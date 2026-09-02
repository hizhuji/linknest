<?php
define('IN_ADMIN', true);
include("../includes/common.php");
if($islogin!=1) exit("<script language='javascript'>window.location.href='./login.php';</script>");
$stor = \lib\StorHelper::getModel($conf['storage']);
$title = '运维中心';
include './head.php';
$health = $DB->getAll("SELECT * FROM pre_system_health ORDER BY component ASC");
$trashCount = intval($DB->getColumn("SELECT count(*) FROM pre_file WHERE deleted_at IS NOT NULL"));
$versionCount = intval($DB->getColumn("SELECT count(*) FROM pre_file_version"));
?>
<div class="container" style="padding-top:70px;">
 <div class="col-xs-12 col-sm-10 col-lg-9 center-block" style="float:none;">
  <div class="row">
   <div class="col-sm-4"><div class="panel panel-warning"><div class="panel-heading">回收站</div><div class="panel-body"><strong><?php echo $trashCount?></strong> 个文件<br><small>保留 <?php echo max(1,intval($conf['trash_retention_days']))?> 天</small></div></div></div>
   <div class="col-sm-4"><div class="panel panel-info"><div class="panel-heading">历史版本</div><div class="panel-body"><strong><?php echo $versionCount?></strong> 个快照<br><small>最多 <?php echo max(1,intval($conf['file_version_max_count']))?> 个/文件</small></div></div></div>
   <div class="col-sm-4"><div class="panel panel-success"><div class="panel-heading">当前存储</div><div class="panel-body"><strong><?php echo htmlspecialchars($conf['storage'], ENT_QUOTES, 'UTF-8')?></strong><br><small>以实际探测结果为准</small></div></div></div>
  </div>
  <div class="panel panel-primary"><div class="panel-heading"><h3 class="panel-title">健康检查</h3></div><div class="panel-body"><p>检查数据库连接，并在当前存储创建、验证、删除一个临时探测对象。</p><button class="btn btn-primary" onclick="runHealth()">执行健康检查</button> <button class="btn btn-default" onclick="runMaintenance()">立即清理到期数据</button></div>
  <table class="table table-striped"><thead><tr><th>组件</th><th>状态</th><th>详情</th><th>最后检查</th></tr></thead><tbody><?php foreach((array)$health as $row){?><tr><td><?php echo htmlspecialchars($row['component'], ENT_QUOTES, 'UTF-8')?></td><td><?php echo htmlspecialchars($row['status'], ENT_QUOTES, 'UTF-8')?></td><td><?php echo htmlspecialchars($row['details'], ENT_QUOTES, 'UTF-8')?></td><td><?php echo htmlspecialchars($row['checked_at'], ENT_QUOTES, 'UTF-8')?></td></tr><?php }?><?php if(!$health){?><tr><td colspan="4" class="text-center text-muted">尚未执行检查</td></tr><?php }?></tbody></table></div>
  <div class="panel panel-primary"><div class="panel-heading"><h3 class="panel-title">备份与恢复记录</h3></div><div class="panel-body"><form id="backupForm" class="form-horizontal">
   <div class="form-group"><label class="col-sm-3 control-label">最近数据库备份</label><div class="col-sm-9"><input type="datetime-local" class="form-control" name="backup_database_at" value="<?php echo htmlspecialchars(str_replace(' ', 'T', substr((string)$conf['backup_database_at'], 0, 16)), ENT_QUOTES, 'UTF-8')?>"></div></div>
   <div class="form-group"><label class="col-sm-3 control-label">最近文件备份</label><div class="col-sm-9"><input type="datetime-local" class="form-control" name="backup_files_at" value="<?php echo htmlspecialchars(str_replace(' ', 'T', substr((string)$conf['backup_files_at'], 0, 16)), ENT_QUOTES, 'UTF-8')?>"></div></div>
   <div class="form-group"><label class="col-sm-3 control-label">最近恢复演练</label><div class="col-sm-9"><input type="datetime-local" class="form-control" name="backup_restore_drill_at" value="<?php echo htmlspecialchars(str_replace(' ', 'T', substr((string)$conf['backup_restore_drill_at'], 0, 16)), ENT_QUOTES, 'UTF-8')?>"></div></div>
   <div class="form-group"><label class="col-sm-3 control-label">说明</label><div class="col-sm-9"><textarea class="form-control" name="backup_note" maxlength="1000"><?php echo htmlspecialchars($conf['backup_note'], ENT_QUOTES, 'UTF-8')?></textarea></div></div>
   <div class="form-group"><div class="col-sm-offset-3 col-sm-9"><button type="button" class="btn btn-primary" onclick="saveBackup()">保存记录</button></div></div>
  </form></div></div>
  <div class="panel panel-primary"><div class="panel-heading"><h3 class="panel-title">数据保护与分享防护</h3></div><div class="panel-body"><form id="policyForm" class="form-horizontal">
   <div class="form-group"><label class="col-sm-3 control-label">回收站保留天数</label><div class="col-sm-9"><input type="number" class="form-control" name="trash_retention_days" min="1" max="3650" value="<?php echo max(1,intval($conf['trash_retention_days']))?>"><p class="help-block">计划任务会在超过保留期后彻底清理文件。</p></div></div>
   <div class="form-group"><label class="col-sm-3 control-label">版本保留天数</label><div class="col-sm-9"><input type="number" class="form-control" name="file_version_retention_days" min="1" max="3650" value="<?php echo max(1,intval($conf['file_version_retention_days']))?>"></div></div>
   <div class="form-group"><label class="col-sm-3 control-label">每个文件最多版本数</label><div class="col-sm-9"><input type="number" class="form-control" name="file_version_max_count" min="1" max="1000" value="<?php echo max(1,intval($conf['file_version_max_count']))?>"></div></div>
   <div class="form-group"><label class="col-sm-3 control-label">密码失败上限</label><div class="col-sm-9"><input type="number" class="form-control" name="share_password_limit" min="1" max="100" value="<?php echo max(1,intval($conf['share_password_limit']))?>"></div></div>
   <div class="form-group"><label class="col-sm-3 control-label">密码封禁秒数</label><div class="col-sm-9"><input type="number" class="form-control" name="share_password_window" min="60" max="86400" value="<?php echo max(60,intval($conf['share_password_window']))?>"></div></div>
   <div class="form-group"><div class="col-sm-offset-3 col-sm-9"><button type="button" class="btn btn-primary" onclick="savePolicy()">保存保护策略</button></div></div>
  </form></div></div>
  <div class="panel panel-default"><div class="panel-heading">维护计划任务</div><div class="panel-body"><code>php <?php echo htmlspecialchars(ROOT.'cron.php', ENT_QUOTES, 'UTF-8')?></code><p class="help-block">建议每日执行一次。任务只会清理超过保留期的回收站文件和过期历史版本。</p></div></div>
 </div>
</div>
<script src="https://s4.zstatic.net/ajax/libs/layer/3.1.1/layer.min.js"></script><script>
function postMaintenance(action, data, done){ $.post('ajax_maintenance.php?act='+action, data || {}, function(result){ if(result.code===0){ layer.msg(result.msg); if(done) done(); }else layer.alert(result.msg,{icon:2}); }, 'json'); }
function runHealth(){ postMaintenance('healthCheck', {}, function(){window.location.reload();}); }
function runMaintenance(){ if(confirm('立即清理已超过保留期的数据吗？')) postMaintenance('runMaintenance', {}, function(){window.location.reload();}); }
function saveBackup(){ postMaintenance('saveBackup', $('#backupForm').serialize(), function(){window.location.reload();}); }
function savePolicy(){ postMaintenance('savePolicy', $('#policyForm').serialize(), function(){window.location.reload();}); }
</script>
