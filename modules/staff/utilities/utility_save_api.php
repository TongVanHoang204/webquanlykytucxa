<?php
session_start();
header('Content-Type: application/json');
require_once '../../../db_connect.php';
require_once '../../../includes/auth_check.php';

// Chỉ admin hoac manager dc phép
if (!isset($_SESSION['Role']) || ($_SESSION['Role'] !== 'Admin' && $_SESSION['Role'] !== 'Manager')) {
    echo JSON_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

$roomId = (int)$input['room_id'];
$month = (int)$input['month'];
$year = (int)$input['year'];
$eOld = (int)$input['e_old'];
$eNew = (int)$input['e_new'];
$wOld = (int)$input['w_old'];
$wNew = (int)$input['w_new'];

if ($eNew < $eOld || $wNew < $wOld) {
    echo json_encode(['success' => false, 'message' => 'Chỉ số mới không được nhỏ hơn chỉ số cũ']);
    exit;
}

// Update or Insert
$sql = "
    INSERT INTO utility_readings (RoomID, ReadingMonth, ReadingYear, ElectricOld, ElectricNew, WaterOld, WaterNew)
    VALUES (?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE 
        ElectricOld = VALUES(ElectricOld),
        ElectricNew = VALUES(ElectricNew),
        WaterOld = VALUES(WaterOld),
        WaterNew = VALUES(WaterNew)
";

$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("iiiiiii", $roomId, $month, $year, $eOld, $eNew, $wOld, $wNew);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Query error: ' . $conn->error]);
}
$conn->close();
?>
