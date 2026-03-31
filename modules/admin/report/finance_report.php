<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include '../../../db_connect.php';
include '../../../includes/admin_header.php';
include '../../../includes/auth_check.php';
requireRole(['Admin']);

function money_vn($n)
{
    return number_format((float)$n, 0, ',', '.') . ' ₫';
}

$range   = $_GET['range']   ?? 'year';
$status  = $_GET['status']  ?? 'all';
$from    = $_GET['from']    ?? date('Y-m-01');
$to      = $_GET['to']      ?? date('Y-m-t');
$year    = intval($_GET['year'] ?? date('Y'));
$month   = $_GET['month']   ?? date('Y-m');

if ($range === 'month') {
    $from = date('Y-m-01', strtotime($month . '-01'));
    $to   = date('Y-m-t',  strtotime($month . '-01'));
} elseif ($range === 'year') {
    $from = "$year-01-01";
    $to   = "$year-12-31";
}

$where = "i.CreatedAt BETWEEN ? AND ?";
$params = [$from . " 00:00:00", $to . " 23:59:59"];
$types  = "ss";

if ($status !== 'all') {
    $where .= " AND i.Status = ?";
    $params[] = $status;
    $types   .= "s";
}

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
    <link rel="stylesheet" href="<?= $base ?>assets/css/global.css">
    <link rel="stylesheet" href="<?= $base ?>assets/css/modules_shared.css">
    <link rel="stylesheet" href="<?= $base ?>assets/css/admin/admin_header.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0"></script>
    <style>
        .chart-container { position: relative; height: 360px; }
    </style>
</head>

<body>
    <div class="mod-container">
        <!-- Header -->
        <div class="mod-header">
            <div class="mod-header-left">
                <h2><i class="fas fa-chart-line"></i> Báo cáo tài chính</h2>
            </div>
            <div class="mod-header-right">
                <form method="get" action="finance_export_csv.php" style="display:inline;">
                    <input type="hidden" name="range" value="<?= htmlspecialchars($range) ?>">
                    <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
                    <input type="hidden" name="from" value="<?= htmlspecialchars($from) ?>">
                    <input type="hidden" name="to" value="<?= htmlspecialchars($to) ?>">
                    <input type="hidden" name="year" value="<?= htmlspecialchars($year) ?>">
                    <input type="hidden" name="month" value="<?= htmlspecialchars($month) ?>">
                    <button type="submit" class="mod-btn mod-btn-primary mod-btn-sm" id="exportDetail">
                        <i class="fas fa-file-csv"></i> CSV chi tiết
                    </button>
                </form>
                <form method="get" action="finance_export_csv.php" style="display:inline;">
                    <input type="hidden" name="summary" value="1">
                    <input type="hidden" name="year" value="<?= htmlspecialchars($seriesYear) ?>">
                    <button type="submit" class="mod-btn mod-btn-outline mod-btn-sm" id="exportSummary">
                        <i class="fas fa-table"></i> CSV tháng
                    </button>
                </form>
                <a href="<?= $base ?>modules/staff/report/report_hub.php?report_key=finance_detail" class="mod-btn mod-btn-outline mod-btn-sm">
                    <i class="fas fa-file-export"></i> Trung tâm export mới
                </a>
                <button class="mod-btn mod-btn-ghost mod-btn-sm" onclick="window.print()">
                    <i class="fas fa-print"></i> In
                </button>
            </div>
        </div>

        <!-- Filters -->
        <form class="mod-filters" method="get" id="filterForm">
            <div class="mod-filter-group">
                <label><i class="fas fa-calendar-alt"></i> Phạm vi</label>
                <select name="range" id="range" class="mod-select" onchange="toggleDateInputs()">
                    <option value="month" <?= $range === 'month' ? 'selected' : ''; ?>>Theo tháng</option>
                    <option value="year" <?= $range === 'year' ? 'selected' : ''; ?>>Theo năm</option>
                    <option value="custom" <?= $range === 'custom' ? 'selected' : ''; ?>>Tuỳ chọn</option>
                </select>
            </div>

            <div class="mod-filter-group" id="monthInput" style="display: <?= $range === 'month' ? 'flex' : 'none' ?>">
                <label><i class="fas fa-calendar-day"></i> Tháng</label>
                <input type="month" name="month" id="month" class="mod-input" value="<?= htmlspecialchars(date('Y-m', strtotime($from))) ?>">
            </div>

            <div class="mod-filter-group" id="yearInput" style="display: <?= $range === 'year' ? 'flex' : 'none' ?>">
                <label><i class="fas fa-calendar-week"></i> Năm</label>
                <select name="year" id="year" class="mod-select">
                    <?php for ($y = date('Y') - 2; $y <= date('Y') + 1; $y++): ?>
                        <option value="<?= $y ?>" <?= $y == $year ? 'selected' : ''; ?>>Năm <?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="mod-filter-group" id="customInput" style="display: <?= $range === 'custom' ? 'flex' : 'none' ?>; flex-direction:column;">
                <label><i class="fas fa-calendar"></i> Từ ngày</label>
                <input type="date" name="from" id="from" class="mod-input" value="<?= htmlspecialchars($from) ?>">
                <label style="margin-top:8px;"><i class="fas fa-calendar-check"></i> Đến ngày</label>
                <input type="date" name="to" id="to" class="mod-input" value="<?= htmlspecialchars($to) ?>">
            </div>

            <div class="mod-filter-group">
                <label><i class="fas fa-filter"></i> Trạng thái</label>
                <select name="status" id="status" class="mod-select">
                    <option value="all" <?= $status === 'all' ? 'selected' : ''; ?>>Tất cả</option>
                    <option value="Đã thanh toán" <?= $status === 'Đã thanh toán' ? 'selected' : ''; ?>>Đã thanh toán</option>
                    <option value="Chưa thanh toán" <?= $status === 'Chưa thanh toán' ? 'selected' : ''; ?>>Chưa thanh toán</option>
                    <option value="Quá hạn" <?= $status === 'Quá hạn' ? 'selected' : ''; ?>>Quá hạn</option>
                </select>
            </div>

            <div class="mod-filter-group" style="flex:0; min-width:auto;">
                <label>&nbsp;</label>
                <button type="submit" class="mod-btn mod-btn-primary mod-btn-sm">
                    <i class="fas fa-filter"></i> Lọc
                </button>
            </div>
        </form>

        <!-- KPI Cards -->
        <div class="mod-stats mod-stagger">
            <div class="mod-stat accent-green">
                <div class="mod-stat-icon" style="background:var(--gradient-success);">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="mod-stat-info">
                    <span class="mod-stat-number"><?= money_vn($kpiRes['total_paid'] ?? 0) ?></span>
                    <span class="mod-stat-label">Đã thu</span>
                </div>
            </div>
            <div class="mod-stat accent-blue">
                <div class="mod-stat-icon" style="background:var(--gradient-primary);">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="mod-stat-info">
                    <span class="mod-stat-number"><?= money_vn($kpiRes['total_unpaid'] ?? 0) ?></span>
                    <span class="mod-stat-label">Chưa thu</span>
                </div>
            </div>
            <div class="mod-stat accent-pink">
                <div class="mod-stat-icon" style="background:var(--gradient-warning);">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="mod-stat-info">
                    <span class="mod-stat-number"><?= money_vn($kpiRes['total_overdue'] ?? 0) ?></span>
                    <span class="mod-stat-label">Quá hạn</span>
                </div>
            </div>
            <div class="mod-stat accent-purple">
                <div class="mod-stat-icon" style="background:var(--gradient-info);">
                    <i class="fas fa-receipt"></i>
                </div>
                <div class="mod-stat-info">
                    <span class="mod-stat-number"><?= (int)($kpiRes['invoice_count'] ?? 0) ?></span>
                    <span class="mod-stat-label">Tổng hoá đơn</span>
                </div>
            </div>
        </div>

        <!-- 2-Column: Chart + Top Debt -->
        <div class="mod-grid-2" style="align-items:start;">
            <!-- Chart -->
            <div class="mod-card">
                <div class="mod-card-header">
                    <div class="mod-card-title"><i class="fas fa-chart-column"></i> Thu/Chưa thu (<?= $seriesYear ?>)</div>
                </div>
                <div class="mod-card-body">
                    <div class="chart-container">
                        <canvas id="monthlyChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Top nợ -->
            <div class="mod-card">
                <div class="mod-card-header">
                    <div class="mod-card-title"><i class="fas fa-user-minus"></i> Top 10 nợ cao nhất</div>
                </div>
                <div class="mod-card-body" style="padding:0;">
                    <div class="mod-table-scroll" style="max-height:400px;">
                        <table class="mod-table">
                            <thead>
                                <tr>
                                    <th>Sinh viên</th>
                                    <th style="text-align:right;">Số nợ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($debtRes->num_rows > 0): ?>
                                    <?php while ($d = $debtRes->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <div class="mod-cell-name"><?= htmlspecialchars($d['FullName']) ?></div>
                                                <div class="mod-cell-sub"><?= htmlspecialchars($d['StudentCode']) ?></div>
                                            </td>
                                            <td style="text-align:right;">
                                                <span class="mod-badge mod-badge-red">
                                                    <?= money_vn($d['debt']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="2">
                                            <div class="mod-empty" style="padding:40px;">
                                                <i class="fas fa-inbox"></i>
                                                <p>Không có dữ liệu nợ</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bảng chi tiết hoá đơn -->
        <div class="mod-card">
            <div class="mod-card-header">
                <div class="mod-card-title"><i class="fas fa-receipt"></i> Chi tiết hoá đơn</div>
                <span class="mod-badge mod-badge-gray"><?= $listRes->num_rows ?> bản ghi</span>
            </div>
            <div class="mod-table-scroll" style="max-height:600px;">
                <table class="mod-table">
                    <thead>
                        <tr>
                            <th>Mã HĐ</th>
                            <th>Sinh viên</th>
                            <th>Phòng</th>
                            <th>Tổng tiền</th>
                            <th>Trạng thái</th>
                            <th>Ngày tạo</th>
                            <th>Hạn TT</th>
                            <th>Ngày TT</th>
                            <th>Tháng/Năm</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($listRes->num_rows > 0): ?>
                            <?php while ($row = $listRes->fetch_assoc()): ?>
                                <?php
                                $sBadge = match($row['Status']) {
                                    'Đã thanh toán'  => 'mod-badge-emerald',
                                    'Quá hạn'        => 'mod-badge-red',
                                    default           => 'mod-badge-amber',
                                };
                                $sIcon = match($row['Status']) {
                                    'Đã thanh toán'  => 'fa-check',
                                    'Quá hạn'        => 'fa-exclamation-triangle',
                                    default           => 'fa-clock',
                                };
                                ?>
                                <tr>
                                    <td><span class="mod-fw-600">#<?= $row['InvoiceID'] ?></span></td>
                                    <td>
                                        <div class="mod-cell-name"><?= htmlspecialchars($row['FullName']) ?></div>
                                        <div class="mod-cell-sub"><?= htmlspecialchars($row['StudentCode']) ?></div>
                                    </td>
                                    <td>
                                        <div class="mod-cell-name"><?= htmlspecialchars($row['BuildingName']) ?></div>
                                        <div class="mod-cell-sub">Phòng <?= htmlspecialchars($row['RoomNumber']) ?></div>
                                    </td>
                                    <td><span class="mod-fw-700"><?= money_vn($row['TotalAmount']) ?></span></td>
                                    <td>
                                        <span class="mod-badge <?= $sBadge ?>">
                                            <i class="fas <?= $sIcon ?>"></i>
                                            <?= htmlspecialchars($row['Status']) ?>
                                        </span>
                                    </td>
                                    <td class="mod-cell-muted"><?= date('d/m/Y', strtotime($row['CreatedAt'])) ?></td>
                                    <td class="mod-cell-muted"><?= !empty($row['DueDate']) ? date('d/m/Y', strtotime($row['DueDate'])) : '-' ?></td>
                                    <td>
                                        <?php if (!empty($row['PaidAt'])): ?>
                                            <span class="mod-badge mod-badge-emerald"><?= date('d/m/Y', strtotime($row['PaidAt'])) ?></span>
                                        <?php else: ?>
                                            <span class="mod-cell-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="mod-fw-600"><?= $row['Month'] ?>/<?= $row['Year'] ?></span></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9">
                                    <div class="mod-empty">
                                        <i class="fas fa-search"></i>
                                        <p>Không tìm thấy hoá đơn nào phù hợp</p>
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
        function toggleDateInputs() {
            const range = document.getElementById('range').value;
            document.getElementById('monthInput').style.display = range === 'month' ? 'flex' : 'none';
            document.getElementById('yearInput').style.display = range === 'year' ? 'flex' : 'none';
            document.getElementById('customInput').style.display = range === 'custom' ? 'flex' : 'none';
        }

        document.addEventListener('DOMContentLoaded', function() {
            toggleDateInputs();
        });

        const labels = ['Th1','Th2','Th3','Th4','Th5','Th6','Th7','Th8','Th9','Th10','Th11','Th12'];
        const paid = [<?php for ($m = 1; $m <= 12; $m++) { echo ($series[$m]['paid'] ?? 0) . ','; } ?>];
        const unpaid = [<?php for ($m = 1; $m <= 12; $m++) { echo ($series[$m]['unpaid'] ?? 0) . ','; } ?>];

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
                    borderRadius: 6
                },{
                    label: 'Chưa thu',
                    data: unpaid,
                    backgroundColor: 'rgba(234, 179, 8, 0.8)',
                    borderColor: 'rgba(234, 179, 8, 1)',
                    borderWidth: 1,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { padding: 20, usePointStyle: true }
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
                    x: { grid: { display: false } },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return new Intl.NumberFormat('vi-VN').format(value) + ' ₫';
                            }
                        },
                        grid: { color: 'rgba(0, 0, 0, 0.06)' }
                    }
                },
                animation: { duration: 1000, easing: 'easeOutQuart' }
            }
        });
    </script>
</body>
</html>
