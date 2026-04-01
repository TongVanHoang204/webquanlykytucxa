<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include '../../../db_connect.php';
include '../../../includes/admin_header.php';
include '../../../includes/auth_check.php';
requireRole(['Admin']);

if (empty($_SESSION['_csrf'])) {
    $_SESSION['_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['_csrf'];

// ==================== 1) TỰ ĐỘNG TẠO & CẬP NHẬT HÓA ĐƠN ====================
function addColumnIfNotExists($conn, $table, $column, $definition) {
    $tableCheck = $conn->query("SHOW TABLES LIKE '$table'");
    if (!$tableCheck || $tableCheck->num_rows == 0) return;
    $colCheck = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    if ($colCheck && $colCheck->num_rows == 0) {
        $conn->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
}

addColumnIfNotExists($conn, "Invoices", "DueDate", "DATE NULL AFTER CreatedAt");
addColumnIfNotExists($conn, "Invoices", "Note", "NVARCHAR(255) NULL AFTER TotalAmount");
addColumnIfNotExists($conn, "Invoices", "PaidAt", "DATETIME NULL AFTER DueDate");

$conn->query("UPDATE Invoices SET DueDate = DATE_ADD(CreatedAt, INTERVAL 5 DAY) WHERE DueDate IS NULL");
$conn->query("UPDATE Invoices SET Status = 'Đã thanh toán' WHERE PaidAt IS NOT NULL AND (Status IS NULL OR Status <> 'Đã thanh toán')");
$conn->query("UPDATE Invoices SET Status = 'Quá hạn' WHERE PaidAt IS NULL AND Status = 'Chưa thanh toán' AND DueDate IS NOT NULL AND DueDate < CURDATE()");

// Thông báo quá hạn
$overdueInvoices = $conn->query("
    SELECT i.InvoiceID, i.ContractID, i.Month, i.Year, s.StudentID, s.UserID, s.FullName, s.Email, r.RoomNumber
    FROM Invoices i
    JOIN Contracts c ON i.ContractID = c.ContractID
    JOIN Students s ON c.StudentID = s.StudentID
    JOIN Rooms r ON c.RoomID = r.RoomID
    WHERE i.Status = 'Quá hạn' AND i.DueDate < CURDATE()
      AND NOT EXISTS (
          SELECT 1 FROM Notifications n WHERE n.UserID = s.UserID
            AND n.Title = CONCAT('Hóa đơn tháng ', i.Month, '/', i.Year, ' bị quá hạn')
      )
");
while ($inv = $overdueInvoices->fetch_assoc()) {
    $title = "Hóa đơn tháng {$inv['Month']}/{$inv['Year']} bị quá hạn";
    $message = "Xin chào {$inv['FullName']},<br>Hóa đơn phòng <strong>{$inv['RoomNumber']}</strong> tháng <strong>{$inv['Month']}/{$inv['Year']}</strong> đã <span style='color:red;'>quá hạn thanh toán</span>.<br>Vui lòng thanh toán sớm nhất.<br><br>— Hệ thống Ký túc xá";
    $userID = $inv['UserID'];
    if ($userID) {
        $stmt = $conn->prepare("INSERT INTO Notifications (UserID, Title, Message) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $userID, $title, $message);
        $stmt->execute();
    }
    if (!empty($inv['Email'])) {
        @mail($inv['Email'], "🔔 Thông báo hóa đơn quá hạn | Ký túc xá", strip_tags($message), "From: no-reply@ktx-system.local\r\nContent-Type: text/plain; charset=UTF-8");
    }
}

// Tự tạo HĐ hàng tháng
$conn->query("
    INSERT INTO Invoices (ContractID, Month, Year, RoomFee, ElectricUsage, ElectricPrice, WaterUsage, WaterPrice, TotalAmount, Status, CreatedAt, DueDate)
    SELECT c.ContractID, MONTH(CURDATE()), YEAR(CURDATE()), r.RoomPrice, 0, 3500, 0, 15000, r.RoomPrice, 'Chưa thanh toán', NOW(), DATE_ADD(NOW(), INTERVAL 5 DAY)
    FROM Contracts c JOIN Rooms r ON c.RoomID = r.RoomID
    WHERE c.Status = 'Hiệu lực'
      AND NOT EXISTS (SELECT 1 FROM Invoices i WHERE i.ContractID = c.ContractID AND i.Month = MONTH(CURDATE()) AND i.Year = YEAR(CURDATE()))
");
$conn->query("UPDATE Invoices SET TotalAmount = RoomFee + (ElectricUsage * ElectricPrice) + (WaterUsage * WaterPrice) WHERE TotalAmount IS NULL OR TotalAmount = 0");
$conn->query("UPDATE Invoices SET DueDate = DATE_ADD(CreatedAt, INTERVAL 5 DAY) WHERE DueDate IS NULL");
$conn->query("UPDATE Invoices SET Status = 'Quá hạn' WHERE Status = 'Chưa thanh toán' AND DueDate IS NOT NULL AND DueDate < CURDATE()");

// ==================== 2) BỘ LỌC ====================
$status  = $_GET['status'] ?? 'all';
$keyword = $_GET['search'] ?? '';
$month   = $_GET['month'] ?? 'all';

// ==================== 3) THỐNG KÊ ====================
$statsSql = "SELECT SUM(Status = 'Chưa thanh toán') as unpaid, SUM(Status = 'Đã thanh toán') as paid, SUM(Status = 'Quá hạn') as overdue, COUNT(*) as total FROM Invoices";
$stats = $conn->query($statsSql)->fetch_assoc();
$totalCollected = $conn->query("SELECT SUM(TotalAmount) as sum FROM Invoices WHERE Status='Đã thanh toán'")->fetch_assoc()['sum'] ?? 0;
$totalUnpaid = $conn->query("SELECT SUM(TotalAmount) as sum FROM Invoices WHERE Status IN ('Chưa thanh toán','Quá hạn')")->fetch_assoc()['sum'] ?? 0;

function formatMoney2($amount) { return number_format($amount, 0, ',', '.') . ' ₫'; }
function getDaysOverdue2($dueDate) {
    $due = new DateTime($dueDate);
    $now = new DateTime();
    return ($now > $due) ? $now->diff($due)->days : 0;
}

// ==================== 4) DANH SÁCH ====================
$sql = "
    SELECT i.InvoiceID, i.Month, i.Year, i.RoomFee, i.TotalAmount, i.Status,
           i.CreatedAt, i.DueDate, i.PaidAt, c.ContractID,
           s.FullName, s.StudentCode, r.RoomNumber, b.BuildingName
    FROM Invoices i
    JOIN Contracts c ON i.ContractID = c.ContractID
    JOIN Students s ON c.StudentID = s.StudentID
    JOIN Rooms r ON c.RoomID = r.RoomID
    JOIN Buildings b ON r.BuildingID = b.BuildingID
    WHERE 1=1
";
if ($status !== 'all') { $s = $conn->real_escape_string($status); $sql .= " AND i.Status = '$s'"; }
if (!empty($keyword)) { $k = $conn->real_escape_string($keyword); $sql .= " AND (s.FullName LIKE '%$k%' OR s.StudentCode LIKE '%$k%' OR r.RoomNumber LIKE '%$k%' OR b.BuildingName LIKE '%$k%')"; }
if (!empty($month) && $month !== 'all') { $monthNum = (int)substr($month, 5, 2); $yearNum = (int)substr($month, 0, 4); $sql .= " AND i.Month = $monthNum AND i.Year = $yearNum"; }
$sql .= " ORDER BY i.Year DESC, i.Month DESC, i.InvoiceID DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
    <title>Quản lý Hóa đơn | Ký túc xá</title>
    <link rel="stylesheet" href="<?= $base ?>assets/css/global.css">
    <link rel="stylesheet" href="<?= $base ?>assets/css/modules_shared.css">
    <link rel="stylesheet" href="<?= $base ?>assets/css/admin/admin_header.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <div class="mod-container">
        <!-- Header -->
        <div class="mod-header">
            <div class="mod-header-left">
                <h2><i class="fas fa-file-invoice"></i> Quản lý Hóa đơn</h2>
            </div>
            <div class="mod-header-right">
                <form style="display:flex;gap:0;" method="get">
                    <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
                    <input type="hidden" name="month" value="<?= htmlspecialchars($month) ?>">
                    <div class="mod-search">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Tìm tên, MSSV, phòng..." value="<?= htmlspecialchars($keyword) ?>">
                    </div>
                </form>
                <a href="invoice_create.php" class="mod-btn mod-btn-primary">
                    <i class="fas fa-plus"></i> Tạo HĐ
                </a>
            </div>
        </div>

        <!-- Alert -->
        <div class="mod-alert mod-alert-info" style="margin-bottom:20px;">
            <i class="fas fa-robot"></i>
            <span>Hóa đơn được tự động tạo hàng tháng cho hợp đồng hiệu lực và cập nhật trạng thái quá hạn.</span>
        </div>

        <!-- Stats -->
        <div class="mod-stats mod-stagger">
            <div class="mod-stat accent-pink">
                <div class="mod-stat-icon" style="background:var(--gradient-warning);"><i class="fas fa-clock"></i></div>
                <div class="mod-stat-info">
                    <span class="mod-stat-number"><?= $stats['unpaid'] ?? 0 ?></span>
                    <span class="mod-stat-label">Chưa thanh toán</span>
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
                <div class="mod-stat-icon" style="background:linear-gradient(135deg,#ef4444,#dc2626);"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="mod-stat-info">
                    <span class="mod-stat-number"><?= $stats['overdue'] ?? 0 ?></span>
                    <span class="mod-stat-label">Quá hạn</span>
                </div>
            </div>
            <div class="mod-stat accent-blue">
                <div class="mod-stat-icon" style="background:var(--gradient-primary);"><i class="fas fa-receipt"></i></div>
                <div class="mod-stat-info">
                    <span class="mod-stat-number"><?= $stats['total'] ?? 0 ?></span>
                    <span class="mod-stat-label">Tổng hóa đơn</span>
                </div>
            </div>
            <div class="mod-stat accent-green">
                <div class="mod-stat-icon" style="background:var(--gradient-success);"><i class="fas fa-wallet"></i></div>
                <div class="mod-stat-info">
                    <span class="mod-stat-number"><?= formatMoney2($totalCollected) ?></span>
                    <span class="mod-stat-label">Đã thu</span>
                </div>
            </div>
            <div class="mod-stat accent-pink">
                <div class="mod-stat-icon" style="background:var(--gradient-warning);"><i class="fas fa-hand-holding-usd"></i></div>
                <div class="mod-stat-info">
                    <span class="mod-stat-number"><?= formatMoney2($totalUnpaid) ?></span>
                    <span class="mod-stat-label">Chưa thu</span>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <form method="get" class="mod-filters">
            <input type="hidden" name="search" value="<?= htmlspecialchars($keyword) ?>">
            <div class="mod-filter-group">
                <label><i class="fas fa-filter"></i> Trạng thái</label>
                <select name="status" class="mod-select" onchange="this.form.submit()">
                    <option value="all" <?= $status == 'all' ? 'selected' : '' ?>>Tất cả</option>
                    <option value="Chưa thanh toán" <?= $status == 'Chưa thanh toán' ? 'selected' : '' ?>>Chưa thanh toán</option>
                    <option value="Đã thanh toán" <?= $status == 'Đã thanh toán' ? 'selected' : '' ?>>Đã thanh toán</option>
                    <option value="Quá hạn" <?= $status == 'Quá hạn' ? 'selected' : '' ?>>Quá hạn</option>
                </select>
            </div>
            <div class="mod-filter-group">
                <label><i class="fas fa-calendar-alt"></i> Tháng/Năm</label>
                <select name="month" class="mod-select" onchange="this.form.submit()">
                    <option value="all" <?= $month == 'all' ? 'selected' : '' ?>>Tất cả</option>
                    <?php
                    $currentYear = date('Y');
                    for ($y = $currentYear - 2; $y <= $currentYear + 1; $y++) {
                        echo "<optgroup label='Năm $y'>";
                        for ($m = 1; $m <= 12; $m++) {
                            $val = sprintf('%04d-%02d', $y, $m);
                            $label = sprintf('Tháng %d / %d', $m, $y);
                            $selected = ($month == $val) ? 'selected' : '';
                            echo "<option value='$val' $selected>$label</option>";
                        }
                        echo "</optgroup>";
                    }
                    ?>
                </select>
            </div>
        </form>

        <!-- Table -->
        <div class="mod-table-wrap">
            <div class="mod-table-scroll">
                <table class="mod-table">
                    <thead>
                        <tr>
                            <th>Mã HĐ</th>
                            <th>Sinh viên</th>
                            <th>Phòng</th>
                            <th>Tổng tiền</th>
                            <th>Trạng thái</th>
                            <th>Tháng/Năm</th>
                            <th>Ngày tạo</th>
                            <th>Hạn TT</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()):
                                $sBadge = match($row['Status']) {
                                    'Đã thanh toán'  => 'mod-badge-emerald',
                                    'Quá hạn'        => 'mod-badge-red',
                                    default           => 'mod-badge-amber',
                                };
                                $sIcon = match($row['Status']) {
                                    'Đã thanh toán'  => 'fa-check-circle',
                                    'Quá hạn'        => 'fa-exclamation-triangle',
                                    default           => 'fa-clock',
                                };
                                $daysOverdue = $row['Status'] === 'Quá hạn' ? getDaysOverdue2($row['DueDate']) : 0;
                            ?>
                                <tr>
                                    <td><span class="mod-fw-700">#<?= $row['InvoiceID'] ?></span></td>
                                    <td>
                                        <div class="mod-cell-name"><?= htmlspecialchars($row['FullName']) ?></div>
                                        <div class="mod-cell-sub"><?= htmlspecialchars($row['StudentCode']) ?></div>
                                    </td>
                                    <td>
                                        <div class="mod-cell-name"><?= htmlspecialchars($row['RoomNumber']) ?></div>
                                        <div class="mod-cell-sub"><?= htmlspecialchars($row['BuildingName']) ?></div>
                                    </td>
                                    <td><span class="mod-fw-700"><?= formatMoney2($row['TotalAmount']) ?></span></td>
                                    <td>
                                        <span class="mod-badge <?= $sBadge ?>">
                                            <i class="fas <?= $sIcon ?>"></i> <?= htmlspecialchars($row['Status']) ?>
                                        </span>
                                        <?php if ($daysOverdue > 0): ?>
                                            <div class="mod-cell-sub" style="color:var(--danger);font-size:0.72rem;">Quá hạn <?= $daysOverdue ?> ngày</div>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="mod-fw-600"><?= $row['Month'] ?>/<?= $row['Year'] ?></span></td>
                                    <td class="mod-cell-muted"><?= date('d/m/Y', strtotime($row['CreatedAt'])) ?></td>
                                    <td>
                                        <div class="mod-cell-muted"><?= date('d/m/Y', strtotime($row['DueDate'])) ?></div>
                                        <?php if ($row['Status'] === 'Đã thanh toán' && !empty($row['PaidAt'])): ?>
                                            <span class="mod-badge mod-badge-emerald" style="font-size:0.68rem;">TT: <?= date('d/m/Y', strtotime($row['PaidAt'])) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="mod-row-actions">
                                            <a href="invoice_detail.php?id=<?= $row['InvoiceID'] ?>" class="mod-btn-icon view" title="Xem">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if ($_SESSION['Role'] === 'Admin'): ?>
                                            <button class="mod-btn-icon delete" title="Xóa"
                                                onclick="deleteInvoice(<?= $row['InvoiceID'] ?>, '<?= htmlspecialchars($row['FullName']) ?>')">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9">
                                    <div class="mod-empty">
                                        <i class="fas fa-inbox"></i>
                                        <p>Không có hóa đơn nào phù hợp.</p>
                                        <a class="mod-btn mod-btn-outline mod-btn-sm" href="?status=all&month=all&search=">
                                            <i class="fas fa-redo"></i> Xem tất cả
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        function deleteInvoice(id, name) {
            Swal.fire({
                title: 'Xác nhận xóa',
                html: `<p>Xóa hóa đơn của <strong>${name}</strong>?</p><p style="color:var(--text-secondary);font-size:0.85rem;">Không thể hoàn tác.</p>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#f72585',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-trash"></i> Xóa',
                cancelButtonText: 'Hủy',
                reverseButtons: true
            }).then((result) => {
                if (!result.isConfirmed) return;
                fetch('invoice_delete_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: new URLSearchParams({ id, _csrf: CSRF_TOKEN })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success') {
                        Swal.fire({ icon: 'success', title: 'Đã xóa!', text: data.message, timer: 1500, showConfirmButton: false });
                        setTimeout(() => location.reload(), 800);
                    } else {
                        Swal.fire('Lỗi!', data.message, 'error');
                    }
                })
                .catch(() => Swal.fire('Lỗi!', 'Không thể kết nối đến máy chủ', 'error'));
            });
        }

        <?php if (isset($_SESSION['message'])): ?>
            Swal.fire({
                icon: '<?= $_SESSION['message_type'] ?? 'success' ?>',
                title: '<?= htmlspecialchars($_SESSION['message']) ?>',
                confirmButtonColor: '#4361ee',
                timer: 3000
            });
            <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
        <?php endif; ?>
    </script>
</body>
</html>
