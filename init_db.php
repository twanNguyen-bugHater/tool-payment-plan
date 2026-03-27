<?php
// Tên file: init_db.php
// Chạy file này trên trình duyệt (http://localhost:8888/tool-payment-debt/init_db.php) để tạo các bảng tự động.

require 'db.php';

try {
    // 1. Tạo bảng users
    $sql_users = "
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            email VARCHAR(100),
            role ENUM('admin', 'leader', 'sale') NOT NULL,
            leader_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($sql_users);
    echo "✅ Đã tạo bảng users.<br>";

    // 2. Tạo bảng customers
    $sql_customers = "
        CREATE TABLE IF NOT EXISTS customers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            gender VARCHAR(20),
            sale_id INT NULL,
            total_bill DECIMAL(15,2),
            currency VARCHAR(10),
            treatment_file VARCHAR(255),
            remaining DECIMAL(15,2),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (sale_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($sql_customers);
    echo "✅ Đã tạo bảng customers.<br>";

    // 3. Tạo bảng installments
    $sql_installments = "
        CREATE TABLE IF NOT EXISTS installments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_id INT NOT NULL,
            payment_number INT NOT NULL,
            amount DECIMAL(15,2) NOT NULL,
            due_date DATE NOT NULL,
            status ENUM('pending', 'paid', 'late', 'cancelled') DEFAULT 'pending',
            payment_date DATE NULL,
            note TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($sql_installments);
    echo "✅ Đã tạo bảng installments.<br>";

    // 4. Tạo bảng activity_logs
    $sql_logs = "
        CREATE TABLE IF NOT EXISTS activity_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            action VARCHAR(50) NOT NULL,
            description TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($sql_logs);
    echo "✅ Đã tạo bảng activity_logs.<br>";

    // 5. Tạo tài khoản Admin mặc định
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = 'admin'");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $password = password_hash('admin123', PASSWORD_BCRYPT);
        $pdo->exec("INSERT INTO users (username, password, email, role) VALUES ('admin', '$password', 'admin@example.com', 'admin')");
        echo "✅ 🎉 Đã tạo thành công tài khoản <strong>Admin</strong>. Mật khẩu: <strong>admin123</strong><br>";
    }

    echo "<br><h3 style='color:green;'>TẠO DỮ LIỆU THÀNH CÔNG!</h3>";
    echo "<p>Bây giờ bạn có thể xoá file <code>init_db.php</code> này khỏi thư mục và truy cập <a href='login.php'>Trang Đăng Nhập</a>.</p>";

} catch (PDOException $e) {
    die("<h3 style='color:red;'>LỖI TẠO BẢNG: " . $e->getMessage() . "</h3>");
}
?>
