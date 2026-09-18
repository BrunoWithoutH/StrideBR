SET search_path TO stridebr, public;

BEGIN;
ALTER TABLE sessoes_treino_series ADD COLUMN IF NOT EXISTS duracao_realizada_s INTEGER NULL;
ALTER TABLE sessoes_treino_series ADD COLUMN IF NOT EXISTS distancia_realizada_m NUMERIC(12,3) NULL;
DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conrelid = 'sessoes_treino_series'::regclass AND conname = 'ck_session_set_duration_nonnegative') THEN
        ALTER TABLE sessoes_treino_series ADD CONSTRAINT ck_session_set_duration_nonnegative CHECK (duracao_realizada_s >= 0);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conrelid = 'sessoes_treino_series'::regclass AND conname = 'ck_session_set_distance_nonnegative') THEN
        ALTER TABLE sessoes_treino_series ADD CONSTRAINT ck_session_set_distance_nonnegative CHECK (distancia_realizada_m >= 0);
    END IF;
END $$;
COMMIT;
