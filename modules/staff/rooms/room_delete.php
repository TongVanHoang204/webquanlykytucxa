<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../../db_connect.php';
require_once '../../../includes/auth_check.php';
require_once '../../../includes/log_helper.php';

requireRole(['Admin']);
requirePost();
requireCsrf();

$id = (int)($_POST['id'] ?? 0);
$confirmRoom = trim((string)($_POST['confirm_room'] ?? ''));

if ($id <= 0) {
    $_SESSION['message'] = 'ID phòng không hợp lệ.';
    $_SESSION['message_type'] = 'error';
    header('Location: rooms.php');
    exit;
}

$roomStmt = $conn->prepare('SELECT RoomNumber, CurrentOccupants, ImagePath FROM Rooms WHERE RoomID = ? LIMIT 1');
$roomStmt->bind_param('i', $id);
$roomStmt->execute();
$room = $roomStmt->get_result()->fetch_assoc();
$roomStmt->close();

if (!$room) {
    $_SESSION['message'] = 'Không tìm thấy phòng cần xóa.';
    $_SESSION['message_type'] = 'error';
    header('Location: rooms.php');
    exit;
}

if ($confirmRoom === '' || strcasecmp($confirmRoom, (string)$room['RoomNumber']) !== 0) {
    addLog(
        $conn,
        $_SESSION['UserID'] ?? null,
        'Delete room denied',
        'Rooms',
        'Từ chối xóa phòng do xác nhận không khớp: ID=' . $id,
        'warning'
    );
    $_SESSION['message'] = "Xác nhận xóa không khớp số phòng <b>{$room['RoomNumber']}</b>.";
    $_SESSION['message_type'] = 'error';
    header('Location: rooms.php');
    exit;
}

$activeContractStmt = $conn->prepare(
    "SELECT COUNT(*) AS cnt
     FROM Contracts
     WHERE RoomID = ? AND Status = 'Hiệu lực'"
);
$activeContractStmt->bind_param('i', $id);
$activeContractStmt->execute();
$activeContracts = (int)($activeContractStmt->get_result()->fetch_assoc()['cnt'] ?? 0);
$activeContractStmt->close();

if ($activeContracts > 0 || (int)$room['CurrentOccupants'] > 0) {
    addLog(
        $conn,
        $_SESSION['UserID'] ?? null,
        'Delete room denied',
        'Rooms',
        'Từ chối xóa phòng ID=' . $id . ' vì vẫn còn sinh viên đang ở.',
        'warning'
    );
    $_SESSION['message'] = "Không thể xóa phòng <b>{$room['RoomNumber']}</b> vì vẫn còn sinh viên đang ở.";
    $_SESSION['message_type'] = 'warning';
    header('Location: rooms.php');
    exit;
}

try {
    $conn->begin_transaction();

    $conn->query("
        DELETE FROM Invoices
        WHERE ContractID IN (SELECT ContractID FROM Contracts WHERE RoomID = {$id})
    ");

    $conn->query("
        DELETE FROM Payments
        WHERE ContractID IN (SELECT ContractID FROM Contracts WHERE RoomID = {$id})
    ");

    $conn->query("
        DELETE FROM Feedbacks
        WHERE StudentID IN (
            SELECT StudentID FROM Contracts WHERE RoomID = {$id}
        )
    ");

    $conn->query("DELETE FROM Contracts WHERE RoomID = {$id}");
    $conn->query("DELETE FROM RoomRequests WHERE RoomID = {$id}");

    if (!empty($room['ImagePath']) && file_exists('../../../' . $room['ImagePath'])) {
        unlink('../../../' . $room['ImagePath']);
    }

    $deleteRoomStmt = $conn->prepare('DELETE FROM Rooms WHERE RoomID = ?');
    $deleteRoomStmt->bind_param('i', $id);
    $deleteRoomStmt->execute();
    $deleteRoomStmt->close();

    $conn->commit();

    addLog(
        $conn,
        $_SESSION['UserID'] ?? null,
        'Delete room',
        'Rooms',
        'Đã xóa phòng ID=' . $id . ' - Số phòng: ' . $room['RoomNumber'],
        'history'
    );

    $_SESSION['message'] = "Đã xóa phòng <b>{$room['RoomNumber']}</b> và dữ liệu liên quan.";
    $_SESSION['message_type'] = 'success';
} catch (Throwable $e) {
    $conn->rollback();
    addLog(
        $conn,
        $_SESSION['UserID'] ?? null,
        'Delete room failed',
        'Rooms',
        'Xóa phòng thất bại: ID=' . $id . ' - ' . $e->getMessage(),
        'warning'
    );
    $_SESSION['message'] = 'Lỗi khi xóa phòng: ' . $e->getMessage();
    $_SESSION['message_type'] = 'error';
}

header('Location: rooms.php');
exit;
