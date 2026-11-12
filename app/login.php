<?php
require __DIR__ . '/db.php';
require_method('POST');

$data       = read_input();
$login      = trim((string)($data['login'] ?? ''));
$password   = (string)($data['password'] ?? '');
$identifier = trim((string)($data['identifier'] ?? ''));

// Вход по логину и паролю
if ($login !== '' && $password !== '') {
    $row = query(
        'SELECT user_id, login, password, fullname, role FROM user WHERE login = ? LIMIT 1',
        [$login]
    )->fetch();

    if (!$row || !password_verify($password, $row['password'])) {
        json_response(['error' => 'Неверный логин или пароль'], 401);
    }

    unset($row['password']);
    json_response(['message' => 'Авторизация успешна', 'user' => $row]);
}

// Данные профиля
if ($identifier !== '') {
    $row = query('SELECT user_id, login, fullname FROM user WHERE login = ? LIMIT 1', [$identifier])->fetch();
    if (!$row) {
        json_response(['error' => 'Пользователь не найден'], 404);
    }
    json_response($row);
}

json_response(['error' => 'Ожидаются поля login и password или identifier'], 400);
