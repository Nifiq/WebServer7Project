<?php
session_start();
require_once __DIR__ . '/config.php';

function require_admin(): void
{
    if (!isset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'])) {
        header('WWW-Authenticate: Basic realm="' . ADMIN_REALM . '"');
        header('HTTP/1.0 401 Unauthorized');
        exit('Нужна авторизация.');
    }

    try {
        $stmt = db()->prepare('SELECT password_hash FROM admins WHERE login = ? LIMIT 1');
        $stmt->execute([$_SERVER['PHP_AUTH_USER']]);
        $admin = $stmt->fetch();
    } catch (PDOException $e) {
        error_log('Admin auth DB error: ' . $e->getMessage());
        http_response_code(500);
        exit('Ошибка сервера.');
    }

    if (!$admin || !password_verify($_SERVER['PHP_AUTH_PW'], $admin['password_hash'])) {
        header('WWW-Authenticate: Basic realm="' . ADMIN_REALM . '"');
        header('HTTP/1.0 401 Unauthorized');
        exit('Неверный логин или пароль.');
    }
}

require_admin();

if (empty($_SESSION['admin_csrf_token'])) {
    $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $postedToken = $_POST['csrf_token'] ?? '';

    if (!$postedToken || !hash_equals($_SESSION['admin_csrf_token'], $postedToken)) {
        http_response_code(403);
        exit('Ошибка безопасности.');
    }

    $id = (int)$_POST['delete_id'];

    if ($id > 0) {
        $stmt = db()->prepare('DELETE FROM support_requests WHERE id = ?');
        $stmt->execute([$id]);
    }

    header('Location: admin.php');
    exit;
}

try {
    $requests = db()
        ->query('SELECT * FROM support_requests ORDER BY created_at DESC, id DESC')
        ->fetchAll();
} catch (PDOException $e) {
    error_log('Admin list DB error: ' . $e->getMessage());
    http_response_code(500);
    exit('Ошибка сервера.');
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Заявки с сайта</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f6f8;
            color: #111827;
        }
        .page {
            width: min(100% - 32px, 1200px);
            margin: 32px auto;
            background: #fff;
            padding: 24px;
            border-radius: 16px;
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
        }
        h1 { margin: 0 0 20px; }
        .table-wrap { overflow-x: auto; }
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 900px;
        }
        th, td {
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
            vertical-align: top;
        }
        th {
            background: #111827;
            color: #fff;
            position: sticky;
            top: 0;
        }
        .message {
            max-width: 360px;
            white-space: pre-wrap;
        }
        .delete-btn {
            border: 0;
            padding: 8px 12px;
            border-radius: 8px;
            background: #dc2626;
            color: #fff;
            cursor: pointer;
        }
        .empty {
            padding: 24px;
            background: #f9fafb;
            border: 1px dashed #d1d5db;
            border-radius: 12px;
        }
    </style>
</head>
<body>
<div class="page">
    <h1>Заявки с сайта</h1>

    <?php if (!$requests): ?>
        <div class="empty">Пока заявок нет.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Дата</th>
                        <th>Имя</th>
                        <th>Телефон</th>
                        <th>Email</th>
                        <th>Комментарий</th>
                        <th>IP</th>
                        <th>Действие</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($requests as $request): ?>
                    <tr>
                        <td><?= (int)$request['id'] ?></td>
                        <td><?= h($request['created_at']) ?></td>
                        <td><?= h($request['name']) ?></td>
                        <td><?= h($request['phone']) ?></td>
                        <td><a href="mailto:<?= h($request['email']) ?>"><?= h($request['email']) ?></a></td>
                        <td class="message"><?= h($request['message']) ?></td>
                        <td><?= h($request['ip_address']) ?></td>
                        <td>
                            <form method="POST" onsubmit="return confirm('Удалить заявку?');">
                                <input type="hidden" name="csrf_token" value="<?= h($_SESSION['admin_csrf_token']) ?>">
                                <input type="hidden" name="delete_id" value="<?= (int)$request['id'] ?>">
                                <button type="submit" class="delete-btn">Удалить</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
