CREATE TABLE IF NOT EXISTS `yu_third_platform` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '平台名称',
  `app_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '平台AppID',
  `app_secret` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '平台密钥',
  `default_cs_uid` int(11) NOT NULL DEFAULT '0' COMMENT '默认客服用户ID',
  `welcome` text COLLATE utf8mb4_unicode_ci COMMENT '平台欢迎语',
  `code_ttl` int(11) NOT NULL DEFAULT '120' COMMENT '登录短码有效期',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态 1启用 0禁用',
  `remark` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '备注',
  `extra` json DEFAULT NULL COMMENT '扩展配置',
  `create_time` int(11) NOT NULL DEFAULT '0',
  `update_time` int(11) NOT NULL DEFAULT '0',
  `delete_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `app_id` (`app_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='三方业务平台表';

CREATE TABLE IF NOT EXISTS `yu_third_user_map` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `platform_id` int(11) NOT NULL DEFAULT '0' COMMENT '平台ID',
  `external_user_id` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '三方用户ID',
  `user_id` int(11) NOT NULL DEFAULT '0' COMMENT 'IM用户ID',
  `nickname` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '三方昵称快照',
  `avatar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '三方头像快照',
  `user_type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user' COMMENT '用户分类',
  `tags` json DEFAULT NULL COMMENT '用户标签',
  `extra` json DEFAULT NULL COMMENT '扩展信息',
  `last_login_time` int(11) NOT NULL DEFAULT '0',
  `create_time` int(11) NOT NULL DEFAULT '0',
  `update_time` int(11) NOT NULL DEFAULT '0',
  `delete_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `platform_external_user` (`platform_id`, `external_user_id`, `user_type`),
  UNIQUE KEY `platform_user` (`platform_id`, `user_id`),
  KEY `user_id` (`user_id`),
  KEY `user_type` (`user_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='三方用户映射表';
