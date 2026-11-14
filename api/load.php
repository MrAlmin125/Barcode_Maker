<?php
require_once 'config.php';
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

$db = getDB();
$rows = $db->query(
    'SELECT id, code, title, price, weight, category, exp_date, created_by, date_added, disabled
     FROM barcodes ORDER BY date_added DESC'
)->fetchAll();

$barcodes = array_map(function($r) {
    return [
        'id'        => $r['id'],
        'code'      => $r['code'],
        'title'     => $r['title'],
        'price'     => $r['price'],
        'weight'    => $r['weight'],
        'category'  => $r['category'],
        'expDate'   => $r['exp_date'],
        'created_by' => $r['created_by'],   // fixed: snake_case to match JS
        'date'      => $r['date_added'],
        'disabled'  => (bool)$r['disabled'],
    ];
}, $rows);

sendJSON(['success' => true, 'barcodes' => $barcodes]);
?>