<?php
require_once 'db_connect.php';

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Modify Status column to VARCHAR(50) to support 'Quá hạn' and other statuses
$sql = "ALTER TABLE Invoices MODIFY COLUMN Status VARCHAR(50) DEFAULT 'Chưa thanh toán'";

if ($conn->query($sql) === TRUE) {
    echo "Column Status updated to VARCHAR(50) successfully.\n";
} else {
    echo "Error updating column Status: " . $conn->error . "\n";
}

$conn->close();
?>
