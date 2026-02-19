<?php
include '../../../db_connect.php';
header('Content-Type: application/json; charset=utf-8');

$contractID = intval($_POST['id'] ?? 0);
$month = intval($_POST['month'] ?? date('n'));
$year = intval($_POST['year'] ?? date('Y'));

$response = ['Electric' => 0, 'Water' => 0];

// 🔎 Kiểm tra dữ liệu điện/nước (giả sử lưu trong bảng MeterReadings)
$sql = "
    SELECT 
        COALESCE(ElectricUsage, 0) AS Electric,
        COALESCE(WaterUsage, 0) AS Water
    FROM MeterReadings
    WHERE ContractID = ? AND Month = ? AND Year = ?
    LIMIT 1
";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("iii", $contractID, $month, $year);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    if ($res) $response = $res;
}

// Nếu không có bảng MeterReadings, ta có thể mặc định về 0
echo json_encode($response);
exit;
