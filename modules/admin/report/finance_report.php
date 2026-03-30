<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include '../../../db_connect.php';
include '../../../includes/admin_header.php';
include '../../../includes/auth_check.php';
requireRole(['Admin']);

// --- Helper function ---
function money_vn($n)
{
    return number_format((float)$n, 0, ',', '.') . ' ₫';
}

// --- Lọc ---
$range   = $_GET['range']   ?? 'year';  // 'month','year','custom'
$status  = $_GET['status']  ?? 'all';   // 'all','Đã thanh toán','Chưa thanh toán','Quá hạn'
$from    = $_GET['from']    ?? date('Y-m-01');
$to      = $_GET['to']      ?? date('Y-m-t');
$year    = intval($_GET['year'] ?? date('Y'));
$month   = $_GET['month']   ?? date('Y-m'); // yyyy-mm

// Chuẩn hoá khoảng thời gian theo phạm vi
if ($range === 'month') {
    $from = date('Y-m-01', strtotime($month . '-01'));
    $to   = date('Y-m-t',  strtotime($month . '-01'));
} elseif ($range === 'year') {
    $from = "$year-01-01";
    $to   = "$year-12-31";
}

// --- Điều kiện WHERE chung ---
$where = "i.CreatedAt BETWEEN ? AND ?";
$params = [$from . " 00:00:00", $to . " 23:59:59"];
$types  = "ss";

if ($status !== 'all') {
    $where .= " AND i.Status = ?";
    $params[] = $status;
    $types   .= "s";
}

// --- KPI tổng quan ---
$kpiSql = "
  SELECT
    SUM(CASE WHEN i.Status='Đã thanh toán' THEN i.TotalAmount ELSE 0 END) AS total_paid,
    SUM(CASE WHEN i.Status IN ('Chưa thanh toán','Quá hạn') THEN i.TotalAmount ELSE 0 END) AS total_unpaid,
    SUM(CASE WHEN i.Status='Quá hạn' THEN i.TotalAmount ELSE 0 END) AS total_overdue,
    COUNT(*) AS invoice_count
  FROM Invoices i
  WHERE $where
";
$kpi = $conn->prepare($kpiSql);
$kpi->bind_param($types, ...$params);
$kpi->execute();
$kpiRes = $kpi->get_result()->fetch_assoc() ?: ['total_paid' => 0, 'total_unpaid' => 0, 'total_overdue' => 0, 'invoice_count' => 0];

// --- Series theo tháng (12 cột: Paid/Unpaid) cho năm được chọn (để vẽ biểu đồ) ---
$seriesYear = $year;
$seriesSql = "
  SELECT i.Month, i.Year,
    SUM(CASE WHEN i.Status='Đã thanh toán' THEN i.TotalAmount ELSE 0 END) AS paid,
    SUM(CASE WHEN i.Status IN ('Chưa thanh toán','Quá hạn') THEN i.TotalAmount ELSE 0 END) AS unpaid
  FROM Invoices i
  WHERE i.Year = ?
  GROUP BY i.Year, i.Month
  ORDER BY i.Year, i.Month
";
$st = $conn->prepare($seriesSql);
$st->bind_param("i", $seriesYear);
$st->execute();
$series = array_fill(1, 12, ['paid' => 0, 'unpaid' => 0]);
$r = $st->get_result();
while ($row = $r->fetch_assoc()) {
    $m = (int)$row['Month'];
    $series[$m] = ['paid' => (float)$row['paid'], 'unpaid' => (float)$row['unpaid']];
}

// --- Top nợ (top 10 sinh viên nợ nhiều nhất trong khoảng lọc) ---
$debtSql = "
  SELECT s.FullName, s.StudentCode,
         SUM(i.TotalAmount) AS debt
  FROM Invoices i
  JOIN Contracts c ON i.ContractID=c.ContractID
  JOIN Students s ON c.StudentID=s.StudentID
  WHERE $where AND i.Status IN ('Chưa thanh toán','Quá hạn')
  GROUP BY s.StudentID
  ORDER BY debt DESC
  LIMIT 10
";
$debt = $conn->prepare($debtSql);
$debt->bind_param($types, ...$params);
$debt->execute();
$debtRes = $debt->get_result();

// --- Bảng chi tiết hoá đơn theo lọc ---
$listSql = "
  SELECT i.InvoiceID, i.Month, i.Year, i.TotalAmount, i.Status,
         i.CreatedAt, i.DueDate, i.PaidAt,
         s.FullName, s.StudentCode,
         r.RoomNumber, b.BuildingName
  FROM Invoices i
  JOIN Contracts c ON i.ContractID=c.ContractID
  JOIN Students s ON c.StudentID=s.StudentID
  JOIN Rooms r ON c.RoomID=r.RoomID
  JOIN Buildings b ON r.BuildingID=b.BuildingID
  WHERE $where
  ORDER BY i.Year DESC, i.Month DESC, i.InvoiceID DESC
";
$list = $conn->prepare($listSql);
$list->bind_param($types, ...$params);
$list->execute();
$listRes = $list->get_result();
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Báo cáo tài chính | KTX</title>
    <link rel="stylesheet" href="../../../assets/css/admin/report/admin_finance.css">
    <link rel="stylesheet" href="../../../assets/vendor/fontawesome/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0"></script>
</head>

<body>
    <div class="wrap">
        <!-- Header -->
        <div class="page-header">
            <h2><i class="fas fa-chart-line"></i> Báo cáo tài chính</h2>
            <div class="export">
                <form method="get" action="finance_export_csv.php" class="export-form">
                    <input type="hidden" name="range" value="<?= htmlspecialchars($range) ?>">
                    <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
                    <input type="hidden" name="from" value="<?= htmlspecialchars($from) ?>">
                    <input type="hidden" name="to" value="<?= htmlspecialchars($to) ?>">
                    <input type="hidden" name="year" value="<?= htmlspecialchars($year) ?>">
                    <input type="hidden" name="month" value="<?= htmlspecialchars($month) ?>">
                    <button type="submit" class="btn primary" id="exportDetail">
                        <i class="fas fa-file-csv"></i> Xuất CSV chi tiết
                    </button>
                </form>
                <form method="get" action="finance_export_csv.php" class="export-form">
                    <input type="hidden" name="summary" value="1">
                    <input type="hidden" name="year" value="<?= htmlspecialchars($seriesYear) ?>">
                    <button type="submit" class="btn secondary" id="exportSummary">
                        <i class="fas fa-table"></i> CSV theo tháng
                    </button>
                </form>
                <button class="btn" onclick="window.print()">
                    <i class="fas fa-print"></i> In báo cáo
                </button>
            </div>
        </div>

        <!-- Filters -->
        <form class="filters" method="get" id="filterForm">
            <div class="row">
                <label for="range"><i class="fas fa-calendar-alt"></i> Phạm vi thời gian</label>
                <select name="range" id="range" onchange="toggleDateInputs()">
                    <option value="month" <?= $range === 'month' ? 'selected' : ''; ?>>Theo tháng</option>
                    <option value="year" <?= $range === 'year' ? 'selected' : ''; ?>>Theo năm</option>
                    <option value="custom" <?= $range === 'custom' ? 'selected' : ''; ?>>Tuỳ chọn</option>
                </select>
            </div>

            <div class="row" id="monthInput" style="display: <?= $range === 'month' ? 'block' : 'none' ?>">
                <label for="month"><i class="fas fa-calendar-day"></i> Chọn tháng</label>
                <input type="month" name="month" id="month" value="<?= htmlspecialchars(date('Y-m', strtotime($from))) ?>">
            </div>

            <div class="row" id="yearInput" style="display: <?= $range === 'year' ? 'block' : 'none' ?>">
                <label for="year"><i class="fas fa-calendar-week"></i> Chọn năm</label>
                <select name="year" id="year">
                    <?php for ($y = date('Y') - 2; $y <= date('Y') + 1; $y++): ?>
                        <option value="<?= $y ?>" <?= $y == $year ? 'selected' : ''; ?>>Năm <?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="row" id="customInput" style="display: <?= $range === 'custom' ? 'block' : 'none' ?>">
                <label for="from"><i class="fas fa-calendar"></i> Từ ngày</label>
                <input type="date" name="from" id="from" value="<?= htmlspecialchars($from) ?>">
                <label for="to" style="margin-top: 8px;">Đến ngày</label>
                <input type="date" name="to" id="to" value="<?= htmlspecialchars($to) ?>">
            </div>

            <div class="row">
                <label for="status"><i class="fas fa-filter"></i> Trạng thái</label>
                <select name="status" id="status">
                    <option value="all" <?= $status === 'all' ? 'selected' : ''; ?>>Tất cả trạng thái</option>
                    <option value="Đã thanh toán" <?= $status === 'Đã thanh toán' ? 'selected' : ''; ?>>Đã thanh toán</option>
                    <option value="Chưa thanh toán" <?= $status === 'Chưa thanh toán' ? 'selected' : ''; ?>>Chưa thanh toán</option>
                    <option value="Quá hạn" <?= $status === 'Quá hạn' ? 'selected' : ''; ?>>Quá hạn</option>
                </select>
            </div>

            <div class="row">
                <button type="submit">
                    <i class="fas fa-filter"></i> Áp dụng bộ lọc
                </button>
            </div>
        </form>

        <!-- KPIs -->
        <div class="kpis">
            <div class="kpi">
                <div class="label"><i class="fas fa-check-circle"></i> Đã thu</div>
                <div class="val"><?= money_vn($kpiRes['total_paid'] ?? 0) ?></div>
                <div class="trend" style="color: var(--success); font-size: 0.9rem; margin-top: 8px;">
                    <i class="fas fa-arrow-up"></i> Hoàn thành
                </div>
            </div>
            <div class="kpi">
                <div class="label"><i class="fas fa-clock"></i> Chưa thu</div>
                <div class="val"><?= money_vn($kpiRes['total_unpaid'] ?? 0) ?></div>
                <div class="trend" style="color: var(--warning); font-size: 0.9rem; margin-top: 8px;">
                    <i class="fas fa-exclamation-triangle"></i> Đang chờ
                </div>
            </div>
            <div class="kpi">
                <div class="label"><i class="fas fa-exclamation-triangle"></i> Quá hạn</div>
                <div class="val"><?= money_vn($kpiRes['total_overdue'] ?? 0) ?></div>
                <div class="trend" style="color: var(--danger); font-size: 0.9rem; margin-top: 8px;">
                    <i class="fas fa-arrow-down"></i> Cần xử lý
                </div>
            </div>
            <div class="kpi">
                <div class="label"><i class="fas fa-receipt"></i> Tổng hoá đơn</div>
                <div class="val"><?= (int)($kpiRes['invoice_count'] ?? 0) ?></div>
                <div class="trend" style="color: var(--primary); font-size: 0.9rem; margin-top: 8px;">
                    <i class="fas fa-chart-bar"></i> Tổng số
                </div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="grid">
            <!-- Biểu đồ -->
            <div class="card">
                <h3><i class="fas fa-chart-column"></i> Thu/Chưa thu theo tháng (<?= $seriesYear ?>)</h3>
                <div class="chart-container">
                    <canvas id="monthlyChart"></canvas>
                </div>
            </div>

            <!-- Top nợ -->
            <div class="card">
                <h3><i class="fas fa-user-minus"></i> Top 10 nợ cao nhất</h3>
                <div style="max-height: 400px; overflow-y: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Sinh viên</th>
                                <th>Số nợ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($debtRes->num_rows > 0): ?>
                                <?php while ($d = $debtRes->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($d['FullName']) ?></strong>
                                            <br>
                                            <small style="color: var(--gray);"><?= htmlspecialchars($d['StudentCode']) ?></small>
                                        </td>
                                        <td>
                                            <strong style="color: var(--danger);"><?= money_vn($d['debt']) ?></strong>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="2" style="text-align: center; color: var(--gray);">
                                        <i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                                        Không có dữ liệu nợ trong khoảng lọc
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Bảng chi tiết -->
        <div class="card">
            <h3><i class="fas fa-receipt"></i> Chi tiết hoá đơn</h3>
            <div style="overflow: auto; max-height: 600px;">
                <table>
                    <thead>
                        <tr>
                            <th>Mã HĐ</th>
                            <th>Sinh viên</th>
                            <th>Phòng</th>
                            <th>Tổng tiền</th>
                            <th>Trạng thái</th>
                            <th>Ngày tạo</th>
                            <th>Hạn thanh toán</th>
                            <th>Ngày thanh toán</th>
                            <th>Tháng/Năm</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($listRes->num_rows > 0): ?>
                            <?php while ($row = $listRes->fetch_assoc()): ?>
                                <?php
                                $statusClass = $row['Status'] === 'Đã thanh toán' ? 'paid' : ($row['Status'] === 'Quá hạn' ? 'overdue' : 'unpaid');
                                ?>
                                <tr>
                                    <td><strong>#<?= $row['InvoiceID'] ?></strong></td>
                                    <td>
                                        <strong><?= htmlspecialchars($row['FullName']) ?></strong>
                                        <br>
                                        <small style="color: var(--gray);"><?= htmlspecialchars($row['StudentCode']) ?></small>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($row['BuildingName']) ?>
                                        <br>
                                        <small style="color: var(--gray);">Phòng <?= htmlspecialchars($row['RoomNumber']) ?></small>
                                    </td>
                                    <td><strong><?= money_vn($row['TotalAmount']) ?></strong></td>
                                    <td>
                                        <span class="status <?= $statusClass ?>">
                                            <i class="fas fa-<?= $statusClass === 'paid' ? 'check' : ($statusClass === 'overdue' ? 'exclamation-triangle' : 'clock') ?>"></i>
                                            <?= htmlspecialchars($row['Status']) ?>
                                        </span>
                                    </td>
                                    <td><?= date('d/m/Y', strtotime($row['CreatedAt'])) ?></td>
                                    <td><?= !empty($row['DueDate']) ? date('d/m/Y', strtotime($row['DueDate'])) : '<span style="color: var(--gray);">-</span>' ?></td>
                                    <td>
                                        <?php if (!empty($row['PaidAt'])): ?>
                                            <span style="color: var(--success);"><?= date('d/m/Y', strtotime($row['PaidAt'])) ?></span>
                                        <?php else: ?>
                                            <span style="color: var(--gray);">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?= $row['Month'] ?>/<?= $row['Year'] ?></strong>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" style="text-align: center; color: var(--gray); padding: 40px;">
                                    <i class="fas fa-search" style="font-size: 3rem; margin-bottom: 16px; display: block;"></i>
                                    Không tìm thấy hoá đơn nào phù hợp với bộ lọc hiện tại
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Toggle date inputs based on range selection
        function toggleDateInputs() {
            const range = document.getElementById('range').value;
            document.getElementById('monthInput').style.display = range === 'month' ? 'block' : 'none';
            document.getElementById('yearInput').style.display = range === 'year' ? 'block' : 'none';
            document.getElementById('customInput').style.display = range === 'custom' ? 'block' : 'none';
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            toggleDateInputs();

            // Add loading states to export buttons
            document.querySelectorAll('.export-form').forEach(form => {
                form.addEventListener('submit', function(e) {
                    const button = this.querySelector('button');
                    const originalText = button.innerHTML;
                    button.innerHTML = '<div class="loading"></div> Đang xuất file...';
                    button.disabled = true;

                    setTimeout(() => {
                        button.innerHTML = originalText;
                        button.disabled = false;
                    }, 3000);
                });
            });
        });

        // Chart data
        const labels = ['Th1', 'Th2', 'Th3', 'Th4', 'Th5', 'Th6', 'Th7', 'Th8', 'Th9', 'Th10', 'Th11', 'Th12'];
        const paid = [<?php for ($m = 1; $m <= 12; $m++) {
                            echo ($series[$m]['paid'] ?? 0) . ',';
                        } ?>];
        const unpaid = [<?php for ($m = 1; $m <= 12; $m++) {
                            echo ($series[$m]['unpaid'] ?? 0) . ',';
                        } ?>];

        // Initialize chart
        const ctx = document.getElementById('monthlyChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                        label: 'Đã thu',
                        data: paid,
                        backgroundColor: 'rgba(16, 185, 129, 0.8)',
                        borderColor: 'rgba(16, 185, 129, 1)',
                        borderWidth: 1,
                        borderRadius: 4
                    },
                    {
                        label: 'Chưa thu',
                        data: unpaid,
                        backgroundColor: 'rgba(234, 179, 8, 0.8)',
                        borderColor: 'rgba(234, 179, 8, 1)',
                        borderWidth: 1,
                        borderRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `${context.dataset.label}: ${new Intl.NumberFormat('vi-VN').format(context.raw)} ₫`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return new Intl.NumberFormat('vi-VN').format(value) + ' ₫';
                            }
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        }
                    }
                },
                animation: {
                    duration: 1000,
                    easing: 'easeOutQuart'
                }
            }
        });
    </script>
</body>

</html>