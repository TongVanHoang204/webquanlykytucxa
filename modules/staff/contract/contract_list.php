<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../../db_connect.php';
require_once '../../../includes/auth_check.php';
requireRole(['Admin', 'Manager']);
require_once '../../../includes/admin_header.php';

$conn->set_charset('utf8mb4');

if (empty($_SESSION['_csrf'])) {
    $_SESSION['_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['_csrf'];

/* ==================== 1) TỰ ĐỘNG CẬP NHẬT HỢP ĐỒNG & PHÒNG ==================== */
$conn->begin_transaction();
try {
    $conn->query("UPDATE Contracts SET Status = 'Hết hạn' WHERE EndDate < CURDATE() AND Status = 'Hiệu lực'");
    $conn->query("UPDATE Rooms SET CurrentOccupants = 0, Status = 'Trống'");
    $conn->query("
        UPDATE Rooms r
        LEFT JOIN (
            SELECT RoomID, COUNT(*) AS cnt FROM Contracts WHERE Status = 'Hiệu lực' GROUP BY RoomID
        ) c ON r.RoomID = c.RoomID
        SET r.CurrentOccupants = COALESCE(c.cnt, 0),
            r.Status = CASE
                WHEN COALESCE(c.cnt, 0) = 0 THEN 'Trống'
                WHEN COALESCE(c.cnt, 0) >= r.Capacity THEN 'Đầy'
                ELSE 'Đang ở'
            END
    ");
    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
}

/* ==================== 2) THỐNG KÊ ==================== */
$stats = ['active' => 0, 'expired' => 0, 'cancelled' => 0, 'expiring' => 0, 'total' => 0];
$resStats = $conn->query("
    SELECT
        SUM(Status = 'Hiệu lực') AS active,
        SUM(Status = 'Hết hạn') AS expired,
        SUM(Status = 'Đã hủy') AS cancelled,
        SUM(Status = 'Hiệu lực' AND EndDate >= CURDATE() AND EndDate <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)) AS expiring,
        COUNT(*) AS total
    FROM Contracts
");
if ($resStats) {
    $row = $resStats->fetch_assoc();
    if ($row) $stats = array_merge($stats, $row);
}

/* ==================== 3) BỘ LỌC ==================== */
$status  = $_GET['status'] ?? 'all';
$keyword = trim($_GET['search'] ?? '');

$sql = "
    SELECT c.ContractID, c.StartDate, c.EndDate, c.Deposit, c.Status, c.CreatedAt,
           s.FullName, s.StudentCode, s.Phone, s.Email,
           r.RoomNumber, b.BuildingName
    FROM Contracts c
    JOIN Students s ON c.StudentID = s.StudentID
    JOIN Rooms r ON c.RoomID = r.RoomID
    JOIN Buildings b ON r.BuildingID = b.BuildingID
    WHERE 1=1
";

if ($status !== 'all') {
    $s = $conn->real_escape_string($status);
    $sql .= " AND c.Status = '$s'";
}
if ($keyword !== '') {
    $k = $conn->real_escape_string($keyword);
    $sql .= " AND (s.FullName LIKE '%$k%' OR s.StudentCode LIKE '%$k%' OR r.RoomNumber LIKE '%$k%' OR b.BuildingName LIKE '%$k%')";
}
$sql .= " ORDER BY c.CreatedAt DESC";
$result = $conn->query($sql);

function e2($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fmtMoney($amount) { return number_format((float)$amount, 0, ',', '.') . ' ₫'; }

function getDaysRemaining(string $endDate): int {
    try {
        $end = new DateTime($endDate);
        $now = new DateTime();
        return (int)$now->diff($end)->format('%r%a');
    } catch (Exception $e) { return 0; }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e2($csrf) ?>">
    <title>Quản lý Hợp đồng | Ký túc xá</title>
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
                <h2><i class="fas fa-file-contract"></i> Quản lý Hợp đồng</h2>
            </div>
            <div class="mod-header-right">
                <?php if (($_SESSION['Role'] ?? '') === 'Admin'): ?>
                <a href="contract_create.php" class="mod-btn mod-btn-primary">
                    <i class="fas fa-plus-circle"></i> Tạo HĐ mới
                </a>
                <?php endif; ?>
                <a href="contract_export.php" class="mod-btn mod-btn-outline">
                    <i class="fas fa-file-export"></i> Xuất báo cáo
                </a>
            </div>
        </div>

        <!-- Auto-sync alert -->
        <div class="mod-alert mod-alert-info" style="margin-bottom:20px;">
            <i class="fas fa-sync-alt"></i>
            <span>HĐ quá ngày kết thúc tự chuyển <b>Hết hạn</b>. Phòng được đồng bộ theo HĐ <b>Hiệu lực</b>.</span>
        </div>

        <!-- Stats -->
        <div class="mod-stats mod-stagger">
            <div class="mod-stat accent-green">
                <div class="mod-stat-icon" style="background:var(--gradient-success);"><i class="fas fa-check-circle"></i></div>
                <div class="mod-stat-info">
                    <span class="mod-stat-number"><?= (int)$stats['active'] ?></span>
                    <span class="mod-stat-label">Đang hiệu lực</span>
                </div>
            </div>
            <div class="mod-stat accent-purple">
                <div class="mod-stat-icon" style="background:var(--gradient-info);"><i class="fas fa-history"></i></div>
                <div class="mod-stat-info">
                    <span class="mod-stat-number"><?= (int)$stats['expired'] ?></span>
                    <span class="mod-stat-label">Đã hết hạn</span>
                </div>
            </div>
            <div class="mod-stat accent-pink">
                <div class="mod-stat-icon" style="background:var(--gradient-warning);"><i class="fas fa-ban"></i></div>
                <div class="mod-stat-info">
                    <span class="mod-stat-number"><?= (int)$stats['cancelled'] ?></span>
                    <span class="mod-stat-label">Đã hủy</span>
                </div>
            </div>
            <div class="mod-stat accent-blue">
                <div class="mod-stat-icon" style="background:var(--gradient-primary);"><i class="fas fa-file-alt"></i></div>
                <div class="mod-stat-info">
                    <span class="mod-stat-number"><?= (int)$stats['total'] ?></span>
                    <span class="mod-stat-label">Tổng hợp đồng</span>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <form method="get" class="mod-filters">
            <div class="mod-filter-group">
                <label><i class="fas fa-filter"></i> Trạng thái</label>
                <select name="status" class="mod-select" onchange="this.form.submit()">
                    <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Tất cả</option>
                    <option value="Hiệu lực" <?= $status === 'Hiệu lực' ? 'selected' : '' ?>>Hiệu lực</option>
                    <option value="Hết hạn" <?= $status === 'Hết hạn' ? 'selected' : '' ?>>Hết hạn</option>
                    <option value="Đã hủy" <?= $status === 'Đã hủy' ? 'selected' : '' ?>>Đã hủy</option>
                </select>
            </div>
            <div class="mod-filter-group" style="flex:2;">
                <label><i class="fas fa-search"></i> Tìm kiếm</label>
                <div style="display:flex;gap:8px;">
                    <input type="text" name="search" class="mod-input" placeholder="Tên, MSSV, phòng, tòa..." value="<?= e2($keyword) ?>">
                    <button type="submit" class="mod-btn mod-btn-primary mod-btn-sm">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
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
                            <th>Thời hạn</th>
                            <th>Tiền cọc</th>
                            <th>Trạng thái</th>
                            <th>Ngày tạo</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()):
                                $daysRemaining = getDaysRemaining($row['EndDate']);
                                $sBadge = match($row['Status']) {
                                    'Hiệu lực' => 'mod-badge-emerald',
                                    'Hết hạn'  => 'mod-badge-gray',
                                    'Đã hủy'   => 'mod-badge-red',
                                    default     => 'mod-badge-blue',
                                };
                                $sIcon = match($row['Status']) {
                                    'Hiệu lực' => 'fa-check-circle',
                                    'Hết hạn'  => 'fa-clock',
                                    'Đã hủy'   => 'fa-ban',
                                    default     => 'fa-file',
                                };
                                $isOverdue = $row['Status'] === 'Hiệu lực' && $daysRemaining < 0;
                                $isExpiring = $row['Status'] === 'Hiệu lực' && $daysRemaining >= 0 && $daysRemaining <= 7;
                            ?>
                                <tr <?= $isOverdue ? 'style="background:rgba(239,68,68,0.05);"' : '' ?>>
                                    <td><span class="mod-fw-700">#<?= (int)$row['ContractID'] ?></span></td>
                                    <td>
                                        <div class="mod-cell-name"><?= e2($row['FullName']) ?></div>
                                        <div class="mod-cell-sub"><?= e2($row['StudentCode']) ?></div>
                                        <?php if ($row['Phone']): ?>
                                            <div class="mod-cell-sub"><i class="fas fa-phone"></i> <?= e2($row['Phone']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="mod-cell-name"><?= e2($row['RoomNumber']) ?></div>
                                        <div class="mod-cell-sub"><?= e2($row['BuildingName']) ?></div>
                                    </td>
                                    <td>
                                        <div class="mod-cell-name">
                                            <?= date('d/m/Y', strtotime($row['StartDate'])) ?>
                                            <span class="mod-cell-muted">→</span>
                                            <?= date('d/m/Y', strtotime($row['EndDate'])) ?>
                                        </div>
                                        <?php if ($row['Status'] === 'Hiệu lực'): ?>
                                            <?php if ($isOverdue): ?>
                                                <span class="mod-badge mod-badge-red" style="font-size:0.7rem;">
                                                    <i class="fas fa-exclamation-triangle"></i> Quá hạn <?= abs($daysRemaining) ?> ngày
                                                </span>
                                            <?php elseif ($isExpiring): ?>
                                                <span class="mod-badge mod-badge-amber" style="font-size:0.7rem;">
                                                    <i class="fas fa-clock"></i> Còn <?= $daysRemaining ?> ngày
                                                </span>
                                            <?php else: ?>
                                                <span class="mod-badge mod-badge-emerald" style="font-size:0.7rem;">
                                                    <i class="fas fa-check"></i> Còn <?= $daysRemaining ?> ngày
                                                </span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="mod-fw-700"><?= fmtMoney($row['Deposit']) ?></span></td>
                                    <td>
                                        <span class="mod-badge <?= $sBadge ?>">
                                            <i class="fas <?= $sIcon ?>"></i> <?= e2($row['Status']) ?>
                                        </span>
                                    </td>
                                    <td class="mod-cell-muted"><?= date('d/m/Y', strtotime($row['CreatedAt'])) ?></td>
                                    <td>
                                        <div class="mod-row-actions">
                                            <a href="contract_detail.php?id=<?= (int)$row['ContractID'] ?>" class="mod-btn-icon view" title="Xem">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if (($_SESSION['Role'] ?? '') === 'Admin'): ?>
                                            <a href="contract_edit.php?id=<?= (int)$row['ContractID'] ?>" class="mod-btn-icon edit" title="Sửa">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button class="mod-btn-icon delete" style="color:var(--amber);" title="Hủy HĐ"
                                                onclick="deleteContract(<?= (int)$row['ContractID'] ?>,'<?= e2($row['FullName']) ?>', false, this)">
                                                <i class="fas fa-ban"></i>
                                            </button>
                                            <button class="mod-btn-icon delete" title="Xóa hẳn"
                                                onclick="deleteContract(<?= (int)$row['ContractID'] ?>,'<?= e2($row['FullName']) ?>', true, this)">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8">
                                    <div class="mod-empty">
                                        <i class="fas fa-inbox"></i>
                                        <p>Không có hợp đồng nào phù hợp.</p>
                                        <a class="mod-btn mod-btn-outline mod-btn-sm" href="?status=all&search=">
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
        const CONTRACT_DELETE_URL = '<?= $base ?>modules/staff/contract/contract_delete_api.php';
        const CSRF_TOKEN = '<?= e2($csrf) ?>';

        async function deleteContract(id, name, hard = false, btnEl = null) {
            const res = await Swal.fire({
                title: hard ? 'Xóa HẲN hợp đồng?' : 'Hủy hợp đồng?',
                html: `<p>Bạn có chắc muốn ${hard ? '<b>xóa hẳn</b>' : '<b>hủy</b>'} hợp đồng của <strong>${name}</strong>?</p>
                <p style="color:var(--text-secondary);font-size:0.85rem;">${hard
                    ? 'Dữ liệu hợp đồng sẽ bị xóa khỏi hệ thống, không thể hoàn tác.'
                    : 'Hợp đồng sẽ ngừng hiệu lực, phòng được giải phóng.'}</p>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: hard ? '#e63946' : '#f72585',
                cancelButtonColor: '#6c757d',
                confirmButtonText: hard ? '<i class="fas fa-trash"></i> Xóa hẳn' : '<i class="fas fa-ban"></i> Hủy HĐ',
                cancelButtonText: 'Thoát',
                reverseButtons: true
            });

            if (!res.isConfirmed) return;

            const originalHTML = btnEl ? btnEl.innerHTML : '';
            if (btnEl) {
                btnEl.disabled = true;
                btnEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            }

            try {
                const form = new FormData();
                form.append('ContractID', String(id));
                form.append('hard', hard ? '1' : '0');
                form.append('_csrf', CSRF_TOKEN);

                const r = await fetch(CONTRACT_DELETE_URL, { method: 'POST', body: form });
                const data = await r.json().catch(() => null);

                if (r.ok && data && data.ok) {
                    await Swal.fire({ icon: 'success', title: hard ? 'Đã xóa!' : 'Đã hủy!', text: data.message || 'Thành công.', timer: 1600, showConfirmButton: false });
                    location.reload();
                } else {
                    const msg = (data && (data.error || data.message)) || `HTTP ${r.status}`;
                    await Swal.fire('Lỗi', msg, 'error');
                }
            } catch (e) {
                await Swal.fire('Lỗi', 'Không thể kết nối đến máy chủ.', 'error');
            } finally {
                if (btnEl) {
                    btnEl.disabled = false;
                    btnEl.innerHTML = originalHTML;
                }
            }
        }

        <?php if (isset($_SESSION['message'])): ?>
            Swal.fire({
                icon: '<?= $_SESSION['message_type'] ?? 'success' ?>',
                title: 'Thông báo',
                html: '<?= e2($_SESSION['message']) ?>',
                confirmButtonColor: '#4361ee',
                timer: 2800
            });
            <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
        <?php endif; ?>
    </script>
</body>
</html>