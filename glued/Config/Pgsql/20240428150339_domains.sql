-- migrate:up

CREATE TABLE "glued"."core_domains" (
    uuid uuid generated always as (((doc->>'uuid'::text))::uuid) stored not null,
    doc jsonb not null,
    nonce bytea generated always as (decode(md5((doc - 'uuid')::text), 'hex')) stored,
    created_by uuid generated always as ((doc->>'createdBy')::uuid) STORED,
    created_at timestamp default CURRENT_TIMESTAMP,
    updated_at timestamp default CURRENT_TIMESTAMP,
    name TEXT GENERATED ALWAYS AS ((doc->>'name')) STORED,
    root_domain CHAR(1) GENERATED ALWAYS AS ((doc->>'isRootDomain')) STORED,
    PRIMARY KEY (uuid)
);

-- migrate:down

DROP TABLE IF EXISTS core_domains;