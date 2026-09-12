BEGIN;

SET search_path TO stridebr, public;

CREATE TABLE IF NOT EXISTS competicoes_usuario (
    idcompeticao VARCHAR(21) PRIMARY KEY,
    idusuario VARCHAR(21) NOT NULL REFERENCES usuarios(idusuario) ON DELETE CASCADE,
    nome VARCHAR(160) NOT NULL CHECK (length(trim(nome)) BETWEEN 3 AND 160),
    data_inicio DATE NOT NULL,
    data_fim DATE,
    idmodalidade_principal VARCHAR(21) REFERENCES modalidades(idmodalidade) ON DELETE SET NULL,
    idevento VARCHAR(21) REFERENCES eventos_esportivos(idevento) ON DELETE SET NULL,
    tipo VARCHAR(40),
    organizador VARCHAR(160),
    local_nome VARCHAR(160),
    cidade VARCHAR(100),
    estado VARCHAR(80),
    pais VARCHAR(80),
    nivel VARCHAR(80),
    oficialidade VARCHAR(24) NOT NULL DEFAULT 'nao_informada'
        CHECK (oficialidade IN ('nao_informada', 'nao_oficial', 'informado_oficial', 'verificado')),
    observacoes TEXT,
    status VARCHAR(20) NOT NULL DEFAULT 'planejada'
        CHECK (status IN ('planejada', 'realizada', 'cancelada')),
    origem VARCHAR(20) NOT NULL DEFAULT 'manual'
        CHECK (origem IN ('manual', 'catalogo', 'importacao', 'api')),
    data_criacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    data_atualizacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT ck_competicoes_usuario_datas CHECK (data_fim IS NULL OR data_fim >= data_inicio),
    CONSTRAINT ck_competicoes_usuario_manual_verified CHECK (NOT (origem = 'manual' AND oficialidade = 'verificado')),
    CONSTRAINT ck_competicoes_usuario_tipo CHECK (tipo IS NULL OR length(trim(tipo)) BETWEEN 1 AND 40),
    CONSTRAINT ck_competicoes_usuario_nivel CHECK (nivel IS NULL OR length(trim(nivel)) BETWEEN 1 AND 80)
);

CREATE INDEX IF NOT EXISTS ix_competicoes_usuario_data
    ON competicoes_usuario (idusuario, data_inicio DESC, idcompeticao);
CREATE INDEX IF NOT EXISTS ix_competicoes_usuario_status_data
    ON competicoes_usuario (idusuario, status, data_inicio DESC);
CREATE INDEX IF NOT EXISTS ix_competicoes_usuario_evento
    ON competicoes_usuario (idevento, idusuario)
    WHERE idevento IS NOT NULL;
CREATE INDEX IF NOT EXISTS ix_competicoes_usuario_modalidade
    ON competicoes_usuario (idusuario, idmodalidade_principal, data_inicio DESC)
    WHERE idmodalidade_principal IS NOT NULL;

ALTER TABLE registros_atividade
    ADD COLUMN IF NOT EXISTS idcompeticao VARCHAR(21) REFERENCES competicoes_usuario(idcompeticao) ON DELETE SET NULL;

CREATE INDEX IF NOT EXISTS ix_registros_atividade_competicao
    ON registros_atividade (idcompeticao, data_inicio, idregistro)
    WHERE idcompeticao IS NOT NULL;

ALTER TABLE benchmarks_usuario
    ADD COLUMN IF NOT EXISTS idcompeticao VARCHAR(21) REFERENCES competicoes_usuario(idcompeticao) ON DELETE SET NULL;

CREATE INDEX IF NOT EXISTS ix_benchmarks_usuario_competicao
    ON benchmarks_usuario (idcompeticao, data_resultado, idbenchmark)
    WHERE idcompeticao IS NOT NULL;

COMMIT;
