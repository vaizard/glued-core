-- migrate:up

CREATE TABLE core_roles (
    uuid UUID NOT NULL DEFAULT gen_random_uuid(),
    name TEXT DEFAULT NULL,
    description TEXT DEFAULT NULL,
    PRIMARY KEY (uuid),
    UNIQUE (name)
);

-- migrate:down

DROP TABLE IF EXISTS core_roles;