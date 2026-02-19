<?php
require_once 'db_connect.php';

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 1. InvoiceNotificationReads
$sql1 = "CREATE TABLE IF NOT EXISTS InvoiceNotificationReads (
    ReadID INT AUTO_INCREMENT PRIMARY KEY,
    InvoiceID INT NOT NULL,
    StudentID INT NOT NULL,
    ReadAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY (InvoiceID),
    KEY (StudentID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if ($conn->query($sql1) === TRUE) {
    echo "Table InvoiceNotificationReads created/exists.\n";
} else {
    echo "Error creating InvoiceNotificationReads: " . $conn->error . "\n";
}

// 2. AnnouncementViews
$sql2 = "CREATE TABLE IF NOT EXISTS AnnouncementViews (
    ViewID INT AUTO_INCREMENT PRIMARY KEY,
    AnnouncementID INT NOT NULL,
    StudentID INT NOT NULL,
    ViewedAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY (AnnouncementID),
    KEY (StudentID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if ($conn->query($sql2) === TRUE) {
    echo "Table AnnouncementViews created/exists.\n";
} else {
    echo "Error creating AnnouncementViews: " . $conn->error . "\n";
}

// 3. Announcements (just in case)
$sql3 = "CREATE TABLE IF NOT EXISTS Announcements (
    AnnouncementID INT AUTO_INCREMENT PRIMARY KEY,
    Title VARCHAR(255) NOT NULL,
    Content TEXT NOT NULL,
    DatePosted DATETIME DEFAULT CURRENT_TIMESTAMP,
    AuthorID INT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if ($conn->query($sql3) === TRUE) {
    echo "Table Announcements created/exists.\n";
} else {
    echo "Error creating Announcements: " . $conn->error . "\n";
}

echo "Done.";
$conn->close();
?>
