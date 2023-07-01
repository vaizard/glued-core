-- migrate:up

CREATE TABLE t_core_api_keys (
    c_uuid BINARY(16) NOT NULL DEFAULT (uuid_to_bin(uuid(), true)) COMMENT 'Api key uuid (v4), generated automatically on insert with UUID_TO_BIN(UUID(), true)',
    c_user_uuid BINARY(16) NOT NULL COMMENT 'References t_core_users.c_uuid',
    c_api_key VARCHAR(255) NOT NULL COMMENT 'The apikey random generated string',
    c_expiry_date DATETIME NULL COMMENT 'Null for api keys which should enver expire, datetime for keys with expiry set',
    c_created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    c_updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`c_uuid`),
    UNIQUE KEY `c_api_key` (`c_api_key`),
    KEY `c_user_uuid` (`c_user_uuid`),
    CONSTRAINT `t_core_api_keys_ibfk_1` FOREIGN KEY (`c_user_uuid`) REFERENCES `t_core_users` (`c_uuid`) ON DELETE CASCADE ON UPDATE CASCADE
);

-- migrate:down

DROP TABLE IF EXISTS t_core_api_keys;