<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') sendJSON(['success' => false, 'error' => 'POST required']);

// SECURITY: Data Import is a data-management action - enforce the same
// perm_data_mang permission server-side that hides the Import button in
// settings.php, so a non-permitted user can't call this endpoint directly.
if (!$permDataMang) sendJSON(['success' => false, 'error' => 'forbidden']);

$input = json_decode(file_get_contents('php://input'), true);
$items = isset($input['items']) && is_array($input['items']) ? $input['items'] : [];
if (!$items) sendJSON(['success' => false, 'error' => 'No items']);
if (count($items) > 5000) sendJSON(['success' => false, 'error' => 'Too many items (max 5000 per import)']);

$db = getDB();

// Normalize + validate every row first (cheap, no DB calls yet).
$clean = [];
$seenCodes = [];
$skipped = 0;
foreach ($items as $i => $raw) {
    $code  = isset($raw['code'])  ? trim((string)$raw['code'])  : '';
    $title = isset($raw['title']) ? trim((string)$raw['title']) : '';
    if (!$code || !$title) { $skipped++; continue; }
    if (isset($seenCodes[$code])) { $skipped++; continue; } // duplicate within the same file
    $seenCodes[$code] = true;

    $id = isset($raw['id']) ? preg_replace('/[^0-9]/', '', (string)$raw['id']) : '';
    if (!$id) $id = (string)(time() . rand(1000, 9999) . $i);

    $clean[] = [
        $id,
        $code,
        $title,
        isset($raw['price'])   ? trim((string)$raw['price'])   : '',
        isset($raw['weight'])  ? trim((string)$raw['weight'])  : '',
        isset($raw['category'])? trim((string)$raw['category']): '',
        isset($raw['expDate']) ? trim((string)$raw['expDate']) : '',
        isset($raw['created_by']) ? trim((string)$raw['created_by']) : '',
        isset($raw['date'])    ? trim((string)$raw['date'])    : date('Y-m-d H:i:s'),
    ];
}

if (!$clean) sendJSON(['success' => true, 'saved' => 0, 'skipped' => $skipped]);

// PERF: one query to find which of the incoming codes already exist,
// instead of a SELECT per row (which is what looping save.php calls used
// to do, N times, over N separate HTTP requests).
$codes = array_column($clean, 1);
$placeholders = implode(',', array_fill(0, count($codes), '?'));
$existing = [];
$chk = $db->prepare("SELECT code FROM barcodes WHERE code IN ($placeholders)");
$chk->execute($codes);
foreach ($chk->fetchAll() as $row) $existing[$row['code']] = true;

$stmt = $db->prepare('
    INSERT INTO barcodes (id, code, title, price, weight, category, exp_date, created_by, date_added)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
');

$saved = 0;
$db->beginTransaction();
try {
    foreach ($clean as $row) {
        if (isset($existing[$row[1]])) { $skipped++; continue; }
        $stmt->execute($row);
        $saved++;
    }
    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
    sendJSON(['success' => false, 'error' => 'Import failed, no rows were saved']);
}

sendJSON(['success' => true, 'saved' => $saved, 'skipped' => $skipped]);
