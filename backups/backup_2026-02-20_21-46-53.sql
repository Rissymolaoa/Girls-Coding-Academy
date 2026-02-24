-- Database Backup
-- Generated: 2026-02-20 21:46:53
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `accounts_categories`;
CREATE TABLE `accounts_categories` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `type` enum('income','expense') NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`category_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`activity_id`),
  KEY `fk_activities_teacher` (`teacher_id`),
  KEY `fk_activities_batch` (`batch_id`),
  CONSTRAINT `fk_activities_batch` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`batch_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_activities_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`teacher_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  PRIMARY KEY (`address_id`),
  KEY `idx_address_lookup` (`address1`(100),`streetName`(100),`postalCode`,`district`(50),`country`(50))
) ENGINE=InnoDB AUTO_INCREMENT=124 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `addresses` (`address_id`, `address1`, `streetName`, `postalCode`, `district`, `country`, `created_at`, `updated_at`) VALUES ('32', 'kokwana', 'nokeng', '1864', 'Gauteng', 'South Africa', '2025-08-27 09:48:03', '2025-11-25 16:03:47');
INSERT INTO `addresses` (`address_id`, `address1`, `streetName`, `postalCode`, `district`, `country`, `created_at`, `updated_at`) VALUES ('111', 'Mohalalitoe', 'mohae', '100', 'Maseru', 'Lesotho', '2025-12-03 12:21:42', NULL);
INSERT INTO `addresses` (`address_id`, `address1`, `streetName`, `postalCode`, `district`, `country`, `created_at`, `updated_at`) VALUES ('112', '', '', '', 'Maseru', 'Lesotho', '2025-12-03 12:33:15', '2025-12-11 22:27:35');
INSERT INTO `addresses` (`address_id`, `address1`, `streetName`, `postalCode`, `district`, `country`, `created_at`, `updated_at`) VALUES ('113', 'Tshepisong', 'Friaty', '100', 'Maseru', 'Lesotho', '2025-12-03 13:39:54', '2025-12-09 22:12:49');
INSERT INTO `addresses` (`address_id`, `address1`, `streetName`, `postalCode`, `district`, `country`, `created_at`, `updated_at`) VALUES ('114', 'Tshepisong', 'kololong Ha Moshoeshoe', '400', 'Botha Bothe', 'Lesotho', '2025-12-03 13:48:55', NULL);
INSERT INTO `addresses` (`address_id`, `address1`, `streetName`, `postalCode`, `district`, `country`, `created_at`, `updated_at`) VALUES ('115', 'Medowlands', 'kentu Street', '1869', 'Gauteng', 'South Africa', NULL, '2025-12-03 22:41:27');
INSERT INTO `addresses` (`address_id`, `address1`, `streetName`, `postalCode`, `district`, `country`, `created_at`, `updated_at`) VALUES ('117', 'Santa', 'Thetsane', '100', 'Qacha\'s Nek', 'Lesotho', '2025-12-03 22:55:04', '2025-12-03 22:55:04');
INSERT INTO `addresses` (`address_id`, `address1`, `streetName`, `postalCode`, `district`, `country`, `created_at`, `updated_at`) VALUES ('119', 'Moshoeshoe', 'Thetsane', '400', 'Maseru', 'Lesotho', '2025-12-03 22:59:17', '2025-12-03 22:59:17');
INSERT INTO `addresses` (`address_id`, `address1`, `streetName`, `postalCode`, `district`, `country`, `created_at`, `updated_at`) VALUES ('120', 'Thembisa', 'Thetsane', '100', 'Gauteng', 'Lesotho', NULL, NULL);
INSERT INTO `addresses` (`address_id`, `address1`, `streetName`, `postalCode`, `district`, `country`, `created_at`, `updated_at`) VALUES ('121', 'Thembisa', 'Thetsane', '100', 'Gauteng', 'Lesotho', NULL, NULL);
INSERT INTO `addresses` (`address_id`, `address1`, `streetName`, `postalCode`, `district`, `country`, `created_at`, `updated_at`) VALUES ('122', 'kololong Ha Moshoeshoe', 'Thetsane', '400', 'Guateng', 'Lesotho', NULL, NULL);
INSERT INTO `addresses` (`address_id`, `address1`, `streetName`, `postalCode`, `district`, `country`, `created_at`, `updated_at`) VALUES ('123', 'kololong Ha Moshoeshoe', 'Thetsane', '400', 'Maseru', 'Lesotho', '2025-12-21 22:33:58', NULL);

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
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `admin_announcements` (`announcement_id`, `message`, `file_path`, `picture_path`, `recipients`, `created_at`, `updated_at`) VALUES ('9', 'weeeeeeees', NULL, 'uploads/announcements/images/img_695032e475209.jpg', 'students', '2025-12-27 21:26:28', '2025-12-27 21:26:28');

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
) ENGINE=InnoDB AUTO_INCREMENT=368 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `attendance` (`attendance_id`, `session_id`, `student_id`, `batch_id`, `status`, `marked_by`, `marked_at`) VALUES ('367', '2025-12-12', '51', '23', 'Present', '21', '2025-12-12 14:13:49');

DROP TABLE IF EXISTS `batches`;
CREATE TABLE `batches` (
  `batch_id` int(11) NOT NULL AUTO_INCREMENT,
  `batch_code` varchar(50) NOT NULL,
  `course_id` int(11) NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('active','completed','inactive') DEFAULT 'active',
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`batch_id`),
  UNIQUE KEY `batch_code` (`batch_code`),
  KEY `course_id` (`course_id`),
  CONSTRAINT `batches_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `batches` (`batch_id`, `batch_code`, `course_id`, `start_date`, `end_date`, `status`, `deleted_at`) VALUES ('21', 'FLL-2025-08-12', '26', '2025-10-21', '2025-12-31', 'active', NULL);
INSERT INTO `batches` (`batch_id`, `batch_code`, `course_id`, `start_date`, `end_date`, `status`, `deleted_at`) VALUES ('22', 'FTC-2025-08-12', '27', '2025-10-21', '2025-12-31', 'active', NULL);
INSERT INTO `batches` (`batch_id`, `batch_code`, `course_id`, `start_date`, `end_date`, `status`, `deleted_at`) VALUES ('23', 'PYT-2025-08-12', '28', '2025-10-21', '2025-12-31', 'active', NULL);
INSERT INTO `batches` (`batch_id`, `batch_code`, `course_id`, `start_date`, `end_date`, `status`, `deleted_at`) VALUES ('28', 'ARC1_2025_12_001', '34', '2025-11-30', '2025-12-31', 'completed', NULL);
INSERT INTO `batches` (`batch_id`, `batch_code`, `course_id`, `start_date`, `end_date`, `status`, `deleted_at`) VALUES ('29', 'NATE_2025_12_001', '33', '2025-11-30', '2025-12-31', 'active', NULL);

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
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `course_assignments` (`assignment_id`, `teacher_id`, `batch_id`, `created_at`) VALUES ('31', '21', '23', '2025-12-03 13:42:46');

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
) ENGINE=InnoDB AUTO_INCREMENT=138 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `course_enrollments` (`enrollment_id`, `enrollment_number`, `student_id`, `batch_id`, `enrolled_at`, `status`) VALUES ('134', 'ENR-2025-12-134000', '51', '29', '2025-12-03 13:56:32', 'active');
INSERT INTO `course_enrollments` (`enrollment_id`, `enrollment_number`, `student_id`, `batch_id`, `enrolled_at`, `status`) VALUES ('135', 'ENR-2025-12-135000', '51', '28', '2025-12-09 23:25:37', 'active');
INSERT INTO `course_enrollments` (`enrollment_id`, `enrollment_number`, `student_id`, `batch_id`, `enrolled_at`, `status`) VALUES ('136', 'ENR-2025-12-136000', '51', '21', '2025-12-09 23:30:00', 'active');
INSERT INTO `course_enrollments` (`enrollment_id`, `enrollment_number`, `student_id`, `batch_id`, `enrolled_at`, `status`) VALUES ('137', 'ENR-2025-12-137000', '51', '23', '2025-12-09 23:30:15', '');

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

DROP TABLE IF EXISTS `course_inquiries`;
CREATE TABLE `course_inquiries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `course_id` int(11) DEFAULT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `replied_at` datetime DEFAULT NULL,
  `status` enum('pending','replied') DEFAULT 'pending',
  PRIMARY KEY (`id`),
  KEY `course_id` (`course_id`),
  CONSTRAINT `course_inquiries_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `course_inquiries` (`id`, `name`, `email`, `phone`, `course_id`, `message`, `created_at`, `replied_at`, `status`) VALUES ('2', 'Litshoanelo', 'litshoanelo@gmail.com', '58375078', '34', 'I want to know what are the tools needed for this course as a full budget recommendation.', '2025-12-09 23:10:36', '2026-01-26 15:08:54', 'replied');

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
  `status` enum('active','inactive','deleted') DEFAULT 'active',
  `image_path` varchar(255) DEFAULT 'uploads/courses/course1.jpg',
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`course_id`)
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `courses` (`course_id`, `title`, `courseName`, `description`, `category`, `level`, `start_date`, `end_date`, `price`, `status`, `image_path`, `deleted_at`) VALUES ('26', 'FLL', 'First Lego League', 'The First Lego League (FLL) is a global STEM program aimed at children ages 4 to 16 (age varies by country), introducing science, technology, engineering, and math through fun, hands-on learning activities and robotics competitions. The program is structured into three divisions:\r\nive innovators.', 'Robotics', 'Beginner', '2025-10-21', '2026-05-21', '8452.00', 'active', 'Uploads/courses/course_1761078012.png', NULL);
INSERT INTO `courses` (`course_id`, `title`, `courseName`, `description`, `category`, `level`, `start_date`, `end_date`, `price`, `status`, `image_path`, `deleted_at`) VALUES ('27', 'FTC', 'First Tech Challenge', 'The FIRST Tech Challenge (FTC) is a STEM robotics program designed for students in grades 7 through 12. It challenges teams of up to 15 members to design, build, program, and operate robots to compete in head-to-head alliance-based competitions. The program fosters skills in engineering, programming, teamwork, ', 'Robotics', 'Advanced', '2025-10-21', '2026-02-20', '2450.00', 'active', 'Uploads/courses/course_1763374929.jpg', NULL);
INSERT INTO `courses` (`course_id`, `title`, `courseName`, `description`, `category`, `level`, `start_date`, `end_date`, `price`, `status`, `image_path`, `deleted_at`) VALUES ('28', 'PYT', 'Python Essentials', 'A typical Python programming course offers an introduction to programming concepts using Python, suitable for beginners with no prior coding experience. It covers fundamental topics such as variables, data types, conditionals, loops, functions, and basic data structures like lists and dictionaries. Students learn to write and run Python scripts, handle user input, and perform simple file operations. More comprehensive courses also introduce object-oriented programming (OOP), error handling, libraries, and modules.\r\n', 'Coding', 'Advanced', '2025-10-21', '2026-04-21', '8450.00', 'active', 'Uploads/courses/course_1761078373.png', NULL);
INSERT INTO `courses` (`course_id`, `title`, `courseName`, `description`, `category`, `level`, `start_date`, `end_date`, `price`, `status`, `image_path`, `deleted_at`) VALUES ('33', 'NETA', 'Network Administration', 'A Network Administration short course typically provides foundational knowledge and skills needed to manage, configure, monitor, and secure computer networks. It covers network fundamentals including protocols, architectures, and network devices such as routers and switches. Participants learn network configuration, administration, troubleshooting, and security practices to ensure efficient and safe network operations. The course often includes practical components and case studies to develop hands-on experience in managing network infrastructure and resolving connectivity problems.', 'Networking', 'Advanced', '2025-11-30', '2026-06-30', '9550.00', 'active', 'Uploads/courses/course_1764530569.jpg', NULL);
INSERT INTO `courses` (`course_id`, `title`, `courseName`, `description`, `category`, `level`, `start_date`, `end_date`, `price`, `status`, `image_path`, `deleted_at`) VALUES ('34', 'ARC', 'Architecture', 'An Architecture short course, specifically in the context of computer architecture, typically covers the fundamental concepts and components that define how computers are designed and function. It includes the study of computer systems’ structure, the interaction between hardware and software, and the principles behind instruction set architectures (ISA), memory, processing units, and input/output systems. The course often explores models like the von Neumann and Harvard architectures, binary data representation, digital logic, memory management, and performance evaluation.', 'Engineering & Technology', 'Advanced', '2026-01-01', '2026-07-01', '8750.00', 'active', 'Uploads/courses/course_1769067831.png', NULL);

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
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `groupchat_settings`;
CREATE TABLE `groupchat_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `is_blocked` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `processed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`request_id`),
  KEY `teacher_id` (`teacher_id`),
  KEY `enrollment_id` (`enrollment_id`),
  KEY `fk_processed_by` (`processed_by`),
  CONSTRAINT `fk_processed_by` FOREIGN KEY (`processed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `inactivation_requests_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`teacher_id`),
  CONSTRAINT `inactivation_requests_ibfk_2` FOREIGN KEY (`enrollment_id`) REFERENCES `course_enrollments` (`enrollment_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `inactivation_requests` (`request_id`, `enrollment_id`, `student_name`, `student_email`, `batch_code`, `teacher_id`, `reason`, `status`, `created_at`, `processed_by`, `rejection_reason`, `processed_at`) VALUES ('4', '137', 'Tshepo Rapolanka', 'tsheporabiri@gmail.com', 'PYT-2025-08-12', '21', 'He hasn\'t been attending my classes for about a week now.\r\nso I have decided to give him a break in this system and disallow him the access to working board for a while.', 'approved', '2025-12-12 14:21:27', '500', NULL, '2026-01-26 15:12:32');

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
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `internal_grades` (`grade_id`, `student_id`, `batch_id`, `test_1`, `test_2`, `test_3`, `test_4`, `test_5`, `test_6`, `test_7`, `end_examination`, `created_at`) VALUES ('19', '51', '23', '89.00', '72.00', NULL, NULL, NULL, NULL, NULL, NULL, '2025-12-12 14:12:40');

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
) ENGINE=InnoDB AUTO_INCREMENT=62 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `invoices` (`invoice_id`, `enrollment_id`, `invoice_number`, `amount`, `due_date`, `status`, `created_at`, `updated_at`) VALUES ('58', '134', 'INV-2025-12-000134', '950.00', '2026-01-02', 'paid', '2025-12-03 13:56:32', '2025-12-03 13:57:14');
INSERT INTO `invoices` (`invoice_id`, `enrollment_id`, `invoice_number`, `amount`, `due_date`, `status`, `created_at`, `updated_at`) VALUES ('59', '135', 'INV-2025-12-000135', '8750.00', '2026-01-08', 'pending', '2025-12-09 23:25:37', '2025-12-09 23:25:37');
INSERT INTO `invoices` (`invoice_id`, `enrollment_id`, `invoice_number`, `amount`, `due_date`, `status`, `created_at`, `updated_at`) VALUES ('60', '136', 'INV-2025-12-000136', '8452.00', '2026-01-08', 'paid', '2025-12-09 23:30:00', '2026-01-22 09:40:57');
INSERT INTO `invoices` (`invoice_id`, `enrollment_id`, `invoice_number`, `amount`, `due_date`, `status`, `created_at`, `updated_at`) VALUES ('61', '137', 'INV-2025-12-000137', '8450.00', '2026-01-08', 'paid', '2025-12-09 23:30:15', '2025-12-09 23:33:45');

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

DROP TABLE IF EXISTS `materials`;
CREATE TABLE `materials` (
  `material_id` int(11) NOT NULL AUTO_INCREMENT,
  `batch_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`material_id`),
  KEY `batch_id` (`batch_id`),
  KEY `teacher_id` (`teacher_id`),
  CONSTRAINT `materials_ibfk_1` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`batch_id`) ON DELETE CASCADE,
  CONSTRAINT `materials_ibfk_2` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`teacher_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `materials` (`material_id`, `batch_id`, `teacher_id`, `title`, `description`, `file_path`, `uploaded_at`, `deleted_at`) VALUES ('11', '23', '21', 'picture', 'none', 'Uploads/arc.jpg', '2026-01-26 15:34:41', NULL);

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
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `notifications` (`notification_id`, `student_id`, `type`, `title`, `description`, `date`, `is_read`) VALUES ('17', '51', 'Payment', 'Outstanding Balance Alert', 'You have an outstanding balance for Python Essentials (Invoice: INV-2025-12-000137). Please make your payment as soon as possible.', '2025-12-09', '0');
INSERT INTO `notifications` (`notification_id`, `student_id`, `type`, `title`, `description`, `date`, `is_read`) VALUES ('18', '51', 'Payment', 'Outstanding Balance Alert', 'You have an outstanding balance for First Lego League (Invoice: INV-2025-12-000136). Please make your payment as soon as possible.', '2025-12-11', '0');

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
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `parent_students` (`id`, `parent_id`, `student_id`, `relationship`, `created_at`) VALUES ('24', '11', '51', 'Mother', '2025-12-03 13:49:43');

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
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `parents` (`parent_id`, `user_id`, `relationship`, `created_at`, `updated_at`, `photo`) VALUES ('11', '503', 'Mother', '2025-12-03 13:48:55', '2025-12-03 13:51:03', 'imageuploads/1764762663_693024274afeb.jpg');
INSERT INTO `parents` (`parent_id`, `user_id`, `relationship`, `created_at`, `updated_at`, `photo`) VALUES ('12', '509', 'Mother', '2025-12-09 22:48:08', '2025-12-09 22:48:08', 'imageuploads/1765313287_69388b07e9635.jpg');

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
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `payments` (`payment_id`, `invoice_id`, `payer_user_id`, `amount`, `payment_method`, `reference_number`, `payment_date`, `status`, `notes`) VALUES ('38', '58', '501', '950.00', 'bank_transfer', 'TXN-20251203-87B2D164', '2025-12-03 13:57:14', 'completed', NULL);
INSERT INTO `payments` (`payment_id`, `invoice_id`, `payer_user_id`, `amount`, `payment_method`, `reference_number`, `payment_date`, `status`, `notes`) VALUES ('39', '61', '501', '8450.00', 'cash', 'TXN-20251209-0A52C0DD', '2025-12-09 23:33:45', 'completed', NULL);
INSERT INTO `payments` (`payment_id`, `invoice_id`, `payer_user_id`, `amount`, `payment_method`, `reference_number`, `payment_date`, `status`, `notes`) VALUES ('40', '60', '501', '8452.00', 'card', 'TXN-20260122-25F9762F', '2026-01-22 09:40:57', 'completed', NULL);

DROP TABLE IF EXISTS `school_equipment`;
CREATE TABLE `school_equipment` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_name` varchar(150) NOT NULL,
  `category` enum('Computer','Projector','Printer','AC','Fan','Lab Tool','Other') NOT NULL,
  `quantity` int(11) NOT NULL,
  `room_id` int(11) DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `conditions` enum('Working','Needs Repair','Broken') DEFAULT 'Working',
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `room_id` (`room_id`),
  CONSTRAINT `school_equipment_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `school_rooms` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `school_equipment` (`id`, `item_name`, `category`, `quantity`, `room_id`, `purchase_date`, `conditions`, `notes`) VALUES ('5', 'Samsung Phone', 'Computer', '1', '7', '2025-12-27', 'Working', 'M2700');

DROP TABLE IF EXISTS `school_floors`;
CREATE TABLE `school_floors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `floor_name` varchar(50) NOT NULL,
  `building` varchar(100) DEFAULT 'Main Building',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `floor_name` (`floor_name`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `school_floors` (`id`, `floor_name`, `building`, `created_at`) VALUES ('1', 'Ground Floor', 'Main Building', '2025-12-01 16:52:36');
INSERT INTO `school_floors` (`id`, `floor_name`, `building`, `created_at`) VALUES ('2', '1st Floor', 'Main Building', '2025-12-01 16:52:36');
INSERT INTO `school_floors` (`id`, `floor_name`, `building`, `created_at`) VALUES ('3', '2nd Floor', 'Main Building', '2025-12-01 16:52:36');
INSERT INTO `school_floors` (`id`, `floor_name`, `building`, `created_at`) VALUES ('4', '3rd Floor', 'Main Building', '2025-12-01 16:52:36');
INSERT INTO `school_floors` (`id`, `floor_name`, `building`, `created_at`) VALUES ('5', 'Basement', 'Main Building', '2025-12-01 16:52:36');

DROP TABLE IF EXISTS `school_furniture`;
CREATE TABLE `school_furniture` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_name` varchar(150) NOT NULL,
  `category` enum('Desk','Chair','Table','Whiteboard','Cabinet','Shelf','Other') NOT NULL,
  `quantity` int(11) NOT NULL,
  `room_id` int(11) DEFAULT NULL,
  `conditions` enum('Good','Worn','Broken') DEFAULT 'Good',
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `room_id` (`room_id`),
  CONSTRAINT `school_furniture_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `school_rooms` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `school_rooms`;
CREATE TABLE `school_rooms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `room_name` varchar(100) NOT NULL,
  `room_type` enum('Classroom','Lab','Office','Library','Staff Room','Other') NOT NULL,
  `capacity` int(11) NOT NULL,
  `floor` varchar(20) DEFAULT NULL,
  `status` enum('Active','Maintenance','Closed') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `floor_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `floor_id` (`floor_id`),
  CONSTRAINT `school_rooms_ibfk_1` FOREIGN KEY (`floor_id`) REFERENCES `school_floors` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `school_rooms` (`id`, `room_name`, `room_type`, `capacity`, `floor`, `status`, `created_at`, `floor_id`) VALUES ('7', 'Room-03', 'Classroom', '40', NULL, '', '2025-12-09 23:16:16', '2');

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
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `student_medical_info` (`medical_id`, `student_id`, `blood_type`, `allergies`, `chronic_conditions`, `medications`, `emergency_contact_name`, `emergency_contact_phone`, `created_at`, `updated_at`) VALUES ('12', '51', 'A', 'Noodles ink', 'non', 'panado', 'Dr Mulaudzi', '58375068', '2025-12-03 12:38:44', '2025-12-09 23:24:54');

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

DROP TABLE IF EXISTS `student_profile_updates`;
CREATE TABLE `student_profile_updates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `field_name` varchar(100) NOT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  CONSTRAINT `student_profile_updates_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `student_transport_info` (`transport_id`, `student_id`, `transport_mode`, `route_number`, `pick_up_point`, `drop_off_point`, `guardian_contact`, `transport_image`, `created_at`, `updated_at`) VALUES ('13', '51', 'Taxi', 'A5767RYT', 'Girls Coding Academy', 'Home', '68945875', 'imageuploads/1764794804_trans_img_6930a1b449dc6.jpg', '2025-12-03 13:56:06', '2025-12-09 23:24:54');

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
) ENGINE=InnoDB AUTO_INCREMENT=54 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `students` (`student_id`, `student_number`, `user_id`, `photo`) VALUES ('51', NULL, '501', 'imageuploads/1764794629_img_6930a1056b338.jpg');

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

DROP TABLE IF EXISTS `support_tickets`;
CREATE TABLE `support_tickets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `priority` enum('low','medium','high','urgent') DEFAULT NULL,
  `status` enum('open','in-progress','resolved') DEFAULT 'open',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

DROP TABLE IF EXISTS `teacher_timetables`;
CREATE TABLE `teacher_timetables` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `batch_id` int(11) NOT NULL,
  `day` tinyint(4) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `period` tinyint(4) NOT NULL,
  `subject` varchar(100) NOT NULL,
  `room` varchar(50) DEFAULT '',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_slot` (`batch_id`,`day`,`period`),
  UNIQUE KEY `no_overlap` (`batch_id`,`day`,`start_time`,`end_time`),
  CONSTRAINT `teacher_timetables_ibfk_1` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`batch_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `teachers`;
CREATE TABLE `teachers` (
  `teacher_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `subject_speciality` varchar(255) DEFAULT NULL,
  `photo` varchar(255) DEFAULT 'NULL',
  PRIMARY KEY (`teacher_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `teachers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `teachers` (`teacher_id`, `user_id`, `subject_speciality`, `photo`) VALUES ('21', '502', NULL, 'NULL');
INSERT INTO `teachers` (`teacher_id`, `user_id`, `subject_speciality`, `photo`) VALUES ('22', '504', NULL, NULL);

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
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  KEY `test_submissions_ibfk_1` (`test_id`),
  KEY `idx_test_student` (`test_id`,`student_id`),
  CONSTRAINT `fk_test_submissions_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_test_submissions_test` FOREIGN KEY (`test_id`) REFERENCES `tests` (`test_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `testimonials`;
CREATE TABLE `testimonials` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `role` varchar(100) DEFAULT NULL,
  `message` text NOT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `testimonials` (`id`, `name`, `role`, `message`, `photo`, `created_at`) VALUES ('1', 'Relebohile Mokoena', 'Python Graduate 2024', 'GCA gave me confidence I never knew I had. Today I work as a backend developer!', 'testimonials/1.jpg', '2025-12-01 14:15:15');
INSERT INTO `testimonials` (`id`, `name`, `role`, `message`, `photo`, `created_at`) VALUES ('2', 'Nthati Lepheane', 'Robotics Champion', 'I built my first robot at 15. Now I teach robotics to younger girls.', 'testimonials/2.jpg', '2025-12-01 14:15:15');
INSERT INTO `testimonials` (`id`, `name`, `role`, `message`, `photo`, `created_at`) VALUES ('3', 'Kamohelo Ralebitso', 'Full-Stack Developer', 'From zero to launching my startup app in 10 months. Best decision ever.', 'testimonials/3.jpg', '2025-12-01 14:15:15');

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
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_token_expiry` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `username` (`username`),
  KEY `address_id` (`address_id`),
  KEY `idx_reset_token` (`reset_token`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`address_id`) REFERENCES `addresses` (`address_id`)
) ENGINE=InnoDB AUTO_INCREMENT=512 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `users` (`user_id`, `firstName`, `lastName`, `email`, `password`, `username`, `role`, `gender`, `dob`, `IDNumber`, `phone`, `document`, `status`, `address_id`, `created_at`, `updated_at`, `reset_token`, `reset_token_expiry`, `deleted_at`) VALUES ('500', 'Tebello', 'Lipolelo', 'nathnaelsello25@gmail.com', '$2y$10$i8ST8fSexx6x4Bi3OsyzEePbJ8gXch8Okg5S2.0r61pVOcIQCV0lC', 'tebello', 'admin', 'Male', '2025-12-17', '16146466', '58375096', NULL, 'active', '111', '2025-12-03 12:21:42', NULL, NULL, NULL, NULL);
INSERT INTO `users` (`user_id`, `firstName`, `lastName`, `email`, `password`, `username`, `role`, `gender`, `dob`, `IDNumber`, `phone`, `document`, `status`, `address_id`, `created_at`, `updated_at`, `reset_token`, `reset_token_expiry`, `deleted_at`) VALUES ('501', 'Tshepo', 'Rapolanka', 'tsheporabiri@gmail.com', '$2y$10$x./d9HWBPYjNd5Vou/OqT.4MrnfJJL3dRJ/AAHUvYraGRpEtVv9PK', 'rissy', 'student', 'male', '2025-12-03', '12335654665', '5485766', '', 'active', '112', '2025-12-03 12:33:15', NULL, NULL, NULL, NULL);
INSERT INTO `users` (`user_id`, `firstName`, `lastName`, `email`, `password`, `username`, `role`, `gender`, `dob`, `IDNumber`, `phone`, `document`, `status`, `address_id`, `created_at`, `updated_at`, `reset_token`, `reset_token_expiry`, `deleted_at`) VALUES ('502', 'Tshoanelo', 'Pameno', 'rameno@gmail.com', '$2y$10$7Cb1Nep8mAoStWwLz59gLu.cflB5a/4areRcMLoLJJAymSJlqBPau', 'rameno', 'teacher', 'Male', '2025-12-08', '468714651651', '58375096', NULL, 'active', '113', '2025-12-03 13:39:54', NULL, NULL, NULL, NULL);
INSERT INTO `users` (`user_id`, `firstName`, `lastName`, `email`, `password`, `username`, `role`, `gender`, `dob`, `IDNumber`, `phone`, `document`, `status`, `address_id`, `created_at`, `updated_at`, `reset_token`, `reset_token_expiry`, `deleted_at`) VALUES ('503', 'Tshepi', 'Labone', 'parent1@gmail.com', '$2y$10$TDjDVAsXvm4EcwBPKu3Qsu135yr6ob2KwJR6kWkxuZJfURzVIArN6', 'parent', 'parent', 'Female', '2025-12-16', '12335654665', '58375096', NULL, 'active', '114', '2025-12-03 13:48:55', NULL, NULL, NULL, NULL);
INSERT INTO `users` (`user_id`, `firstName`, `lastName`, `email`, `password`, `username`, `role`, `gender`, `dob`, `IDNumber`, `phone`, `document`, `status`, `address_id`, `created_at`, `updated_at`, `reset_token`, `reset_token_expiry`, `deleted_at`) VALUES ('504', 'Dinka', 'Xaba', 'dinka@gmail.com', '$2y$10$MThKVAHrRaT9lq9QemmRK.gkdJ2A.54QhywozqRu4jbMUue5kV2YG', 'xaba', 'teacher', 'Female', '2025-12-22', '576766268', '87545', NULL, 'active', '115', NULL, NULL, NULL, NULL, NULL);
INSERT INTO `users` (`user_id`, `firstName`, `lastName`, `email`, `password`, `username`, `role`, `gender`, `dob`, `IDNumber`, `phone`, `document`, `status`, `address_id`, `created_at`, `updated_at`, `reset_token`, `reset_token_expiry`, `deleted_at`) VALUES ('509', 'Thando', 'Ntsoele', 'thando@gmail.com', '$2y$10$p4HjXrGqfUT2jwlI6wB9e.3tOP6phIXtmhhEaVhb31VXKNotl5OBq', 'thando', 'parent', 'Female', '2025-12-17', '12335654665', '58375096', NULL, 'active', '121', NULL, NULL, NULL, NULL, NULL);

COMMIT;
SET AUTOCOMMIT = 1;
