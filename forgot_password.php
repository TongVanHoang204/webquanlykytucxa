<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'db_connect.php';

// CSRF TOKEN
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$error = '';
$success = '';
$oldEmail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['_token'])) {
        $error = "Yêu cầu không hợp lệ (CSRF). Vui lòng thử lại.";
    } else {
        $email = trim($_POST['email'] ?? '');
        $oldEmail = $email;

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Địa chỉ email không hợp lệ.";
        } else {
            // Check if email exists
            $stmt = $conn->prepare("SELECT UserID, FullName FROM Users WHERE Email = ? LIMIT 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($row = $res->fetch_assoc()) {
                // Generate 6-digit OTP
                $otp = sprintf("%06d", random_int(100000, 999999));
                $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));

                // Upsert OTP to PasswordResets
                $clearSt = $conn->prepare("DELETE FROM PasswordResets WHERE Email = ?");
                $clearSt->bind_param("s", $email);
                $clearSt->execute();

                $insSt = $conn->prepare("INSERT INTO PasswordResets (Email, Token, ExpiresAt) VALUES (?, ?, ?)");
                $insSt->bind_param("sss", $email, $otp, $expiresAt);
                if ($insSt->execute()) {
                    // Send Email
                    $subject = "Mã xác nhận khôi phục mật khẩu - Ký túc xá";
                    $message = "Xin chào " . $row['FullName'] . ",\n\n";
                    $message .= "Bạn vừa yêu cầu khôi phục mật khẩu. Mã xác nhận (OTP) của bạn là: $otp\n\n";
                    $message .= "Mã này sẽ hết hạn sau 15 phút.\n";
                    $message .= "Nếu bạn không yêu cầu, vui lòng bỏ qua email này.\n\nTrân trọng,\nBQL Ký Túc Xá";
                    $headers = "From: no-reply@ktx.edu.vn\r\n";
                    $headers .= "Content-Type: text/plain; charset=utf-8\r\n";

                    if (mail($email, $subject, $message, $headers)) {
                        $_SESSION['reset_email'] = $email;
                        header("Location: verify_otp.php");
                        exit;
                    } else {
                        $error = "Không thể gửi email. Vui lòng kiểm tra lại cấu hình SMTP của hệ thống.";
                        // For Laragon environments, ensure MailCatcher is enabled.
                        error_log("OTP for $email is $otp");
                    }
                } else {
                    $error = "Lỗi hệ thống khi lưu mã OTP.";
                }
            } else {
                $error = "Email này không được liên kết với bất kỳ tài khoản nào.";
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
    <title>Quên mật khẩu | Ký túc xá</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="assets/css/auth_bento.css">
    <style>
        .auth-container { max-width: 1000px; }
        .auth-brand { background: linear-gradient(135deg, #f43f5e 0%, #8b5cf6 100%); }
    </style>
</head>
<body>
    <div class="blob-shape blob-1" style="background:var(--gradient-warning); top:-100px; left:-100px;"></div>
    <div class="blob-shape blob-2" style="background:var(--gradient-danger);"></div>

    <div class="auth-container <?= !empty($error) ? 'animate-shake' : '' ?>">
        <!-- Brand Side -->
        <div class="auth-brand">
            <div class="brand-top">
                <div class="brand-logo" style="color:#f43f5e;"><i class="fas fa-shield-alt"></i></div>
                <h1>Khôi phục<br>tài khoản</h1>
                <p>Nhập email đã liên kết với tài khoản của bạn để nhận mã xác nhận bảo mật.</p>
            </div>
            <div class="auth-footer" style="text-align:left; border:none; padding:0; margin-top:40px; color:rgba(255,255,255,0.8);">
                <a href="login.php" style="color:#fff;"><i class="fas fa-arrow-left"></i> Quay lại đăng nhập</a>
            </div>
        </div>

        <!-- Form Side -->
        <div class="auth-form-wrap">
            <h2>Quên mật khẩu?</h2>
            <p style="margin-bottom:24px;">Đừng lo lắng, chúng tôi sẽ giúp bạn lấy lại quyền truy cập.</p>

            <?php if (!empty($error)): ?>
                <div class="auth-alert error">
                    <i class="fas fa-exclamation-circle"></i>
                    <ul><li><?= htmlspecialchars($error) ?></li></ul>
                </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                
                <div class="form-group">
                    <label>Địa chỉ Email</label>
                    <div class="input-icon-wrap">
                        <input type="email" name="email" value="<?= htmlspecialchars($oldEmail) ?>" required placeholder="VD: nguyenvanb@domain.com">
                        <i class="fas fa-envelope icon-left"></i>
                    </div>
                </div>

                <button type="submit" class="btn-submit" onclick="this.innerHTML='<span>Đang gửi...</span> <i class=\'fas fa-spinner fa-spin\'></i>'; this.style.pointerEvents='none'; this.form.submit();">
                    <span>Gửi mã OTP</span> <i class="fas fa-paper-plane"></i>
                </button>
            </form>
        </div>
    </div>
</body>
</html>
