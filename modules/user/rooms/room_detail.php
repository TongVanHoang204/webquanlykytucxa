<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$BASE_URL = '';
require_once __DIR__ . '/../../../db_connect.php';
require_once __DIR__ . '/../../../includes/auth_check.php';
// ❌ Không chặn khách vãn lai - cho phép xem thông tin phòng
// requireRole(['Student', 'Admin', 'Manager']);
include __DIR__ . '/../../../includes/header.php';

$userID = (int)($_SESSION['UserID'] ?? 0);
$isLoggedIn = $userID > 0;

/* ==============================
   LẤY THÔNG TIN SINH VIÊN HIỆN TẠI (nếu đã đăng nhập)
================================ */
$student = null;
if ($isLoggedIn) {
    $stuSql = "
        SELECT s.StudentID, s.FullName, s.StudentCode, f.FacultyName
        FROM Students s
        LEFT JOIN Faculties f ON s.FacultyID = f.FacultyID
        WHERE s.UserID = ?
        LIMIT 1
    ";
    $st = $conn->prepare($stuSql);
    if (!$st) {
        die("Lỗi SQL (stuSql): " . $conn->error);
    }
    $st->bind_param("i", $userID);
    $st->execute();
    $stuRes = $st->get_result();
    $student = $stuRes->fetch_assoc();
    $st->close();
}

/* ==============================
   LẤY ROOM ID TỪ GET
================================ */
$roomID = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_GET['room_id'] ?? 0);
if ($roomID <= 0) {
    echo "<div class='alert alert-danger text-center'>Phòng không tồn tại hoặc tham số không hợp lệ.</div>";
    include __DIR__ . '/../../../includes/footer.php';
    exit;
}

/* ==============================
   LẤY CHI TIẾT PHÒNG + TÒA NHÀ + SỐ CHỖ TRỐNG THỰC
================================ */
$roomSql = "
    SELECT 
        r.RoomID,
        r.RoomNumber,
        r.Capacity,
        r.RoomPrice,
        r.RoomType,
        r.Description      AS RoomDesc,
        r.Status           AS RoomStatus,
        r.ImagePath,
        b.BuildingName,
        b.Description      AS BuildingDesc,
        -- số người đang ở thật (hợp đồng Hiệu lực)
        COUNT(CASE WHEN c.Status = 'Hiệu lực' THEN 1 END) AS CurrentOccupants,
        (r.Capacity - COUNT(CASE WHEN c.Status = 'Hiệu lực' THEN 1 END)) AS AvailableSlots
    FROM Rooms r
    INNER JOIN Buildings b ON r.BuildingID = b.BuildingID
    LEFT JOIN Contracts c 
        ON c.RoomID = r.RoomID
        AND c.Status = 'Hiệu lực'
    WHERE r.RoomID = ?
    GROUP BY 
        r.RoomID, r.RoomNumber, r.Capacity, r.RoomPrice,
        r.RoomType, r.Description, r.Status, r.ImagePath,
        b.BuildingName, b.Description
    LIMIT 1
";

$st = $conn->prepare($roomSql);
if (!$st) {
    die("Lỗi SQL (roomSql): " . $conn->error);
}
$st->bind_param("i", $roomID);
$st->execute();
$roomRes = $st->get_result();
$st->close();

if (!$roomRes || $roomRes->num_rows === 0) {
    echo "<div class='alert alert-danger text-center'>Không tìm thấy thông tin phòng.</div>";
    include __DIR__ . '/../../../includes/footer.php';
    exit;
}

$room = $roomRes->fetch_assoc();

/* ==============================
   LẤY DANH SÁCH SINH VIÊN ĐANG Ở PHÒNG NÀY
================================ */
$mates = [];
$matesSql = "
    SELECT s.FullName, s.StudentCode, f.FacultyName
    FROM Contracts c
    INNER JOIN Students s ON c.StudentID = s.StudentID
    LEFT JOIN Faculties f ON s.FacultyID = f.FacultyID
    WHERE c.RoomID = ?
      AND c.Status = 'Hiệu lực'
    ORDER BY s.FullName ASC
";
$st = $conn->prepare($matesSql);
if ($st) {
    $st->bind_param("i", $roomID);
    $st->execute();
    $matesRes = $st->get_result();
    while ($m = $matesRes->fetch_assoc()) {
        $mates[] = $m;
    }
    $st->close();
}

/* ==============================
   KIỂM TRA SINH VIÊN ĐÃ CÓ HỢP ĐỒNG HIỆU LỰC CHƯA (chỉ khi đã đăng nhập)
================================ */
$hasContract = false;
if ($isLoggedIn && $student) {
    $studentID = (int)$student['StudentID'];
    $contractSql = "
        SELECT ContractID
        FROM Contracts
        WHERE StudentID = ?
          AND Status = 'Hiệu lực'
        LIMIT 1
    ";
    $st = $conn->prepare($contractSql);
    if ($st) {
        $st->bind_param("i", $studentID);
        $st->execute();
        $cRes = $st->get_result();
        $hasContract = ($cRes && $cRes->num_rows > 0);
        $st->close();
    }
}

/* ==============================
   XỬ LÝ MỘT SỐ DỮ LIỆU HIỂN THỊ
================================ */
$roomImage = !empty($room['ImagePath'])
    ? $BASE_URL . '/' . htmlspecialchars($room['ImagePath'], ENT_QUOTES, 'UTF-8')
    : $BASE_URL . '/assets/img/room-default.jpg';

$available = (int)$room['AvailableSlots'];
$capacity  = (int)$room['Capacity'];
$current   = (int)$room['CurrentOccupants'];

$statusClass = 'badge-default';
if ($room['RoomStatus'] === 'Trống') {
    $statusClass = 'badge-success';
} elseif ($room['RoomStatus'] === 'Đang sửa chữa') {
    $statusClass = 'badge-warning';
} else {
    $statusClass = 'badge-danger';
}

/* ==============================
   KIỂM TRA HỒ SƠ SINH VIÊN ĐÃ ĐẦY ĐỦ CHƯA
   -> yêu cầu để ĐƯỢC ĐĂNG KÝ PHÒNG
================================ */
$profileCompleted = false;

if ($isLoggedIn && $student) {
    $profileCompleted = true; // mặc định là đủ, nếu thiếu field nào sẽ set lại = false

    $requiredFields = [
        'FullName',
        #'Gender',
        #'BirthDate',
        #'Phone',
        #'Address',
        'FacultyID',
        #'ClassName',
        #'CourseYear'
    ];

    foreach ($requiredFields as $field) {
        if (empty($student[$field])) {
            $profileCompleted = false;
            break;
        }
    }
}
// ==============================
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Chi tiết phòng <?= htmlspecialchars($room['RoomNumber']) ?> - Ký túc xá</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Font Awesome + CSS riêng -->
    <link rel="stylesheet" href="../../../assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/user/room/room_detail.css">
</head>

<body>
    <div class="room-detail-container">
        <!-- Header room -->
        <div class="room-detail-header">
            <div class="room-image-wrapper">
                <img src="<?= $roomImage ?>"
                    onerror="this.onerror=null;this.src='<?= $BASE_URL ?>/assets/img/room-default.jpg';"
                    alt="Phòng <?= htmlspecialchars($room['RoomNumber']) ?>">
            </div>
            <div class="room-main-info">
                <h1><i class="fas fa-door-open"></i> Phòng <?= htmlspecialchars($room['RoomNumber']) ?></h1>
                <p class="building-name">
                    <i class="fas fa-building"></i>
                    Tòa: <?= htmlspecialchars($room['BuildingName']) ?>
                </p>
                <p class="room-type">
                    <i class="fas fa-bed"></i>
                    Loại phòng: <?= htmlspecialchars($room['RoomType'] ?: 'Chưa cập nhật') ?>
                </p>
                <p class="room-price">
                    <i class="fas fa-money-bill-wave"></i>
                    Giá: <strong><?= number_format($room['RoomPrice'], 0, ',', '.') ?>₫ / tháng</strong>
                </p>
                <p class="room-capacity">
                    <i class="fas fa-users"></i>
                    Sức chứa: <?= $capacity ?> | Đang ở: <?= $current ?> | Còn trống:
                    <span class="<?= $available > 0 ? 'text-success' : 'text-danger' ?>">
                        <?= $available ?>
                    </span>
                </p>
                <p class="room-status">
                    <span class="badge <?= $statusClass ?>">
                        <?= htmlspecialchars($room['RoomStatus']) ?>
                    </span>
                </p>
                <div class="room-actions">
                    <a href="register_room.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Quay lại danh sách phòng
                    </a>

                    <?php if (!$isLoggedIn): ?>
                        <a href="/login.php" class="btn btn-primary">
                            <i class="fas fa-sign-in-alt"></i> Đăng nhập để đăng ký
                        </a>

                    <?php elseif (!$profileCompleted): ?>
                        <a href="/modules/user/students/student_form.php" class="btn btn-warning">
                            <i class="fas fa-user-edit"></i> Hoàn thiện hồ sơ để đăng ký phòng (click vào đây)
                        </a>

                    <?php elseif (!$hasContract && $available > 0 && $room['RoomStatus'] !== 'Đang sửa chữa'): ?>
                        <a href="../room_requests/request_create.php?room_id=<?= (int)$room['RoomID'] ?>" class="btn btn-primary">
                            <i class="fas fa-money-bill-wave"></i> Đăng ký phòng này
                        </a>

                    <?php else: ?>
                        <button class="btn btn-disabled" disabled>
                            <i class="fas fa-ban"></i> Không thể đăng ký
                        </button>
                    <?php endif; ?>
                </div>

            </div>
        </div>

        <!-- Thông tin mô tả -->
        <div class="room-detail-body">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-info-circle"></i>
                    <h2>Thông tin chi tiết phòng</h2>
                </div>
                <div class="card-body">
                    <p><?= nl2br(htmlspecialchars($room['RoomDesc'] ?: 'Chưa có mô tả chi tiết cho phòng này.', ENT_QUOTES, 'UTF-8')) ?></p>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <i class="fas fa-building"></i>
                    <h2>Thông tin tòa nhà</h2>
                </div>
                <div class="card-body">
                    <p><?= nl2br(htmlspecialchars($room['BuildingDesc'] ?: 'Chưa có mô tả tòa nhà.', ENT_QUOTES, 'UTF-8')) ?></p>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <i class="fas fa-users"></i>
                    <h2>Danh sách sinh viên đang ở phòng này</h2>
                </div>
                <div class="card-body">
                    <?php if (count($mates) > 0): ?>
                        <div class="roommates-list">
                            <?php foreach ($mates as $m): ?>
                                <div class="roommate-card">
                                    <div class="roommate-avatar">
                                        <?= strtoupper(substr($m['FullName'], 0, 1)) ?>
                                    </div>
                                    <div class="roommate-info">
                                        <h4><?= htmlspecialchars($m['FullName']) ?></h4>
                                        <p><strong>MSSV:</strong> <?= htmlspecialchars($m['StudentCode']) ?></p>
                                        <p><strong>Khoa:</strong> <?= htmlspecialchars($m['FacultyName'] ?? 'Chưa cập nhật') ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p>Hiện tại chưa có sinh viên nào đang ở phòng này.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Thông tin sinh viên hiện tại (người đang xem) -->
            <div class="card student-card">
                <div class="card-header">
                    <i class="fas fa-user-graduate"></i>
                    <h2>Sinh viên đăng nhập</h2>
                </div>
                <div class="card-body">
                    <?php if ($isLoggedIn && $student): ?>
                        <p><strong>Họ tên:</strong>
                            <?= htmlspecialchars($student['FullName'] ?? 'Chưa cập nhật') ?>
                        </p>
                        <p><strong>MSSV:</strong>
                            <?= htmlspecialchars($student['StudentCode'] ?? 'Chưa cập nhật') ?>
                        </p>
                        <p><strong>Khoa:</strong>
                            <?= htmlspecialchars($student['FacultyName'] ?? 'Chưa cập nhật') ?>
                        </p>
                    <?php elseif ($isLoggedIn && !$student): ?>
                        <p>Bạn đã đăng nhập nhưng chưa hoàn thiện hồ sơ sinh viên.</p>
                        <a href="/modules/user/students/student_form.php" class="btn btn-primary">
                            <i class="fas fa-user-edit"></i> Hoàn thiện hồ sơ
                        </a>
                    <?php else: ?>
                        <p>Bạn chưa đăng nhập. Vui lòng <a href="/login.php">đăng nhập</a> để xem thông tin sinh viên của bạn.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../../../includes/footer.php'; ?>
</body>

</html>