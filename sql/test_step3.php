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

echo "=== Testing Step 3: Vehicle Purchase Commission Engine ===\n";

$prefix = 'EVTEST_' . time() . '_';
$chainIds = [];
$prevSponsor = '';

for ($i = 10; $i >= 0; $i--) {
    $cid = $prefix . 'U' . $i;
    $refId = $prevSponsor;
    $prevSponsor = $cid;

    $conn->query("INSERT INTO signup_users 
        (customer_id, full_name, mobile, referral_id, registration_status, status, active_status, activated_at, wallet_balance, scooter_wallet_balance, product_fund_balance)
        VALUES ('{$cid}', 'Test Member {$i}', '9876543210', '{$refId}', 'Active', 'Active', 'Active', NOW(), 0.00, 0.00, 0.00)");
    $chainIds[$i] = $cid;
}

$buyer = $chainIds[0];
$directSponsor = $chainIds[1];
$orderRef = 'EV-TEST-ORD-' . time();

echo "[TEST] Buyer {$buyer} purchases MAX ZXI (Price: ₹79,000)...\n";

$res = ind_distribute_vehicle_commissions($conn, $buyer, 'MAX_ZXI', $orderRef);
if (empty($res['ok'])) {
    echo "ERROR: " . ($res['error'] ?? 'Unknown error') . "\n";
}
assert_test(!empty($res['ok']), "ind_distribute_vehicle_commissions executed successfully");
assert_test((float)$res['vehicle_price'] === 79000.00, "Vehicle price recognized as ₹79,000");

// 1. Check Direct Commission (5%)
$dirLog = $conn->query("SELECT * FROM ind_vehicle_commissions WHERE order_ref = '{$orderRef}' AND commission_type = 'direct' LIMIT 1")->fetch_assoc();
assert_test(!empty($dirLog), "Direct referral commission record created");
assert_test($dirLog['earner_id'] === $directSponsor, "Direct sponsor {$directSponsor} is the earner");
assert_test((float)$dirLog['percent'] === 5.0, "Direct commission percent is 5.0%");
assert_test((float)$dirLog['gross_amount'] === 3950.00, "Direct commission is 5% of ₹79,000 = ₹3,950.00");
assert_test((float)$dirLog['scooter_wallet_amount'] === 1975.00, "50% credited to Scooter Wallet = ₹1,975.00");
assert_test((float)$dirLog['main_balance_amount'] === 1975.00, "50% credited to Main Balance = ₹1,975.00");

// 2. Check Team Level Commissions (11%)
$teamLevels = [
    1  => ['pct' => 3.0, 'gross' => 2370.00, 'scooter' => 1185.00, 'main' => 1185.00],
    2  => ['pct' => 2.0, 'gross' => 1580.00, 'scooter' => 790.00,  'main' => 790.00],
    3  => ['pct' => 1.5, 'gross' => 1185.00, 'scooter' => 592.50,  'main' => 592.50],
    4  => ['pct' => 1.0, 'gross' => 790.00,  'scooter' => 395.00,  'main' => 395.00],
    5  => ['pct' => 1.0, 'gross' => 790.00,  'scooter' => 395.00,  'main' => 395.00],
    6  => ['pct' => 0.5, 'gross' => 395.00,  'scooter' => 197.50,  'main' => 197.50],
    7  => ['pct' => 0.5, 'gross' => 395.00,  'scooter' => 197.50,  'main' => 197.50],
    8  => ['pct' => 0.5, 'gross' => 395.00,  'scooter' => 197.50,  'main' => 197.50],
    9  => ['pct' => 0.5, 'gross' => 395.00,  'scooter' => 197.50,  'main' => 197.50],
    10 => ['pct' => 0.5, 'gross' => 395.00,  'scooter' => 197.50,  'main' => 197.50]
];

foreach ($teamLevels as $lvl => $expected) {
    $uplineId = $chainIds[$lvl];
    $tLog = $conn->query("SELECT * FROM ind_vehicle_commissions 
                         WHERE order_ref = '{$orderRef}' AND commission_type = 'team_level' AND level_number = {$lvl} LIMIT 1")->fetch_assoc();
    assert_test(!empty($tLog), "Team commission logged for Level {$lvl}");
    assert_test($tLog['earner_id'] === $uplineId, "Level {$lvl} earner is {$uplineId}");
    assert_test((float)$tLog['percent'] === $expected['pct'], "Level {$lvl} percent is {$expected['pct']}%");
    assert_test((float)$tLog['gross_amount'] === $expected['gross'], "Level {$lvl} gross amount is ₹{$expected['gross']}");
    assert_test((float)$tLog['scooter_wallet_amount'] === $expected['scooter'], "Level {$lvl} 50% Scooter Wallet is ₹{$expected['scooter']}");
    assert_test((float)$tLog['main_balance_amount'] === $expected['main'], "Level {$lvl} 50% Main Balance is ₹{$expected['main']}");
}

// 3. Check Total Distributed
$totQ = $conn->query("SELECT SUM(gross_amount) as total FROM ind_vehicle_commissions WHERE order_ref = '{$orderRef}'")->fetch_assoc();
$totalAll = (float)($totQ['total'] ?? 0.0);
assert_test($totalAll === 12640.00, "Total commissions paid on ₹79k vehicle is exactly ₹12,640.00 (5% Direct ₹3,950 + 11% Team ₹8,690 = 16%)");

// Clean up test records
$conn->query("DELETE FROM ind_vehicle_commissions WHERE order_ref = '{$orderRef}'");
$conn->query("DELETE FROM member_income_ledger WHERE customer_id LIKE '{$prefix}%'");
$conn->query("DELETE FROM signup_users WHERE customer_id LIKE '{$prefix}%'");

echo "\nALL STEP 3 TESTS PASSED WITH 100% SUCCESS!\n";
