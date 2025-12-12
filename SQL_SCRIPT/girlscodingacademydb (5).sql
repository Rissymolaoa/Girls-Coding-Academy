-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3307
-- Generation Time: Dec 11, 2025 at 09:06 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `girlscodingacademydb`
--

-- --------------------------------------------------------

--
-- Table structure for table `accounts_budgets`
--

CREATE TABLE `accounts_budgets` (
  `budget_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `accounts_categories`
--

CREATE TABLE `accounts_categories` (
  `category_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `type` enum('income','expense') NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `accounts_expenses`
--

CREATE TABLE `accounts_expenses` (
  `expense_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `vendor` varchar(255) NOT NULL,
  `date` date NOT NULL,
  `description` text DEFAULT NULL,
  `receipt_file` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','paid') DEFAULT 'pending',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `accounts_income`
--

CREATE TABLE `accounts_income` (
  `income_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `source` varchar(255) NOT NULL,
  `invoice_id` int(11) DEFAULT NULL,
  `date` date NOT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `activities`
--

CREATE TABLE `activities` (
  `activity_id` int(11) NOT NULL,
  `batch_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `due_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `resource_file` varchar(255) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'active',
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `activity_submissions`
--

CREATE TABLE `activity_submissions` (
  `submission_id` int(11) NOT NULL,
  `activity_id` int(11) NOT NULL,
  `enrollment_id` int(11) NOT NULL,
  `submission_text` text DEFAULT NULL,
  `submission_file` varchar(255) DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `addresses`
--

CREATE TABLE `addresses` (
  `address_id` int(11) NOT NULL,
  `address1` varchar(255) DEFAULT NULL,
  `streetName` varchar(255) DEFAULT NULL,
  `postalCode` varchar(20) DEFAULT NULL,
  `district` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `addresses`
--

INSERT INTO `addresses` (`address_id`, `address1`, `streetName`, `postalCode`, `district`, `country`, `created_at`, `updated_at`) VALUES
(32, 'kokwana', 'nokeng', '1864', 'Gauteng', 'South Africa', '2025-08-27 09:48:03', '2025-11-25 16:03:47'),
(111, 'Mohalalitoe', 'mohae', '100', 'Maseru', 'Lesotho', '2025-12-03 12:21:42', NULL),
(112, '', '', '', 'Maseru', 'Lesotho', '2025-12-03 12:33:15', '2025-12-09 23:24:54'),
(113, 'Tshepisong', 'Friaty', '100', 'Maseru', 'Lesotho', '2025-12-03 13:39:54', '2025-12-09 22:12:49'),
(114, 'Tshepisong', 'kololong Ha Moshoeshoe', '400', 'Botha Bothe', 'Lesotho', '2025-12-03 13:48:55', NULL),
(115, 'Medowlands', 'kentu Street', '1869', 'Gauteng', 'South Africa', NULL, '2025-12-03 22:41:27'),
(116, 'Santa', 'Thetsane', '100', 'Qacha\'s Nek', 'Lesotho', '2025-12-03 22:54:07', '2025-12-03 22:54:07'),
(117, 'Santa', 'Thetsane', '100', 'Qacha\'s Nek', 'Lesotho', '2025-12-03 22:55:04', '2025-12-03 22:55:04'),
(119, 'Moshoeshoe', 'Thetsane', '400', 'Maseru', 'Lesotho', '2025-12-03 22:59:17', '2025-12-03 22:59:17'),
(120, 'Thembisa', 'Thetsane', '100', 'Gauteng', 'Lesotho', NULL, NULL),
(121, 'Thembisa', 'Thetsane', '100', 'Gauteng', 'Lesotho', NULL, NULL),
(122, 'kololong Ha Moshoeshoe', 'Thetsane', '400', 'Guateng', 'Lesotho', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `admin_announcements`
--

CREATE TABLE `admin_announcements` (
  `announcement_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `picture_path` varchar(255) DEFAULT NULL,
  `recipients` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `attendance_id` int(11) NOT NULL,
  `session_id` date NOT NULL,
  `student_id` int(11) NOT NULL,
  `batch_id` int(11) NOT NULL,
  `status` enum('Present','Absent','Late','Sick') NOT NULL,
  `marked_by` int(11) NOT NULL,
  `marked_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `batches`
--

CREATE TABLE `batches` (
  `batch_id` int(11) NOT NULL,
  `batch_code` varchar(50) NOT NULL,
  `course_id` int(11) NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('active','completed','inactive') DEFAULT 'active',
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `batches`
--

INSERT INTO `batches` (`batch_id`, `batch_code`, `course_id`, `start_date`, `end_date`, `status`, `deleted_at`) VALUES
(21, 'FLL-2025-08-12', 26, '2025-10-21', '2025-12-31', 'active', NULL),
(22, 'FTC-2025-08-12', 27, '2025-10-21', '2025-12-31', 'active', NULL),
(23, 'PYT-2025-08-12', 28, '2025-10-21', '2025-12-31', 'active', NULL),
(28, 'ARC1_2025_12_001', 34, '2025-11-30', '2025-12-31', 'active', NULL),
(29, 'NATE_2025_12_001', 33, '2025-11-30', '2025-12-31', 'active', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `class_schedules`
--

CREATE TABLE `class_schedules` (
  `schedule_id` int(11) NOT NULL,
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `course_id` int(11) NOT NULL,
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
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`course_id`, `title`, `courseName`, `description`, `category`, `level`, `start_date`, `end_date`, `price`, `status`, `image_path`, `deleted_at`) VALUES
(26, 'FLL', 'First Lego League', 'The First Lego League (FLL) is a global STEM program aimed at children ages 4 to 16 (age varies by country), introducing science, technology, engineering, and math through fun, hands-on learning activities and robotics competitions. The program is structured into three divisions:\r\nive innovators.', 'Robotics', 'Beginner', '2025-10-21', '2026-05-21', 8452.00, 'active', 'Uploads/courses/course_1761078012.png', NULL),
(27, 'FTC', 'First Tech Challenge', 'The FIRST Tech Challenge (FTC) is a STEM robotics program designed for students in grades 7 through 12. It challenges teams of up to 15 members to design, build, program, and operate robots to compete in head-to-head alliance-based competitions. The program fosters skills in engineering, programming, teamwork, ', 'Robotics', 'Advanced', '2025-10-21', '2026-02-20', 2450.00, 'active', 'Uploads/courses/course_1763374929.jpg', NULL),
(28, 'PYT', 'Python Essentials', 'A typical Python programming course offers an introduction to programming concepts using Python, suitable for beginners with no prior coding experience. It covers fundamental topics such as variables, data types, conditionals, loops, functions, and basic data structures like lists and dictionaries. Students learn to write and run Python scripts, handle user input, and perform simple file operations. More comprehensive courses also introduce object-oriented programming (OOP), error handling, libraries, and modules.\r\n', 'Coding', 'Advanced', '2025-10-21', '2026-04-21', 8450.00, 'active', 'Uploads/courses/course_1761078373.png', NULL),
(33, 'NETA', 'Network Administration', 'A Network Administration short course typically provides foundational knowledge and skills needed to manage, configure, monitor, and secure computer networks. It covers network fundamentals including protocols, architectures, and network devices such as routers and switches. Participants learn network configuration, administration, troubleshooting, and security practices to ensure efficient and safe network operations. The course often includes practical components and case studies to develop hands-on experience in managing network infrastructure and resolving connectivity problems.', 'Networking', 'Advanced', '2025-11-30', '2026-06-30', 9550.00, 'active', 'Uploads/courses/course_1764530569.jpg', NULL),
(34, 'ARC', 'Architecture', 'An Architecture short course, specifically in the context of computer architecture, typically covers the fundamental concepts and components that define how computers are designed and function. It includes the study of computer systems’ structure, the interaction between hardware and software, and the principles behind instruction set architectures (ISA), memory, processing units, and input/output systems. The course often explores models like the von Neumann and Harvard architectures, binary data representation, digital logic, memory management, and performance evaluation.', 'Engineering & Technology', 'Advanced', '2026-01-01', '2026-07-01', 8750.00, 'inactive', 'Uploads/courses/course_1764530752.jpg', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `course_assignments`
--

CREATE TABLE `course_assignments` (
  `assignment_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `batch_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `course_assignments`
--

INSERT INTO `course_assignments` (`assignment_id`, `teacher_id`, `batch_id`, `created_at`) VALUES
(31, 21, 23, '2025-12-03 11:42:46');

-- --------------------------------------------------------

--
-- Table structure for table `course_enrollments`
--

CREATE TABLE `course_enrollments` (
  `enrollment_id` int(11) NOT NULL,
  `enrollment_number` varchar(50) NOT NULL,
  `student_id` int(11) NOT NULL,
  `batch_id` int(11) NOT NULL,
  `enrolled_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('active','completed','dropped') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `course_enrollments`
--

INSERT INTO `course_enrollments` (`enrollment_id`, `enrollment_number`, `student_id`, `batch_id`, `enrolled_at`, `status`) VALUES
(134, 'ENR-2025-12-134000', 51, 29, '2025-12-03 11:56:32', 'active'),
(135, 'ENR-2025-12-135000', 51, 28, '2025-12-09 21:25:37', 'active'),
(136, 'ENR-2025-12-136000', 51, 21, '2025-12-09 21:30:00', 'active'),
(137, 'ENR-2025-12-137000', 51, 23, '2025-12-09 21:30:15', 'active');

--
-- Triggers `course_enrollments`
--
DELIMITER $$
CREATE TRIGGER `after_enrollment_insert` AFTER INSERT ON `course_enrollments` FOR EACH ROW BEGIN
  DECLARE course_price DECIMAL(10,2);
  DECLARE inv_num VARCHAR(50);
  
  -- Get course price from batch -> course
  SELECT c.price INTO course_price
  FROM courses c
  JOIN batches b ON c.course_id = b.course_id
  WHERE b.batch_id = NEW.batch_id;
  
  IF course_price > 0 THEN
    SET inv_num = CONCAT('INV-', YEAR(NOW()), '-', MONTH(NOW()), '-', LPAD(NEW.enrollment_id, 6, '0'));
    
    INSERT INTO invoices (enrollment_id, invoice_number, amount, due_date)
    VALUES (NEW.enrollment_id, inv_num, course_price, DATE_ADD(NOW(), INTERVAL 30 DAY));
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `course_favorites`
--

CREATE TABLE `course_favorites` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `batch_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `course_inquiries`
--

CREATE TABLE `course_inquiries` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `course_id` int(11) DEFAULT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `replied_at` datetime DEFAULT NULL,
  `status` enum('pending','replied') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `course_inquiries`
--

INSERT INTO `course_inquiries` (`id`, `name`, `email`, `phone`, `course_id`, `message`, `created_at`, `replied_at`, `status`) VALUES
(2, 'Litshoanelo', 'litshoanelo@gmail.com', '58375078', 34, 'I want to know what are the tools needed for this course as a full budget recommendation.', '2025-12-09 21:10:36', NULL, 'pending');

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `event_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `event_date` date NOT NULL,
  `event_time_start` time DEFAULT NULL,
  `event_time_end` time DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `is_posted` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `groupchat_settings`
--

CREATE TABLE `groupchat_settings` (
  `id` int(11) NOT NULL,
  `is_blocked` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `help_tickets`
--

CREATE TABLE `help_tickets` (
  `ticket_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `status` enum('open','in_progress','closed') DEFAULT 'open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inactivation_requests`
--

CREATE TABLE `inactivation_requests` (
  `request_id` int(11) NOT NULL,
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
  `processed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `internal_grades`
--

CREATE TABLE `internal_grades` (
  `grade_id` int(11) NOT NULL,
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `invoice_id` int(11) NOT NULL,
  `enrollment_id` int(11) NOT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `due_date` date NOT NULL,
  `status` enum('pending','paid','overdue','cancelled') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `invoices`
--

INSERT INTO `invoices` (`invoice_id`, `enrollment_id`, `invoice_number`, `amount`, `due_date`, `status`, `created_at`, `updated_at`) VALUES
(58, 134, 'INV-2025-12-000134', 950.00, '2026-01-02', 'paid', '2025-12-03 11:56:32', '2025-12-03 11:57:14'),
(59, 135, 'INV-2025-12-000135', 8750.00, '2026-01-08', 'pending', '2025-12-09 21:25:37', '2025-12-09 21:25:37'),
(60, 136, 'INV-2025-12-000136', 8452.00, '2026-01-08', 'pending', '2025-12-09 21:30:00', '2025-12-09 21:30:00'),
(61, 137, 'INV-2025-12-000137', 8450.00, '2026-01-08', 'paid', '2025-12-09 21:30:15', '2025-12-09 21:33:45');

-- --------------------------------------------------------

--
-- Table structure for table `marketing_analytics_logs`
--

CREATE TABLE `marketing_analytics_logs` (
  `log_id` int(11) NOT NULL,
  `event_type` varchar(100) NOT NULL COMMENT 'e.g., view, click, conversion',
  `campaign_id` int(11) DEFAULT NULL,
  `lead_id` int(11) DEFAULT NULL,
  `content_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `marketing_campaigns`
--

CREATE TABLE `marketing_campaigns` (
  `campaign_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `target_audience` enum('students','parents','alumni','teachers','general') NOT NULL,
  `status` enum('draft','active','completed','paused') DEFAULT 'draft',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `marketing_content`
--

CREATE TABLE `marketing_content` (
  `content_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `linked_batch_id` int(11) DEFAULT NULL,
  `linked_event_id` int(11) DEFAULT NULL,
  `uploaded_by` int(11) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `marketing_feedback_responses`
--

CREATE TABLE `marketing_feedback_responses` (
  `response_id` int(11) NOT NULL,
  `survey_id` int(11) NOT NULL,
  `respondent_id` int(11) NOT NULL,
  `answers` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`answers`)),
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `marketing_feedback_surveys`
--

CREATE TABLE `marketing_feedback_surveys` (
  `survey_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `questions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Array of question objects' CHECK (json_valid(`questions`)),
  `target_group` enum('students','parents','teachers','all') NOT NULL,
  `status` enum('active','closed') DEFAULT 'active',
  `responses_count` int(11) DEFAULT 0,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `marketing_leads`
--

CREATE TABLE `marketing_leads` (
  `lead_id` int(11) NOT NULL,
  `campaign_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `source` varchar(255) DEFAULT NULL,
  `status` enum('new','contacted','qualified','converted','lost') DEFAULT 'new',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `marketing_social_posts`
--

CREATE TABLE `marketing_social_posts` (
  `post_id` int(11) NOT NULL,
  `platform` varchar(50) NOT NULL COMMENT 'e.g., twitter, facebook, instagram',
  `content` text NOT NULL,
  `post_url` varchar(500) DEFAULT NULL,
  `engagement_metrics` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '{"likes": 0, "shares": 0, "comments": 0}' CHECK (json_valid(`engagement_metrics`)),
  `campaign_id` int(11) DEFAULT NULL,
  `posted_by` int(11) NOT NULL,
  `posted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `materials`
--

CREATE TABLE `materials` (
  `material_id` int(11) NOT NULL,
  `batch_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `message_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) DEFAULT NULL,
  `group_id` int(11) DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `attachments` varchar(255) DEFAULT NULL,
  `status` enum('sent','read') DEFAULT 'sent',
  `sent_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `date` date NOT NULL,
  `is_read` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `student_id`, `type`, `title`, `description`, `date`, `is_read`) VALUES
(17, 51, 'Payment', 'Outstanding Balance Alert', 'You have an outstanding balance for Python Essentials (Invoice: INV-2025-12-000137). Please make your payment as soon as possible.', '2025-12-09', 0);

-- --------------------------------------------------------

--
-- Table structure for table `parents`
--

CREATE TABLE `parents` (
  `parent_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `relationship` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `photo` varchar(255) DEFAULT 'NULL'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `parents`
--

INSERT INTO `parents` (`parent_id`, `user_id`, `relationship`, `created_at`, `updated_at`, `photo`) VALUES
(11, 503, 'Mother', '2025-12-03 11:48:55', '2025-12-03 11:51:03', 'imageuploads/1764762663_693024274afeb.jpg'),
(12, 509, 'Mother', '2025-12-09 20:48:08', '2025-12-09 20:48:08', 'imageuploads/1765313287_69388b07e9635.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `parents_groupchat_messages`
--

CREATE TABLE `parents_groupchat_messages` (
  `message_id` int(11) NOT NULL,
  `sender_user_id` int(11) NOT NULL,
  `body` text DEFAULT NULL,
  `attachment_type` enum('document','audio','picture','video') DEFAULT NULL,
  `attachment_path` varchar(255) DEFAULT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_read` tinyint(1) DEFAULT 0,
  `reply_to` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `parent_messages`
--

CREATE TABLE `parent_messages` (
  `message_id` int(11) NOT NULL,
  `sender_user_id` int(11) NOT NULL,
  `recipient_user_id` int(11) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_read` tinyint(1) DEFAULT 0,
  `student_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `parent_students`
--

CREATE TABLE `parent_students` (
  `id` int(11) NOT NULL,
  `parent_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `relationship` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `parent_students`
--

INSERT INTO `parent_students` (`id`, `parent_id`, `student_id`, `relationship`, `created_at`) VALUES
(24, 11, 51, 'Mother', '2025-12-03 11:49:43');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `payer_user_id` int(11) NOT NULL COMMENT 'user_id of student or parent',
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('cash','bank_transfer','card','mobile_money') NOT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `payment_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('completed','failed','refunded') DEFAULT 'completed',
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`payment_id`, `invoice_id`, `payer_user_id`, `amount`, `payment_method`, `reference_number`, `payment_date`, `status`, `notes`) VALUES
(38, 58, 501, 950.00, 'bank_transfer', 'TXN-20251203-87B2D164', '2025-12-03 11:57:14', 'completed', NULL),
(39, 61, 501, 8450.00, 'cash', 'TXN-20251209-0A52C0DD', '2025-12-09 21:33:45', 'completed', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `school_equipment`
--

CREATE TABLE `school_equipment` (
  `id` int(11) NOT NULL,
  `item_name` varchar(150) NOT NULL,
  `category` enum('Computer','Projector','Printer','AC','Fan','Lab Tool','Other') NOT NULL,
  `quantity` int(11) NOT NULL,
  `room_id` int(11) DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `conditions` enum('Working','Needs Repair','Broken') DEFAULT 'Working',
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `school_floors`
--

CREATE TABLE `school_floors` (
  `id` int(11) NOT NULL,
  `floor_name` varchar(50) NOT NULL,
  `building` varchar(100) DEFAULT 'Main Building',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `school_floors`
--

INSERT INTO `school_floors` (`id`, `floor_name`, `building`, `created_at`) VALUES
(1, 'Ground Floor', 'Main Building', '2025-12-01 14:52:36'),
(2, '1st Floor', 'Main Building', '2025-12-01 14:52:36'),
(3, '2nd Floor', 'Main Building', '2025-12-01 14:52:36'),
(4, '3rd Floor', 'Main Building', '2025-12-01 14:52:36'),
(5, 'Basement', 'Main Building', '2025-12-01 14:52:36');

-- --------------------------------------------------------

--
-- Table structure for table `school_furniture`
--

CREATE TABLE `school_furniture` (
  `id` int(11) NOT NULL,
  `item_name` varchar(150) NOT NULL,
  `category` enum('Desk','Chair','Table','Whiteboard','Cabinet','Shelf','Other') NOT NULL,
  `quantity` int(11) NOT NULL,
  `room_id` int(11) DEFAULT NULL,
  `conditions` enum('Good','Worn','Broken') DEFAULT 'Good',
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `school_rooms`
--

CREATE TABLE `school_rooms` (
  `id` int(11) NOT NULL,
  `room_name` varchar(100) NOT NULL,
  `room_type` enum('Classroom','Lab','Office','Library','Staff Room','Other') NOT NULL,
  `capacity` int(11) NOT NULL,
  `floor` varchar(20) DEFAULT NULL,
  `status` enum('Active','Maintenance','Closed') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `floor_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `school_rooms`
--

INSERT INTO `school_rooms` (`id`, `room_name`, `room_type`, `capacity`, `floor`, `status`, `created_at`, `floor_id`) VALUES
(7, 'Room-03', 'Classroom', 40, NULL, '', '2025-12-09 21:16:16', 2);

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `student_id` int(11) NOT NULL,
  `student_number` varchar(50) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `photo` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`student_id`, `student_number`, `user_id`, `photo`) VALUES
(51, NULL, 501, 'imageuploads/1764794629_img_6930a1056b338.jpg'),
(52, NULL, 505, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `student_medical_info`
--

CREATE TABLE `student_medical_info` (
  `medical_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `blood_type` varchar(5) DEFAULT NULL,
  `allergies` varchar(255) DEFAULT NULL,
  `chronic_conditions` varchar(255) DEFAULT NULL,
  `medications` varchar(255) DEFAULT NULL,
  `emergency_contact_name` varchar(100) DEFAULT NULL,
  `emergency_contact_phone` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_medical_info`
--

INSERT INTO `student_medical_info` (`medical_id`, `student_id`, `blood_type`, `allergies`, `chronic_conditions`, `medications`, `emergency_contact_name`, `emergency_contact_phone`, `created_at`, `updated_at`) VALUES
(12, 51, 'A', 'Noodles ink', 'non', 'panado', 'Dr Mulaudzi', '58375068', '2025-12-03 10:38:44', '2025-12-09 21:24:54');

-- --------------------------------------------------------

--
-- Table structure for table `student_messages`
--

CREATE TABLE `student_messages` (
  `message_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `file_path` varchar(255) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `broadcast` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_profile_updates`
--

CREATE TABLE `student_profile_updates` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `field_name` varchar(100) NOT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_transport_info`
--

CREATE TABLE `student_transport_info` (
  `transport_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `transport_mode` varchar(50) DEFAULT NULL,
  `route_number` varchar(50) DEFAULT NULL,
  `pick_up_point` varchar(100) DEFAULT NULL,
  `drop_off_point` varchar(100) DEFAULT NULL,
  `guardian_contact` varchar(50) DEFAULT NULL,
  `transport_image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_transport_info`
--

INSERT INTO `student_transport_info` (`transport_id`, `student_id`, `transport_mode`, `route_number`, `pick_up_point`, `drop_off_point`, `guardian_contact`, `transport_image`, `created_at`, `updated_at`) VALUES
(13, 51, 'Taxi', 'A5767RYT', 'Girls Coding Academy', 'Home', '68945875', 'imageuploads/1764794804_trans_img_6930a1b449dc6.jpg', '2025-12-03 11:56:06', '2025-12-09 21:24:54');

-- --------------------------------------------------------

--
-- Table structure for table `support_inquiries`
--

CREATE TABLE `support_inquiries` (
  `inquiry_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `role` enum('admin','student','teacher','parent','guest') NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `priority` enum('low','medium','high') NOT NULL DEFAULT 'medium',
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `support_tickets`
--

CREATE TABLE `support_tickets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `priority` enum('low','medium','high','urgent') DEFAULT NULL,
  `status` enum('open','in-progress','resolved') DEFAULT 'open',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `teachers`
--

CREATE TABLE `teachers` (
  `teacher_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `subject_speciality` varchar(255) DEFAULT NULL,
  `photo` varchar(255) DEFAULT 'NULL'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teachers`
--

INSERT INTO `teachers` (`teacher_id`, `user_id`, `subject_speciality`, `photo`) VALUES
(21, 502, NULL, 'NULL'),
(22, 504, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `teacher_batches`
--

CREATE TABLE `teacher_batches` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `batch_id` int(11) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `teacher_timetables`
--

CREATE TABLE `teacher_timetables` (
  `id` int(11) NOT NULL,
  `batch_id` int(11) NOT NULL,
  `day` tinyint(4) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `period` tinyint(4) NOT NULL,
  `subject` varchar(100) NOT NULL,
  `room` varchar(50) DEFAULT '',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `temporary_ids`
--

CREATE TABLE `temporary_ids` (
  `temp_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `temporary_code` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `temporary_ids`
--

INSERT INTO `temporary_ids` (`temp_id`, `user_id`, `temporary_code`, `created_at`, `expires_at`) VALUES
(35, 505, 'TMP_6930a36f3ec83', '2025-12-03 20:54:07', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `testimonials`
--

CREATE TABLE `testimonials` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `role` varchar(100) DEFAULT NULL,
  `message` text NOT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `testimonials`
--

INSERT INTO `testimonials` (`id`, `name`, `role`, `message`, `photo`, `created_at`) VALUES
(1, 'Relebohile Mokoena', 'Python Graduate 2024', 'GCA gave me confidence I never knew I had. Today I work as a backend developer!', 'testimonials/1.jpg', '2025-12-01 12:15:15'),
(2, 'Nthati Lepheane', 'Robotics Champion', 'I built my first robot at 15. Now I teach robotics to younger girls.', 'testimonials/2.jpg', '2025-12-01 12:15:15'),
(3, 'Kamohelo Ralebitso', 'Full-Stack Developer', 'From zero to launching my startup app in 10 months. Best decision ever.', 'testimonials/3.jpg', '2025-12-01 12:15:15');

-- --------------------------------------------------------

--
-- Table structure for table `tests`
--

CREATE TABLE `tests` (
  `test_id` int(11) NOT NULL,
  `batch_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `due_date` date NOT NULL,
  `max_score` decimal(5,2) NOT NULL,
  `resource_file` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `test_submissions`
--

CREATE TABLE `test_submissions` (
  `submission_id` int(11) NOT NULL,
  `test_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `submission_text` text DEFAULT NULL,
  `submission_file` varchar(255) DEFAULT NULL,
  `submitted_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
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
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `firstName`, `lastName`, `email`, `password`, `username`, `role`, `gender`, `dob`, `IDNumber`, `phone`, `document`, `status`, `address_id`, `created_at`, `updated_at`, `reset_token`, `reset_token_expiry`, `deleted_at`) VALUES
(500, 'Tebello', 'Lipolelo', 'nathnaelsello25@gmail.com', '$2y$10$i8ST8fSexx6x4Bi3OsyzEePbJ8gXch8Okg5S2.0r61pVOcIQCV0lC', 'tebello', 'admin', 'Male', '2025-12-17', '16146466', '58375096', NULL, 'active', 111, '2025-12-03 12:21:42', NULL, NULL, NULL, NULL),
(501, 'Tshepo', 'Rapolanka', 'tsheporabiri@gmail.com', '$2y$10$x./d9HWBPYjNd5Vou/OqT.4MrnfJJL3dRJ/AAHUvYraGRpEtVv9PK', 'rissy', 'student', 'male', '2025-12-03', '12335654665', '5485766', '', 'active', 112, '2025-12-03 12:33:15', NULL, NULL, NULL, NULL),
(502, 'Tshoanelo', 'Pameno', 'rameno@gmail.com', '$2y$10$7Cb1Nep8mAoStWwLz59gLu.cflB5a/4areRcMLoLJJAymSJlqBPau', 'rameno', 'teacher', 'Male', '2025-12-08', '468714651651', '58375096', NULL, 'active', 113, '2025-12-03 13:39:54', NULL, NULL, NULL, NULL),
(503, 'Tshepi', 'Labone', 'parent1@gmail.com', '$2y$10$TDjDVAsXvm4EcwBPKu3Qsu135yr6ob2KwJR6kWkxuZJfURzVIArN6', 'parent', 'parent', 'Female', '2025-12-16', '12335654665', '58375096', NULL, 'active', 114, '2025-12-03 13:48:55', NULL, NULL, NULL, NULL),
(504, 'Dinka', 'Xaba', 'dinka@gmail.com', '$2y$10$MThKVAHrRaT9lq9QemmRK.gkdJ2A.54QhywozqRu4jbMUue5kV2YG', 'xaba', 'teacher', 'Female', '2025-12-22', '576766268', '87545', NULL, 'active', 115, NULL, NULL, NULL, NULL, NULL),
(505, 'Adivhaho', 'Modau', 'adivhahomodau@gmail.com', '$2y$10$vToPtws7DIPhcOKQcqN5D.Ny8EXQz0tNprTVyTb1pW0q2jsjjrjPe', 'adivaho', 'student', 'Male', '2025-12-18', '24134516525', '58375096', 'uploads/1764795247_Collection-of-Admission-Letters.pdf', 'active', 116, '2025-12-03 22:54:07', '2025-12-03 22:54:07', NULL, NULL, NULL),
(509, 'Thando', 'Ntsoele', 'thando@gmail.com', '$2y$10$p4HjXrGqfUT2jwlI6wB9e.3tOP6phIXtmhhEaVhb31VXKNotl5OBq', 'thando', NULL, 'Female', '2025-12-17', '12335654665', '58375096', NULL, 'active', 121, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_verifications`
--

CREATE TABLE `user_verifications` (
  `verification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `verification_token` varchar(255) NOT NULL,
  `status` enum('pending','verified') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `verified_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_verifications`
--

INSERT INTO `user_verifications` (`verification_id`, `user_id`, `verification_token`, `status`, `created_at`, `verified_at`) VALUES
(35, 505, '34f5d126b4b674eb7e0596013c9921ec', 'pending', '2025-12-03 20:54:07', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `accounts_budgets`
--
ALTER TABLE `accounts_budgets`
  ADD PRIMARY KEY (`budget_id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `accounts_budgets_ibfk_2` (`created_by`);

--
-- Indexes for table `accounts_categories`
--
ALTER TABLE `accounts_categories`
  ADD PRIMARY KEY (`category_id`);

--
-- Indexes for table `accounts_expenses`
--
ALTER TABLE `accounts_expenses`
  ADD PRIMARY KEY (`expense_id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `accounts_expenses_ibfk_2` (`created_by`);

--
-- Indexes for table `accounts_income`
--
ALTER TABLE `accounts_income`
  ADD PRIMARY KEY (`income_id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `invoice_id` (`invoice_id`),
  ADD KEY `accounts_income_ibfk_3` (`created_by`);

--
-- Indexes for table `activities`
--
ALTER TABLE `activities`
  ADD PRIMARY KEY (`activity_id`),
  ADD KEY `fk_activities_teacher` (`teacher_id`),
  ADD KEY `fk_activities_batch` (`batch_id`);

--
-- Indexes for table `activity_submissions`
--
ALTER TABLE `activity_submissions`
  ADD PRIMARY KEY (`submission_id`),
  ADD UNIQUE KEY `activity_id` (`activity_id`,`enrollment_id`);

--
-- Indexes for table `addresses`
--
ALTER TABLE `addresses`
  ADD PRIMARY KEY (`address_id`),
  ADD KEY `idx_address_lookup` (`address1`(100),`streetName`(100),`postalCode`,`district`(50),`country`(50));

--
-- Indexes for table `admin_announcements`
--
ALTER TABLE `admin_announcements`
  ADD PRIMARY KEY (`announcement_id`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`attendance_id`),
  ADD UNIQUE KEY `unique_attendance` (`session_id`,`student_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `batch_id` (`batch_id`),
  ADD KEY `marked_by` (`marked_by`);

--
-- Indexes for table `batches`
--
ALTER TABLE `batches`
  ADD PRIMARY KEY (`batch_id`),
  ADD UNIQUE KEY `batch_code` (`batch_code`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `class_schedules`
--
ALTER TABLE `class_schedules`
  ADD PRIMARY KEY (`schedule_id`),
  ADD KEY `batch_id` (`batch_id`),
  ADD KEY `teacher_id` (`teacher_id`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`course_id`);

--
-- Indexes for table `course_assignments`
--
ALTER TABLE `course_assignments`
  ADD PRIMARY KEY (`assignment_id`),
  ADD KEY `fk_teacher` (`teacher_id`),
  ADD KEY `fk_batch` (`batch_id`);

--
-- Indexes for table `course_enrollments`
--
ALTER TABLE `course_enrollments`
  ADD PRIMARY KEY (`enrollment_id`),
  ADD UNIQUE KEY `enrollment_number` (`enrollment_number`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `fk_batch_id` (`batch_id`);

--
-- Indexes for table `course_favorites`
--
ALTER TABLE `course_favorites`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_favorite` (`student_id`,`batch_id`),
  ADD KEY `batch_id` (`batch_id`);

--
-- Indexes for table `course_inquiries`
--
ALTER TABLE `course_inquiries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`event_id`),
  ADD UNIQUE KEY `unique_event` (`title`,`event_date`,`event_time_start`);

--
-- Indexes for table `groupchat_settings`
--
ALTER TABLE `groupchat_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `help_tickets`
--
ALTER TABLE `help_tickets`
  ADD PRIMARY KEY (`ticket_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `inactivation_requests`
--
ALTER TABLE `inactivation_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `teacher_id` (`teacher_id`),
  ADD KEY `enrollment_id` (`enrollment_id`),
  ADD KEY `fk_processed_by` (`processed_by`);

--
-- Indexes for table `internal_grades`
--
ALTER TABLE `internal_grades`
  ADD PRIMARY KEY (`grade_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `batch_id` (`batch_id`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`invoice_id`),
  ADD UNIQUE KEY `invoice_number` (`invoice_number`),
  ADD KEY `fk_invoice_enrollment` (`enrollment_id`);

--
-- Indexes for table `marketing_analytics_logs`
--
ALTER TABLE `marketing_analytics_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `campaign_id` (`campaign_id`),
  ADD KEY `lead_id` (`lead_id`),
  ADD KEY `content_id` (`content_id`);

--
-- Indexes for table `marketing_campaigns`
--
ALTER TABLE `marketing_campaigns`
  ADD PRIMARY KEY (`campaign_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `marketing_content`
--
ALTER TABLE `marketing_content`
  ADD PRIMARY KEY (`content_id`),
  ADD KEY `uploaded_by` (`uploaded_by`),
  ADD KEY `linked_batch_id` (`linked_batch_id`),
  ADD KEY `linked_event_id` (`linked_event_id`);

--
-- Indexes for table `marketing_feedback_responses`
--
ALTER TABLE `marketing_feedback_responses`
  ADD PRIMARY KEY (`response_id`),
  ADD KEY `survey_id` (`survey_id`),
  ADD KEY `respondent_id` (`respondent_id`);

--
-- Indexes for table `marketing_feedback_surveys`
--
ALTER TABLE `marketing_feedback_surveys`
  ADD PRIMARY KEY (`survey_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `marketing_leads`
--
ALTER TABLE `marketing_leads`
  ADD PRIMARY KEY (`lead_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `campaign_id` (`campaign_id`);

--
-- Indexes for table `marketing_social_posts`
--
ALTER TABLE `marketing_social_posts`
  ADD PRIMARY KEY (`post_id`),
  ADD KEY `campaign_id` (`campaign_id`),
  ADD KEY `posted_by` (`posted_by`);

--
-- Indexes for table `materials`
--
ALTER TABLE `materials`
  ADD PRIMARY KEY (`material_id`),
  ADD KEY `batch_id` (`batch_id`),
  ADD KEY `teacher_id` (`teacher_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `parents`
--
ALTER TABLE `parents`
  ADD PRIMARY KEY (`parent_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `parents_groupchat_messages`
--
ALTER TABLE `parents_groupchat_messages`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `sender_user_id` (`sender_user_id`);

--
-- Indexes for table `parent_messages`
--
ALTER TABLE `parent_messages`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `sender_user_id` (`sender_user_id`),
  ADD KEY `recipient_user_id` (`recipient_user_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `parent_students`
--
ALTER TABLE `parent_students`
  ADD PRIMARY KEY (`id`),
  ADD KEY `parent_id` (`parent_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `fk_payment_invoice` (`invoice_id`),
  ADD KEY `fk_payment_payer` (`payer_user_id`);

--
-- Indexes for table `school_equipment`
--
ALTER TABLE `school_equipment`
  ADD PRIMARY KEY (`id`),
  ADD KEY `room_id` (`room_id`);

--
-- Indexes for table `school_floors`
--
ALTER TABLE `school_floors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `floor_name` (`floor_name`);

--
-- Indexes for table `school_furniture`
--
ALTER TABLE `school_furniture`
  ADD PRIMARY KEY (`id`),
  ADD KEY `room_id` (`room_id`);

--
-- Indexes for table `school_rooms`
--
ALTER TABLE `school_rooms`
  ADD PRIMARY KEY (`id`),
  ADD KEY `floor_id` (`floor_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`student_id`),
  ADD UNIQUE KEY `student_number` (`student_number`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `student_medical_info`
--
ALTER TABLE `student_medical_info`
  ADD PRIMARY KEY (`medical_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `student_messages`
--
ALTER TABLE `student_messages`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `student_profile_updates`
--
ALTER TABLE `student_profile_updates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `student_transport_info`
--
ALTER TABLE `student_transport_info`
  ADD PRIMARY KEY (`transport_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `support_inquiries`
--
ALTER TABLE `support_inquiries`
  ADD PRIMARY KEY (`inquiry_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `support_tickets`
--
ALTER TABLE `support_tickets`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `teachers`
--
ALTER TABLE `teachers`
  ADD PRIMARY KEY (`teacher_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `teacher_batches`
--
ALTER TABLE `teacher_batches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `teacher_id` (`teacher_id`,`batch_id`),
  ADD KEY `batch_id` (`batch_id`);

--
-- Indexes for table `teacher_timetables`
--
ALTER TABLE `teacher_timetables`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_slot` (`batch_id`,`day`,`period`),
  ADD UNIQUE KEY `no_overlap` (`batch_id`,`day`,`start_time`,`end_time`);

--
-- Indexes for table `temporary_ids`
--
ALTER TABLE `temporary_ids`
  ADD PRIMARY KEY (`temp_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `testimonials`
--
ALTER TABLE `testimonials`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tests`
--
ALTER TABLE `tests`
  ADD PRIMARY KEY (`test_id`),
  ADD KEY `batch_id` (`batch_id`),
  ADD KEY `teacher_id` (`teacher_id`);

--
-- Indexes for table `test_submissions`
--
ALTER TABLE `test_submissions`
  ADD PRIMARY KEY (`submission_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `test_submissions_ibfk_1` (`test_id`),
  ADD KEY `idx_test_student` (`test_id`,`student_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `address_id` (`address_id`),
  ADD KEY `idx_reset_token` (`reset_token`);

--
-- Indexes for table `user_verifications`
--
ALTER TABLE `user_verifications`
  ADD PRIMARY KEY (`verification_id`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `accounts_budgets`
--
ALTER TABLE `accounts_budgets`
  MODIFY `budget_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `accounts_categories`
--
ALTER TABLE `accounts_categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `accounts_expenses`
--
ALTER TABLE `accounts_expenses`
  MODIFY `expense_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `accounts_income`
--
ALTER TABLE `accounts_income`
  MODIFY `income_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `activities`
--
ALTER TABLE `activities`
  MODIFY `activity_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `activity_submissions`
--
ALTER TABLE `activity_submissions`
  MODIFY `submission_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `addresses`
--
ALTER TABLE `addresses`
  MODIFY `address_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=123;

--
-- AUTO_INCREMENT for table `admin_announcements`
--
ALTER TABLE `admin_announcements`
  MODIFY `announcement_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `attendance_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=367;

--
-- AUTO_INCREMENT for table `batches`
--
ALTER TABLE `batches`
  MODIFY `batch_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `class_schedules`
--
ALTER TABLE `class_schedules`
  MODIFY `schedule_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `course_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `course_assignments`
--
ALTER TABLE `course_assignments`
  MODIFY `assignment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `course_enrollments`
--
ALTER TABLE `course_enrollments`
  MODIFY `enrollment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=138;

--
-- AUTO_INCREMENT for table `course_favorites`
--
ALTER TABLE `course_favorites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `course_inquiries`
--
ALTER TABLE `course_inquiries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `event_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `groupchat_settings`
--
ALTER TABLE `groupchat_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `help_tickets`
--
ALTER TABLE `help_tickets`
  MODIFY `ticket_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `inactivation_requests`
--
ALTER TABLE `inactivation_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `internal_grades`
--
ALTER TABLE `internal_grades`
  MODIFY `grade_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `invoice_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

--
-- AUTO_INCREMENT for table `marketing_analytics_logs`
--
ALTER TABLE `marketing_analytics_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `marketing_campaigns`
--
ALTER TABLE `marketing_campaigns`
  MODIFY `campaign_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `marketing_content`
--
ALTER TABLE `marketing_content`
  MODIFY `content_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `marketing_feedback_responses`
--
ALTER TABLE `marketing_feedback_responses`
  MODIFY `response_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `marketing_feedback_surveys`
--
ALTER TABLE `marketing_feedback_surveys`
  MODIFY `survey_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `marketing_leads`
--
ALTER TABLE `marketing_leads`
  MODIFY `lead_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `marketing_social_posts`
--
ALTER TABLE `marketing_social_posts`
  MODIFY `post_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `materials`
--
ALTER TABLE `materials`
  MODIFY `material_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `parents`
--
ALTER TABLE `parents`
  MODIFY `parent_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `parents_groupchat_messages`
--
ALTER TABLE `parents_groupchat_messages`
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `parent_messages`
--
ALTER TABLE `parent_messages`
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `parent_students`
--
ALTER TABLE `parent_students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `school_equipment`
--
ALTER TABLE `school_equipment`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `school_floors`
--
ALTER TABLE `school_floors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `school_furniture`
--
ALTER TABLE `school_furniture`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `school_rooms`
--
ALTER TABLE `school_rooms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `student_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT for table `student_medical_info`
--
ALTER TABLE `student_medical_info`
  MODIFY `medical_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `student_messages`
--
ALTER TABLE `student_messages`
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `student_profile_updates`
--
ALTER TABLE `student_profile_updates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_transport_info`
--
ALTER TABLE `student_transport_info`
  MODIFY `transport_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `support_inquiries`
--
ALTER TABLE `support_inquiries`
  MODIFY `inquiry_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `support_tickets`
--
ALTER TABLE `support_tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `teachers`
--
ALTER TABLE `teachers`
  MODIFY `teacher_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `teacher_batches`
--
ALTER TABLE `teacher_batches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `teacher_timetables`
--
ALTER TABLE `teacher_timetables`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `temporary_ids`
--
ALTER TABLE `temporary_ids`
  MODIFY `temp_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `testimonials`
--
ALTER TABLE `testimonials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tests`
--
ALTER TABLE `tests`
  MODIFY `test_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `test_submissions`
--
ALTER TABLE `test_submissions`
  MODIFY `submission_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=511;

--
-- AUTO_INCREMENT for table `user_verifications`
--
ALTER TABLE `user_verifications`
  MODIFY `verification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `accounts_budgets`
--
ALTER TABLE `accounts_budgets`
  ADD CONSTRAINT `accounts_budgets_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `accounts_categories` (`category_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `accounts_budgets_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `accounts_expenses`
--
ALTER TABLE `accounts_expenses`
  ADD CONSTRAINT `accounts_expenses_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `accounts_categories` (`category_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `accounts_expenses_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `accounts_income`
--
ALTER TABLE `accounts_income`
  ADD CONSTRAINT `accounts_income_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `accounts_categories` (`category_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `accounts_income_ibfk_2` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`invoice_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `accounts_income_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `activities`
--
ALTER TABLE `activities`
  ADD CONSTRAINT `fk_activities_batch` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`batch_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_activities_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`teacher_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`batch_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_ibfk_3` FOREIGN KEY (`marked_by`) REFERENCES `teachers` (`teacher_id`) ON DELETE CASCADE;

--
-- Constraints for table `batches`
--
ALTER TABLE `batches`
  ADD CONSTRAINT `batches_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`);

--
-- Constraints for table `class_schedules`
--
ALTER TABLE `class_schedules`
  ADD CONSTRAINT `class_schedules_ibfk_1` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`batch_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `class_schedules_ibfk_2` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`teacher_id`) ON DELETE CASCADE;

--
-- Constraints for table `course_assignments`
--
ALTER TABLE `course_assignments`
  ADD CONSTRAINT `fk_batch` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`batch_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`teacher_id`) ON DELETE CASCADE;

--
-- Constraints for table `course_enrollments`
--
ALTER TABLE `course_enrollments`
  ADD CONSTRAINT `course_enrollments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_batch_id` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`batch_id`);

--
-- Constraints for table `course_favorites`
--
ALTER TABLE `course_favorites`
  ADD CONSTRAINT `course_favorites_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `course_favorites_ibfk_2` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`batch_id`) ON DELETE CASCADE;

--
-- Constraints for table `course_inquiries`
--
ALTER TABLE `course_inquiries`
  ADD CONSTRAINT `course_inquiries_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`);

--
-- Constraints for table `help_tickets`
--
ALTER TABLE `help_tickets`
  ADD CONSTRAINT `help_tickets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `inactivation_requests`
--
ALTER TABLE `inactivation_requests`
  ADD CONSTRAINT `fk_processed_by` FOREIGN KEY (`processed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `inactivation_requests_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`teacher_id`),
  ADD CONSTRAINT `inactivation_requests_ibfk_2` FOREIGN KEY (`enrollment_id`) REFERENCES `course_enrollments` (`enrollment_id`);

--
-- Constraints for table `internal_grades`
--
ALTER TABLE `internal_grades`
  ADD CONSTRAINT `internal_grades_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `internal_grades_ibfk_2` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`batch_id`) ON DELETE CASCADE;

--
-- Constraints for table `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `fk_invoice_enrollment` FOREIGN KEY (`enrollment_id`) REFERENCES `course_enrollments` (`enrollment_id`) ON DELETE CASCADE;

--
-- Constraints for table `marketing_analytics_logs`
--
ALTER TABLE `marketing_analytics_logs`
  ADD CONSTRAINT `marketing_analytics_logs_ibfk_1` FOREIGN KEY (`campaign_id`) REFERENCES `marketing_campaigns` (`campaign_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `marketing_analytics_logs_ibfk_2` FOREIGN KEY (`lead_id`) REFERENCES `marketing_leads` (`lead_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `marketing_analytics_logs_ibfk_3` FOREIGN KEY (`content_id`) REFERENCES `marketing_content` (`content_id`) ON DELETE SET NULL;

--
-- Constraints for table `marketing_campaigns`
--
ALTER TABLE `marketing_campaigns`
  ADD CONSTRAINT `marketing_campaigns_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `marketing_content`
--
ALTER TABLE `marketing_content`
  ADD CONSTRAINT `marketing_content_ibfk_1` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `marketing_content_ibfk_2` FOREIGN KEY (`linked_batch_id`) REFERENCES `batches` (`batch_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `marketing_content_ibfk_3` FOREIGN KEY (`linked_event_id`) REFERENCES `events` (`event_id`) ON DELETE SET NULL;

--
-- Constraints for table `marketing_feedback_responses`
--
ALTER TABLE `marketing_feedback_responses`
  ADD CONSTRAINT `marketing_feedback_responses_ibfk_1` FOREIGN KEY (`survey_id`) REFERENCES `marketing_feedback_surveys` (`survey_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `marketing_feedback_responses_ibfk_2` FOREIGN KEY (`respondent_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `marketing_feedback_surveys`
--
ALTER TABLE `marketing_feedback_surveys`
  ADD CONSTRAINT `marketing_feedback_surveys_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `marketing_leads`
--
ALTER TABLE `marketing_leads`
  ADD CONSTRAINT `marketing_leads_ibfk_1` FOREIGN KEY (`campaign_id`) REFERENCES `marketing_campaigns` (`campaign_id`) ON DELETE SET NULL;

--
-- Constraints for table `marketing_social_posts`
--
ALTER TABLE `marketing_social_posts`
  ADD CONSTRAINT `marketing_social_posts_ibfk_1` FOREIGN KEY (`campaign_id`) REFERENCES `marketing_campaigns` (`campaign_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `marketing_social_posts_ibfk_2` FOREIGN KEY (`posted_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `materials`
--
ALTER TABLE `materials`
  ADD CONSTRAINT `materials_ibfk_1` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`batch_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `materials_ibfk_2` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`teacher_id`) ON DELETE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `parents`
--
ALTER TABLE `parents`
  ADD CONSTRAINT `parents_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `parents_groupchat_messages`
--
ALTER TABLE `parents_groupchat_messages`
  ADD CONSTRAINT `parents_groupchat_messages_ibfk_1` FOREIGN KEY (`sender_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `parent_messages`
--
ALTER TABLE `parent_messages`
  ADD CONSTRAINT `parent_messages_ibfk_1` FOREIGN KEY (`sender_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `parent_messages_ibfk_2` FOREIGN KEY (`recipient_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `parent_messages_ibfk_3` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE SET NULL;

--
-- Constraints for table `parent_students`
--
ALTER TABLE `parent_students`
  ADD CONSTRAINT `parent_students_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `parents` (`parent_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `parent_students_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payment_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`invoice_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_payment_payer` FOREIGN KEY (`payer_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `school_equipment`
--
ALTER TABLE `school_equipment`
  ADD CONSTRAINT `school_equipment_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `school_rooms` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `school_furniture`
--
ALTER TABLE `school_furniture`
  ADD CONSTRAINT `school_furniture_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `school_rooms` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `school_rooms`
--
ALTER TABLE `school_rooms`
  ADD CONSTRAINT `school_rooms_ibfk_1` FOREIGN KEY (`floor_id`) REFERENCES `school_floors` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `student_medical_info`
--
ALTER TABLE `student_medical_info`
  ADD CONSTRAINT `student_medical_info_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `student_messages`
--
ALTER TABLE `student_messages`
  ADD CONSTRAINT `student_messages_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `student_profile_updates`
--
ALTER TABLE `student_profile_updates`
  ADD CONSTRAINT `student_profile_updates_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `student_transport_info`
--
ALTER TABLE `student_transport_info`
  ADD CONSTRAINT `student_transport_info_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `support_inquiries`
--
ALTER TABLE `support_inquiries`
  ADD CONSTRAINT `support_inquiries_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `teachers`
--
ALTER TABLE `teachers`
  ADD CONSTRAINT `teachers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `teacher_batches`
--
ALTER TABLE `teacher_batches`
  ADD CONSTRAINT `teacher_batches_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`teacher_id`),
  ADD CONSTRAINT `teacher_batches_ibfk_2` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`batch_id`);

--
-- Constraints for table `teacher_timetables`
--
ALTER TABLE `teacher_timetables`
  ADD CONSTRAINT `teacher_timetables_ibfk_1` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`batch_id`) ON DELETE CASCADE;

--
-- Constraints for table `temporary_ids`
--
ALTER TABLE `temporary_ids`
  ADD CONSTRAINT `temporary_ids_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `tests`
--
ALTER TABLE `tests`
  ADD CONSTRAINT `tests_ibfk_1` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`batch_id`),
  ADD CONSTRAINT `tests_ibfk_2` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`teacher_id`);

--
-- Constraints for table `test_submissions`
--
ALTER TABLE `test_submissions`
  ADD CONSTRAINT `fk_test_submissions_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_test_submissions_test` FOREIGN KEY (`test_id`) REFERENCES `tests` (`test_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`address_id`) REFERENCES `addresses` (`address_id`);

--
-- Constraints for table `user_verifications`
--
ALTER TABLE `user_verifications`
  ADD CONSTRAINT `user_verifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
