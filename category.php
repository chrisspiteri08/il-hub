<?php
// --- FORCE ERROR DISPLAY ---
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// 1. Include settings (includes db_config.php)
require_once 'config_loader.php';

// Helper for Maltese translation
function translateTag($tag) {
    $map = [
        'politics' => 'Politika',
        'sports' => 'Sports',
        'lifestyle' => 'Lifestyle',
        'comedy' => 'Kummiedja',
        'children' => 'Tfal',
        'music' => 'Mużika minn Malta',
        'business' => 'Negozju',
        'society' => 'Tagħrif Soċjali'
    ];
    return $map[strtolower($tag)] ?? ucfirst($tag);
}

// --- SEO: Category Descriptions (Kept for Meta Tags ONLY) ---
function getCategoryDescription($tag) {
    $descriptions = [
        'politics' => "Segwi l-aħħar dibattiti politiċi, intervisti mal-membri parlamentari, u analiżi kurrenti tas-sitwazzjoni politika f'Malta.",
        'sports' => "L-aħjar azzjoni sportiva lokali. Minn futbol tal-Premier League Malti sa sports oħra.",
        'lifestyle' => "Skopri programmi dwar is-saħħa, l-ikel, l-ivvjaġġar u l-moda.",
        'comedy' => "Idħaq daħka bil-kummiedja Maltija.",
        'children' => "Kontenut edukattiv u divertenti għat-tfal.",
        'music' => "Isma' l-aqwa mużika lokali.",
        'business' => "Analiżi ekonomika u stejjer ta' suċċess.",
        'society' => "Diskussjonijiet dwar temi soċjali u kulturali."
    ];
    return $descriptions[strtolower($tag)] ?? "L-aħjar programmi f'din il-kategorija.";
}

// 2. Resolve a clean category slug while preserving old name-based URLs.
$tag_input = $_GET['tag'] ?? 'politics';
$tag_lookup = $pdo->prepare('SELECT name, slug FROM tags WHERE slug = ? OR name = ? LIMIT 1');
$tag_lookup->execute([$tag_input, $tag_input]);
$current_tag = $tag_lookup->fetch(PDO::FETCH_ASSOC);
if (!$current_tag) {
    http_response_code(404);
    exit('Category not found.');
}
if ($tag_input !== $current_tag['slug']) {
    header('Location: /category/' . rawurlencode($current_tag['slug']), true, 301);
    exit;
}
$tag_name = $current_tag['name'];
$tag_slug = $current_tag['slug'];
$page_description = getCategoryDescription($tag_name); // Set Meta Description

// 3. Pagination
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 12; 
$offset = ($page - 1) * $limit;

try {
    // 4. Queries
    // Count query
    $count_query = "
        SELECT COUNT(DISTINCT ep.id) 
        FROM episodes AS ep 
        JOIN episode_tags AS et ON ep.id = et.episode_id 
        JOIN tags AS t ON et.tag_id = t.id 
        WHERE t.name = ? 
        AND ep.title != 'Deleted video' 
        AND ep.title != 'Private video'
    ";
    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute([$tag_name]);
    $total_items = (int)$count_stmt->fetchColumn();
    $total_pages = ceil($total_items / $limit);

    // --- Main Episodes Query ---
    $query = "
        SELECT 
            ep.id, 
            ep.title AS episode_title, 
            ep.description, 
            ep.media_url, 
            ep.thumbnail_url, 
            ep.platform, 
            ep.publish_date, 
            s.title AS series_title,
            s.slug AS series_slug,  
            s.id AS series_id       
        FROM 
            episodes AS ep
        JOIN 
            series AS s ON ep.series_id = s.id
        JOIN
            episode_tags AS et ON ep.id = et.episode_id
        JOIN
            tags AS t ON et.tag_id = t.id
        WHERE 
            t.name = :tag_name
            AND ep.title != 'Deleted video'
            AND ep.title != 'Private video'
        GROUP BY 
            ep.id
        ORDER BY 
            ep.publish_date DESC
        LIMIT :limit OFFSET :offset
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindParam(':tag_name', $tag_name, PDO::PARAM_STR);
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Navigation Dropdown Data
    $tags_list = $pdo->query("SELECT id, name, slug FROM tags ORDER BY display_order ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $series_list = $pdo->query("SELECT id, title, slug FROM series ORDER BY title ASC")->fetchAll(PDO::FETCH_ASSOC); 

} catch (PDOException $e) {
    die("Error fetching items: " . $e->getMessage());
}

// 5. Include Header
$page_title = translateTag($tag_name) . " - il-hub.com";
$canonical_path = '/category/' . rawurlencode($tag_slug);
include 'header_frontend.php';
?>

    <!-- Main Content -->
    <main class="flex-grow container mx-auto px-4 sm:px-6 lg:px-8 py-12">

        <!-- NEW: Breadcrumbs -->
        <nav class="flex mb-8 text-sm font-medium text-gray-400" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3">
                <li class="inline-flex items-center">
                    <a href="/index.php" class="hover:text-white transition-colors">
                        Home
                    </a>
                </li>
                <li aria-current="page">
                    <div class="flex items-center">
                        <svg class="w-3 h-3 mx-1 text-gray-600" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 6 10">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 9 4-4-4-4"/>
                        </svg>
                        <span class="ml-1 text-orange-500 md:ml-2 capitalize">
                            <?php echo translateTag($tag_name); ?>
                        </span>
                    </div>
                </li>
            </ol>
        </nav>

        <!-- Title & Stats (REMOVED Intro Text) -->
        <div class="mb-12 border-b border-gray-800 pb-6 flex flex-col md:flex-row justify-between md:items-end gap-4">
            <div class="max-w-3xl">
                <h2 class="text-4xl font-bold text-white capitalize mb-2 flex items-center">
                    <span class="w-2 h-8 bg-orange-500 rounded-full mr-4"></span>
                    <?php echo translateTag($tag_name); ?>
                </h2>
                
                <!-- INTRO TEXT REMOVED HERE -->
                
                <p class="text-gray-500 text-sm mt-2">
                    Paġna <?php echo $page; ?> minn <?php echo $total_pages; ?> (<?php echo $total_items; ?> oġġetti totali)
                </p>
            </div>
            <div class="mb-2 shrink-0">
                <a href="/suggest.php" class="whitespace-nowrap text-sm font-medium text-orange-400 hover:text-orange-300 transition-colors border border-orange-500/30 bg-orange-500/10 px-3 py-2 rounded-full hover:bg-orange-500/20">
                    Issuġġerixxi Kontenut &rarr;
                </a>
            </div>
        </div>

        <!-- Items Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8">
            <?php if (empty($items)): ?>
                <div class="col-span-full text-center py-20">
                    <div class="inline-block p-4 rounded-full bg-gray-800 mb-4">
                        <svg class="w-8 h-8 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                    </div>
                    <p class="text-gray-500 text-lg">L-ebda kontenut misjub għal din il-kategorija.</p>
                </div>
            <?php else: ?>
                <?php foreach ($items as $item): ?>
                <?php 
                    $pub_timestamp = !empty($item['publish_date']) ? strtotime($item['publish_date']) : 0;
                    $now = time();
                    $is_coming_soon = $pub_timestamp > $now;
                    $is_new = !$is_coming_soon && ($pub_timestamp > ($now - 86400));
                ?>
                
                <div class="bg-gray-800 rounded-2xl shadow-lg overflow-hidden flex flex-col hover:shadow-2xl hover:-translate-y-1 transition-all duration-300 border border-gray-700 group h-full">
                    <a href="<?php echo htmlspecialchars($item['media_url']); ?>" target="_blank" class="block relative aspect-video overflow-hidden">
                        <img loading="lazy" class="w-full h-full object-cover transform group-hover:scale-105 transition-transform duration-700" src="<?php echo htmlspecialchars($item['thumbnail_url']); ?>" alt="Cover art">
                        
                        <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-center justify-center">
                            <div class="w-16 h-16 bg-orange-500 rounded-full flex items-center justify-center shadow-xl transform scale-75 group-hover:scale-100 transition-transform duration-300">
                                <svg class="w-8 h-8 text-white ml-1" fill="currentColor" viewBox="0 0 20 20"><path d="M6.3 2.841A1.5 1.5 0 004 4.11V15.89a1.5 1.5 0 002.3 1.269l9.344-5.89a1.5 1.5 0 000-2.538L6.3 2.84z"/></svg>
                            </div>
                        </div>

                        <?php if ($is_coming_soon): ?>
                            <div class="absolute top-3 right-3 bg-yellow-500 text-yellow-900 text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider shadow-lg z-20 border border-yellow-400">
                                Dalwaqt
                            </div>
                        <?php elseif ($is_new): ?>
                            <div class="absolute top-3 right-3 bg-brand-coral text-white text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider shadow-lg z-20 border border-brand-coral animate-pulse">
                                Ġdid
                            </div>
                        <?php endif; ?>
                    </a>
                    <div class="p-6 flex flex-col flex-grow">
                        <div class="flex justify-between items-start mb-3">
                            <!-- UPDATED: Made the series badge a clickable link -->
                            <a href="<?php echo getSeriesUrl($item['series_slug'] ?? '', $item['series_id']); ?>" class="text-xs font-bold text-orange-400 uppercase tracking-wider bg-orange-500/10 px-2 py-1 rounded-md hover:bg-orange-500/20 transition-colors">
                                <?php echo htmlspecialchars($item['series_title']); ?>
                            </a>
                            
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
                            <span class="text-xs text-gray-500 font-medium"><?php echo htmlspecialchars($item['platform']); ?></span>
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
        <div class="flex justify-center items-center space-x-4 mt-16">
            <?php if ($page > 1): ?>
                <a href="/category/<?php echo rawurlencode($tag_name); ?>?page=<?php echo $page - 1; ?>" class="px-6 py-3 bg-gray-800 text-white rounded-full hover:bg-gray-700 transition-colors border border-gray-700 font-medium flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg> Preċedenti
                </a>
            <?php else: ?>
                <span class="px-6 py-3 bg-gray-800/50 text-gray-600 rounded-full border border-gray-800 cursor-not-allowed font-medium flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg> Preċedenti
                </span>
            <?php endif; ?>

            <span class="text-gray-400 font-medium px-4">Paġna <?php echo $page; ?> minn <?php echo $total_pages; ?></span>

            <?php if ($page < $total_pages): ?>
                <a href="/category/<?php echo rawurlencode($tag_slug); ?>?page=<?php echo $page + 1; ?>" class="px-6 py-3 bg-orange-600 text-white rounded-full hover:bg-orange-700 transition-colors shadow-lg hover:shadow-orange-500/20 font-medium flex items-center">
                    Li Jmiss <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                </a>
            <?php else: ?>
                <span class="px-6 py-3 bg-gray-800/50 text-gray-600 rounded-full border border-gray-800 cursor-not-allowed font-medium flex items-center">
                    Li Jmiss <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                </span>
            <?php endif; ?>
        </div>

    </main>

    <!-- 6. Include Footer -->
    <?php include 'footer_frontend.php'; ?>
