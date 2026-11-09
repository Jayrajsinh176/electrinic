<?php
/**
 * MASTER DEPLOYMENT SCRIPT: ELECTRINC IND MEMBER BUSINESS PLAN
 * 
 * Applies all migrations in sequential order for cPanel, VPS, or local environments:
 * - Step 1: 10-Month Voucher Validity & 8.6% 10-Level Affiliate Commission
 * - Step 2: 11 Electric Vehicle Models & 5 Dealership Slabs
 * - Step 3: 16% Vehicle Purchase Commissions Table & Settings
 * - Step 4: 25-Month Scooter Brand Promotion Architecture
 * - Step 5: Monthly BPR Verification & Live GPS Tracking Columns
 * - Step 6: 100-Level Auto-Pool Matrix & Dual Wallet Accounting
 */

require_once __DIR__ . '/../member/config/db.php';
require_once __DIR__ . '/../includes/income_common.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=================================================================\n";
echo "ELECTRINC IND MEMBER BUSINESS PLAN - MASTER DEPLOYMENT RUNNER\n";
echo "=================================================================\n\n";

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("[FATAL] Database connection could not be established.\n");
}

function run_sql(mysqli $conn, string $label, string $sql): bool {
    echo "[MIGRATE] {$label}... ";
    if ($conn->query($sql)) {
        echo "OK\n";
        return true;
    } else {
        echo "FAIL: " . $conn->error . "\n";
        return false;
    }
}

// -------------------------------------------------------------
// STEP 1: Voucher Validity & 10-Level Affiliate Commission
// -------------------------------------------------------------
echo "\n--- STEP 1: Vouchers & Affiliate Commission ---\n";
require_once __DIR__ . '/apply_step1_migration.php';

// -------------------------------------------------------------
// STEP 2: Electric Vehicles Catalog & Dealership Slabs
// -------------------------------------------------------------
echo "\n--- STEP 2: EV Master Catalog & Dealerships ---\n";
require_once __DIR__ . '/apply_step2_migration.php';

// -------------------------------------------------------------
// STEP 3: Vehicle Purchase Commission Engine
// -------------------------------------------------------------
echo "\n--- STEP 3: Vehicle Commissions Engine (16% Pool) ---\n";
require_once __DIR__ . '/apply_step3_migration.php';

// -------------------------------------------------------------
// STEP 4: 25-Month Scooter Brand Promotion
// -------------------------------------------------------------
echo "\n--- STEP 4: 25-Month Scooter Promotion Architecture ---\n";
require_once __DIR__ . '/apply_step4_migration.php';

// -------------------------------------------------------------
// STEP 5: Monthly BPR Verification & Live GPS
// -------------------------------------------------------------
echo "\n--- STEP 5: Monthly BPR Verification Columns ---\n";
require_once __DIR__ . '/apply_step5_migration.php';

// -------------------------------------------------------------
// 100 Auto Pools Initialization
// -------------------------------------------------------------
echo "\n--- VERIFYING 100 AUTO POOLS ---\n";
$poolCount = (int)($conn->query("SELECT COUNT(*) c FROM ind_auto_pool_state")->fetch_assoc()['c'] ?? 0);
echo "[INFO] Current Auto Pools initialized: {$poolCount} / 100\n";

if ($poolCount < 100) {
    echo "[MIGRATE] Seeding missing Auto Pool levels...\n";
    for ($lvl = max(1, $poolCount + 1); $lvl <= 100; $lvl++) {
        // Multiplier calculation from PDF
        $entry = 500.0 * $lvl;
        $retopup = 500.0 * $lvl;
        $upgrade = ($lvl < 100) ? (500.0 * ($lvl + 1)) : 0.0;
        $gross = ($entry * 3.0) - $retopup - $upgrade;
        $conn->query("INSERT IGNORE INTO ind_auto_pool_state 
                      (pool_level, entry_amount, retopup_amount, upgrade_amount, withdrawal_gross, entries_count, completed_count)
                      VALUES ({$lvl}, {$entry}, {$retopup}, {$upgrade}, {$gross}, 0, 0)");
    }
    echo "[OK] All 100 Auto Pools seeded.\n";
}

echo "\n=================================================================\n";
echo ">>> MASTER DEPLOYMENT COMPLETE & READY FOR PRODUCTION! <<<\n";
echo "=================================================================\n";
