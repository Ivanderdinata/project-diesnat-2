<?php
declare(strict_types=1);

define('API_REQUEST', true);
require_once dirname(__DIR__) . '/includes/functions.php';
require_once APP_ROOT . '/includes/csrf.php';
require_once APP_ROOT . '/includes/game.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_out(['ok' => false, 'error' => 'Metode tidak diizinkan.'], 405);
}

$token = (string) ($_GET['token'] ?? '');
$session = session_by_token($pdo, $token);

if (!$session) {
    json_out(['ok' => false, 'error' => 'Sesi tidak ditemukan.'], 404);
}

$total = session_total_questions($session);

if ($session['finished_at'] !== null) {
    json_out([
        'ok'       => true,
        'finished' => true,
        'result_url' => APP_URL . '/result.php?token=' . urlencode($session['token']),
    ]);
}

$question = current_question($pdo, $session);

if ($question === null) {
    // Soal hilang/nonaktif — selesaikan sesi dengan menyusuri sisa posisi.
    $session = finalize_session($pdo, $session['token']) ?? $session;
    json_out([
        'ok'       => true,
        'finished' => true,
        'result_url' => APP_URL . '/result.php?token=' . urlencode($session['token']),
    ]);
}

$duration = max(3, min(120, setting_int($pdo, 'question_duration', 15)));

// Tandai waktu kirim hanya sekali per soal.
if ($session['question_sent_at'] === null) {
    mark_question_sent($pdo, (int) $session['id']);
    $session = session_by_token($pdo, $session['token']);
}

$remainingMs = $duration * 1000;
if (!empty($session['question_sent_at'])) {
    $sentAt = new DateTimeImmutable((string) $session['question_sent_at']);
    $now    = new DateTimeImmutable('now');
    $elapsedMs = (int) round(($now->format('U.u') - $sentAt->format('U.u')) * 1000);
    $remainingMs = max(0, ($duration * 1000) - $elapsedMs);
}

$index = (int) $session['current_index'];

json_out([
    'ok'           => true,
    'finished'     => false,
    'token'        => $session['token'],
    'index'        => $index,
    'number'       => $index + 1,
    'total'        => $total,
    'duration'     => $duration,
    'remaining_ms' => $remainingMs,
    'score'        => (int) $session['total_score'],
    'correct'      => (int) $session['correct_count'],
    'question'     => [
        'id'       => (int) $question['id'],
        'text'     => (string) $question['question'],
        'options'  => [
            'A' => (string) $question['option_a'],
            'B' => (string) $question['option_b'],
            'C' => (string) $question['option_c'],
            'D' => (string) $question['option_d'],
        ],
    ],
]);
