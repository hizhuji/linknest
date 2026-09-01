<?php
require __DIR__ . '/../includes/security.php';
require __DIR__ . '/../includes/lib/NativeOauth.php';

if(session_status() !== PHP_SESSION_ACTIVE) session_start();

$google = new \lib\NativeOauth('google', ['clientId'=>'client-id', 'clientSecret'=>'secret'], 'https://example.com/login.php?provider=google');
$url = $google->loginUrl();
if(strpos($url, 'https://accounts.google.com/o/oauth2/v2/auth?') !== 0) throw new RuntimeException('Google authorization endpoint mismatch');
$query = [];
parse_str(parse_url($url, PHP_URL_QUERY), $query);
if($query['client_id'] !== 'client-id' || $query['redirect_uri'] !== 'https://example.com/login.php?provider=google') throw new RuntimeException('Google authorization parameters mismatch');
if(empty($query['state']) || empty($query['nonce']) || !hash_equals($_SESSION['native_oauth']['state'], $query['state'])) throw new RuntimeException('Google state or nonce missing');

$apple = new \lib\NativeOauth('apple', ['clientId'=>'service-id'], 'https://example.com/login.php?provider=apple');
$url = $apple->loginUrl();
if(strpos($url, 'https://appleid.apple.com/auth/authorize?') !== 0) throw new RuntimeException('Apple authorization endpoint mismatch');
parse_str(parse_url($url, PHP_URL_QUERY), $query);
if($query['response_mode'] !== 'query' || $query['client_id'] !== 'service-id') throw new RuntimeException('Apple authorization parameters mismatch');

echo "native oauth tests passed" . PHP_EOL;
