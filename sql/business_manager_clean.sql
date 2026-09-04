-- Business Manager clean import SQL
-- Import this into an already-created database from phpMyAdmin/cPanel.
-- No old sales, stock, audit, or session data is included.
-- Demo logins seeded at bottom:
--   admin / Admin@123
--   cashier1 / Cashier@123
--   accountant1 / Accountant1@123

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `procurement_shopping_list_b2b_map`;
DROP TABLE IF EXISTS `b2b_sales_items`;
DROP TABLE IF EXISTS `return_items`;
DROP TABLE IF EXISTS `sale_returns`;
DROP TABLE IF EXISTS `sale_payments`;
DROP TABLE IF EXISTS `sale_items`;
DROP TABLE IF EXISTS `sales`;
DROP TABLE IF EXISTS `installment_payments`;
DROP TABLE IF EXISTS `installments`;
DROP TABLE IF EXISTS `stock_movements`;
DROP TABLE IF EXISTS `stock_by_location`;
DROP TABLE IF EXISTS `price_adjustments`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `product_categories`;
DROP TABLE IF EXISTS `brands`;
DROP TABLE IF EXISTS `message_attachments`;
DROP TABLE IF EXISTS `message_reads`;
DROP TABLE IF EXISTS `message_logs`;
DROP TABLE IF EXISTS `message_templates`;
DROP TABLE IF EXISTS `bank_transactions`;
DROP TABLE IF EXISTS `bank_accounts`;
DROP TABLE IF EXISTS `vouchers`;
DROP TABLE IF EXISTS `finance`;
DROP TABLE IF EXISTS `email_log`;
DROP TABLE IF EXISTS `doc_sequences`;
DROP TABLE IF EXISTS `procurement_shopping_list`;
DROP TABLE IF EXISTS `procurement_wanted_items`;
DROP TABLE IF EXISTS `contact_category_map`;
DROP TABLE IF EXISTS `contact_tag_map`;
DROP TABLE IF EXISTS `contact_categories`;
DROP TABLE IF EXISTS `contact_tags`;
DROP TABLE IF EXISTS `contacts`;
DROP TABLE IF EXISTS `customers`;
DROP TABLE IF EXISTS `suppliers`;
DROP TABLE IF EXISTS `staff`;
DROP TABLE IF EXISTS `approvals`;
DROP TABLE IF EXISTS `reminders`;
DROP TABLE IF EXISTS `update_history`;
DROP TABLE IF EXISTS `phone_scan_sessions`;
DROP TABLE IF EXISTS `settings`;
DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `sessions`;
DROP TABLE IF EXISTS `password_resets`;
DROP TABLE IF EXISTS `user_permissions`;
DROP TABLE IF EXISTS `role_permissions`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `permissions`;
DROP TABLE IF EXISTS `roles`;

CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `perm_key` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_super_admin_only` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_permissions_key` (`perm_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_role_id` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `role_permissions` (
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `idx_rp_permission` (`permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_permissions` (
  `user_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  `is_allowed` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`user_id`,`permission_id`),
  KEY `idx_up_permission` (`permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sessions` (
  `id` varchar(128) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `data` mediumblob NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `last_activity` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sessions_user_id` (`user_id`),
  KEY `idx_sessions_last_activity` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(120) NOT NULL,
  `token` varchar(128) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_password_resets_token` (`token`),
  KEY `idx_password_resets_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `audit_logs` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity` varchar(80) DEFAULT NULL,
  `entity_id` varchar(80) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_audit_user_id` (`user_id`),
  KEY `idx_audit_action` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `key` varchar(100) NOT NULL,
  `group` varchar(60) NOT NULL DEFAULT 'General',
  `type` varchar(20) NOT NULL DEFAULT 'text',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `value` text DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_settings_key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `locations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `low_alert_qty` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `low_alert_type` varchar(20) NOT NULL DEFAULT 'pieces',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_locations_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_customers_active` (`is_active`),
  KEY `idx_customers_category` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `rating` decimal(3,1) DEFAULT 0.0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_suppliers_status` (`status`),
  KEY `idx_suppliers_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `staff` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_staff_employee_id` (`employee_id`),
  KEY `idx_staff_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `contacts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(120) NOT NULL,
  `last_name` varchar(120) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `company` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `type` enum('individual','business','lead') NOT NULL DEFAULT 'individual',
  `status` enum('active','inactive','prospect','archived') NOT NULL DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_contacts_type` (`type`),
  KEY `idx_contacts_status` (`status`),
  KEY `idx_contacts_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `contact_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `color` varchar(7) DEFAULT '#007bff',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_contact_categories_name` (`name`),
  KEY `idx_contact_categories_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `contact_tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `color` varchar(7) DEFAULT '#28a745',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_contact_tags_name` (`name`),
  KEY `idx_contact_tags_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `contact_category_map` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `contact_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_contact_category_map` (`contact_id`,`category_id`),
  KEY `idx_ccm_category` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `contact_tag_map` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `contact_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_contact_tag_map` (`contact_id`,`tag_id`),
  KEY `idx_ctm_tag` (`tag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `product_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_product_categories_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `brands` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `slug` varchar(160) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_brands_slug` (`slug`),
  KEY `idx_brand_name` (`name`),
  KEY `idx_brand_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `images` longtext DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_products_sku` (`sku`),
  KEY `idx_products_category` (`category_id`),
  KEY `idx_products_brand` (`brand_id`),
  KEY `idx_products_active` (`is_active`),
  KEY `idx_products_default_location` (`default_location_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `stock_by_location` (
  `product_id` int(11) NOT NULL,
  `location_id` int(11) NOT NULL,
  `qty_base` decimal(12,2) NOT NULL DEFAULT 0.00,
  `low_level_base` decimal(12,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`product_id`,`location_id`),
  KEY `idx_sbl_location` (`location_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `stock_movements` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sm_product` (`product_id`),
  KEY `idx_sm_from_location` (`from_location_id`),
  KEY `idx_sm_to_location` (`to_location_id`),
  KEY `idx_sm_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `price_adjustments` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `old_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `new_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `old_wholesale` decimal(12,2) NOT NULL DEFAULT 0.00,
  `new_wholesale` decimal(12,2) NOT NULL DEFAULT 0.00,
  `old_retail` decimal(12,2) NOT NULL DEFAULT 0.00,
  `new_retail` decimal(12,2) NOT NULL DEFAULT 0.00,
  `reason` varchar(255) DEFAULT NULL,
  `changed_by` int(11) DEFAULT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pa_product` (`product_id`),
  KEY `idx_pa_user` (`changed_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `phone_scan_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `session_id` varchar(100) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `status` enum('created','connected','scanning','found','uploaded','error') DEFAULT 'created',
  `image_url` text DEFAULT NULL,
  `message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_phone_scan_session` (`session_id`),
  KEY `idx_phone_scan_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sales` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
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
  `has_b2b` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sales_doc_no` (`doc_no`),
  KEY `idx_sales_location` (`selling_location_id`),
  KEY `idx_sales_customer` (`customer_id`),
  KEY `idx_sales_created_by` (`created_by`),
  KEY `idx_sales_status` (`status`),
  KEY `idx_sales_payment_status` (`payment_status`),
  KEY `idx_sales_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sale_items` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
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
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sale_items_sale` (`sale_id`),
  KEY `idx_sale_items_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sale_payments` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `sale_id` bigint(20) NOT NULL,
  `method` enum('cash','mobile_money','bank') NOT NULL,
  `provider` varchar(40) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `reference` varchar(80) DEFAULT NULL,
  `received_by` bigint(20) NOT NULL,
  `received_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sale_payments_sale` (`sale_id`),
  KEY `idx_sale_payments_received_by` (`received_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sale_returns` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `sale_id` bigint(20) NOT NULL,
  `return_no` varchar(50) NOT NULL,
  `reason` text NOT NULL,
  `refund_amount` decimal(12,2) DEFAULT 0.00,
  `status` enum('pending','approved','completed','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `selling_location_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) NOT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `refunded` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sale_returns_return_no` (`return_no`),
  KEY `idx_sale_returns_sale` (`sale_id`),
  KEY `idx_sale_returns_created_by` (`created_by`),
  KEY `idx_sale_returns_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `return_items` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `return_id` bigint(20) NOT NULL,
  `product_id` int(11) NOT NULL,
  `location_id` int(11) NOT NULL,
  `quantity` decimal(12,3) NOT NULL DEFAULT 0.000,
  `unit_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_return_items_return` (`return_id`),
  KEY `idx_return_items_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `b2b_sales_items` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
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
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_b2b_sale_id` (`sale_id`),
  KEY `idx_b2b_supplier` (`supplier_id`),
  KEY `idx_b2b_sku` (`sku`),
  KEY `idx_b2b_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `installments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `contact_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `amount_due` decimal(15,2) NOT NULL DEFAULT 0.00,
  `amount_paid` decimal(15,2) NOT NULL DEFAULT 0.00,
  `due_date` date DEFAULT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_installments_contact_id` (`contact_id`),
  KEY `idx_installments_status` (`status`),
  KEY `idx_installments_due_date` (`due_date`),
  KEY `idx_installments_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `installment_payments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `installment_id` bigint(20) unsigned NOT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `method` varchar(40) NOT NULL,
  `reference` varchar(120) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `payment_date` datetime NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ip_installment` (`installment_id`),
  KEY `idx_ip_payment_date` (`payment_date`),
  KEY `idx_ip_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `bank_accounts` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
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
  `created_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bank_account_number` (`account_number`),
  KEY `idx_bank_name` (`bank_name`),
  KEY `idx_bank_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `bank_transactions` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
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
  `created_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_bt_account` (`account_id`),
  KEY `idx_bt_date` (`transaction_date`),
  KEY `idx_bt_type` (`type`),
  KEY `idx_bt_reconciled` (`reconciled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `finance` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `type` enum('IN','OUT') NOT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `method` varchar(100) DEFAULT NULL,
  `reference` varchar(150) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_finance_user_id` (`user_id`),
  KEY `idx_finance_type` (`type`),
  KEY `idx_finance_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `vouchers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vouchers_no` (`voucher_no`),
  KEY `idx_vouchers_status` (`status`),
  KEY `idx_vouchers_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `doc_sequences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `doc_type` enum('receipt','invoice','delivery_note') NOT NULL,
  `year` smallint(6) NOT NULL,
  `prefix` varchar(10) NOT NULL,
  `current_no` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_doc_sequences` (`doc_type`,`year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `email_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `to_email` varchar(190) NOT NULL,
  `to_name` varchar(190) DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `body` mediumtext DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'queued',
  `provider` varchar(50) DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `meta_json` longtext DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `sent_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_email_log_status` (`status`),
  KEY `idx_email_log_created_at` (`created_at`),
  KEY `idx_email_log_to_email` (`to_email`),
  KEY `idx_email_log_sent_at` (`sent_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `message_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_message_logs_recipient` (`recipient_type`,`recipient_id`),
  KEY `idx_message_logs_status` (`status`),
  KEY `idx_message_logs_read` (`is_read`),
  KEY `idx_message_logs_scheduled` (`scheduled_at`),
  KEY `idx_message_logs_sender` (`sender_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `message_attachments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `message_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_message_attachments_message` (`message_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `message_reads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `message_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_message_reads_user` (`message_id`,`user_id`),
  KEY `idx_message_reads_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `message_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `category` varchar(100) DEFAULT 'general',
  `variables` longtext DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_message_templates_category` (`category`),
  KEY `idx_message_templates_active` (`is_active`),
  KEY `idx_message_templates_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `approvals` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `approval_type` varchar(80) NOT NULL,
  `reference_table` varchar(80) DEFAULT NULL,
  `reference_id` varchar(80) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `requested_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_approvals_status` (`status`),
  KEY `idx_approvals_type` (`approval_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `reminders` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `title` varchar(150) NOT NULL,
  `remind_at` datetime NOT NULL,
  `channel` varchar(20) NOT NULL DEFAULT 'email',
  `target` varchar(150) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_reminders_status` (`status`),
  KEY `idx_reminders_remind_at` (`remind_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `update_history` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `version_from` varchar(40) DEFAULT NULL,
  `version_to` varchar(40) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'success',
  `notes` longtext DEFAULT NULL,
  `applied_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_update_history_status` (`status`),
  KEY `idx_update_history_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `procurement_shopping_list` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_psl_product` (`product_id`),
  KEY `idx_psl_supplier` (`supplier_id`),
  KEY `idx_psl_user` (`user_id`),
  KEY `idx_psl_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `procurement_wanted_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pwi_requested_by` (`requested_by`),
  KEY `idx_pwi_approved_by` (`approved_by`),
  KEY `idx_pwi_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `procurement_shopping_list_b2b_map` (
  `b2b_id` bigint(20) NOT NULL,
  `procurement_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`b2b_id`,`procurement_id`),
  KEY `idx_pslb_procurement` (`procurement_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `roles` (`id`, `name`, `description`) VALUES
(1, 'super_admin', 'Full access'),
(2, 'cashier', 'POS and limited operations'),
(3, 'accountant', 'Finance and reports');

INSERT INTO `permissions` (`id`, `perm_key`, `description`, `is_super_admin_only`) VALUES
(1, 'dashboard.view', 'View dashboard', 0),
(2, 'pos.use', 'Use POS', 0),
(3, 'sales.view', 'View sales history', 0),
(4, 'sales.returns', 'Process returns', 0),
(5, 'products.view', 'View products', 0),
(6, 'products.create', 'Create products', 0),
(7, 'products.update', 'Update products, stock, and pricing', 0),
(8, 'products.delete', 'Delete products', 0),
(9, 'contacts.view', 'View contacts', 0),
(10, 'contacts.create', 'Create contacts', 0),
(11, 'contacts.update', 'Update contacts', 0),
(12, 'contacts.delete', 'Delete contacts', 0),
(13, 'finance.view', 'View finance', 0),
(14, 'finance.create', 'Create finance records', 0),
(15, 'finance.update', 'Update finance records', 0),
(16, 'finance.delete', 'Delete finance records', 0),
(17, 'reports.view', 'View reports', 0),
(18, 'admin.access', 'Access admin section', 0),
(19, 'admin.users', 'Manage users', 1),
(20, 'admin.rbac', 'Manage roles and permissions', 1),
(21, 'admin.settings', 'Manage system settings', 1),
(22, 'admin.updates', 'Apply updates', 1),
(23, 'audit.view', 'View audit trail', 0),
(24, 'reports.b2b.view', 'View B2B report', 0),
(25, 'shopping_list.create', 'Add items to shopping list', 0),
(26, 'pos.create', 'Create POS sales', 0),
(27, 'pos.view', 'View POS records', 0),
(28, 'pos.void', 'Void or return sales', 0),
(29, 'sales.create', 'Create sales', 0),
(30, 'documents.view', 'View documents', 0),
(31, 'installments.view', 'View installments', 0),
(32, 'installments.create', 'Create installments', 0),
(33, 'installments.edit', 'Edit installments', 0),
(34, 'installments.delete', 'Delete installments', 0),
(35, 'installments.update', 'Update installments', 0),
(36, 'brands.view', 'View brands', 0),
(37, 'brands.create', 'Create brands', 0),
(38, 'brands.edit', 'Edit brands', 0),
(39, 'brands.delete', 'Delete brands', 0),
(40, 'stores.manage', 'Manage stores', 0),
(41, 'stores.update', 'Update stores', 0),
(42, 'stores.delete', 'Delete stores', 0),
(43, 'procurement.view', 'View procurement', 0),
(44, 'messaging.view', 'Use messaging', 0),
(45, 'audit.manage', 'Manage audit logs', 1),
(46, 'reminders.view', 'View reminders', 0),
(47, 'approvals.view', 'View approvals', 0),
(48, 'users.view', 'View users', 1),
(49, 'roles.view', 'View roles', 1),
(50, 'permissions.manage', 'Manage permissions', 1),
(51, 'settings.manage', 'Manage themes and UI settings', 1),
(52, 'payments.manage', 'Manage payment settings', 1),
(53, 'updates.manage', 'Manage updates', 1),
(54, 'updates.view', 'View update history', 1),
(55, 'admin.exclusive', 'Access super-admin-only administration', 1),
(56, 'reports.sales.view', 'View sales report', 0),
(57, 'reports.profit.view', 'View profit report', 0),
(58, 'reports.inventory.view', 'View inventory report', 0),
(59, 'reports.installments.view', 'View installments report', 0),
(60, 'reports.expenses.view', 'View expenses report', 0),
(61, 'reports.capital.view', 'View capital report', 0),
(62, 'reports.audit.view', 'View audit report', 0),
(63, 'stores.view', 'View stores', 0),
(64, 'admin.create', 'Create administration records', 1),
(65, 'admin.update', 'Update administration records', 1),
(66, 'admin.delete', 'Delete administration records', 1),
(67, 'permissions.update', 'Update permissions', 1),
(68, 'pos.apply_discount', 'Apply POS discounts', 0),
(69, 'pos.edit_price', 'Edit POS item prices', 0),
(70, 'pos.invoice', 'Create invoices from POS', 0),
(71, 'pos.delivery_note', 'Create delivery notes from POS', 0),
(72, 'pos.allow_debt', 'Allow debt sales', 0),
(73, 'pos.manage', 'Manage POS operations', 0),
(74, 'procurement.create', 'Create procurement records', 0),
(75, 'procurement.update', 'Update procurement records', 0),
(76, 'procurement.delete', 'Delete procurement records', 0);

INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, `id` FROM `permissions`;

INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 2, `id` FROM `permissions` WHERE `perm_key` IN (
  'dashboard.view','pos.use','pos.view','pos.create','sales.view','sales.create',
  'products.view','contacts.view','contacts.create','documents.view',
  'reports.sales.view','reports.b2b.view','shopping_list.create'
);

INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 3, `id` FROM `permissions` WHERE `perm_key` IN (
  'dashboard.view','finance.view','finance.create','finance.update','sales.view',
  'reports.sales.view','reports.profit.view','reports.inventory.view',
  'reports.installments.view','reports.expenses.view','reports.capital.view',
  'reports.b2b.view','reports.audit.view','audit.view','messaging.view'
);

INSERT INTO `users` (`id`, `role_id`, `username`, `email`, `phone`, `full_name`, `password_hash`, `is_active`) VALUES
(1, 1, 'admin', 'admin@example.com', NULL, 'Admin', '$2y$12$i7YEOrcTXm65Shf2yJ79u.OKVsMQme75sjkYAGeIcJ7n3qKmUSieG', 1),
(2, 2, 'cashier1', 'cashier1@example.com', NULL, 'Cashier', '$2y$12$zum5p2y2nvvgwsc2VwOszeigqz6HZhb1mR3JyfJRzEJ5wQrg7zSUO', 1),
(3, 3, 'accountant1', 'accountant1@example.com', NULL, 'Accountant', '$2y$12$2GkAWln1zRCNWmtgjbsBXOsgK1YH0KslriGjvmq01r69Lt7ktfILC', 1);

INSERT INTO `locations` (`id`, `name`, `is_active`, `low_alert_qty`, `low_alert_type`) VALUES
(1, 'Main Store', 1, 0.0000, 'pieces'),
(2, 'Counter', 1, 0.0000, 'pieces'),
(3, 'Store Room', 1, 0.0000, 'pieces');

INSERT INTO `doc_sequences` (`doc_type`, `year`, `prefix`, `current_no`) VALUES
('receipt', YEAR(CURDATE()), 'RC', 0),
('invoice', YEAR(CURDATE()), 'INV', 0),
('delivery_note', YEAR(CURDATE()), 'DN', 0);

INSERT INTO `settings` (`key`, `group`, `type`, `sort_order`, `value`, `description`) VALUES
('business_name', 'General', 'text', 10, 'Business Manager Pro', 'Business name for receipts and invoices'),
('business_address', 'General', 'text', 20, '', 'Business address'),
('business_phone', 'General', 'text', 30, '', 'Business phone number'),
('business_email', 'General', 'text', 40, '', 'Business email address'),
('business_website', 'General', 'text', 50, '', 'Business website'),
('business_tax_id', 'General', 'text', 60, '', 'Business tax ID'),
('business_logo', 'General', 'text', 70, '', 'Business logo URL or path'),
('currency_symbol', 'Business', 'text', 80, 'UGX ', 'Currency symbol for display'),
('currency_code', 'Business', 'text', 90, 'UGX', 'Currency code'),
('decimal_places', 'Business', 'text', 100, '0', 'Number of decimal places for currency'),
('thousands_separator', 'Business', 'text', 110, ',', 'Thousands separator for numbers'),
('decimal_point', 'Business', 'text', 120, '.', 'Decimal point for numbers'),
('receipt_header', 'Receipts', 'text', 130, 'THANK YOU FOR SHOPPING WITH US', 'Receipt header text'),
('receipt_footer', 'Receipts', 'text', 140, 'Thank you for your purchase!', 'Receipt footer text'),
('receipt_width', 'Printing', 'text', 150, '80', 'Receipt width in mm'),
('printer_name', 'Printing', 'text', 160, 'Default Printer', 'Default receipt printer name'),
('auto_print_receipt', 'Printing', 'bool', 170, '1', 'Auto print receipt after sale'),
('auto_open_drawer', 'Printing', 'bool', 180, '0', 'Auto open cash drawer after sale'),
('show_customer_copy', 'Printing', 'bool', 190, '1', 'Show customer copy option'),
('show_cashier_copy', 'Printing', 'bool', 200, '1', 'Show cashier copy option'),
('app_theme', 'General', 'text', 210, 'default', 'Active UI theme');

INSERT INTO `message_templates` (`name`, `subject`, `message`, `category`, `is_active`, `created_by`) VALUES
('Welcome Message', 'Welcome to our system', 'Hello {username},\n\nWelcome to our business management system.\n\nBest regards,\nThe Team', 'welcome', 1, 1);

SET FOREIGN_KEY_CHECKS = 1;
