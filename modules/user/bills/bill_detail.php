<?php
// ==============================
// 📄 CHI TIẾT HÓA ĐƠN
// ==============================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../../db_connect.php';
require_once '../../../includes/auth_check.php';
requireRole(['Admin','Manager' , 'Student']);

$conn->set_charset('utf8mb4');

$userID = (int)$_SESSION['UserID'];
$invoiceID = (int)($_GET['id'] ?? 0);

// Kiểm tra tham số
if ($invoiceID <= 0) {
    $_SESSION['message'] = 'Hóa đơn không hợp lệ!';
    $_SESSION['message_type'] = 'error';
    header('Location: bills.php');
    exit;
}

// Lấy thông tin sinh viên
$studentQuery = $conn->prepare("SELECT StudentID, FullName, StudentCode, Email, Phone FROM Students WHERE UserID = ?");
if (!$studentQuery) {
    die("Lỗi prepare: " . $conn->error);
}
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

// Lấy thông tin chi tiết hóa đơn
$sql = "
    SELECT 
        i.InvoiceID, i.Month, i.Year,
        i.RoomFee, i.ElectricUsage, i.ElectricPrice,
        i.WaterUsage, i.WaterPrice, i.TotalAmount,
        i.Status, i.CreatedAt, i.DueDate, i.UpdatedAt,
        r.RoomNumber, r.RoomType, r.RoomPrice,
        b.BuildingName,
        c.ContractID, c.StudentID, c.StartDate, c.EndDate
    FROM Invoices i
    INNER JOIN Contracts c ON i.ContractID = c.ContractID
    INNER JOIN Rooms r ON c.RoomID = r.RoomID
    INNER JOIN Buildings b ON r.BuildingID = b.BuildingID
    WHERE i.InvoiceID = ? AND c.StudentID = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Lỗi prepare invoice: " . $conn->error);
}
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

// Tính toán các giá trị
$electricCost = $invoice['ElectricUsage'] * 3000; // Giá mỗi kWh (có thể lấy từ config)
$waterCost = $invoice['WaterUsage'] * 15000; // Giá mỗi m³ (có thể lấy từ config)

// Helper format tiền
function formatMoney($amount) {
    return number_format((float)$amount, 0, ',', '.') . ' ₫';
}

require_once '../../../includes/header.php';
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chi tiết hóa đơn #<?= $invoice['InvoiceID'] ?> - Ký Túc Xá</title>
    <link rel="stylesheet" href="../../../assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/user/bills/bill_detail.css">

</head>
<body>

<div class="detail-container">
    <!-- Header -->
    <div class="detail-header">
        <h1><i class="fas fa-file-invoice-dollar"></i> HÓA ĐƠN KÝ TÚC XÁ</h1>
        <div class="invoice-number">Mã hóa đơn: #<?= str_pad($invoice['InvoiceID'], 6, '0', STR_PAD_LEFT) ?></div>
        <div class="status-badge-large <?= $invoice['Status'] === 'Đã thanh toán' ? 'status-paid' : 'status-unpaid' ?>">
            <i class="fas fa-<?= $invoice['Status'] === 'Đã thanh toán' ? 'check-circle' : 'clock' ?>"></i>
            <?= htmlspecialchars($invoice['Status']) ?>
        </div>
    </div>

    <!-- Thông tin sinh viên và phòng -->
    <div class="info-section">
        <h2><i class="fas fa-user"></i> Thông tin sinh viên</h2>
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Họ và tên</div>
                <div class="info-value"><?= htmlspecialchars($student['FullName']) ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Mã sinh viên</div>
                <div class="info-value"><?= htmlspecialchars($student['StudentCode']) ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Email</div>
                <div class="info-value"><?= htmlspecialchars($student['Email']) ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Số điện thoại</div>
                <div class="info-value"><?= htmlspecialchars($student['Phone']) ?></div>
            </div>
        </div>
    </div>

    <!-- Thông tin phòng -->
    <div class="info-section">
        <h2><i class="fas fa-home"></i> Thông tin phòng</h2>
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Tòa nhà</div>
                <div class="info-value"><?= htmlspecialchars($invoice['BuildingName']) ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Số phòng</div>
                <div class="info-value"><?= htmlspecialchars($invoice['RoomNumber']) ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Loại phòng</div>
                <div class="info-value"><?= htmlspecialchars($invoice['RoomType']) ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Giá phòng</div>
                <div class="info-value"><?= formatMoney($invoice['RoomPrice']) ?>/tháng</div>
            </div>
        </div>
    </div>

    <!-- Thông tin kỳ hóa đơn -->
    <div class="info-section">
        <h2><i class="fas fa-calendar-alt"></i> Kỳ thanh toán</h2>
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Tháng/Năm</div>
                <div class="info-value">Tháng <?= $invoice['Month'] ?> / <?= $invoice['Year'] ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Ngày lập hóa đơn</div>
                <div class="info-value"><?= date('d/m/Y H:i', strtotime($invoice['CreatedAt'])) ?></div>
            </div>
            <?php if ($invoice['DueDate']): ?>
            <div class="info-item">
                <div class="info-label">Hạn thanh toán</div>
                <div class="info-value"><?= date('d/m/Y', strtotime($invoice['DueDate'])) ?></div>
            </div>
            <?php endif; ?>
            <?php if ($invoice['Status'] === 'Đã thanh toán' && $invoice['UpdatedAt']): ?>
            <div class="info-item">
                <div class="info-label">Ngày thanh toán</div>
                <div class="info-value"><?= date('d/m/Y H:i', strtotime($invoice['UpdatedAt'])) ?></div>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if ($invoice['Status'] === 'Chưa thanh toán' && $invoice['DueDate']): 
            $daysLeft = (strtotime($invoice['DueDate']) - time()) / (60 * 60 * 24);
            if ($daysLeft < 7):
        ?>
        <div class="due-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>Lưu ý:</strong> Hóa đơn sẽ đến hạn trong <?= ceil($daysLeft) ?> ngày. 
            Vui lòng thanh toán trước hạn để tránh phụ phí!
        </div>
        <?php endif; endif; ?>
    </div>

    <!-- Chi tiết các khoản phí -->
    <div class="info-section">
        <h2><i class="fas fa-list-ul"></i> Chi tiết các khoản phí</h2>
        
        <table class="charges-table">
            <thead>
                <tr>
                    <th>Khoản phí</th>
                    <th style="text-align: right;">Số tiền</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <div class="charge-name">
                            <i class="fas fa-bed"></i> Tiền phòng
                        </div>
                        <div class="charge-detail">Tháng <?= $invoice['Month'] ?>/<?= $invoice['Year'] ?></div>
                    </td>
                    <td class="charge-amount"><?= formatMoney($invoice['RoomFee']) ?></td>
                </tr>
                
                <tr>
                    <td>
                        <div class="charge-name">
                            <i class="fas fa-bolt"></i> Tiền điện
                        </div>
                        <div class="charge-detail">
                            <?= number_format($invoice['ElectricUsage'], 1) ?> kWh × 3.000 ₫
                        </div>
                    </td>
                    <td class="charge-amount"><?= formatMoney($invoice['ElectricPrice']) ?></td>
                </tr>
                
                <tr>
                    <td>
                        <div class="charge-name">
                            <i class="fas fa-tint"></i> Tiền nước
                        </div>
                        <div class="charge-detail">
                            <?= number_format($invoice['WaterUsage'], 1) ?> m³ × 15.000 ₫
                        </div>
                    </td>
                    <td class="charge-amount"><?= formatMoney($invoice['WaterPrice']) ?></td>
                </tr>
                
                <tr class="total-row">
                    <td><strong>TỔNG CỘNG</strong></td>
                    <td class="charge-amount"><?= formatMoney($invoice['TotalAmount']) ?></td>
                </tr>
            </tbody>
        </table>
        
        <?php if ($invoice['Status'] === 'Đã thanh toán'): ?>
        <div class="payment-info">
            <i class="fas fa-check-circle"></i>
            <strong>Hóa đơn đã được thanh toán</strong>
            <p>Ngày thanh toán: <?= date('d/m/Y H:i', strtotime($invoice['UpdatedAt'])) ?></p>
            <p>Cảm ơn bạn đã thanh toán đúng hạn!</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Lịch sử -->
    <div class="info-section">
        <h2><i class="fas fa-history"></i> Lịch sử hóa đơn</h2>
        <div class="timeline">
            <div class="timeline-item">
                <div class="timeline-date"><?= date('d/m/Y H:i', strtotime($invoice['CreatedAt'])) ?></div>
                <div class="timeline-content">Hóa đơn được tạo</div>
            </div>
            
            <?php if ($invoice['Status'] === 'Đã thanh toán'): ?>
            <div class="timeline-item">
                <div class="timeline-date"><?= date('d/m/Y H:i', strtotime($invoice['UpdatedAt'])) ?></div>
                <div class="timeline-content">Đã thanh toán - <?= formatMoney($invoice['TotalAmount']) ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Action buttons -->
    <div class="action-buttons">
        <?php if ($invoice['Status'] === 'Chưa thanh toán'): ?>
        <a href="#" class="btn btn-success" onclick="showPaymentOptions(<?= $invoice['InvoiceID'] ?>); return false;">
            <i class="fas fa-credit-card"></i> Thanh toán ngay
        </a>
        <?php endif; ?>
        
        <button class="btn btn-primary" onclick="window.print()">
            <i class="fas fa-print"></i> In hóa đơn
        </button>
        
        <a href="bills.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Quay lại
        </a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function showPaymentOptions(invoiceId) {
    Swal.fire({
        title: 'Chọn phương thức thanh toán',
        html: `
            <div style="display: flex; flex-direction: column; gap: 15px; margin-top: 20px;">
                <button onclick="payWithMethod('vnpay', ${invoiceId})" 
                        style="padding: 15px; background: #0088cc; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 16px;">
                    <i class="fas fa-qrcode"></i> VNPay QR
                </button>
                <button onclick="payWithMethod('momo', ${invoiceId})" 
                        style="padding: 15px; background: #d82d8b; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 16px;">
                    <i class="fas fa-qrcode"></i> MoMo QR
                </button>
                <button onclick="payWithMethod('transfer', ${invoiceId})" 
                        style="padding: 15px; background: #28a745; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 16px;">
                    <i class="fas fa-university"></i> Tiền mặt
                </button>
            </div>
        `,
        showConfirmButton: false,
        showCloseButton: true,
        width: 500
    });
}

function payWithMethod(method, invoiceId) {
    window.location.href = `bill_pay.php?id=${invoiceId}&method=${method}`;
}
</script>

</body>
</html>

<?php
require_once '../../../includes/footer.php';
?>
