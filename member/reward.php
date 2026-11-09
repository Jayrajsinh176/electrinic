<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include __DIR__ . '/config/db.php';
require_once __DIR__ . '/../includes/income_common.php';

if (!isset($_SESSION['customer_id']) && !isset($_SESSION['member_id'])) {
    header("Location: login.php");
    exit();
}

$customerId = $_SESSION['customer_id'] ?? $_SESSION['member_id'];

// 1. Official 10 Award & Reward Ranks (PDF Page 9)
$ranks = [
    1  => ['rank' => 'Star Leader', 'target' => '10 Vehicles', 'reward' => 'Induction Cooktop / Mixer Grinder', 'value' => 3000],
    2  => ['rank' => 'Silver Leader', 'target' => '25 Vehicles', 'reward' => '32" Smart LED TV', 'value' => 10000],
    3  => ['rank' => 'Gold Leader', 'target' => '50 Vehicles', 'reward' => 'Smart Tablet / Work Laptop', 'value' => 25000],
    4  => ['rank' => 'Pearl Leader', 'target' => '100 Vehicles', 'reward' => 'Bangkok Tour Package (3N/4D) / Cash', 'value' => 50000],
    5  => ['rank' => 'Emerald Leader', 'target' => '250 Vehicles', 'reward' => 'Royal Enfield Bullet / Cash', 'value' => 150000],
    6  => ['rank' => 'Ruby Leader', 'target' => '500 Vehicles', 'reward' => 'Maruti Alto K10 Car / Cash', 'value' => 300000],
    7  => ['rank' => 'Diamond Leader', 'target' => '1,000 Vehicles', 'reward' => 'Maruti Brezza SUV / Cash', 'value' => 700000],
    8  => ['rank' => 'Blue Diamond', 'target' => '2,500 Vehicles', 'reward' => 'Grand Vitara Hybrid / Cash', 'value' => 1500000],
    9  => ['rank' => 'Crown Diamond', 'target' => '5,000 Vehicles', 'reward' => 'Toyota Fortuner SUV / Cash', 'value' => 3500000],
    10 => ['rank' => 'Universal King', 'target' => '10,000 Vehicles', 'reward' => 'Luxury 3BHK Villa / ₹1.00 Crore Cash', 'value' => 10000000],
];

// Fetch member's direct & team vehicles count
$directVehicles = (int)($conn->query("SELECT COUNT(*) c FROM ind_scooter_promotions sp JOIN signup_users su ON su.customer_id = sp.customer_id WHERE su.referral_id = '{$customerId}' AND sp.status = 'active'")->fetch_assoc()['c'] ?? 0);

// Fetch member's awarded rewards from ind_award_reward_distributions or legacy
$rewardRows = [];
$totalRewarded = 0.0;

if (income_table_exists($conn, 'ind_award_reward_distributions')) {
    $stmt = $conn->prepare("SELECT * FROM ind_award_reward_distributions WHERE customer_id = ? ORDER BY id DESC");
    $stmt->bind_param("s", $customerId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $amt = (float)$r['reward_amount'];
        $totalRewarded += $amt;
        $rewardRows[] = [
            'id' => $r['id'],
            'reward' => !empty($r['notes']) ? $r['notes'] : "Monthly Award ({$r['period_key']})",
            'amount' => $amt,
            'scooter' => (float)$r['scooter_wallet_amount'],
            'main' => (float)$r['main_balance_amount'],
            'date' => date('d-m-Y', strtotime($r['created_at'])),
            'status' => 'Paid'
        ];
    }
    $stmt->close();
}

function money_f($n) { return '₹' . number_format((float)$n, 2); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Award &amp; Reward Program - Electrinc</title>
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

    <main class="p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 pb-4 border-b border-gray-200">
            <div>
                <div class="flex items-center gap-2 text-xs text-gray-500 mb-1">
                    <a href="dashboard.php" class="hover:text-[#c79435]">Dashboard</a>
                    <span>/</span>
                    <span class="text-gray-700 font-medium">Income Status</span>
                    <span>/</span>
                    <span class="text-[#c79435] font-semibold">Award &amp; Reward</span>
                </div>
                <h1 class="text-xl sm:text-2xl font-bold text-gray-900 tracking-tight flex items-center gap-2.5">
                    <span class="w-8 h-8 rounded-lg bg-[#f8f1e7] border border-[#ead9b5] flex items-center justify-center text-[#c79435]">
                        <i data-lucide="gift" class="w-4 h-4"></i>
                    </span>
                    2.5% Company Turnover Award &amp; Reward
                </h1>
                <p class="text-xs text-gray-500 mt-1">2.5% of total company EV sales turnover is pooled monthly and awarded across 10 leadership ranks.</p>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-800 border border-amber-200">
                    <i data-lucide="trophy" class="w-3.5 h-3.5 mr-1 text-[#c79435]"></i> 10 Milestone Ranks
                </span>
            </div>
        </div>

        <!-- Metric Summary Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
            <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-sm">
                <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Rewards Received</span>
                <div class="text-2xl font-extrabold text-[#c79435] mt-2"><?php echo money_f($totalRewarded); ?></div>
                <div class="text-[11px] text-gray-500 mt-1"><?php echo count($rewardRows); ?> Award distributions credited</div>
            </div>

            <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-sm">
                <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Active Direct Vehicles</span>
                <div class="text-2xl font-extrabold text-blue-700 mt-2"><?php echo $directVehicles; ?> Vehicles</div>
                <div class="text-[11px] text-blue-600 mt-1">Count towards Rank &amp; Free Scooter escalation</div>
            </div>

            <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-sm bg-gradient-to-br from-amber-50/40 to-white">
                <span class="text-xs font-semibold text-amber-800 uppercase tracking-wider">Turnover Pool Share</span>
                <div class="text-2xl font-extrabold text-emerald-700 mt-2">2.5% Company CTO</div>
                <div class="text-[11px] text-gray-500 mt-1">Split: 50% Scooter Wallet / 50% Main Balance</div>
            </div>
        </div>

        <!-- 10 Leadership Ranks Ladder (PDF Page 9) -->
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden mb-8">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <div>
                    <h2 class="text-base font-bold text-gray-900">Official 10 Leadership Reward Milestones (PDF Page 9)</h2>
                    <p class="text-xs text-gray-500">Qualify by achieving cumulative electric vehicle milestones with your team.</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead class="bg-[#C89741] text-white uppercase font-semibold">
                        <tr>
                            <th class="px-4 py-3">Rank Level</th>
                            <th class="px-4 py-3">Leadership Title</th>
                            <th class="px-4 py-3 text-center">Vehicle Target</th>
                            <th class="px-4 py-3">Reward / Benefit</th>
                            <th class="px-4 py-3 text-right">Estimated Value</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($ranks as $lvl => $rk): ?>
                            <tr class="data-row transition">
                                <td class="px-4 py-3 font-bold text-gray-500">Rank <?php echo $lvl; ?></td>
                                <td class="px-4 py-3 font-bold text-gray-900 flex items-center gap-1.5">
                                    <i data-lucide="medal" class="w-4 h-4 text-[#c79435]"></i>
                                    <?php echo htmlspecialchars($rk['rank']); ?>
                                </td>
                                <td class="px-4 py-3 text-center font-bold text-blue-700"><?php echo htmlspecialchars($rk['target']); ?></td>
                                <td class="px-4 py-3 font-semibold text-gray-800"><?php echo htmlspecialchars($rk['reward']); ?></td>
                                <td class="px-4 py-3 text-right font-bold text-emerald-700"><?php echo money_f($rk['value']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- My Award Earnings History -->
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <h2 class="text-base font-bold text-gray-900">My Reward Disbursements Log</h2>
                <span class="px-3 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-700">
                    <?php echo count($rewardRows); ?> Records
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead class="bg-gray-50 text-gray-500 uppercase font-semibold border-b border-gray-200">
                        <tr>
                            <th class="px-4 py-3">#</th>
                            <th class="px-4 py-3">Reward Description</th>
                            <th class="px-4 py-3 text-right">Gross Amount</th>
                            <th class="px-4 py-3 text-right text-emerald-700">Main Balance (50%)</th>
                            <th class="px-4 py-3 text-right text-[#b47e1d]">Scooter Wallet (50%)</th>
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (empty($rewardRows)): ?>
                            <tr>
                                <td colspan="7" class="px-4 py-10 text-center text-gray-400">
                                    No award distributions credited yet. Keep advancing your vehicle volume to qualify!
                                </td>
                            </tr>
                        <?php else: foreach ($rewardRows as $i => $r): ?>
                            <tr class="data-row transition">
                                <td class="px-4 py-3 text-gray-400"><?php echo $i + 1; ?></td>
                                <td class="px-4 py-3 font-semibold text-gray-900"><?php echo htmlspecialchars($r['reward']); ?></td>
                                <td class="px-4 py-3 text-right font-bold text-gray-900"><?php echo money_f($r['amount']); ?></td>
                                <td class="px-4 py-3 text-right font-semibold text-emerald-700"><?php echo money_f($r['main']); ?></td>
                                <td class="px-4 py-3 text-right font-semibold text-[#b47e1d]"><?php echo money_f($r['scooter']); ?></td>
                                <td class="px-4 py-3 text-gray-600"><?php echo htmlspecialchars($r['date']); ?></td>
                                <td class="px-4 py-3 text-center">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-green-100 text-green-800">
                                        <?php echo htmlspecialchars($r['status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>
<script>lucide.createIcons();</script>
</body>
</html>
