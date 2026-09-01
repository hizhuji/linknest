<?php
error_reporting(E_ERROR | E_PARSE | E_COMPILE_ERROR);
if(defined('IN_CRONLITE'))return;
define('IN_CRONLITE', true);
define('SYSTEM_ROOT', dirname(__FILE__).'/');
define('ROOT', dirname(SYSTEM_ROOT).'/');
define('VERSION', '1670');
define('VERSION_NAME', '6.7.0');
define('DB_VERSION', '1008');
date_default_timezone_set('Asia/Shanghai');
$date = date("Y-m-d H:i:s");

include_once(SYSTEM_ROOT.'security.php');
if(!$nosession)pan_start_session();

include_once(SYSTEM_ROOT.'txprotect.php');
include_once(SYSTEM_ROOT."autoloader.php");
Autoloader::register();

if (!file_exists(ROOT.'config.php')) {
	header('Content-type:text/html;charset=utf-8');
	echo '你还没安装！<a href="./install/">点此安装</a>';
	exit();
}
require ROOT.'config.php';

if(!$dbconfig['user']||!$dbconfig['pwd']||!$dbconfig['dbname'])//检测安装1
{
header('Content-type:text/html;charset=utf-8');
echo '你还没安装！<a href="./install/">点此安装</a>';
exit();
}

$DB = new \lib\PdoHelper($dbconfig);

if($DB->query("select * from pre_config where 1")==FALSE)//检测安装2
{
header('Content-type:text/html;charset=utf-8');
echo '你还没安装！<a href="./install/">点此安装</a>';
exit();
}

include_once(SYSTEM_ROOT."functions.php");
include_once(SYSTEM_ROOT."shares.php");

$conf=getAllSetting();
define('SYS_KEY', $conf['syskey']);

if (!$conf['version'] || $conf['version'] < DB_VERSION) {
    if (!$install) {
		header('Content-type:text/html;charset=utf-8');
        echo '请先完成网站升级！<a href="/install/update.php"><font color=red>点此升级</font></a>';
        exit;
    }
}

$scriptpath=str_replace('\\','/',$_SERVER['SCRIPT_NAME']);
$sitepath = substr($scriptpath, 0, strrpos($scriptpath, '/'));
if(defined('IN_ADMIN') && substr($sitepath, -6) === '/admin') $sitepath = substr($sitepath, 0, -6);
$detected_siteurl = pan_normalize_site_url((is_https() ? 'https://' : 'http://').$_SERVER['HTTP_HOST'].$sitepath.'/');
if($detected_siteurl === false) $detected_siteurl = '/';
$configured_siteurl = pan_normalize_site_url(isset($conf['site_url']) ? $conf['site_url'] : '');
$siteurl = $configured_siteurl !== false && $configured_siteurl !== '' ? $configured_siteurl : $detected_siteurl;

$ipType = isset($conf['ip_type']) ? intval($conf['ip_type']) : 2;
$trustedProxies = isset($conf['trusted_proxy_ips']) ? preg_split('/[\s,|]+/', $conf['trusted_proxy_ips'], -1, PREG_SPLIT_NO_EMPTY) : [];
$clientip=real_ip($ipType, $trustedProxies);
$islogin=0;
$islogin2=0;
$uid=0;
if(isset($_COOKIE["admin_token"]))
{
	$token = pan_read_auth_token($_COOKIE['admin_token'], SYS_KEY);
	if($token && isset($token['type'], $token['user'], $token['sid']) && $token['type'] === 'admin'){
		$session = hash_hmac('sha256', $conf['admin_user']."\0".$conf['admin_pwd'], SYS_KEY);
		if(hash_equals($conf['admin_user'], $token['user']) && hash_equals($session, $token['sid'])) $islogin=1;
	}
}
if(isset($_COOKIE["user_token"]))
{
	$token = pan_read_auth_token($_COOKIE['user_token'], SYS_KEY);
	if($token && isset($token['type'], $token['uid'], $token['sid']) && $token['type'] === 'user'){
		if($userrow = $DB->getRow("SELECT * FROM pre_user WHERE uid=:uid LIMIT 1", [':uid'=>intval($token['uid'])])){
			$session = hash_hmac('sha256', $userrow['type']."\0".$userrow['openid'], SYS_KEY);
			if(hash_equals($session, $token['sid'])) {
				if($userrow['enable']==1) {
					$islogin2=1;
					$uid=(int)$userrow['uid'];
				}
				else $_SESSION['user_block'] = true;
			}
		}
	}
}

if(defined('IN_ADMIN')) return;

$denyip = explode('|',$conf['blackip']);
if(in_array($clientip,$denyip) && !$islogin){
	Header("HTTP/1.1 403 Forbidden");
	exit;
}

include_once(SYSTEM_ROOT."vendor/autoload.php");

//加载存储模块
$stor = \lib\StorHelper::getModel($conf['storage']);

if (!file_exists(ROOT.'install/install.lock') && file_exists(ROOT.'install/index.php')) {
	sysmsg('<h2>检测到无 install.lock 文件</h2><ul><li><font size="4">如果您尚未安装本程序，请<a href="./install/">前往安装</a></font></li><li><font size="4">如果您已经安装本程序，请手动放置一个空的 install.lock 文件到 /install 文件夹下，<b>为了您站点安全，在您完成它之前我们不会工作。</b></font></li></ul><br/><h4>为什么必须建立 install.lock 文件？</h4>它是安装保护文件，如果检测不到它，就会认为站点还没安装，此时任何人都可以安装/重装你的网站。<br/><br/>');exit;
}
?>
