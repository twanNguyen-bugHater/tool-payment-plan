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
    $stmt = $pdo->prepare("SELECT i.*, c.name, c.remaining, c.id as customer_id FROM installments i JOIN customers c ON i.customer_id = c.id WHERE i.id = ?");
    $stmt->execute([$installment_id]);
    $inst = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($inst && in_array($inst['status'], ['pending', 'late'])) {
        $today = date('Y-m-d');
        
        // 1. Cập nhật trạng thái installment thành 'paid'
        $upd = $pdo->prepare("UPDATE installments SET status = 'paid', payment_date = ? WHERE id = ?");
        $upd->execute([$today, $installment_id]);

        // 2. Cập nhật remaining và debt_status
        $new_remaining = $inst['remaining'] - $inst['amount'];
        $new_debt_status = 'in_progress';
        
        if ($new_remaining <= 0) {
            $new_remaining = 0; 
            $new_debt_status = 'completed';
        }
        
        $updCust = $pdo->prepare("UPDATE customers SET remaining = ?, debt_status = ? WHERE id = ?");
        $updCust->execute([$new_remaining, $new_debt_status, $inst['customer_id']]);

        // 4. Ghi Log
        $moneyFmt = number_format($inst['amount'], 0, ',', '.');
        $desc = "Đã thu tiền Đợt {$inst['payment_number']} ({$moneyFmt}) của khách hàng '{$inst['name']}'. (Ngày thu: $today)";
        
        $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description) VALUES (?, ?, ?)");
        $logStmt->execute([$user_id, 'UPDATE_PAYMENT', $desc]);

        $pdo->commit();
    } else {
        $pdo->rollBack();
    }

    // Hỗ trợ redirect quay lại customer_detail nếu đến từ trang đó
    if (isset($_GET['redirect']) && $_GET['redirect'] === 'customer_detail' && isset($_GET['cid'])) {
        header("Location: customer_detail.php?id=" . intval($_GET['cid']));
    } else {
        header("Location: index.php");
    }
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Lỗi kỹ thuật: " . $e->getMessage());
}
?>
