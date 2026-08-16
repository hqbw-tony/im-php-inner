ALTER TABLE `yu_group`
  ADD COLUMN `avatar_mode` tinyint(1) NOT NULL DEFAULT '0' COMMENT '头像模式:0自动拼图,1自定义头像' AFTER `avatar`;
