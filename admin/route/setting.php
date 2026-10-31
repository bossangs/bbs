<?php

!defined('DEBUG') AND exit('Access Denied.');

$action = param(1);

include _include(APP_PATH.'model/smtp.func.php');
$smtplist = smtp_init(APP_PATH.'conf/smtp.conf.php');
// hook admin_setting_start.php

if($action == 'base') {
	
	// hook admin_setting_base_get_post.php
	
	if($method == 'GET') {
		
		// hook admin_setting_base_get_start.php
		
		$input = array();
		$input['sitename'] = form_text('sitename', $conf['sitename']);
		$input['sitebrief'] = form_textarea('sitebrief', $conf['sitebrief'], '100%', 100);
		$input['runlevel'] = form_radio('runlevel', array(0=>lang('runlevel_0'), 1=>lang('runlevel_1'), 2=>lang('runlevel_2'), 3=>lang('runlevel_3'), 4=>lang('runlevel_4'), 5=>lang('runlevel_5')), $conf['runlevel']);
		$input['user_create_on'] = form_radio_yes_no('user_create_on', $conf['user_create_on']);
		$input['user_create_email_on'] = form_radio_yes_no('user_create_email_on', $conf['user_create_email_on']);
		$input['user_resetpw_on'] = form_radio_yes_no('user_resetpw_on', $conf['user_resetpw_on']);
		$input['lang'] = form_select('lang', array('zh-cn'=>lang('lang_zh_cn'), 'zh-tw'=>lang('lang_zh_tw'), 'en-us'=>lang('lang_en_us'), 'ru-ru'=>lang('lang_ru_ru'), 'th-th'=>lang('lang_th_th')), $conf['lang']);
		$input['url_rewrite_on'] = form_radio_yes_no('url_rewrite_on', $conf['url_rewrite_on']); 
		$input['pagesize'] = form_text('pagesize', $conf['pagesize'], 100); 
		$input['postlist_pagesize'] = form_text('postlist_pagesize', $conf['postlist_pagesize'], 100); 

		$header['title'] = lang('admin_site_setting');
		$header['mobile_title'] =lang('admin_site_setting');
		
		// hook admin_setting_base_get_end.php
		
		include _include(ADMIN_PATH.'view/htm/setting_base.htm');
		
	} else {
		
		$sitebrief = param('sitebrief', '', FALSE);
		$sitename = param('sitename', '', FALSE);
		$runlevel = param('runlevel', 0);
		$user_create_on = param('user_create_on', 0);
		$user_create_email_on = param('user_create_email_on', 0);
		$user_resetpw_on = param('user_resetpw_on', 0);
		$url_rewrite_on = param('url_rewrite_on', 0); 
		$pagesize = param('pagesize', 0); 
		$postlist_pagesize = param('postlist_pagesize', 0);  		
		$_lang = param('lang');
		
		// hook admin_setting_base_post_start.php
		
		$replace = array();
		$replace['sitename'] = $sitename;
		$replace['sitebrief'] = $sitebrief;
		$replace['runlevel'] = $runlevel;
		$replace['user_create_on'] = $user_create_on;
		$replace['user_create_email_on'] = $user_create_email_on;
		$replace['user_resetpw_on'] = $user_resetpw_on;
		$replace['lang'] = $_lang;
		$replace['url_rewrite_on'] = $url_rewrite_on;
		$replace['pagesize'] = $pagesize;
		$replace['postlist_pagesize'] = $postlist_pagesize; 		
		file_replace_var(APP_PATH.'conf/conf.php', $replace);
	
		// hook admin_setting_base_post_end.php
		
		message(0, lang('modify_successfully'));
	}

} elseif($action == 'smtp') {

	// hook admin_setting_smtp_get_post.php
	
	if($method == 'GET') {
		
		// hook admin_setting_smtp_get_start.php
		
		$header['title'] = lang('admin_setting_smtp');
		$header['mobile_title'] = lang('admin_setting_smtp');
	
		$smtplist = smtp_find();
		$maxid = smtp_maxid();
		
		// hook admin_setting_smtp_get_end.php
		
		include _include(ADMIN_PATH."view/htm/setting_smtp.htm");
	
		} else {
			
			// hook admin_setting_smtp_post_start.php
			if(param('smtp_test', 0)) {
				$test_email = trim((string)param('smtp_test_email', ''));
				$smtp_id = intval(param('smtp_id', 0));
				if(!is_email($test_email, $err)) message('smtp_test_email', $err);
				$smtp = smtp_read($smtp_id);
				if(empty($smtp['host']) || empty($smtp['email']) || empty($smtp['port'])) message(-1, '所选 SMTP 配置不完整，请先保存发件服务。');
				include_once _include(XIUNOPHP_PATH.'xn_send_mail.func.php');
				$test_subject = $conf['sitename'].' SMTP 测试';
				$r = xn_send_mail($smtp, $conf['sitename'], $test_email, $test_subject, '这是由站点管理员主动发起的 SMTP 连通性测试邮件。');
				if($r === TRUE) message(0, '测试邮件已交给所选 SMTP 服务器；请检查目标收件箱。');
				$test_error = !empty($errstr) ? $errstr : 'SMTP 测试失败，请核对主机、端口、加密方式和认证信息。';
				xn_log($test_error, 'send_mail_error');
				message(-1, $test_error);
			}
			
			$email = param('email', array(''));
			$host = param('host', array(''));
			$port = param('port', array(0));
			$secure = param('secure', array(''));
			$user = param('user', array(''));
		$pass = param('pass', array(''));
		
		$smtplist = array();
		foreach ($email as $k=>$v) {
			$smtplist[$k] = array(
				'email'=>$email[$k],
					'host'=>$host[$k],
					'port'=>$port[$k],
					'secure'=>isset($secure[$k]) && in_array(strtolower($secure[$k]), array('ssl', 'tls')) ? strtolower($secure[$k]) : '',
				'user'=>$user[$k],
				'pass'=>$pass[$k],
			);
		}
		$r = file_put_contents_try(APP_PATH.'conf/smtp.conf.php', "<?php\r\nreturn ".var_export($smtplist,true).";\r\n?>");
		!$r AND message(-1, lang('conf/smtp.conf.php', array('file'=>'conf/smtp.conf.php')));
		
		// hook admin_setting_smtp_post_end.php
		
		message(0, lang('save_successfully'));
	}
}

// hook admin_setting_end.php

?>
