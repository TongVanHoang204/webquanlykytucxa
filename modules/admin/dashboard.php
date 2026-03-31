<?php
if (session_status() === PHP_SESSION_NONE) session_start();

include '../../db_connect.php';
include '../../includes/auth_check.php';
requireRole(['Admin']);

// -------------------------------
// KPI Counts
// -------------------------------
$unpaidCount        = 0; $feedbackPending = 0;
$activeContracts    = 0; $availableRooms  = 0; $totalStudents = 0;
$recentActivities   = [];

$res = $conn->query("SELECT COUNT(*) AS cnt FROM Invoices WHERE Status='Chưa thanh toán'");
if ($res && $row = $res->fetch_assoc()) $unpaidCount = (int)$row['cnt'];

$res = $conn->query("SELECT COUNT(*) AS cnt FROM Feedbacks WHERE Status IN ('Chưa xử lý','Đang xử lý')");
if ($res && $row = $res->fetch_assoc()) $feedbackPending = (int)$row['cnt'];

$res = $conn->query("SELECT COUNT(*) AS cnt FROM Contracts WHERE Status='Hiệu lực'");
if ($res && $row = $res->fetch_assoc()) $activeContracts = (int)$row['cnt'];

$res = $conn->query("SELECT COUNT(*) AS cnt FROM Rooms WHERE Status='Trống'");
if ($res && $row = $res->fetch_assoc()) $availableRooms = (int)$row['cnt'];

$res = $conn->query("SELECT COUNT(*) AS cnt FROM Students");
if ($res && $row = $res->fetch_assoc()) $totalStudents = (int)$row['cnt'];

$res = $conn->query("
  (SELECT 'feedback' as type, Content as title, CreatedAt as date FROM Feedbacks ORDER BY CreatedAt DESC LIMIT 3)
  UNION ALL
  (SELECT 'invoice' as type, CONCAT('Hóa đơn #', InvoiceID) as title, CreatedAt as date FROM Invoices ORDER BY CreatedAt DESC LIMIT 3)
  UNION ALL
  (SELECT 'contract' as type, CONCAT('Hợp đồng #', ContractID) as title, CreatedAt as date FROM Contracts ORDER BY CreatedAt DESC LIMIT 2)
  ORDER BY date DESC LIMIT 5
");
if ($res) { while ($row = $res->fetch_assoc()) $recentActivities[] = $row; }

// ==============================
// CHART DATA
// ==============================
$chartRevLabels = []; $chartRevData = [];
for ($i = 5; $i >= 0; $i--) {
    $m = date('m', strtotime("-$i months"));
    $y = date('Y', strtotime("-$i months"));
    $chartRevLabels[] = date('m/Y', strtotime("-$i months"));
    $r = $conn->query("SELECT COALESCE(SUM(Amount),0) s FROM payments WHERE Status='Đã xác nhận' AND MONTH(PaymentDate)=$m AND YEAR(PaymentDate)=$y");
    $chartRevData[] = (float)($r ? $r->fetch_assoc()['s'] : 0);
}

$roomLabels = []; $roomData = [];
$rs = $conn->query("SELECT Status, COUNT(*) cnt FROM rooms GROUP BY Status");
while ($r = $rs->fetch_assoc()) { $roomLabels[] = $r['Status']; $roomData[] = (int)$r['cnt']; }

$fbLabels = []; $fbData = [];
$fs = $conn->query("SELECT Status, COUNT(*) cnt FROM feedbacks GROUP BY Status");
while ($r = $fs->fetch_assoc()) { $fbLabels[] = $r['Status']; $fbData[] = (int)$r['cnt']; }

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
                    <a href="../admin/analytics.php"><i class="fas fa-chart-pie"></i> <span>Analytics Dashboard</span></a>
                    <a href="../admin/report/finance_report.php"><i class="fas fa-money-bill-wave"></i> <span>Báo cáo tài chính</span></a>
                    <a href="../../modules/staff/rooms/room_occupancy.php"><i class="fas fa-bed"></i> <span>Tỷ lệ lấp đầy phòng</span></a>
                    <a href="../../modules/staff/feedbacks/feedback_stats.php"><i class="fas fa-chart-bar"></i> <span>Thống kê phản ánh</span></a>
                    <a href="../../modules/staff/users/user_stats.php"><i class="fas fa-user"></i> <span>Thống kê người dùng</span></a>
                </div>
            </div>
        </div>

        <!-- ==================== CHARTS SECTION ==================== -->
        <div style="margin-top: 40px;">
            <h2 style="font-size:1.4rem; font-weight:700; color:var(--text); margin-bottom:20px; display:flex; align-items:center; gap:10px;">
                <span style="background:linear-gradient(135deg,#4361ee,#7209b7); width:36px; height:36px; border-radius:10px; display:inline-flex; align-items:center; justify-content:center; color:#fff; font-size:1rem;"><i class="fas fa-chart-pie"></i></span>
                Biểu đồ tổng hợp
            </h2>
            <div style="display:grid; grid-template-columns: 2fr 1fr 1fr; gap:20px;">

                <!-- Doanh thu 6 tháng -->
                <div style="background:var(--card); border:1px solid var(--stroke); border-radius:14px; padding:20px;">
                    <h3 style="font-size:0.95rem; font-weight:600; color:var(--text); margin:0 0 16px; display:flex; align-items:center; gap:8px;">
                        <i class="fas fa-chart-line" style="color:#4361ee;"></i> Doanh thu 6 tháng gần nhất
                    </h3>
                    <div style="height:220px; position:relative;">
                        <canvas id="adminRevChart"></canvas>
                    </div>
                </div>

                <!-- Trạng thái phòng -->
                <div style="background:var(--card); border:1px solid var(--stroke); border-radius:14px; padding:20px;">
                    <h3 style="font-size:0.95rem; font-weight:600; color:var(--text); margin:0 0 16px; display:flex; align-items:center; gap:8px;">
                        <i class="fas fa-door-closed" style="color:#10b981;"></i> Tình trạng phòng
                    </h3>
                    <div style="height:220px; position:relative;">
                        <canvas id="adminRoomChart"></canvas>
                    </div>
                </div>

                <!-- Phản ánh -->
                <div style="background:var(--card); border:1px solid var(--stroke); border-radius:14px; padding:20px;">
                    <h3 style="font-size:0.95rem; font-weight:600; color:var(--text); margin:0 0 16px; display:flex; align-items:center; gap:8px;">
                        <i class="fas fa-comments" style="color:#f59e0b;"></i> Phản ánh
                    </h3>
                    <div style="height:220px; position:relative;">
                        <canvas id="adminFbChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <!-- END CHARTS -->

    </div>

    <?php include '../../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Hover effects
            const cards = document.querySelectorAll('.stat-card, .action-card');
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = this.classList.contains('stat-card') ?
                        'translateY(-8px) scale(1.02)' : 'translateY(-5px) scale(1.01)';
                });
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0) scale(1)';
                });
            });

            // Count-up
            const statNumbers = document.querySelectorAll('.stat-card p');
            statNumbers.forEach(stat => {
                const finalNumber = parseInt(stat.textContent);
                if (isNaN(finalNumber)) return;
                let cur = 0;
                const inc = Math.max(1, Math.ceil(finalNumber / 30));
                const timer = setInterval(() => {
                    cur = Math.min(cur + inc, finalNumber);
                    stat.textContent = cur + ' ' + (stat.textContent.split(' ')[1] || '');
                    if (cur >= finalNumber) clearInterval(timer);
                }, 50);
            });

            // ---- Chart helpers ----
            const isDark = () => document.body.getAttribute('data-theme') === 'dark';
            const gridC  = () => isDark() ? 'rgba(255,255,255,0.07)' : 'rgba(0,0,0,0.06)';
            const tickC  = () => isDark() ? '#94a3b8' : '#6b7280';
            const legC   = () => isDark() ? '#e2e8f0' : '#374151';
            const tooltip = {
                backgroundColor: isDark() ? '#1e293b' : '#fff',
                titleColor: isDark() ? '#e2e8f0' : '#111',
                bodyColor:  isDark() ? '#94a3b8' : '#374151',
                borderColor: isDark() ? '#334155' : '#e5e7eb',
                borderWidth: 1, padding: 10, cornerRadius: 8
            };
            Chart.defaults.font.family = "'Inter', 'Segoe UI', sans-serif";

            // 1. Revenue line chart
            const revCtx = document.getElementById('adminRevChart').getContext('2d');
            const grad = revCtx.createLinearGradient(0, 0, 0, 220);
            grad.addColorStop(0, 'rgba(67,97,238,0.4)');
            grad.addColorStop(1, 'rgba(67,97,238,0)');
            new Chart(revCtx, {
                type: 'line',
                data: {
                    labels: <?= json_encode($chartRevLabels) ?>,
                    datasets: [{
                        data: <?= json_encode($chartRevData) ?>,
                        borderColor: '#4361ee',
                        backgroundColor: grad,
                        borderWidth: 2.5,
                        pointRadius: 4,
                        pointBackgroundColor: '#4361ee',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: false }, tooltip: { ...tooltip, callbacks: { label: c => ' ' + Number(c.parsed.y).toLocaleString('vi-VN') + ' đ' } } },
                    scales: {
                        x: { grid: { color: gridC() }, ticks: { color: tickC(), font: { size: 11 } } },
                        y: { grid: { color: gridC() }, ticks: { color: tickC(), font: { size: 11 }, callback: v => (v/1000000).toFixed(0) + 'M' } }
                    }
                }
            });

            // 2. Room donut
            new Chart(document.getElementById('adminRoomChart').getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: <?= json_encode($roomLabels) ?>,
                    datasets: [{ data: <?= json_encode($roomData) ?>, backgroundColor: ['#4361ee','#10b981','#f59e0b','#6b7280'], borderWidth: 0, hoverOffset: 6 }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    cutout: '65%',
                    plugins: { legend: { position: 'bottom', labels: { color: legC(), padding: 10, font: { size: 11 } } }, tooltip }
                }
            });

            // 3. Feedbacks bar
            new Chart(document.getElementById('adminFbChart').getContext('2d'), {
                type: 'bar',
                data: {
                    labels: <?= json_encode($fbLabels) ?>,
                    datasets: [{ data: <?= json_encode($fbData) ?>, backgroundColor: ['#f59e0b','#3b82f6','#10b981','#6b7280'], borderRadius: 8, borderSkipped: false }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: false }, tooltip },
                    scales: {
                        x: { grid: { display: false }, ticks: { color: tickC(), font: { size: 10 } } },
                        y: { grid: { color: gridC() }, ticks: { color: tickC(), precision: 0 } }
                    }
                }
            });
        });
    </script>
</body>
</html>