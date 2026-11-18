<?php
declare(strict_types=1);

/**
 * Koneksi database (PDO).
 * Kredensial bisa ditimpa lewat environment variable tanpa mengubah file ini.
 */
$dbHost = getenv('QUIZ_DB_HOST') ?: '127.0.0.1';
$dbPort = getenv('QUIZ_DB_PORT') ?: '3306';
$dbName = getenv('QUIZ_DB_NAME') ?: 'quiz_diesnat';
$dbUser = getenv('QUIZ_DB_USER') ?: 'root';
$dbPass = getenv('QUIZ_DB_PASS') ?: '';

$dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";

$pdoOptions = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::ATTR_STRINGIFY_FETCHES  => false,
];

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $pdoOptions);
} catch (PDOException $e) {
    error_log('[database] ' . $e->getMessage());

    if (defined('API_REQUEST') && API_REQUEST === true) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Database tidak dapat dihubungi.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (PHP_SAPI === 'cli') {
        throw $e;
    }

    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="id"><head><meta charset="utf-8"><title>Kesalahan Server</title></head><body style="font-family:system-ui;padding:40px">'
        . '<h1>Database tidak dapat dihubungi</h1>'
        . '<p>Periksa konfigurasi di <code>config/database.php</code> dan pastikan MySQL sedang berjalan.</p>'
        . '</body></html>';
    exit;
}
