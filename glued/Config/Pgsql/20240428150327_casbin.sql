-- migrate:up

CREATE TABLE IF NOT EXISTS casbin_rule (
   id bigserial NOT NULL,
   ptype varchar(255) NOT NULL,
   v0 varchar(255) DEFAULT NULL,
   v1 varchar(255) DEFAULT NULL,
   v2 varchar(255) DEFAULT NULL,
   v3 varchar(255) DEFAULT NULL,
   v4 varchar(255) DEFAULT NULL,
   v5 varchar(255) DEFAULT NULL,
   hash text GENERATED ALWAYS AS (md5(ptype || '.' || COALESCE(v0, '') || '.' || COALESCE(v1, '') || '.' || COALESCE(v2, '') || '.' || COALESCE(v3, '') || '.' || COALESCE(v4, '') || '.' || COALESCE(v5, ''))) STORED,
   PRIMARY KEY (id),
   UNIQUE (hash)
);

-- migrate:down

DROP TABLE IF EXISTS casbin_rule;
