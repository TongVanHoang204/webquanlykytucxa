<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../../db_connect.php';

$userID = (int)($_SESSION['UserID'] ?? 0);
$isLoggedIn = $userID > 0;

if ($userID <= 0) {
    echo '<!DOCTYPE html><html lang="vi"><head><meta charset="UTF-8"><title>Yêu cầu đăng nhập</title><script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script></head><body>
    <script>document.addEventListener("DOMContentLoaded",()=>{Swal.fire({icon:"info",title:"Bạn chưa đăng nhập",text:"Vui lòng đăng nhập để đăng ký phòng.",confirmButtonText:"Đăng nhập ngay",confirmButtonColor:"#3085d6"}).then(()=>window.location.href="/login.php");});</script></body></html>';
    exit;
}

require_once '../../../includes/auth_check.php';
requireLogin();
$swalScript = "";

$studentQuery = $conn->query("SELECT s.StudentID, s.FullName, s.StudentCode, s.Gender, s.BirthDate, s.Address, s.FacultyID, s.ClassName, s.CourseYear, f.FacultyName FROM Students s LEFT JOIN Faculties f ON s.FacultyID = f.FacultyID WHERE s.UserID = $userID");
if (!$studentQuery || $studentQuery->num_rows == 0) {
    include '../../../includes/header.php';
    echo "<div style='text-align:center;padding:60px;color:var(--text-secondary);'>Không tìm thấy thông tin sinh viên!</div>";
    include '../../../includes/footer.php'; exit;
}
$student = $studentQuery->fetch_assoc();
$studentID = (int)$student['StudentID'];

$checkContract = $conn->query("SELECT c.*, r.RoomNumber, b.BuildingName FROM Contracts c INNER JOIN Rooms r ON c.RoomID = r.RoomID INNER JOIN Buildings b ON r.BuildingID = b.BuildingID WHERE c.StudentID = $studentID AND c.Status = 'Hiệu lực'");
$hasContract = $checkContract && $checkContract->num_rows > 0;
$currentContract = $hasContract ? $checkContract->fetch_assoc() : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['room_id'])) {
    if ($hasContract) { $swalScript = "Swal.fire({icon:'warning',title:'Đã có phòng',text:'Bạn đã có phòng, không thể đăng ký thêm!',confirmButtonColor:'#f39c12'});"; }
    else { $swalScript = "Swal.fire({icon:'success',title:'Gửi yêu cầu thành công!',text:'Yêu cầu đăng ký phòng đã được gửi.',confirmButtonColor:'#27ae60'});"; }
}

$buildings = $conn->query("SELECT BuildingID, BuildingName FROM Buildings ORDER BY BuildingName ASC");
$searchBuilding = $_GET['building'] ?? '';
$searchRoomType = $_GET['room_type'] ?? '';
$searchMaxPrice = isset($_GET['max_price']) ? (int)$_GET['max_price'] : 0;

$roomsSql = "SELECT r.RoomID, r.RoomNumber, r.Capacity, r.RoomPrice, r.RoomType, r.Description, r.ImagePath, r.Status AS RoomStatus, b.BuildingName, COUNT(CASE WHEN c.Status='Hiệu lực' THEN 1 END) AS CurrentOccupants, (r.Capacity - COUNT(CASE WHEN c.Status='Hiệu lực' THEN 1 END)) AS AvailableSlots FROM Rooms r INNER JOIN Buildings b ON r.BuildingID = b.BuildingID LEFT JOIN Contracts c ON c.RoomID = r.RoomID AND c.Status = 'Hiệu lực' WHERE 1=1";
if ($searchBuilding !== "") $roomsSql .= " AND r.BuildingID = " . (int)$searchBuilding;
if ($searchRoomType !== "") $roomsSql .= " AND r.RoomType = '" . $conn->real_escape_string($searchRoomType) . "'";
if ($searchMaxPrice > 0) $roomsSql .= " AND r.RoomPrice <= $searchMaxPrice";
$roomsSql .= " GROUP BY r.RoomID, r.RoomNumber, r.Capacity, r.RoomPrice, r.RoomType, r.Description, r.ImagePath, r.Status, b.BuildingName HAVING AvailableSlots > 0 ORDER BY b.BuildingName, r.RoomNumber";
$rooms = $conn->query($roomsSql);

$profileCompleted = false;
if ($isLoggedIn && $student) {
    $profileCompleted = !empty($student['FullName']) && !empty($student['FacultyID']) && !empty($student['ClassName']);
}

include '../../../includes/header.php';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng ký phòng | Ký túc xá</title>
    <link rel="stylesheet" href="<?= $base ?>assets/css/global.css">
    <link rel="stylesheet" href="<?= $base ?>assets/css/modules_shared.css">
    <style>
        .rr-pg { max-width:1100px; margin:0 auto; padding:30px 20px; }
        .rr-head { background:var(--gradient-primary); color:#fff; border-radius:20px; padding:28px 32px; margin-bottom:24px; position:relative; overflow:hidden; }
        .rr-head::after { content:''; position:absolute; top:-40%; right:-15%; width:250px; height:250px; background:rgba(255,255,255,0.06); border-radius:50%; }
        .rr-head h1 { font-size:1.3rem; font-weight:800; margin:0 0 6px; }
        .rr-head p { font-size:0.88rem; opacity:0.8; margin:0; }

        .rr-stu { background:var(--surface); border:1px solid var(--stroke); border-radius:16px; padding:18px 22px; margin-bottom:20px; display:flex; align-items:center; gap:14px; }
        .rr-stu-av { width:48px; height:48px; border-radius:14px; background:var(--gradient-primary); color:#fff; display:flex; align-items:center; justify-content:center; font-size:1.4rem; flex-shrink:0; }
        .rr-stu h3 { font-size:0.95rem; font-weight:700; margin:0 0 2px; color:var(--text); }
        .rr-stu p { font-size:0.82rem; color:var(--text-secondary); margin:2px 0 0; }

        .rr-filter { background:var(--surface); border:1px solid var(--stroke); border-radius:16px; padding:18px 22px; margin-bottom:24px; }
        .rr-filter-grid { display:flex; gap:14px; flex-wrap:wrap; align-items:flex-end; }
        .rr-filter-item { flex:1; min-width:160px; }
        .rr-filter-item label { font-size:0.78rem; font-weight:600; color:var(--text-secondary); display:block; margin-bottom:4px; }
        .rr-filter-item select, .rr-filter-item input[type="range"] { width:100%; padding:8px 12px; border:1px solid var(--stroke); border-radius:8px; font-size:0.85rem; background:var(--bg); color:var(--text); box-sizing:border-box; }

        .rr-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); gap:16px; }
        .rr-room { background:var(--surface); border:1px solid var(--stroke); border-radius:16px; overflow:hidden; transition:all 0.3s; }
        .rr-room:hover { transform:translateY(-4px); box-shadow:0 12px 32px rgba(0,0,0,0.08); }
        .rr-room-img { position:relative; height:160px; overflow:hidden; }
        .rr-room-img img { width:100%; height:100%; object-fit:cover; }
        .rr-room-img .rr-badge { position:absolute; top:10px; right:10px; background:rgba(0,0,0,0.6); color:#fff; padding:4px 10px; border-radius:8px; font-size:0.72rem; font-weight:600; backdrop-filter:blur(4px); }
        .rr-room-body { padding:16px; }
        .rr-room-body h3 { font-size:1rem; font-weight:800; margin:0 0 8px; color:var(--text); }
        .rr-room-meta { display:flex; flex-direction:column; gap:4px; }
        .rr-room-meta span { font-size:0.82rem; color:var(--text-secondary); display:flex; align-items:center; gap:6px; }
        .rr-room-meta span i { width:14px; color:var(--primary); }
        .rr-room-price { font-size:0.95rem; font-weight:800; color:var(--primary); margin-top:8px; }
        .rr-room-foot { padding:0 16px 16px; }

        .rr-empty { text-align:center; padding:60px 20px; color:var(--text-secondary); }
        .rr-empty i { font-size:3rem; margin-bottom:12px; opacity:0.3; display:block; }
    </style>
</head>
<body>
    <div class="rr-pg">
        <div class="rr-head">
            <h1><i class="fas fa-bed"></i> Đăng ký Phòng Ký Túc Xá</h1>
            <p>Chọn phòng phù hợp với nhu cầu của bạn</p>
        </div>

        <!-- Student Info -->
        <div class="rr-stu">
            <div class="rr-stu-av"><i class="fas fa-user-graduate"></i></div>
            <div>
                <h3><?= htmlspecialchars($student['FullName']) ?></h3>
                <p><i class="fas fa-id-card"></i> MSSV: <?= htmlspecialchars($student['StudentCode']) ?> · Khoa: <?= htmlspecialchars($student['FacultyName'] ?? '—') ?></p>
                <?php if ($hasContract): ?>
                    <p style="color:var(--success);font-weight:600;"><i class="fas fa-check-circle"></i> Đã có phòng: <?= htmlspecialchars($currentContract['BuildingName']) ?> - <?= htmlspecialchars($currentContract['RoomNumber']) ?></p>
                <?php else: ?>
                    <p style="color:var(--warning);"><i class="fas fa-clock"></i> Chưa có phòng – hãy đăng ký ngay!</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Filter -->
        <div class="rr-filter">
            <form id="roomFilterForm" method="GET">
                <div class="rr-filter-grid">
                    <div class="rr-filter-item">
                        <label><i class="fas fa-building"></i> Tòa nhà</label>
                        <select name="building" id="building">
                            <option value="">Tất cả</option>
                            <?php if ($buildings): while ($b = $buildings->fetch_assoc()): ?>
                                <option value="<?= $b['BuildingID'] ?>" <?= $searchBuilding == $b['BuildingID'] ? 'selected' : '' ?>><?= htmlspecialchars($b['BuildingName']) ?></option>
                            <?php endwhile; endif; ?>
                        </select>
                    </div>
                    <div class="rr-filter-item">
                        <label><i class="fas fa-venus-mars"></i> Loại phòng</label>
                        <select name="room_type" id="room_type">
                            <option value="">Tất cả</option>
                            <option value="Nam" <?= $searchRoomType=='Nam'?'selected':'' ?>>Nam</option>
                            <option value="Nữ" <?= $searchRoomType=='Nữ'?'selected':'' ?>>Nữ</option>
                            <option value="Hỗn hợp" <?= $searchRoomType=='Hỗn hợp'?'selected':'' ?>>Hỗn hợp</option>
                        </select>
                    </div>
                    <div class="rr-filter-item">
                        <label><i class="fas fa-money-bill-wave"></i> Giá tối đa: <span id="max_price_text"><?= $searchMaxPrice ? number_format($searchMaxPrice,0,',','.') . ' đ' : 'Không giới hạn' ?></span></label>
                        <input type="range" id="max_price_range" name="max_price" min="0" max="3000000" step="50000" value="<?= $searchMaxPrice ?: 0 ?>">
                    </div>
                    <a href="register_room.php" class="mod-btn mod-btn-outline mod-btn-sm" style="align-self:flex-end;"><i class="fas fa-redo"></i> Reset</a>
                </div>
            </form>
        </div>

        <!-- Room List -->
        <?php if (!$hasContract): ?>
            <?php if ($rooms && $rooms->num_rows > 0): ?>
                <div class="rr-grid">
                    <?php while ($room = $rooms->fetch_assoc()):
                        $img = !empty($room['ImagePath']) ? '/'.$room['ImagePath'] : '/assets/img/room-default.jpg';
                        $available = (int)$room['AvailableSlots'];
                        $status = $room['RoomStatus'] ?? '';
                    ?>
                        <div class="rr-room">
                            <div class="rr-room-img">
                                <img src="<?= $img ?>" alt="Phòng <?= htmlspecialchars($room['RoomNumber']) ?>" onerror="this.src='/assets/img/room-default.jpg'">
                                <span class="rr-badge"><?= $available ?>/<?= (int)$room['Capacity'] ?> trống</span>
                            </div>
                            <div class="rr-room-body">
                                <h3>Phòng <?= htmlspecialchars($room['RoomNumber']) ?></h3>
                                <div class="rr-room-meta">
                                    <span><i class="fas fa-building"></i> <?= htmlspecialchars($room['BuildingName']) ?></span>
                                    <span><i class="fas fa-venus-mars"></i> <?= htmlspecialchars($room['RoomType'] ?: '—') ?></span>
                                    <?php if (!empty($room['Description'])): ?>
                                        <span><i class="fas fa-info-circle"></i> <?= htmlspecialchars(mb_strimwidth($room['Description'],0,60,'...')) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="rr-room-price"><?= number_format($room['RoomPrice'],0,',','.') ?> đ/tháng</div>
                            </div>
                            <div class="rr-room-foot">
                                <?php if (!$profileCompleted): ?>
                                    <a href="<?= $base ?>modules/user/students/student_form.php" class="mod-btn mod-btn-outline mod-btn-sm" style="width:100%;justify-content:center;"><i class="fas fa-user-edit"></i> Hoàn thiện hồ sơ</a>
                                <?php elseif ($available > 0 && $status !== 'Đang sửa chữa'): ?>
                                    <a href="../room_requests/request_create.php?room_id=<?= (int)$room['RoomID'] ?>" class="mod-btn mod-btn-primary mod-btn-sm" style="width:100%;justify-content:center;"><i class="fas fa-check"></i> Đăng ký</a>
                                <?php else: ?>
                                    <button class="mod-btn mod-btn-outline mod-btn-sm" disabled style="width:100%;justify-content:center;"><i class="fas fa-ban"></i> Không thể đăng ký</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="rr-empty"><i class="fas fa-bed"></i><h3>Không có phòng trống phù hợp</h3><p>Thử điều chỉnh bộ lọc hoặc quay lại sau.</p></div>
            <?php endif; ?>
        <?php else: ?>
            <div class="rr-empty"><i class="fas fa-check-circle" style="color:var(--success);"></i><h3>Bạn đã có phòng, không thể đăng ký thêm</h3></div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        const form = document.getElementById('roomFilterForm');
        const building = document.getElementById('building');
        const roomType = document.getElementById('room_type');
        const slider = document.getElementById('max_price_range');
        const priceText = document.getElementById('max_price_text');

        if (building) building.addEventListener('change', () => form.submit());
        if (roomType) roomType.addEventListener('change', () => form.submit());
        if (slider && priceText) {
            slider.addEventListener('input', () => {
                const v = Number(slider.value);
                priceText.innerText = v === 0 ? "Không giới hạn" : v.toLocaleString('vi-VN') + " đ";
            });
            slider.addEventListener('change', () => form.submit());
        }
        <?php if (!empty($swalScript)): ?><?= $swalScript ?><?php endif; ?>
    });
    </script>

    <?php include '../../../includes/footer.php'; ?>
</body>
</html>
