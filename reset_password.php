<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'db_connect.php';

$error = '';
$success = '';

$token = $_GET['token'] ?? '';

if (!$token) {
    die("Liên kết không hợp lệ.");
}

// kiểm tra token
$stmt = $conn->prepare("
    SELECT pr.UserID 
    FROM password_resets pr
    WHERE Token = ? AND ExpiresAt > NOW()
    LIMIT 1
");
$stmt->bind_param("s", $token);
$stmt->execute();
$res = $stmt->get_result();

if (!($row = $res->fetch_assoc())) {
    die("Liên kết đã hết hạn hoặc không hợp lệ.");
}

$userId = $row['UserID'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pw1 = $_POST['password'] ?? '';
    $pw2 = $_POST['confirm_password'] ?? '';

    if ($pw1 !== $pw2) {
        $error = "Mật khẩu không khớp.";
    } elseif (strlen($pw1) < 8) {
        $error = "Mật khẩu phải ít nhất 8 ký tự.";
    } else {
        $hash = password_hash($pw1, PASSWORD_DEFAULT);

        // update password
        $stmt2 = $conn->prepare("UPDATE Users SET PasswordHash=? WHERE UserID=?");
        $stmt2->bind_param("si", $hash, $userId);
        $stmt2->execute();

        // Xóa token
        $conn->query("DELETE FROM password_resets WHERE UserID=$userId");

        $success = "Đặt lại mật khẩu thành công. <a href='login.php'>Đăng nhập ngay</a>.";
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đặt lại mật khẩu</title>
    <link rel="stylesheet" href="assets/css/login.css">
    <link rel="stylesheet" href="assets/css/auth_shell.css">
</head>
<body>

<div class="auth-wrapper">
    <div class="auth-card animate-fadeIn">

        <h2><i class="fa-solid fa-key"></i> Đặt lại mật khẩu</h2>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= $error ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php else: ?>

        <form method="POST">
            <label>Mật khẩu mới</label>
            <input type="password" name="password" required>

            <label>Xác nhận mật khẩu</label>
            <input type="password" name="confirm_password" required>

            <button type="submit" class="btn-primary">Cập nhật</button>
        </form>

        <?php endif; ?>

    </div>
</div>

</body>
</html>
