-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 21, 2025 at 03:58 PM
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

-- --------------------------------------------------------

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
-- AUTO_INCREMENT for table `compensation_packages`
--
ALTER TABLE `compensation_packages`
  MODIFY `compensation_package_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `competencies`
--
ALTER TABLE `competencies`
  MODIFY `competency_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

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

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
