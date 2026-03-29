<?php
// Tên file: edit_customer_action.php
session_start();
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['customer_id'])) {
    die("Invalid request");
}

$customer_id = intval($_POST['customer_id']);

try {
    $pdo->beginTransaction();

    // 1. Kiểm tra khách hàng có tồn tại và Check Quyền Sửa (Lặp lại bảo mật)
    $stmtC = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
    $stmtC->execute([$customer_id]);
    $customer = $stmtC->fetch(PDO::FETCH_ASSOC);

    if (!$customer) die("Khách hàng không tồn tại.");

    $canEdit = false;
    if ($_SESSION['role'] === 'admin') {
        $canEdit = true;
    } elseif ($_SESSION['role'] === 'leader') {
        if ($customer['sale_id'] == $_SESSION['user_id']) {
            $canEdit = true;
        } else {
            $chk = $pdo->prepare("SELECT leader_id FROM users WHERE id = ?");
            $chk->execute([$customer['sale_id']]);
            if ($chk->fetchColumn() == $_SESSION['user_id']) $canEdit = true;
        }
    } elseif ($_SESSION['role'] === 'sale' && $customer['sale_id'] == $_SESSION['user_id']) {
        $canEdit = true;
    }

    if (!$canEdit) {
        throw new Exception("Bạn không có quyền sửa thông tin khách hàng này.");
    }

    // 2. Cập nhật thông tin chung TRỪ fields Tự động tính (Remaining, debt_status)
    $name = trim($_POST['name']);
    $gender = $_POST['gender'];
    $email = $_POST['email'] ?? null;
    $currency = $_POST['currency'];
    $payment_type = $_POST['payment_type'];
    $total_bill = floatval($_POST['total_bill']);
    $initial_debt = floatval($_POST['initial_debt']);

    // Handle File Upload
    $treatment_file = $customer['treatment_file']; // Mặc định giữ lại file cũ
    if (isset($_FILES['treatment_file']) && $_FILES['treatment_file']['error'] == 0) {
        $ext = pathinfo($_FILES['treatment_file']['name'], PATHINFO_EXTENSION);
        $newFileName = uniqid('plan_') . '.' . $ext;
        if (move_uploaded_file($_FILES['treatment_file']['tmp_name'], 'uploads/' . $newFileName)) {
            $treatment_file = 'uploads/' . $newFileName;
            // Xóa file cũ nếu có
            if (!empty($customer['treatment_file']) && file_exists($customer['treatment_file'])) {
                @unlink($customer['treatment_file']);
            }
        }
    }

    $updC = $pdo->prepare("UPDATE customers SET 
                            name=?, email=?, gender=?, treatment_file=?, 
                            completion_date=?, currency=?, payment_type=?, total_bill=?, initial_debt=? 
                           WHERE id=?");
    $updC->execute([$name, $email, $gender, $treatment_file, $completion_date, $currency, $payment_type, $total_bill, $initial_debt, $customer_id]);


    // 3. Cập nhật Đợt trả góp (Installments)
    $inst_ids = $_POST['inst_id'] ?? [];
    $inst_numbers = $_POST['inst_number'] ?? [];
    $inst_dues = $_POST['inst_due'] ?? [];
    $inst_amounts = $_POST['inst_amount'] ?? [];
    $inst_statuses = $_POST['inst_status'] ?? [];
    $inst_payment_dates = $_POST['inst_payment_date'] ?? [];

    $submitted_ids = [];
    $total_paid_money = 0;
    $all_paid = true;
    $total_installments = count($inst_ids);

    // Xử lý từng đợt được Gửi lên
    $valid_counter = 1; // Sắp xếp lại số đợt payment_number (1,2,3...)

    for ($i = 0; $i < $total_installments; $i++) {
        $id_str = $inst_ids[$i];
        $due = !empty($inst_dues[$i]) ? $inst_dues[$i] : date('Y-m-d');
        $amt = floatval($inst_amounts[$i]);
        $st = $inst_statuses[$i];
        $pdate = !empty($inst_payment_dates[$i]) ? $inst_payment_dates[$i] : null;
        
        // Nếu chọn paid nhưng để trống ngày thu, sẽ tự lấy hnay (ngoại trừ NEW form)
        if ($st === 'paid') {
            $total_paid_money += $amt;
            if (!$pdate) $pdate = date('Y-m-d'); 
        } else {
            $all_paid = false;
        }

        if ($id_str === 'NEW') {
            // Thêm mới đợt
            $ins = $pdo->prepare("INSERT INTO installments (customer_id, payment_number, due_date, amount, status, payment_date) VALUES (?, ?, ?, ?, ?, ?)");
            $ins->execute([$customer_id, $valid_counter, $due, $amt, $st, $pdate]);
            $submitted_ids[] = $pdo->lastInsertId();
        } else {
            // Cập nhật hiện tại
            $id = intval($id_str);
            $updInst = $pdo->prepare("UPDATE installments SET payment_number=?, due_date=?, amount=?, status=?, payment_date=? WHERE id=?");
            $updInst->execute([$valid_counter, $due, $amt, $st, $pdate, $id]);
            $submitted_ids[] = $id;
        }

        $valid_counter++; // Tăng đợt số để nó luôn là 1 2 3 không bị trùng
    }

    // 4. Xóa các Đợt (Installments) CÓ TRONG DB nhưng KHÔNG CÓ TRÊN FORM 
    // (tức là người dùng đã bấm nút Xoá dòng đó trên màn hình)
    if (count($submitted_ids) > 0) {
        $inQuery = implode(',', array_fill(0, count($submitted_ids), '?'));
        $delParams = array_merge([$customer_id], $submitted_ids);
        $del = $pdo->prepare("DELETE FROM installments WHERE customer_id = ? AND id NOT IN ($inQuery)");
        $del->execute($delParams);
    } else {
        // Nếu xoá toàn bộ form (mặc dù ko nên)
        $del = $pdo->prepare("DELETE FROM installments WHERE customer_id = ?");
        $del->execute([$customer_id]);
    }

    // 5. Tự động tính Remaining và Debt Status
    $new_remaining = $initial_debt - $total_paid_money;
    if ($new_remaining <= 0) {
        $new_remaining = 0;
        $new_debt_status = 'completed'; // Chỉ hoàn thành khi thực sự đóng đủ tiền mốc đăng ký
    } else {
        $new_debt_status = 'in_progress';
    }
    
    $updRemaining = $pdo->prepare("UPDATE customers SET remaining = ?, debt_status = ? WHERE id = ?");
    $updRemaining->execute([$new_remaining, $new_debt_status, $customer_id]);


    // 6. Ghi Log Cập nhật
    $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description) VALUES (?, ?, ?)");
    $desc = "Sale/Admin ({$_SESSION['username']}) đã sửa đổi thông tin khách hàng: '{$name}'. (Sửa cả đợt trả góp)";
    $logStmt->execute([$_SESSION['user_id'], 'UPDATE_CUSTOMER', $desc]);

    $pdo->commit();
    header("Location: customer_detail.php?id=" . $customer_id);
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Lỗi lưu trữ: " . $e->getMessage());
}
?>
