<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../../db_connect.php';
require_once '../../../includes/admin_header.php';
require_once '../../../includes/auth_check.php';
requireRole(['Admin']);


$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  die('<h3>Yêu cầu không hợp lệ</h3>');
}

$stmt = $conn->prepare("SELECT * FROM Announcements WHERE AnnouncementID = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$announcement = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$announcement) {
  die('<h3>Không tìm thấy thông báo</h3>');
}

$okMsg = $errorMsg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $title = trim($_POST['Title'] ?? '');
  $content = trim($_POST['Content'] ?? '');
  $attachmentPath = $announcement['AttachmentPath'];

  if ($title === '' || $content === '') {
    $errorMsg = "Vui lòng nhập đầy đủ tiêu đề và nội dung.";
  } else {
    // upload file mới (nếu có)
    if (!empty($_FILES['Attachment']['name'])) {
      $uploadDir = '../../../assets/uploads/announcements/';
      if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
      $filename = time() . '_' . basename($_FILES['Attachment']['name']);
      $targetFile = $uploadDir . $filename;
      if (move_uploaded_file($_FILES['Attachment']['tmp_name'], $targetFile)) {
        $attachmentPath = 'assets/uploads/announcements/' . $filename;
      }
    }

    // cập nhật DB
    $stmt = $conn->prepare("UPDATE Announcements SET Title=?, Content=?, AttachmentPath=? WHERE AnnouncementID=?");
    $stmt->bind_param("sssi", $title, $content, $attachmentPath, $id);
    if ($stmt->execute()) {
      $okMsg = "Cập nhật thông báo thành công!";
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
  <title>Sửa thông báo | Quản lý KTX</title>
  <link rel="stylesheet" href="../../assets/css/admin/admin_header.css">
  <link rel="stylesheet" href="../../../assets/css/staff/announcements/announcement_create.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>

  <div class="container">
    <h2><i class="fa-solid fa-pen-to-square"></i> Sửa thông báo</h2>

    <?php if ($okMsg): ?>
      <script>
        Swal.fire({
          icon: 'success',
          title: 'Thành công',
          text: '<?= htmlspecialchars($okMsg) ?>',
          timer: 900,
          showConfirmButton: false
        });
        setTimeout(() => window.location.href = 'announcement_list.php', 1500);
      </script>
    <?php elseif ($errorMsg): ?>
      <div class="alert error"><?= htmlspecialchars($errorMsg) ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="form-box">
      <div class="form-group">
        <label>Tiêu đề <span>*</span></label>
        <input type="text" name="Title" value="<?= htmlspecialchars($announcement['Title']) ?>" required>
      </div>

      <div class="form-group">
        <label>Nội dung <span>*</span></label>
        <textarea name="Content" rows="6" required><?= htmlspecialchars($announcement['Content']) ?></textarea>
      </div>

      <div class="form-group">
        <label>Tệp đính kèm hiện tại:</label>
        <?php if ($announcement['AttachmentPath']): ?>
          <p><a href="../../../<?= htmlspecialchars($announcement['AttachmentPath']) ?>" target="_blank">Xem tệp</a></p>
        <?php else: ?>
          <p><em>Không có tệp đính kèm</em></p>
        <?php endif; ?>
        <label>Thay thế tệp (tùy chọn):</label>
        <input type="file" name="Attachment" accept=".pdf,.doc,.docx,.jpg,.png">
      </div>

      <div class="form-actions">
        <button type="submit" class="btn primary"><i class="fa-solid fa-save"></i> Lưu thay đổi</button>
        <a href="announcement_list.php" class="btn ghost"><i class="fa-solid fa-arrow-left"></i> Quay lại</a>
      </div>
    </form>
  </div>

</body>

</html>