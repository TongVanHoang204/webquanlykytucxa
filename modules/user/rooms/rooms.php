<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include '../../../db_connect.php';
include '../../../includes/header.php';
require_once __DIR__ . '/../../../includes/auth_check.php';
requireRole(['Student', 'Admin', 'Manager']);

$userID = (int)$_SESSION['UserID'];

/* Lấy thông tin sinh viên theo UserID */
$studentSql = "
    SELECT s.StudentID, s.FullName, s.StudentCode, f.FacultyName
    FROM Students s
    LEFT JOIN Faculties f ON s.FacultyID = f.FacultyID and f.facultyName
    WHERE s.UserID = ?
";
$st = $conn->prepare($studentSql);
if (!$st) {
    die("Lỗi SQL (studentSql): " . $conn->error);
}
$st->bind_param("i", $userID);
$st->execute();
$studentRes = $st->get_result();
$st->close();

if (!$studentRes || $studentRes->num_rows === 0) {
    echo "<div class='alert alert-danger text-center'>Không tìm thấy thông tin sinh viên!</div>";
    include '../../../includes/footer.php';
    exit;
}
$student   = $studentRes->fetch_assoc();
$studentID = (int)$student['StudentID'];
// $facultyName = $student['FacultyName'] ?? 'Chưa cập nhật';


/* Lấy hợp đồng hiện tại (Hiệu lực/Chờ duyệt) mới nhất */
/* ✅ SỬA Ở ĐÂY: dùng subquery đếm số người đang ở (Occupants) */
$contractSql = "
    SELECT 
        c.*,
        r.RoomNumber, 
        r.RoomType, 
        r.Capacity, 
        r.RoomPrice, 
        r.Status AS RoomStatus,
        b.BuildingName, 
        b.Description, 
        b.Floors,
        (
            SELECT COUNT(*) 
            FROM Contracts c2 
            WHERE c2.RoomID = c.RoomID 
              AND c2.Status = 'Hiệu lực'
        ) AS Occupants
    FROM Contracts c
    INNER JOIN Rooms r     ON c.RoomID = r.RoomID
    INNER JOIN Buildings b ON r.BuildingID = b.BuildingID
    WHERE c.StudentID = ?
      AND c.Status IN ('Hiệu lực', 'Chờ duyệt')
    ORDER BY c.ContractID DESC
    LIMIT 1
";

$st = $conn->prepare($contractSql);
if (!$st) {
    die("Lỗi SQL (contractSql): " . $conn->error);
}

$st->bind_param("i", $studentID);
$st->execute();
$contractRes = $st->get_result();
$st->close();

if (!$contractRes || $contractRes->num_rows === 0) {
    echo "<div class='empty-state'>
            <i class='fas fa-bed fa-4x'></i>
            <h3>Bạn chưa có hợp đồng phòng ở</h3>
            <p>Vui lòng đăng ký phòng tại mục <b>Đăng ký phòng</b>.</p>
            <a href='../rooms/register_room.php' class='btn btn-success'>
                <i class='fas fa-door-open'></i> Đăng ký ngay
            </a>
          </div>";
    include '../../../includes/footer.php';
    exit;
}

$contract = $contractRes->fetch_assoc();

/* ✅ GÁN LẠI CurrentOccupants + AvailableSlots cho dễ dùng trong view */
$contract['CurrentOccupants'] = isset($contract['Occupants']) ? (int)$contract['Occupants'] : 0;
$contract['Capacity']         = isset($contract['Capacity']) ? (int)$contract['Capacity'] : 0;
$contract['AvailableSlots']   = max(0, $contract['Capacity'] - $contract['CurrentOccupants']);

$roomID = (int)$contract['RoomID'];

/* Lấy danh sách bạn cùng phòng (nếu có roomID) */
$roommates = [];
if ($roomID > 0) {
    $matesSql = "
        SELECT s.FullName, s.StudentCode, s.FacultyID
        FROM Contracts c
        INNER JOIN Students s ON c.StudentID = s.StudentID
        WHERE c.RoomID = ?
          AND c.Status = 'Hiệu lực'
          AND c.StudentID <> ?
        ORDER BY s.FullName ASC
    ";
    $st = $conn->prepare($matesSql);
    if ($st) {
        $st->bind_param("ii", $roomID, $studentID);
        $st->execute();
        $matesRes = $st->get_result();
        while ($row = $matesRes->fetch_assoc()) {
            $roommates[] = $row;
        }
        $st->close();
    }
}
?>
<link rel="stylesheet" href="<?= $base ?>assets/css/user/room/room.css">

    <div class="room-wrapper">
        <!-- Header trang -->
        <div class="page-header">
            <div class="page-title">
                <h1><i class="fas fa-home"></i> Phòng Ở Của Tôi</h1>
                <p>Thông tin chi tiết về hợp đồng, bạn cùng phòng và trạng thái phòng hiện tại.</p>
            </div>
            <div class="page-meta">
                <span class="tag subtle">
                    <i class="fas fa-user-graduate"></i> Sinh viên nội trú
                </span>
            </div>
        </div>

        <!-- Thông tin sinh viên + phòng gọn trên -->
        <div class="grid-2">
            <!-- Thông tin sinh viên -->
            <div class="card student-card">
                <div class="card-header">
                    <div class="student-avatar">
                        <?= strtoupper(substr($student['FullName'], 0, 1)) ?>
                    </div>
                    <div>
                        <h2><?= htmlspecialchars($student['FullName']) ?></h2>
                        <p class="muted">Mã SV: <b><?= htmlspecialchars($student['StudentCode']) ?></b></p>
                        <p class="muted">
                            Khoa: <b><?= htmlspecialchars($student['FacultyName'] ?? 'Chưa cập nhật') ?></b>
                        </p>
                    </div>
                </div>
                <div class="card-body student-meta">
                    <div class="meta-item">
                        <span class="meta-label">Tòa nhà</span>
                        <span class="meta-value">
                            <i class="fas fa-building"></i>
                            <?= htmlspecialchars($contract['BuildingName']) ?>
                        </span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Phòng</span>
                        <span class="meta-value">
                            <i class="fas fa-door-open"></i>
                            <?= htmlspecialchars($contract['RoomNumber']) ?> (<?= htmlspecialchars($contract['RoomType']) ?>)
                        </span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Trạng thái HĐ</span>
                        <span class="meta-value">
                            <span class="status-badge <?=
                                                        $contract['Status'] == 'Hiệu lực' ? 'status-active' : ($contract['Status'] == 'Chờ duyệt' ? 'status-pending' : 'status-inactive') ?>">
                                <?= htmlspecialchars($contract['Status']) ?>
                            </span>
                        </span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Trạng thái phòng</span>
                        <span class="meta-value">
                            <span class="status-badge <?=
                                                        $contract['RoomStatus'] == 'Trống' ? 'status-active' : ($contract['RoomStatus'] == 'Đang sửa chữa' ? 'status-pending' : 'status-inactive') ?>">
                                <?= htmlspecialchars($contract['RoomStatus']) ?>
                            </span>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Tóm tắt tài chính / thời gian -->
            <div class="card highlight-card">
                <div class="card-header">
                    <h2><i class="fas fa-chart-line"></i> Tóm tắt hợp đồng</h2>
                </div>
                <div class="card-body highlight-content">
                    <div class="highlight-item">
                        <span class="label">Tiền phòng / tháng</span>
                        <span class="value price">
                            <?= number_format($contract['RoomPrice'], 0, ',', '.') ?>₫
                        </span>
                    </div>
                    <div class="highlight-item">
                        <span class="label">Tiền cọc</span>
                        <span class="value price">
                            <?= number_format($contract['Deposit'], 0, ',', '.') ?>₫
                        </span>
                    </div>

                    <div class="dates-row">
                        <div class="date-item">
                            <span class="label"><i class="fas fa-calendar-day"></i> Ngày bắt đầu</span>
                            <span class="value">
                                <?= date('d/m/Y', strtotime($contract['StartDate'])) ?>
                            </span>
                        </div>
                        <div class="date-item">
                            <span class="label"><i class="fas fa-calendar-times"></i> Ngày kết thúc</span>
                            <span class="value">
                                <?= date('d/m/Y', strtotime($contract['EndDate'])) ?>
                            </span>
                        </div>
                    </div>

                    <!-- Thanh tiến độ thời gian hợp đồng (demo đơn giản) -->
                    <?php
                    $today = strtotime(date('Y-m-d'));
                    $start = strtotime($contract['StartDate']);
                    $end   = strtotime($contract['EndDate']);
                    $progress = 0;
                    if ($end > $start) {
                        $progress = max(0, min(100, (($today - $start) / ($end - $start)) * 100));
                    }
                    ?>
                    <div class="contract-progress">
                        <div class="progress-header">
                            <span>Tiến độ thời gian hợp đồng</span>
                            <span><?= round($progress) ?>%</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?= round($progress) ?>%;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Thông tin chi tiết phòng / hợp đồng -->
        <div class="card mt-lg">
            <div class="card-header">
                <i class="fas fa-file-contract"></i>
                <h2>Chi tiết hợp đồng & phòng ở</h2>
            </div>
            <div class="card-body contract-details-grid">
                <div class="detail-item">
                    <label><i class="fas fa-building"></i> Tòa nhà</label>
                    <p><?= htmlspecialchars($contract['BuildingName']) ?></p>
                </div>
                <div class="detail-item">
                    <label><i class="fas fa-door-open"></i> Phòng</label>
                    <p><?= htmlspecialchars($contract['RoomNumber']) ?> (<?= htmlspecialchars($contract['RoomType']) ?>)</p>
                </div>
                <div class="detail-item">
                    <label><i class="fas fa-users"></i> Số người tối đa</label>
                    <p><?= (int)$contract['Capacity'] ?> người</p>
                </div>
                <div class="detail-item">
                    <label><i class="fas fa-user-friends"></i> Đang ở</label>
                    <p><?= (int)$contract['CurrentOccupants'] ?> người</p>
                </div>
                <div class="detail-item">
                    <label><i class="fas fa-door-open"></i> Còn trống</label>
                    <p><?= (int)$contract['AvailableSlots'] ?> chỗ</p>
                </div>
                <div class="detail-item">
                    <label><i class="fas fa-money-bill-wave"></i> Tiền phòng</label>
                    <p class="price"><?= number_format($contract['RoomPrice'], 0, ',', '.') ?>₫ / tháng</p>
                </div>
                <div class="detail-item">
                    <label><i class="fas fa-hand-holding-usd"></i> Tiền cọc</label>
                    <p class="price"><?= number_format($contract['Deposit'], 0, ',', '.') ?>₫</p>
                </div>
                <div class="detail-item">
                    <label><i class="fas fa-calendar-day"></i> Ngày bắt đầu</label>
                    <p><?= date('d/m/Y', strtotime($contract['StartDate'])) ?></p>
                </div>
                <div class="detail-item">
                    <label><i class="fas fa-calendar-times"></i> Ngày kết thúc</label>
                    <p><?= date('d/m/Y', strtotime($contract['EndDate'])) ?></p>
                </div>
                <div class="detail-item">
                    <label><i class="fas fa-file-signature"></i> Trạng thái hợp đồng</label>
                    <p>
                        <span class="status-badge <?=
                                                    $contract['Status'] == 'Hiệu lực' ? 'status-active' : ($contract['Status'] == 'Chờ duyệt' ? 'status-pending' : 'status-inactive') ?>">
                            <?= htmlspecialchars($contract['Status']) ?>
                        </span>
                    </p>
                </div>
                <div class="detail-item">
                    <label><i class="fas fa-bed"></i> Trạng thái phòng</label>
                    <p>
                        <span class="status-badge <?=
                                                    $contract['RoomStatus'] == 'Trống' ? 'status-active' : ($contract['RoomStatus'] == 'Đang sửa chữa' ? 'status-pending' : 'status-inactive') ?>">
                            <?= htmlspecialchars($contract['RoomStatus']) ?>
                        </span>
                    </p>
                </div>
            </div>
        </div>

        <!-- Bạn cùng phòng -->
        <div class="card mt-lg">
            <div class="card-header">
                <i class="fas fa-users"></i>
                <h2>Bạn cùng phòng</h2>
            </div>
            <div class="card-body">
                <?php if (count($roommates) > 0): ?>
                    <div class="roommates-list">
                        <?php foreach ($roommates as $mate): ?>
                            <div class="roommate-card">
                                <div class="roommate-avatar">
                                    <?= strtoupper(substr($mate['FullName'], 0, 1)) ?>
                                </div>
                                <div class="roommate-info">
                                    <h4><?= htmlspecialchars($mate['FullName']) ?></h4>
                                    <p class="muted">Mã SV: <?= htmlspecialchars($mate['StudentCode']) ?></p>
                                    <p class="muted">
                                        Khoa: <?= htmlspecialchars($mate['FacultyID'] ?? '') ?>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no-roommates">
                        <i class="fas fa-user-friends"></i>
                        <h3>Chưa có bạn cùng phòng</h3>
                        <p>Hiện tại bạn đang ở một mình trong phòng này.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Nút hành động -->
        <div class="actions mt-lg">
            <a href="../dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Quay lại Dashboard
            </a>
            <a href="#" class="btn btn-primary">
                <i class="fas fa-file-download"></i> Tải hợp đồng
            </a>
            <a href="#" class="btn btn-ghost">
                <i class="fas fa-question-circle"></i> Hỗ trợ
            </a>
        </div>
    </div>

    <?php include '../../../includes/footer.php'; ?>
</body>
</html>