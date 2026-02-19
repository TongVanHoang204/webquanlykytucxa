<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include '../../../db_connect.php';

header('Content-Type: application/json; charset=utf-8');

// ✅ Kiểm tra quyền
if (!isset($_SESSION['Role']) || !in_array($_SESSION['Role'], ['Admin', 'Manager'])) {
    echo json_encode(['status' => 'error', 'message' => 'Bạn không có quyền xóa hóa đơn!']);
    exit;
}

// ✅ Kiểm tra dữ liệu gửi lên
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Yêu cầu không hợp lệ!']);
    exit;
}

$id = intval($_POST['id']);

// 🔍 Lấy thông tin hóa đơn (để lấy trạng thái, tên SV & tháng)
$stmt = $conn->prepare("
    SELECT 
        i.InvoiceID, i.Month, i.Year, i.Status,
        s.FullName, s.StudentCode
    FROM Invoices i
    JOIN Contracts c ON i.ContractID = c.ContractID
    JOIN Students s ON c.StudentID = s.StudentID
    WHERE i.InvoiceID = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$invoice = $res->fetch_assoc();

if (!$invoice) {
    echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy hóa đơn!']);
    exit;
}

// 🚫 Chặn xóa nếu hóa đơn chưa thanh toán
if ($invoice['Status'] === 'Chưa thanh toán') {
    echo json_encode([
        'status' => 'error',
        'message' => '❌ Không thể xóa hóa đơn chưa thanh toán! Vui lòng xác nhận thanh toán hoặc hủy hợp đồng trước.'
    ]);
    exit;
}

// 📝 Ghi log hành động
$admin = $_SESSION['FullName'] ?? 'Người quản lý';
$conn->query("INSERT INTO ActionLogs (Action, PerformedBy, CreatedAt)
              VALUES ('Xóa hóa đơn #$id', '$admin', NOW())");

// 🗑️ Xóa hóa đơn (tạm tắt kiểm tra khóa ngoại)
$conn->query("SET FOREIGN_KEY_CHECKS=0");
$delete = $conn->prepare("DELETE FROM Invoices WHERE InvoiceID = ?");
$delete->bind_param("i", $id);
$success = $delete->execute();
$conn->query("SET FOREIGN_KEY_CHECKS=1");

if ($success) {
    $studentName = $invoice['FullName'] ?? 'Sinh viên không tên';
    $month = $invoice['Month'] ?? '';
    $year  = $invoice['Year'] ?? '';
    echo json_encode([
        'status' => 'success',
        'message' => "Đã xóa hóa đơn tháng {$month}/{$year} của sinh viên {$studentName} thành công!"
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Không thể xóa hóa đơn: ' . $conn->error]);
}

$conn->close();
