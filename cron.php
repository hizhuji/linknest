<?php
if(PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'.PHP_EOL); }
$nosession = true;
$_SERVER['SCRIPT_NAME'] = '/cron.php';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
include __DIR__.'/includes/common.php';
$result = pan_run_maintenance($DB, $stor, $conf, 'cron');
echo 'LinkNest maintenance complete: files '.$result['purged_files'].', versions '.$result['purged_versions'].', objects '.$result['purged_objects'].PHP_EOL;
