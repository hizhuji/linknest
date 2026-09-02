<?php
include("../includes/common.php");
if($islogin==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");
$act=isset($_GET['act'])?daddslashes($_GET['act']):null;

if(!checkRefererHost())exit('{"code":403}');

if(in_array($act, ['setBlock', 'delFile', 'operation', 'saveFileInfo', 'restoreFile', 'purgeFile', 'replaceFile', 'restoreVersion'], true)){
	require_post_request();
	require_csrf_token();
}

@header('Content-Type: application/json; charset=UTF-8');

switch($act){
case 'fileList':
	$conditions=['1=1'];
	$params=[];
	$dstatus = isset($_POST['dstatus']) ? intval($_POST['dstatus']) : -1;
	if($dstatus === 3){
		$conditions[]='`deleted_at` IS NOT NULL';
	}else{
		$conditions[]='`deleted_at` IS NULL';
	}
	if(isset($_POST['uid']) && !empty($_POST['uid'])) {
		$uid = intval($_POST['uid']);
		$conditions[]='`uid`=:uid';
		$params[':uid']=$uid;
	}
	if($dstatus >= 0 && $dstatus <= 2) {
		$conditions[]='`block`=:block';
		$params[':block']=$dstatus;
	}
	if(isset($_POST['kw']) && !empty($_POST['kw'])) {
		$type = intval($_POST['type']);
		$kw = trim($_POST['kw']);
		if($type == 1){
			$conditions[]='`name` LIKE :kw';
			$params[':kw']='%'.$kw.'%';
		}elseif($type == 2){
			$conditions[]='`hash`=:hash';
			$params[':hash']=$kw;
		}elseif($type == 3){
			$conditions[]='`type`=:type';
			$params[':type']=$kw;
		}elseif($type == 4){
			$conditions[]='`ip`=:ip';
			$params[':ip']=$kw;
		}
	}
	if(isset($_POST['tag']) && trim($_POST['tag']) !== ''){
		$conditions[]='EXISTS (SELECT 1 FROM pre_file_tag admin_ft INNER JOIN pre_tag admin_t ON admin_t.id=admin_ft.tag_id WHERE admin_ft.file_id=pre_file.id AND admin_t.name LIKE :tag)';
		$params[':tag']='%'.trim($_POST['tag']).'%';
	}
	if(!empty($_POST['favorite'])) $conditions[]='EXISTS (SELECT 1 FROM pre_file_favorite admin_fav WHERE admin_fav.file_id=pre_file.id)';
	if(isset($_POST['min_size']) && $_POST['min_size'] !== ''){ $conditions[]='`size`>=:min_size'; $params[':min_size']=max(0, intval(floatval($_POST['min_size'])*1048576)); }
	if(isset($_POST['max_size']) && $_POST['max_size'] !== ''){ $conditions[]='`size`<=:max_size'; $params[':max_size']=max(0, intval(floatval($_POST['max_size'])*1048576)); }
	if(!empty($_POST['date_from'])){ $conditions[]='`addtime`>=:date_from'; $params[':date_from']=date('Y-m-d 00:00:00', strtotime($_POST['date_from'])); }
	if(!empty($_POST['date_to'])){ $conditions[]='`addtime`<:date_to'; $params[':date_to']=date('Y-m-d 00:00:00', strtotime($_POST['date_to'].' +1 day')); }
	if($_POST['orderby'] == 1){
		$orderby = 'count desc';
	}else{
		$orderby = 'id desc';
	}
	$offset = max(0, intval($_POST['offset']));
	$limit = min(100, max(1, intval($_POST['limit'])));
	$sql = implode(' AND ', $conditions);
	$total = $DB->getColumn("SELECT count(*) from pre_file WHERE {$sql}", $params);
	$list = $DB->getAll("SELECT * FROM pre_file WHERE {$sql} order by {$orderby} limit $offset,$limit", $params);
	$list2 = [];
	foreach($list as $row){
		$row['icon'] = type_to_icon($row['type']);
		$row['view'] = is_view($row['type']);
		$row['view_type'] = get_view_type($row['type']);
		$row['size'] = size_format($row['size']);

		$row['fileurl'] = './down.php/'.$row['hash'].'.'.($row['type']?$row['type']:'file');
		$row['viewurl'] = './view.php/'.$row['hash'].'.'.($row['type']?$row['type']:'file');
		$share = pan_get_default_share_by_hash($DB, $row['hash']);
		$row['pageurl'] = $share ? '../s.php?code='.rawurlencode($share['code']) : '';
		$row['share_count'] = intval($DB->getColumn("SELECT count(*) FROM pre_share WHERE file_id=:file_id", [':file_id'=>intval($row['id'])]));
		$row['version_count'] = intval($DB->getColumn("SELECT count(*) FROM pre_file_version WHERE file_id=:file_id", [':file_id'=>intval($row['id'])]));
		$row['is_deleted'] = !empty($row['deleted_at']);
		$row['tag_names'] = implode(', ', array_map(function($tag){ return $tag['name']; }, $DB->getAll("SELECT t.name FROM pre_file_tag ft INNER JOIN pre_tag t ON t.id=ft.tag_id WHERE ft.file_id=:file_id ORDER BY t.name", [':file_id'=>intval($row['id'])])));
		$row['favorite_count'] = intval($DB->getColumn("SELECT count(*) FROM pre_file_favorite WHERE file_id=:file_id", [':file_id'=>intval($row['id'])]));

		$list2[] = $row;
	}

	exit(json_encode(['total'=>$total, 'rows'=>$list2]));
break;
case 'setBlock':
	$id=intval($_POST['id']);
	$status=intval($_POST['status']);
	$sql = "UPDATE pre_file SET `block`='$status' WHERE id='$id' AND deleted_at IS NULL";
	if($DB->exec($sql)!==false){
		pan_audit_admin_action($DB, $conf['admin_user'], $status ? 'file_blocked' : 'file_unblocked', 'file', $id, []);
		exit('{"code":0,"msg":"修改成功！"}');
	}
	else exit('{"code":-1,"msg":"修改失败['.$DB->error().']"}');
break;
case 'delFile':
	$id=intval($_POST['id']);
	if(pan_soft_delete_file($DB, $id, $conf['admin_user']))exit('{"code":0,"msg":"文件已移入回收站，可在保留期内恢复。"}');
	else exit('{"code":-1,"msg":"移入回收站失败，文件可能不存在或已删除。"}');
break;
case 'restoreFile':
	$id=intval($_POST['id']);
	if(pan_restore_file($DB, $id, $conf['admin_user']))exit('{"code":0,"msg":"文件已恢复。"}');
	exit('{"code":-1,"msg":"恢复失败，文件可能不在回收站。"}');
break;
case 'purgeFile':
	$id=intval($_POST['id']);
	if(pan_purge_file($DB, $stor, $id, $conf['admin_user']))exit('{"code":0,"msg":"文件已彻底删除。"}');
	exit('{"code":-1,"msg":"彻底删除未完成。请检查存储状态后重试。"}');
break;
case 'operation':
	$status=intval($_POST['status']);
	$checkbox=$_POST['checkbox'];
	if(!$checkbox)exit('{"code":-1,"msg":"未选中文件"}');
	$i=0;
	if($status == 4)$opname = '彻底删除';
	elseif($status == 3)$opname = '恢复';
	elseif($status == 2)$opname = '解封';
	elseif($status == 1)$opname = '封禁';
	else $opname = '移入回收站';
	foreach($checkbox as $id){
		$id = intval($id);
		if($status == 0){
			pan_soft_delete_file($DB, $id, $conf['admin_user']);
		}elseif($status == 1){
			$DB->exec("UPDATE pre_file SET `block`=1 WHERE id='$id'");
			pan_audit_admin_action($DB, $conf['admin_user'], 'file_blocked', 'file', $id, []);
		}elseif($status == 2){
			$DB->exec("UPDATE pre_file SET `block`=0 WHERE id='$id'");
			pan_audit_admin_action($DB, $conf['admin_user'], 'file_unblocked', 'file', $id, []);
		}elseif($status == 3){
			pan_restore_file($DB, $id, $conf['admin_user']);
		}elseif($status == 4){
			pan_purge_file($DB, $stor, $id, $conf['admin_user']);
		}
		$i++;
	}
	exit('{"code":0,"msg":"成功'.$opname.$i.'个文件"}');
break;
case 'getFileInfo':
	$id=intval($_GET['id']);
	$row=$DB->getRow("select * from pre_file where id='$id' limit 1");
	if(!$row)
		exit('{"code":-1,"msg":"当前文件不存在！"}');
	$share = pan_get_default_share_by_hash($DB, $row['hash']);
	$row['code'] = 0;
	$row['size2'] = size_format($row['size']);
	$row['has_password'] = $share && !empty($share['password']);
	$row['pwd'] = '';
	$row['expire_at'] = $share ? $share['expire_at'] : null;
	$row['max_downloads'] = $share ? intval($share['max_accesses']) : 0;
	$row['version_count'] = intval($DB->getColumn("SELECT count(*) FROM pre_file_version WHERE file_id=:file_id", [':file_id'=>$id]));
	exit(json_encode($row));
break;
case 'saveFileInfo':
	$id = intval($_POST['id']);
	$name = pan_normalize_filename($_POST['name']);
	$type = strtolower(trim((string)$_POST['type']));
	if(!preg_match('/^[a-z0-9]{1,50}$/', $type))exit('{"code":-1,"msg":"文件类型格式不正确"}');
	$hide = intval($_POST['hide']);
	$ispwd = intval($_POST['ispwd']);
	$pwd = $ispwd==1 ? trim((string)$_POST['pwd']) : null;
	$expire_at_input = isset($_POST['expire_at']) ? trim($_POST['expire_at']) : '';
	$expire_at = null;
	if($expire_at_input !== ''){
		$expire_timestamp = strtotime($expire_at_input);
		if($expire_timestamp === false)exit('{"code":-1,"msg":"有效期格式不正确"}');
		$expire_at = date('Y-m-d H:i:s', $expire_timestamp);
	}
	$max_downloads = pan_normalize_max_downloads(isset($_POST['max_downloads']) ? $_POST['max_downloads'] : 0);
	if(empty($name))exit('{"code":-1,"msg":"文件名称不能为空"}');
	if($ispwd==1 && !empty($pwd)){
        if (!preg_match('/^[a-zA-Z0-9]+$/', $pwd)) {
			exit('{"code":-1,"msg":"下载密码只能为字母和数字"}');
        }
	}
	$file = $DB->getRow("SELECT * FROM pre_file WHERE id=:id LIMIT 1", [':id'=>$id]);
	if(!$file)exit('{"code":-1,"msg":"当前文件不存在！"}');
	$share = pan_get_default_share_by_hash($DB, $file['hash']);
	$data = [':id'=>$id, ':name'=>$name, ':type'=>$type, ':hide'=>$hide];
	$sql = "UPDATE `pre_file` SET `name`=:name,`type`=:type,`hide`=:hide WHERE `id`=:id";
	$result = $DB->exec($sql, $data);
	if($result!==false && $share){
		$shareData = [':id'=>intval($share['id']), ':expire_at'=>$expire_at, ':max_accesses'=>$max_downloads];
		$passwordSql = '';
		if($ispwd===0){
			$passwordSql = ',`password`=NULL';
		}elseif($pwd!==''){
			$passwordSql = ',`password`=:password';
			$shareData[':password'] = pan_share_password_hash($pwd);
		}
		$result = $DB->exec("UPDATE pre_share SET expire_at=:expire_at,max_accesses=:max_accesses{$passwordSql} WHERE id=:id", $shareData);
	}
	if($result!==false){
		pan_audit_admin_action($DB, $conf['admin_user'], 'file_metadata_updated', 'file', $id, []);
		exit('{"code":0,"msg":"修改文件信息成功！"}');
	}
	else exit('{"code":-1,"msg":"修改文件信息失败['.$DB->error().']"}');
break;
case 'replaceFile':
	$id = intval($_POST['id']);
	if(!isset($_FILES['replacement']) || $_FILES['replacement']['error'] !== UPLOAD_ERR_OK){
		exit('{"code":-1,"msg":"请选择要替换的新文件。"}');
	}
	$result = pan_replace_file_object($DB, $stor, $id, $_FILES['replacement']['tmp_name'], $_FILES['replacement']['name'], $conf['admin_user'], isset($_POST['note']) ? $_POST['note'] : '', $conf);
	exit(json_encode(['code'=>$result['ok'] ? 0 : -1, 'msg'=>$result['message']]));
break;
case 'restoreVersion':
	$fileId = intval($_POST['file_id']);
	$versionId = intval($_POST['version_id']);
	if(pan_restore_file_version($DB, $stor, $fileId, $versionId, $conf['admin_user']))exit('{"code":0,"msg":"历史版本已恢复，当前版本已自动保存为新快照。"}');
	exit('{"code":-1,"msg":"恢复历史版本失败，请确认版本文件仍存在于当前存储后端。"}');
break;
default:
	exit('{"code":-4,"msg":"No Act"}');
break;
}
