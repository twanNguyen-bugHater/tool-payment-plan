<?php
// Tên file: update_payment.php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['id']) || $_GET['action'] !== 'paid') {
    die("Invalid request");
}

$installment_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

try {
    $pdo->beginTransaction();

    // Lấy thông tin installment
    $stmt = $pdo->prepare("SELECT i.*, c.name, c.remaining FROM installments i JOIN customers c ON i.customer_id = c.id WHERE i.id = ?");
    $stmt->execute([$installment_id]);
    $inst = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($inst && $inst['status'] === 'pending') {
        // Cập nhật trạng thái thành 'paid' và ngày trả tiền là ngày hôm nay
        $today = date('Y-m-d');
        $upd = $pdo->prepare("UPDATE installments SET status = 'paid', payment_date = ? WHERE id = ?");
        $upd->execute([$today, $installment_id]);

        // Cập nhật số tiền remaining cho bảng khách hàng
        // Lấy số dư hiện tại trừ đi số tiền bill của installment này
        $new_remaining = $inst['remaining'] - $inst['amount'];
        // Không để số dư âm (đôi khi khách đóng dư tí)
        if ($new_remaining < 0) $new_remaining = 0; 
        
        $updCust = $pdo->prepare("UPDATE customers SET remaining = ? WHERE id = ?");
        $updCust->execute([$new_remaining, $inst['customer_id']]);

        // Ghi Log
        $moneyFmt = number_format($inst['amount'], 2);
        $desc = "Đã thu tiền Đợt {$inst['payment_number']} ({$moneyFmt}) của khách hàng '{$inst['name']}'. (Ngày thu: $today)";
        
        $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description) VALUES (?, ?, ?)");
        $logStmt->execute([$user_id, 'UPDATE_PAYMENT', $desc]);

        $pdo->commit();
    } else {
        $pdo->rollBack(); // Installment đã update trước đó hoặc ko tìm thấy
    }

    header("Location: index.php");
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Lỗi kỹ thuật: " . $e->getMessage());
}
?>
