<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../../db_connect.php';
require_once '../../../includes/auth_check.php';
requireRole(['Admin']);

$conn->set_charset('utf8mb4');

$requestId = (int)($_GET['id'] ?? 0);
$currentUserId = (int)($_SESSION['UserID'] ?? 0);

if ($requestId <= 0) {
    header("Location: staff_request_list.php");
    exit;
}

$stm = $conn->prepare("
    UPDATE roomrequests
    SET Status = 'Từ chối',
        ReviewedBy = ?,
        ReviewedAt = NOW()
    WHERE RequestID = ? AND Status = 'Chờ duyệt'
");
$stm->bind_param('ii', $currentUserId, $requestId);
$stm->execute();

$affected = $stm->affected_rows;
$stm->close();

if ($affected > 0) {
    $_SESSION['message'] = '❌ Đã từ chối yêu cầu đăng ký phòng.';
    $_SESSION['message_type'] = 'success';
} else {
    $_SESSION['message'] = 'Không thể từ chối yêu cầu (có thể đã được xử lý trước đó).';
    $_SESSION['message_type'] = 'error';
}

header("Location: staff_request_list.php");
exit;
