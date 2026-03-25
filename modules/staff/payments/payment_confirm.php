<?php
// ==============================
// ✅ XÁC NHẬN / TỪ CHỐI THANH TOÁN (Admin/Manager)
// ==============================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../../db_connect.php';
require_once '../../../includes/auth_check.php';
require_once '../../../includes/log_helper.php';
requireRole(['Admin', 'Manager']);

$conn->set_charset('utf8mb4');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: payment_list.php');
    exit;
}

$paymentID = (int)($_POST['payment_id'] ?? 0);
$action = $_POST['action'] ?? '';

if ($paymentID <= 0 || !in_array($action, ['confirm', 'reject'])) {
    $_SESSION['message'] = 'Tham số không hợp lệ!';
    $_SESSION['message_type'] = 'error';
    header('Location: payment_list.php');
    exit;
}

// Lấy thông tin thanh toán
$stmt = $conn->prepare("
    SELECT p.*, s.FullName, s.StudentCode, s.UserID AS StudentUserID,
           r.RoomNumber, b.BuildingName,
           i.Month, i.Year, i.InvoiceID AS InvID, i.ContractID
    FROM payments p
    INNER JOIN students s ON p.StudentID = s.StudentID
    INNER JOIN rooms r ON p.RoomID = r.RoomID
    INNER JOIN buildings b ON r.BuildingID = b.BuildingID
    LEFT JOIN invoices i ON p.InvoiceID = i.InvoiceID
    WHERE p.PaymentID = ? AND p.Status = 'Chờ xác nhận'
    LIMIT 1
");
$stmt->bind_param('i', $paymentID);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['message'] = 'Không tìm thấy thanh toán hoặc đã được xử lý!';
    $_SESSION['message_type'] = 'error';
    header('Location: payment_list.php');
    exit;
}

$payment = $result->fetch_assoc();
$stmt->close();

try {
    $conn->begin_transaction();

    if ($action === 'confirm') {
        // 1. Cập nhật trạng thái payment → Đã xác nhận
        $updatePayment = $conn->prepare("UPDATE payments SET Status = 'Đã xác nhận', PaidAt = NOW() WHERE PaymentID = ?");
        $updatePayment->bind_param('i', $paymentID);
        $updatePayment->execute();
        $updatePayment->close();

        // 2. Cập nhật trạng thái hóa đơn → Đã thanh toán (nếu có InvoiceID)
        if ($payment['InvoiceID']) {
            // Kiểm tra cột PaymentDate
            $checkCol = $conn->query("SHOW COLUMNS FROM Invoices LIKE 'PaymentDate'");
            if ($checkCol && $checkCol->num_rows === 0) {
                $conn->query("ALTER TABLE Invoices ADD COLUMN PaymentDate DATETIME NULL AFTER Status");
                $conn->query("ALTER TABLE Invoices ADD COLUMN PaymentMethod VARCHAR(50) NULL AFTER PaymentDate");
            }

            $updateInvoice = $conn->prepare("
                UPDATE invoices 
                SET Status = 'Đã thanh toán', 
                    PaidAt = NOW(),
                    UpdatedAt = NOW()
                WHERE InvoiceID = ?
            ");
            $updateInvoice->bind_param('i', $payment['InvoiceID']);
            $updateInvoice->execute();
            $updateInvoice->close();
        }

        // 3. Gửi thông báo cho sinh viên
        $notiTitle = "Thanh toán đã được xác nhận ✅";
        $notiMessage = "Yêu cầu thanh toán hóa đơn phòng <strong>" . $payment['RoomNumber'] . "</strong>";
        if ($payment['Month'] && $payment['Year']) {
            $notiMessage .= " tháng <strong>" . $payment['Month'] . "/" . $payment['Year'] . "</strong>";
        }
        $notiMessage .= " đã được Admin xác nhận thành công.<br>"
            . "Số tiền: <strong>" . number_format($payment['Amount'], 0, ',', '.') . " ₫</strong><br>"
            . "Mã giao dịch: <strong>" . $payment['TransactionCode'] . "</strong>";

        $insertNoti = $conn->prepare("INSERT INTO notifications (StudentID, Title, Message, CreatedAt, IsRead) VALUES (?, ?, ?, NOW(), 0)");
        $insertNoti->bind_param('iss', $payment['StudentID'], $notiTitle, $notiMessage);
        $insertNoti->execute();
        $insertNoti->close();

        // Log
        addLog($conn, $_SESSION['UserID'] ?? null, 'Confirm payment', 'Payments',
            'Xác nhận thanh toán #' . $paymentID . ' của SV ' . $payment['FullName'], 'activity');

        $conn->commit();
        $_SESSION['message'] = 'Đã xác nhận thanh toán #' . $paymentID . ' thành công!';
        $_SESSION['message_type'] = 'success';

    } else {
        // action === 'reject'
        // 1. Cập nhật trạng thái payment → Từ chối
        $updatePayment = $conn->prepare("UPDATE payments SET Status = 'Từ chối' WHERE PaymentID = ?");
        $updatePayment->bind_param('i', $paymentID);
        $updatePayment->execute();
        $updatePayment->close();

        // 2. Gửi thông báo cho sinh viên
        $notiTitle = "Thanh toán bị từ chối ❌";
        $notiMessage = "Yêu cầu thanh toán hóa đơn phòng <strong>" . $payment['RoomNumber'] . "</strong>";
        if ($payment['Month'] && $payment['Year']) {
            $notiMessage .= " tháng <strong>" . $payment['Month'] . "/" . $payment['Year'] . "</strong>";
        }
        $notiMessage .= " đã bị từ chối.<br>"
            . "Số tiền: <strong>" . number_format($payment['Amount'], 0, ',', '.') . " ₫</strong><br>"
            . "Vui lòng liên hệ Ban quản lý để biết thêm chi tiết.";

        $insertNoti = $conn->prepare("INSERT INTO notifications (StudentID, Title, Message, CreatedAt, IsRead) VALUES (?, ?, ?, NOW(), 0)");
        $insertNoti->bind_param('iss', $payment['StudentID'], $notiTitle, $notiMessage);
        $insertNoti->execute();
        $insertNoti->close();

        // Log
        addLog($conn, $_SESSION['UserID'] ?? null, 'Reject payment', 'Payments',
            'Từ chối thanh toán #' . $paymentID . ' của SV ' . $payment['FullName'], 'activity');

        $conn->commit();
        $_SESSION['message'] = 'Đã từ chối thanh toán #' . $paymentID . '.';
        $_SESSION['message_type'] = 'warning';
    }

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['message'] = 'Lỗi xử lý: ' . $e->getMessage();
    $_SESSION['message_type'] = 'error';
}

header('Location: payment_list.php');
exit;
?>
