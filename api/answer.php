<?php
declare(strict_types=1);

define('API_REQUEST', true);
require_once dirname(__DIR__) . '/includes/functions.php';
require_once APP_ROOT . '/includes/csrf.php';
require_once APP_ROOT . '/includes/game.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'error' => 'Metode tidak diizinkan.'], 405);
}

csrf_require();

$input = request_input();
$token = (string) ($input['token'] ?? ($_SESSION['quiz_token'] ?? ''));
$session = session_by_token($pdo, $token);

if (!$session) {
    json_out(['ok' => false, 'error' => 'Sesi tidak ditemukan.'], 404);
}

if (!rate_limit('answer_' . $session['id'], 3, 1)) {
    json_out(['ok' => false, 'error' => 'Terlalu cepat. Tunggu sebentar.'], 429);
}

if ($session['finished_at'] !== null) {
    json_out([
        'ok'         => true,
        'finished'   => true,
        'result_url' => APP_URL . '/result.php?token=' . urlencode($session['token']),
    ]);
}

$answerRaw = $input['answer'] ?? null;
$answer    = is_string($answerRaw) ? strtoupper(trim($answerRaw)) : null;

if ($answer !== null && !in_array($answer, ANSWER_LABELS, true)) {
    json_out(['ok' => false, 'error' => 'Jawaban tidak valid.'], 422);
}

try {
    $result = submit_answer($pdo, $session, $answer);
} catch (RuntimeException $e) {
    json_out(['ok' => false, 'error' => $e->getMessage()], 409);
}

$updated = session_by_token($pdo, $session['token']);

json_out([
    'ok'             => true,
    'finished'       => $result['session_finished'],
    'is_correct'     => $result['is_correct'],
    'correct_answer' => $result['correct_answer'],
    'explanation'    => $result['explanation'],
    'timed_out'      => $result['timed_out'],
    'answered'       => $result['answered'],
    'gained'         => $result['score'],
    'score'          => $updated ? (int) $updated['total_score'] : (int) $session['total_score'],
    'correct'        => $updated ? (int) $updated['correct_count'] : (int) $session['correct_count'],
    'result_url'     => APP_URL . '/result.php?token=' . urlencode($session['token']),
]);
