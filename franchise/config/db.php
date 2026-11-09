<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$serverName = strtolower((string)($_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'] ?? ''));
$isLocalEnvironment = (
    $serverName === 'localhost' ||
    $serverName === '127.0.0.1' ||
    $serverName === '::1' ||
    str_ends_with($serverName, '.local') ||
    str_ends_with($serverName, '.test') ||
    PHP_SAPI === 'cli'
);

if ($isLocalEnvironment) {
    $host = getenv('DB_HOST') ?: 'localhost';
    $username = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASS') ?: '';
    $database = getenv('DB_NAME');
    $databases = $database ? [$database] : ['electrinc', 'ind_new'];
} else {
    $host = getenv('DB_HOST') ?: 'localhost';
    $username = getenv('DB_USER') ?: '';
    $password = getenv('DB_PASS');
    $database = getenv('DB_NAME') ?: '';
    if (!$username || !$password || !$database) {
        http_response_code(500);
        exit('Database credentials are not configured. Set DB_USER, DB_PASS and DB_NAME.');
    }
    $databases = [$database];
}

$conn = null;
foreach ($databases as $db) {
    try {
        $conn = new mysqli($host, $username, $password, $db);
        $conn->set_charset('utf8mb4');
        break;
    } catch (mysqli_sql_exception $e) {
        continue;
    }
}

if (!$conn) {
    die('Database connection failed: No valid database found.');
}
