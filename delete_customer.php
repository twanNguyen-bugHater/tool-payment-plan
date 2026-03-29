<?php
// Tên file: delete_customer.php
require 'db.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: customers.php");
    exit;
}
$id = intval($_GET['id']);

// Lấy thông tin khách hàng để check quyền và lấy tên file
$stmt = $pdo->prepare("SELECT c.*, u.leader_id FROM customers c LEFT JOIN users u ON c.sale_id = u.id WHERE c.id = ?");
$stmt->execute([$id]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    header("Location: customers.php?msg=notfound");
    exit;
}

// Kiểm tra quyền xoá
$can_delete = false;
if ($_SESSION['role'] === 'admin') {
    $can_delete = true;
} elseif ($_SESSION['role'] === 'leader') {
    // Leader có thể xoá khách do họ hoặc nhân viên của họ tạo
    if ($customer['sale_id'] == $_SESSION['user_id'] || $customer['leader_id'] == $_SESSION['user_id']) {
        $can_delete = true;
    }
} elseif ($_SESSION['role'] === 'sale') {
    // Sale chỉ có thể xoá khách của mình
    if ($customer['sale_id'] == $_SESSION['user_id']) {
        $can_delete = true;
    }
}

// Thực hiện logic xoá hoặc yêu cầu xoá
if ($can_delete) {
    if ($_SESSION['role'] === 'sale') {
        // Sale: Chỉ tạo yêu cầu xoá
        $pdo->prepare("UPDATE customers SET pending_delete = 1 WHERE id = ?")->execute([$id]);
        
        $actionMsg = "Tạo yêu cầu xoá khách hàng: " . $customer['name'] . " (ID: $id)";
        $pdo->prepare("INSERT INTO activity_logs (user_id, action, description) VALUES (?, 'REQUEST DELETE', ?)")->execute([$_SESSION['user_id'], $actionMsg]);
        
        header("Location: customers.php?msg=pending_delete");
        exit;
    } else {
        // Admin / Leader: Xoá thẳng luôn
        // 1. Xoá file đính kèm nếu có (lưu ở uploads/)
        if (!empty($customer['treatment_file']) && file_exists($customer['treatment_file']) && strpos($customer['treatment_file'], 'uploads/') !== false) {
            @unlink($customer['treatment_file']);
        }

        // 2. Xoá các đợt trả góp liên quan (Installments)
        $pdo->prepare("DELETE FROM installments WHERE customer_id = ?")->execute([$id]);

        // 3. Xoá hồ sơ khách hàng
        $pdo->prepare("DELETE FROM customers WHERE id = ?")->execute([$id]);

        // 4. Lưu lại lịch sử xoá (Logs)
        $actionMsg = "Đã xoá khách hàng: " . $customer['name'] . " (ID: $id)";
        $pdo->prepare("INSERT INTO activity_logs (user_id, action, description) VALUES (?, 'DELETE', ?)")->execute([$_SESSION['user_id'], $actionMsg]);

        header("Location: customers.php?msg=deleted");
        exit;
    }
} else {
    header("Location: customers.php?msg=nopermission");
    exit;
}
exit;
?>
