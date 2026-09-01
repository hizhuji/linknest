<?php
include("./includes/common.php");

$shareCode = isset($_GET['share']) ? trim($_GET['share']) : '';
$hash = isset($_GET['hash']) ? trim($_GET['hash']) : '';
$accessToken = isset($_GET['access']) ? trim($_GET['access']) : '';
$share = $shareCode !== '' ? pan_get_share_by_code($DB, $shareCode) : pan_get_default_share_by_hash($DB, $hash);
if(!$share)exit("<script language='javascript'>alert('分享不存在');window.location.href='./';</script>");
$shareCode = $share['code'];
$row = $DB->getRow("SELECT * FROM pre_file WHERE id=:id", [':id'=>intval($share['file_id'])]);
if(!$row)exit("<script language='javascript'>alert('文件不存在');window.location.href='./';</script>");
$hash = $row['hash'];
$is_mine = pan_share_is_owner($share, $islogin, $islogin2, $uid, isset($_SESSION['shareids']) ? $_SESSION['shareids'] : []);
$access_error = pan_share_access_error($share);
if($access_error && !$is_mine && !$islogin){
  http_response_code(410);
  sysmsg(pan_share_access_message($access_error), '分享不可用');
}

$title = '文件查看 - '.$conf['title'];
$is_file=true;
include SYSTEM_ROOT.'header.php';

$csrf_token = pan_csrf_token();
$name = $row['name'];
$type = $row['type'];

$downurl = 'down.php/'.$row['hash'].'.'.$type.'?share='.rawurlencode($shareCode);
$viewurl = 'view.php/'.$row['hash'].'.'.$type.'?share='.rawurlencode($shareCode);

$downurl_all = $siteurl.$downurl;
$viewurl_all = $siteurl.$viewurl;
$playerurl_all = $siteurl.'player.php?share='.rawurlencode($shareCode);

$thisurl = $siteurl.'s.php?code='.rawurlencode($shareCode);

$view_type = get_view_type($type);
$expire_text = empty($share['expire_at']) ? '永久有效' : $share['expire_at'];
$max_downloads_text = intval($share['max_accesses']) > 0 ? intval($share['max_accesses']).' 次' : '不限次数';
$ownedShares = [];
$recentLogs = [];
if($is_mine){
  foreach(pan_get_file_shares($DB, $share['file_id']) as $candidateShare){
    if(pan_share_is_owner($candidateShare, $islogin, $islogin2, $uid, isset($_SESSION['shareids']) ? $_SESSION['shareids'] : [])) $ownedShares[] = $candidateShare;
  }
  $recentLogs = pan_get_share_logs($DB, $share['id'], 10);
}

if($view_type == 'image'){
  $filetype = 1;
  $title = '<i class="fa fa-picture-o"></i> 图片查看器';
  $htmlcode = htmlspecialchars('<img src="'.$viewurl_all.'"/>');
  $ubbcode = '[img]'.$viewurl_all.'[/img]';
  $linktitle = '图片链接';
}elseif($view_type == 'audio'){
  $filetype = 2;
  $title = '<i class="fa fa-music"></i> 音乐播放器';
  $htmlcode = htmlspecialchars('<audio id="bgmMusic" src="'.$viewurl_all.'" autoplay="autoplay" loop="loop" preload="auto"></audio>');
  $htmlcode2 = htmlspecialchars('<iframe src="'.$playerurl_all.'" width="407" scrolling="no" frameborder="0" height="70"></iframe>');
  $ubbcode = '[audio=X]'.$viewurl_all.'[/audio]';
  $linktitle = '音乐链接';
}elseif($view_type == 'video'){
  $filetype = 3;
  $title = '<i class="fa fa-video-camera"></i> 视频播放器';
  $htmlcode = htmlspecialchars('<video id="movies" src="'.$viewurl_all.'" autobuffer="true" controls="" width="100
  %"></video>');
  $htmlcode2 = htmlspecialchars('<iframe src="'.$playerurl_all.'" width="800" height="500" scrolling="no" frameborder="0"></iframe>');
  $ubbcode = '[movie=320*180]'.$viewurl_all.'[/movie]';
  $linktitle = '视频链接';
}else{
  $filetype = 0;
  $title = '<i class="fa fa-file"></i> 文件查看';
  $htmlcode = htmlspecialchars('<a href="'.$downurl_all.'" target="_blank">'.$name.'</a>');
  $ubbcode = '[url='.$downurl_all.']'.$name.'[/url]';
  if($view_type == 'office'){
    $office_url = 'https://view.officeapps.live.com/op/view.aspx?src='.rawurlencode($downurl_all);
  }
}
?>
<div class="container">
    <div class="row">
<?php
if($share['password']!=null){
  if($_SERVER['REQUEST_METHOD'] === 'POST'){
    if(!pan_verify_request_csrf_token()){
      http_response_code(403);
      sysmsg('页面已过期，请刷新后重试。');
    }
    $submittedPassword = isset($_POST['pwd']) ? (string)$_POST['pwd'] : '';
    if(pan_share_password_verify($submittedPassword, $share['password'])) $accessToken = pan_create_share_access_token($share, SYS_KEY);
  }
  if(!pan_verify_share_access_token($accessToken, $share, SYS_KEY)){ ?>
  <meta http-equiv="content-type" content="text/html;charset=utf-8"/>
  <div class="panel panel-default"><div class="panel-heading"><h3 class="panel-title">请输入提取密码</h3></div><div class="panel-body">
  <form method="post" action="file.php?share=<?php echo rawurlencode($shareCode)?>">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8')?>">
    <div class="form-group"><input class="form-control" type="password" name="pwd" autocomplete="off" required></div>
    <button class="btn btn-primary" type="submit">验证并查看文件</button>
  </form></div></div>
<?php
    exit;
  }
  $accessQuery = '&access='.rawurlencode($accessToken);
  $downurl .= $accessQuery;
  $viewurl .= $accessQuery;
  $downurl_all = $siteurl.$downurl;
  $viewurl_all = $siteurl.$viewurl;
  $playerurl_all .= '&access='.rawurlencode($accessToken);
  if($view_type === 'image'){
    $htmlcode = htmlspecialchars('<img src="'.$viewurl_all.'"/>');
    $ubbcode = '[img]'.$viewurl_all.'[/img]';
  }elseif($view_type === 'audio'){
    $htmlcode = htmlspecialchars('<audio id="bgmMusic" src="'.$viewurl_all.'" autoplay="autoplay" loop="loop" preload="auto"></audio>');
    $htmlcode2 = htmlspecialchars('<iframe src="'.$playerurl_all.'" width="407" scrolling="no" frameborder="0" height="70"></iframe>');
    $ubbcode = '[audio=X]'.$viewurl_all.'[/audio]';
  }elseif($view_type === 'video'){
    $htmlcode = htmlspecialchars('<video id="movies" src="'.$viewurl_all.'" autobuffer="true" controls="" width="100%"></video>');
    $htmlcode2 = htmlspecialchars('<iframe src="'.$playerurl_all.'" width="800" height="500" scrolling="no" frameborder="0"></iframe>');
    $ubbcode = '[movie=320*180]'.$viewurl_all.'[/movie]';
  }else{
    $htmlcode = htmlspecialchars('<a href="'.$downurl_all.'" target="_blank">'.$name.'</a>');
    $ubbcode = '[url='.$downurl_all.']'.$name.'[/url]';
  }
}

?>
      <div class="col-sm-9">
<div class="panel panel-primary">
<div class="panel-heading">
<h3 class="panel-title"><?php echo $title?></h3>
</div>
<div class="panel-body" align="center">
<?php
if($access_error){
  echo '<div class="view"><div class="elseview"><div class="tubiao"><i class="fa fa-clock-o"></i></div></div><div class="elsetext"><p>'.htmlspecialchars(pan_share_access_message($access_error), ENT_QUOTES, 'UTF-8').'</p><p>你可以在下方管理区域重新设置有效期或访问次数。</p></div></div>';
}elseif($filetype==1){
  echo '<div class="image_view"><a href="'.$viewurl.'" title="点击查看原图"><img alt="loading" src="'.$viewurl.'" class="image"></a></div>';
}elseif($filetype==2){
  echo '<div class="view"><div id="aplayer"></div></div>';
}elseif($filetype==3 && $row['block']==0){
  echo '<div class="videoplayer"></div>';
}elseif($filetype==3){
  echo '<div class="view">
  <div class="elseview">
  <div class="tubiao"><i class="fa '.type_to_icon($type).'"></i> </div>
</div>
<div class="elsetext"><p>'.htmlspecialchars($name, ENT_QUOTES, 'UTF-8').'</p><p>视频文件需审核通过后才能在线播放和下载，请等待审核通过！</p></div>
</div>';
}else{
  echo '<div class="view">
  <div class="elseview">
  <div class="tubiao"><i class="fa '.type_to_icon($type).'"></i> </div>
</div>
<div class="elsetext"><p>'.htmlspecialchars($name, ENT_QUOTES, 'UTF-8').'（'.size_format($row['size']).'）</p>
<a href="'.$downurl.'" class="btn btn-raised btn-primary btn-lg"><i class="fa fa-download" aria-hidden="true"></i> 下载文件<div class="ripple-container"></div></a>'.($view_type=='office'?'&nbsp;<a href="'.$office_url.'" class="btn btn-raised btn-info btn-lg" target="_blank"><i class="fa fa-eye" aria-hidden="true"></i> 在线预览<div class="ripple-container"></div></a>':'').'
</div>
</div>';
}
?>
</div>
</div>
      <div class="panel panel-default">
          <div class="panel-body" style="padding: 0px;">
              <ul class="nav nav-tabs" style="margin-bottom: 15px;">
                  <li class="active"><a href="#link" data-toggle="tab"><i class="fa fa-link" aria-hidden="true"></i> 文件外链</a>
                  </li>
                  <li><a href="#code" data-toggle="tab"><i class="fa fa-code" aria-hidden="true"></i> 代码调用</a>
                  </li>
                  <li><a href="#info" data-toggle="tab"><i class="fa fa-info-circle" aria-hidden="true"></i> 文件详情</a>
                  </li>
                  <li class="<?php echo $is_mine?'':'hide';?>"><a href="#manager" data-toggle="tab"><i class="fa fa-cog" aria-hidden="true"></i> 管理</a>
                  </li>
              </ul>
              <div id="myTabContent" class="tab-content" style="padding: 19px;">
                  <div class="tab-pane fade active in" id="link">
                    <div class="form-group row <?php echo $filetype==0?'hide':'';?>">
                      <label for="link1" class="col-md-2 control-label"><?php echo $linktitle?>：</label>
                      <div class="col-md-10">
                        <div class="input-group">
                          <input type="text" class="form-control" id="link1" readonly="readonly" value="<?php echo $viewurl_all?>">
                          <span class="input-group-btn">
                          <button class="btn btn-primary btn-raised copy-btn" type="button" data-clipboard-text="<?php echo $viewurl_all?>">复制<div class="ripple-container"></div></button>
                          </span>
                        </div>
                      </div>
                    </div>
                    <div class="form-group row">
                      <label for="link2" class="col-md-2 control-label">下载链接：</label>
                      <div class="col-md-10">
                        <div class="input-group">
                          <input type="text" class="form-control" id="link2" readonly="readonly" value="<?php echo $downurl_all?>">
                          <span class="input-group-btn">
                          <button class="btn btn-primary btn-raised copy-btn" type="button" data-clipboard-text="<?php echo $downurl_all?>">复制<div class="ripple-container"></div></button>
                          </span>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="tab-pane fade" id="code">
                    <div class="form-group row <?php echo $filetype<2?'hide':'';?>">
                      <label for="code1" class="col-md-2 control-label">播放器代码：</label>
                      <div class="col-md-10">
                        <div class="input-group">
                          <input type="text" class="form-control" id="code1" readonly="readonly" value="<?php echo $htmlcode2?>">
                          <span class="input-group-btn">
                          <button class="btn btn-primary btn-raised copy-btn" type="button" data-clipboard-text="<?php echo $htmlcode2?>">复制<div class="ripple-container"></div></button>
                          </span>
                        </div>
                      </div>
                    </div>
                    <div class="form-group row">
                      <label for="code2" class="col-md-2 control-label">HTML代码：</label>
                      <div class="col-md-10">
                        <div class="input-group">
                          <input type="text" class="form-control" id="code2" readonly="readonly" value="<?php echo $htmlcode?>">
                          <span class="input-group-btn">
                          <button class="btn btn-primary btn-raised copy-btn" type="button" data-clipboard-text="<?php echo $htmlcode?>">复制<div class="ripple-container"></div></button>
                          </span>
                        </div>
                      </div>
                    </div>
                    <div class="form-group row">
                      <label for="code3" class="col-md-2 control-label">UBB代码：</label>
                      <div class="col-md-10">
                        <div class="input-group">
                          <input type="text" class="form-control" id="code3" readonly="readonly" value="<?php echo $ubbcode?>">
                          <span class="input-group-btn">
                          <button class="btn btn-primary btn-raised copy-btn" type="button" data-clipboard-text="<?php echo $ubbcode?>">复制<div class="ripple-container"></div></button>
                          </span>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="tab-pane fade" id="info">
                      <div class="row" align="center">
                          <table class="table table-bordered fileinfo-table">
                              <tr>
                                  <th width="97">上传者IP：</td><td width="100"><?php echo preg_replace('/\d+$/','*',$row['ip'])?></td>
                                  <th width="100">上传时间：</td><td width="168"><?php echo $row['addtime']?></td>
                              </tr>
                              <tr>
                                  <th>访问次数：</td><td><?php echo intval($share['access_count'])?></td>
                                  <th>文件大小：</td><td><?php echo size_format($row['size']).' ('.$row['size'].' 字节)'?></td>
                              </tr>
                              <tr>
                                  <th>有效期至：</td><td><?php echo htmlspecialchars($expire_text, ENT_QUOTES, 'UTF-8')?></td>
                                  <th>访问上限：</td><td><?php echo htmlspecialchars($max_downloads_text, ENT_QUOTES, 'UTF-8')?></td>
                              </tr>
                          </table>
                      </div>
                  </div>
                  <div class="tab-pane fade" id="manager">
                      <div class="row" align="center">
                          <div class="col-md-12">
                            <input type="hidden" id="share_code" name="share_code" value="<?php echo htmlspecialchars($shareCode, ENT_QUOTES, 'UTF-8')?>">
                            <input type="hidden" id="csrf_token" name="csrf_token" value="<?php echo $csrf_token?>">
                            <div class="row" style="margin-bottom:15px;text-align:left;">
                              <div class="col-sm-6">
                                <label for="access_expire_days">重新设置有效期</label>
                                <select class="form-control" id="access_expire_days">
                                  <option value="0">永久有效</option>
                                  <option value="1">从现在起 1 天</option>
                                  <option value="7">从现在起 7 天</option>
                                  <option value="30">从现在起 30 天</option>
                                </select>
                              </div>
                              <div class="col-sm-6">
                                <label for="access_max_downloads">最大访问次数</label>
                                <input type="number" class="form-control" id="access_max_downloads" min="0" max="1000000" value="<?php echo intval($share['max_accesses'])?>">
                                <p class="help-block">当前已访问 <?php echo intval($share['access_count'])?> 次，0 表示不限次数</p>
                              </div>
                            </div>
                            <button onclick="update_access_policy()" class="btn btn-raised btn-primary"><i class="fa fa-clock-o" aria-hidden="true"></i> 更新分享策略</button>
                            <button onclick="delete_confirm()" class="btn btn-raised btn-danger"><i class="fa fa-close" aria-hidden="true"></i> 删除当前分享</button>
                            <hr>
                            <h4 style="text-align:left">创建新分享</h4>
                            <div class="row" style="text-align:left">
                              <div class="col-sm-4"><label for="new_share_code">自定义短码</label><input class="form-control" id="new_share_code" maxlength="64" placeholder="留空自动生成"></div>
                              <div class="col-sm-4"><label for="new_share_password">提取密码</label><input class="form-control" id="new_share_password" maxlength="128" type="password" placeholder="留空不设密码"></div>
                              <div class="col-sm-4"><label for="new_share_expire">有效期</label><select class="form-control" id="new_share_expire"><option value="0">永久</option><option value="1">1 天</option><option value="7">7 天</option><option value="30">30 天</option></select></div>
                              <div class="col-sm-4"><label for="new_share_limit">最大访问次数</label><input class="form-control" id="new_share_limit" type="number" min="0" max="1000000" value="0"></div>
                              <div class="col-sm-4" style="padding-top:28px"><label><input id="new_share_once" type="checkbox"> 一次性分享</label></div>
                              <div class="col-sm-4" style="padding-top:20px"><button onclick="create_share()" class="btn btn-raised btn-success"><i class="fa fa-plus"></i> 创建链接</button></div>
                            </div>
                            <hr>
                            <h4 style="text-align:left">我的分享链接</h4>
                            <div class="table-responsive"><table class="table table-bordered table-condensed"><thead><tr><th>短码</th><th>状态</th><th>访问</th><th>类型</th><th>操作</th></tr></thead><tbody>
                            <?php foreach($ownedShares as $ownedShare){ ?>
                              <tr><td><a href="s.php?code=<?php echo rawurlencode($ownedShare['code'])?>" target="_blank"><?php echo htmlspecialchars($ownedShare['code'], ENT_QUOTES, 'UTF-8')?></a></td><td><?php echo intval($ownedShare['status'])===1?'有效':'已撤销'?></td><td><?php echo intval($ownedShare['access_count'])?></td><td><?php echo intval($ownedShare['one_time'])===1?'一次性':'普通'?></td><td><button class="btn btn-xs btn-default" onclick="toggle_share(<?php echo pan_json_for_html($ownedShare['code'])?>)"><?php echo intval($ownedShare['status'])===1?'撤销':'恢复'?></button></td></tr>
                            <?php } ?></tbody></table></div>
                            <h4 style="text-align:left">当前分享最近访问</h4>
                            <div class="table-responsive"><table class="table table-bordered table-condensed"><thead><tr><th>时间</th><th>行为</th><th>IP</th><th>流量</th></tr></thead><tbody>
                            <?php foreach($recentLogs as $log){ ?><tr><td><?php echo htmlspecialchars($log['created_at'], ENT_QUOTES, 'UTF-8')?></td><td><?php echo $log['event']==='download'?'下载':'预览'?></td><td><?php echo htmlspecialchars($log['ip_masked'], ENT_QUOTES, 'UTF-8')?></td><td><?php echo size_format($log['bytes'])?></td></tr><?php } ?>
                            <?php if(!$recentLogs){ ?><tr><td colspan="4">暂无访问记录</td></tr><?php } ?></tbody></table></div>
                          </div>
                      </div>
                  </div>
              </div>
          </div>
      </div>
      </div>
      <div class="col-sm-3">
<div class="panel panel-info">
<div class="panel-heading">
<h3 class="panel-title"><i class="fa fa-exclamation-circle"></i> 提示</h3>
</div>
<div class="panel-body">
<?php echo $conf['gg_file']?>
</div>
</div>
<div class="panel panel-default hidden-xs">
<div class="panel-heading">
<h3 class="panel-title"><i class="fa fa-qrcode"></i> 手机扫码下载</h3>
</div>
<div class="panel-body text-center">
<img alt="二维码" src="//api.qrserver.com/v1/create-qr-code/?size=180x180&margin=10&data=<?php echo urlencode($thisurl);?>">
</div>
</div>
      </div>
    </div>
  </div>
<?php include SYSTEM_ROOT.'footer.php';?>
<?php if(!$access_error && $filetype==2){?>
<script type="text/javascript" src="https://s4.zstatic.net/ajax/libs/aplayer/1.10.1/APlayer.min.js"></script>
<script type="text/javascript">
var ap = new APlayer({
  container: document.getElementById('aplayer'),
  loop: 'none',
  theme: '#b2dae6',
  audio: [{
      title: <?php echo pan_json_for_html($name)?>,
      author: 'none',
      url: <?php echo pan_json_for_html($viewurl_all)?>,
      cover: './assets/img/music.png',
  }]
});
</script>
<?php }elseif(!$access_error && $filetype==3 && $row['block']==0){?>
<script type="text/javascript" src="assets/js/ckplayer.min.js"></script>
<?php if($type=='m3u8'){$plug='hls.js';?><script src="https://s4.zstatic.net/ajax/libs/hls.js/1.2.4/hls.min.js"></script><?php }?>
<?php if($type=='flv'||$type=='f4v'){$plug='flv.js';?><script src="https://s4.zstatic.net/ajax/libs/flv.js/1.6.2/flv.min.js"></script><?php }?>
<script type="text/javascript">
  var videoObject = {
    container: '.videoplayer',
    plug:<?php echo pan_json_for_html($plug)?>,
    video:<?php echo pan_json_for_html($viewurl_all)?>,
    webFull:true,
  };
  var player=new ckplayer(videoObject);
</script>
<?php }?>
<script src="https://s4.zstatic.net/ajax/libs/layer/2.3/layer.js"></script>
<script src="https://s4.zstatic.net/ajax/libs/clipboard.js/1.7.1/clipboard.min.js"></script>
<script>
function update_access_policy(){
  var share_code = $("#share_code").val();
  var csrf_token = $("#csrf_token").val();
  $.ajax({
    type: 'POST',
    url: 'ajax.php?act=updateAccessPolicy',
    data: {
      share_code: share_code,
      csrf_token: csrf_token,
      expire_days: $("#access_expire_days").val(),
      max_downloads: $("#access_max_downloads").val()
    },
    dataType: 'json',
    success: function(data){
      if(data.code == 0){
        layer.alert(data.msg, {icon:1}, function(){window.location.reload();});
      }else{
        layer.alert(data.msg, {icon:2});
      }
    },
    error: function(){layer.msg('服务器错误');}
  });
}
function delete_confirm(){
  var share_code = $("#share_code").val();
  var csrf_token = $("#csrf_token").val();
  var confirmobj = layer.confirm('确定删除当前分享吗？其他分享链接不会受影响。', {
	  btn: ['确定','取消'], icon: 0
	}, function(){
    var ii = layer.load(2);
	  $.ajax({
      type : 'POST',
      url : 'ajax.php?act=deleteFile',
      data : {share_code:share_code, csrf_token:csrf_token},
      dataType : 'json',
      success : function(data) {
        layer.close(ii);
        if(data.code == 0){
          layer.alert('删除成功', {icon:1}, function(){window.location.href="./";});
        }else{
          layer.alert(data.msg, {icon:2});
        }
      },
      error:function(data){
        layer.close(ii);
        layer.msg('服务器错误');
      }
	  });
	}, function(){
	  layer.close(confirmobj);
	});
}
function create_share(){
  $.ajax({type:'POST',url:'ajax.php?act=createShare',dataType:'json',data:{
    share_code:$("#share_code").val(),csrf_token:$("#csrf_token").val(),custom_code:$("#new_share_code").val(),
    use_password:$("#new_share_password").val()?1:0,password:$("#new_share_password").val(),expire_days:$("#new_share_expire").val(),
    max_accesses:$("#new_share_limit").val(),one_time:$("#new_share_once").prop('checked')?1:0
  },success:function(data){if(data.code===0){layer.alert(data.msg+'：'+data.pageurl,{icon:1},function(){window.location.reload();});}else layer.alert(data.msg,{icon:2});},error:function(){layer.msg('服务器错误');}});
}
function toggle_share(code){
  $.ajax({type:'POST',url:'ajax.php?act=toggleShare',dataType:'json',data:{source_code:$("#share_code").val(),target_code:code,csrf_token:$("#csrf_token").val()},success:function(data){if(data.code===0){layer.msg(data.msg,{icon:1});setTimeout(function(){window.location.reload();},500);}else layer.alert(data.msg,{icon:2});},error:function(){layer.msg('服务器错误');}});
}
$(document).ready(function(){
  var clipboard = new Clipboard('.copy-btn');
  clipboard.on('success', function (e) {
    layer.msg('复制成功！', {icon: 1});
  });
  clipboard.on('error', function (e) {
    layer.msg('复制失败，请长按链接后手动复制', {icon: 2});
  });
})
</script>
</body>
</html>
