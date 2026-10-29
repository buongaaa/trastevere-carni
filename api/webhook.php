<?php
// Riceve da Stripe la conferma del pagamento (firmata) e avvisa negozio e cliente.
require __DIR__ . '/lib.php';

$payload = (string) file_get_contents('php://input');
$header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

// Verifica della firma Stripe: t=timestamp, v1=firma HMAC-SHA256 di "timestamp.payload"
$t = null; $sigs = [];
foreach (explode(',', $header) as $part) {
    [$k, $v] = array_pad(explode('=', trim($part), 2), 2, '');
    if ($k === 't') $t = (int) $v;
    if ($k === 'v1') $sigs[] = $v;
}
$expected = hash_hmac('sha256', $t . '.' . $payload, STRIPE_WEBHOOK_SECRET);
$valid = $t && abs(time() - $t) < 300 && array_filter($sigs, fn($s) => hash_equals($expected, $s));
if (!$valid) json_out(['error' => 'Firma non valida'], 400);

$event = json_decode($payload, true);
$session = $event['data']['object'] ?? [];
$order = load_order((string) ($session['client_reference_id'] ?? ''));
if (!$order) json_out(['ok' => true, 'note' => 'ordine non trovato']);

switch ($event['type'] ?? '') {
    case 'checkout.session.completed':
        if (!empty($order['notified'])) break;   // Stripe può inviare lo stesso evento più volte
        $order['status'] = ($session['payment_status'] ?? '') === 'paid' ? 'pagato' : 'autorizzato';
        $order['payment_intent'] = $session['payment_intent'] ?? null;
        $order['notified'] = date('c');
        save_order($order);
        notify($order);
        break;
    case 'checkout.session.expired':
        if ($order['status'] === 'in_attesa_pagamento') {
            $order['status'] = 'scaduto';
            save_order($order);
        }
        break;
}
json_out(['ok' => true]);
