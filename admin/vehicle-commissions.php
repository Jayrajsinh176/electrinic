<?php
session_start();
include __DIR__ . '/config/db.php';
if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.");
}
$conn->set_charset("utf8mb4");
require_once __DIR__ . '/../includes/income_common.php';
require_once __DIR__ . '/../includes/ind_income_functions.php';

function money_fmt($n) { return '₹' . number_format((float)$n, 2); }

// Filters
$typeFilter = trim($_GET['type'] ?? '');
$search = trim($_GET['search'] ?? '');

$where = ["1=1"];
$params = [];
$types = "";

if ($typeFilter === 'direct') {
    $where[] = "vc.commission_type = 'direct'";
} elseif ($typeFilter === 'team_level') {
    $where[] = "vc.commission_type = 'team_level'";
}

if (!empty($search)) {
    $where[] = "(vc.order_ref LIKE ? OR vc.buyer_id LIKE ? OR vc.earner_id LIKE ? OR su_buyer.full_name LIKE ? OR su_earner.full_name LIKE ?)";
    $like = "%{$search}%";
    $params = array_merge($params, [$like, $like, $like, $like, $like]);
    $types .= "sssss";
}

$whereSql = implode(" AND ", $where);

// Aggregates & Table check
$totalCount = 0;
$totalVolume = 0.0;
$totalGross = 0.0;
$directGross = 0.0;
$teamGross = 0.0;
$totalMain = 0.0;
$totalScooter = 0.0;
$totalDeduct = 0.0;

$tableExists = function_exists('income_table_exists') ? income_table_exists($conn, 'ind_vehicle_commissions') : true;

if ($tableExists) {
    $cols = [];
    $cq = $conn->query("SHOW COLUMNS FROM ind_vehicle_commissions");
    while ($cq && ($col = $cq->fetch_assoc())) {
        $cols[$col['Field']] = true;
    }

    $mainExpr = isset($cols['main_amount']) ? 'main_amount' : (isset($cols['main_balance_amount']) ? 'main_balance_amount' : '0');
    $scooterExpr = isset($cols['scooter_amount']) ? 'scooter_amount' : (isset($cols['scooter_wallet_amount']) ? 'scooter_wallet_amount' : '0');
    $deductExpr = isset($cols['deduction_amount']) ? 'deduction_amount' : '0';

    $totRes = $conn->query("SELECT 
        COUNT(*) as total_count,
        COALESCE(SUM(vehicle_price), 0) as total_volume,
        COALESCE(SUM(gross_amount), 0) as total_gross,
        COALESCE(SUM(CASE WHEN commission_type = 'direct' THEN gross_amount ELSE 0 END), 0) as direct_gross,
        COALESCE(SUM(CASE WHEN commission_type = 'team_level' THEN gross_amount ELSE 0 END), 0) as team_gross,
        COALESCE(SUM({$mainExpr}), 0) as total_main,
        COALESCE(SUM({$scooterExpr}), 0) as total_scooter,
        COALESCE(SUM({$deductExpr}), 0) as total_deduction
        FROM ind_vehicle_commissions");
    if ($totRes && ($tr = $totRes->fetch_assoc())) {
        $totalCount = (int)($tr['total_count'] ?? 0);
        $totalVolume = (float)($tr['total_volume'] ?? 0);
        $totalGross = (float)($tr['total_gross'] ?? 0);
        $directGross = (float)($tr['direct_gross'] ?? 0);
        $teamGross = (float)($tr['team_gross'] ?? 0);
        $totalMain = (float)($tr['total_main'] ?? 0);
        $totalScooter = (float)($tr['total_scooter'] ?? 0);
        $totalDeduct = (float)($tr['total_deduction'] ?? 0);
    }
}

// Fetch records
$commissions = [];
if ($tableExists) {
    $query = "SELECT vc.*, 
                     su_buyer.full_name as buyer_name, su_buyer.mobile as buyer_mobile,
                     su_earner.full_name as earner_name, su_earner.mobile as earner_mobile
              FROM ind_vehicle_commissions vc
              LEFT JOIN signup_users su_buyer ON su_buyer.customer_id = vc.buyer_id
              LEFT JOIN signup_users su_earner ON su_earner.customer_id = vc.earner_id
              WHERE {$whereSql}
              ORDER BY vc.id DESC LIMIT 300";

    if (!empty($params)) {
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) $commissions[] = $r;
        $stmt->close();
    } else {
        $res = $conn->query($query);
        if ($res) {
            while ($r = $res->fetch_assoc()) $commissions[] = $r;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicle Sales Commissions - Master Audit</title>
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

            <!-- Header -->
            <div class="flex items-center justify-between gap-4 mb-6 pb-4 border-b border-gray-200">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-[8px] border border-[#ead9b5] bg-[#f8f1e7] flex items-center justify-center shrink-0">
                        <i data-lucide="bike" class="w-5 h-5 text-[#c79435]"></i>
                    </div>
                    <div>
                        <h2 class="text-[22px] sm:text-[24px] font-normal text-[#313131] leading-tight tracking-wide" style="font-family: Georgia, 'Times New Roman', serif;">
                            VEHICLE SALES COMMISSIONS (16% POOL)
                        </h2>
                        <p class="text-xs text-gray-500 mt-0.5">Audit log of 5% Direct Referral and 11% 10-Level Team commissions on electric vehicle sales.</p>
                    </div>
                </div>
            </div>

            <!-- Aggregate Metric Cards -->
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
                <div class="rounded-2xl bg-white border border-gray-200 p-5 shadow-sm">
                    <div class="text-xs uppercase font-semibold text-gray-500">Total Disbursements</div>
                    <div class="text-2xl font-bold text-gray-900 mt-1"><?php echo number_format($totalCount); ?></div>
                    <div class="text-[11px] text-gray-400 mt-0.5">All vehicle commission events</div>
                </div>
                <div class="rounded-2xl bg-white border border-gray-200 p-5 shadow-sm">
                    <div class="text-xs uppercase font-semibold text-gray-500">Total Commissions</div>
                    <div class="text-2xl font-bold text-[#b47e1d] mt-1"><?php echo money_fmt($totalGross); ?></div>
                    <div class="text-[11px] text-gray-400 mt-0.5">Gross vehicle earnings</div>
                </div>
                <div class="rounded-2xl bg-white border border-gray-200 p-5 shadow-sm">
                    <div class="text-xs uppercase font-semibold text-gray-500">Direct 5% Referral</div>
                    <div class="text-2xl font-bold text-blue-700 mt-1"><?php echo money_fmt($directGross); ?></div>
                    <div class="text-[11px] text-blue-600 mt-0.5">Direct sponsor commissions</div>
                </div>
                <div class="rounded-2xl bg-white border border-gray-200 p-5 shadow-sm">
                    <div class="text-xs uppercase font-semibold text-gray-500">Team 11% (10 Levels)</div>
                    <div class="text-2xl font-bold text-purple-700 mt-1"><?php echo money_fmt($teamGross); ?></div>
                    <div class="text-[11px] text-purple-600 mt-0.5">10-level team pool payout</div>
                </div>
                <div class="rounded-2xl bg-white border border-gray-200 p-5 shadow-sm">
                    <div class="text-xs uppercase font-semibold text-gray-500">Dual Wallet Credits</div>
                    <div class="text-sm font-bold text-gray-900 mt-1">Main: <?php echo money_fmt($totalMain); ?></div>
                    <div class="text-sm font-bold text-[#b47e1d]">Scooter: <?php echo money_fmt($totalScooter); ?></div>
                    <div class="text-[10px] text-gray-400 mt-0.5">Deductions: <?php echo money_fmt($totalDeduct); ?></div>
                </div>
            </div>

            <!-- Filter Bar -->
            <div class="bg-white rounded-2xl border border-gray-200 p-4 shadow-sm mb-6 flex flex-col sm:flex-row items-center justify-between gap-4">
                <form method="GET" class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
                    <div>
                        <select name="type" class="border border-gray-200 rounded-xl px-3 py-2 text-xs font-semibold text-gray-700 focus:outline-none focus:border-[#c79435]">
                            <option value="">All Types (Direct &amp; Team)</option>
                            <option value="direct" <?php echo $typeFilter === 'direct' ? 'selected' : ''; ?>>Direct 5% Only</option>
                            <option value="team_level" <?php echo $typeFilter === 'team_level' ? 'selected' : ''; ?>>Team 11% (10 Levels) Only</option>
                        </select>
                    </div>
                    <div>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search Order, Buyer, Earner..." class="border border-gray-200 rounded-xl px-3 py-2 text-xs focus:outline-none focus:border-[#c79435] w-64">
                    </div>
                    <button type="submit" class="px-4 py-2 bg-[#c79435] hover:bg-[#b0802c] text-white rounded-xl text-xs font-bold transition flex items-center gap-1.5">
                        <i data-lucide="search" class="w-3.5 h-3.5"></i> Filter
                    </button>
                    <?php if (!empty($typeFilter) || !empty($search)): ?>
                        <a href="vehicle-commissions.php" class="text-xs text-gray-500 hover:text-gray-800 underline">Reset</a>
                    <?php endif; ?>
                </form>
                <div class="text-xs text-gray-500">
                    Showing <?php echo count($commissions); ?> records
                </div>
            </div>

            <!-- Audit Table -->
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-gray-50 text-gray-500 uppercase font-semibold border-b border-gray-200">
                            <tr>
                                <th class="px-4 py-3">Order / Date</th>
                                <th class="px-4 py-3">Buyer</th>
                                <th class="px-4 py-3">Earner (Upline)</th>
                                <th class="px-4 py-3">Type</th>
                                <th class="px-4 py-3">Vehicle Model</th>
                                <th class="px-4 py-3 text-right">Price</th>
                                <th class="px-4 py-3 text-center">Rate</th>
                                <th class="px-4 py-3 text-right font-bold">Gross</th>
                                <th class="px-4 py-3 text-right text-red-600">10% Ded</th>
                                <th class="px-4 py-3 text-right text-emerald-700 font-semibold">Main (50%)</th>
                                <th class="px-4 py-3 text-right text-[#b47e1d] font-semibold">Scooter (50%)</th>
                                <th class="px-4 py-3 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($commissions)): ?>
                                <tr>
                                    <td colspan="12" class="px-4 py-12 text-center text-gray-400">
                                        No vehicle commission records found matching the filter criteria.
                                    </td>
                                </tr>
                            <?php else: foreach ($commissions as $c): ?>
                                <tr class="data-row transition">
                                    <td class="px-4 py-3">
                                        <div class="font-mono font-bold text-gray-900"><?php echo htmlspecialchars($c['order_ref']); ?></div>
                                        <div class="text-[11px] text-gray-400"><?php echo date('d M Y, h:i A', strtotime($c['created_at'])); ?></div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-bold text-gray-900"><?php echo htmlspecialchars($c['buyer_name'] ?? $c['buyer_id']); ?></div>
                                        <div class="text-[11px] text-gray-500 font-mono"><?php echo htmlspecialchars($c['buyer_id']); ?></div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-bold text-gray-900"><?php echo htmlspecialchars($c['earner_name'] ?? $c['earner_id']); ?></div>
                                        <div class="text-[11px] text-gray-500 font-mono"><?php echo htmlspecialchars($c['earner_id']); ?></div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <?php if ($c['commission_type'] === 'direct'): ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-200">
                                                Direct 5%
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-purple-50 text-purple-700 border border-purple-200">
                                                Team L<?php echo (int)($c['level_no'] ?? $c['level_number'] ?? 0); ?> (<?php echo number_format($c['percent'], 1); ?>%)
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 font-semibold text-gray-800">
                                        <?php echo htmlspecialchars($c['vehicle_model']); ?>
                                    </td>
                                    <td class="px-4 py-3 text-right font-medium text-gray-700">
                                        <?php echo money_fmt($c['vehicle_price']); ?>
                                    </td>
                                    <td class="px-4 py-3 text-center font-bold text-gray-800">
                                        <?php echo number_format($c['percent'], 1); ?>%
                                    </td>
                                    <td class="px-4 py-3 text-right font-bold text-gray-900">
                                        <?php echo money_fmt($c['gross_amount']); ?>
                                    </td>
                                    <td class="px-4 py-3 text-right text-red-600">
                                        -<?php echo money_fmt($c['deduction_amount'] ?? 0); ?>
                                    </td>
                                    <td class="px-4 py-3 text-right font-semibold text-emerald-700">
                                        <?php echo money_fmt($c['main_amount'] ?? $c['main_balance_amount'] ?? 0); ?>
                                    </td>
                                    <td class="px-4 py-3 text-right font-semibold text-[#b47e1d]">
                                        <?php echo money_fmt($c['scooter_amount'] ?? $c['scooter_wallet_amount'] ?? 0); ?>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-green-100 text-green-800 uppercase">
                                            <?php echo htmlspecialchars($c['status']); ?>
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
</div>
<script>lucide.createIcons();</script>
</body>
</html>

