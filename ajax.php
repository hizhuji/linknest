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
	$row = $DB->getRow("SELECT * FROM pre_file WHERE hash=:hash AND deleted_at IS NULL", [':hash'=>$hash]);
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

	$row = $DB->getRow("SELECT * FROM pre_file WHERE hash=:hash AND deleted_at IS NULL", [':hash'=>$hash]);
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

	$row = $DB->getRow("SELECT * FROM pre_file WHERE hash=:hash AND deleted_at IS NULL", [':hash'=>$hash]);
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
	$refererMode = isset($_POST['referer_mode']) ? intval($_POST['referer_mode']) : intval($share['referer_mode']);
	if(!in_array($refererMode, [0, 1, 2], true))exit('{"code":-1,"msg":"来源规则无效"}');
	$refererRules = substr(trim(isset($_POST['referer_rules']) ? (string)$_POST['referer_rules'] : (string)$share['referer_rules']), 0, 4000);
	$allowEmptyReferer = !empty($_POST['allow_empty_referer']) ? 1 : 0;
	$uaBlocklist = substr(trim(isset($_POST['ua_blocklist']) ? (string)$_POST['ua_blocklist'] : (string)$share['ua_blocklist']), 0, 4000);
	$requestLimit = max(0, min(10000, intval(isset($_POST['request_limit']) ? $_POST['request_limit'] : $share['request_limit'])));
	$dailyTraffic = pan_gigabytes_to_bytes(isset($_POST['daily_traffic_gb']) ? $_POST['daily_traffic_gb'] : 0);
	$monthlyTraffic = pan_gigabytes_to_bytes(isset($_POST['monthly_traffic_gb']) ? $_POST['monthly_traffic_gb'] : 0);
	$webhookUrl = substr(trim(isset($_POST['webhook_url']) ? (string)$_POST['webhook_url'] : ''), 0, 1000);
	if($webhookUrl!=='' && pan_safe_webhook_target($webhookUrl)===false)exit('{"code":-1,"msg":"告警地址必须是可解析的公网 HTTPS 地址"}');
	$result = $DB->exec("UPDATE `pre_share` SET `expire_at`=:expire_at,`max_accesses`=:max_accesses,referer_mode=:referer_mode,referer_rules=:referer_rules,allow_empty_referer=:allow_empty_referer,ua_blocklist=:ua_blocklist,request_limit=:request_limit,daily_traffic_limit=:daily_traffic_limit,monthly_traffic_limit=:monthly_traffic_limit,webhook_url=:webhook_url WHERE `id`=:id", [':expire_at'=>$expire_at, ':max_accesses'=>$max_downloads, ':referer_mode'=>$refererMode, ':referer_rules'=>$refererRules, ':allow_empty_referer'=>$allowEmptyReferer, ':ua_blocklist'=>$uaBlocklist, ':request_limit'=>$requestLimit, ':daily_traffic_limit'=>$dailyTraffic, ':monthly_traffic_limit'=>$monthlyTraffic, ':webhook_url'=>($webhookUrl===''?null:$webhookUrl), ':id'=>intval($share['id'])]);
	if($result!==false)exit('{"code":0,"msg":"分享策略已更新"}');
	else exit('{"code":-1,"msg":"分享策略更新失败"}');
break;

case 'createShare':
	$sourceCode = isset($_POST['share_code']) ? trim($_POST['share_code']) : '';
	require_csrf_token();
	$sourceShare = pan_get_share_by_code($DB, $sourceCode);
	if(!$sourceShare)exit('{"code":-1,"msg":"原分享不存在"}');
	if(!pan_share_is_owner($sourceShare, $islogin, $islogin2, $uid, isset($_SESSION['shareids']) ? $_SESSION['shareids'] : []))exit('{"code":-1,"msg":"无权限"}');
	$customCode = isset($_POST['custom_code']) ? trim($_POST['custom_code']) : '';
	if($customCode!=='' && !pan_share_code_is_valid($customCode))exit('{"code":-1,"msg":"短码需为 6-64 位字母、数字、下划线或短横线"}');
	$password = !empty($_POST['use_password']) ? (string)$_POST['password'] : '';
	if($password!=='' && strlen($password)>128)exit('{"code":-1,"msg":"密码过长"}');
	$expireAt = pan_expire_at_from_days(isset($_POST['expire_days']) ? $_POST['expire_days'] : 0);
	$maxAccesses = pan_normalize_max_downloads(isset($_POST['max_accesses']) ? $_POST['max_accesses'] : 0);
	$oneTime = !empty($_POST['one_time']);
	$newShare = pan_create_share($DB, $sourceShare['file_id'], ['code'=>$customCode, 'password'=>$password, 'expire_at'=>$expireAt, 'max_accesses'=>$maxAccesses, 'one_time'=>$oneTime, 'uid'=>($islogin2 ? $uid : intval($sourceShare['created_by_uid']))]);
	if(!$newShare)exit('{"code":-1,"msg":"创建失败，短码可能已被使用"}');
	$_SESSION['shareids'][] = intval($newShare['id']);
	exit(json_encode(['code'=>0, 'msg'=>'新分享已创建', 'share_code'=>$newShare['code'], 'pageurl'=>$siteurl.'s.php?code='.rawurlencode($newShare['code'])]));
break;

case 'toggleShare':
	$sourceCode = isset($_POST['source_code']) ? trim($_POST['source_code']) : '';
	$targetCode = isset($_POST['target_code']) ? trim($_POST['target_code']) : '';
	require_csrf_token();
	$sourceShare = pan_get_share_by_code($DB, $sourceCode);
	$targetShare = pan_get_share_by_code($DB, $targetCode);
	if(!$sourceShare || !$targetShare || intval($sourceShare['file_id'])!==intval($targetShare['file_id']))exit('{"code":-1,"msg":"分享不存在"}');
	if(!pan_share_is_owner($sourceShare, $islogin, $islogin2, $uid, isset($_SESSION['shareids']) ? $_SESSION['shareids'] : []) || !pan_share_is_owner($targetShare, $islogin, $islogin2, $uid, isset($_SESSION['shareids']) ? $_SESSION['shareids'] : []))exit('{"code":-1,"msg":"无权限"}');
	$status = intval($targetShare['status'])===1 ? 0 : 1;
	$resetSql = $status===1 && intval($targetShare['one_time'])===1 ? ',access_count=0' : '';
	if($DB->exec("UPDATE pre_share SET status=:status{$resetSql} WHERE id=:id", [':status'=>$status, ':id'=>intval($targetShare['id'])])!==false)exit(json_encode(['code'=>0, 'msg'=>$status ? '分享已恢复' : '分享已撤销']));
	exit('{"code":-1,"msg":"操作失败"}');
break;

default:
	exit('{"code":-4,"msg":"No Act"}');
break;
}
