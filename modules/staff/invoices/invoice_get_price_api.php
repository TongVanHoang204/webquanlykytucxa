<?php
include '../../../db_connect.php';

$id = intval($_POST['id'] ?? 0);
$response = ['price' => 0];

if ($id) {
    $stmt = $conn->prepare("
        SELECT r.RoomPrice
        FROM Contracts c
        JOIN Rooms r ON c.RoomID = r.RoomID
        WHERE c.ContractID = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    if ($res) $response['price'] = (float)$res['RoomPrice'];
}

header('Content-Type: application/json');
echo json_encode($response);
