<?php
require_once 'casdoor_auth.php';

if (is_casdoor_enabled()) {
    header('Location: index.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $users = get_internal_users();
    $password_hash = $users[$username] ?? null;

    if ($password_hash && password_verify($password, $password_hash)) {
        $_SESSION['internal_authenticated'] = true;
        $_SESSION['auth_user'] = [
            'username' => $username,
            'name' => $username,
            'provider' => 'internal'
        ];

        unset($_SESSION['casdoor_authenticated'], $_SESSION['casdoor_user']);

        header('Location: index.php');
        exit();
    }

    $error = 'Неверный логин или пароль';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Вход в Asternic</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; }
        .login-box { width: 360px; margin: 100px auto; padding: 24px; background: #fff; border: 1px solid #ddd; border-radius: 8px; }
        .login-box h1 { margin: 0 0 16px; font-size: 20px; }
        .field { margin-bottom: 12px; }
        .field label { display: block; margin-bottom: 6px; }
        .field input { width: 100%; padding: 8px; box-sizing: border-box; }
        .error { color: #c62828; margin-bottom: 12px; }
        button { width: 100%; padding: 10px; cursor: pointer; }
    </style>
</head>
<body>
<div class="login-box">
    <h1>Вход в Asternic</h1>
    <?php if ($error !== ''): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <form method="post">
        <div class="field">
            <label for="username">Логин</label>
            <input id="username" name="username" type="text" required>
        </div>
        <div class="field">
            <label for="password">Пароль</label>
            <input id="password" name="password" type="password" required>
        </div>
        <button type="submit">Войти</button>
    </form>
</div>
</body>
</html>
