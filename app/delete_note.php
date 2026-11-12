<?php
require __DIR__ . '/db.php';
require_method('POST');

$data   = read_input();
$userId = require_int($data['user_id'] ?? null, 'user_id');

// Фронтенд может прислать как число, так и id из календаря вида "note-17"
$rawId  = preg_replace('/^note-/', '', (string)($data['note_id'] ?? ''));
$noteId = require_int($rawId, 'note_id');

$deleted = query('DELETE FROM note WHERE note_id = ? AND user_id = ?', [$noteId, $userId])->rowCount();

if ($deleted === 0) {
    json_response(['error' => 'Заметка не найдена'], 404);
}
json_response(['message' => 'Заметка удалена']);
