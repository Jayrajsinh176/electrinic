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

// Fetch all monthly return payouts for this customer
$payouts = [];
$totalEarned = 0.00;

$stmt = $conn->prepare("SELECT p.*, s.package_name, s.subscription_code, s.duration_months
                        FROM ind_bp_monthly_payouts p
                        JOIN ind_bp_subscriptions s ON s.id = p.subscription_id
                        WHERE p.customer_id = ?
                        ORDER BY p.id DESC");
if ($stmt) {
    $stmt->bind_param('s', $customerId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $payouts[] = $r;
        $totalEarned += (float)$r['payout_amount'];
    }
    $stmt->close();
}

// Next upcoming payout
$nextPayout = null;
$nextStmt = $conn->prepare("SELECT next_payout_date, package_name, monthly_payout FROM ind_bp_subscriptions 
                            WHERE customer_id = ? AND status = 'active' AND next_payout_date IS NOT NULL
                            ORDER BY next_payout_date ASC LIMIT 1");
if ($nextStmt) {
    $nextStmt->bind_param('s', $customerId);
    $nextStmt->execute();
    $nextPayout = $nextStmt->get_result()->fetch_assoc();
    $nextStmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Brand Promotion Return Income</title>
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
                <i data-lucide="hand-coins" class="w-5 h-5 text-[#cd9737]"></i>
            </div>
            <div>
                <h1 class="text-xl sm:text-2xl font-bold text-[#103258]">Brand Promotion Return Income</h1>
                <p class="text-xs text-slate-500">10% monthly promotional bonus paid across 10 months on active packages.</p>
            </div>
        </div>

        <!-- Metric Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-5">
            <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm">
                <p class="text-[11px] uppercase font-bold text-slate-400">Total Returns Received</p>
                <p class="text-2xl font-extrabold text-emerald-600 mt-1">₹<?php echo number_format($totalEarned, 2); ?></p>
                <p class="text-xs text-slate-500 mt-1"><?php echo count($payouts); ?> Installment(s) credited</p>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm">
                <p class="text-[11px] uppercase font-bold text-slate-400">Next Upcoming Payout</p>
                <?php if ($nextPayout && $nextPayout['next_payout_date']): ?>
                    <p class="text-xl font-bold text-[#103258] mt-1"><?php echo date('d M Y', strtotime($nextPayout['next_payout_date'])); ?></p>
                    <p class="text-xs text-emerald-600 font-medium mt-1">₹<?php echo number_format($nextPayout['monthly_payout'], 2); ?> (<?php echo htmlspecialchars($nextPayout['package_name']); ?>)</p>
                <?php else: ?>
                    <p class="text-lg font-bold text-slate-400 mt-1">No Active Queue</p>
                    <p class="text-xs text-slate-400 mt-1">Subscribe to a package to start</p>
                <?php endif; ?>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm flex flex-col justify-between">
                <div>
                    <p class="text-[11px] uppercase font-bold text-slate-400">Want to earn more?</p>
                    <p class="text-xs text-slate-600 mt-1">Subscribe to higher tier packages for up to ₹20,000 / month.</p>
                </div>
                <a href="brand-promotion-packages.php" class="inline-flex items-center gap-1.5 text-xs font-bold text-[#103258] hover:text-[#cd9737] mt-2">
                    <span>View Packages</span>
                    <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                </a>
            </div>
        </div>

        <!-- Payout History Table -->
        <div class="mt-6 bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
            <h2 class="text-base font-bold text-[#103258] mb-4 flex items-center gap-2">
                <i data-lucide="list" class="w-4 h-4 text-[#DDBE7E]"></i>
                <span>Monthly Return Ledger</span>
            </h2>

            <?php if (empty($payouts)): ?>
                <div class="py-12 text-center text-slate-400 text-xs">
                    No monthly return payouts recorded yet.
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-slate-50 text-slate-500 uppercase tracking-wider font-semibold border-b border-slate-200">
                            <tr>
                                <th class="py-3 px-3">Date</th>
                                <th class="py-3 px-3">Subscription</th>
                                <th class="py-3 px-3">Package</th>
                                <th class="py-3 px-3">Month #</th>
                                <th class="py-3 px-3">Gross Amount</th>
                                <th class="py-3 px-3">Wallet Credit</th>
                                <th class="py-3 px-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($payouts as $p): ?>
                                <tr class="data-row">
                                    <td class="py-3 px-3 text-slate-600"><?php echo date('d M Y, h:i A', strtotime($p['paid_at'])); ?></td>
                                    <td class="py-3 px-3 font-mono font-medium text-slate-700"><?php echo htmlspecialchars($p['subscription_code']); ?></td>
                                    <td class="py-3 px-3 font-semibold text-[#103258]"><?php echo htmlspecialchars($p['package_name']); ?></td>
                                    <td class="py-3 px-3">
                                        <span class="px-2 py-0.5 bg-slate-100 rounded text-[11px] font-bold text-slate-700">
                                            Month <?php echo (int)$p['month_number']; ?> / <?php echo (int)$p['duration_months']; ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-3 font-bold text-slate-900">₹<?php echo number_format($p['payout_amount'], 2); ?></td>
                                    <td class="py-3 px-3 text-emerald-600 font-semibold">+ ₹<?php echo number_format($p['payout_amount'], 2); ?></td>
                                    <td class="py-3 px-3">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-100 text-emerald-800">
                                            <?php echo htmlspecialchars($p['status']); ?>
                                        </span>
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

