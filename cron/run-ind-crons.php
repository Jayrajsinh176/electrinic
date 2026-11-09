<?php
/**
 * IND Member Business Plan — Automated Cron Worker
 * 
 * Tasks:
 *   1. 48-Hour Auto-Delete: Permanently unlinks video files older than 48 hours to save server storage.
 *   2. Monthly Scooter Support Payout & 6-Month 10-Level Team Promotion Bonus (BPR) Distribution.
 * 
 * Can be run from CLI or HTTP scheduler:
 *   php cron/run-ind-crons.php
 *   http://localhost/cron/run-ind-crons.php?key=<cron_key>
 */

require_once __DIR__ . '/../member/config/db.php';
require_once __DIR__ . '/../includes/income_common.php';
require_once __DIR__ . '/../includes/ind_income_functions.php';

if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
}

echo "=== IND BUSINESS PLAN CRON WORKER ===\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

// -------------------------------------------------------------
// TASK 1: 48-Hour Video Permanent File Purge
// -------------------------------------------------------------
echo "[Task 1] Purging expired video files (> 48 hours)...\n";
$uploadBase = dirname(__DIR__);
$purgeRes = ind_purge_expired_videos($conn, $uploadBase);
echo "  Purged video records: " . ($purgeRes['purged_count'] ?? 0) . "\n";
echo "  Disk storage freed: " . number_format(($purgeRes['freed_bytes'] ?? 0) / (1024 * 1024), 2) . " MB\n\n";

// -------------------------------------------------------------
// TASK 2: Monthly Scooter Support & 10-Level Team BPR Bonus
// -------------------------------------------------------------
$periodKey = date('Y-m');
echo "[Task 2] Processing Monthly Scooter Support & Team BPR Bonus for {$periodKey}...\n";

// Find active promotions that haven't been paid for this period
$promos = $conn->query("SELECT sp.* FROM ind_scooter_promotions sp
                        WHERE sp.status = 'active' 
                        AND sp.months_paid < sp.months_total
                        ORDER BY sp.id ASC");

$payoutCount = 0;
$bprBonusCount = 0;

if ($promos) {
    while ($promo = $promos->fetch_assoc()) {
        $promoId = (int)$promo['id'];
        $custId = (string)$promo['customer_id'];
        $bpr = (float)$promo['monthly_bpr_amount'];
        $monthsPaid = (int)$promo['months_paid'];
        $monthNo = $monthsPaid + 1;

        // Check if already paid for this period
        $chk = $conn->prepare("SELECT id, status FROM ind_scooter_monthly_payouts WHERE promotion_id = ? AND period_key = ? LIMIT 1");
        $chk->bind_param('is', $promoId, $periodKey);
        $chk->execute();
        $existingPayout = $chk->get_result()->fetch_assoc();
        $chk->close();

        if ($existingPayout && in_array($existingPayout['status'], ['paid', 'approved'], true)) {
            continue; // Already processed
        }

        // Check for verified video verification in this month or period
        $vStmt = $conn->prepare("SELECT id FROM ind_video_verifications 
                                 WHERE promotion_id = ? AND status = 'verified' 
                                 AND (month_number = ? OR DATE_FORMAT(created_at, '%Y-%m') = ?) 
                                 LIMIT 1");
        $vStmt->bind_param('iis', $promoId, $monthNo, $periodKey);
        $vStmt->execute();
        $verRow = $vStmt->get_result()->fetch_assoc();
        $vStmt->close();

        $vidId = $verRow ? (int)$verRow['id'] : null;

        // Refresh support percentage based on current direct vehicles count
        $supportPct = ind_refresh_scooter_support($conn, $custId);
        $companySupport = round($bpr * $supportPct / 100.0, 2);
        $memberShare = round($bpr - $companySupport, 2);

        // Record or update monthly payout row
        if (!$existingPayout) {
            $insPay = $conn->prepare("INSERT INTO ind_scooter_monthly_payouts
                (promotion_id, customer_id, month_number, period_key, monthly_bpr_amount, support_percent,
                 company_support_amount, member_share_amount, video_verification_id, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved', NOW())");
            $insPay->bind_param('isisddddi', $promoId, $custId, $monthNo, $periodKey, $bpr, $supportPct, $companySupport, $memberShare, $vidId);
            $insPay->execute();
            $payoutId = (int)$insPay->insert_id;
            $insPay->close();
        } else {
            $payoutId = (int)$existingPayout['id'];
            $conn->query("UPDATE ind_scooter_monthly_payouts 
                          SET support_percent = {$supportPct}, 
                              company_support_amount = {$companySupport}, 
                              member_share_amount = {$memberShare}, 
                              status = 'approved', video_verification_id = " . ($vidId ? $vidId : "NULL") . " 
                          WHERE id = {$payoutId}");
        }

        // Credit company support amount to member (50% Scooter Wallet / 50% Main Balance)
        if ($companySupport > 0) {
            ind_credit_member_income($conn, $custId, "Scooter Monthly Support", $companySupport, [
                'source' => 'Brand Promotion',
                'source_ref' => "BPR-SP-{$promoId}-M{$monthNo}",
                'base_amount' => $bpr,
                'percentage' => $supportPct
            ]);
        }

        // Mark payout as paid
        $conn->query("UPDATE ind_scooter_monthly_payouts SET status = 'paid', paid_at = NOW() WHERE id = {$payoutId}");
        $conn->query("UPDATE ind_scooter_promotions SET months_paid = months_paid + 1 WHERE id = {$promoId}");

        $payoutCount++;

        // Trigger 10-Level Team Promotion Bonus (for first 6 months)
        if ($monthNo <= 6) {
            $bprRes = ind_distribute_team_bpr_bonus($conn, $promoId, $periodKey);
            if (!empty($bprRes['ok'])) {
                $bprBonusCount += count($bprRes['distributed'] ?? []);
            }
        }
    }
}

echo "  Scooter Monthly Support Payouts processed: {$payoutCount}\n";
echo "  10-Level Team Promotion Bonuses distributed: {$bprBonusCount}\n\n";

// -------------------------------------------------------------
// TASK 3: Monthly Cashback Processing (10 Months @ ₹1,000/mo)
// -------------------------------------------------------------
echo "[Task 3] Processing Due Monthly Cashbacks (₹1,000 to Main Wallet)...\n";
if (function_exists('ind_process_due_cashbacks')) {
    $cbRes = ind_process_due_cashbacks($conn);
    echo "  Processed cashback installments: " . ($cbRes['processed'] ?? 0) . "\n";
    if (!empty($cbRes['errors'])) {
        echo "  Errors: " . implode('; ', $cbRes['errors']) . "\n";
    }
}
echo "\n";

// -------------------------------------------------------------
// TASK 4: Brand Promotion Packages & 10-Root Affiliate (10 Months)
// -------------------------------------------------------------
echo "[Task 4] Processing Brand Promotion Packages & 10-Root Affiliate Payouts...\n";
if (file_exists(__DIR__ . '/../includes/bp_package_functions.php')) {
    require_once __DIR__ . '/../includes/bp_package_functions.php';
    $bpRes = bp_process_monthly_payouts($conn, $periodKey);
    echo "  Subscribers paid: " . ($bpRes['subscribers_paid'] ?? 0) . " (₹" . number_format($bpRes['total_subscriber_payout'] ?? 0, 2) . ")\n";
    echo "  10-Root Affiliates paid: " . ($bpRes['affiliates_paid'] ?? 0) . " (₹" . number_format($bpRes['total_affiliate_payout'] ?? 0, 2) . ")\n";
}
echo "\n";

echo "=== CRON RUN FINISHED SUCCESSFULLY ===\n";

