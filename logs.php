<?php
// Tên file: logs.php
require 'db.php';
require 'header.php';

if ($_SESSION['role'] !== 'admin') {
    die("Từ chối truy cập! Chỉ Admin mới có quyền vào đây.");
}

// ======== BỘ LỌC ========
$filter_user = $_GET['filter_user'] ?? '';
$filter_action = $_GET['filter_action'] ?? '';

$whereClause = "WHERE 1=1";
$params = [];

if (!empty($filter_user)) {
    $whereClause .= " AND u.username LIKE ?";
    $params[] = "%$filter_user%";
}
if (!empty($filter_action)) {
    $whereClause .= " AND l.action = ?";
    $params[] = $filter_action;
}

// Lấy danh sách log
$sql = "SELECT l.*, u.username 
        FROM activity_logs l 
        JOIN users u ON l.user_id = u.id 
        $whereClause
        ORDER BY l.created_at DESC 
        LIMIT 500";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Lấy danh sách action unique
$actions = $pdo->query("SELECT DISTINCT action FROM activity_logs ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);

function getActionColor($action) {
    if (strpos($action, 'ADD') !== false) return 'text-success fw-bold';
    if (strpos($action, 'UPDATE') !== false) return 'text-primary fw-bold';
    if (strpos($action, 'DELETE') !== false || strpos($action, 'RESET') !== false) return 'text-danger fw-bold';
    if (strpos($action, 'CHANGE') !== false) return 'text-warning fw-bold';
    return 'text-secondary';
}
?>

<h3 class="mb-4"><i class="bi bi-journals text-primary"></i> LỊCH SỬ HOẠT ĐỘNG HỆ THỐNG</h3>

<!-- BỘ LỌC -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold mb-1">Tên user</label>
                <input type="text" name="filter_user" class="form-control form-control-sm" value="<?= htmlspecialchars($filter_user) ?>" placeholder="Tìm theo tên user...">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold mb-1">Hành động</label>
                <select name="filter_action" class="form-select form-select-sm">
                    <option value="">Tất cả hành động</option>
                    <?php foreach ($actions as $act): ?>
                        <option value="<?= htmlspecialchars($act) ?>" <?= $filter_action==$act?'selected':'' ?>><?= htmlspecialchars($act) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-1">
                <button class="btn btn-sm btn-primary w-100"><i class="bi bi-funnel"></i> Lọc</button>
                <a href="logs.php" class="btn btn-sm btn-outline-secondary w-100"><i class="bi bi-x-circle"></i> Xoá lọc</a>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped mb-0" style="font-size:0.9rem;">
                <thead class="table-dark">
                    <tr>
                        <th class="ps-4">Thời Gian</th>
                        <th>User (Người thực hiện)</th>
                        <th>Hành Động</th>
                        <th>Chi tiết</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($logs) == 0): ?>
                        <tr><td colspan="4" class="text-center py-4 text-muted">Không có log nào khớp bộ lọc.</td></tr>
                    <?php endif; ?>
                    <?php foreach($logs as $log): ?>
                    <tr>
                        <td class="ps-4 text-muted small"><?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?></td>
                        <td class="fw-bold"><i class="bi bi-person text-secondary"></i> <?= htmlspecialchars($log['username']) ?></td>
                        <td class="<?= getActionColor($log['action']) ?>"><?= htmlspecialchars($log['action']) ?></td>
                        <td><?= htmlspecialchars($log['description']) ?></td>
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
