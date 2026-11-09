<?php
require_once __DIR__ . '/../member/config/db.php';
require_once __DIR__ . '/../includes/ind_income_functions.php';

function assert_test($condition, $name) {
    if ($condition) {
        echo "  [PASS] " . $name . "\n";
    } else {
        echo "  [FAIL] " . $name . "\n";
        exit(1);
    }
}

echo "=== Testing Step 2 Electric Vehicle Master Catalog & Helpers ===\n";

// 1. All vehicles
$all = ind_get_active_vehicles($conn);
assert_test(count($all) === 11, "Found 11 active vehicles in catalog");

// 2. BPR vehicles
$bpr = ind_get_active_vehicles($conn, null, true);
assert_test(count($bpr) === 4, "Found 4 BPR-eligible vehicle variants");

// 3. Check MAX VXI
$maxVxi = ind_get_vehicle_by_code($conn, 'MAX_VXI');
assert_test(!empty($maxVxi), "Fetched MAX VXI by code");
assert_test((float)$maxVxi['vehicle_price'] === 66000.00, "MAX VXI price is ₹66,000");
assert_test((float)$maxVxi['bpr_upfront_monthly'] === 2000.00, "MAX VXI upfront monthly BPR is ₹2,000");
assert_test((float)$maxVxi['bpr_finance_monthly'] === 1700.00, "MAX VXI finance monthly BPR is ₹1,700");
assert_test((int)$maxVxi['bpr_months'] === 25, "MAX VXI BPR duration is 25 months");

// 4. Check ACTIVE CHETAAK (Without Finance)
$chetaakNoFin = ind_get_vehicle_by_code($conn, 'ACTIVE_CHETAAK_NOFIN');
assert_test(!empty($chetaakNoFin), "Fetched ACTIVE CHETAAK (Without Finance)");
assert_test((float)$chetaakNoFin['vehicle_price'] === 127000.00, "Price is ₹1,27,000");
assert_test((float)$chetaakNoFin['bpr_upfront_monthly'] === 4000.00, "Upfront BPR is ₹4,000 / month");
assert_test((float)$chetaakNoFin['bpr_upfront_total'] === 100000.00, "Upfront total BPR is ₹1,00,000");

// 5. Check Dealership Tiers
$deals = ind_get_dealership_categories($conn);
assert_test(count($deals) === 5, "Found 5 Dealership Categories");
assert_test((float)$deals[0]['investment_amount'] === 1500000.00, "Gold Dealership is ₹15 Lakh");
assert_test((float)$deals[4]['investment_amount'] === 20000000.00, "Full State is ₹2 Crore");

// 6. Test index.php execution syntax
ob_start();
include __DIR__ . '/../index.php';
$html = ob_get_clean();
assert_test(strpos($html, 'MAX VXI') !== false, "Landing page index.php displays MAX VXI from database");
assert_test(strpos($html, 'ACTIVE CHETAAK') !== false || strpos($html, 'Active Chetaak') !== false, "Landing page index.php displays Active Chetaak");

echo "\nALL STEP 2 TESTS PASSED WITH 100% SUCCESS!\n";

