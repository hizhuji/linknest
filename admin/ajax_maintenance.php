<?php
define('IN_ADMIN', true);
include("../includes/common.php");
if($islogin!=1) exit('{"code":403,"msg":"未登录"}');
if(!checkRefererHost()) exit('{"code":403,"msg":"来源校验失败"}');
require_post_request();
require_csrf_token();
@header('Content-Type: application/json; charset=UTF-8');
$act = isset($_GET['act']) ? $_GET['act'] : '';
if($act === 'healthCheck'){
    $stor = \lib\StorHelper::getModel($conf['storage']);
    $result = pan_run_health_check($DB, $stor);
    pan_audit_admin_action($DB, $conf['admin_user'], 'health_check_run', 'maintenance', '', $result);
    exit(json_encode(['code'=>0, 'msg'=>$result['database'] && $result['storage'] ? '健康检查通过。' : '健康检查完成，请查看失败组件。']));
}
if($act === 'runMaintenance'){
    $stor = \lib\StorHelper::getModel($conf['storage']);
    $result = pan_run_maintenance($DB, $stor, $conf, $conf['admin_user']);
    exit(json_encode(['code'=>0, 'msg'=>'清理完成：文件 '.$result['purged_files'].' 个，历史版本 '.$result['purged_versions'].' 个，存储对象 '.$result['purged_objects'].' 个。']));
}
if($act === 'saveBackup'){
    foreach(['backup_database_at','backup_files_at','backup_restore_drill_at'] as $field){
        $value = isset($_POST[$field]) ? trim($_POST[$field]) : '';
        if($value !== ''){
            $time = strtotime($value);
            if($time === false) exit('{"code":-1,"msg":"备份时间格式不正确"}');
            $value = date('Y-m-d H:i:s', $time);
        }
        saveSetting($field, $value);
    }
    saveSetting('backup_note', substr(trim(isset($_POST['backup_note']) ? $_POST['backup_note'] : ''), 0, 1000));
    pan_audit_admin_action($DB, $conf['admin_user'], 'backup_status_updated', 'maintenance');
    exit('{"code":0,"msg":"备份记录已保存。"}');
}
if($act === 'savePolicy'){
    $fields = ['trash_retention_days'=>[1,3650], 'file_version_retention_days'=>[1,3650], 'file_version_max_count'=>[1,1000], 'share_password_limit'=>[1,100], 'share_password_window'=>[60,86400]];
    foreach($fields as $field=>$bounds){
        $value = isset($_POST[$field]) ? intval($_POST[$field]) : $bounds[0];
        saveSetting($field, max($bounds[0], min($bounds[1], $value)));
    }
    pan_audit_admin_action($DB, $conf['admin_user'], 'protection_policy_updated', 'maintenance');
    exit('{"code":0,"msg":"保护策略已保存。"}');
}
exit('{"code":-1,"msg":"未知操作"}');
