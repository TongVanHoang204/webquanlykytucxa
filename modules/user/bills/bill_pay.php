<?php
// ==============================
// 💰 THANH TOÁN HÓA ĐƠN (Gửi yêu cầu → Admin xác nhận)
// ==============================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../../db_connect.php';
require_once '../../../includes/auth_check.php';
requireRole(['Student']);

$conn->set_charset('utf8mb4');

// === Auto-migration: đảm bảo cột InvoiceID và Note trong payments ===
try {
    $check = $conn->query("SHOW COLUMNS FROM payments LIKE 'InvoiceID'");
    if ($check && $check->num_rows === 0) {
        $conn->query("ALTER TABLE payments ADD COLUMN InvoiceID INT NULL AFTER PaymentID");
        $conn->query("ALTER TABLE payments ADD CONSTRAINT fk_payments_invoice FOREIGN KEY (InvoiceID) REFERENCES invoices(InvoiceID)");
    }
    $check2 = $conn->query("SHOW COLUMNS FROM payments LIKE 'Note'");
    if ($check2 && $check2->num_rows === 0) {
        $conn->query("ALTER TABLE payments ADD COLUMN Note TEXT NULL AFTER TransactionCode");
    }
} catch (Exception $e) {
    // Silent - columns may already exist
}

$userID = (int)$_SESSION['UserID'];
$invoiceID = (int)($_GET['id'] ?? 0);
$paymentMethod = $_GET['method'] ?? '';

// Kiểm tra tham số
if ($invoiceID <= 0) {
    $_SESSION['message'] = 'Hóa đơn không hợp lệ!';
    $_SESSION['message_type'] = 'error';
    header('Location: bills.php');
    exit;
}

if (!in_array($paymentMethod, ['vnpay', 'momo', 'transfer', 'bank', 'cash'])) {
    $_SESSION['message'] = 'Phương thức thanh toán không hợp lệ!';
    $_SESSION['message_type'] = 'error';
    header('Location: bills.php');
    exit;
}

// Map payment method
$methodMap = [
    'vnpay' => 'Chuyển khoản',
    'momo' => 'Chuyển khoản',
    'transfer' => 'Chuyển khoản',
    'bank' => 'Chuyển khoản',
    'cash' => 'Tiền mặt'
];
$dbMethod = $methodMap[$paymentMethod] ?? 'Chuyển khoản';

// Lấy thông tin sinh viên
$studentQuery = $conn->prepare("SELECT StudentID, FullName, StudentCode FROM Students WHERE UserID = ?");
$studentQuery->bind_param('i', $userID);
$studentQuery->execute();
$studentResult = $studentQuery->get_result();

if ($studentResult->num_rows == 0) {
    $_SESSION['message'] = 'Không tìm thấy thông tin sinh viên!';
    $_SESSION['message_type'] = 'error';
    header('Location: bills.php');
    exit;
}

$student = $studentResult->fetch_assoc();
$studentID = $student['StudentID'];
$studentQuery->close();

// Lấy thông tin hóa đơn và kiểm tra quyền
$sql = "
    SELECT 
        i.InvoiceID, i.Month, i.Year,
        i.RoomFee, i.ElectricUsage, i.ElectricPrice,
        i.WaterUsage, i.WaterPrice, i.TotalAmount,
        i.Status, i.CreatedAt, i.DueDate,
        r.RoomID, r.RoomNumber, b.BuildingName,
        c.ContractID, c.StudentID
    FROM Invoices i
    INNER JOIN Contracts c ON i.ContractID = c.ContractID
    INNER JOIN Rooms r ON c.RoomID = r.RoomID
    INNER JOIN Buildings b ON r.BuildingID = b.BuildingID
    WHERE i.InvoiceID = ? AND c.StudentID = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('ii', $invoiceID, $studentID);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    $_SESSION['message'] = 'Không tìm thấy hóa đơn hoặc bạn không có quyền truy cập!';
    $_SESSION['message_type'] = 'error';
    header('Location: bills.php');
    exit;
}

$invoice = $result->fetch_assoc();
$stmt->close();

// Kiểm tra trạng thái hóa đơn
if ($invoice['Status'] === 'Đã thanh toán') {
    $_SESSION['message'] = 'Hóa đơn này đã được thanh toán!';
    $_SESSION['message_type'] = 'warning';
    header('Location: bills.php');
    exit;
}

// Kiểm tra đã có thanh toán đang chờ xác nhận chưa
$checkPending = $conn->prepare("SELECT PaymentID FROM payments WHERE InvoiceID = ? AND Status = 'Chờ xác nhận' LIMIT 1");
$checkPending->bind_param('i', $invoiceID);
$checkPending->execute();
$pendingResult = $checkPending->get_result();
$hasPending = $pendingResult->num_rows > 0;
$checkPending->close();

// Xử lý khi user xác nhận đã thanh toán
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_payment'])) {
    if ($hasPending) {
        $_SESSION['message'] = 'Hóa đơn này đã có yêu cầu thanh toán đang chờ xác nhận!';
        $_SESSION['message_type'] = 'warning';
        header('Location: bills.php');
        exit;
    }

    try {
        $conn->begin_transaction();

        // Tạo mã giao dịch
        $transactionCode = 'TXN' . date('YmdHis') . rand(1000, 9999);

        // 1. Tạo bản ghi Payment (Chờ xác nhận)
        $insertPayment = $conn->prepare("
            INSERT INTO payments (InvoiceID, StudentID, RoomID, Amount, Method, Status, TransactionCode, CreatedAt)
            VALUES (?, ?, ?, ?, ?, 'Chờ xác nhận', ?, NOW())
        ");
        $insertPayment->bind_param('iiidss', 
            $invoiceID, 
            $studentID, 
            $invoice['RoomID'], 
            $invoice['TotalAmount'], 
            $dbMethod, 
            $transactionCode
        );
        $insertPayment->execute();
        $insertPayment->close();

        // 2. Gửi thông báo cho Admin qua adminnotifications
        $notiTitle = "Yêu cầu xác nhận thanh toán mới";
        $notiMessage = "Sinh viên <strong>" . $conn->real_escape_string($student['FullName']) . "</strong> (" . $conn->real_escape_string($student['StudentCode']) . ") "
            . "đã gửi yêu cầu xác nhận thanh toán hóa đơn phòng <strong>" . $conn->real_escape_string($invoice['RoomNumber']) . "</strong> "
            . "tháng <strong>" . $invoice['Month'] . "/" . $invoice['Year'] . "</strong>.<br>"
            . "Số tiền: <strong>" . number_format($invoice['TotalAmount'], 0, ',', '.') . " ₫</strong><br>"
            . "Phương thức: <strong>" . htmlspecialchars($dbMethod) . "</strong><br>"
            . "Mã giao dịch: <strong>" . $transactionCode . "</strong>";

        $insertNoti = $conn->prepare("INSERT INTO adminnotifications (Title, Message, CreatedAt, IsRead) VALUES (?, ?, NOW(), 0)");
        $insertNoti->bind_param('ss', $notiTitle, $notiMessage);
        $insertNoti->execute();
        $insertNoti->close();

        $conn->commit();

        $_SESSION['message'] = 'Yêu cầu thanh toán đã được gửi thành công! Vui lòng chờ Admin xác nhận.';
        $_SESSION['message_type'] = 'success';
        header('Location: bill_detail.php?id=' . $invoiceID);
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['message'] = 'Lỗi khi gửi yêu cầu thanh toán: ' . $e->getMessage();
        $_SESSION['message_type'] = 'error';
    }
}

// Helper format tiền
function formatMoney($amount) {
    return number_format((float)$amount, 0, ',', '.') . ' ₫';
}

// Tạo nội dung chuyển khoản
$transferContent = "KTXSV{$student['StudentCode']} T{$invoice['Month']}/{$invoice['Year']}";

// Thông tin ngân hàng
$bankInfo = [
    'name' => 'Ngân hàng TMCP Ngoại Thương Việt Nam (Vietcombank)',
    'account_number' => '1234567890',
    'account_name' => 'KY TUC XA SINH VIEN',
    'branch' => 'Chi nhánh TP.HCM'
];

require_once '../../../includes/header.php';
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thanh toán hóa đơn - Ký Túc Xá</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/user/bills/student-bills.css">
</head>
<body>

<div class="payment-container">
    <!-- Header -->
    <div class="payment-header">
        <h2><i class="fas fa-credit-card"></i> Thanh toán hóa đơn</h2>
        <div class="invoice-info">
            Phòng <?= htmlspecialchars($invoice['RoomNumber']) ?> - 
            Tòa <?= htmlspecialchars($invoice['BuildingName']) ?> - 
            Tháng <?= $invoice['Month'] ?>/<?= $invoice['Year'] ?>
        </div>
    </div>

    <?php if ($hasPending): ?>
    <div style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 10px; padding: 20px; margin-bottom: 20px; text-align: center;">
        <i class="fas fa-hourglass-half" style="font-size: 2rem; color: #856404; margin-bottom: 10px;"></i>
        <h3 style="color: #856404; margin: 10px 0;">Đang chờ xác nhận</h3>
        <p style="color: #856404;">Bạn đã gửi yêu cầu thanh toán cho hóa đơn này. Vui lòng chờ Admin xác nhận.</p>
        <a href="bills.php" class="btn btn-secondary" style="margin-top: 10px; display: inline-block;">
            <i class="fas fa-arrow-left"></i> Quay lại danh sách hóa đơn
        </a>
    </div>
    <?php else: ?>

    <!-- Tóm tắt hóa đơn -->
    <div class="invoice-summary">
        <h3><i class="fas fa-file-invoice"></i> Thông tin hóa đơn</h3>
        
        <div class="summary-row">
            <span class="summary-label">Mã hóa đơn:</span>
            <span class="summary-value">#<?= $invoice['InvoiceID'] ?></span>
        </div>
        
        <div class="summary-row">
            <span class="summary-label">Sinh viên:</span>
            <span class="summary-value"><?= htmlspecialchars($student['FullName']) ?> (<?= htmlspecialchars($student['StudentCode']) ?>)</span>
        </div>
        
        <div class="summary-row">
            <span class="summary-label">Tiền phòng:</span>
            <span class="summary-value"><?= formatMoney($invoice['RoomFee']) ?></span>
        </div>
        
        <div class="summary-row">
            <span class="summary-label">Tiền điện (<?= $invoice['ElectricUsage'] ?> kWh):</span>
            <span class="summary-value"><?= formatMoney($invoice['ElectricPrice']) ?></span>
        </div>
        
        <div class="summary-row">
            <span class="summary-label">Tiền nước (<?= $invoice['WaterUsage'] ?> m³):</span>
            <span class="summary-value"><?= formatMoney($invoice['WaterPrice']) ?></span>
        </div>
        
        <div class="summary-row total">
            <span class="summary-label">Tổng cộng:</span>
            <span class="summary-value"><?= formatMoney($invoice['TotalAmount']) ?></span>
        </div>
        
        <?php if ($invoice['DueDate']): ?>
        <div class="summary-row">
            <span class="summary-label">Hạn thanh toán:</span>
            <span class="summary-value"><?= date('d/m/Y', strtotime($invoice['DueDate'])) ?></span>
        </div>
        <?php endif; ?>
        
        <div class="summary-row">
            <span class="summary-label">Trạng thái:</span>
            <span class="summary-value">
                <span class="status-badge status-pending">Chưa thanh toán</span>
            </span>
        </div>
    </div>

    <!-- Phương thức thanh toán -->
    <div class="payment-method-section">
        <h3><i class="fas fa-wallet"></i> Phương thức thanh toán</h3>
        
        <?php if ($paymentMethod === 'vnpay'): ?>
            <div class="method-badge">
                <i class="fas fa-qrcode"></i> VNPay QR
            </div>
            
            <div class="qr-section">
                <p style="font-size: 18px; font-weight: 600; color: #333; margin-bottom: 15px;">
                    Quét mã QR để thanh toán
                </p>
                
                <div class="qr-code">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=<?= urlencode("VNPay|Amount:" . $invoice['TotalAmount'] . "|Content:" . $transferContent) ?>" 
                         alt="VNPay QR Code">
                </div>
                
                <div class="scan-instruction">
                    <p><i class="fas fa-mobile-alt"></i> Mở ứng dụng VNPay trên điện thoại</p>
                    <p><i class="fas fa-camera"></i> Quét mã QR bên trên</p>
                    <p><i class="fas fa-check-circle"></i> Xác nhận thanh toán</p>
                </div>
            </div>
            
        <?php elseif ($paymentMethod === 'momo'): ?>
            <div class="method-badge">
                <i class="fas fa-qrcode"></i> MoMo QR
            </div>
            
            <div class="qr-section">
                <p style="font-size: 18px; font-weight: 600; color: #333; margin-bottom: 15px;">
                    Quét mã QR để thanh toán
                </p>
                
                <div class="qr-code">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=<?= urlencode("MoMo|Amount:" . $invoice['TotalAmount'] . "|Content:" . $transferContent) ?>" 
                         alt="MoMo QR Code">
                </div>
                
                <div class="scan-instruction">
                    <p><i class="fas fa-mobile-alt"></i> Mở ứng dụng MoMo trên điện thoại</p>
                    <p><i class="fas fa-camera"></i> Quét mã QR bên trên</p>
                    <p><i class="fas fa-check-circle"></i> Xác nhận thanh toán</p>
                </div>
            </div>
            
        <?php else: // transfer / bank / cash ?>
            <div class="method-badge">
                <i class="fas fa-university"></i> Chuyển khoản ngân hàng
            </div>
            
            <div class="bank-info">
                <div class="bank-row">
                    <span class="bank-label">Ngân hàng:</span>
                    <span class="bank-value"><?= $bankInfo['name'] ?></span>
                </div>
                
                <div class="bank-row">
                    <span class="bank-label">Số tài khoản:</span>
                    <span class="bank-value">
                        <?= $bankInfo['account_number'] ?>
                        <button class="copy-btn" onclick="copyToClipboard('<?= $bankInfo['account_number'] ?>')">
                            <i class="fas fa-copy"></i> Sao chép
                        </button>
                    </span>
                </div>
                
                <div class="bank-row">
                    <span class="bank-label">Chủ tài khoản:</span>
                    <span class="bank-value"><?= $bankInfo['account_name'] ?></span>
                </div>
                
                <div class="bank-row">
                    <span class="bank-label">Chi nhánh:</span>
                    <span class="bank-value"><?= $bankInfo['branch'] ?></span>
                </div>
                
                <div class="bank-row">
                    <span class="bank-label">Số tiền:</span>
                    <span class="bank-value">
                        <?= formatMoney($invoice['TotalAmount']) ?>
                        <button class="copy-btn" onclick="copyToClipboard('<?= $invoice['TotalAmount'] ?>')">
                            <i class="fas fa-copy"></i> Sao chép
                        </button>
                    </span>
                </div>
                
                <div class="bank-row">
                    <span class="bank-label">Nội dung:</span>
                    <span class="bank-value">
                        <?= $transferContent ?>
                        <button class="copy-btn" onclick="copyToClipboard('<?= $transferContent ?>')">
                            <i class="fas fa-copy"></i> Sao chép
                        </button>
                    </span>
                </div>
            </div>
            
            <div class="transfer-note">
                <strong><i class="fas fa-exclamation-triangle"></i> Lưu ý quan trọng:</strong>
                <p>Vui lòng ghi CHÍNH XÁC nội dung chuyển khoản: <strong><?= $transferContent ?></strong></p>
                <p>Sau khi chuyển khoản, bấm nút "Tôi đã thanh toán" bên dưới để gửi yêu cầu xác nhận cho Admin.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Actions -->
    <div class="payment-actions">
        <form method="POST" style="flex: 1;">
            <button type="submit" name="confirm_payment" class="btn btn-primary" 
                    onclick="return confirm('Xác nhận bạn đã thanh toán thành công?\n\nYêu cầu sẽ được gửi đến Admin để xác nhận.')">
                <i class="fas fa-check"></i> Tôi đã thanh toán
            </button>
        </form>
        
        <a href="bills.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Quay lại
        </a>
    </div>
    <?php endif; ?>
</div>

<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        alert('Đã sao chép: ' + text);
    }).catch(err => {
        console.error('Lỗi sao chép:', err);
    });
}
</script>

</body>
</html>

<?php
require_once '../../../includes/footer.php';
?>
