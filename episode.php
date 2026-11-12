<?php
require_once 'config_loader.php';

$slug = $_GET['slug'] ?? null;
$id = $_GET['id'] ?? null;

if ($slug === 'test') {
    header('Location: /', true, 301);
    exit;
}

try {
    if ($slug !== null && $slug !== '') {
        $stmt = $pdo->prepare("SELECT ep.*, s.title AS series_title, s.slug AS series_slug FROM episodes ep JOIN series s ON ep.series_id = s.id WHERE ep.slug = ? LIMIT 1");
        $stmt->execute([$slug]);
    } elseif ($id !== null && ctype_digit((string)$id)) {
        $stmt = $pdo->prepare("SELECT ep.*, s.title AS series_title, s.slug AS series_slug FROM episodes ep JOIN series s ON ep.series_id = s.id WHERE ep.id = ? LIMIT 1");
        $stmt->execute([(int)$id]);
    } else {
        $stmt = null;
    }
    $episode = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;

    if ($episode && (!$slug || $slug !== $episode['slug'])) {
        header('Location: /episode/' . rawurlencode($episode['slug']), true, 301);
        exit;
    }

    if (!$episode && $slug) {
        $redirect_stmt = $pdo->prepare("SELECT ep.slug FROM slug_redirects sr JOIN episodes ep ON ep.id = sr.target_id WHERE sr.type = 'episode' AND sr.old_slug = ? ORDER BY sr.id ASC LIMIT 1");
        $redirect_stmt->execute([$slug]);
        $redirect_slug = $redirect_stmt->fetchColumn();
        if ($redirect_slug) {
            header('Location: /episode/' . rawurlencode($redirect_slug), true, 301);
            exit;
        }
    }
} catch (PDOException $e) {
    http_response_code(500);
    exit('Database Error.');
}

if (!$episode) {
    http_response_code(404);
    $page_title = 'Episodju ma nstabx | il-hub.com';
    $page_description = 'L-episodju li tlabt ma nstabx.';
    include 'header_frontend.php';
    echo '<main class="container mx-auto px-4 py-20 text-center"><h1 class="text-4xl font-extrabold text-white mb-4">Episodju ma nstabx</h1><p class="text-gray-400 mb-8">Jista’ jkun li l-link inbidel jew l-episodju tneħħa.</p><a class="text-orange-400 font-bold" href="/">Mur lura għall-paġna ewlenija</a></main>';
    include 'footer_frontend.php';
    exit;
}

// Set dynamic SEO data for the header
$page_title = $episode['title'] . " | il-hub.com";
$page_description = !empty(trim($episode['description'] ?? ''))
    ? mb_strimwidth(strip_tags($episode['description']), 0, 160, '...')
    : "Ara dan l-episodju fuq il-hub.com.";
$page_image = $episode['thumbnail_url'] ?? null;
$canonical_path = '/episode/' . rawurlencode($episode['slug']);

// Related episodes from the same series, with the newest first.
$related_stmt = $pdo->prepare("
    SELECT id, title, slug, thumbnail_url, media_url, publish_date
    FROM episodes
    WHERE series_id = :series_id AND id != :episode_id
      AND title NOT IN ('Deleted video', 'Private video')
    ORDER BY publish_date DESC
    LIMIT 4
");
$related_stmt->execute([
    'series_id' => $episode['series_id'],
    'episode_id' => $episode['id'],
]);
$related_episodes = $related_stmt->fetchAll(PDO::FETCH_ASSOC);

include 'header_frontend.php';

function getYoutubeId($url) {
    preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $url, $match);
    return $match[1] ?? null;
}
?>

<main class="container mx-auto px-4 py-8 max-w-6xl">
    
    <!-- Video Player Section -->
    <div class="bg-gray-900 rounded-3xl overflow-hidden shadow-2xl mb-10 border border-gray-800">
        <?php 
            $yt_id = getYoutubeId($episode['media_url']); 
            $is_tvmi = strpos($episode['media_url'], 'tvmi.mt') !== false;
        ?>
        <div class="aspect-video w-full bg-black">
            <?php if ($yt_id): ?>
                <!-- YouTube Player -->
                <iframe class="w-full h-full" src="https://www.youtube.com/embed/<?php echo $yt_id; ?>?rel=0&modestbranding=1" frameborder="0" allowfullscreen></iframe>
            
            <?php elseif ($is_tvmi): ?>
                <!-- TVMi Link Button -->
                <div class="flex flex-col items-center justify-center h-full text-gray-400 bg-gray-800">
                    <svg class="w-16 h-16 mb-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <p class="mb-5 font-semibold text-lg text-white">Dan l-episodju jinsab fuq TVMi</p>
                    <a href="<?php echo htmlspecialchars($episode['media_url']); ?>" target="_blank" class="bg-orange-600 hover:bg-orange-500 text-white px-8 py-3 rounded-full font-bold transition-all shadow-lg">
                        Ara fuq TVMi
                    </a>
                </div>

            <?php else: ?>
                <!-- Sources which cannot be embedded still get a useful detail page. -->
                <div class="flex flex-col items-center justify-center h-full text-gray-400 bg-gray-800 px-6 text-center">
                    <svg class="w-16 h-16 mb-4 opacity-20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 00-2 2z"></path></svg>
                    <p class="mb-5 font-semibold text-lg text-white">Dan l-episodju jinsab fuq sit ieħor</p>
                    <a href="<?php echo htmlspecialchars($episode['media_url']); ?>" target="_blank" rel="noopener noreferrer" class="bg-orange-600 hover:bg-orange-500 text-white px-8 py-3 rounded-full font-bold transition-all shadow-lg">
                        Iftaħ fis-Sors
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="p-6 md:p-8 border-t border-gray-800">
            <div class="flex flex-wrap items-center gap-3 mb-4">
                <a href="/series/<?php echo $episode['series_slug']; ?>" class="bg-orange-500/10 text-orange-500 px-4 py-1.5 rounded-full text-xs font-bold uppercase tracking-widest border border-orange-500/20 hover:bg-orange-500 hover:text-white transition-all">
                    <?php echo htmlspecialchars($episode['series_title']); ?>
                </a>
                <span class="text-gray-500 text-sm font-medium">
                    <?php echo date('d M Y', strtotime($episode['publish_date'])); ?>
                </span>
            </div>
            
            <h1 class="text-3xl md:text-5xl font-extrabold text-white leading-tight">
                <?php echo htmlspecialchars($episode['title']); ?>
            </h1>
        </div>
    </div>

    <!-- Content Header -->
    <div class="flex items-center mb-8">
        <h2 class="text-2xl font-bold text-white flex items-center">
            <span class="w-2 h-8 bg-orange-500 rounded-full mr-4"></span>
            Dettalji tal-Episodju
        </h2>
    </div>

    <!-- Description -->
    <?php if (!empty(trim($episode['description']))): ?>
    <div class="bg-gray-800 p-8 rounded-3xl border border-gray-700 shadow-lg max-w-3xl">
        <p class="text-gray-300 leading-relaxed whitespace-pre-line"><?php echo htmlspecialchars($episode['description']); ?></p>
    </div>
    <?php else: ?>
    <div class="bg-gray-800 p-8 rounded-3xl border border-gray-700 shadow-lg max-w-3xl">
        <p class="text-gray-500 italic">Deskrizzjoni mhux disponibbli għal dan l-episodju.</p>
    </div>
    <?php endif; ?>

    <?php if (!empty($related_episodes)): ?>
    <section class="mt-14" aria-labelledby="related-heading">
        <div class="flex items-center justify-between mb-6">
            <h2 id="related-heading" class="text-2xl font-bold text-white flex items-center">
                <span class="w-2 h-8 bg-orange-500 rounded-full mr-4"></span>
                Aktar minn <?php echo htmlspecialchars($episode['series_title']); ?>
            </h2>
            <a href="<?php echo getSeriesUrl($episode['series_slug'] ?? '', $episode['series_id']); ?>" class="text-sm font-semibold text-orange-400 hover:text-orange-300">Ara kollha &rarr;</a>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
            <?php foreach ($related_episodes as $related): ?>
                <a href="<?php echo getEpisodeUrl($related['slug'] ?? '', $related['id']); ?>" class="group bg-gray-800 rounded-2xl overflow-hidden border border-gray-700 hover:border-orange-500/60 transition-all">
                    <div class="aspect-video bg-gray-900 overflow-hidden">
                        <img loading="lazy" src="<?php echo htmlspecialchars($related['thumbnail_url']); ?>" alt="" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                    </div>
                    <div class="p-4">
                        <h3 class="font-bold text-white line-clamp-2 group-hover:text-orange-400 transition-colors"><?php echo htmlspecialchars($related['title']); ?></h3>
                        <?php if (!empty($related['publish_date'])): ?><p class="text-xs text-gray-500 mt-2"><?php echo date('d M Y', strtotime($related['publish_date'])); ?></p><?php endif; ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
</main>

<?php include 'footer_frontend.php'; ?>
