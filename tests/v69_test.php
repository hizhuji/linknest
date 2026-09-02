<?php
require __DIR__ . '/../includes/security.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/organization.php';
require __DIR__ . '/../includes/api_keys.php';

function v69_expect($value, $message) {
    if(!$value) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
}

class V69FakeDb {
    public $params;
    public function exec($sql, $params = []) { $this->params = $params; return true; }
    public function lastInsertId() { return 42; }
}

v69_expect(pan_normalize_tag_name("  项目\n资料  ") === '项目 资料', 'Tags should normalize surrounding and repeated whitespace.');
v69_expect(count(pan_normalize_api_scopes(['files.upload', 'invalid', 'files.upload'])) === 1, 'Scopes should be allowlisted and deduplicated.');
v69_expect(pan_api_key_ip_allowed('203.0.113.0/24, 2001:db8::/32', '203.0.113.8'), 'API Key IPv4 CIDR should match.');
v69_expect(!pan_api_key_ip_allowed('203.0.113.0/24', '198.51.100.1'), 'API Key IP rules should reject unlisted addresses.');
$db = new V69FakeDb();
$created = pan_api_key_create($db, 1000, 'test key', ['files.upload', 'invalid']);
v69_expect(is_array($created) && strpos($created['secret'], 'lnk_') === 0, 'New API Key should return an LNK secret once.');
v69_expect(password_verify($created['secret'], $db->params[':hash']), 'Only a password hash should be sent to persistence.');
v69_expect(!in_array($created['secret'], $db->params, true), 'The raw API Key must never be persisted.');
v69_expect(pan_api_key_record_daily_event($db, 42, 'denied', 'scope'), 'Denied API Key attempts should aggregate into a daily record.');
v69_expect($db->params[':reason'] === 'scope', 'Daily denied-event aggregation should retain only the reason code.');

echo "v6.9 tests passed\n";
