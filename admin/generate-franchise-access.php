<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';

// Check DB connection
if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.");
}

$conn->set_charset("utf8mb4");

// Ensure admin_franchise_access_tokens table exists
$conn->query("
    CREATE TABLE IF NOT EXISTS `admin_franchise_access_tokens` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `admin_session_id` VARCHAR(100) NULL,
        `franchise_code` VARCHAR(50) NOT NULL,
        `token` VARCHAR(100) NOT NULL UNIQUE,
        `expires_at` DATETIME NOT NULL,
        `is_used` TINYINT(1) NOT NULL DEFAULT 0,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        INDEX (`token`),
        INDEX (`franchise_code`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
");

// Get franchise code/ID
$franchiseCode = trim($_GET['franchise_code'] ?? ($_GET['franchies_id'] ?? ''));

if ($franchiseCode === '') {
    die("Franchise ID is missing.");
}

// Verify franchise exists in franchies_holder
$stmt = $conn->prepare("
    SELECT id, franchies_id, franchise_code, name, status 
    FROM franchies_holder 
    WHERE franchise_code = ? OR franchies_id = ? 
    LIMIT 1
");

if (!$stmt) {
    die("Query prepare failed: " . $conn->error);
}

$stmt->bind_param("ss", $franchiseCode, $franchiseCode);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {
    $stmt->close();
    die("Franchise not found with Code: " . htmlspecialchars($franchiseCode));
}

$franchise = $result->fetch_assoc();
$stmt->close();

$actualCode = !empty($franchise['franchise_code']) ? $franchise['franchise_code'] : $franchise['franchies_id'];

// Generate secure random access token
$token = bin2hex(random_bytes(32));
$expiresAt = date("Y-m-d H:i:s", time() + 3600); // 1 hour expiry
$adminSessionId = session_id();

// Insert token into database
$stmt = $conn->prepare("
    INSERT INTO admin_franchise_access_tokens
    (admin_session_id, franchise_code, token, expires_at, is_used)
    VALUES (?, ?, ?, ?, 0)
");

if (!$stmt) {
    die("Token insert query failed: " . $conn->error);
}

$stmt->bind_param("ssss", $adminSessionId, $actualCode, $token, $expiresAt);

if (!$stmt->execute()) {
    die("Token generation failed: " . $stmt->error);
}

$stmt->close();

// Redirect to Franchise Portal impersonation receiver
$redirectUrl = "../franchise/admin-franchise-view.php?token=" . urlencode($token);
header("Location: " . $redirectUrl);
exit();

