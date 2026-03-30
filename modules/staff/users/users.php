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

/* ===================== XÓA NGƯỜI DÙNG ===================== */
if (isset($_GET['delete'])) {
    $userId = (int)$_GET['delete'];

    // Lấy thông tin sinh viên (nếu có)
    $studentRes = $conn->query("SELECT StudentID, FullName FROM Students WHERE UserID = $userId");
    if (!$studentRes || $studentRes->num_rows == 0) {
        // Không phải sinh viên -> xóa thẳng user
        $conn->query("DELETE FROM Users WHERE UserID = $userId");
        $_SESSION['message'] = '✅ Đã xóa người dùng không liên kết với sinh viên!';
        $_SESSION['message_type'] = 'success';
        header('Location: users.php');
        exit;
    }

    $student = $studentRes->fetch_assoc();
    $studentId = (int)$student['StudentID'];
    $studentName = htmlspecialchars($student['FullName']);

    // Kiểm tra hợp đồng đang hiệu lực
    $checkContract = $conn->query("
        SELECT COUNT(*) AS cnt 
        FROM Contracts 
        WHERE StudentID = $studentId AND Status = 'Hiệu lực'
    ");
    $hasActiveContract = ($checkContract && $checkContract->fetch_assoc()['cnt'] > 0);

    if ($hasActiveContract) {
        $_SESSION['message'] = "⚠️ Không thể xóa sinh viên <b>{$studentName}</b> vì đang có hợp đồng hiệu lực!";
        $_SESSION['message_type'] = 'warning';
        header('Location: users.php');
        exit;
    }

    // Không còn hợp đồng → xóa dữ liệu liên quan
    $conn->query("DELETE FROM Feedbacks WHERE StudentID = $studentId");
    $conn->query("DELETE FROM Payments WHERE StudentID = $studentId");
    $conn->query("
        DELETE FROM Invoices 
        WHERE ContractID IN (
            SELECT ContractID FROM Contracts WHERE StudentID = $studentId
        )
    ");
    $conn->query("DELETE FROM Contracts WHERE StudentID = $studentId");
    $conn->query("DELETE FROM Students WHERE StudentID = $studentId");
    $conn->query("DELETE FROM Users WHERE UserID = $userId");

    $_SESSION['message'] = "✅ Đã xóa người dùng <b>{$studentName}</b> và toàn bộ dữ liệu liên quan!";
    $_SESSION['message_type'] = 'success';
    header('Location: users.php');
    exit;
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
/*
   Lưu ý:
   - LEFT JOIN Contracts c với điều kiện Status = 'Hiệu lực'
   - Nếu có c.ContractID + RoomNumber => xem như "ĐÃ CÓ PHÒNG"
*/

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

/* Từ khóa chung (header search nếu có) */
if (!empty($keyword)) {
    $kw = $conn->real_escape_string($keyword);
    $sql .= " AND (u.FullName LIKE '%$kw%' 
              OR u.Email LIKE '%$kw%' 
              OR u.Role LIKE '%$kw%' 
              OR s.StudentCode LIKE '%$kw%')";
}

/* Lọc vai trò */
if (!empty($role_filter) && $role_filter !== 'all') {
    $role = $conn->real_escape_string($role_filter);
    $sql .= " AND u.Role = '$role'";
}

/* Lọc theo tên */
if (!empty($name_filter)) {
    $name = $conn->real_escape_string($name_filter);
    $sql .= " AND u.FullName LIKE '%$name%'";
}

/* Lọc theo MSSV */
if (!empty($mssv_filter)) {
    $mssv = $conn->real_escape_string($mssv_filter);
    $sql .= " AND s.StudentCode LIKE '%$mssv%'";
}

/* Lọc theo giới tính */
if ($gender_filter !== 'all' && $gender_filter !== '') {
    $gender = $conn->real_escape_string($gender_filter);
    $sql .= " AND s.Gender = '$gender'";
}

/* Lọc theo khoa */
if (!empty($faculty_filter) && $faculty_filter !== 'all') {
    $faculty_id = (int)$faculty_filter;
    $sql .= " AND s.FacultyID = $faculty_id";
}


/* Lọc trạng thái phòng ở (ĐÃ CÓ PHÒNG / CHƯA CÓ PHÒNG)
   Dựa vào hợp đồng hiệu lực (c.ContractID) thay vì IsInDorm
*/
if ($isdorm_filter === '1') {
    // Đã có phòng
    $sql .= " AND c.ContractID IS NOT NULL";
} elseif ($isdorm_filter === '0') {
    // Chưa có phòng
    $sql .= " AND c.ContractID IS NULL";
}

/* Sắp xếp */
$sort_clause = " ORDER BY ";
switch ($sort_by) {
    case 'name_asc':
        $sort_clause .= "u.FullName ASC";
        break;
    case 'name_desc':
        $sort_clause .= "u.FullName DESC";
        break;
    case 'email_asc':
        $sort_clause .= "u.Email ASC";
        break;
    case 'email_desc':
        $sort_clause .= "u.Email DESC";
        break;
    case 'created_at_asc':
        $sort_clause .= "u.CreatedAt ASC";
        break;
    default:
        $sort_clause .= "u.CreatedAt DESC";
        break;
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

/* ===================== LẤY DỮ LIỆU ===================== */
$result = $conn->query($sql);

?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý người dùng - Hệ Thống Ký Túc Xá</title>
    <link rel="stylesheet" href="../../assets/css/admin/admin_header.css">
    <link rel="stylesheet" href="../../../assets/css/admin/admin_users.css">
    <link rel="stylesheet" href="../../../assets/vendor/fontawesome/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
</head>

<body>
    <div class="user-container">
        <!-- Page Header -->
        <div class="page-header">
            <h2><i class="fas fa-users-cog"></i> Quản lý người dùng</h2>
            <?php if ($_SESSION['Role'] === 'Admin'): ?>
            <a href="/modules/staff/users/user_create.php" class="btn-add">
                <i class="fas fa-user-plus"></i> Thêm người dùng
            </a>
            <?php endif; ?>
        </div>

        <!-- Statistics Overview -->
        <div class="stats-overview">
            <div class="stat-card">
                <span class="number"><?= $stats['total'] ?></span>
                <span class="label">Tổng người dùng</span>
            </div>
            <div class="stat-card">
                <span class="number"><?= $stats['admins'] ?></span>
                <span class="label">Quản trị viên</span>
            </div>
            <div class="stat-card">
                <span class="number"><?= $stats['staff'] ?></span>
                <span class="label">Nhân viên</span>
            </div>
            <div class="stat-card">
                <span class="number"><?= $stats['users'] ?></span>
                <span class="label">Sinh viên</span>
            </div>
        </div>

        <!-- 🔍 Bộ lọc nâng cao -->
        <form method="get" class="filters-form">
            <div class="filters">

                <!-- Giữ từ khóa tìm kiếm hiện tại nếu có -->
                <input type="hidden" name="search" value="<?= htmlspecialchars($keyword) ?>">

                <!-- Vai trò -->
                <div class="filter-group">
                    <label for="role-filter"><i class="fas fa-user-tag"></i> Vai trò:</label>
                    <select id="role-filter" name="role">
                        <option value="all" <?= $role_filter === 'all' ? 'selected' : '' ?>>Tất cả vai trò</option>
                        <option value="Admin" <?= $role_filter === 'Admin' ? 'selected' : '' ?>>Quản trị viên</option>
                        <option value="Manager" <?= $role_filter === 'Manager' ? 'selected' : '' ?>>Nhân viên</option>
                        <option value="Student" <?= $role_filter === 'Student' ? 'selected' : '' ?>>Sinh viên</option>
                    </select>
                </div>

                <!-- Họ tên -->
                <div class="filter-group">
                    <label for="name-filter"><i class="fas fa-user"></i> Họ tên:</label>
                    <input type="text" id="name-filter" name="name"
                        value="<?= htmlspecialchars($name_filter) ?>"
                        placeholder="Nhập họ tên...">
                </div>

                <!-- MSSV -->
                <div class="filter-group">
                    <label for="mssv-filter"><i class="fas fa-id-card"></i> MSSV:</label>
                    <input type="text" id="mssv-filter" name="mssv"
                        value="<?= htmlspecialchars($mssv_filter) ?>"
                        placeholder="VD: DH001">
                </div>

                <!-- Giới tính -->
                <div class="filter-group">
                    <label for="gender-filter"><i class="fas fa-venus-mars"></i> Giới tính:</label>
                    <select id="gender-filter" name="gender">
                        <option value="all" <?= $gender_filter === 'all' ? 'selected' : '' ?>>Tất cả</option>
                        <option value="Nam" <?= $gender_filter === 'Nam' ? 'selected' : '' ?>>Nam</option>
                        <option value="Nữ" <?= $gender_filter === 'Nữ' ? 'selected' : '' ?>>Nữ</option>
                    </select>
                </div>

                <!-- Khoa -->
                <div class="filter-group">
                    <label for="faculty-filter"><i class="fas fa-university"></i> Khoa:</label>
                    <select id="faculty-filter" name="faculty">
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


                <!-- Trạng thái phòng -->
                <div class="filter-group">
                    <label for="isdorm-filter"><i class="fas fa-bed"></i> Trạng thái:</label>
                    <select id="isdorm-filter" name="isdorm">
                        <option value="all" <?= $isdorm_filter === 'all' ? 'selected' : '' ?>>Tất cả</option>
                        <option value="1" <?= $isdorm_filter === '1' ? 'selected' : '' ?>>Đã có phòng</option>
                        <option value="0" <?= $isdorm_filter === '0' ? 'selected' : '' ?>>Chưa có phòng</option>
                    </select>
                </div>

                <!-- Sắp xếp -->
                <div class="filter-group">
                    <label for="sort"><i class="fas fa-sort-amount-down"></i> Sắp xếp:</label>
                    <select id="sort" name="sort">
                        <option value="created_at_desc" <?= $sort_by === 'created_at_desc' ? 'selected' : '' ?>>Mới nhất</option>
                        <option value="created_at_asc" <?= $sort_by === 'created_at_asc' ? 'selected' : '' ?>>Cũ nhất</option>
                        <option value="name_asc" <?= $sort_by === 'name_asc' ? 'selected' : '' ?>>Tên A-Z</option>
                        <option value="name_desc" <?= $sort_by === 'name_desc' ? 'selected' : '' ?>>Tên Z-A</option>
                    </select>
                </div>

                <!-- Nút -->
                <div class="filter-actions">
                    <button type="submit" class="btn-filter-apply">
                        <i class="fas fa-filter"></i> Tìm
                    </button>
                    <a href="users.php" class="btn-filter-reset">
                        <i class="fas fa-redo"></i> Đặt lại
                    </a>
                </div>
            </div>
        </form>

        <!-- Bảng người dùng -->
        <div class="user-table-container">
            <table class="user-table">
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
                            // Màu avatar theo role
                            $avatarColor = 'var(--gradient-info)';
                            if ($row['Role'] === 'Admin') {
                                $avatarColor = 'var(--gradient-warning)';
                            } elseif ($row['Role'] === 'Manager') {
                                $avatarColor = 'var(--gradient-success)';
                            }

                            // Xác định có phòng hay chưa dựa vào RoomNumber (hợp đồng hiệu lực)
                            $hasRoom = !empty($row['RoomNumber']);

                            $roomDisplay = $hasRoom
                                ? ($row['BuildingName'] . ' - ' . $row['RoomNumber'])
                                : 'Chưa có';

                            $statusText  = $hasRoom ? 'Đã có phòng' : 'Chưa có phòng';
                            $statusClass = $hasRoom ? 'status-active' : 'status-inactive';
                            ?>
                            <tr>
                                <td>
                                    <div class="user-info">
                                        <div class="user-avatar" style="background: <?= $avatarColor ?>">
                                            <?= strtoupper(substr($row['FullName'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div style="font-weight: 600;">
                                                <?= htmlspecialchars($row['FullName']) ?>
                                            </div>
                                            <div class="small-text">
                                                <i class="fas fa-id-card"></i>
                                                <?= $row['StudentCode'] ?: '—' ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($row['Email']) ?></td>
                                <td><?= htmlspecialchars($row['StudentCode'] ?: '—') ?></td>
                                <td><?= htmlspecialchars($row['Gender'] ?: '—') ?></td>
                                
                                <td><?= htmlspecialchars($row['Phone'] ?: '—') ?></td>
                                <td><?= htmlspecialchars($roomDisplay) ?></td>
                                <td>
                                    <span class="status-badge <?= $statusClass ?>">
                                        <?= $statusText ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="role <?= strtolower($row['Role']) ?>">
                                        <i class="fas fa-<?= $row['Role'] === 'Admin'
                                                                ? 'crown'
                                                                : ($row['Role'] === 'Admin' ? 'user-tie' : 'user') ?>"></i>
                                        <?= htmlspecialchars($row['Role']) ?>
                                    </span>
                                </td>
                                <td><?= date('d/m/Y', strtotime($row['CreatedAt'])) ?></td>
                                <td>
                                    <div class="actions">
                                        <a href="../../staff/users/user_view.php?id=<?= $row['UserID'] ?>"
                                            class="btn-view">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($_SESSION['Role'] === 'Admin'): ?>
                                        <a href="../../staff/users/user_edit.php?id=<?= $row['UserID'] ?>"
                                            class="btn-edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="#"
                                            class="btn-delete"
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
                            <td colspan="11" class="no-data">
                                <i class="fas fa-users-slash"></i>
                                Không tìm thấy người dùng nào
                                <?php if (
                                    !empty($keyword)
                                    || $role_filter !== 'all'
                                    || !empty($name_filter)
                                    || !empty($mssv_filter)
                                    || $gender_filter !== 'all'
                                    || $faculty_filter !== 'all'
                                    || $isdorm_filter !== 'all'
                                ): ?>
                                    <br><small>Thử thay đổi bộ lọc hoặc từ khóa tìm kiếm</small>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Thông tin kết quả -->
        <?php if ($result && $result->num_rows > 0): ?>
            <div class="results-info">
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
                    confirmButtonColor: '#3085d6',
                    background: '#fff',
                    backdrop: 'rgba(0,0,0,0.1)'
                });
                <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
            <?php endif; ?>

            // Hover row effect
            document.querySelectorAll('.user-table tbody tr').forEach(row => {
                row.addEventListener('mouseenter', () => {
                    row.style.transform = 'scale(1.005)';
                });
                row.addEventListener('mouseleave', () => {
                    row.style.transform = 'scale(1)';
                });
            });
        });

        // SweetAlert confirm delete
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
                cancelButtonColor: '#6c757d',
                background: '#fff',
                backdrop: 'rgba(0,0,0,0.1)'
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
                                if (row) {
                                    row.style.opacity = '1';
                                }
                            }
                        })
                        .catch(() => {
                            Swal.fire('Lỗi!', 'Không thể kết nối đến máy chủ', 'error');
                            if (row) {
                                row.style.opacity = '1';
                            }
                        });
                }
            });

            return false;
        }

        // Auto submit text filters với debounce
        document.addEventListener('DOMContentLoaded', function() {
            const filtersForm = document.querySelector('.filters-form');
            if (!filtersForm) return;

            const textInputs = filtersForm.querySelectorAll('input[type="text"]');
            let timeoutId;

            textInputs.forEach(input => {
                input.addEventListener('input', function() {
                    clearTimeout(timeoutId);
                    timeoutId = setTimeout(() => {
                        filtersForm.submit();
                    }, 800);
                });
            });
        });
    </script>
</body>

</html>
