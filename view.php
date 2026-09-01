<?php
$nosession=true;
$nosecu=true;
include("./includes/common.php");

$urlarr=explode('/',$_SERVER['PATH_INFO']);
if (($length = count($urlarr)) > 1) {
$url = $urlarr[$length-1];
}
$extension=explode('&',$url);
if (($length = count($extension)) > 1) {
$pwd = $extension[$length-1];
$url = $extension[0];
}

if(strpos($url,".")){
    $hash=substr($url,0,strpos($url,"."));
}else{
    $hash=$url;
}

$row = $DB->getRow("SELECT * FROM `pre_file` WHERE `hash`=:hash limit 1", [':hash'=>$hash]);
if(!$row) exit;
if($row['block']>=1){
    header("Content-type: ".minetype('gif'));
    readfile(ROOT.'assets/img/block.gif');
    exit;
}
$access_error = pan_file_access_error($row);
if($access_error){
    http_response_code(410);
    exit(pan_file_access_message($access_error));
}
if($row['pwd']!=null && $row['pwd']!=$pwd){
    http_response_code(403);
    exit('Password required');
}

if ($stor->exists($row['hash'])) {
    if(is_view($row['type']))
    {
        if(!pan_record_file_access($DB, $row)){
            http_response_code(410);
            exit(pan_file_access_message('limit'));
        }

        file_output($hash, $row['type'], $row['size'], $row['name'], true, isset($_GET['greencheck']));
    }
}
