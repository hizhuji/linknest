CREATE TABLE IF NOT EXISTS `pre_access_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `share_id` bigint(20) unsigned NOT NULL,
  `file_id` int(11) unsigned NOT NULL,
  `event` varchar(20) CHARACTER SET ascii NOT NULL,
  `bytes` bigint(20) unsigned NOT NULL DEFAULT '0',
  `ip_hash` char(64) CHARACTER SET ascii NOT NULL,
  `ip_masked` varchar(64) NOT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `referer` varchar(1000) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `share_time` (`share_id`,`created_at`),
  KEY `file_time` (`file_id`,`created_at`),
  KEY `ip_time` (`ip_hash`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `pre_access_daily` (
  `share_id` bigint(20) unsigned NOT NULL,
  `access_date` date NOT NULL,
  `requests` int(11) unsigned NOT NULL DEFAULT '0',
  `downloads` int(11) unsigned NOT NULL DEFAULT '0',
  `previews` int(11) unsigned NOT NULL DEFAULT '0',
  `bytes` bigint(20) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`share_id`,`access_date`),
  KEY `access_date` (`access_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `pre_config` (`k`,`v`) VALUES ('access_log_retention_days','30');
