<?php
// Tên file: header.php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$current_page = basename($_SERVER['PHP_SELF']);

// Fetch list of pending deletions for Admin & Leader globally
$pendingDeletesList = [];
if (isset($_SESSION['role']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'leader')) {
    if ($_SESSION['role'] === 'admin') {
        $stmtPD = $pdo->query("SELECT id, name FROM customers WHERE pending_delete = 1");
        $pendingDeletesList = $stmtPD->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmtPD = $pdo->prepare("SELECT c.id, c.name FROM customers c LEFT JOIN users u ON c.sale_id = u.id WHERE c.pending_delete = 1 AND (c.sale_id = ? OR u.leader_id = ?)");
        $stmtPD->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
        $pendingDeletesList = $stmtPD->fetchAll(PDO::FETCH_ASSOC);
    }
}
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
        body {
            background-color: #f4f6f9;
        }

        .sidebar {
            min-height: 100vh;
            background-color: #343a40;
            color: white;
            padding-top: 20px;
        }

        .sidebar a {
            color: #adb5bd;
            text-decoration: none;
            padding: 10px 20px;
            display: block;
            font-weight: 500;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background-color: #495057;
            color: white;
            border-radius: 5px;
            margin: 0 10px;
        }

        .main-content {
            padding: 20px;
        }

        .navbar-brand {
            font-weight: bold;
            color: #fff;
            padding-left: 20px;
            margin-bottom: 20px;
            display: block;
        }
    </style>
</head>

<body>
    <div class="container-fluid p-0">
        <div class="row g-0">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar d-none d-md-block">
                <span class="navbar-brand fs-4"><i class="bi bi-wallet2"></i> Quản Lý Trả Góp</span>

                <a href="index.php" class="<?= $current_page == 'index.php' ? 'active' : ''?>">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>

                <a href="customers.php"
                    class="<?= in_array($current_page, ['customers.php', 'add_customer.php', 'customer_detail.php']) ? 'active' : ''?>">
                    <i class="bi bi-people"></i> Khách Hàng
                </a>

                <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'leader'): ?>
                <a href="users.php" class="<?= $current_page == 'users.php' ? 'active' : ''?>">
                    <i class="bi bi-person-badge"></i> Quản Lý Nhân Sự
                </a>
                <?php
endif; ?>
                <?php if ($_SESSION['role'] === 'admin'): ?>
                <a href="logs.php" class="<?= $current_page == 'logs.php' ? 'active' : ''?>">
                    <i class="bi bi-journals"></i> Lịch Sử (Logs)
                </a>
                <?php
endif; ?>

                <div class="mt-auto pt-4 border-top border-secondary mx-3"></div>
                <div class="px-3 text-light pb-2">
                    <small>Xin chào,</small><br>
                    <strong>
                        <?= htmlspecialchars($_SESSION['username'])?>
                    </strong> (
                    <?= strtoupper($_SESSION['role'])?>)
                </div>
                <a href="logout.php" class="text-danger mt-2">
                    <i class="bi bi-box-arrow-right"></i> Đăng Xuất
                </a>
            </div>

            <!-- Nút hiển thị trên mobile -->
            <div class="d-md-none bg-dark text-white p-2 d-flex justify-content-between align-items-center">
                <strong><i class="bi bi-wallet2"></i> </strong>
                <a href="logout.php" class="btn btn-sm btn-danger text-white">Đăng Xuất</a>
            </div>

            <!-- Content Area -->
            <div class="col-md-10 main-content">
                <?php if (!empty($pendingDeletesList)): ?>
                    <div class="alert alert-danger shadow-sm mb-4 border-danger">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <strong class="text-danger"><i class="bi bi-exclamation-octagon-fill fs-5 me-2"></i> 
                            Hệ thống đang có <?= count($pendingDeletesList) ?> khách hàng chờ DUYỆT XOÁ:</strong>
                            <a href="customers.php" class="btn btn-sm btn-danger fw-bold px-3">Xem tất cả <i class="bi bi-arrow-right-short"></i></a>
                        </div>
                        <div class="mt-2">
                            <?php foreach ($pendingDeletesList as $pd): ?>
                                <a href="customer_detail.php?id=<?= $pd['id'] ?>" class="btn btn-sm btn-outline-danger me-2 mb-2 fw-bold" style="background-color: #fff;">
                                    <i class="bi bi-person-x"></i> <?= htmlspecialchars($pd['name']) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>