<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../../db_connect.php';
require_once '../../../includes/auth_check.php';

requireRole(['Admin', 'Manager']);

$conn->set_charset('utf8mb4');

$csrf = csrfToken();
$canEditBuildings = in_array(currentRole(), ['Admin', 'Manager'], true);
$canDeleteBuildings = currentRole() === 'Admin';
$search = trim($_GET['search'] ?? '');
$view = ($_GET['view'] ?? 'table') === 'card' ? 'card' : 'table';

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$whereSql = '';
if ($search !== '') {
    $keyword = $conn->real_escape_string($search);
    $whereSql = "WHERE b.BuildingName LIKE '%{$keyword}%' OR COALESCE(b.Description, '') LIKE '%{$keyword}%'";
}

$sql = "
    SELECT
        b.BuildingID,
        b.BuildingName,
        b.Description,
        b.Floors,
        COUNT(r.RoomID) AS TotalRooms,
        SUM(CASE WHEN r.Status = 'Trống' THEN 1 ELSE 0 END) AS EmptyRooms,
        SUM(CASE WHEN r.Status = 'Đầy' THEN 1 ELSE 0 END) AS FullRooms,
        SUM(CASE WHEN r.Status = 'Bảo trì' THEN 1 ELSE 0 END) AS MaintenanceRooms,
        COALESCE(SUM(r.Capacity), 0) AS TotalCapacity,
        (
            SELECT COUNT(*)
            FROM Contracts c
            JOIN Rooms r2 ON r2.RoomID = c.RoomID
            WHERE c.Status = 'Hiệu lực'
              AND r2.BuildingID = b.BuildingID
        ) AS CurrentOccupants
    FROM Buildings b
    LEFT JOIN Rooms r ON r.BuildingID = b.BuildingID
    {$whereSql}
    GROUP BY b.BuildingID, b.BuildingName, b.Description, b.Floors
    ORDER BY b.BuildingName ASC
";

$res = $conn->query($sql);

$buildings = [];
$totalBuildings = 0;
$totalRooms = 0;
$totalCapacity = 0;
$totalOccupants = 0;

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $buildings[] = $row;
        $totalBuildings++;
        $totalRooms += (int)$row['TotalRooms'];
        $totalCapacity += (int)$row['TotalCapacity'];
        $totalOccupants += (int)$row['CurrentOccupants'];
    }
}

$globalOccRate = $totalCapacity > 0 ? (int)round($totalOccupants / $totalCapacity * 100) : 0;

require_once '../../../includes/admin_header.php';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?= e($csrf) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Danh sách tòa nhà | Hệ thống Ký túc xá</title>

    <link rel="stylesheet" href="../../../assets/css/admin/admin_header.css">
    <link rel="stylesheet" href="../../../assets/css/staff/buildings/staff_building_list.css">
    <link rel="stylesheet" href="../../../assets/vendor/fontawesome/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="../../../assets/vendor/fonts/fonts.css" rel="stylesheet">
</head>
<body>
    <div class="building-container">
        <div class="page-header">
            <h2><i class="fa-solid fa-building"></i> Quản lý Tòa nhà</h2>
            <div class="header-actions">
                <div class="view-toggle">
                    <button type="button" class="btn <?= $view === 'table' ? 'primary' : '' ?>" onclick="setView('table')">
                        <i class="fa-solid fa-table"></i>
                    </button>
                    <button type="button" class="btn <?= $view === 'card' ? 'primary' : '' ?>" onclick="setView('card')">
                        <i class="fa-solid fa-border-all"></i>
                    </button>
                </div>

                <?php if ($canEditBuildings): ?>
                    <a href="building_create.php" class="btn primary">
                        <i class="fa-solid fa-plus"></i> Thêm tòa nhà
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="stats-cards">
            <div class="stat-card">
                <div class="label">Tổng số tòa</div>
                <div class="value"><?= number_format($totalBuildings) ?></div>
            </div>
            <div class="stat-card">
                <div class="label">Tổng phòng</div>
                <div class="value"><?= number_format($totalRooms) ?></div>
            </div>
            <div class="stat-card">
                <div class="label">Tổng sức chứa</div>
                <div class="value"><?= number_format($totalCapacity) ?></div>
            </div>
            <div class="stat-card">
                <div class="label">Đang ở</div>
                <div class="value"><?= number_format($totalOccupants) ?></div>
            </div>
            <div class="stat-card">
                <div class="label">Tỷ lệ lấp đầy</div>
                <div class="value"><?= $globalOccRate ?>%</div>
            </div>
        </div>

        <form method="get" class="filters">
            <input type="hidden" name="view" value="<?= e($view) ?>">
            <input
                type="text"
                name="search"
                value="<?= e($search) ?>"
                placeholder="Tìm theo tên tòa nhà hoặc mô tả..."
            >
            <button class="btn primary" type="submit">
                <i class="fa-solid fa-search"></i> Tìm kiếm
            </button>
            <?php if ($search !== ''): ?>
                <a href="?view=<?= e($view) ?>" class="btn">
                    <i class="fa-solid fa-times"></i> Xóa bộ lọc
                </a>
            <?php endif; ?>
        </form>

        <?php if ($view === 'card'): ?>
            <div class="buildings-grid">
                <?php if ($buildings): ?>
                    <?php foreach ($buildings as $building):
                        $capacity = (int)$building['TotalCapacity'];
                        $occupants = (int)$building['CurrentOccupants'];
                        $rate = $capacity > 0 ? (int)round($occupants / $capacity * 100) : 0;
                    ?>
                        <div class="building-card">
                            <div class="building-header">
                                <div class="building-title">
                                    <h3><?= e($building['BuildingName']) ?></h3>
                                    <div class="floors">
                                        <i class="fa-solid fa-layer-group"></i>
                                        <?= $building['Floors'] ? (int)$building['Floors'] . ' tầng' : 'Chưa xác định' ?>
                                    </div>
                                </div>
                                <div class="actions">
                                    <a
                                        href="../rooms/rooms.php?building_id=<?= (int)$building['BuildingID'] ?>&type=all&status=all"
                                        class="action-btn view"
                                        title="Xem phòng trong tòa <?= e($building['BuildingName']) ?>"
                                    >
                                        <i class="fa-solid fa-door-open"></i>
                                    </a>

                                    <?php if ($canEditBuildings): ?>
                                        <a
                                            href="building_edit.php?id=<?= (int)$building['BuildingID'] ?>"
                                            class="action-btn edit"
                                            title="Sửa tòa nhà"
                                        >
                                            <i class="fa-solid fa-pen"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($canDeleteBuildings): ?>
                                        <button
                                            type="button"
                                            class="action-btn delete"
                                            title="Xóa tòa nhà"
                                            onclick="deleteBuildingSecure(<?= (int)$building['BuildingID'] ?>, <?= e(json_encode($building['BuildingName'], JSON_UNESCAPED_UNICODE)) ?>)"
                                        >
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="building-stats">
                                <div class="building-stat">
                                    <span class="number"><?= (int)$building['TotalRooms'] ?></span>
                                    <span class="label">Tổng phòng</span>
                                </div>
                                <div class="building-stat">
                                    <span class="number"><?= number_format($capacity) ?></span>
                                    <span class="label">Sức chứa</span>
                                </div>
                                <div class="building-stat">
                                    <span class="number"><?= number_format($occupants) ?></span>
                                    <span class="label">Đang ở</span>
                                </div>
                                <div class="building-stat">
                                    <span class="number"><?= $rate ?>%</span>
                                    <span class="label">Tỷ lệ</span>
                                </div>
                            </div>

                            <div class="progress-line">
                                <div class="progress-fill" style="width: <?= min(100, $rate) ?>%;"></div>
                            </div>

                            <div class="room-stats-horizontal">
                                <div class="room-stat-item empty">
                                    <span class="dot"></span>
                                    <span class="room-label">Trống</span>
                                    <span class="room-count"><?= (int)$building['EmptyRooms'] ?></span>
                                </div>
                                <div class="room-stat-item full">
                                    <span class="dot"></span>
                                    <span class="room-label">Đầy</span>
                                    <span class="room-count"><?= (int)$building['FullRooms'] ?></span>
                                </div>
                                <div class="room-stat-item maintenance">
                                    <span class="dot"></span>
                                    <span class="room-label">Bảo trì</span>
                                    <span class="room-count"><?= (int)$building['MaintenanceRooms'] ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty" style="grid-column: 1 / -1;">
                        <i class="fa-regular fa-building"></i>
                        <p>Không tìm thấy tòa nhà nào.</p>
                        <?php if ($canEditBuildings): ?>
                            <a href="building_create.php" class="btn primary">
                                <i class="fa-solid fa-plus"></i> Thêm tòa nhà đầu tiên
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <?php if ($buildings): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Tòa nhà</th>
                                <th>Tầng</th>
                                <th>Phòng</th>
                                <th>Sức chứa / Đang ở</th>
                                <th>Tỷ lệ lấp đầy</th>
                                <th>Trạng thái phòng</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $index = 1; ?>
                            <?php foreach ($buildings as $building):
                                $capacity = (int)$building['TotalCapacity'];
                                $occupants = (int)$building['CurrentOccupants'];
                                $rate = $capacity > 0 ? (int)round($occupants / $capacity * 100) : 0;
                            ?>
                                <tr>
                                    <td><?= $index++ ?></td>
                                    <td>
                                        <strong><?= e($building['BuildingName']) ?></strong>
                                        <?php if (!empty($building['Description'])): ?>
                                            <br>
                                            <small class="text-muted">
                                                <?= e(mb_strimwidth($building['Description'], 0, 80, '...', 'UTF-8')) ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge">
                                            <i class="fa-solid fa-layer-group"></i>
                                            <?= $building['Floors'] ? (int)$building['Floors'] : 'N/A' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge">
                                            <i class="fa-solid fa-door-open"></i>
                                            <?= (int)$building['TotalRooms'] ?> phòng
                                        </span>
                                    </td>
                                    <td>
                                        <strong><?= number_format($capacity) ?></strong> /
                                        <strong style="color: var(--primary);"><?= number_format($occupants) ?></strong>
                                    </td>
                                    <td style="min-width: 120px;">
                                        <div class="progress-line">
                                            <div class="progress-fill" style="width: <?= min(100, $rate) ?>%;"></div>
                                        </div>
                                        <small class="text-muted"><?= $rate ?>%</small>
                                    </td>
                                    <td>
                                        <div class="room-stats-horizontal">
                                            <div class="room-stat-item empty">
                                                <span class="dot"></span>
                                                <span class="room-label">Trống</span>
                                                <span class="room-count"><?= (int)$building['EmptyRooms'] ?></span>
                                            </div>
                                            <div class="room-stat-item full">
                                                <span class="dot"></span>
                                                <span class="room-label">Đầy</span>
                                                <span class="room-count"><?= (int)$building['FullRooms'] ?></span>
                                            </div>
                                            <div class="room-stat-item maintenance">
                                                <span class="dot"></span>
                                                <span class="room-label">Bảo trì</span>
                                                <span class="room-count"><?= (int)$building['MaintenanceRooms'] ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <a
                                                href="../rooms/rooms.php?building_id=<?= (int)$building['BuildingID'] ?>&type=all&status=all"
                                                class="action-btn view"
                                                title="Xem phòng trong tòa <?= e($building['BuildingName']) ?>"
                                            >
                                                <i class="fa-solid fa-door-open"></i>
                                            </a>

                                            <?php if ($canEditBuildings): ?>
                                                <a
                                                    href="building_edit.php?id=<?= (int)$building['BuildingID'] ?>"
                                                    class="action-btn edit"
                                                    title="Sửa tòa nhà"
                                                >
                                                    <i class="fa-solid fa-pen"></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($canDeleteBuildings): ?>
                                                <button
                                                    type="button"
                                                    class="action-btn delete"
                                                    title="Xóa tòa nhà"
                                                    onclick="deleteBuildingSecure(<?= (int)$building['BuildingID'] ?>, <?= e(json_encode($building['BuildingName'], JSON_UNESCAPED_UNICODE)) ?>)"
                                                >
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty">
                        <i class="fa-regular fa-building"></i>
                        <p>Không tìm thấy tòa nhà nào.</p>
                        <?php if ($canEditBuildings): ?>
                            <a href="building_create.php" class="btn primary">
                                <i class="fa-solid fa-plus"></i> Thêm tòa nhà đầu tiên
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function setView(view) {
            const url = new URL(window.location.href);
            url.searchParams.set('view', view);
            window.location.href = url.toString();
        }

        function deleteBuilding(id, name) {
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            Swal.fire({
                title: 'Xóa tòa nhà?',
                html: `Bạn có chắc muốn xóa <b>${name}</b>?<br><small class="text-muted">Chỉ có thể xóa khi tòa chưa có phòng nào.</small>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: '<i class="fa-solid fa-trash"></i> Xóa',
                cancelButtonText: 'Hủy',
                reverseButtons: true
            }).then((result) => {
                if (!result.isConfirmed) {
                    return;
                }

                fetch('building_delete_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: new URLSearchParams({
                        id,
                        _csrf: csrfToken
                    })
                })
                    .then((response) => response.json())
                    .then((data) => {
                        if (data.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Đã xóa',
                                text: data.message,
                                timer: 1200,
                                showConfirmButton: false
                            });
                            setTimeout(() => window.location.reload(), 900);
                            return;
                        }

                        Swal.fire('Lỗi', data.message || 'Không thể xóa tòa nhà.', 'error');
                    })
                    .catch(() => {
                        Swal.fire('Lỗi', 'Không thể kết nối máy chủ.', 'error');
                    });
            });
        }

        function deleteBuildingSecure(id, name) {
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            Swal.fire({
                title: 'XÃ³a tÃ²a nhÃ ?',
                html: `Báº¡n cÃ³ cháº¯c muá»‘n xÃ³a <b>${name}</b>?<br><small class="text-muted">Nháº­p Ä‘Ãºng tÃªn tÃ²a nhÃ  Ä‘á»ƒ xÃ¡c nháº­n.</small>`,
                icon: 'warning',
                input: 'text',
                inputPlaceholder: 'Nháº­p láº¡i tÃªn tÃ²a nhÃ ',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: '<i class="fa-solid fa-trash"></i> XÃ³a',
                cancelButtonText: 'Há»§y',
                reverseButtons: true,
                preConfirm: (value) => {
                    if ((value || '').trim().toLowerCase() !== String(name).trim().toLowerCase()) {
                        Swal.showValidationMessage('TÃªn xÃ¡c nháº­n khÃ´ng khá»›p.');
                    }
                    return value;
                }
            }).then((result) => {
                if (!result.isConfirmed) {
                    return;
                }

                fetch('building_delete_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: new URLSearchParams({
                        id,
                        confirm_name: result.value || '',
                        _csrf: csrfToken
                    })
                })
                    .then((response) => response.json())
                    .then((data) => {
                        if (data.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'ÄÃ£ xÃ³a',
                                text: data.message,
                                timer: 1200,
                                showConfirmButton: false
                            });
                            setTimeout(() => window.location.reload(), 900);
                            return;
                        }

                        Swal.fire('Lá»—i', data.message || 'KhÃ´ng thá»ƒ xÃ³a tÃ²a nhÃ .', 'error');
                    })
                    .catch(() => {
                        Swal.fire('Lá»—i', 'KhÃ´ng thá»ƒ káº¿t ná»‘i mÃ¡y chá»§.', 'error');
                    });
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.building-card').forEach((card) => {
                card.addEventListener('click', function(event) {
                    if (!event.target.closest('.actions')) {
                        this.style.transform = 'scale(0.98)';
                        setTimeout(() => {
                            this.style.transform = '';
                        }, 150);
                    }
                });
            });
        });

        <?php if (isset($_SESSION['message'])):
            $flashType = $_SESSION['message_type'] ?? 'info';
            $flashMessage = $_SESSION['message'];
            unset($_SESSION['message'], $_SESSION['message_type']);
        ?>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: <?= json_encode($flashType, JSON_UNESCAPED_UNICODE) ?>,
                title: 'Thông báo',
                html: <?= json_encode($flashMessage, JSON_UNESCAPED_UNICODE) ?>,
                confirmButtonText: 'Đóng'
            });
        });
        <?php endif; ?>
    </script>
</body>
</html>
