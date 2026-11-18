<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once APP_ROOT . '/includes/csrf.php';
require_once APP_ROOT . '/includes/game.php';

$token = (string) ($_GET['token'] ?? ($_SESSION['quiz_token'] ?? ''));

if (!is_valid_token($token)) {
    flash_set('error', 'Sesi tidak valid. Silakan mulai kuis dari beranda.');
    redirect(APP_URL . '/index.php');
}

$session = session_by_token($pdo, $token);

if (!$session) {
    flash_set('error', 'Sesi tidak ditemukan. Silakan mulai lagi.');
    redirect(APP_URL . '/index.php');
}

if ($session['finished_at'] !== null) {
    redirect(APP_URL . '/result.php?token=' . urlencode($session['token']));
}

$total    = session_total_questions($session);
$duration = max(3, min(120, setting_int($pdo, 'question_duration', 15)));
$index    = (int) $session['current_index'];

// Jika soal pada posisi ini sudah tidak tersedia, selesaikan sesi.
if (current_question($pdo, $session) === null) {
    $finished = finalize_session($pdo, $token);
    if ($finished) {
        redirect(APP_URL . '/result.php?token=' . urlencode($token));
    }
}

$_SESSION['quiz_token'] = $token;

$pageTitle   = 'Bermain — ' . setting($pdo, 'quiz_title', 'Quiz Dies Nat');
$bodyClass   = 'page-play';
$pageScripts = ['game.js'];
require APP_ROOT . '/includes/header.php';
?>

<div class="play-shell"
     id="game"
     data-token="<?= e($token) ?>"
     data-total="<?= $total ?>"
     data-index="<?= $index ?>"
     data-duration="<?= $duration ?>"
     data-fresh="<?= $index === 0 && $session['question_sent_at'] === null ? '1' : '0' ?>"
     data-score="<?= (int) $session['total_score'] ?>"
     data-player="<?= e((string) $session['name']) ?>"
     data-kelas="<?= e((string) $session['kelas']) ?>">

    <div class="play-topline">
        <div class="play-meta">
            <span class="meta-chip">👤 <b><?= e((string) $session['name']) ?></b></span>
            <span class="meta-chip">🏫 <?= e((string) $session['kelas']) ?></span>
            <span class="meta-chip score">⭐ <b id="score-value"><?= number_format((int) $session['total_score'], 0, ',', '.') ?></b></span>
        </div>
        <div style="display:flex;gap:8px;align-items:center">
            <button type="button" class="icon-btn" id="mute-btn" aria-pressed="false"
                    title="Nyalakan/matikan suara" aria-label="Nyalakan atau matikan suara">
                <span id="mute-icon" aria-hidden="true">🔊</span>
            </button>
            <a class="icon-btn" href="<?= e(APP_URL) ?>/index.php"
               title="Keluar ke beranda" aria-label="Keluar ke beranda">
                <span aria-hidden="true">✕</span>
            </a>
        </div>
    </div>

    <div class="progress-wrap">
        <div class="progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="<?= $total ?>"
             aria-valuenow="<?= $index ?>" aria-label="Kemajuan kuis">
            <div class="progress-fill" id="progress-fill"
                 style="width: <?= $total > 0 ? round($index / $total * 100, 2) : 0 ?>%"></div>
        </div>
    </div>

    <div class="timer-row">
        <div class="timer-ring" id="timer-ring">
            <svg width="58" height="58" viewBox="0 0 58 58" aria-hidden="true">
                <circle class="ring-bg" cx="29" cy="29" r="25" fill="none" stroke-width="6"></circle>
                <circle class="ring-fg" id="ring-fg" cx="29" cy="29" r="25" fill="none" stroke-width="6"
                        stroke-dasharray="157.08" stroke-dashoffset="0"></circle>
            </svg>
            <span class="timer-num" id="timer-num"><?= $duration ?></span>
        </div>
        <div class="timer-bar-track" aria-hidden="true">
            <div class="timer-bar" id="timer-bar"></div>
        </div>
        <span class="sr-only" aria-live="polite" id="timer-live"></span>
    </div>

    <div class="q-card" id="q-card">
        <div class="q-eyebrow">
            <span>Stage <span id="q-number"><?= $index + 1 ?></span> / <span id="q-total"><?= $total ?></span></span>
        </div>
        <h1 class="q-text" id="q-text">Bersiap…</h1>

        <div class="answers" id="answers" role="group" aria-label="Pilihan jawaban"></div>

        <div class="loading" id="loading">
            <span class="spinner" aria-hidden="true"></span>
            <span>Menyiapkan soal pertama…</span>
        </div>

        <div class="feedback" id="feedback" hidden></div>
    </div>
</div>

<div class="countdown" id="countdown" hidden aria-live="assertive">
    <span class="countdown-num" id="countdown-num">3</span>
</div>

<noscript>
    <div class="card" style="margin-top:20px">
        JavaScript diperlukan untuk memainkan kuis ini. Aktifkan JavaScript lalu muat ulang halaman.
    </div>
</noscript>

<?php require APP_ROOT . '/includes/footer.php'; ?>
