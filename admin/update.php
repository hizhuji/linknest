<?php
define('IN_ADMIN', true);
include('../includes/common.php');
if($islogin!=1) exit("<script language='javascript'>window.location.href='./login.php';</script>");
require_once SYSTEM_ROOT.'updater.php';

$act = isset($_GET['act']) ? $_GET['act'] : null;
if($act){
	require_post_request();
	require_csrf_token();
	@header('Content-Type: application/json; charset=UTF-8');
	try{
		$manifest = pan_update_fetch_manifest();
		if($act === 'check'){
			exit(json_encode([
				'code' => 0,
				'current_version' => VERSION,
				'current_version_name' => VERSION_NAME,
				'latest_version' => (string)$manifest['version'],
				'version_name' => $manifest['version_name'],
				'released_at' => $manifest['released_at'],
				'changelog' => $manifest['changelog'],
				'has_update' => $manifest['version'] > intval(VERSION),
				'compatible' => $manifest['min_version'] === 0 || intval(VERSION) >= $manifest['min_version'],
			], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
		}
		if($act === 'install'){
			$result = pan_update_install($manifest);
			exit(json_encode([
				'code' => 0,
				'msg' => '程序文件更新成功',
				'version_name' => $manifest['version_name'],
				'backup' => $result['backup'],
				'database_update' => $result['database_update'],
			], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
		}
		exit('{"code":-1,"msg":"未知操作"}');
	}catch(Throwable $e){
		exit(json_encode(['code'=>-1, 'msg'=>$e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
	}
}

$title = '在线更新';
include './head.php';
$csrf_token = pan_csrf_token();
$zip_ready = class_exists('ZipArchive');
$curl_ready = function_exists('curl_init');
$write_ready = is_writable(ROOT) && is_writable(ROOT.'install');
?>
<div class="container" style="padding-top:70px;">
  <div class="col-xs-12 col-sm-10 col-lg-8 center-block" style="float:none;">
    <div class="panel panel-primary">
      <div class="panel-heading"><h3 class="panel-title"><i class="fa fa-refresh"></i> 在线更新</h3></div>
      <div class="panel-body">
        <div class="row">
          <div class="col-sm-6">
            <p class="text-muted">当前程序版本</p>
            <p style="font-size:24px;font-weight:600;">V<?php echo htmlspecialchars(VERSION_NAME, ENT_QUOTES, 'UTF-8'); ?></p>
          </div>
          <div class="col-sm-6">
            <p class="text-muted">运行环境</p>
            <p><span class="label label-<?php echo $curl_ready?'success':'danger'; ?>">cURL <?php echo $curl_ready?'可用':'缺失'; ?></span>&nbsp;
            <span class="label label-<?php echo $zip_ready?'success':'danger'; ?>">ZipArchive <?php echo $zip_ready?'可用':'缺失'; ?></span>&nbsp;
            <span class="label label-<?php echo $write_ready?'success':'danger'; ?>">目录写入 <?php echo $write_ready?'可用':'受限'; ?></span></p>
          </div>
        </div>
        <hr/>
        <div id="update-status" class="alert alert-info">点击下方按钮检查 LinkNest 仓库中的最新稳定版本。</div>
        <div id="release-info" style="display:none;">
          <h4 id="release-title"></h4>
          <p class="text-muted" id="release-date"></p>
          <ul id="release-changelog"></ul>
        </div>
        <div class="text-center" style="margin-top:20px;">
          <button type="button" class="btn btn-primary" id="check-update"><i class="fa fa-search"></i> 检查更新</button>
          <button type="button" class="btn btn-success" id="install-update" style="display:none;"><i class="fa fa-cloud-download"></i> 备份并立即更新</button>
        </div>
      </div>
      <div class="panel-footer">
        更新会自动保护 <code>config.php</code>、本地上传目录和安装锁，并在覆盖程序文件前创建备份。更新期间请勿关闭页面或重复点击。
      </div>
    </div>
  </div>
</div>
<script src="https://s4.zstatic.net/ajax/libs/layer/2.3/layer.js"></script>
<script>
(function(){
  var csrfToken = <?php echo json_encode($csrf_token); ?>;
  var canInstall = <?php echo $curl_ready && $zip_ready && $write_ready ? 'true' : 'false'; ?>;
  var checking = false;

  function setStatus(message, type){
    $('#update-status').attr('class', 'alert alert-' + type).text(message);
  }

  function requestUpdate(action, done){
    $.ajax({
      type: 'POST',
      url: 'update.php?act=' + action,
      data: {csrf_token: csrfToken},
      dataType: 'json',
      timeout: action === 'install' ? 240000 : 30000,
      success: done,
      error: function(xhr){
        var message = '服务器响应异常';
        if(xhr.responseJSON && xhr.responseJSON.msg) message = xhr.responseJSON.msg;
        setStatus(message, 'danger');
        checking = false;
        $('#check-update, #install-update').prop('disabled', false);
      }
    });
  }

  $('#check-update').on('click', function(){
    if(checking) return;
    checking = true;
    $('#check-update, #install-update').prop('disabled', true);
    setStatus('正在连接 LinkNest 仓库检查新版本...', 'info');
    requestUpdate('check', function(data){
      checking = false;
      $('#check-update').prop('disabled', false);
      if(data.code !== 0){
        setStatus(data.msg || '检查更新失败', 'danger');
        return;
      }
      $('#release-info').show();
      $('#release-title').text('最新稳定版：V' + data.version_name);
      $('#release-date').text('发布日期：' + data.released_at);
      $('#release-changelog').empty();
      $.each(data.changelog || [], function(_, item){
        $('<li>').text(item).appendTo('#release-changelog');
      });
      if(!data.has_update){
        setStatus('当前已经是最新版本。', 'success');
        $('#install-update').hide();
      }else if(!data.compatible){
        setStatus('当前版本过旧，不能直接在线更新，请先安装兼容过渡版本。', 'warning');
        $('#install-update').hide();
      }else if(!canInstall){
        setStatus('检测到新版本，但服务器扩展或程序目录写入权限不满足要求。', 'warning');
        $('#install-update').hide();
      }else{
        setStatus('检测到可安装的新版本。', 'success');
        $('#install-update').show().prop('disabled', false);
      }
    });
  });

  $('#install-update').on('click', function(){
    if(!confirm('更新前将自动备份程序文件。确定立即更新吗？')) return;
    $('#check-update, #install-update').prop('disabled', true);
    setStatus('正在下载、校验并安装更新，请勿关闭页面...', 'info');
    requestUpdate('install', function(data){
      if(data.code !== 0){
        setStatus(data.msg || '更新失败', 'danger');
        $('#check-update, #install-update').prop('disabled', false);
        return;
      }
      var message = '更新完成，已生成备份：' + data.backup;
      if(data.database_update){
        setStatus(message + '。还需要执行数据库迁移。', 'warning');
        layer.alert('程序文件已更新，请继续完成数据库升级。', {icon: 1}, function(){window.location.href='../install/update.php';});
      }else{
        setStatus(message, 'success');
        layer.alert('已更新到 V' + data.version_name, {icon: 1}, function(){window.location.reload();});
      }
    });
  });
})();
</script>
