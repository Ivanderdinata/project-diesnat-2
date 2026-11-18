<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once APP_ROOT . '/includes/csrf.php';
require_once APP_ROOT . '/includes/game.php';

$quizTitle    = setting($pdo, 'quiz_title', 'Quiz Dies Nat');
$quizSubtitle = setting($pdo, 'quiz_subtitle', 'Jawab cepat, jawab tepat, jadilah juara!');

$duration   = max(3, min(120, setting_int($pdo, 'question_duration', 15)));
$perSession = max(1, min(100, setting_int($pdo, 'questions_per_session', 10)));

$activeCount  = (int) $pdo->query('SELECT COUNT(*) FROM questions WHERE is_active = 1')->fetchColumn();
$availableQs  = min($perSession, $activeCount);
$playerCount  = (int) $pdo->query(
    "SELECT COUNT(DISTINCT CONCAT(p.name, '|', p.kelas))
       FROM quiz_sessions s JOIN players p ON p.id = s.player_id
      WHERE s.finished_at IS NOT NULL"
)->fetchColumn();

$leaderboard = leaderboard_rows($pdo, 10);

$pageTitle = $quizTitle . ' — Kuis Interaktif';
$bodyClass = 'page-home';
require APP_ROOT . '/includes/header.php';
?>

<section class="hero">
    <div class="hero-copy">
        <span class="pill">🎮 Arcade Quiz</span>
        <h1 class="hero-title">
            Uji <span class="hl">kecepatan</span><br>dan ketepatanmu!
        </h1>
        <p class="hero-sub"><?= e($quizSubtitle) ?> Semakin cepat kamu menjawab dengan benar, semakin besar skor yang kamu kumpulkan.</p>

        <div class="hero-stats">
            <div class="stat-chip">
                <b><?= $availableQs ?></b>
                <span>Soal / sesi</span>
            </div>
            <div class="stat-chip">
                <b><?= $duration ?>s</b>
                <span>Waktu / soal</span>
            </div>
            <div class="stat-chip">
                <b><?= $playerCount ?></b>
                <span>Pemain</span>
            </div>
        </div>

        <ul class="sr-only">
            <li>Skor benar berkisar 500 sampai 1000 poin, tergantung kecepatan.</li>
            <li>Jawaban salah atau kehabisan waktu mendapat 0 poin.</li>
        </ul>
    </div>

    <div class="card start-card" id="main">
        <h2>Siap main?</h2>
        <p class="card-hint">Isi nama dan kelasmu, lalu tekan Mulai.</p>

        <?php if ($activeCount === 0): ?>
            <div class="alert alert-info">
                <span>ℹ️</span>
                <span>Belum ada soal aktif. Minta admin menambahkan soal terlebih dahulu.</span>
            </div>
        <?php endif; ?>

        <form id="start-form" novalidate autocomplete="off">
            <div class="field">
                <label for="player-name">Nama</label>
                <input class="input" type="text" id="player-name" name="name" maxlength="50"
                    placeholder="Contoh: Rani" required autocomplete="off" enterkeyhint="next">
                <span class="field-error" id="err-name"></span>
            </div>

            <div class="field">
                <label for="player-kelas">Kelas</label>
                <input class="input" type="text" id="player-kelas" name="kelas" maxlength="30"
                    placeholder="Contoh: XI RPL 1" required autocomplete="off" enterkeyhint="go">
                <span class="field-error" id="err-kelas"></span>
            </div>

            <button class="btn btn-gold btn-block" type="submit" id="start-btn" <?= $activeCount === 0 ? 'disabled' : '' ?>>
                <span>Mulai Kuis</span>
                <span aria-hidden="true">▶</span>
            </button>

            <p class="form-note">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                    <circle cx="12" cy="12" r="10" />
                    <path d="M12 8v5M12 16.5v.01" />
                </svg>
                <span>Nama dan kelas akan tampil di leaderboard. Gunakan nama asli agar mudah dikenali.</span>
            </p>
        </form>
    </div>
</section>

<div class="section-head" id="leaderboard">
    <div>
        <h2>🏆 Hall of Fame — Top 10</h2>
        <p>Satu pemain tampil sekali dengan skor terbaiknya.</p>
    </div>
    <span class="live-dot" id="live-indicator">Live</span>
</div>

<div class="podium" id="podium" aria-label="Tiga peringkat teratas">
    <?php
    $medals = [1 => '👑 1ST', 2 => '2ND 🥈', 3 => '3RD 🥉'];
    $icons  = [1 => 'military_tech', 2 => 'person', 3 => 'person'];
    foreach ([1, 2, 3] as $place):
        $row = $leaderboard[$place - 1] ?? null;
    ?>
        <div class="podium-card podium-<?= $place ?>">
            <span class="podium-tag"><?= $medals[$place] ?></span>
            <span class="podium-avatar" aria-hidden="true"><?= $place === 1 ? '👑' : '🎖️' ?></span>
            <?php if ($row): ?>
                <span class="podium-name"><?= e((string) $row['name']) ?></span>
                <span class="podium-kelas"><?= e((string) $row['kelas']) ?></span>
                <span class="podium-score"><?= number_format((int) $row['total_score'], 0, ',', '.') ?> PTS</span>
            <?php else: ?>
                <span class="podium-name">—</span>
                <span class="podium-kelas">Slot kosong</span>
                <span class="podium-score">0 PTS</span>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<div class="board" id="board">
    <?php if (!$leaderboard): ?>
        <div class="board-empty">
            <span class="emoji" aria-hidden="true">🎯</span>
            <p><strong>Belum ada pemain.</strong></p>
            <p>Jadilah yang pertama mencetak skor!</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="board-table">
                <thead>
                    <tr>
                        <th class="col-rank">#</th>
                        <th>Pemain</th>
                        <th class="col-time">Waktu</th>
                        <th class="col-score">Skor</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($leaderboard as $i => $row): ?>
                        <?php $rank = $i + 1; ?>
                        <tr class="rank-<?= $rank <= 3 ? $rank : 'n' ?>">
                            <td><span class="rank-badge"><?= $rank ?></span></td>
                            <td>
                                <div class="player-cell">
                                    <span class="player-name"><?= e($row['name']) ?></span>
                                    <span class="player-kelas"><?= e($row['kelas']) ?></span>
                                </div>
                            </td>
                            <td class="num col-time" style="color:var(--text-dim);font-size:.72rem">
                                <?= number_format(((int) $row['total_time_ms']) / 1000, 1, ',', '.') ?>s
                            </td>
                            <td class="col-score"><?= number_format((int) $row['total_score'], 0, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php
$pageScripts = ['leaderboard.js', 'home.js'];
require APP_ROOT . '/includes/footer.php';
?>