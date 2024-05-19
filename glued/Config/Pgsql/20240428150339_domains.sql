-- migrate:up

CREATE TABLE core_domains (
    uuid UUID GENERATED ALWAYS AS ((doc->>'uuid')::UUID) STORED PRIMARY KEY,
    doc JSONB DEFAULT NULL,
    name TEXT GENERATED ALWAYS AS ((doc->>'name')) STORED,
    is_root_domain CHAR(1) GENERATED ALWAYS AS ((doc->>'props.root')) STORED,
    created_by UUID GENERATED ALWAYS AS ((doc->>'created-by')::UUID) STORED,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (uuid)
);

-- migrate:down

DROP TABLE IF EXISTS core_domains;