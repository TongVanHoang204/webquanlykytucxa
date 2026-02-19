-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Server version:               8.0.30 - MySQL Community Server - GPL
-- Server OS:                    Win64
-- HeidiSQL Version:             12.1.0.6537
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Dumping database structure for quanlyktx
DROP DATABASE IF EXISTS `quanlyktx`;
CREATE DATABASE IF NOT EXISTS `quanlyktx` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `quanlyktx`;

-- Dumping structure for table quanlyktx.accesscards
DROP TABLE IF EXISTS `accesscards`;
CREATE TABLE IF NOT EXISTS `accesscards` (
  `CardID` int NOT NULL AUTO_INCREMENT,
  `StudentID` int NOT NULL,
  `CardCode` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `CardType` enum('QR','RFID') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'QR',
  `IssuedDate` datetime DEFAULT CURRENT_TIMESTAMP,
  `IsActive` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`CardID`),
  UNIQUE KEY `CardCode` (`CardCode`),
  KEY `StudentID` (`StudentID`),
  CONSTRAINT `accesscards_ibfk_1` FOREIGN KEY (`StudentID`) REFERENCES `students` (`StudentID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table quanlyktx.accesscards: ~0 rows (approximately)

-- Dumping structure for table quanlyktx.adminnotifications
DROP TABLE IF EXISTS `adminnotifications`;
CREATE TABLE IF NOT EXISTS `adminnotifications` (
  `NotificationID` int NOT NULL AUTO_INCREMENT,
  `Title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `CreatedAt` datetime DEFAULT CURRENT_TIMESTAMP,
  `IsRead` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`NotificationID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table quanlyktx.adminnotifications: ~0 rows (approximately)

-- Dumping structure for table quanlyktx.announcements
DROP TABLE IF EXISTS `announcements`;
CREATE TABLE IF NOT EXISTS `announcements` (
  `AnnouncementID` int NOT NULL AUTO_INCREMENT,
  `Title` varchar(255) NOT NULL,
  `Content` text NOT NULL,
  `DatePosted` datetime DEFAULT CURRENT_TIMESTAMP,
  `PostedBy` varchar(100) DEFAULT 'Ban quản lý',
  `AttachmentPath` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`AnnouncementID`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table quanlyktx.announcements: ~3 rows (approximately)
INSERT INTO `announcements` (`AnnouncementID`, `Title`, `Content`, `DatePosted`, `PostedBy`, `AttachmentPath`) VALUES
	(3, 'Thông báo cúp điện', 'Hiện tại điện đang sửa nên sẽ cúp điện từ 7h - 8h mong mọi người thông cảm', '2025-11-03 20:39:59', 'Admin', 'assets/uploads/announcements/1762177199_Screenshot 2025-11-03 201935.png'),
	(4, 'Nhắc nhở thực hiện nội quy Ký túc xá', 'Kính gửi: Toàn thể sinh viên đang lưu trú tại Ký túc xá\r\nNhằm đảm bảo môi trường học tập, sinh hoạt văn minh, an toàn và lành mạnh, Ban Quản lý Ký túc xá xin nhắc nhở một số nội quy như sau:\r\nGiữ gìn vệ sinh chung tại phòng ở, hành lang, khu vực nhà vệ sinh, khu vực sinh hoạt chung.\r\nKhông tụ tập gây ồn ào sau 22h00, không bật loa lớn, hát karaoke ảnh hưởng đến các phòng xung quanh.\r\nTuyệt đối không sử dụng bếp điện công suất lớn, bếp từ, bếp gas mini, ấm siêu tốc không đúng quy định,… trong phòng ở.\r\nKhông nuôi thú cưng trong Ký túc xá.\r\nChấp hành nghiêm túc quy định về phòng cháy chữa cháy, không hút thuốc lá trong khuôn viên Ký túc xá.\r\nĐề nghị tất cả sinh viên nghiêm túc thực hiện. Trường hợp vi phạm, Ban Quản lý sẽ xử lý theo đúng quy định hiện hành.\r\nTrân trọng./.\r\n\r\nBAN QUẢN LÝ KÝ TÚC XÁ', '2025-11-16 22:06:16', 'Manager', NULL),
	(5, 'Thông báo diễn tập/ tập huấn PCCC', 'Kính gửi: Toàn thể sinh viên đang lưu trú tại Ký túc xá\r\n\r\nNhằm nâng cao ý thức và kỹ năng Phòng cháy Chữa cháy (PCCC) cho sinh viên, Ban Quản lý Ký túc xá phối hợp với (Phòng Cảnh sát PCCC & CNCH / Phòng Công tác SV) tổ chức buổi tập huấn và diễn tập PCCC với thông tin như sau:\r\n\r\nThời gian: … giờ … phút, ngày … / … / 20…\r\n\r\nĐịa điểm tập trung: Khu vực sân trước tòa …\r\n\r\nNội dung:\r\n\r\nHướng dẫn sử dụng bình chữa cháy.\r\nXử lý các tình huống cháy nổ thường gặp tại KTX.\r\nDiễn tập thoát nạn khi có cháy.\r\nĐề nghị sinh viên sắp xếp thời gian tham gia đầy đủ, đúng giờ.\r\nTrân trọng./.', '2025-11-16 22:10:13', 'Manager', NULL);

-- Dumping structure for table quanlyktx.announcementviews
DROP TABLE IF EXISTS `announcementviews`;
CREATE TABLE IF NOT EXISTS `announcementviews` (
  `ViewID` int NOT NULL AUTO_INCREMENT,
  `AnnouncementID` int NOT NULL,
  `StudentID` int NOT NULL,
  `ViewedAt` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ViewID`),
  KEY `AnnouncementID` (`AnnouncementID`),
  KEY `StudentID` (`StudentID`),
  CONSTRAINT `announcementviews_ibfk_1` FOREIGN KEY (`AnnouncementID`) REFERENCES `announcements` (`AnnouncementID`) ON DELETE CASCADE,
  CONSTRAINT `announcementviews_ibfk_2` FOREIGN KEY (`StudentID`) REFERENCES `students` (`StudentID`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=60 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table quanlyktx.announcementviews: ~53 rows (approximately)
INSERT INTO `announcementviews` (`ViewID`, `AnnouncementID`, `StudentID`, `ViewedAt`) VALUES
	(4, 3, 5, '2025-11-23 20:12:42'),
	(5, 3, 5, '2025-11-24 18:06:56'),
	(6, 3, 5, '2025-11-24 18:07:04'),
	(7, 5, 5, '2025-11-24 18:08:26'),
	(8, 5, 5, '2025-11-24 18:09:19'),
	(9, 5, 5, '2025-11-24 18:09:31'),
	(10, 4, 5, '2025-11-24 18:36:14'),
	(11, 3, 5, '2025-11-24 18:42:18'),
	(12, 3, 5, '2025-11-24 18:42:40'),
	(13, 5, 7, '2025-11-24 21:41:41'),
	(14, 3, 7, '2025-11-24 21:41:43'),
	(15, 4, 7, '2025-11-24 21:41:45'),
	(16, 5, 7, '2025-11-25 08:35:14'),
	(17, 3, 4, '2025-11-25 08:58:47'),
	(18, 4, 4, '2025-11-25 08:58:47'),
	(19, 5, 4, '2025-11-25 08:58:47'),
	(20, 3, 4, '2025-11-25 08:58:48'),
	(21, 4, 4, '2025-11-25 08:58:48'),
	(22, 5, 4, '2025-11-25 08:58:48'),
	(23, 3, 4, '2025-11-25 09:52:17'),
	(24, 4, 4, '2025-11-25 09:52:17'),
	(25, 5, 4, '2025-11-25 09:52:17'),
	(26, 3, 4, '2025-11-25 10:14:34'),
	(27, 4, 4, '2025-11-25 10:14:34'),
	(28, 5, 4, '2025-11-25 10:14:34'),
	(29, 3, 4, '2025-11-25 10:40:13'),
	(30, 4, 4, '2025-11-25 10:40:13'),
	(31, 5, 4, '2025-11-25 10:40:13'),
	(32, 3, 4, '2025-11-24 20:12:08'),
	(33, 4, 4, '2025-11-24 20:12:08'),
	(34, 5, 4, '2025-11-24 20:12:08'),
	(35, 3, 4, '2025-11-25 12:47:50'),
	(36, 4, 4, '2025-11-25 12:47:50'),
	(37, 5, 4, '2025-11-25 12:47:50'),
	(38, 3, 4, '2025-11-25 13:31:53'),
	(39, 4, 4, '2025-11-25 13:31:53'),
	(40, 5, 4, '2025-11-25 13:31:53'),
	(41, 3, 4, '2025-11-25 15:16:17'),
	(42, 4, 4, '2025-11-25 15:16:17'),
	(43, 5, 4, '2025-11-25 15:16:17'),
	(44, 3, 4, '2025-11-25 15:17:10'),
	(45, 4, 4, '2025-11-25 15:17:10'),
	(46, 5, 4, '2025-11-25 15:17:10'),
	(47, 3, 4, '2025-11-25 15:18:19'),
	(48, 4, 4, '2025-11-25 15:18:19'),
	(49, 5, 4, '2025-11-25 15:18:19'),
	(50, 3, 4, '2025-11-25 15:22:03'),
	(51, 4, 4, '2025-11-25 15:22:03'),
	(52, 5, 4, '2025-11-25 15:22:03'),
	(53, 3, 4, '2025-11-25 15:42:27'),
	(54, 4, 4, '2025-11-25 15:42:27'),
	(55, 5, 4, '2025-11-25 15:42:27'),
	(56, 5, 8, '2025-11-25 16:36:27'),
	(57, 5, 5, '2025-11-26 13:38:44'),
	(58, 4, 5, '2025-11-26 13:38:46'),
	(59, 3, 9, '2025-11-27 12:55:30');

-- Dumping structure for table quanlyktx.buildings
DROP TABLE IF EXISTS `buildings`;
CREATE TABLE IF NOT EXISTS `buildings` (
  `BuildingID` int NOT NULL AUTO_INCREMENT,
  `BuildingName` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Floors` int DEFAULT '1',
  PRIMARY KEY (`BuildingID`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table quanlyktx.buildings: ~3 rows (approximately)
INSERT INTO `buildings` (`BuildingID`, `BuildingName`, `Description`, `Floors`) VALUES
	(1, 'A', NULL, 6),
	(3, 'B', NULL, 6),
	(5, 'C', NULL, 6);

-- Dumping structure for table quanlyktx.contractlogs
DROP TABLE IF EXISTS `contractlogs`;
CREATE TABLE IF NOT EXISTS `contractlogs` (
  `LogID` int NOT NULL AUTO_INCREMENT,
  `ContractID` int NOT NULL,
  `Action` enum('Tạo mới','Cập nhật','Gia hạn','Hủy') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `PerformedBy` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `CreatedAt` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`LogID`),
  KEY `ContractID` (`ContractID`),
  CONSTRAINT `contractlogs_ibfk_1` FOREIGN KEY (`ContractID`) REFERENCES `contracts` (`ContractID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table quanlyktx.contractlogs: ~0 rows (approximately)

-- Dumping structure for table quanlyktx.contracts
DROP TABLE IF EXISTS `contracts`;
CREATE TABLE IF NOT EXISTS `contracts` (
  `ContractID` int NOT NULL AUTO_INCREMENT,
  `StudentID` int NOT NULL,
  `RoomID` int NOT NULL,
  `StartDate` date NOT NULL,
  `EndDate` date NOT NULL,
  `Deposit` decimal(12,2) DEFAULT '0.00' COMMENT 'Tiền đặt cọc hợp đồng',
  `Status` enum('Hiệu lực','Hết hạn','Đã hủy') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Hiệu lực',
  `CreatedAt` datetime DEFAULT CURRENT_TIMESTAMP,
  `UpdatedAt` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `PaymentStatus` enum('Còn nợ','Đã thanh toán đủ') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Còn nợ',
  PRIMARY KEY (`ContractID`),
  KEY `StudentID` (`StudentID`),
  KEY `RoomID` (`RoomID`),
  CONSTRAINT `contracts_ibfk_1` FOREIGN KEY (`StudentID`) REFERENCES `students` (`StudentID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `contracts_ibfk_2` FOREIGN KEY (`RoomID`) REFERENCES `rooms` (`RoomID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table quanlyktx.contracts: ~4 rows (approximately)
INSERT INTO `contracts` (`ContractID`, `StudentID`, `RoomID`, `StartDate`, `EndDate`, `Deposit`, `Status`, `CreatedAt`, `UpdatedAt`, `PaymentStatus`) VALUES
	(7, 5, 1, '2025-11-06', '2025-12-06', 2000000.00, 'Đã hủy', '2025-11-06 19:56:15', '2025-11-14 22:19:30', 'Còn nợ'),
	(8, 4, 1, '2025-11-11', '2026-02-11', 2000000.00, 'Đã hủy', '2025-11-11 11:32:59', '2025-11-11 13:15:03', 'Còn nợ'),
	(10, 4, 1, '2025-11-17', '2026-05-17', 2200000.00, 'Hiệu lực', '2025-11-14 22:28:37', '2025-11-14 22:37:53', 'Còn nợ'),
	(11, 5, 1, '2025-12-23', '2025-12-24', 2200000.00, 'Hiệu lực', '2025-11-23 20:03:11', '2025-11-27 12:56:07', 'Còn nợ');

-- Dumping structure for table quanlyktx.faculties
DROP TABLE IF EXISTS `faculties`;
CREATE TABLE IF NOT EXISTS `faculties` (
  `FacultyID` int NOT NULL AUTO_INCREMENT,
  `FacultyCode` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `FacultyName` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `CreatedAt` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`FacultyID`),
  UNIQUE KEY `FacultyCode` (`FacultyCode`)
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table quanlyktx.faculties: ~20 rows (approximately)
INSERT INTO `faculties` (`FacultyID`, `FacultyCode`, `FacultyName`, `CreatedAt`) VALUES
	(21, 'CNTT', 'Khoa Công nghệ Thông tin', '2025-11-11 06:33:11'),
	(22, 'KT', 'Khoa Kinh tế', '2025-11-11 06:33:11'),
	(23, 'QTKD', 'Khoa Quản trị Kinh doanh', '2025-11-11 06:33:11'),
	(24, 'KTKT', 'Khoa Kỹ thuật', '2025-11-11 06:33:11'),
	(25, 'DDT', 'Khoa Điện - Điện tử', '2025-11-11 06:33:11'),
	(26, 'CK', 'Khoa Cơ khí', '2025-11-11 06:33:11'),
	(27, 'NN', 'Khoa Ngoại ngữ', '2025-11-11 06:33:11'),
	(28, 'LUAT', 'Khoa Luật', '2025-11-11 06:33:11'),
	(29, 'YD', 'Khoa Y Dược', '2025-11-11 06:33:11'),
	(30, 'SP', 'Khoa Sư phạm', '2025-11-11 06:33:11'),
	(31, 'KTRUC', 'Khoa Kiến trúc', '2025-11-11 06:33:11'),
	(32, 'MT', 'Khoa Môi trường', '2025-11-11 06:33:11'),
	(33, 'NNG', 'Khoa Nông nghiệp', '2025-11-11 06:33:11'),
	(34, 'DL', 'Khoa Du lịch', '2025-11-11 06:33:11'),
	(35, 'MTCN', 'Khoa Mỹ thuật Công nghiệp', '2025-11-11 06:33:11'),
	(36, 'TTBC', 'Khoa Truyền thông và Báo chí', '2025-11-11 06:33:11'),
	(37, 'TCNH', 'Khoa Tài chính - Ngân hàng', '2025-11-11 06:33:11'),
	(38, 'LOG', 'Khoa Logistics ', '2025-11-11 06:33:11'),
	(39, 'QTTH', 'Khoa Quốc tế học', '2025-11-11 06:33:11'),
	(40, 'KHCB', 'Khoa Khoa học Cơ bản', '2025-11-11 06:33:11');

-- Dumping structure for table quanlyktx.feedbacks
DROP TABLE IF EXISTS `feedbacks`;
CREATE TABLE IF NOT EXISTS `feedbacks` (
  `FeedbackID` int NOT NULL AUTO_INCREMENT,
  `StudentID` int NOT NULL,
  `Title` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Content` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Reply` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ImagePath` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Status` enum('Chưa xử lý','Đang xử lý','Đã xử lý') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Chưa xử lý',
  `CreatedAt` datetime DEFAULT CURRENT_TIMESTAMP,
  `UpdatedAt` datetime DEFAULT NULL,
  PRIMARY KEY (`FeedbackID`),
  KEY `StudentID` (`StudentID`),
  CONSTRAINT `feedbacks_ibfk_1` FOREIGN KEY (`StudentID`) REFERENCES `students` (`StudentID`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table quanlyktx.feedbacks: ~2 rows (approximately)
INSERT INTO `feedbacks` (`FeedbackID`, `StudentID`, `Title`, `Content`, `Reply`, `ImagePath`, `Status`, `CreatedAt`, `UpdatedAt`) VALUES
	(1, 5, 'Hỏng bồn nước', 'Bồn nước trong nhà vệ sinh bị bể, cần qua sửa gấp ạ', 'Mình đang gọi người tới sửa, bạn vui lòng chờ nha', NULL, 'Đang xử lý', '2025-11-06 14:26:05', '2025-11-26 15:25:28'),
	(3, 5, 'Không có nước', 'Nước không có để sài', '', NULL, 'Đã xử lý', '2025-11-07 07:51:34', '2025-11-14 22:11:08');

-- Dumping structure for table quanlyktx.invoicenotificationreads
DROP TABLE IF EXISTS `invoicenotificationreads`;
CREATE TABLE IF NOT EXISTS `invoicenotificationreads` (
  `ReadID` int NOT NULL AUTO_INCREMENT,
  `InvoiceID` int NOT NULL,
  `StudentID` int NOT NULL,
  `ReadAt` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ReadID`),
  UNIQUE KEY `unique_invoice_student` (`InvoiceID`,`StudentID`),
  KEY `FK_InvoiceNotificationReads_Invoices` (`InvoiceID`),
  KEY `FK_InvoiceNotificationReads_Students` (`StudentID`),
  CONSTRAINT `FK_InvoiceNotificationReads_Invoices` FOREIGN KEY (`InvoiceID`) REFERENCES `invoices` (`InvoiceID`) ON DELETE CASCADE,
  CONSTRAINT `FK_InvoiceNotificationReads_Students` FOREIGN KEY (`StudentID`) REFERENCES `students` (`StudentID`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table quanlyktx.invoicenotificationreads: ~5 rows (approximately)
INSERT INTO `invoicenotificationreads` (`ReadID`, `InvoiceID`, `StudentID`, `ReadAt`) VALUES
	(1, 4, 5, '2025-11-24 18:19:41'),
	(2, 5, 5, '2025-11-24 18:19:41'),
	(3, 6, 4, '2025-11-25 15:43:30'),
	(4, 7, 4, '2025-11-25 15:43:30'),
	(6, 8, 5, '2025-11-25 15:58:40');

-- Dumping structure for table quanlyktx.invoices
DROP TABLE IF EXISTS `invoices`;
CREATE TABLE IF NOT EXISTS `invoices` (
  `InvoiceID` int NOT NULL AUTO_INCREMENT,
  `ContractID` int NOT NULL,
  `Month` int NOT NULL,
  `Year` int NOT NULL,
  `RoomFee` decimal(10,2) DEFAULT NULL,
  `ElectricUsage` int DEFAULT NULL,
  `ElectricPrice` decimal(10,2) DEFAULT NULL,
  `WaterUsage` int DEFAULT NULL,
  `WaterPrice` decimal(10,2) DEFAULT NULL,
  `TotalAmount` decimal(10,2) DEFAULT NULL,
  `Note` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `Status` enum('Chưa thanh toán','Đã thanh toán','Quá hạn') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Chưa thanh toán',
  `UpdatedAt` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `PaidAt` datetime DEFAULT NULL,
  `CreatedAt` datetime DEFAULT CURRENT_TIMESTAMP,
  `DueDate` date DEFAULT NULL,
  `PaidDate` date DEFAULT NULL,
  PRIMARY KEY (`InvoiceID`),
  KEY `ContractID` (`ContractID`),
  CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`ContractID`) REFERENCES `contracts` (`ContractID`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table quanlyktx.invoices: ~6 rows (approximately)
INSERT INTO `invoices` (`InvoiceID`, `ContractID`, `Month`, `Year`, `RoomFee`, `ElectricUsage`, `ElectricPrice`, `WaterUsage`, `WaterPrice`, `TotalAmount`, `Note`, `Status`, `UpdatedAt`, `PaidAt`, `CreatedAt`, `DueDate`, `PaidDate`) VALUES
	(2, 7, 11, 2025, 2200000.00, 0, 3500.00, 0, 15000.00, 2200000.00, NULL, 'Đã thanh toán', NULL, '2025-11-08 11:23:01', '2025-11-07 06:41:36', '2025-11-12', NULL),
	(4, 7, 12, 2025, 2200000.00, 12, 3500.00, 12, 10000.00, 2362000.00, NULL, 'Chưa thanh toán', NULL, NULL, '2025-11-09 20:34:22', '2025-12-16', NULL),
	(5, 7, 1, 2025, 2200000.00, 0, 3500.00, 0, 10000.00, 2200000.00, NULL, 'Chưa thanh toán', NULL, NULL, '2025-11-09 21:21:00', '2026-01-16', NULL),
	(6, 8, 11, 2025, 2200000.00, 0, 3500.00, 0, 15000.00, 2200000.00, NULL, 'Quá hạn', '2025-11-26 14:49:48', NULL, '2025-11-11 11:53:41', '2025-11-16', NULL),
	(7, 10, 11, 2025, 2200000.00, 0, 3500.00, 0, 15000.00, 2200000.00, NULL, 'Quá hạn', '2025-11-26 14:49:48', NULL, '2025-11-16 21:25:11', '2025-11-21', NULL),
	(8, 11, 11, 2025, 2200000.00, 0, 3500.00, 0, 15000.00, 2200000.00, NULL, 'Chưa thanh toán', NULL, NULL, '2025-11-24 20:25:35', '2025-11-29', NULL);

-- Dumping structure for table quanlyktx.notifications
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE IF NOT EXISTS `notifications` (
  `NotificationID` int NOT NULL AUTO_INCREMENT,
  `StudentID` int NOT NULL,
  `Title` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `Message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `CreatedAt` datetime DEFAULT CURRENT_TIMESTAMP,
  `IsRead` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`NotificationID`),
  KEY `StudentID` (`StudentID`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`StudentID`) REFERENCES `students` (`StudentID`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table quanlyktx.notifications: ~3 rows (approximately)
INSERT INTO `notifications` (`NotificationID`, `StudentID`, `Title`, `Message`, `CreatedAt`, `IsRead`) VALUES
	(3, 5, 'Phản ánh mới từ Người dùng', 'a', '2025-11-09 22:05:51', 1),
	(4, 4, 'Hóa đơn tháng 11/2025 bị quá hạn', 'Xin chào Admin,<br>\r\n        Hóa đơn phòng <strong>A101</strong> tháng <strong>11/2025</strong> đã <span style=\'color:red;\'>quá hạn thanh toán</span>.<br>\r\n        Vui lòng thanh toán sớm nhất để tránh bị xử lý theo quy định.<br><br>\r\n        — Hệ thống Ký túc xá', '2025-11-26 14:49:48', 0),
	(5, 4, 'Hóa đơn tháng 11/2025 bị quá hạn', 'Xin chào Admin,<br>\r\n        Hóa đơn phòng <strong>A101</strong> tháng <strong>11/2025</strong> đã <span style=\'color:red;\'>quá hạn thanh toán</span>.<br>\r\n        Vui lòng thanh toán sớm nhất để tránh bị xử lý theo quy định.<br><br>\r\n        — Hệ thống Ký túc xá', '2025-11-26 14:49:49', 0);

-- Dumping structure for table quanlyktx.password_resets
DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE IF NOT EXISTS `password_resets` (
  `ID` int NOT NULL AUTO_INCREMENT,
  `UserID` int NOT NULL,
  `Token` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ExpiresAt` datetime NOT NULL,
  PRIMARY KEY (`ID`),
  KEY `UserID` (`UserID`),
  CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`UserID`) REFERENCES `users` (`UserID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table quanlyktx.password_resets: ~0 rows (approximately)

-- Dumping structure for table quanlyktx.payments
DROP TABLE IF EXISTS `payments`;
CREATE TABLE IF NOT EXISTS `payments` (
  `PaymentID` int NOT NULL AUTO_INCREMENT,
  `StudentID` int NOT NULL,
  `RoomID` int NOT NULL,
  `Amount` decimal(12,0) NOT NULL,
  `Method` enum('Tiền mặt','Chuyển khoản') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Status` enum('Chờ xác nhận','Đã xác nhận','Từ chối') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Chờ xác nhận',
  `TransactionCode` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `CreatedAt` datetime DEFAULT CURRENT_TIMESTAMP,
  `PaidAt` datetime DEFAULT NULL,
  PRIMARY KEY (`PaymentID`),
  UNIQUE KEY `TransactionCode` (`TransactionCode`),
  KEY `StudentID` (`StudentID`),
  KEY `RoomID` (`RoomID`),
  CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`StudentID`) REFERENCES `students` (`StudentID`),
  CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`RoomID`) REFERENCES `rooms` (`RoomID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table quanlyktx.payments: ~0 rows (approximately)

-- Dumping structure for table quanlyktx.postcomments
DROP TABLE IF EXISTS `postcomments`;
CREATE TABLE IF NOT EXISTS `postcomments` (
  `CommentID` int NOT NULL AUTO_INCREMENT,
  `PostID` int NOT NULL,
  `StudentID` int NOT NULL,
  `Content` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `CreatedAt` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`CommentID`),
  KEY `PostID` (`PostID`),
  KEY `StudentID` (`StudentID`),
  CONSTRAINT `postcomments_ibfk_1` FOREIGN KEY (`PostID`) REFERENCES `posts` (`PostID`),
  CONSTRAINT `postcomments_ibfk_2` FOREIGN KEY (`StudentID`) REFERENCES `students` (`StudentID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table quanlyktx.postcomments: ~0 rows (approximately)

-- Dumping structure for table quanlyktx.postlikes
DROP TABLE IF EXISTS `postlikes`;
CREATE TABLE IF NOT EXISTS `postlikes` (
  `LikeID` int NOT NULL AUTO_INCREMENT,
  `PostID` int NOT NULL,
  `StudentID` int NOT NULL,
  `Reaction` enum('Like','Love','Haha','Wow','Sad','Angry') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Like',
  `CreatedAt` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`LikeID`),
  UNIQUE KEY `PostID` (`PostID`,`StudentID`),
  KEY `StudentID` (`StudentID`),
  CONSTRAINT `postlikes_ibfk_1` FOREIGN KEY (`PostID`) REFERENCES `posts` (`PostID`),
  CONSTRAINT `postlikes_ibfk_2` FOREIGN KEY (`StudentID`) REFERENCES `students` (`StudentID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table quanlyktx.postlikes: ~0 rows (approximately)

-- Dumping structure for table quanlyktx.posts
DROP TABLE IF EXISTS `posts`;
CREATE TABLE IF NOT EXISTS `posts` (
  `PostID` int NOT NULL AUTO_INCREMENT,
  `StudentID` int NOT NULL,
  `Content` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ImagePath` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Visibility` enum('Công khai','Chỉ bạn bè','Riêng tư') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Công khai',
  `CreatedAt` datetime DEFAULT CURRENT_TIMESTAMP,
  `UpdatedAt` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`PostID`),
  KEY `StudentID` (`StudentID`),
  CONSTRAINT `posts_ibfk_1` FOREIGN KEY (`StudentID`) REFERENCES `students` (`StudentID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table quanlyktx.posts: ~0 rows (approximately)

-- Dumping structure for table quanlyktx.roomrequests
DROP TABLE IF EXISTS `roomrequests`;
CREATE TABLE IF NOT EXISTS `roomrequests` (
  `RequestID` int NOT NULL AUTO_INCREMENT,
  `StudentID` int NOT NULL,
  `RoomID` int NOT NULL,
  `DesiredFrom` date DEFAULT NULL,
  `Note` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Status` enum('Chờ duyệt','Đã duyệt','Từ chối') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Chờ duyệt',
  `ReviewedBy` int DEFAULT NULL,
  `ReviewedAt` datetime DEFAULT NULL,
  `CreatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `CheckInDate` date DEFAULT NULL,
  `CheckOutDate` date DEFAULT NULL,
  PRIMARY KEY (`RequestID`),
  KEY `idx_rr_student` (`StudentID`),
  KEY `idx_rr_room` (`RoomID`),
  KEY `idx_rr_status` (`Status`),
  CONSTRAINT `fk_rr_room` FOREIGN KEY (`RoomID`) REFERENCES `rooms` (`RoomID`),
  CONSTRAINT `fk_rr_student` FOREIGN KEY (`StudentID`) REFERENCES `students` (`StudentID`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table quanlyktx.roomrequests: ~2 rows (approximately)
INSERT INTO `roomrequests` (`RequestID`, `StudentID`, `RoomID`, `DesiredFrom`, `Note`, `Status`, `ReviewedBy`, `ReviewedAt`, `CreatedAt`, `UpdatedAt`, `CheckInDate`, `CheckOutDate`) VALUES
	(1, 5, 1, '2025-11-24', '', 'Từ chối', NULL, NULL, '2025-11-23 06:33:16', '2025-11-23 19:52:23', '2025-11-24', '2025-12-24'),
	(2, 5, 1, '2025-12-23', '', 'Đã duyệt', 24, '2025-11-23 20:03:11', '2025-11-23 19:53:09', '2025-11-23 20:03:11', '2025-12-23', '2025-12-24');

-- Dumping structure for table quanlyktx.rooms
DROP TABLE IF EXISTS `rooms`;
CREATE TABLE IF NOT EXISTS `rooms` (
  `RoomID` int NOT NULL AUTO_INCREMENT,
  `BuildingID` int NOT NULL,
  `RoomNumber` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Capacity` int NOT NULL,
  `CurrentOccupants` int DEFAULT '0',
  `RoomType` enum('Nam','Nữ','Khác') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `RoomPrice` decimal(10,2) NOT NULL,
  `Status` enum('Trống','Đầy','Bảo trì') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Trống',
  `ImagePath` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Gallery` json DEFAULT NULL,
  `Description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `Amenities` json DEFAULT NULL,
  `CreatedAt` datetime DEFAULT CURRENT_TIMESTAMP,
  `UpdatedAt` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`RoomID`),
  UNIQUE KEY `uq_rooms_building_room` (`BuildingID`,`RoomNumber`),
  CONSTRAINT `rooms_ibfk_1` FOREIGN KEY (`BuildingID`) REFERENCES `buildings` (`BuildingID`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table quanlyktx.rooms: ~15 rows (approximately)
INSERT INTO `rooms` (`RoomID`, `BuildingID`, `RoomNumber`, `Capacity`, `CurrentOccupants`, `RoomType`, `RoomPrice`, `Status`, `ImagePath`, `Gallery`, `Description`, `Amenities`, `CreatedAt`, `UpdatedAt`) VALUES
	(1, 1, 'A101', 4, 2, 'Nam', 700000.00, 'Trống', 'assets/img/rooms/room_1_d403f0bc.webp', NULL, '', '["wifi", "aircon", "fridge", "tv", "bathroom", "balcony", "windown"]', '2025-11-06 18:14:48', '2025-12-04 12:46:38'),
	(2, 3, 'B101', 4, 0, 'Nữ', 700000.00, 'Trống', NULL, NULL, '', '["wifi", "aircon", "fridge", "tv", "bathroom", "balcony", "windown"]', '2025-11-06 18:38:16', '2025-11-26 12:59:00'),
	(3, 1, 'A102', 2, 0, 'Nam', 1200000.00, 'Trống', 'assets/img/rooms/room_3_626c98f3.webp', NULL, '', '["wifi", "aircon", "fridge", "tv", "bathroom", "balcony"]', '2025-11-25 12:18:36', '2025-11-26 13:02:01'),
	(4, 5, 'C101', 1, 0, 'Nữ', 2000000.00, 'Trống', 'assets/img/rooms/room_4_c3ff8e53.webp', NULL, '', '["wifi", "aircon", "fridge", "tv"]', '2025-11-26 13:16:49', '2025-11-26 13:18:22'),
	(5, 1, 'A104', 1, 0, 'Nam', 2000000.00, 'Trống', 'assets/img/rooms/room_5_a6d970c5.webp', NULL, '', '["wifi", "aircon", "fridge", "tv", "bathroom", "balcony"]', '2025-11-26 13:19:04', '2025-11-26 13:19:15'),
	(6, 1, 'A013', 4, 0, 'Nữ', 700000.00, 'Trống', 'assets/img/rooms/main_491e0d69aa4e.jpg', NULL, '', '["wifi", "aircon", "fridge", "tv", "bathroom"]', '2025-11-27 10:38:40', '2025-11-27 10:38:40'),
	(7, 3, 'B102', 2, 0, 'Nam', 1200000.00, 'Trống', 'assets/img/rooms/main_3de57cbe4bef.webp', NULL, '', '["wifi", "aircon", "fridge", "tv", "bathroom"]', '2025-11-27 10:40:01', '2025-11-27 10:40:01'),
	(8, 5, 'C102', 1, 0, 'Nữ', 2000000.00, 'Trống', 'assets/img/rooms/main_da02d938941a.webp', NULL, '', '["wifi", "aircon", "fridge", "tv", "bathroom", "balcony"]', '2025-11-27 10:41:34', '2025-11-27 10:41:34'),
	(9, 5, 'C103', 1, 0, 'Nam', 2000000.00, 'Trống', 'assets/img/rooms/main_d1e352b45473.webp', NULL, '', '["wifi", "aircon", "fridge", "tv", "bathroom"]', '2025-11-27 10:41:55', '2025-11-27 10:41:55'),
	(10, 3, 'B103', 1, 0, 'Nam', 1200000.00, 'Trống', 'assets/img/rooms/main_749ed3db2bf6.webp', NULL, '', '["wifi", "aircon", "fridge", "tv", "bathroom"]', '2025-11-27 10:42:42', '2025-11-27 10:42:42'),
	(11, 3, 'B104', 4, 0, 'Nam', 700000.00, 'Trống', 'assets/img/rooms/main_6b1ab1c02ecd.jpg', NULL, '', '["wifi", "aircon", "fridge", "tv"]', '2025-11-27 10:44:14', '2025-11-27 10:44:14'),
	(12, 5, 'C104', 4, 0, 'Nữ', 700000.00, 'Trống', 'assets/img/rooms/main_52a984629684.jpg', NULL, '', '["wifi", "aircon", "fridge", "tv", "bathroom"]', '2025-11-27 10:44:47', '2025-11-27 10:44:47'),
	(13, 5, 'C105', 4, 0, 'Nữ', 700000.00, 'Trống', 'assets/img/rooms/main_81ef93248c51.jpg', NULL, '', '["wifi", "aircon", "bathroom"]', '2025-11-27 10:45:25', '2025-11-27 10:45:25'),
	(14, 1, 'A103', 2, 0, 'Nam', 1200000.00, 'Trống', 'assets/img/rooms/main_79cc66b4547e.webp', NULL, '', '["wifi", "aircon", "bathroom"]', '2025-11-27 10:46:00', '2025-11-27 10:46:00'),
	(15, 3, 'B105', 4, 0, 'Nam', 700000.00, 'Trống', 'assets/img/rooms/main_4698989f93f1.webp', '["assets/img/rooms/gallery_16e5f1a72ef3.webp"]', '', '["wifi", "aircon", "bathroom", "balcony"]', '2025-11-27 10:46:34', '2025-11-27 10:46:34');

-- Dumping structure for table quanlyktx.students
DROP TABLE IF EXISTS `students`;
CREATE TABLE IF NOT EXISTS `students` (
  `StudentID` int NOT NULL AUTO_INCREMENT,
  `UserID` int DEFAULT NULL,
  `StudentCode` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `FullName` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `Email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Gender` enum('Nam','Nữ','Khác') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `BirthDate` date DEFAULT NULL,
  `CitizenID` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Hometown` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `Address` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `Phone` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ClassName` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `CourseYear` varchar(9) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Avatar` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `EmergencyContact` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `CheckInDate` date DEFAULT NULL,
  `CheckOutDate` date DEFAULT NULL,
  `IsInDorm` tinyint(1) DEFAULT '1',
  `CreatedAt` datetime DEFAULT CURRENT_TIMESTAMP,
  `UpdatedAt` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `FacultyID` int DEFAULT NULL,
  PRIMARY KEY (`StudentID`),
  UNIQUE KEY `StudentCode` (`StudentCode`),
  UNIQUE KEY `UserID` (`UserID`),
  KEY `fk_faculty` (`FacultyID`),
  CONSTRAINT `fk_faculty` FOREIGN KEY (`FacultyID`) REFERENCES `faculties` (`FacultyID`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `students_ibfk_1` FOREIGN KEY (`UserID`) REFERENCES `users` (`UserID`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table quanlyktx.students: ~6 rows (approximately)
INSERT INTO `students` (`StudentID`, `UserID`, `StudentCode`, `FullName`, `Email`, `Gender`, `BirthDate`, `CitizenID`, `Hometown`, `Address`, `Phone`, `ClassName`, `CourseYear`, `Avatar`, `EmergencyContact`, `CheckInDate`, `CheckOutDate`, `IsInDorm`, `CreatedAt`, `UpdatedAt`, `FacultyID`) VALUES
	(4, 24, 'SV024', 'Admin', '123@gmail.com', 'Nam', NULL, NULL, NULL, NULL, '123456789', NULL, NULL, '/assets/img/avatars/avatar_SV024_1762417829.jpg', NULL, NULL, NULL, 1, '2025-11-01 19:37:18', '2025-11-25 08:49:24', 21),
	(5, 25, 'SV025', 'Người dùng', '123@gmail.com', 'Nam', '2004-08-07', '6600000001', 'Gia Lai', 'Thôn 5', '09358111111', '22DTHA2', '2022', '/assets/img/avatars/avatar_SV025_1762417433.jpg', '0935818222', NULL, NULL, 1, '2025-11-06 14:25:23', '2025-11-27 12:56:07', 38),
	(6, 28, 'SV026', 'Người dùng nữ', '112@gmail.com', 'Nữ', '2005-08-13', NULL, NULL, NULL, NULL, NULL, NULL, '/assets/img/avatars/avatar_SV026_1762870539.jpg', NULL, NULL, NULL, 0, '2025-11-11 14:09:18', '2025-11-25 08:49:24', 21),
	(7, 30, 'SV000027', 'Người dùng 3', '123245@gmail.com', 'Nam', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2025-11-23 17:10:17', '2025-11-25 08:49:24', NULL),
	(8, 31, 'SV000028', 'Tống Văn Hoàng', NULL, 'Nam', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2025-11-25 16:35:50', '2025-11-25 16:35:50', NULL),
	(9, 37, 'SV000029', 'Lee Sang Hyeok', 'phmphc591@gmail.com', 'Nam', '2004-02-27', '080204008321', 'Long An', 'Nhi Thanh', '0332987406', '22DTHA2', '2022', 'stu_9_1764331755.jpg', 'Nhan 0912381274', NULL, NULL, 1, '2025-11-27 10:56:41', '2025-11-28 19:09:15', 21);

-- Dumping structure for table quanlyktx.systemlogs
DROP TABLE IF EXISTS `systemlogs`;
CREATE TABLE IF NOT EXISTS `systemlogs` (
  `LogID` int NOT NULL AUTO_INCREMENT,
  `UserID` int DEFAULT NULL,
  `Action` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Module` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `LogType` enum('activity','history','system') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'activity',
  `IPAddress` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `UserAgent` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `CreatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`LogID`),
  KEY `idx_user` (`UserID`),
  KEY `idx_type_created` (`LogType`,`CreatedAt`),
  CONSTRAINT `fk_systemlogs_user` FOREIGN KEY (`UserID`) REFERENCES `users` (`UserID`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=159 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table quanlyktx.systemlogs: ~151 rows (approximately)
INSERT INTO `systemlogs` (`LogID`, `UserID`, `Action`, `Module`, `Description`, `LogType`, `IPAddress`, `UserAgent`, `CreatedAt`) VALUES
	(1, NULL, 'Login', 'Auth', 'User đăng nhập thành công', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 10:05:06'),
	(2, NULL, 'Login', 'Auth', 'Đăng nhập hệ thống', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-24 20:09:37'),
	(3, NULL, 'Login', 'Auth', 'Đăng nhập hệ thống', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-24 20:09:40'),
	(4, 24, 'Create invoice', 'Invoices', 'Tạo hóa đơn ID= cho hợp đồng #', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-24 20:25:42'),
	(5, 24, 'Update student', 'Students', 'Cập nhật hồ sơ sinh viên ID=', 'history', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-24 20:25:50'),
	(6, 24, 'Create invoice', 'Invoices', 'Tạo hóa đơn ID= cho hợp đồng #', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-24 20:26:01'),
	(7, 24, 'Create contract', 'Contracts', 'Tạo hợp đồng cho StudentID=, RoomID=', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-24 20:26:03'),
	(8, 24, 'Update student', 'Students', 'Cập nhật hồ sơ sinh viên ID=', 'history', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-24 20:28:58'),
	(9, NULL, 'Login', 'Auth', 'Đăng nhập hệ thống', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-24 20:30:10'),
	(10, NULL, 'Login', 'Auth', 'Đăng nhập hệ thống', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-24 20:30:15'),
	(11, NULL, 'Login', 'Auth', 'Đăng nhập hệ thống', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-24 20:30:54'),
	(12, NULL, 'Login', 'Auth', 'Đăng nhập hệ thống', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-24 20:31:25'),
	(13, NULL, 'Login', 'Auth', 'Đăng nhập hệ thống', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-24 20:31:25'),
	(14, NULL, 'Login', 'Auth', 'Đăng nhập hệ thống', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-24 20:31:55'),
	(15, NULL, 'Login', 'Auth', 'Đăng nhập hệ thống', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 11:45:42'),
	(16, NULL, 'Login', 'Auth', 'Đăng nhập hệ thống', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 12:16:54'),
	(17, NULL, 'Login', 'Auth', 'Đăng nhập hệ thống', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 12:16:56'),
	(18, 24, 'Update student', 'Students', 'Cập nhật hồ sơ sinh viên ID=', 'history', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 12:17:03'),
	(19, 24, 'Create room', 'Rooms', 'Thêm phòng mới ID=3 - Số phòng: A102 (form: A102)', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 12:18:36'),
	(20, 24, 'Update student', 'Students', 'Cập nhật hồ sơ sinh viên ID=', 'history', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 12:19:07'),
	(21, 24, 'Create invoice', 'Invoices', 'Tạo hóa đơn ID= cho hợp đồng #', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 12:20:08'),
	(22, 24, 'Update student', 'Students', 'Cập nhật hồ sơ sinh viên ID=', 'history', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 12:20:31'),
	(23, 24, 'Update student', 'Students', 'Cập nhật hồ sơ sinh viên ID=', 'history', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 12:21:27'),
	(24, 24, 'Create contract', 'Contracts', 'Tạo hợp đồng cho StudentID=, RoomID=', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 12:34:13'),
	(25, 24, 'View bills', 'Bills', 'Sinh viên xem danh sách hóa đơn', 'history', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 12:37:25'),
	(26, 24, 'View bills', 'Bills', 'Sinh viên xem danh sách hóa đơn', 'history', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 12:37:29'),
	(27, 24, 'View bills', 'Bills', 'Sinh viên xem danh sách hóa đơn', 'history', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 12:38:09'),
	(28, 24, 'View bills', 'Bills', 'Sinh viên xem danh sách hóa đơn', 'history', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 12:38:12'),
	(29, 24, 'Update student profile', 'Students', 'Sinh viên tự cập nhật hồ sơ của mình', 'history', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 12:39:37'),
	(30, 24, 'Update student profile', 'Students', 'Sinh viên tự cập nhật hồ sơ của mình', 'history', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 12:39:44'),
	(31, NULL, 'Login', 'Auth', 'Đăng nhập hệ thống', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 13:47:35'),
	(32, NULL, 'Login', 'Auth', 'Đăng nhập hệ thống', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 13:47:40'),
	(33, NULL, 'Login', 'Auth', 'Đăng nhập hệ thống', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 13:47:46'),
	(34, NULL, 'Login', 'Auth', 'Đăng nhập hệ thống', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 13:48:09'),
	(35, NULL, 'Login', 'Auth', 'Đăng nhập hệ thống', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 13:48:48'),
	(36, NULL, 'Login', 'Auth', 'Đăng nhập hệ thống', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 13:48:51'),
	(37, NULL, 'Login', 'Auth', 'Đăng nhập hệ thống', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 13:48:56'),
	(38, NULL, 'Login', 'Auth', 'Đăng nhập hệ thống', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 13:49:04'),
	(39, NULL, 'Login', 'Auth', 'Đăng nhập hệ thống', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 15:30:53'),
	(40, NULL, 'Login', 'Auth', 'Đăng nhập hệ thống', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 15:31:00'),
	(41, NULL, 'Login', 'Auth', 'Đăng nhập hệ thống', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 15:31:04'),
	(42, NULL, 'Login', 'Auth', 'Đăng nhập hệ thống', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 15:31:40'),
	(43, NULL, 'Login', 'Auth', 'Đăng nhập hệ thống', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 15:31:42'),
	(44, NULL, 'Login', 'Auth', 'Đăng nhập hệ thống', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 15:32:03'),
	(45, NULL, 'Login', 'Auth', 'Đăng nhập hệ thống', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 15:32:09'),
	(46, NULL, 'Login', 'Auth', 'Đăng nhập hệ thống', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 15:32:37'),
	(47, NULL, 'Login', 'Auth', 'Đăng nhập hệ thống', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 15:32:41'),
	(48, NULL, 'Login', 'Auth', 'Đăng nhập hệ thống', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 15:39:46'),
	(49, NULL, 'Login', 'Auth', 'Đăng nhập hệ thống', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 15:39:50'),
	(50, NULL, 'Login', 'Auth', 'Đăng nhập hệ thống', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 15:39:54'),
	(51, NULL, 'Login', 'Auth', 'Đăng nhập hệ thống', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 15:43:31'),
	(52, NULL, 'Login', 'Auth', 'Đăng nhập hệ thống', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 15:43:37'),
	(53, NULL, 'Login', 'Auth', 'Đăng nhập hệ thống', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 15:45:10'),
	(54, NULL, 'Login', 'Auth', 'Đăng nhập hệ thống', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 15:45:14'),
	(55, NULL, 'Login', 'Auth', 'Đăng nhập hệ thống', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 15:45:16'),
	(56, NULL, 'Login', 'Auth', 'Đăng nhập hệ thống', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 15:51:24'),
	(57, NULL, 'Login', 'Auth', 'Đăng nhập hệ thống', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 15:51:29'),
	(58, NULL, 'Login', 'Auth', 'Đăng nhập hệ thống', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 16:00:41'),
	(59, NULL, 'Login', 'Auth', 'Đăng nhập hệ thống', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 16:01:58'),
	(60, NULL, 'Login', 'Auth', 'Đăng nhập hệ thống', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 16:01:59'),
	(61, NULL, 'Login', 'Auth', 'Đăng nhập hệ thống', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 16:09:26'),
	(62, NULL, 'Login', 'Auth', 'Đăng nhập hệ thống', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 16:11:21'),
	(63, NULL, 'Login', 'Auth', 'Đăng nhập hệ thống', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 16:14:11'),
	(64, NULL, 'Login', 'Auth', 'Đăng nhập hệ thống', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 16:14:42'),
	(65, NULL, 'Login', 'Auth', 'Đăng nhập hệ thống', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 16:16:07'),
	(66, NULL, 'Login', 'Auth', 'Đăng nhập hệ thống', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 16:18:43'),
	(67, NULL, 'Login', 'Auth', 'Đăng nhập hệ thống', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 16:20:35'),
	(68, NULL, 'Login', 'Auth', 'Đăng nhập hệ thống', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 16:25:48'),
	(69, NULL, 'Login', 'Auth', 'Đăng nhập hệ thống', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 16:27:11'),
	(70, NULL, 'Login', 'Auth', 'Đăng nhập hệ thống', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 16:30:08'),
	(71, NULL, 'Login', 'Auth', 'Đăng nhập hệ thống', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 16:35:37'),
	(72, 31, 'View bills', 'Bills', 'Sinh viên xem danh sách hóa đơn', 'history', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 22:09:31'),
	(73, NULL, 'Login', 'Auth', 'Đăng nhập hệ thống', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 22:15:34'),
	(74, NULL, 'Login', 'Auth', 'Đăng nhập hệ thống', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 22:15:37'),
	(75, NULL, 'Login', 'Auth', 'Đăng nhập hệ thống', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 22:15:39'),
	(76, 24, 'PHP Error', 'System', '[PHP ERROR] Undefined variable: abcxyz in C:\\laragon\\www\\WEBQuanLyKyTucXa\\includes\\error_handler.php at line 105', 'system', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 22:40:16'),
	(77, 24, 'PHP Error', 'System', '[PHP ERROR] Undefined variable: abcxyz in C:\\laragon\\www\\WEBQuanLyKyTucXa\\includes\\error_handler.php at line 105', 'system', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 22:40:29'),
	(78, 24, 'PHP Error', 'System', '[PHP ERROR] Undefined variable: unpaidChangePercent in C:\\laragon\\www\\WEBQuanLyKyTucXa\\modules\\admin\\dashboard.php at line 119', 'system', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 22:52:35'),
	(79, 24, 'PHP Error', 'System', '[PHP ERROR] Undefined variable: unpaidChangePercent in C:\\laragon\\www\\WEBQuanLyKyTucXa\\modules\\admin\\dashboard.php at line 119', 'system', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 22:52:46'),
	(80, 24, 'PHP Error', 'System', '[PHP ERROR] Undefined variable: studentChangePercent in C:\\laragon\\www\\WEBQuanLyKyTucXa\\modules\\admin\\dashboard.php at line 118', 'system', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 22:55:41'),
	(81, 24, 'PHP Error', 'System', '[PHP ERROR] Undefined variable: studentChangePercent in C:\\laragon\\www\\WEBQuanLyKyTucXa\\modules\\admin\\dashboard.php at line 118', 'system', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 22:55:46'),
	(82, 24, 'PHP Error', 'System', '[PHP ERROR] Undefined variable: studentChangePercent in C:\\laragon\\www\\WEBQuanLyKyTucXa\\modules\\admin\\dashboard.php at line 118', 'system', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-25 22:56:26'),
	(83, 24, 'Exception', 'System', '[EXCEPTION] Column \'CreatedAt\' in field list is ambiguous in C:\\laragon\\www\\WEBQuanLyKyTucXa\\modules\\user\\dashboard.php at line 111', 'system', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-26 12:54:59'),
	(84, 24, 'Exception', 'System', '[EXCEPTION] Column \'CreatedAt\' in field list is ambiguous in C:\\laragon\\www\\WEBQuanLyKyTucXa\\modules\\user\\dashboard.php at line 111', 'system', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-26 12:55:03'),
	(85, 24, 'Exception', 'System', '[EXCEPTION] Column \'CreatedAt\' in field list is ambiguous in C:\\laragon\\www\\WEBQuanLyKyTucXa\\modules\\user\\dashboard.php at line 111', 'system', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-26 12:55:11'),
	(86, 24, 'Exception', 'System', '[EXCEPTION] Column \'CreatedAt\' in field list is ambiguous in C:\\laragon\\www\\WEBQuanLyKyTucXa\\modules\\user\\dashboard.php at line 111', 'system', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-26 12:55:13'),
	(87, 24, 'Update room', 'Rooms', 'Cập nhật phòng ID=1', 'history', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-26 12:58:26'),
	(88, 24, 'Update room', 'Rooms', 'Cập nhật phòng ID=3', 'history', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-26 12:58:48'),
	(89, 24, 'Update room', 'Rooms', 'Cập nhật phòng ID=2', 'history', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-26 12:59:00'),
	(90, 24, 'Update room', 'Rooms', 'Cập nhật phòng ID=3', 'history', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-26 13:02:01'),
	(91, 24, 'Create room', 'Rooms', 'Thêm phòng mới ID=4 - Số phòng: C101 (form: C101)', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-26 13:16:49'),
	(92, 24, 'Update room', 'Rooms', 'Cập nhật phòng ID=4', 'history', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-26 13:18:22'),
	(93, 24, 'Create room', 'Rooms', 'Thêm phòng mới ID=5 - Số phòng: A104 (form: A104)', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-26 13:19:04'),
	(94, 24, 'Update room', 'Rooms', 'Cập nhật phòng ID=5', 'history', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-26 13:19:15'),
	(95, 24, 'Exception', 'System', '[EXCEPTION] Unknown column \'s.Faculty\' in \'field list\' in C:\\laragon\\www\\WEBQuanLyKyTucXa\\modules\\user\\rooms\\rooms.php at line 108', 'system', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-26 13:20:24'),
	(96, 24, 'Exception', 'System', '[EXCEPTION] Column \'CreatedAt\' in field list is ambiguous in C:\\laragon\\www\\WEBQuanLyKyTucXa\\modules\\user\\dashboard.php at line 111', 'system', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-26 13:20:25'),
	(97, 24, 'Exception', 'System', '[EXCEPTION] Column \'CreatedAt\' in field list is ambiguous in C:\\laragon\\www\\WEBQuanLyKyTucXa\\modules\\user\\dashboard.php at line 111', 'system', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-26 13:20:30'),
	(98, 25, 'Exception', 'System', '[EXCEPTION] Unknown column \'s.Faculty\' in \'field list\' in C:\\laragon\\www\\WEBQuanLyKyTucXa\\modules\\user\\rooms\\rooms.php at line 108', 'system', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-26 13:20:42'),
	(99, 25, 'Exception', 'System', '[EXCEPTION] Column \'CreatedAt\' in field list is ambiguous in C:\\laragon\\www\\WEBQuanLyKyTucXa\\modules\\user\\dashboard.php at line 111', 'system', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-26 13:20:44'),
	(100, 25, 'Exception', 'System', '[EXCEPTION] Column \'CreatedAt\' in field list is ambiguous in C:\\laragon\\www\\WEBQuanLyKyTucXa\\modules\\user\\dashboard.php at line 111', 'system', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-26 13:20:45'),
	(101, 25, 'Exception', 'System', '[EXCEPTION] Column \'CreatedAt\' in field list is ambiguous in C:\\laragon\\www\\WEBQuanLyKyTucXa\\modules\\user\\dashboard.php at line 111', 'system', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-26 13:20:47'),
	(102, 25, 'Exception', 'System', '[EXCEPTION] Column \'CreatedAt\' in field list is ambiguous in C:\\laragon\\www\\WEBQuanLyKyTucXa\\modules\\user\\dashboard.php at line 111', 'system', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-26 13:20:55'),
	(103, 25, 'Exception', 'System', '[EXCEPTION] Column \'CreatedAt\' in field list is ambiguous in C:\\laragon\\www\\WEBQuanLyKyTucXa\\modules\\user\\dashboard.php at line 111', 'system', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-26 13:21:14'),
	(104, 24, 'Exception', 'System', '[EXCEPTION] Unknown column \'f.DateSubmitted\' in \'field list\' in C:\\laragon\\www\\WEBQuanLyKyTucXa\\modules\\user\\dashboard.php at line 121', 'system', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-26 13:24:37'),
	(105, 24, 'Exception', 'System', '[EXCEPTION] Unknown column \'f.DateSubmitted\' in \'field list\' in C:\\laragon\\www\\WEBQuanLyKyTucXa\\modules\\user\\dashboard.php at line 121', 'system', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-26 13:24:40'),
	(106, 24, 'Exception', 'System', '[EXCEPTION] Unknown column \'s.Faculty\' in \'field list\' in C:\\laragon\\www\\WEBQuanLyKyTucXa\\modules\\user\\rooms\\rooms.php at line 108', 'system', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-26 13:24:40'),
	(107, 24, 'Exception', 'System', '[EXCEPTION] Unknown column \'s.Faculty\' in \'field list\' in C:\\laragon\\www\\WEBQuanLyKyTucXa\\modules\\user\\rooms\\rooms.php at line 108', 'system', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-26 13:24:40'),
	(108, 24, 'Exception', 'System', '[EXCEPTION] Unknown column \'f.DateSubmitted\' in \'field list\' in C:\\laragon\\www\\WEBQuanLyKyTucXa\\modules\\user\\dashboard.php at line 121', 'system', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-26 13:24:42'),
	(109, 24, 'Exception', 'System', '[EXCEPTION] Unknown column \'f.DateSubmitted\' in \'field list\' in C:\\laragon\\www\\WEBQuanLyKyTucXa\\modules\\user\\dashboard.php at line 121', 'system', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-26 13:24:50'),
	(110, 24, 'Exception', 'System', '[EXCEPTION] Unknown column \'f.DateSubmitted\' in \'field list\' in C:\\laragon\\www\\WEBQuanLyKyTucXa\\modules\\user\\dashboard.php at line 121', 'system', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-26 13:24:59'),
	(111, 24, 'Exception', 'System', '[EXCEPTION] Unknown column \'s.Faculty\' in \'field list\' in C:\\laragon\\www\\WEBQuanLyKyTucXa\\modules\\user\\rooms\\rooms.php at line 108', 'system', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-26 13:26:27'),
	(112, 24, 'Exception', 'System', '[EXCEPTION] Unknown column \'DateSubmitted\' in \'field list\' in C:\\laragon\\www\\WEBQuanLyKyTucXa\\modules\\user\\dashboard.php at line 116', 'system', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-26 13:26:27'),
	(113, 24, 'Exception', 'System', '[EXCEPTION] Unknown column \'s.Faculty\' in \'field list\' in C:\\laragon\\www\\WEBQuanLyKyTucXa\\modules\\user\\rooms\\rooms.php at line 108', 'system', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-26 13:27:17'),
	(114, 25, 'View bills', 'Bills', 'Sinh viên xem danh sách hóa đơn', 'history', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-26 13:37:44'),
	(115, 24, 'Exception', 'System', '[EXCEPTION] You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use near \'IF NOT EXISTS DueDate DATE NULL AFTER CreatedAt\' at line 1 in C:\\laragon\\www\\WEBQuanLyKyTucXa\\modules\\staff\\invoices\\invoice_list.php at line 11', 'system', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-26 14:37:33'),
	(116, 24, 'Exception', 'System', '[EXCEPTION] You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use near \'IF NOT EXISTS DueDate DATE NULL AFTER CreatedAt\' at line 1 in C:\\laragon\\www\\WEBQuanLyKyTucXa\\modules\\staff\\invoices\\invoice_list.php at line 11', 'system', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-26 14:37:35'),
	(117, 24, 'Exception', 'System', '[EXCEPTION] You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use near \'IF NOT EXISTS DueDate DATE NULL AFTER CreatedAt\' at line 1 in C:\\laragon\\www\\WEBQuanLyKyTucXa\\modules\\staff\\invoices\\invoice_list.php at line 11', 'system', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-26 14:38:16'),
	(118, 24, 'Exception', 'System', '[EXCEPTION] Duplicate column name \'DueDate\' in C:\\laragon\\www\\WEBQuanLyKyTucXa\\modules\\staff\\invoices\\invoice_list.php at line 11', 'system', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-26 14:39:43'),
	(119, 24, 'Exception', 'System', '[EXCEPTION] You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use near \'IF NOT EXISTS DueDate DATE NULL AFTER CreatedAt\' at line 1 in C:\\laragon\\www\\WEBQuanLyKyTucXa\\modules\\staff\\invoices\\invoice_list.php at line 11', 'system', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-26 14:40:27'),
	(120, 24, 'Exception', 'System', '[EXCEPTION] Data truncated for column \'Status\' at row 4 in C:\\laragon\\www\\WEBQuanLyKyTucXa\\modules\\staff\\invoices\\invoice_list.php at line 32', 'system', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-26 14:46:12'),
	(121, 24, 'Exception', 'System', '[EXCEPTION] Data truncated for column \'Status\' at row 4 in C:\\laragon\\www\\WEBQuanLyKyTucXa\\modules\\staff\\invoices\\invoice_list.php at line 32', 'system', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-26 14:46:17'),
	(122, 24, 'Exception', 'System', '[EXCEPTION] Data truncated for column \'Status\' at row 4 in C:\\laragon\\www\\WEBQuanLyKyTucXa\\modules\\staff\\invoices\\invoice_list.php at line 32', 'system', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-26 14:46:17'),
	(123, 24, 'Exception', 'System', '[EXCEPTION] Data truncated for column \'Status\' at row 4 in C:\\laragon\\www\\WEBQuanLyKyTucXa\\modules\\staff\\invoices\\invoice_list.php at line 32', 'system', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-26 14:46:18'),
	(124, 24, 'Exception', 'System', '[EXCEPTION] Incorrect date value: \'0000-00-00\' for column \'DueDate\' at row 1 in C:\\laragon\\www\\WEBQuanLyKyTucXa\\modules\\staff\\invoices\\invoice_list.php at line 112', 'system', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-26 14:49:49'),
	(125, 24, 'Exception', 'System', '[EXCEPTION] Incorrect date value: \'0000-00-00\' for column \'DueDate\' at row 1 in C:\\laragon\\www\\WEBQuanLyKyTucXa\\modules\\staff\\invoices\\invoice_list.php at line 112', 'system', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-26 14:49:55'),
	(126, 24, 'Exception', 'System', '[EXCEPTION] Incorrect date value: \'0000-00-00\' for column \'DueDate\' at row 1 in C:\\laragon\\www\\WEBQuanLyKyTucXa\\modules\\staff\\invoices\\invoice_list.php at line 112', 'system', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-26 14:49:58'),
	(127, 24, 'Exception', 'System', '[EXCEPTION] Incorrect date value: \'0000-00-00\' for column \'DueDate\' at row 1 in C:\\laragon\\www\\WEBQuanLyKyTucXa\\modules\\staff\\invoices\\invoice_list.php at line 112', 'system', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-26 14:49:58'),
	(128, 24, 'Exception', 'System', '[EXCEPTION] Incorrect date value: \'0000-00-00\' for column \'DueDate\' at row 1 in C:\\laragon\\www\\WEBQuanLyKyTucXa\\modules\\staff\\invoices\\invoice_list.php at line 112', 'system', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-26 14:49:59'),
	(129, 24, 'Exception', 'System', '[EXCEPTION] Incorrect date value: \'0000-00-00\' for column \'DueDate\' at row 1 in C:\\laragon\\www\\WEBQuanLyKyTucXa\\modules\\staff\\invoices\\invoice_list.php at line 112', 'system', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-26 14:49:59'),
	(130, 24, 'Exception', 'System', '[EXCEPTION] Incorrect date value: \'0000-00-00\' for column \'DueDate\' at row 1 in C:\\laragon\\www\\WEBQuanLyKyTucXa\\modules\\staff\\invoices\\invoice_list.php at line 112', 'system', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-26 14:49:59'),
	(131, 24, 'Exception', 'System', '[EXCEPTION] Incorrect date value: \'0000-00-00\' for column \'DueDate\' at row 1 in C:\\laragon\\www\\WEBQuanLyKyTucXa\\modules\\staff\\invoices\\invoice_list.php at line 137', 'system', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-26 15:24:51'),
	(132, 24, 'Exception', 'System', '[EXCEPTION] Incorrect date value: \'0000-00-00\' for column \'DueDate\' at row 1 in C:\\laragon\\www\\WEBQuanLyKyTucXa\\modules\\staff\\invoices\\invoice_list.php at line 137', 'system', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-26 15:25:35'),
	(133, 24, 'Exception', 'System', '[EXCEPTION] Incorrect date value: \'\' for column \'DueDate\' at row 1 in C:\\laragon\\www\\WEBQuanLyKyTucXa\\modules\\staff\\invoices\\invoice_list.php at line 137', 'system', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-26 15:35:59'),
	(134, 24, 'Exception', 'System', '[EXCEPTION] Incorrect date value: \'\' for column \'DueDate\' at row 1 in C:\\laragon\\www\\WEBQuanLyKyTucXa\\modules\\staff\\invoices\\invoice_list.php at line 137', 'system', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-26 15:36:03'),
	(135, 24, 'PHP Error', 'System', '[PHP ERROR] Undefined array key "FacultyName" in C:\\laragon\\www\\WEBQuanLyKyTucXa\\modules\\staff\\contract\\contract_create.php at line 262', 'system', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-26 15:47:06'),
	(136, 24, 'Create room', 'Rooms', 'Thêm phòng mới ID=6 - Số phòng: A013 (form: A013)', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-27 10:38:40'),
	(137, 24, 'Create room', 'Rooms', 'Thêm phòng mới ID=7 - Số phòng: B102 (form: B102)', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-27 10:40:01'),
	(138, 24, 'Create room', 'Rooms', 'Thêm phòng mới ID=8 - Số phòng: C102 (form: C102)', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-27 10:41:34'),
	(139, 24, 'Create room', 'Rooms', 'Thêm phòng mới ID=9 - Số phòng: C103 (form: C103)', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-27 10:41:55'),
	(140, 24, 'Create room', 'Rooms', 'Thêm phòng mới ID=10 - Số phòng: B103 (form: B103)', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-27 10:42:42'),
	(141, 24, 'Create room', 'Rooms', 'Thêm phòng mới ID=11 - Số phòng: B104 (form: B104)', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-27 10:44:14'),
	(142, 24, 'Create room', 'Rooms', 'Thêm phòng mới ID=12 - Số phòng: C104 (form: C104)', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-27 10:44:47'),
	(143, 24, 'Create room', 'Rooms', 'Thêm phòng mới ID=13 - Số phòng: C105 (form: C105)', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-27 10:45:25'),
	(144, 24, 'Create room', 'Rooms', 'Thêm phòng mới ID=14 - Số phòng: A103 (form: A103)', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-27 10:46:00'),
	(145, 24, 'Create room', 'Rooms', 'Thêm phòng mới ID=15 - Số phòng: B105 (form: B105)', 'activity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-27 10:46:34'),
	(146, 37, 'Update student profile', 'Students', 'Sinh viên tự cập nhật hồ sơ của mình', 'history', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-27 10:57:10'),
	(147, 37, 'PHP Error', 'System', '[PHP ERROR] Cannot modify header information - headers already sent by (output started at C:\\laragon\\www\\WEBQuanLyKyTucXa\\includes\\header.php:174) in C:\\laragon\\www\\WEBQuanLyKyTucXa\\modules\\user\\students\\student_form.php at line 278', 'system', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-27 10:57:10'),
	(148, 37, 'Update student profile', 'Students', 'Sinh viên tự cập nhật hồ sơ của mình', 'history', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-27 10:57:58'),
	(149, 37, 'PHP Error', 'System', '[PHP ERROR] Cannot modify header information - headers already sent by (output started at C:\\laragon\\www\\WEBQuanLyKyTucXa\\includes\\header.php:174) in C:\\laragon\\www\\WEBQuanLyKyTucXa\\modules\\user\\students\\student_form.php at line 278', 'system', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-27 10:57:58'),
	(150, 37, 'Update student profile', 'Students', 'Sinh viên tự cập nhật hồ sơ của mình', 'history', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-27 12:51:12'),
	(151, 37, 'PHP Error', 'System', '[PHP ERROR] Cannot modify header information - headers already sent by (output started at C:\\laragon\\www\\WEBQuanLyKyTucXa\\includes\\header.php:174) in C:\\laragon\\www\\WEBQuanLyKyTucXa\\modules\\user\\students\\student_form.php at line 278', 'system', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-27 12:51:12'),
	(152, 37, 'Update student profile', 'Students', 'Sinh viên tự cập nhật hồ sơ của mình', 'history', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-27 12:51:37'),
	(153, 37, 'PHP Error', 'System', '[PHP ERROR] Cannot modify header information - headers already sent by (output started at C:\\laragon\\www\\WEBQuanLyKyTucXa\\includes\\header.php:174) in C:\\laragon\\www\\WEBQuanLyKyTucXa\\modules\\user\\students\\student_form.php at line 278', 'system', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-27 12:51:37'),
	(154, 37, 'Update student profile', 'Students', 'Sinh viên tự cập nhật hồ sơ của mình', 'history', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-27 12:51:51'),
	(155, 37, 'Update student profile', 'Students', 'Sinh viên tự cập nhật hồ sơ của mình', 'history', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-27 12:54:48'),
	(156, 37, 'Update student profile', 'Students', 'Sinh viên tự cập nhật hồ sơ của mình', 'history', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-28 19:08:50'),
	(157, 37, 'Update student profile', 'Students', 'Sinh viên tự cập nhật hồ sơ của mình', 'history', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-28 19:09:15'),
	(158, 24, 'Update room', 'Rooms', 'Cập nhật phòng ID=1', 'history', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-12-04 12:46:38');

-- Dumping structure for table quanlyktx.users
DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `UserID` int NOT NULL AUTO_INCREMENT,
  `Username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `PasswordHash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `FullName` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Phone` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Role` enum('Admin','Manager','Student') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Student',
  `CreatedAt` datetime DEFAULT CURRENT_TIMESTAMP,
  `IsActive` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`UserID`),
  UNIQUE KEY `Username` (`Username`)
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table quanlyktx.users: ~12 rows (approximately)
INSERT INTO `users` (`UserID`, `Username`, `PasswordHash`, `FullName`, `Email`, `Phone`, `Role`, `CreatedAt`, `IsActive`) VALUES
	(24, 'Admin', '$2y$10$QfYeOJKnQph9HDrS8ykV4exC901LlnbPczSu8yb7UKf6OovMKdtn2', 'Admin', '123@gmail.com', '123456789', 'Admin', '2025-11-01 19:37:18', 1),
	(25, 'user', '$2y$10$3fzX7C/L8J4VN9VVuAleQOqIJ6Mo4BX8OFitytjBeypBx6Ty2TFE.', 'Người dùng', '123@gmail.com', '09358111111', 'Student', '2025-11-06 14:25:23', 1),
	(27, 'Manager', '$2y$10$PBLvcqEObrXwzNqWOMpCuOjx9JNI.cjIEps1cizgGw70CkvG84NKS', 'Manager', '111@gmail.com', '123456789', 'Manager', '2025-11-08 11:54:06', 1),
	(28, 'user2', '$2y$10$D/XxaDRPRrx44Kj9h6zt0uJ5lw2ZaUHn7pXJAkmb.AS4.jcnt1mLe', 'Trần Vinh Hạo', '112@gmail.com', '12345677890', 'Student', '2025-11-11 20:59:09', 1),
	(29, 'Manager2', '$2y$10$AbpalrcN7AKAlxSSWu6CeuWencEvSeoKM5GUBSdFmaSt6vJW9ZF2i', 'Manager 2', '12345@gmail.com', '1234587252', 'Manager', '2025-11-16 22:44:37', 1),
	(30, 'user3', '$2y$10$gxAzT3VKN27fDPMTtjEMq.d7N3aQ5PpVPKQtni0c9xvOfg.rybqqG', 'Võ Thị Cẩm Tiên', '123245@gmail.com', '01234528727', 'Student', '2025-11-23 17:06:30', 1),
	(31, 'tongvanhoang782004214', '', 'Tống Văn Hoàng', 'tongvanhoang782004@gmail.com', '', 'Student', '2025-11-25 16:35:45', 1),
	(32, 'lethicanlan', '$2y$10$a0clXrOUGHRczJ6ucgazXeYNLnJuraluqAgVVYWP6j9bXBRQBvypy', 'Lê Thị Cẩm Lan', 'ltcl@gmail.com', '0123456789', 'Student', '2025-11-27 10:47:56', 1),
	(33, 'tranconganh', '$2y$10$oBWy8AA8QY.NzXyfPS9TTuvkQwR.UkUpkp6dklHlSwxspbrbtffYi', 'Trần Công Anh', 'tca@gmail.com', '0981235654', 'Student', '2025-11-27 10:49:10', 1),
	(34, 'nguyencongtuan', '$2y$10$i8ddhWulLeO4s.vJ90qUcebV18iX/TXeg3nkT54vtQEL8.XltniyC', 'Nguyễn Công Tuấn', 'nct@gmail.com', '05285327192', 'Student', '2025-11-27 10:50:05', 1),
	(35, 'truongquocthai', '$2y$10$VWuiSMYUuCcMQEZQ4M4bzuh1BELcs4H8G/UFWeD3vHag1TrioSU8i', 'Trương Công Quốc Thái', 'tcqt@gmail.com', '01923871571', 'Student', '2025-11-27 10:50:59', 1),
	(36, 'huaquanghan', '$2y$10$2OKZrOsmhSXRG9E0kNBEO.QjR0/3vwDeqD/qAYBkBhUlgfx8EDeSy', 'Hứa Quang Hán', 'hqh@gmail.com', '01923775152', 'Student', '2025-11-27 10:52:23', 1),
	(37, 'leesanghyeok', '$2y$10$WamHZ0xGPcxdHfLQTslQs.aKpefRCTDh46lk6s9kCaEDlEVmJNk4.', 'Lee Sang Hyeok', 'faker@gmail.com', '06912835165', 'Student', '2025-11-27 10:53:39', 1),
	(38, 'nguyenhuuthang', '$2y$10$iUSiGZDnYJ/q1tflno0c/uIStJwt2NqcvajSSw.ZEqGbMGUZc3b46', 'Nguyễn Hữu Thắng', 'nht@gmail.com', '01293715711', 'Student', '2025-11-27 10:55:09', 1),
	(39, 'phmphc591222', '', 'Quang Phúc', 'phmphc591@gmail.com', NULL, 'Student', '2025-12-04 12:21:23', 1);

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
