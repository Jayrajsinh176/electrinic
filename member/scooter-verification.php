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
require_once __DIR__ . '/../includes/ind_income_functions.php';

$msg = '';
$err = '';

// Handle Vehicle Registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_vehicle'])) {
    $vehicleId = (int)($_POST['vehicle_id'] ?? 0);
    $payOption = trim($_POST['payment_option'] ?? 'full') === 'finance' ? 'finance' : 'full';
    $regNo = trim($_POST['registration_number'] ?? '');

    $veh = ind_get_vehicle_by_id($conn, $vehicleId);
    if (!$veh) {
        $err = "Please select a valid electric vehicle model.";
    } elseif ($regNo === '') {
        $err = "Please enter vehicle registration / chassis number.";
    } else {
        $chk = $conn->prepare("SELECT id FROM ind_scooter_promotions WHERE customer_id = ? AND status = 'active' LIMIT 1");
        $chk->bind_param('s', $customerId);
        $chk->execute();
        if ($chk->get_result()->fetch_row()) {
            $err = "You already have an active Scooter Promotion program.";
        } else {
            $model = (string)$veh['model_name'];
            $bprAmt = $payOption === 'finance' ? (float)$veh['bpr_finance_monthly'] : (float)$veh['bpr_upfront_monthly'];
            $monthsTotal = (int)($veh['bpr_months'] ?: 25);
            $initialSupport = ind_refresh_scooter_support($conn, $customerId);

            $ins = $conn->prepare("INSERT INTO ind_scooter_promotions 
                (customer_id, vehicle_id, vehicle_model, payment_option, registration_number, monthly_bpr_amount, support_percent, months_total, months_paid, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 'active', NOW())");
            $ins->bind_param('sisssddi', $customerId, $vehicleId, $model, $payOption, $regNo, $bprAmt, $initialSupport, $monthsTotal);
            if ($ins->execute()) {
                $msg = "Electric Scooter registered successfully! You are now enrolled in the {$monthsTotal}-Month Brand Promotion Program.";
            } else {
                $err = "Failed to register vehicle: " . $conn->error;
            }
            $ins->close();
        }
        $chk->close();
    }
}

// Handle Digital Agreement Signature
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sign_agreement'])) {
    $promoId = (int)($_POST['promotion_id'] ?? 0);
    if ($promoId > 0) {
        $upd = $conn->prepare("UPDATE ind_scooter_promotions 
                               SET agreement_signed = 1, agreement_signed_at = NOW() 
                               WHERE id = ? AND customer_id = ?");
        $upd->bind_param('is', $promoId, $customerId);
        if ($upd->execute()) {
            $msg = "Digital Vehicle Brand Promotion Agreement signed successfully!";
        }
        $upd->close();
    }
}

// Handle Monthly Video Verification Submission with Live GPS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_verification'])) {
    $promoId = (int)($_POST['promotion_id'] ?? 0);
    $lat = (float)($_POST['gps_latitude'] ?? 0.0);
    $lng = (float)($_POST['gps_longitude'] ?? 0.0);

    if ($promoId <= 0) {
        $err = "Invalid promotion ID.";
    } elseif (abs($lat) < 0.0001 && abs($lng) < 0.0001) {
        $err = "Live GPS coordinates are required! Please allow location access on your browser.";
    } else {
        $uploadBase = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads';
        $videoDir = $uploadBase . DIRECTORY_SEPARATOR . 'videos';
        $screenDir = $uploadBase . DIRECTORY_SEPARATOR . 'screenshots';

        if (!is_dir($videoDir)) @mkdir($videoDir, 0777, true);
        if (!is_dir($screenDir)) @mkdir($screenDir, 0777, true);

        $videoPath = '';
        $waPath = '';
        $socialPath = '';
        $odoPath = '';
        $kmRun = (int)($_POST['km_run'] ?? 0);
        $odoReading = (int)($_POST['odometer_reading'] ?? 0);
        $feedbackNotes = trim($_POST['feedback_notes'] ?? '');

        // 1. Scooter Video Upload
        if (!isset($_FILES['scooter_video']) || $_FILES['scooter_video']['error'] !== UPLOAD_ERR_OK) {
            $err = "Please upload the scooter verification video.";
        } else {
            $vFile = $_FILES['scooter_video'];
            $vExt = strtolower(pathinfo($vFile['name'], PATHINFO_EXTENSION));
            $allowedVideo = ['mp4', 'mov', 'avi', 'mkv', 'webm', '3gp'];

            if (!in_array($vExt, $allowedVideo, true)) {
                $err = "Invalid video format. Allowed: MP4, MOV, WEBM, 3GP.";
            } elseif ($vFile['size'] > 100 * 1024 * 1024) {
                $err = "Video file is too large (maximum 100MB allowed).";
            } else {
                $vName = 'scooter_' . $customerId . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $vExt;
                $vDest = $videoDir . DIRECTORY_SEPARATOR . $vName;
                if (move_uploaded_file($vFile['tmp_name'], $vDest)) {
                    $videoPath = 'uploads/videos/' . $vName;
                } else {
                    $err = "Failed to save uploaded video.";
                }
            }
        }

        // 2. Odometer Photo Proof (Optional / Recommended)
        if (empty($err) && isset($_FILES['odometer_photo']) && $_FILES['odometer_photo']['error'] === UPLOAD_ERR_OK) {
            $oFile = $_FILES['odometer_photo'];
            $oExt = strtolower(pathinfo($oFile['name'], PATHINFO_EXTENSION));
            if (in_array($oExt, ['jpg', 'jpeg', 'png', 'webp'], true) && $oFile['size'] <= 10 * 1024 * 1024) {
                $oName = 'odo_' . $customerId . '_' . date('Ymd_His') . '.' . $oExt;
                if (move_uploaded_file($oFile['tmp_name'], $screenDir . DIRECTORY_SEPARATOR . $oName)) {
                    $odoPath = 'uploads/screenshots/' . $oName;
                }
            }
        }

        // 3. WhatsApp Status Screenshot
        if (empty($err) && isset($_FILES['whatsapp_screenshot']) && $_FILES['whatsapp_screenshot']['error'] === UPLOAD_ERR_OK) {
            $wFile = $_FILES['whatsapp_screenshot'];
            $wExt = strtolower(pathinfo($wFile['name'], PATHINFO_EXTENSION));
            if (in_array($wExt, ['jpg', 'jpeg', 'png', 'webp'], true) && $wFile['size'] <= 10 * 1024 * 1024) {
                $wName = 'wa_' . $customerId . '_' . date('Ymd_His') . '.' . $wExt;
                if (move_uploaded_file($wFile['tmp_name'], $screenDir . DIRECTORY_SEPARATOR . $wName)) {
                    $waPath = 'uploads/screenshots/' . $wName;
                }
            }
        }

        // 4. Social Media Story Screenshot
        if (empty($err) && isset($_FILES['social_screenshot']) && $_FILES['social_screenshot']['error'] === UPLOAD_ERR_OK) {
            $sFile = $_FILES['social_screenshot'];
            $sExt = strtolower(pathinfo($sFile['name'], PATHINFO_EXTENSION));
            if (in_array($sExt, ['jpg', 'jpeg', 'png', 'webp'], true) && $sFile['size'] <= 10 * 1024 * 1024) {
                $sName = 'social_' . $customerId . '_' . date('Ymd_His') . '.' . $sExt;
                if (move_uploaded_file($sFile['tmp_name'], $screenDir . DIRECTORY_SEPARATOR . $sName)) {
                    $socialPath = 'uploads/screenshots/' . $sName;
                }
            }
        }

        if (empty($err) && !empty($videoPath)) {
            $pQuery = $conn->query("SELECT months_paid FROM ind_scooter_promotions WHERE id = {$promoId} LIMIT 1")->fetch_assoc();
            $monthNo = ((int)($pQuery['months_paid'] ?? 0)) + 1;

            $insVer = $conn->prepare("INSERT INTO ind_video_verifications
                (customer_id, promotion_id, month_number, km_run, odometer_reading, video_file_path, odometer_photo_path,
                 video_deleted, gps_latitude, gps_longitude, gps_captured_at, whatsapp_screenshot_path, social_story_screenshot_path,
                 feedback_notes, show_on_landing_page, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?, NOW(), ?, ?, ?, 1, 'submitted', NOW())");
            $insVer->bind_param('siiiissddsss', $customerId, $promoId, $monthNo, $kmRun, $odoReading, $videoPath, $odoPath, $lat, $lng, $waPath, $socialPath, $feedbackNotes);
            if ($insVer->execute()) {
                $msg = "Monthly promotion video, KM activity (" . number_format($kmRun) . " KM) and screenshots submitted successfully with live GPS! It will be reviewed by Admin.";
            } else {
                $err = "Database error: " . $conn->error;
            }
            $insVer->close();
        }
    }
}

// Fetch active promotion
$promo = null;
$stmt = $conn->prepare("SELECT * FROM ind_scooter_promotions WHERE customer_id = ? AND status = 'active' ORDER BY id DESC LIMIT 1");
$stmt->bind_param('s', $customerId);
$stmt->execute();
$promo = $stmt->get_result()->fetch_assoc();
$stmt->close();

$currentSupport = 50.0;
$directVehicles = 0;
if ($promo) {
    $currentSupport = ind_refresh_scooter_support($conn, $customerId);
    $directVehicles = (int)$promo['direct_vehicles_count'];
}

// Fetch member's past verifications
$verifications = [];
if ($promo) {
    $vRes = $conn->query("SELECT * FROM ind_video_verifications WHERE promotion_id = " . (int)$promo['id'] . " ORDER BY id DESC");
    while ($vRow = $vRes->fetch_assoc()) {
        $verifications[] = $vRow;
    }
}

function m($n) { return '₹' . number_format((float)$n, 2); }
$bprVehicles = function_exists('ind_get_active_vehicles') ? ind_get_active_vehicles($conn, null, true) : [];
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicle Verification &amp; Monthly Proof Upload</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body { background:#f3f3f3; }
        @media (min-width: 1024px) { .main-wrap { margin-left: 256px; } }
        .data-row:hover { background:#fdf6ea !important; }
    </style>
</head>
<body>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<div class="main-wrap min-h-screen">
    <?php include __DIR__ . '/includes/navbar.php'; ?>
    <div class="px-3 sm:px-5 py-3">

        <div class="mt-4 sm:mt-5 flex items-center justify-between flex-wrap gap-3">
            <div class="flex items-center gap-3">
                <div class="w-[34px] h-[34px] rounded-[8px] bg-[#f5eee3] flex items-center justify-center shrink-0">
                    <i data-lucide="camera" class="w-[17px] h-[17px] text-[#cd9737]"></i>
                </div>
                <div>
                    <h2 class="text-[20px] font-normal text-[#40352a]" style="font-family: Georgia, 'Times New Roman', serif;">MONTHLY VEHICLE VERIFICATION &amp; PROOF UPLOAD</h2>
                    <p class="text-xs text-[#8a7c68]">Upload monthly video, live GPS location, and odometer reading to qualify for monthly BPR support.</p>
                </div>
            </div>
            <div>
                <a href="scooter-promotion.php" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-white border border-[#d9dde2] hover:bg-[#faf6ee] text-xs font-semibold text-[#40352a] transition shadow-xs">
                    <i data-lucide="file-text" class="w-4 h-4 text-[#cd9737]"></i>
                    <span>View Scooter Income Statement &rarr;</span>
                </a>
            </div>
        </div>
        <div class="w-full h-[2px] bg-[#d1a14b] mt-3"></div>

        <?php if (!empty($msg)): ?>
            <div class="mt-4 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-xl text-sm flex items-center gap-2">
                <i data-lucide="check-circle" class="w-4 h-4 text-green-600 shrink-0"></i>
                <span><?php echo htmlspecialchars($msg); ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($err)): ?>
            <div class="mt-4 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl text-sm flex items-center gap-2">
                <i data-lucide="alert-circle" class="w-4 h-4 text-red-600 shrink-0"></i>
                <span><?php echo htmlspecialchars($err); ?></span>
            </div>
        <?php endif; ?>

        <?php if (!$promo): ?>
            <!-- Enrollment Card -->
            <div class="mt-5 bg-white rounded-2xl border border-[#e2d8c8] p-6 max-w-2xl">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center text-amber-800">
                        <i data-lucide="zap" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h3 class="text-[16px] font-bold text-[#40352a]">Enroll in Electric Scooter Brand Promotion Program</h3>
                        <p class="text-[12px] text-[#8a7c68]">Get 50% up to 100% Free Monthly Support for 25 Months (PDF Pages 7 &amp; 8)</p>
                    </div>
                </div>

                <form method="POST" class="mt-5 space-y-4" id="enrollForm">
                    <div>
                        <label class="block text-[12px] font-medium text-[#40352a]">Select Electric Vehicle Model</label>
                        <select name="vehicle_id" id="vehicle_id" required onchange="updateVehicleDetails()" class="mt-1 w-full rounded-xl border border-[#d9dde2] px-3.5 py-2.5 text-sm outline-none focus:border-[#b47e1d] bg-white">
                            <option value="">-- Choose Eligible Vehicle Model --</option>
                            <?php foreach ($bprVehicles as $bv): ?>
                                <option value="<?php echo (int)$bv['id']; ?>"
                                        data-price="<?php echo (float)$bv['vehicle_price']; ?>"
                                        data-upfront-bpr="<?php echo (float)$bv['bpr_upfront_monthly']; ?>"
                                        data-finance-bpr="<?php echo (float)$bv['bpr_finance_monthly']; ?>"
                                        data-upfront-tot="<?php echo (float)$bv['bpr_upfront_total']; ?>"
                                        data-finance-tot="<?php echo (float)$bv['bpr_finance_total']; ?>"
                                        data-upfront-eff="<?php echo (float)$bv['bpr_upfront_effective_cost']; ?>"
                                        data-finance-eff="<?php echo (float)$bv['bpr_finance_effective_cost']; ?>"
                                        data-months="<?php echo (int)$bv['bpr_months']; ?>"
                                        data-battery="<?php echo htmlspecialchars($bv['battery_spec']); ?>"
                                        data-range="<?php echo htmlspecialchars($bv['range_km']); ?>"
                                        data-warranty="<?php echo (int)$bv['warranty_months']; ?>">
                                    <?php echo htmlspecialchars($bv['model_name']); ?> (<?php echo m($bv['vehicle_price']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[12px] font-medium text-[#40352a] mb-1.5">Payment Option</label>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="flex items-center gap-2 p-3 rounded-xl border border-gray-200 cursor-pointer hover:bg-[#faf6ee] transition" id="payOptFullLbl">
                                <input type="radio" name="payment_option" value="full" checked onchange="updateVehicleDetails()" class="accent-[#b47e1d]">
                                <div>
                                    <span class="block text-xs font-bold text-[#40352a]">Upfront / Full Payment</span>
                                    <span class="block text-[11px] text-[#8a7c68]">Standard cash purchase</span>
                                </div>
                            </label>
                            <label class="flex items-center gap-2 p-3 rounded-xl border border-gray-200 cursor-pointer hover:bg-[#faf6ee] transition" id="payOptFinLbl">
                                <input type="radio" name="payment_option" value="finance" onchange="updateVehicleDetails()" class="accent-[#b47e1d]">
                                <div>
                                    <span class="block text-xs font-bold text-[#40352a]">On Finance</span>
                                    <span class="block text-[11px] text-[#8a7c68]">Monthly loan/EMI support</span>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Live Breakdown Card -->
                    <div id="planSummaryBox" class="p-4 rounded-xl border border-[#e2d8c8] bg-[#fdf9f2] text-xs space-y-2 hidden">
                        <div class="flex items-center justify-between">
                            <span class="text-[#8a7c68]">Official Vehicle Price:</span>
                            <span class="font-bold text-[#40352a]" id="dispVehiclePrice">₹0.00</span>
                        </div>
                        <div class="flex items-center justify-between border-t border-[#f0e8d9] pt-2">
                            <span class="text-[#8a7c68]">Monthly Brand Promotion Reward (BPR):</span>
                            <span class="font-bold text-[#b47e1d] text-sm" id="dispMonthlyBpr">₹0.00 / month</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-[#8a7c68]">Total 25-Month BPR Payout:</span>
                            <span class="font-bold text-[#1e7a48]" id="dispTotalBpr">₹0.00</span>
                        </div>
                        <div class="flex items-center justify-between border-t border-[#f0e8d9] pt-2">
                            <span class="text-[#8a7c68]">Effective Vehicle Cost:</span>
                            <span class="font-bold text-[#40352a]" id="dispEffectiveCost">₹0.00</span>
                        </div>
                        <div class="flex items-center justify-between text-[11px] text-[#9e8f7e]">
                            <span>Program Duration:</span>
                            <span class="font-medium" id="dispDuration">25 Months (support up to 36m per agreement)</span>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[12px] font-medium text-[#40352a]">Vehicle Registration / Chassis Number</label>
                        <input type="text" name="registration_number" placeholder="e.g. DL-01-AB-1234 or Chassis No." required class="mt-1 w-full rounded-xl border border-[#d9dde2] px-3.5 py-2.5 text-sm outline-none focus:border-[#b47e1d]">
                    </div>

                    <button type="submit" name="register_vehicle" class="w-full py-3 bg-[#40352a] hover:bg-[#2c241c] text-white rounded-xl text-sm font-semibold transition flex items-center justify-center gap-2 shadow-sm">
                        <i data-lucide="check" class="w-4 h-4"></i> Submit &amp; Enroll in 25-Month Support Program
                    </button>
                </form>
            </div>
        <?php else: ?>

            <!-- Digital Agreement Section -->
            <div class="mt-4 bg-white rounded-2xl border border-[#e2d8c8] p-5">
                <div class="flex items-center justify-between flex-wrap gap-3">
                    <div>
                        <h3 class="text-sm font-bold text-[#40352a]">Digital Vehicle Brand Promotion Agreement</h3>
                        <p class="text-[12px] text-[#8a7c68]">Official contractual agreement for 25 months brand promotion support (up to 36 months per agreement).</p>
                    </div>
                    <div>
                        <?php if ((int)$promo['agreement_signed'] === 1): ?>
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold bg-green-100 text-green-800">
                                <i data-lucide="check-circle" class="w-3.5 h-3.5"></i> Digitally Signed (<?php echo date('d M Y', strtotime($promo['agreement_signed_at'])); ?>)
                            </span>
                        <?php else: ?>
                            <form method="POST">
                                <input type="hidden" name="promotion_id" value="<?php echo (int)$promo['id']; ?>">
                                <button type="submit" name="sign_agreement" class="px-4 py-2 bg-[#b47e1d] hover:bg-[#966513] text-white text-xs font-bold rounded-xl transition flex items-center gap-1.5 shadow-sm">
                                    <i data-lucide="pen-tool" class="w-3.5 h-3.5"></i> Sign Agreement Digitally
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                <!-- Agreement Summary Details -->
                <div class="mt-4 p-3.5 rounded-xl bg-[#faf6ee] border border-[#e8decb] text-xs grid grid-cols-2 sm:grid-cols-4 gap-3 text-[#5f5140]">
                    <div>
                        <span class="block text-[#9e8f7e] text-[10px] uppercase font-semibold">Agreement Ref</span>
                        <span class="font-bold text-[#40352a]">VPA-<?php echo str_pad((string)$promo['id'], 5, '0', STR_PAD_LEFT); ?></span>
                    </div>
                    <div>
                        <span class="block text-[#9e8f7e] text-[10px] uppercase font-semibold">Monthly Base BPR</span>
                        <span class="font-bold text-[#b47e1d]"><?php echo m($promo['monthly_bpr_amount']); ?> / mo</span>
                    </div>
                    <div>
                        <span class="block text-[#9e8f7e] text-[10px] uppercase font-semibold">Current Support</span>
                        <span class="font-bold text-[#1e7a48]"><?php echo number_format($currentSupport, 0); ?>% (<?php echo m($promo['monthly_bpr_amount'] * $currentSupport / 100); ?>)</span>
                    </div>
                    <div>
                        <span class="block text-[#9e8f7e] text-[10px] uppercase font-semibold">Total Duration</span>
                        <span class="font-bold text-[#40352a]"><?php echo (int)$promo['months_total']; ?> Months</span>
                    </div>
                </div>
            </div>

            <!-- Monthly Video Verification & GPS Uploader -->
            <div class="mt-4 bg-white rounded-2xl border border-[#e2d8c8] p-5">
                <div class="flex items-center gap-2 mb-2">
                    <i data-lucide="video" class="w-5 h-5 text-[#b47e1d]"></i>
                    <h3 class="text-sm font-bold text-[#40352a]">Monthly Promotion Verification &amp; Live GPS Upload</h3>
                </div>
                <p class="text-[12px] text-[#8a7c68] mb-4">
                    Upload your monthly promotion video with your scooter, live GPS location, and status/story screenshots. 
                    <strong class="text-amber-800">Note: To save server storage, uploaded video files are permanently auto-deleted from the server after 48 hours.</strong>
                </p>

                <form method="POST" enctype="multipart/form-data" class="space-y-4" id="videoUploadForm">
                    <input type="hidden" name="promotion_id" value="<?php echo (int)$promo['id']; ?>">
                    <input type="hidden" name="gps_latitude" id="gps_latitude" value="0.0">
                    <input type="hidden" name="gps_longitude" id="gps_longitude" value="0.0">

                    <!-- Live GPS Indicator -->
                    <div class="p-3.5 rounded-xl border border-dashed border-[#b47e1d] bg-[#fdf6ea] flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <i data-lucide="map-pin" class="w-5 h-5 text-[#b47e1d]"></i>
                            <div>
                                <p class="text-xs font-bold text-[#40352a]">Live GPS Location Capture</p>
                                <p class="text-[11px] text-[#8a7c68]" id="gpsStatus">Click "Capture GPS" or wait for browser detection...</p>
                            </div>
                        </div>
                        <button type="button" onclick="captureGPS()" class="px-3 py-1.5 bg-[#40352a] text-white rounded-lg text-xs font-semibold hover:bg-[#251e18] transition flex items-center gap-1">
                            <i data-lucide="locate" class="w-3.5 h-3.5"></i> Capture GPS
                        </button>
                    </div>

                    <!-- Row 1: Monthly Kilometre (KM) Activity -->
                    <div class="p-3.5 rounded-xl bg-[#faf6ee] border border-[#e8decb]">
                        <div class="flex items-center gap-2 mb-2">
                            <i data-lucide="gauge" class="w-4 h-4 text-[#b47e1d]"></i>
                            <h4 class="text-xs font-bold text-[#40352a]">Monthly Activity &amp; Kilometre (KM) Target</h4>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-[11px] font-semibold text-[#40352a]">Kilometres Driven This Month *</label>
                                <input type="number" name="km_run" placeholder="e.g. 500" required min="0" class="mt-1 w-full rounded-lg border border-[#d9dde2] px-3 py-1.5 text-xs outline-none focus:border-[#b47e1d] bg-white">
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-[#40352a]">Current Odometer Reading (KM)</label>
                                <input type="number" name="odometer_reading" placeholder="e.g. 1850" min="0" class="mt-1 w-full rounded-lg border border-[#d9dde2] px-3 py-1.5 text-xs outline-none focus:border-[#b47e1d] bg-white">
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-[#40352a]">Odometer / Speedometer Photo Proof</label>
                                <input type="file" name="odometer_photo" accept="image/*" class="mt-1 w-full text-xs text-gray-500 file:mr-2 file:py-1.5 file:px-2.5 file:rounded-lg file:border-0 file:text-[11px] file:font-semibold file:bg-[#40352a] file:text-white hover:file:bg-[#251e18]">
                            </div>
                        </div>
                    </div>

                    <!-- Row 2: Video & Media Proofs -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-[#40352a]">1. Scooter Video &amp; Feedback *</label>
                            <input type="file" name="scooter_video" accept="video/*" required class="mt-1 w-full text-xs text-gray-500 file:mr-2 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-[#40352a] file:text-white hover:file:bg-[#251e18]">
                            <p class="text-[10px] text-[#8a7c68] mt-1">Show full scooter, number plate &amp; your feedback.</p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-[#40352a]">2. WhatsApp Status Screenshot</label>
                            <input type="file" name="whatsapp_screenshot" accept="image/*" class="mt-1 w-full text-xs text-gray-500 file:mr-2 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-[#40352a] file:text-white hover:file:bg-[#251e18]">
                            <p class="text-[10px] text-[#8a7c68] mt-1">Screenshot showing active WhatsApp status.</p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-[#40352a]">3. Instagram / FB Story Screenshot</label>
                            <input type="file" name="social_screenshot" accept="image/*" class="mt-1 w-full text-xs text-gray-500 file:mr-2 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-[#40352a] file:text-white hover:file:bg-[#251e18]">
                            <p class="text-[10px] text-[#8a7c68] mt-1">Screenshot showing social story upload.</p>
                        </div>
                    </div>

                    <!-- Row 3: Rider Feedback Notes -->
                    <div>
                        <label class="block text-xs font-semibold text-[#40352a]">Monthly Riding Experience &amp; Feedback Notes</label>
                        <textarea name="feedback_notes" rows="2" placeholder="Briefly share how your scooter is riding this month (mileage, battery backup, performance)..." class="mt-1 w-full rounded-xl border border-[#d9dde2] px-3.5 py-2 text-xs outline-none focus:border-[#b47e1d]"></textarea>
                    </div>

                    <button type="submit" name="submit_verification" class="px-5 py-2.5 bg-[#1e7a48] hover:bg-[#155a34] text-white rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-sm">
                        <i data-lucide="upload-cloud" class="w-4 h-4"></i> Submit Monthly Verification &amp; KM Target
                    </button>
                </form>
            </div>

            <!-- Verification History -->
            <?php if (!empty($verifications)): ?>
            <div class="mt-4 bg-white rounded-2xl border border-[#e2d8c8] overflow-hidden">
                <div class="px-5 py-3 border-b border-[#f0e9db] text-[14px] font-semibold text-[#40352a]">Verification Submissions &amp; Status</div>
                <div class="overflow-x-auto">
                    <table class="w-full text-[13px]">
                        <thead class="bg-[#faf5ec] text-[#9e8f7e] text-[11px] uppercase tracking-wider">
                            <tr>
                                <th class="text-left px-4 py-3">Month</th>
                                <th class="text-left px-4 py-3">KM Activity</th>
                                <th class="text-left px-4 py-3">GPS Location</th>
                                <th class="text-center px-4 py-3">48h Video Purge</th>
                                <th class="text-center px-4 py-3">Verification</th>
                                <th class="text-left px-4 py-3">Submitted At</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#f0e9db]">
                            <?php foreach ($verifications as $v): ?>
                            <tr class="data-row">
                                <td class="px-4 py-3 font-semibold text-[#40352a]">Month <?php echo (int)$v['month_number']; ?></td>
                                <td class="px-4 py-3 text-xs">
                                    <span class="font-bold text-[#b47e1d]"><?php echo number_format((int)($v['km_run'] ?? 0)); ?> KM</span>
                                    <?php if (!empty($v['odometer_photo_path'])): ?>
                                        <a href="../<?php echo htmlspecialchars($v['odometer_photo_path']); ?>" target="_blank" class="block text-blue-600 underline text-[11px]">View Meter Photo</a>
                                    <?php endif; ?>
                                    <?php if (!empty($v['video_file_path']) && (int)($v['video_deleted'] ?? 0) === 0): ?>
                                        <a href="../<?php echo htmlspecialchars($v['video_file_path']); ?>" target="_blank" class="block text-emerald-700 font-semibold underline text-[11px] mt-0.5">▷ Watch Video Proof</a>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-xs">
                                    <span class="font-mono text-[#40352a]"><?php echo number_format($v['gps_latitude'], 4); ?>, <?php echo number_format($v['gps_longitude'], 4); ?></span>
                                    <a href="https://maps.google.com/?q=<?php echo $v['gps_latitude']; ?>,<?php echo $v['gps_longitude']; ?>" target="_blank" class="text-blue-600 underline ml-1 text-[11px]">View Map</a>
                                </td>
                                <td class="px-4 py-3 text-center text-xs">
                                    <?php if ((int)$v['video_deleted'] === 1): ?>
                                        <span class="text-gray-500 font-semibold">Deleted (48h Purged)</span>
                                    <?php else: ?>
                                        <span class="text-green-700 font-medium">Stored (Pending 48h purge)</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <?php if ($v['status'] === 'verified'): ?>
                                        <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-green-100 text-green-800">Approved</span>
                                    <?php elseif ($v['status'] === 'rejected'): ?>
                                        <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-red-100 text-red-800">Rejected</span>
                                    <?php else: ?>
                                        <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-100 text-amber-800">Under Review</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-xs text-[#8a7c68]"><?php echo htmlspecialchars(substr($v['created_at'], 0, 16)); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

        <?php endif; ?>

    </div>
</div>

<script>
lucide.createIcons();

function updateVehicleDetails() {
    const sel = document.getElementById('vehicle_id');
    const box = document.getElementById('planSummaryBox');
    if (!sel || !box) return;

    const opt = sel.options[sel.selectedIndex];
    if (!opt || !opt.value) {
        box.classList.add('hidden');
        return;
    }

    const payOption = document.querySelector('input[name="payment_option"]:checked')?.value || 'full';
    const price = parseFloat(opt.getAttribute('data-price')) || 0;
    const isFin = payOption === 'finance';

    const monthlyBpr = isFin ? parseFloat(opt.getAttribute('data-finance-bpr')) : parseFloat(opt.getAttribute('data-upfront-bpr'));
    const totalBpr = isFin ? parseFloat(opt.getAttribute('data-finance-tot')) : parseFloat(opt.getAttribute('data-upfront-tot'));
    const effCost = isFin ? parseFloat(opt.getAttribute('data-finance-eff')) : parseFloat(opt.getAttribute('data-upfront-eff'));
    const months = parseInt(opt.getAttribute('data-months')) || 25;

    document.getElementById('dispVehiclePrice').innerText = '₹' + price.toLocaleString('en-IN', {minimumFractionDigits: 2});
    document.getElementById('dispMonthlyBpr').innerText = '₹' + monthlyBpr.toLocaleString('en-IN', {minimumFractionDigits: 2}) + ' / month';
    document.getElementById('dispTotalBpr').innerText = '₹' + totalBpr.toLocaleString('en-IN', {minimumFractionDigits: 2}) + ' (' + months + ' mos)';
    document.getElementById('dispEffectiveCost').innerText = '₹' + effCost.toLocaleString('en-IN', {minimumFractionDigits: 2});
    document.getElementById('dispDuration').innerText = months + ' Months (support up to 36m per agreement)';

    box.classList.remove('hidden');
}

function captureGPS() {
    const statusText = document.getElementById('gpsStatus');
    if (navigator.geolocation) {
        statusText.innerText = "Requesting live GPS coordinates...";
        navigator.geolocation.getCurrentPosition(
            function(position) {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                document.getElementById('gps_latitude').value = lat;
                document.getElementById('gps_longitude').value = lng;
                statusText.innerHTML = "<span class='text-green-700 font-bold'>✓ GPS Captured:</span> " + lat.toFixed(5) + ", " + lng.toFixed(5);
            },
            function(error) {
                statusText.innerHTML = "<span class='text-red-700 font-bold'>✕ Location denied. Please allow GPS permission.</span>";
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
        );
    } else {
        statusText.innerText = "Geolocation is not supported by your browser.";
    }
}

document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('gps_latitude')) {
        captureGPS();
    }
});
</script>
</body>
</html>

