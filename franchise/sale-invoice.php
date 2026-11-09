<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isAdmin = !empty($_SESSION['admin_auth']) || !empty($_SESSION['admin_id']) || !empty($_SESSION['admin_user']);

if (!$isAdmin) {
    require_once __DIR__ . '/auth.php';
} else {
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/includes/helpers.php';
}

require_once __DIR__ . '/includes/inventory.php';   // pulls customers.php (frc_unit_identifiers)
if (!isset($conn) || !($conn instanceof mysqli)) die('Database connection failed.');

$id  = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo '<p style="font-family:sans-serif;padding:40px">Invalid invoice ID.</p>';
    exit;
}

/* ---- the sale ---- */
$sale = null;
if ($isAdmin) {
    // Admin can view any franchise's invoice
    $s = $conn->prepare("SELECT cp.*, c.customer_code, c.mobile AS c_mobile, c.email AS c_email,
                                c.address AS c_address, c.city AS c_city, c.state AS c_state,
                                c.district AS c_district, c.pincode AS c_pincode,
                                p.name AS current_product_name, p.model AS current_model, p.category AS p_category,
                                p.mileage AS p_mileage, p.battery_spec AS p_battery_spec, p.battery_warranty AS p_battery_warranty,
                                u.unit_no, u.status AS unit_status,
                                fh.franchise_code AS h_fcode, fh.name AS h_fname, fh.address_1 AS h_addr1, fh.address_2 AS h_addr2,
                                fh.city AS h_city, fh.state AS h_state, fh.postal_code AS h_pincode, fh.mobile AS h_mobile,
                                fh.email AS h_email, fh.gst_no AS h_gst
                         FROM fr_customer_purchases cp
                         LEFT JOIN fr_customers c ON c.id = cp.customer_id
                         LEFT JOIN fr_products  p ON p.id = cp.product_id
                         LEFT JOIN fr_units     u ON u.id = cp.unit_id
                         LEFT JOIN franchies_holder fh ON fh.id = cp.franchise_id
                         WHERE cp.id = ? LIMIT 1");
    $s->bind_param('i', $id);
} else {
    $fid = (int)$franchiseId;                            // authenticated franchise PK — never from URL
    $s = $conn->prepare("SELECT cp.*, c.customer_code, c.mobile AS c_mobile, c.email AS c_email,
                                c.address AS c_address, c.city AS c_city, c.state AS c_state,
                                c.district AS c_district, c.pincode AS c_pincode,
                                p.name AS current_product_name, p.model AS current_model, p.category AS p_category,
                                p.mileage AS p_mileage, p.battery_spec AS p_battery_spec, p.battery_warranty AS p_battery_warranty,
                                u.unit_no, u.status AS unit_status
                         FROM fr_customer_purchases cp
                         LEFT JOIN fr_customers c ON c.id = cp.customer_id
                         LEFT JOIN fr_products  p ON p.id = cp.product_id
                         LEFT JOIN fr_units     u ON u.id = cp.unit_id
                         WHERE cp.id = ? AND cp.franchise_id = ? LIMIT 1");
    $s->bind_param('ii', $id, $fid);
}
$s->execute();
$sale = $s->get_result()->fetch_assoc() ?: null;
$s->close();

if (!$sale) {
    http_response_code(404);
    echo '<p style="font-family:sans-serif;padding:40px">Invoice not found. ' . ($isAdmin ? '<a href="../admin/franchise-sale-approvals.php">Back to Approvals</a>' : '<a href="sales.php">Back to Sales</a>') . '</p>';
    exit;
}

$idents = (!empty($sale['unit_id']) && function_exists('frc_unit_identifiers'))
    ? frc_unit_identifiers($conn, (int)$sale['unit_id']) : [];

/* ---- franchise ("from") details ---- */
if ($isAdmin) {
    $fName = $sale['h_fname'] ?: 'Franchise Showroom';
    $fCode = $sale['h_fcode'] ?: ('FR' . ($sale['franchise_id'] ?? ''));
    $fromLines = array_values(array_filter([
        trim((string)($sale['h_addr1'] ?? '')),
        trim((string)($sale['h_addr2'] ?? '')),
        trim(implode(', ', array_filter([$sale['h_city'] ?? '', $sale['h_state'] ?? '', $sale['h_pincode'] ?? '']))),
    ]));
    $fMobile = $sale['h_mobile'] ?? '';
    $fEmail  = $sale['h_email'] ?? '';
    $fGst    = $sale['h_gst'] ?? '';
} else {
    $F = $GLOBALS['franchise'] ?? [];
    $fName = $franchiseName ?: ($F['name'] ?? 'Franchise Showroom');
    $fCode = $franchiseCode ?: ($F['franchise_code'] ?? '');
    $fromLines = array_values(array_filter([
        trim((string)($F['address_1'] ?? '')),
        trim((string)($F['address_2'] ?? '')),
        trim(implode(', ', array_filter([$F['city'] ?? '', $F['state'] ?? '', $F['postal_code'] ?? '']))),
    ]));
    $fMobile = $F['mobile'] ?? '';
    $fEmail  = $F['email'] ?? '';
    $fGst    = $F['gst_no'] ?? ($F['gst_number'] ?? '');
}

$pname  = $sale['current_product_name'] ?: $sale['product_name'] ?: 'Product';
$model  = $sale['current_model'] ?: $sale['model'];
$qtyN   = max(1, (int)$sale['quantity']);
$rate   = (float)$sale['unit_price'] ?: ((float)$sale['sale_amount'] / $qtyN);
$disc   = (float)($sale['discount'] ?? 0);
$appSt  = $sale['approval_status'] ?? ($sale['sale_status'] === 'Completed' ? 'Approved' : 'Pending_Approval');

// Option B Tax & On-Road breakdown
$taxableAmt = (float)($sale['taxable_amount'] > 0 ? $sale['taxable_amount'] : ($rate * $qtyN - $disc));
$cgstAmt    = (float)($sale['cgst_amount'] > 0 ? $sale['cgst_amount'] : ($taxableAmt * 0.025));
$sgstAmt    = (float)($sale['sgst_amount'] > 0 ? $sale['sgst_amount'] : ($taxableAmt * 0.025));
$rtoAmt     = (float)($sale['rto_charges'] ?? 0);
$insAmt     = (float)($sale['insurance_charges'] ?? 0);
$accAmt     = (float)($sale['accessories_charges'] ?? 0);
$subAmt     = (float)($sale['subsidy_discount'] ?? 0);
$total      = (float)($sale['final_invoice_amount'] > 0 ? $sale['final_invoice_amount'] : ($sale['sale_amount'] ?: ($taxableAmt + $cgstAmt + $sgstAmt + $rtoAmt + $insAmt + $accAmt - $subAmt)));

$walletPaid = (float)($sale['wallet_amount_used'] ?? 0);
$bankPaid   = (float)($sale['bank_amount_paid'] ?? 0);
$bankUtr    = trim((string)($sale['bank_utr'] ?? ''));

$paidAmt   = (float)($sale['amount_paid'] ?? 0);
$balance   = max(0.0, $total - $paidAmt);
$payMethod = trim((string)($sale['payment_method'] ?? ''));
$invNo     = $sale['invoice_no'] ?: ('REQ-' . str_pad((string)$sale['id'], 5, '0', STR_PAD_LEFT));
$invDt     = $sale['approved_at'] ?: ($sale['sale_date'] ?: substr((string)$sale['created_at'], 0, 10));

if (!function_exists('siv_inr')) {
    function siv_inr($n) { return '₹' . number_format((float)$n, 2); }
}
if (!function_exists('siv_words')) {
    function siv_words($num) {
    $num = (int) round($num);
    if ($num === 0) return 'Zero Rupees Only';
    $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
        'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
    $seg = function ($n) use (&$seg, $ones, $tens) {
        if ($n < 20) return $ones[$n];
        if ($n < 100) return trim($tens[intdiv($n, 10)] . ' ' . $ones[$n % 10]);
        return trim($ones[intdiv($n, 100)] . ' Hundred ' . $seg($n % 100));
    };
    $out = '';
    $crore = intdiv($num, 10000000); $num %= 10000000;
    $lakh  = intdiv($num, 100000);   $num %= 100000;
    $thou  = intdiv($num, 1000);     $num %= 1000;
    $rest  = $num;
    if ($crore) $out .= $seg($crore) . ' Crore ';
    if ($lakh)  $out .= $seg($lakh) . ' Lakh ';
    if ($thou)  $out .= $seg($thou) . ' Thousand ';
    if ($rest)  $out .= $seg($rest) . ' ';
    return trim($out) . ' Rupees Only';
    }
}
$payBadge = $sale['payment_status'] === 'Paid' ? '#0E7A3B' : '#B25400';
$delBadge = $sale['delivery_status'] === 'Delivered' ? '#0E7A3B' : '#6A6A6A';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice <?php echo e($invNo); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        :root{ --saffron:#E0700C; --green:#0E7A3B; --ink:#232323; --ink2:#6A6A6A; --ink3:#9A948C; --line:#E7E2D8; }
        *{ box-sizing:border-box; }
        body{ margin:0; background:#F4F1EA; font-family:'Segoe UI',system-ui,Arial,sans-serif; color:var(--ink); }
        .bar{ max-width:820px; margin:22px auto 0; display:flex; gap:10px; justify-content:flex-end; }
        .btn{ border:1px solid var(--line); background:#fff; color:var(--ink); border-radius:9px; padding:9px 16px;
            font-size:13px; font-weight:600; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; gap:8px; }
        .btn.primary{ background:linear-gradient(100deg,var(--saffron),var(--green)); color:#fff; border:0; }
        .sheet{ max-width:820px; margin:14px auto 40px; background:#fff; border:1px solid var(--line);
            border-radius:14px; overflow:hidden; box-shadow:0 20px 40px -24px rgba(120,60,10,.25); }
        .accent{ height:5px; background:linear-gradient(90deg,var(--saffron) 0%,#F0A44F 45%,#7FBF8E 55%,var(--green) 100%); }
        .pad{ padding:30px 34px; }
        .top{ display:flex; justify-content:space-between; gap:24px; flex-wrap:wrap; }
        .brand h1{ margin:0; font-size:20px; letter-spacing:-.01em; }
        .brand .muted, .muted{ color:var(--ink2); font-size:12.5px; line-height:1.6; }
        .doc{ text-align:right; }
        .doc .tag{ display:inline-block; font-size:11px; letter-spacing:.16em; text-transform:uppercase;
            color:var(--saffron); font-weight:700; }
        .doc h2{ margin:2px 0 8px; font-size:26px; letter-spacing:.02em; }
        .kv{ font-size:12.5px; color:var(--ink2); }
        .kv b{ color:var(--ink); }
        .parties{ display:flex; gap:22px; flex-wrap:wrap; margin-top:24px; }
        .party{ flex:1; min-width:230px; border:1px solid var(--line); border-radius:11px; padding:14px 16px; background:#FCFAF6; }
        .party .lbl{ font-size:10.5px; letter-spacing:.14em; text-transform:uppercase; color:var(--ink3); font-weight:700; }
        .party .nm{ font-size:14.5px; font-weight:700; margin:5px 0 3px; }
        table.items{ width:100%; border-collapse:collapse; margin-top:24px; font-size:13px; }
        table.items th{ background:#FBF6EE; color:var(--ink2); text-align:left; padding:10px 12px; font-size:11px;
            letter-spacing:.06em; text-transform:uppercase; border-bottom:1px solid var(--line); }
        table.items td{ padding:12px; border-bottom:1px solid #F1ECE3; vertical-align:top; }
        table.items td.r, table.items th.r{ text-align:right; }
        .idents{ margin-top:6px; font-size:11.5px; color:var(--ink2); }
        .idents span{ display:inline-block; background:#F3EEE4; border-radius:6px; padding:2px 7px; margin:2px 4px 0 0; }
        .totals{ margin-top:18px; margin-left:auto; width:300px; font-size:13px; }
        .totals div{ display:flex; justify-content:space-between; padding:7px 0; }
        .totals .grand{ border-top:2px solid var(--ink); margin-top:4px; padding-top:10px; font-size:16px; font-weight:800; }
        .words{ margin-top:14px; font-size:12.5px; color:var(--ink2); }
        .words b{ color:var(--ink); }
        .status{ margin-top:20px; display:flex; gap:10px; flex-wrap:wrap; }
        .chip{ font-size:11.5px; font-weight:700; border-radius:999px; padding:5px 12px; border:1px solid var(--line); }
        .foot{ margin-top:26px; padding-top:16px; border-top:1px dashed var(--line); font-size:11.5px; color:var(--ink3); }
        @media print{
            body{ background:#fff; }
            .bar{ display:none; }
            .sheet{ box-shadow:none; border:0; margin:0; max-width:none; border-radius:0; }
            @page{ margin:14mm; }
        }
    </style>
</head>
<body>

<div class="bar">
    <a href="<?php echo $isAdmin ? '../admin/franchise-sale-approvals.php' : 'sales.php'; ?>" class="btn">← Back to <?php echo $isAdmin ? 'Approvals' : 'Sales'; ?></a>
    <button class="btn primary" onclick="window.print()"><?php echo $appSt === 'Approved' ? '⬇ Print Tax Invoice' : '🖨️ Print Booking Slip'; ?></button>
</div>

<div class="sheet">
    <div class="accent"></div>

    <?php if ($appSt !== 'Approved'): ?>
        <div style="background:#FFFBEB; border-bottom:1px solid #FDE68A; padding:12px 24px; color:#92400E; font-size:13px; display:flex; align-items:center; gap:8px;">
            <span style="font-size:16px;">⏳</span>
            <div>
                <strong>Booking Request Pending Admin Approval:</strong> The vehicle unit is safely <strong>Reserved</strong>. Once Admin verifies the payment in the company bank account, the official GST Tax Invoice will be finalized here and franchise commission will be credited.
            </div>
        </div>
    <?php endif; ?>

    <div class="pad">

        <div class="top">
            <div class="brand">
                <div style="font-size:11px; text-transform:uppercase; letter-spacing:.14em; color:var(--saffron); font-weight:700;">ELECTRINC · Central Invoicing</div>
                <h1 style="margin:2px 0 4px;"><?php echo e($fName); ?></h1>
                <div class="muted">
                    Showroom Code: <b><?php echo e($fCode ?: '—'); ?></b><br>
                    <?php foreach ($fromLines as $ln) { echo e($ln) . '<br>'; } ?>
                    <?php if (!empty($fMobile)): ?>Mob: <?php echo e($fMobile); ?><?php endif; ?>
                    <?php if (!empty($fEmail)): ?> · <?php echo e($fEmail); ?><?php endif; ?><br>
                    <?php if (!empty($fGst)): ?>GSTIN: <?php echo e($fGst); ?><?php else: ?>GSTIN: 24AAAFE1234F1Z5<?php endif; ?>
                </div>
            </div>
            <div class="doc">
                <span class="tag"><?php echo $appSt === 'Approved' ? 'Official Tax Invoice' : 'Counter Booking Slip'; ?></span>
                <h2><?php echo $appSt === 'Approved' ? 'TAX INVOICE' : 'BOOKING SLIP'; ?></h2>
                <div class="kv">Invoice No: <b><?php echo e($invNo); ?></b></div>
                <div class="kv">Date: <b><?php echo e(date('d M Y', strtotime($invDt))); ?></b></div>
                <div class="kv">Status: <b style="color:<?php echo $appSt === 'Approved' ? '#0E7A3B' : '#B25400'; ?>"><?php echo $appSt === 'Approved' ? 'APPROVED &amp; BILLED' : 'PENDING ADMIN APPROVAL'; ?></b></div>
            </div>
        </div>

        <?php if (($sale['finance_status'] ?? '') === 'FINANCED' || !empty($sale['financier_name'])): ?>
            <div style="background:#F5F3FF; border:1px solid #DDD6FE; border-radius:10px; padding:12px 18px; margin-top:16px; color:#5B21B6; font-size:12.5px; display:flex; align-items:center; gap:12px;">
                <span style="font-size:22px;">🏦</span>
                <div>
                    <strong style="text-transform:uppercase; letter-spacing:.05em; font-size:12px; color:#4C1D95;">Vehicle Hypothecation Endorsement (RTO Form 20/21)</strong><br>
                    This vehicle is Hypothecated to <strong><?php echo e($sale['financier_name']); ?></strong> | Loan / DO Ref: <strong><?php echo e($sale['loan_ref_no'] ?: '—'); ?></strong><br>
                    Down Payment Paid: <strong><?php echo siv_inr($sale['down_payment_amount'] ?? 0); ?></strong> | Sanctioned Loan Amount: <strong><?php echo siv_inr($sale['loan_amount'] ?? 0); ?></strong>
                </div>
            </div>
        <?php endif; ?>

        <div class="parties">
            <div class="party">
                <div class="lbl">Customer / Buyer</div>
                <div class="nm"><?php echo e($sale['customer_name'] ?: 'Walk-in Customer'); ?></div>
                <div class="muted">
                    <?php if (($sale['buyer_type'] ?? 'MEMBER') === 'RETAIL'): ?>
                        <span style="display:inline-block; font-size:10.5px; font-weight:700; background:#EDE9FE; color:#5B21B6; padding:2px 8px; border-radius:4px; margin-bottom:4px;">RETAIL BUYER</span><br>
                    <?php elseif (!empty($sale['customer_code'])): ?>
                        Member ID / Code: <b><?php echo e($sale['customer_code']); ?></b><br>
                    <?php endif; ?>
                    <?php
                    $custAddr = array_filter([$sale['c_address'] ?? '', trim(implode(', ', array_filter([$sale['c_district'] ?? '', $sale['c_city'] ?? '', $sale['c_state'] ?? '', $sale['c_pincode'] ?? ''])))]);
                    foreach ($custAddr as $ln) echo e($ln) . '<br>';
                    ?>
                    <?php if (!empty($sale['c_mobile'])): ?>Mob: <?php echo e($sale['c_mobile']); ?><?php endif; ?>
                    <?php if (!empty($sale['c_email'])): ?> · <?php echo e($sale['c_email']); ?><?php endif; ?>
                </div>
            </div>
            <div class="party">
                <div class="lbl">Delivery &amp; Payment</div>
                <div class="muted" style="margin-top:5px;">
                    Showroom: <b><?php echo e($fName); ?> (<?php echo e($fCode); ?>)</b><br>
                    <?php if (($sale['finance_status'] ?? '') === 'FINANCED'): ?>
                        Financier: <b style="color:#5B21B6"><?php echo e($sale['financier_name'] ?: 'Bank Financier'); ?></b><br>
                        DO Ref No: <span style="font-family:monospace; font-weight:600;"><?php echo e($sale['loan_ref_no'] ?: '—'); ?></span><br>
                        Down Payment: <b><?php echo siv_inr($sale['down_payment_amount'] ?? 0); ?></b><br>
                        Bank Loan: <b><?php echo siv_inr($sale['loan_amount'] ?? 0); ?></b><br>
                    <?php else: ?>
                        <?php if ($walletPaid > 0): ?>Wallet Paid: <b style="color:#0E7A3B"><?php echo siv_inr($walletPaid); ?></b><br><?php endif; ?>
                        <?php if ($bankPaid > 0): ?>Bank / QR Paid: <b><?php echo siv_inr($bankPaid); ?></b><br><?php endif; ?>
                        <?php if ($bankUtr !== ''): ?>Bank UTR: <span style="font-family:monospace; font-weight:600;"><?php echo e($bankUtr); ?></span><br><?php endif; ?>
                    <?php endif; ?>
                    Delivery: <b style="color:<?php echo $delBadge; ?>"><?php echo e($sale['delivery_status']); ?></b>
                </div>
            </div>
        </div>

        <table class="items">
            <thead>
                <tr>
                    <th style="width:52px;">#</th>
                    <th>Vehicle Description</th>
                    <th style="width:90px;">HSN</th>
                    <th class="r" style="width:60px;">Qty</th>
                    <th class="r" style="width:120px;">Basic Rate</th>
                    <th class="r" style="width:130px;">Taxable Value</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>1</td>
                    <td>
                        <b style="font-size:14px;"><?php echo e($pname); ?></b><?php if ($model): ?> — <?php echo e($model); ?><?php endif; ?>
                        <?php if (!empty($idents)): ?>
                            <div class="idents">
                                <?php foreach ($idents as $k => $v): ?><span><?php echo e($k); ?>: <?php echo e($v); ?></span><?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <?php 
                        $specs = array_values(array_filter([
                            !empty($sale['p_battery_spec']) ? 'Battery: ' . $sale['p_battery_spec'] : '',
                            !empty($sale['p_mileage']) ? 'Mileage: ' . $sale['p_mileage'] : '',
                            !empty($sale['p_battery_warranty']) ? 'Battery Warranty: ' . $sale['p_battery_warranty'] : '',
                        ]));
                        if (!empty($specs)): ?>
                            <div style="margin-top:5px; font-size:11.5px; color:#1E3A8A; background:#EFF6FF; border:1px solid #BFDBFE; border-radius:6px; padding:4px 8px; display:inline-block;">
                                🛡️ <b>Specs &amp; Warranty:</b> <?php echo e(implode(' · ', $specs)); ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($sale['remarks'])): ?><div class="muted" style="margin-top:5px;"><?php echo e($sale['remarks']); ?></div><?php endif; ?>
                    </td>
                    <td>8711</td>
                    <td class="r"><?php echo $qtyN; ?></td>
                    <td class="r"><?php echo siv_inr($rate); ?></td>
                    <td class="r"><?php echo siv_inr($taxableAmt); ?></td>
                </tr>
            </tbody>
        </table>

        <div class="totals" style="width:340px;">
            <div><span>Taxable Basic Price</span><span><?php echo siv_inr($taxableAmt); ?></span></div>
            <div><span>CGST (2.5%)</span><span>+ <?php echo siv_inr($cgstAmt); ?></span></div>
            <div><span>SGST (2.5%)</span><span>+ <?php echo siv_inr($sgstAmt); ?></span></div>
            <?php if ($rtoAmt > 0): ?><div><span>RTO &amp; Road Tax</span><span>+ <?php echo siv_inr($rtoAmt); ?></span></div><?php endif; ?>
            <?php if ($insAmt > 0): ?><div><span>Comprehensive Insurance</span><span>+ <?php echo siv_inr($insAmt); ?></span></div><?php endif; ?>
            <?php if ($accAmt > 0): ?><div><span>Accessories &amp; Kit</span><span>+ <?php echo siv_inr($accAmt); ?></span></div><?php endif; ?>
            <?php if ($subAmt > 0): ?><div><span>Subsidy / Discount</span><span>− <?php echo siv_inr($subAmt); ?></span></div><?php endif; ?>
            <div class="grand"><span>Final On-Road Total</span><span><?php echo siv_inr($total); ?></span></div>
            
            <div style="margin-top:8px; padding-top:8px; border-top:1px dashed var(--line); font-size:12px;">
                <?php if (($sale['finance_status'] ?? '') === 'FINANCED'): ?>
                    <div style="display:flex; justify-content:space-between; color:#5B21B6;"><span>Down Payment:</span><span><?php echo siv_inr($sale['down_payment_amount'] ?? 0); ?></span></div>
                    <div style="display:flex; justify-content:space-between; color:#5B21B6;"><span>Bank Finance (<?php echo e($sale['financier_name'] ?: 'Loan'); ?>):</span><span><?php echo siv_inr($sale['loan_amount'] ?? 0); ?></span></div>
                    <div style="display:flex; justify-content:space-between; font-weight:700; color:#5B21B6; margin-top:4px;"><span>Payment Status:</span><span>FINANCED &amp; COVERED</span></div>
                <?php else: ?>
                    <?php if ($walletPaid > 0): ?><div style="display:flex; justify-content:space-between; color:#0E7A3B;"><span>Paid via Wallet:</span><span><?php echo siv_inr($walletPaid); ?></span></div><?php endif; ?>
                    <?php if ($bankPaid > 0): ?><div style="display:flex; justify-content:space-between; color:#374151;"><span>Paid via Bank/QR:</span><span><?php echo siv_inr($bankPaid); ?></span></div><?php endif; ?>
                    <div style="display:flex; justify-content:space-between; font-weight:700; color:#0E7A3B; margin-top:4px;"><span>Payment Status:</span><span>PAID IN FULL</span></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="words">Amount in words: <b><?php echo e(siv_words($total)); ?></b></div>

        <div class="foot" style="display:flex; justify-content:space-between; align-items:flex-end;">
            <div>
                This is an official computer-generated Tax Invoice issued by <strong>ELECTRINC Head Office</strong><br>
                and fulfilled via <strong><?php echo e($fName); ?> (<?php echo e($fCode); ?>)</strong>.<br>
                Vehicle comes with official manufacturer warranty and road compliance.
            </div>
            <div style="text-align:right;">
                <div style="font-size:11px; text-transform:uppercase; color:var(--ink3);">Authorized Signatory</div>
                <div style="font-weight:700; color:var(--green); margin-top:2px;">ELECTRINC INDIA PVT. LTD.</div>
            </div>
        </div>

    </div>
</div>

</body>
</html>
