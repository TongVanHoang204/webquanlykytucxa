<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: text/html; charset=utf-8');
require_once '../../../db_connect.php';
require_once '../../../includes/admin_header.php';
require_once '../../../includes/auth_check.php';
requireRole(['Admin', 'Manager']);

if (empty($_SESSION['_csrf'])) {
    $_SESSION['_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['_csrf'];

mysqli_report(MYSQLI_REPORT_OFF);

$sql = "SELECT 
            a.AnnouncementID, a.Title, a.Content, a.DatePosted, a.AttachmentPath,
            u.FullName AS AuthorName, u.Role AS AuthorRole,
            CASE 
              WHEN a.DatePosted >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 'new'
              WHEN a.DatePosted >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 'recent'
              ELSE 'old'
            END AS PostRecency
          FROM Announcements a
          LEFT JOIN Users u ON a.PostedBy = u.UserID
          ORDER BY a.DatePosted DESC";

$list = $conn->query($sql);
$hasAnnouncements = ($list && $list instanceof mysqli_result && $list->num_rows > 0);

function scalarCount($conn, $q) {
    $r = $conn->query($q);
    if ($r && $r instanceof mysqli_result) {
        $row = $r->fetch_assoc();
        return (int)($row['total'] ?? 0);
    }
    return 0;
}

$totalAnnouncements = scalarCount($conn, "SELECT COUNT(*) AS total FROM Announcements");
$todayAnnouncements = scalarCount($conn, "SELECT COUNT(*) AS total FROM Announcements WHERE DATE(DatePosted) = CURDATE()");
$weekAnnouncements  = scalarCount($conn, "SELECT COUNT(*) AS total FROM Announcements WHERE DatePosted >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Thông báo | Ký túc xá</title>
    <link rel="stylesheet" href="<?= $base ?>assets/css/global.css">
    <link rel="stylesheet" href="<?= $base ?>assets/css/modules_shared.css">
    <link rel="stylesheet" href="<?= $base ?>assets/css/admin/admin_header.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .view-toggle { display:flex; gap:4px; background:var(--surface); border:1px solid var(--stroke); border-radius:10px; padding:4px; margin-bottom:20px; width:fit-content; }
        .view-toggle-btn { padding:8px 16px; border:none; background:transparent; border-radius:8px; cursor:pointer; font-size:0.85rem; font-weight:600; color:var(--text-secondary); transition:all 0.2s; display:flex; align-items:center; gap:6px; }
        .view-toggle-btn.active { background:var(--gradient-primary); color:#fff; }
        .ann-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(340px, 1fr)); gap:16px; }
        .ann-card { background:var(--surface); border:1px solid var(--stroke); border-radius:16px; padding:20px; transition:all 0.3s ease; display:flex; flex-direction:column; }
        .ann-card:hover { transform:translateY(-3px); box-shadow:var(--shadow-lg); }
        .ann-card.is-new { border-left:4px solid var(--primary); }
        .ann-card-head { display:flex; justify-content:space-between; align-items:flex-start; gap:8px; margin-bottom:12px; }
        .ann-card-head h3 { font-size:0.95rem; font-weight:700; margin:0; color:var(--text); line-height:1.4; }
        .ann-card-body { font-size:0.84rem; color:var(--text-secondary); line-height:1.6; flex:1; margin-bottom:12px; }
        .ann-card-meta { display:flex; justify-content:space-between; font-size:0.78rem; color:var(--text-secondary); margin-bottom:12px; }
        .ann-card-footer { display:flex; justify-content:space-between; align-items:center; padding-top:12px; border-top:1px solid var(--stroke); }
    </style>
</head>
<body>
    <div class="mod-container">
        <!-- Header -->
        <div class="mod-header">
            <div class="mod-header-left">
                <h2><i class="fas fa-bullhorn"></i> Quản lý Thông báo</h2>
            </div>
            <div class="mod-header-right">
                <a href="announcement_create.php" class="mod-btn mod-btn-primary">
                    <i class="fas fa-plus"></i> Tạo mới
                </a>
            </div>
        </div>

        <!-- Stats -->
        <div class="mod-stats mod-stagger">
            <div class="mod-stat accent-blue">
                <div class="mod-stat-icon" style="background:var(--gradient-primary);"><i class="fas fa-bullhorn"></i></div>
                <div class="mod-stat-info">
                    <span class="mod-stat-number"><?= $totalAnnouncements ?></span>
                    <span class="mod-stat-label">Tổng thông báo</span>
                </div>
            </div>
            <div class="mod-stat accent-green">
                <div class="mod-stat-icon" style="background:var(--gradient-success);"><i class="fas fa-calendar-day"></i></div>
                <div class="mod-stat-info">
                    <span class="mod-stat-number"><?= $todayAnnouncements ?></span>
                    <span class="mod-stat-label">Hôm nay</span>
                </div>
            </div>
            <div class="mod-stat accent-purple">
                <div class="mod-stat-icon" style="background:var(--gradient-info);"><i class="fas fa-calendar-week"></i></div>
                <div class="mod-stat-info">
                    <span class="mod-stat-number"><?= $weekAnnouncements ?></span>
                    <span class="mod-stat-label">7 ngày qua</span>
                </div>
            </div>
        </div>

        <!-- Search & Filter -->
        <div class="mod-filters" style="align-items:flex-end;">
            <div class="mod-filter-group" style="flex:2;">
                <label><i class="fas fa-search"></i> Tìm kiếm</label>
                <input type="text" id="searchInput" class="mod-input" placeholder="Tìm theo tiêu đề hoặc nội dung...">
            </div>
            <div class="mod-filter-group">
                <label><i class="fas fa-filter"></i> Trạng thái</label>
                <select class="mod-select" id="statusFilter" onchange="filterAnnouncements()">
                    <option value="all">Tất cả</option>
                    <option value="new">Mới</option>
                    <option value="recent">Gần đây</option>
                    <option value="old">Cũ</option>
                </select>
            </div>
        </div>

        <!-- View Toggle -->
        <div class="view-toggle" id="viewToggle">
            <button class="view-toggle-btn active" onclick="switchView('table', this)"><i class="fas fa-table"></i> Bảng</button>
            <button class="view-toggle-btn" onclick="switchView('grid', this)"><i class="fas fa-th-large"></i> Lưới</button>
        </div>

        <!-- TABLE VIEW -->
        <div id="tableView">
            <div class="mod-table-wrap">
                <div class="mod-table-scroll">
                    <table class="mod-table">
                        <thead>
                            <tr>
                                <th>Tiêu đề</th>
                                <th>Ngày đăng</th>
                                <th>Người đăng</th>
                                <th>Tệp</th>
                                <th>Trạng thái</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody id="announcementTableBody">
                            <?php if ($hasAnnouncements): ?>
                                <?php while ($row = $list->fetch_assoc()):
                                    $isNew = $row['PostRecency'] === 'new';
                                    $isRecent = $row['PostRecency'] === 'recent';
                                    $postDate = date('d/m/Y', strtotime($row['DatePosted']));
                                    $postTime = date('H:i', strtotime($row['DatePosted']));
                                    $authorName = htmlspecialchars($row['AuthorName'] ?? 'Quản Lý');
                                    $authorRole = htmlspecialchars($row['AuthorRole'] ?? '');
                                ?>
                                    <tr class="announcement-row <?= $isNew ? 'new-announcement' : '' ?>"
                                        data-status="<?= $row['PostRecency'] ?>"
                                        data-search="<?= htmlspecialchars(($row['Title'] ?? '') . ' ' . ($row['Content'] ?? '')) ?>">
                                        <td>
                                            <div class="mod-cell-name">
                                                <?= htmlspecialchars($row['Title']) ?>
                                                <?php if ($isNew): ?>
                                                    <span class="mod-badge mod-badge-emerald" style="font-size:0.68rem;padding:2px 6px;margin-left:6px;">MỚI</span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (!empty($row['Content'])): ?>
                                                <div class="mod-cell-sub"><?= htmlspecialchars(mb_substr($row['Content'], 0, 80)) ?>...</div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="mod-cell-name"><?= $postDate ?></div>
                                            <div class="mod-cell-sub"><?= $postTime ?></div>
                                        </td>
                                        <td>
                                            <div class="mod-cell-name"><?= $authorName ?></div>
                                            <?php if ($authorRole): ?>
                                                <div class="mod-cell-sub"><?= $authorRole ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align:center;">
                                            <?php if (!empty($row['AttachmentPath'])): ?>
                                                <a href="<?= $base . htmlspecialchars($row['AttachmentPath']) ?>" target="_blank" class="mod-btn-icon view" title="Tải xuống">
                                                    <i class="fas fa-paperclip"></i>
                                                </a>
                                            <?php else: ?>
                                                <span class="mod-cell-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($isNew): ?>
                                                <span class="mod-badge mod-badge-emerald"><i class="fas fa-star"></i> Mới</span>
                                            <?php elseif ($isRecent): ?>
                                                <span class="mod-badge mod-badge-blue"><i class="fas fa-clock"></i> Gần đây</span>
                                            <?php else: ?>
                                                <span class="mod-badge mod-badge-gray"><i class="fas fa-archive"></i> Cũ</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="mod-row-actions">
                                                <a href="announcement_view.php?id=<?= $row['AnnouncementID'] ?>" class="mod-btn-icon view" title="Xem"><i class="fas fa-eye"></i></a>
                                                <a href="announcement_edit.php?id=<?= $row['AnnouncementID'] ?>" class="mod-btn-icon edit" title="Sửa"><i class="fas fa-pen"></i></a>
                                                <button class="mod-btn-icon delete" onclick="deleteAnnouncement(<?= $row['AnnouncementID'] ?>,'<?= htmlspecialchars($row['Title'], ENT_QUOTES) ?>')" title="Xóa"><i class="fas fa-trash-alt"></i></button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6">
                                        <div class="mod-empty">
                                            <i class="fas fa-bell-slash"></i>
                                            <p>Chưa có thông báo nào.</p>
                                            <a href="announcement_create.php" class="mod-btn mod-btn-primary mod-btn-sm"><i class="fas fa-plus"></i> Tạo thông báo</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- GRID VIEW -->
        <div id="gridView" style="display:none;">
            <div class="ann-grid" id="announcementGrid">
                <?php if ($hasAnnouncements):
                    $list->data_seek(0);
                    while ($row = $list->fetch_assoc()):
                        $isNew = $row['PostRecency'] === 'new';
                        $postDate = date('d/m/Y H:i', strtotime($row['DatePosted']));
                        $authorName = htmlspecialchars($row['AuthorName'] ?? 'Quản Lý');
                ?>
                    <div class="ann-card announcement-item <?= $isNew ? 'is-new' : '' ?>"
                        data-status="<?= $row['PostRecency'] ?>"
                        data-search="<?= htmlspecialchars(($row['Title'] ?? '') . ' ' . ($row['Content'] ?? '')) ?>">
                        <div class="ann-card-head">
                            <h3><?= htmlspecialchars($row['Title']) ?></h3>
                            <?php if ($isNew): ?>
                                <span class="mod-badge mod-badge-emerald" style="font-size:0.68rem;white-space:nowrap;">MỚI</span>
                            <?php endif; ?>
                        </div>
                        <div class="ann-card-body">
                            <?= htmlspecialchars(mb_substr($row['Content'], 0, 150)) ?><?= mb_strlen($row['Content']) > 150 ? '...' : '' ?>
                        </div>
                        <div class="ann-card-meta">
                            <span><i class="fas fa-user"></i> <?= $authorName ?></span>
                            <span><i class="fas fa-calendar"></i> <?= $postDate ?></span>
                        </div>
                        <div class="ann-card-footer">
                            <?php if (!empty($row['AttachmentPath'])): ?>
                                <a href="<?= $base . htmlspecialchars($row['AttachmentPath']) ?>" target="_blank" class="mod-btn mod-btn-outline mod-btn-sm">
                                    <i class="fas fa-paperclip"></i> Tệp
                                </a>
                            <?php else: ?><div></div><?php endif; ?>
                            <div class="mod-row-actions">
                                <a href="announcement_view.php?id=<?= $row['AnnouncementID'] ?>" class="mod-btn-icon view" title="Xem"><i class="fas fa-eye"></i></a>
                                <a href="announcement_edit.php?id=<?= $row['AnnouncementID'] ?>" class="mod-btn-icon edit" title="Sửa"><i class="fas fa-pen"></i></a>
                                <button class="mod-btn-icon delete" onclick="deleteAnnouncement(<?= $row['AnnouncementID'] ?>,'<?= htmlspecialchars($row['Title'], ENT_QUOTES) ?>')" title="Xóa"><i class="fas fa-trash-alt"></i></button>
                            </div>
                        </div>
                    </div>
                <?php endwhile;
                else: ?>
                    <div class="mod-empty" style="grid-column:1/-1;padding:60px;">
                        <i class="fas fa-bell-slash"></i>
                        <p>Chưa có thông báo nào.</p>
                        <a href="announcement_create.php" class="mod-btn mod-btn-primary mod-btn-sm"><i class="fas fa-plus"></i> Tạo thông báo</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Pagination -->
        <div class="mod-pagination" id="pagination" style="display:none;">
            <a class="mod-pg" id="prevBtn" onclick="changePage(-1)" href="javascript:void(0)">&lsaquo;</a>
            <span class="mod-pg-info" id="pageInfo">Trang 1/1</span>
            <a class="mod-pg" id="nextBtn" onclick="changePage(1)" href="javascript:void(0)">&rsaquo;</a>
        </div>
    </div>

    <script>
        const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        let currentPage = 1;
        const itemsPerPage = 8;
        let filteredItems = [];

        function deleteAnnouncement(id, title) {
            Swal.fire({
                title: 'Xóa thông báo?',
                html: `Xóa thông báo <b>"${title}"</b>?<br><small style="color:var(--text-secondary);">Không thể hoàn tác.</small>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-trash"></i> Xóa',
                cancelButtonText: 'Hủy',
                confirmButtonColor: '#f72585',
                cancelButtonColor: '#6c757d',
                reverseButtons: true
            }).then(res => {
                if (!res.isConfirmed) return;
                fetch('announcement_delete_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: new URLSearchParams({ id, _csrf: CSRF_TOKEN })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success') {
                        Swal.fire({ icon: 'success', title: 'Đã xóa!', timer: 1500, showConfirmButton: false });
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        Swal.fire('Lỗi', data.message || 'Không thể xóa.', 'error');
                    }
                })
                .catch(() => Swal.fire('Lỗi', 'Không thể kết nối máy chủ.', 'error'));
            });
        }

        function switchView(viewType, btnEl) {
            document.querySelectorAll('.view-toggle-btn').forEach(b => b.classList.remove('active'));
            if (btnEl) btnEl.classList.add('active');
            document.getElementById('tableView').style.display = viewType === 'table' ? 'block' : 'none';
            document.getElementById('gridView').style.display = viewType === 'grid' ? 'block' : 'none';
            localStorage.setItem('announcementView', viewType);
            const selector = viewType === 'table' ? '.announcement-row' : '.announcement-item';
            filteredItems = Array.from(document.querySelectorAll(selector)).filter(item => item.style.display !== 'none');
            updatePagination();
        }

        function filterAnnouncements() {
            const searchTerm = (document.getElementById('searchInput').value || '').toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value;
            const items = document.querySelectorAll('.announcement-row, .announcement-item');
            filteredItems = [];
            items.forEach(item => {
                const searchData = (item.getAttribute('data-search') || '').toLowerCase();
                const status = item.getAttribute('data-status') || '';
                const ok = (!searchTerm || searchData.includes(searchTerm)) && (statusFilter === 'all' || status === statusFilter);
                item.style.display = 'none';
                if (ok) filteredItems.push(item);
            });
            currentPage = 1;
            updatePagination();
        }

        function updatePagination() {
            const totalItems = filteredItems.length;
            const totalPages = Math.ceil(totalItems / itemsPerPage) || 1;
            const pagination = document.getElementById('pagination');
            if (totalItems === 0 || totalPages <= 1) { pagination.style.display = 'none'; } else { pagination.style.display = 'flex'; }
            if (currentPage > totalPages) currentPage = totalPages;
            if (currentPage < 1) currentPage = 1;
            document.getElementById('pageInfo').textContent = `Trang ${currentPage}/${totalPages}`;
            document.getElementById('prevBtn').classList.toggle('disabled', currentPage === 1);
            document.getElementById('nextBtn').classList.toggle('disabled', currentPage === totalPages);
            const start = (currentPage - 1) * itemsPerPage;
            const end = start + itemsPerPage;
            filteredItems.forEach((item, index) => { item.style.display = (index >= start && index < end) ? '' : 'none'; });
        }

        function changePage(dir) {
            const totalPages = Math.ceil(filteredItems.length / itemsPerPage) || 1;
            currentPage += dir;
            if (currentPage < 1) currentPage = 1;
            if (currentPage > totalPages) currentPage = totalPages;
            updatePagination();
        }

        document.addEventListener('DOMContentLoaded', function() {
            const savedView = localStorage.getItem('announcementView') || 'table';
            const btnInit = document.querySelector(`.view-toggle-btn[onclick*="${savedView}"]`);
            switchView(savedView, btnInit);
            document.getElementById('searchInput').addEventListener('input', filterAnnouncements);
            filterAnnouncements();
        });
    </script>
</body>
</html>
