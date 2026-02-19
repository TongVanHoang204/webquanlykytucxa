<?php
require_once 'db_connect.php';

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "--- Invoices Table Schema ---\n";
$result = $conn->query("DESCRIBE Invoices");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . " | " . $row['Type'] . " | " . $row['Null'] . "\n";
    }
} else {
    echo "Error describing table: " . $conn->error . "\n";
}

echo "\n--- Current Distinct Statuses ---\n";
$res2 = $conn->query("SELECT DISTINCT Status FROM Invoices");
if ($res2) {
    while ($row = $res2->fetch_assoc()) {
        echo $row['Status'] . "\n";
    }
}

$conn->close();
?>
