<?php
require_once __DIR__ . '/api/config.php';
requireAdmin($isAdmin);

$conn = getMysqli();
if ($conn->connect_error) { echo json_encode(['success'=>false,'error'=>'db']); exit; }

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

if ($action === 'add') {
    $username = trim($data['username'] ?? '');
    $password = trim($data['password'] ?? '');
    // NEW: role support
    $is_admin = isset($data['is_admin']) && $data['is_admin'] ? 1 : 0;

    if (!preg_match('/^\d{6,}$/', $password)) {
        echo json_encode(['success'=>false,'error'=>'invalid_password']); exit;
    }
    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $check = $conn->prepare("SELECT id FROM users WHERE username=?");
    $check->bind_param("s", $username); $check->execute(); $check->store_result();
    if ($check->num_rows > 0) { echo json_encode(['success'=>false,'error'=>'duplicate']); exit; }

    $stmt = $conn->prepare("INSERT INTO users(username,password,is_admin) VALUES(?,?,?)");
    $stmt->bind_param("ssi", $username, $hashed, $is_admin);
    $stmt->execute();
    echo json_encode(['success'=>true, 'id'=>$conn->insert_id, 'is_admin'=>$is_admin]);

} elseif ($action === 'edit') {
    $id = (int)($data['id'] ?? 0);
    $username = trim($data['username'] ?? '');
    $password = trim($data['password'] ?? '');
    // NEW: role update
    $is_admin = isset($data['is_admin']) && $data['is_admin'] ? 1 : 0;

    if (!preg_match('/^\d{6,}$/', $password)) {
        echo json_encode(['success'=>false,'error'=>'invalid_password']); exit;
    }
    $hashed = password_hash($password, PASSWORD_DEFAULT);
    // Allow admin to change another admin's role too (but not their own)
    $selfId = (int)$_SESSION['user_id'];
    if ($id === $selfId) {
        // Own account: don't change is_admin
        $stmt = $conn->prepare("UPDATE users SET username=?, password=? WHERE id=?");
        $stmt->bind_param("ssi", $username, $hashed, $id);
    } else {
        $stmt = $conn->prepare("UPDATE users SET username=?, password=?, is_admin=? WHERE id=?");
        $stmt->bind_param("ssii", $username, $hashed, $is_admin, $id);
    }
    $stmt->execute();
    echo json_encode(['success'=>true]);

} elseif ($action === 'delete') {
    $id = (int)($data['id'] ?? 0);
    $selfId = (int)$_SESSION['user_id'];
    if ($id === $selfId) { echo json_encode(['success'=>false,'error'=>'cannot_delete_self']); exit; }
    $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    echo json_encode(['success'=>true]);
}
?>