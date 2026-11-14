<?php
require_once 'config.php';
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

// PERF: on a remote DB host (e.g. InfinityFree's sql*.infinityfree.com),
// every separate request pays its own full network + DB-connect + query
// round trip. Page load used to fire 3 of these in parallel
// (settings.php, categories.php, load.php). This does all 3 queries on
// ONE connection in ONE request, cutting 2 of those round trips.
$db = getDB();

$settingsRows = $db->query('SELECT key_name, value FROM settings')->fetchAll();
$settings = [];
foreach ($settingsRows as $r) $settings[$r['key_name']] = $r['value'];

$categoryRows = $db->query('SELECT name FROM categories ORDER BY id ASC')->fetchAll();
$categories = array_column($categoryRows, 'name');

$barcodeRows = $db->query(
    'SELECT id, code, title, price, weight, category, exp_date, created_by, date_added, disabled
     FROM barcodes ORDER BY date_added DESC'
)->fetchAll();
$barcodes = array_map(function ($r) {
    return [
        'id'         => $r['id'],
        'code'       => $r['code'],
        'title'      => $r['title'],
        'price'      => $r['price'],
        'weight'     => $r['weight'],
        'category'   => $r['category'],
        'expDate'    => $r['exp_date'],
        'created_by' => $r['created_by'],
        'date'       => $r['date_added'],
        'disabled'   => (bool)$r['disabled'],
    ];
}, $barcodeRows);

sendJSON([
    'success'    => true,
    'settings'   => $settings,
    'categories' => $categories,
    'barcodes'   => $barcodes,
]);
