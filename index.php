<?php
if (version_compare(PHP_VERSION, '7.4.0', '<')) {
    die('require PHP >= 7.4 !');
}
include("./includes/common.php");

$conditions = [];
$params = [];
if(isset($_GET['m']) && $_GET['m']=='mine'){
    $title = '我的文件 - ' . $conf['title'];
    $htext = '我上传的文件';
    if($islogin2){
		$conditions[] = 'uid=:uid';
		$params[':uid'] = (int)$uid;
    }else{
        if($conf['userlogin']==1){
            $htext .= '<span class="text-muted" style="font-size:16px">（根据浏览器缓存记录，<a href="login.php">登录</a>后可永久保留记录）</span>';
        }else{
            $htext .= '<span class="text-muted" style="font-size:16px">（根据浏览器缓存记录）</span>';
        }
        if(isset($_SESSION['fileids']) && count($_SESSION['fileids'])>0){
            $ids = array_reverse($_SESSION['fileids']);
            if(count($ids) > 60){
                $ids = array_splice($ids, 0, 60);
            }
			$ids = array_map('intval', $ids);
            $conditions[] = "id IN (".implode(',', $ids).")";
        }else{
			$conditions[] = '1=2';
        }
    }
    $link = '&m=mine';
}else{
    $title = $conf['title'];
    $htext = '文件列表';
	$conditions[] = 'hide=0';
	$conditions[] = '(expire_at IS NULL OR expire_at > NOW())';
	$conditions[] = '(max_downloads=0 OR count < max_downloads)';
    $link = '';
}
$kw = isset($_GET['kw'])?trim(strip_tags($_GET['kw'])):null;
if($conf['filesearch']==1 && $kw){
	$conditions[] = 'name LIKE :kw';
	$params[':kw'] = '%'.$kw.'%';
    $link .= '&kw='.rawurlencode($kw);
}
$sql = implode(' AND ', $conditions);
$numrows=$DB->getColumn("SELECT count(*) from pre_file WHERE {$sql}", $params);

include SYSTEM_ROOT.'header.php';
?>
<main class="container app-shell">
    <section class="workspace-heading">
        <div>
            <p class="workspace-eyebrow"><i class="fa fa-database" aria-hidden="true"></i> FILE SPACE</p>
            <h1><?php echo $htext?></h1>
            <p class="workspace-meta">共 <?php echo $numrows?> 个文件，随时取用与分享。</p>
        </div>
        <a class="btn btn-primary btn-raised workspace-upload" href="./upload.php"><i class="fa fa-arrow-up" aria-hidden="true"></i><span>上传文件</span></a>
    </section>

    <section class="file-workspace">
        <div class="file-toolbar">
            <div class="file-toolbar-title">
                <h2>文件列表</h2>
                <span><?php echo $numrows?> 项</span>
            </div>
            <?php if($conf['filesearch']==1){?>
            <form class="file-search" action="./" method="GET">
                <?php if(isset($_GET['m'])){?><input name="m" type="hidden" value="<?php echo htmlspecialchars($_GET['m'])?>"><?php }?>
				<label class="sr-only" for="file-search-keyword">搜索文件</label>
				<i class="fa fa-search" aria-hidden="true"></i>
				<input id="file-search-keyword" name="kw" class="form-control" type="search" placeholder="搜索文件名" value="<?php echo htmlspecialchars($kw, ENT_QUOTES, 'UTF-8')?>" required="">
				<button class="btn btn-default" type="submit">搜索</button>
			</form>
            <?php }?>
        </div>
        <div class="table-responsive file-table-wrap">
       <table class="table table-hover filelist">
            <thead>
                <tr>
                    <th>#</th>
                    <th>操作</th>
                    <th>文件名</th>
                    <th>文件大小</th>
                    <th>文件格式</th>
                    <th>上传时间</th>
                    <th>上传者IP</th>
                </tr>
            </thead>
            <tbody>
<?php
$pagesize=15;
$pages=max(1, ceil($numrows/$pagesize));
$page=isset($_GET['page'])?intval($_GET['page']):1;
$offset=$pagesize*($page - 1);

$rs=$DB->query("SELECT * FROM pre_file WHERE {$sql} ORDER BY id DESC LIMIT $offset,$pagesize", $params);
$i=1;
while($res = $rs->fetch())
{
	$fileurl = './down.php/'.$res['hash'].'.'.($res['type']?$res['type']:'file');
	$viewurl = './file.php?hash='.$res['hash'];
	echo '<tr><td class="file-index">'.str_pad($i++, 2, '0', STR_PAD_LEFT).'</td><td class="file-actions"><a href="'.$fileurl.'"><i class="fa fa-download" aria-hidden="true"></i>下载</a><a href="'.$viewurl.'"><i class="fa fa-external-link" aria-hidden="true"></i>查看</a></td><td class="file-name"><i class="fa '.type_to_icon($res['type']).' fa-fw" aria-hidden="true"></i><span>'.htmlspecialchars($res['name'], ENT_QUOTES, 'UTF-8').'</span></td><td>'.size_format($res['size']).'</td><td><span class="file-type">'.htmlspecialchars($res['type']?$res['type']:'未知', ENT_QUOTES, 'UTF-8').'</span></td><td>'.htmlspecialchars($res['addtime'], ENT_QUOTES, 'UTF-8').'</td><td>'.htmlspecialchars(preg_replace('/\d+$/','*',$res['ip']), ENT_QUOTES, 'UTF-8').'</td></tr>';
}
if($numrows == 0) echo '<tr><td colspan="7" class="empty-files"><i class="fa fa-folder-open-o" aria-hidden="true"></i><strong>这里还没有文件</strong><span>上传一个文件，开始建立你的共享空间。</span><a href="./upload.php" class="btn btn-primary">上传文件</a></td></tr>';
?>
            </tbody>
        </table>
        </div>
        <div class="file-pagination">
        <div class="pagination-summary">第 <?php echo $page?> / <?php echo $pages?> 页 <span>共 <?php echo $numrows?> 个文件</span></div>
        <nav aria-label="文件分页">
  <ul class="pagination pagination-sm">
<?php
$first=1;
$prev=$page-1;
$next=$page+1;
$last=$pages;
if ($page>1)
{
echo '<li><a href="index.php?page='.$first.$link.'">首页</a></li>';
echo '<li><a href="index.php?page='.$prev.$link.'">&laquo;</a></li>';
} else {
echo '<li class="disabled"><a>首页</a></li>';
echo '<li class="disabled"><a>&laquo;</a></li>';
}
$start=$page-10>1?$page-10:1;
$end=$page+10<$pages?$page+10:$pages;
for ($i=$start;$i<$page;$i++)
echo '<li><a href="index.php?page='.$i.$link.'">'.$i .'</a></li>';
echo '<li class="disabled"><a>'.$page.'</a></li>';
for ($i=$page+1;$i<=$end;$i++)
echo '<li><a href="index.php?page='.$i.$link.'">'.$i .'</a></li>';
echo '';
if ($page<$pages)
{
echo '<li><a href="index.php?page='.$next.$link.'">&raquo;</a></li>';
echo '<li><a href="index.php?page='.$last.$link.'">尾页</a></li>';
} else {
echo '<li class="disabled"><a>&raquo;</a></li>';
echo '<li class="disabled"><a>尾页</a></li>';
}
?>
  </ul>
</nav>
</div>
</section>
</main>
<?php include SYSTEM_ROOT.'footer.php';?>
<?php if(!empty($conf['gonggao'])){?>
<link href="https://s4.zstatic.net/ajax/libs/snackbarjs/1.1.0/snackbar.min.css" rel="stylesheet">
<script src="https://s4.zstatic.net/ajax/libs/snackbarjs/1.1.0/snackbar.min.js"></script>
<script src="https://s4.zstatic.net/ajax/libs/jquery-cookie/1.4.1/jquery.cookie.min.js"></script>
<script>
$(function() {
    if(!$.cookie('gonggao')){
		$.snackbar({content: <?php echo json_encode($conf['gonggao']); ?>, timeout: 10000});
        var cookietime = new Date(); 
        cookietime.setTime(cookietime.getTime() + (60*60*1000));
        $.cookie('gonggao', false, { expires: cookietime });
    }
});
</script>
<?php }?>
</body>
</html>
