<?php
if (session_status() === PHP_SESSION_NONE) session_start();

include '../../db_connect.php';
include '../../includes/header.php';
require_once __DIR__ . '/../../includes/auth_check.php';

requireRole(['Student', 'Manager', 'Admin']);

$userId = $_SESSION['UserID'] ?? 0;
$studentId = $_SESSION['StudentID'] ?? 0;

// Nếu session chưa có StudentID thì lấy lại từ bảng Students
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

  // 🔹 1. Hóa đơn chưa thanh toán (hoặc dùng bảng Payments nếu không có Invoices)
  $sql = "
        SELECT COUNT(*) AS count 
        FROM Invoices i
        JOIN Contracts c ON c.ContractID = i.ContractID
        WHERE c.StudentID = ? AND i.Status = 'Chưa thanh toán'
    ";
  $stmt = $conn->prepare($sql);
  if (!$stmt) {
    die("❌ SQL Error (Invoices): " . $conn->error);
  }
  $stmt->bind_param("i", $studentId);
  $stmt->execute();
  $unpaidBills = (int)($stmt->get_result()->fetch_assoc()['count'] ?? 0);
  $stmt->close();

  // 🔹 2. Thông tin phòng hiện tại
  $sql = "
        SELECT r.RoomNumber, b.BuildingName
        FROM Contracts c
        JOIN Rooms r ON r.RoomID = c.RoomID
        JOIN Buildings b ON b.BuildingID = r.BuildingID
        WHERE c.StudentID = ? AND c.Status = 'Hiệu lực'
        LIMIT 1
    ";
  $stmt = $conn->prepare($sql);
  if (!$stmt) {
    die("❌ SQL Error (Room): " . $conn->error);
  }
  $stmt->bind_param("i", $studentId);
  $stmt->execute();
  $currentRoom = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  // 🔹 3. Phản ánh đang chờ xử lý
  $sql = "
    SELECT COUNT(*) AS count 
    FROM Feedbacks 
    WHERE StudentID = ? 
      AND Status IN ('Chưa xử lý', 'Đang xử lý')
";
  $stmt = $conn->prepare($sql);
  if (!$stmt) {
    die("❌ SQL Error (Feedbacks): " . $conn->error);
  }
  $stmt->bind_param("i", $studentId);
  $stmt->execute();
  $pendingFeedbacks = (int)($stmt->get_result()->fetch_assoc()['count'] ?? 0);
  $stmt->close();


  // 🔹 4. Thông báo chưa đọc trong 7 ngày
  $sql = "
        SELECT COUNT(*) AS count 
        FROM Announcements 
        WHERE DatePosted >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        AND AnnouncementID NOT IN (
            SELECT AnnouncementID FROM AnnouncementViews WHERE StudentID = ?
        )
    ";
  $stmt = $conn->prepare($sql);
  if (!$stmt) {
    die("❌ SQL Error (Announcements): " . $conn->error);
  }
  $stmt->bind_param("i", $studentId);
  $stmt->execute();
  $unreadAnnouncements = (int)($stmt->get_result()->fetch_assoc()['count'] ?? 0);
  $stmt->close();

  // 🔹 5. Hoạt động gần đây
  $activityQuery = "
        (SELECT 
    'invoice' AS type, 
    i.CreatedAt AS date, 
    'Hóa đơn mới' AS title
 FROM Invoices i
 JOIN Contracts c ON c.ContractID = i.ContractID
 WHERE c.StudentID = $studentId
 ORDER BY i.CreatedAt DESC 
 LIMIT 2)

        UNION
        (SELECT 
        'feedback' AS type, 
        f.CreatedAt AS date, 
        'Phản ánh đã gửi' AS title
     FROM Feedbacks f
     WHERE f.StudentID = $studentId
     ORDER BY f.CreatedAt DESC 
     LIMIT 2)

    ORDER BY date DESC 
    LIMIT 4
    ";
  $recentActivities = $conn->query($activityQuery);

  // Debug
  if (!$recentActivities) {
    error_log("Dashboard activity query error: " . $conn->error);
    error_log("Query: " . $activityQuery);
  }
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard Sinh viên - Ký túc xá</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="/assets/css/Dashboard_user.css">
</head>

<body>
  <!-- DEBUG INFO -->
  <?php if (isset($_GET['debug'])): ?>
    <div style="background: #f8d7da; padding: 15px; margin: 10px; border-radius: 5px;">
      <strong>Debug Info:</strong><br>
      StudentID: <?= $studentId ?><br>
      Activities count: <?= $recentActivities ? $recentActivities->num_rows : 'NULL' ?><br>
      Query error: <?= $conn->error ?><br>
    </div>
  <?php endif; ?>

  <div class="student-dashboard">
    <div class="dashboard-header">
      <h2>🎓 Xin chào, <?= htmlspecialchars($_SESSION['FullName'] ?? 'Sinh viên') ?>!</h2>
      <p>Chào mừng bạn đến với khu vực dành cho sinh viên ký túc xá</p>
    </div>

    <!-- Thống kê nhanh -->
    <div class="quick-stats">
      <div class="stat-card">
        <div class="stat-icon bill">
          <i class="fas fa-file-invoice-dollar"></i>
        </div>
        <div class="stat-content">
          <h4>Hóa đơn chờ</h4>
          <div class="number"><?= $unpaidBills ?></div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon room">
          <i class="fas fa-door-open"></i>
        </div>
        <div class="stat-content">
          <h4>Phòng hiện tại</h4>
          <div class="number"><?= $currentRoom ? $currentRoom['RoomNumber'] : 'Chưa có' ?></div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon feedback">
          <i class="fas fa-comment-dots"></i>
        </div>
        <div class="stat-content">
          <h4>Phản ánh chờ</h4>
          <div class="number"><?= isset($pendingFeedbacks) ? $pendingFeedbacks : 0 ?></div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon announcement">
          <i class="fas fa-bullhorn"></i>
        </div>
        <div class="stat-content">
          <h4>Thông báo mới</h4>
          <div class="number"><?= $unreadAnnouncements ?></div>
        </div>
      </div>
    </div>

    <!-- Menu chính và Hoạt động gần đây (Bố cục 2 cột) -->
    <div class="dashboard-main-content">
      <div class="dashboard-nav-section">
        <div class="dashboard-grid">
          <a href="/modules/user/feedbacks.php" class="dashboard-card">
            <div class="icon-box"><i class="fas fa-comment-dots"></i></div>
            <h3>Gửi phản ánh</h3>
            <p>Gửi ý kiến, phản ánh về phòng ở hoặc cơ sở vật chất.</p>
          </a>

          <a href="/modules/user/bills/bills.php" class="dashboard-card">
            <div class="icon-box"><i class="fas fa-file-invoice-dollar"></i></div>
            <h3>Hóa đơn & Thanh toán</h3>
            <p>Xem chi tiết các khoản phí và lịch sử thanh toán của bạn.</p>
          </a>

          <a href="/modules/user/rooms/rooms.php" class="dashboard-card">
            <div class="icon-box"><i class="fas fa-bed"></i></div>
            <h3>Phòng ở của tôi</h3>
            <p>Thông tin hợp đồng, bạn cùng phòng và trạng thái phòng hiện tại.</p>
          </a>

          <a href="/modules/user/accesslogs.php" class="dashboard-card">
            <div class="icon-box"><i class="fas fa-bullhorn"></i></div>
            <h3>Thông báo</h3>
            <p>Xem các thông báo mới nhất từ ban quản lý ký túc xá.</p>
          </a>
        </div>
      </div>

      <div class="dashboard-side-section">
        <!-- Hoạt động gần đây -->
        <?php if ($recentActivities instanceof mysqli_result && $recentActivities->num_rows > 0): ?>
          <div class="recent-activity">
            <div class="activity-header">
              <h3><i class="fas fa-history"></i> Hoạt động gần đây</h3>
            </div>
            <div class="activity-list">
              <?php while ($activity = $recentActivities->fetch_assoc()): ?>
                <div class="activity-item">
                  <div class="activity-icon" style="background: <?= $activity['type'] == 'invoice' ? 'linear-gradient(135deg, #ff6b6b, #ee5a52)' : 'linear-gradient(135deg, #4ecdc4, #44a08d)' ?>;">
                    <i class="fas fa-<?= $activity['type'] == 'invoice' ? 'file-invoice-dollar' : 'comment-dots' ?>"></i>
                  </div>
                  <div class="activity-content">
                    <p><?= htmlspecialchars($activity['title']) ?></p>
                    <div class="activity-time">
                      <?= date('H:i d/m/Y', strtotime($activity['date'])) ?>
                    </div>
                  </div>
                </div>
              <?php endwhile; ?>
            </div>
          </div>
        <?php else: ?>
          <div class="no-activity">
            <i class="fas fa-info-circle"></i> Chưa có hoạt động nào gần đây.
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php include '../../includes/footer.php'; ?>
</body>

</html>