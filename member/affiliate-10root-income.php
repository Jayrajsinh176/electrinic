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

// Fetch Member's Active Direct Sponsor Count
$myDirects = income_active_direct_count($conn, $customerId);

// Root Rates from Image
$rootRates = [
    1  => 10.0,
    2  => 8.0,
    3  => 5.0,
    4  => 5.0,
    5  => 4.0,
    6  => 4.0,
    7  => 4.0,
    8  => 3.0,
    9  => 3.0,
    10 => 5.0
];

// Calculate earned per root level & overall total
$rootEarnings = array_fill(1, 10, 0.00);
$rootCounts   = array_fill(1, 10, 0);
$totalAffiliateEarned = 0.00;

$payoutLogs = [];
$stmt = $conn->prepare("SELECT r.*, su.full_name as subscriber_name, s.package_name, s.package_amount 
                        FROM ind_bp_root_payouts r
                        LEFT JOIN signup_users su ON su.customer_id = r.subscriber_id
                        LEFT JOIN ind_bp_subscriptions s ON s.id = r.subscription_id
                        WHERE r.earner_id = ?
                        ORDER BY r.id DESC");
if ($stmt) {
    $stmt->bind_param('s', $customerId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $lvl = (int)$r['root_level'];
        if ($r['status'] === 'paid') {
            $totalAffiliateEarned += (float)$r['commission_amount'];
            if (isset($rootEarnings[$lvl])) {
                $rootEarnings[$lvl] += (float)$r['commission_amount'];
                $rootCounts[$lvl]++;
            }
        }
        $payoutLogs[] = $r;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Affiliate Marketing (10 Roots)</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body { background: #f3f3f3; }
        @media (min-width: 1024px) { .main-wrap { margin-left: 256px; } }
        .data-row:hover { background: #fdfaf5 !important; }
    </style>
</head>
<body class="text-slate-800">

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="main-wrap min-h-screen">
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <div class="px-4 sm:px-6 py-5">

        <!-- Title Header -->
        <div class="flex items-center gap-3 border-b border-[#e2d8c8] pb-4">
            <div class="w-9 h-9 rounded-xl bg-[#f5eee3] flex items-center justify-center shrink-0">
                <i data-lucide="network" class="w-5 h-5 text-[#cd9737]"></i>
            </div>
            <div>
                <h1 class="text-xl sm:text-2xl font-bold text-[#103258]">Affiliate Marketing (10 Roots)</h1>
                <p class="text-xs text-slate-500">51% Recurring monthly commission on downline Brand Promotion Bonus for 10 months.</p>
            </div>
        </div>

        <!-- Metric Summary Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-5">
            <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm">
                <p class="text-[11px] uppercase font-bold text-slate-400">Total Affiliate Earned</p>
                <p class="text-2xl font-extrabold text-emerald-600 mt-1">₹<?php echo number_format($totalAffiliateEarned, 2); ?></p>
                <p class="text-xs text-slate-500 mt-1">10-month recurring team commission</p>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm">
                <p class="text-[11px] uppercase font-bold text-slate-400">Active Direct Sponsors</p>
                <p class="text-2xl font-extrabold text-[#103258] mt-1"><?php echo $myDirects; ?> Member(s)</p>
                <p class="text-xs text-emerald-600 font-medium mt-1">Unlocks up to Root <?php echo min(10, $myDirects); ?> of 10</p>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm">
                <p class="text-[11px] uppercase font-bold text-slate-400">Total Affiliate Pool</p>
                <p class="text-2xl font-extrabold text-[#DDBE7E] mt-1">51% Pool</p>
                <p class="text-xs text-slate-500 mt-1">Distributed across 10 Roots for 10 Months</p>
            </div>
        </div>

        <!-- 10 Roots Qualification & Earnings Matrix -->
        <div class="mt-6 bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-4">
                <div>
                    <h2 class="text-base font-bold text-[#103258] flex items-center gap-2">
                        <i data-lucide="layers" class="w-4 h-4 text-[#DDBE7E]"></i>
                        <span>10-Root Qualification Status</span>
                    </h2>
                    <p class="text-xs text-slate-500">Each root opens according to your direct active sponsors.</p>
                </div>
                <div class="text-xs bg-[#fdfaf5] border border-[#DDBE7E]/40 px-3 py-1.5 rounded-lg text-slate-700">
                    You have <strong class="text-[#103258]"><?php echo $myDirects; ?> Active Directs</strong> &rarr; <span class="text-emerald-700 font-bold"><?php echo min(10, $myDirects); ?> / 10 Roots Active</span>
                </div>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-5 xl:grid-cols-10 gap-2.5">
                <?php for ($r = 1; $r <= 10; $r++): 
                    $isOpen = ($myDirects >= $r);
                ?>
                    <div class="rounded-xl border p-3 text-center transition <?php echo $isOpen ? 'bg-[#fdfaf5] border-[#cd9737]/50 shadow-sm' : 'bg-slate-50 border-slate-200 opacity-60'; ?>">
                        <p class="text-[10px] font-bold uppercase tracking-wider <?php echo $isOpen ? 'text-[#103258]' : 'text-slate-400'; ?>">
                            Root <?php echo sprintf('%02d', $r); ?>
                        </p>
                        <p class="text-sm font-black mt-1 <?php echo $isOpen ? 'text-[#cd9737]' : 'text-slate-500'; ?>">
                            <?php echo $rootRates[$r]; ?>%
                        </p>
                        <p class="text-xs font-bold text-slate-800 mt-1">
                            ₹<?php echo number_format($rootEarnings[$r], 0); ?>
                        </p>
                        <div class="mt-2">
                            <?php if ($isOpen): ?>
                                <span class="inline-block text-[9px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800">
                                    Unlocked
                                </span>
                            <?php else: ?>
                                <span class="inline-block text-[9px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded-full bg-slate-200 text-slate-600">
                                    Req <?php echo $r; ?> Dir
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>
        </div>

        <!-- Detailed Commission History Table -->
        <div class="mt-6 bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
            <h2 class="text-base font-bold text-[#103258] mb-4 flex items-center gap-2">
                <i data-lucide="list" class="w-4 h-4 text-[#DDBE7E]"></i>
                <span>Affiliate Commission History</span>
            </h2>

            <?php if (empty($payoutLogs)): ?>
                <div class="py-12 text-center text-slate-400 text-xs">
                    No 10-Root affiliate commissions recorded yet. When members in your downline subscribe to packages, commissions will credit here every month.
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-slate-50 text-slate-500 uppercase tracking-wider font-semibold border-b border-slate-200">
                            <tr>
                                <th class="py-3 px-3">Date</th>
                                <th class="py-3 px-3">Downline Member</th>
                                <th class="py-3 px-3">Package</th>
                                <th class="py-3 px-3">Root Level</th>
                                <th class="py-3 px-3">Month #</th>
                                <th class="py-3 px-3">Commission %</th>
                                <th class="py-3 px-3">Base Amount</th>
                                <th class="py-3 px-3">Earned Amount</th>
                                <th class="py-3 px-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($payoutLogs as $log): 
                                $isPaid = ($log['status'] === 'paid');
                            ?>
                                <tr class="data-row">
                                    <td class="py-3 px-3 text-slate-600"><?php echo date('d M Y, h:i A', strtotime($log['created_at'])); ?></td>
                                    <td class="py-3 px-3 font-semibold text-[#103258]">
                                        <?php echo htmlspecialchars($log['subscriber_name'] ?: $log['subscriber_id']); ?>
                                        <span class="block text-[10px] text-slate-400 font-mono"><?php echo htmlspecialchars($log['subscriber_id']); ?></span>
                                    </td>
                                    <td class="py-3 px-3 text-slate-700 font-medium"><?php echo htmlspecialchars($log['package_name'] ?? '—'); ?></td>
                                    <td class="py-3 px-3">
                                        <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-[#f5eee3] text-[#cd9737]">
                                            Root <?php echo sprintf('%02d', $log['root_level']); ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-3 font-semibold text-slate-600">Month <?php echo (int)$log['month_number']; ?> / 10</td>
                                    <td class="py-3 px-3 font-bold text-slate-800"><?php echo number_format($log['percent'], 1); ?>%</td>
                                    <td class="py-3 px-3 text-slate-600">₹<?php echo number_format($log['base_monthly_amount'], 2); ?></td>
                                    <td class="py-3 px-3 font-bold <?php echo $isPaid ? 'text-emerald-600' : 'text-slate-400'; ?>">
                                        ₹<?php echo number_format($log['commission_amount'], 2); ?>
                                    </td>
                                    <td class="py-3 px-3">
                                        <?php if ($isPaid): ?>
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-100 text-emerald-800">
                                                Paid
                                            </span>
                                        <?php else: ?>
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-amber-100 text-amber-800" title="Insufficient directs">
                                                Skipped (Needs <?php echo (int)$log['root_level']; ?> Dir)
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<script>
    lucide.createIcons();
</script>
</body>
</html>

