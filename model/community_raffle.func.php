<?php

!defined('DEBUG') AND exit('Access Denied.');

function community_raffle_read($raffle_id) {
	return db_find_one('community_raffle', array('raffle_id'=>intval($raffle_id)));
}

function community_raffle_list($status = NULL, $limit = 50) {
	$cond = array();
	if($status !== NULL) $cond['status'] = intval($status);
	$limit = max(1, min(200, intval($limit)));
	$list = db_find('community_raffle', $cond, array('raffle_id'=>-1), 1, $limit);
	if(!$list) return array();
	foreach($list as &$raffle) {
		$raffle['entry_count'] = community_raffle_entry_count($raffle['raffle_id']);
		$raffle['winner_list'] = intval($raffle['status']) == 1 ? community_raffle_winners($raffle['raffle_id']) : array();
	}
	return $list;
}

function community_raffle_find_by_tid($tid, $limit = 10) {
	$list = db_find('community_raffle', array('tid'=>intval($tid)), array('raffle_id'=>-1), 1, max(1, min(50, intval($limit))));
	if(!$list) return array();
	foreach($list as &$raffle) {
		$raffle['entry_count'] = community_raffle_entry_count($raffle['raffle_id']);
		$raffle['winner_list'] = intval($raffle['status']) == 1 ? community_raffle_winners($raffle['raffle_id']) : array();
	}
	return $list;
}

function community_raffle_entry_count($raffle_id) {
	return intval(db_count('community_raffle_entry', array('raffle_id'=>intval($raffle_id))));
}

function community_raffle_commenter_uids($tid) {
	global $db;
	$tid = intval($tid);
	if(!$tid) return array();
	$table = $db->tablepre.'post';
	$sql = "SELECT uid FROM `{$table}` WHERE tid='{$tid}' AND isfirst='0' AND uid>'0' GROUP BY uid ORDER BY MIN(pid) ASC LIMIT 100000";
	$rows = db_sql_find($sql);
	if(!$rows) return array();
	$uids = array();
	foreach($rows as $row) if(!empty($row['uid'])) $uids[] = intval($row['uid']);
	return array_values(array_unique($uids));
}

function community_raffle_add_commenter($tid, $uid) {
	$tid = intval($tid);
	$uid = intval($uid);
	if(!$tid || !$uid || !community_setting_enabled('enable_raffle')) return FALSE;
	$raffles = db_find('community_raffle', array('tid'=>$tid, 'status'=>0), array('raffle_id'=>1), 1, 50);
	if(!$raffles) return TRUE;
	foreach($raffles as $raffle) {
		$r = db_replace('community_raffle_entry', array('raffle_id'=>intval($raffle['raffle_id']), 'uid'=>$uid, 'created_at'=>time()));
		if($r === FALSE) return FALSE;
	}
	return TRUE;
}

function community_raffle_sync_comments($raffle_id, $tid) {
	$raffle_id = intval($raffle_id);
	$uids = community_raffle_commenter_uids($tid);
	if(!$raffle_id) return FALSE;
	if(db_delete('community_raffle_entry', array('raffle_id'=>$raffle_id)) === FALSE) return FALSE;
	foreach($uids as $uid) {
		if(db_insert('community_raffle_entry', array('raffle_id'=>$raffle_id, 'uid'=>intval($uid), 'created_at'=>time())) === FALSE) return FALSE;
	}
	return count($uids);
}

function community_raffle_create($article_id, $prize, $winner_count, $duration_days, $creator_uid) {
	global $gid;
	$article_id = intval($article_id);
	$creator_uid = intval($creator_uid);
	$prize = trim(strip_tags($prize));
	$winner_count = max(1, min(50, intval($winner_count)));
	$duration_days = max(0, min(365, intval($duration_days)));
	if(intval($gid) != 1 || !$article_id || !$creator_uid || $prize === '') return FALSE;

	$thread = thread_read($article_id);
	$source_pid = 0;
	if(empty($thread)) {
		$post = post_read($article_id);
		if(empty($post)) return FALSE;
		$source_pid = intval($article_id);
		$thread = thread_read($post['tid']);
	}
	if(empty($thread) || !forum_access_user($thread['fid'], $gid, 'allowread')) return FALSE;
	$existing = db_find_one('community_raffle', array('tid'=>intval($thread['tid']), 'status'=>0));
	if($existing) return FALSE;
	try {
		$seed = bin2hex(random_bytes(32));
	} catch (Exception $e) {
		return FALSE;
	}
	$time = time();
	$ends_at = $duration_days > 0 ? $time + $duration_days * 86400 : 0;
	$data = array(
		'tid'=>intval($thread['tid']),
		'source_pid'=>$source_pid ? $source_pid : intval($thread['firstpid']),
		'creator_uid'=>$creator_uid,
		'prize'=>substr($prize, 0, 160),
		'winner_count'=>$winner_count,
		'status'=>0,
		'created_at'=>$time,
		'ends_at'=>$ends_at,
		'drawn_at'=>0,
		'draw_seed'=>$seed,
		'seed_hash'=>hash('sha256', $seed)
	);
	db_exec('START TRANSACTION');
	$raffle_id = db_insert('community_raffle', $data);
	if($raffle_id === FALSE) { db_exec('ROLLBACK'); return FALSE; }
	if(community_raffle_sync_comments($raffle_id, $thread['tid']) === FALSE) { db_exec('ROLLBACK'); return FALSE; }
	if(community_add_audit($creator_uid, 'raffle_create', intval($thread['tid']), 0, 'raffle_id='.$raffle_id.'; article_id='.$article_id.'; seed_hash='.hash('sha256', $seed)) === FALSE) { db_exec('ROLLBACK'); return FALSE; }
	if(db_exec('COMMIT') === FALSE) { db_exec('ROLLBACK'); return FALSE; }
	return intval($raffle_id);
}

function community_raffle_draw($raffle_id, $actor_uid) {
	global $gid;
	$raffle_id = intval($raffle_id);
	$actor_uid = intval($actor_uid);
	if(!$raffle_id || !$actor_uid) return FALSE;

	// InnoDB transaction + conditional state update prevent duplicate concurrent draws and stuck partial results.
	db_exec('START TRANSACTION');
	$locked = db_update('community_raffle', array('raffle_id'=>$raffle_id, 'status'=>0), array('status'=>2));
	if(!$locked) { db_exec('ROLLBACK'); return FALSE; }
	$raffle = community_raffle_read($raffle_id);
	if(empty($raffle)) { db_exec('ROLLBACK'); return FALSE; }
	$thread = thread_read($raffle['tid']);
	if(empty($thread) || !forum_access_user($thread['fid'], $gid, 'allowread')) { db_exec('ROLLBACK'); return FALSE; }
	if(intval($gid) != 1 && (!community_setting_enabled('allow_member_raffle_draw') || intval($raffle['ends_at']) > time())) { db_exec('ROLLBACK'); return FALSE; }
	$seed = (string)$raffle['draw_seed'];
	if($seed === '' || !hash_equals((string)$raffle['seed_hash'], hash('sha256', $seed))) { db_exec('ROLLBACK'); return FALSE; }

	// 以当前主题回复评论账号为准，先重建并冻结 UID 去重后的候选快照。
	$uids = community_raffle_commenter_uids($raffle['tid']);
	if(!$uids) { db_exec('ROLLBACK'); return FALSE; }
	if(db_delete('community_raffle_entry', array('raffle_id'=>$raffle_id)) === FALSE) { db_exec('ROLLBACK'); return FALSE; }
	$entries = array();
	foreach($uids as $uid) {
		if(db_insert('community_raffle_entry', array('raffle_id'=>$raffle_id, 'uid'=>intval($uid), 'created_at'=>time())) === FALSE) { db_exec('ROLLBACK'); return FALSE; }
		$entries[] = array('uid'=>intval($uid));
	}
	usort($entries, function($a, $b) use ($seed, $raffle_id) {
		$ha = hash_hmac('sha256', $raffle_id.':'.intval($a['uid']), $seed);
		$hb = hash_hmac('sha256', $raffle_id.':'.intval($b['uid']), $seed);
		return strcmp($ha, $hb);
	});
	$winner_count = min(count($entries), max(1, intval($raffle['winner_count'])));
	if(db_delete('community_raffle_winner', array('raffle_id'=>$raffle_id)) === FALSE) { db_exec('ROLLBACK'); return FALSE; }
	$drawn_at = time();
	$winner_uids = array();
	for($i=0; $i<$winner_count; $i++) {
		$uid = intval($entries[$i]['uid']);
		$winner_uids[] = $uid;
		$proof = hash('sha256', $raffle_id.':'.$uid.':'.$seed);
		if(db_insert('community_raffle_winner', array('raffle_id'=>$raffle_id, 'uid'=>$uid, 'rank'=>$i+1, 'drawn_at'=>$drawn_at, 'proof'=>$proof)) === FALSE) { db_exec('ROLLBACK'); return FALSE; }
	}
	if(db_update('community_raffle', array('raffle_id'=>$raffle_id, 'status'=>2), array('status'=>1, 'drawn_at'=>$drawn_at)) === FALSE) { db_exec('ROLLBACK'); return FALSE; }
	$audit_reason = 'raffle_id='.$raffle_id.'; commenters='.count($entries).'; winner_uids='.implode(',', $winner_uids).'; seed_hash='.$raffle['seed_hash'];
	if(community_add_audit($actor_uid, 'raffle_draw', intval($raffle['tid']), 0, $audit_reason) === FALSE) { db_exec('ROLLBACK'); return FALSE; }
	if(db_exec('COMMIT') === FALSE) { db_exec('ROLLBACK'); return FALSE; }
	return community_raffle_winners($raffle_id);
}

function community_raffle_winners($raffle_id) {
	$list = db_find('community_raffle_winner', array('raffle_id'=>intval($raffle_id)), array('rank'=>1), 1, 50);
	if(!$list) return array();
	foreach($list as &$winner) {
		$user = user_read_cache($winner['uid']);
		$winner['username'] = isset($user['username']) ? $user['username'] : '已注销用户';
		$winner['avatar_url'] = isset($user['avatar_url']) ? $user['avatar_url'] : '';
	}
	return $list;
}

function community_raffle_can_draw($raffle, $gid) {
	if(empty($raffle) || intval($raffle['status']) != 0) return FALSE;
	if(intval($gid) == 1) return TRUE;
	if(!community_setting_enabled('allow_member_raffle_draw')) return FALSE;
	return empty($raffle['ends_at']) || intval($raffle['ends_at']) <= time();
}

?>
