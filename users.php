<?php
// Tên file: users.php
require 'db.php';
require 'header.php';

if ($_SESSION['role'] !== 'admin') {
    die("Từ chối truy cập! Chỉ Admin mới có quyền vào đây.");
}

// Xử lý Thêm User
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $email = $_POST['email'];
    $role = $_POST['role'];
    $leader_id = (!empty($_POST['leader_id'])) ? $_POST['leader_id'] : null;

    $stmt = $pdo->prepare("INSERT INTO users (username, password, email, role, leader_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$username, $password, $email, $role, $leader_id]);
    
    header("Location: users.php?msg=success");
    exit;
}

// Lấy danh sách users
$stmt = $pdo->query("SELECT u.*, l.username as leader_name 
                     FROM users u 
                     LEFT JOIN users l ON u.leader_id = l.id 
                     ORDER BY u.role ASC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ds Leader để chọn
$stmtL = $pdo->query("SELECT id, username FROM users WHERE role = 'leader'");
$leaders = $stmtL->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="row">
    <div class="col-md-8">
        <h3 class="mb-4"><i class="bi bi-person-badge text-primary"></i> QUẢN LÝ NHÂN SỰ</h3>
        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">ID</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Quyền (Role)</th>
                            <th>Leader Của Họ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($users as $u): ?>
                        <tr>
                            <td class="ps-4">#<?= $u['id'] ?></td>
                            <td class="fw-bold"><?= htmlspecialchars($u['username']) ?></td>
                            <td><?= htmlspecialchars($u['email']) ?></td>
                            <td>
                                <?php 
                                    if ($u['role'] == 'admin') echo '<span class="badge bg-danger">Admin</span>';
                                    if ($u['role'] == 'leader') echo '<span class="badge bg-primary">Leader</span>';
                                    if ($u['role'] == 'sale') echo '<span class="badge bg-success">Sale</span>';
                                ?>
                            </td>
                            <td class="text-muted">
                                <?= $u['leader_name'] ? htmlspecialchars($u['leader_name']) : '-' ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white text-center fw-bold">Thêm Nhân Sự Mới</div>
            <div class="card-body">
                <form action="users.php" method="POST">
                    <div class="mb-2">
                        <label>Tên đăng nhập</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="mb-2">
                        <label>Mật khẩu</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="mb-2">
                        <label>Email (Để nhận cảnh báo trễ nợ)</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-2">
                        <label>Quyền (Role)</label>
                        <select name="role" id="roleSelect" class="form-select">
                            <option value="sale">Nhân viên Sale</option>
                            <option value="leader">Trưởng nhóm (Leader)</option>
                            <option value="admin">Quản trị viên (Admin)</option>
                        </select>
                    </div>
                    <div class="mb-3" id="leaderDiv">
                        <label>Người quản lý (Dành cho Sale)</label>
                        <select name="leader_id" class="form-select">
                            <option value="">-- Không có --</option>
                            <?php foreach($leaders as $l): ?>
                                <option value="<?= $l['id'] ?>"><?= $l['username'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button class="btn btn-success w-100">Tạo tài khoản</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById('roleSelect').addEventListener('change', function() {
        if (this.value === 'sale') {
            document.getElementById('leaderDiv').style.display = 'block';
        } else {
            document.getElementById('leaderDiv').style.display = 'none';
        }
    });
</script>
</body>
</html>
