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

$directTotal = 0.0;
$directCount = 0;
$teamTotal = 0.0;
$teamCount = 0;
$scooterTotal = 0.0;
$mainTotal = 0.0;
$grossTotal = 0.0;

$commissions = [];

if (income_table_exists($conn, 'ind_vehicle_commissions')) {
    $stmt = $conn->prepare("SELECT vc.*, su.full_name AS buyer_name, su.mobile AS buyer_mobile 
                            FROM ind_vehicle_commissions vc
                            LEFT JOIN signup_users su ON su.customer_id = vc.buyer_id
                            WHERE vc.earner_id = ? 
                            ORDER BY vc.id DESC");
    if ($stmt) {
        $stmt->bind_param("s", $customerId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $commissions[] = $row;
            $gross = (float)$row['gross_amount'];
            $grossTotal += $gross;
            $scooterTotal += (float)($row['scooter_amount'] ?? $row['scooter_wallet_amount'] ?? 0);
            $mainTotal += (float)($row['main_amount'] ?? $row['main_balance_amount'] ?? 0);

            if ($row['commission_type'] === 'direct') {
                $directTotal += $gross;
                $directCount++;
            } else {
                $teamTotal += $gross;
                $teamCount++;
            }
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicle Sales Commission - Electrinc</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body { background:#f3f3f3; }
        @media (min-width: 1024px) { .main-wrap { margin-left: 256px; } }
        .sum-card:hover { box-shadow:0 6px 24px rgba(200,151,65,.18); }
        .data-row:hover { background:#fdf6ea !important; }
    </style>
</head>
<body>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<div class="main-wrap min-h-screen">
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <main class="p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto">
        <!-- Breadcrumb / Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 pb-4 border-b border-gray-200">
            <div>
                <div class="flex items-center gap-2 text-xs text-gray-500 mb-1">
                    <a href="dashboard.php" class="hover:text-[#c79435]">Dashboard</a>
                    <span>/</span>
                    <span class="text-gray-700 font-medium">Income Reports</span>
                    <span>/</span>
                    <span class="text-[#c79435] font-semibold">Vehicle Sales Commission</span>
                </div>
                <h1 class="text-xl sm:text-2xl font-bold text-gray-900 tracking-tight flex items-center gap-2.5">
                    <span class="w-8 h-8 rounded-lg bg-[#f8f1e7] border border-[#ead9b5] flex items-center justify-center text-[#c79435]">
                        <i data-lucide="bike" class="w-4 h-4"></i>
                    </span>
                    Electric Vehicle Sales Commission
                </h1>
                <p class="text-xs text-gray-500 mt-1">Official 16% Commission Pool: 5% Direct Referral + 11% 10-Level Team Distribution (50% Scooter Wallet / 50% Main Balance).</p>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                    <i data-lucide="check-circle" class="w-3.5 h-3.5 mr-1"></i> Active IND Plan
                </span>
            </div>
        </div>

        <!-- Metric Summary Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <div class="bg-white rounded-2xl border border-gray-200 p-5 sum-card transition shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total EV Earnings</span>
                    <span class="w-8 h-8 rounded-xl bg-amber-50 text-[#c79435] flex items-center justify-center">
                        <i data-lucide="award" class="w-4 h-4"></i>
                    </span>
                </div>
                <div class="text-2xl font-extrabold text-gray-900 mt-2"><?php echo money_fmt($grossTotal); ?></div>
                <div class="text-[11px] text-gray-500 mt-1"><?php echo $directCount + $teamCount; ?> Total Sales Commission credits</div>
            </div>

            <div class="bg-white rounded-2xl border border-gray-200 p-5 sum-card transition shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Direct 5% Commission</span>
                    <span class="w-8 h-8 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                        <i data-lucide="user-check" class="w-4 h-4"></i>
                    </span>
                </div>
                <div class="text-2xl font-extrabold text-blue-700 mt-2"><?php echo money_fmt($directTotal); ?></div>
                <div class="text-[11px] text-gray-500 mt-1"><?php echo $directCount; ?> Direct vehicle sales referred</div>
            </div>

            <div class="bg-white rounded-2xl border border-gray-200 p-5 sum-card transition shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Team 11% (10 Levels)</span>
                    <span class="w-8 h-8 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center">
                        <i data-lucide="users" class="w-4 h-4"></i>
                    </span>
                </div>
                <div class="text-2xl font-extrabold text-purple-700 mt-2"><?php echo money_fmt($teamTotal); ?></div>
                <div class="text-[11px] text-gray-500 mt-1"><?php echo $teamCount; ?> Team vehicle purchases credited</div>
            </div>

            <div class="bg-white rounded-2xl border border-gray-200 p-5 sum-card transition shadow-sm bg-gradient-to-br from-amber-50/40 to-white">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-amber-800 uppercase tracking-wider">Dual Wallet Split</span>
                    <span class="w-8 h-8 rounded-xl bg-amber-100 text-[#b47e1d] flex items-center justify-center">
                        <i data-lucide="wallet" class="w-4 h-4"></i>
                    </span>
                </div>
                <div class="mt-2 space-y-1">
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-gray-600 font-medium">Main Balance (50%):</span>
                        <span class="font-bold text-gray-900"><?php echo money_fmt($mainTotal); ?></span>
                    </div>
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-[#b47e1d] font-medium">Scooter Wallet (50%):</span>
                        <span class="font-bold text-[#b47e1d]"><?php echo money_fmt($scooterTotal); ?></span>
                    </div>
                </div>
                <div class="text-[10px] text-gray-400 mt-1.5">*Net after standard 10% deduction</div>
            </div>
        </div>

        <!-- Plan Rules Infobox -->
        <div class="mb-8 rounded-2xl bg-[#fcf9f2] border border-[#ecdcb8] p-5">
            <div class="flex items-start gap-3">
                <div class="w-8 h-8 rounded-xl bg-[#c79435] text-white flex items-center justify-center shrink-0 mt-0.5">
                    <i data-lucide="info" class="w-4 h-4"></i>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-[#313131]">Official 16% Electric Vehicle Commission Structure (PDF Page 8)</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-2 text-xs text-gray-700">
                        <div>
                            <span class="font-bold text-blue-800">• 5.0% Direct Referral Commission:</span> Paid to the direct sponsor whenever a sponsored member purchases an EV (e.g. ₹3,950 on MAX ZXI).
                        </div>
                        <div>
                            <span class="font-bold text-purple-800">• 11.0% Team Level Commission:</span> Distributed across 10 upline levels:
                            <span class="font-mono text-[11px] block text-gray-600 mt-0.5">L1: 3.0% | L2: 2.0% | L3: 1.5% | L4: 1.0% | L5: 1.0% | L6-10: 0.5% each</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Commission History Table -->
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <div>
                    <h2 class="text-base font-bold text-gray-900">Vehicle Commission Earnings Log</h2>
                    <p class="text-xs text-gray-500">Every direct and team vehicle sale commission credited to your account.</p>
                </div>
                <span class="px-3 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-700">
                    <?php echo count($commissions); ?> Transactions
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead class="bg-gray-50 text-gray-500 uppercase font-semibold border-b border-gray-200">
                        <tr>
                            <th class="px-4 py-3">Order / Date</th>
                            <th class="px-4 py-3">Buyer Member</th>
                            <th class="px-4 py-3">Commission Type</th>
                            <th class="px-4 py-3">Vehicle Model</th>
                            <th class="px-4 py-3 text-right">Vehicle Price</th>
                            <th class="px-4 py-3 text-center">Rate %</th>
                            <th class="px-4 py-3 text-right">Gross Income</th>
                            <th class="px-4 py-3 text-right">10% Deduct</th>
                            <th class="px-4 py-3 text-right">Main (50%)</th>
                            <th class="px-4 py-3 text-right text-[#b47e1d]">Scooter (50%)</th>
                            <th class="px-4 py-3 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (empty($commissions)): ?>
                            <tr>
                                <td colspan="11" class="px-4 py-12 text-center text-gray-400">
                                    <div class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-2 text-gray-400">
                                        <i data-lucide="bike" class="w-6 h-6"></i>
                                    </div>
                                    <p class="font-medium text-gray-600">No vehicle sales commissions recorded yet.</p>
                                    <p class="text-[11px] text-gray-400 mt-1">Vehicle purchase commissions will appear here automatically when you or your team buy electric vehicles.</p>
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
                                    <?php if ($c['commission_type'] === 'direct'): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-blue-50 text-blue-700 border border-blue-200">
                                            Direct Referral
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-purple-50 text-purple-700 border border-purple-200">
                                            Team Level <?php echo (int)($c['level_no'] ?? $c['level_number'] ?? 0); ?>
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
<script>
    lucide.createIcons();
</script>
</body>
</html>

