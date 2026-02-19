<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include '../../../db_connect.php';
header('Content-Type: application/json; charset=utf-8');

$id = intval($_POST['id'] ?? 0);
$action = $_POST['action'] ?? '';

if (!$id || !$action) {
    echo json_encode(['status' => 'error', 'message' => 'Thiếu dữ liệu!']);
    exit;
}

switch ($action) {
    case 'renew':
        $newEndDate = $_POST['newEndDate'] ?? '';
        if (!$newEndDate) {
            echo json_encode(['status' => 'error', 'message' => 'Vui lòng nhập ngày kết thúc mới!']);
            exit;
        }
        $stmt = $conn->prepare("UPDATE Contracts SET EndDate = ?, UpdatedAt = NOW() WHERE ContractID = ?");
        $stmt->bind_param("si", $newEndDate, $id);
        $stmt->execute();
        echo json_encode(['status' => 'success', 'message' => 'Gia hạn hợp đồng thành công!']);
        break;

    case 'cancel':
        $conn->query("UPDATE Contracts SET Status = 'Đã hủy', UpdatedAt = NOW() WHERE ContractID = $id");
        echo json_encode(['status' => 'success', 'message' => 'Hợp đồng đã được hủy.']);
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Hành động không hợp lệ!']);
}
