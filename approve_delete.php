<?php
// Tên file: approve_delete.php
require 'db.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['id']) || !isset($_GET['action'])) {
    header("Location: customers.php");
    exit;
}
$id = intval($_GET['id']);
$action = $_GET['action']; // 'approve' or 'reject'

$stmt = $pdo->prepare("SELECT c.*, u.leader_id FROM customers c LEFT JOIN users u ON c.sale_id = u.id WHERE c.id = ?");
$stmt->execute([$id]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer || $customer['pending_delete'] != 1) {
    header("Location: customers.php");
    exit;
}

// Kiểm tra quyền: Chỉ Admin hoặc Leader quản lý team đó mới được duyệt
$can_approve = false;
if ($_SESSION['role'] === 'admin') {
    $can_approve = true;
} elseif ($_SESSION['role'] === 'leader' && ($customer['sale_id'] == $_SESSION['user_id'] || $customer['leader_id'] == $_SESSION['user_id'])) {
    $can_approve = true;
}

if (!$can_approve) {
    die("Bạn không có quyền thao tác trên khách hàng này.");
}

if ($action === 'approve') {
    // Xoá file đính kèm
    if (!empty($customer['treatment_file']) && file_exists($customer['treatment_file']) && strpos($customer['treatment_file'], 'uploads/') !== false) {
        @unlink($customer['treatment_file']);
    }

    // Xoá các đợt trả góp
    $pdo->prepare("DELETE FROM installments WHERE customer_id = ?")->execute([$id]);

    // Xoá hồ sơ khách hàng
    $pdo->prepare("DELETE FROM customers WHERE id = ?")->execute([$id]);

    // Lịch sử
    $actionMsg = "Dựệt xoá khách hàng: " . $customer['name'] . " (ID: $id)";
    $pdo->prepare("INSERT INTO activity_logs (user_id, action, description) VALUES (?, 'APPROVE DELETE', ?)")->execute([$_SESSION['user_id'], $actionMsg]);

    header("Location: customers.php?msg=deleted");
    exit;

} elseif ($action === 'reject') {
    // Huỷ yêu cầu xoá, khôi phục lại
    $pdo->prepare("UPDATE customers SET pending_delete = 0 WHERE id = ?")->execute([$id]);

    // Lịch sử
    $actionMsg = "Từ chối yêu cầu xoá khách hàng: " . $customer['name'] . " (ID: $id)";
    $pdo->prepare("INSERT INTO activity_logs (user_id, action, description) VALUES (?, 'REJECT DELETE', ?)")->execute([$_SESSION['user_id'], $actionMsg]);

    header("Location: customer_detail.php?id=$id&msg=delete_rejected");
    exit;
} else {
    header("Location: customers.php");
    exit;
}
?>
