BEGIN;
SET search_path TO stridebr, public;

-- Application metadata is an object. Historically an empty PHP array was
-- encoded as JSON [] during token refreshes. Empty arrays become {}, while
-- non-empty arrays retain their original value and expose any object members
-- (for example athlete or oauth) at the expected root.
UPDATE integracoes_usuario AS connection
SET metadados = CASE
    WHEN jsonb_typeof(connection.metadados) = 'array' AND jsonb_array_length(connection.metadados) = 0 THEN '{}'::jsonb
    WHEN jsonb_typeof(connection.metadados) = 'array' THEN
        COALESCE((
            SELECT jsonb_object_agg(entry.key, entry.value)
            FROM jsonb_array_elements(connection.metadados) AS item(value)
            CROSS JOIN LATERAL jsonb_each(CASE WHEN jsonb_typeof(item.value) = 'object' THEN item.value ELSE '{}'::jsonb END) AS entry(key, value)
        ), '{}'::jsonb) || jsonb_build_object('_legacy_array', connection.metadados)
    WHEN jsonb_typeof(connection.metadados) <> 'object' THEN jsonb_build_object('_legacy_value', connection.metadados)
    ELSE connection.metadados
END
WHERE jsonb_typeof(connection.metadados) <> 'object';

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conrelid = 'stridebr.integracoes_usuario'::regclass
          AND conname = 'ck_integracoes_usuario_metadados_object'
    ) THEN
        ALTER TABLE integracoes_usuario
            ADD CONSTRAINT ck_integracoes_usuario_metadados_object
            CHECK (jsonb_typeof(metadados) = 'object');
    END IF;
END $$;

COMMIT;
