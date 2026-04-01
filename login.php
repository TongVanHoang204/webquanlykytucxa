<?php
session_start();
include 'db_connect.php';
require_once __DIR__ . '/includes/log_helper.php';

$userID = $_SESSION['UserID'] ?? null;

// ====== CSRF TOKEN ======
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$error = '';
// Giữ lại username khi lỗi
$oldUsername = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // CSRF check
  if (!isset($_POST['_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['_token'])) {
    $error = "Yêu cầu không hợp lệ (CSRF token không khớp). Vui lòng tải lại trang.";
  } else {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $oldUsername = $username;

    if ($username === '' || $password === '') {
      $error = "Vui lòng nhập đầy đủ tên đăng nhập và mật khẩu.";
    } else {
      $stmt = $conn->prepare("SELECT UserID, Username, FullName, Role, PasswordHash FROM Users WHERE Username = ? LIMIT 1");
      $stmt->bind_param("s", $username);
      $stmt->execute();
      $result = $stmt->get_result();

      if ($row = $result->fetch_assoc()) {
        if (password_verify($password, $row['PasswordHash'])) {
          // Lưu session
          $_SESSION['UserID']   = (int)$row['UserID'];
          $_SESSION['FullName'] = $row['FullName'];
          $_SESSION['Role']     = $row['Role'];
          $_SESSION['Username'] = $row['Username'];

          // ====== NẾU LÀ SINH VIÊN: KIỂM TRA HỒ SƠ ĐÃ ĐẦY ĐỦ CHƯA ======
          if ($row['Role'] === 'Student') {
            $checkProfileSql = "
              SELECT Gender, BirthDate, Phone, ClassName, CourseYear, FacultyID, Address
              FROM Students
              WHERE UserID = ?
              LIMIT 1
            ";
            $cp = $conn->prepare($checkProfileSql);
            if ($cp) {
              $uid = (int)$row['UserID'];
              $cp->bind_param("i", $uid);
              $cp->execute();
              $profileRes = $cp->get_result();
              $cp->close();

              $needProfile = true;

              if ($profileRow = $profileRes->fetch_assoc()) {
                $needProfile = false;
                $requiredFields = ['Gender','BirthDate','Phone','ClassName','CourseYear','FacultyID','Address'];
                foreach ($requiredFields as $f) {
                  $v = $profileRow[$f] ?? null;
                  if ($v === null || $v === '') {
                    $needProfile = true;
                    break;
                  }
                }
              }

              if ($needProfile) {
                header("Location: modules/user/students/student_form.php");
                exit;
              }
            }
          }

          // ====== ROLE ROUTING MẶC ĐỊNH ======
          switch ($row['Role']) {
            case 'Admin': header("Location: modules/admin/dashboard.php"); break;
            case 'Manager': header("Location: modules/staff/dashboard.php"); break;
            default: header("Location: index.php");
          }
          exit;

        } else {
          $error = "Mật khẩu không chính xác.";
        }
      } else {
        $error = "Tài khoản không tồn tại.";
      }
      $stmt->close();
    }
  }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Đăng nhập | Ký Túc Xá</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  
  <!-- Fonts & Icons -->
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/vendor/fontawesome/css/all.min.css">
  
  <!-- Premium Bento Auth CSS -->
  <link rel="stylesheet" href="assets/css/auth_bento.css">
</head>

<body>
  <!-- Animated Background Blobs -->
  <div class="blob-shape blob-1"></div>
  <div class="blob-shape blob-2"></div>
  <div class="blob-shape blob-3"></div>

  <div class="auth-container <?= !empty($error) ? 'animate-shake' : '' ?>" id="authCard">
    <!-- Brand Side -->
    <div class="auth-brand">
      <div class="brand-top">
        <div class="brand-logo"><i class="fas fa-building"></i></div>
        <h1>Chào mừng <br>trở lại</h1>
        <p>Truy cập vào hệ thống quản lý Ký túc xá thông minh với trải nghiệm ưu việt và bảo mật cao.</p>
      </div>
      <div class="brand-bottom">
        <i class="fas fa-star"></i> Nhanh chóng, An toàn, Dễ sử dụng
      </div>
    </div>

    <!-- Form Side -->
    <div class="auth-form-wrap">
      <h2>Đăng nhập hệ thống</h2>
      <p>Nhập thông tin tài khoản để tiếp tục</p>

      <?php if (!empty($error)): ?>
        <div class="auth-alert error">
          <i class="fas fa-triangle-exclamation"></i>
          <ul><li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li></ul>
        </div>
      <?php endif; ?>

      <!-- Client error box -->
      <div id="clientErrorBox" class="auth-alert error" style="display: none;">
        <i class="fas fa-triangle-exclamation"></i>
        <ul id="clientErrorList"></ul>
      </div>

      <form id="loginForm" method="POST" novalidate>
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">

        <div class="form-group">
          <label for="username">Tên đăng nhập</label>
          <div class="input-icon-wrap">
            <input type="text" id="username" name="username" 
              value="<?= htmlspecialchars($oldUsername, ENT_QUOTES, 'UTF-8') ?>" 
              required placeholder="Nhập tên đăng nhập hoặc MSSV">
            <i class="fas fa-user icon-left"></i>
          </div>
        </div>

        <div class="form-group">
          <label for="password">Mật khẩu</label>
          <div class="input-icon-wrap">
            <input type="password" id="password" name="password" required placeholder="Nhập mật khẩu của bạn">
            <i class="fas fa-lock icon-left"></i>
            <button type="button" class="toggle-pw" aria-label="Hiện mật khẩu">
              <i class="fas fa-eye"></i>
            </button>
          </div>
          
          <div class="login-meta">
            <label class="remember-me">
              <input type="checkbox" id="remember" name="remember">
              <span>Ghi nhớ tôi</span>
            </label>
            <a href="forgot_password.php" class="forgot-link">Quên mật khẩu?</a>
          </div>
        </div>

        <button type="submit" class="btn-submit">
          <span>Đăng nhập</span> <i class="fas fa-arrow-right"></i>
        </button>

        <!-- Nút đăng nhập phụ / Google -->
        <div class="social-divider">
          <span></span>
          <p>hoặc</p>
          <span></span>
        </div>

        <a href="http://localhost/oauth_google.php" class="btn-social btn-google">
          <img src="https://www.svgrepo.com/show/475656/google-color.svg" alt="Google">
          Tiếp tục với Google
        </a>

        <div class="auth-footer">
          Chưa có tài khoản? <a href="register.php">Đăng ký ngay</a>
        </div>
      </form>
    </div>
  </div>

  <script>
    // Show/Hide Password
    document.querySelector('.toggle-pw').addEventListener('click', function() {
      const input = document.getElementById('password');
      const icon = this.querySelector('i');
      if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
      } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
      }
    });

    // Client-side Validation (Simple)
    const form = document.getElementById('loginForm');
    const clientErrorBox = document.getElementById('clientErrorBox');
    const clientErrorList = document.getElementById('clientErrorList');
    const authCard = document.getElementById('authCard');

    form.addEventListener('submit', function(e) {
      const username = document.getElementById('username').value.trim();
      const password = document.getElementById('password').value.trim();
      let errors = [];

      if (!username) errors.push('Vui lòng nhập tên đăng nhập.');
      if (!password) errors.push('Vui lòng nhập mật khẩu.');

      if (errors.length > 0) {
        e.preventDefault();
        clientErrorList.innerHTML = errors.map(err => `<li>${err}</li>`).join('');
        clientErrorBox.style.display = 'flex';
        
        authCard.classList.remove('animate-shake');
        void authCard.offsetWidth; // Trigger reflow
        authCard.classList.add('animate-shake');
      } else {
        const btn = this.querySelector('.btn-submit');
        btn.innerHTML = '<span>Đang xử lý</span> <i class="fas fa-circle-notch fa-spin"></i>';
        btn.style.pointerEvents = 'none';
        btn.style.opacity = '0.8';
      }
    });
  </script>
</body>
</html>
