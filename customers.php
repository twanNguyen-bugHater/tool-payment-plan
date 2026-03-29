<?php
// Tên file: customers.php
require 'db.php';
require 'header.php';

// ======== BỘ LỌC ========
$filter_name = $_GET['filter_name'] ?? '';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';
$filter_debt = $_GET['filter_debt'] ?? '';
$filter_payment_type = $_GET['filter_payment_type'] ?? '';
$filter_sale = $_GET['filter_sale'] ?? '';
$sort_by = $_GET['sort_by'] ?? 'created_desc'; // Sắp xếp mặc định

// Phân quyền
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

// Apply filters
if (!empty($filter_name)) {
    $whereClause .= " AND c.name LIKE ?";
    $params[] = "%$filter_name%";
}
if (!empty($filter_date_from)) {
    $whereClause .= " AND c.created_at >= ?";
    $params[] = $filter_date_from;
}
if (!empty($filter_date_to)) {
    $whereClause .= " AND c.created_at <= ?";
    $params[] = $filter_date_to . ' 23:59:59';
}
if (!empty($filter_debt)) {
    $whereClause .= " AND c.debt_status = ?";
    $params[] = $filter_debt;
}
if (!empty($filter_payment_type)) {
    $whereClause .= " AND c.payment_type = ?";
    $params[] = $filter_payment_type;
}
if (!empty($filter_sale)) {
    $whereClause .= " AND c.sale_id = ?";
    $params[] = $filter_sale;
}

// Lấy danh sách Sale cho bộ lọc (Admin/Leader)
$saleList = [];
if ($_SESSION['role'] === 'admin') {
    $saleList = $pdo->query("SELECT id, username FROM users WHERE role IN ('sale','leader') ORDER BY username")->fetchAll(PDO::FETCH_ASSOC);
} elseif ($_SESSION['role'] === 'leader') {
    $stSale = $pdo->prepare("SELECT id, username FROM users WHERE leader_id = ? OR id = ? ORDER BY username");
    $stSale->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
    $saleList = $stSale->fetchAll(PDO::FETCH_ASSOC);
}

// Xây dựng câu lệnh ORDER BY
$orderBy = "ORDER BY c.created_at DESC";
if ($sort_by === 'remaining_desc') $orderBy = "ORDER BY c.remaining DESC";
elseif ($sort_by === 'remaining_asc') $orderBy = "ORDER BY c.remaining ASC";
elseif ($sort_by === 'total_desc') $orderBy = "ORDER BY c.total_bill DESC";
elseif ($sort_by === 'name_asc') $orderBy = "ORDER BY c.name ASC";

$sql = "SELECT c.*, u.username as sale_name,
               (SELECT COUNT(*) FROM installments WHERE customer_id = c.id) as total_installments,
               (SELECT COUNT(*) FROM installments WHERE customer_id = c.id AND status = 'paid') as paid_installments
        FROM customers c
        LEFT JOIN users u ON c.sale_id = u.id
        $whereClause
        $orderBy";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

function getDebtBadgeC($debt_status) {
    if ($debt_status === 'completed') return '<span class="badge bg-success">Đã hoàn thành</span>';
    if ($debt_status === 'bad_debt') return '<span class="badge bg-danger">Nợ xấu</span>';
    return '<span class="badge bg-warning text-dark">Chưa hoàn thành</span>';
}
function getPaymentTypeBadgeC($type) {
    if ($type === 'trip3') return '<span class="badge" style="background-color: #6f42c1; color: white;">Trip 3</span>';
    if ($type === 'trip2') return '<span class="badge bg-info text-dark">Trip 2</span>';
    return '<span class="badge bg-primary">Theo tháng</span>';
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="bi bi-people text-primary"></i> DANH SÁCH KHÁCH HÀNG</h2>
    <a href="add_customer.php" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Thêm Khách Hàng</a>
</div>

<?php if (isset($_GET['msg'])): ?>
    <?php if ($_GET['msg'] === 'pending_delete'): ?>
        <div class="alert alert-warning alert-dismissible fade show fw-bold text-dark border-warning shadow-sm">
            <i class="bi bi-exclamation-triangle-fill text-warning"></i> Yêu cầu xoá đã được gửi. Khách hàng này đang chờ Leader/Admin phê duyệt.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif ($_GET['msg'] === 'deleted'): ?>
        <div class="alert alert-success alert-dismissible fade show fw-bold shadow-sm">
            <i class="bi bi-check-circle-fill"></i> Sạch sẽ! Khách hàng và các đợt thu tiền đã được xoá vĩnh viễn khỏi hệ thống!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif ($_GET['msg'] === 'nopermission'): ?>
        <div class="alert alert-danger alert-dismissible fade show fw-bold shadow-sm">
            <i class="bi bi-slash-circle-fill"></i> Bạn không có quyền thao tác trên khách hàng này!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
<?php endif; ?>

<!-- BỘ LỌC -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">Tên bệnh nhân</label>
                <input type="text" name="filter_name" class="form-control form-control-sm" value="<?= htmlspecialchars($filter_name) ?>" placeholder="Tìm tên...">
            </div>
            <!-- BỘ LỌC SALE -->
            <?php if ($_SESSION['role'] !== 'sale' && count($saleList) > 0): ?>
            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">Sale quản lý</label>
                <select name="filter_sale" class="form-select form-select-sm">
                    <option value="">Tất cả Sale</option>
                    <?php foreach ($saleList as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $filter_sale==$s['id']?'selected':'' ?>><?= htmlspecialchars($s['username']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">Từ ngày tạo</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($filter_date_from) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">Đến ngày tạo</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($filter_date_to) ?>">
            </div>
            <div class="col-md-1">
                <label class="form-label small fw-semibold mb-1">Trạng thái nợ</label>
                <select name="filter_debt" class="form-select form-select-sm">
                    <option value="">Tất cả</option>
                    <option value="in_progress" <?= $filter_debt=='in_progress'?'selected':'' ?>>Chưa hoàn thành</option>
                    <option value="completed" <?= $filter_debt=='completed'?'selected':'' ?>>Đã hoàn thành</option>
                    <option value="bad_debt" <?= $filter_debt=='bad_debt'?'selected':'' ?>>Nợ xấu</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">Hình thức</label>
                <select name="filter_payment_type" class="form-select form-select-sm">
                    <option value="">Tất cả</option>
                    <option value="monthly" <?= $filter_payment_type=='monthly'?'selected':'' ?>>Theo tháng</option>
                    <option value="trip2" <?= $filter_payment_type=='trip2'?'selected':'' ?>>Trip 2</option>
                    <option value="trip3" <?= $filter_payment_type=='trip3'?'selected':'' ?>>Trip 3</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1"><i class="bi bi-sort-down"></i> Sắp xếp</label>
                <select name="sort_by" class="form-select form-select-sm border-primary">
                    <option value="created_desc" <?= $sort_by=='created_desc'?'selected':'' ?>>Mới lên hồ sơ</option>
                    <option value="remaining_desc" <?= $sort_by=='remaining_desc'?'selected':'' ?>>Còn nợ (Cao nhất)</option>
                    <option value="remaining_asc" <?= $sort_by=='remaining_asc'?'selected':'' ?>>Còn nợ (Thấp nhất)</option>
                    <option value="total_desc" <?= $sort_by=='total_desc'?'selected':'' ?>>Tổng Bill (Cao nhất)</option>
                    <option value="name_asc" <?= $sort_by=='name_asc'?'selected':'' ?>>Tên Khách (A-Z)</option>
                </select>
            </div>
            <div class="col-md-12 d-flex justify-content-end gap-2 mt-2">
                <button class="btn btn-sm btn-primary px-4"><i class="bi bi-funnel"></i> Lọc</button>
                <a href="customers.php" class="btn btn-sm btn-outline-secondary px-4"><i class="bi bi-x-circle"></i> Xoá lọc</a>
            </div>
        </form>
    </div>
</div>

<!-- BẢNG KHÁCH HÀNG -->
<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 0.88rem;">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Mã</th>
                        <th>Tên Khách Hàng</th>
                        <th>Email</th>
                        <th>Sale Quản Lý</th>
                        <th class="text-center">Đợt</th>
                        <th>Tổng Tiền</th>
                        <th>Còn Nợ</th>
                        <th>Hoàn Thành Liệu Trình</th>
                        <th class="text-center">Hình Thức</th>
                        <th class="text-center">Trạng Thái Nợ</th>
                        <th class="text-end pe-3">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($customers) == 0): ?>
                        <tr><td colspan="10" class="text-center py-4 text-muted">Chưa có khách hàng nào khớp với bộ lọc.</td></tr>
                    <?php endif; ?>

                    <?php foreach ($customers as $row): ?>
                    <tr style="cursor:pointer;" onclick="window.location='customer_detail.php?id=<?= $row['id'] ?>'">
                        <td class="ps-3 text-muted">#<?= $row['id'] ?></td>
                        <td class="fw-bold"><?= htmlspecialchars($row['name']) ?></td>
                        <td class="text-muted small"><?= htmlspecialchars($row['email'] ?? 'Không có') ?></td>
                        <td class="text-muted small"><?= htmlspecialchars($row['sale_name']) ?></td>
                        <td class="text-center">
                            <span class="badge bg-info text-dark"><?= $row['paid_installments'] ?>/<?= $row['total_installments'] ?></span>
                        </td>
                        <td class="fw-bold"><?= number_format($row['total_bill'], 0, ',', '.') ?> <?= $row['currency'] ?></td>
                        <td class="fw-bold text-danger"><?= number_format($row['remaining'], 0, ',', '.') ?> <?= $row['currency'] ?></td>
                        <td class="small"><?= $row['completion_date'] ? date('d/m/Y', strtotime($row['completion_date'])) : '<span class="text-muted">-</span>' ?></td>
                        <td class="text-center"><?= getPaymentTypeBadgeC($row['payment_type']) ?></td>
                        <td class="text-center"><?= getDebtBadgeC($row['debt_status']) ?></td>
                        <td class="text-end pe-3">
                            <?php if ($row['pending_delete'] == 1): ?>
                                <span class="badge bg-warning text-dark me-2 border border-warning" title="Sale yêu cầu xoá"><i class="bi bi-hourglass-bottom"></i> Xoá</span>
                            <?php else: ?>
                                <a href="delete_customer.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-danger border-0" onclick="event.stopPropagation(); return confirm('CẢNH BÁO: Phân quyền của bạn cho phép <?php echo ($_SESSION['role'] == 'sale') ? 'yêu cầu' : 'thực hiện'; ?> xoá khách hàng này.\n\nBạn có chắc chắn muốn xoá khách hàng [<?= htmlspecialchars($row['name']) ?>]?');" title="Xoá khách hàng">
                                    <i class="bi bi-trash"></i>
                                </a>
                            <?php endif; ?>
                            <i class="bi bi-chevron-right text-muted ms-2"></i>
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
