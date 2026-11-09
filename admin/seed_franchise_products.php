<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/db.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.");
}

$conn->set_charset("utf8mb4");

// 1. Ensure franchies_add_product table exists
$conn->query("
    CREATE TABLE IF NOT EXISTS `franchies_add_product` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `product_name` VARCHAR(255) NOT NULL,
        `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `gst` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `bv` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `quantity` INT NOT NULL DEFAULT 0,
        `product_type` VARCHAR(50) NOT NULL DEFAULT 'repurchase',
        `status` ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX (`product_name`),
        INDEX (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
");

// Ensure quantity, gst, bv columns exist
$checkQty = $conn->query("SHOW COLUMNS FROM franchies_add_product LIKE 'quantity'");
if ($checkQty && $checkQty->num_rows === 0) {
    $conn->query("ALTER TABLE franchies_add_product ADD COLUMN quantity INT NOT NULL DEFAULT 0 AFTER price");
}
foreach (['gst', 'bv'] as $col) {
    $c = $conn->query("SHOW COLUMNS FROM franchies_add_product LIKE '$col'");
    if ($c && $c->num_rows === 0) {
        $conn->query("ALTER TABLE franchies_add_product ADD COLUMN $col DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER price");
    }
}

// 2. Ensure fr_products and inventory tables exist
$conn->query("
    CREATE TABLE IF NOT EXISTS `fr_products` (
        `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `franchise_id`   INT           NOT NULL,
        `franchise_code` VARCHAR(50)   DEFAULT NULL,
        `name`           VARCHAR(200)  NOT NULL,
        `category`       VARCHAR(120)  DEFAULT NULL,
        `brand`          VARCHAR(120)  DEFAULT NULL,
        `model`          VARCHAR(120)  DEFAULT NULL,
        `mileage`        VARCHAR(60)   DEFAULT NULL,
        `battery_spec`   VARCHAR(100)  DEFAULT NULL,
        `battery_warranty` VARCHAR(50) DEFAULT NULL,
        `image_path`     VARCHAR(255)  DEFAULT NULL,
        `price`          DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        `is_serialized`  TINYINT(1)    NOT NULL DEFAULT 1,
        `status`         VARCHAR(20)   NOT NULL DEFAULT 'Active',
        `qty_available`  INT NOT NULL DEFAULT 0,
        `qty_reserved`   INT NOT NULL DEFAULT 0,
        `qty_sold`       INT NOT NULL DEFAULT 0,
        `qty_returned`   INT NOT NULL DEFAULT 0,
        `qty_damaged`    INT NOT NULL DEFAULT 0,
        `created_by`     VARCHAR(120)  DEFAULT NULL,
        `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_franchise` (`franchise_id`),
        KEY `idx_name` (`franchise_id`, `name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
");

// Ensure mileage, battery_spec, battery_warranty columns exist in fr_products
foreach (['mileage' => 'VARCHAR(60) DEFAULT NULL', 'battery_spec' => 'VARCHAR(100) DEFAULT NULL', 'battery_warranty' => 'VARCHAR(50) DEFAULT NULL'] as $col => $colDef) {
    $chkCol = $conn->query("SHOW COLUMNS FROM `fr_products` LIKE '$col'");
    if ($chkCol && $chkCol->num_rows === 0) {
        $conn->query("ALTER TABLE `fr_products` ADD COLUMN `$col` $colDef AFTER `model`");
    }
}

// Ensure mileage, battery_spec, battery_warranty columns exist in franchies_add_product
foreach (['mileage' => 'VARCHAR(60) DEFAULT NULL', 'battery_spec' => 'VARCHAR(100) DEFAULT NULL', 'battery_warranty' => 'VARCHAR(50) DEFAULT NULL'] as $col => $colDef) {
    $chkCol = $conn->query("SHOW COLUMNS FROM `franchies_add_product` LIKE '$col'");
    if ($chkCol && $chkCol->num_rows === 0) {
        $conn->query("ALTER TABLE `franchies_add_product` ADD COLUMN `$col` $colDef AFTER `price`");
    }
}

$conn->query("
    CREATE TABLE IF NOT EXISTS `fr_product_fields` (
        `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `product_id`    BIGINT UNSIGNED NOT NULL,
        `franchise_id`  INT           NOT NULL,
        `field_key`     VARCHAR(60)   NOT NULL,
        `field_label`   VARCHAR(120)  NOT NULL,
        `field_type`    VARCHAR(20)   NOT NULL DEFAULT 'text',
        `is_identifier` TINYINT(1)    NOT NULL DEFAULT 0,
        `sort_order`    INT           NOT NULL DEFAULT 0,
        `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_field` (`product_id`, `field_key`),
        KEY `idx_product` (`product_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
");

// 3. The 16 Finalized EV Products with New Prices (+₹1,000), Mileage & Battery Specs
$productsToSeed = [
    [
        'name' => 'E-SCOOTER MAX VXI - 1.9 KW (Gel)',
        'price' => 66000.00,
        'quantity' => 100,
        'type' => 'repurchase',
        'status' => 'Active',
        'mileage' => '50-60 KM+',
        'battery_spec' => 'Gel 1.9 KW / 60V 32Ah',
        'battery_warranty' => '18 Months',
    ],
    [
        'name' => 'E-SCOOTER ACTIVE / CHETAAK - 2.7 KW',
        'price' => 116000.00,
        'quantity' => 100,
        'type' => 'repurchase',
        'status' => 'Active',
        'mileage' => '110-125 KM+',
        'battery_spec' => 'Lithium 2.7 KW / 60V 45Ah',
        'battery_warranty' => '36 Months',
    ],
    [
        'name' => 'E-SCOOTER MANIA VXI - 2.7 KW',
        'price' => 125500.00,
        'quantity' => 100,
        'type' => 'repurchase',
        'status' => 'Active',
        'mileage' => '110-125 KM+',
        'battery_spec' => 'Lithium 2.7 KW / 60V 45Ah',
        'battery_warranty' => '36 Months',
    ],
    [
        'name' => 'E-SCOOTER MANIA ZXI - 3.6 KW',
        'price' => 142000.00,
        'quantity' => 100,
        'type' => 'repurchase',
        'status' => 'Active',
        'mileage' => '135-150 KM+',
        'battery_spec' => 'Lithium 3.6 KW / 60V 60Ah',
        'battery_warranty' => '36 Months',
    ],
    [
        'name' => 'E-SCOOTER M2 VXI - 2.7 KW',
        'price' => 114000.00,
        'quantity' => 100,
        'type' => 'repurchase',
        'status' => 'Active',
        'mileage' => '110-125 KM+',
        'battery_spec' => 'Lithium 2.7 KW / 60V 45Ah',
        'battery_warranty' => '36 Months',
    ],
    [
        'name' => 'E-SCOOTER M2 ZXI - 3.6 KW',
        'price' => 129000.00,
        'quantity' => 100,
        'type' => 'repurchase',
        'status' => 'Active',
        'mileage' => '135-150 KM+',
        'battery_spec' => 'Lithium 3.6 KW / 60V 60Ah',
        'battery_warranty' => '36 Months',
    ],
    [
        'name' => 'E-BIKE KN1 - 3.2 KW',
        'price' => 195500.00,
        'quantity' => 100,
        'type' => 'repurchase',
        'status' => 'Active',
        'mileage' => '100-110 KM+',
        'battery_spec' => 'Lithium 3.2 KW / 72V 45Ah',
        'battery_warranty' => '36 Months',
    ],
    [
        'name' => 'E-BIKE V6 / V6 2.0 / JF - 4.3 KW',
        'price' => 206500.00,
        'quantity' => 100,
        'type' => 'repurchase',
        'status' => 'Active',
        'mileage' => '110-125 KM+',
        'battery_spec' => 'Lithium 4.3 KW / 72V 60Ah',
        'battery_warranty' => '36 Months',
    ],
    [
        'name' => 'E-RICKSHAW WITH SOLAR PANNEL - 5.1 KW',
        'price' => 220000.00,
        'quantity' => 100,
        'type' => 'repurchase',
        'status' => 'Active',
        'mileage' => '110-135 KM+',
        'battery_spec' => 'Lithium 5.1 KW / 51V 100Ah',
        'battery_warranty' => '36 Months',
    ],
    [
        'name' => 'E-LOADER WITH SOLAR PANNEL - 5.1 KW',
        'price' => 255000.00,
        'quantity' => 100,
        'type' => 'repurchase',
        'status' => 'Active',
        'mileage' => '90-120 KM+',
        'battery_spec' => 'Lithium 5.1 KW / 51V 100Ah',
        'battery_warranty' => '36 Months',
    ],
    [
        'name' => 'E-LOADER COVER BODY WITH SOLAR - 5.1 KW',
        'price' => 276000.00,
        'quantity' => 100,
        'type' => 'repurchase',
        'status' => 'Active',
        'mileage' => '100-150 KM+',
        'battery_spec' => 'Lithium 5.1 KW / 51V 100Ah',
        'battery_warranty' => '36 Months',
    ],
    [
        'name' => 'E-LOADER COVER BODY WITH SOLAR - 7.1 KW',
        'price' => 296000.00,
        'quantity' => 100,
        'type' => 'repurchase',
        'status' => 'Active',
        'mileage' => '150-200 KM+',
        'battery_spec' => 'Lithium 7.1 KW / 51V 140Ah',
        'battery_warranty' => '36 Months',
    ],
    [
        'name' => 'E-LOADER MINI E-LOADER - 5.1 KW',
        'price' => 141000.00,
        'quantity' => 100,
        'type' => 'repurchase',
        'status' => 'Active',
        'mileage' => '100-120 KM+',
        'battery_spec' => 'Lithium 5.1 KW / 51V 100Ah',
        'battery_warranty' => '36 Months',
    ],
    [
        'name' => 'E-AUTO L-3 VXI - 5.1 KW',
        'price' => 233000.00,
        'quantity' => 100,
        'type' => 'repurchase',
        'status' => 'Active',
        'mileage' => '90-100 KM+',
        'battery_spec' => 'Lithium 5.1 KW / 51V 100Ah',
        'battery_warranty' => '36 Months',
    ],
    [
        'name' => 'E-AUTO L-3 ZXI - 7.1 KW',
        'price' => 254000.00,
        'quantity' => 100,
        'type' => 'repurchase',
        'status' => 'Active',
        'mileage' => '125-145 KM+',
        'battery_spec' => 'Lithium 7.1 KW / 51V 140Ah',
        'battery_warranty' => '36 Months',
    ],
    [
        'name' => 'E-AUTO L-5 - 7.6 KW',
        'price' => 433000.00,
        'quantity' => 100,
        'type' => 'repurchase',
        'status' => 'Active',
        'mileage' => '135-150 KM+',
        'battery_spec' => 'Lithium 7.6 KW / 51V 150Ah',
        'battery_warranty' => '36 Months',
    ],
];

// Helper to parse Category and Model
function parseCategoryAndModel(string $fullName): array {
    $cat = 'E-Vehicle';
    if (stripos($fullName, 'E-SCOOTER') !== false) {
        $cat = 'E-Scooter';
    } elseif (stripos($fullName, 'E-BIKE') !== false) {
        $cat = 'E-Bike';
    } elseif (stripos($fullName, 'E-RICKSHAW') !== false) {
        $cat = 'E-Rickshaw';
    } elseif (stripos($fullName, 'E-LOADER') !== false) {
        $cat = 'E-Loader';
    } elseif (stripos($fullName, 'E-AUTO') !== false) {
        $cat = 'E-Auto';
    }

    $model = trim(preg_replace('/^E-(SCOOTER|BIKE|RICKSHAW|LOADER|AUTO)\s+/i', '', $fullName));
    if (strpos($model, ' - ') !== false) {
        $parts = explode(' - ', $model);
        $model = trim($parts[0]);
    }
    return [$cat, $model ?: 'Standard'];
}

// 4. Update/Insert Company Master (franchies_add_product)
foreach ($productsToSeed as $prod) {
    $name     = $prod['name'];
    $price    = (float)$prod['price'];
    $qty      = (int)$prod['quantity'];
    $type     = $prod['type'];
    $status   = $prod['status'];
    $mileage  = $prod['mileage'] ?? null;
    $bSpec    = $prod['battery_spec'] ?? null;
    $bWarr    = $prod['battery_warranty'] ?? null;

    $checkStmt = $conn->prepare("SELECT id FROM franchies_add_product WHERE product_name = ? LIMIT 1");
    $checkStmt->bind_param("s", $name);
    $checkStmt->execute();
    $exist = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();

    if ($exist) {
        $existingId = (int)$exist['id'];
        $upStmt = $conn->prepare("UPDATE franchies_add_product SET price = ?, status = 'Active', product_type = ?, mileage = ?, battery_spec = ?, battery_warranty = ? WHERE id = ?");
        $upStmt->bind_param("dssssi", $price, $type, $mileage, $bSpec, $bWarr, $existingId);
        $upStmt->execute();
        $upStmt->close();
    } else {
        $inStmt = $conn->prepare("INSERT INTO franchies_add_product (product_name, price, gst, bv, quantity, product_type, status, mileage, battery_spec, battery_warranty, created_at) VALUES (?, ?, 0.00, 0.00, ?, ?, ?, ?, ?, ?, NOW())");
        $inStmt->bind_param("sddsssss", $name, $price, $qty, $type, $status, $mileage, $bSpec, $bWarr);
        $inStmt->execute();
        $inStmt->close();
    }
}

// 5. Seed Showroom Catalog (fr_products) for all registered franchises
$franchises = [];
$frRes = $conn->query("SELECT id, franchise_code, franchies_id, name FROM franchies_holder WHERE status = 'active' OR status = 'Active'");
if ($frRes) {
    while ($f = $frRes->fetch_assoc()) {
        $franchises[] = [
            'id' => (int)$f['id'],
            'code' => !empty($f['franchise_code']) ? $f['franchise_code'] : $f['franchies_id'],
            'name' => $f['name']
        ];
    }
}

// Standard custom fields for electric vehicles
$stdFields = [
    ['key' => 'chassis_number', 'label' => 'Chassis Number', 'type' => 'text', 'is_identifier' => 1, 'order' => 1],
    ['key' => 'motor_number',   'label' => 'Motor Number',   'type' => 'text', 'is_identifier' => 1, 'order' => 2],
    ['key' => 'battery_number', 'label' => 'Battery Number', 'type' => 'text', 'is_identifier' => 1, 'order' => 3],
    ['key' => 'colour',         'label' => 'Colour',         'type' => 'text', 'is_identifier' => 0, 'order' => 4],
];

$frProductsAdded = 0;
$frProductsUpdated = 0;

foreach ($franchises as $fr) {
    $fid   = $fr['id'];
    $fcode = $fr['code'];

    foreach ($productsToSeed as $prod) {
        $name    = $prod['name'];
        $price   = (float)$prod['price'];
        $mileage = $prod['mileage'] ?? null;
        $bSpec   = $prod['battery_spec'] ?? null;
        $bWarr   = $prod['battery_warranty'] ?? null;
        list($cat, $model) = parseCategoryAndModel($name);

        $chk = $conn->prepare("SELECT id FROM fr_products WHERE franchise_id = ? AND name = ? LIMIT 1");
        $chk->bind_param("is", $fid, $name);
        $chk->execute();
        $pRow = $chk->get_result()->fetch_assoc();
        $chk->close();

        if ($pRow) {
            $pId = (int)$pRow['id'];
            $up = $conn->prepare("UPDATE fr_products SET price = ?, category = ?, model = ?, mileage = ?, battery_spec = ?, battery_warranty = ?, status = 'Active' WHERE id = ?");
            $up->bind_param("dsssssi", $price, $cat, $model, $mileage, $bSpec, $bWarr, $pId);
            $up->execute();
            $up->close();
            $frProductsUpdated++;
        } else {
            $ins = $conn->prepare("
                INSERT INTO fr_products 
                (franchise_id, franchise_code, name, category, brand, model, mileage, battery_spec, battery_warranty, price, is_serialized, status, qty_available, created_by, created_at)
                VALUES (?, ?, ?, ?, 'E-VELOCY', ?, ?, ?, ?, ?, 1, 'Active', 0, 'Company Master', NOW())
            ");
            $ins->bind_param("issssssssd", $fid, $fcode, $name, $cat, $model, $mileage, $bSpec, $bWarr, $price);
            $ins->execute();
            $pId = (int)$ins->insert_id;
            $ins->close();

            // Insert standard fields
            foreach ($stdFields as $f) {
                $fIns = $conn->prepare("
                    INSERT IGNORE INTO fr_product_fields 
                    (product_id, franchise_id, field_key, field_label, field_type, is_identifier, sort_order, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $fIns->bind_param("iisssii", $pId, $fid, $f['key'], $f['label'], $f['type'], $f['is_identifier'], $f['order']);
                $fIns->execute();
                $fIns->close();
            }

            $frProductsAdded++;
        }
    }
}

echo "SUCCESS: Showroom Products Synced for " . count($franchises) . " franchise(s). (Added: $frProductsAdded, Updated: $frProductsUpdated)\n";
