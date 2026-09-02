<?php

function pan_quota_limits($DB, $uid, $conf = []) {
    $row = $DB->getRow("SELECT byte_limit,file_limit,daily_upload_limit FROM pre_user_quota WHERE uid=:uid LIMIT 1", [':uid'=>intval($uid)]);
    return [
        'byte_limit'=>intval($row ? $row['byte_limit'] : (isset($conf['user_quota_bytes']) ? $conf['user_quota_bytes'] : 0)),
        'file_limit'=>intval($row ? $row['file_limit'] : (isset($conf['user_quota_files']) ? $conf['user_quota_files'] : 0)),
        'daily_upload_limit'=>intval($row ? $row['daily_upload_limit'] : (isset($conf['user_daily_upload_bytes']) ? $conf['user_daily_upload_bytes'] : 0)),
    ];
}

function pan_quota_rebuild_user($DB, $uid) {
    $uid = intval($uid);
    if($uid < 1) return ['used_bytes'=>0, 'file_count'=>0];
    $row = $DB->getRow("SELECT COALESCE(SUM(size),0) AS used_bytes,COUNT(*) AS file_count FROM pre_file WHERE uid=:uid", [':uid'=>$uid]);
    $used = intval($row['used_bytes']);
    $count = intval($row['file_count']);
    $DB->exec("INSERT INTO pre_user_usage (uid,used_bytes,file_count,daily_upload_bytes,daily_upload_date,updated_at) VALUES (:uid,:used,:count,0,CURDATE(),NOW()) ON DUPLICATE KEY UPDATE used_bytes=VALUES(used_bytes),file_count=VALUES(file_count),updated_at=NOW()", [':uid'=>$uid, ':used'=>$used, ':count'=>$count]);
    return ['used_bytes'=>$used, 'file_count'=>$count];
}

function pan_quota_usage($DB, $uid) {
    $uid = intval($uid);
    $row = $DB->getRow("SELECT used_bytes,file_count,daily_upload_bytes,daily_upload_date FROM pre_user_usage WHERE uid=:uid LIMIT 1", [':uid'=>$uid]);
    if(!$row) return pan_quota_rebuild_user($DB, $uid) + ['daily_upload_bytes'=>0];
    if($row['daily_upload_date'] !== date('Y-m-d')){
        $DB->exec("UPDATE pre_user_usage SET daily_upload_bytes=0,daily_upload_date=CURDATE(),updated_at=NOW() WHERE uid=:uid", [':uid'=>$uid]);
        $row['daily_upload_bytes'] = 0;
    }
    return ['used_bytes'=>intval($row['used_bytes']), 'file_count'=>intval($row['file_count']), 'daily_upload_bytes'=>intval($row['daily_upload_bytes'])];
}

function pan_quota_check_upload($DB, $uid, $bytes, $files, $conf = []) {
    $uid = intval($uid);
    if($uid < 1 || empty($conf['user_quota_enforced'])) return null;
    $usage = pan_quota_usage($DB, $uid);
    $limits = pan_quota_limits($DB, $uid, $conf);
    if($limits['byte_limit'] > 0 && $usage['used_bytes'] + max(0, intval($bytes)) > $limits['byte_limit']) return 'storage';
    if($limits['file_limit'] > 0 && $usage['file_count'] + max(0, intval($files)) > $limits['file_limit']) return 'files';
    if($limits['daily_upload_limit'] > 0 && $usage['daily_upload_bytes'] + max(0, intval($bytes)) > $limits['daily_upload_limit']) return 'daily';
    return null;
}

function pan_quota_check_replace($DB, $uid, $oldBytes, $newBytes, $conf = []) {
    $uid = intval($uid);
    if($uid < 1 || empty($conf['user_quota_enforced'])) return null;
    $usage = pan_quota_usage($DB, $uid);
    $limits = pan_quota_limits($DB, $uid, $conf);
    $storageDelta = max(0, intval($newBytes) - intval($oldBytes));
    if($limits['byte_limit'] > 0 && $usage['used_bytes'] + $storageDelta > $limits['byte_limit']) return 'storage';
    if($limits['daily_upload_limit'] > 0 && $usage['daily_upload_bytes'] + max(0, intval($newBytes)) > $limits['daily_upload_limit']) return 'daily';
    return null;
}

function pan_quota_upload_error($reason) {
    if($reason === 'storage') return '账户存储空间不足';
    if($reason === 'files') return '账户文件数量已达到上限';
    if($reason === 'daily') return '账户今日上传流量已达到上限';
    return '账户配额不足';
}

function pan_quota_record_file_created($DB, $uid, $bytes) {
    $uid = intval($uid);
    if($uid < 1) return true;
    return $DB->exec("INSERT INTO pre_user_usage (uid,used_bytes,file_count,daily_upload_bytes,daily_upload_date,updated_at) VALUES (:uid,:bytes,1,:bytes,CURDATE(),NOW()) ON DUPLICATE KEY UPDATE used_bytes=used_bytes+VALUES(used_bytes),file_count=file_count+1,daily_upload_bytes=IF(daily_upload_date=CURDATE(),daily_upload_bytes+VALUES(daily_upload_bytes),VALUES(daily_upload_bytes)),daily_upload_date=CURDATE(),updated_at=NOW()", [':uid'=>$uid, ':bytes'=>max(0, intval($bytes))]) !== false;
}

function pan_quota_record_file_purged($DB, $uid, $bytes) {
    $uid = intval($uid);
    if($uid < 1) return true;
    return $DB->exec("UPDATE pre_user_usage SET used_bytes=GREATEST(0,used_bytes-:bytes),file_count=GREATEST(0,file_count-1),updated_at=NOW() WHERE uid=:uid", [':uid'=>$uid, ':bytes'=>max(0, intval($bytes))]) !== false;
}

function pan_quota_adjust_file_size($DB, $uid, $oldBytes, $newBytes, $conf = []) {
    $delta = intval($newBytes) - intval($oldBytes);
    if(intval($uid) > 0) $DB->exec("UPDATE pre_user_usage SET used_bytes=GREATEST(0,used_bytes+:delta),daily_upload_bytes=IF(daily_upload_date=CURDATE(),daily_upload_bytes+:uploaded,:uploaded),daily_upload_date=CURDATE(),updated_at=NOW() WHERE uid=:uid", [':delta'=>$delta, ':uploaded'=>max(0, intval($newBytes)), ':uid'=>intval($uid)]);
    return null;
}
