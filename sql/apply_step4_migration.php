<?php
require_once __DIR__ . '/../member/config/db.php';

echo "=== Applying Step 4: Scooter Brand Promotion Schema Migration ===\n";

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.\n");
}

// 1. Add vehicle_id if missing
$chk = $conn->query("SHOW COLUMNS FROM `ind_scooter_promotions` LIKE 'vehicle_id'");
if ($chk && $chk->num_rows === 0) {
    $conn->query("ALTER TABLE `ind_scooter_promotions` ADD COLUMN `vehicle_id` INT NOT NULL DEFAULT 0 AFTER `customer_id`");
    echo "[OK] Added column vehicle_id\n";
}

// 2. Add payment_option if missing
$chk = $conn->query("SHOW COLUMNS FROM `ind_scooter_promotions` LIKE 'payment_option'");
if ($chk && $chk->num_rows === 0) {
    $conn->query("ALTER TABLE `ind_scooter_promotions` ADD COLUMN `payment_option` ENUM('full', 'finance') NOT NULL DEFAULT 'full' AFTER `vehicle_model`");
    echo "[OK] Added column payment_option\n";
}

// 3. Update default months_total to 25
$conn->query("ALTER TABLE `ind_scooter_promotions` MODIFY COLUMN `months_total` INT NOT NULL DEFAULT 25");
echo "[OK] Altered months_total default to 25\n";

echo "=== Step 4 Schema Migration Complete ===\n";

