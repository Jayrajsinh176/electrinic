<?php
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

echo "=== Testing Step 4: Scooter Brand Promotion (25m & Models) ===\n";

$prefix = 'PROMOTEST_' . time() . '_';

// 1. Create test user 1
$u1 = $prefix . 'U1';
$conn->query("INSERT INTO signup_users (customer_id, full_name, mobile, status, active_status)
              VALUES ('{$u1}', 'Promo Test User 1', '9876543210', 'Active', 'Active')");

// 2. Fetch MAX VXI from catalog
$maxVxi = ind_get_vehicle_by_code($conn, 'MAX_VXI');
assert_test(!empty($maxVxi), "Fetched MAX VXI vehicle from catalog");

$vehId = (int)$maxVxi['id'];
$modelName = (string)$maxVxi['model_name'];
$bprFull = (float)$maxVxi['bpr_upfront_monthly']; // 2000.00
$monthsTotal = (int)$maxVxi['bpr_months']; // 25
$regNo = 'DL-01-TEST-1234';

// Insert promotion enrollment (Full Payment)
$supportPct = 50.00;
$ins = $conn->prepare("INSERT INTO ind_scooter_promotions 
    (customer_id, vehicle_id, vehicle_model, payment_option, registration_number, monthly_bpr_amount, support_percent, months_total, months_paid, status, created_at)
    VALUES (?, ?, ?, 'full', ?, ?, ?, ?, 0, 'active', NOW())");
$ins->bind_param('sissddi', $u1, $vehId, $modelName, $regNo, $bprFull, $supportPct, $monthsTotal);
$ins->execute();
$promoId1 = (int)$ins->insert_id;
$ins->close();

$p1 = $conn->query("SELECT * FROM ind_scooter_promotions WHERE id = {$promoId1}")->fetch_assoc();
assert_test(!empty($p1), "Promotion record created in ind_scooter_promotions");
assert_test($p1['payment_option'] === 'full', "Payment option is 'full'");
assert_test((float)$p1['monthly_bpr_amount'] === 2000.00, "Monthly BPR is ₹2,000.00 for MAX VXI full payment");
assert_test((int)$p1['months_total'] === 25, "Months total is 25 months");

// Test Digital Agreement Sign
$conn->query("UPDATE ind_scooter_promotions SET agreement_signed = 1, agreement_signed_at = NOW() WHERE id = {$promoId1}");
$p1Signed = $conn->query("SELECT agreement_signed, agreement_signed_at FROM ind_scooter_promotions WHERE id = {$promoId1}")->fetch_assoc();
assert_test((int)$p1Signed['agreement_signed'] === 1, "Digital agreement signed flag is 1");
assert_test(!empty($p1Signed['agreement_signed_at']), "Digital agreement signed timestamp is recorded");

// 3. Create test user 2 with Active Chetaak on Finance
$u2 = $prefix . 'U2';
$conn->query("INSERT INTO signup_users (customer_id, full_name, mobile, status, active_status)
              VALUES ('{$u2}', 'Promo Test User 2', '9876543211', 'Active', 'Active')");

$chetaak = ind_get_vehicle_by_code($conn, 'ACTIVE_CHETAAK_NOFIN');
$chetaakId = (int)$chetaak['id'];
$chetaakName = (string)$chetaak['model_name'];
$bprFin = (float)$chetaak['bpr_finance_monthly']; // 3400.00
$monthsTotal2 = (int)$chetaak['bpr_months']; // 25

$ins2 = $conn->prepare("INSERT INTO ind_scooter_promotions 
    (customer_id, vehicle_id, vehicle_model, payment_option, registration_number, monthly_bpr_amount, support_percent, months_total, months_paid, status, created_at)
    VALUES (?, ?, ?, 'finance', 'MH-02-TEST-5678', ?, ?, ?, 0, 'active', NOW())");
$ins2->bind_param('sisddi', $u2, $chetaakId, $chetaakName, $bprFin, $supportPct, $monthsTotal2);
$ins2->execute();
$promoId2 = (int)$ins2->insert_id;
$ins2->close();

$p2 = $conn->query("SELECT * FROM ind_scooter_promotions WHERE id = {$promoId2}")->fetch_assoc();
assert_test($p2['payment_option'] === 'finance', "Payment option is 'finance'");
assert_test((float)$p2['monthly_bpr_amount'] === 3400.00, "Monthly BPR is ₹3,400.00 for Active Chetaak on finance");
assert_test((int)$p2['months_total'] === 25, "Months total is 25 months");

// Clean up
$conn->query("DELETE FROM ind_scooter_promotions WHERE id IN ({$promoId1}, {$promoId2})");
$conn->query("DELETE FROM signup_users WHERE customer_id IN ('{$u1}', '{$u2}')");

echo "\nALL STEP 4 TESTS PASSED WITH 100% SUCCESS!\n";
