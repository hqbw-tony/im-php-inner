CREATE TABLE IF NOT EXISTS `yu_third_chat_session` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `platform_id` int(11) NOT NULL DEFAULT '0' COMMENT 'third platform id',
  `customer_user_id` int(11) NOT NULL DEFAULT '0' COMMENT 'customer IM user id',
  `agent_user_id` int(11) NOT NULL DEFAULT '0' COMMENT 'agent IM user id',
  `external_user_id` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'external customer id',
  `external_agent_id` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'external agent id',
  `chat_identify` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'private chat identifier',
  `last_msg_id` int(11) NOT NULL DEFAULT '0' COMMENT 'last message id',
  `last_msg_time` int(11) NOT NULL DEFAULT '0' COMMENT 'last message time',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'status 1 enabled 0 disabled',
  `create_time` int(11) NOT NULL DEFAULT '0',
  `update_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `platform_customer_agent` (`platform_id`,`customer_user_id`,`agent_user_id`),
  KEY `platform_agent_last_msg` (`platform_id`,`agent_user_id`,`last_msg_time`),
  KEY `platform_last_msg` (`platform_id`,`last_msg_time`),
  KEY `chat_identify` (`chat_identify`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='third platform customer agent chat session';

INSERT IGNORE INTO `yu_third_chat_session` (
  `platform_id`,`customer_user_id`,`agent_user_id`,`external_user_id`,`external_agent_id`,
  `chat_identify`,`last_msg_id`,`last_msg_time`,`status`,`create_time`,`update_time`
)
SELECT
  customer_map.`platform_id`, customer_map.`user_id`, agent_map.`user_id`,
  customer_map.`external_user_id`, agent_map.`external_user_id`,
  CONCAT(LEAST(customer_map.`user_id`, agent_map.`user_id`), '-', GREATEST(customer_map.`user_id`, agent_map.`user_id`)),
  0, 0, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
FROM `yu_third_user_map` customer_map
INNER JOIN `yu_user` customer ON customer.`user_id` = customer_map.`user_id`
INNER JOIN `yu_third_user_map` agent_map ON agent_map.`platform_id` = customer_map.`platform_id`
  AND agent_map.`user_id` = customer.`cs_uid`
  AND agent_map.`user_type` = '2'
  AND agent_map.`delete_time` = 0
WHERE customer_map.`user_type` = '1'
  AND customer_map.`delete_time` = 0
  AND customer.`cs_uid` > 0;

UPDATE `yu_third_chat_session` chat_session
INNER JOIN `yu_message` message ON message.`chat_identify` = chat_session.`chat_identify`
  AND message.`is_group` = 0
  AND message.`status` = 1
  AND message.`is_last` = 1
SET chat_session.`last_msg_id` = message.`msg_id`,
    chat_session.`last_msg_time` = message.`create_time`,
    chat_session.`update_time` = UNIX_TIMESTAMP();
