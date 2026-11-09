<?php
/**
 * Test Step 5: Monthly BPR Verification Flow
 * Tests:
 * 1. Schema check for new fields in ind_video_verifications
 * 2. Simulation of member monthly proof submission with live GPS, KM, odometer & feedback
 * 3. Simulation of admin approval with BPR wallet credit (50:50) and team BPR bonus trigger
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

echo "=== STEP 5 VERIFICATION TEST ===\n\n";

// 1. Column verification
echo "1. Checking table columns in ind_video_verifications...\n";
$cols = [];
$res = $conn->query("SHOW COLUMNS FROM ind_video_verifications");
while ($r = $res->fetch_assoc()) {
    $cols[] = $r['Field'];
}

$expectedCols = ['km_run', 'odometer_reading', 'odometer_photo_path', 'feedback_notes'];
foreach ($expectedCols as $c) {
    assert_test(in_array($c, $cols), "Column '{$c}' exists");
}

// 2. Set up test members
echo "\n2. Setting up test sponsor & member...\n";
$prefix = 'TEST5_' . time() . '_';
$sponsorId = $prefix . 'SPONSOR';
$memberId = $prefix . 'MEMBER';

$conn->query("INSERT INTO signup_users (customer_id, full_name, mobile, referral_id, status, active_status, activated_at, wallet_balance, scooter_wallet_balance) 
              VALUES ('{$sponsorId}', 'Step 5 Sponsor', '9999900001', '', 'Active', 'Active', NOW(), 0.00, 0.00)");

$conn->query("INSERT INTO signup_users (customer_id, full_name, mobile, referral_id, status, active_status, activated_at, wallet_balance, scooter_wallet_balance) 
              VALUES ('{$memberId}', 'Step 5 Member', '9999900002', '{$sponsorId}', 'Active', 'Active', NOW(), 100.00, 100.00)");

// 3. Create active promotion for member
$v = ind_get_vehicle_by_code($conn, 'MAX_ZXI');
$vehicleId = (int)$v['id'];
$monthlyBpr = (float)($v['bpr_finance_monthly'] ?? 1580.00); // 1580

$conn->query("INSERT INTO ind_scooter_promotions 
              (customer_id, vehicle_id, payment_option, vehicle_model, registration_number, direct_vehicles_count, support_percent, monthly_bpr_amount, months_paid, months_total, agreement_signed, status)
              VALUES ('{$memberId}', {$vehicleId}, 'finance', 'MAX ZXI', 'MH-12-ST-5555', 0, 50.00, {$monthlyBpr}, 0, 25, 1, 'active')");
$promoId = (int)$conn->insert_id;
assert_test($promoId > 0, "Created promotion #{$promoId} for {$memberId} with Monthly BPR = ₹{$monthlyBpr}");

// 4. Member submits Month 1 verification
echo "\n3. Submitting Month 1 Verification Proof (Live GPS + KM + Odometer Photo + Feedback)...\n";
$gpsLat = 18.5204;
$gpsLng = 73.8567;
$kmRun = 450.5;
$odometerReading = "12450";
$odometerPhoto = "uploads/scooter_proofs/test_odometer.jpg";
$feedback = "Scooter running smoothly, completed 450 km this month!";

$stmt = $conn->prepare("INSERT INTO ind_video_verifications 
    (promotion_id, customer_id, month_number, gps_latitude, gps_longitude, km_run, odometer_reading, odometer_photo_path, feedback_notes, video_file_path, status)
    VALUES (?, ?, 1, ?, ?, ?, ?, ?, ?, 'uploads/scooter_videos/test_vid.mp4', 'submitted')");
$stmt->bind_param('isdddsss', $promoId, $memberId, $gpsLat, $gpsLng, $kmRun, $odometerReading, $odometerPhoto, $feedback);
$stmt->execute();
$verId = (int)$stmt->insert_id;
$stmt->close();

assert_test($verId > 0, "Verification #{$verId} submitted successfully");

// 5. Simulate exact Admin Approval from admin/scooter-verifications.php
echo "\n4. Approving Verification #{$verId} via Admin Workflow...\n";

// Execute approval
$vq = $conn->query("SELECT v.*, sp.customer_id, sp.monthly_bpr_amount, sp.support_percent, sp.months_paid, sp.months_total, sp.vehicle_model 
                    FROM ind_video_verifications v 
                    JOIN ind_scooter_promotions sp ON sp.id = v.promotion_id 
                    WHERE v.id = {$verId} LIMIT 1")->fetch_assoc();

assert_test(!empty($vq), "Fetched verification details for approval");

$promoId = (int)$vq['promotion_id'];
$cid = (string)$vq['customer_id'];
$bprBase = (float)$vq['monthly_bpr_amount'];
$supPct = (float)$vq['support_percent'];
$mPaid = (int)$vq['months_paid'];
$mTotal = (int)$vq['months_total'];
$vehModel = (string)$vq['vehicle_model'];
$monthNumber = (int)$vq['month_number'];
$periodKey = date('Y-m');

$companySupport = round($bprBase * $supPct / 100.0, 2);
assert_test($companySupport === round($bprBase * 0.5, 2), "50% support of ₹{$bprBase} is ₹{$companySupport}");

// Update status
$conn->query("UPDATE ind_video_verifications SET status = 'verified', admin_notes = 'Approved test' WHERE id = {$verId}");

// Credit member income (50% Main, 50% Scooter Wallet)
ind_credit_member_income($conn, $cid, "Scooter BPR Month {$monthNumber}", $companySupport, [
    'source' => "Monthly Scooter Promotion ({$vehModel})",
    'source_ref' => "BPR-P{$promoId}-M{$monthNumber}",
    'base_amount' => $bprBase,
    'percentage' => $supPct
]);

// Advance months_paid
$newPaid = $mPaid + 1;
$newStatus = ($newPaid >= $mTotal) ? 'completed' : 'active';
$conn->query("UPDATE ind_scooter_promotions 
              SET months_paid = {$newPaid}, status = '{$newStatus}' 
              WHERE id = {$promoId}");

// Insert payout log
$conn->query("INSERT INTO ind_scooter_monthly_payouts 
    (promotion_id, customer_id, month_number, period_key, monthly_bpr_amount, support_percent, company_support_amount, member_share_amount, video_verification_id, status, paid_at, created_at)
    VALUES ({$promoId}, '{$cid}', {$monthNumber}, '{$periodKey}', {$bprBase}, {$supPct}, {$companySupport}, " . ($bprBase - $companySupport) . ", {$verId}, 'paid', NOW(), NOW())");

// Team BPR bonus to upline
ind_distribute_team_bpr_bonus($conn, $promoId, $periodKey);

// 6. Verify Results
echo "\n5. Validating Resulting Balances and Records...\n";

// Verify member dual wallet credit (Gross ₹790 -> 10% deduction = ₹79 -> Net ₹711. Split: ₹355.50 main, ₹355.50 scooter)
$memUser = $conn->query("SELECT wallet_balance, scooter_wallet_balance FROM signup_users WHERE customer_id = '{$memberId}'")->fetch_assoc();
echo "   Member Balances: Main = ₹{$memUser['wallet_balance']}, Scooter = ₹{$memUser['scooter_wallet_balance']}\n";
assert_test((float)$memUser['wallet_balance'] > 100.00, "Member Main Wallet credited");
assert_test((float)$memUser['scooter_wallet_balance'] > 100.00, "Member Scooter Wallet credited");
assert_test(abs(((float)$memUser['wallet_balance'] - 100.00) - ((float)$memUser['scooter_wallet_balance'] - 100.00)) < 0.05, "50:50 Equal split verified");

// Verify promo progress
$upPromo = $conn->query("SELECT months_paid, months_total FROM ind_scooter_promotions WHERE id = {$promoId}")->fetch_assoc();
assert_test((int)$upPromo['months_paid'] === 1, "Promotion months_paid updated to 1");
assert_test((int)$upPromo['months_total'] === 25, "Promotion months_total is 25");

// Verify team BPR bonus to sponsor (Level 1 is 30% of ₹790 = ₹237 gross)
$spUser = $conn->query("SELECT wallet_balance, scooter_wallet_balance FROM signup_users WHERE customer_id = '{$sponsorId}'")->fetch_assoc();
echo "   Sponsor Balances: Main = ₹{$spUser['wallet_balance']}, Scooter = ₹{$spUser['scooter_wallet_balance']}\n";
assert_test((float)$spUser['wallet_balance'] > 0, "Sponsor received Team BPR Bonus in Main Wallet");
assert_test((float)$spUser['scooter_wallet_balance'] > 0, "Sponsor received Team BPR Bonus in Scooter Wallet");

// Clean up
$conn->query("DELETE FROM ind_scooter_monthly_payouts WHERE promotion_id = {$promoId}");
$conn->query("DELETE FROM ind_video_verifications WHERE promotion_id = {$promoId}");
$conn->query("DELETE FROM ind_scooter_promotions WHERE id = {$promoId}");
$conn->query("DELETE FROM member_income_ledger WHERE customer_id IN ('{$memberId}', '{$sponsorId}')");
$conn->query("DELETE FROM signup_users WHERE customer_id IN ('{$memberId}', '{$sponsorId}')");

echo "\n>>> ALL STEP 5 TESTS PASSED WITH 100% SUCCESS! <<<\n";
