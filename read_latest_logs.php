<?php
require_once 'db_connect.php';

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch latest logs
$sql = "SELECT * FROM SystemLogs ORDER BY LogID DESC LIMIT 5";
$result = $conn->query($sql);

if ($result) {
    echo "Latest Logs:\n";
    while ($row = $result->fetch_assoc()) {
        echo "[" . $row['CreatedAt'] . "] " . $row['LogType'] . ": " . $row['Description'] . "\n";
    }
} else {
    echo "Error reading logs: " . $conn->error . "\n";
}

$conn->close();
?>
