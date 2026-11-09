<?php
session_start();
$_SESSION['admin_user'] = 'Admin Head Office';
$_SESSION['admin_id'] = 1;
$_GET['id'] = 4;

ob_start();
include __DIR__ . '/franchise/sale-invoice.php';
$html = ob_get_clean();

echo "Length: " . strlen($html) . PHP_EOL;
echo "Found INV-2026-00004: " . (strpos($html, 'INV-2026-00004') !== false ? 'YES' : 'NO') . PHP_EOL;
echo "Found Back to Approvals: " . (strpos($html, 'Back to Approvals') !== false ? 'YES' : 'NO') . PHP_EOL;
unlink(__FILE__);
