<?php

function pan_normalize_tag_name($name) {
    $name = trim(preg_replace('/\s+/u', ' ', (string)$name));
    if(function_exists('mb_substr')) $name = mb_substr($name, 0, 32, 'UTF-8');
    else $name = substr($name, 0, 32);
    return $name;
}

function pan_user_owns_file($DB, $fileId, $uid) {
    $fileId = intval($fileId);
    $uid = intval($uid);
    if($fileId < 1 || $uid < 1) return false;
    return intval($DB->getColumn("SELECT count(*) FROM pre_file f WHERE f.id=:file_id AND (f.uid=:uid OR EXISTS (SELECT 1 FROM pre_share s WHERE s.file_id=f.id AND s.created_by_uid=:share_uid))", [':file_id'=>$fileId, ':uid'=>$uid, ':share_uid'=>$uid])) > 0;
}

function pan_user_tags($DB, $uid) {
    return $DB->getAll("SELECT t.id,t.name,COUNT(ft.file_id) AS file_count FROM pre_tag t LEFT JOIN pre_file_tag ft ON ft.tag_id=t.id WHERE t.uid=:uid GROUP BY t.id,t.name ORDER BY t.name ASC", [':uid'=>intval($uid)]);
}

function pan_file_tags($DB, $fileId, $uid) {
    return $DB->getAll("SELECT t.id,t.name FROM pre_file_tag ft INNER JOIN pre_tag t ON t.id=ft.tag_id WHERE ft.file_id=:file_id AND t.uid=:uid ORDER BY t.name ASC", [':file_id'=>intval($fileId), ':uid'=>intval($uid)]);
}

function pan_set_file_tags($DB, $fileId, $uid, $names) {
    if(!pan_user_owns_file($DB, $fileId, $uid)) return false;
    $names = is_array($names) ? $names : explode(',', (string)$names);
    $clean = [];
    foreach($names as $name){
        $name = pan_normalize_tag_name($name);
        $key = function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
        if($name !== '') $clean[$key] = $name;
        if(count($clean) >= 12) break;
    }
    if(!$DB->beginTransaction()) return false;
    $ok = $DB->exec("DELETE FROM pre_file_tag WHERE file_id=:file_id AND tag_id IN (SELECT id FROM pre_tag WHERE uid=:uid)", [':file_id'=>intval($fileId), ':uid'=>intval($uid)]) !== false;
    foreach($clean as $name){
        if(!$ok) break;
        $tag = $DB->getRow("SELECT id FROM pre_tag WHERE uid=:uid AND name=:name LIMIT 1", [':uid'=>intval($uid), ':name'=>$name]);
        if(!$tag){
            $ok = $DB->exec("INSERT INTO pre_tag (uid,name,created_at) VALUES (:uid,:name,NOW())", [':uid'=>intval($uid), ':name'=>$name]) !== false;
            $tagId = intval($DB->lastInsertId());
        }else $tagId = intval($tag['id']);
        if($ok) $ok = $DB->exec("INSERT INTO pre_file_tag (file_id,tag_id,created_at) VALUES (:file_id,:tag_id,NOW())", [':file_id'=>intval($fileId), ':tag_id'=>$tagId]) !== false;
    }
    if($ok) $DB->commit(); else $DB->rollBack();
    return $ok;
}

function pan_toggle_file_favorite($DB, $fileId, $uid, $favorite) {
    if(!pan_user_owns_file($DB, $fileId, $uid)) return false;
    if($favorite) return $DB->exec("INSERT IGNORE INTO pre_file_favorite (uid,file_id,created_at) VALUES (:uid,:file_id,NOW())", [':uid'=>intval($uid), ':file_id'=>intval($fileId)]) !== false;
    return $DB->exec("DELETE FROM pre_file_favorite WHERE uid=:uid AND file_id=:file_id", [':uid'=>intval($uid), ':file_id'=>intval($fileId)]) !== false;
}
