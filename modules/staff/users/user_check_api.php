<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');
require_once '../../../db_connect.php';
require_once '../../../includes/auth_check.php';
requireRole(['Admin', 'Manager']);

$out = ['username_taken' => false, 'email_taken' => false];

if (!empty($_POST['username'])) {
    $u = trim($_POST['username']);
    $stm = $conn->prepare("SELECT 1 FROM Users WHERE Username=? LIMIT 1");
    if ($stm) {
        $stm->bind_param('s', $u);
        $stm->execute();
        $stm->store_result();
        $out['username_taken'] = $stm->num_rows > 0;
        $stm->close();
    }
}

if (!empty($_POST['email'])) {
    $e = trim($_POST['email']);
    $stm = $conn->prepare("SELECT 1 FROM Users WHERE Email=? LIMIT 1");
    if ($stm) {
        $stm->bind_param('s', $e);
        $stm->execute();
        $stm->store_result();
        $out['email_taken'] = $stm->num_rows > 0;
        $stm->close();
    }
}

echo json_encode($out);
