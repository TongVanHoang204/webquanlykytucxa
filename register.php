<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'db_connect.php';

// ====== CSRF TOKEN ======
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$errors  = [];
$success = '';
// Giữ lại giá trị input khi lỗi
$old = [
    'fullname' => '',
    'username' => '',
    'email'    => '',
    'phone'    => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- CSRF CHECK ---
    if (!isset($_POST['_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['_token'])) {
        $errors[] = "Yêu cầu không hợp lệ (CSRF token không khớp). Vui lòng tải lại trang và thử lại.";
    } else {
        // --- Lấy & làm sạch dữ liệu ---
        $fullname = trim($_POST['fullname'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');
        $password_raw = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        $old['fullname'] = $fullname;
        $old['username'] = $username;
        $old['email']    = $email;
        $old['phone']    = $phone;

        // ====== VALIDATION SERVER-SIDE ======

        // Họ tên
        if ($fullname === '' || mb_strlen($fullname) < 3) {
            $errors[] = "Họ và tên phải có ít nhất 3 ký tự.";
        }

        // Username: chữ, số, _ . và 4–20 ký tự
        if (!preg_match('/^[a-zA-Z0-9_.]{4,20}$/', $username)) {
            $errors[] = "Tên đăng nhập chỉ được chứa chữ, số, dấu chấm và gạch dưới, từ 4–20 ký tự.";
        }

        // Email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Email không hợp lệ.";
        }

        // Phone: 10–11 số, bắt đầu bằng 0
        if (!preg_match('/^0[0-9]{9,10}$/', $phone)) {
            $errors[] = "Số điện thoại phải bắt đầu bằng 0 và có 10–11 chữ số.";
        }

        // Password strength
        if (strlen($password_raw) < 8) {
            $errors[] = "Mật khẩu phải có ít nhất 8 ký tự.";
        }
        if (!preg_match('/[A-Z]/', $password_raw)) {
            $errors[] = "Mật khẩu phải chứa ít nhất 1 chữ hoa (A–Z).";
        }
        if (!preg_match('/[a-z]/', $password_raw)) {
            $errors[] = "Mật khẩu phải chứa ít nhất 1 chữ thường (a–z).";
        }
        if (!preg_match('/[0-9]/', $password_raw)) {
            $errors[] = "Mật khẩu phải chứa ít nhất 1 chữ số (0–9).";
        }

        // Confirm password
        if ($password_raw !== $confirm_password) {
            $errors[] = "Xác nhận mật khẩu không khớp.";
        }

        // Nếu không có lỗi validation cơ bản thì kiểm tra trùng DB
        if (empty($errors)) {

            // Kiểm tra trùng username / email / phone
            $check = $conn->prepare("
                SELECT Username, Email, Phone 
                FROM Users 
                WHERE Username = ? OR Email = ? OR Phone = ?
            ");
            $check->bind_param("sss", $username, $email, $phone);
            $check->execute();
            $result = $check->get_result();

            while ($row = $result->fetch_assoc()) {
                if (strcasecmp($row['Username'], $username) === 0) {
                    $errors[] = "Tên đăng nhập đã tồn tại. Vui lòng chọn tên khác.";
                }
                if (strcasecmp($row['Email'], $email) === 0) {
                    $errors[] = "Email này đã được sử dụng. Vui lòng dùng email khác.";
                }
                if ($row['Phone'] === $phone) {
                    $errors[] = "Số điện thoại này đã được sử dụng.";
                }
            }
            $check->close();
        }

        // Nếu vẫn không có lỗi -> tiến hành tạo tài khoản
        if (empty($errors)) {
            $passwordHash = password_hash($password_raw, PASSWORD_DEFAULT);

            // Tạo Users
            $stmt = $conn->prepare("
                INSERT INTO Users (Username, PasswordHash, FullName, Email, Phone, Role)
                VALUES (?, ?, ?, ?, ?, 'Student')
            ");

            if (!$stmt) {
                $errors[] = "Lỗi hệ thống (không thể chuẩn bị câu lệnh Users).";
            } else {
                $stmt->bind_param("sssss", $username, $passwordHash, $fullname, $email, $phone);

                if ($stmt->execute()) {
                    $userId = $stmt->insert_id;
                    $stmt->close();

                    // Tạo mã sinh viên tự động
                    $studentCode = 'SV' . str_pad($userId, 3, '0', STR_PAD_LEFT);

                    // Thêm Students
                    $stmt2 = $conn->prepare("
    INSERT INTO Students (UserID, StudentCode, FullName, Gender, Phone)
    VALUES (?, ?, ?, 'Khác', ?)
");

if ($stmt2) {
    $stmt2->bind_param("isss", $userId, $studentCode, $fullname, $phone);
    $stmt2->execute();
    $stmt2->close();
}


                    // Reset form
                    $old = ['fullname'=>'','username'=>'','email'=>'','phone'=>''];
                    $success = "🎉 Đăng ký tài khoản thành công! Bạn có thể <a href=\"login.php\">đăng nhập ngay</a>.";
                    // Nếu muốn tự động chuyển sang trang login sau vài giây:
                    // header("Location: login.php?register=success");
                    // exit;
                } else {
                    $errors[] = "❌ Lỗi khi tạo tài khoản. Vui lòng thử lại.";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đăng ký tài khoản KTX</title>
    <link rel="stylesheet" href="assets/css/register.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-card animate-fadeIn">
            <div class="auth-header">
                <h2><i class="fa-solid fa-user-plus"></i> Đăng ký tài khoản Ký túc xá</h2>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error animate-shakeX">
                    <div class="alert-title"><i class="fa-solid fa-triangle-exclamation"></i> Có lỗi xảy ra</div>
                    <ul class="alert-list">
                        <?php foreach ($errors as $e): ?>
                            <li><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success animate-slideIn">
                    <div class="alert-title"><i class="fa-solid fa-circle-check"></i> Thành công</div>
                    <p><?= $success ?></p>
                </div>
            <?php endif; ?>

            <form id="registerForm" method="POST" novalidate>
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">

                <div class="form-row">
                    <div class="input-group">
                        <label for="fullname">Họ và tên</label>
                        <div class="input-with-icon">
                            <i class="fa-solid fa-id-card"></i>
                            <input type="text" id="fullname" name="fullname"
                                   value="<?= htmlspecialchars($old['fullname'], ENT_QUOTES, 'UTF-8') ?>"
                                   required minlength="3" placeholder="Nguyễn Văn A">
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="input-group">
                        <label for="email">Email</label>
                        <div class="input-with-icon">
                            <i class="fa-solid fa-envelope"></i>
                            <input type="email" id="email" name="email"
                                   value="<?= htmlspecialchars($old['email'], ENT_QUOTES, 'UTF-8') ?>"
                                   required placeholder="email@sv.truong.edu.vn">
                        </div>
                    </div>

                    <div class="input-group">
                        <label for="phone">Số điện thoại</label>
                        <div class="input-with-icon">
                            <i class="fa-solid fa-phone"></i>
                            <input type="tel" id="phone" name="phone"
                                   value="<?= htmlspecialchars($old['phone'], ENT_QUOTES, 'UTF-8') ?>"
                                   required placeholder="0xxxxxxxxx">
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="input-group">
                        <label for="username">Tên đăng nhập</label>
                        <div class="input-with-icon">
                            <i class="fa-solid fa-user"></i>
                            <input type="text" id="username" name="username"
                                   value="<?= htmlspecialchars($old['username'], ENT_QUOTES, 'UTF-8') ?>"
                                   required minlength="4" maxlength="20" placeholder="username_ktx">
                        </div>
                        <small class="hint">4–20 ký tự, chỉ gồm chữ, số, dấu chấm và gạch dưới.</small>
                    </div>
                </div>

                <div class="form-row">
                    <div class="input-group">
                        <label for="password">Mật khẩu</label>
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
                        <small class="hint">
                            Ít nhất 8 ký tự, gồm chữ hoa, chữ thường và số.
                        </small>
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

                <div id="clientErrorBox" class="alert alert-error hidden">
                    <div class="alert-title"><i class="fa-solid fa-triangle-exclamation"></i> Vui lòng kiểm tra lại</div>
                    <ul class="alert-list" id="clientErrorList"></ul>
                </div>

                <button type="submit" class="btn-primary">
                    <i class="fa-solid fa-paper-plane"></i> Đăng ký
                </button>

                 <!-- ========== HOẶC: ĐĂNG KÝ / ĐĂNG NHẬP NHANH ========== -->
            <div class="social-divider">
                <span></span>
                <p>Hoặc đăng ký nhanh bằng</p>
                <span></span>
            </div>

            <div class="social-buttons">
                <a href="http://localhost:8080/oauth_google.php" class="btn-social btn-google">
                    <i class="fa-brands fa-google"></i>
                    Google
                </a>
                <!-- Bạn muốn thêm mạng khác thì mở thêm dòng dưới -->
            </div>
            <!-- ========== END SOCIAL ========== --> 

                <p class="auth-footer">
                    Đã có tài khoản?
                    <a href="login.php"><i class="fa-solid fa-right-to-bracket"></i> Đăng nhập</a>
                </p>
            </form>
        </div>
    </div>

    <script>
        // ==== Toggle show/hide password ====
        document.querySelectorAll('.toggle-password').forEach(btn => {
            btn.addEventListener('click', function () {
                const targetId = this.getAttribute('data-target');
                const input = document.getElementById(targetId);
                const icon  = this.querySelector('i');
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

        // ==== Password strength indicator ====
        const passwordInput = document.getElementById('password');
        const strengthBar   = document.getElementById('passwordStrengthBar');

        function calcPasswordStrength(pw) {
            let score = 0;
            if (pw.length >= 8) score++;
            if (/[A-Z]/.test(pw)) score++;
            if (/[a-z]/.test(pw)) score++;
            if (/[0-9]/.test(pw)) score++;
            if (/[^A-Za-z0-9]/.test(pw)) score++; // ký tự đặc biệt (bonus)
            return score;
        }

        passwordInput.addEventListener('input', function () {
            const val = this.value;
            const s   = calcPasswordStrength(val);
            strengthBar.className = 'strength-bar'; // reset

            if (!val) {
                return;
            } else if (s <= 2) {
                strengthBar.classList.add('weak');
            } else if (s === 3 || s === 4) {
                strengthBar.classList.add('medium');
            } else {
                strengthBar.classList.add('strong');
            }
        });

        // ==== Real-time password match checker ====
        const confirmInput = document.getElementById('confirm_password');
        const matchMessage = document.getElementById('matchMessage');

        function checkMatch() {
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

        passwordInput.addEventListener('input', checkMatch);
        confirmInput.addEventListener('input', checkMatch);

        // ==== Client-side validation trước khi submit ====
        const form = document.getElementById('registerForm');
        const clientErrorBox  = document.getElementById('clientErrorBox');
        const clientErrorList = document.getElementById('clientErrorList');

        form.addEventListener('submit', function (e) {
            clientErrorList.innerHTML = '';
            clientErrorBox.classList.add('hidden');
            let clientErrors = [];

            const fullname = document.getElementById('fullname').value.trim();
            const username = document.getElementById('username').value.trim();
            const email    = document.getElementById('email').value.trim();
            const phone    = document.getElementById('phone').value.trim();
            const pw       = passwordInput.value;
            const pw2      = confirmInput.value;

            if (fullname.length < 3) {
                clientErrors.push('Họ và tên phải có ít nhất 3 ký tự.');
            }

            if (!/^[a-zA-Z0-9_.]{4,20}$/.test(username)) {
                clientErrors.push('Tên đăng nhập không hợp lệ.');
            }

            // Kiểm tra email sơ bộ
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                clientErrors.push('Email không hợp lệ.');
            }

            if (!/^0[0-9]{9,10}$/.test(phone)) {
                clientErrors.push('Số điện thoại không hợp lệ.');
            }

            // Password
            if (pw.length < 8) clientErrors.push('Mật khẩu phải có ít nhất 8 ký tự.');
            if (!/[A-Z]/.test(pw)) clientErrors.push('Mật khẩu phải có ít nhất 1 chữ hoa.');
            if (!/[a-z]/.test(pw)) clientErrors.push('Mật khẩu phải có ít nhất 1 chữ thường.');
            if (!/[0-9]/.test(pw)) clientErrors.push('Mật khẩu phải có ít nhất 1 chữ số.');

            if (pw !== pw2) {
                clientErrors.push('Xác nhận mật khẩu không khớp.');
            }

            if (clientErrors.length > 0) {
                e.preventDefault();
                clientErrors.forEach(msg => {
                    const li = document.createElement('li');
                    li.textContent = msg;
                    clientErrorList.appendChild(li);
                });
                clientErrorBox.classList.remove('hidden');
                // hiệu ứng lắc
                const card = document.querySelector('.auth-card');
                card.classList.remove('animate-shakeX');
                void card.offsetWidth; // force reflow
                card.classList.add('animate-shakeX');
            }
        });
    </script>
</body>
</html>
