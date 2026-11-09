<?php
require_once __DIR__ . '/../member/config/db.php';

echo "=== Applying Step 5: Monthly Activity & KM Verification Schema Migration ===\n";

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.\n");
}

// 1. Add km_run if missing
$chk = $conn->query("SHOW COLUMNS FROM `ind_video_verifications` LIKE 'km_run'");
if ($chk && $chk->num_rows === 0) {
    $conn->query("ALTER TABLE `ind_video_verifications` ADD COLUMN `km_run` INT NOT NULL DEFAULT 0 AFTER `month_number`");
    echo "[OK] Added column km_run\n";
}

// 2. Add odometer_reading if missing
$chk = $conn->query("SHOW COLUMNS FROM `ind_video_verifications` LIKE 'odometer_reading'");
if ($chk && $chk->num_rows === 0) {
    $conn->query("ALTER TABLE `ind_video_verifications` ADD COLUMN `odometer_reading` INT NOT NULL DEFAULT 0 AFTER `km_run`");
    echo "[OK] Added column odometer_reading\n";
}

// 3. Add odometer_photo_path if missing
$chk = $conn->query("SHOW COLUMNS FROM `ind_video_verifications` LIKE 'odometer_photo_path'");
if ($chk && $chk->num_rows === 0) {
    $conn->query("ALTER TABLE `ind_video_verifications` ADD COLUMN `odometer_photo_path` VARCHAR(255) NULL AFTER `video_file_path`");
    echo "[OK] Added column odometer_photo_path\n";
}

// 4. Add feedback_notes if missing
$chk = $conn->query("SHOW COLUMNS FROM `ind_video_verifications` LIKE 'feedback_notes'");
if ($chk && $chk->num_rows === 0) {
    $conn->query("ALTER TABLE `ind_video_verifications` ADD COLUMN `feedback_notes` TEXT NULL AFTER `social_story_screenshot_path`");
    echo "[OK] Added column feedback_notes\n";
}

echo "=== Step 5 Schema Migration Complete ===\n";

