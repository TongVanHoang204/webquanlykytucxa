<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'db_connect.php';

$error   = '';
$success = '';
$userId  = null;

$token = $_GET['token'] ?? '';

// ===== 1. Kiểm tra token trong URL =====
if (!$token) {
    $error = "Liên kết không hợp lệ hoặc thiếu token. Vui lòng yêu cầu đặt lại mật khẩu lại từ trang Quên mật khẩu.";
} else {
    // ===== 2. Kiểm tra token trong database còn hạn không =====
    $stmt = $conn->prepare("
        SELECT pr.UserID 
        FROM password_resets pr
        WHERE BINARY pr.Token = ? AND pr.ExpiresAt > NOW()
        LIMIT 1
    ");

    if ($stmt) {
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($row = $res->fetch_assoc()) {
            $userId = (int)$row['UserID'];
        } else {
            // Không có bản ghi phù hợp
            $error = "Liên kết đặt lại mật khẩu đã hết hạn hoặc không hợp lệ. Vui lòng gửi yêu cầu mới từ trang Quên mật khẩu.";
        }
        $stmt->close();
    } else {
        $error = "Không thể kiểm tra liên kết. Vui lòng thử lại sau.";
    }
}

// ===== 3. Xử lý đổi mật khẩu khi POST (và token hợp lệ) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $userId !== null && empty($error)) {
    $pw1 = $_POST['password'] ?? '';
    $pw2 = $_POST['confirm_password'] ?? '';

    // Validation server-side
    if ($pw1 !== $pw2) {
        $error = "Mật khẩu xác nhận không khớp.";
    } elseif (
        strlen($pw1) < 8 ||
        !preg_match('/[A-Z]/', $pw1) ||
        !preg_match('/[a-z]/', $pw1) ||
        !preg_match('/[0-9]/', $pw1)
    ) {
        $error = "Mật khẩu phải có ít nhất 8 ký tự, gồm chữ hoa, chữ thường và số.";
    } else {
        $hash = password_hash($pw1, PASSWORD_DEFAULT);

        // Cập nhật mật khẩu
        $stmt2 = $conn->prepare("UPDATE Users SET PasswordHash = ? WHERE UserID = ?");
        if ($stmt2) {
            $stmt2->bind_param("si", $hash, $userId);
            if ($stmt2->execute()) {

                // Xóa token đã dùng
                $del = $conn->prepare("DELETE FROM password_resets WHERE UserID = ?");
                if ($del) {
                    $del->bind_param("i", $userId);
                    $del->execute();
                    $del->close();
                }

                $success = "Đặt lại mật khẩu thành công. Bạn có thể <a href='login.php'>đăng nhập ngay</a>.";
            } else {
                $error = "Không thể cập nhật mật khẩu. Vui lòng thử lại.";
            }
            $stmt2->close();
        } else {
            $error = "Không thể chuẩn bị câu lệnh cập nhật mật khẩu.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8" />
    <title>Đặt lại mật khẩu</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="assets/css/login.css" />
    <link rel="stylesheet" href="assets/css/auth_shell.css" />
    <script src="https://kit.fontawesome.com/a2e0e6d10f.js" crossorigin="anonymous"></script>
</head>

<body>
    <div class="auth-wrapper">
        <div class="auth-card animate-fadeIn">
            <div class="auth-header">
                <h2><i class="fa-solid fa-key"></i> Đặt lại mật khẩu</h2>
                <p>Tạo mật khẩu mới cho tài khoản của bạn.</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error animate-shakeX">
                    <div class="alert-title"><i class="fa-solid fa-triangle-exclamation"></i> Lỗi</div>
                    <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php if ($userId === null): ?>
                        <p class="hint">
                            <a href="forgot_password.php"><i class="fa-solid fa-unlock-keyhole"></i> Gửi lại yêu cầu quên mật khẩu</a>
                        </p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success animate-slideIn">
                    <div class="alert-title"><i class="fa-solid fa-circle-check"></i> Thành công</div>
                    <p><?= $success ?></p>
                </div>
            <?php elseif ($userId !== null && empty($success)): ?>

                <form id="resetForm" method="POST" novalidate>
                    <div class="form-row">
                        <div class="input-group">
                            <label for="password">Mật khẩu mới</label>
                            <div class="input-with-icon password-wrapper">
                                <i class="fa-solid fa-lock"></i>
                                <input type="password" id="password" name="password" required>
                                <button type="button" class="toggle-password" data-target="password">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                            </div>
                            <div class="password-strength">
                                <div class="strength-bar" id="passwordStrengthBar"></div>
                            </div>
                            <small class="hint">Ít nhất 8 ký tự, có chữ hoa, chữ thường và số.</small>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="input-group">
                            <label for="confirm_password">Xác nhận mật khẩu</label>
                            <div class="input-with-icon password-wrapper">
                                <i class="fa-solid fa-lock"></i>
                                <input type="password" id="confirm_password" name="confirm_password" required>
                                <button type="button" class="toggle-password" data-target="confirm_password">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                            </div>
                            <small id="matchMessage" class="hint"></small>
                        </div>
                    </div>

                    <button type="submit" class="btn-primary">
                        <i class="fa-solid fa-floppy-disk"></i> Cập nhật mật khẩu
                    </button>

                    <p class="auth-footer">
                        Nhớ mật khẩu cũ?
                        <a href="login.php"><i class="fa-solid fa-right-to-bracket"></i> Quay lại đăng nhập</a>
                    </p>
                </form>

            <?php endif; ?>
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

        // Password strength
        const passwordInput = document.getElementById('password');
        const strengthBar = document.getElementById('passwordStrengthBar');
        const confirmInput = document.getElementById('confirm_password');
        const matchMessage = document.getElementById('matchMessage');

        function calcPasswordStrength(pw) {
            let score = 0;
            if (pw.length >= 8) score++;
            if (/[A-Z]/.test(pw)) score++;
            if (/[a-z]/.test(pw)) score++;
            if (/[0-9]/.test(pw)) score++;
            if (/[^A-Za-z0-9]/.test(pw)) score++;
            return score;
        }

        if (passwordInput && strengthBar) {
            passwordInput.addEventListener('input', function() {
                const val = this.value;
                const s = calcPasswordStrength(val);
                strengthBar.className = 'strength-bar';
                if (!val) return;
                if (s <= 2) {
                    strengthBar.classList.add('weak');
                } else if (s <= 4) {
                    strengthBar.classList.add('medium');
                } else {
                    strengthBar.classList.add('strong');
                }
                checkMatch();
            });
        }

        function checkMatch() {
            if (!confirmInput || !matchMessage || !passwordInput) return;
            if (!confirmInput.value && !passwordInput.value) {
                matchMessage.textContent = '';
                matchMessage.classList.remove('error', 'success');
                return;
            }
            if (confirmInput.value === passwordInput.value) {
                matchMessage.textContent = '✅ Mật khẩu trùng khớp.';
                matchMessage.classList.remove('error');
                matchMessage.classList.add('success');
            } else {
                matchMessage.textContent = '❌ Mật khẩu không trùng khớp.';
                matchMessage.classList.remove('success');
                matchMessage.classList.add('error');
            }
        }

        if (confirmInput) {
            confirmInput.addEventListener('input', checkMatch);
        }

        // Client-side validation
        const form = document.getElementById('resetForm');
        if (form) {
            form.addEventListener('submit', function(e) {
                const pw1 = passwordInput.value;
                const pw2 = confirmInput.value;
                let errs = [];

                if (pw1.length < 8) errs.push("Mật khẩu phải có ít nhất 8 ký tự.");
                if (!/[A-Z]/.test(pw1)) errs.push("Mật khẩu phải có chữ hoa.");
                if (!/[a-z]/.test(pw1)) errs.push("Mật khẩu phải có chữ thường.");
                if (!/[0-9]/.test(pw1)) errs.push("Mật khẩu phải có chữ số.");
                if (pw1 !== pw2) errs.push("Mật khẩu xác nhận không khớp.");

                if (errs.length > 0) {
                    e.preventDefault();
                    alert(errs.join("\\n"));
                }
            });
        }
    </script>
</body>

</html>
