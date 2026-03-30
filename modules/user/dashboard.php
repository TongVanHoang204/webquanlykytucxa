<?php
if (session_status() === PHP_SESSION_NONE) session_start();

include '../../db_connect.php';
include '../../includes/header.php';
require_once __DIR__ . '/../../includes/auth_check.php';

requireRole(['Student', 'Manager', 'Admin']);

$userId = $_SESSION['UserID'] ?? 0;
$studentId = $_SESSION['StudentID'] ?? 0;

if (empty($studentId) && !empty($userId)) {
  $res = $conn->query("SELECT StudentID FROM Students WHERE UserID = $userId LIMIT 1");
  if ($res && $res->num_rows > 0) {
    $_SESSION['StudentID'] = $res->fetch_assoc()['StudentID'];
    $studentId = $_SESSION['StudentID'];
  }
}

if ($studentId <= 0) {
  $unpaidBills = $pendingFeedbacks = $unreadAnnouncements = 0;
  $currentRoom = null;
  $recentActivities = [];
} else {
  // Hóa đơn chưa thanh toán
  $stmt = $conn->prepare("SELECT COUNT(*) AS count FROM Invoices i JOIN Contracts c ON c.ContractID = i.ContractID WHERE c.StudentID = ? AND i.Status = 'Chưa thanh toán'");
  $stmt->bind_param("i", $studentId);
  $stmt->execute();
  $unpaidBills = (int)($stmt->get_result()->fetch_assoc()['count'] ?? 0);
  $stmt->close();

  // Phòng hiện tại
  $stmt = $conn->prepare("SELECT r.RoomNumber, b.BuildingName FROM Contracts c JOIN Rooms r ON r.RoomID = c.RoomID JOIN Buildings b ON b.BuildingID = r.BuildingID WHERE c.StudentID = ? AND c.Status = 'Hiệu lực' LIMIT 1");
  $stmt->bind_param("i", $studentId);
  $stmt->execute();
  $currentRoom = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  // Phản ánh chờ
  $stmt = $conn->prepare("SELECT COUNT(*) AS count FROM Feedbacks WHERE StudentID = ? AND Status IN ('Chưa xử lý', 'Đang xử lý')");
  $stmt->bind_param("i", $studentId);
  $stmt->execute();
  $pendingFeedbacks = (int)($stmt->get_result()->fetch_assoc()['count'] ?? 0);
  $stmt->close();

  // Thông báo chưa đọc
  $stmt = $conn->prepare("SELECT COUNT(*) AS count FROM Announcements WHERE DatePosted >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND AnnouncementID NOT IN (SELECT AnnouncementID FROM AnnouncementViews WHERE StudentID = ?)");
  $stmt->bind_param("i", $studentId);
  $stmt->execute();
  $unreadAnnouncements = (int)($stmt->get_result()->fetch_assoc()['count'] ?? 0);
  $stmt->close();

  // Hoạt động gần đây
  $recentActivities = $conn->query("
    (SELECT 'invoice' AS type, i.CreatedAt AS date, 'Hóa đơn mới' AS title
     FROM Invoices i JOIN Contracts c ON c.ContractID = i.ContractID WHERE c.StudentID = $studentId ORDER BY i.CreatedAt DESC LIMIT 2)
    UNION
    (SELECT 'feedback' AS type, f.CreatedAt AS date, 'Phản ánh đã gửi' AS title
     FROM Feedbacks f WHERE f.StudentID = $studentId ORDER BY f.CreatedAt DESC LIMIT 2)
    ORDER BY date DESC LIMIT 4
  ");
}
$fullName = htmlspecialchars($_SESSION['FullName'] ?? 'Sinh viên');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard Sinh viên | Ký túc xá</title>
  <link rel="stylesheet" href="<?= $base ?>assets/css/global.css">
  <link rel="stylesheet" href="<?= $base ?>assets/css/modules_shared.css">
  <style>
    .usr-dash { max-width:1100px; margin:0 auto; padding:30px 20px; }
    .usr-welcome { background:var(--gradient-primary); color:#fff; border-radius:20px; padding:32px 36px; margin-bottom:28px; position:relative; overflow:hidden; }
    .usr-welcome::after { content:''; position:absolute; top:-50%; right:-20%; width:300px; height:300px; background:rgba(255,255,255,0.06); border-radius:50%; }
    .usr-welcome h2 { font-size:1.5rem; font-weight:800; margin:0 0 6px; }
    .usr-welcome p { font-size:0.92rem; opacity:0.85; margin:0; }

    .usr-stats { display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:16px; margin-bottom:28px; }
    .usr-stat { background:var(--surface); border:1px solid var(--stroke); border-radius:16px; padding:20px; display:flex; align-items:center; gap:16px; transition:all 0.3s ease; }
    .usr-stat:hover { transform:translateY(-3px); box-shadow:var(--shadow-lg); }
    .usr-stat-icon { width:48px; height:48px; border-radius:14px; display:flex; align-items:center; justify-content:center; color:#fff; font-size:1.2rem; flex-shrink:0; }
    .usr-stat h4 { font-size:0.82rem; color:var(--text-secondary); margin:0 0 4px; font-weight:500; }
    .usr-stat .number { font-size:1.4rem; font-weight:800; color:var(--text); }

    .usr-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:16px; margin-bottom:28px; }
    .usr-card { background:var(--surface); border:1px solid var(--stroke); border-radius:16px; padding:24px; text-decoration:none; color:var(--text); transition:all 0.3s ease; display:flex; flex-direction:column; align-items:flex-start; gap:12px; }
    .usr-card:hover { transform:translateY(-4px); box-shadow:var(--shadow-lg); border-color:var(--primary); }
    .usr-card .icon-box { width:50px; height:50px; border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:1.3rem; color:#fff; }
    .usr-card h3 { font-size:1rem; font-weight:700; margin:0; }
    .usr-card p { font-size:0.82rem; color:var(--text-secondary); margin:0; line-height:1.5; }

    .usr-activity { background:var(--surface); border:1px solid var(--stroke); border-radius:16px; padding:24px; }
    .usr-activity h3 { font-size:1rem; font-weight:700; margin:0 0 16px; display:flex; align-items:center; gap:8px; color:var(--text); }
    .act-item { display:flex; align-items:center; gap:14px; padding:12px 0; border-bottom:1px solid var(--stroke); }
    .act-item:last-child { border-bottom:none; }
    .act-icon { width:36px; height:36px; border-radius:10px; display:flex; align-items:center; justify-content:center; color:#fff; font-size:0.85rem; flex-shrink:0; }
    .act-info p { font-size:0.88rem; font-weight:600; margin:0 0 2px; color:var(--text); }
    .act-info .time { font-size:0.78rem; color:var(--text-secondary); }
    .no-act { text-align:center; padding:30px; color:var(--text-secondary); font-size:0.88rem; }
  </style>
</head>
<body>
  <div class="usr-dash">
    <!-- Welcome -->
    <div class="usr-welcome">
      <h2>🎓 Xin chào, <?= $fullName ?>!</h2>
      <p>Chào mừng bạn đến với khu vực dành cho sinh viên ký túc xá</p>
    </div>

    <!-- Stats -->
    <div class="usr-stats">
      <div class="usr-stat">
        <div class="usr-stat-icon" style="background:linear-gradient(135deg,#f72585,#b5179e);"><i class="fas fa-file-invoice-dollar"></i></div>
        <div>
          <h4>Hóa đơn chờ</h4>
          <div class="number"><?= $unpaidBills ?></div>
        </div>
      </div>
      <div class="usr-stat">
        <div class="usr-stat-icon" style="background:linear-gradient(135deg,#4361ee,#3a0ca3);"><i class="fas fa-door-open"></i></div>
        <div>
          <h4>Phòng hiện tại</h4>
          <div class="number"><?= $currentRoom ? htmlspecialchars($currentRoom['RoomNumber']) : 'Chưa có' ?></div>
        </div>
      </div>
      <div class="usr-stat">
        <div class="usr-stat-icon" style="background:linear-gradient(135deg,#4cc9f0,#4895ef);"><i class="fas fa-comment-dots"></i></div>
        <div>
          <h4>Phản ánh chờ</h4>
          <div class="number"><?= $pendingFeedbacks ?></div>
        </div>
      </div>
      <div class="usr-stat">
        <div class="usr-stat-icon" style="background:linear-gradient(135deg,#f77f00,#fcbf49);"><i class="fas fa-bullhorn"></i></div>
        <div>
          <h4>Thông báo mới</h4>
          <div class="number"><?= $unreadAnnouncements ?></div>
        </div>
      </div>
    </div>

    <!-- Menu -->
    <div class="usr-grid">
      <a href="<?= $base ?>modules/user/feedbacks.php" class="usr-card">
        <div class="icon-box" style="background:linear-gradient(135deg,#4cc9f0,#4895ef);"><i class="fas fa-comment-dots"></i></div>
        <h3>Gửi phản ánh</h3>
        <p>Gửi ý kiến, phản ánh về phòng ở hoặc cơ sở vật chất.</p>
      </a>
      <a href="<?= $base ?>modules/user/bills/bills.php" class="usr-card">
        <div class="icon-box" style="background:linear-gradient(135deg,#f72585,#b5179e);"><i class="fas fa-file-invoice-dollar"></i></div>
        <h3>Hóa đơn & Thanh toán</h3>
        <p>Xem chi tiết các khoản phí và lịch sử thanh toán.</p>
      </a>
      <a href="<?= $base ?>modules/user/rooms/rooms.php" class="usr-card">
        <div class="icon-box" style="background:linear-gradient(135deg,#7209b7,#560bad);"><i class="fas fa-bed"></i></div>
        <h3>Phòng ở của tôi</h3>
        <p>Hợp đồng, bạn cùng phòng và trạng thái phòng hiện tại.</p>
      </a>
      <a href="<?= $base ?>modules/user/notifications.php" class="usr-card">
        <div class="icon-box" style="background:linear-gradient(135deg,#f77f00,#fcbf49);"><i class="fas fa-bullhorn"></i></div>
        <h3>Thông báo</h3>
        <p>Xem các thông báo mới nhất từ ban quản lý ký túc xá.</p>
      </a>
    </div>

    <!-- Activities -->
    <?php if ($recentActivities instanceof mysqli_result && $recentActivities->num_rows > 0): ?>
      <div class="usr-activity">
        <h3><i class="fas fa-history"></i> Hoạt động gần đây</h3>
        <?php while ($activity = $recentActivities->fetch_assoc()): ?>
          <div class="act-item">
            <div class="act-icon" style="background:<?= $activity['type'] == 'invoice' ? 'linear-gradient(135deg,#f72585,#b5179e)' : 'linear-gradient(135deg,#4cc9f0,#4895ef)' ?>;">
              <i class="fas fa-<?= $activity['type'] == 'invoice' ? 'file-invoice-dollar' : 'comment-dots' ?>"></i>
            </div>
            <div class="act-info">
              <p><?= htmlspecialchars($activity['title']) ?></p>
              <div class="time"><?= date('H:i d/m/Y', strtotime($activity['date'])) ?></div>
            </div>
          </div>
        <?php endwhile; ?>
      </div>
    <?php else: ?>
      <div class="no-act">
        <i class="fas fa-info-circle"></i> Chưa có hoạt động nào gần đây.
      </div>
    <?php endif; ?>
  </div>

  <?php include '../../includes/footer.php'; ?>
</body>
</html>