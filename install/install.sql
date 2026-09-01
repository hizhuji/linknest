DROP TABLE IF EXISTS `pre_config`;
create table `pre_config` (
  `k` varchar(32) NOT NULL,
  `v` text NULL,
  PRIMARY KEY  (`k`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `pre_config` VALUES ('version', '1007');
INSERT INTO `pre_config` VALUES ('admin_user', 'admin');
INSERT INTO `pre_config` VALUES ('admin_pwd', '');
INSERT INTO `pre_config` VALUES ('blackip', '');
INSERT INTO `pre_config` VALUES ('title', 'LinkNest 外链云盘');
INSERT INTO `pre_config` VALUES ('site_url', '');
INSERT INTO `pre_config` VALUES ('keywords', '外链网盘,免费外链,免费图床,图片外链');
INSERT INTO `pre_config` VALUES ('description', 'LinkNest 提供文件外链、分享与网盘挂载服务');
INSERT INTO `pre_config` VALUES ('ip_type', '2');
INSERT INTO `pre_config` VALUES ('trusted_proxy_ips', '');
INSERT INTO `pre_config` VALUES ('access_log_retention_days', '30');
INSERT INTO `pre_config` VALUES ('filesearch', '1');
INSERT INTO `pre_config` VALUES ('storage', 'local');
INSERT INTO `pre_config` VALUES ('filepath', '');
INSERT INTO `pre_config` VALUES ('webdav_endpoint', '');
INSERT INTO `pre_config` VALUES ('webdav_username', '');
INSERT INTO `pre_config` VALUES ('webdav_password', '');
INSERT INTO `pre_config` VALUES ('webdav_root', '');
INSERT INTO `pre_config` VALUES ('webdav_public_url', '');
INSERT INTO `pre_config` VALUES ('aliyun_ak', '');
INSERT INTO `pre_config` VALUES ('aliyun_sk', '');
INSERT INTO `pre_config` VALUES ('name_block', '');
INSERT INTO `pre_config` VALUES ('type_block', '');
INSERT INTO `pre_config` VALUES ('type_image', 'png|jpg|jpeg|gif|bmp|webp|ico|svg|svgz|tif|tiff|heic|exif');
INSERT INTO `pre_config` VALUES ('type_audio', 'mp3|wav|ogg|m4a|flac|aac');
INSERT INTO `pre_config` VALUES ('type_video', 'mp4|webm|flv|f4v|mov|3gp|3gpp|avi|mpg|mpeg|wmv|mkv|ts|dat|asf|mts|m2ts|m3u8|m4v');
INSERT INTO `pre_config` VALUES ('green_check', '0');
INSERT INTO `pre_config` VALUES ('green_check_region', 'cn-beijing');
INSERT INTO `pre_config` VALUES ('green_check_porn', '0');
INSERT INTO `pre_config` VALUES ('green_check_terrorism', '0');
INSERT INTO `pre_config` VALUES ('green_label_porn', 'sexy,porn');
INSERT INTO `pre_config` VALUES ('green_label_terrorism', 'bloody,explosion,outfit,logo,weapon,politics');
INSERT INTO `pre_config` VALUES ('gg_file', '网站所有文件内容均由用户自行上传分享，本站严格遵守国家相关法律法规，尊重著作权、版权等第三方权利，如果当前文件侵犯了您的相关权利，请邮件反馈至@qq.com，我们将及时处理。');
INSERT INTO `pre_config` VALUES ('api_open', '0');
INSERT INTO `pre_config` VALUES ('api_token', '');
INSERT INTO `pre_config` VALUES ('api_require_token', '1');
INSERT INTO `pre_config` VALUES ('login_google', '0');
INSERT INTO `pre_config` VALUES ('google_client_id', '');
INSERT INTO `pre_config` VALUES ('google_client_secret', '');
INSERT INTO `pre_config` VALUES ('login_apple', '0');
INSERT INTO `pre_config` VALUES ('apple_client_id', '');
INSERT INTO `pre_config` VALUES ('apple_team_id', '');
INSERT INTO `pre_config` VALUES ('apple_key_id', '');
INSERT INTO `pre_config` VALUES ('apple_private_key', '');

DROP TABLE IF EXISTS `pre_file`;
CREATE TABLE `pre_file` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `type` varchar(50) DEFAULT NULL,
  `size` int(11) unsigned NOT NULL,
  `hash` varchar(32) NOT NULL,
  `addtime` datetime NOT NULL,
  `lasttime` datetime DEFAULT NULL,
  `ip` varchar(15) NOT NULL,
  `hide` int(1) NOT NULL DEFAULT '0',
  `pwd` varchar(255) DEFAULT NULL,
  `block` int(1) NOT NULL DEFAULT '0',
  `count` int(11) unsigned NOT NULL DEFAULT '0',
  `expire_at` datetime DEFAULT NULL,
  `max_downloads` int(11) unsigned NOT NULL DEFAULT '0',
  `uid` int(11) unsigned NOT NULL DEFAULT '0',
   PRIMARY KEY (`id`),
   KEY `hash` (`hash`),
   KEY `uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `pre_user`;
CREATE TABLE `pre_user` (
  `uid` int(11) NOT NULL AUTO_INCREMENT,
  `type` varchar(20) NOT NULL,
  `openid` varchar(150) NOT NULL,
  `nickname` varchar(255) NOT NULL,
  `faceimg` varchar(255) DEFAULT NULL,
  `enable` tinyint(1) NOT NULL DEFAULT '1',
  `regip` varchar(20) DEFAULT NULL,
  `loginip` varchar(20) DEFAULT NULL,
  `level` tinyint(4) NOT NULL DEFAULT '0',
  `addtime` datetime NOT NULL,
  `lasttime` datetime NOT NULL,
  PRIMARY KEY (`uid`),
  KEY `openid` (`openid`,`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1000;

CREATE TABLE `pre_rate_limit` (
  `bucket` varchar(64) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `attempts` smallint(5) unsigned NOT NULL DEFAULT '0',
  `window_start` int(10) unsigned NOT NULL,
  PRIMARY KEY (`bucket`,`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `pre_share` (
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

CREATE TABLE `pre_access_log` (
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

CREATE TABLE `pre_access_daily` (
  `share_id` bigint(20) unsigned NOT NULL,
  `access_date` date NOT NULL,
  `requests` int(11) unsigned NOT NULL DEFAULT '0',
  `downloads` int(11) unsigned NOT NULL DEFAULT '0',
  `previews` int(11) unsigned NOT NULL DEFAULT '0',
  `bytes` bigint(20) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`share_id`,`access_date`),
  KEY `access_date` (`access_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
