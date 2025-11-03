-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 03, 2025 at 06:13 AM
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
-- Database: `gosocially`
--

-- --------------------------------------------------------

--
-- Table structure for table `approved_messaging`
--

CREATE TABLE `approved_messaging` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `approved_user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Table structure for table `invite_codes`
--

CREATE TABLE `invite_codes` (
  `id` int(11) NOT NULL,
  `code` varchar(32) NOT NULL,
  `created_by_user_id` int(11) NOT NULL,
  `used_by_user_id` int(11) DEFAULT NULL,
  `is_used` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `used_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `invite_codes`
--

INSERT INTO `invite_codes` (`id`, `code`, `created_by_user_id`, `used_by_user_id`, `is_used`, `created_at`, `used_at`, `expires_at`) VALUES
(1, 'ABC123DEF456GHI789JKL012MNO345PQ', 1, NULL, 0, '2025-11-03 11:23:31', NULL, '2025-11-10 11:23:31'),
(2, 'XYZ789ABC456DEF123GHI456JKL789MN', 2, NULL, 0, '2025-11-03 11:23:31', NULL, '2025-11-10 11:23:31'),
(3, 'MNO345PQR678STU901VWX234YZA567BC', 3, NULL, 0, '2025-11-03 11:23:31', NULL, '2025-11-10 11:23:31'),
(4, 'STU901VWX234YZA567BCD890EFG123HI', 4, NULL, 0, '2025-11-03 11:23:31', NULL, '2025-11-10 11:23:31'),
(5, 'JKL012MNO345PQR678STU901VWX234YZ', 5, NULL, 0, '2025-11-03 11:23:31', NULL, '2025-11-10 11:23:31'),
(6, '7510c2780cfdd8cb733d6a87ec565bf7', 1, 7, 1, '2025-11-03 12:39:37', '2025-11-03 12:44:13', '2025-11-10 05:39:37'),
(7, '47041df6a82be25c19bf9674754c50c4', 1, 8, 1, '2025-11-03 12:48:16', '2025-11-03 12:50:16', '2025-11-10 05:48:16'),
(8, '74f89ed505e4325352d556e23626cb73', 8, NULL, 0, '2025-11-03 12:54:15', NULL, '2025-11-10 05:54:15');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message_text` text NOT NULL,
  `sent_at` datetime DEFAULT current_timestamp(),
  `is_read` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `sender_id`, `receiver_id`, `message_text`, `sent_at`, `is_read`) VALUES
(7, 1, 3, 'hey', '2025-11-03 12:04:16', 1),
(8, 1, 3, 'hey2', '2025-11-03 12:04:24', 1),
(9, 1, 3, 'hey', '2025-11-03 12:05:39', 1),
(10, 1, 3, 'sdsadasds\\', '2025-11-03 12:05:43', 1),
(11, 3, 1, 'yey', '2025-11-03 13:01:31', 1),
(12, 7, 8, 'pussy', '2025-11-03 13:09:49', 1),
(13, 8, 7, 'ritzussy', '2025-11-03 13:10:18', 0);

-- --------------------------------------------------------

--
-- Table structure for table `reports`
--

CREATE TABLE `reports` (
  `id` int(11) NOT NULL,
  `reporter_id` int(11) NOT NULL,
  `reported_user_id` int(11) NOT NULL,
  `report_type` enum('harassment','spam','inappropriate_content','fake_account','other') NOT NULL,
  `description` text NOT NULL,
  `status` enum('pending','under_review','resolved','dismissed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolved_by` int(11) DEFAULT NULL,
  `resolution_note` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `role` enum('admin','moderator','user') DEFAULT 'user',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `last_login` datetime DEFAULT NULL,
  `invited_by_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `full_name`, `role`, `is_active`, `created_at`, `last_login`, `invited_by_id`) VALUES
(1, 'admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin', 1, '2025-11-03 11:23:31', '2025-11-03 13:02:47', NULL),
(2, 'moderator', 'moderator@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Community Moderator', 'moderator', 1, '2025-11-03 11:23:31', '2025-11-03 09:23:31', NULL),
(3, 'user1', 'user1@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Test User One', 'user', 1, '2025-11-03 11:23:31', '2025-11-03 13:01:10', NULL),
(4, 'user2', 'user2@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Test User Two', 'user', 1, '2025-11-03 11:23:31', '2025-11-02 11:23:31', NULL),
(5, 'user3', 'user3@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Test User Three', 'user', 1, '2025-11-03 11:23:31', '2025-11-02 11:23:31', NULL),
(7, 'med123', 'user@gmail.com', '$2y$10$tPxODkxsQt7Y2OGh1YiMu.41dDeX7BF2sac1kPzvmdmYZ0ra5RkFm', '', 'user', 1, '2025-11-03 12:44:13', '2025-11-03 13:09:40', 1),
(8, 'Kuraku', 'Kuraku@gmail.com', '$2y$10$C1jLkx1Fillc1DjH21cnfu8A1V3l5WOmikNCwsELn6HvtIehP3CTy', '', 'user', 1, '2025-11-03 12:50:16', '2025-11-03 13:10:01', 1);

-- --------------------------------------------------------

--
-- Table structure for table `user_relationships`
--

CREATE TABLE `user_relationships` (
  `id` int(11) NOT NULL,
  `follower_id` int(11) NOT NULL,
  `following_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ;

--
-- Dumping data for table `user_relationships`
--

INSERT INTO `user_relationships` (`id`, `follower_id`, `following_id`, `created_at`) VALUES
(1, 7, 8, '2025-11-03 04:59:39'),
(2, 8, 7, '2025-11-03 05:00:00'),
(5, 3, 8, '2025-11-03 05:01:56'),
(6, 1, 8, '2025-11-03 05:03:02');

-- --------------------------------------------------------

--
-- Table structure for table `user_restrictions`
--

CREATE TABLE `user_restrictions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `restricted_user_id` int(11) DEFAULT NULL,
  `restriction_type` enum('timeout','ban','messaging_restriction') NOT NULL,
  `reason` text NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_settings`
--

CREATE TABLE `user_settings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `messaging_permission` enum('everyone','mutuals_only','approved_only') DEFAULT 'everyone',
  `presence_status` enum('active','busy','invisible') DEFAULT 'active',
  `allow_follow_requests` tinyint(1) DEFAULT 1,
  `show_online_status` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_settings`
--

INSERT INTO `user_settings` (`id`, `user_id`, `messaging_permission`, `presence_status`, `allow_follow_requests`, `show_online_status`) VALUES
(1, 3, 'everyone', 'invisible', 0, 0),
(2, 1, 'everyone', 'active', 0, 0),
(3, 8, 'everyone', 'active', 1, 1),
(4, 7, 'everyone', 'active', 1, 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `approved_messaging`
--
ALTER TABLE `approved_messaging`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_approval` (`user_id`,`approved_user_id`),
  ADD KEY `idx_approved_messaging_user_id` (`user_id`),
  ADD KEY `idx_approved_messaging_approved_user_id` (`approved_user_id`);

--
-- Indexes for table `invite_codes`
--
ALTER TABLE `invite_codes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `created_by_user_id` (`created_by_user_id`),
  ADD KEY `used_by_user_id` (`used_by_user_id`),
  ADD KEY `idx_invite_codes_code` (`code`),
  ADD KEY `idx_invite_codes_unused` (`is_used`,`expires_at`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_messages_sender_receiver` (`sender_id`,`receiver_id`),
  ADD KEY `idx_messages_receiver_unread` (`receiver_id`,`is_read`);

--
-- Indexes for table `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `resolved_by` (`resolved_by`),
  ADD KEY `idx_reports_reporter` (`reporter_id`),
  ADD KEY `idx_reports_reported_user` (`reported_user_id`),
  ADD KEY `idx_reports_status` (`status`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `invited_by_id` (`invited_by_id`),
  ADD KEY `idx_users_username` (`username`),
  ADD KEY `idx_users_email` (`email`),
  ADD KEY `idx_users_active` (`is_active`);

--
-- Indexes for table `user_relationships`
--
ALTER TABLE `user_relationships`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_follow` (`follower_id`,`following_id`),
  ADD KEY `idx_user_relationships_follower` (`follower_id`),
  ADD KEY `idx_user_relationships_following` (`following_id`);

--
-- Indexes for table `user_restrictions`
--
ALTER TABLE `user_restrictions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `restricted_user_id` (`restricted_user_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_user_restrictions_user_id` (`user_id`),
  ADD KEY `idx_user_restrictions_active` (`user_id`,`is_active`);

--
-- Indexes for table `user_settings`
--
ALTER TABLE `user_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD KEY `idx_user_settings_user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `approved_messaging`
--
ALTER TABLE `approved_messaging`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `invite_codes`
--
ALTER TABLE `invite_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `reports`
--
ALTER TABLE `reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `user_relationships`
--
ALTER TABLE `user_relationships`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_restrictions`
--
ALTER TABLE `user_restrictions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_settings`
--
ALTER TABLE `user_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `approved_messaging`
--
ALTER TABLE `approved_messaging`
  ADD CONSTRAINT `approved_messaging_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `approved_messaging_ibfk_2` FOREIGN KEY (`approved_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `invite_codes`
--
ALTER TABLE `invite_codes`
  ADD CONSTRAINT `invite_codes_ibfk_1` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `invite_codes_ibfk_2` FOREIGN KEY (`used_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reports`
--
ALTER TABLE `reports`
  ADD CONSTRAINT `reports_ibfk_1` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reports_ibfk_2` FOREIGN KEY (`reported_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reports_ibfk_3` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`invited_by_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `user_relationships`
--
ALTER TABLE `user_relationships`
  ADD CONSTRAINT `user_relationships_ibfk_1` FOREIGN KEY (`follower_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_relationships_ibfk_2` FOREIGN KEY (`following_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_restrictions`
--
ALTER TABLE `user_restrictions`
  ADD CONSTRAINT `user_restrictions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_restrictions_ibfk_2` FOREIGN KEY (`restricted_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_restrictions_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_settings`
--
ALTER TABLE `user_settings`
  ADD CONSTRAINT `user_settings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
