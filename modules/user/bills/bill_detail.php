<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../../db_connect.php';
require_once '../../../includes/auth_check.php';
requireRole(['Admin','Manager', 'Student']);
$conn->set_charset('utf8mb4');

$userID = (int)$_SESSION['UserID'];
$invoiceID = (int)($_GET['id'] ?? 0);

if ($invoiceID <= 0) {
    $_SESSION['message'] = 'Hóa đơn không hợp lệ!';
    $_SESSION['message_type'] = 'error';
    header('Location: bills.php'); exit;
}

$studentQuery = $conn->prepare("SELECT StudentID, FullName, StudentCode, Email, Phone FROM Students WHERE UserID = ?");
$studentQuery->bind_param('i', $userID);
$studentQuery->execute();
$studentResult = $studentQuery->get_result();
if ($studentResult->num_rows == 0) {
    $_SESSION['message'] = 'Không tìm thấy thông tin sinh viên!';
    $_SESSION['message_type'] = 'error';
    header('Location: bills.php'); exit;
}
$student = $studentResult->fetch_assoc();
$studentID = $student['StudentID'];
$studentQuery->close();

$stmt = $conn->prepare("
    SELECT i.InvoiceID, i.Month, i.Year, i.RoomFee, i.ElectricUsage, i.ElectricPrice,
           i.WaterUsage, i.WaterPrice, i.TotalAmount, i.Status, i.CreatedAt, i.DueDate, i.UpdatedAt,
           r.RoomNumber, r.RoomType, r.RoomPrice, b.BuildingName,
           c.ContractID, c.StudentID, c.StartDate, c.EndDate
    FROM Invoices i
    INNER JOIN Contracts c ON i.ContractID = c.ContractID
    INNER JOIN Rooms r ON c.RoomID = r.RoomID
    INNER JOIN Buildings b ON r.BuildingID = b.BuildingID
    WHERE i.InvoiceID = ? AND c.StudentID = ? LIMIT 1
");
$stmt->bind_param('ii', $invoiceID, $studentID);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 0) {
    $_SESSION['message'] = 'Không tìm thấy hóa đơn!';
    $_SESSION['message_type'] = 'error';
    header('Location: bills.php'); exit;
}
$invoice = $result->fetch_assoc();
$stmt->close();

function formatMoney($amount) { return number_format((float)$amount, 0, ',', '.') . ' ₫'; }

require_once '../../../includes/header.php';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chi tiết hóa đơn #<?= $invoice['InvoiceID'] ?> | Ký túc xá</title>
    <link rel="stylesheet" href="<?= $base ?>assets/css/global.css">
    <link rel="stylesheet" href="<?= $base ?>assets/css/modules_shared.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .bill-dt { max-width:800px; margin:0 auto; padding:30px 20px; }
        .bill-dt-head { background:var(--gradient-primary); color:#fff; border-radius:20px; padding:28px 32px; margin-bottom:24px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; position:relative; overflow:hidden; }
        .bill-dt-head::after { content:''; position:absolute; top:-40%; right:-15%; width:250px; height:250px; background:rgba(255,255,255,0.06); border-radius:50%; }
        .bill-dt-head h1 { font-size:1.2rem; font-weight:800; margin:0; }
        .bill-dt-head .inv-id { font-size:0.88rem; opacity:0.8; }
        .bill-dt-badge { padding:6px 16px; border-radius:999px; font-weight:700; font-size:0.82rem; display:inline-flex; align-items:center; gap:6px; }
        .bill-dt-badge.paid { background:rgba(16,185,129,0.2); color:#10b981; }
        .bill-dt-badge.unpaid { background:rgba(239,68,68,0.2); color:#ef4444; }

        .bill-section { background:var(--surface); border:1px solid var(--stroke); border-radius:16px; padding:24px; margin-bottom:16px; }
        .bill-section h2 { font-size:0.95rem; font-weight:700; margin:0 0 16px; display:flex; align-items:center; gap:8px; color:var(--text); }
        .info-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
        @media(max-width:600px) { .info-grid { grid-template-columns:1fr; } }
        .info-item { padding:10px 12px; background:var(--bg); border-radius:10px; }
        .info-item .lbl { font-size:0.76rem; color:var(--text-secondary); font-weight:600; margin-bottom:2px; }
        .info-item .val { font-size:0.88rem; font-weight:600; color:var(--text); }

        .charges-tbl { width:100%; border-collapse:collapse; }
        .charges-tbl td { padding:12px 14px; border-bottom:1px solid var(--stroke); font-size:0.88rem; }
        .charges-tbl .charge-name { font-weight:600; color:var(--text); display:flex; align-items:center; gap:8px; }
        .charges-tbl .charge-name i { color:var(--primary); width:20px; text-align:center; }
        .charges-tbl .charge-sub { font-size:0.78rem; color:var(--text-secondary); margin-top:2px; padding-left:28px; }
        .charges-tbl .charge-amt { text-align:right; font-weight:600; color:var(--text); }
        .charges-tbl .total-row td { border-top:2px solid var(--primary); border-bottom:none; font-weight:800; font-size:1rem; }
        .charges-tbl .total-row .charge-amt { color:var(--primary); }

        .due-warn { background:rgba(239,68,68,0.08); border:1px solid rgba(239,68,68,0.2); border-radius:12px; padding:14px; margin-top:14px; font-size:0.85rem; color:#ef4444; display:flex; align-items:center; gap:10px; }
        .paid-info { background:rgba(16,185,129,0.08); border:1px solid rgba(16,185,129,0.2); border-radius:12px; padding:14px; margin-top:14px; font-size:0.85rem; color:#10b981; display:flex; align-items:flex-start; gap:10px; }
        .paid-info i { font-size:1.2rem; margin-top:2px; }

        .timeline { position:relative; padding-left:24px; }
        .timeline::before { content:''; position:absolute; left:7px; top:0; bottom:0; width:2px; background:var(--stroke); }
        .tl-item { position:relative; margin-bottom:14px; }
        .tl-item::before { content:''; position:absolute; left:-20px; top:4px; width:10px; height:10px; border-radius:50%; background:var(--primary); border:2px solid var(--surface); }
        .tl-date { font-size:0.78rem; color:var(--text-secondary); }
        .tl-text { font-size:0.88rem; font-weight:600; color:var(--text); }

        .bill-actions { display:flex; gap:10px; flex-wrap:wrap; }
    </style>
</head>
<body>
    <div class="bill-dt">
        <div class="bill-dt-head">
            <div>
                <h1><i class="fas fa-file-invoice-dollar"></i> HÓA ĐƠN KÝ TÚC XÁ</h1>
                <div class="inv-id">Mã: #<?= str_pad($invoice['InvoiceID'], 6, '0', STR_PAD_LEFT) ?></div>
            </div>
            <span class="bill-dt-badge <?= $invoice['Status'] === 'Đã thanh toán' ? 'paid' : 'unpaid' ?>">
                <i class="fas fa-<?= $invoice['Status'] === 'Đã thanh toán' ? 'check-circle' : 'clock' ?>"></i>
                <?= htmlspecialchars($invoice['Status']) ?>
            </span>
        </div>

        <!-- Student -->
        <div class="bill-section">
            <h2><i class="fas fa-user"></i> Thông tin sinh viên</h2>
            <div class="info-grid">
                <div class="info-item"><div class="lbl">Họ và tên</div><div class="val"><?= htmlspecialchars($student['FullName']) ?></div></div>
                <div class="info-item"><div class="lbl">MSSV</div><div class="val"><?= htmlspecialchars($student['StudentCode']) ?></div></div>
                <div class="info-item"><div class="lbl">Email</div><div class="val"><?= htmlspecialchars($student['Email']) ?></div></div>
                <div class="info-item"><div class="lbl">Điện thoại</div><div class="val"><?= htmlspecialchars($student['Phone']) ?></div></div>
            </div>
        </div>

        <!-- Room -->
        <div class="bill-section">
            <h2><i class="fas fa-home"></i> Thông tin phòng</h2>
            <div class="info-grid">
                <div class="info-item"><div class="lbl">Tòa nhà</div><div class="val"><?= htmlspecialchars($invoice['BuildingName']) ?></div></div>
                <div class="info-item"><div class="lbl">Phòng</div><div class="val"><?= htmlspecialchars($invoice['RoomNumber']) ?></div></div>
                <div class="info-item"><div class="lbl">Loại phòng</div><div class="val"><?= htmlspecialchars($invoice['RoomType']) ?></div></div>
                <div class="info-item"><div class="lbl">Giá phòng</div><div class="val"><?= formatMoney($invoice['RoomPrice']) ?>/tháng</div></div>
            </div>
        </div>

        <!-- Period -->
        <div class="bill-section">
            <h2><i class="fas fa-calendar-alt"></i> Kỳ thanh toán</h2>
            <div class="info-grid">
                <div class="info-item"><div class="lbl">Kỳ</div><div class="val">Tháng <?= $invoice['Month'] ?>/<?= $invoice['Year'] ?></div></div>
                <div class="info-item"><div class="lbl">Ngày lập</div><div class="val"><?= date('d/m/Y H:i', strtotime($invoice['CreatedAt'])) ?></div></div>
                <?php if ($invoice['DueDate']): ?>
                    <div class="info-item"><div class="lbl">Hạn thanh toán</div><div class="val"><?= date('d/m/Y', strtotime($invoice['DueDate'])) ?></div></div>
                <?php endif; ?>
                <?php if ($invoice['Status'] === 'Đã thanh toán' && $invoice['UpdatedAt']): ?>
                    <div class="info-item"><div class="lbl">Ngày thanh toán</div><div class="val"><?= date('d/m/Y H:i', strtotime($invoice['UpdatedAt'])) ?></div></div>
                <?php endif; ?>
            </div>
            <?php if ($invoice['Status'] === 'Chưa thanh toán' && $invoice['DueDate']):
                $daysLeft = (strtotime($invoice['DueDate']) - time()) / 86400;
                if ($daysLeft < 7): ?>
                    <div class="due-warn"><i class="fas fa-exclamation-triangle"></i> Hóa đơn sẽ đến hạn trong <?= ceil($daysLeft) ?> ngày. Vui lòng thanh toán sớm!</div>
            <?php endif; endif; ?>
        </div>

        <!-- Charges -->
        <div class="bill-section">
            <h2><i class="fas fa-list-ul"></i> Chi tiết khoản phí</h2>
            <table class="charges-tbl">
                <tr><td><div class="charge-name"><i class="fas fa-bed"></i> Tiền phòng</div><div class="charge-sub">Tháng <?= $invoice['Month'] ?>/<?= $invoice['Year'] ?></div></td><td class="charge-amt"><?= formatMoney($invoice['RoomFee']) ?></td></tr>
                <tr><td><div class="charge-name"><i class="fas fa-bolt"></i> Tiền điện</div><div class="charge-sub"><?= number_format($invoice['ElectricUsage'], 1) ?> kWh</div></td><td class="charge-amt"><?= formatMoney($invoice['ElectricPrice']) ?></td></tr>
                <tr><td><div class="charge-name"><i class="fas fa-tint"></i> Tiền nước</div><div class="charge-sub"><?= number_format($invoice['WaterUsage'], 1) ?> m³</div></td><td class="charge-amt"><?= formatMoney($invoice['WaterPrice']) ?></td></tr>
                <tr class="total-row"><td><strong>TỔNG CỘNG</strong></td><td class="charge-amt"><?= formatMoney($invoice['TotalAmount']) ?></td></tr>
            </table>
            <?php if ($invoice['Status'] === 'Đã thanh toán'): ?>
                <div class="paid-info"><i class="fas fa-check-circle"></i><div><strong>Hóa đơn đã được thanh toán</strong><br>Ngày: <?= date('d/m/Y H:i', strtotime($invoice['UpdatedAt'])) ?></div></div>
            <?php endif; ?>
        </div>

        <!-- Timeline -->
        <div class="bill-section">
            <h2><i class="fas fa-history"></i> Lịch sử</h2>
            <div class="timeline">
                <div class="tl-item"><div class="tl-date"><?= date('d/m/Y H:i', strtotime($invoice['CreatedAt'])) ?></div><div class="tl-text">Hóa đơn được tạo</div></div>
                <?php if ($invoice['Status'] === 'Đã thanh toán'): ?>
                    <div class="tl-item"><div class="tl-date"><?= date('d/m/Y H:i', strtotime($invoice['UpdatedAt'])) ?></div><div class="tl-text">Đã thanh toán - <?= formatMoney($invoice['TotalAmount']) ?></div></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Actions -->
        <div class="bill-actions">
            <?php if ($invoice['Status'] === 'Chưa thanh toán'): ?>
                <a href="#" class="mod-btn mod-btn-primary" onclick="showPaymentOptions(<?= $invoice['InvoiceID'] ?>); return false;"><i class="fas fa-credit-card"></i> Thanh toán ngay</a>
            <?php endif; ?>
            <button class="mod-btn mod-btn-outline" onclick="window.print()"><i class="fas fa-print"></i> In hóa đơn</button>
            <a href="bills.php" class="mod-btn mod-btn-outline"><i class="fas fa-arrow-left"></i> Quay lại</a>
        </div>
    </div>

    <script>
    function showPaymentOptions(id) {
        Swal.fire({
            title: 'Phương thức thanh toán',
            html: `
                <div style="display:flex;flex-direction:column;gap:12px;margin-top:16px;">
                    <button onclick="window.location.href='bill_pay.php?id=${id}&method=vnpay'" style="padding:14px;background:#0088cc;color:#fff;border:none;border-radius:10px;cursor:pointer;font-size:0.95rem;font-weight:600;"><i class="fas fa-qrcode"></i> VNPay QR</button>
                    <button onclick="window.location.href='bill_pay.php?id=${id}&method=momo'" style="padding:14px;background:#d82d8b;color:#fff;border:none;border-radius:10px;cursor:pointer;font-size:0.95rem;font-weight:600;"><i class="fas fa-qrcode"></i> MoMo QR</button>
                    <button onclick="window.location.href='bill_pay.php?id=${id}&method=transfer'" style="padding:14px;background:#10b981;color:#fff;border:none;border-radius:10px;cursor:pointer;font-size:0.95rem;font-weight:600;"><i class="fas fa-university"></i> Tiền mặt</button>
                </div>
            `,
            showConfirmButton: false,
            showCloseButton: true
        });
    }
    </script>

    <?php require_once '../../../includes/footer.php'; ?>
</body>
</html>
