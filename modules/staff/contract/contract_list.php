<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../../db_connect.php';
require_once '../../../includes/auth_check.php';
requireRole(['Admin', 'Manager']);

require_once '../../../includes/admin_header.php';

$conn->set_charset('utf8mb4');

/* CSRF cho JS */
if (empty($_SESSION['_csrf'])) {
    $_SESSION['_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['_csrf'];

/* ==================== 1) TỰ ĐỘNG CẬP NHẬT HỢP ĐỒNG & PHÒNG ==================== */
$conn->begin_transaction();
try {
    // 1) Cập nhật HĐ quá hạn -> Hết hạn
    $conn->query("
        UPDATE Contracts
        SET Status = 'Hết hạn'
        WHERE EndDate < CURDATE()
          AND Status = 'Hiệu lực'
    ");

    // 2) Đồng bộ Rooms theo số HĐ 'Hiệu lực'
    // Reset
    $conn->query("UPDATE Rooms SET CurrentOccupants = 0, Status = 'Trống'");

    // Cập nhật lại
    $conn->query("
        UPDATE Rooms r
        LEFT JOIN (
            SELECT RoomID, COUNT(*) AS cnt
            FROM Contracts
            WHERE Status = 'Hiệu lực'
            GROUP BY RoomID
        ) c ON r.RoomID = c.RoomID
        SET
            r.CurrentOccupants = COALESCE(c.cnt, 0),
            r.Status = CASE
                WHEN COALESCE(c.cnt, 0) = 0 THEN 'Trống'
                WHEN COALESCE(c.cnt, 0) >= r.Capacity THEN 'Đầy'
                ELSE 'Đang ở'
            END
    ");

    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    // error_log($e->getMessage());
}

/* ==================== 2) THỐNG KÊ (KÈM CẢ CẢNH BÁO) ==================== */
$stats = [
    'active'    => 0,
    'expired'   => 0,
    'cancelled' => 0,
    'expiring'  => 0,
    'overdue'   => 0,
    'total'     => 0,
];

$resStats = $conn->query("
    SELECT
        SUM(Status = 'Hiệu lực')                                           AS active,
        SUM(Status = 'Hết hạn')                                            AS expired,
        SUM(Status = 'Đã hủy')                                             AS cancelled,
        SUM(Status = 'Hiệu lực'
            AND EndDate >= CURDATE()
            AND EndDate <= DATE_ADD(CURDATE(), INTERVAL 7 DAY))           AS expiring,
        SUM(Status = 'Hiệu lực'
            AND EndDate < CURDATE())                                      AS overdue,
        COUNT(*)                                                           AS total
    FROM Contracts
");
if ($resStats) {
    $row = $resStats->fetch_assoc();
    if ($row) $stats = array_merge($stats, $row);
}

/* ==================== 3) BỘ LỌC ==================== */
$status  = $_GET['status'] ?? 'all';
$keyword = trim($_GET['search'] ?? '');

/* ==================== 4) LẤY DANH SÁCH HỢP ĐỒNG ==================== */
$sql = "
    SELECT
        c.ContractID, c.StartDate, c.EndDate, c.Deposit, c.Status, c.CreatedAt,
        s.FullName, s.StudentCode, s.Phone, s.Email,
        r.RoomNumber, b.BuildingName
    FROM Contracts c
    JOIN Students  s ON c.StudentID = s.StudentID
    JOIN Rooms     r ON c.RoomID    = r.RoomID
    JOIN Buildings b ON r.BuildingID = b.BuildingID
    WHERE 1=1
";

if ($status !== 'all') {
    $s = $conn->real_escape_string($status);
    $sql .= " AND c.Status = '$s'";
}

if ($keyword !== '') {
    $k = $conn->real_escape_string($keyword);
    $sql .= " AND (
        s.FullName     LIKE '%$k%' OR
        s.StudentCode  LIKE '%$k%' OR
        r.RoomNumber   LIKE '%$k%' OR
        b.BuildingName LIKE '%$k%'
    )";
}

$sql .= " ORDER BY c.CreatedAt DESC";

$result = $conn->query($sql);

/* ==================== 5) HÀM HỖ TRỢ ==================== */

function e($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function formatMoney($amount)
{
    return number_format((float)$amount, 0, ',', '.') . ' ₫';
}

function getStatusClass($status)
{
    return strtolower(str_replace(' ', '-', $status));
}

/**
 * Trả về số ngày còn lại (ÂM nếu đã quá hạn)
 */
function getDaysRemaining(string $endDate): int
{
    try {
        $end = new DateTime($endDate);
        $now = new DateTime();
        // %r%a: có dấu
        return (int)$now->diff($end)->format('%r%a');
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Class CSS cho badge thời hạn:
 * - expiring: còn <=7 ngày
 * - overdue: quá hạn nhưng vẫn đang hiệu lực (nợ quá hạn)
 */
function getDurationClass(string $endDate, string $status): string
{
    if ($status !== 'Hiệu lực') return '';
    $d = getDaysRemaining($endDate);
    if ($d < 0)  return 'overdue';
    if ($d <= 7) return 'expiring';
    return '';
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Quản lý Hợp đồng | Hệ thống Ký túc xá</title>

    <link rel="stylesheet" href="../../../assets/css/admin/admin_header.css">
    <link rel="stylesheet" href="../../../assets/css/staff/contract/staff_contract.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>

<body>
    <div class="contract-container">

        <!-- HEADER -->
        <div class="page-header">
            <h2><i class="fas fa-file-contract"></i> Quản lý Hợp đồng</h2>
        </div>

        <!-- Thông báo hệ thống -->
        <div class="auto-update-alert">
            <i class="fas fa-sync-alt"></i>
            <div class="content">
                <h4>Tự động đồng bộ</h4>
                <p>
                    Hợp đồng quá ngày kết thúc sẽ tự chuyển sang <b>Hết hạn</b>.
                    Số người trong phòng và trạng thái phòng được tự động cập nhật theo hợp đồng <b>Hiệu lực</b>.
                </p>
            </div>
        </div>

        <!-- STATS -->
        <div class="stats-container">
            <div class="stat-card active">
                <div class="stat-number"><?= (int)$stats['active'] ?></div>
                <div class="stat-label">Đang hiệu lực</div>
            </div>
            <div class="stat-card expired">
                <div class="stat-number"><?= (int)$stats['expired'] ?></div>
                <div class="stat-label">Đã hết hạn</div>
            </div>
            <div class="stat-card cancelled">
                <div class="stat-number"><?= (int)$stats['cancelled'] ?></div>
                <div class="stat-label">Đã hủy</div>
            </div>
            <div class="stat-card total">
                <div class="stat-number"><?= (int)$stats['total'] ?></div>
                <div class="stat-label">Tổng hợp đồng</div>
            </div>
        </div>

        <!-- QUICK ACTIONS -->
        <div class="quick-actions">
            <?php if (($_SESSION['Role'] ?? '') === 'Admin'): ?>
            <a href="contract_create.php" class="quick-action-btn primary">
                <i class="fas fa-plus-circle"></i> Tạo hợp đồng mới
            </a>
            <?php endif; ?>
            <a href="contract_export.php" class="quick-action-btn">
                <i class="fas fa-file-export"></i> Xuất báo cáo
            </a>
        </div>

        <!-- FILTERS -->
        <form method="get" class="filters">
            <div class="filter-group">
                <label><i class="fas fa-filter"></i> Trạng thái:</label>
                <select name="status" onchange="this.form.submit()">
                    <option value="all" <?= $status === 'all'       ? 'selected' : '' ?>>Tất cả</option>
                    <option value="Hiệu lực" <?= $status === 'Hiệu lực' ? 'selected' : '' ?>>🟢 Hiệu lực</option>
                    <option value="Hết hạn" <?= $status === 'Hết hạn'  ? 'selected' : '' ?>>⚫ Hết hạn</option>
                    <option value="Đã hủy" <?= $status === 'Đã hủy'   ? 'selected' : '' ?>>🔴 Đã hủy</option>
                </select>
            </div>

            <div class="filter-group search-box">
                <input type="text"
                    name="search"
                    placeholder="Tìm theo tên, MSSV, phòng, tòa..."
                    value="<?= e($keyword) ?>">
                <button type="submit">
                    <i class="fas fa-search"></i> <span>Tìm kiếm</span>
                </button>
            </div>
        </form>

        <!-- TABLE -->
        <div class="contract-table">
            <?php if ($result && $result->num_rows > 0): ?>
                <table>
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
                        <?php while ($row = $result->fetch_assoc()):
                            $statusClass   = getStatusClass($row['Status']);
                            $daysRemaining = getDaysRemaining($row['EndDate']);
                            $durationClass = getDurationClass($row['EndDate'], $row['Status']);
                        ?>
                            <tr class="<?= $durationClass === 'overdue' ? 'row-overdue' : '' ?>">
                                <td data-label="Mã HĐ">
                                    <strong>#<?= (int)$row['ContractID'] ?></strong>
                                </td>
                                <td data-label="Sinh viên">
                                    <strong><?= e($row['FullName']) ?></strong><br>
                                    <small class="text-muted"><?= e($row['StudentCode']) ?></small><br>
                                    <?php if ($row['Phone']): ?>
                                        <small class="text-muted"><?= e($row['Phone']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Phòng">
                                    <strong><?= e($row['RoomNumber']) ?></strong><br>
                                    <small class="text-muted"><?= e($row['BuildingName']) ?></small>
                                </td>
                                <td data-label="Thời hạn">
                                    <div>
                                        <strong><?= date('d/m/Y', strtotime($row['StartDate'])) ?></strong>
                                        <span class="text-muted">→</span>
                                        <strong><?= date('d/m/Y', strtotime($row['EndDate'])) ?></strong>
                                        <?php if ($row['Status'] === 'Hiệu lực'): ?>
                                            <br>
                                            <small class="contract-duration <?= $durationClass ?>">
                                                <?php if ($daysRemaining < 0): ?>
                                                    ⚠️ Nợ quá hạn <?= abs($daysRemaining) ?> ngày
                                                <?php elseif ($daysRemaining <= 7): ?>
                                                    ⏳ Còn <?= (int)$daysRemaining ?> ngày (sắp hết hạn)
                                                <?php else: ?>
                                                    ✅ Còn <?= (int)$daysRemaining ?> ngày
                                                <?php endif; ?>
                                            </small>
                                        <?php elseif ($row['Status'] === 'Hết hạn'): ?>
                                            <br>
                                            <small class="contract-duration expired-label">
                                                ⚫ Đã hết hạn
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td data-label="Tiền cọc">
                                    <strong><?= formatMoney($row['Deposit']) ?></strong>
                                </td>
                                <td data-label="Trạng thái">
                                    <span class="status-badge <?= $statusClass ?>">
                                        <?= e($row['Status']) ?>
                                    </span>
                                </td>
                                <td data-label="Ngày tạo">
                                    <?= date('d/m/Y', strtotime($row['CreatedAt'])) ?>
                                </td>
                                <td data-label="Thao tác">
                                    <div class="action-buttons">
                                        <a href="contract_detail.php?id=<?= (int)$row['ContractID'] ?>"
                                            class="btn btn-view" title="Xem chi tiết">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if (($_SESSION['Role'] ?? '') === 'Admin'): ?>
                                        <a href="contract_edit.php?id=<?= (int)$row['ContractID'] ?>"
                                            class="btn btn-view" title="Sửa">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                            <button class="btn btn-cancel"
                                                title="Hủy hợp đồng"
                                                onclick="deleteContract(<?= (int)$row['ContractID'] ?>,'<?= e($row['FullName']) ?>', false, this)">
                                                <i class="fas fa-ban"></i>
                                            </button>
                                            <button class="btn btn-delete"
                                                title="Xóa hẳn hợp đồng"
                                                onclick="deleteContract(<?= (int)$row['ContractID'] ?>,'<?= e($row['FullName']) ?>', true, this)">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-inbox"></i>
                    <p>Không có hợp đồng nào phù hợp với tiêu chí.</p>
                    <a href="?status=all&search=" class="btn btn-view mt-md">
                        <i class="fas fa-rotate-right"></i> Xem tất cả hợp đồng
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        const CONTRACT_DELETE_URL = '/modules/staff/contract/contract_delete_api.php';
        const CSRF_TOKEN = '<?= e($csrf) ?>';

        async function deleteContract(id, name, hard = false, btnEl = null) {
            const res = await Swal.fire({
                title: hard ? 'Xóa HẲN hợp đồng?' : 'Hủy hợp đồng?',
                html: `
            <p>Bạn có chắc muốn ${hard ? '<b>xóa hẳn</b>' : '<b>hủy</b>'} hợp đồng của
            <strong>${name}</strong>?</p>
            <p class="text-muted">
                ${hard
                    ? 'Dữ liệu hợp đồng sẽ bị xóa khỏi hệ thống, không thể hoàn tác.'
                    : 'Hợp đồng sẽ ngừng hiệu lực, phòng được giải phóng.'}
            </p>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: hard ? '#e63946' : '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: hard ? '<i class="fas fa-trash"></i> Xóa hẳn' : '<i class="fas fa-ban"></i> Hủy',
                cancelButtonText: '<i class="fas fa-times"></i> Thoát',
                reverseButtons: true
            });

            if (!res.isConfirmed) return;

            const originalHTML = btnEl ? btnEl.innerHTML : '';
            if (btnEl) {
                btnEl.disabled = true;
                btnEl.classList.add('loading');
                btnEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            }

            try {
                const form = new FormData();
                form.append('ContractID', String(id));
                form.append('hard', hard ? '1' : '0');
                form.append('_csrf', CSRF_TOKEN);

                const r = await fetch(CONTRACT_DELETE_URL, {
                    method: 'POST',
                    body: form
                });
                const data = await r.json().catch(() => null);

                if (r.ok && data && data.ok) {
                    await Swal.fire({
                        icon: 'success',
                        title: hard ? 'Đã xóa hợp đồng!' : 'Đã hủy hợp đồng!',
                        text: data.message || 'Thao tác thành công.',
                        timer: 1600,
                        showConfirmButton: false
                    });
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
                    btnEl.classList.remove('loading');
                    btnEl.innerHTML = originalHTML;
                }
            }
        }

        // Thông báo session (nếu có)
        <?php if (isset($_SESSION['message'])): ?>
            Swal.fire({
                icon: '<?= $_SESSION['message_type'] ?? 'success' ?>',
                title: 'Thông báo',
                html: '<?= e($_SESSION['message']) ?>',
                confirmButtonColor: '#4361ee',
                timer: 2800
            });
            <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
        <?php endif; ?>
    </script>
</body>

</html>