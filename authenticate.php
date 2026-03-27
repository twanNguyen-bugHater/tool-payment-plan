<?php
// Tên file: authenticate.php
session_start();
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        header("Location: login.php?error=Vui lòng nhập đầy đủ thông tin");
        exit;
    }

    // Truy vấn user
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Dùng password_verify vì mk admin tạo ra bằng password_hash
    if ($user && password_verify($password, $user['password'])) {
        // Lưu thông tin đăng nhập vào Session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role']; // Sẽ là 'admin', 'leader', hoặc 'sale'
        $_SESSION['leader_id'] = $user['leader_id']; // Dùng cho Sale xem ai là Leader quản lý mình

        // Ghi Log hoạt động vào DB
        $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description) VALUES (?, ?, ?)");
        $logStmt->execute([$user['id'], 'LOGIN', 'Đăng nhập vào hệ thống thành công']);

        // Chuyển hướng tới trang chính
        header("Location: index.php");
        exit;
    } else {
        // Sai tài khoản hoặc mật khẩu
        header("Location: login.php?error=Tên đăng nhập hoặc mật khẩu không chính xác");
        exit;
    }
} else {
    // Không phải POST request
    header("Location: login.php");
    exit;
}
?>
