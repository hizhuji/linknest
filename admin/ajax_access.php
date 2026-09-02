<?php
define('IN_ADMIN', true);
include '../includes/common.php';
@header('Content-Type: application/json; charset=UTF-8');
if($islogin !== 1) exit(json_encode(['code'=>-1, 'msg'=>'请先登录']));
$act = isset($_GET['act']) ? $_GET['act'] : '';
if(in_array($act, ['save_quota','rebuild_quota','create_key','revoke_key'], true)){
    require_post_request();
    require_csrf_token();
}
if($act === 'user_usage'){
    $uid = intval(isset($_GET['uid']) ? $_GET['uid'] : 0);
    $user = $DB->getRow("SELECT uid,nickname FROM pre_user WHERE uid=:uid LIMIT 1", [':uid'=>$uid]);
    if(!$user) exit(json_encode(['code'=>-1, 'msg'=>'用户不存在']));
    $usage = pan_quota_usage($DB, $uid);
    $limits = pan_quota_limits($DB, $uid, $conf);
    exit(json_encode(['code'=>0, 'user'=>$user, 'usage'=>$usage, 'limits'=>$limits]));
}
if($act === 'save_quota'){
    $uid = intval($_POST['uid']);
    if(!$DB->getColumn("SELECT uid FROM pre_user WHERE uid=:uid", [':uid'=>$uid])) exit(json_encode(['code'=>-1, 'msg'=>'用户不存在']));
    $bytes = max(0, intval(floatval($_POST['storage_mb']) * 1048576));
    $files = max(0, intval($_POST['file_limit']));
    $daily = max(0, intval(floatval($_POST['daily_mb']) * 1048576));
    $ok = $DB->exec("INSERT INTO pre_user_quota (uid,byte_limit,file_limit,daily_upload_limit,updated_by,updated_at) VALUES (:uid,:bytes,:files,:daily,:actor,NOW()) ON DUPLICATE KEY UPDATE byte_limit=VALUES(byte_limit),file_limit=VALUES(file_limit),daily_upload_limit=VALUES(daily_upload_limit),updated_by=VALUES(updated_by),updated_at=NOW()", [':uid'=>$uid, ':bytes'=>$bytes, ':files'=>$files, ':daily'=>$daily, ':actor'=>$conf['admin_user']]) !== false;
    if($ok) pan_audit_admin_action($DB, $conf['admin_user'], 'user_quota_updated', 'user', $uid, ['byte_limit'=>$bytes, 'file_limit'=>$files, 'daily_upload_limit'=>$daily]);
    exit(json_encode(['code'=>$ok?0:-1, 'msg'=>$ok?'用户配额已保存':'用户配额保存失败']));
}
if($act === 'rebuild_quota'){
    $uid = intval($_POST['uid']);
    $usage = pan_quota_rebuild_user($DB, $uid);
    pan_audit_admin_action($DB, $conf['admin_user'], 'user_usage_rebuilt', 'user', $uid, $usage);
    exit(json_encode(['code'=>0, 'msg'=>'用量已按现有文件重新计算', 'usage'=>$usage]));
}
if($act === 'list_keys'){
    $uid = intval(isset($_GET['uid']) ? $_GET['uid'] : 0);
    $where = $uid > 0 ? 'WHERE k.uid=:uid' : '';
    $params = $uid > 0 ? [':uid'=>$uid] : [];
    $rows = $DB->getAll("SELECT k.*,u.nickname,COALESCE(today.requests,0) AS today_requests,COALESCE(today.bytes,0) AS today_bytes FROM pre_api_key k LEFT JOIN pre_user u ON u.uid=k.uid LEFT JOIN pre_api_key_usage today ON today.key_id=k.id AND today.usage_date=CURDATE() {$where} ORDER BY k.id DESC LIMIT 200", $params);
    exit(json_encode(['code'=>0, 'rows'=>$rows]));
}
if($act === 'create_key'){
    $uid = intval($_POST['uid']);
    if(!$DB->getColumn("SELECT uid FROM pre_user WHERE uid=:uid AND enable=1", [':uid'=>$uid])) exit(json_encode(['code'=>-1, 'msg'=>'用户不存在或已被禁用']));
    $expires = trim(isset($_POST['expires_at']) ? $_POST['expires_at'] : '');
    $expiresAt = null;
    if($expires !== ''){
        $timestamp = strtotime($expires);
        if($timestamp === false || $timestamp <= time()) exit(json_encode(['code'=>-1, 'msg'=>'有效期必须是未来的时间']));
        $expiresAt = date('Y-m-d H:i:s', $timestamp);
    }
    $key = pan_api_key_create($DB, $uid, isset($_POST['name']) ? $_POST['name'] : '', isset($_POST['scopes']) ? $_POST['scopes'] : [], $expiresAt, isset($_POST['ip_rules']) ? $_POST['ip_rules'] : '', max(0, intval($_POST['request_limit'])), pan_gigabytes_to_bytes(isset($_POST['daily_traffic_gb']) ? $_POST['daily_traffic_gb'] : 0));
    if(!$key) exit(json_encode(['code'=>-1, 'msg'=>'请至少选择一个权限范围']));
    pan_audit_admin_action($DB, $conf['admin_user'], 'api_key_created', 'api_key', $key['id'], ['uid'=>$uid, 'prefix'=>$key['prefix'], 'scopes'=>$key['scopes'], 'expires_at'=>$expiresAt]);
    exit(json_encode(['code'=>0, 'msg'=>'API Key 已创建，请立即保存完整 Key。', 'secret'=>$key['secret'], 'id'=>$key['id']]));
}
if($act === 'revoke_key'){
    $id = intval($_POST['id']);
    $active = $DB->getColumn("SELECT id FROM pre_api_key WHERE id=:id AND revoked_at IS NULL", [':id'=>$id]);
    $ok = $active && $DB->exec("UPDATE pre_api_key SET revoked_at=NOW() WHERE id=:id AND revoked_at IS NULL", [':id'=>$id]) !== false;
    if($ok) pan_audit_admin_action($DB, $conf['admin_user'], 'api_key_revoked', 'api_key', $id, []);
    exit(json_encode(['code'=>$ok?0:-1, 'msg'=>$ok?'API Key 已撤销':'API Key 不存在或已撤销']));
}
exit(json_encode(['code'=>-4, 'msg'=>'No Act']));
