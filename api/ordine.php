<?php
// Ordine con pagamento al ritiro o alla consegna: lo salva e avvisa negozio e cliente via email.
require __DIR__ . '/lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['error' => 'Metodo non consentito'], 405);

try {
    $in = read_json_body();
    $c = compute($in, prezzi());
    $order = build_order($in, $c, 'negozio');
} catch (InvalidArgumentException $e) {
    json_out(['error' => $e->getMessage()], 422);
} catch (Throwable $e) {
    error_log('ordine: ' . $e->getMessage());
    json_out(['error' => "Errore nell'invio dell'ordine"], 500);
}

$order['status'] = 'da_preparare';
save_order($order);
notify($order);

json_out(['id' => $order['id'], 'totals' => $order['totals']]);
