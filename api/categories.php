<?php
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();

if ($method === 'GET') {
    $rows = $db->query('SELECT name FROM categories ORDER BY id ASC')->fetchAll();
    sendJSON(['success' => true, 'categories' => array_column($rows, 'name')]);

} elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    if ($action === 'add') {
        $name = trim($input['name'] ?? '');
        if (!$name) sendJSON(['success' => false, 'error' => 'Name required']);
        try {
            $db->prepare('INSERT INTO categories (name) VALUES (?)')->execute([$name]);
            sendJSON(['success' => true]);
        } catch (PDOException $e) {
            sendJSON(['success' => false, 'error' => 'exists']);
        }
    } elseif ($action === 'delete') {
        $name = trim($input['name'] ?? '');
        $db->prepare('DELETE FROM categories WHERE name = ?')->execute([$name]);
        sendJSON(['success' => true]);
    } else {
        sendJSON(['success' => false, 'error' => 'Unknown action']);
    }
} else {
    sendJSON(['success' => false, 'error' => 'Method not allowed']);
}
