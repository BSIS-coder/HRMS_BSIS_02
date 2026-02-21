-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 22, 2025 at 11:20 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `hr_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `attendance_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `attendance_date` date NOT NULL,
  `clock_in` datetime DEFAULT NULL,
  `clock_out` datetime DEFAULT NULL,
  `status` enum('Present','Absent','Late','Half Day','On Leave') NOT NULL,
  `working_hours` decimal(5,2) DEFAULT NULL,
  `overtime_hours` decimal(5,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance_summary`
--

CREATE TABLE `attendance_summary` (
  `summary_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `month` int(11) NOT NULL,
  `year` year(4) NOT NULL,
  `total_present` int(11) DEFAULT 0,
  `total_absent` int(11) DEFAULT 0,
  `total_late` int(11) DEFAULT 0,
  `total_leave` int(11) DEFAULT 0,
  `total_working_hours` decimal(7,2) DEFAULT 0.00,
  `total_overtime_hours` decimal(5,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `benefits_plans`
--

CREATE TABLE `benefits_plans` (
  `benefit_plan_id` int(11) NOT NULL,
  `plan_name` varchar(100) NOT NULL,
  `plan_type` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `eligibility_criteria` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bonus_payments`
--

CREATE TABLE `bonus_payments` (
  `bonus_payment_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `bonus_type` varchar(50) NOT NULL,
  `bonus_amount` decimal(10,2) NOT NULL,
  `payment_date` date NOT NULL,
  `payroll_cycle_id` int(11) DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `candidates`
--

CREATE TABLE `candidates` (
  `candidate_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `resume_url` varchar(255) DEFAULT NULL,
  `cover_letter_url` varchar(255) DEFAULT NULL,
  `source` varchar(100) DEFAULT NULL,
  `current_position` varchar(100) DEFAULT NULL,
  `current_company` varchar(100) DEFAULT NULL,
  `notice_period` varchar(50) DEFAULT NULL,
  `expected_salary` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `career_paths`
--

CREATE TABLE `career_paths` (
  `path_id` int(11) NOT NULL,
  `path_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `career_path_stages`
--

CREATE TABLE `career_path_stages` (
  `stage_id` int(11) NOT NULL,
  `path_id` int(11) NOT NULL,
  `job_role_id` int(11) NOT NULL,
  `stage_order` int(11) NOT NULL,
  `minimum_time_in_role` int(11) DEFAULT NULL COMMENT 'In months',
  `required_skills` text DEFAULT NULL,
  `required_experience` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `certifications`
--

CREATE TABLE `certifications` (
  `certification_id` int(11) NOT NULL,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `compensation_packages`
--

CREATE TABLE `compensation_packages` (
  `compensation_package_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `package_name` varchar(100) NOT NULL,
  `base_salary` decimal(10,2) NOT NULL,
  `variable_pay` decimal(10,2) DEFAULT 0.00,
  `benefits_summary` text DEFAULT NULL,
  `total_compensation` decimal(10,2) NOT NULL,
  `effective_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `competencies`
--

CREATE TABLE `competencies` (
  `competency_id` int(11) NOT NULL,
  `job_role_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `competencies`
--

INSERT INTO `competencies` (`competency_id`, `job_role_id`, `name`, `description`, `category`, `created_at`, `updated_at`) VALUES
(1, 1, 'Leadership', 'Provides vision and direction for the municipality.', 'Core', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(2, 1, 'Strategic Planning', 'Develops and implements long-term municipal goals.', 'Technical', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(3, 1, 'Public Relations', 'Represents the municipality in community and government affairs.', 'Behavioral', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(4, 2, 'Legislative Management', 'Oversees the drafting and passage of local ordinances.', 'Technical', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(5, 2, 'Conflict Resolution', 'Mediates disputes within the council and community.', 'Behavioral', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(6, 2, 'Public Communication', 'Communicates effectively with citizens and stakeholders.', 'Behavioral', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(7, 3, 'Policy Formulation', 'Creates and supports local policies and ordinances.', 'Technical', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(8, 3, 'Community Outreach', 'Engages with the public to understand community needs.', 'Behavioral', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(9, 3, 'Decision Making', 'Makes informed choices to benefit the local community.', 'Core', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(10, 4, 'Revenue Collection', 'Ensures efficient and transparent collection of taxes and fees.', 'Technical', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(11, 4, 'Financial Reporting', 'Prepares accurate financial statements.', 'Technical', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(12, 4, 'Accountability', 'Maintains transparency in handling municipal funds.', 'Core', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(13, 5, 'Budget Preparation', 'Prepares annual municipal budget in coordination with departments.', 'Technical', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(14, 5, 'Fiscal Analysis', 'Analyzes financial data to ensure balanced budgeting.', 'Technical', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(15, 5, 'Resource Allocation', 'Distributes resources effectively to meet objectives.', 'Core', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(16, 6, 'Financial Auditing', 'Conducts internal financial reviews for accuracy.', 'Technical', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(17, 6, 'Compliance Monitoring', 'Ensures all transactions follow accounting standards.', 'Technical', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(18, 6, 'Attention to Detail', 'Maintains precision in recording transactions.', 'Behavioral', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(19, 7, 'Urban Planning', 'Designs and implements sustainable development projects.', 'Technical', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(20, 7, 'Project Evaluation', 'Monitors and assesses progress of municipal plans.', 'Technical', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(21, 7, 'Analytical Thinking', 'Uses data-driven analysis for development decisions.', 'Behavioral', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(22, 8, 'Infrastructure Design', 'Creates engineering plans for municipal projects.', 'Technical', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(23, 8, 'Construction Oversight', 'Supervises construction works for quality and safety.', 'Technical', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(24, 8, 'Problem Solving', 'Resolves engineering and logistical challenges effectively.', 'Behavioral', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(25, 9, 'Records Management', 'Manages vital records such as births, deaths, and marriages.', 'Administrative', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(26, 9, 'Data Accuracy', 'Ensures completeness and correctness of civil documents.', 'Technical', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(27, 9, 'Customer Service', 'Provides courteous and efficient service to citizens.', 'Behavioral', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(28, 10, 'Public Health Management', 'Oversees local health programs and facilities.', 'Core', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(29, 10, 'Epidemiology', 'Monitors and responds to health issues within the municipality.', 'Technical', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(30, 10, 'Team Leadership', 'Leads and mentors municipal health staff.', 'Behavioral', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(31, 11, 'Case Management', 'Handles cases involving vulnerable individuals and families.', 'Technical', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(32, 11, 'Program Implementation', 'Executes social welfare programs efficiently.', 'Technical', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(33, 11, 'Empathy', 'Demonstrates compassion in dealing with community members.', 'Behavioral', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(34, 12, 'Crop Production Management', 'Promotes modern and sustainable farming techniques.', 'Technical', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(35, 12, 'Farmer Training', 'Conducts training and workshops for farmers.', 'Technical', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(36, 12, 'Environmental Awareness', 'Encourages eco-friendly agricultural practices.', 'Behavioral', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(37, 13, 'Property Valuation', 'Determines fair property assessments.', 'Technical', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(38, 13, 'Data Verification', 'Ensures accurate real property data.', 'Technical', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(39, 13, 'Integrity', 'Upholds honesty in property assessments.', 'Core', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(40, 14, 'Recruitment and Selection', 'Manages hiring processes to attract qualified candidates.', 'Technical', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(41, 14, 'Performance Evaluation', 'Implements employee appraisal systems.', 'Technical', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(42, 14, 'Employee Relations', 'Builds a positive and inclusive workplace culture.', 'Behavioral', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(43, 15, 'Disaster Preparedness', 'Develops and conducts disaster response plans.', 'Technical', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(44, 15, 'Emergency Coordination', 'Leads emergency response teams during disasters.', 'Technical', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(45, 15, 'Risk Assessment', 'Identifies and mitigates potential hazards.', 'Core', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(46, 16, 'Asset Management', 'Oversees the maintenance of municipal properties.', 'Technical', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(47, 16, 'Procurement Planning', 'Ensures proper acquisition of goods and services.', 'Technical', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(48, 16, 'Efficiency', 'Optimizes municipal logistics and operations.', 'Behavioral', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(49, 17, 'Patient Care', 'Provides compassionate and professional nursing services.', 'Technical', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(50, 17, 'Health Education', 'Promotes wellness and preventive healthcare.', 'Technical', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(51, 17, 'Teamwork', 'Collaborates with other healthcare professionals effectively.', 'Behavioral', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(52, 18, 'Maternal Care', 'Provides prenatal, delivery, and postnatal care.', 'Technical', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(53, 18, 'Community Health', 'Educates mothers on health and hygiene practices.', 'Behavioral', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(54, 18, 'Emergency Response', 'Responds effectively to maternal emergencies.', 'Technical', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(55, 19, 'Health Inspection', 'Inspects sanitation facilities and waste management systems.', 'Technical', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(56, 19, 'Public Safety Compliance', 'Ensures establishments follow sanitation laws.', 'Technical', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(57, 19, 'Observation Skills', 'Identifies and corrects potential public health hazards.', 'Behavioral', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(58, 20, 'Counseling', 'Provides emotional and practical support to clients.', 'Technical', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(59, 20, 'Case Documentation', 'Maintains accurate client records.', 'Administrative', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(60, 20, 'Interpersonal Skills', 'Builds trust with individuals and families.', 'Behavioral', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(61, 21, 'Soil Management', 'Analyzes soil quality and recommends treatments.', 'Technical', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(62, 21, 'Field Monitoring', 'Assists in implementing agricultural projects.', 'Technical', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(63, 21, 'Communication', 'Advises farmers on best agricultural practices.', 'Behavioral', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(64, 22, 'Structural Design', 'Creates and verifies engineering blueprints.', 'Technical', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(65, 22, 'Safety Compliance', 'Ensures all construction projects meet safety standards.', 'Technical', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(66, 22, 'Critical Thinking', 'Analyzes problems and provides effective engineering solutions.', 'Behavioral', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(67, 23, 'Technical Drafting', 'Prepares precise CAD drawings for projects.', 'Technical', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(68, 23, 'Attention to Detail', 'Maintains accuracy in design documentation.', 'Behavioral', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(69, 23, 'Collaboration', 'Works closely with engineers and architects.', 'Behavioral', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(70, 24, 'Building Code Enforcement', 'Ensures structures comply with safety regulations.', 'Technical', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(71, 24, 'Inspection Reporting', 'Prepares detailed inspection reports.', 'Technical', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(72, 24, 'Integrity', 'Maintains impartiality during inspections.', 'Core', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(73, 25, 'Budget Evaluation', 'Analyzes and reviews budget requests for compliance.', 'Technical', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(74, 25, 'Financial Forecasting', 'Predicts financial trends to guide budgeting decisions.', 'Technical', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(75, 25, 'Analytical Thinking', 'Interprets complex financial data accurately.', 'Behavioral', '2025-10-22 07:45:57', '2025-10-22 07:45:57'),
(76, 26, 'Bookkeeping', 'Maintains accurate financial records and ledgers.', 'Technical', '2025-10-22 07:50:22', '2025-10-22 07:50:22'),
(77, 26, 'Data Accuracy', 'Ensures precision when recording financial transactions.', 'Behavioral', '2025-10-22 07:50:22', '2025-10-22 07:50:22'),
(78, 26, 'Financial Reporting', 'Prepares monthly and annual financial reports.', 'Technical', '2025-10-22 07:50:22', '2025-10-22 07:50:22'),
(79, 27, 'Research and Data Analysis', 'Collects and interprets data for planning purposes.', 'Technical', '2025-10-22 07:50:22', '2025-10-22 07:50:22'),
(80, 27, 'Project Documentation', 'Prepares planning documents and proposals.', 'Administrative', '2025-10-22 07:50:22', '2025-10-22 07:50:22'),
(81, 27, 'Collaboration', 'Works effectively with the planning coordinator and other departments.', 'Behavioral', '2025-10-22 07:50:22', '2025-10-22 07:50:22'),
(82, 28, 'Clerical Support', 'Assists with filing, record keeping, and basic office tasks.', 'Administrative', '2025-10-22 07:50:22', '2025-10-22 07:50:22'),
(83, 28, 'Time Management', 'Completes assigned tasks promptly and efficiently.', 'Behavioral', '2025-10-22 07:50:22', '2025-10-22 07:50:22'),
(84, 28, 'Office Organization', 'Keeps documents and materials organized for easy retrieval.', 'Behavioral', '2025-10-22 07:50:22', '2025-10-22 07:50:22'),
(85, 29, 'Document Management', 'Files and retrieves official documents systematically.', 'Administrative', '2025-10-22 07:50:22', '2025-10-22 07:50:22'),
(86, 29, 'Communication Skills', 'Coordinates effectively with internal and external clients.', 'Behavioral', '2025-10-22 07:50:22', '2025-10-22 07:50:22'),
(87, 29, 'Attention to Detail', 'Ensures accuracy in records and correspondence.', 'Behavioral', '2025-10-22 07:50:22', '2025-10-22 07:50:22'),
(88, 30, 'Cash Handling', 'Processes payments and receipts accurately and securely.', 'Technical', '2025-10-22 07:50:22', '2025-10-22 07:50:22'),
(89, 30, 'Customer Service', 'Provides courteous service when handling transactions.', 'Behavioral', '2025-10-22 07:50:22', '2025-10-22 07:50:22'),
(90, 30, 'Account Reconciliation', 'Balances daily cash collections and deposits.', 'Technical', '2025-10-22 07:50:22', '2025-10-22 07:50:22'),
(91, 31, 'Revenue Collection', 'Collects payments from citizens and businesses efficiently.', 'Technical', '2025-10-22 07:50:22', '2025-10-22 07:50:22'),
(92, 31, 'Record Accuracy', 'Maintains accurate records of collections and receipts.', 'Administrative', '2025-10-22 07:50:22', '2025-10-22 07:50:22'),
(93, 31, 'Integrity', 'Handles municipal funds responsibly and ethically.', 'Core', '2025-10-22 07:50:22', '2025-10-22 07:50:22'),
(94, 32, 'Inventory Management', 'Maintains records of all municipal assets.', 'Technical', '2025-10-22 07:50:22', '2025-10-22 07:50:22'),
(95, 32, 'Asset Security', 'Ensures safekeeping of government property and supplies.', 'Core', '2025-10-22 07:50:22', '2025-10-22 07:50:22'),
(96, 32, 'Reporting', 'Prepares reports on equipment condition and usage.', 'Administrative', '2025-10-22 07:50:22', '2025-10-22 07:50:22'),
(97, 33, 'Facility Maintenance', 'Performs repairs and upkeep of municipal buildings.', 'Technical', '2025-10-22 07:50:22', '2025-10-22 07:50:22'),
(98, 33, 'Safety Compliance', 'Follows safety standards when performing maintenance work.', 'Core', '2025-10-22 07:50:22', '2025-10-22 07:50:22'),
(99, 33, 'Teamwork', 'Works cooperatively with maintenance and engineering teams.', 'Behavioral', '2025-10-22 07:50:22', '2025-10-22 07:50:22'),
(100, 34, 'Cleaning and Sanitation', 'Maintains cleanliness of municipal facilities.', 'Technical', '2025-10-22 07:50:22', '2025-10-22 07:50:22'),
(101, 34, 'Waste Management', 'Properly handles waste disposal and recycling.', 'Technical', '2025-10-22 07:50:22', '2025-10-22 07:50:22'),
(102, 34, 'Dependability', 'Performs assigned duties reliably and on time.', 'Behavioral', '2025-10-22 07:50:22', '2025-10-22 07:50:22'),
(103, 35, 'Vehicle Operation', 'Operates municipal vehicles safely and responsibly.', 'Technical', '2025-10-22 07:50:22', '2025-10-22 07:50:22'),
(104, 35, 'Vehicle Maintenance', 'Conducts basic checks and ensures vehicles are in good condition.', 'Technical', '2025-10-22 07:50:22', '2025-10-22 07:50:22'),
(105, 35, 'Punctuality', 'Adheres to schedules and assigned routes consistently.', 'Behavioral', '2025-10-22 07:50:22', '2025-10-22 07:50:22'),
(106, 36, 'Security Monitoring', 'Guards municipal premises and monitors access points.', 'Technical', '2025-10-22 07:50:22', '2025-10-22 07:50:22'),
(107, 36, 'Crisis Response', 'Responds quickly and appropriately to emergencies.', 'Core', '2025-10-22 07:50:22', '2025-10-22 07:50:22'),
(108, 36, 'Discipline', 'Demonstrates professionalism and vigilance on duty.', 'Behavioral', '2025-10-22 07:50:22', '2025-10-22 07:50:22'),
(109, 37, 'Research Assistance', 'Assists in gathering information for legislative measures.', 'Technical', '2025-10-22 07:50:22', '2025-10-22 07:50:22'),
(110, 37, 'Documentation', 'Prepares and organizes legislative documents and minutes.', 'Administrative', '2025-10-22 07:50:22', '2025-10-22 07:50:22'),
(111, 37, 'Confidentiality', 'Maintains discretion when handling official legislative matters.', 'Core', '2025-10-22 07:50:22', '2025-10-22 07:50:22');

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `department_id` int(11) NOT NULL,
  `department_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`department_id`, `department_name`, `description`, `location`, `created_at`, `updated_at`) VALUES
(1, 'Office of the Mayor', 'Executive office responsible for municipal governance and administration', 'City Hall - 2nd Floor', '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(2, 'Sangguniang Bayan', 'Municipal legislative body responsible for enacting local ordinances', 'City Hall - Session Hall', '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(3, 'Municipal Treasurer\'s Office', 'Handles municipal revenue collection, treasury operations, and financial management', 'City Hall - 1st Floor', '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(4, 'Municipal Budget Office', 'Responsible for budget preparation, monitoring, and financial planning', 'City Hall - 1st Floor', '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(5, 'Municipal Accountant\'s Office', 'Manages municipal accounting, bookkeeping, and financial reporting', 'City Hall - 1st Floor', '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(6, 'Municipal Planning & Development Office', 'Handles municipal planning, development programs, and project management', 'City Hall - 3rd Floor', '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(7, 'Municipal Engineer\'s Office', 'Oversees infrastructure projects, public works, and engineering services', 'Engineering Building', '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(8, 'Municipal Civil Registrar\'s Office', 'Manages civil registration services and vital statistics', 'City Hall - Ground Floor', '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(9, 'Municipal Health Office', 'Provides public health services and healthcare programs', 'Health Center Building', '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(10, 'Municipal Social Welfare & Development Office', 'Administers social services and community development programs', 'Social Services Building', '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(11, 'Municipal Agriculture Office', 'Supports agricultural development and provides farming assistance', 'Agriculture Extension Office', '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(12, 'Municipal Assessor\'s Office', 'Conducts property assessment and real property taxation', 'City Hall - Ground Floor', '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(13, 'Municipal Human Resource & Administrative Office', 'Manages personnel administration and human resources', 'City Hall - 2nd Floor', '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(14, 'Municipal Disaster Risk Reduction & Management Office', 'Coordinates disaster preparedness and emergency response', 'Emergency Operations Center', '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(15, 'General Services Office', 'Provides general administrative support and facility management', 'City Hall - Basement', '2025-09-09 02:00:15', '2025-09-09 02:00:15');

-- --------------------------------------------------------

--
-- Table structure for table `development_activities`
--

CREATE TABLE `development_activities` (
  `activity_id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `activity_name` varchar(100) NOT NULL,
  `activity_type` enum('Training','Mentoring','Project','Education','Other') NOT NULL,
  `description` text DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('Not Started','In Progress','Completed') DEFAULT 'Not Started',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `development_plans`
--

CREATE TABLE `development_plans` (
  `plan_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `plan_name` varchar(100) NOT NULL,
  `plan_description` text DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('Draft','Active','Completed','Cancelled') DEFAULT 'Draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `document_management`
--

CREATE TABLE `document_management` (
  `document_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `document_type` enum('Contract','ID','Resume','Certificate','Performance Review','Other') NOT NULL,
  `document_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `upload_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `expiry_date` date DEFAULT NULL,
  `document_status` enum('Active','Expired','Pending Review') DEFAULT 'Active',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document_management`
--

INSERT INTO `document_management` (`document_id`, `employee_id`, `document_type`, `document_name`, `file_path`, `upload_date`, `expiry_date`, `document_status`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, '', 'Appointment Order - Municipal Treasurer', '/documents/appointments/maria_santos_appointment.pdf', '2025-09-09 02:00:16', NULL, 'Active', 'Appointed by Mayor per Civil Service guidelines', '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(2, 1, 'Contract', 'Employment Contract - Municipal Treasurer', '/documents/contracts/maria_santos_contract.pdf', '2025-09-09 02:00:16', '2025-07-01', 'Active', 'Department head contract', '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(3, 1, 'Resume', 'Resume - Maria Santos', '/documents/resumes/maria_santos_resume.pdf', '2025-09-09 02:00:16', NULL, 'Active', 'CPA with municipal finance experience', '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(4, 2, '', 'Appointment Order - Municipal Engineer', '/documents/appointments/roberto_cruz_appointment.pdf', '2025-09-09 02:00:16', NULL, 'Active', 'Licensed Civil Engineer appointment', '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(5, 2, 'Certificate', 'Professional Engineer License', '/documents/licenses/roberto_cruz_pe_license.pdf', '2025-09-09 02:00:16', '2025-12-31', 'Active', 'Updated PRC license', '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(6, 2, 'Contract', 'Employment Contract - Municipal Engineer', '/documents/contracts/roberto_cruz_contract.pdf', '2025-09-09 02:00:16', '2024-06-15', 'Active', 'Engineering department head', '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(7, 3, 'Contract', 'Employment Contract - Nurse', '/documents/contracts/jennifer_reyes_contract.pdf', '2025-09-09 02:00:16', '2025-01-20', 'Active', 'Municipal health office nurse', '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(8, 3, 'Certificate', 'Nursing License', '/documents/licenses/jennifer_reyes_rn_license.pdf', '2025-09-09 02:00:16', '2025-08-31', 'Active', 'Updated PRC nursing license', '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(9, 3, 'Certificate', 'Basic Life Support Training', '/documents/certificates/jennifer_reyes_bls_cert.pdf', '2025-09-09 02:00:16', '2024-12-31', 'Active', 'Required medical certification', '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(10, 4, 'Contract', 'Employment Contract - CAD Operator', '/documents/contracts/antonio_garcia_contract.pdf', '2025-09-09 02:00:16', '2024-03-10', 'Active', 'Engineering support staff', '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(11, 4, 'Certificate', 'AutoCAD Certification', '/documents/certificates/antonio_garcia_autocad_cert.pdf', '2025-09-09 02:00:16', '2025-06-30', 'Active', 'Professional CAD certification', '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(12, 5, 'Contract', 'Employment Contract - Social Worker', '/documents/contracts/lisa_mendoza_contract.pdf', '2025-09-09 02:00:16', '2024-09-05', 'Active', 'MSWDO social worker', '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(13, 5, 'Certificate', 'Social Work License', '/documents/licenses/lisa_mendoza_sw_license.pdf', '2025-09-09 02:00:16', '2025-10-31', 'Active', 'Updated PRC social work license', '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(14, 6, 'Contract', 'Employment Contract - Accounting Staff', '/documents/contracts/michael_torres_contract.pdf', '2025-09-09 02:00:16', '2025-11-12', 'Active', 'Municipal accountant office staff', '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(15, 6, 'Certificate', 'Bookkeeping Certification', '/documents/certificates/michael_torres_bookkeeping_cert.pdf', '2025-09-09 02:00:16', '2024-12-31', 'Active', 'Professional bookkeeping certification', '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(16, 7, 'Contract', 'Employment Contract - Clerk', '/documents/contracts/carmen_delacruz_contract.pdf', '2025-09-09 02:00:16', '2025-02-28', 'Active', 'Civil registrar office clerk', '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(17, 7, '', 'Civil Registration Training', '/documents/training/carmen_delacruz_civil_reg_training.pdf', '2025-09-09 02:00:16', NULL, 'Active', 'Specialized civil registration procedures', '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(18, 8, 'Contract', 'Employment Contract - Maintenance Worker', '/documents/contracts/ricardo_villanueva_contract.pdf', '2025-09-09 02:00:16', '2024-05-18', 'Active', 'General services maintenance', '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(19, 8, 'Certificate', 'Electrical Safety Training', '/documents/certificates/ricardo_villanueva_electrical_safety.pdf', '2025-09-09 02:00:16', '2024-12-31', 'Active', 'Safety certification for maintenance work', '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(20, 9, 'Contract', 'Employment Contract - Cashier', '/documents/contracts/sandra_pascual_contract.pdf', '2025-09-09 02:00:16', '2025-09-10', 'Active', 'Treasury office cashier', '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(21, 9, '', 'Financial Management Training', '/documents/training/sandra_pascual_finance_training.pdf', '2025-09-09 02:00:16', NULL, 'Active', 'Municipal financial procedures training', '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(22, 10, 'Contract', 'Employment Contract - Collection Officer', '/documents/contracts/jose_ramos_contract.pdf', '2025-09-09 02:00:16', '2024-12-01', 'Active', 'Revenue collection specialist', '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(23, 10, '', 'Revenue Collection Procedures', '/documents/training/jose_ramos_collection_training.pdf', '2025-09-09 02:00:16', NULL, 'Active', 'Specialized revenue collection training', '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(24, 11, 'Contract', 'Employment Contract - Administrative Aide', '/documents/contracts/ana_morales_contract.pdf', '2025-09-09 02:00:16', '2025-04-15', 'Active', 'HR office administrative support', '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(25, 12, 'Contract', 'Employment Contract - Agricultural Technician', '/documents/contracts/pablo_fernandez_contract.pdf', '2025-09-09 02:00:16', '2024-08-20', 'Active', 'Agriculture office technical staff', '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(26, 12, 'Certificate', 'Agricultural Extension Training', '/documents/certificates/pablo_fernandez_agri_ext_cert.pdf', '2025-09-09 02:00:16', '2025-07-31', 'Active', 'Agricultural extension certification', '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(27, 13, 'Contract', 'Employment Contract - Midwife', '/documents/contracts/grace_lopez_contract.pdf', '2025-09-09 02:00:16', '2025-06-30', 'Active', 'Municipal health office midwife', '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(28, 13, 'Certificate', 'Midwifery License', '/documents/licenses/grace_lopez_midwife_license.pdf', '2025-09-09 02:00:16', '2025-09-30', 'Active', 'Updated PRC midwifery license', '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(29, 14, 'Contract', 'Employment Contract - Driver', '/documents/contracts/eduardo_hernandez_contract.pdf', '2025-09-09 02:00:16', '2025-01-10', 'Active', 'Municipal vehicle operator', '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(30, 14, 'Certificate', 'Professional Driver License', '/documents/licenses/eduardo_hernandez_driver_license.pdf', '2025-09-09 02:00:16', '2025-12-31', 'Active', 'Professional driver\'s license', '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(31, 15, 'Contract', 'Employment Contract - Security Personnel', '/documents/contracts/rosario_gonzales_contract.pdf', '2025-09-09 02:00:16', '2024-11-05', 'Active', 'Municipal facility security', '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(32, 15, 'Certificate', 'Security Guard License', '/documents/licenses/rosario_gonzales_security_license.pdf', '2025-09-09 02:00:16', '2025-08-31', 'Active', 'SOSIA security guard license', '2025-09-09 02:00:16', '2025-09-09 02:00:16');

-- --------------------------------------------------------

--
-- Table structure for table `employee_assignments`
--

CREATE TABLE `employee_assignments` (
  `assignment_id` int(11) NOT NULL,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employee_benefits`
--

CREATE TABLE `employee_benefits` (
  `benefit_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `benefit_plan_id` int(11) NOT NULL,
  `enrollment_date` date NOT NULL,
  `benefit_amount` decimal(10,2) DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employee_career_paths`
--

CREATE TABLE `employee_career_paths` (
  `employee_path_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `path_id` int(11) NOT NULL,
  `current_stage_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `target_completion_date` date DEFAULT NULL,
  `status` enum('Active','Completed','On Hold','Abandoned') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employee_competencies`
--

CREATE TABLE `employee_competencies` (
  `employee_id` int(11) NOT NULL,
  `competency_id` int(11) NOT NULL,
  `cycle_id` int(11) NOT NULL,
  `rating` int(11) NOT NULL,
  `assessment_date` date NOT NULL,
  `comments` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employee_competencies`
--

INSERT INTO `employee_competencies` (`employee_id`, `competency_id`, `cycle_id`, `rating`, `assessment_date`, `comments`, `created_at`, `updated_at`) VALUES
(2, 22, 3, 4, '2025-10-22', 'impressive', '2025-10-22 08:13:30', '2025-10-22 08:13:30'),
(2, 23, 3, 3, '2025-10-22', 'nice', '2025-10-22 08:13:30', '2025-10-22 08:13:30'),
(2, 24, 3, 5, '2025-10-22', 'excellent', '2025-10-22 08:13:30', '2025-10-22 08:13:30'),
(11, 76, 3, 3, '2025-10-22', 'nice', '2025-10-22 08:58:29', '2025-10-22 08:58:29'),
(11, 77, 3, 3, '2025-10-22', 'amazing', '2025-10-22 08:58:29', '2025-10-22 08:58:29'),
(11, 78, 3, 4, '2025-10-22', 'excellent', '2025-10-22 08:58:29', '2025-10-22 08:58:29');

-- --------------------------------------------------------

--
-- Table structure for table `employee_onboarding`
--

CREATE TABLE `employee_onboarding` (
  `onboarding_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `expected_completion_date` date NOT NULL,
  `status` enum('Pending','In Progress','Completed') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employee_onboarding_tasks`
--

CREATE TABLE `employee_onboarding_tasks` (
  `employee_task_id` int(11) NOT NULL,
  `onboarding_id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL,
  `due_date` date NOT NULL,
  `status` enum('Not Started','In Progress','Completed','Cancelled') DEFAULT 'Not Started',
  `completion_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employee_profiles`
--

CREATE TABLE `employee_profiles` (
  `employee_id` int(11) NOT NULL,
  `personal_info_id` int(11) DEFAULT NULL,
  `job_role_id` int(11) DEFAULT NULL,
  `employee_number` varchar(20) NOT NULL,
  `hire_date` date NOT NULL,
  `employment_status` enum('Full-time','Part-time','Contract','Intern','Terminated') NOT NULL,
  `current_salary` decimal(10,2) NOT NULL,
  `work_email` varchar(100) DEFAULT NULL,
  `work_phone` varchar(20) DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `remote_work` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employee_profiles`
--

INSERT INTO `employee_profiles` (`employee_id`, `personal_info_id`, `job_role_id`, `employee_number`, `hire_date`, `employment_status`, `current_salary`, `work_email`, `work_phone`, `location`, `remote_work`, `created_at`, `updated_at`) VALUES
(1, 1, 4, 'MUN001', '2019-07-01', 'Full-time', 65000.00, 'maria.santos@municipality.gov.ph', '034-123-0001', 'City Hall - 1st Floor', 0, '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(2, 2, 8, 'MUN002', '2018-06-15', 'Full-time', 75000.00, 'roberto.cruz@municipality.gov.ph', '034-123-0002', 'Engineering Building', 0, '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(3, 3, 17, 'MUN003', '2020-01-20', 'Full-time', 42000.00, 'jennifer.reyes@municipality.gov.ph', '034-123-0003', 'Municipal Health Office', 0, '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(4, 4, 21, 'MUN004', '2019-03-10', 'Full-time', 38000.00, 'antonio.garcia@municipality.gov.ph', '034-123-0004', 'Municipal Engineer\'s Office', 0, '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(5, 5, 20, 'MUN005', '2021-09-05', 'Full-time', 45000.00, 'lisa.mendoza@municipality.gov.ph', '034-123-0005', 'Municipal Social Welfare & Development Office', 0, '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(6, 6, 25, 'MUN006', '2020-11-12', 'Full-time', 28000.00, 'michael.torres@municipality.gov.ph', '034-123-0006', 'Municipal Accountant\'s Office', 0, '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(7, 7, 27, 'MUN007', '2022-02-28', 'Full-time', 30000.00, 'carmen.delacruz@municipality.gov.ph', '034-123-0007', 'Municipal Civil Registrar\'s Office', 0, '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(8, 8, 32, 'MUN008', '2021-05-18', 'Full-time', 22000.00, 'ricardo.villanueva@municipality.gov.ph', '034-123-0008', 'General Services Office', 0, '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(9, 9, 28, 'MUN009', '2020-09-10', 'Full-time', 32000.00, 'sandra.pascual@municipality.gov.ph', '034-123-0009', 'Municipal Treasurer\'s Office', 0, '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(10, 10, 29, 'MUN010', '2019-12-01', 'Full-time', 35000.00, 'jose.ramos@municipality.gov.ph', '034-123-0010', 'Municipal Treasurer\'s Office', 0, '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(11, 11, 26, 'MUN011', '2022-04-15', 'Full-time', 28000.00, 'ana.morales@municipality.gov.ph', '034-123-0011', 'Municipal Human Resource & Administrative Office', 0, '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(12, 12, 19, 'MUN012', '2021-08-20', 'Full-time', 40000.00, 'pablo.fernandez@municipality.gov.ph', '034-123-0012', 'Municipal Agriculture Office', 0, '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(13, 13, 18, 'MUN013', '2020-06-30', 'Full-time', 42000.00, 'grace.lopez@municipality.gov.ph', '034-123-0013', 'Municipal Health Office', 0, '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(14, 14, 31, 'MUN014', '2022-01-10', 'Full-time', 25000.00, 'eduardo.hernandez@municipality.gov.ph', '034-123-0014', 'General Services Office', 0, '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(15, 15, 33, 'MUN015', '2021-11-05', 'Full-time', 24000.00, 'rosario.gonzales@municipality.gov.ph', '034-123-0015', 'General Services Office', 0, '2025-09-09 02:00:16', '2025-09-09 02:00:16');

-- --------------------------------------------------------

--
-- Table structure for table `employee_resources`
--

CREATE TABLE `employee_resources` (
  `employee_resource_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `resource_id` int(11) NOT NULL,
  `assigned_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `completed_date` date DEFAULT NULL,
  `status` enum('Assigned','In Progress','Completed','Overdue') DEFAULT 'Assigned',
  `rating` int(11) DEFAULT NULL,
  `feedback` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employee_shifts`
--

CREATE TABLE `employee_shifts` (
  `employee_shift_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `shift_id` int(11) NOT NULL,
  `assigned_date` date NOT NULL,
  `is_overtime` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employee_shifts`
--

INSERT INTO `employee_shifts` (`employee_shift_id`, `employee_id`, `shift_id`, `assigned_date`, `is_overtime`, `created_at`, `updated_at`) VALUES
(1, 1, 1, '2024-01-15', 0, '2025-09-14 07:13:53', '2025-09-14 07:13:53'),
(2, 2, 2, '2024-01-15', 1, '2025-09-14 07:13:53', '2025-09-14 07:13:53'),
(3, 3, 1, '2024-01-16', 0, '2025-09-14 07:13:53', '2025-09-14 07:13:53'),
(4, 4, 3, '2024-01-16', 0, '2025-09-14 07:13:53', '2025-09-14 07:13:53'),
(5, 5, 1, '2024-01-17', 0, '2025-09-14 07:13:53', '2025-09-14 07:13:53'),
(6, 6, 2, '2024-01-17', 1, '2025-09-14 07:13:53', '2025-09-14 07:13:53'),
(7, 7, 1, '2024-01-18', 0, '2025-09-14 07:13:53', '2025-09-14 07:13:53'),
(8, 8, 4, '2024-01-18', 0, '2025-09-14 07:13:53', '2025-09-14 07:13:53');

-- --------------------------------------------------------

--
-- Table structure for table `employee_skills`
--

CREATE TABLE `employee_skills` (
  `employee_skill_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `skill_id` int(11) NOT NULL,
  `proficiency_level` enum('Beginner','Intermediate','Advanced','Expert') NOT NULL,
  `assessed_date` date NOT NULL,
  `certification_url` varchar(255) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employment_history`
--

CREATE TABLE `employment_history` (
  `history_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `job_title` varchar(150) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `employment_type` enum('Full-time','Part-time','Contractual','Project-based','Casual','Intern') NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `employment_status` enum('Active','Resigned','Terminated','Retired','End of Contract','Transferred') NOT NULL,
  `reporting_manager_id` int(11) DEFAULT NULL,
  `location` varchar(150) DEFAULT NULL,
  `base_salary` decimal(10,2) NOT NULL,
  `allowances` decimal(10,2) DEFAULT 0.00,
  `bonuses` decimal(10,2) DEFAULT 0.00,
  `salary_adjustments` decimal(10,2) DEFAULT 0.00,
  `reason_for_change` varchar(255) DEFAULT NULL,
  `promotions_transfers` text DEFAULT NULL,
  `duties_responsibilities` text DEFAULT NULL,
  `performance_evaluations` text DEFAULT NULL,
  `training_certifications` text DEFAULT NULL,
  `contract_details` text DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employment_history`
--

INSERT INTO `employment_history` (`history_id`, `employee_id`, `job_title`, `department_id`, `employment_type`, `start_date`, `end_date`, `employment_status`, `reporting_manager_id`, `location`, `base_salary`, `allowances`, `bonuses`, `salary_adjustments`, `reason_for_change`, `promotions_transfers`, `duties_responsibilities`, `performance_evaluations`, `training_certifications`, `contract_details`, `remarks`, `created_at`, `updated_at`) VALUES
(1, 1, 'Municipal Treasurer', 3, 'Full-time', '2019-07-01', NULL, 'Active', NULL, 'City Hall - 1st Floor', 65000.00, 5000.00, 0.00, 0.00, 'Appointed as Municipal Treasurer', 'Promoted from Administrative Aide', 'Oversees treasury operations, municipal revenue collection, and financial management.', 'Consistently rated \"Excellent\" in financial audits', 'CPA Certification, Treasury Management Training', 'Appointed by Mayor, renewable 6-year term', 'Key finance official', '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(2, 2, 'Municipal Engineer', 7, 'Full-time', '2018-06-15', NULL, 'Active', NULL, 'Engineering Building', 75000.00, 6000.00, 0.00, 0.00, 'Appointed as Municipal Engineer', 'Promoted from CAD Operator', 'Supervises infrastructure projects, designs municipal roads and buildings.', 'Rated \"Very Satisfactory\" in infrastructure project completion', 'PRC Civil Engineer License, Project Management Certification', 'Appointed by Mayor, renewable 6-year term', 'Head of engineering department', '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(3, 3, 'Nurse', 9, 'Full-time', '2020-01-20', NULL, 'Active', 10, 'Municipal Health Office', 42000.00, 3000.00, 0.00, 0.00, 'Hired as Nurse', NULL, 'Provides nursing care, assists doctors, administers vaccinations.', 'Highly commended during pandemic response', 'PRC Nursing License, Basic Life Support Training', 'Contract renewable every 3 years', 'Dedicated health staff', '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(4, 4, 'CAD Operator', 7, 'Full-time', '2019-03-10', NULL, 'Active', 2, 'Municipal Engineer\'s Office', 38000.00, 2000.00, 0.00, 0.00, 'Hired as CAD Operator', NULL, 'Prepares AutoCAD drawings and engineering plans.', 'Satisfactory performance in multiple LGU projects', 'AutoCAD Certification', 'Fixed-term renewable contract', 'Key engineering support', '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(5, 5, 'Social Worker', 10, 'Full-time', '2021-09-05', NULL, 'Active', NULL, 'Municipal Social Welfare & Development Office', 45000.00, 3000.00, 0.00, 0.00, 'Hired as Social Worker', 'Promoted from Administrative Aide', 'Handles casework, provides assistance to indigent families.', 'Rated \"Very Good\" in community outreach', 'Social Work License, Community Development Training', 'Regular plantilla position', 'Handles social services cases', '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(6, 6, 'Accounting Staff', 5, 'Full-time', '2020-11-12', NULL, 'Active', NULL, 'Municipal Accountant\'s Office', 28000.00, 1500.00, 0.00, 0.00, 'Hired as Accounting Staff', NULL, 'Processes vouchers, prepares reports, assists in bookkeeping.', 'Satisfactory audit reviews', 'Bookkeeping Certification', 'Regular plantilla position', 'Junior accounting role', '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(7, 7, 'Clerk', 8, 'Full-time', '2022-02-28', NULL, 'Active', NULL, 'Municipal Civil Registrar\'s Office', 30000.00, 1000.00, 0.00, 0.00, 'Hired as Clerk', NULL, 'Maintains registry records, assists clients with civil documents.', 'Rated \"Good\" by supervisor', 'Civil Registration Training', 'Contract renewable every 2 years', 'Support staff', '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(8, 8, 'Maintenance Worker', 15, 'Full-time', '2021-05-18', NULL, 'Active', NULL, 'General Services Office', 22000.00, 1000.00, 0.00, 0.00, 'Hired as Maintenance Worker', NULL, 'Performs facility maintenance and minor repairs.', 'Satisfactory in safety inspections', 'Electrical Safety Training', 'Casual employment converted to regular', 'Assigned to city hall facilities', '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(9, 9, 'Cashier', 3, 'Full-time', '2020-09-10', NULL, 'Active', 1, 'Municipal Treasurer\'s Office', 32000.00, 2000.00, 0.00, 0.00, 'Hired as Cashier', 'Promoted from Clerk', 'Handles cash collection, prepares daily receipts.', 'Commended for accurate handling of cash', 'Financial Management Training', 'Regular plantilla position', 'Treasury office staff', '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(10, 10, 'Collection Officer', 3, 'Full-time', '2019-12-01', NULL, 'Active', 1, 'Municipal Treasurer\'s Office', 35000.00, 2000.00, 0.00, 0.00, 'Hired as Collection Officer', 'Promoted from Clerk', 'Collects taxes and fees, manages accounts receivables.', 'Rated \"Very Good\" in collection efficiency', 'Revenue Collection Procedures Training', 'Regular plantilla position', 'Handles revenue collection', '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(11, 1, 'Administrative Aide', 13, 'Full-time', '2017-03-01', '2019-06-30', 'Resigned', NULL, 'City Hall - 2nd Floor', 25000.00, 1000.00, 0.00, 0.00, 'Started as Administrative Aide', 'Later promoted to Treasurer', 'Clerical and administrative support tasks.', 'Rated \"Good\"', NULL, 'Fixed-term appointment', 'Entry-level HR support', '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(12, 2, 'CAD Operator', 7, 'Full-time', '2015-08-01', '2018-06-14', 'Transferred', NULL, 'Engineering Building', 32000.00, 1500.00, 0.00, 0.00, 'Started as CAD Operator', 'Later promoted to Municipal Engineer', 'Drafting technical drawings.', 'Rated \"Good\"', 'AutoCAD Certification', 'Contract ended due to promotion', 'Junior engineering support', '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(13, 5, 'Administrative Aide', 13, 'Full-time', '2019-01-15', '2021-09-04', 'Transferred', NULL, 'City Hall - 2nd Floor', 25000.00, 1000.00, 0.00, 0.00, 'Started as Administrative Aide', 'Later promoted to Social Worker', 'Handled clerical support for social welfare programs.', 'Rated \"Good\"', NULL, 'Casual contract converted to plantilla', 'Support role before promotion', '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(14, 9, 'Clerk', 8, 'Full-time', '2018-05-01', '2020-09-09', 'Transferred', NULL, 'Municipal Civil Registrar\'s Office', 22000.00, 500.00, 0.00, 0.00, 'Started as Clerk', 'Later promoted to Cashier', 'Maintained registry documents, clerical tasks.', 'Rated \"Good\"', NULL, 'Contract ended due to transfer', 'Civil registrar support', '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(15, 10, 'Clerk', 8, 'Full-time', '2017-10-01', '2019-11-30', 'Transferred', NULL, 'Municipal Civil Registrar\'s Office', 20000.00, 500.00, 0.00, 0.00, 'Started as Clerk', 'Later promoted to Collection Officer', 'Clerical tasks, processing records.', 'Rated \"Satisfactory\"', NULL, 'Contract ended due to promotion', 'Civil registrar support role', '2025-09-09 02:00:16', '2025-09-09 02:00:16');

-- --------------------------------------------------------

--
-- Table structure for table `exits`
--

CREATE TABLE `exits` (
  `exit_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `exit_type` enum('Resignation','Termination','Retirement','End of Contract','Other') NOT NULL,
  `exit_reason` text DEFAULT NULL,
  `notice_date` date NOT NULL,
  `exit_date` date NOT NULL,
  `status` enum('Pending','Processing','Completed','Cancelled') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `exit_checklist`
--

CREATE TABLE `exit_checklist` (
  `checklist_id` int(11) NOT NULL,
  `exit_id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `responsible_department` varchar(50) NOT NULL,
  `status` enum('Pending','Completed','Not Applicable') DEFAULT 'Pending',
  `completed_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `exit_documents`
--

CREATE TABLE `exit_documents` (
  `document_id` int(11) NOT NULL,
  `exit_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `document_type` varchar(50) NOT NULL,
  `document_name` varchar(255) NOT NULL,
  `document_url` varchar(255) NOT NULL,
  `uploaded_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `exit_interviews`
--

CREATE TABLE `exit_interviews` (
  `interview_id` int(11) NOT NULL,
  `exit_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `interview_date` date NOT NULL,
  `feedback` text DEFAULT NULL,
  `improvement_suggestions` text DEFAULT NULL,
  `reason_for_leaving` text DEFAULT NULL,
  `would_recommend` tinyint(1) DEFAULT NULL,
  `status` enum('Scheduled','Completed','Cancelled') DEFAULT 'Scheduled',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `goals`
--

CREATE TABLE `goals` (
  `goal_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('Not Started','In Progress','Completed','Cancelled') DEFAULT 'Not Started',
  `progress` decimal(5,2) DEFAULT 0.00,
  `weight` decimal(5,2) DEFAULT 100.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `goal_updates`
--

CREATE TABLE `goal_updates` (
  `update_id` int(11) NOT NULL,
  `goal_id` int(11) NOT NULL,
  `update_date` date NOT NULL,
  `progress` decimal(5,2) NOT NULL,
  `comments` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `interviews`
--

CREATE TABLE `interviews` (
  `interview_id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `stage_id` int(11) NOT NULL,
  `schedule_date` datetime NOT NULL,
  `duration` int(11) NOT NULL COMMENT 'Duration in minutes',
  `location` varchar(255) DEFAULT NULL,
  `interview_type` enum('In-person','Phone','Video Call','Technical Assessment') NOT NULL,
  `status` enum('Scheduled','Completed','Rescheduled','Cancelled') DEFAULT 'Scheduled',
  `feedback` text DEFAULT NULL,
  `rating` decimal(3,2) DEFAULT NULL,
  `recommendation` enum('Strong Yes','Yes','Maybe','No','Strong No') DEFAULT NULL,
  `completed_date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `interview_stages`
--

CREATE TABLE `interview_stages` (
  `stage_id` int(11) NOT NULL,
  `job_opening_id` int(11) NOT NULL,
  `stage_name` varchar(100) NOT NULL,
  `stage_order` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `is_mandatory` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_applications`
--

CREATE TABLE `job_applications` (
  `application_id` int(11) NOT NULL,
  `job_opening_id` int(11) NOT NULL,
  `candidate_id` int(11) NOT NULL,
  `application_date` datetime NOT NULL,
  `status` enum('Applied','Screening','Interview','Assessment','Reference Check','Offer','Hired','Rejected','Withdrawn') DEFAULT 'Applied',
  `notes` text DEFAULT NULL,
  `assessment_scores` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`assessment_scores`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_offers`
--

CREATE TABLE `job_offers` (
  `offer_id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `job_opening_id` int(11) NOT NULL,
  `candidate_id` int(11) NOT NULL,
  `offered_salary` decimal(10,2) NOT NULL,
  `offered_benefits` text DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `expiration_date` date NOT NULL,
  `approval_status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `offer_status` enum('Draft','Sent','Accepted','Negotiating','Declined','Expired') DEFAULT 'Draft',
  `offer_letter_url` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_openings`
--

CREATE TABLE `job_openings` (
  `job_opening_id` int(11) NOT NULL,
  `job_role_id` int(11) NOT NULL,
  `department_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `requirements` text NOT NULL,
  `responsibilities` text NOT NULL,
  `location` varchar(100) DEFAULT NULL,
  `employment_type` enum('Full-time','Part-time','Contract','Temporary','Internship') NOT NULL,
  `experience_level` varchar(50) DEFAULT NULL,
  `education_requirements` text DEFAULT NULL,
  `salary_range_min` decimal(10,2) DEFAULT NULL,
  `salary_range_max` decimal(10,2) DEFAULT NULL,
  `vacancy_count` int(11) DEFAULT 1,
  `posting_date` date NOT NULL,
  `closing_date` date DEFAULT NULL,
  `status` enum('Draft','Open','On Hold','Closed','Cancelled') DEFAULT 'Draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_roles`
--

CREATE TABLE `job_roles` (
  `job_role_id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `department` varchar(50) NOT NULL,
  `min_salary` decimal(10,2) DEFAULT NULL,
  `max_salary` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `job_roles`
--

INSERT INTO `job_roles` (`job_role_id`, `title`, `description`, `department`, `min_salary`, `max_salary`, `created_at`, `updated_at`) VALUES
(1, 'Mayor', 'Chief executive of the municipality responsible for overall governance', 'Office of the Mayor', 80000.00, 120000.00, '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(2, 'Vice Mayor', 'Presiding officer of Sangguniang Bayan and assistant to the Mayor', 'Sangguniang Bayan', 70000.00, 100000.00, '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(3, 'Councilor', 'Member of the municipal legislative body', 'Sangguniang Bayan', 60000.00, 85000.00, '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(4, 'Municipal Treasurer', 'Head of treasury operations and revenue collection', 'Municipal Treasurer\'s Office', 55000.00, 75000.00, '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(5, 'Municipal Budget Officer', 'Responsible for municipal budget preparation and monitoring', 'Municipal Budget Office', 50000.00, 70000.00, '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(6, 'Municipal Accountant', 'Chief accountant responsible for municipal financial records', 'Municipal Accountant\'s Office', 50000.00, 70000.00, '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(7, 'Municipal Planning & Development Coordinator', 'Head of municipal planning and development programs', 'Municipal Planning & Development Office', 55000.00, 75000.00, '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(8, 'Municipal Engineer', 'Chief engineer overseeing infrastructure and public works', 'Municipal Engineer\'s Office', 60000.00, 85000.00, '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(9, 'Municipal Civil Registrar', 'Head of civil registration services', 'Municipal Civil Registrar\'s Office', 45000.00, 65000.00, '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(10, 'Municipal Health Officer', 'Chief medical officer and head of health services', 'Municipal Health Office', 70000.00, 95000.00, '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(11, 'Municipal Social Welfare Officer', 'Head of social welfare and development programs', 'Municipal Social Welfare & Development Office', 50000.00, 70000.00, '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(12, 'Municipal Agriculturist', 'Agricultural development officer and extension coordinator', 'Municipal Agriculture Office', 50000.00, 70000.00, '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(13, 'Municipal Assessor', 'Head of property assessment and real property taxation', 'Municipal Assessor\'s Office', 50000.00, 70000.00, '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(14, 'Municipal HR Officer', 'Head of human resources and personnel administration', 'Municipal Human Resource & Administrative Office', 50000.00, 70000.00, '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(15, 'MDRRM Officer', 'Disaster risk reduction and management coordinator', 'Municipal Disaster Risk Reduction & Management Off', 45000.00, 65000.00, '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(16, 'General Services Officer', 'Head of general services and facility management', 'General Services Office', 40000.00, 60000.00, '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(17, 'Nurse', 'Provides nursing services and healthcare support', 'Municipal Health Office', 35000.00, 50000.00, '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(18, 'Midwife', 'Provides maternal and child health services', 'Municipal Health Office', 30000.00, 45000.00, '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(19, 'Sanitary Inspector', 'Conducts health and sanitation inspections', 'Municipal Health Office', 28000.00, 40000.00, '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(20, 'Social Worker', 'Provides social services and community assistance', 'Municipal Social Welfare & Development Office', 35000.00, 50000.00, '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(21, 'Agricultural Technician', 'Provides technical support for agricultural programs', 'Municipal Agriculture Office', 28000.00, 40000.00, '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(22, 'Civil Engineer', 'Designs and supervises infrastructure projects', 'Municipal Engineer\'s Office', 45000.00, 65000.00, '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(23, 'CAD Operator', 'Creates technical drawings and engineering plans', 'Municipal Engineer\'s Office', 30000.00, 45000.00, '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(24, 'Building Inspector', 'Inspects construction projects for code compliance', 'Municipal Engineer\'s Office', 35000.00, 50000.00, '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(25, 'Budget Analyst', 'Analyzes budget data and prepares financial reports', 'Municipal Budget Office', 35000.00, 50000.00, '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(26, 'Accounting Staff', 'Handles bookkeeping and accounting transactions', 'Municipal Accountant\'s Office', 25000.00, 38000.00, '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(27, 'Planning Staff', 'Assists in municipal planning and development activities', 'Municipal Planning & Development Office', 30000.00, 45000.00, '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(28, 'Administrative Aide', 'Provides administrative support to various departments', 'Municipal Human Resource & Administrative Office', 22000.00, 35000.00, '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(29, 'Clerk', 'Handles clerical work and document processing', 'Municipal Civil Registrar\'s Office', 20000.00, 32000.00, '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(30, 'Cashier', 'Processes payments and financial transactions', 'Municipal Treasurer\'s Office', 22000.00, 35000.00, '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(31, 'Collection Officer', 'Collects municipal revenues and taxes', 'Municipal Treasurer\'s Office', 25000.00, 38000.00, '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(32, 'Property Custodian', 'Manages and maintains municipal property and assets', 'General Services Office', 22000.00, 35000.00, '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(33, 'Maintenance Worker', 'Performs maintenance and repair work on municipal facilities', 'General Services Office', 18000.00, 28000.00, '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(34, 'Utility Worker', 'Provides general utility and janitorial services', 'General Services Office', 16000.00, 25000.00, '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(35, 'Driver', 'Operates municipal vehicles and provides transportation services', 'General Services Office', 20000.00, 32000.00, '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(36, 'Security Personnel', 'Provides security services for municipal facilities', 'General Services Office', 18000.00, 28000.00, '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(37, 'Legislative Staff', 'Provides secretarial support to Sangguniang Bayan', 'Sangguniang Bayan', 25000.00, 38000.00, '2025-09-09 02:00:15', '2025-09-09 02:00:15');

-- --------------------------------------------------------

--
-- Table structure for table `knowledge_transfers`
--

CREATE TABLE `knowledge_transfers` (
  `transfer_id` int(11) NOT NULL,
  `exit_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `handover_details` text DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `completion_date` date DEFAULT NULL,
  `status` enum('Not Started','In Progress','Completed','N/A') DEFAULT 'Not Started',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `learning_resources`
--

CREATE TABLE `learning_resources` (
  `resource_id` int(11) NOT NULL,
  `resource_name` varchar(255) NOT NULL,
  `resource_type` enum('Book','Online Course','Video','Article','Webinar','Podcast','Other') NOT NULL,
  `description` text DEFAULT NULL,
  `resource_url` varchar(255) DEFAULT NULL,
  `author` varchar(100) DEFAULT NULL,
  `publication_date` date DEFAULT NULL,
  `duration` varchar(50) DEFAULT NULL,
  `tags` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `leave_balances`
--

CREATE TABLE `leave_balances` (
  `balance_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `year` year(4) NOT NULL,
  `total_leaves` decimal(5,2) NOT NULL,
  `leaves_taken` decimal(5,2) DEFAULT 0.00,
  `leaves_pending` decimal(5,2) DEFAULT 0.00,
  `leaves_remaining` decimal(5,2) DEFAULT NULL,
  `last_updated` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leave_balances`
--

INSERT INTO `leave_balances` (`balance_id`, `employee_id`, `leave_type_id`, `year`, `total_leaves`, `leaves_taken`, `leaves_pending`, `leaves_remaining`, `last_updated`, `created_at`, `updated_at`) VALUES
(17, 1, 1, '2024', 15.00, 3.00, 0.00, 12.00, NULL, '2025-09-14 07:13:53', '2025-09-14 07:13:53'),
(18, 2, 1, '2024', 15.00, 5.00, 1.00, 9.00, NULL, '2025-09-14 07:13:53', '2025-09-14 07:13:53'),
(19, 3, 1, '2024', 15.00, 2.00, 0.00, 13.00, NULL, '2025-09-14 07:13:53', '2025-09-14 07:13:53'),
(20, 4, 1, '2024', 15.00, 7.00, 0.00, 8.00, NULL, '2025-09-14 07:13:53', '2025-09-14 07:13:53'),
(21, 5, 1, '2024', 15.00, 4.00, 2.00, 9.00, NULL, '2025-09-14 07:13:53', '2025-09-14 07:13:53'),
(22, 1, 2, '2024', 10.00, 1.00, 0.00, 9.00, NULL, '2025-09-14 07:13:53', '2025-09-14 07:13:53'),
(23, 2, 2, '2024', 10.00, 3.00, 0.00, 7.00, NULL, '2025-09-14 07:13:53', '2025-09-14 07:13:53'),
(24, 3, 2, '2024', 10.00, 0.00, 0.00, 10.00, NULL, '2025-09-14 07:13:53', '2025-09-14 07:13:53'),
(25, 4, 2, '2024', 10.00, 2.00, 0.00, 8.00, NULL, '2025-09-14 07:13:53', '2025-09-14 07:13:53'),
(26, 5, 2, '2024', 10.00, 1.00, 0.00, 9.00, NULL, '2025-09-14 07:13:53', '2025-09-14 07:13:53'),
(27, 1, 3, '2024', 60.00, 0.00, 0.00, 60.00, NULL, '2025-09-14 07:13:53', '2025-09-14 07:13:53'),
(28, 2, 3, '2024', 60.00, 0.00, 0.00, 60.00, NULL, '2025-09-14 07:13:53', '2025-09-14 07:13:53'),
(29, 3, 3, '2024', 60.00, 0.00, 0.00, 60.00, NULL, '2025-09-14 07:13:53', '2025-09-14 07:13:53'),
(30, 1, 4, '2024', 7.00, 0.00, 0.00, 7.00, NULL, '2025-09-14 07:13:53', '2025-09-14 07:13:53'),
(31, 2, 4, '2024', 7.00, 0.00, 0.00, 7.00, NULL, '2025-09-14 07:13:53', '2025-09-14 07:13:53'),
(32, 4, 4, '2024', 7.00, 0.00, 0.00, 7.00, NULL, '2025-09-14 07:13:53', '2025-09-14 07:13:53');

-- --------------------------------------------------------

--
-- Table structure for table `leave_requests`
--

CREATE TABLE `leave_requests` (
  `leave_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `total_days` decimal(5,2) NOT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected','Cancelled') DEFAULT 'Pending',
  `applied_on` datetime DEFAULT current_timestamp(),
  `approved_on` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `leave_types`
--

CREATE TABLE `leave_types` (
  `leave_type_id` int(11) NOT NULL,
  `leave_type_name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `paid` tinyint(1) DEFAULT 1,
  `default_days` decimal(5,2) DEFAULT 0.00,
  `carry_forward` tinyint(1) DEFAULT 0,
  `max_carry_forward_days` decimal(5,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leave_types`
--

INSERT INTO `leave_types` (`leave_type_id`, `leave_type_name`, `description`, `paid`, `default_days`, `carry_forward`, `max_carry_forward_days`, `created_at`, `updated_at`) VALUES
(1, 'Vacation Leave', 'Annual vacation leave', 1, 15.00, 0, 0.00, '2025-09-14 07:13:35', '2025-09-14 07:13:35'),
(2, 'Sick Leave', 'Medical leave for illness', 1, 10.00, 0, 0.00, '2025-09-14 07:13:35', '2025-09-14 07:13:35'),
(3, 'Maternity Leave', 'Leave for new mothers', 1, 60.00, 0, 0.00, '2025-09-14 07:13:35', '2025-09-14 07:13:35'),
(4, 'Paternity Leave', 'Leave for new fathers', 1, 7.00, 0, 0.00, '2025-09-14 07:13:35', '2025-09-14 07:13:35'),
(5, 'Emergency Leave', 'Unplanned emergency leave', 0, 5.00, 0, 0.00, '2025-09-14 07:13:35', '2025-09-14 07:13:35');

-- --------------------------------------------------------

--
-- Table structure for table `onboarding_tasks`
--

CREATE TABLE `onboarding_tasks` (
  `task_id` int(11) NOT NULL,
  `task_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `task_type` enum('Administrative','Equipment','Training','Introduction','Documentation','Other') NOT NULL,
  `is_mandatory` tinyint(1) DEFAULT 1,
  `default_due_days` int(11) DEFAULT 7 COMMENT 'Days after joining date',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_disbursements`
--

CREATE TABLE `payment_disbursements` (
  `payment_disbursement_id` int(11) NOT NULL,
  `payroll_transaction_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `payment_method` enum('Bank Transfer','Check','Cash','Other') NOT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `account_number` varchar(50) DEFAULT NULL,
  `payment_amount` decimal(10,2) NOT NULL,
  `disbursement_date` datetime NOT NULL,
  `status` enum('Pending','Processed','Failed') DEFAULT 'Pending',
  `reference_number` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payroll_cycles`
--

CREATE TABLE `payroll_cycles` (
  `payroll_cycle_id` int(11) NOT NULL,
  `cycle_name` varchar(50) NOT NULL,
  `pay_period_start` date NOT NULL,
  `pay_period_end` date NOT NULL,
  `pay_date` date NOT NULL,
  `status` enum('Pending','Processing','Completed') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payroll_transactions`
--

CREATE TABLE `payroll_transactions` (
  `payroll_transaction_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `payroll_cycle_id` int(11) NOT NULL,
  `gross_pay` decimal(10,2) NOT NULL,
  `tax_deductions` decimal(10,2) DEFAULT 0.00,
  `statutory_deductions` decimal(10,2) DEFAULT 0.00,
  `other_deductions` decimal(10,2) DEFAULT 0.00,
  `net_pay` decimal(10,2) NOT NULL,
  `processed_date` datetime NOT NULL,
  `status` enum('Pending','Processed','Paid','Cancelled') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payslips`
--

CREATE TABLE `payslips` (
  `payslip_id` int(11) NOT NULL,
  `payroll_transaction_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `payslip_url` varchar(255) DEFAULT NULL,
  `generated_date` datetime NOT NULL,
  `status` enum('Generated','Sent','Viewed') DEFAULT 'Generated',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `performance_metrics`
--

CREATE TABLE `performance_metrics` (
  `metric_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `metric_name` varchar(100) NOT NULL,
  `metric_value` decimal(10,2) NOT NULL,
  `recorded_date` date NOT NULL,
  `comments` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `performance_reviews`
--

CREATE TABLE `performance_reviews` (
  `review_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `cycle_id` int(11) NOT NULL,
  `review_date` date NOT NULL,
  `overall_rating` decimal(3,2) NOT NULL,
  `strengths` text DEFAULT NULL,
  `areas_of_improvement` text DEFAULT NULL,
  `comments` text DEFAULT NULL,
  `status` enum('Draft','Submitted','Acknowledged','Finalized') DEFAULT 'Draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `performance_review_cycles`
--

CREATE TABLE `performance_review_cycles` (
  `cycle_id` int(11) NOT NULL,
  `cycle_name` varchar(100) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('Upcoming','In Progress','Completed') DEFAULT 'Upcoming',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `performance_review_cycles`
--

INSERT INTO `performance_review_cycles` (`cycle_id`, `cycle_name`, `start_date`, `end_date`, `status`, `created_at`, `updated_at`) VALUES
(3, 'Monthly Evaluation', '2025-10-01', '2025-10-31', '', '2025-10-21 12:47:53', '2025-10-21 12:47:53');

-- --------------------------------------------------------

--
-- Table structure for table `personal_information`
--

CREATE TABLE `personal_information` (
  `personal_info_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `date_of_birth` date NOT NULL,
  `gender` enum('Male','Female','Non-binary','Prefer not to say') NOT NULL,
  `marital_status` enum('Single','Married','Divorced','Widowed') NOT NULL,
  `nationality` varchar(50) NOT NULL,
  `tax_id` varchar(20) DEFAULT NULL,
  `social_security_number` varchar(20) DEFAULT NULL,
  `phone_number` varchar(20) NOT NULL,
  `emergency_contact_name` varchar(100) DEFAULT NULL,
  `emergency_contact_relationship` varchar(50) DEFAULT NULL,
  `emergency_contact_phone` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `personal_information`
--

INSERT INTO `personal_information` (`personal_info_id`, `first_name`, `last_name`, `date_of_birth`, `gender`, `marital_status`, `nationality`, `tax_id`, `social_security_number`, `phone_number`, `emergency_contact_name`, `emergency_contact_relationship`, `emergency_contact_phone`, `created_at`, `updated_at`) VALUES
(1, 'Maria', 'Santos', '1985-03-12', 'Female', 'Married', 'Filipino', '123-45-6789', '123456789', '0917-123-4567', 'Carlos Santos', 'Spouse', '0917-567-8901', '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(2, 'Roberto', 'Cruz', '1978-07-20', 'Male', 'Married', 'Filipino', '234-56-7890', '234567890', '0917-234-5678', 'Elena Cruz', 'Spouse', '0917-678-9012', '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(3, 'Jennifer', 'Reyes', '1988-11-08', 'Female', 'Single', 'Filipino', '345-67-8901', '345678901', '0917-345-6789', 'Mark Reyes', 'Brother', '0917-789-0123', '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(4, 'Antonio', 'Garcia', '1975-01-25', 'Male', 'Married', 'Filipino', '456-78-9012', '456789012', '0917-456-7890', 'Rosa Garcia', 'Spouse', '0917-890-1234', '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(5, 'Lisa', 'Mendoza', '1982-09-14', 'Female', 'Divorced', 'Filipino', '567-89-0123', '567890123', '0917-567-8901', 'John Mendoza', 'Father', '0917-901-2345', '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(6, 'Michael', 'Torres', '1980-06-03', 'Male', 'Married', 'Filipino', '678-90-1234', '678901234', '0917-678-9012', 'Anna Torres', 'Spouse', '0917-012-3456', '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(7, 'Carmen', 'Dela Cruz', '1987-12-18', 'Female', 'Single', 'Filipino', '789-01-2345', '789012345', '0917-789-0123', 'Pedro Dela Cruz', 'Father', '0917-123-4567', '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(8, 'Ricardo', 'Villanueva', '1970-04-07', 'Male', 'Married', 'Filipino', '890-12-3456', '890123456', '0917-890-1234', 'Diana Villanueva', 'Spouse', '0917-234-5678', '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(9, 'Sandra', 'Pascual', '1984-08-29', 'Female', 'Married', 'Filipino', '901-23-4567', '901234567', '0917-901-2345', 'Luis Pascual', 'Spouse', '0917-345-6789', '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(10, 'Jose', 'Ramos', '1972-05-15', 'Male', 'Married', 'Filipino', '012-34-5678', '012345678', '0917-012-3456', 'Teresa Ramos', 'Spouse', '0917-456-7890', '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(11, 'Ana', 'Morales', '1986-10-30', 'Female', 'Single', 'Filipino', '123-56-7890', '123567890', '0917-135-7890', 'Maria Morales', 'Mother', '0917-579-0123', '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(12, 'Pablo', 'Fernandez', '1979-02-22', 'Male', 'Married', 'Filipino', '234-67-8901', '234678901', '0917-246-7890', 'Carmen Fernandez', 'Spouse', '0917-680-1234', '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(13, 'Grace', 'Lopez', '1983-09-07', 'Female', 'Married', 'Filipino', '345-78-9012', '345789012', '0917-357-8901', 'David Lopez', 'Spouse', '0917-791-2345', '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(14, 'Eduardo', 'Hernandez', '1977-12-03', 'Male', 'Married', 'Filipino', '456-89-0123', '456890123', '0917-468-9012', 'Sofia Hernandez', 'Spouse', '0917-802-3456', '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(15, 'Rosario', 'Gonzales', '1989-06-28', 'Female', 'Single', 'Filipino', '567-90-1234', '567901234', '0917-579-0123', 'Miguel Gonzales', 'Father', '0917-913-4567', '2025-09-09 02:00:15', '2025-09-09 02:00:15');

-- --------------------------------------------------------

--
-- Table structure for table `post_exit_surveys`
--

CREATE TABLE `post_exit_surveys` (
  `survey_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `exit_id` int(11) NOT NULL,
  `survey_date` date NOT NULL,
  `survey_response` text DEFAULT NULL,
  `satisfaction_rating` int(11) DEFAULT NULL,
  `submitted_date` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `public_holidays`
--

CREATE TABLE `public_holidays` (
  `holiday_id` int(11) NOT NULL,
  `holiday_date` date NOT NULL,
  `holiday_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `public_holidays`
--

INSERT INTO `public_holidays` (`holiday_id`, `holiday_date`, `holiday_name`, `description`, `created_at`, `updated_at`) VALUES
(1, '2025-01-01', 'New Year\'s Day', 'Bagong Taon', '2025-09-09 02:00:56', '2025-09-09 02:00:56'),
(2, '2025-01-29', 'Chinese New Year', 'Chinese New Year', '2025-09-09 02:00:56', '2025-09-09 02:00:56'),
(3, '2025-04-01', 'Feast of Ramadhan', 'Eid???l Fitr', '2025-09-09 02:00:56', '2025-09-09 02:00:56'),
(4, '2025-04-09', 'Day of Valor', 'Araw ng Kagitingan', '2025-09-09 02:00:56', '2025-09-09 02:00:56'),
(5, '2025-04-17', 'Maundy Thursday', 'Huwebes Santo', '2025-09-09 02:00:57', '2025-09-09 02:00:57'),
(6, '2025-04-18', 'Good Friday', 'Biyernes Santo', '2025-09-09 02:00:57', '2025-09-09 02:00:57'),
(7, '2025-04-19', 'Holy Saturday', 'Sabado de Gloria', '2025-09-09 02:00:57', '2025-09-09 02:00:57'),
(8, '2025-05-01', 'Labor Day', 'Araw ng Paggawa', '2025-09-09 02:00:57', '2025-09-09 02:00:57'),
(9, '2025-05-12', 'Midterm Elections', 'Halalan 2025', '2025-09-09 02:00:57', '2025-09-09 02:00:57'),
(10, '2025-06-06', 'Feast of Sacrifice', 'Eid\'l Adha', '2025-09-09 02:00:57', '2025-09-09 02:00:57'),
(11, '2025-06-12', 'Independence Day', 'Araw ng Kalayaan', '2025-09-09 02:00:57', '2025-09-09 02:00:57'),
(12, '2025-08-21', 'Ninoy Aquino Day', 'Araw ng Kamatayan ni Senador Benigno Simeon \"Ninoy\" Aquino Jr.', '2025-09-09 02:00:57', '2025-09-09 02:00:57'),
(13, '2025-08-25', 'National Heroes Day', 'Araw ng mga Bayani', '2025-09-09 02:00:57', '2025-09-09 02:00:57'),
(14, '2025-10-31', 'All Saints\' Day Eve', 'All Saints\' Day Eve', '2025-09-09 02:00:57', '2025-09-09 02:00:57'),
(15, '2025-11-01', 'All Saints\' Day', 'Araw ng mga Santo', '2025-09-09 02:00:57', '2025-09-09 02:00:57'),
(16, '2025-11-30', 'Bonifacio Day', 'Araw ni Gat Andres Bonifacio', '2025-09-09 02:00:57', '2025-09-09 02:00:57'),
(17, '2025-12-08', 'Feast of the Immaculate Conception of Mary', 'Kapistahan ng Immaculada Concepcion', '2025-09-09 02:00:57', '2025-09-09 02:00:57'),
(18, '2025-12-24', 'Christmas Eve', 'Christmas Eve', '2025-09-09 02:00:57', '2025-09-09 02:00:57'),
(19, '2025-12-25', 'Christmas Day', 'Araw ng Pasko', '2025-09-09 02:00:57', '2025-09-09 02:00:57'),
(20, '2025-12-30', 'Rizal Day', 'Araw ng Kamatayan ni Dr. Jose Rizal', '2025-09-09 02:00:57', '2025-09-09 02:00:57'),
(21, '2025-12-31', 'Last Day of The Year', 'Huling Araw ng Taon', '2025-09-09 02:00:57', '2025-09-09 02:00:57');


--
-- Table structure for table `recruitment_analytics`
--

CREATE TABLE `recruitment_analytics` (
  `analytics_id` int(11) NOT NULL,
  `job_opening_id` int(11) NOT NULL,
  `total_applications` int(11) DEFAULT 0,
  `applications_per_day` decimal(5,2) DEFAULT 0.00,
  `average_processing_time` int(11) DEFAULT 0 COMMENT 'In days',
  `average_time_to_hire` int(11) DEFAULT 0 COMMENT 'In days',
  `offer_acceptance_rate` decimal(5,2) DEFAULT 0.00,
  `recruitment_source_breakdown` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`recruitment_source_breakdown`)),
  `cost_per_hire` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `salary_structures`
--

CREATE TABLE `salary_structures` (
  `salary_structure_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `basic_salary` decimal(10,2) NOT NULL,
  `allowances` decimal(10,2) DEFAULT 0.00,
  `deductions` decimal(10,2) DEFAULT 0.00,
  `effective_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settlements`
--

CREATE TABLE `settlements` (
  `settlement_id` int(11) NOT NULL,
  `exit_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `last_working_day` date NOT NULL,
  `final_salary` decimal(10,2) NOT NULL,
  `severance_pay` decimal(10,2) DEFAULT 0.00,
  `unused_leave_payout` decimal(10,2) DEFAULT 0.00,
  `deductions` decimal(10,2) DEFAULT 0.00,
  `final_settlement_amount` decimal(10,2) NOT NULL,
  `payment_date` date DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `status` enum('Pending','Processing','Completed') DEFAULT 'Pending',
  `processed_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `shifts`
--

CREATE TABLE `shifts` (
  `shift_id` int(11) NOT NULL,
  `shift_name` varchar(50) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `shifts`
--

INSERT INTO `shifts` (`shift_id`, `shift_name`, `start_time`, `end_time`, `description`, `created_at`, `updated_at`) VALUES
(1, 'Morning Shift', '08:00:00', '16:00:00', 'Standard morning shift from 8 AM to 4 PM', '2025-09-14 07:12:31', '2025-09-14 07:12:31'),
(2, 'Afternoon Shift', '14:00:00', '22:00:00', 'Afternoon/evening shift from 2 PM to 10 PM', '2025-09-14 07:12:31', '2025-09-14 07:12:31'),
(3, 'Night Shift', '22:00:00', '06:00:00', 'Night shift from 10 PM to 6 AM', '2025-09-14 07:12:31', '2025-09-14 07:12:31'),
(4, 'Flexible Shift', '09:00:00', '17:00:00', 'Flexible working hours', '2025-09-14 07:12:31', '2025-09-14 07:12:31'),
(5, 'Morning Shift', '08:00:00', '16:00:00', 'Standard morning shift from 8 AM to 4 PM', '2025-09-14 07:13:53', '2025-09-14 07:13:53'),
(6, 'Afternoon Shift', '14:00:00', '22:00:00', 'Afternoon/evening shift from 2 PM to 10 PM', '2025-09-14 07:13:53', '2025-09-14 07:13:53'),
(7, 'Night Shift', '22:00:00', '06:00:00', 'Night shift from 10 PM to 6 AM', '2025-09-14 07:13:53', '2025-09-14 07:13:53'),
(8, 'Flexible Shift', '09:00:00', '17:00:00', 'Flexible working hours', '2025-09-14 07:13:53', '2025-09-14 07:13:53');

-- --------------------------------------------------------

--
-- Table structure for table `skill_matrix`
--

CREATE TABLE `skill_matrix` (
  `skill_id` int(11) NOT NULL,
  `skill_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `statutory_deductions`
--

CREATE TABLE `statutory_deductions` (
  `statutory_deduction_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `deduction_type` varchar(50) NOT NULL,
  `deduction_amount` decimal(10,2) NOT NULL,
  `effective_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tax_deductions`
--

CREATE TABLE `tax_deductions` (
  `tax_deduction_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `tax_type` varchar(50) NOT NULL,
  `tax_percentage` decimal(5,2) DEFAULT NULL,
  `tax_amount` decimal(10,2) DEFAULT NULL,
  `effective_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `trainers`
--

CREATE TABLE `trainers` (
  `trainer_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `specialization` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `is_internal` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `training_courses`
--

CREATE TABLE `training_courses` (
  `course_id` int(11) NOT NULL,
  `course_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `delivery_method` enum('Online','Classroom','Workshop','Self-paced','Hybrid') NOT NULL,
  `duration` int(11) DEFAULT NULL COMMENT 'Duration in hours',
  `max_participants` int(11) DEFAULT NULL,
  `prerequisites` text DEFAULT NULL,
  `materials_url` varchar(255) DEFAULT NULL,
  `status` enum('Active','Inactive','In Development') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `training_enrollments`
--

CREATE TABLE `training_enrollments` (
  `enrollment_id` int(11) NOT NULL,
  `session_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `enrollment_date` datetime DEFAULT current_timestamp(),
  `status` enum('Enrolled','Completed','Dropped','Failed','Waitlisted') DEFAULT 'Enrolled',
  `completion_date` date DEFAULT NULL,
  `score` decimal(5,2) DEFAULT NULL,
  `feedback` text DEFAULT NULL,
  `certificate_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `training_feedback`
--

CREATE TABLE `training_feedback` (
  `feedback_id` int(11) NOT NULL,
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
  `feedback_date` date NOT NULL DEFAULT curdate(),
  `is_anonymous` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `training_needs_assessment`
--

CREATE TABLE `training_needs_assessment` (
  `assessment_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `assessment_date` date NOT NULL,
  `skills_gap` text DEFAULT NULL,
  `recommended_trainings` text DEFAULT NULL,
  `priority` enum('Low','Medium','High') DEFAULT 'Medium',
  `status` enum('Identified','In Progress','Completed') DEFAULT 'Identified',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `training_sessions`
--

CREATE TABLE `training_sessions` (
  `session_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `trainer_id` int(11) NOT NULL,
  `session_name` varchar(255) NOT NULL,
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `capacity` int(11) NOT NULL,
  `cost_per_participant` decimal(10,2) DEFAULT NULL,
  `status` enum('Scheduled','In Progress','Completed','Cancelled') DEFAULT 'Scheduled',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` enum('admin','hr','employee') NOT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password`, `email`, `role`, `employee_id`, `is_active`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin123', 'admin@municipality.gov.ph', 'admin', NULL, 1, NULL, '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(2, 'hr_manager', 'hr123', 'hr@municipality.gov.ph', 'hr', NULL, 1, NULL, '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(3, 'maria.santos', 'emp123', 'maria.santos@municipality.gov.ph', 'employee', 1, 1, NULL, '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(4, 'roberto.cruz', 'emp123', 'roberto.cruz@municipality.gov.ph', 'employee', 2, 1, NULL, '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(5, 'jennifer.reyes', 'emp123', 'jennifer.reyes@municipality.gov.ph', 'employee', 3, 1, NULL, '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(6, 'antonio.garcia', 'emp123', 'antonio.garcia@municipality.gov.ph', 'employee', 4, 1, NULL, '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(7, 'lisa.mendoza', 'emp123', 'lisa.mendoza@municipality.gov.ph', 'employee', 5, 1, NULL, '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(8, 'michael.torres', 'emp123', 'michael.torres@municipality.gov.ph', 'employee', 6, 1, NULL, '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(9, 'carmen.delacruz', 'emp123', 'carmen.delacruz@municipality.gov.ph', 'employee', 7, 1, NULL, '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(10, 'ricardo.villanueva', 'emp123', 'ricardo.villanueva@municipality.gov.ph', 'employee', 8, 1, NULL, '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(11, 'sandra.pascual', 'emp123', 'sandra.pascual@municipality.gov.ph', 'employee', 9, 1, NULL, '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(12, 'jose.ramos', 'emp123', 'jose.ramos@municipality.gov.ph', 'employee', 10, 1, NULL, '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(13, 'ana.morales', 'emp123', 'ana.morales@municipality.gov.ph', 'employee', 11, 1, NULL, '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(14, 'pablo.fernandez', 'emp123', 'pablo.fernandez@municipality.gov.ph', 'employee', 12, 1, NULL, '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(15, 'grace.lopez', 'emp123', 'grace.lopez@municipality.gov.ph', 'employee', 13, 1, NULL, '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(16, 'eduardo.hernandez', 'emp123', 'eduardo.hernandez@municipality.gov.ph', 'employee', 14, 1, NULL, '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(17, 'rosario.gonzales', 'emp123', 'rosario.gonzales@municipality.gov.ph', 'employee', 15, 1, NULL, '2025-09-09 02:00:16', '2025-09-09 02:00:16');

-- --------------------------------------------------------

--
-- Table structure for table `user_roles`
--

CREATE TABLE `user_roles` (
  `role_id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_roles`
--

INSERT INTO `user_roles` (`role_id`, `role_name`, `description`) VALUES
(1, 'admin', 'Administrator role with full system access.'),
(2, 'hr', 'Human Resources role with access to employee and payroll management.'),
(3, 'employee', 'Standard employee role with limited access to personal information and timesheets.');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`attendance_id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `attendance_summary`
--
ALTER TABLE `attendance_summary`
  ADD PRIMARY KEY (`summary_id`),
  ADD UNIQUE KEY `employee_id` (`employee_id`,`month`,`year`);

--
-- Indexes for table `benefits_plans`
--
ALTER TABLE `benefits_plans`
  ADD PRIMARY KEY (`benefit_plan_id`);

--
-- Indexes for table `bonus_payments`
--
ALTER TABLE `bonus_payments`
  ADD PRIMARY KEY (`bonus_payment_id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `payroll_cycle_id` (`payroll_cycle_id`);

--
-- Indexes for table `candidates`
--
ALTER TABLE `candidates`
  ADD PRIMARY KEY (`candidate_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `career_paths`
--
ALTER TABLE `career_paths`
  ADD PRIMARY KEY (`path_id`),
  ADD KEY `department_id` (`department_id`);

--
-- Indexes for table `career_path_stages`
--
ALTER TABLE `career_path_stages`
  ADD PRIMARY KEY (`stage_id`),
  ADD KEY `path_id` (`path_id`),
  ADD KEY `job_role_id` (`job_role_id`);

--
-- Indexes for table `certifications`
--
ALTER TABLE `certifications`
  ADD PRIMARY KEY (`certification_id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `skill_id` (`skill_id`);

--
-- Indexes for table `compensation_packages`
--
ALTER TABLE `compensation_packages`
  ADD PRIMARY KEY (`compensation_package_id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `competencies`
--
ALTER TABLE `competencies`
  ADD PRIMARY KEY (`competency_id`),
  ADD KEY `job_role_id_fk` (`job_role_id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`department_id`);

--
-- Indexes for table `development_activities`
--
ALTER TABLE `development_activities`
  ADD PRIMARY KEY (`activity_id`),
  ADD KEY `plan_id` (`plan_id`);

--
-- Indexes for table `development_plans`
--
ALTER TABLE `development_plans`
  ADD PRIMARY KEY (`plan_id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `document_management`
--
ALTER TABLE `document_management`
  ADD PRIMARY KEY (`document_id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `employee_assignments`
--
ALTER TABLE `employee_assignments`
  ADD PRIMARY KEY (`assignment_id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `session_id` (`session_id`),
  ADD KEY `course_id` (`course_id`),
  ADD KEY `assigned_by_employee_id` (`assigned_by_employee_id`),
  ADD KEY `department_id` (`department_id`);

--
-- Indexes for table `employee_benefits`
--
ALTER TABLE `employee_benefits`
  ADD PRIMARY KEY (`benefit_id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `benefit_plan_id` (`benefit_plan_id`);

--
-- Indexes for table `employee_career_paths`
--
ALTER TABLE `employee_career_paths`
  ADD PRIMARY KEY (`employee_path_id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `path_id` (`path_id`),
  ADD KEY `current_stage_id` (`current_stage_id`);

--
-- Indexes for table `employee_competencies`
--
ALTER TABLE `employee_competencies`
  ADD PRIMARY KEY (`employee_id`,`competency_id`,`assessment_date`),
  ADD KEY `competency_id` (`competency_id`),
  ADD KEY `cycle_id_fk` (`cycle_id`);

--
-- Indexes for table `employee_onboarding`
--
ALTER TABLE `employee_onboarding`
  ADD PRIMARY KEY (`onboarding_id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `employee_onboarding_tasks`
--
ALTER TABLE `employee_onboarding_tasks`
  ADD PRIMARY KEY (`employee_task_id`),
  ADD KEY `onboarding_id` (`onboarding_id`),
  ADD KEY `task_id` (`task_id`);

--
-- Indexes for table `employee_profiles`
--
ALTER TABLE `employee_profiles`
  ADD PRIMARY KEY (`employee_id`),
  ADD UNIQUE KEY `employee_number` (`employee_number`),
  ADD UNIQUE KEY `personal_info_id` (`personal_info_id`),
  ADD UNIQUE KEY `work_email` (`work_email`),
  ADD KEY `job_role_id` (`job_role_id`);

--
-- Indexes for table `employee_resources`
--
ALTER TABLE `employee_resources`
  ADD PRIMARY KEY (`employee_resource_id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `resource_id` (`resource_id`);

--
-- Indexes for table `employee_shifts`
--
ALTER TABLE `employee_shifts`
  ADD PRIMARY KEY (`employee_shift_id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `shift_id` (`shift_id`);

--
-- Indexes for table `employee_skills`
--
ALTER TABLE `employee_skills`
  ADD PRIMARY KEY (`employee_skill_id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `skill_id` (`skill_id`);

--
-- Indexes for table `employment_history`
--
ALTER TABLE `employment_history`
  ADD PRIMARY KEY (`history_id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `department_id` (`department_id`),
  ADD KEY `reporting_manager_id` (`reporting_manager_id`);

--
-- Indexes for table `exits`
--
ALTER TABLE `exits`
  ADD PRIMARY KEY (`exit_id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `exit_checklist`
--
ALTER TABLE `exit_checklist`
  ADD PRIMARY KEY (`checklist_id`),
  ADD KEY `exit_id` (`exit_id`);

--
-- Indexes for table `exit_documents`
--
ALTER TABLE `exit_documents`
  ADD PRIMARY KEY (`document_id`),
  ADD KEY `exit_id` (`exit_id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `exit_interviews`
--
ALTER TABLE `exit_interviews`
  ADD PRIMARY KEY (`interview_id`),
  ADD KEY `exit_id` (`exit_id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `goals`
--
ALTER TABLE `goals`
  ADD PRIMARY KEY (`goal_id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `goal_updates`
--
ALTER TABLE `goal_updates`
  ADD PRIMARY KEY (`update_id`),
  ADD KEY `goal_id` (`goal_id`);

--
-- Indexes for table `interviews`
--
ALTER TABLE `interviews`
  ADD PRIMARY KEY (`interview_id`),
  ADD KEY `application_id` (`application_id`),
  ADD KEY `stage_id` (`stage_id`);

--
-- Indexes for table `interview_stages`
--
ALTER TABLE `interview_stages`
  ADD PRIMARY KEY (`stage_id`),
  ADD KEY `job_opening_id` (`job_opening_id`);

--
-- Indexes for table `job_applications`
--
ALTER TABLE `job_applications`
  ADD PRIMARY KEY (`application_id`),
  ADD KEY `job_opening_id` (`job_opening_id`),
  ADD KEY `candidate_id` (`candidate_id`);

--
-- Indexes for table `job_offers`
--
ALTER TABLE `job_offers`
  ADD PRIMARY KEY (`offer_id`),
  ADD KEY `application_id` (`application_id`),
  ADD KEY `job_opening_id` (`job_opening_id`),
  ADD KEY `candidate_id` (`candidate_id`);

--
-- Indexes for table `job_openings`
--
ALTER TABLE `job_openings`
  ADD PRIMARY KEY (`job_opening_id`),
  ADD KEY `job_role_id` (`job_role_id`),
  ADD KEY `department_id` (`department_id`);

--
-- Indexes for table `job_roles`
--
ALTER TABLE `job_roles`
  ADD PRIMARY KEY (`job_role_id`);

--
-- Indexes for table `knowledge_transfers`
--
ALTER TABLE `knowledge_transfers`
  ADD PRIMARY KEY (`transfer_id`),
  ADD KEY `exit_id` (`exit_id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `learning_resources`
--
ALTER TABLE `learning_resources`
  ADD PRIMARY KEY (`resource_id`);

--
-- Indexes for table `leave_balances`
--
ALTER TABLE `leave_balances`
  ADD PRIMARY KEY (`balance_id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `leave_type_id` (`leave_type_id`);

--
-- Indexes for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD PRIMARY KEY (`leave_id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `leave_type_id` (`leave_type_id`);

--
-- Indexes for table `leave_types`
--
ALTER TABLE `leave_types`
  ADD PRIMARY KEY (`leave_type_id`);

--
-- Indexes for table `onboarding_tasks`
--
ALTER TABLE `onboarding_tasks`
  ADD PRIMARY KEY (`task_id`),
  ADD KEY `department_id` (`department_id`);

--
-- Indexes for table `payment_disbursements`
--
ALTER TABLE `payment_disbursements`
  ADD PRIMARY KEY (`payment_disbursement_id`),
  ADD KEY `payroll_transaction_id` (`payroll_transaction_id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `payroll_cycles`
--
ALTER TABLE `payroll_cycles`
  ADD PRIMARY KEY (`payroll_cycle_id`);

--
-- Indexes for table `payroll_transactions`
--
ALTER TABLE `payroll_transactions`
  ADD PRIMARY KEY (`payroll_transaction_id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `payroll_cycle_id` (`payroll_cycle_id`);

--
-- Indexes for table `payslips`
--
ALTER TABLE `payslips`
  ADD PRIMARY KEY (`payslip_id`),
  ADD KEY `payroll_transaction_id` (`payroll_transaction_id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `performance_metrics`
--
ALTER TABLE `performance_metrics`
  ADD PRIMARY KEY (`metric_id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `performance_reviews`
--
ALTER TABLE `performance_reviews`
  ADD PRIMARY KEY (`review_id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `cycle_id` (`cycle_id`);

--
-- Indexes for table `performance_review_cycles`
--
ALTER TABLE `performance_review_cycles`
  ADD PRIMARY KEY (`cycle_id`);

--
-- Indexes for table `personal_information`
--
ALTER TABLE `personal_information`
  ADD PRIMARY KEY (`personal_info_id`);

--
-- Indexes for table `post_exit_surveys`
--
ALTER TABLE `post_exit_surveys`
  ADD PRIMARY KEY (`survey_id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `exit_id` (`exit_id`);

--
-- Indexes for table `public_holidays`
--
ALTER TABLE `public_holidays`
  ADD PRIMARY KEY (`holiday_id`),
  ADD UNIQUE KEY `holiday_date` (`holiday_date`);

--
-- Indexes for table `recruitment_analytics`
--
ALTER TABLE `recruitment_analytics`
  ADD PRIMARY KEY (`analytics_id`),
  ADD KEY `job_opening_id` (`job_opening_id`);

--
-- Indexes for table `salary_structures`
--
ALTER TABLE `salary_structures`
  ADD PRIMARY KEY (`salary_structure_id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `settlements`
--
ALTER TABLE `settlements`
  ADD PRIMARY KEY (`settlement_id`),
  ADD KEY `exit_id` (`exit_id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `shifts`
--
ALTER TABLE `shifts`
  ADD PRIMARY KEY (`shift_id`);

--
-- Indexes for table `skill_matrix`
--
ALTER TABLE `skill_matrix`
  ADD PRIMARY KEY (`skill_id`);

--
-- Indexes for table `statutory_deductions`
--
ALTER TABLE `statutory_deductions`
  ADD PRIMARY KEY (`statutory_deduction_id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `tax_deductions`
--
ALTER TABLE `tax_deductions`
  ADD PRIMARY KEY (`tax_deduction_id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `trainers`
--
ALTER TABLE `trainers`
  ADD PRIMARY KEY (`trainer_id`);

--
-- Indexes for table `training_courses`
--
ALTER TABLE `training_courses`
  ADD PRIMARY KEY (`course_id`);

--
-- Indexes for table `training_enrollments`
--
ALTER TABLE `training_enrollments`
  ADD PRIMARY KEY (`enrollment_id`),
  ADD KEY `session_id` (`session_id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `training_feedback`
--
ALTER TABLE `training_feedback`
  ADD PRIMARY KEY (`feedback_id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `session_id` (`session_id`),
  ADD KEY `resource_id` (`resource_id`),
  ADD KEY `trainer_id` (`trainer_id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `training_needs_assessment`
--
ALTER TABLE `training_needs_assessment`
  ADD PRIMARY KEY (`assessment_id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `training_sessions`
--
ALTER TABLE `training_sessions`
  ADD PRIMARY KEY (`session_id`),
  ADD KEY `course_id` (`course_id`),
  ADD KEY `trainer_id` (`trainer_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `user_roles`
--
ALTER TABLE `user_roles`
  ADD PRIMARY KEY (`role_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `attendance_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendance_summary`
--
ALTER TABLE `attendance_summary`
  MODIFY `summary_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `benefits_plans`
--
ALTER TABLE `benefits_plans`
  MODIFY `benefit_plan_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bonus_payments`
--
ALTER TABLE `bonus_payments`
  MODIFY `bonus_payment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `candidates`
--
ALTER TABLE `candidates`
  MODIFY `candidate_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `career_paths`
--
ALTER TABLE `career_paths`
  MODIFY `path_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `career_path_stages`
--
ALTER TABLE `career_path_stages`
  MODIFY `stage_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `certifications`
--
ALTER TABLE `certifications`
  MODIFY `certification_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `compensation_packages`
--
ALTER TABLE `compensation_packages`
  MODIFY `compensation_package_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `competencies`
--
ALTER TABLE `competencies`
  MODIFY `competency_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=112;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `department_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `development_activities`
--
ALTER TABLE `development_activities`
  MODIFY `activity_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `development_plans`
--
ALTER TABLE `development_plans`
  MODIFY `plan_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `document_management`
--
ALTER TABLE `document_management`
  MODIFY `document_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `employee_assignments`
--
ALTER TABLE `employee_assignments`
  MODIFY `assignment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employee_benefits`
--
ALTER TABLE `employee_benefits`
  MODIFY `benefit_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employee_career_paths`
--
ALTER TABLE `employee_career_paths`
  MODIFY `employee_path_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employee_onboarding`
--
ALTER TABLE `employee_onboarding`
  MODIFY `onboarding_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employee_onboarding_tasks`
--
ALTER TABLE `employee_onboarding_tasks`
  MODIFY `employee_task_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employee_profiles`
--
ALTER TABLE `employee_profiles`
  MODIFY `employee_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `employee_resources`
--
ALTER TABLE `employee_resources`
  MODIFY `employee_resource_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employee_shifts`
--
ALTER TABLE `employee_shifts`
  MODIFY `employee_shift_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `employee_skills`
--
ALTER TABLE `employee_skills`
  MODIFY `employee_skill_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employment_history`
--
ALTER TABLE `employment_history`
  MODIFY `history_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `exits`
--
ALTER TABLE `exits`
  MODIFY `exit_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `exit_checklist`
--
ALTER TABLE `exit_checklist`
  MODIFY `checklist_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `exit_documents`
--
ALTER TABLE `exit_documents`
  MODIFY `document_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `exit_interviews`
--
ALTER TABLE `exit_interviews`
  MODIFY `interview_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `goals`
--
ALTER TABLE `goals`
  MODIFY `goal_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `goal_updates`
--
ALTER TABLE `goal_updates`
  MODIFY `update_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `interviews`
--
ALTER TABLE `interviews`
  MODIFY `interview_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `interview_stages`
--
ALTER TABLE `interview_stages`
  MODIFY `stage_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `job_applications`
--
ALTER TABLE `job_applications`
  MODIFY `application_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `job_offers`
--
ALTER TABLE `job_offers`
  MODIFY `offer_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `job_openings`
--
ALTER TABLE `job_openings`
  MODIFY `job_opening_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `job_roles`
--
ALTER TABLE `job_roles`
  MODIFY `job_role_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `knowledge_transfers`
--
ALTER TABLE `knowledge_transfers`
  MODIFY `transfer_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `learning_resources`
--
ALTER TABLE `learning_resources`
  MODIFY `resource_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `leave_balances`
--
ALTER TABLE `leave_balances`
  MODIFY `balance_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `leave_requests`
--
ALTER TABLE `leave_requests`
  MODIFY `leave_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `leave_types`
--
ALTER TABLE `leave_types`
  MODIFY `leave_type_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `onboarding_tasks`
--
ALTER TABLE `onboarding_tasks`
  MODIFY `task_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_disbursements`
--
ALTER TABLE `payment_disbursements`
  MODIFY `payment_disbursement_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payroll_cycles`
--
ALTER TABLE `payroll_cycles`
  MODIFY `payroll_cycle_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payroll_transactions`
--
ALTER TABLE `payroll_transactions`
  MODIFY `payroll_transaction_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payslips`
--
ALTER TABLE `payslips`
  MODIFY `payslip_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `performance_metrics`
--
ALTER TABLE `performance_metrics`
  MODIFY `metric_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `performance_reviews`
--
ALTER TABLE `performance_reviews`
  MODIFY `review_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `performance_review_cycles`
--
ALTER TABLE `performance_review_cycles`
  MODIFY `cycle_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `personal_information`
--
ALTER TABLE `personal_information`
  MODIFY `personal_info_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `post_exit_surveys`
--
ALTER TABLE `post_exit_surveys`
  MODIFY `survey_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `public_holidays`
--
ALTER TABLE `public_holidays`
  MODIFY `holiday_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `recruitment_analytics`
--
ALTER TABLE `recruitment_analytics`
  MODIFY `analytics_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `salary_structures`
--
ALTER TABLE `salary_structures`
  MODIFY `salary_structure_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `settlements`
--
ALTER TABLE `settlements`
  MODIFY `settlement_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `shifts`
--
ALTER TABLE `shifts`
  MODIFY `shift_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `skill_matrix`
--
ALTER TABLE `skill_matrix`
  MODIFY `skill_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `statutory_deductions`
--
ALTER TABLE `statutory_deductions`
  MODIFY `statutory_deduction_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tax_deductions`
--
ALTER TABLE `tax_deductions`
  MODIFY `tax_deduction_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `trainers`
--
ALTER TABLE `trainers`
  MODIFY `trainer_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `training_courses`
--
ALTER TABLE `training_courses`
  MODIFY `course_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `training_enrollments`
--
ALTER TABLE `training_enrollments`
  MODIFY `enrollment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `training_feedback`
--
ALTER TABLE `training_feedback`
  MODIFY `feedback_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `training_needs_assessment`
--
ALTER TABLE `training_needs_assessment`
  MODIFY `assessment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `training_sessions`
--
ALTER TABLE `training_sessions`
  MODIFY `session_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `user_roles`
--
ALTER TABLE `user_roles`
  MODIFY `role_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employee_profiles` (`employee_id`) ON DELETE CASCADE;

--
-- Constraints for table `attendance_summary`
--
ALTER TABLE `attendance_summary`
  ADD CONSTRAINT `attendance_summary_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employee_profiles` (`employee_id`) ON DELETE CASCADE;

--
-- Constraints for table `bonus_payments`
--
ALTER TABLE `bonus_payments`
  ADD CONSTRAINT `bonus_payments_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employee_profiles` (`employee_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bonus_payments_ibfk_2` FOREIGN KEY (`payroll_cycle_id`) REFERENCES `payroll_cycles` (`payroll_cycle_id`) ON DELETE SET NULL;

--
-- Constraints for table `career_paths`
--
ALTER TABLE `career_paths`
  ADD CONSTRAINT `career_paths_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`) ON DELETE SET NULL;

--
-- Constraints for table `career_path_stages`
--
ALTER TABLE `career_path_stages`
  ADD CONSTRAINT `career_path_stages_ibfk_1` FOREIGN KEY (`path_id`) REFERENCES `career_paths` (`path_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `career_path_stages_ibfk_2` FOREIGN KEY (`job_role_id`) REFERENCES `job_roles` (`job_role_id`) ON DELETE CASCADE;

--
-- Constraints for table `certifications`
--
ALTER TABLE `certifications`
  ADD CONSTRAINT `certifications_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employee_profiles` (`employee_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `certifications_ibfk_2` FOREIGN KEY (`skill_id`) REFERENCES `skill_matrix` (`skill_id`) ON DELETE SET NULL;

--
-- Constraints for table `compensation_packages`
--
ALTER TABLE `compensation_packages`
  ADD CONSTRAINT `compensation_packages_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employee_profiles` (`employee_id`) ON DELETE CASCADE;

--
-- Constraints for table `competencies`
--
ALTER TABLE `competencies`
  ADD CONSTRAINT `job_role_id_fk` FOREIGN KEY (`job_role_id`) REFERENCES `job_roles` (`job_role_id`) ON DELETE CASCADE;

--
-- Constraints for table `development_activities`
--
ALTER TABLE `development_activities`
  ADD CONSTRAINT `development_activities_ibfk_1` FOREIGN KEY (`plan_id`) REFERENCES `development_plans` (`plan_id`) ON DELETE CASCADE;

--
-- Constraints for table `development_plans`
--
ALTER TABLE `development_plans`
  ADD CONSTRAINT `development_plans_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employee_profiles` (`employee_id`) ON DELETE CASCADE;

--
-- Constraints for table `document_management`
--
ALTER TABLE `document_management`
  ADD CONSTRAINT `document_management_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employee_profiles` (`employee_id`) ON DELETE CASCADE;

--
-- Constraints for table `employee_assignments`
--
ALTER TABLE `employee_assignments`
  ADD CONSTRAINT `employee_assignments_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employee_profiles` (`employee_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `employee_assignments_ibfk_2` FOREIGN KEY (`session_id`) REFERENCES `training_sessions` (`session_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `employee_assignments_ibfk_3` FOREIGN KEY (`course_id`) REFERENCES `training_courses` (`course_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `employee_assignments_ibfk_4` FOREIGN KEY (`assigned_by_employee_id`) REFERENCES `employee_profiles` (`employee_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `employee_assignments_ibfk_5` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`) ON DELETE SET NULL;

--
-- Constraints for table `employee_benefits`
--
ALTER TABLE `employee_benefits`
  ADD CONSTRAINT `employee_benefits_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employee_profiles` (`employee_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `employee_benefits_ibfk_2` FOREIGN KEY (`benefit_plan_id`) REFERENCES `benefits_plans` (`benefit_plan_id`) ON DELETE CASCADE;

--
-- Constraints for table `employee_career_paths`
--
ALTER TABLE `employee_career_paths`
  ADD CONSTRAINT `employee_career_paths_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employee_profiles` (`employee_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `employee_career_paths_ibfk_2` FOREIGN KEY (`path_id`) REFERENCES `career_paths` (`path_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `employee_career_paths_ibfk_3` FOREIGN KEY (`current_stage_id`) REFERENCES `career_path_stages` (`stage_id`) ON DELETE CASCADE;

--
-- Constraints for table `employee_competencies`
--
ALTER TABLE `employee_competencies`
  ADD CONSTRAINT `cycle_id_fk` FOREIGN KEY (`cycle_id`) REFERENCES `performance_review_cycles` (`cycle_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `employee_competencies_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employee_profiles` (`employee_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `employee_competencies_ibfk_2` FOREIGN KEY (`competency_id`) REFERENCES `competencies` (`competency_id`) ON DELETE CASCADE;

--
-- Constraints for table `employee_onboarding`
--
ALTER TABLE `employee_onboarding`
  ADD CONSTRAINT `employee_onboarding_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employee_profiles` (`employee_id`) ON DELETE CASCADE;

--
-- Constraints for table `employee_onboarding_tasks`
--
ALTER TABLE `employee_onboarding_tasks`
  ADD CONSTRAINT `employee_onboarding_tasks_ibfk_1` FOREIGN KEY (`onboarding_id`) REFERENCES `employee_onboarding` (`onboarding_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `employee_onboarding_tasks_ibfk_2` FOREIGN KEY (`task_id`) REFERENCES `onboarding_tasks` (`task_id`) ON DELETE CASCADE;

--
-- Constraints for table `employee_profiles`
--
ALTER TABLE `employee_profiles`
  ADD CONSTRAINT `employee_profiles_ibfk_1` FOREIGN KEY (`personal_info_id`) REFERENCES `personal_information` (`personal_info_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `employee_profiles_ibfk_2` FOREIGN KEY (`job_role_id`) REFERENCES `job_roles` (`job_role_id`) ON DELETE SET NULL;

--
-- Constraints for table `employee_resources`
--
ALTER TABLE `employee_resources`
  ADD CONSTRAINT `employee_resources_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employee_profiles` (`employee_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `employee_resources_ibfk_2` FOREIGN KEY (`resource_id`) REFERENCES `learning_resources` (`resource_id`) ON DELETE CASCADE;

--
-- Constraints for table `employee_shifts`
--
ALTER TABLE `employee_shifts`
  ADD CONSTRAINT `employee_shifts_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employee_profiles` (`employee_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `employee_shifts_ibfk_2` FOREIGN KEY (`shift_id`) REFERENCES `shifts` (`shift_id`) ON DELETE CASCADE;

--
-- Constraints for table `employee_skills`
--
ALTER TABLE `employee_skills`
  ADD CONSTRAINT `employee_skills_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employee_profiles` (`employee_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `employee_skills_ibfk_2` FOREIGN KEY (`skill_id`) REFERENCES `skill_matrix` (`skill_id`) ON DELETE CASCADE;

--
-- Constraints for table `employment_history`
--
ALTER TABLE `employment_history`
  ADD CONSTRAINT `employment_history_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employee_profiles` (`employee_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `employment_history_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `employment_history_ibfk_3` FOREIGN KEY (`reporting_manager_id`) REFERENCES `employee_profiles` (`employee_id`) ON DELETE SET NULL;

--
-- Constraints for table `exits`
--
ALTER TABLE `exits`
  ADD CONSTRAINT `exits_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employee_profiles` (`employee_id`) ON DELETE CASCADE;

--
-- Constraints for table `exit_checklist`
--
ALTER TABLE `exit_checklist`
  ADD CONSTRAINT `exit_checklist_ibfk_1` FOREIGN KEY (`exit_id`) REFERENCES `exits` (`exit_id`) ON DELETE CASCADE;

--
-- Constraints for table `exit_documents`
--
ALTER TABLE `exit_documents`
  ADD CONSTRAINT `exit_documents_ibfk_1` FOREIGN KEY (`exit_id`) REFERENCES `exits` (`exit_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `exit_documents_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `employee_profiles` (`employee_id`) ON DELETE CASCADE;

--
-- Constraints for table `exit_interviews`
--
ALTER TABLE `exit_interviews`
  ADD CONSTRAINT `exit_interviews_ibfk_1` FOREIGN KEY (`exit_id`) REFERENCES `exits` (`exit_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `exit_interviews_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `employee_profiles` (`employee_id`) ON DELETE CASCADE;

--
-- Constraints for table `goals`
--
ALTER TABLE `goals`
  ADD CONSTRAINT `goals_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employee_profiles` (`employee_id`) ON DELETE CASCADE;

--
-- Constraints for table `goal_updates`
--
ALTER TABLE `goal_updates`
  ADD CONSTRAINT `goal_updates_ibfk_1` FOREIGN KEY (`goal_id`) REFERENCES `goals` (`goal_id`) ON DELETE CASCADE;

--
-- Constraints for table `interviews`
--
ALTER TABLE `interviews`
  ADD CONSTRAINT `interviews_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `job_applications` (`application_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `interviews_ibfk_2` FOREIGN KEY (`stage_id`) REFERENCES `interview_stages` (`stage_id`) ON DELETE CASCADE;

--
-- Constraints for table `interview_stages`
--
ALTER TABLE `interview_stages`
  ADD CONSTRAINT `interview_stages_ibfk_1` FOREIGN KEY (`job_opening_id`) REFERENCES `job_openings` (`job_opening_id`) ON DELETE CASCADE;

--
-- Constraints for table `job_applications`
--
ALTER TABLE `job_applications`
  ADD CONSTRAINT `job_applications_ibfk_1` FOREIGN KEY (`job_opening_id`) REFERENCES `job_openings` (`job_opening_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `job_applications_ibfk_2` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`candidate_id`) ON DELETE CASCADE;

--
-- Constraints for table `job_offers`
--
ALTER TABLE `job_offers`
  ADD CONSTRAINT `job_offers_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `job_applications` (`application_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `job_offers_ibfk_2` FOREIGN KEY (`job_opening_id`) REFERENCES `job_openings` (`job_opening_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `job_offers_ibfk_3` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`candidate_id`) ON DELETE CASCADE;

--
-- Constraints for table `job_openings`
--
ALTER TABLE `job_openings`
  ADD CONSTRAINT `job_openings_ibfk_1` FOREIGN KEY (`job_role_id`) REFERENCES `job_roles` (`job_role_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `job_openings_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`) ON DELETE CASCADE;

--
-- Constraints for table `knowledge_transfers`
--
ALTER TABLE `knowledge_transfers`
  ADD CONSTRAINT `knowledge_transfers_ibfk_1` FOREIGN KEY (`exit_id`) REFERENCES `exits` (`exit_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `knowledge_transfers_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `employee_profiles` (`employee_id`) ON DELETE CASCADE;

--
-- Constraints for table `leave_balances`
--
ALTER TABLE `leave_balances`
  ADD CONSTRAINT `leave_balances_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employee_profiles` (`employee_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `leave_balances_ibfk_2` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`leave_type_id`) ON DELETE CASCADE;

--
-- Constraints for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD CONSTRAINT `leave_requests_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employee_profiles` (`employee_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `leave_requests_ibfk_2` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`leave_type_id`) ON DELETE CASCADE;

--
-- Constraints for table `onboarding_tasks`
--
ALTER TABLE `onboarding_tasks`
  ADD CONSTRAINT `onboarding_tasks_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`) ON DELETE SET NULL;

--
-- Constraints for table `payment_disbursements`
--
ALTER TABLE `payment_disbursements`
  ADD CONSTRAINT `payment_disbursements_ibfk_1` FOREIGN KEY (`payroll_transaction_id`) REFERENCES `payroll_transactions` (`payroll_transaction_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payment_disbursements_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `employee_profiles` (`employee_id`) ON DELETE CASCADE;

--
-- Constraints for table `payroll_transactions`
--
ALTER TABLE `payroll_transactions`
  ADD CONSTRAINT `payroll_transactions_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employee_profiles` (`employee_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payroll_transactions_ibfk_2` FOREIGN KEY (`payroll_cycle_id`) REFERENCES `payroll_cycles` (`payroll_cycle_id`) ON DELETE CASCADE;

--
-- Constraints for table `payslips`
--
ALTER TABLE `payslips`
  ADD CONSTRAINT `payslips_ibfk_1` FOREIGN KEY (`payroll_transaction_id`) REFERENCES `payroll_transactions` (`payroll_transaction_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payslips_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `employee_profiles` (`employee_id`) ON DELETE CASCADE;

--
-- Constraints for table `performance_metrics`
--
ALTER TABLE `performance_metrics`
  ADD CONSTRAINT `performance_metrics_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employee_profiles` (`employee_id`) ON DELETE CASCADE;

--
-- Constraints for table `performance_reviews`
--
ALTER TABLE `performance_reviews`
  ADD CONSTRAINT `performance_reviews_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employee_profiles` (`employee_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `performance_reviews_ibfk_2` FOREIGN KEY (`cycle_id`) REFERENCES `performance_review_cycles` (`cycle_id`) ON DELETE CASCADE;

--
-- Constraints for table `post_exit_surveys`
--
ALTER TABLE `post_exit_surveys`
  ADD CONSTRAINT `post_exit_surveys_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employee_profiles` (`employee_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `post_exit_surveys_ibfk_2` FOREIGN KEY (`exit_id`) REFERENCES `exits` (`exit_id`) ON DELETE CASCADE;

--
-- Constraints for table `recruitment_analytics`
--
ALTER TABLE `recruitment_analytics`
  ADD CONSTRAINT `recruitment_analytics_ibfk_1` FOREIGN KEY (`job_opening_id`) REFERENCES `job_openings` (`job_opening_id`) ON DELETE CASCADE;

--
-- Constraints for table `salary_structures`
--
ALTER TABLE `salary_structures`
  ADD CONSTRAINT `salary_structures_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employee_profiles` (`employee_id`) ON DELETE CASCADE;

--
-- Constraints for table `settlements`
--
ALTER TABLE `settlements`
  ADD CONSTRAINT `settlements_ibfk_1` FOREIGN KEY (`exit_id`) REFERENCES `exits` (`exit_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `settlements_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `employee_profiles` (`employee_id`) ON DELETE CASCADE;

--
-- Constraints for table `statutory_deductions`
--
ALTER TABLE `statutory_deductions`
  ADD CONSTRAINT `statutory_deductions_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employee_profiles` (`employee_id`) ON DELETE CASCADE;

--
-- Constraints for table `tax_deductions`
--
ALTER TABLE `tax_deductions`
  ADD CONSTRAINT `tax_deductions_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employee_profiles` (`employee_id`) ON DELETE CASCADE;

--
-- Constraints for table `training_enrollments`
--
ALTER TABLE `training_enrollments`
  ADD CONSTRAINT `training_enrollments_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `training_sessions` (`session_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `training_enrollments_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `employee_profiles` (`employee_id`) ON DELETE CASCADE;

--
-- Constraints for table `training_feedback`
--
ALTER TABLE `training_feedback`
  ADD CONSTRAINT `training_feedback_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employee_profiles` (`employee_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `training_feedback_ibfk_2` FOREIGN KEY (`session_id`) REFERENCES `training_sessions` (`session_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `training_feedback_ibfk_3` FOREIGN KEY (`resource_id`) REFERENCES `learning_resources` (`resource_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `training_feedback_ibfk_4` FOREIGN KEY (`trainer_id`) REFERENCES `trainers` (`trainer_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `training_feedback_ibfk_5` FOREIGN KEY (`course_id`) REFERENCES `training_courses` (`course_id`) ON DELETE SET NULL;

--
-- Constraints for table `training_needs_assessment`
--
ALTER TABLE `training_needs_assessment`
  ADD CONSTRAINT `training_needs_assessment_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employee_profiles` (`employee_id`) ON DELETE CASCADE;

--
-- Constraints for table `training_sessions`
--
ALTER TABLE `training_sessions`
  ADD CONSTRAINT `training_sessions_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `training_courses` (`course_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `training_sessions_ibfk_2` FOREIGN KEY (`trainer_id`) REFERENCES `trainers` (`trainer_id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employee_profiles` (`employee_id`) ON DELETE SET NULL;
COMMIT;

-- --------------------------------------------------------
--
-- Unified Archive Storage Table
-- Handles archiving for: employee_profiles, personal_information, 
-- employment_history, and document_management
--

CREATE TABLE `archive_storage` (
  `archive_id` int(11) NOT NULL AUTO_INCREMENT,
  `source_table` enum('employee_profiles','personal_information','employment_history','document_management') NOT NULL,
  `record_id` int(11) NOT NULL COMMENT 'Original primary key from source table',
  `employee_id` int(11) DEFAULT NULL COMMENT 'Employee reference for all archived records',
  `record_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'JSON containing all original data',
  `archive_reason` enum('Termination','Resignation','Retirement','Data Cleanup','System Migration','Expired Document','Other') NOT NULL,
  `archive_reason_details` text DEFAULT NULL,
  `archived_by` int(11) NOT NULL COMMENT 'User ID who archived the record',
  `archived_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `can_restore` tinyint(1) DEFAULT 1 COMMENT 'Whether this record can be restored',
  `restored_at` timestamp NULL DEFAULT NULL,
  `restored_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`archive_id`),
  KEY `source_table` (`source_table`),
  KEY `record_id` (`record_id`),
  KEY `employee_id` (`employee_id`),
  KEY `archived_by` (`archived_by`),
  KEY `archived_at` (`archived_at`),
  CONSTRAINT `archive_storage_ibfk_1` FOREIGN KEY (`archived_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `archive_storage_ibfk_2` FOREIGN KEY (`restored_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT CHK_record_data CHECK (json_valid(`record_data`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
--
-- Sample Data for Unified Archive Storage
--

-- Example 1: Archived Employee Profile (Terminated)
INSERT INTO `archive_storage` 
(`source_table`, `record_id`, `employee_id`, `record_data`, `archive_reason`, 
`archive_reason_details`, `archived_by`, `archived_at`, `can_restore`, `notes`) 
VALUES
('employee_profiles', 16, 16, 
'{
  "employee_id": 16,
  "personal_info_id": 16,
  "job_role_id": 29,
  "employee_number": "MUN016",
  "hire_date": "2018-03-15",
  "employment_status": "Terminated",
  "current_salary": 30000.00,
  "work_email": "pedro.santos@municipality.gov.ph",
  "work_phone": "034-123-0016",
  "location": "Municipal Civil Registrar\'s Office",
  "remote_work": 0,
  "created_at": "2018-03-15 02:00:00",
  "updated_at": "2025-08-14 05:20:00"
}', 
'Termination', 'Employee terminated due to prolonged absence without notice (AWOL)', 
1, '2025-08-15 08:30:00', 0, 'Final clearance completed. All equipment returned.');

-- Example 2: Archived Personal Information (Terminated Employee)
INSERT INTO `archive_storage` 
(`source_table`, `record_id`, `employee_id`, `record_data`, `archive_reason`, 
`archive_reason_details`, `archived_by`, `archived_at`, `can_restore`, `notes`) 
VALUES
('personal_information', 16, 16, 
'{
  "personal_info_id": 16,
  "first_name": "Pedro",
  "last_name": "Santos",
  "date_of_birth": "1985-05-20",
  "gender": "Male",
  "marital_status": "Single",
  "nationality": "Filipino",
  "tax_id": "678-91-2345",
  "social_security_number": "678912345",
  "phone_number": "0917-680-1234",
  "emergency_contact_name": "Maria Santos",
  "emergency_contact_relationship": "Sister",
  "emergency_contact_phone": "0917-024-5678",
  "created_at": "2018-03-15 02:00:00",
  "updated_at": "2022-06-10 03:15:00"
}', 
'Termination', 'Personal information archived with employee termination', 
1, '2025-08-15 08:30:00', 0, 'Sensitive data retained as per retention policy.');

-- Example 3: Archived Employment History (Terminated Employee)
INSERT INTO `archive_storage` 
(`source_table`, `record_id`, `employee_id`, `record_data`, `archive_reason`, 
`archive_reason_details`, `archived_by`, `archived_at`, `can_restore`, `notes`) 
VALUES
('employment_history', 16, 16, 
'{
  "history_id": 16,
  "employee_id": 16,
  "job_title": "Clerk",
  "department_id": 8,
  "employment_type": "Full-time",
  "start_date": "2018-03-15",
  "end_date": "2025-08-14",
  "employment_status": "Terminated",
  "reporting_manager_id": null,
  "location": "Municipal Civil Registrar\'s Office",
  "base_salary": 30000.00,
  "allowances": 1000.00,
  "bonuses": 0.00,
  "salary_adjustments": 0.00,
  "reason_for_change": "Terminated due to AWOL",
  "promotions_transfers": null,
  "duties_responsibilities": "Maintained registry records, assisted clients with civil documents.",
  "performance_evaluations": "Last rating was Satisfactory in 2024 review",
  "training_certifications": "Civil Registration Training",
  "contract_details": "Fixed-term contract terminated early",
  "remarks": "Multiple written warnings for attendance issues before termination",
  "created_at": "2018-03-15 02:00:00",
  "updated_at": "2025-08-14 05:20:00"
}', 
'Termination', 'Employment history archived upon termination', 
1, '2025-08-15 08:30:00', 0, 'Complete employment record preserved for legal compliance.');

-- Example 4: Archived Document (Expired Contract)
INSERT INTO `archive_storage` 
(`source_table`, `record_id`, `employee_id`, `record_data`, `archive_reason`, 
`archive_reason_details`, `archived_by`, `archived_at`, `can_restore`, `notes`) 
VALUES
('document_management', 33, 16, 
'{
  "document_id": 33,
  "employee_id": 16,
  "document_type": "Contract",
  "document_name": "Employment Contract - Clerk",
  "file_path": "/documents/contracts/pedro_santos_contract.pdf",
  "upload_date": "2018-03-15 02:00:00",
  "expiry_date": "2025-03-15",
  "document_status": "Expired",
  "notes": "Civil registrar office clerk contract",
  "created_at": "2018-03-15 02:00:00",
  "updated_at": "2025-08-14 05:20:00"
}', 
'Expired Document', 'Document archived after employee termination and contract expiry', 
1, '2025-08-15 08:30:00', 0, 'Physical document retained in secure storage.');

-- Example 5: Archived Employee Profile (Retired)
INSERT INTO `archive_storage` 
(`source_table`, `record_id`, `employee_id`, `record_data`, `archive_reason`, 
`archive_reason_details`, `archived_by`, `archived_at`, `can_restore`, `notes`) 
VALUES
('employee_profiles', 17, 17, 
'{
  "employee_id": 17,
  "personal_info_id": 17,
  "job_role_id": 34,
  "employee_number": "MUN017",
  "hire_date": "1995-06-01",
  "employment_status": "Full-time",
  "current_salary": 25000.00,
  "work_email": "ramon.reyes@municipality.gov.ph",
  "work_phone": "034-123-0017",
  "location": "General Services Office",
  "remote_work": 0,
  "created_at": "1995-06-01 02:00:00",
  "updated_at": "2025-06-29 08:00:00"
}', 
'Retirement', 'Employee retired after 30 years of exemplary service', 
2, '2025-06-30 16:00:00', 0, 'Retirement ceremony held on June 28, 2025. Plaque of appreciation awarded.');

-- Example 6: Archived Personal Information (Retired Employee)
INSERT INTO `archive_storage` 
(`source_table`, `record_id`, `employee_id`, `record_data`, `archive_reason`, 
`archive_reason_details`, `archived_by`, `archived_at`, `can_restore`, `notes`) 
VALUES
('personal_information', 17, 17, 
'{
  "personal_info_id": 17,
  "first_name": "Ramon",
  "last_name": "Reyes",
  "date_of_birth": "1960-02-15",
  "gender": "Male",
  "marital_status": "Married",
  "nationality": "Filipino",
  "tax_id": "789-02-3456",
  "social_security_number": "789023456",
  "phone_number": "0917-791-2345",
  "emergency_contact_name": "Elena Reyes",
  "emergency_contact_relationship": "Spouse",
  "emergency_contact_phone": "0917-135-6789",
  "created_at": "1995-06-01 02:00:00",
  "updated_at": "2020-03-10 04:20:00"
}', 
'Retirement', 'Personal information archived upon retirement', 
2, '2025-06-30 16:00:00', 0, 'Contact information maintained for pension processing.');

-- Example 7: Archived Document (Data Cleanup - Old Resume)
INSERT INTO `archive_storage` 
(`source_table`, `record_id`, `employee_id`, `record_data`, `archive_reason`, 
`archive_reason_details`, `archived_by`, `archived_at`, `can_restore`, `notes`) 
VALUES
('document_management', 35, 11, 
'{
  "document_id": 35,
  "employee_id": 11,
  "document_type": "Resume",
  "document_name": "Resume - Ana Morales (2020 Version)",
  "file_path": "/documents/resumes/ana_morales_resume_2020.pdf",
  "upload_date": "2020-04-15 02:00:00",
  "expiry_date": null,
  "document_status": "Active",
  "notes": "Outdated resume replaced with newer version",
  "created_at": "2020-04-15 02:00:00",
  "updated_at": "2025-10-01 03:15:00"
}', 
'Data Cleanup', 'Old version archived after employee submitted updated resume', 
2, '2025-10-01 10:00:00', 1, 'Previous version archived for historical records.');

-- Example 8: Archived Employment History (Resigned Employee)
INSERT INTO `archive_storage` 
(`source_table`, `record_id`, `employee_id`, `record_data`, `archive_reason`, 
`archive_reason_details`, `archived_by`, `archived_at`, `can_restore`, `notes`) 
VALUES
('employment_history', 18, 18, 
'{
  "history_id": 18,
  "employee_id": 18,
  "job_title": "Budget Analyst",
  "department_id": 4,
  "employment_type": "Full-time",
  "start_date": "2019-09-01",
  "end_date": "2025-09-30",
  "employment_status": "Resigned",
  "reporting_manager_id": null,
  "location": "Municipal Budget Office",
  "base_salary": 42000.00,
  "allowances": 3000.00,
  "bonuses": 5000.00,
  "salary_adjustments": 2000.00,
  "reason_for_change": "Resigned for career advancement opportunity abroad",
  "promotions_transfers": "Promoted from Administrative Aide in 2021",
  "duties_responsibilities": "Analyzed budget data and prepared financial reports for municipal operations.",
  "performance_evaluations": "Consistently rated Outstanding. Received Best Employee Award 2023.",
  "training_certifications": "Financial Planning Certification, Advanced Excel Training",
  "contract_details": "Regular plantilla position",
  "remarks": "Excellent employee. Provided comprehensive turnover documentation. Eligible for rehire.",
  "created_at": "2019-09-01 02:00:00",
  "updated_at": "2025-09-30 10:15:00"
}', 
'Resignation', 'Employee resigned in good standing for overseas employment', 
1, '2025-09-30 14:00:00', 1, 'Exit clearance completed. Certificate of Employment issued.');


-- Step 1: Add new columns to personal_information table
ALTER TABLE `personal_information`
ADD COLUMN `highest_educational_attainment` ENUM('Elementary', 'High School', 'Vocational', 'Associate Degree', 'Bachelor\'s Degree', 'Master\'s Degree', 'Doctoral Degree') DEFAULT NULL AFTER `emergency_contact_phone`,
ADD COLUMN `course_degree` VARCHAR(150) DEFAULT NULL AFTER `highest_educational_attainment`,
ADD COLUMN `school_university` VARCHAR(150) DEFAULT NULL AFTER `course_degree`,
ADD COLUMN `year_graduated` YEAR DEFAULT NULL AFTER `school_university`,
ADD COLUMN `marital_status_date` DATE DEFAULT NULL COMMENT 'Date of marriage or divorce' AFTER `marital_status`,
ADD COLUMN `marital_status_document_url` VARCHAR(255) DEFAULT NULL COMMENT 'Marriage certificate or divorce decree' AFTER `marital_status_date`;

-- Step 2: Create educational_background table for detailed education history
CREATE TABLE `educational_background` (
  `education_id` INT(11) NOT NULL AUTO_INCREMENT,
  `personal_info_id` INT(11) NOT NULL,
  `education_level` ENUM('Elementary', 'High School', 'Vocational', 'Associate Degree', 'Bachelor\'s Degree', 'Master\'s Degree', 'Doctoral Degree', 'Other') NOT NULL,
  `school_name` VARCHAR(150) NOT NULL,
  `course_degree` VARCHAR(150) DEFAULT NULL COMMENT 'Course or degree program',
  `major_specialization` VARCHAR(100) DEFAULT NULL,
  `year_started` YEAR DEFAULT NULL,
  `year_graduated` YEAR DEFAULT NULL,
  `honors_awards` VARCHAR(255) DEFAULT NULL,
  `is_highest_attainment` TINYINT(1) DEFAULT 0,
  `document_url` VARCHAR(255) DEFAULT NULL COMMENT 'Diploma or certificate',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`education_id`),
  KEY `personal_info_id` (`personal_info_id`),
  CONSTRAINT `educational_background_ibfk_1` FOREIGN KEY (`personal_info_id`) REFERENCES `personal_information` (`personal_info_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Step 3: Create marital_status_history table for tracking changes
CREATE TABLE `marital_status_history` (
  `status_history_id` INT(11) NOT NULL AUTO_INCREMENT,
  `personal_info_id` INT(11) NOT NULL,
  `marital_status` ENUM('Single', 'Married', 'Divorced', 'Widowed', 'Separated', 'Annulled') NOT NULL,
  `status_date` DATE NOT NULL COMMENT 'Date of marriage, divorce, etc.',
  `spouse_name` VARCHAR(100) DEFAULT NULL,
  `supporting_document_type` ENUM('Marriage Certificate', 'Divorce Decree', 'Death Certificate', 'Annulment Certificate', 'Separation Agreement') DEFAULT NULL,
  `document_url` VARCHAR(255) DEFAULT NULL,
  `document_number` VARCHAR(50) DEFAULT NULL COMMENT 'Certificate or decree number',
  `issuing_authority` VARCHAR(150) DEFAULT NULL,
  `remarks` TEXT DEFAULT NULL,
  `is_current` TINYINT(1) DEFAULT 1 COMMENT '1 = current status, 0 = historical',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`status_history_id`),
  KEY `personal_info_id` (`personal_info_id`),
  CONSTRAINT `marital_status_history_ibfk_1` FOREIGN KEY (`personal_info_id`) REFERENCES `personal_information` (`personal_info_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Step 4: Sample data for educational_background
INSERT INTO `educational_background` 
(`personal_info_id`, `education_level`, `school_name`, `course_degree`, `major_specialization`, `year_started`, `year_graduated`, `honors_awards`, `is_highest_attainment`, `document_url`) 
VALUES
(1, 'Bachelor\'s Degree', 'University of the Philippines', 'Bachelor of Science in Accountancy', 'Accountancy', 2003, 2007, 'Cum Laude', 1, '/documents/diplomas/maria_santos_bsa.pdf'),
(2, 'Bachelor\'s Degree', 'De La Salle University', 'Bachelor of Science in Civil Engineering', 'Civil Engineering', 1996, 2000, NULL, 1, '/documents/diplomas/roberto_cruz_bsce.pdf'),
(3, 'Bachelor\'s Degree', 'Far Eastern University', 'Bachelor of Science in Nursing', 'Nursing', 2006, 2010, NULL, 1, '/documents/diplomas/jennifer_reyes_bsn.pdf'),
(4, 'Vocational', 'Technical Education and Skills Development Authority', 'Computer-Aided Design', 'CAD Operations', 1993, 1995, NULL, 1, '/documents/certificates/antonio_garcia_cad_cert.pdf'),
(5, 'Master\'s Degree', 'University of Santo Tomas', 'Master of Social Work', 'Community Development', 2008, 2012, NULL, 1, '/documents/diplomas/lisa_mendoza_msw.pdf');

-- Step 5: Sample data for marital_status_history
INSERT INTO `marital_status_history` 
(`personal_info_id`, `marital_status`, `status_date`, `spouse_name`, `supporting_document_type`, `document_url`, `document_number`, `issuing_authority`, `is_current`) 
VALUES
(1, 'Married', '2012-05-15', 'Carlos Santos', 'Marriage Certificate', '/documents/marital/maria_santos_marriage_cert.pdf', 'MC-2012-05-001234', 'Manila City Civil Registrar', 1),
(2, 'Married', '2005-11-20', 'Elena Cruz', 'Marriage Certificate', '/documents/marital/roberto_cruz_marriage_cert.pdf', 'MC-2005-11-005678', 'Quezon City Civil Registrar', 1),
(4, 'Married', '2001-03-10', 'Rosa Garcia', 'Marriage Certificate', '/documents/marital/antonio_garcia_marriage_cert.pdf', 'MC-2001-03-009012', 'Makati City Civil Registrar', 1),
(5, 'Divorced', '2018-08-22', 'John Mendoza', 'Divorce Decree', '/documents/marital/lisa_mendoza_divorce_decree.pdf', 'DD-2018-08-003456', 'Family Court Manila', 1),
(6, 'Married', '2008-07-14', 'Anna Torres', 'Marriage Certificate', '/documents/marital/michael_torres_marriage_cert.pdf', 'MC-2008-07-007890', 'Pasig City Civil Registrar', 1);

-- Step 6: Update existing personal_information records with educational data
UPDATE `personal_information` SET 
  `highest_educational_attainment` = 'Bachelor\'s Degree',
  `course_degree` = 'Bachelor of Science in Accountancy',
  `school_university` = 'University of the Philippines',
  `year_graduated` = 2007,
  `marital_status_date` = '2012-05-15',
  `marital_status_document_url` = '/documents/marital/maria_santos_marriage_cert.pdf'
WHERE `personal_info_id` = 1;

UPDATE `personal_information` SET 
  `highest_educational_attainment` = 'Bachelor\'s Degree',
  `course_degree` = 'Bachelor of Science in Civil Engineering',
  `school_university` = 'De La Salle University',
  `year_graduated` = 2000,
  `marital_status_date` = '2005-11-20',
  `marital_status_document_url` = '/documents/marital/roberto_cruz_marriage_cert.pdf'
WHERE `personal_info_id` = 2;

UPDATE `personal_information` SET 
  `highest_educational_attainment` = 'Bachelor\'s Degree',
  `course_degree` = 'Bachelor of Science in Nursing',
  `school_university` = 'Far Eastern University',
  `year_graduated` = 2010
WHERE `personal_info_id` = 3;

UPDATE `personal_information` SET 
  `highest_educational_attainment` = 'Vocational',
  `course_degree` = 'Computer-Aided Design',
  `school_university` = 'TESDA',
  `year_graduated` = 1995,
  `marital_status_date` = '2001-03-10',
  `marital_status_document_url` = '/documents/marital/antonio_garcia_marriage_cert.pdf'
WHERE `personal_info_id` = 4;

UPDATE `personal_information` SET 
  `highest_educational_attainment` = 'Master\'s Degree',
  `course_degree` = 'Master of Social Work',
  `school_university` = 'University of Santo Tomas',
  `year_graduated` = 2012,
  `marital_status_date` = '2018-08-22',
  `marital_status_document_url` = '/documents/marital/lisa_mendoza_divorce_decree.pdf'
WHERE `personal_info_id` = 5;

-- Step 7: Create uploads directory structure (Note: This needs to be done manually on the server)
-- Create these directories in your project root:
-- /uploads/education_documents/
-- /uploads/marital_documents/

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- Enhanced Exit Checklist Table Schema
-- This schema supports all panelist requirements: remarks, approval, physical items, clearances, and tracking codes

-- First, ensure the exit_checklist table has all required columns
ALTER TABLE exit_checklist 
ADD COLUMN IF NOT EXISTS item_type ENUM('Physical', 'Document', 'Access', 'Financial', 'Other') DEFAULT 'Other' AFTER notes,
ADD COLUMN IF NOT EXISTS serial_number VARCHAR(100) DEFAULT NULL AFTER item_type,
ADD COLUMN IF NOT EXISTS sticker_type VARCHAR(100) DEFAULT NULL AFTER serial_number,
ADD COLUMN IF NOT EXISTS approval_status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending' AFTER sticker_type,
ADD COLUMN IF NOT EXISTS approved_by VARCHAR(100) DEFAULT NULL AFTER approval_status,
ADD COLUMN IF NOT EXISTS approved_date DATE DEFAULT NULL AFTER approved_by,
ADD COLUMN IF NOT EXISTS remarks TEXT DEFAULT NULL AFTER approved_date,
ADD COLUMN IF NOT EXISTS clearance_status ENUM('Pending', 'Cleared', 'Conditional') DEFAULT 'Pending' AFTER remarks,
ADD COLUMN IF NOT EXISTS clearance_date DATE DEFAULT NULL AFTER clearance_status,
ADD COLUMN IF NOT EXISTS cleared_by VARCHAR(100) DEFAULT NULL AFTER clearance_date;

-- Create index for faster queries
CREATE INDEX IF NOT EXISTS idx_approval_status ON exit_checklist(approval_status);
CREATE INDEX IF NOT EXISTS idx_clearance_status ON exit_checklist(clearance_status);
CREATE INDEX IF NOT EXISTS idx_item_type ON exit_checklist(item_type);
CREATE INDEX IF NOT EXISTS idx_serial_number ON exit_checklist(serial_number);

-- Create audit log table for tracking changes
CREATE TABLE IF NOT EXISTS exit_checklist_audit (
    audit_id INT PRIMARY KEY AUTO_INCREMENT,
    checklist_id INT NOT NULL,
    action_type ENUM('Created', 'Updated', 'Deleted', 'Approved', 'Rejected', 'Cleared') NOT NULL,
    field_changed VARCHAR(100),
    old_value TEXT,
    new_value TEXT,
    changed_by VARCHAR(100),
    changed_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    remarks TEXT,
    FOREIGN KEY (checklist_id) REFERENCES exit_checklist(checklist_id) ON DELETE CASCADE
);

-- Create approval workflow table
CREATE TABLE IF NOT EXISTS exit_checklist_approvals (
    approval_id INT PRIMARY KEY AUTO_INCREMENT,
    checklist_id INT NOT NULL,
    approver_id VARCHAR(100) NOT NULL,
    approver_name VARCHAR(255) NOT NULL,
    approval_level INT DEFAULT 1,
    decision ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
    decision_date DATETIME,
    decision_remarks TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (checklist_id) REFERENCES exit_checklist(checklist_id) ON DELETE CASCADE
);

-- Create clearance tracking table
CREATE TABLE IF NOT EXISTS exit_clearance_tracking (
    clearance_id INT PRIMARY KEY AUTO_INCREMENT,
    exit_id INT NOT NULL,
    department VARCHAR(100) NOT NULL,
    clearance_officer VARCHAR(100),
    clearance_status ENUM('Pending', 'Cleared', 'Conditional', 'Not Required') DEFAULT 'Pending',
    items_cleared TEXT,
    conditions TEXT,
    cleared_date DATE,
    remarks TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (exit_id) REFERENCES exits(exit_id) ON DELETE CASCADE
);

-- Create physical items inventory table
CREATE TABLE IF NOT EXISTS exit_physical_items (
    item_id INT PRIMARY KEY AUTO_INCREMENT,
    checklist_id INT NOT NULL,
    item_category VARCHAR(100) NOT NULL,
    item_description TEXT,
    serial_number VARCHAR(100),
    sticker_code VARCHAR(100),
    asset_tag VARCHAR(100),
    condition_on_return ENUM('Good', 'Fair', 'Poor', 'Damaged', 'Missing') DEFAULT 'Good',
    return_date DATE,
    received_by VARCHAR(100),
    verification_status ENUM('Pending', 'Verified', 'Discrepancy') DEFAULT 'Pending',
    verification_remarks TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (checklist_id) REFERENCES exit_checklist(checklist_id) ON DELETE CASCADE
);

-- View for complete exit clearance status
CREATE OR REPLACE VIEW exit_clearance_summary AS
SELECT 
    e.exit_id,
    e.employee_id,
    CONCAT(pi.first_name, ' ', pi.last_name) as employee_name,
    ep.employee_number,
    e.exit_date,
    COUNT(ec.checklist_id) as total_items,
    SUM(CASE WHEN ec.status = 'Completed' THEN 1 ELSE 0 END) as completed_items,
    SUM(CASE WHEN ec.approval_status = 'Approved' THEN 1 ELSE 0 END) as approved_items,
    SUM(CASE WHEN ec.clearance_status = 'Cleared' THEN 1 ELSE 0 END) as cleared_items,
    CASE 
        WHEN COUNT(ec.checklist_id) = SUM(CASE WHEN ec.clearance_status = 'Cleared' THEN 1 ELSE 0 END) 
        THEN 'Fully Cleared'
        WHEN SUM(CASE WHEN ec.clearance_status = 'Cleared' THEN 1 ELSE 0 END) > 0 
        THEN 'Partially Cleared'
        ELSE 'Not Cleared'
    END as overall_clearance_status
FROM exits e
LEFT JOIN employee_profiles ep ON e.employee_id = ep.employee_id
LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
LEFT JOIN exit_checklist ec ON e.exit_id = ec.exit_id
GROUP BY e.exit_id, e.employee_id, pi.first_name, pi.last_name, ep.employee_number, e.exit_date;

-- Query to get comprehensive exit checklist report
-- This includes all panelist requirements: remarks, approval, physical items, clearances, codes
SELECT 
    ec.*,
    e.employee_id,
    e.exit_date,
    e.exit_type,
    CONCAT(pi.first_name, ' ', pi.last_name) as employee_name,
    ep.employee_number,
    ec.responsible_department as department,
    -- Clearance indicators
    CASE 
        WHEN ec.status = 'Completed' AND ec.approval_status = 'Approved' AND ec.clearance_status = 'Cleared'
        THEN 'FULLY CLEARED'
        WHEN ec.status = 'Completed' AND ec.approval_status = 'Approved'
        THEN 'PENDING CLEARANCE'
        WHEN ec.status = 'Completed'
        THEN 'PENDING APPROVAL'
        ELSE 'IN PROGRESS'
    END as clearance_indicator,
    -- Physical item tracking
    CONCAT_WS(' | ', 
        CASE WHEN ec.serial_number IS NOT NULL THEN CONCAT('SN:', ec.serial_number) END,
        CASE WHEN ec.sticker_type IS NOT NULL THEN CONCAT('Sticker:', ec.sticker_type) END
    ) as item_codes
FROM exit_checklist ec
LEFT JOIN exits e ON ec.exit_id = e.exit_id
LEFT JOIN employee_profiles ep ON e.employee_id = ep.employee_id
LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
ORDER BY 
    FIELD(ec.clearance_status, 'Pending', 'Conditional', 'Cleared'),
    FIELD(ec.approval_status, 'Pending', 'Rejected', 'Approved'),
    ec.created_at DESC;

-- Stored procedure for automatic clearance status update
DELIMITER //
CREATE PROCEDURE update_clearance_status(IN p_checklist_id INT)
BEGIN
    DECLARE v_status VARCHAR(20);
    DECLARE v_approval VARCHAR(20);
    
    SELECT status, approval_status INTO v_status, v_approval
    FROM exit_checklist
    WHERE checklist_id = p_checklist_id;
    
    IF v_status = 'Completed' AND v_approval = 'Approved' THEN
        UPDATE exit_checklist
        SET clearance_status = 'Cleared',
            clearance_date = CURDATE(),
            cleared_by = COALESCE(approved_by, 'System')
        WHERE checklist_id = p_checklist_id;
    END IF;
END//
DELIMITER ;

-- Trigger to log changes (audit trail only - no table updates to avoid recursion)
DELIMITER //
CREATE TRIGGER after_checklist_update
AFTER UPDATE ON exit_checklist
FOR EACH ROW
BEGIN
    -- Log the change in audit table
    IF OLD.status != NEW.status OR OLD.approval_status != NEW.approval_status OR OLD.clearance_status != NEW.clearance_status THEN
        INSERT INTO exit_checklist_audit (checklist_id, action_type, field_changed, old_value, new_value, changed_by)
        VALUES (NEW.checklist_id, 'Updated', 
                CASE 
                    WHEN OLD.status != NEW.status THEN 'status'
                    WHEN OLD.approval_status != NEW.approval_status THEN 'approval_status'
                    WHEN OLD.clearance_status != NEW.clearance_status THEN 'clearance_status'
                    ELSE 'general'
                END,
                CASE 
                    WHEN OLD.status != NEW.status THEN OLD.status
                    WHEN OLD.approval_status != NEW.approval_status THEN OLD.approval_status
                    WHEN OLD.clearance_status != NEW.clearance_status THEN OLD.clearance_status
                    ELSE NULL
                END,
                CASE 
                    WHEN OLD.status != NEW.status THEN NEW.status
                    WHEN OLD.approval_status != NEW.approval_status THEN NEW.approval_status
                    WHEN OLD.clearance_status != NEW.clearance_status THEN NEW.clearance_status
                    ELSE NULL
                END,
                COALESCE(NEW.approved_by, 'System'));
    END IF;
END//
DELIMITER ;

-- Note: Clearance status should be updated in the PHP code, not via trigger
-- Add this logic to your PHP update statement:
-- IF status='Completed' AND approval_status='Approved' THEN SET clearance_status='Cleared'

ALTER TABLE post_exit_surveys
  ADD COLUMN is_anonymous TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN evaluation_score INT DEFAULT 0,
  ADD COLUMN evaluation_criteria TEXT NULL;

  ALTER TABLE post_exit_surveys
  MODIFY COLUMN employee_id INT NULL;
