-- Схема и данные СтудДневника для MySQL 8 / Percona XtraDB Cluster.
--
-- Файл кладётся в образ БД как /docker-entrypoint-initdb.d/init.sql:
--   * локально (docker compose) его выполняет сам MySQL при первом запуске;
--   * в Kubernetes его применяет Job db-loader из методички.
-- Имя базы здесь не указывается: скрипт выполняется в той базе, которую
-- выбрал запускающий (MYSQL_DATABASE или DB_NAME в db-loader.yaml).
--
-- Отличия от старого дампа (db1.sql):
--   * PRIMARY KEY объявлен сразу в CREATE TABLE — Percona XtraDB Cluster
--     не реплицирует надёжно таблицы без первичного ключа;
--   * TEXT вместо TINYTEXT — в TINYTEXT помещается всего 255 байт
--     (~127 русских букв), длинная заметка падала с «Data too long»;
--   * task.end может быть NULL — событие без времени окончания;
--   * заметки удаляются вместе с задачей (ON DELETE CASCADE);
--   * INSERT IGNORE — повторный запуск скрипта не падает на дубликатах.

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS `user` (
  `user_id`  INT          NOT NULL AUTO_INCREMENT,
  `fullname` VARCHAR(255) NOT NULL DEFAULT '',
  `login`    VARCHAR(255) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role`     VARCHAR(50)  NOT NULL DEFAULT 'student',
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uq_user_login` (`login`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `task` (
  `task_id`     INT          NOT NULL AUTO_INCREMENT,
  `user_id`     INT          NOT NULL,
  `title`       VARCHAR(255) NOT NULL,
  `description` TEXT         NOT NULL,
  `start`       DATETIME     NOT NULL,
  `end`         DATETIME     NULL DEFAULT NULL,
  PRIMARY KEY (`task_id`),
  KEY `idx_task_user_start` (`user_id`, `start`),
  CONSTRAINT `fk_task_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `note` (
  `note_id`     INT          NOT NULL AUTO_INCREMENT,
  `user_id`     INT          NOT NULL,
  `task_id`     INT          NULL DEFAULT NULL,
  `title`       VARCHAR(255) NOT NULL DEFAULT '',
  `content`     TEXT         NOT NULL,
  `create_date` DATETIME     NOT NULL,
  PRIMARY KEY (`note_id`),
  KEY `idx_note_user_date` (`user_id`, `create_date`),
  KEY `idx_note_task` (`task_id`),
  CONSTRAINT `fk_note_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_note_task` FOREIGN KEY (`task_id`) REFERENCES `task` (`task_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Демо-аккаунт для проверки: demo@example.com / demo1234
INSERT IGNORE INTO `user` (`user_id`, `fullname`, `login`, `password`, `role`) VALUES
(1, 'Демо Пользователь', 'demo@example.com', '$2y$10$fGxDPLRvUAaGrbt7pK3ZNuoLY7YhyiVi8p7vTrUEyM4Rqj5OOFsoq', 'student');

INSERT IGNORE INTO `task` (`task_id`, `user_id`, `title`, `description`, `start`, `end`) VALUES
(1, 1, 'Лекция по Kubernetes', 'Ауд. 305', '2026-11-16 10:00:00', '2026-11-16 11:30:00'),
(2, 1, 'Сдача практики Jenkins', '', '2026-11-18 13:00:00', '2026-11-18 14:00:00');

INSERT IGNORE INTO `note` (`note_id`, `user_id`, `task_id`, `title`, `content`, `create_date`) VALUES
(1, 1, 2, '', 'Подготовить скриншоты успешной и упавшей сборки', '2026-11-12 18:00:00'),
(2, 1, NULL, '', 'Общая заметка: повторить ReplicaSet и Service', '2026-11-12 18:05:00');
