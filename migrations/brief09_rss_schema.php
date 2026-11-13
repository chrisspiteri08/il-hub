<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/db_config.php';

function brief09ColumnExists(PDO $pdo, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute(['episodes', $column]);
    return (int) $stmt->fetchColumn() > 0;
}

if (!brief09ColumnExists($pdo, 'external_id')) {
    $pdo->exec('ALTER TABLE episodes ADD COLUMN external_id VARCHAR(255) NULL AFTER media_url');
}
if (!brief09ColumnExists($pdo, 'audio_url')) {
    $pdo->exec('ALTER TABLE episodes ADD COLUMN audio_url TEXT NULL AFTER external_id');
}
if (!brief09ColumnExists($pdo, 'duration_seconds')) {
    $pdo->exec('ALTER TABLE episodes ADD COLUMN duration_seconds INT NULL AFTER audio_url');
}

$index = $pdo->query("SHOW INDEX FROM episodes WHERE Key_name = 'uniq_episodes_external_id'")->fetch(PDO::FETCH_ASSOC);
if (!$index) {
    $pdo->exec('CREATE UNIQUE INDEX uniq_episodes_external_id ON episodes (external_id)');
}

echo "brief09 RSS episode schema is ready.\n";
