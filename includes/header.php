<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Điều chỉnh đường dẫn require phù hợp với cấu trúc thư mục thực tế của bạn
require_once __DIR__ . '/../db_connect.php'; 
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/log_helper.php';
require_once __DIR__ . '/error_handler.php';

// Lấy thông tin user
$isLoggedIn  = isset($_SESSION['UserID']);
$role        = $_SESSION['Role'] ?? 'Guest';
$displayName = $_SESSION['FullName'] ?? $_SESSION['Username'] ?? 'Tài khoản';
$pageTitle = $pageTitle ?? 'Ký túc xá Sinh viên';
$pageBodyClass = trim('app-shell app-shell--user ' . ($pageBodyClass ?? ''));
$pageStylesheets = $pageStylesheets ?? [];

// Active menu logic
$currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

/* Configure Base Path */
$base = '/';
if (strpos($_SERVER['REQUEST_URI'], '/WEBQuanLyKyTucXa') === 0) {
    $base = '/WEBQuanLyKyTucXa/';
}

function nav_active($path)
{
    global $currentPath;
    return $currentPath === $path ? 'active' : '';
}

/* ========= Avatar từ DB / Session ========= */
$avatarUrl = '/assets/img/avatars/user.png';

if (!empty($_SESSION['UserID'])) {
    $userID = (int)$_SESSION['UserID'];

    if (!empty($_SESSION['Avatar'])) {
        $avatarUrl = $_SESSION['Avatar'];
    } elseif (isset($conn) && $conn instanceof mysqli) {
        $sqlAvt = "SELECT Avatar FROM Students WHERE UserID = ? LIMIT 1";
        if ($stAvt = $conn->prepare($sqlAvt)) {
            $stAvt->bind_param("i", $userID);
            $stAvt->execute();
            $avtRes = $stAvt->get_result();
            if ($avtRes && $rowAvt = $avtRes->fetch_assoc()) {
                if (!empty($rowAvt['Avatar'])) {
                    $avatarUrl = $rowAvt['Avatar'];
                    $_SESSION['Avatar'] = $avatarUrl;
                }
            }
            $stAvt->close();
        }
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

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body<?= $pageBodyClass !== '' ? ' class="' . htmlspecialchars($pageBodyClass) . '"' : '' ?>>
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
                <span>Bảng tin</span>
            </a>
            <a href="<?= $base ?>modules/user/rooms/rooms.php" class="<?= nav_active($base . 'modules/user/rooms/rooms.php') ?>">
                <i class="fas fa-bed"></i>
                <span>Phòng đang ở</span>
            </a>
            <a href="<?= $base ?>modules/user/feedbacks.php" class="<?= nav_active($base . 'modules/user/feedbacks.php') ?>">
                <i class="fas fa-comments"></i>
                <span>Phản ánh</span>
            </a>

            <?php
            // Chỉ hiển thị cho sinh viên chưa có phòng
            $hasRoom = false;
            if ($isLoggedIn && $role === 'Student' && isset($conn)) {
                $userID = (int)$_SESSION['UserID'];
                $checkRoom = $conn->query("SELECT c.ContractID FROM Contracts c INNER JOIN Students s ON c.StudentID = s.StudentID WHERE s.UserID = $userID AND c.Status = 'Hiệu lực' LIMIT 1");
                if ($checkRoom && $checkRoom->num_rows > 0) {
                    $hasRoom = true;
                }
            }
            if (!$hasRoom && $role === 'Student'):
            ?>
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
                        <img src="<?= htmlspecialchars($avatarUrl) ?>"
                            alt="Avatar"
                            onerror="this.onerror=null;this.src='<?= $base ?>assets/img/avatars/user.png';">
                        <span class="name"><?= htmlspecialchars($displayName) ?></span>
                        <span class="caret"><i class="fas fa-chevron-down"></i></span>
                    </button>

                    <div class="dropdown-menu" id="profileMenu">
                        <?php if (in_array($role, ['Admin', 'Manager'])): ?>
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
                <i class="fas fa-newspaper"></i><span>Bảng tin</span>
            </a>
            <a href="<?= $base ?>modules/user/rooms/rooms.php">
                <i class="fas fa-bed"></i><span>Phòng ở</span>
            </a>
            <a href="<?= $base ?>modules/user/feedbacks.php">
                <i class="fas fa-comments"></i><span>Phản ánh</span>
            </a>

            <?php if (!$hasRoom && $role === 'Student'): ?>
                <a href="<?= $base ?>modules/user/room_requests/request_list">
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
                <input type="text" id="chatAiInput" placeholder="Nhập câu hỏi của bạn..." />
                <button id="chatAiSend">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
        </div>
    </div>

    <script src="<?= $base ?>assets/js/chat_ai_widget.js"></script>
    <script>
        // ========== THEME TOGGLE ==========
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

        // ========== NOTIFY ==========
        document.addEventListener('DOMContentLoaded', () => {
            const wrap = document.querySelector('.notify');
            if (!wrap) return;

            const btn = wrap.querySelector('.notify-btn');
            const menu = wrap.querySelector('.notify-menu');
            const list = wrap.querySelector('.notify-list');
            const dot = wrap.querySelector('.dot');
            const markRead = wrap.querySelector('.mark-read');

            function formatTime(str) {
                const d = new Date(str);
                if (isNaN(d.getTime())) return str;
                return d.toLocaleString('vi-VN', {
                    hour: '2-digit',
                    minute: '2-digit',
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric'
                });
            }

            async function loadNotifications() {
                try {
                    const res = await fetch('<?= $base ?>modules/api/get_notifications.php', {
                        cache: 'no-store'
                    });
                    if (!res.ok) return;

                    const data = await res.json();
                    list.innerHTML = '';

                    if (!data || data.length === 0) {
                        list.innerHTML = '<li class="empty">Chưa có thông báo mới</li>';
                        dot.hidden = true;
                        return;
                    }

                    let unread = 0;
                    const maxDisplay = 5; // Hiển thị tối đa 5 thông báo
                    data.slice(0, maxDisplay).forEach(n => {
                        const isUnread = Number(n.IsRead) === 0;
                        if (isUnread) unread++;

                        const li = document.createElement('li');
                        li.className = 'notify-item' + (isUnread ? ' unread' : '');
                        li.setAttribute('data-type', n.Type || 'invoice');
                        li.innerHTML = `
                        <div class="notify-content">
                            <div class="notify-title">${n.Title}</div>
                            <div class="notify-message">${n.Message}</div>
                            <div class="notify-meta">
                                <span class="notify-time">${formatTime(n.CreatedAt)}</span>
                                ${n.Link ? `<button class="notify-view" type="button" onclick="window.location.href='${n.Link}'">Xem</button>` : ''}
                            </div>
                        </div>
                    `;
                        list.appendChild(li);
                    });

                    dot.hidden = unread === 0;
                } catch (e) {
                    console.error('Không tải được thông báo', e);
                }
            }

            async function markAllRead() {
                try {
                    const res = await fetch('<?= $base ?>modules/api/mark_read_notifications.php', {
                        method: 'POST'
                    });
                    const text = await res.text();
                    let data = null;
                    try {
                        data = JSON.parse(text);
                    } catch (e) {}

                    if (!res.ok || !data || data.ok !== true) return;

                    await loadNotifications();
                } catch (e) {
                    console.error('Mark read error', e);
                }
            }

            let opened = false;
            btn.addEventListener('click', async (e) => {
                e.stopPropagation();
                opened = !opened;
                menu.classList.toggle('show', opened);
                if (opened) {
                    await markAllRead();
                }
            });

            if (markRead) {
                markRead.addEventListener('click', (e) => {
                    e.stopPropagation();
                    markAllRead();
                });
            }

            document.addEventListener('click', (e) => {
                if (!wrap.contains(e.target)) {
                    menu.classList.remove('show');
                    opened = false;
                }
            });

            loadNotifications();
            // Tự động reload thông báo mỗi 30s
            setInterval(loadNotifications, 30000);
        });

        // ========== PROFILE MENU ==========
        const profileBtn = document.getElementById('profileBtn');
        const profileMenu = document.getElementById('profileMenu');

        if (profileBtn && profileMenu) {
            profileBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                profileMenu.classList.toggle('show');
            });

            document.addEventListener('click', () => {
                profileMenu.classList.remove('show');
            });
        }

        // ========== MOBILE DRAWER ==========
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const mobileDrawer = document.getElementById('mobileDrawer');

        if (hamburgerBtn && mobileDrawer) {
            hamburgerBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                hamburgerBtn.classList.toggle('active');
                mobileDrawer.classList.toggle('open');
                body.classList.toggle('no-scroll');
            });

            document.addEventListener('click', (e) => {
                if (!mobileDrawer.contains(e.target) && !hamburgerBtn.contains(e.target)) {
                    hamburgerBtn.classList.remove('active');
                    mobileDrawer.classList.remove('open');
                    body.classList.remove('no-scroll');
                }
            });
        }
    </script>
