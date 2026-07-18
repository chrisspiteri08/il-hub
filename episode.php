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
        SELECT ep.*, s.title as series_title, s.slug as series_slug, 
               t.clean_transcript as article_mt, t.clean_text_en as article_en, 
               t.summary as summary_mt, t.summary_en as summary_en,
               t.meta_title_mt, t.meta_desc_mt, t.h1_mt,
               t.meta_title_en, t.meta_desc_en, t.h1_en
        FROM episodes ep
        JOIN series s ON ep.series_id = s.id
        LEFT JOIN transcriptions t ON ep.id = t.episode_id
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

// Set dynamic SEO data for the header (Defaulting to English for search engines)
$page_title = !empty($episode['meta_title_en']) ? $episode['meta_title_en'] : $episode['title'] . " | il-hub.com";
$meta_description = !empty($episode['meta_desc_en']) ? $episode['meta_desc_en'] : ($episode['description'] ?? '');

include 'header_frontend.php';

function getYoutubeId($url) {
    preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $url, $match);
    return $match[1] ?? null;
}

/**
 * Supercharged Formatter: Converts Markdown to HTML, handles plain text,
 * and cleans up any database encoding issues.
 */
function format_article_content($text) {
    if (empty($text)) return '';
    
    // 1. Decode entities in case the DB safely encoded <p> as &lt;p&gt;
    $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    
    // 2. Convert Markdown headings to HTML
    $text = preg_replace('/^### (.*?)$/m', '<h3>$1</h3>', $text);
    $text = preg_replace('/^## (.*?)$/m', '<h2>$1</h2>', $text);
    // Sometimes AI uses bold for headings on a new line
    $text = preg_replace('/^\*\*([^\*]+)\*\*$/m', '<h2>$1</h2>', $text); 
    // Sometimes AI uses native HTML bold tags on a new line instead of Markdown
    $text = preg_replace('/^<(strong|b)>(.*?)<\/(strong|b)>$/m', '<h2>$2</h2>', $text);
    // Regular bold
    $text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text);
    
    // 3. Split by double newlines to isolate paragraphs and headings
    $blocks = preg_split('/\n\s*\n/', trim($text));
    $html = '';
    
    foreach ($blocks as $block) {
        $block = trim($block);
        if (empty($block)) continue;
        
        // If it's already an HTML heading, keep it clean
        if (preg_match('/^<h[1-6]>/', $block)) {
            $html .= $block . "\n";
        } 
        // If it already has <p> tags, just append it
        elseif (strpos($block, '<p>') !== false) {
            $html .= $block . "\n";
        }
        // Otherwise, it's raw text. Wrap in <p> and convert single newlines to <br>
        else {
            $html .= "<p>" . nl2br($block) . "</p>\n";
        }
    }
    
    // Inject Tailwind classes directly into the raw HTML tags so they look great
    $html = str_replace('<h2>', '<h2 class="text-2xl md:text-3xl font-bold text-orange-500 mt-10 mb-4">', $html);
    $html = str_replace('<h3>', '<h3 class="text-xl md:text-2xl font-bold text-orange-400 mt-8 mb-3">', $html);
    $html = str_replace('<p>', '<p class="mb-5 leading-relaxed text-gray-200">', $html);
    
    return $html;
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
            
            <!-- Dynamic H1 Titles -->
            <h1 id="title-mt" class="text-3xl md:text-5xl font-extrabold text-white leading-tight">
                <?php echo !empty($episode['h1_mt']) ? htmlspecialchars($episode['h1_mt']) : htmlspecialchars($episode['title']); ?>
            </h1>
            <h1 id="title-en" class="hidden text-3xl md:text-5xl font-extrabold text-white leading-tight">
                <?php echo !empty($episode['h1_en']) ? htmlspecialchars($episode['h1_en']) : htmlspecialchars($episode['title']); ?>
            </h1>
        </div>
    </div>

    <!-- Content Header & Language Switcher -->
    <div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
        <h2 class="text-2xl font-bold text-white flex items-center">
            <span class="w-2 h-8 bg-orange-500 rounded-full mr-4"></span>
            Dettalji tal-Episodju
        </h2>
        
        <div class="flex bg-gray-800 p-1.5 rounded-2xl border border-gray-700 w-fit">
            <button onclick="setLang('mt')" id="btn-mt" class="px-6 py-2.5 rounded-xl font-bold text-sm transition-all bg-orange-600 text-white shadow-lg">Malti</button>
            <button onclick="setLang('en')" id="btn-en" class="px-6 py-2.5 rounded-xl font-bold text-sm transition-all text-gray-400 hover:text-white">English</button>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-10">
        <!-- Article Body -->
        <div class="lg:col-span-2 space-y-8">
            <div id="content-mt" class="lang-content bg-gray-800/40 p-8 md:p-12 rounded-3xl border border-gray-700 leading-relaxed text-gray-200 text-lg shadow-inner">
                <?php if (!empty($episode['article_mt'])): ?>
                    <div class="prose prose-invert max-w-none prose-orange">
                        <?php echo format_article_content($episode['article_mt']); ?>
                    </div>
                <?php else: ?>
                    <div class="flex flex-col items-center py-12 text-center">
                        <div class="w-12 h-12 border-4 border-orange-500 border-t-transparent rounded-full animate-spin mb-4"></div>
                        <p class="text-gray-500 italic">L-artiklu bil-Malti qed jinkiteb bl-AI...</p>
                    </div>
                <?php endif; ?>
            </div>

            <div id="content-en" class="lang-content hidden bg-gray-800/40 p-8 md:p-12 rounded-3xl border border-gray-700 leading-relaxed text-gray-200 text-lg shadow-inner">
                <?php if (!empty($episode['article_en'])): ?>
                    <div class="prose prose-invert max-w-none prose-orange">
                        <?php echo format_article_content($episode['article_en']); ?>
                    </div>
                <?php else: ?>
                    <div class="flex flex-col items-center py-12 text-center">
                        <div class="w-12 h-12 border-4 border-gray-600 border-t-transparent rounded-full animate-spin mb-4"></div>
                        <p class="text-gray-500 italic">The English article is being prepared...</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sidebar Details -->
        <div class="space-y-8">
            <!-- Summary Box -->
            <div class="bg-gradient-to-br from-orange-600/20 to-red-600/10 border border-orange-500/30 p-8 rounded-3xl shadow-xl">
                <h3 class="text-orange-500 font-bold uppercase text-xs tracking-[0.2em] mb-4">Fil-Qosor</h3>
                <div id="sum-mt" class="text-white text-xl font-semibold leading-snug prose prose-invert prose-orange">
                    <?php 
                        // Decodes entities, removes all HTML tags, and converts standard line breaks
                        echo !empty($episode['summary_mt']) ? nl2br(strip_tags(html_entity_decode($episode['summary_mt'], ENT_QUOTES, 'UTF-8'))) : 'Il-punti ewlenin qed jiġu estratti...'; 
                    ?>
                </div>
                <div id="sum-en" class="text-white text-xl font-semibold leading-snug hidden prose prose-invert prose-orange">
                    <?php 
                        echo !empty($episode['summary_en']) ? nl2br(strip_tags(html_entity_decode($episode['summary_en'], ENT_QUOTES, 'UTF-8'))) : 'Main points are being extracted...'; 
                    ?>
                </div>
            </div>
            
            <!-- Description -->
            <div class="bg-gray-800 p-8 rounded-3xl border border-gray-700 shadow-lg">
                <h3 class="text-gray-500 font-bold uppercase text-xs tracking-widest mb-6 border-b border-gray-700 pb-4">Info Oriġinali</h3>
                <p class="text-gray-400 text-sm leading-relaxed whitespace-pre-line"><?php echo htmlspecialchars($episode['description']); ?></p>
            </div>
        </div>
    </div>
</main>

<script>
function setLang(lang) {
    // Toggle content blocks
    document.querySelectorAll('.lang-content').forEach(el => el.classList.add('hidden'));
    document.getElementById('content-' + lang).classList.remove('hidden');
    
    // Toggle summaries
    document.getElementById('sum-mt').classList.toggle('hidden', lang !== 'mt');
    document.getElementById('sum-en').classList.toggle('hidden', lang !== 'en');

    // Toggle H1 titles
    document.getElementById('title-mt').classList.toggle('hidden', lang !== 'mt');
    document.getElementById('title-en').classList.toggle('hidden', lang !== 'en');

    // Toggle button styles
    const btnMt = document.getElementById('btn-mt');
    const btnEn = document.getElementById('btn-en');
    
    if (lang === 'mt') {
        btnMt.className = 'px-6 py-2.5 rounded-xl font-bold text-sm transition-all bg-orange-600 text-white shadow-lg';
        btnEn.className = 'px-6 py-2.5 rounded-xl font-bold text-sm transition-all text-gray-400 hover:text-white';
    } else {
        btnEn.className = 'px-6 py-2.5 rounded-xl font-bold text-sm transition-all bg-orange-600 text-white shadow-lg';
        btnMt.className = 'px-6 py-2.5 rounded-xl font-bold text-sm transition-all text-gray-400 hover:text-white';
    }
}
</script>

<?php include 'footer_frontend.php'; ?>