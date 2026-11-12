<?php
// Ensure config_loader.php is included only once to prevent errors
require_once __DIR__ . '/config_loader.php';

if (!isset($page_title)) {
    $page_title = "il-hub.com - Maltese Podcasts & Discussions | One Hub for Malta's Media";
}

// --- Dynamic Meta Description ---
if (!isset($page_description)) {
    $page_description = "Sib podcasts in Malta, dibattiti u diskussjonijiet minn madwar il-gżejjer. Il-hub.com hu ċ-ċentru organizzat għall-media Maltija.";
}

// --- Dynamic Social Image (Open Graph) ---
$og_image = isset($page_image) && !empty($page_image) ? $page_image : "https://il-hub.com/assets/social_share.jpg"; 
$current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

// Helper for translations
if (!function_exists('translateTag')) {
    function translateTag($tag) {
        $map = [
            'politics' => 'Politika', 'sports' => 'Sports', 'lifestyle' => 'Lifestyle',
            'comedy' => 'Kummiedja', 'children' => 'Tfal', 'music' => 'Mużika minn Malta',
            'business' => 'Negozju', 'society' => 'Tagħrif Soċjali'
        ];
        return $map[strtolower($tag)] ?? ucfirst($tag);
    }
}

// Helper to generate Series URL
if (!function_exists('getSeriesUrl')) {
    function getSeriesUrl($slug, $id) {
        if (!empty($slug)) {
            return "/series/" . htmlspecialchars($slug);
        }
        return "/series.php?series_id=" . $id; 
    }
}

// Helper to generate Category URL
if (!function_exists('getCategoryUrl')) {
    function getCategoryUrl($tag) {
        return "/category/" . rawurlencode($tag);
    }
}

// Keep visitors inside il-hub for episode details. Unsupported media sources
// can still be opened from the episode page itself.
if (!function_exists('getEpisodeUrl')) {
    function getEpisodeUrl($slug, $id) {
        if (!empty($slug)) {
            return "/episode/" . rawurlencode($slug);
        }
        return "/episode.php?id=" . (int) $id;
    }
}

// --- Image Proxy Helper (DISABLED) ---
if (!function_exists('getOptimizedImage')) {
    function getOptimizedImage($url) {
        // Just return the original URL. No proxy. No server load.
        return $url; 
    }
}

$analytics_id = $settings['google_analytics_id'] ?? 'G-7M3V660L5T'; 
if (empty($analytics_id) || $analytics_id === 'YOUR_ANALYTICS_ID_HERE') {
    $analytics_id = 'G-7M3V660L5T';
}

// --- FETCH NAV DATA ---
if (!isset($tags_list)) {
    try {
        $tags_list = $pdo->query("SELECT id, name FROM tags ORDER BY display_order ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $tags_list = [];
    }
}
if (!isset($series_list)) {
    try {
        $series_list = $pdo->query("SELECT id, title, slug FROM series ORDER BY title ASC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $series_list = [];
    }
}
?>
<!DOCTYPE html>
<html lang="mt" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    
    <!-- SEO Meta Tags -->
    <meta name="description" content="<?php echo htmlspecialchars($page_description); ?>">
    <link rel="canonical" href="<?php echo htmlspecialchars($current_url); ?>" />
    <link rel="alternate" type="application/rss+xml" title="il-hub.com" href="https://www.il-hub.com/feed.xml">

    <!-- PERFORMANCE: Preconnect -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://i.ytimg.com"> 
    <link rel="preconnect" href="https://tvmi.mt">
    <link rel="preconnect" href="https://placehold.co">
    <link rel="preconnect" href="https://cdn.tailwindcss.com">

    <!-- PWA / Mobile App Settings -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#111827">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="il-hub">
    <link rel="apple-touch-icon" href="https://placehold.co/192x192/ea580c/ffffff?text=HUB">

    <!-- Open Graph -->
    <meta property="og:type" content="website" />
    <meta property="og:url" content="<?php echo htmlspecialchars($current_url); ?>" />
    <meta property="og:title" content="<?php echo htmlspecialchars($page_title); ?>" />
    <meta property="og:description" content="<?php echo htmlspecialchars($page_description); ?>" />
    <meta property="og:image" content="<?php echo htmlspecialchars($og_image); ?>" />

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image" />
    <meta property="twitter:url" content="<?php echo htmlspecialchars($current_url); ?>" />
    <meta property="twitter:title" content="<?php echo htmlspecialchars($page_title); ?>" />
    <meta property="twitter:description" content="<?php echo htmlspecialchars($page_description); ?>" />
    <meta property="twitter:image" content="<?php echo htmlspecialchars($og_image); ?>" />
    
    <!-- PERFORMANCE: Defer non-critical CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Load Google Fonts asynchronously -->
    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"></noscript>
    
    <?php if (!empty($analytics_id)): ?>
    <!-- Defer Analytics Script -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo htmlspecialchars($analytics_id); ?>"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', '<?php echo htmlspecialchars($analytics_id); ?>');
    </script>
    <?php endif; ?>
    
    <style>
        body { font-family: 'Inter', sans-serif; background: #090d16; color: white; }
        a { color: #fb923c; text-decoration: none; }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #111827; }
        ::-webkit-scrollbar-thumb { background: #374151; border-radius: 8px; }
        ::-webkit-scrollbar-thumb:hover { background: #4b5563; }
        .line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .line-clamp-3 { display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
        .sub-nav::-webkit-scrollbar { height: 4px; }
        .sub-nav::-webkit-scrollbar-thumb { background: #fb923c; border-radius: 4px; }
        .sub-nav::-webkit-scrollbar-track { background: transparent; }
        body { padding-bottom: env(safe-area-inset-bottom); }
        
        /* Content Visibility Optimization */
        .content-visibility-auto {
            content-visibility: auto;
            contain-intrinsic-size: 1px 1000px; /* Estimate height */
        }
        .hub-surface { background: linear-gradient(145deg, rgba(31,41,55,.9), rgba(17,24,39,.72)); }
        .hub-card { background: rgba(22,29,43,.88); border-color: rgba(75,85,99,.55); }
        .hub-card:hover { transform: translateY(-4px); border-color: rgba(249,115,22,.62); box-shadow: 0 22px 55px rgba(0,0,0,.28); }
    </style>
</head>
<body class="antialiased min-h-screen flex flex-col text-gray-200">

    <header class="bg-[#090d16]/90 backdrop-blur-xl sticky top-0 z-50 border-b border-white/5">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16 sm:h-20">
                <div class="flex-shrink-0">
                    <a href="/index.php" class="text-2xl sm:text-3xl font-black tracking-tight text-white">
                        <span class="text-orange-500">il-hub</span>.com
                    </a>
                </div>
                <div class="sm:hidden"></div>
            </div>
        </div>
    </header>

    <nav class="bg-[#0d1320]/92 backdrop-blur-xl border-b border-white/5 sticky top-16 sm:top-20 z-40">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center gap-3 py-3">
                
                <!-- Left Side: Menus -->
                <div class="flex gap-2 sm:gap-4 min-w-0">
                    <div class="relative">
                        <button onclick="toggleDropdown('categoryMenu')" aria-expanded="false" aria-controls="categoryMenu" class="font-semibold text-white transition-colors flex items-center bg-orange-600 hover:bg-orange-700 px-3 sm:px-4 py-2 rounded-lg shadow-md">
                            Kategoriji
                            <svg class="w-4 h-4 ml-2 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </button>
                        <div id="categoryMenu" class="absolute left-0 top-full mt-2 w-56 bg-gray-900 rounded-xl shadow-2xl py-2 z-50 hidden border border-gray-700">
                            <?php if (!empty($tags_list)): ?>
                                <?php foreach ($tags_list as $tag): ?>
                                    <a href="<?php echo getCategoryUrl($tag['name']); ?>" class="block px-5 py-3 text-sm text-gray-300 hover:bg-gray-700/50 hover:text-orange-400 capitalize transition-colors">
                                        <?php echo translateTag($tag['name']); ?>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="relative">
                        <button onclick="toggleDropdown('seriesMenu')" aria-expanded="false" aria-controls="seriesMenu" class="font-semibold text-white transition-colors flex items-center bg-gray-700 hover:bg-gray-600 px-3 sm:px-4 py-2 rounded-lg shadow-md">
                            Serje
                            <svg class="w-4 h-4 ml-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </button>
                        <div id="seriesMenu" class="absolute left-0 top-full mt-2 w-72 bg-gray-900 rounded-xl shadow-2xl py-2 z-50 hidden border border-gray-700 max-h-[80vh] overflow-y-auto">
                            <?php if (!empty($series_list)): ?>
                                <?php foreach ($series_list as $series_item): ?>
                                    <a href="<?php echo getSeriesUrl($series_item['slug'] ?? '', $series_item['id']); ?>" class="block px-5 py-3 text-sm text-gray-300 hover:bg-gray-700/50 hover:text-orange-400 transition-colors">
                                        <?php echo htmlspecialchars($series_item['title']); ?>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Right Side: Search & Suggest -->
                <div class="flex items-center space-x-4">

                    <a href="/search.php" class="text-gray-400 hover:text-white transition-colors p-2 rounded-full hover:bg-gray-700/50" title="Fittex">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </a>

                    <a href="/suggest.php" class="hidden sm:inline-flex items-center text-sm font-medium text-orange-400 hover:text-white transition-colors border border-orange-500/30 bg-orange-500/10 px-3 py-2 rounded-full hover:bg-orange-500/20">
                        Jonqosna xi programm?
                    </a>
                </div>

            </div>
        </div>
    </nav>

<script>
    function toggleDropdown(id) {
        const menuIds = ['categoryMenu', 'seriesMenu'];
        menuIds.forEach(menuId => {
            const menu = document.getElementById(menuId);
            if (menuId === id) {
                menu.classList.toggle('hidden');
                const button = document.querySelector('[aria-controls="' + menuId + '"]');
                if (button) button.setAttribute('aria-expanded', menu.classList.contains('hidden') ? 'false' : 'true');
            } else {
                menu.classList.add('hidden');
                const button = document.querySelector('[aria-controls="' + menuId + '"]');
                if (button) button.setAttribute('aria-expanded', 'false');
            }
        });
    }
    document.addEventListener('click', function(event) {
        const navBar = event.target.closest('nav');
        if (!navBar) {
            document.getElementById('categoryMenu').classList.add('hidden');
            document.getElementById('seriesMenu').classList.add('hidden');
        }
    });
</script>
