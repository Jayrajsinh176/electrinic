<?php
require_once __DIR__ . '/../member/config/db.php';

echo "=== Applying Step 1 Database Updates (Voucher 10 Months & Settings) ===\n";

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.\n");
}

// 1. Update income_settings for voucher validity
$stmt = $conn->prepare("INSERT INTO income_settings (setting_key, setting_value, description) 
                        VALUES ('ind_voucher_validity_months', '10', 'Voucher validity in months (10 months as per plan)')
                        ON DUPLICATE KEY UPDATE setting_value = '10', description = VALUES(description)");
$stmt->execute();
$stmt->close();
echo "[OK] Updated income_settings: ind_voucher_validity_months = 10\n";

// 2. Update ind_vouchers column default if table exists
$chk = $conn->query("SHOW TABLES LIKE 'ind_vouchers'");
if ($chk && $chk->num_rows > 0) {
    $conn->query("ALTER TABLE `ind_vouchers` MODIFY COLUMN `validity_months` INT NOT NULL DEFAULT 10");
    echo "[OK] Altered ind_vouchers: default validity_months = 10\n";
}

echo "=== Step 1 Database Updates Finished Successfully ===\n";

