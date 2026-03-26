<?php
// casdoor_auth.php
session_start();

require_once "config.php";

// Проверка аутентификации
function check_auth() {
    if (isset($_SESSION['casdoor_authenticated']) && $_SESSION['casdoor_authenticated'] === true) {
        return true;
    }
    
    // Перенаправляем на Casdoor для входа
    $auth_url = build_casdoor_auth_url();
    header('Location: ' . $auth_url);
    exit();
}

// Построение URL для аутентификации в Casdoor
function build_casdoor_auth_url() {
    global $casdoor_config;
    
    $params = [
        'client_id'     => $casdoor_config['client_id'],
        'response_type' => 'code',
        'redirect_uri'  => $casdoor_config['redirect_uri'],
        'scope'         => 'openid profile email',
        'state'         => bin2hex(random_bytes(16))
    ];
    
    return $casdoor_config['server_url'] . '/login/oauth/authorize?' . http_build_query($params);
}

// Получение токена по коду
function get_token($code) {
    global $casdoor_config;
    
    $token_url = $casdoor_config['server_url'] . '/api/login/oauth/access_token';
    
    $data = [
        'grant_type'    => 'authorization_code',
        'client_id'     => $casdoor_config['client_id'],
        'client_secret' => $casdoor_config['client_secret'],
        'code'          => $code
    ];
    
    $ch = curl_init($token_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

// Получение информации о пользователе
function get_user_info($access_token) {
    global $casdoor_config;
    
    $userinfo_url = $casdoor_config['server_url'] . '/api/userinfo';
    
    $ch = curl_init($userinfo_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $access_token,
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}
?>
