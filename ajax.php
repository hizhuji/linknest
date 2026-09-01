<?php
$nosecu = true;
include("./includes/common.php");
$act=isset($_GET['act'])?daddslashes($_GET['act']):null;

if(!checkRefererHost())exit('{"code":403}');

@header('Content-Type: application/json; charset=UTF-8');

if($islogin2 && $userrow['level']>0){
	$conf['upload_limit']=0;
	$conf['videoreview']=0;
	$conf['type_block']=null;
	$conf['name_block']=null;
}

switch($act){
case 'pre_upload':
	require_csrf_token();
	if($conf['forcelogin']==1 && !$islogin2)exit('{"code":-1,"msg":"请先登录"}');
	$name = pan_normalize_filename($_POST['name']);
	$hash = trim($_POST['hash']);
	$size = intval($_POST['size']);
	$hide = $_POST['show']==1?0:1;
	$ispwd = intval($_POST['ispwd']);
	$pwd = $ispwd==1?trim(htmlspecialchars($_POST['pwd'])):null;
	$expire_days = pan_normalize_expire_days(isset($_POST['expire_days']) ? $_POST['expire_days'] : 0);
	$expire_at = pan_expire_at_from_days($expire_days);
	$max_downloads = pan_normalize_max_downloads(isset($_POST['max_downloads']) ? $_POST['max_downloads'] : 0);
	if(empty($name))exit('{"code":-1,"msg":"文件名不能为空"}');
	if(!preg_match('/^[0-9a-z]{32}$/i', $hash))exit('{"code":-1,"msg":"hash error"}');
	if($ispwd==1 && !empty($pwd)){
		if (!preg_match('/^[a-zA-Z0-9]+$/', $pwd)) {
			exit('{"code":-1,"msg":"文件密码只能为字母和数字"}');
		}
	}
	$ext=get_file_ext($name);
	if($conf['type_block']){
		$type_block = explode('|',$conf['type_block']);
		if(in_array($ext,$type_block)){
			exit('{"code":-1,"msg":"文件上传失败，不支持上传该格式文件","error":"block"}');
		}
	}
	if($conf['name_block']){
		$name_block = explode('|',$conf['name_block']);
		foreach($name_block as $row){
			if(strpos($name,$row)!==false){
				exit('{"code":-1,"msg":"文件上传失败","error":"block"}');
			}
		}
	}
	$limit_size = intval($conf['upload_size']);
	if($limit_size > 0 && $size > $limit_size * 1024 * 1024){
		exit('{"code":-1,"msg":"上传文件大小限制'.$limit_size.'MB"}');
	}
	if($conf['upload_limit']>0){
		$thisday = date("Y-m-d 00:00:00");
		if($islogin2){
			$ipcount=$DB->getColumn("SELECT count(*) from pre_file WHERE uid='$uid' AND addtime>='".$thisday."'");
		}else{
			$ipcount=$DB->getColumn("SELECT count(*) from pre_file WHERE ip='$clientip' AND addtime>='".$thisday."'");
		}
		if($ipcount>$conf['upload_limit']){
			exit('{"code":-1,"msg":"你今天上传文件的数量已超过限制"}');
		}
	}
	$row = $DB->getRow("SELECT * FROM pre_file WHERE hash=:hash", [':hash'=>$hash]);
	if($row){
		$share = pan_create_share($DB, $row['id'], ['password'=>$pwd, 'expire_at'=>$expire_at, 'max_accesses'=>$max_downloads, 'uid'=>($uid?$uid:0)]);
		if(!$share)exit('{"code":-1,"msg":"创建分享链接失败"}');
		$_SESSION['shareids'][] = intval($share['id']);
		unset($_SESSION['csrf_token']);
		$result = ['code'=>1, 'msg'=>'文件已秒传并创建独立分享', 'exists'=>1, 'hash'=>$hash, 'name'=>$name, 'size'=>$size, 'type'=>$ext, 'id'=>$row['id'], 'share_code'=>$share['code'], 'pageurl'=>$siteurl.'s.php?code='.$share['code']];
		exit(json_encode($result));
	}

	if(\lib\StorHelper::supports_direct_upload() && $conf['uploadfile_type'] == 1){
		$param = $stor->getUploadParam($hash, $name, $limit_size * 1024 * 1024);
		if(!$param)exit('{"code":-1,"msg":"获取上传参数失败","errmsg":"'.$stor->errmsg().'"}');
		$_SESSION['upload'] = [
			'chunks' => 1,
			'name' => $name,
			'hash' => $hash,
			'size' => $size,
			'ext' => $ext,
			'hide' => $hide,
			'pwd' => $pwd,
			'expire_at' => $expire_at,
			'max_downloads' => $max_downloads
		];
		$result = ['code'=>0, 'third'=>true, 'hash'=>$hash, 'url'=>$param['url'], 'post'=>$param['post']];
		exit(json_encode($result));
	}else{
		$chunksize = 8 * 1024 * 1024; //分块上传，每块大小
		$chunks = ceil($size / $chunksize);
		$_SESSION['upload'] = [
			'chunks' => $chunks,
			'name' => $name,
			'hash' => $hash,
			'size' => $size,
			'ext' => $ext,
			'hide' => $hide,
			'pwd' => $pwd,
			'expire_at' => $expire_at,
			'max_downloads' => $max_downloads
		];
		$result = ['code'=>0, 'third'=>false, 'hash'=>$hash, 'chunksize'=>$chunksize, 'chunks'=>$chunks];
		exit(json_encode($result));
	}
break;

case 'upload_part':
	if(!isset($_FILES['file']))exit('{"code":-1,"msg":"请选择文件"}');
	require_csrf_token();
	if($conf['forcelogin']==1 && !$islogin2)exit('{"code":-1,"msg":"请先登录"}');
	$chunk = intval($_POST['chunk']);
	$hash = trim($_POST['hash']);
	if(!$_SESSION['upload'] || !$_SESSION['upload']['hash'] || $_SESSION['upload']['hash']!=$hash){
		exit('{"code":-1,"msg":"参数校验失败，请刷新页面重试"}');
	}
	if(!preg_match('/^[0-9a-z]{32}$/i', $hash))exit('{"code":-1,"msg":"hash error"}');
	$chunks = intval($_SESSION['upload']['chunks']);
	$ext = $_SESSION['upload']['ext'];
	if($chunks > 1){
		$tempFile = sys_get_temp_dir() . '/' . $hash. '.part'.$chunk;
		if(!move_uploaded_file($_FILES['file']['tmp_name'], $tempFile)){
			exit('{"code":-1,"msg":"文件第'.$chunk.'分块上传失败"}');
		}
		if($chunks == $chunk){
			$savePathTemp = file_part_merge($hash, $chunks);
			$real_hash = md5_file($savePathTemp);
			$real_size = filesize($savePathTemp);
			$result = $stor->savefile($hash, $savePathTemp, minetype($ext));
			if(!$result)exit('{"code":-1,"msg":"文件上传失败","error":"stor","errmsg":"'.$stor->errmsg().'"}');
		}else{
			$result = ['code'=>0, 'chunk'=>$chunk];
			exit(json_encode($result));
		}
	}else{
		$real_hash = md5_file($_FILES['file']['tmp_name']);
		$real_size = filesize($_FILES['file']['tmp_name']);
		$result = $stor->upload($hash, $_FILES['file']['tmp_name'], minetype($ext));
		if(!$result)exit('{"code":-1,"msg":"文件上传失败","error":"stor","errmsg":"'.$stor->errmsg().'"}');
	}

	$size = $_SESSION['upload']['size'];
	if($real_size != $size){
		exit('{"code":-1,"msg":"文件大小校验失败"}');
	}
	if($real_hash != $hash){
		exit('{"code":-1,"msg":"文件MD5校验失败"}');
	}

	$name = $_SESSION['upload']['name'];
	$hide = $_SESSION['upload']['hide'];
	$pwd = $_SESSION['upload']['pwd'];
	$expire_at = $_SESSION['upload']['expire_at'];
	$max_downloads = intval($_SESSION['upload']['max_downloads']);

	$row = $DB->getRow("SELECT * FROM pre_file WHERE hash=:hash", [':hash'=>$hash]);
	if($row){
		$share = pan_create_share($DB, $row['id'], ['password'=>$pwd, 'expire_at'=>$expire_at, 'max_accesses'=>$max_downloads, 'uid'=>($uid?$uid:0)]);
		if(!$share)exit('{"code":-1,"msg":"创建分享链接失败"}');
		$_SESSION['shareids'][] = intval($share['id']);
		unset($_SESSION['csrf_token']);
		unset($_SESSION['upload']);
		$result = ['code'=>1, 'msg'=>'文件已秒传并创建独立分享', 'exists'=>1, 'hash'=>$hash, 'name'=>$name, 'size'=>$size, 'type'=>$ext, 'id'=>$row['id'], 'share_code'=>$share['code'], 'pageurl'=>$siteurl.'s.php?code='.$share['code']];
		exit(json_encode($result));
	}

	$sds = $DB->exec("INSERT INTO `pre_file` (`name`,`type`,`size`,`hash`,`addtime`,`ip`,`hide`,`pwd`,`expire_at`,`max_downloads`,`uid`) values (:name,:type,:size,:hash,NOW(),:ip,:hide,:pwd,:expire_at,:max_downloads,:uid)", [':name'=>$name, ':type'=>$ext, ':size'=>$size, ':hash'=>$hash, ':ip'=>$clientip, ':hide'=>$hide, ':pwd'=>$pwd, ':expire_at'=>$expire_at, ':max_downloads'=>$max_downloads, ':uid'=>($uid?$uid:0)]);
	if(!$sds)exit('{"code":-1,"msg":"上传失败'.$DB->error().'","error":"database"}');
	$id = $DB->lastInsertId();
	$share = pan_create_share($DB, $id, ['password'=>$pwd, 'expire_at'=>$expire_at, 'max_accesses'=>$max_downloads, 'uid'=>($uid?$uid:0)]);
	if(!$share)exit('{"code":-1,"msg":"文件已保存，但创建分享链接失败"}');

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
	
	$_SESSION['fileids'][] = $id;
	$_SESSION['shareids'][] = intval($share['id']);
	unset($_SESSION['csrf_token']);
	unset($_SESSION['upload']);
	$result = ['code'=>1, 'msg'=>'文件上传成功！', 'exists'=>0, 'hash'=>$hash, 'name'=>$name, 'size'=>$size, 'type'=>$ext, 'id'=>$id, 'share_code'=>$share['code'], 'pageurl'=>$siteurl.'s.php?code='.$share['code']];
	exit(json_encode($result));
break;

case 'complete_upload':
	require_csrf_token();
	if($conf['forcelogin']==1 && !$islogin2)exit('{"code":-1,"msg":"请先登录"}');
	$hash = trim($_POST['hash']);
	if(!$_SESSION['upload'] || !$_SESSION['upload']['hash'] || $_SESSION['upload']['hash']!=$hash){
		exit('{"code":-1,"msg":"参数校验失败，请刷新页面重试"}');
	}
	if(!preg_match('/^[0-9a-z]{32}$/i', $hash))exit('{"code":-1,"msg":"hash error"}');
	
	if(!$stor->exists($hash)){
		exit('{"code":-1,"msg":"文件上传失败","error":"stor","errmsg":"'.$stor->errmsg().'"}');
	}

	$name = $_SESSION['upload']['name'];
	$size = $_SESSION['upload']['size'];
	$ext = $_SESSION['upload']['ext'];
	$hide = $_SESSION['upload']['hide'];
	$pwd = $_SESSION['upload']['pwd'];
	$expire_at = $_SESSION['upload']['expire_at'];
	$max_downloads = intval($_SESSION['upload']['max_downloads']);

	$row = $DB->getRow("SELECT * FROM pre_file WHERE hash=:hash", [':hash'=>$hash]);
	if($row){
		$share = pan_create_share($DB, $row['id'], ['password'=>$pwd, 'expire_at'=>$expire_at, 'max_accesses'=>$max_downloads, 'uid'=>($uid?$uid:0)]);
		if(!$share)exit('{"code":-1,"msg":"创建分享链接失败"}');
		$_SESSION['shareids'][] = intval($share['id']);
		unset($_SESSION['csrf_token']);
		unset($_SESSION['upload']);
		$result = ['code'=>1, 'msg'=>'文件已秒传并创建独立分享', 'exists'=>1, 'hash'=>$hash, 'name'=>$name, 'size'=>$size, 'type'=>$ext, 'id'=>$row['id'], 'share_code'=>$share['code'], 'pageurl'=>$siteurl.'s.php?code='.$share['code']];
		exit(json_encode($result));
	}

	$sds = $DB->exec("INSERT INTO `pre_file` (`name`,`type`,`size`,`hash`,`addtime`,`ip`,`hide`,`pwd`,`expire_at`,`max_downloads`,`uid`) values (:name,:type,:size,:hash,NOW(),:ip,:hide,:pwd,:expire_at,:max_downloads,:uid)", [':name'=>$name, ':type'=>$ext, ':size'=>$size, ':hash'=>$hash, ':ip'=>$clientip, ':hide'=>$hide, ':pwd'=>$pwd, ':expire_at'=>$expire_at, ':max_downloads'=>$max_downloads, ':uid'=>($uid?$uid:0)]);
	if(!$sds)exit('{"code":-1,"msg":"上传失败'.$DB->error().'","error":"database"}');
	$id = $DB->lastInsertId();
	$share = pan_create_share($DB, $id, ['password'=>$pwd, 'expire_at'=>$expire_at, 'max_accesses'=>$max_downloads, 'uid'=>($uid?$uid:0)]);
	if(!$share)exit('{"code":-1,"msg":"文件已保存，但创建分享链接失败"}');

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
	
	$_SESSION['fileids'][] = $id;
	$_SESSION['shareids'][] = intval($share['id']);
	unset($_SESSION['csrf_token']);
	unset($_SESSION['upload']);
	$result = ['code'=>1, 'msg'=>'文件上传成功！', 'exists'=>0, 'hash'=>$hash, 'name'=>$name, 'size'=>$size, 'type'=>$ext, 'id'=>$id, 'share_code'=>$share['code'], 'pageurl'=>$siteurl.'s.php?code='.$share['code']];
	exit(json_encode($result));
break;

case 'deleteFile':
	$shareCode = isset($_POST['share_code']) ? trim($_POST['share_code']) : exit('{"code":-1,"msg":"缺少分享码"}');
	require_csrf_token();
	$share = pan_get_share_by_code($DB, $shareCode);
	if(!$share)exit('{"code":-1,"msg":"分享不存在"}');
	if(!pan_share_is_owner($share, $islogin, $islogin2, $uid, isset($_SESSION['shareids']) ? $_SESSION['shareids'] : []))exit('{"code":-1,"msg":"无权限"}');
	if(intval($share['block'])===1)exit('{"code":-1,"msg":"文件已被冻结，无法删除"}');
	if(!$islogin2 && strtotime($share['created_at'])<strtotime("-7 days"))exit('{"code":-1,"msg":"无法删除7天前创建的分享"}');
	if(pan_delete_share($DB, $stor, $share))exit('{"code":0,"msg":"分享已删除"}');
	else exit('{"code":-1,"msg":"分享删除失败['.$DB->error().']"}');
break;

case 'updateAccessPolicy':
	$shareCode = isset($_POST['share_code']) ? trim($_POST['share_code']) : exit('{"code":-1,"msg":"缺少分享码"}');
	require_csrf_token();
	$share = pan_get_share_by_code($DB, $shareCode);
	if(!$share)exit('{"code":-1,"msg":"分享不存在"}');
	if(!pan_share_is_owner($share, $islogin, $islogin2, $uid, isset($_SESSION['shareids']) ? $_SESSION['shareids'] : []))exit('{"code":-1,"msg":"无权限"}');
	$expire_days = pan_normalize_expire_days(isset($_POST['expire_days']) ? $_POST['expire_days'] : 0);
	$expire_at = pan_expire_at_from_days($expire_days);
	$max_downloads = pan_normalize_max_downloads(isset($_POST['max_downloads']) ? $_POST['max_downloads'] : 0);
	$result = $DB->exec("UPDATE `pre_share` SET `expire_at`=:expire_at,`max_accesses`=:max_accesses WHERE `id`=:id", [':expire_at'=>$expire_at, ':max_accesses'=>$max_downloads, ':id'=>intval($share['id'])]);
	if($result!==false)exit('{"code":0,"msg":"分享策略已更新"}');
	else exit('{"code":-1,"msg":"分享策略更新失败"}');
break;

default:
	exit('{"code":-4,"msg":"No Act"}');
break;
}
