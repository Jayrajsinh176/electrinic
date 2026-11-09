<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.");
}

$conn->set_charset('utf8mb4');

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$search = trim((string)($_GET['search'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? 'All'));
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 25;
$offset = ($page - 1) * $limit;

$allowedStatuses = ['All', 'Active', 'Inactive'];
if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = 'All';
}

// Single franchise master query from franchies_holder
$baseQuery = "SELECT id,
                     COALESCE(NULLIF(franchise_code,''), NULLIF(franchies_id,''), CAST(id AS CHAR)) AS franchise_code,
                     name AS full_name,
                     email,
                     mobile,
                     state,
                     city,
                     status,
                     created_date AS created_at
              FROM franchies_holder";

$conditions = [];
$params = [];
$paramTypes = '';

if ($statusFilter !== 'All') {
    $conditions[] = 'LOWER(status) = ?';
    $params[] = strtolower($statusFilter);
    $paramTypes .= 's';
}

if ($search !== '') {
    $conditions[] = '(franchise_code LIKE ? OR full_name LIKE ? OR mobile LIKE ? OR email LIKE ? OR state LIKE ? OR city LIKE ?)';
    $searchTerm = '%' . $search . '%';
    for ($i = 0; $i < 6; $i++) {
        $params[] = $searchTerm;
    }
    $paramTypes .= 'ssssss';
}

$whereClause = '';
if (!empty($conditions)) {
    $whereClause = 'WHERE ' . implode(' AND ', $conditions);
}

$countSql = "SELECT COUNT(*) AS total FROM (" . $baseQuery . ") AS tally " . $whereClause;
$countStmt = $conn->prepare($countSql);
if ($countStmt) {
    if (!empty($params)) {
        $countStmt->bind_param($paramTypes, ...$params);
    }
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $totalRows = $countResult ? (int)($countResult->fetch_assoc()['total'] ?? 0) : 0;
    $countStmt->close();
} else {
    $totalRows = 0;
}

$querySql = "SELECT * FROM (" . $baseQuery . ") AS final_franchises " . $whereClause . " ORDER BY created_at DESC, id DESC LIMIT ? OFFSET ?";
$queryStmt = $conn->prepare($querySql);
if (!$queryStmt) {
    die('Query failed: ' . $conn->error);
}

$queryParams = $params;
$queryTypes = $paramTypes . 'ii';
$queryParams[] = $limit;
$queryParams[] = $offset;

if (!empty($params)) {
    $queryStmt->bind_param($queryTypes, ...$queryParams);
} else {
    $queryStmt->bind_param('ii', $limit, $offset);
}

$queryStmt->execute();
$result = $queryStmt->get_result();
$rows = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
}
$queryStmt->close();

$totalPages = (int)ceil($totalRows / $limit);
$hasPagination = $totalPages > 1;

$baseParams = [];
if ($search !== '') {
    $baseParams['search'] = $search;
}
if ($statusFilter !== 'All') {
    $baseParams['status'] = $statusFilter;
}

function buildPageUrl(int $page, array $baseParams): string
{
    $params = $baseParams;
    $params['page'] = $page;
    return '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Franchise List</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/admin-responsive.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-[#f3f4f6] min-h-screen overflow-x-hidden">

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="ml-64 min-h-screen">
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <main class="px-5 py-6">
        <div class="max-w-[1200px] mx-auto">
            <div class="mb-9 flex items-center gap-5">
                <div class="flex h-[44px] w-[44px] shrink-0 items-center justify-center rounded-[10px] bg-[#f6eee2]">
                    <i data-lucide="building-2" class="h-5 w-5 text-[#cf9a3c]"></i>
                </div>
                <h2 class="whitespace-nowrap text-[25px] font-normal leading-none text-[#1f2937]" style="font-family: Georgia, 'Times New Roman', serif;">
                    Franchise List
                </h2>
                <div class="h-[2px] w-full bg-[#d1a14b]"></div>
                <a href="franchies_reg.php" class="inline-flex items-center gap-2 rounded-[8px] bg-[#cd9737] px-4 py-2.5 text-white text-[14px] font-semibold hover:bg-[#b88426] whitespace-nowrap shadow-sm">
                    <i data-lucide="user-plus" class="w-4 h-4"></i>
                    Register Franchise
                </a>
            </div>

            <form method="GET" class="rounded-[10px] border border-[#d6a246] bg-white p-4 shadow-sm">
                <div class="grid gap-4 lg:grid-cols-3">
                    <div class="lg:col-span-2">
                        <label class="mb-2 block text-sm font-semibold text-[#344054]">Search</label>
                        <input type="text" name="search" value="<?php echo e($search); ?>" placeholder="Search by Franchise ID, name, mobile, email, state, city..."
                               class="w-full rounded-[6px] border border-[#d8dce2] bg-[#f2f4f7] px-3 py-2 text-[14px] outline-none">
                    </div>
                    <div>
                        <div class="mb-2 block text-sm font-semibold text-[#344054]">Status</div>
                        <div class="flex flex-wrap gap-2">
                            <?php foreach ($allowedStatuses as $statusOption): ?>
                                <label class="inline-flex items-center gap-2 rounded-[6px] border px-3 py-2 text-[14px] cursor-pointer <?php echo $statusFilter === $statusOption ? 'border-[#143a66] bg-[#eef2ff] text-[#143a66]' : 'border-[#d8dce2] bg-[#f2f4f7] text-[#475569]'; ?>">
                                    <input type="radio" name="status" value="<?php echo e($statusOption); ?>" class="hidden" onchange="this.form.submit()" <?php echo $statusFilter === $statusOption ? 'checked' : ''; ?> />
                                    <?php echo e($statusOption); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-[6px] bg-[#143a66] px-4 py-2 text-white text-[15px] font-semibold hover:bg-[#0f3255]">
                        <i data-lucide="search" class="w-4 h-4"></i>
                        Search
                    </button>
                    <a href="franchise-list.php" class="inline-flex items-center gap-2 rounded-[6px] border border-[#d8dce2] bg-white px-4 py-2 text-[15px] text-[#334155] hover:bg-[#f8fafc]">
                        <i data-lucide="refresh-ccw" class="w-4 h-4"></i>
                        Reset
                    </a>
                </div>
            </form>

            <div class="mt-6 overflow-hidden rounded-[10px] border border-[#d6a246] bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1000px] border-collapse text-[14px]">
                        <thead>
                        <tr class="bg-[#cd9737] text-white text-[14px] h-[40px]">
                            <th class="px-3 text-left font-medium">Sr No</th>
                            <th class="px-3 text-left font-medium">Action</th>
                            <th class="px-3 text-left font-medium">Franchise ID</th>
                            <th class="px-3 text-left font-medium">Name</th>
                            <th class="px-3 text-left font-medium">Mobile</th>
                            <th class="px-3 text-left font-medium">Email</th>
                            <th class="px-3 text-left font-medium">State</th>
                            <th class="px-3 text-left font-medium">City</th>
                            <th class="px-3 text-left font-medium">Status</th>
                            <th class="px-3 text-left font-medium">Joined At</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!empty($rows)): ?>
                            <?php $sr = $offset + 1; ?>
                            <?php foreach ($rows as $row): ?>
                                <tr class="border-t border-[#e8e8e8] hover:bg-[#fbfbfb]">
                                    <td class="px-3 py-3 whitespace-nowrap"><?php echo $sr++; ?></td>
                                    <td class="px-3 py-3 whitespace-nowrap">
                                        <a href="generate-franchise-access.php?franchise_code=<?php echo urlencode($row['franchise_code']); ?>"
                                           target="_blank"
                                           title="1-Click Login to Franchise Portal"
                                           class="inline-flex items-center justify-center gap-1.5 rounded-[7px] bg-[#143a66] px-3 py-1 text-white text-[13px] font-semibold hover:bg-[#0f3255] transition shadow-sm">
                                            Login / View
                                        </a>
                                    </td>
                                    <td class="px-3 py-3 whitespace-nowrap font-mono font-bold text-blue-700"><?php echo e($row['franchise_code']); ?></td>
                                    <td class="px-3 py-3 whitespace-nowrap font-medium text-gray-800"><?php echo e($row['full_name']); ?></td>
                                    <td class="px-3 py-3 whitespace-nowrap"><?php echo e($row['mobile']); ?></td>
                                    <td class="px-3 py-3 whitespace-nowrap"><?php echo e($row['email']); ?></td>
                                    <td class="px-3 py-3 whitespace-nowrap"><?php echo e($row['state']); ?></td>
                                    <td class="px-3 py-3 whitespace-nowrap"><?php echo e($row['city']); ?></td>
                                    <td class="px-3 py-3 whitespace-nowrap">
                                        <?php if (strtolower(trim((string)$row['status'])) === 'active'): ?>
                                            <span class="inline-flex rounded-full bg-green-100 px-2 py-1 text-[12px] font-semibold text-green-700">Active</span>
                                        <?php else: ?>
                                            <span class="inline-flex rounded-full bg-red-100 px-2 py-1 text-[12px] font-semibold text-red-700">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 py-3 whitespace-nowrap"><?php echo e($row['created_at']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="px-3 py-6 text-center text-[#475569]">No franchise records found.</td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if ($hasPagination): ?>
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3 text-[14px] text-[#334155]">
                    <p>Showing page <?php echo $page; ?> of <?php echo $totalPages; ?> (<?php echo $totalRows; ?> records)</p>
                    <div class="flex flex-wrap items-center gap-2">
                        <?php if ($page > 1): ?>
                            <a href="<?php echo buildPageUrl($page - 1, $baseParams); ?>" class="rounded-[6px] border border-[#d8dce2] bg-white px-3 py-2 text-[#334155] hover:bg-[#f8fafc]">Previous</a>
                        <?php endif; ?>
                        <?php if ($page < $totalPages): ?>
                            <a href="<?php echo buildPageUrl($page + 1, $baseParams); ?>" class="rounded-[6px] border border-[#d8dce2] bg-white px-3 py-2 text-[#334155] hover:bg-[#f8fafc]">Next</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </main>
</div>

<script>
    lucide.createIcons();
</script>
</body>
</html>
