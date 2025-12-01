-- Database Backup
-- Generated: 2025-11-21 15:44:23
-- Database: girlscodingacademydb

SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET AUTOCOMMIT = 0;
START TRANSACTION;

DROP TABLE IF EXISTS `accounts_budgets`;
CREATE TABLE `accounts_budgets` (
  `budget_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`budget_id`),
  KEY `category_id` (`category_id`),
  KEY `accounts_budgets_ibfk_2` (`created_by`),
  CONSTRAINT `accounts_budgets_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `accounts_categories` (`category_id`) ON DELETE CASCADE,
  CONSTRAINT `accounts_budgets_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `accounts_categories`;
CREATE TABLE `accounts_categories` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `type` enum('income','expense') NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`category_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `accounts_categories` (`category_id`, `name`, `type`, `description`, `created_at`) VALUES ('1', 'Enrollment Fees', 'income', 'Tuition and registration fees', '2025-11-10 19:10:25');
INSERT INTO `accounts_categories` (`category_id`, `name`, `type`, `description`, `created_at`) VALUES ('2', 'Utilities', 'expense', 'Electricity, water, WiFi', '2025-11-10 19:10:25');
INSERT INTO `accounts_categories` (`category_id`, `name`, `type`, `description`, `created_at`) VALUES ('3', 'Airtime/Data', 'expense', 'Mobile and internet services', '2025-11-10 19:10:25');
INSERT INTO `accounts_categories` (`category_id`, `name`, `type`, `description`, `created_at`) VALUES ('4', 'Infrastructure', 'expense', 'Maintenance and repairs', '2025-11-10 19:10:25');

DROP TABLE IF EXISTS `accounts_expenses`;
CREATE TABLE `accounts_expenses` (
  `expense_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `vendor` varchar(255) NOT NULL,
  `date` date NOT NULL,
  `description` text DEFAULT NULL,
  `receipt_file` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','paid') DEFAULT 'pending',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`expense_id`),
  KEY `category_id` (`category_id`),
  KEY `accounts_expenses_ibfk_2` (`created_by`),
  CONSTRAINT `accounts_expenses_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `accounts_categories` (`category_id`) ON DELETE CASCADE,
  CONSTRAINT `accounts_expenses_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `accounts_income`;
CREATE TABLE `accounts_income` (
  `income_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `source` varchar(255) NOT NULL,
  `invoice_id` int(11) DEFAULT NULL,
  `date` date NOT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`income_id`),
  KEY `category_id` (`category_id`),
  KEY `invoice_id` (`invoice_id`),
  KEY `accounts_income_ibfk_3` (`created_by`),
  CONSTRAINT `accounts_income_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `accounts_categories` (`category_id`) ON DELETE CASCADE,
  CONSTRAINT `accounts_income_ibfk_2` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`invoice_id`) ON DELETE SET NULL,
  CONSTRAINT `accounts_income_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `accounts_income` (`income_id`, `category_id`, `amount`, `source`, `invoice_id`, `date`, `description`, `created_by`, `created_at`) VALUES ('1', '1', '300.00', 'Python', NULL, '2025-11-17', 'none', '484', '2025-11-17 12:57:31');

DROP TABLE IF EXISTS `activities`;
CREATE TABLE `activities` (
  `activity_id` int(11) NOT NULL AUTO_INCREMENT,
  `batch_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `due_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `resource_file` varchar(255) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'active',
  PRIMARY KEY (`activity_id`),
  KEY `fk_activities_teacher` (`teacher_id`),
  KEY `fk_activities_batch` (`batch_id`),
  CONSTRAINT `fk_activities_batch` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`batch_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_activities_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`teacher_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `activities` (`activity_id`, `batch_id`, `teacher_id`, `title`, `description`, `due_date`, `created_at`, `resource_file`, `status`) VALUES ('6', '21', '4', 'End Activity', 'none', '2025-11-21', '2025-11-21 16:25:58', 'Uploads/1763735158_Collection-of-Admission-Letters.pdf', 'active');

DROP TABLE IF EXISTS `activity_submissions`;
CREATE TABLE `activity_submissions` (
  `submission_id` int(11) NOT NULL AUTO_INCREMENT,
  `activity_id` int(11) NOT NULL,
  `enrollment_id` int(11) NOT NULL,
  `submission_text` text DEFAULT NULL,
  `submission_file` varchar(255) DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`submission_id`),
  UNIQUE KEY `activity_id` (`activity_id`,`enrollment_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `activity_submissions` (`submission_id`, `activity_id`, `enrollment_id`, `submission_text`, `submission_file`, `submitted_at`) VALUES ('1', '3', '45', 'Dear Mam Please find my submission below.', '', '2025-09-24 10:32:54');
INSERT INTO `activity_submissions` (`submission_id`, `activity_id`, `enrollment_id`, `submission_text`, `submission_file`, `submitted_at`) VALUES ('2', '4', '51', 'I have to read the book and answered the questions successfully.', 'Uploads/Collection-of-Admission-Letters.pdf', '2025-09-24 13:53:28');
INSERT INTO `activity_submissions` (`submission_id`, `activity_id`, `enrollment_id`, `submission_text`, `submission_file`, `submitted_at`) VALUES ('3', '5', '43', 'ellular Mobile Systems provide flexible, wide-area communication by dividing coverage into overlapping cells. This enables efficient use of frequencies and uninterrupted service through handovers when users move between cells, ensuring continuous calls and data sessions. This system is crucial for both voice calls and data transfer with mobility.\\r\\n\\r\\nMobile Operating Systems act as an interface between mobile hardware and applications, handling tasks such as user interaction, processing, and resource management. They allow smartphones and tablets to run diverse applications, power communication, and enhance user experience. Their importance lies in enabling a versatile, multitasking mobile platform.\\r\\n\\r\\nMobile Video Streaming systems provide real-time visual information sharing in critical scenarios like emergencies. They improve situational awareness and decision-making by transmitting live feeds securely over resilient networks that overcome connectivity challenges such as obstacles or congested networks.\\r\\n\\r\\nMobile Communication Technology overall impacts society by enabling speedy, flexible, and cost-effective information exchange regardless of location. It supports numerous applications from personal communication to remote education and continuous connectivity, significantly enhancing productivity and accessibility', '', '2025-10-01 09:04:08');

DROP TABLE IF EXISTS `addresses`;
CREATE TABLE `addresses` (
  `address_id` int(11) NOT NULL AUTO_INCREMENT,
  `address1` varchar(255) DEFAULT NULL,
  `streetName` varchar(255) DEFAULT NULL,
  `postalCode` varchar(20) DEFAULT NULL,
  `district` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`address_id`)
) ENGINE=InnoDB AUTO_INCREMENT=97 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `addresses` (`address_id`, `address1`, `streetName`, `postalCode`, `district`, `country`, `created_at`, `updated_at`) VALUES ('23', 'Maseru', 'Thetsane', 'Lesotho', 'Mathematics', 'Mobile Computist', '2025-08-25 13:57:39', '2025-08-25 13:57:39');
INSERT INTO `addresses` (`address_id`, `address1`, `streetName`, `postalCode`, `district`, `country`, `created_at`, `updated_at`) VALUES ('28', 'santa ', NULL, '1864', 'Gauteng', 'South Africa', '2025-08-26 11:51:38', NULL);
INSERT INTO `addresses` (`address_id`, `address1`, `streetName`, `postalCode`, `district`, `country`, `created_at`, `updated_at`) VALUES ('30', 'Zone 6', 'Santa', '1862', 'Gauteng', 'South Africa', '2025-08-26 19:28:40', NULL);
INSERT INTO `addresses` (`address_id`, `address1`, `streetName`, `postalCode`, `district`, `country`, `created_at`, `updated_at`) VALUES ('31', 'kokwana', 'nokeng', '1862', 'Gauteng', 'South Africa', '2025-08-27 09:47:05', NULL);
INSERT INTO `addresses` (`address_id`, `address1`, `streetName`, `postalCode`, `district`, `country`, `created_at`, `updated_at`) VALUES ('32', 'kokwana', 'nokeng', '1864', 'Gauteng', 'South Africa', '2025-08-27 09:48:03', '2025-10-21 22:42:03');
INSERT INTO `addresses` (`address_id`, `address1`, `streetName`, `postalCode`, `district`, `country`, `created_at`, `updated_at`) VALUES ('34', 'Zone 6', 'Santa', '1864', 'Gauteng', 'South Africa', '2025-08-27 10:31:36', NULL);
INSERT INTO `addresses` (`address_id`, `address1`, `streetName`, `postalCode`, `district`, `country`, `created_at`, `updated_at`) VALUES ('39', 'Santa', 'Laundry', '1862', 'Gauteng', 'South Africa', '2025-08-29 09:03:37', NULL);
INSERT INTO `addresses` (`address_id`, `address1`, `streetName`, `postalCode`, `district`, `country`, `created_at`, `updated_at`) VALUES ('55', 'Zone 14', 'Siyabulela', '1852', 'Guateng', 'South Africa', '2025-09-26 15:08:32', '2025-09-26 15:08:32');
INSERT INTO `addresses` (`address_id`, `address1`, `streetName`, `postalCode`, `district`, `country`, `created_at`, `updated_at`) VALUES ('56', 'Zone 14', 'Siyabulela', '1852', 'Guateng', 'South Africa', '2025-09-26 15:19:18', '2025-09-26 15:19:18');
INSERT INTO `addresses` (`address_id`, `address1`, `streetName`, `postalCode`, `district`, `country`, `created_at`, `updated_at`) VALUES ('59', '4', 'Thetsane', '100', 'Maseru', 'Lesotho', '2025-10-18 13:45:56', '2025-11-21 16:09:50');
INSERT INTO `addresses` (`address_id`, `address1`, `streetName`, `postalCode`, `district`, `country`, `created_at`, `updated_at`) VALUES ('60', '4', 'Thetsane', '100', 'Botha Bothe', 'Lesotho', '2025-10-18 14:00:04', '2025-10-18 14:00:04');
INSERT INTO `addresses` (`address_id`, `address1`, `streetName`, `postalCode`, `district`, `country`, `created_at`, `updated_at`) VALUES ('61', '4', 'Thetsane', '100', 'Botha Bothe', 'Lesotho', '2025-10-18 14:10:32', '2025-10-18 14:10:32');
INSERT INTO `addresses` (`address_id`, `address1`, `streetName`, `postalCode`, `district`, `country`, `created_at`, `updated_at`) VALUES ('62', '4', 'Thetsane', '100', 'Botha Bothe', 'Lesotho', '2025-10-18 14:10:47', '2025-10-18 14:10:47');
INSERT INTO `addresses` (`address_id`, `address1`, `streetName`, `postalCode`, `district`, `country`, `created_at`, `updated_at`) VALUES ('63', '4', 'Thetsane', '100', 'Botha Bothe', 'Lesotho', '2025-10-18 14:11:18', '2025-10-18 14:11:18');
INSERT INTO `addresses` (`address_id`, `address1`, `streetName`, `postalCode`, `district`, `country`, `created_at`, `updated_at`) VALUES ('64', '4', 'Thetsane', '100', 'Botha Bothe', 'Lesotho', '2025-10-18 14:11:58', '2025-10-18 14:11:58');
INSERT INTO `addresses` (`address_id`, `address1`, `streetName`, `postalCode`, `district`, `country`, `created_at`, `updated_at`) VALUES ('65', '4', 'Thetsane', '100', 'Botha Bothe', 'Lesotho', '2025-10-18 14:12:45', '2025-10-18 14:12:45');
INSERT INTO `addresses` (`address_id`, `address1`, `streetName`, `postalCode`, `district`, `country`, `created_at`, `updated_at`) VALUES ('66', '4', 'Thetsane', '100', 'Botha Bothe', 'Lesotho', '2025-10-18 14:13:16', '2025-10-18 14:13:16');
INSERT INTO `addresses` (`address_id`, `address1`, `streetName`, `postalCode`, `district`, `country`, `created_at`, `updated_at`) VALUES ('67', '4', 'Thetsane', '100', 'Botha Bothe', 'Lesotho', '2025-10-18 14:14:51', '2025-10-18 14:14:51');
INSERT INTO `addresses` (`address_id`, `address1`, `streetName`, `postalCode`, `district`, `country`, `created_at`, `updated_at`) VALUES ('68', '4', 'Thetsane', '100', 'Botha Bothe', 'Lesotho', '2025-10-18 14:18:31', '2025-10-18 14:18:31');
INSERT INTO `addresses` (`address_id`, `address1`, `streetName`, `postalCode`, `district`, `country`, `created_at`, `updated_at`) VALUES ('69', '4', 'Thetsane', '100', 'Botha Bothe', 'Lesotho', '2025-10-18 14:20:07', '2025-10-18 14:20:07');
INSERT INTO `addresses` (`address_id`, `address1`, `streetName`, `postalCode`, `district`, `country`, `created_at`, `updated_at`) VALUES ('70', '4', 'Thetsane', '100', 'Botha Bothe', 'Lesotho', '2025-10-18 14:32:41', '2025-10-18 14:32:41');
INSERT INTO `addresses` (`address_id`, `address1`, `streetName`, `postalCode`, `district`, `country`, `created_at`, `updated_at`) VALUES ('71', '4', 'Thetsane', '100', 'Botha Bothe', 'Lesotho', '2025-10-18 14:34:00', '2025-10-18 14:34:00');
INSERT INTO `addresses` (`address_id`, `address1`, `streetName`, `postalCode`, `district`, `country`, `created_at`, `updated_at`) VALUES ('72', '4', 'Thetsane', '100', 'Botha Bothe', 'Lesotho', '2025-10-18 14:39:56', '2025-10-18 14:39:56');
INSERT INTO `addresses` (`address_id`, `address1`, `streetName`, `postalCode`, `district`, `country`, `created_at`, `updated_at`) VALUES ('73', '4', 'Thetsane', '100', 'Botha Bothe', 'Lesotho', '2025-10-18 14:43:34', '2025-10-26 20:03:02');
INSERT INTO `addresses` (`address_id`, `address1`, `streetName`, `postalCode`, `district`, `country`, `created_at`, `updated_at`) VALUES ('74', '4', 'Thetsane', '100', 'Maseru', 'Lesotho', '2025-10-18 14:47:25', '2025-10-23 09:48:58');
INSERT INTO `addresses` (`address_id`, `address1`, `streetName`, `postalCode`, `district`, `country`, `created_at`, `updated_at`) VALUES ('75', 'Santa', 'Londi Street', '1865', 'Gauteng', 'South Africa', '2025-10-20 11:26:59', '2025-10-21 22:34:31');
INSERT INTO `addresses` (`address_id`, `address1`, `streetName`, `postalCode`, `district`, `country`, `created_at`, `updated_at`) VALUES ('76', 'Mohalalitoe', 'Hamaphohloana', '100', 'Maseru', 'Lesotho', '2025-10-22 15:44:47', '2025-10-25 22:31:28');
INSERT INTO `addresses` (`address_id`, `address1`, `streetName`, `postalCode`, `district`, `country`, `created_at`, `updated_at`) VALUES ('77', 'Mohalalitoe', 'Hamaphohloana', '100', 'Maseru', 'Lesotho', '2025-10-22 15:47:00', '2025-10-22 15:47:00');
INSERT INTO `addresses` (`address_id`, `address1`, `streetName`, `postalCode`, `district`, `country`, `created_at`, `updated_at`) VALUES ('78', 'Ha Moshoeshoe', 'Thetsane', '400', 'Maseru', 'Lesotho', '2025-10-22 16:01:08', '2025-10-22 16:01:08');
INSERT INTO `addresses` (`address_id`, `address1`, `streetName`, `postalCode`, `district`, `country`, `created_at`, `updated_at`) VALUES ('83', 'kololong Ha Moshoeshoe', 'Thetsane', '400', 'Botha Bothe', 'Lesotho', '2025-10-28 09:31:47', '2025-10-28 09:31:47');
INSERT INTO `addresses` (`address_id`, `address1`, `streetName`, `postalCode`, `district`, `country`, `created_at`, `updated_at`) VALUES ('85', 'Ha Abia', 'Mapetla', '100', 'Maseru', 'Lesotho', '2025-10-31 10:18:14', '2025-10-31 10:19:28');
INSERT INTO `addresses` (`address_id`, `address1`, `streetName`, `postalCode`, `district`, `country`, `created_at`, `updated_at`) VALUES ('86', 'kololong Ha Moshoeshoe', 'Thetsane', '400', 'Botha Bothe', 'Lesotho', '2025-10-31 10:57:46', '2025-10-31 10:57:46');
INSERT INTO `addresses` (`address_id`, `address1`, `streetName`, `postalCode`, `district`, `country`, `created_at`, `updated_at`) VALUES ('87', '4', 'Thetsane', '100', 'Maseru', 'Lesotho', '2025-10-31 13:57:01', '2025-10-31 13:57:01');
INSERT INTO `addresses` (`address_id`, `address1`, `streetName`, `postalCode`, `district`, `country`, `created_at`, `updated_at`) VALUES ('88', 'Pita', 'Thetsane', '100', 'Maseru', 'Lesotho', '2025-10-31 13:58:06', '2025-10-31 14:03:35');
INSERT INTO `addresses` (`address_id`, `address1`, `streetName`, `postalCode`, `district`, `country`, `created_at`, `updated_at`) VALUES ('89', 'Santa', 'Zone 6', '1865', 'Gauteng', 'South Africa', '2025-11-05 20:06:40', '2025-11-05 20:54:12');
INSERT INTO `addresses` (`address_id`, `address1`, `streetName`, `postalCode`, `district`, `country`, `created_at`, `updated_at`) VALUES ('90', 'Zikodye', 'Mazukwezi', '129', 'Harare', 'Zimbabwe', '2025-11-05 20:09:05', '2025-11-05 20:09:05');
INSERT INTO `addresses` (`address_id`, `address1`, `streetName`, `postalCode`, `district`, `country`, `created_at`, `updated_at`) VALUES ('91', 'kololong Ha Moshoeshoe', 'Thetsane', '400', 'Maseru', 'Lesotho', '2025-11-05 20:13:00', '2025-11-05 21:21:48');
INSERT INTO `addresses` (`address_id`, `address1`, `streetName`, `postalCode`, `district`, `country`, `created_at`, `updated_at`) VALUES ('92', 'Zone 6', 'Ndlovu', '1864', 'Gauteng', 'South Africa', '2025-11-05 20:17:29', '2025-11-05 20:17:48');
INSERT INTO `addresses` (`address_id`, `address1`, `streetName`, `postalCode`, `district`, `country`, `created_at`, `updated_at`) VALUES ('93', 'Zondi', 'Khehlembe', '1869', 'Gauteng', 'South Africa', '2025-11-05 20:56:38', '2025-11-05 20:56:38');
INSERT INTO `addresses` (`address_id`, `address1`, `streetName`, `postalCode`, `district`, `country`, `created_at`, `updated_at`) VALUES ('94', 'Zondi', 'Khehlembe', '1869', 'Gauteng', 'South Africa', '2025-11-05 20:57:56', '2025-11-05 20:57:56');
INSERT INTO `addresses` (`address_id`, `address1`, `streetName`, `postalCode`, `district`, `country`, `created_at`, `updated_at`) VALUES ('95', 'Ha Moshoeshoe', 'Kolonqi', '400', 'Qacha\'s Nek', 'Lesotho', '2025-11-10 17:23:34', '2025-11-10 17:23:34');
INSERT INTO `addresses` (`address_id`, `address1`, `streetName`, `postalCode`, `district`, `country`, `created_at`, `updated_at`) VALUES ('96', 'Moshoeshoe', 'Kiyini', '400', 'Maputsoe', 'Lesotho', '2025-11-10 19:19:45', '2025-11-10 19:19:45');

DROP TABLE IF EXISTS `admin_announcements`;
CREATE TABLE `admin_announcements` (
  `announcement_id` int(11) NOT NULL AUTO_INCREMENT,
  `message` text NOT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `picture_path` varchar(255) DEFAULT NULL,
  `recipients` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`announcement_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `admin_announcements` (`announcement_id`, `message`, `file_path`, `picture_path`, `recipients`, `created_at`, `updated_at`) VALUES ('1', 'awe awe guys', NULL, 'uploads/announcements/images/announcement_img_68e4d850bcf29.jpg', 'students', '2025-10-07 11:07:28', '2025-10-07 11:07:28');
INSERT INTO `admin_announcements` (`announcement_id`, `message`, `file_path`, `picture_path`, `recipients`, `created_at`, `updated_at`) VALUES ('2', 'Teachers hello', NULL, NULL, 'teachers', '2025-10-07 16:20:47', '2025-10-07 16:20:47');
INSERT INTO `admin_announcements` (`announcement_id`, `message`, `file_path`, `picture_path`, `recipients`, `created_at`, `updated_at`) VALUES ('3', 'The New Week tomorrow guys, Good Luck', 'uploads/print1.jpg', 'uploads/student1.jpg', 'teachers', '2025-10-26 19:41:33', '2025-10-27 15:03:42');
INSERT INTO `admin_announcements` (`announcement_id`, `message`, `file_path`, `picture_path`, `recipients`, `created_at`, `updated_at`) VALUES ('4', 'The Competitions are next week let\'s all suit up and be ready.', 'uploads/n.pdf', 'uploads/1761747025_arc3.jpeg', 'all', '2025-10-31 09:53:55', '2025-10-31 09:53:55');

DROP TABLE IF EXISTS `attendance`;
CREATE TABLE `attendance` (
  `attendance_id` int(11) NOT NULL AUTO_INCREMENT,
  `session_id` date NOT NULL,
  `student_id` int(11) NOT NULL,
  `batch_id` int(11) NOT NULL,
  `status` enum('Present','Absent','Late','Sick') NOT NULL,
  `marked_by` int(11) NOT NULL,
  `marked_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`attendance_id`),
  UNIQUE KEY `unique_attendance` (`session_id`,`student_id`),
  KEY `student_id` (`student_id`),
  KEY `batch_id` (`batch_id`),
  KEY `marked_by` (`marked_by`),
  CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`batch_id`) ON DELETE CASCADE,
  CONSTRAINT `attendance_ibfk_3` FOREIGN KEY (`marked_by`) REFERENCES `teachers` (`teacher_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=329 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `attendance` (`attendance_id`, `session_id`, `student_id`, `batch_id`, `status`, `marked_by`, `marked_at`) VALUES ('239', '2025-10-22', '30', '21', 'Present', '4', '2025-10-22 11:39:45');
INSERT INTO `attendance` (`attendance_id`, `session_id`, `student_id`, `batch_id`, `status`, `marked_by`, `marked_at`) VALUES ('240', '2025-10-22', '31', '21', 'Present', '4', '2025-10-22 11:39:45');
INSERT INTO `attendance` (`attendance_id`, `session_id`, `student_id`, `batch_id`, `status`, `marked_by`, `marked_at`) VALUES ('247', '2025-10-22', '32', '21', 'Present', '4', '2025-10-22 16:18:24');
INSERT INTO `attendance` (`attendance_id`, `session_id`, `student_id`, `batch_id`, `status`, `marked_by`, `marked_at`) VALUES ('250', '2025-10-23', '32', '21', 'Absent', '4', '2025-10-23 09:45:25');
INSERT INTO `attendance` (`attendance_id`, `session_id`, `student_id`, `batch_id`, `status`, `marked_by`, `marked_at`) VALUES ('251', '2025-10-23', '30', '21', 'Present', '4', '2025-10-23 09:45:25');
INSERT INTO `attendance` (`attendance_id`, `session_id`, `student_id`, `batch_id`, `status`, `marked_by`, `marked_at`) VALUES ('252', '2025-10-23', '31', '21', 'Late', '4', '2025-10-23 09:45:25');
INSERT INTO `attendance` (`attendance_id`, `session_id`, `student_id`, `batch_id`, `status`, `marked_by`, `marked_at`) VALUES ('253', '2025-10-25', '17', '23', 'Present', '4', '2025-10-25 22:01:32');
INSERT INTO `attendance` (`attendance_id`, `session_id`, `student_id`, `batch_id`, `status`, `marked_by`, `marked_at`) VALUES ('254', '2025-10-25', '30', '23', 'Present', '4', '2025-10-25 22:01:32');
INSERT INTO `attendance` (`attendance_id`, `session_id`, `student_id`, `batch_id`, `status`, `marked_by`, `marked_at`) VALUES ('255', '2025-10-25', '32', '21', 'Present', '4', '2025-10-25 22:38:32');
INSERT INTO `attendance` (`attendance_id`, `session_id`, `student_id`, `batch_id`, `status`, `marked_by`, `marked_at`) VALUES ('256', '2025-10-27', '32', '21', 'Present', '4', '2025-10-27 15:34:51');
INSERT INTO `attendance` (`attendance_id`, `session_id`, `student_id`, `batch_id`, `status`, `marked_by`, `marked_at`) VALUES ('257', '2025-10-27', '17', '21', 'Present', '4', '2025-10-27 15:34:51');
INSERT INTO `attendance` (`attendance_id`, `session_id`, `student_id`, `batch_id`, `status`, `marked_by`, `marked_at`) VALUES ('258', '2025-10-27', '30', '21', 'Present', '4', '2025-10-27 15:34:51');
INSERT INTO `attendance` (`attendance_id`, `session_id`, `student_id`, `batch_id`, `status`, `marked_by`, `marked_at`) VALUES ('259', '2025-10-27', '29', '21', 'Present', '4', '2025-10-27 15:34:51');
INSERT INTO `attendance` (`attendance_id`, `session_id`, `student_id`, `batch_id`, `status`, `marked_by`, `marked_at`) VALUES ('264', '2025-11-01', '39', '21', 'Present', '4', '2025-11-01 08:28:41');
INSERT INTO `attendance` (`attendance_id`, `session_id`, `student_id`, `batch_id`, `status`, `marked_by`, `marked_at`) VALUES ('265', '2025-11-01', '32', '21', 'Present', '4', '2025-11-01 08:28:41');
INSERT INTO `attendance` (`attendance_id`, `session_id`, `student_id`, `batch_id`, `status`, `marked_by`, `marked_at`) VALUES ('266', '2025-11-01', '17', '21', 'Late', '4', '2025-11-01 08:28:41');
INSERT INTO `attendance` (`attendance_id`, `session_id`, `student_id`, `batch_id`, `status`, `marked_by`, `marked_at`) VALUES ('267', '2025-11-01', '40', '21', 'Present', '4', '2025-11-01 08:28:41');
INSERT INTO `attendance` (`attendance_id`, `session_id`, `student_id`, `batch_id`, `status`, `marked_by`, `marked_at`) VALUES ('268', '2025-11-01', '30', '21', 'Absent', '4', '2025-11-01 08:28:41');
INSERT INTO `attendance` (`attendance_id`, `session_id`, `student_id`, `batch_id`, `status`, `marked_by`, `marked_at`) VALUES ('269', '2025-11-01', '29', '21', 'Present', '4', '2025-11-01 08:28:41');
INSERT INTO `attendance` (`attendance_id`, `session_id`, `student_id`, `batch_id`, `status`, `marked_by`, `marked_at`) VALUES ('270', '2025-11-01', '37', '21', 'Present', '4', '2025-11-01 08:28:41');
INSERT INTO `attendance` (`attendance_id`, `session_id`, `student_id`, `batch_id`, `status`, `marked_by`, `marked_at`) VALUES ('275', '2025-11-04', '39', '21', 'Present', '4', '2025-11-04 13:22:13');
INSERT INTO `attendance` (`attendance_id`, `session_id`, `student_id`, `batch_id`, `status`, `marked_by`, `marked_at`) VALUES ('276', '2025-11-04', '32', '21', 'Present', '4', '2025-11-04 13:22:13');
INSERT INTO `attendance` (`attendance_id`, `session_id`, `student_id`, `batch_id`, `status`, `marked_by`, `marked_at`) VALUES ('277', '2025-11-04', '17', '21', 'Present', '4', '2025-11-04 13:22:13');
INSERT INTO `attendance` (`attendance_id`, `session_id`, `student_id`, `batch_id`, `status`, `marked_by`, `marked_at`) VALUES ('278', '2025-11-04', '40', '21', 'Present', '4', '2025-11-04 13:22:13');
INSERT INTO `attendance` (`attendance_id`, `session_id`, `student_id`, `batch_id`, `status`, `marked_by`, `marked_at`) VALUES ('279', '2025-11-04', '30', '21', 'Present', '4', '2025-11-04 13:22:13');
INSERT INTO `attendance` (`attendance_id`, `session_id`, `student_id`, `batch_id`, `status`, `marked_by`, `marked_at`) VALUES ('280', '2025-11-04', '29', '21', 'Absent', '4', '2025-11-04 13:22:13');
INSERT INTO `attendance` (`attendance_id`, `session_id`, `student_id`, `batch_id`, `status`, `marked_by`, `marked_at`) VALUES ('281', '2025-11-04', '37', '21', 'Present', '4', '2025-11-04 13:22:13');
INSERT INTO `attendance` (`attendance_id`, `session_id`, `student_id`, `batch_id`, `status`, `marked_by`, `marked_at`) VALUES ('287', '2025-11-11', '41', '21', 'Present', '4', '2025-11-11 09:58:23');
INSERT INTO `attendance` (`attendance_id`, `session_id`, `student_id`, `batch_id`, `status`, `marked_by`, `marked_at`) VALUES ('288', '2025-11-11', '39', '21', 'Present', '4', '2025-11-11 09:58:23');
INSERT INTO `attendance` (`attendance_id`, `session_id`, `student_id`, `batch_id`, `status`, `marked_by`, `marked_at`) VALUES ('289', '2025-11-11', '42', '21', 'Present', '4', '2025-11-11 09:58:23');
INSERT INTO `attendance` (`attendance_id`, `session_id`, `student_id`, `batch_id`, `status`, `marked_by`, `marked_at`) VALUES ('290', '2025-11-11', '32', '21', 'Present', '4', '2025-11-11 09:58:23');
INSERT INTO `attendance` (`attendance_id`, `session_id`, `student_id`, `batch_id`, `status`, `marked_by`, `marked_at`) VALUES ('291', '2025-11-11', '17', '21', 'Present', '4', '2025-11-11 09:58:23');
INSERT INTO `attendance` (`attendance_id`, `session_id`, `student_id`, `batch_id`, `status`, `marked_by`, `marked_at`) VALUES ('292', '2025-11-11', '40', '21', 'Present', '4', '2025-11-11 09:58:23');
INSERT INTO `attendance` (`attendance_id`, `session_id`, `student_id`, `batch_id`, `status`, `marked_by`, `marked_at`) VALUES ('293', '2025-11-11', '30', '21', 'Present', '4', '2025-11-11 09:58:23');
INSERT INTO `attendance` (`attendance_id`, `session_id`, `student_id`, `batch_id`, `status`, `marked_by`, `marked_at`) VALUES ('294', '2025-11-11', '29', '21', 'Present', '4', '2025-11-11 09:58:23');
INSERT INTO `attendance` (`attendance_id`, `session_id`, `student_id`, `batch_id`, `status`, `marked_by`, `marked_at`) VALUES ('295', '2025-11-11', '37', '21', 'Present', '4', '2025-11-11 09:58:23');
INSERT INTO `attendance` (`attendance_id`, `session_id`, `student_id`, `batch_id`, `status`, `marked_by`, `marked_at`) VALUES ('310', '2025-11-21', '39', '22', 'Late', '4', '2025-11-21 15:23:13');
INSERT INTO `attendance` (`attendance_id`, `session_id`, `student_id`, `batch_id`, `status`, `marked_by`, `marked_at`) VALUES ('311', '2025-11-21', '32', '22', 'Present', '4', '2025-11-21 15:23:13');
INSERT INTO `attendance` (`attendance_id`, `session_id`, `student_id`, `batch_id`, `status`, `marked_by`, `marked_at`) VALUES ('312', '2025-11-21', '17', '22', 'Present', '4', '2025-11-21 15:23:13');
INSERT INTO `attendance` (`attendance_id`, `session_id`, `student_id`, `batch_id`, `status`, `marked_by`, `marked_at`) VALUES ('313', '2025-11-21', '40', '22', 'Sick', '4', '2025-11-21 15:23:13');
INSERT INTO `attendance` (`attendance_id`, `session_id`, `student_id`, `batch_id`, `status`, `marked_by`, `marked_at`) VALUES ('314', '2025-11-21', '30', '22', 'Present', '4', '2025-11-21 15:23:13');
INSERT INTO `attendance` (`attendance_id`, `session_id`, `student_id`, `batch_id`, `status`, `marked_by`, `marked_at`) VALUES ('315', '2025-11-21', '41', '21', 'Present', '4', '2025-11-21 16:27:30');
INSERT INTO `attendance` (`attendance_id`, `session_id`, `student_id`, `batch_id`, `status`, `marked_by`, `marked_at`) VALUES ('317', '2025-11-21', '42', '21', 'Present', '4', '2025-11-21 16:27:30');
INSERT INTO `attendance` (`attendance_id`, `session_id`, `student_id`, `batch_id`, `status`, `marked_by`, `marked_at`) VALUES ('322', '2025-11-21', '29', '21', 'Present', '4', '2025-11-21 16:27:30');
INSERT INTO `attendance` (`attendance_id`, `session_id`, `student_id`, `batch_id`, `status`, `marked_by`, `marked_at`) VALUES ('323', '2025-11-21', '37', '21', 'Present', '4', '2025-11-21 16:27:30');

DROP TABLE IF EXISTS `batches`;
CREATE TABLE `batches` (
  `batch_id` int(11) NOT NULL AUTO_INCREMENT,
  `batch_code` varchar(50) NOT NULL,
  `course_id` int(11) NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('active','completed','inactive') DEFAULT 'active',
  PRIMARY KEY (`batch_id`),
  UNIQUE KEY `batch_code` (`batch_code`),
  KEY `course_id` (`course_id`),
  CONSTRAINT `batches_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`)
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `batches` (`batch_id`, `batch_code`, `course_id`, `start_date`, `end_date`, `status`) VALUES ('21', 'FLL-2025-08-12', '26', '2025-10-21', '2025-12-31', 'active');
INSERT INTO `batches` (`batch_id`, `batch_code`, `course_id`, `start_date`, `end_date`, `status`) VALUES ('22', 'FTC-2025-08-12', '27', '2025-10-21', '2025-12-31', 'active');
INSERT INTO `batches` (`batch_id`, `batch_code`, `course_id`, `start_date`, `end_date`, `status`) VALUES ('23', 'PYT-2025-08-12', '28', '2025-10-21', '2025-12-31', 'active');

DROP TABLE IF EXISTS `class_schedules`;
CREATE TABLE `class_schedules` (
  `schedule_id` int(11) NOT NULL AUTO_INCREMENT,
  `batch_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `class_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `room_number` varchar(50) NOT NULL,
  `room_building` varchar(100) NOT NULL,
  `room_capacity` int(11) NOT NULL,
  `topic` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`schedule_id`),
  KEY `batch_id` (`batch_id`),
  KEY `teacher_id` (`teacher_id`),
  CONSTRAINT `class_schedules_ibfk_1` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`batch_id`) ON DELETE CASCADE,
  CONSTRAINT `class_schedules_ibfk_2` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`teacher_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `class_schedules` (`schedule_id`, `batch_id`, `teacher_id`, `class_date`, `start_time`, `end_time`, `room_number`, `room_building`, `room_capacity`, `topic`, `description`, `created_at`) VALUES ('1', '21', '4', '2025-10-31', '15:44:00', '16:00:00', '101', 'Main', '23', 'Competition Strategies', 'non', '2025-10-31 15:44:07');
INSERT INTO `class_schedules` (`schedule_id`, `batch_id`, `teacher_id`, `class_date`, `start_time`, `end_time`, `room_number`, `room_building`, `room_capacity`, `topic`, `description`, `created_at`) VALUES ('2', '23', '4', '2025-11-01', '10:00:00', '10:32:00', '101', 'Room 1', '30', 'Introduction to Python', 'Learning the basics of programming from the beggining', '2025-11-01 08:30:33');
INSERT INTO `class_schedules` (`schedule_id`, `batch_id`, `teacher_id`, `class_date`, `start_time`, `end_time`, `room_number`, `room_building`, `room_capacity`, `topic`, `description`, `created_at`) VALUES ('3', '21', '4', '2025-11-04', '14:40:00', '15:00:00', '101', 'Main', '7', 'Lego Authentication', 'to be discussed', '2025-11-04 14:34:52');
INSERT INTO `class_schedules` (`schedule_id`, `batch_id`, `teacher_id`, `class_date`, `start_time`, `end_time`, `room_number`, `room_building`, `room_capacity`, `topic`, `description`, `created_at`) VALUES ('4', '23', '4', '2025-11-04', '15:00:00', '15:30:00', '001', 'Main', '5', 'Indenting', 'to be discussed', '2025-11-04 14:36:37');

DROP TABLE IF EXISTS `course_assignments`;
CREATE TABLE `course_assignments` (
  `assignment_id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `batch_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`assignment_id`),
  KEY `fk_teacher` (`teacher_id`),
  KEY `fk_batch` (`batch_id`),
  CONSTRAINT `fk_batch` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`batch_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`teacher_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `course_assignments` (`assignment_id`, `teacher_id`, `batch_id`, `created_at`) VALUES ('25', '4', '23', '2025-10-23 09:46:05');
INSERT INTO `course_assignments` (`assignment_id`, `teacher_id`, `batch_id`, `created_at`) VALUES ('26', '4', '21', '2025-11-05 20:58:30');
INSERT INTO `course_assignments` (`assignment_id`, `teacher_id`, `batch_id`, `created_at`) VALUES ('27', '16', '22', '2025-11-21 15:09:07');

DROP TABLE IF EXISTS `course_enrollments`;
CREATE TABLE `course_enrollments` (
  `enrollment_id` int(11) NOT NULL AUTO_INCREMENT,
  `enrollment_number` varchar(50) NOT NULL,
  `student_id` int(11) NOT NULL,
  `batch_id` int(11) NOT NULL,
  `enrolled_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('active','completed','dropped') DEFAULT 'active',
  PRIMARY KEY (`enrollment_id`),
  UNIQUE KEY `enrollment_number` (`enrollment_number`),
  KEY `student_id` (`student_id`),
  KEY `fk_batch_id` (`batch_id`),
  CONSTRAINT `course_enrollments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_batch_id` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`batch_id`)
) ENGINE=InnoDB AUTO_INCREMENT=130 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `course_enrollments` (`enrollment_id`, `enrollment_number`, `student_id`, `batch_id`, `enrolled_at`, `status`) VALUES ('82', 'ENR-2025-000082', '30', '23', '2025-10-23 10:14:47', 'active');
INSERT INTO `course_enrollments` (`enrollment_id`, `enrollment_number`, `student_id`, `batch_id`, `enrolled_at`, `status`) VALUES ('83', 'ENR-2025-000083', '30', '22', '2025-10-23 10:14:52', 'active');
INSERT INTO `course_enrollments` (`enrollment_id`, `enrollment_number`, `student_id`, `batch_id`, `enrolled_at`, `status`) VALUES ('84', 'ENR-2025-000084', '17', '23', '2025-10-23 15:55:41', 'active');
INSERT INTO `course_enrollments` (`enrollment_id`, `enrollment_number`, `student_id`, `batch_id`, `enrolled_at`, `status`) VALUES ('85', 'ENR-2025-000085', '17', '22', '2025-10-23 15:55:43', 'active');
INSERT INTO `course_enrollments` (`enrollment_id`, `enrollment_number`, `student_id`, `batch_id`, `enrolled_at`, `status`) VALUES ('86', 'ENR-2025-000086', '32', '21', '2025-10-25 22:31:56', 'active');
INSERT INTO `course_enrollments` (`enrollment_id`, `enrollment_number`, `student_id`, `batch_id`, `enrolled_at`, `status`) VALUES ('87', 'ENR-2025-000087', '32', '23', '2025-10-25 22:32:02', '');
INSERT INTO `course_enrollments` (`enrollment_id`, `enrollment_number`, `student_id`, `batch_id`, `enrolled_at`, `status`) VALUES ('88', 'ENR-2025-000088', '32', '22', '2025-10-26 18:33:59', 'active');
INSERT INTO `course_enrollments` (`enrollment_id`, `enrollment_number`, `student_id`, `batch_id`, `enrolled_at`, `status`) VALUES ('89', 'ENR-2025-000089', '17', '21', '2025-10-26 18:59:17', 'active');
INSERT INTO `course_enrollments` (`enrollment_id`, `enrollment_number`, `student_id`, `batch_id`, `enrolled_at`, `status`) VALUES ('90', 'ENR-2025-000090', '30', '21', '2025-10-26 19:44:12', 'active');
INSERT INTO `course_enrollments` (`enrollment_id`, `enrollment_number`, `student_id`, `batch_id`, `enrolled_at`, `status`) VALUES ('91', 'ENR-2025-000091', '29', '21', '2025-10-26 20:03:21', 'active');
INSERT INTO `course_enrollments` (`enrollment_id`, `enrollment_number`, `student_id`, `batch_id`, `enrolled_at`, `status`) VALUES ('92', 'ENR-2025-000092', '29', '22', '2025-10-26 20:03:23', 'completed');
INSERT INTO `course_enrollments` (`enrollment_id`, `enrollment_number`, `student_id`, `batch_id`, `enrolled_at`, `status`) VALUES ('93', 'ENR-2025-000093', '29', '23', '2025-10-26 20:03:24', 'dropped');
INSERT INTO `course_enrollments` (`enrollment_id`, `enrollment_number`, `student_id`, `batch_id`, `enrolled_at`, `status`) VALUES ('105', 'ENR-2025-007978', '37', '21', '2025-10-28 09:32:04', 'active');
INSERT INTO `course_enrollments` (`enrollment_id`, `enrollment_number`, `student_id`, `batch_id`, `enrolled_at`, `status`) VALUES ('112', '', '39', '22', '2025-10-31 10:24:29', 'active');
INSERT INTO `course_enrollments` (`enrollment_id`, `enrollment_number`, `student_id`, `batch_id`, `enrolled_at`, `status`) VALUES ('120', 'ENR-2025-10-120000', '39', '23', '2025-10-31 10:49:26', 'active');
INSERT INTO `course_enrollments` (`enrollment_id`, `enrollment_number`, `student_id`, `batch_id`, `enrolled_at`, `status`) VALUES ('121', 'ENR-2025-10-121000', '39', '21', '2025-10-31 11:04:18', 'active');
INSERT INTO `course_enrollments` (`enrollment_id`, `enrollment_number`, `student_id`, `batch_id`, `enrolled_at`, `status`) VALUES ('122', 'ENR-2025-10-122000', '40', '21', '2025-10-31 13:58:39', 'active');
INSERT INTO `course_enrollments` (`enrollment_id`, `enrollment_number`, `student_id`, `batch_id`, `enrolled_at`, `status`) VALUES ('123', 'ENR-2025-10-123000', '40', '22', '2025-10-31 14:04:14', 'active');
INSERT INTO `course_enrollments` (`enrollment_id`, `enrollment_number`, `student_id`, `batch_id`, `enrolled_at`, `status`) VALUES ('124', 'ENR-2025-10-124000', '40', '23', '2025-10-31 14:42:32', 'active');
INSERT INTO `course_enrollments` (`enrollment_id`, `enrollment_number`, `student_id`, `batch_id`, `enrolled_at`, `status`) VALUES ('125', 'ENR-2025-11-125000', '42', '21', '2025-11-05 20:19:24', 'active');
INSERT INTO `course_enrollments` (`enrollment_id`, `enrollment_number`, `student_id`, `batch_id`, `enrolled_at`, `status`) VALUES ('127', 'ENR-2025-11-127000', '41', '23', '2025-11-05 21:01:42', 'active');
INSERT INTO `course_enrollments` (`enrollment_id`, `enrollment_number`, `student_id`, `batch_id`, `enrolled_at`, `status`) VALUES ('128', 'ENR-2025-11-128000', '41', '21', '2025-11-05 21:02:40', 'active');

DROP TABLE IF EXISTS `course_favorites`;
CREATE TABLE `course_favorites` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `batch_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_favorite` (`student_id`,`batch_id`),
  KEY `batch_id` (`batch_id`),
  CONSTRAINT `course_favorites_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  CONSTRAINT `course_favorites_ibfk_2` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`batch_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `courses`;
CREATE TABLE `courses` (
  `course_id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `courseName` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `level` varchar(50) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `image_path` varchar(255) DEFAULT 'uploads/courses/course1.jpg',
  PRIMARY KEY (`course_id`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `courses` (`course_id`, `title`, `courseName`, `description`, `category`, `level`, `start_date`, `end_date`, `price`, `status`, `image_path`) VALUES ('26', 'FLL', 'First Lego League', 'The First Lego League (FLL) is a global STEM program aimed at children ages 4 to 16 (age varies by country), introducing science, technology, engineering, and math through fun, hands-on learning activities and robotics competitions. The program is structured into three divisions:\r\nive innovators.', 'Robotics', 'Beginner', '2025-10-21', '2026-01-21', '450.00', 'inactive', 'Uploads/courses/course_1761078012.png');
INSERT INTO `courses` (`course_id`, `title`, `courseName`, `description`, `category`, `level`, `start_date`, `end_date`, `price`, `status`, `image_path`) VALUES ('27', 'FTC', 'First Tech Challenge', 'The FIRST Tech Challenge (FTC) is a STEM robotics program designed for students in grades 7 through 12. It challenges teams of up to 15 members to design, build, program, and operate robots to compete in head-to-head alliance-based competitions. The program fosters skills in engineering, programming, teamwork, ', 'Robotics', 'Advanced', '2025-10-21', '2026-02-20', '450.00', 'active', 'Uploads/courses/course_1763374929.jpg');
INSERT INTO `courses` (`course_id`, `title`, `courseName`, `description`, `category`, `level`, `start_date`, `end_date`, `price`, `status`, `image_path`) VALUES ('28', 'PYT', 'Python Essentials', 'A typical Python programming course offers an introduction to programming concepts using Python, suitable for beginners with no prior coding experience. It covers fundamental topics such as variables, data types, conditionals, loops, functions, and basic data structures like lists and dictionaries. Students learn to write and run Python scripts, handle user input, and perform simple file operations. More comprehensive courses also introduce object-oriented programming (OOP), error handling, libraries, and modules.\r\n', 'Coding', 'Advanced', '2025-10-21', '2026-04-21', '450.00', 'active', 'Uploads/courses/course_1761078373.png');
INSERT INTO `courses` (`course_id`, `title`, `courseName`, `description`, `category`, `level`, `start_date`, `end_date`, `price`, `status`, `image_path`) VALUES ('29', 'WDD', 'Website Design and Development', 'A web development course for beginners typically covers the fundamentals of building websites and web applications. It starts with introducing HTML for structuring web pages and CSS for styling and layout. Students learn to create responsive designs that work across devices. The course then introduces JavaScript to add interactivity and dynamic behavior to websites, including form validation and event handling.\r\n', 'Coding', 'Intermediate', '2025-10-21', '2026-12-21', '450.00', 'active', 'Uploads/courses/course_1761078518.jpeg');

DROP TABLE IF EXISTS `events`;
CREATE TABLE `events` (
  `event_id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `event_date` date NOT NULL,
  `event_time_start` time DEFAULT NULL,
  `event_time_end` time DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `is_posted` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`event_id`),
  UNIQUE KEY `unique_event` (`title`,`event_date`,`event_time_start`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `events` (`event_id`, `title`, `description`, `event_date`, `event_time_start`, `event_time_end`, `category`, `location`, `photo`, `is_posted`, `created_at`) VALUES ('5', 'Graduation', 'Gradual wait ceremony celebration', '2025-10-02', '09:30:00', '17:30:00', 'Competition', 'Mohalalitoe', 'uploads/events/event_68dceca9dce2e6.62213779.jpg', '0', '2025-10-01 10:27:04');
INSERT INTO `events` (`event_id`, `title`, `description`, `event_date`, `event_time_start`, `event_time_end`, `category`, `location`, `photo`, `is_posted`, `created_at`) VALUES ('6', 'Festival', 'hhhh', '2025-10-17', '10:57:00', '10:58:00', 'Festival', 'Tsolo', 'uploads/events/event_68dcf08a4603b7.31565676.jpg', '0', '2025-10-01 10:57:46');
INSERT INTO `events` (`event_id`, `title`, `description`, `event_date`, `event_time_start`, `event_time_end`, `category`, `location`, `photo`, `is_posted`, `created_at`) VALUES ('7', 'Freshers Ball', 'let\'s enjoy the freshers ball event.', '2025-10-11', '18:00:00', '06:00:00', 'Festival', 'Botho University Campus', 'uploads/events/event_68dcf7120669b9.10740708.jpg', '0', '2025-10-01 11:40:34');
INSERT INTO `events` (`event_id`, `title`, `description`, `event_date`, `event_time_start`, `event_time_end`, `category`, `location`, `photo`, `is_posted`, `created_at`) VALUES ('8', 'Presentation Week', 'presentation preparations for the Competition.', '2025-10-02', '13:00:00', '15:30:00', 'Other', 'Girls Coding Academy', 'uploads/events/event_68dcfa18a4e4c3.30120454.jpg', '0', '2025-10-01 11:47:41');
INSERT INTO `events` (`event_id`, `title`, `description`, `event_date`, `event_time_start`, `event_time_end`, `category`, `location`, `photo`, `is_posted`, `created_at`) VALUES ('9', 'Rissclusive Brand Launching', 'The Launching of the t-shirt for swagg and more professional outlook and presentation', '2025-12-25', '09:00:00', '17:07:00', 'Other', 'Botho University Campus', 'uploads/events/event_68f24dd914df52.13074760.png', '1', '2025-10-17 16:08:25');
INSERT INTO `events` (`event_id`, `title`, `description`, `event_date`, `event_time_start`, `event_time_end`, `category`, `location`, `photo`, `is_posted`, `created_at`) VALUES ('10', 'Spring Jump', 'fun all the way, let\'s enjoy.', '2025-11-15', '10:21:00', '18:52:00', 'Competition', 'Thetsane', 'uploads/events/event_6911fb8b9149b9.31367679.jpgLinked to Campaign: 3', '0', '2025-11-10 16:49:05');

DROP TABLE IF EXISTS `groupchat_settings`;
CREATE TABLE `groupchat_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `is_blocked` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `groupchat_settings` (`id`, `is_blocked`) VALUES ('1', '0');

DROP TABLE IF EXISTS `help_tickets`;
CREATE TABLE `help_tickets` (
  `ticket_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `status` enum('open','in_progress','closed') DEFAULT 'open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`ticket_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `help_tickets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `inactivation_requests`;
CREATE TABLE `inactivation_requests` (
  `request_id` int(11) NOT NULL AUTO_INCREMENT,
  `enrollment_id` int(11) NOT NULL,
  `student_name` varchar(255) NOT NULL,
  `student_email` varchar(255) NOT NULL,
  `batch_code` varchar(100) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `processed_by` int(11) DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  PRIMARY KEY (`request_id`),
  KEY `teacher_id` (`teacher_id`),
  KEY `enrollment_id` (`enrollment_id`),
  KEY `processed_by` (`processed_by`),
  CONSTRAINT `inactivation_requests_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`teacher_id`),
  CONSTRAINT `inactivation_requests_ibfk_2` FOREIGN KEY (`enrollment_id`) REFERENCES `course_enrollments` (`enrollment_id`),
  CONSTRAINT `inactivation_requests_ibfk_3` FOREIGN KEY (`processed_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `inactivation_requests` (`request_id`, `enrollment_id`, `student_name`, `student_email`, `batch_code`, `teacher_id`, `reason`, `status`, `created_at`, `processed_by`, `rejection_reason`) VALUES ('1', '120', 'Karabo Setumo', 'karabo@gmail.com', 'PYT-2025-08-12', '4', 'She is not writing my tests.', 'pending', '2025-11-21 16:36:58', NULL, NULL);

DROP TABLE IF EXISTS `internal_grades`;
CREATE TABLE `internal_grades` (
  `grade_id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `batch_id` int(11) NOT NULL,
  `test_1` decimal(5,2) DEFAULT NULL CHECK (`test_1` between 0 and 100),
  `test_2` decimal(5,2) DEFAULT NULL CHECK (`test_2` between 0 and 100),
  `test_3` decimal(5,2) DEFAULT NULL CHECK (`test_3` between 0 and 100),
  `test_4` decimal(5,2) DEFAULT NULL CHECK (`test_4` between 0 and 100),
  `test_5` decimal(5,2) DEFAULT NULL CHECK (`test_5` between 0 and 100),
  `test_6` decimal(5,2) DEFAULT NULL CHECK (`test_6` between 0 and 100),
  `test_7` decimal(5,2) DEFAULT NULL CHECK (`test_7` between 0 and 100),
  `end_examination` decimal(5,2) DEFAULT NULL CHECK (`end_examination` between 0 and 100),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`grade_id`),
  KEY `student_id` (`student_id`),
  KEY `batch_id` (`batch_id`),
  CONSTRAINT `internal_grades_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  CONSTRAINT `internal_grades_ibfk_2` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`batch_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `internal_grades` (`grade_id`, `student_id`, `batch_id`, `test_1`, `test_2`, `test_3`, `test_4`, `test_5`, `test_6`, `test_7`, `end_examination`, `created_at`) VALUES ('8', '30', '21', '99.00', '86.00', '75.00', NULL, NULL, NULL, NULL, NULL, '2025-10-22 09:53:25');
INSERT INTO `internal_grades` (`grade_id`, `student_id`, `batch_id`, `test_1`, `test_2`, `test_3`, `test_4`, `test_5`, `test_6`, `test_7`, `end_examination`, `created_at`) VALUES ('9', '31', '21', '96.00', '99.00', '96.00', NULL, NULL, NULL, NULL, NULL, '2025-10-22 09:53:37');
INSERT INTO `internal_grades` (`grade_id`, `student_id`, `batch_id`, `test_1`, `test_2`, `test_3`, `test_4`, `test_5`, `test_6`, `test_7`, `end_examination`, `created_at`) VALUES ('10', '32', '21', '100.00', '86.00', '83.00', '85.00', '63.00', '69.00', '88.00', NULL, '2025-10-22 16:16:08');
INSERT INTO `internal_grades` (`grade_id`, `student_id`, `batch_id`, `test_1`, `test_2`, `test_3`, `test_4`, `test_5`, `test_6`, `test_7`, `end_examination`, `created_at`) VALUES ('11', '17', '23', '58.00', '85.00', '89.00', '77.00', '82.25', '88.00', '69.00', '85.00', '2025-10-25 21:57:02');
INSERT INTO `internal_grades` (`grade_id`, `student_id`, `batch_id`, `test_1`, `test_2`, `test_3`, `test_4`, `test_5`, `test_6`, `test_7`, `end_examination`, `created_at`) VALUES ('12', '30', '23', '89.00', '96.00', '99.00', '64.00', '86.00', '56.00', '66.00', '79.00', '2025-10-25 21:59:29');
INSERT INTO `internal_grades` (`grade_id`, `student_id`, `batch_id`, `test_1`, `test_2`, `test_3`, `test_4`, `test_5`, `test_6`, `test_7`, `end_examination`, `created_at`) VALUES ('13', '40', '23', '86.00', '76.00', '86.00', '88.00', '78.60', '68.70', '68.50', '76.00', '2025-11-04 13:15:55');
INSERT INTO `internal_grades` (`grade_id`, `student_id`, `batch_id`, `test_1`, `test_2`, `test_3`, `test_4`, `test_5`, `test_6`, `test_7`, `end_examination`, `created_at`) VALUES ('14', '39', '23', '99.00', '85.00', '53.00', '63.00', '69.96', '83.47', '63.00', '89.00', '2025-11-04 13:16:12');
INSERT INTO `internal_grades` (`grade_id`, `student_id`, `batch_id`, `test_1`, `test_2`, `test_3`, `test_4`, `test_5`, `test_6`, `test_7`, `end_examination`, `created_at`) VALUES ('16', '39', '22', '59.00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-21 16:08:51');

DROP TABLE IF EXISTS `invoices`;
CREATE TABLE `invoices` (
  `invoice_id` int(11) NOT NULL AUTO_INCREMENT,
  `enrollment_id` int(11) NOT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `due_date` date NOT NULL,
  `status` enum('pending','paid','overdue','cancelled') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`invoice_id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  KEY `fk_invoice_enrollment` (`enrollment_id`),
  CONSTRAINT `fk_invoice_enrollment` FOREIGN KEY (`enrollment_id`) REFERENCES `course_enrollments` (`enrollment_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=54 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `invoices` (`invoice_id`, `enrollment_id`, `invoice_number`, `amount`, `due_date`, `status`, `created_at`, `updated_at`) VALUES ('8', '82', 'INV-2025-10-000082', '450.00', '2025-11-22', 'paid', '2025-10-23 10:14:47', '2025-10-23 10:28:34');
INSERT INTO `invoices` (`invoice_id`, `enrollment_id`, `invoice_number`, `amount`, `due_date`, `status`, `created_at`, `updated_at`) VALUES ('9', '83', 'INV-2025-10-000083', '450.00', '2025-11-22', 'paid', '2025-10-23 10:14:52', '2025-10-23 10:15:15');
INSERT INTO `invoices` (`invoice_id`, `enrollment_id`, `invoice_number`, `amount`, `due_date`, `status`, `created_at`, `updated_at`) VALUES ('10', '84', 'INV-2025-10-000084', '450.00', '2025-11-22', 'paid', '2025-10-23 15:55:41', '2025-10-26 10:44:33');
INSERT INTO `invoices` (`invoice_id`, `enrollment_id`, `invoice_number`, `amount`, `due_date`, `status`, `created_at`, `updated_at`) VALUES ('11', '85', 'INV-2025-10-000085', '450.00', '2025-11-22', 'paid', '2025-10-23 15:55:43', '2025-10-25 22:09:12');
INSERT INTO `invoices` (`invoice_id`, `enrollment_id`, `invoice_number`, `amount`, `due_date`, `status`, `created_at`, `updated_at`) VALUES ('12', '86', 'INV-2025-10-000086', '450.00', '2025-11-24', 'paid', '2025-10-25 22:31:56', '2025-10-26 10:44:41');
INSERT INTO `invoices` (`invoice_id`, `enrollment_id`, `invoice_number`, `amount`, `due_date`, `status`, `created_at`, `updated_at`) VALUES ('13', '87', 'INV-2025-10-000087', '450.00', '2025-11-24', 'paid', '2025-10-25 22:32:02', '2025-10-26 18:51:13');
INSERT INTO `invoices` (`invoice_id`, `enrollment_id`, `invoice_number`, `amount`, `due_date`, `status`, `created_at`, `updated_at`) VALUES ('15', '88', 'INV-2025-10-000088', '450.00', '2025-11-25', 'paid', '2025-10-26 18:33:59', '2025-10-26 18:48:32');
INSERT INTO `invoices` (`invoice_id`, `enrollment_id`, `invoice_number`, `amount`, `due_date`, `status`, `created_at`, `updated_at`) VALUES ('16', '89', 'INV-2025-10-000089', '450.00', '2025-11-25', 'paid', '2025-10-26 18:59:17', '2025-10-26 19:00:06');
INSERT INTO `invoices` (`invoice_id`, `enrollment_id`, `invoice_number`, `amount`, `due_date`, `status`, `created_at`, `updated_at`) VALUES ('17', '90', 'INV-2025-10-000090', '450.00', '2025-11-25', 'paid', '2025-10-26 19:44:12', '2025-10-26 19:57:21');
INSERT INTO `invoices` (`invoice_id`, `enrollment_id`, `invoice_number`, `amount`, `due_date`, `status`, `created_at`, `updated_at`) VALUES ('18', '91', 'INV-2025-10-000091', '450.00', '2025-11-25', 'paid', '2025-10-26 20:03:21', '2025-10-27 15:01:42');
INSERT INTO `invoices` (`invoice_id`, `enrollment_id`, `invoice_number`, `amount`, `due_date`, `status`, `created_at`, `updated_at`) VALUES ('19', '92', 'INV-2025-10-000092', '450.00', '2025-11-25', 'paid', '2025-10-26 20:03:23', '2025-11-01 08:51:02');
INSERT INTO `invoices` (`invoice_id`, `enrollment_id`, `invoice_number`, `amount`, `due_date`, `status`, `created_at`, `updated_at`) VALUES ('20', '93', 'INV-2025-10-000093', '450.00', '2025-11-25', 'paid', '2025-10-26 20:03:24', '2025-11-01 08:50:18');
INSERT INTO `invoices` (`invoice_id`, `enrollment_id`, `invoice_number`, `amount`, `due_date`, `status`, `created_at`, `updated_at`) VALUES ('37', '105', 'INV-2025-10-000105', '450.00', '2025-11-27', 'paid', '2025-10-28 09:32:04', '2025-10-31 11:34:50');
INSERT INTO `invoices` (`invoice_id`, `enrollment_id`, `invoice_number`, `amount`, `due_date`, `status`, `created_at`, `updated_at`) VALUES ('38', '105', 'INV-2025-000038', '450.00', '2025-11-27', 'paid', '2025-10-28 09:32:04', '2025-10-28 09:35:51');
INSERT INTO `invoices` (`invoice_id`, `enrollment_id`, `invoice_number`, `amount`, `due_date`, `status`, `created_at`, `updated_at`) VALUES ('43', '112', 'INV-2025-10-000112', '450.00', '2025-11-30', 'paid', '2025-10-31 10:24:29', '2025-10-31 10:53:28');
INSERT INTO `invoices` (`invoice_id`, `enrollment_id`, `invoice_number`, `amount`, `due_date`, `status`, `created_at`, `updated_at`) VALUES ('44', '120', 'INV-2025-10-000120', '450.00', '2025-11-30', 'paid', '2025-10-31 10:49:26', '2025-10-31 10:51:10');
INSERT INTO `invoices` (`invoice_id`, `enrollment_id`, `invoice_number`, `amount`, `due_date`, `status`, `created_at`, `updated_at`) VALUES ('45', '121', 'INV-2025-10-000121', '450.00', '2025-11-30', 'paid', '2025-10-31 11:04:18', '2025-10-31 11:05:26');
INSERT INTO `invoices` (`invoice_id`, `enrollment_id`, `invoice_number`, `amount`, `due_date`, `status`, `created_at`, `updated_at`) VALUES ('46', '122', 'INV-2025-10-000122', '450.00', '2025-11-30', 'paid', '2025-10-31 13:58:39', '2025-10-31 14:07:26');
INSERT INTO `invoices` (`invoice_id`, `enrollment_id`, `invoice_number`, `amount`, `due_date`, `status`, `created_at`, `updated_at`) VALUES ('47', '123', 'INV-2025-10-000123', '450.00', '2025-11-30', 'paid', '2025-10-31 14:04:14', '2025-11-01 08:35:30');
INSERT INTO `invoices` (`invoice_id`, `enrollment_id`, `invoice_number`, `amount`, `due_date`, `status`, `created_at`, `updated_at`) VALUES ('48', '124', 'INV-2025-10-000124', '450.00', '2025-11-30', 'paid', '2025-10-31 14:42:32', '2025-11-01 08:35:56');
INSERT INTO `invoices` (`invoice_id`, `enrollment_id`, `invoice_number`, `amount`, `due_date`, `status`, `created_at`, `updated_at`) VALUES ('49', '125', 'INV-2025-11-000125', '450.00', '2025-12-05', 'paid', '2025-11-05 20:19:24', '2025-11-05 20:23:04');
INSERT INTO `invoices` (`invoice_id`, `enrollment_id`, `invoice_number`, `amount`, `due_date`, `status`, `created_at`, `updated_at`) VALUES ('51', '127', 'INV-2025-11-000127', '450.00', '2025-12-05', 'paid', '2025-11-05 21:01:42', '2025-11-05 21:01:59');
INSERT INTO `invoices` (`invoice_id`, `enrollment_id`, `invoice_number`, `amount`, `due_date`, `status`, `created_at`, `updated_at`) VALUES ('52', '128', 'INV-2025-11-000128', '450.00', '2025-12-05', 'paid', '2025-11-05 21:02:40', '2025-11-05 21:35:10');

DROP TABLE IF EXISTS `marketing_analytics_logs`;
CREATE TABLE `marketing_analytics_logs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `event_type` varchar(100) NOT NULL COMMENT 'e.g., view, click, conversion',
  `campaign_id` int(11) DEFAULT NULL,
  `lead_id` int(11) DEFAULT NULL,
  `content_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`),
  KEY `campaign_id` (`campaign_id`),
  KEY `lead_id` (`lead_id`),
  KEY `content_id` (`content_id`),
  CONSTRAINT `marketing_analytics_logs_ibfk_1` FOREIGN KEY (`campaign_id`) REFERENCES `marketing_campaigns` (`campaign_id`) ON DELETE SET NULL,
  CONSTRAINT `marketing_analytics_logs_ibfk_2` FOREIGN KEY (`lead_id`) REFERENCES `marketing_leads` (`lead_id`) ON DELETE SET NULL,
  CONSTRAINT `marketing_analytics_logs_ibfk_3` FOREIGN KEY (`content_id`) REFERENCES `marketing_content` (`content_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `marketing_campaigns`;
CREATE TABLE `marketing_campaigns` (
  `campaign_id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `target_audience` enum('students','parents','alumni','teachers','general') NOT NULL,
  `status` enum('draft','active','completed','paused') DEFAULT 'draft',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`campaign_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `marketing_campaigns_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `marketing_campaigns` (`campaign_id`, `title`, `description`, `start_date`, `end_date`, `target_audience`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('1', 'American Corner', 'Showcasing all we do at Girls coding Academy and what the parents may like it is your turn to be outstanding in this very life.', '2025-11-12', '2025-11-14', 'general', 'active', '483', '2025-11-10 17:56:27', '2025-11-10 17:56:27');
INSERT INTO `marketing_campaigns` (`campaign_id`, `title`, `description`, `start_date`, `end_date`, `target_audience`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('2', 'Tiny Tots', 'Lego Introduction to communities and schools in general', '2025-11-18', '2025-11-19', 'general', 'active', '483', '2025-11-10 18:04:54', '2025-11-10 18:04:54');
INSERT INTO `marketing_campaigns` (`campaign_id`, `title`, `description`, `start_date`, `end_date`, `target_audience`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('3', 'American Corner Expooo', 'non', '2025-11-11', '2025-11-12', 'general', 'active', '483', '2025-11-10 18:06:23', '2025-11-10 18:06:23');

DROP TABLE IF EXISTS `marketing_content`;
CREATE TABLE `marketing_content` (
  `content_id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `linked_batch_id` int(11) DEFAULT NULL,
  `linked_event_id` int(11) DEFAULT NULL,
  `uploaded_by` int(11) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`content_id`),
  KEY `uploaded_by` (`uploaded_by`),
  KEY `linked_batch_id` (`linked_batch_id`),
  KEY `linked_event_id` (`linked_event_id`),
  CONSTRAINT `marketing_content_ibfk_1` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `marketing_content_ibfk_2` FOREIGN KEY (`linked_batch_id`) REFERENCES `batches` (`batch_id`) ON DELETE SET NULL,
  CONSTRAINT `marketing_content_ibfk_3` FOREIGN KEY (`linked_event_id`) REFERENCES `events` (`event_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `marketing_content` (`content_id`, `title`, `description`, `file_path`, `linked_batch_id`, `linked_event_id`, `uploaded_by`, `uploaded_at`) VALUES ('1', 'T-Shirt Printing Logo', 'we are going to showcase all the stuff that we have go for your new uniform.', 'uploads/marketing_content/1762791303_c5bac3642e34325cbe998113fdfc41b1.mp4', '21', '10', '483', '2025-11-10 18:15:03');

DROP TABLE IF EXISTS `marketing_feedback_responses`;
CREATE TABLE `marketing_feedback_responses` (
  `response_id` int(11) NOT NULL AUTO_INCREMENT,
  `survey_id` int(11) NOT NULL,
  `respondent_id` int(11) NOT NULL,
  `answers` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`answers`)),
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`response_id`),
  KEY `survey_id` (`survey_id`),
  KEY `respondent_id` (`respondent_id`),
  CONSTRAINT `marketing_feedback_responses_ibfk_1` FOREIGN KEY (`survey_id`) REFERENCES `marketing_feedback_surveys` (`survey_id`) ON DELETE CASCADE,
  CONSTRAINT `marketing_feedback_responses_ibfk_2` FOREIGN KEY (`respondent_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `marketing_feedback_surveys`;
CREATE TABLE `marketing_feedback_surveys` (
  `survey_id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `questions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Array of question objects' CHECK (json_valid(`questions`)),
  `target_group` enum('students','parents','teachers','all') NOT NULL,
  `status` enum('active','closed') DEFAULT 'active',
  `responses_count` int(11) DEFAULT 0,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`survey_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `marketing_feedback_surveys_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `marketing_feedback_surveys` (`survey_id`, `title`, `questions`, `target_group`, `status`, `responses_count`, `created_by`, `created_at`) VALUES ('1', 'Competition Feedback', '[{\"text\":\"Full Names\",\"type\":\"text\"},{\"text\":\"Email\",\"type\":\"text\"},{\"text\":\"How did the Competition feel like?\",\"type\":\"text\"},{\"text\":\"Which was Your Favorite Mission \",\"type\":\"text\"},{\"text\":\"How was the judging?(was it fair or not, Justify why)\",\"type\":\"text\"},{\"text\":\"Rate the competition out of 5\",\"type\":\"text\"}]', 'students', 'active', '0', '483', '2025-11-10 18:36:46');
INSERT INTO `marketing_feedback_surveys` (`survey_id`, `title`, `questions`, `target_group`, `status`, `responses_count`, `created_by`, `created_at`) VALUES ('2', 'Student Feedback', '[{\"text\":\"Student Number\",\"type\":\"text\"},{\"text\":\"Program enrolled\",\"type\":\"text\"},{\"text\":\"How was the semester?\",\"type\":\"text\"},{\"text\":\"Did you experience any issues with the system?\",\"type\":\"text\"},{\"text\":\"Rate your Teacher\",\"type\":\"rating\"}]', 'students', 'active', '0', '483', '2025-11-10 19:02:32');

DROP TABLE IF EXISTS `marketing_leads`;
CREATE TABLE `marketing_leads` (
  `lead_id` int(11) NOT NULL AUTO_INCREMENT,
  `campaign_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `source` varchar(255) DEFAULT NULL,
  `status` enum('new','contacted','qualified','converted','lost') DEFAULT 'new',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`lead_id`),
  UNIQUE KEY `email` (`email`),
  KEY `campaign_id` (`campaign_id`),
  CONSTRAINT `marketing_leads_ibfk_1` FOREIGN KEY (`campaign_id`) REFERENCES `marketing_campaigns` (`campaign_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `marketing_leads` (`lead_id`, `campaign_id`, `name`, `email`, `phone`, `source`, `status`, `notes`, `created_at`, `updated_at`) VALUES ('1', '3', 'Katlego Moosi', 'katlegomoosi@gmail.com', '68559652', 'Campaign', 'contacted', NULL, '2025-11-10 18:10:03', '2025-11-10 20:39:57');

DROP TABLE IF EXISTS `marketing_social_posts`;
CREATE TABLE `marketing_social_posts` (
  `post_id` int(11) NOT NULL AUTO_INCREMENT,
  `platform` varchar(50) NOT NULL COMMENT 'e.g., twitter, facebook, instagram',
  `content` text NOT NULL,
  `post_url` varchar(500) DEFAULT NULL,
  `engagement_metrics` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '{"likes": 0, "shares": 0, "comments": 0}' CHECK (json_valid(`engagement_metrics`)),
  `campaign_id` int(11) DEFAULT NULL,
  `posted_by` int(11) NOT NULL,
  `posted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`post_id`),
  KEY `campaign_id` (`campaign_id`),
  KEY `posted_by` (`posted_by`),
  CONSTRAINT `marketing_social_posts_ibfk_1` FOREIGN KEY (`campaign_id`) REFERENCES `marketing_campaigns` (`campaign_id`) ON DELETE SET NULL,
  CONSTRAINT `marketing_social_posts_ibfk_2` FOREIGN KEY (`posted_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `marketing_social_posts` (`post_id`, `platform`, `content`, `post_url`, `engagement_metrics`, `campaign_id`, `posted_by`, `posted_at`) VALUES ('1', 'facebook', 'Hey everybody we are Girls Coding academy Your Robotics only offering academy in the Country.', '', '{\"likes\":0,\"shares\":0,\"comments\":0}', '3', '483', '2025-11-10 18:21:54');
INSERT INTO `marketing_social_posts` (`post_id`, `platform`, `content`, `post_url`, `engagement_metrics`, `campaign_id`, `posted_by`, `posted_at`) VALUES ('2', 'linkedin', 'jhjjnjn', '', '{\"likes\":0,\"shares\":0,\"comments\":0}', '2', '483', '2025-11-13 02:08:30');

DROP TABLE IF EXISTS `materials`;
CREATE TABLE `materials` (
  `material_id` int(11) NOT NULL AUTO_INCREMENT,
  `batch_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`material_id`),
  KEY `batch_id` (`batch_id`),
  KEY `teacher_id` (`teacher_id`),
  CONSTRAINT `materials_ibfk_1` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`batch_id`) ON DELETE CASCADE,
  CONSTRAINT `materials_ibfk_2` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`teacher_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `materials` (`material_id`, `batch_id`, `teacher_id`, `title`, `description`, `file_path`, `uploaded_at`) VALUES ('7', '21', '4', 'Competition Guidlines', 'no description', 'Uploads/activity_68cbe5816f694.pdf', '2025-10-22 15:00:53');
INSERT INTO `materials` (`material_id`, `batch_id`, `teacher_id`, `title`, `description`, `file_path`, `uploaded_at`) VALUES ('8', '23', '4', 'End Examination Preparations', 'prepare for the upcoming exams.', 'Uploads/Collection-of-Admission-Letters.pdf', '2025-10-25 22:04:54');
INSERT INTO `materials` (`material_id`, `batch_id`, `teacher_id`, `title`, `description`, `file_path`, `uploaded_at`) VALUES ('9', '23', '4', 'Introduction', 'intro to programming', 'Uploads/Invoices Payment.pdf', '2025-11-01 08:31:50');
INSERT INTO `materials` (`material_id`, `batch_id`, `teacher_id`, `title`, `description`, `file_path`, `uploaded_at`) VALUES ('10', '22', '16', 'Letters', 'none', 'Uploads/Collection-of-Admission-Letters.pdf', '2025-11-21 16:07:16');

DROP TABLE IF EXISTS `messages`;
CREATE TABLE `messages` (
  `message_id` int(11) NOT NULL AUTO_INCREMENT,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) DEFAULT NULL,
  `group_id` int(11) DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `attachments` varchar(255) DEFAULT NULL,
  `status` enum('sent','read') DEFAULT 'sent',
  `sent_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`message_id`),
  KEY `sender_id` (`sender_id`),
  KEY `receiver_id` (`receiver_id`),
  CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `messages` (`message_id`, `sender_id`, `receiver_id`, `group_id`, `subject`, `body`, `attachments`, `status`, `sent_at`) VALUES ('7', '31', '32', NULL, 'Welcome Message', 'Welcome to your new school! We are excited to have you join our community. This is a place where you will learn, grow, and make new friends. Together, let\'s make this year full of discoveries, fun, and success. You belong here, and your ideas matter—let them shine!', NULL, 'sent', '2025-10-23 10:45:09');
INSERT INTO `messages` (`message_id`, `sender_id`, `receiver_id`, `group_id`, `subject`, `body`, `attachments`, `status`, `sent_at`) VALUES ('8', '31', '30', NULL, 'Welcome Message', 'Welcome to your new school! We are excited to have you join our community. This is a place where you will learn, grow, and make new friends. Together, let\'s make this year full of discoveries, fun, and success. You belong here, and your ideas matter—let them shine!', NULL, 'sent', '2025-10-23 10:45:28');
INSERT INTO `messages` (`message_id`, `sender_id`, `receiver_id`, `group_id`, `subject`, `body`, `attachments`, `status`, `sent_at`) VALUES ('9', '31', '31', NULL, 'Welcome Message', 'Welcome to your new school! We are excited to have you join our community. This is a place where you will learn, grow, and make new friends. Together, let\'s make this year full of discoveries, fun, and success. You belong here, and your ideas matter—let them shine!', NULL, 'sent', '2025-10-23 10:45:44');
INSERT INTO `messages` (`message_id`, `sender_id`, `receiver_id`, `group_id`, `subject`, `body`, `attachments`, `status`, `sent_at`) VALUES ('10', '31', '17', NULL, 'Welcome Message', 'Welcome to your new school! We are excited to have you join our community. This is a place where you will learn, grow, and make new friends. Together, let\'s make this year full of discoveries, fun, and success. You belong here, and your ideas matter—let them shine!', NULL, 'sent', '2025-10-23 10:46:06');
INSERT INTO `messages` (`message_id`, `sender_id`, `receiver_id`, `group_id`, `subject`, `body`, `attachments`, `status`, `sent_at`) VALUES ('11', '482', '41', NULL, 'INTERNAL 1', 'Prepare for the test that is of tomorrow.', NULL, 'sent', '2025-11-05 21:08:45');

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `date` date NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`notification_id`),
  KEY `student_id` (`student_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `notifications` (`notification_id`, `student_id`, `type`, `title`, `description`, `date`, `is_read`) VALUES ('4', '30', 'Payment', 'Outstanding Balance Alert', 'You have an outstanding balance for Python Essentials (Invoice: INV-2025-10-000082). Please make your payment as soon as possible.', '2025-10-23', '0');
INSERT INTO `notifications` (`notification_id`, `student_id`, `type`, `title`, `description`, `date`, `is_read`) VALUES ('5', '30', 'Payment', 'Outstanding Balance Alert', 'You have an outstanding balance for Python Essentials (Invoice: INV-2025-10-000082). Please make your payment as soon as possible.', '2025-10-23', '0');
INSERT INTO `notifications` (`notification_id`, `student_id`, `type`, `title`, `description`, `date`, `is_read`) VALUES ('6', '17', 'Payment', 'Outstanding Balance Alert', 'You have an outstanding balance for First Tech League (Invoice: INV-2025-10-000085). Please make your payment as soon as possible.', '2025-10-23', '0');
INSERT INTO `notifications` (`notification_id`, `student_id`, `type`, `title`, `description`, `date`, `is_read`) VALUES ('7', '17', 'Payment', 'Outstanding Balance Alert', 'You have an outstanding balance for First Tech League (Invoice: INV-2025-10-000085). Please make your payment as soon as possible.', '2025-10-23', '0');
INSERT INTO `notifications` (`notification_id`, `student_id`, `type`, `title`, `description`, `date`, `is_read`) VALUES ('8', '17', 'Payment', 'Outstanding Balance Alert', 'You have an outstanding balance for Python Essentials (Invoice: INV-2025-10-000084). Please make your payment as soon as possible.', '2025-10-23', '0');
INSERT INTO `notifications` (`notification_id`, `student_id`, `type`, `title`, `description`, `date`, `is_read`) VALUES ('9', '32', 'Payment', 'Outstanding Balance Alert', 'You have an outstanding balance for Python Essentials (Invoice: INV-2025-10-000087). Please make your payment as soon as possible.', '2025-10-25', '0');
INSERT INTO `notifications` (`notification_id`, `student_id`, `type`, `title`, `description`, `date`, `is_read`) VALUES ('10', '29', 'Payment', 'Outstanding Balance Alert', 'You have an outstanding balance for First Tech Challenge (Invoice: INV-2025-10-000092). Please make your payment as soon as possible.', '2025-10-26', '0');
INSERT INTO `notifications` (`notification_id`, `student_id`, `type`, `title`, `description`, `date`, `is_read`) VALUES ('11', '29', 'Payment', 'Outstanding Balance Alert', 'You have an outstanding balance for Python Essentials (Invoice: INV-2025-10-000093). Please make your payment as soon as possible.', '2025-10-27', '0');
INSERT INTO `notifications` (`notification_id`, `student_id`, `type`, `title`, `description`, `date`, `is_read`) VALUES ('12', '39', 'Payment', 'Outstanding Balance Alert', 'You have an outstanding balance for First Tech Challenge (Invoice: INV-2025-10-000112). Please make your payment as soon as possible.', '2025-10-31', '0');
INSERT INTO `notifications` (`notification_id`, `student_id`, `type`, `title`, `description`, `date`, `is_read`) VALUES ('13', '37', 'Payment', 'Outstanding Balance Alert', 'You have an outstanding balance for First Lego League (Invoice: INV-2025-10-000105). Please make your payment as soon as possible.', '2025-10-31', '0');

DROP TABLE IF EXISTS `parent_messages`;
CREATE TABLE `parent_messages` (
  `message_id` int(11) NOT NULL AUTO_INCREMENT,
  `sender_user_id` int(11) NOT NULL,
  `recipient_user_id` int(11) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_read` tinyint(1) DEFAULT 0,
  `student_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`message_id`),
  KEY `sender_user_id` (`sender_user_id`),
  KEY `recipient_user_id` (`recipient_user_id`),
  KEY `student_id` (`student_id`),
  CONSTRAINT `parent_messages_ibfk_1` FOREIGN KEY (`sender_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `parent_messages_ibfk_2` FOREIGN KEY (`recipient_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `parent_messages_ibfk_3` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `parent_messages` (`message_id`, `sender_user_id`, `recipient_user_id`, `subject`, `body`, `sent_at`, `is_read`, `student_id`) VALUES ('4', '468', '31', 'INTERNAL 2 SUBMISSION', 'wwwwww', '2025-10-25 22:54:18', '0', '32');
INSERT INTO `parent_messages` (`message_id`, `sender_user_id`, `recipient_user_id`, `subject`, `body`, `sent_at`, `is_read`, `student_id`) VALUES ('5', '468', '465', 'INTERNAL 2 SUBMISSION', 'Check her Outstanding Balances.', '2025-10-25 22:54:50', '0', '32');

DROP TABLE IF EXISTS `parent_students`;
CREATE TABLE `parent_students` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `relationship` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `parent_id` (`parent_id`),
  KEY `student_id` (`student_id`),
  CONSTRAINT `parent_students_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `parents` (`parent_id`) ON DELETE CASCADE,
  CONSTRAINT `parent_students_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `parent_students` (`id`, `parent_id`, `student_id`, `relationship`, `created_at`) VALUES ('18', '9', '32', 'Mother', '2025-10-22 16:05:03');
INSERT INTO `parent_students` (`id`, `parent_id`, `student_id`, `relationship`, `created_at`) VALUES ('19', '9', '17', 'Mother', '2025-10-26 00:30:19');
INSERT INTO `parent_students` (`id`, `parent_id`, `student_id`, `relationship`, `created_at`) VALUES ('20', '9', '30', 'Mother', '2025-10-26 00:30:30');
INSERT INTO `parent_students` (`id`, `parent_id`, `student_id`, `relationship`, `created_at`) VALUES ('21', '10', '41', 'Mother', '2025-11-05 21:20:16');
INSERT INTO `parent_students` (`id`, `parent_id`, `student_id`, `relationship`, `created_at`) VALUES ('22', '10', '32', 'Mother', '2025-11-05 21:20:25');
INSERT INTO `parent_students` (`id`, `parent_id`, `student_id`, `relationship`, `created_at`) VALUES ('23', '10', '42', 'Mother', '2025-11-05 21:20:38');

DROP TABLE IF EXISTS `parents`;
CREATE TABLE `parents` (
  `parent_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `relationship` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `photo` varchar(255) DEFAULT 'NULL',
  PRIMARY KEY (`parent_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `parents_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `parents` (`parent_id`, `user_id`, `relationship`, `created_at`, `updated_at`, `photo`) VALUES ('9', '468', 'Mother', '2025-10-22 16:01:08', '2025-10-22 16:01:08', 'imageuploads/1761141668_img_68f8e3a411a22.png');
INSERT INTO `parents` (`parent_id`, `user_id`, `relationship`, `created_at`, `updated_at`, `photo`) VALUES ('10', '480', 'Mother', '2025-11-05 20:13:00', '2025-11-05 20:13:00', 'imageuploads/1762366380_img_690b93ac5d4ce.jpg');

DROP TABLE IF EXISTS `parents_groupchat_messages`;
CREATE TABLE `parents_groupchat_messages` (
  `message_id` int(11) NOT NULL AUTO_INCREMENT,
  `sender_user_id` int(11) NOT NULL,
  `body` text DEFAULT NULL,
  `attachment_type` enum('document','audio','picture','video') DEFAULT NULL,
  `attachment_path` varchar(255) DEFAULT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_read` tinyint(1) DEFAULT 0,
  `reply_to` int(11) DEFAULT NULL,
  PRIMARY KEY (`message_id`),
  KEY `sender_user_id` (`sender_user_id`),
  CONSTRAINT `parents_groupchat_messages_ibfk_1` FOREIGN KEY (`sender_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `parents_groupchat_messages` (`message_id`, `sender_user_id`, `body`, `attachment_type`, `attachment_path`, `sent_at`, `is_read`, `reply_to`) VALUES ('14', '468', 'hey', NULL, NULL, '2025-10-22 16:23:26', '0', NULL);
INSERT INTO `parents_groupchat_messages` (`message_id`, `sender_user_id`, `body`, `attachment_type`, `attachment_path`, `sent_at`, `is_read`, `reply_to`) VALUES ('15', '468', 'hows the day doing', NULL, NULL, '2025-10-29 20:46:43', '0', NULL);
INSERT INTO `parents_groupchat_messages` (`message_id`, `sender_user_id`, `body`, `attachment_type`, `attachment_path`, `sent_at`, `is_read`, `reply_to`) VALUES ('16', '468', '', 'audio', 'uploads/groupchat/1761763626_FocusRsa ft Rissy & Mischievous Lorge.mp3', '2025-10-29 20:47:06', '0', NULL);

DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_id` int(11) NOT NULL,
  `payer_user_id` int(11) NOT NULL COMMENT 'user_id of student or parent',
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('cash','bank_transfer','card','mobile_money') NOT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `payment_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('completed','failed','refunded') DEFAULT 'completed',
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`payment_id`),
  KEY `fk_payment_invoice` (`invoice_id`),
  KEY `fk_payment_payer` (`payer_user_id`),
  CONSTRAINT `fk_payment_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`invoice_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payment_payer` FOREIGN KEY (`payer_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `payments` (`payment_id`, `invoice_id`, `payer_user_id`, `amount`, `payment_method`, `reference_number`, `payment_date`, `status`, `notes`) VALUES ('6', '9', '74', '450.00', 'bank_transfer', 'Tebello Sello', '2025-10-23 10:15:15', 'completed', NULL);
INSERT INTO `payments` (`payment_id`, `invoice_id`, `payer_user_id`, `amount`, `payment_method`, `reference_number`, `payment_date`, `status`, `notes`) VALUES ('7', '8', '74', '450.00', 'cash', 'Tebello Sello', '2025-10-23 10:28:34', 'completed', NULL);
INSERT INTO `payments` (`payment_id`, `invoice_id`, `payer_user_id`, `amount`, `payment_method`, `reference_number`, `payment_date`, `status`, `notes`) VALUES ('8', '10', '59', '450.00', 'cash', 'Molaoa', '2025-10-23 17:51:55', 'completed', NULL);
INSERT INTO `payments` (`payment_id`, `invoice_id`, `payer_user_id`, `amount`, `payment_method`, `reference_number`, `payment_date`, `status`, `notes`) VALUES ('9', '11', '59', '450.00', 'mobile_money', 'Molaoa', '2025-10-25 22:09:12', 'completed', NULL);
INSERT INTO `payments` (`payment_id`, `invoice_id`, `payer_user_id`, `amount`, `payment_method`, `reference_number`, `payment_date`, `status`, `notes`) VALUES ('10', '12', '468', '450.00', 'cash', NULL, '2025-10-25 23:05:57', 'completed', NULL);
INSERT INTO `payments` (`payment_id`, `invoice_id`, `payer_user_id`, `amount`, `payment_method`, `reference_number`, `payment_date`, `status`, `notes`) VALUES ('11', '15', '466', '450.00', 'card', 'TXN-20251026-23797747', '2025-10-26 18:48:08', 'completed', NULL);
INSERT INTO `payments` (`payment_id`, `invoice_id`, `payer_user_id`, `amount`, `payment_method`, `reference_number`, `payment_date`, `status`, `notes`) VALUES ('12', '15', '466', '450.00', 'mobile_money', 'TXN-20251026-6C042BF7', '2025-10-26 18:48:32', 'completed', NULL);
INSERT INTO `payments` (`payment_id`, `invoice_id`, `payer_user_id`, `amount`, `payment_method`, `reference_number`, `payment_date`, `status`, `notes`) VALUES ('13', '13', '466', '450.00', 'card', 'TXN-20251026-288DF796', '2025-10-26 18:51:13', 'completed', NULL);
INSERT INTO `payments` (`payment_id`, `invoice_id`, `payer_user_id`, `amount`, `payment_method`, `reference_number`, `payment_date`, `status`, `notes`) VALUES ('14', '16', '59', '450.00', 'card', 'TXN-20251026-A755B659', '2025-10-26 18:59:51', 'completed', NULL);
INSERT INTO `payments` (`payment_id`, `invoice_id`, `payer_user_id`, `amount`, `payment_method`, `reference_number`, `payment_date`, `status`, `notes`) VALUES ('15', '16', '59', '450.00', 'card', 'TXN-20251026-F6CF1A24', '2025-10-26 19:00:06', 'completed', NULL);
INSERT INTO `payments` (`payment_id`, `invoice_id`, `payer_user_id`, `amount`, `payment_method`, `reference_number`, `payment_date`, `status`, `notes`) VALUES ('16', '17', '74', '450.00', 'card', 'TXN-20251026-E8294856', '2025-10-26 19:57:21', 'completed', NULL);
INSERT INTO `payments` (`payment_id`, `invoice_id`, `payer_user_id`, `amount`, `payment_method`, `reference_number`, `payment_date`, `status`, `notes`) VALUES ('17', '19', '73', '300.00', 'cash', 'TXN-20251026-80EA4D01', '2025-10-26 20:03:58', 'completed', NULL);
INSERT INTO `payments` (`payment_id`, `invoice_id`, `payer_user_id`, `amount`, `payment_method`, `reference_number`, `payment_date`, `status`, `notes`) VALUES ('18', '18', '73', '450.00', 'card', 'TXN-20251027-168B6BE7', '2025-10-27 15:01:42', 'completed', NULL);
INSERT INTO `payments` (`payment_id`, `invoice_id`, `payer_user_id`, `amount`, `payment_method`, `reference_number`, `payment_date`, `status`, `notes`) VALUES ('19', '38', '473', '450.00', 'cash', 'TXN-20251028-EDA4D31F', '2025-10-28 09:35:51', 'completed', NULL);
INSERT INTO `payments` (`payment_id`, `invoice_id`, `payer_user_id`, `amount`, `payment_method`, `reference_number`, `payment_date`, `status`, `notes`) VALUES ('20', '44', '475', '450.00', 'card', 'TXN-20251031-ABC2AD2E', '2025-10-31 10:51:10', 'completed', NULL);
INSERT INTO `payments` (`payment_id`, `invoice_id`, `payer_user_id`, `amount`, `payment_method`, `reference_number`, `payment_date`, `status`, `notes`) VALUES ('21', '43', '475', '450.00', 'card', 'TXN-20251031-EBFFF864', '2025-10-31 10:53:28', 'completed', NULL);
INSERT INTO `payments` (`payment_id`, `invoice_id`, `payer_user_id`, `amount`, `payment_method`, `reference_number`, `payment_date`, `status`, `notes`) VALUES ('22', '45', '475', '450.00', 'mobile_money', 'TXN-20251031-33BAAC98', '2025-10-31 11:05:26', 'completed', NULL);
INSERT INTO `payments` (`payment_id`, `invoice_id`, `payer_user_id`, `amount`, `payment_method`, `reference_number`, `payment_date`, `status`, `notes`) VALUES ('23', '37', '473', '450.00', 'cash', 'TXN-20251031-F84E4AF9', '2025-10-31 11:34:50', 'completed', NULL);
INSERT INTO `payments` (`payment_id`, `invoice_id`, `payer_user_id`, `amount`, `payment_method`, `reference_number`, `payment_date`, `status`, `notes`) VALUES ('24', '46', '478', '450.00', 'card', 'TXN-20251031-DA3108BE', '2025-10-31 14:07:26', 'completed', NULL);
INSERT INTO `payments` (`payment_id`, `invoice_id`, `payer_user_id`, `amount`, `payment_method`, `reference_number`, `payment_date`, `status`, `notes`) VALUES ('25', '47', '478', '450.00', 'cash', 'TXN-20251101-9862D6E8', '2025-11-01 08:35:30', 'completed', NULL);
INSERT INTO `payments` (`payment_id`, `invoice_id`, `payer_user_id`, `amount`, `payment_method`, `reference_number`, `payment_date`, `status`, `notes`) VALUES ('26', '48', '478', '450.00', 'cash', 'TXN-20251101-4A04E1AE', '2025-11-01 08:35:56', 'completed', NULL);
INSERT INTO `payments` (`payment_id`, `invoice_id`, `payer_user_id`, `amount`, `payment_method`, `reference_number`, `payment_date`, `status`, `notes`) VALUES ('27', '20', '73', '450.00', 'bank_transfer', 'TXN-20251101-41D55B89', '2025-11-01 08:50:18', 'completed', NULL);
INSERT INTO `payments` (`payment_id`, `invoice_id`, `payer_user_id`, `amount`, `payment_method`, `reference_number`, `payment_date`, `status`, `notes`) VALUES ('28', '19', '73', '150.00', 'card', 'TXN-20251101-6D7EC819', '2025-11-01 08:51:02', 'completed', NULL);
INSERT INTO `payments` (`payment_id`, `invoice_id`, `payer_user_id`, `amount`, `payment_method`, `reference_number`, `payment_date`, `status`, `notes`) VALUES ('29', '49', '481', '450.00', 'bank_transfer', 'TXN-20251105-EFEEB182', '2025-11-05 20:23:04', 'completed', NULL);
INSERT INTO `payments` (`payment_id`, `invoice_id`, `payer_user_id`, `amount`, `payment_method`, `reference_number`, `payment_date`, `status`, `notes`) VALUES ('31', '51', '479', '450.00', 'bank_transfer', 'TXN-20251105-C06F1D4B', '2025-11-05 21:01:59', 'completed', NULL);
INSERT INTO `payments` (`payment_id`, `invoice_id`, `payer_user_id`, `amount`, `payment_method`, `reference_number`, `payment_date`, `status`, `notes`) VALUES ('32', '52', '479', '450.00', 'card', 'TXN-20251105-7BEE607C', '2025-11-05 21:03:00', 'completed', NULL);

DROP TABLE IF EXISTS `student_medical_info`;
CREATE TABLE `student_medical_info` (
  `medical_id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `blood_type` varchar(5) DEFAULT NULL,
  `allergies` varchar(255) DEFAULT NULL,
  `chronic_conditions` varchar(255) DEFAULT NULL,
  `medications` varchar(255) DEFAULT NULL,
  `emergency_contact_name` varchar(100) DEFAULT NULL,
  `emergency_contact_phone` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`medical_id`),
  KEY `student_id` (`student_id`),
  CONSTRAINT `student_medical_info_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `student_medical_info` (`medical_id`, `student_id`, `blood_type`, `allergies`, `chronic_conditions`, `medications`, `emergency_contact_name`, `emergency_contact_phone`, `created_at`, `updated_at`) VALUES ('5', '31', 'AB', 'Noodles', 'non', 'Panado & Morning before', 'Dr Mpesu', '56238859', '2025-10-21 23:08:16', '2025-10-21 23:08:16');
INSERT INTO `student_medical_info` (`medical_id`, `student_id`, `blood_type`, `allergies`, `chronic_conditions`, `medications`, `emergency_contact_name`, `emergency_contact_phone`, `created_at`, `updated_at`) VALUES ('6', '32', 'AB', 'Noodles', 'non', '', 'Dr Sibiya', '56238859', '2025-10-22 15:53:52', '2025-10-22 15:53:52');
INSERT INTO `student_medical_info` (`medical_id`, `student_id`, `blood_type`, `allergies`, `chronic_conditions`, `medications`, `emergency_contact_name`, `emergency_contact_phone`, `created_at`, `updated_at`) VALUES ('7', '41', 'AB', 'Chaos', 'Non', 'Panado & Morning before', 'Molaoa Molaoa', '58375096', '2025-11-05 20:54:12', '2025-11-05 20:54:12');
INSERT INTO `student_medical_info` (`medical_id`, `student_id`, `blood_type`, `allergies`, `chronic_conditions`, `medications`, `emergency_contact_name`, `emergency_contact_phone`, `created_at`, `updated_at`) VALUES ('8', '17', 'o', 'Noodles', 'non', 'Sinamoon', 'Molaoa', '58375096', '2025-11-14 14:34:21', '2025-11-21 16:09:50');

DROP TABLE IF EXISTS `student_messages`;
CREATE TABLE `student_messages` (
  `message_id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `file_path` varchar(255) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `broadcast` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`message_id`),
  KEY `student_id` (`student_id`),
  CONSTRAINT `student_messages_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `student_transport_info`;
CREATE TABLE `student_transport_info` (
  `transport_id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `transport_mode` varchar(50) DEFAULT NULL,
  `route_number` varchar(50) DEFAULT NULL,
  `pick_up_point` varchar(100) DEFAULT NULL,
  `drop_off_point` varchar(100) DEFAULT NULL,
  `guardian_contact` varchar(50) DEFAULT NULL,
  `transport_image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`transport_id`),
  KEY `student_id` (`student_id`),
  CONSTRAINT `student_transport_info_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `student_transport_info` (`transport_id`, `student_id`, `transport_mode`, `route_number`, `pick_up_point`, `drop_off_point`, `guardian_contact`, `transport_image`, `created_at`, `updated_at`) VALUES ('7', '31', 'Taxi', 'A5767RYT', 'Ha Abia', 'Girls coding Academy', '0817718956', 'imageuploads/1761080845_trans_img_68f7f60d21070.jpg', '2025-10-21 23:07:25', '2025-10-21 23:07:48');
INSERT INTO `student_transport_info` (`transport_id`, `student_id`, `transport_mode`, `route_number`, `pick_up_point`, `drop_off_point`, `guardian_contact`, `transport_image`, `created_at`, `updated_at`) VALUES ('8', '32', 'Taxi', 'A5767RYT', 'Ha Abia', 'Girls coding Academy', '56849646', 'imageuploads/1761141126_trans_img_68f8e186dca9e.jpg', '2025-10-22 15:52:06', '2025-10-22 15:52:06');
INSERT INTO `student_transport_info` (`transport_id`, `student_id`, `transport_mode`, `route_number`, `pick_up_point`, `drop_off_point`, `guardian_contact`, `transport_image`, `created_at`, `updated_at`) VALUES ('9', '40', 'Taxi', 'A5767RYT', 'Ha Abia', 'Home', '0817718956', 'imageuploads/1762365023_trans_img_690b8e5fcd446.jpg', '2025-11-05 19:50:23', '2025-11-05 19:50:23');
INSERT INTO `student_transport_info` (`transport_id`, `student_id`, `transport_mode`, `route_number`, `pick_up_point`, `drop_off_point`, `guardian_contact`, `transport_image`, `created_at`, `updated_at`) VALUES ('10', '41', 'Taxi', 'A567FYG', 'Girls Coding Academy', 'Home', '0817718956', NULL, '2025-11-05 20:53:06', '2025-11-05 20:54:12');
INSERT INTO `student_transport_info` (`transport_id`, `student_id`, `transport_mode`, `route_number`, `pick_up_point`, `drop_off_point`, `guardian_contact`, `transport_image`, `created_at`, `updated_at`) VALUES ('11', '17', 'Bus', 'A567FYG', 'Girls Coding Academy', 'Home', '0817718956', NULL, '2025-11-14 14:34:21', '2025-11-21 16:09:50');

DROP TABLE IF EXISTS `students`;
CREATE TABLE `students` (
  `student_id` int(11) NOT NULL AUTO_INCREMENT,
  `student_number` varchar(50) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `photo` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`student_id`),
  UNIQUE KEY `student_number` (`student_number`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `students_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `students` (`student_id`, `student_number`, `user_id`, `photo`) VALUES ('17', '20250803', '59', 'imageuploads/1760788057_img_68f37e598bcf2.png');
INSERT INTO `students` (`student_id`, `student_number`, `user_id`, `photo`) VALUES ('29', '20250801', '73', 'imageuploads/1761078901_img_68f7ee7531cbe.jpg');
INSERT INTO `students` (`student_id`, `student_number`, `user_id`, `photo`) VALUES ('30', '20250802', '74', 'imageuploads/1761078887_img_68f7ee671f379.jpg');
INSERT INTO `students` (`student_id`, `student_number`, `user_id`, `photo`) VALUES ('31', '20250804', '75', 'imageuploads/1761078871_img_68f7ee574afde.jpg');
INSERT INTO `students` (`student_id`, `student_number`, `user_id`, `photo`) VALUES ('32', '20250805', '466', 'imageuploads/1761141332_img_68f8e2549280c.png');
INSERT INTO `students` (`student_id`, `student_number`, `user_id`, `photo`) VALUES ('37', '20250809', '473', 'imageuploads/1761636707_img_690071635bf71.jpg');
INSERT INTO `students` (`student_id`, `student_number`, `user_id`, `photo`) VALUES ('39', '20250842', '475', 'imageuploads/1761898694_img_690470c6b2f2d.png');
INSERT INTO `students` (`student_id`, `student_number`, `user_id`, `photo`) VALUES ('40', NULL, '478', 'imageuploads/1761911886_img_6904a44eb6596.png');
INSERT INTO `students` (`student_id`, `student_number`, `user_id`, `photo`) VALUES ('41', NULL, '479', 'imageuploads/1762366000_img_690b923006026.jpg');
INSERT INTO `students` (`student_id`, `student_number`, `user_id`, `photo`) VALUES ('42', NULL, '481', 'imageuploads/1762366668_img_690b94cc464c4.jpg');

DROP TABLE IF EXISTS `support_inquiries`;
CREATE TABLE `support_inquiries` (
  `inquiry_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `role` enum('admin','student','teacher','parent','guest') NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `priority` enum('low','medium','high') NOT NULL DEFAULT 'medium',
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`inquiry_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `support_inquiries_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `support_inquiries` (`inquiry_id`, `user_id`, `role`, `subject`, `message`, `priority`, `created_at`) VALUES ('1', NULL, 'guest', 'Welcome Message', 'NONE', 'high', '2025-11-06 21:17:20');
INSERT INTO `support_inquiries` (`inquiry_id`, `user_id`, `role`, `subject`, `message`, `priority`, `created_at`) VALUES ('2', '483', '', 'INTERNAL 2 SUBMISSION', 'I have not yet submitted and I do not have Electricity for my computer.', 'medium', '2025-11-10 18:33:54');

DROP TABLE IF EXISTS `teacher_batches`;
CREATE TABLE `teacher_batches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `batch_id` int(11) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `teacher_id` (`teacher_id`,`batch_id`),
  KEY `batch_id` (`batch_id`),
  CONSTRAINT `teacher_batches_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`teacher_id`),
  CONSTRAINT `teacher_batches_ibfk_2` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`batch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `teachers`;
CREATE TABLE `teachers` (
  `teacher_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `subject_speciality` varchar(255) DEFAULT NULL,
  `photo` varchar(255) DEFAULT 'NULL',
  PRIMARY KEY (`teacher_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `teachers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `teachers` (`teacher_id`, `user_id`, `subject_speciality`, `photo`) VALUES ('4', '31', 'Database Head Manager', 'imageuploads/1761079323_img_68f7f01b91d19.jpg');
INSERT INTO `teachers` (`teacher_id`, `user_id`, `subject_speciality`, `photo`) VALUES ('16', '482', NULL, 'imageuploads/1762369076_img_690b9e3438337.png');

DROP TABLE IF EXISTS `temporary_ids`;
CREATE TABLE `temporary_ids` (
  `temp_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `temporary_code` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`temp_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `temporary_ids_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `temporary_ids` (`temp_id`, `user_id`, `temporary_code`, `created_at`, `expires_at`) VALUES ('8', '59', 'TMP_68f37df46659b', '2025-10-18 13:45:56', NULL);
INSERT INTO `temporary_ids` (`temp_id`, `user_id`, `temporary_code`, `created_at`, `expires_at`) VALUES ('20', '73', 'TMP_68f38b7640218', '2025-10-18 14:43:34', NULL);
INSERT INTO `temporary_ids` (`temp_id`, `user_id`, `temporary_code`, `created_at`, `expires_at`) VALUES ('21', '74', 'TMP_68f38c5daa9c5', '2025-10-18 14:47:25', NULL);
INSERT INTO `temporary_ids` (`temp_id`, `user_id`, `temporary_code`, `created_at`, `expires_at`) VALUES ('22', '75', 'TMP_68f600639a45d', '2025-10-20 11:26:59', NULL);
INSERT INTO `temporary_ids` (`temp_id`, `user_id`, `temporary_code`, `created_at`, `expires_at`) VALUES ('23', '466', 'TMP_68f8dfcf9485f', '2025-10-22 15:44:47', NULL);
INSERT INTO `temporary_ids` (`temp_id`, `user_id`, `temporary_code`, `created_at`, `expires_at`) VALUES ('26', '483', 'TMP_69120376e243b', '2025-11-10 17:23:34', NULL);
INSERT INTO `temporary_ids` (`temp_id`, `user_id`, `temporary_code`, `created_at`, `expires_at`) VALUES ('27', '484', 'TMP_69121eb1e4a2b', '2025-11-10 19:19:45', NULL);

DROP TABLE IF EXISTS `test_submissions`;
CREATE TABLE `test_submissions` (
  `submission_id` int(11) NOT NULL AUTO_INCREMENT,
  `test_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `submission_text` text DEFAULT NULL,
  `submission_file` varchar(255) DEFAULT NULL,
  `submitted_at` datetime NOT NULL,
  PRIMARY KEY (`submission_id`),
  KEY `student_id` (`student_id`),
  KEY `test_submissions_ibfk_1` (`test_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `tests`;
CREATE TABLE `tests` (
  `test_id` int(11) NOT NULL AUTO_INCREMENT,
  `batch_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `due_date` date NOT NULL,
  `max_score` decimal(5,2) NOT NULL,
  `resource_file` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  PRIMARY KEY (`test_id`),
  KEY `batch_id` (`batch_id`),
  KEY `teacher_id` (`teacher_id`),
  CONSTRAINT `tests_ibfk_1` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`batch_id`),
  CONSTRAINT `tests_ibfk_2` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`teacher_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `tests` (`test_id`, `batch_id`, `teacher_id`, `title`, `description`, `due_date`, `max_score`, `resource_file`, `created_at`, `status`) VALUES ('7', '21', '4', 'End Assessment', 'none', '2025-11-21', '50.00', 'Uploads/1763734921_Collection-of-Admission-Letters.pdf', '2025-11-21 16:22:01', 'active');

DROP TABLE IF EXISTS `user_verifications`;
CREATE TABLE `user_verifications` (
  `verification_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `verification_token` varchar(255) NOT NULL,
  `status` enum('pending','verified') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `verified_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`verification_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `user_verifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `user_verifications` (`verification_id`, `user_id`, `verification_token`, `status`, `created_at`, `verified_at`) VALUES ('8', '59', 'bbaa373a3a78eef5417c1c60abf9f8fb', 'verified', '2025-10-18 13:45:56', '2025-10-23 09:22:50');
INSERT INTO `user_verifications` (`verification_id`, `user_id`, `verification_token`, `status`, `created_at`, `verified_at`) VALUES ('20', '73', '587db34de0f32ad96b06badb8dd28e48', 'pending', '2025-10-18 14:43:34', NULL);
INSERT INTO `user_verifications` (`verification_id`, `user_id`, `verification_token`, `status`, `created_at`, `verified_at`) VALUES ('21', '74', '55e9f417224d25af050f9599e06a0413', 'pending', '2025-10-18 14:47:25', NULL);
INSERT INTO `user_verifications` (`verification_id`, `user_id`, `verification_token`, `status`, `created_at`, `verified_at`) VALUES ('22', '75', 'c624e4dccf68cfbea80c17a818bb5bdf', 'pending', '2025-10-20 11:26:59', NULL);
INSERT INTO `user_verifications` (`verification_id`, `user_id`, `verification_token`, `status`, `created_at`, `verified_at`) VALUES ('23', '466', '3fd235c08cec8a7c2b5bd6f9997745e5', 'pending', '2025-10-22 15:44:47', NULL);
INSERT INTO `user_verifications` (`verification_id`, `user_id`, `verification_token`, `status`, `created_at`, `verified_at`) VALUES ('26', '483', 'c8aa2f752a58371488422e91fb54d188', 'pending', '2025-11-10 17:23:34', NULL);
INSERT INTO `user_verifications` (`verification_id`, `user_id`, `verification_token`, `status`, `created_at`, `verified_at`) VALUES ('27', '484', '7f3f0ad45e244091756fb39763aa0f89', 'pending', '2025-11-10 19:19:45', NULL);

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `firstName` varchar(100) DEFAULT NULL,
  `lastName` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `username` varchar(50) DEFAULT NULL,
  `role` varchar(20) DEFAULT NULL,
  `gender` varchar(10) DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `IDNumber` varchar(20) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `document` varchar(255) DEFAULT NULL,
  `status` varchar(20) DEFAULT NULL,
  `address_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `username` (`username`),
  KEY `address_id` (`address_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`address_id`) REFERENCES `addresses` (`address_id`)
) ENGINE=InnoDB AUTO_INCREMENT=485 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `users` (`user_id`, `firstName`, `lastName`, `email`, `password`, `username`, `role`, `gender`, `dob`, `IDNumber`, `phone`, `document`, `status`, `address_id`, `created_at`, `updated_at`) VALUES ('31', 'Thato', 'Leqele', 'thato.leqele@girlscoding.ac.gc', '$2y$10$lON12XTovyYhE1nXVDfVGuQpsug1qFRHL.X7IuUCADQuzNuK91P1S', 'koloi', 'teacher', 'Male', '0000-00-00', '12335654665', '58699562', 'uploads/docs/68aeb833476732.55642516.pdf', 'active', '32', '2025-08-27 09:48:03', NULL);
INSERT INTO `users` (`user_id`, `firstName`, `lastName`, `email`, `password`, `username`, `role`, `gender`, `dob`, `IDNumber`, `phone`, `document`, `status`, `address_id`, `created_at`, `updated_at`) VALUES ('59', 'Molaoa', 'Molaoa', 'rissymolaoa216@gmail.com', '$2y$10$u0YM9/dLplCI/oHWuZqj8u9BVpzr/TNeiXI3cfRh4BfG2kNF6gjjW', 'rissy', 'student', 'Male', '2025-10-27', '468714651651', '58375096', 'uploads/CCNA_Certification_Guide_2024_V8 final.pdf', 'active', '59', '2025-10-18 13:45:56', '2025-10-18 13:45:56');
INSERT INTO `users` (`user_id`, `firstName`, `lastName`, `email`, `password`, `username`, `role`, `gender`, `dob`, `IDNumber`, `phone`, `document`, `status`, `address_id`, `created_at`, `updated_at`) VALUES ('73', 'Tshepiso', 'Molaoa', 'mazumbe@gmail.com', '$2y$10$.LD23xqJq88dNBgRniM1MugQWpCBZLl2XmcLpnfoMz67OaXYQPhZm', 'tshepi', 'student', 'Female', '2025-10-21', '468714651651', '0817716743', 'uploads/PGDMAD_101_slm.pdf', 'active', '73', '2025-10-18 14:43:34', '2025-10-18 14:43:34');
INSERT INTO `users` (`user_id`, `firstName`, `lastName`, `email`, `password`, `username`, `role`, `gender`, `dob`, `IDNumber`, `phone`, `document`, `status`, `address_id`, `created_at`, `updated_at`) VALUES ('74', 'Tebello', 'Sello', 'nathnaelsello25@gmail.com', '$2y$10$N8A3Ocgg9sE8QaQkFsXXU.YFK.gUF2fWpZGfDNYQuIw/7IhU5ab.a', 'tebello', 'student', 'Male', '2025-10-06', '468714651651', '0635428965', 'uploads/PGDMAD_101_slm.pdf', 'active', '74', '2025-10-18 14:47:25', '2025-10-18 14:47:25');
INSERT INTO `users` (`user_id`, `firstName`, `lastName`, `email`, `password`, `username`, `role`, `gender`, `dob`, `IDNumber`, `phone`, `document`, `status`, `address_id`, `created_at`, `updated_at`) VALUES ('75', 'Tokollo', 'Serame', 'tokollo@gmail.com', '$2y$10$N8A3Ocgg9sE8QaQkFsXXU.YFK.gUF2fWpZGfDNYQuIw/7IhU5ab.a', 'tokollo', 'student', 'Male', '2025-10-24', '24134516525', '0635245689', 'uploads/Collection-of-Admission-Letters.pdf', 'active', '75', '2025-10-20 11:26:59', '2025-10-20 11:26:59');
INSERT INTO `users` (`user_id`, `firstName`, `lastName`, `email`, `password`, `username`, `role`, `gender`, `dob`, `IDNumber`, `phone`, `document`, `status`, `address_id`, `created_at`, `updated_at`) VALUES ('465', 'Katlego ', 'Johnsonns', 'katlego@johnsonns.com', '$2y$10$N8A3Ocgg9sE8QaQkFsXXU.YFK.gUF2fWpZGfDNYQuIw/7IhU5ab.a', 'katlegojohn', 'admin', 'Male', '2025-10-06', '5464541684', '6245356', NULL, 'active', '31', '2025-08-27 10:10:00', '0000-00-00 00:00:00');
INSERT INTO `users` (`user_id`, `firstName`, `lastName`, `email`, `password`, `username`, `role`, `gender`, `dob`, `IDNumber`, `phone`, `document`, `status`, `address_id`, `created_at`, `updated_at`) VALUES ('466', 'Lerato', 'Thuso', 'leratothuso6@gmail.com', '$2y$10$1GUUWioxZ4jECAj1TFm/2O8/dOsBSPjhaIKn8NC.lEY2TUpBQMroq', 'lerato', 'student', 'Female', '2025-10-16', '5656156555', '6458264', 'uploads/Collection-of-Admission-Letters.pdf', 'active', '76', '2025-10-22 15:44:47', '2025-10-22 15:44:47');
INSERT INTO `users` (`user_id`, `firstName`, `lastName`, `email`, `password`, `username`, `role`, `gender`, `dob`, `IDNumber`, `phone`, `document`, `status`, `address_id`, `created_at`, `updated_at`) VALUES ('468', 'Mohau', 'Motsoahae', 'mohau@gmail.com', '$2y$10$5fSbEdW.7TBNGjMB3AxSVeIr7tOOwQ5FFo5gEjkxKNvGm0DA8Ryw2', 'mohau', 'parent', 'Female', '2025-10-22', '468714651651', '68549568', 'uploads/docs/doc_68f8e3a411c76.pdf', 'active', '56', '2025-10-22 16:01:08', '2025-10-22 16:01:08');
INSERT INTO `users` (`user_id`, `firstName`, `lastName`, `email`, `password`, `username`, `role`, `gender`, `dob`, `IDNumber`, `phone`, `document`, `status`, `address_id`, `created_at`, `updated_at`) VALUES ('473', 'Waza', 'Mbanjwa', 'waza@gmail.com', '$2y$10$PTtNaOTmwd4rv.dz0k7qdORSS6G/To.DcA7GHwrl6bwuUFlwjq6Mm', 'waza', 'student', 'Male', '2025-10-22', '655566955523266', '45254655', 'uploads/docs/doc_690071635ba94.pdf', 'active', '83', '2025-10-28 09:31:47', '2025-10-28 09:31:47');
INSERT INTO `users` (`user_id`, `firstName`, `lastName`, `email`, `password`, `username`, `role`, `gender`, `dob`, `IDNumber`, `phone`, `document`, `status`, `address_id`, `created_at`, `updated_at`) VALUES ('475', 'Karabo', 'Setumo', 'karabo@gmail.com', '$2y$10$CoUK2u9hy2rewAY5opjWnubPkmniV8lEpy.vnjLE6dEOROFS6FmJu', 'karabo', 'student', 'Female', '2025-10-23', '142651856425', '4656517', 'uploads/docs/doc_690470c6b2b9a.pdf', 'active', '85', '2025-10-31 10:18:14', '2025-10-31 10:18:14');
INSERT INTO `users` (`user_id`, `firstName`, `lastName`, `email`, `password`, `username`, `role`, `gender`, `dob`, `IDNumber`, `phone`, `document`, `status`, `address_id`, `created_at`, `updated_at`) VALUES ('478', 'Motlowa', 'Motlowa', 'motlowa@gmail.com', '$2y$10$IYP89VdaZ9ps0dghZm4bTOYkW2LqoTPAnamvrSfc3osZjDMPJuTRe', 'motlowa', 'student', 'Male', '2025-10-30', '1345413426365', '58375096', 'uploads/docs/doc_6904a44eb62a3.pdf', 'active', '88', '2025-10-31 13:58:06', '2025-10-31 13:58:06');
INSERT INTO `users` (`user_id`, `firstName`, `lastName`, `email`, `password`, `username`, `role`, `gender`, `dob`, `IDNumber`, `phone`, `document`, `status`, `address_id`, `created_at`, `updated_at`) VALUES ('479', 'Adivhaho', 'Modau', 'adivhahomodau@gmail.com', '$2y$10$JofIh1MTpsMw5sRI44qPpORoFkKN5Sd7xABm9T9.xNZ0A4kajCGaG', 'Adi', 'student', 'Male', '2003-11-12', '24134516525', '0652354152', 'uploads/docs/doc_690b923005d02.pdf', 'active', '89', '2025-11-05 20:06:40', '2025-11-05 20:06:40');
INSERT INTO `users` (`user_id`, `firstName`, `lastName`, `email`, `password`, `username`, `role`, `gender`, `dob`, `IDNumber`, `phone`, `document`, `status`, `address_id`, `created_at`, `updated_at`) VALUES ('480', 'Ncane', 'Lehakwe', 'sebongile@gmail.com', '$2y$10$St0PnAC60rSJrMouhxUdmeXFWNQtbMC1TIE2GXyhcLK60N8uN6bp6', 'sebongile', 'parent', 'Female', '2025-11-06', '1345413426365', '58375096', 'uploads/docs/doc_690b93ac5d6e7.pdf', 'active', '91', '2025-11-05 20:13:00', '2025-11-05 20:13:00');
INSERT INTO `users` (`user_id`, `firstName`, `lastName`, `email`, `password`, `username`, `role`, `gender`, `dob`, `IDNumber`, `phone`, `document`, `status`, `address_id`, `created_at`, `updated_at`) VALUES ('481', 'Katlego', 'Zondo', 'katlego@gmail.com', '$2y$10$JO0s8tOW7WNPRjwG3wUolu3D7PSv/fcBs1e5sCxX3Cr//1ZacS7X.', 'katlego', 'student', 'Female', '2025-11-21', '468714651651', '58375096', 'uploads/docs/doc_690b94b9afc81.pdf', 'active', '92', '2025-11-05 20:17:29', '2025-11-05 20:17:29');
INSERT INTO `users` (`user_id`, `firstName`, `lastName`, `email`, `password`, `username`, `role`, `gender`, `dob`, `IDNumber`, `phone`, `document`, `status`, `address_id`, `created_at`, `updated_at`) VALUES ('482', 'Limakatso', 'Xaba', 'xaba@gmail.com', '$2y$10$JYpCDxqD3yqjCYJ47FL4Qeggwr12q/yTCCe/NHSUhfIZTrRJbvkvm', 'xaba', 'teacher', 'Female', '2025-11-19', '1455423596', '5684596', NULL, 'active', '94', '2025-11-05 20:57:56', '2025-11-05 20:57:56');
INSERT INTO `users` (`user_id`, `firstName`, `lastName`, `email`, `password`, `username`, `role`, `gender`, `dob`, `IDNumber`, `phone`, `document`, `status`, `address_id`, `created_at`, `updated_at`) VALUES ('483', 'Twin', 'Keketsi', 'keketsi@gmail.com', '$2y$10$8oI0Yw8ViKEnhMZN3VTSU.lkBBGGXSPGbQDD3hjU6YDsFY9Stqm92', 'twin', 'marketing', 'Male', '2025-11-04', '24134516525', '58375090', 'uploads/n.pdf', 'active', '95', '2025-11-10 17:23:34', '2025-11-10 17:23:34');
INSERT INTO `users` (`user_id`, `firstName`, `lastName`, `email`, `password`, `username`, `role`, `gender`, `dob`, `IDNumber`, `phone`, `document`, `status`, `address_id`, `created_at`, `updated_at`) VALUES ('484', 'Palesa', 'Mosiwa', 'palesa@gmail.com', '$2y$10$ElvqBLCxqv5ufKoHor.okemzVLIO0y.ZYrB7Q0pQEyzlsjHJgI2By', 'palesa', 'accounts', 'Female', '2025-10-29', '24134516525', '58375096', 'uploads/n.pdf', 'active', '96', '2025-11-10 19:19:45', '2025-11-10 19:19:45');

COMMIT;
SET AUTOCOMMIT = 1;
