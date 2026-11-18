<?php
declare(strict_types=1);

/**
 * Cafeteria — lobby eksplorasi bergaya Astro-Diner 77.
 *
 * Karakter astronot digerakkan WASD / panah di stasiun multi-zona:
 *   - Konter makanan & bar robot
 *   - Lounge pemandangan nebula
 *   - Sudut arcade (mesin kuis + mini-game)
 *   - Dek observasi rahasia di balik pintu sekat
 *
 * Interaksi:
 *   - Mesin kuis  → mulai kuis
 *   - Terminal papan skor → halaman leaderboard
 *   - Pintu keluar → beranda
 *
 * Data pemain asli (Top 10) dikirim ke engine lewat CAFE_CONFIG.
 */

require_once __DIR__ . '/includes/functions.php';
require_once APP_ROOT . '/includes/game.php';

$quizTitle = setting($pdo, 'quiz_title', 'Quiz Dies Nat');

$topRows = leaderboard_rows($pdo, 10);
$topPlayers = [];
foreach ($topRows as $i => $row) {
    $topPlayers[] = [
        'rank'  => $i + 1,
        'name'  => (string) $row['name'],
        'kelas' => (string) $row['kelas'],
        'score' => (int) $row['total_score'],
    ];
}

$totalPlayers = (int) $pdo->query(
    "SELECT COUNT(DISTINCT CONCAT(name, '|', kelas))
       FROM quiz_sessions s JOIN players p ON p.id = s.player_id
      WHERE s.finished_at IS NOT NULL"
)->fetchColumn();

$config = [
    'title' => $quizTitle,
    'urls'  => [
        'quiz'        => APP_URL . '/index.php?skip=1#main',
        'home'        => APP_URL . '/index.php?skip=1',
        'leaderboard' => APP_URL . '/leaderboard.php',
    ],
    'top'           => $topPlayers,
    'total_players' => $totalPlayers,
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#070812">
    <title>Astro-Diner 77 — <?= e($quizTitle) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&family=VT323&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(APP_URL) ?>/assets/css/cafeteria.css">
</head>
<body>

<div id="game-container">
    <canvas id="worldCanvas"></canvas>
    <div class="crt-overlay" aria-hidden="true"></div>

    <!-- HUD atas -->
    <div id="hud-top">
        <div class="pixel-box title-badge">
            <div class="game-title">ASTRO-DINER 77</div>
            <div class="zone-label">
                <span class="zone-dot"></span>
                <span id="zone-name">Dek Utama Kafetaria</span>
            </div>
        </div>

        <div class="hud-controls-right">
            <div class="pixel-box hud-credits">
                KREDIT: <span id="credit-count">50</span> ◈
            </div>
            <a class="pixel-btn hud-skip" href="<?= e(APP_URL) ?>/index.php?skip=1">
                BERANDA →
            </a>
            <button id="btn-audio" class="pixel-btn" type="button" title="Suara retro">
                🔊 SFX: ON
            </button>
        </div>
    </div>

    <!-- Minimap -->
    <div id="minimap-wrapper">
        <canvas id="minimapCanvas" width="168" height="120"></canvas>
        <div class="minimap-caption">RADAR STASIUN</div>
    </div>

    <!-- Petunjuk kontrol -->
    <div id="controls-hint" class="pixel-box">
        <div>Gerak: <span class="key-cap">W</span><span class="key-cap">A</span><span class="key-cap">S</span><span class="key-cap">D</span> / <span class="key-cap">PANAH</span></div>
        <div class="hint-line">Interaksi: <span class="key-cap">E</span> / <span class="key-cap">SPASI</span></div>
    </div>

    <!-- Kontrol sentuh -->
    <div id="touch-controls">
        <div class="dpad">
            <div></div>
            <button class="dpad-btn" data-dir="up" type="button" aria-label="Jalan ke atas">▲</button>
            <div></div>
            <button class="dpad-btn" data-dir="left" type="button" aria-label="Jalan ke kiri">◄</button>
            <div></div>
            <button class="dpad-btn" data-dir="right" type="button" aria-label="Jalan ke kanan">►</button>
            <div></div>
            <button class="dpad-btn" data-dir="down" type="button" aria-label="Jalan ke bawah">▼</button>
            <div></div>
        </div>
        <button id="touch-action-btn" class="pixel-btn touch-action" type="button">AKSI [E]</button>
    </div>

    <!-- Prompt interaksi -->
    <div id="interact-prompt" role="button" tabindex="0">
        <span class="key-cap">E</span>
        <span id="interact-prompt-text">Interaksi</span>
    </div>

    <!-- Kotak dialog retro -->
    <div id="dialogue-box" role="dialog" aria-live="polite" aria-label="Dialog">
        <div class="dialogue-header">
            <span id="dialogue-speaker">UNIT-01</span>
            <span class="dialogue-hint">[E / Klik untuk lanjut]</span>
        </div>
        <div id="dialogue-body">Halo penjelajah ruang angkasa!</div>
        <div class="dialogue-footer">
            <div id="dialogue-choices"></div>
            <button id="dialogue-close-btn" class="pixel-btn" type="button">TUTUP [ESC]</button>
        </div>
    </div>

    <!-- Modal mini-game arcade -->
    <div id="arcade-modal" role="dialog" aria-label="Mini game arcade">
        <div class="arcade-cabinet-frame">
            <div class="arcade-marquee" id="arcade-title">★ STAR BLASTER 1984 ★</div>
            <canvas id="arcadeCanvas" width="320" height="240"></canvas>
            <div class="arcade-help">
                Geser <span class="key-cap">◄</span> <span class="key-cap">►</span> &amp; tembak <span class="key-cap">SPASI</span>
            </div>
            <div class="arcade-controls-bar">
                <div class="arcade-controls-group">
                    <button class="pixel-btn" id="arc-left" type="button" aria-label="Geser kiri">◄</button>
                    <button class="pixel-btn arc-fire" id="arc-fire" type="button">TEMBAK</button>
                    <button class="pixel-btn" id="arc-right" type="button" aria-label="Geser kanan">►</button>
                </div>
                <button class="pixel-btn arc-exit" id="arcade-exit-btn" type="button">KELUAR</button>
            </div>
        </div>
    </div>

    <p class="cafe-sr" id="cafe-live" aria-live="polite"></p>
</div>

<script>
    window.CAFE_CONFIG = <?= json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="<?= e(APP_URL) ?>/assets/js/cafeteria.js" defer></script>
</body>
</html>
