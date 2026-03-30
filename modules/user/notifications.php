<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/auth_check.php';

if (empty($_SESSION['UserID'])) {
    header('Location: /login.php');
    exit;
}

$userId = (int)$_SESSION['UserID'];
$studentId = 0;

$stmt = $conn->prepare("SELECT StudentID FROM Students WHERE UserID = ? LIMIT 1");
$stmt->bind_param('i', $userId);
$stmt->execute();
$r = $stmt->get_result();
if ($row = $r->fetch_assoc()) { $studentId = (int)$row['StudentID']; }
$stmt->close();

$notifications = [];

if ($studentId > 0) {
    $stmt = $conn->prepare("
        SELECT 'invoice' AS Type, i.InvoiceID AS ID,
               CONCAT('Hóa đơn tháng ', i.Month, '/', i.Year) AS Title,
               CONCAT('Bạn có hóa đơn ', FORMAT(i.TotalAmount, 0), ' đ chưa thanh toán. Hạn: ', DATE_FORMAT(i.DueDate, '%d/%m/%Y')) AS Message,
               i.DueDate AS CreatedAt,
               CONCAT('/modules/user/bills/bill_detail.php?id=', i.InvoiceID) AS Link,
               IF(inr.ReadID IS NULL, 0, 1) AS IsRead
        FROM Invoices i
        INNER JOIN Contracts c ON i.ContractID = c.ContractID
        LEFT JOIN InvoiceNotificationReads inr ON i.InvoiceID = inr.InvoiceID AND inr.StudentID = ?
        WHERE c.StudentID = ? AND i.Status = 'Chưa thanh toán' AND i.DueDate >= CURDATE()
    ");
    $stmt->bind_param('ii', $studentId, $studentId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) { $notifications[] = $row; }
    $stmt->close();
}

$stmt = $conn->prepare("
    SELECT DISTINCT 'announcement' AS Type, a.AnnouncementID AS ID, a.Title,
           SUBSTRING(a.Content, 1, 200) AS Message, a.DatePosted AS CreatedAt,
           CONCAT('/modules/user/accesslogs.php?view=', a.AnnouncementID) AS Link,
           IF(av.ViewID IS NULL, 0, 1) AS IsRead
    FROM announcements a
    LEFT JOIN AnnouncementViews av ON a.AnnouncementID = av.AnnouncementID AND av.StudentID = ?
    ORDER BY a.DatePosted DESC LIMIT 20
");
$stmt->bind_param('i', $studentId);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) { $notifications[] = $row; }
$stmt->close();

usort($notifications, function($a, $b) {
    if ($a['IsRead'] != $b['IsRead']) return $a['IsRead'] - $b['IsRead'];
    return strtotime($b['CreatedAt']) - strtotime($a['CreatedAt']);
});

$unreadCount = count(array_filter($notifications, fn($n) => $n['IsRead'] == 0));
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thông báo | Ký túc xá</title>
    <link rel="stylesheet" href="<?= $base ?>assets/css/global.css">
    <link rel="stylesheet" href="<?= $base ?>assets/css/modules_shared.css">
    <style>
        .notif-page { max-width:900px; margin:0 auto; padding:30px 20px; }
        .notif-head { text-align:center; margin-bottom:32px; }
        .notif-head h1 { font-size:1.7rem; font-weight:800; color:var(--text); margin:0 0 8px; }
        .notif-head p { color:var(--text-secondary); font-size:0.92rem; margin:0; }
        .notif-head .unread-count { display:inline-block; background:var(--gradient-primary); color:#fff; padding:4px 14px; border-radius:999px; font-size:0.82rem; font-weight:700; margin-top:10px; }

        .notif-card { background:var(--surface); border:1px solid var(--stroke); border-radius:16px; padding:20px 24px; margin-bottom:14px; transition:all 0.3s ease; position:relative; }
        .notif-card:hover { transform:translateY(-2px); box-shadow:var(--shadow-lg); }
        .notif-card.unread { border-left:4px solid var(--primary); background:linear-gradient(135deg, rgba(67,97,238,0.04) 0%, rgba(67,97,238,0.01) 100%); }
        .notif-top { display:flex; align-items:center; justify-content:space-between; margin-bottom:10px; }
        .notif-type { display:inline-flex; align-items:center; gap:6px; padding:4px 12px; font-size:0.72rem; font-weight:700; border-radius:999px; text-transform:uppercase; letter-spacing:0.5px; }
        .notif-type.invoice { background:rgba(16,185,129,0.1); color:#10b981; }
        .notif-type.announcement { background:rgba(67,97,238,0.1); color:#4361ee; }
        .notif-unread-badge { padding:3px 10px; font-size:0.7rem; font-weight:700; background:var(--gradient-primary); color:#fff; border-radius:999px; }
        .notif-title { font-size:1.05rem; font-weight:700; color:var(--text); margin:0 0 6px; }
        .notif-msg { font-size:0.88rem; color:var(--text-secondary); line-height:1.6; margin:0 0 14px; }
        .notif-foot { display:flex; align-items:center; justify-content:space-between; padding-top:12px; border-top:1px solid var(--stroke); }
        .notif-time { font-size:0.82rem; color:var(--text-secondary); display:flex; align-items:center; gap:6px; }
        .notif-action { padding:8px 18px; font-size:0.85rem; font-weight:700; background:var(--gradient-primary); color:#fff; border:none; border-radius:10px; text-decoration:none; transition:all 0.2s; display:inline-flex; align-items:center; gap:6px; }
        .notif-action:hover { transform:translateY(-2px); box-shadow:0 4px 12px rgba(67,97,238,0.3); }
    </style>
</head>
<body>
    <div class="notif-page">
        <div class="notif-head">
            <h1><i class="fas fa-bell"></i> Tất cả thông báo</h1>
            <p>Danh sách các thông báo hóa đơn và thông báo chung</p>
            <?php if ($unreadCount > 0): ?>
                <span class="unread-count"><i class="fas fa-envelope"></i> <?= $unreadCount ?> chưa đọc</span>
            <?php endif; ?>
        </div>

        <?php if (!empty($notifications)): ?>
            <?php foreach ($notifications as $notif):
                $isUnread = $notif['IsRead'] == 0;
                $type = $notif['Type'];
                $typeLabel = $type === 'invoice' ? 'Hóa đơn' : 'Thông báo';
                $typeIcon = $type === 'invoice' ? 'fa-file-invoice-dollar' : 'fa-bullhorn';
            ?>
                <div class="notif-card <?= $isUnread ? 'unread' : '' ?>">
                    <div class="notif-top">
                        <span class="notif-type <?= $type ?>"><i class="fas <?= $typeIcon ?>"></i> <?= $typeLabel ?></span>
                        <?php if ($isUnread): ?>
                            <span class="notif-unread-badge">Chưa đọc</span>
                        <?php endif; ?>
                    </div>
                    <h3 class="notif-title"><?= htmlspecialchars($notif['Title']) ?></h3>
                    <p class="notif-msg"><?= htmlspecialchars($notif['Message']) ?></p>
                    <div class="notif-foot">
                        <span class="notif-time"><i class="fas fa-clock"></i> <?= date('d/m/Y H:i', strtotime($notif['CreatedAt'])) ?></span>
                        <?php if (!empty($notif['Link'])): ?>
                            <a href="<?= htmlspecialchars($notif['Link']) ?>" class="notif-action"><i class="fas fa-arrow-right"></i> Xem</a>
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
    </div>

    <?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html>
