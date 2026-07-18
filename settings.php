<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}
require_once 'config_loader.php';

$message = '';

if (isset($_POST['update_settings'])) {
    try {
        $pdo->beginTransaction();
        
        // Prepare statements for both checking, updating, and inserting
        $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE setting_name = ?");
        $update_stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_name = ?");
        $insert_stmt = $pdo->prepare("INSERT INTO settings (setting_name, setting_value) VALUES (?, ?)");

        foreach ($_POST['settings'] as $name => $value) {
            // Skip password if empty (means don't change)
            if ($name == 'login_password' && empty($value)) continue;

            // 1. Check if this setting exists in the DB
            $check_stmt->execute([$name]);
            $exists = $check_stmt->fetchColumn();

            if ($exists) {
                // 2a. Update existing
                $update_stmt->execute([$value, $name]);
            } else {
                // 2b. Insert new setting (Fixes the "doesn't come up" issue)
                $insert_stmt->execute([$name, $value]);
            }
        }
        $pdo->commit();
        $message = '<div class="bg-green-600 p-4 rounded-lg text-white">Settings updated successfully!</div>';
        
        // Refresh settings list immediately so the form shows the new values
        $settings_list = $pdo->query("SELECT setting_name, setting_value FROM settings")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($settings_list as $item) $settings[$item['setting_name']] = $item['setting_value'];

    } catch (PDOException $e) {
        $pdo->rollBack();
        $message = '<div class="bg-red-600 p-4 rounded-lg text-white">Error updating settings: ' . $e->getMessage() . '</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Site Settings - il-hub.com</title>
    <!-- --- FIXED: USING UNPKG CDN --- -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; background-color: #1f2937; color: white; }</style>
</head>
<body class="bg-gray-800 text-gray-200 antialiased p-8">

    <div class="max-w-2xl mx-auto">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold text-white">Site Settings</h1>
            <a href="admin.php" class="text-sm text-orange-400 hover:text-orange-300">&larr; Back to Admin</a>
        </div>

        <?php if ($message) echo '<div class="mb-6">' . $message . '</div>'; ?>

        <form action="settings.php" method="POST" class="bg-gray-700 p-6 rounded-lg shadow-lg space-y-6">
            
            <div>
                <label for="login_password" class="block text-sm font-medium text-gray-300">Admin Password</label>
                <input type="password" name="settings[login_password]" id="login_password" class="mt-1 block w-full bg-gray-600 border-gray-500 rounded-md shadow-sm text-white focus:border-orange-400 focus:ring focus:ring-orange-300 focus:ring-opacity-50" placeholder="Leave blank to keep current password">
                <p class="text-xs text-gray-400 mt-1">Change your admin login password here.</p>
            </div>

            <hr class="border-gray-600">

            <div>
                <label for="youtube_api_key" class="block text-sm font-medium text-gray-300">YouTube API Key</label>
                <input type="text" name="settings[youtube_api_key]" id="youtube_api_key" class="mt-1 block w-full bg-gray-600 border-gray-500 rounded-md shadow-sm text-white focus:border-orange-400 focus:ring focus:ring-orange-300 focus:ring-opacity-50" value="<?php echo htmlspecialchars($settings['youtube_api_key'] ?? ''); ?>">
            </div>

            <hr class="border-gray-600">

            <div>
                <label for="fb_page_id" class="block text-sm font-medium text-gray-300">Facebook Page ID</label>
                <input type="text" name="settings[fb_page_id]" id="fb_page_id" class="mt-1 block w-full bg-gray-600 border-gray-500 rounded-md shadow-sm text-white focus:border-orange-400 focus:ring focus:ring-orange-300 focus:ring-opacity-50" value="<?php echo htmlspecialchars($settings['fb_page_id'] ?? ''); ?>">
            </div>

            <div>
                <label for="fb_page_token" class="block text-sm font-medium text-gray-300">Facebook Page Access Token</label>
                <textarea name="settings[fb_page_token]" id="fb_page_token" rows="5" class="mt-1 block w-full bg-gray-600 border-gray-500 rounded-md shadow-sm text-white focus:border-orange-400 focus:ring focus:ring-orange-300 focus:ring-opacity-50"><?php echo htmlspecialchars($settings['fb_page_token'] ?? ''); ?></textarea>
            </div>

            <hr class="border-gray-600">
            <div>
                <label for="fb_post_template_single" class="block text-sm font-medium text-gray-300">Facebook Post (Single Episode)</label>
                <textarea name="settings[fb_post_template_single]" id="fb_post_template_single" rows="4" class="mt-1 block w-full bg-gray-600 border-gray-500 rounded-md shadow-sm text-white focus:border-orange-400 focus:ring focus:ring-orange-300 focus:ring-opacity-50"><?php echo htmlspecialchars($settings['fb_post_template_single'] ?? "🔥 EPISODJU ĠDID: {title}\n👉 Ara l-episodju sħiħ hawn: {series_url}\n\nAktar minn {series} u programmi oħra fuq il-hub.com - Sib xi ħaġa tgħodd għalik. 🇲🇹"); ?></textarea>
                <p class="text-xs text-gray-400 mt-1">Use {title}, {series}, {url} (video URL), {series_url} (link to the series page).</p>
            </div>
            <div>
                <label for="fb_post_template_multi" class="block text-sm font-medium text-gray-300">Facebook Post (Multiple Episodes)</label>
                <textarea name="settings[fb_post_template_multi]" id="fb_post_template_multi" rows="3" class="mt-1 block w-full bg-gray-600 border-gray-500 rounded-md shadow-sm text-white focus:border-orange-400 focus:ring focus:ring-orange-300 focus:ring-opacity-50"><?php echo htmlspecialchars($settings['fb_post_template_multi'] ?? "{count} episodji ġodda miżjuda!\nAra l-lista sħiħa fuq: https://il-hub.com\n--- Lista T'Episodji ---\n"); ?></textarea>
                 <p class="text-xs text-gray-400 mt-1">Use {count}. The script will automatically add the episode list and link.</p>
            </div>

            <hr class="border-gray-600">

            <div>
                <label for="cron_secret_key" class="block text-sm font-medium text-gray-300">Cron Job Secret Key</label>
                <input type="text" name="settings[cron_secret_key]" id="cron_secret_key" class="mt-1 block w-full bg-gray-600 border-gray-500 rounded-md shadow-sm text-white focus:border-orange-400 focus:ring focus:ring-orange-300 focus:ring-opacity-50" value="<?php echo htmlspecialchars($settings['cron_secret_key'] ?? ''); ?>">
            </div>

            <hr class="border-gray-600">

            <!-- NEW: Google Analytics -->
            <div>
                <label for="google_analytics_id" class="block text-sm font-medium text-gray-300">Google Analytics ID (G-XXXXXXXXXX)</label>
                <input type="text" name="settings[google_analytics_id]" id="google_analytics_id" class="mt-1 block w-full bg-gray-600 border-gray-500 rounded-md shadow-sm text-white focus:border-orange-400 focus:ring focus:ring-orange-300 focus:ring-opacity-50" placeholder="e.g., G-A1B2C3D4E5" value="<?php echo htmlspecialchars($settings['google_analytics_id'] ?? ''); ?>">
                <p class="text-xs text-gray-400 mt-1">Leave blank to disable analytics.</p>
            </div>

            <button type="submit" name="update_settings" class="w-full bg-blue-500 hover:bg-blue-600 text-white font-medium py-2 px-4 rounded-lg shadow-md transition-colors">
                Save Settings
            </button>
        </form>
    </div>

</body>
</html>