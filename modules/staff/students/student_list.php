<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../../db_connect.php';
require_once '../../../includes/admin_header.php';
require_once '../../../includes/auth_check.php';
requireRole(['Admin', 'Manager']);

if (empty($_SESSION['_csrf'])) {
    $_SESSION['_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['_csrf'];

$conn->set_charset('utf8mb4');

/* ============== 1) THAM SỐ LỌC/PAGING ============== */
$keyword = trim($_GET['search'] ?? '');
$faculty = $_GET['faculty'] ?? 'all';
$inDorm  = trim($_GET['in_dorm'] ?? 'all');
$gender  = trim($_GET['gender'] ?? 'all');
$perPage = (int)($_GET['pp'] ?? 10);
if (!in_array($perPage, [10, 20, 30, 50], true)) $perPage = 10;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

/* ============== 2) THỐNG KÊ LIVE ============== */
$statsSql = "
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN EXISTS(SELECT 1 FROM Contracts c WHERE c.StudentID = s.StudentID AND c.Status = 'Hiệu lực') THEN 1 ELSE 0 END) AS in_dorm,
        SUM(CASE WHEN NOT EXISTS(SELECT 1 FROM Contracts c WHERE c.StudentID = s.StudentID AND c.Status = 'Hiệu lực') THEN 1 ELSE 0 END) AS out_dorm,
        SUM(CASE WHEN s.Gender = 'Nam' THEN 1 ELSE 0 END) AS male,
        SUM(CASE WHEN s.Gender = 'Nữ' THEN 1 ELSE 0 END) AS female
    FROM Students s
";
$statsRes = $conn->query($statsSql);
$stats = $statsRes ? $statsRes->fetch_assoc() : ['total' => 0, 'in_dorm' => 0, 'out_dorm' => 0, 'male' => 0, 'female' => 0];

/* ============== 3) DANH SÁCH KHOA ============== */
$faculties = [];
$facRes = $conn->query("SELECT FacultyID, FacultyName FROM Faculties ORDER BY FacultyName");
if ($facRes) {
    while ($r = $facRes->fetch_assoc()) {
        $faculties[] = $r;
    }
}

/* ============== 4) WHERE ============== */
$where = " WHERE 1=1 ";

if ($keyword !== '') {
    $kw = $conn->real_escape_string($keyword);
    $where .= " AND (s.FullName LIKE '%$kw%' OR s.StudentCode LIKE '%$kw%' OR s.Phone LIKE '%$kw%') ";
}

if ($faculty !== 'all') {
    $fid = (int)$faculty;
    if ($fid > 0) {
        $where .= " AND s.FacultyID = $fid ";
    }
}

if ($gender === 'Nam' || $gender === 'Nữ') {
    $gd = $conn->real_escape_string($gender);
    $where .= " AND s.Gender = '$gd' ";
}

if ($inDorm === '1') {
    $where .= " AND EXISTS (SELECT 1 FROM Contracts c WHERE c.StudentID = s.StudentID AND c.Status = 'Hiệu lực') ";
} elseif ($inDorm === '0') {
    $where .= " AND NOT EXISTS (SELECT 1 FROM Contracts c WHERE c.StudentID = s.StudentID AND c.Status = 'Hiệu lực') ";
}

/* ============== 5) ĐẾM + LẤY DỮ LIỆU ============== */
$countSql = "SELECT COUNT(*) AS cnt FROM Students s $where";
$countRes = $conn->query($countSql);
$totalRows = $countRes ? (int)$countRes->fetch_assoc()['cnt'] : 0;
$totalPages = max(1, (int)ceil($totalRows / $perPage));

$sql = "
SELECT 
    s.StudentID, s.UserID, s.StudentCode, s.FullName, s.Gender,
    s.FacultyID, f.FacultyName,
    s.ClassName, s.CourseYear, s.Phone, s.Email,
    s.IsInDorm, s.CreatedAt, s.Avatar,
    CASE WHEN EXISTS(
        SELECT 1 FROM Contracts c0 WHERE c0.StudentID = s.StudentID AND c0.Status = 'Hiệu lực'
    ) THEN 1 ELSE 0 END AS InDormLive,
    c.ContractID, r.RoomNumber, b.BuildingName
FROM Students s
LEFT JOIN Faculties f ON f.FacultyID = s.FacultyID
LEFT JOIN Contracts c ON c.ContractID = (
    SELECT c2.ContractID FROM Contracts c2
    WHERE c2.StudentID = s.StudentID AND c2.Status = 'Hiệu lực'
    ORDER BY c2.StartDate DESC, c2.ContractID DESC LIMIT 1
)
LEFT JOIN Rooms r ON r.RoomID = c.RoomID
LEFT JOIN Buildings b ON b.BuildingID = r.BuildingID
$where
ORDER BY s.CreatedAt DESC
LIMIT $perPage OFFSET $offset
";
$listRes = $conn->query($sql);

function avatarUrl(?string $path, ?string $gender): string
{
    $fallback = '/assets/img/avatars/user.png';
    $rel = trim((string)$path);
    if ($rel === '') return $fallback;
    if ($rel[0] !== '/') $rel = '/' . $rel;
    return htmlspecialchars($rel, ENT_QUOTES);
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
    <title>Quản lý Sinh viên | Hệ thống Ký túc xá</title>
    <link rel="stylesheet" href="<?= $base ?>assets/css/global.css">
    <link rel="stylesheet" href="<?= $base ?>assets/css/modules_shared.css">
    <link rel="stylesheet" href="<?= $base ?>assets/css/admin/admin_header.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <div class="mod-container">
        <!-- Header -->
        <div class="mod-header">
            <div class="mod-header-left">
                <h2><i class="fas fa-people-roof"></i> Quản lý Sinh viên</h2>
            </div>
            <div class="mod-header-right">
                <?php if ($_SESSION['Role'] === 'Admin'): ?>
                <a href="student_create.php" class="mod-btn mod-btn-primary">
                    <i class="fas fa-user-graduate"></i> Thêm SV
                </a>
                <a href="student_import.php" class="mod-btn mod-btn-success">
                    <i class="fas fa-file-import"></i> Nhập Excel
                </a>
                <?php endif; ?>
                <a href="student_export.php?<?= http_build_query($_GET) ?>" class="mod-btn mod-btn-outline">
                    <i class="fas fa-file-export"></i> Xuất DS
                </a>
            </div>
        </div>

        <!-- Stats -->
        <div class="mod-stats mod-stagger">
            <div class="mod-stat accent-blue">
                <div class="mod-stat-icon" style="background:var(--gradient-primary);"><i class="fas fa-users"></i></div>
                <div class="mod-stat-info">
                    <span class="mod-stat-number"><?= (int)$stats['total'] ?></span>
                    <span class="mod-stat-label">Tổng sinh viên</span>
                </div>
            </div>
            <div class="mod-stat accent-green">
                <div class="mod-stat-icon" style="background:var(--gradient-success);"><i class="fas fa-check-circle"></i></div>
                <div class="mod-stat-info">
                    <span class="mod-stat-number"><?= (int)$stats['in_dorm'] ?></span>
                    <span class="mod-stat-label">Đã có phòng</span>
                </div>
            </div>
            <div class="mod-stat accent-pink">
                <div class="mod-stat-icon" style="background:var(--gradient-warning);"><i class="fas fa-clock"></i></div>
                <div class="mod-stat-info">
                    <span class="mod-stat-number"><?= (int)$stats['out_dorm'] ?></span>
                    <span class="mod-stat-label">Chưa có phòng</span>
                </div>
            </div>
            <div class="mod-stat accent-purple">
                <div class="mod-stat-icon" style="background:var(--gradient-info);"><i class="fas fa-mars"></i></div>
                <div class="mod-stat-info">
                    <span class="mod-stat-number"><?= (int)$stats['male'] ?> / <?= (int)$stats['female'] ?></span>
                    <span class="mod-stat-label">Nam / Nữ</span>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <form class="mod-filters" method="get" id="filterForm">
            <div class="mod-filter-group">
                <label><i class="fas fa-search"></i> Tìm kiếm</label>
                <input type="text" name="search" class="mod-input" placeholder="Tên, MSSV, SĐT..." value="<?= htmlspecialchars($keyword) ?>">
            </div>
            <div class="mod-filter-group">
                <label><i class="fas fa-university"></i> Khoa</label>
                <select name="faculty" class="mod-select" onchange="this.form.submit()">
                    <option value="all" <?= $faculty === 'all' ? 'selected' : '' ?>>Tất cả</option>
                    <?php foreach ($faculties as $f):
                        $fid = (int)$f['FacultyID'];
                        $sel = ($faculty !== 'all' && (int)$faculty === $fid) ? 'selected' : '';
                    ?>
                        <option value="<?= $fid ?>" <?= $sel ?>><?= htmlspecialchars($f['FacultyName']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mod-filter-group">
                <label><i class="fas fa-bed"></i> Phòng KTX</label>
                <select name="in_dorm" class="mod-select" onchange="this.form.submit()">
                    <option value="all" <?= $inDorm === 'all' ? 'selected' : '' ?>>Tất cả</option>
                    <option value="1" <?= $inDorm === '1' ? 'selected' : '' ?>>Đã có phòng</option>
                    <option value="0" <?= $inDorm === '0' ? 'selected' : '' ?>>Chưa có phòng</option>
                </select>
            </div>
            <div class="mod-filter-group">
                <label><i class="fas fa-venus-mars"></i> Giới tính</label>
                <select name="gender" class="mod-select" onchange="this.form.submit()">
                    <option value="all" <?= $gender === 'all' ? 'selected' : '' ?>>Tất cả</option>
                    <option value="Nam" <?= $gender === 'Nam' ? 'selected' : '' ?>>Nam</option>
                    <option value="Nữ" <?= $gender === 'Nữ' ? 'selected' : '' ?>>Nữ</option>
                </select>
            </div>
            <div class="mod-filter-group">
                <label><i class="fas fa-list-ol"></i> Hiển thị</label>
                <select name="pp" class="mod-select" onchange="this.form.submit()">
                    <?php foreach ([10, 20, 30, 50] as $pp): ?>
                        <option value="<?= $pp ?>" <?= $perPage == $pp ? 'selected' : '' ?>><?= $pp ?>/trang</option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>

        <!-- Table -->
        <div class="mod-table-wrap">
            <div class="mod-table-scroll">
                <table class="mod-table">
                    <thead>
                        <tr>
                            <th>Sinh viên</th>
                            <th>MSSV</th>
                            <th>Khoa / Lớp</th>
                            <th>Khóa</th>
                            <th>Phòng</th>
                            <th>Trạng thái</th>
                            <th>Liên hệ</th>
                            <th>Ngày tạo</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($listRes && $listRes->num_rows > 0): ?>
                            <?php while ($row = $listRes->fetch_assoc()): ?>
                                <?php
                                $isLive = (bool)$row['InDormLive'];
                                $hasRoom = !empty($row['RoomNumber']);
                                $avatarBg = $row['Gender'] === 'Nữ' ? 'var(--gradient-warning)' : 'var(--gradient-primary)';
                                ?>
                                <tr>
                                    <td>
                                        <div class="mod-cell-user">
                                            <img class="mod-cell-avatar-img" src="<?= avatarUrl($row['Avatar'] ?? '', $row['Gender'] ?? null) ?>" alt="avatar" loading="lazy" style="width:36px;height:36px;border-radius:50%;object-fit:cover;">
                                            <div>
                                                <div class="mod-cell-name"><?= htmlspecialchars($row['FullName']) ?></div>
                                                <div class="mod-cell-sub">
                                                    <?php if ($row['Gender']): ?>
                                                        <span class="mod-badge <?= $row['Gender'] === 'Nam' ? 'mod-badge-blue' : 'mod-badge-pink' ?>" style="font-size:0.7rem;padding:2px 6px;">
                                                            <?= htmlspecialchars($row['Gender']) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="mod-fw-700"><?= htmlspecialchars($row['StudentCode']) ?></span></td>
                                    <td>
                                        <div class="mod-cell-name"><?= htmlspecialchars($row['FacultyName'] ?? '-') ?></div>
                                        <div class="mod-cell-sub"><?= htmlspecialchars($row['ClassName'] ?: '-') ?></div>
                                    </td>
                                    <td class="mod-cell-muted"><?= $row['CourseYear'] ? (int)$row['CourseYear'] : '-' ?></td>
                                    <td>
                                        <?php if ($hasRoom): ?>
                                            <span class="mod-badge mod-badge-emerald">
                                                <i class="fas fa-door-open"></i>
                                                <?= htmlspecialchars($row['BuildingName']) ?> <?= htmlspecialchars($row['RoomNumber']) ?>
                                            </span>
                                            <?php if ($row['ContractID']): ?>
                                                <div class="mod-cell-sub">HĐ #<?= (int)$row['ContractID'] ?></div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="mod-badge mod-badge-gray"><i class="fas fa-door-closed"></i> Chưa xếp</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($isLive): ?>
                                            <span class="mod-badge mod-badge-emerald"><i class="fas fa-check"></i> Đang ở</span>
                                        <?php else: ?>
                                            <span class="mod-badge mod-badge-amber"><i class="fas fa-clock"></i> Chưa ở</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="mod-cell-name"><?= htmlspecialchars($row['Phone'] ?: '-') ?></div>
                                        <div class="mod-cell-sub"><?= htmlspecialchars($row['Email'] ?: '-') ?></div>
                                    </td>
                                    <td class="mod-cell-muted">
                                        <?= $row['CreatedAt'] ? date('d/m/Y', strtotime($row['CreatedAt'])) : '-' ?>
                                    </td>
                                    <td>
                                        <div class="mod-row-actions">
                                            <a href="student_detail.php?id=<?= (int)$row['StudentID'] ?>" class="mod-btn-icon view" title="Xem">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if ($_SESSION['Role'] === 'Admin'): ?>
                                            <a href="student_edit.php?id=<?= (int)$row['StudentID'] ?>" class="mod-btn-icon edit" title="Sửa">
                                                <i class="fas fa-pen"></i>
                                            </a>
                                            <button class="mod-btn-icon delete" title="Xóa"
                                                onclick="deleteStudent(<?= (int)$row['StudentID'] ?>,'<?= htmlspecialchars($row['FullName'], ENT_QUOTES) ?>', this)">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9">
                                    <div class="mod-empty">
                                        <i class="fas fa-inbox"></i>
                                        <p>Không có sinh viên nào phù hợp với tiêu chí.</p>
                                        <a class="mod-btn mod-btn-outline mod-btn-sm" href="?">
                                            <i class="fas fa-redo"></i> Xem tất cả
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="mod-pagination">
                <?php
                $qs = $_GET;
                unset($qs['page']);
                $baseUrl = '?' . http_build_query($qs);
                $prev = max(1, $page - 1);
                $next = min($totalPages, $page + 1);
                ?>
                <a class="mod-pg <?= $page == 1 ? 'disabled' : '' ?>" href="<?= $baseUrl . '&page=1' ?>">&laquo;</a>
                <a class="mod-pg <?= $page == 1 ? 'disabled' : '' ?>" href="<?= $baseUrl . '&page=' . $prev ?>">&lsaquo;</a>
                <span class="mod-pg-info">Trang <?= $page ?>/<?= $totalPages ?></span>
                <a class="mod-pg <?= $page == $totalPages ? 'disabled' : '' ?>" href="<?= $baseUrl . '&page=' . $next ?>">&rsaquo;</a>
                <a class="mod-pg <?= $page == $totalPages ? 'disabled' : '' ?>" href="<?= $baseUrl . '&page=' . $totalPages ?>">&raquo;</a>
            </div>
        <?php endif; ?>

        <!-- Results info -->
        <div class="mod-results-info">
            <i class="fas fa-info-circle"></i>
            Hiển thị <?= $listRes ? $listRes->num_rows : 0 ?> / <?= $totalRows ?> sinh viên
        </div>
    </div>

    <script>
        const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        // Check for import message
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('msg') && urlParams.get('msg') === 'Imported') {
            const success = urlParams.get('success');
            const fail = urlParams.get('fail');
            Swal.fire({
                title: 'Kết quả nhập dữ liệu',
                html: `<div style="text-align:left;">
                    <p style="color:#10b981;"><i class="fas fa-check-circle"></i> Thành công: <b>${success}</b> sinh viên</p>
                    <p style="color:#ef4444;"><i class="fas fa-times-circle"></i> Thất bại: <b>${fail}</b> sinh viên</p>
                </div>`,
                icon: fail > 0 ? 'warning' : 'success',
                confirmButtonText: 'Đóng',
                confirmButtonColor: '#4361ee'
            }).then(() => {
                window.history.replaceState({}, document.title, window.location.pathname);
            });
        }

        function deleteStudent(id, name, btn) {
            Swal.fire({
                title: 'Xác nhận xóa',
                html: `Bạn có chắc muốn xóa hồ sơ <b>${name}</b>?<br><small style="color:var(--text-secondary);">Hành động này không thể hoàn tác.</small>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#f72585',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-trash-alt"></i> Xóa',
                cancelButtonText: 'Hủy',
                reverseButtons: true
            }).then((res) => {
                if (!res.isConfirmed) return;

                if (btn) {
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                    btn.classList.add('loading');
                }

                fetch('student_delete_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: new URLSearchParams({ id, _csrf: CSRF_TOKEN })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success') {
                        Swal.fire({ icon: 'success', title: 'Đã xóa', text: data.message, timer: 1500, showConfirmButton: false });
                        const row = btn ? btn.closest('tr') : null;
                        if (row) {
                            row.style.transition = 'all 0.5s ease';
                            row.style.opacity = '0';
                            row.style.transform = 'translateX(-100%)';
                            setTimeout(() => {
                                row.remove();
                                if (document.querySelectorAll('.mod-table tbody tr').length === 0) location.reload();
                            }, 500);
                        } else {
                            setTimeout(() => location.reload(), 700);
                        }
                    } else {
                        Swal.fire({ icon: 'error', title: 'Lỗi', text: data.message || 'Không thể xóa' });
                        if (btn) { btn.innerHTML = '<i class="fas fa-trash-alt"></i>'; btn.classList.remove('loading'); }
                    }
                })
                .catch(() => {
                    Swal.fire({ icon: 'error', title: 'Lỗi', text: 'Không thể kết nối máy chủ' });
                    if (btn) { btn.innerHTML = '<i class="fas fa-trash-alt"></i>'; btn.classList.remove('loading'); }
                });
            });
        }

        // Debounce search input
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('filterForm');
            if (!form) return;
            let tid;
            const searchInput = form.querySelector('input[name="search"]');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    clearTimeout(tid);
                    tid = setTimeout(() => form.submit(), 500);
                });
            }
        });
    </script>
</body>
</html>
