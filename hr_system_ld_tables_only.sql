-- =============================================================================
-- L&D (Learning & Development) - Add missing tables only
-- Run this in phpMyAdmin SQL tab if hr_system DB already exists.
-- Creates: certifications, employee_assignments, training_feedback
-- =============================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

USE `hr_system`;

-- -----------------------------------------------------------------------------
-- Table: certifications
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `certifications` (
  `certification_id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `skill_id` int(11) DEFAULT NULL,
  `certification_name` varchar(255) NOT NULL,
  `issuing_organization` varchar(255) NOT NULL,
  `certification_number` varchar(100) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `proficiency_level` enum('Beginner','Intermediate','Advanced','Expert') NOT NULL,
  `assessment_score` decimal(5,2) DEFAULT NULL,
  `issue_date` date NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `assessed_date` date NOT NULL,
  `certification_url` varchar(500) DEFAULT NULL,
  `certificate_file_path` varchar(500) DEFAULT NULL,
  `status` enum('Active','Expired','Suspended','Pending Renewal') DEFAULT 'Active',
  `verification_status` enum('Verified','Pending','Failed') DEFAULT 'Pending',
  `cost` decimal(10,2) DEFAULT 0.00,
  `training_hours` int(11) DEFAULT 0,
  `cpe_credits` decimal(5,2) DEFAULT 0.00,
  `renewal_required` tinyint(1) DEFAULT 0,
  `renewal_period_months` int(11) DEFAULT NULL,
  `renewal_reminder_sent` tinyint(1) DEFAULT 0,
  `next_renewal_date` date DEFAULT NULL,
  `prerequisites` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `tags` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`certification_id`),
  KEY `employee_id` (`employee_id`),
  KEY `skill_id` (`skill_id`),
  CONSTRAINT `certifications_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employee_profiles` (`employee_id`) ON DELETE CASCADE,
  CONSTRAINT `certifications_ibfk_2` FOREIGN KEY (`skill_id`) REFERENCES `skill_matrix` (`skill_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------------------------
-- Table: employee_assignments
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `employee_assignments` (
  `assignment_id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `assignment_type` enum('Training','Project','Task','Mentorship','Special Assignment') NOT NULL,
  `assignment_title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `session_id` int(11) DEFAULT NULL,
  `course_id` int(11) DEFAULT NULL,
  `assigned_date` date NOT NULL,
  `start_date` date NOT NULL,
  `due_date` date NOT NULL,
  `completion_date` date DEFAULT NULL,
  `status` enum('Assigned','In Progress','Completed','Overdue','Cancelled') DEFAULT 'Assigned',
  `progress_percentage` decimal(5,2) DEFAULT 0.00,
  `assigned_by_employee_id` int(11) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `priority` enum('Low','Medium','High','Urgent') DEFAULT 'Medium',
  `estimated_hours` decimal(5,2) DEFAULT NULL,
  `actual_hours` decimal(5,2) DEFAULT NULL,
  `completion_notes` text DEFAULT NULL,
  `evaluation_rating` int(11) DEFAULT NULL,
  `evaluation_comments` text DEFAULT NULL,
  `attachments_url` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`assignment_id`),
  KEY `employee_id` (`employee_id`),
  KEY `session_id` (`session_id`),
  KEY `course_id` (`course_id`),
  KEY `assigned_by_employee_id` (`assigned_by_employee_id`),
  KEY `department_id` (`department_id`),
  CONSTRAINT `employee_assignments_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employee_profiles` (`employee_id`) ON DELETE CASCADE,
  CONSTRAINT `employee_assignments_ibfk_2` FOREIGN KEY (`session_id`) REFERENCES `training_sessions` (`session_id`) ON DELETE SET NULL,
  CONSTRAINT `employee_assignments_ibfk_3` FOREIGN KEY (`course_id`) REFERENCES `training_courses` (`course_id`) ON DELETE SET NULL,
  CONSTRAINT `employee_assignments_ibfk_4` FOREIGN KEY (`assigned_by_employee_id`) REFERENCES `employee_profiles` (`employee_id`) ON DELETE SET NULL,
  CONSTRAINT `employee_assignments_ibfk_5` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------------------------
-- Table: training_feedback
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `training_feedback` (
  `feedback_id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `feedback_type` enum('Training Session','Learning Resource','Trainer','Course') NOT NULL,
  `session_id` int(11) DEFAULT NULL,
  `resource_id` int(11) DEFAULT NULL,
  `trainer_id` int(11) DEFAULT NULL,
  `course_id` int(11) DEFAULT NULL,
  `overall_rating` int(11) NOT NULL,
  `content_rating` int(11) DEFAULT NULL,
  `instructor_rating` int(11) DEFAULT NULL,
  `what_worked_well` text DEFAULT NULL,
  `what_could_improve` text DEFAULT NULL,
  `additional_comments` text DEFAULT NULL,
  `would_recommend` tinyint(1) DEFAULT 1,
  `met_expectations` tinyint(1) DEFAULT 1,
  `feedback_date` date NOT NULL DEFAULT (curdate()),
  `is_anonymous` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`feedback_id`),
  KEY `employee_id` (`employee_id`),
  KEY `session_id` (`session_id`),
  KEY `resource_id` (`resource_id`),
  KEY `trainer_id` (`trainer_id`),
  KEY `course_id` (`course_id`),
  CONSTRAINT `training_feedback_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employee_profiles` (`employee_id`) ON DELETE CASCADE,
  CONSTRAINT `training_feedback_ibfk_2` FOREIGN KEY (`session_id`) REFERENCES `training_sessions` (`session_id`) ON DELETE SET NULL,
  CONSTRAINT `training_feedback_ibfk_3` FOREIGN KEY (`resource_id`) REFERENCES `learning_resources` (`resource_id`) ON DELETE SET NULL,
  CONSTRAINT `training_feedback_ibfk_4` FOREIGN KEY (`trainer_id`) REFERENCES `trainers` (`trainer_id`) ON DELETE SET NULL,
  CONSTRAINT `training_feedback_ibfk_5` FOREIGN KEY (`course_id`) REFERENCES `training_courses` (`course_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
