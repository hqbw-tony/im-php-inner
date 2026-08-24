ALTER TABLE `yu_user`
ADD `friend_direct_add` TINYINT(1) NOT NULL DEFAULT '0' COMMENT '发起添加好友时是否免验证' AFTER `friend_limit`;

UPDATE `yu_config`
SET `value` = JSON_SET(`value`, '$.friendAddMode', 1)
WHERE `name` = 'chatInfo' AND JSON_EXTRACT(`value`, '$.friendAddMode') IS NULL;
