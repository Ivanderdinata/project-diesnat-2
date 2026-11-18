<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once APP_ROOT . '/includes/csrf.php';
require_once APP_ROOT . '/includes/game.php';

$token = (string) ($_GET['token'] ?? ($_SESSION['quiz_token'] ?? ''));

if (!is_valid_token($token)) {
    flash_set('error', 'Sesi tidak valid.');
    redirect(APP_URL . '/index.php');
}

$session = session_by_token($pdo, $token);

if (!$session) {
    flash_set('error', 'Sesi tidak ditemukan.');
    redirect(APP_URL . '/index.php');
}

// Pastikan sesi benar-benar selesai sebelum ditampilkan.
if ($session['finished_at'] === null) {
    $session = finalize_session($pdo, $token);
    if (!$session) {
        redirect(APP_URL . '/index.php');
    }
}

// Sesi yang belum selesai tidak masuk leaderboard; tampilkan hanya bila selesai.
$totalQuestions = session_total_questions($session);
$correctCount   = (int) $session['correct_count'];
$wrongCount     = max(0, $totalQuestions - $correctCount);
$accuracy       = $totalQuestions > 0 ? round($correctCount / $totalQuestions * 100) : 0;
$totalTimeSec   = round((int) $session['total_time_ms'] / 1000, 1);
$averageSec     = $totalQuestions > 0 ? round($totalTimeSec / $totalQuestions, 1) : 0;

$rank = session_rank($pdo, $session);

// Best score per pemain (dipakai untuk pesan "skor terbaikmu").
$stmt = $pdo->prepare(
    'SELECT MAX(s.total_score)
       FROM quiz_sessions s
       JOIN players p ON p.id = s.player_id
      WHERE p.name = ? AND p.kelas = ? AND s.finished_at IS NOT NULL'
);
$stmt->execute([(string) $session['name'], (string) $session['kelas']]);
$bestScore = (int) $stmt->fetchColumn();

$isPersonalBest = (int) $session['total_score'] >= $bestScore;

$perSession = max(1, min(100, setting_int($pdo, 'questions_per_session', 10)));

if ($rank === 1) {
    $headline = 'Juara! Kamu nomor satu!';
    $emoji    = '👑';
    $subline  = 'Luar biasa! Kamu memimpin papan peringkat.';
} elseif ($rank <= 3) {
    $headline = 'Masuk podium!';
    $emoji    = '🏆';
    $subline  = 'Hebat! Kamu ada di tiga besar leaderboard.';
} elseif ($rank <= 10) {
    $headline = 'Masuk Top 10!';
    $emoji    = '🎖️';
    $subline  = 'Kerja bagus! Namamu tampil di leaderboard.';
} elseif ($accuracy >= 80) {
    $headline = 'Hampir sempurna!';
    $emoji    = '✨';
    $subline  = 'Akurasi tinggi — coba lagi untuk menembus Top 10.';
} elseif ($accuracy >= 50) {
    $headline = 'Permainan yang bagus!';
    $emoji    = '💪';
    $subline  = 'Terus berlatih untuk skor yang lebih tinggi.';
} else {
    $headline = 'Jangan menyerah!';
    $emoji    = '🌱';
    $subline  = 'Setiap percobaan membuatmu lebih cepat. Ayo coba lagi!';
}

$pageTitle   = 'Hasil — ' . setting($pdo, 'quiz_title', 'Quiz Dies Nat');
$bodyClass   = 'page-result';
$pageScripts = ['result.js'];
require APP_ROOT . '/includes/header.php';
?>

<div class="result-shell">
    <div class="result-card">
        <span class="result-emoji" aria-hidden="true"><?= $emoji ?></span>
        <h1 class="result-title"><?= e($headline) ?></h1>
        <p class="result-sub"><?= e($subline) ?></p>

        <div class="big-score" aria-label="Total skor"><?= number_format((int) $session['total_score'], 0, ',', '.') ?></div>
        <div class="big-score-label">Total Skor</div>

        <div class="rank-chip">
            <span aria-hidden="true">#</span>
            Peringkat <b><?= $rank ?></b>
        </div>

        <div class="result-grid">
            <div class="result-cell">
                <b><?= $correctCount ?> / <?= $totalQuestions ?></b>
                <span>Benar</span>
            </div>
            <div class="result-cell">
                <b><?= $accuracy ?>%</b>
                <span>Akurasi</span>
            </div>
            <div class="result-cell">
                <b><?= number_format($averageSec, 1, ',', '.') ?>s</b>
                <span>Rata-rata waktu</span>
            </div>
        </div>

        <p style="color:var(--text-dim);font-size:.86rem;margin-bottom:22px">
            <?= e((string) $session['name']) ?> · <?= e((string) $session['kelas']) ?>
            <?php if ($isPersonalBest): ?>
                — <span style="color:var(--gold);font-weight:700">Skor terbaikmu!</span>
            <?php endif; ?>
        </p>

        <div class="result-actions">
            <a class="btn btn-gold" href="<?= e(APP_URL) ?>/index.php#main">
                <span aria-hidden="true">🔁</span> Main Lagi
            </a>
            <a class="btn btn-ghost" href="<?= e(APP_URL) ?>/index.php#leaderboard">
                <span aria-hidden="true">🏆</span> Lihat Leaderboard
            </a>
        </div>
    </div>

    <div class="panel" style="margin-top:22px">
        <div class="panel-head">
            <h2>Ringkasan jawaban</h2>
            <span class="badge badge-info"><?= $totalQuestions ?> soal</span>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Soal</th>
                        <th>Jawabanmu</th>
                        <th>Kunci</th>
                        <th class="num">Waktu</th>
                        <th class="num">Skor</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $stmt = $pdo->prepare(
                    'SELECT a.question_id, a.answer, a.is_correct, a.time_taken_ms, a.score, q.question
                       FROM session_answers a
                       JOIN questions q ON q.id = a.question_id
                      WHERE a.quiz_session_id = ?
                      ORDER BY a.id'
                );
                $stmt->execute([(int) $session['id']]);
                $answers = $stmt->fetchAll();

                // Kunci jawaban boleh ditampilkan setelah sesi selesai.
                $keys = [];
                if ($answers) {
                    $in = implode(',', array_fill(0, count($answers), '?'));
                    $stmt = $pdo->prepare("SELECT id, correct_answer FROM questions WHERE id IN ($in)");
                    $stmt->execute(array_map(fn($row) => (int) $row['question_id'], $answers));
                    foreach ($stmt->fetchAll() as $row) {
                        $keys[(int) $row['id']] = (string) $row['correct_answer'];
                    }
                }

                foreach ($answers as $i => $row):
                    $isCorrect = (int) $row['is_correct'] === 1;
                ?>
                    <tr>
                        <td class="num"><?= $i + 1 ?></td>
                        <td class="q-preview"><?= e((string) $row['question']) ?></td>
                        <td>
                            <?php if ($row['answer'] === null): ?>
                                <span class="badge badge-off">Kosong</span>
                            <?php else: ?>
                                <span class="badge <?= $isCorrect ? 'badge-ok' : 'badge-err' ?>">
                                    <?= e((string) $row['answer']) ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge badge-key"><?= e($keys[(int) $row['question_id']] ?? '?') ?></span></td>
                        <td class="num"><?= number_format(((int) $row['time_taken_ms']) / 1000, 1, ',', '.') ?>s</td>
                        <td class="num" style="font-weight:700;color:<?= $isCorrect ? 'var(--ok)' : 'var(--text-dim)' ?>">
                            <?= (int) $row['score'] ?>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (!$answers): ?>
                    <tr><td colspan="6" style="text-align:center;color:var(--text-dim)">Tidak ada jawaban tercatat.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require APP_ROOT . '/includes/footer.php'; ?>
