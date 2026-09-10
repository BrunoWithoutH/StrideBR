SET search_path TO stridebr, public;

CREATE TABLE IF NOT EXISTS integracao_webhook_eventos (
    id BIGSERIAL PRIMARY KEY,
    provider VARCHAR(32) NOT NULL,
    subscription_id BIGINT NOT NULL,
    owner_external_id VARCHAR(190) NOT NULL,
    object_type VARCHAR(20) NOT NULL,
    object_id VARCHAR(190) NOT NULL,
    aspect_type VARCHAR(20) NOT NULL,
    event_time BIGINT NOT NULL,
    updates JSONB NOT NULL DEFAULT '{}'::jsonb,
    fingerprint CHAR(64) NOT NULL,
    signature_verified BOOLEAN NOT NULL DEFAULT FALSE,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    attempts SMALLINT NOT NULL DEFAULT 0,
    retry_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    processing_started_at TIMESTAMPTZ,
    processed_at TIMESTAMPTZ,
    last_error_code VARCHAR(64),
    CONSTRAINT uq_integracao_webhook_eventos_fingerprint UNIQUE (fingerprint),
    CONSTRAINT ck_integracao_webhook_eventos_provider CHECK (provider = 'strava'),
    CONSTRAINT ck_integracao_webhook_eventos_object CHECK (object_type IN ('activity', 'athlete')),
    CONSTRAINT ck_integracao_webhook_eventos_aspect CHECK (aspect_type IN ('create', 'update', 'delete')),
    CONSTRAINT ck_integracao_webhook_eventos_status CHECK (status IN ('pending', 'processing', 'complete', 'ignored', 'failed')),
    CONSTRAINT ck_integracao_webhook_eventos_attempts CHECK (attempts >= 0)
);

CREATE INDEX IF NOT EXISTS ix_integracao_webhook_eventos_due
ON integracao_webhook_eventos (provider, status, retry_at, id)
WHERE status IN ('pending', 'processing');
