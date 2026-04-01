<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include '../../../db_connect.php';
include '../../../includes/admin_header.php';
include '../../../includes/auth_check.php';
requireRole(['Admin', 'Manager']);

// 1) Truy vấn thống kê tổng quan + theo vai trò
$totRes = $conn->query("SELECT COUNT(*) AS total FROM users");
$totalUsers = $totRes ? (int)$totRes->fetch_assoc()['total'] : 0;

$todayRes = $conn->query("SELECT COUNT(*) AS n FROM users WHERE DATE(CreatedAt)=CURDATE()");
$todayNew = $todayRes ? (int)$todayRes->fetch_assoc()['n'] : 0;

$monthRes = $conn->query("
  SELECT COUNT(*) AS n 
  FROM users 
  WHERE YEAR(CreatedAt)=YEAR(CURDATE()) AND MONTH(CreatedAt)=MONTH(CURDATE())
");
$monthNew = $monthRes ? (int)$monthRes->fetch_assoc()['n'] : 0;

$roles = ['Admin' => 0, 'Manager' => 0, 'Student' => 0];
$roleRes = $conn->query("SELECT Role, COUNT(*) AS c FROM users GROUP BY Role");
if ($roleRes) {
    while ($r = $roleRes->fetch_assoc()) {
        $roles[$r['Role']] = (int)$r['c'];
    }
}

// 2) Đếm theo CreatedAt (12 tháng gần nhất)
function lastMonths($n = 12) {
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

$vals = array_values(array_map(fn($x) => $x['count'], $months));
$growth = 0; $growth_percent = 0;
if (count($vals) >= 2) {
    $curr = $vals[count($vals) - 1];
    $prev = $vals[count($vals) - 2];
    $growth = $curr - $prev;
    $growth_percent = $prev > 0 ? round(($growth / $prev) * 100, 1) : ($curr > 0 ? 100 : 0);
}

// 3) Người dùng mới nhất
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

$labels = array_map(fn($x) => $x['label'], array_values($months));
$dataMonthly = array_map(fn($x) => $x['count'], array_values($months));
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thống kê người dùng | Ký túc xá</title>
    <link rel="stylesheet" href="<?= $base ?>assets/css/global.css">
    <link rel="stylesheet" href="<?= $base ?>assets/css/modules_shared.css">
    <link rel="stylesheet" href="<?= $base ?>assets/css/admin/admin_header.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1"></script>
    <style>
        .chart-container { position: relative; height: 320px; width: 100%; }
    </style>
</head>

<body>
    <div class="mod-container">
        <!-- Header -->
        <div class="mod-header">
            <div class="mod-header-left">
                <h2><i class="fas fa-users-viewfinder"></i> Thống kê người dùng</h2>
            </div>
            <div class="mod-header-right">
                <a href="users.php" class="mod-btn mod-btn-outline mod-btn-sm">
                    <i class="fas fa-list"></i> Quản lý User
                </a>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="mod-stats mod-stagger">
            <div class="mod-stat accent-blue">
                <div class="mod-stat-icon" style="background:var(--gradient-primary);">
                    <i class="fas fa-users"></i>
                </div>
                <div class="mod-stat-info">
                    <span class="mod-stat-number"><?= number_format($totalUsers) ?></span>
                    <span class="mod-stat-label">Tổng người dùng</span>
                </div>
            </div>
            <div class="mod-stat accent-green">
                <div class="mod-stat-icon" style="background:var(--gradient-success);">
                    <i class="fas fa-user-plus"></i>
                </div>
                <div class="mod-stat-info">
                    <span class="mod-stat-number">+<?= number_format($todayNew) ?></span>
                    <span class="mod-stat-label">Đăng ký hôm nay</span>
                </div>
            </div>
            <div class="mod-stat accent-orange">
                <div class="mod-stat-icon" style="background:var(--gradient-warning);">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="mod-stat-info">
                    <span class="mod-stat-number"><?= number_format($monthNew) ?></span>
                    <span class="mod-stat-label">Đăng ký tháng này</span>
                </div>
            </div>
            <div class="mod-stat <?= $growth >= 0 ? 'accent-emerald' : 'accent-pink' ?>">
                <div class="mod-stat-icon" style="background:var(--gradient-<?= $growth >= 0 ? 'success' : 'danger' ?>);">
                    <i class="fas fa-<?= $growth >= 0 ? 'arrow-trend-up' : 'arrow-trend-down' ?>"></i>
                </div>
                <div class="mod-stat-info">
                    <span class="mod-stat-number"><?= $growth >= 0 ? '+' : '' ?><?= number_format($growth) ?></span>
                    <span class="mod-stat-label"><?= $growth_percent ?>% so với tháng trước</span>
                </div>
            </div>
        </div>

        <!-- Charts Grid 2 -->
        <div class="mod-grid-2" style="align-items:start;">
            <div class="mod-card">
                <div class="mod-card-header">
                    <div class="mod-card-title"><i class="fas fa-chart-line"></i> Người dùng mới (12 tháng gần nhất)</div>
                </div>
                <div class="mod-card-body">
                    <div class="chart-container">
                        <canvas id="monthlyChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="mod-card">
                <div class="mod-card-header">
                    <div class="mod-card-title"><i class="fas fa-chart-pie"></i> Phân bố vai trò</div>
                </div>
                <div class="mod-card-body">
                    <div class="chart-container">
                        <canvas id="roleChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bảng chi tiết Users -->
        <div class="mod-card">
            <div class="mod-card-header">
                <div class="mod-card-title"><i class="fas fa-user-clock"></i> 10 người dùng mới nhất</div>
            </div>
            <div class="mod-table-scroll" style="max-height: 400px;">
                <table class="mod-table">
                    <thead>
                        <tr>
                            <th>Mã KH</th>
                            <th>Người dùng</th>
                            <th>Vai trò</th>
                            <th>Thời gian đăng ký</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($latest) > 0): ?>
                            <?php foreach ($latest as $u): ?>
                                <?php
                                $rBadge = match(strtolower($u['Role'])) {
                                    'admin' => 'mod-badge-red',
                                    'manager' => 'mod-badge-amber',
                                    default => 'mod-badge-blue'
                                };
                                ?>
                                <tr>
                                    <td><span class="mod-fw-600">#<?= (int)$u['UserID'] ?></span></td>
                                    <td>
                                        <div class="mod-cell-name"><?= htmlspecialchars($u['FullName'] ?? 'N/A') ?></div>
                                        <div class="mod-cell-sub">@<?= htmlspecialchars($u['Username']) ?></div>
                                    </td>
                                    <td><span class="mod-badge <?= $rBadge ?>"><?= htmlspecialchars($u['Role']) ?></span></td>
                                    <td><span class="mod-cell-muted"><?= date('d/m/Y H:i', strtotime($u['CreatedAt'])) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4">
                                    <div class="mod-empty">
                                        <i class="fas fa-inbox"></i>
                                        <p>Không có dữ liệu người dùng</p>
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
        const labels = <?= json_encode($labels, JSON_UNESCAPED_UNICODE) ?>;
        const dataMonthly = <?= json_encode($dataMonthly) ?>;

        new Chart(document.getElementById('monthlyChart'), {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    label: 'Người dùng mới',
                    data: dataMonthly,
                    borderColor: 'rgba(99, 102, 241, 1)',
                    backgroundColor: 'rgba(99, 102, 241, 0.1)',
                    tension: 0.4, fill: true, borderWidth: 3,
                    pointBackgroundColor: 'rgba(99, 102, 241, 1)', pointBorderColor: '#fff',
                    pointBorderWidth: 2, pointRadius: 5, pointHoverRadius: 7
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: 'rgba(0,0,0,0.06)' } },
                    x: { grid: { display: false } }
                }
            }
        });

        const roleData = {
            labels: ['Admin', 'Manager', 'Student'],
            datasets: [{
                data: [<?= $roles['Admin'] ?>, <?= $roles['Manager'] ?>, <?= $roles['Student'] ?>],
                backgroundColor: ['rgba(239, 68, 68, 0.8)', 'rgba(245, 158, 11, 0.8)', 'rgba(59, 130, 246, 0.8)'],
                borderColor: ['rgb(239, 68, 68)', 'rgb(245, 158, 11)', 'rgb(59, 130, 246)'],
                borderWidth: 2, hoverOffset: 10
            }]
        };

        new Chart(document.getElementById('roleChart'), {
            type: 'doughnut',
            data: roleData,
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { padding: 20, usePointStyle: true } } },
                cutout: '65%'
            }
        });
    </script>
</body>
</html>