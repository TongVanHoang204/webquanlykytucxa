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
  <link rel="stylesheet" href="../../../assets/vendor/fontawesome/css/all.min.css">
  <link href="../../../assets/vendor/fonts/fonts.css" rel="stylesheet">
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

      <!-- AI DRAFTING ASSISTANT BANNER -->
      <div style="background:linear-gradient(135deg,rgba(99,102,241,0.08),rgba(168,85,247,0.05));border:1px dashed rgba(99,102,241,0.35);border-radius:14px;padding:16px 20px;display:flex;align-items:center;gap:14px;margin-bottom:4px;">
        <div style="width:40px;height:40px;border-radius:12px;background:linear-gradient(135deg,#6366f1,#a855f7);display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.1rem;flex-shrink:0;">
          <i class="fas fa-wand-magic-sparkles"></i>
        </div>
        <div style="flex:1;">
          <div style="font-weight:700;color:var(--text,#111);font-size:0.95rem;">Trợ lý AI Soạn thảo</div>
          <div style="font-size:0.82rem;color:#6b7280;margin-top:2px;">Nhập ý chính, AI sẽ viết nội dung thông báo hoàn chỉnh cho bạn.</div>
        </div>
        <button type="button" onclick="openAiDraftModal()" style="background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;border:none;border-radius:10px;padding:9px 18px;font-size:0.9rem;font-weight:600;cursor:pointer;white-space:nowrap;">
          ✨ Dùng AI Soạn Thảo
        </button>
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

  <!-- ===== AI DRAFT MODAL ===== -->
  <div id="aiDraftModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.6);backdrop-filter:blur(4px);align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:20px;padding:28px;max-width:560px;width:90%;box-shadow:0 30px 60px rgba(0,0,0,0.2);">
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;">
        <div style="width:40px;height:40px;border-radius:12px;background:linear-gradient(135deg,#6366f1,#a855f7);display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.1rem;">
          <i class="fas fa-wand-magic-sparkles"></i>
        </div>
        <div>
          <div style="font-weight:800;font-size:1.1rem;">AI Soạn thảo Thông báo</div>
          <div style="font-size:0.82rem;color:#6b7280;">Mô tả ý chính bạn muốn truyền đạt</div>
        </div>
        <button onclick="closeAiDraftModal()" style="margin-left:auto;background:none;border:none;font-size:1.4rem;cursor:pointer;color:#6b7280;">&times;</button>
      </div>
      <div style="margin-bottom:14px;">
        <label style="display:block;font-size:0.85rem;font-weight:600;margin-bottom:6px;">Loại thông báo</label>
        <select id="aiDraftType" style="width:100%;padding:10px 14px;border-radius:10px;border:1.5px solid #e5e7eb;font-size:0.9rem;outline:none;">
          <option value="notice">📢 Thông báo chung</option>
          <option value="debt_reminder">💰 Nhắc nộ đóng tiền</option>
          <option value="warning">⚠️ Cảnh cáo vi phạm</option>
          <option value="evacuation">🚨 Thông báo khẩn</option>
        </select>
      </div>
      <div style="margin-bottom:14px;">
        <label style="display:block;font-size:0.85rem;font-weight:600;margin-bottom:6px;">Ý chính muốn truyền đạt <span style="color:#ef4444;">*</span></label>
        <textarea id="aiDraftTopic" rows="3" placeholder="VD: Nhắc sinh viên tòa A chưa đóng tiền điện tháng 4..." style="width:100%;padding:10px 14px;border-radius:10px;border:1.5px solid #e5e7eb;font-size:0.9rem;resize:vertical;outline:none;font-family:inherit;box-sizing:border-box;"></textarea>
      </div>
      <div id="aiDraftResult" style="display:none;background:#f9fafb;border-radius:12px;padding:14px;margin-bottom:14px;border:1px solid #e5e7eb;">
        <div style="font-size:0.75rem;font-weight:700;color:#6366f1;margin-bottom:8px;text-transform:uppercase;letter-spacing:0.06em;">Kết quả AI tạo</div>
        <div style="font-size:0.88rem;font-weight:700;margin-bottom:6px;" id="aiDraftGenTitle"></div>
        <div style="font-size:0.85rem;line-height:1.7;white-space:pre-wrap;color:#374151;" id="aiDraftGenContent"></div>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap;">
        <button onclick="closeAiDraftModal()" style="padding:9px 16px;border-radius:10px;border:1.5px solid #e5e7eb;background:none;cursor:pointer;font-size:0.9rem;">Hủy</button>
        <button onclick="generateAiDraft()" id="aiDraftGenerateBtn" style="padding:9px 20px;border-radius:10px;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;border:none;font-weight:700;cursor:pointer;font-size:0.9rem;">
          <i class="fas fa-bolt"></i> Tạo nội dung
        </button>
        <button onclick="applyAiDraft()" id="aiDraftApplyBtn" style="display:none;padding:9px 20px;border-radius:10px;background:linear-gradient(135deg,#10b981,#059669);color:#fff;border:none;font-weight:700;cursor:pointer;font-size:0.9rem;">
          <i class="fas fa-check"></i> Áp dụng vào Form
        </button>
      </div>
    </div>
  </div>

  <script>
    let _aiDraftData = null;
    function openAiDraftModal() {
      document.getElementById('aiDraftModal').style.display = 'flex';
      document.getElementById('aiDraftResult').style.display = 'none';
      document.getElementById('aiDraftApplyBtn').style.display = 'none';
      document.getElementById('aiDraftTopic').focus();
    }
    function closeAiDraftModal() { document.getElementById('aiDraftModal').style.display = 'none'; }
    async function generateAiDraft() {
      const topic = document.getElementById('aiDraftTopic').value.trim();
      const type  = document.getElementById('aiDraftType').value;
      if (!topic) { document.getElementById('aiDraftTopic').focus(); return; }
      const btn = document.getElementById('aiDraftGenerateBtn');
      btn.disabled = true; btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Đang tạo...';
      try {
        const res = await fetch('../../../modules/api/admin_ai_draft.php', {
          method: 'POST', headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ topic, type })
        });
        const data = await res.json();
        if (!data.ok) throw new Error(data.error || 'Lỗi AI');
        _aiDraftData = data.draft;
        document.getElementById('aiDraftGenTitle').textContent = data.draft.title || '';
        document.getElementById('aiDraftGenContent').textContent = data.draft.content || '';
        document.getElementById('aiDraftResult').style.display = 'block';
        document.getElementById('aiDraftApplyBtn').style.display = 'inline-block';
      } catch(e) { alert('Lỗi AI: ' + e.message); }
      finally { btn.disabled = false; btn.innerHTML = '<i class="fas fa-rotate-right"></i> Tạo lại'; }
    }
    function applyAiDraft() {
      if (!_aiDraftData) return;
      document.getElementById('titleInput').value   = _aiDraftData.title   || '';
      document.getElementById('contentInput').value = _aiDraftData.content || '';
      document.getElementById('titleInput').dispatchEvent(new Event('input'));
      document.getElementById('contentInput').dispatchEvent(new Event('input'));
      closeAiDraftModal();
    }
    document.getElementById('aiDraftModal').addEventListener('click', (e) => {
      if (e.target === e.currentTarget) closeAiDraftModal();
    });
  </script>

</body>
</html>