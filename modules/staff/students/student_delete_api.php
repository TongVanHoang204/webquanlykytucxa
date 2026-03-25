<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

require_once '../../../db_connect.php';
require_once '../../../includes/auth_check.php';
require_once '../../../includes/log_helper.php';

requireRole(['Admin']);
requirePost();
requireCsrf();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Yêu cầu không hợp lệ.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$chk = $conn->prepare("SELECT 1 FROM Contracts WHERE StudentID = ? LIMIT 1");
$chk->bind_param('i', $id);
$chk->execute();
$chk->store_result();

if ($chk->num_rows > 0) {
    logStudentAction($conn, $_SESSION['UserID'] ?? null, 'delete_denied', "Từ chối xóa sinh viên #{$id} vì còn hợp đồng", 'warning');
    $chk->close();
    echo json_encode(['status' => 'error', 'message' => 'Sinh viên còn hợp đồng, không thể xóa.'], JSON_UNESCAPED_UNICODE);
    exit;
}
$chk->close();

$stm = $conn->prepare("DELETE FROM Students WHERE StudentID = ?");
if (!$stm) {
    logStudentAction($conn, $_SESSION['UserID'] ?? null, 'delete_failed', "Chuẩn bị xóa sinh viên #{$id} thất bại: " . $conn->error, 'warning');
    echo json_encode(['status' => 'error', 'message' => 'Lỗi hệ thống: ' . $conn->error], JSON_UNESCAPED_UNICODE);
    exit;
}

$stm->bind_param('i', $id);
if ($stm->execute()) {
    logStudentAction($conn, $_SESSION['UserID'] ?? null, 'delete', "Xóa sinh viên #{$id}", 'activity');
    echo json_encode(['status' => 'success', 'message' => 'Đã xóa sinh viên khỏi hệ thống.'], JSON_UNESCAPED_UNICODE);
} else {
    logStudentAction($conn, $_SESSION['UserID'] ?? null, 'delete_failed', "Xóa sinh viên #{$id} thất bại: " . $stm->error, 'warning');
    echo json_encode(['status' => 'error', 'message' => 'Không thể xóa: ' . $stm->error], JSON_UNESCAPED_UNICODE);
}

$stm->close();
