-- migrate:up

ALTER TABLE `t_core_users` DROP `c_locale`;
ALTER TABLE `t_core_users` ADD `c_active` tinyint(1) GENERATED ALWAYS AS (json_unquote(json_extract(`c_attr`,_utf8mb4'$.status.active'))) STORED COMMENT '[STORED] Account activity status';
ALTER TABLE `t_core_users` ADD `c_locale` char(5) GENERATED ALWAYS AS (json_unquote(json_extract(`c_attr`,_utf8mb4'$."locale"'))) STORED COMMENT '[STORED] Preferred locale';

-- migrate:down

ALTER TABLE `t_core_users` DROP `c_active`;