<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') sendJSON(['success' => false, 'error' => 'POST required']);
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['id'])) sendJSON(['success' => false, 'error' => 'Missing id']);

$id       = preg_replace('/[^0-9]/', '', (string)$input['id']);
$code     = isset($input['code'])     ? trim($input['code'])     : '';
$title    = isset($input['title'])    ? trim($input['title'])    : '';
$price    = isset($input['price'])    ? trim($input['price'])    : '';
$weight   = isset($input['weight'])   ? trim($input['weight'])   : '';
$category = isset($input['category']) ? trim($input['category']) : '';
$expDate  = isset($input['expDate'])  ? trim($input['expDate'])  : '';
// Frontend sends "created_by" (snake_case)
$createdBy = isset($input['created_by']) ? trim($input['created_by']) : '';
$disabled = isset($input['disabled']) ? (bool)$input['disabled'] : false;

if (!$id || !$code || !$title) sendJSON(['success' => false, 'error' => 'Missing required fields']);

$db = getDB();

// Check duplicate barcode (excluding current id)
$chk = $db->prepare('SELECT id FROM barcodes WHERE code = ? AND id != ?');
$chk->execute([$code, $id]);
if ($chk->fetch()) sendJSON(['success' => false, 'error' => 'duplicate']);

// Update query – keep the original created_by if none provided
$stmt = $db->prepare('UPDATE barcodes SET code=?, title=?, price=?, weight=?, category=?, exp_date=?, created_by=?, disabled=? WHERE id=?');
$stmt->execute([$code, $title, $price, $weight, $category, $expDate, $createdBy, (int)$disabled, $id]);

sendJSON(['success' => true]);
?>