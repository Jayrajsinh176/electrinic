<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ini_set('display_errors', 1);
error_reporting(E_ALL);

$dbPaths = [
    __DIR__ . '/config/db.php',
    __DIR__ . '/../config/db.php',
    dirname(__DIR__) . '/config/db.php'
];

$dbLoaded = false;

foreach ($dbPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $dbLoaded = true;
        break;
    }
}

if (!$dbLoaded || !isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection file not found.");
}

if (!function_exists('e')) {
    function e($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

function tableHasColumn($conn, $table, $column) {
    $table = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return ($result && $result->num_rows > 0);
}

function tableExists($conn, $table) {
    $table = $conn->real_escape_string($table);
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    return ($result && $result->num_rows > 0);
}

function findKycFile($dbFile) {
    $dbFile = trim((string)$dbFile);
    if ($dbFile === '') return '';

    $dbFile = str_replace('\\', '/', $dbFile);
    $base = basename($dbFile);
    $nameOnly = pathinfo($base, PATHINFO_FILENAME);

    $roots = [
        __DIR__,
        dirname(__DIR__),
        $_SERVER['DOCUMENT_ROOT'] ?? '',
        ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/freshiya',
        ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/freshiya_main',
        ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/freshya_member',
    ];

    $folders = [
        '',
        'uploads',
        'uploads/kyc',
        'uploads/franchise_kyc',
        'uploads/franchies_kyc',
        'admin/uploads',
        'admin/uploads/kyc',
        'admin/uploads/franchise_kyc',
        'admin/uploads/franchies_kyc',
        'franchise/uploads',
        'franchise/uploads/kyc',
        'franchise/uploads/franchise_kyc',
        'franchies/uploads',
        'franchies/uploads/kyc',
        'franchies/uploads/franchise_kyc',
        'member/uploads',
        'member/uploads/kyc'
    ];

    foreach ($roots as $root) {
        if ($root === '') continue;

        foreach ($folders as $folder) {
            $dir = rtrim($root . '/' . $folder, '/');

            $try = $dir . '/' . $base;
            if (is_file($try)) return realpath($try);

            foreach (glob($dir . '/' . $nameOnly . '.*') ?: [] as $match) {
                if (is_file($match)) return realpath($match);
            }
        }
    }

    foreach ($roots as $root) {
        if ($root === '' || !is_dir($root)) continue;

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (!$file->isFile()) continue;

                $fileBase = $file->getBasename();
                $fileNameOnly = pathinfo($fileBase, PATHINFO_FILENAME);

                if (strcasecmp($fileBase, $base) === 0 || strcasecmp($fileNameOnly, $nameOnly) === 0) {
                    return $file->getRealPath();
                }
            }
        } catch (Exception $e) {}
    }

    return '';
}

$conn->query("
    CREATE TABLE IF NOT EXISTS `franchise_kyc_documents` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `franchise_code` VARCHAR(50) NOT NULL,
        `document_type` VARCHAR(100) NOT NULL,
        `document_file` VARCHAR(255) NOT NULL,
        `address` TEXT NULL,
        `pincode` VARCHAR(20) NULL,
        `latitude` DECIMAL(10, 8) NULL,
        `longitude` DECIMAL(11, 8) NULL,
        `map_address` VARCHAR(255) NULL,
        `status` ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
        `uploaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        INDEX (`franchise_code`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$conn->query("ALTER TABLE `franchise_kyc_documents` ADD COLUMN IF NOT EXISTS `address` TEXT NULL AFTER `document_file`");
$conn->query("ALTER TABLE `franchise_kyc_documents` ADD COLUMN IF NOT EXISTS `pincode` VARCHAR(20) NULL AFTER `address`");
$conn->query("ALTER TABLE `franchise_kyc_documents` ADD COLUMN IF NOT EXISTS `latitude` DECIMAL(10, 8) NULL AFTER `pincode`");
$conn->query("ALTER TABLE `franchise_kyc_documents` ADD COLUMN IF NOT EXISTS `longitude` DECIMAL(11, 8) NULL AFTER `latitude`");
$conn->query("ALTER TABLE `franchise_kyc_documents` ADD COLUMN IF NOT EXISTS `map_address` VARCHAR(255) NULL AFTER `longitude`");

if (isset($_GET['view_doc'])) {

    $id = (int)$_GET['view_doc'];
    $source = $_GET['source'] ?? 'state';

   if ($source === 'city') {

    $stmt = $conn->prepare("
        SELECT document_file
        FROM city_franchies_kyc
        WHERE id = ?
        LIMIT 1
    ");

} elseif ($source === 'mobile') {

    $stmt = $conn->prepare("
        SELECT document_file
        FROM mobile_franchies_kyc
        WHERE id = ?
        LIMIT 1
    ");

} else {

    $stmt = $conn->prepare("
        SELECT document_file
        FROM franchise_kyc_documents
        WHERE id = ?
        LIMIT 1
    ");

}
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || empty($row['document_file'])) {
        http_response_code(404);
        echo "Document not found in database.";
        exit;
    }

    $file = findKycFile($row['document_file']);

    if ($file === '') {
        http_response_code(404);
        echo "File not found on server. DB path: " . e($row['document_file']);
        exit;
    }

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

    $mime = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'webp' => 'image/webp',
        'gif'  => 'image/gif',
        'pdf'  => 'application/pdf'
    ];

    header("Content-Type: " . ($mime[$ext] ?? 'application/octet-stream'));
    header("Content-Disposition: inline; filename=\"" . basename($file) . "\"");
    header("Content-Length: " . filesize($file));
    readfile($file);
    exit;
}

$message = "";
$error = "";

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["kyc_id"], $_POST["status"], $_POST["source_table"])
) {

    $kycId  = (int)$_POST["kyc_id"];
    $status = trim($_POST["status"]);
    $source = trim($_POST["source_table"]);

    if (in_array($status, ["Pending", "Approved", "Rejected"], true)) {

        if ($source === 'city') {

            $stmt = $conn->prepare("
                UPDATE city_franchies_kyc
                SET status = ?
                WHERE id = ?
                LIMIT 1
            ");

        } elseif ($source === 'mobile') {

            $stmt = $conn->prepare("
                UPDATE mobile_franchies_kyc
                SET status = ?
                WHERE id = ?
                LIMIT 1
            ");

        } else {

            $stmt = $conn->prepare("
                UPDATE franchise_kyc_documents
                SET status = ?
                WHERE id = ?
                LIMIT 1
            ");
        }

        $stmt->bind_param("si", $status, $kycId);

        if ($stmt->execute()) {

    if ($stmt->affected_rows > 0) {
        $message = "KYC status updated successfully.";
    } else {
        $error = "No record updated.";
    }

} else {

    $error = "Status update failed.";
}

        $stmt->close();
    }
}

function getFranchiseName($conn, $franchiseCode) {
    if (!tableExists($conn, "franchies_holder")) {
        return "N/A";
    }

    $codeColumn = "";
    foreach (["franchies_id", "franchise_id", "franchises_id"] as $col) {
        if (tableHasColumn($conn, "franchies_holder", $col)) {
            $codeColumn = $col;
            break;
        }
    }

    $nameColumn = "";
    foreach (["name", "full_name", "franchise_name"] as $col) {
        if (tableHasColumn($conn, "franchies_holder", $col)) {
            $nameColumn = $col;
            break;
        }
    }

    if ($codeColumn === "" || $nameColumn === "") {
        return "N/A";
    }

    $stmt = $conn->prepare("SELECT `$nameColumn` FROM franchies_holder WHERE `$codeColumn` = ? LIMIT 1");
    $stmt->bind_param("s", $franchiseCode);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row[$nameColumn] ?? "N/A";
}

$kycRows = [];

/* PRIMARY FRANCHISE KYC */
$result = $conn->query("
SELECT
    id,
    franchise_code,
    document_type,
    document_file,
    address,
    pincode,
    latitude,
    longitude,
    map_address,
    status,
    uploaded_at,
    'Franchise' AS franchise_type,
    'state' AS source_table
FROM franchise_kyc_documents
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $row["franchise_name"] = getFranchiseName($conn, $row["franchise_code"]);
        $kycRows[] = $row;
    }
}

/* CITY FRANCHISE (Archived fallback if table exists) */
if (tableExists($conn, "city_franchies_kyc")) {
    $result = $conn->query("
        SELECT
            ck.id,
            ck.franchise_code,
            ck.document_type,
            ck.document_file,
            NULL AS address,
            NULL AS pincode,
            NULL AS latitude,
            NULL AS longitude,
            NULL AS map_address,
            ck.status,
            ck.uploaded_at,
            cf.full_name,
            'Franchise' AS franchise_type,
            'city' AS source_table
        FROM city_franchies_kyc ck
        LEFT JOIN city_franchies_holder cf
            ON cf.franchise_code = ck.franchise_code
    ");

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $row["franchise_name"] = $row["full_name"] ?? 'N/A';
            $kycRows[] = $row;
        }
    }
}

/* MOBILE FRANCHISE (Archived fallback if table exists) */
if (tableExists($conn, "mobile_franchies_kyc")) {
    $result = $conn->query("
        SELECT
            mk.id,
            mk.franchise_code,
            mk.document_type,
            mk.document_file,
            NULL AS address,
            NULL AS pincode,
            NULL AS latitude,
            NULL AS longitude,
            NULL AS map_address,
            mk.status,
            mk.uploaded_at,
            mf.owner_name,
            'Franchise' AS franchise_type,
            'mobile' AS source_table
        FROM mobile_franchies_kyc mk
        LEFT JOIN mobile_franchies_holder mf
            ON mf.mobile_franchies_id = mk.franchise_code
    ");

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $row["franchise_name"] = $row["owner_name"] ?? 'N/A';
            $kycRows[] = $row;
        }
    }
}

/* SORT LATEST FIRST */
usort($kycRows, function ($a, $b) {
    return strtotime($b['uploaded_at']) - strtotime($a['uploaded_at']);
});


?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Franchise KYC Details</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://unpkg.com/lucide@latest"></script>
</head>

<body class="bg-[#f3f4f6] font-sans overflow-x-hidden">

<?php include __DIR__ . "/includes/sidebar.php"; ?>

<div class="lg:ml-64 min-h-screen">
<?php include __DIR__ . "/includes/navbar.php"; ?>

<div class="mt-5 px-3 sm:px-7 flex flex-wrap items-center gap-3 sm:gap-4">
    <div class="w-[36px] h-[36px] rounded-[8px] bg-[#f5eee3] flex items-center justify-center shrink-0">
        <i data-lucide="badge-check" class="w-[18px] h-[18px] text-[#cd9737]"></i>
    </div>

    <h2 class="whitespace-nowrap text-[18px] sm:text-[20px] font-normal text-[#40352a]" style="font-family: Georgia, 'Times New Roman', serif;">
        FRANCHIES KYC DETAILS
    </h2>

    <div class="hidden sm:block flex-1 h-[2px] bg-[#d1a14b]"></div>
</div>

<main class="px-3 sm:px-7 py-4 sm:py-7">
<div class="max-w-[1500px] mx-auto">

<?php if ($message !== ""): ?>
    <div class="mb-4 sm:mb-6 rounded-lg border border-green-200 bg-green-50 px-4 sm:px-5 py-3 sm:py-4 text-sm sm:text-base text-green-700">
        <?= e($message); ?>
    </div>
<?php endif; ?>

<?php if ($error !== ""): ?>
    <div class="mb-4 sm:mb-6 rounded-lg border border-red-200 bg-red-50 px-4 sm:px-5 py-3 sm:py-4 text-sm sm:text-base text-red-700">
        <?= e($error); ?>
    </div>
<?php endif; ?>

<div class="bg-white rounded-[14px] shadow border border-gray-200 overflow-hidden">
    <div class="bg-[#12395f] px-4 sm:px-7 py-5 sm:py-7">
        <h1 class="text-white text-[18px] sm:text-[24px] font-bold">Franchise Member KYC Documents</h1>
        <p class="text-white text-[13px] sm:text-[17px] mt-2">View uploaded franchise KYC documents and approve or reject status.</p>
    </div>

    <div class="p-3 sm:p-7 overflow-x-auto">
        <table class="w-full min-w-[1100px]">
            <thead>
            <tr class="bg-[#d19a2e] text-white">
                <th class="px-3 sm:px-4 py-3 sm:py-5 text-left text-sm font-semibold rounded-l-lg">ID</th>
                <th class="px-3 sm:px-4 py-3 sm:py-5 text-left text-sm font-semibold">Franchise</th>
                <th class="px-3 sm:px-4 py-3 sm:py-5 text-left text-sm font-semibold">Document Type</th>
                <th class="px-3 sm:px-4 py-3 sm:py-5 text-left text-sm font-semibold">Declared Address & PIN</th>
                <th class="px-3 sm:px-4 py-3 sm:py-5 text-left text-sm font-semibold">Map Pin</th>
                <th class="px-3 sm:px-4 py-3 sm:py-5 text-left text-sm font-semibold">Upload Date</th>
                <th class="px-3 sm:px-4 py-3 sm:py-5 text-left text-sm font-semibold">Document</th>
                <th class="px-3 sm:px-4 py-3 sm:py-5 text-left text-sm font-semibold">Status</th>
                <th class="px-3 sm:px-4 py-3 sm:py-5 text-left text-sm font-semibold rounded-r-lg">Action</th>
            </tr>
            </thead>

            <tbody>
            <?php if (!empty($kycRows)): ?>
                <?php foreach ($kycRows as $row): ?>
                    <?php
                    $statusClass = "bg-yellow-100 text-yellow-700";
                    if ($row["status"] === "Approved") $statusClass = "bg-green-100 text-green-700";
                    if ($row["status"] === "Rejected") $statusClass = "bg-red-100 text-red-700";

                    $docUrl =
                        basename($_SERVER['PHP_SELF'])
                        . "?view_doc="
                        . urlencode($row["id"])
                        . "&source="
                        . urlencode($row["source_table"]);
                    ?>

                    <tr class="border-b border-gray-100 hover:bg-gray-50/50">
                        <td class="px-3 sm:px-4 py-4 sm:py-5 text-xs sm:text-sm font-mono text-gray-500"><?= e($row["id"]); ?></td>

                        <td class="px-3 sm:px-4 py-4 sm:py-5">
                            <div class="text-xs sm:text-sm font-bold text-[#12395f]"><?= e($row["franchise_code"]); ?></div>
                            <div class="text-xs text-gray-600"><?= e($row["franchise_name"]); ?></div>
                        </td>

                        <td class="px-3 sm:px-4 py-4 sm:py-5 text-xs sm:text-sm font-medium text-gray-800">
                            <?= e($row["document_type"]); ?>
                        </td>

                        <td class="px-3 sm:px-4 py-4 sm:py-5">
                            <?php if (!empty($row['address'])): ?>
                                <div class="text-xs text-gray-800 max-w-[240px] leading-relaxed">
                                    <?= nl2br(e($row['address'])); ?>
                                </div>
                                <?php if (!empty($row['pincode'])): ?>
                                    <span class="inline-block mt-1 px-2 py-0.5 rounded bg-gray-100 text-gray-700 text-[11px] font-mono font-semibold">PIN: <?= e($row['pincode']); ?></span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-gray-400 text-xs italic">Not declared</span>
                            <?php endif; ?>
                        </td>

                        <td class="px-3 sm:px-4 py-4 sm:py-5">
                            <?php if (!empty($row['latitude']) && !empty($row['longitude'])): ?>
                                <a href="https://www.google.com/maps?q=<?= urlencode($row['latitude'] . ',' . $row['longitude']); ?>" target="_blank" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 hover:bg-emerald-100 border border-emerald-200 whitespace-nowrap">
                                    📍 View Pin
                                </a>
                                <div class="text-[10px] text-gray-400 font-mono mt-0.5">
                                    <?= number_format((float)$row['latitude'], 4); ?>, <?= number_format((float)$row['longitude'], 4); ?>
                                </div>
                            <?php else: ?>
                                <span class="text-gray-400 text-xs italic">No pin</span>
                            <?php endif; ?>
                        </td>

                        <td class="px-3 sm:px-4 py-4 sm:py-5 text-xs text-gray-600 whitespace-nowrap">
                            <?= e(date("d-m-Y h:i A", strtotime($row["uploaded_at"]))); ?>
                        </td>

                        <td class="px-3 sm:px-4 py-4 sm:py-5 text-xs sm:text-sm">
                            <button type="button"
                                    onclick="openDocModal(this)"
                                    data-url="<?= e($docUrl); ?>"
                                    data-code="<?= e($row['franchise_code']); ?>"
                                    data-name="<?= e($row['franchise_name']); ?>"
                                    data-type="<?= e($row['document_type']); ?>"
                                    data-address="<?= e($row['address'] ?? ''); ?>"
                                    data-pincode="<?= e($row['pincode'] ?? ''); ?>"
                                    data-lat="<?= e($row['latitude'] ?? ''); ?>"
                                    data-lng="<?= e($row['longitude'] ?? ''); ?>"
                                    class="inline-flex items-center gap-1 text-blue-600 hover:text-blue-800 font-semibold hover:underline whitespace-nowrap">
                                <span>📄</span> View Document
                            </button>
                        </td>

                        <td class="px-3 sm:px-4 py-4 sm:py-5">
                            <span class="px-3 py-1 rounded-full text-xs font-semibold whitespace-nowrap <?= e($statusClass); ?>">
                                <?= e($row["status"]); ?>
                            </span>
                        </td>

                        <td class="px-3 sm:px-4 py-4 sm:py-5">
                            <form method="POST" class="flex items-center gap-2">
                                <input type="hidden" name="kyc_id" value="<?= e($row["id"]); ?>">
                                <input type="hidden" name="source_table" value="<?= e($row["source_table"]); ?>">

                                <select name="status" class="h-[36px] border border-gray-300 rounded-md px-2 text-xs">
                                    <option value="Pending" <?= $row["status"] === "Pending" ? "selected" : ""; ?>>Pending</option>
                                    <option value="Approved" <?= $row["status"] === "Approved" ? "selected" : ""; ?>>Approved</option>
                                    <option value="Rejected" <?= $row["status"] === "Rejected" ? "selected" : ""; ?>>Rejected</option>
                                </select>

                                <button type="submit" class="h-[36px] px-3 rounded-md bg-[#12395f] text-white text-xs font-semibold hover:opacity-90 whitespace-nowrap">
                                    Update
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                   <td colspan="9" class="py-8 text-center text-gray-500">
                        No franchise KYC documents found.
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</div>
</main>
</div>

<div id="docModal" class="fixed inset-0 z-[99999] hidden items-center justify-center bg-black/60 px-2 sm:px-4">
    <div class="w-full max-w-5xl rounded-2xl bg-white shadow-2xl overflow-hidden flex flex-col max-h-[92vh]">
        <div class="flex items-center justify-between border-b px-4 sm:px-6 py-3.5 bg-[#12395f] text-white">
            <div>
                <h2 class="text-base sm:text-lg font-bold" id="mModalTitle">KYC Document Verification</h2>
                <div class="text-xs text-slate-300" id="mModalSub">Verify uploaded document against declared address & location</div>
            </div>
            <button onclick="closeDocModal()" class="rounded-full bg-white/20 hover:bg-white/30 text-white px-3 py-1 text-xl font-bold">×</button>
        </div>

        <!-- Verification Info Banner -->
        <div id="mInfoBanner" class="p-4 bg-amber-50 border-b border-amber-200 text-xs sm:text-sm">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 items-center">
                <div>
                    <span class="text-gray-500 font-medium">Declared Address:</span>
                    <p id="mAddressVal" class="text-gray-900 font-semibold mt-0.5 break-words"></p>
                    <div class="text-gray-600 text-xs mt-1" id="mPinVal"></div>
                </div>
                <div class="md:text-right">
                    <span class="text-gray-500 font-medium block">GPS Location Pin:</span>
                    <div id="mPinLinkVal" class="mt-1"></div>
                </div>
            </div>
        </div>

        <div class="p-3 sm:p-5 bg-gray-100 flex-1 overflow-auto text-center">
            <iframe id="docFrame" src="" class="h-[55vh] sm:h-[60vh] w-full rounded-xl border bg-white shadow-inner"></iframe>
        </div>

        <div class="border-t px-4 sm:px-6 py-3 bg-white flex flex-wrap items-center justify-between gap-2">
            <div class="text-xs text-gray-500">Compare the document details with the declared address and map pin above before approval.</div>
            <a id="docOpenNew" href="#" target="_blank" class="inline-block rounded-lg bg-blue-600 px-4 py-2 text-xs sm:text-sm font-semibold text-white hover:bg-blue-700">
                Open in New Tab
            </a>
        </div>
    </div>
</div>

<script>
function openDocModal(btn) {
    const url = btn.dataset.url;
    const code = btn.dataset.code || '';
    const name = btn.dataset.name || '';
    const type = btn.dataset.type || '';
    const address = btn.dataset.address || '';
    const pincode = btn.dataset.pincode || '';
    const lat = btn.dataset.lat || '';
    const lng = btn.dataset.lng || '';

    document.getElementById('mModalTitle').textContent = code + ' - ' + name + ' (' + type + ')';
    document.getElementById('mAddressVal').textContent = address ? address : 'No address declared by franchise.';
    document.getElementById('mPinVal').innerHTML = pincode ? '<strong>PIN Code:</strong> ' + pincode : '';

    const pinContainer = document.getElementById('mPinLinkVal');
    if (lat && lng) {
        pinContainer.innerHTML = '<a href="https://www.google.com/maps?q=' + encodeURIComponent(lat + ',' + lng) + '" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-600 text-white rounded-lg text-xs font-semibold hover:bg-emerald-700 shadow-sm">📍 View Pinned Location on Google Maps (' + parseFloat(lat).toFixed(4) + ', ' + parseFloat(lng).toFixed(4) + ')</a>';
    } else {
        pinContainer.innerHTML = '<span class="text-gray-400 italic text-xs">No map pin provided</span>';
    }

    document.getElementById('docFrame').src = url;
    document.getElementById('docOpenNew').href = url;
    document.getElementById('docModal').classList.remove('hidden');
    document.getElementById('docModal').classList.add('flex');
}

function closeDocModal() {
    document.getElementById('docFrame').src = '';
    document.getElementById('docModal').classList.add('hidden');
    document.getElementById('docModal').classList.remove('flex');
}

document.addEventListener("DOMContentLoaded", function () {
    if (typeof lucide !== "undefined") {
        lucide.createIcons();
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeDocModal();
});
</script>

</body>
</html>