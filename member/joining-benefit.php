<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['customer_id']) && !isset($_SESSION['member_id'])) {
    header("Location: login.php");
    exit();
}
$memberName = $_SESSION['full_name'] ?? "Member";
$customerId = $_SESSION['customer_id'] ?? $_SESSION['member_id'];

include __DIR__ . '/config/db.php';
if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.");
}
$conn->set_charset("utf8mb4");
require_once __DIR__ . '/../includes/income_common.php';
require_once __DIR__ . '/../includes/ind_income_functions.php';

function money_fmt($n) { return '₹' . number_format((float)$n, 2); }

// Fetch user status and benefit choice from signup_users
$userStatus = 'inactive';
$userBenefitChoice = '';
$userCreatedAt = '';

$uSt = $conn->prepare("SELECT status, benefit_choice, created_at FROM signup_users WHERE customer_id = ? LIMIT 1");
if ($uSt) {
    $uSt->bind_param('s', $customerId);
    $uSt->execute();
    $uRow = $uSt->get_result()->fetch_assoc();
    if ($uRow) {
        $userStatus = strtolower(trim((string)($uRow['status'] ?? 'inactive')));
        $userBenefitChoice = strtolower(trim((string)($uRow['benefit_choice'] ?? '')));
        $userCreatedAt = (string)($uRow['created_at'] ?? '');
    }
    $uSt->close();
}

$isActivated = ($userStatus === 'active');

// Fetch activation request history
$approvedActivation = null;
$aSt = $conn->prepare("SELECT id, amount, payment_mode, benefit_choice, transaction_id, payment_slip, remark, status, created_at 
                       FROM activation_requests 
                       WHERE customer_id = ? 
                       ORDER BY id DESC");
$allRequests = [];
if ($aSt) {
    $aSt->bind_param('s', $customerId);
    $aSt->execute();
    $aRs = $aSt->get_result();
    while ($r = $aRs->fetch_assoc()) {
        $allRequests[] = $r;
        if (strtolower((string)($r['status'] ?? '')) === 'approved' && $approvedActivation === null) {
            $approvedActivation = $r;
        }
    }
    $aSt->close();
}

if ($userBenefitChoice === '' && $approvedActivation && !empty($approvedActivation['benefit_choice'])) {
    $userBenefitChoice = strtolower((string)$approvedActivation['benefit_choice']);
}
if ($userBenefitChoice === '') {
    $userBenefitChoice = 'product';
}

// Fetch active product voucher if any
$memberVoucher = null;
if (income_table_exists($conn, 'ind_vouchers')) {
    $vSt = $conn->prepare("SELECT voucher_code, amount, used_amount, balance_amount, validity_months, expires_at, status, created_at 
                           FROM ind_vouchers 
                           WHERE customer_id = ? 
                           ORDER BY id DESC LIMIT 1");
    if ($vSt) {
        $vSt->bind_param('s', $customerId);
        $vSt->execute();
        $memberVoucher = $vSt->get_result()->fetch_assoc();
        $vSt->close();
    }
}

// Fetch 10-installment cashback schedule if any
$cashbackSchedule = [];
$totalPaidCashback = 0.0;
$paidCount = 0;
$nextUpcoming = null;

if (income_table_exists($conn, 'member_cashback_schedules')) {
    $cbSt = $conn->prepare("SELECT id, installment_no, total_installments, amount, due_date, paid_date, status, wallet_tx_id, created_at 
                            FROM member_cashback_schedules 
                            WHERE customer_id = ? 
                            ORDER BY installment_no ASC");
    if ($cbSt) {
        $cbSt->bind_param('s', $customerId);
        $cbSt->execute();
        $cbRs = $cbSt->get_result();
        while ($cbr = $cbRs->fetch_assoc()) {
            $cashbackSchedule[] = $cbr;
            if (strtolower((string)($cbr['status'] ?? '')) === 'paid') {
                $totalPaidCashback += (float)$cbr['amount'];
                $paidCount++;
            } elseif ($nextUpcoming === null && strtolower((string)($cbr['status'] ?? '')) === 'pending') {
                $nextUpcoming = $cbr;
            }
        }
        $cbSt->close();
    }
}

$totalCashbackTarget = 10000.00;
$remainingCashback = max(0.0, $totalCashbackTarget - $totalPaidCashback);
$totalInstallments = max(10, count($cashbackSchedule));
$progressPct = min(100, (int)round(($paidCount / max(1, $totalInstallments)) * 100));

$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Joining Benefit &amp; Cashback Report</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body { background:#f3f3f3; }
        @media (min-width: 1024px) { .main-wrap { margin-left: 256px; } }
        .data-row:hover { background:#fdf6ea !important; }
    </style>
</head>
<body>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<div class="main-wrap min-h-screen">
    <?php include __DIR__ . '/includes/navbar.php'; ?>
    <div class="px-3 sm:px-5 py-3">

        <!-- Header -->
        <div class="mt-4 sm:mt-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="w-[34px] h-[34px] rounded-[8px] bg-[#f5eee3] flex items-center justify-center shrink-0">
                    <i data-lucide="<?php echo $userBenefitChoice === 'cashback' ? 'coins' : 'gift'; ?>" class="w-[17px] h-[17px] text-[#cd9737]"></i>
                </div>
                <div>
                    <h2 class="text-[20px] font-normal text-[#40352a]" style="font-family: Georgia, 'Times New Roman', serif;">
                        <?php echo $userBenefitChoice === 'cashback' ? 'JOINING MONTHLY CASHBACK REPORT' : 'JOINING PRODUCT VOUCHER REPORT'; ?>
                    </h2>
                    <p class="text-xs text-[#8a7c68]">Official financial ledger for your ₹10,000 joining benefit</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="wallet.php" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-white border border-[#d9dde2] hover:bg-[#faf6ee] text-xs font-semibold text-[#40352a] transition shadow-xs">
                    <i data-lucide="wallet" class="w-4 h-4 text-[#cd9737]"></i>
                    <span>View Main Wallet &rarr;</span>
                </a>
            </div>
        </div>
        <div class="w-full h-[2px] bg-[#d1a14b] mt-3"></div>

        <?php if (!$isActivated): ?>
            <!-- Inactive Warning Card -->
            <div class="mt-5 bg-gradient-to-r from-amber-50 to-orange-50 border border-amber-200 rounded-2xl p-6 text-amber-900 shadow-sm">
                <div class="flex items-start gap-4">
                    <div class="w-11 h-11 rounded-xl bg-amber-100 text-amber-800 flex items-center justify-center shrink-0">
                        <i data-lucide="alert-triangle" class="w-6 h-6"></i>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-base font-bold text-amber-950">Membership Activation Required</h3>
                        <p class="text-xs text-amber-800 mt-1">
                            Your account is not activated yet. Transfer the ₹10,000 qualifying deposit and select your preferred joining benefit (Monthly Cashback of ₹1,000/mo for 10 months OR ₹10,000 Product Voucher valid for 36 months) on the activation screen.
                        </p>
                        <div class="mt-4">
                            <a href="activation.php" class="inline-flex items-center gap-2 px-4 py-2 bg-[#b47e1d] hover:bg-[#966513] text-white rounded-xl text-xs font-bold transition shadow-sm">
                                <i data-lucide="zap" class="w-4 h-4"></i>
                                <span>Go to Book / Activate &rarr;</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>

            <!-- Financial Metric Cards (4 Cards Grid) -->
            <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <!-- Card 1: Chosen Plan -->
                <div class="bg-gradient-to-br from-[#40352a] to-[#251e18] rounded-2xl px-5 py-4 text-white shadow-sm">
                    <p class="text-[10px] uppercase tracking-widest text-[#e7d3ad] font-semibold">Selected Benefit Plan</p>
                    <p class="mt-1 text-[20px] font-bold text-white truncate">
                        <?php echo $userBenefitChoice === 'cashback' ? 'Monthly Cashback' : 'Product Voucher'; ?>
                    </p>
                    <p class="text-[10px] text-[#c9bba6] mt-1">
                        <?php echo $userBenefitChoice === 'cashback' ? '10 Monthly Installments (₹1,000/mo)' : '36 Months Official Voucher Validity'; ?>
                    </p>
                </div>

                <!-- Card 2: Total Plan Value -->
                <div class="bg-white rounded-2xl border border-[#e2d8c8] px-5 py-4 shadow-sm">
                    <p class="text-[10px] uppercase tracking-widest text-[#9e8f7e] font-semibold">Total Benefit Value</p>
                    <p class="mt-1 text-[24px] font-bold text-[#102B4E]">₹10,000.00</p>
                    <p class="text-[10px] text-[#8a7c68] mt-1">100% Value Back on ₹10,000 Joining</p>
                </div>

                <!-- Card 3: Realized / Paid to Wallet -->
                <div class="bg-gradient-to-br from-[#1b4332] to-[#081c15] rounded-2xl px-5 py-4 text-white shadow-sm">
                    <p class="text-[10px] uppercase tracking-widest text-[#95d5b2] font-semibold">
                        <?php echo $userBenefitChoice === 'cashback' ? 'Credited to Main Wallet' : 'Voucher Balance'; ?>
                    </p>
                    <p class="mt-1 text-[24px] font-bold text-white">
                        <?php 
                        if ($userBenefitChoice === 'cashback') {
                            echo money_fmt($totalPaidCashback);
                        } else {
                            echo money_fmt($memberVoucher['balance_amount'] ?? 10000.00);
                        }
                        ?>
                    </p>
                    <p class="text-[10px] text-[#d8f3dc] mt-1">
                        <?php echo $userBenefitChoice === 'cashback' ? ($paidCount . ' of 10 Installments Paid') : 'Ready to use on EV Store'; ?>
                    </p>
                </div>

                <!-- Card 4: Remaining / Expiry -->
                <div class="bg-white rounded-2xl border border-[#e2d8c8] px-5 py-4 shadow-sm">
                    <p class="text-[10px] uppercase tracking-widest text-[#9e8f7e] font-semibold">
                        <?php echo $userBenefitChoice === 'cashback' ? 'Remaining Due Amount' : 'Voucher Validity'; ?>
                    </p>
                    <p class="mt-1 text-[24px] font-bold text-[#b47e1d]">
                        <?php 
                        if ($userBenefitChoice === 'cashback') {
                            echo money_fmt($remainingCashback);
                        } else {
                            echo '36 Months';
                        }
                        ?>
                    </p>
                    <p class="text-[10px] text-[#8a7c68] mt-1">
                        <?php 
                        if ($userBenefitChoice === 'cashback') {
                            echo ($totalInstallments - $paidCount) . ' Installment(s) Pending';
                        } else {
                            $expDate = !empty($memberVoucher['expires_at']) ? date('d M Y', strtotime($memberVoucher['expires_at'])) : date('d M Y', strtotime('+36 months'));
                            echo 'Valid until ' . $expDate;
                        }
                        ?>
                    </p>
                </div>
            </div>

            <?php if ($userBenefitChoice === 'cashback' || !empty($cashbackSchedule)): ?>
                <!-- ========================================================================= -->
                <!-- OPTION 2: MONTHLY CASHBACK (10 MONTHS) FINANCIAL SCHEDULE                 -->
                <!-- ========================================================================= -->
                <div class="mt-6 bg-white rounded-2xl border border-[#e2d8c8] p-5 shadow-sm space-y-5">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-700 border border-emerald-200 flex items-center justify-center shrink-0">
                                <i data-lucide="calendar-check" class="w-5 h-5"></i>
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-[#40352a]">10-Month Cashback Payout Progress</h3>
                                <p class="text-xs text-[#8a7c68]">₹1,000 disbursed every 30 days directly into your withdrawable Main Wallet</p>
                            </div>
                        </div>
                        <div class="text-left sm:text-right">
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-green-100 text-green-800">
                                <span class="w-1.5 h-1.5 rounded-full bg-green-600 animate-pulse"></span>
                                <?php echo $paidCount; ?> / <?php echo $totalInstallments; ?> Months Paid (<?php echo $progressPct; ?>%)
                            </span>
                        </div>
                    </div>

                    <!-- Visual Progress Bar -->
                    <div class="space-y-1.5">
                        <div class="w-full h-3 bg-gray-100 rounded-full overflow-hidden p-0.5 border border-gray-200">
                            <div class="h-full bg-gradient-to-r from-emerald-500 to-teal-500 rounded-full transition-all duration-500" style="width: <?php echo $progressPct; ?>%"></div>
                        </div>
                        <div class="flex items-center justify-between text-[11px] text-[#8a7c68]">
                            <span>Month 1 (Start)</span>
                            <span class="font-semibold text-emerald-800"><?php echo money_fmt($totalPaidCashback); ?> Received</span>
                            <span>Month 10 (₹10,000 Total)</span>
                        </div>
                    </div>

                    <!-- Next Upcoming Installment Callout -->
                    <?php if ($nextUpcoming): ?>
                        <div class="p-3.5 rounded-xl bg-[#faf6ee] border border-[#e8decb] flex items-center justify-between flex-wrap gap-2 text-xs">
                            <div class="flex items-center gap-2">
                                <i data-lucide="clock" class="w-4 h-4 text-[#b47e1d]"></i>
                                <span class="text-[#40352a]">Next Due Installment: <strong class="text-[#b47e1d]">Month <?php echo (int)$nextUpcoming['installment_no']; ?> (₹1,000.00)</strong></span>
                            </div>
                            <div class="text-[#8a7c68]">
                                Scheduled Date: <strong class="text-[#40352a]"><?php echo date('d M Y', strtotime($nextUpcoming['due_date'])); ?></strong>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- 10-Month Financial Payout Schedule Table -->
                <div class="mt-6 bg-white rounded-2xl border border-[#e2d8c8] overflow-hidden shadow-sm">
                    <div class="p-4 sm:px-5 sm:py-3.5 border-b border-[#f0e9db] flex items-center justify-between">
                        <div>
                            <h3 class="text-[14px] font-bold text-[#40352a] uppercase tracking-wide">10-Month Cashback Payout Schedule</h3>
                            <p class="text-xs text-[#8a7c68]">Disbursement ledger to member Main Wallet</p>
                        </div>
                        <span class="px-3 py-1 rounded-full text-xs font-bold bg-[#faf3e7] text-[#b47e1d] border border-[#ecd9b8]">
                            10 Installments x ₹1,000.00
                        </span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-[13px]">
                            <thead class="bg-[#faf5ec] text-[#9e8f7e] text-[11px] uppercase tracking-wider">
                                <tr>
                                    <th class="text-left px-4 py-3">Installment #</th>
                                    <th class="text-left px-4 py-3">Scheduled Due Date</th>
                                    <th class="text-right px-4 py-3">Cashback Amount</th>
                                    <th class="text-center px-4 py-3">Destination</th>
                                    <th class="text-center px-4 py-3">Status</th>
                                    <th class="text-left px-4 py-3">Paid Date</th>
                                    <th class="text-right px-4 py-3">Wallet Tx Ref</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#f0e9db]">
                                <?php if (empty($cashbackSchedule)): ?>
                                    <tr>
                                        <td colspan="7" class="px-4 py-6 text-center text-[#8a7c68]">
                                            Cashback schedule will be generated upon activation approval.
                                        </td>
                                    </tr>
                                <?php else: foreach ($cashbackSchedule as $cs): 
                                    $isPaid = (strtolower((string)($cs['status'] ?? '')) === 'paid');
                                ?>
                                    <tr class="data-row">
                                        <td class="px-4 py-3 font-semibold text-[#40352a]">
                                            Installment <?php echo str_pad((string)$cs['installment_no'], 2, '0', STR_PAD_LEFT); ?> of <?php echo (int)$cs['total_installments']; ?>
                                        </td>
                                        <td class="px-4 py-3 text-xs text-[#6e5d48] font-medium">
                                            <?php echo date('d M Y', strtotime($cs['due_date'])); ?>
                                        </td>
                                        <td class="px-4 py-3 text-right font-bold text-[#102B4E]">
                                            <?php echo money_fmt($cs['amount']); ?>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-semibold bg-blue-50 text-blue-700 border border-blue-100">
                                                <i data-lucide="wallet" class="w-3 h-3"></i> Main Wallet
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <?php if ($isPaid): ?>
                                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-green-100 text-green-800">
                                                    <i data-lucide="check" class="w-3 h-3"></i> Paid
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                                                    <i data-lucide="clock" class="w-3 h-3 text-amber-500"></i> Scheduled
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3 text-xs text-[#8a7c68]">
                                            <?php echo !empty($cs['paid_date']) ? htmlspecialchars(substr($cs['paid_date'], 0, 16)) : '—'; ?>
                                        </td>
                                        <td class="px-4 py-3 text-right text-xs font-mono text-[#8a7c68]">
                                            <?php if (!empty($cs['wallet_tx_id'])): ?>
                                                <span class="text-slate-800 font-semibold">TXN-<?php echo htmlspecialchars($cs['wallet_tx_id']); ?></span>
                                            <?php elseif ($isPaid): ?>
                                                <span class="text-green-700 font-medium">Credited</span>
                                            <?php else: ?>
                                                <span class="text-slate-400">Auto Payout</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php else: ?>
                <!-- ========================================================================= -->
                <!-- OPTION 1: PRODUCT PURCHASE VOUCHER (36 MONTHS) VOUCHER CERTIFICATE        -->
                <!-- ========================================================================= -->
                <div class="mt-6 bg-white rounded-3xl border border-[#e2d8c8] p-6 sm:p-7 shadow-sm space-y-6">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-5 border-b border-[#f0e9db]">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-700 border border-indigo-200 flex items-center justify-center shrink-0">
                                <i data-lucide="gift" class="w-6 h-6"></i>
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <h3 class="text-base sm:text-lg font-bold text-[#40352a]">Official Product Purchase Voucher</h3>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-indigo-100 text-indigo-800 border border-indigo-200">
                                        <i data-lucide="sparkles" class="w-3 h-3 text-indigo-600"></i> Active Benefit
                                    </span>
                                </div>
                                <p class="text-xs text-[#8a7c68] mt-0.5">Valid for 36 months to purchase Electrinc products or book your Electric Scooter</p>
                            </div>
                        </div>
                        <div class="text-left sm:text-right">
                            <p class="text-xs text-[#9e8f7e] font-semibold uppercase tracking-wider">Voucher Face Value</p>
                            <p class="text-2xl font-extrabold text-indigo-700"><?php echo money_fmt($memberVoucher['balance_amount'] ?? 10000.00); ?></p>
                        </div>
                    </div>

                    <!-- Voucher Details Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="bg-[#faf8f5] rounded-2xl p-4 border border-[#e8decb]">
                            <p class="text-[11px] font-semibold text-[#9e8f7e] uppercase tracking-wider">Voucher Code</p>
                            <div class="mt-1 flex items-center justify-between">
                                <span class="font-mono font-bold text-base text-[#40352a]" id="voucherCodeText">
                                    <?php echo htmlspecialchars($memberVoucher['voucher_code'] ?? ('VCH-' . strtoupper($customerId))); ?>
                                </span>
                                <button type="button" onclick="copyVoucherCode()" class="p-1.5 rounded-lg bg-white border border-[#d9dde2] hover:bg-slate-100 text-[#40352a] transition" title="Copy voucher code">
                                    <i data-lucide="copy" class="w-3.5 h-3.5"></i>
                                </button>
                            </div>
                        </div>

                        <div class="bg-[#faf8f5] rounded-2xl p-4 border border-[#e8decb]">
                            <p class="text-[11px] font-semibold text-[#9e8f7e] uppercase tracking-wider">Validity Period</p>
                            <p class="mt-1 font-bold text-[#40352a] text-base">36 Months (3 Years)</p>
                            <p class="text-[11px] text-[#8a7c68]">
                                Valid until <?php echo !empty($memberVoucher['expires_at']) ? date('d M Y', strtotime($memberVoucher['expires_at'])) : date('d M Y', strtotime('+36 months')); ?>
                            </p>
                        </div>

                        <div class="bg-[#faf8f5] rounded-2xl p-4 border border-[#e8decb] flex items-center justify-between">
                            <div>
                                <p class="text-[11px] font-semibold text-[#9e8f7e] uppercase tracking-wider">Redemption Status</p>
                                <p class="mt-1 font-bold text-green-700 text-sm">Active &amp; Ready to Use</p>
                                <p class="text-[11px] text-[#8a7c68]">Applicable on EV Scooter / Store</p>
                            </div>
                            <div class="w-9 h-9 rounded-full bg-green-100 text-green-800 flex items-center justify-center">
                                <i data-lucide="check-circle" class="w-5 h-5"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Usage Guidelines -->
                    <div class="p-4 rounded-2xl bg-indigo-50/60 border border-indigo-100 text-xs text-indigo-950 space-y-1.5">
                        <div class="flex items-center gap-2 font-bold text-indigo-900">
                            <i data-lucide="info" class="w-4 h-4 text-indigo-600"></i>
                            <span>How to Redeem this Voucher:</span>
                        </div>
                        <p>1. Present this code at any authorized ELECTRINC Franchise or dealership to deduct ₹10,000 from your EV Scooter booking.</p>
                        <p>2. You can also utilize this voucher balance when purchasing company products or accessories.</p>
                        <p>3. This voucher is valid for 36 months from activation date and is fully transferable upon request.</p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Activation Record Summary & Audit Reference -->
            <?php if ($approvedActivation): ?>
                <div class="mt-6 bg-white rounded-2xl border border-[#e2d8c8] p-5 shadow-sm">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div>
                            <h4 class="text-sm font-bold text-[#40352a]">Qualifying Activation Record</h4>
                            <p class="text-xs text-[#8a7c68]">UTR payment proof and tax invoice reference</p>
                        </div>
                        <a href="activation-invoice.php?id=<?php echo (int)$approvedActivation['id']; ?>" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-[#faf3e7] text-[#b47e1d] hover:bg-[#f5e9d3] rounded-lg text-xs font-semibold border border-[#ecd9b8] transition">
                            <i data-lucide="file-text" class="w-3.5 h-3.5"></i>
                            <span>Download Official GST Tax Invoice &rarr;</span>
                        </a>
                    </div>
                    <div class="mt-3 grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs text-[#6e5d48] bg-[#fbf9f5] rounded-xl p-3.5 border border-[#f0e6d5]">
                        <div>
                            <span class="block text-[10px] text-[#9e8f7e] uppercase font-semibold">Deposit Paid</span>
                            <span class="font-bold text-[#40352a]"><?php echo money_fmt($approvedActivation['amount']); ?></span>
                        </div>
                        <div>
                            <span class="block text-[10px] text-[#9e8f7e] uppercase font-semibold">Payment Mode</span>
                            <span class="font-bold text-[#40352a]"><?php echo htmlspecialchars($approvedActivation['payment_mode'] ?: 'UPI / IMPS'); ?></span>
                        </div>
                        <div>
                            <span class="block text-[10px] text-[#9e8f7e] uppercase font-semibold">UTR / Transaction Ref</span>
                            <span class="font-mono font-bold text-[#40352a] truncate block"><?php echo htmlspecialchars($approvedActivation['transaction_id'] ?: '—'); ?></span>
                        </div>
                        <div>
                            <span class="block text-[10px] text-[#9e8f7e] uppercase font-semibold">Activated At</span>
                            <span class="font-medium text-[#40352a]"><?php echo htmlspecialchars(substr($approvedActivation['created_at'], 0, 16)); ?></span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        <?php endif; ?>

    </div>
</div>

<script>
lucide.createIcons();

function copyVoucherCode() {
    const el = document.getElementById('voucherCodeText');
    if (!el) return;
    const text = el.innerText.trim();
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(() => {
            alert('Voucher code copied to clipboard: ' + text);
        }).catch(() => {
            prompt('Copy voucher code:', text);
        });
    } else {
        prompt('Copy voucher code:', text);
    }
}
</script>
</body>
</html>
