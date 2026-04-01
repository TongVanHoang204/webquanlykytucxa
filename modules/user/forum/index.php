<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include '../../db_connect.php';
include '../../includes/header.php';
require_once __DIR__ . '/../../includes/auth_check.php';

requireRole(['Student', 'Manager', 'Admin']);

$userId = $_SESSION['UserID'] ?? 0;
$studentId = 0;
$studentName = '';
$isAdmin = false;
$role = $_SESSION['Role'] ?? '';
if ($role === 'Admin' || $role === 'Manager') {
    $isAdmin = true;
}

if ($userId > 0) {
    $stmt = $conn->prepare("SELECT StudentID, FullName FROM Students WHERE UserID = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        $studentData = $res->fetch_assoc();
        $studentId = (int)$studentData['StudentID'];
        $studentName = $studentData['FullName'];
    }
    $stmt->close();
}

// -------------------- LẤY DANH SÁCH BÀI ĐĂNG --------------------
$sqlPosts = "
    SELECT p.PostID, p.StudentID, p.Content, p.ImagePath, p.CreatedAt,
           s.FullName,
           (SELECT COUNT(*) FROM postlikes l WHERE l.PostID = p.PostID) as total_likes,
           (SELECT COUNT(*) FROM postcomments c WHERE c.PostID = p.PostID) as total_comments,
           (SELECT COUNT(*) FROM postlikes l WHERE l.PostID = p.PostID AND l.StudentID = ?) as is_liked
    FROM posts p
    JOIN students s ON p.StudentID = s.StudentID
    ORDER BY p.CreatedAt DESC
    LIMIT 100
";

$stmtPosts = $conn->prepare($sqlPosts);
$stmtPosts->bind_param("i", $studentId);
$stmtPosts->execute();
$postsRes = $stmtPosts->get_result();

$posts = [];
$postIds = [];
while ($row = $postsRes->fetch_assoc()) {
    $posts[] = $row;
    $postIds[] = (int)$row['PostID'];
}
$stmtPosts->close();

// -------------------- LẤY DANH SÁCH COMMENT --------------------
// Tối ưu N+1 query: Gom comment các post đang hiển thị
$commentsByPost = [];
if (!empty($postIds)) {
    $implodedIds = implode(',', $postIds);
    $cQuery = "
        SELECT c.CommentID, c.PostID, c.StudentID, c.Content, c.CreatedAt, s.FullName
        FROM postcomments c
        JOIN students s ON c.StudentID = s.StudentID
        WHERE c.PostID IN ($implodedIds)
        ORDER BY c.CreatedAt ASC
    ";
    $cRes = $conn->query($cQuery);
    if ($cRes && $cRes->num_rows > 0) {
        while ($c = $cRes->fetch_assoc()) {
            $commentsByPost[$c['PostID']][] = $c;
        }
    }
}
?>

<link rel="stylesheet" href="<?= $base ?>assets/css/forum.css">

<div class="forum-wrapper">
    <!-- KHU VỰC TẠO BÀI ĐĂNG (Chỉ sinh viên) -->
    <?php if ($studentId > 0): ?>
    <div class="create-post-card">
        <div class="create-post-header">
            <div class="avatar" style="display:flex;align-items:center;justify-content:center;color:#fff;background:var(--f-primary);"><i class="fas fa-user"></i></div>
            <div class="create-post-input" onclick="openCreateModal()">
                Bạn đang có món đồ gì cần bán hay thanh lý không, <?= explode(' ', $studentName)[count(explode(' ', $studentName))-1] ?>?
            </div>
        </div>
    </div>
    <?php elseif ($isAdmin): ?>
    <div class="create-post-card" style="text-align:center; color:var(--f-muted);">
        <i class="fas fa-shield-alt"></i> Bạn đang duyệt Community với quyền Quản trị.
    </div>
    <?php endif; ?>

    <!-- DANH SÁCH BÀI ĐĂNG -->
    <div id="postsContainer">
        <?php if (!empty($posts)): ?>
            <?php foreach ($posts as $p): ?>
                <div class="post-card" id="post-<?= $p['PostID'] ?>">
                    <div class="post-header">
                        <div class="avatar" style="display:flex;align-items:center;justify-content:center;color:#fff;background:#7209b7;"><i class="fas fa-user-graduate"></i></div>
                        <div class="post-meta">
                            <a href="#" class="post-author"><?= htmlspecialchars($p['FullName']) ?></a>
                            <span class="post-time"><?= date('H:i d/m/Y', strtotime($p['CreatedAt'])) ?></span>
                        </div>
                        <?php if ($studentId == $p['StudentID'] || $isAdmin): ?>
                        <div class="post-options" onclick="confirmDeletePost(<?= $p['PostID'] ?>)">
                            <i class="fas fa-times"></i>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="post-content">
                        <?= nl2br(htmlspecialchars($p['Content'])) ?>
                    </div>

                    <?php if (!empty($p['ImagePath'])): ?>
                        <img class="post-image" src="<?= $base . htmlspecialchars($p['ImagePath']) ?>" alt="Hình ảnh đính kèm" onclick="viewImage(this.src)">
                    <?php endif; ?>

                    <!-- Stats -->
                    <div class="post-stats">
                        <span class="stats-likes">
                            <i class="fas fa-thumbs-up text-primary" style="color:var(--f-primary);"></i> <span id="like-count-<?= $p['PostID'] ?>"><?= $p['total_likes'] ?></span>
                        </span>
                        <span class="stats-comments">
                            <span id="comment-count-<?= $p['PostID'] ?>"><?= $p['total_comments'] ?></span> bình luận
                        </span>
                    </div>

                    <!-- Actions -->
                    <?php if ($studentId > 0): ?>
                    <div class="post-actions">
                        <button class="action-btn <?= $p['is_liked'] ? 'liked' : '' ?>" id="like-btn-<?= $p['PostID'] ?>" onclick="toggleLike(<?= $p['PostID'] ?>)">
                            <i class="<?= $p['is_liked'] ? 'fas' : 'far' ?> fa-thumbs-up"></i> Thích
                        </button>
                        <button class="action-btn" onclick="focusComment(<?= $p['PostID'] ?>)">
                            <i class="far fa-comment-alt"></i> Bình luận
                        </button>
                    </div>
                    <?php endif; ?>

                    <!-- Comments -->
                    <div class="post-comments">
                        <div class="comment-list" id="comment-list-<?= $p['PostID'] ?>">
                            <?php if (isset($commentsByPost[$p['PostID']])): ?>
                                <?php foreach ($commentsByPost[$p['PostID']] as $cmt): ?>
                                    <div class="comment-item">
                                        <div class="avatar" style="width:32px;height:32px;font-size:12px;display:flex;align-items:center;justify-content:center;background:#6c757d;color:#fff;"><i class="fas fa-user"></i></div>
                                        <div>
                                            <div class="comment-body">
                                                <span class="comment-author"><?= htmlspecialchars($cmt['FullName']) ?></span>
                                                <span class="comment-text"><?= nl2br(htmlspecialchars($cmt['Content'])) ?></span>
                                            </div>
                                            <span class="comment-time"><?= date('H:i d/m', strtotime($cmt['CreatedAt'])) ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <?php if ($studentId > 0): ?>
                        <div class="comment-input-area" style="margin-top:10px;">
                            <div class="avatar" style="width:32px;height:32px;display:flex;align-items:center;justify-content:center;background:var(--f-primary);color:#fff;"><i class="fas fa-user"></i></div>
                            <form class="comment-input-wrapper" onsubmit="submitComment(event, <?= $p['PostID'] ?>)">
                                <input type="text" id="comment-input-<?= $p['PostID'] ?>" placeholder="Viết bình luận..." autocomplete="off" required>
                                <button type="submit"><i class="fas fa-paper-plane"></i></button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="text-align:center; padding: 40px; color:var(--f-muted);">
                <i class="fas fa-newspaper fa-4x" style="margin-bottom:15px;opacity:0.5;"></i>
                <h3>Chưa có bài đăng nào</h3>
                <p>Hãy là người đầu tiên đăng bài nhé!</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Create Post -->
<?php if ($studentId > 0): ?>
<div id="createPostModal" class="modal" style="display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.6);">
    <div class="modal-content-forum">
        <div class="modal-header-forum">
            Tạo bài viết
            <div class="modal-close-forum" onclick="closeCreateModal()"><i class="fas fa-times"></i></div>
        </div>
        <form id="createPostForm" action="create_post.php" method="POST" enctype="multipart/form-data">
            <div class="modal-body-forum">
                <div style="display:flex; gap:10px; align-items:center;">
                    <div class="avatar" style="display:flex;align-items:center;justify-content:center;color:#fff;background:var(--f-primary);"><i class="fas fa-user"></i></div>
                    <strong style="color:var(--f-text);"><?= htmlspecialchars($studentName) ?></strong>
                </div>
                <textarea name="content" required placeholder="Bạn đang nghĩ gì về KTX hay có món đồ nào muốn chia sẻ?" id="postTextarea"></textarea>
                
                <div class="upload-preview" id="uploadPreview">
                    <img id="previewImage" src="">
                </div>
            </div>
            <div class="modal-actions-forum">
                <label for="postImage" class="image-upload-btn">
                    <i class="fas fa-images"></i> <span>Ảnh</span>
                </label>
                <input type="file" id="postImage" name="image" accept="image/*" style="display:none;" onchange="previewUpload(event)">
                
                <button type="submit" class="btn-post">Đăng</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// ---------- CREATE POST MODAL ----------
function openCreateModal() {
    document.getElementById('createPostModal').style.display = 'block';
    setTimeout(() => document.getElementById('postTextarea').focus(), 100);
}
function closeCreateModal() {
    document.getElementById('createPostModal').style.display = 'none';
}
function previewUpload(event) {
    if(event.target.files.length > 0){
        let src = URL.createObjectURL(event.target.files[0]);
        let previewContainer = document.getElementById('uploadPreview');
        let previewImg = document.getElementById('previewImage');
        previewImg.src = src;
        previewContainer.style.display = 'block';
    }
}

// ---------- IMAGE VIEWER ----------
function viewImage(src) {
    Swal.fire({ imageUrl: src, imageAlt: 'Hình ảnh', width: 600, showConfirmButton: false, customClass: { popup: 'swal2-dark' } });
}

// ---------- LIKE LOGIC ----------
function toggleLike(postId) {
    fetch('react_post.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'post_id=' + postId
    })
    .then(r => r.json())
    .then(data => {
        if(data.status === 'success') {
            document.getElementById('like-count-' + postId).innerText = data.likes;
            let btn = document.getElementById('like-btn-' + postId);
            let icon = btn.querySelector('i');
            if(data.action === 'liked') {
                btn.classList.add('liked');
                icon.classList.remove('far'); icon.classList.add('fas');
            } else {
                btn.classList.remove('liked');
                icon.classList.remove('fas'); icon.classList.add('far');
            }
        }
    });
}

// ---------- COMMENT LOGIC ----------
function focusComment(postId) {
    let input = document.getElementById('comment-input-' + postId);
    if(input) input.focus();
}

function submitComment(e, postId) {
    e.preventDefault();
    let input = document.getElementById('comment-input-' + postId);
    let content = input.value.trim();
    if (content === '') return;

    fetch('add_comment.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'post_id=' + postId + '&content=' + encodeURIComponent(content)
    })
    .then(r => r.json())
    .then(data => {
        if(data.status === 'success') {
            input.value = '';
            // Tăng số đếm comment
            let countSpan = document.getElementById('comment-count-' + postId);
            countSpan.innerText = parseInt(countSpan.innerText) + 1;

            // Thêm HTML Comment
            let list = document.getElementById('comment-list-' + postId);
            let html = `
                <div class="comment-item">
                    <div class="avatar" style="width:32px;height:32px;font-size:12px;display:flex;align-items:center;justify-content:center;background:#6c757d;color:#fff;"><i class="fas fa-user"></i></div>
                    <div>
                        <div class="comment-body">
                            <span class="comment-author">${data.author_name}</span>
                            <span class="comment-text">${data.content}</span>
                        </div>
                        <span class="comment-time">Vừa xong</span>
                    </div>
                </div>
            `;
            list.insertAdjacentHTML('beforeend', html);
        } else {
            Swal.fire('Lỗi', data.message, 'error');
        }
    });
}

// ---------- DELETE POST LOGIC ----------
function confirmDeletePost(postId) {
    Swal.fire({
        title: 'Xóa bài viết?',
        text: 'Hành động này không thể hoàn tác!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Xóa',
        cancelButtonText: 'Hủy'
    }).then((result) => {
        if(result.isConfirmed) {
            window.location.href = `delete_post.php?post_id=${postId}`;
        }
    });
}

window.onclick = function(event) {
    let modal = document.getElementById('createPostModal');
    if (event.target === modal) modal.style.display = "none";
}
</script>

<?php include '../../includes/footer.php'; ?>
