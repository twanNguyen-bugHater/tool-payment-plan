<?php
// Tên file: db.php
$host = 'localhost';
$dbname = 'tra_gop_official';
$username = 'root';
$password = 'root';

try {
    // MAMP trên Mac đôi khi cần port 8889, nếu lỗi hãy đổi $host thành 'localhost;port=8889'
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}
catch (PDOException $e) {
    die("Lỗi kết nối DB: " . $e->getMessage());
}
?>