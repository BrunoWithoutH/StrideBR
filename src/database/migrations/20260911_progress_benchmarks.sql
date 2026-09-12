BEGIN;

SET search_path TO stridebr, public;

CREATE TABLE IF NOT EXISTS benchmarks_usuario (
    idbenchmark VARCHAR(21) PRIMARY KEY,
    idusuario VARCHAR(21) NOT NULL REFERENCES usuarios(idusuario) ON DELETE CASCADE,
    idmodalidade VARCHAR(21) NOT NULL REFERENCES modalidades(idmodalidade) ON DELETE RESTRICT,
    tipo VARCHAR(40) NOT NULL CHECK (tipo ~ '^[a-z][a-z0-9_]{1,39}$'),
    valor_canonico NUMERIC(20,6) NOT NULL CHECK (valor_canonico > 0),
    data_resultado DATE NOT NULL,
    idexercicio VARCHAR(21) REFERENCES exercicios(idexercicio) ON DELETE SET NULL,
    idregistro VARCHAR(21) REFERENCES registros_atividade(idregistro) ON DELETE SET NULL,
    idunidade_atividade VARCHAR(21) REFERENCES unidades_atividade(idunidade_atividade) ON DELETE SET NULL,
    referencia_nome_snapshot VARCHAR(160),
    origem VARCHAR(20) NOT NULL DEFAULT 'manual' CHECK (origem IN ('manual', 'atividade', 'importacao', 'api')),
    metodo VARCHAR(20) NOT NULL CHECK (metodo IN ('medido', 'calculado', 'estimado', 'informado')),
    contexto VARCHAR(20) CHECK (contexto IS NULL OR contexto IN ('treino', 'teste', 'competicao')),
    oficialidade VARCHAR(30) NOT NULL DEFAULT 'nao_aplicavel' CHECK (oficialidade IN ('nao_aplicavel', 'nao_oficial', 'informado_oficial', 'verificado')),
    protocolo VARCHAR(80),
    distancia_m NUMERIC(12,3) CHECK (distancia_m IS NULL OR distancia_m > 0),
    provider VARCHAR(50),
    external_source_id VARCHAR(180),
    metadados JSONB NOT NULL DEFAULT '{}'::jsonb CHECK (jsonb_typeof(metadados) = 'object'),
    observacoes TEXT,
    excluido_progresso BOOLEAN NOT NULL DEFAULT FALSE,
    data_criacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    data_atualizacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT ck_benchmark_one_rm_exercicio CHECK (tipo <> 'one_rm' OR idexercicio IS NOT NULL OR referencia_nome_snapshot IS NOT NULL),
    CONSTRAINT ck_benchmark_distance_time_distancia CHECK (tipo <> 'distance_time' OR distancia_m IS NOT NULL),
    CONSTRAINT ck_benchmark_snapshot CHECK (referencia_nome_snapshot IS NULL OR length(trim(referencia_nome_snapshot)) BETWEEN 1 AND 160),
    CONSTRAINT ck_benchmark_provider CHECK ((provider IS NULL AND external_source_id IS NULL) OR (provider IS NOT NULL AND external_source_id IS NOT NULL))
);

CREATE INDEX IF NOT EXISTS ix_benchmarks_usuario_modalidade_tipo_data
    ON benchmarks_usuario (idusuario, idmodalidade, tipo, data_resultado DESC, data_criacao DESC);

CREATE INDEX IF NOT EXISTS ix_benchmarks_usuario_exercicio_tipo_data
    ON benchmarks_usuario (idusuario, idexercicio, tipo, data_resultado DESC, data_criacao DESC)
    WHERE idexercicio IS NOT NULL;

CREATE INDEX IF NOT EXISTS ix_benchmarks_usuario_tipo_distancia_data
    ON benchmarks_usuario (idusuario, tipo, distancia_m, data_resultado DESC, data_criacao DESC)
    WHERE distancia_m IS NOT NULL;

CREATE INDEX IF NOT EXISTS ix_benchmarks_external_source
    ON benchmarks_usuario (provider, external_source_id)
    WHERE provider IS NOT NULL AND external_source_id IS NOT NULL;

COMMIT;
