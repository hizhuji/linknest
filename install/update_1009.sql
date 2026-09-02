ALTER TABLE `pre_file`
ADD COLUMN `deleted_at` datetime DEFAULT NULL AFTER `uid`,
ADD COLUMN `deleted_by` varchar(100) DEFAULT NULL AFTER `deleted_at`,
ADD COLUMN `deletion_reason` varchar(500) DEFAULT NULL AFTER `deleted_by`,
ADD KEY `deleted_at` (`deleted_at`);

CREATE TABLE IF NOT EXISTS `pre_file_version` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `file_id` int(11) unsigned NOT NULL,
  `version_no` int(11) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` varchar(50) DEFAULT NULL,
  `size` int(11) unsigned NOT NULL,
  `hash` varchar(32) NOT NULL,
  `created_by` varchar(100) DEFAULT NULL,
  `note` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `file_version` (`file_id`,`version_no`),
  KEY `hash` (`hash`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `pre_admin_audit` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `actor` varchar(100) NOT NULL,
  `action` varchar(80) NOT NULL,
  `resource_type` varchar(40) DEFAULT NULL,
  `resource_id` varchar(100) DEFAULT NULL,
  `ip_hash` char(64) CHARACTER SET ascii NOT NULL,
  `ip_masked` varchar(64) NOT NULL,
  `context` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `action_time` (`action`,`created_at`),
  KEY `resource_time` (`resource_type`,`resource_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `pre_system_health` (
  `component` varchar(50) NOT NULL,
  `status` varchar(20) NOT NULL,
  `details` varchar(1000) DEFAULT NULL,
  `checked_at` datetime NOT NULL,
  PRIMARY KEY (`component`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `pre_storage_cleanup` (
  `hash` varchar(32) NOT NULL,
  `attempts` int(11) unsigned NOT NULL DEFAULT '0',
  `last_error` varchar(1000) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `last_attempt_at` datetime DEFAULT NULL,
  PRIMARY KEY (`hash`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `pre_config` (`k`,`v`) VALUES
('trash_retention_days','30'),
('file_version_retention_days','90'),
('file_version_max_count','10'),
('share_password_limit','5'),
('share_password_window','900'),
('backup_database_at',''),
('backup_files_at',''),
('backup_restore_drill_at',''),
('backup_note','');
