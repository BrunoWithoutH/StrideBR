BEGIN;

SET search_path TO stridebr, public;

ALTER TABLE metas_usuario
    ADD COLUMN IF NOT EXISTS tipo_meta VARCHAR(20) NOT NULL DEFAULT 'metrica',
    ADD COLUMN IF NOT EXISTS benchmark_tipo VARCHAR(40),
    ADD COLUMN IF NOT EXISTS benchmark_distancia_m NUMERIC(12,3),
    ADD COLUMN IF NOT EXISTS benchmark_referencia_nome_snapshot VARCHAR(160),
    ADD COLUMN IF NOT EXISTS valor_inicial NUMERIC(20,6),
    ADD COLUMN IF NOT EXISTS data_valor_inicial DATE,
    ADD COLUMN IF NOT EXISTS idbenchmark_inicial VARCHAR(21) REFERENCES benchmarks_usuario(idbenchmark) ON DELETE SET NULL;

ALTER TABLE metas_usuario ALTER COLUMN metrica DROP NOT NULL;

ALTER TABLE metas_usuario DROP CONSTRAINT IF EXISTS metas_usuario_metrica_check;
ALTER TABLE metas_usuario DROP CONSTRAINT IF EXISTS metas_usuario_periodo_check;
ALTER TABLE metas_usuario DROP CONSTRAINT IF EXISTS ck_metas_metrica;
ALTER TABLE metas_usuario DROP CONSTRAINT IF EXISTS ck_metas_periodo;
ALTER TABLE metas_usuario DROP CONSTRAINT IF EXISTS ck_metas_datas;
ALTER TABLE metas_usuario DROP CONSTRAINT IF EXISTS ck_metas_exercicio;
ALTER TABLE metas_usuario DROP CONSTRAINT IF EXISTS ck_metas_tipo_meta;
ALTER TABLE metas_usuario DROP CONSTRAINT IF EXISTS ck_metas_shape;
ALTER TABLE metas_usuario DROP CONSTRAINT IF EXISTS ck_metas_benchmark_reference;

ALTER TABLE metas_usuario
    ADD CONSTRAINT ck_metas_tipo_meta CHECK (tipo_meta IN ('metrica', 'benchmark')),
    ADD CONSTRAINT ck_metas_periodo CHECK (periodo IN ('continuo', 'semanal', 'mensal', 'anual', 'personalizado')),
    ADD CONSTRAINT ck_metas_datas CHECK (
        (periodo = 'personalizado' AND data_inicio IS NOT NULL AND data_fim IS NOT NULL AND data_fim >= data_inicio)
        OR (periodo = 'continuo' AND data_inicio IS NOT NULL AND data_fim IS NULL)
        OR (periodo IN ('semanal', 'mensal', 'anual') AND data_inicio IS NULL AND data_fim IS NULL)
    ),
    ADD CONSTRAINT ck_metas_shape CHECK (
        (
            tipo_meta = 'metrica'
            AND metrica IN ('distancia', 'duracao', 'atividades', 'elevacao', 'dias_ativos', 'carga_maxima')
            AND benchmark_tipo IS NULL
            AND benchmark_distancia_m IS NULL
            AND benchmark_referencia_nome_snapshot IS NULL
            AND valor_inicial IS NULL
            AND data_valor_inicial IS NULL
            AND idbenchmark_inicial IS NULL
        )
        OR
        (
            tipo_meta = 'benchmark'
            AND metrica IS NULL
            AND benchmark_tipo IN ('one_rm', 'ftp', 'css', 'distance_time')
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
                (valor_inicial IS NULL AND data_valor_inicial IS NULL AND idbenchmark_inicial IS NULL)
                OR (valor_inicial > 0 AND data_valor_inicial IS NOT NULL AND data_valor_inicial <= data_inicio)
            )
        )
    );

CREATE INDEX IF NOT EXISTS ix_metas_usuario_benchmark
    ON metas_usuario (idusuario, benchmark_tipo, idmodalidade, data_inicio DESC)
    WHERE tipo_meta = 'benchmark';

CREATE INDEX IF NOT EXISTS ix_metas_usuario_benchmark_exercicio
    ON metas_usuario (idusuario, benchmark_tipo, idexercicio, data_inicio DESC)
    WHERE tipo_meta = 'benchmark' AND idexercicio IS NOT NULL;

ALTER TABLE metas_conclusoes
    ADD COLUMN IF NOT EXISTS idbenchmark VARCHAR(21) REFERENCES benchmarks_usuario(idbenchmark) ON DELETE SET NULL,
    ADD COLUMN IF NOT EXISTS data_resultado DATE;

CREATE INDEX IF NOT EXISTS ix_metas_conclusoes_benchmark
    ON metas_conclusoes (idbenchmark)
    WHERE idbenchmark IS NOT NULL;

COMMIT;
