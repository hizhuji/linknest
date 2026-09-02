<?php
define('IN_ADMIN', true);
include("../includes/common.php");
if($islogin!=1) exit("<script language='javascript'>window.location.href='./login.php';</script>");
$action = isset($_GET['action']) ? trim($_GET['action']) : '';
$from = isset($_GET['from']) ? trim($_GET['from']) : '';
$to = isset($_GET['to']) ? trim($_GET['to']) : '';
$conditions = ['1=1']; $params = [];
if($action !== ''){ $conditions[]='action=:action'; $params[':action']=$action; }
if($from !== '' && strtotime($from)!==false){ $conditions[]='created_at>=:from'; $params[':from']=date('Y-m-d 00:00:00', strtotime($from)); }
if($to !== '' && strtotime($to)!==false){ $conditions[]='created_at<=:to'; $params[':to']=date('Y-m-d 23:59:59', strtotime($to)); }
$sql = implode(' AND ', $conditions);
$rows = $DB->getAll("SELECT actor,action,resource_type,resource_id,ip_masked,context,created_at FROM pre_admin_audit WHERE {$sql} ORDER BY id DESC LIMIT 1000", $params);
if(isset($_GET['export']) && $_GET['export']==='1'){
    header('Content-Type: text/csv; charset=UTF-8'); header('Content-Disposition: attachment; filename=linknest-audit.csv');
    echo "\xEF\xBB\xBF"; $out=fopen('php://output','w'); fputcsv($out,['管理员','动作','资源类型','资源ID','IP','上下文','时间']);
    foreach((array)$rows as $row) fputcsv($out, $row); fclose($out); exit;
}
$title='管理员审计'; include './head.php';
?>
<div class="container" style="padding-top:70px;"><div class="col-xs-12 center-block" style="float:none;"><div class="panel panel-primary"><div class="panel-heading"><h3 class="panel-title">管理员审计</h3></div><div class="panel-body"><form class="form-inline" method="get"><input class="form-control" name="action" value="<?php echo htmlspecialchars($action,ENT_QUOTES,'UTF-8')?>" placeholder="动作，例如 file_trashed"><input class="form-control" type="date" name="from" value="<?php echo htmlspecialchars($from,ENT_QUOTES,'UTF-8')?>"><input class="form-control" type="date" name="to" value="<?php echo htmlspecialchars($to,ENT_QUOTES,'UTF-8')?>"><button class="btn btn-primary">筛选</button> <a class="btn btn-default" href="audit.php?<?php echo htmlspecialchars(http_build_query(array_merge($_GET,['export'=>'1'])),ENT_QUOTES,'UTF-8')?>">导出 CSV</a></form></div><table class="table table-striped table-hover"><thead><tr><th>管理员</th><th>动作</th><th>资源</th><th>IP</th><th>上下文</th><th>时间</th></tr></thead><tbody><?php foreach((array)$rows as $row){?><tr><td><?php echo htmlspecialchars($row['actor'],ENT_QUOTES,'UTF-8')?></td><td><?php echo htmlspecialchars($row['action'],ENT_QUOTES,'UTF-8')?></td><td><?php echo htmlspecialchars($row['resource_type'].'/'.$row['resource_id'],ENT_QUOTES,'UTF-8')?></td><td><?php echo htmlspecialchars($row['ip_masked'],ENT_QUOTES,'UTF-8')?></td><td><small><?php echo htmlspecialchars($row['context'],ENT_QUOTES,'UTF-8')?></small></td><td><?php echo htmlspecialchars($row['created_at'],ENT_QUOTES,'UTF-8')?></td></tr><?php }?><?php if(!$rows){?><tr><td colspan="6" class="text-center text-muted">暂无审计记录</td></tr><?php }?></tbody></table></div></div></div>
