<?php
require_once __DIR__ . '/../member/config/db.php';

echo "=== Running Brand Promotion Packages Migration ===\n";

$sql = file_get_contents(__DIR__ . '/2026_11_brand_promotion_packages.sql');

if ($conn->multi_query($sql)) {
    do {
        if ($res = $conn->store_result()) {
            $res->free();
        }
    } while ($conn->more_results() && $conn->next_result());
}

if ($conn->error) {
    echo "Migration Error: " . $conn->error . "\n";
} else {
    echo "Tables and settings created successfully!\n";
}

// Verify tables
$tables = ['ind_bp_packages', 'ind_bp_subscriptions', 'ind_bp_monthly_payouts', 'ind_bp_root_payouts'];
foreach ($tables as $t) {
    $res = $conn->query("SHOW TABLES LIKE '{$t}'");
    $exists = ($res && $res->num_rows > 0);
    echo "Table [{$t}]: " . ($exists ? "OK" : "MISSING") . "\n";
}

// Show seeded packages
$res = $conn->query("SELECT package_code, package_name, price, monthly_return, duration_months, total_return FROM ind_bp_packages ORDER BY sort_order ASC");
if ($res) {
    echo "\nSeeded Packages:\n";
    while ($r = $res->fetch_assoc()) {
        echo " - {$r['package_code']}: {$r['package_name']} | Price: ₹{$r['price']} | Monthly: ₹{$r['monthly_return']} x {$r['duration_months']} mo = Total ₹{$r['total_return']}\n";
    }
}

