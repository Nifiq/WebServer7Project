<?php
/**
 * Диагностическая версия обработчика формы.
 * Временно выводит подробную ошибку в JSON, чтобы быстро понять, почему форма не отправляется.
 * После исправления можно оставить этот файл: для обычного пользователя он всё равно возвращает нормальные сообщения.
 */

ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);

$alreadySentJson = false;

function send_json_response(int $statusCode, array $payload): void
{
    global $alreadySentJson;
    $alreadySentJson = true;

    if (ob_get_length()) {
        ob_clean();
    }

    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

register_shutdown_function(function () {
    global $alreadySentJson;
    if ($alreadySentJson) {
        return;
    }

    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (ob_get_length()) {
            ob_clean();
        }
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => false,
            'message' => 'Фатальная ошибка PHP в backend/submit.php или backend/config.php.',
            'debug' => [
                'type' => $error['type'],
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line'],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
});

function load_config_or_fail(): void
{
    $configPath = __DIR__ . '/config.php';

    if (!file_exists($configPath)) {
        send_json_response(500, [
            'ok' => false,
            'message' => 'Не найден файл backend/config.php.',
            'debug' => ['expected_path' => $configPath],
        ]);
    }

    require_once $configPath;

    $requiredConstants = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'];
    $missing = [];
    foreach ($requiredConstants as $constant) {
        if (!defined($constant)) {
            $missing[] = $constant;
        }
    }

    if ($missing) {
        send_json_response(500, [
            'ok' => false,
            'message' => 'В backend/config.php не хватает обязательных констант.',
            'debug' => ['missing_constants' => $missing],
        ]);
    }

    if (!function_exists('db')) {
        send_json_response(500, [
            'ok' => false,
            'message' => 'В backend/config.php не найдена функция db().',
        ]);
    }
}

function run_diagnostics(): array
{
    $checks = [];

    $checks['php_version'] = PHP_VERSION;
    $checks['request_method'] = $_SERVER['REQUEST_METHOD'] ?? 'unknown';
    $checks['current_file'] = __FILE__;
    $checks['config_exists'] = file_exists(__DIR__ . '/config.php');
    $checks['pdo_loaded'] = extension_loaded('pdo');
    $checks['pdo_mysql_loaded'] = extension_loaded('pdo_mysql');

    try {
        load_config_or_fail();
        $checks['config_loaded'] = true;
        $checks['db_name'] = defined('DB_NAME') ? DB_NAME : null;

        try {
            $pdo = db();
            $checks['db_connection'] = true;

            try {
                $stmt = $pdo->query("SHOW TABLES LIKE 'support_requests'");
                $checks['table_support_requests_exists'] = (bool)$stmt->fetchColumn();
            } catch (Throwable $e) {
                $checks['table_support_requests_exists'] = false;
                $checks['table_check_error'] = $e->getMessage();
            }
        } catch (Throwable $e) {
            $checks['db_connection'] = false;
            $checks['db_error'] = $e->getMessage();
        }
    } catch (Throwable $e) {
        $checks['config_loaded'] = false;
        $checks['config_error'] = $e->getMessage();
    }

    return $checks;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(200, [
        'ok' => true,
        'message' => 'backend/submit.php работает. Это технический файл: при открытии в браузере он только показывает проверку. Реальная отправка заявки идёт методом POST со страницы сайта.',
        'debug' => run_diagnostics(),
    ]);
}

load_config_or_fail();

$postedToken = $_POST['csrf_token'] ?? '';
$sessionToken = $_SESSION['csrf_token'] ?? '';

if (!$postedToken || !$sessionToken || !function_exists('hash_equals') || !hash_equals($sessionToken, $postedToken)) {
    send_json_response(403, [
        'ok' => false,
        'message' => 'Ошибка безопасности. Обновите страницу и попробуйте ещё раз.',
        'debug' => [
            'posted_token_exists' => $postedToken !== '',
            'session_token_exists' => $sessionToken !== '',
            'hash_equals_exists' => function_exists('hash_equals'),
        ],
    ]);
}

$name = trim((string)($_POST['name'] ?? ''));
$phone = trim((string)($_POST['phone'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$message = trim((string)($_POST['message'] ?? ''));
$consent = isset($_POST['consent']);

$errors = [];

if ($name === '') {
    $errors['name'] = 'Не заполнено поле «Ваше имя».';
} elseif (!preg_match('/^[\p{L}\s\-]{2,150}$/u', $name)) {
    $errors['name'] = 'Введите корректное имя: только буквы, пробелы и дефис.';
}

if ($phone === '') {
    $errors['phone'] = 'Не заполнено поле «Телефон».';
} elseif (!preg_match('/^\+?[0-9\s\-()]{7,25}$/', $phone)) {
    $errors['phone'] = 'Введите корректный телефон.';
}

if ($email === '') {
    $errors['email'] = 'Не заполнено поле «E-mail».';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Введите корректный E-mail.';
}

$messageLength = function_exists('mb_strlen') ? mb_strlen($message, 'UTF-8') : strlen($message);
if ($messageLength > 2000) {
    $errors['message'] = 'Комментарий слишком длинный. Максимум 2000 символов.';
}

if (!$consent) {
    $errors['consent'] = 'Поставьте галочку согласия на обработку персональных данных.';
}

// reCAPTCHA отключена: поле g-recaptcha-response больше не проверяется.

if ($errors) {
    send_json_response(422, [
        'ok' => false,
        'message' => 'Заполните обязательные поля формы.',
        'errors' => $errors,
    ]);
}

try {
    $stmt = db()->prepare(
        'INSERT INTO support_requests
            (name, phone, email, message, consent, ip_address, user_agent, created_at)
         VALUES
            (:name, :phone, :email, :message, :consent, :ip_address, :user_agent, NOW())'
    );

    $stmt->execute([
        ':name' => $name,
        ':phone' => $phone,
        ':email' => $email,
        ':message' => $message,
        ':consent' => 1,
        ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ':user_agent' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
    ]);

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    send_json_response(200, [
        'ok' => true,
        'message' => 'Спасибо! Заявка отправлена. Мы свяжемся с вами в ближайшее время.',
        'csrf_token' => $_SESSION['csrf_token'],
    ]);
} catch (Throwable $e) {
    error_log('Support form submit error: ' . $e->getMessage());

    send_json_response(500, [
        'ok' => false,
        'message' => 'Ошибка сервера при сохранении заявки.',
        'debug' => [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ],
    ]);
}
