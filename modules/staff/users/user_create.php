<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../../db_connect.php';
require_once '../../../includes/auth_check.php';
requireRole(['Admin']); // chỉ Admin/Manager được tạo user

$currentUserRole = $_SESSION['Role'] ?? '';
// ---- Cấu hình vai trò hợp lệ (đã loại bỏ Staff)
$ALLOWED_ROLES = ['Admin', 'Manager', 'Student'];

$errors = [];
$okMsg  = null;

// ---- Xử lý submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Lấy dữ liệu & làm sạch
    $username = trim($_POST['Username'] ?? '');
    $fullname = trim($_POST['FullName'] ?? '');
    $email    = trim($_POST['Email'] ?? '');
    $phone    = trim($_POST['Phone'] ?? '');
    $rolePost = trim($_POST['Role'] ?? 'Student');

    // Nếu là Manager => luôn chỉ được tạo tài khoản Student
    if ($currentUserRole === 'Manager') {
        $role = 'Student';
    } else {
        // Admin thì lấy theo form (nhưng vẫn check hợp lệ)
        $role = $rolePost !== '' ? $rolePost : 'Student';
    }
    $password = $_POST['Password'] ?? '';
    $confirm  = $_POST['ConfirmPassword'] ?? '';

    // ----- VALIDATION phía server (quyết định cho/không cho tạo)
    // 1) Bắt buộc
    if ($username === '' || $fullname === '' || $password === '' || $confirm === '') {
        $errors[] = 'Vui lòng nhập đầy đủ các trường bắt buộc (*).';
    }
    // 2) Username: 4–32 ký tự, chỉ a-z 0-9 _ . -
    if ($username !== '' && !preg_match('/^[a-z0-9_.-]{4,32}$/i', $username)) {
        $errors[] = 'Tên đăng nhập chỉ gồm chữ/số/._- và dài 4–32 ký tự.';
    }
    // 3) Họ tên: tối thiểu 2 ký tự
    if ($fullname !== '' && mb_strlen($fullname) < 2) {
        $errors[] = 'Họ tên phải từ 2 ký tự trở lên.';
    }
    // 4) Email (nếu nhập) phải hợp lệ
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email không hợp lệ.';
    }
    // 5) Số điện thoại (nếu nhập): 9–15 chữ số, cho phép + ở đầu
    if ($phone !== '' && !preg_match('/^\+?[0-9]{9,15}$/', $phone)) {
        $errors[] = 'Số điện thoại không hợp lệ.';
    }
    // 6) Role hợp lệ
    if (!in_array($role, $ALLOWED_ROLES, true)) {
        $errors[] = 'Vai trò không hợp lệ.';
    }
    // 7) Mật khẩu đủ mạnh: ≥8 ký tự, có chữ hoa, chữ thường, số
    $passStrong = (
        strlen($password) >= 8 &&
        preg_match('/[a-z]/', $password) &&
        preg_match('/[A-Z]/', $password) &&
        preg_match('/\d/', $password)
    );
    if (!$passStrong) {
        $errors[] = 'Mật khẩu phải ≥8 ký tự và có chữ hoa, chữ thường, số.';
    }
    // 8) Xác nhận mật khẩu
    if ($password !== $confirm) {
        $errors[] = 'Xác nhận mật khẩu không khớp.';
    }

    // 9) Trùng username/email?
    if (!$errors) {
        // Kiểm tra username
        $stm = $conn->prepare("SELECT 1 FROM Users WHERE Username=? LIMIT 1");
        if (!$stm) $errors[] = 'Lỗi hệ thống (prepare username).';
        else {
            $stm->bind_param('s', $username);
            $stm->execute();
            $stm->store_result();
            if ($stm->num_rows > 0) $errors[] = 'Tên đăng nhập đã tồn tại.';
            $stm->close();
        }

        // Kiểm tra email nếu có
        if ($email !== '' && !$errors) {
            $stm = $conn->prepare("SELECT 1 FROM Users WHERE Email=? LIMIT 1");
            if (!$stm) $errors[] = 'Lỗi hệ thống (prepare email).';
            else {
                $stm->bind_param('s', $email);
                $stm->execute();
                $stm->store_result();
                if ($stm->num_rows > 0) $errors[] = 'Email đã tồn tại.';
                $stm->close();
            }
        }
    }

    // 10) Nếu hợp lệ → tạo
    if (!$errors) {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        // Nếu cột CreatedAt có default CURRENT_TIMESTAMP thì có thể bỏ trường này trong INSERT
        $stm = $conn->prepare("
            INSERT INTO Users (Username, PasswordHash, FullName, Email, Phone, Role, CreatedAt)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        if (!$stm) {
            $errors[] = 'Lỗi hệ thống (prepare insert): ' . $conn->error;
        } else {
            $stm->bind_param('ssssss', $username, $hash, $fullname, $email, $phone, $role);
            if ($stm->execute()) {
                $_SESSION['message'] = "✅ Đã tạo người dùng mới: {$fullname} ({$username})";
                $_SESSION['message_type'] = "success";
                header("Location: /modules/staff/users/users.php");
                exit;
            } else {
                $errors[] = 'Không thể tạo người dùng: ' . $conn->error;
            }
            $stm->close();
        }
    }
}
require_once '../../../includes/admin_header.php';
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Thêm người dùng mới | Quản lý KTX</title>
    <link rel="stylesheet" href="../../assets/css/admin/admin_header.css">
    <link rel="stylesheet" href="../../../assets/css/staff/users/user_create.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>
    <div class="wrap">
        <div class="header">
            <h2><i class="fa-solid fa-user-plus"></i> Thêm người dùng mới</h2>
            <a class="btn back" href="/modules/staff/dashboard.php"><i class="fa-solid fa-arrow-left"></i> Danh sách</a>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert error">
                <ul>
                    <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" class="form" id="userCreateForm" autocomplete="off">
            <div class="grid">
                <div class="col">
                    <label>Tên đăng nhập* (4–32, a-z0-9._-)</label>
                    <div class="with-indicator">
                        <input type="text" name="Username" id="Username" required value="<?= htmlspecialchars($_POST['Username'] ?? '') ?>">
                        <span id="u-ind" class="indicator"></span>
                    </div>
                </div>
                <div class="col">
                    <label>Họ và tên*</label>
                    <input type="text" name="FullName" required value="<?= htmlspecialchars($_POST['FullName'] ?? '') ?>">
                </div>
            </div>

            <div class="grid">
                <div class="col">
                    <label>Email</label>
                    <div class="with-indicator">
                        <input type="email" name="Email" id="Email" value="<?= htmlspecialchars($_POST['Email'] ?? '') ?>">
                        <span id="e-ind" class="indicator"></span>
                    </div>
                </div>
                <div class="col">
                    <label>Điện thoại</label>
                    <input type="text" name="Phone" placeholder="+84901234567" value="<?= htmlspecialchars($_POST['Phone'] ?? '') ?>">
                </div>
            </div>

            <div class="grid">
                <div class="col">
                    <label>Vai trò*</label>
                    <?php if ($currentUserRole === 'Admin'): ?>
                        <select name="Role" required>
                            <?php
                            $chosen = $_POST['Role'] ?? 'Student';
                            foreach ($ALLOWED_ROLES as $r) {
                                $sel = $r === $chosen ? 'selected' : '';
                                echo "<option value=\"$r\" $sel>$r</option>";
                            }
                            ?>
                        </select>
                    <?php else: ?>
                        <!-- Manager: khóa cứng Student, không cho đổi -->
                        <input type="hidden" name="Role" value="Student">
                        <input type="text" value="Student" disabled class="readonly-role">
                        <small class="muted">Manager chỉ được tạo tài khoản sinh viên.</small>
                    <?php endif; ?>
                </div>
                <div class="col"></div>
            </div>

            <div class="grid">
                <div class="col">
                    <label>Mật khẩu* (≥8, có a-z, A-Z, số)</label>
                    <div class="with-eye">
                        <input type="password" name="Password" id="Password" required>
                        <i class="fa-regular fa-eye" id="togglePass"></i>
                    </div>
                    <div class="strength" id="strengthBar"><span></span></div>
                </div>
                <div class="col">
                    <label>Nhập lại mật khẩu*</label>
                    <div class="with-eye">
                        <input type="password" name="ConfirmPassword" id="ConfirmPassword" required>
                        <i class="fa-regular fa-eye" id="togglePass2"></i>
                    </div>
                    <small id="matchHint" class="muted"></small>
                </div>
            </div>

            <div class="actions">
                <button type="submit" class="btn primary" id="submitBtn"><i class="fa-solid fa-floppy-disk"></i> Tạo người dùng</button>
                <button type="reset" class="btn"><i class="fa-solid fa-rotate-left"></i> Nhập lại</button>
            </div>
        </form>
    </div>

    <script>
        // Hiển thị/ẩn mật khẩu với hiệu ứng
        const togglePassword = (btnId, inputId) => {
            const btn = document.getElementById(btnId);
            const input = document.getElementById(inputId);

            btn.addEventListener('click', () => {
                const isPassword = input.type === 'password';
                input.type = isPassword ? 'text' : 'password';
                btn.className = isPassword ? 'fa-regular fa-eye-slash' : 'fa-regular fa-eye';

                // Thêm hiệu ứng
                btn.style.transform = 'scale(1.2)';
                setTimeout(() => {
                    btn.style.transform = 'scale(1)';
                }, 200);
            });
        };

        togglePassword('togglePass', 'Password');
        togglePassword('togglePass2', 'ConfirmPassword');

        // Thanh đo độ mạnh mật khẩu nâng cao
        function calculateStrength(pw) {
            let score = 0;

            // Độ dài
            if (pw.length >= 8) score++;
            if (pw.length >= 12) score++;

            // Đa dạng ký tự
            if (/[a-z]/.test(pw)) score++;
            if (/[A-Z]/.test(pw)) score++;
            if (/\d/.test(pw)) score++;
            if (/[^a-zA-Z0-9]/.test(pw)) score++;

            return Math.min(score, 4); // 0-4
        }

        function updateStrengthBar(password) {
            const strength = calculateStrength(password);
            const bar = $('#strengthBar span');
            const labels = ['Rất yếu', 'Yếu', 'Trung bình', 'Mạnh', 'Rất mạnh'];
            const colors = ['#ef4444', '#f59e0b', '#eab308', '#84cc16', '#22c55e'];

            bar.attr('data-score', strength);
            bar.attr('title', labels[strength]);

            // Cập nhật tooltip
            bar.css('background-color', colors[strength]);
        }

        $('#Password').on('input', function() {
            updateStrengthBar(this.value);
        });

        // Kiểm tra khớp mật khẩu với hiệu ứng
        function checkPasswordMatch() {
            const password = $('#Password').val();
            const confirm = $('#ConfirmPassword').val();
            const hint = $('#matchHint');

            if (!password && !confirm) {
                hint.text('').removeClass('match no-match');
                return;
            }

            if (confirm && password === confirm) {
                hint.text('✅ Mật khẩu khớp').addClass('match').removeClass('no-match');
            } else if (confirm) {
                hint.text('❌ Mật khẩu không khớp').addClass('no-match').removeClass('match');
            } else {
                hint.text('↳ Nhập lại mật khẩu').removeClass('match no-match');
            }
        }

        $('#ConfirmPassword, #Password').on('input', checkPasswordMatch);

        // Kiểm tra trùng username/email với debounce và hiệu ứng
        let usernameTimeout, emailTimeout;

        function checkAvailability(type, value, indicatorId) {
            if (!value) {
                $(`#${indicatorId}`).removeClass('ok bad checking').text('');
                return;
            }

            $(`#${indicatorId}`).addClass('checking').text('⏳').removeClass('ok bad');

            $.post('user_check_api.php', {
                [type]: value
            }, function(res) {
                const indicator = $(`#${indicatorId}`);
                if (res && res[`${type}_taken`]) {
                    indicator.removeClass('checking').addClass('bad').text('❌ Đã tồn tại');
                } else {
                    indicator.removeClass('checking').addClass('ok').text('✅ Khả dụng');
                }
            }, 'json').fail(function() {
                $(`#${indicatorId}`).removeClass('checking').addClass('bad').text('⚠️ Lỗi kiểm tra');
            });
        }

        $('#Username').on('input', function() {
            clearTimeout(usernameTimeout);
            const value = this.value.trim();
            usernameTimeout = setTimeout(() => {
                checkAvailability('username', value, 'u-ind');
            }, 500);
        });

        $('#Email').on('input', function() {
            clearTimeout(emailTimeout);
            const value = this.value.trim();
            emailTimeout = setTimeout(() => {
                checkAvailability('email', value, 'e-ind');
            }, 500);
        });

        // Real-time validation
        $('#Username').on('input', function() {
            const value = this.value;
            const isValid = /^[a-z0-9_.-]{4,32}$/i.test(value);
            if (value && !isValid) {
                this.setCustomValidity('Tên đăng nhập chỉ gồm chữ/số/._- và dài 4–32 ký tự');
            } else {
                this.setCustomValidity('');
            }
        });

        // Ngăn submit nếu có lỗi
        $('#userCreateForm').on('submit', function(e) {
            const hasErrors = $('#u-ind').hasClass('bad') || $('#e-ind').hasClass('bad');
            const password = $('#Password').val();
            const confirm = $('#ConfirmPassword').val();

            if (hasErrors) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Không thể tạo tài khoản',
                    text: 'Vui lòng kiểm tra lại thông tin đã nhập!',
                    confirmButtonColor: '#ef4444'
                });
                return false;
            }

            if (password !== confirm) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Mật khẩu không khớp',
                    text: 'Vui lòng nhập lại mật khẩu xác nhận!',
                    confirmButtonColor: '#f59e0b'
                });
                return false;
            }

            // Hiệu ứng loading
            const submitBtn = $('#submitBtn');
            submitBtn.addClass('loading');
            submitBtn.html('<i class="fa-solid fa-spinner fa-spin"></i> Đang tạo người dùng...');
            submitBtn.prop('disabled', true);
        });

        // Reset form
        $('button[type="reset"]').on('click', function() {
            setTimeout(() => {
                $('.indicator').removeClass('ok bad checking').text('');
                $('#strengthBar span').attr('data-score', '0').css('width', '0%');
                $('#matchHint').text('').removeClass('match no-match');
                $('#submitBtn').prop('disabled', false).removeClass('loading')
                    .html('<i class="fa-solid fa-floppy-disk"></i> Tạo người dùng');
            }, 100);
        });

        // Auto-focus vào trường đầu tiên
        $(document).ready(function() {
            $('#Username').focus();
        });
    </script>
</body>

</html>