<?php
require_once 'config.php';

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $rows = $db->query('SELECT key_name, value FROM settings')->fetchAll();
    $settings = [];
    foreach ($rows as $r) $settings[$r['key_name']] = $r['value'];
    sendJSON(['success' => true, 'settings' => $settings]);

} elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) sendJSON(['success' => false, 'error' => 'Invalid JSON']);

    // SECURITY FIX: this endpoint used to save ANY key sent to it with no
    // permission check at all - a non-admin user could bypass the hidden
    // UI entirely and POST directly to change shop_name/logo_url/prefix
    // even with perm_shop_name / perm_logo / is_admin = false. Each
    // restricted key is now checked against the SAME session permissions
    // (from api/config.php) that gate the UI, so hiding the input box is
    // no longer the only thing stopping a non-permitted user.
    $restricted = [
        'shop_name'      => $permShopName,
        'logo_url'       => $permLogo,
        'default_prefix' => $isAdmin, // Prefix is admin-only, not a per-user permission
    ];
    $rejected = [];
    foreach ($input as $key => $val) {
        if (array_key_exists($key, $restricted) && !$restricted[$key]) {
            $rejected[] = $key;
            continue;
        }
    }

    $stmt = $db->prepare('INSERT INTO settings (key_name, value) VALUES (?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)');
    foreach ($input as $key => $val) {
        if (in_array($key, $rejected, true)) continue;
        $stmt->execute([trim($key), trim((string)$val)]);
    }

    if ($rejected) {
        sendJSON(['success' => false, 'error' => 'forbidden', 'rejected' => $rejected]);
    }
    sendJSON(['success' => true]);
} else {
    sendJSON(['success' => false, 'error' => 'Method not allowed']);
}
