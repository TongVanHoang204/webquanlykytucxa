<?php
if (session_status() === PHP_SESSION_NONE) session_start();

include '../../db_connect.php';
include '../../includes/header.php';
require_once __DIR__ . '/../../includes/auth_check.php';

requireRole(['Student', 'Manager', 'Admin']);

// -------------------- LẤY THÔNG TIN SINH VIÊN --------------------
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


// -------------------- XỬ LÝ GỬI PHẢN ÁNH --------------------
$success = false;
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $studentId > 0) {
    $title     = trim($_POST['title'] ?? '');
    $content   = trim($_POST['content'] ?? '');
    $imagePath = null;
    $status    = 'Chưa xử lý';

    if ($title === '' || $content === '') {
        $errorMsg = "⚠️ Vui lòng nhập đầy đủ tiêu đề và nội dung.";
    }

    // Upload ảnh (nếu có)
    if (empty($errorMsg) && !empty($_FILES['image']['name'])) {
        $uploadDir = '../../assets/img/feedbacks/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $filename   = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($_FILES['image']['name']));
        $targetFile = $uploadDir . $filename;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
            $imagePath = 'assets/img/feedbacks/' . $filename;
        } else {
            $errorMsg = "⚠️ Không thể lưu ảnh. Kiểm tra quyền ghi thư mục assets/img/feedbacks!";
        }
    }

    // Thêm phản ánh + thông báo
    if (empty($errorMsg)) {
        $sql = "INSERT INTO Feedbacks (StudentID, Title, Content, ImagePath, Status, CreatedAt)
                VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            $errorMsg = "❌ Lỗi prepare(): " . $conn->error;
        } else {
            $stmt->bind_param("issss", $studentId, $title, $content, $imagePath, $status);

            if ($stmt->execute()) {
                $success = true;

                // 🔹 Lấy ID phản ánh vừa tạo
                $feedbackID = $conn->insert_id; // hoặc $stmt->insert_id (tùy phiên bản PHP/MySQLi)

                // 🔔 TẠO THÔNG BÁO CHO ADMIN/MANAGER
                // Rút gọn nội dung để hiển thị trong chuông
                $preview = mb_substr($content, 0, 120, 'UTF-8');
                if (mb_strlen($content, 'UTF-8') > 120) {
                    $preview .= '...';
                }

                $notiTitle = 'Phản ánh mới từ ' . $studentName;
                $notiMsg   = $preview;

                /**
                 * Giả sử bảng Notifications có cấu trúc:
                 * NotificationID (AI), StudentID, Title, Message, CreatedAt, IsRead
                 */
                $notiSql = "INSERT INTO Notifications (StudentID, Title, Message, CreatedAt, IsRead)
                VALUES (?, ?, ?, NOW(), 0)";
                $notiStmt = $conn->prepare($notiSql);

                if ($notiStmt) {
                    $notiStmt->bind_param("iss", $studentId, $notiTitle, $notiMsg);
                    if (!$notiStmt->execute()) {
                        // Ghi log nếu lỗi, tránh làm gãy luồng của user
                        error_log('Notification execute error: ' . $notiStmt->error);
                    }
                    $notiStmt->close();
                } else {
                    // Nếu lỗi prepare, ghi log để bạn check
                    error_log('Notification prepare error: ' . $conn->error);
                }

                // 📝 Ghi log hoạt động sau khi có $feedbackID
                addLog(
                    $conn,
                    $_SESSION['UserID'] ?? null,
                    'Send feedback',
                    'Feedbacks',
                    "Sinh viên gửi phản ánh ID={$feedbackID}",
                    'activity'
                );
            } else {
                $errorMsg = "❌ Lỗi thực thi SQL: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}



// -------------------- LẤY DANH SÁCH PHẢN ÁNH --------------------
$feedbacks = false;
if ($studentId > 0) {
    $feedbacks = $conn->query("
        SELECT FeedbackID, StudentID, Title, Content, Reply, ImagePath, Status, CreatedAt, UpdatedAt
        FROM Feedbacks
        WHERE StudentID = $studentId
        ORDER BY CreatedAt DESC
    ");
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Phản ánh - Ký túc xá</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/feedbacks.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>
    <div class="container">
        <div class="page-header">
            <h2>💬 Phản ánh của bạn</h2>
            <p>Xin chào <strong><?= htmlspecialchars($studentName) ?></strong> — tại đây bạn có thể gửi phản ánh và theo dõi phản hồi từ Ban quản lý.</p>
        </div>

        <!-- Form gửi phản ánh -->
        <div class="feedback-form">
            <h3><i class="fas fa-edit"></i> Gửi phản ánh mới</h3>
            <form method="POST" enctype="multipart/form-data" class="form-feedback" id="feedbackForm">
                <div class="form-group">
                    <label for="title"><i class="fas fa-heading"></i> Tiêu đề</label>
                    <input type="text" id="title" name="title" required
                        placeholder="Ví dụ: Phòng bị hỏng điện, mất nước...">
                </div>

                <div class="form-group">
                    <label for="content"><i class="fas fa-align-left"></i> Nội dung chi tiết</label>
                    <textarea id="content" name="content" rows="5" required
                        placeholder="Mô tả chi tiết vấn đề bạn gặp phải..."></textarea>
                </div>

                <div class="form-group">
                    <label for="image"><i class="fas fa-image"></i> Ảnh minh họa (tùy chọn)</label>
                    <div class="file-upload">
                        <input type="file" id="image" name="image" accept="image/*">
                        <label for="image" class="file-upload-label">
                            <i class="fas fa-upload"></i> Chọn ảnh từ máy tính
                        </label>
                    </div>
                    <div class="file-name" id="fileName">Chưa có file nào được chọn</div>
                </div>

                <button type="submit" class="btn btn-rgb">
                    <i class="fas fa-paper-plane"></i> Gửi phản ánh
                </button>
            </form>
        </div>

        <hr>

        <!-- Lịch sử phản ánh -->
        <div class="feedback-history">
            <h3><i class="fas fa-history"></i> Lịch sử phản ánh</h3>

            <?php if ($feedbacks && $feedbacks instanceof mysqli_result && $feedbacks->num_rows > 0): ?>
                <div class="feedback-list">
                    <?php while ($fb = $feedbacks->fetch_assoc()):
                        $statusClass = strtolower(str_replace(' ', '-', $fb['Status']));
                    ?>
                        <div class="feedback-card">
                            <div class="feedback-header">
                                <h4><?= htmlspecialchars($fb['Title']) ?></h4>
                                <span class="status <?= $statusClass ?>">
                                    <i class="fas <?= $fb['Status'] === 'Đã xử lý'
                                                        ? 'fa-check-circle'
                                                        : ($fb['Status'] === 'Đang xử lý' ? 'fa-spinner' : 'fa-clock') ?>"></i>
                                    <?= htmlspecialchars($fb['Status']) ?>
                                </span>
                            </div>

                            <!-- Nội dung phản ánh -->
                            <p><?= nl2br(htmlspecialchars($fb['Content'])) ?></p>

                            <!-- ✅ PHẢN HỒI TỪ QUẢN LÝ -->
                            <?php if (!empty($fb['Reply'])): ?>
                                <div class="feedback-reply">
                                    <div class="reply-label">
                                        <i class="fas fa-reply"></i> Phản hồi từ Ban quản lý
                                    </div>
                                    <p><?= nl2br(htmlspecialchars($fb['Reply'])) ?></p>
                                    <?php if (!empty($fb['UpdatedAt'])): ?>
                                        <small class="reply-time">
                                            <i class="far fa-clock"></i>
                                            Cập nhật: <?= date('H:i d/m/Y', strtotime($fb['UpdatedAt'])) ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Ảnh nếu có -->
                            <?php if (!empty($fb['ImagePath'])): ?>
                                <div class="feedback-image">
                                    <img
                                        src="../../<?= htmlspecialchars($fb['ImagePath']) ?>"
                                        alt="Ảnh phản ánh"
                                        class="feedback-img"
                                        style="width:120px; height:auto; border-radius:8px; border:1px solid #ddd;
                                               box-shadow:0 3px 10px rgba(0,0,0,0.1); cursor:pointer;
                                               transition:transform 0.2s ease, box-shadow 0.3s ease;"
                                        onclick="openImageModal(this.src)">
                                </div>
                            <?php endif; ?>

                            <div class="feedback-footer">
                                <small>
                                    <i class="far fa-clock"></i>
                                    <?= date('H:i d/m/Y', strtotime($fb['CreatedAt'])) ?>
                                </small>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox fa-3x"></i>
                    <p>📭 Bạn chưa gửi phản ánh nào</p>
                    <small>Hãy gửi phản ánh đầu tiên bằng form bên trên!</small>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- Modal xem ảnh -->
    <div id="imageModal" class="modal">
        <span class="close">&times;</span>
        <img class="modal-content" id="modalImage">
    </div>

    <script>
        // Hiển thị tên file chọn
        document.getElementById('image').addEventListener('change', function(e) {
            const fileName = e.target.files[0] ?
                e.target.files[0].name :
                'Chưa có file nào được chọn';
            document.getElementById('fileName').textContent = fileName;
        });

        // Modal xem ảnh
        const modal = document.getElementById('imageModal');
        const modalImg = document.getElementById('modalImage');
        const closeBtn = document.querySelector('#imageModal .close');

        function openImageModal(src) {
            modal.style.display = 'block';
            modalImg.src = src;
        }

        if (closeBtn) {
            closeBtn.onclick = () => modal.style.display = 'none';
        }
        window.onclick = function(event) {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        };
    </script>

    <?php if ($success): ?>
        <script>
            Swal.fire({
                icon: 'success',
                title: 'Thành công!',
                text: 'Gửi phản ánh thành công!',
                confirmButtonColor: '#4361ee'
            }).then(() => {
                window.location.href = 'feedbacks.php';
            });
        </script>
    <?php elseif (!empty($errorMsg)): ?>
        <script>
            Swal.fire({
                icon: 'error',
                title: 'Lỗi!',
                text: '<?= addslashes($errorMsg) ?>',
                confirmButtonColor: '#e63946'
            });
        </script>
    <?php endif; ?>

    <?php include '../../includes/footer.php'; ?>
</body>

</html>