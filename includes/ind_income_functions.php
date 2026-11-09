<?php
/**
 * ===========================================================================
 * IND MEMBER BUSINESS PLAN — CORE BUSINESS ENGINE
 * ===========================================================================
 * Modules:
 *   1. Joining Voucher (₹10,000) & Cashback Eligibility (₹10,000, 10 Months Validity)
 *   2. 10-Level One-Time Affiliate Marketing Income (8.6% = ₹860 on ₹10k)
 *   3. Product Price ₹1,000 Addon Distribution (Pool 1: 300, Direct: 100, Team: 100, Training: 500)
 *   4. 3x1 Auto-Pool Engine (100 Pools, Auto Re-topup, Auto Upgrade, Payout)
 *   5. Electric Scooter Brand Promotion Module (50% to 100% ladder for 36 months)
 *   6. 10-Level Team Promotion Bonus (83% of Monthly BPR for 6 Months)
 *   7. Video Verification with Live GPS & 48-Hour Permanent Auto-Deletion
 *   8. Dual Wallet System (50% Scooter Wallet / 50% Main Balance) & Bank 10% Deduction
 *   9. Award & Reward Pool (2.5% Turnover)
 * ===========================================================================
 */

if (!defined('IND_ENGINE_LOADED')) {
    define('IND_ENGINE_LOADED', true);
}

/**
 * Dual Wallet Credit Engine:
 * Credits income with a 50:50 split (50% to Scooter Wallet, 50% to Main Balance).
 */
if (!function_exists('ind_credit_member_income')) {
    function ind_credit_member_income(mysqli $conn, string $memberId, string $incomeType, float $gross, array $opts = []): array
    {
        $out = [
            'ok' => false,
            'gross' => round($gross, 2),
            'scooter_wallet' => 0.0,
            'main_wallet' => 0.0,
            'wallet_after' => 0.0,
            'error' => ''
        ];

        try {
            $gross = round($gross, 2);
            if ($gross <= 0 || empty($memberId)) {
                $out['error'] = 'Invalid member or amount';
                return $out;
            }

            $scooterPct = (float)income_setting_num($conn, 'ind_scooter_wallet_percent', 50.0);
            $scooterAmount = round($gross * $scooterPct / 100.0, 2);
            $mainAmount = round($gross - $scooterAmount, 2);

            // Fetch current balances
            $stmt = $conn->prepare("SELECT wallet_balance, scooter_wallet_balance FROM signup_users WHERE customer_id = ? LIMIT 1");
            $stmt->bind_param('s', $memberId);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$user) {
                $out['error'] = 'Member not found';
                return $out;
            }

            $mainBefore = (float)($user['wallet_balance'] ?? 0.0);
            $scooterBefore = (float)($user['scooter_wallet_balance'] ?? 0.0);

            $mainAfter = round($mainBefore + $mainAmount, 2);
            $scooterAfter = round($scooterBefore + $scooterAmount, 2);

            // Update user wallets
            $upd = $conn->prepare("UPDATE signup_users 
                                   SET wallet_balance = ?, 
                                       scooter_wallet_balance = ?,
                                       product_fund_balance = product_fund_balance + ?
                                   WHERE customer_id = ?");
            $upd->bind_param('ddds', $mainAfter, $scooterAfter, $scooterAmount, $memberId);
            $upd->execute();
            $upd->close();

            // Record in member_income_ledger
            $source = (string)($opts['source'] ?? 'IND Plan');
            $sourceRef = (string)($opts['source_ref'] ?? '');
            $fromId = (string)($opts['from_member_id'] ?? '');
            $fromName = (string)($opts['from_member_name'] ?? '');
            $baseAmount = (float)($opts['base_amount'] ?? $gross);
            $percentage = (float)($opts['percentage'] ?? 0.0);
            $reportDate = (string)($opts['report_date'] ?? date('Y-m-d'));
            $cycleKey = date('Y-m');

            if (income_table_exists($conn, 'member_income_ledger')) {
                $ins = $conn->prepare("INSERT INTO member_income_ledger
                    (customer_id, income_type, source, source_ref, from_member_id, from_member_name,
                     report_date, cycle_key, counts_to_cap, base_amount, percentage,
                     gross_amount, paid_amount, flushed_amount, fund_amount, withdrawable_amount,
                     wallet_balance_after, status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, 0, ?, ?, ?, 'Credit', NOW())");
                $ins->bind_param(
                    'ssssssssddddddd',
                    $memberId,
                    $incomeType,
                    $source,
                    $sourceRef,
                    $fromId,
                    $fromName,
                    $reportDate,
                    $cycleKey,
                    $baseAmount,
                    $percentage,
                    $gross,
                    $gross,
                    $scooterAmount,
                    $mainAmount,
                    $mainAfter
                );
                $ins->execute();
                $ins->close();
            }

            $out['ok'] = true;
            $out['scooter_wallet'] = $scooterAmount;
            $out['main_wallet'] = $mainAmount;
            $out['wallet_after'] = $mainAfter;
            return $out;
        } catch (\Throwable $e) {
            $out['error'] = $e->getMessage();
            return $out;
        }
    }
}

/**
 * 1. Joining Voucher & Cashback Generator
 * Member receives ₹10,000 Product Voucher (10 months validity) & ₹10,000 Cashback Limit
 */
if (!function_exists('ind_process_joining_voucher')) {
    function ind_process_joining_voucher(mysqli $conn, string $memberId, float $joiningAmount = 10000.00): array
    {
        try {
            $voucherAmt = (float)income_setting_num($conn, 'ind_voucher_amount', 10000.00);
            $validityMonths = (int)income_setting_num($conn, 'ind_voucher_validity_months', 10);
            $cashbackLimit = (float)income_setting_num($conn, 'ind_cashback_limit', 10000.00);

            // Check if voucher already created for this member
            $chk = $conn->prepare("SELECT id, voucher_code FROM ind_vouchers WHERE customer_id = ? LIMIT 1");
            $chk->bind_param('s', $memberId);
            $chk->execute();
            $existing = $chk->get_result()->fetch_assoc();
            $chk->close();

            if ($existing) {
                return ['ok' => true, 'status' => 'already_issued', 'voucher_code' => $existing['voucher_code']];
            }

            // Generate unique voucher code
            $voucherCode = 'VCH-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid($memberId, true)), 0, 6));

            $ins = $conn->prepare("INSERT INTO ind_vouchers 
                (voucher_code, customer_id, amount, used_amount, balance_amount, validity_months, expires_at, status, created_at)
                VALUES (?, ?, ?, 0.00, ?, ?, DATE_ADD(NOW(), INTERVAL ? MONTH), 'active', NOW())");
            $ins->bind_param('ssddii', $voucherCode, $memberId, $voucherAmt, $voucherAmt, $validityMonths, $validityMonths);
            $ins->execute();
            $ins->close();

            // Set cashback limits on user record
            $upd = $conn->prepare("UPDATE signup_users 
                                   SET cashback_max_limit = ?, cashback_claimed_total = 0.00 
                                   WHERE customer_id = ?");
            $upd->bind_param('ds', $cashbackLimit, $memberId);
            $upd->execute();
            $upd->close();

            return [
                'ok' => true,
                'status' => 'issued',
                'voucher_code' => $voucherCode,
                'amount' => $voucherAmt,
                'cashback_limit' => $cashbackLimit,
                'validity_months' => $validityMonths
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}

/**
 * 2. 10-Level One-Time Affiliate Marketing Income (Triggered on ₹10,000 Activation)
 * Percentages: Root 1: 5.0%, Root 2: 0.8%, Root 3-5: 0.5% each, Root 6-7: 0.3% each, Root 8-9: 0.2% each, Root 10: 0.3% (Total 8.6% = ₹860)
 * Sponsor Rule: Earner must have at least $level direct active sponsors to unlock that level.
 */
if (!function_exists('ind_distribute_affiliate_10level')) {
    function ind_distribute_affiliate_10level(mysqli $conn, string $newMemberId, float $joiningAmount = 10000.00): array
    {
        $rates = [
            1  => 5.0,  // Direct (5.0% = ₹500)
            2  => 0.8,  // Root 2 (0.8% = ₹80)
            3  => 0.5,  // Root 3 (0.5% = ₹50)
            4  => 0.5,  // Root 4 (0.5% = ₹50)
            5  => 0.5,  // Root 5 (0.5% = ₹50)
            6  => 0.3,  // Root 6 (0.3% = ₹30)
            7  => 0.3,  // Root 7 (0.3% = ₹30)
            8  => 0.2,  // Root 8 (0.2% = ₹20)
            9  => 0.2,  // Root 9 (0.2% = ₹20)
            10 => 0.3   // Root 10 (0.3% = ₹30) -> Total = 8.6% (₹860)
        ];

        $results = [];
        $current = $newMemberId;
        $seen = [$newMemberId => true];

        try {
            // Get new member name
            $st = $conn->prepare("SELECT full_name FROM signup_users WHERE customer_id = ? LIMIT 1");
            $st->bind_param('s', $newMemberId);
            $st->execute();
            $row = $st->get_result()->fetch_assoc();
            $st->close();
            $newMemberName = (string)($row['full_name'] ?? '');

            for ($lvl = 1; $lvl <= 10; $lvl++) {
                $upline = income_sponsor_of($conn, $current);
                if ($upline === '' || isset($seen[$upline])) {
                    break; // Chain ended or loop guard
                }
                $seen[$upline] = true;
                $current = $upline;

                $pct = $rates[$lvl] ?? 0.0;
                $gross = round($joiningAmount * $pct / 100.0, 2);

                // Count upline's active direct sponsors
                $directsCount = income_active_direct_count($conn, $upline);

                // Check direct sponsor unlock condition
                // Root L requires at least L active direct sponsors
                $qualified = ($directsCount >= $lvl);
                $status = $qualified ? 'paid' : 'skipped_insufficient_directs';

                $scooterAmt = 0.0;
                $mainAmt = 0.0;

                if ($qualified && $gross > 0) {
                    $creditRes = ind_credit_member_income($conn, $upline, "Affiliate Level {$lvl}", $gross, [
                        'source' => 'Affiliate 10-Level',
                        'source_ref' => $newMemberId,
                        'from_member_id' => $newMemberId,
                        'from_member_name' => $newMemberName,
                        'base_amount' => $joiningAmount,
                        'percentage' => $pct
                    ]);
                    $scooterAmt = $creditRes['scooter_wallet'] ?? 0.0;
                    $mainAmt = $creditRes['main_wallet'] ?? 0.0;
                }

                // Log into ind_affiliate_10level_logs
                $ins = $conn->prepare("INSERT INTO ind_affiliate_10level_logs
                    (new_member_id, earner_id, root_level, percent, base_amount, gross_amount,
                     scooter_wallet_amount, main_balance_amount, directs_count, status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $ins->bind_param('ssisddddis', $newMemberId, $upline, $lvl, $pct, $joiningAmount, $gross, $scooterAmt, $mainAmt, $directsCount, $status);
                $ins->execute();
                $ins->close();

                $results[] = [
                    'root_level' => $lvl,
                    'earner' => $upline,
                    'percent' => $pct,
                    'gross' => $gross,
                    'directs' => $directsCount,
                    'status' => $status
                ];
            }

            return ['ok' => true, 'distributed' => $results];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}

/**
 * 3. Product Price ₹1,000 Add-on Distribution
 * Splits:
 *   ₹300 -> Auto Pool 1 Entry
 *   ₹100 -> Direct Performance Pool
 *   ₹100 -> Team Performance Pool
 *   ₹500 -> Training Wallet
 */
if (!function_exists('ind_process_product_addon')) {
    function ind_process_product_addon(mysqli $conn, string $orderId, string $customerId, float $addonAmount = 1000.00): array
    {
        try {
            $pool1Amount = (float)income_setting_num($conn, 'ind_addon_autopool_amount', 300.00);
            $directPoolAmount = (float)income_setting_num($conn, 'ind_addon_direct_perf_amount', 100.00);
            $teamPoolAmount = (float)income_setting_num($conn, 'ind_addon_team_perf_amount', 100.00);
            $trainingAmount = (float)income_setting_num($conn, 'ind_addon_training_amount', 500.00);

            // 1. Enter member into Auto Pool 1
            $autoPoolPosId = ind_auto_pool_enter($conn, $customerId, 1, 'product_addon');

            // 2. Add to Training Wallet
            $stmt = $conn->query("SELECT balance_after FROM ind_training_wallet_ledger ORDER BY id DESC LIMIT 1");
            $lastRow = $stmt ? $stmt->fetch_assoc() : null;
            $currentTrainingBal = (float)($lastRow['balance_after'] ?? 0.0);
            $newTrainingBal = round($currentTrainingBal + $trainingAmount, 2);

            $insTrain = $conn->prepare("INSERT INTO ind_training_wallet_ledger 
                (direction, amount, source, ref_id, description, balance_after, created_at)
                VALUES ('credit', ?, 'Product Addon', ?, 'Addon allocation from order', ?, NOW())");
            $insTrain->bind_param('dsd', $trainingAmount, $orderId, $newTrainingBal);
            $insTrain->execute();
            $insTrain->close();

            // 3. Contribute to Direct and Team Performance Pools
            $sponsorId = income_sponsor_of($conn, $customerId);
            if ($sponsorId !== '' && function_exists('perf_bonus_contribute')) {
                perf_bonus_contribute($conn, $sponsorId, $customerId);
            }
            if (function_exists('team_business_record')) {
                team_business_record($conn, $customerId, $addonAmount);
            }

            // 4. Log addon split
            $insSplit = $conn->prepare("INSERT INTO ind_product_addon_splits
                (order_id, customer_id, addon_amount, auto_pool_amount, direct_pool_amount, team_pool_amount, training_wallet_amount, auto_pool_position_id, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $insSplit->bind_param('ssdddddi', $orderId, $customerId, $addonAmount, $pool1Amount, $directPoolAmount, $teamPoolAmount, $trainingAmount, $autoPoolPosId);
            $insSplit->execute();
            $insSplit->close();

            return [
                'ok' => true,
                'auto_pool_position_id' => $autoPoolPosId,
                'training_wallet_balance' => $newTrainingBal
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}

/**
 * 4. 3x1 Auto-Pool Architecture (100 Pools Progression)
 * When 3 positions enter under a head position:
 *   - Part 1: Auto Re-topup in same pool (creates new entry in same pool)
 *   - Part 2: Upgrade to next pool (creates new entry in pool + 1)
 *   - Part 3: Withdrawal payout to member (50% Scooter Wallet / 50% Main Balance)
 */
if (!function_exists('ind_auto_pool_enter')) {
    function ind_auto_pool_enter(mysqli $conn, string $customerId, int $poolLevel = 1, string $source = 'product_addon', ?int $fromPosId = null): ?int
    {
        try {
            if ($poolLevel < 1 || $poolLevel > 100 || empty($customerId)) {
                return null;
            }

            // Get pool parameters
            $st = $conn->prepare("SELECT entry_amount, entries_count FROM ind_auto_pool_state WHERE pool_level = ? LIMIT 1");
            $st->bind_param('i', $poolLevel);
            $st->execute();
            $state = $st->get_result()->fetch_assoc();
            $st->close();

            if (!$state) {
                return null;
            }

            $entryAmt = (float)$state['entry_amount'];

            // Increment entries counter
            $conn->query("UPDATE ind_auto_pool_state SET entries_count = entries_count + 1 WHERE pool_level = " . (int)$poolLevel);
            $qRes = $conn->query("SELECT entries_count FROM ind_auto_pool_state WHERE pool_level = " . (int)$poolLevel)->fetch_assoc();
            $queueNo = (int)($qRes['entries_count'] ?? 1);

            // Insert new position
            $ins = $conn->prepare("INSERT INTO ind_auto_pool_positions
                (customer_id, pool_level, queue_no, entry_amount, entry_source, from_position_id, child_count, status, joined_at)
                VALUES (?, ?, ?, ?, ?, ?, 0, 'waiting', NOW())");
            $ins->bind_param('siidsi', $customerId, $poolLevel, $queueNo, $entryAmt, $source, $fromPosId);
            $ins->execute();
            $newPosId = (int)$ins->insert_id;
            $ins->close();

            // Check if this newly entered position satisfies a waiting position in this pool
            ind_auto_pool_step($conn, $poolLevel);

            return $newPosId;
        } catch (\Throwable $e) {
            error_log("ind_auto_pool_enter error: " . $e->getMessage());
            return null;
        }
    }
}

/**
 * 3x1 Completion Step Engine
 */
if (!function_exists('ind_auto_pool_step')) {
    function ind_auto_pool_step(mysqli $conn, int $poolLevel): void
    {
        $guard = 0;
        while ($guard++ < 500) {
            // Find the oldest waiting position in this pool that has child_count < 3
            $st = $conn->prepare("SELECT id, customer_id, child_count FROM ind_auto_pool_positions 
                                  WHERE pool_level = ? AND status = 'waiting' 
                                  ORDER BY queue_no ASC, id ASC LIMIT 1");
            $st->bind_param('i', $poolLevel);
            $st->execute();
            $head = $st->get_result()->fetch_assoc();
            $st->close();

            if (!$head) {
                break; // No waiting position
            }

            $headId = (int)$head['id'];
            $headCust = (string)$head['customer_id'];
            $currentChildren = (int)$head['child_count'];

            // Count available unattached waiting positions behind the head
            $cntQuery = $conn->prepare("SELECT COUNT(*) as unattached FROM ind_auto_pool_positions 
                                        WHERE pool_level = ? AND id > ? AND status = 'waiting'");
            $cntQuery->bind_param('ii', $poolLevel, $headId);
            $cntQuery->execute();
            $unattached = (int)($cntQuery->get_result()->fetch_assoc()['unattached'] ?? 0);
            $cntQuery->close();

            // Total children this head can claim right now
            $needed = 3 - $currentChildren;
            if ($unattached < $needed) {
                // Not enough IDs yet to complete this position, update partial child_count and exit
                if ($unattached > 0 && $unattached != $currentChildren) {
                    $newChildren = min(3, $currentChildren + $unattached);
                    $upd = $conn->prepare("UPDATE ind_auto_pool_positions SET child_count = ? WHERE id = ?");
                    $upd->bind_param('ii', $newChildren, $headId);
                    $upd->execute();
                    $upd->close();
                }
                break;
            }

            // 3 IDs are present! COMPLETE THIS POSITION
            // Fetch pool config
            $cfgSt = $conn->prepare("SELECT entry_amount, retopup_amount, upgrade_amount, withdrawal_gross 
                                     FROM ind_auto_pool_state WHERE pool_level = ? LIMIT 1");
            $cfgSt->bind_param('i', $poolLevel);
            $cfgSt->execute();
            $poolCfg = $cfgSt->get_result()->fetch_assoc();
            $cfgSt->close();

            $entryAmt = (float)$poolCfg['entry_amount'];
            $retopupAmt = (float)$poolCfg['retopup_amount'];
            $upgradeAmt = (float)$poolCfg['upgrade_amount'];
            $grossWithdrawal = (float)$poolCfg['withdrawal_gross'];
            $totalCollected = round($entryAmt * 3, 2);

            // Mark head position as completed
            $updHead = $conn->prepare("UPDATE ind_auto_pool_positions 
                                       SET child_count = 3, status = 'completed', completed_at = NOW() 
                                       WHERE id = ?");
            $updHead->bind_param('i', $headId);
            $updHead->execute();
            $updHead->close();

            // Increment completed count in state
            $conn->query("UPDATE ind_auto_pool_state SET completed_count = completed_count + 1 WHERE pool_level = " . (int)$poolLevel);

            // 1. Same Pool Auto Re-Topup
            $retopupPosId = null;
            if ($retopupAmt > 0) {
                // Insert re-topup entry for same member
                $conn->query("UPDATE ind_auto_pool_state SET entries_count = entries_count + 1 WHERE pool_level = " . (int)$poolLevel);
                $qRes = $conn->query("SELECT entries_count FROM ind_auto_pool_state WHERE pool_level = " . (int)$poolLevel)->fetch_assoc();
                $reQueueNo = (int)($qRes['entries_count'] ?? 1);

                $insRe = $conn->prepare("INSERT INTO ind_auto_pool_positions
                    (customer_id, pool_level, queue_no, entry_amount, entry_source, from_position_id, child_count, status, joined_at)
                    VALUES (?, ?, ?, ?, 'retopup', ?, 0, 'waiting', NOW())");
                $insRe->bind_param('siidi', $headCust, $poolLevel, $reQueueNo, $retopupAmt, $headId);
                $insRe->execute();
                $retopupPosId = (int)$insRe->insert_id;
                $insRe->close();
            }

            // 2. Next Pool Upgrade Entry (up to Pool 100)
            $upgradePosId = null;
            if ($poolLevel < 100 && $upgradeAmt > 0) {
                $nextPool = $poolLevel + 1;
                $conn->query("UPDATE ind_auto_pool_state SET entries_count = entries_count + 1 WHERE pool_level = " . (int)$nextPool);
                $qResNext = $conn->query("SELECT entries_count FROM ind_auto_pool_state WHERE pool_level = " . (int)$nextPool)->fetch_assoc();
                $nextQueueNo = (int)($qResNext['entries_count'] ?? 1);

                $insUp = $conn->prepare("INSERT INTO ind_auto_pool_positions
                    (customer_id, pool_level, queue_no, entry_amount, entry_source, from_position_id, child_count, status, joined_at)
                    VALUES (?, ?, ?, ?, 'upgrade', ?, 0, 'waiting', NOW())");
                $insUp->bind_param('siidi', $headCust, $nextPool, $nextQueueNo, $upgradeAmt, $headId);
                $insUp->execute();
                $upgradePosId = (int)$insUp->insert_id;
                $insUp->close();

                // Recursively check next pool for completion
                ind_auto_pool_step($conn, $nextPool);
            }

            // 3. Member Withdrawal Income Payout (50% Scooter Wallet / 50% Main Balance)
            $scooterCredit = 0.0;
            $mainCredit = 0.0;
            if ($grossWithdrawal > 0) {
                $creditRes = ind_credit_member_income($conn, $headCust, "Auto Pool {$poolLevel}", $grossWithdrawal, [
                    'source' => 'Auto Pool',
                    'source_ref' => "AP-L{$poolLevel}-#{$headId}",
                    'base_amount' => $grossWithdrawal
                ]);
                $scooterCredit = $creditRes['scooter_wallet'] ?? 0.0;
                $mainCredit = $creditRes['main_wallet'] ?? 0.0;
            }

            // Record in ind_auto_pool_ledger
            $insLgr = $conn->prepare("INSERT INTO ind_auto_pool_ledger
                (position_id, customer_id, pool_level, total_collected, retopup_amount, retopup_position_id,
                 upgrade_amount, upgrade_position_id, gross_income, scooter_wallet_credit, main_wallet_credit, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $insLgr->bind_param('isidddidddd', $headId, $headCust, $poolLevel, $totalCollected, $retopupAmt, $retopupPosId, $upgradeAmt, $upgradePosId, $grossWithdrawal, $scooterCredit, $mainCredit);
            $insLgr->execute();
            $insLgr->close();
        }
    }
}

/**
 * 5. Scooter Brand Promotion Escalation Ladder
 * Support starts at 50%, every 2 direct vehicles adds +10% up to 100%
 */
if (!function_exists('ind_calculate_scooter_support_percent')) {
    function ind_calculate_scooter_support_percent(int $directVehiclesCount): float
    {
        if ($directVehiclesCount < 2) {
            return 50.0;
        } elseif ($directVehiclesCount < 4) {
            return 60.0;
        } elseif ($directVehiclesCount < 6) {
            return 70.0;
        } elseif ($directVehiclesCount < 8) {
            return 80.0;
        } elseif ($directVehiclesCount < 10) {
            return 90.0;
        } else {
            return 100.0; // 100% Free Scooter
        }
    }
}

/**
 * Update member's direct vehicle count and recalibrate support percent
 */
if (!function_exists('ind_refresh_scooter_support')) {
    function ind_refresh_scooter_support(mysqli $conn, string $customerId): float
    {
        try {
            // Count active scooter promotions among direct referrals
            $stmt = $conn->prepare("SELECT COUNT(DISTINCT sp.id) as vehicle_count 
                                    FROM ind_scooter_promotions sp
                                    JOIN signup_users su ON su.customer_id = sp.customer_id
                                    WHERE su.referral_id = ? AND sp.status = 'active'");
            $stmt->bind_param('s', $customerId);
            $stmt->execute();
            $count = (int)($stmt->get_result()->fetch_assoc()['vehicle_count'] ?? 0);
            $stmt->close();

            $supportPct = ind_calculate_scooter_support_percent($count);

            // Update signup_users and any active promotions
            $updUser = $conn->prepare("UPDATE signup_users SET direct_vehicles_count = ? WHERE customer_id = ?");
            $updUser->bind_param('is', $count, $customerId);
            $updUser->execute();
            $updUser->close();

            $updPromo = $conn->prepare("UPDATE ind_scooter_promotions 
                                        SET direct_vehicles_count = ?, support_percent = ? 
                                        WHERE customer_id = ? AND status = 'active'");
            $updPromo->bind_param('ids', $count, $supportPct, $customerId);
            $updPromo->execute();
            $updPromo->close();

            return $supportPct;
        } catch (\Throwable $e) {
            return 50.0;
        }
    }
}

/**
 * 6. 10-Level Team Promotion Bonus (6 Months BPR Commission)
 * On each monthly BPR release, distribute 83% across 10 roots:
 * Root 1: 30%, Root 2: 15%, Root 3: 10%, Root 4: 5%, Root 5-7: 4%, Root 8-9: 3%, Root 10: 5%
 */
if (!function_exists('ind_distribute_team_bpr_bonus')) {
    function ind_distribute_team_bpr_bonus(mysqli $conn, int $promotionId, string $periodKey): array
    {
        $teamBprRates = [
            1 => 30.0,
            2 => 15.0,
            3 => 10.0,
            4 => 5.0,
            5 => 4.0,
            6 => 4.0,
            7 => 4.0,
            8 => 3.0,
            9 => 3.0,
            10 => 5.0
        ];

        try {
            $st = $conn->prepare("SELECT customer_id, monthly_bpr_amount, months_paid FROM ind_scooter_promotions WHERE id = ? LIMIT 1");
            $st->bind_param('i', $promotionId);
            $st->execute();
            $promo = $st->get_result()->fetch_assoc();
            $st->close();

            if (!$promo) {
                return ['ok' => false, 'error' => 'Promotion not found'];
            }

            $downlineMember = (string)$promo['customer_id'];
            $bprAmount = (float)$promo['monthly_bpr_amount'];
            $monthIndex = ((int)$promo['months_paid']) + 1; // 1 to 36

            // Team BPR bonus is paid for the first 6 months
            if ($monthIndex > 6) {
                return ['ok' => true, 'status' => 'past_6_months'];
            }

            $current = $downlineMember;
            $seen = [$downlineMember => true];
            $distributed = [];

            for ($lvl = 1; $lvl <= 10; $lvl++) {
                $upline = income_sponsor_of($conn, $current);
                if ($upline === '' || isset($seen[$upline])) {
                    break;
                }
                $seen[$upline] = true;
                $current = $upline;

                $pct = $teamBprRates[$lvl] ?? 0.0;
                $bonusGross = round($bprAmount * $pct / 100.0, 2);

                if ($bonusGross <= 0) {
                    continue;
                }

                // Check idempotency for this promotion + month + root
                $chk = $conn->prepare("SELECT id FROM ind_team_promotion_bonus_logs 
                                       WHERE promotion_id = ? AND earner_id = ? AND month_index = ? LIMIT 1");
                $chk->bind_param('isi', $promotionId, $upline, $monthIndex);
                $chk->execute();
                $already = $chk->get_result()->fetch_row();
                $chk->close();
                if ($already) {
                    continue;
                }

                $creditRes = ind_credit_member_income($conn, $upline, "Team Promotion Bonus L{$lvl}", $bonusGross, [
                    'source' => 'Team Promotion Bonus',
                    'source_ref' => "SP-#{$promotionId}-M{$monthIndex}",
                    'from_member_id' => $downlineMember,
                    'base_amount' => $bprAmount,
                    'percentage' => $pct
                ]);

                $scooterAmt = $creditRes['scooter_wallet'] ?? 0.0;
                $mainAmt = $creditRes['main_wallet'] ?? 0.0;

                $insLog = $conn->prepare("INSERT INTO ind_team_promotion_bonus_logs
                    (promotion_id, downline_member_id, earner_id, root_level, percent, month_index,
                     bpr_base_amount, bonus_amount, scooter_wallet_amount, main_balance_amount, period_key, status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'paid', NOW())");
                $insLog->bind_param('issidiiddds', $promotionId, $downlineMember, $upline, $lvl, $pct, $monthIndex, $bprAmount, $bonusGross, $scooterAmt, $mainAmt, $periodKey);
                $insLog->execute();
                $insLog->close();

                $distributed[] = ['root' => $lvl, 'earner' => $upline, 'bonus' => $bonusGross];
            }

            return ['ok' => true, 'distributed' => $distributed];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}

/**
 * 7. 48-Hour Permanent Auto-Delete Video Engine
 * Permanently unlinks video files older than 48 hours to save server disk storage.
 */
if (!function_exists('ind_purge_expired_videos')) {
    function ind_purge_expired_videos(mysqli $conn, string $baseUploadDir = ''): array
    {
        $deletedCount = 0;
        $freedBytes = 0;

        try {
            $st = $conn->query("SELECT id, video_file_path FROM ind_video_verifications 
                                WHERE video_deleted = 0 
                                AND created_at <= DATE_SUB(NOW(), INTERVAL 48 HOUR)");

            while ($row = $st->fetch_assoc()) {
                $vidId = (int)$row['id'];
                $relPath = (string)$row['video_file_path'];

                if (!empty($relPath)) {
                    $fullPath = $relPath;
                    if (!empty($baseUploadDir) && !file_exists($fullPath)) {
                        $fullPath = rtrim($baseUploadDir, '/\\') . DIRECTORY_SEPARATOR . ltrim($relPath, '/\\');
                    }

                    if (file_exists($fullPath) && is_file($fullPath)) {
                        $freedBytes += filesize($fullPath);
                        @unlink($fullPath);
                    }
                }

                $upd = $conn->prepare("UPDATE ind_video_verifications 
                                       SET video_deleted = 1, video_deleted_at = NOW() 
                                       WHERE id = ?");
                $upd->bind_param('i', $vidId);
                $upd->execute();
                $upd->close();

                $deletedCount++;
            }

            return ['ok' => true, 'purged_count' => $deletedCount, 'freed_bytes' => $freedBytes];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}

/**
 * 8. Award & Reward 2.5% Pool Accumulator
 */
if (!function_exists('ind_record_award_reward_turnover')) {
    function ind_record_award_reward_turnover(mysqli $conn, float $salesAmount): void
    {
        if ($salesAmount <= 0) return;

        $periodKey = date('Y-m');
        $poolPct = (float)income_setting_num($conn, 'ind_award_reward_turnover_percent', 2.50);
        $poolContrib = round($salesAmount * $poolPct / 100.0, 2);

        $st = $conn->prepare("INSERT INTO ind_award_reward_pool 
            (period_key, sales_turnover, pool_percent, pool_amount, balance, status, created_at)
            VALUES (?, ?, ?, ?, ?, 'open', NOW())
            ON DUPLICATE KEY UPDATE 
            sales_turnover = sales_turnover + VALUES(sales_turnover),
            pool_amount = pool_amount + VALUES(pool_amount),
            balance = balance + VALUES(pool_amount)");
        $st->bind_param('sdddd', $periodKey, $salesAmount, $poolPct, $poolContrib, $poolContrib);
        $st->execute();
        $st->close();
    }
}

/**
 * Get Award & Reward Pool Status for a given period
 */
if (!function_exists('ind_get_award_pool_status')) {
    function ind_get_award_pool_status(mysqli $conn, ?string $periodKey = null): array
    {
        $periodKey = $periodKey ?: date('Y-m');
        $poolPct = (float)income_setting_num($conn, 'ind_award_reward_turnover_percent', 2.50);

        $st = $conn->prepare("SELECT * FROM ind_award_reward_pool WHERE period_key = ? LIMIT 1");
        $st->bind_param('s', $periodKey);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();

        if (!$row) {
            return [
                'id' => 0,
                'period_key' => $periodKey,
                'sales_turnover' => 0.0,
                'pool_percent' => $poolPct,
                'pool_amount' => 0.0,
                'distributed_amount' => 0.0,
                'balance' => 0.0,
                'status' => 'open'
            ];
        }

        return [
            'id' => (int)$row['id'],
            'period_key' => $row['period_key'],
            'sales_turnover' => (float)$row['sales_turnover'],
            'pool_percent' => (float)$row['pool_percent'],
            'pool_amount' => (float)$row['pool_amount'],
            'distributed_amount' => (float)$row['distributed_amount'],
            'balance' => (float)$row['balance'],
            'status' => $row['status']
        ];
    }
}

/**
 * Distribute Award & Reward Pool to selected achievers
 * Each achiever receives their reward with 50% to Scooter Wallet and 50% to Main Balance.
 */
if (!function_exists('ind_distribute_award_reward')) {
    function ind_distribute_award_reward(mysqli $conn, string $periodKey, array $achievers, string $adminName = '', string $adminNotes = ''): array
    {
        try {
            $pool = ind_get_award_pool_status($conn, $periodKey);
            $poolId = (int)($pool['id'] ?? 0);

            $totalDistribute = 0.0;
            foreach ($achievers as $item) {
                $totalDistribute += round((float)($item['amount'] ?? 0), 2);
            }

            if ($totalDistribute <= 0) {
                return ['ok' => false, 'error' => 'No reward amounts specified'];
            }

            $distributed = [];

            foreach ($achievers as $item) {
                $cid = trim((string)($item['customer_id'] ?? ''));
                $amt = round((float)($item['amount'] ?? 0), 2);
                $note = trim((string)($item['note'] ?? $adminNotes));

                if (empty($cid) || $amt <= 0) {
                    continue;
                }

                // Credit member income (50% Scooter Wallet / 50% Main Balance)
                $creditRes = ind_credit_member_income($conn, $cid, 'Award & Reward', $amt, [
                    'source' => 'Award & Reward Pool',
                    'source_ref' => "AWARD-{$periodKey}",
                    'base_amount' => $amt
                ]);

                $scooterAmt = (float)($creditRes['scooter_wallet'] ?? 0);
                $mainAmt = (float)($creditRes['main_wallet'] ?? 0);

                // Insert into ind_award_reward_distributions
                $ins = $conn->prepare("INSERT INTO ind_award_reward_distributions 
                    (pool_id, period_key, customer_id, reward_amount, scooter_wallet_amount, main_balance_amount, awarded_by, notes, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $ins->bind_param('issdddss', $poolId, $periodKey, $cid, $amt, $scooterAmt, $mainAmt, $adminName, $note);
                $ins->execute();
                $ins->close();

                $distributed[] = [
                    'customer_id' => $cid,
                    'amount' => $amt,
                    'scooter_wallet' => $scooterAmt,
                    'main_wallet' => $mainAmt
                ];
            }

            // Update pool record
            if ($poolId > 0) {
                $conn->query("UPDATE ind_award_reward_pool 
                              SET distributed_amount = distributed_amount + {$totalDistribute},
                                  balance = balance - {$totalDistribute},
                                  status = 'distributed'
                              WHERE id = {$poolId}");
            }

            return [
                'ok' => true,
                'total_distributed' => $totalDistribute,
                'count' => count($distributed),
                'distributed' => $distributed
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}

/**
 * 9. Electric Vehicle Catalog & Dealership Helpers
 */
if (!function_exists('ind_get_active_vehicles')) {
    function ind_get_active_vehicles(mysqli $conn, ?string $category = null, bool $onlyBpr = false): array
    {
        $vehicles = [];
        try {
            $sql = "SELECT * FROM ind_electric_vehicles WHERE status = 'active'";
            $params = [];
            $types = '';

            if ($category !== null && $category !== '') {
                $sql .= " AND category = ?";
                $params[] = $category;
                $types .= 's';
            }
            if ($onlyBpr) {
                $sql .= " AND has_bpr = 1";
            }
            $sql .= " ORDER BY sort_order ASC, id ASC";

            $stmt = $conn->prepare($sql);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $vehicles[] = $row;
            }
            $stmt->close();
        } catch (\Throwable $e) {
            error_log("ind_get_active_vehicles error: " . $e->getMessage());
        }
        return $vehicles;
    }
}

if (!function_exists('ind_get_vehicle_by_code')) {
    function ind_get_vehicle_by_code(mysqli $conn, string $code): ?array
    {
        try {
            $stmt = $conn->prepare("SELECT * FROM ind_electric_vehicles WHERE model_code = ? LIMIT 1");
            $stmt->bind_param('s', $code);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res->fetch_assoc();
            $stmt->close();
            return $row ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('ind_get_vehicle_by_id')) {
    function ind_get_vehicle_by_id(mysqli $conn, int $id): ?array
    {
        try {
            $stmt = $conn->prepare("SELECT * FROM ind_electric_vehicles WHERE id = ? LIMIT 1");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res->fetch_assoc();
            $stmt->close();
            return $row ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('ind_get_dealership_categories')) {
    function ind_get_dealership_categories(mysqli $conn): array
    {
        $categories = [];
        try {
            $res = $conn->query("SELECT * FROM ind_dealership_categories WHERE status = 'active' ORDER BY investment_amount ASC");
            if ($res) {
                while ($r = $res->fetch_assoc()) {
                    $categories[] = $r;
                }
            }
        } catch (\Throwable $e) {
            error_log("ind_get_dealership_categories error: " . $e->getMessage());
        }
        return $categories;
    }
}

/**
 * 10. Electric Vehicle Purchase Commission Engine (PDF Page 11)
 * Triggers on every Electric Vehicle purchase:
 *   - Part A: Direct Referral Commission (5% of vehicle price)
 *   - Part B: 10-Level Team Commission (Total 11%: L1: 3%, L2: 2%, L3: 1.5%, L4: 1%, L5: 1%, L6-10: 0.5% each)
 *   - Part C: Both direct & team commissions are credited 50% to Scooter Wallet & 50% to Main Balance.
 *   - Part D: Updates buyer's direct sponsor's direct_vehicles_count to advance their BPR ladder.
 */
if (!function_exists('ind_distribute_vehicle_commissions')) {
    function ind_distribute_vehicle_commissions(
        mysqli $conn,
        string $buyerId,
        $vehicleIdOrCode,
        string $orderRef = '',
        float $overridePrice = 0.0
    ): array {
        $out = [
            'ok' => false,
            'vehicle_model' => '',
            'vehicle_price' => 0.0,
            'direct_commission' => null,
            'team_commissions' => [],
            'total_distributed' => 0.0,
            'error' => ''
        ];

        try {
            if (empty($buyerId)) {
                $out['error'] = 'Invalid buyer ID';
                return $out;
            }

            // 1. Resolve vehicle details
            $vehicle = null;
            if (is_numeric($vehicleIdOrCode)) {
                $vehicle = ind_get_vehicle_by_id($conn, (int)$vehicleIdOrCode);
            }
            if (!$vehicle && is_string($vehicleIdOrCode)) {
                $vehicle = ind_get_vehicle_by_code($conn, $vehicleIdOrCode);
            }

            $vehicleId = (int)($vehicle['id'] ?? 0);
            $vehicleModel = (string)($vehicle['model_name'] ?? (is_string($vehicleIdOrCode) ? $vehicleIdOrCode : 'Electric Vehicle'));
            $price = $overridePrice > 0 ? $overridePrice : (float)($vehicle['vehicle_price'] ?? 0.0);

            if ($price <= 0) {
                $out['error'] = 'Invalid vehicle price';
                return $out;
            }

            if (empty($orderRef)) {
                $orderRef = 'EV-ORD-' . date('YmdHis') . '-' . substr(md5(uniqid($buyerId, true)), 0, 6);
            }

            $out['vehicle_model'] = $vehicleModel;
            $out['vehicle_price'] = $price;

            // Get buyer name
            $st = $conn->prepare("SELECT full_name, referral_id FROM signup_users WHERE customer_id = ? LIMIT 1");
            $st->bind_param('s', $buyerId);
            $st->execute();
            $buyerRow = $st->get_result()->fetch_assoc();
            $st->close();

            $buyerName = (string)($buyerRow['full_name'] ?? $buyerId);
            $directSponsor = trim((string)($buyerRow['referral_id'] ?? ''));

            $totalDist = 0.0;

            // 2. Part A: Direct Referral Commission (5%)
            $directPct = (float)income_setting_num($conn, 'ind_vehicle_direct_commission_percent', 5.0);
            if (!empty($directSponsor) && $directSponsor !== $buyerId && $directPct > 0) {
                $directGross = round($price * $directPct / 100.0, 2);
                if ($directGross > 0) {
                    $creditRes = ind_credit_member_income($conn, $directSponsor, "Vehicle Direct Commission", $directGross, [
                        'source' => "Vehicle Direct Referral ({$vehicleModel})",
                        'source_ref' => $orderRef,
                        'from_member_id' => $buyerId,
                        'from_member_name' => $buyerName,
                        'base_amount' => $price,
                        'percentage' => $directPct
                    ]);

                    $scooterPart = (float)($creditRes['scooter_wallet'] ?? 0.0);
                    $mainPart = (float)($creditRes['main_wallet'] ?? 0.0);

                    $insDir = $conn->prepare("INSERT INTO ind_vehicle_commissions
                        (order_ref, buyer_id, earner_id, vehicle_id, vehicle_model, vehicle_price,
                         commission_type, level_number, percent, gross_amount, scooter_wallet_amount, main_balance_amount, status, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, 'direct', 0, ?, ?, ?, ?, 'paid', NOW())");
                    $insDir->bind_param(
                        'sssisddddd',
                        $orderRef,
                        $buyerId,
                        $directSponsor,
                        $vehicleId,
                        $vehicleModel,
                        $price,
                        $directPct,
                        $directGross,
                        $scooterPart,
                        $mainPart
                    );
                    $insDir->execute();
                    $insDir->close();

                    $totalDist += $directGross;
                    $out['direct_commission'] = [
                        'earner' => $directSponsor,
                        'percent' => $directPct,
                        'gross' => $directGross,
                        'scooter_wallet' => $scooterPart,
                        'main_balance' => $mainPart
                    ];

                    // Advance sponsor's direct vehicle count & BPR ladder
                    if (function_exists('ind_refresh_scooter_support')) {
                        ind_refresh_scooter_support($conn, $directSponsor);
                    }
                }
            }

            // 3. Part B: 10-Level Team Commission (Total 11.0%)
            $teamRates = [
                1  => 3.0,
                2  => 2.0,
                3  => 1.5,
                4  => 1.0,
                5  => 1.0,
                6  => 0.5,
                7  => 0.5,
                8  => 0.5,
                9  => 0.5,
                10 => 0.5
            ];

            $teamEnabled = income_setting_bool($conn, 'ind_vehicle_team_commission_enabled', true);
            if ($teamEnabled) {
                $current = $buyerId;
                $seen = [$buyerId => true];

                for ($lvl = 1; $lvl <= 10; $lvl++) {
                    $upline = income_sponsor_of($conn, $current);
                    if ($upline === '' || isset($seen[$upline])) {
                        break;
                    }
                    $seen[$upline] = true;
                    $current = $upline;

                    $pct = $teamRates[$lvl] ?? 0.0;
                    if ($pct <= 0) continue;

                    $teamGross = round($price * $pct / 100.0, 2);
                    if ($teamGross <= 0) continue;

                    $creditRes = ind_credit_member_income($conn, $upline, "Vehicle Team Level {$lvl}", $teamGross, [
                        'source' => "Vehicle Team Commission L{$lvl} ({$vehicleModel})",
                        'source_ref' => $orderRef,
                        'from_member_id' => $buyerId,
                        'from_member_name' => $buyerName,
                        'base_amount' => $price,
                        'percentage' => $pct
                    ]);

                    $scooterPart = (float)($creditRes['scooter_wallet'] ?? 0.0);
                    $mainPart = (float)($creditRes['main_wallet'] ?? 0.0);

                    $insTeam = $conn->prepare("INSERT INTO ind_vehicle_commissions
                        (order_ref, buyer_id, earner_id, vehicle_id, vehicle_model, vehicle_price,
                         commission_type, level_number, percent, gross_amount, scooter_wallet_amount, main_balance_amount, status, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, 'team_level', ?, ?, ?, ?, ?, 'paid', NOW())");
                    $insTeam->bind_param(
                        'sssisdidddd',
                        $orderRef,
                        $buyerId,
                        $upline,
                        $vehicleId,
                        $vehicleModel,
                        $price,
                        $lvl,
                        $pct,
                        $teamGross,
                        $scooterPart,
                        $mainPart
                    );
                    $insTeam->execute();
                    $insTeam->close();

                    $totalDist += $teamGross;
                    $out['team_commissions'][] = [
                        'level' => $lvl,
                        'earner' => $upline,
                        'percent' => $pct,
                        'gross' => $teamGross,
                        'scooter_wallet' => $scooterPart,
                        'main_balance' => $mainPart
                    ];
                }
            }

            // 4. Contribute to turnover pools (Award & Reward 2.5%)
            if (function_exists('ind_record_award_reward_turnover')) {
                ind_record_award_reward_turnover($conn, $price);
            }

            $out['ok'] = true;
            $out['total_distributed'] = $totalDist;
            return $out;
        } catch (\Throwable $e) {
            $out['error'] = $e->getMessage();
            return $out;
        }
    }
}



