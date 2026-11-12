<?php

function normalizeYouTubePlaylistId(string $input): string
{
    $input = trim($input);
    if (filter_var($input, FILTER_VALIDATE_URL)) {
        parse_str((string) parse_url($input, PHP_URL_QUERY), $query);
        $input = (string) ($query['list'] ?? '');
    }

    // Older valid playlist IDs can be only 13 characters long.
    if (!preg_match('/^[A-Za-z0-9_-]{10,}$/', $input)) {
        throw new InvalidArgumentException('Invalid YouTube playlist ID or URL.');
    }
    return $input;
}

function createAutomation(
    PDO $pdo,
    int $seriesId,
    string $jobType,
    string $importUrlOrId,
    array $tagIds,
    int $checkIntervalHours = 1
): int {
    $allowedTypes = ['youtube', 'youtube_channel', 'tvmi', 'one'];
    if ($seriesId < 1 || !in_array($jobType, $allowedTypes, true)) {
        throw new InvalidArgumentException('Invalid automation series or job type.');
    }

    $tagIds = array_values(array_unique(array_filter(array_map('intval', $tagIds))));
    if (!$tagIds) {
        throw new InvalidArgumentException('At least one valid tag is required.');
    }
    if ($jobType === 'youtube') {
        $importUrlOrId = normalizeYouTubePlaylistId($importUrlOrId);
    } elseif (trim($importUrlOrId) === '') {
        throw new InvalidArgumentException('An import URL or ID is required.');
    }
    $checkIntervalHours = max(1, min(168, $checkIntervalHours));

    $seriesCheck = $pdo->prepare('SELECT COUNT(*) FROM series WHERE id = ?');
    $seriesCheck->execute([$seriesId]);
    if ((int) $seriesCheck->fetchColumn() !== 1) {
        throw new InvalidArgumentException('Series does not exist.');
    }

    $existing = $pdo->prepare('SELECT id FROM automated_imports WHERE job_type = ? AND import_url_or_id = ? LIMIT 1');
    $existing->execute([$jobType, $importUrlOrId]);
    $existingId = $existing->fetchColumn();
    if ($existingId) {
        return (int) $existingId;
    }

    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) {
        $pdo->beginTransaction();
    }
    try {
        $stmt = $pdo->prepare('INSERT INTO automated_imports (series_id, job_type, import_url_or_id, check_interval_hours) VALUES (?, ?, ?, ?)');
        $stmt->execute([$seriesId, $jobType, $importUrlOrId, $checkIntervalHours]);
        $importId = (int) $pdo->lastInsertId();

        $tagStmt = $pdo->prepare('INSERT INTO import_tags (import_id, tag_id) VALUES (?, ?)');
        foreach ($tagIds as $tagId) {
            $tagStmt->execute([$importId, $tagId]);
        }
        if ($ownsTransaction) {
            $pdo->commit();
        }
        return $importId;
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
