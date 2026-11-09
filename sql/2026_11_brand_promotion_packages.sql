-- ============================================================
-- BRAND PROMOTION PACKAGES & 10-ROOT AFFILIATE MARKETING (10 MONTHS)
-- ============================================================

CREATE TABLE IF NOT EXISTS `ind_bp_packages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `package_code` VARCHAR(50) NOT NULL UNIQUE,
  `package_name` VARCHAR(100) NOT NULL,
  `price` DECIMAL(12,2) NOT NULL,
  `monthly_return` DECIMAL(12,2) NOT NULL,
  `duration_months` INT NOT NULL DEFAULT 10,
  `total_return` DECIMAL(12,2) NOT NULL,
  `direct_bonus_percent` DECIMAL(5,2) NOT NULL DEFAULT 5.00,
  `badge_text` VARCHAR(50) DEFAULT NULL,
  `description` VARCHAR(255) NULL,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pre-seed the 4 Brand Promotion Packages
INSERT INTO `ind_bp_packages` 
  (`package_code`, `package_name`, `price`, `monthly_return`, `duration_months`, `total_return`, `direct_bonus_percent`, `badge_text`, `description`, `sort_order`)
VALUES
  ('BP-10K', 'Silver Plan', 10000.00, 1000.00, 10, 10000.00, 5.00, 'Starter', '₹1,000 monthly for 10 months + 5% Direct Bonus + 10-Root Affiliate', 1),
  ('BP-50K', 'Gold Plan', 50000.00, 5000.00, 10, 50000.00, 5.00, 'Popular', '₹5,000 monthly for 10 months + 5% Direct Bonus + 10-Root Affiliate', 2),
  ('BP-1L', 'Diamond Plan', 100000.00, 10000.00, 10, 100000.00, 5.00, 'Recommended', '₹10,000 monthly for 10 months + 5% Direct Bonus + 10-Root Affiliate', 3),
  ('BP-2L', 'Platinum Plan', 200000.00, 20000.00, 10, 200000.00, 5.00, 'VIP', '₹20,000 monthly for 10 months + 5% Direct Bonus + 10-Root Affiliate', 4)
ON DUPLICATE KEY UPDATE
  `package_name` = VALUES(`package_name`),
  `price` = VALUES(`price`),
  `monthly_return` = VALUES(`monthly_return`),
  `duration_months` = VALUES(`duration_months`),
  `total_return` = VALUES(`total_return`),
  `direct_bonus_percent` = VALUES(`direct_bonus_percent`),
  `badge_text` = VALUES(`badge_text`),
  `description` = VALUES(`description`),
  `sort_order` = VALUES(`sort_order`);

-- Table for Member Package Subscriptions
CREATE TABLE IF NOT EXISTS `ind_bp_subscriptions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `subscription_code` VARCHAR(50) NOT NULL UNIQUE,
  `customer_id` VARCHAR(50) NOT NULL,
  `package_id` INT NOT NULL,
  `package_code` VARCHAR(50) NOT NULL,
  `package_name` VARCHAR(100) NOT NULL,
  `package_amount` DECIMAL(12,2) NOT NULL,
  `monthly_payout` DECIMAL(12,2) NOT NULL,
  `duration_months` INT NOT NULL DEFAULT 10,
  `months_paid` INT NOT NULL DEFAULT 0,
  `total_paid` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `payment_mode` VARCHAR(30) NOT NULL DEFAULT 'Wallet',
  `transaction_id` VARCHAR(100) NULL,
  `payment_slip` VARCHAR(255) NULL,
  `direct_sponsor_id` VARCHAR(50) NULL,
  `direct_bonus_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `direct_bonus_status` ENUM('pending', 'paid') NOT NULL DEFAULT 'pending',
  `start_date` DATE NULL,
  `next_payout_date` DATE NULL,
  `approved_by` VARCHAR(50) NULL,
  `approved_at` DATETIME NULL,
  `status` ENUM('pending', 'active', 'completed', 'rejected') NOT NULL DEFAULT 'pending',
  `notes` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_cust` (`customer_id`),
  INDEX `idx_sponsor` (`direct_sponsor_id`),
  INDEX `idx_payout` (`status`, `next_payout_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Monthly Payouts to Subscribers
CREATE TABLE IF NOT EXISTS `ind_bp_monthly_payouts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `subscription_id` INT NOT NULL,
  `customer_id` VARCHAR(50) NOT NULL,
  `month_number` INT NOT NULL,
  `payout_amount` DECIMAL(12,2) NOT NULL,
  `period_key` VARCHAR(10) NOT NULL,
  `status` ENUM('paid', 'failed') NOT NULL DEFAULT 'paid',
  `paid_at` DATETIME NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_sub` (`subscription_id`),
  INDEX `idx_cust` (`customer_id`),
  INDEX `idx_period` (`period_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10-Root Affiliate Marketing Monthly Payouts
CREATE TABLE IF NOT EXISTS `ind_bp_root_payouts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `subscription_id` INT NOT NULL,
  `subscriber_id` VARCHAR(50) NOT NULL,
  `earner_id` VARCHAR(50) NOT NULL,
  `root_level` INT NOT NULL,
  `month_number` INT NOT NULL,
  `percent` DECIMAL(5,2) NOT NULL,
  `base_monthly_amount` DECIMAL(12,2) NOT NULL,
  `commission_amount` DECIMAL(12,2) NOT NULL,
  `directs_count` INT NOT NULL DEFAULT 0,
  `status` ENUM('paid', 'skipped_insufficient_directs') NOT NULL DEFAULT 'paid',
  `period_key` VARCHAR(10) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_earner` (`earner_id`),
  INDEX `idx_sub` (`subscription_id`),
  INDEX `idx_period` (`period_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Settings configuration for Brand Promotion Packages
INSERT INTO `income_settings` (`setting_key`, `setting_value`, `description`) VALUES
  ('bp_packages_enabled',        '1',     'Brand Promotion Packages active (1/0)'),
  ('bp_duration_months',         '10',    'Brand Promotion duration in months'),
  ('bp_direct_bonus_percent',    '5.00',  'One-time direct sponsor bonus % on package'),
  ('bp_root_1_percent',          '10.00', 'Affiliate Root 1 bonus % of monthly return'),
  ('bp_root_2_percent',          '8.00',  'Affiliate Root 2 bonus % of monthly return'),
  ('bp_root_3_percent',          '5.00',  'Affiliate Root 3 bonus % of monthly return'),
  ('bp_root_4_percent',          '5.00',  'Affiliate Root 4 bonus % of monthly return'),
  ('bp_root_5_percent',          '4.00',  'Affiliate Root 5 bonus % of monthly return'),
  ('bp_root_6_percent',          '4.00',  'Affiliate Root 6 bonus % of monthly return'),
  ('bp_root_7_percent',          '4.00',  'Affiliate Root 7 bonus % of monthly return'),
  ('bp_root_8_percent',          '3.00',  'Affiliate Root 8 bonus % of monthly return'),
  ('bp_root_9_percent',          '3.00',  'Affiliate Root 9 bonus % of monthly return'),
  ('bp_root_10_percent',         '5.00',  'Affiliate Root 10 bonus % of monthly return')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`), `description` = VALUES(`description`);

