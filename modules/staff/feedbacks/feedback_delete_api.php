<?php
session_start();
include '../../../db_connect.php';

header('Content-Type: application/json; charset=utf-8');

// ✅ Kiểm tra quyền (nếu cần)
if (!isset($_SESSION['Role']) || !in_array($_SESSION['Role'], ['Admin', 'Manager'])) {
    echo json_encode(['status' => 'error', 'message' => 'Bạn không có quyền xóa phản ánh!']);
    exit;
}

// ✅ Kiểm tra dữ liệu gửi lên
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['id'])) {
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
