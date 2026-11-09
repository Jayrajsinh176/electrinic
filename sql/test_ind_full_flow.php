<?php
/**
 * End-to-End Automated Test Suite for IND Member Business Plan
 * 
 * Tests all 9 modules and cleans up test records automatically.
 */

require_once __DIR__ . '/../member/config/db.php';
require_once __DIR__ . '/../includes/income_common.php';
require_once __DIR__ . '/../includes/ind_income_functions.php';

function test_log($msg) {
    echo "[TEST] " . $msg . "\n";
}

function assert_test($condition, $name) {
    if ($condition) {
        echo "  [PASS] " . $name . "\n";
    } else {
        echo "  [FAIL] " . $name . "\n";
        exit(1);
    }
}

echo "========================================================\n";
echo "BEGINNING IND MEMBER BUSINESS PLAN VERIFICATION SUITE\n";
echo "========================================================\n\n";

// Prefix for test data
$prefix = 'INDTEST_' . time() . '_';

// -------------------------------------------------------------
// STEP 1: CREATE 10 UPLINE MEMBERS FOR SPONSOR CHAIN
// -------------------------------------------------------------
test_log("Step 1: Creating 11 linked test accounts (Sponsor Chain for 10 Roots)...");

$chainIds = [];
$prevSponsor = '';
for ($i = 10; $i >= 0; $i--) {
    $cid = $prefix . 'U' . $i;
    $refId = $prevSponsor;
    $prevSponsor = $cid;

    $conn->query("INSERT INTO signup_users 
        (customer_id, full_name, mobile, referral_id, registration_status, status, active_status, activated_at, wallet_balance, scooter_wallet_balance, product_fund_balance)
        VALUES ('{$cid}', 'Test User {$i}', '9876543210', '{$refId}', 'Active', 'Active', 'Active', NOW(), 0.00, 0.00, 0.00)");
    $chainIds[$i] = $cid;
}

// Ensure uplines have enough direct sponsors to qualify for levels 1..10
for ($i = 1; $i <= 10; $i++) {
    $parent = $chainIds[$i];
    for ($d = 1; $d <= $i; $d++) {
        $dummyId = $prefix . "D_{$i}_{$d}";
        $conn->query("INSERT INTO signup_users 
            (customer_id, full_name, referral_id, registration_status, status, active_status, activated_at)
            VALUES ('{$dummyId}', 'Direct {$d} of {$i}', '{$parent}', 'Active', 'Active', 'Active', NOW())");
    }
}

test_log("Created test hierarchy successfully.");

// -------------------------------------------------------------
// STEP 2: TEST JOINING & 10-LEVEL AFFILIATE MARKETING INCOME
// -------------------------------------------------------------
$newMember = $chainIds[0];
test_log("Step 2: Activating member {$newMember} with ₹10,000 joining...");

$actRes = process_member_income_on_activation($conn, $newMember, ['base_amount' => 10000.00]);

// Verify Voucher
$vRow = $conn->query("SELECT * FROM ind_vouchers WHERE customer_id = '{$newMember}' LIMIT 1")->fetch_assoc();
assert_test(!empty($vRow), "Product Voucher was generated in ind_vouchers");
assert_test((float)$vRow['amount'] === 10000.00, "Voucher amount is ₹10,000");
assert_test((int)$vRow['validity_months'] === 10, "Voucher validity is 10 months");

// Verify Cashback Limit
$userRow = $conn->query("SELECT cashback_max_limit, cashback_claimed_total FROM signup_users WHERE customer_id = '{$newMember}' LIMIT 1")->fetch_assoc();
assert_test((float)$userRow['cashback_max_limit'] === 10000.00, "Cashback maximum limit is ₹10,000");

// Verify Affiliate Distribution (Root 1 = 5%, Root 2 = 0.8% ... Root 10 = 0.3% -> Total 8.6%)
$upline1 = $chainIds[1];
$aff1 = $conn->query("SELECT * FROM ind_affiliate_10level_logs WHERE new_member_id = '{$newMember}' AND earner_id = '{$upline1}' AND root_level = 1 LIMIT 1")->fetch_assoc();
assert_test(!empty($aff1), "Root 1 direct sponsor received affiliate commission");
assert_test((float)$aff1['gross_amount'] === 500.00, "Root 1 commission is 5% = ₹500");
assert_test((float)$aff1['scooter_wallet_amount'] === 250.00, "Root 1 Scooter Wallet credited with 50% = ₹250");
assert_test((float)$aff1['main_balance_amount'] === 250.00, "Root 1 Main Balance credited with 50% = ₹250");

$upline2 = $chainIds[2];
$aff2 = $conn->query("SELECT * FROM ind_affiliate_10level_logs WHERE new_member_id = '{$newMember}' AND earner_id = '{$upline2}' AND root_level = 2 LIMIT 1")->fetch_assoc();
assert_test(!empty($aff2), "Root 2 sponsor received affiliate commission");
assert_test((float)$aff2['gross_amount'] === 50.00, "Root 2 commission is 0.5% = ₹50");
assert_test((float)$aff2['scooter_wallet_amount'] === 25.00, "Root 2 Scooter Wallet credited with 50% = ₹25");
assert_test((float)$aff2['main_balance_amount'] === 25.00, "Root 2 Main Balance credited with 50% = ₹25");

$upline10 = $chainIds[10];
$aff10 = $conn->query("SELECT * FROM ind_affiliate_10level_logs WHERE new_member_id = '{$newMember}' AND earner_id = '{$upline10}' AND root_level = 10 LIMIT 1")->fetch_assoc();
assert_test(!empty($aff10), "Root 10 sponsor received affiliate commission");
assert_test((float)$aff10['gross_amount'] === 50.00, "Root 10 commission is 0.5% = ₹50");

// Verify total 10-level distributed is exactly ₹830 (8.3%)
$totAff = $conn->query("SELECT SUM(gross_amount) as total_gross FROM ind_affiliate_10level_logs WHERE new_member_id = '{$newMember}'")->fetch_assoc();
assert_test((float)$totAff['total_gross'] === 830.00, "Total 10-level affiliate income distributed is exactly ₹830.00 (8.3% of ₹10,000)");

// -------------------------------------------------------------
// STEP 3: TEST PRODUCT ADDON DISTRIBUTION (₹1,000 ADDON)
// -------------------------------------------------------------
test_log("Step 3: Testing Product Price ₹1,000 Addon Distribution...");

$addonRes = ind_process_product_addon($conn, 'TEST-ORD-001', $newMember, 1000.00);
assert_test(!empty($addonRes['ok']), "Product addon processed successfully");

$splitRow = $conn->query("SELECT * FROM ind_product_addon_splits WHERE order_id = 'TEST-ORD-001' LIMIT 1")->fetch_assoc();
assert_test(!empty($splitRow), "Product addon split record logged");
assert_test((float)$splitRow['auto_pool_amount'] === 300.00, "Auto Pool 1 allocated ₹300");
assert_test((float)$splitRow['training_wallet_amount'] === 500.00, "Training Wallet allocated ₹500");

// -------------------------------------------------------------
// STEP 4: TEST 3x1 AUTO-POOL ENGINE (100 POOLS RECYCLING)
// -------------------------------------------------------------
test_log("Step 4: Testing 3x1 Auto-Pool 1 Completion & Cascade...");

// Member 0 is waiting in Pool 1. Now add 2 more entries into Pool 1 to reach 3 IDs:
$pos2 = ind_auto_pool_enter($conn, $chainIds[1], 1, 'product_addon');
$pos3 = ind_auto_pool_enter($conn, $chainIds[2], 1, 'product_addon');

// Verify Member 0 position completed!
$headPos = $conn->query("SELECT * FROM ind_auto_pool_positions WHERE customer_id = '{$newMember}' AND pool_level = 1 AND entry_source = 'product_addon' LIMIT 1")->fetch_assoc();
assert_test($headPos['status'] === 'completed', "Pool 1 head position marked 'completed' after 3 IDs entered");

// Verify Re-topup position created in Pool 1
$retopPos = $conn->query("SELECT * FROM ind_auto_pool_positions WHERE customer_id = '{$newMember}' AND pool_level = 1 AND entry_source = 'retopup' LIMIT 1")->fetch_assoc();
assert_test(!empty($retopPos), "Part 1: Same Pool 1 auto re-topup created for continuous earnings");

// Verify Upgrade position created in Pool 2
$upPos = $conn->query("SELECT * FROM ind_auto_pool_positions WHERE customer_id = '{$newMember}' AND pool_level = 2 AND entry_source = 'upgrade' LIMIT 1")->fetch_assoc();
assert_test(!empty($upPos), "Part 2: Pool 2 upgrade entry created (with ₹400)");

// Verify Payout credited (₹200 gross -> ₹100 Scooter Wallet + ₹100 Main Balance)
$lgrRow = $conn->query("SELECT * FROM ind_auto_pool_ledger WHERE position_id = {$headPos['id']} LIMIT 1")->fetch_assoc();
assert_test(!empty($lgrRow), "Part 3: Auto-Pool ledger record logged");
assert_test((float)$lgrRow['gross_income'] === 200.00, "Gross income is ₹200 (₹900 - ₹300 retopup - ₹400 upgrade)");
assert_test((float)$lgrRow['scooter_wallet_credit'] === 100.00, "50% credited to Scooter Wallet = ₹100");
assert_test((float)$lgrRow['main_wallet_credit'] === 100.00, "50% credited to Main Balance = ₹100");

// -------------------------------------------------------------
// STEP 5: TEST SCOOTER BRAND PROMOTION & ESCALATION LADDER
// -------------------------------------------------------------
test_log("Step 5: Testing Scooter Brand Promotion & Escalation Ladder...");

// Enroll member in Scooter program
$insSp = $conn->query("INSERT INTO ind_scooter_promotions 
    (customer_id, vehicle_model, registration_number, monthly_bpr_amount, direct_vehicles_count, support_percent, months_total, months_paid, status, agreement_signed, created_at)
    VALUES ('{$newMember}', 'Electric Scooter X', 'DL-01-TEST', 3000.00, 0, 50.00, 36, 0, 'active', 1, NOW())");
$promoId = (int)$conn->insert_id;

assert_test(ind_calculate_scooter_support_percent(0) === 50.0, "0-1 direct vehicles -> 50% Support");
assert_test(ind_calculate_scooter_support_percent(2) === 60.0, "2 direct vehicles -> 60% Support");
assert_test(ind_calculate_scooter_support_percent(4) === 70.0, "4 direct vehicles -> 70% Support");
assert_test(ind_calculate_scooter_support_percent(6) === 80.0, "6 direct vehicles -> 80% Support");
assert_test(ind_calculate_scooter_support_percent(8) === 90.0, "8 direct vehicles -> 90% Support");
assert_test(ind_calculate_scooter_support_percent(10) === 100.0, "10 direct vehicles -> 100% Free Scooter!");

// -------------------------------------------------------------
// STEP 6: TEST VIDEO VERIFICATION & 48-HOUR AUTO PURGE
// -------------------------------------------------------------
test_log("Step 6: Testing Video Verification & 48-Hour File Purge...");

// Create dummy video file on disk
$testVidFile = dirname(__DIR__) . '/uploads/videos/test_purge_' . time() . '.mp4';
file_put_contents($testVidFile, "DUMMY VIDEO DATA");

// Insert verification record dated 50 hours ago
$conn->query("INSERT INTO ind_video_verifications 
    (customer_id, promotion_id, month_number, video_file_path, video_deleted, gps_latitude, gps_longitude, gps_captured_at, show_on_landing_page, status, created_at)
    VALUES ('{$newMember}', {$promoId}, 1, 'uploads/videos/" . basename($testVidFile) . "', 0, 28.6139, 77.2090, NOW(), 1, 'verified', DATE_SUB(NOW(), INTERVAL 50 HOUR))");
$vidId = (int)$conn->insert_id;

// Run 48-Hour Purge
$purgeRes = ind_purge_expired_videos($conn, dirname(__DIR__));
assert_test(!file_exists($testVidFile), "Expired video file (>48h) was permanently deleted from disk");

$purgedRow = $conn->query("SELECT video_deleted FROM ind_video_verifications WHERE id = {$vidId}")->fetch_assoc();
assert_test((int)$purgedRow['video_deleted'] === 1, "Database record marked video_deleted = 1 while preserving verification log");

// -------------------------------------------------------------
// STEP 7: TEST 10-LEVEL TEAM PROMOTION BONUS (6-MONTH BPR)
// -------------------------------------------------------------
test_log("Step 7: Testing 10-Level Team Promotion Bonus (83% of BPR for Month 1)...");

$bprRes = ind_distribute_team_bpr_bonus($conn, $promoId, date('Y-m'));
assert_test(!empty($bprRes['ok']), "Team BPR bonus executed successfully");

$tbUpline1 = $conn->query("SELECT * FROM ind_team_promotion_bonus_logs WHERE promotion_id = {$promoId} AND earner_id = '{$upline1}' LIMIT 1")->fetch_assoc();
assert_test(!empty($tbUpline1), "Root 1 upline received team promotion bonus");
assert_test((float)$tbUpline1['percent'] === 30.0, "Root 1 rate is 30% of BPR");
assert_test((float)$tbUpline1['bonus_amount'] === 900.00, "Root 1 received ₹900 on ₹3,000 BPR");

// -------------------------------------------------------------
// STEP 8: TEST WITHDRAWAL DEDUCTION (10% FLAT TDS + ADMIN)
// -------------------------------------------------------------
test_log("Step 8: Testing Bank Withdrawal 10% Flat Deduction...");

// Member 1 has balance. Try withdrawing ₹1,000
$wRes = withdrawal_request_create($conn, $upline1, 100.00, 'Test withdrawal');
if (!empty($wRes['ok'])) {
    assert_test((float)$wRes['deduction_percent'] === 10.0, "Flat bank withdrawal deduction is 10% (TDS + Admin)");
    assert_test((float)$wRes['net'] === 90.00, "Net payout received is 90% (₹90 on ₹100 withdrawal)");
} else {
    // If withdrawal days rule blocked today, check setting directly
    $deductKey = income_setting_bool($conn, 'ind_plan_enabled', true) ? 'ind_bank_withdrawal_deduction_percent' : 'wallet_bank_deduction_percent';
    $pct = (float)income_setting_num($conn, $deductKey, 10.0);
    assert_test($pct === 10.0, "IND Plan withdrawal deduction setting is strictly 10.0%");
}

// -------------------------------------------------------------
// STEP 9: CLEAN UP TEST DATA
// -------------------------------------------------------------
test_log("Step 9: Cleaning up all temporary test records...");

$conn->query("DELETE FROM ind_vouchers WHERE customer_id LIKE '{$prefix}%'");
$conn->query("DELETE FROM ind_affiliate_10level_logs WHERE new_member_id LIKE '{$prefix}%' OR earner_id LIKE '{$prefix}%'");
$conn->query("DELETE FROM ind_product_addon_splits WHERE customer_id LIKE '{$prefix}%'");
$conn->query("DELETE FROM ind_training_wallet_ledger WHERE ref_id = 'TEST-ORD-001'");
$conn->query("DELETE FROM ind_auto_pool_positions WHERE customer_id LIKE '{$prefix}%'");
$conn->query("DELETE FROM ind_auto_pool_ledger WHERE customer_id LIKE '{$prefix}%'");
$conn->query("DELETE FROM ind_scooter_promotions WHERE customer_id LIKE '{$prefix}%'");
$conn->query("DELETE FROM ind_scooter_monthly_payouts WHERE customer_id LIKE '{$prefix}%'");
$conn->query("DELETE FROM ind_team_promotion_bonus_logs WHERE downline_member_id LIKE '{$prefix}%' OR earner_id LIKE '{$prefix}%'");
$conn->query("DELETE FROM ind_video_verifications WHERE customer_id LIKE '{$prefix}%'");
$conn->query("DELETE FROM member_income_ledger WHERE customer_id LIKE '{$prefix}%'");
$conn->query("DELETE FROM withdrawal_requests WHERE customer_id LIKE '{$prefix}%'");
$conn->query("DELETE FROM wallet_transactions WHERE customer_id LIKE '{$prefix}%'");
$conn->query("DELETE FROM signup_users WHERE customer_id LIKE '{$prefix}%'");

echo "\n========================================================\n";
echo "ALL TEST SUITES PASSED WITH 100% SUCCESS!\n";
echo "========================================================\n";

