<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/db_config.php';
require dirname(__DIR__) . '/slug_helpers.php';

$dryRun = in_array('--dry-run', $argv, true);

function uniqueEpisodeSlug(string $candidate, array &$used): string
{
    $candidate = $candidate !== '' ? $candidate : 'episode';
    $slug = $candidate;
    $suffix = 2;

    while (isset($used[$slug])) {
        $suffixText = '-' . $suffix++;
        $slug = rtrim(substr($candidate, 0, 90 - strlen($suffixText)), '-') . $suffixText;
    }

    $used[$slug] = true;
    return $slug;
}

$episodes = $pdo->query(
    "SELECT e.id, e.title, e.slug, e.publish_date, e.published_at, e.created_at,
            s.title AS series_title
     FROM episodes e
     LEFT JOIN series s ON s.id = e.series_id
     ORDER BY e.id ASC"
)->fetchAll(PDO::FETCH_ASSOC);

$slugGroups = [];
foreach ($episodes as $episode) {
    $slugGroups[(string) $episode['slug']][] = (int) $episode['id'];
}

$targets = [];
foreach ($episodes as $episode) {
    $oldSlug = (string) $episode['slug'];
    $isDuplicate = count($slugGroups[$oldSlug]) > 1;
    $isInvalid = $oldSlug === '' || preg_match('/^[a-z0-9-]+$/', $oldSlug) !== 1;
    if ($isDuplicate || $isInvalid) {
        $targets[(int) $episode['id']] = $episode;
    }
}

$existingRedirects = [];
foreach ($pdo->query("SELECT old_slug FROM slug_redirects WHERE type = 'episode'")->fetchAll(PDO::FETCH_COLUMN) as $oldSlug) {
    $existingRedirects[(string) $oldSlug] = true;
}

$used = [];
foreach ($episodes as $episode) {
    if (!isset($targets[(int) $episode['id']]) && !empty($episode['slug'])) {
        $used[(string) $episode['slug']] = true;
    }
}
foreach (array_keys($existingRedirects) as $oldSlug) {
    $used[$oldSlug] = true;
}

$insertRedirect = $pdo->prepare(
    "INSERT INTO slug_redirects (type, old_slug, target_id) VALUES ('episode', ?, ?)"
);
$updateSlug = $pdo->prepare('UPDATE episodes SET slug = ? WHERE id = ?');
$redirectsInserted = 0;
$changed = 0;

$pdo->beginTransaction();
try {
    foreach ($slugGroups as $oldSlug => $ids) {
        if ($oldSlug === '' || isset($existingRedirects[$oldSlug])) {
            continue;
        }

        if (count($ids) > 1) {
            $insertRedirect->execute([$oldSlug, min($ids)]);
            $existingRedirects[$oldSlug] = true;
            $used[$oldSlug] = true;
            $redirectsInserted++;
        } elseif (isset($targets[$ids[0]])) {
            $insertRedirect->execute([$oldSlug, $ids[0]]);
            $existingRedirects[$oldSlug] = true;
            $used[$oldSlug] = true;
            $redirectsInserted++;
        }
    }

    foreach ($targets as $id => $episode) {
        $titleBase = make_slug((string) $episode['title']);
        $seriesBase = make_slug((string) ($episode['series_title'] ?? ''));
        $genericTitle = $titleBase !== '' && $titleBase === $seriesBase;
        $candidate = $titleBase !== '' ? $titleBase : ($seriesBase !== '' ? $seriesBase : 'episode');

        if ($genericTitle || isset($used[$candidate])) {
            $dateValue = $episode['publish_date'] ?: ($episode['published_at'] ?: $episode['created_at']);
            $date = $dateValue ? date('Y-m-d', strtotime($dateValue)) : (string) $id;
            $candidate = ($seriesBase !== '' ? $seriesBase : $candidate) . '-' . $date;
        }

        $newSlug = uniqueEpisodeSlug($candidate, $used);
        if ($newSlug !== (string) $episode['slug']) {
            $updateSlug->execute([$newSlug, $id]);
            $changed++;
        }
    }

    $duplicateCount = (int) $pdo->query(
        "SELECT COUNT(*) FROM (
            SELECT slug FROM episodes WHERE slug IS NOT NULL
            GROUP BY slug HAVING COUNT(*) > 1
        ) duplicates"
    )->fetchColumn();
    if ($duplicateCount !== 0) {
        throw new RuntimeException('Duplicate episode slugs remain; refusing to add the unique index.');
    }

    if ($dryRun) {
        $pdo->rollBack();
    } else {
        $pdo->commit();
    }
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $error;
}

$indexExists = (int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.statistics
     WHERE table_schema = DATABASE()
       AND table_name = 'episodes'
       AND index_name = 'uniq_episodes_slug'"
)->fetchColumn();

if (!$dryRun && $indexExists === 0) {
    $pdo->exec('ALTER TABLE episodes ADD UNIQUE INDEX uniq_episodes_slug (slug)');
}

echo "episodes_changed={$changed}\n";
echo "redirects_inserted={$redirectsInserted}\n";
echo "mode=" . ($dryRun ? 'dry_run' : 'apply') . "\n";
echo "unique_index=" . ($dryRun
    ? ($indexExists === 0 ? 'would_create' : 'already_present')
    : ($indexExists === 0 ? 'created' : 'already_present')) . "\n";
