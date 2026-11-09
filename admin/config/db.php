<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Detect environment (LOCAL + NGROK)
$serverName = $_SERVER['SERVER_NAME'] ?? '';
$isLocal = (
    in_array($serverName, ['localhost', '127.0.0.1']) ||
    strpos($serverName, 'ngrok') !== false ||
    PHP_SAPI === 'cli'
);

// Local server (XAMPP + NGROK)
if ($isLocal) {
    $host = "localhost";
    $username = getenv('DB_USER') ?: "root";
    $password = getenv('DB_PASS') ?: "";
    $database = getenv('DB_NAME');
    $databases = $database ? [$database] : ["electrinc", "ind_new"];
} else {
    $host = getenv('DB_HOST') ?: "localhost";
    $username = getenv('DB_USER') ?: '';
    $password = getenv('DB_PASS');
    $database = getenv('DB_NAME') ?: '';
    if (!$username || !$password || !$database) {
        http_response_code(500);
        exit('Database credentials are not configured. Set DB_USER, DB_PASS and DB_NAME.');
    }
    $databases = [$database];
}

// Try connecting to available DB
$conn = null;

foreach ($databases as $db) {
    try {
        $conn = new mysqli($host, $username, $password, $db);
        $conn->set_charset("utf8mb4");
        break;
    } catch (mysqli_sql_exception $e) {
        continue;
    }
}

// If no DB connected
if (!$conn) {
    die("Database connection failed: No valid database found.");
}
?>