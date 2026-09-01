ALTER TABLE `pre_share`
ADD COLUMN `referer_mode` tinyint(1) NOT NULL DEFAULT '0' AFTER `one_time`,
ADD COLUMN `referer_rules` text DEFAULT NULL AFTER `referer_mode`,
ADD COLUMN `allow_empty_referer` tinyint(1) NOT NULL DEFAULT '1' AFTER `referer_rules`,
ADD COLUMN `ua_blocklist` text DEFAULT NULL AFTER `allow_empty_referer`,
ADD COLUMN `request_limit` int(11) unsigned NOT NULL DEFAULT '0' AFTER `ua_blocklist`,
ADD COLUMN `daily_traffic_limit` bigint(20) unsigned NOT NULL DEFAULT '0' AFTER `request_limit`,
ADD COLUMN `monthly_traffic_limit` bigint(20) unsigned NOT NULL DEFAULT '0' AFTER `daily_traffic_limit`,
ADD COLUMN `webhook_url` varchar(1000) DEFAULT NULL AFTER `monthly_traffic_limit`;

CREATE TABLE IF NOT EXISTS `pre_share_rate` (
  `share_id` bigint(20) unsigned NOT NULL,
  `ip_hash` char(64) CHARACTER SET ascii NOT NULL,
  `window_start` int(10) unsigned NOT NULL,
  `requests` int(11) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`share_id`,`ip_hash`),
  KEY `window_start` (`window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `pre_alert_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `share_id` bigint(20) unsigned NOT NULL,
  `alert_type` varchar(40) CHARACTER SET ascii NOT NULL,
  `details` varchar(1000) DEFAULT NULL,
  `notified` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `share_alert_time` (`share_id`,`alert_type`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
