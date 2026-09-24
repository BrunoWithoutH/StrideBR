BEGIN;
SET search_path TO stridebr, public;

CREATE TABLE IF NOT EXISTS grupos_prescricao (
    idgrupo VARCHAR(21) PRIMARY KEY,
    idtreino VARCHAR(21) REFERENCES treinos_cronograma(idtreino) ON DELETE CASCADE,
    idtreino_modelo VARCHAR(21) REFERENCES treinos_modelo(idtreino_modelo) ON DELETE CASCADE,
    idagendamento VARCHAR(21) REFERENCES treinos_agendados(idagendamento) ON DELETE CASCADE,
    idsessao VARCHAR(21) REFERENCES sessoes_treino(idsessao) ON DELETE CASCADE,
    tipo VARCHAR(24) NOT NULL CHECK (tipo IN ('superset','circuit')),
    voltas INTEGER NOT NULL DEFAULT 1 CHECK (voltas BETWEEN 1 AND 99),
    descanso_entre_exercicios_s INTEGER CHECK (descanso_entre_exercicios_s IS NULL OR descanso_entre_exercicios_s BETWEEN 0 AND 86400),
    descanso_pos_volta_s INTEGER CHECK (descanso_pos_volta_s IS NULL OR descanso_pos_volta_s BETWEEN 0 AND 86400),
    ordem INTEGER NOT NULL CHECK (ordem > 0),
    data_criacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT ck_grupos_prescricao_parent CHECK (num_nonnulls(idtreino,idtreino_modelo,idagendamento,idsessao) = 1)
);

CREATE UNIQUE INDEX IF NOT EXISTS ux_grupos_prescricao_treino_ordem ON grupos_prescricao(idtreino,ordem) WHERE idtreino IS NOT NULL;
CREATE UNIQUE INDEX IF NOT EXISTS ux_grupos_prescricao_modelo_ordem ON grupos_prescricao(idtreino_modelo,ordem) WHERE idtreino_modelo IS NOT NULL;
CREATE UNIQUE INDEX IF NOT EXISTS ux_grupos_prescricao_agendamento_ordem ON grupos_prescricao(idagendamento,ordem) WHERE idagendamento IS NOT NULL;
CREATE UNIQUE INDEX IF NOT EXISTS ux_grupos_prescricao_sessao_ordem ON grupos_prescricao(idsessao,ordem) WHERE idsessao IS NOT NULL;

ALTER TABLE treinos_exercicios
    ADD COLUMN IF NOT EXISTS metodo_prescricao VARCHAR(24) NOT NULL DEFAULT 'standard',
    ADD COLUMN IF NOT EXISTS config_prescricao JSONB,
    ADD COLUMN IF NOT EXISTS idgrupo_prescricao VARCHAR(21) REFERENCES grupos_prescricao(idgrupo) ON DELETE SET NULL;

ALTER TABLE treinos_modelo_exercicios
    ADD COLUMN IF NOT EXISTS metodo_prescricao VARCHAR(24) NOT NULL DEFAULT 'standard',
    ADD COLUMN IF NOT EXISTS config_prescricao JSONB,
    ADD COLUMN IF NOT EXISTS idgrupo_prescricao VARCHAR(21) REFERENCES grupos_prescricao(idgrupo) ON DELETE SET NULL;

ALTER TABLE treinos_agendados_exercicios
    ADD COLUMN IF NOT EXISTS metodo_prescricao VARCHAR(24) NOT NULL DEFAULT 'standard',
    ADD COLUMN IF NOT EXISTS config_prescricao JSONB,
    ADD COLUMN IF NOT EXISTS idgrupo_prescricao VARCHAR(21) REFERENCES grupos_prescricao(idgrupo) ON DELETE SET NULL;

ALTER TABLE sessoes_treino_exercicios
    ADD COLUMN IF NOT EXISTS metodo_prescricao VARCHAR(24) NOT NULL DEFAULT 'standard',
    ADD COLUMN IF NOT EXISTS config_prescricao JSONB,
    ADD COLUMN IF NOT EXISTS idgrupo_prescricao VARCHAR(21) REFERENCES grupos_prescricao(idgrupo) ON DELETE SET NULL;

DO $$ DECLARE rel regclass; cname text; BEGIN
    FOREACH rel IN ARRAY ARRAY[
        'stridebr.treinos_exercicios'::regclass,
        'stridebr.treinos_modelo_exercicios'::regclass,
        'stridebr.treinos_agendados_exercicios'::regclass,
        'stridebr.sessoes_treino_exercicios'::regclass
    ] LOOP
        cname := 'ck_' || replace(rel::text, '.', '_') || '_metodo_prescricao';
        IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conrelid = rel AND conname = cname) THEN
            EXECUTE format('ALTER TABLE %s ADD CONSTRAINT %I CHECK (metodo_prescricao IN (''standard'',''cluster'',''drop_set''))', rel, cname);
        END IF;
    END LOOP;
END $$;

ALTER TABLE sessoes_treino_series
    ADD COLUMN IF NOT EXISTS segmento_tipo VARCHAR(24) NOT NULL DEFAULT 'set',
    ADD COLUMN IF NOT EXISTS bloco_indice INTEGER,
    ADD COLUMN IF NOT EXISTS etapa_indice INTEGER,
    ADD COLUMN IF NOT EXISTS repeticoes_planejadas VARCHAR(40),
    ADD COLUMN IF NOT EXISTS carga_planejada VARCHAR(40),
    ADD COLUMN IF NOT EXISTS duracao_planejada_s INTEGER,
    ADD COLUMN IF NOT EXISTS distancia_planejada_m NUMERIC(14,3),
    ADD COLUMN IF NOT EXISTS meta_repeticoes JSONB,
    ADD COLUMN IF NOT EXISTS descanso_apos_s INTEGER;

DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conrelid='sessoes_treino_series'::regclass AND conname='ck_sessoes_treino_series_segmento_tipo') THEN
        ALTER TABLE sessoes_treino_series ADD CONSTRAINT ck_sessoes_treino_series_segmento_tipo CHECK (segmento_tipo IN ('set','cluster','drop_stage'));
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conrelid='sessoes_treino_series'::regclass AND conname='ck_sessoes_treino_series_bloco_indice') THEN
        ALTER TABLE sessoes_treino_series ADD CONSTRAINT ck_sessoes_treino_series_bloco_indice CHECK (bloco_indice IS NULL OR bloco_indice BETWEEN 1 AND 99);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conrelid='sessoes_treino_series'::regclass AND conname='ck_sessoes_treino_series_etapa_indice') THEN
        ALTER TABLE sessoes_treino_series ADD CONSTRAINT ck_sessoes_treino_series_etapa_indice CHECK (etapa_indice IS NULL OR etapa_indice BETWEEN 1 AND 99);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conrelid='sessoes_treino_series'::regclass AND conname='ck_sessoes_treino_series_duracao_planejada') THEN
        ALTER TABLE sessoes_treino_series ADD CONSTRAINT ck_sessoes_treino_series_duracao_planejada CHECK (duracao_planejada_s IS NULL OR duracao_planejada_s BETWEEN 0 AND 604800);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conrelid='sessoes_treino_series'::regclass AND conname='ck_sessoes_treino_series_distancia_planejada') THEN
        ALTER TABLE sessoes_treino_series ADD CONSTRAINT ck_sessoes_treino_series_distancia_planejada CHECK (distancia_planejada_m IS NULL OR distancia_planejada_m BETWEEN 0 AND 999999999.999);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conrelid='sessoes_treino_series'::regclass AND conname='ck_sessoes_treino_series_descanso_apos') THEN
        ALTER TABLE sessoes_treino_series ADD CONSTRAINT ck_sessoes_treino_series_descanso_apos CHECK (descanso_apos_s IS NULL OR descanso_apos_s BETWEEN 0 AND 86400);
    END IF;
END $$;

ALTER TABLE series_exercicio_atividade
    ADD COLUMN IF NOT EXISTS metodo_prescricao VARCHAR(24),
    ADD COLUMN IF NOT EXISTS segmento_tipo VARCHAR(24),
    ADD COLUMN IF NOT EXISTS bloco_indice INTEGER,
    ADD COLUMN IF NOT EXISTS etapa_indice INTEGER,
    ADD COLUMN IF NOT EXISTS meta_planejada JSONB;

CREATE INDEX IF NOT EXISTS ix_grupos_prescricao_treino ON grupos_prescricao(idtreino) WHERE idtreino IS NOT NULL;
CREATE INDEX IF NOT EXISTS ix_grupos_prescricao_modelo ON grupos_prescricao(idtreino_modelo) WHERE idtreino_modelo IS NOT NULL;
CREATE INDEX IF NOT EXISTS ix_grupos_prescricao_agendamento ON grupos_prescricao(idagendamento) WHERE idagendamento IS NOT NULL;
CREATE INDEX IF NOT EXISTS ix_grupos_prescricao_sessao ON grupos_prescricao(idsessao) WHERE idsessao IS NOT NULL;

COMMIT;
