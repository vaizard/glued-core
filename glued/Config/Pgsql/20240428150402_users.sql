-- migrate:up

CREATE TABLE core_users (
    uuid UUID GENERATED ALWAYS AS ((doc->>'uuid')::UUID) STORED PRIMARY KEY,
    doc JSONB DEFAULT NULL,
    email VARCHAR(255) GENERATED ALWAYS AS ((doc->>'profile.email')) STORED,
    handle VARCHAR(255) GENERATED ALWAYS AS ((doc->>'profile.handle')) STORED,
    locale CHAR(5) GENERATED ALWAYS AS ((doc->>'attributes.locale')) STORED,
    active BOOLEAN GENERATED ALWAYS AS ((doc->>'attributes.active')::BOOLEAN) STORED,
    ts_created TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    ts_updated TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_handle ON core_users (handle);

-- migrate:down

DROP TABLE IF EXISTS core_users;