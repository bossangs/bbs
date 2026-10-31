<?php

define('DEBUG', 1);
$_SERVER['db'] = (object)array('tablepre'=>'bbs_');
$invite_rows = array();
$next_invite_id = 1;

function db_insert($table, $row) {
	global $invite_rows, $next_invite_id;
	if($table !== 'community_invite') return FALSE;
	$row['id'] = $next_invite_id++;
	$invite_rows[$row['code_hash']] = $row;
	return $row['id'];
}
function db_find_one($table, $cond) {
	global $invite_rows;
	if($table !== 'community_invite' || empty($cond['code_hash'])) return FALSE;
	return isset($invite_rows[$cond['code_hash']]) ? $invite_rows[$cond['code_hash']] : FALSE;
}
function db_exec($sql) {
	global $invite_rows;
	if(!preg_match("/code_hash`='([a-f0-9]{64})'/", $sql, $matches)) return FALSE;
	$hash = $matches[1];
	if(!isset($invite_rows[$hash])) return 0;
	$row =& $invite_rows[$hash];
	if(strpos($sql, 'SET `used_count`=`used_count`+1') !== FALSE) {
		if(intval($row['revoked_at']) > 0 || (intval($row['expires_at']) > 0 && intval($row['expires_at']) <= time()) || intval($row['used_count']) >= intval($row['max_uses'])) return 0;
		$row['used_count']++;
		return 1;
	}
	if(strpos($sql, 'SET `used_count`=`used_count`-1') !== FALSE) {
		if(intval($row['used_count']) < 1) return 0;
		$row['used_count']--;
		return 1;
	}
	return FALSE;
}
function db_update($table, $cond, $update) {
	global $invite_rows;
	if($table !== 'community_invite') return FALSE;
	foreach($invite_rows as &$row) {
		if(intval($row['id']) === intval($cond['id']) && intval($row['revoked_at']) === intval($cond['revoked_at'])) {
			foreach($update as $key=>$value) $row[$key] = $value;
			return 1;
		}
	}
	return 0;
}

require dirname(__FILE__).'/../model/community_invite.func.php';

function assert_true($condition, $message) {
	if(!$condition) {
		fwrite(STDERR, "FAIL: $message\n");
		exit(1);
	}
}

$raw = '0123456789ABCDEF0123456789ABCDEF';
$expected_hash = hash('sha256', $raw);
assert_true(community_invite_code_hash('01234567-89abcdef-01234567-89abcdef') === $expected_hash, 'normalized code hash');
assert_true(community_invite_code_hash('not-an-invite') === '', 'malformed code rejected');

$created = community_invite_create(7, 3, 0, 'test invite');
assert_true(is_array($created) && intval($created['id']) > 0, 'invite creation succeeds');
assert_true(preg_match('/^[A-F0-9]{8}(?:-[A-F0-9]{8}){3}$/D', $created['code']) === 1, 'invite format is copyable');
$created_hash = community_invite_code_hash($created['code']);
assert_true(isset($invite_rows[$created_hash]), 'hashed invite row is stored');
assert_true(strpos(json_encode($invite_rows[$created_hash]), str_replace('-', '', $created['code'])) === FALSE, 'plaintext code is not stored');
assert_true(community_invite_is_valid($created['code']), 'new invite is valid');
assert_true(community_invite_consume($created_hash) === TRUE, 'first use is consumed');
assert_true(community_invite_consume($created_hash) === TRUE, 'second use is consumed');
assert_true(community_invite_consume($created_hash) === TRUE, 'last allowed use is consumed');
assert_true(community_invite_consume($created_hash) === FALSE, 'use limit is enforced');
assert_true(community_invite_hash_available($created_hash) === FALSE, 'exhausted invite is unavailable');
assert_true(community_invite_release($created_hash) === TRUE, 'failed registration can release a reserved use');
assert_true(community_invite_hash_available($created_hash) === TRUE, 'released use restores availability');
assert_true(community_invite_revoke($created['id']) === TRUE, 'invite can be revoked');
assert_true(community_invite_hash_available($created_hash) === FALSE, 'revoked invite is unavailable');

$bounded = community_invite_create(7, 5000, -5, str_repeat('a', 130));
assert_true(is_array($bounded) && intval($invite_rows[community_invite_code_hash($bounded['code'])]['max_uses']) === 1000, 'maximum use count is bounded');
assert_true(intval($invite_rows[community_invite_code_hash($bounded['code'])]['expires_at']) === 0, 'negative expiry becomes no expiry');
assert_true(strlen($invite_rows[community_invite_code_hash($bounded['code'])]['note']) <= 120, 'note length is bounded');

echo "invite helper tests: PASS\n";
?>
