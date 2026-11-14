<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') sendJSON(['success' => false, 'error' => 'POST required']);

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) sendJSON(['success' => false, 'error' => 'Invalid JSON']);

// Handle large JS timestamps safely (string, not int)
$id       = isset($input['id'])       ? preg_replace('/[^0-9]/', '', (string)$input['id']) : '';
$code     = isset($input['code'])     ? trim($input['code'])     : '';
$title    = isset($input['title'])    ? trim($input['title'])    : '';
$price    = isset($input['price'])    ? trim($input['price'])    : '';
$weight   = isset($input['weight'])   ? trim($input['weight'])   : '';
$category = isset($input['category']) ? trim($input['category']) : '';
$expDate  = isset($input['expDate'])  ? trim($input['expDate'])  : '';
$createdBy = isset($input['created_by']) ? trim($input['created_by']) : '';  // FIXED: snake_case
$date     = isset($input['date'])     ? trim($input['date'])     : date('Y-m-d H:i:s');

if (!$id)    $id = (string)(time() . rand(100,999));
if (!$code || !$title) sendJSON(['success' => false, 'error' => 'code and title required']);

$db = getDB();

// Check duplicate code (different id)
$chk = $db->prepare('SELECT id FROM barcodes WHERE code = ? AND id != ?');
$chk->execute([$code, $id]);
if ($chk->fetch()) sendJSON(['success' => false, 'error' => 'duplicate']);

// Fixed query: 9 columns, 9 placeholders, and ON DUPLICATE KEY UPDATE excludes created_by
$stmt = $db->prepare('
    INSERT INTO barcodes (id, code, title, price, weight, category, exp_date, created_by, date_added)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        title = VALUES(title),
        price = VALUES(price),
        weight = VALUES(weight),
        category = VALUES(category),
        exp_date = VALUES(exp_date)
');
$stmt->execute([$id, $code, $title, $price, $weight, $category, $expDate, $createdBy, $date]);
sendJSON(['success' => true, 'id' => $id]);
?>