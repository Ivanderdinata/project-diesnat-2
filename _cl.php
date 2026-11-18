<?php
require_once __DIR__ . '/includes/functions.php';
$pdo->exec('DELETE FROM quiz_sessions');
$pdo->exec('DELETE FROM players');
echo 'sessions=' . $pdo->query('SELECT COUNT(*) FROM quiz_sessions')->fetchColumn()
    . ' players=' . $pdo->query('SELECT COUNT(*) FROM players')->fetchColumn()
    . ' questions=' . $pdo->query('SELECT COUNT(*) FROM questions')->fetchColumn() . PHP_EOL;
