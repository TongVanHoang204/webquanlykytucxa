<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include '../../../db_connect.php';
include '../../../includes/header.php';
require_once __DIR__ . '/../../../includes/auth_check.php';
requireRole(['Student', 'Admin', 'Manager']);

$userID = (int)$_SESSION['UserID'];
$studentQuery = $conn->query("SELECT StudentID, FullName FROM Students WHERE UserID = $userID");
if (!$studentQuery || $studentQuery->num_rows == 0) {
    echo "<div style='text-align:center;padding:60px;color:var(--text-secondary);'>Không tìm thấy thông tin sinh viên!</div>";
    include '../../../includes/footer.php';
    exit;
}
$student = $studentQuery->fetch_assoc();
$studentID = $student['StudentID'];

$month_filter  = $_GET['month'] ?? '';
$year_filter   = $_GET['year'] ?? date('Y');
$status_filter = $_GET['status'] ?? '';

$sql = "
    SELECT i.InvoiceID, i.Month, i.Year, i.RoomFee, i.ElectricUsage, i.ElectricPrice,
           i.WaterUsage, i.WaterPrice, i.TotalAmount, i.Status, i.CreatedAt,
           r.RoomNumber, b.BuildingName
    FROM Invoices i
    INNER JOIN Contracts c ON i.ContractID = c.ContractID
    INNER JOIN Rooms r ON c.RoomID = r.RoomID
    INNER JOIN Buildings b ON r.BuildingID = b.BuildingID
    WHERE c.StudentID = $studentID
";
if ($status_filter != '') $sql .= " AND i.Status = '" . $conn->real_escape_string($status_filter) . "'";
if ($month_filter != '')  $sql .= " AND i.Month = '" . $conn->real_escape_string($month_filter) . "'";
if ($year_filter != '')   $sql .= " AND i.Year = '" . $conn->real_escape_string($year_filter) . "'";
$sql .= " ORDER BY i.Year DESC, i.Month DESC";
$result = $conn->query($sql);

$stats = $conn->query("
    SELECT COUNT(*) AS total,
           SUM(CASE WHEN i.Status = 'Đã thanh toán' THEN 1 ELSE 0 END) AS paid,
           SUM(CASE WHEN i.Status = 'Chưa thanh toán' THEN 1 ELSE 0 END) AS pending,
           SUM(CASE WHEN i.Status = 'Chưa thanh toán' THEN i.TotalAmount ELSE 0 END) AS total_debt
    FROM Invoices i INNER JOIN Contracts c ON i.ContractID = c.ContractID WHERE c.StudentID = $studentID
")->fetch_assoc();

addLog($conn, $_SESSION['UserID'] ?? null, 'View bills', 'Bills', 'Sinh viên xem danh sách hóa đơn', 'history');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hóa đơn của tôi | Ký túc xá</title>
    <link rel="stylesheet" href="<?= $base ?>assets/css/global.css">
    <link rel="stylesheet" href="<?= $base ?>assets/css/modules_shared.css">
    <style>
        .bill-page { max-width:1100px; margin:0 auto; padding:30px 20px; }
        .bill-welcome { background:var(--gradient-primary); color:#fff; border-radius:20px; padding:28px 32px; margin-bottom:24px; position:relative; overflow:hidden; }
        .bill-welcome::after { content:''; position:absolute; top:-40%; right:-15%; width:250px; height:250px; background:rgba(255,255,255,0.06); border-radius:50%; }
        .bill-welcome h1 { font-size:1.3rem; font-weight:800; margin:0 0 6px; }
        .bill-welcome p { font-size:0.88rem; opacity:0.85; margin:0; }

        .bill-modal { display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,0.5); justify-content:center; align-items:center; }
        .bill-modal-box { background:var(--surface); border-radius:20px; padding:28px; max-width:520px; width:90%; max-height:90vh; overflow-y:auto; position:relative; }
        .bill-modal-close { position:absolute; top:14px; right:18px; font-size:1.5rem; cursor:pointer; color:var(--text-secondary); background:none; border:none; }
        .bill-modal-box h3 { font-size:1.1rem; font-weight:700; margin:0 0 16px; display:flex; align-items:center; gap:8px; }
        .pay-info { background:var(--bg); border-radius:12px; padding:14px; margin-bottom:16px; }
        .pay-info p { margin:6px 0; font-size:0.88rem; color:var(--text); }
        .method-opt { border:2px solid var(--stroke); border-radius:12px; padding:12px 14px; margin:8px 0; transition:all 0.2s; cursor:pointer; }
        .method-opt:hover { border-color:var(--primary); }
        .method-opt label { display:flex; align-items:center; gap:12px; cursor:pointer; margin:0; font-size:0.9rem; }
        .method-opt i { font-size:1.3rem; color:var(--primary); width:28px; text-align:center; }
        .pay-guide { background:rgba(67,97,238,0.06); border-left:3px solid var(--primary); border-radius:8px; padding:14px; margin-top:14px; }
        .pay-guide h5 { margin:0 0 8px; color:var(--primary); font-size:0.88rem; }
        .pay-guide p { margin:4px 0; font-size:0.84rem; color:var(--text); }
        .pay-actions { display:flex; gap:10px; justify-content:flex-end; margin-top:20px; }
    </style>
</head>
<body>
    <div class="bill-page">
        <div class="bill-welcome">
            <h1><i class="fas fa-file-invoice-dollar"></i> Hóa đơn của tôi</h1>
            <p>Xin chào <strong><?= htmlspecialchars($student['FullName']) ?></strong> — Quản lý hóa đơn ký túc xá</p>
        </div>

        <!-- Stats -->
        <div class="mod-stats mod-stagger">
            <div class="mod-stat accent-blue">
                <div class="mod-stat-icon" style="background:var(--gradient-primary);"><i class="fas fa-receipt"></i></div>
                <div class="mod-stat-info">
                    <span class="mod-stat-number"><?= $stats['total'] ?? 0 ?></span>
                    <span class="mod-stat-label">Tổng hóa đơn</span>
                </div>
            </div>
            <div class="mod-stat accent-green">
                <div class="mod-stat-icon" style="background:var(--gradient-success);"><i class="fas fa-check-circle"></i></div>
                <div class="mod-stat-info">
                    <span class="mod-stat-number"><?= $stats['paid'] ?? 0 ?></span>
                    <span class="mod-stat-label">Đã thanh toán</span>
                </div>
            </div>
            <div class="mod-stat accent-pink">
                <div class="mod-stat-icon" style="background:var(--gradient-warning);"><i class="fas fa-clock"></i></div>
                <div class="mod-stat-info">
                    <span class="mod-stat-number"><?= $stats['pending'] ?? 0 ?></span>
                    <span class="mod-stat-label">Chờ thanh toán</span>
                </div>
            </div>
            <div class="mod-stat accent-purple">
                <div class="mod-stat-icon" style="background:linear-gradient(135deg,#f72585,#b5179e);"><i class="fas fa-money-bill-wave"></i></div>
                <div class="mod-stat-info">
                    <span class="mod-stat-number"><?= number_format($stats['total_debt'] ?? 0, 0, ',', '.') ?>₫</span>
                    <span class="mod-stat-label">Tổng nợ</span>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <form method="GET" class="mod-filters" style="align-items:flex-end;">
            <div class="mod-filter-group">
                <label><i class="fas fa-filter"></i> Trạng thái</label>
                <select name="status" class="mod-select" onchange="this.form.submit()">
                    <option value="">Tất cả</option>
                    <option value="Đã thanh toán" <?= $status_filter === 'Đã thanh toán' ? 'selected' : '' ?>>Đã thanh toán</option>
                    <option value="Chưa thanh toán" <?= $status_filter === 'Chưa thanh toán' ? 'selected' : '' ?>>Chưa thanh toán</option>
                </select>
            </div>
            <div class="mod-filter-group">
                <label><i class="fas fa-calendar"></i> Tháng</label>
                <select name="month" class="mod-select" onchange="this.form.submit()">
                    <option value="">Tất cả</option>
                    <?php for ($i = 1; $i <= 12; $i++): ?>
                        <option value="<?= $i ?>" <?= $month_filter == $i ? 'selected' : '' ?>>Tháng <?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="mod-filter-group">
                <label><i class="fas fa-calendar-alt"></i> Năm</label>
                <select name="year" class="mod-select" onchange="this.form.submit()">
                    <?php for ($i = date('Y'); $i >= 2023; $i--): ?>
                        <option value="<?= $i ?>" <?= $year_filter == $i ? 'selected' : '' ?>>Năm <?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="mod-filter-group">
                <a href="bills.php" class="mod-btn mod-btn-outline mod-btn-sm"><i class="fas fa-redo"></i> Đặt lại</a>
            </div>
        </form>

        <!-- Table -->
        <div class="mod-table-wrap">
            <div class="mod-table-scroll">
                <?php if ($result && $result->num_rows > 0): ?>
                    <table class="mod-table">
                        <thead>
                            <tr>
                                <th>Mã HĐ</th><th>Phòng</th><th>Kỳ</th><th>Tiền phòng</th>
                                <th>Điện</th><th>Nước</th><th>Tổng</th><th>Trạng thái</th><th>Ngày tạo</th><th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><strong>#<?= $row['InvoiceID'] ?></strong></td>
                                    <td><?= htmlspecialchars($row['BuildingName']) ?> - <?= htmlspecialchars($row['RoomNumber']) ?></td>
                                    <td>T<?= $row['Month'] ?>/<?= $row['Year'] ?></td>
                                    <td><?= number_format($row['RoomFee'], 0, ',', '.') ?>₫</td>
                                    <td><?= $row['ElectricUsage'] ?> kWh</td>
                                    <td><?= $row['WaterUsage'] ?> m³</td>
                                    <td><strong style="color:<?= $row['Status'] === 'Đã thanh toán' ? 'var(--success)' : 'var(--danger)' ?>"><?= number_format($row['TotalAmount'], 0, ',', '.') ?>₫</strong></td>
                                    <td>
                                        <?php if ($row['Status'] === 'Đã thanh toán'): ?>
                                            <span class="mod-badge mod-badge-emerald"><i class="fas fa-check-circle"></i> Đã TT</span>
                                        <?php else: ?>
                                            <span class="mod-badge mod-badge-amber"><i class="fas fa-clock"></i> Chưa TT</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('d/m/Y', strtotime($row['CreatedAt'])) ?></td>
                                    <td>
                                        <div class="mod-row-actions">
                                            <a href="bill_detail.php?id=<?= $row['InvoiceID'] ?>" class="mod-btn-icon view" title="Chi tiết"><i class="fas fa-eye"></i></a>
                                            <?php if ($row['Status'] === 'Chưa thanh toán'): ?>
                                                <button class="mod-btn-icon edit" title="Thanh toán" onclick="openPayModal(<?= $row['InvoiceID'] ?>, <?= $row['TotalAmount'] ?>)"><i class="fas fa-credit-card"></i></button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="mod-empty" style="padding:60px;">
                        <i class="fas fa-receipt"></i>
                        <p>Không tìm thấy hóa đơn nào.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Payment Modal -->
    <div id="payModal" class="bill-modal" onclick="if(event.target===this)closePayModal()">
        <div class="bill-modal-box">
            <button class="bill-modal-close" onclick="closePayModal()">&times;</button>
            <h3><i class="fas fa-credit-card"></i> Thanh toán hóa đơn</h3>
            <div id="payDetails"></div>
            <div class="pay-actions">
                <button class="mod-btn mod-btn-outline mod-btn-sm" onclick="closePayModal()">Hủy</button>
                <button class="mod-btn mod-btn-primary mod-btn-sm" id="confirmPayBtn" onclick="processPayment()"><i class="fas fa-check"></i> Xác nhận</button>
            </div>
        </div>
    </div>

    <script>
        let currentInvoiceId = null;

        function openPayModal(id, amount) {
            currentInvoiceId = id;
            document.getElementById('payDetails').innerHTML = `
                <div class="pay-info">
                    <p><strong>Mã hóa đơn:</strong> #${id}</p>
                    <p><strong>Số tiền:</strong> ${amount.toLocaleString('vi-VN')}₫</p>
                    <p><strong>Ngày:</strong> ${new Date().toLocaleDateString('vi-VN')}</p>
                </div>
                <h4 style="font-size:0.9rem;margin:12px 0 8px;">Phương thức thanh toán:</h4>
                <div class="method-opt"><label><input type="radio" name="pm" value="bank" checked><i class="fas fa-university"></i><span>Chuyển khoản</span></label></div>
                <div class="method-opt"><label><input type="radio" name="pm" value="momo"><i class="fas fa-mobile-alt"></i><span>Ví MoMo</span></label></div>
                <div class="method-opt"><label><input type="radio" name="pm" value="cash"><i class="fas fa-money-bill"></i><span>Tiền mặt</span></label></div>
                <div class="pay-guide" id="payGuide"></div>
            `;
            document.querySelectorAll('input[name="pm"]').forEach(r => r.addEventListener('change', updateGuide));
            updateGuide();
            document.getElementById('payModal').style.display = 'flex';
        }

        function updateGuide() {
            const m = document.querySelector('input[name="pm"]:checked').value;
            const g = document.getElementById('payGuide');
            const guides = {
                bank: '<h5>Chuyển khoản ngân hàng:</h5><p>• Ngân hàng: <strong>Vietcombank</strong></p><p>• STK: <strong>0123456789</strong></p><p>• Chủ TK: <strong>KY TUC XA SINH VIEN</strong></p><p>• Nội dung: <strong>Thanh toan HD#' + currentInvoiceId + '</strong></p>',
                momo: '<h5>Thanh toán MoMo:</h5><p>• SĐT: <strong>0901234567</strong></p><p>• Tên: <strong>KY TUC XA</strong></p><p>• Nội dung: <strong>Thanh toan HD#' + currentInvoiceId + '</strong></p>',
                cash: '<h5>Thanh toán tiền mặt:</h5><p>• Địa điểm: <strong>Văn phòng Ký túc xá</strong></p><p>• Thời gian: <strong>7:30-17:00 (T2-T6)</strong></p><p>• Mang theo: <strong>CMND/Thẻ SV</strong></p>'
            };
            g.innerHTML = guides[m];
        }

        function closePayModal() { document.getElementById('payModal').style.display = 'none'; currentInvoiceId = null; }

        function processPayment() {
            if (!currentInvoiceId) return;
            const pm = document.querySelector('input[name="pm"]:checked').value;
            const btn = document.getElementById('confirmPayBtn');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang xử lý...';
            btn.disabled = true;
            setTimeout(() => { window.location.href = `bill_pay.php?id=${currentInvoiceId}&method=${pm}`; }, 1500);
        }
    </script>

    <?php include '../../../includes/footer.php'; ?>
</body>
</html>