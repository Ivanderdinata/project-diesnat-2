<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/functions.php';
require_once APP_ROOT . '/includes/csrf.php';
require_once APP_ROOT . '/includes/auth.php';

if (admin_logged_in()) {
    redirect(APP_URL . '/admin/dashboard.php');
}

$errors = [];
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $username = clean_text($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $ip       = client_ip();

    // Rate limit: maksimum 5 percobaan gagal per 5 menit per IP.
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM login_attempts
          WHERE ip_address = ? AND created_at > (NOW() - INTERVAL 5 MINUTE)'
    );
    $stmt->execute([$ip]);
    $recentFailures = (int) $stmt->fetchColumn();

    if ($recentFailures >= 5) {
        $errors['general'] = 'Terlalu banyak percobaan gagal. Coba lagi dalam 5 menit.';
    } elseif ($username === '' || $password === '') {
        $errors['general'] = 'Username dan password wajib diisi.';
    } else {
        $stmt = $pdo->prepare('SELECT id, username, password_hash FROM admins WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, (string) $admin['password_hash'])) {
            // Bersihkan riwayat gagal dan bereskan hash bila algoritma berubah.
            $pdo->prepare('DELETE FROM login_attempts WHERE ip_address = ?')->execute([$ip]);

            if (password_needs_rehash((string) $admin['password_hash'], PASSWORD_DEFAULT)) {
                $pdo->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')
                    ->execute([password_hash($password, PASSWORD_DEFAULT), (int) $admin['id']]);
            }

            admin_login_session($admin);
            flash_set('success', 'Selamat datang kembali, ' . $admin['username'] . '!');
            redirect(APP_URL . '/admin/dashboard.php');
        }

        $pdo->prepare('INSERT INTO login_attempts (ip_address, username) VALUES (?, ?)')
            ->execute([$ip, $username]);

        $errors['general'] = 'Username atau password salah.';
        // Jeda kecil untuk memperlambat brute force.
        usleep(400000);
    }
}

$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Mono:ital,wght@0,400;0,700;1,400;1,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(APP_URL) ?>/assets/css/style.css">
</head>
<body class="page-auth">
<div class="bg-orbs" aria-hidden="true"><span class="orb orb-1"></span><span class="orb orb-2"></span><span class="orb orb-3"></span></div>

<main class="site-main">
    <div class="auth-wrap">
        <div class="auth-card">
            <div class="auth-badge" aria-hidden="true">🔐</div>
            <h1>Login Admin</h1>
            <p class="sub">Masuk untuk mengelola soal, hasil, dan pengaturan kuis.</p>

            <?php foreach ($errors as $message): ?>
                <div class="alert alert-err"><span aria-hidden="true">⚠️</span><span><?= e($message) ?></span></div>
            <?php endforeach; ?>

            <form method="post" action="<?= e(APP_URL) ?>/admin/login.php" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

                <div class="field">
                    <label for="username">Username</label>
                    <input class="input" type="text" id="username" name="username" maxlength="50"
                           value="<?= e($username) ?>" required autocomplete="username" autofocus>
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <input class="input" type="password" id="password" name="password"
                           required autocomplete="current-password">
                </div>

                <button class="btn btn-block" type="submit">Masuk</button>
            </form>

            <div class="demo-hint">
                <strong>Akun demo:</strong> <code>admin</code> / <code>admin123</code><br>
                Segera ganti password di file <code>database.sql</code> atau lewat database sebelum dipakai publik.
            </div>
        </div>
        <p style="text-align:center;margin-top:18px">
            <a href="<?= e(APP_URL) ?>/index.php" style="color:var(--text-dim);font-size:.86rem">← Kembali ke beranda</a>
        </p>
    </div>
</main>

<script>window.APP_BASE = <?= json_encode(APP_URL) ?>;</script>
</body>
</html>
