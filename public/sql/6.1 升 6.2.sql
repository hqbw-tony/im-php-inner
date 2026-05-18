CREATE TABLE `yu_chat_delog` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT '0' COMMENT '用户id',
  `to_user` varchar(32) DEFAULT NULL COMMENT '删除对象',
  `is_group` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否群聊',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态',
  `delete_time` int(11) NOT NULL DEFAULT '0' COMMENT '删除时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='会话删除表';

--
-- 转储表的索引
--

--
-- 表的索引 `yu_chat_delog`
--
ALTER TABLE `yu_chat_delog`
  ADD PRIMARY KEY (`id`);

--
-- 在导出的表使用AUTO_INCREMENT
--

--
-- 使用表AUTO_INCREMENT `yu_chat_delog`
--
ALTER TABLE `yu_chat_delog`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `yu_message` ADD INDEX( `from_user`, `to_user`, `is_group`, `is_read`);

ALTER TABLE `yu_message` ADD INDEX( `from_user`, `to_user`, `is_group`, `is_last`);

ALTER TABLE `yu_message` ADD INDEX( `from_user`, `is_group`, `is_last`);

ALTER TABLE `yu_message` ADD INDEX( `to_user`, `is_group`, `is_last`);

ALTER TABLE `yu_message` ADD INDEX( `chat_identify`);

ALTER TABLE `yu_message` ADD INDEX( `chat_identify`, `is_last`);

ALTER TABLE `yu_message` ADD INDEX( `chat_identify`, `status`);

ALTER TABLE `yu_group_user` ADD INDEX( `group_id`, `status`);

ALTER TABLE `yu_group_user` ADD INDEX( `group_id`, `user_id`);

ALTER TABLE `yu_group_user` ADD INDEX( `group_id`, `user_id`, `status`);

ALTER TABLE `yu_friend` ADD INDEX( `friend_user_id`, `create_user`);

ALTER TABLE `yu_friend` ADD INDEX( `friend_user_id`, `create_user`, `status`);

ALTER TABLE `yu_friend` ADD INDEX( `create_user`, `status`);

ALTER TABLE `yu_friend` ADD INDEX( `create_user`);

ALTER TABLE `yu_chat_delog` ADD INDEX( `user_id`, `is_group`);
COMMIT;