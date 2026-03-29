<?php
// Tên file: add_customer_action.php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    die("Unauthorized");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $pdo->beginTransaction();

        $name = $_POST['name'] ?? '';
        $gender = $_POST['gender'] ?? '';
        $sale_id = isset($_POST['sale_id']) ? $_POST['sale_id'] : $_SESSION['user_id'];
        
        $total_bill = floatval($_POST['total_bill']);
        $initial_debt = floatval($_POST['initial_debt']);
        $remaining = $initial_debt; // Ban đầu nợ còn lại = tiền đăng ký trả góp
        $currency = $_POST['currency'] ?? 'AUD';
        $email = $_POST['email'] ?? null;
        
        // Handle File Upload
        $treatment_file = '';
        if (isset($_FILES['treatment_file']) && $_FILES['treatment_file']['error'] == 0) {
            $ext = pathinfo($_FILES['treatment_file']['name'], PATHINFO_EXTENSION);
            $newFileName = uniqid('plan_') . '.' . $ext;
            if (move_uploaded_file($_FILES['treatment_file']['tmp_name'], 'uploads/' . $newFileName)) {
                $treatment_file = 'uploads/' . $newFileName;
            }
        }
        
        $completion_date = !empty($_POST['completion_date']) ? $_POST['completion_date'] : null;
        $payment_type = $_POST['payment_type'] ?? 'monthly';
        $debt_status = $_POST['debt_status'] ?? 'in_progress';

        // 1. Insert customers
        $stmtCust = $pdo->prepare("INSERT INTO customers (name, email, gender, sale_id, total_bill, initial_debt, currency, treatment_file, remaining, completion_date, payment_type, debt_status) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmtCust->execute([$name, $email, $gender, $sale_id, $total_bill, $initial_debt, $currency, $treatment_file, $remaining, $completion_date, $payment_type, $debt_status]);
        $customer_id = $pdo->lastInsertId();

        // 2. Insert installments
        if (isset($_POST['payment_number']) && is_array($_POST['payment_number'])) {
            $stmtInst = $pdo->prepare("INSERT INTO installments (customer_id, payment_number, amount, due_date, status) VALUES (?, ?, ?, ?, 'pending')");
            
            for ($i = 0; $i < count($_POST['payment_number']); $i++) {
                $payment_number = $_POST['payment_number'][$i];
                $amount = floatval($_POST['amount'][$i]);
                $due_date = $_POST['due_date'][$i];
                
                $stmtInst->execute([$customer_id, $payment_number, $amount, $due_date]);
            }
        }

        // 3. Activity Log
        $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description) VALUES (?, ?, ?)");
        $logStr = "Tạo mới Khách Hàng '{$name}' #" . $customer_id . " - Cần thu " . count($_POST['payment_number']) . " đợt. Hình thức: $payment_type.";
        $logStmt->execute([$_SESSION['user_id'], 'ADD_CUSTOMER', $logStr]);

        $pdo->commit();
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
