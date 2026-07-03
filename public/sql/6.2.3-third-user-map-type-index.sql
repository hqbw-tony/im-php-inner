ALTER TABLE `yu_third_user_map` DROP INDEX `platform_external_user`;
ALTER TABLE `yu_third_user_map` ADD UNIQUE KEY `platform_external_user` (`platform_id`, `external_user_id`, `user_type`);
