<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../../db_connect.php';
require_once '../../../includes/admin_header.php';
require_once '../../../includes/auth_check.php';
requireRole(['Admin', '']);

function e($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
function sanitize($s)
{
    return trim($s ?? '');
}

/* ===== CSRF token cho form POST ===== */
if (empty($_SESSION['_csrf'])) {
    $_SESSION['_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['_csrf'];

$currentRole = $_SESSION['Role'] ?? '';
$currentUserID = $_SESSION['UserID'] ?? 0;

/* Lấy id người dùng */
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo "<h3>Yêu cầu không hợp lệ.</h3>";
    exit;
}

/* Lấy thông tin user (mục tiêu) */
$sql = "
  SELECT UserID, Username, FullName, Email, Phone, Role,
         COALESCE(IsActive, 0) AS IsActive,
         CreatedAt
  FROM Users
  WHERE UserID = ?
  LIMIT 1
";
$stmt = $conn->prepare($sql);
if (!$stmt) die("Lỗi prepare SQL: " . $conn->error);
$stmt->bind_param("i", $id);
if (!$stmt->execute()) die("Lỗi execute: " . $stmt->error);
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    http_response_code(404);
    echo "<h3>Không tìm thấy người dùng.</h3>";
    exit;
}

/* ===== Chặn Manager truy cập/sửa Admin ===== */
if ($currentRole === 'Manager' && ($user['Role'] ?? '') === 'Admin') {
    $blockReason = 'Manager không thể sửa tài khoản Admin.';
    $blockAccess = true;
}

/* ===== Chặn Manager sửa Manager khác ===== */
if ($currentRole === 'Manager' && ($user['Role'] ?? '') === 'Manager' && $currentUserID !== $id) {
    $blockReason = 'Manager không thể sửa tài khoản Manager khác.';
    $blockAccess = true;
}

if (isset($blockAccess) && $blockAccess) {
    $blockReason = $blockReason ?? 'Bạn không có quyền truy cập.';
}

// Fallback an toàn cho các trường có thể thiếu/NULL
$user['IsActive'] = isset($user['IsActive']) ? (int)$user['IsActive'] : 0;
$user['Username'] = $user['Username'] ?? '';
$user['FullName'] = $user['FullName'] ?? '';
$user['Email']    = $user['Email']    ?? '';
$user['Phone']    = $user['Phone']    ?? '';
$user['Role']     = $user['Role']     ?? 'Student';

$errors = [];
$okMsg = null;
$ROLES = ['Admin', 'Manager', 'Student'];

/* ====== Xử lý submit ====== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* CSRF check */
    if (empty($_POST['_csrf']) || !hash_equals($_SESSION['_csrf'], $_POST['_csrf'])) {
        $errors[] = 'CSRF token không hợp lệ. Vui lòng tải lại trang.';
    }

    $Username = sanitize($_POST['Username'] ?? '');
    $FullName = sanitize($_POST['FullName'] ?? '');
    $Email    = sanitize($_POST['Email'] ?? '');
    $Phone    = sanitize($_POST['Phone'] ?? '');
    $Role     = sanitize($_POST['Role'] ?? '');
    $IsActive = isset($_POST['IsActive']) ? 1 : 0;
    $NewPass  = sanitize($_POST['NewPassword'] ?? '');

    if ($Username === '') $errors[] = 'Vui lòng nhập tên đăng nhập.';
    if ($FullName === '') $errors[] = 'Vui lòng nhập họ tên.';
    if ($Email !== '' && !filter_var($Email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email không hợp lệ.';
    if (!in_array($Role, $ROLES, true)) $errors[] = 'Vai trò không hợp lệ.';

    /* Chặn Manager nâng quyền bất kỳ ai lên Admin */
    if ($currentRole === 'Manager' && $Role === 'Admin') {
        $errors[] = 'Manager không được nâng quyền lên Admin.';
    }

    /* Chặn Manager nâng quyền Manager khác lên Admin */
    if ($currentRole === 'Manager' && $user['Role'] === 'Manager' && $currentUserID !== $id) {
        $errors[] = 'Manager không được chỉnh sửa tài khoản Manager khác.';
    }

    /* Kiểm tra trùng Username (ngoại trừ chính user đang sửa) */
    if (empty($errors)) {
        $check_sql = "SELECT 1 FROM Users WHERE Username = ? AND UserID <> ?";
        $check_stmt = $conn->prepare($check_sql);
        if (!$check_stmt) {
            $errors[] = "Lỗi hệ thống khi kiểm tra username.";
        } else {
            $check_stmt->bind_param("si", $Username, $id);
            $check_stmt->execute();
            $check_stmt->store_result();
            if ($check_stmt->num_rows > 0) $errors[] = 'Tên đăng nhập đã được sử dụng.';
            $check_stmt->close();
        }
    }

    /* Validate password mới nếu có */
    $hash = null;
    if ($NewPass !== '') {
        if (strlen($NewPass) < 8) {
            $errors[] = 'Mật khẩu phải có ít nhất 8 ký tự.';
        } elseif (!preg_match('/[A-Z]/', $NewPass) || !preg_match('/[a-z]/', $NewPass) || !preg_match('/[0-9]/', $NewPass)) {
            $errors[] = 'Mật khẩu phải chứa ít nhất một chữ hoa, một chữ thường và một số.';
        } else {
            $hash = password_hash($NewPass, PASSWORD_DEFAULT);
        }
    }

    if (empty($errors)) {
        // Dựng câu UPDATE (ưu tiên cột PasswordHash, fallback Password)
        $sql = "UPDATE Users SET Username=?, FullName=?, Email=?, Phone=?, Role=?, IsActive=?";
        $types = "sssssi";
        $params = [$Username, $FullName, $Email, $Phone, $Role, $IsActive];

        if ($hash) {
            $sql .= ", PasswordHash=?";
            $types .= "s";
            $params[] = $hash;
        }

        $sql  .= " WHERE UserID=?";
        $types .= "i";
        $params[] = $id;

        $stmt = $conn->prepare($sql);
        if (!$stmt && $hash) {
            // fallback cột Password (nếu PasswordHash không tồn tại)
            $sql = "UPDATE Users SET Username=?, FullName=?, Email=?, Phone=?, Role=?, IsActive=?, Password=? WHERE UserID=?";
            $types = "sssssisi";
            $params = [$Username, $FullName, $Email, $Phone, $Role, $IsActive, $hash, $id];
            $stmt = $conn->prepare($sql);
        }

        if (!$stmt) {
            $errors[] = "Lỗi hệ thống khi chuẩn bị cập nhật: " . $conn->error;
        } else {
            $stmt->bind_param($types, ...$params);
            if ($stmt->execute()) {
                $okMsg = '✅ Cập nhật thông tin người dùng thành công.';
                $user = array_merge($user, [
                    'Username' => $Username,
                    'FullName' => $FullName,
                    'Email' => $Email,
                    'Phone' => $Phone,
                    'Role' => $Role,
                    'IsActive' => $IsActive
                ]);
            } else {
                $errors[] = 'Lỗi khi cập nhật: ' . $stmt->error;
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Sửa người dùng #<?= (int)$id ?> | Hệ thống KTX</title>
    <link rel="stylesheet" href="../../assets/css/admin/admin_header.css">
    <link rel="stylesheet" href="../../../assets/css/staff/users/user_edit.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body>

    <div class="container">
        <div class="page-title">
            <h2>
                <i class="fa-regular fa-pen-to-square"></i> Sửa người dùng
                <span class="role-badge <?= strtolower($user['Role']) ?>">
                    <i class="fa-solid fa-<?= $user['Role'] === 'Admin' ? 'crown' : ($user['Role'] === 'Manager' ? 'user-gear' : 'user-graduate') ?>"></i>
                    <?= e($user['Role']) ?>
                </span>
            </h2>
            <div>
                <a class="btn ghost" href="../../staff/dashboard.php">
                    <i class="fa-solid fa-angles-left"></i> Quay lại danh sách
                </a>
            </div>
        </div>

        <?php if (isset($blockAccess) && $blockAccess): ?>
            <div class="alert error-list">
                <strong>Truy cập bị từ chối:</strong>
                <ul><li><?= e($blockReason) ?></li></ul>
            </div>
        <?php elseif ($errors): ?>
            <div class="alert error-list">
                <strong>Không thể lưu thay đổi:</strong>
                <ul><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul>
            </div>
        <?php elseif ($okMsg): ?>
            <div class="alert success"><?= e($okMsg) ?></div>
        <?php endif; ?>

        <?php if (!(isset($blockAccess) && $blockAccess)): ?>

        <form method="post" id="frmUserEdit">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <div class="grid">
                <div class="col-6">
                    <label>Tên đăng nhập <span class="req">*</span></label>
                    <input type="text" name="Username" required value="<?= e($user['Username']) ?>"
                        placeholder="Nhập tên đăng nhập" pattern="[a-zA-Z0-9_.-]{4,32}">
                </div>
                <div class="col-6">
                    <label>Họ và tên <span class="req">*</span></label>
                    <input type="text" name="FullName" required value="<?= e($user['FullName']) ?>"
                        placeholder="Nhập họ và tên đầy đủ">
                </div>

                <div class="col-6">
                    <label>Email</label>
                    <input type="email" name="Email" value="<?= e($user['Email']) ?>" placeholder="email@example.com">
                </div>
                <div class="col-6">
                    <label>Số điện thoại</label>
                    <input type="text" name="Phone" value="<?= e($user['Phone']) ?>" placeholder="+84...">
                </div>

                <div class="col-6">
                    <label>Vai trò</label>

                    <?php if ($currentRole === 'Admin'): ?>
                        <!-- Admin: chọn được mọi vai trò -->
                        <select name="Role" required>
                            <?php foreach ($ROLES as $r): ?>
                                <option value="<?= e($r) ?>" <?= ($r === $user['Role']) ? 'selected' : ''; ?>>
                                    <?= e($r) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                    <?php else: ?>
                        <?php if (in_array($user['Role'], ['Admin', 'Manager'], true)): ?>
                            <!-- Manager: không được đổi role của tài khoản Admin/Manager -->
                            <input type="hidden" name="Role" value="<?= e($user['Role']) ?>">
                            <input type="text" value="<?= e($user['Role']) ?>" class="readonly-role" disabled>
                            <small style="color:#6b7280">
                                Manager không thể thay đổi vai trò của tài khoản <?= e($user['Role']) ?>.
                            </small>
                        <?php else: ?>
                            <!-- Manager: với user thường, chỉ để được Student -->
                            <input type="hidden" name="Role" value="Student">
                            <input type="text" value="Student" class="readonly-role" disabled>
                            <small style="color:#6b7280">
                                Manager chỉ có thể gán hoặc giữ vai trò Student.
                            </small>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>


                <div class="col-6">
                    <label class="check-inline">
                        <input type="checkbox" name="IsActive" <?= (!empty($user['IsActive']) ? 'checked' : '') ?>>
                        <span>
                            Tài khoản kích hoạt
                            <span class="status-indicator <?= $user['IsActive'] ? 'active' : 'inactive' ?>">
                                <i class="fa-solid fa-<?= $user['IsActive'] ? 'check' : 'xmark' ?>"></i>
                                <?= $user['IsActive'] ? 'Đang hoạt động' : 'Đã vô hiệu hóa' ?>
                            </span>
                        </span>
                    </label>
                </div>

                <div class="col-12">
                    <label>Mật khẩu mới (tuỳ chọn)</label>
                    <input type="password" name="NewPassword" id="newPassword"
                        placeholder="Để trống nếu không đổi mật khẩu..." minlength="8">
                    <div class="password-strength" id="passwordStrength">
                        <div class="strength-bar">
                            <div class="strength-bar-inner" id="strengthBar"></div>
                        </div>
                        <div class="strength-text" id="strengthText">Độ mạnh mật khẩu</div>
                    </div>
                </div>

                <div class="col-12 actions">
                    <button class="btn" type="submit">
                        <i class="fa-solid fa-floppy-disk"></i> Lưu thay đổi
                    </button>
                    <a class="btn ghost" href="../../staff/dashboard.php">
                        <i class="fa-solid fa-times"></i> Hủy bỏ
                    </a>
                </div>
            </div>
        </form>
        <?php endif; ?>
    </div>

    <script>
        /* Password strength indicator */
        const passwordInput = document.getElementById('newPassword');
        const strengthBar = document.getElementById('strengthBar');
        const strengthText = document.getElementById('strengthText');
        const passwordStrength = document.getElementById('passwordStrength');

        passwordInput.addEventListener('input', function() {
            const password = this.value;
            if (password.length === 0) {
                passwordStrength.classList.remove('visible');
                return;
            }
            passwordStrength.classList.add('visible');

            let strength = 0,
                text = 'Rất yếu',
                cls = 'weak';
            if (password.length >= 8) strength++;
            if (password.length >= 12) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^a-zA-Z0-9]/.test(password)) strength++;

            if (strength >= 6) {
                text = 'Rất mạnh';
                cls = 'strong';
            } else if (strength >= 4) {
                text = 'Mạnh';
                cls = 'strong';
            } else if (strength >= 3) {
                text = 'Trung bình';
                cls = 'medium';
            } else if (strength >= 2) {
                text = 'Yếu';
                cls = 'weak';
            }

            strengthBar.className = 'strength-bar-inner ' + cls;
            strengthText.textContent = text;
        });

        /* Form validation */
        document.getElementById('frmUserEdit').addEventListener('submit', function(e) {
            const username = this.Username.value.trim();
            const fullname = this.FullName.value.trim();
            const newPassword = this.NewPassword.value;

            if (!username || !fullname) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Thiếu thông tin',
                    text: 'Tên đăng nhập và Họ tên là bắt buộc.'
                });
                return;
            }
            const usernameRegex = /^[a-zA-Z0-9_.-]{4,32}$/;
            if (!usernameRegex.test(username)) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Tên đăng nhập không hợp lệ',
                    text: 'Tên đăng nhập chỉ chứa chữ cái, số, _, ., - (4-32 ký tự).'
                });
                return;
            }
            if (newPassword.length > 0 && newPassword.length < 8) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Mật khẩu quá ngắn',
                    text: 'Mật khẩu phải có ít nhất 8 ký tự.'
                });
                return;
            }

            // loading
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.classList.add('loading');
            submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Đang xử lý...';
        });

        /* Real-time username validation */
        document.querySelector('input[name="Username"]').addEventListener('input', function() {
            const v = this.value;
            const ok = /^[a-zA-Z0-9_.-]{4,32}$/.test(v);
            this.setCustomValidity(v && !ok ? 'Tên đăng nhập chỉ chứa chữ cái, số, _, ., - (4-32 ký tự).' : '');
        });

        /* Auto-focus */
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelector('input[name="Username"]').focus();
        });
    </script>

    <script>
        /* Hiển thị popup nếu bị chặn */
        <?php if (isset($blockAccess) && $blockAccess): ?>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Không được phép',
                    text: '<?= e($blockReason) ?>',
                    confirmButtonText: 'Quay lại danh sách',
                    confirmButtonColor: '#ef4444',
                    allowOutsideClick: false,
                    allowEscapeKey: false
                }).then(() => {
                    window.location.href = '../../staff/dashboard.php';
                });
            });
        <?php endif; ?>
    </script>

</body>

</html>