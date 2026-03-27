<?php
// Tên file: add_customer.php
require 'db.php';
require 'header.php';

// Lấy danh sách Sale (nếu user là Admin hoặc Leader)
$sales = [];
if ($_SESSION['role'] === 'admin') {
    $stmt = $pdo->query("SELECT id, username FROM users WHERE role = 'sale' OR role = 'leader'");
    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif ($_SESSION['role'] === 'leader') {
    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE leader_id = ? OR id = ?");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<div class="row">
    <div class="col-12">
        <h4 class="mb-4"><i class="bi bi-person-plus text-primary"></i> LÊN HỒ SƠ KHÁCH HÀNG MỚI</h4>
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <form action="add_customer_action.php" method="POST" id="customerForm">
                    <h5 class="text-secondary mb-3"><i class="bi bi-info-circle"></i> THÔNG TIN CHUNG</h5>
                    <div class="row mb-4">
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-semibold">Tên Khách Hàng *</label>
                            <input type="text" name="name" class="form-control" required placeholder="Nguyễn Văn A">
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label fw-semibold">Giới tính</label>
                            <select name="gender" class="form-select">
                                <option value="Male">Nam (Male)</option>
                                <option value="Female">Nữ (Female)</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-semibold">Người Quản Lý Sale</label>
                            <?php if ($_SESSION['role'] === 'sale'): ?>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($_SESSION['username']) ?>" disabled>
                                <input type="hidden" name="sale_id" value="<?= $_SESSION['user_id'] ?>">
                            <?php else: ?>
                                <select name="sale_id" class="form-select text-primary fw-bold" required>
                                    <?php foreach ($sales as $sale): ?>
                                        <option value="<?= $sale['id'] ?>"><?= htmlspecialchars($sale['username']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold">Link File ĐH (Drive/Docs)</label>
                            <input type="url" name="treatment_file" class="form-control" placeholder="https://drive.google.com/...">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold text-danger">Tổng hoá đơn (Total Bill) *</label>
                            <input type="number" name="total_bill" class="form-control fw-bold text-danger" required placeholder="Vd: 50000" id="total_bill" step="0.01">
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label fw-semibold">Tiền tệ</label>
                            <select name="currency" class="form-select">
                                <option value="AUD">AUD</option>
                                <option value="NZD">NZD</option>
                                <option value="USD">USD</option>
                                <option value="VND">VND</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold text-warning">Tiền còn nợ (Remaining) *</label>
                            <input type="number" name="remaining" class="form-control fw-bold text-warning" required placeholder="Vd: 30000" id="remaining" step="0.01">
                        </div>
                    </div>

                    <h5 class="text-secondary mt-2 mb-3"><i class="bi bi-calendar-check"></i> DANH SÁCH ĐỢT TRẢ GÓP</h5>
                    <p class="text-muted small">Tạo các khung thời gian trả góp tương ứng với lịch trình thu tiền.</p>
                    
                    <div class="table-responsive">
                        <table class="table table-bordered mb-2" id="installmentTable">
                            <thead class="table-light">
                                <tr>
                                    <th width="15%">Đợt số</th>
                                    <th width="35%">Số tiền cần trả *</th>
                                    <th width="35%">Ngày trả dự kiến *</th>
                                    <th width="15%" class="text-center">Xóa</th>
                                </tr>
                            </thead>
                            <tbody id="installmentBody">
                                <!-- JS sẽ render vào đây -->
                            </tbody>
                        </table>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="addInstallmentRow()">
                            <i class="bi bi-plus-circle"></i> Thêm đợt trả góp
                        </button>
                    </div>

                    <hr class="my-4">
                    <button type="submit" class="btn btn-success p-3 px-5 fw-bold" style="font-size:1.1rem">LƯU HỒ SƠ KHÁCH HÀNG</button>
                    <a href="index.php" class="btn btn-light p-3 ms-2">Hủy bỏ</a>
                </form>
            </div>
        </div>
    </div>
</div>

</div> 
</div>
</div> 

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    let rowCount = 0;

    function addInstallmentRow() {
        rowCount++;
        const tr = document.createElement('tr');
        tr.id = 'row_' + rowCount;
        tr.innerHTML = `
            <td>
                <input type="number" name="payment_number[]" class="form-control text-center disabled" value="${rowCount}" readonly>
            </td>
            <td>
                <input type="number" name="amount[]" class="form-control fw-bold" placeholder="VD: 500" step="0.01" required>
            </td>
            <td>
                <input type="date" name="due_date[]" class="form-control" required>
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-danger mt-1" onclick="removeRow(${rowCount})"><i class="bi bi-trash"></i></button>
            </td>
        `;
        document.getElementById('installmentBody').appendChild(tr);
        recalcNumbers();
    }

    function removeRow(id) {
        document.getElementById('row_' + id).remove();
        recalcNumbers();
    }

    function recalcNumbers() {
        const rows = document.querySelectorAll('#installmentBody tr');
        rowCount = 0;
        rows.forEach((row, index) => {
            rowCount = index + 1;
            row.querySelector('input[name="payment_number[]"]').value = rowCount;
            row.id = 'row_' + rowCount;
            row.querySelector('.btn-danger').setAttribute('onclick', `removeRow(${rowCount})`);
        });
    }

    // Tự động thêm đợt 1
    addInstallmentRow();

    // Mẹo UX: Tự động set remaining bằng total_bill ban đầu
    document.getElementById('total_bill').addEventListener('input', function() {
        document.getElementById('remaining').value = this.value;
    });
</script>
</body>
</html>
