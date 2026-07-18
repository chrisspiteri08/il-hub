<?php
// 1. Include the raw database connection
require_once 'db_config.php';

// 2. Fetch all settings from the database
try {
    $settings_list = $pdo->query("SELECT setting_name, setting_value FROM settings")->fetchAll(PDO::FETCH_ASSOC);

    // 3. Create a clean $settings array
    // This makes it easy to access (e.g., $settings['youtube_api_key'])
    $settings = [];
    foreach ($settings_list as $item) {
        $settings[$item['setting_name']] = $item['setting_value'];
    }

} catch (PDOException $e) {
    die("CRITICAL ERROR: Could not load site settings from database. " . $e->getMessage());
}

// 4. (Optional) Check for missing keys
if (!isset($settings['login_password']) || !isset($settings['youtube_api_key'])) {
    // This check prevents the site from crashing if the DB is empty
    // On a live site, you might want to handle this more gracefully
    die("CRITICAL ERROR: Essential settings (login_password, youtube_api_key) are missing from the database.");
}
?>