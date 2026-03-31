<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../../db_connect.php';
require_once '../../../includes/auth_check.php';
requireRole(['Student']);

$conn->set_charset('utf8mb4');
$userID = (int)$_SESSION['UserID'];

$st = $conn->prepare("SELECT StudentID FROM Students WHERE UserID = ?");
$st->bind_param("i", $userID);
$st->execute();
$res = $st->get_result();
$student = $res->fetch_assoc();
$studentID = $student['StudentID'] ?? 0;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $type = $_POST['type'] ?? '';
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');

    if (empty($title) || empty($content)) {
        $_SESSION['message'] = "Vui lòng nhập đầy đủ tiêu đề và nội dung.";
        $_SESSION['message_type'] = "error";
    } else {
        $imagePath = null;
        if (!empty($_FILES['image']['name'])) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, $allowed) && $_FILES['image']['size'] < 5 * 1024 * 1024) {
                $imagePath = uniqid() . '.' . $ext;
                move_uploaded_file($_FILES['image']['tmp_name'], "../../../uploads/" . $imagePath);
            }
        }

        $sql = "INSERT INTO CommunityPosts (StudentID, PostType, Title, Content, ImagePath) VALUES (?, ?, ?, ?, ?)";
        $ins = $conn->prepare($sql);
        $ins->bind_param("issss", $studentID, $type, $title, $content, $imagePath);
        if ($ins->execute()) {
            $_SESSION['message'] = "Đăng tin thành công!";
            $_SESSION['message_type'] = "success";
            header("Location: community_list.php");
            exit;
        } else {
            $_SESSION['message'] = "Có lỗi xảy ra, vui lòng thử lại sau.";
            $_SESSION['message_type'] = "error";
        }
    }
}

$pageTitle = "Đăng tin mới";
require_once '../../../includes/header.php';
?>

<div class="mod-container" style="max-width:800px; margin:0 auto;">
    <div class="mod-card">
        <h2 style="font-size:1.4rem; font-weight:800; border-bottom:1px solid var(--stroke); padding-bottom:16px; margin-bottom:24px;">
            <i class="fas fa-edit"></i> Đăng tin mới
        </h2>

        <?php if (!empty($_SESSION['message'])): ?>
            <div class="mod-alert mod-alert-<?= $_SESSION['message_type'] ?? 'info' ?>">
                <i class="fas fa-info-circle"></i> <?= htmlspecialchars($_SESSION['message']) ?>
            </div>
            <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" style="display:flex; flex-direction:column; gap:20px;">
            <div class="mod-group">
                <label>Chuyên mục</label>
                <select name="type" class="mod-input" required>
                    <option value="Tìm đồ">Tìm đồ / Thất lạc</option>
                    <option value="Thanh lý">Chợ / Thanh lý / Sang nhượng</option>
                    <option value="Ghép phòng">Tìm người ghép phòng</option>
                    <option value="Khác">Chủ đề khác</option>
                </select>
            </div>

            <div class="mod-group">
                <label>Tiêu đề tin</label>
                <input type="text" name="title" class="mod-input" placeholder="Ví dụ: Cần pass lại tai nghe Sony..." required>
            </div>

            <div class="mod-group">
                <label>Nội dung chi tiết</label>
                <textarea name="content" class="mod-input" rows="6" placeholder="Mô tả món đồ, chi tiết liên hệ, giá cả..." required></textarea>
            </div>

            <div class="mod-group">
                <label>Hình ảnh (Tùy chọn, tối đa 5MB)</label>
                <input type="file" name="image" class="mod-input" accept="image/jpeg, image/png, image/gif" onchange="previewImg(this)">
                <div id="imgPreview" style="margin-top:10px; max-width:200px; display:none;">
                    <img style="width:100%; border-radius:8px; border:1px solid var(--stroke);">
                </div>
            </div>

            <div class="mod-actions" style="justify-content:flex-end;">
                <a href="community_list.php" class="mod-btn mod-btn-outline"><i class="fas fa-times"></i> Hủy</a>
                <button type="submit" class="mod-btn mod-btn-primary"><i class="fas fa-paper-plane"></i> Gửi tin</button>
            </div>
        </form>
    </div>
</div>

<script>
function previewImg(input) {
    const p = document.getElementById('imgPreview');
    const img = p.querySelector('img');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => { img.src = e.target.result; p.style.display = 'block'; }
        reader.readAsDataURL(input.files[0]);
    } else { p.style.display = 'none'; }
}
</script>

<?php require_once '../../../includes/footer.php'; ?>
