<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/inventory.php';   // pulls customers.php + franchise_finance.php
require_once __DIR__ . '/../includes/income_common.php';
require_once __DIR__ . '/../includes/ind_income_functions.php';
if (!isset($conn) || !($conn instanceof mysqli)) die('Database connection failed.');

$fid   = (int)$franchiseId;                          // authenticated franchise PK — never from URL
$fcode = (string)$franchiseCode;
$ftype = (string)$franchiseType;
$by    = (string)($franchiseName ?: $franchiseCode ?: ('FR' . $fid));

/* ---------------- AJAX: member check + scooter wallet balance ---------------- */
if (($_GET['ajax'] ?? '') === 'member_check') {
    header('Content-Type: application/json');
    $q = trim((string)($_GET['q'] ?? $_GET['query'] ?? ''));
    if ($q === '') {
        echo json_encode(['ok' => false, 'found' => false, 'message' => 'Empty search term']);
        exit;
    }
    $st = $conn->prepare("SELECT customer_id, full_name, mobile, scooter_wallet_balance, status, active_status, registration_status
                          FROM signup_users
                          WHERE customer_id = ? OR mobile = ?
                          LIMIT 1");
    if (!$st) {
        echo json_encode(['ok' => false, 'found' => false, 'message' => 'DB error: ' . $conn->error]);
        exit;
    }
    $st->bind_param('ss', $q, $q);
    $st->execute();
    $m = $st->get_result()->fetch_assoc();
    $st->close();
    if ($m) {
        echo json_encode([
            'ok'             => true,
            'found'          => true,
            'customer_id'    => $m['customer_id'],
            'full_name'      => $m['full_name'],
            'mobile'         => $m['mobile'],
            'scooter_wallet' => (float)($m['scooter_wallet_balance'] ?? 0.0),
            'status'         => $m['registration_status'] ?? $m['status'] ?? 'Active'
        ]);
    } else {
        echo json_encode(['ok' => true, 'found' => false]);
    }
    exit;
}

/* ---------------- AJAX: product + its available units ---------------- */
if (($_GET['ajax'] ?? '') === 'product') {
    header('Content-Type: application/json');
    $pidA = (int)($_GET['product_id'] ?? 0);
    $p = fr_product_get($conn, $fid, $pidA);
    if (!$p) { echo json_encode(['ok' => false]); exit; }
    $out = [
        'ok'           => true,
        'is_serialized' => (int)$p['is_serialized'],
        'price'        => (float)$p['price'],
        'mileage'      => $p['mileage'] ?? '',
        'battery_spec' => $p['battery_spec'] ?? '',
        'battery_warranty' => $p['battery_warranty'] ?? '',
        'available'    => 0,
        'units'        => [],
    ];
    if ((int)$p['is_serialized'] === 1) {
        $r = $conn->query("SELECT id, unit_no FROM fr_units WHERE franchise_id = " . $fid . " AND product_id = " . $pidA . " AND status = 'Available' ORDER BY id ASC");
        while ($r && ($u = $r->fetch_assoc())) {
            $idv = frc_unit_identifiers($conn, (int)$u['id']);
            $out['units'][] = ['id' => (int)$u['id'], 'unit_no' => $u['unit_no'], 'idents' => $idv];
        }
        $out['available'] = count($out['units']);
    } else {
        $out['available'] = (int)$p['qty_available'];
    }
    echo json_encode($out);
    exit;
}

/* ---------------- helper: upload payment receipt ---------------- */
function pos_upload_receipt(string $fcode, string &$err): string
{
    $fileKey = '';
    if (isset($_FILES['payment_receipt']) && $_FILES['payment_receipt']['error'] !== UPLOAD_ERR_NO_FILE) {
        $fileKey = 'payment_receipt';
    } elseif (isset($_FILES['payment_receipt_retail']) && $_FILES['payment_receipt_retail']['error'] !== UPLOAD_ERR_NO_FILE) {
        $fileKey = 'payment_receipt_retail';
    }
    if ($fileKey === '') return '';

    $file = $_FILES[$fileKey];
    if ($file['error'] !== UPLOAD_ERR_OK) { $err = 'Receipt upload failed.'; return ''; }

    $allowedExt = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
    $maxSize = 5 * 1024 * 1024;
    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) { $err = 'Receipt must be JPG, PNG, WEBP or PDF.'; return ''; }
    if ((int)$file['size'] > $maxSize) { $err = 'Receipt must be under 5 MB.'; return ''; }

    $uploadDir = __DIR__ . '/uploads/sale_receipts/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    $safeCode = preg_replace('/[^a-zA-Z0-9_-]/', '_', $fcode);
    $newFileName = 'receipt_' . $safeCode . '_' . time() . '_' . random_int(1000, 9999) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $newFileName)) { $err = 'Could not save the uploaded receipt.'; return ''; }

    return 'uploads/sale_receipts/' . $newFileName;
}

/* ---------------- helper: upload finance doc (DO / sanction letter) ---------------- */
function pos_upload_finance_doc(string $fcode, string &$err): string
{
    if (!isset($_FILES['finance_doc']) || $_FILES['finance_doc']['error'] === UPLOAD_ERR_NO_FILE) return '';
    $file = $_FILES['finance_doc'];
    if ($file['error'] !== UPLOAD_ERR_OK) { $err = 'Finance document upload failed.'; return ''; }

    $allowedExt = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
    $maxSize = 5 * 1024 * 1024;
    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) { $err = 'Finance document must be JPG, PNG, WEBP or PDF.'; return ''; }
    if ((int)$file['size'] > $maxSize) { $err = 'Finance document must be under 5 MB.'; return ''; }

    $uploadDir = __DIR__ . '/uploads/finance_docs/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    $safeCode = preg_replace('/[^a-zA-Z0-9_-]/', '_', $fcode);
    $newFileName = 'loan_do_' . $safeCode . '_' . time() . '_' . random_int(1000, 9999) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $newFileName)) { $err = 'Could not save the uploaded finance document.'; return ''; }

    return 'uploads/finance_docs/' . $newFileName;
}

/* ---------------- POST: create sale booking request ---------------- */
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_sale') {
    try {
        $buyerType = in_array(trim((string)($_POST['buyer_type'] ?? 'MEMBER')), ['MEMBER', 'RETAIL'], true)
            ? trim((string)$_POST['buyer_type'])
            : 'MEMBER';

        $custRef       = (int)($_POST['customer_ref'] ?? 0);
        $custName      = trim((string)($_POST['customer_name'] ?? ''));
        $inputMobile   = trim((string)($_POST['new_mobile'] ?? ''));
        $memberIdInput = trim((string)($_POST['member_id'] ?? ''));

        // Identify MLM Member if MEMBER type
        $memberRow = null;
        if ($buyerType === 'MEMBER') {
            if ($memberIdInput !== '') {
                $sm = $conn->prepare("SELECT customer_id, full_name, mobile, referral_id, scooter_wallet_balance, status, active_status FROM signup_users WHERE customer_id = ? OR mobile = ? LIMIT 1");
                if ($sm) {
                    $sm->bind_param('ss', $memberIdInput, $memberIdInput);
                    $sm->execute();
                    $memberRow = $sm->get_result()->fetch_assoc();
                    $sm->close();
                }
                if (!$memberRow) {
                    throw new RuntimeException("Member ID / Mobile '{$memberIdInput}' not found in the member database.");
                }
            } elseif ($inputMobile !== '') {
                $sm = $conn->prepare("SELECT customer_id, full_name, mobile, referral_id, scooter_wallet_balance, status, active_status FROM signup_users WHERE mobile = ? LIMIT 1");
                if ($sm) {
                    $sm->bind_param('s', $inputMobile);
                    $sm->execute();
                    $memberRow = $sm->get_result()->fetch_assoc();
                    $sm->close();
                }
            } elseif ($custRef > 0) {
                $crow = frc_customer_get($conn, $fid, $custRef);
                if ($crow && !empty($crow['mobile'])) {
                    $cmob = trim((string)$crow['mobile']);
                    $sm = $conn->prepare("SELECT customer_id, full_name, mobile, referral_id, scooter_wallet_balance, status, active_status FROM signup_users WHERE mobile = ? LIMIT 1");
                    if ($sm) {
                        $sm->bind_param('s', $cmob);
                        $sm->execute();
                        $memberRow = $sm->get_result()->fetch_assoc();
                        $sm->close();
                    }
                }
            }
        }

        // Customer creation or resolution
        if ($buyerType === 'RETAIL') {
            if ($custName === '') {
                throw new RuntimeException("Customer Name is required for retail walk-in purchase.");
            }
            if ($inputMobile === '') {
                throw new RuntimeException("Customer Mobile number is required for retail walk-in purchase.");
            }
            if ($custRef <= 0) {
                $nc = frc_customer_save($conn, $fid, $fcode, [
                    'customer_name' => $custName,
                    'mobile'        => $inputMobile,
                    'email'         => $_POST['new_email'] ?? '',
                    'address'       => $_POST['address'] ?? '',
                    'city'          => $_POST['city'] ?? '',
                    'state'         => $_POST['state'] ?? '',
                    'pincode'       => $_POST['pincode'] ?? '',
                ], $by);
                if (!empty($nc['ok'])) {
                    $custRef = (int)$nc['id'];
                }
            }
        } elseif ($custRef <= 0 && (($_POST['new_customer'] ?? '') === '1' || $custName !== '')) {
            $nc = frc_customer_save($conn, $fid, $fcode, [
                'customer_name' => $custName ?: ($memberRow['full_name'] ?? 'Walk-in'),
                'mobile'        => $inputMobile ?: ($memberRow['mobile'] ?? ''),
                'email'         => $_POST['new_email'] ?? '',
            ], $by);
            if (!empty($nc['ok'])) {
                $custRef = (int)$nc['id'];
            }
        }

        /* 2. product / unit / qty */
        $pid   = (int)($_POST['product_id'] ?? 0);
        $prod  = fr_product_get($conn, $fid, $pid);
        if (!$prod) throw new RuntimeException('Select a valid product.');
        $serial = (int)$prod['is_serialized'] === 1;
        $unitId = (int)($_POST['unit_id'] ?? 0);
        $qty    = $serial ? 1 : max(1, (int)($_POST['quantity'] ?? 1));

        /* 3. pricing & GST calculation */
        $price    = round((float)($_POST['selling_price'] ?? $prod['price']), 2);
        $discount = max(0.0, round((float)($_POST['discount'] ?? 0), 2));
        $taxable  = max(0.0, ($price * $qty) - $discount);
        $gstRate  = 5.00;
        $cgstAmt  = round(($taxable * 0.025), 2);
        $sgstAmt  = round(($taxable * 0.025), 2);
        $estimatedFinal = round($taxable + $cgstAmt + $sgstAmt, 2);

        /* 4. Payment & Finance Resolution */
        $uploadErr = '';
        $receiptPath = pos_upload_receipt($fcode, $uploadErr);
        if ($uploadErr !== '') throw new RuntimeException($uploadErr);

        $fDocErr = '';
        $financeDocPath = pos_upload_finance_doc($fcode, $fDocErr);
        if ($fDocErr !== '') throw new RuntimeException($fDocErr);

        $financeStatus = 'NONE';
        $financierName = null;
        $loanRefNo     = null;
        $downPaymentAmt= 0.00;
        $loanAmt       = 0.00;
        $walletAmt     = 0.00;
        $bankAmt       = 0.00;
        $bankUtr       = '';
        $payMethod     = 'Company Bank QR / Transfer';
        $saleRem       = '';

        if ($buyerType === 'RETAIL') {
            $retailOption = trim((string)($_POST['retail_pay_option'] ?? 'DIRECT'));
            if ($retailOption === 'FINANCE') {
                $financeStatus = 'FINANCED';
                $fNameSel = trim((string)($_POST['financier_name'] ?? ''));
                if ($fNameSel === 'Other') {
                    $financierName = trim((string)($_POST['other_financier_name'] ?? 'Other Bank / Financier'));
                } else {
                    $financierName = $fNameSel ?: 'Bank Financier';
                }
                $loanRefNo = trim((string)($_POST['loan_ref_no'] ?? ''));
                if ($loanRefNo === '') {
                    throw new RuntimeException("Loan Delivery Order (DO) / Sanction Reference number is required for financed purchases.");
                }
                $downPaymentAmt = max(0.0, round((float)($_POST['down_payment_amount'] ?? 0), 2));
                $loanAmt = max(0.0, round((float)($_POST['loan_amount'] ?? ($estimatedFinal - $downPaymentAmt)), 2));
                $bankAmt = $downPaymentAmt;
                $bankUtr = trim((string)($_POST['down_payment_utr'] ?? $_POST['bank_utr'] ?? ''));
                $payMethod = trim((string)($_POST['down_payment_method'] ?? 'Bank Loan / Vehicle Finance'));
                $saleRem = "Retail Walk-in · Financed by {$financierName} (DO Ref: {$loanRefNo})";
            } else {
                $financeStatus = 'NONE';
                $bankAmt = max(0.0, round((float)($_POST['retail_bank_amount'] ?? $_POST['bank_amount_paid'] ?? $estimatedFinal), 2));
                $bankUtr = trim((string)($_POST['retail_bank_utr'] ?? $_POST['bank_utr'] ?? ''));
                $payMethod = trim((string)($_POST['retail_payment_method'] ?? $_POST['payment_method'] ?? 'Company Bank QR / Transfer'));
                $saleRem = "Retail Walk-in · Direct Cash / Bank Purchase";
            }
        } else {
            // MLM MEMBER
            $walletAmt = max(0.0, round((float)($_POST['wallet_amount_used'] ?? 0), 2));
            $bankAmt   = max(0.0, round((float)($_POST['bank_amount_paid'] ?? 0), 2));
            $bankUtr   = trim((string)($_POST['bank_utr'] ?? ''));
            $payMethod = trim((string)($_POST['payment_method'] ?? 'Company Bank QR / Transfer'));
            $saleRem   = $memberRow ? ("Member ID: " . $memberRow['customer_id']) : '';

            /* Validate & Put Member Wallet on HOLD */
            if ($walletAmt > 0) {
                if (!$memberRow) {
                    throw new RuntimeException("Using Wallet requires a verified Member ID.");
                }
                $currentScooterBal = (float)($memberRow['scooter_wallet_balance'] ?? 0.0);
                if ($currentScooterBal < $walletAmt) {
                    throw new RuntimeException("Insufficient Scooter Wallet balance. Member has ₹" . number_format($currentScooterBal, 2) . ", but ₹" . number_format($walletAmt, 2) . " was requested.");
                }
                $newScooterBal = max(0.0, $currentScooterBal - $walletAmt);
                $upd = $conn->prepare("UPDATE signup_users SET scooter_wallet_balance = ? WHERE customer_id = ?");
                if ($upd) {
                    $upd->bind_param('ds', $newScooterBal, $memberRow['customer_id']);
                    $upd->execute();
                    $upd->close();
                }
                if (function_exists('wallet_log_transaction')) {
                    wallet_log_transaction($conn, (string)$memberRow['customer_id'], 'debit', 'scooter_purchase_hold', $walletAmt, $newScooterBal, [
                        'counterparty_id' => $fcode,
                        'ref_type'        => 'pos_sale_booking',
                        'note'            => "HOLD: Vehicle booking pending Admin approval at Franchise {$fcode}",
                    ]);
                }
            }
        }

        $today = date('Y-m-d');
        $buyerName = $custName ?: ($memberRow['full_name'] ?? 'Walk-in');
        $buyerIdStr = ($buyerType === 'MEMBER' && $memberRow) ? (string)$memberRow['customer_id'] : ($custRef > 0 ? (string)$custRef : '');
        $commEst = round($taxable * 0.10, 2); // 10% franchise commission on basic taxable price

        /* 5. Reserve unit and insert pending booking into fr_customer_purchases */
        if ($serial) {
            if ($unitId <= 0) throw new RuntimeException('Select the exact unit / scooter being booked.');
            $chk = fr_unit_get($conn, $fid, $unitId);
            if (!$chk || (string)$chk['status'] !== 'Available') throw new RuntimeException('That unit is not available (already booked or sold).');

            // Mark unit as Reserved
            $uUpd = $conn->prepare("UPDATE fr_units SET status = 'Reserved', customer_name = ?, customer_id = ?, customer_ref = ?, sale_amount = ?, sale_date = ?, remarks = ? WHERE id = ? AND franchise_id = ?");
            if ($uUpd) {
                $rem = 'Reserved: Pending Admin Approval (' . $fcode . ')';
                $cRefParam = $custRef > 0 ? $custRef : null;
                $uUpd->bind_param('sssdssii', $buyerName, $buyerIdStr, $cRefParam, $estimatedFinal, $today, $rem, $unitId, $fid);
                $uUpd->execute();
                $uUpd->close();
            }
        }

        $insPurchase = $conn->prepare("INSERT INTO fr_customer_purchases
            (franchise_id, customer_id, customer_name, buyer_type, product_id, unit_id, product_name, model, quantity,
             unit_price, taxable_amount, gst_rate, cgst_amount, sgst_amount, igst_amount, rto_charges,
             insurance_charges, accessories_charges, subsidy_discount, final_invoice_amount, discount,
             payment_method, finance_status, financier_name, loan_ref_no, down_payment_amount, loan_amount, finance_doc,
             sale_amount, wallet_amount_used, wallet_type, bank_amount_paid, bank_utr,
             payment_receipt, balance_due, payment_status, delivery_status, sale_status, approval_status,
             franchise_commission_amount, commission_status, remarks, created_by, sale_date, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0.00, 0.00, 0.00, 0.00, 0.00, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'scooter_wallet', ?, ?, ?, 0.00, 'Paid', 'Pending', 'Pending_Approval', 'Pending_Approval', ?, 'Pending', ?, ?, CURDATE(), NOW())");

        if (!$insPurchase) throw new RuntimeException("Database error: " . $conn->error);

        $prodName = (string)$prod['name'];
        $prodModel = (string)$prod['model'];

        $insPurchase->bind_param(
            'iissiissidddddddssssddsdddssdss',
            $fid, $custRef, $buyerName, $buyerType, $pid, $unitId, $prodName, $prodModel, $qty,
            $price, $taxable, $gstRate, $cgstAmt, $sgstAmt, $estimatedFinal, $discount,
            $payMethod, $financeStatus, $financierName, $loanRefNo, $downPaymentAmt, $loanAmt, $financeDocPath,
            $estimatedFinal, $walletAmt, $bankAmt, $bankUtr, $receiptPath,
            $commEst, $saleRem, $by
        );

        $insPurchase->execute();
        $saleId = (int)$insPurchase->insert_id;
        $insPurchase->close();

        header('Location: sales.php?submitted=1&id=' . $saleId);
        exit;

    } catch (\Throwable $e) {
        $err = $e->getMessage();
    }
}

/* ---------------- form data ---------------- */
$customers = frc_active_customers($conn, $fid);
$products  = [];
$r = $conn->query("SELECT id, name, model, price, is_serialized, status, mileage, battery_spec, battery_warranty FROM fr_products WHERE franchise_id = " . $fid . " AND status = 'Active' ORDER BY name ASC");
while ($r && ($x = $r->fetch_assoc())) $products[] = $x;

$recent = [];
$r = $conn->query("SELECT cp.id, cp.invoice_no, cp.sale_date, cp.sale_amount, cp.payment_status,
                          cp.approval_status, cp.sale_status,
                          COALESCE(NULLIF(c.customer_name,''), cp.customer_name) AS buyer, cp.product_name
                   FROM fr_customer_purchases cp
                   LEFT JOIN fr_customers c ON c.id = cp.customer_id
                   WHERE cp.franchise_id = " . $fid . " ORDER BY cp.id DESC LIMIT 8");
while ($r && ($x = $r->fetch_assoc())) $recent[] = $x;

function pos_money($n) { return '₹' . number_format((float)$n, 2); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Billing POS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-[#f3f3f3] font-sans overflow-x-hidden">
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<div class="ml-64 min-h-screen bg-[#f3f3f3]">
    <?php include __DIR__ . '/includes/navbar.php'; ?>
    <main class="px-4 sm:px-5 py-6">
        <div class="max-w-6xl mx-auto space-y-6">

            <div class="bg-[#17457a] rounded-xl px-5 sm:px-6 py-4 text-white shadow flex items-center justify-between flex-wrap gap-3">
                <div>
                    <h1 class="text-[18px] sm:text-[20px] font-semibold">Billing POS — New Sale</h1>
                    <p class="text-[12px] text-white/80 mt-1"><?php echo e($franchiseName); ?> · <?php echo e($franchiseCode); ?> · one screen: customer → unit → invoice</p>
                </div>
                <div class="flex gap-2">
                    <a href="sales.php" class="bg-white/15 hover:bg-white/25 rounded-lg px-3 py-2 text-[12px]">All Sales</a>
                    <a href="stock-report.php" class="bg-white/15 hover:bg-white/25 rounded-lg px-3 py-2 text-[12px]">Stock</a>
                </div>
            </div>

            <?php if ($err): ?><div class="rounded-lg border border-red-200 bg-red-50 px-4 py-2.5 text-red-700 text-[13px]"><?php echo e($err); ?></div><?php endif; ?>

            <?php if (empty($products)): ?>
                <div class="bg-white border border-gray-200 rounded-xl p-6 text-center text-gray-500 text-[13px]">
                    No active products. <a href="inventory.php" class="text-[#17457a] underline">Add a product</a> and receive stock first.
                </div>
            <?php else: ?>
            <form method="post" id="posForm" enctype="multipart/form-data" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <input type="hidden" name="action" value="create_sale">
                <input type="hidden" name="buyer_type" id="buyer_type" value="MEMBER">

                <!-- LEFT: customer + product -->
                <div class="lg:col-span-2 space-y-6">

                    <!-- BUYER TYPE SWITCHER -->
                    <div class="bg-white border border-gray-200 rounded-xl p-3 shadow-sm flex items-center gap-2">
                        <button type="button" id="tabBtnMember" onclick="switchBuyerType('MEMBER')"
                                class="flex-1 py-2.5 px-4 rounded-lg text-[13px] font-semibold flex items-center justify-center gap-2 transition bg-[#17457a] text-white shadow-sm">
                            <i data-lucide="award" class="w-4 h-4"></i>
                            <span>Member Booking</span>
                        </button>
                        <button type="button" id="tabBtnRetail" onclick="switchBuyerType('RETAIL')"
                                class="flex-1 py-2.5 px-4 rounded-lg text-[13px] font-semibold flex items-center justify-center gap-2 transition text-gray-600 hover:text-gray-900 hover:bg-gray-100">
                            <i data-lucide="user-check" class="w-4 h-4"></i>
                            <span>Retail / Walk-in Buyer</span>
                        </button>
                    </div>

                    <!-- CUSTOMER INFORMATION -->
                    <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
                        <div class="flex items-center justify-between mb-3">
                            <h2 class="text-[14px] font-semibold text-[#173a67] flex items-center gap-2">
                                <i data-lucide="user" class="w-4 h-4"></i>
                                <span id="custCardTitle">Member Customer Information</span>
                            </h2>
                            <span id="custBadge" class="text-[11px] text-[#0E7A3B] bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 rounded-full font-medium">
                                16% EV Bonus Eligible
                            </span>
                        </div>

                        <!-- 1A. Member Section (Visible when buyer_type === 'MEMBER') -->
                        <div id="memberSection" class="space-y-4">
                            <div class="bg-slate-50 border border-slate-200 rounded-lg p-3">
                                <label class="block text-[11px] font-semibold text-slate-700 uppercase tracking-wider mb-1">
                                    Buyer Member ID / Mobile <span class="text-gray-400 font-normal lowercase">(required for Scooter Wallet &amp; 16% EV network bonus)</span>
                                </label>
                                <div class="flex gap-2">
                                    <div class="relative flex-1">
                                        <input type="text" name="member_id" id="member_id" placeholder="Enter Member ID (e.g. IND10001) or Mobile" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-[13px] font-mono tracking-wide focus:outline-none focus:border-[#17457a]">
                                    </div>
                                    <button type="button" id="btnVerifyMember" class="bg-[#17457a] hover:bg-[#123a66] text-white text-[12px] font-medium px-4 py-2 rounded-lg flex items-center gap-1.5 transition-colors whitespace-nowrap">
                                        <i data-lucide="search" class="w-3.5 h-3.5"></i> Verify Member
                                    </button>
                                </div>
                                <div id="memberStatusBox" class="mt-2 text-[12px] hidden"></div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-[13px]">
                                <label class="block sm:col-span-2"><span class="text-gray-500 text-[11px]">Select from existing franchise records</span>
                                    <select name="customer_ref" id="customer_ref" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2">
                                        <option value="0">— walk-in / new —</option>
                                        <?php foreach ($customers as $c): ?>
                                            <option value="<?php echo (int)$c['id']; ?>"><?php echo e($c['customer_name'] . ($c['mobile'] ? ' · ' . $c['mobile'] : '') . ' (' . $c['customer_code'] . ')'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label class="flex items-center gap-2 sm:col-span-2 text-[12px]"><input type="checkbox" name="new_customer" value="1" id="new_customer"> Save into franchise customer address book</label>
                                <label class="block"><span class="text-gray-500 text-[11px]">Member Full Name</span>
                                    <input name="customer_name" id="customer_name" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="Full name"></label>
                                <label class="block"><span class="text-gray-500 text-[11px]">Mobile Number</span>
                                    <input name="new_mobile" id="new_mobile" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="10-digit mobile"></label>
                                <label class="block sm:col-span-2 nc-only hidden"><span class="text-gray-500 text-[11px]">Email</span>
                                    <input name="new_email" id="new_email" type="email" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="email@domain.com"></label>
                            </div>
                        </div>

                        <!-- 1B. Retail Walk-in Section (Visible when buyer_type === 'RETAIL') -->
                        <div id="retailSection" class="hidden space-y-3 text-[13px]">
                            <div class="p-3 bg-purple-50 border border-purple-200 rounded-lg text-purple-900 text-[12px]">
                                <strong>Retail Walk-in Customer:</strong> Direct vehicle sale to non-member buyer. 10% Showroom commission is credited. Choose between <strong>Direct Cash / Transfer</strong> or <strong>Bank Loan / EMI Finance</strong> below.
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <label class="block"><span class="text-gray-600 text-[11px] font-semibold">Customer Full Name *</span>
                                    <input name="retail_name" id="retail_name" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="e.g. Ramesh Patel"></label>
                                
                                <label class="block"><span class="text-gray-600 text-[11px] font-semibold">Mobile Number *</span>
                                    <input name="retail_mobile" id="retail_mobile" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="e.g. 9876543210"></label>
                                
                                <label class="block sm:col-span-2"><span class="text-gray-500 text-[11px]">Email Address (optional)</span>
                                    <input name="retail_email" id="retail_email" type="email" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="customer@example.com"></label>

                                <label class="block sm:col-span-2"><span class="text-gray-500 text-[11px]">Residential Address (for RTO / Tax Invoice)</span>
                                    <input name="address" id="address" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="House / Flat No., Street, Landmark"></label>

                                <label class="block"><span class="text-gray-500 text-[11px]">City / Taluka</span>
                                    <input name="city" id="city" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="City"></label>

                                <label class="block"><span class="text-gray-500 text-[11px]">State</span>
                                    <input name="state" id="state" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="State" value="Gujarat"></label>

                                <label class="block"><span class="text-gray-500 text-[11px]">Pincode</span>
                                    <input name="pincode" id="pincode" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="6-digit pincode"></label>
                            </div>
                        </div>

                    </div>

                    <!-- PRODUCT + UNIT -->
                    <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
                        <h2 class="text-[14px] font-semibold text-[#173a67] mb-3 flex items-center gap-2"><i data-lucide="package" class="w-4 h-4"></i> Product &amp; Unit</h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-[13px]">
                            <label class="block sm:col-span-2"><span class="text-gray-500 text-[11px]">Product *</span>
                                <select name="product_id" id="product_id" required class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2">
                                    <option value="">Select…</option>
                                    <?php foreach ($products as $p): ?>
                                        <option value="<?php echo (int)$p['id']; ?>"
                                                data-price="<?php echo (float)$p['price']; ?>"
                                                data-serial="<?php echo (int)$p['is_serialized']; ?>"
                                                data-mileage="<?php echo e($p['mileage'] ?? ''); ?>"
                                                data-battery="<?php echo e($p['battery_spec'] ?? ''); ?>"
                                                data-warranty="<?php echo e($p['battery_warranty'] ?? ''); ?>">
                                             <?php echo e($p['name'] . ($p['model'] ? ' / ' . $p['model'] : '')); ?> <?php echo (int)$p['is_serialized'] === 1 ? '· unit-tracked' : '· qty'; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <!-- PRODUCT SPECS BADGE BOX -->
                            <div id="productSpecsBox" class="sm:col-span-2 hidden p-3 rounded-lg bg-slate-50 border border-slate-200 text-[12px] flex flex-wrap gap-x-4 gap-y-2 items-center">
                                <div id="specMileageWrap" class="hidden flex items-center gap-1.5 font-medium text-blue-800">
                                    <i data-lucide="gauge" class="w-3.5 h-3.5 text-blue-600"></i>
                                    <span>Mileage: <b id="valMileage">—</b></span>
                                </div>
                                <div id="specBatteryWrap" class="hidden flex items-center gap-1.5 font-medium text-amber-800">
                                    <i data-lucide="zap" class="w-3.5 h-3.5 text-amber-600"></i>
                                    <span>Battery: <b id="valBattery">—</b></span>
                                </div>
                                <div id="specWarrantyWrap" class="hidden flex items-center gap-1.5 font-medium text-emerald-800">
                                    <i data-lucide="shield-check" class="w-3.5 h-3.5 text-emerald-600"></i>
                                    <span>Warranty: <b id="valWarranty">—</b></span>
                                </div>
                            </div>

                            <div id="unitWrap" class="block sm:col-span-2 hidden">
                                <span class="text-gray-500 text-[11px]">Select the exact unit / scooter *</span>
                                <select name="unit_id" id="unit_id" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2">
                                    <option value="">—</option>
                                </select>
                                <p id="unitIdents" class="mt-1.5 text-[11px] text-gray-500"></p>
                                <p class="text-[11px] text-gray-400">Only <b>available</b> units are listed. Unit will be <b>Reserved</b> for Admin approval.</p>
                            </div>

                            <label id="qtyWrap" class="block hidden"><span class="text-gray-500 text-[11px]">Quantity *</span>
                                <input name="quantity" id="quantity" type="number" min="1" value="1" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2"></label>

                            <p id="stockHint" class="sm:col-span-2 text-[12px] text-gray-500"></p>
                        </div>
                    </div>
                </div>

                <!-- RIGHT: pricing + payment -->
                <div class="space-y-6">
                    <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
                        <h2 class="text-[14px] font-semibold text-[#173a67] mb-3 flex items-center gap-2"><i data-lucide="receipt" class="w-4 h-4"></i> Price Calculation</h2>
                        <div class="space-y-2.5 text-[13px]">
                            <label class="block"><span class="text-gray-500 text-[11px]">Basic Vehicle Price (Ex-Showroom)</span>
                                <input name="selling_price" id="selling_price" type="number" step="0.01" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 bg-gray-50 font-medium"></label>
                            
                            <label class="block"><span class="text-gray-500 text-[11px]">Showroom Discount</span>
                                <input name="discount" id="discount" type="number" step="0.01" value="0" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2"></label>
                            
                            <div class="bg-blue-50 border border-blue-100 rounded-lg p-2.5 space-y-1.5 text-[12px]">
                                <div class="flex justify-between text-gray-600">
                                    <span>Taxable Value:</span>
                                    <span id="taxableVal" class="font-medium text-gray-800">₹0.00</span>
                                </div>
                                <div class="flex justify-between text-gray-600">
                                    <span>Estimated 5% GST (CGST 2.5% + SGST 2.5%):</span>
                                    <span id="gstVal" class="font-medium text-gray-800">+ ₹0.00</span>
                                </div>
                                <p class="text-[10px] text-gray-400">Note: Final RTO &amp; Insurance will be confirmed on Admin invoice.</p>
                            </div>

                            <div class="flex items-center justify-between border-t border-gray-100 pt-3">
                                <div>
                                    <span class="text-gray-700 font-semibold block text-[13px]">Estimated Total</span>
                                    <span class="text-[10px] text-gray-400">Vehicle Basic + 5% GST</span>
                                </div>
                                <span id="finalAmt" class="text-[20px] font-bold text-[#173a67]">₹0.00</span>
                            </div>
                        </div>
                    </div>

                    <!-- PAYMENT SECTION: MEMBER WALLET vs RETAIL -->
                    <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
                        
                        <!-- 2A. MEMBER PAYMENT BLOCK -->
                        <div id="memberPayBlock" class="space-y-3 text-[13px]">
                            <h2 class="text-[14px] font-semibold text-[#173a67] mb-2 flex items-center gap-2"><i data-lucide="indian-rupee" class="w-4 h-4"></i> Payment Split (Option 1)</h2>
                            
                            <!-- Member Wallet contribution -->
                            <div class="bg-emerald-50/70 border border-emerald-200 rounded-lg p-3">
                                <label class="block text-[11px] font-semibold text-emerald-900 uppercase tracking-wider mb-1">
                                    Member Wallet Payment (₹)
                                </label>
                                <input name="wallet_amount_used" id="wallet_amount_used" type="number" step="0.01" min="0" value="0" placeholder="0.00" class="w-full border border-emerald-300 rounded-lg px-3 py-2 text-[13px] font-semibold text-emerald-900 focus:outline-none focus:border-emerald-600">
                                <p id="walletHint" class="text-[11px] text-emerald-700 mt-1">Verify Member ID above to use Scooter Wallet balance.</p>
                            </div>

                            <!-- Remaining balance -->
                            <div class="p-2.5 rounded-lg bg-gray-50 border border-gray-200 flex justify-between items-center text-[13px]">
                                <span class="text-gray-600 font-medium">Remaining to Pay:</span>
                                <span id="remainingToPay" class="font-bold text-[16px] text-gray-900">₹0.00</span>
                            </div>

                            <!-- Payment mode for remaining balance -->
                            <label class="block"><span class="text-gray-500 text-[11px]">Payment Mode for Remaining Balance</span>
                                <select name="payment_method" id="payment_method" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2">
                                    <option value="Company Bank QR / Transfer">Company Bank QR / UPI / NEFT</option>
                                    <option value="Cash at Showroom">Cash at Showroom</option>
                                    <option value="NetBanking / RTGS">NetBanking / RTGS</option>
                                    <option value="Full Wallet">Full Wallet</option>
                                    <option value="Other">Other</option>
                                </select>
                            </label>

                            <!-- Bank UTR -->
                            <label class="block"><span class="text-gray-500 text-[11px]">Bank UTR / Transaction Reference No</span>
                                <input name="bank_utr" id="bank_utr" placeholder="e.g. UTRIB99881122" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-[13px] font-mono"></label>

                            <!-- Receipt upload -->
                            <label class="block"><span class="text-gray-500 text-[11px]">Upload Payment Receipt / Screenshot (JPG, PNG, PDF)</span>
                                <input type="file" name="payment_receipt" id="payment_receipt" accept=".jpg,.jpeg,.png,.webp,.pdf" class="mt-1 w-full text-[12px] border border-gray-300 rounded-lg p-1.5 bg-white"></label>
                        </div>

                        <!-- 2B. RETAIL PAYMENT BLOCK -->
                        <div id="retailPayBlock" class="hidden space-y-4 text-[13px]">
                            <h2 class="text-[14px] font-semibold text-[#173a67] flex items-center gap-2"><i data-lucide="credit-card" class="w-4 h-4"></i> Retail Payment Option</h2>
                            
                            <!-- Sub-choice: Direct Cash vs Bank Finance -->
                            <div class="grid grid-cols-2 gap-2 p-1 bg-gray-100 rounded-lg border border-gray-200">
                                <label class="flex items-center justify-center gap-1.5 py-2 px-2 rounded-md cursor-pointer text-[12px] font-medium transition text-gray-700 has-[:checked]:bg-white has-[:checked]:text-[#17457a] has-[:checked]:shadow-sm">
                                    <input type="radio" name="retail_pay_option" value="DIRECT" checked onchange="switchRetailOption('DIRECT')" class="sr-only">
                                    <i data-lucide="banknote" class="w-4 h-4"></i>
                                    <span>Direct / Cash</span>
                                </label>
                                <label class="flex items-center justify-center gap-1.5 py-2 px-2 rounded-md cursor-pointer text-[12px] font-medium transition text-gray-700 has-[:checked]:bg-white has-[:checked]:text-[#17457a] has-[:checked]:shadow-sm">
                                    <input type="radio" name="retail_pay_option" value="FINANCE" onchange="switchRetailOption('FINANCE')" class="sr-only">
                                    <i data-lucide="landmark" class="w-4 h-4"></i>
                                    <span>Bank Loan / EMI</span>
                                </label>
                            </div>

                            <!-- Retail Option 1: Direct Cash / Bank Transfer -->
                            <div id="retailDirectWrap" class="space-y-3">
                                <div class="p-2.5 rounded-lg bg-blue-50 border border-blue-200 flex justify-between items-center text-[13px]">
                                    <span class="text-blue-900 font-medium">Payable Amount:</span>
                                    <span id="retailDirectPayable" class="font-bold text-[16px] text-blue-900">₹0.00</span>
                                </div>

                                <label class="block"><span class="text-gray-500 text-[11px]">Payment Mode</span>
                                    <select name="retail_payment_method" id="retail_payment_method" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2">
                                        <option value="Company Bank QR / Transfer">Company Bank QR / UPI / NEFT</option>
                                        <option value="Cash at Showroom">Cash at Showroom</option>
                                        <option value="NetBanking / RTGS">NetBanking / RTGS</option>
                                    </select>
                                </label>

                                <label class="block"><span class="text-gray-500 text-[11px]">Bank UTR / Transaction Reference</span>
                                    <input name="retail_bank_utr" id="retail_bank_utr" placeholder="e.g. UTRIB99881122" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-[13px] font-mono"></label>

                                <label class="block"><span class="text-gray-500 text-[11px]">Payment Receipt / Deposit Slip (JPG, PNG, PDF)</span>
                                    <input type="file" name="payment_receipt_retail" id="payment_receipt_retail" accept=".jpg,.jpeg,.png,.webp,.pdf" class="mt-1 w-full text-[12px] border border-gray-300 rounded-lg p-1.5 bg-white"></label>
                            </div>

                            <!-- Retail Option 2: Bank Loan / Vehicle Finance -->
                            <div id="retailFinanceWrap" class="hidden space-y-3">
                                <div class="p-3 bg-purple-50 border border-purple-200 rounded-lg text-purple-900 text-[12px]">
                                    <strong>Vehicle Hypothecation:</strong> Enter bank DO details below. Tax invoice issued by Admin will be endorsed with bank hypothecation.
                                </div>

                                <label class="block"><span class="text-gray-600 text-[11px] font-semibold">Bank / Financier *</span>
                                    <select name="financier_name" id="financier_name" onchange="checkCustomFinancier()" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2">
                                        <option value="HDFC Bank">HDFC Bank</option>
                                        <option value="Bajaj Finserv">Bajaj Finserv</option>
                                        <option value="IDFC First Bank">IDFC First Bank</option>
                                        <option value="IndusInd Bank">IndusInd Bank</option>
                                        <option value="State Bank of India (SBI)">State Bank of India (SBI)</option>
                                        <option value="Kotak Mahindra Prime">Kotak Mahindra Prime</option>
                                        <option value="Hero FinCorp">Hero FinCorp</option>
                                        <option value="L&amp;T Finance">L&amp;T Finance</option>
                                        <option value="TVS Credit">TVS Credit</option>
                                        <option value="Other">Other Bank / Financier</option>
                                    </select>
                                </label>

                                <div id="customFinancierWrap" class="hidden">
                                    <label class="block"><span class="text-gray-600 text-[11px] font-semibold">Custom Financier Name *</span>
                                        <input name="other_financier_name" id="other_financier_name" placeholder="Enter Financier / Bank Name" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2"></label>
                                </div>

                                <label class="block"><span class="text-gray-600 text-[11px] font-semibold">Loan Delivery Order (DO) / Ref No *</span>
                                    <input name="loan_ref_no" id="loan_ref_no" placeholder="e.g. HDFC-DO-2024-9812" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 font-mono"></label>

                                <div class="grid grid-cols-2 gap-2">
                                    <label class="block"><span class="text-gray-600 text-[11px] font-semibold">Down Payment (₹) *</span>
                                        <input type="number" step="0.01" min="0" name="down_payment_amount" id="down_payment_amount" placeholder="0.00" value="0" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 font-semibold text-emerald-800"></label>
                                    
                                    <label class="block"><span class="text-gray-600 text-[11px] font-semibold">Bank Loan (₹) *</span>
                                        <input type="number" step="0.01" min="0" name="loan_amount" id="loan_amount" placeholder="0.00" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 font-semibold text-purple-900"></label>
                                </div>

                                <label class="block"><span class="text-gray-500 text-[11px]">Down Payment Payment Mode</span>
                                    <select name="down_payment_method" id="down_payment_method" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2">
                                        <option value="Company Bank QR / Transfer">Company Bank QR / UPI / NEFT</option>
                                        <option value="Cash at Showroom">Cash at Showroom</option>
                                        <option value="NetBanking / RTGS">NetBanking / RTGS</option>
                                    </select>
                                </label>

                                <label class="block"><span class="text-gray-500 text-[11px]">Down Payment UTR / Ref</span>
                                    <input name="down_payment_utr" id="down_payment_utr" placeholder="UTR for down payment if paid via bank" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-[13px] font-mono"></label>

                                <label class="block bg-purple-50/50 p-2.5 rounded-lg border border-purple-200">
                                    <span class="text-purple-900 text-[11px] font-semibold block mb-1">Upload Bank Sanction Letter / Delivery Order (DO) *</span>
                                    <input type="file" name="finance_doc" id="finance_doc" accept=".jpg,.jpeg,.png,.webp,.pdf" class="mt-1 w-full text-[12px] border border-gray-300 rounded-lg p-1.5 bg-white">
                                    <span class="text-[10px] text-gray-500 mt-0.5 block">JPG, PNG or PDF under 5 MB</span>
                                </label>
                            </div>
                        </div>

                        <div class="mt-4 p-3 bg-amber-50 border border-amber-200 rounded-lg text-[11px] text-amber-800 flex items-start gap-2">
                            <i data-lucide="info" class="w-4 h-4 shrink-0 mt-0.5 text-amber-600"></i>
                            <span><strong>Centralized Billing:</strong> When you submit, the vehicle unit is <strong>Reserved</strong>. Admin will verify payment / finance documents, issue the official GST Tax Invoice, and release your franchise commission.</span>
                        </div>

                        <button type="submit" class="mt-4 w-full bg-[#17457a] hover:bg-[#123a66] text-white rounded-lg px-5 py-3 text-[14px] font-semibold flex items-center justify-center gap-2 shadow transition">
                            <i data-lucide="send" class="w-4 h-4"></i> Submit Booking to Admin
                        </button>
                    </div>
                </div>
            </form>
            <?php endif; ?>

            <!-- RECENT -->
            <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100 text-[14px] font-semibold text-[#173a67]">Recent Sales &amp; Bookings</div>
                <div class="overflow-x-auto">
                    <table class="w-full text-[13px]">
                        <thead class="bg-gray-50 text-gray-500 text-[12px]"><tr>
                            <th class="text-left px-5 py-2.5">Date</th><th class="text-left px-5 py-2.5">Invoice / Status</th>
                            <th class="text-left px-5 py-2.5">Customer</th><th class="text-left px-5 py-2.5">Product</th>
                            <th class="text-right px-5 py-2.5">Amount</th><th class="text-left px-5 py-2.5">Approval</th><th class="px-5 py-2.5"></th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($recent)): ?><tr><td colspan="7" class="px-5 py-8 text-center text-gray-400">No sales or bookings yet.</td></tr><?php endif; ?>
                            <?php foreach ($recent as $s): ?>
                                <tr>
                                    <td class="px-5 py-2.5 text-gray-500 whitespace-nowrap"><?php echo e($s['sale_date']); ?></td>
                                    <td class="px-5 py-2.5">
                                        <?php if (!empty($s['invoice_no'])): ?>
                                            <a href="sale-invoice.php?id=<?php echo (int)$s['id']; ?>" target="_blank" class="text-[#0E7A3B] font-semibold hover:underline"><?php echo e($s['invoice_no']); ?></a>
                                        <?php else: ?>
                                            <span class="text-amber-700 bg-amber-50 px-2 py-0.5 rounded text-[11px] font-medium">Pending Invoicing</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-5 py-2.5"><?php echo e($s['buyer'] ?: 'Walk-in'); ?></td>
                                    <td class="px-5 py-2.5 text-gray-600"><?php echo e($s['product_name']); ?></td>
                                    <td class="px-5 py-2.5 text-right font-semibold"><?php echo pos_money($s['sale_amount']); ?></td>
                                    <td class="px-5 py-2.5">
                                        <?php 
                                        $appSt = ($s['approval_status'] ?? '') ?: ((($s['sale_status'] ?? '') === 'Completed') ? 'Approved' : 'Pending_Approval');
                                        if ($appSt === 'Approved'): ?>
                                            <span class="text-[11px] rounded-full px-2 py-0.5 bg-green-50 text-green-700 font-semibold">✓ Approved</span>
                                        <?php elseif ($appSt === 'Rejected'): ?>
                                            <span class="text-[11px] rounded-full px-2 py-0.5 bg-red-50 text-red-700 font-semibold">✕ Rejected</span>
                                        <?php else: ?>
                                            <span class="text-[11px] rounded-full px-2 py-0.5 bg-amber-50 text-amber-700 font-semibold">⏳ Pending Admin</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-5 py-2.5 text-right">
                                        <a href="sale-invoice.php?id=<?php echo (int)$s['id']; ?>" target="_blank" class="text-[12px] text-[#17457a] hover:underline">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>
</div>

<script>
if (typeof lucide !== 'undefined') lucide.createIcons();

function switchBuyerType(type) {
    var isMember = (type === 'MEMBER');
    document.getElementById('buyer_type').value = isMember ? 'MEMBER' : 'RETAIL';
    
    // Tab styles
    var btnM = document.getElementById('tabBtnMember');
    var btnR = document.getElementById('tabBtnRetail');
    if (isMember) {
        btnM.className = 'flex-1 py-2.5 px-4 rounded-lg text-[13px] font-semibold flex items-center justify-center gap-2 transition bg-[#17457a] text-white shadow-sm';
        btnR.className = 'flex-1 py-2.5 px-4 rounded-lg text-[13px] font-semibold flex items-center justify-center gap-2 transition text-gray-600 hover:text-gray-900 hover:bg-gray-100';
    } else {
        btnR.className = 'flex-1 py-2.5 px-4 rounded-lg text-[13px] font-semibold flex items-center justify-center gap-2 transition bg-[#17457a] text-white shadow-sm';
        btnM.className = 'flex-1 py-2.5 px-4 rounded-lg text-[13px] font-semibold flex items-center justify-center gap-2 transition text-gray-600 hover:text-gray-900 hover:bg-gray-100';
    }

    // Title & badge
    document.getElementById('custCardTitle').textContent = isMember ? 'Member Customer Information' : 'Retail Walk-in Customer Details';
    var badge = document.getElementById('custBadge');
    if (isMember) {
        badge.textContent = '16% EV Bonus Eligible';
        badge.className = 'text-[11px] text-[#0E7A3B] bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 rounded-full font-medium';
    } else {
        badge.textContent = 'Retail Non-Member';
        badge.className = 'text-[11px] text-purple-800 bg-purple-50 border border-purple-200 px-2.5 py-0.5 rounded-full font-medium';
    }

    // Toggle left blocks
    document.getElementById('memberSection').classList.toggle('hidden', !isMember);
    document.getElementById('retailSection').classList.toggle('hidden', isMember);

    // Toggle right payment blocks
    document.getElementById('memberPayBlock').classList.toggle('hidden', !isMember);
    document.getElementById('retailPayBlock').classList.toggle('hidden', isMember);

    if (window._recalcPOS) window._recalcPOS();
}

function switchRetailOption(opt) {
    var isDirect = (opt === 'DIRECT');
    document.getElementById('retailDirectWrap').classList.toggle('hidden', !isDirect);
    document.getElementById('retailFinanceWrap').classList.toggle('hidden', isDirect);
    if (window._recalcPOS) window._recalcPOS();
}

function checkCustomFinancier() {
    var sel = document.getElementById('financier_name');
    var cWrap = document.getElementById('customFinancierWrap');
    if (sel && cWrap) {
        cWrap.classList.toggle('hidden', sel.value !== 'Other');
    }
}

(function () {
    var f = document.getElementById('posForm');
    if (!f) return;
    var prod = document.getElementById('product_id'),
        unitWrap = document.getElementById('unitWrap'), unitSel = document.getElementById('unit_id'),
        unitIdents = document.getElementById('unitIdents'),
        qtyWrap = document.getElementById('qtyWrap'), qty = document.getElementById('quantity'),
        stockHint = document.getElementById('stockHint'),
        price = document.getElementById('selling_price'), disc = document.getElementById('discount'),
        taxableVal = document.getElementById('taxableVal'), gstVal = document.getElementById('gstVal'),
        finalAmt = document.getElementById('finalAmt'),
        walletUsed = document.getElementById('wallet_amount_used'),
        walletHint = document.getElementById('walletHint'),
        remainingToPay = document.getElementById('remainingToPay'),
        bankUtr = document.getElementById('bank_utr'),
        payMethod = document.getElementById('payment_method'),
        newCust = document.getElementById('new_customer'), custRef = document.getElementById('customer_ref'),
        custName = document.getElementById('customer_name'),
        memberInput = document.getElementById('member_id'), btnVerify = document.getElementById('btnVerifyMember'),
        memberStatusBox = document.getElementById('memberStatusBox'),
        newMobile = document.getElementById('new_mobile'),
        retailName = document.getElementById('retail_name'),
        retailMobile = document.getElementById('retail_mobile'),
        retailPayable = document.getElementById('retailDirectPayable'),
        downPay = document.getElementById('down_payment_amount'),
        loanAmt = document.getElementById('loan_amount');

    var verifiedMember = null;

    function money(n){ return '₹' + (Number(n)||0).toLocaleString('en-IN', {minimumFractionDigits:2, maximumFractionDigits:2}); }

    function updateWalletFields(){
        var fin = parseFloat(finalAmt.getAttribute('data-raw') || 0);
        if (verifiedMember) {
            var swBal = verifiedMember.scooter_wallet || 0;
            walletHint.innerHTML = 'Member has <strong>' + money(swBal) + '</strong> Scooter Wallet balance available.';
            walletUsed.max = Math.min(swBal, fin);
        } else {
            walletHint.textContent = 'Verify Member ID above to use Scooter Wallet balance.';
            walletUsed.value = 0;
            walletUsed.max = 0;
        }
        recalcRemaining();
    }

    function recalcRemaining(){
        var fin = parseFloat(finalAmt.getAttribute('data-raw') || 0);
        var wVal = parseFloat(walletUsed.value) || 0;
        if (verifiedMember) {
            var maxW = Math.min(verifiedMember.scooter_wallet || 0, fin);
            if (wVal > maxW) {
                wVal = maxW;
                walletUsed.value = wVal.toFixed(2);
            }
        } else {
            wVal = 0;
            walletUsed.value = 0;
        }
        var rem = Math.max(0, fin - wVal);
        remainingToPay.textContent = money(rem);
        if (rem === 0 && wVal > 0) {
            payMethod.value = 'Full Wallet';
        }
    }

    function verifyMemberAjax(){
        var val = (memberInput ? memberInput.value : '').trim();
        if (!val) {
            memberStatusBox.className = 'mt-2 text-[12px] p-2 rounded bg-amber-50 border border-amber-200 text-amber-800';
            memberStatusBox.textContent = 'Please enter a Member ID or Mobile to verify.';
            memberStatusBox.classList.remove('hidden');
            verifiedMember = null;
            updateWalletFields();
            return;
        }

        memberStatusBox.className = 'mt-2 text-[12px] p-2 rounded bg-blue-50 border border-blue-200 text-blue-700';
        memberStatusBox.textContent = 'Verifying member...';
        memberStatusBox.classList.remove('hidden');

        fetch('pos.php?ajax=member_check&q=' + encodeURIComponent(val))
            .then(function(r){ return r.json(); })
            .then(function(res){
                if (res && res.found) {
                    verifiedMember = res;
                    memberStatusBox.className = 'mt-2 text-[12px] p-2.5 rounded bg-emerald-50 border border-emerald-200 text-emerald-800 space-y-0.5';
                    memberStatusBox.innerHTML = '<div><strong class="font-semibold text-emerald-900">' + res.full_name + '</strong> (' + res.customer_id + ')</div>' +
                        '<div class="text-[11px] text-emerald-700">Mobile: ' + (res.mobile || '—') + ' · Status: <span class="font-medium">' + (res.status || 'Active') + '</span> · Scooter Wallet: <strong>' + money(res.scooter_wallet) + '</strong></div>' +
                        '<div class="text-[11px] text-emerald-600 font-medium mt-0.5">✓ Verified for 16% EV Commission &amp; Scooter Wallet checkout</div>';

                    if (!custName.value.trim()) custName.value = res.full_name;
                    if (newMobile && !newMobile.value.trim() && res.mobile) newMobile.value = res.mobile;
                } else {
                    verifiedMember = null;
                    memberStatusBox.className = 'mt-2 text-[12px] p-2 rounded bg-red-50 border border-red-200 text-red-700';
                    memberStatusBox.textContent = 'Member not found with ID/Mobile "' + val + '".';
                }
                updateWalletFields();
            })
            .catch(function(err){
                memberStatusBox.className = 'mt-2 text-[12px] p-2 rounded bg-red-50 border border-red-200 text-red-700';
                memberStatusBox.textContent = 'Error checking member: ' + err.message;
                updateWalletFields();
            });
    }

    if (btnVerify) {
        btnVerify.addEventListener('click', verifyMemberAjax);
    }
    if (memberInput) {
        memberInput.addEventListener('keydown', function(e){
            if (e.key === 'Enter') {
                e.preventDefault();
                verifyMemberAjax();
            }
        });
    }
    if (walletUsed) {
        walletUsed.addEventListener('input', recalcRemaining);
    }

    function recalc(){
        var q = (unitWrap.classList.contains('hidden')) ? (parseInt(qty.value)||1) : 1;
        var p = parseFloat(price.value)||0;
        var d = parseFloat(disc.value)||0;
        var tax = Math.max(0, (p * q) - d);
        var gst = Math.round(tax * 0.05 * 100) / 100;
        var tot = tax + gst;

        taxableVal.textContent = money(tax);
        gstVal.textContent = '+ ' + money(gst);
        finalAmt.textContent = money(tot);
        finalAmt.setAttribute('data-raw', tot);
        if (retailPayable) retailPayable.textContent = money(tot);

        // Update loan amount suggestion for retail finance
        if (downPay && loanAmt) {
            var dpVal = parseFloat(downPay.value) || 0;
            var remLoan = Math.max(0, tot - dpVal);
            loanAmt.value = remLoan.toFixed(2);
        }

        updateWalletFields();
    }

    window._recalcPOS = recalc;

    [price, disc, qty].forEach(function(el){ el && el.addEventListener('input', recalc); });

    if (downPay) {
        downPay.addEventListener('input', function(){
            var tot = parseFloat(finalAmt.getAttribute('data-raw') || 0);
            var dp = parseFloat(downPay.value) || 0;
            if (loanAmt) {
                loanAmt.value = Math.max(0, tot - dp).toFixed(2);
            }
        });
    }

    prod.addEventListener('change', function(){
        var opt = prod.options[prod.selectedIndex];
        unitWrap.classList.add('hidden'); qtyWrap.classList.add('hidden');
        unitSel.innerHTML = '<option value="">—</option>'; unitIdents.textContent = ''; stockHint.textContent = '';

        var specsBox = document.getElementById('productSpecsBox');
        var sMilWrap = document.getElementById('specMileageWrap');
        var sBatWrap = document.getElementById('specBatteryWrap');
        var sWarWrap = document.getElementById('specWarrantyWrap');
        var vMil = document.getElementById('valMileage');
        var vBat = document.getElementById('valBattery');
        var vWar = document.getElementById('valWarranty');

        if (!prod.value) {
            if (specsBox) specsBox.classList.add('hidden');
            return;
        }

        var m = opt.getAttribute('data-mileage') || '';
        var b = opt.getAttribute('data-battery') || '';
        var w = opt.getAttribute('data-warranty') || '';

        if (specsBox) {
            if (m || b || w) {
                if (m) { vMil.textContent = m; sMilWrap.classList.remove('hidden'); } else { sMilWrap.classList.add('hidden'); }
                if (b) { vBat.textContent = b; sBatWrap.classList.remove('hidden'); } else { sBatWrap.classList.add('hidden'); }
                if (w) { vWar.textContent = w; sWarWrap.classList.remove('hidden'); } else { sWarWrap.classList.add('hidden'); }
                specsBox.classList.remove('hidden');
                if (window.lucide) window.lucide.createIcons();
            } else {
                specsBox.classList.add('hidden');
            }
        }

        price.value = opt.getAttribute('data-price') || '';
        fetch('pos.php?ajax=product&product_id=' + encodeURIComponent(prod.value))
            .then(function(r){ return r.json(); })
            .then(function(d){
                if (!d || !d.ok) return;
                if (d.is_serialized) {
                    unitWrap.classList.remove('hidden');
                    unitSel.required = true;
                    (d.units || []).forEach(function(u){
                        var o = document.createElement('option');
                        o.value = u.id; o.textContent = u.unit_no;
                        o.dataset.idents = Object.entries(u.idents || {}).map(function(kv){ return kv[0]+': '+kv[1]; }).join('  ·  ');
                        unitSel.appendChild(o);
                    });
                    stockHint.textContent = d.available + ' unit(s) available';
                } else {
                    qtyWrap.classList.remove('hidden');
                    unitSel.required = false;
                    qty.max = d.available;
                    stockHint.textContent = d.available + ' in stock';
                }
                recalc();
            });
    });
    unitSel.addEventListener('change', function(){
        var o = unitSel.options[unitSel.selectedIndex];
        unitIdents.textContent = (o && o.dataset.idents) ? o.dataset.idents : '';
    });

    function syncCust(){
        if (!newCust) return;
        var isNew = newCust.checked;
        document.querySelectorAll('.nc-only').forEach(function(el){ el.classList.toggle('hidden', !isNew); });
        if (isNew) { custRef.value = '0'; custRef.disabled = true; } else { custRef.disabled = false; }
    }
    if (newCust) newCust.addEventListener('change', syncCust);
    if (custRef) custRef.addEventListener('change', function(){ if (custRef.value !== '0' && newCust) { newCust.checked = false; syncCust(); } });
    syncCust();
    recalc();

    f.addEventListener('submit', function(e){
        var bType = document.getElementById('buyer_type').value;

        if (bType === 'RETAIL') {
            // Validate retail customer fields
            var rName = (retailName ? retailName.value : '').trim();
            var rMob  = (retailMobile ? retailMobile.value : '').trim();
            if (!rName) {
                alert('Please enter the Retail Customer\'s Name.');
                e.preventDefault();
                retailName.focus();
                return false;
            }
            if (!rMob) {
                alert('Please enter the Retail Customer\'s Mobile number.');
                e.preventDefault();
                retailMobile.focus();
                return false;
            }

            // Sync into general customer_name and new_mobile so backend receives them
            custName.value = rName;
            newMobile.value = rMob;
            var rEmail = document.getElementById('retail_email');
            var nEmail = document.getElementById('new_email');
            if (rEmail && nEmail) nEmail.value = rEmail.value;

            // Check finance validation if finance selected
            var rOpt = document.querySelector('input[name="retail_pay_option"]:checked');
            if (rOpt && rOpt.value === 'FINANCE') {
                var doRef = (document.getElementById('loan_ref_no') ? document.getElementById('loan_ref_no').value : '').trim();
                if (!doRef) {
                    alert('Please enter the Bank Loan DO / Sanction Reference Number.');
                    e.preventDefault();
                    document.getElementById('loan_ref_no').focus();
                    return false;
                }
                var fDoc = document.getElementById('finance_doc');
                if (fDoc && fDoc.files.length === 0) {
                    alert('Please upload the Bank Sanction Letter / Delivery Order (DO) document.');
                    e.preventDefault();
                    fDoc.focus();
                    return false;
                }
            } else {
                // Retail Direct
                var rReceipt = document.getElementById('payment_receipt_retail');
                var mReceipt = document.getElementById('payment_receipt');
                if (rReceipt && rReceipt.files.length > 0 && mReceipt) {
                    // Files can't be assigned directly, backend reads payment_receipt_retail or payment_receipt
                }
            }

        } else {
            // MLM MEMBER validation
            var wVal = parseFloat(walletUsed.value) || 0;
            if (wVal > 0) {
                var val = (memberInput ? memberInput.value : '').trim();
                if (!val || !verifiedMember) {
                    alert('Wallet payment requires a verified Member ID. Please enter and verify the buyer\'s Member ID first.');
                    e.preventDefault();
                    if (memberInput) memberInput.focus();
                    return false;
                }
                if ((verifiedMember.scooter_wallet || 0) < wVal) {
                    alert('Insufficient Scooter Wallet balance!\nMember has: ' + money(verifiedMember.scooter_wallet) + '\nRequested: ' + money(wVal));
                    e.preventDefault();
                    return false;
                }
            }
            var rem = parseFloat(finalAmt.getAttribute('data-raw') || 0) - wVal;
            if (rem > 0 && (payMethod.value.includes('Bank') || payMethod.value.includes('QR') || payMethod.value.includes('UPI'))) {
                if (!bankUtr.value.trim()) {
                    if (!confirm('You have not entered a Bank UTR / Reference number for the remaining balance (' + money(rem) + '). Proceed anyway?')) {
                        e.preventDefault();
                        bankUtr.focus();
                        return false;
                    }
                }
            }
        }
    });
})();
</script>
</body>
</html>
