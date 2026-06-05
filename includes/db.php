<?php
// db.php - Database connection using PDO with secure settings
require_once __DIR__ . '/config.php';

try {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $exception) {
    http_response_code(500);
    echo '<h1>Database Connection Error</h1>';
    echo '<p>Please verify your database settings in includes/config.php.</p>';
    if (defined('IS_LOCAL_SERVER') && IS_LOCAL_SERVER) {
        echo '<p><strong>Local error:</strong> ' . htmlspecialchars($exception->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
    }
    exit;
}
