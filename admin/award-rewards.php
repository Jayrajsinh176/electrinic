<?php
session_start();
include __DIR__ . '/config/db.php';
if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.");
}
$conn->set_charset("utf8mb4");
require_once __DIR__ . '/../includes/income_common.php';
require_once __DIR__ . '/../includes/ind_income_functions.php';

$successMsg = '';
$errorMsg = '';

$periodKey = trim($_GET['period'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $periodKey)) {
    $periodKey = date('Y-m');
}

// Handle Award Distribution
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['disburse_rewards'])) {
    $achievers = [];
    $rawMembers = $_POST['members'] ?? [];
    $rawAmounts = $_POST['amounts'] ?? [];
    $adminNotes = trim($_POST['admin_notes'] ?? 'Monthly Award & Reward bonus');
    $adminName = $_SESSION['admin_name'] ?? ($_SESSION['username'] ?? 'Admin');

    foreach ($rawMembers as $idx => $cid) {
        $cid = trim((string)$cid);
        $amt = (float)($rawAmounts[$idx] ?? 0.0);
        if (!empty($cid) && $amt > 0) {
            $achievers[] = [
                'customer_id' => $cid,
                'amount' => $amt,
                'note' => $adminNotes
            ];
        }
    }

    if (empty($achievers)) {
        $errorMsg = "Please enter reward amount for at least one qualifying leader.";
    } else {
        $distRes = ind_distribute_award_reward($conn, $periodKey, $achievers, $adminName, $adminNotes);
        if (!empty($distRes['ok'])) {
            $tot = number_format($distRes['total_distributed'], 2);
            $cnt = $distRes['count'];
            $successMsg = "Successfully disbursed ₹{$tot} in Award & Reward to {$cnt} leaders! (50% Scooter Wallet / 50% Main Balance applied).";
        } else {
            $errorMsg = "Distribution failed: " . ($distRes['error'] ?? 'Unknown error');
        }
    }
}

// Fetch Pool Status
$poolStatus = ind_get_award_pool_status($conn, $periodKey);

// Fetch Top Leaders (Ranked by Directs Count and Total BV/Turnover)
$leaders = [];
$leadQ = $conn->query("SELECT su.customer_id, su.full_name, su.mobile, su.total_turnover,
                       (SELECT COUNT(*) FROM signup_users d WHERE d.referral_id = su.customer_id AND LOWER(TRIM(d.status)) = 'active') as direct_count
                       FROM signup_users su
                       WHERE LOWER(TRIM(su.status)) = 'active'
                       ORDER BY direct_count DESC, su.total_turnover DESC LIMIT 50");
if ($leadQ) {
    while ($r = $leadQ->fetch_assoc()) {
        $leaders[] = $r;
    }
}

// Fetch Distribution History for Period
$history = [];
$histQ = $conn->query("SELECT d.*, su.full_name, su.mobile 
                       FROM ind_award_reward_distributions d
                       LEFT JOIN signup_users su ON su.customer_id = d.customer_id
                       WHERE d.period_key = '" . $conn->real_escape_string($periodKey) . "'
                       ORDER BY d.id DESC");
if ($histQ) {
    while ($r = $histQ->fetch_assoc()) {
        $history[] = $r;
    }
}

// All available periods
$periods = [];
$pRes = $conn->query("SELECT DISTINCT period_key FROM ind_award_reward_pool ORDER BY period_key DESC");
if ($pRes) {
    while ($r = $pRes->fetch_assoc()) {
        $periods[] = $r['period_key'];
    }
}
if (!in_array(date('Y-m'), $periods, true)) {
    array_unshift($periods, date('Y-m'));
}

if (!function_exists('m')) {
    function m($n) { return '₹' . number_format((float)$n, 2); }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Award &amp; Reward Pool (2.5% Turnover)</title>
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

        <main class="px-4 sm:px-6 lg:px-8 py-5">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 pb-4 border-b border-gray-200">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-[8px] border border-[#ead9b5] bg-[#f8f1e7] flex items-center justify-center shrink-0">
                        <i data-lucide="award" class="w-5 h-5 text-[#c79435]"></i>
                    </div>
                    <div>
                        <h2 class="text-[22px] sm:text-[24px] font-normal text-[#313131] leading-tight tracking-wide" style="font-family: Georgia, 'Times New Roman', serif;">
                            AWARD &amp; REWARD POOL (2.5% TURNOVER)
                        </h2>
                        <p class="text-xs text-gray-500 mt-0.5">2.5% company turnover reserved for qualifying active leaders and target achievers.</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <label class="text-xs font-semibold text-gray-600">Select Month:</label>
                    <form method="GET" class="inline">
                        <select name="period" onchange="this.form.submit()" class="rounded-xl border border-gray-300 bg-white px-3 py-1.5 text-xs font-bold text-gray-700 outline-none shadow-sm">
                            <?php foreach ($periods as $p): ?>
                                <option value="<?php echo htmlspecialchars($p); ?>" <?php echo $p === $periodKey ? 'selected' : ''; ?>>
                                    <?php echo date('F Y', strtotime($p . '-01')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
            </div>

        <?php if (!empty($successMsg)): ?>
            <div class="mt-4 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-xl text-sm flex items-center gap-2">
                <i data-lucide="check-circle" class="w-4 h-4 text-green-600 shrink-0"></i>
                <span><?php echo htmlspecialchars($successMsg); ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($errorMsg)): ?>
            <div class="mt-4 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl text-sm flex items-center gap-2">
                <i data-lucide="alert-circle" class="w-4 h-4 text-red-600 shrink-0"></i>
                <span><?php echo htmlspecialchars($errorMsg); ?></span>
            </div>
        <?php endif; ?>

        <!-- Stat Cards -->
        <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-sm">
                <p class="text-xs uppercase font-semibold text-gray-400">Total Sales Turnover</p>
                <p class="text-2xl font-bold text-gray-800 mt-1"><?php echo m($poolStatus['sales_turnover']); ?></p>
                <p class="text-[11px] text-gray-500 mt-0.5"><?php echo date('F Y', strtotime($periodKey . '-01')); ?> Sales</p>
            </div>
            <div class="bg-gradient-to-br from-[#b47e1d] to-[#8c5f0f] rounded-2xl p-5 text-white shadow-sm">
                <p class="text-xs uppercase font-semibold text-amber-100">2.5% Award Pool Fund</p>
                <p class="text-2xl font-bold text-white mt-1"><?php echo m($poolStatus['pool_amount']); ?></p>
                <p class="text-[11px] text-amber-200 mt-0.5">Reserved from Turnover</p>
            </div>
            <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-sm">
                <p class="text-xs uppercase font-semibold text-gray-400">Total Distributed</p>
                <p class="text-2xl font-bold text-green-700 mt-1"><?php echo m($poolStatus['distributed_amount']); ?></p>
                <p class="text-[11px] text-gray-500 mt-0.5">Awarded to Leaders</p>
            </div>
            <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-sm">
                <p class="text-xs uppercase font-semibold text-gray-400">Available Pool Balance</p>
                <p class="text-2xl font-bold <?php echo $poolStatus['balance'] > 0 ? 'text-[#b47e1d]' : 'text-gray-700'; ?> mt-1">
                    <?php echo m($poolStatus['balance']); ?>
                </p>
                <p class="text-[11px] text-gray-500 mt-0.5">Ready for Distribution</p>
            </div>
        </div>

        <!-- Section 1: Disburse Rewards Form -->
        <div class="mt-8 bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-bold text-gray-800">Select Leaders &amp; Disburse Rewards</h2>
                    <p class="text-xs text-gray-500">Enter reward amounts for qualifying achievers. Incomes will automatically split 50% Scooter Wallet / 50% Main Balance.</p>
                </div>
            </div>

            <form method="POST" id="disburseForm" onsubmit="return confirm('Are you sure you want to disburse rewards to the selected leaders?');">
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-gray-50 text-gray-500 uppercase font-semibold border-b border-gray-200">
                            <tr>
                                <th class="px-4 py-3 text-center w-12">#</th>
                                <th class="px-4 py-3">Member Details</th>
                                <th class="px-4 py-3 text-center">Active Directs</th>
                                <th class="px-4 py-3 text-right">Team Turnover</th>
                                <th class="px-4 py-3 text-right w-48">Reward Amount (₹)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($leaders)): ?>
                                <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">No active leaders found.</td></tr>
                            <?php else: foreach ($leaders as $idx => $l): ?>
                                <tr class="data-row">
                                    <td class="px-4 py-3 text-center font-bold text-gray-400"><?php echo $idx + 1; ?></td>
                                    <td class="px-4 py-3">
                                        <div class="font-bold text-gray-900"><?php echo htmlspecialchars($l['full_name']); ?></div>
                                        <div class="text-[11px] text-gray-500 font-mono"><?php echo htmlspecialchars($l['customer_id']); ?> · <?php echo htmlspecialchars($l['mobile']); ?></div>
                                        <input type="hidden" name="members[]" value="<?php echo htmlspecialchars($l['customer_id']); ?>">
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-50 text-amber-800 border border-amber-200">
                                            <?php echo (int)$l['direct_count']; ?> Directs
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-semibold text-gray-700">
                                        <?php echo m($l['total_turnover']); ?>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="relative">
                                            <span class="absolute inset-y-0 left-0 pl-2.5 flex items-center text-gray-400 text-xs">₹</span>
                                            <input type="number" step="0.01" name="amounts[]" placeholder="0.00" class="w-full pl-6 pr-2 py-1.5 border border-gray-300 rounded-lg text-xs font-semibold text-right outline-none focus:border-[#b47e1d] focus:bg-amber-50/20">
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="w-full sm:w-1/2">
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Admin Notes / Remarks:</label>
                        <input type="text" name="admin_notes" value="Award & Reward Bonus for <?php echo date('F Y', strtotime($periodKey . '-01')); ?>" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-xs outline-none focus:border-[#b47e1d]">
                    </div>
                    <div>
                        <button type="submit" name="disburse_rewards" class="px-6 py-2.5 bg-[#b47e1d] hover:bg-[#8c5f0f] text-white rounded-xl text-xs font-bold transition flex items-center gap-2 shadow-sm">
                            <i data-lucide="check-circle" class="w-4 h-4"></i> Disburse Award &amp; Rewards
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Section 2: Distribution History Table -->
        <div class="mt-8 bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="text-base font-bold text-gray-800">Distribution History (<?php echo date('F Y', strtotime($periodKey . '-01')); ?>)</h2>
                <p class="text-xs text-gray-500">Record of disbursed awards and 50/50 wallet allocations.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead class="bg-gray-50 text-gray-500 uppercase font-semibold border-b border-gray-200">
                        <tr>
                            <th class="px-4 py-3">Member Details</th>
                            <th class="px-4 py-3 text-right">Gross Reward</th>
                            <th class="px-4 py-3 text-right">50% Scooter Wallet</th>
                            <th class="px-4 py-3 text-right">50% Main Balance</th>
                            <th class="px-4 py-3">Awarded By</th>
                            <th class="px-4 py-3">Notes</th>
                            <th class="px-4 py-3 text-left">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (empty($history)): ?>
                            <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">No awards disbursed for this period yet.</td></tr>
                        <?php else: foreach ($history as $h): ?>
                            <tr class="data-row">
                                <td class="px-4 py-3">
                                    <div class="font-bold text-gray-900"><?php echo htmlspecialchars($h['full_name'] ?: $h['customer_id']); ?></div>
                                    <div class="text-[11px] text-gray-500 font-mono"><?php echo htmlspecialchars($h['customer_id']); ?></div>
                                </td>
                                <td class="px-4 py-3 text-right font-bold text-[#b47e1d] text-sm">
                                    <?php echo m($h['reward_amount']); ?>
                                </td>
                                <td class="px-4 py-3 text-right font-medium text-green-700">
                                    <?php echo m($h['scooter_wallet_amount']); ?>
                                </td>
                                <td class="px-4 py-3 text-right font-medium text-blue-700">
                                    <?php echo m($h['main_balance_amount']); ?>
                                </td>
                                <td class="px-4 py-3 font-medium text-gray-600">
                                    <?php echo htmlspecialchars($h['awarded_by'] ?: 'Admin'); ?>
                                </td>
                                <td class="px-4 py-3 text-gray-500">
                                    <?php echo htmlspecialchars($h['notes'] ?: '—'); ?>
                                </td>
                                <td class="px-4 py-3 text-gray-500 font-mono">
                                    <?php echo htmlspecialchars(substr($h['created_at'], 0, 16)); ?>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>
    </div>
</div>

<script>
lucide.createIcons();
</script>
</body>
</html>
