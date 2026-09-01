<?php
include './includes/common.php';
$code = isset($_GET['code']) ? trim($_GET['code']) : (isset($_SERVER['PATH_INFO']) ? trim($_SERVER['PATH_INFO'], '/') : '');
if(!pan_share_code_is_valid($code)){
    http_response_code(404);
    exit('Share Not Found');
}
header('Location: '.$siteurl.'file.php?share='.rawurlencode($code), true, 302);
exit;
