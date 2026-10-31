<?php

!defined('DEBUG') AND exit('Access Denied.');

function community_mail_absolute_thread_url($tid) {
	$host = isset($_SERVER['HTTP_HOST']) ? trim((string)$_SERVER['HTTP_HOST']) : '';
	if(!preg_match('/^(?:[A-Za-z0-9.-]+|\[[0-9A-Fa-f:]+\])(?::[0-9]{1,5})?$/D', $host)) return '';
	if(preg_match('/:(\d+)$/', $host, $port_match) && (intval($port_match[1]) < 1 || intval($port_match[1]) > 65535)) return '';
	if(!function_exists('http_url_path')) return '';
	$base = http_url_path();
	$parts = @parse_url($base);
	if(!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) return '';
	if(!in_array(strtolower($parts['scheme']), array('http', 'https'))) return '';
	$host_parts = @parse_url('http://'.$host);
	if(!is_array($host_parts) || empty($host_parts['host'])) return '';
	$header_host = strtolower(trim($host_parts['host'], '[]'));
	$base_host = strtolower(trim($parts['host'], '[]'));
	$header_port = isset($host_parts['port']) ? intval($host_parts['port']) : 0;
	$base_port = isset($parts['port']) ? intval($parts['port']) : 0;
	if($header_host !== $base_host || $header_port !== $base_port) return '';
	$path = url('thread-'.intval($tid));
	return $base.ltrim($path, '/');
}

function community_send_reply_email($thread, $post) {
	if(!community_setting_enabled('enable_comment_email_notifications')) return TRUE;
	if(empty($thread) || empty($post) || !empty($post['isfirst'])) return TRUE;

	$recipient_uid = intval($thread['uid']);
	$sender_uid = intval($post['uid']);
	if($recipient_uid < 1 || $recipient_uid == $sender_uid) return TRUE;

	$recipient = user_read($recipient_uid);
	if(empty($recipient) || empty($recipient['email'])) return TRUE;
	if(!is_email($recipient['email'], $email_error)) return TRUE;

	$fid = intval($thread['fid']);
	$forum = forum_read($fid);
	if(empty($forum) || !forum_access_user($fid, intval($recipient['gid']), 'allowread')) return TRUE;

	$smtp_file = APP_PATH.'conf/smtp.conf.php';
	if(!is_file($smtp_file)) {
		xn_log('Reply email notification skipped: SMTP configuration is missing.', 'send_mail_error');
		return FALSE;
	}
	$smtp_list = include _include($smtp_file);
	if(!is_array($smtp_list)) $smtp_list = array();
	$valid_smtp = array();
	foreach($smtp_list as $smtp_row) {
		if(!is_array($smtp_row)) continue;
		$port = isset($smtp_row['port']) ? intval($smtp_row['port']) : 0;
		if(!empty($smtp_row['host']) && !empty($smtp_row['email']) && $port > 0 && $port < 65536 && is_email($smtp_row['email'], $sender_error)) {
			$valid_smtp[] = $smtp_row;
		}
	}
	if(empty($valid_smtp)) {
		xn_log('Reply email notification skipped: no valid SMTP configuration.', 'send_mail_error');
		return FALSE;
	}

	include_once _include(XIUNOPHP_PATH.'xn_send_mail.func.php');
	$sender = user_read($sender_uid);
	$sender_name = !empty($sender['username']) ? $sender['username'] : '社区会员';
	$site_name = isset($GLOBALS['conf']['sitename']) ? (string)$GLOBALS['conf']['sitename'] : '社区';
	$title = isset($thread['subject']) ? trim(strip_tags((string)$thread['subject'])) : '';
	$title = preg_replace('/[\r\n\x00-\x1f\x7f]+/', ' ', $title);
	if(function_exists('mb_substr')) $title = mb_substr($title, 0, 120, 'UTF-8');
	$subject = '['.preg_replace('/[\r\n\x00-\x1f\x7f]+/', ' ', $site_name).'] 你的主题收到新回复';
	if(function_exists('mb_substr')) $subject = mb_substr($subject, 0, 180, 'UTF-8');

	$raw_message = isset($post['message']) ? (string)$post['message'] : '';
	$snippet = html_entity_decode(strip_tags($raw_message), ENT_QUOTES, 'UTF-8');
	$snippet = preg_replace('/[\r\n\x00-\x1f\x7f]+/u', ' ', $snippet);
	$snippet = trim($snippet);
	if(function_exists('mb_substr')) $snippet = mb_substr($snippet, 0, 500, 'UTF-8');
	else $snippet = substr($snippet, 0, 500);

	$body = '<p>你发起的主题收到了新回复。</p>';
	if($title !== '') $body .= '<p><strong>主题：</strong>'.htmlspecialchars($title, ENT_QUOTES, 'UTF-8').'</p>';
	$body .= '<p><strong>回复人：</strong>'.htmlspecialchars($sender_name, ENT_QUOTES, 'UTF-8').'</p>';
	if($snippet !== '') $body .= '<blockquote>'.nl2br(htmlspecialchars($snippet, ENT_QUOTES, 'UTF-8')).'</blockquote>';
	$link = community_mail_absolute_thread_url(intval($thread['tid']));
	if($link !== '') $body .= '<p><a href="'.htmlspecialchars($link, ENT_QUOTES, 'UTF-8').'">查看主题</a></p>';
	$body .= '<p>此邮件由站点自动发送。</p>';

	$smtp = $valid_smtp[0];
	try {
		$result = xn_send_mail($smtp, $site_name, $recipient['email'], $subject, $body);
	} catch (Throwable $e) {
		$result = FALSE;
		$send_error = $e->getMessage();
	}
	if($result !== TRUE) {
		$error_text = isset($send_error) ? $send_error : (isset($GLOBALS['errstr']) ? (string)$GLOBALS['errstr'] : 'SMTP send failed');
		xn_log('Reply email notification failed: '.substr($error_text, 0, 300), 'send_mail_error');
		return FALSE;
	}
	return TRUE;
}

?>
