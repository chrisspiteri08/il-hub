<?php
/*
 * Database Configuration
 *
 * Real credentials live in db_credentials.php, which is gitignored and
 * must be created manually on each environment (see db_credentials.example.php).
 */

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     if (is_file(__DIR__ . '/db_credentials.php')) {
         require __DIR__ . '/db_credentials.php';
         $dsn = "mysql:host=$db_host;dbname=$db_name;charset=$charset";
         $pdo = new PDO($dsn, $db_user, $db_pass, $options);
     } else {
         // Safe local preview: never connects to or modifies production data.
         $demo_database = __DIR__ . '/demo.sqlite';
         $pdo = new PDO('sqlite:' . $demo_database, null, null, $options);
         require_once __DIR__ . '/local_demo_db.php';
         initializeLocalDemoDatabase($pdo);
     }
} catch (\PDOException $e) {
     error_log('Database connection failed: ' . $e->getMessage());
     http_response_code(500);
     die('The site is temporarily unavailable.');
}
?>
