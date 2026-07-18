<?php
// Set the correct header so browsers/Google treat this as XML
header("Content-Type: application/xml; charset=utf-8");

// Include database connection
require_once 'config_loader.php';

// Base URL (Change this if you move domains)
$base_url = "https://il-hub.com";

// Start XML Output
echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">

    <!-- 1. Static Pages -->
    <url>
        <loc><?php echo $base_url; ?>/</loc>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>
    <url>
        <loc><?php echo $base_url; ?>/suggest.php</loc>
        <changefreq>monthly</changefreq>
        <priority>0.3</priority>
    </url>

    <!-- 2. Dynamic Categories -->
    <?php
    try {
        $tags = $pdo->query("SELECT name FROM tags ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($tags as $tag) {
            // Use rawurlencode to handle spaces or special characters safely
            $loc = $base_url . '/category/' . rawurlencode($tag['name']);
            echo "
    <url>
        <loc>{$loc}</loc>
        <changefreq>daily</changefreq>
        <priority>0.8</priority>
    </url>";
        }
    } catch (PDOException $e) {
        // Silently fail or log error
    }
    ?>

    <!-- 3. Dynamic Series -->
    <?php
    try {
        // FIX: Removed 'updated_at' to prevent errors if column is missing
        // FIX: Select ID as well for fallback
        $series_list = $pdo->query("SELECT id, slug FROM series")->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($series_list as $series) {
            // Use pretty URL if slug exists, otherwise fallback to ID
            if (!empty($series['slug'])) {
                $loc = $base_url . '/series/' . htmlspecialchars($series['slug']);
            } else {
                $loc = $base_url . '/series.php?series_id=' . $series['id'];
            }
            
            // Use current date as default lastmod
            $lastmod = date('Y-m-d');
            
            echo "
    <url>
        <loc>{$loc}</loc>
        <lastmod>{$lastmod}</lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.9</priority>
    </url>";
        }
    } catch (PDOException $e) {
        // Log the error internally so it doesn't break the XML structure
        error_log("Sitemap Error: " . $e->getMessage());
    }
    ?>

</urlset>