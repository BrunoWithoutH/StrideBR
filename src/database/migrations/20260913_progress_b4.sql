BEGIN;

SET search_path TO stridebr, public;

CREATE TABLE IF NOT EXISTS temporadas_usuario (
    idtemporada VARCHAR(21) PRIMARY KEY,
    idusuario VARCHAR(21) NOT NULL REFERENCES usuarios(idusuario) ON DELETE CASCADE,
    idmodalidade VARCHAR(21) NOT NULL REFERENCES modalidades(idmodalidade) ON DELETE RESTRICT,
    nome VARCHAR(120) NOT NULL CHECK (length(trim(nome)) BETWEEN 1 AND 120),
    data_inicio DATE NOT NULL,
    data_fim DATE,
    status VARCHAR(16) NOT NULL DEFAULT 'ativa' CHECK (status IN ('ativa', 'encerrada')),
    observacoes TEXT,
    data_criacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    data_atualizacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT ck_temporadas_usuario_datas CHECK (data_fim IS NULL OR data_fim >= data_inicio),
    CONSTRAINT ck_temporadas_usuario_status_datas CHECK (
        (status = 'ativa' AND data_fim IS NULL)
        OR (status = 'encerrada' AND data_fim IS NOT NULL)
    )
);

CREATE UNIQUE INDEX IF NOT EXISTS ux_temporadas_usuario_ativa_modalidade
    ON temporadas_usuario (idusuario, idmodalidade)
    WHERE status = 'ativa';

CREATE INDEX IF NOT EXISTS ix_temporadas_usuario_modalidade_datas
    ON temporadas_usuario (idusuario, idmodalidade, data_inicio DESC, data_fim DESC NULLS FIRST);

CREATE OR REPLACE FUNCTION fn_temporadas_usuario_sem_sobreposicao()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM temporadas_usuario t
        WHERE t.idusuario = NEW.idusuario
          AND t.idmodalidade = NEW.idmodalidade
          AND t.idtemporada <> NEW.idtemporada
          AND daterange(t.data_inicio, COALESCE(t.data_fim, 'infinity'::date), '[]')
              && daterange(NEW.data_inicio, COALESCE(NEW.data_fim, 'infinity'::date), '[]')
    ) THEN
        RAISE EXCEPTION 'Temporadas da mesma modalidade não podem se sobrepor.' USING ERRCODE = '23514';
    END IF;
    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trg_temporadas_usuario_sem_sobreposicao ON temporadas_usuario;
CREATE TRIGGER trg_temporadas_usuario_sem_sobreposicao
BEFORE INSERT OR UPDATE OF idusuario, idmodalidade, data_inicio, data_fim
ON temporadas_usuario
FOR EACH ROW EXECUTE FUNCTION fn_temporadas_usuario_sem_sobreposicao();

ALTER TABLE benchmarks_usuario
    ADD COLUMN IF NOT EXISTS athletics_event_code VARCHAR(40),
    ADD COLUMN IF NOT EXISTS athletics_environment VARCHAR(12),
    ADD COLUMN IF NOT EXISTS wind_mps NUMERIC(5,2),
    ADD COLUMN IF NOT EXISTS timing_method VARCHAR(12);

ALTER TABLE benchmarks_usuario DROP CONSTRAINT IF EXISTS ck_benchmarks_athletics_environment;
ALTER TABLE benchmarks_usuario DROP CONSTRAINT IF EXISTS ck_benchmarks_timing_method;
ALTER TABLE benchmarks_usuario DROP CONSTRAINT IF EXISTS ck_benchmarks_athletics_shape;

ALTER TABLE benchmarks_usuario
    ADD CONSTRAINT ck_benchmarks_athletics_environment CHECK (
        athletics_environment IS NULL OR athletics_environment IN ('indoor', 'outdoor', 'unknown')
    ),
    ADD CONSTRAINT ck_benchmarks_timing_method CHECK (
        timing_method IS NULL OR timing_method IN ('fat', 'hand', 'unknown')
    ),
    ADD CONSTRAINT ck_benchmarks_athletics_shape CHECK (
        (tipo <> 'athletics' AND athletics_event_code IS NULL AND athletics_environment IS NULL AND wind_mps IS NULL AND timing_method IS NULL)
        OR
        (tipo = 'athletics' AND athletics_event_code IS NOT NULL AND athletics_environment IS NOT NULL)
    );

CREATE INDEX IF NOT EXISTS ix_benchmarks_usuario_athletics_event_data
    ON benchmarks_usuario (idusuario, athletics_event_code, athletics_environment, data_resultado DESC, data_criacao DESC)
    WHERE tipo = 'athletics';

ALTER TABLE metas_usuario
    ADD COLUMN IF NOT EXISTS benchmark_event_code VARCHAR(40),
    ADD COLUMN IF NOT EXISTS benchmark_environment VARCHAR(12),
    ADD COLUMN IF NOT EXISTS benchmark_require_eligible BOOLEAN NOT NULL DEFAULT FALSE;

ALTER TABLE metas_usuario DROP CONSTRAINT IF EXISTS ck_metas_shape;
ALTER TABLE metas_usuario DROP CONSTRAINT IF EXISTS ck_metas_exercicio;
ALTER TABLE metas_usuario DROP CONSTRAINT IF EXISTS ck_metas_benchmark_reference;
ALTER TABLE metas_usuario DROP CONSTRAINT IF EXISTS ck_metas_benchmark_environment;

ALTER TABLE metas_usuario
    ADD CONSTRAINT ck_metas_benchmark_environment CHECK (
        benchmark_environment IS NULL OR benchmark_environment IN ('indoor', 'outdoor', 'unknown')
    ),
    ADD CONSTRAINT ck_metas_shape CHECK (
        (
            tipo_meta = 'metrica'
            AND metrica IN ('distancia', 'duracao', 'atividades', 'elevacao', 'dias_ativos', 'carga_maxima')
            AND benchmark_tipo IS NULL
            AND benchmark_distancia_m IS NULL
            AND benchmark_referencia_nome_snapshot IS NULL
            AND benchmark_event_code IS NULL
            AND benchmark_environment IS NULL
            AND benchmark_require_eligible = FALSE
            AND valor_inicial IS NULL
            AND data_valor_inicial IS NULL
            AND idbenchmark_inicial IS NULL
        )
        OR
        (
            tipo_meta = 'benchmark'
            AND metrica IS NULL
            AND benchmark_tipo IN ('one_rm', 'ftp', 'css', 'distance_time', 'athletics')
            AND idmodalidade IS NOT NULL
            AND periodo IN ('continuo', 'personalizado')
        )
    ),
    ADD CONSTRAINT ck_metas_exercicio CHECK (
        (tipo_meta = 'metrica' AND (metrica <> 'carga_maxima' OR idexercicio IS NOT NULL))
        OR
        (tipo_meta = 'benchmark' AND (benchmark_tipo <> 'one_rm' OR idexercicio IS NOT NULL OR benchmark_referencia_nome_snapshot IS NOT NULL))
    ),
    ADD CONSTRAINT ck_metas_benchmark_reference CHECK (
        tipo_meta <> 'benchmark'
        OR (
            (benchmark_tipo = 'distance_time' OR benchmark_distancia_m IS NULL)
            AND (benchmark_tipo <> 'distance_time' OR benchmark_distancia_m IS NOT NULL)
            AND (benchmark_distancia_m IS NULL OR benchmark_distancia_m > 0)
            AND (benchmark_tipo = 'one_rm' OR (idexercicio IS NULL AND benchmark_referencia_nome_snapshot IS NULL))
            AND (benchmark_referencia_nome_snapshot IS NULL OR length(trim(benchmark_referencia_nome_snapshot)) BETWEEN 1 AND 160)
            AND (
                (benchmark_tipo = 'athletics' AND benchmark_event_code IS NOT NULL AND benchmark_environment IS NOT NULL)
                OR (benchmark_tipo <> 'athletics' AND benchmark_event_code IS NULL AND benchmark_environment IS NULL AND benchmark_require_eligible = FALSE)
            )
            AND (
                (valor_inicial IS NULL AND data_valor_inicial IS NULL AND idbenchmark_inicial IS NULL)
                OR (valor_inicial > 0 AND data_valor_inicial IS NOT NULL AND data_valor_inicial <= data_inicio)
            )
        )
    );

CREATE INDEX IF NOT EXISTS ix_metas_usuario_athletics_event
    ON metas_usuario (idusuario, benchmark_event_code, benchmark_environment, data_inicio DESC)
    WHERE tipo_meta = 'benchmark' AND benchmark_tipo = 'athletics';

ALTER TABLE metas_conclusoes
    ADD COLUMN IF NOT EXISTS idregistro VARCHAR(21) REFERENCES registros_atividade(idregistro) ON DELETE SET NULL,
    ADD COLUMN IF NOT EXISTS idunidade_atividade VARCHAR(21) REFERENCES unidades_atividade(idunidade_atividade) ON DELETE SET NULL;

CREATE INDEX IF NOT EXISTS ix_metas_conclusoes_registro
    ON metas_conclusoes (idregistro)
    WHERE idregistro IS NOT NULL;

CREATE INDEX IF NOT EXISTS ix_metas_conclusoes_unidade
    ON metas_conclusoes (idunidade_atividade)
    WHERE idunidade_atividade IS NOT NULL;

COMMIT;
