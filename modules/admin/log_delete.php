<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../includes/auth_check.php';
requireRole(['Admin', 'Manager']);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

// Chỉ cho phép POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: log.php');
    exit;
}

// CSRF check
$csrf = $_POST['_csrf'] ?? '';
if (empty($_SESSION['_csrf']) || !hash_equals($_SESSION['_csrf'], $csrf)) {
    die('CSRF token không hợp lệ.');
}

// Lấy log_id
$logId = (int)($_POST['log_id'] ?? 0);
if ($logId <= 0) {
    header('Location: log.php?msg=invalid_id');
    exit;
}

// Xóa
$stmt = $conn->prepare("DELETE FROM SystemLogs WHERE LogID = ?");
$stmt->bind_param("i", $logId);
$stmt->execute();
$stmt->close();

// Có thể ghi thêm log xóa log nếu muốn (meta-level 😅)

// Quay lại danh sách
header('Location: log.php?msg=deleted');
exit;
