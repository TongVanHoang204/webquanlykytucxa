<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'db_connect.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Must have verified OTP
if (!isset($_SESSION['reset_email']) || empty($_SESSION['otp_verified'])) {
    header("Location: forgot_password.php");
    exit;
}
$email = $_SESSION['reset_email'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['_token'])) {
        $error = "Yêu cầu không hợp lệ (CSRF).";
    } else {
        $pw = $_POST['password'] ?? '';
        $cpw = $_POST['confirm_password'] ?? '';

        if (strlen($pw) < 8) $error = "Mật khẩu ít nhất 8 ký tự.";
        elseif (!preg_match('/[A-Z]/', $pw)) $error = "Mật khẩu cần 1 chữ hoa.";
        elseif (!preg_match('/[a-z]/', $pw)) $error = "Mật khẩu cần 1 chữ thường.";
        elseif (!preg_match('/[0-9]/', $pw)) $error = "Mật khẩu cần 1 chữ số.";
        elseif ($pw !== $cpw) $error = "Xác nhận mật khẩu không khớp.";

        if (empty($error)) {
            $hash = password_hash($pw, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE Users SET PasswordHash = ? WHERE Email = ?");
            $stmt->bind_param("ss", $hash, $email);
            
            if ($stmt->execute()) {
                // Clear OTP
                $del = $conn->prepare("DELETE FROM PasswordResets WHERE Email = ?");
                $del->bind_param("s", $email);
                $del->execute();

                // Clear session auth flags
                unset($_SESSION['reset_email']);
                unset($_SESSION['otp_verified']);

                // Optionally set success message flag for login.php if it supports it
                // We'll just redirect to login with query param or wait
                echo "<script>alert('Đổi mật khẩu thành công! Vui lòng đăng nhập lại.'); window.location.href='login.php';</script>";
                exit;
            } else {
                $error = "Cập nhật thất bại. Lỗi hệ thống.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đặt lại mật khẩu | Ký túc xá</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="assets/css/auth_bento.css">
    <style>
        .auth-container { max-width: 1000px; }
        .auth-brand { background: linear-gradient(135deg, #10b981 0%, #3b82f6 100%); }
    </style>
</head>
<body>
    <div class="blob-shape blob-1" style="background:var(--gradient-success); top:-100px; left:-100px;"></div>
    <div class="blob-shape blob-2" style="background:var(--gradient-info);"></div>

    <div class="auth-container <?= !empty($error) ? 'animate-shake' : '' ?>">
        <!-- Brand Side -->
        <div class="auth-brand">
            <div class="brand-top">
                <div class="brand-logo" style="color:#10b981;"><i class="fas fa-lock"></i></div>
                <h1>Bảo mật<br>Tài khoản</h1>
                <p>Mã xác nhận hợp lệ. Bây giờ bạn có thể thiết lập mật khẩu mới (Tối thiểu 8 ký tự, 1 hoa, 1 số).</p>
            </div>
        </div>

        <!-- Form Side -->
        <div class="auth-form-wrap">
            <h2>Mật khẩu mới</h2>
            <p style="margin-bottom:24px;">Hãy tạo mật khẩu mạnh để bảo vệ tài khoản.</p>

            <?php if (!empty($error)): ?>
                <div class="auth-alert error">
                    <i class="fas fa-exclamation-circle"></i>
                    <ul><li><?= htmlspecialchars($error) ?></li></ul>
                </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                
                <div class="form-group">
                    <label>Mật khẩu mới</label>
                    <div class="input-icon-wrap">
                        <input type="password" id="pw" name="password" required placeholder="Nhập mật khẩu mới">
                        <i class="fas fa-shield-alt icon-left"></i>
                        <button type="button" class="toggle-pw" onclick="togglePw('pw')"><i class="fas fa-eye"></i></button>
                    </div>
                </div>

                <div class="form-group">
                    <label>Xác nhận mật khẩu mới</label>
                    <div class="input-icon-wrap">
                        <input type="password" id="cpw" name="confirm_password" required placeholder="Nhập lại mật khẩu mới">
                        <i class="fas fa-shield-check icon-left"></i>
                        <button type="button" class="toggle-pw" onclick="togglePw('cpw')"><i class="fas fa-eye"></i></button>
                    </div>
                </div>

                <button type="submit" class="btn-submit" onclick="this.innerHTML='<span>Đang xử lý...</span> <i class=\'fas fa-spinner fa-spin\'></i>'; this.style.pointerEvents='none'; this.form.submit();">
                    <span>Cập nhật mật khẩu</span> <i class="fas fa-save"></i>
                </button>
            </form>
        </div>
    </div>

    <script>
        function togglePw(id) {
            const el = document.getElementById(id);
            const btn = el.nextElementSibling.nextElementSibling.querySelector('i');
            if (el.type === 'password') {
                el.type = 'text';
                btn.className = 'fas fa-eye-slash';
            } else {
                el.type = 'password';
                btn.className = 'fas fa-eye';
            }
        }
    </script>
</body>
</html>
