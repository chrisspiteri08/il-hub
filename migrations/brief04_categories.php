<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/db_config.php';
require dirname(__DIR__) . '/slug_helpers.php';

$column = $pdo->query("SHOW COLUMNS FROM tags LIKE 'slug'")->fetch(PDO::FETCH_ASSOC);
if (!$column) {
    $pdo->exec("ALTER TABLE tags ADD COLUMN slug VARCHAR(100) NULL AFTER name");
}

$pdo->beginTransaction();
try {
    $tags = $pdo->query('SELECT id, name, slug FROM tags ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $update = $pdo->prepare('UPDATE tags SET slug = ? WHERE id = ?');
    foreach ($tags as $tag) {
        $slug = $tag['name'] === 'tagħrif soċjali' ? 'taghrif-socjali' : make_slug($tag['name']);
        if ($tag['slug'] !== $slug) {
            $update->execute([$slug, $tag['id']]);
        }
    }

    $find = $pdo->prepare('SELECT id FROM tags WHERE name = ? LIMIT 1');
    $find->execute(['Diskussjonijiet u Podcasts']);
    $podcast_tag_id = $find->fetchColumn();
    if (!$podcast_tag_id) {
        $next_order = (int) $pdo->query('SELECT COALESCE(MAX(display_order), 0) + 1 FROM tags')->fetchColumn();
        $insert = $pdo->prepare('INSERT INTO tags (name, slug, display_order) VALUES (?, ?, ?)');
        $insert->execute(['Diskussjonijiet u Podcasts', 'diskussjonijiet-u-podcasts', $next_order]);
        $podcast_tag_id = $pdo->lastInsertId();
    } else {
        $update->execute(['diskussjonijiet-u-podcasts', $podcast_tag_id]);
    }

    $pdo->commit();
    echo 'podcast_tag_id=' . $podcast_tag_id . PHP_EOL;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

$index = $pdo->query("SHOW INDEX FROM tags WHERE Key_name = 'uniq_tags_slug'")->fetch(PDO::FETCH_ASSOC);
if (!$index) {
    $pdo->exec('ALTER TABLE tags ADD UNIQUE INDEX uniq_tags_slug (slug)');
}

echo "categories_ready=1\n";
