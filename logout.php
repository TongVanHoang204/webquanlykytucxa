<?php
// Bắt đầu hoặc khôi phục session hiện tại
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ✅ Xóa toàn bộ dữ liệu session
$_SESSION = []; // Xóa tất cả biến trong session

// ✅ Xóa cookie session (nếu có)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// ✅ Hủy session hoàn toàn
session_destroy();

// ✅ Điều hướng về trang chủ hoặc trang đăng nhập
header("Location: /login.php");
exit;
?>
