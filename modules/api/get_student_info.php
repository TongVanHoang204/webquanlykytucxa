<?php
// Bật error reporting để debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/../../db_connect.php';

header('Content-Type: application/json');

// Kiểm tra đăng nhập
if (!isset($_SESSION['UserID'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['UserID'];

try {

    // Lấy thông tin sinh viên
    $studentInfo = null;
    $stmt = $conn->prepare("
    SELECT s.StudentID, s.FullName, s.StudentCode, s.Phone, s.Email
    FROM Students s
    WHERE s.UserID = ?
    LIMIT 1
");
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $studentInfo = $row;
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'error' => 'Database query error']);
        exit;
    }

    if (!$studentInfo) {
        echo json_encode(['success' => false, 'error' => 'Student not found']);
        exit;
    }

    $studentId = $studentInfo['StudentID'];

    // Lấy thông tin hợp đồng và phòng hiện tại
    $contractInfo = null;

    // Test query đơn giản trước
    $testStmt = $conn->prepare("SELECT ContractID, Status FROM contracts WHERE StudentID = ?");
    $testStmt->bind_param("i", $studentId);
    $testStmt->execute();
    $testResult = $testStmt->get_result();
    $testContracts = [];
    while ($testRow = $testResult->fetch_assoc()) {
        $testContracts[] = $testRow;
    }
    $testStmt->close();

    // Query chính: Lấy hợp đồng mới nhất (kèm số người đang ở)
    $stmt = $conn->prepare("
    SELECT 
        c.ContractID,
        c.StartDate,
        c.EndDate,
        c.Deposit,
        c.Status as ContractStatus,
        r.RoomID,
        r.RoomNumber,
        r.RoomType,
        r.Capacity,
        r.RoomPrice,
        b.BuildingID,
        b.BuildingName,
        b.Description as BuildingDescription,
        (
            SELECT COUNT(*) 
            FROM contracts c2 
            WHERE c2.RoomID = c.RoomID 
              AND c2.Status = 'Hiệu lực'
        ) AS CurrentOccupants
    FROM contracts c
    LEFT JOIN rooms r ON r.RoomID = c.RoomID
    LEFT JOIN buildings b ON b.BuildingID = r.BuildingID
    WHERE c.StudentID = ?
    ORDER BY c.ContractID DESC
    LIMIT 1
");

    $debugQueryResult = null;
    if ($stmt) {
        $stmt->bind_param("i", $studentId);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $debugQueryResult = [
                'num_rows' => $result->num_rows,
                'fields'   => []
            ];

            // Lấy tên cột
            $fields = $result->fetch_fields();
            foreach ($fields as $field) {
                $debugQueryResult['fields'][] = $field->name;
            }

            if ($result && $row = $result->fetch_assoc()) {
                $contractInfo = $row;

                // Chuẩn hóa kiểu số
                $capacity  = isset($row['Capacity']) ? (int)$row['Capacity'] : 0;
                $occupants = isset($row['CurrentOccupants']) ? (int)$row['CurrentOccupants'] : 0;

                $contractInfo['Capacity']         = $capacity;
                $contractInfo['CurrentOccupants'] = $occupants;
                $contractInfo['AvailableSlots']   = max(0, $capacity - $occupants);

                $debugQueryResult['rowData'] = $contractInfo;
            } else {
                $debugQueryResult['rowData'] = 'null or empty';
            }
        } else {
            $debugQueryResult = 'execute failed: ' . $stmt->error;
        }
        $stmt->close();
    } else {
        $debugQueryResult = 'prepare failed: ' . $conn->error;
    }


    // Đếm hóa đơn chưa thanh toán
    $unpaidInvoices = 0;
    $totalUnpaidAmount = 0;
    $stmt = $conn->prepare("
    SELECT COUNT(*) as Count, COALESCE(SUM(TotalAmount), 0) as TotalAmount
    FROM Invoices
    WHERE StudentID = ? AND Status = 'Chưa thanh toán'
");
    if ($stmt) {
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $unpaidInvoices = (int)$row['Count'];
            $totalUnpaidAmount = (float)$row['TotalAmount'];
        }
        $stmt->close();
    }

    // Đếm tổng số hóa đơn
    $totalInvoices = 0;
    $stmt = $conn->prepare("
    SELECT COUNT(*) as Count
    FROM Invoices
    WHERE StudentID = ?
");
    if ($stmt) {
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $totalInvoices = (int)$row['Count'];
        }
        $stmt->close();
    }

    // Đếm phản ánh đã gửi
    $totalFeedbacks = 0;
    $pendingFeedbacks = 0;
    $stmt = $conn->prepare("
    SELECT 
        COUNT(*) as Total,
        SUM(CASE WHEN Status = 'Đang xử lý' OR Status = 'Chờ xử lý' THEN 1 ELSE 0 END) as Pending
    FROM feedbacks
    WHERE StudentID = ?
");
    if ($stmt) {
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $totalFeedbacks = (int)$row['Total'];
            $pendingFeedbacks = (int)$row['Pending'];
        }
        $stmt->close();
    }

    // Đếm số phòng trống
    $availableRooms = 0;
    $availableRoomsByType = [];
    $result = $conn->query("
    SELECT 
        RoomType,
        COUNT(*) as Count
    FROM rooms
    WHERE Status = 'Trống'
    GROUP BY RoomType
");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $availableRooms += (int)$row['Count'];
            $availableRoomsByType[$row['RoomType']] = (int)$row['Count'];
        }
    }

    // Trả về dữ liệu
    echo json_encode([
        'success' => true,
        'debug' => [
            'studentId' => $studentId,
            'contractQuery' => 'SELECT c.ContractID FROM contracts c WHERE c.StudentID = ' . $studentId,
            'testContracts' => $testContracts,
            'queryResult' => $debugQueryResult
        ],
        'student' => [
            'id' => $studentInfo['StudentID'],
            'name' => $studentInfo['FullName'],
            'code' => $studentInfo['StudentCode'],
            'phone' => $studentInfo['Phone'] ?? '',
            'email' => $studentInfo['Email'] ?? ''
        ],
        'contract' => $contractInfo,
        'invoices' => [
            'total' => $totalInvoices,
            'unpaid' => $unpaidInvoices,
            'unpaidAmount' => $totalUnpaidAmount
        ],
        'feedbacks' => [
            'total' => $totalFeedbacks,
            'pending' => $pendingFeedbacks
        ],
        'rooms' => [
            'available' => $availableRooms,
            'byType' => $availableRoomsByType
        ]
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Server error'
    ]);
}
