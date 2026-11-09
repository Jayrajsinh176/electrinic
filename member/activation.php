<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';
if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection failed.');
}
$conn->set_charset('utf8mb4');
require_once __DIR__ . '/../includes/income_common.php';

if (!isset($_SESSION['customer_id']) || trim((string)$_SESSION['customer_id']) === '') {
    header('Location: login.php');
    exit;
}
$customer_id = trim((string)$_SESSION['customer_id']);
$memberName  = $_SESSION['full_name'] ?? 'Member';

function e($s): string { return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); }

function normalizeUploadError(int $code): string
{
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Uploaded file is too large.',
        UPLOAD_ERR_PARTIAL   => 'File was only partially uploaded.',
        UPLOAD_ERR_NO_FILE   => 'Please upload your payment receipt.',
        UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'Server upload folder problem.',
        default => 'File upload failed.',
    };
}

/* fixed booking amount (admin-configurable) */
$bookingAmount = round(income_setting_num($conn, 'business_qualifying_amount', 10000.0), 2);

/* member status */
$user = null;
$st = $conn->prepare("SELECT customer_id, full_name, registration_status, benefit_choice, wallet_balance, cashback_claimed_total, cashback_max_limit FROM signup_users WHERE customer_id = ? LIMIT 1");
$st->bind_param('s', $customer_id);
$st->execute();
$user = $st->get_result()->fetch_assoc() ?: null;
$st->close();
if ($user && !empty($user['full_name'])) {
    $memberName = (string)$user['full_name'];
}
$isActivated = strtolower((string)($user['registration_status'] ?? '')) === 'active';
$userBenefitChoice = strtolower((string)($user['benefit_choice'] ?? ''));

$hasPending = false;
$st = $conn->prepare("SELECT id FROM activation_requests WHERE customer_id = ? AND status = 'Pending' LIMIT 1");
$st->bind_param('s', $customer_id);
$st->execute();
$hasPending = (bool)$st->get_result()->fetch_row();
$st->close();

/* booking history — this member's own activation_requests only, never a URL param */
$bookingHistory = [];
$st = $conn->prepare("SELECT id, amount, payment_mode, benefit_choice, transaction_id, payment_slip, remark, status, created_at FROM activation_requests WHERE customer_id = ? ORDER BY id DESC");
$st->bind_param('s', $customer_id);
$st->execute();
$rs = $st->get_result();
while ($row = $rs->fetch_assoc()) $bookingHistory[] = $row;
$st->close();

$latestApprovedId  = null;
$latestApprovedRow = null;
$pendingRow        = null;

foreach ($bookingHistory as $bh) {
    $stLower = strtolower(trim((string)($bh['status'] ?? '')));
    if ($stLower === 'approved' && $latestApprovedId === null) {
        $latestApprovedId  = (int)$bh['id'];
        $latestApprovedRow = $bh;
    }
    if ($stLower === 'pending' && $pendingRow === null) {
        $pendingRow = $bh;
    }
}

// If userBenefitChoice is not on signup_users yet, inherit from latest request
if ($userBenefitChoice === '' && $latestApprovedRow && !empty($latestApprovedRow['benefit_choice'])) {
    $userBenefitChoice = strtolower((string)$latestApprovedRow['benefit_choice']);
}

// Fetch active product voucher if any
$memberVoucher = null;
if (income_table_exists($conn, 'ind_vouchers')) {
    $vSt = $conn->prepare("SELECT voucher_code, amount, used_amount, balance_amount, validity_months, expires_at, status FROM ind_vouchers WHERE customer_id = ? ORDER BY id DESC LIMIT 1");
    if ($vSt) {
        $vSt->bind_param('s', $customer_id);
        $vSt->execute();
        $memberVoucher = $vSt->get_result()->fetch_assoc();
        $vSt->close();
    }
}

// Fetch cashback schedule if any
$memberCashbackSchedule = [];
if (income_table_exists($conn, 'member_cashback_schedules')) {
    $cbSt = $conn->prepare("SELECT installment_no, total_installments, amount, due_date, paid_date, status FROM member_cashback_schedules WHERE customer_id = ? ORDER BY installment_no ASC");
    if ($cbSt) {
        $cbSt->bind_param('s', $customer_id);
        $cbSt->execute();
        $cbRs = $cbSt->get_result();
        while ($cbr = $cbRs->fetch_assoc()) {
            $memberCashbackSchedule[] = $cbr;
        }
        $cbSt->close();
    }
}

$bank = [
    'Account Name' => 'ELECTRINC INFRASTRUCTURE PVT LTD',
    'Account No'   => '000000XXXX',
    'IFSC'         => 'SBIN000XXXX',
    'Bank'         => 'State Bank of India',
    'Branch'       => 'Kopar Khairane, Navi Mumbai',
    'UPI'          => 'electrinc@phonepe',
];

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order']) && !$isActivated && !$hasPending) {
    $payment_mode   = trim((string)($_POST['payment_mode'] ?? ''));
    $transaction_no = trim((string)($_POST['transaction_no'] ?? ''));
    $remark         = trim((string)($_POST['remark'] ?? ''));
    $benefit_choice = strtolower(trim((string)($_POST['benefit_choice'] ?? 'product')));
    if (!in_array($benefit_choice, ['product', 'cashback'], true)) {
        $benefit_choice = 'product';
    }
    $allowedModes   = ['UPI', 'IMPS', 'NEFT', 'RTGS', 'Cash Deposit'];

    if (!in_array($payment_mode, $allowedModes, true)) {
        $error = 'Please select a valid payment mode.';
    } elseif ($transaction_no === '') {
        $error = 'Please enter the transaction / UTR number.';
    } elseif (!isset($_FILES['receipt']) || (int)$_FILES['receipt']['error'] !== UPLOAD_ERR_OK) {
        $error = normalizeUploadError((int)($_FILES['receipt']['error'] ?? UPLOAD_ERR_NO_FILE));
    } else {
        $tmp  = (string)$_FILES['receipt']['tmp_name'];
        $name = (string)$_FILES['receipt']['name'];
        $size = (int)$_FILES['receipt']['size'];
        $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $mime = @mime_content_type($tmp) ?: '';
        $okMimes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'pdf' => 'application/pdf'];

        if (!is_uploaded_file($tmp)) {
            $error = 'Invalid uploaded file.';
        } elseif (!isset($okMimes[$ext]) || $mime !== $okMimes[$ext]) {
            $error = 'Only JPG, PNG or PDF files are allowed.';
        } elseif ($size <= 0 || $size > 5 * 1024 * 1024) {
            $error = 'File must be between 1 byte and 5 MB.';
        } else {
            $dir = __DIR__ . '/uploads/receipts/';
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            $newName  = 'receipt_' . date('Ymd_His') . '_' . random_int(1000, 9999) . '.' . $ext;
            $relPath  = 'uploads/receipts/' . $newName;

            if (@move_uploaded_file($tmp, __DIR__ . '/' . $relPath)) {
                $planLabel = ($benefit_choice === 'cashback') ? 'Monthly Cashback (10 Mo)' : 'Product Purchase (36 Mo)';
                $productsJson = json_encode([['item' => 'Joining Booking - ' . $planLabel, 'amount' => $bookingAmount, 'qty' => 1]], JSON_UNESCAPED_UNICODE);
                $ins = $conn->prepare("INSERT INTO activation_requests
                    (customer_id, amount, payment_mode, benefit_choice, transaction_id, payment_slip, remark, products_json, status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending', NOW())");
                $ins->bind_param('sdssssss', $customer_id, $bookingAmount, $payment_mode, $benefit_choice, $transaction_no, $relPath, $remark, $productsJson);
                if ($ins->execute()) {
                    $success = 'Your booking request has been submitted. Please wait for admin approval.';
                    $hasPending = true;
                    // Refresh booking history
                    $bookingHistory = [];
                    $st = $conn->prepare("SELECT id, amount, payment_mode, benefit_choice, transaction_id, payment_slip, remark, status, created_at FROM activation_requests WHERE customer_id = ? ORDER BY id DESC");
                    $st->bind_param('s', $customer_id);
                    $st->execute();
                    $rs = $st->get_result();
                    while ($row = $rs->fetch_assoc()) $bookingHistory[] = $row;
                    $st->close();
                    if (!empty($bookingHistory)) {
                        $pendingRow = $bookingHistory[0];
                    }
                } else {
                    $error = 'Could not save the request. Please try again.';
                    @unlink(__DIR__ . '/' . $relPath);
                }
                $ins->close();
            } else {
                $error = 'Failed to upload the receipt.';
            }
        }
    }
}

function money($n): string {
    return '₹' . number_format((float)$n, 2);
}

function formatBookingDate(?string $dt): array
{
    if (!$dt || trim($dt) === '') {
        return ['date' => '—', 'time' => ''];
    }
    $ts = strtotime($dt);
    if ($ts === false) {
        return ['date' => e($dt), 'time' => ''];
    }
    return [
        'date' => date('d M Y', $ts),
        'time' => date('h:i A', $ts),
    ];
}

function paymentModeBadge(string $mode): array
{
    $m = strtoupper(trim($mode));
    return match ($m) {
        'UPI'          => ['icon' => 'smartphone',       'class' => 'bg-purple-50 text-purple-700 border-purple-200'],
        'NEFT'         => ['icon' => 'landmark',         'class' => 'bg-blue-50 text-blue-700 border-blue-200'],
        'IMPS'         => ['icon' => 'zap',              'class' => 'bg-amber-50 text-amber-700 border-amber-200'],
        'RTGS'         => ['icon' => 'arrow-right-left', 'class' => 'bg-indigo-50 text-indigo-700 border-indigo-200'],
        'CASH DEPOSIT' => ['icon' => 'banknote',         'class' => 'bg-emerald-50 text-emerald-700 border-emerald-200'],
        default        => ['icon' => 'credit-card',      'class' => 'bg-slate-50 text-slate-700 border-slate-200'],
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book / Activate — Member Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'Inter', 'sans-serif'],
                        mono: ['ui-monospace', 'SFMono-Regular', 'Menlo', 'Monaco', 'Consolas', 'monospace'],
                    },
                    colors: {
                        brand: {
                            forest: '#0c382b',
                            forestDark: '#08281e',
                            emerald: '#0e7a3b',
                            emeraldLight: '#eaf5ec',
                            gold: '#c79435',
                            goldLight: '#faf2e4',
                            goldDark: '#a37522',
                            saffron: '#e0700c',
                        }
                    },
                    boxShadow: {
                        'soft': '0 2px 10px rgba(0, 0, 0, 0.04), 0 1px 3px rgba(0, 0, 0, 0.02)',
                        'card': '0 4px 20px -2px rgba(14, 122, 59, 0.06), 0 2px 6px -1px rgba(0, 0, 0, 0.04)',
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #FBFAF6; color: #1e293b; }
        @media (min-width: 1024px) { .main-wrap { margin-left: 256px; } }
        input, select, textarea { outline: none; }
        .table-scroll::-webkit-scrollbar { height: 6px; }
        .table-scroll::-webkit-scrollbar-track { background: #f1f5f9; }
        .table-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 9999px; }
        .animate-fadeIn { animation: fadeIn 0.25s ease-out; }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-4px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="bg-[#FBFAF6]">

<?php if (file_exists(__DIR__ . '/includes/sidebar.php')) include __DIR__ . '/includes/sidebar.php'; ?>

<div class="main-wrap min-h-screen flex flex-col">
    <?php if (file_exists(__DIR__ . '/includes/navbar.php')) include __DIR__ . '/includes/navbar.php'; ?>

    <main class="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl w-full mx-auto space-y-6">

        <!-- Header Card -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white rounded-2xl border border-slate-200/90 p-4 sm:px-6 sm:py-4 shadow-soft">
            <div class="flex items-center gap-3.5">
                <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-[#f8efe0] to-[#f2dfc4] border border-[#d6b06b]/40 flex items-center justify-center text-[#c79435] shrink-0 shadow-xs">
                    <i data-lucide="zap" class="w-5 h-5"></i>
                </div>
                <div>
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <h1 class="text-lg sm:text-xl font-bold text-slate-900 tracking-tight">Book / Activate</h1>
                        <?php if ($isActivated): ?>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300/80">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-600 animate-pulse"></span>
                                Active Member
                            </span>
                        <?php elseif ($hasPending): ?>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-100 text-amber-800 border border-amber-300/80">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-600 animate-pulse"></span>
                                Review in Progress
                            </span>
                        <?php else: ?>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-slate-100 text-slate-700 border border-slate-300/80">
                                Activation Required
                            </span>
                        <?php endif; ?>
                    </div>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Member: <strong class="text-slate-800"><?php echo e($memberName); ?></strong> &middot; ID:
                        <span class="inline-flex items-center gap-1 font-mono font-semibold text-slate-800 bg-slate-100 hover:bg-slate-200 px-1.5 py-0.5 rounded cursor-pointer transition-colors"
                              onclick="copyText('<?php echo e($customer_id); ?>', this)" title="Click to copy Customer ID">
                            <?php echo e($customer_id); ?>
                            <i data-lucide="copy" class="w-3 h-3 text-slate-400"></i>
                        </span>
                    </p>
                </div>
            </div>

            <!-- Quick Navigation Pills -->
            <div class="flex items-center gap-2.5 self-start sm:self-auto">
                <a href="dashboard.php" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:text-slate-900 hover:bg-slate-100 border border-slate-200/80 transition-all">
                    <i data-lucide="layout-dashboard" class="w-3.5 h-3.5 text-slate-400"></i>
                    <span>Dashboard</span>
                </a>
                <a href="order_status.php" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:text-slate-900 hover:bg-slate-100 border border-slate-200/80 transition-all">
                    <i data-lucide="clipboard-list" class="w-3.5 h-3.5 text-slate-400"></i>
                    <span>Orders</span>
                </a>
            </div>
        </div>

        <!-- Feedback Alert Messages -->
        <?php if ($success !== ''): ?>
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50/90 p-4 sm:p-5 shadow-sm flex items-start gap-3 text-emerald-800 animate-fadeIn">
                <div class="w-8 h-8 rounded-xl bg-emerald-100 flex items-center justify-center shrink-0 text-emerald-700 mt-0.5">
                    <i data-lucide="check-circle-2" class="w-5 h-5"></i>
                </div>
                <div class="flex-1 text-sm font-medium">
                    <?php echo e($success); ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="rounded-2xl border border-rose-200 bg-rose-50/90 p-4 sm:p-5 shadow-sm flex items-start gap-3 text-rose-800 animate-fadeIn">
                <div class="w-8 h-8 rounded-xl bg-rose-100 flex items-center justify-center shrink-0 text-rose-700 mt-0.5">
                    <i data-lucide="alert-triangle" class="w-5 h-5"></i>
                </div>
                <div class="flex-1 text-sm font-medium">
                    <?php echo e($error); ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- ========================================================================= -->
        <!-- STATE 1: MEMBER IS ACTIVATED                                              -->
        <!-- ========================================================================= -->
        <?php if ($isActivated): ?>

            <!-- Premium Hero Banner -->
            <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#0c392c] via-[#0f4b39] to-[#125a44] p-6 sm:p-8 text-white shadow-card border border-emerald-900/30">
                <div class="absolute -right-16 -bottom-16 w-72 h-72 rounded-full bg-emerald-400/10 blur-3xl pointer-events-none"></div>
                <div class="absolute right-32 top-0 w-48 h-48 rounded-full bg-amber-400/10 blur-2xl pointer-events-none"></div>

                <div class="relative z-10 flex flex-col lg:flex-row lg:items-center justify-between gap-6">
                    <div class="max-w-2xl space-y-3">
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-emerald-400/20 text-emerald-200 border border-emerald-300/30">
                            <i data-lucide="shield-check" class="w-3.5 h-3.5 text-emerald-300"></i>
                            Account Verified &amp; Active
                        </div>
                        <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-white">
                            Your ID is Already Activated
                        </h2>
                        <p class="text-sm text-emerald-100/90 leading-relaxed">
                            Welcome to ELECTRINC. Your account has verified active distributor status with complete access to direct referrals, binary team rewards, auto-pool earnings, and official invoicing.
                        </p>
                        <div class="pt-2 flex flex-wrap gap-2.5">
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs font-medium bg-white/10 text-emerald-100 border border-white/15">
                                <i data-lucide="check" class="w-3.5 h-3.5 text-emerald-300"></i> Lifetime Business Access
                            </span>
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs font-medium bg-white/10 text-emerald-100 border border-white/15">
                                <i data-lucide="check" class="w-3.5 h-3.5 text-emerald-300"></i> Direct &amp; Team Income
                            </span>
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs font-medium bg-white/10 text-emerald-100 border border-white/15">
                                <i data-lucide="check" class="w-3.5 h-3.5 text-emerald-300"></i> Auto Pool Bonus Ready
                            </span>
                        </div>
                    </div>

                    <!-- Right Action Card -->
                    <div class="shrink-0 flex flex-col sm:flex-row lg:flex-col gap-3 min-w-[240px]">
                        <?php if ($latestApprovedId): ?>
                            <a href="activation-invoice.php?id=<?php echo $latestApprovedId; ?>" target="_blank"
                               class="inline-flex items-center justify-center gap-2 px-5 py-3.5 rounded-2xl bg-gradient-to-r from-[#d4a340] to-[#b98422] hover:from-[#c5922e] hover:to-[#a4721a] text-white font-bold text-sm shadow-md hover:shadow-lg transition-all duration-200">
                                <i data-lucide="file-text" class="w-4 h-4"></i>
                                <span>Download Tax Invoice</span>
                                <i data-lucide="external-link" class="w-3.5 h-3.5 opacity-80"></i>
                            </a>
                        <?php endif; ?>
                        <a href="wallet.php"
                           class="inline-flex items-center justify-center gap-2 px-5 py-3 rounded-2xl bg-white/10 hover:bg-white/20 text-white font-semibold text-xs border border-white/20 transition-all">
                            <i data-lucide="wallet" class="w-4 h-4 text-emerald-300"></i>
                            <span>View Wallet &amp; Payouts</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- 3 Stat Metrics -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-soft flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0 border border-emerald-100">
                        <i data-lucide="badge-check" class="w-6 h-6"></i>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Account Standing</p>
                        <p class="text-base font-bold text-slate-900 mt-0.5">Active &amp; Qualified</p>
                        <p class="text-[11px] text-emerald-600 font-semibold mt-0.5">● Permanent Activation</p>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-soft flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-700 flex items-center justify-center shrink-0 border border-amber-100">
                        <i data-lucide="indian-rupee" class="w-6 h-6"></i>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Qualifying Amount</p>
                        <p class="text-base font-bold text-slate-900 mt-0.5"><?php echo money($bookingAmount); ?></p>
                        <p class="text-[11px] text-slate-500 mt-0.5">Business Qualifying Value</p>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-soft flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-700 flex items-center justify-center shrink-0 border border-blue-100">
                        <i data-lucide="history" class="w-6 h-6"></i>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Booking Records</p>
                        <p class="text-base font-bold text-slate-900 mt-0.5"><?php echo count($bookingHistory); ?> Transaction<?php echo count($bookingHistory) !== 1 ? 's' : ''; ?></p>
                        <p class="text-[11px] text-slate-500 mt-0.5">Audit log preserved</p>
                    </div>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- ACTIVE BENEFIT PLAN DETAIL (Product 36M or Cashback 10M)                  -->
            <!-- ========================================================================= -->
            <?php if ($userBenefitChoice === 'cashback' || !empty($memberCashbackSchedule)): ?>
                <?php
                $paidInstallments = 0;
                $paidCashbackAmount = 0.0;
                $totalCashbackAmount = 10000.00;
                foreach ($memberCashbackSchedule as $cs) {
                    if (strtolower((string)$cs['status']) === 'paid') {
                        $paidInstallments++;
                        $paidCashbackAmount += (float)$cs['amount'];
                    }
                }
                $cbPercent = min(100, round(($paidInstallments / max(1, count($memberCashbackSchedule) ?: 10)) * 100));
                ?>
                <div class="bg-white rounded-3xl border border-emerald-200/90 shadow-soft overflow-hidden p-6 sm:p-7 space-y-6">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-5 border-b border-slate-100">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-700 border border-emerald-200 flex items-center justify-center shrink-0">
                                <i data-lucide="coins" class="w-6 h-6"></i>
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <h3 class="text-base sm:text-lg font-bold text-slate-900">Monthly Cashback Plan</h3>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-600 animate-pulse"></span> Active Plan
                                    </span>
                                </div>
                                <p class="text-xs text-slate-500 mt-0.5">₹1,000 / month credited directly to your withdrawable Main Wallet for 10 months</p>
                            </div>
                        </div>
                        <div class="text-left sm:text-right">
                            <p class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Total Received</p>
                            <p class="text-xl font-extrabold text-emerald-700"><?php echo money($paidCashbackAmount); ?> <span class="text-xs font-normal text-slate-400">/ <?php echo money($totalCashbackAmount); ?></span></p>
                            <a href="joining-benefit.php" class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-700 hover:underline mt-0.5">
                                <span>Full Payout Report &rarr;</span>
                            </a>
                        </div>
                    </div>

                    <!-- Progress Bar -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between text-xs font-semibold">
                            <span class="text-slate-600">Installments Completed: <strong class="text-emerald-700"><?php echo $paidInstallments; ?> of <?php echo count($memberCashbackSchedule) ?: 10; ?> Months</strong></span>
                            <span class="text-emerald-700 font-bold"><?php echo $cbPercent; ?>%</span>
                        </div>
                        <div class="w-full h-3 bg-slate-100 rounded-full overflow-hidden p-0.5 border border-slate-200/60">
                            <div class="h-full bg-gradient-to-r from-emerald-500 to-teal-500 rounded-full transition-all duration-500" style="width: <?php echo $cbPercent; ?>%"></div>
                        </div>
                    </div>

                    <!-- Installments Schedule Table -->
                    <?php if (!empty($memberCashbackSchedule)): ?>
                        <div class="overflow-x-auto rounded-2xl border border-slate-100 table-scroll">
                            <table class="w-full text-left text-xs sm:text-sm">
                                <thead class="bg-slate-50 text-slate-500 uppercase tracking-wider text-[11px] font-bold">
                                    <tr>
                                        <th class="px-4 py-3">Installment</th>
                                        <th class="px-4 py-3">Amount</th>
                                        <th class="px-4 py-3">Due Date</th>
                                        <th class="px-4 py-3 text-center">Status</th>
                                        <th class="px-4 py-3 text-right">Destination</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <?php foreach ($memberCashbackSchedule as $cs):
                                        $csStatus = strtolower((string)($cs['status'] ?? 'pending'));
                                        $dueDt = date('d M Y', strtotime($cs['due_date']));
                                    ?>
                                        <tr class="hover:bg-slate-50/60 transition-colors">
                                            <td class="px-4 py-3 font-semibold text-slate-800">
                                                Month <?php echo (int)$cs['installment_no']; ?> of <?php echo (int)$cs['total_installments']; ?>
                                            </td>
                                            <td class="px-4 py-3 font-bold text-slate-900">
                                                <?php echo money($cs['amount']); ?>
                                            </td>
                                            <td class="px-4 py-3 text-slate-600 font-medium">
                                                <?php echo e($dueDt); ?>
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <?php if ($csStatus === 'paid'): ?>
                                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                                                        <i data-lucide="check" class="w-3 h-3 text-emerald-600"></i> Paid
                                                    </span>
                                                <?php else: ?>
                                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                                                        <i data-lucide="clock" class="w-3 h-3 text-amber-500"></i> Scheduled
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-3 text-right text-xs text-slate-500 font-medium">
                                                Main Wallet
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <!-- Product Purchase Benefit Card -->
                <div class="bg-gradient-to-br from-indigo-50/80 via-white to-blue-50/50 rounded-3xl border border-indigo-200/80 shadow-soft p-6 sm:p-7 space-y-5">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-indigo-100">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-2xl bg-indigo-100 text-indigo-700 border border-indigo-200 flex items-center justify-center shrink-0">
                                <i data-lucide="gift" class="w-6 h-6"></i>
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <h3 class="text-base sm:text-lg font-bold text-slate-900">Product Purchase Voucher (36 Months)</h3>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-indigo-100 text-indigo-800 border border-indigo-200">
                                        <i data-lucide="sparkles" class="w-3 h-3 text-indigo-600"></i> Active Benefit
                                    </span>
                                </div>
                                <p class="text-xs text-slate-500 mt-0.5">Use this official voucher code to purchase company products or book your Electric Scooter</p>
                            </div>
                        </div>
                        <div class="text-left sm:text-right">
                            <p class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Voucher Value</p>
                            <p class="text-xl font-extrabold text-indigo-700"><?php echo money($memberVoucher['balance_amount'] ?? 10000.00); ?></p>
                            <a href="joining-benefit.php" class="inline-flex items-center gap-1 text-[11px] font-semibold text-indigo-700 hover:underline mt-0.5">
                                <span>View Voucher Statement &rarr;</span>
                            </a>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="bg-white rounded-2xl p-4 border border-indigo-100 shadow-2xs">
                            <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Voucher Code</p>
                            <div class="mt-1 flex items-center justify-between">
                                <span class="font-mono font-bold text-sm sm:text-base text-slate-800">
                                    <?php echo e($memberVoucher['voucher_code'] ?? 'VCH-ACTIVE-10K'); ?>
                                </span>
                                <button type="button" onclick="copyText('<?php echo e($memberVoucher['voucher_code'] ?? 'VCH-ACTIVE-10K'); ?>', this)"
                                        class="p-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 transition-colors" title="Copy code">
                                    <i data-lucide="copy" class="w-3.5 h-3.5"></i>
                                </button>
                            </div>
                        </div>

                        <div class="bg-white rounded-2xl p-4 border border-indigo-100 shadow-2xs">
                            <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Validity Period</p>
                            <p class="mt-1 font-bold text-slate-800 text-sm sm:text-base">36 Months (3 Years)</p>
                            <p class="text-[11px] text-slate-400">Valid until <?php echo !empty($memberVoucher['expires_at']) ? date('d M Y', strtotime($memberVoucher['expires_at'])) : date('d M Y', strtotime('+36 months')); ?></p>
                        </div>

                        <div class="bg-white rounded-2xl p-4 border border-indigo-100 shadow-2xs flex items-center justify-between">
                            <div>
                                <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Redeem Status</p>
                                <p class="mt-1 font-bold text-emerald-600 text-sm">Ready to Use</p>
                            </div>
                            <a href="scooter-promotion.php"
                               class="inline-flex items-center gap-1 px-3 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold transition-colors">
                                <span>Book EV</span>
                                <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Booking History Card -->
            <div class="bg-white rounded-2xl border border-slate-200/90 shadow-soft overflow-hidden">
                <div class="px-5 sm:px-6 py-4 border-b border-slate-200/80 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-[#fbf5eb] border border-[#f2e1c2] flex items-center justify-center text-[#c79435] shrink-0">
                            <i data-lucide="receipt" class="w-4 h-4"></i>
                        </div>
                        <div>
                            <h3 class="text-sm sm:text-base font-bold text-slate-900">Booking &amp; Activation History</h3>
                            <p class="text-xs text-slate-500">Official log of your joining booking submissions and tax invoices</p>
                        </div>
                    </div>
                    <span class="self-start sm:self-auto text-xs font-semibold px-2.5 py-1 rounded-full bg-slate-100 text-slate-600 border border-slate-200">
                        <?php echo count($bookingHistory); ?> Record<?php echo count($bookingHistory) !== 1 ? 's' : ''; ?>
                    </span>
                </div>

                <?php if (empty($bookingHistory)): ?>
                    <div class="p-12 text-center">
                        <div class="w-14 h-14 rounded-2xl bg-slate-100 text-slate-400 mx-auto flex items-center justify-center mb-3">
                            <i data-lucide="inbox" class="w-7 h-7"></i>
                        </div>
                        <p class="text-sm font-semibold text-slate-700">No booking records found</p>
                        <p class="text-xs text-slate-400 mt-1">Transactions will appear here once submitted.</p>
                    </div>
                <?php else: ?>
                    <!-- Desktop Table (md and up) -->
                    <div class="hidden md:block overflow-x-auto table-scroll">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50/80 text-slate-500 text-[11px] font-bold uppercase tracking-wider border-b border-slate-200/80">
                                <tr>
                                    <th class="px-6 py-3.5">Date &amp; Time</th>
                                    <th class="px-6 py-3.5 text-right">Amount</th>
                                    <th class="px-6 py-3.5">Benefit Plan</th>
                                    <th class="px-6 py-3.5">Payment Mode</th>
                                    <th class="px-6 py-3.5">Transaction ID / UTR</th>
                                    <th class="px-6 py-3.5 text-center">Status</th>
                                    <th class="px-6 py-3.5 text-right">Invoice &amp; Slip</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach ($bookingHistory as $bh):
                                    $dt = formatBookingDate($bh['created_at'] ?? '');
                                    $badge = paymentModeBadge($bh['payment_mode'] ?? '');
                                    $st = strtolower(trim((string)($bh['status'] ?? 'pending')));
                                ?>
                                    <tr class="hover:bg-slate-50/80 transition-colors">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="font-semibold text-slate-800 text-xs sm:text-sm"><?php echo e($dt['date']); ?></div>
                                            <?php if ($dt['time'] !== ''): ?>
                                                <div class="text-[11px] text-slate-400 flex items-center gap-1 mt-0.5">
                                                    <i data-lucide="clock" class="w-3 h-3"></i>
                                                    <?php echo e($dt['time']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>

                                        <td class="px-6 py-4 text-right whitespace-nowrap">
                                            <span class="font-bold text-slate-900 text-sm sm:text-base">
                                                <?php echo money($bh['amount']); ?>
                                            </span>
                                        </td>

                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php if (strtolower((string)($bh['benefit_choice'] ?? '')) === 'cashback'): ?>
                                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                    💵 Cashback (10 Mo)
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                                    🎁 Product (36 Mo)
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold border <?php echo $badge['class']; ?>">
                                                <i data-lucide="<?php echo $badge['icon']; ?>" class="w-3.5 h-3.5"></i>
                                                <?php echo e($bh['payment_mode']); ?>
                                            </span>
                                        </td>

                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center gap-1.5 font-mono text-xs font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200/80 px-2.5 py-1.5 rounded-lg border border-slate-200/70 cursor-pointer transition-colors group"
                                                  onclick="copyText('<?php echo e($bh['transaction_id']); ?>', this)"
                                                  title="Click to copy Transaction ID">
                                                <span><?php echo e($bh['transaction_id']); ?></span>
                                                <i data-lucide="copy" class="w-3 h-3 text-slate-400 group-hover:text-slate-600 transition-colors"></i>
                                            </span>
                                        </td>

                                        <td class="px-6 py-4 text-center whitespace-nowrap">
                                            <?php if ($st === 'approved'): ?>
                                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                                    Approved
                                                </span>
                                            <?php elseif ($st === 'pending'): ?>
                                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                                    Pending Review
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-rose-50 text-rose-700 border border-rose-200">
                                                    <i data-lucide="x-circle" class="w-3.5 h-3.5 text-rose-500"></i>
                                                    <?php echo e($bh['status']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <td class="px-6 py-4 text-right whitespace-nowrap">
                                            <div class="inline-flex items-center gap-2 justify-end">
                                                <?php if ($st === 'approved'): ?>
                                                    <a href="activation-invoice.php?id=<?php echo (int)$bh['id']; ?>" target="_blank"
                                                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-50 hover:bg-emerald-100 text-[#0E7A3B] border border-emerald-200/80 text-xs font-semibold transition-all">
                                                        <i data-lucide="file-text" class="w-3.5 h-3.5"></i>
                                                        <span>Invoice</span>
                                                    </a>
                                                <?php endif; ?>

                                                <?php if (!empty($bh['payment_slip'])): ?>
                                                    <a href="<?php echo e($bh['payment_slip']); ?>" target="_blank"
                                                       class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-medium transition-all"
                                                       title="View Uploaded Payment Slip">
                                                        <i data-lucide="paperclip" class="w-3.5 h-3.5 text-slate-500"></i>
                                                        <span>Slip</span>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile Cards (< md) -->
                    <div class="md:hidden divide-y divide-slate-100">
                        <?php foreach ($bookingHistory as $bh):
                            $dt = formatBookingDate($bh['created_at'] ?? '');
                            $badge = paymentModeBadge($bh['payment_mode'] ?? '');
                            $st = strtolower(trim((string)($bh['status'] ?? 'pending')));
                        ?>
                            <div class="p-4 space-y-3">
                                <div class="flex items-center justify-between gap-2">
                                    <div>
                                        <span class="text-xs text-slate-400"><?php echo e($dt['date']); ?> <?php echo e($dt['time']); ?></span>
                                        <div class="text-lg font-bold text-slate-900 mt-0.5"><?php echo money($bh['amount']); ?></div>
                                    </div>
                                    <?php if ($st === 'approved'): ?>
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Approved
                                        </span>
                                    <?php elseif ($st === 'pending'): ?>
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Pending
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-rose-50 text-rose-700 border border-rose-200">
                                            <?php echo e($bh['status']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div class="flex flex-wrap items-center gap-2 pt-1">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold border <?php echo $badge['class']; ?>">
                                        <i data-lucide="<?php echo $badge['icon']; ?>" class="w-3.5 h-3.5"></i>
                                        <?php echo e($bh['payment_mode']); ?>
                                    </span>

                                    <span class="inline-flex items-center gap-1 font-mono text-xs text-slate-700 bg-slate-100 px-2 py-1 rounded-md cursor-pointer border border-slate-200/70"
                                          onclick="copyText('<?php echo e($bh['transaction_id']); ?>', this)">
                                        <span>UTR: <?php echo e($bh['transaction_id']); ?></span>
                                        <i data-lucide="copy" class="w-3 h-3 text-slate-400"></i>
                                    </span>
                                </div>

                                <div class="flex items-center gap-2 pt-1 border-t border-slate-100">
                                    <?php if ($st === 'approved'): ?>
                                        <a href="activation-invoice.php?id=<?php echo (int)$bh['id']; ?>" target="_blank"
                                           class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-emerald-50 hover:bg-emerald-100 text-[#0E7A3B] border border-emerald-200 text-xs font-semibold transition-all">
                                            <i data-lucide="file-text" class="w-4 h-4"></i> Download Tax Invoice
                                        </a>
                                    <?php endif; ?>

                                    <?php if (!empty($bh['payment_slip'])): ?>
                                        <a href="<?php echo e($bh['payment_slip']); ?>" target="_blank"
                                           class="inline-flex items-center justify-center gap-1 px-3 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-medium transition-all">
                                            <i data-lucide="paperclip" class="w-3.5 h-3.5"></i> Slip
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        <!-- ========================================================================= -->
        <!-- STATE 2: REQUEST IS PENDING APPROVAL                                      -->
        <!-- ========================================================================= -->
        <?php elseif ($hasPending): ?>

            <div class="rounded-3xl border border-amber-200 bg-gradient-to-br from-amber-50/90 via-[#fffbf2] to-amber-100/40 p-6 sm:p-8 shadow-soft space-y-6">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 pb-6 border-b border-amber-200/70">
                    <div class="space-y-2.5 max-w-2xl">
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-amber-100 text-amber-900 border border-amber-300/80">
                            <span class="w-2 h-2 rounded-full bg-amber-500 animate-ping"></span>
                            Payment Slip Under Review
                        </div>
                        <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                            Booking Request Submitted
                        </h2>
                        <p class="text-sm text-slate-600 leading-relaxed">
                            We have received your payment proof for <strong class="text-slate-900"><?php echo money($bookingAmount); ?></strong>. Our finance desk is verifying your transaction reference. Once confirmed, your ID will be activated automatically with full privileges.
                        </p>
                    </div>

                    <?php if ($pendingRow): ?>
                        <div class="bg-white/95 border border-amber-200/80 rounded-2xl p-4 sm:p-5 shadow-xs shrink-0 min-w-[220px]">
                            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Submitted Details</p>
                            <p class="text-xl font-extrabold text-slate-900 mt-1"><?php echo money($pendingRow['amount']); ?></p>
                            <div class="text-xs text-slate-600 mt-2 space-y-1">
                                <p><span class="text-slate-400">Benefit Plan:</span> 
                                    <strong class="text-slate-800">
                                        <?php echo (strtolower((string)($pendingRow['benefit_choice'] ?? '')) === 'cashback') ? '💵 Monthly Cashback (10 Mo)' : '🎁 Product Voucher (36 Mo)'; ?>
                                    </strong>
                                </p>
                                <p><span class="text-slate-400">Mode:</span> <strong class="text-slate-800"><?php echo e($pendingRow['payment_mode']); ?></strong></p>
                                <p><span class="text-slate-400">UTR:</span> <span class="font-mono text-slate-800"><?php echo e($pendingRow['transaction_id']); ?></span></p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- 3-Step Verification Stepper -->
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3.5">Activation Step Progress</p>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="flex items-center gap-3 p-3.5 rounded-2xl bg-white border border-emerald-200 shadow-xs">
                            <div class="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">
                                <i data-lucide="check" class="w-4 h-4"></i>
                            </div>
                            <div>
                                <p class="text-xs font-bold text-slate-900">1. Booking Submitted</p>
                                <p class="text-[11px] text-emerald-600 font-medium">Payment slip uploaded</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 p-3.5 rounded-2xl bg-amber-50 border-2 border-amber-400 shadow-xs">
                            <div class="w-8 h-8 rounded-xl bg-amber-400 text-amber-950 flex items-center justify-center shrink-0 font-bold text-xs">
                                <i data-lucide="clock" class="w-4 h-4 animate-spin" style="animation-duration: 4s;"></i>
                            </div>
                            <div>
                                <p class="text-xs font-bold text-slate-900">2. Verification</p>
                                <p class="text-[11px] text-amber-700 font-semibold">Under Review (1-2 hrs)</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 p-3.5 rounded-2xl bg-white/70 border border-slate-200 text-slate-400">
                            <div class="w-8 h-8 rounded-xl bg-slate-100 text-slate-400 flex items-center justify-center shrink-0 text-xs font-bold">
                                3
                            </div>
                            <div>
                                <p class="text-xs font-bold text-slate-500">3. ID Activated</p>
                                <p class="text-[11px] text-slate-400">Auto privileges unlock</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Booking History Card for Pending User -->
            <?php if (!empty($bookingHistory)): ?>
                <div class="bg-white rounded-2xl border border-slate-200/90 shadow-soft overflow-hidden">
                    <div class="px-5 sm:px-6 py-4 border-b border-slate-200/80 flex items-center justify-between">
                        <h3 class="text-sm sm:text-base font-bold text-slate-900">Your Booking Submission History</h3>
                        <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-slate-100 text-slate-600">
                            <?php echo count($bookingHistory); ?> Record<?php echo count($bookingHistory) !== 1 ? 's' : ''; ?>
                        </span>
                    </div>

                    <div class="overflow-x-auto table-scroll">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50/80 text-slate-500 text-[11px] font-bold uppercase tracking-wider border-b border-slate-200/80">
                                <tr>
                                    <th class="px-6 py-3.5">Date &amp; Time</th>
                                    <th class="px-6 py-3.5 text-right">Amount</th>
                                    <th class="px-6 py-3.5">Benefit Plan</th>
                                    <th class="px-6 py-3.5">Payment Mode</th>
                                    <th class="px-6 py-3.5">Transaction ID / UTR</th>
                                    <th class="px-6 py-3.5 text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach ($bookingHistory as $bh):
                                    $dt = formatBookingDate($bh['created_at'] ?? '');
                                    $badge = paymentModeBadge($bh['payment_mode'] ?? '');
                                    $st = strtolower(trim((string)($bh['status'] ?? 'pending')));
                                ?>
                                    <tr class="hover:bg-slate-50/80 transition-colors">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="font-semibold text-slate-800 text-xs sm:text-sm"><?php echo e($dt['date']); ?></div>
                                            <div class="text-[11px] text-slate-400 flex items-center gap-1 mt-0.5">
                                                <i data-lucide="clock" class="w-3 h-3"></i>
                                                <?php echo e($dt['time']); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-right font-bold text-slate-900 whitespace-nowrap">
                                            <?php echo money($bh['amount']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php if (strtolower((string)($bh['benefit_choice'] ?? '')) === 'cashback'): ?>
                                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                    💵 Cashback (10 Mo)
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                                    🎁 Product (36 Mo)
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold border <?php echo $badge['class']; ?>">
                                                <i data-lucide="<?php echo $badge['icon']; ?>" class="w-3.5 h-3.5"></i>
                                                <?php echo e($bh['payment_mode']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap font-mono text-xs text-slate-700">
                                            <?php echo e($bh['transaction_id']); ?>
                                        </td>
                                        <td class="px-6 py-4 text-center whitespace-nowrap">
                                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                                Pending Review
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

        <!-- ========================================================================= -->
        <!-- STATE 3: INITIAL BOOKING / ACTIVATION FORM                                -->
        <!-- ========================================================================= -->
        <?php else: ?>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

                <!-- Left Column: Package Info & Bank Details -->
                <div class="lg:col-span-5 space-y-5">

                    <!-- Booking Amount Hero Card -->
                    <div class="rounded-3xl bg-gradient-to-br from-[#0c392c] via-[#0f4b39] to-[#125a44] p-6 text-white shadow-card relative overflow-hidden">
                        <div class="absolute -right-10 -bottom-10 w-44 h-44 rounded-full bg-emerald-400/10 blur-xl pointer-events-none"></div>

                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-emerald-400/20 text-emerald-200 border border-emerald-300/30">
                            One-Time Qualifying Booking
                        </span>
                        <p class="text-xs text-emerald-100/80 mt-3 font-medium uppercase tracking-wider">Required Deposit</p>
                        <p class="text-3xl sm:text-4xl font-extrabold tracking-tight text-white mt-1">
                            <?php echo money($bookingAmount); ?>
                        </p>
                        <p class="text-xs text-emerald-100/90 mt-2 leading-relaxed">
                            Transfer this amount to the verified company bank account or UPI ID below, then submit your transaction details for instant verification.
                        </p>

                        <div class="mt-5 pt-4 border-t border-emerald-800/60 space-y-2 text-xs text-emerald-50">
                            <div class="flex items-center gap-2">
                                <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-300 shrink-0"></i>
                                <span>Full Member Portal Access &amp; Referral Program</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-300 shrink-0"></i>
                                <span>Direct Sponsor &amp; 10-Level Team Commissions</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-300 shrink-0"></i>
                                <span>Auto Pool Entry &amp; Electric Scooter Rewards</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-300 shrink-0"></i>
                                <span>Official GST Tax Invoice upon Activation</span>
                            </div>
                        </div>
                    </div>

                    <!-- Company Bank Details Card -->
                    <div class="bg-white rounded-3xl border border-slate-200/90 p-6 shadow-soft">
                        <div class="flex items-center justify-between gap-3 pb-4 border-b border-slate-100">
                            <div class="flex items-center gap-2.5">
                                <div class="w-9 h-9 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center shrink-0 border border-amber-200/60">
                                    <i data-lucide="landmark" class="w-4 h-4"></i>
                                </div>
                                <div>
                                    <h3 class="text-sm font-bold text-slate-900">Official Company Bank Details</h3>
                                    <p class="text-[11px] text-slate-500">Pay directly to ELECTRINC INFRASTRUCTURE PVT LTD</p>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 space-y-3 text-xs">
                            <?php foreach ($bank as $k => $v):
                                $isCopyable = in_array($k, ['Account No', 'IFSC', 'UPI'], true);
                            ?>
                                <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50/70 border border-slate-100 hover:bg-slate-100/60 transition-colors">
                                    <div>
                                        <span class="text-[10px] text-slate-400 font-semibold block uppercase tracking-wider"><?php echo e($k); ?></span>
                                        <span class="font-bold text-slate-800 text-xs sm:text-sm <?php echo in_array($k, ['UPI', 'IFSC', 'Account No'], true) ? 'font-mono' : ''; ?>">
                                            <?php echo e($v); ?>
                                        </span>
                                    </div>
                                    <?php if ($isCopyable): ?>
                                        <button type="button" onclick="copyText('<?php echo e($v); ?>', this)"
                                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-white border border-slate-200 text-slate-600 hover:text-slate-900 text-[11px] font-semibold shadow-2xs hover:shadow-xs transition-all cursor-pointer">
                                            <i data-lucide="copy" class="w-3 h-3 text-slate-400"></i>
                                            <span>Copy</span>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if (file_exists(__DIR__ . '/assets/payment-qr.png')): ?>
                            <div class="mt-4 pt-3 border-t border-slate-100">
                                <button type="button" onclick="toggleQrCode()"
                                        class="w-full inline-flex items-center justify-center gap-2 py-2.5 px-3 rounded-xl border border-amber-200 bg-amber-50/70 text-amber-900 hover:bg-amber-100/70 text-xs font-semibold transition-all cursor-pointer">
                                    <i data-lucide="qr-code" class="w-4 h-4 text-amber-700"></i>
                                    <span>Show Official Payment QR Code</span>
                                </button>
                                <div id="qr-code-container" class="hidden mt-3 p-3 bg-slate-50 rounded-2xl border border-slate-200 text-center animate-fadeIn">
                                    <img src="assets/payment-qr.png" alt="Payment QR Code" loading="lazy" class="max-w-[200px] mx-auto rounded-xl shadow-xs border border-white">
                                    <p class="text-[11px] text-slate-500 mt-2 font-medium">Scan with PhonePe, GPay, Paytm or any UPI App</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>

                <!-- Right Column: Submission Form -->
                <div class="lg:col-span-7">
                    <form method="POST" enctype="multipart/form-data" class="bg-white rounded-3xl border border-slate-200/90 p-6 sm:p-7 shadow-soft space-y-5">
                        <div class="flex items-center gap-3 pb-4 border-b border-slate-100">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-[#f8efe0] to-[#f2dfc4] border border-[#d6b06b]/40 flex items-center justify-center text-[#c79435] shrink-0">
                                <i data-lucide="credit-card" class="w-5 h-5"></i>
                            </div>
                            <div>
                                <h3 class="text-base font-bold text-slate-900">Submit Payment Details</h3>
                                <p class="text-xs text-slate-500">Provide payment proof to activate your membership ID</p>
                            </div>
                        </div>

                        <!-- Step 1: Benefit Plan Choice -->
                        <div class="space-y-3 pt-1">
                            <div class="flex items-center justify-between flex-wrap gap-2">
                                <label class="block text-xs font-bold text-slate-800 uppercase tracking-wider">
                                    Step 1: Choose Your Joining Benefit Plan <span class="text-rose-500">*</span>
                                </label>
                                <span class="text-[11px] font-semibold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-200">
                                    100% Value Back
                                </span>
                            </div>
                            <p class="text-xs text-slate-500">
                                Select how you want to receive your ₹10,000 joining benefit upon activation:
                            </p>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 pt-1">
                                <!-- Option 1: Product Voucher (36M) -->
                                <label class="relative flex flex-col p-4 rounded-2xl border-2 cursor-pointer transition-all border-indigo-500 bg-indigo-50/40 hover:bg-indigo-50/70 shadow-xs" id="card-benefit-product">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="w-8 h-8 rounded-xl bg-indigo-100 text-indigo-700 flex items-center justify-center shrink-0">
                                            <i data-lucide="gift" class="w-4 h-4"></i>
                                        </div>
                                        <input type="radio" name="benefit_choice" value="product" checked class="mt-1 h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-slate-300 cursor-pointer" onchange="updateBenefitSelection('product')">
                                    </div>
                                    <div class="mt-3">
                                        <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-indigo-100 text-indigo-800 mb-1">
                                            36 Months Validity
                                        </span>
                                        <h4 class="text-sm font-bold text-slate-900 leading-snug">Product Purchase Voucher</h4>
                                        <p class="text-xs font-extrabold text-indigo-700 mt-0.5">₹10,000 Shopping Credit</p>
                                        <p class="text-[11px] text-slate-500 mt-1 leading-relaxed">
                                            Redeem anytime within 3 years for Electric Scooter bookings or company products.
                                        </p>
                                    </div>
                                </label>

                                <!-- Option 2: Monthly Cashback (10M) -->
                                <label class="relative flex flex-col p-4 rounded-2xl border-2 cursor-pointer transition-all border-slate-200 bg-white hover:bg-slate-50 shadow-xs" id="card-benefit-cashback">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">
                                            <i data-lucide="coins" class="w-4 h-4"></i>
                                        </div>
                                        <input type="radio" name="benefit_choice" value="cashback" class="mt-1 h-4 w-4 text-emerald-600 focus:ring-emerald-500 border-slate-300 cursor-pointer" onchange="updateBenefitSelection('cashback')">
                                    </div>
                                    <div class="mt-3">
                                        <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-emerald-100 text-emerald-800 mb-1">
                                            10 Months Plan
                                        </span>
                                        <h4 class="text-sm font-bold text-slate-900 leading-snug">Monthly Cashback Plan</h4>
                                        <p class="text-xs font-extrabold text-emerald-700 mt-0.5">₹1,000 / month (₹10k Total)</p>
                                        <p class="text-[11px] text-slate-500 mt-1 leading-relaxed">
                                            Auto-credited every month directly to your withdrawable Main Wallet for 10 months.
                                        </p>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Step 2 Divider -->
                        <div class="pt-2 border-t border-slate-100">
                            <label class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1">
                                Step 2: Payment Verification Details
                            </label>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- Amount paid (read-only) -->
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5 uppercase tracking-wider">
                                    Amount Paid
                                </label>
                                <div class="relative">
                                    <input type="text" value="<?php echo money($bookingAmount); ?>" readonly
                                           class="w-full border border-slate-200 rounded-xl px-3.5 py-2.5 text-sm font-bold bg-slate-50 text-slate-800 cursor-not-allowed">
                                    <span class="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400">
                                        <i data-lucide="lock" class="w-4 h-4"></i>
                                    </span>
                                </div>
                                <p class="text-[11px] text-slate-400 mt-1">Fixed one-time joining qualifying fee</p>
                            </div>

                            <!-- Payment mode -->
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5 uppercase tracking-wider">
                                    Payment Mode <span class="text-rose-500">*</span>
                                </label>
                                <select name="payment_mode" required
                                        class="w-full border border-slate-200 focus:border-[#c79435] focus:ring-2 focus:ring-[#c79435]/20 rounded-xl px-3.5 py-2.5 text-sm bg-white text-slate-800 transition-all">
                                    <option value="">Select payment method…</option>
                                    <?php foreach (['UPI', 'IMPS', 'NEFT', 'RTGS', 'Cash Deposit'] as $mode): ?>
                                        <option value="<?php echo $mode; ?>" <?php echo (($_POST['payment_mode'] ?? '') === $mode) ? 'selected' : ''; ?>>
                                            <?php echo $mode; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="text-[11px] text-slate-400 mt-1">Method used to make the transfer</p>
                            </div>

                            <!-- Transaction / UTR number -->
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-bold text-slate-700 mb-1.5 uppercase tracking-wider">
                                    Transaction / UTR Number <span class="text-rose-500">*</span>
                                </label>
                                <div class="relative">
                                    <input type="text" name="transaction_no" required value="<?php echo e($_POST['transaction_no'] ?? ''); ?>"
                                           placeholder="e.g. 12-digit UTR, IMPS Ref, or Bank Transaction ID"
                                           class="w-full border border-slate-200 focus:border-[#c79435] focus:ring-2 focus:ring-[#c79435]/20 rounded-xl px-3.5 py-2.5 text-sm font-mono bg-white text-slate-800 transition-all placeholder:font-sans placeholder:text-slate-400">
                                    <span class="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400">
                                        <i data-lucide="hash" class="w-4 h-4"></i>
                                    </span>
                                </div>
                                <p class="text-[11px] text-slate-500 mt-1">Found in your payment receipt or bank SMS/App confirmation</p>
                            </div>

                            <!-- Payment receipt upload -->
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-bold text-slate-700 mb-1.5 uppercase tracking-wider">
                                    Payment Receipt / Screenshot <span class="text-rose-500">*</span>
                                </label>

                                <label id="receipt-dropzone" for="receipt-input"
                                       class="border-2 border-dashed border-slate-300 hover:border-[#c79435] rounded-2xl p-5 text-center transition-all cursor-pointer bg-slate-50/50 hover:bg-amber-50/20 block">
                                    <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center mx-auto mb-2 border border-amber-200/60">
                                        <i data-lucide="upload-cloud" class="w-5 h-5"></i>
                                    </div>
                                    <p class="text-xs font-semibold text-slate-700">Click to choose or drag &amp; drop payment receipt</p>
                                    <p class="text-[11px] text-slate-400 mt-0.5">JPG, PNG or PDF (Maximum size: 5MB)</p>
                                    <p id="receipt-file-name" class="hidden mt-2 text-xs font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 px-3 py-1 rounded-lg inline-block"></p>
                                </label>
                                <input id="receipt-input" type="file" name="receipt" accept=".jpg,.jpeg,.png,.pdf" required class="hidden">
                            </div>

                            <!-- Remark (optional) -->
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-bold text-slate-700 mb-1.5 uppercase tracking-wider">
                                    Remark / Note <span class="text-slate-400 font-normal">(Optional)</span>
                                </label>
                                <textarea name="remark" rows="2" placeholder="Any additional notes or transaction details..."
                                          class="w-full border border-slate-200 focus:border-[#c79435] focus:ring-2 focus:ring-[#c79435]/20 rounded-xl p-3 text-sm bg-white text-slate-800 transition-all"><?php echo e($_POST['remark'] ?? ''); ?></textarea>
                            </div>
                        </div>

                        <div class="pt-2">
                            <button type="submit" name="place_order"
                                    onclick="return confirm('Confirm submit your booking request for <?php echo money($bookingAmount); ?>?');"
                                    class="w-full py-3.5 px-6 rounded-xl bg-gradient-to-r from-[#c79435] to-[#b3822c] hover:from-[#b98422] hover:to-[#9f7121] text-white font-bold text-sm shadow-md hover:shadow-lg transition-all duration-200 flex items-center justify-center gap-2 cursor-pointer">
                                <i data-lucide="check-circle-2" class="w-4 h-4"></i>
                                <span>Submit Booking &amp; Request Activation</span>
                            </button>
                            <p class="text-center text-[11px] text-slate-400 mt-2.5">
                                Our admin verification desk verifies payments quickly within 1-2 hours.
                            </p>
                        </div>
                    </form>
                </div>

            </div>

        <?php endif; ?>

    </main>
</div>

<script>
    lucide.createIcons();

    function copyText(text, btn) {
        if (!text) return;
        if (!navigator.clipboard) {
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            try {
                document.execCommand('copy');
                showCopyFeedback(btn);
            } catch (e) {}
            document.body.removeChild(ta);
            return;
        }
        navigator.clipboard.writeText(text).then(() => {
            showCopyFeedback(btn);
        }).catch(() => {});
    }

    function showCopyFeedback(btn) {
        if (!btn) return;
        const origHTML = btn.innerHTML;
        btn.innerHTML = '<i data-lucide="check" class="w-3.5 h-3.5 text-emerald-600 inline"></i> <span class="text-emerald-700 text-[11px] font-semibold">Copied!</span>';
        lucide.createIcons();
        setTimeout(() => {
            btn.innerHTML = origHTML;
            lucide.createIcons();
        }, 1600);
    }

    const receiptInput = document.getElementById('receipt-input');
    const receiptFileName = document.getElementById('receipt-file-name');
    const receiptDropzone = document.getElementById('receipt-dropzone');

    if (receiptInput && receiptFileName) {
        receiptInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const file = this.files[0];
                const sizeMB = (file.size / (1024 * 1024)).toFixed(2);
                receiptFileName.textContent = file.name + ' (' + sizeMB + ' MB)';
                receiptFileName.classList.remove('hidden');
                if (receiptDropzone) {
                    receiptDropzone.classList.add('border-emerald-500', 'bg-emerald-50/40');
                }
            } else {
                receiptFileName.textContent = '';
                receiptFileName.classList.add('hidden');
                if (receiptDropzone) {
                    receiptDropzone.classList.remove('border-emerald-500', 'bg-emerald-50/40');
                }
            }
        });
    }

    function toggleQrCode() {
        const el = document.getElementById('qr-code-container');
        if (!el) return;
        el.classList.toggle('hidden');
    }

    function updateBenefitSelection(choice) {
        const cardProd = document.getElementById('card-benefit-product');
        const cardCash = document.getElementById('card-benefit-cashback');
        if (!cardProd || !cardCash) return;
        if (choice === 'cashback') {
            cardCash.className = 'relative flex flex-col p-4 rounded-2xl border-2 cursor-pointer transition-all border-emerald-500 bg-emerald-50/40 hover:bg-emerald-50/70 shadow-xs';
            cardProd.className = 'relative flex flex-col p-4 rounded-2xl border-2 cursor-pointer transition-all border-slate-200 bg-white hover:bg-slate-50 shadow-xs';
        } else {
            cardProd.className = 'relative flex flex-col p-4 rounded-2xl border-2 cursor-pointer transition-all border-indigo-500 bg-indigo-50/40 hover:bg-indigo-50/70 shadow-xs';
            cardCash.className = 'relative flex flex-col p-4 rounded-2xl border-2 cursor-pointer transition-all border-slate-200 bg-white hover:bg-slate-50 shadow-xs';
        }
    }
</script>
</body>
</html>
