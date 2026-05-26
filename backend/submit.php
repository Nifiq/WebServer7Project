<?php
/**
 * HOTFIX для реальной таблицы сайта /katalog_zadaniya_8/.
 * В таблице support_requests логин и пароль называются:
 * - user_login
 * - user_password_hash
 *
 * Файл также поддерживает запасной вариант login/password_hash.
 */

ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

function send_json_response($status, array $payload)
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code((int)$status);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

register_shutdown_function(function () {
    $error = error_get_last();

    if (!$error) {
        return;
    }

    $fatalTypes = array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR);

    if (in_array($error['type'], $fatalTypes, true)) {
        send_json_response(500, array(
            'ok' => false,
            'message' => 'Фатальная ошибка PHP в backend/submit.php или подключаемых файлах.',
            'debug' => array(
                'type' => $error['type'],
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line'],
            ),
        ));
    }
});

function load_config_or_fail()
{
    $configPath = __DIR__ . '/config.php';

    if (!file_exists($configPath)) {
        send_json_response(500, array(
            'ok' => false,
            'message' => 'Не найден файл backend/config.php.',
            'debug' => array('expected_path' => $configPath),
        ));
    }

    require_once $configPath;

    $missing = array();
    foreach (array('DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS') as $constant) {
        if (!defined($constant)) {
            $missing[] = $constant;
        }
    }

    if ($missing) {
        send_json_response(500, array(
            'ok' => false,
            'message' => 'В backend/config.php не хватает обязательных констант.',
            'debug' => array('missing_constants' => $missing),
        ));
    }

    if (!function_exists('db')) {
        send_json_response(500, array(
            'ok' => false,
            'message' => 'В backend/config.php не найдена функция db().',
        ));
    }
}

function get_support_columns(PDO $pdo)
{
    $columns = array();
    $stmt = $pdo->query('SHOW COLUMNS FROM support_requests');

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (isset($row['Field'])) {
            $columns[$row['Field']] = true;
        }
    }

    return $columns;
}

function has_column(array $columns, $name)
{
    return isset($columns[$name]);
}

function get_credential_columns(array $columns)
{
    if (has_column($columns, 'user_login') && has_column($columns, 'user_password_hash')) {
        return array(
            'login' => 'user_login',
            'hash' => 'user_password_hash',
        );
    }

    if (has_column($columns, 'login') && has_column($columns, 'password_hash')) {
        return array(
            'login' => 'login',
            'hash' => 'password_hash',
        );
    }

    return null;
}

function generate_user_password($length = 12)
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    $max = strlen($alphabet) - 1;
    $password = '';

    for ($i = 0; $i < $length; $i++) {
        $password .= $alphabet[random_int(0, $max)];
    }

    return $password;
}

function run_diagnostics()
{
    $checks = array();
    $checks['php_version'] = PHP_VERSION;
    $checks['request_method'] = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'unknown';
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

                if ($checks['table_support_requests_exists']) {
                    $columns = get_support_columns($pdo);
                    $checks['support_requests_columns'] = array_keys($columns);
                    $checks['credential_columns_detected'] = get_credential_columns($columns);
                }
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

if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(200, array(
        'ok' => true,
        'message' => 'backend/submit.php работает. Это технический файл: отправка заявки идёт методом POST со страницы сайта.',
        'debug' => run_diagnostics(),
    ));
}

try {
    load_config_or_fail();

    $postedToken = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : '';
    $sessionToken = isset($_SESSION['csrf_token']) ? (string)$_SESSION['csrf_token'] : '';

    if (!$postedToken || !$sessionToken || !hash_equals($sessionToken, $postedToken)) {
        send_json_response(403, array(
            'ok' => false,
            'message' => 'Ошибка безопасности. Обновите страницу и попробуйте ещё раз.',
            'debug' => array(
                'posted_token_exists' => $postedToken !== '',
                'session_token_exists' => $sessionToken !== '',
            ),
        ));
    }

    $name = trim((string)(isset($_POST['name']) ? $_POST['name'] : ''));
    $phone = trim((string)(isset($_POST['phone']) ? $_POST['phone'] : ''));
    $email = trim((string)(isset($_POST['email']) ? $_POST['email'] : ''));
    $message = trim((string)(isset($_POST['message']) ? $_POST['message'] : ''));
    $consent = isset($_POST['consent']);

    $errors = array();

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

    // reCAPTCHA сейчас НЕ проверяется на сервере. Это оставлено без изменений.

    if ($errors) {
        send_json_response(422, array(
            'ok' => false,
            'message' => 'Заполните обязательные поля формы.',
            'errors' => $errors,
        ));
    }

    $pdo = db();
    $columns = get_support_columns($pdo);

    foreach (array('name', 'phone', 'email') as $requiredColumn) {
        if (!has_column($columns, $requiredColumn)) {
            send_json_response(500, array(
                'ok' => false,
                'message' => 'В таблице support_requests не хватает обязательной колонки: ' . $requiredColumn,
                'debug' => array('columns' => array_keys($columns)),
            ));
        }
    }

    $credentialColumns = get_credential_columns($columns);

    $pdo->beginTransaction();

    $insertData = array(
        'name' => $name,
        'phone' => $phone,
        'email' => $email,
    );

    if (has_column($columns, 'message')) {
        $insertData['message'] = $message;
    }
    if (has_column($columns, 'consent')) {
        $insertData['consent'] = 1;
    }
    if (has_column($columns, 'ip_address')) {
        $insertData['ip_address'] = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
    }
    if (has_column($columns, 'user_agent')) {
        $insertData['user_agent'] = substr((string)(isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ''), 0, 255);
    }
    if (has_column($columns, 'created_at')) {
        $insertData['created_at'] = '__NOW__';
    }

    $fieldSql = array();
    $valueSql = array();
    $params = array();

    foreach ($insertData as $field => $value) {
        $fieldSql[] = '`' . $field . '`';
        if ($value === '__NOW__') {
            $valueSql[] = 'NOW()';
        } else {
            $placeholder = ':' . $field;
            $valueSql[] = $placeholder;
            $params[$placeholder] = $value;
        }
    }

    $sql = 'INSERT INTO support_requests (' . implode(', ', $fieldSql) . ') VALUES (' . implode(', ', $valueSql) . ')';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $requestId = (int)$pdo->lastInsertId();
    $login = null;
    $plainPassword = null;
    $credentialsWarning = null;

    if ($credentialColumns !== null) {
        $login = 'user' . $requestId;
        $plainPassword = generate_user_password(12);
        $passwordHash = password_hash($plainPassword, PASSWORD_DEFAULT);

        $loginColumn = $credentialColumns['login'];
        $hashColumn = $credentialColumns['hash'];

        $update = $pdo->prepare('UPDATE support_requests SET `' . $loginColumn . '` = :login, `' . $hashColumn . '` = :password_hash WHERE id = :id');
        $update->execute(array(
            ':login' => $login,
            ':password_hash' => $passwordHash,
            ':id' => $requestId,
        ));
    } else {
        $credentialsWarning = 'Заявка сохранена, но логин и пароль не созданы: в таблице support_requests нет колонок user_login/user_password_hash.';
    }

    $pdo->commit();

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    $response = array(
        'ok' => true,
        'message' => $credentialsWarning ? 'Спасибо! Заявка отправлена. ' . $credentialsWarning : 'Спасибо! Заявка отправлена. Сохраните логин и пароль.',
        'request_id' => $requestId,
        'csrf_token' => $_SESSION['csrf_token'],
    );

    if ($login !== null && $plainPassword !== null) {
        // JS на странице ждёт именно login/password, поэтому наружу отдаём такие имена.
        $response['login'] = $login;
        $response['password'] = $plainPassword;
    }

    if ($credentialsWarning) {
        $response['warning'] = $credentialsWarning;
    }

    send_json_response(200, $response);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Support form submit error: ' . $e->getMessage());

    send_json_response(500, array(
        'ok' => false,
        'message' => 'Ошибка сервера при сохранении заявки.',
        'debug' => array(
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ),
    ));
}
