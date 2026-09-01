ALTER TABLE `pre_file`
ADD COLUMN `expire_at` datetime DEFAULT NULL AFTER `count`,
ADD COLUMN `max_downloads` int(11) unsigned NOT NULL DEFAULT '0' AFTER `expire_at`;
