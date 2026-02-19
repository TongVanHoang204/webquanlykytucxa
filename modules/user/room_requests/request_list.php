<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../../db_connect.php';
require_once '../../../includes/auth_check.php';
requireRole(['Admin', 'Student', 'Manager']);

$conn->set_charset('utf8mb4');
$currentUserId = (int)($_SESSION['UserID'] ?? 0);

/* Lấy StudentID từ UserID */
$studentId = 0;
$stm = $conn->prepare("SELECT StudentID FROM students WHERE UserID = ? LIMIT 1");
$stm->bind_param('i', $currentUserId);
$stm->execute();
$stm->bind_result($studentId);
$stm->fetch();
$stm->close();

$requests = [];
if ($studentId > 0) {
    $sql = "
    SELECT 
            rr.RequestID,
            rr.Status,
            rr.DesiredFrom,
            rr.Note,
            rr.CreatedAt,
            r.RoomNumber
        FROM roomrequests rr
        JOIN rooms r ON r.RoomID = rr.RoomID
        WHERE rr.StudentID = ?
        ORDER BY rr.CreatedAt DESC
    ";

    $stm = $conn->prepare($sql);
    $stm->bind_param('i', $studentId);
    $stm->execute();
    $res = $stm->get_result();
    while ($row = $res->fetch_assoc()) $requests[] = $row;
    $stm->close();
}
include '../../../includes/header.php';
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Yêu cầu đăng ký phòng của tôi</title>
    <link rel="stylesheet" href="/assets/css/header.css">
    <link rel="stylesheet" href="/assets/css/user/room_requests/request_list.css">
</head>

<body>
    <div class="container">
        <div class="top-bar">
            <h2 style="margin:0"><i class="fa-solid fa-list"></i> Yêu cầu đăng ký phòng</h2>
            <a href="/modules/user/rooms/register_room.php" class="btn">
                <i class="fa-solid fa-plus"></i> Gửi yêu cầu mới
            </a>
        </div>

        <?php if (!$studentId): ?>
            <p>Chưa tìm thấy hồ sơ sinh viên gắn với tài khoản hiện tại.</p>
        <?php elseif (!$requests): ?>
            <p>Hiện bạn chưa có yêu cầu nào.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Phòng</th>
                        <th>Ngày vào</th>
                        <th>Ghi chú</th>
                        <th>Trạng thái</th>
                        <th>Ngày gửi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $i => $r):
                        $status = $r['Status'];
                        $cls = str_replace(' ', '', $status);
                    ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><?= htmlspecialchars($r['RoomNumber']) ?></td>
                            <td><?= htmlspecialchars($r['DesiredFrom'] ?: '-') ?></td>
                            <td><?= htmlspecialchars($r['Note'] ?: '-') ?></td>
                            <td><span class="status <?= $cls ?>"><?= htmlspecialchars($status) ?></span></td>
                            <td><?= htmlspecialchars($r['CreatedAt']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>

            </table>
        <?php endif; ?>
    </div>
</body>

</html>



<?php include '../../../includes/footer.php'; ?>