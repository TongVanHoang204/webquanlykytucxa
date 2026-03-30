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

// Đếm theo trạng thái
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

// Tính tỷ lệ phần trăm
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
    <link rel="stylesheet" href="../../assets/css/admin/admin_header.css">
    <link rel="stylesheet" href="../../../assets/css/staff/feedback/staff_feedback_stats.css">
    <link rel="stylesheet" href="../../../assets/vendor/fontawesome/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
    <div class="feedback-stats-container">
        <div class="page-header">
            <h2><i class="fas fa-chart-line"></i> Thống kê phản ánh</h2>
            <a href="feedback_list.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Quay lại danh sách
            </a>
        </div>

        <!-- Tổng quan -->
        <div class="stats-cards">
            <div class="card">
                <div class="title"><i class="fas fa-inbox"></i> Tổng số phản ánh</div>
                <div class="value"><?= number_format($total) ?></div>
                <div class="progress-indicator">
                    <span></span>
                </div>
            </div>
            <div class="card">
                <div class="title"><i class="fas fa-clock"></i> Chưa xử lý</div>
                <div class="value pending"><?= number_format($pending) ?></div>
                <div class="progress-indicator">
                    <span></span>
                </div>
                <div style="margin-top: 8px; font-size: 0.9rem; color: var(--gray);">
                    <?= $pending_rate ?>% tổng số
                </div>
            </div>
            <div class="card">
                <div class="title"><i class="fas fa-cog"></i> Đang xử lý</div>
                <div class="value processing"><?= number_format($processing) ?></div>
                <div class="progress-indicator">
                    <span></span>
                </div>
                <div style="margin-top: 8px; font-size: 0.9rem; color: var(--gray);">
                    <?= $processing_rate ?>% tổng số
                </div>
            </div>
            <div class="card">
                <div class="title"><i class="fas fa-check-circle"></i> Đã xử lý</div>
                <div class="value processed"><?= number_format($processed) ?></div>
                <div class="progress-indicator">
                    <span></span>
                </div>
                <div style="margin-top: 8px; font-size: 0.9rem; color: var(--gray);">
                    <?= $processed_rate ?>% tổng số
                </div>
            </div>
        </div>

        <!-- Biểu đồ -->
        <div class="charts">
            <div class="chart-box">
                <h3><i class="fas fa-calendar-alt"></i> Phản ánh theo tháng (12 tháng gần nhất)</h3>
                <canvas id="chartMonth"></canvas>
            </div>

            <div class="chart-box">
                <h3><i class="fas fa-tasks"></i> Tỷ lệ trạng thái</h3>
                <canvas id="chartStatus"></canvas>
            </div>
        </div>

        <!-- Bảng chi tiết -->
        <div class="block">
            <h3><i class="fas fa-list"></i> Chi tiết thống kê theo tháng</h3>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th><i class="fas fa-calendar"></i> Tháng</th>
                            <th><i class="fas fa-chart-bar"></i> Số lượng phản ánh</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($monthly) > 0): ?>
                            <?php foreach ($monthly as $m): ?>
                                <tr>
                                    <td>
                                        <i class="fas fa-calendar-alt" style="color: var(--gray); margin-right: 8px;"></i>
                                        <?= htmlspecialchars($m['Month']) ?>
                                    </td>
                                    <td>
                                        <strong><?= number_format($m['Count']) ?></strong>
                                        <span style="color: var(--gray); font-size: 0.9rem; margin-left: 8px;">
                                            phản ánh
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="2" style="text-align: center; color: var(--gray); padding: 40px;">
                                    <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 16px; display: block;"></i>
                                    Không có dữ liệu phản ánh
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Dữ liệu biểu đồ theo tháng
        const monthLabels = <?= json_encode(array_column($monthly, 'Month')) ?>;
        const monthData = <?= json_encode(array_column($monthly, 'Count')) ?>;

        // Biểu đồ cột - Phản ánh theo tháng
        new Chart(document.getElementById('chartMonth'), {
            type: 'bar',
            data: {
                labels: monthLabels,
                datasets: [{
                    label: 'Số phản ánh',
                    data: monthData,
                    backgroundColor: 'rgba(99, 102, 241, 0.8)',
                    borderColor: 'rgba(99, 102, 241, 1)',
                    borderWidth: 1,
                    borderRadius: 4,
                    borderSkipped: false,
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `Số phản ánh: ${context.raw}`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                },
                animation: {
                    duration: 1000,
                    easing: 'easeOutQuart'
                }
            }
        });

        // Biểu đồ tròn - Tỷ lệ trạng thái
        const statusData = {
            labels: ['Chưa xử lý', 'Đang xử lý', 'Đã xử lý'],
            datasets: [{
                data: [<?= $pending ?>, <?= $processing ?>, <?= $processed ?>],
                backgroundColor: [
                    'rgba(239, 68, 68, 0.8)',
                    'rgba(245, 158, 11, 0.8)',
                    'rgba(34, 197, 94, 0.8)'
                ],
                borderColor: [
                    'rgb(239, 68, 68)',
                    'rgb(245, 158, 11)',
                    'rgb(34, 197, 94)'
                ],
                borderWidth: 2,
                hoverOffset: 15
            }]
        };

        new Chart(document.getElementById('chartStatus'), {
            type: 'doughnut',
            data: statusData,
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label;
                                const value = context.raw;
                                const total = <?= $total ?>;
                                const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                },
                cutout: '60%',
                animation: {
                    animateScale: true,
                    animateRotate: true
                }
            }
        });

        // Animation cho progress bars
        document.addEventListener('DOMContentLoaded', function() {
            const progressBars = document.querySelectorAll('.progress-indicator span');
            progressBars.forEach(bar => {
                const width = bar.style.width;
                bar.style.width = '0';
                setTimeout(() => {
                    bar.style.width = width;
                }, 500);
            });
        });
    </script>
</body>

</html>