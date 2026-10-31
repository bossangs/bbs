<?php

!defined('DEBUG') AND exit('Access Denied.');

$action = param(1, 'raffles');

if($action != 'action' && community_guest_board_gate()) message(-1, '请先登录后查看社区内容。');

if($action == 'action') {
	if($method != 'POST') message(-1, '请求方法错误。');
	$community_action = param('community_action');
	community_require_login();
	if(!community_rate_limit('action', 40, 60)) message(-1, '操作过于频繁，请稍后再试。');

	if($community_action == 'reaction') {
		$tid = intval(param('tid', 0));
		$kind = param('kind');
		$thread = thread_read($tid);
		if(empty($thread) || !forum_access_user($thread['fid'], $gid, 'allowread')) message(-1, '主题不存在或无权访问。');
		if(!community_toggle_reaction($tid, $uid, $kind)) message(-1, '互动功能未启用或操作失败。');
		if(intval($thread['uid']) != intval($uid)) community_add_notification($thread['uid'], $uid, $kind, $tid, $thread['firstpid'], '用户对你的主题作出了互动。');
		message(0, '操作成功。');
	} elseif($community_action == 'bookmark') {
		$tid = intval(param('tid', 0));
		$thread = thread_read($tid);
		if(empty($thread) || !forum_access_user($thread['fid'], $gid, 'allowread')) message(-1, '主题不存在或无权访问。');
		$r = community_toggle_bookmark($tid, $uid);
		if($r === FALSE) message(-1, '收藏功能未启用或操作失败。');
		message(0, $r ? '已收藏。' : '已取消收藏。');
	} elseif($community_action == 'follow') {
		$target_uid = intval(param('target_uid', 0));
		if(empty(user_read($target_uid))) message(-1, '用户不存在。');
		$r = community_toggle_follow($uid, $target_uid);
		if($r === FALSE) message(-1, '关注功能未启用或操作失败。');
		if($r) community_add_notification($target_uid, $uid, 'follow', 0, 0, '有会员关注了你。');
		message(0, $r ? '已关注。' : '已取消关注。');
	} elseif($community_action == 'report') {
		if(!community_setting_enabled('enable_reports')) message(-1, '举报功能当前未启用。');
		$tid = intval(param('tid', 0));
		$pid = intval(param('pid', 0));
		$reason = trim(strip_tags(param('reason', '', FALSE)));
		if(!$tid || $reason === '' || xn_strlen($reason) > 400) message(-1, '请填写有效的举报理由。');
		$thread = thread_read($tid);
		if(empty($thread) || !forum_access_user($thread['fid'], $gid, 'allowread')) message(-1, '主题不存在或无权访问。');
		$r = db_insert('community_report', array('reporter_uid'=>$uid, 'tid'=>$tid, 'pid'=>$pid, 'target_uid'=>intval($thread['uid']), 'reason'=>substr($reason, 0, 500), 'status'=>0, 'created_at'=>time(), 'handled_by'=>0, 'handled_at'=>0));
		if($r === FALSE) message(-1, '举报提交失败。');
		community_add_audit($uid, 'report_create', $tid, intval($thread['uid']), $reason);
		message(0, '举报已提交，感谢协助维护社区。');
	} elseif($community_action == 'message') {
		if(!community_setting_enabled('enable_messages')) message(-1, '私信功能当前未启用。');
		$target_uid = intval(param('target_uid', 0));
		$subject = trim(strip_tags(param('subject', '', FALSE)));
		$content = trim(strip_tags(param('content', '', FALSE)));
		if(!$target_uid || $target_uid == $uid || empty(user_read($target_uid))) message(-1, '收件人无效。');
		if($content === '' || xn_strlen($content) > 2000 || xn_strlen($subject) > 160) message(-1, '私信内容为空或过长。');
		$r = db_insert('community_message', array('from_uid'=>$uid, 'to_uid'=>$target_uid, 'subject'=>substr($subject, 0, 160), 'content'=>substr($content, 0, 8000), 'created_at'=>time(), 'read_at'=>0));
		if($r === FALSE) message(-1, '发送失败。');
		community_add_notification($target_uid, $uid, 'message', 0, 0, '你收到一条新的站内私信。');
		message(0, '私信已发送。');
	} elseif($community_action == 'raffle_draw') {
		if(!community_setting_enabled('enable_raffle')) message(-1, '抽奖功能当前未启用。');
		$raffle_id = intval(param('raffle_id', 0));
		if($gid != 1 && !community_setting_enabled('allow_member_raffle_draw')) {
			$denied_raffle = community_raffle_read($raffle_id);
			community_add_audit($uid, 'raffle_draw_denied', $denied_raffle ? intval($denied_raffle['tid']) : 0, 0, 'raffle_id='.$raffle_id.'; reason=member_draw_disabled');
			message(-1, '当前设置仅允许管理员开奖。');
		}
		$winners = community_raffle_draw($raffle_id, $uid);
		if($winners === FALSE) {
			$failed_raffle = community_raffle_read($raffle_id);
			community_add_audit($uid, 'raffle_draw_failed', $failed_raffle ? intval($failed_raffle['tid']) : 0, 0, 'raffle_id='.$raffle_id.'; reason=not_ready_or_not_allowed');
			message(-1, '开奖失败：请确认已到截止时间、存在评论者、活动未开奖且你有权访问文章。');
		}
		message(0, '开奖完成，共有 '.count($winners).' 位中奖者；操作者和结果已记录。');
	} elseif($community_action == 'raffle_enter') {
		message(-1, '无需手动报名：在对应文章下回复即可自动进入抽奖池。');
	}
	message(-1, '未知操作。');
}

if($action == 'rss') {
	if(!community_setting_enabled('enable_rss')) message(-1, 'RSS 功能当前未启用。');
	$threadlist = thread_find_by_fid(0, 1, 30, 'lastpid');
	thread_list_access_filter($threadlist, $gid);
	$scheme = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) != 'off') ? 'https://' : 'http://';
	$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
	$basepath = isset($_SERVER['SCRIPT_NAME']) ? rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') : '';
	$baseurl = $scheme.$host.($basepath ? $basepath.'/' : '/');
	header('Content-Type: application/rss+xml; charset=utf-8');
	echo '<?xml version="1.0" encoding="UTF-8"?>';
	echo '<rss version="2.0"><channel><title>'.htmlspecialchars($conf['sitename'], ENT_QUOTES, 'UTF-8').'</title><link>'.htmlspecialchars($baseurl, ENT_QUOTES, 'UTF-8').'</link><description>'.htmlspecialchars(strip_tags($conf['sitebrief']), ENT_QUOTES, 'UTF-8').'</description>';
	if($threadlist) foreach($threadlist as $thread) {
		$link = $baseurl.url('thread-'.$thread['tid']);
		echo '<item><title>'.htmlspecialchars(strip_tags($thread['subject']), ENT_QUOTES, 'UTF-8').'</title><link>'.htmlspecialchars($link, ENT_QUOTES, 'UTF-8').'</link><guid>'.htmlspecialchars($link, ENT_QUOTES, 'UTF-8').'</guid><pubDate>'.gmdate(DATE_RSS, intval($thread['create_date'])).'</pubDate></item>';
	}
	echo '</channel></rss>';
	exit;
}

$header['title'] = '社区';
$header['mobile_title'] = '社区';
$community_title = '社区';
$threadlist = array();
$rafflelist = array();
$notificationlist = array();
$bookmarklist = array();
$messagelist = array();
$keyword = '';

if($action == 'featured') {
	if(!community_setting_enabled('enable_featured')) message(-1, '精选功能当前未启用。');
	$community_title = '精选 / 发现';
	$rows = db_find('community_featured', array(), array('rank'=>-1, 'created_at'=>-1), 1, 100);
	$tids = $rows ? arrlist_values($rows, 'tid') : array();
	$threadlist = $tids ? thread_find_by_tids($tids, array('tid'=>-1)) : array();
	thread_list_access_filter($threadlist, $gid);
} elseif($action == 'search') {
	if(!community_setting_enabled('enable_search')) message(-1, '搜索功能当前未启用。');
	$community_title = '站内搜索';
	$keyword = trim(param('q', '', FALSE));
	if($keyword !== '' && xn_strlen($keyword) <= 80) {
		$threadlist = thread_find_by_keyword($keyword);
		thread_list_access_filter($threadlist, $gid);
	}
} elseif($action == 'bookmarks') {
	community_require_login();
	if(!community_setting_enabled('enable_bookmarks')) message(-1, '收藏功能当前未启用。');
	$community_title = '我的收藏';
	$rows = db_find('community_bookmark', array('uid'=>$uid), array('created_at'=>-1), 1, 100);
	if($rows) foreach($rows as $row) {
		$thread = thread_read($row['tid']);
		if(!empty($thread) && forum_access_user($thread['fid'], $gid, 'allowread')) $threadlist[$thread['tid']] = $thread;
	}
} elseif($action == 'notifications') {
	community_require_login();
	if(!community_setting_enabled('enable_notifications')) message(-1, '通知功能当前未启用。');
	$community_title = '通知';
	$notificationlist = db_find('community_notification', array('uid'=>$uid), array('nid'=>-1), 1, 100);
	community_mark_notifications_read($uid);
	if(!$notificationlist) $notificationlist = array();
} elseif($action == 'messages') {
	community_require_login();
	if(!community_setting_enabled('enable_messages')) message(-1, '私信功能当前未启用。');
	$community_title = '站内私信';
	$messagelist = db_find('community_message', array('to_uid'=>$uid), array('mid'=>-1), 1, 100);
	if(!$messagelist) $messagelist = array();
	foreach($messagelist as &$message_row) {
		$sender = user_read_cache($message_row['from_uid']);
		$message_row['from_username'] = isset($sender['username']) ? $sender['username'] : '已注销用户';
	}
	unset($message_row);
	$unread = array();
	foreach($messagelist as $message_row) if(empty($message_row['read_at'])) $unread[] = intval($message_row['mid']);
	if($unread) db_update('community_message', array('mid'=>$unread, 'to_uid'=>$uid), array('read_at'=>time()));
	} elseif($action == 'raffles') {
		if(!community_setting_enabled('enable_raffle')) message(-1, '抽奖功能当前未启用。');
		$community_title = '文章评论抽奖';
		$rafflelist = community_raffle_list(NULL, 100);
		$visible_raffles = array();
		foreach($rafflelist as $raffle_item) {
			$raffle_thread = thread_read($raffle_item['tid']);
			if(!empty($raffle_thread) && forum_access_user($raffle_thread['fid'], $gid, 'allowread')) $visible_raffles[] = $raffle_item;
		}
		$rafflelist = $visible_raffles;
} else {
	http_404();
}

include _include(APP_PATH.'view/htm/community.htm');

?>
