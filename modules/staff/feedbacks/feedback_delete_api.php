<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../../db_connect.php';
require_once '../../../includes/auth_check.php';

header('Content-Type: application/json; charset=utf-8');

requireRole(['Admin', 'Manager']);
requirePost();
requireCsrf();

if (empty($_POST['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Yêu cầu không hợp lệ!']);
    exit;
}

$id = intval($_POST['id']);

// 🔍 Lấy tiêu đề và ảnh để xóa
$stmt = $conn->prepare("SELECT Title, ImagePath FROM Feedbacks WHERE FeedbackID = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$fb = $res->fetch_assoc();

if (!$fb) {
    echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy phản ánh!']);
    exit;
}

// 🗑️ Xóa ảnh nếu có
if (!empty($fb['ImagePath'])) {
    $imgPath = "../../../" . ltrim($fb['ImagePath'], '/');
    if (file_exists($imgPath)) unlink($imgPath);
}

// 🗑️ Xóa trong database
$delete = $conn->prepare("DELETE FROM Feedbacks WHERE FeedbackID = ?");
$delete->bind_param("i", $id);

if ($delete->execute()) {
    $title = $fb['Title'] ?? "Phản ánh không tên";
    echo json_encode([
        'status' => 'success',
        'message' => "Đã xóa phản ánh '{$title}' thành công!"
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Không thể xóa phản ánh: ' . $conn->error]);
}
