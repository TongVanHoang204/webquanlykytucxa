<?php
require_once 'db_connect.php';

$output = "--- Notifications Table ---\n";
$res = $conn->query("DESCRIBE Notifications");
if($res) {
    while ($row = $res->fetch_assoc()) {
        $output .= $row['Field'] . "\n";
    }
} else {
    $output .= "Error: " . $conn->error . "\n";
}


$output .= "\n--- Students Table ---\n";
$res = $conn->query("DESCRIBE Students");
if($res) {
    while ($row = $res->fetch_assoc()) {
        $output .= $row['Field'] . "\n";
    }
} else {
    $output .= "Error: " . $conn->error . "\n";
}

file_put_contents('notify_schema_debug.txt', $output);
?>
