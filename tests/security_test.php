<?php

require __DIR__ . '/../includes/security.php';
require __DIR__ . '/../includes/functions.php';

function expect_true($value, $message) {
    if (!$value) {
        fwrite(STDERR, "FAIL: " . $message . PHP_EOL);
        exit(1);
    }
}

$key = 'test-key';
$token = pan_create_auth_token(['type'=>'admin', 'user'=>'admin', 'sid'=>'session', 'exp'=>time()+60], $key);
$payload = pan_read_auth_token($token, $key);
expect_true($payload['user'] === 'admin', 'Signed token should round-trip.');
expect_true(pan_read_auth_token($token . 'x', $key) === false, 'Tampered token should fail.');
expect_true(pan_read_auth_token(pan_create_auth_token(['exp'=>time()-1], $key), $key) === false, 'Expired token should fail.');

if (session_status() !== PHP_SESSION_ACTIVE) pan_start_session();
$csrf = pan_csrf_token();
expect_true(pan_verify_csrf_token($csrf), 'CSRF token should validate.');
expect_true(!pan_verify_csrf_token('wrong-token'), 'Invalid CSRF token should fail.');

$hash = password_hash('correct horse battery staple', PASSWORD_DEFAULT);
expect_true(pan_verify_admin_password('correct horse battery staple', $hash), 'Password hash should validate.');
expect_true(!pan_verify_admin_password('incorrect', $hash), 'Wrong password should fail.');

echo "security tests passed" . PHP_EOL;
