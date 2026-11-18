<?php
declare(strict_types=1);

/** Layout admin: bagian atas. Variabel: $adminPage, $adminTitle. */

if (!defined('APP_ROOT')) {
    require_once dirname(__DIR__, 2) . '/includes/functions.php';
}

$adminPage  = $adminPage  ?? '';
$adminTitle = $adminTitle ?? 'Dashboard';
$flash      = flash_get();

$navItems = [
    'dashboard' => ['dashboard.php', '📊', 'Dashboard'],
    'questions' => ['questions.php', '📝', 'Soal'],
    'results'   => ['results.php', '🎯', 'Hasil Bermain'],
    'settings'  => ['settings.php', '⚙️', 'Pengaturan'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($adminTitle) ?> — Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Mono:ital,wght@0,400;0,700;1,400;1,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(APP_URL) ?>/assets/css/style.css">
</head>
<body class="page-admin" data-base="<?= e(APP_URL) ?>">
<div class="bg-orbs" aria-hidden="true"><span class="orb orb-1"></span><span class="orb orb-2"></span></div>

<header class="site-header">
    <div class="header-inner">
        <a class="brand" href="<?= e(APP_URL) ?>/admin/dashboard.php">
            <span class="brand-mark" aria-hidden="true">★</span>
            <span class="brand-text">
                <b>Admin Panel</b>
                <small><?= e(setting($pdo, 'quiz_title', 'Quiz Dies Nat')) ?></small>
            </span>
        </a>
        <nav class="site-nav" aria-label="Navigasi admin">
            <span class="nav-link" style="cursor:default">👤 <?= e((string) ($_SESSION['admin_username'] ?? '')) ?></span>
            <a class="nav-link" href="<?= e(APP_URL) ?>/index.php?skip=1" target="_blank" rel="noopener">Lihat Situs</a>
            <a class="nav-link nav-link-accent" href="<?= e(APP_URL) ?>/admin/logout.php"
               onclick="return confirm('Keluar dari panel admin?')">Logout</a>
        </nav>
    </div>
</header>

<main class="site-main">
<?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] === 'error' ? 'err' : ($flash['type'] === 'success' ? 'ok' : 'info') ?>">
        <span aria-hidden="true"><?= $flash['type'] === 'error' ? '⚠️' : '✅' ?></span>
        <span><?= e($flash['message']) ?></span>
    </div>
<?php endif; ?>

<div class="admin-shell">
    <aside class="admin-side">
        <div class="admin-side-title">Menu</div>
        <nav class="admin-nav" aria-label="Menu admin">
            <?php foreach ($navItems as $key => [$file, $icon, $label]): ?>
                <a href="<?= e(APP_URL) ?>/admin/<?= e($file) ?>" class="<?= $adminPage === $key ? 'active' : '' ?>">
                    <span aria-hidden="true"><?= $icon ?></span> <?= e($label) ?>
                </a>
            <?php endforeach; ?>
            <div class="sep"></div>
            <a href="<?= e(APP_URL) ?>/index.php?skip=1" target="_blank" rel="noopener">
                <span aria-hidden="true">🌐</span> Buka Website
            </a>
        </nav>
    </aside>
    <div class="admin-main">
