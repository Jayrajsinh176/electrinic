<?php
/**
 * ELECTRINC — Multi-Step Member Signup & Activation Flow
 * 
 * Step 1: Sponsor & Personal Details (Validated via AJAX)
 * Step 2: Choose Joining Method:
 *         Option 1: Direct Buy Electric Vehicle (Full Online Payment)
 *         Option 2: Joining Booking & Activation (₹10,000 — 36M Voucher or 10M Cashback)
 * Step 3: Online Payment Verification (UPI / Bank Transfer + UTR + Receipt Upload)
 * Finish: Account Created with Member ID & Password + Activation Request Submitted
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection failed. Please check config/db.php');
}
$conn->set_charset('utf8mb4');

function e($s): string {
    return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8');
}

// ════════════════════════════════════════════════════════════════════════════
//  AJAX VALIDATION ENDPOINT (For real-time Step 1 check)
// ════════════════════════════════════════════════════════════════════════════
if (isset($_GET['ajax_validate']) && $_GET['ajax_validate'] === 'step1') {
    header('Content-Type: application/json; charset=utf-8');

    $refId       = trim((string)($_POST['referral_id'] ?? ''));
    $email       = strtolower(trim((string)($_POST['email'] ?? '')));
    $mobile      = trim((string)($_POST['mobile'] ?? ''));
    $sponsorName = '';

    if ($refId === '') {
        echo json_encode(['ok' => false, 'error' => 'Please enter a Referral / Sponsor ID.']);
        exit;
    }

    // Check referral exists
    $st = $conn->prepare("SELECT full_name FROM signup_users WHERE customer_id = ? LIMIT 1");
    $st->bind_param('s', $refId);
    $st->execute();
    $sRow = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$sRow) {
        echo json_encode(['ok' => false, 'error' => 'Referral ID "' . $refId . '" was not found. Please enter a valid Sponsor ID.']);
        exit;
    }
    $sponsorName = (string)($sRow['full_name'] ?? '');

    // Check email uniqueness
    if ($email !== '') {
        $st = $conn->prepare("SELECT id FROM signup_users WHERE email = ? LIMIT 1");
        $st->bind_param('s', $email);
        $st->execute();
        $eExists = (bool)$st->get_result()->fetch_row();
        $st->close();
        if ($eExists) {
            echo json_encode(['ok' => false, 'error' => 'The email address "' . $email . '" is already registered. Please sign in or use a different email.']);
            exit;
        }
    }

    // Check mobile uniqueness
    if ($mobile !== '') {
        $st = $conn->prepare("SELECT id FROM signup_users WHERE mobile = ? LIMIT 1");
        $st->bind_param('s', $mobile);
        $st->execute();
        $mExists = (bool)$st->get_result()->fetch_row();
        $st->close();
        if ($mExists) {
            echo json_encode(['ok' => false, 'error' => 'The mobile number "' . $mobile . '" is already registered. Please sign in or use a different number.']);
            exit;
        }
    }

    echo json_encode(['ok' => true, 'sponsor_name' => $sponsorName]);
    exit;
}

// ════════════════════════════════════════════════════════════════════════════
//  VEHICLES CATALOG DATA (For Option 1)
// ════════════════════════════════════════════════════════════════════════════
$vehiclesList = [];
try {
    $vq = $conn->query("SELECT * FROM ind_electric_vehicles WHERE status = 'active' ORDER BY sort_order ASC, id ASC");
    if ($vq && $vq->num_rows > 0) {
        while ($row = $vq->fetch_assoc()) {
            $cat = in_array($row['category'], ['rickshaw', 'loader', 'auto'], true) ? 'rickshaw' : $row['category'];
            $img = !empty($row['image_path']) ? $row['image_path'] : 'max-vxi.jpeg';
            if (strpos($img, 'assets/') === false) {
                $img = 'assets/ev-products/' . ltrim($img, '/');
            }
            $vehiclesList[] = [
                'id'              => (int)$row['id'],
                'model_code'      => (string)($row['model_code'] ?? 'EV_' . $row['id']),
                'model_name'      => (string)$row['model_name'],
                'category'        => $cat,
                'vehicle_price'   => (float)$row['vehicle_price'],
                'battery_spec'    => !empty($row['battery_spec']) ? (string)$row['battery_spec'] : 'Lithium / Gel Battery',
                'range_km'        => !empty($row['range_km']) ? (string)$row['range_km'] : '50–60 KM+',
                'warranty_months' => (int)($row['warranty_months'] ?? 36),
                'image_path'      => $img,
            ];
        }
    }
} catch (\Throwable $e) {}

// Fallback vehicle list if table is empty or unmigrated
if (empty($vehiclesList)) {
    $vehiclesList = [
        [
            'id'              => 1,
            'model_code'      => 'MAX_VXI',
            'model_name'      => 'MAX VXI',
            'category'        => 'scooter',
            'vehicle_price'   => 66000.00,
            'battery_spec'    => '60V / 32AH Gel Battery',
            'range_km'        => '50 - 55 km+',
            'warranty_months' => 12,
            'image_path'      => 'assets/ev-products/max-vxi.jpeg',
        ],
        [
            'id'              => 2,
            'model_code'      => 'MAX_ZXI',
            'model_name'      => 'MAX ZXI',
            'category'        => 'scooter',
            'vehicle_price'   => 79000.00,
            'battery_spec'    => '60V / 30AH Gel Battery',
            'range_km'        => '50 - 60 km+',
            'warranty_months' => 36,
            'image_path'      => 'assets/ev-products/evelocy-m2.jpeg',
        ],
        [
            'id'              => 3,
            'model_code'      => 'ACTIVE_CHETAAK_FIN',
            'model_name'      => 'ACTIVE CHETAAK (Finance)',
            'category'        => 'scooter',
            'vehicle_price'   => 116000.00,
            'battery_spec'    => '60V / 30AH Gel Battery',
            'range_km'        => '50 - 60 km+',
            'warranty_months' => 36,
            'image_path'      => 'assets/ev-products/active-chetaak.jpeg',
        ],
        [
            'id'              => 4,
            'model_code'      => 'ACTIVE_CHETAAK_NOFIN',
            'model_name'      => 'ACTIVE CHETAAK (Without Finance)',
            'category'        => 'scooter',
            'vehicle_price'   => 127000.00,
            'battery_spec'    => '60V / 30AH Gel Battery',
            'range_km'        => '50 - 60 km+',
            'warranty_months' => 36,
            'image_path'      => 'assets/ev-products/active-chetaak.jpeg',
        ],
        [
            'id'              => 5,
            'model_code'      => 'AUTO_L3',
            'model_name'      => 'Auto L-3',
            'category'        => 'rickshaw',
            'vehicle_price'   => 185000.00,
            'battery_spec'    => '51.2V / 100AH Lithium',
            'range_km'        => '90–100 KM+',
            'warranty_months' => 36,
            'image_path'      => 'assets/ev-products/auto-l3.jpeg',
        ],
        [
            'id'              => 6,
            'model_code'      => 'ERIKSHAW_SOLAR',
            'model_name'      => 'E-Rickshaw with Solar',
            'category'        => 'rickshaw',
            'vehicle_price'   => 210000.00,
            'battery_spec'    => '51.2V / 100AH Lithium',
            'range_km'        => '100 KM+',
            'warranty_months' => 36,
            'image_path'      => 'assets/ev-products/erikshaw-solar.jpeg',
        ],
    ];
}

// ════════════════════════════════════════════════════════════════════════════
//  BANK & COMPANY PAYMENT DETAILS (For Step 3)
// ════════════════════════════════════════════════════════════════════════════
$companyBank = [
    'Account Name' => 'ELECTRINC INFRASTRUCTURE PVT LTD',
    'Bank'         => 'State Bank of India',
    'Account No'   => '41893829104',
    'IFSC'         => 'SBIN0011758',
    'Branch'       => 'Kopar Khairane, Navi Mumbai',
    'UPI'          => 'electrinc@phonepe',
];

$indianStates = [
    'Andhra Pradesh', 'Arunachal Pradesh', 'Assam', 'Bihar', 'Chhattisgarh',
    'Goa', 'Gujarat', 'Haryana', 'Himachal Pradesh', 'Jharkhand',
    'Karnataka', 'Kerala', 'Madhya Pradesh', 'Maharashtra', 'Manipur',
    'Meghalaya', 'Mizoram', 'Nagaland', 'Odisha', 'Punjab',
    'Rajasthan', 'Sikkim', 'Tamil Nadu', 'Telangana', 'Tripura',
    'Uttar Pradesh', 'Uttarakhand', 'West Bengal',
    'Andaman and Nicobar Islands', 'Chandigarh', 'Dadra and Nagar Haveli and Daman and Diu',
    'Delhi', 'Jammu and Kashmir', 'Ladakh', 'Lakshadweep', 'Puducherry'
];

function generateCustomerId(mysqli $conn): string
{
    do {
        $randomNumber = str_pad((string)mt_rand(0, 9999999), 7, '0', STR_PAD_LEFT);
        $customerId   = 'NA' . $randomNumber;
        $stmt = $conn->prepare("SELECT id FROM signup_users WHERE customer_id = ? LIMIT 1");
        $stmt->bind_param('s', $customerId);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();
    } while ($exists);
    return $customerId;
}

$referral_id      = trim((string)($_GET['ref'] ?? ''));
$sponsor_name     = '';
$fromReferralLink = false;
$referralReadonly = '';

if ($referral_id !== '') {
    $fromReferralLink = true;
    $referralReadonly = 'readonly';
    $st = $conn->prepare("SELECT full_name FROM signup_users WHERE customer_id = ? LIMIT 1");
    $st->bind_param('s', $referral_id);
    $st->execute();
    $sRow = $st->get_result()->fetch_assoc();
    $st->close();
    if ($sRow) {
        $sponsor_name = (string)$sRow['full_name'];
    }
}

// ════════════════════════════════════════════════════════════════════════════
//  FINAL POST SUBMISSION HANDLER
// ════════════════════════════════════════════════════════════════════════════
$success         = '';
$error           = '';
$new_customer_id = '';
$full_name       = '';
$plain_password  = '';
$selected_plan_summary = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = trim((string)($_POST['_form_token'] ?? ''));
    $sessionToken   = (string)($_SESSION['_signup_token'] ?? '');

    // Double submit protection
    if ($submittedToken !== '' && $submittedToken === $sessionToken && isset($_SESSION['_signup_done'])) {
        $success               = 'success';
        $new_customer_id       = $_SESSION['_signup_done']['id']   ?? '';
        $full_name             = $_SESSION['_signup_done']['name'] ?? '';
        $plain_password        = $_SESSION['_signup_done']['pass'] ?? '';
        $selected_plan_summary = $_SESSION['_signup_done']['plan'] ?? '';
    } else {
        // Step 1 values
        $full_name    = trim((string)($_POST['full_name'] ?? ''));
        $email        = strtolower(trim((string)($_POST['email'] ?? '')));
        $mobile       = trim((string)($_POST['mobile'] ?? ''));
        $plain_password = trim((string)($_POST['password'] ?? ''));
        $state        = trim((string)($_POST['state'] ?? ''));
        $city         = trim((string)($_POST['city'] ?? ''));
        $referral_id  = trim((string)($_POST['referral_id'] ?? ''));
        $terms        = isset($_POST['terms']) ? 1 : 0;
        $placement_id = null;
        $position     = null;

        // Step 2 values
        $joining_type   = trim((string)($_POST['joining_type'] ?? 'vehicle')); // 'vehicle' or 'activation'
        $selected_veh_id = (int)($_POST['selected_vehicle_id'] ?? 0);
        $benefit_choice = strtolower(trim((string)($_POST['benefit_choice'] ?? 'product'))); // 'product' or 'cashback'

        // Step 3 values
        $payment_mode   = trim((string)($_POST['payment_mode'] ?? 'UPI'));
        $transaction_no = trim((string)($_POST['transaction_no'] ?? ''));
        $remark         = trim((string)($_POST['remark'] ?? ''));

        // Basic validations
        if ($full_name === '' || $email === '' || $mobile === '' || $plain_password === '' || $state === '' || $city === '' || $referral_id === '') {
            $error = 'Please fill all required personal details in Step 1.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (!preg_match('/^[0-9]{10}$/', $mobile)) {
            $error = 'Mobile number must be exactly 10 digits.';
        } elseif (strlen($plain_password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } elseif (!$terms) {
            $error = 'Please agree to the Terms and Conditions to proceed.';
        } else {
            // Check sponsor exists
            $stmt = $conn->prepare("SELECT id FROM signup_users WHERE customer_id = ? LIMIT 1");
            $stmt->bind_param('s', $referral_id);
            $stmt->execute();
            $refExists = (bool)$stmt->get_result()->fetch_row();
            $stmt->close();

            // Check email uniqueness
            $stmt = $conn->prepare("SELECT id FROM signup_users WHERE email = ? LIMIT 1");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $eExists = (bool)$stmt->get_result()->fetch_row();
            $stmt->close();

            // Check mobile uniqueness
            $stmt = $conn->prepare("SELECT id FROM signup_users WHERE mobile = ? LIMIT 1");
            $stmt->bind_param('s', $mobile);
            $stmt->execute();
            $mExists = (bool)$stmt->get_result()->fetch_row();
            $stmt->close();

            if (!$refExists) {
                $error = 'Referral / Sponsor ID not found. Please check and try again.';
            } elseif ($eExists) {
                $error = 'This email address is already registered. Please sign in or use another email.';
            } elseif ($mExists) {
                $error = 'This mobile number is already registered. Please sign in or use another number.';
            } else {
                // Determine Plan & Amount
                $finalAmount = 10000.00;
                $finalBenefitChoice = 'product';
                $productsJson = '[]';
                $planTitle = '';

                if ($joining_type === 'vehicle') {
                    // Option 1: Direct Buy Electric Vehicle
                    $foundVehicle = null;
                    foreach ($vehiclesList as $v) {
                        if ((int)$v['id'] === $selected_veh_id) {
                            $foundVehicle = $v;
                            break;
                        }
                    }
                    if (!$foundVehicle && !empty($vehiclesList)) {
                        $foundVehicle = $vehiclesList[0];
                    }

                    if (!$foundVehicle) {
                        $error = 'Please select an Electric Vehicle from Option 1.';
                    } else {
                        $finalAmount = (float)$foundVehicle['vehicle_price'];
                        $finalBenefitChoice = 'vehicle_purchase';
                        $planTitle = 'Direct EV Purchase — ' . $foundVehicle['model_name'];
                        $productsJson = json_encode([
                            [
                                'vehicle_id' => $foundVehicle['id'],
                                'item'       => $foundVehicle['model_name'] . ' (' . ucfirst($foundVehicle['category']) . ')',
                                'amount'     => $finalAmount,
                                'qty'        => 1
                            ]
                        ], JSON_UNESCAPED_UNICODE);
                    }
                } else {
                    // Option 2: Joining Booking & Activation (₹10,000)
                    $finalAmount = 10000.00;
                    if ($benefit_choice === 'cashback') {
                        $finalBenefitChoice = 'cashback';
                        $planTitle = 'Joining Booking — Monthly Cashback (10 Months)';
                    } else {
                        $finalBenefitChoice = 'product';
                        $planTitle = 'Joining Booking — Product Purchase Voucher (36 Months)';
                    }
                    $productsJson = json_encode([
                        [
                            'item'   => $planTitle,
                            'amount' => $finalAmount,
                            'qty'    => 1
                        ]
                    ], JSON_UNESCAPED_UNICODE);
                }

                // Validate Step 3 Payment fields
                $allowedModes = ['UPI', 'IMPS', 'NEFT', 'RTGS', 'Online'];
                if ($error === '') {
                    if ($transaction_no === '') {
                        $error = 'Please enter your online transaction / UTR number in Step 3.';
                    } elseif (!isset($_FILES['receipt']) || (int)$_FILES['receipt']['error'] !== UPLOAD_ERR_OK) {
                        $error = 'Please upload a screenshot or photo of your online payment receipt.';
                    } else {
                        // Validate uploaded file
                        $tmpName = (string)$_FILES['receipt']['tmp_name'];
                        $fileName = (string)$_FILES['receipt']['name'];
                        $fileSize = (int)$_FILES['receipt']['size'];
                        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                        $okExts = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'pdf' => 'application/pdf'];
                        $mime = @mime_content_type($tmpName) ?: '';

                        if (!is_uploaded_file($tmpName)) {
                            $error = 'Invalid uploaded receipt file.';
                        } elseif (!isset($okExts[$ext]) || ($mime !== '' && $mime !== $okExts[$ext])) {
                            $error = 'Payment receipt must be a JPG, PNG, or PDF file.';
                        } elseif ($fileSize <= 0 || $fileSize > 5 * 1024 * 1024) {
                            $error = 'Payment receipt file must be between 1 KB and 5 MB.';
                        } else {
                            // File is valid: move to destination
                            $receiptDir = __DIR__ . '/uploads/receipts/';
                            if (!is_dir($receiptDir)) {
                                @mkdir($receiptDir, 0755, true);
                            }
                            $newReceiptName = 'receipt_' . date('Ymd_His') . '_' . random_int(1000, 9999) . '.' . $ext;
                            $relReceiptPath = 'uploads/receipts/' . $newReceiptName;

                            if (@move_uploaded_file($tmpName, __DIR__ . '/' . $relReceiptPath)) {
                                // 1. Generate Customer ID
                                $customerId = generateCustomerId($conn);

                                // 2. Insert into signup_users (Unilevel IND business plan - no binary placement)
                                $insUser = $conn->prepare("
                                    INSERT INTO signup_users
                                        (customer_id, referral_id, placement_id, position,
                                         full_name, email, mobile, password, state, city,
                                         terms_accepted, age_confirmed, registration_status, benefit_choice)
                                    VALUES (?, ?, NULL, NULL, ?, ?, ?, ?, ?, ?, ?, 1, 'Inactive', ?)
                                ");
                                $insUser->bind_param(
                                    'ssssssssis',
                                    $customerId,
                                    $referral_id,
                                    $full_name,
                                    $email,
                                    $mobile,
                                    $plain_password,
                                    $state,
                                    $city,
                                    $terms,
                                    $finalBenefitChoice
                                );

                                if ($insUser->execute()) {
                                    $insUser->close();

                                    // 3. Insert into activation_requests
                                    $insAct = $conn->prepare("
                                        INSERT INTO activation_requests
                                            (customer_id, amount, payment_mode, benefit_choice, transaction_id, payment_slip, remark, products_json, status, created_at)
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending', NOW())
                                    ");
                                    $insAct->bind_param(
                                        'sdssssss',
                                        $customerId,
                                        $finalAmount,
                                        $payment_mode,
                                        $finalBenefitChoice,
                                        $transaction_no,
                                        $relReceiptPath,
                                        $remark,
                                        $productsJson
                                    );
                                    $insAct->execute();
                                    $insAct->close();

                                    // Save success state
                                    $_SESSION['_signup_token'] = $submittedToken;
                                    $_SESSION['_signup_done']  = [
                                        'id'   => $customerId,
                                        'name' => $full_name,
                                        'pass' => $plain_password,
                                        'plan' => $planTitle . ' (₹' . number_format($finalAmount, 2) . ')',
                                    ];

                                    $success               = 'success';
                                    $new_customer_id       = $customerId;
                                    $selected_plan_summary = $planTitle . ' (₹' . number_format($finalAmount, 2) . ')';
                                } else {
                                    $error = 'Registration failed: ' . $insUser->error;
                                    @unlink(__DIR__ . '/' . $relReceiptPath);
                                }
                            } else {
                                $error = 'Failed to save the payment receipt. Please check folder permissions and try again.';
                            }
                        }
                    }
                }
            }
        }
    }
}

// Generate token for double-submit prevention
$formToken = bin2hex(random_bytes(16));
$_SESSION['_signup_token'] = null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Electrinc – Member Registration & Joining</title>
    <link rel="icon" href="assets/electrinc-logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --saffron: #E0700C;
            --saffron-d: #B25400;
            --saffron-soft: #FFF3E7;
            --green: #0E7A3B;
            --green-d: #0A5E2D;
            --green-soft: #EAF5EC;
            --night: #07241B;
            --night-2: #0C382B;
            --night-3: #12492F;
            --ink: #1C1C1C;
            --ink-2: #5F6960;
            --page: #FBFAF6;
            --line: #ECE7DD;
            --gold: #D4A64A;
        }

        * { box-sizing: border-box; }
        body {
            font-family: 'Manrope', 'Plus Jakarta Sans', system-ui, sans-serif;
            background: var(--page);
            color: var(--ink);
        }

        .dot-grid {
            background-image: radial-gradient(rgba(255, 255, 255, 0.12) 1px, transparent 1px);
            background-size: 22px 22px;
        }

        /* Smooth tab & step animation */
        .step-panel { display: none; }
        .step-panel.active { display: block; animation: stepFadeIn .3s ease-out; }

        @keyframes stepFadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* Success overlay */
        #success-overlay {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 1000;
            background: rgba(7, 36, 27, 0.85);
            backdrop-filter: blur(10px);
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        #success-overlay.show { display: flex; animation: fadeIn .3s ease; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

        .cred-box {
            background: #F4FAF6;
            border: 1.5px dashed #A3D4B6;
            border-radius: 12px;
            padding: 10px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }
        .cred-value {
            font-family: 'Courier New', monospace;
            font-size: 16px;
            font-weight: 800;
            color: #0C382B;
            letter-spacing: .04em;
        }
        .copy-btn {
            font-size: 12px;
            font-weight: 700;
            color: #0E7A3B;
            background: #EAF5EC;
            border-radius: 6px;
            padding: 5px 12px;
            cursor: pointer;
            transition: all .2s;
        }
        .copy-btn:hover { background: #D5EADB; }

        .field-input {
            width: 100%;
            background: transparent;
            border: 0;
            border-bottom: 2px solid #ECE7DD;
            padding-bottom: 9px;
            font-size: 15px;
            font-weight: 600;
            color: #1C1C1C;
            outline: none;
            transition: border-color .2s, box-shadow .2s;
        }
        .field-input::placeholder { color: #A8A29E; font-weight: 400; }
        .field-input:focus { border-bottom-color: #0E7A3B; }

        select.field-input {
            cursor: pointer;
            padding-right: 24px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%230E7A3B' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 4px center;
            background-size: 16px;
        }

        .section-card {
            background: #ffffff;
            border-radius: 24px;
            padding: 26px 22px;
            box-shadow: 0 4px 20px rgba(12, 56, 43, 0.04);
            border: 1px solid #ECE7DD;
        }
        @media (min-width: 640px) { .section-card { padding: 32px 28px; } }

        /* Radio card selection */
        .choice-card {
            border: 2px solid #ECE7DD;
            border-radius: 18px;
            background: #ffffff;
            transition: all .2s cubic-bezier(.4, 0, .2, 1);
            cursor: pointer;
        }
        .choice-card:hover {
            border-color: #6FD69A;
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(14, 122, 59, 0.08);
        }
        .choice-card.selected {
            border-color: #0C382B;
            background-color: #F4FAF6;
            box-shadow: 0 8px 25px rgba(12, 56, 43, 0.12);
        }

        /* Step circle indicators */
        .step-circle {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 800;
            transition: all .25s ease;
        }
        .step-circle.active {
            background: linear-gradient(135deg, #0C382B 0%, #0E7A3B 100%);
            color: #ffffff;
            box-shadow: 0 0 0 4px rgba(14, 122, 59, 0.22);
        }
        .step-circle.completed {
            background: #0E7A3B;
            color: #ffffff;
        }
        .step-circle.inactive {
            background: #F2EFE8;
            color: #8C827A;
            border: 1px solid #ECE7DD;
        }
    </style>
</head>

<body class="min-h-screen bg-[#FBFAF6] text-[#1C1C1C] pb-16">

<!-- ════════════════════════════════════════════════════════════════════════
     SUCCESS OVERLAY (Creds & Welcome)
════════════════════════════════════════════════════════════════════════ -->
<?php if ($success === 'success'): ?>
<div id="success-overlay" class="show">
    <div class="w-full max-w-[480px] rounded-[24px] bg-white shadow-[0_25px_60px_rgba(0,0,0,.25)] overflow-hidden border border-[#ECE7DD]">
        <div class="px-8 pt-8 pb-6 flex flex-col items-center text-center border-b border-[#ECE7DD] bg-gradient-to-b from-[#F4FAF6] to-white relative overflow-hidden">
            <div class="absolute -right-10 -top-10 w-40 h-40 rounded-full bg-emerald-400/15 blur-2xl pointer-events-none"></div>
            <div class="absolute -left-10 -bottom-10 w-40 h-40 rounded-full bg-amber-400/15 blur-2xl pointer-events-none"></div>

            <div class="bg-gradient-to-r from-[#07241B] via-[#0C382B] to-[#12492F] rounded-2xl px-5 py-3 inline-flex items-center mb-4 shadow border border-emerald-500/20 relative z-10">
                <img src="assets/electrinc-logo.png" alt="Electrinc" class="h-8 w-auto object-contain">
            </div>
            <div class="w-[58px] h-[58px] rounded-full bg-[#EAF5EC] flex items-center justify-center mb-3 border-2 border-[#0E7A3B] relative z-10 shadow-sm">
                <i data-lucide="check" class="w-7 h-7 text-[#0E7A3B]"></i>
            </div>
            <h2 class="text-[#07241B] text-[22px] font-extrabold relative z-10">Registration Successful!</h2>
            <p class="text-[14px] text-stone-600 mt-1 relative z-10">
                Welcome, <strong class="text-stone-900"><?= e($full_name) ?></strong>! Your membership account and online payment verification request have been registered.
            </p>
            <?php if (!empty($selected_plan_summary)): ?>
                <div class="mt-3 px-3.5 py-1.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold relative z-10">
                    <?= e($selected_plan_summary) ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="px-8 py-5 space-y-3.5">
            <div>
                <p class="text-[11px] font-bold text-stone-400 uppercase tracking-wider mb-1">Your Member ID</p>
                <div class="cred-box">
                    <span class="cred-value" id="member-id"><?= e($new_customer_id) ?></span>
                    <button type="button" class="copy-btn" onclick="copyText('member-id', this)">Copy ID</button>
                </div>
            </div>
            <div>
                <p class="text-[11px] font-bold text-stone-400 uppercase tracking-wider mb-1">Your Password</p>
                <div class="cred-box">
                    <span class="cred-value" id="member-pass"><?= e($plain_password) ?></span>
                    <button type="button" class="copy-btn" onclick="copyText('member-pass', this)">Copy Password</button>
                </div>
            </div>
            <p class="text-[12px] text-stone-500 text-center pt-1 leading-relaxed">
                Please save your credentials securely. Once your online payment receipt is verified by admin, your distributor account will be fully activated.
            </p>
        </div>

        <div class="px-8 pb-8 pt-2">
            <a href="login.php" class="block w-full h-[52px] rounded-2xl bg-gradient-to-r from-[#0C382B] via-[#0E7A3B] to-[#0A5E2D] hover:from-[#07241B] hover:to-[#084A24] text-white text-[15px] font-extrabold text-center leading-[52px] shadow-[0_10px_25px_-5px_rgba(14,122,59,0.35)] transition">
                Proceed to Member Sign In
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ════════════════════════════════════════════════════════════════════════
     MAIN FORM CONTAINER
════════════════════════════════════════════════════════════════════════ -->
<div class="w-full flex justify-center px-4 pt-6 md:pt-10">
<div class="w-full max-w-[840px]">

    <!-- Top Utility Bar (matching portal navigation header) -->
    <div class="bg-white rounded-2xl border border-[#ECE7DD] px-5 py-3 mb-5 shadow-sm flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-[#FFF3E7] text-[#E0700C] flex items-center justify-center shrink-0 border border-[#E0700C]/20">
                <i data-lucide="zap" class="w-5 h-5"></i>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <span class="font-extrabold text-[#0C382B] text-sm md:text-base">Join ELECTRINC</span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-[#EAF5EC] text-[#0E7A3B] border border-[#0E7A3B]/30">
                        New Member
                    </span>
                </div>
                <p class="text-[11px] text-stone-500">Official Distributor Registration Portal</p>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <a href="login.php" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-stone-50 hover:bg-[#F4FAF6] text-stone-700 hover:text-[#0C382B] text-xs font-bold border border-[#ECE7DD] transition">
                <i data-lucide="log-in" class="w-4 h-4 text-[#0E7A3B]"></i>
                <span class="hidden sm:inline">Already registered?</span> Sign In
            </a>
        </div>
    </div>

    <!-- Hero Banner (100% matched with site UI & activation card) -->
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#0c392b] via-[#0f4d3a] to-[#07241b] border border-emerald-500/20 p-6 sm:p-8 text-white shadow-xl mb-6">
        <!-- Ambient Glows -->
        <div class="absolute -right-16 -bottom-16 w-72 h-72 rounded-full bg-emerald-400/15 blur-3xl pointer-events-none"></div>
        <div class="absolute right-32 top-0 w-48 h-48 rounded-full bg-amber-400/15 blur-2xl pointer-events-none"></div>
        <div class="absolute inset-0 dot-grid opacity-20 pointer-events-none"></div>

        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div class="max-w-xl space-y-2.5">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-emerald-400/20 text-emerald-200 border border-emerald-300/30">
                    <i data-lucide="shield-check" class="w-3.5 h-3.5 text-emerald-300"></i>
                    Official Member Onboarding
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-white">
                    Create Your Account
                </h1>
                <p class="text-sm text-emerald-100/90 leading-relaxed">
                    Welcome to ELECTRINC. Choose your Electric Vehicle or ₹10,000 joining booking package to start your business.
                </p>
                <div class="pt-2 flex flex-wrap gap-2.5">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs font-medium bg-white/10 text-emerald-100 border border-white/15">
                        <i data-lucide="check" class="w-3.5 h-3.5 text-emerald-300"></i> Lifetime Business Access
                    </span>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs font-medium bg-white/10 text-emerald-100 border border-white/15">
                        <i data-lucide="check" class="w-3.5 h-3.5 text-emerald-300"></i> Direct &amp; Team Income
                    </span>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs font-medium bg-white/10 text-emerald-100 border border-white/15">
                        <i data-lucide="check" class="w-3.5 h-3.5 text-emerald-300"></i> Online Verification Ready
                    </span>
                </div>
            </div>

            <!-- Electrinc Logo Badge -->
            <div class="shrink-0 flex items-center md:justify-end">
                <div class="bg-white/10 backdrop-blur-md border border-white/20 rounded-2xl px-5 py-3 shadow-inner">
                    <img src="assets/electrinc-logo.png" alt="Electrinc" class="h-9 md:h-10 w-auto object-contain">
                </div>
            </div>
        </div>
    </div>

    <!-- Multi-Step Progress Header -->
    <div class="bg-white rounded-2xl border border-[#ECE7DD] p-4 mb-6 shadow-sm">
        <div class="flex items-center justify-between max-w-lg mx-auto">
            <!-- Step 1 -->
            <div class="flex items-center gap-2.5">
                <div id="circle-step-1" class="step-circle active">1</div>
                <div class="text-left">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-700 block leading-tight">Step 1</span>
                    <span id="text-step-1" class="text-xs font-extrabold text-[#0C382B]">Personal Info</span>
                </div>
            </div>

            <div id="line-step-1" class="flex-1 h-[2.5px] bg-[#ECE7DD] mx-3 transition-colors duration-300"></div>

            <!-- Step 2 -->
            <div class="flex items-center gap-2.5">
                <div id="circle-step-2" class="step-circle inactive">2</div>
                <div class="text-left">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-stone-400 block leading-tight">Step 2</span>
                    <span id="text-step-2" class="text-xs font-semibold text-stone-500">Choose Option</span>
                </div>
            </div>

            <div id="line-step-2" class="flex-1 h-[2.5px] bg-[#ECE7DD] mx-3 transition-colors duration-300"></div>

            <!-- Step 3 -->
            <div class="flex items-center gap-2.5">
                <div id="circle-step-3" class="step-circle inactive">3</div>
                <div class="text-left">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-stone-400 block leading-tight">Step 3</span>
                    <span id="text-step-3" class="text-xs font-semibold text-stone-500">Payment</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Referral link notice -->
    <?php if ($fromReferralLink && $referral_id !== ''): ?>
    <div class="mb-5 rounded-2xl border border-[#0E7A3B]/30 bg-[#EAF5EC] px-5 py-3.5 text-center text-[#07241B] text-[14px]">
        Referral link detected — Sponsor ID <strong class="text-[#0E7A3B]"><?= e($referral_id) ?></strong>
        <?php if ($sponsor_name !== ''): ?>
            &nbsp;|&nbsp; Sponsor Name: <strong id="sponsor-badge-name" class="text-[#0E7A3B]"><?= e($sponsor_name) ?></strong>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Error notice -->
    <div id="ajax-error-box" class="<?= !empty($error) ? 'block' : 'hidden' ?> mb-5 rounded-2xl border border-red-200 bg-red-50 px-5 py-3.5 text-center text-red-700 text-[14px] font-medium">
        ⚠ &nbsp;<span id="ajax-error-msg"><?= e($error) ?></span>
    </div>

    <form id="signup-wizard-form" action="" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="_form_token" value="<?= e($formToken) ?>">

        <!-- ════════════════════════════════════════════════════════════════
             STEP 1: SPONSOR & PERSONAL DETAILS
        ════════════════════════════════════════════════════════════════ -->
        <div id="panel-step-1" class="step-panel active space-y-6">
            <!-- Sponsor Info -->
            <div class="section-card">
                <div class="flex items-center gap-3 mb-6">
                    <span class="block w-[5px] h-[22px] rounded-full bg-gradient-to-b from-[#E0700C] to-[#0E7A3B]"></span>
                    <h2 class="text-[#0C382B] text-[18px] sm:text-[19px] font-extrabold tracking-tight">Sponsor Information</h2>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 items-start">
                    <div>
                        <label class="block text-[11.5px] font-bold text-[#5F6960] uppercase tracking-wider mb-2">
                            Referral / Sponsor ID <span class="text-[#E0700C] font-bold">*</span>
                        </label>
                        <input type="text" name="referral_id" id="field-referral-id"
                               value="<?= e($referral_id) ?>"
                               <?= $referralReadonly ?>
                               placeholder="e.g. NA7915191"
                               class="field-input uppercase"
                               onblur="checkSponsorName()"
                               oninput="checkSponsorName()">
                    </div>
                    <div>
                        <label class="block text-[11.5px] font-bold text-[#5F6960] uppercase tracking-wider mb-2">
                            Sponsor Verification
                        </label>
                        <div id="sponsor-badge-box" class="h-[44px] rounded-xl border border-[#D5EADB] bg-[#F4FAF6] px-4 flex items-center gap-2">
                            <i data-lucide="shield-check" class="w-4 h-4 text-[#0E7A3B] shrink-0"></i>
                            <span id="sponsor-badge-text" class="text-xs font-bold text-[#0C382B] truncate">
                                <?= $sponsor_name !== '' ? '✓ Verified: ' . e($sponsor_name) : 'Enter Sponsor ID to verify' ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Personal Details -->
            <div class="section-card">
                <div class="flex items-center gap-3 mb-6">
                    <span class="block w-[5px] h-[22px] rounded-full bg-gradient-to-b from-[#E0700C] to-[#0E7A3B]"></span>
                    <h2 class="text-[#0C382B] text-[18px] sm:text-[19px] font-extrabold tracking-tight">Personal Details</h2>
                </div>

                <div class="space-y-6">
                    <div>
                        <label class="block text-[11.5px] font-bold text-[#5F6960] uppercase tracking-wider mb-2">
                            Full Name <span class="text-[#E0700C] font-bold">*</span>
                        </label>
                        <input type="text" name="full_name" id="field-full-name"
                               placeholder="Enter your full name"
                               class="field-input">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-[11.5px] font-bold text-[#5F6960] uppercase tracking-wider mb-2">
                                Email Address <span class="text-[#E0700C] font-bold">*</span>
                            </label>
                            <input type="email" name="email" id="field-email"
                                   placeholder="you@example.com"
                                   class="field-input">
                        </div>
                        <div>
                            <label class="block text-[11.5px] font-bold text-[#5F6960] uppercase tracking-wider mb-2">
                                Mobile No. <span class="text-[#E0700C] font-bold">*</span>
                            </label>
                            <input type="text" name="mobile" id="field-mobile" maxlength="10"
                                   placeholder="10-digit mobile number"
                                   inputmode="numeric"
                                   oninput="this.value=this.value.replace(/[^0-9]/g,'')"
                                   class="field-input">
                        </div>
                    </div>

                    <!-- Password -->
                    <div>
                        <label class="block text-[11.5px] font-bold text-[#5F6960] uppercase tracking-wider mb-2">
                            Password <span class="text-[#E0700C] font-bold">*</span>
                        </label>
                        <div class="relative">
                            <input type="password" name="password" id="field-password"
                                   placeholder="Minimum 6 characters"
                                   class="field-input pr-10">
                            <button type="button" onclick="togglePasswordVisibility()"
                                    class="absolute right-0 bottom-2 text-gray-400 hover:text-[#0E7A3B]">
                                <i data-lucide="eye" id="eye-icon" class="w-5 h-5"></i>
                            </button>
                        </div>
                    </div>

                    <!-- State & City -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-[11.5px] font-bold text-[#5F6960] uppercase tracking-wider mb-2">
                                State <span class="text-[#E0700C] font-bold">*</span>
                            </label>
                            <select name="state" id="field-state" class="field-input bg-transparent">
                                <option value="">Select State</option>
                                <?php foreach ($indianStates as $s): ?>
                                    <option value="<?= e($s) ?>"><?= e($s) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11.5px] font-bold text-[#5F6960] uppercase tracking-wider mb-2">
                                City <span class="text-[#E0700C] font-bold">*</span>
                            </label>
                            <input type="text" name="city" id="field-city"
                                   placeholder="Enter your city"
                                   class="field-input">
                        </div>
                    </div>
                </div>

                <div class="my-6 border-t border-[#ECE7DD]"></div>

                <!-- Terms -->
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox" name="terms" id="field-terms"
                           class="mt-1 w-[18px] h-[18px] rounded border-2 border-gray-300 accent-[#0E7A3B] cursor-pointer">
                    <span class="text-[14px] text-gray-600 leading-relaxed">
                        I confirm that the details entered are correct and I agree to the
                        <a href="#" class="text-[#0E7A3B] font-bold underline hover:text-[#0A5E2D]">Terms and Conditions</a>.
                    </span>
                </label>
            </div>

            <!-- Step 1 Button -->
            <button type="button" onclick="validateAndGoToStep2()"
                    id="btn-to-step-2"
                    class="w-full h-[54px] rounded-2xl bg-gradient-to-r from-[#0C382B] via-[#0E7A3B] to-[#0A5E2D] hover:from-[#07241B] hover:to-[#084A24] text-white text-[16px] md:text-[17px] font-extrabold tracking-wide shadow-[0_10px_25px_-5px_rgba(14,122,59,0.35)] flex items-center justify-center gap-2.5 transition duration-200">
                <span>Continue to Step 2: Choose Joining Option</span>
                <i data-lucide="arrow-right" class="w-5 h-5"></i>
            </button>
        </div>

        <!-- ════════════════════════════════════════════════════════════════
             STEP 2: CHOOSE JOINING OPTION (2 CLEAR CHOICES)
        ════════════════════════════════════════════════════════════════ -->
        <div id="panel-step-2" class="step-panel space-y-6">
            <div class="section-card">
                <div class="flex items-center gap-3 mb-2">
                    <span class="block w-[5px] h-[22px] rounded-full bg-gradient-to-b from-[#E0700C] to-[#0E7A3B]"></span>
                    <h2 class="text-[#0C382B] text-[20px] font-extrabold tracking-tight">Select Your Joining Option</h2>
                </div>
                <p class="text-sm text-[#5F6960] mb-6">
                    Choose how you want to join Electrinc. You can either buy an Electric Vehicle directly or activate with the ₹10,000 joining booking package.
                </p>

                <!-- Hidden inputs that save choice for POST -->
                <input type="hidden" name="joining_type" id="input-joining-type" value="vehicle">
                <input type="hidden" name="selected_vehicle_id" id="input-selected-vehicle-id" value="<?= !empty($vehiclesList) ? $vehiclesList[0]['id'] : 1 ?>">
                <input type="hidden" name="benefit_choice" id="input-benefit-choice" value="product">

                <!-- 2 Option Selector Tabs -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <!-- Option 1 Tab -->
                    <div id="tab-option-1" onclick="selectJoiningType('vehicle')"
                         class="choice-card selected p-5 flex items-start gap-4">
                        <div class="w-12 h-12 rounded-2xl bg-[#EAF5EC] text-[#0E7A3B] flex items-center justify-center shrink-0 border border-[#0E7A3B]/20">
                            <i data-lucide="bike" class="w-6 h-6"></i>
                        </div>
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <h3 class="font-extrabold text-[#0C382B] text-[16px]">Option 1: Direct Buy Vehicle</h3>
                                <span class="bg-gradient-to-r from-[#0C382B] to-[#0E7A3B] text-white text-[10px] font-extrabold uppercase px-2.5 py-0.5 rounded-full shadow-sm">Full Online Payment</span>
                            </div>
                            <p class="text-xs text-[#5F6960] mt-1">
                                Purchase an Electric Scooter, Bike, or Rickshaw directly with full payment. Instant qualification.
                            </p>
                        </div>
                    </div>

                    <!-- Option 2 Tab -->
                    <div id="tab-option-2" onclick="selectJoiningType('activation')"
                         class="choice-card p-5 flex items-start gap-4">
                        <div class="w-12 h-12 rounded-2xl bg-[#FFF3E7] text-[#E0700C] flex items-center justify-center shrink-0 border border-[#E0700C]/20">
                            <i data-lucide="gift" class="w-6 h-6"></i>
                        </div>
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <h3 class="font-extrabold text-[#0C382B] text-[16px]">Option 2: Joining Booking</h3>
                                <span class="bg-gradient-to-r from-[#E0700C] to-[#F0A44F] text-white text-[10px] font-extrabold uppercase px-2.5 py-0.5 rounded-full shadow-sm">₹10,000 Package</span>
                            </div>
                            <p class="text-xs text-[#5F6960] mt-1">
                                Standard business activation with choice of 36-Month Product Voucher or 10-Month Cashback.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- ── VIEW FOR OPTION 1: SMART VEHICLE DROPDOWN & SHOWCASE ── -->
                <div id="view-vehicle-catalog" class="space-y-4">
                    <!-- Dropdown Container -->
                    <div class="bg-gradient-to-r from-[#F4FAF6] to-[#EAF5EC] border border-[#D5EADB] rounded-2xl p-4 md:p-5">
                        <label for="vehicle-select-dropdown" class="block text-xs font-bold text-[#0C382B] uppercase tracking-wider mb-2 flex items-center justify-between">
                            <span class="flex items-center gap-1.5">
                                <i data-lucide="layers" class="w-4 h-4 text-[#0E7A3B]"></i>
                                Select Electric Vehicle Model
                            </span>
                            <span class="text-[11px] font-normal normal-case text-gray-500">Pick model to view details</span>
                        </label>

                        <div class="relative">
                            <select id="vehicle-select-dropdown" onchange="onVehicleDropdownChange(this)"
                                    class="w-full h-[52px] pl-4 pr-11 rounded-xl border-2 border-[#D5EADB] bg-white font-bold text-gray-900 text-[15px] shadow-sm outline-none focus:border-[#0E7A3B] focus:ring-2 focus:ring-[#0E7A3B]/10 transition cursor-pointer appearance-none">
                                <?php foreach ($vehiclesList as $v): ?>
                                    <option value="<?= $v['id'] ?>"
                                            data-name="<?= e($v['model_name']) ?>"
                                            data-price="<?= $v['vehicle_price'] ?>"
                                            data-category="<?= e(ucfirst((string)($v['category'] ?? 'scooter'))) ?>"
                                            data-battery="<?= e($v['battery_spec']) ?>"
                                            data-range="<?= e($v['range_km']) ?>"
                                            data-warranty="<?= e($v['warranty_months']) ?>"
                                            data-img="<?= e($v['image_path']) ?>">
                                        <?= e($v['model_name']) ?> — ₹<?= number_format((float)($v['vehicle_price'] ?? 0), 2) ?> (<?= ucfirst((string)($v['category'] ?? 'scooter')) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3.5 text-gray-500">
                                <i data-lucide="chevron-down" class="w-5 h-5 text-[#0E7A3B]"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Smart Vehicle Showcase Card -->
                    <div id="vehicle-showcase-card"
                         class="rounded-3xl border-2 border-[#0E7A3B]/20 bg-gradient-to-b from-white to-[#F7FAF8] p-5 md:p-6 shadow-[0_8px_25px_rgba(12,56,43,0.06)] transition-all">
                        <div class="flex flex-col md:flex-row gap-6 items-center">
                            
                            <!-- Vehicle Image Box -->
                            <div class="w-full md:w-56 h-44 rounded-2xl bg-[#FBFAF6] border border-[#ECE7DD] flex items-center justify-center p-3 relative overflow-hidden shrink-0 group">
                                <img id="showcase-img"
                                     src="<?= e($vehiclesList[0]['image_path']) ?>"
                                     alt="<?= e($vehiclesList[0]['model_name']) ?>"
                                     class="h-full object-contain drop-shadow transition-transform duration-300 group-hover:scale-105"
                                     onerror="this.src='assets/ev-products/max-vxi.jpeg';">
                                <span id="showcase-cat-badge"
                                      class="absolute top-2.5 left-2.5 text-[10px] font-black uppercase tracking-wider px-2.5 py-1 rounded-full bg-[#0C382B] text-white shadow">
                                    <?= e(ucfirst((string)($vehiclesList[0]['category'] ?? 'scooter'))) ?>
                                </span>
                            </div>

                            <!-- Vehicle Info & Specs -->
                            <div class="flex-1 w-full flex flex-col justify-between">
                                <div>
                                    <div class="flex items-center justify-between gap-2 mb-1">
                                        <h4 id="showcase-title" class="text-xl md:text-2xl font-black text-[#0C382B] tracking-tight">
                                            <?= e($vehiclesList[0]['model_name']) ?>
                                        </h4>
                                        <span class="inline-flex items-center gap-1 text-xs font-bold text-[#0E7A3B] bg-[#EAF5EC] border border-[#0E7A3B]/30 px-3 py-1 rounded-full shrink-0">
                                            <i data-lucide="check" class="w-3.5 h-3.5 text-[#0E7A3B]"></i> Selected
                                        </span>
                                    </div>
                                    <p class="text-xs text-[#5F6960] mb-3.5">Official ELECTRINC Certified EV with Pan-India Warranty</p>

                                    <!-- Specs Pills -->
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 mb-4">
                                        <div class="flex items-center gap-2 px-3 py-2 rounded-xl bg-emerald-50/90 border border-emerald-100">
                                            <i data-lucide="battery-charging" class="w-4 h-4 text-[#0E7A3B] shrink-0"></i>
                                            <div class="min-w-0">
                                                <span class="text-[10px] uppercase font-bold text-gray-400 block leading-tight">Battery</span>
                                                <span id="showcase-battery" class="text-xs font-bold text-gray-800 truncate block">
                                                    <?= e($vehiclesList[0]['battery_spec']) ?>
                                                </span>
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-2 px-3 py-2 rounded-xl bg-amber-50/90 border border-amber-100">
                                            <i data-lucide="gauge" class="w-4 h-4 text-[#E0700C] shrink-0"></i>
                                            <div class="min-w-0">
                                                <span class="text-[10px] uppercase font-bold text-gray-400 block leading-tight">Range</span>
                                                <span id="showcase-range" class="text-xs font-bold text-gray-800 truncate block">
                                                    <?= e($vehiclesList[0]['range_km']) ?>
                                                </span>
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-2 px-3 py-2 rounded-xl bg-teal-50/90 border border-teal-100">
                                            <i data-lucide="shield-check" class="w-4 h-4 text-teal-600 shrink-0"></i>
                                            <div class="min-w-0">
                                                <span class="text-[10px] uppercase font-bold text-gray-400 block leading-tight">Warranty</span>
                                                <span id="showcase-warranty" class="text-xs font-bold text-gray-800 truncate block">
                                                    <?= e($vehiclesList[0]['warranty_months']) ?> Months
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Price Footer -->
                                <div class="pt-3 border-t border-[#ECE7DD] flex items-center justify-between">
                                    <div>
                                        <span class="text-[11px] text-[#5F6960] font-semibold uppercase tracking-wider block">Full Online Price</span>
                                        <span class="text-xs text-[#0E7A3B] font-semibold">Includes 5% Sponsor Bonus + Team Qualification</span>
                                    </div>
                                    <span id="showcase-price" class="text-2xl md:text-3xl font-black text-[#0E7A3B]">
                                        ₹<?= number_format($vehiclesList[0]['vehicle_price'], 2) ?>
                                    </span>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- ── VIEW FOR OPTION 2: 2ND IMAGE LOGIC (VOUCHER OR CASHBACK) ── -->
                <div id="view-activation-package" class="hidden space-y-4">
                    <div class="p-4 rounded-2xl bg-[#EAF5EC] border border-[#D5EADB] text-[#0C382B] text-xs flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <i data-lucide="sparkles" class="w-5 h-5 text-[#0E7A3B] shrink-0"></i>
                            <span>Standard Business Qualifying Amount: <strong>₹10,000.00</strong></span>
                        </div>
                        <span class="font-extrabold text-[#0E7A3B]">Permanent Standing</span>
                    </div>

                    <h4 class="font-bold text-[#0C382B] text-sm uppercase tracking-wider pt-2">
                        Choose Your Benefit Plan (Select 1 of 2):
                    </h4>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Benefit A: Product Purchase Voucher -->
                        <div onclick="selectBenefitChoice('product')"
                             id="card-benefit-product"
                             class="choice-card selected p-5 flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between mb-3">
                                    <span class="px-2.5 py-1 rounded-full bg-[#FFF3E7] text-[#E0700C] text-xs font-bold border border-[#E0700C]/20">
                                        Active Benefit
                                    </span>
                                    <span id="benefit-check-product" class="text-[#E0700C]">
                                        <i data-lucide="check-circle" class="w-5 h-5"></i>
                                    </span>
                                </div>
                                <h5 class="text-base font-bold text-gray-900 mb-1">
                                    Product Purchase Voucher
                                </h5>
                                <span class="text-xs font-semibold text-[#E0700C] block mb-2">36 Months Validity</span>
                                <p class="text-xs text-[#5F6960] leading-relaxed">
                                    Use this official ₹10,000 voucher code to purchase company FMCG / daily products or redeem towards booking an Electric Scooter.
                                </p>
                            </div>
                            <div class="pt-4 mt-4 border-t border-[#ECE7DD] flex items-center justify-between">
                                <span class="text-xs text-gray-400 uppercase font-semibold">Voucher Value</span>
                                <span class="text-lg font-black text-[#E0700C]">₹10,000.00</span>
                            </div>
                        </div>

                        <!-- Benefit B: Monthly Cashback -->
                        <div onclick="selectBenefitChoice('cashback')"
                             id="card-benefit-cashback"
                             class="choice-card p-5 flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between mb-3">
                                    <span class="px-2.5 py-1 rounded-full bg-[#EAF5EC] text-[#0E7A3B] text-xs font-bold border border-[#0E7A3B]/20">
                                        Cashback Reward
                                    </span>
                                    <span id="benefit-check-cashback" class="text-[#0E7A3B] hidden">
                                        <i data-lucide="check-circle" class="w-5 h-5"></i>
                                    </span>
                                </div>
                                <h5 class="text-base font-bold text-gray-900 mb-1">
                                    Monthly Cashback Plan
                                </h5>
                                <span class="text-xs font-semibold text-[#0E7A3B] block mb-2">10 Months Scheduled</span>
                                <p class="text-xs text-[#5F6960] leading-relaxed">
                                    Get ₹1,000 credited directly to your Main Wallet every month for 10 months (Total ₹10,000). Withdrawable to your bank account.
                                </p>
                            </div>
                            <div class="pt-4 mt-4 border-t border-[#ECE7DD] flex items-center justify-between">
                                <span class="text-xs text-gray-400 uppercase font-semibold">Total Cashback</span>
                                <span class="text-lg font-black text-[#0E7A3B]">₹10,000.00</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 2 Navigation -->
            <div class="flex items-center gap-3">
                <button type="button" onclick="goToStep(1)"
                        class="w-1/3 h-[52px] rounded-2xl border-2 border-[#ECE7DD] hover:border-[#D5CFC2] bg-white text-[#1C1C1C] font-bold text-[15px] flex items-center justify-center gap-2 transition">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i>
                    <span>Back</span>
                </button>
                <button type="button" onclick="validateAndGoToStep3()"
                        class="w-2/3 h-[52px] rounded-2xl bg-gradient-to-r from-[#0C382B] via-[#0E7A3B] to-[#0A5E2D] hover:from-[#07241B] hover:to-[#084A24] text-white font-extrabold text-[16px] shadow-[0_10px_25px_-5px_rgba(14,122,59,0.35)] flex items-center justify-center gap-2 transition duration-200">
                    <span>Continue to Step 3: Payment</span>
                    <i data-lucide="arrow-right" class="w-4 h-4"></i>
                </button>
            </div>
        </div>

        <!-- ════════════════════════════════════════════════════════════════
             STEP 3: ONLINE PAYMENT & FINISH
        ════════════════════════════════════════════════════════════════ -->
        <div id="panel-step-3" class="step-panel space-y-6">
            <!-- Selected Plan Summary Card -->
            <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#0c392b] via-[#0f4d3a] to-[#07241b] border border-emerald-500/20 text-white p-6 shadow-xl">
                <div class="absolute -right-10 -bottom-10 w-48 h-48 rounded-full bg-emerald-400/15 blur-2xl pointer-events-none"></div>
                <div class="absolute right-20 top-0 w-36 h-36 rounded-full bg-amber-400/15 blur-xl pointer-events-none"></div>
                <div class="relative z-10 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-[#FFF3E7] text-[#E0700C] border border-[#E0700C]/30 mb-1.5">
                            Selected Joining Plan
                        </span>
                        <h3 id="summary-plan-title" class="text-xl md:text-2xl font-black mt-0.5 text-white">
                            Electric Vehicle: MAX VXI
                        </h3>
                        <p id="summary-plan-sub" class="text-xs text-emerald-100/90 mt-1">
                            Option 1: Direct Buy Vehicle with Full Online Payment
                        </p>
                    </div>
                    <div class="text-left sm:text-right">
                        <span class="text-xs text-emerald-200 font-semibold uppercase tracking-wider block">Total Payable:</span>
                        <span id="summary-plan-amount" class="text-2xl md:text-3xl font-black text-white">
                            ₹66,000.00
                        </span>
                    </div>
                </div>
            </div>

            <!-- Official Bank & UPI Gateway Info -->
            <div class="section-card">
                <div class="flex items-center gap-3 mb-5">
                    <span class="block w-[5px] h-[22px] rounded-full bg-gradient-to-b from-[#E0700C] to-[#0E7A3B]"></span>
                    <h2 class="text-[#0C382B] text-[18px] sm:text-[19px] font-extrabold tracking-tight">Company Payment Details</h2>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <!-- UPI Section -->
                    <div class="p-5 rounded-2xl border border-[#E0700C]/30 bg-[#FFF8F0] flex flex-col justify-between">
                        <div>
                            <div class="flex items-center gap-2 text-[#8F4700] font-bold text-sm mb-2">
                                <i data-lucide="smartphone" class="w-4 h-4 text-[#E0700C]"></i>
                                <span>Scan &amp; Pay via UPI / PhonePe / GPay</span>
                            </div>
                            <p class="text-xs text-[#8F4700] mb-3">
                                Transfer exact amount to official corporate UPI ID:
                            </p>
                            <div class="bg-white border border-[#E0700C]/30 rounded-xl p-3 flex items-center justify-between">
                                <span class="font-mono font-bold text-[#8F4700] text-sm" id="upi-val"><?= e($companyBank['UPI']) ?></span>
                                <button type="button" class="copy-btn !bg-[#E0700C] !text-white hover:!bg-[#B25400]" onclick="copyText('upi-val', this)">Copy UPI</button>
                            </div>
                        </div>
                    </div>

                    <!-- Bank Transfer Section -->
                    <div class="p-5 rounded-2xl border border-[#0E7A3B]/30 bg-[#F4FAF6] text-xs space-y-2">
                        <div class="flex items-center gap-2 text-[#0C382B] font-bold text-sm mb-2">
                            <i data-lucide="landmark" class="w-4 h-4 text-[#0E7A3B]"></i>
                            <span>Bank Transfer (IMPS / NEFT / RTGS)</span>
                        </div>
                        <div><strong class="text-[#0C382B]">A/C Name:</strong> <?= e($companyBank['Account Name']) ?></div>
                        <div><strong class="text-[#0C382B]">Bank:</strong> <?= e($companyBank['Bank']) ?></div>
                        <div><strong class="text-[#0C382B]">A/C No:</strong> <?= e($companyBank['Account No']) ?></div>
                        <div><strong class="text-[#0C382B]">IFSC Code:</strong> <?= e($companyBank['IFSC']) ?></div>
                        <div><strong class="text-[#0C382B]">Branch:</strong> <?= e($companyBank['Branch']) ?></div>
                    </div>
                </div>
            </div>

            <!-- Payment Submission Form -->
            <div class="section-card">
                <div class="flex items-center gap-3 mb-5">
                    <span class="block w-[5px] h-[22px] rounded-full bg-gradient-to-b from-[#E0700C] to-[#0E7A3B]"></span>
                    <h2 class="text-[#0C382B] text-[18px] sm:text-[19px] font-extrabold tracking-tight">Online Payment Verification</h2>
                </div>

                <div class="space-y-5">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-[11.5px] font-bold text-[#5F6960] uppercase tracking-wider mb-2">
                                Payment Mode <span class="text-[#E0700C] font-bold">*</span>
                            </label>
                            <select name="payment_mode" id="field-payment-mode" class="field-input bg-transparent">
                                <option value="UPI">UPI (Google Pay / PhonePe / Paytm)</option>
                                <option value="IMPS">IMPS (Instant Net Banking)</option>
                                <option value="NEFT">NEFT Transfer</option>
                                <option value="RTGS">RTGS Transfer</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11.5px] font-bold text-[#5F6960] uppercase tracking-wider mb-2">
                                Transaction ID / UTR No. <span class="text-[#E0700C] font-bold">*</span>
                            </label>
                            <input type="text" name="transaction_no" id="field-transaction-no"
                                   placeholder="Enter 12-digit UTR or Txn Ref"
                                   class="field-input">
                        </div>
                    </div>

                    <!-- Receipt Upload -->
                    <div>
                        <label class="block text-[11.5px] font-bold text-[#5F6960] uppercase tracking-wider mb-2">
                            Upload Payment Receipt / Screenshot <span class="text-[#E0700C] font-bold">*</span>
                        </label>
                        <div class="border-2 border-dashed border-[#D5CFC2] rounded-2xl p-6 text-center bg-[#FAF8F5] hover:bg-[#F4F1EA] transition relative cursor-pointer"
                             onclick="document.getElementById('receipt-file-input').click()">
                            <input type="file" name="receipt" id="receipt-file-input"
                                   accept=".jpg,.jpeg,.png,.pdf"
                                   class="hidden"
                                   onchange="updateFileName(this)">
                            <div class="flex flex-col items-center justify-center">
                                <div class="w-12 h-12 rounded-2xl bg-[#EAF5EC] text-[#0E7A3B] flex items-center justify-center mb-2.5 border border-[#0E7A3B]/20">
                                    <i data-lucide="upload-cloud" class="w-6 h-6 text-[#0E7A3B]"></i>
                                </div>
                                <p class="text-sm font-bold text-stone-800" id="receipt-label">
                                    Click here to select or drag your payment receipt
                                </p>
                                <p class="text-xs text-stone-400 mt-1">
                                    Supported formats: JPG, PNG, PDF (Max 5MB)
                                </p>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11.5px] font-bold text-[#5F6960] uppercase tracking-wider mb-2">
                            Remarks / Additional Notes <span class="normal-case font-normal text-gray-400">(Optional)</span>
                        </label>
                        <input type="text" name="remark" id="field-remark"
                                placeholder="e.g. Paid via PhonePe from Jayraj"
                                class="field-input">
                    </div>
                </div>
            </div>

            <!-- Step 3 Navigation -->
            <div class="flex items-center gap-3">
                <button type="button" onclick="goToStep(2)"
                        class="w-1/3 h-[54px] rounded-2xl border-2 border-[#ECE7DD] hover:border-[#D5CFC2] bg-white text-[#1C1C1C] font-bold text-[15px] flex items-center justify-center gap-2 transition">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i>
                    <span>Back</span>
                </button>
                <button type="submit" id="btn-submit-signup"
                        class="w-2/3 h-[54px] rounded-2xl bg-gradient-to-r from-[#0C382B] via-[#0E7A3B] to-[#0A5E2D] hover:from-[#07241B] hover:to-[#084A24] text-white font-extrabold text-[16px] shadow-[0_12px_30px_rgba(14,122,59,0.35)] flex items-center justify-center gap-2 transition duration-200">
                    <i data-lucide="check-circle" class="w-5 h-5 text-emerald-200"></i>
                    <span>Submit Payment &amp; Create Account</span>
                </button>
            </div>
        </div>

        <p class="text-center pt-6 text-[14px] text-stone-600">
            Already have an active account?
            <a href="login.php" class="text-[#0E7A3B] font-extrabold underline ml-1 hover:text-[#0A5E2D]">Sign In</a>
        </p>
    </form>
</div>
</div>

<script>
// ── Global Wizard State ───────────────────────────────────────────────────
let currentStep = 1;
let selectedJoiningType = 'vehicle';
let selectedVehicleId = <?= !empty($vehiclesList) ? (int)$vehiclesList[0]['id'] : 1 ?>;
let selectedVehicleName = '<?= !empty($vehiclesList) ? e(addslashes($vehiclesList[0]['model_name'])) : "MAX VXI" ?>';
let selectedVehiclePrice = <?= !empty($vehiclesList) ? (float)$vehiclesList[0]['vehicle_price'] : 66000.00 ?>;
let selectedBenefit = 'product';

function goToStep(step) {
    if (step < 1 || step > 3) return;

    // Toggle panels
    document.querySelectorAll('.step-panel').forEach(p => p.classList.remove('active'));
    document.getElementById('panel-step-' + step).classList.add('active');

    // Update Progress Indicator
    for (let s = 1; s <= 3; s++) {
        const circle = document.getElementById('circle-step-' + s);
        const text = document.getElementById('text-step-' + s);
        const line = document.getElementById('line-step-' + (s - 1));

        circle.classList.remove('active', 'completed', 'inactive');
        if (s === step) {
            circle.classList.add('active');
            circle.innerHTML = s;
            if (text) text.className = 'text-xs font-extrabold text-[#0C382B]';
        } else if (s < step) {
            circle.classList.add('completed');
            circle.innerHTML = '✓';
            if (text) text.className = 'text-xs font-bold text-[#0E7A3B]';
        } else {
            circle.classList.add('inactive');
            circle.innerHTML = s;
            if (text) text.className = 'text-xs font-semibold text-stone-500';
        }

        if (line) {
            line.style.backgroundColor = (s <= step) ? '#0E7A3B' : '#ECE7DD';
        }
    }

    // Scroll smoothly to top of wizard
    window.scrollTo({ top: 80, behavior: 'smooth' });
    currentStep = step;
}

// ── Check Sponsor Name on input/blur ───────────────────────────────────────
async function checkSponsorName() {
    const refId = document.getElementById('field-referral-id').value.trim();
    const badgeText = document.getElementById('sponsor-badge-text');
    if (!refId) {
        if (badgeText) badgeText.textContent = 'Enter Sponsor ID to verify';
        return;
    }

    try {
        const formData = new FormData();
        formData.append('referral_id', refId);
        const res = await fetch('signup.php?ajax_validate=step1', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.ok && data.sponsor_name) {
            if (badgeText) badgeText.textContent = '✓ Verified: ' + data.sponsor_name;
        } else {
            if (badgeText) badgeText.textContent = '⚠ Sponsor ID not found';
        }
    } catch (e) {}
}

// ── Validate Step 1 and proceed to Step 2 ─────────────────────────────────
async function validateAndGoToStep2() {
    clearError();

    const refId = document.getElementById('field-referral-id').value.trim();
    const fullName = document.getElementById('field-full-name').value.trim();
    const email = document.getElementById('field-email').value.trim();
    const mobile = document.getElementById('field-mobile').value.trim();
    const password = document.getElementById('field-password').value.trim();
    const state = document.getElementById('field-state').value.trim();
    const city = document.getElementById('field-city').value.trim();
    const terms = document.getElementById('field-terms').checked;

    if (!refId) return showError('Please enter a Referral / Sponsor ID.');
    if (!fullName) return showError('Please enter your full name.');
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return showError('Please enter a valid email address.');
    if (!mobile || !/^[0-9]{10}$/.test(mobile)) return showError('Mobile number must be exactly 10 digits.');
    if (!password || password.length < 6) return showError('Password must be at least 6 characters.');
    if (!state) return showError('Please select your State.');
    if (!city) return showError('Please enter your City.');
    if (!terms) return showError('Please accept the Terms and Conditions.');

    const btn = document.getElementById('btn-to-step-2');
    btn.disabled = true;
    btn.innerHTML = 'Verifying Details…';

    try {
        const formData = new FormData();
        formData.append('referral_id', refId);
        formData.append('email', email);
        formData.append('mobile', mobile);

        const res = await fetch('signup.php?ajax_validate=step1', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        btn.disabled = false;
        btn.innerHTML = '<span>Continue to Step 2: Choose Joining Option</span> <i data-lucide="arrow-right" class="w-5 h-5"></i>';
        lucide.createIcons();

        if (!data.ok) {
            return showError(data.error || 'Validation failed. Please check inputs.');
        }

        // Sponsor verified: go to step 2
        goToStep(2);
    } catch (err) {
        btn.disabled = false;
        btn.innerHTML = '<span>Continue to Step 2: Choose Joining Option</span> <i data-lucide="arrow-right" class="w-5 h-5"></i>';
        lucide.createIcons();
        goToStep(2);
    }
}

// ── Select Joining Type in Step 2 ─────────────────────────────────────────
function selectJoiningType(type) {
    selectedJoiningType = type;
    document.getElementById('input-joining-type').value = type;

    const tab1 = document.getElementById('tab-option-1');
    const tab2 = document.getElementById('tab-option-2');
    const vView = document.getElementById('view-vehicle-catalog');
    const aView = document.getElementById('view-activation-package');

    if (type === 'vehicle') {
        tab1.classList.add('selected');
        tab2.classList.remove('selected');
        vView.classList.remove('hidden');
        aView.classList.add('hidden');
    } else {
        tab2.classList.add('selected');
        tab1.classList.remove('selected');
        aView.classList.remove('hidden');
        vView.classList.add('hidden');
    }
    updateSummary();
}

// ── Vehicle Dropdown Selection in Option 1 ───────────────────────────────
function onVehicleDropdownChange(sel) {
    const opt = sel.options[sel.selectedIndex];
    if (!opt) return;

    const id = parseInt(opt.value, 10);
    const name = opt.getAttribute('data-name');
    const price = parseFloat(opt.getAttribute('data-price'));
    const category = opt.getAttribute('data-category');
    const battery = opt.getAttribute('data-battery');
    const range = opt.getAttribute('data-range');
    const warranty = opt.getAttribute('data-warranty');
    const img = opt.getAttribute('data-img');

    selectedVehicleId = id;
    selectedVehicleName = name;
    selectedVehiclePrice = price;
    document.getElementById('input-selected-vehicle-id').value = id;

    // Update Showcase card elements
    const titleEl = document.getElementById('showcase-title');
    const badgeEl = document.getElementById('showcase-cat-badge');
    const batEl = document.getElementById('showcase-battery');
    const rngEl = document.getElementById('showcase-range');
    const warEl = document.getElementById('showcase-warranty');
    const prcEl = document.getElementById('showcase-price');
    const imgEl = document.getElementById('showcase-img');

    if (titleEl) titleEl.textContent = name;
    if (badgeEl) badgeEl.textContent = category;
    if (batEl) batEl.textContent = battery;
    if (rngEl) rngEl.textContent = range;
    if (warEl) warEl.textContent = warranty + ' Months';
    if (prcEl) prcEl.textContent = '₹' + price.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    if (imgEl) {
        imgEl.src = img;
        imgEl.alt = name;
    }

    updateSummary();
}

// ── Select Benefit in Option 2 ────────────────────────────────────────────
function selectBenefitChoice(choice) {
    selectedBenefit = choice;
    document.getElementById('input-benefit-choice').value = choice;

    const prodCard = document.getElementById('card-benefit-product');
    const cashCard = document.getElementById('card-benefit-cashback');
    const prodChk = document.getElementById('benefit-check-product');
    const cashChk = document.getElementById('benefit-check-cashback');

    if (choice === 'product') {
        prodCard.classList.add('selected');
        cashCard.classList.remove('selected');
        prodChk.classList.remove('hidden');
        cashChk.classList.add('hidden');
    } else {
        cashCard.classList.add('selected');
        prodCard.classList.remove('selected');
        cashChk.classList.remove('hidden');
        prodChk.classList.add('hidden');
    }
    updateSummary();
}

// ── Update Step 3 Summary ─────────────────────────────────────────────────
function updateSummary() {
    const titleEl = document.getElementById('summary-plan-title');
    const subEl = document.getElementById('summary-plan-sub');
    const amtEl = document.getElementById('summary-plan-amount');

    if (selectedJoiningType === 'vehicle') {
        titleEl.textContent = 'Electric Vehicle: ' + selectedVehicleName;
        subEl.textContent = 'Option 1: Direct Buy Vehicle with Full Online Payment';
        amtEl.textContent = '₹' + selectedVehiclePrice.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    } else {
        if (selectedBenefit === 'cashback') {
            titleEl.textContent = 'Joining Booking: Monthly Cashback Plan (10 Mo)';
            subEl.textContent = 'Option 2: ₹1,000 / month scheduled cashback to Main Wallet';
        } else {
            titleEl.textContent = 'Joining Booking: Product Purchase Voucher (36 Mo)';
            subEl.textContent = 'Option 2: ₹10,000 product voucher code for products / scooter booking';
        }
        amtEl.textContent = '₹10,000.00';
    }
}

// ── Validate Step 2 and proceed to Step 3 ─────────────────────────────────
function validateAndGoToStep3() {
    clearError();
    updateSummary();
    goToStep(3);
}

// ── Upload Receipt Filename Preview ───────────────────────────────────────
function updateFileName(input) {
    const label = document.getElementById('receipt-label');
    if (input.files && input.files[0]) {
        label.innerHTML = '✓ Selected: <strong class="text-emerald-700">' + input.files[0].name + '</strong>';
    } else {
        label.textContent = 'Click here to select or drag your payment receipt';
    }
}

// ── Step 3 Form Submit Check ──────────────────────────────────────────────
document.getElementById('signup-wizard-form').addEventListener('submit', function(e) {
    clearError();
    const txn = document.getElementById('field-transaction-no').value.trim();
    const file = document.getElementById('receipt-file-input').files[0];

    if (!txn) {
        e.preventDefault();
        showError('Please enter the online Transaction / UTR number in Step 3.');
        return;
    }
    if (!file) {
        e.preventDefault();
        showError('Please upload a screenshot or photo of your online payment receipt.');
        return;
    }

    const btn = document.getElementById('btn-submit-signup');
    btn.disabled = true;
    btn.innerHTML = '<i data-lucide="loader-2" class="w-5 h-5 animate-spin"></i> <span>Creating Your Account…</span>';
    lucide.createIcons();
});

// ── Helpers ───────────────────────────────────────────────────────────────
function showError(msg) {
    const box = document.getElementById('ajax-error-box');
    const txt = document.getElementById('ajax-error-msg');
    txt.textContent = msg;
    box.classList.remove('hidden');
    box.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function clearError() {
    const box = document.getElementById('ajax-error-box');
    box.classList.add('hidden');
}

function copyText(id, btn) {
    const el = document.getElementById(id);
    if (!el) return;
    navigator.clipboard.writeText(el.textContent.trim()).then(() => {
        const orig = btn.textContent;
        btn.textContent = '✓ Copied';
        btn.style.cssText = 'background:#d4edda;color:#155724;';
        setTimeout(() => { btn.textContent = orig; btn.style.cssText = ''; }, 2000);
    });
}

function togglePasswordVisibility() {
    const f = document.getElementById('field-password');
    const isShowing = f.type === 'text';
    f.type = isShowing ? 'password' : 'text';
}

document.addEventListener('DOMContentLoaded', () => {
    lucide.createIcons();
    updateSummary();
});
</script>
</body>
</html>
