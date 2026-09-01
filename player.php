<?php
include("./includes/common.php");

$shareCode = isset($_GET['share'])?trim($_GET['share']):'';
$hash = isset($_GET['hash'])?trim($_GET['hash']):'';
$accessToken = isset($_GET['access'])?trim($_GET['access']):'';
$share = $shareCode !== '' ? pan_get_share_by_code($DB, $shareCode) : pan_get_default_share_by_hash($DB, $hash);
if(!$share)exit('404 Not Found');
$row = $share;
if($access_error = pan_share_access_error($share))exit(pan_share_access_message($access_error));
if($share['password']!=null && !pan_verify_share_access_token($accessToken, $share, SYS_KEY))exit('Password required');
$name = $row['name'];
$type = $row['type'];
$viewurl_all = $siteurl.'view.php/'.$row['hash'].'.'.$type.'?share='.rawurlencode($share['code']);
if(!empty($share['password']))$viewurl_all .= '&access='.rawurlencode($accessToken);

$view_type = get_view_type($type);

if($view_type == 'audio'){
    $title = '音乐播放器 - '.$conf['title'];
}elseif($view_type == 'video'){
    $title = '视频播放器 - '.$conf['title'];
}else{
    exit('NO player');
}

@header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="zh-cn">
<head>
  <meta charset="utf-8"/>
  <meta name="renderer" content="webkit">
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title><?php echo $title ?></title>
  <link rel="stylesheet" href="https://s4.zstatic.net/ajax/libs/aplayer/1.10.1/APlayer.min.css">
  <link href="./assets/css/ckplayer.css" rel="stylesheet">
  <script src="https://s4.zstatic.net/ajax/libs/jquery/2.1.4/jquery.min.js"></script>
<style type="text/css">
body{margin:0;}
</style>
</head>
<body>
<div id="preview" align="center">
<?php
if($view_type == 'audio'){
  echo '<div id="aplayer"></div>';
}elseif($view_type == 'video'){
  echo '<div class="videoplayer" style="width:100%"></div>';
}else{
  exit;
}
?>
</div>
<?php if($view_type == 'audio'){?>
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
<?php }elseif($view_type == 'video'){?>
<script type="text/javascript" src="./assets/js/ckplayer.min.js"></script>
<?php if($type=='m3u8'){$plug='hls.js';?><script src="https://s4.zstatic.net/ajax/libs/hls.js/1.2.4/hls.min.js"></script><?php }?>
<?php if($type=='flv'||$type=='f4v'){$plug='flv.js';?><script src="https://s4.zstatic.net/ajax/libs/flv.js/1.6.2/flv.min.js"></script><?php }?>
<script type="text/javascript">
  $(".videoplayer").height($(window).height());
  var videoObject = {
    container: '.videoplayer',
    plug:<?php echo pan_json_for_html($plug)?>,
    video:<?php echo pan_json_for_html($viewurl_all)?>,
    webFull:true,
  };
  var player=new ckplayer(videoObject);
</script>
<?php }?>
</body>
</html>
