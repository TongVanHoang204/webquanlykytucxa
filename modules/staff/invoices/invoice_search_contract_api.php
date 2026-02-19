<?php
include '../../../db_connect.php';

$keyword = trim($_POST['keyword'] ?? '');
$data = [];

// Nếu có hoặc không có keyword đều tìm hợp đồng đang hiệu lực
if ($keyword !== '') {
    // Tìm theo keyword (tên hoặc MSSV)
    $stmt = $conn->prepare("
        SELECT c.ContractID, s.FullName, s.StudentCode, r.RoomNumber, b.BuildingName
        FROM Contracts c
        JOIN Students s ON c.StudentID = s.StudentID
        JOIN Rooms r ON c.RoomID = r.RoomID
        JOIN Buildings b ON r.BuildingID = b.BuildingID
        WHERE c.Status = 'Hiệu lực'
          AND (s.FullName LIKE CONCAT('%', ?, '%') OR s.StudentCode LIKE CONCAT('%', ?, '%'))
        ORDER BY s.FullName ASC
        LIMIT 20
    ");
    $stmt->bind_param("ss", $keyword, $keyword);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    // Không có keyword: trả về các hợp đồng đang hiệu lực (ví dụ giới hạn 20)
    $sql = "
        SELECT c.ContractID, s.FullName, s.StudentCode, r.RoomNumber, b.BuildingName
        FROM Contracts c
        JOIN Students s ON c.StudentID = s.StudentID
        JOIN Rooms r ON c.RoomID = r.RoomID
        JOIN Buildings b ON r.BuildingID = b.BuildingID
        WHERE c.Status = 'Hiệu lực'
        ORDER BY s.FullName ASC
        LIMIT 20
    ";
    $result = $conn->query($sql);
}

while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($data);
