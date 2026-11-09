<?php
/**
 * ===========================================================================
 * BRAND PROMOTION PACKAGES & 10-ROOT AFFILIATE ENGINE (10-MONTH MODEL)
 * ===========================================================================
 * Modules:
 *   1. 4 Tier Packages (₹10,000, ₹50,000, ₹1,00,000, ₹2,00,000)
 *   2. 10% Monthly Promotional Return for 10 Months (100% Money Back)
 *   3. 5% Instant One-Time Direct Sponsor Bonus
 *   4. 10-Root Affiliate Marketing (51% total) Paid Monthly for 10 Months
 *   5. Seamless Member Wallet & Bank Deposit Payment Support
 * ===========================================================================
 */

if (!defined('BP_ENGINE_LOADED')) {
    define('BP_ENGINE_LOADED', true);
}

require_once __DIR__ . '/income_common.php';
if (file_exists(__DIR__ . '/ind_income_functions.php')) {
    require_once __DIR__ . '/ind_income_functions.php';
}

/**
 * Fetch all active Brand Promotion Packages
 */
function bp_get_active_packages(mysqli $conn): array
{
    $packages = [];
    $res = $conn->query("SELECT * FROM ind_bp_packages WHERE status = 'active' ORDER BY sort_order ASC, price ASC");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $packages[] = $r;
        }
    }
    return $packages;
}

/**
 * Fetch a single package by ID
 */
function bp_get_package_by_id(mysqli $conn, int $id): ?array
{
    $stmt = $conn->prepare("SELECT * FROM ind_bp_packages WHERE id = ? LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $res ?: null;
}

/**
 * Fetch active subscriptions for a member
 */
function bp_get_member_subscriptions(mysqli $conn, string $customerId): array
{
    $subs = [];
    $stmt = $conn->prepare("SELECT * FROM ind_bp_subscriptions WHERE customer_id = ? ORDER BY id DESC");
    if ($stmt) {
        $stmt->bind_param('s', $customerId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $subs[] = $r;
        }
        $stmt->close();
    }
    return $subs;
}

/**
 * Subscribe to a package paying directly from Main Wallet Balance (Instant Activation)
 */
function bp_subscribe_from_wallet(mysqli $conn, string $customerId, int $packageId): array
{
    $out = ['ok' => false, 'message' => '', 'subscription_id' => 0];

    $pkg = bp_get_package_by_id($conn, $packageId);
    if (!$pkg || $pkg['status'] !== 'active') {
        $out['message'] = 'Selected package is not valid or active.';
        return $out;
    }

    $price = (float)$pkg['price'];
    $monthlyReturn = (float)$pkg['monthly_return'];
    $duration = (int)($pkg['duration_months'] ?: 10);
    $directPct = (float)($pkg['direct_bonus_percent'] ?: 5.00);

    // Check user wallet balance
    $st = $conn->prepare("SELECT wallet_balance, full_name FROM signup_users WHERE customer_id = ? LIMIT 1");
    $st->bind_param('s', $customerId);
    $st->execute();
    $user = $st->get_result()->fetch_assoc();
    $st->close();

    if (!$user) {
        $out['message'] = 'User account not found.';
        return $out;
    }

    $walletBal = (float)($user['wallet_balance'] ?? 0.00);
    if ($walletBal < $price) {
        $out['message'] = "Insufficient wallet balance. You need ₹" . number_format($price, 2) . " but your available balance is ₹" . number_format($walletBal, 2) . ".";
        return $out;
    }

    $conn->begin_transaction();
    try {
        // 1. Deduct price from wallet_balance
        $newBal = round($walletBal - $price, 2);
        $upd = $conn->prepare("UPDATE signup_users SET wallet_balance = ? WHERE customer_id = ?");
        $upd->bind_param('ds', $newBal, $customerId);
        $upd->execute();
        $upd->close();

        // 2. Log in wallet_transactions
        if (income_table_exists($conn, 'wallet_transactions')) {
            $insTx = $conn->prepare("INSERT INTO wallet_transactions
                (customer_id, direction, category, amount, balance_after, note, created_at)
                VALUES (?, 'debit', 'package_purchase', ?, ?, ?, NOW())");
            $note = "Subscribed to " . $pkg['package_name'] . " (₹" . number_format($price, 0) . ")";
            $insTx->bind_param('sdds', $customerId, $price, $newBal, $note);
            $insTx->execute();
            $insTx->close();
        }

        // 3. Create Subscription
        $subCode = 'SUB-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid($customerId, true)), 0, 6));
        $startDate = date('Y-m-d');
        $nextPayoutDate = date('Y-m-d', strtotime('+1 month'));
        $sponsorId = income_sponsor_of($conn, $customerId);
        $directBonusAmt = round($price * $directPct / 100.0, 2);

        $insSub = $conn->prepare("INSERT INTO ind_bp_subscriptions
            (subscription_code, customer_id, package_id, package_code, package_name, package_amount,
             monthly_payout, duration_months, months_paid, total_paid, payment_mode, direct_sponsor_id,
             direct_bonus_amount, direct_bonus_status, start_date, next_payout_date, approved_by, approved_at, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 0.00, 'Wallet', ?, ?, 'pending', ?, ?, 'Wallet Instant', NOW(), 'active', NOW())");
        $insSub->bind_param(
            'ssissddisdss',
            $subCode,
            $customerId,
            $packageId,
            $pkg['package_code'],
            $pkg['package_name'],
            $price,
            $monthlyReturn,
            $duration,
            $sponsorId,
            $directBonusAmt,
            $startDate,
            $nextPayoutDate
        );
        $insSub->execute();
        $subId = (int)$insSub->insert_id;
        $insSub->close();

        // 4. Distribute 5% Direct Sponsor Bonus immediately
        bp_distribute_direct_bonus($conn, $subId);

        $conn->commit();
        $out['ok'] = true;
        $out['message'] = "Successfully subscribed to {$pkg['package_name']}! Your 10-month promotional return schedule is now active.";
        $out['subscription_id'] = $subId;
        return $out;
    } catch (\Throwable $e) {
        $conn->rollback();
        $out['message'] = "Subscription failed: " . $e->getMessage();
        return $out;
    }
}

/**
 * Submit package request paying via Bank / UPI (Pending Admin Approval)
 */
function bp_request_from_bank(mysqli $conn, string $customerId, int $packageId, string $paymentMode, string $trxId, string $slipPath): array
{
    $out = ['ok' => false, 'message' => '', 'subscription_id' => 0];

    $pkg = bp_get_package_by_id($conn, $packageId);
    if (!$pkg || $pkg['status'] !== 'active') {
        $out['message'] = 'Selected package is not valid or active.';
        return $out;
    }

    $price = (float)$pkg['price'];
    $monthlyReturn = (float)$pkg['monthly_return'];
    $duration = (int)($pkg['duration_months'] ?: 10);
    $directPct = (float)($pkg['direct_bonus_percent'] ?: 5.00);

    $subCode = 'SUB-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid($customerId, true)), 0, 6));
    $sponsorId = income_sponsor_of($conn, $customerId);
    $directBonusAmt = round($price * $directPct / 100.0, 2);

    $stmt = $conn->prepare("INSERT INTO ind_bp_subscriptions
        (subscription_code, customer_id, package_id, package_code, package_name, package_amount,
         monthly_payout, duration_months, months_paid, total_paid, payment_mode, transaction_id, payment_slip,
         direct_sponsor_id, direct_bonus_amount, direct_bonus_status, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 0.00, ?, ?, ?, ?, ?, 'pending', 'pending', NOW())");
    if (!$stmt) {
        $out['message'] = "Database error: " . $conn->error;
        return $out;
    }
    $stmt->bind_param(
        'ssissddissssd',
        $subCode,
        $customerId,
        $packageId,
        $pkg['package_code'],
        $pkg['package_name'],
        $price,
        $monthlyReturn,
        $duration,
        $paymentMode,
        $trxId,
        $slipPath,
        $sponsorId,
        $directBonusAmt
    );
    if ($stmt->execute()) {
        $out['ok'] = true;
        $out['subscription_id'] = (int)$stmt->insert_id;
        $out['message'] = "Your {$pkg['package_name']} subscription request has been submitted successfully! Once verified by admin, your 10-month return will start.";
    } else {
        $out['message'] = "Submission failed: " . $stmt->error;
    }
    $stmt->close();
    return $out;
}

/**
 * Admin Approves a pending bank subscription request
 */
function bp_approve_subscription(mysqli $conn, int $subId, string $adminName = 'Admin'): array
{
    $out = ['ok' => false, 'message' => ''];

    $st = $conn->prepare("SELECT * FROM ind_bp_subscriptions WHERE id = ? LIMIT 1");
    $st->bind_param('i', $subId);
    $st->execute();
    $sub = $st->get_result()->fetch_assoc();
    $st->close();

    if (!$sub) {
        $out['message'] = 'Subscription request not found.';
        return $out;
    }

    if ($sub['status'] === 'active') {
        $out['message'] = 'Subscription is already active.';
        return $out;
    }

    $startDate = date('Y-m-d');
    $nextPayoutDate = date('Y-m-d', strtotime('+1 month'));

    $upd = $conn->prepare("UPDATE ind_bp_subscriptions 
                           SET status = 'active', start_date = ?, next_payout_date = ?, approved_by = ?, approved_at = NOW()
                           WHERE id = ?");
    $upd->bind_param('sssi', $startDate, $nextPayoutDate, $adminName, $subId);
    if ($upd->execute()) {
        $upd->close();
        // Credit the 5% Direct Sponsor Bonus
        bp_distribute_direct_bonus($conn, $subId);
        $out['ok'] = true;
        $out['message'] = "Subscription approved successfully. 5% Direct Bonus credited to sponsor, and 10-month return is now active.";
    } else {
        $out['message'] = "Approval failed: " . $conn->error;
        $upd->close();
    }
    return $out;
}

/**
 * Admin Rejects a pending bank subscription request
 */
function bp_reject_subscription(mysqli $conn, int $subId, string $adminName = 'Admin', string $reason = ''): array
{
    $out = ['ok' => false, 'message' => ''];
    $upd = $conn->prepare("UPDATE ind_bp_subscriptions SET status = 'rejected', notes = ?, approved_by = ? WHERE id = ?");
    $upd->bind_param('ssi', $reason, $adminName, $subId);
    if ($upd->execute()) {
        $out['ok'] = true;
        $out['message'] = "Subscription request rejected.";
    } else {
        $out['message'] = "Action failed: " . $conn->error;
    }
    $upd->close();
    return $out;
}

/**
 * Distribute 5% One-Time Direct Sponsor Bonus
 */
function bp_distribute_direct_bonus(mysqli $conn, int $subId): array
{
    $out = ['ok' => false, 'sponsor_id' => '', 'amount' => 0.0];

    $st = $conn->prepare("SELECT * FROM ind_bp_subscriptions WHERE id = ? LIMIT 1");
    $st->bind_param('i', $subId);
    $st->execute();
    $sub = $st->get_result()->fetch_assoc();
    $st->close();

    if (!$sub || $sub['direct_bonus_status'] === 'paid') {
        return $out;
    }

    $buyerId = (string)$sub['customer_id'];
    $subCode = (string)$sub['subscription_code'];
    $pkgAmount = (float)$sub['package_amount'];
    $pkgName = (string)$sub['package_name'];
    $bonusAmt = (float)$sub['direct_bonus_amount'];

    $sponsorId = (string)($sub['direct_sponsor_id'] ?? '');
    if ($sponsorId === '') {
        $sponsorId = income_sponsor_of($conn, $buyerId);
    }

    if ($sponsorId === '' || $sponsorId === $buyerId || $bonusAmt <= 0) {
        $conn->query("UPDATE ind_bp_subscriptions SET direct_bonus_status = 'paid' WHERE id = {$subId}");
        return $out;
    }

    // Get buyer full name
    $stN = $conn->prepare("SELECT full_name FROM signup_users WHERE customer_id = ? LIMIT 1");
    $stN->bind_param('s', $buyerId);
    $stN->execute();
    $bRow = $stN->get_result()->fetch_assoc();
    $stN->close();
    $buyerName = (string)($bRow['full_name'] ?? $buyerId);

    // Credit sponsor using core dual-wallet or wallet engine
    if (function_exists('ind_credit_member_income')) {
        ind_credit_member_income($conn, $sponsorId, 'Brand Promotion Direct Bonus', $bonusAmt, [
            'source' => 'Brand Promotion Package',
            'source_ref' => "{$pkgName} ({$subCode})",
            'from_member_id' => $buyerId,
            'from_member_name' => $buyerName,
            'base_amount' => $pkgAmount,
            'percentage' => 5.0
        ]);
    }

    // Mirror into direct_sponsor_income table so it reflects on member direct-sponsor-income.php
    if (income_table_exists($conn, 'direct_sponsor_income')) {
        $curDate = date('Y-m-d');
        $srcText = "BP Package ({$pkgName})";
        $insDir = $conn->prepare("INSERT INTO direct_sponsor_income
            (sponsor_id, from_member_id, from_member_name, report_date, base_amount, percentage,
             income_amount, fund_amount, withdrawable_amount, credit, balance, source, ref_id, status, created_at)
            VALUES (?, ?, ?, ?, ?, 5.00, ?, 0.00, ?, ?, ?, ?, ?, 'Credit', NOW())
            ON DUPLICATE KEY UPDATE income_amount = VALUES(income_amount)");
        if ($insDir) {
            $insDir->bind_param(
                'ssssdddddsi',
                $sponsorId,
                $buyerId,
                $buyerName,
                $curDate,
                $pkgAmount,
                $bonusAmt,
                $bonusAmt,
                $bonusAmt,
                $bonusAmt,
                $srcText,
                $subId
            );
            $insDir->execute();
            $insDir->close();
        }
    }

    $conn->query("UPDATE ind_bp_subscriptions SET direct_sponsor_id = '{$sponsorId}', direct_bonus_status = 'paid' WHERE id = {$subId}");

    $out['ok'] = true;
    $out['sponsor_id'] = $sponsorId;
    $out['amount'] = $bonusAmt;
    return $out;
}

/**
 * Process Monthly Payouts & 10-Root Affiliate Commission (for 10 Months)
 * Triggered by scheduled cron or manual admin execution
 */
function bp_process_monthly_payouts(mysqli $conn, ?string $periodKey = null, bool $force = false): array
{
    $periodKey = $periodKey ?: date('Y-m');
    $out = [
        'ok' => true,
        'period_key' => $periodKey,
        'subscribers_paid' => 0,
        'total_subscriber_payout' => 0.0,
        'affiliates_paid' => 0,
        'total_affiliate_payout' => 0.0,
        'details' => []
    ];

    // Root rate configuration (Total = 51%)
    $rootRates = [
        1  => (float)income_setting_num($conn, 'bp_root_1_percent', 10.0),
        2  => (float)income_setting_num($conn, 'bp_root_2_percent', 8.0),
        3  => (float)income_setting_num($conn, 'bp_root_3_percent', 5.0),
        4  => (float)income_setting_num($conn, 'bp_root_4_percent', 5.0),
        5  => (float)income_setting_num($conn, 'bp_root_5_percent', 4.0),
        6  => (float)income_setting_num($conn, 'bp_root_6_percent', 4.0),
        7  => (float)income_setting_num($conn, 'bp_root_7_percent', 4.0),
        8  => (float)income_setting_num($conn, 'bp_root_8_percent', 3.0),
        9  => (float)income_setting_num($conn, 'bp_root_9_percent', 3.0),
        10 => (float)income_setting_num($conn, 'bp_root_10_percent', 5.0)
    ];

    // Find active subscriptions where months_paid < duration_months
    $whereDate = $force ? "1=1" : "(next_payout_date IS NULL OR next_payout_date <= CURDATE())";
    $query = "SELECT * FROM ind_bp_subscriptions 
              WHERE status = 'active' AND months_paid < duration_months AND {$whereDate}
              ORDER BY id ASC";
    $subs = $conn->query($query);

    if (!$subs) return $out;

    while ($sub = $subs->fetch_assoc()) {
        $subId = (int)$sub['id'];
        $custId = (string)$sub['customer_id'];
        $monthlyPayout = (float)$sub['monthly_payout'];
        $duration = (int)$sub['duration_months'];
        $monthsPaid = (int)$sub['months_paid'];
        $monthNo = $monthsPaid + 1;
        $pkgName = (string)$sub['package_name'];

        // Guard against double payout in the same period
        $chk = $conn->prepare("SELECT id FROM ind_bp_monthly_payouts WHERE subscription_id = ? AND (month_number = ? OR period_key = ?) LIMIT 1");
        $chk->bind_param('iis', $subId, $monthNo, $periodKey);
        $chk->execute();
        if ($chk->get_result()->fetch_assoc()) {
            $chk->close();
            continue; // Already processed
        }
        $chk->close();

        // 1. Credit subscriber their 10% monthly promotional bonus
        if (function_exists('ind_credit_member_income')) {
            ind_credit_member_income($conn, $custId, "Brand Promotion Return M{$monthNo}", $monthlyPayout, [
                'source' => 'Brand Promotion Package',
                'source_ref' => "SUB-{$subId}-M{$monthNo}",
                'base_amount' => $sub['package_amount'],
                'percentage' => 10.0
            ]);
        }

        // Insert into ind_bp_monthly_payouts
        $insP = $conn->prepare("INSERT INTO ind_bp_monthly_payouts
            (subscription_id, customer_id, month_number, payout_amount, period_key, status, paid_at, created_at)
            VALUES (?, ?, ?, ?, ?, 'paid', NOW(), NOW())");
        $insP->bind_param('isids', $subId, $custId, $monthNo, $monthlyPayout, $periodKey);
        $insP->execute();
        $insP->close();

        $out['subscribers_paid']++;
        $out['total_subscriber_payout'] += $monthlyPayout;

        // 2. Distribute 10-Root Affiliate Commission (51% of monthly payout)
        $currUpline = $custId;
        $seen = [$custId => true];
        $affiliatesThisSub = 0;

        for ($lvl = 1; $lvl <= 10; $lvl++) {
            $upline = income_sponsor_of($conn, $currUpline);
            if ($upline === '' || isset($seen[$upline])) {
                break;
            }
            $seen[$upline] = true;
            $currUpline = $upline;

            $pct = $rootRates[$lvl] ?? 0.0;
            $commAmt = round($monthlyPayout * $pct / 100.0, 2);

            // Qualification rule: Root L requires at least L active direct sponsors
            $directsCount = income_active_direct_count($conn, $upline);
            $qualified = ($directsCount >= $lvl);
            $status = $qualified ? 'paid' : 'skipped_insufficient_directs';

            if ($qualified && $commAmt > 0 && function_exists('ind_credit_member_income')) {
                ind_credit_member_income($conn, $upline, "Affiliate Root {$lvl} (M{$monthNo})", $commAmt, [
                    'source' => 'Brand Promotion Affiliate',
                    'source_ref' => "SUB-{$subId}-M{$monthNo}-R{$lvl}",
                    'from_member_id' => $custId,
                    'base_amount' => $monthlyPayout,
                    'percentage' => $pct
                ]);
                $out['affiliates_paid']++;
                $out['total_affiliate_payout'] += $commAmt;
                $affiliatesThisSub++;
            }

            // Log in ind_bp_root_payouts
            $insRoot = $conn->prepare("INSERT INTO ind_bp_root_payouts
                (subscription_id, subscriber_id, earner_id, root_level, month_number, percent,
                 base_monthly_amount, commission_amount, directs_count, status, period_key, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $insRoot->bind_param('issiidddiss', $subId, $custId, $upline, $lvl, $monthNo, $pct, $monthlyPayout, $commAmt, $directsCount, $status, $periodKey);
            $insRoot->execute();
            $insRoot->close();
        }

        // 3. Update subscription record
        $newMonthsPaid = $monthsPaid + 1;
        $newTotalPaid = round((float)$sub['total_paid'] + $monthlyPayout, 2);
        $nextDate = date('Y-m-d', strtotime('+1 month'));

        if ($newMonthsPaid >= $duration) {
            $conn->query("UPDATE ind_bp_subscriptions 
                          SET months_paid = {$newMonthsPaid}, total_paid = {$newTotalPaid}, status = 'completed', next_payout_date = NULL 
                          WHERE id = {$subId}");
        } else {
            $conn->query("UPDATE ind_bp_subscriptions 
                          SET months_paid = {$newMonthsPaid}, total_paid = {$newTotalPaid}, next_payout_date = '{$nextDate}' 
                          WHERE id = {$subId}");
        }

        $out['details'][] = [
            'subscription_id' => $subId,
            'customer_id' => $custId,
            'package_name' => $pkgName,
            'month_number' => $monthNo,
            'payout_amount' => $monthlyPayout,
            'affiliates_paid' => $affiliatesThisSub,
            'is_completed' => ($newMonthsPaid >= $duration)
        ];
    }

    return $out;
}
