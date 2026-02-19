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
    </div>



    <script>
        // Animation thẻ thống kê
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.stat-card').forEach((el, i) => {
                el.style.animationDelay = `${i * 0.1}s`;
            });
        });
    </script>
</body>

</html>