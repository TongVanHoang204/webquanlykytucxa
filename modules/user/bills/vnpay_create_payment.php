<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../../db_connect.php';
require_once '../../../includes/auth_check.php';
requireRole(['Student']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Truy cập không hợp lệ.");
}

$invoice_id = $_POST['invoice_id'] ?? '';
$amount = $_POST['amount'] ?? '';
$content = $_POST['content'] ?? "Thanh toan hoa don KTX";

if (!$invoice_id || !$amount) {
    die("Dữ liệu thanh toán không hợp lệ.");
}

/* ======================================================================
  CẤU HÌNH VNPAY SANDBOX (Tài khoản Test)
  Nguồn: https://sandbox.vnpayment.vn/apis/vnpay-demo/
====================================================================== */
$vnp_TmnCode = "CT92A02C"; // Mã website (Terminal ID) - Tài khoản sandbox demo
$vnp_HashSecret = "VIBYYDUSMWTYMVKUXRNRRXZXWXZTNDBB"; // Chuỗi bí mật
$vnp_Url = "https://sandbox.vnpayment.vn/paymentv2/vpcpay.html";
$vnp_Returnurl = "http://" . $_SERVER['HTTP_HOST'] . "/WEBQuanLyKyTucXa/modules/user/bills/vnpay_return.php";

$vnp_TxnRef = $invoice_id . '_' . time(); // Mã đơn hàng (độc nhất)
$vnp_OrderInfo = $content;
$vnp_OrderType = "billpayment";
$vnp_Amount = $amount * 100; // VNPay yêu cầu nhân 100 cho VNĐ
$vnp_Locale = "vn";
$vnp_BankCode = ""; // Để trống nếu muốn chọn thẻ trên cổng VNPay
$vnp_IpAddr = $_SERVER['REMOTE_ADDR'];

$inputData = array(
    "vnp_Version" => "2.1.0",
    "vnp_TmnCode" => $vnp_TmnCode,
    "vnp_Amount" => $vnp_Amount,
    "vnp_Command" => "pay",
    "vnp_CreateDate" => date('YmdHis'),
    "vnp_CurrCode" => "VND",
    "vnp_IpAddr" => $vnp_IpAddr,
    "vnp_Locale" => $vnp_Locale,
    "vnp_OrderInfo" => $vnp_OrderInfo,
    "vnp_OrderType" => $vnp_OrderType,
    "vnp_ReturnUrl" => $vnp_Returnurl,
    "vnp_TxnRef" => $vnp_TxnRef
);

if (isset($vnp_BankCode) && $vnp_BankCode != "") {
    $inputData['vnp_BankCode'] = $vnp_BankCode;
}

ksort($inputData);
$query = "";
$i = 0;
$hashdata = "";
foreach ($inputData as $key => $value) {
    if ($i == 1) {
        $hashdata .= '&' . urlencode($key) . "=" . urlencode($value);
    } else {
        $hashdata .= urlencode($key) . "=" . urlencode($value);
        $i = 1;
    }
    $query .= urlencode($key) . "=" . urlencode($value) . '&';
}

$vnp_Url = $vnp_Url . "?" . $query;

if (isset($vnp_HashSecret)) {
    $vnpSecureHash = hash_hmac('sha512', $hashdata, $vnp_HashSecret);
    $vnp_Url .= 'vnp_SecureHash=' . $vnpSecureHash;
}

header('Location: ' . $vnp_Url);
die();
