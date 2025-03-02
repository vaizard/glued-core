-- migrate:up

CREATE TABLE glued.core_pats (
                                 uuid uuid GENERATED ALWAYS AS (((doc->>'uuid')::text)::uuid) STORED NOT NULL,
                                 doc jsonb NOT NULL,
                                 nonce bytea GENERATED ALWAYS AS (decode(md5((doc - 'uuid')::text), 'hex')) STORED,
                                 created_at timestamp DEFAULT CURRENT_TIMESTAMP,
                                 updated_at timestamp DEFAULT CURRENT_TIMESTAMP,
                                 expired_at timestamp with time zone GENERATED ALWAYS AS (to_timestamp((doc->>'exp')::double precision)) STORED,
                                 token text GENERATED ALWAYS AS ((doc->>'token')) STORED,
                                 inherit_uuid uuid GENERATED ALWAYS AS (((doc->'inherit'->>'uuid')::text)::uuid) STORED NOT NULL,
                                 PRIMARY KEY (uuid),
                                 UNIQUE (token)
);

CREATE OR REPLACE VIEW glued.core_pats_ext AS
SELECT
    tok.uuid,
    jsonb_set(
            jsonb_set(
                    tok.doc,
                    '{inherit,handle}',
                    to_jsonb(u.handle)
            ),
            '{inherit,active}',
            to_jsonb(u.active)
    ) AS doc,
    tok.nonce,
    tok.created_at,
    tok.updated_at,
    tok.expired_at,
    tok.token,
    tok.inherit_uuid
FROM glued.core_pats AS tok
         LEFT JOIN glued.core_users AS u ON tok.inherit_uuid = u.uuid
WHERE
    COALESCE(tok.expired_at, now() + interval '42 seconds') >= now()
  AND u.active = true;

INSERT INTO "glued"."core_pats" (doc)
VALUES (
           jsonb_build_object(
                   'uuid', gen_random_uuid()::text,
                   'token', replace(
                           replace(
                                   trim(trailing '=' from encode(sha256(random()::text::bytea), 'base64')),
                                   '+', '-'
                           ),
                           '/', '_'
                            ),
                   'exp', null,
                   'inherit', jsonb_build_object(
                           'uuid', (
                       SELECT doc->>'uuid'
                       FROM "glued"."core_users"
                       WHERE handle = 'agent'
                       LIMIT 1
                   )
                              ),
                   'name', 'System agent''s main PAT'
           )
       );


-- migrate:down

DROP VIEW core_pats_ext;
DROP TABLE IF EXISTS core_pats;