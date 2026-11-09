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

$isInd = income_setting_bool($conn, 'ind_plan_enabled', true);
$hasPool = income_table_exists($conn, 'ind_auto_pool_positions');
$cfg = auto_pool_config($conn);

$indPositions = [];
$indStates = [];
$totalIndIncome = 0.0;

if ($isInd && $hasPool) {
    // Fetch 100 pool states
    $stRes = $conn->query("SELECT pool_level, entry_amount, retopup_amount, upgrade_amount, withdrawal_gross, entries_count, completed_count FROM ind_auto_pool_state ORDER BY pool_level ASC");
    while ($stRow = $stRes->fetch_assoc()) {
        $indStates[(int)$stRow['pool_level']] = $stRow;
    }

    // Fetch user's positions
    $posRes = $conn->prepare("SELECT id, pool_level, queue_no, entry_amount, entry_source, child_count, status, joined_at, completed_at 
                             FROM ind_auto_pool_positions WHERE customer_id = ? ORDER BY pool_level ASC, id ASC");
    $posRes->bind_param("s", $customerId);
    $posRes->execute();
    $pResult = $posRes->get_result();
    while ($pRow = $pResult->fetch_assoc()) {
        $indPositions[] = $pRow;
    }
    $posRes->close();

    // Fetch total income from ind_auto_pool_ledger
    if (income_table_exists($conn, 'ind_auto_pool_ledger')) {
        $lgrStmt = $conn->prepare("SELECT SUM(gross_income) as tot FROM ind_auto_pool_ledger WHERE customer_id = ?");
        $lgrStmt->bind_param("s", $customerId);
        $lgrStmt->execute();
        $totalIndIncome = (float)($lgrStmt->get_result()->fetch_assoc()['tot'] ?? 0.0);
        $lgrStmt->close();
    }
}

$ladder = $hasPool && !$isInd ? auto_pool_status($conn) : [];

$myPos = [];
if (!$isInd && income_table_exists($conn, 'auto_pool_positions')) {
    $stmt = $conn->prepare("SELECT pool_level, queue_no, entry_amount, entry_source, status, income_amount, repool_amount, joined_at, paid_at
                            FROM auto_pool_positions WHERE customer_id = ? ORDER BY pool_level");
    $stmt->bind_param("s", $customerId);
    $stmt->execute();
    $r = $stmt->get_result();
    while ($x = $r->fetch_assoc()) $myPos[(int)$x['pool_level']] = $x;
    $stmt->close();
}

$myIncome = $isInd ? $totalIndIncome : 0.0;
$myLedger = [];
if (income_table_exists($conn, 'member_income_ledger')) {
    $stmt = $conn->prepare("SELECT report_date, source, base_amount, percentage, gross_amount, paid_amount, fund_amount, withdrawable_amount, status, created_at
                            FROM member_income_ledger WHERE customer_id = ? AND income_type LIKE '%Auto Pool%' ORDER BY id DESC");
    $stmt->bind_param("s", $customerId);
    $stmt->execute();
    $r = $stmt->get_result();
    while ($x = $r->fetch_assoc()) { if (!$isInd) $myIncome += (float)$x['paid_amount']; $myLedger[] = $x; }
    $stmt->close();
}

// how far each of my waiting positions is from completing
function m($n) { return '₹' . number_format((float)$n, 2); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auto Pool Income</title>
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
                <i data-lucide="layers" class="w-[17px] h-[17px] text-[#cd9737]"></i>
            </div>
            <h2 class="whitespace-nowrap text-[20px] font-normal text-[#40352a]" style="font-family: Georgia, 'Times New Roman', serif;">AUTO POOL INCOME</h2>
            <div class="w-full h-[2px] bg-[#d1a14b]"></div>
        </div>

        <p class="mt-3 text-[12px] text-[#8a7c68]">
            <?php if ($isInd): ?>
                <strong>3x1 Auto-Pool System (100 Pools Progression):</strong> Each pool completes automatically when <strong>3 IDs</strong> enter.
                Collection is distributed 3 ways: 1) Same pool auto re-topup (continuous earning), 2) Next pool upgrade entry, 3) Member withdrawal income (flat 10% TDS+Admin deduction).
            <?php else: ?>
                On joining you automatically get one <strong>Pool 1</strong> entry &mdash; funded by the company, not from your &#8377;10,000.
                When <?php echo (int)$cfg['per_pool']; ?> entries fill a pool, its oldest member is paid <?php echo number_format($cfg['split'], 0); ?>% of the pool
                and advances to the next pool (the other <?php echo number_format(100 - $cfg['split'], 0); ?>% re-pools).
            <?php endif; ?>
        </p>

        <div class="mt-4 grid grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="bg-white rounded-2xl border border-[#e2d8c8] px-4 py-3"><p class="text-[10px] uppercase tracking-widest text-[#9e8f7e] font-semibold">Total Auto Pool Income</p><p class="mt-1 text-[18px] font-semibold"><?php echo m($myIncome); ?></p></div>
            <div class="bg-white rounded-2xl border border-[#e2d8c8] px-4 py-3"><p class="text-[10px] uppercase tracking-widest text-[#9e8f7e] font-semibold">Active Positions</p><p class="mt-1 text-[18px] font-semibold"><?php echo $isInd ? count(array_filter($indPositions, fn($p) => $p['status'] === 'waiting')) : count(array_filter($myPos, fn($p) => $p['status'] === 'waiting')); ?></p></div>
            <div class="bg-white rounded-2xl border border-[#e2d8c8] px-4 py-3"><p class="text-[10px] uppercase tracking-widest text-[#9e8f7e] font-semibold">Completed Cycles</p><p class="mt-1 text-[18px] font-semibold"><?php echo $isInd ? count(array_filter($indPositions, fn($p) => $p['status'] === 'completed')) : count(array_filter($myPos, fn($p) => $p['status'] === 'paid')); ?></p></div>
            <div class="bg-white rounded-2xl border border-[#e2d8c8] px-4 py-3"><p class="text-[10px] uppercase tracking-widest text-[#9e8f7e] font-semibold">Highest Pool Reached</p><p class="mt-1 text-[18px] font-semibold"><?php 
                $maxPool = 0; 
                if ($isInd) {
                    foreach ($indPositions as $p) { if ((int)$p['pool_level'] > $maxPool) $maxPool = (int)$p['pool_level']; }
                } else {
                    foreach ($myPos as $lvl => $p) { if ($lvl > $maxPool) $maxPool = $lvl; }
                }
                echo $maxPool ? 'Pool ' . $maxPool : '—'; 
            ?></p></div>
        </div>

        <?php if ($isInd): ?>
        <div class="mt-4 bg-white rounded-2xl border border-[#e2d8c8] overflow-hidden">
            <div class="px-5 py-3 border-b border-[#f0e9db] text-[14px] font-semibold text-[#40352a]">My 3x1 Auto-Pool Positions (100 Pools)</div>
            <div class="overflow-x-auto">
                <table class="w-full text-[13px]">
                    <thead class="bg-[#faf5ec] text-[#9e8f7e] text-[11px] uppercase tracking-wider">
                        <tr>
                            <th class="text-left px-4 py-3">Position ID</th>
                            <th class="text-left px-4 py-3">Pool Level</th>
                            <th class="text-right px-4 py-3">Entry Fee</th>
                            <th class="text-center px-4 py-3">Queue No</th>
                            <th class="text-center px-4 py-3">3x1 Progress</th>
                            <th class="text-left px-4 py-3">Source</th>
                            <th class="text-center px-4 py-3">Status</th>
                            <th class="text-left px-4 py-3">Joined Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#f0e9db]">
                        <?php if (empty($indPositions)): ?>
                            <tr><td colspan="8" class="px-4 py-6 text-center text-[#8a7c68]">No Auto-Pool positions yet. Positions are automatically added when product orders with add-on are placed.</td></tr>
                        <?php else: foreach ($indPositions as $pos): ?>
                            <tr class="data-row">
                                <td class="px-4 py-3 font-semibold text-[#40352a]">#<?php echo (int)$pos['id']; ?></td>
                                <td class="px-4 py-3 font-medium text-[#b47e1d]">Pool <?php echo (int)$pos['pool_level']; ?></td>
                                <td class="px-4 py-3 text-right font-medium"><?php echo m($pos['entry_amount']); ?></td>
                                <td class="px-4 py-3 text-center"><?php echo (int)$pos['queue_no']; ?></td>
                                <td class="px-4 py-3 text-center">
                                    <div class="inline-flex items-center gap-1.5">
                                        <div class="w-20 bg-[#efe9dc] rounded-full h-2 overflow-hidden">
                                            <div class="bg-[#1e7a48] h-2" style="width: <?php echo min(100, ((int)$pos['child_count'] / 3) * 100); ?>%"></div>
                                        </div>
                                        <span class="text-[11px] font-semibold text-[#40352a]"><?php echo (int)$pos['child_count']; ?>/3 IDs</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 capitalize text-[#8a7c68]"><?php echo htmlspecialchars(str_replace('_', ' ', (string)$pos['entry_source'])); ?></td>
                                <td class="px-4 py-3 text-center">
                                    <?php if ($pos['status'] === 'completed'): ?>
                                        <span class="inline-block px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-green-100 text-green-800">Completed &amp; Retopped</span>
                                    <?php else: ?>
                                        <span class="inline-block px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-amber-100 text-amber-800">In Queue</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-[#8a7c68] whitespace-nowrap"><?php echo htmlspecialchars(substr((string)$pos['joined_at'], 0, 16)); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <div class="mt-4 bg-white rounded-2xl border border-[#e2d8c8] overflow-hidden">
            <div class="px-5 py-3 border-b border-[#f0e9db] text-[14px] font-semibold text-[#40352a]">Pool Ladder &amp; My Position</div>
            <div class="overflow-x-auto">
                <table class="w-full text-[13px]">
                    <thead class="bg-[#faf5ec] text-[#9e8f7e] text-[11px] uppercase tracking-wider">
                        <tr>
                            <th class="text-left px-4 py-3">Pool</th>
                            <th class="text-right px-4 py-3">Per User</th>
                            <th class="text-right px-4 py-3">Pool Total</th>
                            <th class="text-right px-4 py-3">Income</th>
                            <th class="text-left px-4 py-3">Cycle Progress</th>
                            <th class="text-left px-4 py-3">My Status</th>
                            <th class="text-right px-4 py-3">My Income</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#f0e9db]">
                        <?php foreach ($ladder as $s):
                            $mp = $myPos[$s['level']] ?? null; ?>
                            <tr class="data-row">
                                <td class="px-4 py-3 font-medium">Pool <?php echo (int)$s['level']; ?></td>
                                <td class="px-4 py-3 text-right"><?php echo m($s['per_user']); ?></td>
                                <td class="px-4 py-3 text-right"><?php echo m($s['pool_total']); ?></td>
                                <td class="px-4 py-3 text-right font-semibold"><?php echo m($s['income']); ?></td>
                                <td class="px-4 py-3">
                                    <div class="w-36 bg-[#efe9dc] rounded-full h-2 overflow-hidden">
                                        <div class="bg-[#c79435] h-2" style="width: <?php echo (float)$s['progress_pct']; ?>%"></div>
                                    </div>
                                    <span class="text-[10px] text-[#a99c85]"><?php echo (float)$s['progress_pct']; ?>% · <?php echo (int)$s['waiting']; ?> waiting</span>
                                </td>
                                <td class="px-4 py-3">
                                    <?php if (!$mp): ?>
                                        <span class="text-[#b3a78f]">—</span>
                                    <?php elseif ($mp['status'] === 'paid'): ?>
                                        <span class="rounded-full bg-[#e8f7ee] text-[#1e7a48] px-2 py-0.5 text-[11px]">Cleared</span>
                                    <?php else: ?>
                                        <span class="rounded-full bg-[#fef3e0] text-[#b07d00] px-2 py-0.5 text-[11px]">Waiting · #<?php echo (int)$mp['queue_no']; ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-right"><?php echo $mp ? m($mp['income_amount']) : '—'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4 bg-white rounded-2xl border border-[#e2d8c8] overflow-hidden">
            <div class="px-5 py-3 border-b border-[#f0e9db] text-[14px] font-semibold text-[#40352a]">My Auto Pool Payouts</div>
            <div class="overflow-x-auto">
                <table class="w-full text-[13px]">
                    <thead class="bg-[#faf5ec] text-[#9e8f7e] text-[11px] uppercase tracking-wider">
                        <tr>
                            <th class="text-left px-4 py-3">Date</th>
                            <th class="text-left px-4 py-3">Pool</th>
                            <th class="text-right px-4 py-3">Pool Total</th>
                            <th class="text-right px-4 py-3">%</th>
                            <th class="text-right px-4 py-3">Income</th>
                            <th class="text-right px-4 py-3">Withdrawable</th>
                            <th class="text-left px-4 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#f0e9db]">
                        <?php if (empty($myLedger)): ?>
                            <tr><td colspan="7" class="px-4 py-10 text-center text-[#b3a78f]">
                                <i data-lucide="inbox" class="w-10 h-10 opacity-40 mx-auto mb-2"></i><div>No Auto Pool payouts yet.</div>
                            </td></tr>
                        <?php else: foreach ($myLedger as $x): ?>
                            <tr class="data-row">
                                <td class="px-4 py-3"><?php echo htmlspecialchars($x['report_date'] ?: substr((string)$x['created_at'], 0, 10)); ?></td>
                                <td class="px-4 py-3"><?php echo htmlspecialchars((string)$x['source']); ?></td>
                                <td class="px-4 py-3 text-right"><?php echo m($x['base_amount']); ?></td>
                                <td class="px-4 py-3 text-right"><?php echo number_format((float)$x['percentage'], 2); ?></td>
                                <td class="px-4 py-3 text-right font-semibold"><?php echo m($x['gross_amount']); ?></td>
                                <td class="px-4 py-3 text-right text-[#8a7c68]"><?php echo m($x['withdrawable_amount']); ?></td>
                                <td class="px-4 py-3">
                                    <?php $st = $x['status']; $cls = $st === 'Credit' ? 'bg-[#e8f7ee] text-[#1e7a48]' : 'bg-[#fef3e0] text-[#b07d00]'; ?>
                                    <span class="rounded-full <?php echo $cls; ?> px-2 py-0.5 text-[11px]"><?php echo htmlspecialchars($st); ?></span>
                                </td>
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
