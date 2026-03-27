<?php
// Tên file: add_customer_action.php
session_start();
require 'db.php';

// Kiểm tra quyền (chỉ logged in user mới được insert)
if (!isset($_SESSION['user_id'])) {
    die("Unauthorized");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Transaction để đảm bảo tính nhất quán của dữ liệu (cả khách hàng và khoản trả góp phải thành công cùng lúc)
    try {
        $pdo->beginTransaction();

        $name = $_POST['name'] ?? '';
        $gender = $_POST['gender'] ?? '';
        // Ưu tiên sale_id submit (nếu là Admin/Leader) hoặc lấy Session nếu là Sale
        $sale_id = isset($_POST['sale_id']) ? $_POST['sale_id'] : $_SESSION['user_id'];
        
        $total_bill = floatval($_POST['total_bill']);
        $remaining = floatval($_POST['remaining']);
        $currency = $_POST['currency'] ?? 'AUD';
        $treatment_file = $_POST['treatment_file'] ?? '';

        // 1. Insert thông tin khách hàng vào DB Customers
        $stmtStatus = $pdo->prepare("INSERT INTO customers (name, gender, sale_id, total_bill, currency, treatment_file, remaining) 
                                     VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmtStatus->execute([$name, $gender, $sale_id, $total_bill, $currency, $treatment_file, $remaining]);
        $customer_id = $pdo->lastInsertId();

        // 2. Insert các đợt trả góp vào DB Installments
        if (isset($_POST['payment_number']) && is_array($_POST['payment_number'])) {
            $stmtInst = $pdo->prepare("INSERT INTO installments (customer_id, payment_number, amount, due_date, status) VALUES (?, ?, ?, ?, 'pending')");
            
            for ($i = 0; $i < count($_POST['payment_number']); $i++) {
                $payment_number = $_POST['payment_number'][$i];
                $amount = floatval($_POST['amount'][$i]);
                $due_date = $_POST['due_date'][$i];
                
                $stmtInst->execute([$customer_id, $payment_number, $amount, $due_date]);
            }
        }

        // 3. Ghi Activity Log
        $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description) VALUES (?, ?, ?)");
        $logStr = "Tạo mới Khách Hàng '{$name}' #" . $customer_id . " - Cần thu " . count($_POST['payment_number']) . " đợt.";
        $logStmt->execute([$_SESSION['user_id'], 'ADD_CUSTOMER', $logStr]);

        $pdo->commit();
        // Redirect về Dashboard
        header("Location: index.php?msg=success_added");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Lỗi lưu dữ liệu: " . $e->getMessage());
    }
} else {
    header("Location: add_customer.php");
    exit;
}
?>
