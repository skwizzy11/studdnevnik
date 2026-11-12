<?php
// Заметка к задаче: создаёт новую или обновляет существующую.
// Пустой текст удаляет заметку.
require __DIR__ . '/db.php';
require_method('POST');

$data    = read_input();
$userId  = require_int($data['user_id'] ?? null, 'user_id');
$taskId  = require_int($data['task_id'] ?? null, 'task_id');
$content = trim((string)($data['content'] ?? ''));

$owns = query('SELECT 1 FROM task WHERE task_id = ? AND user_id = ?', [$taskId, $userId])->fetchColumn();
if (!$owns) {
    json_response(['error' => 'Событие не найдено'], 404);
}

if ($content === '') {
    query('DELETE FROM note WHERE task_id = ? AND user_id = ?', [$taskId, $userId]);
    json_response(['message' => 'Заметка удалена']);
}

$noteId = query('SELECT note_id FROM note WHERE task_id = ? AND user_id = ? LIMIT 1', [$taskId, $userId])
    ->fetchColumn();

if ($noteId) {
    query('UPDATE note SET content = ?, create_date = ? WHERE note_id = ?', [$content, client_now($data), $noteId]);
    json_response(['message' => 'Заметка обновлена', 'note_id' => (int)$noteId]);
}

query(
    "INSERT INTO note (task_id, user_id, title, content, create_date) VALUES (?, ?, '', ?, ?)",
    [$taskId, $userId, $content, client_now($data)]
);
json_response(['message' => 'Заметка сохранена', 'note_id' => (int)db()->lastInsertId()], 201);
