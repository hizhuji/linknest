CREATE TABLE IF NOT EXISTS `pre_tag` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uid` int(11) unsigned NOT NULL,
  `name` varchar(64) NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_tag` (`uid`,`name`),
  KEY `uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `pre_file_tag` (
  `file_id` int(11) unsigned NOT NULL,
  `tag_id` bigint(20) unsigned NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`file_id`,`tag_id`),
  KEY `tag_id` (`tag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `pre_file_favorite` (
  `uid` int(11) unsigned NOT NULL,
  `file_id` int(11) unsigned NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`uid`,`file_id`),
  KEY `file_id` (`file_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `pre_user_quota` (
  `uid` int(11) unsigned NOT NULL,
  `byte_limit` bigint(20) unsigned NOT NULL DEFAULT '0',
  `file_limit` int(11) unsigned NOT NULL DEFAULT '0',
  `daily_upload_limit` bigint(20) unsigned NOT NULL DEFAULT '0',
  `updated_by` varchar(100) DEFAULT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `pre_user_usage` (
  `uid` int(11) unsigned NOT NULL,
  `used_bytes` bigint(20) unsigned NOT NULL DEFAULT '0',
  `file_count` int(11) unsigned NOT NULL DEFAULT '0',
  `daily_upload_bytes` bigint(20) unsigned NOT NULL DEFAULT '0',
  `daily_upload_date` date NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `pre_api_key` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uid` int(11) unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `key_prefix` varchar(32) CHARACTER SET ascii NOT NULL,
  `secret_hash` varchar(255) NOT NULL,
  `scopes` varchar(500) NOT NULL,
  `expires_at` datetime DEFAULT NULL,
  `ip_rules` text DEFAULT NULL,
  `request_limit` int(11) unsigned NOT NULL DEFAULT '0',
  `daily_traffic_limit` bigint(20) unsigned NOT NULL DEFAULT '0',
  `last_used_at` datetime DEFAULT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_prefix` (`key_prefix`),
  KEY `user_active` (`uid`,`revoked_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `pre_api_key_usage` (
  `key_id` bigint(20) unsigned NOT NULL,
  `usage_date` date NOT NULL,
  `requests` int(11) unsigned NOT NULL DEFAULT '0',
  `bytes` bigint(20) unsigned NOT NULL DEFAULT '0',
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`key_id`,`usage_date`),
  KEY `usage_date` (`usage_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `pre_config` (`k`,`v`) VALUES
('user_quota_enforced','0'),
('user_quota_bytes','0'),
('user_quota_files','0'),
('user_daily_upload_bytes','0');
