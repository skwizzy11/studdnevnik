<?php
require __DIR__ . '/db.php';
require_method('GET');

$userId = require_int($_GET['user_id'] ?? null, 'user_id');
$start  = parse_datetime($_GET['start'] ?? null, 'start');
$end    = parse_datetime($_GET['end'] ?? null, 'end');
if ($start === null || $end === null) {
    json_response(['error' => 'Не указан диапазон дат'], 400);
}

// Задачи пользователя в диапазоне + привязанная к задаче заметка (если есть)
$rows = query(
    'SELECT t.task_id, t.title, t.description, t.start, t.end, n.content AS note_content
       FROM task t
       LEFT JOIN note n ON n.task_id = t.task_id AND n.user_id = ?
      WHERE t.user_id = ? AND t.start < ? AND COALESCE(t.end, t.start) >= ?
      ORDER BY t.start',
    [$userId, $userId, $end, $start]
)->fetchAll();

$events = array_map(fn($r) => [
    'id'            => 'task-' . $r['task_id'],
    'title'         => $r['title'],
    'start'         => iso($r['start']),
    'end'           => iso($r['end']),
    'extendedProps' => [
        'kind'        => 'task',
        'taskId'      => (int)$r['task_id'],
        'description' => $r['description'],
        'noteContent' => $r['note_content'],
    ],
], $rows);

json_response($events);
