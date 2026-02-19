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

/* 3️⃣ Theo khoa (dùng FacultyID + Faculties)
   - Nếu FacultyID null/không khớp: gom vào 'Chưa gán khoa'
*/
/* 3️⃣ Theo khoa (hiển thị cả khoa chưa có sinh viên) */
$byFaculty = [];
$q2 = $conn->query("
    SELECT 
        f.FacultyName,
        COUNT(s.StudentID) AS c
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
/* Đang ở: có ít nhất 1 HĐ Status = 'Hiệu lực' */
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

/* Không ở: không có HĐ hiệu lực */
$outDorm = 0;
$resOut = $conn->query("
    SELECT COUNT(*) AS c
    FROM Students s
    LEFT JOIN Contracts c
        ON c.StudentID = s.StudentID
        AND c.Status = 'Hiệu lực'
    WHERE c.ContractID IS NULL
");
if ($resOut && $row = $resOut->fetch_assoc()) {
    $outDorm = (int)$row['c'];
}
?>
<!-- Page specific styles -->
<link rel="stylesheet" href="../../../assets/css/staff/students/staff_student_stats.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="dashboard-container">
        <!-- Header -->
        <div class="dashboard-header">
            <div class="header-content">
                <h1><i class="fas fa-chart-pie"></i> Thống kê Sinh viên</h1>
                <p>Tổng quan về số lượng, phân bố và tình trạng sinh viên</p>
                <a href="../../../modules/admin/dashboard.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Quay lại Dashboard
                </a>
            </div>
        </div>

        <!-- Stats Overview -->
        <div class="stats-overview">
            <!-- Card 1: Tổng sinh viên -->
            <div class="stat-card">
                <div class="stat-icon total">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <h3><?= number_format($total) ?></h3>
                    <p>Tổng số sinh viên</p>
                    <div class="stat-breakdown">
                        <span class="badge male"><i class="fas fa-mars"></i> <?= $gender['Nam'] ?></span>
                        <span class="badge female"><i class="fas fa-venus"></i> <?= $gender['Nữ'] ?></span>
                    </div>
                </div>
            </div>

            <!-- Card 2: Đang ở KTX -->
            <?php 
                $inDormPct = $total > 0 ? round(($inDorm / $total) * 100, 1) : 0;
            ?>
            <div class="stat-card">
                <div class="stat-icon dorm">
                    <i class="fas fa-bed"></i>
                </div>
                <div class="stat-content">
                    <h3><?= number_format($inDorm) ?></h3>
                    <p>Đang ở KTX</p>
                    <div class="stat-breakdown">
                        <span class="badge success"><i class="fas fa-check-circle"></i> <?= $inDormPct ?>% tổng số</span>
                    </div>
                </div>
            </div>

            <!-- Card 3: Không ở KTX -->
             <?php 
                $outDormPct = $total > 0 ? round(($outDorm / $total) * 100, 1) : 0;
            ?>
            <div class="stat-card">
                <div class="stat-icon nodorm">
                    <i class="fas fa-door-open"></i>
                </div>
                <div class="stat-content">
                    <h3><?= number_format($outDorm) ?></h3>
                    <p>Không ở KTX</p>
                    <div class="stat-breakdown">
                        <span class="badge warning"><i class="fas fa-exclamation-circle"></i> <?= $outDormPct ?>% tổng số</span>
                    </div>
                </div>
            </div>

            <!-- Card 4: Thông tin đào tạo -->
            <div class="stat-card">
                <div class="stat-icon faculty">
                    <i class="fas fa-university"></i>
                </div>
                <div class="stat-content">
                    <h3><?= count($byFaculty) ?></h3>
                    <p>Khoa đào tạo</p>
                     <div class="stat-breakdown">
                        <span class="badge info"><i class="fas fa-layer-group"></i> <?= count($byCourse) ?> Khóa học</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Grid -->
        <div class="charts-grid">
            <div class="chart-box">
                <h3><i class="fas fa-venus-mars"></i> Phân bố giới tính</h3>
                <canvas id="chartGender"></canvas>
            </div>

            <div class="chart-box">
                <h3><i class="fas fa-bed"></i> Tình trạng ở KTX</h3>
                <canvas id="chartDorm"></canvas>
            </div>

            <div class="chart-box">
                <h3><i class="fas fa-calendar-alt"></i> Sinh viên theo khóa học</h3>
                <canvas id="chartCourse"></canvas>
            </div>

            <div class="chart-box">
                 <h3><i class="fas fa-university"></i> Top Khoa đông sinh viên nhất</h3>
                 <canvas id="chartFaculty"></canvas>
            </div>
        </div>

        <!-- Detailed Table -->
        <div class="unified-box">
            <div class="box-header">
                <h3><i class="fas fa-list-ol"></i> Chi tiết theo Khoa</h3>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Tên Khoa</th>
                            <th class="text-center">Số lượng</th>
                            <th style="width: 40%">Tỷ lệ phần trăm</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Sort by count desc for better visualization
                        usort($byFaculty, function($a, $b) {
                            return $b['c'] - $a['c'];
                        });
                        
                        $max = $total > 0 ? $total : 1;
                        foreach ($byFaculty as $f):
                            $percent = round(($f['c'] / $max) * 100, 1);
                        ?>
                            <tr>
                                <td class="faculty-name">
                                    <?= htmlspecialchars($f['FacultyName']) ?>
                                </td>
                                <td class="count-col">
                                    <?= number_format($f['c']) ?>
                                </td>
                                <td>
                                    <div class="progress-wrapper">
                                        <div class="progress-bg">
                                            <div class="progress-bar" style="width: <?= $percent ?>%"></div>
                                        </div>
                                        <span class="pct"><?= $percent ?>%</span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Common Options
        const commonOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 20,
                        usePointStyle: true,
                    }
                }
            }
        };

        // 1. Giới tính (Doughnut)
        new Chart(document.getElementById('chartGender'), {
            type: 'doughnut',
            data: {
                labels: ['Nam', 'Nữ', 'Khác'],
                datasets: [{
                    data: [<?= $gender['Nam'] ?>, <?= $gender['Nữ'] ?>, <?= $gender['Khác'] ?>],
                    backgroundColor: ['#3b82f6', '#ec4899', '#9ca3af'],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                ...commonOptions,
                cutout: '65%'
            }
        });

        // 2. Tình trạng ở KTX (Pie)
        new Chart(document.getElementById('chartDorm'), {
            type: 'pie',
            data: {
                labels: ['Đang ở KTX', 'Không ở KTX'],
                datasets: [{
                    data: [<?= $inDorm ?>, <?= $outDorm ?>],
                    backgroundColor: ['#10b981', '#f59e0b'],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: commonOptions
        });

        // 3. Khóa học (Bar - Vertical)
        new Chart(document.getElementById('chartCourse'), {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_column($byCourse, 'CourseYear')) ?>,
                datasets: [{
                    label: 'Số sinh viên',
                    data: <?= json_encode(array_column($byCourse, 'c')) ?>,
                    backgroundColor: '#8b5cf6',
                    borderRadius: 6
                }]
            },
            options: {
                ...commonOptions,
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { borderDash: [2, 4], color: '#e5e7eb' }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });

        // 4. Khoa (Bar - Horizontal for readablity)
        // Taking top 5 faculties for chart
        <?php 
            $topFaculties = array_slice($byFaculty, 0, 8); 
        ?>
        new Chart(document.getElementById('chartFaculty'), {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_column($topFaculties, 'FacultyName')) ?>,
                datasets: [{
                    label: 'Số sinh viên',
                    data: <?= json_encode(array_column($topFaculties, 'c')) ?>,
                    backgroundColor: '#06d6a0',
                    borderRadius: 4
                }]
            },
            options: {
                ...commonOptions,
                indexAxis: 'y', // Horizontal bar
                scales: {
                    x: {
                        beginAtZero: true,
                         grid: { borderDash: [2, 4], color: '#e5e7eb' }
                    },
                    y: {
                        grid: { display: false }
                    }
                }
            }
        });
    </script>
    
    </main>
    <?php include '../../../includes/footer.php'; ?>
</body>
</html>
