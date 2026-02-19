<?php
// modules/staff/contract/contract_delete_api.php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

require_once '../../../db_connect.php';
require_once '../../../includes/auth_check.php';
requireRole(['Admin']); // Chỉ Admin/Manager

/* ============== Helpers ============== */
function json_exit(int $code, array $payload)
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

// Bắt mọi lỗi -> trả JSON
set_exception_handler(function ($e) {
    json_exit(500, ['ok' => false, 'error' => 'Internal error: ' . $e->getMessage()]);
});
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    throw new ErrorException("$errstr @ $errfile:$errline", 0, $errno);
});

/* ============== Method check ============== */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_exit(405, ['ok' => false, 'error' => 'Chỉ hỗ trợ POST']);
}

/* ============== CSRF ============== */
$session_csrf = $_SESSION['_csrf'] ?? '';
$csrf_header  = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
$csrf_post    = $_POST['_csrf'] ?? '';
if (!$session_csrf || (!hash_equals($session_csrf, (string)$csrf_header) && !hash_equals($session_csrf, (string)$csrf_post))) {
    json_exit(400, ['ok' => false, 'error' => 'CSRF không hợp lệ']);
}

/* ============== Input ============== */
$contractID = (int)($_POST['ContractID'] ?? $_POST['id'] ?? 0);
$hardDelete = (int)($_POST['hard'] ?? 0); // 0=hủy mềm, 1=xóa hẳn
if ($contractID <= 0) {
    json_exit(400, ['ok' => false, 'error' => 'Thiếu ContractID']);
}

/* ============== Lấy thông tin HĐ ============== */
$roomID = 0;
$curStatus = '';
$stmt = $conn->prepare("SELECT RoomID, Status FROM Contracts WHERE ContractID=?");
$stmt->bind_param("i", $contractID);
$stmt->execute();
$stmt->bind_result($roomID, $curStatus);
$found = $stmt->fetch();
$stmt->close();

if (!$found) {
    json_exit(404, ['ok' => false, 'error' => 'Không tìm thấy hợp đồng']);
}

/* ============== Transaction ============== */
$conn->begin_transaction();
try {
    if ($hardDelete === 1) {
        // Chỉ Admin mới được xóa hẳn
        if (($_SESSION['Role'] ?? '') !== 'Admin') {
            throw new Exception('Bạn không có quyền xóa hẳn.');
        }
        $del = $conn->prepare("DELETE FROM Contracts WHERE ContractID=?");
        $del->bind_param("i", $contractID);
        $del->execute();
        $del->close();
        $action = 'deleted';
        $message = 'Đã xóa hợp đồng.';
    } else {
        // Hủy mềm -> đặt ĐÃ HỦY
        if ($curStatus !== 'Đã hủy') {
            $upd = $conn->prepare("UPDATE Contracts SET Status='Đã hủy', UpdatedAt=NOW() WHERE ContractID=?");
            $upd->bind_param("i", $contractID);
            $upd->execute();
            $upd->close();
        }
        $action = 'canceled';
        $message = 'Đã hủy hợp đồng.';
    }

    /* ===== Đồng bộ phòng theo số HĐ hiệu lực ===== */
    // Đếm HĐ hiệu lực của phòng
    $active = 0;
    $q1 = $conn->prepare("SELECT COUNT(*) FROM Contracts WHERE RoomID=? AND Status='Hiệu lực'");
    $q1->bind_param("i", $roomID);
    $q1->execute();
    $q1->bind_result($active);
    $q1->fetch();
    $q1->close();

    // Lấy Capacity
    $capacity = 0;
    $q2 = $conn->prepare("SELECT Capacity FROM Rooms WHERE RoomID=?");
    $q2->bind_param("i", $roomID);
    $q2->execute();
    $q2->bind_result($capacity);
    $q2->fetch();
    $q2->close();

    // Chỉ dùng ENUM có sẵn: Trống / Đầy / Bảo trì
    // Quy ước: còn chỗ (1..capacity-1) => 'Trống'
    if ($active <= 0) {
        $roomStatus = 'Trống';
    } elseif ($active >= $capacity) {
        $roomStatus = 'Đầy';
    } else {
        $roomStatus = 'Trống'; // còn chỗ nhưng chưa đầy
    }

    $u = $conn->prepare("UPDATE Rooms SET CurrentOccupants=?, `Status`=? WHERE RoomID=?");
    if ($u === false) {
        throw new Exception('Prepare UPDATE Rooms lỗi: ' . $conn->error);
    }
    $u->bind_param("isi", $active, $roomStatus, $roomID);
    if (!$u->execute()) {
        throw new Exception('Không thể cập nhật phòng: ' . $u->error);
    }
    $u->close();

    $conn->commit();

    json_exit(200, [
        'ok'          => true,
        'action'      => $action,
        'message'     => $message,
        'room'        => (int)$roomID,
        'active'      => (int)$active,
        'capacity'    => (int)$capacity,
        'room_status' => $roomStatus  // <<< dùng đúng biến
    ]);
} catch (Throwable $e) {
    $conn->rollback();
    throw $e; // sẽ được handler trả JSON 500
}
