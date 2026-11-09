<?php
require_once __DIR__ . '/includes/auth.php';

if (!function_exists('e')) {
    function e($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (empty($_SESSION['franchise_id'])) {
    header("Location: index.php");
    exit();
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.");
}

$franchiseId = (int)$_SESSION['franchise_id'];
$franchiseTable = 'franchies_holder';
$kycTable = 'franchise_kyc_documents';

// Ensure table and new address/location columns exist
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
");

$conn->query("ALTER TABLE `franchise_kyc_documents` ADD COLUMN IF NOT EXISTS `address` TEXT NULL AFTER `document_file`");
$conn->query("ALTER TABLE `franchise_kyc_documents` ADD COLUMN IF NOT EXISTS `pincode` VARCHAR(20) NULL AFTER `address`");
$conn->query("ALTER TABLE `franchise_kyc_documents` ADD COLUMN IF NOT EXISTS `latitude` DECIMAL(10, 8) NULL AFTER `pincode`");
$conn->query("ALTER TABLE `franchise_kyc_documents` ADD COLUMN IF NOT EXISTS `longitude` DECIMAL(11, 8) NULL AFTER `latitude`");
$conn->query("ALTER TABLE `franchise_kyc_documents` ADD COLUMN IF NOT EXISTS `map_address` VARCHAR(255) NULL AFTER `longitude`");

// Fetch franchise details
$stmt = $conn->prepare("SELECT * FROM `$franchiseTable` WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $franchiseId);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {
    header("Location: logout.php");
    exit();
}

$franchise = $result->fetch_assoc();
$stmt->close();

$franchiseCode = $franchise['franchise_code'] 
    ?? $franchise['franchies_id'] 
    ?? ($_SESSION['franchise_code'] ?? '');

$defaultState = $franchise['state'] ?? 'Gujarat';
$defaultCity  = $franchise['city'] ?? 'Bharuch';

$message = '';
$error = '';

$uploadDir = __DIR__ . '/uploads/franchise_kyc/';
$uploadUrl = 'uploads/franchise_kyc/';

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$allowedTypes = ['PAN CARD', 'AADHAR CARD', 'PASSPORT', 'DRIVING LICENSE', 'SHOP ESTABLISHMENT / ELECTRICITY BILL'];
$allowedExt = ['jpg', 'jpeg', 'png', 'pdf'];
$maxSize = 2 * 1024 * 1024;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $documentType = trim($_POST['document_type'] ?? '');
    $address      = trim($_POST['address'] ?? '');
    $pincode      = trim($_POST['pincode'] ?? '');
    $latInput     = trim((string)($_POST['latitude'] ?? ''));
    $lngInput     = trim((string)($_POST['longitude'] ?? ''));
    $mapAddress   = trim($_POST['map_address'] ?? '');

    $lat = ($latInput !== '' && is_numeric($latInput)) ? (float)$latInput : null;
    $lng = ($lngInput !== '' && is_numeric($lngInput)) ? (float)$lngInput : null;

    if ($documentType === '' || !in_array($documentType, $allowedTypes, true)) {
        $error = "Please select a valid document type.";
    } elseif ($address === '') {
        $error = "Please enter the address as shown on your uploaded document.";
    } elseif ($pincode === '') {
        $error = "Please enter your 6-digit postal PIN code.";
    } elseif (!isset($_FILES['document_file']) || $_FILES['document_file']['error'] !== UPLOAD_ERR_OK) {
        $error = "Please upload document file.";
    } elseif ($franchiseCode === '') {
        $error = "Franchise code not found. Please login again.";
    } else {
        $file = $_FILES['document_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedExt, true)) {
            $error = "Only JPG, JPEG, PNG and PDF files are allowed.";
        } elseif ((int)$file['size'] > $maxSize) {
            $error = "Max file size allowed is 2 MB.";
        } else {
            $safeType = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $documentType));
            $safeCode = preg_replace('/[^a-zA-Z0-9_-]/', '_', $franchiseCode);
            $newFileName = $safeType . '_' . $safeCode . '_' . time() . '.' . $ext;
            $targetPath = $uploadDir . $newFileName;

            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                $stmt = $conn->prepare("
                    INSERT INTO `$kycTable`
                    (`franchise_code`, `document_type`, `document_file`, `address`, `pincode`, `latitude`, `longitude`, `map_address`, `status`, `uploaded_at`)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending', NOW())
                ");

                if ($stmt) {
                    $stmt->bind_param("sssssdds", $franchiseCode, $documentType, $newFileName, $address, $pincode, $lat, $lng, $mapAddress);

                    if ($stmt->execute()) {
                        $message = "KYC document, address and location pin submitted successfully for verification.";
                    } else {
                        $error = "Database insert failed: " . $stmt->error;
                    }

                    $stmt->close();
                } else {
                    $error = "Database prepare failed: " . $conn->error;
                }
            } else {
                $error = "File upload failed.";
            }
        }
    }
}

$documents = [];

$stmt = $conn->prepare("
    SELECT id, franchise_code, document_type, document_file, address, pincode, latitude, longitude, map_address, status, uploaded_at
    FROM `$kycTable`
    WHERE franchise_code = ?
    ORDER BY id DESC
");

if ($stmt) {
    $stmt->bind_param("s", $franchiseCode);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $documents[] = $row;
    }

    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My KYC - Address & Location Pin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>

    <!-- Leaflet.js (Free OpenStreetMap - Zero API keys required) -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <style>
        #kycMap {
            height: 320px;
            z-index: 10;
        }
        .leaflet-container {
            font-family: inherit;
        }
    </style>
</head>

<body class="bg-[#f3f4f6] font-sans overflow-x-hidden">

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="ml-64 min-h-screen">
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <main class="px-5 py-5">
        <div class="max-w-[1500px] mx-auto">

            <div class="mt-5 flex items-center gap-3 mb-6">
                <div class="w-[36px] h-[36px] rounded-[8px] bg-[#f5eee3] flex items-center justify-center shrink-0">
                    <i data-lucide="badge-check" class="w-[18px] h-[18px] text-[#cd9737]"></i>
                </div>

                <h2 class="whitespace-nowrap text-[20px] font-normal text-[#40352a]" style="font-family: Georgia, 'Times New Roman', serif;">
                    MY KYC
                </h2>

                <div class="w-full h-[2px] bg-[#d1a14b]"></div>
            </div>

            <?php if ($message !== ''): ?>
                <div class="mb-5 rounded-lg border border-green-200 bg-green-50 px-5 py-3 text-green-700 flex items-center gap-2">
                    <i data-lucide="check-circle-2" class="w-5 h-5 text-green-600 shrink-0"></i>
                    <span><?php echo e($message); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-5 py-3 text-red-700 flex items-center gap-2">
                    <i data-lucide="alert-circle" class="w-5 h-5 text-red-600 shrink-0"></i>
                    <span><?php echo e($error); ?></span>
                </div>
            <?php endif; ?>

            <div class="bg-white rounded-[12px] shadow border border-gray-200 overflow-hidden mb-7">

                <div class="bg-[#103258] text-white px-7 py-5 rounded-t-[10px]">
                    <h2 class="text-[20px] font-bold">
                        Upload Authorized Person KYC &amp; Address Verification
                    </h2>
                    <p class="text-[14px] sm:text-[15px] mt-1 text-slate-200">
                        Please upload your valid address proof document, type the matching address, and pin your showroom/business on the map.
                    </p>
                </div>

                <form method="POST" enctype="multipart/form-data" class="px-7 py-6">

                    <!-- Document Type -->
                    <div class="grid grid-cols-1 md:grid-cols-[200px_1fr] items-center gap-4 mb-7">
                        <label class="text-[16px] font-medium text-[#0f1f45]">
                            Select Document Type <span class="text-red-500">*</span>
                        </label>

                        <select name="document_type"
                                required
                                class="w-full h-[52px] rounded-[9px] border border-gray-300 px-4 text-[16px] focus:outline-none focus:ring-2 focus:ring-[#103258]">
                            <option value="">Select document type</option>
                            <option value="AADHAR CARD">AADHAR CARD</option>
                            <option value="PAN CARD">PAN CARD</option>
                            <option value="PASSPORT">PASSPORT</option>
                            <option value="DRIVING LICENSE">DRIVING LICENSE</option>
                            <option value="SHOP ESTABLISHMENT / ELECTRICITY BILL">SHOP ESTABLISHMENT / ELECTRICITY BILL</option>
                        </select>
                    </div>

                    <!-- Upload Document File -->
                    <div class="border-t border-dashed border-gray-300 pt-6">
                        <label class="block text-[16px] font-medium text-[#0f1f45] mb-3">
                            Upload Document File <span class="text-red-500">*</span>
                        </label>

                        <div class="flex items-center gap-3">
                            <label class="inline-flex items-center gap-2 px-5 py-2.5 border border-gray-300 rounded-[8px] bg-[#f9fafb] cursor-pointer hover:bg-gray-100 transition shadow-sm">
                                <i data-lucide="upload" class="w-4 h-4 text-gray-600"></i>
                                <span class="text-[15px] font-medium">Choose File</span>
                                <input type="file"
                                       name="document_file"
                                       required
                                       accept=".jpg,.jpeg,.png,.pdf"
                                       class="hidden"
                                       onchange="document.getElementById('fileName').innerText = this.files[0] ? this.files[0].name : '';">
                            </label>

                            <span id="fileName" class="text-[14px] text-gray-600 font-mono"></span>
                        </div>

                        <p class="mt-2 text-[13px] text-gray-500">
                            Max. file size allowed: <strong>2 MB</strong> | Supported formats: <strong>JPG, PNG, PDF</strong>
                        </p>
                    </div>

                    <!-- Address & Map Location Pin Section -->
                    <div class="border-t border-dashed border-gray-300 pt-7 mt-7">
                        <div class="flex items-center gap-2 mb-2">
                            <i data-lucide="map-pinned" class="w-5 h-5 text-[#cd9737]"></i>
                            <h3 class="text-[18px] font-bold text-[#0f1f45]">Showroom Address &amp; Location Pin</h3>
                        </div>
                        <p class="text-xs sm:text-sm text-gray-500 mb-5">
                            Admin will verify this address against the document uploaded above.
                        </p>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-5">
                            <div class="md:col-span-2">
                                <label class="block text-[15px] font-semibold text-[#0f1f45] mb-2">
                                    Full Address (As shown on Document) <span class="text-red-500">*</span>
                                </label>
                                <textarea name="address"
                                          id="inputAddress"
                                          required
                                          rows="3"
                                          class="w-full rounded-[9px] border border-gray-300 p-3.5 text-[15px] focus:outline-none focus:ring-2 focus:ring-[#103258]"
                                          placeholder="Type complete street address, shop/plot no, building name, area..."><?php echo e($franchise['address_1'] ?? ''); ?></textarea>
                            </div>

                            <div>
                                <label class="block text-[15px] font-semibold text-[#0f1f45] mb-2">
                                    Postal PIN Code <span class="text-red-500">*</span>
                                </label>
                                <input type="text"
                                       name="pincode"
                                       id="inputPincode"
                                       required
                                       maxlength="10"
                                       value="<?php echo e($franchise['postal_code'] ?? ''); ?>"
                                       class="w-full h-[48px] rounded-[9px] border border-gray-300 px-4 text-[15px] focus:outline-none focus:ring-2 focus:ring-[#103258]"
                                       placeholder="e.g. 392001">

                                <p class="text-xs text-gray-500 mt-2">
                                    City: <strong><?php echo e($defaultCity); ?></strong> | State: <strong><?php echo e($defaultState); ?></strong>
                                </p>
                            </div>
                        </div>

                        <!-- Interactive Map Card -->
                        <div class="rounded-xl border border-gray-200 bg-[#fbfbfb] p-4 sm:p-5">
                            <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                                <div>
                                    <label class="text-[15px] font-bold text-[#0f1f45] flex items-center gap-1.5">
                                        <i data-lucide="map-pin" class="w-4 h-4 text-red-600"></i>
                                        Pin Exact Showroom / Business Location on Map
                                    </label>
                                    <p class="text-xs text-gray-500 mt-0.5">Drag the red pin or click anywhere on the map to mark your exact location.</p>
                                </div>
                                <div>
                                    <button type="button"
                                            id="btnLocateMe"
                                            class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-lg bg-emerald-700 text-white hover:bg-emerald-800 shadow-sm transition">
                                        <i data-lucide="navigation" class="w-3.5 h-3.5"></i> Use My Location (GPS)
                                    </button>
                                </div>
                            </div>

                            <!-- Map Search -->
                            <div class="flex items-center gap-2 mb-3">
                                <input type="text"
                                       id="mapSearchInput"
                                       placeholder="Search city, area or landmark to move map..."
                                       class="flex-1 h-[40px] px-3.5 rounded-lg border border-gray-300 text-xs sm:text-sm bg-white focus:outline-none focus:ring-1 focus:ring-[#103258]">
                                <button type="button"
                                        id="btnSearchMap"
                                        class="h-[40px] px-4 rounded-lg bg-[#103258] text-white text-xs font-semibold hover:opacity-90">
                                    Find Location
                                </button>
                            </div>

                            <!-- Map -->
                            <div id="kycMap" class="w-full rounded-lg border border-gray-300 overflow-hidden shadow-inner"></div>

                            <div class="mt-2.5 flex flex-wrap items-center justify-between gap-2 text-xs text-gray-600">
                                <span class="flex items-center gap-1.5">
                                    <i data-lucide="check-circle" class="w-3.5 h-3.5 text-emerald-600"></i>
                                    Coordinates: <strong id="coordsText" class="font-mono text-gray-800">Pin placed at center</strong>
                                </span>
                                <span id="mapAddressText" class="text-gray-500 truncate max-w-full sm:max-w-[50%] italic"></span>
                            </div>

                            <input type="hidden" name="latitude" id="inputLat" value="">
                            <input type="hidden" name="longitude" id="inputLng" value="">
                            <input type="hidden" name="map_address" id="inputMapAddress" value="">
                        </div>
                    </div>

                    <div class="border-t border-gray-300 mt-8 pt-7">
                        <button type="submit"
                                class="bg-[#103258] text-white px-9 py-3 rounded-[8px] text-[16px] font-semibold hover:opacity-90 shadow-md">
                            Submit KYC for Verification
                        </button>
                    </div>

                </form>
            </div>

            <!-- KYC History Table -->
            <div class="bg-white rounded-[12px] shadow border border-gray-200 p-6">
                <h3 class="text-[17px] font-bold text-[#103258] mb-4 flex items-center gap-2">
                    <i data-lucide="file-text" class="w-5 h-5 text-[#cd9737]"></i>
                    Uploaded KYC Documents &amp; Verification Status
                </h3>

                <div class="overflow-x-auto">
                    <table class="w-full border border-gray-200 rounded-[8px] overflow-hidden">
                        <thead>
                        <tr class="bg-[#103258] text-white text-sm">
                            <th class="py-3 px-3 text-center font-bold">Sr No</th>
                            <th class="py-3 px-3 text-center font-bold">Upload Date</th>
                            <th class="py-3 px-3 text-center font-bold">Document Type</th>
                            <th class="py-3 px-3 text-left font-bold">Address &amp; PIN</th>
                            <th class="py-3 px-3 text-center font-bold">Location Pin</th>
                            <th class="py-3 px-3 text-center font-bold">Document</th>
                            <th class="py-3 px-3 text-center font-bold">Status</th>
                        </tr>
                        </thead>

                        <tbody>
                        <?php if (!empty($documents)): ?>
                            <?php $sr = 1; ?>
                            <?php foreach ($documents as $doc): ?>
                                <tr class="border-b border-gray-200 text-sm hover:bg-gray-50/50">
                                    <td class="py-3 px-3 text-center text-gray-600 font-mono">
                                        <?php echo $sr++; ?>
                                    </td>

                                    <td class="py-3 px-3 text-center text-gray-600 whitespace-nowrap">
                                        <?php echo e(date('d-m-Y h:i A', strtotime($doc['uploaded_at']))); ?>
                                    </td>

                                    <td class="py-3 px-3 text-center font-semibold text-gray-800">
                                        <?php echo e($doc['document_type']); ?>
                                    </td>

                                    <td class="py-3 px-3 text-left max-w-[240px]">
                                        <div class="text-xs text-gray-800 leading-snug">
                                            <p class="font-medium break-words"><?php echo e($doc['address'] ?: '—'); ?></p>
                                            <?php if (!empty($doc['pincode'])): ?>
                                                <span class="inline-block mt-0.5 text-[11px] font-mono text-gray-500 font-semibold">PIN: <?php echo e($doc['pincode']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                    <td class="py-3 px-3 text-center whitespace-nowrap">
                                        <?php if (!empty($doc['latitude']) && !empty($doc['longitude'])): ?>
                                            <a href="https://www.google.com/maps?q=<?php echo urlencode($doc['latitude'] . ',' . $doc['longitude']); ?>"
                                               target="_blank"
                                               class="inline-flex items-center gap-1 px-2.5 py-1 text-xs rounded bg-emerald-50 text-emerald-700 border border-emerald-200 font-semibold hover:bg-emerald-100 shadow-sm">
                                                <i data-lucide="map-pin" class="w-3.5 h-3.5 text-red-500"></i>
                                                View Pin
                                            </a>
                                        <?php else: ?>
                                            <span class="text-xs text-gray-400 italic">No pin</span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="py-3 px-3 text-center whitespace-nowrap">
                                        <a href="<?php echo e($uploadUrl . $doc['document_file']); ?>"
                                           target="_blank"
                                           class="inline-flex items-center justify-center bg-[#103258] text-white px-3.5 py-1.5 rounded-[6px] text-xs font-semibold hover:bg-[#0c2440]">
                                            View File
                                        </a>
                                    </td>

                                    <td class="py-3 px-3 text-center whitespace-nowrap">
                                        <?php
                                        $status = $doc['status'];
                                        $statusClass = 'bg-yellow-100 text-yellow-700';

                                        if ($status === 'Approved') {
                                            $statusClass = 'bg-green-100 text-green-700';
                                        } elseif ($status === 'Rejected') {
                                            $statusClass = 'bg-red-100 text-red-700';
                                        }
                                        ?>

                                        <span class="px-3 py-1 rounded-full text-xs font-semibold <?php echo e($statusClass); ?>">
                                            <?php echo e($status); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="py-6 px-4 text-center text-gray-500">
                                    No KYC documents uploaded yet.
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

<script>
document.addEventListener("DOMContentLoaded", function () {
    if (typeof lucide !== "undefined") {
        lucide.createIcons();
    }

    // Default coordinates: Gujarat / Bharuch or India center
    let initialLat = 21.7051;
    let initialLng = 72.9959;
    let initialZoom = 13;

    // Check if map container exists
    const mapContainer = document.getElementById('kycMap');
    if (!mapContainer) return;

    // Initialize Leaflet Map with OpenStreetMap tiles (100% Free, No API Key needed)
    const map = L.map('kycMap').setView([initialLat, initialLng], initialZoom);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    // Add draggable marker
    const marker = L.marker([initialLat, initialLng], {
        draggable: true
    }).addTo(map);

    function updateCoords(lat, lng) {
        document.getElementById('inputLat').value = lat.toFixed(7);
        document.getElementById('inputLng').value = lng.toFixed(7);
        document.getElementById('coordsText').innerText = 'Lat: ' + lat.toFixed(5) + ', Lng: ' + lng.toFixed(5);
    }

    function reverseGeocode(lat, lng) {
        fetch('https://nominatim.openstreetmap.org/reverse?format=json&lat=' + lat + '&lon=' + lng)
            .then(res => res.json())
            .then(data => {
                if (data && data.display_name) {
                    document.getElementById('mapAddressText').innerText = data.display_name;
                    document.getElementById('inputMapAddress').value = data.display_name;

                    // If address textarea is empty, suggest the reverse geocoded address
                    const addrBox = document.getElementById('inputAddress');
                    if (!addrBox.value.trim()) {
                        addrBox.value = data.display_name;
                    }

                    if (data.address && data.address.postcode) {
                        const pinBox = document.getElementById('inputPincode');
                        if (!pinBox.value.trim()) {
                            pinBox.value = data.address.postcode;
                        }
                    }
                }
            })
            .catch(() => {});
    }

    // Set initial values
    updateCoords(initialLat, initialLng);

    // Marker drag event
    marker.on('dragend', function (e) {
        const pos = marker.getLatLng();
        updateCoords(pos.lat, pos.lng);
        reverseGeocode(pos.lat, pos.lng);
    });

    // Map click event: moves marker to clicked position
    map.on('click', function (e) {
        marker.setLatLng(e.latlng);
        updateCoords(e.latlng.lat, e.latlng.lng);
        reverseGeocode(e.latlng.lat, e.latlng.lng);
    });

    // GPS "Locate Me" button
    document.getElementById('btnLocateMe').addEventListener('click', function () {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function (pos) {
                const lat = pos.coords.latitude;
                const lng = pos.coords.longitude;
                map.setView([lat, lng], 16);
                marker.setLatLng([lat, lng]);
                updateCoords(lat, lng);
                reverseGeocode(lat, lng);
            }, function (err) {
                alert('Could not get device location: ' + err.message + '. Please drag the pin on map.');
            }, { enableHighAccuracy: true });
        } else {
            alert('Geolocation is not supported by your browser. Please drag the pin manually.');
        }
    });

    // Search Location button
    document.getElementById('btnSearchMap').addEventListener('click', function () {
        searchLocation();
    });

    document.getElementById('mapSearchInput').addEventListener('keypress', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            searchLocation();
        }
    });

    function searchLocation() {
        const query = document.getElementById('mapSearchInput').value.trim();
        if (!query) return;

        fetch('https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(query))
            .then(res => res.json())
            .then(data => {
                if (data && data.length > 0) {
                    const lat = parseFloat(data[0].lat);
                    const lng = parseFloat(data[0].lon);
                    map.setView([lat, lng], 15);
                    marker.setLatLng([lat, lng]);
                    updateCoords(lat, lng);
                    reverseGeocode(lat, lng);
                } else {
                    alert('Location not found. Try searching with city or area name, or drag the pin directly.');
                }
            })
            .catch(() => {
                alert('Search service temporary unavailable. Please click/drag the pin on the map.');
            });
    }

    // Try detecting GPS on first load if user allows
    if (navigator.geolocation && !document.getElementById('inputLat').value) {
        navigator.geolocation.getCurrentPosition(function (pos) {
            const lat = pos.coords.latitude;
            const lng = pos.coords.longitude;
            map.setView([lat, lng], 15);
            marker.setLatLng([lat, lng]);
            updateCoords(lat, lng);
        }, function () {});
    }
});
</script>

</body>
</html>
