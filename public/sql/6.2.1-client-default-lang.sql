UPDATE `yu_config`
SET `value` = JSON_SET(`value`, '$.clientDefaultLang', 'zh-cn')
WHERE `name` = 'sysInfo' AND JSON_EXTRACT(`value`, '$.clientDefaultLang') IS NULL;
