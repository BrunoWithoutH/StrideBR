BEGIN;
SET search_path TO stridebr, public;

ALTER TABLE registros_atividade
    ADD COLUMN IF NOT EXISTS excluir_estatisticas BOOLEAN NOT NULL DEFAULT FALSE;

CREATE INDEX IF NOT EXISTS ix_registros_atividade_stats
    ON registros_atividade (idusuario, data_inicio DESC, idregistro DESC)
    WHERE excluido_em IS NULL AND status = 'concluido' AND excluir_estatisticas = FALSE;

ALTER TABLE equipamentos_usuario
    ADD COLUMN IF NOT EXISTS limite_alerta_km NUMERIC(12,3),
    ADD COLUMN IF NOT EXISTS data_fim_uso DATE;

DO $$ BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'ck_equipamentos_usuario_limite_alerta'
          AND conrelid = 'stridebr.equipamentos_usuario'::regclass
    ) THEN
        ALTER TABLE equipamentos_usuario
            ADD CONSTRAINT ck_equipamentos_usuario_limite_alerta
            CHECK (limite_alerta_km IS NULL OR limite_alerta_km > 0);
    END IF;
END $$;

ALTER TABLE competicoes_usuario
    ADD COLUMN IF NOT EXISTS participacao_status VARCHAR(20) NOT NULL DEFAULT 'interessado',
    ADD COLUMN IF NOT EXISTS resultado_tempo_s INTEGER,
    ADD COLUMN IF NOT EXISTS resultado_distancia_m NUMERIC(14,3),
    ADD COLUMN IF NOT EXISTS resultado_posicao_geral INTEGER,
    ADD COLUMN IF NOT EXISTS resultado_posicao_categoria INTEGER,
    ADD COLUMN IF NOT EXISTS resultado_categoria VARCHAR(120),
    ADD COLUMN IF NOT EXISTS resultado_medalha VARCHAR(120),
    ADD COLUMN IF NOT EXISTS resultado_observacoes TEXT,
    ADD COLUMN IF NOT EXISTS url_oficial TEXT;

DO $$ BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'ck_competicoes_usuario_participacao_status'
          AND conrelid = 'stridebr.competicoes_usuario'::regclass
    ) THEN
        ALTER TABLE competicoes_usuario
            ADD CONSTRAINT ck_competicoes_usuario_participacao_status
            CHECK (participacao_status IN ('interessado','inscrito','participou','cancelou'));
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'ck_competicoes_usuario_resultado'
          AND conrelid = 'stridebr.competicoes_usuario'::regclass
    ) THEN
        ALTER TABLE competicoes_usuario
            ADD CONSTRAINT ck_competicoes_usuario_resultado
            CHECK (
                (resultado_tempo_s IS NULL OR resultado_tempo_s > 0)
                AND (resultado_distancia_m IS NULL OR resultado_distancia_m > 0)
                AND (resultado_posicao_geral IS NULL OR resultado_posicao_geral > 0)
                AND (resultado_posicao_categoria IS NULL OR resultado_posicao_categoria > 0)
            );
    END IF;
END $$;

CREATE INDEX IF NOT EXISTS ix_competicoes_usuario_participacao
    ON competicoes_usuario (idusuario, participacao_status, data_inicio DESC);

CREATE TABLE IF NOT EXISTS rotas_salvas (
    idrota_salva VARCHAR(21) PRIMARY KEY,
    idusuario VARCHAR(21) NOT NULL REFERENCES usuarios(idusuario) ON DELETE CASCADE,
    nome VARCHAR(140) NOT NULL CHECK (length(trim(nome)) BETWEEN 1 AND 140),
    idmodalidade VARCHAR(21) REFERENCES modalidades(idmodalidade) ON DELETE SET NULL,
    idatividade_origem VARCHAR(21) REFERENCES registros_atividade(idregistro) ON DELETE SET NULL,
    idpacerplan VARCHAR(21) REFERENCES pacer_plans(idplan) ON DELETE SET NULL,
    coordenadas JSONB NOT NULL,
    distancia_m NUMERIC(14,3) CHECK (distancia_m IS NULL OR distancia_m >= 0),
    ganho_elevacao_m NUMERIC(12,2) CHECK (ganho_elevacao_m IS NULL OR ganho_elevacao_m >= 0),
    perda_elevacao_m NUMERIC(12,2) CHECK (perda_elevacao_m IS NULL OR perda_elevacao_m >= 0),
    elevacao_min_m NUMERIC(10,2),
    elevacao_max_m NUMERIC(10,2),
    perfil_elevacao JSONB,
    privacidade VARCHAR(20) NOT NULL DEFAULT 'privado' CHECK (privacidade IN ('privado','nao_listado','publico')),
    arquivada BOOLEAN NOT NULL DEFAULT FALSE,
    data_ultima_utilizacao TIMESTAMPTZ,
    data_criacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    data_atualizacao TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS ix_rotas_salvas_usuario
    ON rotas_salvas (idusuario, arquivada, data_ultima_utilizacao DESC NULLS LAST, data_atualizacao DESC);
CREATE INDEX IF NOT EXISTS ix_rotas_salvas_modalidade
    ON rotas_salvas (idusuario, idmodalidade, arquivada)
    WHERE idmodalidade IS NOT NULL;

CREATE TABLE IF NOT EXISTS rotas_salvas_atividades (
    idrota_salva VARCHAR(21) NOT NULL REFERENCES rotas_salvas(idrota_salva) ON DELETE CASCADE,
    idregistro VARCHAR(21) NOT NULL REFERENCES registros_atividade(idregistro) ON DELETE CASCADE,
    origem VARCHAR(20) NOT NULL DEFAULT 'manual' CHECK (origem IN ('manual','source','workout')),
    data_criacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    PRIMARY KEY (idrota_salva, idregistro),
    UNIQUE (idregistro)
);

CREATE INDEX IF NOT EXISTS ix_rotas_salvas_atividades_registro
    ON rotas_salvas_atividades (idregistro, idrota_salva);

ALTER TABLE treinos_cronograma
    ADD COLUMN IF NOT EXISTS idrota_salva VARCHAR(21) REFERENCES rotas_salvas(idrota_salva) ON DELETE SET NULL;
ALTER TABLE treinos_agendados
    ADD COLUMN IF NOT EXISTS idrota_salva VARCHAR(21) REFERENCES rotas_salvas(idrota_salva) ON DELETE SET NULL;
ALTER TABLE treinos_modelo
    ADD COLUMN IF NOT EXISTS idrota_salva VARCHAR(21) REFERENCES rotas_salvas(idrota_salva) ON DELETE SET NULL;

CREATE INDEX IF NOT EXISTS ix_treinos_cronograma_rota
    ON treinos_cronograma (idrota_salva) WHERE idrota_salva IS NOT NULL;
CREATE INDEX IF NOT EXISTS ix_treinos_agendados_rota
    ON treinos_agendados (idrota_salva) WHERE idrota_salva IS NOT NULL;

ALTER TABLE treinos_modelo_exercicios
    ADD COLUMN IF NOT EXISTS tipo_passo VARCHAR(24) NOT NULL DEFAULT 'exercise',
    ADD COLUMN IF NOT EXISTS repeticoes_bloco INTEGER,
    ADD COLUMN IF NOT EXISTS alvo_tipo VARCHAR(24),
    ADD COLUMN IF NOT EXISTS alvo_min NUMERIC(14,3),
    ADD COLUMN IF NOT EXISTS alvo_max NUMERIC(14,3),
    ADD COLUMN IF NOT EXISTS alvo_unidade VARCHAR(24),
    ADD COLUMN IF NOT EXISTS recuperacao_duracao_s INTEGER,
    ADD COLUMN IF NOT EXISTS recuperacao_distancia_m NUMERIC(14,3);

ALTER TABLE treinos_exercicios
    ADD COLUMN IF NOT EXISTS tipo_passo VARCHAR(24) NOT NULL DEFAULT 'exercise',
    ADD COLUMN IF NOT EXISTS repeticoes_bloco INTEGER,
    ADD COLUMN IF NOT EXISTS alvo_tipo VARCHAR(24),
    ADD COLUMN IF NOT EXISTS alvo_min NUMERIC(14,3),
    ADD COLUMN IF NOT EXISTS alvo_max NUMERIC(14,3),
    ADD COLUMN IF NOT EXISTS alvo_unidade VARCHAR(24),
    ADD COLUMN IF NOT EXISTS recuperacao_duracao_s INTEGER,
    ADD COLUMN IF NOT EXISTS recuperacao_distancia_m NUMERIC(14,3);

ALTER TABLE treinos_agendados_exercicios
    ADD COLUMN IF NOT EXISTS tipo_passo VARCHAR(24) NOT NULL DEFAULT 'exercise',
    ADD COLUMN IF NOT EXISTS repeticoes_bloco INTEGER,
    ADD COLUMN IF NOT EXISTS alvo_tipo VARCHAR(24),
    ADD COLUMN IF NOT EXISTS alvo_min NUMERIC(14,3),
    ADD COLUMN IF NOT EXISTS alvo_max NUMERIC(14,3),
    ADD COLUMN IF NOT EXISTS alvo_unidade VARCHAR(24),
    ADD COLUMN IF NOT EXISTS recuperacao_duracao_s INTEGER,
    ADD COLUMN IF NOT EXISTS recuperacao_distancia_m NUMERIC(14,3);

DO $$ DECLARE rel regclass; BEGIN
    FOREACH rel IN ARRAY ARRAY[
        'stridebr.treinos_modelo_exercicios'::regclass,
        'stridebr.treinos_exercicios'::regclass,
        'stridebr.treinos_agendados_exercicios'::regclass
    ] LOOP
        IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'ck_' || replace(rel::text, '.', '_') || '_tipo_passo') THEN
            EXECUTE format('ALTER TABLE %s ADD CONSTRAINT %I CHECK (tipo_passo IN (''exercise'',''warmup'',''work'',''recovery'',''cooldown'',''interval_group''))', rel, 'ck_' || replace(rel::text, '.', '_') || '_tipo_passo');
        END IF;
    END LOOP;
END $$;

COMMIT;
