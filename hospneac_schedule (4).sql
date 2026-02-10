-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Feb 10, 2026 at 04:08 AM
-- Server version: 11.4.9-MariaDB-cll-lve
-- PHP Version: 8.3.29

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `hospneac_schedule`
--

-- --------------------------------------------------------

--
-- Table structure for table `aloha_employees_stage`
--

CREATE TABLE `aloha_employees_stage` (
  `id` int(11) NOT NULL,
  `restaurant_id` int(11) NOT NULL,
  `batch_id` int(11) NOT NULL,
  `external_employee_id` varchar(100) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `display_name` varchar(150) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `raw_json` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `aloha_import_batches`
--

CREATE TABLE `aloha_import_batches` (
  `id` int(11) NOT NULL,
  `restaurant_id` int(11) NOT NULL,
  `provider` varchar(50) NOT NULL DEFAULT 'aloha',
  `import_type` enum('employees','labor','sales') NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `status` enum('uploaded','mapped','processed','failed') NOT NULL DEFAULT 'uploaded',
  `mapping_json` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `processed_at` datetime DEFAULT NULL,
  `error_text` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `aloha_labor_punches_stage`
--

CREATE TABLE `aloha_labor_punches_stage` (
  `id` int(11) NOT NULL,
  `restaurant_id` int(11) NOT NULL,
  `batch_id` int(11) NOT NULL,
  `external_employee_id` varchar(100) NOT NULL,
  `punch_in_dt` datetime NOT NULL,
  `punch_out_dt` datetime DEFAULT NULL,
  `job_code` varchar(100) DEFAULT NULL,
  `location_code` varchar(100) DEFAULT NULL,
  `raw_json` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `aloha_sales_daily_stage`
--

CREATE TABLE `aloha_sales_daily_stage` (
  `id` int(11) NOT NULL,
  `restaurant_id` int(11) NOT NULL,
  `batch_id` int(11) NOT NULL,
  `business_date` date NOT NULL,
  `gross_sales` decimal(12,2) NOT NULL,
  `net_sales` decimal(12,2) DEFAULT NULL,
  `orders_count` int(11) DEFAULT NULL,
  `raw_json` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `restaurant_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `body` text NOT NULL,
  `audience` varchar(100) NOT NULL,
  `starts_at` datetime NOT NULL DEFAULT current_timestamp(),
  `ends_at` datetime DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `availability`
--

CREATE TABLE `availability` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `restaurant_id` int(11) NOT NULL,
  `day_of_week` varchar(10) NOT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `is_unavailable` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `callouts`
--

CREATE TABLE `callouts` (
  `id` int(11) NOT NULL,
  `restaurant_id` int(11) NOT NULL,
  `shift_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('reported','coverage_requested','covered','manager_closed') NOT NULL DEFAULT 'reported',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `invites`
--

CREATE TABLE `invites` (
  `id` int(11) NOT NULL,
  `restaurant_id` int(11) NOT NULL,
  `email` varchar(190) NOT NULL,
  `role` enum('manager','team') NOT NULL DEFAULT 'team',
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `accepted_at` datetime DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_locks`
--

CREATE TABLE `job_locks` (
  `lock_key` varchar(128) NOT NULL,
  `locked_at` datetime NOT NULL,
  `expires_at` datetime NOT NULL,
  `owner` varchar(64) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_logs`
--

CREATE TABLE `job_logs` (
  `id` int(11) NOT NULL,
  `job_id` int(11) NOT NULL,
  `log_level` enum('info','warn','error') NOT NULL DEFAULT 'info',
  `message` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_queue`
--

CREATE TABLE `job_queue` (
  `id` int(11) NOT NULL,
  `restaurant_id` int(11) DEFAULT NULL,
  `job_type` varchar(64) NOT NULL,
  `payload_json` text NOT NULL,
  `status` enum('queued','running','succeeded','failed','cancelled') NOT NULL DEFAULT 'queued',
  `priority` int(11) NOT NULL DEFAULT 100,
  `run_after` datetime NOT NULL DEFAULT current_timestamp(),
  `attempts` int(11) NOT NULL DEFAULT 0,
  `max_attempts` int(11) NOT NULL DEFAULT 5,
  `last_error` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `started_at` datetime DEFAULT NULL,
  `finished_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `restaurant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` varchar(64) NOT NULL,
  `title` varchar(140) NOT NULL,
  `body` text NOT NULL,
  `link_url` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `request_ip` varchar(64) DEFAULT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pos_connections`
--

CREATE TABLE `pos_connections` (
  `id` int(11) NOT NULL,
  `restaurant_id` int(11) NOT NULL,
  `provider` varchar(50) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `credentials_json` text NOT NULL,
  `last_sync_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pos_mappings`
--

CREATE TABLE `pos_mappings` (
  `id` int(11) NOT NULL,
  `restaurant_id` int(11) NOT NULL,
  `provider` varchar(50) NOT NULL,
  `external_id` varchar(100) NOT NULL,
  `internal_id` varchar(100) NOT NULL,
  `type` varchar(50) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `restaurants`
--

CREATE TABLE `restaurants` (
  `id` int(11) NOT NULL,
  `name` varchar(140) NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL,
  `manager_code` varchar(10) DEFAULT NULL,
  `staff_code` varchar(10) DEFAULT NULL,
  `subscription_status` varchar(20) DEFAULT 'trial',
  `stripe_subscription_id` varchar(100) DEFAULT NULL,
  `subscription_end_date` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `restaurant_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `color` varchar(20) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `schedule_enforcement_events`
--

CREATE TABLE `schedule_enforcement_events` (
  `id` int(11) NOT NULL,
  `restaurant_id` int(11) NOT NULL,
  `event_type` varchar(64) NOT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `shift_id` int(11) DEFAULT NULL,
  `external_employee_id` varchar(64) DEFAULT NULL,
  `event_dt` datetime NOT NULL,
  `message` varchar(255) NOT NULL,
  `details_json` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `schedule_policies`
--

CREATE TABLE `schedule_policies` (
  `id` int(11) NOT NULL,
  `restaurant_id` int(11) NOT NULL,
  `policy_set_id` int(11) NOT NULL,
  `policy_key` varchar(64) NOT NULL,
  `enabled` tinyint(4) NOT NULL DEFAULT 1,
  `mode` enum('warn','block') NOT NULL DEFAULT 'warn',
  `params_json` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `schedule_policy_sets`
--

CREATE TABLE `schedule_policy_sets` (
  `id` int(11) NOT NULL,
  `restaurant_id` int(11) NOT NULL,
  `name` varchar(80) NOT NULL,
  `is_active` tinyint(4) NOT NULL DEFAULT 1,
  `is_default` tinyint(4) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `schedule_quality`
--

CREATE TABLE `schedule_quality` (
  `id` int(11) NOT NULL,
  `restaurant_id` int(11) NOT NULL,
  `week_start_date` date NOT NULL,
  `score` int(11) NOT NULL,
  `reasons_json` text NOT NULL,
  `generated_at` datetime NOT NULL DEFAULT current_timestamp(),
  `generated_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `schedule_settings`
--

CREATE TABLE `schedule_settings` (
  `id` int(11) NOT NULL,
  `restaurant_id` int(11) NOT NULL,
  `timezone` varchar(64) NOT NULL DEFAULT 'America/New_York',
  `demo_mode` tinyint(1) NOT NULL DEFAULT 0,
  `last_worker_run_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `schedule_violations`
--

CREATE TABLE `schedule_violations` (
  `id` int(11) NOT NULL,
  `restaurant_id` int(11) NOT NULL,
  `week_start_date` date NOT NULL,
  `shift_id` int(11) DEFAULT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `policy_key` varchar(64) NOT NULL,
  `severity` enum('info','warn','block') NOT NULL,
  `message` varchar(255) NOT NULL,
  `details_json` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `shifts`
--

CREATE TABLE `shifts` (
  `id` int(11) NOT NULL,
  `restaurant_id` int(11) NOT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `role_id` int(11) DEFAULT NULL,
  `start_dt` datetime NOT NULL,
  `end_dt` datetime NOT NULL,
  `break_minutes` int(11) NOT NULL DEFAULT 0,
  `notes` text DEFAULT NULL,
  `status` enum('draft','published','deleted') NOT NULL DEFAULT 'draft',
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `shift_pickup_requests`
--

CREATE TABLE `shift_pickup_requests` (
  `id` int(11) NOT NULL,
  `restaurant_id` int(11) NOT NULL,
  `shift_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `shift_trade_requests`
--

CREATE TABLE `shift_trade_requests` (
  `id` int(11) NOT NULL,
  `restaurant_id` int(11) NOT NULL,
  `shift_id` int(11) NOT NULL,
  `from_staff_id` int(11) NOT NULL,
  `to_staff_id` int(11) DEFAULT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_availability`
--

CREATE TABLE `staff_availability` (
  `id` int(11) NOT NULL,
  `restaurant_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `day_of_week` tinyint(4) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `status` varchar(20) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_labor_profile`
--

CREATE TABLE `staff_labor_profile` (
  `id` int(11) NOT NULL,
  `restaurant_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `is_minor` tinyint(4) NOT NULL DEFAULT 0,
  `birthdate` date DEFAULT NULL,
  `max_weekly_hours_override` decimal(5,2) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_pay_rates`
--

CREATE TABLE `staff_pay_rates` (
  `id` int(11) NOT NULL,
  `restaurant_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `role_id` int(11) DEFAULT NULL,
  `hourly_rate` decimal(10,2) NOT NULL,
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_skills`
--

CREATE TABLE `staff_skills` (
  `id` int(11) NOT NULL,
  `restaurant_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `skill_key` varchar(100) NOT NULL,
  `level` tinyint(4) NOT NULL DEFAULT 1,
  `expires_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `time_off_requests`
--

CREATE TABLE `time_off_requests` (
  `id` int(11) NOT NULL,
  `restaurant_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `start_dt` datetime NOT NULL,
  `end_dt` datetime NOT NULL,
  `reason` text NOT NULL,
  `status` enum('pending','approved','denied','cancelled') NOT NULL DEFAULT 'pending',
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `email` varchar(190) NOT NULL,
  `name` varchar(120) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `email_verified` tinyint(1) NOT NULL DEFAULT 0,
  `last_login_at` datetime DEFAULT NULL,
  `failed_logins` int(11) NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_restaurants`
--

CREATE TABLE `user_restaurants` (
  `id` int(11) NOT NULL,
  `restaurant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` enum('manager','team') NOT NULL DEFAULT 'team',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `aloha_employees_stage`
--
ALTER TABLE `aloha_employees_stage`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_aloha_employees_batch` (`restaurant_id`,`batch_id`),
  ADD KEY `idx_aloha_employees_external` (`restaurant_id`,`external_employee_id`);

--
-- Indexes for table `aloha_import_batches`
--
ALTER TABLE `aloha_import_batches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_aloha_batches_restaurant` (`restaurant_id`),
  ADD KEY `idx_aloha_batches_restaurant_type` (`restaurant_id`,`import_type`);

--
-- Indexes for table `aloha_labor_punches_stage`
--
ALTER TABLE `aloha_labor_punches_stage`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_aloha_labor_batch` (`restaurant_id`,`batch_id`),
  ADD KEY `idx_aloha_labor_employee` (`restaurant_id`,`external_employee_id`,`punch_in_dt`);

--
-- Indexes for table `aloha_sales_daily_stage`
--
ALTER TABLE `aloha_sales_daily_stage`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_aloha_sales_day` (`restaurant_id`,`business_date`),
  ADD KEY `idx_aloha_sales_day` (`restaurant_id`,`business_date`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_announcements_restaurant` (`restaurant_id`),
  ADD KEY `idx_announcements_restaurant_starts` (`restaurant_id`,`starts_at`);

--
-- Indexes for table `availability`
--
ALTER TABLE `availability`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`,`restaurant_id`);

--
-- Indexes for table `callouts`
--
ALTER TABLE `callouts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_callouts_restaurant` (`restaurant_id`),
  ADD KEY `idx_callouts_restaurant_shift` (`restaurant_id`,`shift_id`),
  ADD KEY `idx_callouts_restaurant_status` (`restaurant_id`,`status`);

--
-- Indexes for table `invites`
--
ALTER TABLE `invites`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_invite_email` (`restaurant_id`,`email`,`accepted_at`),
  ADD KEY `restaurant_id` (`restaurant_id`),
  ADD KEY `email` (`email`),
  ADD KEY `token_hash` (`token_hash`),
  ADD KEY `fk_invites_creator` (`created_by`);

--
-- Indexes for table `job_locks`
--
ALTER TABLE `job_locks`
  ADD PRIMARY KEY (`lock_key`);

--
-- Indexes for table `job_logs`
--
ALTER TABLE `job_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_job_logs_job_created` (`job_id`,`created_at`);

--
-- Indexes for table `job_queue`
--
ALTER TABLE `job_queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_job_queue_status_run` (`status`,`run_after`,`priority`),
  ADD KEY `idx_job_queue_restaurant_status` (`restaurant_id`,`status`,`run_after`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notifications_restaurant_user` (`restaurant_id`,`user_id`,`is_read`,`created_at`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `expires_at` (`expires_at`),
  ADD KEY `token_hash` (`token_hash`);

--
-- Indexes for table `pos_connections`
--
ALTER TABLE `pos_connections`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_pos_connections_restaurant_provider` (`restaurant_id`,`provider`),
  ADD KEY `idx_pos_connections_restaurant` (`restaurant_id`);

--
-- Indexes for table `pos_mappings`
--
ALTER TABLE `pos_mappings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_pos_mappings_restaurant_provider_type_external` (`restaurant_id`,`provider`,`type`,`external_id`),
  ADD KEY `idx_pos_mappings_restaurant` (`restaurant_id`);

--
-- Indexes for table `restaurants`
--
ALTER TABLE `restaurants`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_restaurants_created_by` (`created_by`),
  ADD KEY `idx_manager_code` (`manager_code`),
  ADD KEY `idx_staff_code` (`staff_code`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_roles_restaurant_name` (`restaurant_id`,`name`),
  ADD KEY `idx_roles_restaurant` (`restaurant_id`);

--
-- Indexes for table `schedule_enforcement_events`
--
ALTER TABLE `schedule_enforcement_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_schedule_enforcement_type_dt` (`restaurant_id`,`event_type`,`event_dt`),
  ADD KEY `idx_schedule_enforcement_staff_dt` (`restaurant_id`,`staff_id`,`event_dt`);

--
-- Indexes for table `schedule_policies`
--
ALTER TABLE `schedule_policies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_schedule_policies_restaurant_set` (`restaurant_id`,`policy_set_id`);

--
-- Indexes for table `schedule_policy_sets`
--
ALTER TABLE `schedule_policy_sets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_schedule_policy_sets_restaurant_name` (`restaurant_id`,`name`);

--
-- Indexes for table `schedule_quality`
--
ALTER TABLE `schedule_quality`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_schedule_quality_restaurant_week` (`restaurant_id`,`week_start_date`),
  ADD KEY `idx_schedule_quality_restaurant` (`restaurant_id`);

--
-- Indexes for table `schedule_settings`
--
ALTER TABLE `schedule_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `restaurant_id` (`restaurant_id`);

--
-- Indexes for table `schedule_violations`
--
ALTER TABLE `schedule_violations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_schedule_violations_week` (`restaurant_id`,`week_start_date`),
  ADD KEY `idx_schedule_violations_staff_week` (`restaurant_id`,`staff_id`,`week_start_date`),
  ADD KEY `idx_schedule_violations_policy_week` (`restaurant_id`,`policy_key`,`week_start_date`);

--
-- Indexes for table `shifts`
--
ALTER TABLE `shifts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_shifts_restaurant_start` (`restaurant_id`,`start_dt`),
  ADD KEY `idx_shifts_restaurant_staff_start` (`restaurant_id`,`staff_id`,`start_dt`);

--
-- Indexes for table `shift_pickup_requests`
--
ALTER TABLE `shift_pickup_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_shift_pickup_requests_restaurant` (`restaurant_id`),
  ADD KEY `idx_shift_pickup_requests_restaurant_shift` (`restaurant_id`,`shift_id`),
  ADD KEY `idx_shift_pickup_requests_restaurant_staff` (`restaurant_id`,`staff_id`);

--
-- Indexes for table `shift_trade_requests`
--
ALTER TABLE `shift_trade_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_shift_trade_requests_restaurant` (`restaurant_id`),
  ADD KEY `idx_shift_trade_requests_restaurant_shift` (`restaurant_id`,`shift_id`),
  ADD KEY `idx_shift_trade_requests_restaurant_status` (`restaurant_id`,`status`);

--
-- Indexes for table `staff_availability`
--
ALTER TABLE `staff_availability`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_staff_availability_restaurant` (`restaurant_id`),
  ADD KEY `idx_staff_availability_restaurant_staff_day` (`restaurant_id`,`staff_id`,`day_of_week`);

--
-- Indexes for table `staff_labor_profile`
--
ALTER TABLE `staff_labor_profile`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_staff_labor_profile_restaurant_staff` (`restaurant_id`,`staff_id`),
  ADD KEY `idx_staff_labor_profile_staff` (`restaurant_id`,`staff_id`);

--
-- Indexes for table `staff_pay_rates`
--
ALTER TABLE `staff_pay_rates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_staff_pay_rates_restaurant` (`restaurant_id`),
  ADD KEY `idx_staff_pay_rates_restaurant_staff_from` (`restaurant_id`,`staff_id`,`effective_from`);

--
-- Indexes for table `staff_skills`
--
ALTER TABLE `staff_skills`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_staff_skills_restaurant` (`restaurant_id`),
  ADD KEY `idx_staff_skills_restaurant_staff` (`restaurant_id`,`staff_id`);

--
-- Indexes for table `time_off_requests`
--
ALTER TABLE `time_off_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_time_off_requests_restaurant_staff_start` (`restaurant_id`,`staff_id`,`start_dt`),
  ADD KEY `idx_time_off_requests_restaurant_status` (`restaurant_id`,`status`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `email_2` (`email`);

--
-- Indexes for table `user_restaurants`
--
ALTER TABLE `user_restaurants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_restaurant_user` (`restaurant_id`,`user_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `restaurant_id` (`restaurant_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `aloha_employees_stage`
--
ALTER TABLE `aloha_employees_stage`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `aloha_import_batches`
--
ALTER TABLE `aloha_import_batches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `aloha_labor_punches_stage`
--
ALTER TABLE `aloha_labor_punches_stage`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `aloha_sales_daily_stage`
--
ALTER TABLE `aloha_sales_daily_stage`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `availability`
--
ALTER TABLE `availability`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `callouts`
--
ALTER TABLE `callouts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `invites`
--
ALTER TABLE `invites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `job_logs`
--
ALTER TABLE `job_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `job_queue`
--
ALTER TABLE `job_queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pos_connections`
--
ALTER TABLE `pos_connections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pos_mappings`
--
ALTER TABLE `pos_mappings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `restaurants`
--
ALTER TABLE `restaurants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `schedule_enforcement_events`
--
ALTER TABLE `schedule_enforcement_events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `schedule_policies`
--
ALTER TABLE `schedule_policies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `schedule_policy_sets`
--
ALTER TABLE `schedule_policy_sets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `schedule_quality`
--
ALTER TABLE `schedule_quality`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `schedule_settings`
--
ALTER TABLE `schedule_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `schedule_violations`
--
ALTER TABLE `schedule_violations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `shifts`
--
ALTER TABLE `shifts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `shift_pickup_requests`
--
ALTER TABLE `shift_pickup_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `shift_trade_requests`
--
ALTER TABLE `shift_trade_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff_availability`
--
ALTER TABLE `staff_availability`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff_labor_profile`
--
ALTER TABLE `staff_labor_profile`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff_pay_rates`
--
ALTER TABLE `staff_pay_rates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff_skills`
--
ALTER TABLE `staff_skills`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `time_off_requests`
--
ALTER TABLE `time_off_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_restaurants`
--
ALTER TABLE `user_restaurants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `invites`
--
ALTER TABLE `invites`
  ADD CONSTRAINT `fk_invites_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_invites_restaurant` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`);

--
-- Constraints for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `fk_password_resets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `restaurants`
--
ALTER TABLE `restaurants`
  ADD CONSTRAINT `fk_restaurants_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `user_restaurants`
--
ALTER TABLE `user_restaurants`
  ADD CONSTRAINT `fk_user_restaurants_restaurant` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`),
  ADD CONSTRAINT `fk_user_restaurants_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
