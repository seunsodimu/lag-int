-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 08, 2025 at 02:35 AM
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
-- Database: `integration_db`
--

-- --------------------------------------------------------

--
-- Stand-in structure for view `active_users`
-- (See below for the actual view)
--
CREATE TABLE `active_users` (
`id` int(11)
,`username` varchar(50)
,`email` varchar(100)
,`role` enum('admin','user')
,`first_name` varchar(50)
,`last_name` varchar(50)
,`created_at` timestamp
,`last_login` timestamp
,`status` varchar(8)
);

-- --------------------------------------------------------

--
-- Table structure for table `integration_activity_log`
--

CREATE TABLE `integration_activity_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `activity_type` enum('order_processing','customer_creation','manual_upload','configuration_change') NOT NULL,
  `order_id` varchar(50) DEFAULT NULL,
  `customer_id` varchar(50) DEFAULT NULL,
  `status` enum('success','failure','pending') NOT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `error_message` text DEFAULT NULL,
  `processing_time_ms` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','user') NOT NULL DEFAULT 'user',
  `first_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `failed_login_attempts` int(11) DEFAULT 0,
  `locked_until` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `role`, `first_name`, `last_name`, `is_active`, `created_at`, `updated_at`, `last_login`, `failed_login_attempts`, `locked_until`) VALUES
(1, 'admin', 'seun_sodimu@lagunatools.com', '$2y$10$jyXFOT0BBtzwvvg/HSdgg.ls6d40tT37p5sw5pweoe6b392AWfNm2', 'admin', 'System', 'Administrator', 1, '2025-08-13 15:28:28', '2025-09-04 17:23:24', '2025-09-04 17:23:24', 0, NULL),
(2, 'testuser_1755098986', 'testuser_1755098986@example.com', '$2y$10$dSPxYzKIDEGkNlDFpdGP.O53A/WGT1c0LSDUpW6bH6OaLpQ/1yZF6', 'user', 'Test', 'User', 1, '2025-08-13 15:29:46', '2025-08-13 15:29:46', NULL, 0, NULL),
(3, 'testuser_1755099802', 'testuser_1755099802@example.com', '$2y$10$YBk5afMDBID.LtIWHUkSh.BwRBWHOT2ttXVh5BwA3XIg8256Agkmi', 'user', 'Test', 'User', 1, '2025-08-13 15:43:22', '2025-08-13 15:43:22', NULL, 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_activity_log`
--

CREATE TABLE `user_activity_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_activity_log`
--

INSERT INTO `user_activity_log` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 'login_failed', 'Invalid password', '', '', '2025-08-13 15:29:11'),
(2, 1, 'login_success', 'User logged in successfully', '', '', '2025-08-13 15:29:46'),
(3, 1, 'user_created', 'Created user: testuser_1755098986 (ID: 2)', '', '', '2025-08-13 15:29:46'),
(4, 1, 'logout', 'User logged out', '', '', '2025-08-13 15:29:46'),
(5, 1, 'login_success', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-13 15:33:12'),
(6, 1, 'login_success', 'User logged in successfully', '', '', '2025-08-13 15:43:22'),
(7, 1, 'user_created', 'Created user: testuser_1755099802 (ID: 3)', '', '', '2025-08-13 15:43:22'),
(8, 1, 'logout', 'User logged out', '', '', '2025-08-13 15:43:22'),
(9, 1, 'logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-13 15:44:50'),
(10, 1, 'login_success', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-13 15:45:02'),
(11, 1, 'logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-14 19:26:03'),
(12, 1, 'login_success', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-14 19:26:13'),
(13, 1, 'logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-14 19:33:26'),
(14, 1, 'login_success', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-14 19:33:46'),
(15, 1, 'login_success', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-14 19:34:32'),
(16, 1, 'login_success', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-15 13:51:14'),
(17, 1, 'login_success', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-18 14:46:28'),
(18, 1, 'login_success', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-19 14:36:23'),
(19, 1, 'login_success', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-20 17:58:07'),
(20, 1, 'login_success', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-21 14:42:57'),
(21, 1, 'login_success', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-21 16:19:57'),
(22, 1, 'login_success', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-22 22:22:01'),
(23, 1, 'login_success', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-23 17:51:15'),
(24, 1, 'login_success', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-27 15:52:26'),
(25, 1, 'login_success', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-28 16:23:45'),
(26, 1, 'login_success', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-29 17:11:53'),
(27, 1, 'login_success', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 14:32:57'),
(28, 1, 'logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 15:37:08'),
(29, 1, 'login_success', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 15:37:14'),
(30, 1, 'logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 16:16:54'),
(31, 1, 'login_success', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 16:17:00'),
(32, 1, 'login_success', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-04 17:23:24');

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` varchar(128) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_sessions`
--

INSERT INTO `user_sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `created_at`, `expires_at`, `is_active`) VALUES
('00b61e84bc7959cceedf2492e044d55b80a2c29dfe0971456db3dc9c4fdcc4c1', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-15 13:51:14', '2025-08-16 04:51:14', 1),
('0378c5f980838680dffed8fcd81bd76ea471a0ce7123c147a990f59e5b6aaaed', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-14 19:33:46', '2025-08-15 10:33:46', 1),
('06a2db85bc04f32c9c17294265d402ea3b7635f2bdac17219cb729844882d40b', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-21 14:42:57', '2025-08-22 05:42:57', 1),
('099123ad9dcc1ddf6637560dd1d5d88eac639c02693a2965137de6a0f8d8c005', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 15:37:14', '2025-09-03 06:37:14', 0),
('0f06e3a77ffa1c10b783e7ed3d906a349f1acb0e7929296788a69e90ff54045d', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-29 17:11:53', '2025-08-30 08:11:53', 1),
('119e326fddd45330b9d048a9a90d889cb092a5ec522f73e159dea84bcf9e02f4', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-13 15:33:12', '2025-09-12 22:33:12', 0),
('264b743b016d26e04eadf5fa951db29f420a4d55083908e7998de5654716e610', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-28 16:23:45', '2025-08-29 07:23:45', 1),
('2cf534537aaf8b9b7ac8b61a49f766f5e5973cf304d898a9747e189fa47a125f', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-13 15:45:02', '2025-09-12 22:45:02', 0),
('5972a1525c47dfdb2b52c1ec6e04bf1cd471d3375f6744b847c1e3d1eb420565', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-19 14:36:23', '2025-08-20 05:36:23', 1),
('6107a0a7afe404a193febb682eaec7f28d2fa6c0b2dbabd432ba07a841dd005f', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-22 22:22:01', '2025-08-23 13:22:01', 1),
('6a14211281eaa3ee283c5f1d58faa47933f8ce44045e60aa205b9c643e88e3a2', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-20 17:58:07', '2025-08-21 08:58:07', 1),
('72adb61a4d7512485ba6b471d63c1d649b3ab923ba25526817ee629ad2f281d6', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-04 17:23:23', '2025-09-05 08:23:23', 1),
('7444c5e039ba511422b6b19a88a6519f8d814854e51a0e703059c6d7cdd2437b', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-21 16:19:57', '2025-08-22 07:19:57', 1),
('8b239de14d76d0ed212b78fa7cbcb8d30496cfbea4f72bef59a800627dd5dac9', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-14 19:34:32', '2025-08-15 10:34:32', 1),
('9e9ed431580f7169f1d1753459d09fd94f9e9e03ab2c84fed0727a4b52a20423', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-14 19:26:13', '2025-08-15 10:26:13', 0),
('b17c1b7a7d92343ebef4ce147328c64d9bd106cc1ec01fce53b439976b41a8fe', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-23 17:51:15', '2025-08-24 08:51:15', 1),
('b3d64df67e48501adb167af0ffa284b4e9a71c3ca7ef5b666ec7a5aca7cd5d5f', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 16:17:00', '2025-09-03 07:17:00', 1),
('d8c452242d687bcdd5c7dcd7bc7daf17e9b7977a6f6c0473b93255a56a665e6b', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-27 15:52:26', '2025-08-28 06:52:26', 1),
('e5b8b8b8b8b8b8b8b8b8b8b8b8b8b8b8b8b8b8b8b8b8b8b8b8b8b8b8b8b8b8b8', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 14:32:57', '2025-09-03 05:32:57', 1);

-- --------------------------------------------------------

--
-- Stand-in structure for view `user_session_summary`
-- (See below for the actual view)
--
CREATE TABLE `user_session_summary` (
`id` int(11)
,`username` varchar(50)
,`email` varchar(100)
,`role` enum('admin','user')
,`active_sessions` bigint(21)
,`last_session_start` timestamp
);

-- --------------------------------------------------------

--
-- Structure for view `active_users`
--
DROP TABLE IF EXISTS `active_users`;

CREATE VIEW `active_users` AS SELECT `users`.`id` AS `id`, `users`.`username` AS `username`, `users`.`email` AS `email`, `users`.`role` AS `role`, `users`.`first_name` AS `first_name`, `users`.`last_name` AS `last_name`, `users`.`created_at` AS `created_at`, `users`.`last_login` AS `last_login`, CASE WHEN `users`.`locked_until` is not null AND `users`.`locked_until` > current_timestamp() THEN 'locked' WHEN `users`.`is_active` = 1 THEN 'active' ELSE 'inactive' END AS `status` FROM `users` WHERE `users`.`is_active` = 1 ;

-- --------------------------------------------------------

--
-- Structure for view `user_session_summary`
--
DROP TABLE IF EXISTS `user_session_summary`;

CREATE VIEW `user_session_summary` AS SELECT `u`.`id` AS `id`, `u`.`username` AS `username`, `u`.`email` AS `email`, `u`.`role` AS `role`, count(`s`.`id`) AS `active_sessions`, max(`s`.`created_at`) AS `last_session_start` FROM (`users` `u` left join `user_sessions` `s` on(`u`.`id` = `s`.`user_id` and `s`.`is_active` = 1 and `s`.`expires_at` > current_timestamp())) GROUP BY `u`.`id`, `u`.`username`, `u`.`email`, `u`.`role` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `integration_activity_log`
--
ALTER TABLE `integration_activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id_integration` (`user_id`),
  ADD KEY `idx_activity_type` (`activity_type`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at_integration` (`created_at`),
  ADD KEY `idx_order_id` (`order_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `user_activity_log`
--
ALTER TABLE `user_activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id_activity` (`user_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_created_at_activity` (`created_at`);

--
-- Indexes for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id_sessions` (`user_id`),
  ADD KEY `idx_expires` (`expires_at`),
  ADD KEY `idx_active_sessions` (`is_active`),
  ADD KEY `idx_sessions_cleanup` (`expires_at`,`is_active`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `integration_activity_log`
--
ALTER TABLE `integration_activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `user_activity_log`
--
ALTER TABLE `user_activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `integration_activity_log`
--
ALTER TABLE `integration_activity_log`
  ADD CONSTRAINT `integration_activity_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `user_activity_log`
--
ALTER TABLE `user_activity_log`
  ADD CONSTRAINT `user_activity_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;