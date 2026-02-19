<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../../db_connect.php';
require_once '../../../includes/auth_check.php';
requireRole(['Admin']);

header('Content-Type: application/json; charset=utf-8');

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['status'=>'error','message'=>'ID không hợp lệ']);
    exit;
}

$stmt = $conn->prepare("SELECT AttachmentPath FROM Announcements WHERE AnnouncementID=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$result) {
    echo json_encode(['status'=>'error','message'=>'Không tìm thấy thông báo']);
    exit;
}

// xóa file đính kèm (nếu có)
if (!empty($result['AttachmentPath'])) {
    $filePath = '../../../' . $result['AttachmentPath'];
    if (file_exists($filePath)) unlink($filePath);
}

// xóa trong DB
$stmt = $conn->prepare("DELETE FROM Announcements WHERE AnnouncementID=?");
$stmt->bind_param("i", $id);
if ($stmt->execute()) {
    echo json_encode(['status'=>'success','message'=>'Đã xóa thông báo']);
} else {
    echo json_encode(['status'=>'error','message'=>'Không thể xóa']);
}
$stmt->close();
