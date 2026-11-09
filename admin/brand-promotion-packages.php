<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';
if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.");
}
$conn->set_charset("utf8mb4");

require_once __DIR__ . '/../includes/income_common.php';
require_once __DIR__ . '/../includes/bp_package_functions.php';

$adminName = $_SESSION['admin_username'] ?? ($_SESSION['full_name'] ?? 'Admin');
$actionMsg = '';
$actionErr = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');
    $subId  = (int)($_POST['subscription_id'] ?? 0);

    if ($action === 'approve' && $subId > 0) {
        $res = bp_approve_subscription($conn, $subId, $adminName);
        if ($res['ok']) {
            $actionMsg = $res['message'];
        } else {
            $actionErr = $res['message'];
        }
    } elseif ($action === 'reject' && $subId > 0) {
        $reason = trim($_POST['reject_reason'] ?? 'Invalid payment reference / slip.');
        $res = bp_reject_subscription($conn, $subId, $adminName, $reason);
        if ($res['ok']) {
            $actionMsg = $res['message'];
        } else {
            $actionErr = $res['message'];
        }
    } elseif ($action === 'run_monthly_cron') {
        $res = bp_process_monthly_payouts($conn, date('Y-m'), true);
        $actionMsg = "Monthly payout run complete! Subscribers paid: {$res['subscribers_paid']} (₹" . number_format($res['total_subscriber_payout'], 2) . "), 10-Root Affiliates paid: {$res['affiliates_paid']} (₹" . number_format($res['total_affiliate_payout'], 2) . ").";
    }
}

// Filter
$statusFilter = trim($_GET['status'] ?? '');
$where = "1=1";
if ($statusFilter !== '' && in_array($statusFilter, ['pending', 'active', 'completed', 'rejected'], true)) {
    $where = "s.status = '" . $conn->real_escape_string($statusFilter) . "'";
}

// Fetch Subscriptions
$subs = [];
$res = $conn->query("SELECT s.*, su.full_name as member_name, su.mobile, sp.full_name as sponsor_name
                     FROM ind_bp_subscriptions s
                     LEFT JOIN signup_users su ON su.customer_id = s.customer_id
                     LEFT JOIN signup_users sp ON sp.customer_id = s.direct_sponsor_id
                     WHERE {$where}
                     ORDER BY s.id DESC");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $subs[] = $r;
    }
}

// Metric Aggregates
$mRes = $conn->query("SELECT 
    COUNT(*) as total_count,
    SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) as active_count,
    SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending_count,
    SUM(CASE WHEN status IN ('active','completed') THEN package_amount ELSE 0 END) as total_volume,
    SUM(total_paid) as total_paid_out,
    SUM(CASE WHEN direct_bonus_status='paid' THEN direct_bonus_amount ELSE 0 END) as total_direct_bonus
FROM ind_bp_subscriptions")->fetch_assoc();

$totalCount    = (int)($mRes['total_count'] ?? 0);
$activeCount   = (int)($mRes['active_count'] ?? 0);
$pendingCount  = (int)($mRes['pending_count'] ?? 0);
$totalVolume   = (float)($mRes['total_volume'] ?? 0.0);
$totalPaidOut  = (float)($mRes['total_paid_out'] ?? 0.0);
$totalDirBonus = (float)($mRes['total_direct_bonus'] ?? 0.0);

// Total 10-Root Affiliate Commission Paid
$rootPaid = (float)($conn->query("SELECT SUM(commission_amount) as s FROM ind_bp_root_payouts WHERE status='paid'")->fetch_assoc()['s'] ?? 0.0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Brand Promotion Packages Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .flash-msg { animation: fadeIn .2s ease-in; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-4px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body class="bg-gray-100 text-slate-800">

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="ml-64 min-h-screen">
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <div class="px-5 py-5">

        <!-- Top Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-gray-200 pb-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Brand Promotion Packages</h1>
                <p class="text-xs text-gray-500 mt-0.5">Manage 10-month package subscriptions, approve bank payments &amp; trigger monthly returns.</p>
            </div>
            <div class="flex items-center gap-2">
                <form method="POST" onsubmit="return confirm('Trigger monthly returns & 10-Root affiliate payouts for all active subscriptions now?');">
                    <input type="hidden" name="action" value="run_monthly_cron">
                    <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 bg-[#103258] hover:bg-[#1A3F66] text-white rounded-xl text-xs font-semibold shadow-sm transition">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                        Run Monthly Payouts Now
                    </button>
                </form>
            </div>
        </div>

        <!-- Flash Notices -->
        <?php if ($actionMsg): ?>
            <div class="flash-msg mt-4 p-3.5 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-xs font-semibold">
                <?php echo htmlspecialchars($actionMsg); ?>
            </div>
        <?php endif; ?>
        <?php if ($actionErr): ?>
            <div class="flash-msg mt-4 p-3.5 bg-red-50 border border-red-200 text-red-800 rounded-xl text-xs font-semibold">
                <?php echo htmlspecialchars($actionErr); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3 mt-5">
            <div class="bg-white rounded-xl border border-gray-200 p-3.5 shadow-sm">
                <p class="text-[10px] uppercase font-bold text-gray-400">Total Sales Volume</p>
                <p class="text-lg font-black text-[#103258] mt-1">₹<?php echo number_format($totalVolume, 2); ?></p>
                <p class="text-[10px] text-gray-500 mt-0.5"><?php echo $totalCount; ?> Subscriptions</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-3.5 shadow-sm">
                <p class="text-[10px] uppercase font-bold text-gray-400">Active Plans</p>
                <p class="text-lg font-black text-emerald-600 mt-1"><?php echo $activeCount; ?></p>
                <p class="text-[10px] text-emerald-600 mt-0.5">Earning 10%/mo</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-3.5 shadow-sm">
                <p class="text-[10px] uppercase font-bold text-gray-400">Pending Approvals</p>
                <p class="text-lg font-black text-amber-600 mt-1"><?php echo $pendingCount; ?></p>
                <p class="text-[10px] text-amber-600 mt-0.5">Bank slip verification</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-3.5 shadow-sm">
                <p class="text-[10px] uppercase font-bold text-gray-400">Returns Paid Out</p>
                <p class="text-lg font-black text-blue-600 mt-1">₹<?php echo number_format($totalPaidOut, 2); ?></p>
                <p class="text-[10px] text-gray-500 mt-0.5">To Subscribers</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-3.5 shadow-sm">
                <p class="text-[10px] uppercase font-bold text-gray-400">5% Direct Bonus</p>
                <p class="text-lg font-black text-purple-600 mt-1">₹<?php echo number_format($totalDirBonus, 2); ?></p>
                <p class="text-[10px] text-gray-500 mt-0.5">To Sponsors</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-3.5 shadow-sm">
                <p class="text-[10px] uppercase font-bold text-gray-400">10-Root Affiliate</p>
                <p class="text-lg font-black text-[#DDBE7E] mt-1">₹<?php echo number_format($rootPaid, 2); ?></p>
                <p class="text-[10px] text-gray-500 mt-0.5">51% Team Pool</p>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="mt-6 flex flex-wrap items-center justify-between gap-3 bg-white p-3 rounded-xl border border-gray-200">
            <div class="flex items-center gap-2">
                <span class="text-xs font-bold text-gray-500">Filter Status:</span>
                <a href="brand-promotion-packages.php" class="px-2.5 py-1 rounded-lg text-xs font-semibold <?php echo $statusFilter === '' ? 'bg-[#103258] text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'; ?>">All (<?php echo $totalCount; ?>)</a>
                <a href="brand-promotion-packages.php?status=pending" class="px-2.5 py-1 rounded-lg text-xs font-semibold <?php echo $statusFilter === 'pending' ? 'bg-amber-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'; ?>">Pending (<?php echo $pendingCount; ?>)</a>
                <a href="brand-promotion-packages.php?status=active" class="px-2.5 py-1 rounded-lg text-xs font-semibold <?php echo $statusFilter === 'active' ? 'bg-emerald-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'; ?>">Active (<?php echo $activeCount; ?>)</a>
                <a href="brand-promotion-packages.php?status=completed" class="px-2.5 py-1 rounded-lg text-xs font-semibold <?php echo $statusFilter === 'completed' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'; ?>">Completed</a>
            </div>
            <span class="text-xs text-gray-400 font-medium"><?php echo count($subs); ?> record(s) found</span>
        </div>

        <!-- Subscriptions Table -->
        <div class="mt-4 bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
            <?php if (empty($subs)): ?>
                <div class="py-12 text-center text-gray-400 text-xs">
                    No subscriptions found matching this filter.
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-gray-50 text-gray-500 uppercase tracking-wider font-semibold border-b border-gray-200">
                            <tr>
                                <th class="py-3 px-3">Subscription</th>
                                <th class="py-3 px-3">Member</th>
                                <th class="py-3 px-3">Package / Amount</th>
                                <th class="py-3 px-3">Monthly Return</th>
                                <th class="py-3 px-3">10-Mo Progress</th>
                                <th class="py-3 px-3">Direct Sponsor</th>
                                <th class="py-3 px-3">Payment</th>
                                <th class="py-3 px-3">Status</th>
                                <th class="py-3 px-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach ($subs as $s): 
                                $pct = min(100, round(($s['months_paid'] / $s['duration_months']) * 100));
                                $statusBadge = match($s['status']) {
                                    'active' => 'bg-emerald-100 text-emerald-800',
                                    'completed' => 'bg-blue-100 text-blue-800',
                                    'pending' => 'bg-amber-100 text-amber-800',
                                    'rejected' => 'bg-red-100 text-red-800',
                                    default => 'bg-gray-100 text-gray-800'
                                };
                            ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="py-3 px-3">
                                        <span class="font-mono font-bold text-gray-800"><?php echo htmlspecialchars($s['subscription_code']); ?></span>
                                        <span class="block text-[10px] text-gray-400"><?php echo date('d M Y', strtotime($s['created_at'])); ?></span>
                                    </td>
                                    <td class="py-3 px-3">
                                        <span class="font-bold text-[#103258]"><?php echo htmlspecialchars($s['member_name'] ?: $s['customer_id']); ?></span>
                                        <span class="block text-[10px] text-gray-400 font-mono"><?php echo htmlspecialchars($s['customer_id']); ?></span>
                                    </td>
                                    <td class="py-3 px-3">
                                        <span class="font-semibold text-gray-900"><?php echo htmlspecialchars($s['package_name']); ?></span>
                                        <span class="block font-bold text-gray-800">₹<?php echo number_format($s['package_amount'], 2); ?></span>
                                    </td>
                                    <td class="py-3 px-3">
                                        <span class="font-bold text-emerald-600">₹<?php echo number_format($s['monthly_payout'], 2); ?> / mo</span>
                                        <span class="block text-[10px] text-gray-400">Next: <?php echo $s['next_payout_date'] ? date('d M Y', strtotime($s['next_payout_date'])) : '—'; ?></span>
                                    </td>
                                    <td class="py-3 px-3">
                                        <div class="w-28">
                                            <div class="flex justify-between text-[10px] text-gray-500 mb-0.5">
                                                <span>Month <?php echo (int)$s['months_paid']; ?>/<?php echo (int)$s['duration_months']; ?></span>
                                                <span><?php echo $pct; ?>%</span>
                                            </div>
                                            <div class="w-full bg-gray-200 h-1.5 rounded-full overflow-hidden">
                                                <div class="bg-emerald-500 h-full rounded-full" style="width: <?php echo $pct; ?>%"></div>
                                            </div>
                                            <span class="text-[10px] text-gray-400">Paid: ₹<?php echo number_format($s['total_paid'], 0); ?></span>
                                        </div>
                                    </td>
                                    <td class="py-3 px-3">
                                        <span class="font-medium text-gray-700"><?php echo htmlspecialchars($s['sponsor_name'] ?: ($s['direct_sponsor_id'] ?: 'None')); ?></span>
                                        <span class="block text-[10px] <?php echo $s['direct_bonus_status'] === 'paid' ? 'text-emerald-600 font-semibold' : 'text-gray-400'; ?>">
                                            5% Bonus (₹<?php echo number_format($s['direct_bonus_amount'], 0); ?>): <?php echo ucfirst($s['direct_bonus_status']); ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-3">
                                        <span class="inline-block px-1.5 py-0.5 bg-gray-100 rounded text-[10px] font-semibold text-gray-700"><?php echo htmlspecialchars($s['payment_mode']); ?></span>
                                        <?php if (!empty($s['transaction_id'])): ?>
                                            <span class="block text-[10px] text-gray-500 font-mono mt-0.5">UTR: <?php echo htmlspecialchars($s['transaction_id']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($s['payment_slip'])): ?>
                                            <a href="../<?php echo htmlspecialchars($s['payment_slip']); ?>" target="_blank" class="text-[10px] text-blue-600 hover:underline block mt-0.5">View Receipt</a>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-3">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider <?php echo $statusBadge; ?>">
                                            <?php echo ucfirst($s['status']); ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-3 text-right">
                                        <?php if ($s['status'] === 'pending'): ?>
                                            <div class="flex items-center justify-end gap-1">
                                                <form method="POST" onsubmit="return confirm('Approve this subscription and activate 10-month return?');">
                                                    <input type="hidden" name="action" value="approve">
                                                    <input type="hidden" name="subscription_id" value="<?php echo (int)$s['id']; ?>">
                                                    <button type="submit" class="px-2 py-1 bg-emerald-600 hover:bg-emerald-700 text-white text-[11px] font-bold rounded">Approve</button>
                                                </form>
                                                <form method="POST" onsubmit="return confirm('Reject this subscription request?');">
                                                    <input type="hidden" name="action" value="reject">
                                                    <input type="hidden" name="subscription_id" value="<?php echo (int)$s['id']; ?>">
                                                    <button type="submit" class="px-2 py-1 bg-red-600 hover:bg-red-700 text-white text-[11px] font-bold rounded">Reject</button>
                                                </form>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-gray-400 text-[11px]">—</span>
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

</body>
</html>

