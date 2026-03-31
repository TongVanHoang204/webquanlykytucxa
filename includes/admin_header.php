<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/log_helper.php';
require_once __DIR__ . "/error_handler.php";

$userName = $_SESSION['FullName'] ?? 'Quản trị viên';
$userAvatar = $_SESSION['Avatar'] ?? null;
$userRole = $_SESSION['Role'] ?? 'Staff';
$pageTitle = $pageTitle ?? 'Trang quản trị - Ký túc xá';
$pageBodyClass = trim('app-shell app-shell--admin ' . ($pageBodyClass ?? 'theme-auto'));
$pageStylesheets = $pageStylesheets ?? [];

/* Configure Base Path */
$base = '/';
if (strpos($_SERVER['REQUEST_URI'], '/WEBQuanLyKyTucXa') === 0) {
    $base = '/WEBQuanLyKyTucXa/';
} 

// Fetch Avatar if missing
if (!$userAvatar && isset($_SESSION['UserID']) && isset($conn) && $conn instanceof mysqli) {
    try {
        $userId = (int)$_SESSION['UserID'];
        $stmt = $conn->prepare("SELECT Avatar FROM Students WHERE UserID = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $userAvatar = $row['Avatar'];
                $_SESSION['Avatar'] = $userAvatar;
            }
            $stmt->close();
        }
    } catch (Exception $e) {
        // Silent fail for header
    }
}

// Final Avatar Path
$avatarPath = $base . 'assets/img/avatars/user.png';
if ($userAvatar && !empty($userAvatar)) {
    if (preg_match('/^https?:\/\//', $userAvatar)) {
        $avatarPath = $userAvatar;
    } else {
        $avatarPath = $base . ltrim($userAvatar, '/');
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= htmlspecialchars($pageTitle) ?></title>

    <!-- Fonts & Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Core CSS -->
    <link rel="stylesheet" href="<?= $base ?>assets/css/global.css" />
    <link rel="stylesheet" href="<?= $base ?>assets/css/admin/admin_header.css" />
    <!-- Admin Chat AI CSS -->
    <link rel="stylesheet" href="<?= $base ?>assets/css/admin/chat_ai_widget.css" />
    <link rel="stylesheet" href="<?= $base ?>assets/css/app_shell.css" />
    <?php foreach ($pageStylesheets as $stylesheet): ?>
        <link rel="stylesheet" href="<?= htmlspecialchars(preg_match('/^(https?:)?\/\//', $stylesheet) ? $stylesheet : $base . ltrim($stylesheet, '/')) ?>" />
    <?php endforeach; ?>

    <!-- Scripts -->
    <script src="<?= $base ?>assets/js/admin_header.js" defer></script>
    <?php if ($userRole === 'Admin'): ?>
        <script src="<?= $base ?>assets/js/admin_chat_ai_widget.js" defer></script>
    <?php endif; ?>
</head>

<body class="<?= htmlspecialchars($pageBodyClass) ?>" data-base="<?= $base ?>">

    <!-- Mobile Drawer (Sidebar) -->
    <aside class="drawer">
        <div class="logo" style="margin-bottom: 20px;">
            <i class="fas fa-building"></i> <span>Ký túc xá</span>
        </div>
        <nav class="drawer-nav">
            <?php if ($userRole !== 'Manager'): ?>
                <a href="<?= $base ?>modules/admin/dashboard.php">
                    <i class="fas fa-gauge-high"></i> Dashboard Admin
                </a>
            <?php endif; ?>
            
            <a href="<?= $base ?>modules/staff/dashboard.php">
                <i class="fas fa-chart-line"></i> Dashboard Manager
            </a>

            <?php if ($userRole !== 'Manager'): ?>
                <a href="<?= $base ?>modules/staff/users/users.php">
                    <i class="fas fa-users"></i> Người dùng
                </a>
            <?php endif; ?>

            <a href="<?= $base ?>modules/staff/rooms/rooms.php">
                <i class="fas fa-bed"></i> Quản lý phòng
            </a>
            
            <a href="<?= $base ?>modules/staff/students/student_list.php">
                <i class="fas fa-user-graduate"></i> Sinh viên
            </a>

            <a href="<?= $base ?>modules/staff/payments/payment_list.php">
                <i class="fas fa-money-check-alt"></i> Thanh toán
            </a>

            <a href="<?= $base ?>modules/staff/utilities/utility_list.php">
                <i class="fas fa-bolt"></i> Chỉ số Điện Nước
            </a>

            <a href="<?= $base ?>modules/staff/maintenance/index.php">
                <i class="fas fa-tools"></i> Bảo trì & Sửa chữa
            </a>

            <a href="<?= $base ?>index.php">
                <i class="fas fa-house-user"></i> Trang chủ User
            </a>
        </nav>
    </aside>
    <div class="drawer-overlay"></div>

    <!-- Main Header -->
    <header class="admin-header">
        <div class="left">
            <!-- Mobile Toggle -->
            <button class="hamburger" aria-label="Mở menu">
                <span></span><span></span><span></span>
            </button>

            <!-- Logo -->
            <a href="<?= $base ?>modules/staff/dashboard.php" class="logo">
                <i class="fas fa-building"></i>
                <span>Ký túc xá</span>
            </a>

            <!-- Desktop Nav -->
            <nav class="admin-nav">
                <?php if ($userRole !== 'Manager'): ?>
                    <a href="<?= $base ?>modules/admin/dashboard.php" data-path="modules/admin/dashboard.php">
                        <i class="fas fa-gauge-high"></i>
                        <span>Admin</span>
                    </a>
                <?php endif; ?>
                
                <a href="<?= $base ?>modules/staff/dashboard.php" data-path="modules/staff/dashboard.php">
                    <i class="fas fa-chart-line"></i>
                    <span>Manager</span>
                </a>

                <?php if ($userRole !== 'Manager'): ?>
                    <a href="<?= $base ?>modules/staff/users/users.php" data-path="modules/staff/users/users.php">
                        <i class="fas fa-users"></i>
                        <span>Users</span>
                    </a>
                <?php endif; ?>

                <a href="<?= $base ?>modules/staff/rooms/rooms.php" data-path="modules/staff/rooms/rooms.php">
                    <i class="fas fa-bed"></i>
                    <span>Phòng</span>
                </a>

                <a href="<?= $base ?>modules/staff/students/student_list.php" data-path="modules/staff/students/student_list.php">
                     <i class="fas fa-user-graduate"></i>
                     <span>Sinh viên</span>
                </a>

                <a href="<?= $base ?>modules/staff/payments/payment_list.php" data-path="modules/staff/payments/payment_list.php">
                    <i class="fas fa-money-check-alt"></i>
                    <span>Thanh toán</span>
                </a>

                <a href="<?= $base ?>modules/staff/utilities/utility_list.php" data-path="modules/staff/utilities/utility_list.php">
                    <i class="fas fa-bolt"></i>
                    <span>Điện Nước</span>
                </a>

                <a href="<?= $base ?>modules/staff/maintenance/index.php" data-path="modules/staff/maintenance/index.php">
                    <i class="fas fa-tools"></i>
                    <span>Bảo trì</span>
                </a>

                <a href="<?= $base ?>index.php" data-path="index.php">
                    <i class="fas fa-house-user"></i>
                    <span>Home</span>
                </a>
                
                <div class="nav-underline"></div>
            </nav>
        </div>

        <div class="right">
            <!-- Admin Chat AI Toggle -->
            <?php if ($userRole === 'Admin'): ?>
                <div id="adminChatAiToggle" class="chat-ai-toggle" title="Trợ lý Admin">
                    <i class="fas fa-robot"></i>
                </div>
            <?php endif; ?>

            <!-- Theme Toggle -->
            <button class="theme-toggle" aria-label="Chế độ tối/sáng">
                <i class="fa-regular fa-sun"></i>
                <i class="fa-regular fa-moon"></i>
            </button>

            <!-- Notifications -->
            <div class="notify">
                <button class="notify-btn" aria-label="Thông báo">
                    <i class="fa-regular fa-bell"></i>
                    <span class="dot" hidden></span>
                </button>
                <div class="notify-menu">
                    <div class="notify-head">
                        <strong>Thông báo</strong>
                        <button class="mark-read">Đánh dấu đã đọc</button>
                    </div>
                    <ul class="notify-list">
                        <!-- JS renders items here -->
                        <li class="empty">Đang tải...</li>
                    </ul>
                </div>
            </div>

            <!-- Profile Dropdown -->
            <div class="admin-profile">
                <button class="profile-btn" aria-haspopup="true" aria-expanded="false">
                    <img src="<?= htmlspecialchars($avatarPath) ?>" alt="Avatar" onerror="this.src='<?= $base ?>assets/img/avatars/user.png'">
                    <span class="name"><?= htmlspecialchars($userName) ?></span>
                    <i class="fas fa-caret-down caret"></i>
                </button>
                <div class="dropdown-menu">
                    <a href="<?= $base ?>modules/user/UserProfile/profile.php">
                        <i class="fas fa-id-card"></i> Hồ sơ cá nhân
                    </a>
                    <?php if ($userRole === 'Admin'): ?>
                        <hr style="margin: 4px 0; border: 0; border-top: 1px solid var(--stroke);">
                        <a href="<?= $base ?>modules/admin/log.php">
                            <i class="fas fa-clipboard-list"></i> Chi tiết lỗi hệ thống
                        </a>
                    <?php endif; ?>
                    <hr style="margin: 4px 0; border: 0; border-top: 1px solid var(--stroke);">
                    <a href="<?= $base ?>logout.php" style="color: #ef4444;">
                        <i class="fas fa-sign-out-alt"></i> Đăng xuất
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Admin Chat AI Window -->
    <?php if ($userRole === 'Admin'): ?>
    <div id="adminChatAiWindow" class="chat-ai-window">
      <div class="chat-ai-header">
        <span><i class="fas fa-robot"></i> AI Trợ lý Admin</span>
        <button id="adminChatAiClose">&times;</button>
      </div>
      <div id="adminChatAiMessages" class="chat-ai-messages"></div>
      <div class="chat-ai-input-area">
        <input id="adminChatAiInput" type="text" placeholder="Hỏi AI: tổng quan, công nợ, phòng trống...">
        <button id="adminChatAiSend"><i class="fas fa-paper-plane"></i></button>
      </div>
    </div>
    <?php endif; ?>


    
    <!-- Main Content Wrapper (Optional, to be closed by footer or page) -->
    <main class="app-main app-main--admin">
