-- bk.chuhuo.top 社区扩展迁移；备份数据库后执行。不会删除或覆盖已有帖子、用户或其他配置；仅将游客组设为可读公开板块、不可发帖回复，并创建邀请码表。
-- 若板块 ID 已被占用，INSERT IGNORE 会保留现有记录，管理员可在后台重命名/排序。
INSERT IGNORE INTO `bbs_forum` (`fid`,`name`,`rank`,`brief`,`accesson`) VALUES
(2,'日常',90,'社区日常讨论',0),
(3,'情报',80,'行业动态与资讯',0),
(4,'测评',70,'产品体验与评测',0),
(5,'交易',60,'合规的闲置交易与服务信息',0),
(6,'拼车',50,'出行与资源互助',0),
(7,'推广',40,'项目与作品推广',0),
(8,'生活',30,'生活经验分享',0),
(9,'Dev',20,'开发者交流',0),
(10,'贴图',10,'图片与作品展示',0),
(11,'曝光',9,'基于事实的风险提示与曝光',0),
(12,'无意义',8,'轻松闲聊',0),
(13,'沙盒',7,'测试与实验内容',0);

UPDATE `bbs_group` SET `allowread`=1, `allowthread`=0, `allowpost`=0 WHERE `gid`=0;

CREATE TABLE IF NOT EXISTS `bbs_community_reaction` (
  `uid` int(11) unsigned NOT NULL, `tid` int(11) unsigned NOT NULL,
  `kind` varchar(16) NOT NULL DEFAULT 'like', `created_at` int(11) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`uid`,`tid`), KEY `tid_kind` (`tid`,`kind`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
CREATE TABLE IF NOT EXISTS `bbs_community_bookmark` (
  `uid` int(11) unsigned NOT NULL, `tid` int(11) unsigned NOT NULL, `created_at` int(11) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`uid`,`tid`), KEY `tid` (`tid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
CREATE TABLE IF NOT EXISTS `bbs_community_notification` (
  `nid` int(11) unsigned NOT NULL AUTO_INCREMENT, `uid` int(11) unsigned NOT NULL, `from_uid` int(11) unsigned NOT NULL DEFAULT '0',
  `type` varchar(24) NOT NULL DEFAULT '', `tid` int(11) unsigned NOT NULL DEFAULT '0', `pid` int(11) unsigned NOT NULL DEFAULT '0',
  `content` varchar(500) NOT NULL DEFAULT '', `created_at` int(11) unsigned NOT NULL DEFAULT '0', `read_at` int(11) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`nid`), KEY `uid_read` (`uid`,`read_at`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
CREATE TABLE IF NOT EXISTS `bbs_community_follow` (
  `uid` int(11) unsigned NOT NULL, `target_uid` int(11) unsigned NOT NULL, `created_at` int(11) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`uid`,`target_uid`), KEY `target_uid` (`target_uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
CREATE TABLE IF NOT EXISTS `bbs_community_audit` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT, `actor_uid` int(11) unsigned NOT NULL DEFAULT '0', `action` varchar(32) NOT NULL DEFAULT '',
  `tid` int(11) unsigned NOT NULL DEFAULT '0', `target_uid` int(11) unsigned NOT NULL DEFAULT '0', `reason` varchar(500) NOT NULL DEFAULT '',
  `created_at` int(11) unsigned NOT NULL DEFAULT '0', PRIMARY KEY (`id`), KEY `action_date` (`action`,`created_at`), KEY `tid` (`tid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
CREATE TABLE IF NOT EXISTS `bbs_community_report` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT, `reporter_uid` int(11) unsigned NOT NULL, `tid` int(11) unsigned NOT NULL DEFAULT '0',
  `pid` int(11) unsigned NOT NULL DEFAULT '0', `target_uid` int(11) unsigned NOT NULL DEFAULT '0', `reason` varchar(500) NOT NULL DEFAULT '',
  `status` tinyint(1) unsigned NOT NULL DEFAULT '0', `created_at` int(11) unsigned NOT NULL DEFAULT '0',
  `handled_by` int(11) unsigned NOT NULL DEFAULT '0', `handled_at` int(11) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`), KEY `status_date` (`status`,`created_at`), KEY `tid` (`tid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
CREATE TABLE IF NOT EXISTS `bbs_community_message` (
  `mid` int(11) unsigned NOT NULL AUTO_INCREMENT, `from_uid` int(11) unsigned NOT NULL, `to_uid` int(11) unsigned NOT NULL,
  `subject` varchar(160) NOT NULL DEFAULT '', `content` text NOT NULL, `created_at` int(11) unsigned NOT NULL DEFAULT '0', `read_at` int(11) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`mid`), KEY `to_read` (`to_uid`,`read_at`,`created_at`), KEY `from_date` (`from_uid`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
CREATE TABLE IF NOT EXISTS `bbs_community_featured` (
  `tid` int(11) unsigned NOT NULL, `rank` int(11) NOT NULL DEFAULT '0', `created_at` int(11) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`tid`), KEY `rank` (`rank`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
CREATE TABLE IF NOT EXISTS `bbs_community_login_attempt` (
  `ip_hash` char(64) NOT NULL, `window_start` int(11) unsigned NOT NULL DEFAULT '0', `count` int(11) unsigned NOT NULL DEFAULT '0',
  `blocked_until` int(11) unsigned NOT NULL DEFAULT '0', PRIMARY KEY (`ip_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
CREATE TABLE IF NOT EXISTS `bbs_community_raffle` (
  `raffle_id` int(11) unsigned NOT NULL AUTO_INCREMENT, `tid` int(11) unsigned NOT NULL, `source_pid` int(11) unsigned NOT NULL DEFAULT '0',
  `creator_uid` int(11) unsigned NOT NULL, `prize` varchar(160) NOT NULL DEFAULT '', `winner_count` smallint(5) unsigned NOT NULL DEFAULT '1',
  `status` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '0 open,1 drawn,2 drawing,3 cancelled', `created_at` int(11) unsigned NOT NULL DEFAULT '0',
  `ends_at` int(11) unsigned NOT NULL DEFAULT '0', `drawn_at` int(11) unsigned NOT NULL DEFAULT '0', `draw_seed` char(64) NOT NULL DEFAULT '', `seed_hash` char(64) NOT NULL DEFAULT '',
  PRIMARY KEY (`raffle_id`), KEY `tid_status` (`tid`,`status`), KEY `status_date` (`status`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
CREATE TABLE IF NOT EXISTS `bbs_community_raffle_entry` (
  `raffle_id` int(11) unsigned NOT NULL, `uid` int(11) unsigned NOT NULL, `created_at` int(11) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`raffle_id`,`uid`), KEY `created_at` (`raffle_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
CREATE TABLE IF NOT EXISTS `bbs_community_raffle_winner` (
  `raffle_id` int(11) unsigned NOT NULL, `uid` int(11) unsigned NOT NULL, `rank` smallint(5) unsigned NOT NULL DEFAULT '1', `drawn_at` int(11) unsigned NOT NULL DEFAULT '0',
  `proof` char(64) NOT NULL DEFAULT '', PRIMARY KEY (`raffle_id`,`uid`), UNIQUE KEY `raffle_rank` (`raffle_id`,`rank`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
CREATE TABLE IF NOT EXISTS `bbs_community_invite` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT, `code_hash` char(64) NOT NULL, `code_prefix` char(8) NOT NULL DEFAULT '',
  `max_uses` int(11) unsigned NOT NULL DEFAULT '1', `used_count` int(11) unsigned NOT NULL DEFAULT '0',
  `expires_at` int(11) unsigned NOT NULL DEFAULT '0', `created_by` int(11) unsigned NOT NULL DEFAULT '0',
  `created_at` int(11) unsigned NOT NULL DEFAULT '0', `revoked_at` int(11) unsigned NOT NULL DEFAULT '0',
  `note` varchar(120) NOT NULL DEFAULT '', PRIMARY KEY (`id`), UNIQUE KEY `code_hash` (`code_hash`),
  KEY `status_expiry` (`revoked_at`,`expires_at`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
