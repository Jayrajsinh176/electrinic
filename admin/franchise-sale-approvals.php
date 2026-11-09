<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/../includes/income_common.php';
require_once __DIR__ . '/../includes/ind_income_functions.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.");
}

$conn->set_charset('utf8mb4');

function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function adm_money($n): string
{
    return '₹' . number_format((float)$n, 2);
}

$adminUser = $_SESSION['admin_user'] ?? $_SESSION['full_name'] ?? 'Admin Head Office';
$msg = '';
$err = '';

/* =========================================================================
   POST ACTIONS: APPROVE & ISSUE INVOICE / REJECT
   ========================================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? ''));
    $saleId = (int)($_POST['sale_id'] ?? 0);

    if ($saleId <= 0) {
        $err = 'Invalid sale request ID.';
    } elseif ($action === 'approve_sale') {
        try {
            $conn->begin_transaction();

            // Fetch sale record
            $st = $conn->prepare("SELECT cp.*, fh.franchise_code AS h_fcode, fh.name AS h_fname, fh.state AS h_state
                                  FROM fr_customer_purchases cp
                                  LEFT JOIN franchies_holder fh ON fh.id = cp.franchise_id
                                  WHERE cp.id = ? FOR UPDATE");
            $st->bind_param('i', $saleId);
            $st->execute();
            $sale = $st->get_result()->fetch_assoc();
            $st->close();

            if (!$sale) throw new RuntimeException("Sale record #{$saleId} not found.");
            if ($sale['approval_status'] === 'Approved') throw new RuntimeException("Sale #{$saleId} has already been approved.");

            $fcode = trim((string)(($sale['h_fcode'] ?? '') ?: ($sale['franchise_code'] ?? '') ?: ('FR' . ($sale['franchise_id'] ?? ''))));
            $taxable    = max(0.0, round((float)($_POST['taxable_amount'] ?? $sale['taxable_amount'] ?? $sale['unit_price']), 2));
            $gstRate    = 5.00;
            $cgst       = max(0.0, round((float)($_POST['cgst_amount'] ?? ($taxable * 0.025)), 2));
            $sgst       = max(0.0, round((float)($_POST['sgst_amount'] ?? ($taxable * 0.025)), 2));
            $igst       = max(0.0, round((float)($_POST['igst_amount'] ?? 0.0), 2));
            $rto        = max(0.0, round((float)($_POST['rto_charges'] ?? 0.0), 2));
            $ins        = max(0.0, round((float)($_POST['insurance_charges'] ?? 0.0), 2));
            $acc        = max(0.0, round((float)($_POST['accessories_charges'] ?? 0.0), 2));
            $sub        = max(0.0, round((float)($_POST['subsidy_discount'] ?? 0.0), 2));
            $finalTotal = max(0.0, round(($taxable + $cgst + $sgst + $igst + $rto + $ins + $acc) - $sub, 2));

            // Franchise commission: 10% on basic taxable price
            $commAmt = max(0.0, round((float)($_POST['commission_amount'] ?? ($taxable * 0.10)), 2));

            // Generate Official GST Tax Invoice Number
            $invPrefix = 'INV-' . date('Y') . '-';
            $invNo = $invPrefix . str_pad((string)$saleId, 5, '0', STR_PAD_LEFT);

            // 1. Update fr_customer_purchases
            $upd = $conn->prepare("UPDATE fr_customer_purchases SET
                invoice_no = ?,
                taxable_amount = ?,
                gst_rate = ?,
                cgst_amount = ?,
                sgst_amount = ?,
                igst_amount = ?,
                rto_charges = ?,
                insurance_charges = ?,
                accessories_charges = ?,
                subsidy_discount = ?,
                final_invoice_amount = ?,
                sale_amount = ?,
                approval_status = 'Approved',
                sale_status = 'Completed',
                delivery_status = 'Delivered',
                approved_by = ?,
                approved_at = NOW(),
                sale_date = COALESCE(NULLIF(sale_date, '0000-00-00'), CURDATE()),
                franchise_commission_amount = ?,
                commission_status = 'Credited'
                WHERE id = ?");
            
            if (!$upd) throw new RuntimeException("Prepare error: " . $conn->error);
            $upd->bind_param('sdddddddddddsdi',
                $invNo, $taxable, $gstRate, $cgst, $sgst, $igst, $rto, $ins, $acc, $sub,
                $finalTotal, $finalTotal, $adminUser, $commAmt, $saleId
            );
            $upd->execute();
            $upd->close();

            // 2. Update fr_units status to Delivered
            $unitId = (int)$sale['unit_id'];
            if ($unitId > 0) {
                $uUpd = $conn->prepare("UPDATE fr_units SET
                    status = 'Delivered',
                    invoice_no = ?,
                    sale_amount = ?,
                    delivery_date = CURDATE(),
                    remarks = CONCAT(COALESCE(remarks,''), ' [Billed: ', ?, ']')
                    WHERE id = ?");
                if ($uUpd) {
                    $uUpd->bind_param('sdsi', $invNo, $finalTotal, $invNo, $unitId);
                    $uUpd->execute();
                    $uUpd->close();
                }
            }

            // 3. Credit Franchise Commission into commission_ledger
            if ($commAmt > 0 && $fcode !== '') {
                $insComm = $conn->prepare("INSERT INTO commission_ledger
                    (sale_id, receiver_type, receiver_id, commission_percent, commission_amount, from_sale_type, remarks, created_at, payout_status)
                    VALUES (?, 'FRANCHISE', ?, 10.00, ?, 'SHOWROOM_SALE', ?, NOW(), 'Pending')");
                if ($insComm) {
                    $cRem = "Franchise EV Sale Commission · Inv #{$invNo} · " . ($sale['product_name'] ?: 'Vehicle');
                    $insComm->bind_param('isds', $saleId, $fcode, $commAmt, $cRem);
                    $insComm->execute();
                    $insComm->close();
                }
            }

            // 4. Distribute 16% EV Purchase Commission if buyer is an MLM member (NOT Retail)
            $buyerType = (string)($sale['buyer_type'] ?? 'MEMBER');
            $isRetail = ($buyerType === 'RETAIL');

            if (!$isRetail) {
                $buyerMemberId = '';
                if (!empty($sale['remarks']) && preg_match('/Member ID:\s*([A-Za-z0-9_-]+)/i', (string)$sale['remarks'], $mMatch)) {
                    $buyerMemberId = trim($mMatch[1]);
                }
                if ($buyerMemberId === '' && !empty($sale['customer_id'])) {
                    $chkM = $conn->query("SELECT customer_id FROM signup_users WHERE customer_id = '" . $conn->real_escape_string($sale['customer_id']) . "' OR id = " . (int)$sale['customer_id'] . " LIMIT 1");
                    if ($chkM && ($cmRow = $chkM->fetch_assoc())) {
                        $buyerMemberId = (string)$cmRow['customer_id'];
                    }
                }

                if ($buyerMemberId !== '' && function_exists('ind_distribute_vehicle_commissions')) {
                    $modelName = trim((string)($sale['model'] ?: $sale['product_name']));
                    $commRes = ind_distribute_vehicle_commissions($conn, $buyerMemberId, $modelName, $invNo, $finalTotal);
                    if (!empty($commRes['ok'])) {
                        $dirAmt = (float)($commRes['direct_commission']['gross'] ?? 0.0);
                        $totAmt = (float)($commRes['total_distributed'] ?? 0.0);
                        $commNote = "16% EV Comm Distributed: ₹" . number_format($totAmt, 2) . " (Direct: ₹" . number_format($dirAmt, 2) . ", Buyer: {$buyerMemberId})";
                        $conn->query("UPDATE fr_customer_purchases SET remarks = CONCAT(COALESCE(remarks,''), ' [{$commNote}]') WHERE id = " . $saleId);
                    }
                }
            } else {
                $commNote = "Retail Walk-in Sale · 10% Franchise Commission Credited";
                $conn->query("UPDATE fr_customer_purchases SET remarks = CONCAT(COALESCE(remarks,''), ' [{$commNote}]') WHERE id = " . $saleId);
            }

            $conn->commit();
            $msg = "Sale #{$saleId} successfully approved! Official GST Tax Invoice <strong>{$invNo}</strong> issued and franchise commission credited.";

        } catch (\Throwable $e) {
            $conn->rollback();
            $err = "Approval failed: " . $e->getMessage();
        }
    } elseif ($action === 'reject_sale') {
        try {
            $conn->begin_transaction();
            $reason = trim((string)($_POST['rejection_reason'] ?? 'Rejected by Admin'));

            // Fetch sale record
            $st = $conn->prepare("SELECT * FROM fr_customer_purchases WHERE id = ? FOR UPDATE");
            $st->bind_param('i', $saleId);
            $st->execute();
            $sale = $st->get_result()->fetch_assoc();
            $st->close();

            if (!$sale) throw new RuntimeException("Sale record #{$saleId} not found.");
            if ($sale['approval_status'] === 'Approved') throw new RuntimeException("Cannot reject an already approved sale.");

            // 1. Update purchase row
            $upd = $conn->prepare("UPDATE fr_customer_purchases SET
                approval_status = 'Rejected',
                sale_status = 'Cancelled',
                rejection_reason = ?,
                approved_by = ?,
                approved_at = NOW()
                WHERE id = ?");
            $upd->bind_param('ssi', $reason, $adminUser, $saleId);
            $upd->execute();
            $upd->close();

            // 2. Release unit status back to Available
            $unitId = (int)$sale['unit_id'];
            if ($unitId > 0) {
                $conn->query("UPDATE fr_units SET
                    status = 'Available',
                    customer_name = NULL,
                    customer_id = NULL,
                    customer_ref = NULL,
                    sale_amount = 0.00,
                    remarks = CONCAT(COALESCE(remarks,''), ' [Released: Booking Rejected by Admin]')
                    WHERE id = " . $unitId);
            }

            // 3. Refund held Member Wallet balance if used
            $walletUsed = (float)($sale['wallet_amount_used'] ?? 0.0);
            $buyerMemberId = '';
            if (!empty($sale['remarks']) && preg_match('/Member ID:\s*([A-Za-z0-9_-]+)/i', (string)$sale['remarks'], $mMatch)) {
                $buyerMemberId = trim($mMatch[1]);
            }
            if ($walletUsed > 0 && $buyerMemberId !== '') {
                $uUser = $conn->prepare("UPDATE signup_users SET scooter_wallet_balance = scooter_wallet_balance + ? WHERE customer_id = ?");
                if ($uUser) {
                    $uUser->bind_param('ds', $walletUsed, $buyerMemberId);
                    $uUser->execute();
                    $uUser->close();
                }
                if (function_exists('wallet_log_transaction')) {
                    $curBalRow = $conn->query("SELECT scooter_wallet_balance FROM signup_users WHERE customer_id = '" . $conn->real_escape_string($buyerMemberId) . "' LIMIT 1")->fetch_assoc();
                    $newBal = (float)($curBalRow['scooter_wallet_balance'] ?? 0.0);
                    wallet_log_transaction($conn, $buyerMemberId, 'credit', 'scooter_booking_refund', $walletUsed, $newBal, [
                        'counterparty_id' => 'ADMIN',
                        'ref_type'        => 'pos_sale_reject',
                        'note'            => "REFUND: Booking #{$saleId} rejected by Admin. Reason: {$reason}",
                    ]);
                }
            }

            $conn->commit();
            $msg = "Sale #{$saleId} has been rejected. Unit reserved was released back to Available inventory.";

        } catch (\Throwable $e) {
            $conn->rollback();
            $err = "Rejection failed: " . $e->getMessage();
        }
    }
}

/* =========================================================================
   LIST & STATS
   ========================================================================= */
$tab = trim((string)($_GET['tab'] ?? 'Pending'));
$search = trim((string)($_GET['search'] ?? ''));

$stats = [
    'total'          => (int)($conn->query("SELECT COUNT(*) AS c FROM fr_customer_purchases")->fetch_assoc()['c'] ?? 0),
    'pending'        => (int)($conn->query("SELECT COUNT(*) AS c FROM fr_customer_purchases WHERE approval_status = 'Pending_Approval' OR approval_status IS NULL OR approval_status = ''")->fetch_assoc()['c'] ?? 0),
    'member'         => (int)($conn->query("SELECT COUNT(*) AS c FROM fr_customer_purchases WHERE buyer_type = 'MEMBER' OR buyer_type IS NULL OR buyer_type = ''")->fetch_assoc()['c'] ?? 0),
    'retail_cash'    => (int)($conn->query("SELECT COUNT(*) AS c FROM fr_customer_purchases WHERE buyer_type = 'RETAIL' AND (finance_status = 'NONE' OR finance_status IS NULL OR finance_status = '')")->fetch_assoc()['c'] ?? 0),
    'retail_finance' => (int)($conn->query("SELECT COUNT(*) AS c FROM fr_customer_purchases WHERE buyer_type = 'RETAIL' AND finance_status = 'FINANCED'")->fetch_assoc()['c'] ?? 0),
    'approved'       => (int)($conn->query("SELECT COUNT(*) AS c FROM fr_customer_purchases WHERE approval_status = 'Approved'")->fetch_assoc()['c'] ?? 0),
    'revenue'        => (float)($conn->query("SELECT COALESCE(SUM(final_invoice_amount), SUM(sale_amount), 0) AS s FROM fr_customer_purchases WHERE approval_status = 'Approved'")->fetch_assoc()['s'] ?? 0),
];

// Query
$where = "WHERE 1=1";
$params = [];
$types = '';

if ($tab === 'Pending') {
    $where .= " AND (cp.approval_status = 'Pending_Approval' OR cp.approval_status IS NULL OR cp.approval_status = '')";
} elseif ($tab === 'Member') {
    $where .= " AND (cp.buyer_type = 'MEMBER' OR cp.buyer_type IS NULL OR cp.buyer_type = '')";
} elseif ($tab === 'RetailCash') {
    $where .= " AND (cp.buyer_type = 'RETAIL' AND (cp.finance_status = 'NONE' OR cp.finance_status IS NULL OR cp.finance_status = ''))";
} elseif ($tab === 'RetailFinance') {
    $where .= " AND (cp.buyer_type = 'RETAIL' AND cp.finance_status = 'FINANCED')";
} elseif ($tab === 'Approved') {
    $where .= " AND cp.approval_status = 'Approved'";
} elseif ($tab === 'Rejected') {
    $where .= " AND cp.approval_status = 'Rejected'";
}

if ($search !== '') {
    $where .= " AND (cp.invoice_no LIKE ? OR cp.customer_name LIKE ? OR cp.product_name LIKE ? OR cp.bank_utr LIKE ? OR fh.franchise_code LIKE ? OR fh.name LIKE ?)";
    $like = "%$search%";
    $params = array_merge($params, [$like, $like, $like, $like, $like, $like]);
    $types .= 'ssssss';
}

$sql = "SELECT cp.*,
               fh.franchise_code AS h_fcode, fh.name AS h_fname, fh.city AS h_city, fh.state AS h_state,
               u.unit_no, u.status AS unit_status
        FROM fr_customer_purchases cp
        LEFT JOIN franchies_holder fh ON fh.id = cp.franchise_id
        LEFT JOIN fr_units u ON u.id = cp.unit_id
        $where
        ORDER BY cp.id DESC LIMIT 200";

$stmt = $conn->prepare($sql);
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Franchise Sale Approvals &amp; Billing — Admin Panel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .modal-active { display: flex !important; }
    </style>
</head>
<body class="bg-[#f3f3f3] font-sans overflow-x-hidden">

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="ml-64 min-h-screen bg-[#f3f3f3]">
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <main class="px-5 py-6">
        <div class="max-w-[1300px] mx-auto space-y-6">

            <!-- Title header -->
            <div class="mb-4 flex items-center justify-between gap-5 flex-wrap">
                <div class="flex items-center gap-4">
                    <div class="flex h-[44px] w-[44px] shrink-0 items-center justify-center rounded-[10px] bg-[#f6eee2]">
                        <i data-lucide="file-check" class="h-5 w-5 text-[#cf9a3c]"></i>
                    </div>
                    <div>
                        <h1 class="text-[24px] font-semibold text-[#1f2937]" style="font-family: Georgia, 'Times New Roman', serif;">
                            Franchise Sale Approvals &amp; Billing
                        </h1>
                        <p class="text-[12px] text-gray-500">Centralized billing authority: verify payments, configure GST &amp; RTO, generate official invoices, and release franchise commission.</p>
                    </div>
                </div>
            </div>

            <!-- Alerts -->
            <?php if ($msg): ?>
                <div class="rounded-xl border border-green-200 bg-green-50 p-4 text-[13px] text-green-800 flex items-center gap-2">
                    <i data-lucide="check-circle" class="w-4 h-4 text-green-600 shrink-0"></i>
                    <div><?php echo $msg; ?></div>
                </div>
            <?php endif; ?>
            <?php if ($err): ?>
                <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-[13px] text-red-800 flex items-center gap-2">
                    <i data-lucide="alert-triangle" class="w-4 h-4 text-red-600 shrink-0"></i>
                    <div><?php echo e($err); ?></div>
                </div>
            <?php endif; ?>

            <!-- STATS TILES -->
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3.5">
                <div class="bg-white border border-gray-200 rounded-xl p-3.5 shadow-sm">
                    <div class="flex items-center justify-between text-gray-400 text-[11px] uppercase tracking-wider font-semibold">
                        <span>Total Requests</span>
                        <i data-lucide="files" class="w-4 h-4 text-blue-500"></i>
                    </div>
                    <div class="mt-1 text-[22px] font-bold text-gray-800"><?php echo $stats['total']; ?></div>
                </div>

                <div class="bg-white border border-amber-200 rounded-xl p-3.5 shadow-sm bg-gradient-to-br from-white to-amber-50/40">
                    <div class="flex items-center justify-between text-amber-700 text-[11px] uppercase tracking-wider font-semibold">
                        <span>Pending Review</span>
                        <i data-lucide="clock" class="w-4 h-4 text-amber-500"></i>
                    </div>
                    <div class="mt-1 text-[22px] font-bold text-amber-700"><?php echo $stats['pending']; ?></div>
                </div>

                <div class="bg-white border border-purple-200 rounded-xl p-3.5 shadow-sm bg-gradient-to-br from-white to-purple-50/40">
                    <div class="flex items-center justify-between text-purple-700 text-[11px] uppercase tracking-wider font-semibold">
                        <span>Retail Finance</span>
                        <i data-lucide="landmark" class="w-4 h-4 text-purple-600"></i>
                    </div>
                    <div class="mt-1 text-[22px] font-bold text-purple-800"><?php echo $stats['retail_finance']; ?></div>
                </div>

                <div class="bg-white border border-green-200 rounded-xl p-3.5 shadow-sm bg-gradient-to-br from-white to-green-50/40">
                    <div class="flex items-center justify-between text-green-700 text-[11px] uppercase tracking-wider font-semibold">
                        <span>Approved &amp; Billed</span>
                        <i data-lucide="check-check" class="w-4 h-4 text-green-500"></i>
                    </div>
                    <div class="mt-1 text-[22px] font-bold text-green-700"><?php echo $stats['approved']; ?></div>
                </div>

                <div class="bg-white border border-gray-200 rounded-xl p-3.5 shadow-sm">
                    <div class="flex items-center justify-between text-gray-400 text-[11px] uppercase tracking-wider font-semibold">
                        <span>Total Invoiced</span>
                        <i data-lucide="indian-rupee" class="w-4 h-4 text-[#143a66]"></i>
                    </div>
                    <div class="mt-1 text-[20px] font-bold text-[#143a66]"><?php echo adm_money($stats['revenue']); ?></div>
                </div>
            </div>

            <!-- TABS & FILTER -->
            <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm flex flex-col lg:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-1.5 border border-gray-200 rounded-lg p-1 bg-gray-50 flex-wrap">
                    <?php
                    $tabs = [
                        'Pending'       => 'Pending Review (' . $stats['pending'] . ')',
                        'Member'        => 'Member (' . $stats['member'] . ')',
                        'RetailCash'    => 'Retail Cash (' . $stats['retail_cash'] . ')',
                        'RetailFinance' => 'Retail Bank Finance (' . $stats['retail_finance'] . ')',
                        'Approved'      => 'Approved (' . $stats['approved'] . ')',
                        'Rejected'      => 'Rejected',
                        'All'           => 'All (' . $stats['total'] . ')',
                    ];
                    foreach ($tabs as $k => $label):
                        $active = ($tab === $k);
                    ?>
                        <a href="franchise-sale-approvals.php?tab=<?php echo urlencode($k); ?>&search=<?php echo urlencode($search); ?>"
                           class="px-3 py-1.5 rounded-md text-[12px] font-medium transition whitespace-nowrap <?php echo $active ? 'bg-[#143a66] text-white shadow-sm' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-200/50'; ?>">
                            <?php echo $label; ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <form method="get" class="flex gap-2 w-full sm:w-auto">
                    <input type="hidden" name="tab" value="<?php echo e($tab); ?>">
                    <input type="text" name="search" value="<?php echo e($search); ?>" placeholder="Search franchise, customer, UTR, vehicle..."
                           class="border border-gray-300 rounded-lg px-3 py-1.5 text-[13px] w-full sm:w-64 focus:outline-none focus:border-[#143a66]">
                    <button class="bg-[#143a66] hover:bg-[#0f3255] text-white px-3.5 py-1.5 rounded-lg text-[13px] font-medium flex items-center gap-1.5">
                        <i data-lucide="search" class="w-3.5 h-3.5"></i>
                    </button>
                    <?php if ($search !== ''): ?>
                        <a href="franchise-sale-approvals.php?tab=<?php echo urlencode($tab); ?>" class="border border-gray-300 px-3 py-1.5 rounded-lg text-[13px] text-gray-500 hover:bg-gray-100">Clear</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- SALES TABLE -->
            <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-[13px]">
                        <thead class="bg-gray-50 border-b border-gray-200 text-gray-600 text-[12px]">
                            <tr>
                                <th class="text-left px-5 py-3">Req ID / Date</th>
                                <th class="text-left px-5 py-3">Franchise Showroom</th>
                                <th class="text-left px-5 py-3">Customer / Buyer</th>
                                <th class="text-left px-5 py-3">Vehicle / Unit</th>
                                <th class="text-right px-5 py-3">Amount</th>
                                <th class="text-left px-5 py-3">Payment Info</th>
                                <th class="text-center px-5 py-3">Status</th>
                                <th class="text-right px-5 py-3">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($rows)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-12 text-gray-400">
                                        <i data-lucide="inbox" class="w-8 h-8 mx-auto mb-2 opacity-50"></i>
                                        No sale requests found in this tab.
                                    </td>
                                </tr>
                            <?php endif; ?>
                            <?php foreach ($rows as $r):
                                $appSt = ($r['approval_status'] ?? '') ?: 'Pending_Approval';
                                $fcode = ($r['h_fcode'] ?? '') ?: ($r['franchise_code'] ?? '') ?: ('FR' . ($r['franchise_id'] ?? ''));
                                $fname = ($r['h_fname'] ?? '') ?: 'Franchise';
                                $city  = ($r['h_city'] ?? '');
                                $walletUsed = (float)($r['wallet_amount_used'] ?? 0);
                                $bankAmt = (float)($r['bank_amount_paid'] ?? 0);
                                $totalAmt = (float)(!empty($r['final_invoice_amount']) ? $r['final_invoice_amount'] : ($r['sale_amount'] ?? 0));
                            ?>
                                <tr class="hover:bg-gray-50/80 transition">
                                    <td class="px-5 py-3 whitespace-nowrap">
                                        <span class="font-mono font-semibold text-gray-800">#REQ-<?php echo str_pad((string)$r['id'], 5, '0', STR_PAD_LEFT); ?></span>
                                        <div class="text-[11px] text-gray-400"><?php echo e(substr((string)$r['created_at'], 0, 16)); ?></div>
                                    </td>

                                    <td class="px-5 py-3">
                                        <div class="font-medium text-gray-900"><?php echo e($fname); ?></div>
                                        <div class="text-[11px] text-gray-500 font-mono"><?php echo e($fcode); ?><?php if ($city): ?> · <?php echo e($city); ?><?php endif; ?></div>
                                    </td>

                                    <td class="px-5 py-3">
                                        <div class="font-medium text-gray-900"><?php echo e($r['customer_name'] ?: 'Walk-in'); ?></div>
                                        <?php 
                                        $bType = $r['buyer_type'] ?? 'MEMBER';
                                        $fStatus = $r['finance_status'] ?? 'NONE';
                                        if ($bType === 'RETAIL'):
                                            if ($fStatus === 'FINANCED'): ?>
                                                <span class="inline-flex items-center gap-1 text-[10px] bg-purple-100 text-purple-800 font-semibold px-2 py-0.5 rounded-full mt-1">
                                                    <i data-lucide="landmark" class="w-3 h-3"></i> Retail Finance
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center gap-1 text-[10px] bg-slate-100 text-slate-800 font-semibold px-2 py-0.5 rounded-full mt-1">
                                                    <i data-lucide="user" class="w-3 h-3"></i> Retail Cash
                                                </span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 text-[10px] bg-blue-100 text-blue-800 font-semibold px-2 py-0.5 rounded-full mt-1">
                                                <i data-lucide="award" class="w-3 h-3"></i> Member
                                            </span>
                                        <?php endif; ?>
                                        <?php if (!empty($r['remarks'])): ?>
                                            <div class="text-[11px] text-gray-500 mt-1"><?php echo e($r['remarks']); ?></div>
                                        <?php endif; ?>
                                    </td>

                                    <td class="px-5 py-3">
                                        <div class="font-medium text-gray-800"><?php echo e($r['product_name']); ?></div>
                                        <?php if ($r['unit_no']): ?>
                                            <div class="text-[11px] text-emerald-700 font-mono">Chassis: <?php echo e($r['unit_no']); ?></div>
                                        <?php endif; ?>
                                    </td>

                                    <td class="px-5 py-3 text-right whitespace-nowrap">
                                        <div class="font-bold text-[#143a66] text-[14px]"><?php echo adm_money($totalAmt); ?></div>
                                        <div class="text-[11px] text-gray-400">Basic: <?php echo adm_money($r['unit_price']); ?></div>
                                    </td>

                                    <td class="px-5 py-3 text-[12px]">
                                        <?php if ($fStatus === 'FINANCED'): ?>
                                            <div class="font-semibold text-purple-900"><?php echo e($r['financier_name'] ?: 'Bank Financier'); ?></div>
                                            <?php if (!empty($r['loan_ref_no'])): ?>
                                                <div class="text-[11px] font-mono text-purple-700">DO: <?php echo e($r['loan_ref_no']); ?></div>
                                            <?php endif; ?>
                                            <div class="text-[11px] text-gray-600">Down: <?php echo adm_money($r['down_payment_amount'] ?? 0); ?> · Loan: <?php echo adm_money($r['loan_amount'] ?? 0); ?></div>
                                            <div class="flex items-center gap-2 mt-0.5">
                                                <?php if (!empty($r['finance_doc'])): ?>
                                                    <a href="../franchise/<?php echo e($r['finance_doc']); ?>" target="_blank" class="text-purple-700 font-semibold text-[11px] hover:underline inline-flex items-center gap-1">
                                                        <i data-lucide="file-text" class="w-3 h-3"></i> Bank DO
                                                    </a>
                                                <?php endif; ?>
                                                <?php if (!empty($r['payment_receipt'])): ?>
                                                    <a href="../franchise/<?php echo e($r['payment_receipt']); ?>" target="_blank" class="text-blue-600 text-[11px] hover:underline inline-flex items-center gap-1">
                                                        <i data-lucide="paperclip" class="w-3 h-3"></i> Receipt
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <?php if ($walletUsed > 0): ?>
                                                <div class="text-emerald-700 font-medium">Wallet: <?php echo adm_money($walletUsed); ?></div>
                                            <?php endif; ?>
                                            <?php if ($bankAmt > 0): ?>
                                                <div class="text-gray-700">Bank: <?php echo adm_money($bankAmt); ?></div>
                                            <?php endif; ?>
                                            <?php if ($r['bank_utr']): ?>
                                                <div class="text-gray-600 font-mono text-[11px]">UTR: <span class="font-semibold"><?php echo e($r['bank_utr']); ?></span></div>
                                            <?php endif; ?>
                                            <?php if (!empty($r['payment_receipt'])): ?>
                                                <a href="../franchise/<?php echo e($r['payment_receipt']); ?>" target="_blank" class="text-blue-600 text-[11px] hover:underline inline-flex items-center gap-1 mt-0.5">
                                                    <i data-lucide="paperclip" class="w-3 h-3"></i> View Receipt
                                                </a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>

                                    <td class="px-5 py-3 text-center whitespace-nowrap">
                                        <?php if ($appSt === 'Approved'): ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-800">
                                                <i data-lucide="check" class="w-3 h-3"></i> Approved
                                            </span>
                                            <div class="text-[10px] text-emerald-700 font-mono mt-0.5"><?php echo e($r['invoice_no']); ?></div>
                                        <?php elseif ($appSt === 'Rejected'): ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-red-100 text-red-800">
                                                <i data-lucide="x" class="w-3 h-3"></i> Rejected
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-amber-100 text-amber-800 animate-pulse">
                                                <i data-lucide="clock" class="w-3 h-3"></i> Pending Review
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="px-5 py-3 text-right whitespace-nowrap">
                                        <?php if ($appSt === 'Approved'): ?>
                                            <a href="../franchise/sale-invoice.php?id=<?php echo (int)$r['id']; ?>" target="_blank"
                                               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[12px] font-semibold bg-emerald-50 text-emerald-800 border border-emerald-300 hover:bg-emerald-100">
                                                <i data-lucide="printer" class="w-3.5 h-3.5"></i> Tax Invoice
                                            </a>
                                        <?php elseif ($appSt === 'Rejected'): ?>
                                            <span class="text-[12px] text-gray-400">Cancelled</span>
                                        <?php else: ?>
                                            <button type="button" onclick="openReviewModal(<?php echo htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8'); ?>)"
                                                    class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg text-[12px] font-semibold bg-[#143a66] text-white hover:bg-[#0f3255] shadow-sm transition">
                                                <i data-lucide="file-check" class="w-3.5 h-3.5"></i> Review &amp; Bill
                                            </button>
                                        <?php endif; ?>
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

<!-- =========================================================================
     REVIEW & BILLING MODAL
     ========================================================================= -->
<div id="reviewModal" class="fixed inset-0 z-50 hidden bg-black/60 backdrop-blur-sm items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-2xl max-w-3xl w-full shadow-2xl border border-gray-200 overflow-hidden my-8">
        
        <!-- Modal header -->
        <div class="px-6 py-4 bg-[#143a66] text-white flex items-center justify-between">
            <div>
                <h3 class="text-[17px] font-semibold flex items-center gap-2">
                    <i data-lucide="shield-check" class="w-5 h-5 text-amber-400"></i>
                    Review Booking &amp; Issue Official GST Tax Invoice
                </h3>
                <p id="mSubhead" class="text-[12px] text-white/80 mt-0.5">Request #REQ-00000</p>
            </div>
            <button type="button" onclick="closeReviewModal()" class="text-white/80 hover:text-white p-1 rounded-lg">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form method="post" id="approvalForm" class="p-6 space-y-6">
            <input type="hidden" name="action" id="modalAction" value="approve_sale">
            <input type="hidden" name="sale_id" id="mSaleId" value="0">

            <!-- Summary 2-column info -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-[13px]">
                <div class="bg-gray-50 border border-gray-200 rounded-xl p-3.5 space-y-2">
                    <h4 class="font-semibold text-gray-800 text-[12px] uppercase tracking-wider flex items-center gap-1.5 text-blue-900">
                        <i data-lucide="store" class="w-4 h-4 text-blue-700"></i> Franchise &amp; Customer
                    </h4>
                    <div><span class="text-gray-500">Franchise:</span> <strong id="mFranchise" class="text-gray-900"></strong></div>
                    <div><span class="text-gray-500">Customer:</span> <strong id="mCustomer" class="text-gray-900"></strong></div>
                    <div id="mRemarksWrap"><span class="text-gray-500">Member Info:</span> <span id="mRemarks" class="text-blue-700 font-semibold font-mono"></span></div>
                    <div id="mTypeNotice" class="text-[11px] p-2 rounded bg-blue-50 text-blue-800 border border-blue-200"></div>
                </div>

                <div class="bg-gray-50 border border-gray-200 rounded-xl p-3.5 space-y-2">
                    <h4 class="font-semibold text-gray-800 text-[12px] uppercase tracking-wider flex items-center gap-1.5 text-emerald-900">
                        <i data-lucide="bike" class="w-4 h-4 text-emerald-700"></i> Vehicle Selected
                    </h4>
                    <div><span class="text-gray-500">Model:</span> <strong id="mProduct" class="text-gray-900"></strong></div>
                    <div><span class="text-gray-500">Chassis No:</span> <strong id="mChassis" class="text-emerald-800 font-mono"></strong></div>
                    <div><span class="text-gray-500">Inventory Status:</span> <span class="bg-amber-100 text-amber-800 text-[11px] font-semibold px-2 py-0.5 rounded">Reserved</span></div>
                </div>
            </div>

            <!-- Retail Finance Block (Hidden unless financed) -->
            <div id="mFinanceWrap" class="hidden bg-purple-50/80 border border-purple-200 rounded-xl p-4 text-[13px] space-y-2">
                <div class="flex items-center justify-between">
                    <h4 class="font-semibold text-purple-900 text-[12px] uppercase tracking-wider flex items-center gap-1.5">
                        <i data-lucide="landmark" class="w-4 h-4 text-purple-700"></i> Vehicle Bank Finance &amp; Delivery Order (DO)
                    </h4>
                    <span class="text-[11px] bg-purple-200 text-purple-900 px-2 py-0.5 rounded font-semibold">Bank Hypothecated</span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <span class="text-gray-500 text-[11px] block">Financier / Bank</span>
                        <strong id="mFinancierName" class="text-purple-950 text-[14px]">—</strong>
                    </div>
                    <div>
                        <span class="text-gray-500 text-[11px] block">DO / Sanction Reference</span>
                        <strong id="mLoanRefNo" class="text-purple-950 font-mono text-[14px]">—</strong>
                    </div>
                    <div>
                        <span class="text-gray-500 text-[11px] block">Sanctioned Loan Amount</span>
                        <strong id="mLoanAmount" class="text-purple-950 text-[14px]">₹0.00</strong>
                    </div>
                </div>
                <div id="mFinanceDocWrap" class="pt-2 border-t border-purple-200 hidden">
                    <a id="mFinanceDocLink" href="#" target="_blank" class="inline-flex items-center gap-1.5 text-[12px] font-semibold text-purple-800 hover:underline">
                        <i data-lucide="file-text" class="w-4 h-4"></i> View Bank Delivery Order (DO) / Sanction Letter Document
                    </a>
                </div>
            </div>

            <!-- Payment Proof Verification -->
            <div class="bg-amber-50/70 border border-amber-200 rounded-xl p-4 text-[13px] space-y-2">
                <h4 class="font-semibold text-amber-900 text-[12px] uppercase tracking-wider flex items-center gap-1.5">
                    <i data-lucide="wallet" class="w-4 h-4 text-amber-700"></i> Verified Payment Breakdown
                </h4>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <span class="text-gray-500 text-[11px] block">Wallet Used</span>
                        <strong id="mWalletPaid" class="text-emerald-700 text-[15px]">₹0.00</strong>
                    </div>
                    <div>
                        <span class="text-gray-500 text-[11px] block">Bank / QR Payment</span>
                        <strong id="mBankPaid" class="text-gray-800 text-[15px]">₹0.00</strong>
                    </div>
                    <div>
                        <span class="text-gray-500 text-[11px] block">Bank UTR / Reference</span>
                        <strong id="mBankUtr" class="text-gray-900 font-mono text-[14px]">—</strong>
                    </div>
                </div>
                <div id="mReceiptLinkWrap" class="pt-2 border-t border-amber-200 hidden">
                    <a id="mReceiptLink" href="#" target="_blank" class="inline-flex items-center gap-1 text-[12px] font-semibold text-blue-700 hover:underline">
                        <i data-lucide="external-link" class="w-3.5 h-3.5"></i> Click to Open Attached Bank Receipt / Screenshot
                    </a>
                </div>
            </div>

            <!-- Interactive GST & On-Road Calculation -->
            <div class="border border-gray-200 rounded-xl p-4 space-y-3 bg-white">
                <h4 class="font-semibold text-[#143a66] text-[13px] uppercase tracking-wider flex items-center justify-between">
                    <span class="flex items-center gap-1.5"><i data-lucide="calculator" class="w-4 h-4 text-[#143a66]"></i> Tax &amp; Official Invoicing Breakdown</span>
                    <span class="text-[11px] text-gray-400 font-normal normal-case">Option B (Basic + 5% GST)</span>
                </h4>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 text-[13px]">
                    <div>
                        <label class="block text-[11px] font-semibold text-gray-600 mb-1">Basic Taxable Price (₹) *</label>
                        <input type="number" step="0.01" name="taxable_amount" id="cTaxable" required
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-[13px] font-semibold text-gray-900">
                    </div>

                    <div>
                        <label class="block text-[11px] font-semibold text-gray-600 mb-1">CGST 2.5% (₹)</label>
                        <input type="number" step="0.01" name="cgst_amount" id="cCgst" readonly
                               class="w-full border border-gray-200 rounded-lg px-3 py-2 text-[13px] bg-gray-50 text-gray-700">
                    </div>

                    <div>
                        <label class="block text-[11px] font-semibold text-gray-600 mb-1">SGST 2.5% (₹)</label>
                        <input type="number" step="0.01" name="sgst_amount" id="cSgst" readonly
                               class="w-full border border-gray-200 rounded-lg px-3 py-2 text-[13px] bg-gray-50 text-gray-700">
                    </div>

                    <div>
                        <label class="block text-[11px] font-semibold text-gray-600 mb-1">RTO &amp; Road Tax (₹)</label>
                        <input type="number" step="0.01" name="rto_charges" id="cRto" value="0.00"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-[13px]">
                    </div>

                    <div>
                        <label class="block text-[11px] font-semibold text-gray-600 mb-1">Comprehensive Insurance (₹)</label>
                        <input type="number" step="0.01" name="insurance_charges" id="cIns" value="0.00"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-[13px]">
                    </div>

                    <div>
                        <label class="block text-[11px] font-semibold text-gray-600 mb-1">Accessories / Kit (₹)</label>
                        <input type="number" step="0.01" name="accessories_charges" id="cAcc" value="0.00"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-[13px]">
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-[11px] font-semibold text-gray-600 mb-1">Special Discount / Govt Subsidy (₹)</label>
                        <input type="number" step="0.01" name="subsidy_discount" id="cSub" value="0.00"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-[13px] text-red-600">
                    </div>

                    <div>
                        <label class="block text-[11px] font-semibold text-emerald-800 mb-1">Franchise Commission (10%)</label>
                        <input type="number" step="0.01" name="commission_amount" id="cComm" readonly
                               class="w-full border border-emerald-300 rounded-lg px-3 py-2 text-[13px] font-bold bg-emerald-50 text-emerald-900">
                    </div>
                </div>

                <!-- Grand Total Box -->
                <div class="mt-4 p-4 rounded-xl bg-gradient-to-r from-[#143a66] to-[#1e528e] text-white flex items-center justify-between">
                    <div>
                        <span class="text-[12px] text-white/80 uppercase tracking-wider block font-medium">Final Official Tax Invoice Amount</span>
                        <span class="text-[11px] text-amber-300">Basic + 5% GST + RTO + Insurance − Subsidy</span>
                    </div>
                    <div id="cTotalDisplay" class="text-[26px] font-bold text-amber-300">₹0.00</div>
                </div>
            </div>

            <!-- Rejection Reason input (hidden unless rejecting) -->
            <div id="rejectionWrap" class="hidden bg-red-50 border border-red-200 rounded-xl p-4">
                <label class="block text-[12px] font-semibold text-red-800 mb-1">Reason for Rejection *</label>
                <textarea name="rejection_reason" id="rejectionReason" rows="2" placeholder="e.g. UTR payment not found in bank statement, wrong chassis requested..."
                          class="w-full border border-red-300 rounded-lg p-2.5 text-[13px] focus:outline-none focus:border-red-600"></textarea>
            </div>

            <!-- Modal footer actions -->
            <div class="flex items-center justify-between gap-3 pt-3 border-t border-gray-100 flex-wrap">
                <button type="button" onclick="triggerRejectMode()" id="btnRejectToggle"
                        class="px-4 py-2.5 rounded-lg border border-red-300 text-red-700 hover:bg-red-50 text-[13px] font-semibold transition">
                    <i data-lucide="x-circle" class="w-4 h-4 inline mr-1"></i> Reject Request
                </button>

                <div class="flex gap-2">
                    <button type="button" onclick="closeReviewModal()" class="px-4 py-2.5 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-100 text-[13px] font-medium">
                        Cancel
                    </button>
                    <button type="submit" id="btnSubmitApproval" class="px-5 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-[13px] font-semibold flex items-center gap-2 shadow transition">
                        <i data-lucide="check-circle" class="w-4 h-4"></i> Approve &amp; Generate Tax Invoice
                    </button>
                </div>
            </div>
        </form>

    </div>
</div>

<script>
if (typeof lucide !== 'undefined') lucide.createIcons();

var modal = document.getElementById('reviewModal');
var cTaxable = document.getElementById('cTaxable');
var cCgst    = document.getElementById('cCgst');
var cSgst    = document.getElementById('cSgst');
var cRto     = document.getElementById('cRto');
var cIns     = document.getElementById('cIns');
var cAcc     = document.getElementById('cAcc');
var cSub     = document.getElementById('cSub');
var cComm    = document.getElementById('cComm');
var cTotalDisplay = document.getElementById('cTotalDisplay');

function money(n){ return '₹' + (Number(n)||0).toLocaleString('en-IN', {minimumFractionDigits:2, maximumFractionDigits:2}); }

function recalcModal(){
    var tax = parseFloat(cTaxable.value) || 0;
    var cgst = Math.round(tax * 0.025 * 100) / 100;
    var sgst = Math.round(tax * 0.025 * 100) / 100;
    var rto = parseFloat(cRto.value) || 0;
    var ins = parseFloat(cIns.value) || 0;
    var acc = parseFloat(cAcc.value) || 0;
    var sub = parseFloat(cSub.value) || 0;

    cCgst.value = cgst.toFixed(2);
    cSgst.value = sgst.toFixed(2);

    // 10% commission on basic
    var comm = Math.round(tax * 0.10 * 100) / 100;
    cComm.value = comm.toFixed(2);

    var total = Math.max(0, (tax + cgst + sgst + rto + ins + acc) - sub);
    cTotalDisplay.textContent = money(total);
}

[cTaxable, cRto, cIns, cAcc, cSub].forEach(function(el){
    el && el.addEventListener('input', recalcModal);
});

function openReviewModal(row) {
    document.getElementById('mSaleId').value = row.id;
    document.getElementById('mSubhead').textContent = 'Request #REQ-' + String(row.id).padStart(5, '0');
    document.getElementById('mFranchise').textContent = (row.h_fname || 'Franchise') + ' (' + (row.h_fcode || row.franchise_code || '') + ')';
    document.getElementById('mCustomer').textContent = (row.customer_name || 'Walk-in');
    
    var bType = row.buyer_type || 'MEMBER';
    var fStatus = row.finance_status || 'NONE';

    // Type notice
    var mTypeNotice = document.getElementById('mTypeNotice');
    if (mTypeNotice) {
        if (bType === 'RETAIL') {
            mTypeNotice.className = 'text-[11px] p-2 rounded bg-purple-50 text-purple-900 border border-purple-200 mt-1';
            mTypeNotice.innerHTML = '<strong>Retail Customer:</strong> Non-MLM sale. 16% EV network bonus will be skipped. 10% Showroom commission will be credited.';
        } else {
            mTypeNotice.className = 'text-[11px] p-2 rounded bg-blue-50 text-blue-900 border border-blue-200 mt-1';
            mTypeNotice.innerHTML = '<strong>Member Booking:</strong> 16% EV Commission will be distributed across the sponsor tree upon approval.';
        }
    }

    var remWrap = document.getElementById('mRemarksWrap');
    if (row.remarks) {
        remWrap.classList.remove('hidden');
        document.getElementById('mRemarks').textContent = row.remarks;
    } else {
        remWrap.classList.add('hidden');
    }

    // Finance Details
    var finWrap = document.getElementById('mFinanceWrap');
    if (fStatus === 'FINANCED') {
        finWrap.classList.remove('hidden');
        document.getElementById('mFinancierName').textContent = row.financier_name || 'Bank Financier';
        document.getElementById('mLoanRefNo').textContent = row.loan_ref_no || '—';
        document.getElementById('mLoanAmount').textContent = money(row.loan_amount || 0);

        var fDocWrap = document.getElementById('mFinanceDocWrap');
        if (row.finance_doc) {
            fDocWrap.classList.remove('hidden');
            document.getElementById('mFinanceDocLink').href = '../franchise/' + row.finance_doc;
        } else {
            fDocWrap.classList.add('hidden');
        }
    } else {
        finWrap.classList.add('hidden');
    }

    document.getElementById('mProduct').textContent = row.product_name + (row.model ? ' / ' + row.model : '');
    document.getElementById('mChassis').textContent = row.unit_no || '—';
    document.getElementById('mWalletPaid').textContent = money(row.wallet_amount_used || 0);
    document.getElementById('mBankPaid').textContent = money(row.bank_amount_paid || 0);
    document.getElementById('mBankUtr').textContent = row.bank_utr || '—';

    var rWrap = document.getElementById('mReceiptLinkWrap');
    if (row.payment_receipt) {
        rWrap.classList.remove('hidden');
        document.getElementById('mReceiptLink').href = '../franchise/' + row.payment_receipt;
    } else {
        rWrap.classList.add('hidden');
    }

    // Set prices
    var basic = parseFloat(row.taxable_amount || row.unit_price || 0);
    cTaxable.value = basic.toFixed(2);
    cRto.value = parseFloat(row.rto_charges || 0).toFixed(2);
    cIns.value = parseFloat(row.insurance_charges || 0).toFixed(2);
    cAcc.value = parseFloat(row.accessories_charges || 0).toFixed(2);
    cSub.value = parseFloat(row.subsidy_discount || 0).toFixed(2);

    // Reset reject mode
    document.getElementById('modalAction').value = 'approve_sale';
    document.getElementById('rejectionWrap').classList.add('hidden');
    document.getElementById('btnRejectToggle').classList.remove('hidden');
    var btnSubmit = document.getElementById('btnSubmitApproval');
    btnSubmit.className = 'px-5 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-[13px] font-semibold flex items-center gap-2 shadow transition';
    btnSubmit.innerHTML = '<i data-lucide="check-circle" class="w-4 h-4"></i> Approve &amp; Generate Tax Invoice';

    recalcModal();
    modal.classList.add('modal-active');
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function closeReviewModal() {
    modal.classList.remove('modal-active');
}

function triggerRejectMode() {
    var rWrap = document.getElementById('rejectionWrap');
    var actInput = document.getElementById('modalAction');
    var btnSubmit = document.getElementById('btnSubmitApproval');
    var btnRejectToggle = document.getElementById('btnRejectToggle');

    rWrap.classList.remove('hidden');
    actInput.value = 'reject_sale';
    btnRejectToggle.classList.add('hidden');

    btnSubmit.className = 'px-5 py-2.5 rounded-lg bg-red-600 hover:bg-red-700 text-white text-[13px] font-semibold flex items-center gap-2 shadow transition';
    btnSubmit.innerHTML = '<i data-lucide="x-circle" class="w-4 h-4"></i> Confirm Rejection';
    document.getElementById('rejectionReason').focus();
    if (typeof lucide !== 'undefined') lucide.createIcons();
}
</script>
</body>
</html>

