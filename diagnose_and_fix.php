<?php
require_once 'db_connect.php';

$log = [];
function myLog($msg) {
    global $log;
    $log[] = $msg;
}

if ($conn->connect_error) {
    myLog("Connection failed: " . $conn->connect_error);
    file_put_contents('diagnose.log', implode("\n", $log));
    die();
}

myLog("Current Database: " . $conn->query("SELECT DATABASE()")->fetch_row()[0]);

myLog("--- ALL TABLES ---");
$tables = [];
$result = $conn->query("SHOW TABLES");
if ($result) {
    while ($row = $result->fetch_row()) {
        $tables[] = $row[0];
        myLog($row[0]);
    }
}

myLog("--- END OF TABLES ---");

file_put_contents('diagnose.log', implode("\n", $log));
$conn->close();
?>
