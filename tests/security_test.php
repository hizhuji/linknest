<?php

require __DIR__ . '/../includes/security.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/shares.php';
require __DIR__ . '/../includes/updater.php';

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

$_SERVER['REMOTE_ADDR'] = '203.0.113.10';
$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.24';
expect_true(real_ip(0, []) === '203.0.113.10', 'Proxy headers must be ignored without a trusted proxy.');
expect_true(real_ip(0, ['203.0.113.10']) === '198.51.100.24', 'Proxy headers may be used behind an explicitly trusted proxy.');
expect_true(pan_ip_matches_rule('10.1.2.3', '10.0.0.0/8'), 'Trusted proxy CIDR rules should match.');
expect_true(pan_normalize_filename(" report\r\n' + alert(1) + '.mp3 ") === "report' + alert(1) + '.mp3", 'Filename normalization should remove control characters.');
expect_true(strpos(pan_json_for_html("' </script>"), '<') === false, 'JavaScript values should be safely encoded for HTML script blocks.');

$fileToken = pan_create_file_access_token('abc123', $key, 60);
expect_true(pan_verify_file_access_token($fileToken, 'abc123', $key), 'File access token should validate for its file.');
expect_true(!pan_verify_file_access_token($fileToken, 'other', $key), 'File access token must not authorize another file.');

expect_true(pan_share_code_is_valid('Abc_123-x'), 'Share codes should allow URL-safe characters.');
expect_true(!pan_share_code_is_valid('bad code'), 'Share codes should reject spaces.');
$sharePassword = pan_share_password_hash('share-secret');
expect_true(pan_share_password_verify('share-secret', $sharePassword), 'Share password hashes should validate.');
expect_true(!pan_share_password_verify('wrong', $sharePassword), 'Wrong share passwords should fail.');
$shareToken = pan_create_share_access_token(['id'=>12, 'code'=>'Abc_123-x'], $key, 60);
expect_true(pan_verify_share_access_token($shareToken, ['id'=>12, 'code'=>'Abc_123-x'], $key), 'Share access tokens should be bound to a share.');
expect_true(!pan_verify_share_access_token($shareToken, ['id'=>13, 'code'=>'Abc_123-x'], $key), 'Share access tokens must not authorize another share.');

$hash = password_hash('correct horse battery staple', PASSWORD_DEFAULT);
expect_true(pan_verify_admin_password('correct horse battery staple', $hash), 'Password hash should validate.');
expect_true(!pan_verify_admin_password('incorrect', $hash), 'Wrong password should fail.');

$now = strtotime('2026-09-01 12:00:00');
expect_true(pan_expire_at_from_days(7, $now) === '2026-09-08 12:00:00', 'Expiry should be calculated from the requested number of days.');
expect_true(pan_file_access_error(['expire_at'=>'2026-09-01 11:59:59', 'count'=>0, 'max_downloads'=>0], $now) === 'expired', 'Expired shares should be rejected.');
expect_true(pan_file_access_error(['expire_at'=>null, 'count'=>3, 'max_downloads'=>3], $now) === 'limit', 'Shares at their access limit should be rejected.');
expect_true(pan_file_access_error(['expire_at'=>'2026-09-02 12:00:00', 'count'=>2, 'max_downloads'=>3], $now) === null, 'Active shares below their access limit should be allowed.');
expect_true(pan_share_access_error(['status'=>0, 'block'=>0, 'expire_at'=>null, 'max_accesses'=>0, 'access_count'=>0], $now) === 'revoked', 'Revoked shares should be rejected.');
expect_true(pan_share_access_error(['status'=>1, 'block'=>0, 'expire_at'=>'2026-09-01 11:59:59', 'max_accesses'=>0, 'access_count'=>0], $now) === 'expired', 'Expired share records should be rejected.');
expect_true(pan_share_access_error(['status'=>1, 'block'=>0, 'expire_at'=>null, 'max_accesses'=>1, 'access_count'=>1], $now) === 'limit', 'Shares at their access limit should be rejected.');
expect_true(pan_mask_ip('203.0.113.42') === '203.0.113.*', 'IPv4 access logs should mask the final octet.');
expect_true(substr(pan_mask_ip('2001:db8:abcd:1234::1'), -3) === '/48', 'IPv6 access logs should retain only a /48 prefix.');
expect_true(pan_normalize_site_url('pan.example.com') === 'https://pan.example.com/', 'Bare domains should normalize to HTTPS.');
expect_true(pan_normalize_site_url('https://pan.example.com/files') === 'https://pan.example.com/files/', 'Site paths should be preserved with a trailing slash.');
expect_true(pan_normalize_site_url('https://user:pass@pan.example.com/') === false, 'Credential-bearing site URLs should be rejected.');
expect_true(pan_update_allowed_url('https://codeload.github.com/hizhuji/linknest/zip/refs/tags/v6.5.0', ['codeload.github.com']), 'The official package host should be accepted.');
expect_true(!pan_update_allowed_url('http://codeload.github.com/hizhuji/linknest.zip', ['codeload.github.com']), 'Update URLs must use HTTPS.');
expect_true(pan_update_preserve_path('config.php'), 'The site configuration must be preserved during updates.');
expect_true(pan_update_preserve_path('file/example.bin'), 'Local uploads must be preserved during updates.');
expect_true(!pan_update_preserve_path('admin/index.php'), 'Application source files should be replaceable.');
$manifest = pan_update_validate_manifest(['version'=>'1650', 'version_name'=>'6.5.0', 'package_url'=>'https://codeload.github.com/hizhuji/linknest/zip/refs/tags/v6.5.0', 'sha256'=>str_repeat('a', 64), 'released_at'=>'2026-09-01', 'changelog'=>['Online updater'], 'database_version'=>'1004']);
expect_true($manifest['version'] === 1650 && $manifest['database_version'] === 1004, 'Update manifests should normalize numeric version fields.');

echo "security tests passed" . PHP_EOL;
