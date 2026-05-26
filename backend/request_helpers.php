<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!function_exists('json_response')) {
    function json_response(int $statusCode, array $payload): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!function_exists('e')) {
    function e(?string $value): string
    {
        if (function_exists('h')) {
            return h($value);
        }
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string)$_SESSION['csrf_token'];
}

function refresh_csrf_token(): string
{
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return (string)$_SESSION['csrf_token'];
}

function require_valid_csrf(): void
{
    $postedToken = (string)($_POST['csrf_token'] ?? '');
    $sessionToken = (string)($_SESSION['csrf_token'] ?? '');

    if ($postedToken === '' || $sessionToken === '' || !hash_equals($sessionToken, $postedToken)) {
        json_response(403, [
            'ok' => false,
            'message' => 'Ошибка безопасности. Обновите страницу и попробуйте ещё раз.',
            'csrf_token' => csrf_token(),
        ]);
    }
}

function request_input(): array
{
    return [
        'name' => trim((string)($_POST['name'] ?? '')),
        'phone' => trim((string)($_POST['phone'] ?? '')),
        'email' => trim((string)($_POST['email'] ?? '')),
        'message' => trim((string)($_POST['message'] ?? '')),
        'consent' => isset($_POST['consent']) ? 1 : 0,
    ];
}

function validate_request_input(array $data): array
{
    $errors = [];

    if ($data['name'] === '') {
        $errors['name'] = 'Не заполнено поле «Ваше имя».';
    } elseif (!preg_match('/^[\p{L}\s\-]{2,150}$/u', $data['name'])) {
        $errors['name'] = 'Введите корректное имя: только буквы, пробелы и дефис.';
    }

    if ($data['phone'] === '') {
        $errors['phone'] = 'Не заполнено поле «Телефон».';
    } elseif (!preg_match('/^\+?[0-9\s\-()]{7,25}$/', $data['phone'])) {
        $errors['phone'] = 'Введите корректный телефон.';
    }

    if ($data['email'] === '') {
        $errors['email'] = 'Не заполнено поле «E-mail».';
    } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Введите корректный E-mail.';
    }

    $messageLength = function_exists('mb_strlen') ? mb_strlen($data['message'], 'UTF-8') : strlen($data['message']);
    if ($messageLength > 2000) {
        $errors['message'] = 'Комментарий слишком длинный. Максимум 2000 символов.';
    }

    if (!$data['consent']) {
        $errors['consent'] = 'Поставьте галочку согласия на обработку персональных данных.';
    }

    return $errors;
}

function generate_user_password(int $bytes = 6): string
{
    return substr(bin2hex(random_bytes($bytes)), 0, 12);
}

function support_table_columns(PDO $pdo): array
{
    $columns = [];
    $stmt = $pdo->query('SHOW COLUMNS FROM support_requests');
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (isset($row['Field'])) {
            $columns[(string)$row['Field']] = true;
        }
    }
    return $columns;
}

function support_credential_columns(PDO $pdo): ?array
{
    $columns = support_table_columns($pdo);

    // Реальная таблица на сайте использует именно эти имена.
    if (isset($columns['user_login'], $columns['user_password_hash'])) {
        return [
            'login' => 'user_login',
            'hash' => 'user_password_hash',
        ];
    }

    // Запасной вариант, если где-то была применена другая миграция.
    if (isset($columns['login'], $columns['password_hash'])) {
        return [
            'login' => 'login',
            'hash' => 'password_hash',
        ];
    }

    return null;
}

function safe_user_request_row(array $row): array
{
    return [
        'id' => (int)$row['id'],
        'login' => (string)($row['user_login'] ?? $row['login'] ?? ''),
        'name' => (string)($row['name'] ?? ''),
        'phone' => (string)($row['phone'] ?? ''),
        'email' => (string)($row['email'] ?? ''),
        'message' => (string)($row['message'] ?? ''),
        'consent' => (int)($row['consent'] ?? 0),
        'created_at' => (string)($row['created_at'] ?? ''),
        'updated_at' => (string)($row['updated_at'] ?? ''),
    ];
}
