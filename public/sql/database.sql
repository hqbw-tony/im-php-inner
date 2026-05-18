
--
-- 数据库： `im`
--

-- --------------------------------------------------------

--
-- 表的结构 `yu_config`
--

CREATE TABLE `yu_config` (
  `id` int(11) NOT NULL,
  `name` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `value` json DEFAULT NULL,
  `create_user` int(11) NOT NULL DEFAULT '0',
  `update_time` int(11) NOT NULL DEFAULT '0',
  `create_time` int(11) NOT NULL DEFAULT '0',
  `remark` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='配置表';

--
-- 转存表中的数据 `yu_config`
--

INSERT INTO `yu_config` (`id`, `name`, `value`, `create_user`, `update_time`, `create_time`, `remark`, `status`) VALUES
(1, 'sysInfo', '{\"logo\": \"\", \"name\": \"Raingad-IM\", \"state\": \"1\", \"regauth\": \"0\", \"regtype\": \"2\", \"runMode\": \"1\", \"ipregion\": \"1\", \"closeTips\": \"系统升级维护中，请稍候再试！\", \"description\": \"一款基于vue2.0的即时通信系统\", \"registerInterval\": \"600\"}', 0, 1688462862, 1688462862, NULL, 1),
(2, 'chatInfo', '{\"stun\": \"\", \"online\": \"1\", \"webrtc\": \"0\", \"dbDelMsg\": \"1\", \"msgClear\": \"1\", \"redoTime\": \"120\", \"stunPass\": \"\", \"stunUser\": \"\", \"groupChat\": \"1\", \"simpleChat\": \"1\", \"autoAddUser\": {\"status\": \"0\", \"welcome\": \"你好啊，欢迎来到Raingad-IM\", \"user_ids\": [\"1\", \"2\", \"3\"], \"user_items\": [\"1\", \"2\", \"3\"]}, \"msgClearDay\": \"30\", \"autoAddGroup\": {\"name\": \"春游交流\", \"status\": \"0\", \"userMax\": \"100\", \"owner_uid\": \"1\", \"owner_info\": [{\"id\": \"1\", \"avatar\": \"", \"user_id\": \"1\", \"realname\": \"管理员\"}]}, \"groupUserMax\": \"0\", \"sendInterval\": \"0\"}', 0, 1688463300, 1688463300, NULL, 1),
(3, 'smtp', '{\"addr\": \"xiekunyu@sss.com\", \"host\": \"smtp.exmail.qq.com\", \"pass\": \"ssss\", \"port\": \"465\", \"sign\": \"Raingad-IM\", \"security\": \"ssl\"}', 0, 1688464072, 1688464072, NULL, 1),
(4, 'fileUpload', '{\"disk\": \"local\", \"size\": \"50\", \"qiniu\": {\"url\": \"\", \"bucket\": \"\", \"accessKey\": \"\", \"secretKey\": \"\"}, \"aliyun\": {\"url\": \"\", \"bucket\": \"\", \"accessId\": \"\", \"endpoint\": \"\", \"accessSecret\": \"\"}, \"qcloud\": {\"cdn\": \"\", \"appId\": \"\", \"bucket\": \"\", \"region\": \"\", \"secretId\": \"\", \"secretKey\": \"\"}, \"fileExt\": [\"jpg\", \"jpeg\", \"ico\", \"webp\", \"bmp\", \"gif\", \"pdf\", \"mp3\", \"wav\", \"wmv\", \"amr\", \"mp4\", \"3gp\", \"avi\", \"m2v\", \"mkv\", \"mov\", \"ppt\", \"pptx\", \"doc\", \"docx\", \"xls\", \"xlsx\", \"txt\", \"md\", \"hevc\", \"png\", \"KLKV\"], \"preview\": \"\"}', 0, 1688464130, 1688464130, NULL, 1),
(5, 'compass', '{\"list\": [], \"mode\": 1, \"status\": 0}', 0, 1688464130, 1688464130, NULL, 1);

-- --------------------------------------------------------

--
-- 表的结构 `yu_file`
--

CREATE TABLE `yu_file` (
  `file_id` int(11) NOT NULL,
  `cate` tinyint(1) NOT NULL DEFAULT '9' COMMENT '文件分类',
  `file_type` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '文件类型',
  `parent_id` int(11) NOT NULL DEFAULT '0' COMMENT '父Id',
  `name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '名称',
  `src` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '链接',
  `size` int(11) DEFAULT '0' COMMENT '大小',
  `ext` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '文件后缀',
  `md5` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'md5',
  `user_id` int(11) NOT NULL DEFAULT '0' COMMENT '创建人',
  `create_time` int(11) NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_time` int(11) NOT NULL DEFAULT '0' COMMENT '更新时间',
  `delete_time` int(11) NOT NULL DEFAULT '0' COMMENT '删除时间',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态',
  `is_lock` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否锁定'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='文件库';

-- --------------------------------------------------------

--
-- 表的结构 `yu_friend`
--

CREATE TABLE `yu_friend` (
  `friend_id` int(11) NOT NULL,
  `friend_user_id` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '好友ID',
  `nickname` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '好友备注',
  `is_invite` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否为邀请方',
  `is_top` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否置顶',
  `is_notice` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否消息提醒',
  `create_user` int(11) NOT NULL DEFAULT '0',
  `update_time` int(11) NOT NULL DEFAULT '0',
  `create_time` int(11) NOT NULL DEFAULT '0',
  `delete_time` int(11) NOT NULL DEFAULT '0',
  `remark` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '申请备注',
  `status` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='联系人置顶表';

-- --------------------------------------------------------

--
-- 表的结构 `yu_group`
--

CREATE TABLE `yu_group` (
  `group_id` int(11) NOT NULL,
  `name` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '团队名称',
  `name_py` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '团队的拼音',
  `avatar` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '群聊头像',
  `level` tinyint(1) NOT NULL DEFAULT '1' COMMENT '等级',
  `create_user` int(11) NOT NULL DEFAULT '0' COMMENT '创建人',
  `create_time` int(11) NOT NULL DEFAULT '0' COMMENT '创建时间',
  `owner_id` int(11) NOT NULL DEFAULT '0' COMMENT '拥有者',
  `is_public` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否公开',
  `notice` mediumtext COLLATE utf8mb4_unicode_ci COMMENT '公告',
  `setting` mediumtext COLLATE utf8mb4_unicode_ci COMMENT '设置',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态',
  `delete_time` int(11) NOT NULL DEFAULT '0' COMMENT '删除时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- 表的结构 `yu_group_user`
--

CREATE TABLE `yu_group_user` (
  `id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL DEFAULT '0' COMMENT '团队Id',
  `user_id` int(11) NOT NULL DEFAULT '0' COMMENT '用户Id',
  `role` tinyint(1) NOT NULL DEFAULT '2' COMMENT '角色 1拥有者，2管理员，3成员',
  `invite_id` int(11) NOT NULL DEFAULT '0' COMMENT '邀请人',
  `create_time` int(11) NOT NULL DEFAULT '0' COMMENT '创建时间',
  `unread` int(11) NOT NULL DEFAULT '0' COMMENT '群未读消息',
  `is_notice` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1是否提醒',
  `is_top` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否置顶',
  `no_speak_time` int(11) NOT NULL DEFAULT '0' COMMENT '禁言到期时间',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态 0 ，未同意邀请，1，同意',
  `delete_time` int(11) NOT NULL DEFAULT '0' COMMENT '删除时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- 表的结构 `yu_message`
--

CREATE TABLE `yu_message` (
  `msg_id` int(11) NOT NULL,
  `id` varchar(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '消息id',
  `from_user` int(11) NOT NULL DEFAULT '0' COMMENT '发送者',
  `to_user` int(11) NOT NULL DEFAULT '0' COMMENT '接受收者',
  `content` text COLLATE utf8mb4_unicode_ci COMMENT '消息内容，如果为文件或图片就是url',
  `chat_identify` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '标识 ：a与b聊天，b与a聊天。记录 a-b',
  `type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'text' COMMENT '消息类型：text、file、image...',
  `is_group` tinyint(1) NOT NULL DEFAULT '0' COMMENT '群聊消息',
  `is_read` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否阅读',
  `is_last` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否是最后一条消息',
  `create_time` int(13) NOT NULL DEFAULT '0' COMMENT '发送时间',
  `is_undo` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否撤回',
  `at` text COLLATE utf8mb4_unicode_ci COMMENT '提醒某人',
  `pid` int(11) DEFAULT '0' COMMENT '引用id',
  `file_id` int(11) NOT NULL DEFAULT '0' COMMENT '文件id',
  `file_cate` tinyint(1) NOT NULL DEFAULT '0' COMMENT '文件类型',
  `file_size` int(11) NOT NULL DEFAULT '0' COMMENT '文件大小',
  `file_name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '文件名称',
  `extends` json DEFAULT NULL COMMENT '消息扩展内容',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态',
  `del_user` text COLLATE utf8mb4_unicode_ci COMMENT '已删除成员'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------


--
-- 表的结构 `yu_emoji`
--

CREATE TABLE `yu_emoji` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT '0' COMMENT '用户id，0为系统',
  `type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '类型',
  `name` varchar(255) DEFAULT NULL COMMENT '名称',
  `src` varchar(255) DEFAULT NULL COMMENT '链接',
  `file_id` INT(11) NOT NULL DEFAULT '0' COMMENT '文件id',
  `create_time` int(11) NOT NULL DEFAULT '0',
  `update_time` int(11) NOT NULL DEFAULT '0',
  `delete_time` int(11) NOT NULL DEFAULT '0',
  `status` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='表情表';

--
-- 表的结构 `yu_user`
--

CREATE TABLE `yu_user` (
  `user_id` int(11) NOT NULL,
  `account` char(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `realname` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` char(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `salt` varchar(4) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '加密盐',
  `avatar` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '头像',
  `email` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '电子邮箱',
  `sex` tinyint(1) NOT NULL DEFAULT '2' COMMENT '性别，0女，1男，2未知',
  `role` tinyint(1) NOT NULL DEFAULT '0' COMMENT '角色，0无角色，1超管，2普管',
  `motto` text COLLATE utf8mb4_unicode_ci COMMENT '个性签名',
  `remark` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '备注',
  `name_py` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '名字的拼音',
  `cs_uid` int(11) NOT NULL DEFAULT '0' COMMENT '客服ID',
  `setting` json DEFAULT NULL COMMENT '用户设置',
  `friend_limit` int(11) NOT NULL DEFAULT '0' COMMENT '好友上限',
  `group_limit` int(11) NOT NULL DEFAULT '0' COMMENT '群聊上限',
  `create_time` int(11) UNSIGNED NOT NULL COMMENT '创建时间',
  `update_time` int(11) UNSIGNED DEFAULT NULL,
  `login_count` mediumint(8) UNSIGNED DEFAULT '0' COMMENT '登录次数',
  `is_auth` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否认证',
  `last_login_time` int(11) UNSIGNED DEFAULT '0' COMMENT '最后登录时间',
  `last_login_ip` char(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '最后登录Ip\n',
  `register_ip` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '注册IP',
  `delete_time` int(11) UNSIGNED NOT NULL DEFAULT '0',
  `status` tinyint(1) UNSIGNED DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=COMPACT;


--
-- 转存表中的数据 `yu_user`
--

INSERT INTO `yu_user` (`user_id`, `account`, `realname`, `password`, `salt`, `avatar`, `email`, `sex`, `role`, `motto`, `remark`, `name_py`, `cs_uid`, `setting`, `friend_limit`, `group_limit`, `create_time`, `update_time`, `login_count`, `is_auth`, `last_login_time`, `last_login_ip`, `register_ip`, `delete_time`, `status`) VALUES
(1, 'administrator', '管理员', '2cb4ecb7fd5295685e275edc7d44e02e', 'srww', NULL, 'service@kais.com', 1, 1, NULL, '', 'guanliyuan', 0, '{\"theme\": \"default\", \"isVoice\": \"true\", \"sendKey\": \"1\", \"avatarCricle\": \"false\", \"hideMessageName\": \"false\", \"hideMessageTime\": \"false\"}', 0, 0, 1222907803, 1702625051, 300, 0, 1730704229, '171.212.121.209', NULL, 0, 1),
(2, '13800000002', '熊大', '2cb4ecb7fd5295685e275edc7d44e02e', 'srww', NULL, 'lllll@bchn', 2, 0, '我是测试', '', 'xiongda', 0, '{\"theme\": \"default\", \"isVoice\": \"true\", \"sendKey\": \"1\", \"avatarCricle\": \"true\", \"hideMessageName\": \"true\", \"hideMessageTime\": \"true\"}', 0, 0, 1555341865, 1730171777, 14886, 0, 1730704870, '125.80.141.99', NULL, 0, 1),
(3, '13800000003', '熊二', '2cb4ecb7fd5295685e275edc7d44e02e', 'srww', NULL, '12345@qq.com', 0, 0, '', '', 'xionger', 0, '{\"theme\": \"blue\", \"isVoice\": \"true\", \"sendKey\": \"1\", \"avatarCricle\": \"false\", \"hideMessageName\": \"false\", \"hideMessageTime\": \"false\"}', 0, 0, 1557933999, 1728161315, 1217, 0, 1730697701, '103.121.164.134', NULL, 0, 1),
(4, '13800000004', '喜洋洋', '2cb4ecb7fd5295685e275edc7d44e02e', 'srww', NULL, 'xiyangyang@qq.com', 1, 0, '', '', 'xiyangyang', 0, '{\"theme\": \"blue\", \"isVoice\": \"true\", \"sendKey\": \"1\", \"avatarCricle\": \"true\", \"hideMessageName\": \"true\", \"hideMessageTime\": \"true\"}', 0, 0, 1604587165, 1730142085, 834, 0, 1730643800, '180.91.180.120', NULL, 0, 1),
(5, '13800000005', '灰太狼', '2cb4ecb7fd5295685e275edc7d44e02e', 'srww', NULL, 'huitailang@qq.com', 1, 0, NULL, '', 'huitailang', 0, '{\"theme\": \"default\", \"isVoice\": \"true\", \"sendKey\": \"1\", \"avatarCricle\": \"true\", \"hideMessageName\": \"true\", \"hideMessageTime\": \"true\"}', 0, 0, 1604587246, 1711360067, 859, 0, 1730692491, '1.199.39.24', NULL, 0, 1),
(6, '13800000006', '奥特曼', '2cb4ecb7fd5295685e275edc7d44e02e', 'srww', NULL, 'aoteman@qq.com', 1, 0, '', '', 'aoteman', 0, '{\"theme\": \"blue\", \"isVoice\": \"true\", \"sendKey\": \"1\", \"avatarCricle\": \"true\", \"hideMessageName\": \"true\", \"hideMessageTime\": \"false\"}', 0, 0, 1604587295, 1729431591, 824, 0, 1730688234, '120.224.39.54', NULL, 0, 1),
(7, '13800000007', '孙悟空', '2cb4ecb7fd5295685e275edc7d44e02e', 'srww', NULL, 'sunwukong@qq.com', 1, 0, '', '', 'sunwukong', 0, '{\"theme\": \"default\", \"isVoice\": \"true\", \"sendKey\": \"1\", \"avatarCricle\": \"false\", \"hideMessageName\": \"true\", \"hideMessageTime\": \"false\"}', 0, 0, 1604587347, 1728972288, 761, 0, 1730703214, '115.60.18.127', NULL, 0, 1),
(8, '13800000008', '猪八戒', '2cb4ecb7fd5295685e275edc7d44e02e', 'srww', NULL, 'zhubajie@qq.com', 1, 0, '', '', 'zhubajie', 0, '{\"theme\": \"default\", \"isVoice\": \"false\", \"sendKey\": \"1\", \"avatarCricle\": \"true\", \"hideMessageName\": \"false\", \"hideMessageTime\": \"true\"}', 0, 0, 1604587378, 1726480311, 894, 0, 1730705108, '120.211.148.44', NULL, 0, 1),
(9, '13800000009', '唐三藏', '2cb4ecb7fd5295685e275edc7d44e02e', 'srww', NULL, 'tangsanzang@qq.com', 0, 0, '', '', 'tangsanzang', 0, '{\"theme\": \"blue\", \"isVoice\": \"true\", \"sendKey\": \"1\", \"avatarCricle\": \"true\", \"hideMessageName\": \"false\", \"hideMessageTime\": \"false\"}', 0, 0, 1604587409, 1723078304, 1147, 0, 1730462811, '120.228.7.21', NULL, 0, 1),
(10, '13800000010', '沙悟净', '2cb4ecb7fd5295685e275edc7d44e02e', 'srww', NULL, 'sss', 2, 0, '', '', 'shawujing', 0, '{\"theme\": \"blue\", \"isVoice\": \"true\", \"sendKey\": \"1\", \"avatarCricle\": \"true\", \"hideMessageName\": \"true\", \"hideMessageTime\": \"false\"}', 0, 0, 1604587409, 1727523988, 818, 0, 1730689889, '120.224.39.54', NULL, 0, 1),
(11, '13800000011', '刘备', '2cb4ecb7fd5295685e275edc7d44e02e', 'srww', NULL, 'liubei@kaishanlaw.com', 1, 0, '', '', 'hongbaolai', 0, '{\"theme\": \"blue\", \"isVoice\": \"true\", \"sendKey\": \"2\", \"avatarCricle\": \"false\", \"hideMessageName\": \"false\", \"hideMessageTime\": \"false\"}', 0, 0, 1555341865, 1724836138, 861, 0, 1730703374, '115.60.18.127', NULL, 0, 1),
(12, '13800000012', '关羽', '2cb4ecb7fd5295685e275edc7d44e02e', 'srww', NULL, 'gggg', 1, 0, '', '', 'guanyu', 0, '{\"theme\": \"blue\", \"isVoice\": \"true\", \"sendKey\": \"1\", \"avatarCricle\": \"false\", \"hideMessageName\": \"false\", \"hideMessageTime\": \"false\"}', 0, 0, 1557933999, 1730285557, 781, 0, 1730686560, '120.224.39.54', NULL, 0, 1),
(13, '13800000013', '张飞', '2cb4ecb7fd5295685e275edc7d44e02e', 'srww', NULL, 'i', 1, 0, '', '', 'zhangfei', 0, '{\"theme\": \"default\", \"isVoice\": \"true\", \"sendKey\": \"1\", \"avatarCricle\": \"false\", \"hideMessageName\": \"false\", \"hideMessageTime\": \"false\"}', 0, 0, 1604587165, 1729275843, 641, 0, 1730702978, '115.60.18.127', NULL, 0, 1),
(14, '13800000014', '赵云', '2cb4ecb7fd5295685e275edc7d44e02e', 'srww', '', 'zhaoyun@qq.com', 1, 0, '', '', 'zhaoyun', 0, '{\"theme\": \"default\", \"isVoice\": \"true\", \"sendKey\": \"1\", \"avatarCricle\": \"true\", \"hideMessageName\": \"true\", \"hideMessageTime\": \"false\"}', 0, 0, 1604587246, 1730374373, 662, 0, 1730688783, '124.135.239.79', NULL, 0, 1),
(15, '13800000015', '曹操', '2cb4ecb7fd5295685e275edc7d44e02e', 'srww', NULL, 'caocao@qq.com', 1, 0, '', '', 'caocao', 0, '{\"theme\": \"blue\", \"isVoice\": \"true\", \"sendKey\": \"1\", \"avatarCricle\": \"true\", \"hideMessageName\": \"false\", \"hideMessageTime\": \"false\"}', 0, 0, 1604587295, 1720255912, 800, 0, 1730703843, '220.173.180.106', NULL, 0, 1),
(16, '13800000016', '司马懿', '2cb4ecb7fd5295685e275edc7d44e02e', 'srww', NULL, 'simayi@qq.com', 2, 0, '', '', 'simayi', 0, '{\"theme\": \"default\", \"isVoice\": \"true\", \"sendKey\": \"1\", \"avatarCricle\": \"true\", \"hideMessageName\": \"false\", \"hideMessageTime\": \"false\"}', 0, 0, 1604587347, 1711527030, 781, 0, 1730703600, '218.57.140.131', NULL, 0, 1),
(17, '13800000017', '孙权', '2cb4ecb7fd5295685e275edc7d44e02e', 'srww', NULL, 'sunquan@qq.com', 1, 0, 'fv', '', 'sunquan', 0, '{\"theme\": \"default\", \"isVoice\": \"true\", \"sendKey\": \"1\", \"avatarCricle\": \"true\", \"hideMessageName\": \"true\", \"hideMessageTime\": \"true\"}', 0, 0, 1604587378, 1714396067, 713, 0, 1730598894, '39.148.72.199', NULL, 0, 1),
(18, '13800000018', '周瑜', '2cb4ecb7fd5295685e275edc7d44e02e', 'srww', NULL, 'zhouyu@qq.com', 1, 0, '12121', '', 'zhouyu', 0, '{\"theme\": \"default\", \"isVoice\": \"true\", \"sendKey\": \"1\", \"avatarCricle\": \"true\", \"hideMessageName\": \"false\", \"hideMessageTime\": \"false\"}', 0, 0, 1604587409, 1714437700, 786, 0, 1730700668, '222.71.91.18', NULL, 0, 1),
(19, '13800000019', '诸葛亮', '2cb4ecb7fd5295685e275edc7d44e02e', 'srww', NULL, 'zhugeliang@qq.com', 0, 0, '', '', 'zhugeliang', 0, '{\"theme\": \"blue\", \"isVoice\": \"true\", \"sendKey\": \"1\", \"avatarCricle\": \"false\", \"hideMessageName\": \"false\", \"hideMessageTime\": \"false\"}', 0, 0, 1604587378, 1730705058, 883, 0, 1730688482, '222.212.4.43', NULL, 0, 1),
(20, '13800000020', '吕布', '2cb4ecb7fd5295685e275edc7d44e02e', 'srww', NULL, 'lvbu@qq.com', 0, 0, '', '', 'lvbu', 0, '{\"theme\": \"default\", \"isVoice\": \"true\", \"sendKey\": \"1\", \"avatarCricle\": \"true\", \"hideMessageName\": \"false\", \"hideMessageTime\": \"false\"}', 0, 0, 1604587409, 1729935411, 1750, 0, 1730014387, '101.44.83.192', NULL, 0, 1);


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
-- 使用表AUTO_INCREMENT `yu_chat_delog`
--
ALTER TABLE `yu_chat_delog`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

--
-- 表的索引 `yu_config`
--
ALTER TABLE `yu_config`
  ADD PRIMARY KEY (`id`);

--
-- 表的索引 `yu_file`
--
ALTER TABLE `yu_file`
  ADD PRIMARY KEY (`file_id`);

--
-- 表的索引 `yu_friend`
--
ALTER TABLE `yu_friend`
  ADD PRIMARY KEY (`friend_id`);

--
-- 表的索引 `yu_group`
--
ALTER TABLE `yu_group`
  ADD PRIMARY KEY (`group_id`);

--
-- 表的索引 `yu_group_user`
--
ALTER TABLE `yu_group_user`
  ADD PRIMARY KEY (`id`);

--
-- 表的索引 `yu_message`
--
ALTER TABLE `yu_message`
  ADD PRIMARY KEY (`msg_id`);

--
-- 表的索引 `yu_emoji`
--
ALTER TABLE `yu_emoji`
  ADD PRIMARY KEY (`id`);

--
-- 表的索引 `yu_user`
--
ALTER TABLE `yu_user`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `account` (`account`) USING BTREE,
  ADD KEY `accountpassword` (`account`,`password`);

--
-- 在导出的表使用AUTO_INCREMENT
--

--
-- 使用表AUTO_INCREMENT `yu_config`
--
ALTER TABLE `yu_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- 使用表AUTO_INCREMENT `yu_file`
--
ALTER TABLE `yu_file`
  MODIFY `file_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `yu_friend`
--
ALTER TABLE `yu_friend`
  MODIFY `friend_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `yu_group`
--
ALTER TABLE `yu_group`
  MODIFY `group_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `yu_group_user`
--
ALTER TABLE `yu_group_user`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `yu_message`
--
ALTER TABLE `yu_message`
  MODIFY `msg_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `yu_emoji`
--
ALTER TABLE `yu_emoji`
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
--
-- 使用表AUTO_INCREMENT `yu_user`
--
ALTER TABLE `yu_user`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;
COMMIT;

