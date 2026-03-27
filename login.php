<?php
// Tên file: login.php
session_start();

// Nếu đã đăng nhập, chuyển hướng về index (dashboard)
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - Tool Quản Lý Trả Góp</title>
    <!-- Phải ưu tiên giao diện đẹp, sử dụng Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .login-card {
            background-color: #fff;
            padding: 2.5rem;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 400px;
        }
        .login-title {
            font-weight: 700;
            color: #333;
            margin-bottom: 30px;
            text-align: center;
        }
    </style>
</head>
<body>

    <div class="login-card">
        <h3 class="login-title">Hệ Thống <br> Quản Lý Trả Góp</h3>
        
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger text-center">
                <?= htmlspecialchars($_GET['error']) ?>
            </div>
        <?php endif; ?>

        <form action="authenticate.php" method="POST">
            <div class="mb-3">
                <label class="form-label fw-semibold text-secondary">Tên đăng nhập</label>
                <input type="text" name="username" class="form-control" placeholder="Nhập tên đăng nhập" required autofocus>
            </div>
            <div class="mb-4">
                <label class="form-label fw-semibold text-secondary">Mật khẩu</label>
                <input type="password" name="password" class="form-control" placeholder="Nhập mật khẩu" required>
            </div>
            <button type="submit" class="btn btn-primary w-100 fw-bold py-2 mb-3">ĐĂNG NHẬP</button>
            <div class="text-center text-muted" style="font-size: 0.9em;">
                Tài khoản mặc định: admin / admin123
            </div>
        </form>
    </div>

</body>
</html>
