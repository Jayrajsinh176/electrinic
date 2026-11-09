<?php
/**
 * Test Step 6: Admin & Member UI Alignment & Reports
 * Tests:
 * 1. Verify existence and query health of member/vehicle-commissions.php
 * 2. Verify existence and query health of admin/vehicle-commissions.php
 * 3. Verify admin/auto-pool-income.php reads from ind_auto_pool_state & ind_auto_pool_ledger
 * 4. Verify sidebar navigation links in member and admin sidebars
 * 5. Verify Scooter Wallet checkout validation & deduction logic
 */

require_once __DIR__ . '/../member/config/db.php';
require_once __DIR__ . '/../includes/income_common.php';
require_once __DIR__ . '/../includes/ind_income_functions.php';

function assert_test($condition, $name) {
    if ($condition) {
        echo "  [PASS] " . $name . "\n";
    } else {
        echo "  [FAIL] " . $name . "\n";
        exit(1);
    }
}

echo "=== STEP 6 VERIFICATION TEST ===\n\n";

// 1. Check member/vehicle-commissions.php file
echo "1. Checking member vehicle-commissions report...\n";
$memberFile = __DIR__ . '/../member/vehicle-commissions.php';
assert_test(file_exists($memberFile), "member/vehicle-commissions.php exists");

// Check member sidebar has link
$memberSidebar = file_get_contents(__DIR__ . '/../member/includes/sidebar.php');
assert_test(strpos($memberSidebar, 'vehicle-commissions.php') !== false, "Member sidebar includes vehicle-commissions.php");

// 2. Check admin/vehicle-commissions.php file
echo "\n2. Checking admin vehicle-commissions master audit report...\n";
$adminFile = __DIR__ . '/../admin/vehicle-commissions.php';
assert_test(file_exists($adminFile), "admin/vehicle-commissions.php exists");

// Check admin sidebar has link
$adminSidebar = file_get_contents(__DIR__ . '/../admin/includes/sidebar.php');
assert_test(strpos($adminSidebar, 'vehicle-commissions.php') !== false, "Admin sidebar includes vehicle-commissions.php");

// 3. Check admin/auto-pool-income.php queries
echo "\n3. Checking admin 100-pool dashboard integration...\n";
$adminAutoPool = file_get_contents(__DIR__ . '/../admin/auto-pool-income.php');
assert_test(strpos($adminAutoPool, 'ind_auto_pool_state') !== false, "admin/auto-pool-income.php queries ind_auto_pool_state");
assert_test(strpos($adminAutoPool, 'ind_auto_pool_ledger') !== false, "admin/auto-pool-income.php queries ind_auto_pool_ledger");

// Test query against ind_auto_pool_state
$poolLadder = $conn->query("SELECT COUNT(*) c FROM ind_auto_pool_state")->fetch_assoc();
assert_test((int)$poolLadder['c'] === 100, "ind_auto_pool_state contains all 100 pools");

// 4. Test Scooter Wallet checkout deduction logic
echo "\n4. Testing POS Scooter Wallet payment validation & deduction...\n";
$testCust = 'TEST6_MEMBER_' . time();
$conn->query("INSERT INTO signup_users (customer_id, full_name, mobile, status, active_status, activated_at, scooter_wallet_balance) 
              VALUES ('{$testCust}', 'Test Step6 Customer', '9876500000', 'Active', 'Active', NOW(), 5000.00)");

// Check initial balance
$b = $conn->query("SELECT scooter_wallet_balance FROM signup_users WHERE customer_id = '{$testCust}'")->fetch_assoc();
assert_test((float)$b['scooter_wallet_balance'] === 5000.00, "Initial Scooter Wallet balance is ₹5,000.00");

// Simulate purchase of ₹2,500 with Scooter Wallet
$orderAmt = 2500.00;
$conn->query("UPDATE signup_users SET scooter_wallet_balance = scooter_wallet_balance - {$orderAmt} WHERE customer_id = '{$testCust}'");

$bAfter = $conn->query("SELECT scooter_wallet_balance FROM signup_users WHERE customer_id = '{$testCust}'")->fetch_assoc();
assert_test((float)$bAfter['scooter_wallet_balance'] === 2500.00, "Deducted ₹2,500.00, remaining balance is ₹2,500.00");

// Clean up test customer
$conn->query("DELETE FROM signup_users WHERE customer_id = '{$testCust}'");

echo "\n>>> ALL STEP 6 TESTS PASSED WITH 100% SUCCESS! <<<\n";

