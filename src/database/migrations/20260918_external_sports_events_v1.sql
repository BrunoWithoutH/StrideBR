BEGIN;
SET search_path TO stridebr, public;

ALTER TABLE eventos_esportivos
    ADD COLUMN IF NOT EXISTS origem VARCHAR(20) NOT NULL DEFAULT 'manual',
    ADD COLUMN IF NOT EXISTS sincronizacao_bloqueada BOOLEAN NOT NULL DEFAULT FALSE,
    ADD COLUMN IF NOT EXISTS seo_indexavel BOOLEAN NOT NULL DEFAULT TRUE,
    ADD COLUMN IF NOT EXISTS horario_informado BOOLEAN NOT NULL DEFAULT TRUE;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'ck_eventos_origem'
          AND conrelid = 'eventos_esportivos'::regclass
    ) THEN
        ALTER TABLE eventos_esportivos
            ADD CONSTRAINT ck_eventos_origem CHECK (origem IN ('manual', 'externo'));
    END IF;
END $$;

CREATE TABLE IF NOT EXISTS eventos_modalidades (
    idevento VARCHAR(21) NOT NULL REFERENCES eventos_esportivos(idevento) ON DELETE CASCADE,
    idmodalidade VARCHAR(21) NOT NULL REFERENCES modalidades(idmodalidade) ON DELETE CASCADE,
    principal BOOLEAN NOT NULL DEFAULT FALSE,
    ordem SMALLINT NOT NULL DEFAULT 1 CHECK (ordem BETWEEN 1 AND 100),
    data_criacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    PRIMARY KEY (idevento, idmodalidade)
);

CREATE UNIQUE INDEX IF NOT EXISTS ux_eventos_modalidades_principal
    ON eventos_modalidades (idevento)
    WHERE principal = TRUE;

CREATE INDEX IF NOT EXISTS ix_eventos_modalidades_modalidade
    ON eventos_modalidades (idmodalidade, idevento);

INSERT INTO eventos_modalidades (idevento, idmodalidade, principal, ordem)
SELECT idevento, idmodalidade, TRUE, 1
FROM eventos_esportivos
WHERE idmodalidade IS NOT NULL
ON CONFLICT (idevento, idmodalidade) DO NOTHING;

CREATE TABLE IF NOT EXISTS eventos_provas (
    idprova VARCHAR(21) PRIMARY KEY,
    idevento VARCHAR(21) NOT NULL REFERENCES eventos_esportivos(idevento) ON DELETE CASCADE,
    idmodalidade VARCHAR(21) REFERENCES modalidades(idmodalidade) ON DELETE SET NULL,
    nome VARCHAR(180) NOT NULL CHECK (length(trim(nome)) BETWEEN 1 AND 180),
    grupo VARCHAR(50),
    categoria VARCHAR(120),
    sexo VARCHAR(40),
    distancia_m NUMERIC(12,3),
    data_hora TIMESTAMPTZ,
    fase VARCHAR(80),
    ordem SMALLINT NOT NULL DEFAULT 1 CHECK (ordem BETWEEN 1 AND 1000),
    metadados JSONB NOT NULL DEFAULT '{}'::jsonb,
    data_criacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT ck_eventos_provas_distancia CHECK (distancia_m IS NULL OR distancia_m >= 0)
);

CREATE INDEX IF NOT EXISTS ix_eventos_provas_evento
    ON eventos_provas (idevento, ordem, idprova);

CREATE INDEX IF NOT EXISTS ix_eventos_provas_busca
    ON eventos_provas USING gin (to_tsvector('simple', nome));

CREATE TABLE IF NOT EXISTS eventos_fontes_externas (
    idvinculo VARCHAR(21) PRIMARY KEY,
    idevento VARCHAR(21) NOT NULL REFERENCES eventos_esportivos(idevento) ON DELETE CASCADE,
    provider VARCHAR(40) NOT NULL,
    identity_key VARCHAR(190) NOT NULL,
    external_id VARCHAR(190),
    source_url TEXT NOT NULL CHECK (source_url ~* '^https://'),
    first_seen_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    last_seen_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    last_fetched_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    content_hash VARCHAR(64),
    sync_misses INTEGER NOT NULL DEFAULT 0 CHECK (sync_misses >= 0),
    stale_since TIMESTAMPTZ,
    metadados JSONB NOT NULL DEFAULT '{}'::jsonb,
    data_atualizacao TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE UNIQUE INDEX IF NOT EXISTS ux_eventos_fontes_externas_provider_identity
    ON eventos_fontes_externas (provider, identity_key);

CREATE INDEX IF NOT EXISTS ix_eventos_fontes_externas_provider_id
    ON eventos_fontes_externas (provider, external_id)
    WHERE external_id IS NOT NULL;

CREATE INDEX IF NOT EXISTS ix_eventos_fontes_externas_evento
    ON eventos_fontes_externas (idevento, provider);

CREATE INDEX IF NOT EXISTS ix_eventos_fontes_externas_seen
    ON eventos_fontes_externas (provider, last_seen_at DESC);

CREATE TABLE IF NOT EXISTS eventos_provedores_sync (
    provider VARCHAR(40) PRIMARY KEY,
    enabled BOOLEAN NOT NULL DEFAULT FALSE,
    auto_publish BOOLEAN NOT NULL DEFAULT FALSE,
    compliance_status VARCHAR(20) NOT NULL DEFAULT 'pendente',
    last_attempt_at TIMESTAMPTZ,
    last_success_at TIMESTAMPTZ,
    last_error_code VARCHAR(80),
    consecutive_failures INTEGER NOT NULL DEFAULT 0 CHECK (consecutive_failures >= 0),
    etag TEXT,
    last_modified TEXT,
    next_sync_at TIMESTAMPTZ,
    stats JSONB NOT NULL DEFAULT '{}'::jsonb,
    data_atualizacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT ck_eventos_provider_compliance CHECK (compliance_status IN ('pendente', 'aprovado', 'bloqueado'))
);

INSERT INTO eventos_provedores_sync (provider, enabled, auto_publish, compliance_status)
VALUES
    ('faergs', FALSE, FALSE, 'pendente'),
    ('cbat', FALSE, FALSE, 'pendente'),
    ('cbc', FALSE, FALSE, 'pendente'),
    ('fgc', FALSE, FALSE, 'pendente'),
    ('cbtri', FALSE, FALSE, 'pendente')
ON CONFLICT (provider) DO NOTHING;

COMMIT;
