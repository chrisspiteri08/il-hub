<?php
/*
 * Database Configuration
 *
 * Real credentials live in db_credentials.php, which is gitignored and
 * must be created manually on each environment (see db_credentials.example.php).
 */

require __DIR__ . '/db_credentials.php';

$dsn = "mysql:host=$db_host;dbname=$db_name;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (\PDOException $e) {
     // --- DEBUGGING: SIMPLIFIED ERROR ---
     // This will show a clear error if the connection fails.
     die("DATABASE CONNECTION FAILED: " . $e->getMessage());
     // --- END DEBUGGING ---
}
?>
