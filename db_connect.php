<?php
$servername = "localhost";
$username = "root";
$password = "";
$database = "quanlyktx";

require_once __DIR__ . '/includes/log_helper.php';

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    die("❌ Lỗi kết nối: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


?>