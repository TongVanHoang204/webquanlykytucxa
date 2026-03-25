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
    <link rel="stylesheet" href="../../../assets/css/admin/admin_header.css">
    <link rel="stylesheet" href="../../../assets/css/staff/room/staff_rooms.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <div class="room-container">
        <div class="page-header">
            <h2><i class="fas fa-door-open"></i> Quản lý Phòng</h2>
            <form class="search-form" method="get">
                <input type="hidden" name="building_id" value="<?= $buildingId > 0 ? $buildingId : '' ?>">
                <input type="hidden" name="type" value="<?= e($type) ?>">
                <input type="hidden" name="status" value="<?= e($status) ?>">
                <input type="text" name="search" placeholder="Tìm theo số phòng hoặc tòa..." value="<?= e($keyword) ?>">
                <button type="submit"><i class="fas fa-search"></i></button>
            </form>
            <?php if ($canEditRooms): ?>
                <a href="room_add.php" class="btn-add"><i class="fas fa-plus-circle"></i> Thêm phòng mới</a>
            <?php endif; ?>
        </div>

        <div class="stats-overview">
            <div class="stat-card">
                <span class="number"><?= (int)$stats['total'] ?></span>
                <span class="label">Tổng số phòng</span>
                <div class="trend"><i class="fas fa-home"></i> Toàn hệ thống</div>
            </div>
            <div class="stat-card">
                <span class="number"><?= (int)$stats['available'] ?></span>
                <span class="label">Phòng trống</span>
                <div class="trend"><i class="fas fa-bed"></i> Có sẵn</div>
            </div>
            <div class="stat-card">
                <span class="number"><?= (int)$stats['occupied'] ?></span>
                <span class="label">Phòng đầy</span>
                <div class="trend"><i class="fas fa-users"></i> Đã thuê</div>
            </div>
            <div class="stat-card">
                <span class="number"><?= (int)$stats['maintenance'] ?></span>
                <span class="label">Đang bảo trì</span>
                <div class="trend"><i class="fas fa-tools"></i> Bảo trì</div>
            </div>
        </div>

        <form method="get" class="filters">
            <input type="hidden" name="search" value="<?= e($keyword) ?>">
            <div class="filter-group">
                <label><i class="fas fa-building"></i> Tòa nhà:</label>
                <select name="building_id" onchange="this.form.submit()">
                    <option value="0">Tất cả</option>
                    <?php foreach ($buildingOptions as $building): ?>
                        <option value="<?= (int)$building['BuildingID'] ?>" <?= $buildingId === (int)$building['BuildingID'] ? 'selected' : '' ?>>
                            <?= e($building['BuildingName']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label><i class="fas fa-venus-mars"></i> Loại phòng:</label>
                <select name="type" onchange="this.form.submit()">
                    <option value="all" <?= $type === 'all' ? 'selected' : '' ?>>Tất cả loại</option>
                    <option value="Nam" <?= $type === 'Nam' ? 'selected' : '' ?>>Nam</option>
                    <option value="Nữ" <?= $type === 'Nữ' ? 'selected' : '' ?>>Nữ</option>
                    <option value="Khác" <?= $type === 'Khác' ? 'selected' : '' ?>>Khác</option>
                </select>
            </div>
            <div class="filter-group">
                <label><i class="fas fa-info-circle"></i> Trạng thái:</label>
                <select name="status" onchange="this.form.submit()">
                    <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Tất cả</option>
                    <option value="Trống" <?= $status === 'Trống' ? 'selected' : '' ?>>Còn trống</option>
                    <option value="Đầy" <?= $status === 'Đầy' ? 'selected' : '' ?>>Đã đầy</option>
                    <option value="Bảo trì" <?= $status === 'Bảo trì' ? 'selected' : '' ?>>Bảo trì</option>
                </select>
            </div>
        </form>

        <div class="room-table-container">
            <table class="room-table">
                <thead>
                    <tr>
                        <th>Thông tin phòng</th>
                        <th>Loại</th>
                        <th>Tình trạng sử dụng</th>
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
                            $statusClass = $statusShow === 'Đầy' ? 'status-red' : ($statusShow === 'Bảo trì' ? 'status-gray' : 'status-green');
                            $statusIcon = $statusShow === 'Đầy' ? 'times-circle' : ($statusShow === 'Bảo trì' ? 'tools' : 'check-circle');
                            $roomType = $room['RoomType'];
                            $roomTypeClass = $roomType === 'Nam' ? 'male' : ($roomType === 'Nữ' ? 'female' : 'other');
                            $roomTypeIcon = $roomType === 'Nam' ? 'mars' : ($roomType === 'Nữ' ? 'venus' : 'venus-mars');
                            $roomTypeBadgeClass = $roomType === 'Nam' ? 'status-green' : ($roomType === 'Nữ' ? 'status-red' : 'status-gray');
                        ?>
                            <tr>
                                <td>
                                    <div class="room-info">
                                        <div class="room-icon <?= $roomTypeClass ?>">
                                            <i class="fas fa-<?= $roomTypeIcon ?>"></i>
                                        </div>
                                        <div class="room-details">
                                            <span class="room-number"><?= e($room['RoomNumber']) ?></span>
                                            <span class="building-name"><?= e($room['BuildingName']) ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge <?= $roomTypeBadgeClass ?>">
                                        <i class="fas fa-<?= $roomTypeIcon ?>"></i>
                                        <?= e($roomType) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="occupancy-progress">
                                        <div class="progress-bar">
                                            <div class="progress-fill <?= $progressClass ?>" style="width: <?= $rate ?>%"></div>
                                        </div>
                                        <span class="occupancy-text"><?= $active ?>/<?= $capacity ?></span>
                                    </div>
                                </td>
                                <td>
                                    <span class="room-price"><?= number_format((float)$room['RoomPrice'], 0, ',', '.') ?>₫</span>
                                </td>
                                <td>
                                    <span class="status-badge <?= $statusClass ?>">
                                        <i class="fas fa-<?= $statusIcon ?>"></i>
                                        <?= e($statusShow) ?>
                                    </span>
                                </td>
                                <td class="actions">
                                    <a href="room_view.php?id=<?= (int)$room['RoomID'] ?>" class="btn-view" title="Xem chi tiết"><i class="fas fa-eye"></i></a>
                                    <?php if ($canEditRooms): ?>
                                        <a href="room_edit.php?id=<?= (int)$room['RoomID'] ?>" class="btn-edit" title="Chỉnh sửa"><i class="fas fa-edit"></i></a>
                                        <a
                                            href="#"
                                            class="btn-delete"
                                            data-id="<?= (int)$room['RoomID'] ?>"
                                            data-room="<?= e($room['RoomNumber']) ?>"
                                            title="Xóa phòng"
                                            onclick="return confirmDelete(event, this)"
                                        >
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="no-data"><i class="fas fa-door-closed"></i> Không tìm thấy phòng nào phù hợp</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php include '../../../includes/footer.php'; ?>

    <script>
        function confirmDelete(event, element) {
            event.preventDefault();

            const roomId = element.getAttribute('data-id');
            const roomNumber = element.getAttribute('data-room');
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            Swal.fire({
                title: 'Xác nhận xóa',
                html: `Bạn có chắc chắn muốn xóa phòng <b>"${roomNumber}"</b>?<br><small>Hành động này không thể hoàn tác</small>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Xóa',
                cancelButtonText: 'Hủy',
                confirmButtonColor: '#f72585',
                cancelButtonColor: '#6c757d'
            }).then((result) => {
                if (!result.isConfirmed) {
                    return;
                }

                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'room_delete.php';

                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'id';
                idInput.value = roomId || '';
                form.appendChild(idInput);

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
                confirmButtonColor: '#3085d6',
                background: '#fff',
                backdrop: 'rgba(0,0,0,0.15)',
                allowOutsideClick: true
            });
        });
        <?php unset($_SESSION['message'], $_SESSION['message_type']); endif; ?>
    </script>
</body>
</html>
