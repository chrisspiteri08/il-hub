<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

require_once 'config_loader.php';

$q = trim($_GET['q'] ?? '');
$result_type = $_GET['type'] ?? 'all';
if (!in_array($result_type, ['all', 'series', 'episodes'], true)) {
    $result_type = 'all';
}
$page_title = "Fittex: " . htmlspecialchars($q) . " - il-hub.com";

// Helper functions (same as index/series)
function translateTag($tag) {
    $map = [
        'politics' => 'Politika', 'sports' => 'Sports', 'lifestyle' => 'Lifestyle',
        'comedy' => 'Kummiedja', 'children' => 'Tfal', 'music' => 'Mużika minn Malta',
        'business' => 'Negozju', 'society' => 'Tagħrif Soċjali'
    ];
    return $map[strtolower($tag)] ?? ucfirst($tag);
}
if (!function_exists('getSeriesUrl')) {
    function getSeriesUrl($slug, $id) {
        if (!empty($slug)) return "/series/" . htmlspecialchars($slug);
        return "/series.php?series_id=" . $id;
    }
}
if (!function_exists('getCategoryUrl')) {
    function getCategoryUrl($tag, $slug = null) {
        return "/category/" . rawurlencode($slug ?: $tag);
    }
}

$series_results = [];
$episode_results = [];

if (!empty($q)) {
    $search_term = "%$q%";
    
    try {
        // 1. Search Series (Fix: Use unique placeholders :q1 and :q2)
        $stmt = $pdo->prepare("
            SELECT * FROM series 
            WHERE title LIKE :q1 OR description LIKE :q2
            ORDER BY title ASC LIMIT 6
        ");
        $stmt->execute(['q1' => $search_term, 'q2' => $search_term]);
        $series_results = $result_type === 'episodes' ? [] : $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. Search Episodes (Fix: Use unique placeholders :q1 and :q2)
        $stmt = $pdo->prepare("
            SELECT ep.*, s.title as series_title, s.slug as series_slug, s.id as series_id
            FROM episodes ep
            JOIN series s ON ep.series_id = s.id
            WHERE ep.title LIKE :q1 OR ep.description LIKE :q2
            ORDER BY ep.publish_date DESC LIMIT 12
        ");
        $stmt->execute(['q1' => $search_term, 'q2' => $search_term]);
        $episode_results = $result_type === 'series' ? [] : $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        // Handle error silently or log
    }
}

// Header nav data
$tags_list = $pdo->query("SELECT id, name, slug FROM tags ORDER BY display_order ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
$series_list = $pdo->query("SELECT id, title, slug FROM series ORDER BY title ASC")->fetchAll(PDO::FETCH_ASSOC);

include 'header_frontend.php';
?>

<main class="flex-grow container mx-auto px-4 sm:px-6 lg:px-8 py-12">

    <!-- Search Input Header -->
    <div class="max-w-3xl mx-auto text-center mb-16">
        <h1 class="text-3xl font-bold text-white mb-6">Fittex fil-Hub</h1>
        <form action="search.php" method="GET" class="relative">
            <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" 
                   class="w-full bg-gray-800 border border-gray-700 text-white rounded-full py-4 px-6 pl-12 focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500 transition-all shadow-lg"
                   placeholder="Fittex podcasts, mistiedna, jew suġġetti..." autofocus>
            <svg class="w-6 h-6 text-gray-400 absolute left-4 top-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            <button type="submit" class="absolute right-2 top-2 bg-orange-600 text-black rounded-full px-6 py-2 hover:bg-orange-700 transition-colors font-medium">
                Fittex
            </button>
        </form>
        <div class="flex flex-wrap justify-center gap-2 mt-5" aria-label="Iffiltra r-riżultati">
            <?php foreach (['all' => 'Kollha', 'series' => 'Programmi', 'episodes' => 'Episodji'] as $type_value => $type_label): ?>
                <a href="?q=<?php echo urlencode($q); ?>&type=<?php echo $type_value; ?>"
                   class="px-4 py-2 rounded-full text-sm font-semibold border transition-colors <?php echo $result_type === $type_value ? 'bg-orange-600 border-orange-500 text-black' : 'bg-gray-800 border-gray-700 text-gray-300 hover:border-orange-500/60 hover:text-white'; ?>">
                    <?php echo $type_label; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if (empty($q)): ?>
        <!-- State: No search term entered yet -->
        <div class="text-center text-gray-500 py-12">
            <p>It-tiftix it-tajjeb!</p>
        </div>
    
    <?php elseif (empty($series_results) && empty($episode_results)): ?>
        <!-- State: No results -->
        <div class="text-center py-12">
            <div class="inline-block p-4 rounded-full bg-gray-800 mb-4 text-gray-500">
                <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <h3 class="text-xl font-bold text-white mb-2">L-ebda riżultat misjub</h3>
            <p class="text-gray-400">Ipprova kliem differenti jew <a href="/suggest.php" class="text-orange-400 hover:underline">issuġġerixxi programm</a>.</p>
        </div>

    <?php else: ?>

        <!-- 1. Series Results -->
        <?php if (!empty($series_results)): ?>
            <div class="mb-12">
                <h2 class="text-2xl font-bold text-white mb-6 flex items-center">
                    <span class="w-2 h-8 bg-orange-500 rounded-full mr-4"></span>
                    Programmi u Serje
                </h2>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                    <?php foreach ($series_results as $series): ?>
                        <a href="<?php echo getSeriesUrl($series['slug'] ?? '', $series['id']); ?>" class="group block bg-gray-800 rounded-xl overflow-hidden hover:shadow-xl transition-all duration-300 border border-gray-700 hover:border-orange-500/50">
                            <div class="aspect-video overflow-hidden relative">
                                <?php $img = !empty($series['series_image']) ? $series['series_image'] : 'https://placehold.co/600x400/1f2937/7f9cf5?text=' . urlencode($series['title']); ?>
                                <img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($series['title']); ?>" class="w-full h-full object-cover transform group-hover:scale-105 transition-transform duration-500">
                                <div class="absolute inset-0 bg-black/20 group-hover:bg-black/10 transition-colors"></div>
                            </div>
                            <div class="p-4">
                                <h4 class="text-lg font-bold text-white group-hover:text-orange-400 transition-colors truncate"><?php echo htmlspecialchars($series['title']); ?></h4>
                                <p class="text-sm text-gray-500 mt-1">Ara l-episodji &rarr;</p>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- 2. Episode Results -->
        <?php if (!empty($episode_results)): ?>
            <div>
                <h2 class="text-2xl font-bold text-white mb-6 flex items-center">
                    <span class="w-2 h-8 bg-orange-500 rounded-full mr-4"></span>
                    Episodji
                </h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    <?php foreach ($episode_results as $item): ?>
                        <div class="bg-gray-800 rounded-lg shadow-lg overflow-hidden flex flex-col hover:shadow-xl transition-shadow duration-300 border border-gray-700">
                            <a href="<?php echo getEpisodeUrl($item['slug'] ?? '', $item['id']); ?>" class="block relative group/image h-48 overflow-hidden">
                                <img loading="lazy" class="h-full w-full object-cover transform group-hover/image:scale-105 transition-transform duration-500" src="<?php echo htmlspecialchars($item['thumbnail_url']); ?>" alt="Cover">
                                <div class="absolute inset-0 bg-black bg-opacity-0 group-hover/image:bg-opacity-20 transition-all duration-300 flex items-center justify-center">
                                    <svg class="w-12 h-12 text-white opacity-0 group-hover/image:opacity-100 transform scale-75 group-hover/image:scale-100 transition-all duration-300" fill="currentColor" viewBox="0 0 20 20"><path d="M6.3 2.841A1.5 1.5 0 004 4.11V15.89a1.5 1.5 0 002.3 1.269l9.344-5.89a1.5 1.5 0 000-2.538L6.3 2.84z"/></svg>
                                </div>
                            </a>
                            <div class="p-6 flex flex-col flex-grow">
                                <a href="<?php echo getSeriesUrl($item['series_slug'] ?? '', $item['series_id']); ?>" class="text-xs font-bold text-orange-400 mb-2 uppercase tracking-wide hover:underline">
                                    <?php echo htmlspecialchars($item['series_title']); ?>
                                </a>
                                <h4 class="text-lg font-bold text-white mb-2 line-clamp-2 hover:text-orange-300 transition-colors">
                                    <a href="<?php echo getEpisodeUrl($item['slug'] ?? '', $item['id']); ?>"><?php echo htmlspecialchars($item['title']); ?></a>
                                </h4>
                                <p class="text-gray-400 text-sm mb-4 flex-grow line-clamp-3">
                                    <?php echo mb_strimwidth(htmlspecialchars($item['description']), 0, 100, "..."); ?>
                                </p>
                                <a href="<?php echo getEpisodeUrl($item['slug'] ?? '', $item['id']); ?>" class="mt-auto w-full block bg-gray-700 hover:bg-orange-600 text-white hover:text-black font-medium py-2 px-4 rounded-lg text-center transition-colors text-sm">
                                    Ara d-Dettalji <svg class="w-4 h-4 ml-1 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m0 0H3"></path></svg>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

    <?php endif; ?>

</main>

<?php include 'footer_frontend.php'; ?>
