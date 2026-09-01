CREATE TABLE IF NOT EXISTS `pre_share` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `file_id` int(11) unsigned NOT NULL,
  `code` varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `expire_at` datetime DEFAULT NULL,
  `max_accesses` int(11) unsigned NOT NULL DEFAULT '0',
  `access_count` int(11) unsigned NOT NULL DEFAULT '0',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `one_time` tinyint(1) NOT NULL DEFAULT '0',
  `created_by_uid` int(11) unsigned NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL,
  `last_access_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `file_id` (`file_id`),
  KEY `owner_status` (`created_by_uid`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `pre_share` (`file_id`,`code`,`password`,`expire_at`,`max_accesses`,`access_count`,`status`,`one_time`,`created_by_uid`,`created_at`,`last_access_at`)
SELECT `id`,`hash`,`pwd`,`expire_at`,`max_downloads`,`count`,1,0,`uid`,`addtime`,`lasttime` FROM `pre_file`;
