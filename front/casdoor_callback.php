<?php
require_once 'casdoor_auth.php';

if (!is_casdoor_enabled()) {
    header('Location: internal_login.php');
    exit();
}

if (isset($_GET['code'])) {
    $code = $_GET['code'];

    $token_data = get_token($code);

    if (isset($token_data['access_token'])) {
        $user_info = get_user_info($token_data['access_token']);

        $_SESSION['casdoor_authenticated'] = true;
        $_SESSION['casdoor_user'] = [
            'id'        => $user_info['sub'] ?? '',
            'username'  => $user_info['preferred_username'] ?? '',
            'name'      => $user_info['name'] ?? '',
            'email'     => $user_info['email'] ?? '',
            'roles'     => $user_info['roles'] ?? []
        ];
        $_SESSION['auth_user'] = [
            'username' => $_SESSION['casdoor_user']['username'],
            'name' => $_SESSION['casdoor_user']['name'] ?: $_SESSION['casdoor_user']['username'],
            'provider' => 'casdoor'
        ];

        header('Location: index.php');
        exit();
    } else {
        die('Ошибка аутентификации: не удалось получить токен');
    }
} else {
    die('Ошибка: код авторизации не получен');
}
?>
