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

// Handle Verification Action (Approve / Reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_verification'])) {
    $verId = (int)($_POST['verification_id'] ?? 0);
    $action = trim($_POST['status_action'] ?? '');
    $notes = trim($_POST['admin_notes'] ?? '');

    if ($verId > 0 && in_array($action, ['verified', 'rejected'], true)) {
        $showFeed = ($action === 'verified') ? 1 : 0;
        $upd = $conn->prepare("UPDATE ind_video_verifications 
                               SET status = ?, admin_notes = ?, show_on_landing_page = ?, verified_at = NOW() 
                               WHERE id = ?");
        $upd->bind_param('ssii', $action, $notes, $showFeed, $verId);
        if ($upd->execute()) {
            if ($action === 'verified') {
                $vq = $conn->query("SELECT v.*, sp.monthly_bpr_amount, sp.support_percent, sp.months_paid, sp.months_total, sp.vehicle_model 
                                    FROM ind_video_verifications v 
                                    JOIN ind_scooter_promotions sp ON sp.id = v.promotion_id 
                                    WHERE v.id = {$verId} LIMIT 1")->fetch_assoc();

                if ($vq) {
                    $promoId = (int)$vq['promotion_id'];
                    $cid = (string)$vq['customer_id'];
                    $bprBase = (float)$vq['monthly_bpr_amount'];
                    $supPct = (float)$vq['support_percent'];
                    $mPaid = (int)$vq['months_paid'];
                    $mTotal = (int)$vq['months_total'];
                    $vehModel = (string)$vq['vehicle_model'];
                    $monthNumber = (int)$vq['month_number'];
                    $periodKey = date('Y-m');

                    $companySupport = round($bprBase * $supPct / 100.0, 2);

                    if ($companySupport > 0) {
                        ind_credit_member_income($conn, $cid, "Scooter BPR Month {$monthNumber}", $companySupport, [
                            'source' => "Monthly Scooter Promotion ({$vehModel})",
                            'source_ref' => "BPR-P{$promoId}-M{$monthNumber}",
                            'base_amount' => $bprBase,
                            'percentage' => $supPct
                        ]);
                    }

                    $newPaid = $mPaid + 1;
                    $newStatus = ($newPaid >= $mTotal) ? 'completed' : 'active';
                    $conn->query("UPDATE ind_scooter_promotions 
                                  SET months_paid = {$newPaid}, status = '{$newStatus}' 
                                  WHERE id = {$promoId}");

                    $conn->query("INSERT INTO ind_scooter_monthly_payouts 
                        (promotion_id, customer_id, month_number, period_key, monthly_bpr_amount, support_percent, company_support_amount, member_share_amount, video_verification_id, status, paid_at, created_at)
                        VALUES ({$promoId}, '{$cid}', {$monthNumber}, '{$periodKey}', {$bprBase}, {$supPct}, {$companySupport}, " . ($bprBase - $companySupport) . ", {$verId}, 'paid', NOW(), NOW())");

                    if (function_exists('ind_distribute_team_bpr_bonus')) {
                        ind_distribute_team_bpr_bonus($conn, $promoId, $periodKey);
                    }

                    $successMsg = "Verification Approved! Monthly BPR of ₹" . number_format($companySupport, 2) . " disbursed to member {$cid} (50% Scooter / 50% Main). Team BPR bonus distributed.";
                } else {
                    $successMsg = "Verification marked as Verified successfully!";
                }
            } else {
                $successMsg = "Verification marked as Rejected.";
            }
        } else {
            $errorMsg = "Database error: " . $conn->error;
        }
        $upd->close();
    }
}

// Handle Manual 48-Hour Purge Trigger
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['trigger_purge'])) {
    $uploadBase = dirname(__DIR__);
    $purgeRes = ind_purge_expired_videos($conn, $uploadBase);
    $cnt = $purgeRes['purged_count'] ?? 0;
    $bytes = $purgeRes['freed_bytes'] ?? 0;
    $mb = number_format($bytes / (1024 * 1024), 2);
    $successMsg = "48-Hour Purge executed: {$cnt} video files permanently deleted, {$mb} MB disk space freed!";
}

// Statistics
$totalVehicles = (int)($conn->query("SELECT COUNT(*) as c FROM ind_scooter_promotions WHERE status='active'")->fetch_assoc()['c'] ?? 0);
$pendingVerifications = (int)($conn->query("SELECT COUNT(*) as c FROM ind_video_verifications WHERE status='submitted'")->fetch_assoc()['c'] ?? 0);
$verifiedVideos = (int)($conn->query("SELECT COUNT(*) as c FROM ind_video_verifications WHERE status='verified'")->fetch_assoc()['c'] ?? 0);
$purgedVideos = (int)($conn->query("SELECT COUNT(*) as c FROM ind_video_verifications WHERE video_deleted=1")->fetch_assoc()['c'] ?? 0);

// Fetch Video Verifications
$verifications = [];
$vQuery = $conn->query("SELECT v.*, su.full_name, su.mobile, sp.vehicle_model, sp.registration_number, sp.support_percent 
                       FROM ind_video_verifications v
                       JOIN signup_users su ON su.customer_id = v.customer_id
                       JOIN ind_scooter_promotions sp ON sp.id = v.promotion_id
                       ORDER BY v.id DESC LIMIT 100");
if ($vQuery) {
    while ($r = $vQuery->fetch_assoc()) {
        $verifications[] = $r;
    }
}

// Fetch Active Scooter Promotions
$promotions = [];
$pQuery = $conn->query("SELECT sp.*, su.full_name, su.mobile 
                       FROM ind_scooter_promotions sp
                       JOIN signup_users su ON su.customer_id = sp.customer_id
                       ORDER BY sp.id DESC LIMIT 100");
if ($pQuery) {
    while ($r = $pQuery->fetch_assoc()) {
        $promotions[] = $r;
    }
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
    <title>Electric Scooter Promotions &amp; Video Verifications</title>
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
                        <i data-lucide="bike" class="w-5 h-5 text-[#c79435]"></i>
                    </div>
                    <div>
                        <h2 class="text-[22px] sm:text-[24px] font-normal text-[#313131] leading-tight tracking-wide" style="font-family: Georgia, 'Times New Roman', serif;">
                            SCOOTER BRAND PROMOTIONS &amp; VIDEOS
                        </h2>
                        <p class="text-xs text-gray-500 mt-0.5">Review live GPS-tagged promotion videos, approve for landing page feed, and manage 48-hour video auto-purge.</p>
                    </div>
                </div>
                <div>
                    <form method="POST" onsubmit="return confirm('Execute 48-Hour video storage purge now? Files older than 48 hours will be permanently deleted from server.');">
                        <button type="submit" name="trigger_purge" class="px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-xl text-xs font-semibold flex items-center gap-1.5 transition shadow-sm">
                            <i data-lucide="trash-2" class="w-4 h-4"></i> Run 48h Video Purge Now
                        </button>
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
                <p class="text-xs uppercase font-semibold text-gray-400">Total Enrolled Scooters</p>
                <p class="text-2xl font-bold text-gray-800 mt-1"><?php echo $totalVehicles; ?></p>
                <p class="text-[11px] text-gray-500 mt-0.5">25-Month Support Programs</p>
            </div>
            <div class="bg-white rounded-2xl border border-amber-200 p-5 shadow-sm bg-amber-50/30">
                <p class="text-xs uppercase font-semibold text-amber-700">Pending Review</p>
                <p class="text-2xl font-bold text-amber-800 mt-1"><?php echo $pendingVerifications; ?></p>
                <p class="text-[11px] text-amber-600 mt-0.5">Awaiting GPS &amp; Proof Approval</p>
            </div>
            <div class="bg-white rounded-2xl border border-green-200 p-5 shadow-sm bg-green-50/30">
                <p class="text-xs uppercase font-semibold text-green-700">Verified &amp; Live Feed</p>
                <p class="text-2xl font-bold text-green-800 mt-1"><?php echo $verifiedVideos; ?></p>
                <p class="text-[11px] text-green-600 mt-0.5">Customer Video Proofs</p>
            </div>
            <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-sm">
                <p class="text-xs uppercase font-semibold text-gray-400">48h Auto-Purged Files</p>
                <p class="text-2xl font-bold text-gray-700 mt-1"><?php echo $purgedVideos; ?></p>
                <p class="text-[11px] text-gray-500 mt-0.5">Server Storage Permanently Saved</p>
            </div>
        </div>

        <!-- Section 1: Video Verifications Queue -->
        <div class="mt-8 bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <div>
                    <h2 class="text-base font-bold text-gray-800">Monthly Video Verifications &amp; GPS Submissions</h2>
                    <p class="text-xs text-gray-500">Live GPS coordinates and social media proof required for monthly BPR release.</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead class="bg-gray-50 text-gray-500 uppercase font-semibold border-b border-gray-200">
                        <tr>
                            <th class="px-4 py-3">Member</th>
                            <th class="px-4 py-3">Vehicle Details</th>
                            <th class="px-4 py-3">Month</th>
                            <th class="px-4 py-3">GPS Location</th>
                            <th class="px-4 py-3">KM / Odometer</th>
                            <th class="px-4 py-3">Media Proofs</th>
                            <th class="px-4 py-3 text-center">48h Storage</th>
                            <th class="px-4 py-3 text-center">Status</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (empty($verifications)): ?>
                            <tr><td colspan="9" class="px-4 py-8 text-center text-gray-400">No video verification submissions yet.</td></tr>
                        <?php else: foreach ($verifications as $v): ?>
                            <tr class="data-row">
                                <td class="px-4 py-3">
                                    <div class="font-bold text-gray-900"><?php echo htmlspecialchars($v['full_name']); ?></div>
                                    <div class="text-[11px] text-gray-500 font-mono"><?php echo htmlspecialchars($v['customer_id']); ?> · <?php echo htmlspecialchars($v['mobile']); ?></div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-800"><?php echo htmlspecialchars($v['vehicle_model']); ?></div>
                                    <div class="text-[11px] text-gray-500 font-mono"><?php echo htmlspecialchars($v['registration_number']); ?></div>
                                </td>
                                <td class="px-4 py-3 font-semibold text-[#b47e1d]">Month <?php echo (int)$v['month_number']; ?></td>
                                <td class="px-4 py-3">
                                    <div class="font-mono text-gray-700"><?php echo number_format($v['gps_latitude'], 5); ?>, <?php echo number_format($v['gps_longitude'], 5); ?></div>
                                    <a href="https://maps.google.com/?q=<?php echo $v['gps_latitude']; ?>,<?php echo $v['gps_longitude']; ?>" target="_blank" class="text-blue-600 hover:underline flex items-center gap-1 mt-0.5">
                                        <i data-lucide="external-link" class="w-3 h-3"></i> View on Google Maps
                                    </a>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-bold text-gray-800"><?php echo number_format($v['km_run'] ?? 0); ?> KM</div>
                                    <?php if (!empty($v['odometer_reading'])): ?>
                                        <div class="text-[11px] text-gray-500 font-mono">Reading: <?php echo htmlspecialchars($v['odometer_reading']); ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($v['odometer_photo_path'])): ?>
                                        <a href="../<?php echo htmlspecialchars($v['odometer_photo_path']); ?>" target="_blank" class="text-blue-600 hover:underline flex items-center gap-1 mt-0.5">
                                            <i data-lucide="image" class="w-3 h-3"></i> Odometer Photo
                                        </a>
                                    <?php endif; ?>
                                    <?php if (!empty($v['feedback_notes'])): ?>
                                        <div class="text-[11px] text-gray-600 italic mt-1 bg-gray-50 p-1.5 rounded max-w-[160px] truncate" title="<?php echo htmlspecialchars($v['feedback_notes']); ?>">
                                            "<?php echo htmlspecialchars($v['feedback_notes']); ?>"
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <?php if ((int)$v['video_deleted'] === 0 && !empty($v['video_file_path'])): ?>
                                            <button type="button" 
                                                    onclick="openVideoModal('<?php echo htmlspecialchars('../' . ltrim($v['video_file_path'], '/\\')); ?>', '<?php echo htmlspecialchars(addslashes($v['full_name'] . ' (' . $v['customer_id'] . ')')); ?>', 'Month <?php echo (int)$v['month_number']; ?>', '<?php echo number_format((int)$v['km_run']); ?> KM (Odometer: <?php echo number_format((int)$v['odometer_reading']); ?> KM)', '<?php echo number_format((float)$v['gps_latitude'], 4); ?>, <?php echo number_format((float)$v['gps_longitude'], 4); ?>')" 
                                                    class="px-2 py-1 rounded bg-blue-50 text-blue-700 hover:bg-blue-100 font-semibold flex items-center gap-1 cursor-pointer transition">
                                                <i data-lucide="play" class="w-3 h-3"></i> Video
                                            </button>
                                        <?php else: ?>
                                            <span class="text-gray-400 italic">Video Purged</span>
                                        <?php endif; ?>

                                        <?php if (!empty($v['whatsapp_screenshot_path'])): ?>
                                            <a href="../<?php echo htmlspecialchars($v['whatsapp_screenshot_path']); ?>" target="_blank" class="px-2 py-1 rounded bg-green-50 text-green-700 hover:bg-green-100 font-semibold flex items-center gap-1">
                                                <i data-lucide="image" class="w-3 h-3"></i> WA
                                            </a>
                                        <?php endif; ?>

                                        <?php if (!empty($v['social_story_screenshot_path'])): ?>
                                            <a href="../<?php echo htmlspecialchars($v['social_story_screenshot_path']); ?>" target="_blank" class="px-2 py-1 rounded bg-purple-50 text-purple-700 hover:bg-purple-100 font-semibold flex items-center gap-1">
                                                <i data-lucide="image" class="w-3 h-3"></i> Story
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <?php if ((int)$v['video_deleted'] === 1): ?>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-gray-100 text-gray-600">File Purged</span>
                                    <?php else: ?>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-blue-50 text-blue-700">Stored (&lt;48h)</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <?php if ($v['status'] === 'verified'): ?>
                                        <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-green-100 text-green-800">Approved</span>
                                    <?php elseif ($v['status'] === 'rejected'): ?>
                                        <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-red-100 text-red-800">Rejected</span>
                                    <?php else: ?>
                                        <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-100 text-amber-800">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <?php if ($v['status'] === 'submitted'): ?>
                                        <form method="POST" class="inline-flex items-center gap-1">
                                            <input type="hidden" name="verification_id" value="<?php echo (int)$v['id']; ?>">
                                            <input type="hidden" name="admin_notes" value="Verified by admin">
                                            <button type="submit" name="action_verification" value="1" onclick="this.form.status_action.value='verified';" class="px-2.5 py-1 bg-green-600 hover:bg-green-700 text-white font-bold rounded-lg text-[11px]">
                                                Approve
                                            </button>
                                            <button type="submit" name="action_verification" value="1" onclick="this.form.status_action.value='rejected';" class="px-2.5 py-1 bg-red-600 hover:bg-red-700 text-white font-bold rounded-lg text-[11px]">
                                                Reject
                                            </button>
                                            <input type="hidden" name="status_action" value="verified">
                                        </form>
                                    <?php else: ?>
                                        <span class="text-gray-400 text-[11px]">Completed</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Section 2: Active Scooter Promotions -->
        <div class="mt-8 bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="text-base font-bold text-gray-800">All Enrolled Electric Vehicles (25-Month Program)</h2>
                <p class="text-xs text-gray-500">Tracks escalation ladder from 50% up to 100% Free Scooter based on direct vehicle count.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead class="bg-gray-50 text-gray-500 uppercase font-semibold border-b border-gray-200">
                        <tr>
                            <th class="px-4 py-3">Member ID</th>
                            <th class="px-4 py-3">Vehicle Details</th>
                            <th class="px-4 py-3 text-center">Direct Vehicles</th>
                            <th class="px-4 py-3 text-center">Current Support %</th>
                            <th class="px-4 py-3 text-right">Monthly BPR</th>
                            <th class="px-4 py-3 text-center">Progress</th>
                            <th class="px-4 py-3 text-center">Agreement</th>
                            <th class="px-4 py-3 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (empty($promotions)): ?>
                            <tr><td colspan="8" class="px-4 py-8 text-center text-gray-400">No members enrolled in Scooter Promotion yet.</td></tr>
                        <?php else: foreach ($promotions as $p): ?>
                            <tr class="data-row">
                                <td class="px-4 py-3">
                                    <div class="font-bold text-gray-900"><?php echo htmlspecialchars($p['full_name']); ?></div>
                                    <div class="text-[11px] text-gray-500 font-mono"><?php echo htmlspecialchars($p['customer_id']); ?></div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-800"><?php echo htmlspecialchars($p['vehicle_model']); ?></div>
                                    <div class="text-[11px] text-gray-500 font-mono"><?php echo htmlspecialchars($p['registration_number']); ?></div>
                                </td>
                                <td class="px-4 py-3 text-center font-bold text-gray-700"><?php echo (int)$p['direct_vehicles_count']; ?> Directs</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="px-2.5 py-1 rounded-full text-[11px] font-bold <?php echo (float)$p['support_percent'] >= 100 ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800'; ?>">
                                        <?php echo number_format($p['support_percent'], 0); ?>% <?php echo (float)$p['support_percent'] >= 100 ? '(FREE)' : 'Support'; ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right font-semibold text-gray-800"><?php echo m($p['monthly_bpr_amount']); ?></td>
                                <td class="px-4 py-3 text-center font-semibold text-[#b47e1d]"><?php echo (int)$p['months_paid']; ?> / <?php echo (int)($p['months_total'] ?? 25); ?> Mos</td>
                                <td class="px-4 py-3 text-center">
                                    <?php if ((int)$p['agreement_signed'] === 1): ?>
                                        <span class="text-green-700 font-semibold flex items-center justify-center gap-1">
                                            <i data-lucide="check" class="w-3 h-3"></i> Signed
                                        </span>
                                    <?php else: ?>
                                        <span class="text-amber-600 font-medium">Pending Sign</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-green-50 text-green-700 capitalize"><?php echo htmlspecialchars($p['status']); ?></span>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Video Player Lightbox Modal -->
        <div id="videoModal" class="fixed inset-0 bg-black/75 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl max-w-2xl w-full overflow-hidden shadow-2xl border border-gray-200">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between bg-gradient-to-r from-amber-50 to-white">
                    <div>
                        <h3 id="videoModalTitle" class="text-base font-bold text-gray-900">Ride Proof Video</h3>
                        <p id="videoModalSubtitle" class="text-xs text-gray-500 mt-0.5">Brand Promotion Monthly Proof Verification</p>
                    </div>
                    <button type="button" onclick="closeVideoModal()" class="w-8 h-8 rounded-full bg-gray-100 hover:bg-gray-200 flex items-center justify-center text-gray-500 transition cursor-pointer">
                        <i data-lucide="x" class="w-4 h-4"></i>
                    </button>
                </div>
                <div class="p-3 bg-black flex items-center justify-center">
                    <video id="modalVideoPlayer" controls class="w-full max-h-[440px] rounded-lg shadow bg-black" preload="auto">
                        <source id="modalVideoSource" src="" type="video/mp4">
                        Your browser does not support HTML5 video tag.
                    </video>
                </div>
                <div class="px-5 py-3 bg-gray-50 flex items-center justify-between text-xs border-t border-gray-200">
                    <div id="videoModalTelemetry" class="text-gray-600 flex items-center gap-2"></div>
                    <a id="videoModalNewTab" href="#" target="_blank" class="text-blue-600 hover:underline flex items-center gap-1 font-semibold">
                        Open in New Tab <i data-lucide="external-link" class="w-3.5 h-3.5"></i>
                    </a>
                </div>
            </div>
        </div>
    </main>
    </div>
</div>

<script>
lucide.createIcons();

function openVideoModal(url, memberName, month, kmInfo, gps) {
    const modal = document.getElementById('videoModal');
    const player = document.getElementById('modalVideoPlayer');
    const src = document.getElementById('modalVideoSource');
    const title = document.getElementById('videoModalTitle');
    const telemetry = document.getElementById('videoModalTelemetry');
    const newTab = document.getElementById('videoModalNewTab');

    title.innerText = memberName + ' — ' + month;
    telemetry.innerHTML = '<span class="font-bold text-[#b47e1d]">' + kmInfo + '</span> &bull; <span>GPS: ' + gps + '</span>';
    newTab.href = url;

    src.src = url;
    player.load();
    player.play().catch(function() {});

    modal.classList.remove('hidden');
    lucide.createIcons();
}

function closeVideoModal() {
    const modal = document.getElementById('videoModal');
    const player = document.getElementById('modalVideoPlayer');
    player.pause();
    modal.classList.add('hidden');
}

document.getElementById('videoModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeVideoModal();
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeVideoModal();
});
</script>
</body>
</html>

