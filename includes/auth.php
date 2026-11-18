<?php
declare(strict_types=1);

/**
 * Proteksi halaman admin. Include setelah functions.php + csrf.php.
 */

function admin_logged_in(): bool
{
    return !empty($_SESSION['admin_id']) && !empty($_SESSION['admin_username']);
}

function current_admin(): ?array
{
    return admin_logged_in()
        ? ['id' => (int) $_SESSION['admin_id'], 'username' => (string) $_SESSION['admin_username']]
        : null;
}

function require_admin(): void
{
    if (!admin_logged_in()) {
        flash_set('error', 'Silakan login terlebih dahulu.');
        redirect(APP_URL . '/admin/login.php');
    }
}

/**
 * Selesaikan login: regenerasi ID sesi, simpan identitas admin.
 */
function admin_login_session(array $admin): void
{
    session_regenerate_id(true);
    $_SESSION['admin_id']       = (int) $admin['id'];
    $_SESSION['admin_username'] = (string) $admin['username'];
    $_SESSION['admin_login_at'] = time();

    // Bersihkan token CSRF lama agar tidak dipakai ulang setelah login.
    unset($_SESSION['csrf_token']);
}

function admin_logout_session(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $params['path'],
            'domain'   => $params['domain'],
            'secure'   => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }
    session_destroy();
}
