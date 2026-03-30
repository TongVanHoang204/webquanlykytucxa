<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../../db_connect.php';
require_once '../../../includes/auth_check.php';
requireRole(['Admin', 'Manager']);

$conn->set_charset('utf8mb4');

if (empty($_SESSION['_csrf'])) {
    $_SESSION['_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['_csrf'];

/* ====== FILTER & PHÂN TRANG ====== */
$statusFilter = $_GET['status'] ?? 'all';
$search       = trim($_GET['q'] ?? '');
$validStatus  = ['Chờ duyệt', 'Đã duyệt', 'Từ chối'];

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset  = ($page - 1) * $perPage;

$conds  = [];
$params = [];
$types  = '';

/* Lọc theo trạng thái */
if (in_array($statusFilter, $validStatus, true)) {
    $conds[]  = "rr.Status = ?";
    $params[] = $statusFilter;
    $types   .= 's';
}

/* Tìm kiếm: MSSV / Họ tên / Số phòng */
if ($search !== '') {
    $conds[] = "(st.StudentCode LIKE ? OR st.FullName LIKE ? OR r.RoomNumber LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types   .= 'sss';
}

$where = $conds ? 'WHERE ' . implode(' AND ', $conds) : '';

/* ====== ĐẾM TỔNG BẢN GHI CHO PHÂN TRANG ====== */
$sqlCount = "
    SELECT COUNT(*) 
    FROM roomrequests rr
    JOIN students st ON st.StudentID = rr.StudentID
    JOIN rooms r     ON r.RoomID     = rr.RoomID
    $where
";

$stm = $conn->prepare($sqlCount);
if (!$stm) {
    die('Lỗi prepare count: ' . $conn->error);
}
if ($params) {
    $stm->bind_param($types, ...$params);
}
$stm->execute();
$stm->bind_result($totalRows);
$stm->fetch();
$stm->close();

$totalRows  = (int)($totalRows ?? 0);
$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page   = $totalPages;
    $offset = ($page - 1) * $perPage;
}

/* ====== LẤY DANH SÁCH YÊU CẦU (CÓ PHÂN TRANG) ====== */
$sql = "
    SELECT 
        rr.RequestID, 
        rr.Status, 
        rr.DesiredFrom, 
        rr.Note, 
        rr.CreatedAt,
        st.StudentCode, 
        st.FullName,
        r.RoomNumber AS RoomCode,
        COALESCE(r.RoomType,'')        AS RoomType,
        COALESCE(r.Capacity,0)         AS Capacity,
        COALESCE(c.ActiveCount,0)      AS CurrentOccupants,
        COALESCE(r.RoomPrice,0)        AS RoomPrice
    FROM roomrequests rr
    JOIN students st ON st.StudentID = rr.StudentID
    JOIN rooms r     ON r.RoomID     = rr.RoomID
    LEFT JOIN (
        SELECT 
            RoomID,
            COUNT(*) AS ActiveCount
        FROM contracts
        WHERE Status = 'Hiệu lực'
          AND StartDate <= CURDATE()
          AND (EndDate IS NULL OR EndDate >= CURDATE())
        GROUP BY RoomID
    ) c ON c.RoomID = r.RoomID
    $where
    ORDER BY rr.CreatedAt DESC
    LIMIT ?, ?
";

$paramsData   = $params;
$typesData    = $types . 'ii';
$paramsData[] = $offset;
$paramsData[] = $perPage;

$stm = $conn->prepare($sql);
if (!$stm) {
    die('Lỗi prepare data: ' . $conn->error);
}
$stm->bind_param($typesData, ...$paramsData);
$stm->execute();
$res = $stm->get_result();

$rows = [];
if ($res) {
    while ($row = $res->fetch_assoc()) $rows[] = $row;
}
$stm->close();

/* Helper format tiền VNĐ */
function money_vn($n)
{
    return number_format((float)$n, 0, ',', '.') . ' ₫';
}

// Đếm số lượng theo trạng thái (trong page hiện tại)
$pendingCount  = 0;
$approvedCount = 0;
$rejectedCount = 0;
foreach ($rows as $r) {
    switch ($r['Status']) {
        case 'Chờ duyệt':
            $pendingCount++;
            break;
        case 'Đã duyệt':
            $approvedCount++;
            break;
        case 'Từ chối':
            $rejectedCount++;
            break;
    }
}

require_once '../../../includes/admin_header.php';

// DEBUG: Hiển thị session message nếu có
if (isset($_SESSION['message'])) {
    echo "<!-- DEBUG SESSION MESSAGE: " . htmlspecialchars($_SESSION['message']) . " -->";
    echo "<!-- DEBUG MESSAGE TYPE: " . ($_SESSION['message_type'] ?? 'none') . " -->";
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Duyệt yêu cầu đăng ký phòng</title>
    <link rel="stylesheet" href="../../../assets/css/admin/admin_header.css">
    <link rel="stylesheet" href="../../../assets/css/staff/room_requests/staff_request_list.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="../../../assets/vendor/fontawesome/css/all.min.css">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
    <style>
        /* Đảm bảo SweetAlert2 hiển thị trên cùng */
        .swal2-container {
            z-index: 99999 !important;
        }

        .swal2-popup {
            display: block !important;
            visibility: visible !important;
        }
    </style>
</head>

<body>

    <div class="admin-container">
        <!-- HEADER -->
        <div class="page-header">
            <h2><i class="fa-solid fa-clipboard-check"></i> Duyệt yêu cầu đăng ký phòng</h2>
            <div class="header-actions">
                <button class="btn primary" onclick="window.print()">
                    <i class="fa-solid fa-print"></i> In danh sách
                </button>
            </div>
        </div>

        <!-- STATS CARDS -->
        <div class="stats-cards">
            <div class="stat-card">
                <div class="label">Tổng yêu cầu</div>
                <div class="value"><?= $totalRows ?></div>
            </div>
            <div class="stat-card">
                <div class="label">Chờ duyệt</div>
                <div class="value"><?= $pendingCount ?></div>
            </div>
            <div class="stat-card">
                <div class="label">Đã duyệt</div>
                <div class="value"><?= $approvedCount ?></div>
            </div>
            <div class="stat-card">
                <div class="label">Từ chối</div>
                <div class="value"><?= $rejectedCount ?></div>
            </div>
        </div>

        <!-- FILTERS -->
        <div class="filters-container">
            <form method="get" class="filters" id="filterForm">
                <div class="filter-group">
                    <label for="search">Tìm kiếm</label>
                    <input type="text"
                        id="search"
                        name="q"
                        placeholder="Tìm MSSV, họ tên, số phòng..."
                        value="<?= htmlspecialchars($search) ?>"
                        class="search-input">
                </div>

                <div class="filter-group">
                    <label for="status">Trạng thái</label>
                    <select id="status" name="status" class="filter-select">
                        <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>Tất cả</option>
                        <option value="Chờ duyệt" <?= $statusFilter === 'Chờ duyệt' ? 'selected' : '' ?>>Chờ duyệt</option>
                        <option value="Đã duyệt" <?= $statusFilter === 'Đã duyệt' ? 'selected' : '' ?>>Đã duyệt</option>
                        <option value="Từ chối" <?= $statusFilter === 'Từ chối' ? 'selected' : '' ?>>Từ chối</option>
                    </select>
                </div>
            </form>
        </div>

        <!-- RESULTS -->
        <?php if (!$rows): ?>
            <div class="empty-state">
                <i class="fa-solid fa-inbox"></i>
                <h3>Không có yêu cầu nào</h3>
                <p>Không tìm thấy yêu cầu đăng ký phòng nào phù hợp với bộ lọc của bạn.</p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Sinh viên</th>
                            <th>Phòng đăng ký</th>
                            <th>Ngày bắt đầu</th>
                            <th>Trạng thái</th>
                            <th>Ngày gửi</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $i => $r):
                            $cls = str_replace(' ', '', $r['Status']);
                        ?>
                            <tr>
                                <td><?= ($offset + $i + 1) ?></td>

                                <!-- Thông tin sinh viên -->
                                <td class="student-info">
                                    <strong><?= htmlspecialchars($r['FullName']) ?></strong>
                                    <span class="small">MSSV: <?= htmlspecialchars($r['StudentCode']) ?></span>
                                </td>

                                <!-- Thông tin phòng -->
                                <td>
                                    <strong><?= htmlspecialchars($r['RoomCode']) ?></strong>
                                    <div class="room-details">
                                        <?= htmlspecialchars($r['RoomType'] ?: 'Không rõ') ?> ·
                                        <?= (int)$r['CurrentOccupants'] ?>/<?= (int)$r['Capacity'] ?> SV ·
                                        <?= money_vn($r['RoomPrice']) ?>/tháng
                                    </div>
                                </td>

                                <td><?= htmlspecialchars($r['DesiredFrom'] ?: '-') ?></td>

                                <td><span class="status <?= $cls ?>"><?= htmlspecialchars($r['Status']) ?></span></td>
                                <td><?= date('d/m/Y', strtotime($r['CreatedAt'])) ?></td>

                                <td class="actions">
                                    <!-- Nút xem chi tiết -->
                                    <a class="btn info" href="staff_request_detail.php?id=<?= (int)$r['RequestID'] ?>">
                                        <i class="fa-solid fa-circle-info"></i> Chi tiết
                                    </a>
                                    <?php if (($_SESSION['Role'] ?? '') === 'Admin'): ?>
                                        <?php if ($r['Status'] === 'Chờ duyệt'): ?>
                                            <!-- Nút duyệt -->
                                            <button type="button" class="btn approve swal-approve"
                                                data-id="<?= (int)$r['RequestID'] ?>">
                                                <i class="fa-solid fa-check"></i> Duyệt
                                            </button>

                                            <!-- Nút từ chối -->
                                            <button type="button" class="btn reject swal-reject"
                                                data-id="<?= (int)$r['RequestID'] ?>"
                                                data-name="<?= htmlspecialchars($r['FullName']) ?>">
                                                <i class="fa-solid fa-xmark"></i> Từ chối
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- PHÂN TRANG -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php for ($p = 1; $p <= $totalPages; $p++):
                        $query = http_build_query([
                            'status' => $statusFilter,
                            'q'      => $search,
                            'page'   => $p
                        ]);
                    ?>
                        <a href="?<?= $query ?>" class="<?= $p === $page ? 'active' : '' ?>">
                            <?= $p ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        function submitSecurePost(url, payload = {}) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = url;

            Object.entries(payload).forEach(([key, value]) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = value;
                form.appendChild(input);
            });

            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = '_csrf';
            csrfInput.value = CSRF_TOKEN;
            form.appendChild(csrfInput);

            document.body.appendChild(form);
            form.submit();
        }
        // ================== TOAST THÔNG BÁO ĐƠN GIẢN (KHÔNG DÙNG classList) ==================
        function showToast(message, type = 'info', duration = 3000) {
            let container = document.querySelector('.toast-container');
            if (!container) {
                container = document.createElement('div');
                container.className = 'toast-container';
                container.style.position = 'fixed';
                container.style.top = '16px';
                container.style.right = '16px';
                container.style.zIndex = '2000';
                document.body.appendChild(container);
            }

            const toast = document.createElement('div');
            toast.className = 'toast ' + type;
            toast.style.display = 'flex';
            toast.style.alignItems = 'center';
            toast.style.gap = '8px';
            toast.style.marginBottom = '8px';
            toast.style.padding = '10px 14px';
            toast.style.borderRadius = '6px';
            toast.style.background = (type === 'success') ? '#16a34a' :
                (type === 'error') ? '#dc2626' : '#2563eb';
            toast.style.color = '#fff';
            toast.style.boxShadow = '0 4px 10px rgba(0,0,0,0.15)';
            toast.style.fontSize = '14px';

            let icon = 'fa-info-circle';
            if (type === 'success') icon = 'fa-check-circle';
            if (type === 'error') icon = 'fa-exclamation-circle';

            toast.innerHTML =
                '<i class="fa-solid ' + icon + '"></i>' +
                '<span class="message">' + message + '</span>' +
                '<span class="close" style="margin-left:8px;cursor:pointer;">' +
                '<i class="fa-solid fa-times"></i>' +
                '</span>';

            container.appendChild(toast);

            const closeBtn = toast.querySelector('.close');
            closeBtn.addEventListener('click', function() {
                if (toast && toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            });

            setTimeout(() => {
                if (toast && toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, duration);
        }

        // ================== HIỂN THỊ THÔNG BÁO SAU KHI HÀNH ĐỘNG (CÓ DEBUG) ==================
        function checkActionMessage() {
            const rawQuery = window.location.search;
            const params = new URLSearchParams(rawQuery);

            console.log('🔍 DEBUG raw query:', rawQuery);

            const allParams = {};
            params.forEach((v, k) => {
                allParams[k] = v;
            });
            console.log('🔍 DEBUG parsed params:', allParams);

            let needClean = false;

            if (params.has('approved')) {
                console.log('✅ DEBUG: approved detected');
                showToast('✓ Đã duyệt yêu cầu thành công!', 'success');
                needClean = true;
            } else if (params.has('rejected')) {
                console.log('⚠️ DEBUG: rejected detected');
                showToast('✗ Đã từ chối yêu cầu!', 'error');
                needClean = true;
            } else if (params.has('error')) {
                const errMsg = params.get('error') || 'Không rõ lỗi';
                console.error('❌ DEBUG: error param =', errMsg);
                showToast('Có lỗi xảy ra, vui lòng thử lại! (' + errMsg + ')', 'error');
                needClean = true;
            } else {
                console.log('ℹ️ DEBUG: Không có tham số approved / rejected / error trong URL.');
            }

            // Sau khi hiển thị toast thì xóa param khỏi URL để F5 không hiện lại
            if (needClean) {
                params.delete('approved');
                params.delete('rejected');
                params.delete('error');

                const newQuery = params.toString();
                const newUrl = window.location.pathname + (newQuery ? '?' + newQuery : '');
                console.log('🧹 DEBUG: replaceState URL ->', newUrl);

                window.history.replaceState(null, '', newUrl);
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            console.log('📄 DEBUG: staff_request_list loaded, rows hiện tại = <?= count($rows) ?>');

            // 1) Thông báo theo query (?approved=1, ?rejected=1,...)
            checkActionMessage();

            // 2) Auto-submit form filter khi đổi trạng thái
            const statusSelect = document.getElementById('status');
            const filterForm = document.getElementById('filterForm');
            if (statusSelect && filterForm) {
                statusSelect.addEventListener('change', function() {
                    console.log('🔁 DEBUG: đổi trạng thái filter thành:', this.value);
                    filterForm.submit();
                });
            } else {
                console.warn('⚠️ DEBUG: Không tìm thấy #status hoặc #filterForm');
            }

            // 3) SweetAlert2 cho nút DUYỆT
            const approveBtns = document.querySelectorAll('.swal-approve');
            console.log('🔍 DEBUG: Tìm thấy', approveBtns.length, 'nút DUYỆT');

            approveBtns.forEach((btn, index) => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();

                    const requestId = this.getAttribute('data-id');
                    console.log(`▶️ DEBUG [${index}]: Click DUYỆT`);
                    console.log('  - RequestID:', requestId);
                    console.log('  - Button element:', btn);
                    console.log('  - Swal object:', typeof Swal, Swal);

                    if (typeof Swal === 'undefined') {
                        console.error('❌ SweetAlert2 chưa được load!');
                        alert('Đang tải thư viện, vui lòng thử lại!');
                        return false;
                    }

                    console.log('🚀 DEBUG: Chuẩn bị gọi Swal.fire()...');

                    try {
                        const swalResult = Swal.fire({
                            title: 'Duyệt yêu cầu này?',
                            text: 'Hệ thống sẽ tự tạo hợp đồng từ yêu cầu này.',
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonText: 'Duyệt',
                            cancelButtonText: 'Hủy',
                            confirmButtonColor: '#28a745',
                            cancelButtonColor: '#6c757d',
                            allowOutsideClick: false,
                            allowEscapeKey: false
                        });

                        console.log('✅ DEBUG: Đã gọi Swal.fire(), kết quả:', swalResult);

                        swalResult.then(result => {
                            console.log('🎯 DEBUG: Vào .then() callback');
                            console.log('✔️ DEBUG Swal approve result:', result);
                            console.log('  - isConfirmed:', result.isConfirmed);
                            console.log('  - isDismissed:', result.isDismissed);
                            console.log('  - isDenied:', result.isDenied);

                            if (result.isConfirmed) {
                                setTimeout(() => {
                                    submitSecurePost('staff_request_approve.php', { id: requestId });
                                }, 100);
                            } else {
                                console.log('❌ DEBUG: Người dùng hủy thao tác');
                            }
                        }).catch(err => {
                            console.error('❌ DEBUG: Lỗi SweetAlert approve .then():', err);
                        });
                    } catch (error) {
                        console.error('💥 DEBUG: Exception khi gọi Swal.fire():', error);
                    }

                    return false;
                }, true);
            });

            // 4) SweetAlert2 cho nút TỪ CHỐI
            const rejectBtns = document.querySelectorAll('.swal-reject');
            console.log('🔍 DEBUG: Tìm thấy', rejectBtns.length, 'nút TỪ CHỐI');

            rejectBtns.forEach((btn, index) => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const name = this.getAttribute('data-name') || '';
                    const requestId = this.getAttribute('data-id');
                    console.log(`▶️ DEBUG [${index}]: Click TỪ CHỐI`);
                    console.log('  - Student Name:', name);
                    console.log('  - Button element:', btn);

                    Swal.fire({
                        title: 'Từ chối yêu cầu?',
                        text: name ? 'Bạn muốn từ chối yêu cầu của: ' + name + '?' : 'Bạn chắc chắn muốn từ chối yêu cầu này?',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Từ chối',
                        cancelButtonText: 'Hủy',
                        confirmButtonColor: '#dc3545'
                    }).then(result => {
                        console.log('✔️ DEBUG Swal reject result:', result);
                        if (result.isConfirmed) {
                            submitSecurePost('request_reject.php', { id: requestId || '' });
                        } else {
                            console.log('❌ DEBUG: Người dùng hủy thao tác');
                        }
                    }).catch(err => {
                        console.error('❌ DEBUG: Lỗi SweetAlert reject:', err);
                    });

                    return false;
                });
            });
        });
    </script>

</body>

</html>
