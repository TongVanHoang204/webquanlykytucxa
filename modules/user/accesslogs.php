<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/auth_check.php';

$isLoggedIn = !empty($_SESSION['UserID']);
$studentId  = 0;

if ($isLoggedIn) {
    if (!empty($_SESSION['StudentID'])) {
        $studentId = (int)$_SESSION['StudentID'];
    } else {
        $userId = (int)$_SESSION['UserID'];
        if ($st = $conn->prepare("SELECT StudentID FROM Students WHERE UserID = ? LIMIT 1")) {
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

// VIEW DETAIL
if (isset($_GET['view']) && is_numeric($_GET['view'])) {
    $announcementId = (int)$_GET['view'];

    if ($studentId > 0) {
        $stmtView = $conn->prepare("INSERT IGNORE INTO AnnouncementViews (AnnouncementID, StudentID, ViewedAt) VALUES (?, ?, NOW())");
        if ($stmtView) {
            $stmtView->bind_param("ii", $announcementId, $studentId);
            $stmtView->execute();
            $stmtView->close();
        }
    }

    $stmtDetail = $conn->prepare("SELECT * FROM Announcements WHERE AnnouncementID = ? LIMIT 1");
    if ($stmtDetail) {
        $stmtDetail->bind_param("i", $announcementId);
        $stmtDetail->execute();
        $detail = $stmtDetail->get_result()->fetch_assoc();
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($detail['Title']) ?> | Ký túc xá</title>
    <link rel="stylesheet" href="<?= $base ?>assets/css/global.css">
    <link rel="stylesheet" href="<?= $base ?>assets/css/modules_shared.css">
    <style>
        .ann-detail { max-width:800px; margin:0 auto; padding:30px 20px; }
        .ann-detail-head { background:var(--gradient-primary); color:#fff; border-radius:20px; padding:32px; margin-bottom:24px; position:relative; overflow:hidden; }
        .ann-detail-head::after { content:''; position:absolute; top:-40%; right:-15%; width:250px; height:250px; background:rgba(255,255,255,0.06); border-radius:50%; }
        .ann-detail-head h1 { font-size:1.4rem; font-weight:800; margin:0 0 10px; }
        .ann-detail-head p { font-size:0.88rem; opacity:0.8; margin:0; display:flex; gap:16px; flex-wrap:wrap; }
        .ann-detail-body { background:var(--surface); border:1px solid var(--stroke); border-radius:16px; padding:28px; font-size:0.92rem; line-height:1.8; color:var(--text); margin-bottom:20px; }
        .ann-detail-back { display:inline-flex; align-items:center; gap:6px; padding:10px 20px; background:var(--gradient-primary); color:#fff; border-radius:10px; font-weight:700; font-size:0.88rem; text-decoration:none; transition:all 0.2s; }
        .ann-detail-back:hover { transform:translateY(-2px); box-shadow:0 4px 12px rgba(67,97,238,0.3); }
    </style>
</head>
<body>
    <div class="ann-detail">
        <div class="ann-detail-head">
            <h1><i class="fas fa-bullhorn"></i> <?= htmlspecialchars($detail['Title']) ?></h1>
            <p>
                <span><i class="fas fa-calendar-day"></i> <?= date('d/m/Y H:i', strtotime($detail['DatePosted'])) ?></span>
                <span><i class="fas fa-user"></i> <?= htmlspecialchars($detail['PostedBy']) ?></span>
            </p>
        </div>
        <div class="ann-detail-body"><?= nl2br(htmlspecialchars($detail['Content'])) ?></div>
        <a href="<?= $base ?>modules/user/accesslogs.php" class="ann-detail-back"><i class="fas fa-arrow-left"></i> Quay lại</a>
    </div>
    <?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html>
<?php
        exit;
    else:
        echo "<div style='text-align:center;padding:60px;color:var(--text-secondary);'>Thông báo không tồn tại hoặc đã bị xóa.</div>";
        include __DIR__ . '/../../includes/footer.php';
        exit;
    endif;
}

// LIST VIEW
if ($studentId > 0) {
    $announcements = $conn->query("
        SELECT a.AnnouncementID, a.Title, a.Content, a.DatePosted, a.PostedBy,
               (SELECT COUNT(*) FROM AnnouncementViews v WHERE v.AnnouncementID = a.AnnouncementID AND v.StudentID = {$studentId}) AS IsRead
        FROM Announcements a ORDER BY a.DatePosted DESC LIMIT 15
    ");
    $unreadRes = $conn->query("
        SELECT COUNT(*) AS cnt FROM Announcements a
        LEFT JOIN AnnouncementViews v ON v.AnnouncementID = a.AnnouncementID AND v.StudentID = {$studentId}
        WHERE v.AnnouncementID IS NULL
    ");
    $unreadCount = ($unreadRes && $row = $unreadRes->fetch_assoc()) ? (int)$row['cnt'] : 0;
} else {
    $announcements = $conn->query("SELECT a.AnnouncementID, a.Title, a.Content, a.DatePosted, a.PostedBy, 1 AS IsRead FROM Announcements a ORDER BY a.DatePosted DESC LIMIT 15");
    $unreadCount = 0;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thông báo ký túc xá</title>
    <link rel="stylesheet" href="<?= $base ?>assets/css/global.css">
    <link rel="stylesheet" href="<?= $base ?>assets/css/modules_shared.css">
    <style>
        .ann-page { max-width:900px; margin:0 auto; padding:30px 20px; }
        .ann-head { text-align:center; margin-bottom:32px; }
        .ann-head h1 { font-size:1.6rem; font-weight:800; color:var(--text); margin:0 0 8px; }
        .ann-head p { color:var(--text-secondary); font-size:0.9rem; margin:0 0 10px; }
        .ann-head .unread-pill { display:inline-block; background:var(--gradient-primary); color:#fff; padding:4px 14px; border-radius:999px; font-size:0.82rem; font-weight:700; }
        .ann-head .guest-msg { background:var(--bg); border:1px solid var(--stroke); border-radius:12px; padding:14px; margin-top:12px; font-size:0.84rem; color:var(--text-secondary); }

        .ann-card { display:flex; gap:16px; background:var(--surface); border:1px solid var(--stroke); border-radius:16px; padding:18px 22px; margin-bottom:12px; text-decoration:none; color:inherit; transition:all 0.3s ease; }
        .ann-card:hover { transform:translateY(-2px); box-shadow:var(--shadow-lg); border-color:var(--primary); }
        .ann-card.unread { border-left:4px solid var(--primary); background:linear-gradient(135deg, rgba(67,97,238,0.04) 0%, transparent 100%); }
        .ann-card-info { flex:1; }
        .ann-card-info h3 { font-size:0.95rem; font-weight:700; margin:0 0 6px; color:var(--text); display:flex; align-items:center; gap:8px; }
        .ann-card-info p { font-size:0.84rem; color:var(--text-secondary); line-height:1.5; margin:0; }
        .ann-card-meta { display:flex; flex-direction:column; align-items:flex-end; gap:4px; font-size:0.78rem; color:var(--text-secondary); white-space:nowrap; }
        .ann-new-badge { background:var(--gradient-primary); color:#fff; padding:2px 8px; border-radius:999px; font-size:0.68rem; font-weight:700; }

        .ann-back { display:inline-flex; align-items:center; gap:6px; padding:10px 20px; background:var(--surface); border:1px solid var(--stroke); border-radius:10px; font-weight:600; font-size:0.88rem; color:var(--text); text-decoration:none; margin-top:20px; transition:all 0.2s; }
        .ann-back:hover { border-color:var(--primary); color:var(--primary); }
    </style>
</head>
<body>
    <div class="ann-page">
        <div class="ann-head">
            <h1><i class="fas fa-bullhorn"></i> Thông báo mới nhất</h1>
            <p>Các thông báo từ ban quản lý ký túc xá</p>
            <?php if ($studentId > 0): ?>
                <?php if ($unreadCount > 0): ?>
                    <span class="unread-pill"><i class="fas fa-envelope"></i> <?= $unreadCount ?> chưa đọc</span>
                <?php endif; ?>
            <?php else: ?>
                <div class="guest-msg"><i class="fas fa-user"></i> Bạn đang xem với tư cách khách. Đăng nhập để ghi nhận thông báo đã đọc.</div>
            <?php endif; ?>
        </div>

        <?php if ($announcements && $announcements->num_rows > 0): ?>
            <?php while ($a = $announcements->fetch_assoc()):
                $isRead = !empty($a['IsRead']);
            ?>
                <a href="<?= $base ?>modules/user/accesslogs.php?view=<?= (int)$a['AnnouncementID'] ?>" class="ann-card <?= $isRead ? '' : 'unread' ?>">
                    <div class="ann-card-info">
                        <h3>
                            <?= htmlspecialchars($a['Title']) ?>
                            <?php if (!$isRead && $studentId > 0): ?>
                                <span class="ann-new-badge">Mới</span>
                            <?php endif; ?>
                        </h3>
                        <p><?= htmlspecialchars(mb_strimwidth(strip_tags($a['Content']), 0, 120, '...')) ?></p>
                    </div>
                    <div class="ann-card-meta">
                        <span><i class="fas fa-calendar-day"></i> <?= date('d/m/Y', strtotime($a['DatePosted'])) ?></span>
                        <span><i class="fas fa-user"></i> <?= htmlspecialchars($a['PostedBy']) ?></span>
                    </div>
                </a>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="mod-empty" style="padding:60px;">
                <i class="fas fa-info-circle"></i>
                <p>Hiện chưa có thông báo nào.</p>
            </div>
        <?php endif; ?>

        <a href="<?= $base ?>modules/user/dashboard.php" class="ann-back"><i class="fas fa-arrow-left"></i> Quay lại Dashboard</a>
    </div>

    <?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html>