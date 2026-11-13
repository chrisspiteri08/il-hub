<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/db_config.php';

function brief10ColumnType(PDO $pdo, string $table, string $column): ?string
{
    $stmt = $pdo->prepare('SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
    $stmt->execute([$table, $column]);
    $type = $stmt->fetchColumn();
    return $type === false ? null : strtolower((string) $type);
}

if (brief10ColumnType($pdo, 'episodes', 'media_kind') === null) {
    $pdo->exec("ALTER TABLE episodes ADD COLUMN media_kind VARCHAR(16) NULL AFTER duration_seconds");
}

$backfill = $pdo->exec("UPDATE episodes SET media_kind='audio' WHERE media_kind IS NULL AND (platform='Podcast' OR (audio_url IS NOT NULL AND TRIM(audio_url)!=''))");

$summaryType = brief10ColumnType($pdo, 'cron_logs', 'summary');
if ($summaryType !== 'mediumtext') {
    $pdo->exec('ALTER TABLE cron_logs MODIFY summary MEDIUMTEXT NULL');
}

echo "brief10 schema ready; audio rows backfilled: " . (int)$backfill . "; cron summary: " . brief10ColumnType($pdo, 'cron_logs', 'summary') . PHP_EOL;
