-- migrate:up

CREATE TABLE core_tokens (
    uuid UUID NOT NULL DEFAULT gen_random_uuid(),
    token TEXT NOT NULL,
    inherit_uuid UUID DEFAULT NULL,
    expired_at TIMESTAMPTZ DEFAULT NULL,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    props JSONB DEFAULT NULL,
    PRIMARY KEY (uuid),
    UNIQUE (token)
);

-- migrate:down

DROP TABLE IF EXISTS core_tokens;