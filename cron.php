<?php
if(PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'.PHP_EOL); }
$nosession = true;
$_SERVER['SCRIPT_NAME'] = '/cron.php';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
include __DIR__.'/includes/common.php';
$result = pan_run_maintenance($DB, $stor, $conf, 'cron');
$quotaUsers = $DB->getAll("SELECT uid FROM pre_user WHERE uid>0");
foreach((array)$quotaUsers as $quotaUser) pan_quota_rebuild_user($DB, intval($quotaUser['uid']));
echo 'LinkNest maintenance complete: files '.$result['purged_files'].', versions '.$result['purged_versions'].', objects '.$result['purged_objects'].', quota users '.count($quotaUsers).PHP_EOL;
