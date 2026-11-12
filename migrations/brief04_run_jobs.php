<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if ($argc < 2 || !preg_match('/^\d+(?:,\d+)*$/', $argv[1])) {
    fwrite(STDERR, "Usage: php migrations/brief04_run_jobs.php 1,2,3\n");
    exit(2);
}
require dirname(__DIR__) . '/config_loader.php';
$_GET['key'] = $settings['cron_secret_key'];
$_GET['jobs'] = $argv[1];
require dirname(__DIR__) . '/cron_run_imports.php';
