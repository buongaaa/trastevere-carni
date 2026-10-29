<?php
// Dopo il ritorno da Stripe il sito chiede qui se il pagamento è andato a buon fine.
require __DIR__ . '/lib.php';

$sid = (string) ($_GET['session_id'] ?? '');
if (!preg_match('/^cs_(test|live)_[A-Za-z0-9]+$/', $sid)) json_out(['error' => 'Sessione non valida'], 400);

try {
    $s = stripe('GET', 'checkout/sessions/' . $sid);
} catch (Throwable $e) {
    json_out(['error' => $e->getMessage()], 502);
}
json_out([
    'id' => $s['client_reference_id'] ?? null,
    'completed' => ($s['status'] ?? '') === 'complete',
    'payment_status' => $s['payment_status'] ?? null,
]);
