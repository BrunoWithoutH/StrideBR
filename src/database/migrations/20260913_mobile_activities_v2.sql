BEGIN;

SET search_path TO stridebr, public;

ALTER TABLE gravacoes_gps_web
    ALTER COLUMN duracao_s TYPE NUMERIC(12,3) USING duracao_s::numeric;

ALTER TABLE rotas_atividade
    ADD COLUMN IF NOT EXISTS pontos_metadata JSONB;

ALTER TABLE rotas_atividade DROP CONSTRAINT IF EXISTS ck_rotas_pontos_metadata;
ALTER TABLE rotas_atividade
    ADD CONSTRAINT ck_rotas_pontos_metadata CHECK (
        pontos_metadata IS NULL OR jsonb_typeof(pontos_metadata) = 'array'
    );

COMMIT;
