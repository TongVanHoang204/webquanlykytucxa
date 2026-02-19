<?php
include '../../../db_connect.php';

include '../../../includes/auth_check.php';

// 🔒 Chỉ cho phép Admin hoặc Manager
if (!isset($_SESSION['Role']) || !in_array($_SESSION['Role'], ['Admin', 'Manager'])) {
    header('Location: ../../login.php');
    exit;
}

// 🔍 Lấy ID phản ánh
if (!isset($_GET['id'])) {
    die("❌ Thiếu tham số phản ánh ID");
}
$id = intval($_GET['id']);

// ==============================
// 📦 LẤY THÔNG TIN PHẢN ÁNH
// ==============================
$sql = "SELECT f.*, s.FullName, s.StudentCode, s.Email, s.Phone 
        FROM Feedbacks f
        JOIN Students s ON f.StudentID = s.StudentID
        WHERE f.FeedbackID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$feedback = $result->fetch_assoc();

if (!$feedback) {
    die("❌ Không tìm thấy phản ánh.");
}

// ==============================
// 💾 CẬP NHẬT PHẢN ÁNH
// ==============================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = $_POST['Status'] ?? $feedback['Status'];
    $reply = $_POST['Reply'] ?? '';

    // Kiểm tra xem có cột Reply chưa, nếu chưa thì tự động thêm
    $checkColumn = $conn->query("SHOW COLUMNS FROM Feedbacks LIKE 'Reply'");
    if ($checkColumn->num_rows == 0) {
        $conn->query("ALTER TABLE Feedbacks ADD Reply TEXT NULL AFTER Content");
    }

    $update = $conn->prepare("UPDATE Feedbacks 
                              SET Status = ?, Reply = ?, UpdatedAt = NOW()
                              WHERE FeedbackID = ?");
    $update->bind_param("ssi", $status, $reply, $id);
    if ($update->execute()) {
        $_SESSION['message'] = "✅ Đã cập nhật phản ánh thành công!";
        $_SESSION['message_type'] = 'success';
        header("Location: feedback_list.php");
        exit;
    } else {
        $errorMsg = "❌ Lỗi khi cập nhật phản ánh: " . $conn->error;
    }
}

// Hàm lấy class cho status badge
function getStatusClass($status)
{
    switch ($status) {
        case 'Chưa xử lý':
            return 'pending';
        case 'Đang xử lý':
            return 'processing';
        case 'Đã xử lý':
            return 'resolved';
        default:
            return 'pending';
    }
}

// Hàm chuẩn hóa đường dẫn ảnh
function getImagePath($imagePath)
{
    if (empty($imagePath)) return null;

    // Các định dạng đường dẫn có thể có
    $possiblePaths = [
        '../../../' . ltrim($imagePath, '/'),
        '../../../assets/img/feedbacks/' . basename($imagePath),
        '../../../img/feedbacks/' . basename($imagePath),
        '../../../uploads/feedbacks/' . basename($imagePath),
        '../../../assets/uploads/feedbacks/' . basename($imagePath)
    ];

    foreach ($possiblePaths as $path) {
        if (file_exists($path)) {
            return $path;
        }
    }

    return null;
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xử lý phản ánh #<?= $feedback['FeedbackID'] ?> | Hệ thống Ký túc xá</title>
    <link rel="stylesheet" href="../../assets/css/admin/admin_header.css">
    <link rel="stylesheet" href="../../../assets/css/staff/feedback/feedback_resolve.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>
    <div class="container">
        <div class="header">
            <h2><i class="fas fa-tools"></i> Xử lý phản ánh #<?= htmlspecialchars($feedback['FeedbackID']) ?></h2>
            <p>Quản lý và phản hồi phản ánh từ sinh viên</p>
        </div>

        <div class="content">
            <?php if (isset($errorMsg)): ?>
                <div class="alert error">
                    <i class="fas fa-exclamation-circle"></i> <?= $errorMsg ?>
                </div>
            <?php endif; ?>

            <div class="info-box">
                <p>
                    <i class="fas fa-user"></i>
                    <strong>Sinh viên:</strong>
                    <?= htmlspecialchars($feedback['FullName']) ?> (<?= htmlspecialchars($feedback['StudentCode']) ?>)
                </p>
                <p>
                    <i class="fas fa-envelope"></i>
                    <strong>Email:</strong>
                    <?= htmlspecialchars($feedback['Email'] ?? 'Chưa cập nhật') ?>
                </p>
                <p>
                    <i class="fas fa-phone"></i>
                    <strong>SĐT:</strong>
                    <?= htmlspecialchars($feedback['Phone'] ?? 'Chưa cập nhật') ?>
                </p>
                <p>
                    <i class="fas fa-clock"></i>
                    <strong>Gửi lúc:</strong>
                    <?= date('d/m/Y H:i', strtotime($feedback['CreatedAt'])) ?>
                </p>
                <p>
                    <i class="fas fa-info-circle"></i>
                    <strong>Trạng thái hiện tại:</strong>
                    <span class="status-preview <?= getStatusClass($feedback['Status']) ?>">
                        <?= htmlspecialchars($feedback['Status']) ?>
                    </span>
                </p>
            </div>

            <h3><i class="fas fa-edit"></i> Nội dung phản ánh</h3>
            <div class="feedback-content">
                <strong><?= htmlspecialchars($feedback['Title']) ?></strong>
                <p><?= nl2br(htmlspecialchars($feedback['Content'])) ?></p>
            </div>

            <?php
            // ==========================
            // 🖼️ HIỂN THỊ ẢNH PHẢN ÁNH
            // ==========================
            if (!empty($feedback['ImagePath'])) {
                $fullPath = getImagePath($feedback['ImagePath']);

                if ($fullPath && file_exists($fullPath)) {
                    echo '<div class="feedback-image">';
                    echo '<p><strong><i class="fas fa-image"></i> Hình ảnh đính kèm:</strong></p>';
                    echo '<img src="' . htmlspecialchars($fullPath) . '" alt="Ảnh phản ánh" onclick="openImageModal(this.src)">';
                    echo '</div>';
                } else {
                    echo '<div class="alert warning">';
                    echo '<i class="fas fa-exclamation-triangle"></i> ';
                    echo '⚠️ Không thể tải ảnh. File: ' . htmlspecialchars(basename($feedback['ImagePath']));
                    echo '<br><small>Đường dẫn: ' . htmlspecialchars($feedback['ImagePath']) . '</small>';
                    echo '</div>';
                }
            }
            ?>

            <div class="divider"></div>

            <form method="POST" id="feedbackForm">
                <label for="Status">
                    <i class="fas fa-sync-alt"></i> Trạng thái xử lý:
                </label>
                <select name="Status" id="Status" required onchange="updateStatusPreview()">
                    <option value="Chưa xử lý" <?= $feedback['Status'] == 'Chưa xử lý' ? 'selected' : '' ?>>⏳ Chưa xử lý</option>
                    <option value="Đang xử lý" <?= $feedback['Status'] == 'Đang xử lý' ? 'selected' : '' ?>>🔄 Đang xử lý</option>
                    <option value="Đã xử lý" <?= $feedback['Status'] == 'Đã xử lý' ? 'selected' : '' ?>>✅ Đã xử lý</option>
                </select>

                <label for="Reply">
                    <i class="fas fa-reply"></i> Phản hồi của Ban quản lý:
                </label>
                <textarea name="Reply" id="Reply" placeholder="Nhập nội dung phản hồi cho sinh viên..."
                    oninput="updateCharCounter()"><?= htmlspecialchars($feedback['Reply'] ?? '') ?></textarea>
                <div id="charCounter" class="char-counter">0/1000 ký tự</div>

                <div class="btn-container">
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="fas fa-save"></i> Lưu cập nhật
                    </button>
                    <a href="feedback_list.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Quay lại danh sách
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openImageModal(src) {
            const modal = document.createElement('div');
            modal.classList.add('image-modal');
            modal.innerHTML = `
                <div class="modal-content">
                    <span class="close-btn" onclick="closeImageModal()">&times;</span>
                    <img src="${src}" alt="Ảnh phản ánh">
                </div>
            `;
            modal.onclick = (e) => {
                if (e.target === modal) closeImageModal();
            };
            document.body.appendChild(modal);

            // Đóng modal bằng ESC
            const closeModal = (e) => {
                if (e.key === 'Escape') {
                    closeImageModal();
                }
            };
            document.addEventListener('keydown', closeModal);
        }

        function closeImageModal() {
            const modal = document.querySelector('.image-modal');
            if (modal) {
                document.body.removeChild(modal);
            }
        }

        function updateStatusPreview() {
            const status = document.getElementById('Status').value;
            const statusText = document.querySelector('.status-preview');
            if (statusText) {
                statusText.textContent = status;
                statusText.className = 'status-preview ' + getStatusClass(status);
            }
        }

        function getStatusClass(status) {
            switch (status) {
                case 'Chưa xử lý':
                    return 'pending';
                case 'Đang xử lý':
                    return 'processing';
                case 'Đã xử lý':
                    return 'resolved';
                default:
                    return 'pending';
            }
        }

        function updateCharCounter() {
            const textarea = document.getElementById('Reply');
            const counter = document.getElementById('charCounter');
            const length = textarea.value.length;
            counter.textContent = length + '/1000 ký tự';

            if (length > 800) {
                counter.className = 'char-counter warning';
            } else if (length > 950) {
                counter.className = 'char-counter danger';
            } else {
                counter.className = 'char-counter';
            }
        }

        // Khởi tạo
        document.addEventListener('DOMContentLoaded', function() {
            updateCharCounter();

            // Form submission
            document.getElementById('feedbackForm').addEventListener('submit', function(e) {
                const submitBtn = document.getElementById('submitBtn');
                submitBtn.classList.add('loading');
                submitBtn.disabled = true;
            });
        });
    </script>
</body>

</html>