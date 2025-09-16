-- migrate:up

-- 1) real table renamed to core_casbin
CREATE TABLE IF NOT EXISTS core_casbin (
   id   BIGSERIAL PRIMARY KEY,
   ptype VARCHAR(255) NOT NULL,
   v0    VARCHAR(255),
   v1    VARCHAR(255),
   v2    VARCHAR(255),
   v3    VARCHAR(255),
   v4    VARCHAR(255),
   v5    VARCHAR(255),
   hash  TEXT GENERATED ALWAYS AS (
       md5(
               ptype  || '.' ||
               COALESCE(v0,'') || '.' ||
               COALESCE(v1,'') || '.' ||
               COALESCE(v2,'') || '.' ||
               COALESCE(v3,'') || '.' ||
               COALESCE(v4,'') || '.' ||
               COALESCE(v5,'')
       )
       ) STORED,
   doc   JSONB DEFAULT '{}'::jsonb NOT NULL,
   UNIQUE(hash)
);

-- 2) view under the old name
CREATE OR REPLACE VIEW casbin_rule AS
SELECT * FROM core_casbin;

-- 3) INSTEAD OF INSERT trigger function on the view
CREATE OR REPLACE FUNCTION casbin_rule_view_insert()
    RETURNS TRIGGER AS $$
DECLARE
    _h TEXT := md5(
            NEW.ptype  || '.' ||
            COALESCE(NEW.v0,'') || '.' ||
            COALESCE(NEW.v1,'') || '.' ||
            COALESCE(NEW.v2,'') || '.' ||
            COALESCE(NEW.v3,'') || '.' ||
            COALESCE(NEW.v4,'') || '.' ||
            COALESCE(NEW.v5,'')
    );
BEGIN
    IF EXISTS (SELECT 1 FROM core_casbin WHERE hash = _h) THEN
        -- update only the hidden doc column
        UPDATE core_casbin SET doc = COALESCE(NEW.doc, doc)
        WHERE hash = _h;
    ELSE
        -- insert new row, with NEW.doc (or default {})
        INSERT INTO core_casbin (ptype, v0, v1, v2, v3, v4, v5, doc)
        VALUES (NEW.ptype, NEW.v0, NEW.v1, NEW.v2, NEW.v3, NEW.v4, NEW.v5, COALESCE(NEW.doc, '{}'::jsonb));
    END IF;
    RETURN NULL;  -- swallow result so clients see “success”
END;
$$ LANGUAGE plpgsql;

-- 4) bind the trigger to the view
CREATE TRIGGER trg_casbin_rule_insert
    INSTEAD OF INSERT ON casbin_rule
    FOR EACH ROW
EXECUTE FUNCTION casbin_rule_view_insert();


-- migrate:down

DROP TRIGGER IF EXISTS trg_casbin_rule_insert on casbin_rule;
DROP FUNCTION IF EXISTS casbin_rule_view_insert();
DROP VIEW IF EXISTS casbin_rule;
DROP TABLE IF EXISTS core_casbin;