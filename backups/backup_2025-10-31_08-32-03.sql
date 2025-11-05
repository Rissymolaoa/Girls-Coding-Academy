-- Database Backup
-- Generated: 2025-10-31 08:32:03
-- Database: girlscodingacademydb

SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET AUTOCOMMIT = 0;
START TRANSACTION;

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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=84 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `addresses` (`address_id`, `address1`, `streetName`, `postalCode`, `district`, `country`, `created_at`, `updated_at`) VALUES ('23', 'Maseru', 'Thetsane', 'Lesotho', 'Mathematics', 'Mobile Computist', '2025-08-25 13:57:39', '2025-08-25 13:57:39');
INSERT INTO `addresses` (`address_id`, `address1`, `streetName`, `postalCode`, `district`, `country`, `created_at`, `updated_at`) VALUES ('28', 'santa ', NULL, '1864', 'Gauteng', 'South Africa', '2025-08-26 11:51:38', NULL);
INSERT INTO `addresses` (`address_id`, `address1`, `streetName`, `postalCode`, `district`, `country`, `created_at`, `updated_at`) VALUES ('30', 'Zone 6', 'Santa', '1862', 'Gauteng', 'South Africa', '2025-08-26 19:28:40', NULL);
INSERT INTO `addresses` (`address_id`, `address1`, `streetName`, `postalCode`, `district`, `country`, `created_at`, `updated_at`) VALUES ('31', 'kokwana', 'nokeng', '1862', 'Gauteng', 'South Africa', '2025-08-27 09:47:05', NULL);
INSERT INTO `addresses` (`address_id`, `address1`, `streetName`, `postalCode`, `district`, `country`, `created_at`, `updated_at`) VALUES ('32', 'kokwana', 'nokeng', '1864', 'Gauteng', 'South Africa', '2025-08-27 09:48:03', '2025-10-21 22:42:03');
INSERT INTO `addresses` (`address_id`, `address1`, `streetName`, `postalCode`, `district`, `country`, `created_at`, `updated_at`) VALUES ('34', 'Zone 6', 'Santa', '1864', 'Gauteng', 'South Africa', '2025-08-27 10:31:36', NULL);
INSERT INTO `addresses` (`address_id`, `address1`, `streetName`, `postalCode`, `district`, `country`, `created_at`, `updated_at`) VALUES ('39', 'Santa', 'Laundry', '1862', 'Gauteng', 'South Africa', '2025-08-29 09:03:37', NULL);
INSERT INTO `addresses` (`address_id`, `address1`, `streetName`, `postalCode`, `district`, `country`, `created_at`, `updated_at`) VALUES ('55', 'Zone 14', 'Siyabulela', '1852', 'Guateng', 'South Africa', '2025-09-26 15:08:32', '2025-09-26 15:08:32');
INSERT INTO `addresses` (`address_id`, `address1`, `streetName`, `postalCode`, `district`, `country`, `created_at`, `updated_at`) VALUES ('56', 'Zone 14', 'Siyabulela', '1852', 'Guateng', 'South Africa', '2025-09-26 15:19:18', '2025-09-26 15:19:18');
INSERT INTO `addresses` (`address_id`, `address1`, `streetName`, `postalCode`, `district`, `country`, `created_at`, `updated_at`) VALUES ('59', '4', 'Thetsane', '100', 'Maseru', 'Lesotho', '2025-10-18 13:45:56', '2025-10-23 10:46:44');
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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `admin_announcements` (`announcement_id`, `message`, `file_path`, `picture_path`, `recipients`, `created_at`, `updated_at`) VALUES ('1', 'awe awe guys', NULL, 'uploads/announcements/images/announcement_img_68e4d850bcf29.jpg', 'students', '2025-10-07 11:07:28', '2025-10-07 11:07:28');
INSERT INTO `admin_announcements` (`announcement_id`, `message`, `file_path`, `picture_path`, `recipients`, `created_at`, `updated_at`) VALUES ('2', 'Teachers hello', NULL, NULL, 'teachers', '2025-10-07 16:20:47', '2025-10-07 16:20:47');
INSERT INTO `admin_announcements` (`announcement_id`, `message`, `file_path`, `picture_path`, `recipients`, `created_at`, `updated_at`) VALUES ('3', 'The New Week tomorrow guys, Good Luck', 'uploads/print1.jpg', 'uploads/student1.jpg', 'teachers', '2025-10-26 19:41:33', '2025-10-27 15:03:42');

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
) ENGINE=InnoDB AUTO_INCREMENT=264 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
INSERT INTO `batches` (`batch_id`, `batch_code`, `course_id`, `start_date`, `end_date`, `status`) VALUES ('22', 'FTL-2025-08-12', '27', '2025-10-21', '2025-12-31', 'active');
INSERT INTO `batches` (`batch_id`, `batch_code`, `course_id`, `start_date`, `end_date`, `status`) VALUES ('23', 'PYT-2025-08-12', '28', '2025-10-21', '2025-12-31', 'active');
INSERT INTO `batches` (`batch_id`, `batch_code`, `course_id`, `start_date`, `end_date`, `status`) VALUES ('24', 'WDD-2025-08-12', '29', '2025-10-21', '2025-12-31', 'inactive');

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
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `course_assignments` (`assignment_id`, `teacher_id`, `batch_id`, `created_at`) VALUES ('24', '4', '21', '2025-10-21 22:44:29');
INSERT INTO `course_assignments` (`assignment_id`, `teacher_id`, `batch_id`, `created_at`) VALUES ('25', '4', '23', '2025-10-23 09:46:05');

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
) ENGINE=InnoDB AUTO_INCREMENT=106 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

INSERT INTO `courses` (`course_id`, `title`, `courseName`, `description`, `category`, `level`, `start_date`, `end_date`, `price`, `status`, `image_path`) VALUES ('26', 'FLL', 'First Lego League', 'The First Lego League (FLL) is a global STEM program aimed at children ages 4 to 16 (age varies by country), introducing science, technology, engineering, and math through fun, hands-on learning activities and robotics competitions. The program is structured into three divisions:\r\n\r\n    Discover (ages 4-6): An introductory STEM program that ignites curiosity and builds learning habits through hands-on activities using LEGO DUPLO bricks.\r\n\r\n    Explore (ages 6-10): Focuses on fundamentals of engineering, problem solving, design, and coding, using LEGO bricks powered by LEGO Education SPIKE Essential.\r\n\r\n    Challenge (ages 9-16): At the heart of the program, students engage in research, coding, engineering, and build and program a LEGO robot to perform seasonal missions in a robot game. It promotes teamwork, critical thinking, and creativity through friendly competition.\r\n\r\nFLL courses typically involve guided robotics programs, real-world problem-solving projects, and foster STEM skills alongside collaboration and confidence building. It emphasizes a project-based, hands-on learning approach with educational materials, software, and robot-building components provided to participants. Through these activities, students learn computational thinking, engineering design processes, and gain experience relevant for future STEM careers.\r\n\r\nThe Challenge division also offers a professional development course for educators to effectively implement the program, helping students become confident and creative innovators.', 'Robotics', 'Junior Certificate', '2025-10-21', '2026-01-21', '450.00', 'inactive', 'Uploads/courses/course_1761078012.png');
INSERT INTO `courses` (`course_id`, `title`, `courseName`, `description`, `category`, `level`, `start_date`, `end_date`, `price`, `status`, `image_path`) VALUES ('27', 'FTC', 'First Tech Challenge', 'The FIRST Tech Challenge (FTC) is a STEM robotics program designed for students in grades 7 through 12. It challenges teams of up to 15 members to design, build, program, and operate robots to compete in head-to-head alliance-based competitions. The program fosters skills in engineering, programming, teamwork, fundraising, branding, and community outreach. Teams use a reusable robot kit, which can be programmed using graphical interfaces or Java-based languages.\r\n\r\nEach season presents a new game requiring innovative robot design according to evolving rules, culminating in local, regional, and world championship events. FTC emphasizes Gracious Professionalism®, encouraging high-quality work, respect, and collaboration among participants. The program offers diverse roles beyond robot-building, such as marketing and public speaking, welcoming students with varied skills and interests. Overall, FTC provides hands-on experience in robotics and real-world STEM applications, preparing students for future education and careers in technology and engineering', 'Robotics', 'Junior Certificate', '2025-10-21', '2026-02-20', '450.00', 'inactive', 'Uploads/courses/course_1761078258.png');
INSERT INTO `courses` (`course_id`, `title`, `courseName`, `description`, `category`, `level`, `start_date`, `end_date`, `price`, `status`, `image_path`) VALUES ('28', 'PYT', 'Python Essentials', 'A typical Python programming course offers an introduction to programming concepts using Python, suitable for beginners with no prior coding experience. It covers fundamental topics such as variables, data types, conditionals, loops, functions, and basic data structures like lists and dictionaries. Students learn to write and run Python scripts, handle user input, and perform simple file operations. More comprehensive courses also introduce object-oriented programming (OOP), error handling, libraries, and modules.\r\n\r\nHands-on practice with coding exercises and real-world projects helps students develop problem-solving skills and confidence in programming. The course prepares learners for advanced Python topics like automation, web development, and data science. Many courses are self-paced and include downloadable materials, projects, and quizzes to reinforce learning.\r\n\r\nSuch courses aim to make programming accessible and build a solid foundation for further study or career development in software development or data-related fields.', 'Coding', 'Certificate', '2025-10-21', '2026-04-21', '450.00', 'active', 'Uploads/courses/course_1761078373.png');
INSERT INTO `courses` (`course_id`, `title`, `courseName`, `description`, `category`, `level`, `start_date`, `end_date`, `price`, `status`, `image_path`) VALUES ('29', 'WDD', 'Website Design and Development', 'A web development course for beginners typically covers the fundamentals of building websites and web applications. It starts with introducing HTML for structuring web pages and CSS for styling and layout. Students learn to create responsive designs that work across devices. The course then introduces JavaScript to add interactivity and dynamic behavior to websites, including form validation and event handling.\r\n\r\nBeyond front-end basics, many courses provide a high-level overview of back-end development concepts, including server, database interactions, and APIs. Students may work on hands-on projects like building a personal portfolio site or simple web applications to practice their skills. Emphasis is placed on understanding tools such as code editors, developer browsers, and version control systems.\r\n\r\nThe goal is to equip learners with a solid foundation in both UI and fundamental programming principles, making it easier to advance to frameworks, libraries, or full-stack development. Courses often include practical challenges and guidance on deploying websites live on the internet.', 'Coding', 'Certificate', '2025-10-21', '2026-12-21', '450.00', 'active', 'Uploads/courses/course_1761078518.jpeg');

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
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `events` (`event_id`, `title`, `description`, `event_date`, `event_time_start`, `event_time_end`, `category`, `location`, `photo`, `is_posted`, `created_at`) VALUES ('5', 'Graduation', 'Gradual wait ceremony celebration', '2025-10-02', '09:30:00', '17:30:00', 'Competition', 'Mohalalitoe', 'uploads/events/event_68dceca9dce2e6.62213779.jpg', '1', '2025-10-01 10:27:04');
INSERT INTO `events` (`event_id`, `title`, `description`, `event_date`, `event_time_start`, `event_time_end`, `category`, `location`, `photo`, `is_posted`, `created_at`) VALUES ('6', 'Festival', 'hhhh', '2025-10-17', '10:57:00', '10:58:00', 'Festival', 'Thetsane', 'uploads/events/event_68dcf08a4603b7.31565676.jpg', '1', '2025-10-01 10:57:46');
INSERT INTO `events` (`event_id`, `title`, `description`, `event_date`, `event_time_start`, `event_time_end`, `category`, `location`, `photo`, `is_posted`, `created_at`) VALUES ('7', 'Freshers Ball', 'let\'s enjoy the freshers ball event.', '2025-10-11', '18:00:00', '06:00:00', 'Festival', 'Botho University Campus', 'uploads/events/event_68dcf7120669b9.10740708.jpg', '1', '2025-10-01 11:40:34');
INSERT INTO `events` (`event_id`, `title`, `description`, `event_date`, `event_time_start`, `event_time_end`, `category`, `location`, `photo`, `is_posted`, `created_at`) VALUES ('8', 'Presentation Week', 'presentation preparations for the Competition.', '2025-10-02', '13:00:00', '15:30:00', 'Other', 'Girls Coding Academy', 'uploads/events/event_68dcfa18a4e4c3.30120454.jpg', '1', '2025-10-01 11:47:41');
INSERT INTO `events` (`event_id`, `title`, `description`, `event_date`, `event_time_start`, `event_time_end`, `category`, `location`, `photo`, `is_posted`, `created_at`) VALUES ('9', 'Rissclusive Brand Launching', 'The Launching of the t-shirt for swagg and more professional outlook and presentation', '2025-12-25', '09:00:00', '17:07:00', 'Other', 'Botho University Campus', 'uploads/events/event_68f24dd914df52.13074760.png', '1', '2025-10-17 16:08:25');

DROP TABLE IF EXISTS `groupchat_settings`;
CREATE TABLE `groupchat_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `is_blocked` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `groupchat_settings` (`id`, `is_blocked`) VALUES ('1', '0');

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
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `internal_grades` (`grade_id`, `student_id`, `batch_id`, `test_1`, `test_2`, `test_3`, `test_4`, `test_5`, `test_6`, `test_7`, `end_examination`, `created_at`) VALUES ('8', '30', '21', '99.00', '86.00', '75.00', NULL, NULL, NULL, NULL, NULL, '2025-10-22 09:53:25');
INSERT INTO `internal_grades` (`grade_id`, `student_id`, `batch_id`, `test_1`, `test_2`, `test_3`, `test_4`, `test_5`, `test_6`, `test_7`, `end_examination`, `created_at`) VALUES ('9', '31', '21', '96.00', '99.00', '96.00', NULL, NULL, NULL, NULL, NULL, '2025-10-22 09:53:37');
INSERT INTO `internal_grades` (`grade_id`, `student_id`, `batch_id`, `test_1`, `test_2`, `test_3`, `test_4`, `test_5`, `test_6`, `test_7`, `end_examination`, `created_at`) VALUES ('10', '32', '21', '100.00', '86.00', '83.00', '85.00', '63.00', '69.00', '88.00', NULL, '2025-10-22 16:16:08');
INSERT INTO `internal_grades` (`grade_id`, `student_id`, `batch_id`, `test_1`, `test_2`, `test_3`, `test_4`, `test_5`, `test_6`, `test_7`, `end_examination`, `created_at`) VALUES ('11', '17', '23', '58.00', '85.00', '89.00', '77.00', '82.25', '88.00', '69.00', NULL, '2025-10-25 21:57:02');
INSERT INTO `internal_grades` (`grade_id`, `student_id`, `batch_id`, `test_1`, `test_2`, `test_3`, `test_4`, `test_5`, `test_6`, `test_7`, `end_examination`, `created_at`) VALUES ('12', '30', '23', '89.00', '96.00', '99.00', '64.00', '86.00', '56.00', '66.00', NULL, '2025-10-25 21:59:29');

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
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
INSERT INTO `invoices` (`invoice_id`, `enrollment_id`, `invoice_number`, `amount`, `due_date`, `status`, `created_at`, `updated_at`) VALUES ('19', '92', 'INV-2025-10-000092', '450.00', '2025-11-25', 'pending', '2025-10-26 20:03:23', '2025-10-26 20:03:58');
INSERT INTO `invoices` (`invoice_id`, `enrollment_id`, `invoice_number`, `amount`, `due_date`, `status`, `created_at`, `updated_at`) VALUES ('20', '93', 'INV-2025-10-000093', '450.00', '2025-11-25', 'overdue', '2025-10-26 20:03:24', '2025-10-27 15:31:01');
INSERT INTO `invoices` (`invoice_id`, `enrollment_id`, `invoice_number`, `amount`, `due_date`, `status`, `created_at`, `updated_at`) VALUES ('37', '105', 'INV-2025-10-000105', '450.00', '2025-11-27', 'pending', '2025-10-28 09:32:04', '2025-10-28 09:32:04');
INSERT INTO `invoices` (`invoice_id`, `enrollment_id`, `invoice_number`, `amount`, `due_date`, `status`, `created_at`, `updated_at`) VALUES ('38', '105', 'INV-2025-000038', '450.00', '2025-11-27', 'paid', '2025-10-28 09:32:04', '2025-10-28 09:35:51');

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
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `materials` (`material_id`, `batch_id`, `teacher_id`, `title`, `description`, `file_path`, `uploaded_at`) VALUES ('7', '21', '4', 'Competition Guidlines', 'no description', 'Uploads/activity_68cbe5816f694.pdf', '2025-10-22 15:00:53');
INSERT INTO `materials` (`material_id`, `batch_id`, `teacher_id`, `title`, `description`, `file_path`, `uploaded_at`) VALUES ('8', '23', '4', 'End Examination Preparations', 'prepare for the upcoming exams.', 'Uploads/Collection-of-Admission-Letters.pdf', '2025-10-25 22:04:54');

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
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `messages` (`message_id`, `sender_id`, `receiver_id`, `group_id`, `subject`, `body`, `attachments`, `status`, `sent_at`) VALUES ('7', '31', '32', NULL, 'Welcome Message', 'Welcome to your new school! We are excited to have you join our community. This is a place where you will learn, grow, and make new friends. Together, let\'s make this year full of discoveries, fun, and success. You belong here, and your ideas matter—let them shine!', NULL, 'sent', '2025-10-23 10:45:09');
INSERT INTO `messages` (`message_id`, `sender_id`, `receiver_id`, `group_id`, `subject`, `body`, `attachments`, `status`, `sent_at`) VALUES ('8', '31', '30', NULL, 'Welcome Message', 'Welcome to your new school! We are excited to have you join our community. This is a place where you will learn, grow, and make new friends. Together, let\'s make this year full of discoveries, fun, and success. You belong here, and your ideas matter—let them shine!', NULL, 'sent', '2025-10-23 10:45:28');
INSERT INTO `messages` (`message_id`, `sender_id`, `receiver_id`, `group_id`, `subject`, `body`, `attachments`, `status`, `sent_at`) VALUES ('9', '31', '31', NULL, 'Welcome Message', 'Welcome to your new school! We are excited to have you join our community. This is a place where you will learn, grow, and make new friends. Together, let\'s make this year full of discoveries, fun, and success. You belong here, and your ideas matter—let them shine!', NULL, 'sent', '2025-10-23 10:45:44');
INSERT INTO `messages` (`message_id`, `sender_id`, `receiver_id`, `group_id`, `subject`, `body`, `attachments`, `status`, `sent_at`) VALUES ('10', '31', '17', NULL, 'Welcome Message', 'Welcome to your new school! We are excited to have you join our community. This is a place where you will learn, grow, and make new friends. Together, let\'s make this year full of discoveries, fun, and success. You belong here, and your ideas matter—let them shine!', NULL, 'sent', '2025-10-23 10:46:06');

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
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `notifications` (`notification_id`, `student_id`, `type`, `title`, `description`, `date`, `is_read`) VALUES ('4', '30', 'Payment', 'Outstanding Balance Alert', 'You have an outstanding balance for Python Essentials (Invoice: INV-2025-10-000082). Please make your payment as soon as possible.', '2025-10-23', '0');
INSERT INTO `notifications` (`notification_id`, `student_id`, `type`, `title`, `description`, `date`, `is_read`) VALUES ('5', '30', 'Payment', 'Outstanding Balance Alert', 'You have an outstanding balance for Python Essentials (Invoice: INV-2025-10-000082). Please make your payment as soon as possible.', '2025-10-23', '0');
INSERT INTO `notifications` (`notification_id`, `student_id`, `type`, `title`, `description`, `date`, `is_read`) VALUES ('6', '17', 'Payment', 'Outstanding Balance Alert', 'You have an outstanding balance for First Tech League (Invoice: INV-2025-10-000085). Please make your payment as soon as possible.', '2025-10-23', '0');
INSERT INTO `notifications` (`notification_id`, `student_id`, `type`, `title`, `description`, `date`, `is_read`) VALUES ('7', '17', 'Payment', 'Outstanding Balance Alert', 'You have an outstanding balance for First Tech League (Invoice: INV-2025-10-000085). Please make your payment as soon as possible.', '2025-10-23', '0');
INSERT INTO `notifications` (`notification_id`, `student_id`, `type`, `title`, `description`, `date`, `is_read`) VALUES ('8', '17', 'Payment', 'Outstanding Balance Alert', 'You have an outstanding balance for Python Essentials (Invoice: INV-2025-10-000084). Please make your payment as soon as possible.', '2025-10-23', '0');
INSERT INTO `notifications` (`notification_id`, `student_id`, `type`, `title`, `description`, `date`, `is_read`) VALUES ('9', '32', 'Payment', 'Outstanding Balance Alert', 'You have an outstanding balance for Python Essentials (Invoice: INV-2025-10-000087). Please make your payment as soon as possible.', '2025-10-25', '0');
INSERT INTO `notifications` (`notification_id`, `student_id`, `type`, `title`, `description`, `date`, `is_read`) VALUES ('10', '29', 'Payment', 'Outstanding Balance Alert', 'You have an outstanding balance for First Tech Challenge (Invoice: INV-2025-10-000092). Please make your payment as soon as possible.', '2025-10-26', '0');
INSERT INTO `notifications` (`notification_id`, `student_id`, `type`, `title`, `description`, `date`, `is_read`) VALUES ('11', '29', 'Payment', 'Outstanding Balance Alert', 'You have an outstanding balance for Python Essentials (Invoice: INV-2025-10-000093). Please make your payment as soon as possible.', '2025-10-27', '0');

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
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `parent_students` (`id`, `parent_id`, `student_id`, `relationship`, `created_at`) VALUES ('18', '9', '32', 'Mother', '2025-10-22 16:05:03');
INSERT INTO `parent_students` (`id`, `parent_id`, `student_id`, `relationship`, `created_at`) VALUES ('19', '9', '17', 'Mother', '2025-10-26 00:30:19');
INSERT INTO `parent_students` (`id`, `parent_id`, `student_id`, `relationship`, `created_at`) VALUES ('20', '9', '30', 'Mother', '2025-10-26 00:30:30');

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
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `parents` (`parent_id`, `user_id`, `relationship`, `created_at`, `updated_at`, `photo`) VALUES ('9', '468', 'Mother', '2025-10-22 16:01:08', '2025-10-22 16:01:08', 'imageuploads/1761141668_img_68f8e3a411a22.png');

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
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `student_medical_info` (`medical_id`, `student_id`, `blood_type`, `allergies`, `chronic_conditions`, `medications`, `emergency_contact_name`, `emergency_contact_phone`, `created_at`, `updated_at`) VALUES ('5', '31', 'AB', 'Noodles', 'non', 'Panado & Morning before', 'Dr Mpesu', '56238859', '2025-10-21 23:08:16', '2025-10-21 23:08:16');
INSERT INTO `student_medical_info` (`medical_id`, `student_id`, `blood_type`, `allergies`, `chronic_conditions`, `medications`, `emergency_contact_name`, `emergency_contact_phone`, `created_at`, `updated_at`) VALUES ('6', '32', 'AB', 'Noodles', 'non', '', 'Dr Sibiya', '56238859', '2025-10-22 15:53:52', '2025-10-22 15:53:52');

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
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `student_transport_info` (`transport_id`, `student_id`, `transport_mode`, `route_number`, `pick_up_point`, `drop_off_point`, `guardian_contact`, `transport_image`, `created_at`, `updated_at`) VALUES ('7', '31', 'Taxi', 'A5767RYT', 'Ha Abia', 'Girls coding Academy', '0817718956', 'imageuploads/1761080845_trans_img_68f7f60d21070.jpg', '2025-10-21 23:07:25', '2025-10-21 23:07:48');
INSERT INTO `student_transport_info` (`transport_id`, `student_id`, `transport_mode`, `route_number`, `pick_up_point`, `drop_off_point`, `guardian_contact`, `transport_image`, `created_at`, `updated_at`) VALUES ('8', '32', 'Taxi', 'A5767RYT', 'Ha Abia', 'Girls coding Academy', '56849646', 'imageuploads/1761141126_trans_img_68f8e186dca9e.jpg', '2025-10-22 15:52:06', '2025-10-22 15:52:06');

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
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `students` (`student_id`, `student_number`, `user_id`, `photo`) VALUES ('17', '20250803', '59', 'imageuploads/1760788057_img_68f37e598bcf2.png');
INSERT INTO `students` (`student_id`, `student_number`, `user_id`, `photo`) VALUES ('29', '20250801', '73', 'imageuploads/1761078901_img_68f7ee7531cbe.jpg');
INSERT INTO `students` (`student_id`, `student_number`, `user_id`, `photo`) VALUES ('30', '20250802', '74', 'imageuploads/1761078887_img_68f7ee671f379.jpg');
INSERT INTO `students` (`student_id`, `student_number`, `user_id`, `photo`) VALUES ('31', '20250804', '75', 'imageuploads/1761078871_img_68f7ee574afde.jpg');
INSERT INTO `students` (`student_id`, `student_number`, `user_id`, `photo`) VALUES ('32', '20250805', '466', 'imageuploads/1761141332_img_68f8e2549280c.png');
INSERT INTO `students` (`student_id`, `student_number`, `user_id`, `photo`) VALUES ('37', NULL, '473', 'imageuploads/1761636707_img_690071635bf71.jpg');

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `teachers` (`teacher_id`, `user_id`, `subject_speciality`, `photo`) VALUES ('4', '31', 'Database Head Manager', 'imageuploads/1761079323_img_68f7f01b91d19.jpg');

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
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `temporary_ids` (`temp_id`, `user_id`, `temporary_code`, `created_at`, `expires_at`) VALUES ('8', '59', 'TMP_68f37df46659b', '2025-10-18 13:45:56', NULL);
INSERT INTO `temporary_ids` (`temp_id`, `user_id`, `temporary_code`, `created_at`, `expires_at`) VALUES ('20', '73', 'TMP_68f38b7640218', '2025-10-18 14:43:34', NULL);
INSERT INTO `temporary_ids` (`temp_id`, `user_id`, `temporary_code`, `created_at`, `expires_at`) VALUES ('21', '74', 'TMP_68f38c5daa9c5', '2025-10-18 14:47:25', NULL);
INSERT INTO `temporary_ids` (`temp_id`, `user_id`, `temporary_code`, `created_at`, `expires_at`) VALUES ('22', '75', 'TMP_68f600639a45d', '2025-10-20 11:26:59', NULL);
INSERT INTO `temporary_ids` (`temp_id`, `user_id`, `temporary_code`, `created_at`, `expires_at`) VALUES ('23', '466', 'TMP_68f8dfcf9485f', '2025-10-22 15:44:47', NULL);

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
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `user_verifications` (`verification_id`, `user_id`, `verification_token`, `status`, `created_at`, `verified_at`) VALUES ('8', '59', 'bbaa373a3a78eef5417c1c60abf9f8fb', 'verified', '2025-10-18 13:45:56', '2025-10-23 09:22:50');
INSERT INTO `user_verifications` (`verification_id`, `user_id`, `verification_token`, `status`, `created_at`, `verified_at`) VALUES ('20', '73', '587db34de0f32ad96b06badb8dd28e48', 'pending', '2025-10-18 14:43:34', NULL);
INSERT INTO `user_verifications` (`verification_id`, `user_id`, `verification_token`, `status`, `created_at`, `verified_at`) VALUES ('21', '74', '55e9f417224d25af050f9599e06a0413', 'pending', '2025-10-18 14:47:25', NULL);
INSERT INTO `user_verifications` (`verification_id`, `user_id`, `verification_token`, `status`, `created_at`, `verified_at`) VALUES ('22', '75', 'c624e4dccf68cfbea80c17a818bb5bdf', 'pending', '2025-10-20 11:26:59', NULL);
INSERT INTO `user_verifications` (`verification_id`, `user_id`, `verification_token`, `status`, `created_at`, `verified_at`) VALUES ('23', '466', '3fd235c08cec8a7c2b5bd6f9997745e5', 'pending', '2025-10-22 15:44:47', NULL);

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
) ENGINE=InnoDB AUTO_INCREMENT=474 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `users` (`user_id`, `firstName`, `lastName`, `email`, `password`, `username`, `role`, `gender`, `dob`, `IDNumber`, `phone`, `document`, `status`, `address_id`, `created_at`, `updated_at`) VALUES ('31', 'Thato', 'Leqele', 'thato.leqele@girlscoding.ac.gc', '$2y$10$lON12XTovyYhE1nXVDfVGuQpsug1qFRHL.X7IuUCADQuzNuK91P1S', 'koloi', 'teacher', 'Male', '0000-00-00', '12335654665', '58699562', 'uploads/docs/68aeb833476732.55642516.pdf', 'active', '32', '2025-08-27 09:48:03', NULL);
INSERT INTO `users` (`user_id`, `firstName`, `lastName`, `email`, `password`, `username`, `role`, `gender`, `dob`, `IDNumber`, `phone`, `document`, `status`, `address_id`, `created_at`, `updated_at`) VALUES ('59', 'Molaoa', 'Molaoa', 'rissymolaoa216@gmail.com', '$2y$10$u0YM9/dLplCI/oHWuZqj8u9BVpzr/TNeiXI3cfRh4BfG2kNF6gjjW', 'rissy', 'student', 'Male', '2025-10-27', '468714651651', '58375096', 'uploads/CCNA_Certification_Guide_2024_V8 final.pdf', 'active', '59', '2025-10-18 13:45:56', '2025-10-18 13:45:56');
INSERT INTO `users` (`user_id`, `firstName`, `lastName`, `email`, `password`, `username`, `role`, `gender`, `dob`, `IDNumber`, `phone`, `document`, `status`, `address_id`, `created_at`, `updated_at`) VALUES ('73', 'Tshepiso', 'Molaoa', 'mazumbe@gmail.com', '$2y$10$.LD23xqJq88dNBgRniM1MugQWpCBZLl2XmcLpnfoMz67OaXYQPhZm', 'tshepi', 'student', 'Female', '2025-10-21', '468714651651', '0817716743', 'uploads/PGDMAD_101_slm.pdf', 'active', '73', '2025-10-18 14:43:34', '2025-10-18 14:43:34');
INSERT INTO `users` (`user_id`, `firstName`, `lastName`, `email`, `password`, `username`, `role`, `gender`, `dob`, `IDNumber`, `phone`, `document`, `status`, `address_id`, `created_at`, `updated_at`) VALUES ('74', 'Tebello', 'Sello', 'nathnaelsello25@gmail.com', '$2y$10$N8A3Ocgg9sE8QaQkFsXXU.YFK.gUF2fWpZGfDNYQuIw/7IhU5ab.a', 'tebello', 'student', 'Male', '2025-10-06', '468714651651', '0635428965', 'uploads/PGDMAD_101_slm.pdf', 'active', '74', '2025-10-18 14:47:25', '2025-10-18 14:47:25');
INSERT INTO `users` (`user_id`, `firstName`, `lastName`, `email`, `password`, `username`, `role`, `gender`, `dob`, `IDNumber`, `phone`, `document`, `status`, `address_id`, `created_at`, `updated_at`) VALUES ('75', 'Tokollo', 'Serame', 'tokollo@gmail.com', '$2y$10$N8A3Ocgg9sE8QaQkFsXXU.YFK.gUF2fWpZGfDNYQuIw/7IhU5ab.a', 'tokollo', 'student', 'Male', '2025-10-24', '24134516525', '0635245689', 'uploads/Collection-of-Admission-Letters.pdf', 'active', '75', '2025-10-20 11:26:59', '2025-10-20 11:26:59');
INSERT INTO `users` (`user_id`, `firstName`, `lastName`, `email`, `password`, `username`, `role`, `gender`, `dob`, `IDNumber`, `phone`, `document`, `status`, `address_id`, `created_at`, `updated_at`) VALUES ('465', 'Katlego ', 'Johnsonns', 'katlego@johnsonns.com', '$2y$10$N8A3Ocgg9sE8QaQkFsXXU.YFK.gUF2fWpZGfDNYQuIw/7IhU5ab.a', 'katlegojohn', 'admin', 'Male', '2025-10-06', '5464541684', '6245356', NULL, 'active', '31', '2025-08-27 10:10:00', '0000-00-00 00:00:00');
INSERT INTO `users` (`user_id`, `firstName`, `lastName`, `email`, `password`, `username`, `role`, `gender`, `dob`, `IDNumber`, `phone`, `document`, `status`, `address_id`, `created_at`, `updated_at`) VALUES ('466', 'Lerato', 'Thuso', 'leratothuso6@gmail.com', '$2y$10$1GUUWioxZ4jECAj1TFm/2O8/dOsBSPjhaIKn8NC.lEY2TUpBQMroq', 'lerato', 'student', 'Female', '2025-10-16', '5656156555', '6458264', 'uploads/Collection-of-Admission-Letters.pdf', 'active', '76', '2025-10-22 15:44:47', '2025-10-22 15:44:47');
INSERT INTO `users` (`user_id`, `firstName`, `lastName`, `email`, `password`, `username`, `role`, `gender`, `dob`, `IDNumber`, `phone`, `document`, `status`, `address_id`, `created_at`, `updated_at`) VALUES ('468', 'Mohau', 'Motsoahae', 'mohau@gmail.com', '$2y$10$5fSbEdW.7TBNGjMB3AxSVeIr7tOOwQ5FFo5gEjkxKNvGm0DA8Ryw2', 'mohau', 'parent', 'Female', '2025-10-22', '468714651651', '68549568', 'uploads/docs/doc_68f8e3a411c76.pdf', 'active', '78', '2025-10-22 16:01:08', '2025-10-22 16:01:08');
INSERT INTO `users` (`user_id`, `firstName`, `lastName`, `email`, `password`, `username`, `role`, `gender`, `dob`, `IDNumber`, `phone`, `document`, `status`, `address_id`, `created_at`, `updated_at`) VALUES ('473', 'Waza', 'Mbanjwa', 'waza@gmail.com', '$2y$10$PTtNaOTmwd4rv.dz0k7qdORSS6G/To.DcA7GHwrl6bwuUFlwjq6Mm', 'waza', 'student', 'Male', '2025-10-22', '655566955523266', '45254655', 'uploads/docs/doc_690071635ba94.pdf', 'active', '83', '2025-10-28 09:31:47', '2025-10-28 09:31:47');

COMMIT;
SET AUTOCOMMIT = 1;
