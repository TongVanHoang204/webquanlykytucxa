<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include '../../../db_connect.php';
include '../../../includes/admin_header.php';
include '../../../includes/auth_check.php';
requireRole(['Admin', 'Manager']);

// Tổng số phản ánh
$total = 0;
$processed = 0;
$pending = 0;
$processing = 0;

$q = $conn->query("SELECT Status, COUNT(*) AS Count FROM feedbacks GROUP BY Status");
if ($q) {
    while ($row = $q->fetch_assoc()) {
        switch ($row['Status']) {
            case 'Đã xử lý':
                $processed = $row['Count'];
                break;
            case 'Đang xử lý':
                $processing = $row['Count'];
                break;
            case 'Chưa xử lý':
            default:
                $pending += $row['Count'];
                break;
        }
        $total += $row['Count'];
    }
}

// Thống kê theo tháng (12 tháng gần nhất)
$monthly = [];
$res = $conn->query("
    SELECT DATE_FORMAT(CreatedAt, '%Y-%m') AS Month, COUNT(*) AS Count
    FROM feedbacks 
    WHERE CreatedAt >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(CreatedAt, '%Y-%m')
    ORDER BY Month ASC
");
if ($res) {
    while ($r = $res->fetch_assoc()) $monthly[] = $r;
}

$pending_rate = $total > 0 ? round(($pending / $total) * 100, 1) : 0;
$processing_rate = $total > 0 ? round(($processing / $total) * 100, 1) : 0;
$processed_rate = $total > 0 ? round(($processed / $total) * 100, 1) : 0;
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thống kê phản ánh | Quản lý KTX</title>
    <link rel="stylesheet" href="<?= $base ?>assets/css/global.css">
    <link rel="stylesheet" href="<?= $base ?>assets/css/modules_shared.css">
    <link rel="stylesheet" href="<?= $base ?>assets/css/admin/admin_header.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0"></script>
    <style>
        .chart-container { position: relative; height: 320px; width: 100%; }
    </style>
</head>

<body>
    <div class="mod-container">
        <!-- Header -->
        <div class="mod-header">
            <div class="mod-header-left">
                <h2><i class="fas fa-chart-pie"></i> Thống kê phản ánh</h2>
            </div>
            <div class="mod-header-right">
                <a href="feedback_list.php" class="mod-btn mod-btn-outline mod-btn-sm">
                    <i class="fas fa-arrow-left"></i> Danh sách
                </a>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="mod-stats mod-stagger">
            <div class="mod-stat accent-blue">
                <div class="mod-stat-icon" style="background:var(--gradient-primary);">
                    <i class="fas fa-inbox"></i>
                </div>
                <div class="mod-stat-info">
                    <span class="mod-stat-number"><?= number_format($total) ?></span>
                    <span class="mod-stat-label">Tổng phản ánh</span>
                </div>
            </div>
            <div class="mod-stat accent-pink">
                <div class="mod-stat-icon" style="background:var(--gradient-danger);">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="mod-stat-info">
                    <span class="mod-stat-number"><?= number_format($pending) ?></span>
                    <span class="mod-stat-label">Chưa xử lý (<?= $pending_rate ?>%)</span>
                </div>
            </div>
            <div class="mod-stat accent-orange">
                <div class="mod-stat-icon" style="background:var(--gradient-warning);">
                    <i class="fas fa-cog fa-spin"></i>
                </div>
                <div class="mod-stat-info">
                    <span class="mod-stat-number"><?= number_format($processing) ?></span>
                    <span class="mod-stat-label">Đang xử lý (<?= $processing_rate ?>%)</span>
                </div>
            </div>
            <div class="mod-stat accent-green">
                <div class="mod-stat-icon" style="background:var(--gradient-success);">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="mod-stat-info">
                    <span class="mod-stat-number"><?= number_format($processed) ?></span>
                    <span class="mod-stat-label">Đã xử lý (<?= $processed_rate ?>%)</span>
                </div>
            </div>
        </div>

        <!-- Charts Grid -->
        <div class="mod-grid-2" style="align-items:start;">
            <div class="mod-card">
                <div class="mod-card-header">
                    <div class="mod-card-title"><i class="fas fa-calendar-alt"></i> Phản ánh theo tháng (12 tháng gần nhất)</div>
                </div>
                <div class="mod-card-body">
                    <div class="chart-container">
                        <canvas id="chartMonth"></canvas>
                    </div>
                </div>
            </div>

            <div class="mod-card">
                <div class="mod-card-header">
                    <div class="mod-card-title"><i class="fas fa-tasks"></i> Tỷ lệ trạng thái</div>
                </div>
                <div class="mod-card-body">
                    <div class="chart-container">
                        <canvas id="chartStatus"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detailed Table -->
        <div class="mod-card">
            <div class="mod-card-header">
                <div class="mod-card-title"><i class="fas fa-list"></i> Chi tiết theo tháng</div>
            </div>
            <div class="mod-table-scroll" style="max-height: 400px;">
                <table class="mod-table">
                    <thead>
                        <tr>
                            <th>Tháng</th>
                            <th style="text-align:right;">Số lượng phản ánh</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($monthly) > 0): ?>
                            <?php foreach ($monthly as $m): ?>
                                <tr>
                                    <td>
                                        <i class="fas fa-calendar-alt" style="color:var(--text-secondary); margin-right:8px;"></i>
                                        <span class="mod-fw-600"><?= htmlspecialchars($m['Month']) ?></span>
                                    </td>
                                    <td style="text-align:right;">
                                        <span class="mod-badge mod-badge-blue"><?= number_format($m['Count']) ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="2">
                                    <div class="mod-empty">
                                        <i class="fas fa-inbox"></i>
                                        <p>Không có dữ liệu phản ánh</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        const monthLabels = <?= json_encode(array_column($monthly, 'Month')) ?>;
        const monthData = <?= json_encode(array_column($monthly, 'Count')) ?>;

        new Chart(document.getElementById('chartMonth'), {
            type: 'bar',
            data: {
                labels: monthLabels,
                datasets: [{
                    label: 'Số phản ánh',
                    data: monthData,
                    backgroundColor: 'rgba(79, 70, 229, 0.8)',
                    borderColor: 'rgba(79, 70, 229, 1)',
                    borderWidth: 1,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: 'rgba(0,0,0,0.06)' } },
                    x: { grid: { display: false } }
                }
            }
        });

        const statusData = {
            labels: ['Chưa xử lý', 'Đang xử lý', 'Đã xử lý'],
            datasets: [{
                data: [<?= $pending ?>, <?= $processing ?>, <?= $processed ?>],
                backgroundColor: ['rgba(239, 68, 68, 0.8)', 'rgba(245, 158, 11, 0.8)', 'rgba(16, 185, 129, 0.8)'],
                borderColor: ['rgb(239, 68, 68)', 'rgb(245, 158, 11)', 'rgb(16, 185, 129)'],
                borderWidth: 2,
                hoverOffset: 10
            }]
        };

        new Chart(document.getElementById('chartStatus'), {
            type: 'doughnut',
            data: statusData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { padding: 20, usePointStyle: true } }
                },
                cutout: '65%'
            }
        });
    </script>
</body>
</html>