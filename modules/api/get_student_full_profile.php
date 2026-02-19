<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../db_connect.php';

// ===== 1. KIỂM TRA ĐĂNG NHẬP =====
if (!isset($_SESSION['UserID'])) {
    echo json_encode([
        'success' => false,
        'error'   => 'NOT_LOGGED_IN',
        'message' => 'Bạn chưa đăng nhập.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$userId = (int)$_SESSION['UserID'];

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $conn->set_charset("utf8mb4");

    // ===== 2. LẤY THÔNG TIN USER + STUDENT =====
    // User
    $sqlUser = "
        SELECT u.UserID, u.Username, u.FullName, u.Email, u.Phone, u.Role, u.CreatedAt, u.IsActive
        FROM users u
        WHERE u.UserID = ?
        LIMIT 1
    ";
    $stmt = $conn->prepare($sqlUser);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        echo json_encode([
            'success' => false,
            'error'   => 'USER_NOT_FOUND'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Student + Faculty
    $sqlStudent = "
        SELECT 
            s.*,
            f.FacultyName,
            f.FacultyCode
        FROM students s
        LEFT JOIN faculties f ON s.FacultyID = f.FacultyID
        WHERE s.UserID = ?
        LIMIT 1
    ";
    $stmt = $conn->prepare($sqlStudent);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$student) {
        echo json_encode([
            'success' => false,
            'error'   => 'NO_STUDENT_PROFILE',
            'message' => 'Tài khoản này chưa gắn với hồ sơ sinh viên.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $studentId = (int)$student['StudentID'];

    // ===== 3. HỢP ĐỒNG (TẤT CẢ + ĐANG HIỆU LỰC) =====
    $sqlContracts = "
        SELECT 
            c.*,
            r.RoomNumber,
            r.RoomPrice,
            r.RoomType,
            r.Capacity,
            r.Status      AS RoomStatus,
            b.BuildingName,
            b.Description AS BuildingDescription
        FROM contracts c
        JOIN rooms r      ON c.RoomID = r.RoomID
        JOIN buildings b  ON r.BuildingID = b.BuildingID
        WHERE c.StudentID = ?
        ORDER BY c.StartDate DESC, c.ContractID DESC
    ";
    $stmt = $conn->prepare($sqlContracts);
    $stmt->bind_param("i", $studentId);
    $stmt->execute();
    $rsContracts = $stmt->get_result();
    $contracts = [];
    $activeContract = null;
    $currentRoom = null;

    while ($row = $rsContracts->fetch_assoc()) {
        $contracts[] = $row;
        if ($row['Status'] === 'Hiệu lực' && $activeContract === null) {
            $activeContract = $row;
            $currentRoom = [
                'BuildingName'        => $row['BuildingName'],
                'RoomNumber'          => $row['RoomNumber'],
                'RoomPrice'           => $row['RoomPrice'],
                'RoomType'            => $row['RoomType'],
                'Capacity'            => $row['Capacity'],
                'RoomStatus'          => $row['RoomStatus']
            ];
        }
    }
    $stmt->close();

    // ===== 4. HÓA ĐƠN (ALL + UNPAID + PAID) =====
    $sqlInvoices = "
        SELECT 
            i.*,
            c.StudentID,
            r.RoomNumber,
            b.BuildingName
        FROM invoices i
        JOIN contracts c ON i.ContractID = c.ContractID
        JOIN rooms r     ON c.RoomID    = r.RoomID
        JOIN buildings b ON r.BuildingID = b.BuildingID
        WHERE c.StudentID = ?
        ORDER BY i.Year DESC, i.Month DESC, i.InvoiceID DESC
    ";
    $stmt = $conn->prepare($sqlInvoices);
    $stmt->bind_param("i", $studentId);
    $stmt->execute();
    $rsInv = $stmt->get_result();
    $invoicesAll = [];
    $invoicesUnpaid = [];
    $invoicesPaid = [];

    while ($row = $rsInv->fetch_assoc()) {
        $invoicesAll[] = $row;
        if ($row['Status'] === 'Chưa thanh toán') {
            $invoicesUnpaid[] = $row;
        } else {
            $invoicesPaid[] = $row;
        }
    }
    $stmt->close();

    // ===== 5. PAYMENTS =====
    $sqlPayments = "
        SELECT p.*, r.RoomNumber, b.BuildingName
        FROM payments p
        JOIN rooms r     ON p.RoomID = r.RoomID
        JOIN buildings b ON r.BuildingID = b.BuildingID
        WHERE p.StudentID = ?
        ORDER BY p.CreatedAt DESC
    ";
    $stmt = $conn->prepare($sqlPayments);
    $stmt->bind_param("i", $studentId);
    $stmt->execute();
    $rsPay = $stmt->get_result();
    $payments = [];
    while ($row = $rsPay->fetch_assoc()) {
        $payments[] = $row;
    }
    $stmt->close();

    // ===== 6. FEEDBACKS =====
    $sqlFeedbacks = "
        SELECT f.*
        FROM feedbacks f
        WHERE f.StudentID = ?
        ORDER BY f.CreatedAt DESC
    ";
    $stmt = $conn->prepare($sqlFeedbacks);
    $stmt->bind_param("i", $studentId);
    $stmt->execute();
    $rsFb = $stmt->get_result();
    $feedbacksAll = [];
    $feedbacksPending = [];
    $feedbacksDone = [];

    while ($row = $rsFb->fetch_assoc()) {
        $feedbacksAll[] = $row;
        if ($row['Status'] === 'Đã xử lý') {
            $feedbacksDone[] = $row;
        } else {
            $feedbacksPending[] = $row;
        }
    }
    $stmt->close();

    // ===== 7. ROOM REQUESTS =====
    $sqlRoomRequests = "
        SELECT rr.*, r.RoomNumber, b.BuildingName
        FROM roomrequests rr
        JOIN rooms r     ON rr.RoomID = r.RoomID
        JOIN buildings b ON r.BuildingID = b.BuildingID
        WHERE rr.StudentID = ?
        ORDER BY rr.CreatedAt DESC
    ";
    $stmt = $conn->prepare($sqlRoomRequests);
    $stmt->bind_param("i", $studentId);
    $stmt->execute();
    $rsReq = $stmt->get_result();
    $roomRequests = [];
    while ($row = $rsReq->fetch_assoc()) {
        $roomRequests[] = $row;
    }
    $stmt->close();

    // ===== 8. ACCESS CARDS =====
    $sqlCards = "
        SELECT a.*
        FROM accesscards a
        WHERE a.StudentID = ?
        ORDER BY a.IssuedDate DESC
    ";
    $stmt = $conn->prepare($sqlCards);
    $stmt->bind_param("i", $studentId);
    $stmt->execute();
    $rsCards = $stmt->get_result();
    $accessCards = [];
    while ($row = $rsCards->fetch_assoc()) {
        $accessCards[] = $row;
    }
    $stmt->close();

    // ===== 9. NOTIFICATIONS =====
    $sqlNoti = "
        SELECT n.*
        FROM notifications n
        WHERE n.StudentID = ?
        ORDER BY n.CreatedAt DESC
        LIMIT 20
    ";
    $stmt = $conn->prepare($sqlNoti);
    $stmt->bind_param("i", $studentId);
    $stmt->execute();
    $rsNoti = $stmt->get_result();
    $notifications = [];
    while ($row = $rsNoti->fetch_assoc()) {
        $notifications[] = $row;
    }
    $stmt->close();

    // ===== 10. ANNOUNCEMENTS + VIEWS =====
    // Lấy 10 thông báo chung mới nhất
    $sqlAnn = "
        SELECT a.*
        FROM announcements a
        ORDER BY a.DatePosted DESC
        LIMIT 10
    ";
    $rsAnn = $conn->query($sqlAnn);
    $announcements = [];
    while ($row = $rsAnn->fetch_assoc()) {
        $announcements[] = $row;
    }

    // ===== 11. ROOMS =====
    // ===== 11. ROOMS =====
    $sqlRooms = "
    SELECT 
        r.RoomID,
        r.RoomNumber,
        r.Capacity,

        -- Đếm số hợp đồng đang hiệu lực để tính số người đang ở
        (
            SELECT COUNT(*)
            FROM contracts c
            WHERE c.RoomID = r.RoomID
              AND c.Status = 'Hiệu lực'
        ) AS CurrentOccupants,

        -- Chỗ trống = Sức chứa - số người đang ở thật
        (r.Capacity - 
            (
                SELECT COUNT(*)
                FROM contracts c
                WHERE c.RoomID = r.RoomID
                  AND c.Status = 'Hiệu lực'
            )
        ) AS SlotsLeft,

        r.RoomType,
        r.RoomPrice,
        r.Status AS RoomStatus,
        b.BuildingName
    FROM rooms r
    JOIN buildings b ON r.BuildingID = b.BuildingID
    WHERE r.Status = 'Trống'
    HAVING SlotsLeft > 0
    ORDER BY b.BuildingName, r.RoomNumber
";

    $rooms = $conn->query($sqlRooms)->fetch_all(MYSQLI_ASSOC);



    // Những announcement mà sinh viên đã xem
    $sqlAnnViews = "
        SELECT av.AnnouncementID
        FROM announcementviews av
        WHERE av.StudentID = ?
    ";
    $stmt = $conn->prepare($sqlAnnViews);
    $stmt->bind_param("i", $studentId);
    $stmt->execute();
    $rsAnnV = $stmt->get_result();
    $announcementViews = [];
    while ($row = $rsAnnV->fetch_assoc()) {
        $announcementViews[] = (int)$row['AnnouncementID'];
    }
    $stmt->close();



    // ===== 12. TỔNG HỢP RESPONSE =====
    $response = [
        'success'  => true,
        'meta'     => [
            'generatedAt' => date('Y-m-d H:i:s'),
        ],
        'user'     => $user,
        'student'  => $student,
        'currentRoom'     => $currentRoom,
        'activeContract'  => $activeContract,
        'contracts'       => $contracts,
        'invoices'        => [
            'all'    => $invoicesAll,
            'unpaid' => $invoicesUnpaid,
            'paid'   => $invoicesPaid,
        ],
        'payments'        => $payments,
        'feedbacks'       => [
            'all'    => $feedbacksAll,
            'pending' => $feedbacksPending,
            'done'   => $feedbacksDone,
        ],
        'roomRequests'    => $roomRequests,
        'accessCards'     => $accessCards,
        'notifications'   => $notifications,
        'announcements'   => [
            'latest'   => $announcements,
            'viewedId' => $announcementViews,
        ],
        'rooms'           => $conn->query($sqlRooms)->fetch_all(MYSQLI_ASSOC),

    ];

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'error'   => 'SERVER_ERROR',
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);

    // ❌ BỎ dòng echo thứ 2 đi, nếu cần log thì log trong file, không in ra output JSON
    // error_log($e->getMessage());
}
