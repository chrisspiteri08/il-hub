<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/db_config.php';

$select = $pdo->query("SELECT e.id, e.title, e.media_url, e.platform, e.publish_date, e.external_id, s.title AS series_title
    FROM episodes e JOIN series s ON s.id = e.series_id
    WHERE e.platform IN ('NET On Demand', 'ONE Play')");
$updateExternal = $pdo->prepare('UPDATE episodes SET external_id = ? WHERE id = ? AND external_id IS NULL');
$updateTitle = $pdo->prepare('UPDATE episodes SET title = ? WHERE id = ?');
$externalCount = 0;
$titleCount = 0;

foreach ($select as $episode) {
    $externalId = null;
    if ($episode['platform'] === 'NET On Demand' && preg_match('#/play/(\d+)(?:$|[/?])#', $episode['media_url'], $match)) {
        $externalId = 'netondemand:' . $match[1];
    } elseif ($episode['platform'] === 'ONE Play' && preg_match('#/ep/(\d+)(?:/|$)#', $episode['media_url'], $match)) {
        $externalId = 'one_play:' . $match[1];
    }
    if ($externalId !== null && empty($episode['external_id'])) {
        $updateExternal->execute([$externalId, $episode['id']]);
        $externalCount += $updateExternal->rowCount();
    }
    if ($episode['platform'] === 'ONE Play'
        && trim((string)$episode['title']) === trim((string)$episode['series_title'])
        && !empty($episode['publish_date'])) {
        $title = $episode['series_title'] . ' - ' . date('d/m/Y', strtotime($episode['publish_date']));
        $updateTitle->execute([$title, $episode['id']]);
        $titleCount += $updateTitle->rowCount();
    }
}

echo "external_ids={$externalCount}\none_titles={$titleCount}\n";
