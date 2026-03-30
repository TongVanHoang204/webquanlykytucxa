<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../../db_connect.php';
require_once '../../../includes/auth_check.php';
requireRole(['Admin', 'Student', 'Manager']);
$conn->set_charset('utf8mb4');

$currentUserId = (int)($_SESSION['UserID'] ?? 0);
$studentId = 0;
$stm = $conn->prepare("SELECT StudentID FROM students WHERE UserID = ? LIMIT 1");
$stm->bind_param('i', $currentUserId); $stm->execute(); $stm->bind_result($studentId); $stm->fetch(); $stm->close();

$requests = [];
if ($studentId > 0) {
    $stm = $conn->prepare("SELECT rr.RequestID, rr.Status, rr.DesiredFrom, rr.Note, rr.CreatedAt, r.RoomNumber FROM roomrequests rr JOIN rooms r ON r.RoomID = rr.RoomID WHERE rr.StudentID = ? ORDER BY rr.CreatedAt DESC");
    $stm->bind_param('i', $studentId); $stm->execute(); $res = $stm->get_result();
    while ($row = $res->fetch_assoc()) $requests[] = $row;
    $stm->close();
}
include '../../../includes/header.php';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yêu cầu đăng ký phòng | Ký túc xá</title>
    <link rel="stylesheet" href="<?= $base ?>assets/css/global.css">
    <link rel="stylesheet" href="<?= $base ?>assets/css/modules_shared.css">
    <style>
        .rq-pg { max-width:900px; margin:0 auto; padding:30px 20px; }
    </style>
</head>
<body>
    <div class="rq-pg">
        <div class="mod-header">
            <div>
                <h1 class="mod-title"><i class="fas fa-clipboard-list"></i> Yêu cầu đăng ký phòng</h1>
                <p class="mod-subtitle">Theo dõi trạng thái các yêu cầu đăng ký phòng của bạn</p>
            </div>
            <a href="<?= $base ?>modules/user/rooms/register_room.php" class="mod-btn mod-btn-primary"><i class="fas fa-plus"></i> Gửi yêu cầu mới</a>
        </div>

        <?php if (!$studentId): ?>
            <div class="mod-empty" style="padding:60px;"><i class="fas fa-user-slash"></i><p>Chưa tìm thấy hồ sơ sinh viên.</p></div>
        <?php elseif (empty($requests)): ?>
            <div class="mod-empty" style="padding:60px;"><i class="fas fa-inbox"></i><p>Bạn chưa có yêu cầu nào.</p></div>
        <?php else: ?>
            <div class="mod-table-wrap"><div class="mod-table-scroll">
                <table class="mod-table">
                    <thead><tr><th>#</th><th>Phòng</th><th>Ngày vào</th><th>Ghi chú</th><th>Trạng thái</th><th>Ngày gửi</th></tr></thead>
                    <tbody>
                        <?php foreach ($requests as $i => $r):
                            $badge = match($r['Status']) {
                                'Đã duyệt' => 'mod-badge-emerald',
                                'Chờ duyệt' => 'mod-badge-amber',
                                'Từ chối'   => 'mod-badge-rose',
                                default     => 'mod-badge-gray',
                            };
                        ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><strong><?= htmlspecialchars($r['RoomNumber']) ?></strong></td>
                                <td><?= htmlspecialchars($r['DesiredFrom'] ?: '—') ?></td>
                                <td><?= htmlspecialchars($r['Note'] ?: '—') ?></td>
                                <td><span class="mod-badge <?= $badge ?>"><?= htmlspecialchars($r['Status']) ?></span></td>
                                <td><?= date('d/m/Y H:i', strtotime($r['CreatedAt'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div></div>
        <?php endif; ?>
    </div>
    <?php include '../../../includes/footer.php'; ?>
</body>
</html>