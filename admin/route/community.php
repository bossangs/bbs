<?php

!defined('DEBUG') AND exit('Access Denied.');

$action = param(1, 'settings');
if($method == 'POST') {
	$community_action = isset($_POST['community_action']) ? trim($_POST['community_action']) : 'save_settings';
	if($community_action == 'save_settings') {
		if(community_settings_save($_POST) === FALSE) message(-1, '社区设置保存失败。');
		$visibility = isset($_POST['community_visibility']) && is_array($_POST['community_visibility']) ? $_POST['community_visibility'] : array();
		foreach($visibility as $fid=>$public) {
			$fid = intval($fid);
			if(!isset($forumlist[$fid])) continue;
			community_forum_set_visibility($fid, intval($public) === 1);
		}
		cache_delete('forumlist');
		message(0, '社区设置已保存。');
	} elseif($community_action == 'raffle_create') {
		if(!community_setting_enabled('enable_raffle')) message(-1, '请先在社区设置中启用抽奖功能。');
		$article_id = isset($_POST['article_id']) ? intval($_POST['article_id']) : 0;
		$prize = isset($_POST['prize']) ? trim($_POST['prize']) : '';
		$winner_count = isset($_POST['winner_count']) ? intval($_POST['winner_count']) : intval(community_setting('raffle_winner_count_default', 1));
		$duration_days = isset($_POST['duration_days']) ? intval($_POST['duration_days']) : intval(community_setting('raffle_duration_days_default', 7));
		$raffle_id = community_raffle_create($article_id, $prize, $winner_count, $duration_days, $uid);
		if(!$raffle_id) {
			$attempt_thread = thread_read($article_id);
			if(empty($attempt_thread)) { $attempt_post = post_read($article_id); if(!empty($attempt_post)) $attempt_thread = thread_read($attempt_post['tid']); }
			community_add_audit($uid, 'raffle_create_failed', !empty($attempt_thread['tid']) ? intval($attempt_thread['tid']) : 0, 0, 'article_id='.$article_id.'; reason=invalid_or_active_raffle');
			message(-1, '创建失败：请确认文章/主题/回复 ID 有效，且该主题没有进行中的抽奖。');
		}
		message(0, '抽奖活动已创建，活动编号：'.$raffle_id.'。候选人将从该主题回复评论中自动去重。');
	} elseif($community_action == 'raffle_draw') {
		$raffle_id = isset($_POST['raffle_id']) ? intval($_POST['raffle_id']) : 0;
		$winners = community_raffle_draw($raffle_id, $uid);
		if($winners === FALSE) {
			$failed_raffle = community_raffle_read($raffle_id);
			community_add_audit($uid, 'raffle_draw_failed', $failed_raffle ? intval($failed_raffle['tid']) : 0, 0, 'raffle_id='.$raffle_id.'; reason=not_ready_or_system_error');
			message(-1, '开奖失败：可能尚未到截止时间、没有评论者、活动已开奖，或候选池暂不可用。');
		}
		message(0, '开奖完成，共有 '.count($winners).' 位中奖者；操作已记录。');
	}
	message(-1, '未知的社区操作。');
}

$settings = community_settings();
$forumlist = forum_list_cache();
$rafflelist = community_raffle_list(NULL, 50);
	$auditlist = db_find('community_audit', array('action'=>array('raffle_create', 'raffle_draw', 'raffle_create_failed', 'raffle_draw_failed', 'raffle_draw_denied', 'raffle_entry_sync_failed')), array('id'=>-1), 1, 50);
if(!$auditlist) $auditlist = array();
$header['title'] = '社区设置与管理';
$header['mobile_title'] = '社区管理';
include _include(ADMIN_PATH.'view/htm/community.htm');

?>
