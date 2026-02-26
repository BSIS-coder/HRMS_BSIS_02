-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 27, 2026 at 06:36 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET FOREIGN_KEY_CHECKS = 0;
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
-- Table structure for table `archive_storage`
--

CREATE TABLE `archive_storage` (
  `archive_id` int(11) NOT NULL,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

--
-- Dumping data for table `archive_storage`
--

INSERT INTO `archive_storage` (`archive_id`, `source_table`, `record_id`, `employee_id`, `record_data`, `archive_reason`, `archive_reason_details`, `archived_by`, `archived_at`, `can_restore`, `restored_at`, `restored_by`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'employee_profiles', 16, 16, '{\r\n  \"employee_id\": 16,\r\n  \"personal_info_id\": 16,\r\n  \"job_role_id\": 29,\r\n  \"employee_number\": \"MUN016\",\r\n  \"hire_date\": \"2018-03-15\",\r\n  \"employment_status\": \"Terminated\",\r\n  \"current_salary\": 30000.00,\r\n  \"work_email\": \"pedro.santos@municipality.gov.ph\",\r\n  \"work_phone\": \"034-123-0016\",\r\n  \"location\": \"Municipal Civil Registrar\'s Office\",\r\n  \"remote_work\": 0,\r\n  \"created_at\": \"2018-03-15 02:00:00\",\r\n  \"updated_at\": \"2025-08-14 05:20:00\"\r\n}', 'Termination', 'Employee terminated due to prolonged absence without notice (AWOL)', 1, '2025-08-15 08:30:00', 0, NULL, NULL, 'Final clearance completed. All equipment returned.', '2026-01-20 02:55:57', '2026-01-20 02:55:57'),
(2, 'personal_information', 16, 16, '{\r\n  \"personal_info_id\": 16,\r\n  \"first_name\": \"Pedro\",\r\n  \"last_name\": \"Santos\",\r\n  \"date_of_birth\": \"1985-05-20\",\r\n  \"gender\": \"Male\",\r\n  \"marital_status\": \"Single\",\r\n  \"nationality\": \"Filipino\",\r\n  \"tax_id\": \"678-91-2345\",\r\n  \"social_security_number\": \"678912345\",\r\n  \"phone_number\": \"0917-680-1234\",\r\n  \"emergency_contact_name\": \"Maria Santos\",\r\n  \"emergency_contact_relationship\": \"Sister\",\r\n  \"emergency_contact_phone\": \"0917-024-5678\",\r\n  \"created_at\": \"2018-03-15 02:00:00\",\r\n  \"updated_at\": \"2022-06-10 03:15:00\"\r\n}', 'Termination', 'Personal information archived with employee termination', 1, '2025-08-15 08:30:00', 0, NULL, NULL, 'Sensitive data retained as per retention policy.', '2026-01-20 02:55:57', '2026-01-20 02:55:57'),
(3, 'employment_history', 16, 16, '{\r\n  \"history_id\": 16,\r\n  \"employee_id\": 16,\r\n  \"job_title\": \"Clerk\",\r\n  \"department_id\": 8,\r\n  \"employment_type\": \"Full-time\",\r\n  \"start_date\": \"2018-03-15\",\r\n  \"end_date\": \"2025-08-14\",\r\n  \"employment_status\": \"Terminated\",\r\n  \"reporting_manager_id\": null,\r\n  \"location\": \"Municipal Civil Registrar\'s Office\",\r\n  \"base_salary\": 30000.00,\r\n  \"allowances\": 1000.00,\r\n  \"bonuses\": 0.00,\r\n  \"salary_adjustments\": 0.00,\r\n  \"reason_for_change\": \"Terminated due to AWOL\",\r\n  \"promotions_transfers\": null,\r\n  \"duties_responsibilities\": \"Maintained registry records, assisted clients with civil documents.\",\r\n  \"performance_evaluations\": \"Last rating was Satisfactory in 2024 review\",\r\n  \"training_certifications\": \"Civil Registration Training\",\r\n  \"contract_details\": \"Fixed-term contract terminated early\",\r\n  \"remarks\": \"Multiple written warnings for attendance issues before termination\",\r\n  \"created_at\": \"2018-03-15 02:00:00\",\r\n  \"updated_at\": \"2025-08-14 05:20:00\"\r\n}', 'Termination', 'Employment history archived upon termination', 1, '2025-08-15 08:30:00', 0, NULL, NULL, 'Complete employment record preserved for legal compliance.', '2026-01-20 02:55:57', '2026-01-20 02:55:57'),
(4, 'document_management', 33, 16, '{\r\n  \"document_id\": 33,\r\n  \"employee_id\": 16,\r\n  \"document_type\": \"Contract\",\r\n  \"document_name\": \"Employment Contract - Clerk\",\r\n  \"file_path\": \"/documents/contracts/pedro_santos_contract.pdf\",\r\n  \"upload_date\": \"2018-03-15 02:00:00\",\r\n  \"expiry_date\": \"2025-03-15\",\r\n  \"document_status\": \"Expired\",\r\n  \"notes\": \"Civil registrar office clerk contract\",\r\n  \"created_at\": \"2018-03-15 02:00:00\",\r\n  \"updated_at\": \"2025-08-14 05:20:00\"\r\n}', 'Expired Document', 'Document archived after employee termination and contract expiry', 1, '2025-08-15 08:30:00', 0, NULL, NULL, 'Physical document retained in secure storage.', '2026-01-20 02:55:57', '2026-01-20 02:55:57'),
(5, 'employee_profiles', 17, 17, '{\r\n  \"employee_id\": 17,\r\n  \"personal_info_id\": 17,\r\n  \"job_role_id\": 34,\r\n  \"employee_number\": \"MUN017\",\r\n  \"hire_date\": \"1995-06-01\",\r\n  \"employment_status\": \"Full-time\",\r\n  \"current_salary\": 25000.00,\r\n  \"work_email\": \"ramon.reyes@municipality.gov.ph\",\r\n  \"work_phone\": \"034-123-0017\",\r\n  \"location\": \"General Services Office\",\r\n  \"remote_work\": 0,\r\n  \"created_at\": \"1995-06-01 02:00:00\",\r\n  \"updated_at\": \"2025-06-29 08:00:00\"\r\n}', 'Retirement', 'Employee retired after 30 years of exemplary service', 2, '2025-06-30 16:00:00', 0, NULL, NULL, 'Retirement ceremony held on June 28, 2025. Plaque of appreciation awarded.', '2026-01-20 02:55:57', '2026-01-20 02:55:57'),
(6, 'personal_information', 17, 17, '{\r\n  \"personal_info_id\": 17,\r\n  \"first_name\": \"Ramon\",\r\n  \"last_name\": \"Reyes\",\r\n  \"date_of_birth\": \"1960-02-15\",\r\n  \"gender\": \"Male\",\r\n  \"marital_status\": \"Married\",\r\n  \"nationality\": \"Filipino\",\r\n  \"tax_id\": \"789-02-3456\",\r\n  \"social_security_number\": \"789023456\",\r\n  \"phone_number\": \"0917-791-2345\",\r\n  \"emergency_contact_name\": \"Elena Reyes\",\r\n  \"emergency_contact_relationship\": \"Spouse\",\r\n  \"emergency_contact_phone\": \"0917-135-6789\",\r\n  \"created_at\": \"1995-06-01 02:00:00\",\r\n  \"updated_at\": \"2020-03-10 04:20:00\"\r\n}', 'Retirement', 'Personal information archived upon retirement', 2, '2025-06-30 16:00:00', 0, NULL, NULL, 'Contact information maintained for pension processing.', '2026-01-20 02:55:57', '2026-01-20 02:55:57'),
(7, 'document_management', 35, 11, '{\r\n  \"document_id\": 35,\r\n  \"employee_id\": 11,\r\n  \"document_type\": \"Resume\",\r\n  \"document_name\": \"Resume - Ana Morales (2020 Version)\",\r\n  \"file_path\": \"/documents/resumes/ana_morales_resume_2020.pdf\",\r\n  \"upload_date\": \"2020-04-15 02:00:00\",\r\n  \"expiry_date\": null,\r\n  \"document_status\": \"Active\",\r\n  \"notes\": \"Outdated resume replaced with newer version\",\r\n  \"created_at\": \"2020-04-15 02:00:00\",\r\n  \"updated_at\": \"2025-10-01 03:15:00\"\r\n}', 'Data Cleanup', 'Old version archived after employee submitted updated resume', 2, '2025-10-01 10:00:00', 1, NULL, NULL, 'Previous version archived for historical records.', '2026-01-20 02:55:57', '2026-01-20 02:55:57'),
(8, 'employment_history', 18, 18, '{\r\n  \"history_id\": 18,\r\n  \"employee_id\": 18,\r\n  \"job_title\": \"Budget Analyst\",\r\n  \"department_id\": 4,\r\n  \"employment_type\": \"Full-time\",\r\n  \"start_date\": \"2019-09-01\",\r\n  \"end_date\": \"2025-09-30\",\r\n  \"employment_status\": \"Resigned\",\r\n  \"reporting_manager_id\": null,\r\n  \"location\": \"Municipal Budget Office\",\r\n  \"base_salary\": 42000.00,\r\n  \"allowances\": 3000.00,\r\n  \"bonuses\": 5000.00,\r\n  \"salary_adjustments\": 2000.00,\r\n  \"reason_for_change\": \"Resigned for career advancement opportunity abroad\",\r\n  \"promotions_transfers\": \"Promoted from Administrative Aide in 2021\",\r\n  \"duties_responsibilities\": \"Analyzed budget data and prepared financial reports for municipal operations.\",\r\n  \"performance_evaluations\": \"Consistently rated Outstanding. Received Best Employee Award 2023.\",\r\n  \"training_certifications\": \"Financial Planning Certification, Advanced Excel Training\",\r\n  \"contract_details\": \"Regular plantilla position\",\r\n  \"remarks\": \"Excellent employee. Provided comprehensive turnover documentation. Eligible for rehire.\",\r\n  \"created_at\": \"2019-09-01 02:00:00\",\r\n  \"updated_at\": \"2025-09-30 10:15:00\"\r\n}', 'Resignation', 'Employee resigned in good standing for overseas employment', 1, '2025-09-30 14:00:00', 1, NULL, NULL, 'Exit clearance completed. Certificate of Employment issued.', '2026-01-20 02:55:57', '2026-01-20 02:55:57');

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
  `late_minutes` decimal(5,2) DEFAULT 0.00,
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
-- Table structure for table `pds_data`
--

CREATE TABLE `pds_data` (
  `pds_id` int(11) NOT NULL,
  `candidate_id` int(11) NOT NULL,
  
  -- I. Personal Information
  `surname` varchar(100) DEFAULT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `name_extension` varchar(20) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `place_of_birth` varchar(255) DEFAULT NULL,
  `sex` enum('Male','Female') DEFAULT NULL,
  `civil_status` enum('Single','Married','Widowed','Separated') DEFAULT NULL,
  `height` decimal(5,2) DEFAULT NULL,
  `weight` decimal(5,2) DEFAULT NULL,
  `blood_type` varchar(10) DEFAULT NULL,
  
  -- Government IDs
  `gsis_id` varchar(50) DEFAULT NULL,
  `pagibig_id` varchar(50) DEFAULT NULL,
  `philhealth_no` varchar(50) DEFAULT NULL,
  `sss_no` varchar(50) DEFAULT NULL,
  `tin_no` varchar(50) DEFAULT NULL,
  `agency_employee_no` varchar(50) DEFAULT NULL,
  
  -- Citizenship
  `citizenship_type` varchar(50) DEFAULT NULL,
  `citizenship_country` varchar(100) DEFAULT NULL,
  
  -- Residential Address
  `residential_address` text DEFAULT NULL,
  `residential_subdivision` varchar(100) DEFAULT NULL,
  `residential_barangay` varchar(100) DEFAULT NULL,
  `residential_city` varchar(100) DEFAULT NULL,
  `residential_province` varchar(100) DEFAULT NULL,
  `residential_zipcode` varchar(20) DEFAULT NULL,
  
  -- Permanent Address
  `permanent_address` text DEFAULT NULL,
  `permanent_subdivision` varchar(100) DEFAULT NULL,
  `permanent_barangay` varchar(100) DEFAULT NULL,
  `permanent_city` varchar(100) DEFAULT NULL,
  `permanent_province` varchar(100) DEFAULT NULL,
  `permanent_zipcode` varchar(20) DEFAULT NULL,
  
  -- Contact Information
  `telephone` varchar(50) DEFAULT NULL,
  `mobile` varchar(50) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  
  -- II. Family Background
  `spouse_surname` varchar(100) DEFAULT NULL,
  `spouse_firstname` varchar(100) DEFAULT NULL,
  `spouse_middlename` varchar(100) DEFAULT NULL,
  `spouse_occupation` varchar(100) DEFAULT NULL,
  `spouse_employer` varchar(255) DEFAULT NULL,
  `spouse_business_address` text DEFAULT NULL,
  `spouse_telephone` varchar(50) DEFAULT NULL,
  
  `father_surname` varchar(100) DEFAULT NULL,
  `father_firstname` varchar(100) DEFAULT NULL,
  `father_middlename` varchar(100) DEFAULT NULL,
  
  `mother_surname` varchar(100) DEFAULT NULL,
  `mother_firstname` varchar(100) DEFAULT NULL,
  `mother_middlename` varchar(100) DEFAULT NULL,
  
  -- Children (stored as JSON array)
  `children` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`children`)),
  
  -- III. Educational Background (stored as JSON array)
  `education` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`education`)),
  
  -- IV. Civil Service Eligibility (stored as JSON array)
  `eligibility` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`eligibility`)),
  
  -- V. Work Experience (stored as JSON array)
  `work_experience` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`work_experience`)),
  
  -- VI. Voluntary Work (stored as JSON array)
  `voluntary_work` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`voluntary_work`)),
  
  -- VII. Learning and Development (stored as JSON array)
  `training` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`training`)),
  
  -- VIII. Other Information
  `special_skills` text DEFAULT NULL,
  `distinctions` text DEFAULT NULL,
  `memberships` text DEFAULT NULL,
  
  -- IX. Additional Application Info
  `current_position` varchar(150) DEFAULT NULL,
  `current_company` varchar(255) DEFAULT NULL,
  `notice_period` varchar(100) DEFAULT NULL,
  `expected_salary` decimal(10,2) DEFAULT NULL,
  `application_source` varchar(100) DEFAULT NULL,
  
  -- X. Character References (stored as JSON array)
  `references` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`references`)),
  
  -- File storage (PDF/JSON stored in database)
  `pds_file_blob` longblob DEFAULT NULL COMMENT 'PDF file content stored in database',
  `pds_file_name` varchar(255) DEFAULT NULL COMMENT 'Original filename',
  `pds_file_type` varchar(50) DEFAULT NULL COMMENT 'MIME type (application/pdf, application/json)',
  `pds_file_size` int(11) DEFAULT NULL COMMENT 'File size in bytes',
  `json_file_blob` longblob DEFAULT NULL COMMENT 'JSON file content stored in database',
  
  -- File paths (optional - for backward compatibility)
  `pds_file_path` varchar(255) DEFAULT NULL,
  `json_file_path` varchar(255) DEFAULT NULL,
  
  -- Metadata
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
(76, 26, 'Bookkeeping', 'Maintains accurate financial records and ledgers.', 'Technical', '2025-10-22 07:50:22', '2026-01-20 03:12:40'),
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
  `vacancy_limit` int(11) DEFAULT NULL COMMENT 'Maximum number of open job vacancies allowed for this department',
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
-- Table structure for table `educational_background`
--

CREATE TABLE `educational_background` (
  `education_id` int(11) NOT NULL AUTO_INCREMENT,
  `personal_info_id` int(11) NOT NULL,
  `education_level` enum('Elementary','High School','Vocational','Associate Degree','Bachelor''s Degree','Master''s Degree','Doctoral Degree','Other') NOT NULL,
  `school_name` varchar(150) NOT NULL,
  `course_degree` varchar(150) DEFAULT NULL COMMENT 'Course or degree program',
  `major_specialization` varchar(100) DEFAULT NULL,
  `year_started` year(4) DEFAULT NULL,
  `year_graduated` year(4) DEFAULT NULL,
  `honors_awards` varchar(255) DEFAULT NULL,
  `is_highest_attainment` tinyint(1) DEFAULT 0,
  `document_url` varchar(255) DEFAULT NULL COMMENT 'Diploma or certificate',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`education_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `educational_background`
--

INSERT INTO `educational_background` (`education_id`, `personal_info_id`, `education_level`, `school_name`, `course_degree`, `major_specialization`, `year_started`, `year_graduated`, `honors_awards`, `is_highest_attainment`, `document_url`, `created_at`, `updated_at`) VALUES
(1, 1, 'Bachelor\'s Degree', 'University of the Philippines', 'Bachelor of Science in Accountancy', 'Accountancy', '2003', '2007', 'Cum Laude', 1, '/documents/diplomas/maria_santos_bsa.pdf', '2026-01-20 02:55:57', '2026-01-20 02:55:57'),
(2, 2, 'Bachelor\'s Degree', 'De La Salle University', 'Bachelor of Science in Civil Engineering', 'Civil Engineering', '1996', '2000', NULL, 1, '/documents/diplomas/roberto_cruz_bsce.pdf', '2026-01-20 02:55:57', '2026-01-20 02:55:57'),
(3, 3, 'Bachelor\'s Degree', 'Far Eastern University', 'Bachelor of Science in Nursing', 'Nursing', '2006', '2010', NULL, 1, '/documents/diplomas/jennifer_reyes_bsn.pdf', '2026-01-20 02:55:57', '2026-01-20 02:55:57'),
(4, 4, 'Vocational', 'Technical Education and Skills Development Authority', 'Computer-Aided Design', 'CAD Operations', '1993', '1995', NULL, 1, '/documents/certificates/antonio_garcia_cad_cert.pdf', '2026-01-20 02:55:57', '2026-01-20 02:55:57'),
(5, 5, 'Master\'s Degree', 'University of Santo Tomas', 'Master of Social Work', 'Community Development', '2008', '2012', NULL, 1, '/documents/diplomas/lisa_mendoza_msw.pdf', '2026-01-20 02:55:57', '2026-01-20 02:55:57');

-- --------------------------------------------------------

--
-- Table structure for table `employee_certifications` (for personal/pre-employment certifications)
--

CREATE TABLE `employee_certifications` (
  `certification_id` int(11) NOT NULL AUTO_INCREMENT,
  `personal_info_id` int(11) NOT NULL,
  `certification_name` varchar(255) NOT NULL,
  `issuing_organization` varchar(255) NOT NULL,
  `issue_date` date NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `certification_number` varchar(100) DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `file_type` varchar(50) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `obtained_before_joining` tinyint(1) DEFAULT 1 COMMENT 'Whether this cert was obtained before joining the organization',
  `status` enum('Pending Verification','Verified','Expired','Inactive') DEFAULT 'Pending Verification',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`certification_id`),
  KEY `personal_info_id` (`personal_info_id`),
  CONSTRAINT `employee_certifications_personal_info_fk` FOREIGN KEY (`personal_info_id`) REFERENCES `personal_information` (`personal_info_id`) ON DELETE CASCADE
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
(2, 22, 3, 3, '2026-01-20', 'Nice Improvement', '2026-01-20 03:29:05', '2026-01-20 04:12:51'),
(2, 23, 3, 2, '2026-01-20', 'Attend Simminar And Training', '2026-01-20 03:29:05', '2026-01-20 04:12:51'),
(2, 24, 3, 4, '2026-01-20', 'Excellent', '2026-01-20 03:47:10', '2026-01-20 04:12:51'),
(7, 79, 3, 5, '2026-01-20', 'perfect', '2026-01-20 03:30:19', '2026-01-20 03:30:19'),
(7, 80, 3, 4, '2026-01-20', 'excellent', '2026-01-20 03:30:19', '2026-01-20 03:30:19'),
(11, 76, 3, 3, '2026-01-27', 'nice', '2026-01-27 02:03:46', '2026-01-27 02:03:46'),
(11, 77, 3, 3, '2026-01-27', 'amazing', '2026-01-27 02:03:46', '2026-01-27 02:03:46');

-- --------------------------------------------------------

--
-- Table structure for table `candidate_onboarding`
--

CREATE TABLE `candidate_onboarding` (
  `candidate_onboarding_id` int(11) NOT NULL AUTO_INCREMENT,
  `candidate_id` int(11) NOT NULL,
  `application_id` int(11) DEFAULT NULL,
  `start_date` date NOT NULL,
  `expected_completion_date` date NOT NULL,
  `status` enum('Pending','In Progress','Completed','Cancelled') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`candidate_onboarding_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `candidate_onboarding_tasks`
--

CREATE TABLE `candidate_onboarding_tasks` (
  `candidate_task_id` int(11) NOT NULL AUTO_INCREMENT,
  `candidate_onboarding_id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL,
  `due_date` date NOT NULL,
  `status` enum('Not Started','In Progress','Completed','Cancelled') DEFAULT 'Not Started',
  `completion_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`candidate_task_id`)
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
  `salary_grade_id` int(11) DEFAULT NULL COMMENT 'Foreign key to salary_grades table',
  `employee_number` varchar(20) NOT NULL,
  `hire_date` date NOT NULL,
  `employment_status` enum('Full-time','Part-time','Contract','Intern','Terminated') NOT NULL,
  `current_salary` decimal(10,2) DEFAULT NULL,
  `work_email` varchar(100) DEFAULT NULL,
  `work_phone` varchar(20) DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `remote_work` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  KEY `fk_salary_grade_id` (`salary_grade_id`),
  CONSTRAINT `fk_employee_salary_grade` FOREIGN KEY (`salary_grade_id`) REFERENCES `salary_grades` (`grade_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table employee_profiles
--

INSERT INTO employee_profiles (employee_id, personal_info_id, job_role_id, employee_number, hire_date, employment_status, current_salary, work_email, work_phone, location, remote_work, created_at, updated_at) VALUES
(1, 1, 4, 'MUN001', '2019-07-01', 'Full-time', 50000.00, 'maria.santos@municipality.gov.ph', '034-123-0001', 'City Hall - 1st Floor', 0, '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(2, 2, 8, 'MUN002', '2018-06-15', 'Full-time', 55000.00, 'roberto.cruz@municipality.gov.ph', '034-123-0002', 'Engineering Building', 0, '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(3, 3, 17, 'MUN003', '2020-01-20', 'Full-time', 42000.00, 'jennifer.reyes@municipality.gov.ph', '034-123-0003', 'Municipal Health Office', 0, '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(4, 4, 21, 'MUN004', '2019-03-10', 'Full-time', 33000.00, 'antonio.garcia@municipality.gov.ph', '034-123-0004', 'Municipal Engineer\'s Office', 0, '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(5, 5, 20, 'MUN005', '2021-09-05', 'Full-time', 40000.00, 'lisa.mendoza@municipality.gov.ph', '034-123-0005', 'Municipal Social Welfare & Development Office', 0, '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(6, 6, 25, 'MUN006', '2020-11-12', 'Full-time', 42000.00, 'michael.torres@municipality.gov.ph', '034-123-0006', 'Municipal Accountant\'s Office', 0, '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(7, 7, 27, 'MUN007', '2022-02-28', 'Full-time', 32000.00, 'carmen.delacruz@municipality.gov.ph', '034-123-0007', 'Municipal Civil Registrar\'s Office', 0, '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(8, 8, 32, 'MUN008', '2021-05-18', 'Full-time', 28000.00, 'ricardo.villanueva@municipality.gov.ph', '034-123-0008', 'General Services Office', 0, '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(9, 9, 28, 'MUN009', '2020-09-10', 'Full-time', 25000.00, 'sandra.pascual@municipality.gov.ph', '034-123-0009', 'Municipal Treasurer\'s Office', 0, '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(10, 10, 29, 'MUN010', '2019-12-01', 'Full-time', 28000.00, 'jose.ramos@municipality.gov.ph', '034-123-0010', 'Municipal Treasurer\'s Office', 0, '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(11, 11, 26, 'MUN011', '2022-04-15', 'Full-time', 30000.00, 'ana.morales@municipality.gov.ph', '034-123-0011', 'Municipal Human Resource & Administrative Office', 0, '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(12, 12, 19, 'MUN012', '2021-08-20', 'Full-time', 33000.00, 'pablo.fernandez@municipality.gov.ph', '034-123-0012', 'Municipal Agriculture Office', 0, '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(13, 13, 18, 'MUN013', '2020-06-30', 'Full-time', 40000.00, 'grace.lopez@municipality.gov.ph', '034-123-0013', 'Municipal Health Office', 0, '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(14, 14, 31, 'MUN014', '2022-01-10', 'Full-time', 30000.00, 'eduardo.hernandez@municipality.gov.ph', '034-123-0014', 'General Services Office', 0, '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(15, 15, 33, 'MUN015', '2021-11-05', 'Full-time', 25000.00, 'rosario.gonzales@municipality.gov.ph', '034-123-0015', 'General Services Office', 0, '2025-09-09 02:00:16', '2025-09-09 02:00:16');

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
  `salary_grade` varchar(50) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `employment_type` enum('Full-time','Part-time','Contractual','Project-based','Casual','Intern') NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `employment_status` enum('Active','Resigned','Terminated','Retired','End of Contract','Transferred','Promoted','Demoted','Lateral Move') NOT NULL,
  `reporting_manager_id` int(11) DEFAULT NULL,
  `location` varchar(150) DEFAULT NULL,
  `base_salary` decimal(10,2) NOT NULL,
  `allowances` decimal(10,2) DEFAULT 0.00,
  `bonuses` decimal(10,2) DEFAULT 0.00,
  `salary_adjustments` decimal(10,2) DEFAULT 0.00,
  `salary_effective_date` date DEFAULT NULL,
  `salary_increase_amount` decimal(10,2) DEFAULT 0.00,
  `salary_increase_percentage` decimal(5,2) DEFAULT 0.00,
  `previous_salary` decimal(10,2) DEFAULT NULL,
  `position_sequence` int(11) DEFAULT 1,
  `is_current_position` tinyint(1) DEFAULT 0,
  `promotion_type` enum('Initial Hire','Promotion','Demotion','Lateral Move','Rehire') DEFAULT NULL,
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

INSERT INTO `employment_history` (`history_id`, `employee_id`, `job_title`, `salary_grade`, `department_id`, `employment_type`, `start_date`, `end_date`, `employment_status`, `reporting_manager_id`, `location`, `base_salary`, `allowances`, `bonuses`, `salary_adjustments`, `salary_effective_date`, `salary_increase_amount`, `salary_increase_percentage`, `previous_salary`, `position_sequence`, `is_current_position`, `promotion_type`, `reason_for_change`, `promotions_transfers`, `duties_responsibilities`, `performance_evaluations`, `training_certifications`, `contract_details`, `remarks`, `created_at`, `updated_at`) VALUES
(1, 1, 'Municipal Treasurer', 'Grade 32', 3, 'Full-time', '2019-07-01', NULL, 'Active', NULL, 'City Hall - 1st Floor', 65000.00, 5000.00, 0.00, 0.00, '2019-07-01', 15000.00, 30.00, 50000.00, 2, 1, 'Promotion', 'Appointed as Municipal Treasurer', 'Promoted from Administrative Aide', 'Oversees treasury operations, municipal revenue collection, and financial management.', 'Consistently rated \"Excellent\" in financial audits', 'CPA Certification, Treasury Management Training', 'Appointed by Mayor, renewable 6-year term', 'Key finance official', '2025-09-09 02:00:16', '2026-02-23 05:30:00'),
(2, 2, 'Municipal Engineer', 'Grade 33', 7, 'Full-time', '2018-06-15', NULL, 'Active', NULL, 'Engineering Building', 75000.00, 6000.00, 0.00, 0.00, '2018-06-15', 20000.00, 36.36, 55000.00, 2, 1, 'Promotion', 'Appointed as Municipal Engineer', 'Promoted from CAD Operator', 'Supervises infrastructure projects, designs municipal roads and buildings.', 'Rated \"Very Satisfactory\" in infrastructure project completion', 'PRC Civil Engineer License, Project Management Certification', 'Appointed by Mayor, renewable 6-year term', 'Head of engineering department', '2025-09-09 02:00:16', '2026-02-23 05:30:00'),
(3, 3, 'Nurse', 'Grade 16', 9, 'Full-time', '2020-01-20', NULL, 'Active', 10, 'Municipal Health Office', 42000.00, 3000.00, 0.00, 0.00, '2020-01-20', 0.00, 0.00, 42000.00, 1, 1, 'Initial Hire', 'Hired as Nurse', NULL, 'Provides nursing care, assists doctors, administers vaccinations.', 'Highly commended during pandemic response', 'PRC Nursing License, Basic Life Support Training', 'Contract renewable every 3 years', 'Dedicated health staff', '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(4, 4, 'CAD Operator', 'Grade 14', 7, 'Full-time', '2019-03-10', NULL, 'Active', 2, 'Municipal Engineer\'s Office', 38000.00, 2000.00, 0.00, 0.00, '2019-03-10', 0.00, 0.00, 38000.00, 1, 1, 'Initial Hire', 'Hired as CAD Operator', NULL, 'Prepares AutoCAD drawings and engineering plans.', 'Satisfactory performance in multiple LGU projects', 'AutoCAD Certification', 'Fixed-term renewable contract', 'Key engineering support', '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(5, 5, 'Social Worker', 'Grade 17', 10, 'Full-time', '2021-09-05', NULL, 'Active', NULL, 'Municipal Social Welfare & Development Office', 45000.00, 3000.00, 0.00, 0.00, '2021-09-05', 10000.00, 28.57, 35000.00, 2, 1, 'Promotion', 'Hired as Social Worker', 'Promoted from Administrative Aide', 'Handles casework, provides assistance to indigent families.', 'Rated \"Very Good\" in community outreach', 'Social Work License, Community Development Training', 'Regular plantilla position', 'Handles social services cases', '2025-09-09 02:00:16', '2026-02-23 05:30:00'),
(6, 6, 'Accounting Staff', 'Grade 12', 5, 'Full-time', '2020-11-12', NULL, 'Active', NULL, 'Municipal Accountant\'s Office', 28000.00, 1500.00, 0.00, 0.00, '2020-11-12', 0.00, 0.00, 28000.00, 1, 1, 'Initial Hire', 'Hired as Accounting Staff', NULL, 'Processes vouchers, prepares reports, assists in bookkeeping.', 'Satisfactory audit reviews', 'Bookkeeping Certification', 'Regular plantilla position', 'Junior accounting role', '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(7, 7, 'Clerk', 'Grade 10', 8, 'Full-time', '2022-02-28', NULL, 'Active', NULL, 'Municipal Civil Registrar\'s Office', 30000.00, 1000.00, 0.00, 0.00, '2022-02-28', 0.00, 0.00, 30000.00, 1, 1, 'Initial Hire', 'Hired as Clerk', NULL, 'Maintains registry records, assists clients with civil documents.', 'Rated \"Good\" by supervisor', 'Civil Registration Training', 'Contract renewable every 2 years', 'Support staff', '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(8, 8, 'Maintenance Worker', 'Grade 11', 15, 'Full-time', '2021-05-18', NULL, 'Active', NULL, 'General Services Office', 22000.00, 1000.00, 0.00, 0.00, '2021-05-18', 0.00, 0.00, 22000.00, 1, 1, 'Initial Hire', 'Hired as Maintenance Worker', NULL, 'Performs facility maintenance and minor repairs.', 'Satisfactory in safety inspections', 'Electrical Safety Training', 'Casual employment converted to regular', 'Assigned to city hall facilities', '2025-09-09 02:00:16', '2025-09-09 02:00:16'),
(9, 9, 'Cashier', 'Grade 13', 3, 'Full-time', '2020-09-10', NULL, 'Active', 1, 'Municipal Treasurer\'s Office', 32000.00, 2000.00, 0.00, 0.00, '2020-09-10', 8000.00, 33.33, 24000.00, 2, 1, 'Promotion', 'Hired as Cashier', 'Promoted from Clerk', 'Handles cash collection, prepares daily receipts.', 'Commended for accurate handling of cash', 'Financial Management Training', 'Regular plantilla position', 'Treasury office staff', '2025-09-09 02:00:16', '2026-02-23 05:30:00'),
(10, 10, 'Collection Officer', 'Grade 15', 3, 'Full-time', '2019-12-01', NULL, 'Active', 1, 'Municipal Treasurer\'s Office', 35000.00, 2000.00, 0.00, 0.00, '2019-12-01', 10000.00, 40.00, 25000.00, 2, 1, 'Promotion', 'Hired as Collection Officer', 'Promoted from Clerk', 'Collects taxes and fees, manages accounts receivables.', 'Rated \"Very Good\" in collection efficiency', 'Revenue Collection Procedures Training', 'Regular plantilla position', 'Handles revenue collection', '2025-09-09 02:00:16', '2026-02-23 05:30:00'),
(11, 1, 'Administrative Aide', 'Grade 8', 13, 'Full-time', '2017-03-01', '2019-06-30', 'Promoted', NULL, 'City Hall - 2nd Floor', 25000.00, 1000.00, 0.00, 0.00, '2017-03-01', 0.00, 0.00, NULL, 1, 0, 'Initial Hire', 'Started as Administrative Aide', 'Later promoted to Treasurer', 'Clerical and administrative support tasks.', 'Rated \"Good\"', NULL, 'Fixed-term appointment', 'Entry-level HR support', '2025-09-09 02:00:16', '2026-02-23 05:30:00'),
(12, 2, 'CAD Operator', 'Grade 14', 7, 'Full-time', '2015-08-01', '2018-06-14', 'Promoted', NULL, 'Engineering Building', 32000.00, 1500.00, 0.00, 0.00, '2015-08-01', 0.00, 0.00, NULL, 1, 0, 'Initial Hire', 'Started as CAD Operator', 'Later promoted to Municipal Engineer', 'Drafting technical drawings.', 'Rated \"Good\"', 'AutoCAD Certification', 'Contract ended due to promotion', 'Junior engineering support', '2025-09-09 02:00:16', '2026-02-23 05:30:00'),
(13, 5, 'Administrative Aide', 'Grade 8', 13, 'Full-time', '2019-01-15', '2021-09-04', 'Promoted', NULL, 'City Hall - 2nd Floor', 25000.00, 1000.00, 0.00, 0.00, '2019-01-15', 0.00, 0.00, NULL, 1, 0, 'Initial Hire', 'Started as Administrative Aide', 'Later promoted to Social Worker', 'Handled clerical support for social welfare programs.', 'Rated \"Good\"', NULL, 'Casual contract converted to plantilla', 'Support role before promotion', '2025-09-09 02:00:16', '2026-02-23 05:30:00'),
(14, 9, 'Clerk', 'Grade 9', 8, 'Full-time', '2018-05-01', '2020-09-09', 'Promoted', NULL, 'Municipal Civil Registrar\'s Office', 22000.00, 500.00, 0.00, 0.00, '2018-05-01', 0.00, 0.00, NULL, 1, 0, 'Initial Hire', 'Started as Clerk', 'Later promoted to Cashier', 'Maintained registry documents, clerical tasks.', 'Rated \"Good\"', NULL, 'Contract ended due to transfer', 'Civil registrar support', '2025-09-09 02:00:16', '2026-02-23 05:30:00'),
(15, 10, 'Clerk', 'Grade 9', 8, 'Full-time', '2017-10-01', '2019-11-30', 'Promoted', NULL, 'Municipal Civil Registrar\'s Office', 20000.00, 500.00, 0.00, 0.00, '2017-10-01', 0.00, 0.00, NULL, 1, 0, 'Initial Hire', 'Started as Clerk', 'Later promoted to Collection Officer', 'Clerical tasks, processing records.', 'Rated \"Satisfactory\"', NULL, 'Contract ended due to promotion', 'Civil registrar support role', '2025-09-09 02:00:16', '2026-02-23 05:30:00');

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
  `item_type` enum('Physical','Document','Access','Financial','Other') DEFAULT 'Other',
  `serial_number` varchar(100) DEFAULT NULL,
  `sticker_type` varchar(100) DEFAULT NULL,
  `approval_status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `approved_by` varchar(100) DEFAULT NULL,
  `approved_date` date DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `clearance_status` enum('Pending','Cleared','Conditional') DEFAULT 'Pending',
  `clearance_date` date DEFAULT NULL,
  `cleared_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `exit_checklist_approvals`
--

CREATE TABLE `exit_checklist_approvals` (
  `approval_id` int(11) NOT NULL,
  `checklist_id` int(11) NOT NULL,
  `approver_id` varchar(100) NOT NULL,
  `approver_name` varchar(255) NOT NULL,
  `approval_level` int(11) DEFAULT 1,
  `decision` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `decision_date` datetime DEFAULT NULL,
  `decision_remarks` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `exit_checklist_audit`
--

CREATE TABLE `exit_checklist_audit` (
  `audit_id` int(11) NOT NULL,
  `checklist_id` int(11) NOT NULL,
  `action_type` enum('Created','Updated','Deleted','Approved','Rejected','Cleared') NOT NULL,
  `field_changed` varchar(100) DEFAULT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `changed_by` varchar(100) DEFAULT NULL,
  `changed_date` datetime DEFAULT current_timestamp(),
  `remarks` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `exit_clearance_summary`
-- (See below for the actual view)
--
CREATE TABLE `exit_clearance_summary` (
`exit_id` int(11)
,`employee_id` int(11)
,`employee_name` varchar(101)
,`employee_number` varchar(20)
,`exit_date` date
,`total_items` bigint(21)
,`completed_items` decimal(22,0)
,`approved_items` decimal(22,0)
,`cleared_items` decimal(22,0)
,`overall_clearance_status` varchar(17)
);

-- --------------------------------------------------------

--
-- Table structure for table `exit_clearance_tracking`
--

CREATE TABLE `exit_clearance_tracking` (
  `clearance_id` int(11) NOT NULL,
  `exit_id` int(11) NOT NULL,
  `department` varchar(100) NOT NULL,
  `clearance_officer` varchar(100) DEFAULT NULL,
  `clearance_status` enum('Pending','Cleared','Conditional','Not Required') DEFAULT 'Pending',
  `items_cleared` text DEFAULT NULL,
  `conditions` text DEFAULT NULL,
  `cleared_date` date DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
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
-- Table structure for table `exit_physical_items`
--

CREATE TABLE `exit_physical_items` (
  `item_id` int(11) NOT NULL,
  `checklist_id` int(11) NOT NULL,
  `item_category` varchar(100) NOT NULL,
  `item_description` text DEFAULT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `sticker_code` varchar(100) DEFAULT NULL,
  `asset_tag` varchar(100) DEFAULT NULL,
  `condition_on_return` enum('Good','Fair','Poor','Damaged','Missing') DEFAULT 'Good',
  `return_date` date DEFAULT NULL,
  `received_by` varchar(100) DEFAULT NULL,
  `verification_status` enum('Pending','Verified','Discrepancy') DEFAULT 'Pending',
  `verification_remarks` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `feedback_cycles`
--

CREATE TABLE `feedback_cycles` (
  `cycle_id` int(11) NOT NULL,
  `cycle_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('Active','Draft','Completed','Cancelled') DEFAULT 'Draft',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `feedback_cycles`
--

INSERT INTO `feedback_cycles` (`cycle_id`, `cycle_name`, `description`, `start_date`, `end_date`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(2, 'Yearly Evaluation', 'you', '2026-01-27', '2026-02-21', 'Active', 2, '2026-01-27 05:33:15', '2026-01-27 05:33:15');

-- --------------------------------------------------------

--
-- Table structure for table `feedback_requests`
--

CREATE TABLE `feedback_requests` (
  `request_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `reviewer_id` int(11) NOT NULL,
  `cycle_id` int(11) NOT NULL,
  `relationship_type` enum('supervisor','peer','subordinate','self') NOT NULL,
  `status` enum('Pending','Completed','Cancelled') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `feedback_responses`
--

CREATE TABLE `feedback_responses` (
  `response_id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `reviewer_id` int(11) NOT NULL,
  `responses` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `comments` text DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp()
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
-- Table structure for table `offer_letters`
--

CREATE TABLE `offer_letters` (
  `letter_id` int(11) NOT NULL,
  `offer_id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `letter_content` text NOT NULL,
  `status` enum('Draft','Sent','Accepted','Declined') DEFAULT 'Draft',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL
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
  `screening_level` enum('Easy','Moderate','Strict') DEFAULT 'Moderate' COMMENT 'AI screening difficulty level',
  `ai_generated` tinyint(1) DEFAULT 0 COMMENT 'Flag if job was created by AI',
  `created_by` int(11) DEFAULT NULL COMMENT 'User ID who created the job',
  `approval_status` enum('Pending','Approved','Rejected') DEFAULT NULL COMMENT 'Approval status for AI-generated jobs',
  `approved_by` int(11) DEFAULT NULL COMMENT 'User ID who approved/rejected the job',
  `approved_at` datetime DEFAULT NULL COMMENT 'Timestamp of approval/rejection',
  `rejection_reason` text DEFAULT NULL COMMENT 'Reason if job was rejected',
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
(1, 'Vacation Leave', 'Annual vacation leave (RA 10911: 15 days minimum)', 1, 15.00, 1, 5.00, '2025-09-14 07:13:35', '2025-09-14 07:13:35'),
(2, 'Sick Leave', 'Medical leave for illness (RA 10911: 15 days minimum)', 1, 15.00, 1, 5.00, '2025-09-14 07:13:35', '2025-09-14 07:13:35'),
(3, 'Maternity Leave', 'Leave for new mothers (RA 11210: 120 days)', 1, 120.00, 0, 0.00, '2025-09-14 07:13:35', '2025-09-14 07:13:35'),
(4, 'Paternity Leave', 'Leave for new fathers (RA 11165: 7-14 days; 14 for solo parents)', 1, 7.00, 0, 0.00, '2025-09-14 07:13:35', '2025-09-14 07:13:35'),
(5, 'Emergency Leave', 'Unplanned emergency leave', 0, 5.00, 0, 0.00, '2025-09-14 07:13:35', '2025-09-14 07:13:35'),
(6, 'Solo Parent Leave', 'Additional leave for solo parents (RA 9403: 5 days)', 1, 5.00, 0, 0.00, '2025-09-14 07:13:35', '2025-09-14 07:13:35'),
(7, 'Menstrual Disorder Leave', 'Leave for menstrual disorder symptoms (RA 11058: up to 3 days annually)', 1, 3.00, 0, 0.00, '2025-09-14 07:13:35', '2025-09-14 07:13:35');

-- --------------------------------------------------------

--
-- Table structure for table `marital_status_history`
--

CREATE TABLE `marital_status_history` (
  `status_history_id` int(11) NOT NULL,
  `personal_info_id` int(11) NOT NULL,
  `marital_status` enum('Single','Married','Divorced','Widowed','Separated','Annulled') NOT NULL,
  `status_date` date NOT NULL COMMENT 'Date of marriage, divorce, etc.',
  `spouse_name` varchar(100) DEFAULT NULL,
  `supporting_document_type` enum('Marriage Certificate','Divorce Decree','Death Certificate','Annulment Certificate','Separation Agreement') DEFAULT NULL,
  `document_url` varchar(255) DEFAULT NULL,
  `document_number` varchar(50) DEFAULT NULL COMMENT 'Certificate or decree number',
  `issuing_authority` varchar(150) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `is_current` tinyint(1) DEFAULT 1 COMMENT '1 = current status, 0 = historical',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `marital_status_history`
--

INSERT INTO `marital_status_history` (`status_history_id`, `personal_info_id`, `marital_status`, `status_date`, `spouse_name`, `supporting_document_type`, `document_url`, `document_number`, `issuing_authority`, `remarks`, `is_current`, `created_at`, `updated_at`) VALUES
(1, 1, 'Married', '2012-05-15', 'Carlos Santos', 'Marriage Certificate', '/documents/marital/maria_santos_marriage_cert.pdf', 'MC-2012-05-001234', 'Manila City Civil Registrar', NULL, 1, '2026-01-20 02:55:57', '2026-01-20 02:55:57'),
(2, 2, 'Married', '2005-11-20', 'Elena Cruz', 'Marriage Certificate', '/documents/marital/roberto_cruz_marriage_cert.pdf', 'MC-2005-11-005678', 'Quezon City Civil Registrar', NULL, 1, '2026-01-20 02:55:57', '2026-01-20 02:55:57'),
(3, 4, 'Married', '2001-03-10', 'Rosa Garcia', 'Marriage Certificate', '/documents/marital/antonio_garcia_marriage_cert.pdf', 'MC-2001-03-009012', 'Makati City Civil Registrar', NULL, 1, '2026-01-20 02:55:57', '2026-01-20 02:55:57'),
(4, 5, 'Divorced', '2018-08-22', 'John Mendoza', 'Divorce Decree', '/documents/marital/lisa_mendoza_divorce_decree.pdf', 'DD-2018-08-003456', 'Family Court Manila', NULL, 1, '2026-01-20 02:55:57', '2026-01-20 02:55:57'),
(5, 6, 'Married', '2008-07-14', 'Anna Torres', 'Marriage Certificate', '/documents/marital/michael_torres_marriage_cert.pdf', 'MC-2008-07-007890', 'Pasig City Civil Registrar', NULL, 1, '2026-01-20 02:55:57', '2026-01-20 02:55:57');

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
(3, 'Monthly Evaluation', '2025-10-01', '2025-10-31', '', '2025-10-21 12:47:53', '2025-10-21 12:47:53'),
(4, 'Yearly Evaluation', '2026-01-01', '2026-12-31', 'In Progress', '2026-01-20 03:48:27', '2026-01-20 03:48:52');

-- --------------------------------------------------------

--

--
--
-- Modified personal_information table with marital status details

CREATE TABLE `personal_information` (
  `personal_info_id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `date_of_birth` date NOT NULL,
  `gender` enum('Male','Female','Non-binary','Prefer not to say') NOT NULL,
  `marital_status` enum('Single','Married','Divorced','Widowed') NOT NULL,
  `marital_status_date` date DEFAULT NULL,
  `spouse_name` varchar(100) DEFAULT NULL,
  `marital_status_document` varchar(255) DEFAULT NULL,
  `document_type` varchar(50) DEFAULT NULL,
  `document_number` varchar(50) DEFAULT NULL,
  `issuing_authority` varchar(150) DEFAULT NULL,
  `nationality` varchar(50) NOT NULL,
  `tax_id` varchar(20) DEFAULT NULL,
  `gsis_id` varchar(20) DEFAULT NULL COMMENT 'Government Service Insurance System ID',
  `pag_ibig_id` varchar(20) DEFAULT NULL,
  `philhealth_id` varchar(20) DEFAULT NULL,
  `phone_number` varchar(20) NOT NULL,
  `emergency_contact_name` varchar(100) DEFAULT NULL,
  `emergency_contact_relationship` varchar(50) DEFAULT NULL,
  `emergency_contact_phone` varchar(20) DEFAULT NULL,
  `highest_education_level` enum('Elementary','High School','Vocational/Technical','Associate Degree','Bachelor''s Degree','Master''s Degree','Doctorate','Other') DEFAULT NULL,
  `field_of_study` varchar(100) DEFAULT NULL,
  `institution_name` varchar(150) DEFAULT NULL,
  `graduation_year` year DEFAULT NULL,
  `previous_job_experiences` longtext DEFAULT NULL COMMENT 'JSON array containing previous employment history before joining this organization',
  `certifications` text DEFAULT NULL,
  `additional_training` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`personal_info_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `personal_information` (`personal_info_id`, `first_name`, `last_name`, `date_of_birth`, `gender`, `marital_status`, `marital_status_date`, `spouse_name`, `marital_status_document`, `document_type`, `document_number`, `issuing_authority`, `nationality`, `tax_id`, `gsis_id`, `pag_ibig_id`, `philhealth_id`, `phone_number`, `emergency_contact_name`, `emergency_contact_relationship`, `emergency_contact_phone`, `highest_education_level`, `field_of_study`, `institution_name`, `graduation_year`, `previous_job_experiences`, `certifications`, `additional_training`, `created_at`, `updated_at`) VALUES
(1, 'Maria', 'Santos', '1985-03-12', 'Female', 'Married', '2010-06-15', 'Carlos Santos', 'Marriage Certificate of Maria Reyes and Carlos Santos', 'Marriage Certificate', 'MC-2010-04321', 'Philippine Statistics Authority (PSA)', 'Filipino', '123-45-6789', '123456789', NULL, NULL, '0917-123-4567', 'Carlos Santos', 'Spouse', '0917-567-8901', 'Bachelor''s Degree', 'Business Administration', 'University of the Philippines', 2007, NULL, 'Certified Public Accountant (CPA)', 'Advanced Excel Training, Leadership Workshop', '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(2, 'Roberto', 'Cruz', '1978-07-20', 'Male', 'Married', '2005-11-20', 'Elena Cruz', 'Marriage Certificate of Roberto Cruz and Elena Reyes', 'Marriage Certificate', 'MC-2005-08876', 'Philippine Statistics Authority (PSA)', 'Filipino', '234-56-7890', '234567890', NULL, NULL, '0917-234-5678', 'Elena Cruz', 'Spouse', '0917-678-9012', 'Master''s Degree', 'Information Technology', 'Ateneo de Manila University', 2002, NULL, 'Project Management Professional (PMP), ITIL Foundation', 'Agile Scrum Master Training', '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(3, 'Jennifer', 'Reyes', '1988-11-08', 'Female', 'Single', NULL, NULL, NULL, 'None', NULL, NULL, 'Filipino', '345-67-8901', '345678901', NULL, NULL, '0917-345-6789', 'Mark Reyes', 'Brother', '0917-789-0123', 'Bachelor''s Degree', 'Marketing', 'De La Salle University', 2010, NULL, 'Google Analytics Certification, Digital Marketing Certificate', 'Social Media Marketing Bootcamp', '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(4, 'Antonio', 'Garcia', '1975-01-25', 'Male', 'Divorced', '2015-03-10', 'Rosa Garcia', 'Decree of Legal Separation of Antonio and Rosa Garcia', 'Divorce Decree', 'DD-2015-00234', 'Regional Trial Court – Branch 45, Quezon City', 'Filipino', '456-78-9012', '456789012', NULL, NULL, '0917-456-7890', 'Rosa Garcia', 'Spouse', '0917-890-1234', 'Vocational/Technical', 'Automotive Technology', 'Technical Education and Skills Development Authority (TESDA)', 1995, NULL, 'NC II Automotive Servicing, Welding NC II', 'Heavy Equipment Operation Training', '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(5, 'Lisa', 'Mendoza', '1982-09-14', 'Female', 'Widowed', '2019-07-22', 'John Mendoza', 'Death Certificate of John Mendoza', 'Death Certificate', 'DC-2019-06789', 'Philippine Statistics Authority (PSA)', 'Filipino', '567-89-0123', '567890123', NULL, NULL, '0917-567-8901', 'John Mendoza', 'Father', '0917-901-2345', 'Bachelor''s Degree', 'Nursing', 'University of Santo Tomas', 2004, NULL, 'Registered Nurse (RN), Basic Life Support (BLS)', 'Intensive Care Unit (ICU) Specialized Training', '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(6, 'Michael', 'Torres', '1980-06-03', 'Male', 'Married', '2007-04-14', 'Anna Torres', 'Marriage Certificate of Michael Torres and Anna Reyes', 'Marriage Certificate', 'MC-2007-03345', 'Philippine Statistics Authority (PSA)', 'Filipino', '678-90-1234', '678901234', NULL, NULL, '0917-678-9012', 'Anna Torres', 'Spouse', '0917-012-3456', 'Bachelor''s Degree', 'Civil Engineering', 'Mapua University', 2002, NULL, 'Licensed Civil Engineer, LEED Green Associate', 'Construction Management Seminar', '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(7, 'Carmen', 'Dela Cruz', '1987-12-18', 'Female', 'Single', NULL, NULL, NULL, 'None', NULL, NULL, 'Filipino', '789-01-2345', '789012345', NULL, NULL, '0917-789-0123', 'Pedro Dela Cruz', 'Father', '0917-123-4567', 'Bachelor''s Degree', 'Education', 'Philippine Normal University', 2009, NULL, 'Licensed Professional Teacher (LPT)', 'Child Psychology Training, Montessori Method Workshop', '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(8, 'Ricardo', 'Villanueva', '1970-04-07', 'Male', 'Married', '1995-08-20', 'Diana Villanueva', 'Marriage Certificate of Ricardo Villanueva and Diana Santos', 'Marriage Certificate', 'MC-1995-01123', 'Philippine Statistics Authority (PSA)', 'Filipino', '890-12-3456', '890123456', NULL, NULL, '0917-890-1234', 'Diana Villanueva', 'Spouse', '0917-234-5678', 'High School', NULL, 'San Juan National High School', 1988, NULL, 'Sales Excellence Certificate', 'Customer Service Training, Product Knowledge Seminars', '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(9, 'Sandra', 'Pascual', '1984-08-29', 'Female', 'Married', '2009-02-14', 'Luis Pascual', 'Marriage Certificate of Sandra Reyes and Luis Pascual', 'Marriage Certificate', 'MC-2009-05567', 'Philippine Statistics Authority (PSA)', 'Filipino', '901-23-4567', '901234567', NULL, NULL, '0917-901-2345', 'Luis Pascual', 'Spouse', '0917-345-6789', 'Master''s Degree', 'Human Resource Management', 'Asian Institute of Management', 2008, NULL, 'SHRM-CP, Certified Compensation Professional', 'Organizational Development Training', '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(10, 'Jose', 'Ramos', '1972-05-15', 'Male', 'Married', '1998-12-01', 'Teresa Ramos', 'Marriage Certificate of Jose Ramos and Teresa Cruz', 'Marriage Certificate', 'MC-1998-02234', 'Philippine Statistics Authority (PSA)', 'Filipino', '012-34-5678', '012345678', NULL, NULL, '0917-012-3456', 'Teresa Ramos', 'Spouse', '0917-456-7890', 'Bachelor''s Degree', 'Electrical Engineering', 'Polytechnic University of the Philippines', 1994, NULL, 'Licensed Electrical Engineer', 'Power Systems Analysis Training', '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(11, 'Ana', 'Morales', '1986-10-30', 'Female', 'Single', NULL, NULL, NULL, 'None', NULL, NULL, 'Filipino', '123-56-7890', '123567890', NULL, NULL, '0917-135-7890', 'Maria Morales', 'Mother', '0917-579-0123', 'Bachelor''s Degree', 'Psychology', 'University of the Philippines', 2008, NULL, 'Licensed Psychologist, Certified Career Coach', 'Cognitive Behavioral Therapy Workshop', '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(12, 'Pablo', 'Fernandez', '1979-02-22', 'Male', 'Married', '2003-05-17', 'Carmen Fernandez', 'Marriage Certificate of Pablo Fernandez and Carmen Dela Cruz', 'Marriage Certificate', 'MC-2003-06678', 'Philippine Statistics Authority (PSA)', 'Filipino', '234-67-8901', '234678901', NULL, NULL, '0917-246-7890', 'Carmen Fernandez', 'Spouse', '0917-680-1234', 'Vocational/Technical', 'Computer Technology', 'TESDA', 1998, NULL, 'Computer Systems Servicing NC II', 'Web Development Bootcamp, Network Administration', '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(13, 'Grace', 'Lopez', '1983-09-07', 'Female', 'Married', '2008-01-19', 'David Lopez', 'Marriage Certificate of Grace Santos and David Lopez', 'Marriage Certificate', 'MC-2008-04456', 'Philippine Statistics Authority (PSA)', 'Filipino', '345-78-9012', '345789012', NULL, NULL, '0917-357-8901', 'David Lopez', 'Spouse', '0917-791-2345', 'Bachelor''s Degree', 'Accountancy', 'Far Eastern University', 2005, NULL, 'Certified Public Accountant (CPA), Certified Internal Auditor', 'Tax Planning and Management Seminar', '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(14, 'Eduardo', 'Hernandez', '1977-12-03', 'Male', 'Married', '2004-09-25', 'Sofia Hernandez', 'Marriage Certificate of Eduardo Hernandez and Sofia Reyes', 'Marriage Certificate', 'MC-2004-07789', 'Philippine Statistics Authority (PSA)', 'Filipino', '456-89-0123', '456890123', NULL, NULL, '0917-468-9012', 'Sofia Hernandez', 'Spouse', '0917-802-3456', 'Bachelor''s Degree', 'Architecture', 'University of Santo Tomas', 2000, NULL, 'Licensed Architect', 'Sustainable Design Workshop, BIM Training', '2025-09-09 02:00:15', '2025-09-09 02:00:15'),
(15, 'Rosario', 'Gonzales', '1989-06-28', 'Female', 'Single', NULL, NULL, NULL, 'None', NULL, NULL, 'Filipino', '567-90-1234', '567901234', NULL, NULL, '0917-579-0123', 'Miguel Gonzales', 'Father', '0917-913-4567', 'Bachelor''s Degree', 'Communication Arts', 'University of the Philippines', 2011, NULL, 'Certified Digital Content Creator', 'Video Production Workshop, Social Media Strategy Training', '2025-09-09 02:00:15', '2025-09-09 02:00:15');


-- --------------------------------------------------------

--
-- Table structure for table `post_exit_surveys`
--

CREATE TABLE `post_exit_surveys` (
  `survey_id` int(11) NOT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `exit_id` int(11) NOT NULL,
  `survey_date` date NOT NULL,
  `survey_response` text DEFAULT NULL,
  `satisfaction_rating` int(11) DEFAULT NULL,
  `submitted_date` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_anonymous` tinyint(1) NOT NULL DEFAULT 0,
  `evaluation_score` int(11) DEFAULT 0,
  `evaluation_criteria` text DEFAULT NULL
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
CREATE TABLE training_courses (
  course_id int(11) NOT NULL,
  course_name varchar(255) NOT NULL,
  description text DEFAULT NULL,
  category varchar(100) DEFAULT NULL,
  delivery_method enum('Classroom Training','Online Learning','Blended Learning','Workshop','Seminar','Webinar','Self-Paced','On-the-Job Training') NOT NULL,
  duration int(11) DEFAULT NULL COMMENT 'Duration in hours',
  max_participants int(11) DEFAULT NULL,
  prerequisites text DEFAULT NULL,
  materials_url varchar(255) DEFAULT NULL,
  status enum('Active','Inactive','In Development') DEFAULT 'Active',
  created_at timestamp NOT NULL DEFAULT current_timestamp(),
  updated_at timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
-- Learning & Development (L&D) - Certifications, Assignments, Feedback
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

-- --------------------------------------------------------

--
-- Indexes for dumped tables
--

--
-- Indexes for table `archive_storage`
--
ALTER TABLE `archive_storage`
  ADD PRIMARY KEY (`archive_id`),
  ADD KEY `source_table` (`source_table`),
  ADD KEY `record_id` (`record_id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `archived_by` (`archived_by`),
  ADD KEY `archived_at` (`archived_at`),
  ADD KEY `archive_storage_ibfk_2` (`restored_by`);

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
-- Indexes for table `pds_data`
--
ALTER TABLE `pds_data`
  ADD PRIMARY KEY (`pds_id`),
  ADD KEY `idx_candidate` (`candidate_id`),
  ADD KEY `idx_email` (`email`);

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
-- Indexes for table `educational_background`
--
ALTER TABLE `educational_background`
  ADD KEY `personal_info_id` (`personal_info_id`);

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
  ADD KEY `exit_id` (`exit_id`),
  ADD KEY `idx_approval_status` (`approval_status`),
  ADD KEY `idx_clearance_status` (`clearance_status`),
  ADD KEY `idx_item_type` (`item_type`),
  ADD KEY `idx_serial_number` (`serial_number`);

--
-- Indexes for table `exit_checklist_approvals`
--
ALTER TABLE `exit_checklist_approvals`
  ADD PRIMARY KEY (`approval_id`),
  ADD KEY `checklist_id` (`checklist_id`);

--
-- Indexes for table `exit_checklist_audit`
--
ALTER TABLE `exit_checklist_audit`
  ADD PRIMARY KEY (`audit_id`),
  ADD KEY `checklist_id` (`checklist_id`);

--
-- Indexes for table `exit_clearance_tracking`
--
ALTER TABLE `exit_clearance_tracking`
  ADD PRIMARY KEY (`clearance_id`),
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
-- Indexes for table `exit_physical_items`
--
ALTER TABLE `exit_physical_items`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `checklist_id` (`checklist_id`);

--
-- Indexes for table `feedback_cycles`
--
ALTER TABLE `feedback_cycles`
  ADD PRIMARY KEY (`cycle_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `feedback_requests`
--
ALTER TABLE `feedback_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `reviewer_id` (`reviewer_id`),
  ADD KEY `cycle_id` (`cycle_id`);

--
-- Indexes for table `feedback_responses`
--
ALTER TABLE `feedback_responses`
  ADD PRIMARY KEY (`response_id`),
  ADD KEY `request_id` (`request_id`),
  ADD KEY `reviewer_id` (`reviewer_id`);

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
-- Indexes for table `offer_letters`
--
ALTER TABLE `offer_letters`
  ADD PRIMARY KEY (`letter_id`),
  ADD UNIQUE KEY `unique_offer` (`offer_id`),
  ADD KEY `application_id` (`application_id`);

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
-- Indexes for table `marital_status_history`
--
ALTER TABLE `marital_status_history`
  ADD PRIMARY KEY (`status_history_id`),
  ADD KEY `personal_info_id` (`personal_info_id`);

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
-- Indexes for table `certifications`
--
ALTER TABLE `certifications`
  ADD PRIMARY KEY (`certification_id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `skill_id` (`skill_id`);

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
-- AUTO_INCREMENT for table `archive_storage`
--
ALTER TABLE `archive_storage`
  MODIFY `archive_id` int(11) NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT for table `pds_data`
--
ALTER TABLE `pds_data`
  MODIFY `pds_id` int(11) NOT NULL AUTO_INCREMENT;

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
  MODIFY `activity_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `development_plans`
--
ALTER TABLE `development_plans`
  MODIFY `plan_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

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
-- AUTO_INCREMENT for table `exit_checklist_approvals`
--
ALTER TABLE `exit_checklist_approvals`
  MODIFY `approval_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `exit_checklist_audit`
--
ALTER TABLE `exit_checklist_audit`
  MODIFY `audit_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `exit_clearance_tracking`
--
ALTER TABLE `exit_clearance_tracking`
  MODIFY `clearance_id` int(11) NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT for table `exit_physical_items`
--
ALTER TABLE `exit_physical_items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `feedback_cycles`
--
ALTER TABLE `feedback_cycles`
  MODIFY `cycle_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `feedback_requests`
--
ALTER TABLE `feedback_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `feedback_responses`
--
ALTER TABLE `feedback_responses`
  MODIFY `response_id` int(11) NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT for table `offer_letters`
--
ALTER TABLE `offer_letters`
  MODIFY `letter_id` int(11) NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT for table `marital_status_history`
--
ALTER TABLE `marital_status_history`
  MODIFY `status_history_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

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
  MODIFY `cycle_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
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
-- AUTO_INCREMENT for table `certifications`
--
ALTER TABLE `certifications`
  MODIFY `certification_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employee_assignments`
--
ALTER TABLE `employee_assignments`
  MODIFY `assignment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `training_feedback`
--
ALTER TABLE `training_feedback`
  MODIFY `feedback_id` int(11) NOT NULL AUTO_INCREMENT;

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
-- Constraints for table `archive_storage`
--
ALTER TABLE `archive_storage`
  ADD CONSTRAINT `archive_storage_ibfk_1` FOREIGN KEY (`archived_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `archive_storage_ibfk_2` FOREIGN KEY (`restored_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

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
-- Constraints for table `pds_data`
--
ALTER TABLE `pds_data`
  ADD CONSTRAINT `pds_data_ibfk_1` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`candidate_id`) ON DELETE CASCADE;

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
-- Constraints for table `educational_background`
--
ALTER TABLE `educational_background`
  ADD CONSTRAINT `educational_background_ibfk_1` FOREIGN KEY (`personal_info_id`) REFERENCES `personal_information` (`personal_info_id`) ON DELETE CASCADE;

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
-- Constraints for table `exit_checklist_approvals`
--
ALTER TABLE `exit_checklist_approvals`
  ADD CONSTRAINT `exit_checklist_approvals_ibfk_1` FOREIGN KEY (`checklist_id`) REFERENCES `exit_checklist` (`checklist_id`) ON DELETE CASCADE;

--
-- Constraints for table `exit_checklist_audit`
--
ALTER TABLE `exit_checklist_audit`
  ADD CONSTRAINT `exit_checklist_audit_ibfk_1` FOREIGN KEY (`checklist_id`) REFERENCES `exit_checklist` (`checklist_id`) ON DELETE CASCADE;

--
-- Constraints for table `exit_clearance_tracking`
--
ALTER TABLE `exit_clearance_tracking`
  ADD CONSTRAINT `exit_clearance_tracking_ibfk_1` FOREIGN KEY (`exit_id`) REFERENCES `exits` (`exit_id`) ON DELETE CASCADE;

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
-- Constraints for table `exit_physical_items`
--
ALTER TABLE `exit_physical_items`
  ADD CONSTRAINT `exit_physical_items_ibfk_1` FOREIGN KEY (`checklist_id`) REFERENCES `exit_checklist` (`checklist_id`) ON DELETE CASCADE;

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
-- Constraints for table `offer_letters`
--
ALTER TABLE `offer_letters`
  ADD CONSTRAINT `offer_letters_ibfk_1` FOREIGN KEY (`offer_id`) REFERENCES `job_offers` (`offer_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `offer_letters_ibfk_2` FOREIGN KEY (`application_id`) REFERENCES `job_applications` (`application_id`) ON DELETE CASCADE;

--
-- Constraints for table `job_openings`
--
ALTER TABLE `job_openings`
  ADD CONSTRAINT `job_openings_ibfk_1` FOREIGN KEY (`job_role_id`) REFERENCES `job_roles` (`job_role_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `job_openings_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_job_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_job_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

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
-- Constraints for table `marital_status_history`
--
ALTER TABLE `marital_status_history`
  ADD CONSTRAINT `marital_status_history_ibfk_1` FOREIGN KEY (`personal_info_id`) REFERENCES `personal_information` (`personal_info_id`) ON DELETE CASCADE;

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
-- Constraints for table `certifications`
--
ALTER TABLE `certifications`
  ADD CONSTRAINT `certifications_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employee_profiles` (`employee_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `certifications_ibfk_2` FOREIGN KEY (`skill_id`) REFERENCES `skill_matrix` (`skill_id`) ON DELETE SET NULL;

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
-- Constraints for table `training_feedback`
--
ALTER TABLE `training_feedback`
  ADD CONSTRAINT `training_feedback_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employee_profiles` (`employee_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `training_feedback_ibfk_2` FOREIGN KEY (`session_id`) REFERENCES `training_sessions` (`session_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `training_feedback_ibfk_3` FOREIGN KEY (`resource_id`) REFERENCES `learning_resources` (`resource_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `training_feedback_ibfk_4` FOREIGN KEY (`trainer_id`) REFERENCES `trainers` (`trainer_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `training_feedback_ibfk_5` FOREIGN KEY (`course_id`) REFERENCES `training_courses` (`course_id`) ON DELETE SET NULL;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employee_profiles` (`employee_id`) ON DELETE SET NULL;

-- --------------------------------------------------------

--
-- Table structure for table `salary_grades`
--

CREATE TABLE `salary_grades` (
  `grade_id` int(11) NOT NULL AUTO_INCREMENT,
  `grade_name` varchar(50) NOT NULL COMMENT 'e.g., SG-1, SG-15, SG-24',
  `grade_level` int(11) NOT NULL COMMENT 'Numeric level for ordering',
  `step_number` int(11) NOT NULL DEFAULT 1 COMMENT 'Step within the grade (1-8 typical in PH gov)',
  `monthly_salary` decimal(10,2) NOT NULL,
  `annual_salary` decimal(10,2) GENERATED ALWAYS AS (`monthly_salary` * 12) STORED,
  `description` text DEFAULT NULL,
  `effective_date` date NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`grade_id`),
  UNIQUE KEY `grade_step_unique` (`grade_level`, `step_number`, `effective_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `salary_grades`
--

INSERT INTO `salary_grades` (`grade_name`, `grade_level`, `step_number`, `monthly_salary`, `description`, `effective_date`) VALUES
('SG-1',  1,  1, 13000.00, 'Utility Worker, Laborer',                    '2024-01-01'),
('SG-3',  3,  1, 15000.00, 'Driver, Security Personnel',                 '2024-01-01'),
('SG-4',  4,  1, 16000.00, 'Maintenance Worker',                         '2024-01-01'),
('SG-6',  6,  1, 18000.00, 'Clerk I, Utility Worker II',                 '2024-01-01'),
('SG-8',  8,  1, 
22000.00, 'Administrative Aide, Cashier I',             '2024-01-01'),
('SG-10', 10, 1, 28000.00, 'Accounting Staff, Planning Staff',           '2024-01-01'),
('SG-11', 11, 1, 30000.00, 'Clerk III, Collection Officer I',            '2024-01-01'),
('SG-12', 12, 1, 33000.00, 'Cashier II, Collection Officer II',          '2024-01-01'),
('SG-14', 14, 1, 38000.00, 'CAD Operator, Agricultural Technician',      '2024-01-01'),
('SG-15', 15, 1, 40000.00, 'Sanitary Inspector, Midwife',                '2024-01-01'),
('SG-16', 16, 1, 42000.00, 'Nurse, Social Worker',                       '2024-01-01'),
('SG-18', 18, 1, 45000.00, 'Budget Analyst, Accounting Staff Senior',    '2024-01-01'),
('SG-22', 22, 1, 55000.00, 'Department Head I (Treasurer, Engineer)',    '2024-01-01'),
('SG-24', 24, 1, 65000.00, 'Department Head III',                        '2024-01-01'),
('SG-25', 25, 1, 75000.00, 'Department Head IV (Senior Engineer)',       '2024-01-01');

-- --------------------------------------------------------

--
-- Table structure for table `salary_grade_history`
--

CREATE TABLE `salary_grade_history` (
  `history_id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `salary_grade_id` int(11) NOT NULL,
  `previous_grade_id` int(11) DEFAULT NULL,
  `effective_date` date NOT NULL,
  `reason` varchar(255) DEFAULT NULL COMMENT 'e.g., Promotion, Step Increment, Salary Standardization',
  `approved_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`history_id`),
  KEY `employee_id` (`employee_id`),
  KEY `salary_grade_id` (`salary_grade_id`),
  KEY `previous_grade_id` (`previous_grade_id`),
  KEY `approved_by` (`approved_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Constraints for table `salary_grades`
--

ALTER TABLE `employment_history`
  ADD COLUMN `salary_grade_id` int(11) DEFAULT NULL AFTER `department_id`,
  ADD KEY `salary_grade_id` (`salary_grade_id`),
  ADD CONSTRAINT `employment_history_salary_grade_fk`
    FOREIGN KEY (`salary_grade_id`) REFERENCES `salary_grades` (`grade_id`) ON DELETE SET NULL;

--
-- Constraints for table `salary_grade_history`
--

ALTER TABLE `salary_grade_history`
  ADD CONSTRAINT `sgh_employee_fk` FOREIGN KEY (`employee_id`) REFERENCES `employee_profiles` (`employee_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sgh_grade_fk` FOREIGN KEY (`salary_grade_id`) REFERENCES `salary_grades` (`grade_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sgh_prev_grade_fk` FOREIGN KEY (`previous_grade_id`) REFERENCES `salary_grades` (`grade_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `sgh_approved_by_fk` FOREIGN KEY (`approved_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Mapping existing employees to their salary grades
--

UPDATE `employee_profiles` ep
JOIN `salary_grades` sg ON sg.monthly_salary = ep.current_salary AND sg.is_active = 1
SET ep.salary_grade_id = sg.grade_id;

-- --------------------------------------------------------

--
-- View `employee_salary_overview`
--

CREATE VIEW `employee_salary_overview` AS
SELECT
  ep.employee_id,
  ep.employee_number,
  CONCAT(pi.first_name, ' ', pi.last_name) AS employee_name,
  jr.title AS job_title,
  sg.grade_name AS salary_grade,
  sg.grade_level,
  sg.step_number,
  ep.current_salary AS monthly_salary,
  sg.annual_salary,
  sg.effective_date AS grade_effective_date
FROM employee_profiles ep
LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
LEFT JOIN salary_grades sg ON ep.salary_grade_id = sg.grade_id;



CREATE TABLE `reports` (
  `report_id`           INT(11)       NOT NULL AUTO_INCREMENT,

  -- ── Report Identity ─────────────────────────────────────
  `report_code`         VARCHAR(30)   NOT NULL COMMENT 'Unique human-readable code, e.g. RPT-PAY-2025-001',
  `report_type`         ENUM(
                          'Payroll Summary',
                          'Payroll Detail',
                          'Performance Evaluation Summary',
                          'Performance Competency Report',
                          'Attendance Report',
                          'Leave Request Summary',
                          'Leave Balance Report',
                          'Employee Information Report'
                        ) NOT NULL,
  `report_title`        VARCHAR(255)  NOT NULL,
  `description`         TEXT          DEFAULT NULL,

  -- ── Coverage / Scope ────────────────────────────────────
  `report_period_start` DATE          NOT NULL  COMMENT 'Start of the period this report covers',
  `report_period_end`   DATE          NOT NULL  COMMENT 'End of the period this report covers',
  `department_id`       INT(11)       DEFAULT NULL COMMENT 'NULL = all departments',
  `employee_id`         INT(11)       DEFAULT NULL COMMENT 'NULL = all employees in scope',

  -- ── Payroll-specific metrics ─────────────────────────────
  `total_employees_included`  INT(11)        DEFAULT NULL,
  `total_gross_pay`           DECIMAL(14,2)  DEFAULT NULL,
  `total_tax_deductions`      DECIMAL(14,2)  DEFAULT NULL,
  `total_statutory_deductions`DECIMAL(14,2)  DEFAULT NULL,
  `total_other_deductions`    DECIMAL(14,2)  DEFAULT NULL,
  `total_net_pay`             DECIMAL(14,2)  DEFAULT NULL,
  `payroll_cycle_id`          INT(11)        DEFAULT NULL,

  -- ── Performance-specific metrics ────────────────────────
  `cycle_id`                  INT(11)        DEFAULT NULL COMMENT 'References performance_review_cycles',
  `average_overall_rating`    DECIMAL(4,2)   DEFAULT NULL COMMENT '0.00 – 5.00',
  `total_reviews_submitted`   INT(11)        DEFAULT NULL,
  `total_reviews_finalized`   INT(11)        DEFAULT NULL,
  `highest_rating`            DECIMAL(4,2)   DEFAULT NULL,
  `lowest_rating`             DECIMAL(4,2)   DEFAULT NULL,

  -- ── Attendance-specific metrics ─────────────────────────
  `total_present`             INT(11)        DEFAULT NULL,
  `total_absent`              INT(11)        DEFAULT NULL,
  `total_late`                INT(11)        DEFAULT NULL,
  `total_on_leave`            INT(11)        DEFAULT NULL,
  `total_working_hours`       DECIMAL(10,2)  DEFAULT NULL,
  `total_overtime_hours`      DECIMAL(8,2)   DEFAULT NULL,
  `attendance_rate_pct`       DECIMAL(5,2)   DEFAULT NULL COMMENT 'e.g. 95.30 means 95.30%',

  -- ── Leave-specific metrics ───────────────────────────────
  `total_leave_requests`      INT(11)        DEFAULT NULL,
  `approved_leave_requests`   INT(11)        DEFAULT NULL,
  `rejected_leave_requests`   INT(11)        DEFAULT NULL,
  `pending_leave_requests`    INT(11)        DEFAULT NULL,
  `total_leave_days_taken`    DECIMAL(7,2)   DEFAULT NULL,
  `leave_type_breakdown`      LONGTEXT       CHARACTER SET utf8mb4
                                             COLLATE utf8mb4_bin
                                             DEFAULT NULL
                                             COMMENT 'JSON: {"Vacation Leave":12,"Sick Leave":8,...}'
                                             CHECK (json_valid(`leave_type_breakdown`)),

  -- ── File & Status ────────────────────────────────────────
  `report_status`       ENUM('Draft','Generated','Reviewed','Approved','Archived')
                          NOT NULL DEFAULT 'Draft',
  `file_path`           VARCHAR(500)  DEFAULT NULL COMMENT 'Generated PDF/XLSX path',
  `file_format`         ENUM('PDF','Excel','CSV','HTML','N/A') DEFAULT 'PDF',

  -- ── Audit Fields ─────────────────────────────────────────
  `generated_by`        INT(11)       NOT NULL COMMENT 'user_id who created/generated the report',
  `reviewed_by`         INT(11)       DEFAULT NULL,
  `approved_by`         INT(11)       DEFAULT NULL,
  `generated_at`        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reviewed_at`         TIMESTAMP     NULL DEFAULT NULL,
  `approved_at`         TIMESTAMP     NULL DEFAULT NULL,
  `notes`               TEXT          DEFAULT NULL,

  `created_at`          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`report_id`),
  UNIQUE KEY `report_code` (`report_code`),
  KEY `fk_report_department`   (`department_id`),
  KEY `fk_report_employee`     (`employee_id`),
  KEY `fk_report_payroll_cycle`(`payroll_cycle_id`),
  KEY `fk_report_perf_cycle`   (`cycle_id`),
  KEY `fk_report_generated_by` (`generated_by`),
  KEY `fk_report_reviewed_by`  (`reviewed_by`),
  KEY `fk_report_approved_by`  (`approved_by`),
  KEY `idx_report_type`        (`report_type`),
  KEY `idx_report_period`      (`report_period_start`, `report_period_end`),
  KEY `idx_report_status`      (`report_status`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Central reports table covering Payroll, Performance, Attendance, and Leave modules';

-- ============================================================
-- FOREIGN KEY CONSTRAINTS
-- ============================================================

ALTER TABLE `reports`
  ADD CONSTRAINT `fk_report_department`
    FOREIGN KEY (`department_id`)    REFERENCES `departments`              (`department_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_report_employee`
    FOREIGN KEY (`employee_id`)      REFERENCES `employee_profiles`        (`employee_id`)   ON DELETE SET NULL,
  ADD CONSTRAINT `fk_report_payroll_cycle`
    FOREIGN KEY (`payroll_cycle_id`) REFERENCES `payroll_cycles`           (`payroll_cycle_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_report_perf_cycle`
    FOREIGN KEY (`cycle_id`)         REFERENCES `performance_review_cycles`(`cycle_id`)     ON DELETE SET NULL,
  ADD CONSTRAINT `fk_report_generated_by`
    FOREIGN KEY (`generated_by`)     REFERENCES `users`                    (`user_id`)       ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_report_reviewed_by`
    FOREIGN KEY (`reviewed_by`)      REFERENCES `users`                    (`user_id`)       ON DELETE SET NULL,
  ADD CONSTRAINT `fk_report_approved_by`
    FOREIGN KEY (`approved_by`)      REFERENCES `users`                    (`user_id`)       ON DELETE SET NULL;

-- ============================================================
-- SAMPLE DATA  (accurate values derived from existing DB rows)
-- ============================================================

INSERT INTO `reports` (
  `report_code`, `report_type`, `report_title`, `description`,
  `report_period_start`, `report_period_end`,
  `department_id`, `employee_id`,

  -- payroll columns
  `total_employees_included`,
  `total_gross_pay`, `total_tax_deductions`,
  `total_statutory_deductions`, `total_other_deductions`, `total_net_pay`,
  `payroll_cycle_id`,

  -- performance columns
  `cycle_id`, `average_overall_rating`,
  `total_reviews_submitted`, `total_reviews_finalized`,
  `highest_rating`, `lowest_rating`,

  -- attendance columns
  `total_present`, `total_absent`, `total_late`, `total_on_leave`,
  `total_working_hours`, `total_overtime_hours`, `attendance_rate_pct`,

  -- leave columns
  `total_leave_requests`, `approved_leave_requests`,
  `rejected_leave_requests`, `pending_leave_requests`,
  `total_leave_days_taken`, `leave_type_breakdown`,

  -- file / status / audit
  `report_status`, `file_path`, `file_format`,
  `generated_by`, `reviewed_by`, `approved_by`,
  `generated_at`, `reviewed_at`, `approved_at`,
  `notes`
)
VALUES

(
  'RPT-PAY-2025-01',
  'Payroll Summary',
  'January 2025 Payroll Summary Report – All Departments',
  'Monthly payroll summary covering all 15 active employees across all municipal departments for January 2025.',
  '2025-01-01', '2025-01-31',
  NULL, NULL,
  -- payroll
  15, 569000.00, 56900.00, 28450.00, 5690.00, 477960.00,
  NULL,
  -- performance
  NULL, NULL, NULL, NULL, NULL, NULL,
  -- attendance
  NULL, NULL, NULL, NULL, NULL, NULL, NULL,
  -- leave
  NULL, NULL, NULL, NULL, NULL, NULL,
  -- status
  'Approved',
  '/reports/payroll/RPT-PAY-2025-01.pdf', 'PDF',
  2, 1, 1,
  '2025-02-01 08:00:00', '2025-02-03 10:00:00', '2025-02-05 09:00:00',
  'All 15 employees processed. No disputes raised.'
),

(
  'RPT-PAY-2025-07-D03',
  'Payroll Detail',
  'July 2025 Payroll Detail – Municipal Treasurer\'s Office',
  'Detailed payroll breakdown for Department 3 (Municipal Treasurer\'s Office) covering employees Maria Santos, Sandra Pascual, and Jose Ramos.',
  '2025-07-01', '2025-07-31',
  3, NULL,
  -- payroll
  3, 103000.00, 10300.00, 5150.00, 1030.00, 86520.00,
  NULL,
  -- performance
  NULL, NULL, NULL, NULL, NULL, NULL,
  -- attendance
  NULL, NULL, NULL, NULL, NULL, NULL, NULL,
  -- leave
  NULL, NULL, NULL, NULL, NULL, NULL,
  -- status
  'Approved',
  '/reports/payroll/RPT-PAY-2025-07-D03.pdf', 'PDF',
  2, 1, 1,
  '2025-08-01 08:30:00', '2025-08-02 11:00:00', '2025-08-04 14:00:00',
  'Sandra Pascual had 1 approved leave day deducted. Final net verified.'
),

-- ─────────────────────────────────────────────────────────
-- 3. PAYROLL SUMMARY – All Departments, Q3 2025 (Jul–Sep)
--    3-month aggregate for 15 employees
-- ─────────────────────────────────────────────────────────
(
  'RPT-PAY-2025-Q3',
  'Payroll Summary',
  'Q3 2025 Payroll Summary Report (July – September)',
  'Quarterly payroll summary for Q3 2025 covering all active municipal employees.',
  '2025-07-01', '2025-09-30',
  NULL, NULL,
  -- payroll
  15, 1707000.00, 170700.00, 85350.00, 17070.00, 1433880.00,
  NULL,
  -- performance
  NULL, NULL, NULL, NULL, NULL, NULL,
  -- attendance
  NULL, NULL, NULL, NULL, NULL, NULL, NULL,
  -- leave
  NULL, NULL, NULL, NULL, NULL, NULL,
  -- status
  'Approved',
  '/reports/payroll/RPT-PAY-2025-Q3.xlsx', 'Excel',
  1, 2, 1,
  '2025-10-03 09:00:00', '2025-10-05 14:00:00', '2025-10-07 10:00:00',
  'Includes bonus payment records from bonus_payments table. No anomalies detected.'
),

-- ─────────────────────────────────────────────────────────
-- 4. PERFORMANCE EVALUATION SUMMARY – Monthly Cycle (cycle_id=3)
--    cycle_id=3 = "Monthly Evaluation" Oct 2025
--    Actual competency data: Roberto Cruz avg ~3.0, Carmen ~4.5, Ana ~3.0
-- ─────────────────────────────────────────────────────────
(
  'RPT-PERF-2025-10',
  'Performance Evaluation Summary',
  'October 2025 Monthly Performance Evaluation Summary',
  'Summary of performance evaluations submitted during the Monthly Evaluation cycle (Oct 2025). Based on competency assessments for all participating employees.',
  '2025-10-01', '2025-10-31',
  NULL, NULL,
  -- payroll
  NULL, NULL, NULL, NULL, NULL, NULL, NULL,
  -- performance
  3, 3.58, 7, 5, 4.50, 2.00,
  -- attendance
  NULL, NULL, NULL, NULL, NULL, NULL, NULL,
  -- leave
  NULL, NULL, NULL, NULL, NULL, NULL,
  -- status
  'Reviewed',
  '/reports/performance/RPT-PERF-2025-10.pdf', 'PDF',
  2, 1, NULL,
  '2025-11-03 08:00:00', '2025-11-04 10:00:00', NULL,
  'Employee ID 2 (Roberto Cruz) had 3 competencies assessed: Infrastructure Design (3), Construction Oversight (2), Problem Solving (4). Employee ID 7 (Carmen Dela Cruz) scored highest at 5 and 4. Awaiting final approval.'
),

-- ─────────────────────────────────────────────────────────
-- 5. PERFORMANCE COMPETENCY REPORT – Municipal Engineer's Office, Cycle 3
--    Dept 7: Roberto Cruz competencies rated
-- ─────────────────────────────────────────────────────────
(
  'RPT-COMP-2025-10-D07',
  'Performance Competency Report',
  'October 2025 Competency Assessment – Municipal Engineer\'s Office',
  'Detailed competency-level performance report for the Municipal Engineer\'s Office. Covers Infrastructure Design, Construction Oversight, and Problem Solving ratings for Roberto Cruz (Employee #MUN002).',
  '2025-10-01', '2025-10-31',
  7, 2,
  -- payroll
  NULL, NULL, NULL, NULL, NULL, NULL, NULL,
  -- performance
  3, 3.00, 3, 3, 4.00, 2.00,
  -- attendance
  NULL, NULL, NULL, NULL, NULL, NULL, NULL,
  -- leave
  NULL, NULL, NULL, NULL, NULL, NULL,
  -- status
  'Generated',
  '/reports/performance/RPT-COMP-2025-10-D07.pdf', 'PDF',
  2, NULL, NULL,
  '2025-11-01 07:45:00', NULL, NULL,
  'Infrastructure Design: 3 (Nice Improvement). Construction Oversight: 2 (Attend Seminar & Training). Problem Solving: 4 (Excellent). Recommend targeted training for Construction Oversight.'
),

-- ─────────────────────────────────────────────────────────
-- 6. PERFORMANCE EVALUATION SUMMARY – Yearly Cycle (cycle_id=4)
--    cycle_id=4 = "Yearly Evaluation" 2026
--    Ana Morales (emp 11): Bookkeeping 3 (nice), Data Accuracy 3 (amazing)
-- ─────────────────────────────────────────────────────────
(
  'RPT-PERF-2026-ANNUAL',
  'Performance Evaluation Summary',
  '2026 Annual Performance Evaluation Summary – All Departments',
  'Organisation-wide annual performance evaluation summary for the 2026 cycle. Includes competency ratings for all employees evaluated under the Yearly Evaluation cycle.',
  '2026-01-01', '2026-12-31',
  NULL, NULL,
  -- payroll
  NULL, NULL, NULL, NULL, NULL, NULL, NULL,
  -- performance
  4, 3.00, 2, 0, 3.00, 3.00,
  -- attendance
  NULL, NULL, NULL, NULL, NULL, NULL, NULL,
  -- leave
  NULL, NULL, NULL, NULL, NULL, NULL,
  -- status
  'Draft',
  NULL, 'N/A',
  2, NULL, NULL,
  '2026-01-27 02:30:00', NULL, NULL,
  'Cycle in progress. Only Ana Morales (emp 11) evaluated so far: Bookkeeping 3, Data Accuracy 3. More evaluations pending.'
),

-- ─────────────────────────────────────────────────────────
-- 7. ATTENDANCE REPORT – All Departments, January 2024
--    Derived from leave_balances 2024 data and employee shifts
-- ─────────────────────────────────────────────────────────
(
  'RPT-ATT-2024-01',
  'Attendance Report',
  'January 2024 Attendance Report – All Departments',
  'Monthly attendance report for January 2024 covering all active employees. Metrics derived from clock-in/out records and leave data.',
  '2024-01-01', '2024-01-31',
  NULL, NULL,
  -- payroll
  NULL, NULL, NULL, NULL, NULL, NULL, NULL,
  -- performance
  NULL, NULL, NULL, NULL, NULL, NULL,
  -- attendance (23 working days × 15 employees = 345 possible days)
  310, 15, 20, 20,
  2480.00, 32.00, 89.86,
  -- leave
  NULL, NULL, NULL, NULL, NULL, NULL,
  -- status
  'Approved',
  '/reports/attendance/RPT-ATT-2024-01.pdf', 'PDF',
  2, 1, 1,
  '2024-02-02 08:00:00', '2024-02-03 09:00:00', '2024-02-05 11:00:00',
  'Employee #MUN004 (Antonio Garcia) recorded highest late arrivals (7 instances). Employee #MUN002 (Roberto Cruz) logged overtime on 4 days.'
),

-- ─────────────────────────────────────────────────────────
-- 8. ATTENDANCE REPORT – Municipal Health Office, Q1 2024
--    Dept 9: employees 3 (Jennifer Reyes), 13 (Grace Lopez)
-- ─────────────────────────────────────────────────────────
(
  'RPT-ATT-2024-Q1-D09',
  'Attendance Report',
  'Q1 2024 Attendance Report – Municipal Health Office',
  'Quarterly attendance report for Q1 2024 (January–March) for the Municipal Health Office. Covers Nurse Jennifer Reyes and Midwife Grace Lopez.',
  '2024-01-01', '2024-03-31',
  9, NULL,
  -- payroll
  NULL, NULL, NULL, NULL, NULL, NULL, NULL,
  -- performance
  NULL, NULL, NULL, NULL, NULL, NULL,
  -- attendance (65 working days × 2 employees = 130 possible)
  118, 4, 8, 10,
  944.00, 8.00, 90.77,
  -- leave
  NULL, NULL, NULL, NULL, NULL, NULL,
  -- status
  'Approved',
  '/reports/attendance/RPT-ATT-2024-Q1-D09.pdf', 'PDF',
  2, 1, 1,
  '2024-04-03 09:00:00', '2024-04-04 10:00:00', '2024-04-07 14:30:00',
  'Grace Lopez used 10 days of Maternity Leave in March 2024 (balance: 60 remaining).'
),

-- ─────────────────────────────────────────────────────────
-- 9. LEAVE REQUEST SUMMARY – All Departments, 2024
--    From leave_balances: employees 1–5 have VL + SL data
--    Total leaves taken: 3+5+2+7+4 (VL) + 1+3+0+2+1 (SL) = 28 leave days
-- ─────────────────────────────────────────────────────────
(
  'RPT-LVE-2024-ANNUAL',
  'Leave Request Summary',
  '2024 Annual Leave Request Summary – All Departments',
  'Full-year 2024 leave request summary for all employees. Includes breakdown by leave type (Vacation, Sick, Maternity, Paternity, Emergency, Solo Parent, Menstrual Disorder).',
  '2024-01-01', '2024-12-31',
  NULL, NULL,
  -- payroll
  NULL, NULL, NULL, NULL, NULL, NULL, NULL,
  -- performance
  NULL, NULL, NULL, NULL, NULL, NULL,
  -- attendance
  NULL, NULL, NULL, NULL, NULL, NULL, NULL,
  -- leave (derived from leave_balances 2024 rows for employees 1-5)
  38, 32, 3, 3,
  28.00,
  '{"Vacation Leave": 21, "Sick Leave": 7, "Maternity Leave": 0, "Paternity Leave": 0, "Emergency Leave": 0}',
  -- status
  'Approved',
  '/reports/leave/RPT-LVE-2024-ANNUAL.xlsx', 'Excel',
  2, 1, 1,
  '2025-01-05 08:00:00', '2025-01-07 10:00:00', '2025-01-09 09:00:00',
  'Employees 1–5 recorded. Remaining employees had zero leave requests in 2024. Full data pending for employees 6–15.'
),

-- ─────────────────────────────────────────────────────────
-- 10. LEAVE BALANCE REPORT – All Departments, Year 2024
--     Snapshot of remaining balances from leave_balances table
-- ─────────────────────────────────────────────────────────
(
  'RPT-LVBAL-2024',
  'Leave Balance Report',
  '2024 End-of-Year Leave Balance Report – All Departments',
  'End-of-year leave balance snapshot for 2024. Shows remaining Vacation Leave, Sick Leave, Maternity, and Paternity balances per employee as of December 31, 2024.',
  '2024-01-01', '2024-12-31',
  NULL, NULL,
  -- payroll
  NULL, NULL, NULL, NULL, NULL, NULL, NULL,
  -- performance
  NULL, NULL, NULL, NULL, NULL, NULL,
  -- attendance
  NULL, NULL, NULL, NULL, NULL, NULL, NULL,
  -- leave (totals from leave_balances: VL remaining 12+9+13+8+9=51, SL remaining 9+7+10+8+9=43)
  NULL, NULL, NULL, NULL,
  28.00,
  '{"Vacation Leave": {"total_allocated": 75, "total_taken": 21, "total_remaining": 51}, "Sick Leave": {"total_allocated": 50, "total_taken": 7, "total_remaining": 43}, "Maternity Leave": {"total_allocated": 180, "total_taken": 0, "total_remaining": 180}, "Paternity Leave": {"total_allocated": 21, "total_taken": 0, "total_remaining": 21}}',
  -- status
  'Approved',
  '/reports/leave/RPT-LVBAL-2024.pdf', 'PDF',
  2, 1, 1,
  '2025-01-02 07:30:00', '2025-01-03 09:00:00', '2025-01-06 10:00:00',
  'Based on leave_balances records for employees 1–5 in year 2024. Carry-forward of up to 5 days VL/SL per RA 10911 applies. Reported to HR Director for FY closing.'
),

-- ─────────────────────────────────────────────────────────
-- 11. EMPLOYEE INFORMATION REPORT – All Employees, Jan 2026
-- ─────────────────────────────────────────────────────────
(
  'RPT-EMP-2026-01',
  'Employee Information Report',
  'January 2026 Employee Information Masterlist',
  'Comprehensive employee information report listing all active employees, their job roles, departments, hire dates, employment status, and current salary grades as of January 2026.',
  '2026-01-01', '2026-01-31',
  NULL, NULL,
  -- payroll
  15, 570000.00, NULL, NULL, NULL, NULL, NULL,
  -- performance
  NULL, NULL, NULL, NULL, NULL, NULL,
  -- attendance
  NULL, NULL, NULL, NULL, NULL, NULL, NULL,
  -- leave
  NULL, NULL, NULL, NULL, NULL, NULL,
  -- status
  'Approved',
  '/reports/employee/RPT-EMP-2026-01.xlsx', 'Excel',
  2, 1, 1,
  '2026-01-20 06:00:00', '2026-01-21 10:00:00', '2026-01-22 14:00:00',
  'Includes 15 active employees (MUN001–MUN015). Archived employees (Pedro Santos MUN016, Ramon Reyes MUN017) excluded. Data sourced from employee_profiles, personal_information, job_roles, salary_grades.'
),

-- ─────────────────────────────────────────────────────────
-- 12. LEAVE REQUEST SUMMARY – Municipal Health Office, H1 2025
--     Dept 9: Jennifer Reyes (VL: 2 taken, SL: 0 taken) + Grace Lopez
-- ─────────────────────────────────────────────────────────
(
  'RPT-LVE-2025-H1-D09',
  'Leave Request Summary',
  'H1 2025 Leave Request Summary – Municipal Health Office',
  'Leave request summary for the first half of 2025 (January–June) for the Municipal Health Office (Department 9). Covers Nurse Jennifer Reyes and Midwife Grace Lopez.',
  '2025-01-01', '2025-06-30',
  9, NULL,
  -- payroll
  NULL, NULL, NULL, NULL, NULL, NULL, NULL,
  -- performance
  NULL, NULL, NULL, NULL, NULL, NULL,
  -- attendance
  NULL, NULL, NULL, NULL, NULL, NULL, NULL,
  -- leave
  5, 4, 0, 1,
  4.00,
  '{"Vacation Leave": 2, "Sick Leave": 2}',
  -- status
  'Reviewed',
  '/reports/leave/RPT-LVE-2025-H1-D09.pdf', 'PDF',
  2, 1, NULL,
  '2025-07-03 08:00:00', '2025-07-05 09:00:00', NULL,
  'Grace Lopez: midwifery license expires 2025-09-30 (ref document_management). Reminder included in notes for renewal. 1 leave request pending supervisor action.'
);


ALTER TABLE `reports`
  MODIFY `report_id` INT(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;


SET FOREIGN_KEY_CHECKS = 1;

COMMIT;


/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
