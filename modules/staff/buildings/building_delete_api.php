<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../../db_connect.php';
require_once '../../../includes/auth_check.php';
require_once '../../../includes/log_helper.php';

header('Content-Type: application/json; charset=utf-8');

requireRole(['Admin']);
requirePost();
requireCsrf();

$buildingId = (int)($_POST['id'] ?? 0);
$confirmName = trim((string)($_POST['confirm_name'] ?? ''));

if ($buildingId <= 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'ID tòa nhà không hợp lệ.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$buildingStmt = $conn->prepare(
    "SELECT b.BuildingID, b.BuildingName, COUNT(r.RoomID) AS TotalRooms
     FROM Buildings b
     LEFT JOIN Rooms r ON r.BuildingID = b.BuildingID
     WHERE b.BuildingID = ?
     GROUP BY b.BuildingID, b.BuildingName
     LIMIT 1"
);
$buildingStmt->bind_param('i', $buildingId);
$buildingStmt->execute();
$building = $buildingStmt->get_result()->fetch_assoc();
$buildingStmt->close();

if (!$building) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Không tìm thấy tòa nhà cần xóa.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($confirmName === '' || mb_strtolower($confirmName, 'UTF-8') !== mb_strtolower((string)$building['BuildingName'], 'UTF-8')) {
    addLog(
        $conn,
        $_SESSION['UserID'] ?? null,
        'Delete building denied',
        'Buildings',
        'Từ chối xóa tòa nhà do xác nhận không khớp: ID=' . $buildingId,
        'warning'
    );
    echo json_encode([
        'status' => 'error',
        'message' => 'Xác nhận xóa không khớp tên tòa nhà.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ((int)$building['TotalRooms'] > 0) {
    addLog(
        $conn,
        $_SESSION['UserID'] ?? null,
        'Delete building denied',
        'Buildings',
        'Từ chối xóa tòa nhà ID=' . $buildingId . ' vì vẫn còn phòng trực thuộc.',
        'warning'
    );
    echo json_encode([
        'status' => 'error',
        'message' => 'Không thể xóa tòa nhà khi vẫn còn phòng trực thuộc. Vui lòng chuyển hoặc xóa hết phòng trước.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$deleteStmt = $conn->prepare('DELETE FROM Buildings WHERE BuildingID = ?');
$deleteStmt->bind_param('i', $buildingId);

if (!$deleteStmt->execute()) {
    $deleteStmt->close();
    addLog(
        $conn,
        $_SESSION['UserID'] ?? null,
        'Delete building failed',
        'Buildings',
        'Xóa tòa nhà thất bại: ID=' . $buildingId,
        'warning'
    );
    echo json_encode([
        'status' => 'error',
        'message' => 'Không thể xóa tòa nhà. Vui lòng thử lại.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$deleteStmt->close();

addLog(
    $conn,
    $_SESSION['UserID'] ?? null,
    'Delete building',
    'Buildings',
    'Đã xóa tòa nhà ID=' . $buildingId . ' - Tên: ' . $building['BuildingName'],
    'history'
);

echo json_encode([
    'status' => 'success',
    'message' => 'Đã xóa tòa nhà ' . $building['BuildingName'] . '.',
], JSON_UNESCAPED_UNICODE);
