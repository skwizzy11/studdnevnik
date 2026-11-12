<?php
require __DIR__ . '/db.php';
require_method('POST');

$data    = read_input();
$userId  = require_int($data['user_id'] ?? null, 'user_id');
$content = trim((string)($data['content'] ?? ''));

if ($content === '') {
    json_response(['error' => 'Заметка не может быть пустой'], 400);
}

query(
    "INSERT INTO note (user_id, task_id, title, content, create_date) VALUES (?, NULL, '', ?, ?)",
    [$userId, $content, client_now($data)]
);

json_response(['message' => 'Заметка добавлена', 'note_id' => (int)db()->lastInsertId()], 201);
