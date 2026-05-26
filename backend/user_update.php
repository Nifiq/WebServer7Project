<?php

declare(strict_types=1);

require_once __DIR__ . '/request_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(405, [
        'ok' => false,
        'message' => 'Метод не поддерживается.',
    ]);
}

$requestId = (int)($_SESSION['user_request_id'] ?? 0);

if ($requestId <= 0) {
    json_response(401, [
        'ok' => false,
        'message' => 'Сначала войдите по логину и паролю.',
        'csrf_token' => csrf_token(),
    ]);
}

require_valid_csrf();

$data = request_input();
$errors = validate_request_input($data);

if ($errors) {
    json_response(422, [
        'ok' => false,
        'message' => 'Заполните обязательные поля формы.',
        'errors' => $errors,
        'csrf_token' => csrf_token(),
    ]);
}

try {
    $stmt = db()->prepare(
        'UPDATE support_requests
         SET name = :name,
             phone = :phone,
             email = :email,
             message = :message,
             consent = 1,
             updated_at = NOW()
         WHERE id = :id'
    );

    $stmt->execute([
        ':name' => $data['name'],
        ':phone' => $data['phone'],
        ':email' => $data['email'],
        ':message' => $data['message'],
        ':id' => $requestId,
    ]);

    $stmt = db()->prepare('SELECT * FROM support_requests WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $requestId]);
    $request = $stmt->fetch();

    json_response(200, [
        'ok' => true,
        'message' => 'Заявка успешно обновлена.',
        'request' => $request ? safe_user_request_row($request) : null,
        'csrf_token' => refresh_csrf_token(),
    ]);
} catch (Throwable $e) {
    error_log('User update error: ' . $e->getMessage());
    json_response(500, [
        'ok' => false,
        'message' => 'Ошибка сервера при обновлении заявки. Проверьте, что выполнена миграция updated_at.',
        'debug' => ['error' => $e->getMessage()],
        'csrf_token' => csrf_token(),
    ]);
}
