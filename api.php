<?php
$nosession = true;
$nosecu = true;
include("./includes/common.php");

function showresult($arr, $format='json'){
	$format = isset($_POST['format'])?$_POST['format']:'json';
	if(!in_array($format, ['json', 'jsonp', 'form'], true))$format = 'json';
	if($format == 'json'){
		@header('Content-Type: application/json; charset=UTF-8');
		exit(json_encode($arr));
	}elseif($format == 'jsonp'){
		$callback = isset($_POST['callback'])?$_POST['callback']:'callback';
		if(!valid_jsonp_callback($callback)){
			@header('Content-Type: application/json; charset=UTF-8');
			exit(json_encode(['code'=>-1, 'msg'=>'无效的 JSONP 回调名称']));
		}
		@header('Content-Type: application/javascript; charset=UTF-8');
		exit($callback.'('.json_encode($arr).')');
	}else{
		@header('Content-Type: text/html; charset=UTF-8');
		if($arr['code']==0){
			$backurl = isset($_POST['backurl'])?$_POST['backurl']:(isset($_SERVER['HTTP_REFERER'])?$_SERVER['HTTP_REFERER']:'');
			if(!valid_redirect_url($backurl))$backurl = '';
			if(!$backurl)sysmsg('无效的跳转地址');
			$downurl = htmlspecialchars($arr['downurl'], ENT_QUOTES, 'UTF-8');
			$type = htmlspecialchars($arr['type'], ENT_QUOTES, 'UTF-8');
			$name = htmlspecialchars($arr['name'], ENT_QUOTES, 'UTF-8');
			$backurl = htmlspecialchars($backurl, ENT_QUOTES, 'UTF-8');
echo '<html>
<head>
<meta http-equiv="content-type" content="text/html;charset=utf-8"/>
<meta name="viewport" content="width=device-width">
<title>文件上传页面</title>
</head>
<body>
<form action="'.$backurl.'" method="post">
<input name="file" type="hidden" value="'.$downurl.'" />
<input name="type" type="hidden" value="'.$type.'" />
<input name="name" type="hidden" value="'.$name.'" />
<input name="submit" type="submit" value="下一步" />
</form>
</body></html>';
exit;
		}else{
			sysmsg($arr['msg']);
		}
	}
}

if(!$conf['api_open'])showresult(['code'=>-4, 'msg'=>'当前站点未开启上传API']);

if(isset($conf['api_require_token']) && $conf['api_require_token'] == 1){
	$api_token = isset($_SERVER['HTTP_X_API_KEY']) ? $_SERVER['HTTP_X_API_KEY'] : (isset($_POST['api_token']) ? $_POST['api_token'] : '');
	if(empty($conf['api_token']) || !hash_equals($conf['api_token'], $api_token))showresult(['code'=>-4, 'msg'=>'API 密钥不正确']);
}

if(!empty($conf['api_referer'])){
	$referers = array_map('strtolower', array_filter(array_map('trim', explode('|',$conf['api_referer']))));
	$url_arr = isset($_SERVER['HTTP_REFERER']) ? parse_url($_SERVER['HTTP_REFERER']) : false;
	$host = is_array($url_arr) && isset($url_arr['host']) ? strtolower($url_arr['host']) : '';
	if(!$host || !in_array($host, $referers, true))showresult(['code'=>-4, 'msg'=>'来源地址不正确']);
}


if(!isset($_FILES['file']))showresult(['code'=>-1, 'msg'=>'请选择文件']);
if($_SERVER['REQUEST_METHOD'] !== 'POST')showresult(['code'=>-1, 'msg'=>'请求方式错误']);
if($_FILES['file']['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($_FILES['file']['tmp_name']))showresult(['code'=>-1, 'msg'=>'文件上传失败']);
$name=pan_normalize_filename($_FILES['file']['name']);
$size=intval($_FILES['file']['size']);
$hide = isset($_POST['show']) && $_POST['show']==1?0:1;
$ispwd = isset($_POST['ispwd']) ? intval($_POST['ispwd']) : 0;
$pwd = $ispwd==1 && isset($_POST['pwd'])?trim(htmlspecialchars($_POST['pwd'])):null;
$expire_days = pan_normalize_expire_days(isset($_POST['expire_days']) ? $_POST['expire_days'] : 0);
$expire_at = pan_expire_at_from_days($expire_days);
$max_downloads = pan_normalize_max_downloads(isset($_POST['max_downloads']) ? $_POST['max_downloads'] : 0);
if(empty($name))showresult(['code'=>-1, 'msg'=>'文件名不能为空']);
if(!empty($conf['upload_size']) && $size > intval($conf['upload_size']) * 1024 * 1024)showresult(['code'=>-1, 'msg'=>'上传文件大小超过限制']);
if($ispwd==1 && !empty($pwd)){
	if (!preg_match('/^[a-zA-Z0-9]+$/', $pwd)) {
		showresult(['code'=>-1, 'msg'=>'文件密码只能为字母和数字']);
	}
}
$ext=get_file_ext($name);
if($conf['type_block']){
	$type_block = explode('|',$conf['type_block']);
	if(in_array($ext,$type_block)){
		showresult(['code'=>-1, 'msg'=>'文件上传失败', 'error'=>'block']);
	}
}
if($conf['name_block']){
	$name_block = explode('|',$conf['name_block']);
	foreach($name_block as $row){
		if(strpos($name,$row)!==false){
			showresult(['code'=>-1, 'msg'=>'文件上传失败', 'error'=>'block']);
		}
	}
}
if(!empty($conf['upload_limit'])){
	$thisday = date("Y-m-d 00:00:00");
	$ipcount = $DB->getColumn('SELECT count(*) FROM pre_file WHERE ip=:ip AND addtime>=:addtime', [':ip'=>$clientip, ':addtime'=>$thisday]);
	if($ipcount >= intval($conf['upload_limit']))showresult(['code'=>-1, 'msg'=>'你今天上传文件的数量已超过限制']);
}
$hash = md5_file($_FILES['file']['tmp_name']);
$row = $DB->getRow("SELECT * FROM pre_file WHERE hash=:hash", [':hash'=>$hash]);
if($row){
	$downurl = $siteurl.'down.php/'.$row['hash'].'.'.$row['type'];
	if(!empty($row['pwd']))$downurl = $siteurl.'file.php?hash='.$row['hash'];
	$result = ['code'=>0, 'msg'=>'本站已存在该文件', 'exists'=>1, 'hash'=>$hash, 'name'=>$name, 'size'=>$size, 'type'=>$ext, 'id'=>$row['id'], 'downurl'=>$downurl];
	if(is_view($row['type']))$result['viewurl'] = $siteurl.'view.php/'.$hash.'.'.$row['type'];
	showresult($result);
}
$result = $stor->upload($hash, $_FILES['file']['tmp_name'], minetype($ext));
if(!$result)showresult(['code'=>-1, 'msg'=>'文件上传失败', 'error'=>'stor']);
$sds = $DB->exec("INSERT INTO `pre_file` (`name`,`type`,`size`,`hash`,`addtime`,`ip`,`hide`,`pwd`,`expire_at`,`max_downloads`) values (:name,:type,:size,:hash,NOW(),:ip,:hide,:pwd,:expire_at,:max_downloads)", [':name'=>$name, ':type'=>$ext, ':size'=>$size, ':hash'=>$hash, ':ip'=>$clientip, ':hide'=>$hide, ':pwd'=>$pwd, ':expire_at'=>$expire_at, ':max_downloads'=>$max_downloads]);
if(!$sds)showresult(['code'=>-1, 'msg'=>'上传失败'.$DB->error(), 'error'=>'database']);
$id = $DB->lastInsertId();

$type_image = explode('|',$conf['type_image']);
$type_video = explode('|',$conf['type_video']);
if($conf['green_check']>0 && in_array($ext,$type_image)){
	if(checkImage($hash, $ext)){
		$DB->exec("UPDATE `pre_file` SET `block`=1 WHERE `id`='{$id}' LIMIT 1");
	}
}
if($conf['videoreview']==1 && in_array($ext,$type_video)){
	$DB->exec("UPDATE `pre_file` SET `block`=2 WHERE `id`='{$id}' LIMIT 1");
}

$downurl = $siteurl.'down.php/'.$hash.'.'.$ext;
if(!empty($pwd))$downurl = $siteurl.'file.php?hash='.$hash;
$result = ['code'=>0, 'msg'=>'文件上传成功！', 'exists'=>0, 'hash'=>$hash, 'name'=>$name, 'size'=>$size, 'type'=>$ext, 'id'=>$id, 'downurl'=>$downurl];
if(is_view($ext))$result['viewurl'] = $siteurl.'view.php/'.$hash.'.'.$ext;
showresult($result);
