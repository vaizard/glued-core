-- migrate:up

CREATE TABLE "glued"."core_pats" (
    uuid uuid generated always as (((doc->>'uuid'::text))::uuid) stored not null,
    doc jsonb not null,
    nonce bytea generated always as (decode(md5((doc - 'uuid')::text), 'hex')) stored,
    created_at timestamp default CURRENT_TIMESTAMP,
    updated_at timestamp default CURRENT_TIMESTAMP,
    expired_at timestamp with time zone GENERATED ALWAYS AS (to_timestamp((doc->>'exp')::double precision)) STORED,
    token TEXT GENERATED ALWAYS AS ((doc->>'token')) STORED,
    inherit_from uuid generated always as (((doc->>'inheritUuid'::text))::uuid) stored not null,
    PRIMARY KEY (uuid),
    UNIQUE (token)
);

CREATE OR REPLACE VIEW glued.core_pat_details AS
SELECT
    tok.*,
    u.username as user_name,
    u.active as user_active
FROM glued.core_pats AS tok
    LEFT JOIN glued.core_users AS u ON tok.inherit_from = u.uuid
WHERE
    COALESCE(tok.expired_at, NOW() + interval '42 seconds') >= NOW()
    AND u.active = true;

-- migrate:down

DROP VIEW core_pat_details;
DROP TABLE IF EXISTS core_pats;