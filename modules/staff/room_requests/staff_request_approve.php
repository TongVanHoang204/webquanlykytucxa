<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../../db_connect.php';
require_once '../../../includes/auth_check.php';
requireRole(['Admin']);

// Debug logging
error_log("DEBUG approve: Start processing RequestID=" . ($_GET['id'] ?? 'NULL'));

$conn->set_charset('utf8mb4');

$currentUserId = (int)($_SESSION['UserID'] ?? 0);
error_log("DEBUG approve: Current UserID=" . $currentUserId);

// Lấy id request từ query string
$requestId = (int)($_GET['id'] ?? 0);
if ($requestId <= 0) {
    error_log("DEBUG approve: Invalid RequestID=" . $requestId);
    $_SESSION['message'] = 'Yêu cầu không hợp lệ.';
    $_SESSION['message_type'] = 'error';
    header('Location: staff_request_list.php');
    exit;
}

/**
 * 1. Lấy thông tin yêu cầu + phòng + sinh viên
 */
$sql = "
    SELECT 
        rr.RequestID,
        rr.StudentID,
        rr.RoomID,
        rr.Status,
        rr.DesiredFrom,
        rr.CheckInDate,
        rr.CheckOutDate,
        st.FullName,
        st.StudentCode,
        st.IsInDorm,
        r.RoomNumber,
        COALESCE(r.Capacity, 0) AS Capacity,
        r.Status AS RoomStatus,
        COALESCE(r.RoomPrice,0) AS RoomPrice
    FROM roomrequests rr
    JOIN students st ON st.StudentID = rr.StudentID
    JOIN rooms r     ON r.RoomID     = rr.RoomID
    WHERE rr.RequestID = ?
    LIMIT 1
";

$stm = $conn->prepare($sql);
if (!$stm) {
    error_log("DEBUG approve: Prepare failed - " . $conn->error);
    $_SESSION['message'] = 'Lỗi hệ thống (prepare select): ' . $conn->error;
    $_SESSION['message_type'] = 'error';
    header('Location: staff_request_list.php');
    exit;
}
$stm->bind_param('i', $requestId);
$stm->execute();
$res = $stm->get_result();
$request = $res ? $res->fetch_assoc() : null;
$stm->close();

error_log("DEBUG approve: Request found=" . ($request ? 'YES' : 'NO'));

if (!$request) {
    error_log("DEBUG approve: Request not found for ID=" . $requestId);
    $_SESSION['message'] = 'Không tìm thấy yêu cầu tương ứng.';
    $_SESSION['message_type'] = 'error';
    header('Location: staff_request_list.php');
    exit;
}

error_log("DEBUG approve: Request status=" . $request['Status']);

if ($request['Status'] !== 'Chờ duyệt') {
    error_log("DEBUG approve: Request already processed with status=" . $request['Status']);
    $_SESSION['message'] = 'Yêu cầu này đã được xử lý trước đó.';
    $_SESSION['message_type'] = 'warning';
    header('Location: staff_request_list.php');
    exit;
}

$capacity   = (int)$request['Capacity'];
$roomId     = (int)$request['RoomID'];
$studentId  = (int)$request['StudentID'];
$roomPrice  = (float)$request['RoomPrice'];

// ===== 2. Lấy ngày từ DB hoặc tính toán mặc định =====
// Ngày bắt đầu: ưu tiên CheckInDate, nếu không có dùng DesiredFrom, cuối cùng là hôm nay
$startDate = $request['CheckInDate'];
if (empty($startDate)) {
    $startDate = $request['DesiredFrom'];
}
if (empty($startDate) || strtotime($startDate) === false) {
    $startDate = date('Y-m-d');
}

// Ngày kết thúc: ưu tiên CheckOutDate, nếu không có thì +6 tháng
$endDate = $request['CheckOutDate'];
if (empty($endDate) || strtotime($endDate) === false) {
    $endDate = date('Y-m-d', strtotime($startDate . ' +6 months'));
}

// Tiền cọc: mặc định bằng 1 tháng tiền phòng
$deposit = $roomPrice;

// Tiền cọc: mặc định bằng 1 tháng tiền phòng
$deposit = $roomPrice;

// Validate ngày tháng
if (strtotime($startDate) === false) {
    $_SESSION['message'] = 'Ngày bắt đầu không hợp lệ.';
    $_SESSION['message_type'] = 'error';
    header('Location: staff_request_list.php');
    exit;
}

error_log("DEBUG approve: startDate=$startDate, endDate=$endDate, deposit=$deposit");

/**
 * 3. Tính lại số người đang ở từ bảng contracts
 */
$activeCount = 0;
$stm = $conn->prepare("
    SELECT COUNT(*)
    FROM contracts
    WHERE RoomID = ?
      AND Status = 'Hiệu lực'
      AND StartDate <= CURDATE()
      AND (EndDate IS NULL OR EndDate >= CURDATE())
");
if ($stm) {
    $stm->bind_param('i', $roomId);
    $stm->execute();
    $stm->bind_result($activeCount);
    $stm->fetch();
    $stm->close();
}
$occupants = (int)$activeCount;

// Phòng còn chỗ?
if ($capacity > 0 && $occupants >= $capacity) {
    $_SESSION['message'] = 'Phòng đã đầy, không thể duyệt yêu cầu này.';
    $_SESSION['message_type'] = 'error';
    header('Location: staff_request_list.php');
    exit;
}

try {
    $conn->begin_transaction();

    /**
     * 4. Cập nhật trạng thái yêu cầu -> Đã duyệt
     */
    $sqlUpdateReq = "
        UPDATE roomrequests
        SET Status = 'Đã duyệt',
            ReviewedBy = ?,
            ReviewedAt = NOW()
        WHERE RequestID = ? AND Status = 'Chờ duyệt'
    ";
    $stm = $conn->prepare($sqlUpdateReq);
    if (!$stm) {
        throw new Exception('Lỗi prepare update request: ' . $conn->error);
    }
    $stm->bind_param('ii', $currentUserId, $requestId);
    $stm->execute();
    if ($stm->affected_rows <= 0) {
        $stm->close();
        throw new Exception('Không thể cập nhật trạng thái yêu cầu (có thể đã bị xử lý bởi người khác).');
    }
    $stm->close();

    /**
     * 5. Tạo hợp đồng mới từ dữ liệu yêu cầu
     */
    $sqlInsertContract = "
        INSERT INTO contracts 
            (StudentID, RoomID, StartDate, EndDate, Deposit, Status, PaymentStatus, CreatedAt, UpdatedAt)
        VALUES 
            (?, ?, ?, ?, ?, 'Hiệu lực', 'Còn nợ', NOW(), NOW())
    ";
    $stm = $conn->prepare($sqlInsertContract);
    if (!$stm) {
        throw new Exception('Lỗi prepare insert contract: ' . $conn->error);
    }
    $stm->bind_param('iissd', $studentId, $roomId, $startDate, $endDate, $deposit);
    $stm->execute();
    if ($stm->affected_rows <= 0) {
        $err = $stm->error;
        $stm->close();
        throw new Exception('Không thể tạo hợp đồng cho sinh viên: ' . $err);
    }
    $stm->close();

    /**
     * 6. Cập nhật số người trong phòng + trạng thái phòng
     */
    $newOccupants = $occupants + 1;
    if ($capacity > 0 && $newOccupants >= $capacity) {
        $newRoomStatus = 'Đầy';
    } elseif ($newOccupants > 0) {
        $newRoomStatus = 'Đang sử dụng';
    } else {
        $newRoomStatus = 'Trống';
    }

    $sqlUpdateRoom = "
        UPDATE rooms
        SET CurrentOccupants = ?,
            Status = ?
        WHERE RoomID = ?
    ";
    $stm = $conn->prepare($sqlUpdateRoom);
    if (!$stm) {
        throw new Exception('Lỗi prepare update room: ' . $conn->error);
    }
    $stm->bind_param('isi', $newOccupants, $newRoomStatus, $roomId);
    $stm->execute();
    $stm->close();

    /**
     * 7. Cập nhật trạng thái đang ở KTX của sinh viên
     */
    $sqlUpdateStudent = "
        UPDATE students
        SET IsInDorm = 1
        WHERE StudentID = ?
    ";
    $stm = $conn->prepare($sqlUpdateStudent);
    if ($stm) {
        $stm->bind_param('i', $studentId);
        $stm->execute();
        $stm->close();
    }

    $conn->commit();

    $_SESSION['message'] = '✅ Đã duyệt yêu cầu và tạo hợp đồng thành công từ RoomRequests.';
    $_SESSION['message_type'] = 'success';

    addLog(
        $conn,
        $_SESSION['UserID'] ?? null,
        'Create contract',
        'Contracts',
        "Tạo hợp đồng cho StudentID={$studentID}, RoomID={$roomID}",
        'activity'
    );
} catch (Exception $ex) {
    $conn->rollback();
    $_SESSION['message'] = '❌ Lỗi khi duyệt yêu cầu: ' . $ex->getMessage();
    $_SESSION['message_type'] = 'error';
}




// Sau khi duyệt xong chuyển sang danh sách hợp đồng
header('Location: ../contract/contract_list.php?approved=1');
exit;
