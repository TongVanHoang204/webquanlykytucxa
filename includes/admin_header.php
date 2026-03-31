<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/log_helper.php';
require_once __DIR__ . '/error_handler.php';

$userName = $_SESSION['FullName'] ?? 'Quản trị viên';
$userAvatar = $_SESSION['Avatar'] ?? null;
$userRole = $_SESSION['Role'] ?? 'Staff';
$pageTitle = $pageTitle ?? 'Trang quản trị - Ký túc xá';
$pageBodyClass = trim('app-shell app-shell--admin ' . ($pageBodyClass ?? 'theme-auto'));
$pageStylesheets = $pageStylesheets ?? [];

$base = '/';
if (strpos($_SERVER['REQUEST_URI'] ?? '', '/WEBQuanLyKyTucXa') === 0) {
    $base = '/WEBQuanLyKyTucXa/';
}

if (!$userAvatar && isset($_SESSION['UserID']) && isset($conn) && $conn instanceof mysqli) {
    try {
        $userId = (int) $_SESSION['UserID'];
        $stmt = $conn->prepare('SELECT Avatar FROM Students WHERE UserID = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result instanceof mysqli_result && ($row = $result->fetch_assoc())) {
                $userAvatar = $row['Avatar'] ?? null;
                if ($userAvatar) {
                    $_SESSION['Avatar'] = $userAvatar;
                }
            }
            $stmt->close();
        }
    } catch (Throwable $e) {
        // Header should never fatal because avatar lookup fails.
    }
}

$avatarPath = $base . 'assets/img/avatars/user.png';
if (!empty($userAvatar)) {
    $avatarPath = preg_match('/^https?:\/\//', $userAvatar)
        ? $userAvatar
        : $base . ltrim((string) $userAvatar, '/');
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>

    <link rel="stylesheet" href="<?= $base ?>assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="<?= $base ?>assets/vendor/fonts/fonts.css">
    <link rel="stylesheet" href="<?= $base ?>assets/css/global.css">
    <link rel="stylesheet" href="<?= $base ?>assets/css/admin/admin_header.css">
    <link rel="stylesheet" href="<?= $base ?>assets/css/admin/chat_ai_widget.css">
    <link rel="stylesheet" href="<?= $base ?>assets/css/app_shell.css">
    <?php foreach ($pageStylesheets as $stylesheet): ?>
        <link rel="stylesheet" href="<?= htmlspecialchars(preg_match('/^(https?:)?\/\//', $stylesheet) ? $stylesheet : $base . ltrim($stylesheet, '/')) ?>">
    <?php endforeach; ?>

    <script src="<?= $base ?>assets/js/admin_header.js" defer></script>
    <?php if ($userRole === 'Admin'): ?>
        <script src="<?= $base ?>assets/js/admin_chat_ai_widget.js" defer></script>
    <?php endif; ?>
</head>

<body class="<?= htmlspecialchars($pageBodyClass) ?>" data-base="<?= htmlspecialchars($base, ENT_QUOTES) ?>">
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
            <a href="<?= $base ?>modules/staff/communications/notification_center.php">
                <i class="fas fa-bell"></i> Thông báo
            </a>
            <a href="<?= $base ?>modules/staff/communications/mass_email.php">
                <i class="fas fa-envelope-open-text"></i> Mass Email
            </a>
            <a href="<?= $base ?>modules/staff/report/report_hub.php">
                <i class="fas fa-file-export"></i> Export báo cáo
            </a>
            <a href="<?= $base ?>modules/staff/access/gate_scanner.php">
                <i class="fas fa-qrcode"></i> Quét cổng
            </a>
            <a href="<?= $base ?>modules/staff/access/gate_logs.php">
                <i class="fas fa-door-open"></i> Nhật ký cổng
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
            <a href="<?= $base ?>index.php">
                <i class="fas fa-house-user"></i> Trang chủ User
            </a>
        </nav>
    </aside>
    <div class="drawer-overlay"></div>

    <header class="admin-header">
        <div class="left">
            <button class="hamburger" aria-label="Mở menu">
                <span></span><span></span><span></span>
            </button>

            <a href="<?= $base ?>modules/staff/dashboard.php" class="logo">
                <i class="fas fa-building"></i>
                <span>Ký túc xá</span>
            </a>

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
                <a href="<?= $base ?>modules/staff/communications/notification_center.php" data-path="modules/staff/communications/notification_center.php">
                    <i class="fas fa-bell"></i>
                    <span>Thông báo</span>
                </a>
                <a href="<?= $base ?>modules/staff/communications/mass_email.php" data-path="modules/staff/communications/mass_email.php">
                    <i class="fas fa-envelope-open-text"></i>
                    <span>Email</span>
                </a>
                <a href="<?= $base ?>modules/staff/report/report_hub.php" data-path="modules/staff/report/report_hub.php">
                    <i class="fas fa-file-export"></i>
                    <span>Report</span>
                </a>
                <a href="<?= $base ?>modules/staff/access/gate_scanner.php" data-path="modules/staff/access/gate_scanner.php">
                    <i class="fas fa-qrcode"></i>
                    <span>Quét cổng</span>
                </a>
                <a href="<?= $base ?>modules/staff/access/gate_logs.php" data-path="modules/staff/access/gate_logs.php">
                    <i class="fas fa-door-open"></i>
                    <span>Log cổng</span>
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
                <a href="<?= $base ?>index.php" data-path="index.php">
                    <i class="fas fa-house-user"></i>
                    <span>Home</span>
                </a>

                <div class="nav-underline"></div>
            </nav>
        </div>

        <div class="right">
            <?php if ($userRole === 'Admin'): ?>
                <button id="adminChatAiToggle" class="chat-ai-toggle" title="Trợ lý AI" aria-label="Trợ lý AI">
                    <i class="fas fa-robot"></i>
                </button>
            <?php endif; ?>

            <button class="theme-toggle" aria-label="Chế độ tối/sáng">
                <i class="fa-regular fa-sun"></i>
                <i class="fa-regular fa-moon"></i>
            </button>

            <div class="notify">
                <button class="notify-btn" aria-label="Thông báo">
                    <i class="fa-regular fa-bell"></i>
                    <span class="dot" hidden></span>
                </button>
                <div class="notify-menu">
                    <div class="notify-head">
                        <strong>Thông báo</strong>
                        <button class="mark-read" type="button">Đánh dấu đã đọc</button>
                    </div>
                    <ul class="notify-list">
                        <li class="empty">Đang tải...</li>
                    </ul>
                </div>
            </div>

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

    <main class="app-main app-main--admin">
