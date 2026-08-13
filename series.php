<?php
// --- FORCE ERROR DISPLAY ---
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

require_once 'config_loader.php';

// Helper for Maltese translation
function translateTag($tag) {
    $map = [
        'politics' => 'Politika', 'sports' => 'Sports', 'lifestyle' => 'Lifestyle',
        'comedy' => 'Kummiedja', 'children' => 'Tfal', 'music' => 'Mużika minn Malta',
        'business' => 'Negozju', 'society' => 'Tagħrif Soċjali'
    ];
    return $map[strtolower($tag)] ?? ucfirst($tag);
}

// Helper to generate Category URL
if (!function_exists('getCategoryUrl')) {
    function getCategoryUrl($tag) {
        return "/category/" . rawurlencode($tag);
    }
}

// Helper to generate Series URL
if (!function_exists('getSeriesUrl')) {
    function getSeriesUrl($slug, $id) {
        if (!empty($slug)) return "/series/" . htmlspecialchars($slug);
        return "series.php?series_id=" . $id;
    }
}

// 2. Get Series ID OR Slug
$series_slug = $_GET['slug'] ?? null;
$series_id = $_GET['series_id'] ?? null;

try {
    // 3. Fetch Series Details
    if ($series_slug) {
        $series_stmt = $pdo->prepare("SELECT * FROM series WHERE slug = ?");
        $series_stmt->execute([$series_slug]);
    } else {
        $series_stmt = $pdo->prepare("SELECT * FROM series WHERE id = ?");
        $series_stmt->execute([$series_id]);
    }
    
    $series = $series_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$series) {
        header("HTTP/1.0 404 Not Found");
        echo "<h1 style='color:white;text-align:center;margin-top:50px;'>Series Not Found</h1>";
        exit;
    }

    $series_title = $series['title'];
    $series_id = $series['id'];
    
    // --- SEO MAGIC ---
    if (!empty($series['description'])) {
        $page_description = mb_strimwidth(strip_tags($series['description']), 0, 160, "...");
    } else {
        $page_description = "Ara l-episodji kollha ta' $series_title fuq il-hub.com. L-aqwa kontenut Malti f'post wieħed.";
    }
    
    // --- NEW: Set Social Image for Header ---
    if (!empty($series['series_image'])) {
        $page_image = $series['series_image'];
    }

    // --- Determine Primary Category for Breadcrumbs & Recommendations ---
    $cat_stmt = $pdo->prepare("
        SELECT t.name 
        FROM tags t
        JOIN episode_tags et ON t.id = et.tag_id
        JOIN episodes e ON et.episode_id = e.id
        WHERE e.series_id = ?
        GROUP BY t.id
        ORDER BY COUNT(et.tag_id) DESC
        LIMIT 1
    ");
    $cat_stmt->execute([$series_id]);
    $primary_tag = $cat_stmt->fetchColumn();

    // --- Fetch Similar Series ---
    $similar_series = [];
    if ($primary_tag) {
        $sim_stmt = $pdo->prepare("
            SELECT DISTINCT s.id, s.title, s.slug, s.series_image 
            FROM series s
            JOIN episodes e ON s.id = e.series_id
            JOIN episode_tags et ON e.id = et.episode_id
            JOIN tags t ON et.tag_id = t.id
            WHERE t.name = ? AND s.id != ?
            LIMIT 3
        ");
        $sim_stmt->execute([$primary_tag, $series_id]);
        $similar_series = $sim_stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // 4. Pagination setup
    $page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 12; 
    $offset = ($page - 1) * $limit;

    // 5. Count Items
    $count_query = "SELECT COUNT(id) FROM episodes WHERE series_id = ? AND title != 'Deleted video' AND title != 'Private video'";
    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute([$series_id]);
    $total_items = (int)$count_stmt->fetchColumn();
    $total_pages = ceil($total_items / $limit);

    // 6. Fetch Episodes
    $query = "
        SELECT title AS episode_title, description, media_url, thumbnail_url, platform, publish_date
        FROM episodes
        WHERE series_id = :series_id AND title != 'Deleted video' AND title != 'Private video'
        ORDER BY publish_date DESC
        LIMIT :limit OFFSET :offset
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindParam(':series_id', $series_id, PDO::PARAM_INT);
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Nav Data
    $tags_list = $pdo->query("SELECT id, name FROM tags ORDER BY display_order ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $series_list = $pdo->query("SELECT id, title, slug FROM series ORDER BY title ASC")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error fetching items: " . $e->getMessage());
}

// 7. Include Header
$page_title = htmlspecialchars($series_title) . " - il-hub.com";
include 'header_frontend.php';
?>

    <!-- Main Content -->
    <main class="flex-grow container mx-auto px-4 sm:px-6 lg:px-8 py-12">

        <!-- Breadcrumbs -->
        <nav class="flex mb-8 text-sm font-medium text-gray-400" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3">
                <li class="inline-flex items-center">
                    <a href="/index.php" class="hover:text-white transition-colors">
                        Home
                    </a>
                </li>
                <?php if ($primary_tag): ?>
                <li>
                    <div class="flex items-center">
                        <svg class="w-3 h-3 mx-1 text-gray-600" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 6 10">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 9 4-4-4-4"/>
                        </svg>
                        <a href="<?php echo getCategoryUrl($primary_tag); ?>" class="ml-1 hover:text-white transition-colors capitalize">
                            <?php echo translateTag($primary_tag); ?>
                        </a>
                    </div>
                </li>
                <?php endif; ?>
                <li aria-current="page">
                    <div class="flex items-center">
                        <svg class="w-3 h-3 mx-1 text-gray-600" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 6 10">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 9 4-4-4-4"/>
                        </svg>
                        <span class="ml-1 text-orange-500 md:ml-2">
                            <?php echo htmlspecialchars($series_title); ?>
                        </span>
                    </div>
                </li>
            </ol>
        </nav>

        <!-- Series Header Info -->
        <div class="mb-12 border-b border-gray-800 pb-6 flex flex-col md:flex-row justify-between md:items-end gap-6">
            <div>
                <h2 class="text-4xl font-bold text-white mb-2">
                    <?php echo htmlspecialchars($series_title); ?>
                </h2>
                <?php if (!empty($series['description'])): ?>
                    <p class="text-gray-300 max-w-2xl mt-2 text-lg leading-relaxed">
                        <?php echo nl2br(htmlspecialchars($series['description'])); ?>
                    </p>
                <?php endif; ?>
                
                <p class="text-gray-500 mt-4 text-sm">
                    Paġna <?php echo $page; ?> minn <?php echo $total_pages; ?> (<?php echo $total_items; ?> oġġetti totali)
                </p>
            </div>
             <div class="mb-2">
                <a href="suggest.php" class="text-sm font-medium text-orange-400 hover:text-orange-300 transition-colors border border-orange-500/30 bg-orange-500/10 px-3 py-2 rounded-full hover:bg-orange-500/20">
                    Issuġġerixxi Kontenut &rarr;
                </a>
            </div>
        </div>

        <!-- Episodes Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8">
            <?php if (empty($items)): ?>
                <div class="col-span-full text-center py-20">
                    <div class="inline-block p-4 rounded-full bg-gray-800 mb-4">
                        <svg class="w-8 h-8 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                    </div>
                    <p class="text-gray-500 text-lg">L-ebda episodju misjub għal din is-serje.</p>
                </div>
            <?php else: ?>
                <?php foreach ($items as $item): ?>
                <?php $is_coming_soon = !empty($item['publish_date']) && strtotime($item['publish_date']) > time(); ?>
                
                <div class="bg-gray-800 rounded-2xl shadow-lg overflow-hidden flex flex-col hover:shadow-2xl hover:-translate-y-1 transition-all duration-300 border border-gray-700 group h-full">
                    <a href="<?php echo htmlspecialchars($item['media_url']); ?>" target="_blank" class="block relative aspect-video overflow-hidden">
                        <img class="w-full h-full object-cover transform group-hover:scale-105 transition-transform duration-700" src="<?php echo htmlspecialchars($item['thumbnail_url']); ?>" alt="Cover art">
                        <?php if ($is_coming_soon): ?>
                            <div class="absolute top-3 right-3 bg-yellow-500 text-yellow-900 text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider shadow-lg z-10 border border-yellow-400">Coming Soon</div>
                        <?php endif; ?>
                        <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-center justify-center backdrop-blur-[2px]">
                            <div class="w-16 h-16 bg-orange-500 rounded-full flex items-center justify-center shadow-xl transform scale-75 group-hover:scale-100 transition-transform duration-300">
                                <svg class="w-8 h-8 text-white ml-1" fill="currentColor" viewBox="0 0 20 20"><path d="M6.3 2.841A1.5 1.5 0 004 4.11V15.89a1.5 1.5 0 002.3 1.269l9.344-5.89a1.5 1.5 0 000-2.538L6.3 2.84z"/></svg>
                            </div>
                        </div>
                    </a>
                    <div class="p-6 flex flex-col flex-grow">
                        <div class="flex justify-between items-start mb-3">
                            <span class="text-xs font-bold text-gray-500 uppercase tracking-wider bg-gray-700/50 px-2 py-1 rounded-md"><?php echo htmlspecialchars($item['platform']); ?></span>
                            <?php if (!empty($item['publish_date'])): ?>
                                <span class="text-xs text-gray-500 flex items-center whitespace-nowrap ml-2"><?php echo date('M d', strtotime($item['publish_date'])); ?></span>
                            <?php endif; ?>
                        </div>
                        <h4 class="text-xl font-bold text-white mb-3 leading-tight line-clamp-2 group-hover:text-orange-400 transition-colors">
                            <a href="<?php echo htmlspecialchars($item['media_url']); ?>" target="_blank"><?php echo htmlspecialchars($item['episode_title']); ?></a>
                        </h4>
                        <p class="text-gray-400 text-sm mb-6 flex-grow line-clamp-3 leading-relaxed">
                            <?php echo mb_strimwidth(htmlspecialchars($item['description']), 0, 120, "..."); ?>
                        </p>
                        <div class="mt-auto pt-4 border-t border-gray-700/50 flex items-center justify-between">
                            <span class="text-xs text-gray-500 font-medium"></span>
                            <a href="<?php echo htmlspecialchars($item['media_url']); ?>" target="_blank" class="text-sm font-semibold text-white hover:text-orange-400 transition-colors flex items-center">
                                Ara Issa <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <div class="flex justify-center items-center space-x-4 mt-16 mb-16">
            <?php 
                $base_url = $series_slug ? "series/" . $series_slug : "series.php?series_id=" . $series_id;
                $joiner = strpos($base_url, '?') !== false ? '&' : '?';
            ?>
            
            <?php if ($page > 1): ?>
                <a href="<?php echo $base_url . $joiner . 'page=' . ($page - 1); ?>" class="px-6 py-3 bg-gray-800 text-white rounded-full hover:bg-gray-700 transition-colors border border-gray-700 font-medium flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg> Preċedenti
                </a>
            <?php else: ?>
                <span class="px-6 py-3 bg-gray-800/50 text-gray-600 rounded-full border border-gray-800 cursor-not-allowed font-medium flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg> Preċedenti
                </span>
            <?php endif; ?>

            <span class="text-gray-400 font-medium px-4">Paġna <?php echo $page; ?> minn <?php echo $total_pages; ?></span>

            <?php if ($page < $total_pages): ?>
                <a href="<?php echo $base_url . $joiner . 'page=' . ($page + 1); ?>" class="px-6 py-3 bg-orange-600 text-white rounded-full hover:bg-orange-700 transition-colors shadow-lg hover:shadow-orange-500/20 font-medium flex items-center">
                    Li Jmiss <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                </a>
            <?php else: ?>
                <span class="px-6 py-3 bg-gray-800/50 text-gray-600 rounded-full border border-gray-800 cursor-not-allowed font-medium flex items-center">
                    Li Jmiss <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                </span>
            <?php endif; ?>
        </div>

        <!-- === NEW: SIMILAR SERIES SECTION === -->
        <?php if (!empty($similar_series)): ?>
        <div class="border-t border-gray-800 pt-12 mt-12">
            <h3 class="text-2xl font-bold text-white mb-6">Jistgħu jinteressawk ukoll</h3>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                <?php foreach ($similar_series as $sim): ?>
                    <a href="<?php echo getSeriesUrl($sim['slug'], $sim['id']); ?>" class="group block bg-gray-800 rounded-xl overflow-hidden hover:shadow-xl transition-all duration-300 border border-gray-700 hover:border-orange-500/50">
                        <div class="aspect-video overflow-hidden relative">
                            <!-- Use series image or fallback placeholder -->
                            <?php 
                                $img = !empty($sim['series_image']) ? $sim['series_image'] : 'https://placehold.co/600x400/1f2937/7f9cf5?text=' . urlencode($sim['title']);
                            ?>
                            <img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($sim['title']); ?>" class="w-full h-full object-cover transform group-hover:scale-105 transition-transform duration-500">
                            
                            <!-- Overlay -->
                            <div class="absolute inset-0 bg-black/20 group-hover:bg-black/10 transition-colors"></div>
                        </div>
                        <div class="p-4">
                            <h4 class="text-lg font-bold text-white group-hover:text-orange-400 transition-colors truncate">
                                <?php echo htmlspecialchars($sim['title']); ?>
                            </h4>
                            <p class="text-sm text-gray-500 mt-1">Ara s-serje &rarr;</p>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </main>

    <?php include 'footer_frontend.php'; ?>
