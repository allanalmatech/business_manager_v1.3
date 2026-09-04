-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 16, 2026 at 04:26 PM
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
-- Database: `business_manager`
--

-- --------------------------------------------------------

--
-- Table structure for table `approvals`
--

CREATE TABLE `approvals` (
  `id` bigint(20) NOT NULL,
  `approval_type` varchar(80) NOT NULL,
  `reference_table` varchar(80) DEFAULT NULL,
  `reference_id` varchar(80) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `requested_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` bigint(20) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity` varchar(80) DEFAULT NULL,
  `entity_id` varchar(80) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity`, `entity_id`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, NULL, 'auth.login_failed', 'user', 'admin', 'Invalid credentials', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 07:15:52'),
(2, NULL, 'auth.login_failed', 'user', 'admin', 'Invalid credentials', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 07:16:09'),
(3, 1, 'auth.login_success', 'user', '1', 'Login successful', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 07:16:34'),
(4, 1, 'auth.logout', 'user', '1', 'User logged out', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 07:19:15'),
(5, 2, 'auth.login_success', 'user', '2', 'Login successful', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 07:19:45'),
(6, 2, 'auth.logout', 'user', '2', 'User logged out', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 07:20:21'),
(7, 3, 'auth.login_success', 'user', '3', 'Login successful', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 07:20:41'),
(8, 3, 'auth.logout', 'user', '3', 'User logged out', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 07:21:37'),
(9, NULL, 'auth.login_failed', 'user', 'admin', 'Invalid credentials', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 07:21:40'),
(10, 1, 'auth.login_success', 'user', '1', 'Login successful', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 07:21:53'),
(11, 1, 'auth.login_success', 'user', '1', 'Login successful', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 10:15:28'),
(12, 1, 'products.category_create', 'product_category', '1', 'Created category: Ceramic Cup', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 10:17:37'),
(13, 1, 'products.create', 'product', '1', 'Created: 12132', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 10:55:49'),
(14, 1, 'products.update', 'product', '1', 'Updated: 12132', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 10:56:15'),
(15, 1, 'products.update', 'product', '1', 'Updated: 12132', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 12:42:35'),
(16, 1, 'products.update', 'product', '1', 'Updated: 12132', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 12:52:15'),
(17, 1, 'stock.transfer', 'product', '1', 'Transfer #1 from 2 to 1 qty 5', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 12:56:54'),
(18, 1, 'products.update', 'product', '1', 'Updated: 12132', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 13:27:09'),
(19, 1, 'stock.transfer', 'product', '1', 'Transfer #2 from 1 to 2 qty 4', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 13:28:47'),
(20, 1, 'products.stock_adjustment', 'product', '1', 'Adjustment: -2 at location 2 (Damage)', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 13:34:46'),
(21, 1, 'auth.login_success', 'user', '1', 'Login successful', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 15:52:42'),
(22, 1, 'auth.login_success', 'user', '1', 'Login successful', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 15:56:58'),
(23, 1, 'products.price_update', 'product', '1', 'Price update: Supplier Price Change', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 16:30:51'),
(24, 1, 'auth.login_success', 'user', '1', 'Login successful', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 17:04:27'),
(25, 1, 'products.image_upload', 'product', '1', 'Uploaded image: GEN_RC_002.jpg', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 17:09:01'),
(26, 1, 'products.image_delete', 'product', '1', 'Deleted image: uploads/products/GEN_RC_001.jpg', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 17:09:05'),
(27, 1, 'products.update', 'product', '1', 'Updated: 12132', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 17:09:06'),
(28, 1, 'products.image_upload', 'product', '1', 'Uploaded image: GEN_RC_003.jpg', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 17:12:35'),
(29, 1, 'products.image_delete', 'product', '1', 'Deleted image: uploads/products/GEN_RC_002.jpg', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 17:12:39'),
(30, 1, 'products.image_upload', 'product', '1', 'Uploaded image: GEN_RC_004.jpg', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 17:13:07'),
(31, 1, 'products.image_delete', 'product', '1', 'Deleted image: uploads/products/GEN_RC_003.jpg', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 17:13:10'),
(32, 1, 'products.update', 'product', '1', 'Updated: 12132', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 17:13:12'),
(33, 1, 'products.image_import', 'product', '1', 'Imported image from URL: GEN_RC_005.jpg', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 17:15:37'),
(34, 1, 'products.image_import', 'product', '1', 'Imported image from URL: GEN_RC_006.jpg', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 17:20:39'),
(35, 1, 'products.update', 'product', '1', 'Updated: 12132', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 17:20:57'),
(36, 1, 'products.image_upload', 'product', '1', 'Uploaded image: GEN_RC_007.jpg', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 17:21:10'),
(37, 1, 'products.image_delete', 'product', '1', 'Deleted image: uploads/products/GEN_RC_006.jpg', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 17:21:13'),
(38, 1, 'products.update', 'product', '1', 'Updated: 12132', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 17:21:15'),
(39, 1, 'products.image_delete', 'product', '1', 'Deleted image: uploads/products/GEN_RC_004.jpg', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 17:21:50'),
(40, 1, 'products.image_delete', 'product', '1', 'Deleted image: uploads/products/GEN_RC_005.jpg', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 17:21:56'),
(41, 1, 'products.update', 'product', '1', 'Updated: 12132', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 17:21:57'),
(42, 1, 'auth.login_success', 'user', '1', 'Login successful', '192.168.100.87', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', '2026-01-23 17:30:45'),
(43, NULL, 'auth.login_failed', 'user', 'admin', 'Invalid credentials', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 17:35:36'),
(44, 1, 'auth.login_success', 'user', '1', 'Login successful', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 17:35:44'),
(45, 1, 'auth.login_success', 'user', '1', 'Login successful', '192.168.100.87', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', '2026-01-23 17:38:38'),
(46, 1, 'products.image_upload', 'product', '1', 'Uploaded image: GEN_RC_008.jpg', '192.168.100.87', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', '2026-01-23 17:41:42'),
(47, 1, 'auth.login_success', 'user', '1', 'Login successful', '192.168.100.87', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/29.0 Chrome/136.0.0.0 Mobile Safari/537.36', '2026-01-23 17:43:23'),
(48, 1, 'products.image_delete', 'product', '1', 'Deleted image: uploads/products/GEN_RC_008.jpg', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 17:43:50'),
(49, 1, 'products.image_upload', 'product', '1', 'Uploaded image: GEN_RC_008.jpg', '192.168.100.87', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', '2026-01-23 17:43:56'),
(50, 1, 'products.image_delete', 'product', '1', 'Deleted image: uploads/products/GEN_RC_008.jpg', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 17:45:03'),
(51, 1, 'products.image_upload', 'product', '1', 'Uploaded image: GEN_RC_008.jpg', '192.168.100.87', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', '2026-01-23 17:45:20'),
(52, 1, 'products.image_delete', 'product', '1', 'Deleted image: uploads/products/GEN_RC_008.jpg', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 17:46:17'),
(53, 1, 'products.image_upload', 'product', '1', 'Uploaded image: GEN_RC_008.jpg', '192.168.100.87', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', '2026-01-23 17:46:40'),
(54, 1, 'products.image_upload', 'product', '1', 'Uploaded image: GEN_RC_009.jpg', '192.168.100.87', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', '2026-01-23 17:53:03'),
(55, 1, 'products.image_upload', 'product', '1', 'Uploaded image: GEN_RC_010.jpg', '192.168.100.87', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', '2026-01-23 18:04:20'),
(56, 1, 'products.image_upload', 'product', '1', 'Uploaded image: GEN_RC_011.jpg', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 18:04:47'),
(57, 1, 'products.image_delete', 'product', '1', 'Deleted image: uploads/products/GEN_RC_010.jpg', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 18:04:53'),
(58, 1, 'products.image_upload', 'product', '1', 'Uploaded image: GEN_RC_012.jpg', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 18:05:43'),
(59, 1, 'products.image_delete', 'product', '1', 'Deleted image: uploads/products/GEN_RC_011.jpg', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 18:05:52'),
(60, 1, 'auth.login_success', 'user', '1', 'Login successful', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 18:09:09'),
(61, 1, 'products.image_upload', 'product', '1', 'Uploaded image: GEN_RC_013.jpg', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 11; SAMSUNG SM-G973U) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/14.2 Chrome/87.0.4280.141 Mobile Safari/537.36', '2026-01-23 18:09:46'),
(62, 1, 'products.image_delete', 'product', '1', 'Deleted image: uploads/products/GEN_RC_013.jpg', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 11; SAMSUNG SM-G973U) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/14.2 Chrome/87.0.4280.141 Mobile Safari/537.36', '2026-01-23 18:09:52'),
(63, 1, 'products.update', 'product', '1', 'Updated: 12132', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 18:12:46'),
(64, 1, 'products.update', 'product', '1', 'Updated: 12132', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 18:13:09'),
(65, 1, 'products.image_upload', 'product', '1', 'Uploaded image: GEN_RC_013.jpg', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 18:13:31'),
(66, 1, 'products.update', 'product', '1', 'Updated: 12132', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 18:13:32'),
(67, 1, 'auth.login_success', 'user', '1', 'Login successful', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 18:14:44'),
(68, 1, 'products.update', 'product', '1', 'Updated: 12132', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 18:15:19'),
(69, 1, 'products.image_delete', 'product', '1', 'Deleted image: uploads/products/GEN_RC_013.jpg', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 11; SAMSUNG SM-G973U) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/14.2 Chrome/87.0.4280.141 Mobile Safari/537.36', '2026-01-23 18:17:37'),
(70, 1, 'products.image_upload', 'product', '1', 'Uploaded image: GEN_RC_013.jpg', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 11; SAMSUNG SM-G973U) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/14.2 Chrome/87.0.4280.141 Mobile Safari/537.36', '2026-01-23 18:17:43'),
(71, 1, 'products.image_delete', 'product', '1', 'Deleted image: uploads/products/GEN_RC_013.jpg', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 11; SAMSUNG SM-G973U) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/14.2 Chrome/87.0.4280.141 Mobile Safari/537.36', '2026-01-23 18:17:54'),
(72, 1, 'products.image_delete', 'product', '1', 'Deleted image: uploads/products/GEN_RC_012.jpg', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 11; SAMSUNG SM-G973U) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/14.2 Chrome/87.0.4280.141 Mobile Safari/537.36', '2026-01-23 18:17:56'),
(73, 1, 'products.image_delete', 'product', '1', 'Deleted image: uploads/products/GEN_RC_007.jpg', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 11; SAMSUNG SM-G973U) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/14.2 Chrome/87.0.4280.141 Mobile Safari/537.36', '2026-01-23 18:17:58'),
(74, 1, 'products.image_delete', 'product', '1', 'Deleted image: uploads/products/GEN_RC_008.jpg', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 11; SAMSUNG SM-G973U) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/14.2 Chrome/87.0.4280.141 Mobile Safari/537.36', '2026-01-23 18:18:00'),
(75, 1, 'products.image_delete', 'product', '1', 'Deleted image: uploads/products/GEN_RC_009.jpg', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 11; SAMSUNG SM-G973U) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/14.2 Chrome/87.0.4280.141 Mobile Safari/537.36', '2026-01-23 18:18:02'),
(76, 1, 'products.image_upload', 'product', '1', 'Uploaded image: GEN_RC_001.jpg', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 11; SAMSUNG SM-G973U) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/14.2 Chrome/87.0.4280.141 Mobile Safari/537.36', '2026-01-23 18:19:06'),
(77, 1, 'products.image_upload', 'product', '1', 'Uploaded image: GEN_RC_002.jpg', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 11; SAMSUNG SM-G973U) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/14.2 Chrome/87.0.4280.141 Mobile Safari/537.36', '2026-01-23 18:19:06'),
(78, 1, 'products.image_upload', 'product', '1', 'Uploaded image: GEN_RC_003.jpg', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 11; SAMSUNG SM-G973U) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/14.2 Chrome/87.0.4280.141 Mobile Safari/537.36', '2026-01-23 18:19:06'),
(79, 1, 'products.image_upload', 'product', '1', 'Uploaded image: GEN_RC_004.jpg', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 11; SAMSUNG SM-G973U) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/14.2 Chrome/87.0.4280.141 Mobile Safari/537.36', '2026-01-23 18:19:06'),
(80, 1, 'products.image_upload', 'product', '1', 'Uploaded image: GEN_RC_005.jpg', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 11; SAMSUNG SM-G973U) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/14.2 Chrome/87.0.4280.141 Mobile Safari/537.36', '2026-01-23 18:19:06'),
(81, 1, 'products.image_delete', 'product', '1', 'Deleted image: uploads/products/GEN_RC_005.jpg', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 11; SAMSUNG SM-G973U) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/14.2 Chrome/87.0.4280.141 Mobile Safari/537.36', '2026-01-23 18:19:55'),
(82, 1, 'products.image_delete', 'product', '1', 'Deleted image: uploads/products/GEN_RC_004.jpg', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 11; SAMSUNG SM-G973U) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/14.2 Chrome/87.0.4280.141 Mobile Safari/537.36', '2026-01-23 18:19:57'),
(83, 1, 'products.image_delete', 'product', '1', 'Deleted image: uploads/products/GEN_RC_001.jpg', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 11; SAMSUNG SM-G973U) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/14.2 Chrome/87.0.4280.141 Mobile Safari/537.36', '2026-01-23 18:19:59'),
(84, 1, 'products.image_delete', 'product', '1', 'Deleted image: uploads/products/GEN_RC_002.jpg', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 11; SAMSUNG SM-G973U) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/14.2 Chrome/87.0.4280.141 Mobile Safari/537.36', '2026-01-23 18:20:02'),
(85, 1, 'products.image_delete', 'product', '1', 'Deleted image: uploads/products/GEN_RC_003.jpg', '127.0.0.1', 'Mozilla/5.0 (Linux; Android 11; SAMSUNG SM-G973U) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/14.2 Chrome/87.0.4280.141 Mobile Safari/537.36', '2026-01-23 18:20:04'),
(86, 1, 'products.image_import', 'product', '1', 'Imported image from URL: GEN_RC_001.jpg', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 18:22:32'),
(87, 1, 'products.image_import', 'product', '1', 'Imported image from URL: GEN_RC_001.jpg', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-23 18:22:32'),
(88, 1, 'products.image_upload', 'product', '1', 'Uploaded image: GEN_RC_002.jpg', '192.168.100.87', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', '2026-01-23 18:25:03'),
(89, 1, 'products.image_upload', 'product', '1', 'Uploaded image: GEN_RC_003.jpg', '192.168.100.87', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', '2026-01-23 18:25:03'),
(90, 1, 'auth.login_success', 'user', '1', 'Login successful', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-24 06:57:45'),
(91, 1, 'auth.login_success', 'user', '1', 'Login successful', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-24 09:41:26'),
(92, 1, 'auth.login_success', 'user', '1', 'Login successful', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-24 12:00:55'),
(93, 1, 'auth.login_success', 'user', '1', 'Login successful', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-24 19:34:59'),
(94, 1, 'auth.login_success', 'user', '1', 'Login successful', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-24 20:00:38'),
(95, 1, 'auth.login_success', 'user', '1', 'Login successful', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-25 09:48:03'),
(96, 1, 'auth.login_success', 'user', '1', 'Login successful', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-25 09:58:07'),
(97, 1, 'auth.login_success', 'user', '1', 'Login successful', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-25 10:07:01'),
(98, 1, 'auth.login_success', 'user', '1', 'Login successful', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-25 10:30:48'),
(99, 1, 'auth.login_success', 'user', '1', 'Login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-01-25 11:51:42'),
(100, 1, 'auth.login_success', 'user', '1', 'Login successful', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-01-25 12:53:11'),
(101, 1, 'auth.login_success', 'user', '1', 'Login successful', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-01-25 14:55:13'),
(102, 1, 'products.stock_in', 'product', '1', 'Stock in: 10 units', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-01-25 14:55:42'),
(103, 1, 'products.stock_in', 'product', '1', 'Stock in: 2 units', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-01-25 14:58:39'),
(104, 1, 'products.stock_in', 'product', '1', 'Stock in: 10 units', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-01-25 15:03:34'),
(105, 1, 'products.stock_in', 'product', '1', 'Stock in: 3 units', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-01-25 15:04:16'),
(106, 1, 'products.stock_in', 'product', '1', 'Stock in: 10 units', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-01-25 15:05:00'),
(107, 1, 'products.stock_in', 'product', '1', 'Stock in: 1 units', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-01-25 15:07:05'),
(108, 1, 'products.stock_in', 'product', '1', 'Stock in: 0.5 units', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-01-25 15:07:33'),
(109, 1, 'products.stock_in', 'product', '1', 'Stock in: 0.5 units', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-01-25 15:07:55'),
(110, 1, 'auth.login_success', 'user', '1', 'Login successful', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-01-31 16:33:24'),
(111, NULL, 'auth.login_failed', 'user', 'admin@almatechconsults.com', 'Invalid credentials', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-01 11:57:10'),
(112, 1, 'auth.login_success', 'user', '1', 'Login successful', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-01 12:15:13'),
(113, 1, 'auth.login_success', 'user', '1', 'Login successful', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-02 07:23:47'),
(114, NULL, 'auth.login_failed', 'user', 'admin@almatechconsults.com', 'Invalid credentials', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-03 08:10:11'),
(115, NULL, 'auth.login_failed', 'user', 'admin@almatechconsults.com', 'Invalid credentials', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-03 08:10:13'),
(116, 1, 'auth.login_success', 'user', '1', 'Login successful', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-03 08:10:16'),
(117, 1, 'products.stock_in', 'product', '1', 'Stock in: 2 units', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-03 13:16:11'),
(118, 1, 'products.stock_in', 'product', '1', 'Stock in: 2 units', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-03 13:17:47'),
(119, 1, 'products.stock_in', 'product', '1', 'Stock in: 50 units', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-03 13:18:14'),
(120, 1, 'stock.transfer', 'product', '1', 'Transfer #58 from 1 to 2 qty 10', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-03 13:26:43'),
(121, 1, 'stock.transfer', 'product', '1', 'Transfer #59 from 2 to 1 qty 1', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-03 13:27:27'),
(122, 1, 'auth.login_success', 'user', '1', 'Login successful', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-04 11:19:28'),
(123, 1, 'auth.login_success', 'user', '1', 'Login successful', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-04 14:55:35'),
(124, 1, 'auth.login_success', 'user', '1', 'Login successful', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-06 09:23:29'),
(125, NULL, 'auth.login_failed', 'user', 'admin', 'Invalid credentials', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-06 14:12:51'),
(126, NULL, 'auth.login_failed', 'user', 'admin', 'Invalid credentials', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-06 14:13:03'),
(127, NULL, 'auth.login_failed', 'user', 'admin@almatechconsults.com', 'Invalid credentials', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-06 14:13:24'),
(128, 1, 'auth.login_success', 'user', '1', 'Login successful', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-06 14:13:27'),
(129, NULL, 'auth.login_failed', 'user', 'admin@almatechconsults.com', 'Invalid credentials', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-07 09:06:14'),
(130, 1, 'auth.login_success', 'user', '1', 'Login successful', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-07 09:06:17'),
(131, 1, 'installments.create', 'installment', '3', 'Created installment for contact ID 1', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-07 10:29:20'),
(132, 1, 'products.update', 'product', '1', 'Updated: 12132', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-07 15:46:48'),
(133, 1, 'products.update', 'product', '1', 'Updated: 12132', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-07 15:47:55'),
(134, 1, 'products.create', 'product', '2', 'Created: 31223123', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-07 15:50:21'),
(135, 1, 'products.update', 'product', '2', 'Updated: 31223123', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-07 15:56:35'),
(136, 1, 'products.update', 'product', '2', 'Updated: 31223123', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-07 16:07:14'),
(137, 1, 'auth.login_success', 'user', '1', 'Login successful', '192.168.100.92', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', '2026-02-07 16:58:25'),
(138, 1, 'products.update', 'product', '2', 'Updated: 31223123', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-07 17:04:29'),
(139, NULL, 'auth.login_failed', 'user', 'admin', 'Invalid credentials', '192.168.100.4', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-07 17:05:15'),
(140, NULL, 'auth.login_failed', 'user', 'admin', 'Invalid credentials', '192.168.100.4', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-07 17:05:22'),
(141, 1, 'auth.login_success', 'user', '1', 'Login successful', '192.168.100.4', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-07 17:05:30'),
(142, 1, 'products.update', 'product', '2', 'Updated: 31223123', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-07 17:23:50'),
(143, 1, 'products.update', 'product', '2', 'Updated: 31223123', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-07 17:24:00'),
(144, 1, 'products.update', 'product', '2', 'Updated: 31223123', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-07 17:24:14'),
(145, 1, 'products.update', 'product', '2', 'Updated: 31223123', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-07 17:28:41'),
(146, 1, 'auth.login_success', 'user', '1', 'Login successful', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-09 07:53:12'),
(147, 1, 'auth.logout', 'user', '1', 'User logged out', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-09 08:14:30'),
(148, 2, 'auth.login_success', 'user', '2', 'Login successful', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-09 08:14:34'),
(149, 2, 'auth.logout', 'user', '2', 'User logged out', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-09 08:22:54'),
(150, 1, 'auth.login_success', 'user', '1', 'Login successful', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-09 08:24:12'),
(151, 1, 'auth.logout', 'user', '1', 'User logged out', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-09 08:27:10'),
(152, 2, 'auth.login_success', 'user', '2', 'Login successful', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-09 08:27:17'),
(153, 2, 'auth.login_success', 'user', '2', 'Login successful', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-09 10:06:48'),
(154, 2, 'auth.logout', 'user', '2', 'User logged out', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-09 10:07:59'),
(155, 1, 'auth.login_success', 'user', '1', 'Login successful', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-09 10:08:05'),
(156, NULL, 'auth.login_failed', 'user', 'admin', 'Invalid credentials', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-09 10:41:42'),
(157, 1, 'auth.login_success', 'user', '1', 'Login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-09 10:41:47'),
(158, 1, 'auth.logout', 'user', '1', 'User logged out', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-09 10:49:11'),
(159, 2, 'auth.login_success', 'user', '2', 'Login successful', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-09 10:49:17'),
(160, 2, 'auth.logout', 'user', '2', 'User logged out', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-09 10:49:56'),
(161, 1, 'auth.login_success', 'user', '1', 'Login successful', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-09 10:50:03'),
(162, 1, 'auth.logout', 'user', '1', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-09 10:50:32'),
(163, 2, 'auth.login_success', 'user', '2', 'Login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-09 10:50:46'),
(164, 2, 'auth.logout', 'user', '2', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-09 11:09:46'),
(165, 2, 'auth.login_success', 'user', '2', 'Login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-09 11:09:47'),
(166, 2, 'auth.logout', 'user', '2', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-09 11:18:25'),
(167, 2, 'auth.login_success', 'user', '2', 'Login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-09 11:18:26'),
(168, 1, 'auth.logout', 'user', '1', 'User logged out', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-09 11:18:43'),
(169, 2, 'auth.login_success', 'user', '2', 'Login successful', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-09 11:18:46'),
(170, 2, 'auth.logout', 'user', '2', 'User logged out', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-09 11:24:31'),
(171, 1, 'auth.login_success', 'user', '1', 'Login successful', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-09 11:24:38'),
(172, 2, 'auth.login_success', 'user', '2', 'Login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-09 11:25:37'),
(173, 2, 'auth.logout', 'user', '2', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-09 13:04:03'),
(174, 2, 'auth.login_success', 'user', '2', 'Login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-09 13:04:05'),
(175, 2, 'auth.login_success', 'user', '2', 'Login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-09 13:28:17'),
(176, 1, 'categories.create', 'categories', '3', 'Created category: mouse', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-09 16:21:10'),
(177, 1, 'categories.update', 'categories', '3', 'Updated category: mousey', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-09 16:22:47'),
(178, 1, 'categories.delete', 'categories', '3', 'Deleted category ID: 3', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-09 16:22:52'),
(179, 1, 'categories.create', 'categories', '4', 'Created category: mouse', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-09 16:22:57'),
(180, 1, 'auth.login_success', 'user', '1', 'Login successful', '192.168.100.92', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', '2026-02-09 16:27:06'),
(181, 1, 'products.create', 'product', '3', 'Created: 45654erty', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-09 16:47:21'),
(182, 1, 'products.delete', 'product', '3', 'Deleted', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-09 16:47:28'),
(183, 1, 'categories.delete', 'categories', '4', 'Deleted category ID: 4', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-09 16:48:00'),
(184, 1, 'categories.create', 'categories', '5', 'Created category: laptop', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-09 16:48:06'),
(185, 1, 'products.create', 'product', '4', 'Created: g2', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-09 16:49:06'),
(186, 1, 'products.update', 'product', '4', 'Updated: g2', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-09 16:49:26'),
(187, 1, 'stock.stock_in', 'product', '4', 'Stock In #68 loc:2 qty:10', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-09 17:14:42'),
(188, 1, 'stock.stock_in', 'product', '4', 'Stock In #69 loc:3 qty:5', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-09 17:17:39'),
(189, 1, 'auth.login_success', 'user', '1', 'Login successful', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-10 07:55:15'),
(190, 1, 'auth.login_success', 'user', '1', 'Login successful', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-10 17:46:27'),
(191, 1, 'auth.logout', 'user', '1', 'User logged out', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-10 18:18:14'),
(192, NULL, 'auth.login_failed', 'user', 'cashier', 'Invalid credentials', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-10 18:18:20'),
(193, NULL, 'auth.login_failed', 'user', 'cashier', 'Invalid credentials', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-10 18:18:46'),
(194, NULL, 'auth.login_failed', 'user', 'cashier', 'Invalid credentials', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-10 18:19:08'),
(195, NULL, 'auth.login_failed', 'user', 'cashier', 'Invalid credentials', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-10 18:19:51'),
(196, NULL, 'auth.login_failed', 'user', 'cashier', 'Invalid credentials', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-10 18:20:36'),
(197, 2, 'auth.login_success', 'user', '2', 'Login successful', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-10 18:20:39'),
(198, 2, 'auth.logout', 'user', '2', 'User logged out', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-10 18:20:45'),
(199, NULL, 'auth.login_failed', 'user', 'manager', 'Invalid credentials', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-10 18:21:13'),
(200, NULL, 'auth.login_failed', 'user', 'manager', 'Invalid credentials', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-10 18:22:06'),
(201, 3, 'auth.login_success', 'user', '3', 'Login successful', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-10 18:22:45'),
(202, 3, 'auth.logout', 'user', '3', 'User logged out', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-10 18:22:49'),
(203, 1, 'auth.login_success', 'user', '1', 'Login successful', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-10 18:25:02'),
(204, 1, 'auth.logout', 'user', '1', 'User logged out', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-10 18:32:40'),
(205, 2, 'auth.login_success', 'user', '2', 'Login successful', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-10 18:32:45'),
(206, 2, 'auth.logout', 'user', '2', 'User logged out', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-10 18:34:07'),
(207, 3, 'auth.login_success', 'user', '3', 'Login successful', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-10 18:34:10'),
(208, 3, 'auth.logout', 'user', '3', 'User logged out', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-10 18:35:35'),
(209, 1, 'auth.login_success', 'user', '1', 'Login successful', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-11 11:35:10'),
(210, 1, 'auth.logout', 'user', '1', 'User logged out', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-11 13:00:07'),
(211, 1, 'auth.login_success', 'user', '1', 'Login successful', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-11 13:00:10'),
(212, 1, 'auth.logout', 'user', '1', 'User logged out', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-11 14:26:32'),
(213, 3, 'auth.login_success', 'user', '3', 'Login successful', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-11 14:26:36'),
(214, 3, 'auth.logout', 'user', '3', 'User logged out', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-11 14:26:42'),
(215, 1, 'auth.login_success', 'user', '1', 'Login successful', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-11 14:26:46'),
(216, 1, 'auth.logout', 'user', '1', 'User logged out', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-11 14:28:18'),
(217, 3, 'auth.login_success', 'user', '3', 'Login successful', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-11 14:28:21'),
(218, 3, 'auth.logout', 'user', '3', 'User logged out', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-11 15:55:54'),
(219, 1, 'auth.login_success', 'user', '1', 'Login successful', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-11 15:55:57'),
(220, 1, 'auth.logout', 'user', '1', 'User logged out', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-11 16:00:11'),
(221, 2, 'auth.login_success', 'user', '2', 'Login successful', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-11 16:00:16'),
(222, 2, 'auth.logout', 'user', '2', 'User logged out', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-11 17:37:17'),
(223, 1, 'auth.login_success', 'user', '1', 'Login successful', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-11 17:37:20'),
(224, NULL, 'auth.login_failed', 'user', 'admin', 'Invalid credentials', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-16 10:12:29'),
(225, NULL, 'auth.login_failed', 'user', 'admin', 'Invalid credentials', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-16 10:12:40'),
(226, NULL, 'auth.login_failed', 'user', 'admin', 'Invalid credentials', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-16 10:12:50'),
(227, NULL, 'auth.login_failed', 'user', 'admin', 'Invalid credentials', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-16 10:13:00'),
(228, 1, 'auth.login_success', 'user', '1', 'Login successful', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-16 10:14:03'),
(229, 1, 'categories.create', 'categories', '6', 'Created category: smartphones', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-16 10:16:10'),
(230, 1, 'products.create', 'product', '5', 'Created: 23434', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-16 10:19:30'),
(231, 1, 'stock.transfer', 'product', '5', 'Transfer #76 from 1 to 2 qty 8', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-16 10:22:44'),
(232, 1, 'auth.login_success', 'user', '1', 'Login successful', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-17 14:37:03'),
(233, 1, 'categories.create', 'categories', '7', 'Created category: Accessories', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-17 14:38:27'),
(234, 1, 'products.create', 'product', '6', 'Created: 23132', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-17 14:40:39'),
(235, 1, 'stock.transfer', 'product', '6', 'Transfer #78 from 1 to 2 qty 150', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-17 14:42:12'),
(236, 1, 'auth.login_success', 'user', '1', 'Login successful', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', '2026-02-17 14:46:12'),
(237, 1, 'auth.login_success', 'user', '1', 'Login successful', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:149.0) Gecko/20100101 Firefox/149.0', '2026-03-14 10:33:29'),
(238, 1, 'auth.login_success', 'user', '1', 'Login successful', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-16 14:05:50');

-- --------------------------------------------------------

--
-- Table structure for table `b2b_sales_items`
--

CREATE TABLE `b2b_sales_items` (
  `id` bigint(20) NOT NULL,
  `sale_id` bigint(20) NOT NULL,
  `name` varchar(200) NOT NULL,
  `sku` varchar(120) DEFAULT NULL,
  `qty` decimal(12,2) NOT NULL DEFAULT 1.00,
  `unit_type` enum('boxes','dozens','pairs','pieces','units') NOT NULL DEFAULT 'pieces',
  `unit_name` varchar(50) DEFAULT NULL,
  `cost_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `sell_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(10) NOT NULL DEFAULT 'UGX',
  `exchange_rate` decimal(12,6) NOT NULL DEFAULT 1.000000,
  `supplier_id` int(11) DEFAULT NULL,
  `supplier_name` varchar(200) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `b2b_sales_items`
--

INSERT INTO `b2b_sales_items` (`id`, `sale_id`, `name`, `sku`, `qty`, `unit_type`, `unit_name`, `cost_price`, `sell_price`, `currency`, `exchange_rate`, `supplier_id`, `supplier_name`, `note`, `created_at`) VALUES
(2, 53, 'red cup', NULL, 1.00, 'pieces', NULL, 2000.00, 10000.00, 'UGX', 1.000000, NULL, NULL, NULL, '2026-02-06 18:34:45'),
(3, 54, 'Car charger', NULL, 1.00, 'pieces', NULL, 4000.00, 10000.00, 'UGX', 1.000000, NULL, NULL, NULL, '2026-02-07 13:16:14'),
(4, 62, 'Charger', NULL, 1.00, 'pieces', NULL, 15000.00, 30000.00, 'UGX', 1.000000, NULL, NULL, NULL, '2026-02-10 11:17:57'),
(5, 63, 'Charger', NULL, 1.00, 'pieces', NULL, 10000.00, 15000.00, 'UGX', 1.000000, NULL, NULL, 'not paid', '2026-02-16 13:25:51');

-- --------------------------------------------------------

--
-- Table structure for table `bank_accounts`
--

CREATE TABLE `bank_accounts` (
  `id` bigint(20) NOT NULL,
  `account_name` varchar(100) NOT NULL,
  `account_number` varchar(50) NOT NULL,
  `bank_name` varchar(100) NOT NULL,
  `branch` varchar(100) DEFAULT NULL,
  `account_type` enum('current','savings','fixed_deposit','business') NOT NULL DEFAULT 'current',
  `currency` varchar(3) NOT NULL DEFAULT 'USD',
  `opening_balance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `current_balance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bank_accounts`
--

INSERT INTO `bank_accounts` (`id`, `account_name`, `account_number`, `bank_name`, `branch`, `account_type`, `currency`, `opening_balance`, `current_balance`, `is_active`, `notes`, `created_at`, `updated_at`, `created_by`) VALUES
(1, 'Main Business Account', '1234567890', 'First National Bank', 'Main Branch', 'current', 'USD', 10000.00, 10000.00, 1, NULL, '2026-02-03 14:00:00', '2026-02-03 14:00:00', 1),
(2, 'Petty Cash Account', '9876543210', 'First National Bank', 'Main Branch', 'current', 'USD', 1000.00, 1000.00, 1, NULL, '2026-02-03 14:00:00', '2026-02-03 14:00:00', 1),
(3, 'Savings Account', '5555666677', 'First National Bank', 'Main Branch', 'savings', 'USD', 5000.00, 5000.00, 1, NULL, '2026-02-03 14:00:00', '2026-02-03 14:00:00', 1);

-- --------------------------------------------------------

--
-- Table structure for table `bank_transactions`
--

CREATE TABLE `bank_transactions` (
  `id` bigint(20) NOT NULL,
  `account_id` bigint(20) NOT NULL,
  `transaction_date` date NOT NULL,
  `type` enum('debit','credit') NOT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `reference` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `reconciled` tinyint(1) NOT NULL DEFAULT 0,
  `reconciliation_date` datetime DEFAULT NULL,
  `attachment_path` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bank_transactions`
--

INSERT INTO `bank_transactions` (`id`, `account_id`, `transaction_date`, `type`, `amount`, `reference`, `description`, `category`, `reconciled`, `reconciliation_date`, `attachment_path`, `created_at`, `updated_at`, `created_by`) VALUES
(1, 1, '2026-01-27', 'credit', 5000.00, 'DEP001', 'Customer Deposit - Invoice #1001', 'deposit', 0, NULL, NULL, '2026-02-03 14:00:00', '2026-02-03 14:00:00', 1),
(2, 1, '2026-01-29', 'debit', 1500.00, 'PAY001', 'Supplier Payment - Office Supplies', 'expense', 0, NULL, NULL, '2026-02-03 14:00:00', '2026-02-03 14:00:00', 1),
(3, 2, '2026-01-31', 'debit', 500.00, 'PETTY001', 'Petty Cash Withdrawal', 'transfer', 0, NULL, NULL, '2026-02-03 14:00:00', '2026-02-03 14:00:00', 1),
(4, 1, '2026-02-02', 'credit', 2500.00, 'DEP002', 'Customer Deposit - Invoice #1002', 'deposit', 0, NULL, NULL, '2026-02-03 14:00:00', '2026-02-03 14:00:00', 1);

-- --------------------------------------------------------

--
-- Table structure for table `brands`
--

CREATE TABLE `brands` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `slug` varchar(160) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `brands`
--

INSERT INTO `brands` (`id`, `name`, `slug`, `description`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Always', 'always', '', 'active', '2026-02-07 15:12:12', '2026-02-09 15:35:56'),
(4, 'hp', 'hp', 'hp', 'active', '2026-02-09 16:47:52', NULL),
(5, 'Tecno', 'tecno', '', 'active', '2026-02-16 10:15:45', NULL),
(6, 'Acer', 'acer', '', 'active', '2026-02-17 14:38:13', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `contact_categories`
--

CREATE TABLE `contact_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `color` varchar(7) DEFAULT '#007bff',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contact_category_map`
--

CREATE TABLE `contact_category_map` (
  `id` int(11) NOT NULL,
  `contact_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contact_tags`
--

CREATE TABLE `contact_tags` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `color` varchar(7) DEFAULT '#28a745',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contact_tag_map`
--

CREATE TABLE `contact_tag_map` (
  `id` int(11) NOT NULL,
  `contact_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `name`, `phone`, `email`, `address`, `category_id`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Ampumuza Bronia', '+256700868939', 'ambronia@gmail.com', 'Mbarara', NULL, 1, '2026-02-07 10:27:40', '2026-02-07 10:27:40');

-- --------------------------------------------------------

--
-- Table structure for table `doc_sequences`
--

CREATE TABLE `doc_sequences` (
  `id` int(11) NOT NULL,
  `doc_type` enum('receipt','invoice','delivery_note') NOT NULL,
  `year` smallint(6) NOT NULL,
  `prefix` varchar(10) NOT NULL,
  `current_no` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_log`
--

CREATE TABLE `email_log` (
  `id` int(11) NOT NULL,
  `to_email` varchar(190) NOT NULL,
  `to_name` varchar(190) DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `body` mediumtext DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'queued',
  `provider` varchar(50) DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `meta_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta_json`)),
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `sent_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `finance`
--

CREATE TABLE `finance` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `type` enum('IN','OUT') NOT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `method` varchar(100) DEFAULT NULL,
  `reference` varchar(150) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `installments`
--

CREATE TABLE `installments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `contact_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `amount_due` decimal(15,2) NOT NULL DEFAULT 0.00,
  `amount_paid` decimal(15,2) NOT NULL DEFAULT 0.00,
  `due_date` date DEFAULT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `installments`
--

INSERT INTO `installments` (`id`, `contact_id`, `user_id`, `reference`, `amount_due`, `amount_paid`, `due_date`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(3, 1, 1, '0001', 500000.00, 0.00, '2026-02-07', 'active', '', '2026-02-07 10:29:20', '2026-02-07 12:01:29');

-- --------------------------------------------------------

--
-- Table structure for table `installment_payments`
--

CREATE TABLE `installment_payments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `installment_id` bigint(20) UNSIGNED NOT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `method` varchar(40) NOT NULL,
  `reference` varchar(120) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `payment_date` datetime NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `locations`
--

CREATE TABLE `locations` (
  `id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `low_alert_qty` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `low_alert_type` varchar(20) NOT NULL DEFAULT 'pieces'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `locations`
--

INSERT INTO `locations` (`id`, `name`, `is_active`, `created_at`, `low_alert_qty`, `low_alert_type`) VALUES
(1, 'Main Store', 1, '2026-01-23 11:18:09', 0.0000, 'pieces'),
(2, 'Counter', 1, '2026-01-23 11:18:09', 0.0000, 'pieces'),
(3, 'Store Room', 1, '2026-01-23 11:18:09', 0.0000, 'pieces');

-- --------------------------------------------------------

--
-- Table structure for table `message_attachments`
--

CREATE TABLE `message_attachments` (
  `id` int(11) NOT NULL,
  `message_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `message_logs`
--

CREATE TABLE `message_logs` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) DEFAULT NULL,
  `recipient_type` enum('user','role','all') NOT NULL DEFAULT 'user',
  `recipient_id` int(11) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `status` enum('queued','sent','failed','delivered') NOT NULL DEFAULT 'queued',
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `scheduled_at` timestamp NULL DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `message_logs`
--

INSERT INTO `message_logs` (`id`, `sender_id`, `recipient_type`, `recipient_id`, `subject`, `message`, `status`, `is_read`, `read_at`, `scheduled_at`, `sent_at`, `created_at`, `updated_at`) VALUES
(1, 2, 'all', 0, 'New Feature Available', 'Hello cashier1,\r\n\r\nWe are excited to announce a new feature: {feature_name}\r\n\r\n{feature_description}\r\n\r\nYou can access this feature from your dashboard.\r\n\r\nEnjoy!\r\nProduct Team', 'sent', 1, '2026-02-11 20:23:51', NULL, NULL, '2026-02-11 17:09:00', '2026-02-11 17:23:51'),
(2, 2, 'all', 0, 'New Feature Available', 'Hello cashier1,\r\n\r\nWe are excited to announce a new feature: {feature_name}\r\n\r\n{feature_description}\r\n\r\nYou can access this feature from your dashboard.\r\n\r\nEnjoy!\r\nProduct Team', 'sent', 1, '2026-02-11 20:25:18', NULL, NULL, '2026-02-11 17:25:15', '2026-02-11 17:25:18');

-- --------------------------------------------------------

--
-- Table structure for table `message_reads`
--

CREATE TABLE `message_reads` (
  `id` int(11) NOT NULL,
  `message_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `message_templates`
--

CREATE TABLE `message_templates` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `category` varchar(100) DEFAULT 'general',
  `variables` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`variables`)),
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `message_templates`
--

INSERT INTO `message_templates` (`id`, `name`, `subject`, `message`, `category`, `variables`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Welcome Message', 'Welcome to our system', 'Hello {username},\n\nWelcome to our business management system. Your account has been successfully created.\n\nBest regards,\nThe Team', 'welcome', NULL, 1, 1, '2026-02-11 17:08:40', '2026-02-11 17:08:40');

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` int(11) NOT NULL,
  `perm_key` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_super_admin_only` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`id`, `perm_key`, `description`, `created_at`, `is_super_admin_only`) VALUES
(1, 'dashboard.view', 'View dashboard', '2026-01-23 07:10:11', 0),
(2, 'pos.use', 'Use POS', '2026-01-23 07:10:11', 0),
(3, 'sales.view', 'View sales reports', '2026-01-23 07:10:11', 0),
(4, 'sales.returns', 'Process returns', '2026-01-23 07:10:11', 0),
(5, 'products.view', 'View products and inventory', '2026-01-23 07:10:11', 0),
(6, 'products.create', 'Create products', '2026-01-23 07:10:11', 0),
(7, 'products.update', 'Update products / stock / pricing', '2026-01-23 07:10:11', 0),
(8, 'products.delete', 'Delete products', '2026-01-23 07:10:11', 0),
(9, 'contacts.view', 'View contacts', '2026-01-23 07:10:11', 0),
(10, 'contacts.create', 'Create contacts', '2026-01-23 07:10:11', 0),
(11, 'contacts.update', 'Update contacts', '2026-01-23 07:10:11', 0),
(12, 'contacts.delete', 'Delete contacts', '2026-01-23 07:10:11', 0),
(13, 'finance.view', 'View finance module', '2026-01-23 07:10:11', 0),
(14, 'finance.create', 'Create finance entries', '2026-01-23 07:10:11', 0),
(15, 'finance.update', 'Update finance records', '2026-01-23 07:10:11', 0),
(16, 'finance.delete', 'Delete finance records', '2026-01-23 07:10:11', 0),
(17, 'reports.view', 'Access reports module', '2026-01-23 07:10:11', 0),
(18, 'admin.access', 'Access admin area', '2026-01-23 07:10:11', 0),
(19, 'admin.users', 'Manage users', '2026-01-23 07:10:11', 0),
(20, 'admin.rbac', 'Manage roles/permissions', '2026-01-23 07:10:11', 0),
(21, 'admin.settings', 'Manage system settings', '2026-01-23 07:10:11', 0),
(22, 'admin.updates', 'Apply updates', '2026-01-23 07:10:11', 0),
(23, 'audit.view', 'View audit logs', '2026-01-23 07:10:11', 0),
(24, 'reports.b2b.view', 'View B2B items report', '2026-02-06 13:15:16', 0),
(25, 'shopping_list.create', 'Create shopping list entries', '2026-02-06 13:15:16', 0),
(26, 'pos.create', 'Create a POS sale', '2026-02-09 10:07:40', 0),
(27, 'pos.view', 'View POS sales / history', '2026-02-09 10:07:40', 0),
(28, 'pos.void', 'Void a POS sale', '2026-02-09 10:07:40', 0),
(29, 'sales.create', 'Create a sale via API', '2026-02-09 10:07:40', 0),
(30, 'documents.view', 'View documents (receipts, invoices, delivery notes, history)', '2026-02-09 10:07:40', 0),
(31, 'installments.view', 'View installments', '2026-02-09 10:07:40', 0),
(32, 'installments.create', 'Create installment / receive payment', '2026-02-09 10:07:40', 0),
(33, 'installments.edit', 'Edit installment', '2026-02-09 10:07:40', 0),
(34, 'installments.delete', 'Delete installment', '2026-02-09 10:07:40', 0),
(35, 'installments.update', 'Update installments / run actions', '2026-02-09 10:07:40', 0),
(36, 'brands.view', 'View brands', '2026-02-09 10:07:40', 0),
(37, 'brands.create', 'Create brands', '2026-02-09 10:07:40', 0),
(38, 'brands.edit', 'Edit brands', '2026-02-09 10:07:40', 0),
(39, 'brands.delete', 'Delete brands', '2026-02-09 10:07:40', 0),
(40, 'stores.manage', 'Manage stores', '2026-02-09 10:07:40', 0),
(41, 'stores.update', 'Update stores', '2026-02-09 10:07:40', 0),
(42, 'stores.delete', 'Delete stores', '2026-02-09 10:07:40', 0),
(43, 'procurement.view', 'View procurement module', '2026-02-09 10:07:40', 0),
(44, 'messaging.view', 'Messaging module access', '2026-02-09 10:07:40', 0),
(45, 'audit.manage', 'Manage audit logs (delete ranges, etc.)', '2026-02-09 10:07:40', 0),
(46, 'reminders.view', 'View reminders', '2026-02-09 10:07:40', 0),
(47, 'approvals.view', 'View approvals', '2026-02-09 10:07:40', 0),
(48, 'users.view', 'Manage users', '2026-02-09 10:07:40', 1),
(49, 'roles.view', 'Manage roles', '2026-02-09 10:07:40', 1),
(50, 'permissions.manage', 'Manage permissions', '2026-02-09 10:07:40', 1),
(51, 'settings.manage', 'Manage system settings', '2026-02-09 10:07:40', 1),
(52, 'payments.manage', 'Manage payment settings', '2026-02-09 10:07:40', 1),
(53, 'updates.manage', 'Run updates', '2026-02-09 10:07:40', 1),
(54, 'updates.view', 'View update history', '2026-02-09 10:07:40', 1),
(66, 'admin.exclusive', 'Access admin-exclusive features', '2026-02-09 11:45:27', 0),
(67, 'reports.sales.view', 'View Sales report', '2026-02-09 11:49:06', 0),
(68, 'reports.profit.view', 'View Profit report', '2026-02-09 11:49:06', 0),
(69, 'reports.inventory.view', 'View Inventory report', '2026-02-09 11:49:06', 0),
(70, 'reports.installments.view', 'View Installments report', '2026-02-09 11:49:06', 0),
(71, 'reports.expenses.view', 'View Expenses report', '2026-02-09 11:49:06', 0),
(72, 'reports.capital.view', 'View Capital report', '2026-02-09 11:49:06', 0),
(73, 'reports.audit.view', 'View Audit report', '2026-02-09 11:49:06', 0),
(74, 'stores.view', 'Access Stores module', '2026-02-09 12:58:19', 0),
(75, 'admin.settings.view', 'View Settings', '2026-02-09 12:58:19', 1),
(76, 'admin.themes.view', 'View Themes & UI', '2026-02-09 12:58:53', 1),
(77, 'admin.payments.view', 'View Payment Settings', '2026-02-09 12:58:53', 1),
(78, 'admin.reminders.view', 'View Reminders', '2026-02-09 12:58:53', 1),
(79, 'admin.users.view', 'Manage Users', '2026-02-09 12:58:53', 1),
(80, 'admin.roles.view', 'Manage Roles', '2026-02-09 12:58:53', 1),
(81, 'admin.permissions.manage', 'Manage Permissions', '2026-02-09 12:58:53', 1),
(82, 'admin.approvals.view', 'View Approvals', '2026-02-09 12:58:53', 1),
(83, 'admin.audit_trail.view', 'View Admin Audit Trail', '2026-02-09 12:58:53', 1),
(84, 'admin.updates.view', 'View Updates', '2026-02-09 12:58:53', 1),
(85, 'admin.update_history.view', 'View Update History', '2026-02-09 12:58:53', 1);

-- --------------------------------------------------------

--
-- Table structure for table `phone_scan_sessions`
--

CREATE TABLE `phone_scan_sessions` (
  `id` int(11) NOT NULL,
  `session_id` varchar(100) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `status` enum('created','connected','scanning','found','uploaded','error') DEFAULT 'created',
  `image_url` text DEFAULT NULL,
  `message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `price_adjustments`
--

CREATE TABLE `price_adjustments` (
  `id` bigint(20) NOT NULL,
  `product_id` int(11) NOT NULL,
  `old_cost` decimal(12,2) NOT NULL,
  `new_cost` decimal(12,2) NOT NULL,
  `old_wholesale` decimal(12,2) NOT NULL,
  `new_wholesale` decimal(12,2) NOT NULL,
  `old_retail` decimal(12,2) NOT NULL,
  `new_retail` decimal(12,2) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `changed_by` int(11) DEFAULT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `procurement_shopping_list`
--

CREATE TABLE `procurement_shopping_list` (
  `id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit` varchar(50) NOT NULL,
  `estimated_cost` decimal(12,2) DEFAULT 0.00,
  `actual_cost` decimal(12,2) DEFAULT NULL,
  `status` enum('pending','ordered','received','cancelled') DEFAULT 'pending',
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `supplier_id` int(11) DEFAULT NULL,
  `ordered_date` datetime DEFAULT NULL,
  `received_date` datetime DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `procurement_shopping_list_b2b_map`
--

CREATE TABLE `procurement_shopping_list_b2b_map` (
  `b2b_id` bigint(20) NOT NULL,
  `procurement_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `procurement_wanted_items`
--

CREATE TABLE `procurement_wanted_items` (
  `id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `estimated_cost` decimal(12,2) DEFAULT 0.00,
  `status` enum('pending','approved','rejected','ordered') DEFAULT 'pending',
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `reason` text DEFAULT NULL,
  `requested_by` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `brand_id` int(11) DEFAULT NULL,
  `name` varchar(200) NOT NULL,
  `sku` varchar(80) NOT NULL,
  `description` text DEFAULT NULL,
  `source` varchar(255) DEFAULT NULL,
  `unit_type` enum('boxes','dozens','pairs','pieces','units') NOT NULL DEFAULT 'pieces',
  `unit_name` varchar(40) DEFAULT NULL,
  `pieces_per_box` int(11) DEFAULT NULL,
  `unit` varchar(40) DEFAULT NULL,
  `track_expiry` tinyint(1) NOT NULL DEFAULT 0,
  `expiry_date` date DEFAULT NULL,
  `cost_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `wholesale_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `retail_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `qty_base` decimal(12,2) NOT NULL DEFAULT 0.00,
  `low_level_base` decimal(12,2) NOT NULL DEFAULT 0.00,
  `qty_on_hand` decimal(12,2) NOT NULL DEFAULT 0.00,
  `low_level` decimal(12,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `default_location_id` int(11) DEFAULT NULL,
  `images` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`images`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `category_id`, `brand_id`, `name`, `sku`, `description`, `source`, `unit_type`, `unit_name`, `pieces_per_box`, `unit`, `track_expiry`, `expiry_date`, `cost_price`, `wholesale_price`, `retail_price`, `qty_base`, `low_level_base`, `qty_on_hand`, `low_level`, `is_active`, `created_at`, `updated_at`, `default_location_id`, `images`) VALUES
(1, NULL, 1, 'Round Cup', '12132', 'nice', 'Baaka', 'boxes', '', 10, NULL, 0, NULL, 1500.00, 2500.00, 3000.00, 10.00, 5.00, 0.00, 0.00, 1, '2026-01-23 10:55:49', '2026-02-07 15:47:55', 1, '[\"uploads\\/products\\/GEN_RC_001.jpg\",\"uploads\\/products\\/GEN_RC_002.jpg\",\"uploads\\/products\\/GEN_RC_003.jpg\"]'),
(2, NULL, 1, 'Red Cup', '31223123', 'red', 'akaka', 'boxes', '', 16, NULL, 0, NULL, 4000.00, 5000.00, 8000.00, 32.00, 6.00, 0.00, 0.00, 1, '2026-02-07 15:50:21', '2026-02-07 17:28:44', 1, '[\"uploads\\/products\\/product_2_1770480328_6867.png\",\"uploads\\/products\\/product_2_capture_1770485063_9901.jpg\",\"uploads\\/products\\/product_2_capture_1770485083_9025.jpg\",\"uploads\\/products\\/product_2_capture_1770485236_4206.jpg\",\"uploads\\/products\\/product_2_capture_1770485324_2024.jpg\"]'),
(4, NULL, 4, 'Hp 840 G2', 'g2', 'g3', 'Bash', 'pieces', '', 0, NULL, 0, NULL, 500000.00, 650000.00, 800000.00, 5.00, 2.00, 0.00, 0.00, 1, '2026-02-09 16:49:06', '2026-02-09 16:49:26', 1, '[\"uploads\\/products\\/product_temp_1770655744_8120.png\",\"uploads\\/products\\/product_4_1770655765_8291.png\"]'),
(5, NULL, 5, 'Tecno Spark 9C', '23434', 'Smart', 'Tecno LTD', 'boxes', '', 24, NULL, 0, NULL, 150000.00, 180000.00, 220000.00, 48.00, 8.00, 0.00, 0.00, 1, '2026-02-16 10:19:30', NULL, 1, '[\"uploads\\/products\\/product_temp_1771237167_8087.jpg\"]'),
(6, NULL, 6, 'Acer Mouse', '23132', 'Black', 'PC world', 'boxes', '', 100, NULL, 0, NULL, 5000.00, 6000.00, 10000.00, 1000.00, 50.00, 0.00, 0.00, 1, '2026-02-17 14:40:39', NULL, 1, '[\"uploads\\/products\\/product_temp_1771339237_3561.png\"]');

-- --------------------------------------------------------

--
-- Table structure for table `product_categories`
--

CREATE TABLE `product_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_categories`
--

INSERT INTO `product_categories` (`id`, `name`, `description`, `is_active`, `created_at`) VALUES
(1, 'Ceramic Cup', NULL, 1, '2026-01-23 10:17:37'),
(2, 'Plate', 'Plates', 1, '2026-02-03 13:01:15'),
(5, 'laptop', NULL, 1, '2026-02-09 16:48:06'),
(6, 'smartphones', NULL, 1, '2026-02-16 10:16:10'),
(7, 'Accessories', NULL, 1, '2026-02-17 14:38:27');

-- --------------------------------------------------------

--
-- Table structure for table `reminders`
--

CREATE TABLE `reminders` (
  `id` bigint(20) NOT NULL,
  `title` varchar(150) NOT NULL,
  `remind_at` datetime NOT NULL,
  `channel` varchar(20) NOT NULL DEFAULT 'email',
  `target` varchar(150) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `return_items`
--

CREATE TABLE `return_items` (
  `id` bigint(20) NOT NULL,
  `return_id` bigint(20) NOT NULL,
  `product_id` int(11) NOT NULL,
  `location_id` int(11) NOT NULL,
  `quantity` decimal(12,3) NOT NULL DEFAULT 0.000,
  `unit_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `return_items`
--

INSERT INTO `return_items` (`id`, `return_id`, `product_id`, `location_id`, `quantity`, `unit_price`, `total`, `status`, `created_at`) VALUES
(3, 3, 1, 0, 1.000, 3000.00, 3000.00, 'pending', '2026-02-02 19:35:25'),
(4, 4, 1, 1, 1.000, 3000.00, 3000.00, 'pending', '2026-02-02 20:10:02');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `description`, `created_at`) VALUES
(1, 'super_admin', 'Full access', '2026-01-23 07:10:11'),
(2, 'cashier', 'POS and limited operations', '2026-01-23 07:10:11'),
(3, 'accountant', 'Finance and reports', '2026-01-23 07:10:11');

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `role_permissions`
--

INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(1, 1),
(1, 2),
(1, 3),
(1, 4),
(1, 5),
(1, 6),
(1, 7),
(1, 8),
(1, 9),
(1, 10),
(1, 11),
(1, 12),
(1, 13),
(1, 14),
(1, 15),
(1, 16),
(1, 17),
(1, 18),
(1, 19),
(1, 20),
(1, 21),
(1, 22),
(1, 23),
(1, 76),
(1, 77),
(1, 78),
(1, 79),
(1, 80),
(1, 81),
(1, 82),
(1, 83),
(1, 84),
(1, 85),
(2, 1),
(2, 2),
(2, 3),
(2, 4),
(2, 5),
(2, 6),
(2, 7),
(2, 9),
(2, 10),
(2, 17),
(2, 24),
(2, 26),
(2, 27),
(2, 29),
(2, 30),
(2, 31),
(2, 32),
(2, 33),
(2, 35),
(2, 36),
(2, 37),
(2, 38),
(2, 39),
(2, 44),
(2, 46),
(2, 67),
(2, 70),
(3, 1),
(3, 3),
(3, 9),
(3, 13),
(3, 14),
(3, 15),
(3, 17),
(3, 23),
(3, 24),
(3, 30),
(3, 43),
(3, 44),
(3, 45),
(3, 67),
(3, 68),
(3, 69),
(3, 70),
(3, 71),
(3, 72),
(3, 73);

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `id` bigint(20) NOT NULL,
  `doc_type` enum('receipt','invoice','delivery_note') NOT NULL,
  `doc_no` varchar(30) NOT NULL,
  `selling_location_id` int(11) NOT NULL,
  `customer_id` bigint(20) DEFAULT NULL,
  `pricing_mode` enum('retail','wholesale') NOT NULL DEFAULT 'retail',
  `status` enum('draft','confirmed','voided') NOT NULL DEFAULT 'confirmed',
  `payment_status` enum('unpaid','partial','paid') NOT NULL DEFAULT 'unpaid',
  `currency` varchar(10) NOT NULL DEFAULT 'UGX',
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discount_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tax_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `grand_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `amount_paid` decimal(12,2) NOT NULL DEFAULT 0.00,
  `balance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `notes` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `has_b2b` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sales`
--

INSERT INTO `sales` (`id`, `doc_type`, `doc_no`, `selling_location_id`, `customer_id`, `pricing_mode`, `status`, `payment_status`, `currency`, `subtotal`, `discount_total`, `tax_total`, `grand_total`, `amount_paid`, `balance`, `notes`, `created_by`, `created_at`, `has_b2b`) VALUES
(34, 'receipt', 'RC-20260202-121847-16', 1, NULL, 'retail', 'confirmed', 'paid', 'UGX', 3000.00, 0.00, 0.00, 3000.00, 5000.00, 0.00, '', 1, '2026-02-02 14:18:47', 0),
(35, 'receipt', 'RC-20260202-122803-88', 1, NULL, 'retail', 'confirmed', 'unpaid', 'UGX', 3000.00, 0.00, 0.00, 3000.00, 0.00, 3000.00, '', 1, '2026-02-02 14:28:03', 0),
(36, 'receipt', 'RC-20260202-123610-65', 1, NULL, 'retail', 'confirmed', 'unpaid', 'UGX', 3000.00, 0.00, 0.00, 3000.00, 0.00, 3000.00, '', 1, '2026-02-02 14:36:10', 0),
(37, 'receipt', 'RC-20260202-124218-85', 1, NULL, 'retail', 'confirmed', 'paid', 'UGX', 3000.00, 0.00, 0.00, 3000.00, 4000.00, 0.00, '', 1, '2026-02-02 14:42:18', 0),
(38, 'receipt', 'RC-20260202-124401-78', 1, NULL, 'retail', 'confirmed', 'unpaid', 'UGX', 6000.00, 0.00, 0.00, 6000.00, 0.00, 6000.00, '', 1, '2026-02-02 14:44:01', 0),
(39, 'receipt', 'RC-20260202-125049-71', 1, NULL, 'retail', 'confirmed', 'paid', 'UGX', 3000.00, 0.00, 0.00, 3000.00, 8000.00, 0.00, '', 1, '2026-02-02 14:50:49', 0),
(40, 'receipt', 'RC-20260202-130329-86', 1, NULL, 'retail', 'confirmed', 'unpaid', 'UGX', 3000.00, 0.00, 0.00, 3000.00, 0.00, 3000.00, '', 1, '2026-02-02 15:03:29', 0),
(41, 'receipt', 'RC-20260202-130757-62', 1, NULL, 'retail', 'confirmed', 'unpaid', 'UGX', 3000.00, 0.00, 0.00, 3000.00, 0.00, 3000.00, '', 1, '2026-02-02 15:07:57', 0),
(42, 'receipt', 'RC-20260202-131427-43', 1, NULL, 'retail', 'confirmed', 'unpaid', 'UGX', 3000.00, 0.00, 0.00, 3000.00, 0.00, 3000.00, '', 1, '2026-02-02 15:14:27', 0),
(43, 'receipt', 'RC-20260202-131605-62', 1, NULL, 'retail', 'confirmed', 'unpaid', 'UGX', 3000.00, 0.00, 0.00, 3000.00, 0.00, 3000.00, '', 1, '2026-02-02 15:16:05', 0),
(44, 'receipt', 'RC-20260202-131725-40', 1, NULL, 'retail', 'confirmed', 'unpaid', 'UGX', 3000.00, 0.00, 0.00, 3000.00, 0.00, 3000.00, '', 1, '2026-02-02 15:17:25', 0),
(45, 'receipt', 'RC-20260203-142021-17', 2, NULL, 'retail', 'confirmed', 'unpaid', 'UGX', 15000.00, 0.00, 0.00, 15000.00, 0.00, 15000.00, '', 1, '2026-02-03 16:20:21', 0),
(46, 'receipt', 'RC-20260203-142146-40', 2, NULL, 'retail', 'confirmed', 'paid', 'UGX', 6000.00, 0.00, 0.00, 6000.00, 10000.00, 0.00, '', 1, '2026-02-03 16:21:46', 0),
(47, 'receipt', 'RC-20260203-142429-99', 2, NULL, 'retail', 'confirmed', 'paid', 'UGX', 21000.00, 0.00, 0.00, 21000.00, 25000.00, 0.00, '', 1, '2026-02-03 16:24:29', 0),
(48, 'receipt', 'RC-20260203-142516-10', 1, NULL, 'retail', 'confirmed', 'paid', 'UGX', 24000.00, 0.00, 0.00, 24000.00, 30000.00, 0.00, '', 1, '2026-02-03 16:25:16', 0),
(49, 'receipt', 'RC-20260206-104451-16', 2, NULL, 'retail', 'confirmed', 'unpaid', 'UGX', 9000.00, 0.00, 0.00, 9000.00, 0.00, 9000.00, '', 1, '2026-02-06 12:44:51', 0),
(50, 'receipt', 'RC-20260206-162729-37', 2, NULL, 'retail', 'confirmed', 'unpaid', 'UGX', 3000.00, 0.00, 0.00, 3000.00, 0.00, 3000.00, '', 1, '2026-02-06 18:27:29', 0),
(53, 'receipt', 'RC-20260206-163445-83', 2, NULL, 'retail', 'confirmed', 'unpaid', 'UGX', 9000.00, 0.00, 0.00, 19000.00, 0.00, 19000.00, '', 1, '2026-02-06 18:34:45', 1),
(54, 'receipt', 'RC-20260207-111613-77', 2, NULL, 'retail', 'confirmed', 'unpaid', 'UGX', 9000.00, 0.00, 0.00, 19000.00, 0.00, 19000.00, '', 1, '2026-02-07 13:16:13', 1),
(55, 'receipt', 'RC-20260207-111637-71', 2, NULL, 'retail', 'confirmed', 'unpaid', 'UGX', 3000.00, 0.00, 0.00, 3000.00, 0.00, 3000.00, '', 1, '2026-02-07 13:16:37', 0),
(56, 'receipt', 'RC-20260207-155334-54', 2, 1, 'retail', 'confirmed', 'unpaid', 'UGX', 3000.00, 0.00, 0.00, 3000.00, 0.00, 3000.00, '', 1, '2026-02-07 17:53:34', 0),
(57, 'receipt', 'RC-20260209-182644-34', 2, NULL, 'retail', 'confirmed', 'paid', 'UGX', 800000.00, 0.00, 0.00, 800000.00, 800000.00, 0.00, '', 1, '2026-02-09 20:26:44', 0),
(58, 'receipt', 'RC-20260209-183348-28', 2, NULL, 'retail', 'confirmed', 'unpaid', 'UGX', 800000.00, 0.00, 0.00, 800000.00, 0.00, 800000.00, '', 1, '2026-02-09 20:33:48', 0),
(59, 'receipt', 'RC-20260209-183550-63', 2, NULL, 'retail', 'confirmed', 'unpaid', 'UGX', 800000.00, 0.00, 0.00, 800000.00, 0.00, 800000.00, '', 1, '2026-02-09 20:35:50', 0),
(60, 'receipt', 'RC-20260209-183558-40', 2, NULL, 'retail', 'confirmed', 'unpaid', 'UGX', 800000.00, 0.00, 0.00, 800000.00, 0.00, 800000.00, '', 1, '2026-02-09 20:35:58', 0),
(61, 'receipt', 'RC-20260209-183622-96', 1, NULL, 'retail', 'confirmed', 'unpaid', 'UGX', 8000.00, 0.00, 0.00, 8000.00, 0.00, 8000.00, '', 1, '2026-02-09 20:36:22', 0),
(62, 'receipt', 'RC-20260210-091757-21', 2, NULL, 'retail', 'confirmed', 'partial', 'UGX', 800000.00, 0.00, 0.00, 830000.00, 812000.00, 18000.00, '', 1, '2026-02-10 11:17:57', 1),
(63, 'receipt', 'RC-20260216-112551-40', 2, 1, 'retail', 'confirmed', 'paid', 'UGX', 220000.00, 0.00, 0.00, 235000.00, 250000.00, 0.00, '', 1, '2026-02-16 13:25:51', 1),
(64, 'receipt', 'RC-20260217-154346-85', 2, NULL, 'wholesale', 'confirmed', 'paid', 'UGX', 180000.00, 0.00, 0.00, 180000.00, 200000.00, 0.00, '', 1, '2026-02-17 17:43:46', 0);

-- --------------------------------------------------------

--
-- Table structure for table `sale_items`
--

CREATE TABLE `sale_items` (
  `id` bigint(20) NOT NULL,
  `sale_id` bigint(20) NOT NULL,
  `product_id` bigint(20) DEFAULT NULL,
  `is_external` tinyint(1) NOT NULL DEFAULT 0,
  `sku_snapshot` varchar(64) DEFAULT NULL,
  `name_snapshot` varchar(200) NOT NULL,
  `unit_type_snapshot` varchar(30) DEFAULT NULL,
  `pieces_per_box_snapshot` int(11) DEFAULT NULL,
  `qty_input` decimal(12,3) NOT NULL DEFAULT 0.000,
  `qty_base` int(11) NOT NULL DEFAULT 0,
  `price_mode_snapshot` enum('retail','wholesale') NOT NULL DEFAULT 'retail',
  `unit_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `external_cost` decimal(12,2) DEFAULT NULL,
  `external_source` varchar(120) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sale_items`
--

INSERT INTO `sale_items` (`id`, `sale_id`, `product_id`, `is_external`, `sku_snapshot`, `name_snapshot`, `unit_type_snapshot`, `pieces_per_box_snapshot`, `qty_input`, `qty_base`, `price_mode_snapshot`, `unit_price`, `discount_amount`, `line_total`, `external_cost`, `external_source`, `created_at`) VALUES
(32, 34, 1, 0, '12132', 'Round Cup', NULL, NULL, 0.000, 1, 'retail', 3000.00, 0.00, 3000.00, NULL, NULL, '2026-02-02 14:18:48'),
(33, 35, 1, 0, '12132', 'Round Cup', NULL, NULL, 0.000, 1, 'retail', 3000.00, 0.00, 3000.00, NULL, NULL, '2026-02-02 14:28:03'),
(34, 36, 1, 0, '12132', 'Round Cup', NULL, NULL, 0.000, 1, 'retail', 3000.00, 0.00, 3000.00, NULL, NULL, '2026-02-02 14:36:10'),
(35, 37, 1, 0, '12132', 'Round Cup', NULL, NULL, 0.000, 1, 'retail', 3000.00, 0.00, 3000.00, NULL, NULL, '2026-02-02 14:42:18'),
(36, 38, 1, 0, '12132', 'Round Cup', NULL, NULL, 0.000, 2, 'retail', 3000.00, 0.00, 6000.00, NULL, NULL, '2026-02-02 14:44:01'),
(37, 39, 1, 0, '12132', 'Round Cup', NULL, NULL, 0.000, 1, 'retail', 3000.00, 0.00, 3000.00, NULL, NULL, '2026-02-02 14:50:49'),
(38, 40, 1, 0, '12132', 'Round Cup', NULL, NULL, 0.000, 1, 'retail', 3000.00, 0.00, 3000.00, NULL, NULL, '2026-02-02 15:03:29'),
(39, 41, 1, 0, '12132', 'Round Cup', NULL, NULL, 0.000, 1, 'retail', 3000.00, 0.00, 3000.00, NULL, NULL, '2026-02-02 15:07:57'),
(40, 42, 1, 0, '12132', 'Round Cup', NULL, NULL, 0.000, 1, 'retail', 3000.00, 0.00, 3000.00, NULL, NULL, '2026-02-02 15:14:27'),
(41, 43, 1, 0, '12132', 'Round Cup', NULL, NULL, 0.000, 1, 'retail', 3000.00, 0.00, 3000.00, NULL, NULL, '2026-02-02 15:16:05'),
(42, 44, 1, 0, '12132', 'Round Cup', NULL, NULL, 0.000, 1, 'retail', 3000.00, 0.00, 3000.00, NULL, NULL, '2026-02-02 15:17:25'),
(43, 45, 1, 0, '12132', 'Round Cup', NULL, NULL, 0.000, 5, 'retail', 3000.00, 0.00, 15000.00, NULL, NULL, '2026-02-03 16:20:22'),
(44, 46, 1, 0, '12132', 'Round Cup', NULL, NULL, 0.000, 2, 'retail', 3000.00, 0.00, 6000.00, NULL, NULL, '2026-02-03 16:21:46'),
(45, 47, 1, 0, '12132', 'Round Cup', NULL, NULL, 0.000, 7, 'retail', 3000.00, 0.00, 21000.00, NULL, NULL, '2026-02-03 16:24:29'),
(46, 48, 1, 0, '12132', 'Round Cup', NULL, NULL, 0.000, 8, 'retail', 3000.00, 0.00, 24000.00, NULL, NULL, '2026-02-03 16:25:16'),
(47, 49, 1, 0, '12132', 'Round Cup', NULL, NULL, 0.000, 3, 'retail', 3000.00, 0.00, 9000.00, NULL, NULL, '2026-02-06 12:44:51'),
(48, 50, 1, 0, '12132', 'Round Cup', NULL, NULL, 0.000, 1, 'retail', 3000.00, 0.00, 3000.00, NULL, NULL, '2026-02-06 18:27:29'),
(51, 53, 1, 0, '12132', 'Round Cup', NULL, NULL, 0.000, 3, 'retail', 3000.00, 0.00, 9000.00, NULL, NULL, '2026-02-06 18:34:45'),
(52, 54, 1, 0, '12132', 'Round Cup', NULL, NULL, 0.000, 3, 'retail', 3000.00, 0.00, 9000.00, NULL, NULL, '2026-02-07 13:16:13'),
(53, 55, 1, 0, '12132', 'Round Cup', NULL, NULL, 0.000, 1, 'retail', 3000.00, 0.00, 3000.00, NULL, NULL, '2026-02-07 13:16:37'),
(54, 56, 1, 0, '12132', 'Round Cup', NULL, NULL, 0.000, 1, 'retail', 3000.00, 0.00, 3000.00, NULL, NULL, '2026-02-07 17:53:34'),
(55, 57, 4, 0, 'g2', 'Hp 840 G2', NULL, NULL, 0.000, 1, 'retail', 800000.00, 0.00, 800000.00, NULL, NULL, '2026-02-09 20:26:44'),
(56, 58, 4, 0, 'g2', 'Hp 840 G2', NULL, NULL, 0.000, 1, 'retail', 800000.00, 0.00, 800000.00, NULL, NULL, '2026-02-09 20:33:48'),
(57, 59, 4, 0, 'g2', 'Hp 840 G2', NULL, NULL, 0.000, 1, 'retail', 800000.00, 0.00, 800000.00, NULL, NULL, '2026-02-09 20:35:50'),
(58, 60, 4, 0, 'g2', 'Hp 840 G2', NULL, NULL, 0.000, 1, 'retail', 800000.00, 0.00, 800000.00, NULL, NULL, '2026-02-09 20:35:58'),
(59, 61, 2, 0, '31223123', 'Red Cup', NULL, NULL, 0.000, 1, 'retail', 8000.00, 0.00, 8000.00, NULL, NULL, '2026-02-09 20:36:22'),
(60, 62, 4, 0, 'g2', 'Hp 840 G2', NULL, NULL, 0.000, 1, 'retail', 800000.00, 0.00, 800000.00, NULL, NULL, '2026-02-10 11:17:57'),
(61, 63, 5, 0, '23434', 'Tecno Spark 9C', NULL, NULL, 0.000, 1, 'retail', 220000.00, 0.00, 220000.00, NULL, NULL, '2026-02-16 13:25:51'),
(62, 64, 6, 0, '23132', 'Acer Mouse', NULL, NULL, 0.000, 30, 'retail', 6000.00, 0.00, 180000.00, NULL, NULL, '2026-02-17 17:43:46');

-- --------------------------------------------------------

--
-- Table structure for table `sale_payments`
--

CREATE TABLE `sale_payments` (
  `id` bigint(20) NOT NULL,
  `sale_id` bigint(20) NOT NULL,
  `method` enum('cash','mobile_money','bank') NOT NULL,
  `provider` varchar(40) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `reference` varchar(80) DEFAULT NULL,
  `received_by` bigint(20) NOT NULL,
  `received_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sale_payments`
--

INSERT INTO `sale_payments` (`id`, `sale_id`, `method`, `provider`, `amount`, `reference`, `received_by`, `received_at`) VALUES
(7, 34, 'cash', '', 5000.00, '', 1, '2026-02-02 14:18:48'),
(8, 37, 'cash', '', 4000.00, '', 1, '2026-02-02 14:42:18'),
(9, 39, 'cash', '', 8000.00, '', 1, '2026-02-02 14:50:49'),
(10, 46, 'cash', '', 10000.00, '', 1, '2026-02-03 16:21:46'),
(11, 47, 'cash', '', 25000.00, '', 1, '2026-02-03 16:24:29'),
(12, 48, 'cash', '', 30000.00, '', 1, '2026-02-03 16:25:16'),
(13, 57, 'cash', '', 800000.00, '', 1, '2026-02-09 20:26:44'),
(14, 62, 'cash', '', 812000.00, '', 1, '2026-02-10 11:17:57'),
(15, 63, 'cash', '', 250000.00, '', 1, '2026-02-16 13:25:51'),
(16, 64, 'cash', '', 200000.00, '', 1, '2026-02-17 17:43:46');

-- --------------------------------------------------------

--
-- Table structure for table `sale_returns`
--

CREATE TABLE `sale_returns` (
  `id` bigint(20) NOT NULL,
  `sale_id` bigint(20) NOT NULL,
  `return_no` varchar(50) NOT NULL,
  `reason` text NOT NULL,
  `refund_amount` decimal(12,2) DEFAULT 0.00,
  `status` enum('pending','approved','completed','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `selling_location_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) NOT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `refunded` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sale_returns`
--

INSERT INTO `sale_returns` (`id`, `sale_id`, `return_no`, `reason`, `refund_amount`, `status`, `selling_location_id`, `created_at`, `created_by`, `updated_at`, `refunded`) VALUES
(3, 42, 'RET-2026-000042-ea90', 'Not taken', 2000.00, 'pending', 3, '2026-02-02 17:37:34', 1, '2026-02-02 19:37:34', 1),
(4, 35, 'RET-2026-000035-356d', 'brought back', 3000.00, 'completed', 1, '2026-02-02 00:00:00', 1, NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(128) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `data` mediumblob NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `last_activity` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sessions`
--

INSERT INTO `sessions` (`id`, `user_id`, `data`, `ip_address`, `user_agent`, `last_activity`, `created_at`, `updated_at`) VALUES
('1p1k6e3b9id4kkgkio8ok4m6ei', NULL, 0x637372667c733a36343a2261633731623936663963636134653564313937643535383233323432333334643632333562346237383265313035356664303732663036616262653134393661223b, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', 1770659055, '2026-02-09 17:44:15', NULL),
('2sl3mkrphdlade3hc23341r8p9', NULL, 0x637372667c733a36343a2236333765623830626634333434656131323130353731343439313235613330393061656161363961663031383266376433306437626561646363656537613662223b, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', 1770660370, '2026-02-09 18:06:10', NULL),
('3g9f3dnr8sdoh3htqpbaqj0t81', NULL, 0x637372667c733a36343a2232626633666665373762373534306261616365633365313862303831303032663864333433613266326236626264396430336462326664623732663737333536223b, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', 1770710138, '2026-02-10 07:55:38', NULL),
('4t7438vj7kli00ta22d2nqcm99', 1, 0x637372667c733a36343a2233626662306231646630333035366165326233386539336461306561613161303633336638356164396464613636623534306364303237663264363563386137223b757365727c613a353a7b733a323a226964223b693a313b733a373a22726f6c655f6964223b693a313b733a343a22726f6c65223b733a31313a2273757065725f61646d696e223b733a383a22757365726e616d65223b733a353a2261646d696e223b733a343a226e616d65223b733a31313a2253757065722041646d696e223b7d, '192.168.100.92', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', 1770659190, '2026-02-09 16:27:06', '2026-02-09 17:46:30'),
('5e2dole4v7k34ikr8bkt0pc65n', 2, 0x637372667c733a36343a2230376430623165326664383038663534623663323635313866316130396630626664373839616236643661636237363962643635663235373333613139633338223b757365727c613a353a7b733a323a226964223b693a323b733a373a22726f6c655f6964223b693a323b733a343a22726f6c65223b733a373a2263617368696572223b733a383a22757365726e616d65223b733a383a226361736869657231223b733a343a226e616d65223b733a31313a2243617368696572204f6e65223b7d, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1770643671, '2026-02-09 13:04:05', '2026-02-09 13:27:51'),
('7hjf4dlvugggbnttd44k6e65ak', NULL, 0x637372667c733a36343a2262323239373136393366326535303932616462356337383937326633623962373461343066626533313065646635333265643563616636323437336235363239223b, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', 1770747582, '2026-02-10 18:18:14', '2026-02-10 18:19:42'),
('7vr00nh6n4klpb8vehd95o1vvt', NULL, 0x637372667c733a36343a2234636263633962366165333365333365343734336234663738653832326363616132383430646161386565303436383264363536363934653336393431386662223b, '::1', '', 1770809789, '2026-02-11 11:36:29', NULL),
('81imr3c9mlls171ku2p85pov01', 1, 0x637372667c733a36343a2234373839616131303931646364363731306634653832613835303734383233373064306530623035313530623031383332663633623162656361623662643064223b757365727c613a353a7b733a323a226964223b693a313b733a373a22726f6c655f6964223b693a313b733a343a22726f6c65223b733a31313a2273757065725f61646d696e223b733a383a22757365726e616d65223b733a353a2261646d696e223b733a343a226e616d65223b733a31313a2253757065722041646d696e223b7d, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', 1770831444, '2026-02-11 17:37:20', '2026-02-11 17:37:24'),
('8ut81cs53t74nqam3bjs84vhrt', NULL, 0x637372667c733a36343a2265646365386337336638333039353561376432646136306632643561303336653263656662303962326130313637363961653264636636353265373037646339223b, '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.4652', 1770809678, '2026-02-11 11:34:38', NULL),
('an5lar5pg5bretjg1lkrqk8sao', NULL, 0x637372667c733a36343a2233636438333565323130646266306336663561353265313464373361333064613237346430313939373566633563386239313064663636363334356133316662223b, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', 1770746595, '2026-02-10 18:03:15', NULL),
('bi8makrgcj39e8aeco8lvfdjjq', NULL, 0x637372667c733a36343a2237353933303461653136633532643037643437626262363861343564303065363133343638353764306438316335366265323930343662623535653161356539223b, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', 1770827736, '2026-02-11 16:35:36', NULL),
('c5kmuthdsmo0mh80j45v4f4snd', NULL, 0x637372667c733a36343a2262373836326235613837613264376561666661623561663564643766373461383830663033333364313430343437653664313764623363313661616236623535223b, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 1770806217, '2026-02-09 13:28:17', '2026-02-11 10:36:57'),
('c7fkcqf6oda5hoqt2vc46mtku6', 1, 0x637372667c733a36343a2232623866383233323534376264353033363830343663613162366366333466363339353439396131356161396338363836666635366439356466623964323834223b757365727c613a353a7b733a323a226964223b693a313b733a373a22726f6c655f6964223b693a313b733a343a22726f6c65223b733a31313a2273757065725f61646d696e223b733a383a22757365726e616d65223b733a353a2261646d696e223b733a343a226e616d65223b733a31313a2253757065722041646d696e223b7d637372665f746f6b656e7c733a36343a2233633266373766393363373930626665663262656331313238326137386362363239643330333932306363376563343932643334633133366137383837303633223b, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', 1771339558, '2026-02-17 14:37:03', '2026-02-17 14:45:58'),
('cfn53nme5j7r5utsrk8f0s7c3t', 1, 0x637372667c733a36343a2237323763313864353135366531336632663736333362356237306331633238623637323530633934386235313765663839663231346434643838616163626637223b757365727c613a353a7b733a323a226964223b693a313b733a373a22726f6c655f6964223b693a313b733a343a22726f6c65223b733a31313a2273757065725f61646d696e223b733a383a22757365726e616d65223b733a353a2261646d696e223b733a343a226e616d65223b733a31313a2253757065722041646d696e223b7d, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:149.0) Gecko/20100101 Firefox/149.0', 1773484412, '2026-03-14 10:33:29', '2026-03-14 10:33:32'),
('de7aht75pvnkpt57qutrt2irnl', 1, 0x637372667c733a36343a2230613330663932333731643266326133663639663137326631396335623537323665626130313435633037643265306262306234343836363866343166636239223b757365727c613a353a7b733a323a226964223b693a313b733a373a22726f6c655f6964223b693a313b733a343a22726f6c65223b733a31313a2273757065725f61646d696e223b733a383a22757365726e616d65223b733a353a2261646d696e223b733a343a226e616d65223b733a31313a2253757065722041646d696e223b7d, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', 1781618750, '2026-06-16 14:05:50', NULL),
('eaord0n2lc5h9rviqgnj624fqv', NULL, 0x637372667c733a36343a2261386232336164613162643462626338633532363130383362333132396435386636333031343735323736366535353538633736643333643764663063306238223b, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', 1770827736, '2026-02-11 16:35:36', NULL),
('gvt0q3fob5m0cld1cbm09mdjrb', NULL, 0x637372667c733a36343a2233666533623339373363646232316639666238303137396537343961653138336465393363633537653139613337623638396563623334396336313039393432223b, '::1', '', 1770809761, '2026-02-11 11:36:01', NULL),
('jm989hihraomqtkqgs7cj05sja', NULL, 0x637372667c733a36343a2265333764353864613230616532313634666432333231376263303534303834353131643264373432646466366333326462663961313239633937656262303339223b, '::1', '', 1770809803, '2026-02-11 11:36:43', NULL),
('klu2kc0esa52b5frcj44t668tc', NULL, 0x637372667c733a36343a2238626262303137633238383136636262616335386631323733623666643664616437333630363463386462666566623233623965316535356163653565333362223b, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', 1770746594, '2026-02-10 18:03:14', NULL),
('nbfgraa2l1epshrefk6mgatj0s', NULL, 0x637372667c733a36343a2236346237373933336461363764613334303063643462303436306261333639373965616333323265336134356138313562346161623964393666343663663437223b, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', 1770660370, '2026-02-09 18:06:10', NULL),
('nr2f0r0rhofmjbtibhl6mrppeu', NULL, 0x637372667c733a36343a2233376339316331393639626464373234346132663435333335383033353136633664303161623133666662346534656333663161326339633736373766663934223b, '::1', 'colly - https://github.com/gocolly/colly', 1770653788, '2026-02-09 16:16:28', NULL),
('ns40j1e9qg9p353oo0h64cgkmr', NULL, 0x637372667c733a36343a2263393962643365306434653334356334333961646531313265363266336635363330656135653230666134613735616637316463333237366637653566613836223b, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', 1770659055, '2026-02-09 17:44:15', NULL),
('p7p5ble8pomtb16ifcvpse96kl', NULL, 0x637372667c733a36343a2233616636643132653635646233383836666435326235396164323236353539343139636133633362626433386537353164306339663766656233626437666538223b, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', 1770710138, '2026-02-10 07:55:38', NULL),
('p97muh4lmku0rpcjee0m7lfuqb', NULL, 0x637372667c733a36343a2230343537356535623961646431356338633737336238663139383563303666333062643037623065346264353138663733646235343834393137313666633736223b, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', 1770651066, '2026-02-09 15:31:06', NULL),
('pm25htbmr0urs3b7d96q6cdmn1', 1, 0x637372667c733a36343a2266396661626439356633306535626162616463373032356236356133323231626261666363306632616166323935393134306338326439323937303865653238223b757365727c613a353a7b733a323a226964223b693a313b733a373a22726f6c655f6964223b693a313b733a343a22726f6c65223b733a31313a2273757065725f61646d696e223b733a383a22757365726e616d65223b733a353a2261646d696e223b733a343a226e616d65223b733a31313a2253757065722041646d696e223b7d, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', 1771342292, '2026-02-17 14:46:12', '2026-02-17 15:31:32'),
('rg3f33rtq5o7p3kcb3bsg01p5k', NULL, 0x637372667c733a36343a2238633833333537643261376335396665313535623431383031386339623064353136643737323632643036383438643562353935663663333138333239373739223b, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', 1770811766, '2026-02-11 12:09:26', NULL),
('skb6ged3b6f1udaigahc98fjfe', NULL, 0x637372667c733a36343a2237363666646231633361343633363132323133356334343539353233376533383065373930643832396636386437643135656639636634363466616536663863223b, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', 1770811765, '2026-02-11 12:09:25', NULL),
('u49jd81k3jin9c99pnht7c2mj6', NULL, 0x637372667c733a36343a2236343035393339653735336661393433633131386138343238366566653933626138386337666537353731636335323632343364343131626236636663323937223b, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', 1770651065, '2026-02-09 15:31:05', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `key` varchar(100) NOT NULL,
  `group` varchar(60) NOT NULL DEFAULT 'General',
  `type` varchar(20) NOT NULL DEFAULT 'text',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `value` text DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `key`, `group`, `type`, `sort_order`, `value`, `description`, `created_at`, `updated_at`) VALUES
(1, 'business_name', 'General', 'text', 0, 'Business Manager Pro 1.2', 'Business name for receipts and invoices', '2026-02-02 11:29:48', '2026-02-11 12:00:37'),
(2, 'business_address', 'General', 'text', 0, 'Shop B09\r\nMbarara, Uganda', '', '2026-02-02 11:29:48', '2026-02-11 12:13:09'),
(3, 'business_phone', 'General', 'text', 0, '+256 700 868 939', '', '2026-02-02 11:29:48', '2026-02-11 12:10:33'),
(4, 'business_email', 'General', 'text', 0, 'developer@almatechconsults.com', 'Business email address', '2026-02-02 11:29:48', '2026-02-11 11:52:16'),
(5, 'receipt_header', 'General', 'text', 0, 'THANK YOU FOR SHOPPING WITH US\r\nPlease come again!', '', '2026-02-02 11:29:48', '2026-02-11 12:07:50'),
(6, 'receipt_footer', 'General', 'text', 0, 'Thank you for your purchase!\r\nAll sales are final\r\nNo returns without receipt\r\nVisit us again soon!                                ', 'Footer text for receipts', '2026-02-02 11:29:48', '2026-02-11 12:03:05'),
(7, 'business_logo', 'General', 'text', 0, '', 'Business logo URL or path', '2026-02-02 11:29:48', '2026-02-02 11:29:48'),
(8, 'business_website', 'General', 'text', 0, 'https://www.almatechconsults.com', '', '2026-02-02 11:29:48', '2026-02-11 12:10:21'),
(9, 'business_tax_id', 'General', 'text', 0, '', '', '2026-02-02 11:29:48', '2026-02-11 12:10:05'),
(10, 'currency_symbol', 'General', 'text', 0, 'UGX', 'Currency symbol for display', '2026-02-02 11:29:48', '2026-02-02 11:29:48'),
(11, 'currency_code', 'General', 'text', 0, 'UGX', 'Currency code (ISO)', '2026-02-02 11:29:48', '2026-02-02 11:29:48'),
(12, 'decimal_places', 'General', 'text', 0, '0', 'Number of decimal places for currency', '2026-02-02 11:29:48', '2026-02-02 11:29:48'),
(13, 'thousands_separator', 'General', 'text', 0, ',', 'Thousands separator for numbers', '2026-02-02 11:29:48', '2026-02-02 11:29:48'),
(14, 'decimal_point', 'General', 'text', 0, '.', 'Decimal point for numbers', '2026-02-02 11:29:48', '2026-02-02 11:29:48'),
(15, 'receipt_width', 'General', 'text', 0, '80', 'Receipt width in mm for thermal printers', '2026-02-02 11:29:48', '2026-02-02 11:29:48'),
(16, 'cash_drawer_port', 'General', 'text', 0, 'COM1', 'Cash drawer port (if applicable)', '2026-02-02 11:29:48', '2026-02-02 11:29:48'),
(17, 'printer_name', 'General', 'text', 0, 'Default Printer', 'Default receipt printer name', '2026-02-02 11:29:48', '2026-02-02 11:29:48'),
(18, 'auto_print_receipt', 'General', 'text', 0, '1', 'Auto print receipt after sale (1=on, 0=off)', '2026-02-02 11:29:48', '2026-02-11 12:05:36'),
(19, 'auto_open_drawer', 'General', 'text', 0, '1', 'Auto open cash drawer after sale (1=on, 0=off)', '2026-02-02 11:29:48', '2026-02-02 11:29:48'),
(20, 'show_customer_copy', 'General', 'text', 0, '1', 'Show customer copy option (1=on, 0=off)', '2026-02-02 11:29:48', '2026-02-02 11:29:48'),
(21, 'show_cashier_copy', 'General', 'text', 0, '1', 'Show cashier copy option (1=on, 0=off)', '2026-02-02 11:29:48', '2026-02-02 11:29:48'),
(22, 'app_theme', 'General', 'text', 0, 'default', NULL, '2026-02-06 09:26:27', '2026-02-10 18:25:28');

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `employee_id` varchar(50) DEFAULT NULL,
  `role` varchar(100) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `hire_date` date DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `status` enum('active','inactive','on_leave','terminated') DEFAULT 'active',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stock_by_location`
--

CREATE TABLE `stock_by_location` (
  `product_id` int(11) NOT NULL,
  `location_id` int(11) NOT NULL,
  `qty_base` decimal(12,2) NOT NULL DEFAULT 0.00,
  `low_level_base` decimal(12,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_by_location`
--

INSERT INTO `stock_by_location` (`product_id`, `location_id`, `qty_base`, `low_level_base`) VALUES
(1, 1, 10.00, 5.00),
(1, 2, 37.00, 5.00),
(2, 1, 31.00, 6.00),
(4, 1, 5.00, 2.00),
(4, 2, 5.00, 0.00),
(4, 3, 5.00, 0.00),
(5, 1, 40.00, 8.00),
(5, 2, 7.00, 0.00),
(6, 1, 850.00, 50.00),
(6, 2, 120.00, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `stock_movements`
--

CREATE TABLE `stock_movements` (
  `id` bigint(20) NOT NULL,
  `product_id` int(11) NOT NULL,
  `from_location_id` int(11) DEFAULT NULL,
  `to_location_id` int(11) DEFAULT NULL,
  `movement_type` enum('stock_in','sale','return','adjustment','transfer') NOT NULL,
  `qty_change` decimal(12,2) NOT NULL,
  `qty_before` decimal(12,2) NOT NULL,
  `qty_after` decimal(12,2) NOT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` varchar(80) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_movements`
--

INSERT INTO `stock_movements` (`id`, `product_id`, `from_location_id`, `to_location_id`, `movement_type`, `qty_change`, `qty_before`, `qty_after`, `reference_type`, `reference_id`, `note`, `created_by`, `created_at`) VALUES
(1, 1, 2, 1, 'transfer', -5.00, 10.00, 5.00, 'transfer', NULL, '', 1, '2026-01-23 12:56:54'),
(2, 1, 1, 2, 'transfer', -4.00, 10.00, 6.00, 'transfer', NULL, '', 1, '2026-01-23 13:28:47'),
(3, 1, 2, 2, 'adjustment', -2.00, 9.00, 7.00, 'adjustment', NULL, '', 1, '2026-01-23 13:34:46'),
(5, 1, 2, NULL, 'sale', -1.00, 7.00, 6.00, 'receipt', '2', 'POS RC-2026-13560176', 1, '2026-01-25 10:56:01'),
(6, 1, 2, NULL, 'sale', -1.00, 6.00, 5.00, 'receipt', '3', 'POS RC-2026-13573862', 1, '2026-01-25 10:57:38'),
(7, 1, 2, NULL, 'sale', -1.00, 5.00, 4.00, 'receipt', '4', 'POS RC-2026-14002542', 1, '2026-01-25 11:00:25'),
(8, 1, 2, NULL, 'sale', -1.00, 4.00, 3.00, 'receipt', '5', 'POS RC-2026-14022312', 1, '2026-01-25 11:02:23'),
(9, 1, 2, NULL, 'sale', -1.00, 3.00, 2.00, 'receipt', '6', 'POS RC-2026-14273148', 1, '2026-01-25 11:27:31'),
(10, 1, 2, NULL, 'sale', -1.00, 2.00, 1.00, 'receipt', '7', 'POS RC-2026-14284940', 1, '2026-01-25 11:28:49'),
(11, 1, 2, NULL, 'sale', -1.00, 1.00, 0.00, 'receipt', '8', 'POS RC-2026-14285799', 1, '2026-01-25 11:28:57'),
(12, 1, 2, NULL, 'sale', -1.00, 0.00, -1.00, 'receipt', '9', 'POS RC-2026-14311048', 1, '2026-01-25 11:31:10'),
(13, 1, 2, NULL, 'sale', -1.00, -1.00, -2.00, 'receipt', '10', 'POS RC-2026-14410642', 1, '2026-01-25 11:41:06'),
(14, 1, 2, NULL, 'sale', -1.00, -2.00, -3.00, 'receipt', '11', 'POS RC-2026-14430126', 1, '2026-01-25 11:43:01'),
(15, 1, 2, NULL, 'sale', -1.00, -3.00, -4.00, 'receipt', '12', 'POS RC-2026-14431772', 1, '2026-01-25 11:43:17'),
(16, 1, 2, NULL, 'sale', -1.00, -4.00, -5.00, 'receipt', '13', 'POS RC-2026-14521198', 1, '2026-01-25 11:52:11'),
(17, 1, 1, 1, 'stock_in', 10.00, 10.00, 20.00, 'stock_in', NULL, '', 1, '2026-01-25 14:55:42'),
(18, 1, 1, 1, 'stock_in', 2.00, 20.00, 22.00, 'stock_in', NULL, '', 1, '2026-01-25 14:58:39'),
(19, 1, 1, 1, 'stock_in', 10.00, 22.00, 32.00, 'stock_in', NULL, '', 1, '2026-01-25 15:03:34'),
(20, 1, 1, 1, 'stock_in', 3.00, 32.00, 35.00, 'stock_in', NULL, '', 1, '2026-01-25 15:04:16'),
(21, 1, 2, 2, 'stock_in', 10.00, -5.00, 5.00, 'stock_in', NULL, '', 1, '2026-01-25 15:05:00'),
(22, 1, 2, 2, 'stock_in', 1.00, 5.00, 6.00, 'stock_in', NULL, '', 1, '2026-01-25 15:07:05'),
(23, 1, 2, 2, 'stock_in', 0.50, 6.00, 6.50, 'stock_in', NULL, '', 1, '2026-01-25 15:07:33'),
(24, 1, 2, 2, 'stock_in', 0.50, 6.50, 7.00, 'stock_in', NULL, '', 1, '2026-01-25 15:07:55'),
(25, 1, 2, 2, 'sale', -1.00, 0.00, 0.00, 'receipt', '19', 'RC-20260202-082550-89', 1, '2026-02-02 07:25:50'),
(26, 1, 2, 2, 'sale', -1.00, 0.00, 0.00, 'receipt', '20', 'RC-20260202-082649-25', 1, '2026-02-02 07:26:49'),
(27, 1, 2, 2, 'sale', -1.00, 0.00, 0.00, 'receipt', '21', 'RC-20260202-082716-39', 1, '2026-02-02 07:27:16'),
(29, 1, 2, 2, 'sale', -1.00, 0.00, 0.00, 'receipt', '23', 'RC-20260202-095436-39', 1, '2026-02-02 08:54:36'),
(31, 1, 2, 2, 'sale', -1.00, 0.00, 0.00, 'receipt', '25', 'RC-20260202-100153-71', 1, '2026-02-02 09:01:53'),
(32, 1, 2, 2, 'sale', -1.00, 0.00, 0.00, 'receipt', '26', 'RC-20260202-100204-98', 1, '2026-02-02 09:02:04'),
(33, 1, 2, 2, 'sale', -1.00, 0.00, 0.00, 'receipt', '27', 'RC-20260202-101330-61', 1, '2026-02-02 09:13:30'),
(34, 1, 1, 1, 'sale', -1.00, 0.00, 0.00, 'receipt', '28', 'RC-20260202-102356-12', 1, '2026-02-02 09:23:56'),
(35, 1, 1, 1, 'sale', -1.00, 0.00, 0.00, 'receipt', '29', 'RC-20260202-103552-18', 1, '2026-02-02 09:35:52'),
(36, 1, 1, 1, 'sale', -1.00, 0.00, 0.00, 'receipt', '30', 'RC-20260202-104626-59', 1, '2026-02-02 09:46:26'),
(37, 1, 1, 1, 'sale', -1.00, 0.00, 0.00, 'receipt', '31', 'RC-20260202-110428-31', 1, '2026-02-02 10:04:28'),
(38, 1, 1, 1, 'sale', -1.00, 0.00, 0.00, 'receipt', '32', 'RC-20260202-120958-26', 1, '2026-02-02 11:09:58'),
(39, 1, 1, 1, 'sale', -1.00, 0.00, 0.00, 'receipt', '33', 'RC-20260202-121507-48', 1, '2026-02-02 11:15:07'),
(40, 1, 1, 1, 'sale', -1.00, 0.00, 0.00, 'receipt', '34', 'RC-20260202-121847-16', 1, '2026-02-02 11:18:48'),
(41, 1, 1, 1, 'sale', -1.00, 0.00, 0.00, 'receipt', '35', 'RC-20260202-122803-88', 1, '2026-02-02 11:28:03'),
(42, 1, 1, 1, 'sale', -1.00, 0.00, 0.00, 'receipt', '36', 'RC-20260202-123610-65', 1, '2026-02-02 11:36:10'),
(43, 1, 1, 1, 'sale', -1.00, 0.00, 0.00, 'receipt', '37', 'RC-20260202-124218-85', 1, '2026-02-02 11:42:18'),
(44, 1, 1, 1, 'sale', -2.00, 0.00, 0.00, 'receipt', '38', 'RC-20260202-124401-78', 1, '2026-02-02 11:44:01'),
(45, 1, 1, 1, 'sale', -1.00, 0.00, 0.00, 'receipt', '39', 'RC-20260202-125049-71', 1, '2026-02-02 11:50:49'),
(46, 1, 1, 1, 'sale', -1.00, 0.00, 0.00, 'receipt', '40', 'RC-20260202-130329-86', 1, '2026-02-02 12:03:29'),
(47, 1, 1, 1, 'sale', -1.00, 0.00, 0.00, 'receipt', '41', 'RC-20260202-130757-62', 1, '2026-02-02 12:07:57'),
(48, 1, 1, 1, 'sale', -1.00, 0.00, 0.00, 'receipt', '42', 'RC-20260202-131427-43', 1, '2026-02-02 12:14:27'),
(49, 1, 1, 1, 'sale', -1.00, 0.00, 0.00, 'receipt', '43', 'RC-20260202-131605-62', 1, '2026-02-02 12:16:05'),
(50, 1, 1, 1, 'sale', -1.00, 0.00, 0.00, 'receipt', '44', 'RC-20260202-131725-40', 1, '2026-02-02 12:17:25'),
(51, 1, 2, 2, 'stock_in', 2.00, 0.00, 2.00, 'stock_in', NULL, '', 1, '2026-02-03 13:16:11'),
(52, 1, 2, 2, 'stock_in', 2.00, 2.00, 4.00, 'stock_in', NULL, '', 1, '2026-02-03 13:17:47'),
(53, 1, 2, 2, 'stock_in', 50.00, 4.00, 54.00, 'stock_in', NULL, '', 1, '2026-02-03 13:18:14'),
(54, 1, 2, 2, 'sale', -5.00, 0.00, 0.00, 'receipt', '45', 'RC-20260203-142021-17', 1, '2026-02-03 13:20:22'),
(55, 1, 2, 2, 'sale', -2.00, 0.00, 0.00, 'receipt', '46', 'RC-20260203-142146-40', 1, '2026-02-03 13:21:46'),
(56, 1, 2, 2, 'sale', -7.00, 0.00, 0.00, 'receipt', '47', 'RC-20260203-142429-99', 1, '2026-02-03 13:24:29'),
(57, 1, 1, 1, 'sale', -8.00, 0.00, 0.00, 'receipt', '48', 'RC-20260203-142516-10', 1, '2026-02-03 13:25:16'),
(58, 1, 1, 2, 'transfer', -10.00, 10.00, 0.00, 'transfer', NULL, '', 1, '2026-02-03 13:26:43'),
(59, 1, 2, 1, 'transfer', -1.00, 50.00, 49.00, 'transfer', NULL, 'stocking', 1, '2026-02-03 13:27:27'),
(60, 1, 2, 2, 'sale', -3.00, 0.00, 0.00, 'receipt', '49', 'RC-20260206-104451-16', 1, '2026-02-06 09:44:51'),
(61, 1, 2, 2, 'sale', -1.00, 0.00, 0.00, 'receipt', '50', 'RC-20260206-162729-37', 1, '2026-02-06 15:27:29'),
(64, 1, 2, 2, 'sale', -3.00, 0.00, 0.00, 'receipt', '53', 'RC-20260206-163445-83', 1, '2026-02-06 15:34:45'),
(65, 1, 2, 2, 'sale', -3.00, 0.00, 0.00, 'receipt', '54', 'RC-20260207-111613-77', 1, '2026-02-07 10:16:14'),
(66, 1, 2, 2, 'sale', -1.00, 0.00, 0.00, 'receipt', '55', 'RC-20260207-111637-71', 1, '2026-02-07 10:16:37'),
(67, 1, 2, 2, 'sale', -1.00, 0.00, 0.00, 'receipt', '56', 'RC-20260207-155334-54', 1, '2026-02-07 14:53:34'),
(68, 4, NULL, 2, 'stock_in', 10.00, 0.00, 10.00, 'stock_in', '', '', 1, '2026-02-09 17:14:42'),
(69, 4, NULL, 3, 'stock_in', 5.00, 0.00, 5.00, 'stock_in', '', '', 1, '2026-02-09 17:17:39'),
(70, 4, 2, 2, 'sale', -1.00, 0.00, 0.00, 'receipt', '57', 'RC-20260209-182644-34', 1, '2026-02-09 17:26:44'),
(71, 4, 2, 2, 'sale', -1.00, 0.00, 0.00, 'receipt', '58', 'RC-20260209-183348-28', 1, '2026-02-09 17:33:48'),
(72, 4, 2, 2, 'sale', -1.00, 0.00, 0.00, 'receipt', '59', 'RC-20260209-183550-63', 1, '2026-02-09 17:35:50'),
(73, 4, 2, 2, 'sale', -1.00, 0.00, 0.00, 'receipt', '60', 'RC-20260209-183558-40', 1, '2026-02-09 17:35:58'),
(74, 2, 1, 1, 'sale', -1.00, 0.00, 0.00, 'receipt', '61', 'RC-20260209-183622-96', 1, '2026-02-09 17:36:22'),
(75, 4, 2, 2, 'sale', -1.00, 0.00, 0.00, 'receipt', '62', 'RC-20260210-091757-21', 1, '2026-02-10 08:17:57'),
(76, 5, 1, 2, 'transfer', -8.00, 48.00, 40.00, 'transfer', NULL, 'Ibam', 1, '2026-02-16 10:22:44'),
(77, 5, 2, 2, 'sale', -1.00, 0.00, 0.00, 'receipt', '63', 'RC-20260216-112551-40', 1, '2026-02-16 10:25:51'),
(78, 6, 1, 2, 'transfer', -150.00, 1000.00, 850.00, 'transfer', NULL, '', 1, '2026-02-17 14:42:12'),
(79, 6, 2, 2, 'sale', -30.00, 0.00, 0.00, 'receipt', '64', 'RC-20260217-154346-85', 1, '2026-02-17 14:43:46');

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `contact_person` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `payment_terms` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `preferred` tinyint(1) DEFAULT 0,
  `rating` decimal(3,1) DEFAULT 0.0 CHECK (`rating` >= 0 and `rating` <= 5),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `update_history`
--

CREATE TABLE `update_history` (
  `id` bigint(20) NOT NULL,
  `version_from` varchar(40) DEFAULT NULL,
  `version_to` varchar(40) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'success',
  `notes` longtext DEFAULT NULL,
  `applied_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `username` varchar(60) NOT NULL,
  `email` varchar(120) DEFAULT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `full_name` varchar(120) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `role_id`, `username`, `email`, `phone`, `full_name`, `password_hash`, `profile_photo`, `is_active`, `must_change_password`, `last_login_at`, `created_at`, `updated_at`) VALUES
(1, 1, 'admin', 'admin@almatechuganda.com', '0700868939', 'Super Admin', '$2y$10$EyS5WjBZlS40zfB2DAOYAujg5kYZZb3THDaDTpeO3NIv2GrUuFSpq', 'uploads/profiles/user_1_1770624499.jpg', 1, 0, '2026-06-16 17:05:50', '2026-01-23 07:14:23', '2026-06-16 14:05:50'),
(2, 2, 'cashier1', 'dkikabi@almatechconsults.com', '778415709', 'Kikabi David', '$2y$10$x23hT6CLw1VQHL7lR9SHsuq8WlyCP33X0bFobCYVwg1G9KpsZovAW', 'uploads/profiles/user_2_1770831052.jpg', 1, 0, '2026-02-11 19:00:16', '2026-01-23 07:14:23', '2026-02-11 17:30:52'),
(3, 3, 'accountant1', 'abronia@almatechconsults.com', '700000002', 'Ampumuza', '$2y$10$9bf8eCRNZbh2fXGWmLtwZOR4d49wE2t5.pdUDJN3tvasXAYknxgIW', 'uploads/profiles/user_3_1770824848.jpg', 1, 0, '2026-02-11 17:28:21', '2026-01-23 07:14:23', '2026-02-11 15:47:28');

-- --------------------------------------------------------

--
-- Table structure for table `user_permissions`
--

CREATE TABLE `user_permissions` (
  `user_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  `is_allowed` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vouchers`
--

CREATE TABLE `vouchers` (
  `id` int(11) NOT NULL,
  `voucher_no` varchar(30) NOT NULL,
  `voucher_date` datetime NOT NULL,
  `payee` varchar(150) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('draft','pending','approved','rejected','paid') DEFAULT 'draft',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `approvals`
--
ALTER TABLE `approvals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_type` (`approval_type`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audit_user_id` (`user_id`),
  ADD KEY `idx_audit_action` (`action`);

--
-- Indexes for table `b2b_sales_items`
--
ALTER TABLE `b2b_sales_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_b2b_sale_id` (`sale_id`),
  ADD KEY `idx_b2b_created_at` (`created_at`),
  ADD KEY `idx_b2b_supplier` (`supplier_id`),
  ADD KEY `idx_b2b_sku` (`sku`),
  ADD KEY `idx_b2b_name` (`name`),
  ADD KEY `idx_b2b_sale_created` (`sale_id`,`created_at`);

--
-- Indexes for table `bank_accounts`
--
ALTER TABLE `bank_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_account_number` (`account_number`),
  ADD KEY `idx_bank_name` (`bank_name`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- Indexes for table `bank_transactions`
--
ALTER TABLE `bank_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_account_id` (`account_id`),
  ADD KEY `idx_transaction_date` (`transaction_date`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_reconciled` (`reconciled`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `brands`
--
ALTER TABLE `brands`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_brand_name` (`name`),
  ADD KEY `idx_brand_status` (`status`);

--
-- Indexes for table `contact_categories`
--
ALTER TABLE `contact_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `idx_name` (`name`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `contact_category_map`
--
ALTER TABLE `contact_category_map`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_contact_category` (`contact_id`,`category_id`),
  ADD KEY `idx_contact_id` (`contact_id`),
  ADD KEY `idx_category_id` (`category_id`);

--
-- Indexes for table `contact_tags`
--
ALTER TABLE `contact_tags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `idx_name` (`name`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `contact_tag_map`
--
ALTER TABLE `contact_tag_map`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_contact_tag` (`contact_id`,`tag_id`),
  ADD KEY `idx_contact_id` (`contact_id`),
  ADD KEY `idx_tag_id` (`tag_id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_customers_active` (`is_active`),
  ADD KEY `idx_customers_category` (`category_id`);

--
-- Indexes for table `doc_sequences`
--
ALTER TABLE `doc_sequences`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_doc_type_year` (`doc_type`,`year`);

--
-- Indexes for table `email_log`
--
ALTER TABLE `email_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_to_email` (`to_email`),
  ADD KEY `idx_sent_at` (`sent_at`);

--
-- Indexes for table `finance`
--
ALTER TABLE `finance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_finance_user_id` (`user_id`),
  ADD KEY `idx_finance_type` (`type`),
  ADD KEY `idx_finance_created_at` (`created_at`);

--
-- Indexes for table `installments`
--
ALTER TABLE `installments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_installments_contact_id` (`contact_id`),
  ADD KEY `idx_installments_status` (`status`),
  ADD KEY `idx_installments_due_date` (`due_date`),
  ADD KEY `fk_installments_user` (`user_id`);

--
-- Indexes for table `installment_payments`
--
ALTER TABLE `installment_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_installment_id` (`installment_id`),
  ADD KEY `idx_payment_date` (`payment_date`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `locations`
--
ALTER TABLE `locations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `message_attachments`
--
ALTER TABLE `message_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_attachment_message` (`message_id`);

--
-- Indexes for table `message_logs`
--
ALTER TABLE `message_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_recipient` (`recipient_type`,`recipient_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_read` (`is_read`),
  ADD KEY `idx_scheduled` (`scheduled_at`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `idx_sender` (`sender_id`);

--
-- Indexes for table `message_reads`
--
ALTER TABLE `message_reads`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_read` (`message_id`,`user_id`),
  ADD KEY `fk_read_user` (`user_id`);

--
-- Indexes for table `message_templates`
--
ALTER TABLE `message_templates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `perm_key` (`perm_key`);

--
-- Indexes for table `phone_scan_sessions`
--
ALTER TABLE `phone_scan_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_id` (`session_id`),
  ADD KEY `idx_session` (`session_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `price_adjustments`
--
ALTER TABLE `price_adjustments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_pa_product` (`product_id`),
  ADD KEY `fk_pa_user` (`changed_by`);

--
-- Indexes for table `procurement_shopping_list`
--
ALTER TABLE `procurement_shopping_list`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_priority` (`priority`),
  ADD KEY `idx_product_name` (`product_name`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `supplier_id` (`supplier_id`);

--
-- Indexes for table `procurement_shopping_list_b2b_map`
--
ALTER TABLE `procurement_shopping_list_b2b_map`
  ADD PRIMARY KEY (`b2b_id`),
  ADD KEY `idx_procurement_id` (`procurement_id`);

--
-- Indexes for table `procurement_wanted_items`
--
ALTER TABLE `procurement_wanted_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_priority` (`priority`),
  ADD KEY `idx_item_name` (`item_name`),
  ADD KEY `idx_requested_by` (`requested_by`),
  ADD KEY `idx_approved_by` (`approved_by`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sku` (`sku`),
  ADD KEY `fk_products_category` (`category_id`),
  ADD KEY `default_location_id` (`default_location_id`);

--
-- Indexes for table `product_categories`
--
ALTER TABLE `product_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `reminders`
--
ALTER TABLE `reminders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_remind_at` (`remind_at`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `return_items`
--
ALTER TABLE `return_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `return_id` (`return_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `idx_location_id` (`location_id`),
  ADD KEY `idx_item_status` (`status`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`role_id`,`permission_id`),
  ADD KEY `fk_rp_perm` (`permission_id`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_sales_doc_no` (`doc_no`),
  ADD KEY `idx_sales_created_at` (`created_at`),
  ADD KEY `idx_sales_location` (`selling_location_id`);

--
-- Indexes for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sale_items_sale` (`sale_id`),
  ADD KEY `idx_sale_items_product` (`product_id`);

--
-- Indexes for table `sale_payments`
--
ALTER TABLE `sale_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sale_payments_sale` (`sale_id`);

--
-- Indexes for table `sale_returns`
--
ALTER TABLE `sale_returns`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `return_no` (`return_no`),
  ADD KEY `sale_id` (`sale_id`),
  ADD KEY `status` (`status`),
  ADD KEY `created_at` (`created_at`),
  ADD KEY `fk_sale_returns_user` (`created_by`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sessions_last_activity` (`last_activity`),
  ADD KEY `idx_sessions_user_id` (`user_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_key` (`key`),
  ADD KEY `idx_settings_key` (`key`),
  ADD KEY `idx_settings_updated` (`updated_at`),
  ADD KEY `idx_settings_group` (`group`,`sort_order`,`key`);

--
-- Indexes for table `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `employee_id` (`employee_id`),
  ADD KEY `idx_first_name` (`first_name`),
  ADD KEY `idx_last_name` (`last_name`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_employee_id` (`employee_id`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_department` (`department`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_hire_date` (`hire_date`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- Indexes for table `stock_by_location`
--
ALTER TABLE `stock_by_location`
  ADD PRIMARY KEY (`product_id`,`location_id`),
  ADD KEY `fk_sbl_location` (`location_id`);

--
-- Indexes for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_stock_product` (`product_id`),
  ADD KEY `idx_stock_type` (`movement_type`),
  ADD KEY `fk_sm_user` (`created_by`),
  ADD KEY `fk_sm_from_loc` (`from_location_id`),
  ADD KEY `fk_sm_to_loc` (`to_location_id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_name` (`name`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_preferred` (`preferred`),
  ADD KEY `idx_rating` (`rating`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `update_history`
--
ALTER TABLE `update_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_users_role` (`role_id`);

--
-- Indexes for table `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD PRIMARY KEY (`user_id`,`permission_id`),
  ADD KEY `fk_up_perm` (`permission_id`);

--
-- Indexes for table `vouchers`
--
ALTER TABLE `vouchers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `voucher_no` (`voucher_no`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_date` (`voucher_date`),
  ADD KEY `idx_voucher_no` (`voucher_no`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `approvals`
--
ALTER TABLE `approvals`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=239;

--
-- AUTO_INCREMENT for table `b2b_sales_items`
--
ALTER TABLE `b2b_sales_items`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `bank_accounts`
--
ALTER TABLE `bank_accounts`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `bank_transactions`
--
ALTER TABLE `bank_transactions`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `brands`
--
ALTER TABLE `brands`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `contact_categories`
--
ALTER TABLE `contact_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `contact_category_map`
--
ALTER TABLE `contact_category_map`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `contact_tags`
--
ALTER TABLE `contact_tags`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `contact_tag_map`
--
ALTER TABLE `contact_tag_map`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `doc_sequences`
--
ALTER TABLE `doc_sequences`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_log`
--
ALTER TABLE `email_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `finance`
--
ALTER TABLE `finance`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `installments`
--
ALTER TABLE `installments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `installment_payments`
--
ALTER TABLE `installment_payments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `locations`
--
ALTER TABLE `locations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `message_attachments`
--
ALTER TABLE `message_attachments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `message_logs`
--
ALTER TABLE `message_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `message_reads`
--
ALTER TABLE `message_reads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `message_templates`
--
ALTER TABLE `message_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=86;

--
-- AUTO_INCREMENT for table `phone_scan_sessions`
--
ALTER TABLE `phone_scan_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `price_adjustments`
--
ALTER TABLE `price_adjustments`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `procurement_shopping_list`
--
ALTER TABLE `procurement_shopping_list`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `procurement_wanted_items`
--
ALTER TABLE `procurement_wanted_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `product_categories`
--
ALTER TABLE `product_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `reminders`
--
ALTER TABLE `reminders`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `return_items`
--
ALTER TABLE `return_items`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;

--
-- AUTO_INCREMENT for table `sale_items`
--
ALTER TABLE `sale_items`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

--
-- AUTO_INCREMENT for table `sale_payments`
--
ALTER TABLE `sale_payments`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `sale_returns`
--
ALTER TABLE `sale_returns`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=67;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=80;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `update_history`
--
ALTER TABLE `update_history`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `vouchers`
--
ALTER TABLE `vouchers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `b2b_sales_items`
--
ALTER TABLE `b2b_sales_items`
  ADD CONSTRAINT `fk_b2b_sales` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_b2b_sales_items_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_b2b_sales_items_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_b2b_sales_items_supplier_id` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_b2b_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `bank_transactions`
--
ALTER TABLE `bank_transactions`
  ADD CONSTRAINT `fk_bank_transactions_account` FOREIGN KEY (`account_id`) REFERENCES `bank_accounts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `contact_category_map`
--
ALTER TABLE `contact_category_map`
  ADD CONSTRAINT `contact_category_map_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `contact_categories` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `contact_tag_map`
--
ALTER TABLE `contact_tag_map`
  ADD CONSTRAINT `contact_tag_map_ibfk_1` FOREIGN KEY (`tag_id`) REFERENCES `contact_tags` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `finance`
--
ALTER TABLE `finance`
  ADD CONSTRAINT `fk_finance_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `installments`
--
ALTER TABLE `installments`
  ADD CONSTRAINT `fk_installments_contact` FOREIGN KEY (`contact_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_installments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `installment_payments`
--
ALTER TABLE `installment_payments`
  ADD CONSTRAINT `fk_ip_installment` FOREIGN KEY (`installment_id`) REFERENCES `installments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `message_attachments`
--
ALTER TABLE `message_attachments`
  ADD CONSTRAINT `fk_attachment_message` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `message_logs`
--
ALTER TABLE `message_logs`
  ADD CONSTRAINT `message_logs_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `message_reads`
--
ALTER TABLE `message_reads`
  ADD CONSTRAINT `fk_read_message` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_read_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `message_templates`
--
ALTER TABLE `message_templates`
  ADD CONSTRAINT `message_templates_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `price_adjustments`
--
ALTER TABLE `price_adjustments`
  ADD CONSTRAINT `fk_pa_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pa_user` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `procurement_shopping_list`
--
ALTER TABLE `procurement_shopping_list`
  ADD CONSTRAINT `procurement_shopping_list_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `procurement_shopping_list_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `procurement_shopping_list_ibfk_3` FOREIGN KEY (`supplier_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `procurement_shopping_list_b2b_map`
--
ALTER TABLE `procurement_shopping_list_b2b_map`
  ADD CONSTRAINT `fk_map_b2b` FOREIGN KEY (`b2b_id`) REFERENCES `b2b_sales_items` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_map_procurement` FOREIGN KEY (`procurement_id`) REFERENCES `procurement_shopping_list` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `procurement_wanted_items`
--
ALTER TABLE `procurement_wanted_items`
  ADD CONSTRAINT `procurement_wanted_items_ibfk_1` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `procurement_wanted_items_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `product_categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`default_location_id`) REFERENCES `locations` (`id`);

--
-- Constraints for table `return_items`
--
ALTER TABLE `return_items`
  ADD CONSTRAINT `fk_return_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_return_items_return` FOREIGN KEY (`return_id`) REFERENCES `sale_returns` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `fk_rp_perm` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sales`
--
ALTER TABLE `sales`
  ADD CONSTRAINT `fk_sales_location` FOREIGN KEY (`selling_location_id`) REFERENCES `locations` (`id`);

--
-- Constraints for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD CONSTRAINT `fk_sale_items_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sale_payments`
--
ALTER TABLE `sale_payments`
  ADD CONSTRAINT `fk_sale_payments_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sale_returns`
--
ALTER TABLE `sale_returns`
  ADD CONSTRAINT `fk_sale_returns_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sale_returns_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `sessions`
--
ALTER TABLE `sessions`
  ADD CONSTRAINT `fk_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `stock_by_location`
--
ALTER TABLE `stock_by_location`
  ADD CONSTRAINT `fk_sbl_location` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sbl_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD CONSTRAINT `fk_sm_from_loc` FOREIGN KEY (`from_location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_sm_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sm_to_loc` FOREIGN KEY (`to_location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_sm_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);

--
-- Constraints for table `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD CONSTRAINT `fk_up_perm` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_up_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
