<?php
if (session_status() === PHP_SESSION_NONE) session_start();

include '../../db_connect.php';
include '../../includes/auth_check.php';
requireRole(['Admin']);




// -------------------------------
// Khởi tạo mặc định để tránh Notice
// -------------------------------
$unpaidCount        = 0;
$feedbackPending    = 0;
$activeContracts    = 0;
$availableRooms     = 0;
$totalStudents      = 0;
$recentActivities   = [];

// -------------------------------
// 1) Hóa đơn chưa thanh toán
// -------------------------------
$res = $conn->query("SELECT COUNT(*) AS cnt FROM Invoices WHERE Status='Chưa thanh toán'");
if ($res && $row = $res->fetch_assoc()) {
    $unpaidCount = (int) $row['cnt'];
}

// -------------------------------
// 2) Phản ánh đang chờ (gồm 'Chưa xử lý' + 'Đang xử lý')
// -------------------------------
$res = $conn->query("
  SELECT COUNT(*) AS cnt 
  FROM Feedbacks 
  WHERE Status IN ('Chưa xử lý','Đang xử lý')
");
if ($res && $row = $res->fetch_assoc()) {
    $feedbackPending = (int) $row['cnt'];
}

// -------------------------------
// 3) Hợp đồng đang hiệu lực (đếm nhanh tình trạng sử dụng)
// -------------------------------
$res = $conn->query("SELECT COUNT(*) AS cnt FROM Contracts WHERE Status='Hiệu lực'");
if ($res && $row = $res->fetch_assoc()) {
    $activeContracts = (int) $row['cnt'];
}

// -------------------------------
// 4) Phòng còn trống
// -------------------------------
$res = $conn->query("SELECT COUNT(*) AS cnt FROM Rooms WHERE Status='Trống'");
if ($res && $row = $res->fetch_assoc()) {
    $availableRooms = (int) $row['cnt'];
}

// -------------------------------
// 5) Tổng số sinh viên
// -------------------------------
$res = $conn->query("SELECT COUNT(*) AS cnt FROM Students");
if ($res && $row = $res->fetch_assoc()) {
    $totalStudents = (int) $row['cnt'];
}

// -------------------------------
// 6) Hoạt động gần đây
// -------------------------------
$res = $conn->query("
  (SELECT 'feedback' as type, Content as title, CreatedAt as date FROM Feedbacks ORDER BY CreatedAt DESC LIMIT 3)
  UNION ALL
  (SELECT 'invoice' as type, CONCAT('Hóa đơn #', InvoiceID) as title, CreatedAt as date FROM Invoices ORDER BY CreatedAt DESC LIMIT 3)
  UNION ALL
  (SELECT 'contract' as type, CONCAT('Hợp đồng #', ContractID) as title, CreatedAt as date FROM Contracts ORDER BY CreatedAt DESC LIMIT 2)
  ORDER BY date DESC LIMIT 5
");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $recentActivities[] = $row;
    }
}




// Lấy tên admin từ session
$adminName = $_SESSION['user_name'] ?? 'Quản trị viên';

require_once '../../includes/admin_header.php';
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trang Quản Trị - Hệ Thống Ký Túc Xá</title>
    <link rel="stylesheet" href="../../assets/css/admin/admin_header.css">
    <link rel="stylesheet" href="../../assets/css/admin/admin_dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>
    <div class="dashboard-container">
        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <h3>Xin chào, <?php echo htmlspecialchars($adminName); ?>! 👋</h3>
            <p>Chúc bạn một ngày làm việc hiệu quả. Dưới đây là tổng quan về hệ thống.</p>

            <div class="quick-stats">
                <div class="quick-stat">
                    <span class="number"><?= $totalStudents ?></span>
                    <span class="label">Sinh viên</span>
                </div>
                <div class="quick-stat">
                    <span class="number"><?= $activeContracts ?></span>
                    <span class="label">Hợp đồng</span>
                </div>
                <div class="quick-stat">
                    <span class="number"><?= $availableRooms ?></span>
                    <span class="label">Phòng trống</span>
                </div>
            </div>
        </div>

        <div class="dashboard-header">
            <h2><i class="fas fa-tachometer-alt"></i> Bảng điều khiển quản trị</h2>
        </div>

        <!-- Statistics Grid -->
        <div class="stats-grid">
            <div class="stat-card i-amber">
                <i class="fas fa-file-invoice i-amber"></i>
                <div class="stat-card-content">
                    <h3>Hóa đơn chưa thanh toán</h3>
                    <p><?= $unpaidCount ?> hóa đơn</p>
                    <div class="trend">
                        <i class="fas fa-exclamation-circle"></i>
                        Cần xử lý
                    </div>
                </div>
            </div>

            <div class="stat-card i-fuchsia">
                <i class="fas fa-comment-dots i-fuchsia"></i>
                <div class="stat-card-content">
                    <h3>Phản ánh đang xử lý</h3>
                    <p><?= $feedbackPending ?> phản ánh</p>
                    <div class="trend">
                        <i class="fas fa-clock"></i>
                        Chờ phản hồi
                    </div>
                </div>
            </div>

            <div class="stat-card i-emerald">
                <i class="fas fa-file-signature i-emerald"></i>
                <div class="stat-card-content">
                    <h3>Hợp đồng hiệu lực</h3>
                    <p><?= $activeContracts ?> hợp đồng</p>
                    <div class="trend">
                        <i class="fas fa-check-circle"></i>
                        Đang hoạt động
                    </div>
                </div>
            </div>

            <div class="stat-card i-blue">
                <i class="fas fa-door-open i-blue"></i>
                <div class="stat-card-content">
                    <h3>Phòng còn trống</h3>
                    <p><?= $availableRooms ?> phòng</p>
                    <div class="trend">
                        <i class="fas fa-home"></i>
                        Có sẵn
                    </div>
                </div>
            </div>
        </div>

        <!-- Navigation & Actions -->
        <div class="dashboard-actions">
            <div class="action-card">
                <h3><i class="fas fa-cogs"></i> Quản lý hệ thống</h3>
                <div class="nav-links">
                    <a href="../staff/users/users.php"><i class="fas fa-users"></i> <span>Quản lý người dùng</span></a>
                    <a href="../staff/students/student_list.php"><i class="fas fa-users"></i> <span>Quản lý sinh viên</span></a>
                    <a href="../staff/buildings/building_list.php"><i class="fas fa-door-open"></i> <span>Quản lý tòa nhà</span></a>
                    <a href="../staff/rooms/rooms.php"><i class="fas fa-door-open"></i> <span>Quản lý phòng</span></a>
                    <a href="../staff/feedbacks/feedback_list.php"><i class="fas fa-comments"></i> <span>Xem phản ánh</span></a>
                    <a href="../staff/invoices/invoice_list.php"><i class="fas fa-file-invoice"></i> <span>Quản lý hóa đơn</span></a>
                    <a href="../staff/contract/contract_list.php"><i class="fas fa-file-contract"></i> <span>Quản lý hợp đồng</span></a>
                    <a href="../staff/room_requests/staff_request_list.php"><i class="fas fa-clipboard-check"></i> <span>Danh sách Yêu cầu đăng ký phòng</span></a>
                    <a href="../../modules/staff/announcements/announcement_list.php" class="quick-action"><div class="action-icon"><i class="fa-solid fa-list"></i></div>
                        <span>Danh sách Thông báo</span>
                    </a>

                </div>

            </div>

            <div class="action-card">
                <h3><i class="fas fa-tasks"></i> Hành động nhanh</h3>
                <div class="action-links">
                    <a href="../staff/buildings/building_create.php"><i class="fas fa-building"></i> <span>Tạo tòa nhà mới</span></a>
                    <a href="../../modules/staff/users/user_create.php"><i class="fas fa-user-plus"></i> <span>Thêm người dùng mới</span></a>
                    <a href="../staff/students/student_create.php"><i class="fas fa-user-plus"></i> <span>Thêm sinh viên mới</span></a>
                    <a href="../staff/rooms/room_add.php"><i class="fas fa-plus-circle"></i> <span>Thêm phòng mới</span></a>
                    <a href="../staff/invoices/invoice_create.php"><i class="fas fa-file-invoice-dollar"></i> <span>Tạo hóa đơn mới</span></a>
                    <a href="../staff/contract/contract_create.php"><i class="fas fa-file-signature"></i> <span>Tạo hợp đồng mới</span></a>
                    <a href="../staff/announcements/announcement_create.php"><i class="fas fa-broadcast-tower"></i><span>Tạo thông báo mới</span></a>

                </div>
            </div>

            <div class="action-card">
                <h3><i class="fas fa-chart-line"></i> Báo cáo & Thống kê</h3>
                <div class="action-links">
                    <a href="../admin/report/finance_report.php"><i class="fas fa-money-bill-wave"></i> <span>Báo cáo tài chính</span></a>
                    <a href="../../modules/staff/rooms/room_occupancy.php"><i class="fas fa-bed"></i> <span>Tỷ lệ lấp đầy phòng</span></a>
                    <a href="../../modules/staff/feedbacks/feedback_stats.php"><i class="fas fa-chart-bar"></i> <span>Thống kê phản ánh</span></a>
                    <a href="../../modules/staff/users/user_stats.php"><i class="fas fa-user"></i> <span>Thống kê người dùng</span></a>
                    <a href="../../modules/staff/students/student_stats.php"><i class="fas fa-user-graduate"></i> <span>Thống kê sinh viên</span></a>
                </div>
            </div>
        </div>
    </div>

    <?php include '../../includes/footer.php'; ?>

    <script>
        // Thêm hiệu ứng tương tác
        document.addEventListener('DOMContentLoaded', function() {
            // Hiệu ứng hover cho các card
            const cards = document.querySelectorAll('.stat-card, .action-card');
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = this.classList.contains('stat-card') ?
                        'translateY(-8px) scale(1.02)' :
                        'translateY(-5px) scale(1.01)';
                });

                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0) scale(1)';
                });
            });

            // Hiệu ứng loading cho dữ liệu
            const statNumbers = document.querySelectorAll('.stat-card p');
            statNumbers.forEach(stat => {
                const finalNumber = parseInt(stat.textContent);
                let currentNumber = 0;
                const increment = finalNumber / 30;
                const timer = setInterval(() => {
                    currentNumber += increment;
                    if (currentNumber >= finalNumber) {
                        currentNumber = finalNumber;
                        clearInterval(timer);
                    }
                    stat.textContent = Math.floor(currentNumber) + ' ' + stat.textContent.split(' ')[1];
                }, 50);
            });
        });
    </script>
</body>

</html>