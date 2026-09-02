<?php

function pan_audit_admin_action($DB, $actor, $action, $resourceType = '', $resourceId = '', $context = [], $clientIp = null) {
    $clientIp = $clientIp === null ? (isset($GLOBALS['clientip']) ? $GLOBALS['clientip'] : '') : $clientIp;
    $contextJson = $context ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
    if($contextJson === false) $contextJson = null;
    return $DB->exec("INSERT INTO pre_admin_audit (actor,action,resource_type,resource_id,ip_hash,ip_masked,context,created_at) VALUES (:actor,:action,:resource_type,:resource_id,:ip_hash,:ip_masked,:context,NOW())", [
        ':actor'=>substr((string)$actor, 0, 100), ':action'=>substr((string)$action, 0, 80),
        ':resource_type'=>substr((string)$resourceType, 0, 40), ':resource_id'=>substr((string)$resourceId, 0, 100),
        ':ip_hash'=>hash_hmac('sha256', (string)$clientIp, SYS_KEY), ':ip_masked'=>pan_mask_ip($clientIp),
        ':context'=>$contextJson,
    ]) !== false;
}

function pan_soft_delete_file($DB, $fileId, $actor, $reason = '') {
    $fileId = intval($fileId);
    $row = $DB->getRow("SELECT id,name,hash FROM pre_file WHERE id=:id AND deleted_at IS NULL LIMIT 1", [':id'=>$fileId]);
    if(!$row) return false;
    $result = $DB->exec("UPDATE pre_file SET deleted_at=NOW(),deleted_by=:actor,deletion_reason=:reason WHERE id=:id AND deleted_at IS NULL", [':actor'=>substr((string)$actor, 0, 100), ':reason'=>substr(trim((string)$reason), 0, 500), ':id'=>$fileId]);
    if($result === false) return false;
    pan_audit_admin_action($DB, $actor, 'file_trashed', 'file', $fileId, ['name'=>$row['name']]);
    return true;
}

function pan_restore_file($DB, $fileId, $actor) {
    $fileId = intval($fileId);
    $row = $DB->getRow("SELECT id,name FROM pre_file WHERE id=:id AND deleted_at IS NOT NULL LIMIT 1", [':id'=>$fileId]);
    if(!$row) return false;
    $result = $DB->exec("UPDATE pre_file SET deleted_at=NULL,deleted_by=NULL,deletion_reason=NULL WHERE id=:id", [':id'=>$fileId]);
    if($result === false) return false;
    pan_audit_admin_action($DB, $actor, 'file_restored', 'file', $fileId, ['name'=>$row['name']]);
    return true;
}

function pan_storage_object_is_referenced($DB, $hash) {
    $files = intval($DB->getColumn("SELECT count(*) FROM pre_file WHERE hash=:hash", [':hash'=>$hash]));
    $versions = intval($DB->getColumn("SELECT count(*) FROM pre_file_version WHERE hash=:hash", [':hash'=>$hash]));
    return $files > 0 || $versions > 0;
}

function pan_delete_storage_if_unreferenced($DB, $stor, $hash) {
    if($hash === '' || pan_storage_object_is_referenced($DB, $hash)) return true;
    return $stor->delete($hash);
}

function pan_enqueue_storage_cleanup($DB, $hash) {
    if(!preg_match('/^[a-f0-9]{32}$/i', (string)$hash)) return false;
    return $DB->exec("INSERT INTO pre_storage_cleanup (hash,attempts,last_error,created_at,last_attempt_at) VALUES (:hash,0,NULL,NOW(),NULL) ON DUPLICATE KEY UPDATE hash=VALUES(hash)", [':hash'=>$hash]) !== false;
}

function pan_run_storage_cleanup($DB, $stor, $limit = 100) {
    $limit = max(1, min(500, intval($limit)));
    $rows = $DB->getAll("SELECT hash FROM pre_storage_cleanup ORDER BY created_at ASC LIMIT {$limit}");
    $deleted = 0;
    foreach((array)$rows as $row){
        $hash = $row['hash'];
        if(pan_storage_object_is_referenced($DB, $hash)){
            $DB->exec("DELETE FROM pre_storage_cleanup WHERE hash=:hash", [':hash'=>$hash]);
            continue;
        }
        if($stor->delete($hash)){
            $DB->exec("DELETE FROM pre_storage_cleanup WHERE hash=:hash", [':hash'=>$hash]);
            $deleted++;
        }else{
            $message = method_exists($stor, 'errmsg') ? substr((string)$stor->errmsg(), 0, 1000) : 'storage delete failed';
            $DB->exec("UPDATE pre_storage_cleanup SET attempts=attempts+1,last_error=:last_error,last_attempt_at=NOW() WHERE hash=:hash", [':last_error'=>$message, ':hash'=>$hash]);
        }
    }
    return $deleted;
}

function pan_purge_file($DB, $stor, $fileId, $actor) {
    $fileId = intval($fileId);
    $row = $DB->getRow("SELECT * FROM pre_file WHERE id=:id AND deleted_at IS NOT NULL LIMIT 1", [':id'=>$fileId]);
    if(!$row) return false;
    $hashes = [$row['hash']];
    $versions = $DB->getAll("SELECT hash FROM pre_file_version WHERE file_id=:file_id", [':file_id'=>$fileId]);
    foreach((array)$versions as $version) $hashes[] = $version['hash'];
    $shareIds = $DB->getAll("SELECT id FROM pre_share WHERE file_id=:file_id", [':file_id'=>$fileId]);
    foreach((array)$shareIds as $shareId){
        $sid = intval($shareId['id']);
        $DB->exec("DELETE FROM pre_access_log WHERE share_id=:id", [':id'=>$sid]);
        $DB->exec("DELETE FROM pre_access_daily WHERE share_id=:id", [':id'=>$sid]);
        $DB->exec("DELETE FROM pre_share_rate WHERE share_id=:id", [':id'=>$sid]);
        $DB->exec("DELETE FROM pre_alert_log WHERE share_id=:id", [':id'=>$sid]);
    }
    if($DB->exec("DELETE FROM pre_share WHERE file_id=:file_id", [':file_id'=>$fileId]) === false) return false;
    $DB->exec("DELETE FROM pre_file_version WHERE file_id=:file_id", [':file_id'=>$fileId]);
    if($DB->exec("DELETE FROM pre_file WHERE id=:id", [':id'=>$fileId]) === false) return false;
    foreach(array_unique($hashes) as $hash){
        pan_enqueue_storage_cleanup($DB, $hash);
    }
    $deleted = pan_run_storage_cleanup($DB, $stor, 50);
    pan_audit_admin_action($DB, $actor, 'file_purged', 'file', $fileId, ['name'=>$row['name'], 'storage_cleanup_deleted'=>$deleted]);
    return true;
}

function pan_create_file_version($DB, $file, $actor, $note = '') {
    $number = intval($DB->getColumn("SELECT COALESCE(MAX(version_no),0)+1 FROM pre_file_version WHERE file_id=:file_id", [':file_id'=>intval($file['id'])]));
    return $DB->exec("INSERT INTO pre_file_version (file_id,version_no,name,type,size,hash,created_by,note,created_at) VALUES (:file_id,:version_no,:name,:type,:size,:hash,:created_by,:note,NOW())", [
        ':file_id'=>intval($file['id']), ':version_no'=>$number, ':name'=>$file['name'], ':type'=>$file['type'], ':size'=>intval($file['size']), ':hash'=>$file['hash'], ':created_by'=>substr((string)$actor, 0, 100), ':note'=>substr(trim((string)$note), 0, 500),
    ]) !== false;
}

function pan_replace_file_object($DB, $stor, $fileId, $tmpFile, $originalName, $actor, $note = '', $conf = []) {
    $fileId = intval($fileId);
    $file = $DB->getRow("SELECT * FROM pre_file WHERE id=:id AND deleted_at IS NULL LIMIT 1", [':id'=>$fileId]);
    if(!$file || !is_uploaded_file($tmpFile)) return ['ok'=>false, 'message'=>'文件不存在或上传无效'];
    $newName = pan_normalize_filename($originalName);
    $newType = get_file_ext($newName);
    $newSize = intval(filesize($tmpFile));
    $newHash = md5_file($tmpFile);
    if($newName === '' || $newHash === false) return ['ok'=>false, 'message'=>'无法读取新文件'];
    $uploadLimit = isset($conf['upload_size']) ? intval($conf['upload_size']) : 0;
    if($uploadLimit > 0 && $newSize > $uploadLimit * 1024 * 1024) return ['ok'=>false, 'message'=>'新文件超过上传大小限制'];
    $blockedTypes = isset($conf['type_block']) ? array_filter(explode('|', $conf['type_block'])) : [];
    if(in_array($newType, $blockedTypes, true)) return ['ok'=>false, 'message'=>'新文件类型不允许上传'];
    $blockedNames = isset($conf['name_block']) ? array_filter(explode('|', $conf['name_block'])) : [];
    foreach($blockedNames as $blockedName) if($blockedName !== '' && strpos($newName, $blockedName) !== false) return ['ok'=>false, 'message'=>'新文件名不允许上传'];
    if(hash_equals((string)$file['hash'], (string)$newHash)) return ['ok'=>true, 'message'=>'新文件与当前版本相同，无需创建快照'];
    $objectAlreadyExists = $stor->exists($newHash);
    if(!$objectAlreadyExists && !$stor->upload($newHash, $tmpFile, minetype($newType))) return ['ok'=>false, 'message'=>'存储上传失败'];
    if(!$DB->beginTransaction()) return ['ok'=>false, 'message'=>'无法开始数据库事务'];
    $ok = pan_create_file_version($DB, $file, $actor, $note);
    if($ok) $ok = $DB->exec("UPDATE pre_file SET name=:name,type=:type,size=:size,hash=:hash,lasttime=NOW() WHERE id=:id", [':name'=>$newName, ':type'=>$newType, ':size'=>$newSize, ':hash'=>$newHash, ':id'=>$fileId]) !== false;
    if($ok) $DB->commit(); else $DB->rollBack();
    if(!$ok){
        if(!$objectAlreadyExists) pan_delete_storage_if_unreferenced($DB, $stor, $newHash);
        return ['ok'=>false, 'message'=>'保存版本快照失败'];
    }
    pan_audit_admin_action($DB, $actor, 'file_replaced', 'file', $fileId, ['previous_hash'=>$file['hash'], 'new_hash'=>$newHash]);
    return ['ok'=>true, 'message'=>'文件已替换，原文件已保存为历史版本'];
}

function pan_restore_file_version($DB, $stor, $fileId, $versionId, $actor) {
    $fileId = intval($fileId);
    $versionId = intval($versionId);
    $file = $DB->getRow("SELECT * FROM pre_file WHERE id=:id AND deleted_at IS NULL LIMIT 1", [':id'=>$fileId]);
    $version = $DB->getRow("SELECT * FROM pre_file_version WHERE id=:id AND file_id=:file_id LIMIT 1", [':id'=>$versionId, ':file_id'=>$fileId]);
    if(!$file || !$version) return false;
    if(!$stor->exists($version['hash'])) return false;
    if(!$DB->beginTransaction()) return false;
    $ok = pan_create_file_version($DB, $file, $actor, '恢复版本 '.$version['version_no'].' 前的自动快照');
    if($ok) $ok = $DB->exec("UPDATE pre_file SET name=:name,type=:type,size=:size,hash=:hash,lasttime=NOW() WHERE id=:id", [':name'=>$version['name'], ':type'=>$version['type'], ':size'=>intval($version['size']), ':hash'=>$version['hash'], ':id'=>$fileId]) !== false;
    if($ok) $DB->commit(); else $DB->rollBack();
    if(!$ok) return false;
    pan_audit_admin_action($DB, $actor, 'file_version_restored', 'file', $fileId, ['version_id'=>$versionId, 'version_no'=>$version['version_no']]);
    return true;
}

function pan_prune_file_versions($DB, $stor, $retentionDays, $maxCount, $actor = 'cron') {
    $retentionDays = max(1, min(3650, intval($retentionDays)));
    $maxCount = max(1, min(1000, intval($maxCount)));
    $removed = 0;
    $expired = $DB->getAll("SELECT id,hash FROM pre_file_version WHERE created_at<DATE_SUB(NOW(),INTERVAL {$retentionDays} DAY) ORDER BY id ASC LIMIT 500");
    foreach((array)$expired as $row){
        if($DB->exec("DELETE FROM pre_file_version WHERE id=:id", [':id'=>intval($row['id'])]) !== false){
            pan_enqueue_storage_cleanup($DB, $row['hash']);
            $removed++;
        }
    }
    $fileIds = $DB->getAll("SELECT file_id FROM pre_file_version GROUP BY file_id HAVING count(*)>:max_count", [':max_count'=>$maxCount]);
    foreach((array)$fileIds as $fileRow){
        $obsolete = $DB->getAll("SELECT id,hash FROM pre_file_version WHERE file_id=:file_id ORDER BY version_no DESC LIMIT {$maxCount},1000", [':file_id'=>intval($fileRow['file_id'])]);
        foreach((array)$obsolete as $row){
            if($DB->exec("DELETE FROM pre_file_version WHERE id=:id", [':id'=>intval($row['id'])]) !== false){
                pan_enqueue_storage_cleanup($DB, $row['hash']);
                $removed++;
            }
        }
    }
    if($removed > 0) pan_audit_admin_action($DB, $actor, 'file_versions_pruned', 'maintenance', '', ['count'=>$removed]);
    return $removed;
}

function pan_run_maintenance($DB, $stor, $conf, $actor = 'cron') {
    $retention = isset($conf['trash_retention_days']) ? intval($conf['trash_retention_days']) : 30;
    $retention = max(1, min(3650, $retention));
    $purged = 0;
    $rows = $DB->getAll("SELECT id FROM pre_file WHERE deleted_at<DATE_SUB(NOW(),INTERVAL {$retention} DAY) ORDER BY deleted_at ASC LIMIT 100");
    foreach((array)$rows as $row) if(pan_purge_file($DB, $stor, intval($row['id']), $actor)) $purged++;
    $versions = pan_prune_file_versions($DB, $stor, isset($conf['file_version_retention_days']) ? $conf['file_version_retention_days'] : 90, isset($conf['file_version_max_count']) ? $conf['file_version_max_count'] : 10, $actor);
    $objects = pan_run_storage_cleanup($DB, $stor, 100);
    return ['purged_files'=>$purged, 'purged_versions'=>$versions, 'purged_objects'=>$objects];
}

function pan_set_health_status($DB, $component, $status, $details = '') {
    return $DB->exec("INSERT INTO pre_system_health (component,status,details,checked_at) VALUES (:component,:status,:details,NOW()) ON DUPLICATE KEY UPDATE status=VALUES(status),details=VALUES(details),checked_at=VALUES(checked_at)", [':component'=>substr((string)$component,0,50), ':status'=>substr((string)$status,0,20), ':details'=>substr((string)$details,0,1000)]);
}

function pan_run_health_check($DB, $stor) {
    $result = [];
    $dbOk = $DB->getColumn('SELECT 1') == 1;
    pan_set_health_status($DB, 'database', $dbOk ? 'ok' : 'error', $dbOk ? '数据库连接正常' : '数据库查询失败');
    $result['database'] = $dbOk;
    $probe = md5('linknest-health-'.microtime(true).'-'.random_int(1, PHP_INT_MAX));
    $tmp = tempnam(sys_get_temp_dir(), 'linknest-health-');
    $storageOk = false;
    if($tmp !== false){
        file_put_contents($tmp, 'LinkNest storage health probe');
        $uploaded = $stor->savefile($probe, $tmp, 'text/plain');
        $exists = $uploaded && $stor->exists($probe);
        $deleted = $uploaded ? $stor->delete($probe) : false;
        if(file_exists($tmp)) @unlink($tmp);
        $storageOk = $uploaded && $exists && $deleted;
    }
    pan_set_health_status($DB, 'storage', $storageOk ? 'ok' : 'error', $storageOk ? '临时写入、存在性检查和删除均成功' : '存储探测失败，请检查后端配置和权限');
    $result['storage'] = $storageOk;
    return $result;
}
