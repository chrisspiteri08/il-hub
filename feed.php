<?php

header('Content-Type: application/rss+xml; charset=utf-8');

$cacheFile = __DIR__ . '/cache/feed.xml';
$cacheLifetime = 900;

if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < $cacheLifetime) {
    readfile($cacheFile);
    exit;
}

require_once __DIR__ . '/config_loader.php';

$baseUrl = 'https://www.il-hub.com';

function feedEpisodeUrl(string $baseUrl, string $slug): string
{
    return $baseUrl . '/episode/' . rawurlencode($slug);
}

function feedDescription(?string $description): string
{
    $text = html_entity_decode(strip_tags((string) $description), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $lines = preg_split('/\R/u', $text) ?: [];
    $cleanLines = [];

    foreach ($lines as $line) {
        if (preg_match('/patreon|subscribe|follow\s+us|(?:^|\s)@[\p{L}\p{N}_.-]+/iu', $line)) {
            continue;
        }

        $line = preg_replace('~(?:https?://|www\.)\S+~iu', '', $line) ?? $line;
        $line = trim(preg_replace('/\s+/u', ' ', $line) ?? $line);
        if ($line !== '') {
            $cleanLines[] = $line;
        }
    }

    $clean = trim(implode(' ', $cleanLines));
    return mb_strimwidth($clean, 0, 300, '', 'UTF-8');
}

$episodes = $pdo->query(
    "WITH ranked AS (
        SELECT e.title,
               e.slug,
               e.description,
               e.thumbnail_url,
               e.publish_date AS publication_date,
               s.title AS series_title,
               ROW_NUMBER() OVER (PARTITION BY e.series_id ORDER BY e.publish_date DESC, e.id DESC) AS series_rank
        FROM episodes e
        JOIN series s ON s.id = e.series_id
        WHERE e.slug IS NOT NULL AND TRIM(e.slug) != ''
          AND e.title NOT IN ('Deleted video', 'Private video')
          AND e.publish_date BETWEEN DATE_SUB(NOW(), INTERVAL 30 DAY) AND NOW()
     )
     SELECT title, slug, description, thumbnail_url, publication_date, series_title
     FROM ranked
     WHERE series_rank <= 5
     ORDER BY publication_date DESC
     LIMIT 50"
)->fetchAll(PDO::FETCH_ASSOC);

$xml = new XMLWriter();
$xml->openMemory();
$xml->setIndent(true);
$xml->setIndentString('  ');
$xml->startDocument('1.0', 'UTF-8');
$xml->startElement('rss');
$xml->writeAttribute('version', '2.0');
$xml->writeAttribute('xmlns:media', 'http://search.yahoo.com/mrss/');
$xml->writeAttribute('xmlns:atom', 'http://www.w3.org/2005/Atom');
$xml->startElement('channel');
$xml->writeElement('title', 'il-hub.com: Podcasts u programmi Maltin');
$xml->writeElement('link', $baseUrl . '/');
$xml->writeElement('description', 'L-aħħar podcasts, diskussjonijiet u programmi Maltin fuq il-hub.com.');
$xml->writeElement('language', 'mt');
$xml->startElement('atom:link');
$xml->writeAttribute('href', $baseUrl . '/feed.xml');
$xml->writeAttribute('rel', 'self');
$xml->writeAttribute('type', 'application/rss+xml');
$xml->endElement();

foreach ($episodes as $episode) {
    $link = feedEpisodeUrl($baseUrl, $episode['slug']);
    $publicationTimestamp = strtotime($episode['publication_date'] ?? '') ?: time();

    $xml->startElement('item');
    $xml->writeElement('title', $episode['title'] . ' | ' . $episode['series_title']);
    $xml->writeElement('link', $link);
    $xml->startElement('guid');
    $xml->writeAttribute('isPermaLink', 'true');
    $xml->text($link);
    $xml->endElement();
    $xml->writeElement('pubDate', date(DATE_RSS, $publicationTimestamp));
    $xml->writeElement('description', feedDescription($episode['description']));

    if (!empty($episode['thumbnail_url'])) {
        $xml->startElement('media:thumbnail');
        $xml->writeAttribute('url', $episode['thumbnail_url']);
        $xml->endElement();
    }

    $xml->endElement();
}

$xml->endElement();
$xml->endElement();
$xml->endDocument();
$output = $xml->outputMemory();

if (is_dir(dirname($cacheFile)) && is_writable(dirname($cacheFile))) {
    $temporaryCache = $cacheFile . '.tmp.' . getmypid();
    if (file_put_contents($temporaryCache, $output, LOCK_EX) !== false) {
        rename($temporaryCache, $cacheFile);
    }
}

echo $output;
