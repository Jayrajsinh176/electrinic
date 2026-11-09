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

$wallet = wallet_balance_of($conn, $customerId);
$fund = 0.0;
$scooterWallet = 0.0;
$cashbackLimit = 10000.0;
$cashbackClaimed = 0.0;
$benefitChoice = '';

$fr = $conn->query("SELECT product_fund_balance, scooter_wallet_balance, cashback_max_limit, cashback_claimed_total, benefit_choice 
                    FROM signup_users WHERE customer_id='" . $conn->real_escape_string($customerId) . "' LIMIT 1")->fetch_assoc();
if ($fr) {
    $fund = (float)($fr['product_fund_balance'] ?? 0);
    $scooterWallet = (float)($fr['scooter_wallet_balance'] ?? 0);
    $cashbackLimit = (float)($fr['cashback_max_limit'] ?? 10000.0);
    $cashbackClaimed = (float)($fr['cashback_claimed_total'] ?? 0.0);
    $benefitChoice = strtolower(trim((string)($fr['benefit_choice'] ?? '')));
}

// Fetch active product voucher
$voucher = null;
if (income_table_exists($conn, 'ind_vouchers')) {
    $voucher = $conn->query("SELECT * FROM ind_vouchers WHERE customer_id='" . $conn->real_escape_string($customerId) . "' AND status='active' ORDER BY id DESC LIMIT 1")->fetch_assoc();
}

// Fetch cashback schedule stats
$cashbackScheduleCount = 0;
$cashbackPaidCount = 0;
if (income_table_exists($conn, 'member_cashback_schedules')) {
    $cbs = $conn->query("SELECT status, COUNT(*) as c FROM member_cashback_schedules WHERE customer_id='" . $conn->real_escape_string($customerId) . "' GROUP BY status");
    while ($cbs && ($cbr = $cbs->fetch_assoc())) {
        $cashbackScheduleCount += (int)$cbr['c'];
        if (strtolower((string)$cbr['status']) === 'paid') {
            $cashbackPaidCount += (int)$cbr['c'];
        }
    }
}

$isCashbackPlan = ($benefitChoice === 'cashback' || $cashbackScheduleCount > 0);
$isProductPlan = (!$isCashbackPlan && ($benefitChoice === 'product' || $voucher));

$joinSplit = $conn->query("SELECT joining_amount, own_fund_amount, income_pool_amount FROM joining_splits WHERE customer_id='" . $conn->real_escape_string($customerId) . "' LIMIT 1")->fetch_assoc();

$wdDays  = income_withdrawal_days($conn);
$allowedToday = withdrawal_allowed_today($conn);

$hist = [];
$res = $conn->query("SELECT created_at, direction, category, amount, balance_after, counterparty_id, note
                     FROM wallet_transactions WHERE customer_id='" . $conn->real_escape_string($customerId) . "'
                     ORDER BY id DESC LIMIT 200");
while ($res && ($x = $res->fetch_assoc())) $hist[] = $x;

function m($n) { return '₹' . number_format((float)$n, 2); }
$catLabel = [
    'p2p_send' => 'Sent (P2P)', 'p2p_receive' => 'Received (P2P)',
    'withdrawal_hold' => 'Withdrawal hold', 'withdrawal_refund' => 'Withdrawal refund', 'adjustment' => 'Adjustment',
    'income' => 'Income Credit',
    'cashback' => 'Monthly Cashback',
    'scooter_purchase' => 'EV Scooter Purchase'
];
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wallet Overview</title>
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

        <div class="mt-4 sm:mt-5 flex items-center gap-3">
            <div class="w-[34px] h-[34px] rounded-[8px] bg-[#f5eee3] flex items-center justify-center shrink-0">
                <i data-lucide="wallet" class="w-[17px] h-[17px] text-[#cd9737]"></i>
            </div>
            <h2 class="whitespace-nowrap text-[20px] font-normal text-[#40352a]" style="font-family: Georgia, 'Times New Roman', serif;">WALLET OVERVIEW</h2>
            <div class="w-full h-[2px] bg-[#d1a14b]"></div>
        </div>

        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <div class="bg-[#40352a] rounded-2xl px-4 py-4">
                <p class="text-[10px] uppercase tracking-widest text-[#e7d3ad] font-semibold">Main Balance (50%)</p>
                <p class="mt-1 text-[22px] font-bold text-white"><?php echo m($wallet); ?></p>
                <p class="text-[10px] text-[#a99c85]">Withdrawable / P2P Transfer</p>
            </div>
            <div class="bg-gradient-to-br from-[#1e4620] to-[#123114] rounded-2xl px-4 py-4 text-white">
                <p class="text-[10px] uppercase tracking-widest text-[#a8e6a1] font-semibold">Scooter Wallet (50%)</p>
                <p class="mt-1 text-[22px] font-bold text-white"><?php echo m($scooterWallet); ?></p>
                <p class="text-[10px] text-[#85c97d]">For EV Purchase / Scooter EMI</p>
            </div>
            <div class="bg-gradient-to-br from-[#1a385c] to-[#0d223a] rounded-2xl px-4 py-4 text-white">
                <p class="text-[10px] uppercase tracking-widest text-[#9bc5f5] font-semibold">Product Fund</p>
                <p class="mt-1 text-[22px] font-bold text-white"><?php echo m($fund); ?></p>
                <p class="text-[10px] text-[#88aedc]">Held Toward EV Purchase</p>
            </div>
            <!-- Card 4: Product Voucher -->
            <div class="<?php echo $isProductPlan ? 'bg-white border-2 border-[#b47e1d]/50' : 'bg-slate-50 border border-slate-200/80 opacity-70'; ?> rounded-2xl px-4 py-4">
                <div class="flex items-center justify-between">
                    <p class="text-[10px] uppercase tracking-widest text-[#9e8f7e] font-semibold">Product Voucher</p>
                    <?php if ($isProductPlan): ?>
                        <span class="text-[9px] font-bold px-1.5 py-0.2 rounded bg-amber-100 text-amber-800">36M Active</span>
                    <?php endif; ?>
                </div>
                <p class="mt-1 text-[20px] font-bold text-[#b47e1d]">
                    <?php echo $isProductPlan ? ($voucher ? m($voucher['balance_amount']) : '₹10,000.00') : '—'; ?>
                </p>
                <p class="text-[10px] text-[#8a7c68] truncate" title="<?php echo $voucher ? htmlspecialchars($voucher['voucher_code']) : ''; ?>">
                    <?php if ($isProductPlan): ?>
                        Code: <?php echo $voucher ? htmlspecialchars($voucher['voucher_code']) : 'Active'; ?>
                    <?php else: ?>
                        Not Opted (Cashback Active)
                    <?php endif; ?>
                </p>
            </div>

            <!-- Card 5: Cashback Benefit -->
            <div class="<?php echo $isCashbackPlan ? 'bg-white border-2 border-emerald-500/50' : 'bg-slate-50 border border-slate-200/80 opacity-70'; ?> rounded-2xl px-4 py-4">
                <div class="flex items-center justify-between">
                    <p class="text-[10px] uppercase tracking-widest text-[#9e8f7e] font-semibold">Monthly Cashback</p>
                    <?php if ($isCashbackPlan): ?>
                        <span class="text-[9px] font-bold px-1.5 py-0.2 rounded bg-emerald-100 text-emerald-800">10M Active</span>
                    <?php endif; ?>
                </div>
                <p class="mt-1 text-[20px] font-bold text-[#1e7a48]">
                    <?php if ($isCashbackPlan): ?>
                        <?php echo m($cashbackPaidCount * 1000); ?> <span class="text-[11px] font-normal text-[#888]">/ ₹10,000.00</span>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </p>
                <p class="text-[10px] text-[#8a7c68]">
                    <?php if ($isCashbackPlan): ?>
                        Month <?php echo $cashbackPaidCount; ?> of 10 Paid (₹1k/mo)
                    <?php else: ?>
                        Not Opted (Product Active)
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <?php if ($joinSplit): ?>
        <p class="mt-2 text-[11px] text-[#8a7c68]">
            Your &#8377;<?php echo number_format((float)$joinSplit['joining_amount'], 0); ?> joining was split:
            <strong>&#8377;<?php echo number_format((float)$joinSplit['own_fund_amount'], 0); ?></strong> to your product fund (held toward your EV) &middot;
            <strong>&#8377;<?php echo number_format((float)$joinSplit['income_pool_amount'], 0); ?></strong> to the income distribution pool.
        </p>
        <?php endif; ?>

        <div class="mt-4 flex flex-wrap gap-3">
            <a href="<?php echo $basePath; ?>/wallet-p2p.php" class="rounded-xl bg-white border border-[#e2d8c8] px-5 py-3 text-[13px] font-medium text-[#40352a] hover:bg-[#fdf6ea] flex items-center gap-2">
                <i data-lucide="arrow-left-right" class="w-4 h-4 text-[#c79435]"></i> P2P Transfer
            </a>
            <a href="<?php echo $basePath; ?>/wallet-bank.php" class="rounded-xl bg-white border border-[#e2d8c8] px-5 py-3 text-[13px] font-medium text-[#40352a] hover:bg-[#fdf6ea] flex items-center gap-2">
                <i data-lucide="banknote" class="w-4 h-4 text-[#c79435]"></i> Wallet to Bank
            </a>
        </div>

        <div class="mt-4 bg-white rounded-2xl border border-[#e2d8c8] overflow-hidden">
            <div class="px-5 py-3 border-b border-[#f0e9db] text-[14px] font-semibold text-[#40352a]">Wallet Activity</div>
            <div class="overflow-x-auto">
                <table class="w-full text-[13px]">
                    <thead class="bg-[#faf5ec] text-[#9e8f7e] text-[11px] uppercase tracking-wider">
                        <tr><th class="text-left px-4 py-3">Date</th><th class="text-left px-4 py-3">Activity</th><th class="text-left px-4 py-3">With</th><th class="text-right px-4 py-3">Amount</th><th class="text-right px-4 py-3">Balance</th><th class="text-left px-4 py-3">Note</th></tr>
                    </thead>
                    <tbody class="divide-y divide-[#f0e9db]">
                        <?php if (empty($hist)): ?>
                            <tr><td colspan="6" class="px-4 py-8 text-center text-[#b3a78f]">No wallet activity yet.</td></tr>
                        <?php else: foreach ($hist as $x): ?>
                            <tr class="data-row">
                                <td class="px-4 py-3 whitespace-nowrap"><?php echo htmlspecialchars(substr((string)$x['created_at'], 0, 16)); ?></td>
                                <td class="px-4 py-3"><?php echo htmlspecialchars($catLabel[$x['category']] ?? $x['category']); ?></td>
                                <td class="px-4 py-3"><?php echo htmlspecialchars((string)($x['counterparty_id'] ?: '—')); ?></td>
                                <td class="px-4 py-3 text-right font-semibold <?php echo $x['direction'] === 'credit' ? 'text-[#1e7a48]' : 'text-[#9b1c1c]'; ?>"><?php echo ($x['direction'] === 'credit' ? '+' : '−') . m($x['amount']); ?></td>
                                <td class="px-4 py-3 text-right text-[#8a7c68]"><?php echo m($x['balance_after']); ?></td>
                                <td class="px-4 py-3 text-[#8a7c68]"><?php echo htmlspecialchars((string)$x['note']); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>
<script>lucide.createIcons();</script>
</body>
</html>
