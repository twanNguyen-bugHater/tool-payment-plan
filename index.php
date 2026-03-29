<?php
// Tên file: index.php
require 'db.php';
require 'header.php';

// ============ BỘ LỌC (FILTERS) ============
$filter_name = $_GET['filter_name'] ?? '';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-t'); // Mặc định đến cuối tháng hiện tại
$filter_status = isset($_GET['filter_status']) ? $_GET['filter_status'] : 'pending'; // Mặc định hiển thị nợ chờ thu
$filter_debt = $_GET['filter_debt'] ?? '';
$filter_payment_type = $_GET['filter_payment_type'] ?? '';
$filter_sale = $_GET['filter_sale'] ?? '';
$sort_by = $_GET['sort_by'] ?? 'due_asc'; // Mặc định sắp xếp ngày đến hạn gần nhất

// Phân quyền: Sale chỉ thấy khách của mình
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
    $whereClause .= " AND i.due_date >= ?";
    $params[] = $filter_date_from;
}
if (!empty($filter_date_to)) {
    $whereClause .= " AND i.due_date <= ?";
    $params[] = $filter_date_to;
}
if (!empty($filter_status)) {
    $whereClause .= " AND i.status = ?";
    $params[] = $filter_status;
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
$orderBy = "ORDER BY i.due_date ASC";
if ($sort_by === 'due_desc') $orderBy = "ORDER BY i.due_date DESC";
elseif ($sort_by === 'amount_desc') $orderBy = "ORDER BY i.amount DESC";
elseif ($sort_by === 'amount_asc') $orderBy = "ORDER BY i.amount ASC";
elseif ($sort_by === 'remaining_desc') $orderBy = "ORDER BY c.remaining DESC";

$sql = "SELECT i.*, c.name as customer_name, c.email, c.currency, c.remaining, c.total_bill,
               c.completion_date, c.payment_type, c.debt_status, c.id as cust_id,
               u.username as sale_name,
               (SELECT COUNT(*) FROM installments WHERE customer_id = c.id) as total_installments,
               (SELECT COUNT(*) FROM installments WHERE customer_id = c.id AND status = 'paid') as paid_installments
        FROM installments i
        JOIN customers c ON i.customer_id = c.id
        LEFT JOIN users u ON c.sale_id = u.id
        $whereClause
        $orderBy";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$installments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ===== THỐNG KÊ CHO BIỂU ĐỒ (Admin/Leader) =====
$chartData = [];
if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'leader' || $_SESSION['role'] === 'sale') {
    $roleFilter = "";
    $roleParams = [];
    if ($_SESSION['role'] === 'leader') {
        $roleFilter = "WHERE (c.sale_id = ? OR u.leader_id = ?)";
        $roleParams = [$_SESSION['user_id'], $_SESSION['user_id']];
    } elseif ($_SESSION['role'] === 'sale') {
        $roleFilter = "WHERE c.sale_id = ?";
        $roleParams = [$_SESSION['user_id']];
    }

    // Áp dụng bộ lọc Sale (nếu có chọn)
    if (!empty($filter_sale) && $_SESSION['role'] !== 'sale') {
        if ($roleFilter === "") {
            $roleFilter = "WHERE c.sale_id = ?";
        } else {
            $roleFilter .= " AND c.sale_id = ?";
        }
        $roleParams[] = $filter_sale;
    }

    // Tổng nợ toàn bộ & Tổng hóa đơn (Theo từng Currency)
    $q1 = $pdo->prepare("SELECT c.currency, COALESCE(SUM(c.remaining),0) as total_remaining, COALESCE(SUM(c.total_bill),0) as total_bill FROM customers c LEFT JOIN users u ON c.sale_id = u.id $roleFilter GROUP BY c.currency");
    $q1->execute($roleParams);
    $chartData['summary'] = $q1->fetchAll(PDO::FETCH_ASSOC);

    // Đếm theo debt_status
    $q2 = $pdo->prepare("SELECT c.debt_status, COUNT(*) as cnt FROM customers c LEFT JOIN users u ON c.sale_id = u.id $roleFilter GROUP BY c.debt_status");
    $q2->execute($roleParams);
    $chartData['debt_status'] = $q2->fetchAll(PDO::FETCH_ASSOC);

    // Đếm theo payment_type
    $q3 = $pdo->prepare("SELECT c.payment_type, COUNT(*) as cnt FROM customers c LEFT JOIN users u ON c.sale_id = u.id $roleFilter GROUP BY c.payment_type");
    $q3->execute($roleParams);
    $chartData['payment_type'] = $q3->fetchAll(PDO::FETCH_ASSOC);

    // Đã thu tháng này (Theo từng Currency)
    $monthStart = date('Y-m-01');
    $monthEnd = date('Y-m-t');
    $q4 = $pdo->prepare("SELECT c.currency, COALESCE(SUM(i.amount),0) as collected FROM installments i 
                          JOIN customers c ON i.customer_id = c.id
                          LEFT JOIN users u ON c.sale_id = u.id
                          " . ($roleFilter ? $roleFilter . " AND" : "WHERE") . " 
                          i.status = 'paid' AND i.payment_date BETWEEN ? AND ? GROUP BY c.currency");
    $q4Params = array_merge($roleParams, [$monthStart, $monthEnd]);
    $q4->execute($q4Params);
    $chartData['collected_this_month'] = $q4->fetchAll(PDO::FETCH_ASSOC);

    // Số bệnh nhân nợ
    $q5 = $pdo->prepare("SELECT COUNT(DISTINCT c.id) as cnt FROM customers c 
                          LEFT JOIN users u ON c.sale_id = u.id
                          " . ($roleFilter ?: '') . "
                          " . ($roleFilter ? "AND" : "WHERE") . " c.debt_status != 'completed'");
    $q5->execute($roleParams);
    $chartData['debtors_count'] = $q5->fetch(PDO::FETCH_ASSOC)['cnt'];

    // Thu theo từng tháng (6 tháng gần nhất) - Có phân loại theo currency
    $monthly_data = [];
    $all_currencies = [];
    for ($m = 5; $m >= 0; $m--) {
        $ms = date('Y-m-01', strtotime("-$m months"));
        $me = date('Y-m-t', strtotime("-$m months"));
        $label = date('m/Y', strtotime("-$m months"));
        $qm = $pdo->prepare("SELECT c.currency, COALESCE(SUM(i.amount),0) as total FROM installments i 
                              JOIN customers c ON i.customer_id = c.id
                              LEFT JOIN users u ON c.sale_id = u.id
                              " . ($roleFilter ? $roleFilter . " AND" : "WHERE") . " 
                              i.status = 'paid' AND i.payment_date BETWEEN ? AND ? GROUP BY c.currency");
        $qmParams = array_merge($roleParams, [$ms, $me]);
        $qm->execute($qmParams);
        $month_totals = $qm->fetchAll(PDO::FETCH_ASSOC);
        
        $entry = ['label' => $label, 'amounts' => []];
        foreach($month_totals as $mt) {
            $curr = $mt['currency'] ?: 'VND'; 
            $entry['amounts'][$curr] = floatval($mt['total']);
            if(!in_array($curr, $all_currencies)) $all_currencies[] = $curr;
        }
        $monthly_data[] = $entry;
    }
    $chartData['monthly'] = $monthly_data;
    $chartData['monthly_currencies'] = $all_currencies;
}

// Helper functions - VIẾT ĐẦY ĐỦ KHÔNG VIẾT TẮT
function getStatusBadge($status, $dueDate) {
    if ($status === 'paid') return '<span class="badge bg-success">Đã thu (PAID)</span>';
    if ($status === 'pending' && strtotime($dueDate) < strtotime(date('Y-m-d'))) {
        return '<span class="badge bg-danger">Quá hạn</span>';
    }
    if ($status === 'late') return '<span class="badge bg-warning text-dark">Trễ hẹn</span>';
    if ($status === 'cancelled') return '<span class="badge bg-dark border border-secondary">Nợ xấu</span>';
    return '<span class="badge bg-light text-dark border">Chờ thu</span>';
}

function getDebtBadge($debt_status) {
    if ($debt_status === 'completed') return '<span class="badge bg-success">Đã hoàn thành</span>';
    if ($debt_status === 'bad_debt') return '<span class="badge bg-danger">Nợ xấu</span>';
    return '<span class="badge bg-warning text-dark">Chưa hoàn thành</span>';
}

function getPaymentTypeBadge($type) {
    if ($type === 'trip3') return '<span class="badge" style="background-color: #6f42c1; color: white;">Trip 3</span>';
    if ($type === 'trip2') return '<span class="badge bg-info text-dark">Trip 2</span>';
    return '<span class="badge bg-primary">Theo tháng</span>';
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="bi bi-speedometer2 text-primary"></i> TỔNG QUAN ĐỢT TRẢ GÓP</h2>
    <?php if ($_SESSION['role'] !== 'admin'): ?>
        <span class="badge bg-secondary p-2">Dữ liệu chỉ hiển thị khách của bạn</span>
    <?php endif; ?>
    <a href="add_customer.php" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Thêm Khách Hàng</a>
</div>

<!-- ========== BỘ LỌC ========== -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">Tên bệnh nhân</label>
                <input type="text" name="filter_name" class="form-control form-control-sm" value="<?= htmlspecialchars($filter_name) ?>" placeholder="Tìm tên...">
            </div>
            <!-- BỘ LỌC SALE (Admin/Leader) -->
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
            <div class="col-md-1">
                <label class="form-label small fw-semibold mb-1">Từ ngày</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($filter_date_from) ?>">
            </div>
            <div class="col-md-1">
                <label class="form-label small fw-semibold mb-1">Đến ngày</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($filter_date_to) ?>">
            </div>
            <div class="col-md-1">
                <label class="form-label small fw-semibold mb-1">Trạng thái đợt</label>
                <select name="filter_status" class="form-select form-select-sm">
                    <option value="">Tất cả</option>
                    <option value="pending" <?= $filter_status=='pending'?'selected':'' ?>>Chờ thu</option>
                    <option value="paid" <?= $filter_status=='paid'?'selected':'' ?>>Đã thu</option>
                    <option value="late" <?= $filter_status=='late'?'selected':'' ?>>Trễ hẹn</option>
                </select>
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
                    <option value="due_asc" <?= $sort_by=='due_asc'?'selected':'' ?>>Hạn đóng (Gần nhất)</option>
                    <option value="due_desc" <?= $sort_by=='due_desc'?'selected':'' ?>>Hạn đóng (Xa nhất)</option>
                    <option value="amount_desc" <?= $sort_by=='amount_desc'?'selected':'' ?>>Cần thu (Cao nhất)</option>
                    <option value="amount_asc" <?= $sort_by=='amount_asc'?'selected':'' ?>>Cần thu (Thấp nhất)</option>
                    <option value="remaining_desc" <?= $sort_by=='remaining_desc'?'selected':'' ?>>Còn nợ (Tổng cao nhất)</option>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-1">
                <button class="btn btn-sm btn-primary w-100"><i class="bi bi-funnel"></i> Lọc</button>
                <a href="index.php" class="btn btn-sm btn-outline-secondary w-100"><i class="bi bi-x-circle"></i> Xoá</a>
            </div>
        </form>
    </div>
</div>

<!-- ========== THỐNG KÊ NHANH ========== -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card bg-primary text-white shadow h-100">
            <div class="card-body py-3">
                <h6 class="text-uppercase fw-bold opacity-75 small mb-2">Tổng Hóa Đơn</h6>
                <?php if(empty($chartData['summary'])): ?><h4 class="mb-0">0</h4><?php endif; ?>
                <?php foreach($chartData['summary'] as $sum): ?>
                    <div class="d-flex justify-content-between align-items-center mb-1 pb-1 border-bottom border-light border-opacity-25">
                        <span class="small"><?= htmlspecialchars($sum['currency'] ?: 'VND') ?></span>
                        <h5 class="mb-0 fw-bold"><?= number_format($sum['total_bill'], 0) ?></h5>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card bg-danger text-white shadow h-100">
            <div class="card-body py-3">
                <h6 class="text-uppercase fw-bold opacity-75 small mb-2">Tổng Nợ Còn Lại</h6>
                <?php if(empty($chartData['summary'])): ?><h4 class="mb-0">0</h4><?php endif; ?>
                <?php foreach($chartData['summary'] as $sum): ?>
                    <div class="d-flex justify-content-between align-items-center mb-1 pb-1 border-bottom border-light border-opacity-25">
                        <span class="small"><?= htmlspecialchars($sum['currency'] ?: 'VND') ?></span>
                        <h5 class="mb-0 fw-bold"><?= number_format($sum['total_remaining'], 0) ?></h5>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card bg-success text-white shadow h-100">
            <div class="card-body py-3">
                <h6 class="text-uppercase fw-bold opacity-75 small mb-2">Đã Thu Tháng Này</h6>
                <?php if(empty($chartData['collected_this_month'])): ?><h4 class="mb-0">0</h4><?php endif; ?>
                <?php foreach($chartData['collected_this_month'] as $col): ?>
                    <div class="d-flex justify-content-between align-items-center mb-1 pb-1 border-bottom border-light border-opacity-25">
                        <span class="small"><?= htmlspecialchars($col['currency'] ?: 'VND') ?></span>
                        <h5 class="mb-0 fw-bold"><?= number_format($col['collected'], 0) ?></h5>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card bg-warning text-dark shadow h-100">
            <div class="card-body py-3">
                <h6 class="text-uppercase fw-bold opacity-75 small">Số Khách Hàng Nợ</h6>
                <h3 class="mb-0 fw-bold mt-2 pb-1"><?= $chartData['debtors_count'] ?> <small class="fs-6 fw-normal">người</small></h3>
            </div>
        </div>
    </div>
</div>

<!-- ========== BIỂU ĐỒ PHÂN TÍCH ========== -->
<div class="row mb-4">
    <div class="col-md-4 mb-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white fw-bold small">Phân bổ trạng thái nợ</div>
            <div class="card-body d-flex align-items-center justify-content-center">
                <canvas id="chartDebt" style="max-height:220px;"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white fw-bold small">Hình thức trả góp</div>
            <div class="card-body d-flex align-items-center justify-content-center">
                <canvas id="chartType" style="max-height:220px;"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white fw-bold small">Đã thu 6 tháng gần nhất</div>
            <div class="card-body d-flex align-items-center justify-content-center">
                <canvas id="chartMonthly" style="max-height:220px;"></canvas>
            </div>
        </div>
    </div>
</div>


<!-- ========== BẢNG DỮ LIỆU ========== -->
<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size:0.88rem;">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Khách Hàng</th>
                        <th>Email</th>
                        <th>Sale Quản Lý</th>
                        <th class="text-center">Đợt</th>
                        <th>Cần Thu</th>
                        <th>Còn Nợ</th>
                        <th>Hạn Đóng</th>
                        <th>Hoàn Thành Liệu Trình</th>
                        <th class="text-center">Hình Thức</th>
                        <th class="text-center">Trạng Thái Đợt</th>
                        <th class="text-center">Trạng Thái Nợ</th>
                        <th class="text-end pe-3">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($installments) == 0): ?>
                        <tr><td colspan="11" class="text-center py-4 text-muted">Không có đợt thu tiền nào khớp với bộ lọc.</td></tr>
                    <?php endif; ?>

                    <?php foreach ($installments as $row): ?>
                    <tr>
                        <td class="ps-3 fw-bold">
                            <a href="customer_detail.php?id=<?= $row['cust_id'] ?>" class="text-decoration-none">
                                <?= htmlspecialchars($row['customer_name']) ?>
                            </a>
                        </td>
                        <td class="text-muted small"><?= htmlspecialchars($row['email'] ?? 'Không có') ?></td>
                        <td class="text-muted small"><?= htmlspecialchars($row['sale_name']) ?></td>
                        <td class="text-center">
                            <span class="badge bg-info text-dark"><?= $row['paid_installments'] ?>/<?= $row['total_installments'] ?></span>
                        </td>
                        <td class="fw-bold"><?= number_format($row['amount'], 0, ',', '.') ?> <?= $row['currency'] ?></td>
                        <td class="text-danger fw-bold"><?= number_format($row['remaining'], 0, ',', '.') ?> <?= $row['currency'] ?></td>
                        <td><?= date('d/m/Y', strtotime($row['due_date'])) ?></td>
                        <td class="small"><?= $row['completion_date'] ? date('d/m/Y', strtotime($row['completion_date'])) : '<span class="text-muted">-</span>' ?></td>
                        <td class="text-center"><?= getPaymentTypeBadge($row['payment_type']) ?></td>
                        <td class="text-center"><?= getStatusBadge($row['status'], $row['due_date']) ?></td>
                        <td class="text-center"><?= getDebtBadge($row['debt_status']) ?></td>
                        <td class="text-end pe-3">
                            <?php if ($row['status'] == 'pending'): ?>
                                <a href="update_payment.php?id=<?= $row['id'] ?>&action=paid" class="btn btn-sm btn-outline-success" title="Đánh dấu Đã thu">
                                    <i class="bi bi-check-circle"></i>
                                </a>
                            <?php else: ?>
                                <span class="text-success"><i class="bi bi-check2-all"></i></span>
                            <?php endif; ?>
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

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// 1. Biểu đồ Doughnut - Trạng thái nợ
const debtLabels = [];
const debtValues = [];
const debtColors = [];
const debtColorMap = {'in_progress': '#ffc107', 'completed': '#198754', 'bad_debt': '#dc3545'};
const debtLabelMap = {'in_progress': 'Chưa hoàn thành', 'completed': 'Đã hoàn thành', 'bad_debt': 'Nợ xấu'};
<?php foreach ($chartData['debt_status'] as $ds): ?>
    debtLabels.push(debtLabelMap['<?= $ds['debt_status'] ?>'] || '<?= $ds['debt_status'] ?>');
    debtValues.push(<?= $ds['cnt'] ?>);
    debtColors.push(debtColorMap['<?= $ds['debt_status'] ?>'] || '#6c757d');
<?php endforeach; ?>
new Chart(document.getElementById('chartDebt'), {
    type: 'doughnut',
    data: { labels: debtLabels, datasets: [{ data: debtValues, backgroundColor: debtColors }] },
    options: { responsive: true, plugins: { legend: { position: 'bottom', labels: {font:{size:11}} } } }
});

// 2. Biểu đồ Pie - Hình thức trả góp
const typeLabels = [];
const typeValues = [];
const typeLabelMap = {'monthly': 'Theo tháng', 'trip2': 'Trip 2', 'trip3': 'Trip 3'};
<?php foreach ($chartData['payment_type'] as $pt): ?>
    typeLabels.push(typeLabelMap['<?= $pt['payment_type'] ?>'] || '<?= $pt['payment_type'] ?>');
    typeValues.push(<?= $pt['cnt'] ?>);
<?php endforeach; ?>
new Chart(document.getElementById('chartType'), {
    type: 'pie',
    data: { labels: typeLabels, datasets: [{ data: typeValues, backgroundColor: ['#0d6efd','#0dcaf0','#6f42c1','#6c757d'] }] },
    options: { responsive: true, plugins: { legend: { position: 'bottom', labels: {font:{size:11}} } } }
});

// 3. Biểu đồ Bar - Thu theo tháng
const monthlyLabels = [];
<?php foreach ($chartData['monthly'] as $m): ?>
    monthlyLabels.push('<?= $m['label'] ?>');
<?php endforeach; ?>

const datasets = [];
const currencyColors = { 'VND': 'rgba(25, 135, 84, 0.7)', 'USD': 'rgba(13, 110, 253, 0.7)', 'AUD': 'rgba(255, 193, 7, 0.7)', 'NZD': 'rgba(220, 53, 69, 0.7)' };
const currencyBorders = { 'VND': '#198754', 'USD': '#0d6efd', 'AUD': '#ffc107', 'NZD': '#dc3545' };

<?php foreach($chartData['monthly_currencies'] as $index => $curr): ?>
    var data_<?= strtolower($curr) ?> = [];
    <?php foreach ($chartData['monthly'] as $m): ?>
        data_<?= strtolower($curr) ?>.push(<?= isset($m['amounts'][$curr]) ? $m['amounts'][$curr] : 0 ?>);
    <?php endforeach; ?>
    
    datasets.push({
        label: 'Đã thu (<?= $curr ?>)',
        data: data_<?= strtolower($curr) ?>,
        backgroundColor: currencyColors['<?= $curr ?>'] || 'rgba(108, 117, 125, 0.7)',
        borderColor: currencyBorders['<?= $curr ?>'] || '#6c757d',
        borderWidth: 1
    });
<?php endforeach; ?>

new Chart(document.getElementById('chartMonthly'), {
    type: 'bar',
    data: {
        labels: monthlyLabels,
        datasets: datasets
    },
    options: {
        responsive: true,
        plugins: { 
            legend: { position: 'bottom', labels: {font:{size:11}} },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        let label = context.dataset.label || '';
                        if (label) { label += ': '; }
                        if (context.parsed.y !== null) { label += new Intl.NumberFormat('vi-VN').format(context.parsed.y); }
                        return label;
                    }
                }
            }
        },
        scales: { y: { beginAtZero: true } }
    }
});
</script>
</body>
</html>