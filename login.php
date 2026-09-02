<?php
include("./includes/common.php");

function finishUserLogin($type, $openid, $nickname, $faceimg) {
	global $DB, $clientip;
	$userrow=$DB->find('user','*',['type'=>$type, 'openid'=>$openid], null, '1');
	if(!$userrow){
		if(!$DB->insert('user', [
			'type'=>$type, 'openid'=>$openid, 'nickname'=>$nickname, 'faceimg'=>$faceimg,
			'enable'=>1, 'regip'=>$clientip, 'loginip'=>$clientip, 'addtime'=>'NOW()', 'lasttime'=>'NOW()',
		])) sysmsg('用户注册失败 '.$DB->error());
		$uid = $DB->lastInsertId();
	}else{
		if($userrow['enable']==0){ $_SESSION['user_block'] = true; sysmsg('当前用户已被禁止登录'); }
		$uid = $userrow['uid'];
		$DB->update('user', ['nickname'=>$nickname, 'faceimg'=>$faceimg, 'loginip'=>$clientip, 'lasttime'=>'NOW()'], ['uid'=>$uid]);
	}
	if(!empty($_SESSION['user_block'])){
		$DB->update('user', ['enable'=>0], ['uid'=>$uid]);
		sysmsg('当前用户已被禁止登录');
	}
	if(isset($_SESSION['fileids']) && count($_SESSION['fileids'])>0){
		$ids = array_reverse($_SESSION['fileids']);
		if(count($ids) > 60) $ids = array_splice($ids, 0, 60);
		$ids = implode(',', array_map('intval', $ids));
		$DB->exec("UPDATE pre_file SET uid='{$uid}' WHERE id IN ({$ids}) AND uid=0");
		pan_quota_rebuild_user($DB, $uid);
	}
	$expiretime=time()+2592000;
	$session=hash_hmac('sha256', $type."\0".$openid, SYS_KEY);
	session_regenerate_id(true);
	$token=pan_create_auth_token(['type'=>'user', 'uid'=>(int)$uid, 'sid'=>$session, 'exp'=>$expiretime], SYS_KEY);
	ob_clean();
	pan_set_auth_cookie('user_token', $token, $expiretime);
	exit("<script language='javascript'>window.location.href='./';</script>");
}

if(!$conf['userlogin']){
    @header('Content-Type: text/html; charset=UTF-8');
	exit("<script language='javascript'>alert('未开启登录');window.location.href='./';</script>");
}
if(isset($_GET['logout'])){
	if(!checkRefererHost())exit();
	pan_clear_auth_cookie("user_token");
	@header('Content-Type: text/html; charset=UTF-8');
	exit("<script language='javascript'>alert('您已成功注销本次登录！');window.location.href='./login.php';</script>");
}elseif($islogin2==1){
	@header('Content-Type: text/html; charset=UTF-8');
	exit("<script language='javascript'>alert('您已登录！');window.location.href='./';</script>");
}elseif(isset($_GET['act']) && $_GET['act']=='connect'){
	@header('Content-Type: application/json; charset=UTF-8');
	require_post_request();
	if(!pan_verify_request_csrf_token())exit('{"code":403,"msg":"CSRF TOKEN ERROR"}');
    $type = isset($_POST['type'])?$_POST['type']:exit('{"code":-1,"msg":"no type"}');
	if(in_array($type, ['google', 'apple'], true)){
		if(empty($conf['login_'.$type])) exit('{"code":-1,"msg":"该登录方式未开启"}');
		if($type === 'google'){
			$config = ['clientId'=>isset($conf['google_client_id'])?$conf['google_client_id']:'', 'clientSecret'=>isset($conf['google_client_secret'])?$conf['google_client_secret']:''];
			if(empty($config['clientId']) || empty($config['clientSecret'])) exit('{"code":-1,"msg":"Google 登录参数未配置完整"}');
		}else{
			$config = [
				'clientId'=>isset($conf['apple_client_id'])?$conf['apple_client_id']:'', 'teamId'=>isset($conf['apple_team_id'])?$conf['apple_team_id']:'',
				'keyId'=>isset($conf['apple_key_id'])?$conf['apple_key_id']:'', 'privateKey'=>isset($conf['apple_private_key'])?$conf['apple_private_key']:'',
			];
			if(in_array('', $config, true)) exit('{"code":-1,"msg":"Apple 登录参数未配置完整"}');
		}
		$redirectUri = $siteurl.'login.php?provider='.$type;
		$Oauth = new \lib\NativeOauth($type, $config, $redirectUri);
		$url = $Oauth->loginUrl();
		exit(json_encode($url ? ['code'=>0, 'url'=>$url] : ['code'=>-1, 'msg'=>$Oauth->errmsg()]));
	}
    if(!$conf['login_apiurl'] || !$conf['login_appid'] || !$conf['login_appkey'])exit('{"code":-1,"msg":"未配置好快捷登录接口信息"}');
    $Oauth = new \lib\Oauth($conf['login_apiurl'], $conf['login_appid'], $conf['login_appkey']);
    $res = $Oauth->login($type);
    if(isset($res['code']) && $res['code']==0){
        $result = ['code'=>0, 'url'=>$res['url']];
    }elseif(isset($res['code'])){
        $result = ['code'=>-1, 'msg'=>$res['msg']];
    }else{
        $result = ['code'=>-1, 'msg'=>'快捷登录接口请求失败'];
    }
    exit(json_encode($result));
}elseif(isset($_GET['provider'], $_GET['code'], $_GET['state']) && in_array($_GET['provider'], ['google', 'apple'], true)){
	$type = $_GET['provider'];
	if(empty($conf['login_'.$type])) sysmsg('该登录方式未开启');
	if($type === 'google'){
		$config = ['clientId'=>isset($conf['google_client_id'])?$conf['google_client_id']:'', 'clientSecret'=>isset($conf['google_client_secret'])?$conf['google_client_secret']:''];
	}else{
		$config = [
			'clientId'=>isset($conf['apple_client_id'])?$conf['apple_client_id']:'', 'teamId'=>isset($conf['apple_team_id'])?$conf['apple_team_id']:'',
			'keyId'=>isset($conf['apple_key_id'])?$conf['apple_key_id']:'', 'privateKey'=>isset($conf['apple_private_key'])?$conf['apple_private_key']:'',
		];
	}
	$Oauth = new \lib\NativeOauth($type, $config, $siteurl.'login.php?provider='.$type);
	$user = $Oauth->callback($_GET['code'], $_GET['state']);
	if(!$user) sysmsg(htmlspecialchars($Oauth->errmsg(), ENT_QUOTES, 'UTF-8'));
	finishUserLogin($type, $user['openid'], trim($user['nickname']), $user['faceimg']);
}elseif(isset($_GET['code'], $_GET['type'], $_GET['state'])){
	if(empty($_SESSION['Oauth_state']) || !hash_equals($_SESSION['Oauth_state'], $_GET['state'])){
		sysmsg("<h2>The state does not match. You may be a victim of CSRF.</h2>");
	}
	$type = $_GET['type'];
    $typename = $type=='wx'?'微信':'QQ';
	$Oauth = new \lib\Oauth($conf['login_apiurl'], $conf['login_appid'], $conf['login_appkey']);
	$arr = $Oauth->callback();
	if(isset($arr['code']) && $arr['code']==0){
		$openid=$arr['social_uid'];
		$access_token=$arr['access_token'];
		$nickname=trim($arr['nickname']);
        if(empty($nickname) || $nickname=='-') $nickname = $typename.'用户';
		$faceimg=$arr['faceimg'];
	}elseif(isset($arr['code'])){
		sysmsg('<h3>error:</h3>'.$arr['errcode'].'<h3>msg  :</h3>'.$arr['msg']);
	}else{
		sysmsg('获取登录数据失败');
	}
	finishUserLogin($type, $openid, $nickname, $faceimg);
}

$title = '用户登录 - ' . $conf['title'];
$csrf_token = pan_csrf_token();
include SYSTEM_ROOT.'header.php';
?>
<div class="container">
<div class="col-xs-10 col-sm-8 col-md-6 col-lg-4 center-block" style="float: none;">
    <div class="well bs-component" style="margin-top:50%">
        <div class="row text-center">
        <div class="col-xs-12">
            <h5>请选择登录方式</h5><br/>
            <p id="loginform">
                <?php if($conf['login_qq']){?><a href="javascript:connect('qq')" class="btn btn-info btn-fab loginbtn"><i class="fa fa-qq"></i></a><?php }?>
                <?php if($conf['login_wx']){?><a href="javascript:connect('wx')" class="btn btn-success btn-fab loginbtn"><i class="fa fa-wechat"></i></a><?php }?>
				<?php if(!empty($conf['login_google'])){?><a href="javascript:connect('google')" class="btn btn-default btn-fab loginbtn" title="使用 Google 登录"><i class="fa fa-google"></i></a><?php }?>
				<?php if(!empty($conf['login_apple'])){?><a href="javascript:connect('apple')" class="btn btn-default btn-fab loginbtn" title="使用 Apple 登录"><i class="fa fa-apple"></i></a><?php }?>
            </p>
            <p class="text-muted">新用户快捷登录后会自动注册账号</p>
        </div>
        </div>
    </div>
</div>
</div>
<?php include SYSTEM_ROOT.'footer.php';?>
<script src="https://s4.zstatic.net/ajax/libs/layer/2.3/layer.js"></script>
<script>
function connect(type){
    var ii = layer.load(2, {shade:[0.1,'#fff']});
	$.ajax({
		type : "POST",
		url : "login.php?act=connect",
		data : {type:type, csrf_token:<?php echo json_encode($csrf_token); ?>},
		dataType : 'json',
		success : function(data) {
			layer.close(ii);
			if(data.code == 0){
				window.location.href = data.url;
			}else{
				layer.alert(data.msg, {icon: 7});
			}
		} 
	});
}
</script>
</body>
</html>
