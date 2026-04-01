<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../../db_connect.php';
require_once '../../../includes/auth_check.php';
requireRole(['Student']);

$conn->set_charset('utf8mb4');

// Filters
$type = $_GET['type'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build Query
$sql = "SELECT p.*, s.FullName, s.StudentCode, s.Avatar 
        FROM CommunityPosts p 
        JOIN Students s ON p.StudentID = s.StudentID 
        WHERE 1=1";
$params = [];
$types = "";

if ($type !== 'all') {
    $sql .= " AND p.PostType = ?";
    $params[] = $type;
    $types .= "s";
}
if ($search !== '') {
    $sql .= " AND (p.Title LIKE ? OR p.Content LIKE ?)";
    $lk = "%$search%";
    $params[] = $lk;
    $params[] = $lk;
    $types .= "ss";
}

$sql .= " ORDER BY p.CreatedAt DESC";

$stmt = $conn->prepare($sql);
if ($types) {
    if (count($params) === 1) $stmt->bind_param($types, $params[0]);
    if (count($params) === 2) $stmt->bind_param($types, $params[0], $params[1]);
    if (count($params) === 3) $stmt->bind_param($types, $params[0], $params[1], $params[2]);
}
$stmt->execute();
$result = $stmt->get_result();

$pageTitle = "Chợ KTX & Bảng tin";
require_once '../../../includes/header.php';
?>

<div class="mod-container">
    <div class="mod-header">
        <div class="mod-header-left">
            <h2><i class="fas fa-bullhorn"></i> Bảng tin Cộng đồng KTX</h2>
            <p style="color:var(--text-secondary); margin-top:4px;">Tìm đồ, mua bán, pass đồ, tìm bạn ở ghép</p>
        </div>
        <div class="mod-header-right">
            <a href="community_create.php" class="mod-btn mod-btn-primary">
                <i class="fas fa-plus"></i> Đăng tin mới
            </a>
            <form method="GET" style="display:flex; gap:0;">
                <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">
                <div class="mod-search">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="Tìm kiếm tin..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </form>
        </div>
    </div>

    <!-- Filters -->
    <div class="mod-filters" style="margin-bottom:24px;">
        <div style="display:flex; gap:12px; overflow-x:auto; padding-bottom:8px;">
            <a href="?type=all&search=<?= urlencode($search) ?>" class="mod-btn <?= $type === 'all' ? 'mod-btn-primary' : 'mod-btn-outline' ?>" style="border-radius:99px;">
                Tất cả
            </a>
            <a href="?type=Tìm đồ&search=<?= urlencode($search) ?>" class="mod-btn <?= $type === 'Tìm đồ' ? 'mod-btn-primary' : 'mod-btn-outline' ?>" style="border-radius:99px;">
                <i class="fas fa-search-location"></i> Tìm đồ / Thất lạc
            </a>
            <a href="?type=Thanh lý&search=<?= urlencode($search) ?>" class="mod-btn <?= $type === 'Thanh lý' ? 'mod-btn-primary' : 'mod-btn-outline' ?>" style="border-radius:99px;">
                <i class="fas fa-store"></i> Chợ / Thanh lý
            </a>
            <a href="?type=Ghép phòng&search=<?= urlencode($search) ?>" class="mod-btn <?= $type === 'Ghép phòng' ? 'mod-btn-primary' : 'mod-btn-outline' ?>" style="border-radius:99px;">
                <i class="fas fa-user-friends"></i> Tìm người ghép phòng
            </a>
        </div>
    </div>

    <!-- Grid -->
    <?php if ($result->num_rows > 0): ?>
        <div class="mod-grid" style="grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));">
            <?php while ($post = $result->fetch_assoc()): 
                $badgeType = match($post['PostType']) {
                    'Tìm đồ' => ['class' => 'mod-badge-danger', 'icon' => 'fa-search-location'],
                    'Thanh lý' => ['class' => 'mod-badge-emerald', 'icon' => 'fa-store'],
                    'Ghép phòng' => ['class' => 'mod-badge-blue', 'icon' => 'fa-user-friends'],
                    default => ['class' => 'mod-badge-gray', 'icon' => 'fa-tag']
                };
                $avatar = $post['Avatar'] ? "../../../" . ltrim($post['Avatar'], "/") : "../../../assets/img/avatars/user.png";
            ?>
                <div class="mod-card" style="display:flex; flex-direction:column; overflow:hidden;">
                    <!-- User Info & Meta -->
                    <div style="padding:16px; border-bottom:1px solid var(--stroke); display:flex; align-items:center; justify-content:space-between;">
                        <div style="display:flex; align-items:center; gap:12px;">
                            <img src="<?= htmlspecialchars($avatar) ?>" alt="Avatar" style="width:40px; height:40px; border-radius:50%; object-fit:cover;">
                            <div>
                                <div style="font-weight:700; color:var(--text); font-size:0.95rem; line-height:1.2;"><?= htmlspecialchars($post['FullName']) ?></div>
                                <div style="font-size:0.8rem; color:var(--text-secondary); margin-top:2px;">
                                    <?= date('d/m/Y H:i', strtotime($post['CreatedAt'])) ?>
                                </div>
                            </div>
                        </div>
                        <span class="mod-badge <?= $badgeType['class'] ?>" style="font-size:0.75rem;">
                            <i class="fas <?= $badgeType['icon'] ?>"></i> <?= htmlspecialchars($post['PostType']) ?>
                        </span>
                    </div>

                    <!-- Content -->
                    <div style="padding:16px; flex:1;">
                        <h3 style="font-size:1.1rem; font-weight:700; color:var(--text); margin:0 0 8px; line-height:1.4;">
                            <?= htmlspecialchars($post['Title']) ?>
                        </h3>
                        <div style="font-size:0.9rem; color:var(--text-secondary); line-height:1.6; display:-webkit-box; -webkit-line-clamp:3; -webkit-box-orient:vertical; overflow:hidden; margin-bottom:16px;">
                            <?= nl2br(htmlspecialchars($post['Content'])) ?>
                        </div>

                        <?php if ($post['ImagePath']): ?>
                            <div style="border-radius:12px; overflow:hidden; border:1px solid var(--stroke); background:#f9f9f9; height:180px;">
                                <img src="<?= "../../../uploads/" . htmlspecialchars($post['ImagePath']) ?>" alt="Post Image" style="width:100%; height:100%; object-fit:contain; object-position:center; cursor:pointer;" onclick="viewImage(this.src)">
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="mod-empty" style="padding:60px 20px;">
            <i class="fas fa-box-open" style="font-size:3rem; color:var(--stroke); margin-bottom:16px;"></i>
            <h3 style="margin-bottom:8px;">Bảng tin đang trống</h3>
            <p>Chưa có ai đăng bài viết nào vào chuyên mục này.</p>
        </div>
    <?php endif; ?>
</div>

<div id="imgModal" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,0.85); align-items:center; justify-content:center;">
    <span onclick="this.parentElement.style.display='none'" style="position:absolute; top:20px; right:30px; font-size:2rem; color:#fff; cursor:pointer;">&times;</span>
    <img id="modalImg" style="max-width:90vw; max-height:90vh; border-radius:12px;">
</div>
<script>
function viewImage(src) {
    document.getElementById('modalImg').src = src;
    document.getElementById('imgModal').style.display = 'flex';
}
</script>

<?php require_once '../../../includes/footer.php'; ?>
