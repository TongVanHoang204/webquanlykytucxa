<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../../../db_connect.php';
require_once '../../../includes/admin_header.php';
require_once '../../../includes/auth_check.php';
requireRole(['Admin', 'Manager']);

if (empty($_SESSION['_csrf'])) {
    $_SESSION['_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['_csrf'];

$conn->set_charset('utf8mb4');

/* ========== FILTERS ========== */
$status  = isset($_GET['status']) ? $_GET['status'] : 'all';
$keyword = isset($_GET['search']) ? $_GET['search'] : '';

/* ========== THỐNG KÊ ========== */
$statsSql = "
    SELECT 
        SUM(CASE WHEN Status = 'Chưa xử lý' THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN Status = 'Đang xử lý' THEN 1 ELSE 0 END) AS processing,
        SUM(CASE WHEN Status = 'Đã xử lý'   THEN 1 ELSE 0 END) AS resolved,
        COUNT(*) AS total
    FROM Feedbacks
";
$statsRes = $conn->query($statsSql);
$stats = $statsRes ? $statsRes->fetch_assoc() : ['pending' => 0, 'processing' => 0, 'resolved' => 0, 'total' => 0];

/* ========== TRUY VẤN ========== */
$sql = "
    SELECT f.FeedbackID, f.Title, f.Content, f.ImagePath, f.Status, f.CreatedAt,
           s.FullName, s.StudentCode, s.Gender, s.StudentID, fc.FacultyName
    FROM Feedbacks f
    JOIN Students s ON f.StudentID = s.StudentID
    LEFT JOIN Faculties fc ON fc.FacultyID = s.FacultyID
    WHERE 1=1
";

if ($status !== 'all') {
    $s = $conn->real_escape_string($status);
    $sql .= " AND f.Status = '$s'";
}
if ($keyword !== '') {
    $k = $conn->real_escape_string($keyword);
    $sql .= " AND (f.Title LIKE '%$k%' OR f.Content LIKE '%$k%' OR s.FullName LIKE '%$k%' OR s.StudentCode LIKE '%$k%' OR fc.FacultyName LIKE '%$k%')";
}
$sql .= " ORDER BY f.CreatedAt DESC";
$result = $conn->query($sql);

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function buildImageSrc($path) {
    if (!$path) return null;
    return '../../../' . ltrim($path, '/\\');
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= h($csrf) ?>">
    <title>Quản lý Phản ánh | Ký túc xá</title>
    <link rel="stylesheet" href="<?= $base ?>assets/css/global.css">
    <link rel="stylesheet" href="<?= $base ?>assets/css/modules_shared.css">
    <link rel="stylesheet" href="<?= $base ?>assets/css/admin/admin_header.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .img-modal { display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,0.85); justify-content:center; align-items:center; }
        .img-modal img { max-width:90vw; max-height:90vh; border-radius:12px; }
        .img-modal .close-btn { position:absolute; top:20px; right:30px; font-size:2rem; color:#fff; cursor:pointer; }
        
        /* Utility for text truncation */
        .text-truncate-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
    </style>
</head>
<body>
<div class="mod-container">
    <!-- Header -->
    <div class="mod-header">
        <div class="mod-header-left">
            <h2><i class="fas fa-comments"></i> Quản lý Phản ánh</h2>
        </div>
        <div class="mod-header-right">
            <a href="feedback_stats.php" class="mod-btn mod-btn-outline mod-btn-sm" style="margin-right: 8px;">
                <i class="fas fa-chart-pie"></i> Thống kê
            </a>
            <form style="display:flex;gap:0;" method="get">
                <input type="hidden" name="status" value="<?= h($status) ?>">
                <div class="mod-search">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="Tìm tiêu đề, SV, MSSV..." value="<?= h($keyword) ?>">
                </div>
            </form>
        </div>
    </div>

    <!-- Stats -->
    <div class="mod-stats mod-stagger" style="margin-bottom: 20px;">
        <div class="mod-stat accent-blue">
            <div class="mod-stat-icon" style="background:var(--gradient-primary);"><i class="fas fa-inbox"></i></div>
            <div class="mod-stat-info">
                <span class="mod-stat-number"><?= (int)($stats['total'] ?? 0) ?></span>
                <span class="mod-stat-label">Tổng phản ánh</span>
            </div>
        </div>
        <div class="mod-stat accent-pink">
            <div class="mod-stat-icon" style="background:var(--gradient-warning);"><i class="fas fa-clock"></i></div>
            <div class="mod-stat-info">
                <span class="mod-stat-number"><?= (int)($stats['pending'] ?? 0) ?></span>
                <span class="mod-stat-label">Chưa xử lý</span>
            </div>
        </div>
        <div class="mod-stat accent-orange">
            <div class="mod-stat-icon" style="background:var(--gradient-danger);"><i class="fas fa-spinner fa-spin"></i></div>
            <div class="mod-stat-info">
                <span class="mod-stat-number"><?= (int)($stats['processing'] ?? 0) ?></span>
                <span class="mod-stat-label">Đang xử lý</span>
            </div>
        </div>
        <div class="mod-stat accent-green">
            <div class="mod-stat-icon" style="background:var(--gradient-success);"><i class="fas fa-check-circle"></i></div>
            <div class="mod-stat-info">
                <span class="mod-stat-number"><?= (int)($stats['resolved'] ?? 0) ?></span>
                <span class="mod-stat-label">Đã xử lý</span>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <form method="get" class="mod-filters">
        <input type="hidden" name="search" value="<?= h($keyword) ?>">
        <div class="mod-filter-group">
            <label><i class="fas fa-filter"></i> Trạng thái</label>
            <select name="status" class="mod-select" onchange="this.form.submit()">
                <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Tất cả</option>
                <option value="Chưa xử lý" <?= $status === 'Chưa xử lý' ? 'selected' : '' ?>>Chưa xử lý (<?= (int)($stats['pending']??0) ?>)</option>
                <option value="Đang xử lý" <?= $status === 'Đang xử lý' ? 'selected' : '' ?>>Đang xử lý (<?= (int)($stats['processing']??0) ?>)</option>
                <option value="Đã xử lý" <?= $status === 'Đã xử lý' ? 'selected' : '' ?>>Đã xử lý (<?= (int)($stats['resolved']??0) ?>)</option>
            </select>
        </div>
    </form>

    <!-- Feedback Table (Compact Layout) -->
    <div class="mod-card">
        <div class="mod-table-scroll">
            <table class="mod-table">
                <thead>
                    <tr>
                        <th style="width: 60px;">ID</th>
                        <th style="width: 35%;">Tiêu đề / Nội dung</th>
                        <th style="width: 20%;">Thông tin Sinh viên</th>
                        <th style="width: 15%;">Trạng thái</th>
                        <th style="width: 15%;">Ngày gửi</th>
                        <th style="width: 100px; text-align: center;">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($fb = $result->fetch_assoc()):
                            $imgSrc = buildImageSrc($fb['ImagePath']);
                            $badgeClass = match($fb['Status']) {
                                'Đã xử lý'  => 'mod-badge-emerald',
                                'Đang xử lý' => 'mod-badge-blue',
                                default       => 'mod-badge-amber',
                            };
                            $badgeIcon = match($fb['Status']) {
                                'Đã xử lý'  => 'fa-check-circle',
                                'Đang xử lý' => 'fa-spinner',
                                default       => 'fa-clock',
                            };
                        ?>
                            <tr class="fb-row" style="<?= $fb['Status'] === 'Đã xử lý' ? 'opacity: 0.75; background: rgba(0,0,0,0.01);' : '' ?>">
                                <td><span class="mod-fw-600">#<?= $fb['FeedbackID'] ?></span></td>
                                
                                <td>
                                    <div class="mod-fw-600" style="color:var(--text); margin-bottom: 4px;">
                                        <i class="fas fa-flag" style="color:var(--primary); margin-right:4px;"></i> 
                                        <?= h($fb['Title']) ?>
                                    </div>
                                    <div class="mod-cell-muted text-truncate-2" style="font-size: 0.85rem; line-height: 1.5;">
                                        <?= h($fb['Content']) ?>
                                    </div>
                                    <?php if ($imgSrc): ?>
                                        <div style="margin-top: 6px;">
                                            <a href="javascript:void(0)" onclick="openImageModal('<?= h($imgSrc) ?>')" class="mod-badge mod-badge-gray" style="text-transform:none; cursor:pointer;">
                                                <i class="fas fa-image"></i> Đính kèm
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                
                                <td>
                                    <div class="mod-cell-name"><?= h($fb['FullName']) ?></div>
                                    <div class="mod-cell-sub"><?= h($fb['StudentCode']) ?> &bull; <?= h($fb['FacultyName'] ?: 'Chưa gán Khoa') ?></div>
                                </td>
                                
                                <td>
                                    <span class="mod-badge <?= $badgeClass ?>">
                                        <i class="fas <?= $badgeIcon ?>"></i> <?= h($fb['Status']) ?>
                                    </span>
                                </td>
                                
                                <td class="mod-cell-muted">
                                    <?= date('d/m/Y', strtotime($fb['CreatedAt'])) ?><br>
                                    <small><?= date('H:i', strtotime($fb['CreatedAt'])) ?></small>
                                </td>
                                
                                <td style="text-align: center;">
                                    <div style="display:flex; gap:6px; justify-content:center;">
                                        <a href="feedback_resolve.php?id=<?= (int)$fb['FeedbackID'] ?>" 
                                           class="mod-btn <?= $fb['Status'] !== 'Đã xử lý' ? 'mod-btn-primary' : 'mod-btn-outline' ?> mod-btn-sm" 
                                           style="padding: 0 10px;"
                                           title="<?= $fb['Status'] !== 'Đã xử lý' ? 'Xử lý' : 'Xem chi tiết' ?>">
                                            <i class="fas <?= $fb['Status'] !== 'Đã xử lý' ? 'fa-pen' : 'fa-eye' ?>"></i>
                                        </a>
                                        <a href="#" data-id="<?= (int)$fb['FeedbackID'] ?>" 
                                           class="mod-btn mod-btn-outline mod-btn-sm" 
                                           style="padding: 0 10px; color:var(--danger); border-color:var(--danger);" 
                                           onclick="return confirmDelete(event)" title="Xoá">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6">
                                <div class="mod-empty" style="padding: 40px;">
                                    <i class="fas fa-inbox"></i>
                                    <p>Không tìm thấy phản ánh nào.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Image Modal -->
<div id="imgModal" class="img-modal" onclick="closeImageModal()">
    <span class="close-btn" onclick="closeImageModal()">&times;</span>
    <img id="modalImg">
</div>

<script>
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

function openImageModal(src) {
    const modal = document.getElementById('imgModal');
    const img = document.getElementById('modalImg');
    if (!modal || !img) return;
    img.src = src;
    modal.style.display = 'flex';
}
function closeImageModal() {
    const modal = document.getElementById('imgModal');
    if (modal) modal.style.display = 'none';
}

function confirmDelete(e) {
    e.preventDefault();
    const btn = e.currentTarget;
    const row = btn.closest('.fb-row');
    const id = btn.getAttribute('data-id');

    Swal.fire({
        title: 'Xác nhận xóa?',
        text: 'Dữ liệu phản ánh này sẽ bị xóa khỏi hệ thống!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: '<i class="fas fa-trash"></i> Xóa ngay',
        cancelButtonText: 'Hủy'
    }).then((result) => {
        if (!result.isConfirmed) return;
        
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        btn.style.pointerEvents = 'none';

        fetch('feedback_delete_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: new URLSearchParams({ id, _csrf: CSRF_TOKEN })
        })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                Swal.fire({ icon: 'success', title: 'Thành công', text: data.message, timer: 1500, showConfirmButton: false });
                if (row) {
                    row.style.transition = 'all 0.3s ease';
                    row.style.opacity = '0';
                    setTimeout(() => { row.remove(); }, 300);
                }
            } else {
                btn.innerHTML = originalHtml;
                btn.style.pointerEvents = 'auto';
                Swal.fire('Thất bại', data.message || 'Không thể xóa phản ánh.', 'error');
            }
        })
        .catch(() => {
            btn.innerHTML = originalHtml;
            btn.style.pointerEvents = 'auto';
            Swal.fire('Lỗi mạng', 'Không thể kết nối đến máy chủ.', 'error');
        });
    });
    return false;
}

document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeImageModal(); });

<?php if (isset($_SESSION['message'])): ?>
Swal.fire({
    icon: '<?= h($_SESSION['message_type'] ?? 'success') ?>',
    title: <?= json_encode($_SESSION['message']) ?>,
    confirmButtonColor: '#3b82f6',
    timer: 3000
});
<?php unset($_SESSION['message'], $_SESSION['message_type']); endif; ?>
</script>
</body>
</html>
