<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include '../../../db_connect.php';
include '../../../includes/auth_check.php';

requireRole(['Admin']);
requirePost();
requireCsrf();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    $_SESSION['message'] = 'ID phòng không hợp lệ.';
    $_SESSION['message_type'] = 'error';
    header('Location: rooms.php');
    exit;
}

$roomRes = $conn->query("SELECT RoomNumber, CurrentOccupants, ImagePath FROM Rooms WHERE RoomID = $id");
if (!$roomRes || $roomRes->num_rows === 0) {
    $_SESSION['message'] = 'Không tìm thấy phòng cần xóa.';
    $_SESSION['message_type'] = 'error';
    header('Location: rooms.php');
    exit;
}

$room = $roomRes->fetch_assoc();
$activeContracts = $conn->query("
    SELECT COUNT(*) AS cnt
    FROM Contracts
    WHERE RoomID = $id AND Status = 'Hiệu lực'
")->fetch_assoc()['cnt'] ?? 0;

if ($activeContracts > 0 || (int)$room['CurrentOccupants'] > 0) {
    $_SESSION['message'] = "Không thể xóa phòng <b>{$room['RoomNumber']}</b> vì vẫn còn sinh viên đang ở.";
    $_SESSION['message_type'] = 'warning';
    header('Location: rooms.php');
    exit;
}

try {
    $conn->begin_transaction();

    $conn->query("
        DELETE FROM Invoices
        WHERE ContractID IN (SELECT ContractID FROM Contracts WHERE RoomID = $id)
    ");

    $conn->query("
        DELETE FROM Payments
        WHERE ContractID IN (SELECT ContractID FROM Contracts WHERE RoomID = $id)
    ");

    $conn->query("
        DELETE FROM Feedbacks
        WHERE StudentID IN (
            SELECT StudentID FROM Contracts WHERE RoomID = $id
        )
    ");

    $conn->query("DELETE FROM Contracts WHERE RoomID = $id");

    if (!empty($room['ImagePath']) && file_exists('../../../' . $room['ImagePath'])) {
        unlink('../../../' . $room['ImagePath']);
    }

    $conn->query("DELETE FROM Rooms WHERE RoomID = $id");
    $conn->commit();

    $_SESSION['message'] = "Đã xóa phòng <b>{$room['RoomNumber']}</b> và dữ liệu liên quan.";
    $_SESSION['message_type'] = 'success';
} catch (Throwable $e) {
    $conn->rollback();
    $_SESSION['message'] = 'Lỗi khi xóa phòng: ' . $e->getMessage();
    $_SESSION['message_type'] = 'error';
}

header('Location: rooms.php');
exit;
