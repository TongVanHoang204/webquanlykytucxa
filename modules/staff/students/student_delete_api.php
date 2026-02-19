<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');
require_once '../../../db_connect.php';
require_once '../../../includes/auth_check.php';
requireRole(['Admin']); // chỉ Admin được xóa

if ($_SERVER['REQUEST_METHOD']!=='POST' || empty($_POST['id'])) {
    echo json_encode(['status'=>'error','message'=>'Yêu cầu không hợp lệ']); exit;
}
$id = (int)$_POST['id'];

/* Có thể kiểm tra ràng buộc: còn hợp đồng/hoá đơn… thì từ chối xóa */
$chk = $conn->prepare("SELECT 1 FROM Contracts WHERE StudentID=? LIMIT 1");
$chk->bind_param('i',$id);
$chk->execute(); $chk->store_result();
if ($chk->num_rows > 0) {
    echo json_encode(['status'=>'error','message'=>'Sinh viên còn hợp đồng, không thể xóa.']); exit;
}

/* Thực hiện xóa */
$stm = $conn->prepare("DELETE FROM Students WHERE StudentID=?");
if (!$stm) { echo json_encode(['status'=>'error','message'=>'Lỗi prepare: '.$conn->error]); exit; }
$stm->bind_param('i',$id);
if ($stm->execute()) {
    echo json_encode(['status'=>'success','message'=>'Đã xóa sinh viên khỏi hệ thống.']);
} else {
    echo json_encode(['status'=>'error','message'=>'Không thể xóa: '.$conn->error]);
}
