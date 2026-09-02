<?php
define('IN_ADMIN', true);
include '../includes/common.php';
$title = '账户与接口';
include './head.php';
if($islogin !== 1) exit("<script>location.href='./login.php'</script>");
?>
<div class="container" style="padding-top:70px;max-width:1050px">
  <div class="row"><div class="col-md-5">
    <div class="panel panel-primary"><div class="panel-heading"><h3 class="panel-title">用户配额与用量</h3></div><div class="panel-body">
      <form id="quotaForm" class="form-horizontal">
        <div class="form-group"><label class="col-sm-4 control-label">用户 UID</label><div class="col-sm-8"><input class="form-control" type="number" min="1" name="uid" required onblur="loadUsage()"></div></div>
        <div id="usageInfo" class="alert alert-info">填写 UID 后可查看当前用量。回收站文件仍占用配额，彻底删除后释放。</div>
        <div class="form-group"><label class="col-sm-4 control-label">存储上限</label><div class="col-sm-8"><div class="input-group"><input class="form-control" type="number" min="0" step="1" name="storage_mb"><span class="input-group-addon">MB</span></div></div></div>
        <div class="form-group"><label class="col-sm-4 control-label">文件数量上限</label><div class="col-sm-8"><input class="form-control" type="number" min="0" name="file_limit"></div></div>
        <div class="form-group"><label class="col-sm-4 control-label">每日上传上限</label><div class="col-sm-8"><div class="input-group"><input class="form-control" type="number" min="0" step="1" name="daily_mb"><span class="input-group-addon">MB</span></div></div></div>
        <p class="help-block">填写 0 表示该项不限制。全站强制开关在“系统设置 / 文件上传设置”中控制。</p>
        <button class="btn btn-primary" type="submit">保存用户配额</button> <button class="btn btn-default" type="button" onclick="rebuildUsage()">校准用量</button>
      </form>
    </div></div>
  </div><div class="col-md-7">
    <div class="panel panel-primary"><div class="panel-heading"><h3 class="panel-title">创建 API Key</h3></div><div class="panel-body">
      <form id="keyForm" class="form-horizontal">
        <div class="form-group"><label class="col-sm-3 control-label">用户 UID</label><div class="col-sm-9"><input class="form-control" type="number" min="1" name="uid" required></div></div>
        <div class="form-group"><label class="col-sm-3 control-label">名称</label><div class="col-sm-9"><input class="form-control" name="name" maxlength="100" placeholder="例如：网站前端上传"></div></div>
        <div class="form-group"><label class="col-sm-3 control-label">权限</label><div class="col-sm-9"><label class="checkbox-inline"><input type="checkbox" name="scopes[]" value="files.upload" checked> 上传</label><label class="checkbox-inline"><input type="checkbox" name="scopes[]" value="shares.create"> 创建分享</label><label class="checkbox-inline"><input type="checkbox" name="scopes[]" value="files.read_metadata"> 读元数据</label><label class="checkbox-inline"><input type="checkbox" name="scopes[]" value="files.delete_owned"> 删除自己的文件</label></div></div>
        <div class="form-group"><label class="col-sm-3 control-label">有效期至</label><div class="col-sm-9"><input class="form-control" type="datetime-local" name="expires_at"></div></div>
        <div class="form-group"><label class="col-sm-3 control-label">IP 规则</label><div class="col-sm-9"><textarea class="form-control" name="ip_rules" rows="2" placeholder="多个 IP 或 CIDR 用逗号分隔，例如 203.0.113.8, 2001:db8::/32"></textarea></div></div>
        <div class="form-group"><label class="col-sm-3 control-label">每日请求</label><div class="col-sm-3"><input class="form-control" type="number" min="0" name="request_limit" value="0"></div><label class="col-sm-3 control-label">每日流量</label><div class="col-sm-3"><div class="input-group"><input class="form-control" type="number" min="0" step="0.1" name="daily_traffic_gb" value="0"><span class="input-group-addon">GB</span></div></div></div>
        <button class="btn btn-primary" type="submit">创建并显示一次</button>
      </form>
    </div></div>
  </div></div>
  <div class="panel panel-default"><div class="panel-heading"><strong>已创建的 API Key</strong> <button class="btn btn-xs btn-default pull-right" onclick="loadKeys()">刷新</button></div><div class="table-responsive"><table class="table table-striped"><thead><tr><th>用户</th><th>名称 / 前缀</th><th>权限</th><th>有效期</th><th>今日用量</th><th>状态</th><th></th></tr></thead><tbody id="keys"></tbody></table></div></div>
</div>
<script src="https://s4.zstatic.net/ajax/libs/layer/3.1.1/layer.min.js"></script>
<script>
function mb(v){return (Number(v||0)/1048576).toFixed(1)+' MB';}
function loadUsage(){var uid=$('#quotaForm [name=uid]').val();if(!uid)return;$.getJSON('ajax_access.php?act=user_usage&uid='+encodeURIComponent(uid),function(r){if(r.code!==0){$('#usageInfo').text(r.msg);return;}var l=r.limits,u=r.usage;$('#usageInfo').html('用户 '+$('<div>').text(r.user.nickname).html()+'：已用 '+mb(u.used_bytes)+'，'+u.file_count+' 个文件；今日上传 '+mb(u.daily_upload_bytes));$('#quotaForm [name=storage_mb]').val(l.byte_limit?Math.floor(l.byte_limit/1048576):0);$('#quotaForm [name=file_limit]').val(l.file_limit);$('#quotaForm [name=daily_mb]').val(l.daily_upload_limit?Math.floor(l.daily_upload_limit/1048576):0);});}
$('#quotaForm').submit(function(e){e.preventDefault();$.post('ajax_access.php?act=save_quota',$(this).serialize(),function(r){layer.msg(r.msg);if(r.code===0)loadUsage();},'json');});
function rebuildUsage(){var uid=$('#quotaForm [name=uid]').val();if(!uid)return layer.msg('请先填写 UID');$.post('ajax_access.php?act=rebuild_quota',{uid:uid},function(r){layer.msg(r.msg);loadUsage();},'json');}
$('#keyForm').submit(function(e){e.preventDefault();$.post('ajax_access.php?act=create_key',$(this).serialize(),function(r){if(r.code!==0)return layer.alert(r.msg,{icon:2});layer.open({type:1,title:'请立即保存 API Key（只显示一次）',area:['650px','auto'],content:'<div style="padding:20px"><p>'+r.msg+'</p><textarea class="form-control" rows="3" readonly>'+r.secret+'</textarea></div>'});$('#keyForm')[0].reset();loadKeys();},'json');});
function loadKeys(){$.getJSON('ajax_access.php?act=list_keys',function(r){if(r.code!==0)return;var html='';$.each(r.rows,function(_,k){var state=k.revoked_at?'已撤销':(k.expires_at&&new Date(k.expires_at.replace(' ','T'))<new Date()?'已过期':'有效');var usage=k.today_requests+' 次 / '+mb(k.today_bytes);if(Number(k.today_denied_requests)>0)usage+='<br><span class="text-warning">拒绝 '+k.today_denied_requests+' 次'+(k.last_denied_reason?'（'+$('<div>').text(k.last_denied_reason).html()+'）':'')+'</span>';html+='<tr><td>'+k.uid+' '+$('<div>').text(k.nickname||'').html()+'</td><td>'+$('<div>').text(k.name).html()+'<br><code>'+$('<div>').text(k.key_prefix).html()+'...</code></td><td>'+$('<div>').text(k.scopes).html()+'</td><td>'+($('<div>').text(k.expires_at||'长期').html())+'</td><td>'+usage+'</td><td>'+state+'</td><td>'+(k.revoked_at?'':'<button class="btn btn-xs btn-danger" onclick="revokeKey('+k.id+')">撤销</button>')+'</td></tr>';});$('#keys').html(html||'<tr><td colspan="7" class="text-muted">暂无 API Key</td></tr>');});}
function revokeKey(id){if(!confirm('撤销后不可恢复，确定继续吗？'))return;$.post('ajax_access.php?act=revoke_key',{id:id},function(r){layer.msg(r.msg);loadKeys();},'json');}
loadKeys();
</script>
</body></html>
