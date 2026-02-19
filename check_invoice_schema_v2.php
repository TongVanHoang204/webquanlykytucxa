<?php
require_once 'db_connect.php';

$output = "";
$output .= "--- Invoices Table Schema ---\n";
$result = $conn->query("DESCRIBE Invoices");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $output .= $row['Field'] . " | " . $row['Type'] . " | " . $row['Null'] . "\n";
    }
} else {
    $output .= "Error describing table: " . $conn->error . "\n";
}

$output .= "\n--- Current Distinct Statuses ---\n";
$res2 = $conn->query("SELECT DISTINCT Status FROM Invoices");
if ($res2) {
    while ($row = $res2->fetch_assoc()) {
        $output .= $row['Status'] . "\n";
    }
} else {
    $output .= "Error selecting distinct status: " . $conn->error . "\n";
}

file_put_contents('schema_debug.txt', $output);
?>
