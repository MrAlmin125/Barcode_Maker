<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') sendJSON(['success' => false, 'error' => 'POST required']);
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) sendJSON(['success' => false, 'error' => 'Missing id']);

$db = getDB();

// PERF FIX: supports bulk delete (input['ids'] = [...]) in a single query,
// so "delete all" / multi-select no longer needs one HTTP round-trip and
// one DELETE statement per row.
// SECURITY FIX: "delete all" is a data-management action - it was only
// gated by hiding the button in settings.php. Now enforced server-side
// against the same perm_data_mang permission, so a non-permitted user
// can't wipe every barcode by calling this endpoint directly.
if (isset($input['ids']) && is_array($input['ids'])) {
    if (!$permDataMang) sendJSON(['success' => false, 'error' => 'forbidden']);

    $ids = array_values(array_unique(array_filter(array_map(function ($v) {
        return preg_replace('/[^0-9]/', '', (string)$v);
    }, $input['ids']))));
    if (!$ids) sendJSON(['success' => false, 'error' => 'Invalid ids']);

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("DELETE FROM barcodes WHERE id IN ($placeholders)");
    $stmt->execute($ids);
    sendJSON(['success' => true, 'deleted' => $stmt->rowCount()]);
}

if (!isset($input['id'])) sendJSON(['success' => false, 'error' => 'Missing id']);
$id = preg_replace('/[^0-9]/', '', (string)$input['id']);
if (!$id) sendJSON(['success' => false, 'error' => 'Invalid id']);

$stmt = $db->prepare('DELETE FROM barcodes WHERE id = ?');
$stmt->execute([$id]);
sendJSON(['success' => true, 'deleted' => $stmt->rowCount()]);
