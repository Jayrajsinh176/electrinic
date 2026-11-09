<?php
include __DIR__ . '/config/db.php';
if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.");
}
$conn->set_charset("utf8mb4");
require_once __DIR__ . '/../includes/income_common.php';
require_once __DIR__ . '/../includes/ind_income_functions.php';

function money($n) { return '₹' . number_format((float)$n, 2); }

$isInd = income_setting_bool($conn, 'ind_plan_enabled', true);
$hasIndPool = income_table_exists($conn, 'ind_auto_pool_state');

if ($isInd && $hasIndPool) {
    // 1. IND Plan 100 Pools Stats
    $posStats = $conn->query("SELECT 
        COUNT(*) as tot_pos,
        SUM(CASE WHEN status='waiting' THEN 1 ELSE 0 END) as waiting_pos,
        SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) as completed_pos
        FROM ind_auto_pool_positions")->fetch_assoc();

    $totPositions = (int)($posStats['tot_pos'] ?? 0);
    $waitingPositions = (int)($posStats['waiting_pos'] ?? 0);
    $completedPositions = (int)($posStats['completed_pos'] ?? 0);

    $lgrStats = $conn->query("SELECT 
        COUNT(*) as cnt,
        COALESCE(SUM(gross_income), 0) as total_gross,
        COALESCE(SUM(retopup_amount), 0) as total_retopup,
        COALESCE(SUM(upgrade_amount), 0) as total_upgrade,
        COALESCE(SUM(main_wallet_credit), 0) as total_main,
        COALESCE(SUM(scooter_wallet_credit), 0) as total_scooter
        FROM ind_auto_pool_ledger")->fetch_assoc();

    $totCompletions = (int)($lgrStats['cnt'] ?? 0);
    $totGross = (float)($lgrStats['total_gross'] ?? 0);
    $totRetopup = (float)($lgrStats['total_retopup'] ?? 0);
    $totUpgrade = (float)($lgrStats['total_upgrade'] ?? 0);
    $totMainCredit = (float)($lgrStats['total_main'] ?? 0);
    $totScooterCredit = (float)($lgrStats['total_scooter'] ?? 0);

    // 2. Fetch 100-Level Ladder with waiting counts
    $indLadder = [];
    $ladRes = $conn->query("SELECT s.*, 
        (SELECT COUNT(*) FROM ind_auto_pool_positions p WHERE p.pool_level = s.pool_level AND p.status = 'waiting') as waiting_count 
        FROM ind_auto_pool_state s 
        ORDER BY s.pool_level ASC");
    if ($ladRes) {
        while ($r = $ladRes->fetch_assoc()) {
            $indLadder[] = $r;
        }
    }

    // 3. Fetch Recent Completions
    $indCompletions = [];
    $compRes = $conn->query("SELECT l.*, su.full_name, su.mobile 
        FROM ind_auto_pool_ledger l 
        LEFT JOIN signup_users su ON su.customer_id = l.customer_id 
        ORDER BY l.id DESC LIMIT 150");
    if ($compRes) {
        while ($r = $compRes->fetch_assoc()) {
            $indCompletions[] = $r;
        }
    }
} else {
    // Legacy Pool Fallback
    $status = income_table_exists($conn, 'auto_pool_state') ? auto_pool_status($conn) : [];
    $cfg = auto_pool_config($conn);
    $tot = ['cnt' => 0, 'income' => 0.0, 'paid' => 0.0, 'flushed' => 0.0];
    $res = $conn->query("SELECT COUNT(*) c, COALESCE(SUM(income_amount),0) i, COALESCE(SUM(paid_amount),0) p, COALESCE(SUM(flushed_amount),0) f FROM auto_pool_completions");
    if ($res && ($r = $res->fetch_assoc())) {
        $tot = ['cnt' => (int)$r['c'], 'income' => (float)$r['i'], 'paid' => (float)$r['p'], 'flushed' => (float)$r['f']];
    }
    $debitTot = (float)($conn->query("SELECT COALESCE(SUM(amount),0) s FROM auto_pool_debits")->fetch_assoc()['s'] ?? 0);
    $rows = [];
    $res = $conn->query("SELECT id, report_date, pool_level, head_customer_id, pool_total, income_amount, repool_amount, repool_to_level, paid_amount, flushed_amount, created_at FROM auto_pool_completions ORDER BY id DESC LIMIT 300");
    if ($res) {
        while ($x = $res->fetch_assoc()) $rows[] = $x;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auto Pool Income - 100 Pools</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .data-row:hover { background:#fdfaf3 !important; }
    </style>
</head>
<body class="m-0 bg-[#f3f4f6] font-sans">
<div class="ml-64 min-h-screen w-[calc(100%-16rem)]">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="flex-1 min-w-0 flex flex-col">
        <?php include __DIR__ . '/includes/navbar.php'; ?>
        <main class="px-8 py-5">

            <div class="flex items-center justify-between gap-4 mb-6 pb-4 border-b border-gray-200">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-[8px] border border-[#ead9b5] bg-[#f8f1e7] flex items-center justify-center shrink-0">
                        <i data-lucide="layers" class="w-5 h-5 text-[#c79435]"></i>
                    </div>
                    <div>
                        <h2 class="text-[22px] sm:text-[24px] font-normal text-[#313131] leading-tight tracking-wide" style="font-family: Georgia, 'Times New Roman', serif;">
                            AUTO POOL INCOME (100 POOLS)
                        </h2>
                        <p class="text-xs text-gray-500 mt-0.5">3-member non-working matrix with auto re-topup, level upgrade, and 50:50 dual wallet release.</p>
                    </div>
                </div>
                <div>
                    <a href="income-rules.php" class="px-3.5 py-2 rounded-xl border border-gray-300 text-xs font-semibold text-gray-700 hover:bg-gray-50 flex items-center gap-1.5 transition">
                        <i data-lucide="sliders-horizontal" class="w-3.5 h-3.5 text-[#c79435]"></i>
                        Configure Rules
                    </a>
                </div>
            </div>

            <?php if ($isInd && $hasIndPool): ?>
                <!-- Stat Cards -->
                <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
                    <div class="rounded-2xl bg-white border border-gray-200 p-5 shadow-sm">
                        <div class="text-xs uppercase font-semibold text-gray-500">Total Positions</div>
                        <div class="text-2xl font-bold text-gray-900 mt-1"><?php echo number_format($totPositions); ?></div>
                        <div class="text-[11px] text-gray-400 mt-0.5"><?php echo number_format($waitingPositions); ?> waiting / <?php echo number_format($completedPositions); ?> completed</div>
                    </div>
                    <div class="rounded-2xl bg-white border border-gray-200 p-5 shadow-sm">
                        <div class="text-xs uppercase font-semibold text-gray-500">Cycles Completed</div>
                        <div class="text-2xl font-bold text-emerald-700 mt-1"><?php echo number_format($totCompletions); ?></div>
                        <div class="text-[11px] text-emerald-600 mt-0.5">3-Member payouts processed</div>
                    </div>
                    <div class="rounded-2xl bg-white border border-gray-200 p-5 shadow-sm">
                        <div class="text-xs uppercase font-semibold text-gray-500">Gross Income Generated</div>
                        <div class="text-2xl font-bold text-[#b47e1d] mt-1"><?php echo money($totGross); ?></div>
                        <div class="text-[11px] text-gray-400 mt-0.5">Main: <?php echo money($totMainCredit); ?> | Sc: <?php echo money($totScooterCredit); ?></div>
                    </div>
                    <div class="rounded-2xl bg-white border border-gray-200 p-5 shadow-sm">
                        <div class="text-xs uppercase font-semibold text-gray-500">Auto Re-Topups</div>
                        <div class="text-2xl font-bold text-blue-700 mt-1"><?php echo money($totRetopup); ?></div>
                        <div class="text-[11px] text-blue-600 mt-0.5">Re-injected into same pool</div>
                    </div>
                    <div class="rounded-2xl bg-white border border-gray-200 p-5 shadow-sm">
                        <div class="text-xs uppercase font-semibold text-gray-500">Upgrades Advanced</div>
                        <div class="text-2xl font-bold text-purple-700 mt-1"><?php echo money($totUpgrade); ?></div>
                        <div class="text-[11px] text-purple-600 mt-0.5">Funded into next higher pool</div>
                    </div>
                </div>

                <!-- 100-Level Ladder Table -->
                <div class="rounded-2xl bg-white border border-gray-200 shadow-sm overflow-hidden mb-8">
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                        <div>
                            <h3 class="text-base font-bold text-gray-800">Official 100-Level Auto Pool Matrix (PDF Pages 4–6)</h3>
                            <p class="text-xs text-gray-500">Pool entry requires 3 members (3x entry = Total). Split: Same pool Re-Topup + Next pool Upgrade + Gross Withdrawal (50:50 net).</p>
                        </div>
                        <span class="px-3 py-1 rounded-full text-xs font-semibold bg-[#f8f1e7] text-[#c79435] border border-[#ead9b5]">
                            100 Active Pools
                        </span>
                    </div>
                    <div class="overflow-x-auto max-h-[500px] overflow-y-auto">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-gray-50 text-gray-500 uppercase font-semibold border-b border-gray-200 sticky top-0 z-10">
                                <tr>
                                    <th class="px-4 py-3">Pool</th>
                                    <th class="px-4 py-3 text-right">Entry Fee</th>
                                    <th class="px-4 py-3 text-right">Pool Total (3x)</th>
                                    <th class="px-4 py-3 text-right text-blue-600">Re-Topup</th>
                                    <th class="px-4 py-3 text-right text-purple-600">Next Upgrade</th>
                                    <th class="px-4 py-3 text-right font-bold text-gray-900">Gross Withdraw</th>
                                    <th class="px-4 py-3 text-right text-emerald-700">Net Main (50%)</th>
                                    <th class="px-4 py-3 text-right text-[#b47e1d]">Net Scooter (50%)</th>
                                    <th class="px-4 py-3 text-center">Waiting Queue</th>
                                    <th class="px-4 py-3 text-right">Completions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php foreach ($indLadder as $row): 
                                    $gross = (float)$row['withdrawal_gross'];
                                    $net = $gross * 0.90;
                                    $halfNet = $net / 2.0;
                                ?>
                                    <tr class="data-row">
                                        <td class="px-4 py-3 font-bold text-gray-900">Pool <?php echo (int)$row['pool_level']; ?></td>
                                        <td class="px-4 py-3 text-right font-medium"><?php echo money($row['entry_amount']); ?></td>
                                        <td class="px-4 py-3 text-right font-medium text-gray-700"><?php echo money($row['entry_amount'] * 3); ?></td>
                                        <td class="px-4 py-3 text-right text-blue-700 font-semibold"><?php echo money($row['retopup_amount']); ?></td>
                                        <td class="px-4 py-3 text-right text-purple-700 font-semibold"><?php echo money($row['upgrade_amount']); ?></td>
                                        <td class="px-4 py-3 text-right font-bold text-gray-900"><?php echo money($gross); ?></td>
                                        <td class="px-4 py-3 text-right font-semibold text-emerald-700"><?php echo money($halfNet); ?></td>
                                        <td class="px-4 py-3 text-right font-semibold text-[#b47e1d]"><?php echo money($halfNet); ?></td>
                                        <td class="px-4 py-3 text-center">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold <?php echo (int)$row['waiting_count'] > 0 ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-500'; ?>">
                                                <?php echo (int)$row['waiting_count']; ?> Waiting
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-right font-bold text-gray-800"><?php echo number_format((int)$row['completed_count']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Recent Ledger Completions Table -->
                <div class="rounded-2xl bg-white border border-gray-200 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                        <div>
                            <h3 class="text-base font-bold text-gray-800">Recent 100-Pool Ledger Completions</h3>
                            <p class="text-xs text-gray-500">Real-time log of members who completed 3-member cycles with 50/50 dual wallet payouts.</p>
                        </div>
                        <span class="px-3 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-700">
                            <?php echo count($indCompletions); ?> Logged
                        </span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-gray-50 text-gray-500 uppercase font-semibold border-b border-gray-200">
                                <tr>
                                    <th class="px-4 py-3">#</th>
                                    <th class="px-4 py-3">Date</th>
                                    <th class="px-4 py-3">Member Details</th>
                                    <th class="px-4 py-3 text-center">Pool Level</th>
                                    <th class="px-4 py-3 text-right">Total Collected</th>
                                    <th class="px-4 py-3 text-right">Re-Topup</th>
                                    <th class="px-4 py-3 text-right">Next Upgrade</th>
                                    <th class="px-4 py-3 text-right font-bold">Gross Income</th>
                                    <th class="px-4 py-3 text-right text-emerald-700">Main Balance</th>
                                    <th class="px-4 py-3 text-right text-[#b47e1d]">Scooter Wallet</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php if (empty($indCompletions)): ?>
                                    <tr>
                                        <td colspan="10" class="px-4 py-12 text-center text-gray-400">
                                            No auto pool completions recorded yet.
                                        </td>
                                    </tr>
                                <?php else: foreach ($indCompletions as $i => $c): ?>
                                    <tr class="data-row">
                                        <td class="px-4 py-3 text-gray-400"><?php echo count($indCompletions) - $i; ?></td>
                                        <td class="px-4 py-3 text-gray-500"><?php echo date('d M Y, h:i A', strtotime($c['created_at'] ?? $c['completed_at'] ?? 'now')); ?></td>
                                        <td class="px-4 py-3">
                                            <div class="font-bold text-gray-900"><?php echo htmlspecialchars($c['full_name'] ?? $c['customer_id']); ?></div>
                                            <div class="text-[11px] text-gray-500 font-mono"><?php echo htmlspecialchars($c['customer_id']); ?></div>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-50 text-[#b47e1d] border border-amber-200">
                                                Pool <?php echo (int)$c['pool_level']; ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-right font-medium text-gray-700"><?php echo money($c['total_collected']); ?></td>
                                        <td class="px-4 py-3 text-right text-blue-700 font-semibold"><?php echo money($c['retopup_amount']); ?></td>
                                        <td class="px-4 py-3 text-right text-purple-700 font-semibold"><?php echo money($c['upgrade_amount']); ?></td>
                                        <td class="px-4 py-3 text-right font-bold text-gray-900"><?php echo money($c['gross_income']); ?></td>
                                        <td class="px-4 py-3 text-right font-semibold text-emerald-700"><?php echo money($c['main_wallet_credit']); ?></td>
                                        <td class="px-4 py-3 text-right font-semibold text-[#b47e1d]"><?php echo money($c['scooter_wallet_credit']); ?></td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php else: ?>
                <!-- Fallback Legacy Pool Display -->
                <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
                    <div class="rounded-xl bg-white border border-gray-200 p-4"><div class="text-[12px] text-gray-500">Completions</div><div class="text-[20px] font-semibold"><?php echo (int)$tot['cnt']; ?></div></div>
                    <div class="rounded-xl bg-white border border-gray-200 p-4"><div class="text-[12px] text-gray-500">Income Generated</div><div class="text-[20px] font-semibold"><?php echo money($tot['income']); ?></div></div>
                    <div class="rounded-xl bg-white border border-gray-200 p-4"><div class="text-[12px] text-gray-500">Paid</div><div class="text-[20px] font-semibold"><?php echo money($tot['paid']); ?></div></div>
                    <div class="rounded-xl bg-white border border-gray-200 p-4"><div class="text-[12px] text-gray-500">Flushed</div><div class="text-[20px] font-semibold text-[#b91c1c]"><?php echo money($tot['flushed']); ?></div></div>
                    <div class="rounded-xl bg-white border border-gray-200 p-4"><div class="text-[12px] text-gray-500">Entry Fees Collected</div><div class="text-[20px] font-semibold"><?php echo money($debitTot); ?></div></div>
                </div>
            <?php endif; ?>

        </main>
    </div>
</div>
<script>lucide.createIcons();</script>
</body>
</html>
