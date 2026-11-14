<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

// Admin only
if (empty($_SESSION['is_admin'])) {
    echo json_encode([
        'success' => false,
        'msg' => 'Access denied'
    ]);
    exit;
}

$conn = new mysqli(
    "sql110.infinityfree.com",
    "if0_40706792",
    "NewTree1",
    "if0_40706792_barcodes"
);

if ($conn->connect_error) {
    echo json_encode([
        'success' => false,
        'msg' => 'Database connection failed'
    ]);
    exit;
}

$conn->set_charset('utf8mb4');

$user_id = (int)($_POST['user_id'] ?? 0);

$perm_settings  = !empty($_POST['perm_settings']) ? 1 : 0;
$perm_shop_name = !empty($_POST['perm_shop_name']) ? 1 : 0;
$perm_logo      = !empty($_POST['perm_logo']) ? 1 : 0;
$perm_data_mang = !empty($_POST['perm_data_mang']) ? 1 : 0;

if ($user_id <= 0) {
    echo json_encode([
        'success' => false,
        'msg' => 'Invalid user'
    ]);
    exit;
}

$stmt = $conn->prepare("
    INSERT INTO user_permissions
    (
        user_id,
        perm_settings,
        perm_shop_name,
        perm_logo,
        perm_data_mang
    )
    VALUES (?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        perm_settings  = VALUES(perm_settings),
        perm_shop_name = VALUES(perm_shop_name),
        perm_logo      = VALUES(perm_logo),
        perm_data_mang = VALUES(perm_data_mang)
");

if (!$stmt) {
    echo json_encode([
        'success' => false,
        'msg' => $conn->error
    ]);
    exit;
}

$stmt->bind_param(
    "iiiii",
    $user_id,
    $perm_settings,
    $perm_shop_name,
    $perm_logo,
    $perm_data_mang
);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true
    ]);
} else {
    echo json_encode([
        'success' => false,
        'msg' => $stmt->error
    ]);
}

$stmt->close();
$conn->close();
?>