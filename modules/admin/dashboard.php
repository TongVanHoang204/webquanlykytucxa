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
    <link rel="stylesheet" href="../../assets/vendor/fontawesome/css/all.min.css">
</head>

    <div class="bento-dashboard-container">
        <!-- 1. Bento Welcome Banner -->
        <div class="bento-welcome gradient-flare">
            <div class="welcome-text">
                <h1>Xin chào, <?php echo htmlspecialchars($adminName); ?>! 👋</h1>
                <p>Khám phá tình trạng hiện tại của hệ thống. Dưới đây là các chỉ số theo thời gian thực.</p>
            </div>
            <div class="welcome-quick-stats">
                <div class="wqs-item">
                    <div class="wqs-icon"><i class="fas fa-user-graduate"></i></div>
                    <div class="wqs-info">
                        <span class="number"><?= $totalStudents ?></span>
                        <span class="label">Sinh viên</span>
                    </div>
                </div>
                <div class="wqs-item">
                    <div class="wqs-icon"><i class="fas fa-file-signature"></i></div>
                    <div class="wqs-info">
                        <span class="number"><?= $activeContracts ?></span>
                        <span class="label">Hợp đồng</span>
                    </div>
                </div>
                <div class="wqs-item">
                    <div class="wqs-icon"><i class="fas fa-door-open"></i></div>
                    <div class="wqs-info">
                        <span class="number"><?= $availableRooms ?></span>
                        <span class="label">Phòng trống</span>
                    </div>
                </div>
            </div>
            <div class="flare-effect"></div>
        </div>

        <!-- 2. Bento Metrics Grid -->
        <div class="bento-metrics">
            <div class="bento-metric-card glow-amber">
                <div class="icon-wrapper">
                    <i class="fas fa-file-invoice"></i>
                </div>
                <div class="metric-content">
                    <h3>Hóa đơn chờ</h3>
                    <div class="metric-number"><?= $unpaidCount ?></div>
                    <span class="trend trend-warning"><i class="fas fa-exclamation-circle"></i> Cần xử lý</span>
                </div>
            </div>
            
            <div class="bento-metric-card glow-fuchsia">
                <div class="icon-wrapper">
                    <i class="fas fa-comment-dots"></i>
                </div>
                <div class="metric-content">
                    <h3>Phản ánh</h3>
                    <div class="metric-number"><?= $feedbackPending ?></div>
                    <span class="trend trend-info"><i class="fas fa-clock"></i> Chờ phản hồi</span>
                </div>
            </div>

            <div class="bento-metric-card glow-emerald">
                <div class="icon-wrapper">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="metric-content">
                    <h3>Hợp đồng</h3>
                    <div class="metric-number"><?= $activeContracts ?></div>
                    <span class="trend trend-success"><i class="fas fa-chart-line"></i> Đang hiệu lực</span>
                </div>
            </div>

            <div class="bento-metric-card glow-blue">
                <div class="icon-wrapper">
                    <i class="fas fa-home"></i>
                </div>
                <div class="metric-content">
                    <h3>Phòng trống</h3>
                    <div class="metric-number"><?= $availableRooms ?></div>
                    <span class="trend trend-primary"><i class="fas fa-key"></i> Sẵn sàng</span>
                </div>
            </div>
        </div>

        <!-- 3. Bento Actions Modules -->
        <div class="bento-modules">
            <!-- Systems Management Bento -->
            <div class="bento-module-card span-col-2">
                <div class="module-header">
                    <div class="header-icon"><i class="fas fa-cogs"></i></div>
                    <h2>Quản lý Hệ Thống</h2>
                </div>
                <div class="module-grid-links">
                    <a href="../staff/users/users.php" class="bento-btn"><i class="fas fa-users-cog"></i> Mọi Người Dùng</a>
                    <a href="../staff/students/student_list.php" class="bento-btn"><i class="fas fa-user-graduate"></i> Sinh Viên</a>
                    <a href="../staff/buildings/building_list.php" class="bento-btn"><i class="fas fa-building"></i> Tòa Nhà</a>
                    <a href="../staff/rooms/rooms.php" class="bento-btn"><i class="fas fa-bed"></i> Quản Lý Phòng</a>
                    <a href="../staff/feedbacks/feedback_list.php" class="bento-btn"><i class="fas fa-headset"></i> Trung Tâm Hỗ Trợ</a>
                    <a href="../staff/invoices/invoice_list.php" class="bento-btn"><i class="fas fa-file-invoice-dollar"></i> Kế Toán / Hóa Đơn</a>
                    <a href="../staff/contract/contract_list.php" class="bento-btn"><i class="fas fa-file-signature"></i> Thỏa Thuận Lưu Trú</a>
                    <a href="../staff/room_requests/staff_request_list.php" class="bento-btn"><i class="fas fa-clipboard-list"></i> Yêu Cầu Thuê Phòng</a>
                    <a href="../../modules/staff/announcements/announcement_list.php" class="bento-btn"><i class="fas fa-bullhorn"></i> Bản Tin Hệ Thống</a>
                </div>
            </div>

            <div class="bento-module-column">
                <!-- Quick Actions Bento -->
                <div class="bento-module-card quick-actions">
                    <div class="module-header">
                        <div class="header-icon"><i class="fas fa-bolt"></i></div>
                        <h2>Tác Vụ Tốc Độ</h2>
                    </div>
                    <div class="module-list-links">
                        <a href="../staff/buildings/building_create.php" class="list-item"><i class="fas fa-plus"></i> Khởi tạo Tòa nhà</a>
                        <a href="../../modules/staff/users/user_create.php" class="list-item"><i class="fas fa-user-plus"></i> Cấp tài khoản mới</a>
                        <a href="../staff/students/student_create.php" class="list-item"><i class="fas fa-plus-circle"></i> Hồ sơ Sinh viên</a>
                        <a href="../staff/rooms/room_add.php" class="list-item"><i class="fas fa-folder-plus"></i> Thêm Phòng ở</a>
                        <a href="../staff/invoices/invoice_create.php" class="list-item"><i class="fas fa-file-medical"></i> Lập Hóa đơn</a>
                        <a href="../staff/contract/contract_create.php" class="list-item"><i class="fas fa-pen-nib"></i> Soạn Hợp đồng</a>
                        <a href="../staff/announcements/announcement_create.php" class="list-item"><i class="fas fa-broadcast-tower"></i> Đăng Thông báo</a>
                    </div>
                </div>
            </div>
            
            <div class="bento-module-column">
                <!-- Analytics Bento -->
                <div class="bento-module-card analytics glassmorphism">
                    <div class="module-header glass-header">
                        <div class="header-icon"><i class="fas fa-chart-pie"></i></div>
                        <h2>Báo Cáo & Thống Kê</h2>
                    </div>
                    <div class="module-list-links">
                        <a href="../admin/report/finance_report.php" class="list-item hover-glass"><i class="fas fa-chart-line"></i> Dòng tiền & Tài chính</a>
                        <a href="../../modules/staff/rooms/room_occupancy.php" class="list-item hover-glass"><i class="fas fa-chart-bar"></i> Lấp đầy Cơ sở vật chất</a>
                        <a href="../../modules/staff/feedbacks/feedback_stats.php" class="list-item hover-glass"><i class="fas fa-chart-area"></i> Đo lường Sự Hài Lòng</a>
                        <a href="../../modules/staff/users/user_stats.php" class="list-item hover-glass"><i class="fas fa-users-viewfinder"></i> Tổng quan Người dùng</a>
                        <a href="../../modules/staff/students/student_stats.php" class="list-item hover-glass"><i class="fas fa-graduation-cap"></i> Phân loại Sinh viên</a>
                    </div>
                </div>
            </div>
            
        </div>
    </div>

    <?php include '../../includes/footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Number increment animation for metrics
            const metricNumbers = document.querySelectorAll('.metric-number, .wqs-info .number');
            metricNumbers.forEach(el => {
                const text = el.textContent.trim();
                const targetNum = parseInt(text.replace(/[^0-9]/g, ''), 10);
                if(isNaN(targetNum) || targetNum === 0) return;
                
                let cur = 0;
                const duration = 1500; // ms
                const interval = 30; // ms
                const step = Math.max(1, targetNum / (duration / interval));
                
                const timer = setInterval(() => {
                    cur += step;
                    if (cur >= targetNum) {
                        cur = targetNum;
                        clearInterval(timer);
                    }
                    el.textContent = Math.floor(cur);
                }, interval);
            });
            
            // Mouse move flare effect on welcome card
            const banner = document.querySelector('.bento-welcome');
            const flare = document.querySelector('.flare-effect');
            
            if(banner && flare) {
                banner.addEventListener('mousemove', (e) => {
                    const rect = banner.getBoundingClientRect();
                    const x = e.clientX - rect.left;
                    const y = e.clientY - rect.top;
                    flare.style.background = `radial-gradient(circle at ${x}px ${y}px, rgba(255,255,255,0.15) 0%, transparent 50%)`;
                });
            }
        });
    </script>
</body>

</html>