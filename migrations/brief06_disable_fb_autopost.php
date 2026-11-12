<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/db_config.php';

$stmt = $pdo->prepare(
    "INSERT INTO settings (setting_name, setting_value) VALUES ('fb_autopost_enabled', '0')
     ON DUPLICATE KEY UPDATE setting_value = '0'"
);
$stmt->execute();
echo "fb_autopost_enabled=0\n";
