<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../../db_connect.php';

/* ======================================================================
  CẤU HÌNH VNPAY SANDBOX
====================================================================== */
$vnp_HashSecret = "VIBYYDUSMWTYMVKUXRNRRXZXWXZTNDBB"; // Same as create

$inputData = array();
foreach ($_GET as $key => $value) {
    if (substr($key, 0, 4) == "vnp_") {
        $inputData[$key] = $value;
    }
}

$vnp_SecureHash = $inputData['vnp_SecureHash'];
unset($inputData['vnp_SecureHash']);
ksort($inputData);

$hashData = "";
$i = 0;
foreach ($inputData as $key => $value) {
    if ($i == 1) {
        $hashData = $hashData . '&' . urlencode($key) . "=" . urlencode($value);
    } else {
        $hashData = $hashData . urlencode($key) . "=" . urlencode($value);
        $i = 1;
    }
}

$secureHash = hash_hmac('sha512', $hashData, $vnp_HashSecret);
$isValid = $secureHash === $vnp_SecureHash;

$txnRef = $_GET['vnp_TxnRef'] ?? '';
$parts = explode('_', $txnRef);
$invoiceID = (int)$parts[0];
$responseCode = $_GET['vnp_ResponseCode'] ?? '';

if ($isValid) {
    if ($responseCode == '00') {
        // Giao dịch thành công
        // UPDATE Invoices table
        try {
            $conn->begin_transaction();
            // Create columns if not exist
            $checkCol = $conn->query("SHOW COLUMNS FROM Invoices LIKE 'PaymentDate'");
            if ($checkCol->num_rows === 0) {
                $conn->query("ALTER TABLE Invoices ADD COLUMN PaymentDate DATETIME NULL AFTER Status");
                $conn->query("ALTER TABLE Invoices ADD COLUMN PaymentMethod VARCHAR(50) NULL AFTER PaymentDate");
            }
            $upd = $conn->prepare("UPDATE Invoices SET Status='Đã thanh toán', PaymentDate=NOW(), PaymentMethod='vnpay', UpdatedAt=NOW() WHERE InvoiceID=? AND Status='Chưa thanh toán'");
            $upd->bind_param('i', $invoiceID);
            $upd->execute();
            if ($upd->affected_rows > 0) {
                $conn->commit();
                $_SESSION['message'] = "Thanh toán VNPay thành công!";
            } else {
                $conn->rollback();
                // $_SESSION['message'] = "Hóa đơn đã được thanh toán hoặc không tồn tại.";
            }
            $upd->close();
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['message'] = "Lỗi hệ thống ghi nhận.";
        }
        
    } else {
        // Giao dịch lỗi hoặc bị hủy
        $_SESSION['message'] = "Thanh toán VNPay thất bại hoặc đã bị hủy (Mã lỗi: $responseCode)";
        $_SESSION['message_type'] = "error";
    }
} else {
    $_SESSION['message'] = "Chữ ký VNPay không hợp lệ. Đã phát hiện can thiệp.";
    $_SESSION['message_type'] = "error";
}

header("Location: bill_detail.php?id=" . $invoiceID);
exit;
?>
