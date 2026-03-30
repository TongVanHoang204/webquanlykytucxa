<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../../db_connect.php';
require_once '../../../includes/auth_check.php';
requireRole(['Student']);
$conn->set_charset('utf8mb4');

$userID = (int)$_SESSION['UserID'];
$invoiceID = (int)($_GET['id'] ?? 0);
$paymentMethod = $_GET['method'] ?? '';

if ($invoiceID <= 0) { $_SESSION['message'] = 'Hóa đơn không hợp lệ!'; $_SESSION['message_type'] = 'error'; header('Location: bills.php'); exit; }
if (!in_array($paymentMethod, ['vnpay', 'momo', 'transfer'])) { $_SESSION['message'] = 'Phương thức không hợp lệ!'; $_SESSION['message_type'] = 'error'; header('Location: bills.php'); exit; }

$studentQuery = $conn->prepare("SELECT StudentID, FullName, StudentCode FROM Students WHERE UserID = ?");
$studentQuery->bind_param('i', $userID); $studentQuery->execute(); $studentResult = $studentQuery->get_result();
if ($studentResult->num_rows == 0) { $_SESSION['message'] = 'Không tìm thấy SV!'; header('Location: bills.php'); exit; }
$student = $studentResult->fetch_assoc(); $studentID = $student['StudentID']; $studentQuery->close();

$stmt = $conn->prepare("SELECT i.*, r.RoomNumber, b.BuildingName, c.ContractID, c.StudentID FROM Invoices i INNER JOIN Contracts c ON i.ContractID = c.ContractID INNER JOIN Rooms r ON c.RoomID = r.RoomID INNER JOIN Buildings b ON r.BuildingID = b.BuildingID WHERE i.InvoiceID = ? AND c.StudentID = ? LIMIT 1");
$stmt->bind_param('ii', $invoiceID, $studentID); $stmt->execute(); $result = $stmt->get_result();
if ($result->num_rows == 0) { $_SESSION['message'] = 'Không tìm thấy hóa đơn!'; header('Location: bills.php'); exit; }
$invoice = $result->fetch_assoc(); $stmt->close();
if ($invoice['Status'] === 'Đã thanh toán') { $_SESSION['message'] = 'Hóa đơn đã thanh toán!'; header('Location: bills.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_payment'])) {
    try {
        $conn->begin_transaction();
        $checkCol = $conn->query("SHOW COLUMNS FROM Invoices LIKE 'PaymentDate'");
        if ($checkCol->num_rows === 0) {
            $conn->query("ALTER TABLE Invoices ADD COLUMN PaymentDate DATETIME NULL AFTER Status");
            $conn->query("ALTER TABLE Invoices ADD COLUMN PaymentMethod VARCHAR(50) NULL AFTER PaymentDate");
        }
        $upd = $conn->prepare("UPDATE Invoices SET Status='Đã thanh toán', PaymentDate=NOW(), PaymentMethod=?, UpdatedAt=NOW() WHERE InvoiceID=? AND Status='Chưa thanh toán'");
        $upd->bind_param('si', $paymentMethod, $invoiceID); $upd->execute();
        if ($upd->affected_rows > 0) { $conn->commit(); $_SESSION['message'] = 'Thanh toán thành công!'; $_SESSION['message_type'] = 'success'; }
        else { throw new Exception('Không thể cập nhật.'); }
        $upd->close();
        header('Location: bill_detail.php?id=' . $invoiceID); exit;
    } catch (Exception $e) { $conn->rollback(); $_SESSION['message'] = 'Lỗi: ' . $e->getMessage(); $_SESSION['message_type'] = 'error'; }
}

function formatMoney($a) { return number_format((float)$a, 0, ',', '.') . ' ₫'; }
$transferContent = "KTXSV{$student['StudentCode']} T{$invoice['Month']}/{$invoice['Year']}";
$bankInfo = ['name' => 'Vietcombank', 'account_number' => '1234567890', 'account_name' => 'KY TUC XA SINH VIEN', 'branch' => 'Chi nhánh TP.HCM'];

require_once '../../../includes/header.php';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thanh toán hóa đơn | Ký túc xá</title>
    <link rel="stylesheet" href="<?= $base ?>assets/css/global.css">
    <link rel="stylesheet" href="<?= $base ?>assets/css/modules_shared.css">
    <style>
        .pay-pg { max-width:700px; margin:0 auto; padding:30px 20px; }
        .pay-head { background:var(--gradient-primary); color:#fff; border-radius:20px; padding:28px 32px; margin-bottom:24px; position:relative; overflow:hidden; }
        .pay-head::after { content:''; position:absolute; top:-40%; right:-15%; width:250px; height:250px; background:rgba(255,255,255,0.06); border-radius:50%; }
        .pay-head h2 { font-size:1.2rem; font-weight:800; margin:0 0 6px; }
        .pay-head p { font-size:0.88rem; opacity:0.8; margin:0; }

        .pay-box { background:var(--surface); border:1px solid var(--stroke); border-radius:16px; padding:24px; margin-bottom:16px; }
        .pay-box h3 { font-size:0.95rem; font-weight:700; margin:0 0 16px; display:flex; align-items:center; gap:8px; color:var(--text); }
        .sum-row { display:flex; justify-content:space-between; padding:8px 0; font-size:0.88rem; border-bottom:1px solid var(--stroke); }
        .sum-row:last-child { border-bottom:none; }
        .sum-row.total { font-weight:800; font-size:1rem; color:var(--primary); border-top:2px solid var(--primary); border-bottom:none; padding-top:12px; margin-top:4px; }

        .method-pill { display:inline-flex; align-items:center; gap:8px; padding:8px 18px; border-radius:999px; font-weight:700; font-size:0.88rem; margin-bottom:16px; }
        .method-pill.vnpay { background:rgba(0,136,204,0.1); color:#0088cc; }
        .method-pill.momo { background:rgba(216,45,139,0.1); color:#d82d8b; }
        .method-pill.transfer { background:rgba(16,185,129,0.1); color:#10b981; }

        .qr-section { text-align:center; padding:20px 0; }
        .qr-section img { border-radius:16px; border:2px solid var(--stroke); padding:8px; background:#fff; }
        .qr-steps { margin-top:16px; text-align:left; }
        .qr-steps p { font-size:0.85rem; color:var(--text-secondary); margin:6px 0; display:flex; align-items:center; gap:8px; }
        .qr-steps i { color:var(--primary); width:18px; text-align:center; }

        .bank-grid { display:grid; gap:10px; }
        .bank-row { display:flex; justify-content:space-between; align-items:center; padding:10px 12px; background:var(--bg); border-radius:10px; flex-wrap:wrap; gap:6px; }
        .bank-row .lbl { font-size:0.82rem; color:var(--text-secondary); font-weight:600; }
        .bank-row .val { font-size:0.88rem; font-weight:600; color:var(--text); display:flex; align-items:center; gap:8px; }
        .copy-btn { padding:4px 10px; font-size:0.75rem; border:1px solid var(--stroke); background:var(--surface); border-radius:6px; cursor:pointer; color:var(--primary); font-weight:600; transition:all 0.2s; }
        .copy-btn:hover { background:var(--primary); color:#fff; }

        .pay-note { background:rgba(239,68,68,0.06); border:1px solid rgba(239,68,68,0.15); border-radius:12px; padding:14px; margin-top:16px; font-size:0.84rem; color:#ef4444; }
        .pay-note strong { display:flex; align-items:center; gap:6px; margin-bottom:6px; }

        .pay-actions { display:flex; gap:10px; flex-wrap:wrap; }
    </style>
</head>
<body>
    <div class="pay-pg">
        <div class="pay-head">
            <h2><i class="fas fa-credit-card"></i> Thanh toán hóa đơn</h2>
            <p>Phòng <?= htmlspecialchars($invoice['RoomNumber']) ?> - Tòa <?= htmlspecialchars($invoice['BuildingName']) ?> - T<?= $invoice['Month'] ?>/<?= $invoice['Year'] ?></p>
        </div>

        <!-- Summary -->
        <div class="pay-box">
            <h3><i class="fas fa-file-invoice"></i> Thông tin hóa đơn</h3>
            <div class="sum-row"><span>Mã hóa đơn</span><span>#<?= $invoice['InvoiceID'] ?></span></div>
            <div class="sum-row"><span>Sinh viên</span><span><?= htmlspecialchars($student['FullName']) ?> (<?= $student['StudentCode'] ?>)</span></div>
            <div class="sum-row"><span>Tiền phòng</span><span><?= formatMoney($invoice['RoomFee']) ?></span></div>
            <div class="sum-row"><span>Điện (<?= $invoice['ElectricUsage'] ?> kWh)</span><span><?= formatMoney($invoice['ElectricPrice']) ?></span></div>
            <div class="sum-row"><span>Nước (<?= $invoice['WaterUsage'] ?> m³)</span><span><?= formatMoney($invoice['WaterPrice']) ?></span></div>
            <div class="sum-row total"><span>Tổng cộng</span><span><?= formatMoney($invoice['TotalAmount']) ?></span></div>
            <?php if ($invoice['DueDate']): ?>
                <div class="sum-row"><span>Hạn thanh toán</span><span><?= date('d/m/Y', strtotime($invoice['DueDate'])) ?></span></div>
            <?php endif; ?>
        </div>

        <!-- Method -->
        <div class="pay-box">
            <h3><i class="fas fa-wallet"></i> Phương thức thanh toán</h3>

            <?php if ($paymentMethod === 'vnpay'): ?>
                <span class="method-pill vnpay"><i class="fas fa-qrcode"></i> VNPay QR</span>
                <div class="qr-section">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=<?= urlencode("VNPay|Amount:" . $invoice['TotalAmount'] . "|Content:" . $transferContent) ?>" alt="VNPay QR">
                    <div class="qr-steps">
                        <p><i class="fas fa-mobile-alt"></i> Mở ứng dụng VNPay</p>
                        <p><i class="fas fa-camera"></i> Quét mã QR</p>
                        <p><i class="fas fa-check-circle"></i> Xác nhận thanh toán</p>
                    </div>
                </div>

            <?php elseif ($paymentMethod === 'momo'): ?>
                <span class="method-pill momo"><i class="fas fa-qrcode"></i> MoMo QR</span>
                <div class="qr-section">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=<?= urlencode("MoMo|Amount:" . $invoice['TotalAmount'] . "|Content:" . $transferContent) ?>" alt="MoMo QR">
                    <div class="qr-steps">
                        <p><i class="fas fa-mobile-alt"></i> Mở ứng dụng MoMo</p>
                        <p><i class="fas fa-camera"></i> Quét mã QR</p>
                        <p><i class="fas fa-check-circle"></i> Xác nhận thanh toán</p>
                    </div>
                </div>

            <?php else: ?>
                <span class="method-pill transfer"><i class="fas fa-university"></i> Chuyển khoản</span>
                <div class="bank-grid">
                    <div class="bank-row"><span class="lbl">Ngân hàng</span><span class="val"><?= $bankInfo['name'] ?></span></div>
                    <div class="bank-row"><span class="lbl">Số TK</span><span class="val"><?= $bankInfo['account_number'] ?> <button class="copy-btn" onclick="copyText('<?= $bankInfo['account_number'] ?>')"><i class="fas fa-copy"></i></button></span></div>
                    <div class="bank-row"><span class="lbl">Chủ TK</span><span class="val"><?= $bankInfo['account_name'] ?></span></div>
                    <div class="bank-row"><span class="lbl">Chi nhánh</span><span class="val"><?= $bankInfo['branch'] ?></span></div>
                    <div class="bank-row"><span class="lbl">Số tiền</span><span class="val"><?= formatMoney($invoice['TotalAmount']) ?> <button class="copy-btn" onclick="copyText('<?= $invoice['TotalAmount'] ?>')"><i class="fas fa-copy"></i></button></span></div>
                    <div class="bank-row"><span class="lbl">Nội dung</span><span class="val"><?= $transferContent ?> <button class="copy-btn" onclick="copyText('<?= $transferContent ?>')"><i class="fas fa-copy"></i></button></span></div>
                </div>
                <div class="pay-note">
                    <strong><i class="fas fa-exclamation-triangle"></i> Lưu ý quan trọng</strong>
                    <p>Ghi chính xác nội dung: <strong><?= $transferContent ?></strong></p>
                    <p>Hệ thống tự xác nhận sau 5-10 phút.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Actions -->
        <div class="pay-actions">
            <?php if ($paymentMethod !== 'transfer'): ?>
                <form method="POST" style="flex:1;">
                    <button type="submit" name="confirm_payment" class="mod-btn mod-btn-primary" onclick="return confirm('Xác nhận đã thanh toán?')">
                        <i class="fas fa-check"></i> Tôi đã thanh toán
                    </button>
                </form>
            <?php else: ?>
                <button class="mod-btn mod-btn-outline" disabled><i class="fas fa-clock"></i> Chờ xác nhận</button>
            <?php endif; ?>
            <a href="bills.php" class="mod-btn mod-btn-outline"><i class="fas fa-arrow-left"></i> Quay lại</a>
        </div>
    </div>

    <script>
    function copyText(text) {
        navigator.clipboard.writeText(text).then(() => {
            const el = event.target.closest('.copy-btn');
            const orig = el.innerHTML;
            el.innerHTML = '<i class="fas fa-check"></i>';
            setTimeout(() => el.innerHTML = orig, 1500);
        });
    }
    <?php if ($paymentMethod === 'transfer'): ?>
    setTimeout(() => { if (confirm('Kiểm tra trạng thái thanh toán?')) window.location.href = 'bills.php'; }, 30000);
    <?php endif; ?>
    </script>

    <?php require_once '../../../includes/footer.php'; ?>
</body>
</html>
