ALTER TABLE `yu_third_platform`
  ADD COLUMN `default_customer_avatar` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '客户默认头像' AFTER `default_cs_uid`,
  ADD COLUMN `default_agent_avatar` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '代理客服默认头像' AFTER `default_customer_avatar`;

ALTER TABLE `yu_user`
  MODIFY COLUMN `avatar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '头像';
