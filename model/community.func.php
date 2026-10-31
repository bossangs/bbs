<?php

!defined('DEBUG') AND exit('Access Denied.');

function community_default_settings() {
	return array(
		'require_login_for_boards'=>0,
		'enable_search'=>1,
		'enable_featured'=>1,
		'enable_rss'=>1,
		'enable_like'=>1,
		'enable_thanks'=>1,
		'enable_dislike'=>1,
		'enable_bookmarks'=>1,
		'enable_follow'=>1,
		'enable_notifications'=>1,
		'enable_messages'=>1,
		'enable_reports'=>1,
		'enable_raffle'=>1,
		'allow_member_raffle_draw'=>1,
		'raffle_winner_count_default'=>1,
		'raffle_duration_days_default'=>7,
		'feed_page_size'=>20,
		'theme_background'=>'#f4f4f4',
		'theme_surface'=>'#ffffff',
		'theme_navbar'=>'#ffffff',
		'theme_primary'=>'#0b74b7',
		'theme_text'=>'#22252a'
	);
}

function community_settings() {
	$saved = setting_get('community_config');
	if(!is_array($saved)) $saved = array();
	return array_merge(community_default_settings(), $saved);
}

function community_setting($key, $default = NULL) {
	$settings = community_settings();
	return array_key_exists($key, $settings) ? $settings[$key] : $default;
}

function community_setting_enabled($key) {
	return intval(community_setting($key, 0)) === 1;
}

function community_settings_save($input) {
	$defaults = community_default_settings();
	$old = community_settings();
	$boolkeys = array('require_login_for_boards', 'enable_search', 'enable_featured', 'enable_rss', 'enable_like', 'enable_thanks', 'enable_dislike', 'enable_bookmarks', 'enable_follow', 'enable_notifications', 'enable_messages', 'enable_reports', 'enable_raffle', 'allow_member_raffle_draw');
	foreach($boolkeys as $key) $old[$key] = empty($input[$key]) ? 0 : 1;
	$size = isset($input['feed_page_size']) ? intval($input['feed_page_size']) : intval($defaults['feed_page_size']);
	$old['feed_page_size'] = max(5, min(100, $size));
	$winner_count = isset($input['raffle_winner_count_default']) ? intval($input['raffle_winner_count_default']) : intval($defaults['raffle_winner_count_default']);
	$old['raffle_winner_count_default'] = max(1, min(50, $winner_count));
	$duration_days = isset($input['raffle_duration_days_default']) ? intval($input['raffle_duration_days_default']) : intval($defaults['raffle_duration_days_default']);
	$old['raffle_duration_days_default'] = max(0, min(365, $duration_days));
	$colors = array('theme_background', 'theme_surface', 'theme_navbar', 'theme_primary', 'theme_text');
	foreach($colors as $key) {
		$value = isset($input[$key]) ? trim($input[$key]) : $defaults[$key];
		$old[$key] = preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtolower($value) : $defaults[$key];
	}
	return setting_set('community_config', $old);
}

function community_guest_board_gate() {
	global $uid;
	return community_setting_enabled('require_login_for_boards') && empty($uid);
}

function community_require_login($message = '请先登录后继续。') {
	global $uid;
	if(empty($uid)) message(-1, $message);
	return TRUE;
}

function community_csrf_token() {
	if(!isset($_SESSION['community_csrf']) || !is_string($_SESSION['community_csrf']) || strlen($_SESSION['community_csrf']) < 32) {
		try {
			$_SESSION['community_csrf'] = bin2hex(random_bytes(32));
		} catch (Exception $e) {
			$_SESSION['community_csrf'] = sha1(uniqid(mt_rand(), TRUE).mt_rand());
		}
	}
	return $_SESSION['community_csrf'];
}

function community_csrf_field() {
	return '<input type="hidden" name="_community_csrf" value="'.htmlspecialchars(community_csrf_token(), ENT_QUOTES, 'UTF-8').'">';
}

function community_csrf_output_filter($html) {
	if(!is_string($html) || strpos($html, '<form') === FALSE) return $html;
	$field = community_csrf_field();
	return preg_replace_callback('~<form\b[^>]*>.*?</form\s*>~is', function($match) use ($field) {
		$form = $match[0];
		$open_end = strpos($form, '>');
		if($open_end === FALSE) return $form;
		$opening = substr($form, 0, $open_end + 1);
		if(!preg_match('/\bmethod\s*=\s*([\'\"]?)post\\1(?=\s|>)/i', $opening)) return $form;
		if(strpos($form, 'name="_community_csrf"') !== FALSE) return $form;
		return $opening.$field.substr($form, $open_end + 1);
	}, $html);
}

function community_csrf_check($token = NULL) {
	if($token === NULL) $token = param('_community_csrf');
	$expected = community_csrf_token();
	return is_string($token) && hash_equals($expected, $token);
}

function community_rate_limit($bucket, $limit, $window) {
	global $time;
	$key = 'community_rl_'.preg_replace('/[^a-zA-Z0-9_]/', '', $bucket);
	$now = isset($time) ? intval($time) : time();
	$state = isset($_SESSION[$key]) && is_array($_SESSION[$key]) ? $_SESSION[$key] : array('start'=>$now, 'count'=>0);
	if($now - intval($state['start']) >= intval($window)) $state = array('start'=>$now, 'count'=>0);
	$state['count'] = intval($state['count']) + 1;
	$_SESSION[$key] = $state;
	return $state['count'] <= intval($limit);
}

function community_current_user_reaction($tid, $user_id = 0) {
	global $uid;
	$user_id = $user_id ? intval($user_id) : intval($uid);
	if(!$user_id || !$tid) return '';
	$row = db_find_one('community_reaction', array('uid'=>$user_id, 'tid'=>intval($tid)));
	return $row ? $row['kind'] : '';
}

function community_reaction_counts($tid) {
	$counts = array('like'=>0, 'thanks'=>0, 'dislike'=>0);
	foreach(array_keys($counts) as $kind) $counts[$kind] = intval(db_count('community_reaction', array('tid'=>intval($tid), 'kind'=>$kind)));
	return $counts;
}

function community_toggle_reaction($tid, $uid, $kind) {
	$allowed = array('like'=>'enable_like', 'thanks'=>'enable_thanks', 'dislike'=>'enable_dislike');
	if(!isset($allowed[$kind]) || !community_setting_enabled($allowed[$kind])) return FALSE;
	$tid = intval($tid); $uid = intval($uid);
	if(!$tid || !$uid) return FALSE;
	$old = db_find_one('community_reaction', array('uid'=>$uid, 'tid'=>$tid));
	if($old && $old['kind'] == $kind) return db_delete('community_reaction', array('uid'=>$uid, 'tid'=>$tid)) !== FALSE;
	return db_replace('community_reaction', array('uid'=>$uid, 'tid'=>$tid, 'kind'=>$kind, 'created_at'=>time())) !== FALSE;
}

function community_is_bookmarked($tid, $uid) {
	if(!$uid || !$tid || !community_setting_enabled('enable_bookmarks')) return FALSE;
	return (bool)db_find_one('community_bookmark', array('uid'=>intval($uid), 'tid'=>intval($tid)));
}

function community_toggle_bookmark($tid, $uid) {
	$tid = intval($tid); $uid = intval($uid);
	if(!$uid || !$tid || !community_setting_enabled('enable_bookmarks')) return FALSE;
	$row = db_find_one('community_bookmark', array('uid'=>$uid, 'tid'=>$tid));
	if($row) return db_delete('community_bookmark', array('uid'=>$uid, 'tid'=>$tid)) !== FALSE ? 0 : FALSE;
	return db_insert('community_bookmark', array('uid'=>$uid, 'tid'=>$tid, 'created_at'=>time())) !== FALSE ? 1 : FALSE;
}

function community_add_notification($uid, $from_uid, $type, $tid, $pid, $content) {
	$uid = intval($uid); $from_uid = intval($from_uid);
	if(!$uid || $uid == $from_uid || !community_setting_enabled('enable_notifications')) return FALSE;
	return db_insert('community_notification', array('uid'=>$uid, 'from_uid'=>$from_uid, 'type'=>substr($type, 0, 24), 'tid'=>intval($tid), 'pid'=>intval($pid), 'content'=>substr($content, 0, 500), 'created_at'=>time(), 'read_at'=>0));
}

function community_unread_notifications($uid) {
	return $uid ? db_count('community_notification', array('uid'=>intval($uid), 'read_at'=>0)) : 0;
}

function community_mark_notifications_read($uid) {
	if(!$uid) return FALSE;
	return db_update('community_notification', array('uid'=>intval($uid), 'read_at'=>0), array('read_at'=>time()));
}

function community_toggle_follow($uid, $target_uid) {
	$uid = intval($uid); $target_uid = intval($target_uid);
	if(!$uid || !$target_uid || $uid == $target_uid || !community_setting_enabled('enable_follow')) return FALSE;
	$row = db_find_one('community_follow', array('uid'=>$uid, 'target_uid'=>$target_uid));
	if($row) return db_delete('community_follow', array('uid'=>$uid, 'target_uid'=>$target_uid)) !== FALSE ? 0 : FALSE;
	return db_insert('community_follow', array('uid'=>$uid, 'target_uid'=>$target_uid, 'created_at'=>time())) !== FALSE ? 1 : FALSE;
}

function community_following($uid, $target_uid) {
	return $uid && $target_uid ? (bool)db_find_one('community_follow', array('uid'=>intval($uid), 'target_uid'=>intval($target_uid))) : FALSE;
}

function community_add_audit($actor_uid, $action, $tid = 0, $target_uid = 0, $reason = '') {
	return db_insert('community_audit', array('actor_uid'=>intval($actor_uid), 'action'=>substr($action, 0, 32), 'tid'=>intval($tid), 'target_uid'=>intval($target_uid), 'reason'=>substr($reason, 0, 500), 'created_at'=>time()));
}

function community_forum_is_public($forum) {
	if(empty($forum) || empty($forum['accesson'])) return TRUE;
	return !empty($forum['accesslist'][0]['allowread']);
}

function community_forum_set_visibility($fid, $public) {
	global $grouplist;
	$fid = intval($fid);
	$forum = forum_read($fid);
	if(empty($forum)) return FALSE;
	if($public) {
		$r = forum_update($fid, array('accesson'=>0));
	} else {
		$r = forum_update($fid, array('accesson'=>1));
		if($r === FALSE) return FALSE;
		foreach($grouplist as $gid=>$group) {
			$allow = function($name) use ($group) { return isset($group[$name]) ? intval($group[$name]) : 0; };
			$access = array(
				'allowread'=>intval($gid) == 0 ? 0 : $allow('allowread'),
				'allowthread'=>$allow('allowthread'),
				'allowpost'=>$allow('allowpost'),
				'allowattach'=>$allow('allowattach'),
				'allowdown'=>$allow('allowdown')
			);
			forum_access_replace($fid, intval($gid), $access);
		}
	}
	cache_delete('forumlist');
	return $r !== FALSE;
}

function community_color_vars() {
	$settings = community_settings();
	$keys = array('theme_background'=>'#f4f4f4', 'theme_surface'=>'#ffffff', 'theme_navbar'=>'#ffffff', 'theme_primary'=>'#0b74b7', 'theme_text'=>'#22252a');
	foreach($keys as $key=>$default) {
		$value = isset($settings[$key]) ? $settings[$key] : $default;
		$keys[$key] = preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtolower($value) : $default;
	}
	return $keys;
}

?>
