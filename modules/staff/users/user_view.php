<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../../db_connect.php';
require_once '../../../includes/admin_header.php';
require_once '../../../includes/auth_check.php';
requireRole(['Admin', 'Manager']);

function e($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

$currentRole   = $_SESSION['Role']   ?? '';
$currentUserID = $_SESSION['UserID'] ?? 0;

/* ===== CSRF token cho AJAX (xóa) ===== */
if (empty($_SESSION['_csrf'])) {
    $_SESSION['_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['_csrf'];

/* ===== Lấy id từ query ===== */
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo "<h3>Yêu cầu không hợp lệ.</h3><p><a href='../../staff/dashboard.php'>Quay lại danh sách</a></p>";
    exit;
}

/* ===== Lấy thông tin người dùng ===== */
$sql = "SELECT UserID, Username, FullName, Email, Phone, Role, COALESCE(IsActive,0) AS IsActive, CreatedAt
        FROM Users WHERE UserID = ? LIMIT 1";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Lỗi prepare: " . $conn->error);
}
$stmt->bind_param("i", $id);
$stmt->execute();
$res  = $stmt->get_result();
$u    = $res->fetch_assoc();
$stmt->close();

if (!$u) {
    http_response_code(404);
    echo "<h3>Không tìm thấy người dùng.</h3><p><a href='../../staff/dashboard.php'>Quay lại danh sách</a></p>";
    exit;
}

/* ===== Quy ước nút thao tác =====
   - Admin: được Sửa/Xóa tất cả (kể cả Admin khác), chặn tự xóa ở API.
   - Manager: được Sửa/Xóa mọi user TRỪ Admin TRỪ Manager khác.
*/
$viewRole = $u['Role'] ?? 'Student';
$canManageThisUser = (
    $currentRole === 'Admin' ||
    ($currentRole === 'Manager' && $viewRole !== 'Admin' && ($currentUserID === (int)$u['UserID']))
);

$badgeIcon  = ($viewRole === 'Admin') ? 'crown' : (($viewRole === 'Manager') ? 'user-tie' : 'user-graduate');
$badgeClass = strtolower($viewRole);
$isActive   = (int)$u['IsActive'] === 1;

/* Tạo avatar từ tên */
$initials = '';
$name  = trim($u['FullName'] ?: $u['Username']);
$parts = preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY);
if (count($parts) >= 2) {
    $initials = mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr(end($parts), 0, 1));
} else {
    $initials = mb_strtoupper(mb_substr($name, 0, 2));
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hồ sơ người dùng #<?= (int)$u['UserID'] ?> | Hệ thống KTX</title>
    <link rel="stylesheet" href="../../assets/css/admin/admin_header.css">
    <link rel="stylesheet" href="../../../assets/css/staff/users/user_view.css">
    <link rel="stylesheet" href="../../../assets/vendor/fontawesome/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <meta name="csrf-token" content="<?= e($csrf) ?>">
</head>

<body>

    <!-- Wrapper đã được scope để không xung đột layout chung -->
    <div class="user-view-page">
        <div class="uv-container">

            <div class="page-header">
                <h2><i class="fa-regular fa-id-card"></i> Hồ sơ người dùng</h2>
                <div class="actions">
                    <a href="../../staff/dashboard.php" class="btn ghost">
                        <i class="fa-solid fa-arrow-left"></i> Quay lại danh sách
                    </a>
                    <?php if ($canManageThisUser): ?>
                    <?php elseif ($currentRole === 'Manager' && $viewRole === 'Admin'): ?>
                        <span class="btn ghost" title="Manager không thể sửa/xóa Admin" style="opacity:.6;cursor:not-allowed">
                            <i class="fa-solid fa-lock"></i> Hạn chế quyền
                        </span>
                    <?php elseif ($currentRole === 'Manager' && $viewRole === 'Manager' && $currentUserID !== (int)$u['UserID']): ?>
                        <span class="btn ghost" title="Manager không thể sửa Manager khác" style="opacity:.6;cursor:not-allowed">
                            <i class="fa-solid fa-lock"></i> Hạn chế quyền
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="profile-wrap">
                <div class="profile-header">
                    <div class="avatar" aria-hidden="true"><?= e($initials) ?></div>
                    <div class="profile-title">
                        <h2>
                            <?= e($u['FullName'] ?: $u['Username']) ?>
                            <span class="role-badge <?= $badgeClass ?>">
                                <i class="fa-solid fa-<?= $badgeIcon ?>"></i> <?= e($viewRole) ?>
                            </span>
                        </h2>
                        <div class="sub">ID: #<?= (int)$u['UserID'] ?> · Tham gia từ <?= e(date('d/m/Y', strtotime($u['CreatedAt']))) ?></div>
                    </div>
                    <div>
                        <span class="status-chip <?= $isActive ? 'active' : 'inactive' ?>">
                            <i class="fa-solid fa-<?= $isActive ? 'circle-check' : 'circle-xmark' ?>"></i>
                            <?= $isActive ? 'Đang hoạt động' : 'Đã vô hiệu hóa' ?>
                        </span>
                    </div>
                </div>

                <div class="profile-body">
                    <div class="grid">
                        <div class="col-6">
                            <div class="field">
                                <div class="label">Tên đăng nhập</div>
                                <div class="value"><i class="fa-solid fa-user"></i><?= e($u['Username']) ?></div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="field">
                                <div class="label">Họ và tên</div>
                                <div class="value"><i class="fa-solid fa-signature"></i><?= e($u['FullName'] ?: '—') ?></div>
                            </div>
                        </div>

                        <div class="col-6">
                            <div class="field">
                                <div class="label">Email</div>
                                <div class="value"><i class="fa-solid fa-envelope"></i><?= e($u['Email'] ?: '—') ?></div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="field">
                                <div class="label">Số điện thoại</div>
                                <div class="value"><i class="fa-solid fa-phone"></i><?= e($u['Phone'] ?: '—') ?></div>
                            </div>
                        </div>

                        <div class="col-6">
                            <div class="field">
                                <div class="label">Vai trò</div>
                                <div class="value"><i class="fa-solid fa-<?= $badgeIcon ?>"></i><?= e($viewRole) ?></div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="field">
                                <div class="label">Trạng thái</div>
                                <div class="value">
                                    <i class="fa-solid fa-<?= $isActive ? 'check-circle' : 'times-circle' ?>"></i>
                                    <?= $isActive ? 'Kích hoạt' : 'Vô hiệu hóa' ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="hr"></div>
                            <div class="muted">Thông tin bổ sung</div>
                            <div class="note">
                                <i class="fa-solid fa-info-circle"></i>
                                Trang này chỉ hiển thị thông tin người dùng.
                                <?php if ($canManageThisUser): ?>
                                    Bạn có thể <b>Chỉnh sửa</b> hoặc <b>Xóa</b> người dùng này bằng các nút bên dưới.
                                <?php elseif ($currentRole === 'Manager' && $viewRole === 'Admin'): ?>
                                    Tài khoản Manager không có quyền chỉnh sửa hoặc xóa tài khoản Admin.
                                <?php elseif ($currentRole === 'Manager' && $viewRole === 'Manager' && $currentUserID !== (int)$u['UserID']): ?>
                                    Tài khoản Manager không có quyền chỉnh sửa hoặc xóa tài khoản Manager khác.
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="actions">
                        <a href="../../staff/dashboard.php" class="btn ghost">
                            <i class="fa-solid fa-arrow-left"></i> Quay lại danh sách
                        </a>
                        <?php if ($canManageThisUser): ?>
                            <a href="user_edit.php?id=<?= (int)$u['UserID'] ?>" class="btn primary">
                                <i class="fa-regular fa-pen-to-square"></i> Chỉnh sửa
                            </a>
                            <button class="btn danger"
                                onclick="deleteUser(<?= (int)$u['UserID'] ?>,'<?= e($u['FullName'] ?: $u['Username']) ?>','<?= e($u['Role']) ?>')">
                                <i class="fa-regular fa-trash-can"></i> Xóa người dùng
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div><!-- /.uv-container -->
    </div><!-- /.user-view-page -->

    <script>
        function deleteUser(id, name, targetRole) {
            const myRole = '<?= e($currentRole) ?>';
            if (myRole === 'Manager' && targetRole === 'Admin') {
                Swal.fire({
                    icon: 'error',
                    title: 'Không được phép',
                    text: 'Manager không thể xóa tài khoản Admin.',
                    confirmButtonColor: '#3b82f6'
                });
                return;
            }
            if (myRole === 'Manager' && targetRole === 'Manager') {
                Swal.fire({
                    icon: 'error',
                    title: 'Không được phép',
                    text: 'Manager không thể xóa tài khoản Manager khác.',
                    confirmButtonColor: '#3b82f6'
                });
                return;
            }
            const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            Swal.fire({
                title: 'Xóa người dùng?',
                html: `Bạn có chắc muốn xóa <b>${name}</b>?<br><small class="muted">Hành động này không thể hoàn tác.</small>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                cancelButtonText: 'Hủy bỏ',
                confirmButtonText: '<i class="fa-regular fa-trash-can"></i> Xóa người dùng',
                reverseButtons: true
            }).then(res => {
                if (!res.isConfirmed) return;
                fetch('user_delete_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                        body: new URLSearchParams({
                            id,
                            _csrf: token
                        })
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Đã xóa!',
                                text: data.message,
                                timer: 1500,
                                showConfirmButton: false
                            });
                            setTimeout(() => {
                                window.location.href = '../../staff/dashboard.php';
                            }, 700);
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Lỗi!',
                                text: data.message || 'Không thể xóa người dùng',
                                confirmButtonColor: '#3b82f6'
                            });
                        }
                    })
                    .catch(() => Swal.fire({
                        icon: 'error',
                        title: 'Lỗi kết nối',
                        text: 'Không thể kết nối đến máy chủ',
                        confirmButtonColor: '#3b82f6'
                    }));
            });
        }
    </script>

</body>

</html>
