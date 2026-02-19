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
      $error = "⚠️ Vui lòng nhập đầy đủ tên đăng nhập và mật khẩu.";
    } else {
      // Chỉ lấy các cột cần thiết
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

              $needProfile = true; // mặc định: cần hoàn thiện

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

              // Nếu hồ sơ chưa đủ -> ép sang trang điền thông tin sinh viên
              if ($needProfile) {
                header("Location: modules/user/students/student_form.php");
                exit;
              }
            }
          }

          // ====== ROLE ROUTING MẶC ĐỊNH ======
          switch ($row['Role']) {
            case 'Admin':
              header("Location: modules/admin/dashboard.php");
              break;
            case 'Manager':
              header("Location: modules/staff/dashboard.php");
              break;
            default: // Student & các role khác
              header("Location: index.php");
          }
          exit;

        } else {
          $error = "❌ Mật khẩu không chính xác.";
        }
      } else {
        $error = "❌ Tài khoản không tồn tại.";
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
  <title>Đăng nhập KTX</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <!-- CSS riêng cho trang login -->
  <link rel="stylesheet" href="assets/css/login.css">
  <link rel="stylesheet" href="/assets/css/style.css">

  <!-- FontAwesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <!-- jQuery + jQuery Validate (không có dấu cách sau https:) -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.19.3/jquery.validate.min.js"></script>

</head>

<body>
  <div class="auth-wrapper">
    <div class="auth-card animate-fadeIn">
      <div class="auth-header">
        <h2><i class="fa-solid fa-right-to-bracket"></i> Đăng nhập hệ thống KTX</h2>
        <p>Nhập thông tin tài khoản để truy cập hệ thống ký túc xá.</p>
      </div>

      <?php if (!empty($error)): ?>
        <div class="alert alert-error animate-shakeX">
          <div class="alert-title">
            <i class="fa-solid fa-triangle-exclamation"></i> Không thể đăng nhập
          </div>
          <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
        </div>
      <?php endif; ?>

      <form id="loginForm" method="POST" novalidate>
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">

        <div class="form-row">
          <div class="input-group">
            <label for="username">Tên đăng nhập</label>
            <div class="input-with-icon">
              <i class="fa-solid fa-user"></i>
              <input type="text" id="username" name="username"
                value="<?= htmlspecialchars($oldUsername, ENT_QUOTES, 'UTF-8') ?>"
                required placeholder="Nhập tên đăng nhập">
            </div>
          </div>
        </div>

        <div class="form-row">
          <div class="input-group">
            <label for="password">Mật khẩu</label>
            <div class="input-with-icon password-wrapper">
              <i class="fa-solid fa-lock"></i>
              <input type="password" id="password" name="password" required placeholder="Nhập mật khẩu">
              <button type="button" class="toggle-password" data-target="password">
                <i class="fa-solid fa-eye"></i>
              </button>
            </div>

            <div class="login-meta">
              <label class="remember-me">
                <input type="checkbox" id="remember" name="remember">
                <span>Ghi nhớ đăng nhập</span>
              </label>
              <a href="forgot_password.php" class="forgot-link">
                <i class="fa-solid fa-key"></i> Quên mật khẩu?
              </a>
            </div>
          </div>
        </div>

        <div id="clientErrorBox" class="alert alert-error hidden">
          <div class="alert-title">
            <i class="fa-solid fa-triangle-exclamation"></i> Vui lòng kiểm tra lại
          </div>
          <ul class="alert-list" id="clientErrorList"></ul>
        </div>

        <button type="submit" class="btn-primary">
          <i class="fa-solid fa-right-to-bracket"></i> Đăng nhập
        </button>

        <!-- ========== HOẶC: ĐĂNG NHẬP NHANH ========== -->
        <div class="social-divider">
          <span></span>
          <p>Hoặc đăng nhập nhanh bằng</p>
          <span></span>
        </div>

        <div class="social-buttons">
          <a href="http://localhost/oauth_google.php" class="btn-social btn-google">
            <i class="fa-brands fa-google"></i>
            Google
          </a>
        </div>
        <!-- ========== END SOCIAL ========== -->

        <p class="auth-footer">
          Chưa có tài khoản?
          <a href="register.php"><i class="fa-solid fa-user-plus"></i> Đăng ký ngay</a>
        </p>
      </form>
    </div>
  </div>

  <script>
    // Toggle show/hide password
    document.querySelectorAll('.toggle-password').forEach(btn => {
      btn.addEventListener('click', function() {
        const targetId = this.getAttribute('data-target');
        const input = document.getElementById(targetId);
        const icon = this.querySelector('i');

        if (input.type === 'password') {
          input.type = 'text';
          icon.classList.remove('fa-eye');
          icon.classList.add('fa-eye-slash');
        } else {
          input.type = 'password';
          icon.classList.remove('fa-eye-slash');
          icon.classList.add('fa-eye');
        }
      });
    });

    // Client-side validation đơn giản
    const form = document.getElementById('loginForm');
    const clientErrorBox = document.getElementById('clientErrorBox');
    const clientErrorList = document.getElementById('clientErrorList');

    form.addEventListener('submit', function(e) {
      clientErrorList.innerHTML = '';
      clientErrorBox.classList.add('hidden');

      const username = document.getElementById('username').value.trim();
      const password = document.getElementById('password').value.trim();
      let clientErrors = [];

      if (!username) clientErrors.push('Vui lòng nhập tên đăng nhập.');
      if (!password) clientErrors.push('Vui lòng nhập mật khẩu.');

      if (clientErrors.length > 0) {
        e.preventDefault();
        clientErrors.forEach(msg => {
          const li = document.createElement('li');
          li.textContent = msg;
          clientErrorList.appendChild(li);
        });
        clientErrorBox.classList.remove('hidden');

        const card = document.querySelector('.auth-card');
        card.classList.remove('animate-shakeX');
        void card.offsetWidth;
        card.classList.add('animate-shakeX');
      }
    });
  </script>
</body>

</html>