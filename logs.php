<?php
// Tên file: logs.php
require 'db.php';
require 'header.php';

if ($_SESSION['role'] !== 'admin') {
    die("Từ chối truy cập! Chỉ Admin mới có quyền vào đây.");
}

// Lấy danh sách log
$stmt = $pdo->query("SELECT l.*, u.username 
                     FROM activity_logs l 
                     JOIN users u ON l.user_id = u.id 
                     ORDER BY l.created_at DESC 
                     LIMIT 500");
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

function getActionColor($action) {
    if (strpos($action, 'ADD') !== false) return 'text-success fw-bold';
    if (strpos($action, 'UPDATE') !== false) return 'text-primary fw-bold';
    if (strpos($action, 'DELETE') !== false) return 'text-danger fw-bold';
    return 'text-secondary';
}
?>

<h3 class="mb-4"><i class="bi bi-journals text-primary"></i> LỊCH SỬ HOẠT ĐỘNG THÔNG KÊ HỆ THỐNG</h3>
<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped mb-0">
                <thead class="table-dark">
                    <tr>
                        <th class="ps-4">Thời Gian</th>
                        <th>User (Người thực hiện)</th>
                        <th>Hành Động</th>
                        <th>Chi tiết</th>
                    </tr>
                </thead>
                <tbody>
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
