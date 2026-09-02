<?php

function pan_api_key_scopes() {
    return ['files.upload','shares.create','files.read_metadata','files.delete_owned'];
}

function pan_normalize_api_scopes($scopes) {
    $allowed = array_flip(pan_api_key_scopes());
    $values = is_array($scopes) ? $scopes : explode(',', (string)$scopes);
    $result = [];
    foreach($values as $scope){ $scope = trim($scope); if(isset($allowed[$scope])) $result[$scope] = $scope; }
    return array_values($result);
}

function pan_api_key_create($DB, $uid, $name, $scopes, $expiresAt = null, $ipRules = '', $requestLimit = 0, $dailyTrafficLimit = 0) {
    $uid = intval($uid);
    $scopes = pan_normalize_api_scopes($scopes);
    if($uid < 1 || !$scopes) return false;
    $secret = 'lnk_'.rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    $prefix = substr($secret, 0, 20);
    $ok = $DB->exec("INSERT INTO pre_api_key (uid,name,key_prefix,secret_hash,scopes,expires_at,ip_rules,request_limit,daily_traffic_limit,created_at) VALUES (:uid,:name,:prefix,:hash,:scopes,:expires_at,:ip_rules,:request_limit,:traffic,NOW())", [
        ':uid'=>$uid, ':name'=>substr(trim((string)$name),0,100), ':prefix'=>$prefix, ':hash'=>password_hash($secret, PASSWORD_DEFAULT), ':scopes'=>implode(',', $scopes), ':expires_at'=>$expiresAt ?: null, ':ip_rules'=>substr(trim((string)$ipRules),0,2000), ':request_limit'=>max(0, intval($requestLimit)), ':traffic'=>max(0, intval($dailyTrafficLimit)),
    ]);
    return $ok === false ? false : ['id'=>intval($DB->lastInsertId()), 'secret'=>$secret, 'prefix'=>$prefix, 'scopes'=>$scopes];
}

function pan_api_key_presented_secret() {
    $authorization = isset($_SERVER['HTTP_AUTHORIZATION']) ? trim($_SERVER['HTTP_AUTHORIZATION']) : '';
    if(stripos($authorization, 'Bearer ') === 0) return trim(substr($authorization, 7));
    return isset($_SERVER['HTTP_X_API_KEY']) ? trim($_SERVER['HTTP_X_API_KEY']) : (isset($_POST['api_token']) ? trim($_POST['api_token']) : '');
}

function pan_api_key_ip_allowed($rules, $ip) {
    $rules = preg_split('/[\s,|]+/', trim((string)$rules), -1, PREG_SPLIT_NO_EMPTY);
    if(!$rules) return true;
    foreach($rules as $rule) if(pan_ip_matches_rule($ip, $rule)) return true;
    return false;
}

function pan_api_key_authorize($DB, $secret, $scope, $clientIp, $bytes = 0) {
    if(strlen($secret) < 21 || strpos($secret, 'lnk_') !== 0) return ['ok'=>false, 'reason'=>'invalid'];
    $prefix = substr($secret, 0, 20);
    $key = $DB->getRow("SELECT k.*,u.enable AS user_enabled FROM pre_api_key k INNER JOIN pre_user u ON u.uid=k.uid WHERE k.key_prefix=:prefix LIMIT 1", [':prefix'=>$prefix]);
    if(!$key || !password_verify($secret, $key['secret_hash'])) return ['ok'=>false, 'reason'=>'invalid'];
    $keyId = intval($key['id']);
    if(intval($key['user_enabled']) !== 1) return ['ok'=>false, 'reason'=>'revoked', 'key'=>$key];
    if($key['revoked_at'] !== null) return ['ok'=>false, 'reason'=>'revoked', 'key'=>$key];
    if($key['expires_at'] !== null && strtotime($key['expires_at']) <= time()) return ['ok'=>false, 'reason'=>'expired', 'key'=>$key];
    if(!pan_api_key_ip_allowed($key['ip_rules'], $clientIp)) return ['ok'=>false, 'reason'=>'ip', 'key'=>$key];
    $scopes = pan_normalize_api_scopes($key['scopes']);
    if(!in_array($scope, $scopes, true)) return ['ok'=>false, 'reason'=>'scope', 'key'=>$key];
    $usage = $DB->getRow("SELECT requests,bytes FROM pre_api_key_usage WHERE key_id=:key_id AND usage_date=CURDATE() LIMIT 1", [':key_id'=>$keyId]);
    $requests = intval($usage ? $usage['requests'] : 0);
    $usedBytes = intval($usage ? $usage['bytes'] : 0);
    if(intval($key['request_limit']) > 0 && $requests + 1 > intval($key['request_limit'])) return ['ok'=>false, 'reason'=>'requests', 'key'=>$key];
    if(intval($key['daily_traffic_limit']) > 0 && $usedBytes + max(0, intval($bytes)) > intval($key['daily_traffic_limit'])) return ['ok'=>false, 'reason'=>'traffic', 'key'=>$key];
    $DB->exec("INSERT INTO pre_api_key_usage (key_id,usage_date,requests,bytes,updated_at) VALUES (:key_id,CURDATE(),1,:bytes,NOW()) ON DUPLICATE KEY UPDATE requests=requests+1,bytes=bytes+VALUES(bytes),updated_at=NOW()", [':key_id'=>$keyId, ':bytes'=>max(0, intval($bytes))]);
    $DB->exec("UPDATE pre_api_key SET last_used_at=NOW() WHERE id=:id", [':id'=>$keyId]);
    return ['ok'=>true, 'key'=>$key, 'uid'=>intval($key['uid'])];
}

function pan_api_key_error_message($reason) {
    $messages = ['invalid'=>'API Key 不正确', 'revoked'=>'API Key 已撤销', 'expired'=>'API Key 已过期', 'ip'=>'当前来源 IP 不允许使用此 API Key', 'scope'=>'API Key 没有此操作权限', 'requests'=>'API Key 今日请求次数已达到上限', 'traffic'=>'API Key 今日流量已达到上限'];
    return isset($messages[$reason]) ? $messages[$reason] : 'API Key 校验失败';
}
