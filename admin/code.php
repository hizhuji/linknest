<?php
require_once '../includes/security.php';
pan_start_session();

$alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
$code = '';
for($i = 0; $i < 5; $i++) $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
$_SESSION['vc_code'] = strtolower($code);

header('Content-Type: image/svg+xml; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');
$escaped = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
echo '<svg xmlns="http://www.w3.org/2000/svg" width="150" height="44" viewBox="0 0 150 44"><rect width="150" height="44" fill="#f3f6f8"/><path d="M4 10L146 34M4 34L146 10" stroke="#ccd6dd"/><text x="75" y="30" text-anchor="middle" font-family="Consolas,monospace" font-size="24" letter-spacing="5" fill="#263238">'.$escaped.'</text></svg>';
