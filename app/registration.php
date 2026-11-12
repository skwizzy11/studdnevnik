<?php
require __DIR__ . '/db.php';
require_method('POST');

$data     = read_input();
$email    = trim((string)($data['email'] ?? ''));
$password = (string)($data['password'] ?? '');

if ($email === '' || $password === '') {
    json_response(['error' => 'Заполните email и пароль'], 400);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(['error' => 'Неверный формат email'], 400);
}
if (mb_strlen($password) < 6) {
    json_response(['error' => 'Пароль должен быть не короче 6 символов'], 400);
}

if (query('SELECT 1 FROM user WHERE login = ? LIMIT 1', [$email])->fetchColumn()) {
    json_response(['error' => 'Пользователь с таким email уже существует'], 409);
}

// user_id выдаёт AUTO_INCREMENT, а не MAX(user_id) + 1: так нет гонки при
// одновременной регистрации на нескольких репликах бэкенда.
query(
    'INSERT INTO user (fullname, login, password, role) VALUES (?, ?, ?, ?)',
    ['', $email, password_hash($password, PASSWORD_DEFAULT), 'student']
);

json_response([
    'message' => 'Регистрация успешно завершена',
    'user'    => [
        'user_id'  => (int)db()->lastInsertId(),
        'login'    => $email,
        'fullname' => '',
        'role'     => 'student',
    ],
], 201);
