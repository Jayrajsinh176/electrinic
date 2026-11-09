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
require_once __DIR__ . '/../includes/bp_package_functions.php';

$successMsg = '';
$errorMsg   = '';

// Fetch user current wallet balance
$userBal = 0.00;
$stU = $conn->prepare("SELECT wallet_balance FROM signup_users WHERE customer_id = ? LIMIT 1");
if ($stU) {
    $stU->bind_param('s', $customerId);
    $stU->execute();
    $uRow = $stU->get_result()->fetch_assoc();
    $userBal = (float)($uRow['wallet_balance'] ?? 0.00);
    $stU->close();
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');
    $pkgId  = (int)($_POST['package_id'] ?? 0);

    if ($action === 'pay_wallet') {
        $res = bp_subscribe_from_wallet($conn, $customerId, $pkgId);
        if ($res['ok']) {
            $successMsg = $res['message'];
            // Refresh wallet balance
            $stU = $conn->prepare("SELECT wallet_balance FROM signup_users WHERE customer_id = ? LIMIT 1");
            $stU->bind_param('s', $customerId);
            $stU->execute();
            $userBal = (float)($stU->get_result()->fetch_assoc()['wallet_balance'] ?? 0.00);
            $stU->close();
        } else {
            $errorMsg = $res['message'];
        }
    } elseif ($action === 'pay_bank') {
        $payMode = trim($_POST['payment_mode'] ?? 'UPI');
        $trxId   = trim($_POST['transaction_id'] ?? '');

        if ($trxId === '') {
            $errorMsg = "Please enter your Transaction / UTR reference number.";
        } else {
            $slipPath = '';
            if (!empty($_FILES['payment_slip']['name'])) {
                $uploadDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'slips';
                if (!is_dir($uploadDir)) @mkdir($uploadDir, 0777, true);

                $ext = strtolower(pathinfo($_FILES['payment_slip']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'pdf', 'webp'], true)) {
                    $fileName = 'SLIP-BP-' . date('YmdHis') . '-' . substr(md5(uniqid()), 0, 6) . '.' . $ext;
                    $dest = $uploadDir . DIRECTORY_SEPARATOR . $fileName;
                    if (move_uploaded_file($_FILES['payment_slip']['tmp_name'], $dest)) {
                        $slipPath = 'uploads/slips/' . $fileName;
                    }
                }
            }

            $res = bp_request_from_bank($conn, $customerId, $pkgId, $payMode, $trxId, $slipPath);
            if ($res['ok']) {
                $successMsg = $res['message'];
            } else {
                $errorMsg = $res['message'];
            }
        }
    }
}

// Fetch Packages & My Subscriptions
$packages = bp_get_active_packages($conn);
$mySubs   = bp_get_member_subscriptions($conn, $customerId);

$totalInvested = 0.00;
$totalReceived = 0.00;
$activeCount   = 0;
foreach ($mySubs as $s) {
    if ($s['status'] === 'active' || $s['status'] === 'completed') {
        $totalInvested += (float)$s['package_amount'];
        $totalReceived += (float)$s['total_paid'];
        if ($s['status'] === 'active') $activeCount++;
    }
}

$bankDetails = [
    'Account Name' => 'NETVEXA PVT LTD.',
    'Account No'   => '000000XXXX',
    'IFSC'         => 'SBIN000XXXX',
    'Bank'         => 'State Bank of India',
    'Branch'       => 'Kopar Khairane, Navi Mumbai',
    'UPI'          => 'netvexa@phonepe',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Brand Promotion Packages</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body { background: #f3f3f3; }
        @media (min-width: 1024px) { .main-wrap { margin-left: 256px; } }
        .pkg-card { transition: transform .2s, box-shadow .2s; }
        .pkg-card:hover { transform: translateY(-4px); box-shadow: 0 12px 28px rgba(16, 50, 88, 0.14); }
    </style>
</head>
<body class="text-slate-800">

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="main-wrap min-h-screen">
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <div class="px-4 sm:px-6 py-5">

        <!-- Title Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-[#e2d8c8] pb-4">
            <div>
                <div class="flex items-center gap-2">
                    <span class="w-8 h-8 rounded-lg bg-[#103258] text-[#DDBE7E] flex items-center justify-center font-bold text-sm">
                        <i data-lucide="award" class="w-4 h-4"></i>
                    </span>
                    <h1 class="text-xl sm:text-2xl font-bold text-[#103258]">Brand Promotion Packages</h1>
                </div>
                <p class="text-xs text-slate-500 mt-1">10-Month Promotional Return Plan with 5% Direct Bonus & 10-Root Affiliate Marketing.</p>
            </div>
            <div class="bg-white border border-[#254A73]/20 rounded-xl px-4 py-2.5 flex items-center gap-3 shadow-sm">
                <i data-lucide="wallet" class="w-5 h-5 text-[#DDBE7E]"></i>
                <div>
                    <p class="text-[10px] uppercase font-bold text-slate-400">Available Wallet</p>
                    <p class="text-base font-bold text-[#103258]">₹<?php echo number_format($userBal, 2); ?></p>
                </div>
            </div>
        </div>

        <!-- Flash Messages -->
        <?php if ($successMsg): ?>
            <div class="mt-4 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-center gap-3">
                <i data-lucide="check-circle" class="w-5 h-5 text-emerald-600 shrink-0"></i>
                <span><?php echo htmlspecialchars($successMsg); ?></span>
            </div>
        <?php endif; ?>
        <?php if ($errorMsg): ?>
            <div class="mt-4 p-4 rounded-xl bg-red-50 border border-red-200 text-red-800 text-sm flex items-center gap-3">
                <i data-lucide="alert-circle" class="w-5 h-5 text-red-600 shrink-0"></i>
                <span><?php echo htmlspecialchars($errorMsg); ?></span>
            </div>
        <?php endif; ?>

        <!-- Summary Metric Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-5">
            <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm">
                <p class="text-[11px] uppercase font-bold text-slate-400">Total Packages Subscribed</p>
                <p class="text-2xl font-extrabold text-[#103258] mt-1">₹<?php echo number_format($totalInvested, 2); ?></p>
                <p class="text-xs text-emerald-600 font-medium mt-1"><?php echo $activeCount; ?> Active Subscription(s)</p>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm">
                <p class="text-[11px] uppercase font-bold text-slate-400">Total Returns Received</p>
                <p class="text-2xl font-extrabold text-emerald-600 mt-1">₹<?php echo number_format($totalReceived, 2); ?></p>
                <p class="text-xs text-slate-500 mt-1">10% monthly payout progress</p>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm">
                <p class="text-[11px] uppercase font-bold text-slate-400">Duration & Benefits</p>
                <p class="text-2xl font-extrabold text-[#DDBE7E] mt-1">10 Months</p>
                <p class="text-xs text-slate-500 mt-1">100% Capital Return + 51% Affiliate Pool</p>
            </div>
        </div>

        <!-- 4 Package Cards Section -->
        <div class="mt-8">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-lg font-bold text-[#103258]">Available Packages</h2>
                    <p class="text-xs text-slate-500">Select a package to start receiving monthly promotional returns.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5">
                <?php 
                $cardColors = [
                    'BP-10K' => ['grad' => 'from-slate-700 to-slate-900', 'badge' => 'bg-slate-200 text-slate-800'],
                    'BP-50K' => ['grad' => 'from-amber-600 to-amber-800', 'badge' => 'bg-amber-100 text-amber-800'],
                    'BP-1L'  => ['grad' => 'from-blue-700 to-indigo-900', 'badge' => 'bg-blue-100 text-blue-800'],
                    'BP-2L'  => ['grad' => 'from-purple-800 to-indigo-950', 'badge' => 'bg-purple-100 text-purple-800'],
                ];

                foreach ($packages as $pkg): 
                    $pCode = $pkg['package_code'];
                    $style = $cardColors[$pCode] ?? ['grad' => 'from-[#103258] to-[#1a4a7d]', 'badge' => 'bg-[#DDBE7E] text-[#103258]'];
                ?>
                    <div class="pkg-card bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm flex flex-col justify-between">
                        <!-- Top Header -->
                        <div class="bg-gradient-to-br <?php echo $style['grad']; ?> p-5 text-white relative">
                            <?php if (!empty($pkg['badge_text'])): ?>
                                <span class="absolute top-4 right-4 text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full <?php echo $style['badge']; ?>">
                                    <?php echo htmlspecialchars($pkg['badge_text']); ?>
                                </span>
                            <?php endif; ?>
                            <p class="text-xs uppercase font-semibold text-white/70 tracking-widest"><?php echo htmlspecialchars($pkg['package_name']); ?></p>
                            <h3 class="text-3xl font-black mt-2">₹<?php echo number_format($pkg['price'], 0); ?></h3>
                            <p class="text-[11px] text-white/80 mt-1">One-time payment</p>
                        </div>

                        <!-- Card Body -->
                        <div class="p-5 flex-1 flex flex-col justify-between space-y-4">
                            <ul class="space-y-2.5 text-xs text-slate-600">
                                <li class="flex items-center gap-2">
                                    <i data-lucide="check" class="w-4 h-4 text-emerald-500 shrink-0"></i>
                                    <span><strong>₹<?php echo number_format($pkg['monthly_return'], 0); ?> / Month</strong> for 10 Months</span>
                                </li>
                                <li class="flex items-center gap-2">
                                    <i data-lucide="check" class="w-4 h-4 text-emerald-500 shrink-0"></i>
                                    <span>Total Return: <strong>₹<?php echo number_format($pkg['total_return'], 0); ?></strong> (100% Back)</span>
                                </li>
                                <li class="flex items-center gap-2">
                                    <i data-lucide="check" class="w-4 h-4 text-emerald-500 shrink-0"></i>
                                    <span>Direct Sponsor Bonus: <strong>5% (₹<?php echo number_format($pkg['price'] * 0.05, 0); ?>)</strong></span>
                                </li>
                                <li class="flex items-center gap-2">
                                    <i data-lucide="check" class="w-4 h-4 text-emerald-500 shrink-0"></i>
                                    <span>10-Root Affiliate: <strong>51% Pool</strong> for 10 Mo</span>
                                </li>
                            </ul>

                            <!-- Action Buttons -->
                            <div class="pt-3 border-t border-slate-100 flex flex-col gap-2">
                                <button type="button" 
                                        onclick="openWalletModal(<?php echo (int)$pkg['id']; ?>, '<?php echo addslashes($pkg['package_name']); ?>', <?php echo (float)$pkg['price']; ?>)"
                                        class="w-full py-2.5 px-3 rounded-xl bg-[#103258] hover:bg-[#1A3F66] text-white font-semibold text-xs transition flex items-center justify-center gap-1.5 shadow-sm">
                                    <i data-lucide="zap" class="w-3.5 h-3.5 text-[#DDBE7E]"></i>
                                    <span>Pay from Wallet (Instant)</span>
                                </button>
                                <button type="button" 
                                        onclick="openBankModal(<?php echo (int)$pkg['id']; ?>, '<?php echo addslashes($pkg['package_name']); ?>', <?php echo (float)$pkg['price']; ?>)"
                                        class="w-full py-2 px-3 rounded-xl border border-slate-300 hover:bg-slate-50 text-slate-700 font-semibold text-xs transition flex items-center justify-center gap-1.5">
                                    <i data-lucide="building" class="w-3.5 h-3.5 text-slate-500"></i>
                                    <span>Pay via Bank / UPI</span>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- My Subscriptions History -->
        <div class="mt-10 bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
            <h2 class="text-base font-bold text-[#103258] flex items-center gap-2">
                <i data-lucide="history" class="w-4 h-4 text-[#DDBE7E]"></i>
                <span>My Subscribed Packages</span>
            </h2>

            <?php if (empty($mySubs)): ?>
                <div class="py-8 text-center text-slate-400 text-xs">
                    You have not subscribed to any Brand Promotion packages yet. Choose a package above to begin!
                </div>
            <?php else: ?>
                <div class="overflow-x-auto mt-4">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-slate-50 text-slate-500 uppercase tracking-wider font-semibold border-b border-slate-200">
                            <tr>
                                <th class="py-3 px-3">Subscription</th>
                                <th class="py-3 px-3">Package</th>
                                <th class="py-3 px-3">Amount</th>
                                <th class="py-3 px-3">Monthly Return</th>
                                <th class="py-3 px-3">10-Mo Progress</th>
                                <th class="py-3 px-3">Next Payout</th>
                                <th class="py-3 px-3">Payment</th>
                                <th class="py-3 px-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($mySubs as $sub): 
                                $pctProgress = min(100, round(($sub['months_paid'] / $sub['duration_months']) * 100));
                                $badgeColor = match($sub['status']) {
                                    'active' => 'bg-emerald-100 text-emerald-800',
                                    'completed' => 'bg-blue-100 text-blue-800',
                                    'pending' => 'bg-amber-100 text-amber-800',
                                    'rejected' => 'bg-red-100 text-red-800',
                                    default => 'bg-slate-100 text-slate-800'
                                };
                            ?>
                                <tr class="hover:bg-slate-50">
                                    <td class="py-3 px-3 font-mono font-medium text-slate-700"><?php echo htmlspecialchars($sub['subscription_code']); ?></td>
                                    <td class="py-3 px-3 font-semibold text-[#103258]"><?php echo htmlspecialchars($sub['package_name']); ?></td>
                                    <td class="py-3 px-3 font-bold text-slate-900">₹<?php echo number_format($sub['package_amount'], 2); ?></td>
                                    <td class="py-3 px-3 font-semibold text-emerald-600">₹<?php echo number_format($sub['monthly_payout'], 2); ?>/mo</td>
                                    <td class="py-3 px-3">
                                        <div class="w-32">
                                            <div class="flex justify-between text-[10px] text-slate-500 mb-0.5">
                                                <span>Month <?php echo (int)$sub['months_paid']; ?> / <?php echo (int)$sub['duration_months']; ?></span>
                                                <span><?php echo $pctProgress; ?>%</span>
                                            </div>
                                            <div class="w-full bg-slate-200 h-1.5 rounded-full overflow-hidden">
                                                <div class="bg-emerald-500 h-full rounded-full" style="width: <?php echo $pctProgress; ?>%"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3 px-3 text-slate-600">
                                        <?php echo $sub['next_payout_date'] ? date('d M Y', strtotime($sub['next_payout_date'])) : '—'; ?>
                                    </td>
                                    <td class="py-3 px-3 text-slate-500">
                                        <span class="inline-block px-2 py-0.5 bg-slate-100 rounded text-[11px]"><?php echo htmlspecialchars($sub['payment_mode']); ?></span>
                                    </td>
                                    <td class="py-3 px-3">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider <?php echo $badgeColor; ?>">
                                            <?php echo ucfirst($sub['status']); ?>
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

<!-- Modal 1: Wallet Instant Payment -->
<div id="walletModal" class="fixed inset-0 bg-black/60 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-100 animate-in fade-in duration-200">
        <div class="flex items-center justify-between border-b pb-3 border-slate-100">
            <h3 class="text-base font-bold text-[#103258] flex items-center gap-2">
                <i data-lucide="zap" class="w-4 h-4 text-[#DDBE7E]"></i>
                <span>Subscribe via Wallet</span>
            </h3>
            <button onclick="closeWalletModal()" class="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
        </div>

        <form method="POST" class="mt-4 space-y-4">
            <input type="hidden" name="action" value="pay_wallet">
            <input type="hidden" name="package_id" id="wallet_package_id" value="">

            <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 text-xs space-y-1.5">
                <div class="flex justify-between">
                    <span class="text-slate-500">Selected Plan:</span>
                    <span class="font-bold text-[#103258]" id="wallet_pkg_name">—</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Package Cost:</span>
                    <span class="font-bold text-slate-900" id="wallet_pkg_price">—</span>
                </div>
                <div class="flex justify-between border-t border-slate-200 pt-1">
                    <span class="text-slate-500">Your Available Balance:</span>
                    <span class="font-bold text-emerald-600">₹<?php echo number_format($userBal, 2); ?></span>
                </div>
            </div>

            <p class="text-[11px] text-slate-500">
                Amount will be instantly deducted from your Main Wallet balance. Your 10-month return schedule will activate immediately.
            </p>

            <div class="flex gap-2 pt-2">
                <button type="button" onclick="closeWalletModal()" class="w-1/2 py-2.5 rounded-xl border border-slate-300 text-xs font-semibold text-slate-600 hover:bg-slate-50">Cancel</button>
                <button type="submit" class="w-1/2 py-2.5 rounded-xl bg-[#103258] hover:bg-[#1A3F66] text-white text-xs font-semibold shadow">Confirm & Pay</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 2: Bank / UPI Payment -->
<div id="bankModal" class="fixed inset-0 bg-black/60 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-100 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b pb-3 border-slate-100">
            <h3 class="text-base font-bold text-[#103258] flex items-center gap-2">
                <i data-lucide="building" class="w-4 h-4 text-[#DDBE7E]"></i>
                <span>Subscribe via Bank / UPI</span>
            </h3>
            <button onclick="closeBankModal()" class="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
        </div>

        <form method="POST" enctype="multipart/form-data" class="mt-4 space-y-4">
            <input type="hidden" name="action" value="pay_bank">
            <input type="hidden" name="package_id" id="bank_package_id" value="">

            <div class="p-3 bg-amber-50/60 rounded-xl border border-amber-200 text-xs space-y-1">
                <div class="flex justify-between">
                    <span class="text-slate-600">Plan: <strong class="text-[#103258]" id="bank_pkg_name">—</strong></span>
                    <span>Payable: <strong class="text-slate-900" id="bank_pkg_price">—</strong></span>
                </div>
            </div>

            <!-- Company Bank Details Box -->
            <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 text-xs space-y-1">
                <p class="font-bold text-[#103258] mb-1">Company Bank Details:</p>
                <?php foreach ($bankDetails as $lbl => $val): ?>
                    <div class="flex justify-between text-slate-600">
                        <span><?php echo $lbl; ?>:</span>
                        <strong class="text-slate-900"><?php echo $val; ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Payment Mode</label>
                <select name="payment_mode" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs bg-white focus:outline-none focus:ring-2 focus:ring-[#103258]">
                    <option value="UPI">UPI / QR Code</option>
                    <option value="IMPS">IMPS</option>
                    <option value="NEFT">NEFT</option>
                    <option value="RTGS">RTGS</option>
                    <option value="Cash Deposit">Cash Deposit at Bank</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Transaction / UTR Reference Number *</label>
                <input type="text" name="transaction_id" required placeholder="e.g. 238910481920"
                       class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[#103258]">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Upload Payment Receipt Screenshot (Optional)</label>
                <input type="file" name="payment_slip" accept="image/*,application/pdf"
                       class="w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200">
            </div>

            <div class="flex gap-2 pt-2">
                <button type="button" onclick="closeBankModal()" class="w-1/2 py-2.5 rounded-xl border border-slate-300 text-xs font-semibold text-slate-600 hover:bg-slate-50">Cancel</button>
                <button type="submit" class="w-1/2 py-2.5 rounded-xl bg-[#103258] hover:bg-[#1A3F66] text-white text-xs font-semibold shadow">Submit Request</button>
            </div>
        </form>
    </div>
</div>

<script>
    lucide.createIcons();

    function openWalletModal(id, name, price) {
        document.getElementById('wallet_package_id').value = id;
        document.getElementById('wallet_pkg_name').innerText = name;
        document.getElementById('wallet_pkg_price').innerText = '₹' + price.toLocaleString('en-IN');
        document.getElementById('walletModal').classList.remove('hidden');
    }
    function closeWalletModal() {
        document.getElementById('walletModal').classList.add('hidden');
    }

    function openBankModal(id, name, price) {
        document.getElementById('bank_package_id').value = id;
        document.getElementById('bank_pkg_name').innerText = name;
        document.getElementById('bank_pkg_price').innerText = '₹' + price.toLocaleString('en-IN');
        document.getElementById('bankModal').classList.remove('hidden');
    }
    function closeBankModal() {
        document.getElementById('bankModal').classList.add('hidden');
    }
</script>

</body>
</html>

