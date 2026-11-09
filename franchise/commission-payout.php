<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/franchise_finance.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection failed.');
}

$fid   = (int)$franchiseId;
$fcode = (string)$franchiseCode;
$fname = (string)$franchiseName;
$ftype = strtoupper((string)$franchiseType);

// Ensure payout requests table exists
$conn->query("
CREATE TABLE IF NOT EXISTS `franchise_payout_requests` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `request_no` VARCHAR(50) NOT NULL UNIQUE,
  `franchise_id` INT NOT NULL,
  `franchise_code` VARCHAR(50) NOT NULL,
  `franchise_name` VARCHAR(150) NULL,
  `franchise_type` VARCHAR(50) NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `bank_name` VARCHAR(100) NULL,
  `account_number` VARCHAR(100) NULL,
  `ifsc_code` VARCHAR(50) NULL,
  `account_holder` VARCHAR(150) NULL,
  `payment_mode` VARCHAR(50) NOT NULL DEFAULT 'Bank Transfer',
  `remarks` VARCHAR(255) NULL,
  `status` ENUM('Pending','Approved','Paid','Rejected') NOT NULL DEFAULT 'Pending',
  `admin_notes` VARCHAR(255) NULL,
  `utr_number` VARCHAR(100) NULL,
  `requested_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `processed_at` DATETIME NULL,
  INDEX `idx_fr_id` (`franchise_id`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

$successMsg = '';
$errorMsg   = '';

// 1. Calculate Total Earned Commissions
$totEarned = 0.0;

// From commission_ledger (Showroom Sales + Sponsor Commissions)
$sCl = $conn->prepare("SELECT COALESCE(SUM(commission_amount), 0) AS s FROM commission_ledger WHERE receiver_id = ? OR receiver_id = ?");
if ($sCl) {
    $sCl->bind_param("ss", $fid, $fcode);
    $sCl->execute();
    $totEarned += (float)($sCl->get_result()->fetch_assoc()['s'] ?? 0);
    $sCl->close();
}

// From franchise_cashback_ledger (Monthly ₹5L Investment Cashback)
if (frf_tbl($conn, 'franchise_cashback_ledger')) {
    $sCb = $conn->prepare("SELECT COALESCE(SUM(amount), 0) AS s FROM franchise_cashback_ledger WHERE franchise_id = ?");
    if ($sCb) {
        $sCb->bind_param("i", $fid);
        $sCb->execute();
        $totEarned += (float)($sCb->get_result()->fetch_assoc()['s'] ?? 0);
        $sCb->close();
    }
}

// 2. Calculate Total Already Paid Out
$totPaid = 0.0;

// From franchise_payout_requests (status = 'Paid')
$sPrPaid = $conn->prepare("SELECT COALESCE(SUM(amount), 0) AS s FROM franchise_payout_requests WHERE franchise_id = ? AND status = 'Paid'");
if ($sPrPaid) {
    $sPrPaid->bind_param("i", $fid);
    $sPrPaid->execute();
    $totPaid += (float)($sPrPaid->get_result()->fetch_assoc()['s'] ?? 0);
    $sPrPaid->close();
}

// From franchise_payout_closing if any
if (frf_tbl($conn, 'franchise_payout_closing')) {
    $sPc = $conn->prepare("SELECT COALESCE(SUM(total_commission), 0) AS s FROM franchise_payout_closing WHERE (receiver_id = ? OR receiver_id = ?) AND status = 'Paid'");
    if ($sPc) {
        $sPc->bind_param("ss", $fid, $fcode);
        $sPc->execute();
        $closingPaid = (float)($sPc->get_result()->fetch_assoc()['s'] ?? 0);
        // Only add if not duplicate with requests
        if ($closingPaid > $totPaid) {
            $totPaid = $closingPaid;
        }
        $sPc->close();
    }
}

// 3. Calculate Total In Pending / Under Process Requests
$totPendingReq = 0.0;
$sPrPend = $conn->prepare("SELECT COALESCE(SUM(amount), 0) AS s FROM franchise_payout_requests WHERE franchise_id = ? AND status IN ('Pending', 'Approved')");
if ($sPrPend) {
    $sPrPend->bind_param("i", $fid);
    $sPrPend->execute();
    $totPendingReq = (float)($sPrPend->get_result()->fetch_assoc()['s'] ?? 0);
    $sPrPend->close();
}

// Available Withdrawable Balance
$availableBal = max(0.0, round($totEarned - $totPaid - $totPendingReq, 2));

// Pre-fill Bank Details from latest request or franchise row
$defBank   = $franchise['bank_name'] ?? '';
$defAcc    = $franchise['account_number'] ?? ($franchise['account_no'] ?? '');
$defIfsc   = $franchise['ifsc_code'] ?? ($franchise['ifsc'] ?? '');
$defHolder = $franchise['account_holder'] ?? ($franchise['holder_name'] ?? $fname);

// Check if a previous payout request had bank details saved
$lastPr = $conn->query("SELECT bank_name, account_number, ifsc_code, account_holder FROM franchise_payout_requests WHERE franchise_id = {$fid} ORDER BY id DESC LIMIT 1");
if ($lastPr && ($lp = $lastPr->fetch_assoc())) {
    if (!empty($lp['bank_name']))      $defBank   = $lp['bank_name'];
    if (!empty($lp['account_number'])) $defAcc    = $lp['account_number'];
    if (!empty($lp['ifsc_code']))      $defIfsc   = $lp['ifsc_code'];
    if (!empty($lp['account_holder'])) $defHolder = $lp['account_holder'];
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_payout'])) {
    $reqAmount   = max(0.0, round((float)($_POST['amount'] ?? 0), 2));
    $bankName    = trim((string)($_POST['bank_name'] ?? ''));
    $accNo       = trim((string)($_POST['account_number'] ?? ''));
    $ifscCode    = strtoupper(trim((string)($_POST['ifsc_code'] ?? '')));
    $accHolder   = trim((string)($_POST['account_holder'] ?? ''));
    $payMode     = trim((string)($_POST['payment_mode'] ?? 'NEFT / RTGS'));
    $remarks     = trim((string)($_POST['remarks'] ?? ''));

    if ($reqAmount < 500) {
        $errorMsg = "Minimum withdrawal amount is ₹500.00.";
    } elseif ($reqAmount > $availableBal) {
        $errorMsg = "Requested amount (₹" . number_format($reqAmount, 2) . ") exceeds available withdrawable balance (₹" . number_format($availableBal, 2) . ").";
    } elseif ($bankName === '' || $accNo === '' || $ifscCode === '') {
        $errorMsg = "Please enter complete Bank Name, Account Number, and IFSC Code.";
    } else {
        $reqNo = 'FPR-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
        
        $ins = $conn->prepare("INSERT INTO franchise_payout_requests
            (request_no, franchise_id, franchise_code, franchise_name, franchise_type, amount, bank_name, account_number, ifsc_code, account_holder, payment_mode, remarks, status, requested_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', NOW())");
        if ($ins) {
            $ins->bind_param("sisssdssssss", $reqNo, $fid, $fcode, $fname, $ftype, $reqAmount, $bankName, $accNo, $ifscCode, $accHolder, $payMode, $remarks);
            if ($ins->execute()) {
                $successMsg = "Payout request {$reqNo} for ₹" . number_format($reqAmount, 2) . " submitted successfully! Central finance team will process this into your bank account.";
                // Refresh balances
                $totPendingReq += $reqAmount;
                $availableBal   = max(0.0, round($availableBal - $reqAmount, 2));
            } else {
                $errorMsg = "Failed to submit request: " . $conn->error;
            }
            $ins->close();
        } else {
            $errorMsg = "Database error: " . $conn->error;
        }
    }
}

// Fetch Payout Requests History
$payoutRequests = [];
$resPr = $conn->query("SELECT * FROM franchise_payout_requests WHERE franchise_id = {$fid} ORDER BY id DESC LIMIT 100");
if ($resPr) {
    while ($r = $resPr->fetch_assoc()) {
        $payoutRequests[] = $r;
    }
}

function m_fmt($n) { return '₹' . number_format((float)$n, 2); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Commission Payout & Withdrawal | Franchise Panel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', system-ui, sans-serif; }
    </style>
</head>
<body class="bg-[#F8FAFC] text-slate-800">

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="ml-64 min-h-screen flex flex-col">
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <main class="flex-1 p-5 sm:p-7 max-w-7xl w-full mx-auto space-y-6">

        <!-- Header -->
        <div class="bg-gradient-to-r from-[#103258] to-[#1C4573] rounded-2xl p-6 text-white shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-white/10 text-white/90 border border-white/20 mb-2">
                    <i data-lucide="wallet" class="w-3.5 h-3.5"></i> Showroom Financials
                </span>
                <h1 class="text-2xl font-bold tracking-tight">Commission Payout & Bank Withdrawal</h1>
                <p class="text-sm text-slate-200 mt-1">Manage EV sales commissions, sponsor bonuses, and request bank payouts.</p>
            </div>
            <a href="commission-report.php" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white/10 hover:bg-white/20 text-white text-xs font-bold transition border border-white/20 self-start sm:self-auto">
                <i data-lucide="bar-chart-2" class="w-4 h-4"></i> View Sales Ledger
            </a>
        </div>

        <?php if ($successMsg !== ''): ?>
            <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-center gap-3">
                <i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-600 shrink-0"></i>
                <span><?php echo htmlspecialchars($successMsg); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($errorMsg !== ''): ?>
            <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm flex items-center gap-3">
                <i data-lucide="alert-circle" class="w-5 h-5 text-rose-600 shrink-0"></i>
                <span><?php echo htmlspecialchars($errorMsg); ?></span>
            </div>
        <?php endif; ?>

        <!-- KPI Balance Summary Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Available Withdrawable -->
            <div class="bg-white rounded-2xl border-2 border-emerald-500 p-5 shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-emerald-700">Available to Withdraw</span>
                    <span class="w-9 h-9 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center">
                        <i data-lucide="arrow-down-to-dot" class="w-5 h-5"></i>
                    </span>
                </div>
                <div class="mt-3 text-2xl sm:text-3xl font-extrabold text-[#103258]">
                    <?php echo m_fmt($availableBal); ?>
                </div>
                <p class="mt-1 text-xs text-slate-500">Ready for instant bank payout</p>
            </div>

            <!-- Total Earned -->
            <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Total Commissions Earned</span>
                    <span class="w-9 h-9 rounded-xl bg-blue-50 text-[#103258] flex items-center justify-center">
                        <i data-lucide="badge-percent" class="w-5 h-5"></i>
                    </span>
                </div>
                <div class="mt-3 text-2xl sm:text-3xl font-extrabold text-slate-900">
                    <?php echo m_fmt($totEarned); ?>
                </div>
                <p class="mt-1 text-xs text-slate-500">Sales (10%) + Sponsor (5%) + Cashback</p>
            </div>

            <!-- Pending Requests -->
            <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-amber-600">Under Process</span>
                    <span class="w-9 h-9 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
                        <i data-lucide="hourglass" class="w-5 h-5"></i>
                    </span>
                </div>
                <div class="mt-3 text-2xl sm:text-3xl font-extrabold text-slate-900">
                    <?php echo m_fmt($totPendingReq); ?>
                </div>
                <p class="mt-1 text-xs text-slate-500">Awaiting bank transfer approval</p>
            </div>

            <!-- Total Paid Out -->
            <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Total Paid Out</span>
                    <span class="w-9 h-9 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center">
                        <i data-lucide="landmark" class="w-5 h-5"></i>
                    </span>
                </div>
                <div class="mt-3 text-2xl sm:text-3xl font-extrabold text-slate-900">
                    <?php echo m_fmt($totPaid); ?>
                </div>
                <p class="mt-1 text-xs text-slate-500">Successfully transferred to bank</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Request Withdrawal Form -->
            <div class="lg:col-span-1 bg-white rounded-2xl border border-slate-200 p-6 shadow-xs h-fit">
                <div class="flex items-center gap-3 pb-4 border-b border-slate-100">
                    <span class="w-10 h-10 rounded-xl bg-[#103258] text-white flex items-center justify-center shadow-xs">
                        <i data-lucide="send" class="w-5 h-5"></i>
                    </span>
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Request Bank Payout</h2>
                        <p class="text-xs text-slate-500">Transfer commissions to bank</p>
                    </div>
                </div>

                <form method="POST" action="" class="mt-5 space-y-4">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">
                            Withdrawal Amount (₹) <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 font-bold">₹</span>
                            <input type="number" step="0.01" min="500" max="<?php echo $availableBal; ?>" name="amount" required
                                   value="<?php echo $availableBal > 0 ? htmlspecialchars((string)$availableBal) : ''; ?>"
                                   placeholder="Min ₹500"
                                   class="w-full pl-8 pr-4 py-2.5 rounded-xl border border-slate-200 focus:border-[#103258] focus:ring-1 focus:ring-[#103258] outline-none text-sm font-semibold text-slate-900 transition">
                        </div>
                        <p class="text-[11px] text-slate-400 mt-1">Available: <?php echo m_fmt($availableBal); ?></p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">
                            Account Holder Name <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" name="account_holder" required value="<?php echo htmlspecialchars($defHolder); ?>"
                               placeholder="As per bank passbook"
                               class="w-full px-3.5 py-2 rounded-xl border border-slate-200 focus:border-[#103258] focus:ring-1 focus:ring-[#103258] outline-none text-xs font-medium text-slate-800 transition">
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">
                            Bank Name <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" name="bank_name" required value="<?php echo htmlspecialchars($defBank); ?>"
                               placeholder="e.g. State Bank of India"
                               class="w-full px-3.5 py-2 rounded-xl border border-slate-200 focus:border-[#103258] focus:ring-1 focus:ring-[#103258] outline-none text-xs font-medium text-slate-800 transition">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">
                                Account No. <span class="text-rose-500">*</span>
                            </label>
                            <input type="text" name="account_number" required value="<?php echo htmlspecialchars($defAcc); ?>"
                                   placeholder="Account number"
                                   class="w-full px-3.5 py-2 rounded-xl border border-slate-200 focus:border-[#103258] focus:ring-1 focus:ring-[#103258] outline-none text-xs font-mono text-slate-800 transition">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">
                                IFSC Code <span class="text-rose-500">*</span>
                            </label>
                            <input type="text" name="ifsc_code" required value="<?php echo htmlspecialchars($defIfsc); ?>"
                                   placeholder="e.g. SBIN0001234"
                                   class="w-full px-3.5 py-2 rounded-xl border border-slate-200 focus:border-[#103258] focus:ring-1 focus:ring-[#103258] outline-none text-xs font-mono uppercase text-slate-800 transition">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">
                            Transfer Mode
                        </label>
                        <select name="payment_mode" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 focus:border-[#103258] focus:ring-1 focus:ring-[#103258] outline-none text-xs font-medium text-slate-800 transition bg-white">
                            <option value="NEFT / RTGS">NEFT / RTGS Bank Transfer</option>
                            <option value="IMPS">IMPS Instant Bank Transfer</option>
                            <option value="UPI">UPI Transfer</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">
                            Notes / Remarks
                        </label>
                        <input type="text" name="remarks" placeholder="Optional notes for finance"
                               class="w-full px-3.5 py-2 rounded-xl border border-slate-200 focus:border-[#103258] focus:ring-1 focus:ring-[#103258] outline-none text-xs font-medium text-slate-800 transition">
                    </div>

                    <button type="submit" name="submit_payout"
                            <?php echo $availableBal < 500 ? 'disabled' : ''; ?>
                            class="w-full py-3 rounded-xl bg-[#103258] hover:bg-[#0c2644] text-white font-bold text-xs uppercase tracking-wider shadow-sm transition flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                        <i data-lucide="check-circle" class="w-4 h-4"></i> Submit Payout Request
                    </button>
                </form>
            </div>

            <!-- Payout Request History Table -->
            <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 p-6 shadow-xs flex flex-col">
                <div class="flex items-center justify-between pb-4 border-b border-slate-100 flex-wrap gap-2">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Payout Requests Ledger</h2>
                        <p class="text-xs text-slate-500">Track status of past bank withdrawal requests</p>
                    </div>
                    <span class="text-xs font-semibold px-2.5 py-1 rounded-lg bg-slate-100 text-slate-600">
                        Total Requests: <?php echo count($payoutRequests); ?>
                    </span>
                </div>

                <div class="mt-4 flex-1 overflow-x-auto">
                    <?php if (empty($payoutRequests)): ?>
                        <div class="py-12 text-center text-slate-400">
                            <i data-lucide="receipt" class="w-10 h-10 mx-auto text-slate-300 stroke-[1.5] mb-2"></i>
                            <p class="text-sm font-semibold text-slate-600">No payout requests yet</p>
                            <p class="text-xs text-slate-400 mt-0.5">When you submit a withdrawal request, it will appear here.</p>
                        </div>
                    <?php else: ?>
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="border-b border-slate-100 text-slate-400 font-bold uppercase tracking-wider">
                                    <th class="pb-3 px-3">Request #</th>
                                    <th class="pb-3 px-3">Date</th>
                                    <th class="pb-3 px-3">Amount</th>
                                    <th class="pb-3 px-3">Bank Details</th>
                                    <th class="pb-3 px-3">Status</th>
                                    <th class="pb-3 px-3">Admin UTR / Note</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-slate-700">
                                <?php foreach ($payoutRequests as $pr):
                                    $st = (string)$pr['status'];
                                    $badge = match ($st) {
                                        'Paid'     => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                        'Approved' => 'bg-blue-50 text-blue-700 border-blue-200',
                                        'Rejected' => 'bg-rose-50 text-rose-700 border-rose-200',
                                        default    => 'bg-amber-50 text-amber-700 border-amber-200',
                                    };
                                ?>
                                    <tr class="hover:bg-slate-50/70 transition">
                                        <td class="py-3 px-3 font-mono font-bold text-slate-900 whitespace-nowrap">
                                            <?php echo htmlspecialchars($pr['request_no']); ?>
                                        </td>
                                        <td class="py-3 px-3 text-slate-500 whitespace-nowrap">
                                            <?php echo date('d M Y, h:i A', strtotime($pr['requested_at'])); ?>
                                        </td>
                                        <td class="py-3 px-3 font-bold text-slate-900 whitespace-nowrap">
                                            <?php echo m_fmt($pr['amount']); ?>
                                        </td>
                                        <td class="py-3 px-3">
                                            <div class="font-medium text-slate-800"><?php echo htmlspecialchars($pr['bank_name'] ?: 'Bank'); ?></div>
                                            <div class="text-[11px] font-mono text-slate-400">
                                                Acc: <?php echo htmlspecialchars(substr((string)$pr['account_number'], -4) ? ('***' . substr((string)$pr['account_number'], -4)) : '-'); ?> · <?php echo htmlspecialchars($pr['ifsc_code'] ?: ''); ?>
                                            </div>
                                        </td>
                                        <td class="py-3 px-3 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold border <?php echo $badge; ?>">
                                                <?php echo htmlspecialchars($st); ?>
                                            </span>
                                        </td>
                                        <td class="py-3 px-3 text-[11px] text-slate-500 max-w-[180px] truncate">
                                            <?php if (!empty($pr['utr_number'])): ?>
                                                <span class="font-mono font-bold text-emerald-700">UTR: <?php echo htmlspecialchars($pr['utr_number']); ?></span>
                                            <?php elseif (!empty($pr['admin_notes'])): ?>
                                                <?php echo htmlspecialchars($pr['admin_notes']); ?>
                                            <?php else: ?>
                                                <span class="text-slate-300">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

        </div>

    </main>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        if (typeof lucide !== 'undefined') lucide.createIcons();
    });
</script>

</body>
</html>

