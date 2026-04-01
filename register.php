<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'db_connect.php';

// CSRF TOKEN
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$errors  = [];
$success = '';
$old = ['fullname'=>'', 'username'=>'', 'email'=>'', 'phone'=>''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['_token'])) {
        $errors[] = "Yêu cầu không hợp lệ (CSRF). Vui lòng tải lại trang.";
    } else {
        $fullname = trim($_POST['fullname'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');
        $password_raw = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        $old = ['fullname'=>$fullname, 'username'=>$username, 'email'=>$email, 'phone'=>$phone];

        if ($fullname === '' || mb_strlen($fullname) < 3) $errors[] = "Họ và tên ít nhất 3 ký tự.";
        if (!preg_match('/^[a-zA-Z0-9_.]{4,20}$/', $username)) $errors[] = "Tên đăng nhập 4-20 ký tự (chữ, số, _, .).";
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Email không hợp lệ.";
        if (!preg_match('/^0[0-9]{9,10}$/', $phone)) $errors[] = "SĐT 10-11 số, bắt đầu bằng 0.";
        if (strlen($password_raw) < 8) $errors[] = "Mật khẩu ít nhất 8 ký tự.";
        if (!preg_match('/[A-Z]/', $password_raw)) $errors[] = "Mật khẩu cần 1 chữ hoa.";
        if (!preg_match('/[a-z]/', $password_raw)) $errors[] = "Mật khẩu cần 1 chữ thường.";
        if (!preg_match('/[0-9]/', $password_raw)) $errors[] = "Mật khẩu cần 1 chữ số.";
        if ($password_raw !== $confirm_password) $errors[] = "Xác nhận mật khẩu không khớp.";

        if (empty($errors)) {
            $check = $conn->prepare("SELECT Username, Email, Phone FROM Users WHERE Username = ? OR Email = ? OR Phone = ?");
            $check->bind_param("sss", $username, $email, $phone);
            $check->execute();
            $result = $check->get_result();
            while ($row = $result->fetch_assoc()) {
                if (strcasecmp($row['Username'], $username) === 0) $errors[] = "Tên đăng nhập đã tồn tại.";
                if (strcasecmp($row['Email'], $email) === 0) $errors[] = "Email đã được sử dụng.";
                if ($row['Phone'] === $phone) $errors[] = "Số điện thoại đã được sử dụng.";
            }
            $check->close();
        }

        if (empty($errors)) {
            $passwordHash = password_hash($password_raw, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO Users (Username, PasswordHash, FullName, Email, Phone, Role) VALUES (?, ?, ?, ?, ?, 'Student')");
            if (!$stmt) {
                $errors[] = "Lỗi hệ thống Database.";
            } else {
                $stmt->bind_param("sssss", $username, $passwordHash, $fullname, $email, $phone);
                if ($stmt->execute()) {
                    $userId = $stmt->insert_id;
                    $stmt->close();
                    
                    $studentCode = 'SV' . str_pad($userId, 4, '0', STR_PAD_LEFT);
                    $stmt2 = $conn->prepare("INSERT INTO Students (UserID, StudentCode, FullName, Gender, Phone) VALUES (?, ?, ?, 'Khác', ?)");
                    if ($stmt2) {
                        $stmt2->bind_param("isss", $userId, $studentCode, $fullname, $phone);
                        $stmt2->execute();
                        $stmt2->close();
                    }
                    $old = ['fullname'=>'', 'username'=>'', 'email'=>'', 'phone'=>''];
                    $success = "Đăng ký thành công! Đang chuyển hướng đến đăng nhập...";
                    echo "<script>setTimeout(() => window.location.href='login.php', 2000);</script>";
                } else {
                    $errors[] = "Lỗi khi tạo tài khoản. Thử lại sau.";
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng ký | Ký túc xá</title>
    
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/vendor/fontawesome/css/all.min.css">
    
    <!-- Premium Bento Auth CSS -->
    <link rel="stylesheet" href="assets/css/auth_bento.css">
    
    <!-- Custom layout tweaks for register page to look balanced -->
    <style>
        .auth-container { flex-direction: row-reverse; height: auto; max-height: 90vh; }
        .auth-brand { background: linear-gradient(135deg, #10b981 0%, #3b82f6 100%); }
    </style>
</head>
<body>
    <!-- Animated Background Blobs -->
    <div class="blob-shape blob-1"></div>
    <div class="blob-shape blob-2"></div>
    <div class="blob-shape blob-3"></div>
    
    <div class="auth-container">
        <!-- Brand Side -->
        <div class="auth-brand">
            <div class="brand-top">
                <div class="brand-logo"><i class="fas fa-user-plus"></i></div>
                <h1>Bắt đầu ngay<br>hôm nay</h1>
                <p>1 phút đăng ký tài khoản và trải nghiệm quản lý ký túc xá trực tuyến hiện đại bậc nhất.</p>
            </div>
            <div class="brand-bottom" style="background: rgba(255,255,255,0.15); border-color: rgba(255,255,255,0.2);">
                <i class="fas fa-check-circle" style="color: #fff;"></i> Tối ưu trải nghiệm sinh viên
            </div>
        </div>

        <!-- Form Side -->
        <div class="auth-form-wrap">
            <h2>Tạo tài khoản mới</h2>
            <p>Vui lòng điền thông tin bên dưới để đăng ký</p>

            <?php if (!empty($errors)): ?>
                <div class="auth-alert error">
                    <i class="fas fa-exclamation-circle"></i>
                    <ul><?php foreach ($errors as $e) echo "<li>".htmlspecialchars($e)."</li>"; ?></ul>
                </div>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
                 <div class="auth-alert success"><i class="fas fa-check-circle"></i> <?= $success ?></div>
            <?php endif; ?>

            <form method="POST" id="registerForm">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Họ và tên</label>
                        <div class="input-icon-wrap">
                            <input type="text" name="fullname" value="<?= htmlspecialchars($old['fullname']) ?>" required placeholder="Vd: Nguyễn Văn A">
                            <i class="fas fa-id-card icon-left"></i>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Số điện thoại</label>
                        <div class="input-icon-wrap">
                            <input type="tel" name="phone" value="<?= htmlspecialchars($old['phone']) ?>" required placeholder="0xxxxxxxxx">
                            <i class="fas fa-phone icon-left"></i>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Email</label>
                    <div class="input-icon-wrap">
                        <input type="email" name="email" value="<?= htmlspecialchars($old['email']) ?>" required placeholder="email@truong.edu.vn">
                        <i class="fas fa-envelope icon-left"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label>Tên đăng nhập</label>
                    <div class="input-icon-wrap">
                        <input type="text" name="username" value="<?= htmlspecialchars($old['username']) ?>" required placeholder="user_ktx (4-20 ký tự)">
                        <i class="fas fa-user icon-left"></i>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Mật khẩu</label>
                        <div class="input-icon-wrap">
                            <input type="password" id="password" name="password" required placeholder="Tạo mật khẩu">
                            <i class="fas fa-lock icon-left"></i>
                            <button type="button" class="toggle-pw" onclick="togglePw('password')"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Xác nhận mật khẩu</label>
                        <div class="input-icon-wrap">
                            <input type="password" id="confirm_password" name="confirm_password" required placeholder="Nhập lại mật khẩu">
                            <i class="fas fa-lock icon-left"></i>
                            <button type="button" class="toggle-pw" onclick="togglePw('confirm_password')"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-submit">
                    <span>Đăng ký tài khoản</span> <i class="fas fa-check"></i>
                </button>

                <!-- Nút đăng nhập phụ / Google (Tùy chọn) -->
                <div class="social-divider">
                    <span></span>
                    <p>hoặc</p>
                    <span></span>
                </div>

                <a href="http://localhost/oauth_google.php" class="btn-social btn-google">
                    <img src="https://www.svgrepo.com/show/475656/google-color.svg" alt="Google">
                    Đăng ký bằng Google
                </a>

                <div class="auth-footer">
                    Đã có tài khoản? <a href="login.php">Đăng nhập</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Toggle PW
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

        // Fix Form Submit State
        document.getElementById('registerForm').addEventListener('submit', function() {
            const btn = this.querySelector('.btn-submit');
            btn.innerHTML = '<span>Đang xử lý</span> <i class="fas fa-circle-notch fa-spin"></i>';
            btn.style.pointerEvents = 'none';
            btn.style.opacity = '0.8';
        });
    </script>
</body>
</html>
