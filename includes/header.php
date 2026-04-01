<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/log_helper.php';
require_once __DIR__ . '/error_handler.php';

$isLoggedIn = isset($_SESSION['UserID']);
$role = $_SESSION['Role'] ?? 'Guest';
$displayName = $_SESSION['FullName'] ?? $_SESSION['Username'] ?? 'Tài khoản';
$pageTitle = $pageTitle ?? 'Ký túc xá Sinh viên';
$pageBodyClass = trim('app-shell app-shell--user ' . ($pageBodyClass ?? ''));
$pageStylesheets = $pageStylesheets ?? [];

$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$base = '/';
if (strpos($_SERVER['REQUEST_URI'] ?? '', '/WEBQuanLyKyTucXa') === 0) {
    $base = '/WEBQuanLyKyTucXa/';
}

function nav_active($path)
{
    global $currentPath;
    return $currentPath === $path ? 'active' : '';
}

$avatarUrl = '/assets/img/avatars/user.png';
if (!empty($_SESSION['UserID'])) {
    $userId = (int) $_SESSION['UserID'];
    if (!empty($_SESSION['Avatar'])) {
        $avatarUrl = $_SESSION['Avatar'];
    } elseif (isset($conn) && $conn instanceof mysqli) {
        $stmt = $conn->prepare('SELECT Avatar FROM Students WHERE UserID = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result instanceof mysqli_result && ($row = $result->fetch_assoc()) && !empty($row['Avatar'])) {
                $avatarUrl = $row['Avatar'];
                $_SESSION['Avatar'] = $avatarUrl;
            }
            $stmt->close();
        }
    }
}

$hasRoom = false;
if ($isLoggedIn && $role === 'Student' && isset($conn) && $conn instanceof mysqli) {
    $userId = (int) $_SESSION['UserID'];
    $checkRoom = $conn->query(
        "SELECT c.ContractID
         FROM Contracts c
         INNER JOIN Students s ON c.StudentID = s.StudentID
         WHERE s.UserID = {$userId} AND c.Status = 'Hiệu lực'
         LIMIT 1"
    );
    if ($checkRoom instanceof mysqli_result && $checkRoom->num_rows > 0) {
        $hasRoom = true;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>

    <link rel="stylesheet" href="<?= $base ?>assets/css/global.css">
    <link rel="stylesheet" href="<?= $base ?>assets/css/header.css">
    <link rel="stylesheet" href="<?= $base ?>assets/css/footer.css">
    <link rel="stylesheet" href="<?= $base ?>assets/css/index.css">
    <link rel="stylesheet" href="<?= $base ?>assets/css/app_shell.css">
    <?php foreach ($pageStylesheets as $stylesheet): ?>
        <link rel="stylesheet" href="<?= htmlspecialchars(preg_match('/^(https?:)?\/\//', $stylesheet) ? $stylesheet : $base . ltrim($stylesheet, '/')) ?>">
    <?php endforeach; ?>

    <link rel="stylesheet" href="<?= $base ?>assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="<?= $base ?>assets/vendor/fonts/fonts.css">
    <script src="<?= $base ?>assets/js/user_header.js" defer></script>
</head>

<body<?= $pageBodyClass !== '' ? ' class="' . htmlspecialchars($pageBodyClass) . '"' : '' ?> data-base="<?= htmlspecialchars($base, ENT_QUOTES) ?>">
    <header class="admin-header">
        <div class="left">
            <a href="<?= $base ?>index.php" class="logo">
                <i class="fas fa-building"></i>
                <span>Ký túc xá Sinh viên</span>
            </a>

            <button class="hamburger" id="hamburgerBtn" type="button" aria-label="Menu">
                <span></span><span></span><span></span>
            </button>
        </div>

        <nav class="admin-nav" id="adminNav">
            <a href="<?= $base ?>index.php" class="<?= nav_active($base . 'index.php') ?>">
                <i class="fas fa-house"></i>
                <span>Trang chủ</span>
            </a>
            <a href="<?= $base ?>modules/user/rooms/register_room.php" class="<?= nav_active($base . 'modules/user/rooms/register_room.php') ?>">
                <i class="fas fa-house-user"></i>
                <span>Danh sách phòng</span>
            </a>
            <a href="<?= $base ?>modules/user/dashboard.php" class="<?= nav_active($base . 'modules/user/dashboard.php') ?>">
                <i class="fas fa-user-graduate"></i>
                <span>Trang sinh viên</span>
            </a>
            <a href="<?= $base ?>modules/user/accesslogs.php" class="<?= nav_active($base . 'modules/user/accesslogs.php') ?>">
                <i class="fas fa-newspaper"></i>
                <span>Bảng tin KTX</span>
            </a>
            <a href="<?= $base ?>modules/user/community/community_list.php" class="<?= nav_active($base . 'modules/user/community/community_list.php') ?>">
                <i class="fas fa-store"></i>
                <span>Chợ KTX</span>
            </a>
            <a href="<?= $base ?>modules/user/forum/index.php" class="<?= nav_active($base . 'modules/user/forum/index.php') ?>">
                <i class="fas fa-users"></i>
                <span>Cá»™ng Ä‘á»“ng SV</span>
            </a>
            <a href="<?= $base ?>modules/user/rooms/rooms.php" class="<?= nav_active($base . 'modules/user/rooms/rooms.php') ?>">
                <i class="fas fa-bed"></i>
                <span>Phòng đang ở</span>
            </a>
            <a href="<?= $base ?>modules/user/maintenance/index.php" class="<?= nav_active($base . 'modules/user/maintenance/index.php') ?>">
                <i class="fas fa-tools"></i>
                <span>Báº£o trÃ¬</span>
            </a>
            <a href="<?= $base ?>modules/user/packages/index.php" class="<?= nav_active($base . 'modules/user/packages/index.php') ?>">
                <i class="fas fa-box-open"></i>
                <span>BÆ°u pháº©m</span>
            </a>
            <a href="<?= $base ?>modules/user/access_card/index.php" class="<?= nav_active($base . 'modules/user/access_card/index.php') ?>">
                <i class="fas fa-id-card"></i>
                <span>Tháº» ra/vÃ o</span>
            </a>
            <a href="<?= $base ?>modules/user/feedbacks.php" class="<?= nav_active($base . 'modules/user/feedbacks.php') ?>">
                <i class="fas fa-comments"></i>
                <span>Phản ánh</span>
            </a>
            <?php if ($role === 'Student'): ?>
                <a href="<?= $base ?>modules/user/access_qr.php" class="<?= nav_active($base . 'modules/user/access_qr.php') ?>">
                    <i class="fas fa-qrcode"></i>
                    <span>Mã QR cổng</span>
                </a>
                <a href="<?= $base ?>modules/user/gate_history.php" class="<?= nav_active($base . 'modules/user/gate_history.php') ?>">
                    <i class="fas fa-clock-rotate-left"></i>
                    <span>Lịch sử ra/vào</span>
                </a>
            <?php endif; ?>

            <?php if (!$hasRoom && $role === 'Student'): ?>
                <a href="<?= $base ?>modules/user/room_requests/request_list.php" class="<?= nav_active($base . 'modules/user/room_requests/request_list.php') ?>">
                    <i class="fas fa-hand-pointer"></i>
                    <span>Yêu cầu của tôi</span>
                </a>
            <?php endif; ?>
        </nav>

        <div class="right">
            <button id="themeToggle" class="theme-toggle" type="button" aria-label="Đổi giao diện">
                <i class="fas fa-sun"></i>
                <i class="fas fa-moon"></i>
            </button>

            <div class="notify">
                <button class="notify-btn" aria-label="Thông báo">
                    <i class="fa-regular fa-bell"></i>
                    <span class="dot" hidden></span>
                </button>

                <div class="notify-menu">
                    <div class="notify-head">
                        <strong>Thông báo</strong>
                        <button class="mark-read">Đã đọc tất cả</button>
                    </div>

                    <ul class="notify-list">
                        <li class="empty">Chưa có thông báo mới</li>
                    </ul>

                    <div class="notify-footer">
                        <a href="<?= $base ?>modules/user/notifications.php" class="view-all">Xem tất cả</a>
                    </div>
                </div>
            </div>

            <button id="chatAiToggle" class="chat-ai-button" aria-label="Chat AI">
                <i class="fas fa-robot"></i>
            </button>

            <div class="admin-profile">
                <?php if ($isLoggedIn): ?>
                    <button class="profile-btn" type="button" id="profileBtn">
                        <img src="<?= htmlspecialchars($avatarUrl) ?>" alt="Avatar" onerror="this.onerror=null;this.src='<?= $base ?>assets/img/avatars/user.png';">
                        <span class="name"><?= htmlspecialchars($displayName) ?></span>
                        <span class="caret"><i class="fas fa-chevron-down"></i></span>
                    </button>

                    <div class="dropdown-menu" id="profileMenu">
                        <?php if (in_array($role, ['Admin', 'Manager'], true)): ?>
                            <a href="<?= $base ?>modules/staff/dashboard.php">
                                <i class="fas fa-gauge"></i>
                                <span>Trang quản trị</span>
                            </a>
                        <?php endif; ?>
                        <a href="<?= $base ?>modules/user/UserProfile/profile.php">
                            <i class="fas fa-id-card"></i>
                            <span>Hồ sơ cá nhân</span>
                        </a>
                        <a href="<?= $base ?>logout.php">
                            <i class="fas fa-right-from-bracket"></i>
                            <span>Đăng xuất</span>
                        </a>
                    </div>
                <?php else: ?>
                    <a href="<?= $base ?>login.php" class="profile-btn" style="text-decoration:none;">
                        <i class="fas fa-right-to-bracket"></i>
                        <span class="name">Đăng nhập</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <nav class="drawer" id="mobileDrawer">
        <div class="drawer-nav">
            <a href="<?= $base ?>index.php">
                <i class="fas fa-house"></i><span>Trang chủ</span>
            </a>
            <a href="<?= $base ?>modules/user/dashboard.php">
                <i class="fas fa-user-graduate"></i><span>Trang sinh viên</span>
            </a>
            <a href="<?= $base ?>modules/user/accesslogs.php">
                <i class="fas fa-newspaper"></i><span>Bảng tin KTX</span>
            </a>
            <a href="<?= $base ?>modules/user/community/community_list.php">
                <i class="fas fa-store"></i><span>Chợ KTX</span>
            </a>
            <a href="<?= $base ?>modules/user/forum/index.php">
                <i class="fas fa-users"></i><span>Cá»™ng Ä‘á»“ng SV</span>
            </a>
            <a href="<?= $base ?>modules/user/rooms/rooms.php">
                <i class="fas fa-bed"></i><span>Phòng ở</span>
            </a>
            <a href="<?= $base ?>modules/user/maintenance/index.php">
                <i class="fas fa-tools"></i><span>Báº£o trÃ¬</span>
            </a>
            <a href="<?= $base ?>modules/user/packages/index.php">
                <i class="fas fa-box-open"></i><span>BÆ°u pháº©m</span>
            </a>
            <a href="<?= $base ?>modules/user/access_card/index.php">
                <i class="fas fa-id-card"></i><span>Tháº» ra/vÃ o</span>
            </a>
            <a href="<?= $base ?>modules/user/feedbacks.php">
                <i class="fas fa-comments"></i><span>Phản ánh</span>
            </a>
            <?php if ($role === 'Student'): ?>
                <a href="<?= $base ?>modules/user/access_qr.php">
                    <i class="fas fa-qrcode"></i><span>Mã QR cổng</span>
                </a>
                <a href="<?= $base ?>modules/user/gate_history.php">
                    <i class="fas fa-clock-rotate-left"></i><span>Lịch sử ra/vào</span>
                </a>
            <?php endif; ?>

            <?php if (!$hasRoom && $role === 'Student'): ?>
                <a href="<?= $base ?>modules/user/room_requests/request_list.php">
                    <i class="fas fa-hand-pointer"></i><span>Yêu cầu của tôi</span>
                </a>
            <?php endif; ?>

            <?php if (!$isLoggedIn): ?>
                <a href="<?= $base ?>login.php">
                    <i class="fas fa-right-to-bracket"></i><span>Đăng nhập</span>
                </a>
            <?php else: ?>
                <a href="<?= $base ?>logout.php">
                    <i class="fas fa-right-from-bracket"></i><span>Đăng xuất</span>
                </a>
            <?php endif; ?>
        </div>
    </nav>

    <div class="chat-ai-widget">
        <div class="chat-ai-window" id="chatAiWindow">
            <div class="chat-ai-header">
                <div class="chat-ai-title">
                    <i class="fas fa-robot"></i>
                    <span>Trợ lý AI KTX</span>
                </div>
            </div>

            <div class="chat-ai-messages" id="chatAiMessages">
                <div class="chat-ai-message bot">
                    <div class="chat-ai-avatar">
                        <i class="fas fa-robot"></i>
                    </div>
                    <div class="chat-ai-bubble">
                        <p>Xin chào! Tôi là trợ lý AI của Ký túc xá. Tôi có thể giúp gì cho bạn?</p>
                        <div class="chat-ai-suggestions">
                            <button class="chat-ai-suggestion" data-question="Làm sao để đăng ký phòng?">Cách đăng ký phòng</button>
                            <button class="chat-ai-suggestion" data-question="Giá phòng bao nhiêu?">Giá phòng</button>
                            <button class="chat-ai-suggestion" data-question="Quy định ký túc xá?">Nội quy KTX</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="chat-ai-input">
                <input type="text" id="chatAiInput" placeholder="Nhập câu hỏi của bạn...">
                <button id="chatAiSend">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
        </div>
    </div>

    <script src="<?= $base ?>assets/js/chat_ai_widget.js"></script>
    <script>
        const body = document.body;
        const themeToggle = document.getElementById('themeToggle');
        const savedTheme = localStorage.getItem('ktx-theme') || 'dark';
        body.setAttribute('data-theme', savedTheme);

        if (themeToggle) {
            themeToggle.addEventListener('click', () => {
                const current = body.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
                body.setAttribute('data-theme', current);
                localStorage.setItem('ktx-theme', current);
            });
        }

        const profileBtn = document.getElementById('profileBtn');
        const profileMenu = document.getElementById('profileMenu');
        if (profileBtn && profileMenu) {
            profileBtn.addEventListener('click', (event) => {
                event.stopPropagation();
                profileMenu.classList.toggle('show');
            });

            document.addEventListener('click', () => {
                profileMenu.classList.remove('show');
            });
        }

        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const mobileDrawer = document.getElementById('mobileDrawer');
        if (hamburgerBtn && mobileDrawer) {
            hamburgerBtn.addEventListener('click', (event) => {
                event.stopPropagation();
                hamburgerBtn.classList.toggle('active');
                mobileDrawer.classList.toggle('open');
                body.classList.toggle('no-scroll');
            });

            document.addEventListener('click', (event) => {
                if (!mobileDrawer.contains(event.target) && !hamburgerBtn.contains(event.target)) {
                    hamburgerBtn.classList.remove('active');
                    mobileDrawer.classList.remove('open');
                    body.classList.remove('no-scroll');
                }
            });
        }
    </script>
