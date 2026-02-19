<?php
require_once 'db_connect.php';

echo "--- Notifications Table ---\n";
$res = $conn->query("DESCRIBE Notifications");
while ($row = $res->fetch_assoc()) {
    echo $row['Field'] . "\n";
}

echo "\n--- Students Table ---\n";
$res = $conn->query("DESCRIBE Students");
while ($row = $res->fetch_assoc()) {
    echo $row['Field'] . "\n";
}
?>
