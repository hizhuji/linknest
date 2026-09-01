CREATE TABLE IF NOT EXISTS `pre_rate_limit` (
  `bucket` varchar(64) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `attempts` smallint(5) unsigned NOT NULL DEFAULT '0',
  `window_start` int(10) unsigned NOT NULL,
  PRIMARY KEY (`bucket`,`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `pre_config` VALUES ('api_token', '');
INSERT IGNORE INTO `pre_config` VALUES ('api_require_token', '0');
