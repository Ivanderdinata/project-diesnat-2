<?php
declare(strict_types=1);

/**
 * Helper umum + bootstrap aplikasi.
 * Di-include paling awal oleh setiap halaman.
 */

// ------------------------------------------------------------
// Path & konfigurasi dasar
// ------------------------------------------------------------
define('APP_ROOT', dirname(__DIR__));

// Base URL aplikasi. Dihitung dari SCRIPT_NAME agar tetap benar di subfolder
// maupun saat dipasang lewat alias Apache.
if (!defined('APP_URL')) {
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $base   = rtrim(dirname($script), '/');

    // Halaman di admin/ dan api/ berada satu level di bawah root aplikasi.
    if (preg_match('#/(admin|api)$#', $base)) {
        $base = (string) preg_replace('#/(admin|api)$#', '', $base);
    }

    define('APP_URL', $base === '/' ? '' : $base);
}

if (!defined('APP_TIMEZONE')) {
    define('APP_TIMEZONE', 'Asia/Jakarta');
}
date_default_timezone_set(APP_TIMEZONE);

// Error handling: tampilkan hanya saat development.
$isDev = (getenv('QUIZ_DEV') === '1') || php_sapi_name() === 'cli';
ini_set('display_errors', $isDev ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

if (!is_dir(APP_ROOT . '/logs')) {
    @mkdir(APP_ROOT . '/logs', 0775, true);
}
ini_set('error_log', APP_ROOT . '/logs/php-error.log');

// ------------------------------------------------------------
// Sesi
// ------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE && PHP_SAPI !== 'cli') {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('QUIZSESS');
    session_start();
}

// ------------------------------------------------------------
// Header keamanan dasar (juga diatur .htaccess bila tersedia)
// ------------------------------------------------------------
if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
}

// ------------------------------------------------------------
// Koneksi database
// ------------------------------------------------------------
require_once APP_ROOT . '/config/database.php';

// ------------------------------------------------------------
// Helper output
// ------------------------------------------------------------

/** Escape untuk output HTML. */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Kirim respons JSON lalu keluar. */
function json_out(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Ambil body JSON atau form-data dari request. */
function request_input(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $data = json_decode((string) $raw, true);
        return is_array($data) ? $data : [];
    }
    return $_POST;
}

// ------------------------------------------------------------
// Pengaturan aplikasi (tabel settings)
// ------------------------------------------------------------
function settings_all(PDO $pdo): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach ($pdo->query('SELECT setting_key, setting_value FROM settings')->fetchAll() as $row) {
            $cache[$row['setting_key']] = $row['setting_value'];
        }
    }
    return $cache;
}

function setting(PDO $pdo, string $key, string $default = ''): string
{
    $all = settings_all($pdo);
    return $all[$key] ?? $default;
}

function setting_int(PDO $pdo, string $key, int $default): int
{
    $value = setting($pdo, $key, (string) $default);
    return is_numeric($value) ? (int) $value : $default;
}

function setting_set(PDO $pdo, string $key, string $value): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $stmt->execute([$key, $value]);
}

// ------------------------------------------------------------
// Skor
// ------------------------------------------------------------
/**
 * Skor jawaban benar: 500 + round((sisa_detik / durasi) * 500), maks 1000.
 */
function calculate_score(int $timeTakenMs, int $durationSec): int
{
    if ($durationSec <= 0) {
        return 500;
    }
    $remainingMs = max(0, ($durationSec * 1000) - $timeTakenMs);
    $speedBonus  = (int) round(($remainingMs / ($durationSec * 1000)) * 500);
    return 500 + max(0, min(500, $speedBonus));
}

// ------------------------------------------------------------
// Identitas & util
// ------------------------------------------------------------
function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

function generate_token(int $bytes = 32): string
{
    return bin2hex(random_bytes($bytes));
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

/** Validasi format token sesi permainan. */
function is_valid_token(string $token): bool
{
    return (bool) preg_match('/^[a-f0-9]{64}$/', $token);
}

/** Normalisasi teks: trim + rapatkan spasi. */
function clean_text(?string $value): string
{
    $value = trim((string) $value);
    return preg_replace('/\s+/u', ' ', $value) ?? '';
}

/** Panjang karakter (UTF-8) tanpa bergantung mbstring. */
function text_length(string $value): int
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($value, 'UTF-8');
    }
    return count(preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY) ?: []);
}

/** Rate limit sederhana berbasis file (tanpa tambahan tabel). */
function rate_limit(string $bucket, int $maxCalls, int $windowSec): bool
{
    $dir = APP_ROOT . '/storage/ratelimit';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $file = $dir . '/' . preg_replace('/[^a-z0-9_\-]/i', '_', $bucket) . '.json';
    $now  = time();
    $hits = [];

    if (is_file($file)) {
        $decoded = json_decode((string) @file_get_contents($file), true);
        if (is_array($decoded)) {
            $hits = array_values(array_filter($decoded, fn($t) => is_int($t) && $t > $now - $windowSec));
        }
    }

    if (count($hits) >= $maxCalls) {
        return false;
    }

    $hits[] = $now;
    @file_put_contents($file, json_encode($hits), LOCK_EX);
    return true;
}

/** Flash message sederhana. */
function flash_set(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flash_get(): ?array
{
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}
