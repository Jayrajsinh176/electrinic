<?php
/**
 * Test Suite: Brand Promotion Packages & 10-Root Affiliate Marketing Flow
 */
require_once __DIR__ . '/../member/config/db.php';
require_once __DIR__ . '/../includes/income_common.php';
require_once __DIR__ . '/../includes/bp_package_functions.php';

echo "=================================================================\n";
echo "TEST: BRAND PROMOTION PACKAGES & 10-ROOT AFFILIATE (10-MONTH MODEL)\n";
echo "=================================================================\n\n";

$pass = 0;
$fail = 0;

function assert_test(string $desc, bool $condition) {
    global $pass, $fail;
    if ($condition) {
        echo " [PASS] {$desc}\n";
        $pass++;
    } else {
        echo " [FAIL] {$desc}\n";
        $fail++;
    }
}

// 1. Check Packages Table
$packages = bp_get_active_packages($conn);
assert_test("Found 4 active Brand Promotion packages", count($packages) === 4);

$pkgMap = [];
foreach ($packages as $p) {
    $pkgMap[$p['package_code']] = $p;
}

assert_test("Silver Plan exists with ₹10,000 price & ₹1,000 monthly for 10 months", 
    isset($pkgMap['BP-10K']) && (float)$pkgMap['BP-10K']['price'] === 10000.00 && (float)$pkgMap['BP-10K']['monthly_return'] === 1000.00 && (int)$pkgMap['BP-10K']['duration_months'] === 10);

assert_test("Gold Plan exists with ₹50,000 price & ₹5,000 monthly for 10 months", 
    isset($pkgMap['BP-50K']) && (float)$pkgMap['BP-50K']['price'] === 50000.00 && (float)$pkgMap['BP-50K']['monthly_return'] === 5000.00 && (int)$pkgMap['BP-50K']['duration_months'] === 10);

assert_test("Diamond Plan exists with ₹1,00,000 price & ₹10,000 monthly for 10 months", 
    isset($pkgMap['BP-1L']) && (float)$pkgMap['BP-1L']['price'] === 100000.00 && (float)$pkgMap['BP-1L']['monthly_return'] === 10000.00 && (int)$pkgMap['BP-1L']['duration_months'] === 10);

assert_test("Platinum Plan exists with ₹2,00,000 price & ₹20,000 monthly for 10 months", 
    isset($pkgMap['BP-2L']) && (float)$pkgMap['BP-2L']['price'] === 200000.00 && (float)$pkgMap['BP-2L']['monthly_return'] === 20000.00 && (int)$pkgMap['BP-2L']['duration_months'] === 10);

// 2. Setup Test Members (Chain of 3 sponsors: TestUpline2 -> TestSponsor1 -> TestBuyer)
$testUpline2 = 'BPTEST_UP2';
$testSponsor1 = 'BPTEST_SP1';
$testBuyer    = 'BPTEST_BUY';

// Cleanup previous test data
$conn->query("DELETE FROM signup_users WHERE customer_id IN ('{$testUpline2}', '{$testSponsor1}', '{$testBuyer}')");
$conn->query("DELETE FROM ind_bp_subscriptions WHERE customer_id IN ('{$testUpline2}', '{$testSponsor1}', '{$testBuyer}')");
$conn->query("DELETE FROM ind_bp_monthly_payouts WHERE customer_id IN ('{$testUpline2}', '{$testSponsor1}', '{$testBuyer}')");
$conn->query("DELETE FROM ind_bp_root_payouts WHERE subscriber_id IN ('{$testBuyer}') OR earner_id IN ('{$testUpline2}', '{$testSponsor1}')");
$conn->query("DELETE FROM direct_sponsor_income WHERE sponsor_id IN ('{$testSponsor1}')");

// Create Upline 2 (needs 2 directs to qualify for Root 2)
$conn->query("INSERT INTO signup_users (customer_id, full_name, referral_id, registration_status, wallet_balance, active_status) 
              VALUES ('{$testUpline2}', 'Upline Root 2', '', 'Active', 0.00, 'active')");

// Create Sponsor 1 (sponsored by Upline 2, has 1 direct)
$conn->query("INSERT INTO signup_users (customer_id, full_name, referral_id, registration_status, wallet_balance, active_status) 
              VALUES ('{$testSponsor1}', 'Direct Sponsor 1', '{$testUpline2}', 'Active', 0.00, 'active')");

// Create dummy extra direct for Upline 2 so Upline 2 has 2 active directs
$conn->query("INSERT INTO signup_users (customer_id, full_name, referral_id, registration_status, active_status) 
              VALUES ('BPTEST_DUMMY', 'Dummy Direct', '{$testUpline2}', 'Active', 'active')");

// Create Buyer (sponsored by Sponsor 1, starts with ₹60,000 in wallet)
$conn->query("INSERT INTO signup_users (customer_id, full_name, referral_id, registration_status, wallet_balance, active_status) 
              VALUES ('{$testBuyer}', 'Test Buyer', '{$testSponsor1}', 'Active', 60000.00, 'active')");

// 3. Test Subscribe from Wallet
echo "\nTesting Wallet Subscription...\n";
$goldPkgId = (int)$pkgMap['BP-50K']['id'];
$subRes = bp_subscribe_from_wallet($conn, $testBuyer, $goldPkgId);
if (!$subRes['ok']) {
    echo "  DEBUG subRes error: " . ($subRes['message'] ?? 'unknown') . "\n";
}

assert_test("Wallet subscription succeeded", $subRes['ok'] === true);
$subId = (int)$subRes['subscription_id'];

// Check buyer wallet deducted (60k - 50k = 10k)
$buyerBal = (float)$conn->query("SELECT wallet_balance FROM signup_users WHERE customer_id = '{$testBuyer}'")->fetch_assoc()['wallet_balance'];
assert_test("Buyer wallet deducted by ₹50,000 (Remaining: ₹10,000)", $buyerBal === 10000.00);

// Check 5% Direct Bonus credited to Sponsor 1 (5% of ₹50,000 = ₹2,500)
$spRow = $conn->query("SELECT wallet_balance, scooter_wallet_balance FROM signup_users WHERE customer_id = '{$testSponsor1}'")->fetch_assoc();
$spTotalEarned = (float)$spRow['wallet_balance'] + (float)$spRow['scooter_wallet_balance'];
assert_test("Direct Sponsor 1 received 5% bonus (₹2,500 total credit)", $spTotalEarned === 2500.00);

// 4. Test Monthly Payout & 10-Root Affiliate Run
echo "\nTesting Monthly Payout & 10-Root Affiliate Execution...\n";
$cronRes = bp_process_monthly_payouts($conn, '2026-11', true);

assert_test("Monthly payout processed subscriber", $cronRes['subscribers_paid'] >= 1);
assert_test("Subscriber received monthly payout in ind_bp_monthly_payouts", 
    (int)$conn->query("SELECT COUNT(*) as c FROM ind_bp_monthly_payouts WHERE subscription_id = {$subId} AND month_number = 1")->fetch_assoc()['c'] === 1);

// Check 10-Root Affiliate records for Root 1 (10% of ₹5,000 = ₹500) and Root 2 (8% of ₹5,000 = ₹400)
$root1Row = $conn->query("SELECT * FROM ind_bp_root_payouts WHERE subscription_id = {$subId} AND earner_id = '{$testSponsor1}' AND root_level = 1")->fetch_assoc();
assert_test("Root 1 (Sponsor 1) received 10% affiliate commission (₹500.00)", 
    $root1Row && (float)$root1Row['commission_amount'] === 500.00 && $root1Row['status'] === 'paid');

$root2Row = $conn->query("SELECT * FROM ind_bp_root_payouts WHERE subscription_id = {$subId} AND earner_id = '{$testUpline2}' AND root_level = 2")->fetch_assoc();
assert_test("Root 2 (Upline 2) received 8% affiliate commission (₹400.00)", 
    $root2Row && (float)$root2Row['commission_amount'] === 400.00 && $root2Row['status'] === 'paid');

// 5. Test Double Payout Guard (Running cron again for same month shouldn't duplicate)
echo "\nTesting Double-Payout Prevention...\n";
$cronRes2 = bp_process_monthly_payouts($conn, '2026-11', true);
$payoutCount = (int)$conn->query("SELECT COUNT(*) as c FROM ind_bp_monthly_payouts WHERE subscription_id = {$subId} AND month_number = 1")->fetch_assoc()['c'];
assert_test("Double-payout prevented (Installment 1 paid exactly once)", $payoutCount === 1);

// 6. Clean up test records
$conn->query("DELETE FROM signup_users WHERE customer_id IN ('{$testUpline2}', '{$testSponsor1}', '{$testBuyer}', 'BPTEST_DUMMY')");
$conn->query("DELETE FROM ind_bp_subscriptions WHERE customer_id IN ('{$testUpline2}', '{$testSponsor1}', '{$testBuyer}')");
$conn->query("DELETE FROM ind_bp_monthly_payouts WHERE customer_id IN ('{$testUpline2}', '{$testSponsor1}', '{$testBuyer}')");
$conn->query("DELETE FROM ind_bp_root_payouts WHERE subscriber_id IN ('{$testBuyer}') OR earner_id IN ('{$testUpline2}', '{$testSponsor1}')");
$conn->query("DELETE FROM direct_sponsor_income WHERE sponsor_id IN ('{$testSponsor1}')");

echo "\n=================================================================\n";
echo "SUMMARY: {$pass} PASSED, {$fail} FAILED\n";
echo "=================================================================\n";
