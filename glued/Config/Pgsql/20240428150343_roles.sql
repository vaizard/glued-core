-- migrate:up

CREATE TABLE "glued"."core_roles" (
    uuid uuid generated always as (((doc->>'uuid'::text))::uuid) stored not null,
    doc jsonb not null,
    nonce bytea generated always as (decode(md5((doc - 'uuid')::text), 'hex')) stored,
    created_at timestamp default CURRENT_TIMESTAMP,
    updated_at timestamp default CURRENT_TIMESTAMP,
    PRIMARY KEY (uuid)
);

-- migrate:down

DROP TABLE IF EXISTS core_roles;