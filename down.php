<?php
$nosession=true;
$nosecu=true;
include("./includes/common.php");

$urlarr=explode('/',$_SERVER['PATH_INFO']);
if (($length = count($urlarr)) > 1) {
$url = $urlarr[$length-1];
}
if(strpos($url,".")){
    $hash=substr($url,0,strpos($url,"."));
}else{
    $hash=$url;
}

$shareCode = isset($_GET['share']) ? trim($_GET['share']) : '';
$share = $shareCode !== '' ? pan_get_share_by_code($DB, $shareCode) : pan_get_default_share_by_hash($DB, $hash);
if(!$share || !hash_equals((string)$share['hash'], (string)$hash))exit('404 Not Found');
$row = $share;
$access_error = pan_share_access_error($share);
if($access_error){
    http_response_code(410);
    exit(pan_share_access_message($access_error));
}

$accessToken = isset($_GET['access']) ? trim($_GET['access']) : '';
if($share['password']!=null && !pan_verify_share_access_token($accessToken, $share, SYS_KEY)){
    header('Location: '.$siteurl.'file.php?share='.rawurlencode($share['code']));
    exit;
}

$billableBytes = pan_requested_bytes($row['size']);
$controlError = pan_enforce_share_controls($DB, $share, $clientip, $billableBytes);
if($controlError){
    pan_share_alert($DB, $share, $controlError, pan_share_control_message($controlError).'；IP：'.pan_mask_ip($clientip));
    http_response_code(in_array($controlError, ['referer', 'user_agent'], true) ? 403 : ($controlError === 'rate' ? 429 : 509));
    exit(pan_share_control_message($controlError));
}

if($stor->exists($hash))
{
    if(!pan_record_share_access($DB, $share)){
        http_response_code(410);
        exit(pan_share_access_message('limit'));
    }
	pan_record_share_event($DB, $share, 'download', $billableBytes, $clientip, SYS_KEY, isset($conf['access_log_retention_days']) ? $conf['access_log_retention_days'] : 30);

    file_output($hash, $row['type'], $row['size'], $row['name']);
}
else{
    exit('File Not Found');
}
