<?php
require_once 'config_loader.php';

$slug = $_GET['slug'] ?? null;
$id = $_GET['id'] ?? null;

// Smart Fallback: If a slug is provided but no explicit ID, check if it ends with "-[ID]"
$fallback_id = null;
if ($slug && !$id) {
    // If it ends with a full date (e.g. -2026-05-17), do not treat the day as an ID
    if (!preg_match('/-\d{4}-\d{2}-\d{2}$/', $slug)) {
        if (preg_match('/-(\d+)$/', $slug, $matches)) {
            $fallback_id = (int)$matches[1];
        }
    }
}

// Ensure $fallback_id is set to an impossible database ID if none was found 
// to prevent SQL syntax errors in strict environments.
$fallback_id = $fallback_id ?? -1;

// Improved query to handle slug/id safely without repeated named parameter issues
try {
    $stmt = $pdo->prepare("
        SELECT ep.*, s.title as series_title, s.slug as series_slug
        FROM episodes ep
        JOIN series s ON ep.series_id = s.id
        WHERE ep.slug = :slug
           OR ep.id = :id
           OR ep.id = :fallback_id
        LIMIT 1
    ");
    $stmt->execute([
        'slug' => $slug, 
        'id' => $id,
        'fallback_id' => $fallback_id
    ]);
    $episode = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database Error.");
}

if (!$episode) { header("Location: /index.php"); exit; }

// Set dynamic SEO data for the header
$page_title = $episode['title'] . " | il-hub.com";
$meta_description = $episode['description'] ?? '';

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
                <!-- Processing Placeholder -->
                <div class="flex flex-col items-center justify-center h-full text-gray-500">
                    <svg class="w-16 h-16 mb-4 opacity-20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 00-2 2z"></path></svg>
                    <p class="italic">Il-video qed jiġi pproċessat...</p>
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
    <div class="bg-gray-800 p-8 rounded-3xl border border-gray-700 shadow-lg max-w-3xl">
        <p class="text-gray-300 leading-relaxed whitespace-pre-line"><?php echo htmlspecialchars($episode['description']); ?></p>
    </div>
</main>

<?php include 'footer_frontend.php'; ?>