<?php
include("../includes/common.php");
$title = '文件版本';
include './head.php';
if($islogin!=1) exit("<script language='javascript'>window.location.href='./login.php';</script>");
$fileId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$file = $DB->getRow("SELECT * FROM pre_file WHERE id=:id LIMIT 1", [':id'=>$fileId]);
if(!$file) exit('文件不存在');
$versions = $DB->getAll("SELECT * FROM pre_file_version WHERE file_id=:file_id ORDER BY version_no DESC", [':file_id'=>$fileId]);
?>
<div class="container" style="padding-top:70px;">
  <div class="col-xs-12 col-sm-10 col-lg-9 center-block" style="float:none;">
    <div class="panel panel-primary">
      <div class="panel-heading"><h3 class="panel-title">历史版本：<?php echo htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8')?></h3></div>
      <div class="panel-body"><p>当前版本：<?php echo htmlspecialchars($file['hash'], ENT_QUOTES, 'UTF-8')?>，<?php echo size_format($file['size'])?>。恢复历史版本时，当前内容会自动保存为新的快照。</p></div>
      <table class="table table-striped table-hover"><thead><tr><th>版本</th><th>文件</th><th>大小</th><th>快照时间</th><th>备注</th><th>操作</th></tr></thead><tbody>
      <?php foreach((array)$versions as $version){?><tr>
        <td><?php echo intval($version['version_no'])?></td>
        <td><?php echo htmlspecialchars($version['name'], ENT_QUOTES, 'UTF-8')?> <small class="text-muted"><?php echo htmlspecialchars($version['hash'], ENT_QUOTES, 'UTF-8')?></small></td>
        <td><?php echo size_format($version['size'])?></td>
        <td><?php echo htmlspecialchars($version['created_at'], ENT_QUOTES, 'UTF-8')?></td>
        <td><?php echo htmlspecialchars($version['note'], ENT_QUOTES, 'UTF-8')?></td>
        <td><button type="button" class="btn btn-xs btn-primary" onclick="restoreVersion(<?php echo intval($version['id'])?>)">恢复此版本</button></td>
      </tr><?php }?>
      <?php if(!$versions){?><tr><td colspan="6" class="text-center text-muted">暂无历史版本</td></tr><?php }?>
      </tbody></table>
    </div>
    <a class="btn btn-default" href="file.php">返回文件管理</a>
  </div>
</div>
<script src="https://s4.zstatic.net/ajax/libs/layer/3.1.1/layer.min.js"></script>
<script>
function restoreVersion(versionId){
  if(!confirm('恢复后当前文件会保存为新的历史版本，确定继续吗？')) return;
  $.post('ajax_file.php?act=restoreVersion', {file_id:<?php echo $fileId?>,version_id:versionId}, function(data){
    if(data.code === 0){ layer.alert(data.msg,{icon:1},function(){window.location.reload();}); }
    else layer.alert(data.msg,{icon:2});
  }, 'json');
}
</script>
