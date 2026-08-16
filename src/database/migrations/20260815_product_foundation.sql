BEGIN;
SET search_path TO stridebr, public;

ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS nome_exibicao VARCHAR(120);
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS username VARCHAR(40);
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS papelusuario VARCHAR(12) NOT NULL DEFAULT 'user';
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS preferenciasusuario JSONB NOT NULL DEFAULT '{}'::jsonb;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = 'stridebr'
          AND table_name = 'usuarios'
          AND column_name = 'onboarding_concluido'
    ) THEN
        ALTER TABLE usuarios ADD COLUMN onboarding_concluido BOOLEAN NOT NULL DEFAULT FALSE;
        UPDATE usuarios SET onboarding_concluido = TRUE;
    END IF;
END $$;

UPDATE usuarios
SET nome_exibicao = COALESCE(NULLIF(trim(nome_exibicao), ''), nomeusuario)
WHERE nome_exibicao IS NULL OR trim(nome_exibicao) = '';

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'ck_usuarios_papelusuario'
          AND conrelid = 'usuarios'::regclass
    ) THEN
        ALTER TABLE usuarios
        ADD CONSTRAINT ck_usuarios_papelusuario
        CHECK (papelusuario IN ('user', 'moderator', 'admin', 'owner'));
    END IF;
END $$;

CREATE UNIQUE INDEX IF NOT EXISTS ux_usuarios_username
ON usuarios (lower(username))
WHERE username IS NOT NULL;

ALTER TABLE exercicios ADD COLUMN IF NOT EXISTS imagem_url TEXT;
ALTER TABLE exercicios ADD COLUMN IF NOT EXISTS video_url TEXT;

CREATE TABLE IF NOT EXISTS amizades (
    idamizade VARCHAR(21) PRIMARY KEY,
    idusuario_solicitante VARCHAR(21) NOT NULL REFERENCES usuarios(idusuario) ON DELETE CASCADE,
    idusuario_destino VARCHAR(21) NOT NULL REFERENCES usuarios(idusuario) ON DELETE CASCADE,
    status VARCHAR(12) NOT NULL DEFAULT 'pendente' CHECK (status IN ('pendente', 'aceita', 'recusada', 'bloqueada')),
    data_criacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    data_atualizacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT ck_amizade_usuarios_diferentes CHECK (idusuario_solicitante <> idusuario_destino)
);

CREATE UNIQUE INDEX IF NOT EXISTS ux_amizades_par
ON amizades (
    LEAST(idusuario_solicitante, idusuario_destino),
    GREATEST(idusuario_solicitante, idusuario_destino)
);
CREATE INDEX IF NOT EXISTS ix_amizades_destino_status ON amizades (idusuario_destino, status, data_atualizacao DESC);
CREATE INDEX IF NOT EXISTS ix_amizades_solicitante_status ON amizades (idusuario_solicitante, status, data_atualizacao DESC);

CREATE TABLE IF NOT EXISTS cronograma_membros (
    idcronograma VARCHAR(21) NOT NULL REFERENCES cronogramas(idcronograma) ON DELETE CASCADE,
    idusuario VARCHAR(21) NOT NULL REFERENCES usuarios(idusuario) ON DELETE CASCADE,
    papel VARCHAR(10) NOT NULL DEFAULT 'viewer' CHECK (papel IN ('owner', 'editor', 'viewer')),
    data_criacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    PRIMARY KEY (idcronograma, idusuario)
);

INSERT INTO cronograma_membros (idcronograma, idusuario, papel)
SELECT idcronograma, idusuario, 'owner'
FROM cronogramas
ON CONFLICT (idcronograma, idusuario) DO NOTHING;

CREATE TABLE IF NOT EXISTS cronograma_compartilhamentos (
    idcompartilhamento VARCHAR(21) PRIMARY KEY,
    idcronograma_origem VARCHAR(21) REFERENCES cronogramas(idcronograma) ON DELETE SET NULL,
    idusuario_origem VARCHAR(21) NOT NULL REFERENCES usuarios(idusuario) ON DELETE CASCADE,
    idusuario_destino VARCHAR(21) NOT NULL REFERENCES usuarios(idusuario) ON DELETE CASCADE,
    tipo VARCHAR(12) NOT NULL CHECK (tipo IN ('snapshot', 'sincronizado')),
    snapshot JSONB,
    status VARCHAR(12) NOT NULL DEFAULT 'pendente' CHECK (status IN ('pendente', 'aceito', 'recusado', 'revogado')),
    data_criacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    data_atualizacao TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS ix_compartilhamentos_destino ON cronograma_compartilhamentos (idusuario_destino, status, data_criacao DESC);

CREATE TABLE IF NOT EXISTS sessoes_treino (
    idsessao VARCHAR(21) PRIMARY KEY,
    idusuario VARCHAR(21) NOT NULL REFERENCES usuarios(idusuario) ON DELETE CASCADE,
    idcronograma_origem VARCHAR(21) REFERENCES cronogramas(idcronograma) ON DELETE SET NULL,
    idtreino_origem VARCHAR(21) REFERENCES treinos_cronograma(idtreino) ON DELETE SET NULL,
    idregistro_atividade VARCHAR(21) REFERENCES registros_atividade(idregistro) ON DELETE SET NULL,
    titulo_snapshot VARCHAR(120) NOT NULL,
    status VARCHAR(12) NOT NULL DEFAULT 'ativo' CHECK (status IN ('ativo', 'concluido', 'cancelado')),
    data_inicio TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    data_fim TIMESTAMPTZ,
    observacoes TEXT,
    data_criacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    data_atualizacao TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE UNIQUE INDEX IF NOT EXISTS ux_sessoes_treino_ativa_usuario
ON sessoes_treino (idusuario)
WHERE status = 'ativo';
CREATE INDEX IF NOT EXISTS ix_sessoes_treino_usuario_data ON sessoes_treino (idusuario, data_inicio DESC);

CREATE TABLE IF NOT EXISTS sessoes_treino_exercicios (
    idsessao_exercicio VARCHAR(21) PRIMARY KEY,
    idsessao VARCHAR(21) NOT NULL REFERENCES sessoes_treino(idsessao) ON DELETE CASCADE,
    idexercicio VARCHAR(21) REFERENCES exercicios(idexercicio) ON DELETE SET NULL,
    nome_snapshot VARCHAR(120) NOT NULL,
    series_planejadas INTEGER CHECK (series_planejadas IS NULL OR series_planejadas > 0),
    repeticoes_snapshot VARCHAR(40),
    carga_snapshot VARCHAR(40),
    descanso_snapshot VARCHAR(40),
    observacoes_snapshot TEXT,
    ordem INTEGER NOT NULL CHECK (ordem > 0),
    concluido BOOLEAN NOT NULL DEFAULT FALSE
);

CREATE UNIQUE INDEX IF NOT EXISTS ux_sessao_exercicios_ordem ON sessoes_treino_exercicios (idsessao, ordem);

CREATE TABLE IF NOT EXISTS sessoes_treino_series (
    idserie VARCHAR(21) PRIMARY KEY,
    idsessao_exercicio VARCHAR(21) NOT NULL REFERENCES sessoes_treino_exercicios(idsessao_exercicio) ON DELETE CASCADE,
    numero INTEGER NOT NULL CHECK (numero > 0),
    concluida BOOLEAN NOT NULL DEFAULT FALSE,
    repeticoes_realizadas VARCHAR(40),
    carga_realizada VARCHAR(40),
    data_conclusao TIMESTAMPTZ,
    UNIQUE (idsessao_exercicio, numero)
);

CREATE TABLE IF NOT EXISTS feature_flags (
    chave VARCHAR(80) PRIMARY KEY,
    ativo BOOLEAN NOT NULL DEFAULT FALSE,
    descricao TEXT,
    data_atualizacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    atualizado_por VARCHAR(21) REFERENCES usuarios(idusuario) ON DELETE SET NULL
);

INSERT INTO feature_flags (chave, ativo, descricao) VALUES
('friends.enabled', TRUE, 'Ativa a área de amigos.'),
('workout_sessions.enabled', TRUE, 'Ativa execução de treinos a partir dos cronogramas.'),
('synced_schedules.enabled', FALSE, 'Ativa cronogramas sincronizados entre amigos.'),
('public_profiles.enabled', TRUE, 'Ativa perfis públicos por username.'),
('exercise_media.enabled', TRUE, 'Ativa imagem e vídeo por URL nos exercícios.'),
('access_logs.enabled', TRUE, 'Registra acessos autenticados por até 90 dias para segurança e métricas.')
ON CONFLICT (chave) DO NOTHING;

CREATE TABLE IF NOT EXISTS acessos_usuario (
    idacesso BIGSERIAL PRIMARY KEY,
    idusuario VARCHAR(21) REFERENCES usuarios(idusuario) ON DELETE CASCADE,
    ip INET,
    user_agent VARCHAR(500),
    data_acesso TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS ix_acessos_usuario_data ON acessos_usuario (data_acesso DESC);
CREATE INDEX IF NOT EXISTS ix_acessos_usuario_usuario_data ON acessos_usuario (idusuario, data_acesso DESC);

CREATE TABLE IF NOT EXISTS admin_audit_log (
    idlog BIGSERIAL PRIMARY KEY,
    idator VARCHAR(21) REFERENCES usuarios(idusuario) ON DELETE SET NULL,
    acao VARCHAR(120) NOT NULL,
    alvo_tipo VARCHAR(80),
    alvo_id VARCHAR(120),
    detalhes JSONB NOT NULL DEFAULT '{}'::jsonb,
    ip INET,
    data_criacao TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS ix_admin_audit_data ON admin_audit_log (data_criacao DESC);
CREATE INDEX IF NOT EXISTS ix_admin_audit_ator ON admin_audit_log (idator, data_criacao DESC);

COMMIT;
