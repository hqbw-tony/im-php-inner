ALTER TABLE `yu_friend`
  ADD COLUMN `apply_time` int(11) NOT NULL DEFAULT '0' COMMENT '申请时间' AFTER `create_time`;

UPDATE `yu_friend`
SET `apply_time` = `create_time`
WHERE `is_invite` = 1 AND `apply_time` = 0;
