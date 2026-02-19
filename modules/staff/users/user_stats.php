<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include '../../../db_connect.php';
include '../../../includes/admin_header.php';
include '../../../includes/auth_check.php';
requireRole(['Admin', 'Manager']);

// ─────────────────────────────────────────────────────────────
// 1) Truy vấn thống kê tổng quan + theo vai trò
// ─────────────────────────────────────────────────────────────

// Tổng người dùng
$totRes = $conn->query("SELECT COUNT(*) AS total FROM users");
$totalUsers = $totRes ? (int)$totRes->fetch_assoc()['total'] : 0;

// Mới hôm nay
$todayRes = $conn->query("SELECT COUNT(*) AS n FROM users WHERE DATE(CreatedAt)=CURDATE()");
$todayNew = $todayRes ? (int)$todayRes->fetch_assoc()['n'] : 0;

// Mới trong tháng này
$monthRes = $conn->query("
  SELECT COUNT(*) AS n 
  FROM users 
  WHERE YEAR(CreatedAt)=YEAR(CURDATE()) AND MONTH(CreatedAt)=MONTH(CURDATE())
");
$monthNew = $monthRes ? (int)$monthRes->fetch_assoc()['n'] : 0;

// Theo vai trò
$roles = ['Admin' => 0, 'Manager' => 0, 'Student' => 0];
$roleRes = $conn->query("SELECT Role, COUNT(*) AS c FROM users GROUP BY Role");
if ($roleRes) {
    while ($r = $roleRes->fetch_assoc()) {
        $roles[$r['Role']] = (int)$r['c'];
    }
}

// ─────────────────────────────────────────────────────────────
// 2) Tạo chuỗi 12 tháng gần nhất và đếm số user theo CreatedAt
// ─────────────────────────────────────────────────────────────
function lastMonths($n = 12)
{
    $out = [];
    for ($i = $n - 1; $i >= 0; $i--) {
        $ts = strtotime(date('Y-m-01') . " -$i month");
        $key = date('Y-m', $ts);
        $label = date('m/Y', $ts);
        $out[$key] = ['label' => $label, 'count' => 0];
    }
    return $out;
}

$months = lastMonths(12);
$seriesRes = $conn->query("
  SELECT DATE_FORMAT(CreatedAt,'%Y-%m') AS ym, COUNT(*) AS c
  FROM users
  WHERE CreatedAt >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 11 MONTH), '%Y-%m-01')
  GROUP BY ym
  ORDER BY ym ASC
");
if ($seriesRes) {
    while ($row = $seriesRes->fetch_assoc()) {
        $ym = $row['ym'];
        if (isset($months[$ym])) $months[$ym]['count'] = (int)$row['c'];
    }
}

// Tính tăng trưởng so với tháng trước
$vals = array_values(array_map(fn($x) => $x['count'], $months));
$growth = 0;
$growth_percent = 0;
if (count($vals) >= 2) {
    $curr = $vals[count($vals) - 1];
    $prev = $vals[count($vals) - 2];
    $growth = $curr - $prev;
    $growth_percent = $prev > 0 ? round(($growth / $prev) * 100, 1) : ($curr > 0 ? 100 : 0);
}

// ─────────────────────────────────────────────────────────────
// 3) Người dùng mới nhất
// ─────────────────────────────────────────────────────────────
$latest = [];
$lastRes = $conn->query("
  SELECT UserID, Username, FullName, Email, Role, CreatedAt
  FROM users
  ORDER BY CreatedAt DESC
  LIMIT 10
");
if ($lastRes) {
    while ($row = $lastRes->fetch_assoc()) {
        $latest[] = $row;
    }
}

// Dữ liệu cho JS
$labels = array_map(fn($x) => $x['label'], array_values($months));
$dataMonthly = array_map(fn($x) => $x['count'], array_values($months));
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thống kê người dùng | Hệ thống Ký túc xá</title>
    <link rel="stylesheet" href="../../assets/css/admin/admin_header.css">
    <link rel="stylesheet" href="../../../assets/css/staff/users/user_stats.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1"></script>
</head>

<body>
    <div class="container">
        <div class="page-header">
            <h2><i class="fas fa-users"></i> Thống kê người dùng</h2>
        </div>

        <!-- Thẻ tổng quan -->
        <div class="cards">
            <div class="card">
                <div class="k"><i class="fas fa-users"></i> Tổng người dùng</div>
                <div class="v"><?= number_format($totalUsers) ?></div>
                <div class="tiny"><i class="fas fa-clock"></i> Cập nhật: <?= date('d/m/Y H:i') ?></div>
                <div class="progress-indicator">
                    <span style="width: 100%;"></span>
                </div>
            </div>
            <div class="card">
                <div class="k"><i class="fas fa-user-plus"></i> Mới hôm nay</div>
                <div class="v" style="color:var(--success)">+<?= number_format($todayNew) ?></div>
                <div class="tiny">Đăng ký trong ngày hôm nay</div>
                <div class="progress-indicator">
                    <span style="width: <?= $totalUsers > 0 ? ($todayNew / $totalUsers * 100) : 0 ?>%;"></span>
                </div>
            </div>
            <div class="card">
                <div class="k"><i class="fas fa-calendar-alt"></i> Mới trong tháng</div>
                <div class="v"><?= number_format($monthNew) ?></div>
                <div class="tiny" style="color:<?= $growth >= 0 ? 'var(--success)' : 'var(--danger)' ?>">
                    <i class="fas fa-<?= $growth >= 0 ? 'arrow-up' : 'arrow-down' ?>"></i>
                    <?= $growth >= 0 ? '+' : '' ?><?= number_format($growth) ?> (<?= $growth_percent ?>%) so với tháng trước
                </div>
                <div class="progress-indicator">
                    <span style="width: <?= $totalUsers > 0 ? ($monthNew / $totalUsers * 100) : 0 ?>%;"></span>
                </div>
            </div>
            <div class="card">
                <div class="k"><i class="fas fa-chart-pie"></i> Phân bố theo vai trò</div>
                <div class="tiny">
                    <span class="role r-admin">Admin: <?= $roles['Admin'] ?></span>
                    <span class="role r-manager">Manager: <?= $roles['Manager'] ?></span>

                    <span class="role r-student">Student: <?= $roles['Student'] ?></span>
                </div>
                <canvas id="roleChart" height="120"></canvas>
            </div>
        </div>

        <div class="grid">
            <!-- Biểu đồ 12 tháng -->
            <div class="panel">
                <h3><i class="fas fa-chart-line"></i> Người dùng mới theo tháng (12 tháng gần nhất)</h3>
                <canvas id="monthlyChart" height="300"></canvas>
            </div>

            <!-- Người dùng mới nhất -->
            <div class="panel">
                <h3><i class="fas fa-user-plus"></i> Người dùng mới nhất</h3>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th><i class="fas fa-id-card"></i> ID</th>
                                <th><i class="fas fa-user"></i> Họ tên</th>
                                <th><i class="fas fa-at"></i> Username</th>
                                <th><i class="fas fa-user-tag"></i> Vai trò</th>
                                <th><i class="fas fa-calendar"></i> Ngày tạo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($latest)): ?>
                                <?php foreach ($latest as $u): ?>
                                    <tr>
                                        <td><strong>#<?= (int)$u['UserID'] ?></strong></td>
                                        <td><?= htmlspecialchars($u['FullName'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($u['Username']) ?></td>
                                        <td>
                                            <?php
                                            $role = strtolower($u['Role']);
                                            $cls = $role === 'admin' ? 'r-admin' : ($role === 'manager' ? 'r-manager' : 'r-student');
                                            ?>
                                            <span class="role <?= $cls ?>"><?= htmlspecialchars($u['Role']) ?></span>
                                        </td>
                                        <td><?= date('d/m/Y H:i', strtotime($u['CreatedAt'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: var(--gray); padding: 40px;">
                                        <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 16px; display: block;"></i>
                                        Không có dữ liệu người dùng
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Dữ liệu từ PHP
        const labels = <?= json_encode($labels, JSON_UNESCAPED_UNICODE) ?>;
        const dataMonthly = <?= json_encode($dataMonthly) ?>;

        // Biểu đồ đường: người dùng mới theo tháng
        new Chart(document.getElementById('monthlyChart'), {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    label: 'Người dùng mới',
                    data: dataMonthly,
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99, 102, 241, 0.1)',
                    tension: 0.4,
                    fill: true,
                    borderWidth: 3,
                    pointBackgroundColor: '#6366f1',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 6,
                    pointHoverRadius: 8
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
                                return `Người dùng mới: ${context.raw}`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
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

        // Biểu đồ tròn: phân bố vai trò
        const roleData = {
            labels: ['Admin', 'Manager', 'Student'],
            datasets: [{
                data: [<?= $roles['Admin'] ?>, <?= $roles['Manager'] ?>, <?= $roles['Student'] ?>],
                backgroundColor: [
                    'rgba(239, 68, 68, 0.8)', // Admin - red
                    'rgba(245, 158, 11, 0.8)', // Manager - orange  
                    'rgba(34, 197, 94, 0.8)' // Student - green
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

        new Chart(document.getElementById('roleChart'), {
            type: 'doughnut',
            data: roleData,
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
                                const total = <?= $totalUsers ?>;
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