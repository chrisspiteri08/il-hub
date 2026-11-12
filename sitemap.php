<?php

header('Content-Type: application/xml; charset=utf-8');

$cacheFile = __DIR__ . '/cache/sitemap.xml';
$cacheLifetime = 3600;

if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < $cacheLifetime) {
    readfile($cacheFile);
    exit;
}

require_once __DIR__ . '/config_loader.php';

$baseUrl = 'https://www.il-hub.com';

function sitemapXmlEscape(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function sitemapUrl(string $baseUrl, string $type, string $slug): string
{
    return $baseUrl . '/' . $type . '/' . rawurlencode($slug);
}

function sitemapLastmod(?string $date): ?string
{
    if (empty($date)) {
        return null;
    }

    $timestamp = strtotime($date);
    return $timestamp === false ? null : gmdate('Y-m-d', $timestamp);
}

$tags = $pdo->query("SELECT slug FROM tags WHERE slug IS NOT NULL AND TRIM(slug) != '' ORDER BY slug ASC")->fetchAll(PDO::FETCH_ASSOC);

$series = $pdo->query(
    "SELECT s.slug,
            MAX(COALESCE(e.publish_date, e.published_at, e.created_at)) AS lastmod
     FROM series s
     LEFT JOIN episodes e ON e.series_id = s.id
     WHERE s.slug IS NOT NULL AND TRIM(s.slug) != ''
     GROUP BY s.id, s.slug
     ORDER BY s.slug ASC"
)->fetchAll(PDO::FETCH_ASSOC);

$episodes = $pdo->query(
    "SELECT slug, COALESCE(publish_date, published_at, created_at) AS lastmod
     FROM episodes
     WHERE slug IS NOT NULL AND TRIM(slug) != '' AND slug != 'test'
     ORDER BY id ASC"
)->fetchAll(PDO::FETCH_ASSOC);

$xml = new XMLWriter();
$xml->openMemory();
$xml->setIndent(true);
$xml->setIndentString('  ');
$xml->startDocument('1.0', 'UTF-8');
$xml->startElement('urlset');
$xml->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

$writeUrl = static function (string $location, ?string $lastmod = null) use ($xml): void {
    $xml->startElement('url');
    $xml->writeElement('loc', sitemapXmlEscape($location));
    if ($lastmod !== null) {
        $xml->writeElement('lastmod', sitemapXmlEscape($lastmod));
    }
    $xml->endElement();
};

$writeUrl($baseUrl . '/');

foreach ($tags as $tag) {
    $writeUrl(sitemapUrl($baseUrl, 'category', $tag['slug']));
}

foreach ($series as $item) {
    $writeUrl(
        sitemapUrl($baseUrl, 'series', $item['slug']),
        sitemapLastmod($item['lastmod'])
    );
}

foreach ($episodes as $episode) {
    $writeUrl(
        sitemapUrl($baseUrl, 'episode', $episode['slug']),
        sitemapLastmod($episode['lastmod'])
    );
}

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
