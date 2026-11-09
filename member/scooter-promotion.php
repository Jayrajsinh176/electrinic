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

function m($n) { return '₹' . number_format((float)$n, 2); }

// Fetch active promotion
$promo = null;
$stmt = $conn->prepare("SELECT * FROM ind_scooter_promotions WHERE customer_id = ? AND status = 'active' ORDER BY id DESC LIMIT 1");
$stmt->bind_param('s', $customerId);
$stmt->execute();
$promo = $stmt->get_result()->fetch_assoc();
$stmt->close();

$currentSupport = 50.0;
$directVehicles = 0;
if ($promo) {
    $currentSupport = ind_refresh_scooter_support($conn, $customerId);
    $directVehicles = (int)($promo['direct_vehicles_count'] ?? 0);
}

// Fetch member's personal monthly BPR payouts
$monthlyPayouts = [];
$totBprEarned = 0.0;
$pRes = $conn->query("SELECT * FROM ind_scooter_monthly_payouts WHERE customer_id = '" . $conn->real_escape_string($customerId) . "' ORDER BY month_number ASC");
if ($pRes) {
    while ($pRow = $pRes->fetch_assoc()) {
        $monthlyPayouts[] = $pRow;
        if ($pRow['status'] === 'paid') {
            $totBprEarned += (float)$pRow['company_support_amount'];
        }
    }
}

// Fetch team BPR bonuses earned from downline (Affiliate 10 roots, first 6 months)
$teamBonuses = [];
$totTeamBonus = 0.0;
$tbRes = $conn->query("SELECT tb.*, su.full_name as downline_name 
                      FROM ind_team_promotion_bonus_logs tb
                      LEFT JOIN signup_users su ON su.customer_id = tb.downline_member_id
                      WHERE tb.earner_id = '" . $conn->real_escape_string($customerId) . "'
                      ORDER BY tb.id DESC LIMIT 50");
if ($tbRes) {
    while ($tbRow = $tbRes->fetch_assoc()) {
        $teamBonuses[] = $tbRow;
        $totTeamBonus += (float)$tbRow['bonus_amount'];
    }
}

$totScooterIncome = $totBprEarned + $totTeamBonus;

$myDirectsCount = function_exists('income_active_direct_count') ? income_active_direct_count($conn, $customerId) : 0;
$teamBprRates = [
    1 => 30.0, 2 => 15.0, 3 => 10.0, 4 => 5.0, 5 => 4.0,
    6 => 4.0, 7 => 4.0, 8 => 3.0, 9 => 3.0, 10 => 5.0
];
$teamBprTotals = [];
for ($i = 1; $i <= 10; $i++) {
    $teamBprTotals[$i] = ['pct' => $teamBprRates[$i], 'earned' => 0.0, 'count' => 0];
}
foreach ($teamBonuses as $tb) {
    $rl = (int)$tb['root_level'];
    if (isset($teamBprTotals[$rl])) {
        $teamBprTotals[$rl]['earned'] += (float)$tb['bonus_amount'];
        $teamBprTotals[$rl]['count']++;
    }
}

// Fetch recent verification submissions status
$recentVerifications = [];
if ($promo) {
    $vRes = $conn->query("SELECT id, month_number, km_run, status, created_at FROM ind_video_verifications WHERE promotion_id = " . (int)$promo['id'] . " ORDER BY id DESC LIMIT 5");
    if ($vRes) {
        while ($vRow = $vRes->fetch_assoc()) {
            $recentVerifications[] = $vRow;
        }
    }
}

$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scooter Brand Promotion Income Report</title>
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
                    <i data-lucide="bike" class="w-[17px] h-[17px] text-[#cd9737]"></i>
                </div>
                <div>
                    <h2 class="text-[20px] font-normal text-[#40352a]" style="font-family: Georgia, 'Times New Roman', serif;">SCOOTER BRAND PROMOTION INCOME</h2>
                    <p class="text-xs text-[#8a7c68]">Monthly Brand Promotion Support (BPR) &amp; 10-Level Downline Affiliate Compensation</p>
                </div>
            </div>
            <div>
                <a href="scooter-verification.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-[#40352a] hover:bg-[#2c241c] text-white text-xs font-semibold transition shadow-sm">
                    <i data-lucide="video" class="w-4 h-4 text-[#e7d3ad]"></i>
                    <span>Vehicle Verification &amp; Upload &rarr;</span>
                </a>
            </div>
        </div>
        <div class="w-full h-[2px] bg-[#d1a14b] mt-3"></div>

        <!-- Verification Reminder / Action Banner -->
        <div class="mt-4 bg-gradient-to-r from-[#faf6ee] to-[#f4ede0] border border-[#e2d8c8] rounded-2xl p-4 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-start sm:items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-[#b47e1d] text-white flex items-center justify-center shrink-0 shadow-sm">
                    <i data-lucide="info" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-[#40352a]">
                        <?php echo $promo ? "Monthly Verification & Proof Submission" : "Enrolled Vehicle Required"; ?>
                    </h3>
                    <p class="text-xs text-[#6e5d48] mt-0.5">
                        <?php if ($promo): ?>
                            Submit your monthly riding video, live GPS coordinates, and odometer photo to qualify for this month's BPR support payout.
                        <?php else: ?>
                            You have not enrolled an Electric Scooter yet. Register your vehicle on the Vehicle Verification page to start receiving monthly BPR support up to 100% Free!
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <div class="shrink-0 flex items-center gap-2">
                <a href="scooter-verification.php" class="px-4 py-2 bg-[#b47e1d] hover:bg-[#966513] text-white rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-sm">
                    <i data-lucide="upload" class="w-3.5 h-3.5"></i>
                    <span><?php echo $promo ? "Upload Monthly Proof" : "Register Vehicle"; ?></span>
                </a>
            </div>
        </div>

        <!-- Key Financial Summary Cards -->
        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <!-- Total Scooter Income -->
            <div class="bg-gradient-to-br from-[#1b4332] to-[#081c15] rounded-2xl px-5 py-4 text-white shadow-sm">
                <p class="text-[10px] uppercase tracking-widest text-[#95d5b2] font-semibold">Total Scooter Income</p>
                <p class="mt-1 text-[26px] font-bold text-white"><?php echo m($totScooterIncome); ?></p>
                <p class="text-[10px] text-[#d8f3dc] mt-1 flex items-center justify-between">
                    <span>Personal BPR: <?php echo m($totBprEarned); ?></span>
                    <span>Team: <?php echo m($totTeamBonus); ?></span>
                </p>
            </div>

            <!-- Current Monthly Support -->
            <div class="bg-gradient-to-br from-[#40352a] to-[#251e18] rounded-2xl px-5 py-4 text-white shadow-sm">
                <p class="text-[10px] uppercase tracking-widest text-[#e7d3ad] font-semibold">Company Monthly Support</p>
                <p class="mt-1 text-[26px] font-bold <?php echo $currentSupport >= 100 ? 'text-[#a8e6a1]' : 'text-white'; ?>">
                    <?php echo number_format($currentSupport, 0); ?>%
                </p>
                <p class="text-[10px] text-[#c9bba6] mt-1">
                    <?php if ($promo): ?>
                        <?php echo $currentSupport >= 100 ? '100% Free Scooter Achieved!' : (m($promo['monthly_bpr_amount'] * $currentSupport / 100) . ' / month company share'); ?>
                    <?php else: ?>
                        50% Base Support upon vehicle enrollment
                    <?php endif; ?>
                </p>
            </div>

            <!-- Direct Vehicles Sponsored -->
            <div class="bg-white rounded-2xl border border-[#e2d8c8] px-5 py-4 shadow-sm">
                <p class="text-[10px] uppercase tracking-widest text-[#9e8f7e] font-semibold">Direct Vehicles Sponsored</p>
                <p class="mt-1 text-[24px] font-bold text-[#40352a]"><?php echo $directVehicles; ?> Vehicles</p>
                <p class="text-[10px] text-[#8a7c68] mt-1">
                    <?php if ($directVehicles >= 10): ?>
                        <span class="text-green-700 font-bold">Max 100% Free Achieved</span>
                    <?php else: ?>
                        Need <?php echo (10 - $directVehicles); ?> more for 100% Free
                    <?php endif; ?>
                </p>
            </div>

            <!-- 25-Month Timeline -->
            <div class="bg-white rounded-2xl border border-[#e2d8c8] px-5 py-4 shadow-sm">
                <p class="text-[10px] uppercase tracking-widest text-[#9e8f7e] font-semibold">Support Program Duration</p>
                <p class="mt-1 text-[24px] font-bold text-[#b47e1d]">
                    <?php echo $promo ? ((int)$promo['months_paid'] . ' / ' . (int)$promo['months_total'] . ' Mo') : '25-36 Months'; ?>
                </p>
                <p class="text-[10px] text-[#8a7c68] mt-1">
                    <?php echo $promo ? htmlspecialchars($promo['vehicle_model']) : 'Contractual Brand Promotion'; ?>
                </p>
            </div>
        </div>

        <!-- Escalation Ladder to 100% Free Scooter -->
        <div class="mt-4 bg-white rounded-2xl border border-[#e2d8c8] p-5 shadow-sm">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                <div>
                    <h3 class="text-sm font-bold text-[#40352a]">Escalation Ladder to 100% Free Scooter</h3>
                    <p class="text-xs text-[#8a7c68]">Every 2 direct vehicles sponsored increases your monthly company support by +10%.</p>
                </div>
                <span class="text-xs font-semibold px-3 py-1 rounded-full <?php echo $currentSupport >= 100 ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800'; ?> self-start sm:self-auto">
                    <?php echo $currentSupport >= 100 ? '🎉 100% Free Scooter Unlocked!' : ($directVehicles . '/10 Directs for 100% Free'); ?>
                </span>
            </div>
            <div class="mt-4 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2 text-center text-xs">
                <div class="p-3 rounded-xl border <?php echo ($directVehicles >= 0 && $directVehicles < 2) ? 'bg-[#fdf6ea] border-[#b47e1d] font-bold text-[#b47e1d] ring-2 ring-[#b47e1d]/30' : ($directVehicles >= 2 ? 'bg-green-50 border-green-200 text-green-800' : 'bg-gray-50 text-gray-500'); ?>">
                    <div class="text-[11px]">0-1 Directs</div>
                    <div class="text-sm font-bold mt-0.5">50% Support</div>
                </div>
                <div class="p-3 rounded-xl border <?php echo ($directVehicles >= 2 && $directVehicles < 4) ? 'bg-[#fdf6ea] border-[#b47e1d] font-bold text-[#b47e1d] ring-2 ring-[#b47e1d]/30' : ($directVehicles >= 4 ? 'bg-green-50 border-green-200 text-green-800' : 'bg-gray-50 text-gray-500'); ?>">
                    <div class="text-[11px]">2 Directs</div>
                    <div class="text-sm font-bold mt-0.5">60% Support</div>
                </div>
                <div class="p-3 rounded-xl border <?php echo ($directVehicles >= 4 && $directVehicles < 6) ? 'bg-[#fdf6ea] border-[#b47e1d] font-bold text-[#b47e1d] ring-2 ring-[#b47e1d]/30' : ($directVehicles >= 6 ? 'bg-green-50 border-green-200 text-green-800' : 'bg-gray-50 text-gray-500'); ?>">
                    <div class="text-[11px]">4 Directs</div>
                    <div class="text-sm font-bold mt-0.5">70% Support</div>
                </div>
                <div class="p-3 rounded-xl border <?php echo ($directVehicles >= 6 && $directVehicles < 8) ? 'bg-[#fdf6ea] border-[#b47e1d] font-bold text-[#b47e1d] ring-2 ring-[#b47e1d]/30' : ($directVehicles >= 8 ? 'bg-green-50 border-green-200 text-green-800' : 'bg-gray-50 text-gray-500'); ?>">
                    <div class="text-[11px]">6 Directs</div>
                    <div class="text-sm font-bold mt-0.5">80% Support</div>
                </div>
                <div class="p-3 rounded-xl border <?php echo ($directVehicles >= 8 && $directVehicles < 10) ? 'bg-[#fdf6ea] border-[#b47e1d] font-bold text-[#b47e1d] ring-2 ring-[#b47e1d]/30' : ($directVehicles >= 10 ? 'bg-green-50 border-green-200 text-green-800' : 'bg-gray-50 text-gray-500'); ?>">
                    <div class="text-[11px]">8 Directs</div>
                    <div class="text-sm font-bold mt-0.5">90% Support</div>
                </div>
                <div class="p-3 rounded-xl border <?php echo $directVehicles >= 10 ? 'bg-green-100 border-green-500 font-bold text-green-800 ring-2 ring-green-500/30' : 'bg-gray-50 text-gray-500'; ?>">
                    <div class="text-[11px]">10 Directs</div>
                    <div class="text-sm font-bold mt-0.5">100% FREE</div>
                </div>
            </div>
        </div>

        <?php if ($promo): ?>
            <!-- Digital Agreement Summary Card -->
            <div class="mt-4 bg-white rounded-2xl border border-[#e2d8c8] p-5 shadow-sm">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <div>
                        <h3 class="text-sm font-bold text-[#40352a]">Digital Vehicle Brand Promotion Agreement</h3>
                        <p class="text-[12px] text-[#8a7c68]">Official contractual agreement for 25-36 months brand promotion support.</p>
                    </div>
                    <div>
                        <?php if ((int)$promo['agreement_signed'] === 1): ?>
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold bg-green-100 text-green-800">
                                <i data-lucide="check-circle" class="w-3.5 h-3.5"></i> Digitally Signed (<?php echo date('d M Y', strtotime($promo['agreement_signed_at'])); ?>)
                            </span>
                        <?php else: ?>
                            <a href="scooter-verification.php" class="inline-flex items-center gap-1 px-3 py-1.5 bg-amber-100 text-amber-800 text-xs font-semibold rounded-lg hover:bg-amber-200 transition">
                                <i data-lucide="pen-tool" class="w-3.5 h-3.5"></i> Pending Digital Signature &rarr;
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="mt-4 p-3.5 rounded-xl bg-[#faf6ee] border border-[#e8decb] text-xs grid grid-cols-2 sm:grid-cols-4 gap-3 text-[#5f5140]">
                    <div>
                        <span class="block text-[#9e8f7e] text-[10px] uppercase font-semibold">Agreement Ref</span>
                        <span class="font-bold text-[#40352a]">VPA-<?php echo str_pad((string)$promo['id'], 5, '0', STR_PAD_LEFT); ?></span>
                    </div>
                    <div>
                        <span class="block text-[#9e8f7e] text-[10px] uppercase font-semibold">Monthly Base BPR</span>
                        <span class="font-bold text-[#b47e1d]"><?php echo m($promo['monthly_bpr_amount']); ?> / mo</span>
                    </div>
                    <div>
                        <span class="block text-[#9e8f7e] text-[10px] uppercase font-semibold">Current Support</span>
                        <span class="font-bold text-[#1e7a48]"><?php echo number_format($currentSupport, 0); ?>% (<?php echo m($promo['monthly_bpr_amount'] * $currentSupport / 100); ?>)</span>
                    </div>
                    <div>
                        <span class="block text-[#9e8f7e] text-[10px] uppercase font-semibold">Registered Vehicle</span>
                        <span class="font-bold text-[#40352a] truncate block"><?php echo htmlspecialchars($promo['vehicle_model']); ?> (<?php echo htmlspecialchars($promo['registration_number']); ?>)</span>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- SECTION 1: Personal Monthly BPR Support Payouts History -->
        <div class="mt-6 bg-white rounded-2xl border border-[#e2d8c8] overflow-hidden shadow-sm">
            <div class="p-5 border-b border-[#f0e9db] flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                <div>
                    <h3 class="text-[15px] font-bold text-[#40352a] uppercase tracking-wide">Personal Monthly Brand Promotion Reward (BPR) Payouts</h3>
                    <p class="text-xs text-[#8a7c68] mt-0.5">Monthly company support payments disbursed upon monthly video and GPS verification.</p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <span class="px-3 py-1 rounded-full text-xs font-bold bg-green-50 text-green-700 border border-green-200">
                        Total Paid: <?php echo m($totBprEarned); ?>
                    </span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-[13px]">
                    <thead class="bg-[#faf5ec] text-[#9e8f7e] text-[11px] uppercase tracking-wider">
                        <tr>
                            <th class="text-left px-4 py-3">Month #</th>
                            <th class="text-left px-4 py-3">Billing Period</th>
                            <th class="text-right px-4 py-3">Base BPR</th>
                            <th class="text-center px-4 py-3">Support %</th>
                            <th class="text-right px-4 py-3">Company Paid</th>
                            <th class="text-right px-4 py-3">Member Share</th>
                            <th class="text-center px-4 py-3">Status</th>
                            <th class="text-left px-4 py-3">Disbursed At</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#f0e9db]">
                        <?php if (empty($monthlyPayouts)): ?>
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center text-[#8a7c68]">
                                    <div class="max-w-md mx-auto space-y-2">
                                        <p class="text-sm font-medium text-[#40352a]">No monthly BPR payouts recorded yet.</p>
                                        <p class="text-xs">Once your monthly vehicle video and GPS verification are approved by Admin, your monthly BPR company support will be credited and reflected here.</p>
                                        <div class="pt-2">
                                            <a href="scooter-verification.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-[#b47e1d] hover:bg-[#966513] text-white text-xs font-semibold transition">
                                                <i data-lucide="upload" class="w-3.5 h-3.5"></i> Submit Monthly Video &rarr;
                                            </a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php else: foreach ($monthlyPayouts as $p): ?>
                            <tr class="data-row">
                                <td class="px-4 py-3 font-semibold text-[#40352a]">Month <?php echo (int)$p['month_number']; ?></td>
                                <td class="px-4 py-3 text-xs text-[#8a7c68] font-mono"><?php echo htmlspecialchars($p['period_key'] ?: '—'); ?></td>
                                <td class="px-4 py-3 text-right text-xs font-medium"><?php echo m($p['monthly_bpr_amount']); ?></td>
                                <td class="px-4 py-3 text-center text-xs font-bold text-[#b47e1d]"><?php echo number_format((float)$p['support_percent'], 0); ?>%</td>
                                <td class="px-4 py-3 text-right font-bold text-[#1e7a48]"><?php echo m($p['company_support_amount']); ?></td>
                                <td class="px-4 py-3 text-right text-xs text-[#8a7c68]"><?php echo m($p['member_share_amount']); ?></td>
                                <td class="px-4 py-3 text-center">
                                    <?php if ($p['status'] === 'paid'): ?>
                                        <span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-green-100 text-green-800">Paid</span>
                                    <?php elseif ($p['status'] === 'approved'): ?>
                                        <span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-blue-100 text-blue-800">Approved</span>
                                    <?php elseif ($p['status'] === 'rejected'): ?>
                                        <span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-red-100 text-red-800">Rejected</span>
                                    <?php else: ?>
                                        <span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-amber-100 text-amber-800">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-xs text-[#8a7c68]">
                                    <?php echo !empty($p['paid_at']) ? htmlspecialchars(substr($p['paid_at'], 0, 16)) : (!empty($p['created_at']) ? htmlspecialchars(substr($p['created_at'], 0, 16)) : '—'); ?>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- SECTION 2: Downline Team Promotion Bonus (10-Level Affiliate Commission) -->
        <div class="mt-6 bg-white rounded-2xl border border-[#e2d8c8] overflow-hidden shadow-sm">
            <div class="p-5 border-b border-[#f0e9db]">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <div>
                        <h3 class="text-[15px] font-bold text-[#40352a] uppercase tracking-wide">Compensation Business Plan of Affiliate Marketing</h3>
                        <p class="text-xs text-[#8a7c68] mt-0.5">10-Level Team Promotion Bonus Commission (BPR Reward for First 6 Months)</p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <span class="px-3 py-1 rounded-full text-xs font-bold bg-[#faf3e7] text-[#b47e1d] border border-[#ecd9b8]">
                            <?php echo $myDirectsCount; ?> Direct Sponsor<?php echo $myDirectsCount === 1 ? '' : 's'; ?> (Unlocks Root <?php echo min(10, $myDirectsCount); ?>)
                        </span>
                        <span class="px-3 py-1 rounded-full text-xs font-bold bg-green-50 text-green-700 border border-green-200">
                            Total: <?php echo m($totTeamBonus); ?>
                        </span>
                    </div>
                </div>

                <div class="mt-3 text-[11px] text-[#8a7c68] bg-[#fbf8f2] rounded-xl p-3 border border-[#f0e6d5] space-y-0.5">
                    <p><span class="font-bold text-[#b47e1d]">NOTE:</span> Root rewards percentage (%) is of brand promotion bonus reward for the first 6 months only.</p>
                    <p><span class="font-bold text-[#b47e1d]">NOTE:</span> Each root will be opened according to your direct sponsor.</p>
                </div>

                <!-- 10 Root Qualification Cards -->
                <div class="mt-4 grid grid-cols-2 sm:grid-cols-5 lg:grid-cols-10 gap-2">
                    <?php for ($r = 1; $r <= 10; $r++): 
                        $isOpen = ($myDirectsCount >= $r);
                    ?>
                        <div class="rounded-xl border <?php echo $isOpen ? 'border-[#b47e1d] bg-[#fdfaf5]' : 'border-gray-200 bg-gray-50/70 opacity-60'; ?> p-2.5 text-center transition">
                            <p class="text-[9px] uppercase font-bold <?php echo $isOpen ? 'text-[#b47e1d]' : 'text-gray-400'; ?>">
                                Root <?php echo $r === 1 ? '01/Direct' : str_pad((string)$r, 2, '0', STR_PAD_LEFT); ?>
                            </p>
                            <p class="text-[13px] font-extrabold text-[#102B4E] mt-0.5"><?php echo $teamBprRates[$r]; ?>%</p>
                            <p class="mt-1 text-[11px] font-bold text-[#40352a]"><?php echo m($teamBprTotals[$r]['earned']); ?></p>
                            <span class="inline-block mt-1 text-[9px] font-semibold px-1.5 py-0.5 rounded <?php echo $isOpen ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-600'; ?>">
                                <?php echo $isOpen ? 'Open' : ('Req ' . $r . ' Dir'); ?>
                            </span>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>

            <div class="px-5 py-3 border-b border-[#f0e9db] bg-[#faf5ec] flex items-center justify-between">
                <span class="text-[13px] font-semibold text-[#40352a]">Team Promotion Bonus Payout History</span>
                <span class="text-xs text-[#8a7c68]"><?php echo count($teamBonuses); ?> Bonus Record<?php echo count($teamBonuses) === 1 ? '' : 's'; ?></span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-[13px]">
                    <thead class="bg-[#faf5ec] text-[#9e8f7e] text-[11px] uppercase tracking-wider">
                        <tr>
                            <th class="text-left px-4 py-3">Date</th>
                            <th class="text-left px-4 py-3">Downline Member</th>
                            <th class="text-center px-4 py-3">Root Level</th>
                            <th class="text-center px-4 py-3">Month (1 to 6)</th>
                            <th class="text-right px-4 py-3">Commission %</th>
                            <th class="text-right px-4 py-3">Bonus Earned</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#f0e9db]">
                        <?php if (empty($teamBonuses)): ?>
                            <tr>
                                <td colspan="6" class="px-4 py-6 text-center text-[#8a7c68]">
                                    No team vehicle bonuses received yet. When downline members acquire vehicles, you receive monthly BPR bonus up to 10 roots for 6 months.
                                </td>
                            </tr>
                        <?php else: foreach ($teamBonuses as $tb): ?>
                            <tr class="data-row">
                                <td class="px-4 py-3 text-xs text-[#8a7c68]"><?php echo htmlspecialchars(substr($tb['created_at'], 0, 16)); ?></td>
                                <td class="px-4 py-3 font-medium text-[#40352a]"><?php echo htmlspecialchars($tb['downline_name'] ?: $tb['downline_member_id']); ?></td>
                                <td class="px-4 py-3 text-center font-bold text-[#b47e1d]">Root <?php echo (int)$tb['root_level']; ?></td>
                                <td class="px-4 py-3 text-center text-xs">Month <?php echo (int)$tb['month_index']; ?>/6</td>
                                <td class="px-4 py-3 text-right font-medium"><?php echo number_format($tb['percent'], 1); ?>%</td>
                                <td class="px-4 py-3 text-right font-bold text-[#1e7a48]"><?php echo m($tb['bonus_amount']); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- SECTION 3: Recent Monthly Verification Proofs Snapshot -->
        <?php if (!empty($recentVerifications)): ?>
            <div class="mt-6 bg-white rounded-2xl border border-[#e2d8c8] overflow-hidden shadow-sm">
                <div class="px-5 py-3.5 border-b border-[#f0e9db] flex items-center justify-between">
                    <div>
                        <h4 class="text-[14px] font-semibold text-[#40352a]">Recent Verification Submissions</h4>
                        <p class="text-[11px] text-[#8a7c68]">Track your submitted videos and kilometre targets</p>
                    </div>
                    <a href="scooter-verification.php" class="text-xs font-semibold text-[#b47e1d] hover:underline flex items-center gap-1">
                        <span>Go to Full Verification Page</span>
                        <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                    </a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-[13px]">
                        <thead class="bg-[#faf5ec] text-[#9e8f7e] text-[11px] uppercase tracking-wider">
                            <tr>
                                <th class="text-left px-4 py-2.5">Month</th>
                                <th class="text-left px-4 py-2.5">KM Reported</th>
                                <th class="text-center px-4 py-2.5">Status</th>
                                <th class="text-right px-4 py-2.5">Submitted At</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#f0e9db]">
                            <?php foreach ($recentVerifications as $rv): ?>
                                <tr class="data-row">
                                    <td class="px-4 py-2.5 font-semibold text-[#40352a]">Month <?php echo (int)$rv['month_number']; ?></td>
                                    <td class="px-4 py-2.5 text-xs font-bold text-[#b47e1d]"><?php echo number_format((int)$rv['km_run']); ?> KM</td>
                                    <td class="px-4 py-2.5 text-center">
                                        <?php if ($rv['status'] === 'verified'): ?>
                                            <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-green-100 text-green-800">Approved</span>
                                        <?php elseif ($rv['status'] === 'rejected'): ?>
                                            <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-red-100 text-red-800">Rejected</span>
                                        <?php else: ?>
                                            <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-100 text-amber-800">Under Review</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-2.5 text-right text-xs text-[#8a7c68]"><?php echo htmlspecialchars(substr($rv['created_at'], 0, 16)); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

    </div>
</div>

<script>
lucide.createIcons();
</script>
</body>
</html>
