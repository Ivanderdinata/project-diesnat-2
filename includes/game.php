<?php
declare(strict_types=1);

/**
 * Logika inti permainan. Semua keputusan (waktu, kunci jawaban, skor)
 * dihitung di server.
 */

const ANSWER_LABELS = ['A', 'B', 'C', 'D'];

/**
 * Ambil satu sesi berdasarkan token, beserta data pemain.
 */
function session_by_token(PDO $pdo, string $token): ?array
{
    if (!is_valid_token($token)) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT s.*, p.name, p.kelas
           FROM quiz_sessions s
           JOIN players p ON p.id = s.player_id
          WHERE s.token = ?
          LIMIT 1'
    );
    $stmt->execute([$token]);
    $session = $stmt->fetch();

    return $session ?: null;
}

/**
 * Daftar id soal dalam urutan sesi.
 */
function session_question_ids(array $session): array
{
    $decoded = json_decode((string) $session['question_order'], true);
    return is_array($decoded) ? array_map('intval', $decoded) : [];
}

/**
 * Mulai sesi baru. Mengembalikan data sesi atau melempar RuntimeException.
 */
function start_quiz_session(PDO $pdo, string $name, string $kelas): array
{
    $limit = max(1, min(100, setting_int($pdo, 'questions_per_session', 10)));

    $ids = $pdo->query('SELECT id FROM questions WHERE is_active = 1')->fetchAll(PDO::FETCH_COLUMN);
    if (!$ids) {
        throw new RuntimeException('Belum ada soal aktif. Hubungi admin.');
    }

    shuffle($ids);
    $ids = array_slice(array_map('intval', $ids), 0, $limit);

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('INSERT INTO players (name, kelas) VALUES (?, ?)');
        $stmt->execute([$name, $kelas]);
        $playerId = (int) $pdo->lastInsertId();

        $token = generate_token(32);
        $stmt  = $pdo->prepare(
            'INSERT INTO quiz_sessions (player_id, token, question_order, current_index)
             VALUES (?, ?, ?, 0)'
        );
        $stmt->execute([$playerId, $token, json_encode($ids)]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    $session = session_by_token($pdo, $token);
    if (!$session) {
        throw new RuntimeException('Gagal membuat sesi.');
    }
    return $session;
}

/**
 * Soal aktif pada posisi current_index, atau null bila sesi selesai.
 * Kunci jawaban TIDAK ikut dikirim.
 */
function current_question(PDO $pdo, array $session): ?array
{
    $ids   = session_question_ids($session);
    $index = (int) $session['current_index'];

    if (!isset($ids[$index])) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT id, question, option_a, option_b, option_c, option_d
           FROM questions
          WHERE id = ? AND is_active = 1
          LIMIT 1'
    );
    $stmt->execute([$ids[$index]]);
    $question = $stmt->fetch();

    return $question ?: null;
}

/**
 * Tandai soal aktif dikirim sekarang (basis perhitungan waktu di server).
 */
function mark_question_sent(PDO $pdo, int $sessionId): void
{
    $stmt = $pdo->prepare(
        'UPDATE quiz_sessions SET question_sent_at = NOW(3) WHERE id = ? AND question_sent_at IS NULL'
    );
    $stmt->execute([$sessionId]);
}

/**
 * Proses jawaban. Semua validasi waktu & skor dilakukan di sini.
 *
 * @return array{is_correct: bool, correct_answer: string, score: int, explanation: ?string,
 *               timed_out: bool, session_finished: bool}
 */
function submit_answer(PDO $pdo, array $session, ?string $answer): array
{
    $duration = max(3, min(120, setting_int($pdo, 'question_duration', 15)));
    $graceMs  = 1000; // toleransi jaringan ±1 detik

    $ids   = session_question_ids($session);
    $index = (int) $session['current_index'];

    if (!isset($ids[$index])) {
        throw new RuntimeException('Sesi sudah selesai.');
    }

    $questionId = (int) $ids[$index];
    $stmt       = $pdo->prepare('SELECT * FROM questions WHERE id = ? LIMIT 1');
    $stmt->execute([$questionId]);
    $question = $stmt->fetch();

    if (!$question) {
        throw new RuntimeException('Soal tidak ditemukan.');
    }

    // Waktu dihitung dari jam server. Soal harus sudah dikirim lebih dulu
    // (lewat api/question.php) agar tidak bisa menjawab tanpa timer berjalan.
    if (empty($session['question_sent_at'])) {
        throw new RuntimeException('Soal belum dikirim. Muat ulang halaman.');
    }

    $sentAt    = new DateTimeImmutable((string) $session['question_sent_at']);
    $now       = new DateTimeImmutable('now');
    $elapsedMs = max(0, (int) round(($now->format('U.u') - $sentAt->format('U.u')) * 1000));

    $timedOut = $elapsedMs > ($duration * 1000) + $graceMs;

    // Validasi nilai jawaban: hanya A/B/C/D atau null (timeout/lewat).
    if ($answer !== null && !in_array($answer, ANSWER_LABELS, true)) {
        $answer = null;
    }
    if ($timedOut) {
        $answer = null;
    }

    $isCorrect = $answer !== null
        && strtoupper((string) $question['correct_answer']) === $answer;

    $score = $isCorrect ? calculate_score($elapsedMs, $duration) : 0;

    $sessionFinished = $index + 1 >= count($ids);

    $pdo->beginTransaction();
    try {
        // Satu soal hanya bisa dijawab sekali per sesi.
        $stmt = $pdo->prepare(
            'INSERT IGNORE INTO session_answers
                (quiz_session_id, question_id, answer, is_correct, time_taken_ms, score)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([(int) $session['id'], $questionId, $answer, $isCorrect ? 1 : 0, $elapsedMs, $score]);

        if ($stmt->rowCount() > 0) {
            $pdo->prepare(
                'UPDATE quiz_sessions
                    SET total_score   = total_score + ?,
                        correct_count = correct_count + ?,
                        total_time_ms = total_time_ms + ?,
                        current_index = ?,
                        question_sent_at = NULL,
                        finished_at   = IF(?, NOW(), NULL)
                  WHERE id = ?'
            )->execute([
                $score,
                $isCorrect ? 1 : 0,
                $elapsedMs,
                $index + 1,
                $sessionFinished ? 1 : 0,
                (int) $session['id'],
            ]);
        } else {
            // Duplikat: jangan menambah skor, tapi jangan kunci pemain selamanya.
            $pdo->prepare(
                'UPDATE quiz_sessions
                    SET current_index = ?,
                        question_sent_at = NULL,
                        finished_at   = IF(?, NOW(), NULL)
                  WHERE id = ?'
            )->execute([
                $index + 1,
                $sessionFinished ? 1 : 0,
                (int) $session['id'],
            ]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    return [
        'is_correct'       => $isCorrect,
        'correct_answer'   => (string) $question['correct_answer'],
        'score'            => $score,
        'explanation'      => $question['explanation'] !== null ? (string) $question['explanation'] : null,
        'timed_out'        => $timedOut,
        'session_finished' => $sessionFinished,
        'answered'         => $answer !== null,
        'elapsed_ms'       => $elapsedMs,
    ];
}

/**
 * Pastikan sesi sudah selesai (memanggil submit dengan null bila perlu)
 * lalu kembalikan data sesi terbaru.
 */
function finalize_session(PDO $pdo, string $token): ?array
{
    $session = session_by_token($pdo, $token);
    if (!$session) {
        return null;
    }

    $guard = 0;
    while ($session['finished_at'] === null && $guard < 200) {
        $guard++;
        $question = current_question($pdo, $session);

        if ($question === null) {
            // Soal tidak tersedia (mis. dihapus) — lewati posisinya.
            $pdo->prepare('UPDATE quiz_sessions SET current_index = current_index + 1 WHERE id = ?')
                ->execute([(int) $session['id']]);
        } else {
            if ($session['question_sent_at'] === null) {
                mark_question_sent($pdo, (int) $session['id']);
            }
            submit_answer($pdo, $session, null);
        }

        $session = session_by_token($pdo, $token);
        if (!$session) {
            return null;
        }
    }

    return $session;
}

/**
 * Peringkat global sesi (1 = terbaik) berdasarkan aturan tie-breaker.
 */
function session_rank(PDO $pdo, array $session): int
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) + 1
           FROM quiz_sessions s
          WHERE s.finished_at IS NOT NULL
            AND (
                  s.total_score > ?
               OR (s.total_score = ? AND s.total_time_ms < ?)
               OR (s.total_score = ? AND s.total_time_ms = ? AND s.finished_at < ?)
            )'
    );
    $stmt->execute([
        (int) $session['total_score'],
        (int) $session['total_score'],
        (int) $session['total_time_ms'],
        (int) $session['total_score'],
        (int) $session['total_time_ms'],
        (string) $session['finished_at'],
    ]);

    return (int) $stmt->fetchColumn();
}

/**
 * Skor terbaik tiap pemain (nama + kelas) untuk leaderboard.
 */
function leaderboard_rows(PDO $pdo, int $limit = 10): array
{
    $limit = max(1, min(100, $limit));

    // Satu baris per pemain (nama + kelas): sesi terbaik menurut aturan tie-breaker.
    $stmt = $pdo->prepare(
        'SELECT p.name, p.kelas, s.total_score, s.total_time_ms, s.finished_at, s.id AS session_id
           FROM quiz_sessions s
           JOIN players p ON p.id = s.player_id
          WHERE s.finished_at IS NOT NULL
            AND s.id = (
                SELECT s2.id
                  FROM quiz_sessions s2
                  JOIN players p2 ON p2.id = s2.player_id
                 WHERE s2.finished_at IS NOT NULL
                   AND p2.name = p.name
                   AND p2.kelas = p.kelas
                 ORDER BY s2.total_score DESC, s2.total_time_ms ASC, s2.finished_at ASC, s2.id ASC
                 LIMIT 1
            )
          ORDER BY s.total_score DESC, s.total_time_ms ASC, s.finished_at ASC, s.id ASC
          LIMIT ' . $limit
    );
    $stmt->execute();

    return $stmt->fetchAll();
}

/**
 * Jumlah soal pada satu sesi.
 */
function session_total_questions(array $session): int
{
    return count(session_question_ids($session));
}
