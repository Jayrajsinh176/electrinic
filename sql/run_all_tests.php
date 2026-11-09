<?php
/**
 * MASTER SYSTEM VERIFICATION TEST SUITE
 * Runs all 6 step verification suites in sequence.
 */

$suites = [
    'Step 1: Voucher 10m & 8.6% Joining Level Income' => __DIR__ . '/test_ind_full_flow.php',
    'Step 2: 11 EV Master Catalog & Dealership Slabs'  => __DIR__ . '/test_step2.php',
    'Step 3: 16% Vehicle Purchase Commission Pool'     => __DIR__ . '/test_step3.php',
    'Step 4: 25-Month Scooter Promotion & BPR Models'  => __DIR__ . '/test_step4.php',
    'Step 5: Monthly BPR Verification & Live GPS'      => __DIR__ . '/test_step5.php',
    'Step 6: UI Alignment, Reports & POS Scooter Wallet'=> __DIR__ . '/test_step6.php'
];

echo "=================================================================\n";
echo "ELECTRINC IND BUSINESS PLAN - MASTER CERTIFICATION AUDIT\n";
echo "=================================================================\n\n";

$passCount = 0;
$failCount = 0;

foreach ($suites as $name => $file) {
    echo ">> RUNNING SUITE: {$name}...\n";
    $output = [];
    $exitCode = 0;
    exec("php " . escapeshellarg($file), $output, $exitCode);
    
    if ($exitCode === 0) {
        echo "   [PASSED] {$name}\n\n";
        $passCount++;
    } else {
        echo "   [FAILED] {$name}\n";
        echo implode("\n", array_slice($output, -10)) . "\n\n";
        $failCount++;
    }
}

echo "=================================================================\n";
echo "AUDIT RESULTS: {$passCount} PASSED / {$failCount} FAILED\n";
if ($failCount === 0) {
    echo "STATUS: 100% COMPLETE & VERIFIED AGAINST 13-PAGE PLAN PDF!\n";
} else {
    echo "STATUS: FAILURES DETECTED.\n";
    exit(1);
}
echo "=================================================================\n";

