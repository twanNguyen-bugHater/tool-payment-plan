<?php
// Tên file: users.php
require 'db.php';
require 'header.php';

// Admin và Leader đều được vào trang quản lý user
if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'leader') {
    die("Từ chối truy cập! Chỉ Admin hoặc Leader mới có quyền vào đây.");
}

$msg = '';

// ========== XỬ LÝ THÊM USER ==========
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {

    // THÊM USER MỚI
    if ($_POST['action'] === 'add_user') {
        $username = trim($_POST['username']);
        $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
        $email = $_POST['email'];
        $role = $_POST['role'];
        $leader_id = (!empty($_POST['leader_id'])) ? $_POST['leader_id'] : null;
        
        // Leader chỉ được tạo Sale
        if ($_SESSION['role'] === 'leader' && $role !== 'sale') {
            $msg = 'Leader chỉ được phép tạo tài khoản Sale.';
        } else {
            $stmt = $pdo->prepare("INSERT INTO users (username, password, email, role, leader_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$username, $password, $email, $role, $leader_id]);

            $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description) VALUES (?, ?, ?)");
            $logStmt->execute([$_SESSION['user_id'], 'ADD_USER', "Tạo tài khoản mới: $username (role: $role)"]);
            
            header("Location: users.php?msg=Tạo tài khoản thành công!");
            exit;
        }
    }
    
    // ĐỔI MẬT KHẨU
    if ($_POST['action'] === 'change_password') {
        $target_user_id = intval($_POST['target_user_id']);
        $new_password = password_hash($_POST['new_password'], PASSWORD_BCRYPT);
        
        // Kiểm tra quyền
        $targetUser = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $targetUser->execute([$target_user_id]);
        $tu = $targetUser->fetch(PDO::FETCH_ASSOC);
        
        $canChange = false;
        if ($_SESSION['role'] === 'admin') {
            $canChange = true; // Admin đổi được tất cả
        } elseif ($_SESSION['role'] === 'leader') {
            // Leader chỉ đổi được Sale thuộc team mình
            if ($tu && $tu['role'] === 'sale' && $tu['leader_id'] == $_SESSION['user_id']) {
                $canChange = true;
            }
        }
        
        if ($canChange && $tu) {
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$new_password, $target_user_id]);
            $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description) VALUES (?, ?, ?)");
            $logStmt->execute([$_SESSION['user_id'], 'CHANGE_PASSWORD', "Đổi mật khẩu cho user: {$tu['username']}"]);
            header("Location: users.php?msg=Đã đổi mật khẩu cho {$tu['username']}!");
            exit;
        } else {
            $msg = 'Bạn không có quyền đổi mật khẩu cho user này.';
        }
    }
    
    // XOÁ USER
    if ($_POST['action'] === 'delete_user') {
        $target_user_id = intval($_POST['target_user_id']);
        
        // Không cho xoá chính mình
        if ($target_user_id == $_SESSION['user_id']) {
            $msg = 'Không thể xoá chính tài khoản đang đăng nhập!';
        } else {
            $targetUser = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $targetUser->execute([$target_user_id]);
            $tu = $targetUser->fetch(PDO::FETCH_ASSOC);

            $canDelete = false;
            if ($_SESSION['role'] === 'admin') {
                $canDelete = true; // Admin xoá được tất cả (kể cả leader)
            } elseif ($_SESSION['role'] === 'leader') {
                // Leader chỉ xoá Sale thuộc team mình 
                if ($tu && $tu['role'] === 'sale' && $tu['leader_id'] == $_SESSION['user_id']) {
                    $canDelete = true;
                }
            }
            
            if ($canDelete && $tu) {
                // Chuyển khách hàng của user bị xoá sang null (unassigned)
                $pdo->prepare("UPDATE customers SET sale_id = NULL WHERE sale_id = ?")->execute([$target_user_id]);
                $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$target_user_id]);
                
                $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description) VALUES (?, ?, ?)");
                $logStmt->execute([$_SESSION['user_id'], 'DELETE_USER', "Đã xoá tài khoản: {$tu['username']} (role: {$tu['role']})"]);
                header("Location: users.php?msg=Đã xoá tài khoản {$tu['username']}!");
                exit;
            } else {
                $msg = 'Bạn không có quyền xoá user này.';
            }
        }
    }
}

// Lấy message từ URL
if (isset($_GET['msg'])) $msg = $_GET['msg'];

// Lấy danh sách users (Theo quyền)
if ($_SESSION['role'] === 'admin') {
    $stmt = $pdo->query("SELECT u.*, l.username as leader_name FROM users u LEFT JOIN users l ON u.leader_id = l.id ORDER BY u.role ASC");
} else {
    // Leader chỉ thấy mình + sale thuộc team
    $stmt = $pdo->prepare("SELECT u.*, l.username as leader_name FROM users u LEFT JOIN users l ON u.leader_id = l.id WHERE u.id = ? OR u.leader_id = ? ORDER BY u.role ASC");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
}
$users = ($_SESSION['role'] === 'admin') ? $stmt->fetchAll(PDO::FETCH_ASSOC) : $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ds Leader để chọn
$stmtL = $pdo->query("SELECT id, username FROM users WHERE role = 'leader'");
$leaders = $stmtL->fetchAll(PDO::FETCH_ASSOC);
?>

<?php if(!empty($msg)): ?>
    <div class="alert alert-info alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($msg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-8">
        <h3 class="mb-4"><i class="bi bi-person-badge text-primary"></i> QUẢN LÝ NHÂN SỰ</h3>
        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <table class="table table-hover mb-0" style="font-size:0.9rem;">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">ID</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Quyền (Role)</th>
                            <th>Leader</th>
                            <th class="text-end pe-4">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($users as $u): ?>
                        <tr>
                            <td class="ps-4">#<?= $u['id'] ?></td>
                            <td class="fw-bold"><?= htmlspecialchars($u['username'] ?? '') ?></td>
                            <td><?= htmlspecialchars($u['email'] ?? '') ?></td>
                            <td>
                                <?php 
                                    if ($u['role'] == 'admin') echo '<span class="badge bg-danger">Admin</span>';
                                    if ($u['role'] == 'leader') echo '<span class="badge bg-primary">Leader</span>';
                                    if ($u['role'] == 'sale') echo '<span class="badge bg-success">Sale</span>';
                                ?>
                            </td>
                            <td class="text-muted">
                                <?= !empty($u['leader_name']) ? htmlspecialchars($u['leader_name']) : '-' ?>
                            </td>
                            <td class="text-end pe-4">
                                <?php 
                                // Hiển thị nút theo quyền
                                $showActions = false;
                                if ($_SESSION['role'] === 'admin' && $u['id'] != $_SESSION['user_id']) {
                                    $showActions = true;
                                } elseif ($_SESSION['role'] === 'leader' && $u['role'] === 'sale' && $u['leader_id'] == $_SESSION['user_id']) {
                                    $showActions = true;
                                }
                                ?>
                                <?php if ($showActions): ?>
                                    <!-- Nút Đổi mật khẩu -->
                                    <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modalPwd<?= $u['id'] ?>" title="Đổi mật khẩu">
                                        <i class="bi bi-key"></i>
                                    </button>
                                    <!-- Nút Xoá -->
                                    <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalDel<?= $u['id'] ?>" title="Xoá tài khoản">
                                        <i class="bi bi-trash"></i>
                                    </button>

                                    <!-- Modal Đổi MK -->
                                    <div class="modal fade" id="modalPwd<?= $u['id'] ?>" tabindex="-1">
                                        <div class="modal-dialog modal-sm">
                                            <div class="modal-content">
                                                <form method="POST">
                                                    <input type="hidden" name="action" value="change_password">
                                                    <input type="hidden" name="target_user_id" value="<?= $u['id'] ?>">
                                                    <div class="modal-header"><h6 class="modal-title">Đổi mật khẩu: <?= htmlspecialchars($u['username']) ?></h6></div>
                                                    <div class="modal-body">
                                                        <input type="password" name="new_password" class="form-control" placeholder="Mật khẩu mới" required minlength="4">
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Huỷ</button>
                                                        <button type="submit" class="btn btn-sm btn-warning">Đổi mật khẩu</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Modal Xoá -->
                                    <div class="modal fade" id="modalDel<?= $u['id'] ?>" tabindex="-1">
                                        <div class="modal-dialog modal-sm">
                                            <div class="modal-content">
                                                <form method="POST">
                                                    <input type="hidden" name="action" value="delete_user">
                                                    <input type="hidden" name="target_user_id" value="<?= $u['id'] ?>">
                                                    <div class="modal-header"><h6 class="modal-title text-danger">Xác nhận xoá</h6></div>
                                                    <div class="modal-body">
                                                        Bạn có chắc muốn xoá tài khoản <strong><?= htmlspecialchars($u['username']) ?></strong>?
                                                        <br><small class="text-muted">Khách hàng thuộc user này sẽ bị chuyển thành "Chưa gán Sale".</small>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Huỷ</button>
                                                        <button type="submit" class="btn btn-sm btn-danger">Xoá vĩnh viễn</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
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
                    <input type="hidden" name="action" value="add_user">
                    <div class="mb-2">
                        <label>Tên đăng nhập</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="mb-2">
                        <label>Mật khẩu</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="mb-2">
                        <label>Quyền (Role)</label>
                        <select name="role" id="roleSelect" class="form-select">
                            <option value="sale">Nhân viên Sale</option>
                            <?php if ($_SESSION['role'] === 'admin'): ?>
                                <option value="leader">Trưởng nhóm (Leader)</option>
                                <option value="admin">Quản trị viên (Admin)</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="mb-2" id="emailDiv" style="display:none;">
                        <label>Email Leader <small class="text-muted">(Nhận thông báo nhắc nợ)</small></label>
                        <input type="email" name="email" id="emailInput" class="form-control" placeholder="vd: leader@company.com">
                    </div>
                    <div class="mb-3" id="leaderDiv">
                        <label>Người quản lý (Dành cho Sale)</label>
                        <select name="leader_id" class="form-select">
                            <option value="">-- Không có --</option>
                            <?php foreach($leaders as $l): ?>
                                <option value="<?= $l['id'] ?>" <?= ($_SESSION['role']==='leader' && $l['id']==$_SESSION['user_id'])?'selected':'' ?>><?= $l['username'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button class="btn btn-success w-100">Tạo tài khoản</button>
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
    function toggleFields() {
        var role = document.getElementById('roleSelect').value;
        document.getElementById('leaderDiv').style.display = (role === 'sale') ? 'block' : 'none';
        document.getElementById('emailDiv').style.display = (role === 'leader') ? 'block' : 'none';
        // Email chỉ required khi tạo Leader
        var emailInput = document.getElementById('emailInput');
        if (role === 'leader') {
            emailInput.setAttribute('required', 'required');
        } else {
            emailInput.removeAttribute('required');
            emailInput.value = '';
        }
    }
    document.getElementById('roleSelect').addEventListener('change', toggleFields);
    toggleFields();
</script>
</body>
</html>
