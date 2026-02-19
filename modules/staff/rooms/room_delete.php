<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include '../../../db_connect.php';
include '../../../includes/auth_check.php';
requireRole(['Admin']);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) die("❌ ID phòng không hợp lệ!");

// 🔍 Kiểm tra phòng có tồn tại không
$roomRes = $conn->query("SELECT RoomNumber, CurrentOccupants, ImagePath FROM Rooms WHERE RoomID = $id");
if (!$roomRes || $roomRes->num_rows === 0) {
    $_SESSION['message'] = "❌ Không tìm thấy phòng cần xóa!";
    $_SESSION['message_type'] = "error";
    header("Location: rooms.php");
    exit;
}
$room = $roomRes->fetch_assoc();

// 🚫 Kiểm tra có hợp đồng hiệu lực không (tức là vẫn có người đang ở)
$activeContracts = $conn->query("
    SELECT COUNT(*) AS cnt 
    FROM Contracts 
    WHERE RoomID = $id AND Status = 'Hiệu lực'
")->fetch_assoc()['cnt'];

// 🚫 Hoặc kiểm tra phòng còn người đang ở (CurrentOccupants > 0)
if ($activeContracts > 0 || (int)$room['CurrentOccupants'] > 0) {
    $_SESSION['message'] = "⚠️ Không thể xóa phòng <b>{$room['RoomNumber']}</b> vì vẫn còn sinh viên đang ở!";
    $_SESSION['message_type'] = "warning";
    header("Location: rooms.php");
    exit;
}

// ======================
// 🔥 XÓA DỮ LIỆU LIÊN QUAN
// ======================
try {
    $conn->begin_transaction();

    // Xóa hóa đơn liên quan đến hợp đồng trong phòng
    $conn->query("
        DELETE FROM Invoices 
        WHERE ContractID IN (SELECT ContractID FROM Contracts WHERE RoomID = $id)
    ");

    // Xóa thanh toán liên quan
    $conn->query("
        DELETE FROM Payments 
        WHERE ContractID IN (SELECT ContractID FROM Contracts WHERE RoomID = $id)
    ");

    // Xóa phản ánh của sinh viên trong phòng (nếu có)
    $conn->query("
        DELETE FROM Feedbacks 
        WHERE StudentID IN (
            SELECT StudentID FROM Contracts WHERE RoomID = $id
        )
    ");

    // Xóa hợp đồng
    $conn->query("DELETE FROM Contracts WHERE RoomID = $id");

    // 🖼 Xóa ảnh chính nếu có
    if (!empty($room['ImagePath']) && file_exists('../../' . $room['ImagePath'])) {
        unlink('../../' . $room['ImagePath']);
    }

    // Xóa bản ghi phòng
    $conn->query("DELETE FROM Rooms WHERE RoomID = $id");

    $conn->commit();

    $_SESSION['message'] = "✅ Đã xóa phòng <b>{$room['RoomNumber']}</b> và toàn bộ dữ liệu liên quan.";
    $_SESSION['message_type'] = "success";

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['message'] = "❌ Lỗi khi xóa: " . $e->getMessage();
    $_SESSION['message_type'] = "error";
}

if ($delStmt->execute()) {
    addLog(
        $conn,
        $_SESSION['UserID'] ?? null,
        'Delete room',
        'Rooms',
        "Xóa phòng ID={$roomID}",
        'system'
    );
}


header("Location: rooms.php");
exit;
?>
