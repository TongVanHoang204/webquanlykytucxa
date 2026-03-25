<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../../db_connect.php';
require_once '../../../includes/auth_check.php';
require_once '../../../includes/log_helper.php';

header('Content-Type: application/json; charset=utf-8');

requireRole(['Admin', 'Manager']);
requirePost();
requireCsrf();

$id = (int)($_POST['id'] ?? 0);
$action = trim((string)($_POST['action'] ?? ''));

if ($id <= 0 || $action === '') {
    echo json_encode(['status' => 'error', 'message' => 'Thiếu dữ liệu!'], JSON_UNESCAPED_UNICODE);
    exit;
}

switch ($action) {
    case 'renew':
        $newEndDate = trim((string)($_POST['newEndDate'] ?? ''));
        if ($newEndDate === '') {
            echo json_encode(['status' => 'error', 'message' => 'Vui lòng nhập ngày kết thúc mới!'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmt = $conn->prepare("UPDATE Contracts SET EndDate = ?, UpdatedAt = NOW() WHERE ContractID = ?");
        $stmt->bind_param('si', $newEndDate, $id);

        if ($stmt->execute()) {
            logContractAction($conn, $_SESSION['UserID'] ?? null, 'renew', "Gia hạn hợp đồng #{$id} đến {$newEndDate}", 'activity');
            echo json_encode(['status' => 'success', 'message' => 'Gia hạn hợp đồng thành công!'], JSON_UNESCAPED_UNICODE);
        } else {
            logContractAction($conn, $_SESSION['UserID'] ?? null, 'renew_failed', "Gia hạn hợp đồng #{$id} thất bại: " . $stmt->error, 'warning');
            echo json_encode(['status' => 'error', 'message' => 'Không thể gia hạn hợp đồng.'], JSON_UNESCAPED_UNICODE);
        }
        $stmt->close();
        break;

    case 'cancel':
        $stmt = $conn->prepare("UPDATE Contracts SET Status = 'Đã hủy', UpdatedAt = NOW() WHERE ContractID = ?");
        $stmt->bind_param('i', $id);

        if ($stmt->execute()) {
            logContractAction($conn, $_SESSION['UserID'] ?? null, 'cancel', "Hủy hợp đồng #{$id} từ cập nhật trạng thái", 'activity');
            echo json_encode(['status' => 'success', 'message' => 'Hợp đồng đã được hủy.'], JSON_UNESCAPED_UNICODE);
        } else {
            logContractAction($conn, $_SESSION['UserID'] ?? null, 'cancel_failed', "Hủy hợp đồng #{$id} thất bại: " . $stmt->error, 'warning');
            echo json_encode(['status' => 'error', 'message' => 'Không thể hủy hợp đồng.'], JSON_UNESCAPED_UNICODE);
        }
        $stmt->close();
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Hành động không hợp lệ!'], JSON_UNESCAPED_UNICODE);
        break;
}
