<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include '../../../db_connect.php';
include '../../../includes/admin_header.php';
include '../../../includes/auth_check.php';
requireRole(['Admin', 'Manager']);

if (empty($_SESSION['_csrf'])) {
    $_SESSION['_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['_csrf'];

$id = $_GET['id'] ?? 0;
if (!$id) {
    echo "<script>alert('Thiếu ID hóa đơn!'); window.location='invoice_list.php';</script>";
    exit;
}

// 🔍 Lấy thông tin chi tiết hóa đơn
$sql = "
    SELECT 
        i.*,
        c.StartDate, c.EndDate,
        s.FullName, s.StudentCode, s.Email, s.Phone,
        r.RoomNumber, r.RoomPrice, b.BuildingName
    FROM Invoices i
    JOIN Contracts c ON i.ContractID = c.ContractID
    JOIN Students s ON c.StudentID = s.StudentID
    JOIN Rooms r ON c.RoomID = r.RoomID
    JOIN Buildings b ON r.BuildingID = b.BuildingID
    WHERE i.InvoiceID = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$invoice = $stmt->get_result()->fetch_assoc();

if (!$invoice) {
    echo "<script>alert('Không tìm thấy hóa đơn!'); window.location='invoice_list.php';</script>";
    exit;
}

// 💰 Tính các khoản phí
$roomFee = $invoice['RoomFee'] ?? 0;
$electricFee = ($invoice['ElectricUsage'] ?? 0) * ($invoice['ElectricPrice'] ?? 0);
$waterFee = ($invoice['WaterUsage'] ?? 0) * ($invoice['WaterPrice'] ?? 0);
$totalAmount = $invoice['TotalAmount'] ?? ($roomFee + $electricFee + $waterFee);

// 📅 Kiểm tra hạn thanh toán
$isOverdue = false;
$daysOverdue = 0;
if ($invoice['Status'] === 'Chưa thanh toán' && !empty($invoice['DueDate'])) {
    $dueDate = new DateTime($invoice['DueDate']);
    $today = new DateTime();
    if ($today > $dueDate) {
        $isOverdue = true;
        $daysOverdue = $today->diff($dueDate)->days;
    }
}

function formatMoney($n)
{
    return number_format($n, 0, ',', '.') . ' ₫';
}

function getStatusClass($status)
{
    switch ($status) {
        case 'Đã thanh toán':
            return 'paid';
        case 'Chưa thanh toán':
            return 'unpaid';
        case 'Quá hạn':
            return 'overdue';
        default:
            return 'unpaid';
    }
}

function getStatusIcon($status)
{
    switch ($status) {
        case 'Đã thanh toán':
            return '✅';
        case 'Chưa thanh toán':
            return '⏳';
        case 'Quá hạn':
            return '⚠️';
        default:
            return '📝';
    }
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chi tiết hóa đơn #<?= $invoice['InvoiceID'] ?> | Hệ thống Ký túc xá</title>
    <link rel="stylesheet" href="../../assets/css/admin/admin_header.css">
    <link rel="stylesheet" href="../../../assets/css/staff/invoice/staff_invoice_detail.css">
    <link rel="stylesheet" href="../../../assets/vendor/fontawesome/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>
    <div class="invoice-detail-container">
        <div class="header">
            <h2><i class="fas fa-file-invoice"></i> Chi tiết hóa đơn #<?= $invoice['InvoiceID'] ?></h2>
            <a href="invoice_list.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Quay lại danh sách
            </a>
        </div>

        <?php if ($invoice['Status'] === 'Đã thanh toán'): ?>
            <div class="paid-state">
                <i class="fas fa-check-circle"></i>
                <h4>Hóa đơn đã được thanh toán</h4>
                <p><strong>Thời gian thanh toán:</strong> <?= !empty($invoice['PaidAt']) ? date('H:i d/m/Y', strtotime($invoice['PaidAt'])) : 'Không xác định' ?></p>
            </div>
        <?php elseif ($isOverdue): ?>
            <div class="due-date-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <div class="content">
                    <h4>Hóa đơn đã quá hạn!</h4>
                    <p>Đã quá hạn thanh toán <?= $daysOverdue === 0 ? 'hôm nay' : 
                                                 $daysOverdue . ' ngày' ?>. 
                    Hạn thanh toán: <?= date('d/m/Y', strtotime($invoice['DueDate'])) ?></p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Thông tin meta -->
        <div class="invoice-meta">
            <div class="meta-item">
                <div class="label">Tổng tiền</div>
                <div class="value"><?= formatMoney($totalAmount) ?></div>
            </div>
            <div class="meta-item">
                <div class="label">Trạng thái</div>
                <div class="value <?= getStatusClass($invoice['Status']) ?>">
                    <?= getStatusIcon($invoice['Status']) ?> <?= $invoice['Status'] ?>
                </div>
            </div>
            <div class="meta-item">
                <div class="label">Tháng/Năm</div>
                <div class="value"><?= $invoice['Month'] ?>/<?= $invoice['Year'] ?></div>
            </div>
            <div class="meta-item">
                <div class="label">Hạn thanh toán</div>
                <div class="value">
                    <?php if (!empty($invoice['DueDate']) && strtotime($invoice['DueDate']) > 0): ?>
                        <?= date('d/m/Y', strtotime($invoice['DueDate'])) ?>
                        <?php if ($isOverdue): ?>
                            <br><small class="text-danger">(Quá hạn <?= $daysOverdue ?> ngày)</small>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="text-muted">Chưa thiết lập</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if (!empty($invoice['Note'])): ?>
            <div class="note-box">
                <i class="fas fa-sticky-note"></i>
                <div class="content">
                    <strong>Ghi chú:</strong> <?= htmlspecialchars($invoice['Note']) ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="invoice-info">
            <div class="info-left">
                <h3><i class="fas fa-user-graduate"></i> Thông tin sinh viên</h3>
                <p>
                    <strong><i class="fas fa-user"></i> Họ tên:</strong>
                    <?= htmlspecialchars($invoice['FullName']) ?>
                </p>
                <p>
                    <strong><i class="fas fa-id-card"></i> MSSV:</strong>
                    <?= htmlspecialchars($invoice['StudentCode']) ?>
                </p>
                <p>
                    <strong><i class="fas fa-envelope"></i> Email:</strong>
                    <?= htmlspecialchars($invoice['Email']) ?>
                </p>
                <p>
                    <strong><i class="fas fa-phone"></i> SĐT:</strong>
                    <?= htmlspecialchars($invoice['Phone']) ?>
                </p>
            </div>

            <div class="info-right">
                <h3><i class="fas fa-building"></i> Thông tin phòng</h3>
                <p>
                    <strong><i class="fas fa-hotel"></i> Tòa nhà:</strong>
                    <?= htmlspecialchars($invoice['BuildingName']) ?>
                </p>
                <p>
                    <strong><i class="fas fa-door-open"></i> Phòng:</strong>
                    <?= htmlspecialchars($invoice['RoomNumber']) ?>
                </p>
                <p>
                    <strong><i class="fas fa-calendar-alt"></i> Hợp đồng:</strong>
                    <?= date('d/m/Y', strtotime($invoice['StartDate'])) ?> → <?= date('d/m/Y', strtotime($invoice['EndDate'])) ?>
                </p>
                <p>
                    <strong><i class="fas fa-info-circle"></i> Trạng thái:</strong>
                    <span class="status-badge <?= getStatusClass($invoice['Status']) ?>">
                        <?= getStatusIcon($invoice['Status']) ?> <?= htmlspecialchars($invoice['Status']) ?>
                    </span>
                </p>
            </div>
        </div>

        <div class="invoice-table">
            <h3><i class="fas fa-receipt"></i> Chi tiết hóa đơn tháng <?= $invoice['Month'] ?>/<?= $invoice['Year'] ?></h3>
            <table>
                <thead>
                    <tr>
                        <th>Nội dung</th>
                        <th>Số lượng</th>
                        <th>Đơn giá</th>
                        <th>Thành tiền</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><i class="fas fa-bed"></i> Tiền phòng</td>
                        <td>1 tháng</td>
                        <td><?= formatMoney($roomFee) ?></td>
                        <td><?= formatMoney($roomFee) ?></td>
                    </tr>
                    <tr>
                        <td><i class="fas fa-bolt"></i> Điện năng tiêu thụ</td>
                        <td><?= $invoice['ElectricUsage'] ?> kWh</td>
                        <td><?= formatMoney($invoice['ElectricPrice']) ?></td>
                        <td><?= formatMoney($electricFee) ?></td>
                    </tr>
                    <tr>
                        <td><i class="fas fa-tint"></i> Nước sử dụng</td>
                        <td><?= $invoice['WaterUsage'] ?> m³</td>
                        <td><?= formatMoney($invoice['WaterPrice']) ?></td>
                        <td><?= formatMoney($waterFee) ?></td>
                    </tr>
                    <tr class="total-row">
                        <td colspan="3"><strong>Tổng cộng</strong></td>
                        <td><strong><?= formatMoney($totalAmount) ?></strong></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <?php if (in_array($_SESSION['Role'], ['Admin',]) && $invoice['Status'] !== 'Đã thanh toán'): ?>
            <button id="confirmBtn" class="btn-confirm" onclick="confirmPayment(<?= $invoice['InvoiceID'] ?>, '<?= htmlspecialchars($invoice['FullName']) ?>')">
                <i class="fas fa-check-circle"></i> Xác nhận đã thanh toán
            </button>
        <?php endif; ?>
    </div>

    <script>
        const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        function confirmPayment(id, name) {
            const btn = document.getElementById('confirmBtn');
            const originalHTML = btn.innerHTML;

            btn.innerHTML = '<div class="loading"></div> Đang xử lý...';
            btn.disabled = true;

            Swal.fire({
                title: 'Xác nhận thanh toán?',
                html: `
            <p>Bạn có chắc muốn đánh dấu hóa đơn của <strong>${name}</strong> là <span class="text-success">ĐÃ THANH TOÁN</span>?</p>
            <p class="text-muted">Hành động này sẽ cập nhật trạng thái hóa đơn và không thể hoàn tác.</p>
        `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-check-circle"></i> Xác nhận thanh toán',
                cancelButtonText: '<i class="fas fa-times"></i> Hủy bỏ',
                confirmButtonColor: '#4cc9f0',
                cancelButtonColor: '#6c757d',
                reverseButtons: true,
                showLoaderOnConfirm: true,
                preConfirm: () => {
                    return fetch('invoice_update_status.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: new URLSearchParams({
                                id,
                                _csrf: CSRF_TOKEN
                            })
                        })
                        .then(async response => {
                            const text = await response.text();
                            try {
                                return JSON.parse(text);
                            } catch {
                                throw new Error('Phản hồi không hợp lệ từ máy chủ');
                            }
                        })
                        .catch(error => {
                            Swal.showValidationMessage(`Lỗi kết nối: ${error}`);
                        });
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    if (result.value.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Thành công!',
                            html: result.value.message,
                            timer: 800,
                            showConfirmButton: false,
                            willClose: () => {
                                location.reload();
                            }
                        });
                    } else {
                        Swal.fire('Lỗi!', result.value.message || 'Đã xảy ra lỗi không xác định.', 'error');
                        btn.innerHTML = originalHTML;
                        btn.disabled = false;
                    }
                } else {
                    btn.innerHTML = originalHTML;
                    btn.disabled = false;
                }
            }).catch(error => {
                Swal.fire('Lỗi!', 'Không thể kết nối đến máy chủ: ' + error.message, 'error');
                btn.innerHTML = originalHTML;
                btn.disabled = false;
            });
        }

        // Hiển thị thông báo từ session
        <?php if (isset($_SESSION['message'])): ?>
            Swal.fire({
                icon: '<?= $_SESSION['message_type'] ?? 'success' ?>',
                title: '<?= $_SESSION['message'] ?>',
                confirmButtonColor: '#4361ee',
                timer: 3000
            });
            <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
        <?php endif; ?>
    </script>
</body>

</html>
