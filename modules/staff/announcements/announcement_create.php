<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../../db_connect.php';
require_once '../../../includes/admin_header.php';
require_once '../../../includes/auth_check.php';
requireRole(['Admin', 'Manager']);





$okMsg = $errorMsg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $title = trim($_POST['Title'] ?? '');
  $content = trim($_POST['Content'] ?? '');
  $postedBy = $_SESSION['FullName'] ?? 'Quản trị KTX';
  $attachmentPath = null;

  if ($title === '' || $content === '') {
    $errorMsg = "Vui lòng nhập đầy đủ tiêu đề và nội dung.";
  } else {
    // upload file (nếu có)
    if (!empty($_FILES['Attachment']['name'])) {
      $uploadDir = '../../../assets/uploads/announcements/';
      if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
      $filename = time() . '_' . basename($_FILES['Attachment']['name']);
      $targetFile = $uploadDir . $filename;
      if (move_uploaded_file($_FILES['Attachment']['tmp_name'], $targetFile)) {
        $attachmentPath = 'assets/uploads/announcements/' . $filename;
      }
    }

    // insert vào DB
    $stmt = $conn->prepare("INSERT INTO Announcements (Title, Content, DatePosted, PostedBy, AttachmentPath) VALUES (?, ?, NOW(), ?, ?)");
    $stmt->bind_param("ssss", $title, $content, $postedBy, $attachmentPath);
    if ($stmt->execute()) {
      $okMsg = "Đã đăng thông báo thành công!";
      addLog(
        $conn,
        $_SESSION['UserID'] ?? null,
        'Create announcement',
        'Announcements',
        "Tạo thông báo: {$title}",
        'activity'
      );
    } else {
      $errorMsg = "Lỗi khi lưu dữ liệu: " . $conn->error;
    }
    $stmt->close();
  }
}


?>

<!DOCTYPE html>
<html lang="vi">

<head>
  <meta charset="UTF-8">
  <title>Tạo thông báo | Quản lý KTX</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../../assets/css/admin/admin_header.css">
  <link rel="stylesheet" href="../../../assets/css/staff/announcements/announcement_create.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>

  <div class="container">
    <h2><i class="fa-solid fa-bullhorn"></i> Tạo thông báo ký túc xá</h2>

    <?php if ($okMsg): ?>
      <script>
        Swal.fire({
          icon: 'success',
          title: 'Thành công',
          text: '<?= htmlspecialchars($okMsg) ?>',
          timer: 1000,
          showConfirmButton: false,
          background: '#f0f9ff',
          iconColor: '#10b981'
        });
        setTimeout(() => window.location.href = 'announcement_list.php', 950);
      </script>
    <?php elseif ($errorMsg): ?>
      <div class="alert error">
        <i class="fa-solid fa-circle-exclamation"></i>
        <?= htmlspecialchars($errorMsg) ?>
      </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="form-box" id="announcementForm">
      <div class="form-group">
        <label>Tiêu đề thông báo <span>*</span></label>
        <input type="text" name="Title" required maxlength="255" placeholder="Nhập tiêu đề thông báo..." id="titleInput">
        <div class="char-counter"><span id="titleCount">0</span>/255 ký tự</div>
      </div>

      <div class="form-group">
        <label>Nội dung thông báo <span>*</span></label>
        <textarea name="Content" rows="8" required placeholder="Nhập nội dung chi tiết của thông báo..." id="contentInput"></textarea>
        <div class="char-counter"><span id="contentCount">0</span>/2000 ký tự</div>
      </div>

      <div class="form-group">
        <label>Tệp đính kèm (tuỳ chọn)</label>
        <div class="file-upload">
          <input type="file" name="Attachment" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.zip" id="fileInput">
          <div class="file-upload-content">
            <i class="fa-solid fa-cloud-arrow-up"></i>
            <span>Nhấn để chọn tệp đính kèm</span>
            <small>Hỗ trợ: PDF, DOC, DOCX, JPG, PNG, ZIP (Tối đa 10MB)</small>
          </div>
        </div>
        <div class="file-info" id="fileInfo">
          <i class="fa-solid fa-file"></i>
          <span id="fileName"></span>
        </div>
      </div>

      <!-- Preview Section -->
      <div class="preview-section" id="previewSection">
        <h3><i class="fa-solid fa-eye"></i> Xem trước thông báo</h3>
        <div class="preview-content">
          <div class="preview-title" id="previewTitle">Tiêu đề sẽ hiển thị ở đây</div>
          <div class="preview-body" id="previewContent">Nội dung thông báo sẽ hiển thị ở đây...</div>
        </div>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn primary" id="submitBtn">
          <i class="fa-solid fa-paper-plane"></i> Đăng thông báo
        </button>
        <button type="button" class="btn ghost" onclick="togglePreview()" id="previewBtn">
          <i class="fa-solid fa-eye"></i> Xem trước
        </button>
        <a href="announcement_list.php" class="btn ghost">
          <i class="fa-solid fa-arrow-left"></i> Quay lại
        </a>
      </div>
    </form>
  </div>

  <script>
    // Character counters
    const titleInput = document.getElementById('titleInput');
    const contentInput = document.getElementById('contentInput');
    const titleCount = document.getElementById('titleCount');
    const contentCount = document.getElementById('contentCount');
    const fileInput = document.getElementById('fileInput');
    const fileInfo = document.getElementById('fileInfo');
    const fileName = document.getElementById('fileName');
    const previewSection = document.getElementById('previewSection');
    const previewTitle = document.getElementById('previewTitle');
    const previewContent = document.getElementById('previewContent');
    const previewBtn = document.getElementById('previewBtn');
    const submitBtn = document.getElementById('submitBtn');
    const form = document.getElementById('announcementForm');

    // Update character counters
    titleInput.addEventListener('input', function() {
      const count = this.value.length;
      titleCount.textContent = count;
      if (count > 200) {
        titleCount.parentElement.className = 'char-counter warning';
      } else {
        titleCount.parentElement.className = 'char-counter';
      }
    });

    contentInput.addEventListener('input', function() {
      const count = this.value.length;
      contentCount.textContent = count;
      if (count > 1800) {
        contentCount.parentElement.className = 'char-counter warning';
      } else if (count > 1900) {
        contentCount.parentElement.className = 'char-counter danger';
      } else {
        contentCount.parentElement.className = 'char-counter';
      }
    });

    // File input handling
    fileInput.addEventListener('change', function() {
      if (this.files.length > 0) {
        const file = this.files[0];
        fileName.textContent = file.name;
        fileInfo.classList.add('show');
      } else {
        fileInfo.classList.remove('show');
      }
    });

    // Preview functionality
    function togglePreview() {
      const title = titleInput.value.trim() || 'Tiêu đề sẽ hiển thị ở đây';
      const content = contentInput.value.trim() || 'Nội dung thông báo sẽ hiển thị ở đây...';

      previewTitle.textContent = title;
      previewContent.textContent = content;

      previewSection.classList.toggle('show');
      previewBtn.innerHTML = previewSection.classList.contains('show') ?
        '<i class="fa-solid fa-eye-slash"></i> Ẩn xem trước' :
        '<i class="fa-solid fa-eye"></i> Xem trước';
    }

    // Form submission handling
    form.addEventListener('submit', function(e) {
      const title = titleInput.value.trim();
      const content = contentInput.value.trim();

      if (!title || !content) {
        e.preventDefault();
        Swal.fire({
          icon: 'warning',
          title: 'Thiếu thông tin',
          text: 'Vui lòng nhập đầy đủ tiêu đề và nội dung thông báo.',
          confirmButtonColor: '#3b82f6'
        });
        return;
      }

      // Show loading state
      submitBtn.classList.add('loading');
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<i class="fa-solid fa-spinner"></i> Đang đăng...';
    });

    // Initialize counters
    titleInput.dispatchEvent(new Event('input'));
    contentInput.dispatchEvent(new Event('input'));

    // Auto-save draft (optional)
    let draftTimer;

    function saveDraft() {
      const draft = {
        title: titleInput.value,
        content: contentInput.value
      };
      localStorage.setItem('announcement_draft', JSON.stringify(draft));
    }

    titleInput.addEventListener('input', () => {
      clearTimeout(draftTimer);
      draftTimer = setTimeout(saveDraft, 1000);
    });

    contentInput.addEventListener('input', () => {
      clearTimeout(draftTimer);
      draftTimer = setTimeout(saveDraft, 1000);
    });

    // Load draft on page load
    window.addEventListener('load', () => {
      const draft = localStorage.getItem('announcement_draft');
      if (draft) {
        const {
          title,
          content
        } = JSON.parse(draft);
        if (title) titleInput.value = title;
        if (content) contentInput.value = content;

        // Update counters
        titleInput.dispatchEvent(new Event('input'));
        contentInput.dispatchEvent(new Event('input'));

        // Ask user if they want to restore draft
        Swal.fire({
          title: 'Khôi phục bản nháp?',
          text: 'Chúng tôi tìm thấy bản nháp chưa được lưu. Bạn có muốn khôi phục không?',
          icon: 'question',
          showCancelButton: true,
          confirmButtonText: 'Có, khôi phục',
          cancelButtonText: 'Không, xoá bản nháp',
          confirmButtonColor: '#3b82f6'
        }).then((result) => {
          if (!result.isConfirmed) {
            localStorage.removeItem('announcement_draft');
          }
        });
      }
    });

    // Clear draft on successful submission
    window.addEventListener('beforeunload', () => {
      if (document.querySelector('.swal2-container') === null) {
        localStorage.removeItem('announcement_draft');
      }
    });
  </script>

</body>

</html>