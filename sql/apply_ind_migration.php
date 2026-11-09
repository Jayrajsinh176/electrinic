<?php
require_once __DIR__ . '/../member/config/db.php';

echo "=== Applying IND Member Business Plan Migration ===\n";

$sql = file_get_contents(__DIR__ . '/2026_10_ind_business_plan.sql');

if ($conn->multi_query($sql)) {
    do {
        if ($conn->error) {
            echo "Note/Error: " . $conn->error . "\n";
        }
    } while ($conn->more_results() && $conn->next_result());
}

echo "SQL Migration executed.\n";

// Populate or update the 100 Auto Pools state table
echo "Seeding 100 Auto-Pool States...\n";
$stmt = $conn->prepare("INSERT INTO ind_auto_pool_state 
    (pool_level, entry_amount, retopup_amount, upgrade_amount, withdrawal_gross) 
    VALUES (?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE 
    entry_amount = VALUES(entry_amount),
    retopup_amount = VALUES(retopup_amount),
    upgrade_amount = VALUES(upgrade_amount),
    withdrawal_gross = VALUES(withdrawal_gross)");

for ($n = 1; $n <= 100; $n++) {
    $entry = (float)(200 + ($n * 100)); // Pool 1 = 300, Pool 2 = 400 ...
    $retopup = $entry;
    if ($n < 100) {
        $upgrade = $entry + 100.0;
        $withdrawal = $entry - 100.0;
    } else {
        // Pool 100 has no next pool upgrade, so remainder goes to withdrawal
        $upgrade = 0.0;
        $withdrawal = ($entry * 3) - $retopup; // 20400.00
    }

    $stmt->bind_param('idddd', $n, $entry, $retopup, $upgrade, $withdrawal);
    $stmt->execute();
}
$stmt->close();

echo "Seeded 100 pools successfully.\n";

// Verify new tables
$tables = [
    'ind_vouchers',
    'ind_product_addon_splits',
    'ind_training_wallet_ledger',
    'ind_auto_pool_state',
    'ind_auto_pool_positions',
    'ind_auto_pool_ledger',
    'ind_affiliate_10level_logs',
    'ind_scooter_promotions',
    'ind_scooter_monthly_payouts',
    'ind_team_promotion_bonus_logs',
    'ind_video_verifications',
    'ind_award_reward_pool'
];

echo "\nVerification of new tables:\n";
foreach ($tables as $tbl) {
    $r = $conn->query("SHOW TABLES LIKE '$tbl'");
    echo ($r->num_rows > 0 ? "[OK] " : "[FAIL] ") . $tbl . "\n";
}

echo "\n=== Migration Complete ===\n";

