<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/db_config.php';

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS slug_redirects (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        type ENUM('episode', 'series') NOT NULL,
        old_slug VARCHAR(512) NOT NULL,
        target_id INT NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        INDEX idx_slug_redirects_type_old_slug (type, old_slug)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

echo "slug_redirects ready\n";
