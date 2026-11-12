<?php
require __DIR__ . '/db.php';
require_method('POST');

$data        = read_input();
$userId      = require_int($data['user_id'] ?? null, 'user_id');
$title       = trim((string)($data['title'] ?? ''));
$description = trim((string)($data['description'] ?? ''));
$start       = parse_datetime($data['start'] ?? null, 'start');
$end         = parse_datetime($data['end'] ?? null, 'end');

if ($title === '' || $start === null) {
    json_response(['error' => 'Укажите название и время начала'], 400);
}
if ($end !== null && $end < $start) {
    json_response(['error' => 'Время окончания не может быть раньше начала'], 400);
}

query(
    'INSERT INTO task (user_id, title, description, start, end) VALUES (?, ?, ?, ?, ?)',
    [$userId, $title, $description, $start, $end]
);

json_response(['message' => 'Событие добавлено', 'task_id' => (int)db()->lastInsertId()], 201);
