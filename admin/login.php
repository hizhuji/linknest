<?php
/**
 * 登录
**/
$verifycode = 1;
define('IN_ADMIN', true);
include("../includes/common.php");
if(isset($_POST['user']) && isset($_POST['pass'])){
	$user=trim($_POST['user']);
	$pass=$_POST['pass'];
	$code=isset($_POST['code']) ? trim($_POST['code']) : '';
	if (!pan_verify_request_csrf_token()) {
		@header('Content-Type: text/html; charset=UTF-8');
		exit("<script language='javascript'>alert('页面已过期，请刷新后重试！');history.go(-1);</script>");
	}elseif (is_rate_limited('admin_login', $clientip, 5, 900)) {
		@header('Content-Type: text/html; charset=UTF-8');
		exit("<script language='javascript'>alert('登录尝试过于频繁，请15分钟后再试！');history.go(-1);</script>");
	}elseif ($verifycode==1 && (!$code || !isset($_SESSION['vc_code']) || !hash_equals(strtolower($_SESSION['vc_code']), strtolower($code)))) {
		unset($_SESSION['vc_code']);
		record_rate_limit_failure('admin_login', $clientip, 900);
		@header('Content-Type: text/html; charset=UTF-8');
		exit("<script language='javascript'>alert('验证码错误！');history.go(-1);</script>");
	}elseif(hash_equals($conf['admin_user'], $user) && pan_verify_admin_password($pass, $conf['admin_pwd'])) {
		if (pan_password_needs_upgrade($conf['admin_pwd'])) {
			$conf['admin_pwd'] = password_hash($pass, PASSWORD_DEFAULT);
			saveSetting('admin_pwd', $conf['admin_pwd']);
		}
		clear_rate_limit('admin_login', $clientip);
		session_regenerate_id(true);
		$expiretime=time()+2592000;
		$session=hash_hmac('sha256', $user."\0".$conf['admin_pwd'], SYS_KEY);
		$token=pan_create_auth_token(['type'=>'admin', 'user'=>$user, 'sid'=>$session, 'exp'=>$expiretime], SYS_KEY);
		ob_clean();
		pan_set_auth_cookie("admin_token", $token, $expiretime);
		@header('Content-Type: text/html; charset=UTF-8');
		exit("<script language='javascript'>alert('登陆管理中心成功！');window.location.href='./';</script>");
	}else {
		record_rate_limit_failure('admin_login', $clientip, 900);
		@header('Content-Type: text/html; charset=UTF-8');
		exit("<script language='javascript'>alert('用户名或密码不正确！');history.go(-1);</script>");
	}
}elseif(isset($_GET['logout'])){
	pan_clear_auth_cookie("admin_token");
	$_SESSION = [];
	session_destroy();
	@header('Content-Type: text/html; charset=UTF-8');
	exit("<script language='javascript'>alert('您已成功注销本次登陆！');window.location.href='./login.php';</script>");
}elseif($islogin==1){
	exit("<script language='javascript'>alert('您已登陆！');window.location.href='./';</script>");
}
$title='用户登录';
$csrf_token = pan_csrf_token();
?>
<!DOCTYPE html>
<html lang="zh">
<head>
	<meta charset="UTF-8">
	<meta name="renderer" content="webkit">
	<meta name="viewport" content="width=device-width,height=device-height,inital-scale=1.0,maximum-scale=1.0,user-scalable=no;">
	<title>管理员登录</title>
	<link href="https://s4.zstatic.net/ajax/libs/twitter-bootstrap/3.4.1/css/bootstrap.min.css" rel="stylesheet"/>
	<link href="https://s4.zstatic.net/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet"/>
	<style>
body{background:linear-gradient(to right,#49bdad,#6a67c7) fixed}
.form-horizontal{background-color:#fff;text-align:center;padding:50px 30px 30px;box-shadow:12px 12px 0 0 rgba(0,0,0,.3);margin-top:50%}
.form-horizontal .heading{color:#555;font-size:30px;font-weight:600;letter-spacing:1px;text-transform:capitalize;margin:0 0 50px 0}
.form-horizontal .form-group{margin:0 auto 30px;position:relative}
.form-horizontal .form-group:nth-last-child(2){margin-bottom:20px}
.form-horizontal .form-group:last-child{margin:0}
.form-horizontal .form-group>i{color:#999;transform:translateY(-50%);position:absolute;left:5px;top:50%}
.form-horizontal .form-control{color:#7ab6b6;background-color:#fff;font-size:17px;letter-spacing:1px;height:40px;padding:5px 10px 2px 25px;box-shadow:0 0 0 0 transparent;border:none;border-bottom:1px solid rgba(0,0,0,.1);border-radius:0;display:inline-block}
.form-control::placeholder{color:rgba(0,0,0,.2);font-size:16px}
.form-horizontal .form-control:focus{border-bottom:1px solid #7ab6b6;box-shadow:none}
.form-horizontal .btn{color:#7ab6b6;background-color:#edf6f5;font-size:18px;font-weight:700;letter-spacing:1px;border-radius:5px;width:50%;height:45px;padding:7px 30px;margin:0 auto 25px;border:none;display:block;position:relative;transition:all .3s ease}
.form-horizontal .btn:focus,.form-horizontal .btn:hover{color:#fff;background-color:#7ab6b6}
.form-horizontal .btn:after,.form-horizontal .btn:before{content:'';background-color:#7ab6b6;height:50%;width:2px;position:absolute;left:0;bottom:0;z-index:1;transition:all .3s}
.form-horizontal .btn:after{bottom:auto;top:0;left:auto;right:0}
.form-horizontal .btn:hover:after,.form-horizontal .btn:hover:before{height:100%;width:50%;opacity:0}
	</style>
</head>
<body>
  <div class="container">
      <div class="row">
          <div class="col-md-offset-4 col-md-4 col-sm-offset-3 col-sm-6">
               <form class="form-horizontal" method="post">
				   <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                  <div class="heading">管理员登录</div>
                  <div class="form-group">
                      <i class="fa fa-user"></i><input required name="user" type="text" class="form-control" placeholder="用户名">
                  </div>
				  <div class="form-group">
					  <i class="fa fa-lock"></i><input required name="pass" type="password" class="form-control" placeholder="密码"/>
				  </div>
				  <div class="form-group">
					  <i class="fa fa-shield"></i><input required name="code" type="text" class="form-control" placeholder="验证码" autocomplete="off" maxlength="5"/>
					  <img src="code.php" alt="验证码" title="点击更换验证码" style="margin-top:10px;height:44px;cursor:pointer" onclick="this.src='code.php?t='+Date.now()">
				  </div>
                  <div class="form-group">
                      <button type="submit" class="btn btn-default"><i class="fa fa-arrow-right"></i></button>
                  </div>
              </form>
          </div>
      </div>
  </div>
</body>
</html>
