<?php
// 1. Include the configuration and database connection
require_once 'config_loader.php';

// 2. Fetch all episodes tagged with 'elezzjoni2026'
try {
    $query = "
        SELECT 
            ep.id, 
            ep.title AS episode_title, 
            ep.slug AS episode_slug,
            ep.description, 
            ep.media_url, 
            ep.thumbnail_url, 
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
            (t.name = 'elezzjoni2026')
            AND ep.title != 'Deleted video' 
            AND ep.title != 'Private video'
        ORDER BY 
            ep.publish_date DESC
        LIMIT 50
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $trail_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching campaign trail: " . $e->getMessage());
}

// Helper for Episode Page URL
if (!function_exists('getEpisodeUrl')) {
    function getEpisodeUrl($slug, $id) {
        if (!empty($slug)) return "/episode/" . htmlspecialchars($slug);
        return "episode.php?id=" . $id;
    }
}

// Helper for Series URL (Fallback check)
if (!function_exists('getSeriesUrl')) {
    function getSeriesUrl($slug, $id) {
        if (!empty($slug)) return "/series/" . htmlspecialchars($slug);
        return "series.php?series_id=" . $id;
    }
}

$page_title = "Elezzjoni 2026: Partit Laburista, Partit Nazzjonalista | il-hub.com";
$page_description = "Segwi l-kampanja elettorali u d-dibattiti għall-Elezzjoni 2026. L-aħħar aġġornamenti mill-programmi tal-Partit Laburista u l-Partit Nazzjonalista, l-intervisti u l-attivitajiet politiċi f'Malta.";
include 'header_frontend.php';
?>

<main class="flex-grow container mx-auto px-4 sm:px-6 lg:px-8 py-12">
    
    <!-- Header Banner Section -->
    <section class="relative w-full h-64 md:h-80 rounded-2xl overflow-hidden mb-16 flex flex-col items-center shadow-2xl border border-gray-700 text-center px-4 bg-gray-900">
        <!-- Background Image -->
        <img src="/assets/election_2026_banner.png" alt="Election 2026 Banner" class="absolute inset-0 w-full h-full object-cover object-top z-0 opacity-80">
        
        <!-- Gradient Overlay -->
        <div class="absolute inset-0 bg-gradient-to-t from-gray-900 via-gray-900/40 to-transparent z-10"></div>
        
        <!-- Text Content -->
        <div class="relative z-20 max-w-4xl mx-auto mt-auto mb-8">
            <p class="text-lg md:text-xl text-gray-200 mx-auto drop-shadow-md font-medium mb-3">
                Il-programmi, id-dibattiti u l-intervisti politiċi kollha f'post wieħed. Segwi l-kampanja elettorali.
            </p>
            <p class="text-sm md:text-base text-gray-300 mx-auto drop-shadow-md">
                Żomm ruħek infurmat(a) mad-dibattiti u l-iżviluppi ewlenin mill-kampanji elettorali għall-<strong>Elezzjoni 2026</strong>. 
                Segwi dak li qed jintqal u jsir minn ta' quddiem, fl-attivitajiet u l-konferenzi stampa tal-<strong>Partit Laburista</strong> u l-<strong>Partit Nazzjonalista</strong> f'Malta.
            </p>
        </div>
    </section>

    <!-- Timeline Section -->
    <section class="max-w-4xl mx-auto">
        <?php if (empty($trail_items)): ?>
            <div class="bg-gray-800 rounded-xl shadow-lg border border-gray-700 p-8 text-center">
                <p class="text-gray-400 text-lg italic">Għad m'hemm l-ebda programm għal din il-kampanja.</p>
                <p class="text-gray-500 text-sm mt-2">L-ewwel programmi jibdew jidhru hawn hekk kif tibda l-kampanja elettorali.</p>
            </div>
        <?php else: ?>
            <div class="relative border-l-4 border-orange-500/30 ml-3 md:ml-6 space-y-12 pb-12">
                
                <?php 
                $current_date = '';
                foreach ($trail_items as $item): 
                    $item_date = date('Y-m-d', strtotime($item['publish_date']));
                    $display_date = date('l, d F Y', strtotime($item['publish_date']));
                    
                    if ($item_date !== $current_date): 
                        if ($current_date !== '') echo '</div>'; // close previous date group if not first
                        $current_date = $item_date;
                ?>
                    <!-- Date Header -->
                    <div class="relative pl-8 md:pl-12 mb-6 mt-12 group first:mt-0">
                        <div class="absolute w-4 h-4 bg-gray-700 rounded-full -left-[10px] top-1/2 -translate-y-1/2 border-2 border-gray-900 shadow-sm"></div>
                        <h2 class="text-xl font-bold text-orange-400 capitalize tracking-wide"><?php echo $display_date; ?></h2>
                    </div>
                    <div class="space-y-6">
                <?php endif; ?>

                    <!-- Timeline Item -->
                    <div class="relative pl-8 md:pl-12 group">
                        <!-- Timeline Dot -->
                        <div class="absolute w-6 h-6 bg-orange-500 rounded-full -left-[14px] top-2 border-4 border-gray-900 shadow-lg group-hover:scale-125 transition-transform duration-300"></div>
                        
                        <!-- Content Card -->
                        <div class="bg-gray-800 rounded-xl shadow-lg border border-gray-700 overflow-hidden flex flex-col md:flex-row hover:border-orange-500/50 transition-colors duration-300">
                            
                            <!-- Thumbnail -->
                            <a href="<?php echo getEpisodeUrl($item['episode_slug'], $item['id']); ?>" class="block md:w-1/3 relative overflow-hidden shrink-0">
                                <img loading="lazy" class="w-full h-48 md:h-full object-cover transform group-hover:scale-105 transition-transform duration-500" 
                                     src="<?php echo htmlspecialchars(getOptimizedImage($item['thumbnail_url'])); ?>" alt="Thumbnail">
                                <div class="absolute inset-0 bg-black/20 group-hover:bg-transparent transition-colors"></div>
                            </a>

                            <!-- Details -->
                            <div class="p-6 flex flex-col justify-center flex-grow">
                                <div class="flex items-center justify-between mb-2">
                                    <a href="<?php echo getSeriesUrl($item['series_slug'], $item['series_id']); ?>" class="text-xs font-bold text-orange-400 uppercase tracking-wide hover:underline">
                                        <?php echo htmlspecialchars($item['series_title']); ?>
                                    </a>
                                </div>
                                
                                <h3 class="text-xl font-bold text-white mb-3 hover:text-orange-300 transition-colors">
                                    <a href="<?php echo getEpisodeUrl($item['episode_slug'], $item['id']); ?>">
                                        <?php echo htmlspecialchars($item['episode_title']); ?>
                                    </a>
                                </h3>
                                
                                <p class="text-sm text-gray-400 line-clamp-2 mb-4">
                                    <?php echo htmlspecialchars($item['description']); ?>
                                </p>
                                
                                <div class="mt-auto">
                                    <a href="<?php echo getEpisodeUrl($item['episode_slug'], $item['id']); ?>" class="inline-flex items-center text-sm font-medium text-white bg-gray-700 hover:bg-orange-600 px-4 py-2 rounded-lg transition-colors">
                                        Ara l-Episodju u d-Dettalji 
                                        <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div> <!-- keep last div closed -->

            </div>
        <?php endif; ?>
    </section>

</main>

<?php include 'footer_frontend.php'; ?>