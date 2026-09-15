BEGIN;

SET search_path TO stridebr, public;

ALTER TABLE sessoes_treino
    ADD COLUMN IF NOT EXISTS origem_externa VARCHAR(32),
    ADD COLUMN IF NOT EXISTS referencia_externa VARCHAR(200),
    ADD COLUMN IF NOT EXISTS recipient_ref_externo VARCHAR(200),
    ADD COLUMN IF NOT EXISTS contexto_institucional_snapshot JSONB NOT NULL DEFAULT '{}'::jsonb;

CREATE INDEX IF NOT EXISTS ix_sessoes_treino_external_ref
ON sessoes_treino (idusuario, origem_externa, referencia_externa)
WHERE referencia_externa IS NOT NULL;

COMMIT;
