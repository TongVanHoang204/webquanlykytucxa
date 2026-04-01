<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'db_connect.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

if (!isset($_SESSION['reset_email'])) {
    header("Location: forgot_password.php");
    exit;
}
$email = $_SESSION['reset_email'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['_token'])) {
        $error = "Yêu cầu không hợp lệ (CSRF).";
    } else {
        $otp = trim($_POST['otp'] ?? '');
        
        if (strlen($otp) !== 6 || !is_numeric($otp)) {
            $error = "Mã OTP phải gồm 6 chữ số.";
        } else {
            $st = $conn->prepare("SELECT Token, ExpiresAt FROM PasswordResets WHERE Email = ? LIMIT 1");
            $st->bind_param("s", $email);
            $st->execute();
            $res = $st->get_result();

            if ($row = $res->fetch_assoc()) {
                if (strtotime($row['ExpiresAt']) < time()) {
                    $error = "Mã OTP đã hết hạn. Vui lòng yêu cầu mã mới.";
                } elseif ($row['Token'] !== $otp) {
                    $error = "Mã OTP không chính xác.";
                } else {
                    // OTP is valid
                    $_SESSION['otp_verified'] = true;
                    header("Location: reset_password.php");
                    exit;
                }
            } else {
                $error = "Không tìm thấy yêu cầu đặt lại mật khẩu hợp lệ.";
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
    <title>Xác minh OTP | Ký túc xá</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="assets/css/auth_bento.css">
    <style>
        .auth-container { max-width: 1000px; }
        .auth-brand { background: linear-gradient(135deg, #f59e0b 0%, #ec4899 100%); }
    </style>
</head>
<body>
    <div class="blob-shape blob-1" style="background:var(--gradient-success); top:-100px; left:-100px;"></div>
    <div class="blob-shape blob-2" style="background:var(--gradient-primary);"></div>

    <div class="auth-container <?= !empty($error) ? 'animate-shake' : '' ?>">
        <!-- Brand Side -->
        <div class="auth-brand">
            <div class="brand-top">
                <div class="brand-logo" style="color:#f59e0b;"><i class="fas fa-key"></i></div>
                <h1>Xác minh<br>Danh tính</h1>
                <p>Một mã bảo mật 6 số đã được gửi tới Email: <strong><?= htmlspecialchars($email) ?></strong></p>
            </div>
            <div class="auth-footer" style="text-align:left; border:none; padding:0; margin-top:40px; color:rgba(255,255,255,0.8);">
                <a href="forgot_password.php" style="color:#fff;"><i class="fas fa-arrow-left"></i> Sử dụng email khác</a>
            </div>
        </div>

        <!-- Form Side -->
        <div class="auth-form-wrap">
            <h2>Nhập mã OTP</h2>
            <p style="margin-bottom:24px;">Vui lòng kiểm tra hộp thư đến hoặc phần Spam.</p>

            <?php if (!empty($error)): ?>
                <div class="auth-alert error">
                    <i class="fas fa-exclamation-circle"></i>
                    <ul><li><?= htmlspecialchars($error) ?></li></ul>
                </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                
                <div class="form-group">
                    <label>Mã OTP (6 chữ số)</label>
                    <div class="input-icon-wrap" style="text-align:center;">
                        <input type="text" name="otp" required maxlength="6" pattern="\d{6}" placeholder="------" 
                               style="font-size:2rem; letter-spacing:14px; text-align:center; padding-left:10px;">
                    </div>
                </div>

                <button type="submit" class="btn-submit" onclick="this.innerHTML='<span>Đang xử lý...</span> <i class=\'fas fa-spinner fa-spin\'></i>'; this.style.pointerEvents='none'; this.form.submit();">
                    <span>Xác nhận</span> <i class="fas fa-check-circle"></i>
                </button>
            </form>
        </div>
    </div>
</body>
</html>
