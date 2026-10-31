<?php

!defined('DEBUG') AND exit('Access Denied.');

function community_invite_code_hash($code) {
	$code = strtoupper(trim((string)$code));
	$code = str_replace(array('-', ' ', "\t", "\r", "\n"), '', $code);
	if(!preg_match('/^[0-9A-F]{32}$/D', $code)) return '';
	return hash('sha256', $code);
}

function community_invite_hash_available($code_hash) {
	if(!is_string($code_hash) || !preg_match('/^[a-f0-9]{64}$/D', $code_hash)) return FALSE;
	$row = db_find_one('community_invite', array('code_hash'=>$code_hash));
	if(empty($row)) return FALSE;
	if(intval($row['revoked_at']) > 0 || intval($row['used_count']) >= intval($row['max_uses'])) return FALSE;
	if(intval($row['expires_at']) > 0 && intval($row['expires_at']) <= time()) return FALSE;
	return TRUE;
}

function community_invite_is_valid($code) {
	$code_hash = community_invite_code_hash($code);
	return $code_hash !== '' && community_invite_hash_available($code_hash);
}

function community_invite_create($created_by, $max_uses = 1, $expires_in_days = 0, $note = '') {
	$max_uses = max(1, min(1000, intval($max_uses)));
	$expires_in_days = max(0, min(3650, intval($expires_in_days)));
	$expires_at = $expires_in_days ? time() + ($expires_in_days * 86400) : 0;
	$note = trim(strip_tags((string)$note));
	if(function_exists('mb_substr')) $note = mb_substr($note, 0, 120, 'UTF-8');
	else $note = substr($note, 0, 120);

	for($attempt = 0; $attempt < 3; $attempt++) {
		try {
			$raw = strtoupper(bin2hex(random_bytes(16)));
		} catch (Exception $e) {
			return FALSE;
		}
		$code_hash = hash('sha256', $raw);
		$id = db_insert('community_invite', array(
			'code_hash'=>$code_hash,
			'code_prefix'=>substr($raw, 0, 8),
			'max_uses'=>$max_uses,
			'used_count'=>0,
			'expires_at'=>$expires_at,
			'created_by'=>intval($created_by),
			'created_at'=>time(),
			'revoked_at'=>0,
			'note'=>$note
		));
		if($id !== FALSE && intval($id) > 0) {
			$pretty = substr($raw, 0, 8).'-'.substr($raw, 8, 8).'-'.substr($raw, 16, 8).'-'.substr($raw, 24, 8);
			return array('id'=>intval($id), 'code'=>$pretty, 'max_uses'=>$max_uses, 'expires_at'=>$expires_at);
		}
	}
	return FALSE;
}

function community_invite_consume($code_hash) {
	if(!is_string($code_hash) || !preg_match('/^[a-f0-9]{64}$/D', $code_hash)) return FALSE;
	$db = isset($_SERVER['db']) ? $_SERVER['db'] : NULL;
	if(empty($db) || empty($db->tablepre)) return FALSE;
	$now = time();
	$sql = "UPDATE `{$db->tablepre}community_invite` SET `used_count`=`used_count`+1 WHERE `code_hash`='$code_hash' AND `revoked_at`=0 AND (`expires_at`=0 OR `expires_at`>$now) AND `used_count`<`max_uses`";
	return db_exec($sql) === 1;
}

function community_invite_release($code_hash) {
	if(!is_string($code_hash) || !preg_match('/^[a-f0-9]{64}$/D', $code_hash)) return FALSE;
	$db = isset($_SERVER['db']) ? $_SERVER['db'] : NULL;
	if(empty($db) || empty($db->tablepre)) return FALSE;
	$sql = "UPDATE `{$db->tablepre}community_invite` SET `used_count`=`used_count`-1 WHERE `code_hash`='$code_hash' AND `used_count`>0";
	return db_exec($sql) === 1;
}

function community_invite_revoke($id) {
	$id = intval($id);
	if($id < 1) return FALSE;
	return db_update('community_invite', array('id'=>$id, 'revoked_at'=>0), array('revoked_at'=>time())) !== FALSE;
}

function community_invite_find($limit = 100) {
	$limit = max(1, min(500, intval($limit)));
	return db_find('community_invite', array(), array('id'=>-1), 1, $limit);
}

?>
