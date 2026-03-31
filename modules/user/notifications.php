<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/notification_service.php';
requireRole(['Student']);

$userId = (int) ($_SESSION['UserID'] ?? 0);
$notifications = fetchUserNotifications($conn, $userId, 50);
$unreadCount = countUnreadUserNotifications($conn, $userId);

$pageTitle = 'Thông báo';
$pageStylesheets = ['assets/css/modules_shared.css'];
require_once __DIR__ . '/../../includes/header.php';
?>
<style>
        .notif-page { max-width: 900px; margin: 0 auto; padding: 30px 20px; }
        .notif-head { text-align: center; margin-bottom: 32px; }
        .notif-head h1 { font-size: 1.7rem; font-weight: 800; color: var(--text); margin: 0 0 8px; }
        .notif-head p { color: var(--text-secondary); font-size: 0.92rem; margin: 0; }
        .notif-head .unread-count { display: inline-block; background: var(--gradient-primary); color: #fff; padding: 4px 14px; border-radius: 999px; font-size: 0.82rem; font-weight: 700; margin-top: 10px; }

        .notif-card { background: var(--surface); border: 1px solid var(--stroke); border-radius: 16px; padding: 20px 24px; margin-bottom: 14px; transition: all 0.3s ease; position: relative; }
        .notif-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-lg); }
        .notif-card.unread { border-left: 4px solid var(--primary); background: linear-gradient(135deg, rgba(67,97,238,0.04) 0%, rgba(67,97,238,0.01) 100%); }
        .notif-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; gap: 12px; }
        .notif-type { display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; font-size: 0.72rem; font-weight: 700; border-radius: 999px; text-transform: uppercase; letter-spacing: 0.5px; }
        .notif-type.system, .notif-type.info { background: rgba(67,97,238,0.1); color: #4361ee; }
        .notif-type.billing, .notif-type.success { background: rgba(16,185,129,0.1); color: #10b981; }
        .notif-type.access { background: rgba(59,130,246,0.1); color: #2563eb; }
        .notif-type.email { background: rgba(168,85,247,0.12); color: #9333ea; }
        .notif-type.warning { background: rgba(245,158,11,0.12); color: #d97706; }
        .notif-type.danger { background: rgba(239,68,68,0.12); color: #dc2626; }
        .notif-unread-badge { padding: 3px 10px; font-size: 0.7rem; font-weight: 700; background: var(--gradient-primary); color: #fff; border-radius: 999px; }
        .notif-title { font-size: 1.05rem; font-weight: 700; color: var(--text); margin: 0 0 6px; }
        .notif-msg { font-size: 0.88rem; color: var(--text-secondary); line-height: 1.6; margin: 0 0 14px; }
        .notif-foot { display: flex; align-items: center; justify-content: space-between; padding-top: 12px; border-top: 1px solid var(--stroke); gap: 12px; }
        .notif-time { font-size: 0.82rem; color: var(--text-secondary); display: flex; align-items: center; gap: 6px; }
        .notif-action { padding: 8px 18px; font-size: 0.85rem; font-weight: 700; background: var(--gradient-primary); color: #fff; border: none; border-radius: 10px; text-decoration: none; transition: all 0.2s; display: inline-flex; align-items: center; gap: 6px; }
        .notif-action:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(67,97,238,0.3); }
    </style>
<section class="notif-page">
        <div class="notif-head">
            <h1><i class="fas fa-bell"></i> Tất cả thông báo</h1>
            <p>Danh sách các thông báo cá nhân theo tài khoản hiện tại</p>
            <?php if ($unreadCount > 0): ?>
                <span class="unread-count"><i class="fas fa-envelope"></i> <?= $unreadCount ?> chưa đọc</span>
            <?php endif; ?>
        </div>

        <?php if (!empty($notifications)): ?>
            <?php foreach ($notifications as $notif): ?>
                <?php
                $isUnread = (int) ($notif['IsRead'] ?? 0) === 0;
                $type = (string) ($notif['Type'] ?? 'system');
                $severity = (string) ($notif['Severity'] ?? 'info');
                $typeLabel = match ($type) {
                    'billing' => 'Hóa đơn',
                    'access' => 'Ra/vào cổng',
                    'email' => 'Email',
                    default => 'Thông báo',
                };
                $typeIcon = match ($type) {
                    'billing' => 'fa-file-invoice-dollar',
                    'access' => 'fa-door-open',
                    'email' => 'fa-envelope-open-text',
                    default => 'fa-bullhorn',
                };
                $typeClass = in_array($severity, ['success', 'warning', 'danger'], true) ? $severity : $type;
                ?>
                <div class="notif-card <?= $isUnread ? 'unread' : '' ?>">
                    <div class="notif-top">
                        <span class="notif-type <?= htmlspecialchars($typeClass) ?>">
                            <i class="fas <?= htmlspecialchars($typeIcon) ?>"></i>
                            <?= htmlspecialchars($typeLabel) ?>
                        </span>
                        <?php if ($isUnread): ?>
                            <span class="notif-unread-badge">Chưa đọc</span>
                        <?php endif; ?>
                    </div>
                    <h3 class="notif-title"><?= htmlspecialchars($notif['Title'] ?? '') ?></h3>
                    <p class="notif-msg"><?= htmlspecialchars($notif['Message'] ?? '') ?></p>
                    <div class="notif-foot">
                        <span class="notif-time">
                            <i class="fas fa-clock"></i>
                            <?= !empty($notif['CreatedAt']) ? date('d/m/Y H:i', strtotime((string) $notif['CreatedAt'])) : '-' ?>
                        </span>
                        <?php if (!empty($notif['Link'])): ?>
                            <a href="<?= htmlspecialchars((string) $notif['Link']) ?>" class="notif-action">
                                <i class="fas fa-arrow-right"></i> Xem
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="mod-empty" style="padding:60px;">
                <i class="fas fa-inbox"></i>
                <p>Bạn chưa có thông báo nào.</p>
            </div>
        <?php endif; ?>
    </section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
