<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

require_once '../../../db_connect.php';
require_once '../../../includes/auth_check.php';

requireRole(['Admin']);
requirePost();
requireCsrf();

if (empty($_POST['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Yêu cầu không hợp lệ.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$id = (int)$_POST['id'];

$chk = $conn->prepare("SELECT 1 FROM Contracts WHERE StudentID = ? LIMIT 1");
$chk->bind_param('i', $id);
$chk->execute();
$chk->store_result();

if ($chk->num_rows > 0) {
    echo json_encode(['status' => 'error', 'message' => 'Sinh viên còn hợp đồng, không thể xóa.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$stm = $conn->prepare("DELETE FROM Students WHERE StudentID = ?");
if (!$stm) {
    echo json_encode(['status' => 'error', 'message' => 'Lỗi prepare: ' . $conn->error], JSON_UNESCAPED_UNICODE);
    exit;
}

$stm->bind_param('i', $id);
if ($stm->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Đã xóa sinh viên khỏi hệ thống.'], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Không thể xóa: ' . $conn->error], JSON_UNESCAPED_UNICODE);
}
