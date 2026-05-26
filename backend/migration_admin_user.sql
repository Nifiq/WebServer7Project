-- Миграция для WebServer7Project / katalog_zadaniya_8
-- Выполнить в phpMyAdmin/MySQL перед проверкой формы.
-- Если таблица/колонка уже существует, MySQL может написать Duplicate column/table — это нормально, такую строку можно пропустить.

CREATE TABLE IF NOT EXISTS support_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    phone VARCHAR(25) NOT NULL,
    email VARCHAR(255) NOT NULL,
    message TEXT NULL,
    consent TINYINT(1) NOT NULL DEFAULT 1,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE support_requests ADD COLUMN login VARCHAR(64) NULL UNIQUE AFTER id;
ALTER TABLE support_requests ADD COLUMN password_hash VARCHAR(255) NULL AFTER login;
ALTER TABLE support_requests ADD COLUMN updated_at DATETIME NULL DEFAULT NULL AFTER created_at;

CREATE TABLE IF NOT EXISTS admins (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    login VARCHAR(64) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Администратора удобнее создать через backend/install_admin_user.php:
-- он сам создаст admin и покажет случайный пароль один раз.
