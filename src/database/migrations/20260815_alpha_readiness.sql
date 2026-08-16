BEGIN;
SET search_path TO stridebr, public;

ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS email_verificado_em TIMESTAMPTZ;
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS termos_versao VARCHAR(60);
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS privacidade_versao VARCHAR(60);
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS termos_aceitos_em TIMESTAMPTZ;
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS bloqueado_em TIMESTAMPTZ;
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS bloqueado_por VARCHAR(21) REFERENCES usuarios(idusuario) ON DELETE SET NULL;
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS bloqueio_motivo TEXT;
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS sessao_versao INTEGER NOT NULL DEFAULT 1;

UPDATE usuarios
SET email_verificado_em = COALESCE(email_verificado_em, CASE WHEN verificado THEN dataregistrousuario ELSE NULL END)
WHERE verificado = TRUE;

CREATE TABLE IF NOT EXISTS auth_tokens (
    idtoken VARCHAR(21) PRIMARY KEY,
    idusuario VARCHAR(21) NOT NULL REFERENCES usuarios(idusuario) ON DELETE CASCADE,
    tipo VARCHAR(24) NOT NULL CHECK (tipo IN ('verificar_email', 'redefinir_senha')),
    token_hash CHAR(64) NOT NULL UNIQUE,
    email_destino VARCHAR(255) NOT NULL,
    criado_em TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    expira_em TIMESTAMPTZ NOT NULL,
    usado_em TIMESTAMPTZ,
    solicitacao_ip INET
);
CREATE INDEX IF NOT EXISTS ix_auth_tokens_usuario_tipo ON auth_tokens (idusuario, tipo, criado_em DESC);
CREATE INDEX IF NOT EXISTS ix_auth_tokens_expira ON auth_tokens (expira_em) WHERE usado_em IS NULL;

CREATE TABLE IF NOT EXISTS feedbacks (
    idfeedback VARCHAR(21) PRIMARY KEY,
    idusuario VARCHAR(21) REFERENCES usuarios(idusuario) ON DELETE SET NULL,
    anonimo BOOLEAN NOT NULL DEFAULT FALSE,
    tipo VARCHAR(12) NOT NULL DEFAULT 'outro' CHECK (tipo IN ('bug', 'ideia', 'ux', 'elogio', 'outro')),
    titulo VARCHAR(140) NOT NULL CHECK (length(trim(titulo)) > 0),
    mensagem TEXT NOT NULL CHECK (length(trim(mensagem)) > 0),
    pagina TEXT,
    status VARCHAR(14) NOT NULL DEFAULT 'novo' CHECK (status IN ('novo', 'lendo', 'planejado', 'resolvido', 'arquivado')),
    prioridade VARCHAR(10) NOT NULL DEFAULT 'normal' CHECK (prioridade IN ('baixa', 'normal', 'alta')),
    notas_admin TEXT,
    user_agent VARCHAR(500),
    ip INET,
    criado_em TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    atualizado_em TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS ix_feedbacks_status_data ON feedbacks (status, criado_em DESC);
CREATE INDEX IF NOT EXISTS ix_feedbacks_usuario_data ON feedbacks (idusuario, criado_em DESC);

CREATE TABLE IF NOT EXISTS convites_alpha (
    idconvite VARCHAR(21) PRIMARY KEY,
    codigo_hash CHAR(64) NOT NULL UNIQUE,
    prefixo VARCHAR(12) NOT NULL,
    usos_maximos INTEGER NOT NULL DEFAULT 1 CHECK (usos_maximos > 0),
    usos INTEGER NOT NULL DEFAULT 0 CHECK (usos >= 0),
    ativo BOOLEAN NOT NULL DEFAULT TRUE,
    expira_em TIMESTAMPTZ,
    criado_por VARCHAR(21) REFERENCES usuarios(idusuario) ON DELETE SET NULL,
    criado_em TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS ix_convites_alpha_ativos ON convites_alpha (ativo, expira_em, criado_em DESC);

INSERT INTO feature_flags (chave, ativo, descricao) VALUES
('registration.enabled', TRUE, 'Permite criar novas contas.'),
('registration.invite_only.enabled', FALSE, 'Exige um convite válido para criar conta.'),
('auth.email_verification.enabled', FALSE, 'Envia verificação de e-mail para novas contas.'),
('auth.email_verification.required', FALSE, 'Exige e-mail verificado para entrar.'),
('auth.password_reset.enabled', FALSE, 'Ativa recuperação de senha por e-mail.'),
('feedback.enabled', TRUE, 'Exibe o canal de feedback da alpha fechada.'),
('legal.alpha_notice.enabled', TRUE, 'Exibe aviso de versão alpha nas páginas legais.'),
('legal.reaccept.required', FALSE, 'Exige novo aceite quando a versão de Termos ou Privacidade mudar.')
ON CONFLICT (chave) DO NOTHING;

COMMIT;
