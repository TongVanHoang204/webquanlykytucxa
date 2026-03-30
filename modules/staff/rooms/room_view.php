<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../../db_connect.php';
require_once '../../../includes/admin_header.php';
require_once '../../../includes/auth_check.php';
requireRole(['Admin', 'Manager']);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo "Yêu cầu không hợp lệ.";
    exit;
}

$conn->set_charset('utf8mb4');

/* ===== Lấy thông tin phòng (prepared) ===== */
$sqlRoom = "
    SELECT r.*, b.BuildingName
    FROM Rooms r
    JOIN Buildings b ON r.BuildingID = b.BuildingID
    WHERE r.RoomID = ?
";
$stmt = $conn->prepare($sqlRoom);
if (!$stmt) {
    echo "Lỗi truy vấn phòng: " . htmlspecialchars($conn->error);
    exit;
}
$stmt->bind_param("i", $id);
$stmt->execute();
$room = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$room) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Không tìm thấy phòng',
            text: 'Phòng không tồn tại hoặc đã bị xóa!',
            confirmButtonText: 'Quay lại'
        }).then(() => window.location.href = 'rooms.php');
    </script>";
    exit;
}

/* ===== Đếm số SV đang ở (live) theo hợp đồng hiệu lực ===== */
$activeOccupants = 0;
$stmt = $conn->prepare("
    SELECT COUNT(*) 
    FROM Contracts 
    WHERE RoomID = ? AND Status = 'Hiệu lực'
");
if ($stmt) {
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($activeOccupants);
    $stmt->fetch();
    $stmt->close();
}

/* ===== Tính chỉ số hiển thị ===== */
$capacity      = (int)$room['Capacity'];
$occupantsLive = (int)$activeOccupants;
$vacancyLive   = max(0, $capacity - $occupantsLive);
$occupancyRate = $capacity > 0 ? ($occupantsLive / $capacity) * 100 : 0;
$canAddStudent = $room['Status'] !== 'Báº£o trÃ¬' && $occupantsLive < $capacity;

/* Trạng thái hiển thị (không ghi DB nếu Bảo trì) */
$displayStatus = ($room['Status'] === 'Bảo trì')
    ? 'Bảo trì'
    : ($occupantsLive >= $capacity ? 'Đầy' : 'Trống');

/* (TÙY CHỌN) Đồng bộ Rooms theo số liệu live (nếu không bảo trì) */
if ($room['Status'] !== 'Bảo trì') {
    $roomStatusForDb = ($occupantsLive >= $capacity) ? 'Đầy' : 'Trống';
    $upd = $conn->prepare("
        UPDATE Rooms 
        SET CurrentOccupants = ?, `Status` = ?
        WHERE RoomID = ?
    ");
    if ($upd) {
        $upd->bind_param("isi", $occupantsLive, $roomStatusForDb, $id);
        $upd->execute();
        $upd->close();

        $room['CurrentOccupants'] = $occupantsLive;
        $room['Status'] = $roomStatusForDb;
        $displayStatus = $roomStatusForDb;
    }
}

/* ===== Lấy danh sách sinh viên đang ở (JOIN Faculties) ===== */
$sqlStudents = "
    SELECT 
        s.StudentCode,
        s.FullName,
        s.Gender,
        s.Phone,
        f.FacultyName,
        c.StartDate,
        c.EndDate
    FROM Contracts c
    JOIN Students s ON c.StudentID = s.StudentID
    LEFT JOIN Faculties f ON f.FacultyID = s.FacultyID
    WHERE c.RoomID = ? 
      AND c.Status = 'Hiệu lực'
    ORDER BY s.FullName ASC
";
$stmt = $conn->prepare($sqlStudents);
if (!$stmt) {
    echo "Lỗi truy vấn sinh viên: " . htmlspecialchars($conn->error);
    exit;
}
$stmt->bind_param("i", $id);
$stmt->execute();
$students = $stmt->get_result();
$stmt->close();

/* ===== Gallery & Amenities ===== */
$gallery   = !empty($room['Gallery'])   ? json_decode($room['Gallery'], true)   : [];
$amenities = !empty($room['Amenities']) ? json_decode($room['Amenities'], true) : [];

function getInitials($name)
{
    $words = preg_split('/\s+/', trim($name));
    $ini = '';
    foreach ($words as $w) {
        if ($w !== '') $ini .= mb_strtoupper(mb_substr($w, 0, 1, 'UTF-8'), 'UTF-8');
    }
    return mb_substr($ini, 0, 2, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Chi tiết Phòng <?= htmlspecialchars($room['RoomNumber']) ?> - Hệ Thống Ký Túc Xá</title>
    <link rel="stylesheet" href="../../../assets/css/admin/admin_header.css">
    <link rel="stylesheet" href="../../../assets/css/staff/room/staff_rooms.css">
    <link rel="stylesheet" href="../../../assets/css/staff/room/staff_room_view.css">
    <link rel="stylesheet" href="../../../assets/vendor/fontawesome/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>
    <div class="room-detail-container">
        <!-- Header Section -->
        <div class="room-detail-header">
            <div class="room-header-content">
                <div>
                    <?php if (!empty($room['ImagePath'])): ?>
                        <img src="/<?= htmlspecialchars($room['ImagePath']) ?>"
                            alt="Ảnh phòng <?= htmlspecialchars($room['RoomNumber']) ?>"
                            class="room-main-img">
                    <?php else: ?>
                        <img src="/assets/img/no-image.png"
                            alt="Không có ảnh"
                            class="room-main-img">
                    <?php endif; ?>
                </div>

                <div class="room-info">
                    <h2><i class="fas fa-door-open"></i> Phòng <?= htmlspecialchars($room['RoomNumber']) ?></h2>
                    <p><strong><i class="fas fa-building"></i> Tòa nhà:</strong> <?= htmlspecialchars($room['BuildingName']) ?></p>

                    <p><strong><i class="fas fa-venus-mars"></i> Loại phòng:</strong>
                        <span class="status-badge <?= $room['RoomType'] === 'Nam' ? 'status-available' : 'status-occupied' ?>">
                            <i class="fas fa-<?= $room['RoomType'] === 'Nam' ? 'mars' : 'venus' ?>"></i>
                            <?= htmlspecialchars($room['RoomType']) ?>
                        </span>
                    </p>

                    <p><strong><i class="fas fa-tag"></i> Giá phòng:</strong>
                        <span style="color: var(--primary); font-weight: 700; font-size: 1.2rem;">
                            <?= number_format($room['RoomPrice'], 0, ',', '.') ?>₫
                        </span> / tháng
                    </p>

                    <p><strong><i class="fas fa-info-circle"></i> Trạng thái:</strong>
                        <span class="status-badge
                        <?= $displayStatus === 'Trống'
                            ? 'status-available'
                            : ($displayStatus === 'Đầy'
                                ? 'status-occupied'
                                : 'status-maintenance') ?>">
                            <i class="fas fa-<?= $displayStatus === 'Trống'
                                                    ? 'check-circle'
                                                    : ($displayStatus === 'Đầy'
                                                        ? 'times-circle'
                                                        : 'tools') ?>"></i>
                            <?= htmlspecialchars($displayStatus) ?>
                        </span>
                        <?php if ($displayStatus === 'Trống' && $occupantsLive > 0): ?>
                            <small style="margin-left:8px;color:var(--text-light);font-weight:500;">(Còn chỗ)</small>
                        <?php endif; ?>
                    </p>

                    <div class="progress-container">
                        <div class="progress-info">
                            <span>Tình trạng sử dụng </span>
                            <span><?= $occupantsLive ?>/<?= $capacity ?> (<?= round($occupancyRate) ?>%)</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?= min(100, $occupancyRate) ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Overview -->
        <div class="room-stats">
            <div class="stat-card">
                <i class="fas fa-users" style="color: var(--primary);"></i>
                <span class="number"><?= $capacity ?></span>
                <span class="label">Sức chứa tối đa</span>
            </div>
            <div class="stat-card">
                <i class="fas fa-user-check" style="color: var(--success);"></i>
                <span class="number"><?= $occupantsLive ?></span>
                <span class="label">Đang ở</span>
            </div>
            <div class="stat-card">
                <i class="fas fa-bed" style="color: #f59e0b;"></i>
                <span class="number"><?= $vacancyLive ?></span>
                <span class="label">Chỗ trống</span>
            </div>
            <div class="stat-card">
                <i class="fas fa-percentage" style="color: #6366f1;"></i>
                <span class="number"><?= round($occupancyRate) ?>%</span>
                <span class="label">Tỷ lệ lấp đầy</span>
            </div>
        </div>

        <!-- Tiện nghi phòng -->
        <?php if (!empty($amenities)): ?>
            <div class="room-description-section">
                <div class="section-title">
                    <i class="fas fa-star"></i>
                    <h3>Tiện Nghi Phòng</h3>
                </div>

                <div class="amenities-grid">
                    <?php
                    // Bảng tiện nghi tiếng Việt + icon Font Awesome Free
                    $amenityIcons = [
                        'wifi'      => ['icon' => 'wifi',       'label' => 'Wi-Fi'],
                        'aircon'    => ['icon' => 'snowflake',  'label' => 'Điều hòa'],
                        'ac'        => ['icon' => 'snowflake',  'label' => 'Điều hòa'],
                        'fridge'    => ['icon' => 'snowflake',  'label' => 'Tủ lạnh'],
                        'tv'        => ['icon' => 'tv',         'label' => 'Tivi'],
                        'tivi'      => ['icon' => 'tv',         'label' => 'Tivi'],
                        'bathroom'  => ['icon' => 'bath',       'label' => 'Phòng tắm riêng'],
                        'wc'        => ['icon' => 'bath',       'label' => 'Nhà vệ sinh'],
                        'toilet'    => ['icon' => 'bath',       'label' => 'Nhà vệ sinh'],
                        'balcony'   => ['icon' => 'door-open',  'label' => 'Ban công'],
                        'desk'      => ['icon' => 'chair',      'label' => 'Bàn ghế học tập'],
                        'bed'       => ['icon' => 'bed',        'label' => 'Giường ngủ'],
                        'heater'    => ['icon' => 'fire',       'label' => 'Máy nước nóng'],
                        'washing'   => ['icon' => 'soap',       'label' => 'Máy giặt'],
                        'windown'   => ['icon' => 'border-none', 'label' => 'Cửa sổ'],
                    ];

                    foreach ($amenities as $amenityRaw):
                        $key = strtolower(trim(is_array($amenityRaw) ? ($amenityRaw['key'] ?? '') : $amenityRaw));
                        if ($key === '') continue;

                        $data = $amenityIcons[$key] ?? ['icon' => 'check', 'label' => ucfirst($key)];
                        $icon  = $data['icon'];
                        $label = $data['label'];
                    ?>
                        <div class="amenity-item">
                            <i class="fas fa-<?= htmlspecialchars($icon) ?>"></i>
                            <span><?= htmlspecialchars($label) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>



        <!-- Gallery -->
        <?php if (!empty($gallery)): ?>
            <div class="room-gallery-section">
                <div class="section-title">
                    <i class="fas fa-images"></i>
                    <h3>Thư viện ảnh</h3>
                </div>
                <div class="room-gallery">
                    <?php foreach ($gallery as $img): ?>
                        <img src="../../<?= htmlspecialchars($img) ?>"
                            alt="Ảnh phòng <?= htmlspecialchars($room['RoomNumber']) ?>"
                            onclick="openImageModal('../../<?= htmlspecialchars($img) ?>')">
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Mô tả -->
        <?php if (!empty($room['Description'])): ?>
            <div class="room-description-section">
                <div class="section-title">
                    <i class="fas fa-file-alt"></i>
                    <h3>Mô tả phòng</h3>
                </div>
                <div class="room-description">
                    <?= nl2br(htmlspecialchars($room['Description'])) ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Danh sách sinh viên -->
        <div class="students-section">
            <div class="section-title" style="padding: 25px 25px 0;">
                <i class="fas fa-user-graduate"></i>
                <h3>Danh sách sinh viên đang ở</h3>
            </div>
            <table class="students-table">
                <thead>
                    <tr>
                        <th>Sinh viên</th>
                        <th>Mã SV</th>
                        <th>Khoa</th>
                        <th>SĐT</th>
                        <th>Ngày bắt đầu</th>
                        <th>Ngày kết thúc</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($students && $students->num_rows > 0): ?>
                        <?php while ($s = $students->fetch_assoc()):
                            $avatarClass = ($s['Gender'] === 'Nam') ? 'male' : 'female';
                            $initials = getInitials($s['FullName']);
                        ?>
                            <tr>
                                <td data-label="Sinh viên">
                                    <div class="student-info">
                                        <div class="student-avatar <?= $avatarClass ?>">
                                            <?= $initials ?>
                                        </div>
                                        <div>
                                            <div style="font-weight: 600;"><?= htmlspecialchars($s['FullName']) ?></div>
                                            <div style="font-size: 0.8rem; color: var(--text-light);">
                                                <?= htmlspecialchars($s['Gender']) ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td data-label="Mã SV"><?= htmlspecialchars($s['StudentCode']) ?></td>
                                <td data-label="Khoa"><?= htmlspecialchars($s['FacultyName'] ?: '—') ?></td>
                                <td data-label="SĐT"><?= htmlspecialchars($s['Phone'] ?: '—') ?></td>
                                <td data-label="Bắt đầu"><?= date('d/m/Y', strtotime($s['StartDate'])) ?></td>
                                <td data-label="Kết thúc"><?= date('d/m/Y', strtotime($s['EndDate'])) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="no-data">
                                <i class="fas fa-users-slash"></i>
                                <div>Phòng hiện chưa có sinh viên nào</div>
                                <small>Hãy thêm sinh viên vào phòng này</small>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Nút hành động -->
        <div class="action-buttons">
            <a href="room_add_student.php?room=<?= $id ?>" class="btn btn-add">
                <i class="fas fa-user-plus"></i> ThÃªm sinh viÃªn
            </a>
            <a href="room_edit.php?id=<?= $id ?>" class="btn btn-add">
                <i class="fas fa-edit"></i> Chỉnh sửa phòng
            </a>
            <a href="rooms.php" class="btn btn-back">
                <i class="fas fa-arrow-left"></i> Quay lại</a>
        </div>

        <!-- Modal xem ảnh -->
        <div id="imageModal" class="modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.9);z-index:1000;justify-content:center;align-items:center;">
            <span class="close" onclick="closeImageModal()" style="position:absolute;top:20px;right:30px;color:#fff;font-size:40px;cursor:pointer;">&times;</span>
            <img id="modalImage" src="" alt="" style="max-width:90%;max-height:90%;object-fit:contain;">
        </div>

        <?php if (isset($_SESSION['message'])): ?>
            <script>
                Swal.fire({
                    icon: '<?= $_SESSION['message_type'] ?? "success" ?>',
                    title: 'Thông báo',
                    html: '<?= $_SESSION['message'] ?>',
                    confirmButtonText: 'Đóng',
                    confirmButtonColor: '#3085d6'
                });
            </script>
        <?php unset($_SESSION['message'], $_SESSION['message_type']);
        endif; ?>

        <script>
            function openImageModal(src) {
                document.getElementById('modalImage').src = src;
                document.getElementById('imageModal').style.display = 'flex';
            }

            function closeImageModal() {
                document.getElementById('imageModal').style.display = 'none';
            }
            document.getElementById('imageModal').addEventListener('click', e => {
                if (e.target === e.currentTarget) closeImageModal();
            });

            <?php if (!$canAddStudent): ?>
            const addStudentButton = document.querySelector('a[href="room_add_student.php?room=<?= $id ?>"]');
            if (addStudentButton) {
                addStudentButton.style.display = 'none';
            }
            <?php endif; ?>

            const addStudentButtonByState = document.querySelector('a[href="room_add_student.php?room=<?= $id ?>"]');
            const statusBadge = document.querySelector('.room-info .status-badge');
            const usageText = document.querySelector('.progress-info span:last-child');
            if (addStudentButtonByState) {
                addStudentButtonByState.innerHTML = '<i class="fas fa-user-plus"></i> Thêm sinh viên';
                const statusText = (statusBadge?.textContent || '').toLowerCase();
                const usageMatch = (usageText?.textContent || '').match(/(\d+)\s*\/\s*(\d+)/);
                const isMaintenance = /b.*tr/i.test(statusText);
                const isFull = usageMatch ? Number(usageMatch[1]) >= Number(usageMatch[2]) : false;
                if (isMaintenance || isFull) {
                    addStudentButtonByState.style.display = 'none';
                }
            }
        </script>
</body>

</html>
