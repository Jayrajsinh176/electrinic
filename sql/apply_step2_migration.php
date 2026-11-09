<?php
require_once __DIR__ . '/../member/config/db.php';

echo "=== Applying Step 2: Electric Vehicle Master Catalog & Dealership Migration ===\n";

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.\n");
}

// 1. Create table ind_electric_vehicles
$createVehiclesSql = "CREATE TABLE IF NOT EXISTS `ind_electric_vehicles` (
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
    `bpr_upfront_effective_cost` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `bpr_finance_monthly` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `bpr_finance_total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `bpr_finance_effective_cost` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `battery_spec` VARCHAR(100) NULL,
    `range_km` VARCHAR(50) NULL,
    `warranty_months` INT NOT NULL DEFAULT 12,
    `image_path` VARCHAR(255) NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_cat_status` (`category`, `status`),
    INDEX `idx_bpr` (`has_bpr`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($createVehiclesSql)) {
    echo "[OK] Table ind_electric_vehicles ready.\n";
} else {
    echo "[FAIL] Table ind_electric_vehicles: " . $conn->error . "\n";
}

// 2. Create table ind_dealership_categories
$createDealershipSql = "CREATE TABLE IF NOT EXISTS `ind_dealership_categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `category_name` VARCHAR(100) NOT NULL UNIQUE,
    `investment_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `investment_label` VARCHAR(50) NOT NULL,
    `description` VARCHAR(255) NULL,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($createDealershipSql)) {
    echo "[OK] Table ind_dealership_categories ready.\n";
} else {
    echo "[FAIL] Table ind_dealership_categories: " . $conn->error . "\n";
}

// 3. Seed Electric Vehicles
$vehicles = [
    [
        'model_code' => 'MAX_VXI',
        'model_name' => 'MAX VXI',
        'category' => 'scooter',
        'vehicle_price' => 66000.00,
        'has_bpr' => 1,
        'bpr_months' => 25,
        'bpr_upfront_monthly' => 2000.00,
        'bpr_upfront_total' => 50000.00,
        'bpr_upfront_effective_cost' => 16000.00,
        'bpr_finance_monthly' => 1700.00,
        'bpr_finance_total' => 42500.00,
        'bpr_finance_effective_cost' => 23500.00,
        'battery_spec' => '60V / 32AH Gel Battery',
        'range_km' => '50 - 55 km+',
        'warranty_months' => 12,
        'image_path' => 'max-vxi.jpeg',
        'sort_order' => 1
    ],
    [
        'model_code' => 'MAX_ZXI',
        'model_name' => 'MAX ZXI',
        'category' => 'scooter',
        'vehicle_price' => 79000.00,
        'has_bpr' => 1,
        'bpr_months' => 25,
        'bpr_upfront_monthly' => 2000.00,
        'bpr_upfront_total' => 50000.00,
        'bpr_upfront_effective_cost' => 29000.00,
        'bpr_finance_monthly' => 1700.00,
        'bpr_finance_total' => 42500.00,
        'bpr_finance_effective_cost' => 36500.00,
        'battery_spec' => '60V / 30AH Gel Battery',
        'range_km' => '50 - 60 km+',
        'warranty_months' => 36,
        'image_path' => 'evelocy-m2.jpeg',
        'sort_order' => 2
    ],
    [
        'model_code' => 'ACTIVE_CHETAAK_FIN',
        'model_name' => 'ACTIVE CHETAAK (Finance)',
        'category' => 'scooter',
        'vehicle_price' => 116000.00,
        'has_bpr' => 1,
        'bpr_months' => 25,
        'bpr_upfront_monthly' => 3500.00,
        'bpr_upfront_total' => 87500.00,
        'bpr_upfront_effective_cost' => 28500.00,
        'bpr_finance_monthly' => 3000.00,
        'bpr_finance_total' => 75000.00,
        'bpr_finance_effective_cost' => 41000.00,
        'battery_spec' => '60V / 30AH Gel Battery',
        'range_km' => '50 - 60 km+',
        'warranty_months' => 36,
        'image_path' => 'active-chetaak.jpeg',
        'sort_order' => 3
    ],
    [
        'model_code' => 'ACTIVE_CHETAAK_NOFIN',
        'model_name' => 'ACTIVE CHETAAK (Without Finance)',
        'category' => 'scooter',
        'vehicle_price' => 127000.00,
        'has_bpr' => 1,
        'bpr_months' => 25,
        'bpr_upfront_monthly' => 4000.00,
        'bpr_upfront_total' => 100000.00,
        'bpr_upfront_effective_cost' => 27000.00,
        'bpr_finance_monthly' => 3400.00,
        'bpr_finance_total' => 85000.00,
        'bpr_finance_effective_cost' => 42000.00,
        'battery_spec' => '60V / 45AH Gel Battery',
        'range_km' => '50 - 60 km+',
        'warranty_months' => 36,
        'image_path' => 'active-chetaak.jpeg',
        'sort_order' => 4
    ],
    [
        'model_code' => 'MANIA_VXI',
        'model_name' => 'MANIA VXI',
        'category' => 'scooter',
        'vehicle_price' => 125500.00,
        'has_bpr' => 0,
        'bpr_months' => 0,
        'bpr_upfront_monthly' => 0.00,
        'bpr_upfront_total' => 0.00,
        'bpr_upfront_effective_cost' => 0.00,
        'bpr_finance_monthly' => 0.00,
        'bpr_finance_total' => 0.00,
        'bpr_finance_effective_cost' => 0.00,
        'battery_spec' => '60V / 30AH Lithium',
        'range_km' => '60 - 70 km+',
        'warranty_months' => 36,
        'image_path' => 'mania-zxi.jpeg',
        'sort_order' => 5
    ],
    [
        'model_code' => 'M2_ZXI',
        'model_name' => 'M2 ZXI',
        'category' => 'scooter',
        'vehicle_price' => 124000.00,
        'has_bpr' => 0,
        'bpr_months' => 0,
        'bpr_upfront_monthly' => 0.00,
        'bpr_upfront_total' => 0.00,
        'bpr_upfront_effective_cost' => 0.00,
        'bpr_finance_monthly' => 0.00,
        'bpr_finance_total' => 0.00,
        'bpr_finance_effective_cost' => 0.00,
        'battery_spec' => '60V / 30AH Lithium',
        'range_km' => '60 - 75 km+',
        'warranty_months' => 36,
        'image_path' => 'evelocy-m2.jpeg',
        'sort_order' => 6
    ],
    [
        'model_code' => 'EBIKE_KN1',
        'model_name' => 'E-Bike KN1',
        'category' => 'bike',
        'vehicle_price' => 195500.00,
        'has_bpr' => 0,
        'bpr_months' => 0,
        'bpr_upfront_monthly' => 0.00,
        'bpr_upfront_total' => 0.00,
        'bpr_upfront_effective_cost' => 0.00,
        'bpr_finance_monthly' => 0.00,
        'bpr_finance_total' => 0.00,
        'bpr_finance_effective_cost' => 0.00,
        'battery_spec' => '72V / 45AH Lithium',
        'range_km' => '100 km+',
        'warranty_months' => 36,
        'image_path' => 'bike-kn1.jpeg',
        'sort_order' => 7
    ],
    [
        'model_code' => 'ERIKSHAW_SOLAR',
        'model_name' => 'E-Rickshaw Solar',
        'category' => 'rickshaw',
        'vehicle_price' => 220000.00,
        'has_bpr' => 0,
        'bpr_months' => 0,
        'bpr_upfront_monthly' => 0.00,
        'bpr_upfront_total' => 0.00,
        'bpr_upfront_effective_cost' => 0.00,
        'bpr_finance_monthly' => 0.00,
        'bpr_finance_total' => 0.00,
        'bpr_finance_effective_cost' => 0.00,
        'battery_spec' => '51.2V / 100AH Lithium + Solar Panel',
        'range_km' => '100 km+',
        'warranty_months' => 36,
        'image_path' => 'erikshaw-solar.jpeg',
        'sort_order' => 8
    ],
    [
        'model_code' => 'ELOADER_SOLAR',
        'model_name' => 'E-Loader Solar',
        'category' => 'loader',
        'vehicle_price' => 255000.00,
        'has_bpr' => 0,
        'bpr_months' => 0,
        'bpr_upfront_monthly' => 0.00,
        'bpr_upfront_total' => 0.00,
        'bpr_upfront_effective_cost' => 0.00,
        'bpr_finance_monthly' => 0.00,
        'bpr_finance_total' => 0.00,
        'bpr_finance_effective_cost' => 0.00,
        'battery_spec' => '51.2V / 100AH Lithium + Solar Roof',
        'range_km' => '100 km+',
        'warranty_months' => 36,
        'image_path' => 'eloader-solar.jpeg',
        'sort_order' => 9
    ],
    [
        'model_code' => 'EAUTO_L3_VXI',
        'model_name' => 'E-Auto L-3 VXI',
        'category' => 'auto',
        'vehicle_price' => 233000.00,
        'has_bpr' => 0,
        'bpr_months' => 0,
        'bpr_upfront_monthly' => 0.00,
        'bpr_upfront_total' => 0.00,
        'bpr_upfront_effective_cost' => 0.00,
        'bpr_finance_monthly' => 0.00,
        'bpr_finance_total' => 0.00,
        'bpr_finance_effective_cost' => 0.00,
        'battery_spec' => '51.2V / 100AH Lithium',
        'range_km' => '90 - 100 km+',
        'warranty_months' => 36,
        'image_path' => 'auto-l3.jpeg',
        'sort_order' => 10
    ],
    [
        'model_code' => 'EAUTO_L5',
        'model_name' => 'E-Auto L-5',
        'category' => 'auto',
        'vehicle_price' => 433000.00,
        'has_bpr' => 0,
        'bpr_months' => 0,
        'bpr_upfront_monthly' => 0.00,
        'bpr_upfront_total' => 0.00,
        'bpr_upfront_effective_cost' => 0.00,
        'bpr_finance_monthly' => 0.00,
        'bpr_finance_total' => 0.00,
        'bpr_finance_effective_cost' => 0.00,
        'battery_spec' => 'Heavy Duty Lithium',
        'range_km' => '120 - 150 km+',
        'warranty_months' => 36,
        'image_path' => 'auto-l3.jpeg',
        'sort_order' => 11
    ]
];

$insVehicle = $conn->prepare("INSERT INTO `ind_electric_vehicles`
    (`model_code`, `model_name`, `category`, `vehicle_price`, `has_bpr`, `bpr_months`,
     `bpr_upfront_monthly`, `bpr_upfront_total`, `bpr_upfront_effective_cost`,
     `bpr_finance_monthly`, `bpr_finance_total`, `bpr_finance_effective_cost`,
     `battery_spec`, `range_km`, `warranty_months`, `image_path`, `sort_order`, `status`)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
    ON DUPLICATE KEY UPDATE
    `model_name` = VALUES(`model_name`),
    `category` = VALUES(`category`),
    `vehicle_price` = VALUES(`vehicle_price`),
    `has_bpr` = VALUES(`has_bpr`),
    `bpr_months` = VALUES(`bpr_months`),
    `bpr_upfront_monthly` = VALUES(`bpr_upfront_monthly`),
    `bpr_upfront_total` = VALUES(`bpr_upfront_total`),
    `bpr_upfront_effective_cost` = VALUES(`bpr_upfront_effective_cost`),
    `bpr_finance_monthly` = VALUES(`bpr_finance_monthly`),
    `bpr_finance_total` = VALUES(`bpr_finance_total`),
    `bpr_finance_effective_cost` = VALUES(`bpr_finance_effective_cost`),
    `battery_spec` = VALUES(`battery_spec`),
    `range_km` = VALUES(`range_km`),
    `warranty_months` = VALUES(`warranty_months`),
    `image_path` = VALUES(`image_path`),
    `sort_order` = VALUES(`sort_order`)");

foreach ($vehicles as $v) {
    $insVehicle->bind_param(
        'sssdiiddddddssisi',
        $v['model_code'],
        $v['model_name'],
        $v['category'],
        $v['vehicle_price'],
        $v['has_bpr'],
        $v['bpr_months'],
        $v['bpr_upfront_monthly'],
        $v['bpr_upfront_total'],
        $v['bpr_upfront_effective_cost'],
        $v['bpr_finance_monthly'],
        $v['bpr_finance_total'],
        $v['bpr_finance_effective_cost'],
        $v['battery_spec'],
        $v['range_km'],
        $v['warranty_months'],
        $v['image_path'],
        $v['sort_order']
    );
    $insVehicle->execute();
}
$insVehicle->close();
echo "[OK] Seeded 11 Electric Vehicle models successfully.\n";

// 4. Seed Dealership Categories
$dealerships = [
    ['Gold', 1500000.00, '15 Lakh', 'Gold Dealership Partner'],
    ['Diamond', 3000000.00, '30 Lakh', 'Diamond Dealership Partner'],
    ['State Area (25%)', 5000000.00, '50 Lakh', '25% State Regional Partner'],
    ['State Area (50%)', 10000000.00, '1 Crore', '50% State Regional Partner'],
    ['Full State', 20000000.00, '2 Crore', 'Full State Master Partner']
];

$insDeal = $conn->prepare("INSERT INTO `ind_dealership_categories`
    (`category_name`, `investment_amount`, `investment_label`, `description`, `status`)
    VALUES (?, ?, ?, ?, 'active')
    ON DUPLICATE KEY UPDATE
    `investment_amount` = VALUES(`investment_amount`),
    `investment_label` = VALUES(`investment_label`),
    `description` = VALUES(`description`)");

foreach ($dealerships as $d) {
    $insDeal->bind_param('sdss', $d[0], $d[1], $d[2], $d[3]);
    $insDeal->execute();
}
$insDeal->close();
echo "[OK] Seeded 5 Dealership categories successfully.\n";

echo "=== Step 2 Database Migration Finished Successfully ===\n";

