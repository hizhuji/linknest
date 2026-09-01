<?php
error_reporting(E_ERROR | E_WARNING | E_PARSE);
define('IN_ADMIN', true);
$install = true;
require '../includes/common.php';
if($islogin !== 1){
	header('Location: ../admin/login.php');
	exit;
}

@header('Content-Type: text/html; charset=UTF-8');

if($_SERVER['REQUEST_METHOD'] !== 'POST'){
	$csrfToken = pan_csrf_token();
	exit('<!doctype html><html lang="zh-CN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>数据库升级</title><style>body{font-family:Arial,"Microsoft YaHei",sans-serif;max-width:680px;margin:80px auto;padding:24px;color:#222}.box{border:1px solid #ddd;padding:24px}button{background:#1677ff;color:#fff;border:0;padding:10px 20px;cursor:pointer}</style></head><body><div class="box"><h2>LinkNest 数据库升级</h2><p>升级前请确认已经备份数据库。此操作只允许当前登录的管理员执行。</p><form method="post"><input type="hidden" name="csrf_token" value="'.htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8').'"><button type="submit">确认执行升级</button></form></div></body></html>');
}
require_csrf_token();

try{
	$db=new PDO("mysql:host=".$dbconfig['host'].";dbname=".$dbconfig['dbname'].";port=".$dbconfig['port'],$dbconfig['user'],$dbconfig['pwd']);
}catch(Exception $e){
	exit('链接数据库失败:'.$e->getMessage());
}
date_default_timezone_set("PRC");
$date = date("Y-m-d");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
$db->exec("set sql_mode = ''");
$db->exec("set names utf8mb4");

$version = 0;
if($rs = $db->query("SELECT v FROM pre_config WHERE k='version'")){
	$version = $rs->fetchColumn();
}

if($version>=1008){
	exit('你的网站已经升级到最新版本了');
}
$sqls = [];
if($version<1001){
	$sqls = array_merge($sqls, explode(';', file_get_contents(__DIR__.'/update.sql')));
	if(!$db->query("SELECT v FROM pre_config WHERE k='syskey'")->fetchColumn()){
		$sqls[]="REPLACE INTO `pre_config` VALUES ('syskey', '".pan_random_string(64)."')";
	}
}
if($version<1002){
	$sqls = array_merge($sqls, explode(';', file_get_contents(__DIR__.'/update_1002.sql')));
	$sqls[]="REPLACE INTO `pre_config` VALUES ('version', '1002')";
}
if($version<1003){
	$sqls = array_merge($sqls, explode(';', file_get_contents(__DIR__.'/update_1003.sql')));
	$sqls[]="REPLACE INTO `pre_config` VALUES ('version', '1003')";
}
if($version<1004){
	$sqls = array_merge($sqls, explode(';', file_get_contents(__DIR__.'/update_1004.sql')));
	$sqls[]="REPLACE INTO `pre_config` VALUES ('version', '1004')";
}
if($version<1005){
	$sqls = array_merge($sqls, explode(';', file_get_contents(__DIR__.'/update_1005.sql')));
	$sqls[]="REPLACE INTO `pre_config` VALUES ('version', '1005')";
}
if($version<1006){
	$sqls = array_merge($sqls, explode(';', file_get_contents(__DIR__.'/update_1006.sql')));
	$sqls[]="REPLACE INTO `pre_config` VALUES ('version', '1006')";
}
if($version<1007){
	$sqls = array_merge($sqls, explode(';', file_get_contents(__DIR__.'/update_1007.sql')));
	$sqls[]="REPLACE INTO `pre_config` VALUES ('version', '1007')";
}
if($version<1008){
	$sqls = array_merge($sqls, explode(';', file_get_contents(__DIR__.'/update_1008.sql')));
	$sqls[]="REPLACE INTO `pre_config` VALUES ('version', '1008')";
}
$success=0;$error=0;$errorMsg=null;
foreach ($sqls as $value) {
	$value=trim($value);
	if(empty($value))continue;
	if($db->exec($value)===false){
		$error++;
		$dberror=$db->errorInfo();
		$errorMsg.=$dberror[2]."<br>";
	}else{
		$success++;
	}
}
echo '成功执行SQL语句'.$success.'条！<br/>';
if($errorMsg){
//echo '<div class="alert alert-danger text-center" role="alert">'.$errorMsg.'</div>';
}
exit("<script language='javascript'>alert('网站数据库升级完成！');window.location.href='../';</script>");
?>
