<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include '../../../db_connect.php';
include '../../../includes/admin_header.php';
include '../../../includes/auth_check.php';
requireRole(['Admin', 'Manager']);

// ====== Bộ lọc ======
$buildingId = isset($_GET['building']) && $_GET['building'] !== 'all'
    ? (int)$_GET['building'] : null;

// Lấy danh sách tòa nhà (để filter)
$buildings = $conn->query("SELECT BuildingID, BuildingName FROM Buildings ORDER BY BuildingName");

// --------- Subquery tính lấp đầy theo phòng ---------
$roomOccupancySql = "
    SELECT
        r.RoomID,
        r.RoomNumber,
        b.BuildingID,
        b.BuildingName,
        COALESCE(r.Capacity, 1) AS Capacity,
        COUNT(c.ContractID) AS Occupied
    FROM Rooms r
    JOIN Buildings b ON r.BuildingID = b.BuildingID
    LEFT JOIN Contracts c
        ON c.RoomID = r.RoomID
        AND c.Status = 'Hiệu lực'
    " . ($buildingId ? "WHERE b.BuildingID = ?" : "") . "
    GROUP BY r.RoomID
    ORDER BY b.BuildingName ASC, r.RoomNumber ASC
";

if ($buildingId) {
    $stmtRoom = $conn->prepare($roomOccupancySql);
    $stmtRoom->bind_param("i", $buildingId);
    $stmtRoom->execute();
    $roomsRes = $stmtRoom->get_result();
} else {
    $roomsRes = $conn->query($roomOccupancySql);
}

// Gom dữ liệu phòng để làm tổng hợp
$rooms = [];
$totalCapacity = 0;
$totalOccupied = 0;

while ($row = $roomsRes->fetch_assoc()) {
    $cap = max(1, (int)$row['Capacity']);
    $occ = (int)$row['Occupied'];
    $rate = $cap > 0 ? round($occ * 100 / $cap, 1) : 0.0;

    $row['Rate'] = $rate;
    $rooms[] = $row;

    $totalCapacity += $cap;
    $totalOccupied += min($occ, $cap);
}

$overallRate = $totalCapacity > 0 ? round($totalOccupied * 100 / $totalCapacity, 1) : 0.0;

// --------- Tổng hợp theo tòa ---------
$byBuilding = [];
foreach ($rooms as $r) {
    $bid = $r['BuildingID'];
    if (!isset($byBuilding[$bid])) {
        $byBuilding[$bid] = [
            'BuildingName' => $r['BuildingName'],
            'Capacity' => 0,
            'Occupied' => 0
        ];
    }
    $byBuilding[$bid]['Capacity'] += max(1, (int)$r['Capacity']);
    $byBuilding[$bid]['Occupied'] += min((int)$r['Occupied'], max(1, (int)$r['Capacity']));
}

// Xác định màu sắc cho tỷ lệ
function getRateColor($rate)
{
    if ($rate >= 90) return 'good';
    if ($rate >= 60) return 'warn';
    return 'bad';
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tỷ lệ lấp đầy phòng | Hệ thống KTX</title>
    <link rel="stylesheet" href="../../assets/css/admin/admin_header.css">
    <link rel="stylesheet" href="../../../assets/css/staff/room/staff_room_occupancy.css">
    <link rel="stylesheet" href="../../../assets/vendor/fontawesome/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
    <div class="occ-container">
        <div class="page-header">
            <h2><i class="fas fa-chart-pie"></i> Tỷ lệ lấp đầy phòng</h2>

            <form method="get" class="filters">
                <div class="filter-group">
                    <label><i class="fas fa-building"></i> Tòa nhà:</label>
                    <select name="building" onchange="this.form.submit()">
                        <option value="all" <?= $buildingId ? '' : 'selected' ?>>Tất cả tòa</option>
                        <?php while ($b = $buildings->fetch_assoc()): ?>
                            <option value="<?= $b['BuildingID'] ?>"
                                <?= ($buildingId && $buildingId == $b['BuildingID']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($b['BuildingName']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </form>
        </div>

        <!-- Tổng quan -->
        <div class="overall-cards">
            <div class="card">
                <div class="card-title"><i class="fas fa-bed"></i> Sức chứa</div>
                <div class="card-number"><?= number_format($totalCapacity) ?></div>
                <div class="card-sub">Giường khả dụng</div>
                <div class="progress">
                    <span style="width: 100%; background: var(--primary);"></span>
                </div>
            </div>
            <div class="card">
                <div class="card-title"><i class="fas fa-users"></i> Đang ở</div>
                <div class="card-number"><?= number_format($totalOccupied) ?></div>
                <div class="card-sub">Hợp đồng hiệu lực</div>
                <div class="progress">
                    <span style="width: <?= min(100, ($totalOccupied / max(1, $totalCapacity)) * 100) ?>%; background: var(--secondary);"></span>
                </div>
            </div>
            <div class="card">
                <div class="card-title"><i class="fas fa-percentage"></i> Tỷ lệ lấp đầy</div>
                <div class="card-number <?= getRateColor($overallRate) ?>">
                    <?= $overallRate ?>%
                </div>
                <div class="card-sub">Tổng tỷ lệ sử dụng</div>
                <div class="progress">
                    <span style="width: <?= min(100, $overallRate) ?>%;"></span>
                </div>
            </div>
        </div>

        <!-- Theo tòa -->
        <div class="block">
            <div class="block-title"><i class="fas fa-city"></i> Tỷ lệ theo tòa</div>
            <?php if (count($byBuilding) > 0): ?>
                <div class="building-grid">
                    <?php foreach ($byBuilding as $bid => $v):
                        $cap = $v['Capacity'] ?: 1;
                        $occ = min($v['Occupied'], $cap);
                        $rate = round($occ * 100 / $cap, 1);
                        $rateColor = getRateColor($rate);
                    ?>
                        <div class="bcard">
                            <div class="bname">
                                <i class="fas fa-building"></i>
                                <?= htmlspecialchars($v['BuildingName']) ?>
                            </div>
                            <div class="bnums">
                                <span><?= number_format($occ) ?>/<?= number_format($cap) ?> giường</span>
                                <strong class="<?= $rateColor ?>"><?= $rate ?>%</strong>
                            </div>
                            <div class="progress small">
                                <span style="width: <?= min(100, $rate) ?>%;"></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-inbox"></i>
                    <p>Không có dữ liệu tòa nhà.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Chi tiết từng phòng -->
        <div class="block">
            <div class="block-title"><i class="fas fa-door-open"></i> Chi tiết từng phòng</div>
            <?php if (count($rooms) > 0): ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th><i class="fas fa-building"></i> Tòa</th>
                                <th><i class="fas fa-door-closed"></i> Phòng</th>
                                <th><i class="fas fa-bed"></i> Sức chứa</th>
                                <th><i class="fas fa-user-check"></i> Đang ở</th>
                                <th><i class="fas fa-chart-line"></i> Tỷ lệ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rooms as $r):
                                $cap = max(1, (int)$r['Capacity']);
                                $occ = min((int)$r['Occupied'], $cap);
                                $rate = $r['Rate'];
                                $rateColor = getRateColor($rate);
                            ?>
                                <tr>
                                    <td>
                                        <i class="fas fa-building" style="color: var(--gray); margin-right: 8px;"></i>
                                        <?= htmlspecialchars($r['BuildingName']) ?>
                                    </td>
                                    <td><strong><?= htmlspecialchars($r['RoomNumber']) ?></strong></td>
                                    <td><?= number_format($cap) ?></td>
                                    <td><?= number_format($occ) ?></td>
                                    <td class="rate">
                                        <span class="rate-num <?= $rateColor ?>"><?= $rate ?>%</span>
                                        <div class="progress mini <?= $rateColor ?>">
                                            <span style="width: <?= min(100, $rate) ?>%;"></span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-inbox"></i>
                    <p>Không có phòng nào phù hợp bộ lọc.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Animation for progress bars
        document.addEventListener('DOMContentLoaded', function() {
            const progressBars = document.querySelectorAll('.progress span');
            progressBars.forEach(bar => {
                const width = bar.style.width;
                bar.style.width = '0';
                setTimeout(() => {
                    bar.style.width = width;
                }, 500);
            });
        });

        // Add hover effects to cards
        const cards = document.querySelectorAll('.card, .bcard');
        cards.forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
            });

            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
    </script>
</body>

</html>