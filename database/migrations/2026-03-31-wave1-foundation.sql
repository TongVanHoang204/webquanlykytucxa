CREATE TABLE IF NOT EXISTS `user_notifications` (
  `NotificationID` INT NOT NULL AUTO_INCREMENT,
  `UserID` INT NOT NULL,
  `Title` VARCHAR(255) NOT NULL,
  `Message` TEXT NOT NULL,
  `Type` VARCHAR(50) NOT NULL DEFAULT 'system',
  `Severity` VARCHAR(20) NOT NULL DEFAULT 'info',
  `Link` VARCHAR(255) DEFAULT NULL,
  `PayloadJson` LONGTEXT DEFAULT NULL,
  `IsRead` TINYINT(1) NOT NULL DEFAULT 0,
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ReadAt` DATETIME DEFAULT NULL,
  PRIMARY KEY (`NotificationID`),
  KEY `idx_user_notifications_user_created` (`UserID`, `CreatedAt`),
  KEY `idx_user_notifications_user_read` (`UserID`, `IsRead`, `CreatedAt`),
  CONSTRAINT `fk_user_notifications_user` FOREIGN KEY (`UserID`) REFERENCES `users` (`UserID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `email_campaigns` (
  `CampaignID` INT NOT NULL AUTO_INCREMENT,
  `Subject` VARCHAR(255) NOT NULL,
  `BodyHtml` LONGTEXT NOT NULL,
  `AudienceType` VARCHAR(50) NOT NULL,
  `CreatedByUserID` INT NOT NULL,
  `Status` VARCHAR(20) NOT NULL DEFAULT 'draft',
  `TotalRecipients` INT NOT NULL DEFAULT 0,
  `SuccessCount` INT NOT NULL DEFAULT 0,
  `FailCount` INT NOT NULL DEFAULT 0,
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `SentAt` DATETIME DEFAULT NULL,
  PRIMARY KEY (`CampaignID`),
  KEY `idx_email_campaigns_created_by` (`CreatedByUserID`, `CreatedAt`),
  KEY `idx_email_campaigns_status` (`Status`, `CreatedAt`),
  CONSTRAINT `fk_email_campaigns_created_by` FOREIGN KEY (`CreatedByUserID`) REFERENCES `users` (`UserID`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `email_campaign_recipients` (
  `RecipientID` INT NOT NULL AUTO_INCREMENT,
  `CampaignID` INT NOT NULL,
  `UserID` INT NOT NULL,
  `Email` VARCHAR(255) NOT NULL,
  `SendStatus` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `ErrorMessage` TEXT DEFAULT NULL,
  `SentAt` DATETIME DEFAULT NULL,
  PRIMARY KEY (`RecipientID`),
  UNIQUE KEY `uniq_campaign_user` (`CampaignID`, `UserID`),
  KEY `idx_email_campaign_recipients_status` (`CampaignID`, `SendStatus`),
  CONSTRAINT `fk_email_campaign_recipients_campaign` FOREIGN KEY (`CampaignID`) REFERENCES `email_campaigns` (`CampaignID`) ON DELETE CASCADE,
  CONSTRAINT `fk_email_campaign_recipients_user` FOREIGN KEY (`UserID`) REFERENCES `users` (`UserID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `gate_qr_tokens` (
  `TokenID` INT NOT NULL AUTO_INCREMENT,
  `StudentID` INT NOT NULL,
  `TokenHash` CHAR(64) NOT NULL,
  `ExpiresAt` DATETIME NOT NULL,
  `IsUsed` TINYINT(1) NOT NULL DEFAULT 0,
  `ActiveStudentID` INT GENERATED ALWAYS AS (CASE WHEN `IsUsed` = 0 THEN `StudentID` ELSE NULL END) STORED,
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`TokenID`),
  UNIQUE KEY `uniq_gate_qr_token_hash` (`TokenHash`),
  UNIQUE KEY `uniq_gate_qr_active_student` (`ActiveStudentID`),
  KEY `idx_gate_qr_tokens_student_created` (`StudentID`, `CreatedAt`),
  KEY `idx_gate_qr_tokens_student_active` (`StudentID`, `IsUsed`, `ExpiresAt`),
  KEY `idx_gate_qr_tokens_expires` (`ExpiresAt`, `IsUsed`),
  CONSTRAINT `fk_gate_qr_tokens_student` FOREIGN KEY (`StudentID`) REFERENCES `students` (`StudentID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `gate_access_logs` (
  `LogID` INT NOT NULL AUTO_INCREMENT,
  `StudentID` INT NOT NULL,
  `CardID` INT DEFAULT NULL,
  `GateName` VARCHAR(100) NOT NULL DEFAULT 'Main Gate',
  `Direction` ENUM('IN', 'OUT') NOT NULL,
  `ScannedByUserID` INT DEFAULT NULL,
  `ScanMethod` VARCHAR(20) NOT NULL DEFAULT 'QR',
  `ScanToken` CHAR(64) DEFAULT NULL,
  `Status` VARCHAR(20) NOT NULL DEFAULT 'accepted',
  `RejectReason` VARCHAR(255) DEFAULT NULL,
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`LogID`),
  KEY `idx_gate_access_logs_student_created` (`StudentID`, `CreatedAt`),
  KEY `idx_gate_access_logs_gate_created` (`GateName`, `CreatedAt`),
  KEY `idx_gate_access_logs_token` (`ScanToken`, `CreatedAt`),
  CONSTRAINT `fk_gate_access_logs_student` FOREIGN KEY (`StudentID`) REFERENCES `students` (`StudentID`) ON DELETE CASCADE,
  CONSTRAINT `fk_gate_access_logs_card` FOREIGN KEY (`CardID`) REFERENCES `accesscards` (`CardID`) ON DELETE SET NULL,
  CONSTRAINT `fk_gate_access_logs_scanned_by` FOREIGN KEY (`ScannedByUserID`) REFERENCES `users` (`UserID`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
