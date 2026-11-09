<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/inventory.php';   // pulls customers.php (frc_sales_*)
if (!isset($conn) || !($conn instanceof mysqli)) die('Database connection failed.');

$fid = (int)$franchiseId;                           // authenticated franchise PK — never from URL

$today = date('Y-m-d');
$defFrom = date('Y-m-01');                          // default: this month
$o = [
    'from'     => trim((string)($_GET['from'] ?? $defFrom)),
    'to'       => trim((string)($_GET['to'] ?? $today)),
    'payment'  => trim((string)($_GET['payment'] ?? '')),
    'delivery' => trim((string)($_GET['delivery'] ?? '')),
    'q'        => trim((string)($_GET['q'] ?? '')),
];

// Ensure any purchases without sale_date are safely set to their creation date
$conn->query("UPDATE fr_customer_purchases SET sale_date = DATE(created_at) WHERE (sale_date IS NULL OR sale_date = '0000-00-00') AND created_at IS NOT NULL");

$rows    = frc_sales_list($conn, $fid, $o, 1000);
$sum     = frc_sales_summary($conn, $fid, $o);

function sl_money($n) { return '₹' . number_format((float)$n, 2); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sales Report</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="assets/report-export.js"></script>
</head>
<body class="bg-[#f3f3f3] font-sans overflow-x-hidden">
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<div class="ml-64 min-h-screen bg-[#f3f3f3]">
    <?php include __DIR__ . '/includes/navbar.php'; ?>
    <main class="px-4 sm:px-5 py-6">
        <div class="max-w-7xl mx-auto space-y-6">

            <!-- header -->
            <div class="bg-[#17457a] rounded-xl px-5 sm:px-6 py-4 text-white shadow flex items-center justify-between flex-wrap gap-3">
                <div>
                    <h1 class="text-[18px] sm:text-[20px] font-semibold flex items-center gap-2">
                        <i data-lucide="bar-chart-3" class="w-5 h-5"></i>
                        Sales Report &amp; Analytics
                    </h1>
                    <p class="text-[12px] text-white/80 mt-1"><?php echo e($franchiseName); ?> · <?php echo e($franchiseCode); ?> · audited billing summary, tax breakdown &amp; export</p>
                </div>
                <div class="flex gap-2">
                    <a href="sales.php" class="bg-white/15 hover:bg-white/25 rounded-lg px-3 py-2 text-[12px] font-medium flex items-center gap-1.5"><i data-lucide="list-ordered" class="w-4 h-4"></i> Sales &amp; Bookings</a>
                    <a href="pos.php" class="bg-white/20 hover:bg-white/30 rounded-lg px-3 py-2 text-[12px] font-semibold flex items-center gap-1.5"><i data-lucide="plus-circle" class="w-4 h-4"></i> New Sale / Booking</a>
                    <a href="turnover.php" class="bg-white/15 hover:bg-white/25 rounded-lg px-3 py-2 text-[12px]">Turnover</a>
                </div>
            </div>

            <!-- filters -->
            <form method="get" class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3 text-[13px] items-end">
                <label class="block"><span class="text-gray-500 text-[11px]">From</span>
                    <input type="date" name="from" value="<?php echo e($o['from']); ?>" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2"></label>
                <label class="block"><span class="text-gray-500 text-[11px]">To</span>
                    <input type="date" name="to" value="<?php echo e($o['to']); ?>" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2"></label>
                <label class="block"><span class="text-gray-500 text-[11px]">Payment</span>
                    <select name="payment" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 bg-white">
                        <option value="">Any</option>
                        <?php foreach (['Paid', 'Pending', 'Partial'] as $ps): ?>
                            <option <?php echo $o['payment'] === $ps ? 'selected' : ''; ?>><?php echo $ps; ?></option>
                        <?php endforeach; ?>
                    </select></label>
                <label class="block"><span class="text-gray-500 text-[11px]">Delivery</span>
                    <select name="delivery" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 bg-white">
                        <option value="">Any</option>
                        <?php foreach (['Pending', 'Delivered'] as $ds): ?>
                            <option <?php echo $o['delivery'] === $ds ? 'selected' : ''; ?>><?php echo $ds; ?></option>
                        <?php endforeach; ?>
                    </select></label>
                <label class="block lg:col-span-1"><span class="text-gray-500 text-[11px]">Search</span>
                    <input name="q" value="<?php echo e($o['q']); ?>" placeholder="product / invoice / customer" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2"></label>
                <div class="flex gap-2">
                    <button class="bg-[#17457a] text-white rounded-lg px-4 py-2 text-[13px]">Apply</button>
                    <a href="sales-report.php" class="border border-gray-300 bg-white rounded-lg px-4 py-2 text-[13px]">Reset</a>
                </div>
            </form>

            <!-- summary tiles -->
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                <?php
                $tiles = [
                    ['Sales', (string)$sum['sales'], 'receipt'],
                    ['Units Sold', (string)$sum['units'], 'package-check'],
                    ['Turnover', sl_money($sum['turnover']), 'trending-up'],
                    ['Payment Received', sl_money($sum['paid']), 'wallet'],
                    ['Payment Pending', sl_money($sum['pending_amt']), 'hourglass'],
                ];
                foreach ($tiles as $tl): ?>
                    <div class="bg-white border border-gray-200 rounded-xl p-4">
                        <div class="flex items-center gap-2 text-gray-400 text-[11px] uppercase tracking-wide">
                            <i data-lucide="<?php echo $tl[2]; ?>" class="w-4 h-4"></i><?php echo $tl[0]; ?>
                        </div>
                        <div class="mt-2 text-[18px] font-semibold text-[#17457a]"><?php echo $tl[1]; ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- sales table with Excel and PDF export -->
            <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between flex-wrap gap-2">
                    <div>
                        <h2 class="text-[14px] font-semibold text-[#173a67]">Audited Sales Records</h2>
                        <span class="text-[12px] text-gray-400"><?php echo count($rows); ?> records in selected date range</span>
                    </div>
                    <div class="flex gap-2 no-print">
                        <button type="button" onclick="frExportTableToExcel('salesReportTable', 'SalesReport_<?php echo e($franchiseCode); ?>_<?php echo e($o['from']); ?>_<?php echo e($o['to']); ?>.xlsx', 'Sales')"
                                class="bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded-lg text-[12px] font-semibold flex items-center gap-1.5 transition">
                            <i data-lucide="file-spreadsheet" class="w-4 h-4"></i> Excel Export
                        </button>
                        <button type="button" onclick="window.print()"
                                class="bg-gray-600 hover:bg-gray-700 text-white px-3 py-1.5 rounded-lg text-[12px] font-semibold flex items-center gap-1.5 transition">
                            <i data-lucide="printer" class="w-4 h-4"></i> Print / PDF
                        </button>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-[13px]" id="salesReportTable">
                        <thead class="bg-gray-50 text-gray-500 text-[12px]">
                            <tr>
                                <th class="text-left px-5 py-2.5">Date</th>
                                <th class="text-left px-5 py-2.5">Invoice / Status</th>
                                <th class="text-left px-5 py-2.5">Product / Model</th>
                                <th class="text-left px-5 py-2.5">Customer</th>
                                <th class="text-right px-5 py-2.5">Qty</th>
                                <th class="text-right px-5 py-2.5">Amount</th>
                                <th class="text-left px-5 py-2.5">Payment</th>
                                <th class="text-left px-5 py-2.5">Delivery</th>
                                <th class="text-left px-5 py-2.5">Vehicle Unit</th>
                                <th class="text-right px-5 py-2.5">Invoice</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($rows)): ?>
                                <tr><td colspan="10" class="px-5 py-8 text-center text-gray-400">No sales records in this date range.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($rows as $r):
                                $pname = $r['current_product_name'] ?: $r['product_name'] ?: '—';
                                $mdl   = $r['current_model'] ?: $r['model'];
                                $buyer = trim((string)($r['buyer_name'] ?? ''));
                                $appSt = $r['approval_status'] ?? ($r['sale_status'] === 'Completed' ? 'Approved' : 'Pending_Approval');
                                $dispAmt = (float)($r['final_invoice_amount'] > 0 ? $r['final_invoice_amount'] : $r['sale_amount']);
                            ?>
                                <tr>
                                    <td class="px-5 py-2.5 text-gray-500 whitespace-nowrap"><?php echo e($r['sale_date'] ?: substr((string)$r['created_at'], 0, 10)); ?></td>
                                    <td class="px-5 py-2.5">
                                        <?php if ($appSt === 'Approved' && !empty($r['invoice_no'])): ?>
                                            <a href="sale-invoice.php?id=<?php echo (int)$r['id']; ?>" target="_blank" class="text-emerald-700 font-mono font-bold hover:underline"><?php echo e($r['invoice_no']); ?></a>
                                        <?php elseif ($appSt === 'Rejected'): ?>
                                            <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-red-700 bg-red-50 border border-red-200 px-2 py-0.5 rounded">✕ Rejected</span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-amber-800 bg-amber-50 border border-amber-200 px-2 py-0.5 rounded">⏳ Pending Admin</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-5 py-2.5 font-medium text-gray-800"><?php echo e($pname); ?><?php if ($mdl): ?> <span class="text-gray-400 text-[11px]">/ <?php echo e($mdl); ?></span><?php endif; ?></td>
                                    <td class="px-5 py-2.5">
                                        <?php if (!empty($r['customer_id'])): ?>
                                            <a href="customers.php?id=<?php echo (int)$r['customer_id']; ?>" class="text-[#17457a] hover:underline"><?php echo e($buyer ?: 'Customer'); ?></a>
                                            <?php if ($r['customer_mobile']): ?><span class="text-gray-400 text-[11px]"> · <?php echo e($r['customer_mobile']); ?></span><?php endif; ?>
                                        <?php else: ?>
                                            <?php echo e($buyer ?: 'Walk-in'); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-5 py-2.5 text-right"><?php echo (int)$r['quantity']; ?></td>
                                    <td class="px-5 py-2.5 text-right font-semibold text-[#17457a]"><?php echo sl_money($dispAmt); ?></td>
                                    <td class="px-5 py-2.5"><span class="text-[11px] rounded-full px-2 py-0.5 <?php echo $r['payment_status'] === 'Paid' ? 'bg-green-50 text-green-700 font-medium' : 'bg-amber-50 text-amber-700 font-medium'; ?>"><?php echo e($r['payment_status']); ?></span></td>
                                    <td class="px-5 py-2.5"><span class="text-[11px] rounded-full px-2 py-0.5 <?php echo $r['delivery_status'] === 'Delivered' ? 'bg-green-50 text-green-700 font-medium' : 'bg-gray-100 text-gray-600'; ?>"><?php echo e($r['delivery_status']); ?></span></td>
                                    <td class="px-5 py-2.5 text-gray-500 text-[12px]">
                                        <?php if ($r['unit_no']): ?><a href="product-unit.php?id=<?php echo (int)$r['unit_id']; ?>" class="text-[#17457a] font-mono hover:underline"><?php echo e($r['unit_no']); ?></a><?php else: ?>—<?php endif; ?>
                                    </td>
                                    <td class="px-5 py-2.5 text-right whitespace-nowrap">
                                        <?php if ($appSt === 'Approved'): ?>
                                            <a href="sale-invoice.php?id=<?php echo (int)$r['id']; ?>" target="_blank" class="inline-flex items-center gap-1 text-[12px] font-semibold text-[#0E7A3B] hover:underline">
                                                <i data-lucide="printer" class="w-3.5 h-3.5"></i> Tax Invoice
                                            </a>
                                        <?php elseif ($appSt === 'Rejected'): ?>
                                            <span class="text-[11px] text-gray-400">Cancelled</span>
                                        <?php else: ?>
                                            <a href="sale-invoice.php?id=<?php echo (int)$r['id']; ?>" target="_blank" class="inline-flex items-center gap-1 text-[12px] text-amber-700 hover:underline">
                                                <i data-lucide="eye" class="w-3.5 h-3.5"></i> View Details
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>
</div>
<script>if (typeof lucide !== 'undefined') lucide.createIcons();</script>
</body>
</html>

