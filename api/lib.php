<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

const ORDERS_DIR = __DIR__ . '/../ordini';

function json_out(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Il listino è in prezzi.js (lo stesso file che usa il sito): qui si legge l'oggetto JSON al suo interno.
function prezzi(): array {
    $js = (string) file_get_contents(__DIR__ . '/../prezzi.js');
    $a = strpos($js, '{');
    $b = strrpos($js, '}');
    $data = ($a === false || $b === false) ? null : json_decode(substr($js, $a, $b - $a + 1), true);
    if (!is_array($data)) throw new RuntimeException('Listino non leggibile: controlla prezzi.js');
    return $data;
}

function read_json_body(): array {
    $raw = (string) file_get_contents('php://input');
    if (strlen($raw) > 60000) json_out(['error' => 'Richiesta troppo grande'], 413);
    $o = json_decode($raw, true);
    if (!is_array($o)) json_out(['error' => 'Ordine non valido'], 400);
    return $o;
}

function clean($s, int $max = 200): string {
    return mb_substr(trim(strip_tags((string) $s)), 0, $max);
}

function fmt_g(int $g): string {
    if ($g < 1000) return $g . ' g';
    return str_replace('.', ',', rtrim(rtrim(number_format($g / 1000, 2, '.', ''), '0'), '.')) . ' kg';
}

function eur($n): string {
    return number_format((float) $n, 2, ',', '.') . ' €';
}

// Ricalcola tutto lato server: i prezzi inviati dal browser non vengono mai usati.
function compute(array $o, array $P): array {
    $items = [];
    $sub = 0.0; $kgSub = 0.0; $hasPack = false;
    foreach (($o['items'] ?? []) as $it) {
        $name = (string) ($it['name'] ?? '');
        $q = (int) ($it['grams'] ?? 0);
        if (isset($P['pacchi'][$name])) {
            if ($q < 1 || $q > 10) throw new InvalidArgumentException("Quantità non valida per $name");
            $price = $P['pacchi'][$name] * $q;
            $qty = $q === 1 ? '1 pacco' : "$q pacchi";
            $hasPack = true;
        } elseif (isset($P['kg'][$name])) {
            if ($q < 100 || $q > 20000) throw new InvalidArgumentException("Quantità non valida per $name");
            $price = $P['kg'][$name] * $q / 1000;
            $qty = fmt_g($q);
            $kgSub += $price;
        } else {
            throw new InvalidArgumentException("Prodotto non disponibile: $name");
        }
        $items[] = ['name' => $name, 'qty' => $qty, 'q' => $q, 'price' => round($price, 2), 'pack' => isset($P['pacchi'][$name])];
        $sub += $price;
    }
    if (!$items) throw new InvalidArgumentException('Il carrello è vuoto');
    if (count($items) > 40) throw new InvalidArgumentException('Troppi prodotti in un solo ordine');

    $coupon = strtoupper(clean($o['coupon'] ?? '', 30));
    $pct = (float) ($P['coupon'][$coupon] ?? 0);
    if (!$pct) $coupon = '';
    $disc = $sub * $pct;

    $mode = ($o['mode'] ?? '') === 'consegna' ? 'consegna' : 'ritiro';
    $C = $P['consegna'];
    $ship = 0.0;
    if ($mode === 'consegna') {
        $cap = clean($o['address']['cap'] ?? '', 5);
        if (!in_array($cap, $C['cap'], true)) throw new InvalidArgumentException('Il CAP indicato non è nella zona di consegna');
        if ($sub < $C['minimo']) throw new InvalidArgumentException('Ordine minimo per la consegna: ' . eur((float) $C['minimo']));
        $free = $C['gratisSopra'] ?? null;
        if (!$free || $sub - $disc < $free) $ship = (float) $C['costo'];
    }
    $tot = $sub - $disc + $ship;
    $margin = $kgSub > 0 ? round($kgSub * (1 - $pct) * (float) $P['marginePeso'], 2) : 0.0;
    return compact('items', 'sub', 'disc', 'ship', 'tot', 'hasPack', 'kgSub', 'mode', 'coupon', 'pct', 'margin');
}

function new_order_id(): string {
    return 'TC-' . date('md') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 5));
}

// Dati del cliente ripuliti e controllati
function build_order(array $o, array $c, string $payment): array {
    $email = clean($o['customer']['email'] ?? '', 120);
    $phone = clean($o['customer']['phone'] ?? '', 30);
    $name  = clean($o['customer']['name'] ?? '', 80);
    if (mb_strlen($name) < 3) throw new InvalidArgumentException('Inserisci nome e cognome');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Email non valida');
    if (strlen(preg_replace('/\D/', '', $phone)) < 8) throw new InvalidArgumentException('Telefono non valido');
    $date = (string) ($o['date'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) throw new InvalidArgumentException('Giorno non valido');
    $slot = array_map('floatval', array_slice((array) ($o['slot'] ?? []), 0, 2));
    if (count($slot) !== 2) throw new InvalidArgumentException('Fascia oraria non valida');

    return [
        'id' => new_order_id(),
        'created' => date('c'),
        'status' => 'nuovo',
        'payment' => $payment,
        'items' => $c['items'],
        'mode' => $c['mode'],
        'date' => $date,
        'dayLabel' => clean($o['dayLabel'] ?? $date, 40),
        'slot' => $slot,
        'customer' => ['name' => $name, 'phone' => $phone, 'email' => $email, 'newsletter' => !empty($o['customer']['newsletter'])],
        'address' => $c['mode'] === 'consegna' ? [
            'street' => clean($o['address']['street'] ?? '', 150),
            'cap' => clean($o['address']['cap'] ?? '', 5),
            'bell' => clean($o['address']['bell'] ?? '', 80),
        ] : null,
        'note' => clean($o['note'] ?? '', 500),
        'coupon' => $c['coupon'],
        'invoice' => !empty($o['invoice']) ? ['vat' => clean($o['invoice']['vat'] ?? '', 20), 'sdi' => clean($o['invoice']['sdi'] ?? '', 80)] : null,
        'totals' => [
            'subtotal' => round($c['sub'], 2), 'discount' => round($c['disc'], 2), 'delivery' => $c['ship'],
            'total' => round($c['tot'], 2), 'margin' => $c['margin'],
        ],
    ];
}

function save_order(array $order): void {
    if (!is_dir(ORDERS_DIR)) {
        mkdir(ORDERS_DIR, 0750, true);
        file_put_contents(ORDERS_DIR . '/.htaccess', "Require all denied\nDeny from all\n");
    }
    file_put_contents(ORDERS_DIR . '/' . $order['id'] . '.json', json_encode($order, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function load_order(string $id): ?array {
    if (!preg_match('/^TC-\d{4}-[A-Z0-9]{5}$/', $id)) return null;
    $f = ORDERS_DIR . "/$id.json";
    return is_file($f) ? json_decode((string) file_get_contents($f), true) : null;
}

// Chiamata all'API di Stripe senza librerie esterne
function stripe(string $method, string $path, array $params = []): array {
    $ch = curl_init('https://api.stripe.com/v1/' . $path . ($method === 'GET' && $params ? '?' . http_build_query($params) : ''));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => STRIPE_SECRET_KEY . ':',
        CURLOPT_TIMEOUT => 30,
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    }
    $res = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = json_decode((string) $res, true) ?: [];
    if ($code >= 400 || !$data) {
        error_log('Stripe: ' . ($data['error']['message'] ?? "HTTP $code"));
        throw new RuntimeException('Pagamento non disponibile al momento');
    }
    return $data;
}

function slot_label(array $s): string {
    $f = fn($h) => sprintf('%d:%02d', (int) $h, (int) round(fmod($h, 1) * 60));
    return $f($s[0]) . ' – ' . $f($s[1]);
}

function order_html(array $o, bool $forShop): string {
    $h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    $rows = '';
    foreach ($o['items'] as $i) $rows .= "<tr><td>{$h($i['name'])}</td><td>{$h($i['qty'])}</td><td align='right'>" . eur($i['price']) . '</td></tr>';
    $t = $o['totals'];
    if ($t['discount']) $rows .= "<tr><td colspan='2'>Sconto {$h($o['coupon'])}</td><td align='right'>−" . eur($t['discount']) . '</td></tr>';
    if ($o['mode'] === 'consegna') $rows .= "<tr><td colspan='2'>Consegna</td><td align='right'>" . ($t['delivery'] ? eur($t['delivery']) : 'Gratis') . '</td></tr>';
    $rows .= "<tr><td colspan='2'><b>Totale</b></td><td align='right'><b>" . eur($t['total']) . '</b></td></tr>';
    $pay = ['online' => 'Pagato online (Stripe)', 'negozio' => $o['mode'] === 'consegna' ? 'Da pagare alla consegna' : 'Da pagare al ritiro'][$o['payment']] ?? $o['payment'];
    $where = $o['address'] ? "{$h($o['address']['street'])}, {$h($o['address']['cap'])} Roma" . ($o['address']['bell'] ? " ({$h($o['address']['bell'])})" : '') : 'Ritiro in negozio';
    $c = $o['customer'];
    $html = "<div style='font-family:Arial,sans-serif;font-size:15px;color:#222;max-width:560px'>"
        . "<h2 style='margin:0 0 6px'>Ordine {$h($o['id'])}</h2>"
        . "<p style='margin:0 0 16px'><b>" . ($o['mode'] === 'consegna' ? 'Consegna' : 'Ritiro') . ":</b> {$h($o['dayLabel'])}, " . slot_label($o['slot']) . "<br>$where</p>"
        . "<table cellpadding='6' style='border-collapse:collapse;width:100%;border-top:1px solid #ddd'>$rows</table>"
        . "<p><b>Pagamento:</b> {$h($pay)}</p>";
    if ($o['payment'] === 'online' && $t['margin'] > 0)
        $html .= "<p style='color:#666;font-size:13px'>La carne a peso viene pesata al momento: l'addebito finale corrisponde al peso reale. Sulla carta è bloccato al massimo " . eur($t['total'] + $t['margin']) . ".</p>";
    if ($forShop) {
        $html .= "<p><b>Cliente:</b> {$h($c['name'])}<br>Tel: {$h($c['phone'])}<br>Email: {$h($c['email'])}</p>";
        if ($o['note']) $html .= "<p><b>Note:</b> {$h($o['note'])}</p>";
        if ($o['invoice']) $html .= "<p><b>Fattura:</b> {$h($o['invoice']['vat'])} — SDI/PEC: {$h($o['invoice']['sdi'])}</p>";
        if ($o['payment'] === 'online' && $t['margin'] > 0)
            $html .= "<p style='background:#fff4d6;padding:10px'>Dopo aver pesato, incassa l'importo reale da Stripe: Pagamenti → apri il pagamento → <b>Acquisisci</b> → inserisci l'importo. Va fatto entro 7 giorni.</p>";
    } else {
        $html .= "<p>Per qualsiasi cosa scrivici su WhatsApp al 338 903 0246 o chiama lo 06 4555 6793.</p><p>Grazie,<br>" . SHOP_NAME . "<br>Via Guglielmo Mengarini 67/69, Roma</p><p style='color:#999;font-size:12px'>Trastevere Carni S.r.l. · P.IVA 14039211009</p>";
    }
    return $html . '</div>';
}

function send_mail(string $to, string $subject, string $html, string $replyTo = ''): void {
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . SHOP_NAME . ' <' . FROM_EMAIL . '>',
    ];
    if ($replyTo) $headers[] = 'Reply-To: ' . $replyTo;
    if (!@mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $html, implode("\r\n", $headers)))
        error_log("Email non inviata a $to");
}

function notify(array $o): void {
    $label = $o['payment'] === 'online' ? 'pagato online' : 'da pagare ' . ($o['mode'] === 'consegna' ? 'alla consegna' : 'al ritiro');
    send_mail(SHOP_EMAIL, "Nuovo ordine {$o['id']} — {$o['customer']['name']} ($label)", order_html($o, true), $o['customer']['email']);
    send_mail($o['customer']['email'], "Il tuo ordine {$o['id']} da " . SHOP_NAME, order_html($o, false), SHOP_EMAIL);
}
