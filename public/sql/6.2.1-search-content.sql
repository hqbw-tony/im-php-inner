ALTER TABLE `yu_message`
  ADD COLUMN `search_content` text COLLATE utf8mb4_unicode_ci COMMENT '搜索内容，仅用于检索，不返回给客户端' AFTER `content`;
