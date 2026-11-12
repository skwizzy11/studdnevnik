<?php
// Проверка состояния: фронтенд опрашивает её раз в несколько секунд.
// Отвечает всегда, даже без базы: 200 — всё работает, 503 — бэкенд жив, но БД недоступна.
require __DIR__ . '/db.php';

$dbOk = false;
try {
    $pdo = db(false);
    if ($pdo !== null) {
        $pdo->query('SELECT 1');
        $dbOk = true;
    }
} catch (PDOException $e) {
    error_log('Health check: ' . $e->getMessage());
}

json_response([
    'status' => $dbOk ? 'ok' : 'db_unavailable',
    'db'     => $dbOk,
    'pod'    => gethostname(),
    'node'   => env('NODE_NAME', 'local'),
], $dbOk ? 200 : 503);
