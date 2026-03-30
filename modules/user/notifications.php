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

// Lấy StudentID
$stmt = $conn->prepare("SELECT StudentID FROM Students WHERE UserID = ? LIMIT 1");
$stmt->bind_param('i', $userId);
$stmt->execute();
$r = $stmt->get_result();
if ($row = $r->fetch_assoc()) {
    $studentId = (int)$row['StudentID'];
}
$stmt->close();

// Lấy tất cả thông báo (invoice + announcement)
$notifications = [];

if ($studentId > 0) {
    // Thông báo hóa đơn
    $sqlInvoice = "
        SELECT 
            'invoice' AS Type,
            i.InvoiceID AS ID,
            CONCAT('Hóa đơn tháng ', i.Month, '/', i.Year) AS Title,
            CONCAT(
                'Bạn có hóa đơn ',
                FORMAT(i.TotalAmount, 0), ' đ chưa thanh toán. ',
                'Hạn thanh toán: ', DATE_FORMAT(i.DueDate, '%d/%m/%Y')
            ) AS Message,
            i.DueDate AS CreatedAt,
            CONCAT('/modules/user/bills/bill_detail.php?id=', i.InvoiceID) AS Link,
            IF(inr.ReadID IS NULL, 0, 1) AS IsRead
        FROM Invoices i
        INNER JOIN Contracts c ON i.ContractID = c.ContractID
        LEFT JOIN InvoiceNotificationReads inr ON i.InvoiceID = inr.InvoiceID AND inr.StudentID = ?
        WHERE c.StudentID = ?
          AND i.Status = 'Chưa thanh toán'
          AND i.DueDate >= CURDATE()
    ";
    
    $stmt = $conn->prepare($sqlInvoice);
    $stmt->bind_param('ii', $studentId, $studentId);
    $stmt->execute();
    $res = $stmt->get_result();
    
    while ($row = $res->fetch_assoc()) {
        $notifications[] = $row;
    }
    $stmt->close();
}

// Thông báo công khai - Loại bỏ trùng lặp
$sqlAnnouncement = "
    SELECT DISTINCT
        'announcement' AS Type,
        a.AnnouncementID AS ID,
        a.Title,
        SUBSTRING(a.Content, 1, 200) AS Message,
        a.DatePosted AS CreatedAt,
        CONCAT('/modules/user/accesslogs.php?view=', a.AnnouncementID) AS Link,
        IF(av.ViewID IS NULL, 0, 1) AS IsRead
    FROM announcements a
    LEFT JOIN AnnouncementViews av ON a.AnnouncementID = av.AnnouncementID AND av.StudentID = ?
    ORDER BY a.DatePosted DESC
    LIMIT 20
";

$stmt = $conn->prepare($sqlAnnouncement);
$stmt->bind_param('i', $studentId);
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    $notifications[] = $row;
}
$stmt->close();

// Sắp xếp: Chưa đọc trước, sau đó mới nhất
usort($notifications, function($a, $b) {
    if ($a['IsRead'] != $b['IsRead']) {
        return $a['IsRead'] - $b['IsRead'];
    }
    return strtotime($b['CreatedAt']) - strtotime($a['CreatedAt']);
});
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Tất cả thông báo - Ký túc xá</title>
    <link rel="stylesheet" href="../../assets/vendor/fontawesome/css/all.min.css">
    <style>
        .notifications-page {
            max-width: 900px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .page-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .page-header h1 {
            font-size: 2rem;
            color: var(--text);
            margin-bottom: 10px;
        }

        .page-header p {
            color: var(--muted);
            font-size: 0.95rem;
        }

        .notification-card {
            background: var(--bg-card);
            border: 1px solid var(--stroke);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 16px;
            transition: all 0.2s ease;
            position: relative;
        }

        .notification-card.unread {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.08) 0%, rgba(96, 165, 250, 0.05) 100%);
            border-color: rgba(59, 130, 246, 0.3);
        }

        .notification-card.unread::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: linear-gradient(180deg, var(--brand), var(--brand-2));
            border-radius: 12px 0 0 12px;
        }

        .notification-card:hover {
            border-color: var(--brand);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.15);
            transform: translateY(-2px);
        }

        .notification-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .notification-type {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            font-size: 11px;
            font-weight: 600;
            border-radius: 999px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .notification-type.invoice {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }

        .notification-type.announcement {
            background: rgba(59, 130, 246, 0.1);
            color: var(--brand);
        }

        .notification-badge {
            padding: 4px 10px;
            font-size: 11px;
            font-weight: 600;
            background: var(--brand);
            color: white;
            border-radius: 999px;
        }

        .notification-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 8px;
        }

        .notification-message {
            font-size: 0.95rem;
            color: var(--muted);
            line-height: 1.6;
            margin-bottom: 12px;
        }

        .notification-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-top: 12px;
            border-top: 1px solid var(--stroke);
        }

        .notification-time {
            font-size: 0.85rem;
            color: var(--muted);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .notification-action {
            padding: 8px 16px;
            font-size: 0.9rem;
            font-weight: 600;
            background: linear-gradient(135deg, var(--brand), var(--brand-2));
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-block;
        }

        .notification-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-state i {
            font-size: 4rem;
            color: var(--muted);
            margin-bottom: 20px;
        }

        .empty-state h3 {
            font-size: 1.3rem;
            color: var(--text);
            margin-bottom: 10px;
        }

        .empty-state p {
            color: var(--muted);
        }
    </style>
</head>
<body>
    <div class="notifications-page">
        <div class="page-header">
            <h1><i class="fas fa-bell"></i> Tất cả thông báo</h1>
            <p>Danh sách đầy đủ các thông báo hóa đơn và thông báo chung</p>
        </div>

        <?php if (!empty($notifications)): ?>
            <?php foreach ($notifications as $notif): ?>
                <?php 
                    $isUnread = $notif['IsRead'] == 0;
                    $type = $notif['Type'];
                    $typeLabel = $type === 'invoice' ? 'Hóa đơn' : 'Thông báo';
                    $typeIcon = $type === 'invoice' ? 'fa-file-invoice-dollar' : 'fa-bullhorn';
                ?>
                <div class="notification-card <?= $isUnread ? 'unread' : '' ?>">
                    <div class="notification-header">
                        <span class="notification-type <?= $type ?>">
                            <i class="fas <?= $typeIcon ?>"></i>
                            <?= $typeLabel ?>
                        </span>
                        <?php if ($isUnread): ?>
                            <span class="notification-badge">Chưa đọc</span>
                        <?php endif; ?>
                    </div>

                    <h3 class="notification-title"><?= htmlspecialchars($notif['Title']) ?></h3>
                    <p class="notification-message"><?= htmlspecialchars($notif['Message']) ?></p>

                    <div class="notification-footer">
                        <span class="notification-time">
                            <i class="fas fa-clock"></i>
                            <?= date('d/m/Y H:i', strtotime($notif['CreatedAt'])) ?>
                        </span>
                        <?php if (!empty($notif['Link'])): ?>
                            <a href="<?= htmlspecialchars($notif['Link']) ?>" class="notification-action">
                                <i class="fas fa-arrow-right"></i> Xem chi tiết
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h3>Không có thông báo</h3>
                <p>Bạn chưa có thông báo nào trong hệ thống</p>
            </div>
        <?php endif; ?>
    </div>

    <?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html>
