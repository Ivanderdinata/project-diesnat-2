<?php
declare(strict_types=1);

define('API_REQUEST', true);
require_once dirname(__DIR__) . '/includes/functions.php';
require_once APP_ROOT . '/includes/game.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_out(['ok' => false, 'error' => 'Metode tidak diizinkan.'], 405);
}

$rows = leaderboard_rows($pdo, 10);

$players = [];
$rank = 0;
foreach ($rows as $row) {
    $rank++;
    $players[] = [
        'rank'        => $rank,
        'name'        => (string) $row['name'],
        'kelas'       => (string) $row['kelas'],
        'score'       => (int) $row['total_score'],
        'time_ms'     => (int) $row['total_time_ms'],
    ];
}

$totalPlayers = (int) $pdo->query(
    "SELECT COUNT(DISTINCT CONCAT(name, '|', kelas))
       FROM quiz_sessions s JOIN players p ON p.id = s.player_id
      WHERE s.finished_at IS NOT NULL"
)->fetchColumn();

json_out([
    'ok'            => true,
    'players'       => $players,
    'total_players' => $totalPlayers,
    'updated_at'    => date('c'),
]);
