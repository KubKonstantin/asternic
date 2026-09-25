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

        $raw_username = $user_info['preferred_username'] ?? ($user_info['name'] ?? ($user_info['email'] ?? ''));
        $normalized_username = get_casdoor_username($user_info);

        $_SESSION['casdoor_authenticated'] = true;
        $_SESSION['casdoor_user'] = [
            'id'           => $user_info['sub'] ?? '',
            'username'     => $normalized_username,
            'raw_username' => $raw_username,
            'name'         => $user_info['name'] ?? '',
            'email'        => $user_info['email'] ?? '',
            'roles'        => $user_info['roles'] ?? []
        ];
        $_SESSION['auth_user'] = [
            'username' => $normalized_username,
            'raw_username' => $raw_username,
            'name' => $_SESSION['casdoor_user']['name'] ?: $normalized_username,
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
