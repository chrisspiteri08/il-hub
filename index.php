<?php
// --- FORCE ERROR DISPLAY ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 1. Include the database connection logic
require_once 'config_loader.php';

// 2. Function to fetch media items by category
function getMediaItems($pdo, $category, $exclude_ids = []) {
    $exclude_sql = '';
    $params = [$category];

    if (!empty($exclude_ids)) {
        $placeholders = implode(',', array_fill(0, count($exclude_ids), '?'));
        $exclude_sql = "AND ep.id NOT IN ($placeholders)";
        $params = array_merge($params, $exclude_ids);
    }
    
    try {
        $query = "
            SELECT 
                ep.id, 
                ep.title AS episode_title, 
                ep.slug AS episode_slug,
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
                t.name = ?
                AND ep.title != 'Deleted video'
                AND ep.title != 'Private video'
                $exclude_sql
            GROUP BY 
                ep.id 
            ORDER BY 
                ep.publish_date DESC
            LIMIT 6
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("Error fetching items: " . $e->getMessage());
    }
}

// 3. Fetch Tags and Series
try {
    $tags_list = $pdo->query("SELECT id, name FROM tags ORDER BY display_order ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $series_list = $pdo->query("SELECT id, title, slug FROM series ORDER BY title ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching data: " . $e->getMessage());
}

// --- Fetch 1 Latest Episode per Category for the Top Scroll ---
$latest_per_category = [];
foreach ($tags_list as $tag) {
    if ($tag['name'] === 'elezzjoni2026') continue; // archived, exclude from freshest scroll
    try {
        $l_stmt = $pdo->prepare("
            SELECT 
                ep.id, ep.title AS episode_title, ep.slug AS episode_slug, ep.thumbnail_url, ep.media_url, ep.publish_date, 
                s.title AS series_title, s.slug AS series_slug, s.id AS series_id, t.name AS tag_name
            FROM episodes AS ep
            JOIN series AS s ON ep.series_id = s.id
            JOIN episode_tags AS et ON ep.id = et.episode_id
            JOIN tags AS t ON et.tag_id = t.id
            WHERE t.name = ? AND ep.title != 'Deleted video' AND ep.title != 'Private video'
            ORDER BY ep.publish_date DESC
            LIMIT 1
        ");
        $l_stmt->execute([$tag['name']]);
        $latest = $l_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($latest) {
            $already_added = false;
            foreach($latest_per_category as $existing) {
                if ($existing['id'] == $latest['id']) { $already_added = true; break; }
            }
            if (!$already_added) {
                $latest_per_category[] = $latest;
            }
        }
    } catch (PDOException $e) { /* ignore */ }
}

// Helper for Episode Page URL
if (!function_exists('getEpisodeUrl')) {
    function getEpisodeUrl($slug, $id) {
        if (!empty($slug)) return "/episode/" . htmlspecialchars($slug);
        return "episode.php?id=" . $id;
    }
}

// Helpers for Series and Categories are in header_frontend.php, but kept here for local context if needed
if (!function_exists('getSeriesUrl')) {
    function getSeriesUrl($slug, $id) {
        if (!empty($slug)) return "/series/" . htmlspecialchars($slug);
        return "series.php?series_id=" . $id;
    }
}

$page_title = "il-hub.com - Maltese Podcasts & Discussions | One Hub for Malta's Media";
include 'header_frontend.php';
?>

    <main class="flex-grow container mx-auto px-4 sm:px-6 lg:px-8 py-12">

        <!-- Hero Section -->
        <section class="text-center mb-16 relative">
            <div class="absolute inset-0 flex justify-center items-center opacity-20 pointer-events-none">
                 <div class="w-96 h-96 bg-orange-600 rounded-full filter blur-[100px]"></div>
            </div>
            <div class="relative z-10">
                <h1 class="hidden">Maltese Podcasts in Malta | il-hub.com</h1>
                <h2 class="text-5xl md:text-7xl font-extrabold text-white mb-6 tracking-tight">
                    Sib xi ħaġa <span class="text-transparent bg-clip-text bg-gradient-to-r from-orange-400 to-red-500">tgħodd għalik.</span>
                </h2>
                <p class="text-xl md:text-2xl text-gray-400 max-w-3xl mx-auto leading-relaxed mb-8">
                    Sib l-aħjar <b>podcasts f'Malta</b>, dibattiti u diskussjonijiet minn madwar il-gżejjer.
                </p>
                <a href="suggest.php" class="inline-flex items-center px-6 py-3 border border-orange-500 text-base font-medium rounded-full text-orange-400 bg-transparent hover:bg-orange-500 hover:text-white transition-all duration-300 shadow-lg hover:shadow-orange-500/30">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    Jonqosna xi programm? Kellimna!
                </a>
            </div>
        </section>

        <!-- LATEST UPDATES SCROLL -->
        <?php if (!empty($latest_per_category)): ?>
        <section class="mb-20">
            <div class="flex items-center mb-6">
                <h3 class="text-2xl font-bold text-white">L-iktar Friski</h3>
            </div>
            <div class="flex overflow-x-auto pb-6 -mx-4 px-4 space-x-4 snap-x">
                <?php foreach ($latest_per_category as $item): ?>
                    <div class="flex-none w-72 snap-center bg-gray-800 rounded-xl shadow-lg overflow-hidden border border-gray-700 hover:border-orange-500/50 transition-all duration-300 group">
                        <a href="<?php echo getEpisodeUrl($item['episode_slug'], $item['id']); ?>" class="block relative h-40 overflow-hidden">
                            <img loading="eager" fetchpriority="high" class="w-full h-full object-cover transform group-hover:scale-105 transition-transform duration-500" 
                                 src="<?php echo htmlspecialchars(getOptimizedImage($item['thumbnail_url'])); ?>" alt="Thumbnail" width="288" height="160">
                            
                            <div class="absolute top-2 left-2 bg-black/60 backdrop-blur-sm text-white text-[10px] font-bold px-2 py-1 rounded uppercase tracking-wider">
                                <?php echo translateTag($item['tag_name']); ?>
                            </div>
                            <div class="absolute inset-0 bg-black/20 group-hover:bg-black/10 transition-colors flex items-center justify-center opacity-0 group-hover:opacity-100">
                                <div class="w-10 h-10 bg-orange-500 rounded-full flex items-center justify-center shadow-lg">
                                    <svg class="w-5 h-5 text-white ml-0.5" fill="currentColor" viewBox="0 0 20 20"><path d="M6.3 2.841A1.5 1.5 0 004 4.11V15.89a1.5 1.5 0 002.3 1.269l9.344-5.89a1.5 1.5 0 000-2.538L6.3 2.84z"/></svg>
                                </div>
                            </div>
                        </a>
                        <div class="p-4">
                            <h4 class="text-sm font-bold text-white line-clamp-2 leading-snug mb-2 group-hover:text-orange-400 transition-colors">
                                <a href="<?php echo getEpisodeUrl($item['episode_slug'], $item['id']); ?>">
                                    <?php echo htmlspecialchars($item['episode_title']); ?>
                                </a>
                            </h4>
                            <div class="flex justify-between items-center text-xs text-gray-400">
                                <span class="font-medium text-orange-400/80 truncate max-w-[60%]"><?php echo htmlspecialchars($item['series_title']); ?></span>
                                <span><?php echo date('M d', strtotime($item['publish_date'])); ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- DYNAMIC SECTIONS LOOP -->
        <?php foreach ($tags_list as $tag): ?>
            <?php 
                $items = getMediaItems($pdo, $tag['name'], []);
            ?>
            <section id="<?php echo htmlspecialchars($tag['name']); ?>" class="mb-16 content-visibility-auto">
                
                <div class="flex justify-between items-end mb-8 border-b border-gray-800 pb-4">
                    <a href="<?php echo getCategoryUrl($tag['name']); ?>" class="group inline-block">
                        <h3 class="text-3xl font-bold text-white capitalize flex items-center group-hover:text-orange-400 transition-colors">
                            <span class="w-2 h-8 bg-orange-500 rounded-full mr-4"></span>
                            <?php echo translateTag($tag['name']); ?> 
                        </h3>
                    </a>
                    <a href="<?php echo getCategoryUrl($tag['name']); ?>" class="text-sm font-semibold text-orange-400 hover:text-orange-300 transition-colors flex items-center mb-1">
                        Ara Kollha <span class="ml-1 text-lg">&rarr;</span>
                    </a>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php if (empty($items)): ?>
                        <p class="text-gray-400 col-span-full py-8 text-center italic">L-ebda kontenut ġdid misjub.</p>
                    <?php else: ?>
                        <?php foreach ($items as $item): ?>
                        
                        <?php 
                            $pub_timestamp = !empty($item['publish_date']) ? strtotime($item['publish_date']) : 0;
                            $now = time();
                            $is_coming_soon = $pub_timestamp > $now;
                            $is_new = !$is_coming_soon && ($pub_timestamp > ($now - 86400));
                        ?>

                        <div class="bg-gray-800 rounded-lg shadow-lg overflow-hidden flex flex-col hover:shadow-xl transition-shadow duration-300 border border-gray-700">
                            <a href="<?php echo getEpisodeUrl($item['episode_slug'], $item['id']); ?>" class="block relative group/image h-48 overflow-hidden">
                                <img loading="lazy" class="h-full w-full object-cover transform group-hover:scale-105 transition-transform duration-500" 
                                     src="<?php echo htmlspecialchars(getOptimizedImage($item['thumbnail_url'])); ?>" alt="Cover art" width="400" height="225">
                                
                                <div class="absolute inset-0 bg-black bg-opacity-0 group-hover/image:bg-opacity-20 transition-all duration-300 flex items-center justify-center">
                                    <svg class="w-12 h-12 text-white opacity-0 group-hover/image:opacity-100 transform scale-75 group-hover/image:scale-100 transition-all duration-300" fill="currentColor" viewBox="0 0 20 20"><path d="M6.3 2.841A1.5 1.5 0 004 4.11V15.89a1.5 1.5 0 002.3 1.269l9.344-5.89a1.5 1.5 0 000-2.538L6.3 2.84z"/></svg>
                                </div>

                                <?php if ($is_coming_soon): ?>
                                    <div class="absolute top-3 right-3 bg-yellow-400 text-yellow-900 text-xs font-bold px-2 py-1 rounded-md uppercase tracking-wider shadow-lg z-20">Dalwaqt</div>
                                <?php elseif ($is_new): ?>
                                    <div class="absolute top-3 right-3 bg-green-600 text-white text-xs font-bold px-3 py-1 rounded-md uppercase tracking-wider shadow-lg z-20 border border-green-500 animate-pulse">Ġdid</div>
                                <?php endif; ?>
                            </a>
                            <div class="p-6 flex flex-col flex-grow">
                                <a href="<?php echo getSeriesUrl($item['series_slug'] ?? '', $item['series_id'] ?? 0); ?>" class="text-xs font-bold text-orange-400 mb-2 uppercase tracking-wide hover:underline">
                                    <?php echo htmlspecialchars($item['series_title']); ?>
                                </a>
                                
                                <h4 class="text-lg font-bold text-white mb-2 line-clamp-2 hover:text-orange-300 transition-colors">
                                    <a href="<?php echo getEpisodeUrl($item['episode_slug'], $item['id']); ?>"><?php echo htmlspecialchars($item['episode_title']); ?></a>
                                </h4>
                                
                                <?php if (!empty($item['publish_date'])): ?>
                                    <p class="text-xs text-gray-500 mb-3 flex items-center">
                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                        <?php echo date('d M Y', strtotime($item['publish_date'])); ?>
                                    </p>
                                <?php endif; ?>

                                <p class="text-gray-400 text-sm mb-4 flex-grow line-clamp-3">
                                    <?php echo mb_strimwidth(htmlspecialchars($item['description']), 0, 100, "..."); ?>
                                </p>
                                <a href="<?php echo getEpisodeUrl($item['episode_slug'], $item['id']); ?>" class="mt-auto w-full block bg-gray-700 hover:bg-orange-600 text-white font-medium py-2 px-4 rounded-lg text-center transition-colors text-sm">
                                    Ara l-Episodju <svg class="w-4 h-4 ml-1 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                </div>
            </section>
        <?php endforeach; ?>

    </main>

    <!-- 5. Include Footer -->
    <?php include 'footer_frontend.php'; ?>