-- migrate:up

CREATE TABLE "glued"."core_users" (
    uuid uuid generated always as (((doc->>'uuid'::text))::uuid) stored not null,
    doc jsonb not null,
    nonce bytea generated always as (decode(md5((doc - 'uuid')::text), 'hex')) stored,
    created_at timestamp with time zone default CURRENT_TIMESTAMP,
    updated_at timestamp with time zone default CURRENT_TIMESTAMP,
    email VARCHAR(255) GENERATED ALWAYS AS ((doc->>'profile.email')) STORED,
    username VARCHAR(255) GENERATED ALWAYS AS ((doc->>'profile.username')) STORED,
    locale CHAR(5) GENERATED ALWAYS AS ((doc->>'attributes.locale')) STORED,
    active BOOLEAN GENERATED ALWAYS AS ((doc->>'attributes.active')::BOOLEAN) STORED,
    PRIMARY KEY (uuid)
);

CREATE INDEX idx_username ON core_users (username);

INSERT INTO "glued"."core_users" (doc)
VALUES (
           jsonb_build_object(
                   'uuid', '00000000-0000-0000-0000-000000000000',
                   'profile', jsonb_build_object(
                           'email', null,
                           'username', null
                   ),
                   'attributes', jsonb_build_object(
                           'locale', 'en-US',
                           'active', false
                   )
           )
       );

-- migrate:down

DROP TABLE IF EXISTS core_users;