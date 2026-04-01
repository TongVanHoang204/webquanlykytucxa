<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$BASE_URL = '';
require_once __DIR__ . '/../../../db_connect.php';
require_once __DIR__ . '/../../../includes/auth_check.php';
include __DIR__ . '/../../../includes/header.php';

$userID = (int)($_SESSION['UserID'] ?? 0);
$isLoggedIn = $userID > 0;

$student = null;
if ($isLoggedIn) {
    $st = $conn->prepare("SELECT s.StudentID, s.FullName, s.StudentCode, f.FacultyName FROM Students s LEFT JOIN Faculties f ON s.FacultyID = f.FacultyID WHERE s.UserID = ? LIMIT 1");
    $st->bind_param("i", $userID); $st->execute(); $student = $st->get_result()->fetch_assoc(); $st->close();
}

$roomID = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_GET['room_id'] ?? 0);
if ($roomID <= 0) {
    echo "<div style='text-align:center;padding:60px;color:var(--text-secondary);'>Phòng không tồn tại.</div>";
    include __DIR__ . '/../../../includes/footer.php'; exit;
}

$st = $conn->prepare("
    SELECT r.RoomID, r.RoomNumber, r.Capacity, r.RoomPrice, r.RoomType, r.Description AS RoomDesc,
           r.Status AS RoomStatus, r.ImagePath, b.BuildingName, b.Description AS BuildingDesc,
           COUNT(CASE WHEN c.Status = 'Hiệu lực' THEN 1 END) AS CurrentOccupants,
           (r.Capacity - COUNT(CASE WHEN c.Status = 'Hiệu lực' THEN 1 END)) AS AvailableSlots
    FROM Rooms r INNER JOIN Buildings b ON r.BuildingID = b.BuildingID
    LEFT JOIN Contracts c ON c.RoomID = r.RoomID AND c.Status = 'Hiệu lực'
    WHERE r.RoomID = ? GROUP BY r.RoomID LIMIT 1
");
$st->bind_param("i", $roomID); $st->execute(); $roomRes = $st->get_result(); $st->close();
if (!$roomRes || $roomRes->num_rows === 0) {
    echo "<div style='text-align:center;padding:60px;color:var(--text-secondary);'>Không tìm thấy phòng.</div>";
    include __DIR__ . '/../../../includes/footer.php'; exit;
}
$room = $roomRes->fetch_assoc();

$mates = [];
$st = $conn->prepare("SELECT s.FullName, s.StudentCode, f.FacultyName FROM Contracts c INNER JOIN Students s ON c.StudentID = s.StudentID LEFT JOIN Faculties f ON s.FacultyID = f.FacultyID WHERE c.RoomID = ? AND c.Status = 'Hiệu lực' ORDER BY s.FullName");
if ($st) { $st->bind_param("i", $roomID); $st->execute(); $r = $st->get_result(); while ($m = $r->fetch_assoc()) $mates[] = $m; $st->close(); }

$hasContract = false;
if ($isLoggedIn && $student) {
    $sid = (int)$student['StudentID'];
    $st = $conn->prepare("SELECT ContractID FROM Contracts WHERE StudentID = ? AND Status = 'Hiệu lực' LIMIT 1");
    if ($st) { $st->bind_param("i", $sid); $st->execute(); $hasContract = $st->get_result()->num_rows > 0; $st->close(); }
}

$profileCompleted = false;
if ($isLoggedIn && $student) {
    $profileCompleted = !empty($student['FullName']) && !empty($student['StudentCode']);
}

$available = (int)$room['AvailableSlots'];
$capacity  = (int)$room['Capacity'];
$current   = (int)$room['CurrentOccupants'];
$roomImage = !empty($room['ImagePath']) ? $base . htmlspecialchars($room['ImagePath']) : $base . 'assets/img/room-default.jpg';

function roomStatusBadge($s) {
    return match($s) { 'Trống' => 'mod-badge-emerald', 'Đang sửa chữa' => 'mod-badge-amber', default => 'mod-badge-rose' };
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chi tiết phòng <?= htmlspecialchars($room['RoomNumber']) ?> | Ký túc xá</title>
    <link rel="stylesheet" href="<?= $base ?>assets/css/global.css">
    <link rel="stylesheet" href="<?= $base ?>assets/css/modules_shared.css">
    <style>
        .rd-pg { max-width:900px; margin:0 auto; padding:30px 20px; }
        .rd-hero { display:grid; grid-template-columns:320px 1fr; gap:24px; background:var(--surface); border:1px solid var(--stroke); border-radius:20px; overflow:hidden; margin-bottom:24px; }
        @media(max-width:768px) { .rd-hero { grid-template-columns:1fr; } }
        .rd-hero img { width:100%; height:100%; object-fit:cover; min-height:240px; }
        .rd-hero-info { padding:24px; display:flex; flex-direction:column; justify-content:center; gap:10px; }
        .rd-hero-info h1 { font-size:1.3rem; font-weight:800; margin:0; display:flex; align-items:center; gap:8px; color:var(--text); }
        .rd-hero-info .rd-meta { font-size:0.88rem; color:var(--text-secondary); display:flex; align-items:center; gap:6px; margin:0; }
        .rd-hero-info .rd-price { font-size:1.1rem; font-weight:800; color:var(--primary); margin:4px 0; }
        .rd-hero-info .rd-cap { font-size:0.88rem; color:var(--text-secondary); margin:0; }
        .rd-hero-actions { display:flex; gap:10px; flex-wrap:wrap; margin-top:8px; }

        .rd-card { background:var(--surface); border:1px solid var(--stroke); border-radius:16px; padding:24px; margin-bottom:16px; }
        .rd-card h2 { font-size:0.95rem; font-weight:700; margin:0 0 14px; display:flex; align-items:center; gap:8px; color:var(--text); }
        .rd-card p { font-size:0.88rem; color:var(--text); line-height:1.7; margin:0; }

        .mate-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(220px, 1fr)); gap:12px; }
        .mate-item { display:flex; align-items:center; gap:12px; padding:14px; background:var(--bg); border-radius:12px; border:1px solid var(--stroke); }
        .mate-av { width:40px; height:40px; border-radius:12px; background:linear-gradient(135deg,#7209b7,#560bad); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:0.9rem; flex-shrink:0; }
        .mate-item h4 { font-size:0.88rem; font-weight:700; margin:0 0 2px; color:var(--text); }
        .mate-item p { font-size:0.78rem; color:var(--text-secondary); margin:0; }
    </style>
</head>
<body>
    <div class="rd-pg">
        <!-- Hero -->
        <div class="rd-hero">
            <img src="<?= $roomImage ?>" onerror="this.onerror=null;this.src='<?= $base ?>assets/img/room-default.jpg';" alt="Phòng <?= htmlspecialchars($room['RoomNumber']) ?>">
            <div class="rd-hero-info">
                <h1><i class="fas fa-door-open"></i> Phòng <?= htmlspecialchars($room['RoomNumber']) ?></h1>
                <p class="rd-meta"><i class="fas fa-building"></i> Tòa: <?= htmlspecialchars($room['BuildingName']) ?></p>
                <p class="rd-meta"><i class="fas fa-bed"></i> Loại: <?= htmlspecialchars($room['RoomType'] ?: 'Chưa cập nhật') ?></p>
                <p class="rd-price"><i class="fas fa-money-bill-wave"></i> <?= number_format($room['RoomPrice'], 0, ',', '.') ?>₫/tháng</p>
                <p class="rd-cap"><i class="fas fa-users"></i> Sức chứa: <?= $capacity ?> | Đang ở: <?= $current ?> | Còn: <strong style="color:<?= $available > 0 ? 'var(--success)' : 'var(--danger)' ?>"><?= $available ?></strong></p>
                <span class="mod-badge <?= roomStatusBadge($room['RoomStatus']) ?>"><?= htmlspecialchars($room['RoomStatus']) ?></span>
                <div class="rd-hero-actions">
                    <a href="register_room.php" class="mod-btn mod-btn-outline mod-btn-sm"><i class="fas fa-arrow-left"></i> Danh sách phòng</a>
                    <?php if (!$isLoggedIn): ?>
                        <a href="<?= $base ?>login.php" class="mod-btn mod-btn-primary mod-btn-sm"><i class="fas fa-sign-in-alt"></i> Đăng nhập</a>
                    <?php elseif (!$profileCompleted): ?>
                        <a href="<?= $base ?>modules/user/students/student_form.php" class="mod-btn mod-btn-primary mod-btn-sm" style="background:var(--gradient-warning);"><i class="fas fa-user-edit"></i> Hoàn thiện hồ sơ</a>
                    <?php elseif (!$hasContract && $available > 0 && $room['RoomStatus'] !== 'Đang sửa chữa'): ?>
                        <a href="../room_requests/request_create.php?room_id=<?= (int)$room['RoomID'] ?>" class="mod-btn mod-btn-primary mod-btn-sm"><i class="fas fa-check"></i> Đăng ký phòng này</a>
                    <?php else: ?>
                        <button class="mod-btn mod-btn-outline mod-btn-sm" disabled><i class="fas fa-ban"></i> Không thể đăng ký</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Room Description -->
        <div class="rd-card">
            <h2><i class="fas fa-info-circle"></i> Thông tin phòng</h2>
            <p><?= nl2br(htmlspecialchars($room['RoomDesc'] ?: 'Chưa có mô tả chi tiết.')) ?></p>
        </div>

        <!-- Building Description -->
        <div class="rd-card">
            <h2><i class="fas fa-building"></i> Thông tin tòa nhà</h2>
            <p><?= nl2br(htmlspecialchars($room['BuildingDesc'] ?: 'Chưa có mô tả tòa nhà.')) ?></p>
        </div>

        <!-- Occupants -->
        <div class="rd-card">
            <h2><i class="fas fa-users"></i> Sinh viên đang ở (<?= count($mates) ?>)</h2>
            <?php if (count($mates) > 0): ?>
                <div class="mate-grid">
                    <?php foreach ($mates as $m): ?>
                        <div class="mate-item">
                            <div class="mate-av"><?= strtoupper(mb_substr($m['FullName'], 0, 1, 'UTF-8')) ?></div>
                            <div>
                                <h4><?= htmlspecialchars($m['FullName']) ?></h4>
                                <p>MSSV: <?= htmlspecialchars($m['StudentCode']) ?></p>
                                <p>Khoa: <?= htmlspecialchars($m['FacultyName'] ?? 'Chưa cập nhật') ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="text-align:center;padding:20px;color:var(--text-secondary);font-size:0.88rem;">Chưa có sinh viên nào đang ở phòng này.</div>
            <?php endif; ?>
        </div>

        <!-- Current User Info -->
        <div class="rd-card">
            <h2><i class="fas fa-user-graduate"></i> Sinh viên đăng nhập</h2>
            <?php if ($isLoggedIn && $student): ?>
                <p><strong>Họ tên:</strong> <?= htmlspecialchars($student['FullName']) ?> · <strong>MSSV:</strong> <?= htmlspecialchars($student['StudentCode']) ?> · <strong>Khoa:</strong> <?= htmlspecialchars($student['FacultyName'] ?? 'Chưa cập nhật') ?></p>
            <?php elseif ($isLoggedIn): ?>
                <p>Bạn chưa hoàn thiện hồ sơ. <a href="<?= $base ?>modules/user/students/student_form.php">Hoàn thiện ngay</a></p>
            <?php else: ?>
                <p>Chưa đăng nhập. <a href="<?= $base ?>login.php">Đăng nhập</a> để xem thông tin.</p>
            <?php endif; ?>
        </div>
    </div>

    <?php include __DIR__ . '/../../../includes/footer.php'; ?>
</body>
</html>