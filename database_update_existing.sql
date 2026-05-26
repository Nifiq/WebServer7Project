-- Выполняйте этот файл только если таблица support_requests уже существует.
-- Если какая-то колонка уже есть, phpMyAdmin может показать ошибку "Duplicate column" — это нормально,
-- в таком случае просто переходите к следующей команде или используйте database.sql для новой базы.

ALTER TABLE support_requests ADD COLUMN user_login VARCHAR(120) NULL AFTER consent;
ALTER TABLE support_requests ADD COLUMN user_password_hash VARCHAR(255) NULL AFTER user_login;
ALTER TABLE support_requests ADD COLUMN updated_at DATETIME NULL AFTER created_at;
CREATE INDEX idx_user_login ON support_requests (user_login);
