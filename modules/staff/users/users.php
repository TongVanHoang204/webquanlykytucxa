<?php
if (session_status() === PHP_SESSION_NONE) session_start();

include '../../../db_connect.php';
include '../../../includes/admin_header.php';
include '../../../includes/auth_check.php';

requireRole(['Admin', 'Manager']);

if (empty($_SESSION['_csrf'])) {
    $_SESSION['_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['_csrf'];

if (isset($_GET['delete'])) {
    sendRequestError('Chuc nang xoa nguoi dung chi chap nhan POST co CSRF token.', 405);
}

/* ===================== BỘ LỌC & TÌM KIẾM ===================== */
$keyword      = $_GET['search']  ?? '';
$role_filter  = $_GET['role']    ?? 'all';
$sort_by      = $_GET['sort']    ?? 'created_at_desc';

$name_filter     = $_GET['name']    ?? '';
$mssv_filter     = $_GET['mssv']    ?? '';
$gender_filter   = $_GET['gender']  ?? 'all';
$faculty_filter  = $_GET['faculty'] ?? 'all';
$isdorm_filter   = $_GET['isdorm']  ?? 'all';

/* ===================== XÂY DỰNG CÂU TRUY VẤN ===================== */
$sql = "
SELECT 
    u.UserID, u.FullName, u.Email, u.Role, u.CreatedAt,
    s.StudentCode, s.Gender, s.FacultyID, s.Phone,
    f.FacultyName,
    r.RoomNumber, b.BuildingName,
    c.ContractID
FROM Users u
LEFT JOIN Students s 
    ON s.UserID = u.UserID
LEFT JOIN Faculties f
    ON f.FacultyID = s.FacultyID
LEFT JOIN Contracts c 
    ON c.StudentID = s.StudentID AND c.Status = 'Hiệu lực'
LEFT JOIN Rooms r 
    ON r.RoomID = c.RoomID
LEFT JOIN Buildings b 
    ON b.BuildingID = r.BuildingID
WHERE 1=1
";

if (!empty($keyword)) {
    $kw = $conn->real_escape_string($keyword);
    $sql .= " AND (u.FullName LIKE '%$kw%' 
              OR u.Email LIKE '%$kw%' 
              OR u.Role LIKE '%$kw%' 
              OR s.StudentCode LIKE '%$kw%')";
}

if (!empty($role_filter) && $role_filter !== 'all') {
    $role = $conn->real_escape_string($role_filter);
    $sql .= " AND u.Role = '$role'";
}

if (!empty($name_filter)) {
    $name = $conn->real_escape_string($name_filter);
    $sql .= " AND u.FullName LIKE '%$name%'";
}

if (!empty($mssv_filter)) {
    $mssv = $conn->real_escape_string($mssv_filter);
    $sql .= " AND s.StudentCode LIKE '%$mssv%'";
}

if ($gender_filter !== 'all' && $gender_filter !== '') {
    $gender = $conn->real_escape_string($gender_filter);
    $sql .= " AND s.Gender = '$gender'";
}

if (!empty($faculty_filter) && $faculty_filter !== 'all') {
    $faculty_id = (int)$faculty_filter;
    $sql .= " AND s.FacultyID = $faculty_id";
}

if ($isdorm_filter === '1') {
    $sql .= " AND c.ContractID IS NOT NULL";
} elseif ($isdorm_filter === '0') {
    $sql .= " AND c.ContractID IS NULL";
}

$sort_clause = " ORDER BY ";
switch ($sort_by) {
    case 'name_asc':         $sort_clause .= "u.FullName ASC"; break;
    case 'name_desc':        $sort_clause .= "u.FullName DESC"; break;
    case 'email_asc':        $sort_clause .= "u.Email ASC"; break;
    case 'email_desc':       $sort_clause .= "u.Email DESC"; break;
    case 'created_at_asc':   $sort_clause .= "u.CreatedAt ASC"; break;
    default:                 $sort_clause .= "u.CreatedAt DESC"; break;
}
$sql .= $sort_clause;

/* ===================== THỐNG KÊ ===================== */
$stats = ['total' => 0, 'admins' => 0, 'staff' => 0, 'users' => 0];

$stats_result = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN Role = 'Admin' THEN 1 ELSE 0 END) as admins,
        SUM(CASE WHEN Role = 'Manager' THEN 1 ELSE 0 END) as staff,
        SUM(CASE WHEN Role = 'Student' THEN 1 ELSE 0 END) as users
    FROM Users
");

if ($stats_result && $stats_row = $stats_result->fetch_assoc()) {
    $stats = $stats_row;
}

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý người dùng - Hệ Thống Ký Túc Xá</title>
    <link rel="stylesheet" href="<?= $base ?>assets/css/global.css">
    <link rel="stylesheet" href="<?= $base ?>assets/css/modules_shared.css">
    <link rel="stylesheet" href="<?= $base ?>assets/css/admin/admin_header.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
</head>

<body>
    <div class="mod-container">
        <!-- Header -->
        <div class="mod-header">
            <div class="mod-header-left">
                <h2><i class="fas fa-users-cog"></i> Quản lý người dùng</h2>
            </div>
            <div class="mod-header-right">
                <?php if ($_SESSION['Role'] === 'Admin'): ?>
                <a href="<?= $base ?>modules/staff/users/user_create.php" class="mod-btn mod-btn-primary">
                    <i class="fas fa-user-plus"></i> Thêm người dùng
                </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Stats -->
        <div class="mod-stats mod-stagger">
            <div class="mod-stat accent-blue">
                <div class="mod-stat-icon" style="background:var(--gradient-primary);"><i class="fas fa-users"></i></div>
                <div class="mod-stat-info">
                    <span class="mod-stat-number"><?= $stats['total'] ?></span>
                    <span class="mod-stat-label">Tổng người dùng</span>
                </div>
            </div>
            <div class="mod-stat accent-pink">
                <div class="mod-stat-icon" style="background:var(--gradient-warning);"><i class="fas fa-crown"></i></div>
                <div class="mod-stat-info">
                    <span class="mod-stat-number"><?= $stats['admins'] ?></span>
                    <span class="mod-stat-label">Quản trị viên</span>
                </div>
            </div>
            <div class="mod-stat accent-green">
                <div class="mod-stat-icon" style="background:var(--gradient-success);"><i class="fas fa-user-tie"></i></div>
                <div class="mod-stat-info">
                    <span class="mod-stat-number"><?= $stats['staff'] ?></span>
                    <span class="mod-stat-label">Nhân viên</span>
                </div>
            </div>
            <div class="mod-stat accent-purple">
                <div class="mod-stat-icon" style="background:var(--gradient-info);"><i class="fas fa-user-graduate"></i></div>
                <div class="mod-stat-info">
                    <span class="mod-stat-number"><?= $stats['users'] ?></span>
                    <span class="mod-stat-label">Sinh viên</span>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <form method="get" class="mod-filters">
            <input type="hidden" name="search" value="<?= htmlspecialchars($keyword) ?>">

            <div class="mod-filter-group">
                <label><i class="fas fa-user-tag"></i> Vai trò</label>
                <select name="role" class="mod-select">
                    <option value="all" <?= $role_filter === 'all' ? 'selected' : '' ?>>Tất cả</option>
                    <option value="Admin" <?= $role_filter === 'Admin' ? 'selected' : '' ?>>Admin</option>
                    <option value="Manager" <?= $role_filter === 'Manager' ? 'selected' : '' ?>>Nhân viên</option>
                    <option value="Student" <?= $role_filter === 'Student' ? 'selected' : '' ?>>Sinh viên</option>
                </select>
            </div>

            <div class="mod-filter-group">
                <label><i class="fas fa-user"></i> Họ tên</label>
                <input type="text" name="name" class="mod-input" value="<?= htmlspecialchars($name_filter) ?>" placeholder="Nhập họ tên...">
            </div>

            <div class="mod-filter-group">
                <label><i class="fas fa-id-card"></i> MSSV</label>
                <input type="text" name="mssv" class="mod-input" value="<?= htmlspecialchars($mssv_filter) ?>" placeholder="VD: DH001">
            </div>

            <div class="mod-filter-group">
                <label><i class="fas fa-venus-mars"></i> Giới tính</label>
                <select name="gender" class="mod-select">
                    <option value="all" <?= $gender_filter === 'all' ? 'selected' : '' ?>>Tất cả</option>
                    <option value="Nam" <?= $gender_filter === 'Nam' ? 'selected' : '' ?>>Nam</option>
                    <option value="Nữ" <?= $gender_filter === 'Nữ' ? 'selected' : '' ?>>Nữ</option>
                </select>
            </div>

            <div class="mod-filter-group">
                <label><i class="fas fa-university"></i> Khoa</label>
                <select name="faculty" class="mod-select">
                    <option value="all" <?= $faculty_filter === 'all' ? 'selected' : '' ?>>Tất cả</option>
                    <?php
                    $facRes = $conn->query("SELECT FacultyID, FacultyName FROM Faculties ORDER BY FacultyName");
                    if ($facRes) {
                        while ($fac = $facRes->fetch_assoc()) {
                            $selected = ($faculty_filter == $fac['FacultyID']) ? 'selected' : '';
                            echo "<option value='{$fac['FacultyID']}' $selected>{$fac['FacultyName']}</option>";
                        }
                    }
                    ?>
                </select>
            </div>

            <div class="mod-filter-group">
                <label><i class="fas fa-bed"></i> Phòng ở</label>
                <select name="isdorm" class="mod-select">
                    <option value="all" <?= $isdorm_filter === 'all' ? 'selected' : '' ?>>Tất cả</option>
                    <option value="1" <?= $isdorm_filter === '1' ? 'selected' : '' ?>>Đã có phòng</option>
                    <option value="0" <?= $isdorm_filter === '0' ? 'selected' : '' ?>>Chưa có phòng</option>
                </select>
            </div>

            <div class="mod-filter-group">
                <label><i class="fas fa-sort"></i> Sắp xếp</label>
                <select name="sort" class="mod-select">
                    <option value="created_at_desc" <?= $sort_by === 'created_at_desc' ? 'selected' : '' ?>>Mới nhất</option>
                    <option value="created_at_asc" <?= $sort_by === 'created_at_asc' ? 'selected' : '' ?>>Cũ nhất</option>
                    <option value="name_asc" <?= $sort_by === 'name_asc' ? 'selected' : '' ?>>Tên A-Z</option>
                    <option value="name_desc" <?= $sort_by === 'name_desc' ? 'selected' : '' ?>>Tên Z-A</option>
                </select>
            </div>

            <div class="mod-filter-group" style="flex:0; min-width:auto;">
                <label>&nbsp;</label>
                <div style="display:flex; gap:8px;">
                    <button type="submit" class="mod-btn mod-btn-primary mod-btn-sm">
                        <i class="fas fa-filter"></i> Lọc
                    </button>
                    <a href="users.php" class="mod-btn mod-btn-outline mod-btn-sm">
                        <i class="fas fa-redo"></i> Reset
                    </a>
                </div>
            </div>
        </form>

        <!-- Table -->
        <div class="mod-table-wrap">
            <div class="mod-table-scroll">
                <table class="mod-table">
                    <thead>
                        <tr>
                            <th>Họ và tên</th>
                            <th>Email</th>
                            <th>MSSV</th>
                            <th>Giới tính</th>
                            <th>SĐT</th>
                            <th>Phòng ở</th>
                            <th>Trạng thái</th>
                            <th>Vai trò</th>
                            <th>Ngày tạo</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <?php
                                $avatarBg = match($row['Role']) {
                                    'Admin'   => 'var(--gradient-warning)',
                                    'Manager' => 'var(--gradient-success)',
                                    default   => 'var(--gradient-info)',
                                };
                                $hasRoom = !empty($row['RoomNumber']);
                                $roomDisplay = $hasRoom
                                    ? ($row['BuildingName'] . ' - ' . $row['RoomNumber'])
                                    : 'Chưa có';
                                $roleBadge = match($row['Role']) {
                                    'Admin'   => 'mod-badge-pink',
                                    'Manager' => 'mod-badge-green',
                                    default   => 'mod-badge-blue',
                                };
                                $roleIcon = match($row['Role']) {
                                    'Admin'   => 'fa-crown',
                                    'Manager' => 'fa-user-tie',
                                    default   => 'fa-user-graduate',
                                };
                                ?>
                                <tr>
                                    <td>
                                        <div class="mod-cell-user">
                                            <div class="mod-cell-avatar" style="background:<?= $avatarBg ?>;">
                                                <?= strtoupper(mb_substr($row['FullName'], 0, 1)) ?>
                                            </div>
                                            <div>
                                                <div class="mod-cell-name"><?= htmlspecialchars($row['FullName']) ?></div>
                                                <div class="mod-cell-sub"><i class="fas fa-id-card"></i> <?= $row['StudentCode'] ?: '—' ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="mod-cell-muted"><?= htmlspecialchars($row['Email']) ?></td>
                                    <td><span class="mod-cell-muted"><?= htmlspecialchars($row['StudentCode'] ?: '—') ?></span></td>
                                    <td><span class="mod-cell-muted"><?= htmlspecialchars($row['Gender'] ?: '—') ?></span></td>
                                    <td><span class="mod-cell-muted"><?= htmlspecialchars($row['Phone'] ?: '—') ?></span></td>
                                    <td>
                                        <?php if ($hasRoom): ?>
                                            <span class="mod-badge mod-badge-emerald"><i class="fas fa-door-open"></i> <?= htmlspecialchars($roomDisplay) ?></span>
                                        <?php else: ?>
                                            <span class="mod-badge mod-badge-gray"><i class="fas fa-door-closed"></i> Chưa có</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($hasRoom): ?>
                                            <span class="mod-badge mod-badge-emerald"><i class="fas fa-check"></i> Đã có phòng</span>
                                        <?php else: ?>
                                            <span class="mod-badge mod-badge-amber"><i class="fas fa-clock"></i> Chưa có phòng</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="mod-badge <?= $roleBadge ?>">
                                            <i class="fas <?= $roleIcon ?>"></i>
                                            <?= htmlspecialchars($row['Role']) ?>
                                        </span>
                                    </td>
                                    <td class="mod-cell-muted"><?= date('d/m/Y', strtotime($row['CreatedAt'])) ?></td>
                                    <td>
                                        <div class="mod-row-actions">
                                            <a href="user_view.php?id=<?= $row['UserID'] ?>" class="mod-btn-icon view" title="Xem">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if ($_SESSION['Role'] === 'Admin'): ?>
                                            <a href="user_edit.php?id=<?= $row['UserID'] ?>" class="mod-btn-icon edit" title="Sửa">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="#" class="mod-btn-icon delete" title="Xóa"
                                                data-id="<?= (int)$row['UserID'] ?>"
                                                onclick="return confirmDelete(event, '<?= htmlspecialchars($row['FullName']) ?>', this)">
                                                <i class="fas fa-trash-alt"></i>
                                            </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10">
                                    <div class="mod-empty">
                                        <i class="fas fa-users-slash"></i>
                                        <p>Không tìm thấy người dùng nào</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Results info -->
        <?php if ($result && $result->num_rows > 0): ?>
            <div class="mod-results-info">
                <i class="fas fa-info-circle"></i>
                Hiển thị <?= $result->num_rows ?> kết quả
            </div>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            <?php if (isset($_SESSION['message'])): ?>
                Swal.fire({
                    icon: '<?= $_SESSION['message_type'] ?? 'info' ?>',
                    title: 'Thông báo',
                    html: '<?= $_SESSION['message'] ?>',
                    confirmButtonText: 'Đóng',
                    confirmButtonColor: '#4361ee'
                });
                <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
            <?php endif; ?>
        });

        function confirmDelete(event, userName, element) {
            event.preventDefault();
            const userId = element.getAttribute('data-id');
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            Swal.fire({
                title: 'Xác nhận xóa',
                html: `Bạn có chắc chắn muốn xóa người dùng <b>"${userName}"</b>?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Xóa',
                cancelButtonText: 'Hủy',
                confirmButtonColor: '#f72585',
                cancelButtonColor: '#6c757d'
            }).then((result) => {
                if (result.isConfirmed) {
                    const row = element.closest('tr');
                    if (row) {
                        row.style.opacity = '0.6';
                        row.style.transition = 'all 0.3s ease';
                    }

                    fetch('user_delete_api.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: new URLSearchParams({
                            id: userId,
                            _csrf: csrfToken
                        })
                    })
                        .then(r => r.json())
                        .then(data => {
                            if (data.status === 'success') {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Đã xóa',
                                    text: data.message,
                                    timer: 1500,
                                    showConfirmButton: false
                                });
                                setTimeout(() => window.location.reload(), 700);
                            } else {
                                Swal.fire('Lỗi!', data.message || 'Không thể xóa người dùng', 'error');
                                if (row) row.style.opacity = '1';
                            }
                        })
                        .catch(() => {
                            Swal.fire('Lỗi!', 'Không thể kết nối đến máy chủ', 'error');
                            if (row) row.style.opacity = '1';
                        });
                }
            });

            return false;
        }

        // Auto submit text filters with debounce
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('.mod-filters');
            if (!form) return;
            let tid;
            form.querySelectorAll('input[type="text"]').forEach(input => {
                input.addEventListener('input', function() {
                    clearTimeout(tid);
                    tid = setTimeout(() => form.submit(), 800);
                });
            });
        });
    </script>
</body>
</html>
