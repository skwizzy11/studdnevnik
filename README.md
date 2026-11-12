# СтудДневник

Учебный планировщик: календарь событий, заметки к событиям и общие заметки.
Работает в Kubernetes-кластере из трёх нод (master, worker1, worker2),
деплоится пайплайном Jenkins прямо из этого репозитория.

```
Браузер → 192.168.0.200 (MetalLB) → crud-frontend (nginx, worker1)
                                       └─ *.php → crud-api (PHP 8.3, worker2)
                                                    └─ db:33006 → ProxySQL → Percona XtraDB Cluster (3 узла)
```

## Структура

| Путь | Что это |
|---|---|
| `index.html`, `app/vendor/` | одностраничный фронтенд (FullCalendar встроен, без CDN) |
| `app/*.php` | API: вход, регистрация, события, заметки, `check_connection.php` |
| `dump/init.sql` | схема БД для PXC (первичные ключи, InnoDB) и демо-данные |
| `php.Dockerfile` | образ бэкенда `s2407773/crudback:arm64-v4` |
| `frontend.Dockerfile`, `nginx/` | образ фронтенда `s2407773/crudfront:arm64` |
| `mysql.Dockerfile` | образ MySQL 8 со схемой (для локального запуска) |
| `k8s/db-service.yaml` | сервис `db`: база доступна **только на порту 33006** |
| `k8s/backend.yaml` | бэкенд `crud-api`, предпочитает worker2 |
| `k8s/frontend.yaml` | фронтенд `crud-frontend` на worker1 + LoadBalancer 192.168.0.200 |
| `Jenkinsfile` | пайплайн деплоя |
| `jenkins/jenkins-pv.yaml` | постоянное хранилище для Jenkins на worker2 |
| `docker-compose.yaml` | локальный запуск: сайт :8080, phpMyAdmin :8081 |

Все образы собраны под **arm64** (Apple Silicon / Ubuntu arm64).

## Пайплайн Jenkins

Jenkins берёт `Jenkinsfile` и манифесты из этого репозитория
(*Pipeline script from SCM*) и раз в минуту проверяет его на новые коммиты:
после `git push` сборка запускается сама.

Порт, по которому приложение подключается к базе, задан в коде —
`DB_PORT` в `k8s/backend.yaml`. Сама база доступна только на 33006:

| DB_PORT в коде | Что происходит | Итог сборки |
|---|---|---|
| `33006` | база отвечает → деплоятся фронтенд и бэкенд, сквозная проверка `check_connection.php` | SUCCESS |
| `3306` | база на этом порту не отвечает → деплоится только фронтенд, бэкенд выключается (0 подов), сайт открывается с плашкой «База данных недоступна» | UNSTABLE |

Этапы: проверка кластера и чтение порта из кода → сервис `db` на 33006 →
проверка базы (DNS, endpoint'ы, кворум PXC, порт) → деплой фронтенда →
деплой или выключение бэкенда → финальная проверка.

Нужно в Jenkins: плагины Kubernetes, Kubernetes CLI, Docker Pipeline, Git
и учётные данные `kubeconfig-secret-id` (Secret file с kubeconfig кластера).

## Локальный запуск

```bash
docker compose -p studdnevnik up -d --build
```

Сайт: http://localhost:8080, демо-вход `demo@example.com` / `demo1234`.
