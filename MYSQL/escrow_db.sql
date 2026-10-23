-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Oct 23, 2026 at 12:25 PM
-- Server version: 8.4.3
-- PHP Version: 8.3.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `escrow_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `deals`
--

CREATE TABLE `deals` (
  `id` int NOT NULL,
  `deal_code` varchar(10) NOT NULL,
  `seller_id` int NOT NULL,
  `buyer_id` int DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `description` text,
  `amount` decimal(12,2) NOT NULL,
  `delivery_method` varchar(50) DEFAULT NULL,
  `deadline` date DEFAULT NULL,
  `status` enum('DEAL_CREATED','AWAITING_BUYER','AWAITING_PAYMENT','FUNDS_HELD','DELIVERING','COMPLETED','DISPUTED','UNDER_REVIEW','RESOLVED','CANCELLED') DEFAULT 'AWAITING_BUYER',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `deals`
--

INSERT INTO `deals` (`id`, `deal_code`, `seller_id`, `buyer_id`, `title`, `description`, `amount`, `delivery_method`, `deadline`, `status`, `created_at`, `updated_at`) VALUES
(1, 'B04DE0', 2, 3, 'samsung', 'new', 150000.00, 'Meet-up', '2026-10-07', 'COMPLETED', '2026-10-07 08:53:02', '2026-10-13 09:44:41'),
(2, 'DD14B6', 2, 3, 'laptop', 'dell and brand new', 5000000.00, 'Bus', '2026-10-28', 'DISPUTED', '2026-10-14 08:05:04', '2026-10-14 08:11:38'),
(3, 'FDEF53', 2, 3, 'desktop', 'hp', 1000000.00, 'Courier', '2026-10-28', 'RESOLVED', '2026-10-16 07:00:27', '2026-10-16 08:07:58'),
(4, 'E6B647', 2, 3, 'fridge', 'upright purple color fridge from SA', 1000000.00, 'Bus', '2026-10-28', 'DISPUTED', '2026-10-23 07:41:00', '2026-10-23 07:42:25');

-- --------------------------------------------------------

--
-- Table structure for table `disputes`
--

CREATE TABLE `disputes` (
  `id` int NOT NULL,
  `deal_id` int NOT NULL,
  `opened_by` int NOT NULL,
  `reason` varchar(100) DEFAULT NULL,
  `description` text,
  `status` enum('open','under_review','resolved') DEFAULT 'open',
  `resolution` enum('refund','release','split') DEFAULT NULL,
  `resolved_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `disputes`
--

INSERT INTO `disputes` (`id`, `deal_id`, `opened_by`, `reason`, `description`, `status`, `resolution`, `resolved_by`, `created_at`) VALUES
(1, 2, 2, 'Payment issue', 'money not recieved', 'open', NULL, NULL, '2026-10-14 08:11:38'),
(2, 3, 3, 'Item not as described', 'wanted hp but received Lenovo', 'resolved', 'refund', 4, '2026-10-16 07:02:51'),
(3, 4, 3, 'Damaged item', 'its not working', 'open', NULL, NULL, '2026-10-23 07:42:25');

-- --------------------------------------------------------

--
-- Table structure for table `evidence`
--

CREATE TABLE `evidence` (
  `id` int NOT NULL,
  `dispute_id` int NOT NULL,
  `uploaded_by` int NOT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `description` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `evidence`
--

INSERT INTO `evidence` (`id`, `dispute_id`, `uploaded_by`, `file_path`, `description`, `created_at`) VALUES
(1, 1, 3, 'uploads/evidence/dispute_1_1792402023_7553144f.png', 'my proof', '2026-10-19 09:27:03'),
(2, 3, 3, 'uploads/evidence/dispute_3_1792741368_f46abad8.png', 'damaged coil', '2026-10-23 07:42:48');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int NOT NULL,
  `deal_id` int NOT NULL,
  `sender_id` int NOT NULL,
  `body` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `deal_id` int DEFAULT NULL,
  `message` text,
  `is_read` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `deal_id`, `message`, `is_read`, `created_at`) VALUES
(1, 3, 1, 'You have been invited to deal #B04DE0 for MK 150,000.00', 1, '2026-10-07 08:53:02'),
(2, 2, 1, 'Money secured for deal #B04DE0. Please deliver the item.', 1, '2026-10-13 08:57:16'),
(3, 3, 1, 'Seller has marked deal #B04DE0 as delivered. Confirm receipt when you receive the item.', 1, '2026-10-13 09:22:23'),
(4, 2, 1, 'Money released for deal #B04DE0. Please CHECK your wallet.', 1, '2026-10-13 09:44:41'),
(5, 3, 2, 'You have been invited to deal #DD14B6 for MK 5,000,000.00', 1, '2026-10-14 08:05:04'),
(6, 2, 2, 'Money secured for deal #DD14B6. Please deliver the item.', 1, '2026-10-14 08:08:50'),
(7, 3, 2, 'A dispute has been opened on deal #DD14B6. Reason: Payment issue', 1, '2026-10-14 08:11:38'),
(8, 3, 3, 'You have been invited to deal #FDEF53 for MK 1,000,000.00', 1, '2026-10-16 07:00:27'),
(9, 2, 3, 'Money secured for deal #FDEF53. Please deliver the item.', 1, '2026-10-16 07:00:55'),
(10, 2, 3, 'A dispute has been opened on deal #FDEF53. Reason: Item not as described', 1, '2026-10-16 07:02:51'),
(11, 3, 3, 'Dispute resolved for deal #FDEF53. You have been refunded MK 1,000,000.00.', 1, '2026-10-16 08:07:58'),
(12, 2, 3, 'Dispute resolved for deal #FDEF53. The buyer has been refunded.', 1, '2026-10-16 08:07:58'),
(13, 2, 2, 'New evidence uploaded for the dispute on deal #2', 1, '2026-10-19 09:27:03'),
(14, 3, 4, 'You have been invited to deal #E6B647 for MK 1,000,000.00', 0, '2026-10-23 07:41:00'),
(15, 2, 4, 'Money secured for deal #E6B647. Please deliver the item.', 0, '2026-10-23 07:41:57'),
(16, 2, 4, 'A dispute has been opened on deal #E6B647. Reason: Damaged item', 0, '2026-10-23 07:42:25'),
(17, 4, 4, 'URGENT: A new dispute has been filed on deal #E6B647', 1, '2026-10-23 07:42:25'),
(18, 2, 4, 'New evidence uploaded for the dispute on deal #4', 0, '2026-10-23 07:42:48');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int NOT NULL,
  `deal_id` int NOT NULL,
  `from_user` int DEFAULT NULL,
  `to_user` int DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `type` enum('deposit','release','refund') NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `deal_id`, `from_user`, `to_user`, `amount`, `type`, `created_at`) VALUES
(1, 1, 3, NULL, 150000.00, 'deposit', '2026-10-13 08:57:16'),
(2, 1, NULL, 2, 150000.00, 'release', '2026-10-13 09:44:41'),
(3, 2, 3, NULL, 5000000.00, 'deposit', '2026-10-14 08:08:50'),
(4, 3, 3, NULL, 1000000.00, 'deposit', '2026-10-16 07:00:55'),
(5, 3, NULL, 3, 1000000.00, 'refund', '2026-10-16 08:07:58'),
(6, 4, 3, NULL, 1000000.00, 'deposit', '2026-10-23 07:41:57');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('buyer','seller','admin') DEFAULT 'buyer',
  `wallet_balance` decimal(12,2) DEFAULT '0.00',
  `trust_score` int DEFAULT '50',
  `is_banned` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `phone`, `email`, `password_hash`, `role`, `wallet_balance`, `trust_score`, `is_banned`, `created_at`) VALUES
(2, 'Bashil Phiri', '0987723144', NULL, '$2y$10$OakFCrkSAmBexvSnRle3Z.dTzZG8.6EugJ63g2uEOW7YZ87zgUu0i', 'seller', 1200000.00, 50, 0, '2026-10-07 08:05:43'),
(3, 'John Phiri', '0999741411', NULL, '$2y$10$/QA70bizAKKNiLSKfoUlrOwXQc/ybK81xtZa28JKo9e/6OygFSKGC', 'buyer', 350000.00, 50, 0, '2026-10-07 08:51:42'),
(4, 'System Admin', '0999999999', NULL, '$2y$10$LzUYcnXi5X6mXjDM9czpQuA7Il67PDZebIKet30v8Vo450FQM0TBa', 'admin', 0.00, 50, 0, '2026-10-16 07:56:41');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `deals`
--
ALTER TABLE `deals`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `deal_code` (`deal_code`),
  ADD KEY `seller_id` (`seller_id`),
  ADD KEY `buyer_id` (`buyer_id`);

--
-- Indexes for table `disputes`
--
ALTER TABLE `disputes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `deal_id` (`deal_id`),
  ADD KEY `opened_by` (`opened_by`);

--
-- Indexes for table `evidence`
--
ALTER TABLE `evidence`
  ADD PRIMARY KEY (`id`),
  ADD KEY `dispute_id` (`dispute_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `deal_id` (`deal_id`),
  ADD KEY `sender_id` (`sender_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `deal_id` (`deal_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `phone` (`phone`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `deals`
--
ALTER TABLE `deals`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `disputes`
--
ALTER TABLE `disputes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `evidence`
--
ALTER TABLE `evidence`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `deals`
--
ALTER TABLE `deals`
  ADD CONSTRAINT `deals_ibfk_1` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `deals_ibfk_2` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `disputes`
--
ALTER TABLE `disputes`
  ADD CONSTRAINT `disputes_ibfk_1` FOREIGN KEY (`deal_id`) REFERENCES `deals` (`id`),
  ADD CONSTRAINT `disputes_ibfk_2` FOREIGN KEY (`opened_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `evidence`
--
ALTER TABLE `evidence`
  ADD CONSTRAINT `evidence_ibfk_1` FOREIGN KEY (`dispute_id`) REFERENCES `disputes` (`id`);

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`deal_id`) REFERENCES `deals` (`id`),
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`deal_id`) REFERENCES `deals` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
