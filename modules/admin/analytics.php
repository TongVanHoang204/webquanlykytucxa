<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../db_connect.php';
require_once '../../includes/auth_check.php';
requireRole(['Admin', 'Manager']);
require_once '../../includes/admin_header.php';

// ============================================================
// KHU VỰC QUERY DỮ LIỆU
// ============================================================

// --- KPI Cards ---
$totalStudents   = (int)$conn->query("SELECT COUNT(*) c FROM students")->fetch_assoc()['c'];
$totalRooms      = (int)$conn->query("SELECT COUNT(*) c FROM rooms")->fetch_assoc()['c'];
$emptyRooms      = (int)$conn->query("SELECT COUNT(*) c FROM rooms WHERE Status='Trống'")->fetch_assoc()['c'];
$occupiedRooms   = $totalRooms - $emptyRooms;
$occupancyRate   = $totalRooms > 0 ? round($occupiedRooms / $totalRooms * 100) : 0;

$totalRevenue    = (float)($conn->query("SELECT COALESCE(SUM(Amount),0) s FROM payments WHERE Status='Đã xác nhận'")->fetch_assoc()['s'] ?? 0);
$unpaidInvoices  = (int)$conn->query("SELECT COUNT(*) c FROM invoices WHERE Status='Chưa thanh toán'")->fetch_assoc()['c'];
$pendingMaint    = (int)$conn->query("SELECT COUNT(*) c FROM maintenancerequests WHERE Status='Chờ xử lý'")->fetch_assoc()['c'];
$pendingPackages = (int)$conn->query("SELECT COUNT(*) c FROM packages WHERE Status='Chờ lấy'")->fetch_assoc()['c'];
$forumPosts      = (int)$conn->query("SELECT COUNT(*) c FROM posts")->fetch_assoc()['c'];

// --- Doanh thu 6 tháng gần nhất (Line chart) ---
$revenueData = [];
$revenueLabels = [];
for ($i = 5; $i >= 0; $i--) {
    $label = date('m/Y', strtotime("-$i months"));
    $month = date('m', strtotime("-$i months"));
    $year  = date('Y', strtotime("-$i months"));
    $res = $conn->query("SELECT COALESCE(SUM(Amount),0) s FROM payments WHERE Status='Đã xác nhận' AND MONTH(PaymentDate)=$month AND YEAR(PaymentDate)=$year");
    $revenueLabels[] = $label;
    $revenueData[] = (float)($res->fetch_assoc()['s'] ?? 0);
}

// --- Trạng thái phòng (Donut chart) ---
$roomStatuses = [];
$rs = $conn->query("SELECT Status, COUNT(*) cnt FROM rooms GROUP BY Status");
while ($r = $rs->fetch_assoc()) {
    $roomStatuses[$r['Status']] = (int)$r['cnt'];
}

// --- Trạng thái bảo trì (Bar chart) ---
$maintStatuses = [];
$ms = $conn->query("SELECT Status, COUNT(*) cnt FROM maintenancerequests GROUP BY Status");
while ($r = $ms->fetch_assoc()) {
    $maintStatuses[$r['Status']] = (int)$r['cnt'];
}

// --- Số lượng sinh viên đăng ký theo tháng (Bar chart) ---
$studentLabels = [];
$studentCounts = [];
for ($i = 5; $i >= 0; $i--) {
    $label = date('m/Y', strtotime("-$i months"));
    $month = date('m', strtotime("-$i months"));
    $year  = date('Y', strtotime("-$i months"));
    $res2 = $conn->query("SELECT COUNT(*) c FROM students WHERE MONTH(CreatedAt)=$month AND YEAR(CreatedAt)=$year");
    $studentLabels[] = $label;
    $studentCounts[] = (int)($res2->fetch_assoc()['c'] ?? 0);
}

// --- Hoạt động gần đây ---
$activities = [];
$actRes = $conn->query("
    (SELECT 'Bưu phẩm' AS cat, SenderInfo AS detail, ReceivedAt AS ts FROM packages ORDER BY ReceivedAt DESC LIMIT 3)
    UNION ALL
    (SELECT 'Báo trì' AS cat, Title AS detail, CreatedAt AS ts FROM maintenancerequests ORDER BY CreatedAt DESC LIMIT 3)
    UNION ALL
    (SELECT 'Phản ánh' AS cat, Title AS detail, CreatedAt AS ts FROM feedbacks ORDER BY CreatedAt DESC LIMIT 3)
    UNION ALL
    (SELECT 'Hóa đơn' AS cat, CONCAT('Hóa đơn #', InvoiceID) AS detail, CreatedAt AS ts FROM invoices ORDER BY CreatedAt DESC LIMIT 2)
    ORDER BY ts DESC LIMIT 10
");
if ($actRes) {
    while ($r = $actRes->fetch_assoc()) $activities[] = $r;
}

// JSON encode cho Chart.js
$revenueLabelsJson = json_encode($revenueLabels);
$revenueDataJson   = json_encode($revenueData);
$roomStatusLabels  = json_encode(array_keys($roomStatuses));
$roomStatusData    = json_encode(array_values($roomStatuses));
$maintLabels       = json_encode(array_keys($maintStatuses));
$maintData         = json_encode(array_values($maintStatuses));
$studentLabelsJson = json_encode($studentLabels);
$studentDataJson   = json_encode($studentCounts);
?>

<style>
/* ---- Analytics Page Styles ---- */
.analytics-wrapper {
    padding: 2rem;
    max-width: 1400px;
    margin: 0 auto;
}

.analytics-title {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 2rem;
}
.analytics-title h1 {
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--text);
    margin: 0;
}
.analytics-title p {
    margin: 4px 0 0;
    color: var(--muted);
    font-size: 0.95rem;
}
.analytics-title .title-icon {
    width: 52px; height: 52px;
    background: linear-gradient(135deg, #4361ee, #7209b7);
    border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 1.4rem;
    flex-shrink: 0;
}

/* KPI Cards */
.kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1.25rem;
    margin-bottom: 2rem;
}
.kpi-card {
    background: var(--card);
    border: 1px solid var(--stroke);
    border-radius: 12px;
    padding: 1.25rem 1.5rem;
    display: flex;
    align-items: center;
    gap: 14px;
    transition: transform 0.2s, box-shadow 0.2s;
    position: relative;
    overflow: hidden;
}
.kpi-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.12);
}
.kpi-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    border-radius: 12px 12px 0 0;
}
.kpi-card.blue::before   { background: #4361ee; }
.kpi-card.green::before  { background: #10b981; }
.kpi-card.amber::before  { background: #f59e0b; }
.kpi-card.red::before    { background: #ef4444; }
.kpi-card.purple::before { background: #7c3aed; }
.kpi-card.teal::before   { background: #14b8a6; }
.kpi-card.pink::before   { background: #ec4899; }
.kpi-card.orange::before { background: #f97316; }

.kpi-icon {
    width: 46px; height: 46px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.3rem;
    flex-shrink: 0;
}
.kpi-card.blue   .kpi-icon { background: #eff3ff; color: #4361ee; }
.kpi-card.green  .kpi-icon { background: #d1fae5; color: #059669; }
.kpi-card.amber  .kpi-icon { background: #fef3c7; color: #d97706; }
.kpi-card.red    .kpi-icon { background: #fee2e2; color: #dc2626; }
.kpi-card.purple .kpi-icon { background: #ede9fe; color: #7c3aed; }
.kpi-card.teal   .kpi-icon { background: #ccfbf1; color: #0d9488; }
.kpi-card.pink   .kpi-icon { background: #fce7f3; color: #db2777; }
.kpi-card.orange .kpi-icon { background: #ffedd5; color: #ea580c; }

[data-theme="dark"] .kpi-card.blue   .kpi-icon { background: rgba(67,97,238,.2); color: #818cf8; }
[data-theme="dark"] .kpi-card.green  .kpi-icon { background: rgba(16,185,129,.15); color: #34d399; }
[data-theme="dark"] .kpi-card.amber  .kpi-icon { background: rgba(245,158,11,.15); color: #fbbf24; }
[data-theme="dark"] .kpi-card.red    .kpi-icon { background: rgba(239,68,68,.15); color: #f87171; }
[data-theme="dark"] .kpi-card.purple .kpi-icon { background: rgba(124,58,237,.15); color: #a78bfa; }
[data-theme="dark"] .kpi-card.teal   .kpi-icon { background: rgba(20,184,166,.15); color: #2dd4bf; }
[data-theme="dark"] .kpi-card.pink   .kpi-icon { background: rgba(236,72,153,.15); color: #f472b6; }
[data-theme="dark"] .kpi-card.orange .kpi-icon { background: rgba(249,115,22,.15); color: #fb923c; }

.kpi-info { min-width: 0; }
.kpi-value {
    font-size: 1.65rem;
    font-weight: 800;
    color: var(--text);
    line-height: 1;
    letter-spacing: -0.5px;
}
.kpi-label {
    font-size: 0.8rem;
    color: var(--muted);
    margin-top: 4px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Charts Grid */
.charts-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
    margin-bottom: 2rem;
}
.chart-card {
    background: var(--card);
    border: 1px solid var(--stroke);
    border-radius: 12px;
    padding: 1.5rem;
}
.chart-card.full-width {
    grid-column: 1 / -1;
}
.chart-card h3 {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text);
    margin: 0 0 1.25rem;
    display: flex;
    align-items: center;
    gap: 8px;
}
.chart-card h3 i { color: var(--primary, #4361ee); }
.chart-wrap {
    position: relative;
    height: 260px;
}

/* Activity feed */
.bottom-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
    margin-bottom: 2rem;
}
.activity-card {
    background: var(--card);
    border: 1px solid var(--stroke);
    border-radius: 12px;
    padding: 1.5rem;
}
.activity-card h3 {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text);
    margin: 0 0 1rem;
    display: flex; align-items: center; gap:8px;
}
.activity-list { display: flex; flex-direction: column; gap: 10px; }
.activity-item {
    display: flex; align-items: center; gap: 12px;
    padding: 10px 12px;
    border-radius: 8px;
    background: var(--bg, #f8f9fa);
    transition: background 0.2s;
}
.activity-item:hover { background: var(--hover, #e9ecef); }
.activity-badge {
    padding: 3px 8px;
    border-radius: 6px;
    font-size: 0.72rem;
    font-weight: 600;
    white-space: nowrap;
    flex-shrink: 0;
}
.badge-bưu-phẩm { background:#dbeafe; color:#1d4ed8; }
.badge-báo-trì  { background:#fef3c7; color:#92400e; }
.badge-phản-ánh { background:#f3e8ff; color:#6d28d9; }
.badge-hóa-đơn  { background:#d1fae5; color:#065f46; }

.activity-detail {
    flex: 1;
    font-size: 0.85rem;
    color: var(--text);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.activity-time {
    font-size: 0.75rem;
    color: var(--muted);
    white-space: nowrap;
    flex-shrink: 0;
}

/* Occupancy progress */
.occ-wrap { padding: 0.5rem 0; }
.occ-row { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; }
.occ-label { font-size: 0.875rem; color: var(--muted); width: 100px; flex-shrink: 0; }
.occ-bar-wrap { flex: 1; background: var(--bg,#f3f4f6); border-radius: 99px; height: 10px; overflow:hidden; }
.occ-bar { height: 100%; border-radius: 99px; transition: width 1.2s cubic-bezier(0.4,0,0.2,1); }
.occ-val { font-size: 0.875rem; font-weight: 600; color: var(--text); width: 40px; text-align: right; flex-shrink: 0; }

@media (max-width: 900px) {
    .charts-grid, .bottom-grid { grid-template-columns: 1fr; }
    .chart-card.full-width { grid-column: auto; }
    .kpi-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 500px) {
    .kpi-grid { grid-template-columns: 1fr 1fr; }
    .analytics-wrapper { padding: 1rem; }
}
</style>

<div class="analytics-wrapper">

    <!-- Page Title -->
    <div class="analytics-title">
        <div class="title-icon"><i class="fas fa-chart-pie"></i></div>
        <div>
            <h1>Thống kê & Phân tích</h1>
            <p>Tổng quan hoạt động Ký túc xá — cập nhật theo thời gian thực</p>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="kpi-grid">
        <div class="kpi-card blue">
            <div class="kpi-icon"><i class="fas fa-user-graduate"></i></div>
            <div class="kpi-info">
                <div class="kpi-value" data-count="<?= $totalStudents ?>"><?= $totalStudents ?></div>
                <div class="kpi-label">Sinh viên nội trú</div>
            </div>
        </div>
        <div class="kpi-card green">
            <div class="kpi-icon"><i class="fas fa-bed"></i></div>
            <div class="kpi-info">
                <div class="kpi-value"><?= $occupancyRate ?>%</div>
                <div class="kpi-label">Tỉ lệ lấp đầy phòng</div>
            </div>
        </div>
        <div class="kpi-card teal">
            <div class="kpi-icon"><i class="fas fa-door-open"></i></div>
            <div class="kpi-info">
                <div class="kpi-value" data-count="<?= $emptyRooms ?>"><?= $emptyRooms ?></div>
                <div class="kpi-label">Phòng còn trống</div>
            </div>
        </div>
        <div class="kpi-card purple">
            <div class="kpi-icon"><i class="fas fa-money-bill-wave"></i></div>
            <div class="kpi-info">
                <div class="kpi-value"><?= number_format($totalRevenue/1000000, 1) ?>M</div>
                <div class="kpi-label">Tổng thu (VNĐ)</div>
            </div>
        </div>
        <div class="kpi-card amber">
            <div class="kpi-icon"><i class="fas fa-file-invoice-dollar"></i></div>
            <div class="kpi-info">
                <div class="kpi-value" data-count="<?= $unpaidInvoices ?>"><?= $unpaidInvoices ?></div>
                <div class="kpi-label">Hóa đơn chưa thanh toán</div>
            </div>
        </div>
        <div class="kpi-card red">
            <div class="kpi-icon"><i class="fas fa-tools"></i></div>
            <div class="kpi-info">
                <div class="kpi-value" data-count="<?= $pendingMaint ?>"><?= $pendingMaint ?></div>
                <div class="kpi-label">Bảo trì chờ xử lý</div>
            </div>
        </div>
        <div class="kpi-card orange">
            <div class="kpi-icon"><i class="fas fa-box"></i></div>
            <div class="kpi-info">
                <div class="kpi-value" data-count="<?= $pendingPackages ?>"><?= $pendingPackages ?></div>
                <div class="kpi-label">Bưu phẩm chờ lấy</div>
            </div>
        </div>
        <div class="kpi-card pink">
            <div class="kpi-icon"><i class="fas fa-store"></i></div>
            <div class="kpi-info">
                <div class="kpi-value" data-count="<?= $forumPosts ?>"><?= $forumPosts ?></div>
                <div class="kpi-label">Bài đăng cộng đồng</div>
            </div>
        </div>
    </div>

    <!-- Charts Row 1 -->
    <div class="charts-grid">
        <!-- Doanh thu 6 tháng -->
        <div class="chart-card full-width">
            <h3><i class="fas fa-chart-line"></i> Doanh thu 6 tháng gần nhất</h3>
            <div class="chart-wrap">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>

        <!-- Trạng thái phòng -->
        <div class="chart-card">
            <h3><i class="fas fa-door-closed"></i> Tình trạng phòng ở</h3>
            <div class="chart-wrap">
                <canvas id="roomChart"></canvas>
            </div>
        </div>

        <!-- Trạng thái bảo trì -->
        <div class="chart-card">
            <h3><i class="fas fa-tools"></i> Yêu cầu bảo trì theo trạng thái</h3>
            <div class="chart-wrap">
                <canvas id="maintChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Charts Row 2 + Activity -->
    <div class="bottom-grid">
        <!-- Sinh viên mới theo tháng -->
        <div class="chart-card">
            <h3><i class="fas fa-user-plus"></i> Sinh viên đăng ký mới (6 tháng)</h3>
            <div class="chart-wrap" style="height:220px;">
                <canvas id="studentChart"></canvas>
            </div>
        </div>

        <!-- Tỉ lệ lấp đầy phòng chi tiết -->
        <div class="activity-card">
            <h3><i class="fas fa-chart-bar"></i> Tỉ lệ lấp đầy phòng</h3>
            <div class="occ-wrap" id="occWrap">
                <?php
                $roomByStatus = $conn->query("SELECT Status, COUNT(*) cnt FROM rooms GROUP BY Status ORDER BY cnt DESC");
                $colors = ['Đã ở' => '#4361ee', 'Trống' => '#10b981', 'Bảo trì' => '#f59e0b', 'Đóng cửa' => '#6b7280'];
                while ($row = $roomByStatus->fetch_assoc()):
                    $pct = $totalRooms > 0 ? round($row['cnt'] / $totalRooms * 100) : 0;
                    $color = $colors[$row['Status']] ?? '#94a3b8';
                ?>
                <div class="occ-row">
                    <div class="occ-label"><?= htmlspecialchars($row['Status']) ?></div>
                    <div class="occ-bar-wrap">
                        <div class="occ-bar" data-pct="<?= $pct ?>" style="width:0%; background:<?= $color ?>;"></div>
                    </div>
                    <div class="occ-val"><?= $pct ?>%</div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>

    <!-- Activity Log -->
    <div class="chart-card" style="margin-bottom: 2rem;">
        <h3><i class="fas fa-history"></i> Hoạt động gần đây</h3>
        <div class="activity-list">
            <?php foreach ($activities as $act): 
                $catClass = 'badge-' . strtolower(str_replace(' ', '-', $act['cat']));
            ?>
            <div class="activity-item">
                <span class="activity-badge <?= $catClass ?>"><?= $act['cat'] ?></span>
                <span class="activity-detail"><?= htmlspecialchars($act['detail']) ?></span>
                <span class="activity-time"><?= date('H:i d/m', strtotime($act['ts'])) ?></span>
            </div>
            <?php endforeach; ?>
            <?php if (empty($activities)): ?>
                <p style="color:var(--muted); text-align:center; padding:20px;">Chưa có hoạt động nào</p>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {

    // ----------- Detect Dark Mode -----------
    const isDark = () => document.body.getAttribute('data-theme') === 'dark';
    const gridColor = () => isDark() ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.07)';
    const tickColor = () => isDark() ? '#94a3b8' : '#6b7280';
    const legendColor = () => isDark() ? '#e2e8f0' : '#374151';

    // ----------- KPI Count-Up Animation -----------
    document.querySelectorAll('.kpi-value[data-count]').forEach(el => {
        const target = parseInt(el.dataset.count);
        if (isNaN(target)) return;
        let start = 0;
        const step = Math.max(1, Math.ceil(target / 40));
        const timer = setInterval(() => {
            start = Math.min(start + step, target);
            el.textContent = start.toLocaleString('vi-VN');
            if (start >= target) clearInterval(timer);
        }, 40);
    });

    // ----------- Progress Bars -----------
    setTimeout(() => {
        document.querySelectorAll('.occ-bar[data-pct]').forEach(bar => {
            bar.style.width = bar.dataset.pct + '%';
        });
    }, 300);

    // ----------- Chart defaults -----------
    Chart.defaults.font.family = "'Inter', sans-serif";

    // Helper tooltip style
    const tooltipStyle = {
        backgroundColor: isDark() ? '#1e293b' : '#fff',
        titleColor: isDark() ? '#e2e8f0' : '#111827',
        bodyColor: isDark() ? '#94a3b8' : '#374151',
        borderColor: isDark() ? '#334155' : '#e5e7eb',
        borderWidth: 1,
        padding: 12,
        cornerRadius: 10
    };

    // ----------- 1. Revenue Line Chart -----------
    const revCtx = document.getElementById('revenueChart').getContext('2d');
    const revenueGradient = revCtx.createLinearGradient(0, 0, 0, 260);
    revenueGradient.addColorStop(0, 'rgba(67,97,238,0.35)');
    revenueGradient.addColorStop(1, 'rgba(67,97,238,0)');

    new Chart(revCtx, {
        type: 'line',
        data: {
            labels: <?= $revenueLabelsJson ?>,
            datasets: [{
                label: 'Doanh thu (VNĐ)',
                data: <?= $revenueDataJson ?>,
                borderColor: '#4361ee',
                backgroundColor: revenueGradient,
                borderWidth: 2.5,
                pointBackgroundColor: '#4361ee',
                pointRadius: 5,
                pointHoverRadius: 7,
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    ...tooltipStyle,
                    callbacks: {
                        label: ctx => ' ' + Number(ctx.parsed.y).toLocaleString('vi-VN') + ' đ'
                    }
                }
            },
            scales: {
                x: { grid: { color: gridColor() }, ticks: { color: tickColor() } },
                y: {
                    grid: { color: gridColor() },
                    ticks: {
                        color: tickColor(),
                        callback: v => (v / 1000000).toFixed(0) + 'M'
                    }
                }
            }
        }
    });

    // ----------- 2. Room Donut Chart -----------
    new Chart(document.getElementById('roomChart').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: <?= $roomStatusLabels ?>,
            datasets: [{
                data: <?= $roomStatusData ?>,
                backgroundColor: ['#4361ee','#10b981','#f59e0b','#6b7280','#ef4444'],
                borderWidth: 0,
                hoverOffset: 8
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            cutout: '68%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { color: legendColor(), padding: 16, font: { size: 12 } }
                },
                tooltip: tooltipStyle
            }
        }
    });

    // ----------- 3. Maintenance Bar Chart -----------
    new Chart(document.getElementById('maintChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: <?= $maintLabels ?>,
            datasets: [{
                label: 'Số lượng',
                data: <?= $maintData ?>,
                backgroundColor: ['#f59e0b','#3b82f6','#10b981','#6b7280'],
                borderRadius: 8,
                borderSkipped: false
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: tooltipStyle
            },
            scales: {
                x: { grid: { display: false }, ticks: { color: tickColor() } },
                y: {
                    grid: { color: gridColor() },
                    ticks: { color: tickColor(), precision: 0 }
                }
            }
        }
    });

    // ----------- 4. Student Registration Bar Chart -----------
    new Chart(document.getElementById('studentChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: <?= $studentLabelsJson ?>,
            datasets: [{
                label: 'Sinh viên mới',
                data: <?= $studentDataJson ?>,
                backgroundColor: 'rgba(124,58,237,0.75)',
                borderRadius: 8,
                borderSkipped: false
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: tooltipStyle
            },
            scales: {
                x: { grid: { display: false }, ticks: { color: tickColor() } },
                y: {
                    grid: { color: gridColor() },
                    ticks: { color: tickColor(), precision: 0 }
                }
            }
        }
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
