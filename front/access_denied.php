<?php
require_once "config.php";
require_once "casdoor_auth.php";

check_auth();
$current_user = get_authenticated_user();
$username = $current_user['username'] ?? '';
$name = $current_user['name'] ?? $username;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Доступ запрещен</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; }
        .access-denied-box { width: 520px; margin: 100px auto; padding: 28px; background: #fff; border: 1px solid #ddd; border-radius: 8px; text-align: center; }
        .access-denied-box h1 { margin: 0 0 16px; color: #c62828; font-size: 24px; }
        .access-denied-box p { margin: 8px 0; color: #333; }
        .actions { margin-top: 20px; }
    </style>
</head>
<body>
<div class="access-denied-box">
    <h1>Доступ запрещен</h1>
    <p>У пользователя <?php echo htmlspecialchars($name); ?> нет доступа к очередям и агентам.</p>
    <p>Обратитесь к администратору для настройки прав доступа.</p>
    <div class="actions">
        <a href="logout.php">Выйти</a>
    </div>
</div>
</body>
</html>
