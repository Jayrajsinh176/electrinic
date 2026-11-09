-- ============================================================
-- IND MEMBER BUSINESS PLAN MIGRATION
-- Modules:
--   1. Joining Voucher & Cashback Limit
--   2. Product Price Addon (₹1000: ₹300 Auto-Pool, ₹100 Direct, ₹100 Team, ₹500 Training)
--   3. 3x1 Auto-Pool Architecture (100 Pools, Re-topup, Upgrade, Payout)
--   4. 10-Level One-Time Affiliate Income (₹10,000 Joining)
--   5. Brand Promotion 100% Free Electric Scooter (36 Months Support)
--   6. 10-Level Team Promotion Bonus (83% of BPR for 6 Months)
--   7. Video Verification & 48-Hour Auto-Deletion Engine
--   8. Dual Wallet System (50% Scooter Wallet / 50% Main Balance) & Bank 10% Deduction
--   9. Award & Reward Pool (2.5% Turnover)
-- ============================================================

-- Disable binary income cleanly in income_settings
INSERT INTO `income_settings` (`setting_key`, `setting_value`, `description`) VALUES
  ('binary_income_enabled',                  '0',         'Binary income deactivated for IND plan (1/0)'),
  ('ind_plan_enabled',                       '1',         'IND Member Business Plan active (1/0)'),
  ('ind_joining_amount',                     '10000.00',  'IND member registration/activation amount'),
  ('ind_voucher_amount',                     '10000.00',  'Product voucher face value given on joining'),
  ('ind_voucher_validity_months',            '10',        'Voucher validity in months (10 months as per plan)'),
  ('ind_cashback_limit',                     '10000.00',  'Total claimable cashback limit per member'),
  ('ind_product_addon_amount',               '1000.00',   'Addon added to base product price'),
  ('ind_addon_autopool_amount',              '300.00',    'Amount from addon to Auto Pool 1'),
  ('ind_addon_direct_perf_amount',           '100.00',    'Amount from addon to Direct Performance Pool'),
  ('ind_addon_team_perf_amount',             '100.00',    'Amount from addon to Team Performance Pool'),
  ('ind_addon_training_amount',              '500.00',    'Amount from addon to Training Wallet'),
  ('ind_scooter_wallet_percent',             '50.00',     '% of every income credited to Scooter Wallet'),
  ('ind_main_wallet_percent',                '50.00',     '% of every income credited to Main Balance'),
  ('ind_bank_withdrawal_deduction_percent',  '10.00',     'Flat bank withdrawal deduction % (TDS + Admin)'),
  ('ind_award_reward_turnover_percent',      '2.50',      '% of company turnover reserved for Award/Reward pool')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`), `description` = VALUES(`description`);

-- Ensure columns exist in signup_users
SET @s := (SELECT IF(
   NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='signup_users' AND COLUMN_NAME='scooter_wallet_balance'),
   'ALTER TABLE `signup_users` ADD COLUMN `scooter_wallet_balance` DECIMAL(20,2) NOT NULL DEFAULT 0.00 AFTER `product_fund_balance`',
   'SELECT 1'));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @s := (SELECT IF(
   NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='signup_users' AND COLUMN_NAME='cashback_claimed_total'),
   'ALTER TABLE `signup_users` ADD COLUMN `cashback_claimed_total` DECIMAL(20,2) NOT NULL DEFAULT 0.00 AFTER `scooter_wallet_balance`',
   'SELECT 1'));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @s := (SELECT IF(
   NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='signup_users' AND COLUMN_NAME='cashback_max_limit'),
   'ALTER TABLE `signup_users` ADD COLUMN `cashback_max_limit` DECIMAL(20,2) NOT NULL DEFAULT 10000.00 AFTER `cashback_claimed_total`',
   'SELECT 1'));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @s := (SELECT IF(
   NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='signup_users' AND COLUMN_NAME='direct_vehicles_count'),
   'ALTER TABLE `signup_users` ADD COLUMN `direct_vehicles_count` INT NOT NULL DEFAULT 0 AFTER `cashback_max_limit`',
   'SELECT 1'));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 1. Product Vouchers Table
CREATE TABLE IF NOT EXISTS `ind_vouchers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `voucher_code` VARCHAR(50) NOT NULL UNIQUE,
  `customer_id` VARCHAR(50) NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL DEFAULT 10000.00,
  `used_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `balance_amount` DECIMAL(10,2) NOT NULL DEFAULT 10000.00,
  `validity_months` INT NOT NULL DEFAULT 10,
  `expires_at` DATETIME NOT NULL,
  `status` ENUM('active', 'redeemed', 'expired') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_cust` (`customer_id`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Product Addon Distribution Table
CREATE TABLE IF NOT EXISTS `ind_product_addon_splits` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` VARCHAR(50) NOT NULL,
  `customer_id` VARCHAR(50) NOT NULL,
  `addon_amount` DECIMAL(10,2) NOT NULL DEFAULT 1000.00,
  `auto_pool_amount` DECIMAL(10,2) NOT NULL DEFAULT 300.00,
  `direct_pool_amount` DECIMAL(10,2) NOT NULL DEFAULT 100.00,
  `team_pool_amount` DECIMAL(10,2) NOT NULL DEFAULT 100.00,
  `training_wallet_amount` DECIMAL(10,2) NOT NULL DEFAULT 500.00,
  `auto_pool_position_id` INT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_cust` (`customer_id`),
  INDEX `idx_order` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Training Wallet Ledger
CREATE TABLE IF NOT EXISTS `ind_training_wallet_ledger` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `direction` ENUM('credit', 'debit') NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `source` VARCHAR(100) NOT NULL,
  `ref_id` VARCHAR(100) NULL,
  `description` VARCHAR(255) NOT NULL,
  `balance_after` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. 100 Auto Pools: State and Positions
CREATE TABLE IF NOT EXISTS `ind_auto_pool_state` (
  `pool_level` INT NOT NULL PRIMARY KEY,
  `entry_amount` DECIMAL(12,2) NOT NULL,
  `retopup_amount` DECIMAL(12,2) NOT NULL,
  `upgrade_amount` DECIMAL(12,2) NOT NULL,
  `withdrawal_gross` DECIMAL(12,2) NOT NULL,
  `entries_count` INT NOT NULL DEFAULT 0,
  `completed_count` INT NOT NULL DEFAULT 0,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ind_auto_pool_positions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `customer_id` VARCHAR(50) NOT NULL,
  `pool_level` INT NOT NULL,
  `queue_no` INT NOT NULL,
  `entry_amount` DECIMAL(12,2) NOT NULL,
  `entry_source` ENUM('product_addon', 'retopup', 'upgrade') NOT NULL DEFAULT 'product_addon',
  `from_position_id` INT NULL,
  `child_count` INT NOT NULL DEFAULT 0,
  `status` ENUM('waiting', 'completed') NOT NULL DEFAULT 'waiting',
  `completed_at` DATETIME NULL,
  `joined_at` DATETIME NOT NULL,
  INDEX `idx_pool_status` (`pool_level`, `status`, `queue_no`),
  INDEX `idx_customer` (`customer_id`, `pool_level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ind_auto_pool_ledger` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `position_id` INT NOT NULL,
  `customer_id` VARCHAR(50) NOT NULL,
  `pool_level` INT NOT NULL,
  `total_collected` DECIMAL(12,2) NOT NULL,
  `retopup_amount` DECIMAL(12,2) NOT NULL,
  `retopup_position_id` INT NULL,
  `upgrade_amount` DECIMAL(12,2) NOT NULL,
  `upgrade_position_id` INT NULL,
  `gross_income` DECIMAL(12,2) NOT NULL,
  `scooter_wallet_credit` DECIMAL(12,2) NOT NULL,
  `main_wallet_credit` DECIMAL(12,2) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_pos` (`position_id`),
  INDEX `idx_cust` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. 10-Level Affiliate Marketing Income Logs (₹10,000 joining)
CREATE TABLE IF NOT EXISTS `ind_affiliate_10level_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `new_member_id` VARCHAR(50) NOT NULL,
  `earner_id` VARCHAR(50) NOT NULL,
  `root_level` INT NOT NULL,
  `percent` DECIMAL(5,2) NOT NULL,
  `base_amount` DECIMAL(10,2) NOT NULL DEFAULT 10000.00,
  `gross_amount` DECIMAL(10,2) NOT NULL,
  `scooter_wallet_amount` DECIMAL(10,2) NOT NULL,
  `main_balance_amount` DECIMAL(10,2) NOT NULL,
  `directs_count` INT NOT NULL DEFAULT 0,
  `status` ENUM('paid', 'skipped_insufficient_directs') NOT NULL DEFAULT 'paid',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_earner` (`earner_id`),
  INDEX `idx_new_member` (`new_member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Electric Scooter Brand Promotion Module
CREATE TABLE IF NOT EXISTS `ind_scooter_promotions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `customer_id` VARCHAR(50) NOT NULL,
  `vehicle_model` VARCHAR(100) NOT NULL,
  `vehicle_chassis_no` VARCHAR(100) NULL,
  `registration_number` VARCHAR(50) NULL,
  `monthly_bpr_amount` DECIMAL(10,2) NOT NULL DEFAULT 3000.00,
  `direct_vehicles_count` INT NOT NULL DEFAULT 0,
  `support_percent` DECIMAL(5,2) NOT NULL DEFAULT 50.00,
  `months_total` INT NOT NULL DEFAULT 36,
  `months_paid` INT NOT NULL DEFAULT 0,
  `status` ENUM('active', 'completed', 'paused', 'cancelled') NOT NULL DEFAULT 'active',
  `agreement_signed` TINYINT(1) NOT NULL DEFAULT 0,
  `agreement_signed_at` DATETIME NULL,
  `agreement_file` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_cust` (`customer_id`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ind_scooter_monthly_payouts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `promotion_id` INT NOT NULL,
  `customer_id` VARCHAR(50) NOT NULL,
  `month_number` INT NOT NULL,
  `period_key` VARCHAR(10) NOT NULL,
  `monthly_bpr_amount` DECIMAL(10,2) NOT NULL,
  `support_percent` DECIMAL(5,2) NOT NULL,
  `company_support_amount` DECIMAL(10,2) NOT NULL,
  `member_share_amount` DECIMAL(10,2) NOT NULL,
  `video_verification_id` INT NULL,
  `status` ENUM('pending_video', 'approved', 'paid', 'rejected') NOT NULL DEFAULT 'pending_video',
  `paid_at` DATETIME NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_promo` (`promotion_id`),
  INDEX `idx_period` (`period_key`),
  INDEX `idx_cust` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. 10-Level Team Promotion Bonus (6 Months BPR Commission)
CREATE TABLE IF NOT EXISTS `ind_team_promotion_bonus_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `promotion_id` INT NOT NULL,
  `downline_member_id` VARCHAR(50) NOT NULL,
  `earner_id` VARCHAR(50) NOT NULL,
  `root_level` INT NOT NULL,
  `percent` DECIMAL(5,2) NOT NULL,
  `month_index` INT NOT NULL,
  `bpr_base_amount` DECIMAL(10,2) NOT NULL,
  `bonus_amount` DECIMAL(10,2) NOT NULL,
  `scooter_wallet_amount` DECIMAL(10,2) NOT NULL,
  `main_balance_amount` DECIMAL(10,2) NOT NULL,
  `period_key` VARCHAR(10) NOT NULL,
  `status` ENUM('paid', 'pending') NOT NULL DEFAULT 'paid',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_earner` (`earner_id`),
  INDEX `idx_downline` (`downline_member_id`),
  INDEX `idx_period` (`period_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Video Verification Records with GPS and 48-Hour Auto-Delete Flag
CREATE TABLE IF NOT EXISTS `ind_video_verifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `customer_id` VARCHAR(50) NOT NULL,
  `promotion_id` INT NOT NULL,
  `month_number` INT NOT NULL,
  `video_file_path` VARCHAR(255) NOT NULL,
  `video_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  `video_deleted_at` DATETIME NULL,
  `gps_latitude` DECIMAL(10,7) NOT NULL,
  `gps_longitude` DECIMAL(10,7) NOT NULL,
  `gps_captured_at` DATETIME NOT NULL,
  `whatsapp_screenshot_path` VARCHAR(255) NULL,
  `social_story_screenshot_path` VARCHAR(255) NULL,
  `show_on_landing_page` TINYINT(1) NOT NULL DEFAULT 0,
  `status` ENUM('submitted', 'verified', 'rejected') NOT NULL DEFAULT 'submitted',
  `admin_notes` VARCHAR(255) NULL,
  `verified_at` DATETIME NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_cust` (`customer_id`),
  INDEX `idx_promo` (`promotion_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_feed` (`show_on_landing_page`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Award & Reward 2.5% Pool
CREATE TABLE IF NOT EXISTS `ind_award_reward_pool` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `period_key` VARCHAR(10) NOT NULL UNIQUE,
  `sales_turnover` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `pool_percent` DECIMAL(5,2) NOT NULL DEFAULT 2.50,
  `pool_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `distributed_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('open', 'distributed', 'closed') NOT NULL DEFAULT 'open',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Award & Reward Distributions History
CREATE TABLE IF NOT EXISTS `ind_award_reward_distributions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `pool_id` INT NOT NULL DEFAULT 0,
  `period_key` VARCHAR(10) NOT NULL,
  `customer_id` VARCHAR(50) NOT NULL,
  `reward_amount` DECIMAL(12,2) NOT NULL,
  `scooter_wallet_amount` DECIMAL(12,2) NOT NULL,
  `main_balance_amount` DECIMAL(12,2) NOT NULL,
  `awarded_by` VARCHAR(100) NULL,
  `notes` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_period` (`period_key`),
  INDEX `idx_cust` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Vehicle Purchase Commission Distribution Ledger (16% Pool)
CREATE TABLE IF NOT EXISTS `ind_vehicle_commissions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_ref` VARCHAR(100) NOT NULL,
  `buyer_id` VARCHAR(50) NOT NULL,
  `earner_id` VARCHAR(50) NOT NULL,
  `vehicle_id` INT NOT NULL DEFAULT 0,
  `vehicle_model` VARCHAR(100) NOT NULL,
  `vehicle_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `commission_type` VARCHAR(20) NOT NULL DEFAULT 'direct', -- 'direct' (5%) or 'team_level' (11% 10-level)
  `level_number` INT NOT NULL DEFAULT 0,
  `level_no` INT NOT NULL DEFAULT 0,
  `percent` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `gross_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `deduction_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `scooter_wallet_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `scooter_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `main_balance_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `main_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `status` VARCHAR(20) NOT NULL DEFAULT 'paid',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_order` (`order_ref`),
  INDEX `idx_buyer` (`buyer_id`),
  INDEX `idx_earner` (`earner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

