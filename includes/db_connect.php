<?php
/**
 * ELECTRINC — Centralized Resilient Database Connector
 * 
 * Capabilities:
 * - Smart Local vs Live environment detection
 * - Multi-port connectivity: Auto-probes port 3307 (XAMPP configured) and port 3306 (standard MySQL)
 * - Auto-creates the target database ('electrinc') on local if it does not exist yet
 * - Graceful diagnostic screen with actionable XAMPP/terminal steps if MySQL is stopped
 * - Non-fatal support for landing page via DB_OPTIONAL constant
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Suppress uncaught mysqli_sql_exception fatal halts
mysqli_report(MYSQLI_REPORT_OFF);

$serverName = strtolower((string)($_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'] ?? ''));
$isLocal = (
    in_array($serverName, ['localhost', '127.0.0.1', '::1'], true) ||
    strpos($serverName, 'localhost') !== false ||
    strpos($serverName, '127.0.0.1') !== false ||
    strpos($serverName, 'ngrok') !== false ||
    str_ends_with($serverName, '.local') ||
    str_ends_with($serverName, '.test') ||
    PHP_SAPI === 'cli'
);

$host = getenv('DB_HOST') ?: 'localhost';
$envUsername = getenv('DB_USER');
$envPassword = getenv('DB_PASS');
$envDatabase = getenv('DB_NAME');

if ($isLocal) {
    $username  = $envUsername !== false && $envUsername !== '' ? $envUsername : 'root';
    $password  = $envPassword !== false ? $envPassword : '';
    $databases = $envDatabase !== false && $envDatabase !== '' ? [$envDatabase] : ["electrinc", "ind_new"];
} else {
    if (!$envUsername || !$envPassword || !$envDatabase) {
        http_response_code(500);
        exit('Database credentials are not configured. Set DB_USER, DB_PASS and DB_NAME.');
    }

    $username  = $envUsername;
    $password  = $envPassword;
    $databases = [$envDatabase];
}

// Ports to test: 3307 (XAMPP custom) and 3306 (default MySQL)
$ports = [3307, 3306];
if (defined('DB_PORT') && (int)DB_PORT > 0) {
    array_unshift($ports, (int)DB_PORT);
}

$conn          = null;
$connectedPort = null;
$connectedDb   = null;

// 1. Attempt connection to target databases across ports
foreach ($databases as $db) {
    foreach ($ports as $port) {
        try {
            $testConn = @new mysqli($host, $username, $password, $db, $port);
            if ($testConn && !$testConn->connect_error) {
                $conn          = $testConn;
                $connectedPort = $port;
                $connectedDb   = $db;
                $conn->set_charset("utf8mb4");
                break 2;
            }
        } catch (\Throwable $e) {
            continue;
        }
    }
}

// 2. If local and database does not exist yet, connect to MySQL server root and auto-create target database
if (!$conn && $isLocal) {
    foreach ($ports as $port) {
        try {
            $rootConn = @new mysqli($host, $username, $password, "", $port);
            if ($rootConn && !$rootConn->connect_error) {
                $targetDb = $databases[0] ?? 'electrinc';
                $rootConn->query("CREATE DATABASE IF NOT EXISTS `{$targetDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $rootConn->close();

                $newConn = @new mysqli($host, $username, $password, $targetDb, $port);
                if ($newConn && !$newConn->connect_error) {
                    $conn          = $newConn;
                    $connectedPort = $port;
                    $connectedDb   = $targetDb;
                    $conn->set_charset("utf8mb4");
                    break;
                }
            }
        } catch (\Throwable $e) {
            continue;
        }
    }
}

// 3. Handle connection failure
if (!$conn) {
    if (!defined('DB_OPTIONAL') || !DB_OPTIONAL) {
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, "[FATAL] Database connection failed: MySQL server is not running on port 3307 or 3306.\n");
            exit(1);
        }

        http_response_code(503);
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Database Offline - ELECTRINC</title>
            <script src="https://cdn.tailwindcss.com"></script>
        </head>
        <body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-4 antialiased font-sans">
            <div class="max-w-md w-full bg-slate-900 border border-amber-500/30 rounded-2xl p-6 sm:p-8 shadow-2xl">
                <div class="w-12 h-12 bg-amber-500/10 border border-amber-500/30 rounded-xl flex items-center justify-center text-amber-400 mb-5 text-2xl font-bold">
                    ⚠️
                </div>
                <h1 class="text-xl font-bold text-white mb-2 tracking-tight">Database Not Connected</h1>
                <p class="text-slate-300 text-sm mb-4 leading-relaxed">
                    The application could not connect to MySQL server on <code class="text-amber-300 font-mono">localhost</code> (checked ports 3307 &amp; 3306).
                </p>
                <div class="bg-slate-950/80 border border-slate-800 rounded-xl p-4 mb-5 text-xs text-slate-300 space-y-2">
                    <div class="font-semibold text-white">How to start MySQL:</div>
                    <div class="flex items-start gap-2">
                        <span class="text-amber-400 font-bold">1.</span>
                        <span>Open <strong>XAMPP Control Panel</strong> and click <strong>Start</strong> next to MySQL.</span>
                    </div>
                    <div class="flex items-start gap-2">
                        <span class="text-amber-400 font-bold">2.</span>
                        <span>Or in PowerShell run: <code class="bg-slate-800 text-amber-200 px-1.5 py-0.5 rounded font-mono">C:\xampp82\mysql_start.bat</code></span>
                    </div>
                </div>
                <button onclick="window.location.reload()" class="w-full bg-amber-500 hover:bg-amber-400 text-slate-950 font-semibold py-2.5 px-4 rounded-xl shadow-lg shadow-amber-500/20 transition flex items-center justify-center gap-2 cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Retry Connection
                </button>
            </div>
        </body>
        </html>
        <?php
        exit();
    }
}
