<?php
define('VERSION', '1641');
require __DIR__ . '/../includes/updater.php';

function updaterAssert($condition, $message) {
	if(!$condition) throw new RuntimeException($message);
}

updaterAssert(pan_update_allowed_url('https://raw.githubusercontent.com/hizhuji/pan/main/releases/pan-v6.4.1.zip', pan_update_package_hosts()), 'Raw GitHub package should be trusted');
updaterAssert(pan_update_allowed_url('https://codeload.github.com/hizhuji/pan/zip/refs/tags/v6.4.1', pan_update_package_hosts()), 'Codeload package should be trusted');
updaterAssert(!pan_update_allowed_url('http://raw.githubusercontent.com/hizhuji/pan/main/package.zip', pan_update_package_hosts()), 'HTTP package must be rejected');
updaterAssert(!pan_update_allowed_url('https://example.com/package.zip', pan_update_package_hosts()), 'Unknown package host must be rejected');

$manifest = pan_update_validate_manifest([
	'version'=>'1641', 'version_name'=>'6.4.1', 'released_at'=>'2026-09-01',
	'package_url'=>'https://raw.githubusercontent.com/hizhuji/pan/main/releases/pan-v6.4.1.zip',
	'package_urls'=>['https://codeload.github.com/hizhuji/pan/zip/refs/tags/v6.4.1'],
	'sha256'=>str_repeat('a', 64), 'changelog'=>['Updater fallback'],
]);
updaterAssert($manifest['version'] === 1641, 'Manifest version should be normalized');

echo "updater tests passed" . PHP_EOL;
