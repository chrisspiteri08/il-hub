<?php
// FORCE SHOW ERRORS
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>1. Starting Database Fix...</h1>";

// Try to load config
if (!file_exists('config_loader.php')) {
    die("<h2 style='color:red'>Error: config_loader.php not found in this folder!</h2>");
}

require_once 'config_loader.php';
echo "<p>✅ Database connected successfully.</p>";

try {
    // 1. Check if column exists manually
    echo "<p>Checking table structure...</p>";
    $stmt = $pdo->query("SHOW COLUMNS FROM series LIKE 'slug'");
    $exists = $stmt->fetch();

    if ($exists) {
        echo "<p style='color:blue'>ℹ️ Column 'slug' already exists. Moving to update...</p>";
    } else {
        echo "<p>⚠️ Column 'slug' is MISSING. Attempting to add it now...</p>";
        $pdo->exec("ALTER TABLE series ADD COLUMN slug VARCHAR(255)");
        echo "<p style='color:green; font-weight:bold; font-size:20px;'>✅ SUCCESS: 'slug' column added!</p>";
    }

    // 2. Populate Slugs
    echo "<hr><h3>2. Generating Slugs for Series...</h3>";
    $series_list = $pdo->query("SELECT id, title, slug FROM series")->fetchAll();
    
    $update_stmt = $pdo->prepare("UPDATE series SET slug = ? WHERE id = ?");
    
    foreach ($series_list as $s) {
        // Only update if slug is empty
        if (empty($s['slug'])) {
            $slug = strtolower($s['title']);
            $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug); // Remove special chars
            $slug = preg_replace('/[\s-]+/', '-', $slug); // Replace spaces with dashes
            $slug = trim($slug, '-');
            
            $update_stmt->execute([$slug, $s['id']]);
            echo "<li>Updated: <strong>{$s['title']}</strong> -> <code>$slug</code></li>";
        } else {
            echo "<li>Skipped: {$s['title']} (Already has slug: {$s['slug']})</li>";
        }
    }
    
    echo "<hr><h1>🎉 DONE! You can now verify the homepage.</h1>";

} catch (PDOException $e) {
    echo "<h2 style='color:red'>❌ DATABASE ERROR:</h2>";
    echo "<pre>" . $e->getMessage() . "</pre>";
    echo "<p>If the error says 'ALTER command denied', your database user might not have permission to change tables. You will need to use phpMyAdmin.</p>";
}
?>