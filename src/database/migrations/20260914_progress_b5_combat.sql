BEGIN;

SET search_path TO stridebr, public;

CREATE TABLE IF NOT EXISTS graduacoes_usuario (
    idgraduacao VARCHAR(21) PRIMARY KEY,
    idusuario VARCHAR(21) NOT NULL REFERENCES usuarios(idusuario) ON DELETE CASCADE,
    idmodalidade VARCHAR(21) NOT NULL REFERENCES modalidades(idmodalidade) ON DELETE RESTRICT,
    sistema VARCHAR(120),
    sistema_normalizado VARCHAR(120) NOT NULL DEFAULT '',
    graduacao VARCHAR(120) NOT NULL CHECK (length(trim(graduacao)) BETWEEN 1 AND 120),
    detalhe VARCHAR(120),
    data_graduacao DATE NOT NULL,
    emissor VARCHAR(160),
    observacoes TEXT,
    data_criacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    data_atualizacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT ck_graduacoes_usuario_sistema CHECK (sistema IS NULL OR length(trim(sistema)) BETWEEN 1 AND 120),
    CONSTRAINT ck_graduacoes_usuario_detalhe CHECK (detalhe IS NULL OR length(trim(detalhe)) BETWEEN 1 AND 120),
    CONSTRAINT ck_graduacoes_usuario_emissor CHECK (emissor IS NULL OR length(trim(emissor)) BETWEEN 1 AND 160)
);

CREATE INDEX IF NOT EXISTS ix_graduacoes_usuario_modalidade_data
    ON graduacoes_usuario (idusuario, idmodalidade, data_graduacao DESC, data_criacao DESC, idgraduacao DESC);
CREATE INDEX IF NOT EXISTS ix_graduacoes_usuario_sistema_data
    ON graduacoes_usuario (idusuario, idmodalidade, sistema_normalizado, data_graduacao DESC, data_criacao DESC, idgraduacao DESC);

CREATE TABLE IF NOT EXISTS tecnicas_usuario (
    idtecnica VARCHAR(21) PRIMARY KEY,
    idusuario VARCHAR(21) NOT NULL REFERENCES usuarios(idusuario) ON DELETE CASCADE,
    idmodalidade VARCHAR(21) NOT NULL REFERENCES modalidades(idmodalidade) ON DELETE RESTRICT,
    nome VARCHAR(160) NOT NULL CHECK (length(trim(nome)) BETWEEN 1 AND 160),
    nome_normalizado VARCHAR(160) NOT NULL CHECK (length(trim(nome_normalizado)) BETWEEN 1 AND 160),
    categoria_code VARCHAR(32),
    categoria_custom VARCHAR(100),
    estado VARCHAR(20) NOT NULL DEFAULT 'learning' CHECK (estado IN ('learning', 'practicing', 'consolidated', 'archived')),
    observacoes TEXT,
    archived_at TIMESTAMPTZ,
    data_criacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    data_atualizacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT ux_tecnicas_usuario_nome UNIQUE (idusuario, idmodalidade, nome_normalizado),
    CONSTRAINT ck_tecnicas_usuario_categoria CHECK (
        categoria_code IS NULL OR categoria_code IN ('striking','clinch','takedown','throw','guard','pass','sweep','submission','defense','control','movement','kata_form','weapon','other')
    ),
    CONSTRAINT ck_tecnicas_usuario_categoria_custom CHECK (categoria_custom IS NULL OR length(trim(categoria_custom)) BETWEEN 1 AND 100),
    CONSTRAINT ck_tecnicas_usuario_archive_state CHECK ((estado = 'archived') = (archived_at IS NOT NULL))
);

CREATE INDEX IF NOT EXISTS ix_tecnicas_usuario_modalidade_estado
    ON tecnicas_usuario (idusuario, idmodalidade, estado, nome_normalizado);

CREATE TABLE IF NOT EXISTS praticas_tecnica (
    idpratica VARCHAR(21) PRIMARY KEY,
    idusuario VARCHAR(21) NOT NULL REFERENCES usuarios(idusuario) ON DELETE CASCADE,
    idtecnica VARCHAR(21) NOT NULL REFERENCES tecnicas_usuario(idtecnica) ON DELETE CASCADE,
    data_pratica DATE NOT NULL,
    idregistro VARCHAR(21) REFERENCES registros_atividade(idregistro) ON DELETE SET NULL,
    origem VARCHAR(16) NOT NULL DEFAULT 'manual' CHECK (origem IN ('manual', 'activity')),
    observacoes TEXT,
    data_criacao TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS ix_praticas_tecnica_usuario_data
    ON praticas_tecnica (idusuario, data_pratica DESC, idpratica DESC);
CREATE INDEX IF NOT EXISTS ix_praticas_tecnica_tecnica_data
    ON praticas_tecnica (idtecnica, data_pratica DESC, idpratica DESC);
CREATE INDEX IF NOT EXISTS ix_praticas_tecnica_atividade
    ON praticas_tecnica (idregistro, idtecnica)
    WHERE idregistro IS NOT NULL;

COMMIT;
