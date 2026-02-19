<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db_connect.php';

echo "Connected successfully.\n";
echo "Database: " . $conn->query("SELECT DATABASE()")->fetch_row()[0] . "\n";

echo "Tables:\n";
$tables = [];
$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_row()) {
    $tables[] = $row[0];
    echo " - " . $row[0] . "\n";
}

if (in_array('systemlogs', $tables) || in_array('SystemLogs', $tables)) {
    echo "Found 'systemlogs' or 'SystemLogs' in table list.\n";
    
    // Check structure
    $desc = $conn->query("DESCRIBE SystemLogs");
    if ($desc) {
        echo "Table structure:\n";
        while($row = $desc->fetch_assoc()) {
            echo " - " . $row['Field'] . " (" . $row['Type'] . ")\n";
        }
    } else {
        echo "Failed to DESCRIBE SystemLogs: " . $conn->error . "\n";
    }

} else {
    echo "CRITICAL: 'SystemLogs' NOT found in table list.\n";
    
    // Try to create it right here
    $sql = "CREATE TABLE IF NOT EXISTS SystemLogs (
        LogID INT AUTO_INCREMENT PRIMARY KEY,
        UserID INT NULL,
        Action VARCHAR(255) NOT NULL,
        Module VARCHAR(100) NOT NULL,
        Description TEXT,
        LogType VARCHAR(50) DEFAULT 'activity',
        IPAddress VARCHAR(45),
        UserAgent TEXT,
        CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    if ($conn->query($sql) === TRUE) {
        echo "Attempted to create SystemLogs: SUCCESS.\n";
    } else {
        echo "Attempted to create SystemLogs: FAILED - " . $conn->error . "\n";
    }
}
?>
