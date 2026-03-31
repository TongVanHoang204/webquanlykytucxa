<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../db_connect.php';
require_once '../../includes/auth_check.php';

/** Chỉ Admin & Manager được truy cập */
requireRole(['Admin', 'Manager']);

$currentRole = $_SESSION['Role'] ?? '';

/* CSRF token cho AJAX */
if (empty($_SESSION['_csrf'])) {
    $_SESSION['_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['_csrf'];

/* ===================== THỐNG KÊ ===================== */
$adminCount    = $conn->query("SELECT COUNT(*) AS total FROM Users WHERE Role = 'Admin'")->fetch_assoc()['total'] ?? 0;
$managerCount  = $conn->query("SELECT COUNT(*) AS total FROM Users WHERE Role = 'Manager'")->fetch_assoc()['total'] ?? 0;
$studentCount  = $conn->query("SELECT COUNT(*) AS total FROM Users WHERE Role = 'Student'")->fetch_assoc()['total'] ?? 0;
$activeUsers   = $conn->query("SELECT COUNT(*) AS total FROM Users WHERE IsActive = 1")->fetch_assoc()['total'] ?? 0;
$inactiveUsers = $conn->query("SELECT COUNT(*) AS total FROM Users WHERE IsActive = 0")->fetch_assoc()['total'] ?? 0;

// Thêm thống kê mới
$totalRooms          = $conn->query("SELECT COUNT(*) AS total FROM Rooms")->fetch_assoc()['total'] ?? 0;
$occupiedRooms       = $conn->query("SELECT COUNT(*) AS total FROM Rooms WHERE Status = 'occupied'")->fetch_assoc()['total'] ?? 0;
$availableRooms      = $conn->query("SELECT COUNT(*) AS total FROM Rooms WHERE Status = 'available'")->fetch_assoc()['total'] ?? 0;
$totalAnnouncements  = $conn->query("SELECT COUNT(*) AS total FROM Announcements")->fetch_assoc()['total'] ?? 0;
$todayAnnouncements  = $conn->query("SELECT COUNT(*) AS total FROM Announcements WHERE DATE(DatePosted) = CURDATE()")->fetch_assoc()['total'] ?? 0;

/* ===================== LỌC TÌM KIẾM ===================== */
$roleFilter   = isset($_GET['role'])   ? trim($_GET['role'])   : '';
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';
$q            = isset($_GET['q'])      ? trim($_GET['q'])      : '';

$where  = [];
$params = [];
$types  = '';

if ($roleFilter !== '' && in_array($roleFilter, ['Admin', 'Manager', 'Student'], true)) {
    $where[]  = "Role = ?";
    $params[] = $roleFilter;
    $types   .= 's';
}
if ($statusFilter !== '' && in_array($statusFilter, ['1', '0'], true)) {
    $where[]  = "IsActive = ?";
    $params[] = (int)$statusFilter;
    $types   .= 'i';
}
if ($q !== '') {
    $where[]  = "(Username LIKE ? OR FullName LIKE ? OR Email LIKE ?)";
    $like     = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types   .= 'sss';
}

$sql = "SELECT UserID, Username, FullName, Email, Role, IsActive, CreatedAt FROM Users";
if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY CreatedAt DESC LIMIT 20";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $list = $stmt->get_result();
    $stmt->close();
} else {
    $list = $conn->query($sql);
}

require_once '../../includes/admin_header.php';
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Manager | Hệ thống Ký túc xá</title>

    <!-- CSS riêng cho dashboard manager -->
    <link rel="stylesheet" href="<?= $base ?>assets/css/staff/staff_dashboard.css">

    <!-- SweetAlert & Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <meta name="csrf-token" content="<?= htmlspecialchars($csrf) ?>">
</head>

<body class="theme-auto">
    <div class="dashboard-container">
        <!-- Header -->
        <div class="dashboard-header">
            <div class="header-content">
                <h1><i class="fa-solid fa-chart-line"></i> Dashboard Manager</h1>
                <p>Quản lý toàn diện hệ thống ký túc xá</p>
            </div>
        </div>

        <!-- Thống kê tổng quan -->
        <div class="stats-overview">
            <div class="stat-card">
                <div class="stat-icon user">
                    <i class="fa-solid fa-users"></i>
                </div>
                <div class="stat-content">
                    <h3><?= $adminCount + $managerCount + $studentCount ?></h3>
                    <p>Tổng người dùng</p>
                    <div class="stat-breakdown">
                        <span class="badge admin"><?= $adminCount ?> Admin</span>
                        <span class="badge manager"><?= $managerCount ?> Manager</span>
                        <span class="badge student"><?= $studentCount ?> Student</span>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon room">
                    <i class="fa-solid fa-building"></i>
                </div>
                <div class="stat-content">
                    <h3><?= $totalRooms ?></h3>
                    <p>Tổng phòng</p>
                    <div class="stat-breakdown">
                        <span class="badge occupied"><?= $occupiedRooms ?> Đã thuê</span>
                        <span class="badge available"><?= $availableRooms ?> Trống</span>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon announcement">
                    <i class="fa-solid fa-bullhorn"></i>
                </div>
                <div class="stat-content">
                    <h3><?= $totalAnnouncements ?></h3>
                    <p>Thông báo</p>
                    <div class="stat-breakdown">
                        <span class="badge today"><?= $todayAnnouncements ?> Hôm nay</span>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon activity">
                    <i class="fa-solid fa-chart-bar"></i>
                </div>
                <div class="stat-content">
                    <h3><?= $activeUsers ?></h3>
                    <p>Đang hoạt động</p>
                    <div class="stat-breakdown">
                        <span class="badge inactive"><?= $inactiveUsers ?> Ngừng HĐ</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bộ lọc và tìm kiếm -->
        <div class="search-filter-section">
            <form class="search-filter-form" method="get">
                <div class="search-box">
                    <i class="fa-solid fa-search"></i>
                    <input type="text" name="q" placeholder="Tìm theo tên, email..." value="<?= htmlspecialchars($q) ?>">
                </div>

                <div class="filter-group">
                    <select name="role" class="filter-select">
                        <option value="">Tất cả vai trò</option>
                        <option value="Admin" <?= $roleFilter === 'Admin'   ? 'selected' : '' ?>>Admin</option>
                        <option value="Manager" <?= $roleFilter === 'Manager' ? 'selected' : '' ?>>Manager</option>
                        <option value="Student" <?= $roleFilter === 'Student' ? 'selected' : '' ?>>Student</option>
                    </select>

                    <select name="status" class="filter-select">
                        <option value="">Tất cả trạng thái</option>
                        <option value="1" <?= $statusFilter === '1' ? 'selected' : '' ?>>Hoạt động</option>
                        <option value="0" <?= $statusFilter === '0' ? 'selected' : '' ?>>Ngừng</option>
                    </select>

                    <button type="submit" class="btn primary filter-btn">
                        <i class="fa-solid fa-filter"></i> Lọc
                    </button>

                    <?php if ($roleFilter || $statusFilter || $q): ?>
                        <a class="btn ghost" href="?">
                            <i class="fa-solid fa-rotate-left"></i> Bỏ lọc
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions-section">
            <h3>Thao tác nhanh</h3>
            <div class="quick-actions-grid">

                <a href="<?= $base ?>modules/staff/users/users.php" class="quick-action">
                    <div class="action-icon">
                        <i class="fa-solid fa-user"></i>
                    </div>
                    <span>Danh sách user</span>
                </a>

                <a href="<?= $base ?>modules/staff/students/student_list.php" class="quick-action">
                    <div class="action-icon">
                        <i class="fa-solid fa-user"></i>
                    </div>
                    <span>Danh sách sinh viên</span>
                </a>

                <a href="<?= $base ?>modules/staff/rooms/rooms.php" class="quick-action">
                    <div class="action-icon">
                        <i class="fa-solid fa-bed"></i>
                    </div>
                    <span>Danh sách phòng</span>
                </a>

                <a href="<?= $base ?>modules/staff/invoices/invoice_list.php" class="quick-action">
                    <div class="action-icon">
                        <i class="fa-solid fa-file-invoice"></i>
                    </div>
                    <span>Danh sách hóa đơn</span>
                </a>

                <a href="<?= $base ?>modules/staff/contract/contract_list.php" class="quick-action">
                    <div class="action-icon">
                        <i class="fa-solid fa-file-signature"></i>
                    </div>
                    <span>Danh sách hợp đồng</span>
                </a>

                <a href="<?= $base ?>modules/staff/announcements/announcement_list.php" class="quick-action">
                    <div class="action-icon">
                        <i class="fa-solid fa-list"></i>
                    </div>
                    <span>Danh sách thông báo</span>
                </a>

                <a href="<?= $base ?>modules/staff/feedbacks/feedback_list.php" class="quick-action">
                    <div class="action-icon">
                        <i class="fa-solid fa-comments"></i>
                    </div>
                    <span>Danh sách phản ánh</span>
                </a>

                <a href="<?= $base ?>modules/staff/room_requests/staff_request_list.php" class="quick-action">
                    <div class="action-icon">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                    <span>Danh sách đăng ký phòng</span>
                </a>
            </div>
        </div>

        <!-- ==================== CHARTS SECTION ==================== -->
        <?php
        // Chart data cho Staff Dashboard
        $sChartRoles = ['Admin' => 0, 'Manager' => 0, 'Student' => 0];
        $rc = $conn->query("SELECT Role, COUNT(*) cnt FROM users GROUP BY Role");
        while ($r = $rc->fetch_assoc()) $sChartRoles[$r['Role']] = (int)$r['cnt'];

        $sInvLabels = []; $sInvPaid = []; $sInvUnpaid = [];
        for ($i = 4; $i >= 0; $i--) {
            $m = date('m', strtotime("-$i months"));
            $y = date('Y', strtotime("-$i months"));
            $sInvLabels[] = date('m/Y', strtotime("-$i months"));
            $p = $conn->query("SELECT COUNT(*) c FROM invoices WHERE MONTH(CreatedAt)=$m AND YEAR(CreatedAt)=$y AND Status='Đã thanh toán'");
            $u = $conn->query("SELECT COUNT(*) c FROM invoices WHERE MONTH(CreatedAt)=$m AND YEAR(CreatedAt)=$y AND Status='Chưa thanh toán'");
            $sInvPaid[]   = (int)($p ? $p->fetch_assoc()['c'] : 0);
            $sInvUnpaid[] = (int)($u ? $u->fetch_assoc()['c'] : 0);
        }

        $sRoomLabels = []; $sRoomData = [];
        $rr = $conn->query("SELECT Status, COUNT(*) cnt FROM rooms GROUP BY Status");
        while ($r = $rr->fetch_assoc()) { $sRoomLabels[] = $r['Status']; $sRoomData[] = (int)$r['cnt']; }
        ?>
        <div class="dashboard-container" style="padding:0 30px 40px;">
            <h2 style="font-size:1.3rem; font-weight:700; color:var(--text); margin-bottom:18px; display:flex; align-items:center; gap:10px;">
                <span style="background:linear-gradient(135deg,#4361ee,#7209b7); width:34px; height:34px; border-radius:9px; display:inline-flex; align-items:center; justify-content:center; color:#fff; font-size:0.95rem; flex-shrink:0;">
                    <i class="fas fa-chart-bar"></i>
                </span> Biểu đồ phân tích
            </h2>
            <div style="display:grid; grid-template-columns:1fr 2fr 1fr; gap:18px;">

                <!-- Phân bổ vai trò -->
                <div style="background:var(--card); border:1px solid var(--stroke); border-radius:14px; padding:18px;">
                    <h3 style="font-size:0.9rem; font-weight:600; color:var(--text); margin:0 0 14px; display:flex; align-items:center; gap:7px;">
                        <i class="fas fa-users" style="color:#4361ee;"></i> Phân bổ vai trò
                    </h3>
                    <div style="height:200px; position:relative;"><canvas id="sRoleChart"></canvas></div>
                </div>

                <!-- Hóa đơn 5 tháng -->
                <div style="background:var(--card); border:1px solid var(--stroke); border-radius:14px; padding:18px;">
                    <h3 style="font-size:0.9rem; font-weight:600; color:var(--text); margin:0 0 14px; display:flex; align-items:center; gap:7px;">
                        <i class="fas fa-file-invoice" style="color:#10b981;"></i> Hóa đơn 5 tháng gần nhất
                    </h3>
                    <div style="height:200px; position:relative;"><canvas id="sInvChart"></canvas></div>
                </div>

                <!-- Trạng thái phòng -->
                <div style="background:var(--card); border:1px solid var(--stroke); border-radius:14px; padding:18px;">
                    <h3 style="font-size:0.9rem; font-weight:600; color:var(--text); margin:0 0 14px; display:flex; align-items:center; gap:7px;">
                        <i class="fas fa-building" style="color:#f59e0b;"></i> Tình trạng phòng
                    </h3>
                    <div style="height:200px; position:relative;"><canvas id="sRoomChart"></canvas></div>
                </div>
            </div>
        </div>
        <!-- END CHARTS -->
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.stat-card').forEach((el, i) => {
                el.style.animationDelay = `${i * 0.1}s`;
            });

            const isDark = () => document.body.getAttribute('data-theme') === 'dark';
            const gridC  = () => isDark() ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)';
            const tickC  = () => isDark() ? '#94a3b8' : '#6b7280';
            const legC   = () => isDark() ? '#e2e8f0' : '#374151';
            const tip    = { backgroundColor: isDark() ? '#1e293b' : '#fff', titleColor: isDark() ? '#e2e8f0' : '#111', bodyColor: isDark() ? '#94a3b8' : '#555', borderColor: isDark() ? '#334155' : '#e5e7eb', borderWidth: 1, padding: 10, cornerRadius: 8 };

            Chart.defaults.font.family = "'Inter', 'Segoe UI', sans-serif";

            // 1. Role polar/pie chart
            new Chart(document.getElementById('sRoleChart').getContext('2d'), {
                type: 'pie',
                data: {
                    labels: ['Admin', 'Manager', 'Student'],
                    datasets: [{ data: [<?= $sChartRoles['Admin'] ?>, <?= $sChartRoles['Manager'] ?>, <?= $sChartRoles['Student'] ?>], backgroundColor: ['#4361ee', '#7c3aed', '#10b981'], borderWidth: 0, hoverOffset: 8 }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom', labels: { color: legC(), padding: 10, font: { size: 11 } } }, tooltip: tip }
                }
            });

            // 2. Invoice grouped bar
            new Chart(document.getElementById('sInvChart').getContext('2d'), {
                type: 'bar',
                data: {
                    labels: <?= json_encode($sInvLabels) ?>,
                    datasets: [
                        { label: 'Đã thanh toán', data: <?= json_encode($sInvPaid) ?>,   backgroundColor: '#10b981', borderRadius: 6 },
                        { label: 'Chưa TT',        data: <?= json_encode($sInvUnpaid) ?>, backgroundColor: '#f59e0b', borderRadius: 6 }
                    ]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { position: 'top', labels: { color: legC(), font: { size: 11 } } }, tooltip: tip },
                    scales: {
                        x: { grid: { display: false }, ticks: { color: tickC(), font: { size: 10 } } },
                        y: { grid: { color: gridC() }, ticks: { color: tickC(), precision: 0 } }
                    }
                }
            });

            // 3. Room doughnut
            new Chart(document.getElementById('sRoomChart').getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: <?= json_encode($sRoomLabels) ?>,
                    datasets: [{ data: <?= json_encode($sRoomData) ?>, backgroundColor: ['#4361ee','#10b981','#f59e0b','#6b7280','#ef4444'], borderWidth: 0, hoverOffset: 6 }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    cutout: '62%',
                    plugins: { legend: { position: 'bottom', labels: { color: legC(), padding: 8, font: { size: 11 } } }, tooltip: tip }
                }
            });
        });
    </script>
</body>
</html>