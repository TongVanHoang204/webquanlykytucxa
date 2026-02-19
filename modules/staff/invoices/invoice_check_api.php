<?php
include '../../../db_connect.php';
header('Content-Type: application/json; charset=utf-8');

$contractID = intval($_POST['ContractID'] ?? 0);
$month = intval($_POST['Month'] ?? 0);
$year = intval($_POST['Year'] ?? 0);
$response = [
    'allowed' => false,
    'message' => '❌ Không xác định được trạng thái hợp đồng.',
];

// 🧩 Kiểm tra đầu vào
if (!$contractID || !$month || !$year || $month < 1 || $month > 12) {
    $response['message'] = '⚠️ Dữ liệu không hợp lệ.';
    echo json_encode($response);
    exit;
}

// 🧾 Lấy thông tin hợp đồng
$stmt = $conn->prepare("
    SELECT c.ContractID, c.Status, c.StartDate, c.EndDate,
           s.FullName, s.StudentCode
    FROM Contracts c
    JOIN Students s ON c.StudentID = s.StudentID
    WHERE c.ContractID = ?
");
$stmt->bind_param("i", $contractID);
$stmt->execute();
$contract = $stmt->get_result()->fetch_assoc();

if (!$contract) {
    $response['message'] = '❌ Hợp đồng không tồn tại hoặc đã bị xóa.';
    echo json_encode($response);
    exit;
}

// 🕒 Kiểm tra trạng thái hợp đồng
$today = date('Y-m-d');

if ($contract['Status'] === 'Đã hủy') {
    $response['message'] = '❌ Hợp đồng này đã bị hủy. Không thể tạo hóa đơn mới.';
    echo json_encode($response);
    exit;
}

if ($contract['Status'] === 'Hết hạn' || $contract['EndDate'] < $today) {
    $response['message'] = '⚠️ Hợp đồng này đã hết hạn vào ' . date('d/m/Y', strtotime($contract['EndDate'])) . '.';
    echo json_encode($response);
    exit;
}

if ($contract['StartDate'] > $today) {
    $response['message'] = '⚠️ Hợp đồng này sẽ bắt đầu từ ngày ' . date('d/m/Y', strtotime($contract['StartDate'])) . '. Chưa thể tạo hóa đơn.';
    echo json_encode($response);
    exit;
}

// 🔍 Kiểm tra trùng hóa đơn
$check = $conn->prepare("SELECT 1 FROM Invoices WHERE ContractID = ? AND Month = ? AND Year = ?");
$check->bind_param("iii", $contractID, $month, $year);
$check->execute();
$exists = $check->get_result()->num_rows > 0;

if ($exists) {
    $response['message'] = "⚠️ Hóa đơn tháng {$month}/{$year} cho sinh viên {$contract['FullName']} ({$contract['StudentCode']}) đã tồn tại.";
    echo json_encode($response);
    exit;
}

// ✅ Hợp lệ
$response['allowed'] = true;
$response['message'] = "✅ Có thể tạo hóa đơn tháng {$month}/{$year} cho sinh viên {$contract['FullName']} ({$contract['StudentCode']}).";
echo json_encode($response);
