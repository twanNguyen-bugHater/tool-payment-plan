<?php
// Tên file: logout.php
session_start();
require 'db.php';

// Ghi nhật ký trước khi xóa session
if (isset($_SESSION['user_id'])) {
    $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description) VALUES (?, ?, ?)");
    $logStmt->execute([$_SESSION['user_id'], 'LOGOUT', 'Đăng xuất khỏi hệ thống.']);
}

// Xóa session
session_unset();
session_destroy();
header("Location: login.php");
exit;
?>
