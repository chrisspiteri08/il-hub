<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/db_config.php';
require dirname(__DIR__) . '/slug_helpers.php';

$dryRun = in_array('--dry-run', $argv, true);
$series = $pdo->query('SELECT id, title, slug FROM series ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);

$targets = [];
$used = [];
foreach ($series as $item) {
    $slug = (string) $item['slug'];
    $invalid = $slug === '' || preg_match('/^[a-z0-9-]+$/', $slug) !== 1;
    if ($invalid) {
        $targets[(int) $item['id']] = $item;
    } else {
        $used[$slug] = true;
    }
}

$existingRedirects = [];
foreach ($pdo->query("SELECT old_slug FROM slug_redirects WHERE type = 'series'")->fetchAll(PDO::FETCH_COLUMN) as $oldSlug) {
    $existingRedirects[(string) $oldSlug] = true;
    $used[(string) $oldSlug] = true;
}

$insertRedirect = $pdo->prepare(
    "INSERT INTO slug_redirects (type, old_slug, target_id) VALUES ('series', ?, ?)"
);
$updateSlug = $pdo->prepare('UPDATE series SET slug = ? WHERE id = ?');
$changed = 0;
$redirectsInserted = 0;

$pdo->beginTransaction();
try {
    foreach ($targets as $id => $item) {
        $oldSlug = (string) $item['slug'];
        if ($oldSlug !== '' && !isset($existingRedirects[$oldSlug])) {
            $insertRedirect->execute([$oldSlug, $id]);
            $existingRedirects[$oldSlug] = true;
            $used[$oldSlug] = true;
            $redirectsInserted++;
        }

        $base = make_slug((string) $item['title']);
        $base = $base !== '' ? $base : 'series-' . $id;
        $candidate = $base;
        $suffix = 2;
        while (isset($used[$candidate])) {
            $suffixText = '-' . $suffix++;
            $candidate = rtrim(substr($base, 0, 90 - strlen($suffixText)), '-') . $suffixText;
        }

        $used[$candidate] = true;
        if ($candidate !== $oldSlug) {
            $updateSlug->execute([$candidate, $id]);
            $changed++;
        }
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

echo "series_changed={$changed}\n";
echo "redirects_inserted={$redirectsInserted}\n";
echo "mode=" . ($dryRun ? 'dry_run' : 'apply') . "\n";
