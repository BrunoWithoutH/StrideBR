BEGIN;
SET search_path TO stridebr, public;

ALTER TABLE sessoes_treino_exercicios
    ADD COLUMN IF NOT EXISTS tracking_mode VARCHAR(24) NOT NULL DEFAULT 'load_reps',
    ADD COLUMN IF NOT EXISTS tipo_passo VARCHAR(24) NOT NULL DEFAULT 'exercise',
    ADD COLUMN IF NOT EXISTS repeticoes_bloco INTEGER,
    ADD COLUMN IF NOT EXISTS alvo_tipo VARCHAR(24),
    ADD COLUMN IF NOT EXISTS alvo_min NUMERIC(14,3),
    ADD COLUMN IF NOT EXISTS alvo_max NUMERIC(14,3),
    ADD COLUMN IF NOT EXISTS alvo_unidade VARCHAR(24),
    ADD COLUMN IF NOT EXISTS recuperacao_duracao_s INTEGER,
    ADD COLUMN IF NOT EXISTS recuperacao_distancia_m NUMERIC(14,3);

DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conrelid='sessoes_treino_exercicios'::regclass AND conname='ck_sessoes_treino_exercicios_tracking_mode') THEN
        ALTER TABLE sessoes_treino_exercicios ADD CONSTRAINT ck_sessoes_treino_exercicios_tracking_mode CHECK (tracking_mode IN ('load_reps','reps','duration','distance','duration_distance'));
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conrelid='sessoes_treino_exercicios'::regclass AND conname='ck_sessoes_treino_exercicios_tipo_passo') THEN
        ALTER TABLE sessoes_treino_exercicios ADD CONSTRAINT ck_sessoes_treino_exercicios_tipo_passo CHECK (tipo_passo IN ('exercise','warmup','work','recovery','cooldown','interval_group'));
    END IF;
END $$;

COMMIT;
