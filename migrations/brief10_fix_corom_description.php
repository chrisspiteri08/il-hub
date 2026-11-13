<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/db_config.php';

$description = 'College of Remote and Offshore Medicine (Malta) — conversations on remote, offshore and expedition medicine.';
$stmt = $pdo->prepare('UPDATE series SET description=? WHERE slug=?');
$stmt->execute([$description, 'corom-cast']);
echo 'CoROM description rows updated: ' . $stmt->rowCount() . PHP_EOL;
