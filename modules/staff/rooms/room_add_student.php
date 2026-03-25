<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../../db_connect.php';
require_once '../../../includes/auth_check.php';
require_once '../../../includes/log_helper.php';

requireRole(['Admin', 'Manager']);

if (empty($_SESSION['_csrf'])) {
    $_SESSION['_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['_csrf'];

$roomId = (int)($_GET['room'] ?? $_POST['room_id'] ?? 0);
if ($roomId <= 0) {
    http_response_code(400);
    exit('Yêu cầu không hợp lệ.');
}

$roomStmt = $conn->prepare(
    "SELECT r.RoomID, r.RoomNumber, r.Capacity, r.CurrentOccupants, r.Status, b.BuildingName
     FROM Rooms r
     JOIN Buildings b ON b.BuildingID = r.BuildingID
     WHERE r.RoomID = ?
     LIMIT 1"
);
$roomStmt->bind_param('i', $roomId);
$roomStmt->execute();
$room = $roomStmt->get_result()->fetch_assoc();
$roomStmt->close();

if (!$room) {
    exit('Không tìm thấy phòng.');
}

$students = $conn->query(
    "SELECT s.StudentID, s.StudentCode, s.FullName, s.Gender, f.FacultyName
     FROM Students s
     LEFT JOIN Faculties f ON f.FacultyID = s.FacultyID
     WHERE NOT EXISTS (
         SELECT 1
         FROM Contracts c
         WHERE c.StudentID = s.StudentID AND c.Status = 'Hiệu lực'
     )
     ORDER BY s.FullName ASC"
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    $studentId = (int)($_POST['student_id'] ?? 0);
    $startDate = trim((string)($_POST['start_date'] ?? ''));
    $endDate = trim((string)($_POST['end_date'] ?? ''));

    if (($room['Status'] ?? '') === 'Báº£o trÃ¬') {
        $_SESSION['message'] = 'PhÃ²ng Ä‘ang báº£o trÃ¬, khÃ´ng thá»ƒ thÃªm sinh viÃªn.';
        $_SESSION['message_type'] = 'warning';
        header("Location: room_view.php?id={$roomId}");
        exit;
    }

    if ($studentId <= 0 || $startDate === '' || $endDate === '') {
        $_SESSION['message'] = 'Vui lòng nhập đầy đủ thông tin hợp đồng.';
        $_SESSION['message_type'] = 'warning';
        header("Location: room_add_student.php?room={$roomId}");
        exit;
    }

    if ($endDate <= $startDate) {
        $_SESSION['message'] = 'Ngày kết thúc phải sau ngày bắt đầu.';
        $_SESSION['message_type'] = 'warning';
        header("Location: room_add_student.php?room={$roomId}");
        exit;
    }

    $activeOccupantsStmt = $conn->prepare(
        "SELECT COUNT(*) AS cnt
         FROM Contracts
         WHERE RoomID = ? AND Status = 'Hiệu lực'"
    );
    $activeOccupantsStmt->bind_param('i', $roomId);
    $activeOccupantsStmt->execute();
    $activeOccupants = (int)($activeOccupantsStmt->get_result()->fetch_assoc()['cnt'] ?? 0);
    $activeOccupantsStmt->close();

    if ($activeOccupants >= (int)$room['Capacity']) {
        $_SESSION['message'] = 'Phòng đã đầy, không thể thêm sinh viên mới.';
        $_SESSION['message_type'] = 'warning';
        header("Location: room_view.php?id={$roomId}");
        exit;
    }

    $studentStmt = $conn->prepare(
        "SELECT StudentID, StudentCode, FullName
         FROM Students
         WHERE StudentID = ?
         LIMIT 1"
    );
    $studentStmt->bind_param('i', $studentId);
    $studentStmt->execute();
    $student = $studentStmt->get_result()->fetch_assoc();
    $studentStmt->close();

    if (!$student) {
        $_SESSION['message'] = 'Không tìm thấy sinh viên đã chọn.';
        $_SESSION['message_type'] = 'error';
        header("Location: room_add_student.php?room={$roomId}");
        exit;
    }

    $checkStmt = $conn->prepare(
        "SELECT COUNT(*) AS cnt
         FROM Contracts
         WHERE StudentID = ? AND Status = 'Hiệu lực'"
    );
    $checkStmt->bind_param('i', $studentId);
    $checkStmt->execute();
    $existingContracts = (int)($checkStmt->get_result()->fetch_assoc()['cnt'] ?? 0);
    $checkStmt->close();

    if ($existingContracts > 0) {
        $_SESSION['message'] = 'Sinh viên này đã có hợp đồng hiệu lực.';
        $_SESSION['message_type'] = 'warning';
        header("Location: room_view.php?id={$roomId}");
        exit;
    }

    try {
        $conn->begin_transaction();

        $insertStmt = $conn->prepare(
            "INSERT INTO Contracts (StudentID, RoomID, StartDate, EndDate, Status)
             VALUES (?, ?, ?, ?, 'Hiệu lực')"
        );
        $insertStmt->bind_param('iiss', $studentId, $roomId, $startDate, $endDate);
        $insertStmt->execute();
        $insertStmt->close();

        $newOccupants = $activeOccupants + 1;
        $newStatus = $newOccupants >= (int)$room['Capacity'] ? 'Đầy' : 'Trống';
        $updateRoomStmt = $conn->prepare(
            "UPDATE Rooms
             SET CurrentOccupants = ?, Status = ?, UpdatedAt = NOW()
             WHERE RoomID = ?"
        );
        $updateRoomStmt->bind_param('isi', $newOccupants, $newStatus, $roomId);
        $updateRoomStmt->execute();
        $updateRoomStmt->close();

        $conn->commit();

        logRoomAction(
            $conn,
            $_SESSION['UserID'] ?? null,
            'assign_student',
            "Thêm sinh viên {$student['StudentCode']} vào phòng {$room['BuildingName']}-{$room['RoomNumber']}",
            'activity'
        );

        $_SESSION['message'] = 'Đã thêm sinh viên vào phòng thành công.';
        $_SESSION['message_type'] = 'success';
        header("Location: room_view.php?id={$roomId}");
        exit;
    } catch (Throwable $e) {
        $conn->rollback();
        logRoomAction(
            $conn,
            $_SESSION['UserID'] ?? null,
            'assign_student_failed',
            'Thêm sinh viên vào phòng thất bại: RoomID=' . $roomId . ' - ' . $e->getMessage(),
            'warning'
        );
        $_SESSION['message'] = 'Không thể thêm sinh viên vào phòng: ' . $e->getMessage();
        $_SESSION['message_type'] = 'error';
        header("Location: room_add_student.php?room={$roomId}");
        exit;
    }
}

$capacityClass = '';
if ((int)$room['CurrentOccupants'] >= (int)$room['Capacity']) {
    $capacityClass = 'danger';
} elseif ((int)$room['CurrentOccupants'] >= (int)$room['Capacity'] * 0.8) {
    $capacityClass = 'warning';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thêm sinh viên vào <?= htmlspecialchars((string)$room['RoomNumber'], ENT_QUOTES, 'UTF-8') ?> | Hệ thống Ký túc xá</title>
    <link rel="stylesheet" href="../../assets/css/admin/admin_header.css">
    <link rel="stylesheet" href="../../assets/css/staff/room/staff_room_add_student.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <div class="form-container">
        <div class="form-header">
            <h2><i class="fas fa-user-plus"></i> Thêm Sinh Viên Vào Phòng</h2>
        </div>

        <div class="room-info">
            <div class="room-details">
                <div class="room-detail-item">
                    <i class="fas fa-door-open"></i>
                    <span>Phòng: <?= htmlspecialchars((string)$room['RoomNumber'], ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="room-detail-item">
                    <i class="fas fa-building"></i>
                    <span>Tòa: <?= htmlspecialchars((string)$room['BuildingName'], ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            </div>
            <div class="capacity-badge <?= $capacityClass ?>">
                <i class="fas fa-users"></i>
                <span><?= (int)$room['CurrentOccupants'] ?> / <?= (int)$room['Capacity'] ?> sinh viên</span>
            </div>
        </div>

        <div class="form-body">
            <form method="post" id="addForm">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="room_id" value="<?= $roomId ?>">

                <div class="form-group">
                    <label for="student_id"><i class="fas fa-user-graduate"></i> Chọn sinh viên</label>
                    <select class="form-select" name="student_id" id="student_id" required>
                        <option value="">-- Chọn sinh viên --</option>
                        <?php if ($students instanceof mysqli_result && $students->num_rows > 0): ?>
                            <?php while ($student = $students->fetch_assoc()): ?>
                                <option value="<?= (int)$student['StudentID'] ?>">
                                    <?= htmlspecialchars((string)$student['FullName'], ENT_QUOTES, 'UTF-8') ?>
                                    - <?= htmlspecialchars((string)$student['StudentCode'], ENT_QUOTES, 'UTF-8') ?>
                                    - <?= htmlspecialchars((string)($student['FacultyName'] ?? 'Chưa có khoa'), ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <option disabled>Không có sinh viên khả dụng</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="start_date"><i class="fas fa-calendar-plus"></i> Ngày bắt đầu</label>
                        <input type="date" class="form-control" name="start_date" id="start_date" required>
                    </div>
                    <div class="form-group">
                        <label for="end_date"><i class="fas fa-calendar-minus"></i> Ngày kết thúc</label>
                        <input type="date" class="form-control" name="end_date" id="end_date" required>
                    </div>
                </div>

                <div class="actions">
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="fas fa-save"></i> Lưu Hợp Đồng
                    </button>
                    <a href="room_view.php?id=<?= $roomId ?>" class="btn btn-outline">
                        <i class="fas fa-arrow-left"></i> Quay Lại
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('start_date').min = today;
        document.getElementById('end_date').min = today;

        document.getElementById('start_date').addEventListener('change', function() {
            const start = new Date(this.value);
            const end = new Date(start);
            end.setFullYear(end.getFullYear() + 1);
            document.getElementById('end_date').value = end.toISOString().split('T')[0];
        });

        document.getElementById('addForm').addEventListener('submit', function(event) {
            const start = new Date(document.getElementById('start_date').value);
            const end = new Date(document.getElementById('end_date').value);
            const studentId = document.getElementById('student_id').value;

            if (!studentId) {
                event.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Thiếu thông tin',
                    text: 'Vui lòng chọn sinh viên.',
                    confirmButtonColor: '#4361ee'
                });
                return;
            }

            if (end <= start) {
                event.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Lỗi ngày tháng',
                    text: 'Ngày kết thúc phải sau ngày bắt đầu.',
                    confirmButtonColor: '#4361ee'
                });
                return;
            }

            const submitBtn = document.getElementById('submitBtn');
            submitBtn.classList.add('btn-loading');
            submitBtn.disabled = true;
        });

        <?php if (isset($_SESSION['message'])): ?>
        Swal.fire({
            icon: '<?= $_SESSION['message_type'] === 'success' ? 'success' : ($_SESSION['message_type'] ?? 'info') ?>',
            title: <?= json_encode($_SESSION['message'], JSON_UNESCAPED_UNICODE) ?>,
            confirmButtonColor: '#4361ee'
        });
        <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
        <?php endif; ?>
    </script>
</body>
</html>
