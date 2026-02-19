<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start(); // Bắt đầu session nếu chưa có
}

// ❌ KHÔNG tự động redirect ở đây - để các trang tự quyết định
// Chỉ định nghĩa các hàm kiểm tra, không tự động chặn

function requireRole($roles = []) {
    if (!isset($_SESSION['Role']) || !in_array($_SESSION['Role'], $roles)) {
        echo "<div style='color:red; text-align:center; margin-top:20px;'>
                ❌ Bạn không có quyền truy cập trang này.
              </div>";
        include __DIR__ . '/footer.php';
        exit;
    }
}

function requireLogin() {
    if (!isset($_SESSION['UserID'])) {
        header("Location: /login.php");
        exit();
    }
}
function requireAdmin() {
    if (!isset($_SESSION['Role']) || $_SESSION['Role'] !== 'Admin') {
        echo "<div style='color:red; text-align:center; margin-top:20px;'>
                ❌ Bạn không có quyền truy cập trang này.
              </div>";
        include __DIR__ . '/footer.php';
        exit;
    }
}

?>
