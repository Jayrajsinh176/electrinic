<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentPage = basename($_SERVER['PHP_SELF'] ?? '');

$pageConfig = [
    // Core & Dashboard
    'dashboard.php'              => ['title' => 'Dashboard', 'icon' => 'layout-dashboard'],
    'activation.php'             => ['title' => 'Book / Activate', 'icon' => 'zap'],
    'activation-invoice.php'     => ['title' => 'Activation Invoice', 'icon' => 'file-text'],
    'order_status.php'           => ['title' => 'My Order Status', 'icon' => 'clipboard-list'],
    'scooter-verification.php'   => ['title' => 'Vehicle Verification & Upload', 'icon' => 'bike'],

    // Profile & KYC
    'profile.php'                => ['title' => 'My Profile', 'icon' => 'user-round'],
    'shipping-address.php'       => ['title' => 'My Shipping Details', 'icon' => 'map-pinned'],
    'bank-info.php'              => ['title' => 'My KYC & Bank Info', 'icon' => 'landmark'],
    'change-password.php'        => ['title' => 'Change Password', 'icon' => 'lock-keyhole'],
    'change-transaction-password.php' => ['title' => 'Change Transaction Password', 'icon' => 'badge-dollar-sign'],
    'welcome-letter.php'         => ['title' => 'Welcome Letter', 'icon' => 'mail-open'],

    // Team & Network
    'my-referral-list.php'       => ['title' => 'My Referral List', 'icon' => 'users'],
    'my-team-mates.php'          => ['title' => 'My Team Mates', 'icon' => 'network'],
    'tree-view.php'              => ['title' => 'Tree View', 'icon' => 'network'],

    // Wallets & Transactions
    'wallet.php'                 => ['title' => 'Wallet Overview', 'icon' => 'wallet'],
    'wallet-p2p.php'             => ['title' => 'P2P Transfer', 'icon' => 'arrow-left-right'],
    'wallet-bank.php'            => ['title' => 'Wallet to Bank', 'icon' => 'landmark'],
    'income-transactions.php'    => ['title' => 'All Income Transactions', 'icon' => 'receipt'],

    // IND Plan Income Reports
    'direct-sponsor-income.php'  => ['title' => 'Direct Sponsor Income', 'icon' => 'hand-coins'],
    'level-income.php'           => ['title' => 'Affiliate 10-Level Income', 'icon' => 'list-tree'],
    'auto-pool-income.php'       => ['title' => 'Auto Pool Income', 'icon' => 'layers'],
    'vehicle-commissions.php'    => ['title' => 'Vehicle Sales Commission', 'icon' => 'bike'],
    'scooter-promotion.php'      => ['title' => 'Scooter Brand Promotion', 'icon' => 'bike'],
    'joining-benefit.php'        => ['title' => 'Joining Benefit & Cashback', 'icon' => 'gift'],
    'performance-bonus.php'      => ['title' => 'Performance Bonus', 'icon' => 'trophy'],
    'team-performance-bonus.php' => ['title' => 'Team Performance Bonus', 'icon' => 'users-round'],
    'reward.php'                 => ['title' => 'Award & Reward', 'icon' => 'award'],

    // Communication / Support
    'compose.php'                => ['title' => 'Compose Message', 'icon' => 'messages-square'],
    'inbox.php'                  => ['title' => 'Inbox', 'icon' => 'messages-square'],
    'outbox.php'                 => ['title' => 'Outbox', 'icon' => 'messages-square'],
    'inbox-thread-details.php'   => ['title' => 'Message Details', 'icon' => 'messages-square'],
    'outbox-thread-details.php'  => ['title' => 'Message Details', 'icon' => 'messages-square'],

    // Legacy Fallbacks
    'member-payout-history.php'  => ['title' => 'Payout History', 'icon' => 'wallet'],
    'retail-profit.php'          => ['title' => 'Retail Profit', 'icon' => 'wallet'],
    'level-matching-bonus.php'   => ['title' => 'Level Matching Bonus', 'icon' => 'wallet'],
    'club-bonus.php'             => ['title' => 'Club Bonus', 'icon' => 'wallet'],
    'referral-bonus.php'         => ['title' => 'Referral Bonus', 'icon' => 'wallet'],
    'repurchase.php'             => ['title' => 'Repurchase', 'icon' => 'shopping-cart'],
];

$navTitle = $pageConfig[$currentPage]['title'] ?? 'Dashboard';
$navIcon  = $pageConfig[$currentPage]['icon'] ?? 'layout-dashboard';

$profileImage = $profileImage ?? 'https://i.pravatar.cc/80?img=12';
$managerName  = $_SESSION['full_name'] ?? ($managerName ?? 'Member');
$managerRole  = $managerRole ?? 'Customer';
$customerId   = $_SESSION['customer_id'] ?? ($customerId ?? '');
?>

<div class="bg-white border-b border-gray-300 h-[76px] flex items-center">
    <div class="w-full px-6">
        <div class="flex items-start justify-between gap-4">
            <!-- Left -->
            <div class="flex items-start gap-3 min-w-0">
                <button type="button" class="md:hidden h-10 w-10 rounded-md border border-gray-300 text-[#173a67] flex items-center justify-center shrink-0" onclick="openSidebar()" aria-label="Open menu">
                    <i data-lucide="menu" class="w-5 h-5"></i>
                </button>

                <div class="flex flex-col justify-center min-w-0">
                <div class="flex items-center gap-2 text-[#173a67] font-semibold text-[18px] leading-none min-w-0">
                    <i data-lucide="<?php echo htmlspecialchars($navIcon); ?>" class="w-4 h-4 shrink-0"></i>
                    <span class="truncate"><?php echo htmlspecialchars($navTitle); ?></span>
                </div>

                <p class="mt-4 md:mt-5 text-[11px] md:text-[12px] uppercase tracking-wide text-gray-400 leading-none truncate max-w-[55vw] md:max-w-none">
                    CUSTOMER ID: <?php echo htmlspecialchars($customerId); ?>
                </p>
                </div>
            </div>

            <!-- Right -->
            <div class="flex items-center gap-3 shrink-0">
                <div class="relative z-[70]" id="navProfileMenu">
                    <button type="button" onclick="navToggleProfileMenu(event)" class="flex items-center gap-3 focus:outline-none">
                        <img
                            src="<?php echo htmlspecialchars($profileImage); ?>"
                            alt="Profile"
                            class="h-10 w-10 rounded-full object-cover border border-gray-300 shrink-0"
                        >
                        <div class="leading-tight text-left hidden sm:block">
                            <p class="text-[14px] font-semibold text-[#1f2d3d] leading-none truncate max-w-[220px]">
                                <?php echo htmlspecialchars($managerName); ?>
                            </p>
                            <p class="text-[12px] text-gray-500 mt-1 leading-none">
                                <?php echo htmlspecialchars($managerRole); ?>
                            </p>
                        </div>
                        <i data-lucide="chevron-down" class="w-4 h-4 text-gray-400 shrink-0 hidden sm:block"></i>
                    </button>

                    <div id="navProfileDropdown" class="hidden absolute right-0 top-[calc(100%+10px)] w-52 bg-white rounded-xl border border-gray-200 shadow-lg py-2 z-[70]">
                        <div class="px-4 py-2 border-b border-gray-100">
                            <p class="text-[13px] font-semibold text-[#1f2d3d] truncate"><?php echo htmlspecialchars($managerName); ?></p>
                            <p class="text-[11px] text-gray-400 truncate"><?php echo htmlspecialchars($customerId); ?></p>
                        </div>
                        <a href="profile.php" class="flex items-center gap-2.5 px-4 py-2.5 text-[13px] text-gray-700 hover:bg-[#FBF8F2]">
                            <span class="h-7 w-7 rounded-lg flex items-center justify-center shrink-0" style="background:#FFF3E7">
                                <i data-lucide="user-round" class="w-3.5 h-3.5" style="color:#B25400"></i>
                            </span>
                            My Profile
                        </a>
                        <a href="logout.php" class="flex items-center gap-2.5 px-4 py-2.5 text-[13px] text-red-600 hover:bg-red-50">
                            <span class="h-7 w-7 rounded-lg flex items-center justify-center shrink-0 bg-red-50">
                                <i data-lucide="log-out" class="w-3.5 h-3.5"></i>
                            </span>
                            Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }

    function navToggleProfileMenu(evt) {
        evt.stopPropagation();
        var dd = document.getElementById('navProfileDropdown');
        if (dd) dd.classList.toggle('hidden');
    }
    document.addEventListener('click', function (evt) {
        var wrap = document.getElementById('navProfileMenu');
        var dd = document.getElementById('navProfileDropdown');
        if (wrap && dd && !wrap.contains(evt.target)) dd.classList.add('hidden');
    });
</script>