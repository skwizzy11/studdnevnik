<?php
require __DIR__ . '/db.php';
require_method('POST');

$data   = read_input();
$userId = require_int($data['user_id'] ?? null, 'user_id');
$taskId = require_int($data['task_id'] ?? null, 'task_id');

// Заметки к задаче удаляются каскадно (FOREIGN KEY ... ON DELETE CASCADE)
$deleted = query('DELETE FROM task WHERE task_id = ? AND user_id = ?', [$taskId, $userId])->rowCount();

if ($deleted === 0) {
    json_response(['error' => 'Событие не найдено'], 404);
}
json_response(['message' => 'Событие удалено']);
