-- migrate:up

ALTER TABLE `t_core_users`
DROP `c_account`,
CHANGE `c_attr` `c_attr` json NULL COMMENT 'Account attributes and state (locale, enabled/disabled, GDPR anonymised, etc.)' AFTER `c_profile`,
CHANGE `c_nick` `c_handle` varchar(255) COLLATE 'utf8mb4_0900_ai_ci' NOT NULL COMMENT 'User handle' AFTER `c_locale`;
ALTER TABLE  `t_core_users`  DROP `c_stor_name`;
ALTER TABLE `t_core_users` ADD `c_stor_name` varchar(255) GENERATED ALWAYS AS (`c_handle`) VIRTUAL COMMENT '[VIRTUAL] Stor name';
ALTER TABLE `t_core_users` ADD INDEX `c_handle` (`c_handle`);
ALTER TABLE `t_core_users` DROP INDEX `c_nick`;
ALTER TABLE `t_core_domains` DROP `c_stor_name`;
ALTER TABLE `t_core_domains` DROP `c_name`;
ALTER TABLE `t_core_domains` ADD `c_name` varchar(255) GENERATED ALWAYS AS (c_json->>'$.name') VIRTUAL COMMENT '[VIRTUAL] Domain name' AFTER `c_json`;
ALTER TABLE `t_core_domains` ADD`c_stor_name` varchar(255) GENERATED ALWAYS AS (`c_name`) VIRTUAL COMMENT '[VIRTUAL] Stor name';
ALTER TABLE `t_core_domains` CHANGE `c_core_user` `c_primary_owner` binary(16) NOT NULL COMMENT 'Domain\'s primary owner (c_core_users.uuid)' AFTER `c_uuid`;
ALTER TABLE `t_core_domains` ADD `c_is_root` char(1) GENERATED ALWAYS AS (c_json->>'$._root') VIRTUAL COMMENT '[VIRTUAL] Root domain flag' AFTER `c_name`;

-- migrate:down

