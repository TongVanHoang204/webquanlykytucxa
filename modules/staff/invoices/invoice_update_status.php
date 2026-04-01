<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
ob_start();

require_once '../../../db_connect.php';
require_once '../../../includes/auth_check.php';

requireRole(['Admin', 'Manager']);
requirePost();
requireCsrf();

if (empty($_POST['id'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Yêu cầu không hợp lệ!'], JSON_UNESCAPED_UNICODE);
    exit;
}

$id = (int)$_POST['id'];

$sql = "
    SELECT i.InvoiceID, i.Status, s.FullName
    FROM Invoices i
    JOIN Contracts c ON i.ContractID = c.ContractID
    JOIN Students s ON c.StudentID = s.StudentID
    WHERE i.InvoiceID = ?
";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Lỗi prepare: ' . $conn->error], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt->bind_param("i", $id);
$stmt->execute();
$info = $stmt->get_result()->fetch_assoc();

if (!$info) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy hóa đơn!'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($info['Status'] === 'Đã thanh toán') {
    echo json_encode(['status' => 'warning', 'message' => 'Hóa đơn đã ở trạng thái ĐÃ THANH TOÁN.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$upd = $conn->prepare("UPDATE Invoices SET Status = 'Đã thanh toán', PaidAt = NOW() WHERE InvoiceID = ?");
if (!$upd) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Lỗi prepare update: ' . $conn->error], JSON_UNESCAPED_UNICODE);
    exit;
}

$upd->bind_param("i", $id);

if ($upd->execute()) {
    echo json_encode([
        'status' => 'success',
        'message' => "Đã cập nhật hóa đơn của sinh viên <b>" . htmlspecialchars($info['FullName']) . "</b> thành <strong>ĐÃ THANH TOÁN</strong>!"
    ], JSON_UNESCAPED_UNICODE);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Lỗi khi cập nhật: ' . $conn->error], JSON_UNESCAPED_UNICODE);
}
