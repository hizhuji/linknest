<?php

function pan_is_https() {
    if (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) return true;
    if (isset($_SERVER['HTTPS']) && (strtolower($_SERVER['HTTPS']) === 'on' || $_SERVER['HTTPS'] === '1')) return true;
    foreach (['HTTP_X_FORWARDED_PROTO', 'HTTP_X_CLIENT_SCHEME', 'REQUEST_SCHEME'] as $header) {
        if (isset($_SERVER[$header]) && strtolower(trim(explode(',', $_SERVER[$header])[0])) === 'https') return true;
    }
    return false;
}

function pan_start_session() {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    $secure = pan_is_https();
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params(['lifetime'=>0, 'path'=>'/', 'secure'=>$secure, 'httponly'=>true, 'samesite'=>'Lax']);
    } else {
        session_set_cookie_params(0, '/; samesite=Lax', '', $secure, true);
    }
    session_start();
}

function pan_random_string($length = 32) {
    $bytes = '';
    if (function_exists('random_bytes')) {
        try { $bytes = random_bytes((int)ceil($length / 2)); } catch (Exception $e) { $bytes = ''; }
    }
    if ($bytes === '' && function_exists('openssl_random_pseudo_bytes')) $bytes = openssl_random_pseudo_bytes((int)ceil($length / 2));
    if ($bytes === false || $bytes === '') $bytes = hash('sha256', uniqid((string)mt_rand(), true), true);
    return substr(bin2hex($bytes), 0, $length);
}

function pan_base64url_encode($value) { return rtrim(strtr(base64_encode($value), '+/', '-_'), '='); }

function pan_base64url_decode($value) {
    $padding = strlen($value) % 4;
    if ($padding) $value .= str_repeat('=', 4 - $padding);
    return base64_decode(strtr($value, '-_', '+/'), true);
}

function pan_create_auth_token($payload, $key) {
    $body = pan_base64url_encode(json_encode($payload, JSON_UNESCAPED_SLASHES));
    return 'v2.' . $body . '.' . hash_hmac('sha256', $body, $key);
}

function pan_read_auth_token($token, $key) {
    $parts = explode('.', (string)$token);
    if (count($parts) !== 3 || $parts[0] !== 'v2') return false;
    $expected = hash_hmac('sha256', $parts[1], $key);
    if (!hash_equals($expected, $parts[2])) return false;
    $raw = pan_base64url_decode($parts[1]);
    $payload = $raw === false ? null : json_decode($raw, true);
    if (!is_array($payload) || empty($payload['exp']) || (int)$payload['exp'] <= time()) return false;
    return $payload;
}

function pan_set_auth_cookie($name, $value, $expires) {
    $secure = pan_is_https();
    if (PHP_VERSION_ID >= 70300) {
        setcookie($name, $value, ['expires'=>$expires, 'path'=>'/', 'secure'=>$secure, 'httponly'=>true, 'samesite'=>'Lax']);
    } else {
        setcookie($name, $value, $expires, '/; samesite=Lax', '', $secure, true);
    }
}

function pan_clear_auth_cookie($name) { pan_set_auth_cookie($name, '', time() - 3600); }

function pan_csrf_token() {
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = pan_random_string(64);
    return $_SESSION['csrf_token'];
}

function pan_verify_csrf_token($token) {
    return !empty($_SESSION['csrf_token']) && is_string($token) && hash_equals($_SESSION['csrf_token'], $token);
}

function pan_verify_request_csrf_token() {
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : null;
    if (!$token && isset($_SERVER['HTTP_X_CSRF_TOKEN'])) $token = $_SERVER['HTTP_X_CSRF_TOKEN'];
    return pan_verify_csrf_token($token);
}

function pan_is_password_hash($value) { return is_string($value) && preg_match('/^\$2[ayb]\$|^\$argon2/i', $value) === 1; }

function pan_verify_admin_password($password, $stored) {
    return pan_is_password_hash($stored) ? password_verify($password, $stored) : (is_string($stored) && hash_equals($stored, (string)$password));
}

function pan_password_needs_upgrade($stored) { return !pan_is_password_hash($stored) || password_needs_rehash($stored, PASSWORD_DEFAULT); }

function pan_create_file_access_token($hash, $key, $ttl = 1800) {
    return pan_create_auth_token(['type'=>'file_access', 'hash'=>(string)$hash, 'exp'=>time()+max(60, intval($ttl))], $key);
}

function pan_verify_file_access_token($token, $hash, $key) {
    $payload = pan_read_auth_token((string)$token, $key);
    return is_array($payload) && isset($payload['type'], $payload['hash']) && $payload['type'] === 'file_access' && hash_equals((string)$hash, (string)$payload['hash']);
}
