<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/db_config.php';
require dirname(__DIR__) . '/slug_helpers.php';
require dirname(__DIR__) . '/automation_helpers.php';

$tags = [];
foreach ($pdo->query('SELECT id, name, slug FROM tags') as $tag) {
    $tags[$tag['slug']] = (int)$tag['id'];
    $tags[$tag['name']] = (int)$tag['id'];
}
$podcast = $tags['diskussjonijiet-u-podcasts'] ?? 0;
if (!$podcast) throw new RuntimeException('Diskussjonijiet u Podcasts tag is missing.');

$shows = [
    ["P.Q's", 'pqs', 379, "P.Q's, programm ta’ diskussjoni fuq NET Television.", $tags['politics'] ?? null],
    ['Leħen il-Bidwi u s-Sajjied', 'lehen-il-bidwi-u-s-sajjied', 64, "Leħen il-Bidwi u s-Sajjied, nhar ta' Ħamis fis-18:30.", $tags['taghrif-socjali'] ?? null],
];

$find = $pdo->prepare('SELECT id, slug FROM series WHERE main_url = ? OR slug = ? OR LOWER(title) = LOWER(?) ORDER BY main_url = ? DESC LIMIT 1');
$insert = $pdo->prepare('INSERT INTO series (title, description, main_url, series_image, slug) VALUES (?, ?, ?, ?, ?)');
$slugTaken = $pdo->prepare('SELECT COUNT(*) FROM series WHERE slug = ?');

foreach ($shows as [$title, $wantedSlug, $sourceId, $fallbackDescription, $secondaryTag]) {
    $url = 'https://netondemand.mt/series/' . $sourceId;
    $html = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 20, 'user_agent' => 'il-hub importer/1.0']]));
    $description = $fallbackDescription;
    $image = '';
    if ($html) {
        $doc = new DOMDocument(); @$doc->loadHTML($html);
        foreach ($doc->getElementsByTagName('meta') as $meta) {
            if ($meta->getAttribute('property') === 'og:description' && trim($meta->getAttribute('content')) !== '') $description = trim($meta->getAttribute('content'));
            if ($meta->getAttribute('property') === 'og:image') $image = trim($meta->getAttribute('content'));
        }
    }
    $find->execute([$url, $wantedSlug, $title, $url]);
    $series = $find->fetch(PDO::FETCH_ASSOC);
    if (!$series) {
        $slug = $wantedSlug; $base = $slug; $suffix = 2;
        while (true) { $slugTaken->execute([$slug]); if (!(int)$slugTaken->fetchColumn()) break; $slug = $base . '-' . $suffix++; }
        $insert->execute([$title, $description, $url, $image, $slug]);
        $series = ['id' => (int)$pdo->lastInsertId(), 'slug' => $slug];
    }
    $tagIds = array_values(array_unique(array_filter([$podcast, $secondaryTag])));
    $jobId = createAutomation($pdo, (int)$series['id'], 'netondemand', $url, $tagIds, 6);
    echo implode("\t", [(int)$series['id'], $jobId, $series['slug'], $title]) . PHP_EOL;
}
