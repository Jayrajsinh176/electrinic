<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect to member dashboard
$previewParam = (isset($_GET['preview']) && $_GET['preview'] === '1') ? '?preview=1' : '';
header('Location: dashboard.php' . $previewParam);
exit;

