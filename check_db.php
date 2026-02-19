<?php
require_once 'db_connect.php';

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Current Database: " . $conn->query("SELECT DATABASE()")->fetch_row()[0] . "\n";

echo "Tables in DB:\n";
$result = $conn->query("SHOW TABLES");
if ($result) {
    while ($row = $result->fetch_row()) {
        echo "- " . $row[0] . "\n";
    }
} else {
    echo "Error listing tables: " . $conn->error . "\n";
}

echo "\nChecking SystemLogs:\n";
$check = $conn->query("SELECT * FROM SystemLogs LIMIT 1");
if ($check) {
    echo "SELECT FROM SystemLogs: OK\n";
} else {
    echo "SELECT FROM SystemLogs: FAILED - " . $conn->error . "\n";
}

// Check lowercase just in case
$checkLower = $conn->query("SELECT * FROM systemlogs LIMIT 1");
if ($checkLower) {
    echo "SELECT FROM systemlogs: OK\n";
} else {
    echo "SELECT FROM systemlogs: FAILED - " . $conn->error . "\n";
}
?>
