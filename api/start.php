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

if (!rate_limit('start_' . client_ip(), 20, 60)) {
    json_out(['ok' => false, 'error' => 'Terlalu banyak permintaan. Coba lagi sebentar.'], 429);
}

$input = request_input();
$name  = clean_text($input['name'] ?? '');
$kelas = clean_text($input['kelas'] ?? '');

$errors = [];
if ($name === '' || text_length($name) < 2) {
    $errors['name'] = 'Nama minimal 2 karakter.';
} elseif (text_length($name) > 50) {
    $errors['name'] = 'Nama maksimal 50 karakter.';
}
if ($kelas === '' || text_length($kelas) < 1) {
    $errors['kelas'] = 'Kelas wajib diisi.';
} elseif (text_length($kelas) > 30) {
    $errors['kelas'] = 'Kelas maksimal 30 karakter.';
}

if ($errors) {
    json_out(['ok' => false, 'errors' => $errors], 422);
}

try {
    $session = start_quiz_session($pdo, $name, $kelas);
} catch (RuntimeException $e) {
    json_out(['ok' => false, 'error' => $e->getMessage()], 409);
}

$_SESSION['quiz_token'] = $session['token'];

json_out([
    'ok'        => true,
    'token'     => $session['token'],
    'play_url'  => APP_URL . '/play.php?token=' . urlencode($session['token']),
    'total'     => session_total_questions($session),
    'duration'  => max(3, min(120, setting_int($pdo, 'question_duration', 15))),
]);
