<?php
// Tên file: edit_customer.php
require 'db.php';
require 'header.php';

if (!isset($_GET['id'])) {
    die("Thiếu ID khách hàng.");
}
$customer_id = intval($_GET['id']);

// Lấy thông tin khách
$stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    die("Không tìm thấy khách hàng.");
}

// Lấy danh sách đợt trả góp
$stmtInst = $pdo->prepare("SELECT * FROM installments WHERE customer_id = ? ORDER BY payment_number ASC");
$stmtInst->execute([$customer_id]);
$installments = $stmtInst->fetchAll(PDO::FETCH_ASSOC);

// Kiểm tra quyền
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
    die("<div class='alert alert-danger'>Bạn không có quyền sửa thông tin khách hàng này.</div>");
}
?>

<div class="row mb-3">
    <div class="col-12">
        <a href="customer_detail.php?id=<?= $customer_id ?>" class="btn btn-outline-secondary btn-sm mb-2"><i class="bi bi-arrow-left"></i> Quay lại Hồ Sơ</a>
        <h3 class="fw-bold text-primary"><i class="bi bi-pencil-square"></i> BIÊN TẬP KHÁCH HÀNG #<?= $customer_id ?></h3>
        <p class="text-muted">Bạn có thể sửa thông tin chung hoặc chia lại/cập nhật các đợt trả góp bên dưới.</p>
    </div>
</div>

<form action="edit_customer_action.php" method="POST" id="editForm" enctype="multipart/form-data">
    <input type="hidden" name="customer_id" value="<?= $customer_id ?>">
    
    <!-- 1. THÔNG TIN CHUNG -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-light fw-bold"><i class="bi bi-person"></i> THÔNG TIN CHUNG</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-semibold">Họ tên khách hàng *</label>
                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($customer['name'] ?? '') ?>" required>
                </div>
                <div class="col-md-2 mb-3">
                    <label class="form-label fw-semibold">Giới tính</label>
                    <select name="gender" class="form-select">
                        <option value="Nam" <?= $customer['gender']=='Nam'?'selected':'' ?>>Nam</option>
                        <option value="Nữ" <?= $customer['gender']=='Nữ'?'selected':'' ?>>Nữ</option>
                        <option value="Khác" <?= $customer['gender']=='Khác'?'selected':'' ?>>Khác</option>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-semibold">Email khách hàng</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($customer['email'] ?? '') ?>" placeholder="nguyenvana@gmail.com">
                </div>
            </div>

            <div class="row">
                <div class="col-md-5 mb-3">
                    <label class="form-label fw-semibold">File HD/Kế hoạch (PDF/Ảnh) mới</label>
                    <input type="file" name="treatment_file" class="form-control" accept=".pdf,image/*">
                    <?php if (!empty($customer['treatment_file'])): ?>
                        <div class="mt-2 small">
                            Đã tải lên: <a href="<?= htmlspecialchars($customer['treatment_file']) ?>" target="_blank" class="fw-bold">Xem file hiện tại</a>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-semibold">Ngày hoàn thành liệu trình</label>
                    <input type="date" name="completion_date" class="form-control" value="<?= $customer['completion_date'] ?>">
                </div>
                <div class="col-md-2 mb-3">
                    <label class="form-label fw-semibold">Tiền tệ</label>
                    <select name="currency" class="form-select">
                        <option value="VND" <?= $customer['currency']=='VND'?'selected':'' ?>>VND</option>
                        <option value="AUD" <?= $customer['currency']=='AUD'?'selected':'' ?>>AUD</option>
                        <option value="USD" <?= $customer['currency']=='USD'?'selected':'' ?>>USD</option>
                        <option value="NZD" <?= $customer['currency']=='NZD'?'selected':'' ?>>NZD</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-semibold">Hình thức trả góp</label>
                    <select name="payment_type" class="form-select">
                        <option value="monthly" <?= $customer['payment_type']=='monthly'?'selected':'' ?>>Theo tháng</option>
                        <option value="trip2" <?= $customer['payment_type']=='trip2'?'selected':'' ?>>Trip 2</option>
                        <option value="trip3" <?= $customer['payment_type']=='trip3'?'selected':'' ?>>Trip 3</option>
                    </select>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-semibold text-danger">Tổng hoá đơn (Total Bill)</label>
                    <input type="number" name="total_bill" class="form-control fw-bold text-danger" step="0.01" value="<?= floatval($customer['total_bill']) ?>" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-semibold text-warning">Tiền đăng ký trả góp</label>
                    <input type="number" name="initial_debt" class="form-control fw-bold text-warning" step="0.01" value="<?= floatval($customer['initial_debt']) ?>" required>
                    <div class="form-text">Tiền Nợ (Remaining) sẽ được hệ thống tính tự động bằng [Tiền đăng ký] - [Tổng đã thu].</div>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. QUẢN LÝ ĐỢT TRẢ GÓP -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <span class="fw-bold"><i class="bi bi-list-check"></i> CÁC ĐỢT TRẢ GÓP</span>
            <button type="button" class="btn btn-sm btn-outline-primary" onclick="addInstallmentRow()"><i class="bi bi-plus"></i> Thêm đợt mới</button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered mb-0" id="installmentTable">
                    <thead class="table-light">
                        <tr>
                            <th width="8%" class="text-center">Đợt số</th>
                            <th width="15%">Hạn đóng (Due)</th>
                            <th width="20%">Số tiền</th>
                            <th width="15%">Trạng thái</th>
                            <th width="15%">Ngày thu thật sự</th>
                            <th width="27%">Xoá đợt</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($installments as $i => $inst): ?>
                        <tr>
                            <td class="text-center align-middle fw-bold"><?= $inst['payment_number'] ?></td>
                            <td>
                                <!-- Truyền ID ẩn để biết đợt này đã tồn tại -->
                                <input type="hidden" name="inst_id[]" value="<?= $inst['id'] ?>">
                                <input type="hidden" name="inst_number[]" value="<?= $inst['payment_number'] ?>">
                                <input type="date" name="inst_due[]" class="form-control form-control-sm" value="<?= $inst['due_date'] ?>" required>
                            </td>
                            <td>
                                <input type="number" name="inst_amount[]" class="form-control form-control-sm fw-bold" step="0.01" value="<?= floatval($inst['amount']) ?>" required>
                            </td>
                            <td>
                                <select name="inst_status[]" class="form-select form-select-sm <?= $inst['status']=='paid'?'bg-success text-white':'' ?>">
                                    <option value="pending" <?= $inst['status']=='pending'?'selected':'' ?>>Chờ thu</option>
                                    <option value="paid" <?= $inst['status']=='paid'?'selected':'' ?>>Đã thu</option>
                                    <option value="late" <?= $inst['status']=='late'?'selected':'' ?>>Trễ hẹn</option>
                                    <option value="cancelled" <?= $inst['status']=='cancelled'?'selected':'' ?>>Nợ xấu</option>
                                </select>
                            </td>
                            <td>
                                <input type="date" name="inst_payment_date[]" class="form-control form-control-sm" value="<?= $inst['payment_date'] ?>">
                            </td>
                            <td class="align-middle">
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove()"><i class="bi bi-trash"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="p-3 text-muted small bg-light border-top">
                <b>Mẹo:</b> Để "chia lại đợt trả", bạn có thể sửa số tiền của các đợt hiện tại để giảm xuống, rồi bấm "Thêm đợt mới" để tạo đợt thu cuối cùng bằng số tiền còn lại.
            </div>
        </div>
    </div>

    <!-- BUTTONS -->
    <div class="mb-5 d-flex gap-2">
        <button type="submit" class="btn btn-lg btn-success px-5 fw-bold"><i class="bi bi-save"></i> LƯU THAY ĐỔI</button>
        <a href="customer_detail.php?id=<?= $customer_id ?>" class="btn btn-lg btn-secondary">Huỷ Bỏ</a>
    </div>

</form>

</div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    let newInstCounter = <?= count($installments) ?>;
    function addInstallmentRow() {
        newInstCounter++;
        let tbody = document.querySelector("#installmentTable tbody");
        let tr = document.createElement("tr");
        tr.innerHTML = `
            <td class="text-center align-middle text-primary fw-bold">Mới</td>
            <td>
                <input type="hidden" name="inst_id[]" value="NEW">
                <input type="hidden" name="inst_number[]" value="0">
                <input type="date" name="inst_due[]" class="form-control form-control-sm" required>
            </td>
            <td>
                <input type="number" name="inst_amount[]" class="form-control form-control-sm fw-bold" step="0.01" required placeholder="Số tiền">
            </td>
            <td>
                <select name="inst_status[]" class="form-select form-select-sm border-primary">
                    <option value="pending">Chờ thu</option>
                    <option value="paid">Đã thu</option>
                </select>
            </td>
            <td>
                <input type="date" name="inst_payment_date[]" class="form-control form-control-sm">
            </td>
            <td class="align-middle">
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove()"><i class="bi bi-trash"></i> Xoá</button>
            </td>
        `;
        tbody.appendChild(tr);
    }
</script>
</body>
</html>
