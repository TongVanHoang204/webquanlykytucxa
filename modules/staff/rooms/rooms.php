<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../../db_connect.php';
require_once '../../../includes/auth_check.php';

requireRole(['Admin', 'Manager']);

$csrf = csrfToken();
$canEditRooms = in_array(currentRole(), ['Admin', 'Manager'], true);
$canDeleteRooms = currentRole() === 'Admin';
$status = trim($_GET['status'] ?? 'all');
$type = trim($_GET['type'] ?? 'all');
$keyword = trim($_GET['search'] ?? '');
$buildingId = (int)($_GET['building_id'] ?? 0);
$legacyBuildingName = trim($_GET['building'] ?? '');

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$buildingOptions = [];
$buildingsRes = $conn->query('SELECT BuildingID, BuildingName FROM Buildings ORDER BY BuildingName');
if ($buildingsRes) {
    while ($row = $buildingsRes->fetch_assoc()) {
        $buildingOptions[] = $row;
        if ($buildingId <= 0 && $legacyBuildingName !== '' && $legacyBuildingName !== 'all' && $legacyBuildingName === $row['BuildingName']) {
            $buildingId = (int)$row['BuildingID'];
        }
    }
}

$allowedTypes = ['Nam', 'Nữ', 'Khác'];
$allowedStatuses = ['Trống', 'Đầy', 'Bảo trì'];

if (!in_array($type, array_merge(['all'], $allowedTypes), true)) {
    $type = 'all';
}
if (!in_array($status, array_merge(['all'], $allowedStatuses), true)) {
    $status = 'all';
}

$baseSubquery = "
    SELECT
        r.RoomID,
        r.BuildingID,
        r.RoomNumber,
        r.RoomType,
        r.Capacity,
        r.RoomPrice,
        r.Status AS StaticStatus,
        b.BuildingName,
        COALESCE(ac.ActiveOccupants, 0) AS ActiveOccupants,
        CASE
            WHEN r.Status = 'Bảo trì' THEN 'Bảo trì'
            WHEN COALESCE(ac.ActiveOccupants, 0) >= r.Capacity THEN 'Đầy'
            ELSE 'Trống'
        END AS EffectiveStatus
    FROM Rooms r
    JOIN Buildings b ON b.BuildingID = r.BuildingID
    LEFT JOIN (
        SELECT RoomID, COUNT(*) AS ActiveOccupants
        FROM Contracts
        WHERE Status = 'Hiệu lực'
        GROUP BY RoomID
    ) ac ON ac.RoomID = r.RoomID
";

$filterClauses = [];
if ($buildingId > 0) {
    $filterClauses[] = 't.BuildingID = ' . $buildingId;
}
if ($type !== 'all') {
    $safeType = $conn->real_escape_string($type);
    $filterClauses[] = "t.RoomType = '{$safeType}'";
}
if ($status !== 'all') {
    $safeStatus = $conn->real_escape_string($status);
    $filterClauses[] = "t.EffectiveStatus = '{$safeStatus}'";
}
if ($keyword !== '') {
    $safeKeyword = $conn->real_escape_string($keyword);
    $filterClauses[] = "(t.RoomNumber LIKE '%{$safeKeyword}%' OR t.BuildingName LIKE '%{$safeKeyword}%')";
}

$filterSql = $filterClauses ? ' WHERE ' . implode(' AND ', $filterClauses) : '';
$sql = "SELECT * FROM ({$baseSubquery}) t{$filterSql}";
$sql .= ' ORDER BY t.BuildingName ASC, t.RoomNumber ASC';

$result = $conn->query($sql);

$statsSql = "
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN EffectiveStatus = 'Trống' THEN 1 ELSE 0 END) AS available,
        SUM(CASE WHEN EffectiveStatus = 'Đầy' THEN 1 ELSE 0 END) AS occupied,
        SUM(CASE WHEN EffectiveStatus = 'Bảo trì' THEN 1 ELSE 0 END) AS maintenance
    FROM ({$baseSubquery}) t{$filterSql}
";
$statsRes = $conn->query($statsSql);
$stats = $statsRes ? $statsRes->fetch_assoc() : [
    'total' => 0,
    'available' => 0,
    'occupied' => 0,
    'maintenance' => 0,
];

require_once '../../../includes/admin_header.php';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e($csrf) ?>">
    <title>Quản lý Phòng - Ký túc xá</title>
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
                <h2><i class="fas fa-door-open"></i> Quản lý Phòng</h2>
            </div>
            <div class="mod-header-right">
                <form style="display:flex; gap:0;" method="get">
                    <input type="hidden" name="building_id" value="<?= $buildingId > 0 ? $buildingId : '' ?>">
                    <input type="hidden" name="type" value="<?= e($type) ?>">
                    <input type="hidden" name="status" value="<?= e($status) ?>">
                    <div class="mod-search">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Tìm phòng, tòa..." value="<?= e($keyword) ?>">
                    </div>
                </form>
                <?php if ($canEditRooms): ?>
                    <a href="room_add.php" class="mod-btn mod-btn-primary">
                        <i class="fas fa-plus-circle"></i> Thêm phòng
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Stats -->
        <div class="mod-stats mod-stagger">
            <div class="mod-stat accent-blue">
                <div class="mod-stat-icon" style="background:var(--gradient-primary);"><i class="fas fa-home"></i></div>
                <div class="mod-stat-info">
                    <span class="mod-stat-number"><?= (int)$stats['total'] ?></span>
                    <span class="mod-stat-label">Tổng số phòng</span>
                </div>
            </div>
            <div class="mod-stat accent-green">
                <div class="mod-stat-icon" style="background:var(--gradient-success);"><i class="fas fa-bed"></i></div>
                <div class="mod-stat-info">
                    <span class="mod-stat-number"><?= (int)$stats['available'] ?></span>
                    <span class="mod-stat-label">Phòng trống</span>
                </div>
            </div>
            <div class="mod-stat accent-pink">
                <div class="mod-stat-icon" style="background:var(--gradient-warning);"><i class="fas fa-users"></i></div>
                <div class="mod-stat-info">
                    <span class="mod-stat-number"><?= (int)$stats['occupied'] ?></span>
                    <span class="mod-stat-label">Phòng đầy</span>
                </div>
            </div>
            <div class="mod-stat accent-purple">
                <div class="mod-stat-icon" style="background:var(--gradient-info);"><i class="fas fa-tools"></i></div>
                <div class="mod-stat-info">
                    <span class="mod-stat-number"><?= (int)$stats['maintenance'] ?></span>
                    <span class="mod-stat-label">Bảo trì</span>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <form method="get" class="mod-filters">
            <input type="hidden" name="search" value="<?= e($keyword) ?>">
            <div class="mod-filter-group">
                <label><i class="fas fa-building"></i> Tòa nhà</label>
                <select name="building_id" class="mod-select" onchange="this.form.submit()">
                    <option value="0">Tất cả</option>
                    <?php foreach ($buildingOptions as $building): ?>
                        <option value="<?= (int)$building['BuildingID'] ?>" <?= $buildingId === (int)$building['BuildingID'] ? 'selected' : '' ?>>
                            <?= e($building['BuildingName']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mod-filter-group">
                <label><i class="fas fa-venus-mars"></i> Loại phòng</label>
                <select name="type" class="mod-select" onchange="this.form.submit()">
                    <option value="all" <?= $type === 'all' ? 'selected' : '' ?>>Tất cả</option>
                    <option value="Nam" <?= $type === 'Nam' ? 'selected' : '' ?>>Nam</option>
                    <option value="Nữ" <?= $type === 'Nữ' ? 'selected' : '' ?>>Nữ</option>
                    <option value="Khác" <?= $type === 'Khác' ? 'selected' : '' ?>>Khác</option>
                </select>
            </div>
            <div class="mod-filter-group">
                <label><i class="fas fa-info-circle"></i> Trạng thái</label>
                <select name="status" class="mod-select" onchange="this.form.submit()">
                    <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Tất cả</option>
                    <option value="Trống" <?= $status === 'Trống' ? 'selected' : '' ?>>Còn trống</option>
                    <option value="Đầy" <?= $status === 'Đầy' ? 'selected' : '' ?>>Đã đầy</option>
                    <option value="Bảo trì" <?= $status === 'Bảo trì' ? 'selected' : '' ?>>Bảo trì</option>
                </select>
            </div>
        </form>

        <!-- Table -->
        <div class="mod-table-wrap">
            <div class="mod-table-scroll">
                <table class="mod-table">
                    <thead>
                        <tr>
                            <th>Thông tin phòng</th>
                            <th>Loại</th>
                            <th>Tình trạng</th>
                            <th>Giá phòng</th>
                            <th>Trạng thái</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while ($room = $result->fetch_assoc()):
                                $active = (int)$room['ActiveOccupants'];
                                $capacity = (int)$room['Capacity'];
                                $rate = $capacity > 0 ? (int)round($active * 100 / $capacity) : 0;
                                $rate = max(0, min(100, $rate));
                                $progressClass = $rate >= 100 ? 'full' : ($rate >= 70 ? 'high' : ($rate >= 35 ? 'mid' : 'low'));
                                $statusShow = $room['EffectiveStatus'];
                                $statusBadge = match($statusShow) {
                                    'Đầy'    => 'mod-badge-red',
                                    'Bảo trì' => 'mod-badge-amber',
                                    default   => 'mod-badge-emerald',
                                };
                                $statusIcon = match($statusShow) {
                                    'Đầy'    => 'fa-times-circle',
                                    'Bảo trì' => 'fa-tools',
                                    default   => 'fa-check-circle',
                                };
                                $roomType = $room['RoomType'];
                                $typeBadge = match($roomType) {
                                    'Nam' => 'mod-badge-blue',
                                    'Nữ'  => 'mod-badge-pink',
                                    default => 'mod-badge-gray',
                                };
                                $typeIcon = match($roomType) {
                                    'Nam' => 'fa-mars',
                                    'Nữ'  => 'fa-venus',
                                    default => 'fa-venus-mars',
                                };
                            ?>
                                <tr>
                                    <td>
                                        <div class="mod-cell-user">
                                            <div class="mod-cell-avatar" style="background:<?= $roomType === 'Nam' ? 'var(--gradient-primary)' : ($roomType === 'Nữ' ? 'var(--gradient-warning)' : 'var(--gradient-info)') ?>;">
                                                <i class="fas <?= $typeIcon ?>" style="font-size:1rem;"></i>
                                            </div>
                                            <div>
                                                <div class="mod-cell-name"><?= e($room['RoomNumber']) ?></div>
                                                <div class="mod-cell-sub"><i class="fas fa-building"></i> <?= e($room['BuildingName']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="mod-badge <?= $typeBadge ?>">
                                            <i class="fas <?= $typeIcon ?>"></i> <?= e($roomType) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="mod-progress">
                                            <div class="mod-progress-bar">
                                                <div class="mod-progress-fill <?= $progressClass ?>" style="width:<?= $rate ?>%"></div>
                                            </div>
                                            <span class="mod-progress-text"><?= $active ?>/<?= $capacity ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="mod-fw-700"><?= number_format((float)$room['RoomPrice'], 0, ',', '.') ?>₫</span>
                                    </td>
                                    <td>
                                        <span class="mod-badge <?= $statusBadge ?>">
                                            <i class="fas <?= $statusIcon ?>"></i> <?= e($statusShow) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="mod-row-actions">
                                            <a href="room_view.php?id=<?= (int)$room['RoomID'] ?>" class="mod-btn-icon view" title="Xem">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if ($canEditRooms): ?>
                                                <a href="room_edit.php?id=<?= (int)$room['RoomID'] ?>" class="mod-btn-icon edit" title="Sửa">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="#" class="mod-btn-icon delete" title="Xóa"
                                                    data-id="<?= (int)$room['RoomID'] ?>"
                                                    data-room="<?= e($room['RoomNumber']) ?>"
                                                    onclick="return confirmDeleteStrict(event, this)">
                                                    <i class="fas fa-trash-alt"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6">
                                    <div class="mod-empty">
                                        <i class="fas fa-door-closed"></i>
                                        <p>Không tìm thấy phòng nào phù hợp</p>
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
        function confirmDeleteStrict(event, element) {
            event.preventDefault();
            const roomId = element.getAttribute('data-id');
            const roomNumber = element.getAttribute('data-room');
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            Swal.fire({
                title: 'Xác nhận xóa',
                html: `Nhập lại số phòng <b>${roomNumber}</b> để xác nhận xóa.`,
                icon: 'warning',
                input: 'text',
                inputPlaceholder: 'Nhập lại số phòng',
                showCancelButton: true,
                confirmButtonText: 'Xóa',
                cancelButtonText: 'Hủy',
                confirmButtonColor: '#f72585',
                cancelButtonColor: '#6c757d',
                preConfirm: (value) => {
                    if ((value || '').trim().toLowerCase() !== String(roomNumber).trim().toLowerCase()) {
                        Swal.showValidationMessage('Số phòng xác nhận không khớp.');
                    }
                    return value;
                }
            }).then((result) => {
                if (!result.isConfirmed) return;

                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'room_delete.php';

                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'id';
                idInput.value = roomId || '';
                form.appendChild(idInput);

                const confirmInput = document.createElement('input');
                confirmInput.type = 'hidden';
                confirmInput.name = 'confirm_room';
                confirmInput.value = result.value || '';
                form.appendChild(confirmInput);

                const csrfInput = document.createElement('input');
                csrfInput.type = 'hidden';
                csrfInput.name = '_csrf';
                csrfInput.value = csrfToken;
                form.appendChild(csrfInput);

                document.body.appendChild(form);
                form.submit();
            });

            return false;
        }

        <?php if (isset($_SESSION['message'])): ?>
        document.addEventListener('DOMContentLoaded', () => {
            Swal.fire({
                icon: <?= json_encode($_SESSION['message_type'] ?? 'info', JSON_UNESCAPED_UNICODE) ?>,
                title: 'Thông báo',
                html: <?= json_encode($_SESSION['message'], JSON_UNESCAPED_UNICODE) ?>,
                confirmButtonText: 'Đóng',
                confirmButtonColor: '#4361ee'
            });
        });
        <?php unset($_SESSION['message'], $_SESSION['message_type']); endif; ?>
    </script>
</body>
</html>
