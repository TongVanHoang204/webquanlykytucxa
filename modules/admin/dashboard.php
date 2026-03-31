<?php
if (session_status() === PHP_SESSION_NONE) session_start();

include '../../db_connect.php';
include '../../includes/auth_check.php';
requireRole(['Admin']);




// -------------------------------
// Khởi tạo mặc định để tránh Notice
// -------------------------------
$unpaidCount        = 0;
$feedbackPending    = 0;
$activeContracts    = 0;
$availableRooms     = 0;
$totalStudents      = 0;
$recentActivities   = [];

// -------------------------------
// 1) Hóa đơn chưa thanh toán
// -------------------------------
$res = $conn->query("SELECT COUNT(*) AS cnt FROM Invoices WHERE Status='Chưa thanh toán'");
if ($res && $row = $res->fetch_assoc()) {
    $unpaidCount = (int) $row['cnt'];
}

// -------------------------------
// 2) Phản ánh đang chờ (gồm 'Chưa xử lý' + 'Đang xử lý')
// -------------------------------
$res = $conn->query("
  SELECT COUNT(*) AS cnt 
  FROM Feedbacks 
  WHERE Status IN ('Chưa xử lý','Đang xử lý')
");
if ($res && $row = $res->fetch_assoc()) {
    $feedbackPending = (int) $row['cnt'];
}

// -------------------------------
// 3) Hợp đồng đang hiệu lực (đếm nhanh tình trạng sử dụng)
// -------------------------------
$res = $conn->query("SELECT COUNT(*) AS cnt FROM Contracts WHERE Status='Hiệu lực'");
if ($res && $row = $res->fetch_assoc()) {
    $activeContracts = (int) $row['cnt'];
}

// -------------------------------
// 4) Phòng còn trống
// -------------------------------
$res = $conn->query("SELECT COUNT(*) AS cnt FROM Rooms WHERE Status='Trống'");
if ($res && $row = $res->fetch_assoc()) {
    $availableRooms = (int) $row['cnt'];
}

// -------------------------------
// 5) Tổng số sinh viên
// -------------------------------
$res = $conn->query("SELECT COUNT(*) AS cnt FROM Students");
if ($res && $row = $res->fetch_assoc()) {
    $totalStudents = (int) $row['cnt'];
}

// -------------------------------
// 6) Hoạt động gần đây
// -------------------------------
$res = $conn->query("
  (SELECT 'feedback' as type, Content as title, CreatedAt as date FROM Feedbacks ORDER BY CreatedAt DESC LIMIT 3)
  UNION ALL
  (SELECT 'invoice' as type, CONCAT('Hóa đơn #', InvoiceID) as title, CreatedAt as date FROM Invoices ORDER BY CreatedAt DESC LIMIT 3)
  UNION ALL
  (SELECT 'contract' as type, CONCAT('Hợp đồng #', ContractID) as title, CreatedAt as date FROM Contracts ORDER BY CreatedAt DESC LIMIT 2)
  ORDER BY date DESC LIMIT 5
");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $recentActivities[] = $row;
    }
}




// Lấy tên admin từ session
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
    <link rel="stylesheet" href="../../assets/vendor/fontawesome/css/all.min.css">
</head>

    <div class="bento-dashboard-container">
        <!-- 1. Bento Welcome Banner -->
        <div class="bento-welcome gradient-flare">
            <div class="welcome-text">
                <h1>Xin chào, <?php echo htmlspecialchars($adminName); ?>! 👋</h1>
                <p>Khám phá tình trạng hiện tại của hệ thống. Dưới đây là các chỉ số theo thời gian thực.</p>
            </div>
            <div class="welcome-quick-stats">
                <div class="wqs-item">
                    <div class="wqs-icon"><i class="fas fa-user-graduate"></i></div>
                    <div class="wqs-info">
                        <span class="number"><?= $totalStudents ?></span>
                        <span class="label">Sinh viên</span>
                    </div>
                </div>
                <div class="wqs-item">
                    <div class="wqs-icon"><i class="fas fa-file-signature"></i></div>
                    <div class="wqs-info">
                        <span class="number"><?= $activeContracts ?></span>
                        <span class="label">Hợp đồng</span>
                    </div>
                </div>
                <div class="wqs-item">
                    <div class="wqs-icon"><i class="fas fa-door-open"></i></div>
                    <div class="wqs-info">
                        <span class="number"><?= $availableRooms ?></span>
                        <span class="label">Phòng trống</span>
                    </div>
                </div>
            </div>
            <div class="flare-effect"></div>
        </div>

        <!-- AI EXECUTIVE SUMMARY BANNER -->
        <div id="aiInsightsBanner" style="
            background: linear-gradient(135deg, rgba(99,102,241,0.12) 0%, rgba(168,85,247,0.08) 100%);
            border: 1px solid rgba(99,102,241,0.25);
            border-radius: 18px;
            padding: 18px 24px;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 16px;
            position: relative;
            overflow: hidden;
        ">
            <div style="flex-shrink:0; width:42px; height:42px; border-radius:12px; background:linear-gradient(135deg,#6366f1,#a855f7); display:flex; align-items:center; justify-content:center; color:#fff; font-size:1.2rem;">
                <i class="fas fa-brain"></i>
            </div>
            <div style="flex:1; min-width:0;">
                <div style="font-size:0.75rem; font-weight:700; color:#a855f7; text-transform:uppercase; letter-spacing:0.08em; margin-bottom:6px;">
                    AI EXECUTIVE SUMMARY
                </div>
                <div id="aiInsightsText" style="font-size:0.95rem; line-height:1.7; color:var(--text);">
                    <span style="display:inline-flex; align-items:center; gap:8px; color:var(--text-secondary);">
                        <i class="fas fa-circle-notch fa-spin" style="color:#6366f1;"></i>
                        Đang phân tích dữ liệu hệ thống...
                    </span>
                </div>
            </div>
            <button onclick="loadAiInsights()" title="Làm mới phân tích" style="flex-shrink:0; background:rgba(99,102,241,0.1); border:1px solid rgba(99,102,241,0.2); border-radius:8px; padding:6px 10px; color:#6366f1; cursor:pointer; font-size:0.85rem; transition:all 0.2s;">
                <i class="fas fa-rotate-right"></i>
            </button>
        </div>

        <!-- AI NATURAL LANGUAGE SEARCH -->
        <div style="margin-bottom: 24px;">
            <div style="position:relative;">
                <div style="
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    background: var(--surface);
                    border: 1.5px solid rgba(99,102,241,0.3);
                    border-radius: 14px;
                    padding: 12px 16px;
                    transition: border-color 0.2s, box-shadow 0.2s;
                " id="aiSearchWrap">
                    <div style="width:36px; height:36px; border-radius:10px; background:linear-gradient(135deg,#6366f1,#8b5cf6); display:flex; align-items:center; justify-content:center; color:#fff; flex-shrink:0;">
                        <i class="fas fa-wand-magic-sparkles"></i>
                    </div>
                    <input type="text" id="aiSearchInput" placeholder='Tìm kiếm thông minh... VD: "hóa đơn chưa đóng tháng 3 sinh viên tòa B"'
                        style="flex:1; background:none; border:none; outline:none; font-size:0.95rem; color:var(--text); font-family:inherit;"
                        onkeypress="if(event.key==='Enter') runAiSearch()">
                    <button onclick="runAiSearch()" id="aiSearchBtn" style="
                        background: linear-gradient(135deg,#6366f1,#8b5cf6);
                        border: none; border-radius: 10px;
                        padding: 8px 18px; color: #fff; font-size: 0.9rem;
                        font-weight: 600; cursor: pointer; flex-shrink:0;
                        transition: opacity 0.2s;
                    ">
                        <i class="fas fa-search"></i> Tìm
                    </button>
                </div>
                <!-- Search Results Dropdown -->
                <div id="aiSearchResults" style="display:none; position:absolute; top:calc(100% + 8px); left:0; right:0; z-index:500;
                    background:var(--surface); border:1px solid var(--stroke); border-radius:14px;
                    box-shadow:0 20px 40px rgba(0,0,0,0.15); max-height:450px; overflow-y:auto;"></div>
            </div>
        </div>

        <!-- 2. Bento Metrics Grid -->
        <div class="bento-metrics">
            <div class="bento-metric-card glow-amber">
                <div class="icon-wrapper">
                    <i class="fas fa-file-invoice"></i>
                </div>
                <div class="metric-content">
                    <h3>Hóa đơn chờ</h3>
                    <div class="metric-number"><?= $unpaidCount ?></div>
                    <span class="trend trend-warning"><i class="fas fa-exclamation-circle"></i> Cần xử lý</span>
                </div>
            </div>
            
            <div class="bento-metric-card glow-fuchsia">
                <div class="icon-wrapper">
                    <i class="fas fa-comment-dots"></i>
                </div>
                <div class="metric-content">
                    <h3>Phản ánh</h3>
                    <div class="metric-number"><?= $feedbackPending ?></div>
                    <span class="trend trend-info"><i class="fas fa-clock"></i> Chờ phản hồi</span>
                </div>
            </div>

            <div class="bento-metric-card glow-emerald">
                <div class="icon-wrapper">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="metric-content">
                    <h3>Hợp đồng</h3>
                    <div class="metric-number"><?= $activeContracts ?></div>
                    <span class="trend trend-success"><i class="fas fa-chart-line"></i> Đang hiệu lực</span>
                </div>
            </div>

            <div class="bento-metric-card glow-blue">
                <div class="icon-wrapper">
                    <i class="fas fa-home"></i>
                </div>
                <div class="metric-content">
                    <h3>Phòng trống</h3>
                    <div class="metric-number"><?= $availableRooms ?></div>
                    <span class="trend trend-primary"><i class="fas fa-key"></i> Sẵn sàng</span>
                </div>
            </div>
        </div>

        <!-- 3. Bento Actions Modules -->
        <div class="bento-modules">
            <!-- Systems Management Bento -->
            <div class="bento-module-card span-col-2">
                <div class="module-header">
                    <div class="header-icon"><i class="fas fa-cogs"></i></div>
                    <h2>Quản lý Hệ Thống</h2>
                </div>
                <div class="module-grid-links">
                    <a href="../staff/users/users.php" class="bento-btn"><i class="fas fa-users-cog"></i> Mọi Người Dùng</a>
                    <a href="../staff/students/student_list.php" class="bento-btn"><i class="fas fa-user-graduate"></i> Sinh Viên</a>
                    <a href="../staff/buildings/building_list.php" class="bento-btn"><i class="fas fa-building"></i> Tòa Nhà</a>
                    <a href="../staff/rooms/rooms.php" class="bento-btn"><i class="fas fa-bed"></i> Quản Lý Phòng</a>
                    <a href="../staff/feedbacks/feedback_list.php" class="bento-btn"><i class="fas fa-headset"></i> Trung Tâm Hỗ Trợ</a>
                    <a href="../staff/invoices/invoice_list.php" class="bento-btn"><i class="fas fa-file-invoice-dollar"></i> Kế Toán / Hóa Đơn</a>
                    <a href="../staff/contract/contract_list.php" class="bento-btn"><i class="fas fa-file-signature"></i> Thỏa Thuận Lưu Trú</a>
                    <a href="../staff/room_requests/staff_request_list.php" class="bento-btn"><i class="fas fa-clipboard-list"></i> Yêu Cầu Thuê Phòng</a>
                    <a href="../../modules/staff/announcements/announcement_list.php" class="bento-btn"><i class="fas fa-bullhorn"></i> Bản Tin Hệ Thống</a>
                </div>
            </div>

            <div class="bento-module-column">
                <!-- Quick Actions Bento -->
                <div class="bento-module-card quick-actions">
                    <div class="module-header">
                        <div class="header-icon"><i class="fas fa-bolt"></i></div>
                        <h2>Tác Vụ Tốc Độ</h2>
                    </div>
                    <div class="module-list-links">
                        <a href="../staff/buildings/building_create.php" class="list-item"><i class="fas fa-plus"></i> Khởi tạo Tòa nhà</a>
                        <a href="../../modules/staff/users/user_create.php" class="list-item"><i class="fas fa-user-plus"></i> Cấp tài khoản mới</a>
                        <a href="../staff/students/student_create.php" class="list-item"><i class="fas fa-plus-circle"></i> Hồ sơ Sinh viên</a>
                        <a href="../staff/rooms/room_add.php" class="list-item"><i class="fas fa-folder-plus"></i> Thêm Phòng ở</a>
                        <a href="../staff/invoices/invoice_create.php" class="list-item"><i class="fas fa-file-medical"></i> Lập Hóa đơn</a>
                        <a href="../staff/contract/contract_create.php" class="list-item"><i class="fas fa-pen-nib"></i> Soạn Hợp đồng</a>
                        <a href="../staff/announcements/announcement_create.php" class="list-item"><i class="fas fa-broadcast-tower"></i> Đăng Thông báo</a>
                    </div>
                </div>
            </div>
            
            <div class="bento-module-column">
                <!-- Analytics Bento -->
                <div class="bento-module-card analytics glassmorphism">
                    <div class="module-header glass-header">
                        <div class="header-icon"><i class="fas fa-chart-pie"></i></div>
                        <h2>Báo Cáo & Thống Kê</h2>
                    </div>
                    <div class="module-list-links">
                        <a href="../admin/report/finance_report.php" class="list-item hover-glass"><i class="fas fa-chart-line"></i> Dòng tiền & Tài chính</a>
                        <a href="../../modules/staff/rooms/room_occupancy.php" class="list-item hover-glass"><i class="fas fa-chart-bar"></i> Lấp đầy Cơ sở vật chất</a>
                        <a href="../../modules/staff/feedbacks/feedback_stats.php" class="list-item hover-glass"><i class="fas fa-chart-area"></i> Đo lường Sự Hài Lòng</a>
                        <a href="../../modules/staff/users/user_stats.php" class="list-item hover-glass"><i class="fas fa-users-viewfinder"></i> Tổng quan Người dùng</a>
                        <a href="../../modules/staff/students/student_stats.php" class="list-item hover-glass"><i class="fas fa-graduation-cap"></i> Phân loại Sinh viên</a>
                    </div>
                </div>
            </div>
            
        </div>
    </div>

    <div class="mod-card" style="margin-top: 24px; padding: 24px;">
        <h3 style="margin-top: 0;"><i class="fas fa-rocket"></i> Wave 1</h3>
        <div class="module-grid-links">
            <a href="../../modules/staff/communications/mass_email.php" class="bento-btn"><i class="fas fa-envelope-open-text"></i> Mass Email</a>
            <a href="../../modules/staff/report/report_hub.php" class="bento-btn"><i class="fas fa-file-export"></i> Export báo cáo</a>
            <a href="../../modules/staff/access/gate_scanner.php" class="bento-btn"><i class="fas fa-qrcode"></i> Quét cổng</a>
            <a href="../../modules/staff/access/gate_logs.php" class="bento-btn"><i class="fas fa-door-open"></i> Nhật ký ra/vào</a>
        </div>
    </div>

    <?php include '../../includes/footer.php'; ?>

    <script>
        // ========== NUMBER ANIMATION ==========
        document.addEventListener('DOMContentLoaded', () => {
            const metricNumbers = document.querySelectorAll('.metric-number, .wqs-info .number');
            metricNumbers.forEach(el => {
                const targetNum = parseInt(el.textContent.replace(/[^0-9]/g, ''), 10);
                if(isNaN(targetNum) || targetNum === 0) return;
                let cur = 0;
                const step = Math.max(1, targetNum / 50);
                const timer = setInterval(() => {
                    cur += step;
                    if (cur >= targetNum) { cur = targetNum; clearInterval(timer); }
                    el.textContent = Math.floor(cur);
                }, 30);
            });

            const banner = document.querySelector('.bento-welcome');
            const flare = document.querySelector('.flare-effect');
            if(banner && flare) {
                banner.addEventListener('mousemove', (e) => {
                    const rect = banner.getBoundingClientRect();
                    flare.style.background = `radial-gradient(circle at ${e.clientX - rect.left}px ${e.clientY - rect.top}px, rgba(255,255,255,0.15) 0%, transparent 50%)`;
                });
            }

            // Auto-load AI insights on page load
            loadAiInsights();
        });

        // ========== AI EXECUTIVE SUMMARY ==========
        async function loadAiInsights() {
            const textEl = document.getElementById('aiInsightsText');
            if (!textEl) return;
            textEl.innerHTML = '<span style="display:inline-flex;align-items:center;gap:8px;color:var(--text-secondary);"><i class="fas fa-circle-notch fa-spin" style="color:#6366f1;"></i>Đang phân tích dữ liệu hệ thống...</span>';
            try {
                const res = await fetch('../../modules/api/admin_ai_insights.php');
                const data = await res.json();
                if (data.ok && data.summary) {
                    const isFallback = data._fallback ? ' <span style="font-size:0.75rem;color:var(--text-secondary);margin-left:8px;">(Phân tích cục bộ)</span>' : '';
                    textEl.innerHTML = `<span>${escHtml(data.summary)}</span>${isFallback}`;
                } else {
                    textEl.innerHTML = '<span style="color:var(--text-secondary);">Không thể tải báo cáo AI lúc này.</span>';
                }
            } catch(e) {
                textEl.innerHTML = '<span style="color:var(--text-secondary);">Máy chủ AI chưa khởi động. Hãy chạy Node server!</span>';
            }
        }

        function escHtml(t) {
            const d = document.createElement('div'); d.textContent = t; return d.innerHTML;
        }

        // ========== AI NATURAL LANGUAGE SEARCH ==========
        async function runAiSearch() {
            const input = document.getElementById('aiSearchInput');
            const btn = document.getElementById('aiSearchBtn');
            const resultsEl = document.getElementById('aiSearchResults');
            const q = input.value.trim();
            if (!q) { input.focus(); return; }

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i>';
            resultsEl.style.display = 'block';
            resultsEl.innerHTML = '<div style="padding:20px;text-align:center;color:var(--text-secondary);"><i class="fas fa-circle-notch fa-spin" style="color:#6366f1;margin-right:8px;"></i>AI đang phân tích truy vấn...</div>';

            try {
                const res = await fetch('../../modules/api/admin_ai_search.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ query: q })
                });
                const data = await res.json();

                if (!data.ok) { throw new Error(data.error); }

                const entityIcons = { invoice: 'fa-file-invoice', student: 'fa-user-graduate', room: 'fa-bed', feedback: 'fa-comments', contract: 'fa-file-signature' };
                const icon = entityIcons[data.entity] || 'fa-search';

                let html = `<div style="padding:12px 16px; border-bottom:1px solid var(--stroke); display:flex; align-items:center; gap:10px;">
                    <i class="fas ${icon}" style="color:#6366f1;"></i>
                    <span style="font-size:0.85rem; color:var(--text-secondary);">${escHtml(data.interpretation)}</span>
                    <span style="margin-left:auto; font-size:0.8rem; font-weight:700; color:#6366f1;">${data.count} kết quả</span>
                    <button onclick="document.getElementById('aiSearchResults').style.display='none'" style="background:none;border:none;cursor:pointer;color:var(--text-secondary);">&times;</button>
                </div>`;

                if (data.results.length === 0) {
                    html += '<div style="padding:30px;text-align:center;color:var(--text-secondary);">Không tìm thấy kết quả phù hợp</div>';
                } else {
                    html += '<div style="padding:8px;">';
                    data.results.slice(0, 20).forEach(row => {
                        html += renderSearchRow(data.entity, row);
                    });
                    if (data.count > 20) {
                        html += `<div style="padding:10px;text-align:center;font-size:0.85rem;color:var(--text-secondary);">... và ${data.count - 20} kết quả khác</div>`;
                    }
                    html += '</div>';
                }

                resultsEl.innerHTML = html;
            } catch(e) {
                resultsEl.innerHTML = `<div style="padding:20px;text-align:center;color:#ef4444;"><i class="fas fa-exclamation-circle"></i> Lỗi: ${escHtml(e.message || 'Không thể kết nối AI')}</div>`;
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-search"></i> Tìm';
            }
        }

        function renderSearchRow(entity, row) {
            const base = '<?= htmlspecialchars($base) ?>';
            let link = '#', title = '', sub = '', badge = '';

            if (entity === 'invoice') {
                link = `${base}modules/staff/invoices/invoice_detail.php?id=${row.InvoiceID}`;
                const amt = Number(row.TotalAmount).toLocaleString('vi-VN') + ' ₫';
                title = `HĐ #${row.InvoiceID} - ${row.FullName || ''} (${row.StudentCode || ''})`;
                sub = `${row.BuildingName || ''} - Phòng ${row.RoomNumber || ''} | T${row.Month}/${row.Year} | ${amt}`;
                const statusColor = row.Status === 'Đã thanh toán' ? '#10b981' : '#f59e0b';
                badge = `<span style="font-size:0.75rem;padding:2px 8px;border-radius:99px;background:${statusColor}20;color:${statusColor};font-weight:600;">${row.Status || ''}</span>`;
            } else if (entity === 'student') {
                link = `${base}modules/staff/students/student_view.php?id=${row.StudentID}`;
                title = `${row.FullName || ''} (${row.StudentCode || ''})`;
                sub = `${row.FacultyName || 'Chưa gán Khoa'} • ${row.RoomNumber ? 'Phòng ' + row.RoomNumber + ' - ' + row.BuildingName : 'Chưa có phòng'}`;
                badge = `<span style="font-size:0.75rem;padding:2px 8px;border-radius:99px;background:rgba(99,102,241,0.1);color:#6366f1;font-weight:600;">${row.Gender || ''}</span>`;
            } else if (entity === 'feedback') {
                link = `${base}modules/staff/feedbacks/feedback_resolve.php?id=${row.FeedbackID}`;
                title = row.Title || '';
                sub = `${row.FullName || ''} (${row.StudentCode || ''}) • ${new Date(row.CreatedAt).toLocaleDateString('vi-VN')}`;
                const sc = row.Status === 'Đã xử lý' ? '#10b981' : row.Status === 'Đang xử lý' ? '#3b82f6' : '#f59e0b';
                badge = `<span style="font-size:0.75rem;padding:2px 8px;border-radius:99px;background:${sc}20;color:${sc};font-weight:600;">${row.Status || ''}</span>`;
            } else if (entity === 'room') {
                link = `${base}modules/staff/rooms/room_detail.php?id=${row.RoomID}`;
                title = `Phòng ${row.RoomNumber} - ${row.BuildingName}`;
                sub = `${row.RoomType || ''} • Sức chứa: ${row.Capacity} • ${Number(row.RoomPrice).toLocaleString('vi-VN')} ₫/tháng`;
                const rc = row.Status === 'Trống' ? '#10b981' : '#6b7280';
                badge = `<span style="font-size:0.75rem;padding:2px 8px;border-radius:99px;background:${rc}20;color:${rc};font-weight:600;">${row.Status || ''}</span>`;
            }

            return `<a href="${link}" style="display:flex;align-items:center;gap:12px;padding:10px 12px;border-radius:10px;text-decoration:none;transition:background 0.15s;" 
                    onmouseover="this.style.background='var(--bg)'" onmouseout="this.style.background='none'">
                <div style="flex:1;min-width:0;">
                    <div style="font-weight:600;color:var(--text);font-size:0.9rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${escHtml(title)}</div>
                    <div style="font-size:0.8rem;color:var(--text-secondary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:2px;">${escHtml(sub)}</div>
                </div>
                ${badge}
                <i class="fas fa-chevron-right" style="color:var(--text-secondary);font-size:0.75rem;flex-shrink:0;"></i>
            </a>`;
        }

        // Close search results on outside click
        document.addEventListener('click', (e) => {
            const wrap = document.getElementById('aiSearchWrap');
            const results = document.getElementById('aiSearchResults');
            if (wrap && results && !wrap.contains(e.target) && !results.contains(e.target)) {
                results.style.display = 'none';
            }
        });
    </script>
</body>
</html>
