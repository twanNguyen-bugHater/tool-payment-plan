<?php
/**
 * recalc_debt.php
 * Hàm tính lại debt_status cho một khách hàng dựa trên trạng thái các đợt trả.
 *
 * Quy tắc:
 *  - Nếu remaining <= 0  → 'completed'
 *  - Nếu có ít nhất 1 đợt (pending/late) quá hạn >= 90 ngày → 'bad_debt'
 *  - Còn lại → 'in_progress'
 *
 * Hàm này KHÔNG commit transaction, gọi nó bên trong transaction của caller.
 */

function recalcDebtStatus(PDO $pdo, int $customer_id): string
{
    // Lấy remaining hiện tại
    $stmtC = $pdo->prepare("SELECT remaining FROM customers WHERE id = ?");
    $stmtC->execute([$customer_id]);
    $remaining = (float)$stmtC->fetchColumn();

    if ($remaining <= 0) {
        $status = 'completed';
    } else {
        // Kiểm tra có đợt nào pending/late mà due_date quá hạn >= 90 ngày không
        $today = date('Y-m-d');
        $stmtI = $pdo->prepare("
            SELECT COUNT(*) FROM installments
            WHERE customer_id = ?
              AND status IN ('pending', 'late')
              AND due_date <= DATE_SUB(?, INTERVAL 90 DAY)
        ");
        $stmtI->execute([$customer_id, $today]);
        $overdue_count = (int)$stmtI->fetchColumn();

        $status = ($overdue_count > 0) ? 'bad_debt' : 'in_progress';
    }

    $upd = $pdo->prepare("UPDATE customers SET debt_status = ? WHERE id = ?");
    $upd->execute([$status, $customer_id]);

    return $status;
}
