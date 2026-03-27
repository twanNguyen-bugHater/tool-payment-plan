<?php
// Tên file: customers.php
require 'db.php';
require 'header.php';

// Filter role
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

$sql = "SELECT c.*, u.username as sale_name 
        FROM customers c
        LEFT JOIN users u ON c.sale_id = u.id
        $whereClause
        ORDER BY c.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-people text-primary"></i> DANH SÁCH KHÁCH HÀNG</h2>
    <a href="add_customer.php" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Thêm Khách Hàng</a>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Mã KH</th>
                        <th>Tên Khách Hàng</th>
                        <th>Giới tính</th>
                        <th>Sale Phụ Trách</th>
                        <th>File Điều Trị</th>
                        <th>Tổng Tiền</th>
                        <th>Còn Nợ</th>
                        <th class="text-end pe-4">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($customers) == 0): ?>
                        <tr><td colspan="8" class="text-center py-4 text-muted">Chưa có khách hàng nào.</td></tr>
                    <?php endif; ?>

                    <?php foreach ($customers as $row): ?>
                    <tr>
                        <td class="ps-4 text-muted">#<?= $row['id'] ?></td>
                        <td class="fw-bold"><?= htmlspecialchars($row['name']) ?></td>
                        <td><?= htmlspecialchars($row['gender']) ?></td>
                        <td><?= htmlspecialchars($row['sale_name']) ?></td>
                        <td>
                            <?php if(!empty($row['treatment_file'])): ?>
                                <a href="<?= htmlspecialchars($row['treatment_file']) ?>" target="_blank" class="btn btn-sm btn-outline-info"><i class="bi bi-link-45deg"></i> Link Drive</a>
                            <?php else: ?>
                                <span class="text-muted small">Chưa có link</span>
                            <?php endif; ?>
                        </td>
                        <td class="fw-bold text-dark"><?= number_format($row['total_bill'], 2) ?> <?= htmlspecialchars($row['currency']) ?></td>
                        <td class="fw-bold text-danger"><?= number_format($row['remaining'], 2) ?> <?= htmlspecialchars($row['currency']) ?></td>
                        <td class="text-end pe-4">
                            <button class="btn btn-sm btn-outline-secondary" disabled>Chi tiết Đợt (Sắp ra mắt)</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</div> 
</div>
</div> 
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
