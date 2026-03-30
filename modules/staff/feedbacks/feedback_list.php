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

/* ========== THỐNG KÊ THEO TRẠNG THÁI ========== */
$statsSql = "
    SELECT 
        SUM(CASE WHEN Status = 'Chưa xử lý' THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN Status = 'Đang xử lý' THEN 1 ELSE 0 END) AS processing,
        SUM(CASE WHEN Status = 'Đã xử lý'   THEN 1 ELSE 0 END) AS resolved,
        COUNT(*) AS total
    FROM Feedbacks
";
$statsRes = $conn->query($statsSql);
$stats = $statsRes ? $statsRes->fetch_assoc() : [
    'pending'   => 0,
    'processing'=> 0,
    'resolved'  => 0,
    'total'     => 0
];

/* ========== TRUY VẤN DANH SÁCH PHẢN ÁNH ========== */
$sql = "
    SELECT 
        f.FeedbackID,
        f.Title,
        f.Content,
        f.ImagePath,
        f.Status,
        f.CreatedAt,
        s.FullName,
        s.StudentCode,
        s.Gender,
        s.StudentID,
        fc.FacultyName
    FROM Feedbacks f
    JOIN Students s 
        ON f.StudentID = s.StudentID
    LEFT JOIN Faculties fc
        ON fc.FacultyID = s.FacultyID
    WHERE 1=1
";

if ($status !== 'all') {
    $s = $conn->real_escape_string($status);
    $sql .= " AND f.Status = '$s'";
}

if ($keyword !== '') {
    $k = $conn->real_escape_string($keyword);
    $sql .= " AND (
        f.Title        LIKE '%$k%' OR
        f.Content      LIKE '%$k%' OR
        s.FullName     LIKE '%$k%' OR
        s.StudentCode  LIKE '%$k%' OR
        fc.FacultyName LIKE '%$k%'
    )";
}

$sql .= " ORDER BY f.CreatedAt DESC";

$result = $conn->query($sql);

/* ========== HELPERS ========== */

function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function buildImageSrc($path) {
    if (!$path) return null;
    return '../../../' . ltrim($path, '/\\');
}

function getStatusIcon($status) {
    switch ($status) {
        case 'Chưa xử lý':
            return '⏳';
        case 'Đang xử lý':
            return '🔄';
        case 'Đã xử lý':
            return '✅';
        default:
            return '📝';
    }
}

function getStatusBadgeClass($status) {
    switch ($status) {
        case 'Đã xử lý':
            return 'done';
        case 'Đang xử lý':
            return 'processing';
        case 'Chưa xử lý':
        default:
            return 'pending';
    }
}

function getCardClass($status) {
    return ($status === 'Đã xử lý') ? 'resolved' : 'pending';
}

?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?= h($csrf) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Phản ánh Sinh viên | Hệ thống Ký túc xá</title>
    <link rel="stylesheet"
          href="../../../assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/admin/admin_header.css">
    <link rel="stylesheet" href="../../../assets/css/staff/feedback/staff_feedback.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>
<div class="feedback-container">
    <!-- Header -->
    <div class="page-header">
        <h2><i class="fas fa-comments"></i> Quản lý Phản ánh Sinh viên</h2>
        <form class="search-form" method="get">
            <input type="hidden" name="status" value="<?= h($status) ?>">
            <input type="text" name="search"
                   placeholder="Tìm theo tiêu đề, nội dung, tên SV, MSSV, khoa..."
                   value="<?= h($keyword) ?>">
            <button type="submit">
                <i class="fas fa-search"></i> Tìm kiếm
            </button>
        </form>
    </div>

    <!-- Thống kê -->
    <div class="stats-container">
        <div class="stat-card pending">
            <div class="stat-number"><?= (int)($stats['pending'] ?? 0) ?></div>
            <div class="stat-label">Chưa xử lý</div>
        </div>
        <div class="stat-card processing">
            <div class="stat-number"><?= (int)($stats['processing'] ?? 0) ?></div>
            <div class="stat-label">Đang xử lý</div>
        </div>
        <div class="stat-card resolved">
            <div class="stat-number"><?= (int)($stats['resolved'] ?? 0) ?></div>
            <div class="stat-label">Đã xử lý</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" style="color: var(--dark);">
                <?= (int)($stats['total'] ?? 0) ?>
            </div>
            <div class="stat-label">Tổng số phản ánh</div>
        </div>
    </div>

    <!-- Bộ lọc -->
    <form method="get" class="filters">
        <input type="hidden" name="search" value="<?= h($keyword) ?>">
        <div class="filter-group">
            <label><i class="fas fa-filter"></i> Lọc theo trạng thái:</label>
            <select name="status" onchange="this.form.submit()">
                <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Tất cả trạng thái</option>
                <option value="Chưa xử lý" <?= $status === 'Chưa xử lý' ? 'selected' : '' ?>>⏳ Chưa xử lý</option>
                <option value="Đang xử lý" <?= $status === 'Đang xử lý' ? 'selected' : '' ?>>🔄 Đang xử lý</option>
                <option value="Đã xử lý"   <?= $status === 'Đã xử lý' ? 'selected' : '' ?>>✅ Đã xử lý</option>
            </select>
        </div>
    </form>

    <!-- Danh sách phản ánh -->
    <div class="feedback-list">
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($fb = $result->fetch_assoc()):
                $badgeClass  = getStatusBadgeClass($fb['Status']);
                $cardClass   = getCardClass($fb['Status']);
                $imgSrc      = buildImageSrc($fb['ImagePath']);
                $statusIcon  = getStatusIcon($fb['Status']);
                $facultyName = $fb['FacultyName'] ? $fb['FacultyName'] : '—';
            ?>
                <div class="feedback-card <?= h($cardClass) ?>">
                    <div class="feedback-header">
                        <h3><i class="fas fa-flag"></i> <?= h($fb['Title']) ?></h3>
                        <span class="status-badge <?= h($badgeClass) ?>">
                            <?= $statusIcon ?> <?= h($fb['Status']) ?>
                        </span>
                    </div>

                    <div class="feedback-meta">
                        <i class="fas fa-user"></i>
                        <strong><?= h($fb['FullName']) ?></strong>
                        (<?= h($fb['StudentCode']) ?> - <?= h($facultyName) ?>)
                        <br>
                        <i class="fas fa-clock"></i>
                        <?= date('d/m/Y H:i', strtotime($fb['CreatedAt'])) ?>
                    </div>

                    <p class="feedback-content">
                        <?= nl2br(h($fb['Content'])) ?>
                    </p>

                    <?php if ($imgSrc): ?>
                        <div class="feedback-image">
                            <img src="<?= h($imgSrc) ?>" alt="Ảnh phản ánh"
                                 onclick="openImageModal(this.src)">
                        </div>
                    <?php endif; ?>

                    <div class="feedback-actions">
                        <?php if ($fb['Status'] !== 'Đã xử lý'): ?>
                            <a href="feedback_resolve.php?id=<?= (int)$fb['FeedbackID'] ?>"
                               class="btn btn-resolve">
                                <i class="fas fa-check-circle"></i> Xử lý phản ánh
                            </a>
                        <?php endif; ?>

                        <a href="#"
                           data-id="<?= (int)$fb['FeedbackID'] ?>"
                           class="btn btn-delete"
                           onclick="return confirmDelete(event)">
                            <i class="fas fa-trash-alt"></i> Xóa phản ánh
                        </a>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="no-data">
                <i class="fas fa-inbox"></i>
                <h3>Không có phản ánh nào</h3>
                <p>Hiện tại không có phản ánh nào phù hợp với tiêu chí tìm kiếm của bạn.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal xem ảnh -->
<div id="imgModal" class="modal" onclick="closeImageModal()">
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
    const card = e.currentTarget.closest('.feedback-card');
    const id = e.currentTarget.getAttribute('data-id');

    Swal.fire({
        title: 'Xác nhận xóa?',
        text: 'Phản ánh này sẽ bị xóa vĩnh viễn khỏi hệ thống!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e63946',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Xóa ngay',
        cancelButtonText: 'Hủy'
    }).then((result) => {
        if (!result.isConfirmed) return;

        fetch('feedback_delete_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams({id: id, _csrf: CSRF_TOKEN})
        })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: 'Đã xóa!',
                    text: data.message,
                    timer: 1500,
                    showConfirmButton: false
                });
                if (card) {
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(-6px)';
                    setTimeout(function () {
                        card.remove();
                    }, 300);
                }
            } else {
                Swal.fire('Lỗi!', data.message || 'Không thể xóa.', 'error');
            }
        })
        .catch(function () {
            Swal.fire('Lỗi!', 'Không thể kết nối đến máy chủ.', 'error');
        });
    });

    return false;
}

// ESC đóng modal
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeImageModal();
});

// Thông báo từ session (nếu có)
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
