<?php
if(PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'.PHP_EOL); }
$nosession = true;
$_SERVER['SCRIPT_NAME'] = '/cron.php';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
include __DIR__.'/includes/common.php';
$result = pan_run_maintenance($DB, $stor, $conf, 'cron');
$quotaUsers = pan_quota_reconcile_pending($DB, 200);
$apiUsagePruned = pan_prune_api_key_usage($DB, isset($conf['api_key_usage_retention_days']) ? $conf['api_key_usage_retention_days'] : 180);
echo 'LinkNest maintenance complete: files '.$result['purged_files'].', versions '.$result['purged_versions'].', objects '.$result['purged_objects'].', quota users '.$quotaUsers.', api usage pruned '.$apiUsagePruned.PHP_EOL;
