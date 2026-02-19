<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../../../db_connect.php';
require_once '../../../includes/admin_header.php';
require_once '../../../includes/auth_check.php';
requireRole([ 'Admin']);

/* ================== BỘ LỌC ================== */
$building = $_GET['building'] ?? 'all';
$status   = $_GET['status']   ?? 'all';   // Trống / Đầy / Bảo trì / all
$type     = $_GET['type']     ?? 'all';   // Nam / Nữ / all
$keyword  = $_GET['search']   ?? '';

/*
 * Subquery đếm live số HĐ hiệu lực theo phòng,
 * và suy ra EffectiveStatus chỉ với 3 trạng thái: Trống / Đầy / Bảo trì
 */
$baseSubquery = "
    SELECT
        r.RoomID, r.RoomNumber, r.RoomType, r.Capacity, r.RoomPrice, r.Status AS StaticStatus,
        b.BuildingName,
        COALESCE(ac.ActiveOccupants, 0) AS ActiveOccupants,
        CASE
            WHEN r.Status = 'Bảo trì' THEN 'Bảo trì'
            WHEN COALESCE(ac.ActiveOccupants,0) >= r.Capacity THEN 'Đầy'
            ELSE 'Trống'
        END AS EffectiveStatus
    FROM Rooms r
    JOIN Buildings b ON r.BuildingID = b.BuildingID
    LEFT JOIN (
        SELECT RoomID, COUNT(*) AS ActiveOccupants
        FROM Contracts
        WHERE Status = 'Hiệu lực'
        GROUP BY RoomID
    ) ac ON ac.RoomID = r.RoomID
";

/* ================== TRUY VẤN DANH SÁCH (áp bộ lọc trên kết quả tính sẵn) ================== */
$sql = "SELECT * FROM ( $baseSubquery ) t WHERE 1=1";

if ($building !== 'all') {
    $b = $conn->real_escape_string($building);
    $sql .= " AND t.BuildingName = '$b'";
}
if ($type !== 'all') {
    $t = $conn->real_escape_string($type);
    $sql .= " AND t.RoomType = '$t'";
}
if ($status !== 'all') {
    $s = $conn->real_escape_string($status);
    $sql .= " AND t.EffectiveStatus = '$s'";
}
if (!empty($keyword)) {
    $k = $conn->real_escape_string($keyword);
    $sql .= " AND (t.RoomNumber LIKE '%$k%' OR t.BuildingName LIKE '%$k%')";
}

$sql .= " ORDER BY t.BuildingName ASC, t.RoomNumber ASC";
$result = $conn->query($sql);

/* ================== DANH SÁCH TÒA ================== */
$buildings = $conn->query("SELECT DISTINCT BuildingName FROM Buildings ORDER BY BuildingName");

/* ================== THỐNG KÊ (theo EffectiveStatus, live) ================== */
$statsSql = "
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN EffectiveStatus='Trống'  THEN 1 ELSE 0 END) AS available,
        SUM(CASE WHEN EffectiveStatus='Đầy'    THEN 1 ELSE 0 END) AS occupied,
        SUM(CASE WHEN EffectiveStatus='Bảo trì' THEN 1 ELSE 0 END) AS maintenance
    FROM ( $baseSubquery ) s
";
$statsRes = $conn->query($statsSql);
$stats = $statsRes ? $statsRes->fetch_assoc() : ['total' => 0, 'available' => 0, 'occupied' => 0, 'maintenance' => 0];
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Phòng - Ký túc xá</title>
    <link rel="stylesheet" href="../../../assets/css/admin/admin_header.css">
    <link rel="stylesheet" href="../../../assets/css/staff/room/staff_rooms.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>
    <div class="room-container">
        <!-- Header -->
        <div class="page-header">
            <h2><i class="fas fa-door-open"></i> Quản lý Phòng</h2>
            <form class="search-form" method="get">
                <input type="text" name="search" placeholder="Tìm theo số phòng hoặc tòa..." value="<?= htmlspecialchars($keyword) ?>">
                <button type="submit"><i class="fas fa-search"></i></button>
            </form>
            <a href="../rooms/room_add.php" class="btn-add"><i class="fas fa-plus-circle"></i> Thêm phòng mới</a>
        </div>

        <!-- Statistics -->
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

        <!-- Filters -->
        <form method="get" class="filters">
            <input type="hidden" name="search" value="<?= htmlspecialchars($keyword) ?>">
            <div class="filter-group">
                <label><i class="fas fa-building"></i> Tòa nhà:</label>
                <select name="building" onchange="this.form.submit()">
                    <option value="all">Tất cả</option>
                    <?php while ($b = $buildings->fetch_assoc()): ?>
                        <option value="<?= htmlspecialchars($b['BuildingName']) ?>" <?= $building == $b['BuildingName'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($b['BuildingName']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="filter-group">
                <label><i class="fas fa-venus-mars"></i> Loại phòng:</label>
                <select name="type" onchange="this.form.submit()">
                    <option value="all">Tất cả loại</option>
                    <option value="Nam" <?= $type == 'Nam' ? 'selected' : '' ?>>Nam</option>
                    <option value="Nữ" <?= $type == 'Nữ'  ? 'selected' : '' ?>>Nữ</option>
                </select>
            </div>
            <div class="filter-group">
                <label><i class="fas fa-info-circle"></i> Trạng thái:</label>
                <select name="status" onchange="this.form.submit()">
                    <option value="all">Tất cả</option>
                    <option value="Trống" <?= $status == 'Trống'   ? 'selected' : '' ?>>Còn trống</option>
                    <option value="Đầy" <?= $status == 'Đầy'     ? 'selected' : '' ?>>Đã đầy</option>
                    <option value="Bảo trì" <?= $status == 'Bảo trì' ? 'selected' : '' ?>>Bảo trì</option>
                </select>
            </div>
        </form>

        <!-- Table -->
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
                            $active   = (int)$room['ActiveOccupants'];  // lấy live từ subquery
                            $cap      = (int)$room['Capacity'];
                            $rate     = $cap > 0 ? (int)round($active * 100 / $cap) : 0;
                            $rate     = max(0, min(100, $rate));
                            $progressClass = $rate >= 100 ? 'full' : ($rate >= 70 ? 'high' : ($rate >= 35 ? 'mid' : 'low'));
                            $roomIconClass = $room['RoomType'] === 'Nam' ? 'male' : 'female';
                            $statusShow    = $room['EffectiveStatus']; // Trống / Đầy / Bảo trì
                            $statusClass   = ($statusShow === 'Đầy') ? 'status-red' : (($statusShow === 'Bảo trì') ? 'status-gray' : 'status-green');
                            $statusIcon    = ($statusShow === 'Đầy') ? 'times-circle' : (($statusShow === 'Bảo trì') ? 'tools' : 'check-circle');
                        ?>
                            <tr>
                                <td>
                                    <div class="room-info">
                                        <div class="room-icon <?= $roomIconClass ?>">
                                            <i class="fas fa-<?= $room['RoomType'] === 'Nam' ? 'mars' : 'venus' ?>"></i>
                                        </div>
                                        <div class="room-details">
                                            <span class="room-number"><?= htmlspecialchars($room['RoomNumber']) ?></span>
                                            <span class="building-name"><?= htmlspecialchars($room['BuildingName']) ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge <?= $room['RoomType'] === 'Nam' ? 'status-green' : 'status-red' ?>">
                                        <i class="fas fa-<?= $room['RoomType'] === 'Nam' ? 'mars' : 'venus' ?>"></i>
                                        <?= htmlspecialchars($room['RoomType']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="occupancy-progress">
                                        <div class="progress-bar">
                                            <div class="progress-fill <?= $progressClass ?>" style="width: <?= $rate ?>%"></div>
                                        </div>
                                        <span class="occupancy-text"><?= $active ?>/<?= $cap ?></span>
                                    </div>
                                </td>
                                <td>
                                    <span class="room-price"><?= number_format((float)$room['RoomPrice'], 0, ',', '.') ?>₫</span>
                                </td>
                                <td>
                                    <span class="status-badge <?= $statusClass ?>">
                                        <i class="fas fa-<?= $statusIcon ?>"></i>
                                        <?= htmlspecialchars($statusShow) ?>
                                    </span>
                                </td>
                                <td class="actions">
                                    <a href="room_view.php?id=<?= (int)$room['RoomID'] ?>" class="btn-view" title="Xem chi tiết"><i class="fas fa-eye"></i></a>
                                    <a href="room_edit.php?id=<?= (int)$room['RoomID'] ?>" class="btn-edit" title="Chỉnh sửa"><i class="fas fa-edit"></i></a>
                                    <?php if ($_SESSION['Role'] === 'Admin'): ?>
                                    <a href="room_delete.php?id=<?= (int)$room['RoomID'] ?>" class="btn-delete" title="Xóa phòng" onclick="return confirmDelete('<?= htmlspecialchars($room['RoomNumber'], ENT_QUOTES) ?>', this)">
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
        function confirmDelete(roomNumber, element) {
            event.preventDefault();
            const deleteUrl = element.getAttribute('href');
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
                if (result.isConfirmed) {
                    window.location.href = deleteUrl;
                }
            });
            return false;
        }

        // Thông báo session (nếu có)
        <?php if (isset($_SESSION['message'])): ?>
            document.addEventListener('DOMContentLoaded', () => {
                Swal.fire({
                    icon: '<?= $_SESSION['message_type'] ?? 'info' ?>',
                    title: 'Thông báo',
                    html: `<?= $_SESSION['message'] ?>`,
                    confirmButtonText: 'Đóng',
                    confirmButtonColor: '#3085d6',
                    background: '#fff',
                    backdrop: 'rgba(0,0,0,0.15)',
                    allowOutsideClick: true
                });
            });
        <?php unset($_SESSION['message'], $_SESSION['message_type']);
        endif; ?>
    </script>
</body>

</html>