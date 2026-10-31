<?php

define('DEBUG', 1);
$method = 'POST';
$uid = 17;
$captured_create = NULL;
$captured_revoke = NULL;

class CommunityInviteTestMessage extends Exception {
	public $message_code;
	public function __construct($code, $message) {
		$this->message_code = $code;
		parent::__construct($message);
	}
}
function param($key, $default = NULL) { return $default; }
function community_invite_create($created_by, $max_uses, $days, $note) {
	global $captured_create;
	$captured_create = array($created_by, $max_uses, $days, $note);
	return array('id'=>42, 'code'=>'ABCD1234-ABCD1234-ABCD1234-ABCD1234', 'max_uses'=>intval($max_uses), 'expires_at'=>0);
}
function community_invite_revoke($id) {
	global $captured_revoke;
	$captured_revoke = intval($id);
	return TRUE;
}
function message($code, $text) { throw new CommunityInviteTestMessage($code, $text); }
function assert_true($condition, $message) {
	if(!$condition) {
		fwrite(STDERR, "FAIL: $message\n");
		exit(1);
	}
}

$_POST = array('community_action'=>'invite_create', 'invite_max_uses'=>'5', 'invite_expiry_days'=>'30', 'invite_note'=>'community launch');
try {
	include dirname(__FILE__).'/../admin/route/community.php';
} catch (CommunityInviteTestMessage $result) {
	assert_true(intval($result->message_code) === 0, 'invite creation returns success');
	assert_true(strpos($result->getMessage(), 'ABCD1234-ABCD1234-ABCD1234-ABCD1234') !== FALSE, 'new plaintext is shown in one-time success notice');
}
assert_true($captured_create === array(17, 5, 30, 'community launch'), 'creation fields reach the invite model');

$_POST = array('community_action'=>'invite_revoke', 'invite_id'=>'42');
try {
	include dirname(__FILE__).'/../admin/route/community.php';
} catch (CommunityInviteTestMessage $result) {
	assert_true(intval($result->message_code) === 0, 'invite revocation returns success');
}
assert_true($captured_revoke === 42, 'revoke id reaches the invite model');

$template = file_get_contents(dirname(__FILE__).'/../admin/view/htm/community.htm');
assert_true(strpos($template, '生成邀请码') !== FALSE, 'admin page contains generation control');
assert_true(strpos($template, 'invite_max_uses') !== FALSE && strpos($template, 'invite_expiry_days') !== FALSE, 'admin page exposes use and expiry limits');
assert_true(strpos($template, 'invite_revoke') !== FALSE, 'admin page contains revoke action');
assert_true(strpos($template, 'require_invite_code') !== FALSE, 'admin page can enable required invitations');

echo "invite admin action tests: PASS\n";
?>
