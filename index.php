<?php
// Tên file: index.php
require 'db.php';
require 'header.php'; // Chứa luôn session_start() và Sidebar

// Lọc dữ liệu dựa trên Role:
// Sale: Chỉ thấy list của mình.
// Leader: Thấy list của mình + list của các Sale thuộc team mình.
// Admin: Thấy tất cả.
$whereClause = "WHERE 1=1";
$params = [];

if ($_SESSION['role'] == 'sale') {
    $whereClause .= " AND c.sale_id = ?";
    $params[] = $_SESSION['user_id'];
} elseif ($_SESSION['role'] == 'leader') {
    $whereClause .= " AND (c.sale_id = ? OR u.leader_id = ?)";
    $params[] = $_SESSION['user_id'];
    $params[] = $_SESSION['user_id'];
}

// Câu lệnh SQL lấy tất cả đợt trả góp, kết nối với thông tin khách và sale
$sql = "SELECT i.*, c.name as customer_name, c.currency, u.username as sale_name 
        FROM installments i
        JOIN customers c ON i.customer_id = c.id
        LEFT JOIN users u ON c.sale_id = u.id
        $whereClause
        ORDER BY i.due_date ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$installments = $stmt->fetchAll(PDO::FETCH_ASSOC);

function getStatusBadge($status, $dueDate) {
    if ($status === 'paid') return '<span class="badge bg-success">Đã thu (PAID)</span>';
    // Đã qua hạn mà chưa thu đổi thành ĐỎ
    if ($status === 'pending' && strtotime($dueDate) < strtotime(date('Y-m-d'))) {
        return '<span class="badge bg-danger">Quá hạn (LATE)</span>';
    }
    if ($status === 'late') return '<span class="badge bg-warning text-dark">Trễ hẹn (LATE)</span>';
    if ($status === 'cancelled') return '<span class="badge bg-secondary">Đã huỷ</span>';
    return '<span class="badge bg-light text-dark">Chờ thu (PENDING)</span>';
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-speedometer2 text-primary"></i> TỔNG QUAN ĐỢT TRẢ GÓP</h2>
    <?php if($_SESSION['role'] !== 'leader'): // Tuỳ bạn, sale có quyền thêm khách ?>
        <a href="add_customer.php" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Thêm Khách Hàng</a>
    <?php endif; ?>
</div>

<!-- Dashboard Stats -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white shadow-sm h-100">
            <div class="card-body py-4">
                <h6 class="card-title text-uppercase fw-bold opacity-75">Tài khoản của bạn</h6>
                <h3 class="mb-0"><?= ucfirst($_SESSION['role']) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white shadow-sm h-100">
            <div class="card-body py-4">
                <h6 class="card-title text-uppercase fw-bold opacity-75">Tổng đợt Cần Thu</h6>
                <h3 class="mb-0"><?= count($installments) ?> đợt</h3>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Tên Khách Hàng</th>
                        <th>Sale Quản Lý</th>
                        <th>Đợt</th>
                        <th>Cần Thu</th>
                        <th>Hạn Đóng</th>
                        <th>Trạng Thái</th>
                        <th class="text-end pe-4">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($installments) == 0): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">Không có đợt thu tiền nào.</td></tr>
                    <?php endif; ?>

                    <?php foreach ($installments as $row): ?>
                    <tr>
                        <td class="ps-4 fw-bold">
                            #<?= $row['customer_id'] ?> - <?= htmlspecialchars($row['customer_name']) ?>
                        </td>
                        <td>
                            <i class="bi bi-person text-secondary"></i> <?= htmlspecialchars($row['sale_name']) ?>
                        </td>
                        <td>
                            <span class="badge bg-info text-dark">Đợt <?= $row['payment_number'] ?></span>
                        </td>
                        <td class="fw-bold text-success">
                            <?= number_format($row['amount'], 2) ?> <?= htmlspecialchars($row['currency']) ?>
                        </td>
                        <td>
                            <!-- Đổi định dạng ngày -->
                            <?= date('d/m/Y', strtotime($row['due_date'])) ?>
                        </td>
                        <td>
                            <?= getStatusBadge($row['status'], $row['due_date']) ?>
                        </td>
                        <td class="text-end pe-4">
                            <?php if ($row['status'] == 'pending'): ?>
                                <!-- Nút gọi popup hoặc redirect đến trang update -->
                                <a href="update_payment.php?id=<?= $row['id'] ?>&action=paid" class="btn btn-sm btn-outline-success">
                                    <i class="bi bi-check-circle"></i> Mark as Paid
                                </a>
                            <?php else: ?>
                                <span class="text-muted"><i class="bi bi-check2-all"></i> Hoàn tất</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</div> <!-- Đóng thẻ col-md-10 của header.php -->
</div> <!-- Đóng thẻ row của header.php -->
</div> <!-- Đóng thẻ container của header.php -->

<!-- Nhúng JS Bootstrap -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>