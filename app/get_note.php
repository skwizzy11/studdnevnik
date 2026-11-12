<?php
require __DIR__ . '/db.php';
require_method('GET');

$userId = require_int($_GET['user_id'] ?? null, 'user_id');
$taskId = require_int($_GET['task_id'] ?? null, 'task_id');

$content = query('SELECT content FROM note WHERE task_id = ? AND user_id = ? LIMIT 1', [$taskId, $userId])
    ->fetchColumn();

json_response(['content' => $content === false ? null : $content]);
