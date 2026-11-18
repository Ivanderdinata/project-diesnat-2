<?php
declare(strict_types=1);

if (!defined('APP_ROOT')) {
    require_once dirname(__DIR__) . '/includes/functions.php';
}

$pageTitle  = $pageTitle  ?? setting($pdo, 'quiz_title', 'Quiz Dies Nat');
$pageSub    = $pageSub    ?? '';
$bodyClass  = $bodyClass  ?? '';
$showNav    = $showNav    ?? true;
$csrfToken  = $showNav ? csrf_token() : '';
$quizTitle  = setting($pdo, 'quiz_title', 'Quiz Dies Nat');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0f131e">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="Kuis pilihan ganda interaktif bergaya arcade. Jawab cepat, raih skor tertinggi, dan masuk Top 10.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Mono:ital,wght@0,400;0,700;1,400;1,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(APP_URL) ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= e(APP_URL) ?>/assets/css/pixel-decor.css">
    <link rel="stylesheet" href="<?= e(APP_URL) ?>/assets/css/fx.css">
    <link rel="stylesheet" href="<?= e(APP_URL) ?>/assets/css/leaderboard.css">
</head>
<body class="<?= e($bodyClass) ?>" data-base="<?= e(APP_URL) ?>" data-csrf="<?= e($csrfToken) ?>">
<div class="bg-orbs" aria-hidden="true">
    <span class="orb orb-1"></span>
    <span class="orb orb-2"></span>
    <span class="orb orb-3"></span>
</div>
<?php
require_once APP_ROOT . '/includes/pixel-decor.php';
pixel_decor_layer();
?>
<?php if ($showNav): ?>
<header class="site-header">
    <div class="header-inner">
        <a class="brand" href="<?= e(APP_URL) ?>/cafeteria.php">
            <span class="brand-mark" aria-hidden="true">★</span>
            <span class="brand-text">
                <b><?= e($quizTitle) ?></b>
                <small>Arcade Quiz Edition</small>
            </span>
        </a>
        <nav class="site-nav" aria-label="Navigasi utama">
            <a class="nav-link" href="<?= e(APP_URL) ?>/cafeteria.php">🗺️ Cafeteria</a>
            <a class="nav-link" href="<?= e(APP_URL) ?>/index.php?skip=1">Beranda</a>
            <a class="nav-link" href="<?= e(APP_URL) ?>/leaderboard.php">Leaderboard</a>
            <a class="nav-link nav-link-accent" href="<?= e(APP_URL) ?>/index.php?skip=1#main">Main Sekarang</a>
        </nav>
    </div>
</header>
<?php endif; ?>
<main class="site-main">
