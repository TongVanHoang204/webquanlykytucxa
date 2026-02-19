<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: text/html; charset=utf-8');
require_once '../../../db_connect.php';
require_once '../../../includes/admin_header.php';
require_once '../../../includes/auth_check.php';
requireRole(['Admin', 'Manager']);


// Bật chế độ báo lỗi MySQLi rõ ràng (hữu ích khi dev)
mysqli_report(MYSQLI_REPORT_OFF); // tránh throw Exception nếu bạn chưa try/catch

$sqlError = null;

// Lấy danh sách thông báo với thông tin chi tiết hơn
$sql = "SELECT 
            a.AnnouncementID,
            a.Title,
            a.Content,
            a.DatePosted,
            a.AttachmentPath,
            u.FullName AS AuthorName,
            u.Role AS AuthorRole,
            CASE 
              WHEN a.DatePosted >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 'new'
              WHEN a.DatePosted >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 'recent'
              ELSE 'old'
            END AS PostRecency
          FROM Announcements a
          LEFT JOIN Users u ON a.PostedBy = u.UserID
          ORDER BY a.DatePosted DESC";

$list = $conn->query($sql);
if (!$list) {
    $sqlError = "Query Announcements failed: " . $conn->error;
    $hasAnnouncements = false;
} else {
    $hasAnnouncements = ($list instanceof mysqli_result) && ($list->num_rows > 0);
}

// Thống kê (bọc kiểm tra lỗi tương tự)
function scalarCount($conn, $q)
{
    $r = $conn->query($q);
    if ($r && $r instanceof mysqli_result) {
        $row = $r->fetch_assoc();
        // Cần kiểm tra $row tồn tại trước khi truy cập
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
    <title>Danh sách thông báo | Hệ thống Ký túc xá</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="../../../assets/css/admin/admin_header.css">
    <link rel="stylesheet" href="../../../assets/css/staff/announcements/announcement_list.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    </head>

<body>
    <div class="announcement-container">
        <div class="announcement-header">
            <h2><i class="fa-solid fa-bullhorn"></i> Quản lý Thông báo</h2>
        </div>

        <div class="stats-grid">
            <div class="stat">
                <i class="fa-solid fa-bullhorn"></i>
                <div class="stat-content">
                    <h3><?= $totalAnnouncements ?></h3>
                    <p>Tổng thông báo</p>
                </div>
            </div>
            <div class="stat">
                <i class="fa-solid fa-calendar-day"></i>
                <div class="stat-content">
                    <h3><?= $todayAnnouncements ?></h3>
                    <p>Hôm nay</p>
                </div>
            </div>
            <div class="stat">
                <i class="fa-solid fa-calendar-week"></i>
                <div class="stat-content">
                    <h3><?= $weekAnnouncements ?></h3>
                    <p>7 ngày qua</p>
                </div>
            </div>
        </div>

        <div class="search-filter-bar">
            <div class="search-box">
                <i class="fa-solid fa-search"></i>
                <input type="text" id="searchInput" placeholder="Tìm thông báo theo tiêu đề hoặc nội dung">
            </div>
            <select class="filter-select" id="statusFilter" onchange="filterAnnouncements()">
                <option value="all">Tất cả trạng thái</option>
                <option value="new">Mới</option>
                <option value="recent">Gần đây</option>
                <option value="old">Cũ</option>
            </select>

        </div>

        <div class="quick-actions">
            <a href="announcement_create.php" class="quick-action-btn"><i class="fa-solid fa-plus"></i><span>Tạo mới</span></a>
            <a href="#" class="quick-action-btn" onclick="exportAnnouncements()"><i class="fa-solid fa-download"></i><span>Xuất danh sách</span></a>
            <a href="#" class="quick-action-btn" onclick="showAllAnnouncements()"><i class="fa-solid fa-list"></i><span>Xem tất cả</span></a>
        </div>

        <div class="view-toggle" id="viewToggle">
            <button class="view-toggle-btn active" onclick="switchView('table', this)"><i class="fa-solid fa-table"></i><span>Dạng bảng</span></button>
            <button class="view-toggle-btn" onclick="switchView('grid', this)"><i class="fa-solid fa-grid-2"></i><span>Dạng lưới</span></button>
        </div>

        <div class="table-view" id="tableView">
            <div class="table-wrap">
                <table class="table table-fix">
                    <colgroup>
                        <col style="width:30%">
                        <col style="width:10%">
                        <col style="width:12%">
                        <col style="width:8%">
                        <col style="width:8%">
                        <col style="width:12%">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>TIÊU ĐỀ</th>
                            <th>NGÀY ĐĂNG</th>
                            <th>NGƯỜI ĐĂNG</th>
                            <th class="text-center">TỆP ĐÍNH KÈM</th>
                            <th class="text-center">TRẠNG THÁI</th>
                            <th class="text-center">HÀNH ĐỘNG</th>
                        </tr>
                    </thead>
                    <tbody id="announcementTableBody">
                        <?php if ($hasAnnouncements): ?>
                            <?php $rowIndex = 0; while ($row = $list->fetch_assoc()):
                                $rowIndex++;
                                $isNew = $row['PostRecency'] === 'new';
                                $isRecent = $row['PostRecency'] === 'recent';
                                $postDate = date('d/m/Y', strtotime($row['DatePosted']));
                                $postTime = date('H:i', strtotime($row['DatePosted']));
                                $authorName = htmlspecialchars($row['AuthorName'] ?? 'Quản Lý');
                                $authorRole = htmlspecialchars($row['AuthorRole'] ?? '');
                                $animationDelay = ($rowIndex - 1) * 0.05 + 0.4;
                            ?>
                                <tr class="announcement-row <?= $isNew ? 'new-announcement' : '' ?>"
                                    style="animation-delay: <?= $animationDelay ?>s"
                                    data-status="<?= $row['PostRecency'] ?>"
                                    data-author="<?= $authorName ?>"
                                    data-search="<?= htmlspecialchars(($row['Title'] ?? '') . ' ' . ($row['Content'] ?? '')) ?>">
                                    <td>
                                        <div class="announcement-title">
                                            <strong><?= htmlspecialchars($row['Title']) ?></strong>
                                            <?php if ($isNew): ?>
                                                <span class="badge priority-high"><i class="fa-solid fa-star"></i> MỚI</span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($row['Content'])): ?>
                                            <div class="text-muted announcement-content-excerpt">
                                                <?= htmlspecialchars(mb_substr($row['Content'], 0, 100)) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="cell announcement-date">
                                            <span class="primary"><?= $postDate ?></span>
                                            <span class="sub"><?= $postTime ?></span>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="cell announcement-author">
                                            <span class="primary"><?= $authorName ?></span>
                                            <?php if (!empty($authorRole)): ?>
                                                <span class="sub"><?= $authorRole ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                    <td class="text-center">
                                        <?php if (!empty($row['AttachmentPath'])): ?>
                                            <a href="../../../<?= htmlspecialchars($row['AttachmentPath']) ?>" target="_blank" class="btn ghost icon small" title="Tải xuống tệp đính kèm">
                                                <i class="fa-solid fa-paperclip"></i>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted no-attachment">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($isNew): ?>
                                            <span class="status active">Mới</span>
                                        <?php elseif ($isRecent): ?>
                                            <span class="status active">Gần đây</span>
                                        <?php else: ?>
                                            <span class="status inactive">Cũ</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="row-actions">
                                            <a href="announcement_view.php?id=<?= $row['AnnouncementID'] ?>" class="btn info icon small" title="Xem chi tiết"><i class="fa-solid fa-eye"></i></a>
                                            <a href="announcement_edit.php?id=<?= $row['AnnouncementID'] ?>" class="btn warning icon small" title="Chỉnh sửa"><i class="fa-solid fa-pen-to-square"></i></a>
                                            <button class="btn danger icon small" onclick="deleteAnnouncement(<?= $row['AnnouncementID'] ?>,'<?= htmlspecialchars($row['Title'], ENT_QUOTES) ?>')" title="Xóa thông báo"><i class="fa-solid fa-trash-can"></i></button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="empty-state">
                                    <i class="fa-solid fa-bell-slash"></i>
                                    <h3>Chưa có thông báo nào</h3>
                                    <p>Hãy tạo thông báo đầu tiên để thông báo tới cư dân ký túc xá.</p>
                                    <a href="announcement_create.php" class="btn primary mt-3"><i class="fa-solid fa-plus"></i> Tạo thông báo đầu tiên</a>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="grid-view" id="gridView" style="display:none;">
            <div class="announcement-grid" id="announcementGrid">
                <?php if ($hasAnnouncements):
                    $list->data_seek(0);
                    while ($row = $list->fetch_assoc()):
                        $isNew = $row['PostRecency'] === 'new';
                        $postDate = date('d/m/Y H:i', strtotime($row['DatePosted']));
                        $authorName = htmlspecialchars($row['AuthorName'] ?? 'Quản Lý');
                ?>
                        <div class="announcement-card announcement-item <?= $isNew ? 'new-announcement' : '' ?>"
                            data-status="<?= $row['PostRecency'] ?>"
                            data-author="<?= $authorName ?>"
                            data-search="<?= htmlspecialchars(($row['Title'] ?? '') . ' ' . ($row['Content'] ?? '')) ?>">
                            <div class="announcement-card-header">
                                <h3><?= htmlspecialchars($row['Title']) ?></h3>
                            </div>
                            <div class="announcement-card-content"><?= htmlspecialchars(mb_substr($row['Content'], 0, 150)) . (mb_strlen($row['Content']) > 150 ? '...' : '') ?></div>
                            <div class="announcement-card-meta">
                                <div class="announcement-card-author"><i class="fa-solid fa-user"></i><?= $authorName ?></div>
                                <div class="announcement-card-date"><i class="fa-solid fa-calendar"></i><?= $postDate ?></div>
                            </div>
                            <div class="announcement-card-footer">
                                <?php if (!empty($row['AttachmentPath'])): ?>
                                    <div class="announcement-attachment">
                                        <a href="../../../<?= htmlspecialchars($row['AttachmentPath']) ?>" target="_blank" class="attachment-link">
                                            <i class="fa-solid fa-paperclip"></i><span>Tệp đính kèm</span>
                                        </a>
                                    </div>
                                <?php else: ?><div></div><?php endif; ?>
                                <div class="row-actions">
                                    <a href="announcement_view.php?id=<?= $row['AnnouncementID'] ?>" class="btn info icon small" title="Xem chi tiết"><i class="fa-solid fa-eye"></i></a>
                                    <a href="announcement_edit.php?id=<?= $row['AnnouncementID'] ?>" class="btn warning icon small" title="Chỉnh sửa"><i class="fa-solid fa-pen-to-square"></i></a>
                                    <button class="btn danger icon small" onclick="deleteAnnouncement(<?= $row['AnnouncementID'] ?>,'<?= htmlspecialchars($row['Title'], ENT_QUOTES) ?>')" title="Xóa thông báo"><i class="fa-solid fa-trash-can"></i></button>
                                </div>
                            </div>
                        </div>
                    <?php endwhile;
                else: ?>
                    <div class="empty-state" style="grid-column:1/-1;">
                        <i class="fa-solid fa-bell-slash"></i>
                        <h3>Chưa có thông báo nào</h3>
                        <p>Hãy tạo thông báo đầu tiên để thông báo tới cư dân ký túc xá.</p>
                        <a href="announcement_create.php" class="btn primary mt-3"><i class="fa-solid fa-plus"></i> Tạo thông báo đầu tiên</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="pagination" id="pagination" style="display:none;">
            <button class="pagination-btn" onclick="changePage(-1)" id="prevBtn">Trước</button>
            <span class="pagination-info" id="pageInfo">Trang 1 của 1</span>
            <button class="pagination-btn" onclick="changePage(1)" id="nextBtn">Sau</button>
        </div>

    </div>

    <script>
        // Biến toàn cục
        let currentPage = 1;
        const itemsPerPage = 8;
        let filteredItems = [];

        // Toast (đã được thay thế bằng Swal.fire)
        function showToast(message, type = 'info') {
             Swal.fire({
                toast: true,
                position: 'top-end',
                icon: type === 'success' ? 'success' : type === 'error' ? 'error' : 'info',
                title: message,
                showConfirmButton: false,
                timer: 2000,
                timerProgressBar: true
            });
        }

        // Xóa
        function deleteAnnouncement(id, title) {
            Swal.fire({
                title: 'Xóa thông báo?',
                html: `Bạn có chắc muốn xóa thông báo <b>"${title}"</b>?<br><small class="text-muted">Hành động này không thể hoàn tác.</small>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Xóa thông báo',
                cancelButtonText: 'Hủy bỏ',
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
                reverseButtons: true,
                showClass: {
                    popup: 'animate__animated animate__fadeInDown'
                },
                hideClass: {
                    popup: 'animate__animated animate__fadeOutUp'
                }
            }).then(res => {
                if (!res.isConfirmed) return;
                document.body.classList.add('loading');
                fetch('announcement_delete_api.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: new URLSearchParams({
                            id
                        })
                    })
                    .then(r => r.json())
                    .then(data => {
                        document.body.classList.remove('loading');
                        if (data.status === 'success') {
                            showToast('Thông báo đã được xóa thành công!', 'success');
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            showToast(data.message || 'Không thể xóa thông báo.', 'error');
                        }
                    })
                    .catch(() => {
                        document.body.classList.remove('loading');
                        showToast('Không thể kết nối đến máy chủ.', 'error');
                    });
            });
        }

        // Đổi view
        function switchView(viewType, btnEl) {
            const tableView = document.getElementById('tableView');
            const gridView = document.getElementById('gridView');

            document.querySelectorAll('.view-toggle-btn').forEach(b => b.classList.remove('active'));
            if (btnEl) btnEl.classList.add('active');

            if (viewType === 'table') {
                tableView.style.display = 'block';
                gridView.style.display = 'none';
            } else {
                tableView.style.display = 'none';
                gridView.style.display = 'block';
            }
            localStorage.setItem('announcementView', viewType);

            // rebuild filteredItems theo view hiện tại
            const selector = viewType === 'table' ? '.announcement-row' : '.announcement-item';
            filteredItems = Array.from(document.querySelectorAll(selector)).filter(item => item.style.display !== 'none');
            updatePagination();
        }

        function toggleView() {
            const currentView = localStorage.getItem('announcementView') || 'table';
            const next = currentView === 'table' ? 'grid' : 'table';
            const btn = document.querySelector(`.view-toggle-btn[onclick*="${next}"]`);
            switchView(next, btn);
        }

        // Lọc
        function filterAnnouncements() {
            const searchTerm = (document.getElementById('searchInput').value || '').toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value;

            const currentView = localStorage.getItem('announcementView') || 'table';
            const items = document.querySelectorAll('.announcement-row, .announcement-item'); // Quét hết để ẩn/hiện

            filteredItems = [];
            items.forEach(item => {
                const searchData = (item.getAttribute('data-search') || '').toLowerCase();
                const status = item.getAttribute('data-status') || '';

                const matchesSearch = !searchTerm || searchData.includes(searchTerm);
                const matchesStatus = statusFilter === 'all' || status === statusFilter;

                const ok = matchesSearch && matchesStatus;
                
                // Ẩn/hiện tất cả các item trước, sau đó phân trang sẽ hiển thị lại
                item.style.display = 'none'; 

                if (ok) filteredItems.push(item);
            });

            currentPage = 1;
            updatePagination();
        }

        // Xem tất cả
        function showAllAnnouncements() {
            document.getElementById('searchInput').value = '';
            document.getElementById('statusFilter').value = 'all';
            filterAnnouncements();
        }

        // Phân trang
        function updatePagination() {
            const totalItems = filteredItems.length;
            const totalPages = Math.ceil(totalItems / itemsPerPage) || 1;

            const pagination = document.getElementById('pagination');
            const pageInfo = document.getElementById('pageInfo');
            const prevBtn = document.getElementById('prevBtn');
            const nextBtn = document.getElementById('nextBtn');
            const currentView = localStorage.getItem('announcementView') || 'table';
            
            // Ẩn/hiện pagination nếu không có mục nào
            if (totalItems === 0) {
                pagination.style.display = 'none';
                return;
            }


            // Hiển thị/ẩn phân trang
            if (totalPages <= 1) {
                pagination.style.display = 'none';
            } else {
                pagination.style.display = 'flex';
            }

            // Giới hạn currentPage
            if (currentPage > totalPages) currentPage = totalPages;
            if (currentPage < 1) currentPage = 1;

            pageInfo.textContent = `Trang ${currentPage} của ${totalPages}`;
            prevBtn.disabled = currentPage === 1;
            nextBtn.disabled = currentPage === totalPages;

            const start = (currentPage - 1) * itemsPerPage;
            const end = start + itemsPerPage;

            // Ẩn/hiện item theo trang
            filteredItems.forEach((item, index) => {
                item.style.display = (index >= start && index < end) ? '' : 'none';
            });
        }

        function changePage(direction) {
            const totalPages = Math.ceil(filteredItems.length / itemsPerPage) || 1;
            currentPage += direction;
            if (currentPage < 1) currentPage = 1;
            if (currentPage > totalPages) currentPage = totalPages;
            updatePagination();
        }

        // Build danh sách tác giả (từ cả 2 view)
        function rebuildAuthorFilter() {
            const authorFilter = document.getElementById('authorFilter');
            if (!authorFilter) return; // Kiểm tra nếu element không tồn tại
            // clear giữ lại option "all"
            authorFilter.innerHTML = '<option value="all">Tất cả người đăng</option>';
            const authors = new Set();
            document.querySelectorAll('.announcement-row, .announcement-item').forEach(item => {
                const a = item.getAttribute('data-author');
                if (a) authors.add(a);
            });
            [...authors].sort().forEach(a => {
                const op = document.createElement('option');
                op.value = a;
                op.textContent = a;
                authorFilter.appendChild(op);
            });
        }

        // Init
        document.addEventListener('DOMContentLoaded', function() {
            const savedView = localStorage.getItem('announcementView') || 'table';
            const btnInit = document.querySelector(`.view-toggle-btn[onclick*="${savedView}"]`);
            // Gọi switchView để hiển thị view đúng và khởi tạo filteredItems
            switchView(savedView, btnInit);

            // hiệu ứng cho item mới
            document.querySelectorAll('.new-announcement').forEach((el, idx) => el.style.animationDelay = `${idx*0.1}s`);

            // sự kiện tìm kiếm realtime
            document.getElementById('searchInput').addEventListener('input', filterAnnouncements);

            // sự kiện lọc theo trạng thái
            document.getElementById('statusFilter').addEventListener('change', filterAnnouncements);

            // lần đầu: hiển thị tất cả và phân trang
            filterAnnouncements(); 
        });

        function exportAnnouncements() {
            Swal.fire({
                title: 'Xuất danh sách',
                text: 'Tính năng này đang được phát triển.',
                icon: 'info',
                confirmButtonText: 'Đã hiểu',
                confirmButtonColor: '#3b82f6'
            });
        }
        
    </script>
</body>

</html>