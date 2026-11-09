<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/inventory.php';   // pulls customers.php
if (!isset($conn) || !($conn instanceof mysqli)) die('Database connection failed.');

$fid   = (int)$franchiseId;
$fcode = (string)$franchiseCode;

// Ensure any purchases without sale_date are safely set to their creation date
$conn->query("UPDATE fr_customer_purchases SET sale_date = DATE(created_at) WHERE (sale_date IS NULL OR sale_date = '0000-00-00') AND created_at IS NOT NULL");

/* --- Filters & Presets --- */
$range  = trim((string)($_GET['range'] ?? ''));
$from   = trim((string)($_GET['from'] ?? ''));
$to     = trim((string)($_GET['to'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$q      = trim((string)($_GET['q'] ?? ''));

// Handle quick presets (Today, 7 days, 30 days, 90 days, All)
if ($range === 'today') {
    $from = date('Y-m-d');
    $to   = date('Y-m-d');
} elseif ($range === '7') {
    $from = date('Y-m-d', strtotime('-6 days'));
    $to   = date('Y-m-d');
} elseif ($range === '30') {
    $from = date('Y-m-d', strtotime('-29 days'));
    $to   = date('Y-m-d');
} elseif ($range === '90') {
    $from = date('Y-m-d', strtotime('-89 days'));
    $to   = date('Y-m-d');
} elseif ($range === 'all') {
    $from = '';
    $to   = '';
}

// Summary counts for this franchise
$statAll = (int)($conn->query("SELECT COUNT(*) AS c FROM fr_customer_purchases WHERE franchise_id = $fid")->fetch_assoc()['c'] ?? 0);
$statPending = (int)($conn->query("SELECT COUNT(*) AS c FROM fr_customer_purchases WHERE franchise_id = $fid AND (approval_status = 'Pending_Approval' OR approval_status IS NULL OR approval_status = '')")->fetch_assoc()['c'] ?? 0);
$statApproved = (int)($conn->query("SELECT COUNT(*) AS c FROM fr_customer_purchases WHERE franchise_id = $fid AND approval_status = 'Approved'")->fetch_assoc()['c'] ?? 0);
$statRejected = (int)($conn->query("SELECT COUNT(*) AS c FROM fr_customer_purchases WHERE franchise_id = $fid AND approval_status = 'Rejected'")->fetch_assoc()['c'] ?? 0);

/* --- Build Query --- */
$where  = ["cp.franchise_id = ?"];
$types  = "i";
$params = [$fid];

if ($from !== '') {
    $where[] = "COALESCE(NULLIF(cp.sale_date, '0000-00-00'), DATE(cp.created_at)) >= ?";
    $types  .= "s";
    $params[] = $from;
}
if ($to !== '') {
    $where[] = "COALESCE(NULLIF(cp.sale_date, '0000-00-00'), DATE(cp.created_at)) <= ?";
    $types  .= "s";
    $params[] = $to;
}
if ($status !== '') {
    if ($status === 'Pending') {
        $where[] = "(cp.approval_status = 'Pending_Approval' OR cp.approval_status IS NULL OR cp.approval_status = '')";
    } elseif ($status === 'Approved') {
        $where[] = "cp.approval_status = 'Approved'";
    } elseif ($status === 'Rejected') {
        $where[] = "cp.approval_status = 'Rejected'";
    }
}
if ($q !== '') {
    $where[] = "(cp.product_name LIKE ? OR cp.model LIKE ? OR cp.invoice_no LIKE ? OR cp.customer_name LIKE ? OR cp.bank_utr LIKE ? OR cp.id = ?)";
    $like = "%$q%";
    $qNum = is_numeric($q) ? (int)$q : 0;
    $types .= "sssssi";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $qNum;
}

$whereSql = "WHERE " . implode(" AND ", $where);

/* --- Count for Pagination --- */
$cntStmt = $conn->prepare("SELECT COUNT(*) AS total FROM fr_customer_purchases cp $whereSql");
if ($types !== '') {
    $cntStmt->bind_param($types, ...$params);
}
$cntStmt->execute();
$totalRecords = (int)($cntStmt->get_result()->fetch_assoc()['total'] ?? 0);
$cntStmt->close();

/* --- Pagination calculation --- */
$perPage = 10;
$totalPages = max(1, (int)ceil($totalRecords / $perPage));
$page = max(1, min($totalPages, (int)($_GET['page'] ?? 1)));
$offset = ($page - 1) * $perPage;

/* --- Fetch Page Rows --- */
$sql = "SELECT cp.*,
               c.customer_code, c.mobile AS customer_mobile,
               COALESCE(NULLIF(c.customer_name, ''), cp.customer_name) AS buyer_name,
               u.unit_no, u.status AS unit_status,
               pr.name AS current_product_name, pr.model AS current_model
        FROM fr_customer_purchases cp
        LEFT JOIN fr_customers c ON c.id = cp.customer_id
        LEFT JOIN fr_units u ON u.id = cp.unit_id
        LEFT JOIN fr_products pr ON pr.id = cp.product_id
        $whereSql
        ORDER BY COALESCE(NULLIF(cp.sale_date, '0000-00-00'), DATE(cp.created_at)) DESC, cp.id DESC
        LIMIT ? OFFSET ?";

$pageTypes = $types . 'ii';
$pageParams = array_merge($params, [$perPage, $offset]);

$stmt = $conn->prepare($sql);
$stmt->bind_param($pageTypes, ...$pageParams);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

function sl_money($n) { return '₹' . number_format((float)$n, 2); }

// Helper for pagination URLs preserving filters
function pageUrl($p) {
    $params = $_GET;
    $params['page'] = $p;
    return 'sales.php?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sales &amp; Bookings</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-[#f3f3f3] font-sans overflow-x-hidden">
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<div class="ml-64 min-h-screen bg-[#f3f3f3]">
    <?php include __DIR__ . '/includes/navbar.php'; ?>
    <main class="px-4 sm:px-5 py-6">
        <div class="max-w-7xl mx-auto space-y-6">

            <!-- Banner / Header -->
            <div class="bg-[#17457a] rounded-xl px-5 sm:px-6 py-4 text-white shadow flex items-center justify-between flex-wrap gap-3">
                <div>
                    <h1 class="text-[18px] sm:text-[20px] font-semibold flex items-center gap-2">
                        <i data-lucide="shopping-cart" class="w-5 h-5"></i>
                        Sales &amp; Bookings
                    </h1>
                    <p class="text-[12px] text-white/80 mt-1"><?php echo e($franchiseName); ?> · <?php echo e($franchiseCode); ?> · vehicle booking requests &amp; customer order history</p>
                </div>
                <div class="flex gap-2">
                    <a href="pos.php" class="bg-white/20 hover:bg-white/30 text-white rounded-lg px-3 py-2 text-[12px] font-semibold flex items-center gap-1.5 transition">
                        <i data-lucide="plus-circle" class="w-4 h-4"></i> New Sale / Booking
                    </a>
                    <a href="sales-report.php" class="bg-white/15 hover:bg-white/25 text-white rounded-lg px-3 py-2 text-[12px] font-medium flex items-center gap-1.5 transition">
                        <i data-lucide="bar-chart-3" class="w-4 h-4"></i> Sales Report
                    </a>
                </div>
            </div>

            <?php if (!empty($_GET['submitted'])): 
                $subId = (int)($_GET['id'] ?? 0);
            ?>
                <div class="rounded-xl border border-blue-200 bg-blue-50 p-4 text-[13px] text-blue-900 flex items-center justify-between gap-3 shadow-sm flex-wrap">
                    <div class="flex items-center gap-2.5">
                        <i data-lucide="check-circle" class="w-5 h-5 text-blue-600 shrink-0"></i>
                        <div>
                            <strong>Booking Submitted to Admin!</strong> Request <strong>#REQ-<?php echo str_pad((string)$subId, 5, '0', STR_PAD_LEFT); ?></strong> is now pending Admin approval. The vehicle unit has been safely <strong>Reserved</strong>.
                        </div>
                    </div>
                    <?php if ($subId > 0): ?>
                        <a href="sale-invoice.php?id=<?php echo $subId; ?>" target="_blank"
                           class="bg-blue-600 hover:bg-blue-700 text-white px-3.5 py-1.5 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition">
                            <i data-lucide="printer" class="w-3.5 h-3.5"></i> Print Booking Slip
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Status Quick Counters -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <a href="sales.php" class="bg-white border rounded-xl p-3 shadow-sm hover:border-[#17457a] transition <?php echo ($status === '' && $range === '') ? 'border-[#17457a] ring-2 ring-[#17457a]/10' : 'border-gray-200'; ?>">
                    <div class="text-[11px] text-gray-500 uppercase font-semibold">Total Bookings</div>
                    <div class="text-[20px] font-bold text-gray-800 mt-1"><?php echo $statAll; ?></div>
                </a>
                <a href="sales.php?status=Pending" class="bg-white border rounded-xl p-3 shadow-sm hover:border-amber-400 transition <?php echo $status === 'Pending' ? 'border-amber-500 ring-2 ring-amber-500/10' : 'border-gray-200'; ?>">
                    <div class="text-[11px] text-amber-700 uppercase font-semibold flex items-center gap-1"><i data-lucide="hourglass" class="w-3.5 h-3.5"></i> Pending Admin</div>
                    <div class="text-[20px] font-bold text-amber-600 mt-1"><?php echo $statPending; ?></div>
                </a>
                <a href="sales.php?status=Approved" class="bg-white border rounded-xl p-3 shadow-sm hover:border-emerald-500 transition <?php echo $status === 'Approved' ? 'border-emerald-600 ring-2 ring-emerald-600/10' : 'border-gray-200'; ?>">
                    <div class="text-[11px] text-emerald-700 uppercase font-semibold flex items-center gap-1"><i data-lucide="check-circle" class="w-3.5 h-3.5"></i> Approved &amp; Billed</div>
                    <div class="text-[20px] font-bold text-emerald-600 mt-1"><?php echo $statApproved; ?></div>
                </a>
                <a href="sales.php?status=Rejected" class="bg-white border rounded-xl p-3 shadow-sm hover:border-red-400 transition <?php echo $status === 'Rejected' ? 'border-red-500 ring-2 ring-red-500/10' : 'border-gray-200'; ?>">
                    <div class="text-[11px] text-red-700 uppercase font-semibold flex items-center gap-1"><i data-lucide="x-circle" class="w-3.5 h-3.5"></i> Rejected</div>
                    <div class="text-[20px] font-bold text-red-600 mt-1"><?php echo $statRejected; ?></div>
                </a>
            </div>

            <!-- Filters & Quick Date Presets -->
            <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm space-y-3">
                <div class="flex items-center justify-between flex-wrap gap-2 pb-2 border-b border-gray-100">
                    <div class="flex items-center gap-1.5 flex-wrap text-[12px]">
                        <span class="text-gray-400 font-medium mr-1 flex items-center gap-1"><i data-lucide="calendar" class="w-3.5 h-3.5"></i> Date Preset:</span>
                        <a href="sales.php?range=all<?php echo $status ? '&status=' . urlencode($status) : ''; ?>"
                           class="px-3 py-1 rounded-full border transition <?php echo ($range === 'all' || ($range === '' && $from === '' && $to === '')) ? 'bg-[#17457a] text-white border-[#17457a] font-semibold' : 'bg-gray-50 text-gray-600 border-gray-200 hover:bg-gray-100'; ?>">All Time</a>
                        <a href="sales.php?range=today<?php echo $status ? '&status=' . urlencode($status) : ''; ?>"
                           class="px-3 py-1 rounded-full border transition <?php echo $range === 'today' ? 'bg-[#17457a] text-white border-[#17457a] font-semibold' : 'bg-gray-50 text-gray-600 border-gray-200 hover:bg-gray-100'; ?>">Today</a>
                        <a href="sales.php?range=7<?php echo $status ? '&status=' . urlencode($status) : ''; ?>"
                           class="px-3 py-1 rounded-full border transition <?php echo $range === '7' ? 'bg-[#17457a] text-white border-[#17457a] font-semibold' : 'bg-gray-50 text-gray-600 border-gray-200 hover:bg-gray-100'; ?>">Last 7 Days</a>
                        <a href="sales.php?range=30<?php echo $status ? '&status=' . urlencode($status) : ''; ?>"
                           class="px-3 py-1 rounded-full border transition <?php echo $range === '30' ? 'bg-[#17457a] text-white border-[#17457a] font-semibold' : 'bg-gray-50 text-gray-600 border-gray-200 hover:bg-gray-100'; ?>">Last 30 Days</a>
                        <a href="sales.php?range=90<?php echo $status ? '&status=' . urlencode($status) : ''; ?>"
                           class="px-3 py-1 rounded-full border transition <?php echo $range === '90' ? 'bg-[#17457a] text-white border-[#17457a] font-semibold' : 'bg-gray-50 text-gray-600 border-gray-200 hover:bg-gray-100'; ?>">Last 90 Days</a>
                    </div>
                    <?php if ($range !== '' || $from !== '' || $to !== '' || $status !== '' || $q !== ''): ?>
                        <a href="sales.php" class="text-[12px] text-gray-500 hover:text-red-600 flex items-center gap-1 transition">
                            <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i> Clear Filters
                        </a>
                    <?php endif; ?>
                </div>

                <form method="get" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 text-[13px] items-end">
                    <?php if ($range !== ''): ?><input type="hidden" name="range" value="<?php echo e($range); ?>"><?php endif; ?>
                    <label class="block">
                        <span class="text-gray-500 text-[11px]">From Date</span>
                        <input type="date" name="from" value="<?php echo e($from); ?>" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:border-[#17457a]">
                    </label>
                    <label class="block">
                        <span class="text-gray-500 text-[11px]">To Date</span>
                        <input type="date" name="to" value="<?php echo e($to); ?>" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:border-[#17457a]">
                    </label>
                    <label class="block">
                        <span class="text-gray-500 text-[11px]">Approval Status</span>
                        <select name="status" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 bg-white text-[13px] focus:outline-none focus:border-[#17457a]">
                            <option value="">All Statuses</option>
                            <option value="Pending" <?php echo $status === 'Pending' ? 'selected' : ''; ?>>Pending Admin Review</option>
                            <option value="Approved" <?php echo $status === 'Approved' ? 'selected' : ''; ?>>Approved &amp; Billed</option>
                            <option value="Rejected" <?php echo $status === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </label>
                    <label class="block">
                        <span class="text-gray-500 text-[11px]">Search</span>
                        <input name="q" value="<?php echo e($q); ?>" placeholder="Req #, Invoice, Customer, UTR" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:border-[#17457a]">
                    </label>
                    <div>
                        <button type="submit" class="w-full bg-[#17457a] hover:bg-[#123660] text-white rounded-lg px-4 py-2 text-[13px] font-semibold transition flex items-center justify-center gap-1.5">
                            <i data-lucide="search" class="w-4 h-4"></i> Apply Filter
                        </button>
                    </div>
                </form>
            </div>

            <!-- Booking Records Table (No Export buttons - Pure Operations) -->
            <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between flex-wrap gap-2">
                    <div>
                        <h2 class="text-[14px] font-semibold text-[#173a67]">Showroom Booking History</h2>
                        <span class="text-[12px] text-gray-400">
                            Showing <?php echo $totalRecords > 0 ? ($offset + 1) : 0; ?> to <?php echo min($offset + $perPage, $totalRecords); ?> of <?php echo $totalRecords; ?> bookings
                        </span>
                    </div>
                    <div class="text-[12px] text-gray-500">
                        Page <strong class="text-gray-800"><?php echo $page; ?></strong> of <strong><?php echo $totalPages; ?></strong>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-[13px]">
                        <thead class="bg-gray-50 text-gray-500 text-[12px]">
                            <tr>
                                <th class="text-left px-5 py-2.5">Req # / Date</th>
                                <th class="text-left px-5 py-2.5">Customer / Buyer</th>
                                <th class="text-left px-5 py-2.5">Vehicle Unit</th>
                                <th class="text-right px-5 py-2.5">Amount</th>
                                <th class="text-left px-5 py-2.5">Payment Split</th>
                                <th class="text-center px-5 py-2.5">Status</th>
                                <th class="text-right px-5 py-2.5">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($rows)): ?>
                                <tr>
                                    <td colspan="7" class="px-5 py-10 text-center text-gray-400">
                                        <i data-lucide="inbox" class="w-8 h-8 mx-auto text-gray-300 mb-2"></i>
                                        No bookings found matching your filter criteria.
                                    </td>
                                </tr>
                            <?php endif; ?>
                            <?php foreach ($rows as $r):
                                $pname = $r['current_product_name'] ?: $r['product_name'] ?: 'Vehicle';
                                $mdl   = $r['current_model'] ?: $r['model'];
                                $buyer = trim((string)($r['buyer_name'] ?? ''));
                                $appSt = $r['approval_status'] ?? ($r['sale_status'] === 'Completed' ? 'Approved' : 'Pending_Approval');
                                $dispAmt = (float)($r['final_invoice_amount'] > 0 ? $r['final_invoice_amount'] : $r['sale_amount']);
                                $reqNo = '#REQ-' . str_pad((string)$r['id'], 5, '0', STR_PAD_LEFT);
                                $saleDate = $r['sale_date'] ?: substr((string)$r['created_at'], 0, 10);
                            ?>
                                <tr class="hover:bg-gray-50/70 transition">
                                    <td class="px-5 py-3">
                                        <div class="font-mono font-bold text-[#17457a] text-[13px]"><?php echo e($reqNo); ?></div>
                                        <div class="text-[11px] text-gray-400 mt-0.5"><?php echo e($saleDate); ?></div>
                                    </td>
                                    <td class="px-5 py-3">
                                        <div class="font-semibold text-gray-800"><?php echo e($buyer ?: 'Walk-in'); ?></div>
                                        <?php if (!empty($r['customer_mobile'])): ?>
                                            <div class="text-[11px] text-gray-400"><?php echo e($r['customer_mobile']); ?></div>
                                        <?php endif; ?>
                                        <?php 
                                        $bType = $r['buyer_type'] ?? 'MEMBER';
                                        $fStatus = $r['finance_status'] ?? 'NONE';
                                        if ($bType === 'RETAIL'):
                                            if ($fStatus === 'FINANCED'): ?>
                                                <span class="inline-flex items-center gap-1 text-[10px] bg-purple-100 text-purple-800 font-semibold px-2 py-0.5 rounded-full mt-0.5">
                                                    <i data-lucide="landmark" class="w-3 h-3"></i> Retail Finance
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center gap-1 text-[10px] bg-slate-100 text-slate-800 font-semibold px-2 py-0.5 rounded-full mt-0.5">
                                                    <i data-lucide="user" class="w-3 h-3"></i> Retail Cash
                                                </span>
                                            <?php endif; ?>
                                        <?php elseif (!empty($r['remarks']) && strpos($r['remarks'], 'Member ID:') !== false): ?>
                                            <span class="inline-block text-[10px] bg-blue-50 text-blue-700 font-mono px-1.5 py-0.2 rounded mt-0.5"><?php echo e(trim(str_replace('Member ID:', '', $r['remarks']))); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-5 py-3">
                                        <div class="font-medium text-gray-900"><?php echo e($pname); ?></div>
                                        <?php if ($mdl): ?><div class="text-[11px] text-gray-500"><?php echo e($mdl); ?></div><?php endif; ?>
                                        <?php if (!empty($r['unit_no'])): ?>
                                            <div class="text-[11px] font-mono text-gray-400 mt-0.5">Unit: <?php echo e($r['unit_no']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        <div class="font-bold text-[#17457a] text-[14px]"><?php echo sl_money($dispAmt); ?></div>
                                        <?php if ((float)$r['taxable_amount'] > 0): ?>
                                            <div class="text-[11px] text-gray-400">Basic: <?php echo sl_money($r['taxable_amount']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-5 py-3 text-[12px]">
                                        <?php if ($fStatus === 'FINANCED'): ?>
                                            <div class="font-semibold text-purple-900"><?php echo e($r['financier_name'] ?: 'Financed'); ?></div>
                                            <div class="text-[11px] text-gray-600">Down: <?php echo sl_money($r['down_payment_amount'] ?? 0); ?></div>
                                            <div class="text-[11px] text-gray-600">Loan: <?php echo sl_money($r['loan_amount'] ?? 0); ?></div>
                                        <?php else: ?>
                                            <?php if ((float)($r['wallet_amount_used'] ?? 0) > 0): ?>
                                                <div><span class="text-gray-400">Wallet:</span> <strong class="text-emerald-700"><?php echo sl_money($r['wallet_amount_used']); ?></strong></div>
                                            <?php endif; ?>
                                            <?php if ((float)($r['bank_amount_paid'] ?? 0) > 0): ?>
                                                <div><span class="text-gray-400">Bank:</span> <strong><?php echo sl_money($r['bank_amount_paid']); ?></strong></div>
                                            <?php endif; ?>
                                            <?php if (!empty($r['bank_utr'])): ?>
                                                <div class="text-[10px] font-mono text-gray-500 mt-0.5 truncate max-w-[120px]" title="UTR: <?php echo e($r['bank_utr']); ?>">UTR: <?php echo e($r['bank_utr']); ?></div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-5 py-3 text-center whitespace-nowrap">
                                        <?php if ($appSt === 'Approved'): ?>
                                            <span class="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-800 bg-emerald-50 border border-emerald-200 px-2.5 py-1 rounded-full">
                                                <i data-lucide="check-circle" class="w-3.5 h-3.5 text-emerald-600"></i> Approved
                                            </span>
                                            <?php if (!empty($r['invoice_no'])): ?>
                                                <div class="font-mono text-[10px] text-gray-500 mt-1"><?php echo e($r['invoice_no']); ?></div>
                                            <?php endif; ?>
                                        <?php elseif ($appSt === 'Rejected'): ?>
                                            <span class="inline-flex items-center gap-1 text-[11px] font-bold text-red-800 bg-red-50 border border-red-200 px-2.5 py-1 rounded-full">
                                                <i data-lucide="x-circle" class="w-3.5 h-3.5 text-red-600"></i> Rejected
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 text-[11px] font-bold text-amber-800 bg-amber-50 border border-amber-200 px-2.5 py-1 rounded-full">
                                                <i data-lucide="hourglass" class="w-3.5 h-3.5 text-amber-600"></i> Pending Review
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-5 py-3 text-right whitespace-nowrap">
                                        <?php if ($appSt === 'Approved'): ?>
                                            <a href="sale-invoice.php?id=<?php echo (int)$r['id']; ?>" target="_blank"
                                               class="inline-flex items-center gap-1 text-[12px] font-semibold bg-emerald-50 text-emerald-700 hover:bg-emerald-100 border border-emerald-200 px-2.5 py-1 rounded-lg transition">
                                                <i data-lucide="printer" class="w-3.5 h-3.5"></i> Tax Invoice
                                            </a>
                                        <?php elseif ($appSt === 'Rejected'): ?>
                                            <span class="text-[12px] text-gray-400">Cancelled</span>
                                        <?php else: ?>
                                            <a href="sale-invoice.php?id=<?php echo (int)$r['id']; ?>" target="_blank"
                                               class="inline-flex items-center gap-1 text-[12px] font-semibold bg-amber-50 text-amber-800 hover:bg-amber-100 border border-amber-200 px-2.5 py-1 rounded-lg transition">
                                                <i data-lucide="eye" class="w-3.5 h-3.5"></i> View Details
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Clean Pagination Controls -->
                <?php if ($totalPages > 1): ?>
                    <div class="px-5 py-3.5 border-t border-gray-100 flex items-center justify-between flex-wrap gap-2 text-[12px]">
                        <div class="text-gray-500">
                            Showing page <strong class="text-gray-800"><?php echo $page; ?></strong> of <strong><?php echo $totalPages; ?></strong> (<?php echo $totalRecords; ?> total)
                        </div>
                        <div class="flex items-center gap-1">
                            <!-- Prev Button -->
                            <?php if ($page > 1): ?>
                                <a href="<?php echo pageUrl($page - 1); ?>" class="px-3 py-1.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition flex items-center gap-1">
                                    <i data-lucide="chevron-left" class="w-3.5 h-3.5"></i> Previous
                                </a>
                            <?php else: ?>
                                <span class="px-3 py-1.5 border border-gray-200 rounded-lg text-gray-300 cursor-not-allowed flex items-center gap-1">
                                    <i data-lucide="chevron-left" class="w-3.5 h-3.5"></i> Previous
                                </span>
                            <?php endif; ?>

                            <!-- Page Numbers -->
                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                            for ($p = $startPage; $p <= $endPage; $p++):
                            ?>
                                <a href="<?php echo pageUrl($p); ?>"
                                   class="px-3 py-1.5 border rounded-lg font-medium transition <?php echo $p === $page ? 'bg-[#17457a] text-white border-[#17457a]' : 'border-gray-300 text-gray-700 hover:bg-gray-100'; ?>">
                                    <?php echo $p; ?>
                                </a>
                            <?php endfor; ?>

                            <!-- Next Button -->
                            <?php if ($page < $totalPages): ?>
                                <a href="<?php echo pageUrl($page + 1); ?>" class="px-3 py-1.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition flex items-center gap-1">
                                    Next <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                                </a>
                            <?php else: ?>
                                <span class="px-3 py-1.5 border border-gray-200 rounded-lg text-gray-300 cursor-not-allowed flex items-center gap-1">
                                    Next <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

            </div>

        </div>
    </main>
</div>
<script>if (typeof lucide !== 'undefined') lucide.createIcons();</script>
</body>
</html>
