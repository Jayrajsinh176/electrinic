<?php
require_once __DIR__ . '/../member/config/db.php';

echo "=== Applying Step 3: Vehicle Purchase Commission Engine Migration ===\n";

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.\n");
}

// 1. Create table ind_vehicle_commissions
$createTableSql = "CREATE TABLE IF NOT EXISTS `ind_vehicle_commissions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_ref` VARCHAR(50) NOT NULL,
    `buyer_id` VARCHAR(50) NOT NULL,
    `earner_id` VARCHAR(50) NOT NULL,
    `vehicle_id` INT NOT NULL DEFAULT 0,
    `vehicle_model` VARCHAR(100) NOT NULL,
    `vehicle_price` DECIMAL(12,2) NOT NULL,
    `commission_type` ENUM('direct', 'team_level') NOT NULL,
    `level_number` INT NOT NULL DEFAULT 1,
    `percent` DECIMAL(5,2) NOT NULL,
    `gross_amount` DECIMAL(12,2) NOT NULL,
    `scooter_wallet_amount` DECIMAL(12,2) NOT NULL,
    `main_balance_amount` DECIMAL(12,2) NOT NULL,
    `status` ENUM('paid', 'pending', 'cancelled') NOT NULL DEFAULT 'paid',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_earner` (`earner_id`),
    INDEX `idx_buyer` (`buyer_id`),
    INDEX `idx_order` (`order_ref`),
    INDEX `idx_type_level` (`commission_type`, `level_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($createTableSql)) {
    echo "[OK] Table ind_vehicle_commissions created/verified successfully.\n";
} else {
    echo "[FAIL] Table ind_vehicle_commissions: " . $conn->error . "\n";
}

// 2. Settings for Vehicle Commissions
$settings = [
    ['ind_vehicle_direct_commission_percent', '5.00', 'Direct commission % on EV purchase (PDF Page 11)'],
    ['ind_vehicle_team_commission_enabled', '1', '10-Level team commission on EV purchase active (1/0)'],
    ['ind_vehicle_commission_enabled', '1', 'Electric vehicle commission engine active (1/0)']
];

$stmt = $conn->prepare("INSERT INTO income_settings (setting_key, setting_value, description)
    VALUES (?, ?, ?)
    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), description = VALUES(description)");

foreach ($settings as $s) {
    $stmt->bind_param('sss', $s[0], $s[1], $s[2]);
    $stmt->execute();
}
$stmt->close();
echo "[OK] Seeded vehicle commission settings in income_settings.\n";

echo "=== Step 3 Migration Finished Successfully ===\n";

