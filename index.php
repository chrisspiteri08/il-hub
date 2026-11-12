<?php
// Log production errors without exposing technical details to visitors.
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
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
    $tags_list = $pdo->query("SELECT id, name, slug FROM tags ORDER BY display_order ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
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
$canonical_path = '/';
include 'header_frontend.php';
?>

    <main class="flex-grow container mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">

        <!-- Hero Section -->
        <section class="relative overflow-hidden rounded-[2rem] mb-16 border border-white/10 hub-surface px-6 py-12 sm:px-12 sm:py-16 lg:px-16 lg:py-20">
            <div class="absolute -top-32 -right-24 w-96 h-96 bg-orange-600/25 rounded-full blur-[110px] pointer-events-none"></div>
            <div class="absolute -bottom-40 left-1/3 w-80 h-80 bg-red-600/10 rounded-full blur-[110px] pointer-events-none"></div>
            <div class="relative z-10 max-w-4xl">
                <div class="inline-flex items-center gap-2 rounded-full border border-orange-500/25 bg-orange-500/10 px-3 py-1.5 text-xs font-bold uppercase tracking-[.18em] text-orange-300 mb-6">
                    <span class="w-2 h-2 rounded-full bg-orange-400 animate-pulse"></span> Il-media Maltija, f'post wieħed
                </div>
                <h1 class="text-4xl sm:text-6xl lg:text-7xl font-black text-white mb-6 tracking-[-.045em] leading-[1.02]">
                    Isma'. Ara. Skopri.<br><span class="text-transparent bg-clip-text bg-gradient-to-r from-white via-[#F7797A] to-[#F45859]">Kollox Malti.</span>
                </h1>
                <p class="text-lg sm:text-xl text-gray-300 max-w-2xl leading-relaxed mb-8">
                    Podcasts, diskussjonijiet u programmi minn madwar il-gżejjer—organizzati biex issib dak li jinteressak malajr.
                </p>
                <div class="flex flex-col sm:flex-row gap-3">
                    <a href="/search.php" class="inline-flex justify-center items-center px-6 py-3.5 rounded-xl text-white bg-orange-600 hover:bg-orange-500 font-bold transition-all shadow-xl shadow-orange-950/30">Fittex fil-Hub &rarr;</a>
                    <a href="#latest" class="inline-flex justify-center items-center px-6 py-3.5 rounded-xl text-white border border-white/10 bg-white/5 hover:bg-white/10 font-semibold transition-all">Ara l-aktar friski</a>
                </div>
            </div>
        </section>

        <!-- LATEST UPDATES SCROLL -->
        <?php if (!empty($latest_per_category)): ?>
        <section id="latest" class="mb-20 scroll-mt-36">
            <div class="flex items-end justify-between mb-7">
                <div><p class="text-xs uppercase tracking-[.2em] font-bold text-orange-400 mb-2">Aġġornat regolarment</p><h2 class="text-3xl sm:text-4xl font-black tracking-tight text-white">L-iktar friski</h2></div>
                <a href="/search.php" class="text-sm font-semibold text-gray-400 hover:text-orange-400">Esplora kollox &rarr;</a>
            </div>
            <div class="flex overflow-x-auto pb-6 -mx-4 px-4 space-x-4 snap-x">
                <?php foreach ($latest_per_category as $item): ?>
                    <article class="flex-none w-72 snap-center hub-card rounded-2xl overflow-hidden border transition-all duration-300 group">
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
                    </article>
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
                
                <div class="flex justify-between items-end mb-7">
                    <a href="<?php echo getCategoryUrl($tag['name'], $tag['slug'] ?? null); ?>" class="group inline-block">
                        <h3 class="text-2xl sm:text-3xl font-black text-white capitalize flex items-center group-hover:text-orange-400 transition-colors">
                            <span class="w-1.5 h-7 bg-orange-500 rounded-full mr-3"></span>
                            <?php echo translateTag($tag['name']); ?> 
                        </h3>
                    </a>
                    <a href="<?php echo getCategoryUrl($tag['name'], $tag['slug'] ?? null); ?>" class="text-sm font-semibold text-orange-400 hover:text-orange-300 transition-colors flex items-center mb-1">
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

                        <article class="hub-card rounded-2xl overflow-hidden flex flex-col transition-all duration-300 border">
                            <a href="<?php echo getEpisodeUrl($item['episode_slug'], $item['id']); ?>" class="block relative group/image h-48 overflow-hidden">
                                <img loading="lazy" class="h-full w-full object-cover transform group-hover:scale-105 transition-transform duration-500" 
                                     src="<?php echo htmlspecialchars(getOptimizedImage($item['thumbnail_url'])); ?>" alt="Cover art" width="400" height="225">
                                
                                <div class="absolute inset-0 bg-black bg-opacity-0 group-hover/image:bg-opacity-20 transition-all duration-300 flex items-center justify-center">
                                    <svg class="w-12 h-12 text-white opacity-0 group-hover/image:opacity-100 transform scale-75 group-hover/image:scale-100 transition-all duration-300" fill="currentColor" viewBox="0 0 20 20"><path d="M6.3 2.841A1.5 1.5 0 004 4.11V15.89a1.5 1.5 0 002.3 1.269l9.344-5.89a1.5 1.5 0 000-2.538L6.3 2.84z"/></svg>
                                </div>

                                <?php if ($is_coming_soon): ?>
                                    <div class="absolute top-3 right-3 bg-brand-red text-white text-xs font-bold px-2 py-1 rounded-md uppercase tracking-wider shadow-lg z-20">Dalwaqt</div>
                                <?php elseif ($is_new): ?>
                                    <div class="absolute top-3 right-3 bg-brand-red text-white text-xs font-bold px-3 py-1 rounded-md uppercase tracking-wider shadow-lg z-20 border border-brand-red animate-pulse">Ġdid</div>
                                <?php endif; ?>
                            </a>
                            <div class="p-5 flex flex-col flex-grow">
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
                                <a href="<?php echo getEpisodeUrl($item['episode_slug'], $item['id']); ?>" class="mt-auto w-full block bg-white/5 hover:bg-orange-600 border border-white/5 text-white hover:text-white font-semibold py-2.5 px-4 rounded-xl text-center transition-colors text-sm">
                                    Ara l-Episodju <svg class="w-4 h-4 ml-1 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                                </a>
                            </div>
                        </article>
                        <?php endforeach; ?>
                    <?php endif; ?>

                </div>
            </section>
        <?php endforeach; ?>

    </main>

    <!-- 5. Include Footer -->
    <?php include 'footer_frontend.php'; ?>
