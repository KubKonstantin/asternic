<?php
require_once "config.php";

function get_access_control_config() {
    static $config = null;

    if ($config !== null) {
        return $config;
    }

    $default_config = [
        'auth_provider' => getenv('AUTH_PROVIDER') ?: 'casdoor',
        'queue_permissions' => [],
        'internal_users' => []
    ];

    $config_path = getenv('ACCESS_CONTROL_CONFIG') ?: __DIR__ . '/access_control.json';
    if (!is_readable($config_path)) {
        $config = $default_config;
        return $config;
    }

    $raw_config = file_get_contents($config_path);
    $decoded = json_decode($raw_config, true);

    if (!is_array($decoded)) {
        $config = $default_config;
        return $config;
    }

    $config = array_merge($default_config, $decoded);
    return $config;
}

function get_auth_provider() {
    $config = get_access_control_config();
    $provider = strtolower(trim((string)$config['auth_provider']));

    if (!in_array($provider, ['casdoor', 'internal'], true)) {
        return 'casdoor';
    }

    return $provider;
}

function is_casdoor_enabled() {
    return get_auth_provider() === 'casdoor';
}

function get_authenticated_user() {
    if (isset($_SESSION['auth_user']) && is_array($_SESSION['auth_user'])) {
        return $_SESSION['auth_user'];
    }

    if (isset($_SESSION['casdoor_user']) && is_array($_SESSION['casdoor_user'])) {
        return [
            'username' => $_SESSION['casdoor_user']['username'] ?? '',
            'name' => $_SESSION['casdoor_user']['name'] ?? ($_SESSION['casdoor_user']['username'] ?? ''),
            'provider' => 'casdoor'
        ];
    }

    return null;
}

function get_authenticated_username() {
    $user = get_authenticated_user();
    return $user['username'] ?? '';
}

function check_auth() {
    if (is_casdoor_enabled()) {
        if (isset($_SESSION['casdoor_authenticated']) && $_SESSION['casdoor_authenticated'] === true) {
            return true;
        }

        $auth_url = build_casdoor_auth_url();
        header('Location: ' . $auth_url);
        exit();
    }

    if (isset($_SESSION['internal_authenticated']) && $_SESSION['internal_authenticated'] === true) {
        return true;
    }

    header('Location: internal_login.php');
    exit();
}

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

function get_internal_users() {
    $config = get_access_control_config();
    return is_array($config['internal_users']) ? $config['internal_users'] : [];
}

function get_allowed_queues_for_user($username) {
    $config = get_access_control_config();
    $permissions = is_array($config['queue_permissions']) ? $config['queue_permissions'] : [];

    if ($username === '') {
        return [];
    }

    if (!array_key_exists($username, $permissions)) {
        return [];
    }

    $allowed = $permissions[$username];
    if (!is_array($allowed)) {
        return [];
    }

    if (in_array('*', $allowed, true)) {
        return null;
    }

    return array_values(array_unique(array_map('strval', $allowed)));
}

function filter_queues_for_user($queues, $username) {
    $allowed = get_allowed_queues_for_user($username);

    if ($allowed === null) {
        return $queues;
    }

    return array_values(array_intersect($queues, $allowed));
}
?>
