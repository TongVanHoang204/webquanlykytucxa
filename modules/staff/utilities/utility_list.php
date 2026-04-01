<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../../../db_connect.php';
require_once '../../../includes/admin_header.php';
require_once '../../../includes/auth_check.php';
requireRole(['Admin', 'Manager']);

/* ================== BỘ LỌC ================== */
$building = $_GET['building'] ?? 'all';
$month    = $_GET['month'] ?? date('m');
$year     = $_GET['year'] ?? date('Y');

$m = (int)$month;
$y = (int)$year;

/* ================== LẤY DỮ LIỆU TÒA NHÀ ================== */
$buildings = $conn->query("SELECT DISTINCT BuildingName FROM buildings ORDER BY BuildingName");

/* ================== TRUY VẤN DANH SÁCH ================== */
$baseQuery = "
    SELECT 
        r.RoomID, r.RoomNumber, b.BuildingName,
        ur.ReadingID,
        ur.ElectricOld AS CurrentElectricOld,
        ur.ElectricNew AS CurrentElectricNew,
        ur.WaterOld AS CurrentWaterOld,
        ur.WaterNew AS CurrentWaterNew,
        (SELECT ElectricNew FROM utility_readings 
         WHERE RoomID = r.RoomID 
         AND (ReadingYear < $y OR (ReadingYear = $y AND ReadingMonth < $m))
         ORDER BY ReadingYear DESC, ReadingMonth DESC LIMIT 1) AS PrevElectricNew,
        (SELECT WaterNew FROM utility_readings 
         WHERE RoomID = r.RoomID 
         AND (ReadingYear < $y OR (ReadingYear = $y AND ReadingMonth < $m))
         ORDER BY ReadingYear DESC, ReadingMonth DESC LIMIT 1) AS PrevWaterNew
    FROM rooms r
    JOIN buildings b ON r.BuildingID = b.BuildingID
    LEFT JOIN utility_readings ur ON ur.RoomID = r.RoomID AND ur.ReadingMonth = $m AND ur.ReadingYear = $y
    WHERE 1=1
";

if ($building !== 'all') {
    $b = $conn->real_escape_string($building);
    $baseQuery .= " AND b.BuildingName = '$b'";
}

$baseQuery .= " ORDER BY b.BuildingName ASC, r.RoomNumber ASC";
$result = $conn->query($baseQuery);

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chỉ số Điện Nước - Ký túc xá</title>
    <link rel="stylesheet" href="../../../assets/css/admin/admin_header.css">
    <link rel="stylesheet" href="../../../assets/css/staff/room/staff_rooms.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .utility-table input[type="number"] {
            width: 80px;
            padding: 5px;
            border: 1px solid #ccc;
            border-radius: 4px;
            text-align: right;
        }
        .utility-table th { text-align: center; }
        .utility-table td { vertical-align: middle; text-align: center; }
        .btn-save {
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            transition: 0.2s;
        }
        .btn-save:hover { background-color: var(--primary-dark); }
        .usage-col { font-weight: bold; color: var(--text-dark); }
    </style>
</head>
<body>
    <div class="room-container">
        <!-- Header -->
        <div class="page-header">
            <h2><i class="fas fa-bolt"></i> Quản lý Chỉ số Điện Nước</h2>
            <div>Tháng <?= $m ?> / Năm <?= $y ?></div>
        </div>

        <!-- Filters -->
        <form method="get" class="filters">
            <div class="filter-group">
                <label>Tòa nhà:</label>
                <select name="building" onchange="this.form.submit()">
                    <option value="all">Tất cả</option>
                    <?php while ($bldg = $buildings->fetch_assoc()): ?>
                        <option value="<?= htmlspecialchars($bldg['BuildingName']) ?>" <?= $building == $bldg['BuildingName'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($bldg['BuildingName']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Tháng:</label>
                <select name="month" onchange="this.form.submit()">
                    <?php for ($i=1; $i<=12; $i++): ?>
                        <option value="<?= $i ?>" <?= $m == $i ? 'selected' : '' ?>>Tháng <?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Năm:</label>
                <input type="number" name="year" value="<?= $y ?>" min="2020" max="2100" style="width: 80px;" onchange="this.form.submit()">
            </div>
        </form>

        <!-- Table -->
        <div class="room-table-container">
            <table class="room-table utility-table">
                <thead>
                    <tr>
                        <th rowspan="2">Phòng</th>
                        <th colspan="3">Chỉ số Điện</th>
                        <th colspan="3">Chỉ số Nước</th>
                        <th rowspan="2">Hành động</th>
                    </tr>
                    <tr>
                        <th>Cũ</th>
                        <th>Mới</th>
                        <th>Tiêu thụ</th>
                        <th>Cũ</th>
                        <th>Mới</th>
                        <th>Tiêu thụ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): 
                            $elecOld = $row['CurrentElectricOld'] !== null ? $row['CurrentElectricOld'] : ($row['PrevElectricNew'] ?? 0);
                            $elecNew = $row['CurrentElectricNew'] !== null ? $row['CurrentElectricNew'] : $elecOld;
                            
                            $waterOld = $row['CurrentWaterOld'] !== null ? $row['CurrentWaterOld'] : ($row['PrevWaterNew'] ?? 0);
                            $waterNew = $row['CurrentWaterNew'] !== null ? $row['CurrentWaterNew'] : $waterOld;

                            $elecUsage = max(0, $elecNew - $elecOld);
                            $waterUsage = max(0, $waterNew - $waterOld);
                            $isSaved = $row['ReadingID'] ? true : false;
                        ?>
                            <tr id="row-<?= $row['RoomID'] ?>">
                                <td>
                                    <strong><?= htmlspecialchars($row['RoomNumber']) ?></strong>
                                    <br><small><?= htmlspecialchars($row['BuildingName']) ?></small>
                                </td>
                                <td><input type="number" class="e-old" value="<?= $elecOld ?>" min="0"></td>
                                <td><input type="number" class="e-new" value="<?= $elecNew ?>" min="0"></td>
                                <td class="usage-col e-usage"><?= $elecUsage ?></td>
                                <td><input type="number" class="w-old" value="<?= $waterOld ?>" min="0"></td>
                                <td><input type="number" class="w-new" value="<?= $waterNew ?>" min="0"></td>
                                <td class="usage-col w-usage"><?= $waterUsage ?></td>
                                <td>
                                    <button class="btn-save" onclick="saveReading(<?= $row['RoomID'] ?>)">
                                        <i class="fas <?= $isSaved ? 'fa-check' : 'fa-save' ?>"></i> 
                                        <?= $isSaved ? 'Đã lưu' : 'Lưu' ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="8">Không có dữ liệu phòng.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php include '../../../includes/footer.php'; ?>

    <script>
        // Tự động tính usage khi nhập
        document.querySelectorAll('.utility-table tbody tr').forEach(row => {
            const eOld = row.querySelector('.e-old');
            const eNew = row.querySelector('.e-new');
            const wOld = row.querySelector('.w-old');
            const wNew = row.querySelector('.w-new');
            
            const eUsage = row.querySelector('.e-usage');
            const wUsage = row.querySelector('.w-usage');

            function updateUsage() {
                eUsage.innerText = Math.max(0, eNew.value - eOld.value);
                wUsage.innerText = Math.max(0, wNew.value - wOld.value);
            }

            eOld.addEventListener('input', updateUsage);
            eNew.addEventListener('input', updateUsage);
            wOld.addEventListener('input', updateUsage);
            wNew.addEventListener('input', updateUsage);
        });

        async function saveReading(roomId) {
            const row = document.getElementById('row-' + roomId);
            const btn = row.querySelector('.btn-save');
            const eOld = row.querySelector('.e-old').value;
            const eNew = row.querySelector('.e-new').value;
            const wOld = row.querySelector('.w-old').value;
            const wNew = row.querySelector('.w-new').value;

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang lưu...';

            try {
                const res = await fetch('utility_save_api.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        room_id: roomId,
                        month: <?= $m ?>,
                        year: <?= $y ?>,
                        e_old: parseInt(eOld),
                        e_new: parseInt(eNew),
                        w_old: parseInt(wOld),
                        w_new: parseInt(wNew)
                    })
                });
                const data = await res.json();
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Đã lưu thành công',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 1500
                    });
                    btn.innerHTML = '<i class="fas fa-check"></i> Đã lưu';
                    btn.style.backgroundColor = '#28a745';
                } else {
                    Swal.fire('Lỗi', data.message || 'Lưu thất bại', 'error');
                    btn.innerHTML = '<i class="fas fa-save"></i> Lưu';
                }
            } catch (err) {
                console.error(err);
                Swal.fire('Lỗi', 'Không thể kết nối', 'error');
                btn.innerHTML = '<i class="fas fa-save"></i> Lưu';
            } finally {
                btn.disabled = false;
            }
        }
    </script>
</body>
</html>
