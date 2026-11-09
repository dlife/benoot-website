<?php
/* Ontvangt printgegevens van Home Assistant en schrijft laatst_geprint.json
   voor de "Laatst geprint"-regel op benoot.be.
   Aangeroepen door de HA-automatisering via rest_command (website_laatst_geprint). */
header('Content-Type: application/json; charset=utf-8');

$GEHEIM = 'f6f694a3fb52ff99fdbdee24fbd55030';

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in) || ($in['geheim'] ?? '') !== $GEHEIM) {
    http_response_code(403);
    echo '{"ok":false,"fout":"geheim onjuist"}';
    exit;
}

$printerKey = preg_replace('/[^a-z0-9_]/', '', strtolower($in['printer'] ?? ''));
$taak = trim($in['taak'] ?? '');
if ($printerKey === '' || $taak === '') {
    http_response_code(400);
    echo '{"ok":false,"fout":"printer of taak ontbreekt"}';
    exit;
}

$bestand = __DIR__ . '/laatst_geprint.json';
$data = [];
if (is_file($bestand)) {
    $data = json_decode(file_get_contents($bestand), true);
    if (!is_array($data)) $data = [];
}

$gewicht = isset($in['gewicht']) && is_numeric($in['gewicht']) ? round((float)$in['gewicht'], 1) : null;

$data[$printerKey] = [
    'printer'  => $in['printer'],
    'taak'     => mb_substr($taak, 0, 120),
    'gewicht'  => $gewicht,
    'einde'    => $in['einde'] ?? '',
    'opgeslagen' => date('c'),
];
$data['bijgewerkt'] = date('c');

$ok = file_put_contents($bestand, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
if ($ok === false) {
    http_response_code(500);
    echo '{"ok":false,"fout":"kan niet schrijven"}';
    exit;
}
echo '{"ok":true}';
