-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 05, 2026 at 03:09 AM
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
-- Database: `admin_cnpapp`
--

-- --------------------------------------------------------

--
-- Table structure for table `academic_days`
--

CREATE TABLE `academic_days` (
  `id` int(11) NOT NULL,
  `academic_year` int(11) DEFAULT NULL,
  `semester` int(11) DEFAULT NULL,
  `date_val` date DEFAULT NULL,
  `activity` varchar(255) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `category` varchar(100) DEFAULT 'ทั่วไป',
  `day_type` varchar(50) DEFAULT 'ปกติ',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `academic_days`
--


-- --------------------------------------------------------

--
-- Table structure for table `admin_jobs`
--

CREATE TABLE `admin_jobs` (
  `id` int(11) NOT NULL,
  `job_group` varchar(100) NOT NULL,
  `job_name` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_jobs`
--


-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `type` enum('daily','subject') DEFAULT 'subject',
  `subject_code` varchar(20) DEFAULT NULL,
  `student_id` int(11) DEFAULT NULL,
  `date` date NOT NULL,
  `status` varchar(20) NOT NULL,
  `remark` varchar(255) DEFAULT NULL,
  `recorded_by` int(11) DEFAULT NULL,
  `period` int(11) DEFAULT 1,
  `class_name` varchar(50) DEFAULT NULL,
  `room_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `attendance`
--


--
-- Triggers `attendance`
--
DELIMITER $$
CREATE TRIGGER `attendance_sync_fk_insert` BEFORE INSERT ON `attendance` FOR EACH ROW BEGIN
    IF NEW.class_name IS NOT NULL AND NEW.class_name <> '' AND NEW.room_id IS NULL THEN
        SET NEW.room_id = (SELECT id FROM rooms WHERE classroom_code = NEW.class_name LIMIT 1);
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `attendance_sync_fk_update` BEFORE UPDATE ON `attendance` FOR EACH ROW BEGIN
    IF (NEW.class_name <> OLD.class_name OR (NEW.class_name IS NOT NULL AND OLD.class_name IS NULL))
       AND NEW.class_name IS NOT NULL AND NEW.class_name <> '' THEN
        SET NEW.room_id = (SELECT id FROM rooms WHERE classroom_code = NEW.class_name LIMIT 1);
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `attendance_subjects`
--

CREATE TABLE `attendance_subjects` (
  `id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `subject_code` varchar(20) DEFAULT NULL,
  `class_name` varchar(50) DEFAULT NULL,
  `date` date NOT NULL,
  `period` int(11) DEFAULT 1,
  `status` varchar(20) NOT NULL,
  `remark` varchar(255) DEFAULT NULL,
  `recorded_by` int(11) DEFAULT NULL,
  `academic_year` varchar(10) DEFAULT NULL,
  `semester` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `attendance_subjects`
--


-- --------------------------------------------------------

--
-- Table structure for table `auth_tokens`
--

CREATE TABLE `auth_tokens` (
  `id` int(11) NOT NULL,
  `selector` varchar(32) NOT NULL,
  `token_hash` varchar(128) NOT NULL,
  `user_id` int(11) NOT NULL,
  `expires_at` datetime NOT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT current_timestamp(),
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `auth_tokens`
--


-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `name_th` varchar(200) NOT NULL DEFAULT '',
  `name_en` varchar(200) DEFAULT '',
  `abbr_th` varchar(50) DEFAULT '',
  `abbr_en` varchar(50) DEFAULT '',
  `head_name` varchar(200) DEFAULT '',
  `location` varchar(50) DEFAULT '',
  `color` varchar(7) DEFAULT '#1e3c72',
  `icon` varchar(50) DEFAULT 'fas fa-book',
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `departments`
--


-- --------------------------------------------------------

--
-- Table structure for table `department_jobs`
--

CREATE TABLE `department_jobs` (
  `id` int(11) NOT NULL,
  `job_name` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `department_jobs`
--


-- --------------------------------------------------------

--
-- Table structure for table `districts`
--

CREATE TABLE `districts` (
  `id` int(5) NOT NULL,
  `name` varchar(150) NOT NULL,
  `province_id` int(5) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `districts`
--


-- --------------------------------------------------------

--
-- Table structure for table `executives`
--

CREATE TABLE `executives` (
  `id` int(11) NOT NULL,
  `position_slug` varchar(50) DEFAULT NULL,
  `position_label` varchar(200) DEFAULT '',
  `teacher_id` int(11) DEFAULT NULL,
  `name_override` varchar(200) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `name` varchar(100) NOT NULL,
  `position` varchar(100) NOT NULL,
  `photo_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `executives`
--


-- --------------------------------------------------------

--
-- Table structure for table `grade_levels`
--

CREATE TABLE `grade_levels` (
  `id` int(11) NOT NULL,
  `grade_name` varchar(100) NOT NULL,
  `head_teacher_id` int(11) DEFAULT NULL,
  `room_count` int(11) DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `grade_levels`
--


--
-- Triggers `grade_levels`
--
DELIMITER $$
CREATE TRIGGER `grade_levels_propagate_name` AFTER UPDATE ON `grade_levels` FOR EACH ROW BEGIN
    IF NEW.grade_name <> OLD.grade_name THEN
        UPDATE students SET grade_level = NEW.grade_name WHERE grade_level = OLD.grade_name;
        UPDATE rooms    SET grade_level = NEW.grade_name WHERE grade_level = OLD.grade_name;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `homeroom_sessions`
--

CREATE TABLE `homeroom_sessions` (
  `id` int(11) NOT NULL,
  `date` date NOT NULL,
  `room_id` int(11) NOT NULL,
  `session_type` enum('short','long') NOT NULL DEFAULT 'short',
  `activity` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `recorded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `homeroom_sessions`
--


-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` varchar(50) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `message` text DEFAULT NULL,
  `link` varchar(255) DEFAULT NULL,
  `icon` varchar(50) DEFAULT 'bi bi-bell',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--


-- --------------------------------------------------------

--
-- Table structure for table `point_categories`
--

CREATE TABLE `point_categories` (
  `id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `is_positive` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `point_categories`
--


-- --------------------------------------------------------

--
-- Table structure for table `point_items`
--

CREATE TABLE `point_items` (
  `id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `item_name` varchar(255) NOT NULL,
  `points` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `point_items`
--


-- --------------------------------------------------------

--
-- Table structure for table `point_transactions`
--

CREATE TABLE `point_transactions` (
  `id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `item_id` int(11) DEFAULT NULL,
  `points` int(11) DEFAULT NULL,
  `remark` text DEFAULT NULL,
  `recorded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `occurrence_date` date DEFAULT NULL,
  `semester` int(11) DEFAULT 1,
  `academic_year` int(11) DEFAULT 2569
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `point_transactions`
--


-- --------------------------------------------------------

--
-- Table structure for table `provinces`
--

CREATE TABLE `provinces` (
  `id` int(5) NOT NULL,
  `name` varchar(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `provinces`
--


-- --------------------------------------------------------

--
-- Table structure for table `public_relations`
--

CREATE TABLE `public_relations` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `category` varchar(100) DEFAULT 'ทั่วไป',
  `visibility` varchar(50) NOT NULL DEFAULT 'all',
  `image_path` varchar(255) DEFAULT NULL,
  `author_id` int(11) NOT NULL,
  `author_role` varchar(50) NOT NULL,
  `author_name` varchar(255) NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `approved_at` timestamp NULL DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `public_relations`
--


-- --------------------------------------------------------

--
-- Table structure for table `public_service_records`
--

CREATE TABLE `public_service_records` (
  `id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `activity_name` varchar(255) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `impact_benefit` text DEFAULT NULL,
  `activity_date` date NOT NULL,
  `duration` decimal(5,2) DEFAULT 0.00,
  `duration_unit` varchar(20) DEFAULT 'ครั้ง',
  `certifier_name` varchar(255) DEFAULT NULL,
  `approver_id` int(11) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `academic_year` varchar(4) DEFAULT NULL,
  `semester` varchar(1) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `public_service_records`
--


-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `label` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--


-- --------------------------------------------------------

--
-- Table structure for table `rooms`
--

CREATE TABLE `rooms` (
  `id` int(11) NOT NULL,
  `classroom_code` varchar(20) NOT NULL,
  `class_level` varchar(10) DEFAULT NULL,
  `classroom_no` varchar(10) DEFAULT NULL,
  `grade_level` varchar(100) DEFAULT NULL,
  `location_code` varchar(20) NOT NULL,
  `building` varchar(50) DEFAULT NULL,
  `floor` varchar(20) DEFAULT NULL,
  `room_no` varchar(20) DEFAULT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  `house` varchar(100) DEFAULT NULL,
  `program` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `rooms`
--


--
-- Triggers `rooms`
--
DELIMITER $$
CREATE TRIGGER `rooms_propagate_code` AFTER UPDATE ON `rooms` FOR EACH ROW BEGIN
    IF NEW.classroom_code <> OLD.classroom_code THEN
        UPDATE students   SET room       = NEW.classroom_code WHERE room       = OLD.classroom_code;
        UPDATE attendance SET class_name = NEW.classroom_code WHERE class_name = OLD.classroom_code;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `student_id` varchar(20) DEFAULT NULL,
  `student_id_card` varchar(20) DEFAULT NULL,
  `number_in_class` int(11) DEFAULT NULL,
  `class_name` varchar(20) DEFAULT NULL,
  `house` varchar(100) DEFAULT NULL,
  `room_id` int(11) DEFAULT NULL,
  `grade_level` varchar(20) DEFAULT NULL,
  `grade_level_id` int(11) DEFAULT NULL,
  `faculty` varchar(50) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `prefix` varchar(50) DEFAULT NULL,
  `first_name_th` varchar(100) DEFAULT NULL,
  `last_name_th` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `enrollment_status` enum('กำลังศึกษา','พักการเรียน','ลาออก','จำหน่าย') NOT NULL DEFAULT 'กำลังศึกษา',
  `full_name_th` varchar(255) DEFAULT NULL,
  `first_name_en` varchar(100) DEFAULT NULL,
  `last_name_en` varchar(100) DEFAULT NULL,
  `nickname` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `gender` varchar(20) DEFAULT NULL,
  `birth_sex` varchar(20) DEFAULT NULL,
  `id_card` varchar(20) DEFAULT NULL,
  `ethnicity` varchar(50) DEFAULT NULL,
  `nationality` varchar(50) DEFAULT NULL,
  `religion` varchar(50) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `child_order` int(11) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `line_id` varchar(50) DEFAULT NULL,
  `facebook` varchar(100) DEFAULT NULL,
  `address_status` varchar(50) DEFAULT NULL,
  `reg_house_no` varchar(50) DEFAULT NULL,
  `reg_soi` varchar(100) DEFAULT NULL,
  `reg_road` varchar(100) DEFAULT NULL,
  `reg_moo` varchar(20) DEFAULT NULL,
  `reg_village` varchar(100) DEFAULT NULL,
  `reg_subdistrict` varchar(100) DEFAULT NULL,
  `reg_district` varchar(100) DEFAULT NULL,
  `reg_province` varchar(100) DEFAULT NULL,
  `reg_zipcode` varchar(10) DEFAULT NULL,
  `curr_house_no` varchar(50) DEFAULT NULL,
  `curr_soi` varchar(100) DEFAULT NULL,
  `curr_road` varchar(100) DEFAULT NULL,
  `curr_moo` varchar(20) DEFAULT NULL,
  `curr_village` varchar(100) DEFAULT NULL,
  `curr_subdistrict` varchar(100) DEFAULT NULL,
  `curr_district` varchar(100) DEFAULT NULL,
  `curr_province` varchar(100) DEFAULT NULL,
  `curr_zipcode` varchar(10) DEFAULT NULL,
  `location_coords` varchar(100) DEFAULT NULL,
  `location_landmark` text DEFAULT NULL,
  `village_headman` varchar(100) DEFAULT NULL,
  `subdistrict_headman` varchar(100) DEFAULT NULL,
  `house_type` varchar(50) DEFAULT NULL,
  `house_style` varchar(50) DEFAULT NULL,
  `house_condition` varchar(50) DEFAULT NULL,
  `house_cleanliness` varchar(50) DEFAULT NULL,
  `has_electricity` varchar(20) DEFAULT NULL,
  `has_water` varchar(20) DEFAULT NULL,
  `has_toilet` varchar(20) DEFAULT NULL,
  `dist_to_school` varchar(50) DEFAULT NULL,
  `travel_time` varchar(50) DEFAULT NULL,
  `travel_method` varchar(100) DEFAULT NULL,
  `f_prefix` varchar(50) DEFAULT NULL,
  `f_first_name` varchar(100) DEFAULT NULL,
  `f_last_name` varchar(100) DEFAULT NULL,
  `f_age` int(11) DEFAULT NULL,
  `f_phone` varchar(20) DEFAULT NULL,
  `f_education` varchar(100) DEFAULT NULL,
  `f_job` varchar(100) DEFAULT NULL,
  `f_workplace` varchar(200) DEFAULT NULL,
  `f_family_status` varchar(100) DEFAULT NULL,
  `f_welfare` varchar(50) DEFAULT NULL,
  `f_income` decimal(12,2) DEFAULT NULL,
  `m_prefix` varchar(50) DEFAULT NULL,
  `m_first_name` varchar(100) DEFAULT NULL,
  `m_last_name` varchar(100) DEFAULT NULL,
  `m_age` int(11) DEFAULT NULL,
  `m_phone` varchar(20) DEFAULT NULL,
  `m_education` varchar(100) DEFAULT NULL,
  `m_job` varchar(100) DEFAULT NULL,
  `m_workplace` varchar(200) DEFAULT NULL,
  `m_family_status` varchar(100) DEFAULT NULL,
  `m_welfare` varchar(50) DEFAULT NULL,
  `m_income` decimal(12,2) DEFAULT NULL,
  `family_status` varchar(100) DEFAULT NULL,
  `guardian_relation` varchar(100) DEFAULT NULL,
  `g_prefix` varchar(50) DEFAULT NULL,
  `g_first_name` varchar(100) DEFAULT NULL,
  `g_last_name` varchar(100) DEFAULT NULL,
  `g_age` int(11) DEFAULT NULL,
  `g_phone` varchar(20) DEFAULT NULL,
  `g_education` varchar(100) DEFAULT NULL,
  `g_job` varchar(100) DEFAULT NULL,
  `g_workplace` varchar(200) DEFAULT NULL,
  `g_income` decimal(12,2) DEFAULT NULL,
  `total_family_members` int(11) DEFAULT NULL,
  `male_members` int(11) DEFAULT NULL,
  `female_members` int(11) DEFAULT NULL,
  `full_siblings` int(11) DEFAULT NULL,
  `full_siblings_male` int(11) DEFAULT NULL,
  `full_siblings_female` int(11) DEFAULT NULL,
  `half_siblings` int(11) DEFAULT NULL,
  `half_siblings_male` int(11) DEFAULT NULL,
  `half_siblings_female` int(11) DEFAULT NULL,
  `family_relationship` varchar(100) DEFAULT NULL,
  `rel_father` varchar(100) DEFAULT NULL,
  `rel_mother` varchar(100) DEFAULT NULL,
  `rel_brothers` varchar(100) DEFAULT NULL,
  `rel_sisters` varchar(100) DEFAULT NULL,
  `rel_grandparents` varchar(100) DEFAULT NULL,
  `rel_relatives` varchar(100) DEFAULT NULL,
  `time_spent_together` varchar(100) DEFAULT NULL,
  `allowance_source` varchar(100) DEFAULT NULL,
  `allowance_per_day` decimal(10,2) DEFAULT NULL,
  `responsibilities` text DEFAULT NULL,
  `caregiver_when_away` varchar(100) DEFAULT NULL,
  `part_time_job` varchar(200) DEFAULT NULL,
  `part_time_income` decimal(10,2) DEFAULT NULL,
  `weight` float DEFAULT NULL,
  `height` float DEFAULT NULL,
  `blood_group` varchar(10) DEFAULT NULL,
  `food_allergies` text DEFAULT NULL,
  `drug_allergies` text DEFAULT NULL,
  `congenital_disease` text DEFAULT NULL,
  `covid_vaccine` varchar(100) DEFAULT NULL,
  `internet_access` varchar(100) DEFAULT NULL,
  `social_media_usage` varchar(200) DEFAULT NULL,
  `talents` text DEFAULT NULL,
  `interests` text DEFAULT NULL,
  `hobbies` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `instagram` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `students`
--


--
-- Triggers `students`
--
DELIMITER $$
CREATE TRIGGER `students_sync_fk_insert` BEFORE INSERT ON `students` FOR EACH ROW BEGIN
    IF NEW.class_name IS NOT NULL AND NEW.class_name <> '' AND NEW.room_id IS NULL THEN
        SET NEW.room_id = (SELECT id FROM rooms WHERE classroom_code = NEW.class_name LIMIT 1);
    END IF;
    IF NEW.grade_level IS NOT NULL AND NEW.grade_level <> '' AND NEW.grade_level_id IS NULL THEN
        SET NEW.grade_level_id = (SELECT id FROM grade_levels WHERE grade_name = NEW.grade_level LIMIT 1);
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `students_sync_fk_update` BEFORE UPDATE ON `students` FOR EACH ROW BEGIN
    IF (NEW.class_name <> OLD.class_name OR (NEW.class_name IS NOT NULL AND OLD.class_name IS NULL))
       AND NEW.class_name IS NOT NULL AND NEW.class_name <> '' THEN
        SET NEW.room_id = (SELECT id FROM rooms WHERE classroom_code = NEW.class_name LIMIT 1);
    END IF;
    IF (NEW.grade_level <> OLD.grade_level OR (NEW.grade_level IS NOT NULL AND OLD.grade_level IS NULL))
       AND NEW.grade_level IS NOT NULL AND NEW.grade_level <> '' THEN
        SET NEW.grade_level_id = (SELECT id FROM grade_levels WHERE grade_name = NEW.grade_level LIMIT 1);
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `student_evaluations`
--

CREATE TABLE `student_evaluations` (
  `id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `eval_type` varchar(50) DEFAULT NULL COMMENT 'academic, behavioral, health',
  `score` decimal(5,2) DEFAULT NULL,
  `risk_level` varchar(20) DEFAULT NULL COMMENT 'low, medium, high',
  `teacher_comment` text DEFAULT NULL,
  `recorded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_risk_flags`
--

CREATE TABLE `student_risk_flags` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'ยังต้องติดตาม',
  `note` text DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `month` varchar(7) DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `subdistricts`
--

CREATE TABLE `subdistricts` (
  `id` varchar(6) NOT NULL,
  `postcode` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `district_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subdistricts`
--


-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `id` int(11) NOT NULL,
  `subject_code` varchar(20) NOT NULL,
  `subject_name` varchar(255) NOT NULL,
  `semester` tinyint(4) DEFAULT 1,
  `year_be` int(11) DEFAULT 2568,
  `rooms` text DEFAULT NULL,
  `order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `department` varchar(100) DEFAULT NULL,
  `academic_year` varchar(10) DEFAULT NULL,
  `room` text DEFAULT NULL,
  `periods` text DEFAULT NULL,
  `teacher_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `subjects`
--


-- --------------------------------------------------------

--
-- Table structure for table `sub_departments`
--

CREATE TABLE `sub_departments` (
  `id` int(11) NOT NULL,
  `department_id` int(11) NOT NULL,
  `name_th` varchar(200) NOT NULL DEFAULT '',
  `name_en` varchar(200) DEFAULT '',
  `abbr_th` varchar(50) DEFAULT '',
  `abbr_en` varchar(50) DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sub_departments`
--


-- --------------------------------------------------------

--
-- Table structure for table `supervision_bookings`
--

CREATE TABLE `supervision_bookings` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `semester` int(11) NOT NULL DEFAULT 1,
  `year` int(11) NOT NULL DEFAULT 2569,
  `subject_code` varchar(50) NOT NULL,
  `subject_name` varchar(255) NOT NULL,
  `classroom` varchar(100) NOT NULL,
  `room_number` varchar(100) NOT NULL,
  `lesson_topic` varchar(255) DEFAULT NULL,
  `booking_date` date NOT NULL,
  `booking_period` int(11) NOT NULL,
  `peer_teacher_id` int(11) NOT NULL,
  `head_teacher_id` int(11) NOT NULL,
  `academic_teacher_id` int(11) DEFAULT NULL,
  `teacher_position` varchar(100) NOT NULL DEFAULT 'ครู',
  `academic_standing` varchar(100) NOT NULL DEFAULT 'ไม่มีวิทยฐานะ',
  `status` varchar(50) NOT NULL DEFAULT 'pending',
  `evaluation_purpose` varchar(255) NOT NULL DEFAULT 'ไม่มีวิทยฐานะ',
  `lesson_plan_doc` varchar(255) DEFAULT NULL,
  `post_teaching_record` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `supervision_bookings`
--


-- --------------------------------------------------------

--
-- Table structure for table `supervision_docs`
--

CREATE TABLE `supervision_docs` (
  `booking_id` int(11) NOT NULL,
  `doc_subject_structure` varchar(255) DEFAULT NULL,
  `doc_unit_structure` varchar(255) DEFAULT NULL,
  `doc_unit_plan` varchar(255) DEFAULT NULL,
  `doc_lesson_plan` varchar(255) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `supervision_docs`
--


-- --------------------------------------------------------

--
-- Table structure for table `supervision_doc_reads`
--

CREATE TABLE `supervision_doc_reads` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `evaluator_id` int(11) NOT NULL,
  `role` varchar(50) NOT NULL,
  `read_subject_structure` datetime DEFAULT NULL,
  `read_unit_structure` datetime DEFAULT NULL,
  `read_unit_plan` datetime DEFAULT NULL,
  `read_lesson_plan` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `supervision_evaluations`
--

CREATE TABLE `supervision_evaluations` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `evaluator_teacher_id` int(11) NOT NULL,
  `evaluator_role` varchar(50) NOT NULL,
  `doc_score_1` int(11) DEFAULT 0,
  `doc_score_2` int(11) DEFAULT 0,
  `doc_score_3` int(11) DEFAULT 0,
  `doc_score_4` int(11) DEFAULT 0,
  `doc_score_5` int(11) DEFAULT 0,
  `doc_comments` text DEFAULT NULL,
  `doc_evaluated_at` timestamp NULL DEFAULT NULL,
  `class_score_1` int(11) DEFAULT 0,
  `class_score_2` int(11) DEFAULT 0,
  `class_score_3` int(11) DEFAULT 0,
  `class_score_4` int(11) DEFAULT 0,
  `class_score_5` int(11) DEFAULT 0,
  `class_score_6` int(11) DEFAULT 0,
  `class_score_7` int(11) DEFAULT 0,
  `class_score_8` int(11) DEFAULT 0,
  `class_score_9` int(11) DEFAULT 0,
  `class_score_10` int(11) DEFAULT 0,
  `class_comments` text DEFAULT NULL,
  `class_evaluated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `unit_score_1` int(11) DEFAULT 0,
  `unit_score_2` int(11) DEFAULT 0,
  `unit_score_3` int(11) DEFAULT 0,
  `unit_score_4` int(11) DEFAULT 0,
  `unit_score_5` int(11) DEFAULT 0,
  `unit_score_6` int(11) DEFAULT 0,
  `unit_score_7` int(11) DEFAULT 0,
  `unit_score_8` int(11) DEFAULT 0,
  `unit_score_9` int(11) DEFAULT 0,
  `unit_score_10` int(11) DEFAULT 0,
  `unit_score_11` int(11) DEFAULT 0,
  `unit_score_12` int(11) DEFAULT 0,
  `unit_score_13` int(11) DEFAULT 0,
  `unit_score_14` int(11) DEFAULT 0,
  `unit_score_15` int(11) DEFAULT 0,
  `unit_score_16` int(11) DEFAULT 0,
  `unit_score_17` int(11) DEFAULT 0,
  `unit_score_18` int(11) DEFAULT 0,
  `unit_score_19` int(11) DEFAULT 0,
  `plan_score_1` int(11) DEFAULT 0,
  `plan_score_2` int(11) DEFAULT 0,
  `plan_score_3` int(11) DEFAULT 0,
  `plan_score_4` int(11) DEFAULT 0,
  `plan_score_5` int(11) DEFAULT 0,
  `plan_score_6` int(11) DEFAULT 0,
  `plan_score_7` int(11) DEFAULT 0,
  `plan_score_8` int(11) DEFAULT 0,
  `plan_score_9` int(11) DEFAULT 0,
  `plan_score_10` int(11) DEFAULT 0,
  `plan_score_11` int(11) DEFAULT 0,
  `plan_score_12` int(11) DEFAULT 0,
  `plan_score_13` int(11) DEFAULT 0,
  `plan_score_14` int(11) DEFAULT 0,
  `plan_score_15` int(11) DEFAULT 0,
  `plan_score_16` int(11) DEFAULT 0,
  `plan_score_17` int(11) DEFAULT 0,
  `plan_score_18` int(11) DEFAULT 0,
  `plan_score_19` int(11) DEFAULT 0,
  `plan_score_20` int(11) DEFAULT 0,
  `plan_score_21` int(11) DEFAULT 0,
  `plan_score_22_1` int(11) DEFAULT 0,
  `plan_score_22_2` int(11) DEFAULT 0,
  `plan_score_22_3` int(11) DEFAULT 0,
  `plan_score_22_4` int(11) DEFAULT 0,
  `unit_integration` text DEFAULT NULL,
  `plan_integration` text DEFAULT NULL,
  `class_score_11` int(11) DEFAULT 0,
  `class_score_12` int(11) DEFAULT 0,
  `class_score_13` int(11) DEFAULT 0,
  `class_score_14` int(11) DEFAULT 0,
  `class_score_15` int(11) DEFAULT 0,
  `class_score_16` int(11) DEFAULT 0,
  `class_score_17` int(11) DEFAULT 0,
  `class_score_18` int(11) DEFAULT 0,
  `class_score_19` int(11) DEFAULT 0,
  `class_score_20` int(11) DEFAULT 0,
  `class_score_21` int(11) DEFAULT 0,
  `class_score_22` int(11) DEFAULT 0,
  `class_score_23` int(11) DEFAULT 0,
  `class_score_24` int(11) DEFAULT 0,
  `class_score_25` int(11) DEFAULT 0,
  `class_score_26` int(11) DEFAULT 0,
  `class_score_27` int(11) DEFAULT 0,
  `class_score_28` int(11) DEFAULT 0,
  `class_score_29` int(11) DEFAULT 0,
  `class_score_30` int(11) DEFAULT 0,
  `class_score_31` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `supervision_evaluations`
--


-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `system_settings`
--


-- --------------------------------------------------------

--
-- Table structure for table `teachers`
--

CREATE TABLE `teachers` (
  `id` int(11) NOT NULL,
  `teacher_id` varchar(50) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `id_card` varchar(20) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `appointment_date` date DEFAULT NULL,
  `prefix` varchar(50) DEFAULT NULL,
  `first_name_th` varchar(100) DEFAULT NULL,
  `last_name_th` varchar(100) DEFAULT NULL,
  `first_name_en` varchar(100) DEFAULT NULL,
  `last_name_en` varchar(100) DEFAULT NULL,
  `nickname` varchar(50) DEFAULT NULL,
  `classroom` varchar(100) DEFAULT NULL,
  `advisory_room_id` int(11) DEFAULT NULL,
  `faculty` varchar(100) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `academic_standing` varchar(100) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `department_position` varchar(500) DEFAULT NULL,
  `admin_position` varchar(100) DEFAULT NULL,
  `sub_department` varchar(100) DEFAULT NULL,
  `retirement_year` int(11) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `line_id` varchar(100) DEFAULT NULL,
  `hometown` varchar(100) DEFAULT NULL,
  `ethnicity` varchar(50) DEFAULT NULL,
  `nationality` varchar(50) DEFAULT NULL,
  `religion` varchar(50) DEFAULT NULL,
  `signature` mediumtext DEFAULT NULL,
  `weight` float DEFAULT NULL,
  `height` float DEFAULT NULL,
  `blood_group` varchar(10) DEFAULT NULL,
  `food_allergies` text DEFAULT NULL,
  `drug_allergies` text DEFAULT NULL,
  `congenital_disease` text DEFAULT NULL,
  `covid_vaccine` varchar(100) DEFAULT NULL,
  `health_note` text DEFAULT NULL,
  `home_address_no` varchar(50) DEFAULT NULL,
  `home_address_moo` varchar(20) DEFAULT NULL,
  `home_address_soi` varchar(50) DEFAULT NULL,
  `home_address_road` varchar(50) DEFAULT NULL,
  `home_address_subdistrict` varchar(100) DEFAULT NULL,
  `home_address_district` varchar(100) DEFAULT NULL,
  `home_address_province` varchar(100) DEFAULT NULL,
  `home_address_zipcode` varchar(10) DEFAULT NULL,
  `address_no` varchar(50) DEFAULT NULL,
  `address_moo` varchar(20) DEFAULT NULL,
  `address_soi` varchar(100) DEFAULT NULL,
  `address_road` varchar(100) DEFAULT NULL,
  `address_subdistrict` varchar(100) DEFAULT NULL,
  `address_district` varchar(100) DEFAULT NULL,
  `address_province` varchar(100) DEFAULT NULL,
  `address_zipcode` varchar(10) DEFAULT NULL,
  `use_home_address` tinyint(1) DEFAULT 0,
  `facebook` varchar(100) DEFAULT NULL,
  `instagram` varchar(100) DEFAULT NULL,
  `tiktok` varchar(100) DEFAULT NULL,
  `twitter` varchar(100) DEFAULT NULL,
  `edu_highschool` varchar(255) DEFAULT NULL,
  `edu_highschool_year` varchar(4) DEFAULT NULL,
  `edu_university` varchar(255) DEFAULT NULL,
  `edu_university_degree` varchar(255) DEFAULT NULL,
  `edu_university_year` varchar(4) DEFAULT NULL,
  `education_history` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `teachers`
--


-- --------------------------------------------------------

--
-- Table structure for table `timetable`
--

CREATE TABLE `timetable` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL COMMENT 'FK ??? teachers.id',
  `day_of_week` tinyint(1) NOT NULL COMMENT '1=?????????????????? 2=?????????????????? 3=????????? 4=??????????????? 5=??????????????? 6=??????????????? 7=?????????????????????',
  `period` tinyint(2) NOT NULL COMMENT '?????????????????? 0???11',
  `subject_name` varchar(200) NOT NULL COMMENT '????????????????????????',
  `subject_code` varchar(50) DEFAULT NULL COMMENT '????????????????????????',
  `grade_level` varchar(20) DEFAULT NULL COMMENT '???????????? ???????????? ???.1 ???.4',
  `class_name` varchar(30) DEFAULT NULL COMMENT '??????????????????????????? ???????????? 1/1 4/2',
  `room_location` varchar(100) DEFAULT NULL COMMENT '?????????????????????????????? (???????????????/????????????)',
  `academic_year` varchar(10) DEFAULT NULL COMMENT '?????????????????????????????? ???????????? 2568',
  `semester` tinyint(1) DEFAULT 1,
  `note` varchar(200) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `timetable`
--


-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','teacher','student') NOT NULL,
  `role_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--


--
-- Indexes for dumped tables
--

--
-- Indexes for table `academic_days`
--
ALTER TABLE `academic_days`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `date_val` (`date_val`);

--
-- Indexes for table `admin_jobs`
--
ALTER TABLE `admin_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `group_job` (`job_group`,`job_name`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `idx_date` (`date`),
  ADD KEY `idx_class_name` (`class_name`),
  ADD KEY `idx_type_date` (`type`,`date`),
  ADD KEY `idx_attendance_room_id` (`room_id`);

--
-- Indexes for table `attendance_subjects`
--
ALTER TABLE `attendance_subjects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_as_student` (`student_id`),
  ADD KEY `idx_as_date` (`date`),
  ADD KEY `idx_as_class` (`class_name`),
  ADD KEY `idx_as_subject` (`subject_code`);

--
-- Indexes for table `auth_tokens`
--
ALTER TABLE `auth_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_selector` (`selector`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `department_jobs`
--
ALTER TABLE `department_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `job_name` (`job_name`);

--
-- Indexes for table `districts`
--
ALTER TABLE `districts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `executives`
--
ALTER TABLE `executives`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_position_slug` (`position_slug`),
  ADD KEY `fk_exec_teacher` (`teacher_id`);

--
-- Indexes for table `grade_levels`
--
ALTER TABLE `grade_levels`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `grade_name` (`grade_name`);

--
-- Indexes for table `homeroom_sessions`
--
ALTER TABLE `homeroom_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_homeroom_date_room` (`date`,`room_id`),
  ADD KEY `idx_homeroom_date` (`date`),
  ADD KEY `idx_homeroom_room` (`room_id`),
  ADD KEY `fk_homeroom_user` (`recorded_by`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `is_read` (`is_read`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `point_categories`
--
ALTER TABLE `point_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `point_items`
--
ALTER TABLE `point_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `point_transactions`
--
ALTER TABLE `point_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `provinces`
--
ALTER TABLE `provinces`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `public_relations`
--
ALTER TABLE `public_relations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `public_service_records`
--
ALTER TABLE `public_service_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `status` (`status`),
  ADD KEY `academic_year` (`academic_year`,`semester`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `rooms`
--
ALTER TABLE `rooms`
  ADD PRIMARY KEY (`id`),
  ADD KEY `teacher_id` (`teacher_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_id` (`student_id`),
  ADD KEY `idx_grade_room` (`grade_level`,`class_name`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_students_room_id` (`room_id`),
  ADD KEY `idx_students_grade_level_id` (`grade_level_id`);

--
-- Indexes for table `student_evaluations`
--
ALTER TABLE `student_evaluations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `student_risk_flags`
--
ALTER TABLE `student_risk_flags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_student_month` (`student_id`,`month`);

--
-- Indexes for table `subdistricts`
--
ALTER TABLE `subdistricts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_subject_unique` (`subject_code`,`academic_year`,`semester`,`teacher_id`),
  ADD KEY `idx_subjects_teacher` (`teacher_id`);

--
-- Indexes for table `sub_departments`
--
ALTER TABLE `sub_departments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `department_id` (`department_id`);

--
-- Indexes for table `supervision_bookings`
--
ALTER TABLE `supervision_bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `teacher_id` (`teacher_id`),
  ADD KEY `booking_date` (`booking_date`);

--
-- Indexes for table `supervision_docs`
--
ALTER TABLE `supervision_docs`
  ADD PRIMARY KEY (`booking_id`);

--
-- Indexes for table `supervision_doc_reads`
--
ALTER TABLE `supervision_doc_reads`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_evaluator` (`booking_id`,`evaluator_id`);

--
-- Indexes for table `supervision_evaluations`
--
ALTER TABLE `supervision_evaluations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `booking_evaluator` (`booking_id`,`evaluator_teacher_id`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `evaluator_teacher_id` (`evaluator_teacher_id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `teachers`
--
ALTER TABLE `teachers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `teacher_id` (`teacher_id`),
  ADD KEY `idx_teachers_advisory_room_id` (`advisory_room_id`);

--
-- Indexes for table `timetable`
--
ALTER TABLE `timetable`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_teacher` (`teacher_id`),
  ADD KEY `idx_day_period` (`day_of_week`,`period`),
  ADD KEY `idx_class` (`grade_level`,`class_name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `fk_users_role` (`role_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `academic_days`
--
ALTER TABLE `academic_days`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2155;

--
-- AUTO_INCREMENT for table `admin_jobs`
--
ALTER TABLE `admin_jobs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47526;

--
-- AUTO_INCREMENT for table `attendance_subjects`
--
ALTER TABLE `attendance_subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14048;

--
-- AUTO_INCREMENT for table `auth_tokens`
--
ALTER TABLE `auth_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9980;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `department_jobs`
--
ALTER TABLE `department_jobs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `executives`
--
ALTER TABLE `executives`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `grade_levels`
--
ALTER TABLE `grade_levels`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `homeroom_sessions`
--
ALTER TABLE `homeroom_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1079;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `point_categories`
--
ALTER TABLE `point_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `point_items`
--
ALTER TABLE `point_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=90;

--
-- AUTO_INCREMENT for table `point_transactions`
--
ALTER TABLE `point_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=80;

--
-- AUTO_INCREMENT for table `public_relations`
--
ALTER TABLE `public_relations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `public_service_records`
--
ALTER TABLE `public_service_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3173;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `rooms`
--
ALTER TABLE `rooms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=77;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6631;

--
-- AUTO_INCREMENT for table `student_evaluations`
--
ALTER TABLE `student_evaluations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_risk_flags`
--
ALTER TABLE `student_risk_flags`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2773;

--
-- AUTO_INCREMENT for table `sub_departments`
--
ALTER TABLE `sub_departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `supervision_bookings`
--
ALTER TABLE `supervision_bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `supervision_doc_reads`
--
ALTER TABLE `supervision_doc_reads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `supervision_evaluations`
--
ALTER TABLE `supervision_evaluations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `teachers`
--
ALTER TABLE `teachers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=183;

--
-- AUTO_INCREMENT for table `timetable`
--
ALTER TABLE `timetable`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4489;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6945;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `fk_attendance_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_attendance_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `attendance_subjects`
--
ALTER TABLE `attendance_subjects`
  ADD CONSTRAINT `fk_as_student_new` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `auth_tokens`
--
ALTER TABLE `auth_tokens`
  ADD CONSTRAINT `fk_auth_token_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `executives`
--
ALTER TABLE `executives`
  ADD CONSTRAINT `fk_exec_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `homeroom_sessions`
--
ALTER TABLE `homeroom_sessions`
  ADD CONSTRAINT `fk_homeroom_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_homeroom_user` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `point_items`
--
ALTER TABLE `point_items`
  ADD CONSTRAINT `fk_pi_category` FOREIGN KEY (`category_id`) REFERENCES `point_categories` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `point_transactions`
--
ALTER TABLE `point_transactions`
  ADD CONSTRAINT `fk_pt_item` FOREIGN KEY (`item_id`) REFERENCES `point_items` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pt_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `rooms`
--
ALTER TABLE `rooms`
  ADD CONSTRAINT `fk_rooms_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `fk_students_grade_level` FOREIGN KEY (`grade_level_id`) REFERENCES `grade_levels` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_students_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `student_evaluations`
--
ALTER TABLE `student_evaluations`
  ADD CONSTRAINT `fk_se_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `sub_departments`
--
ALTER TABLE `sub_departments`
  ADD CONSTRAINT `fk_subdept_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `supervision_docs`
--
ALTER TABLE `supervision_docs`
  ADD CONSTRAINT `supervision_docs_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `supervision_bookings` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `supervision_doc_reads`
--
ALTER TABLE `supervision_doc_reads`
  ADD CONSTRAINT `supervision_doc_reads_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `supervision_bookings` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `teachers`
--
ALTER TABLE `teachers`
  ADD CONSTRAINT `fk_teachers_advisory_room` FOREIGN KEY (`advisory_room_id`) REFERENCES `rooms` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `timetable`
--
ALTER TABLE `timetable`
  ADD CONSTRAINT `fk_timetable_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
