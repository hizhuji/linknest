<?php
include './includes/common.php';
@header('Content-Type: application/json; charset=UTF-8');
if(!$islogin2) exit(json_encode(['code'=>-1, 'msg'=>'请先登录']));
if($_SERVER['REQUEST_METHOD'] !== 'POST' || !pan_verify_request_csrf_token()) exit(json_encode(['code'=>-1, 'msg'=>'请求校验失败']));
$act = isset($_GET['act']) ? $_GET['act'] : '';
$fileId = isset($_POST['file_id']) ? intval($_POST['file_id']) : 0;
if($act === 'tags'){
    $tags = isset($_POST['tags']) ? $_POST['tags'] : '';
    if(!pan_set_file_tags($DB, $fileId, $uid, $tags)) exit(json_encode(['code'=>-1, 'msg'=>'标签保存失败或文件不属于当前账户']));
    exit(json_encode(['code'=>0, 'msg'=>'标签已保存', 'tags'=>pan_file_tags($DB, $fileId, $uid)]));
}
if($act === 'favorite'){
    if(!pan_toggle_file_favorite($DB, $fileId, $uid, !empty($_POST['favorite']))) exit(json_encode(['code'=>-1, 'msg'=>'收藏操作失败或文件不属于当前账户']));
    exit(json_encode(['code'=>0, 'msg'=>!empty($_POST['favorite']) ? '已收藏' : '已取消收藏']));
}
exit(json_encode(['code'=>-4, 'msg'=>'No Act']));
