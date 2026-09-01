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

$now = strtotime('2026-09-01 12:00:00');
expect_true(pan_expire_at_from_days(7, $now) === '2026-09-08 12:00:00', 'Expiry should be calculated from the requested number of days.');
expect_true(pan_file_access_error(['expire_at'=>'2026-09-01 11:59:59', 'count'=>0, 'max_downloads'=>0], $now) === 'expired', 'Expired shares should be rejected.');
expect_true(pan_file_access_error(['expire_at'=>null, 'count'=>3, 'max_downloads'=>3], $now) === 'limit', 'Shares at their access limit should be rejected.');
expect_true(pan_file_access_error(['expire_at'=>'2026-09-02 12:00:00', 'count'=>2, 'max_downloads'=>3], $now) === null, 'Active shares below their access limit should be allowed.');

echo "security tests passed" . PHP_EOL;
