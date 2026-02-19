<?php
include '../../../db_connect.php';

$id = intval($_POST['id'] ?? 0);
$month = intval($_POST['month'] ?? date('n'));
$year = intval($_POST['year'] ?? date('Y'));

$response = [
    'status' => 'unknown',
    'exists' => false,
    'message' => ''
];

if ($id) {
    // 🔎 Lấy trạng thái hợp đồng
    $stmt = $conn->prepare("SELECT Status, EndDate FROM Contracts WHERE ContractID = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $contract = $stmt->get_result()->fetch_assoc();

    if ($contract) {
        $response['status'] = $contract['Status'];

        if ($contract['Status'] !== 'Hiệu lực') {
            $response['message'] = "❌ Hợp đồng này đã '$contract[Status]' — không thể tạo hóa đơn mới.";
        } else {
            // ⏳ Kiểm tra ngày hết hạn
            $end = new DateTime($contract['EndDate']);
            $today = new DateTime();
            if ($today > $end) {
                $response['status'] = 'Hết hạn';
                $response['message'] = "⚠️ Hợp đồng này đã hết hạn ngày " . $end->format('d/m/Y');
            }
        }

        // 🔎 Kiểm tra xem hóa đơn tháng này đã có chưa
        $check = $conn->prepare("SELECT InvoiceID FROM Invoices WHERE ContractID = ? AND Month = ? AND Year = ?");
        $check->bind_param("iii", $id, $month, $year);
        $check->execute();
        $res = $check->get_result();
        if ($res->num_rows > 0) {
            $response['exists'] = true;
            $response['message'] .= " ⚠️ Hóa đơn tháng $month/$year đã tồn tại.";
        }
    } else {
        $response['message'] = "Không tìm thấy hợp đồng.";
    }
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($response);
