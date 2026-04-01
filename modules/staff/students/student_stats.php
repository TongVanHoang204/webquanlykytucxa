<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include '../../../db_connect.php';
include '../../../includes/admin_header.php';
include '../../../includes/auth_check.php';
requireRole(['Admin', 'Manager']);

$conn->set_charset('utf8mb4');

/* 1️⃣ Tổng số sinh viên */
$total = 0;
$resTotal = $conn->query("SELECT COUNT(*) AS c FROM Students");
if ($resTotal && $row = $resTotal->fetch_assoc()) {
    $total = (int)$row['c'];
}

/* 2️⃣ Giới tính */
$gender = ['Nam' => 0, 'Nữ' => 0, 'Khác' => 0];
$q = $conn->query("SELECT Gender, COUNT(*) AS c FROM Students GROUP BY Gender");
if ($q) {
    while ($r = $q->fetch_assoc()) {
        $g = $r['Gender'] ?? 'Khác';
        if (!isset($gender[$g])) $g = 'Khác';
        $gender[$g] = (int)$r['c'];
    }
}

/* 3️⃣ Theo khoa */
$byFaculty = [];
$q2 = $conn->query("
    SELECT f.FacultyName, COUNT(s.StudentID) AS c
    FROM Faculties f
    LEFT JOIN Students s ON s.FacultyID = f.FacultyID
    GROUP BY f.FacultyID, f.FacultyName
    ORDER BY f.FacultyName ASC
");
if ($q2) {
    while ($r = $q2->fetch_assoc()) {
        $byFaculty[] = [
            'FacultyName' => $r['FacultyName'],
            'c' => (int)$r['c']
        ];
    }
}

/* 4️⃣ Theo năm học (CourseYear) */
$byCourse = [];
$q3 = $conn->query("
    SELECT CourseYear, COUNT(*) AS c
    FROM Students
    GROUP BY CourseYear
    ORDER BY CourseYear ASC
");
if ($q3) {
    while ($r = $q3->fetch_assoc()) {
        $byCourse[] = [
            'CourseYear' => $r['CourseYear'] ?: 'Chưa gán',
            'c' => (int)$r['c'],
        ];
    }
}

/* 5️⃣ Tình trạng ở ký túc xá (dựa trên HĐ hiệu lực) */
$inDorm = 0;
$resIn = $conn->query("
    SELECT COUNT(DISTINCT s.StudentID) AS c
    FROM Students s
    JOIN Contracts c ON c.StudentID = s.StudentID
    WHERE c.Status = 'Hiệu lực'
");
if ($resIn && $row = $resIn->fetch_assoc()) {
    $inDorm = (int)$row['c'];
}

$outDorm = 0;
$resOut = $conn->query("
    SELECT COUNT(*) AS c
    FROM Students s
    LEFT JOIN Contracts c ON c.StudentID = s.StudentID AND c.Status = 'Hiệu lực'
    WHERE c.ContractID IS NULL
");
if ($resOut && $row = $resOut->fetch_assoc()) {
    $outDorm = (int)$row['c'];
}

$inDormPct = $total > 0 ? round(($inDorm / $total) * 100, 1) : 0;
$outDormPct = $total > 0 ? round(($outDorm / $total) * 100, 1) : 0;

// Sort faculties by count desc for visualization
usort($byFaculty, function($a, $b) { return $b['c'] - $a['c']; });
$topFaculties = array_slice($byFaculty, 0, 8);
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thống kê sinh viên | Hệ thống Ký túc xá</title>
    <link rel="stylesheet" href="<?= $base ?>assets/css/global.css">
    <link rel="stylesheet" href="<?= $base ?>assets/css/modules_shared.css">
    <link rel="stylesheet" href="<?= $base ?>assets/css/admin/admin_header.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1"></script>
    <style>
        .chart-container { position: relative; height: 320px; width: 100%; }
        /* Progress line for table */
        .prog-line-wrapper { display: flex; align-items: center; gap: 10px; }
        .prog-line-bg { flex: 1; height: 8px; background: rgba(0,0,0,0.05); border-radius: 4px; overflow: hidden; }
        .prog-line-fill { height: 100%; border-radius: 4px; background: var(--primary); }
    </style>
</head>

<body>
<div class="mod-container">
    <!-- Header -->
    <div class="mod-header">
        <div class="mod-header-left">
            <h2><i class="fas fa-chart-pie"></i> Thống kê Sinh viên</h2>
        </div>
        <div class="mod-header-right">
            <a href="student_list.php" class="mod-btn mod-btn-outline mod-btn-sm">
                <i class="fas fa-list"></i> Danh sách sinh viên
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
                <span class="mod-stat-number"><?= number_format($total) ?></span>
                <span class="mod-stat-label">Tổng sinh viên</span>
            </div>
        </div>
        <div class="mod-stat accent-green">
            <div class="mod-stat-icon" style="background:var(--gradient-success);">
                <i class="fas fa-bed"></i>
            </div>
            <div class="mod-stat-info">
                <span class="mod-stat-number"><?= number_format($inDorm) ?></span>
                <span class="mod-stat-label">Đang ở KTX (<?= $inDormPct ?>%)</span>
            </div>
        </div>
        <div class="mod-stat accent-orange">
            <div class="mod-stat-icon" style="background:var(--gradient-warning);">
                <i class="fas fa-door-open"></i>
            </div>
            <div class="mod-stat-info">
                <span class="mod-stat-number"><?= number_format($outDorm) ?></span>
                <span class="mod-stat-label">Không ở KTX (<?= $outDormPct ?>%)</span>
            </div>
        </div>
        <div class="mod-stat accent-purple">
            <div class="mod-stat-icon" style="background:var(--gradient-info);">
                <i class="fas fa-university"></i>
            </div>
            <div class="mod-stat-info">
                <span class="mod-stat-number"><?= count($byFaculty) ?></span>
                <span class="mod-stat-label">Khoa đào tạo</span>
            </div>
        </div>
    </div>

    <!-- Charts Grid (2x2 style handled by mod-grid-2 nested) -->
    <div class="mod-grid-2" style="align-items:start;">
        <div class="mod-card">
            <div class="mod-card-header">
                <div class="mod-card-title"><i class="fas fa-venus-mars"></i> Phân bố giới tính</div>
            </div>
            <div class="mod-card-body">
                <div class="chart-container">
                    <canvas id="chartGender"></canvas>
                </div>
            </div>
        </div>
        
        <div class="mod-card">
            <div class="mod-card-header">
                <div class="mod-card-title"><i class="fas fa-bed"></i> Tình trạng ở KTX</div>
            </div>
            <div class="mod-card-body">
                <div class="chart-container">
                    <canvas id="chartDorm"></canvas>
                </div>
            </div>
        </div>
        
        <div class="mod-card">
            <div class="mod-card-header">
                <div class="mod-card-title"><i class="fas fa-layer-group"></i> Theo khóa học</div>
            </div>
            <div class="mod-card-body">
                <div class="chart-container">
                    <canvas id="chartCourse"></canvas>
                </div>
            </div>
        </div>
        
        <div class="mod-card">
            <div class="mod-card-header">
                <div class="mod-card-title"><i class="fas fa-university"></i> Top Khoa đông SV nhất</div>
            </div>
            <div class="mod-card-body">
                <div class="chart-container">
                    <canvas id="chartFaculty"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Bảng chi tiết -->
    <div class="mod-card">
        <div class="mod-card-header">
            <div class="mod-card-title"><i class="fas fa-list-ol"></i> Chi tiết theo Khoa</div>
        </div>
        <div class="mod-table-scroll" style="max-height: 500px;">
            <table class="mod-table">
                <thead>
                    <tr>
                        <th style="width:40%;">Tên Khoa</th>
                        <th style="width:20%;">Số lượng</th>
                        <th style="width:40%;">Tỷ lệ (%)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $maxVal = $total > 0 ? $total : 1;
                    foreach ($byFaculty as $f):
                        $percent = round(($f['c'] / $maxVal) * 100, 1);
                    ?>
                        <tr>
                            <td><span class="mod-fw-600"><?= htmlspecialchars($f['FacultyName']) ?></span></td>
                            <td><span class="mod-badge mod-badge-blue"><?= number_format($f['c']) ?></span></td>
                            <td>
                                <div class="prog-line-wrapper">
                                    <div class="prog-line-bg"><div class="prog-line-fill" style="width: <?= $percent ?>%"></div></div>
                                    <span class="mod-cell-muted" style="min-width:40px;text-align:right;"><?= $percent ?>%</span>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (count($byFaculty)===0): ?>
                        <tr><td colspan="3"><div class="mod-empty">Chưa có dữ liệu</div></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    const opts = { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { padding: 20, usePointStyle: true } } } };

    new Chart(document.getElementById('chartGender'), {
        type: 'doughnut',
        data: {
            labels: ['Nam', 'Nữ', 'Khác'],
            datasets: [{
                data: [<?= $gender['Nam'] ?>, <?= $gender['Nữ'] ?>, <?= $gender['Khác'] ?>],
                backgroundColor: ['rgba(59, 130, 246, 0.8)', 'rgba(236, 72, 153, 0.8)', 'rgba(156, 163, 175, 0.8)'],
                borderColor: ['#3b82f6', '#ec4899', '#9ca3af'], borderWidth: 2, hoverOffset: 10
            }]
        }, options: { ...opts, cutout: '65%' }
    });

    new Chart(document.getElementById('chartDorm'), {
        type: 'pie',
        data: {
            labels: ['Đang ở KTX', 'Không ở KTX'],
            datasets: [{
                data: [<?= $inDorm ?>, <?= $outDorm ?>],
                backgroundColor: ['rgba(16, 185, 129, 0.8)', 'rgba(245, 158, 11, 0.8)'],
                borderColor: ['#10b981', '#f59e0b'], borderWidth: 2, hoverOffset: 10
            }]
        }, options: opts
    });

    new Chart(document.getElementById('chartCourse'), {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_column($byCourse, 'CourseYear')) ?>,
            datasets: [{
                label: 'Số sinh viên',
                data: <?= json_encode(array_column($byCourse, 'c')) ?>,
                backgroundColor: 'rgba(139, 92, 246, 0.8)', borderColor: '#8b5cf6', borderWidth: 1, borderRadius: 4
            }]
        }, options: {
            ...opts, scales: { y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.06)' } }, x: { grid: { display: false } } }
        }
    });

    new Chart(document.getElementById('chartFaculty'), {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_column($topFaculties, 'FacultyName')) ?>,
            datasets: [{
                label: 'Số sinh viên',
                data: <?= json_encode(array_column($topFaculties, 'c')) ?>,
                backgroundColor: 'rgba(6, 214, 160, 0.8)', borderColor: '#06d6a0', borderWidth: 1, borderRadius: 4
            }]
        }, options: {
            ...opts, indexAxis: 'y', scales: { x: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.06)' } }, y: { grid: { display: false } } }
        }
    });
</script>
</body>
</html>
