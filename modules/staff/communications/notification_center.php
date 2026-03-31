<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../db_connect.php';
require_once __DIR__ . '/../../../includes/admin_header.php';
require_once __DIR__ . '/../../../includes/auth_check.php';

requireRole(['Admin', 'Manager']);

$recentNotifications = [];
$result = $conn->query("
    SELECT un.NotificationID, un.Title, un.Message, un.Type, un.Severity, un.CreatedAt, u.FullName, u.Role
    FROM user_notifications un
    INNER JOIN users u ON u.UserID = un.UserID
    ORDER BY un.CreatedAt DESC, un.NotificationID DESC
    LIMIT 20
");

if ($result instanceof mysqli_result) {
    while ($row = $result->fetch_assoc()) {
        $recentNotifications[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trung tâm thông báo | Ký túc xá</title>
    <link rel="stylesheet" href="<?= $base ?>assets/css/global.css">
    <link rel="stylesheet" href="<?= $base ?>assets/css/modules_shared.css">
    <style>
        .notify-center-grid { display:grid; grid-template-columns:minmax(340px, 420px) minmax(0, 1fr); gap:24px; align-items:start; }
        .notify-card { background:var(--surface); border:1px solid var(--stroke); border-radius:18px; padding:24px; box-shadow:var(--shadow-sm); }
        .notify-card h3 { margin:0 0 16px; font-size:1rem; }
        .notify-form { display:grid; gap:14px; }
        .notify-form textarea { min-height:148px; resize:vertical; }
        .notify-form .row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
        .notify-status { min-height:22px; font-size:0.85rem; color:var(--text-secondary); }
        .notify-history { display:grid; gap:12px; }
        .notify-history-item { border:1px solid var(--stroke); border-radius:14px; padding:14px 16px; background:rgba(255,255,255,0.02); }
        .notify-history-item strong { display:block; margin-bottom:6px; }
        .notify-history-meta { display:flex; flex-wrap:wrap; gap:10px; font-size:0.8rem; color:var(--text-secondary); margin-top:10px; }
        @media (max-width: 992px) {
            .notify-center-grid { grid-template-columns:1fr; }
            .notify-form .row { grid-template-columns:1fr; }
        }
    </style>
</head>
<body>
    <div class="mod-container">
        <div class="mod-header">
            <div class="mod-header-left">
                <h2><i class="fas fa-bell"></i> Trung tâm thông báo nội bộ</h2>
                <p>Gửi thông báo realtime cho sinh viên và staff/admin từ một màn hình chung.</p>
            </div>
        </div>

        <div class="notify-center-grid">
            <section class="notify-card">
                <h3>Soạn thông báo mới</h3>
                <form id="notificationComposer" class="notify-form">
                    <div class="row">
                        <div>
                            <label for="notifyTitle">Tiêu đề</label>
                            <input id="notifyTitle" name="title" class="mod-input" type="text" required>
                        </div>
                        <div>
                            <label for="notifyScope">Nhóm nhận</label>
                            <select id="notifyScope" name="target_scope" class="mod-select">
                                <option value="students">Sinh viên</option>
                                <option value="staff_admin">Staff/Admin</option>
                                <option value="all">Toàn bộ người dùng đang hoạt động</option>
                                <option value="user_ids">Danh sách UserID cụ thể</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div>
                            <label for="notifyType">Loại</label>
                            <input id="notifyType" name="type" class="mod-input" type="text" value="system">
                        </div>
                        <div>
                            <label for="notifySeverity">Mức độ</label>
                            <select id="notifySeverity" name="severity" class="mod-select">
                                <option value="info">Thông tin</option>
                                <option value="success">Thành công</option>
                                <option value="warning">Cảnh báo</option>
                                <option value="danger">Khẩn cấp</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label for="notifyLink">Liên kết đính kèm</label>
                        <input id="notifyLink" name="link" class="mod-input" type="text" placeholder="/modules/user/...">
                    </div>

                    <div>
                        <label for="notifyUsers">UserID đích (khi chọn nhóm cụ thể)</label>
                        <input id="notifyUsers" name="user_ids" class="mod-input" type="text" placeholder="Ví dụ: 24,27,35">
                    </div>

                    <div>
                        <label for="notifyMessage">Nội dung</label>
                        <textarea id="notifyMessage" name="message" class="mod-input" required></textarea>
                    </div>

                    <div class="notify-status" id="notificationStatus">Sẵn sàng gửi thông báo.</div>

                    <button class="mod-btn mod-btn-primary" type="submit">
                        <i class="fas fa-paper-plane"></i> Gửi thông báo
                    </button>
                </form>
            </section>

            <section class="notify-card">
                <h3>Thông báo gần đây</h3>
                <div class="notify-history">
                    <?php if ($recentNotifications): ?>
                        <?php foreach ($recentNotifications as $item): ?>
                            <article class="notify-history-item">
                                <strong><?= htmlspecialchars($item['Title']) ?></strong>
                                <div><?= nl2br(htmlspecialchars($item['Message'])) ?></div>
                                <div class="notify-history-meta">
                                    <span><i class="fas fa-user"></i> <?= htmlspecialchars($item['FullName']) ?></span>
                                    <span><i class="fas fa-user-shield"></i> <?= htmlspecialchars($item['Role']) ?></span>
                                    <span><i class="fas fa-tag"></i> <?= htmlspecialchars($item['Type']) ?></span>
                                    <span><i class="fas fa-circle-info"></i> <?= htmlspecialchars($item['Severity']) ?></span>
                                    <span><i class="fas fa-clock"></i> <?= htmlspecialchars($item['CreatedAt']) ?></span>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="mod-empty">
                            <i class="fas fa-inbox"></i>
                            <p>Chưa có thông báo nào trong hệ thống mới.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('notificationComposer');
            const status = document.getElementById('notificationStatus');
            if (!form || !status) {
                return;
            }

            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                const payload = Object.fromEntries(new FormData(form).entries());
                status.textContent = 'Đang gửi thông báo...';

                try {
                    const response = await fetch('<?= $base ?>modules/api/push_notification.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify(payload),
                    });
                    const result = await response.json();
                    if (!response.ok || !result.ok) {
                        throw new Error(result.error || 'Không gửi được thông báo.');
                    }

                    status.textContent = `Đã tạo ${result.createdCount} thông báo.`;
                    form.reset();
                    window.setTimeout(() => window.location.reload(), 600);
                } catch (error) {
                    status.textContent = error.message;
                }
            });
        });
    </script>
</body>
</html>
