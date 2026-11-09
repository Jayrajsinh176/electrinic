<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.");
}

$conn->set_charset("utf8mb4");

$token = trim($_GET['token'] ?? '');

if ($token === '') {
    die("Invalid access token.");
}

// Check token in database
$stmt = $conn->prepare("
    SELECT id, franchise_code, expires_at, is_used
    FROM admin_franchise_access_tokens
    WHERE token = ?
    LIMIT 1
");

if (!$stmt) {
    die("Database query error: " . $conn->error);
}

$stmt->bind_param("s", $token);
$stmt->execute();
$res = $stmt->get_result();
$tokenRow = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$tokenRow) {
    die("Access token not found or invalid.");
}

if ((int)$tokenRow['is_used'] === 1) {
    die("This access link has already been used. Please click 'Login / View' again from Admin Panel.");
}

if (strtotime($tokenRow['expires_at']) < time()) {
    die("This access link has expired. Please click 'Login / View' again from Admin Panel.");
}

// Mark token as used to prevent replay
$stmt = $conn->prepare("UPDATE admin_franchise_access_tokens SET is_used = 1 WHERE id = ?");
$tokenId = (int)$tokenRow['id'];
$stmt->bind_param("i", $tokenId);
$stmt->execute();
$stmt->close();

$franchiseCode = trim($tokenRow['franchise_code']);

// Fetch franchise details
$stmt = $conn->prepare("
    SELECT * 
    FROM franchies_holder 
    WHERE franchise_code = ? OR franchies_id = ? 
    LIMIT 1
");

if (!$stmt) {
    die("Franchise lookup error: " . $conn->error);
}

$stmt->bind_param("ss", $franchiseCode, $franchiseCode);
$stmt->execute();
$res = $stmt->get_result();
$franchise = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$franchise) {
    die("Franchise record not found for code: " . htmlspecialchars($franchiseCode));
}

// Regenerate session id safely
session_regenerate_id(true);

$code = !empty($franchise['franchise_code']) ? $franchise['franchise_code'] : $franchise['franchies_id'];
$name = !empty($franchise['name']) ? $franchise['name'] : (!empty($franchise['full_name']) ? $franchise['full_name'] : 'Franchise');

// Set complete franchise portal session
$_SESSION['franchise_id']       = (int)$franchise['id'];
$_SESSION['franchise_type']     = 'franchise';
$_SESSION['franchise_table']    = 'franchies_holder';
$_SESSION['franchise_login_id'] = $code;
$_SESSION['franchise_code']     = $code;
$_SESSION['franchise_name']     = $name;
$_SESSION['franchise_email']    = $franchise['email'] ?? '';
$_SESSION['franchise_mobile']   = $franchise['mobile'] ?? '';
$_SESSION['franchise_state']    = $franchise['state'] ?? '';
$_SESSION['franchise_city']     = $franchise['city'] ?? '';

// Impersonation flags
$_SESSION['admin_view_mode']    = true;
$_SESSION['admin_preview']      = true;
$_SESSION['admin_preview_from'] = '../admin/franchise-list.php';

setcookie('franchise_preview_mode', '1', time() + 3600, '/');

// Redirect to dashboard
header("Location: dashboard.php");
exit();

