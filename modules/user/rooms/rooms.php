<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include '../../../db_connect.php';
include '../../../includes/header.php';
require_once __DIR__ . '/../../../includes/auth_check.php';
requireRole(['Student', 'Admin', 'Manager']);

$userID = (int)$_SESSION['UserID'];

$st = $conn->prepare("SELECT s.StudentID, s.FullName, s.StudentCode, f.FacultyName FROM Students s LEFT JOIN Faculties f ON s.FacultyID = f.FacultyID WHERE s.UserID = ?");
$st->bind_param("i", $userID);
$st->execute();
$studentRes = $st->get_result();
$st->close();

if (!$studentRes || $studentRes->num_rows === 0) {
    echo "<div style='text-align:center;padding:60px;color:var(--text-secondary);'>Không tìm thấy thông tin sinh viên!</div>";
    include '../../../includes/footer.php';
    exit;
}
$student   = $studentRes->fetch_assoc();
$studentID = (int)$student['StudentID'];

$st = $conn->prepare("
    SELECT c.*, r.RoomNumber, r.RoomType, r.Capacity, r.RoomPrice, r.Status AS RoomStatus,
           b.BuildingName, b.Description, b.Floors,
           (SELECT COUNT(*) FROM Contracts c2 WHERE c2.RoomID = c.RoomID AND c2.Status = 'Hiệu lực') AS Occupants
    FROM Contracts c
    INNER JOIN Rooms r ON c.RoomID = r.RoomID
    INNER JOIN Buildings b ON r.BuildingID = b.BuildingID
    WHERE c.StudentID = ? AND c.Status IN ('Hiệu lực', 'Chờ duyệt')
    ORDER BY c.ContractID DESC LIMIT 1
");
$st->bind_param("i", $studentID);
$st->execute();
$contractRes = $st->get_result();
$st->close();

if (!$contractRes || $contractRes->num_rows === 0) {
    ?>
    <link rel="stylesheet" href="<?= $base ?>assets/css/global.css">
    <link rel="stylesheet" href="<?= $base ?>assets/css/modules_shared.css">
    <div style="max-width:600px;margin:60px auto;text-align:center;">
        <div class="mod-empty" style="padding:60px;">
            <i class="fas fa-bed"></i>
            <p>Bạn chưa có hợp đồng phòng ở.</p>
            <a href="register_room.php" class="mod-btn mod-btn-primary"><i class="fas fa-door-open"></i> Đăng ký ngay</a>
        </div>
    </div>
    <?php include '../../../includes/footer.php'; exit;
}

$contract = $contractRes->fetch_assoc();
$contract['CurrentOccupants'] = (int)($contract['Occupants'] ?? 0);
$contract['Capacity'] = (int)($contract['Capacity'] ?? 0);
$contract['AvailableSlots'] = max(0, $contract['Capacity'] - $contract['CurrentOccupants']);
$roomID = (int)$contract['RoomID'];

$roommates = [];
if ($roomID > 0) {
    $st = $conn->prepare("SELECT s.FullName, s.StudentCode, f.FacultyName FROM Contracts c INNER JOIN Students s ON c.StudentID = s.StudentID LEFT JOIN Faculties f ON s.FacultyID = f.FacultyID WHERE c.RoomID = ? AND c.Status = 'Hiệu lực' AND c.StudentID <> ? ORDER BY s.FullName");
    if ($st) {
        $st->bind_param("ii", $roomID, $studentID);
        $st->execute();
        $matesRes = $st->get_result();
        while ($row = $matesRes->fetch_assoc()) $roommates[] = $row;
        $st->close();
    }
}

$today = strtotime(date('Y-m-d'));
$start = strtotime($contract['StartDate']);
$end   = strtotime($contract['EndDate']);
$progress = ($end > $start) ? max(0, min(100, (($today - $start) / ($end - $start)) * 100)) : 0;

function statusBadge($status) {
    return match($status) {
        'Hiệu lực', 'Trống' => 'mod-badge-emerald',
        'Chờ duyệt', 'Đang sửa chữa' => 'mod-badge-amber',
        default => 'mod-badge-gray',
    };
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Phòng ở của tôi | Ký túc xá</title>
    <link rel="stylesheet" href="<?= $base ?>assets/css/global.css">
    <link rel="stylesheet" href="<?= $base ?>assets/css/modules_shared.css">
    <style>
        .room-pg { max-width:1000px; margin:0 auto; padding:30px 20px; }
        .room-head { background:var(--gradient-primary); color:#fff; border-radius:20px; padding:28px 32px; margin-bottom:24px; position:relative; overflow:hidden; }
        .room-head::after { content:''; position:absolute; top:-40%; right:-15%; width:250px; height:250px; background:rgba(255,255,255,0.06); border-radius:50%; }
        .room-head h1 { font-size:1.3rem; font-weight:800; margin:0 0 6px; }
        .room-head p { font-size:0.88rem; opacity:0.85; margin:0; }

        .room-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:24px; }
        @media(max-width:768px) { .room-grid { grid-template-columns:1fr; } }

        .room-card { background:var(--surface); border:1px solid var(--stroke); border-radius:16px; padding:24px; }
        .room-card h3 { font-size:1rem; font-weight:700; margin:0 0 16px; display:flex; align-items:center; gap:8px; color:var(--text); }
        .room-card-head { display:flex; align-items:center; gap:16px; margin-bottom:16px; }
        .room-avatar { width:56px; height:56px; border-radius:16px; background:var(--gradient-primary); color:#fff; display:flex; align-items:center; justify-content:center; font-size:1.4rem; font-weight:800; flex-shrink:0; }
        .room-card-head h2 { font-size:1.1rem; font-weight:700; margin:0 0 4px; color:var(--text); }
        .room-card-head .sub { font-size:0.82rem; color:var(--text-secondary); margin:0; }

        .meta-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
        .meta-item { padding:10px 12px; background:var(--bg); border-radius:10px; }
        .meta-item label { font-size:0.76rem; color:var(--text-secondary); font-weight:600; display:flex; align-items:center; gap:4px; margin-bottom:4px; }
        .meta-item p { margin:0; font-size:0.88rem; font-weight:600; color:var(--text); }

        .progress-wrap { margin-top:16px; }
        .progress-header { display:flex; justify-content:space-between; font-size:0.82rem; color:var(--text-secondary); margin-bottom:6px; }
        .progress-bar { height:8px; background:var(--stroke); border-radius:999px; overflow:hidden; }
        .progress-fill { height:100%; background:var(--gradient-primary); border-radius:999px; transition:width 0.5s ease; }

        .mate-list { display:grid; grid-template-columns:repeat(auto-fill, minmax(220px, 1fr)); gap:12px; }
        .mate-card { display:flex; align-items:center; gap:12px; padding:14px; background:var(--bg); border-radius:12px; border:1px solid var(--stroke); }
        .mate-avatar { width:40px; height:40px; border-radius:12px; background:linear-gradient(135deg,#7209b7,#560bad); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:0.9rem; flex-shrink:0; }
        .mate-info h4 { font-size:0.88rem; font-weight:700; margin:0 0 2px; color:var(--text); }
        .mate-info p { font-size:0.78rem; color:var(--text-secondary); margin:0; }

        .room-actions { display:flex; gap:10px; flex-wrap:wrap; }
    </style>
</head>
<body>
    <div class="room-pg">
        <div class="room-head">
            <h1><i class="fas fa-home"></i> Phòng Ở Của Tôi</h1>
            <p>Thông tin hợp đồng, bạn cùng phòng và trạng thái phòng hiện tại.</p>
        </div>

        <div class="room-grid">
            <!-- Student Info -->
            <div class="room-card">
                <div class="room-card-head">
                    <div class="room-avatar"><?= strtoupper(mb_substr($student['FullName'], 0, 1, 'UTF-8')) ?></div>
                    <div>
                        <h2><?= htmlspecialchars($student['FullName']) ?></h2>
                        <p class="sub">MSSV: <strong><?= htmlspecialchars($student['StudentCode']) ?></strong> · Khoa: <strong><?= htmlspecialchars($student['FacultyName'] ?? 'Chưa cập nhật') ?></strong></p>
                    </div>
                </div>
                <div class="meta-grid">
                    <div class="meta-item">
                        <label><i class="fas fa-building"></i> Tòa nhà</label>
                        <p><?= htmlspecialchars($contract['BuildingName']) ?></p>
                    </div>
                    <div class="meta-item">
                        <label><i class="fas fa-door-open"></i> Phòng</label>
                        <p><?= htmlspecialchars($contract['RoomNumber']) ?> (<?= htmlspecialchars($contract['RoomType']) ?>)</p>
                    </div>
                    <div class="meta-item">
                        <label><i class="fas fa-file-signature"></i> Hợp đồng</label>
                        <p><span class="mod-badge <?= statusBadge($contract['Status']) ?>"><?= htmlspecialchars($contract['Status']) ?></span></p>
                    </div>
                    <div class="meta-item">
                        <label><i class="fas fa-bed"></i> Trạng thái phòng</label>
                        <p><span class="mod-badge <?= statusBadge($contract['RoomStatus']) ?>"><?= htmlspecialchars($contract['RoomStatus']) ?></span></p>
                    </div>
                </div>
            </div>

            <!-- Contract Summary -->
            <div class="room-card">
                <h3><i class="fas fa-chart-line"></i> Tóm tắt hợp đồng</h3>
                <div class="meta-grid">
                    <div class="meta-item"><label><i class="fas fa-money-bill-wave"></i> Tiền phòng/tháng</label><p style="color:var(--primary);font-weight:800;"><?= number_format($contract['RoomPrice'], 0, ',', '.') ?>₫</p></div>
                    <div class="meta-item"><label><i class="fas fa-hand-holding-usd"></i> Tiền cọc</label><p style="color:var(--primary);font-weight:800;"><?= number_format($contract['Deposit'], 0, ',', '.') ?>₫</p></div>
                    <div class="meta-item"><label><i class="fas fa-calendar-day"></i> Bắt đầu</label><p><?= date('d/m/Y', strtotime($contract['StartDate'])) ?></p></div>
                    <div class="meta-item"><label><i class="fas fa-calendar-times"></i> Kết thúc</label><p><?= date('d/m/Y', strtotime($contract['EndDate'])) ?></p></div>
                    <div class="meta-item"><label><i class="fas fa-users"></i> Sức chứa</label><p><?= $contract['Capacity'] ?> người</p></div>
                    <div class="meta-item"><label><i class="fas fa-user-friends"></i> Đang ở</label><p><?= $contract['CurrentOccupants'] ?> / <?= $contract['Capacity'] ?> (còn <?= $contract['AvailableSlots'] ?> chỗ)</p></div>
                </div>
                <div class="progress-wrap">
                    <div class="progress-header"><span>Tiến độ hợp đồng</span><span><?= round($progress) ?>%</span></div>
                    <div class="progress-bar"><div class="progress-fill" style="width:<?= round($progress) ?>%"></div></div>
                </div>
            </div>
        </div>

        <!-- Roommates -->
        <div class="room-card" style="margin-bottom:24px;">
            <h3><i class="fas fa-users"></i> Bạn cùng phòng</h3>
            <?php if (count($roommates) > 0): ?>
                <div class="mate-list">
                    <?php foreach ($roommates as $mate): ?>
                        <div class="mate-card">
                            <div class="mate-avatar"><?= strtoupper(mb_substr($mate['FullName'], 0, 1, 'UTF-8')) ?></div>
                            <div class="mate-info">
                                <h4><?= htmlspecialchars($mate['FullName']) ?></h4>
                                <p>MSSV: <?= htmlspecialchars($mate['StudentCode']) ?></p>
                                <p>Khoa: <?= htmlspecialchars($mate['FacultyName'] ?? '—') ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="text-align:center;padding:30px;color:var(--text-secondary);">
                    <i class="fas fa-user-friends" style="font-size:2rem;opacity:0.3;display:block;margin-bottom:10px;"></i>
                    Hiện tại bạn đang ở một mình.
                </div>
            <?php endif; ?>
        </div>

        <!-- Actions -->
        <div class="room-actions">
            <a href="<?= $base ?>modules/user/dashboard.php" class="mod-btn mod-btn-outline"><i class="fas fa-arrow-left"></i> Dashboard</a>
        </div>
    </div>

    <?php include '../../../includes/footer.php'; ?>
</body>
</html>