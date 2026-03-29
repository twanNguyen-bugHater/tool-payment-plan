<?php
// Tên file: customer_detail.php
require 'db.php';
require 'header.php';

if (!isset($_GET['id'])) {
    die("Thiếu ID khách hàng.");
}
$customer_id = intval($_GET['id']);

// Lấy thông tin khách hàng
$stmt = $pdo->prepare("SELECT c.*, u.username as sale_name, u.email as sale_email
                        FROM customers c
                        LEFT JOIN users u ON c.sale_id = u.id
                        WHERE c.id = ?");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    die("Không tìm thấy khách hàng với ID #$customer_id");
}

// Phân quyền: Sale chỉ thấy khách của mình
if ($_SESSION['role'] === 'sale' && $customer['sale_id'] != $_SESSION['user_id']) {
    die("Bạn không có quyền xem hồ sơ này.");
}

// Lấy danh sách đợt trả góp
$stmtInst = $pdo->prepare("SELECT * FROM installments WHERE customer_id = ? ORDER BY payment_number ASC");
$stmtInst->execute([$customer_id]);
$installments = $stmtInst->fetchAll(PDO::FETCH_ASSOC);

$total_installments = count($installments);
$paid_installments = 0;
$total_paid_amount = 0;
foreach ($installments as $inst) {
    if ($inst['status'] === 'paid') { 
        $paid_installments++; 
        $total_paid_amount += $inst['amount'];
    }
}
$progress = $total_installments > 0 ? round(($paid_installments / $total_installments) * 100) : 0;


// Helper functions
function getStatusBadgeD($status, $dueDate) {
    if ($status === 'paid') return '<span class="badge bg-success">Đã thu (PAID)</span>';
    if ($status === 'pending' && strtotime($dueDate) < strtotime(date('Y-m-d'))) {
        return '<span class="badge bg-danger">Quá hạn</span>';
    }
    if ($status === 'late') return '<span class="badge bg-warning text-dark">Trễ hẹn</span>';
    if ($status === 'cancelled') return '<span class="badge bg-dark border border-secondary">Nợ xấu</span>';
    return '<span class="badge bg-light text-dark border">Chờ thu</span>';
}
function getDebtBadgeD($s) {
    if ($s === 'completed') return '<span class="badge bg-success fs-6">Hoàn thành</span>';
    if ($s === 'bad_debt') return '<span class="badge bg-danger fs-6">Nợ xấu</span>';
    return '<span class="badge bg-warning text-dark fs-6">Chưa hoàn thành</span>';
}
function getPaymentTypeLabelD($t) {
    if ($t === 'trip3') return 'Trip 3';
    if ($t === 'trip2') return 'Trip 2';
    return 'Theo tháng (Monthly)';
}

// Quyền sửa khách hàng
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
} elseif ($_SESSION['role'] === 'sale') {
    if ($customer['sale_id'] == $_SESSION['user_id']) {
        $canEdit = true;
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <a href="customers.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Quay lại Danh sách</a>
    <div class="d-flex gap-2">
        <?php if ($customer['pending_delete'] == 1): ?>
            <?php 
                $canApprove = false;
                if ($_SESSION['role'] === 'admin') $canApprove = true;
                elseif ($_SESSION['role'] === 'leader' && ($customer['sale_id'] == $_SESSION['user_id'] || $customer['leader_id'] == $_SESSION['user_id'])) $canApprove = true;
            ?>
            <?php if ($canApprove): ?>
                <a href="approve_delete.php?id=<?= $customer['id'] ?>&action=approve" class="btn btn-danger btn-sm fw-bold" onclick="return confirm('Bạn có chắc chắn muốn duyệt xoá khách hàng này? Mọi dữ liệu sẽ mất.');"><i class="bi bi-check-circle"></i> DUYỆT XOÁ</a>
                <a href="approve_delete.php?id=<?= $customer['id'] ?>&action=reject" class="btn btn-secondary btn-sm fw-bold" onclick="return confirm('Huỷ yêu cầu xoá của Sale?');"><i class="bi bi-x-circle"></i> TỪ CHỐI XOÁ</a>
            <?php else: ?>
                <span class="badge bg-warning text-dark p-2" style="font-size:0.9rem;"><i class="bi bi-hourglass-split"></i> Đang chờ duyệt xoá</span>
            <?php endif; ?>
        <?php else: ?>
            <?php if ($canEdit): ?>
                <a href="edit_customer.php?id=<?= $customer['id'] ?>" class="btn btn-warning btn-sm fw-bold"><i class="bi bi-pencil-square"></i> Cập nhật / Chia lại đợt nợ</a>
                <a href="delete_customer.php?id=<?= $customer['id'] ?>" class="btn btn-danger btn-sm fw-bold" onclick="return confirm('CẢNH BÁO: Phân quyền của bạn cho phép <?php echo ($_SESSION['role'] == 'sale') ? 'YÊU CẦU' : 'THỰC HIỆN'; ?> xoá khách hàng này.\n\nBạn có chắc chắn muốn tiếp tục không?');"><i class="bi bi-trash"></i> Xoá Khách Hàng</a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'delete_rejected'): ?>
    <div class="alert alert-success alert-dismissible fade show fw-bold shadow-sm">
        <i class="bi bi-check-circle-fill"></i> Đã từ chối yêu cầu xoá! Khách hàng này lại tiếp tục hoạt động bình thường.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <!-- CỘT TRÁI: THÔNG TIN CHUNG -->
    <div class="col-md-4 mb-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-primary text-white fw-bold">
                <i class="bi bi-person-circle"></i> THÔNG TIN KHÁCH HÀNG #<?= $customer['id'] ?>
            </div>
            <div class="card-body">
                <table class="table table-borderless mb-0" style="font-size: 0.92rem;">
                    <tr>
                        <td class="text-muted fw-semibold" width="35%">Họ và tên:</td>
                        <td class="fw-bold fs-5 text-primary"><?= htmlspecialchars($customer['name']) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted fw-semibold">Email:</td>
                        <td class="fw-bold"><?= htmlspecialchars($customer['email'] ?? 'Chưa cập nhật') ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted fw-semibold">Giới tính:</td>
                        <td><?= htmlspecialchars($customer['gender']) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted fw-semibold">Sale phụ trách:</td>
                        <td><i class="bi bi-person text-primary"></i> <?= htmlspecialchars($customer['sale_name']) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted fw-semibold">Tổng Bill:</td>
                        <td class="fw-bold text-dark"><?= number_format($customer['total_bill'], 0, ',', '.') ?> <?= $customer['currency'] ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted fw-semibold">Còn nợ:</td>
                        <td class="fw-bold text-danger"><?= number_format($customer['remaining'], 0, ',', '.') ?> <?= $customer['currency'] ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted fw-semibold">Đã trả:</td>
                        <td class="fw-bold text-success"><?= number_format($total_paid_amount, 0, ',', '.') ?> <?= $customer['currency'] ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted fw-semibold">Hoàn thành liệu trình:</td>
                        <td><?= $customer['completion_date'] ? date('d/m/Y', strtotime($customer['completion_date'])) : '<span class="text-muted">Chưa có</span>' ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted fw-semibold">Hình thức:</td>
                        <td><?= getPaymentTypeLabelD($customer['payment_type']) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted fw-semibold">Trạng thái nợ:</td>
                        <td><?= getDebtBadgeD($customer['debt_status']) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted fw-semibold">File ĐH:</td>
                        <td>
                            <?php if (!empty($customer['treatment_file'])): ?>
                        <?php 
                            $ext = strtolower(pathinfo($customer['treatment_file'], PATHINFO_EXTENSION));
                            $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                        ?>
                        <?php if ($isImage): ?>
                            <a href="<?= htmlspecialchars($customer['treatment_file']) ?>" target="_blank">
                                <img src="<?= htmlspecialchars($customer['treatment_file']) ?>" alt="Kế hoạch điều trị" class="img-fluid rounded border mt-2" style="max-height: 200px; width: 100%; object-fit: cover;">
                            </a>
                        <?php else: ?>
                            <a href="<?= htmlspecialchars($customer['treatment_file']) ?>" target="_blank" class="btn btn-primary d-block w-100 fw-bold">
                                <i class="bi bi-file-earmark-pdf"></i> Xem File Kế Hoạch
                            </a>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="alert alert-secondary text-center">Chưa có file điều trị</div>
                    <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted fw-semibold">Ngày tạo:</td>
                        <td class="small text-muted"><?= date('d/m/Y H:i', strtotime($customer['created_at'])) ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <!-- CỘT PHẢI: LỊCH TRÌNH TRẢ GÓP -->
    <div class="col-md-8 mb-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
                <span><i class="bi bi-calendar-check text-success"></i> LỊCH TRÌNH TRẢ GÓP</span>
                <span class="badge bg-info text-dark fs-6"><?= $paid_installments ?>/<?= $total_installments ?> Đợt</span>
            </div>
            <div class="card-body pb-2">
                <!-- Thanh tiến trình -->
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <small class="fw-semibold text-muted">Tiến độ thu tiền</small>
                        <small class="fw-bold"><?= $progress ?>%</small>
                    </div>
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar bg-success" role="progressbar" style="width: <?= $progress ?>%"></div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0" style="font-size: 0.9rem;">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center" width="8%">Đợt</th>
                                <th width="20%">Số Tiền</th>
                                <th width="18%">Ngày Đến Hạn</th>
                                <th width="18%">Ngày Đã Trả</th>
                                <th class="text-center" width="18%">Trạng Thái</th>
                                <th width="18%">Ghi chú</th>
                                <th class="text-end" width="10%">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($installments as $inst): ?>
                            <tr>
                                <td class="text-center fw-bold"><?= $inst['payment_number'] ?></td>
                                <td class="fw-bold"><?= number_format($inst['amount'], 0, ',', '.') ?> <?= $customer['currency'] ?></td>
                                <td><?= date('d/m/Y', strtotime($inst['due_date'])) ?></td>
                                <td>
                                    <?php if ($inst['payment_date']): ?>
                                        <span class="text-success"><?= date('d/m/Y', strtotime($inst['payment_date'])) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center"><?= getStatusBadgeD($inst['status'], $inst['due_date']) ?></td>
                                <td class="text-muted small"><?= htmlspecialchars($inst['note'] ?? '') ?></td>
                                <td class="text-end">
                                    <?php if ($inst['status'] === 'pending'): ?>
                                        <a href="update_payment.php?id=<?= $inst['id'] ?>&action=paid&redirect=customer_detail&cid=<?= $customer_id ?>" class="btn btn-sm btn-outline-success" title="Đánh dấu đã thu">
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
</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
