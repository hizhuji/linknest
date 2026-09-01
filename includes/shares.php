<?php

function pan_share_code_is_valid($code) {
    return is_string($code) && preg_match('/^[A-Za-z0-9_-]{6,64}$/', $code) === 1;
}

function pan_generate_share_code($length = 10) {
    $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
    $code = '';
    for($i = 0; $i < $length; $i++) $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    return $code;
}

function pan_share_password_hash($password) {
    $password = trim((string)$password);
    return $password === '' ? null : password_hash($password, PASSWORD_DEFAULT);
}

function pan_share_password_verify($password, $stored) {
    if($stored === null || $stored === '') return true;
    if(pan_is_password_hash($stored)) return password_verify((string)$password, $stored);
    return hash_equals((string)$stored, (string)$password);
}

function pan_share_select_sql() {
    return "SELECT s.*, f.hash, f.name, f.type, f.size, f.block, f.hide, f.uid AS file_uid, f.ip AS upload_ip, f.addtime AS file_addtime FROM pre_share s INNER JOIN pre_file f ON f.id=s.file_id";
}

function pan_get_share_by_code($DB, $code) {
    if(!pan_share_code_is_valid($code)) return false;
    return $DB->getRow(pan_share_select_sql()." WHERE s.code=:code LIMIT 1", [':code'=>$code]);
}

function pan_get_default_share_by_hash($DB, $hash) {
    if(!preg_match('/^[a-f0-9]{32}$/i', (string)$hash)) return false;
    return $DB->getRow(pan_share_select_sql()." WHERE f.hash=:hash ORDER BY s.id ASC LIMIT 1", [':hash'=>$hash]);
}

function pan_get_file_shares($DB, $fileId) {
    return $DB->getAll(pan_share_select_sql()." WHERE s.file_id=:file_id ORDER BY s.id DESC", [':file_id'=>intval($fileId)]);
}

function pan_create_share($DB, $fileId, $options = []) {
    $fileId = intval($fileId);
    if($fileId < 1) return false;
    $requestedCode = isset($options['code']) ? trim((string)$options['code']) : '';
    $password = isset($options['password']) ? (string)$options['password'] : '';
    $expireAt = isset($options['expire_at']) && $options['expire_at'] !== '' ? $options['expire_at'] : null;
    $maxAccesses = pan_normalize_max_downloads(isset($options['max_accesses']) ? $options['max_accesses'] : 0);
    $oneTime = !empty($options['one_time']) ? 1 : 0;
    if($oneTime) $maxAccesses = 1;
    $uid = isset($options['uid']) ? intval($options['uid']) : 0;
    for($attempt = 0; $attempt < 8; $attempt++){
        $code = $requestedCode !== '' ? $requestedCode : pan_generate_share_code();
        if(!pan_share_code_is_valid($code)) return false;
        $result = $DB->exec("INSERT INTO pre_share (file_id,code,password,expire_at,max_accesses,access_count,status,one_time,created_by_uid,created_at) VALUES (:file_id,:code,:password,:expire_at,:max_accesses,0,1,:one_time,:uid,NOW())", [
            ':file_id'=>$fileId,
            ':code'=>$code,
            ':password'=>pan_share_password_hash($password),
            ':expire_at'=>$expireAt,
            ':max_accesses'=>$maxAccesses,
            ':one_time'=>$oneTime,
            ':uid'=>$uid,
        ]);
        if($result !== false) return pan_get_share_by_code($DB, $code);
        if($requestedCode !== '') return false;
    }
    return false;
}

function pan_share_access_error($share, $now = null) {
    if(!$share || intval($share['status']) !== 1) return 'revoked';
    if(intval($share['block']) > 0) return 'blocked';
    $now = $now === null ? time() : intval($now);
    if(!empty($share['expire_at'])){
        $expires = strtotime($share['expire_at']);
        if($expires !== false && $expires <= $now) return 'expired';
    }
    $max = intval($share['max_accesses']);
    if($max > 0 && intval($share['access_count']) >= $max) return 'limit';
    return null;
}

function pan_share_access_message($reason) {
    if($reason === 'revoked') return '该分享链接已被撤销';
    if($reason === 'blocked') return '该文件已被管理员封禁';
    if($reason === 'expired') return '该分享链接已过期';
    if($reason === 'limit') return '该分享链接已达到最大访问次数';
    return '该分享链接当前不可用';
}

function pan_create_share_access_token($share, $key, $ttl = 1800) {
    return pan_create_auth_token(['type'=>'share_access', 'share_id'=>intval($share['id']), 'code'=>(string)$share['code'], 'exp'=>time()+max(60, intval($ttl))], $key);
}

function pan_verify_share_access_token($token, $share, $key) {
    $payload = pan_read_auth_token((string)$token, $key);
    return is_array($payload) && isset($payload['type'], $payload['share_id'], $payload['code']) && $payload['type'] === 'share_access' && intval($payload['share_id']) === intval($share['id']) && hash_equals((string)$share['code'], (string)$payload['code']);
}

function pan_record_share_access($DB, $share) {
    $stmt = $DB->query("UPDATE pre_share SET access_count=access_count+1,last_access_at=NOW(),status=IF(one_time=1,0,status) WHERE id=:id AND status=1 AND (expire_at IS NULL OR expire_at>NOW()) AND (max_accesses=0 OR access_count<max_accesses)", [':id'=>intval($share['id'])]);
    if(!$stmt || $stmt->rowCount() !== 1) return false;
    $DB->exec("UPDATE pre_file SET lasttime=NOW(),count=count+1 WHERE id=:id", [':id'=>intval($share['file_id'])]);
    return true;
}

function pan_mask_ip($ip) {
    if(filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)){
        $parts = explode('.', $ip);
        return $parts[0].'.'.$parts[1].'.'.$parts[2].'.*';
    }
    if(filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)){
        $packed = inet_pton($ip);
        if($packed !== false) return inet_ntop(substr($packed, 0, 6).str_repeat("\0", 10)).'/48';
    }
    return 'unknown';
}

function pan_record_share_event($DB, $share, $event, $bytes, $clientIp, $key, $retentionDays = 30) {
    if(!in_array($event, ['download', 'preview'], true)) return false;
    $bytes = max(0, intval($bytes));
    $userAgent = substr(isset($_SERVER['HTTP_USER_AGENT']) ? (string)$_SERVER['HTTP_USER_AGENT'] : '', 0, 500);
    $refererRaw = isset($_SERVER['HTTP_REFERER']) ? (string)$_SERVER['HTTP_REFERER'] : '';
    $refererParts = $refererRaw !== '' ? parse_url($refererRaw) : false;
    $referer = $refererParts && !empty($refererParts['host']) ? (isset($refererParts['scheme']) ? $refererParts['scheme'].'://' : '').$refererParts['host'].(isset($refererParts['path']) ? $refererParts['path'] : '/') : '';
    $referer = substr($referer, 0, 1000);
    $ipHash = hash_hmac('sha256', (string)$clientIp, (string)$key);
    $result = $DB->exec("INSERT INTO pre_access_log (share_id,file_id,event,bytes,ip_hash,ip_masked,user_agent,referer,created_at) VALUES (:share_id,:file_id,:event,:bytes,:ip_hash,:ip_masked,:user_agent,:referer,NOW())", [
        ':share_id'=>intval($share['id']), ':file_id'=>intval($share['file_id']), ':event'=>$event, ':bytes'=>$bytes,
        ':ip_hash'=>$ipHash, ':ip_masked'=>pan_mask_ip($clientIp), ':user_agent'=>$userAgent, ':referer'=>$referer,
    ]);
    if($result === false) return false;
    $downloads = $event === 'download' ? 1 : 0;
    $previews = $event === 'preview' ? 1 : 0;
    $DB->exec("INSERT INTO pre_access_daily (share_id,access_date,requests,downloads,previews,bytes) VALUES (:share_id,CURDATE(),1,:downloads,:previews,:bytes) ON DUPLICATE KEY UPDATE requests=requests+1,downloads=downloads+VALUES(downloads),previews=previews+VALUES(previews),bytes=bytes+VALUES(bytes)", [
        ':share_id'=>intval($share['id']), ':downloads'=>$downloads, ':previews'=>$previews, ':bytes'=>$bytes,
    ]);
    $retentionDays = max(1, min(3650, intval($retentionDays)));
    if(random_int(1, 100) === 1) $DB->exec("DELETE FROM pre_access_log WHERE created_at<DATE_SUB(NOW(),INTERVAL {$retentionDays} DAY)");
    return true;
}

function pan_get_share_logs($DB, $shareId, $limit = 10) {
    $limit = max(1, min(100, intval($limit)));
    return $DB->getAll("SELECT event,bytes,ip_masked,user_agent,referer,created_at FROM pre_access_log WHERE share_id=:share_id ORDER BY id DESC LIMIT {$limit}", [':share_id'=>intval($shareId)]);
}

function pan_split_rules($value) {
    $rules = preg_split('/[\r\n,|]+/', strtolower((string)$value), -1, PREG_SPLIT_NO_EMPTY);
    return array_values(array_filter(array_map('trim', $rules), function($rule){ return $rule !== ''; }));
}

function pan_host_matches_rule($host, $rule) {
    $host = strtolower(rtrim((string)$host, '.'));
    $rule = strtolower(rtrim(trim((string)$rule), '.'));
    if($host === '' || $rule === '') return false;
    if(strpos($rule, '*.') === 0){
        $base = substr($rule, 2);
        return $host !== $base && substr($host, -strlen('.'.$base)) === '.'.$base;
    }
    return hash_equals($rule, $host);
}

function pan_share_referer_allowed($share, $referer) {
    $mode = isset($share['referer_mode']) ? intval($share['referer_mode']) : 0;
    if($mode === 0) return true;
    $referer = trim((string)$referer);
    if($referer === '') return !empty($share['allow_empty_referer']);
    $host = parse_url($referer, PHP_URL_HOST);
    if(!$host) return false;
    $siteHost = isset($_SERVER['HTTP_HOST']) ? parse_url('http://'.$_SERVER['HTTP_HOST'], PHP_URL_HOST) : '';
    if($siteHost && hash_equals(strtolower($siteHost), strtolower($host))) return true;
    $matched = false;
    foreach(pan_split_rules(isset($share['referer_rules']) ? $share['referer_rules'] : '') as $rule){
        if(pan_host_matches_rule($host, $rule)){ $matched = true; break; }
    }
    return $mode === 1 ? $matched : !$matched;
}

function pan_share_user_agent_allowed($share, $userAgent) {
    $userAgent = strtolower((string)$userAgent);
    foreach(pan_split_rules(isset($share['ua_blocklist']) ? $share['ua_blocklist'] : '') as $blocked){
        if($blocked !== '' && strpos($userAgent, $blocked) !== false) return false;
    }
    return true;
}

function pan_public_ip($ip) {
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
}

function pan_safe_webhook_target($url) {
    $parts = parse_url(trim((string)$url));
    if(!$parts || strtolower(isset($parts['scheme']) ? $parts['scheme'] : '') !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) return false;
    $host = strtolower($parts['host']);
    if($host === 'localhost' || substr($host, -6) === '.local') return false;
    if(filter_var($host, FILTER_VALIDATE_IP)) return pan_public_ip($host) ? $host : false;
    $records = function_exists('dns_get_record') ? @dns_get_record($host, DNS_A | DNS_AAAA) : [];
    if(!$records) return false;
    $resolved = null;
    foreach($records as $record){
        $ip = isset($record['ip']) ? $record['ip'] : (isset($record['ipv6']) ? $record['ipv6'] : null);
        if(!$ip || !pan_public_ip($ip)) return false;
        if($resolved === null) $resolved = $ip;
    }
    return $resolved;
}

function pan_send_share_webhook($url, $payload) {
    $ip = pan_safe_webhook_target($url);
    if($ip === false || !function_exists('curl_init')) return false;
    $parts = parse_url($url);
    $host = $parts['host'];
    $port = isset($parts['port']) ? intval($parts['port']) : 443;
    $received = 0;
    $ch = curl_init($url);
    $resolvedIp = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? '['.$ip.']' : $ip;
    curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>json_encode($payload), CURLOPT_HTTPHEADER=>['Content-Type: application/json'], CURLOPT_RETURNTRANSFER=>false, CURLOPT_FOLLOWLOCATION=>false, CURLOPT_CONNECTTIMEOUT=>2, CURLOPT_TIMEOUT=>4, CURLOPT_RESOLVE=>[$host.':'.$port.':'.$resolvedIp], CURLOPT_WRITEFUNCTION=>function($ch, $data) use (&$received){ $received += strlen($data); return $received > 65536 ? 0 : strlen($data); }]);
    if(defined('CURLOPT_PROTOCOLS')) curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
    $ok = curl_exec($ch);
    $status = intval(curl_getinfo($ch, CURLINFO_RESPONSE_CODE));
    curl_close($ch);
    return $ok !== false && $status >= 200 && $status < 300;
}

function pan_share_alert($DB, $share, $type, $details) {
    $recent = intval($DB->getColumn("SELECT count(*) FROM pre_alert_log WHERE share_id=:share_id AND alert_type=:alert_type AND created_at>DATE_SUB(NOW(),INTERVAL 10 MINUTE)", [':share_id'=>intval($share['id']), ':alert_type'=>$type]));
    if($recent > 0) return false;
    $notified = 0;
    if(!empty($share['webhook_url'])) $notified = pan_send_share_webhook($share['webhook_url'], ['event'=>'share_limit', 'type'=>$type, 'share_code'=>$share['code'], 'details'=>$details, 'time'=>date(DATE_ATOM)]) ? 1 : 0;
    return $DB->exec("INSERT INTO pre_alert_log (share_id,alert_type,details,notified,created_at) VALUES (:share_id,:alert_type,:details,:notified,NOW())", [':share_id'=>intval($share['id']), ':alert_type'=>$type, ':details'=>substr((string)$details, 0, 1000), ':notified'=>$notified]) !== false;
}

function pan_enforce_share_controls($DB, $share, $clientIp, $bytes = 0) {
    if(!pan_share_referer_allowed($share, isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '')) return 'referer';
    if(!pan_share_user_agent_allowed($share, isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '')) return 'user_agent';
    $requestLimit = isset($share['request_limit']) ? intval($share['request_limit']) : 0;
    if($requestLimit > 0){
        $ipHash = hash_hmac('sha256', (string)$clientIp, SYS_KEY);
        $window = intval(floor(time() / 60) * 60);
        $DB->exec("INSERT INTO pre_share_rate (share_id,ip_hash,window_start,requests) VALUES (:share_id,:ip_hash,:window,1) ON DUPLICATE KEY UPDATE requests=IF(window_start=:window_reset,requests+1,1),window_start=:window_update", [':share_id'=>intval($share['id']), ':ip_hash'=>$ipHash, ':window'=>$window, ':window_reset'=>$window, ':window_update'=>$window]);
        $requests = intval($DB->getColumn("SELECT requests FROM pre_share_rate WHERE share_id=:share_id AND ip_hash=:ip_hash", [':share_id'=>intval($share['id']), ':ip_hash'=>$ipHash]));
        if($requests > $requestLimit) return 'rate';
    }
    $bytes = max(0, intval($bytes));
    $dailyLimit = isset($share['daily_traffic_limit']) ? intval($share['daily_traffic_limit']) : 0;
    if($dailyLimit > 0){
        $used = intval($DB->getColumn("SELECT COALESCE(bytes,0) FROM pre_access_daily WHERE share_id=:share_id AND access_date=CURDATE()", [':share_id'=>intval($share['id'])]));
        if($used + $bytes > $dailyLimit) return 'daily_traffic';
    }
    $monthlyLimit = isset($share['monthly_traffic_limit']) ? intval($share['monthly_traffic_limit']) : 0;
    if($monthlyLimit > 0){
        $used = intval($DB->getColumn("SELECT COALESCE(SUM(bytes),0) FROM pre_access_daily WHERE share_id=:share_id AND access_date>=DATE_FORMAT(CURDATE(),'%Y-%m-01')", [':share_id'=>intval($share['id'])]));
        if($used + $bytes > $monthlyLimit) return 'monthly_traffic';
    }
    return null;
}

function pan_share_control_message($reason) {
    if($reason === 'referer') return '当前来源域名不允许访问此分享';
    if($reason === 'user_agent') return '当前客户端不允许访问此分享';
    if($reason === 'rate') return '请求过于频繁，请稍后再试';
    if($reason === 'daily_traffic') return '该分享已达到今日流量上限';
    if($reason === 'monthly_traffic') return '该分享已达到本月流量上限';
    return '该分享当前受访问策略限制';
}

function pan_gigabytes_to_bytes($value) {
    $value = max(0, min(1048576, floatval($value)));
    return intval(round($value * 1073741824));
}

function pan_requested_bytes($size) {
    $size = max(0, intval($size));
    $range = function_exists('get_file_range') ? get_file_range($size) : false;
    return $range ? max(0, intval($range[1]) - intval($range[0]) + 1) : $size;
}

function pan_share_is_owner($share, $isAdmin, $isUser, $uid, $sessionShareIds = []) {
    if($isAdmin) return true;
    if($isUser && intval($share['created_by_uid']) === intval($uid)) return true;
    return in_array(intval($share['id']), array_map('intval', (array)$sessionShareIds), true);
}

function pan_delete_share($DB, $stor, $share) {
    $shareId = intval($share['id']);
    $fileId = intval($share['file_id']);
    $DB->exec("DELETE FROM pre_access_log WHERE share_id=:id", [':id'=>$shareId]);
    $DB->exec("DELETE FROM pre_access_daily WHERE share_id=:id", [':id'=>$shareId]);
    $DB->exec("DELETE FROM pre_share_rate WHERE share_id=:id", [':id'=>$shareId]);
    $DB->exec("DELETE FROM pre_alert_log WHERE share_id=:id", [':id'=>$shareId]);
    if($DB->exec("DELETE FROM pre_share WHERE id=:id", [':id'=>$shareId]) === false) return false;
    $remaining = intval($DB->getColumn("SELECT count(*) FROM pre_share WHERE file_id=:file_id", [':file_id'=>$fileId]));
    if($remaining === 0){
        $stor->delete($share['hash']);
        $DB->exec("DELETE FROM pre_file WHERE id=:id", [':id'=>$fileId]);
    }
    return true;
}
