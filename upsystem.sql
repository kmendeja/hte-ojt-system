-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 30, 2025 at 06:52 AM
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
-- Database: `upsystem`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `admin_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `position` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`admin_id`, `user_id`, `full_name`, `position`, `email`, `contact_number`, `profile_image`) VALUES
(6, 18, 'DIOSA, RUBY JHANE', 'CSS SECRETARY', 'lumactod_rubyjane@plpasig.edu.ph@plpasig.edu.ph', '09123456789', 'php/profiles_admin/admin_18.jpg'),
(7, 19, 'Admin', 'System Administrator', 'admin@plpasig.edu.ph', '09234567890', 'php/profiles_admin/admin_19.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `admin_announcements`
--

CREATE TABLE `admin_announcements` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `target_role` varchar(20) NOT NULL,
  `created_by` int(11) NOT NULL,
  `is_important` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance_logs`
--

CREATE TABLE `attendance_logs` (
  `id` int(11) NOT NULL,
  `trainee_id` varchar(255) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `time_in` time DEFAULT NULL,
  `time_out` time DEFAULT NULL,
  `hours_worked` decimal(5,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `companies`
--

CREATE TABLE `companies` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `effective_date` date DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `corporate_email` varchar(100) DEFAULT NULL,
  `corporate_contact` varchar(20) DEFAULT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `documents` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `coordinators`
--

CREATE TABLE `coordinators` (
  `coordinator_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `job_description` varchar(255) DEFAULT NULL,
  `employee_id` varchar(20) NOT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `account_created` date DEFAULT NULL,
  `archived` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `coordinator_announcements`
--

CREATE TABLE `coordinator_announcements` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `coordinator_id` int(11) NOT NULL,
  `is_important` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `coordinator_announcement_recipients`
--

CREATE TABLE `coordinator_announcement_recipients` (
  `id` int(11) NOT NULL,
  `announcement_id` int(11) NOT NULL,
  `trainee_id` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `coordinator_announcement_sections`
--

CREATE TABLE `coordinator_announcement_sections` (
  `id` int(11) NOT NULL,
  `announcement_id` int(11) DEFAULT NULL,
  `program` varchar(10) DEFAULT NULL,
  `section` varchar(10) DEFAULT NULL,
  `academic_year` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `coordinator_trainees`
--

CREATE TABLE `coordinator_trainees` (
  `id` int(11) NOT NULL,
  `coordinator_id` int(11) NOT NULL,
  `trainee_id` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_role` enum('trainee','coordinator','admin') NOT NULL,
  `message` text NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `used` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `requirements`
--

CREATE TABLE `requirements` (
  `id` int(11) NOT NULL,
  `requirement_name` varchar(255) NOT NULL,
  `requirement_type` enum('pre','post') NOT NULL,
  `position` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `requirements`
--

INSERT INTO `requirements` (`id`, `requirement_name`, `requirement_type`, `position`, `created_at`, `updated_at`) VALUES
(5, 'Final Report', 'post', 0, '2025-05-11 18:10:44', '2025-05-11 18:10:44'),
(6, 'Company Evaluation', 'post', 0, '2025-05-11 18:10:44', '2025-05-11 18:10:44'),
(7, 'Requirements Evaluation', 'post', 0, '2025-05-11 18:10:44', '2025-05-21 17:47:31'),
(8, 'Job Description', 'post', 0, '2025-05-11 18:10:44', '2025-05-21 17:47:15'),
(13, 'Certificate of Registration', 'pre', 0, '2025-05-18 13:41:49', '2025-05-23 15:40:21'),
(14, 'Application Form', 'pre', 0, '2025-05-18 13:41:49', '2025-05-21 18:09:08'),
(15, 'Resume/Curriculum Vitae', 'pre', 0, '2025-05-18 13:41:49', '2025-05-21 18:06:15'),
(16, 'Medical Certificate', 'pre', 0, '2025-05-18 13:41:49', '2025-05-21 18:10:02'),
(17, 'DTI Certificate', 'pre', 0, '2025-05-18 13:41:49', '2025-05-21 17:46:36'),
(18, 'GAD Document', 'pre', 0, '2025-05-18 13:41:49', '2025-05-21 17:45:59'),
(19, 'COR', 'pre', 0, '2025-05-18 13:41:49', '2025-05-23 21:51:17'),
(20, 'Training Agreement', 'pre', 0, '2025-05-18 13:41:49', '2025-05-21 17:45:05'),
(21, 'Certificate of Training/Seminar/Orientation', 'pre', 0, '2025-05-18 13:41:49', '2025-05-18 13:41:49'),
(22, 'Parental Consent Form', 'pre', 0, '2025-05-18 13:41:49', '2025-05-18 13:41:49'),
(39, 'Grade', 'post', 0, '2025-05-21 17:47:56', '2025-05-21 17:47:56'),
(40, 'Certificate of Completion', 'post', 0, '2025-05-21 17:48:18', '2025-05-21 17:48:18'),
(41, 'Narrative Report', 'post', 0, '2025-05-21 17:48:35', '2025-05-21 17:48:35');

-- --------------------------------------------------------

--
-- Table structure for table `section_assignments`
--

CREATE TABLE `section_assignments` (
  `id` int(11) NOT NULL,
  `coordinator_id` int(11) NOT NULL,
  `program` varchar(10) NOT NULL,
  `section` varchar(10) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `task_files`
--

CREATE TABLE `task_files` (
  `id` int(11) NOT NULL,
  `submission_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `upload_date` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `task_submissions`
--

CREATE TABLE `task_submissions` (
  `id` int(11) NOT NULL,
  `trainee_id` varchar(50) NOT NULL,
  `submission_type` enum('Weekly Report','Submission of Deliverables') NOT NULL,
  `comment` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'Pending',
  `remarks` text DEFAULT NULL,
  `date_submitted` datetime DEFAULT current_timestamp(),
  `date_updated` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `is_read` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `trainees`
--

CREATE TABLE `trainees` (
  `trainee_id` varchar(50) NOT NULL,
  `full_name` varchar(255) DEFAULT NULL,
  `program` varchar(100) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `school` varchar(255) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `nature_of_work` varchar(100) DEFAULT NULL,
  `deployment_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `deployment_status` varchar(50) DEFAULT 'not-deployed',
  `user_id` int(11) DEFAULT NULL,
  `company_id` int(11) DEFAULT NULL,
  `section` varchar(50) DEFAULT NULL,
  `ojt_status` varchar(50) DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `trainee_requirements`
--

CREATE TABLE `trainee_requirements` (
  `id` int(11) NOT NULL,
  `trainee_id` varchar(50) DEFAULT NULL,
  `requirement_name` varchar(255) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `requirement_type` enum('pre','post') DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Pending',
  `remarks` text DEFAULT NULL,
  `date_submitted` datetime DEFAULT current_timestamp(),
  `date_updated` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('trainee','admin','coordinator') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `failed_login_attempts` int(11) DEFAULT 0,
  `account_locked_until` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `email`, `password`, `role`, `created_at`, `failed_login_attempts`, `account_locked_until`) VALUES
(18, 'lumactod_rubyjane@plpasig.edu.ph', 'lumactod_rubyjane@plpasig.edu.ph', '$2y$10$MtSKXH.P.ouXHmeLn/2JV./JM0IY0POniwKLQy1RNbyk90u/q/8oi', 'admin', '2025-05-24 01:16:05', 0, NULL),
(19, 'admin_main@plpasig.edu.ph', 'admin_main@plpasig.edu.ph', '$2y$10$/CLzBmL5UGQrk1daw4ZyP.n16ldutuvM4Zbz6HKioPMpS0HR/xfc.', 'admin', '2025-05-29 23:30:07', 0, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`admin_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `admin_announcements`
--
ALTER TABLE `admin_announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `attendance_logs`
--
ALTER TABLE `attendance_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `trainee_id` (`trainee_id`);

--
-- Indexes for table `companies`
--
ALTER TABLE `companies`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `coordinators`
--
ALTER TABLE `coordinators`
  ADD PRIMARY KEY (`coordinator_id`),
  ADD UNIQUE KEY `employee_id` (`employee_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `coordinator_announcements`
--
ALTER TABLE `coordinator_announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `coordinator_id` (`coordinator_id`);

--
-- Indexes for table `coordinator_announcement_recipients`
--
ALTER TABLE `coordinator_announcement_recipients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `announcement_id` (`announcement_id`),
  ADD KEY `trainee_id` (`trainee_id`);

--
-- Indexes for table `coordinator_announcement_sections`
--
ALTER TABLE `coordinator_announcement_sections`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_announcement_section` (`announcement_id`,`program`,`section`,`academic_year`);

--
-- Indexes for table `coordinator_trainees`
--
ALTER TABLE `coordinator_trainees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_coordinator_trainee` (`coordinator_id`,`trainee_id`),
  ADD KEY `trainee_id` (`trainee_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `requirements`
--
ALTER TABLE `requirements`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `section_assignments`
--
ALTER TABLE `section_assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `coordinator_id` (`coordinator_id`);

--
-- Indexes for table `task_files`
--
ALTER TABLE `task_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_submission_files` (`submission_id`);

--
-- Indexes for table `task_submissions`
--
ALTER TABLE `task_submissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_trainee_submissions` (`trainee_id`,`submission_type`);

--
-- Indexes for table `trainees`
--
ALTER TABLE `trainees`
  ADD PRIMARY KEY (`trainee_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `trainee_requirements`
--
ALTER TABLE `trainee_requirements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `trainee_id` (`trainee_id`),
  ADD KEY `requirement_type` (`requirement_type`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email_unique` (`email`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `admin_announcements`
--
ALTER TABLE `admin_announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `attendance_logs`
--
ALTER TABLE `attendance_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=258;

--
-- AUTO_INCREMENT for table `companies`
--
ALTER TABLE `companies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `coordinators`
--
ALTER TABLE `coordinators`
  MODIFY `coordinator_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT for table `coordinator_announcements`
--
ALTER TABLE `coordinator_announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `coordinator_announcement_recipients`
--
ALTER TABLE `coordinator_announcement_recipients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=235;

--
-- AUTO_INCREMENT for table `coordinator_announcement_sections`
--
ALTER TABLE `coordinator_announcement_sections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `coordinator_trainees`
--
ALTER TABLE `coordinator_trainees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4345;

--
-- AUTO_INCREMENT for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT for table `requirements`
--
ALTER TABLE `requirements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `section_assignments`
--
ALTER TABLE `section_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `task_files`
--
ALTER TABLE `task_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `task_submissions`
--
ALTER TABLE `task_submissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT for table `trainee_requirements`
--
ALTER TABLE `trainee_requirements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=352;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=790;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admins`
--
ALTER TABLE `admins`
  ADD CONSTRAINT `admins_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `admin_announcements`
--
ALTER TABLE `admin_announcements`
  ADD CONSTRAINT `admin_announcements_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `attendance_logs`
--
ALTER TABLE `attendance_logs`
  ADD CONSTRAINT `attendance_logs_ibfk_1` FOREIGN KEY (`trainee_id`) REFERENCES `trainees` (`trainee_id`);

--
-- Constraints for table `coordinators`
--
ALTER TABLE `coordinators`
  ADD CONSTRAINT `coordinators_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `coordinator_announcements`
--
ALTER TABLE `coordinator_announcements`
  ADD CONSTRAINT `coordinator_announcements_ibfk_1` FOREIGN KEY (`coordinator_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `coordinator_announcement_recipients`
--
ALTER TABLE `coordinator_announcement_recipients`
  ADD CONSTRAINT `coordinator_announcement_recipients_ibfk_1` FOREIGN KEY (`announcement_id`) REFERENCES `coordinator_announcements` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `coordinator_announcement_recipients_ibfk_2` FOREIGN KEY (`trainee_id`) REFERENCES `trainees` (`trainee_id`) ON DELETE CASCADE;

--
-- Constraints for table `coordinator_announcement_sections`
--
ALTER TABLE `coordinator_announcement_sections`
  ADD CONSTRAINT `coordinator_announcement_sections_ibfk_1` FOREIGN KEY (`announcement_id`) REFERENCES `coordinator_announcements` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `coordinator_trainees`
--
ALTER TABLE `coordinator_trainees`
  ADD CONSTRAINT `coordinator_trainees_ibfk_1` FOREIGN KEY (`coordinator_id`) REFERENCES `coordinators` (`coordinator_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `coordinator_trainees_ibfk_2` FOREIGN KEY (`trainee_id`) REFERENCES `trainees` (`trainee_id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD CONSTRAINT `password_reset_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `section_assignments`
--
ALTER TABLE `section_assignments`
  ADD CONSTRAINT `section_assignments_ibfk_1` FOREIGN KEY (`coordinator_id`) REFERENCES `coordinators` (`coordinator_id`) ON DELETE CASCADE;

--
-- Constraints for table `task_files`
--
ALTER TABLE `task_files`
  ADD CONSTRAINT `task_files_ibfk_1` FOREIGN KEY (`submission_id`) REFERENCES `task_submissions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `task_submissions`
--
ALTER TABLE `task_submissions`
  ADD CONSTRAINT `task_submissions_ibfk_1` FOREIGN KEY (`trainee_id`) REFERENCES `trainees` (`trainee_id`) ON DELETE CASCADE;

--
-- Constraints for table `trainees`
--
ALTER TABLE `trainees`
  ADD CONSTRAINT `trainees_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
