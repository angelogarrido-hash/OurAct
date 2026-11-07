-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 07, 2026 at 03:28 AM
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
-- Database: `tbusers`
--

-- --------------------------------------------------------

--
-- Table structure for table `tbattendance`
--

CREATE TABLE `tbattendance` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `attendance_date` date NOT NULL,
  `status` enum('Present','Absent','Late') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbdocument_requests`
--

CREATE TABLE `tbdocument_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `document_type` varchar(50) NOT NULL,
  `purpose` varchar(255) NOT NULL,
  `status` enum('Pending','Approved','Rejected','Released') NOT NULL DEFAULT 'Pending',
  `remarks` varchar(255) DEFAULT NULL,
  `requested_at` datetime NOT NULL DEFAULT current_timestamp(),
  `processed_by` int(11) DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL,
  `released_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbdocument_requests`
--

INSERT INTO `tbdocument_requests` (`id`, `user_id`, `document_type`, `purpose`, `status`, `remarks`, `requested_at`, `processed_by`, `processed_at`, `released_at`) VALUES
(1, 2, 'Transcript of Records', 'transfer', 'Released', 'transcript of records', '2026-11-07 10:04:43', 1, '2026-11-07 10:05:44', '2026-11-07 10:05:51'),
(2, 3, 'Certificate of Grades', 'to know if I can graduate', 'Rejected', 'study hard', '2026-11-07 10:07:54', 1, '2026-11-07 10:08:31', NULL),
(3, 2, 'Certificate of Enrollment', 'a', 'Pending', NULL, '2026-11-07 10:20:49', NULL, NULL, NULL),
(4, 2, 'Certificate of Grades', 'a', 'Pending', NULL, '2026-11-07 10:20:53', NULL, NULL, NULL),
(5, 2, 'Transcript of Records', 'a', 'Pending', NULL, '2026-11-07 10:20:57', NULL, NULL, NULL),
(6, 2, 'Good Moral Certificate', 'a', 'Pending', NULL, '2026-11-07 10:21:00', NULL, NULL, NULL),
(7, 2, 'Certificate of Graduation', 'a', 'Pending', NULL, '2026-11-07 10:21:02', NULL, NULL, NULL),
(8, 2, 'Certificate of Enrollment', 'enroll', 'Approved', NULL, '2026-11-07 10:26:27', 1, '2026-11-07 10:26:40', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tbstudent_subjects`
--

CREATE TABLE `tbstudent_subjects` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `grade` decimal(4,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbstudent_subjects`
--

INSERT INTO `tbstudent_subjects` (`id`, `user_id`, `subject_id`, `grade`) VALUES
(1, 2, 1, NULL),
(2, 2, 2, NULL),
(3, 2, 3, NULL),
(4, 3, 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tbsubjects`
--

CREATE TABLE `tbsubjects` (
  `id` int(11) NOT NULL,
  `subject_code` varchar(20) NOT NULL,
  `subject_name` varchar(100) NOT NULL,
  `units` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbsubjects`
--

INSERT INTO `tbsubjects` (`id`, `subject_code`, `subject_name`, `units`) VALUES
(1, 'IT111', 'Introduction to Computing', 3),
(2, 'IT112', 'Computer Programming 1', 3),
(3, 'IT113', 'Discrete Mathematics', 3),
(4, 'IT111', 'Introduction to Computing', 3),
(5, 'IT112', 'Computer Programming 1', 3),
(6, 'IT113', 'Discrete Mathematics', 3);

-- --------------------------------------------------------

--
-- Table structure for table `tbusers`
--

CREATE TABLE `tbusers` (
  `id` int(11) NOT NULL,
  `Role` varchar(10) DEFAULT NULL,
  `Username` varchar(10) DEFAULT NULL,
  `Password` int(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbusers`
--

INSERT INTO `tbusers` (`id`, `Role`, `Username`, `Password`) VALUES
(1, 'Admin', 'admin', 321),
(2, 'Student', 'student', 123),
(3, 'Student', 'student2', 456);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `tbattendance`
--
ALTER TABLE `tbattendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_attendance` (`user_id`,`subject_id`,`attendance_date`),
  ADD KEY `subject_id` (`subject_id`);

--
-- Indexes for table `tbdocument_requests`
--
ALTER TABLE `tbdocument_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `processed_by` (`processed_by`);

--
-- Indexes for table `tbstudent_subjects`
--
ALTER TABLE `tbstudent_subjects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_subject` (`user_id`,`subject_id`),
  ADD KEY `subject_id` (`subject_id`);

--
-- Indexes for table `tbsubjects`
--
ALTER TABLE `tbsubjects`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbusers`
--
ALTER TABLE `tbusers`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `tbattendance`
--
ALTER TABLE `tbattendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbdocument_requests`
--
ALTER TABLE `tbdocument_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `tbstudent_subjects`
--
ALTER TABLE `tbstudent_subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `tbsubjects`
--
ALTER TABLE `tbsubjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `tbusers`
--
ALTER TABLE `tbusers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `tbattendance`
--
ALTER TABLE `tbattendance`
  ADD CONSTRAINT `tbattendance_ibfk_1` FOREIGN KEY (`user_id`,`subject_id`) REFERENCES `tbstudent_subjects` (`user_id`, `subject_id`) ON DELETE CASCADE;

--
-- Constraints for table `tbdocument_requests`
--
ALTER TABLE `tbdocument_requests`
  ADD CONSTRAINT `tbdocument_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `tbusers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tbdocument_requests_ibfk_2` FOREIGN KEY (`processed_by`) REFERENCES `tbusers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `tbstudent_subjects`
--
ALTER TABLE `tbstudent_subjects`
  ADD CONSTRAINT `tbstudent_subjects_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `tbusers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tbstudent_subjects_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `tbsubjects` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
