<?php
// Общий модуль для всех эндпоинтов: CORS, JSON-ответы и подключение к БД.
// Параметры БД берутся из переменных окружения, которые задают
// docker-compose.yaml или манифест Kubernetes (app-deployment.yaml).

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function json_response($data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function require_method(string $method): void
{
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        header("Allow: $method, OPTIONS");
        json_response(['error' => "Метод {$_SERVER['REQUEST_METHOD']} не поддерживается"], 405);
    }
}

// Тело запроса: JSON, а если его нет — обычные POST-поля.
function read_input(): array
{
    $data = json_decode(file_get_contents('php://input') ?: '', true);
    return is_array($data) ? $data : $_POST;
}

function env(string $name, ?string $default = null): ?string
{
    $value = getenv($name);
    return $value === false || $value === '' ? $default : $value;
}

// $fatal = false — при недоступной БД вернуть null вместо ответа 503
// (нужно проверке состояния, которая сама формирует ответ).
function db(bool $fatal = true): ?PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $host = env('DB_HOST', 'db');
    $port = env('DB_PORT', '3306');
    $name = env('DB_NAME', 'db1');
    $user = env('DB_USER', 'root');
    // В методичке переменная называется DB_PASS, в старом docker-compose — DB_PASSWORD.
    $pass = env('DB_PASS', env('DB_PASSWORD', ''));

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_TIMEOUT            => 3,
    ];

    // Подключаемся по TLS. В MySQL 8 / PXC пароль проверяется через
    // caching_sha2_password, и первый вход после перезапуска сервера или
    // ProxySQL без шифрования обрывается с «MySQL server has gone away».
    // Сертификат не проверяем: внутри кластера он самоподписанный.
    // Отключить можно переменной DB_SSL=0.
    if (env('DB_SSL', '1') !== '0') {
        $options[PDO::MYSQL_ATTR_SSL_CA] = '/etc/ssl/certs/ca-certificates.crt';
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
    }

    try {
        $pdo = new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4", $user, $pass, $options);
    } catch (PDOException $e) {
        error_log('DB connection failed: ' . $e->getMessage());
        if (!$fatal) {
            return null;
        }
        json_response(['error' => 'Нет подключения к базе данных'], 503);
    }

    return $pdo;
}

// Ошибки, которые означают «база сейчас недоступна», а не ошибку в запросе.
// На них отвечаем 503: фронтенд показывает баннер и сам повторяет запрос,
// когда кластер снова соберётся (после запуска ВМ или отказа ноды).
function db_unavailable(PDOException $e): bool
{
    return in_array($e->errorInfo[1] ?? null, [
        9001, // ProxySQL: нет доступного узла в hostgroup (Max connect timeout)
        1047, // Galera: узел ещё не готов принимать запросы (WSREP has not yet prepared node)
        1053, // сервер завершает работу
        2002, // не удалось подключиться
        2006, // MySQL server has gone away
        2013, // соединение потеряно во время запроса
    ], true);
}

// Обёртка над запросом: при ошибке SQL отвечает JSON-ом, а не падает с 500 без тела.
function query(string $sql, array $params = []): PDOStatement
{
    try {
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log('SQL error: ' . $e->getMessage() . ' | ' . $sql);
        if (db_unavailable($e)) {
            json_response(['error' => 'База данных временно недоступна'], 503);
        }
        json_response(['error' => 'Ошибка базы данных: ' . $e->getMessage()], 500);
    }
}

function require_int($value, string $field): int
{
    if (!is_numeric($value) || (int)$value <= 0) {
        json_response(['error' => "Некорректное поле $field"], 400);
    }
    return (int)$value;
}

// Принимает дату из формы (YYYY-MM-DDTHH:mm) или ISO 8601 от FullCalendar.
function parse_datetime(?string $value, string $field): ?string
{
    if ($value === null || trim($value) === '') {
        return null;
    }
    try {
        return (new DateTime($value))->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        json_response(['error' => "Неверный формат даты в поле $field"], 400);
    }
}

// Время создания заметки присылает браузер в местном времени пользователя:
// события в календаре тоже хранятся в местном времени, а часовой пояс
// контейнера с БД (обычно UTC) может отличаться.
function client_now(array $data): string
{
    return parse_datetime($data['created_at'] ?? null, 'created_at') ?? date('Y-m-d H:i:s');
}

function iso(?string $mysqlDatetime): ?string
{
    return $mysqlDatetime === null ? null : str_replace(' ', 'T', $mysqlDatetime);
}
