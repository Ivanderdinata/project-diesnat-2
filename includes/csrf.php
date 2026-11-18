<?php
declare(strict_types=1);

/** Token CSRF: pembuat + pemeriksa. */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify(?string $token): bool
{
    return !empty($_SESSION['csrf_token'])
        && is_string($token)
        && hash_equals($_SESSION['csrf_token'], $token);
}

/** Wajibkan CSRF valid untuk request non-GET; jika gagal, kirim 419. */
function csrf_require(): void
{
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (!csrf_verify(is_string($token) ? $token : null)) {
        if (defined('API_REQUEST')) {
            json_out(['ok' => false, 'error' => 'Sesi kedaluwarsa. Muat ulang halaman.'], 403);
        }
        http_response_code(403);
        echo '<!doctype html><meta charset="utf-8"><title>403</title>'
            . '<p style="font-family:system-ui;padding:30px">403 — Token CSRF tidak valid. '
            . '<a href="javascript:history.back()">Kembali</a></p>';
        exit;
    }
}
