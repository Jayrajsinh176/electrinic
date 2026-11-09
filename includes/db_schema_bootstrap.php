<?php
/**
 * ELECTRINC — Master Database Schema Bootstrapper & Self-Healer
 * 
 * Ensures all foundational tables and baseline seeds exist so that
 * the project works immediately on any fresh installation without errors.
 */

if (!function_exists('ensure_database_schema')) {
    function ensure_database_schema(mysqli $conn): array
    {
        $log = [];
        $conn->set_charset("utf8mb4");

        // Helper to run query safely
        $run = static function(string $label, string $sql) use ($conn, &$log): bool {
            if ($conn->query($sql)) {
                $log[] = "[OK] {$label}";
                return true;
            }
            $log[] = "[ERR] {$label}: " . $conn->error;
            return false;
        };

        // 1. SIGNUP_USERS TABLE
        $run('signup_users table', "CREATE TABLE IF NOT EXISTS `signup_users` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `customer_id` VARCHAR(50) NOT NULL UNIQUE,
            `referral_id` VARCHAR(50) DEFAULT NULL,
            `placement_id` VARCHAR(50) DEFAULT NULL,
            `position` VARCHAR(10) DEFAULT 'L',
            `full_name` VARCHAR(150) NOT NULL DEFAULT '',
            `email` VARCHAR(150) DEFAULT NULL,
            `mobile` VARCHAR(20) DEFAULT NULL,
            `password` VARCHAR(255) NOT NULL,
            `transaction_password` VARCHAR(255) DEFAULT NULL,
            `state` VARCHAR(100) DEFAULT NULL,
            `city` VARCHAR(100) DEFAULT NULL,
            `wallet_balance` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `scooter_wallet_balance` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `product_fund_balance` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `total_left_members` INT UNSIGNED NOT NULL DEFAULT 0,
            `total_right_members` INT UNSIGNED NOT NULL DEFAULT 0,
            `product_name` VARCHAR(150) DEFAULT 'Electric Vehicle Voucher',
            `product_price` DECIMAL(12,2) NOT NULL DEFAULT 10000.00,
            `product_purchased` TINYINT(1) NOT NULL DEFAULT 0,
            `registration_status` ENUM('Active', 'Inactive', 'Blocked') NOT NULL DEFAULT 'Inactive',
            `terms_accepted` TINYINT(1) NOT NULL DEFAULT 1,
            `age_confirmed` TINYINT(1) NOT NULL DEFAULT 1,
            `activated_at` DATETIME DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_referral` (`referral_id`),
            INDEX `idx_placement` (`placement_id`),
            INDEX `idx_status` (`registration_status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 2. SIGNUP_ADMIN TABLE
        $run('signup_admin table', "CREATE TABLE IF NOT EXISTS `signup_admin` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `customer_id` VARCHAR(50) NOT NULL UNIQUE,
            `name` VARCHAR(100) NOT NULL DEFAULT 'Super Admin',
            `email` VARCHAR(150) DEFAULT 'admin@electrinc.local',
            `mobile` VARCHAR(20) DEFAULT NULL,
            `password` VARCHAR(255) NOT NULL,
            `role` VARCHAR(50) NOT NULL DEFAULT 'Super Admin',
            `status` VARCHAR(20) NOT NULL DEFAULT 'Active',
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Seed default Admin if signup_admin is empty
        $adminCheck = $conn->query("SELECT COUNT(*) AS c FROM `signup_admin`");
        $adminCount = $adminCheck ? (int)$adminCheck->fetch_assoc()['c'] : 0;
        if ($adminCount === 0) {
            $adminPass = password_hash('admin123', PASSWORD_DEFAULT);
            $conn->query("INSERT INTO `signup_admin` (`customer_id`, `name`, `email`, `password`, `role`, `status`)
                          VALUES ('admin', 'Super Administrator', 'admin@electrinc.com', '{$adminPass}', 'Super Admin', 'Active')");
            $log[] = "[SEED] Default Admin created: ID = admin / Password = admin123";
        }

        // Seed root Member (fresh0001) if signup_users is empty
        $userCheck = $conn->query("SELECT COUNT(*) AS c FROM `signup_users`");
        $userCount = $userCheck ? (int)$userCheck->fetch_assoc()['c'] : 0;
        if ($userCount === 0) {
            $memberPass = password_hash('123456', PASSWORD_DEFAULT);
            $conn->query("INSERT INTO `signup_users` 
                (`customer_id`, `referral_id`, `placement_id`, `position`, `full_name`, `email`, `mobile`, `password`, `state`, `city`, `registration_status`, `activated_at`)
                VALUES 
                ('fresh0001', '', '', 'L', 'Freshiya Master Account', 'member@electrinc.com', '9876543210', '{$memberPass}', 'Gujarat', 'Surat', 'Active', NOW())");
            $log[] = "[SEED] Root Member seeded: ID = fresh0001 / Password = 123456";
        }

        // 3. MEMBER_PROFILES TABLE
        $run('member_profiles table', "CREATE TABLE IF NOT EXISTS `member_profiles` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `customer_id` VARCHAR(50) NOT NULL UNIQUE,
            `full_name` VARCHAR(150) NOT NULL DEFAULT '',
            `birth_date` DATE DEFAULT NULL,
            `gender` VARCHAR(20) DEFAULT NULL,
            `house_number` VARCHAR(100) DEFAULT NULL,
            `street_name` VARCHAR(150) DEFAULT NULL,
            `state_name` VARCHAR(100) DEFAULT NULL,
            `district_name` VARCHAR(100) DEFAULT NULL,
            `city_name` VARCHAR(100) DEFAULT NULL,
            `pincode` VARCHAR(20) DEFAULT NULL,
            `email` VARCHAR(150) DEFAULT NULL,
            `mobile_no` VARCHAR(20) DEFAULT NULL,
            `nominee_name` VARCHAR(150) DEFAULT NULL,
            `nominee_relation` VARCHAR(100) DEFAULT NULL,
            `nominee_birthdate` DATE DEFAULT NULL,
            `profile_photo` VARCHAR(255) DEFAULT NULL,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 4. INCOME SETTINGS TABLE
        $run('income_settings table', "CREATE TABLE IF NOT EXISTS `income_settings` (
            `setting_key` VARCHAR(60) NOT NULL PRIMARY KEY,
            `setting_value` VARCHAR(255) NOT NULL,
            `description` VARCHAR(255) DEFAULT NULL,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Baseline income settings seed
        $settings = [
            ['ind_voucher_validity_months', '10', 'Voucher validity in months (10 months as per plan)'],
            ['ind_voucher_amount', '10000.00', 'Voucher face value on joining'],
            ['ind_cashback_amount', '10000.00', 'Cashback total benefit'],
            ['ind_addon_amount', '1000.00', 'Addon price split on product'],
            ['ind_scooter_wallet_percent', '50.00', 'Dual wallet % locked for scooter purchase'],
            ['ind_bank_deduction_percent', '10.00', 'Bank withdrawal TDS/Admin deduction'],
            ['direct_sponsor_percent', '5.00', 'Direct Sponsor Commission %']
        ];
        foreach ($settings as $s) {
            $k = $conn->real_escape_string($s[0]);
            $v = $conn->real_escape_string($s[1]);
            $d = $conn->real_escape_string($s[2]);
            $conn->query("INSERT INTO `income_settings` (`setting_key`, `setting_value`, `description`) 
                          VALUES ('{$k}', '{$v}', '{$d}') ON DUPLICATE KEY UPDATE `description` = VALUES(`description`)");
        }

        // 5. MEMBER INCOME LEDGER
        $run('member_income_ledger table', "CREATE TABLE IF NOT EXISTS `member_income_ledger` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `customer_id` VARCHAR(50) NOT NULL,
            `income_type` VARCHAR(50) NOT NULL,
            `source` VARCHAR(50) DEFAULT NULL,
            `source_ref` VARCHAR(120) DEFAULT NULL,
            `report_date` DATE DEFAULT NULL,
            `cycle_key` VARCHAR(20) DEFAULT NULL,
            `counts_to_cap` TINYINT(1) NOT NULL DEFAULT 0,
            `gross_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `credited_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `flushed_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `scooter_wallet_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `main_wallet_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_cust_date` (`customer_id`, `report_date`),
            INDEX `idx_type` (`income_type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 6. AUTO-POOL STATE & MATRIX
        $run('ind_auto_pool_state table', "CREATE TABLE IF NOT EXISTS `ind_auto_pool_state` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `pool_level` INT NOT NULL UNIQUE,
            `entry_amount` DECIMAL(10,2) NOT NULL,
            `retopup_amount` DECIMAL(10,2) NOT NULL,
            `upgrade_amount` DECIMAL(10,2) NOT NULL,
            `withdrawal_gross` DECIMAL(10,2) NOT NULL,
            `entries_count` INT NOT NULL DEFAULT 0,
            `completed_count` INT NOT NULL DEFAULT 0,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Seed 100 Auto Pools if empty
        $poolCheck = $conn->query("SELECT COUNT(*) AS c FROM `ind_auto_pool_state`");
        $poolCount = $poolCheck ? (int)$poolCheck->fetch_assoc()['c'] : 0;
        if ($poolCount < 100) {
            $stmt = $conn->prepare("INSERT INTO `ind_auto_pool_state` 
                (`pool_level`, `entry_amount`, `retopup_amount`, `upgrade_amount`, `withdrawal_gross`) 
                VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE entry_amount = VALUES(entry_amount)");
            for ($lvl = 1; $lvl <= 100; $lvl++) {
                $entry = (float)(200 + ($lvl * 100)); // Pool 1 = 300, Pool 2 = 400 ...
                $retopup = $entry;
                $upgrade = ($lvl < 100) ? ($entry + 100.0) : 0.0;
                $withdrawal = ($lvl < 100) ? ($entry - 100.0) : (($entry * 3) - $retopup);
                $stmt->bind_param('idddd', $lvl, $entry, $retopup, $upgrade, $withdrawal);
                $stmt->execute();
            }
            $stmt->close();
            $log[] = "[SEED] 100 Auto-Pool levels seeded successfully";
        }

        // 7. ELECTRIC VEHICLES MASTER CATALOG
        $run('ind_electric_vehicles table', "CREATE TABLE IF NOT EXISTS `ind_electric_vehicles` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `model_code` VARCHAR(50) NOT NULL UNIQUE,
            `model_name` VARCHAR(100) NOT NULL,
            `category` ENUM('scooter', 'bike', 'rickshaw', 'loader', 'auto') NOT NULL DEFAULT 'scooter',
            `vehicle_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `includes_addon` TINYINT(1) NOT NULL DEFAULT 1,
            `addon_amount` DECIMAL(10,2) NOT NULL DEFAULT 1000.00,
            `has_bpr` TINYINT(1) NOT NULL DEFAULT 0,
            `bpr_months` INT NOT NULL DEFAULT 25,
            `bpr_upfront_monthly` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `bpr_upfront_total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `bpr_finance_monthly` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `battery_spec` VARCHAR(100) NULL,
            `range_km` VARCHAR(50) NULL,
            `warranty_months` INT NOT NULL DEFAULT 36,
            `image_path` VARCHAR(255) NULL,
            `sort_order` INT NOT NULL DEFAULT 0,
            `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Seed 11 EV Models if empty
        $evCheck = $conn->query("SELECT COUNT(*) AS c FROM `ind_electric_vehicles`");
        $evCount = $evCheck ? (int)$evCheck->fetch_assoc()['c'] : 0;
        if ($evCount === 0) {
            $evList = [
                ['auto-l3',        'Auto L-3',                 'rickshaw', 145000, '51.2V / 100AH Lithium', '90–100 KM+',   36, 'auto-l3.jpeg',        1],
                ['erikshaw-solar', 'E-Rickshaw with Solar',     'rickshaw', 165000, '51.2V / 100AH Lithium', '100 KM+',      36, 'erikshaw-solar.jpeg', 2],
                ['mini-eloader',   'Mini E-Loader',             'loader',   125000, '51V / 45–75AH Lithium',  '70–120 KM+',  18, 'mini-eloader.jpeg',   3],
                ['eloader-solar',  'E-Loader with Solar',       'loader',   155000, '51.2V / 100AH Lithium', '100 KM+',      36, 'eloader-solar.jpeg',  4],
                ['rickshaw-deluxe','Passenger E-Rickshaw Deluxe','rickshaw',165000,'51.2V / 100AH Lithium', '100–115 KM+',  36, 'rickshaw-passenger-deluxe.png', 18],
                ['loader-box-blue','E-Loader Max Container',    'loader',   175000, '51.2V / 100AH Lithium', '100–120 KM+',  36, 'rickshaw-cargo-box-blue.png',   19],
                ['auto-l5-white',  'E-Auto L-5 Passenger',      'auto',     245000, '51.2V / 120AH Lithium', '120–140 KM+',  36, 'rickshaw-auto-l5-white.png',    20],
                ['loader-open-bed','Mini E-Loader Open Bed',    'loader',   135000, '51V / 60AH Lithium',    '80–100 KM+',   18, 'rickshaw-mini-loader-bed.png',  21],
                ['max-vxi',        'MAX VXI',                   'scooter',   66000, '60V / 32AH Gel Battery',  '50 - 55 km+', 12, 'max-vxi.jpeg',            5],
                ['max-zxi',        'MAX ZXI',                   'scooter',   79000, '60V / 30AH Gel Battery',  '50 - 60 km+', 36, 'evelocy-m2.jpeg',         6],
                ['active-chetaak', 'Active Chetaak',            'scooter',   72000, '60V / 45AH Lithium',      '55 – 125 KM+',36, 'active-chetaak.jpeg',     7],
                ['mania-vxi',      'MANIA VXI',                 'scooter',  125500, '60V / 30AH Lithium',      '60 - 70 km+', 36, 'mania-zxi.jpeg',          8],
                ['m2-zxi',         'M2 ZXI',                    'scooter',  124000, '60V / 30AH Lithium',      '60 - 75 km+', 36, 'evelocy-m2.jpeg',         9],
                ['max-vxi-aero',   'MAX VXI (Silver Aero)',     'scooter',   69000, '51V / 30AH Lithium',      '65 - 80 km+', 36, 'scooter-silver-aero.png', 10],
                ['beast-pro',      'BEAST PRO (Emerald)',       'scooter',   84000, '60V / 35AH Lithium',      '80 - 95 km+', 36, 'scooter-beast-pro.png',   11],
                ['beast-pro-plus', 'BEAST PRO+ (Army Green)',   'scooter',   88000, '60V / 40AH Lithium',      '85 - 100 km+',36, 'scooter-beast-pro-plus.png',12],
                ['chetaak-red',    'ACTIVE CHETAAK PRO (Red)',  'scooter',   76000, '60V / 35AH Lithium',      '75 - 90 km+', 36, 'scooter-chetaak-red.png', 13],
                ['chetaak-mint',   'ACTIVE CHETAAK (Mint)',     'scooter',   74000, '60V / 32AH Lithium',      '70 - 85 km+', 36, 'scooter-chetaak-mint.png',14],
                ['mania-orange',   'ELECTER MANIA (Orange)',    'scooter',   89000, '60V / 42AH Lithium',      '90 - 110 km+',36, 'scooter-mania-orange.png',15],
                ['m2-sage',        'E-VELOCY M2 (Sage Tri-LED)','scooter',   94000, '60V / 45AH Lithium',      '100 - 120 km+',36, 'scooter-m2-sage.png',    16],
                ['bike-v6',          'Bike V6 2.0 Sports',        'bike',     185000, '72V / 60AH Lithium',    '120 KM+',      36, 'bike-v6.jpeg',          30],
                ['bike-kn1',         'Bike KN1 Commuter',         'bike',     145000, '72V / 45AH Lithium',    '100 KM+',      36, 'bike-kn1.jpeg',         31],
                ['bike-ninja-red',   'BIKE V6 SUPERSPORT (Red)',  'bike',     185000, '72V / 60AH Lithium',    '130–150 KM+',  36, 'bike-ninja-red.png',    32],
                ['bike-ninja-green', 'NINJA STEALTH (Green)',     'bike',     195000, '72V / 60AH Lithium',    '140–160 KM+',  36, 'bike-ninja-green.png',  33],
                ['bike-trellis',     'STREETFIGHTER EV (Blue)',   'bike',     165000, '72V / 50AH Lithium',    '120–135 KM+',  36, 'bike-trellis-blue.png', 34],
                ['bike-commuter',    'KN1 PRO COMMUTER (Blue)',   'bike',     145000, '72V / 45AH Lithium',    '110–125 KM+',  36, 'bike-commuter-blue.png',35],
            ];
            $stmt = $conn->prepare("INSERT INTO `ind_electric_vehicles` 
                (`model_code`, `model_name`, `category`, `vehicle_price`, `battery_spec`, `range_km`, `warranty_months`, `image_path`, `sort_order`, `status`)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')");
            foreach ($evList as $ev) {
                $stmt->bind_param('sssissisi', $ev[0], $ev[1], $ev[2], $ev[3], $ev[4], $ev[5], $ev[6], $ev[7], $ev[8]);
                $stmt->execute();
            }
            $stmt->close();
            $log[] = "[SEED] 11 Electric Vehicle models seeded in master catalog";
        }

        return $log;
    }
}

