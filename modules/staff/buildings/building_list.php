<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../../db_connect.php';
require_once '../../../includes/auth_check.php';
requireRole(['Admin']);

$conn->set_charset('utf8mb4');

function e($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/* ========== BỘ LỌC ========== */
$search = trim($_GET['search'] ?? '');
$view   = $_GET['view'] ?? 'table'; // 'table' | 'card'

/* ========== TRUY VẤN DANH SÁCH TÒA + THỐNG KÊ PHÒNG ========== */
$where = " WHERE 1=1 ";
if ($search !== '') {
    $kw = $conn->real_escape_string($search);
    $where .= " AND (b.BuildingName LIKE '%$kw%' OR b.Description LIKE '%$kw%') ";
}

$sql = "
    SELECT 
        b.BuildingID,
        b.BuildingName,
        b.Description,
        b.Floors,

        -- Đếm phòng theo Rooms
        COUNT(r.RoomID) AS TotalRooms,
        SUM(CASE WHEN r.Status = 'Trống'   THEN 1 ELSE 0 END) AS EmptyRooms,
        SUM(CASE WHEN r.Status = 'Đầy'     THEN 1 ELSE 0 END) AS FullRooms,
        SUM(CASE WHEN r.Status = 'Bảo trì' THEN 1 ELSE 0 END) AS MaintenanceRooms,

        -- Tổng sức chứa = sum Capacity của phòng trong tòa
        COALESCE(SUM(r.Capacity), 0) AS TotalCapacity,

        -- Số SV đang ở = đếm HĐ hiệu lực thuộc các phòng trong tòa
        (
            SELECT COUNT(*)
            FROM Contracts c
            JOIN Rooms r2 ON r2.RoomID = c.RoomID
            WHERE c.Status = 'Hiệu lực'
              AND r2.BuildingID = b.BuildingID
        ) AS CurrentOccupants

    FROM Buildings b
    LEFT JOIN Rooms r ON r.BuildingID = b.BuildingID
    $where
    GROUP BY b.BuildingID, b.BuildingName, b.Description, b.Floors
    ORDER BY b.BuildingName ASC
";

$res = $conn->query($sql);

/* ========== TÍNH TỔNG QUAN ========== */
$buildings  = [];
$totalB     = 0;
$totalRooms = 0;
$totalCap   = 0;
$totalOcc   = 0;

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $buildings[] = $row;
        $totalB++;
        $totalRooms += (int)$row['TotalRooms'];
        $totalCap   += (int)$row['TotalCapacity'];
        $totalOcc   += (int)$row['CurrentOccupants'];
    }
}

$globalOccRate = ($totalCap > 0) ? round($totalOcc / $totalCap * 100) : 0;

require_once '../../../includes/admin_header.php';
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Danh sách Tòa nhà | Hệ thống Ký túc xá</title>

    <link rel="stylesheet" href="../../../assets/css/admin/admin_header.css">
    <link rel="stylesheet" href="../../../assets/css/staff/buildings/staff_building_list.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>

<body>
    <div class="building-container">
        <!-- HEADER -->
        <div class="page-header">
            <h2><i class="fa-solid fa-building"></i> Quản lý Tòa nhà</h2>
            <div class="header-actions">
                <div class="view-toggle">
                    <button type="button"
                        class="btn <?= $view === 'table' ? 'primary' : '' ?>"
                        onclick="setView('table')">
                        <i class="fa-solid fa-table"></i>
                    </button>
                    <button type="button"
                        class="btn <?= $view === 'card' ? 'primary' : '' ?>"
                        onclick="setView('card')">
                        <i class="fa-solid fa-border-all"></i>
                    </button>
                </div>
                <a href="building_create.php" class="btn primary">
                    <i class="fa-solid fa-plus"></i> Thêm tòa nhà
                </a>
            </div>
        </div>

        <!-- STATS -->
        <div class="stats-cards">
            <div class="stat-card">
                <div class="label">Tổng số tòa</div>
                <div class="value"><?= number_format($totalB) ?></div>
            </div>
            <div class="stat-card">
                <div class="label">Tổng phòng</div>
                <div class="value"><?= number_format($totalRooms) ?></div>
            </div>
            <div class="stat-card">
                <div class="label">Tổng sức chứa</div>
                <div class="value"><?= number_format($totalCap) ?></div>
            </div>
            <div class="stat-card">
                <div class="label">Đang ở</div>
                <div class="value"><?= number_format($totalOcc) ?></div>
            </div>
            <div class="stat-card">
                <div class="label">Tỷ lệ lấp đầy</div>
                <div class="value"><?= $globalOccRate ?>%</div>
            </div>
        </div>

        <!-- FILTERS -->
        <form method="get" class="filters">
            <input type="hidden" name="view" value="<?= e($view) ?>">
            <input type="text"
                name="search"
                value="<?= e($search) ?>"
                placeholder="Tìm theo tên tòa nhà hoặc mô tả...">
            <button class="btn primary" type="submit">
                <i class="fa-solid fa-search"></i> Tìm kiếm
            </button>
            <?php if ($search): ?>
                <a href="?view=<?= e($view) ?>" class="btn">
                    <i class="fa-solid fa-times"></i> Xóa bộ lọc
                </a>
            <?php endif; ?>
        </form>

        <?php if ($view === 'card'): ?>

            <!-- ========== CARD VIEW ========== -->
            <div class="buildings-grid">
                <?php if (!empty($buildings)): ?>
                    <?php foreach ($buildings as $b):
                        $cap  = (int)$b['TotalCapacity'];
                        $occ  = (int)$b['CurrentOccupants'];
                        $rate = $cap > 0 ? round($occ / $cap * 100) : 0;
                        $emptyRooms       = (int)$b['EmptyRooms'];
                        $fullRooms        = (int)$b['FullRooms'];
                        $maintenanceRooms = (int)$b['MaintenanceRooms'];
                    ?>
                        <div class="building-card">
                            <div class="building-header">
                                <div class="building-title">
                                    <h3><?= e($b['BuildingName']) ?></h3>
                                    <div class="floors">
                                        <i class="fa-solid fa-layer-group"></i>
                                        <?= $b['Floors'] ? $b['Floors'] . ' tầng' : 'Chưa xác định' ?>
                                    </div>
                                </div>
                                <div class="actions">
                                    <a href="building_edit.php?id=<?= (int)$b['BuildingID'] ?>"
                                        class="action-btn edit" title="Sửa tòa nhà">
                                        <i class="fa-solid fa-pen"></i>
                                    </a>
                                    <a href="../rooms/rooms.php?search=&building=<?= urlencode($b['BuildingName']) ?>&type=all&status=all"
                                        class="action-btn view"
                                        title="Xem phòng trong tòa <?= e($b['BuildingName']) ?>">
                                        <i class="fa-solid fa-door-open"></i>
                                    </a>

                                    <?php if (($_SESSION['Role'] ?? '') === 'Admin'): ?>
                                        <button type="button"
                                            class="action-btn delete"
                                            title="Xóa tòa nhà"
                                            onclick="deleteBuilding(<?= (int)$b['BuildingID'] ?>,'<?= e($b['BuildingName']) ?>', this)">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>


                            <div class="building-stats">
                                <div class="building-stat">
                                    <span class="number"><?= (int)$b['TotalRooms'] ?></span>
                                    <span class="label">Tổng phòng</span>
                                </div>
                                <div class="building-stat">
                                    <span class="number"><?= number_format($cap) ?></span>
                                    <span class="label">Sức chứa</span>
                                </div>
                                <div class="building-stat">
                                    <span class="number"><?= number_format($occ) ?></span>
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

                            <!-- Trạng thái phòng: 3 ô ngang -->
                            <div class="room-stats-horizontal">
                                <div class="room-stat-item empty">
                                    <span class="dot"></span>
                                    <span class="room-label">Trống</span>
                                    <span class="room-count"><?= $emptyRooms ?></span>
                                </div>
                                <div class="room-stat-item full">
                                    <span class="dot"></span>
                                    <span class="room-label">Đầy</span>
                                    <span class="room-count"><?= $fullRooms ?></span>
                                </div>
                                <div class="room-stat-item maintenance">
                                    <span class="dot"></span>
                                    <span class="room-label">Bảo trì</span>
                                    <span class="room-count"><?= $maintenanceRooms ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty" style="grid-column:1 / -1;">
                        <i class="fa-regular fa-building"></i>
                        <p>Không tìm thấy tòa nhà nào.</p>
                        <a href="building_create.php" class="btn primary">
                            <i class="fa-solid fa-plus"></i> Thêm tòa nhà đầu tiên
                        </a>
                    </div>
                <?php endif; ?>
            </div>

        <?php else: ?>

            <!-- ========== TABLE VIEW ========== -->
            <div class="table-wrap">
                <?php if (!empty($buildings)): ?>
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
                            <?php $i = 1;
                            foreach ($buildings as $b):
                                $cap  = (int)$b['TotalCapacity'];
                                $occ  = (int)$b['CurrentOccupants'];
                                $rate = $cap > 0 ? round($occ / $cap * 100) : 0;
                                $emptyRooms       = (int)$b['EmptyRooms'];
                                $fullRooms        = (int)$b['FullRooms'];
                                $maintenanceRooms = (int)$b['MaintenanceRooms'];
                            ?>
                                <tr>
                                    <td><?= $i++ ?></td>
                                    <td>
                                        <strong><?= e($b['BuildingName']) ?></strong>
                                        <?php if (!empty($b['Description'])): ?>
                                            <br>
                                            <small class="text-muted">
                                                <?= e(mb_strimwidth($b['Description'], 0, 80, '...', 'UTF-8')) ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge">
                                            <i class="fa-solid fa-layer-group"></i>
                                            <?= $b['Floors'] ?: 'N/A' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge">
                                            <i class="fa-solid fa-door-open"></i>
                                            <?= (int)$b['TotalRooms'] ?> phòng
                                        </span>
                                    </td>
                                    <td>
                                        <strong><?= number_format($cap) ?></strong> /
                                        <strong style="color:var(--primary);">
                                            <?= number_format($occ) ?>
                                        </strong>
                                    </td>
                                    <td style="min-width:120px;">
                                        <div class="progress-line">
                                            <div class="progress-fill" style="width:<?= min(100, $rate) ?>%;"></div>
                                        </div>
                                        <small class="text-muted"><?= $rate ?>%</small>
                                    </td>
                                    <td>
                                        <!-- 3 trạng thái ngang, gọn -->
                                        <div class="room-stats-horizontal">
                                            <div class="room-stat-item empty">
                                                <span class="dot"></span>
                                                <span class="room-label">Trống</span>
                                                <span class="room-count"><?= $emptyRooms ?></span>
                                            </div>
                                            <div class="room-stat-item full">
                                                <span class="dot"></span>
                                                <span class="room-label">Đầy</span>
                                                <span class="room-count"><?= $fullRooms ?></span>
                                            </div>
                                            <div class="room-stat-item maintenance">
                                                <span class="dot"></span>
                                                <span class="room-label">Bảo trì</span>
                                                <span class="room-count"><?= $maintenanceRooms ?></span>
                                            </div>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="actions">
                                            <a href="../rooms/rooms.php?search=&building=<?= urlencode($b['BuildingName']) ?>&type=all&status=all"
                                                class="action-btn view"
                                                title="Xem phòng trong tòa <?= e($b['BuildingName']) ?>">
                                                <i class="fa-solid fa-door-open"></i>
                                            </a>

                                            <?php if (($_SESSION['Role'] ?? '') === 'Admin'): ?>
                                                <button type="button"
                                                    class="action-btn delete"
                                                    title="Xóa tòa nhà"
                                                    onclick="deleteBuilding(<?= (int)$b['BuildingID'] ?>,'<?= e($b['BuildingName']) ?>', this)">
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
                        <a href="building_create.php" class="btn primary">
                            <i class="fa-solid fa-plus"></i> Thêm tòa nhà đầu tiên
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function setView(view) {
            const url = new URL(window.location);
            url.searchParams.set('view', view);
            window.location.href = url.toString();
        }

        function deleteBuilding(id, name, btn) {
            Swal.fire({
                title: 'Xóa tòa nhà?',
                html: `Bạn có chắc muốn xóa <b>${name}</b>?<br>
               <small class="text-muted">Hành động này không thể hoàn tác. Tất cả phòng và dữ liệu liên quan có thể bị ảnh hưởng.</small>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: '<i class="fa-solid fa-trash"></i> Xóa',
                cancelButtonText: 'Hủy',
                reverseButtons: true
            }).then((res) => {
                if (!res.isConfirmed) return;

                if (btn) btn.classList.add('loading');

                fetch('building_delete_api.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: new URLSearchParams({
                            id
                        })
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Đã xóa',
                                text: data.message,
                                timer: 1500,
                                showConfirmButton: false
                            });

                            const row = btn.closest('.building-card') || btn.closest('tr');
                            if (row) {
                                row.style.transition = 'opacity .3s, transform .3s';
                                row.style.opacity = '0';
                                row.style.transform = 'translateX(-20px)';
                                setTimeout(() => row.remove(), 300);
                            }
                        } else {
                            Swal.fire('Lỗi', data.message || 'Không thể xóa tòa nhà.', 'error');
                        }
                    })
                    .catch(() => {
                        Swal.fire('Lỗi', 'Không thể kết nối máy chủ.', 'error');
                    })
                    .finally(() => {
                        if (btn) btn.classList.remove('loading');
                    });
            });
        }

        // click effect cho card
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.building-card').forEach(card => {
                card.addEventListener('click', function(e) {
                    if (!e.target.closest('.actions')) {
                        this.style.transform = 'scale(0.98)';
                        setTimeout(() => {
                            this.style.transform = '';
                        }, 150);
                    }
                });
            });
        });
    </script>
</body>

</html>