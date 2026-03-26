<?php
// casdoor_callback.php
require_once 'casdoor_auth.php';

session_start();

if (isset($_GET['code'])) {
    $code = $_GET['code'];
    
    // Получаем токен
    $token_data = get_token($code);
    
    if (isset($token_data['access_token'])) {
        // Получаем информацию о пользователе
        $user_info = get_user_info($token_data['access_token']);
        
        // Сохраняем в сессию
        $_SESSION['casdoor_authenticated'] = true;
        $_SESSION['casdoor_user'] = [
            'id'        => $user_info['sub'] ?? '',
            'username'  => $user_info['preferred_username'] ?? '',
            'name'      => $user_info['name'] ?? '',
            'email'     => $user_info['email'] ?? '',
            'roles'     => $user_info['roles'] ?? []
        ];
        
        // Перенаправляем на главную страницу
        header('Location: index.php');
        exit();
    } else {
        die('Ошибка аутентификации: не удалось получить токен');
    }
} else {
    die('Ошибка: код авторизации не получен');
}
?>
