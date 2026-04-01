<?php
// ==============================
// 💰 QUẢN LÝ THANH TOÁN (Admin/Manager)
// ==============================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = 'Quản lý thanh toán - Ký Túc Xá';
require_once '../../../includes/admin_header.php';
require_once '../../../includes/auth_check.php';
requireRole(['Admin', 'Manager']);

$conn->set_charset('utf8mb4');

// Auto-migration
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
} catch (Exception $e) {}

// Bộ lọc
$statusFilter = $_GET['status'] ?? '';
$searchQuery = $_GET['search'] ?? '';

// Truy vấn danh sách thanh toán
$sql = "
    SELECT 
        p.PaymentID, p.InvoiceID, p.StudentID, p.RoomID, p.Amount, 
        p.Method, p.Status, p.TransactionCode, p.CreatedAt, p.PaidAt,
        s.FullName, s.StudentCode,
        r.RoomNumber,
        b.BuildingName,
        i.Month, i.Year, i.TotalAmount AS InvoiceTotal
    FROM payments p
    INNER JOIN students s ON p.StudentID = s.StudentID
    INNER JOIN rooms r ON p.RoomID = r.RoomID
    INNER JOIN buildings b ON r.BuildingID = b.BuildingID
    LEFT JOIN invoices i ON p.InvoiceID = i.InvoiceID
    WHERE 1=1
";

$params = [];
$types = '';

if ($statusFilter !== '') {
    $sql .= " AND p.Status = ?";
    $params[] = $statusFilter;
    $types .= 's';
}

if ($searchQuery !== '') {
    $sql .= " AND (s.FullName LIKE ? OR s.StudentCode LIKE ? OR p.TransactionCode LIKE ? OR r.RoomNumber LIKE ?)";
    $searchLike = "%$searchQuery%";
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
    $types .= 'ssss';
}

$sql .= " ORDER BY FIELD(p.Status, 'Chờ xác nhận', 'Đã xác nhận', 'Từ chối'), p.CreatedAt DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Thống kê
$statsSQL = "SELECT 
    COUNT(*) AS total,
    SUM(CASE WHEN Status = 'Chờ xác nhận' THEN 1 ELSE 0 END) AS pending,
    SUM(CASE WHEN Status = 'Đã xác nhận' THEN 1 ELSE 0 END) AS confirmed,
    SUM(CASE WHEN Status = 'Từ chối' THEN 1 ELSE 0 END) AS rejected
    FROM payments";
$statsResult = $conn->query($statsSQL);
$stats = $statsResult->fetch_assoc();
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
    .payment-management {
        padding: 2rem;
        max-width: 1400px;
        margin: 0 auto;
    }

    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .page-header h1 {
        font-size: 1.8rem;
        color: var(--text, #1a1a2e);
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .page-header h1 i {
        color: #6c5ce7;
    }

    /* Stats Cards */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: var(--card-bg, #fff);
        border-radius: 12px;
        padding: 1.5rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        transition: transform 0.2s;
    }

    .stat-card:hover {
        transform: translateY(-2px);
    }

    .stat-card .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
    }

    .stat-card.total .stat-icon { background: #e8f4fd; color: #0088cc; }
    .stat-card.pending .stat-icon { background: #fff3cd; color: #856404; }
    .stat-card.confirmed .stat-icon { background: #d4edda; color: #155724; }
    .stat-card.rejected .stat-icon { background: #f8d7da; color: #721c24; }

    .stat-card .stat-info .stat-number {
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--text, #1a1a2e);
    }

    .stat-card .stat-info .stat-label {
        font-size: 0.85rem;
        color: var(--text-secondary, #666);
    }

    /* Filter */
    .filter-section {
        background: var(--card-bg, #fff);
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    }

    .filter-form {
        display: flex;
        gap: 1rem;
        align-items: flex-end;
        flex-wrap: wrap;
    }

    .filter-group {
        display: flex;
        flex-direction: column;
        gap: 0.3rem;
    }

    .filter-group label {
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--text-secondary, #666);
    }

    .filter-group select,
    .filter-group input {
        padding: 0.6rem 1rem;
        border: 1px solid var(--stroke, #ddd);
        border-radius: 8px;
        font-size: 0.9rem;
        background: var(--card-bg, #fff);
        color: var(--text, #333);
        min-width: 200px;
    }

    .btn {
        padding: 0.6rem 1.2rem;
        border: none;
        border-radius: 8px;
        font-size: 0.9rem;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.2s;
        text-decoration: none;
        font-weight: 500;
    }

    .btn-primary { background: #6c5ce7; color: white; }
    .btn-primary:hover { background: #5a4bd1; }
    .btn-success { background: #00b894; color: white; }
    .btn-success:hover { background: #00a381; }
    .btn-danger { background: #e17055; color: white; }
    .btn-danger:hover { background: #d45d43; }
    .btn-secondary { background: #b2bec3; color: white; }
    .btn-secondary:hover { background: #a0aeb3; }
    .btn-sm { padding: 0.4rem 0.8rem; font-size: 0.8rem; }

    /* Table */
    .table-container {
        background: var(--card-bg, #fff);
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    }

    .payment-table {
        width: 100%;
        border-collapse: collapse;
    }

    .payment-table th,
    .payment-table td {
        padding: 1rem;
        text-align: left;
        border-bottom: 1px solid var(--stroke, #eee);
    }

    .payment-table th {
        background: var(--bg-secondary, #f8f9fa);
        font-weight: 600;
        font-size: 0.85rem;
        color: var(--text-secondary, #666);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .payment-table tbody tr:hover {
        background: var(--bg-secondary, #f8f9fa);
    }

    .payment-table td {
        font-size: 0.9rem;
        color: var(--text, #333);
    }

    /* Status badges */
    .status-badge {
        padding: 0.3rem 0.8rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
    }

    .status-pending {
        background: #fff3cd;
        color: #856404;
    }

    .status-confirmed {
        background: #d4edda;
        color: #155724;
    }

    .status-rejected {
        background: #f8d7da;
        color: #721c24;
    }

    .action-btns {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    .amount-text {
        font-weight: 600;
        color: #e17055;
    }

    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        color: var(--text-secondary, #999);
    }

    .empty-state i {
        font-size: 3rem;
        margin-bottom: 1rem;
        opacity: 0.5;
    }

    @media (max-width: 768px) {
        .payment-management { padding: 1rem; }
        .payment-table { font-size: 0.8rem; }
        .payment-table th, .payment-table td { padding: 0.6rem; }
        .filter-form { flex-direction: column; }
        .filter-group select, .filter-group input { min-width: auto; width: 100%; }
    }
</style>

<div class="payment-management">
    <!-- Header -->
    <div class="page-header">
        <h1><i class="fas fa-money-check-alt"></i> Quản lý thanh toán</h1>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card total">
            <div class="stat-icon"><i class="fas fa-receipt"></i></div>
            <div class="stat-info">
                <div class="stat-number"><?= $stats['total'] ?? 0 ?></div>
                <div class="stat-label">Tổng số</div>
            </div>
        </div>
        <div class="stat-card pending">
            <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
            <div class="stat-info">
                <div class="stat-number"><?= $stats['pending'] ?? 0 ?></div>
                <div class="stat-label">Chờ xác nhận</div>
            </div>
        </div>
        <div class="stat-card confirmed">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-info">
                <div class="stat-number"><?= $stats['confirmed'] ?? 0 ?></div>
                <div class="stat-label">Đã xác nhận</div>
            </div>
        </div>
        <div class="stat-card rejected">
            <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
            <div class="stat-info">
                <div class="stat-number"><?= $stats['rejected'] ?? 0 ?></div>
                <div class="stat-label">Từ chối</div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filter-section">
        <form method="GET" class="filter-form">
            <div class="filter-group">
                <label><i class="fas fa-filter"></i> Trạng thái</label>
                <select name="status" onchange="this.form.submit()">
                    <option value="">Tất cả</option>
                    <option value="Chờ xác nhận" <?= $statusFilter === 'Chờ xác nhận' ? 'selected' : '' ?>>Chờ xác nhận</option>
                    <option value="Đã xác nhận" <?= $statusFilter === 'Đã xác nhận' ? 'selected' : '' ?>>Đã xác nhận</option>
                    <option value="Từ chối" <?= $statusFilter === 'Từ chối' ? 'selected' : '' ?>>Từ chối</option>
                </select>
            </div>
            <div class="filter-group">
                <label><i class="fas fa-search"></i> Tìm kiếm</label>
                <input type="text" name="search" placeholder="Tên, mã SV, mã GD, phòng..." value="<?= htmlspecialchars($searchQuery) ?>">
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Lọc</button>
            <a href="payment_list.php" class="btn btn-secondary"><i class="fas fa-redo"></i> Đặt lại</a>
        </form>
    </div>

    <!-- Table -->
    <div class="table-container">
        <?php if ($result && $result->num_rows > 0): ?>
        <table class="payment-table">
            <thead>
                <tr>
                    <th>Mã TT</th>
                    <th>Sinh viên</th>
                    <th>Phòng</th>
                    <th>Hóa đơn</th>
                    <th>Số tiền</th>
                    <th>Phương thức</th>
                    <th>Mã giao dịch</th>
                    <th>Ngày gửi</th>
                    <th>Trạng thái</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><strong>#<?= $row['PaymentID'] ?></strong></td>
                    <td>
                        <?= htmlspecialchars($row['FullName']) ?><br>
                        <small style="color: #999;"><?= htmlspecialchars($row['StudentCode']) ?></small>
                    </td>
                    <td><?= htmlspecialchars($row['BuildingName']) ?> - <?= htmlspecialchars($row['RoomNumber']) ?></td>
                    <td>
                        <?php if ($row['InvoiceID']): ?>
                            T<?= $row['Month'] ?>/<?= $row['Year'] ?>
                            <br><small style="color: #999;">#<?= $row['InvoiceID'] ?></small>
                        <?php else: ?>
                            <span style="color: #999;">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="amount-text"><?= number_format($row['Amount'], 0, ',', '.') ?> ₫</td>
                    <td><?= htmlspecialchars($row['Method']) ?></td>
                    <td>
                        <code style="background: #f0f0f0; padding: 2px 6px; border-radius: 4px; font-size: 0.8rem;">
                            <?= htmlspecialchars($row['TransactionCode'] ?? '—') ?>
                        </code>
                    </td>
                    <td><?= date('d/m/Y H:i', strtotime($row['CreatedAt'])) ?></td>
                    <td>
                        <?php 
                        $statusClass = match($row['Status']) {
                            'Chờ xác nhận' => 'status-pending',
                            'Đã xác nhận' => 'status-confirmed',
                            'Từ chối' => 'status-rejected',
                            default => ''
                        };
                        $statusIcon = match($row['Status']) {
                            'Chờ xác nhận' => 'fa-hourglass-half',
                            'Đã xác nhận' => 'fa-check-circle',
                            'Từ chối' => 'fa-times-circle',
                            default => 'fa-question'
                        };
                        ?>
                        <span class="status-badge <?= $statusClass ?>">
                            <i class="fas <?= $statusIcon ?>"></i>
                            <?= htmlspecialchars($row['Status']) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($row['Status'] === 'Chờ xác nhận'): ?>
                        <div class="action-btns">
                            <form method="POST" action="payment_confirm.php" style="display: inline;">
                                <input type="hidden" name="payment_id" value="<?= $row['PaymentID'] ?>">
                                <input type="hidden" name="action" value="confirm">
                                <button type="submit" class="btn btn-success btn-sm" 
                                        onclick="return confirm('Xác nhận thanh toán #<?= $row['PaymentID'] ?>?\n\nHóa đơn sẽ được chuyển sang trạng thái Đã thanh toán.')">
                                    <i class="fas fa-check"></i> Xác nhận
                                </button>
                            </form>
                            <form method="POST" action="payment_confirm.php" style="display: inline;">
                                <input type="hidden" name="payment_id" value="<?= $row['PaymentID'] ?>">
                                <input type="hidden" name="action" value="reject">
                                <button type="submit" class="btn btn-danger btn-sm"
                                        onclick="return confirm('Từ chối thanh toán #<?= $row['PaymentID'] ?>?\n\nSinh viên sẽ nhận được thông báo.')">
                                    <i class="fas fa-times"></i> Từ chối
                                </button>
                            </form>
                        </div>
                        <?php else: ?>
                            <span style="color: #999; font-size: 0.85rem;">
                                <?php if ($row['PaidAt']): ?>
                                    <?= date('d/m/Y H:i', strtotime($row['PaidAt'])) ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <h3>Không có thanh toán nào</h3>
            <p>Chưa có yêu cầu thanh toán nào phù hợp với bộ lọc.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
$stmt->close();
require_once '../../../includes/footer.php';
?>
