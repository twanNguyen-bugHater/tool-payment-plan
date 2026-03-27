<?php
// Tên file: header.php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tool Quản Lý Trả Góp</title>
    <!-- CSS Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: #f4f6f9; }
        .sidebar { min-height: 100vh; background-color: #343a40; color: white; padding-top: 20px;}
        .sidebar a { color: #adb5bd; text-decoration: none; padding: 10px 20px; display: block; font-weight: 500;}
        .sidebar a:hover, .sidebar a.active { background-color: #495057; color: white; border-radius: 5px; margin: 0 10px;}
        .main-content { padding: 20px; }
        .navbar-brand { font-weight: bold; color: #fff; padding-left: 20px; margin-bottom: 20px; display: block;}
    </style>
</head>
<body>
    <div class="container-fluid p-0">
        <div class="row g-0">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar d-none d-md-block">
                <span class="navbar-brand fs-4"><i class="bi bi-wallet2"></i> Quản Lý Nợ</span>
                
                <a href="index.php" class="<?= $current_page == 'index.php' ? 'active' : '' ?>">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
                
                <a href="customers.php" class="<?= $current_page == 'customers.php' || $current_page == 'add_customer.php' ? 'active' : '' ?>">
                    <i class="bi bi-people"></i> Khách Hàng
                </a>
                
                <?php if($_SESSION['role'] === 'admin'): ?>
                <a href="users.php" class="<?= $current_page == 'users.php' ? 'active' : '' ?>">
                    <i class="bi bi-person-badge"></i> Quản Lý Nhân Sự
                </a>
                <a href="logs.php" class="<?= $current_page == 'logs.php' ? 'active' : '' ?>">
                    <i class="bi bi-journals"></i> Lịch Sử (Logs)
                </a>
                <?php endif; ?>

                <div class="mt-auto pt-4 border-top border-secondary mx-3"></div>
                <div class="px-3 text-light pb-2">
                    <small>Xin chào,</small><br>
                    <strong><?= htmlspecialchars($_SESSION['username']) ?></strong> (<?= strtoupper($_SESSION['role']) ?>)
                </div>
                <a href="logout.php" class="text-danger mt-2">
                    <i class="bi bi-box-arrow-right"></i> Đăng Xuất
                </a>
            </div>

            <!-- Nút hiển thị trên mobile -->
            <div class="d-md-none bg-dark text-white p-2 d-flex justify-content-between align-items-center">
                <strong><i class="bi bi-wallet2"></i> Quản Lý Nợ</strong>
                <a href="logout.php" class="btn btn-sm btn-danger text-white">Đăng Xuất</a>
            </div>

            <!-- Content Area -->
            <div class="col-md-10 main-content">
