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

/* ============== 0) ĐỒNG BỘ IsInDorm (OPTIONAL) ============== */
$SYNC_IS_IN_DORM = false;
if ($SYNC_IS_IN_DORM) {
    $conn->query("
        UPDATE Students s
        SET s.IsInDorm = 1
        WHERE EXISTS (
            SELECT 1 FROM Contracts c
            WHERE c.StudentID = s.StudentID AND c.Status = 'Hiệu lực'
        )
    ");
    $conn->query("
        UPDATE Students s
        SET s.IsInDorm = 0
        WHERE NOT EXISTS (
            SELECT 1 FROM Contracts c
            WHERE c.StudentID = s.StudentID AND c.Status = 'Hiệu lực'
        )
    ");
}

/* ============== 1) THAM SỐ LỌC/PAGING ============== */
$keyword = trim($_GET['search'] ?? '');
$faculty = $_GET['faculty'] ?? 'all';          // bây giờ: FacultyID hoặc 'all'
$inDorm  = trim($_GET['in_dorm'] ?? 'all');    // all|1|0
$gender  = trim($_GET['gender'] ?? 'all');     // all|Nam|Nữ
$perPage = (int)($_GET['pp'] ?? 10);
if (!in_array($perPage, [10, 20, 30, 50], true)) $perPage = 10;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

/* ============== 2) THỐNG KÊ LIVE ============== */
$statsSql = "
    SELECT
        COUNT(*) AS total,
        SUM(
            CASE WHEN EXISTS(
                SELECT 1 FROM Contracts c
                WHERE c.StudentID = s.StudentID AND c.Status = 'Hiệu lực'
            ) THEN 1 ELSE 0 END
        ) AS in_dorm,
        SUM(
            CASE WHEN NOT EXISTS(
                SELECT 1 FROM Contracts c
                WHERE c.StudentID = s.StudentID AND c.Status = 'Hiệu lực'
            ) THEN 1 ELSE 0 END
        ) AS out_dorm,
        SUM(CASE WHEN s.Gender = 'Nam' THEN 1 ELSE 0 END) AS male,
        SUM(CASE WHEN s.Gender = 'Nữ' THEN 1 ELSE 0 END) AS female
    FROM Students s
";
$statsRes = $conn->query($statsSql);
$stats = $statsRes
    ? $statsRes->fetch_assoc()
    : ['total' => 0, 'in_dorm' => 0, 'out_dorm' => 0, 'male' => 0, 'female' => 0];

/* ============== 3) DANH SÁCH KHOA TỪ Faculties ============== */
$faculties = [];
$facRes = $conn->query("SELECT FacultyID, FacultyName FROM Faculties ORDER BY FacultyName");
if ($facRes) {
    while ($r = $facRes->fetch_assoc()) {
        $faculties[] = $r;
    }
}

/* ============== 4) WHERE CHUNG ============== */
$where = " WHERE 1=1 ";

if ($keyword !== '') {
    $kw = $conn->real_escape_string($keyword);
    $where .= " AND (s.FullName LIKE '%$kw%' 
                OR s.StudentCode LIKE '%$kw%' 
                OR s.Phone LIKE '%$kw%') ";
}

/* Lọc Khoa theo FacultyID */
if ($faculty !== 'all') {
    $fid = (int)$faculty;
    if ($fid > 0) {
        $where .= " AND s.FacultyID = $fid ";
    }
}

/* Lọc giới tính */
if ($gender === 'Nam' || $gender === 'Nữ') {
    $gd = $conn->real_escape_string($gender);
    $where .= " AND s.Gender = '$gd' ";
}

/* Lọc theo tình trạng HĐ hiệu lực (live) */
if ($inDorm === '1') {
    $where .= " AND EXISTS (
                    SELECT 1 FROM Contracts c 
                    WHERE c.StudentID = s.StudentID 
                      AND c.Status = 'Hiệu lực'
                ) ";
} elseif ($inDorm === '0') {
    $where .= " AND NOT EXISTS (
                    SELECT 1 FROM Contracts c 
                    WHERE c.StudentID = s.StudentID 
                      AND c.Status = 'Hiệu lực'
                ) ";
}

/* ============== 5) ĐẾM TỔNG + LẤY DANH SÁCH ============== */
$countSql = "SELECT COUNT(*) AS cnt FROM Students s $where";
$countRes = $conn->query($countSql);
$totalRows = $countRes ? (int)$countRes->fetch_assoc()['cnt'] : 0;
$totalPages = max(1, (int)ceil($totalRows / $perPage));

/*
 * Lấy dữ liệu:
 *  - InDormLive: tính bằng EXISTS
 *  - JOIN Faculties để lấy FacultyName
 *  - JOIN HĐ hiệu lực mới nhất để lấy phòng hiện tại
 */
$sql = "
SELECT 
    s.StudentID, s.UserID, s.StudentCode, s.FullName, s.Gender,
    s.FacultyID, f.FacultyName,
    s.ClassName, s.CourseYear, s.Phone, s.Email,
    s.IsInDorm, s.CreatedAt, s.Avatar,

    CASE WHEN EXISTS(
        SELECT 1 FROM Contracts c0
        WHERE c0.StudentID = s.StudentID AND c0.Status = 'Hiệu lực'
    ) THEN 1 ELSE 0 END AS InDormLive,

    c.ContractID,
    r.RoomNumber,
    b.BuildingName
FROM Students s
LEFT JOIN Faculties f 
    ON f.FacultyID = s.FacultyID
LEFT JOIN Contracts c
    ON c.ContractID = (
        SELECT c2.ContractID
        FROM Contracts c2
        WHERE c2.StudentID = s.StudentID AND c2.Status = 'Hiệu lực'
        ORDER BY c2.StartDate DESC, c2.ContractID DESC
        LIMIT 1
    )
LEFT JOIN Rooms r ON r.RoomID = c.RoomID
LEFT JOIN Buildings b ON b.BuildingID = r.BuildingID
$where
ORDER BY s.CreatedAt DESC
LIMIT $perPage OFFSET $offset
";
$listRes = $conn->query($sql);

/* ============== 6) HÀM PHỤ ============== */
function badge($text, $class = '')
{
    return "<span class=\"badge $class\">$text</span>";
}
function yesNoChip(bool $bool)
{
    return $bool
        ? "<span class='chip yes'>Đang ở</span>"
        : "<span class='chip no'>Chưa ở</span>";
}
function avatarUrl(?string $path, ?string $gender): string
{
    $fallback = '/assets/img/avatars/user.png';
    if ($gender === 'Nam') $fallback = '/assets/img/avatars/user.png';
    elseif ($gender === 'Nữ') $fallback = '/assets/img/avatars/user.png';

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
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
    <title>Quản lý Sinh viên | Hệ thống Ký túc xá</title>
    <link rel="stylesheet" href="../../../assets/css/admin/admin_header.css">
    <link rel="stylesheet" href="../../../assets/css/staff/students/staff_student_list.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>
    <div class="student-container">
        <div class="page-header">
            <h2><i class="fa-solid fa-people-roof"></i> Quản lý Sinh viên</h2>
            
            <div class="actions-right">
                <?php if ($_SESSION['Role'] === 'Admin'): ?>
                <a href="student_create.php" class="btn primary">
                    <i class="fa-solid fa-user-graduate"></i> Thêm hồ sơ sinh viên
                </a>
                <a href="student_import.php" class="btn btn-success">
                    <i class="fa-solid fa-file-import"></i> Nhập Excel
                </a>
                <?php endif; ?>
                <a href="student_export.php?<?= http_build_query($_GET) ?>" class="btn">
                    <i class="fa-solid fa-file-export"></i> Xuất danh sách
                </a>
            </div>
        </div>

        <!-- Thống kê nhanh -->
        <div class="stats">
            <div class="stat">
                <div class="num"><?= (int)$stats['total'] ?></div>
                <div class="label">Tổng sinh viên</div>
            </div>
            <div class="stat green">
                <div class="num"><?= (int)$stats['in_dorm'] ?></div>
                <div class="label">Đã có phòng</div>
            </div>
            <div class="stat gray">
                <div class="num"><?= (int)$stats['out_dorm'] ?></div>
                <div class="label">Chưa có phòng</div>
            </div>
            <div class="stat">
                <div class="num"><?= (int)$stats['male'] ?></div>
                <div class="label">Nam</div>
            </div>
            <div class="stat">
                <div class="num"><?= (int)$stats['female'] ?></div>
                <div class="label">Nữ</div>
            </div>
        </div>

        <!-- Bộ lọc -->
        <form class="filters" method="get">
            <div class="group">
                <label><i class="fa-solid fa-magnifying-glass"></i></label>
                <input type="text" name="search"
                    placeholder="Tìm tên, MSSV, SĐT..."
                    value="<?= htmlspecialchars($keyword) ?>">
            </div>

            <div class="group">
                <label>Khoa</label>
                <select name="faculty" onchange="this.form.submit()">
                    <option value="all" <?= $faculty === 'all' ? 'selected' : '' ?>>
                        Tất cả
                    </option>
                    <?php foreach ($faculties as $f):
                        $fid = (int)$f['FacultyID'];
                        $sel = ($faculty !== 'all' && (int)$faculty === $fid) ? 'selected' : '';
                    ?>
                        <option value="<?= $fid ?>" <?= $sel ?>>
                            <?= htmlspecialchars($f['FacultyName']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="group">
                <label>Trong KTX</label>
                <select name="in_dorm" onchange="this.form.submit()">
                    <option value="all" <?= $inDorm === 'all' ? 'selected' : '' ?>>Tất cả</option>
                    <option value="1" <?= $inDorm === '1'   ? 'selected' : '' ?>>Đã có phòng</option>
                    <option value="0" <?= $inDorm === '0'   ? 'selected' : '' ?>>Chưa có phòng</option>
                </select>
            </div>

            <div class="group">
                <label>Giới tính</label>
                <select name="gender" onchange="this.form.submit()">
                    <option value="all" <?= $gender === 'all' ? 'selected' : '' ?>>Tất cả</option>
                    <option value="Nam" <?= $gender === 'Nam' ? 'selected' : '' ?>>Nam</option>
                    <option value="Nữ" <?= $gender === 'Nữ'  ? 'selected' : '' ?>>Nữ</option>
                </select>
            </div>

            <div class="group">
                <label>Hiển thị</label>
                <select name="pp" onchange="this.form.submit()">
                    <?php foreach ([10, 20, 30, 50] as $pp): ?>
                        <option value="<?= $pp ?>" <?= $perPage == $pp ? 'selected' : '' ?>>
                            <?= $pp ?>/trang
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>

        <!-- Bảng danh sách -->
        <div class="table-wrap">
            <?php if ($listRes && $listRes->num_rows > 0): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Ảnh</th>
                            <th>MSSV</th>
                            <th>Họ tên</th>
                            <th>Khoa / Lớp</th>
                            <th>Khóa</th>
                            <th>Phòng hiện tại</th>
                            <th>Trong KTX</th>
                            <th>Liên hệ</th>
                            <th>Ngày tạo</th>
                            <th style="width:120px">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $listRes->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <img class="avatar"
                                        src="<?= avatarUrl($row['Avatar'] ?? '', $row['Gender'] ?? null) ?>"
                                        alt="avatar" loading="lazy">
                                </td>
                                <td><strong><?= htmlspecialchars($row['StudentCode']) ?></strong></td>
                                <td>
                                    <div class="name">
                                        <?= htmlspecialchars($row['FullName']) ?>
                                        <?= $row['Gender'] ? badge($row['Gender'], 'light') : '' ?>
                                    </div>
                                </td>
                                <td>
                                    <div><?= htmlspecialchars($row['FacultyName'] ?? '-') ?></div>
                                    <small class="muted">
                                        <?= htmlspecialchars($row['ClassName'] ?: '-') ?>
                                    </small>
                                </td>
                                <td><?= $row['CourseYear'] ? (int)$row['CourseYear'] : '-' ?></td>
                                <td>
                                    <?php if (!empty($row['RoomNumber'])): ?>
                                        <strong>
                                            <?= htmlspecialchars($row['BuildingName']) ?>
                                            <?= htmlspecialchars($row['RoomNumber']) ?>
                                        </strong>
                                        <?php if ($row['ContractID']): ?>
                                            <br>
                                            <small class="muted">
                                                HĐ #<?= (int)$row['ContractID'] ?>
                                            </small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="muted">Chưa xếp phòng</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= yesNoChip((bool)$row['InDormLive']) ?></td>
                                <td>
                                    <div><?= htmlspecialchars($row['Phone'] ?: '-') ?></div>
                                    <small class="muted">
                                        <?= htmlspecialchars($row['Email'] ?: '-') ?>
                                    </small>
                                </td>
                                <td>
                                    <?= $row['CreatedAt']
                                        ? date('d/m/Y', strtotime($row['CreatedAt']))
                                        : '-' ?>
                                </td>
                                <td>
                                    <div class="row-actions">
                                        <a href="student_detail.php?id=<?= (int)$row['StudentID'] ?>"
                                            class="action-btn view"
                                            title="Xem chi tiết">
                                            <i class="fa-solid fa-eye"></i>
                                        </a>
                                        <?php if ($_SESSION['Role'] === 'Admin'): ?>
                                        <a href="student_edit.php?id=<?= (int)$row['StudentID'] ?>"
                                            class="action-btn edit"
                                            title="Sửa thông tin">
                                            <i class="fa-solid fa-pen"></i>
                                        </a>
                                            <button class="action-btn delete"
                                                title="Xóa sinh viên"
                                                onclick="deleteStudent(<?= (int)$row['StudentID'] ?>,'<?= htmlspecialchars($row['FullName'], ENT_QUOTES) ?>', this)">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty">
                    <i class="fa-regular fa-inbox"></i>
                    <p>Không có sinh viên nào phù hợp với tiêu chí.</p>
                    <a class="btn" href="?">
                        <i class="fa-solid fa-rotate"></i> Xem tất cả
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Phân trang -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php
                $qs = $_GET;
                unset($qs['page']);
                $base = '?' . http_build_query($qs);
                $prev = max(1, $page - 1);
                $next = min($totalPages, $page + 1);
                ?>
                <a class="pg <?= $page == 1 ? 'disabled' : '' ?>" href="<?= $base . '&page=1' ?>">&laquo;</a>
                <a class="pg <?= $page == 1 ? 'disabled' : '' ?>" href="<?= $base . '&page=' . $prev ?>">&lsaquo;</a>
                <span class="cur">Trang <?= $page ?>/<?= $totalPages ?></span>
                <a class="pg <?= $page == $totalPages ? 'disabled' : '' ?>" href="<?= $base . '&page=' . $next ?>">&rsaquo;</a>
                <a class="pg <?= $page == $totalPages ? 'disabled' : '' ?>" href="<?= $base . '&page=' . $totalPages ?>">&raquo;</a>
            </div>
        <?php endif; ?>
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
                        <p class="text-success"><i class="fa-solid fa-check-circle"></i> Thành công: <b>${success}</b> sinh viên</p>
                        <p class="text-danger"><i class="fa-solid fa-times-circle"></i> Thất bại: <b>${fail}</b> sinh viên</p>
                       </div>`,
                icon: fail > 0 ? 'warning' : 'success',
                confirmButtonText: 'Đóng'
            }).then(() => {
                // Remove params from URL
                window.history.replaceState({}, document.title, window.location.pathname);
            });
        }

        function deleteStudent(id, name, btn) {
            Swal.fire({
                title: 'Xác nhận xóa',
                html: `Bạn có chắc muốn xóa hồ sơ <b>${name}</b>?<br><small class="muted">Hành động này không thể hoàn tác.</small>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e11d48',
                cancelButtonColor: '#6b7280',
                confirmButtonText: '<i class="fa-regular fa-trash-can"></i> Xóa',
                cancelButtonText: 'Hủy',
                reverseButtons: true,
                showClass: {
                    popup: 'animate__animated animate__zoomIn'
                },
                hideClass: {
                    popup: 'animate__animated animate__zoomOut'
                }
            }).then((res) => {
                if (!res.isConfirmed) return;

                if (btn) {
                    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
                    btn.classList.add('loading');
                }

                fetch('student_delete_api.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: new URLSearchParams({
                            id,
                            _csrf: CSRF_TOKEN
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
                                showConfirmButton: false,
                                showClass: {
                                    popup: 'animate__animated animate__bounceIn'
                                }
                            });

                            const row = btn ? btn.closest('tr') : null;
                            if (row) {
                                row.style.animation = 'fadeOut 0.5s ease forwards';
                                setTimeout(() => {
                                    row.remove();
                                    if (document.querySelectorAll('.table tbody tr').length === 0) {
                                        location.reload();
                                    }
                                }, 500);
                            } else {
                                setTimeout(() => location.reload(), 700);
                            }
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Lỗi',
                                text: data.message || 'Không thể xóa',
                                showClass: {
                                    popup: 'animate__animated animate__shakeX'
                                }
                            });
                            if (btn) {
                                btn.innerHTML = '<i class="fa-solid fa-trash-can"></i>';
                                btn.classList.remove('loading');
                            }
                        }
                    })
                    .catch(() => {
                        Swal.fire({
                            icon: 'error',
                            title: 'Lỗi',
                            text: 'Không thể kết nối máy chủ',
                            showClass: {
                                popup: 'animate__animated animate__shakeX'
                            }
                        });
                        if (btn) {
                            btn.innerHTML = '<i class="fa-solid fa-trash-can"></i>';
                            btn.classList.remove('loading');
                        }
                    });
            });
        }

        // Một ít hiệu ứng UI giữ nguyên
        document.addEventListener('DOMContentLoaded', function() {
            const filterForm = document.getElementById('filterForm');
            if (!filterForm) return;

            const filterSelects = filterForm.querySelectorAll('select, input[name="search"]');
            let filterTimeout;

            // Hàm gửi filter AJAX
            function submitFilterWithAjax() {
                const formData = new FormData(filterForm);
                const params = new URLSearchParams(formData);
                
                // Lưu vị trí cuộn trang hiện tại
                const scrollTop = window.scrollY || document.documentElement.scrollTop;

                const url = window.location.pathname + '?' + params.toString();

                // Cập nhật URL
                window.history.replaceState(null, '', url);

                // Tải dữ liệu mới
                fetch(url, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(r => r.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newContainer = doc.querySelector('.student-container');
                    
                    if (newContainer) {
                        const oldContainer = document.querySelector('.student-container');
                        if (oldContainer) {
                            oldContainer.innerHTML = newContainer.innerHTML;
                            // Khôi phục vị trí cuộn trang
                            window.scrollTo(0, scrollTop);
                            // Re-attach event listeners sau khi cập nhật DOM
                            reattachEventListeners();
                        }
                    }
                })
                .catch(e => console.error('Filter error:', e));
            }

            function reattachEventListeners() {
                // Re-attach filter listeners
                const newFilterForm = document.getElementById('filterForm');
                if (newFilterForm) {
                    const newFilterSelects = newFilterForm.querySelectorAll('select, input[name="search"]');
                    newFilterSelects.forEach(select => {
                        select.addEventListener('change', function() {
                            clearTimeout(filterTimeout);
                            filterTimeout = setTimeout(() => {
                                submitFilterWithAjax();
                            }, 300);
                        });
                    });

                    const newSearchInput = newFilterForm.querySelector('input[name="search"]');
                    if (newSearchInput) {
                        newSearchInput.addEventListener('input', function() {
                            clearTimeout(filterTimeout);
                            filterTimeout = setTimeout(() => {
                                submitFilterWithAjax();
                            }, 500);
                        });
                    }
                }

                // Re-attach delete button listeners
                const deleteButtons = document.querySelectorAll('.action-btn.delete');
                deleteButtons.forEach(btn => {
                    btn.addEventListener('click', function(e) {
                        e.preventDefault();
                        const id = this.getAttribute('data-id');
                        const name = this.getAttribute('data-name');
                        deleteStudent(id, name, this);
                    });
                });

                // Re-attach input focus/blur effects
                const filterInputs = document.querySelectorAll('.filters input, .filters select');
                filterInputs.forEach(input => {
                    input.addEventListener('focus', function() {
                        this.parentElement.style.transform = 'translateY(-3px)';
                        this.parentElement.style.boxShadow = '0 5px 15px rgba(0,0,0,0.1)';
                    });
                    input.addEventListener('blur', function() {
                        this.parentElement.style.transform = 'translateY(0)';
                        this.parentElement.style.boxShadow = 'none';
                    });
                });

                // Re-attach button effects
                const buttons = document.querySelectorAll('.btn');
                buttons.forEach(btn => {
                    btn.addEventListener('click', function() {
                        this.style.transform = 'scale(0.95)';
                        setTimeout(() => { this.style.transform = ''; }, 150);
                    });
                });
            }

            // Gửi filter khi select/input thay đổi (không tải lại trang)
            filterSelects.forEach(select => {
                select.addEventListener('change', function() {
                    clearTimeout(filterTimeout);
                    filterTimeout = setTimeout(() => {
                        submitFilterWithAjax();
                    }, 300);
                });
            });

            // Gửi filter khi search input thay đổi (debounce 500ms)
            const searchInput = filterForm.querySelector('input[name="search"]');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    clearTimeout(filterTimeout);
                    filterTimeout = setTimeout(() => {
                        submitFilterWithAjax();
                    }, 500);
                });
            }

            // Re-attach delete button listeners
            const deleteButtons = document.querySelectorAll('.action-btn.delete');
            deleteButtons.forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const id = this.getAttribute('data-id');
                    const name = this.getAttribute('data-name');
                    deleteStudent(id, name, this);
                });
            });

            const filterInputs = document.querySelectorAll('.filters input, .filters select');
            filterInputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.style.transform = 'translateY(-3px)';
                    this.parentElement.style.boxShadow = '0 5px 15px rgba(0,0,0,0.1)';
                });
                input.addEventListener('blur', function() {
                    this.parentElement.style.transform = 'translateY(0)';
                    this.parentElement.style.boxShadow = 'none';
                });
            });

            const buttons = document.querySelectorAll('.btn');
            buttons.forEach(btn => {
                btn.addEventListener('click', function() {
                    this.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        this.style.transform = '';
                    }, 150);
                });
            });
        });

        const style = document.createElement('style');
        style.textContent = `
        @keyframes fadeOut {
            from { opacity: 1; transform: translateX(0); }
            to   { opacity: 0; transform: translateX(-100%); }
        }
    `;
        document.head.appendChild(style);
    </script>
</body>

</html>
