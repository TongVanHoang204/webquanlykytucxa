<?php
if (session_status() === PHP_SESSION_NONE) session_start();

include '../../db_connect.php';
include '../../includes/header.php';
require_once __DIR__ . '/../../includes/auth_check.php';

requireRole(['Student', 'Manager', 'Admin']);

$userId = $_SESSION['UserID'] ?? 0;
$student = null;
$studentId = 0;
$studentName = 'Sinh viên';

if ($userId > 0) {
    $stmt = $conn->prepare("SELECT StudentID, FullName FROM Students WHERE UserID = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $student = $result->fetch_assoc();
        $studentId = (int)$student['StudentID'];
        $studentName = $student['FullName'];
    }
    $stmt->close();
}

// XỬ LÝ GỬI PHẢN ÁNH
$success = false;
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $studentId > 0) {
    $title     = trim($_POST['title'] ?? '');
    $content   = trim($_POST['content'] ?? '');
    $imagePath = null;
    $status    = 'Chưa xử lý';

    if ($title === '' || $content === '') {
        $errorMsg = "Vui lòng nhập đầy đủ tiêu đề và nội dung.";
    }

    if (empty($errorMsg) && !empty($_FILES['image']['name'])) {
        $uploadDir = '../../assets/img/feedbacks/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        $filename   = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($_FILES['image']['name']));
        $targetFile = $uploadDir . $filename;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
            $imagePath = 'assets/img/feedbacks/' . $filename;
        } else {
            $errorMsg = "Không thể lưu ảnh. Kiểm tra quyền ghi thư mục.";
        }
    }

    if (empty($errorMsg)) {
        $sql = "INSERT INTO Feedbacks (StudentID, Title, Content, ImagePath, Status, CreatedAt) VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            $errorMsg = "Lỗi hệ thống: " . $conn->error;
        } else {
            $stmt->bind_param("issss", $studentId, $title, $content, $imagePath, $status);
            if ($stmt->execute()) {
                $success = true;
                $feedbackID = $conn->insert_id;
                $preview = mb_substr($content, 0, 120, 'UTF-8');
                if (mb_strlen($content, 'UTF-8') > 120) $preview .= '...';
                $notiTitle = 'Phản ánh mới từ ' . $studentName;
                $notiStmt = $conn->prepare("INSERT INTO Notifications (StudentID, Title, Message, CreatedAt, IsRead) VALUES (?, ?, ?, NOW(), 0)");
                if ($notiStmt) {
                    $notiStmt->bind_param("iss", $studentId, $notiTitle, $preview);
                    $notiStmt->execute();
                    $notiStmt->close();
                }
                addLog($conn, $_SESSION['UserID'] ?? null, 'Send feedback', 'Feedbacks', "Sinh viên gửi phản ánh ID={$feedbackID}", 'activity');
            } else {
                $errorMsg = "Lỗi thực thi: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

$feedbacks = false;
if ($studentId > 0) {
    $feedbacks = $conn->query("
        SELECT FeedbackID, StudentID, Title, Content, Reply, ImagePath, Status, CreatedAt, UpdatedAt
        FROM Feedbacks WHERE StudentID = $studentId ORDER BY CreatedAt DESC
    ");
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Phản ánh | Ký túc xá</title>
    <link rel="stylesheet" href="<?= $base ?>assets/css/global.css">
    <link rel="stylesheet" href="<?= $base ?>assets/css/modules_shared.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .fb-page { max-width:900px; margin:0 auto; padding:30px 20px; }
        .fb-welcome { background:var(--gradient-primary); color:#fff; border-radius:20px; padding:28px 32px; margin-bottom:28px; position:relative; overflow:hidden; }
        .fb-welcome::after { content:''; position:absolute; top:-40%; right:-15%; width:250px; height:250px; background:rgba(255,255,255,0.06); border-radius:50%; }
        .fb-welcome h2 { font-size:1.3rem; font-weight:800; margin:0 0 6px; }
        .fb-welcome p { font-size:0.88rem; opacity:0.85; margin:0; }

        .fb-form-box { background:var(--surface); border:1px solid var(--stroke); border-radius:16px; padding:28px; margin-bottom:28px; }
        .fb-form-box h3 { font-size:1.05rem; font-weight:700; margin:0 0 20px; display:flex; align-items:center; gap:8px; color:var(--text); }
        .fb-group { margin-bottom:18px; }
        .fb-group label { display:block; font-size:0.85rem; font-weight:600; color:var(--text); margin-bottom:6px; }
        .fb-group label i { color:var(--primary); margin-right:4px; }
        .fb-group input, .fb-group textarea { width:100%; padding:12px 14px; border:1px solid var(--stroke); border-radius:10px; font-size:0.88rem; background:var(--bg); color:var(--text); transition:border-color 0.2s; box-sizing:border-box; font-family:inherit; }
        .fb-group input:focus, .fb-group textarea:focus { border-color:var(--primary); outline:none; box-shadow:0 0 0 3px rgba(67,97,238,0.1); }
        .fb-group textarea { resize:vertical; min-height:120px; }

        .fb-file { display:flex; align-items:center; gap:12px; }
        .fb-file-btn { padding:10px 18px; background:var(--bg); border:1px dashed var(--stroke); border-radius:10px; cursor:pointer; font-size:0.85rem; color:var(--text-secondary); transition:all 0.2s; display:flex; align-items:center; gap:6px; }
        .fb-file-btn:hover { border-color:var(--primary); color:var(--primary); }
        .fb-file input[type="file"] { display:none; }
        .fb-file-name { font-size:0.82rem; color:var(--text-secondary); }

        .fb-submit { padding:12px 28px; background:var(--gradient-primary); color:#fff; border:none; border-radius:12px; font-size:0.92rem; font-weight:700; cursor:pointer; display:flex; align-items:center; gap:8px; transition:all 0.2s; }
        .fb-submit:hover { transform:translateY(-2px); box-shadow:0 6px 16px rgba(67,97,238,0.3); }

        .fb-history h3 { font-size:1.05rem; font-weight:700; margin:0 0 16px; display:flex; align-items:center; gap:8px; color:var(--text); }
        .fb-item { background:var(--surface); border:1px solid var(--stroke); border-radius:16px; padding:20px; margin-bottom:14px; transition:all 0.3s; }
        .fb-item:hover { box-shadow:var(--shadow-lg); }
        .fb-item-head { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; margin-bottom:10px; }
        .fb-item-head h4 { font-size:0.95rem; font-weight:700; margin:0; color:var(--text); }
        .fb-item-content { font-size:0.86rem; color:var(--text-secondary); line-height:1.6; margin-bottom:12px; }
        .fb-reply { background:var(--bg); border:1px solid var(--stroke); border-radius:12px; padding:14px; margin-bottom:12px; border-left:3px solid var(--primary); }
        .fb-reply-label { font-size:0.78rem; font-weight:700; color:var(--primary); margin-bottom:6px; display:flex; align-items:center; gap:6px; }
        .fb-reply p { font-size:0.85rem; color:var(--text); line-height:1.5; margin:0; }
        .fb-reply small { font-size:0.76rem; color:var(--text-secondary); }
        .fb-item-img { max-width:140px; border-radius:10px; cursor:pointer; transition:transform 0.2s; border:1px solid var(--stroke); }
        .fb-item-img:hover { transform:scale(1.05); }
        .fb-item-foot { font-size:0.8rem; color:var(--text-secondary); padding-top:10px; border-top:1px solid var(--stroke); display:flex; align-items:center; gap:6px; }

        .img-modal { display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,0.85); justify-content:center; align-items:center; }
        .img-modal img { max-width:90vw; max-height:90vh; border-radius:12px; }
        .img-modal .close-modal { position:absolute; top:20px; right:30px; font-size:2rem; color:#fff; cursor:pointer; }
    </style>
</head>
<body>
    <div class="fb-page">
        <!-- Welcome -->
        <div class="fb-welcome">
            <h2>💬 Phản ánh của bạn</h2>
            <p>Xin chào <strong><?= htmlspecialchars($studentName) ?></strong> — gửi phản ánh và theo dõi phản hồi từ Ban quản lý.</p>
        </div>

        <!-- Form -->
        <div class="fb-form-box">
            <h3><i class="fas fa-edit"></i> Gửi phản ánh mới</h3>
            <form method="POST" enctype="multipart/form-data" id="feedbackForm">
                <div class="fb-group">
                    <label><i class="fas fa-heading"></i> Tiêu đề</label>
                    <input type="text" name="title" required placeholder="Ví dụ: Phòng bị hỏng điện, mất nước...">
                </div>
                <div class="fb-group">
                    <label><i class="fas fa-align-left"></i> Nội dung chi tiết</label>
                    <textarea name="content" rows="5" required placeholder="Mô tả chi tiết vấn đề bạn gặp phải..."></textarea>
                </div>
                <div class="fb-group">
                    <label><i class="fas fa-image"></i> Ảnh minh họa (tùy chọn)</label>
                    <div class="fb-file">
                        <label for="image" class="fb-file-btn"><i class="fas fa-upload"></i> Chọn ảnh</label>
                        <input type="file" id="image" name="image" accept="image/*">
                        <span class="fb-file-name" id="fileName">Chưa chọn file</span>
                    </div>
                </div>
                <button type="submit" class="fb-submit"><i class="fas fa-paper-plane"></i> Gửi phản ánh</button>
            </form>
        </div>

        <!-- History -->
        <div class="fb-history">
            <h3><i class="fas fa-history"></i> Lịch sử phản ánh</h3>
            <?php if ($feedbacks && $feedbacks instanceof mysqli_result && $feedbacks->num_rows > 0): ?>
                <?php while ($fb = $feedbacks->fetch_assoc()):
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
                    <div class="fb-item">
                        <div class="fb-item-head">
                            <h4><?= htmlspecialchars($fb['Title']) ?></h4>
                            <span class="mod-badge <?= $badgeClass ?>"><i class="fas <?= $badgeIcon ?>"></i> <?= htmlspecialchars($fb['Status']) ?></span>
                        </div>
                        <div class="fb-item-content"><?= nl2br(htmlspecialchars($fb['Content'])) ?></div>

                        <?php if (!empty($fb['Reply'])): ?>
                            <div class="fb-reply">
                                <div class="fb-reply-label"><i class="fas fa-reply"></i> Phản hồi từ Ban quản lý</div>
                                <p><?= nl2br(htmlspecialchars($fb['Reply'])) ?></p>
                                <?php if (!empty($fb['UpdatedAt'])): ?>
                                    <small><i class="far fa-clock"></i> Cập nhật: <?= date('H:i d/m/Y', strtotime($fb['UpdatedAt'])) ?></small>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($fb['ImagePath'])): ?>
                            <img class="fb-item-img" src="<?= $base . htmlspecialchars($fb['ImagePath']) ?>" alt="Ảnh phản ánh" onclick="openModal(this.src)">
                        <?php endif; ?>

                        <div class="fb-item-foot">
                            <i class="far fa-clock"></i> <?= date('H:i d/m/Y', strtotime($fb['CreatedAt'])) ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="mod-empty" style="padding:40px;">
                    <i class="fas fa-inbox"></i>
                    <p>Bạn chưa gửi phản ánh nào. Hãy gửi phản ánh đầu tiên!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Image Modal -->
    <div id="imgModal" class="img-modal" onclick="closeModal()">
        <span class="close-modal" onclick="closeModal()">&times;</span>
        <img id="modalImg">
    </div>

    <script>
        document.getElementById('image').addEventListener('change', function(e) {
            document.getElementById('fileName').textContent = e.target.files[0] ? e.target.files[0].name : 'Chưa chọn file';
        });

        function openModal(src) { document.getElementById('modalImg').src = src; document.getElementById('imgModal').style.display = 'flex'; }
        function closeModal() { document.getElementById('imgModal').style.display = 'none'; }
        document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
    </script>

    <?php if ($success): ?>
        <script>
            Swal.fire({ icon: 'success', title: 'Thành công!', text: 'Gửi phản ánh thành công!', confirmButtonColor: '#4361ee' })
                .then(() => { window.location.href = 'feedbacks.php'; });
        </script>
    <?php elseif (!empty($errorMsg)): ?>
        <script>
            Swal.fire({ icon: 'error', title: 'Lỗi!', text: '<?= addslashes($errorMsg) ?>', confirmButtonColor: '#e63946' });
        </script>
    <?php endif; ?>

    <?php include '../../includes/footer.php'; ?>
</body>
</html>