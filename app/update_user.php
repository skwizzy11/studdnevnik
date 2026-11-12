<?php
require __DIR__ . '/db.php';
require_method('POST');

$data       = read_input();
$identifier = trim((string)($data['identifier'] ?? ''));
$fullname   = trim((string)($data['fullname'] ?? ''));

if ($identifier === '') {
    json_response(['error' => 'Не указан пользователь'], 400);
}

if (!query('SELECT 1 FROM user WHERE login = ? LIMIT 1', [$identifier])->fetchColumn()) {
    json_response(['error' => 'Пользователь не найден'], 404);
}

query('UPDATE user SET fullname = ? WHERE login = ?', [$fullname, $identifier]);
json_response(['message' => 'Профиль сохранён', 'fullname' => $fullname]);
