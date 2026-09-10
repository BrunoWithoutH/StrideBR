BEGIN;

SET search_path TO stridebr, public;

CREATE TABLE IF NOT EXISTS api_sessoes (
    idsessao VARCHAR(21) PRIMARY KEY,
    idusuario VARCHAR(21) NOT NULL REFERENCES usuarios(idusuario) ON DELETE CASCADE,
    access_token_hash CHAR(64) NOT NULL UNIQUE,
    refresh_token_hash CHAR(64) NOT NULL UNIQUE,
    familia_sessao VARCHAR(21) NOT NULL,
    identificador_dispositivo VARCHAR(128),
    nome_dispositivo VARCHAR(120),
    plataforma VARCHAR(20) CHECK (plataforma IS NULL OR plataforma IN ('android', 'ios', 'web', 'other')),
    sessao_versao INTEGER NOT NULL DEFAULT 1 CHECK (sessao_versao > 0),
    criado_em TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    ultimo_uso_em TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    access_expira_em TIMESTAMPTZ NOT NULL,
    refresh_expira_em TIMESTAMPTZ NOT NULL,
    revogado_em TIMESTAMPTZ,
    rotacionado_de VARCHAR(21) REFERENCES api_sessoes(idsessao) ON DELETE SET NULL,
    CONSTRAINT ck_api_sessoes_expiracao CHECK (refresh_expira_em > access_expira_em)
);

CREATE INDEX IF NOT EXISTS ix_api_sessoes_usuario_ativas
    ON api_sessoes (idusuario, ultimo_uso_em DESC)
    WHERE revogado_em IS NULL;

CREATE INDEX IF NOT EXISTS ix_api_sessoes_refresh_ativas
    ON api_sessoes (refresh_token_hash, refresh_expira_em)
    WHERE revogado_em IS NULL;

COMMIT;
