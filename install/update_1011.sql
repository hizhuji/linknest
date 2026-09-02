ALTER TABLE `pre_user_usage`
ADD COLUMN `reconciled_at` datetime DEFAULT NULL AFTER `updated_at`;

ALTER TABLE `pre_api_key_usage`
ADD COLUMN `denied_requests` int(11) unsigned NOT NULL DEFAULT '0' AFTER `bytes`,
ADD COLUMN `last_denied_reason` varchar(40) DEFAULT NULL AFTER `denied_requests`,
ADD COLUMN `last_denied_at` datetime DEFAULT NULL AFTER `last_denied_reason`;

UPDATE `pre_user_usage` SET `reconciled_at`=NOW() WHERE `reconciled_at` IS NULL;

INSERT IGNORE INTO `pre_config` (`k`,`v`) VALUES
('api_key_usage_retention_days','180');
