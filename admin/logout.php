<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/functions.php';
require_once APP_ROOT . '/includes/csrf.php';
require_once APP_ROOT . '/includes/auth.php';

// Simpan pesan sebelum sesi dihancurkan.
$wasLoggedIn = admin_logged_in();

admin_logout_session();

if ($wasLoggedIn) {
    session_start();
    flash_set('success', 'Kamu telah logout.');
}

redirect(APP_URL . '/admin/login.php');
