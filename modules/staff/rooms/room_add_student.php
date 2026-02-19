<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include '../../../db_connect.php';
include '../../../includes/auth_check.php';
requireRole(['Admin']);

$roomId = (int)($_GET['room'] ?? 0);
$room = $conn->query("SELECT * FROM Rooms WHERE RoomID = $roomId")->fetch_assoc();
if (!$room) die("❌ Không tìm thấy phòng!");

// ✅ Lấy danh sách sinh viên chưa có hợp đồng hiệu lực
$students = $conn->query("
    SELECT s.StudentID, s.StudentCode, s.FullName, s.Gender, s.Faculty
    FROM Students s
    WHERE s.StudentID NOT IN (
        SELECT StudentID FROM Contracts WHERE Status IN ('Hiệu lực', 'Đang hiệu lực')
    )
    ORDER BY s.FullName
");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $studentId = (int)$_POST['student_id'];
    $startDate = $_POST['start_date'];
    $endDate = $_POST['end_date'];

    // Kiểm tra phòng còn chỗ
    if ($room['CurrentOccupants'] >= $room['Capacity']) {
        $_SESSION['message'] = "⚠️ Phòng đã đầy, không thể thêm sinh viên mới!";
        $_SESSION['message_type'] = "warning";
        header("Location: room_view.php?id=$roomId");
        exit;
    }

    // Kiểm tra sinh viên đã có hợp đồng hiệu lực chưa
    $check = $conn->prepare("SELECT COUNT(*) AS cnt FROM Contracts WHERE StudentID = ? AND Status IN ('Hiệu lực', 'Đang hiệu lực')");
    $check->bind_param("i", $studentId);
    $check->execute();
    $count = $check->get_result()->fetch_assoc()['cnt'] ?? 0;
    $check->close();

    if ($count > 0) {
        $_SESSION['message'] = "⚠️ Sinh viên này đã có hợp đồng hiệu lực, không thể thêm!";
        $_SESSION['message_type'] = "warning";
        header("Location: room_view.php?id=$roomId");
        exit;
    }

    // ✅ Thêm hợp đồng mới
    $stmt = $conn->prepare("INSERT INTO Contracts (StudentID, RoomID, StartDate, EndDate, Status)
                            VALUES (?, ?, ?, ?, 'Hiệu lực')");
    $stmt->bind_param("iiss", $studentId, $roomId, $startDate, $endDate);
    $stmt->execute();

    // ✅ Cập nhật số người đang ở
    $conn->query("UPDATE Rooms SET CurrentOccupants = CurrentOccupants + 1 WHERE RoomID = $roomId");

    $_SESSION['message'] = "✅ Đã thêm sinh viên vào phòng thành công!";
    $_SESSION['message_type'] = "success";
    header("Location: room_view.php?id=$roomId");
    exit;
}

// Xác định trạng thái sức chứa
$capacityClass = '';
if ($room['CurrentOccupants'] >= $room['Capacity']) {
    $capacityClass = 'danger';
} elseif ($room['CurrentOccupants'] >= $room['Capacity'] * 0.8) {
    $capacityClass = 'warning';
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thêm sinh viên vào <?= htmlspecialchars($room['RoomNumber']) ?> | Hệ thống Ký túc xá</title>
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
                    <span>Phòng: <?= htmlspecialchars($room['RoomNumber']) ?></span>
                </div>
                <div class="room-detail-item">
                    <i class="fas fa-building"></i>
                    <span>Tòa: <?= htmlspecialchars($room['BuildingID']) ?></span>
                </div>
            </div>
            <div class="capacity-badge <?= $capacityClass ?>">
                <i class="fas fa-users"></i>
                <span><?= $room['CurrentOccupants'] ?> / <?= $room['Capacity'] ?> sinh viên</span>
            </div>
        </div>

        <div class="form-body">
            <form method="post" id="addForm">
                <div class="form-group">
                    <label for="student_id"><i class="fas fa-user-graduate"></i> Chọn sinh viên</label>
                    <select class="form-select" name="student_id" id="student_id" required>
                        <option value="">-- Chọn sinh viên --</option>
                        <?php if ($students->num_rows > 0): ?>
                            <?php while ($s = $students->fetch_assoc()): ?>
                                <option value="<?= $s['StudentID'] ?>">
                                    <div class="student-option">
                                        <div class="student-info">
                                            <span class="student-name"><?= htmlspecialchars($s['FullName']) ?></span>
                                            <span class="student-code"><?= $s['StudentCode'] ?></span>
                                        </div>
                                        <div class="student-details">
                                            <span><?= $s['Gender'] ?></span>
                                            <span>•</span>
                                            <span><?= $s['Faculty'] ?></span>
                                        </div>
                                    </div>
                                </option>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <option disabled>
                                <div class="empty-state">
                                    <i class="fas fa-users-slash"></i>
                                    <h3>Không có sinh viên nào khả dụng</h3>
                                    <p>Tất cả sinh viên đều đã có hợp đồng hiệu lực</p>
                                </div>
                            </option>
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
        // Ngày mặc định
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('start_date').min = today;
        document.getElementById('end_date').min = today;

        // Gợi ý kết thúc sau 1 năm
        document.getElementById('start_date').addEventListener('change', function() {
            const start = new Date(this.value);
            const end = new Date(start);
            end.setFullYear(end.getFullYear() + 1);
            document.getElementById('end_date').value = end.toISOString().split('T')[0];
        });

        // Xác thực form
        document.getElementById('addForm').addEventListener('submit', function(e) {
            const start = new Date(document.getElementById('start_date').value);
            const end = new Date(document.getElementById('end_date').value);
            const studentId = document.getElementById('student_id').value;

            if (!studentId) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Thiếu thông tin',
                    text: 'Vui lòng chọn sinh viên!',
                    confirmButtonColor: '#4361ee'
                });
                return;
            }

            if (end <= start) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Lỗi ngày tháng',
                    text: 'Ngày kết thúc phải sau ngày bắt đầu!',
                    confirmButtonColor: '#4361ee'
                });
                return;
            }

            // Hiển thị loading
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.classList.add('btn-loading');
            submitBtn.disabled = true;
        });

        // Hiển thị thông báo nếu có
        <?php if (isset($_SESSION['message'])): ?>
            Swal.fire({
                icon: '<?= $_SESSION['message_type'] === 'success' ? 'success' : 'warning' ?>',
                title: '<?= $_SESSION['message'] ?>',
                confirmButtonColor: '#4361ee'
            });
            <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
        <?php endif; ?>
    </script>
</body>

</html>