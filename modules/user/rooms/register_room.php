<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../../db_connect.php';

/* ============================
   1) KIỂM TRA ĐĂNG NHẬP
=============================== */
$userID = (int)($_SESSION['UserID'] ?? 0);
$isLoggedIn = isset($_SESSION['UserID']) && (int)$_SESSION['UserID'] > 0;

if ($userID <= 0) {
    // Khách vãng lai: hiện SweetAlert rồi chuyển sang login
    echo '<!DOCTYPE html><html lang="vi"><head>
        <meta charset="UTF-8">
        <title>Yêu cầu đăng nhập</title>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    </head><body>
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            Swal.fire({
                icon: "info",
                title: "Bạn chưa đăng nhập",
                text: "Vui lòng đăng nhập để đăng ký phòng ký túc xá.",
                confirmButtonText: "Đăng nhập ngay",
                confirmButtonColor: "#3085d6"
            }).then(() => window.location.href = "/login.php");
        });
    </script>
    </body></html>';
    exit;
}

require_once '../../../includes/auth_check.php';
requireLogin();

// Biến chứa JS SweetAlert sẽ chạy sau khi load trang
$swalScript = "";

/* ============================
   2) LẤY THÔNG TIN SINH VIÊN
=============================== */
$studentQuery = $conn->query("
    SELECT 
        s.StudentID,
        s.FullName,
        s.StudentCode,
        s.Gender,
        s.BirthDate,
        s.Address,
        s.FacultyID,
        s.ClassName,
        s.CourseYear,
        f.FacultyName
    FROM Students s
    LEFT JOIN Faculties f ON s.FacultyID = f.FacultyID
    WHERE s.UserID = $userID
");


if (!$studentQuery || $studentQuery->num_rows == 0) {
    include '../../../includes/header.php';
    echo "<div class='alert alert-danger text-center m-4'>Không tìm thấy thông tin sinh viên!</div>";
    include '../../../includes/footer.php';
    exit;
}

$student   = $studentQuery->fetch_assoc();
$studentID = (int)$student['StudentID'];

/* ============================
   3) KIỂM TRA ĐÃ CÓ PHÒNG?
=============================== */
$checkContract = $conn->query("
    SELECT c.*, r.RoomNumber, b.BuildingName 
    FROM Contracts c
    INNER JOIN Rooms r ON c.RoomID = r.RoomID
    INNER JOIN Buildings b ON r.BuildingID = b.BuildingID
    WHERE c.StudentID = $studentID AND c.Status = 'Hiệu lực'
");

$hasContract     = $checkContract && $checkContract->num_rows > 0;
$currentContract = $hasContract ? $checkContract->fetch_assoc() : null;

/* ============================
   4) XỬ LÝ ĐĂNG KÝ PHÒNG (POST)
=============================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['room_id'])) {
    if ($hasContract) {
        // Đã có phòng rồi
        $swalScript = "
            Swal.fire({
                icon: 'warning',
                title: 'Đã có phòng',
                text: 'Bạn đã có phòng hiện tại, không thể đăng ký thêm!',
                confirmButtonColor: '#f39c12'
            });
        ";
    } else {
        $roomID = (int)$_POST['room_id'];

        // TODO: kiểm tra phòng còn chỗ, tạo yêu cầu, thanh toán,... (sau này)
        // Tạm thời chỉ thông báo thành công
        $swalScript = "
            Swal.fire({
                icon: 'success',
                title: 'Gửi yêu cầu thành công!',
                text: 'Yêu cầu đăng ký phòng của bạn đã được gửi đến ban quản lý.',
                confirmButtonColor: '#27ae60'
            });
        ";
    }
}

/* ============================
   5) LẤY DANH SÁCH TÒA NHÀ (CHO FILTER)
=============================== */
$buildings = $conn->query("SELECT BuildingID, BuildingName FROM Buildings ORDER BY BuildingName ASC");

/* NHẬN GIÁ TRỊ FILTER TỪ GET */
$searchBuilding = $_GET['building']  ?? '';
$searchRoomType = $_GET['room_type'] ?? '';
$searchMaxPrice = isset($_GET['max_price']) ? (int)$_GET['max_price'] : 0;

/* ============================
   6) QUERY LỌC PHÒNG TRỐNG
=============================== */
$roomsSql = "
    SELECT 
        r.RoomID,
        r.RoomNumber,
        r.Capacity,
        r.RoomPrice,
        r.RoomType,
        r.Description,
        r.ImagePath,
        r.Status AS RoomStatus,   -- trạng thái phòng
        b.BuildingName,

        -- Số người đang ở thực tế theo hợp đồng hiệu lực
        COUNT(CASE WHEN c.Status = 'Hiệu lực' THEN 1 END) AS CurrentOccupants,

        -- Số chỗ trống thực tế
        (r.Capacity - COUNT(CASE WHEN c.Status = 'Hiệu lực' THEN 1 END)) AS AvailableSlots

    FROM Rooms r
    INNER JOIN Buildings b ON r.BuildingID = b.BuildingID
    LEFT JOIN Contracts c 
        ON c.RoomID = r.RoomID
        AND c.Status = 'Hiệu lực'
    WHERE 1=1
";

if ($searchBuilding !== "") {
    $roomsSql .= " AND r.BuildingID = " . (int)$searchBuilding;
}

if ($searchRoomType !== "") {
    $roomsSql .= " AND r.RoomType = '" . $conn->real_escape_string($searchRoomType) . "'";
}

if ($searchMaxPrice > 0) {
    $roomsSql .= " AND r.RoomPrice <= $searchMaxPrice";
}

$roomsSql .= "
    GROUP BY 
        r.RoomID, r.RoomNumber, r.Capacity, r.RoomPrice,
        r.RoomType, r.Description, r.ImagePath, r.Status, b.BuildingName
    HAVING AvailableSlots > 0
    ORDER BY b.BuildingName, r.RoomNumber
";

$rooms = $conn->query($roomsSql);
if (!$rooms) {
    die("Lỗi SQL rooms: " . $conn->error);
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
        #'Address',
        'FacultyID',
        'ClassName',
        #'CourseYear'
    ];

    foreach ($requiredFields as $field) {
        if (empty($student[$field])) {
            $profileCompleted = false;
            break;
        }
    }
}


/* ============================
   7) HEADER CHUNG
=============================== */
include '../../../includes/header.php';
?>

<link rel="stylesheet" href="<?= $base ?>assets/css/register_room.css">

<div class="registration-container">

    <!-- HEADER TRANG -->
    <div class="registration-header">
        <h1><i class="fas fa-bed"></i> Đăng ký Phòng Ký Túc Xá</h1>
        <p>Chọn phòng phù hợp với nhu cầu của bạn</p>
    </div>

    

    <!-- =========================================
         THÔNG TIN SINH VIÊN
    ========================================== -->
    <div class="student-info-card">
        <div class="student-avatar"><i class="fas fa-user-graduate"></i></div>
        <div class="student-details">
            <h3><?= htmlspecialchars($student['FullName']) ?></h3>
            <p><i class="fas fa-id-card"></i> MSSV: <?= htmlspecialchars($student['StudentCode']) ?></p>
            <p><i class="fas fa-graduation-cap"></i> Khoa: <?= htmlspecialchars($student['FacultyName']) ?></p>

            <?php if ($hasContract): ?>
                <p class="text-success">
                    <i class="fas fa-check-circle"></i>
                    Đã có phòng: <?= htmlspecialchars($currentContract['BuildingName']) ?>
                    - Phòng <?= htmlspecialchars($currentContract['RoomNumber']) ?>
                </p>
            <?php else: ?>
                <p class="text-warning">
                    <i class="fas fa-clock"></i> Bạn chưa có phòng, hãy đăng ký ngay!
                </p>
            <?php endif; ?>
        </div>
    </div>

    <!-- =========================================
         BỘ LỌC PHÒNG
    ========================================== -->
    <div class="search-form-container">
        <form id="roomFilterForm" method="GET" action="" class="room-search-form">
            <div class="search-filters">

                <!-- Tòa nhà -->
                <div class="filter-item">
                    <label for="building">
                        <i class="fas fa-building"></i> Tòa nhà
                    </label>
                    <select name="building" id="building">
                        <option value=""> Tất cả </option>
                        <?php if ($buildings): ?>
                            <?php while ($b = $buildings->fetch_assoc()): ?>
                                <option value="<?= $b['BuildingID'] ?>"
                                    <?= ($searchBuilding == $b['BuildingID']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($b['BuildingName']) ?>
                                </option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <!-- Loại phòng -->
                <div class="filter-item">
                    <label for="room_type">
                        <i class="fas fa-venus-mars"></i> Loại phòng
                    </label>
                    <select name="room_type" id="room_type">
                        <option value=""> Tất cả </option>
                        <option value="Nam" <?= $searchRoomType == 'Nam' ? 'selected' : '' ?>>Nam</option>
                        <option value="Nữ" <?= $searchRoomType == 'Nữ' ? 'selected' : '' ?>>Nữ</option>
                        <option value="Hỗn hợp" <?= $searchRoomType == 'Hỗn hợp' ? 'selected' : '' ?>>Hỗn hợp</option>
                    </select>
                </div>

                <!-- Giá tối đa -->
                <div class="filter-item">
                    <label>
                        <i class="fas fa-money-bill-wave"></i> Giá tối đa:
                        <span id="max_price_text">
                            <?= $searchMaxPrice ? number_format($searchMaxPrice, 0, ',', '.') . ' đ' : 'Không giới hạn' ?>
                        </span>
                    </label>

                    <input type="range"
                        id="max_price_range"
                        name="max_price"
                        min="0" max="3000000" step="50000"
                        value="<?= $searchMaxPrice ?: 0 ?>">
                </div>

                <!-- Nút reset -->
                <a href="register_room.php" class="btn btn-secondary">
                    <i class="fas fa-redo"></i> Đặt lại
                </a>
            </div>
        </form>
    </div>

    <!-- =========================================
         DANH SÁCH PHÒNG
    ========================================== -->
    <?php if (!$hasContract): ?>
        <?php if ($rooms && $rooms->num_rows > 0): ?>

            <div class="section-header">
                <h2><i class="fas fa-door-open"></i> Danh sách phòng trống</h2>
            </div>

            <div class="room-grid">
                <?php while ($room = $rooms->fetch_assoc()): ?>
                    <?php
                    $img       = !empty($room['ImagePath']) ? '/' . $room['ImagePath'] : '/assets/img/room-default.jpg';
                    $available = (int)$room['AvailableSlots'];
                    $status    = $room['RoomStatus'] ?? '';
                    ?>
                    <div class="room-card v2">
                        <!-- Ảnh + badge -->
                        <div class="room-card-image">
                            <span class="deposit-badge">
                                <i class="fas fa-money-bill-wave"></i>
                                Đặt cọc 500K
                            </span>
                            <img src="<?= $img ?>"
                                alt="Phòng <?= htmlspecialchars($room['RoomNumber']) ?>"
                                onerror="this.src='/assets/img/room-default.jpg'">
                        </div>

                        <!-- Thông tin chính -->
                        <div class="room-card-body">
                            <h3 class="room-title">Phòng <?= htmlspecialchars($room['RoomNumber']) ?></h3>

                            <div class="room-meta">
                                <div class="meta-item">
                                    <i class="fas fa-building"></i>
                                    <span><?= htmlspecialchars($room['BuildingName']) ?></span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-users"></i>
                                    <span><?= $available ?>/<?= (int)$room['Capacity'] ?> chỗ trống</span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-money-bill"></i>
                                    <span><?= number_format($room['RoomPrice'], 0, ',', '.') ?> đ/tháng</span>
                                </div>
                                <?php if (!empty($room['Description'])): ?>
                                    <div class="meta-item meta-desc">
                                        <i class="fas fa-info-circle"></i>
                                        <span><?= htmlspecialchars($room['Description']) ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- CTA -->
                        <div class="room-card-footer">
                            <?php if (!$isLoggedIn): ?>
                                <!-- Chưa đăng nhập -->
                                <a href="/login.php" class="btn-room btn-room-primary">
                                    <i class="fas fa-sign-in-alt"></i>
                                    <span>Đăng nhập để đăng ký</span>
                                </a>

                            <?php elseif (!$profileCompleted): ?>
                                <!-- Hồ sơ chưa đầy đủ -->
                                <a href="/modules/user/students/student_form.php" class="btn-room btn-room-outline">
                                    <i class="fas fa-user-edit"></i>
                                    <span>Hoàn thiện hồ sơ để đăng ký</span>
                                </a>

                            <?php elseif ($hasContract): ?>
                                <!-- Đã có hợp đồng -->
                                <button class="btn-room btn-room-disabled" disabled>
                                    <i class="fas fa-check-circle"></i>
                                    <span>Bạn đã có phòng</span>
                                </button>

                            <?php elseif ($available > 0 && $status !== 'Đang sửa chữa'): ?>
                                <!-- Được phép đăng ký -->
                                <a href="../room_requests/request_create.php?room_id=<?= (int)$room['RoomID'] ?>"
                                    class="btn-room btn-room-primary">
                                    <i class="fas fa-money-bill-wave"></i>
                                    <span>Đăng ký phòng này</span>
                                </a>

                            <?php else: ?>
                                <!-- Không thể đăng ký -->
                                <button class="btn-room btn-room-disabled" disabled>
                                    <i class="fas fa-ban"></i>
                                    <span>Không thể đăng ký</span>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-bed fa-4x"></i>
                <h3>Không có phòng trống phù hợp</h3>
                <p>Hãy thử điều chỉnh bộ lọc hoặc quay lại sau.</p>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-check-circle fa-4x text-success"></i>
            <h3>Bạn đã có phòng, không thể đăng ký thêm</h3>
        </div>
    <?php endif; ?>

</div> <!-- /.registration-container -->

<!-- ============================
     SCRIPT
=============================== -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // Tự động submit bộ lọc khi thay đổi
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
                priceText.innerText = v === 0 ?
                    "Không giới hạn" :
                    v.toLocaleString('vi-VN') + " đ";
            });

            slider.addEventListener('change', () => form.submit());
        }

        // Thông báo SweetAlert sau khi xử lý POST
        <?php if (!empty($swalScript)): ?>
            <?= $swalScript ?>

        <?php endif; ?>
    });
</script>

<?php
include '../../../includes/footer.php';
