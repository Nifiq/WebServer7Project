<?php
/**
 * Быстрая диагностика backend для формы.
 * Откройте этот файл в браузере. Он должен вернуть JSON.
 */

ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);

function out(array $payload, int $code = 200): void
{
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (ob_get_length()) {
            ob_clean();
        }
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => false,
            'message' => 'Фатальная PHP-ошибка.',
            'debug' => $error,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
});

$checks = [
    'php_version' => PHP_VERSION,
    'current_file' => __FILE__,
    'config_exists' => file_exists(__DIR__ . '/config.php'),
    'pdo_loaded' => extension_loaded('pdo'),
    'pdo_mysql_loaded' => extension_loaded('pdo_mysql'),
];

if (!$checks['config_exists']) {
    out([
        'ok' => false,
        'message' => 'Не найден backend/config.php рядом с check_submit.php.',
        'checks' => $checks,
    ], 500);
}

try {
    require_once __DIR__ . '/config.php';
    $checks['config_loaded'] = true;
    $checks['db_host'] = defined('DB_HOST') ? DB_HOST : null;
    $checks['db_name'] = defined('DB_NAME') ? DB_NAME : null;
    $checks['db_user'] = defined('DB_USER') ? DB_USER : null;

    if (!function_exists('db')) {
        out([
            'ok' => false,
            'message' => 'Функция db() не найдена в config.php.',
            'checks' => $checks,
        ], 500);
    }

    $pdo = db();
    $checks['db_connection'] = true;

    $stmt = $pdo->query("SHOW TABLES LIKE 'support_requests'");
    $checks['table_support_requests_exists'] = (bool)$stmt->fetchColumn();

    out([
        'ok' => $checks['table_support_requests_exists'],
        'message' => $checks['table_support_requests_exists']
            ? 'Backend формы выглядит исправным.'
            : 'База подключилась, но таблица support_requests не найдена. Нужно импортировать database.sql.',
        'checks' => $checks,
    ], $checks['table_support_requests_exists'] ? 200 : 500);
} catch (Throwable $e) {
    $checks['db_connection'] = false;
    out([
        'ok' => false,
        'message' => 'Ошибка подключения к backend/config.php или MySQL.',
        'checks' => $checks,
        'debug' => [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ],
    ], 500);
}
