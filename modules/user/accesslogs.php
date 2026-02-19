<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/auth_check.php';

/**
 * Trang thông báo:
 * - Cho phép KHÁCH vãng lai truy cập (không gọi requireRole / requireLogin)
 * - Nếu là sinh viên đã có StudentID → theo dõi đã đọc / chưa đọc
 */

$isLoggedIn = !empty($_SESSION['UserID']);
$studentId  = 0;

// Nếu là sinh viên, cố gắng lấy StudentID (từ session hoặc DB)
if ($isLoggedIn) {
    if (!empty($_SESSION['StudentID'])) {
        $studentId = (int)$_SESSION['StudentID'];
    } else {
        $userId = (int)$_SESSION['UserID'];
        $sqlStu = "SELECT StudentID FROM Students WHERE UserID = ? LIMIT 1";
        if ($st = $conn->prepare($sqlStu)) {
            $st->bind_param("i", $userId);
            $st->execute();
            $resStu = $st->get_result();
            if ($resStu && $rowStu = $resStu->fetch_assoc()) {
                $studentId = (int)$rowStu['StudentID'];
                $_SESSION['StudentID'] = $studentId;
            }
            $st->close();
        }
    }
}

// ==============================
//  LẤY DANH SÁCH THÔNG BÁO
// ==============================
if ($studentId > 0) {
    // Có StudentID → tính IsRead đúng
    $announcements = $conn->query("
        SELECT a.AnnouncementID, a.Title, a.Content, a.DatePosted, a.PostedBy,
               (
                   SELECT COUNT(*) 
                   FROM AnnouncementViews v 
                   WHERE v.AnnouncementID = a.AnnouncementID 
                     AND v.StudentID = {$studentId}
               ) AS IsRead
        FROM Announcements a
        ORDER BY a.DatePosted DESC
        LIMIT 15
    ");

    // Đếm số thông báo chưa đọc
    $unreadCount = 0;
    $unreadRes = $conn->query("
        SELECT COUNT(*) AS cnt
        FROM Announcements a
        LEFT JOIN AnnouncementViews v
            ON v.AnnouncementID = a.AnnouncementID
           AND v.StudentID = {$studentId}
        WHERE v.AnnouncementID IS NULL
    ");
    if ($unreadRes && $rowUnread = $unreadRes->fetch_assoc()) {
        $unreadCount = (int)$rowUnread['cnt'];
    }
} else {
    // Không có StudentID (khách / user không phải sinh viên)
    // → Không theo dõi read/unread, luôn coi là đã đọc
    $announcements = $conn->query("
        SELECT a.AnnouncementID, a.Title, a.Content, a.DatePosted, a.PostedBy,
               1 AS IsRead
        FROM Announcements a
        ORDER BY a.DatePosted DESC
        LIMIT 15
    ");
    $unreadCount = 0;
}

// ==============================
//  XEM CHI TIẾT THÔNG BÁO
// ==============================
if (isset($_GET['view']) && is_numeric($_GET['view'])) {
    $announcementId = (int)$_GET['view'];

    // Nếu có StudentID thì ghi nhận đã xem
    if ($studentId > 0) {
        $stmtView = $conn->prepare("
            INSERT IGNORE INTO AnnouncementViews (AnnouncementID, StudentID, ViewedAt)
            VALUES (?, ?, NOW())
        ");
        if ($stmtView) {
            $stmtView->bind_param("ii", $announcementId, $studentId);
            $stmtView->execute();
            $stmtView->close();
        }
    }

    // Lấy thông tin chi tiết
    $stmtDetail = $conn->prepare("SELECT * FROM Announcements WHERE AnnouncementID = ? LIMIT 1");
    if ($stmtDetail) {
        $stmtDetail->bind_param("i", $announcementId);
        $stmtDetail->execute();
        $detailRes = $stmtDetail->get_result();
        $detail = $detailRes ? $detailRes->fetch_assoc() : null;
        $stmtDetail->close();
    } else {
        $detail = null;
    }

    if ($detail):
?>
        <!DOCTYPE html>
        <html lang="vi">

        <head>
            <meta charset="UTF-8">
            <title><?= htmlspecialchars($detail['Title']) ?> - Thông báo ký túc xá</title>
            <link rel="stylesheet"
                href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
            <link rel="stylesheet" href="/assets/css/accesslogs.css">
        </head>

        <body>
            <div class="announcement-container">
                <div class="announcement-header">
                    <h1><i class="fas fa-bullhorn"></i> <?= htmlspecialchars($detail['Title']) ?></h1>
                    <p>
                        <i class="fas fa-calendar-day"></i>
                        <?= date('d/m/Y H:i', strtotime($detail['DatePosted'])) ?> |
                        <i class="fas fa-user"></i>
                        <?= htmlspecialchars($detail['PostedBy']) ?>
                    </p>
                </div>

                <div class="announcement-content">
                    <?= nl2br(htmlspecialchars($detail['Content'])) ?>
                </div>

                <div class="announcement-footer">
                    <a href="/modules/user/accesslogs.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i> Quay lại danh sách
                    </a>
                </div>
            </div>

            <?php include __DIR__ . '/../../includes/footer.php'; ?>
        </body>

        </html>
<?php
        exit;
    else:
        echo "<div class='alert alert-warning text-center'>Thông báo không tồn tại hoặc đã bị xóa.</div>";
        include __DIR__ . '/../../includes/footer.php';
        exit;
    endif;
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Thông báo ký túc xá - Ký túc xá</title>
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/accesslogs.css">
</head>

<body>
    <div class="announcement-page">
        <div class="page-header">
            <h1><i class="fas fa-bullhorn"></i> Thông báo mới nhất</h1>
            <p>Xem các thông báo từ ban quản lý ký túc xá</p>

            <?php if ($studentId > 0): ?>
                <p class="unread-info">
                    <i class="fas fa-envelope-open-text"></i>
                    Bạn có <strong><?= $unreadCount ?></strong> thông báo chưa đọc.
                </p>
            <?php else: ?>
                <p class="unread-info muted">
                    <i class="fas fa-user"></i>
                    Bạn đang xem với tư cách khách hoặc tài khoản chưa liên kết sinh viên.
                    <br>Vui lòng đăng nhập / hoàn thiện hồ sơ sinh viên để hệ thống ghi nhận thông báo đã đọc.
                </p>
            <?php endif; ?>
        </div>

        <?php if ($announcements && $announcements->num_rows > 0): ?>
            <div class="announcement-list">
                <?php while ($a = $announcements->fetch_assoc()): ?>
                    <?php
                    $isRead = !empty($a['IsRead']); // 0/1
                    ?>
                    <a href="/modules/user/accesslogs.php?view=<?= (int)$a['AnnouncementID'] ?>"
                        class="announcement-card <?= $isRead ? 'read' : 'unread' ?>">
                        <div class="announcement-info">
                            <h3>
                                <?= htmlspecialchars($a['Title']) ?>
                                <?php if (!$isRead && $studentId > 0): ?>
                                    <span class="badge-unread">Mới</span>
                                <?php endif; ?>
                            </h3>
                            <p>
                                <?= htmlspecialchars(mb_strimwidth(strip_tags($a['Content']), 0, 120, '...')) ?>
                            </p>
                        </div>
                        <div class="announcement-meta">
                            <span>
                                <i class="fas fa-calendar-day"></i>
                                <?= date('d/m/Y', strtotime($a['DatePosted'])) ?>
                            </span>
                            <span>
                                <i class="fas fa-user"></i>
                                <?= htmlspecialchars($a['PostedBy']) ?>
                            </span>
                        </div>
                    </a>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-info-circle"></i>
                <h3>Hiện chưa có thông báo nào</h3>
                <p>Ban quản lý sẽ sớm cập nhật các thông tin mới nhất.</p>
            </div>
        <?php endif; ?>

        <div class="actions">
            <a href="/modules/user/dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Quay lại Dashboard
            </a>
        </div>
    </div>

    <?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>

</html>