<?php
// ==============================
// 💰 THANH TOÁN HÓA ĐƠN
// ==============================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../../db_connect.php';
require_once '../../../includes/auth_check.php';
requireRole(['Student']);

$conn->set_charset('utf8mb4');

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

if (!in_array($paymentMethod, ['vnpay', 'momo', 'transfer'])) {
    $_SESSION['message'] = 'Phương thức thanh toán không hợp lệ!';
    $_SESSION['message_type'] = 'error';
    header('Location: bills.php');
    exit;
}

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
        r.RoomNumber, b.BuildingName,
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

// Xử lý thanh toán khi nhận callback
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_payment'])) {
    try {
        $conn->begin_transaction();
        
        // Kiểm tra xem bảng Invoices có cột PaymentDate và PaymentMethod chưa
        $checkColumns = $conn->query("SHOW COLUMNS FROM Invoices LIKE 'PaymentDate'");
        if ($checkColumns->num_rows === 0) {
            // Thêm cột PaymentDate và PaymentMethod nếu chưa có
            $conn->query("ALTER TABLE Invoices ADD COLUMN PaymentDate DATETIME NULL AFTER Status");
            $conn->query("ALTER TABLE Invoices ADD COLUMN PaymentMethod VARCHAR(50) NULL AFTER PaymentDate");
        }
        
        // Cập nhật trạng thái hóa đơn với phương thức thanh toán
        $updateStmt = $conn->prepare("
            UPDATE Invoices 
            SET Status = 'Đã thanh toán',
                PaymentDate = NOW(),
                PaymentMethod = ?,
                UpdatedAt = NOW()
            WHERE InvoiceID = ? AND Status = 'Chưa thanh toán'
        ");
        $updateStmt->bind_param('si', $paymentMethod, $invoiceID);
        $updateStmt->execute();
        
        if ($updateStmt->affected_rows > 0) {
            $conn->commit();
            $_SESSION['message'] = 'Thanh toán thành công! Hóa đơn đã được cập nhật.';
            $_SESSION['message_type'] = 'success';
        } else {
            throw new Exception('Không thể cập nhật trạng thái hóa đơn. Hóa đơn có thể đã được thanh toán.');
        }
        
        $updateStmt->close();
        header('Location: bill_detail.php?id=' . $invoiceID);
        exit;
        
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['message'] = 'Lỗi khi xử lý thanh toán: ' . $e->getMessage();
        $_SESSION['message_type'] = 'error';
    }
}

// Helper format tiền
function formatMoney($amount) {
    return number_format((float)$amount, 0, ',', '.') . ' ₫';
}

// Tạo nội dung chuyển khoản
$transferContent = "KTXSV{$student['StudentCode']} T{$invoice['Month']}/{$invoice['Year']}";

// Thông tin ngân hàng (có thể lấy từ config hoặc DB)
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
    <link rel="stylesheet" href="../../../assets/vendor/fontawesome/css/all.min.css">
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
                    <!-- Trong thực tế, đây sẽ là QR code thật từ VNPay API -->
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
                    <!-- Trong thực tế, đây sẽ là QR code thật từ MoMo API -->
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=<?= urlencode("MoMo|Amount:" . $invoice['TotalAmount'] . "|Content:" . $transferContent) ?>" 
                         alt="MoMo QR Code">
                </div>
                
                <div class="scan-instruction">
                    <p><i class="fas fa-mobile-alt"></i> Mở ứng dụng MoMo trên điện thoại</p>
                    <p><i class="fas fa-camera"></i> Quét mã QR bên trên</p>
                    <p><i class="fas fa-check-circle"></i> Xác nhận thanh toán</p>
                </div>
            </div>
            
        <?php else: // transfer ?>
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
                <p>Hệ thống sẽ tự động xác nhận thanh toán sau khi nhận được tiền (trong vòng 5-10 phút)</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Actions -->
    <div class="payment-actions">
        <?php if ($paymentMethod !== 'transfer'): ?>
        <form method="POST" style="flex: 1;">
            <button type="submit" name="confirm_payment" class="btn btn-primary" 
                    onclick="return confirm('Xác nhận bạn đã thanh toán thành công?')">
                <i class="fas fa-check"></i> Tôi đã thanh toán
            </button>
        </form>
        <?php else: ?>
        <button class="btn btn-primary" disabled>
            <i class="fas fa-clock"></i> Chờ xác nhận thanh toán
        </button>
        <?php endif; ?>
        
        <a href="bills.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Quay lại
        </a>
    </div>
</div>

<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        alert('Đã sao chép: ' + text);
    }).catch(err => {
        console.error('Lỗi sao chép:', err);
    });
}

// Tự động làm mới trang sau 30 giây nếu dùng chuyển khoản
<?php if ($paymentMethod === 'transfer'): ?>
setTimeout(() => {
    if (confirm('Kiểm tra lại trạng thái thanh toán?')) {
        window.location.href = 'bills.php';
    }
}, 30000);
<?php endif; ?>
</script>

</body>
</html>

<?php
require_once '../../../includes/footer.php';
?>
