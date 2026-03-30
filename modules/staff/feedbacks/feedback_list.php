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
        .fb-card { background:var(--surface); border:1px solid var(--stroke); border-radius:16px; padding:24px; margin-bottom:16px; transition:all 0.3s ease; }
        .fb-card:hover { transform:translateY(-2px); box-shadow:var(--shadow-lg); }
        .fb-card.resolved { opacity:0.75; }
        .fb-head { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; margin-bottom:12px; }
        .fb-head h3 { font-size:1rem; font-weight:700; color:var(--text); margin:0; display:flex; align-items:center; gap:8px; }
        .fb-meta { font-size:0.82rem; color:var(--text-secondary); margin-bottom:12px; display:flex; flex-wrap:wrap; gap:12px; align-items:center; }
        .fb-content { font-size:0.88rem; color:var(--text); line-height:1.6; margin-bottom:16px; background:var(--bg); padding:14px; border-radius:10px; border:1px solid var(--stroke); }
        .fb-img { max-width:200px; border-radius:10px; cursor:pointer; transition:transform 0.2s; border:1px solid var(--stroke); }
        .fb-img:hover { transform:scale(1.05); }
        .fb-actions { display:flex; gap:8px; margin-top:16px; padding-top:16px; border-top:1px solid var(--stroke); }
        .img-modal { display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,0.85); justify-content:center; align-items:center; }
        .img-modal img { max-width:90vw; max-height:90vh; border-radius:12px; }
        .img-modal .close-btn { position:absolute; top:20px; right:30px; font-size:2rem; color:#fff; cursor:pointer; }
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
    <div class="mod-stats mod-stagger">
        <div class="mod-stat accent-pink">
            <div class="mod-stat-icon" style="background:var(--gradient-warning);"><i class="fas fa-clock"></i></div>
            <div class="mod-stat-info">
                <span class="mod-stat-number"><?= (int)($stats['pending'] ?? 0) ?></span>
                <span class="mod-stat-label">Chưa xử lý</span>
            </div>
        </div>
        <div class="mod-stat accent-blue">
            <div class="mod-stat-icon" style="background:var(--gradient-primary);"><i class="fas fa-spinner"></i></div>
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
        <div class="mod-stat accent-purple">
            <div class="mod-stat-icon" style="background:var(--gradient-info);"><i class="fas fa-list"></i></div>
            <div class="mod-stat-info">
                <span class="mod-stat-number"><?= (int)($stats['total'] ?? 0) ?></span>
                <span class="mod-stat-label">Tổng phản ánh</span>
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
                <option value="Chưa xử lý" <?= $status === 'Chưa xử lý' ? 'selected' : '' ?>>Chưa xử lý</option>
                <option value="Đang xử lý" <?= $status === 'Đang xử lý' ? 'selected' : '' ?>>Đang xử lý</option>
                <option value="Đã xử lý" <?= $status === 'Đã xử lý' ? 'selected' : '' ?>>Đã xử lý</option>
            </select>
        </div>
    </form>

    <!-- Feedback Cards -->
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
            <div class="fb-card <?= $fb['Status'] === 'Đã xử lý' ? 'resolved' : '' ?>">
                <div class="fb-head">
                    <h3><i class="fas fa-flag"></i> <?= h($fb['Title']) ?></h3>
                    <span class="mod-badge <?= $badgeClass ?>">
                        <i class="fas <?= $badgeIcon ?>"></i> <?= h($fb['Status']) ?>
                    </span>
                </div>
                <div class="fb-meta">
                    <span><i class="fas fa-user"></i> <strong><?= h($fb['FullName']) ?></strong> (<?= h($fb['StudentCode']) ?> - <?= h($fb['FacultyName'] ?: '—') ?>)</span>
                    <span><i class="fas fa-clock"></i> <?= date('d/m/Y H:i', strtotime($fb['CreatedAt'])) ?></span>
                </div>
                <div class="fb-content"><?= nl2br(h($fb['Content'])) ?></div>
                <?php if ($imgSrc): ?>
                    <img class="fb-img" src="<?= h($imgSrc) ?>" alt="Ảnh phản ánh" onclick="openImageModal(this.src)">
                <?php endif; ?>
                <div class="fb-actions">
                    <?php if ($fb['Status'] !== 'Đã xử lý'): ?>
                        <a href="feedback_resolve.php?id=<?= (int)$fb['FeedbackID'] ?>" class="mod-btn mod-btn-primary mod-btn-sm">
                            <i class="fas fa-check-circle"></i> Xử lý
                        </a>
                    <?php endif; ?>
                    <a href="#" data-id="<?= (int)$fb['FeedbackID'] ?>" class="mod-btn mod-btn-outline mod-btn-sm" style="color:var(--danger);border-color:var(--danger);"
                       onclick="return confirmDelete(event)">
                        <i class="fas fa-trash-alt"></i> Xóa
                    </a>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="mod-empty" style="padding:60px;">
            <i class="fas fa-inbox"></i>
            <p>Không có phản ánh nào phù hợp.</p>
        </div>
    <?php endif; ?>
</div>

<!-- Image Modal -->
<div id="imgModal" class="img-modal" onclick="closeImageModal()">
    <span class="close-btn" onclick="closeImageModal()">&times;</span>
    <img id="modalImg">
</div>

<script>
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

function openImageModal(src) {
    document.getElementById('modalImg').src = src;
    document.getElementById('imgModal').style.display = 'flex';
}
function closeImageModal() {
    document.getElementById('imgModal').style.display = 'none';
}

function confirmDelete(e) {
    e.preventDefault();
    const card = e.currentTarget.closest('.fb-card');
    const id = e.currentTarget.getAttribute('data-id');

    Swal.fire({
        title: 'Xác nhận xóa?',
        text: 'Phản ánh này sẽ bị xóa vĩnh viễn!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#f72585',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Xóa ngay',
        cancelButtonText: 'Hủy'
    }).then((result) => {
        if (!result.isConfirmed) return;
        fetch('feedback_delete_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: new URLSearchParams({ id, _csrf: CSRF_TOKEN })
        })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                Swal.fire({ icon: 'success', title: 'Đã xóa!', text: data.message, timer: 1500, showConfirmButton: false });
                if (card) {
                    card.style.transition = 'all 0.3s ease';
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(-6px)';
                    setTimeout(() => card.remove(), 300);
                }
            } else {
                Swal.fire('Lỗi!', data.message || 'Không thể xóa.', 'error');
            }
        })
        .catch(() => Swal.fire('Lỗi!', 'Không thể kết nối đến máy chủ.', 'error'));
    });
    return false;
}

document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeImageModal(); });

<?php if (isset($_SESSION['message'])): ?>
Swal.fire({
    icon: '<?= h($_SESSION['message_type'] ?? 'success') ?>',
    title: <?= json_encode($_SESSION['message']) ?>,
    confirmButtonColor: '#4361ee',
    timer: 3000
});
<?php unset($_SESSION['message'], $_SESSION['message_type']); endif; ?>
</script>
</body>
</html>
