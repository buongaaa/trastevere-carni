<?php
// Crea il pagamento su Stripe Checkout e restituisce l'indirizzo della pagina di pagamento.
require __DIR__ . '/lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['error' => 'Metodo non consentito'], 405);

try {
    $in = read_json_body();
    $c = compute($in, prezzi());
    $order = build_order($in, $c, 'online');
} catch (InvalidArgumentException $e) {
    json_out(['error' => $e->getMessage()], 422);
} catch (Throwable $e) {
    error_log('checkout: ' . $e->getMessage());
    json_out(['error' => 'Errore nel preparare il pagamento'], 500);
}

$pct = $c['pct'];
$lines = [];
foreach ($c['items'] as $i) {
    $lines[] = [
        'quantity' => 1,
        'price_data' => [
            'currency' => 'eur',
            'unit_amount' => (int) round($i['price'] * (1 - $pct) * 100),
            'product_data' => ['name' => $i['name'] . ' · ' . $i['qty'] . ($pct ? ' (sconto ' . round($pct * 100) . '%)' : '')],
        ],
    ];
}
if ($c['ship'] > 0) {
    $lines[] = ['quantity' => 1, 'price_data' => ['currency' => 'eur', 'unit_amount' => (int) round($c['ship'] * 100), 'product_data' => ['name' => 'Consegna a domicilio']]];
}
// La carne a peso si paga sul peso reale: si blocca un piccolo margine e si incassa solo il dovuto (cattura manuale).
if ($c['margin'] > 0) {
    $lines[] = ['quantity' => 1, 'price_data' => ['currency' => 'eur', 'unit_amount' => (int) round($c['margin'] * 100), 'product_data' => [
        'name' => 'Margine per peso variabile',
        'description' => 'Solo bloccato, non addebitato: paghi il peso reale della carne.',
    ]]];
}

$id = $order['id'];
try {
    $session = stripe('POST', 'checkout/sessions', [
        'mode' => 'payment',
        'locale' => 'it',
        'line_items' => $lines,
        'customer_email' => $order['customer']['email'],
        'client_reference_id' => $id,
        'metadata' => ['ordine' => $id],
        'payment_intent_data' => [
            'capture_method' => $c['margin'] > 0 ? 'manual' : 'automatic',
            'description' => "Ordine $id — " . $order['customer']['name'],
            'metadata' => ['ordine' => $id],
        ],
        'expires_at' => time() + 3600,
        'success_url' => SITE_URL . '/?ordine=ok&session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => SITE_URL . '/?ordine=annullato',
    ]);
} catch (Throwable $e) {
    json_out(['error' => $e->getMessage()], 502);
}

$order['status'] = 'in_attesa_pagamento';
$order['stripe_session'] = $session['id'];
save_order($order);

json_out(['url' => $session['url'], 'id' => $id, 'totals' => $order['totals']]);
