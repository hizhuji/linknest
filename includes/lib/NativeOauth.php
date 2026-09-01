<?php
namespace lib;

class NativeOauth {
	private $provider;
	private $config;
	private $redirectUri;
	private $errmsg;

	public function __construct($provider, $config, $redirectUri) {
		$this->provider = $provider;
		$this->config = $config;
		$this->redirectUri = $redirectUri;
	}

	public function errmsg(){ return $this->errmsg; }

	public function loginUrl() {
		$state = pan_random_string(48);
		$nonce = pan_random_string(48);
		$_SESSION['native_oauth'] = ['provider'=>$this->provider, 'state'=>$state, 'nonce'=>$nonce, 'created'=>time()];
		if($this->provider === 'google'){
			$query = [
				'client_id'=>$this->config['clientId'], 'redirect_uri'=>$this->redirectUri,
				'response_type'=>'code', 'scope'=>'openid profile email', 'state'=>$state,
				'nonce'=>$nonce, 'prompt'=>'select_account',
			];
			return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query($query);
		}
		if($this->provider === 'apple'){
			$query = [
				'client_id'=>$this->config['clientId'], 'redirect_uri'=>$this->redirectUri,
				'response_type'=>'code', 'response_mode'=>'query', 'state'=>$state, 'nonce'=>$nonce,
			];
			return 'https://appleid.apple.com/auth/authorize?'.http_build_query($query);
		}
		$this->errmsg = '不支持的登录方式';
		return false;
	}

	public function callback($code, $state) {
		$session = isset($_SESSION['native_oauth']) ? $_SESSION['native_oauth'] : null;
		unset($_SESSION['native_oauth']);
		if(!is_array($session) || $session['provider'] !== $this->provider || empty($session['state']) || !hash_equals($session['state'], (string)$state)){
			$this->errmsg = '登录状态校验失败，请重新登录';
			return false;
		}
		if(empty($session['created']) || time() - (int)$session['created'] > 600){
			$this->errmsg = '登录请求已过期，请重新登录';
			return false;
		}
		if($this->provider === 'google') return $this->googleCallback($code, $session['nonce']);
		if($this->provider === 'apple') return $this->appleCallback($code, $session['nonce']);
		$this->errmsg = '不支持的登录方式';
		return false;
	}

	private function googleCallback($code, $nonce) {
		$token = $this->request('POST', 'https://oauth2.googleapis.com/token', [
			'code'=>$code, 'client_id'=>$this->config['clientId'], 'client_secret'=>$this->config['clientSecret'],
			'redirect_uri'=>$this->redirectUri, 'grant_type'=>'authorization_code',
		]);
		if(!$token || empty($token['access_token']) || empty($token['id_token'])) return false;
		if(!$this->verifyIdToken($token['id_token'], 'https://www.googleapis.com/oauth2/v3/certs')) return false;
		$claims = $this->jwtPayload($token['id_token']);
		if(!$this->validateClaims($claims, ['https://accounts.google.com', 'accounts.google.com'], $this->config['clientId'], $nonce)) return false;
		$user = $this->request('GET', 'https://openidconnect.googleapis.com/v1/userinfo', null, ['Authorization: Bearer '.$token['access_token']]);
		if(!$user || empty($user['sub']) || !hash_equals((string)$claims['sub'], (string)$user['sub'])){
			$this->errmsg = 'Google 用户信息校验失败';
			return false;
		}
		return ['openid'=>(string)$user['sub'], 'nickname'=>!empty($user['name']) ? $user['name'] : 'Google 用户', 'faceimg'=>isset($user['picture']) ? $user['picture'] : ''];
	}

	private function appleCallback($code, $nonce) {
		$secret = $this->appleClientSecret();
		if(!$secret) return false;
		$token = $this->request('POST', 'https://appleid.apple.com/auth/token', [
			'code'=>$code, 'client_id'=>$this->config['clientId'], 'client_secret'=>$secret,
			'redirect_uri'=>$this->redirectUri, 'grant_type'=>'authorization_code',
		]);
		if(!$token || empty($token['id_token'])) return false;
		if(!$this->verifyIdToken($token['id_token'], 'https://appleid.apple.com/auth/keys')) return false;
		$claims = $this->jwtPayload($token['id_token']);
		if(!$this->validateClaims($claims, ['https://appleid.apple.com'], $this->config['clientId'], $nonce)) return false;
		return ['openid'=>(string)$claims['sub'], 'nickname'=>'Apple 用户', 'faceimg'=>''];
	}

	private function validateClaims($claims, $issuers, $audience, $nonce) {
		if(!is_array($claims) || empty($claims['sub']) || empty($claims['iss']) || !in_array($claims['iss'], $issuers, true)){
			$this->errmsg = '登录身份凭证无效'; return false;
		}
		$aud = isset($claims['aud']) ? $claims['aud'] : null;
		if((is_array($aud) && !in_array($audience, $aud, true)) || (!is_array($aud) && $aud !== $audience)){
			$this->errmsg = '登录身份凭证不属于本站'; return false;
		}
		if(empty($claims['exp']) || (int)$claims['exp'] <= time() || empty($claims['nonce']) || !hash_equals((string)$nonce, (string)$claims['nonce'])){
			$this->errmsg = '登录身份凭证已过期或校验失败'; return false;
		}
		return true;
	}

	private function appleClientSecret() {
		if(!function_exists('openssl_sign')){ $this->errmsg = '服务器未启用 OpenSSL 扩展'; return false; }
		$header = pan_base64url_encode(json_encode(['alg'=>'ES256', 'kid'=>$this->config['keyId']]));
		$now = time();
		$payload = pan_base64url_encode(json_encode([
			'iss'=>$this->config['teamId'], 'iat'=>$now, 'exp'=>$now + 300,
			'aud'=>'https://appleid.apple.com', 'sub'=>$this->config['clientId'],
		]));
		$input = $header.'.'.$payload;
		$key = openssl_pkey_get_private(str_replace('\\n', "\n", trim($this->config['privateKey'])));
		if(!$key || !openssl_sign($input, $der, $key, OPENSSL_ALGO_SHA256)){
			$this->errmsg = 'Apple 私钥无效或签名失败'; return false;
		}
		$signature = $this->derToJose($der, 32);
		if($signature === false){ $this->errmsg = 'Apple 登录签名格式转换失败'; return false; }
		return $input.'.'.pan_base64url_encode($signature);
	}

	private function derToJose($der, $partLength) {
		$offset = 0;
		if(ord($der[$offset++]) !== 0x30) return false;
		$this->readDerLength($der, $offset);
		if(ord($der[$offset++]) !== 0x02) return false;
		$rLength = $this->readDerLength($der, $offset);
		$r = substr($der, $offset, $rLength); $offset += $rLength;
		if(ord($der[$offset++]) !== 0x02) return false;
		$sLength = $this->readDerLength($der, $offset);
		$s = substr($der, $offset, $sLength);
		$r = str_pad(ltrim($r, "\0"), $partLength, "\0", STR_PAD_LEFT);
		$s = str_pad(ltrim($s, "\0"), $partLength, "\0", STR_PAD_LEFT);
		return substr($r, -$partLength).substr($s, -$partLength);
	}

	private function readDerLength($der, &$offset) {
		$length = ord($der[$offset++]);
		if($length < 0x80) return $length;
		$count = $length & 0x7f; $length = 0;
		while($count-- > 0) $length = ($length << 8) | ord($der[$offset++]);
		return $length;
	}

	private function jwtPayload($jwt) {
		$parts = explode('.', (string)$jwt);
		if(count($parts) !== 3) return false;
		$payload = pan_base64url_decode($parts[1]);
		return $payload === false ? false : json_decode($payload, true);
	}

	private function verifyIdToken($jwt, $keysUrl) {
		if(!function_exists('openssl_verify')){ $this->errmsg = '服务器未启用 OpenSSL 扩展'; return false; }
		$parts = explode('.', (string)$jwt);
		if(count($parts) !== 3){ $this->errmsg = '登录身份凭证格式错误'; return false; }
		$headerRaw = pan_base64url_decode($parts[0]);
		$signature = pan_base64url_decode($parts[2]);
		$header = $headerRaw === false ? null : json_decode($headerRaw, true);
		if(!is_array($header) || empty($header['kid']) || !isset($header['alg']) || $header['alg'] !== 'RS256' || $signature === false){
			$this->errmsg = '登录身份凭证签名信息无效'; return false;
		}
		$jwks = $this->request('GET', $keysUrl);
		if(!$jwks || empty($jwks['keys']) || !is_array($jwks['keys'])) return false;
		foreach($jwks['keys'] as $jwk){
			if(!isset($jwk['kid'], $jwk['kty'], $jwk['n'], $jwk['e']) || $jwk['kid'] !== $header['kid'] || $jwk['kty'] !== 'RSA') continue;
			$pem = $this->rsaJwkToPem($jwk['n'], $jwk['e']);
			if($pem && openssl_verify($parts[0].'.'.$parts[1], $signature, $pem, OPENSSL_ALGO_SHA256) === 1) return true;
		}
		$this->errmsg = '登录身份凭证签名校验失败';
		return false;
	}

	private function rsaJwkToPem($modulus, $exponent) {
		$n = pan_base64url_decode($modulus); $e = pan_base64url_decode($exponent);
		if($n === false || $e === false) return false;
		$rsa = $this->asn1Sequence($this->asn1Integer($n).$this->asn1Integer($e));
		$algorithm = hex2bin('300d06092a864886f70d0101010500');
		$der = $this->asn1Sequence($algorithm."\x03".$this->asn1Length(strlen($rsa)+1)."\x00".$rsa);
		return "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END PUBLIC KEY-----\n";
	}

	private function asn1Integer($value) {
		$value = ltrim($value, "\x00");
		if($value === '') $value = "\x00";
		if((ord($value[0]) & 0x80) !== 0) $value = "\x00".$value;
		return "\x02".$this->asn1Length(strlen($value)).$value;
	}

	private function asn1Sequence($value) { return "\x30".$this->asn1Length(strlen($value)).$value; }

	private function asn1Length($length) {
		if($length < 128) return chr($length);
		$encoded = '';
		while($length > 0){ $encoded = chr($length & 0xff).$encoded; $length >>= 8; }
		return chr(0x80 | strlen($encoded)).$encoded;
	}

	private function request($method, $url, $form = null, $headers = []) {
		if(!function_exists('curl_init')){ $this->errmsg = '服务器未启用 cURL 扩展'; return false; }
		$ch = curl_init($url);
		$options = [CURLOPT_RETURNTRANSFER=>true, CURLOPT_CONNECTTIMEOUT=>10, CURLOPT_TIMEOUT=>30, CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_SSL_VERIFYHOST=>2];
		if($method === 'POST'){
			$options[CURLOPT_POST] = true;
			$options[CURLOPT_POSTFIELDS] = http_build_query($form);
			$headers[] = 'Content-Type: application/x-www-form-urlencoded';
		}
		if($headers) $options[CURLOPT_HTTPHEADER] = $headers;
		curl_setopt_array($ch, $options);
		$body = curl_exec($ch); $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $error = curl_error($ch); curl_close($ch);
		$data = $body === false ? null : json_decode($body, true);
		if($body === false || $status < 200 || $status >= 300 || !is_array($data)){
			$this->errmsg = $error !== '' ? $error : (isset($data['error_description']) ? $data['error_description'] : '登录服务请求失败（HTTP '.$status.'）');
			return false;
		}
		return $data;
	}
}
