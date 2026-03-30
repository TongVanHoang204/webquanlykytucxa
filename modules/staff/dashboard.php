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
    <div class="bento-dashboard-container">
        <!-- 1. Hero / Welcome Bento -->
        <div class="bento-welcome gradient-flare">
            <div class="welcome-text">
                <h1><i class="fa-solid fa-chart-line"></i> Dashboard Manager</h1>
                <p>Khám phá tình trạng hiện tại của hệ thống. Dưới đây là các chỉ số theo thời gian thực.</p>
            </div>
            <div class="welcome-quick-stats">
                <div class="wqs-item">
                    <div class="wqs-icon"><i class="fas fa-user-shield"></i></div>
                    <div class="wqs-info">
                        <span class="number"><?= $adminCount ?></span>
                        <span class="label">Admin</span>
                    </div>
                </div>
                <div class="wqs-item">
                    <div class="wqs-icon"><i class="fas fa-user-tie"></i></div>
                    <div class="wqs-info">
                        <span class="number"><?= $managerCount ?></span>
                        <span class="label">Manager</span>
                    </div>
                </div>
                <div class="wqs-item">
                    <div class="wqs-icon"><i class="fas fa-user-graduate"></i></div>
                    <div class="wqs-info">
                        <span class="number"><?= $studentCount ?></span>
                        <span class="label">Student</span>
                    </div>
                </div>
            </div>
            <div class="flare-effect"></div>
        </div>

        <!-- 2. Thống kê tổng quan (Metrics Grid) -->
        <div class="bento-metrics">
            <div class="bento-metric-card glow-amber">
                <div class="icon-wrapper">
                    <i class="fa-solid fa-users"></i>
                </div>
                <div class="metric-content">
                    <h3>Tổng người dùng</h3>
                    <div class="metric-number"><?= ($adminCount + $managerCount + $studentCount) ?></div>
                    <span class="trend trend-warning"><i class="fa-solid fa-chart-bar"></i> Hoạt động: <?= $activeUsers ?></span>
                </div>
            </div>

            <div class="bento-metric-card glow-blue">
                <div class="icon-wrapper">
                    <i class="fa-solid fa-building"></i>
                </div>
                <div class="metric-content">
                    <h3>Cơ sở vật chất</h3>
                    <div class="metric-number"><?= $totalRooms ?></div>
                    <span class="trend trend-primary"><i class="fas fa-key"></i> Đã thuê: <?= $occupiedRooms ?> | Trống: <?= $availableRooms ?></span>
                </div>
            </div>

            <div class="bento-metric-card glow-fuchsia">
                <div class="icon-wrapper">
                    <i class="fa-solid fa-bullhorn"></i>
                </div>
                <div class="metric-content">
                    <h3>Thông báo</h3>
                    <div class="metric-number"><?= $totalAnnouncements ?></div>
                    <span class="trend trend-info"><i class="fas fa-calendar-day"></i> Hôm nay: <?= $todayAnnouncements ?></span>
                </div>
            </div>

            <div class="bento-metric-card glow-emerald">
                <div class="icon-wrapper">
                    <i class="fas fa-user-clock"></i>
                </div>
                <div class="metric-content">
                    <h3>Tài khoản tạm ngừng</h3>
                    <div class="metric-number"><?= $inactiveUsers ?></div>
                    <span class="trend trend-success"><i class="fas fa-bed"></i> Không hoạt động</span>
                </div>
            </div>
        </div>

        <!-- 3. Modules & Data Grid -->
        <div class="bento-modules">
            <!-- Thao tác nhanh (Span 1) -->
            <div class="bento-module-column">
                <div class="bento-module-card quick-actions glassmorphism">
                    <div class="module-header glass-header">
                        <div class="header-icon"><i class="fas fa-bolt"></i></div>
                        <h2>Thao Tác Nhanh</h2>
                    </div>
                    <div class="module-list-links">
                        <a href="<?= $base ?>modules/staff/users/users.php" class="list-item hover-glass"><i class="fa-solid fa-user"></i> Người dùng</a>
                        <a href="<?= $base ?>modules/staff/students/student_list.php" class="list-item hover-glass"><i class="fa-solid fa-user-graduate"></i> Sinh viên</a>
                        <a href="<?= $base ?>modules/staff/rooms/rooms.php" class="list-item hover-glass"><i class="fa-solid fa-bed"></i> Kho khoá phòng</a>
                        <a href="<?= $base ?>modules/staff/invoices/invoice_list.php" class="list-item hover-glass"><i class="fa-solid fa-file-invoice"></i> Quản trị thu/chi</a>
                        <a href="<?= $base ?>modules/staff/contract/contract_list.php" class="list-item hover-glass"><i class="fa-solid fa-file-signature"></i> Hợp đồng lưu trú</a>
                        <a href="<?= $base ?>modules/staff/announcements/announcement_list.php" class="list-item hover-glass"><i class="fa-solid fa-list"></i> Bảng thông báo</a>
                        <a href="<?= $base ?>modules/staff/feedbacks/feedback_list.php" class="list-item hover-glass"><i class="fa-solid fa-comments"></i> Hỗ trợ sinh viên</a>
                        <a href="<?= $base ?>modules/staff/room_requests/staff_request_list.php" class="list-item hover-glass"><i class="fas fa-clipboard-check"></i> Đơn đăng ký</a>
                    </div>
                </div>
            </div>

            <!-- Bảng danh sách User tích hợp (Span 3) -->
            <div class="bento-module-card table-card span-col-3">
                <div class="module-header table-header">
                    <div class="title-group">
                        <div class="header-icon"><i class="fas fa-users-viewfinder"></i></div>
                        <h2>Danh Sách Người Dùng</h2>
                    </div>
                    <form class="search-filter-form bento-search" method="get">
                        <div class="search-box">
                            <i class="fa-solid fa-search"></i>
                            <input type="text" name="q" placeholder="Tên, email..." value="<?= htmlspecialchars($q) ?>">
                        </div>
                        <select name="role" class="filter-select">
                            <option value="">Vai trò</option>
                            <option value="Admin" <?= $roleFilter === 'Admin'   ? 'selected' : '' ?>>Admin</option>
                            <option value="Manager" <?= $roleFilter === 'Manager' ? 'selected' : '' ?>>Manager</option>
                            <option value="Student" <?= $roleFilter === 'Student' ? 'selected' : '' ?>>Student</option>
                        </select>
                        <select name="status" class="filter-select">
                            <option value="">Trạng thái</option>
                            <option value="1" <?= $statusFilter === '1' ? 'selected' : '' ?>>Hoạt động</option>
                            <option value="0" <?= $statusFilter === '0' ? 'selected' : '' ?>>Ngừng</option>
                        </select>
                        <button type="submit" class="bento-btn mini-btn"><i class="fa-solid fa-filter"></i> Lọc</button>
                        <?php if ($roleFilter || $statusFilter || $q): ?>
                            <a class="bento-btn mini-btn ghost" href="?"><i class="fa-solid fa-rotate-left"></i> Xóa</a>
                        <?php endif; ?>
                    </form>
                </div>
                <div class="table-responsive bento-table-wrapper">
                    <table class="bento-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Tên Đăng Nhập</th>
                                <th>Họ Tên</th>
                                <th>Email</th>
                                <th>Phân Quyền</th>
                                <th>Trạng Thái</th>
                                <th>Ngày Tạo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($list && $list->num_rows > 0): ?>
                                <?php while ($row = $list->fetch_assoc()): ?>
                                    <tr>
                                        <td>#<?= htmlspecialchars($row['UserID']) ?></td>
                                        <td><span class="fw-600"><?= htmlspecialchars($row['Username']) ?></span></td>
                                        <td><?= htmlspecialchars($row['FullName']) ?></td>
                                        <td><?= htmlspecialchars($row['Email']) ?></td>
                                        <td>
                                            <span class="role-badge role-<?= strtolower($row['Role']) ?>">
                                                <?= htmlspecialchars($row['Role']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($row['IsActive']): ?>
                                                <span class="status-badge active"><i class="fa-solid fa-circle-check"></i> Hoạt động</span>
                                            <?php else: ?>
                                                <span class="status-badge inactive"><i class="fa-solid fa-circle-xmark"></i> Ngừng</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-muted"><?= date('d/m/Y', strtotime($row['CreatedAt'])) ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center empty-cell">
                                        <div class="empty-state">
                                            <i class="fa-regular fa-folder-open"></i>
                                            <p>Không tìm thấy người dùng nào phù hợp với bộ lọc.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Number increment animation for metrics
            const metricNumbers = document.querySelectorAll('.metric-number, .wqs-info .number');
            metricNumbers.forEach(el => {
                const text = el.textContent.trim();
                const targetNum = parseInt(text.replace(/[^0-9]/g, ''), 10);
                if(isNaN(targetNum) || targetNum === 0) return;
                
                let cur = 0;
                const duration = 1500; // ms
                const interval = 30; // ms
                const step = Math.max(1, targetNum / (duration / interval));
                
                const timer = setInterval(() => {
                    cur += step;
                    if (cur >= targetNum) {
                        cur = targetNum;
                        clearInterval(timer);
                    }
                    el.textContent = Math.floor(cur);
                }, interval);
            });
            
            // Mouse move flare effect on welcome card
            const banner = document.querySelector('.bento-welcome');
            const flare = document.querySelector('.flare-effect');
            
            if(banner && flare) {
                banner.addEventListener('mousemove', (e) => {
                    const rect = banner.getBoundingClientRect();
                    const x = e.clientX - rect.left;
                    const y = e.clientY - rect.top;
                    flare.style.background = `radial-gradient(circle at ${x}px ${y}px, rgba(255,255,255,0.15) 0%, transparent 50%)`;
                });
            }
        });
    </script>
</body>

</html>