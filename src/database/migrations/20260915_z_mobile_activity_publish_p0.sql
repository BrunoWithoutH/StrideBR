BEGIN;
SET search_path TO stridebr, public;

CREATE TABLE IF NOT EXISTS rotas_atividade (
    idrota VARCHAR(21) PRIMARY KEY,
    idregistro VARCHAR(21) NOT NULL UNIQUE REFERENCES registros_atividade(idregistro) ON DELETE CASCADE,
    modo VARCHAR(20) NOT NULL CHECK (modo IN ('desenho_livre', 'seguir_ruas', 'gps', 'importada')),
    coordenadas JSONB NOT NULL,
    distancia_metros NUMERIC(14,3) CHECK (distancia_metros IS NULL OR distancia_metros >= 0),
    ganho_elevacao_m NUMERIC(12,2),
    perda_elevacao_m NUMERIC(12,2),
    elevacao_min_m NUMERIC(10,2),
    elevacao_max_m NUMERIC(10,2),
    perfil_elevacao JSONB,
    fonte_elevacao VARCHAR(40),
    data_criacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    data_atualizacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT ck_rotas_elevacao_ganho_perda CHECK ((ganho_elevacao_m IS NULL OR ganho_elevacao_m >= 0) AND (perda_elevacao_m IS NULL OR perda_elevacao_m >= 0)),
    CONSTRAINT ck_rotas_elevacao_faixa CHECK (elevacao_min_m IS NULL OR elevacao_max_m IS NULL OR elevacao_max_m >= elevacao_min_m)
);

CREATE TABLE IF NOT EXISTS gravacoes_gps_web (
    idregistro VARCHAR(21) PRIMARY KEY REFERENCES registros_atividade(idregistro) ON DELETE CASCADE,
    chave_gravacao VARCHAR(80) NOT NULL UNIQUE,
    iniciado_em TIMESTAMPTZ NOT NULL,
    finalizado_em TIMESTAMPTZ NOT NULL,
    distancia_medida_m NUMERIC(12,3),
    distancia_final_m NUMERIC(12,3),
    duracao_s INTEGER NOT NULL CHECK (duracao_s >= 0),
    pontos_recebidos INTEGER NOT NULL DEFAULT 0 CHECK (pontos_recebidos >= 0),
    pontos_aceitos INTEGER NOT NULL DEFAULT 0 CHECK (pontos_aceitos >= 0),
    pontos_rejeitados INTEGER NOT NULL DEFAULT 0 CHECK (pontos_rejeitados >= 0),
    precisao_media_m NUMERIC(10,2),
    precisao_melhor_m NUMERIC(10,2),
    precisao_pior_m NUMERIC(10,2),
    lacunas_visibilidade INTEGER NOT NULL DEFAULT 0 CHECK (lacunas_visibilidade >= 0),
    tipo_meta VARCHAR(16) CHECK (tipo_meta IS NULL OR tipo_meta IN ('distance','time')),
    valor_meta NUMERIC(14,3),
    finalizado_por_meta BOOLEAN NOT NULL DEFAULT FALSE,
    usuario_ajustou BOOLEAN NOT NULL DEFAULT FALSE,
    criado_em TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS ix_gravacoes_gps_web_iniciado
ON gravacoes_gps_web (iniciado_em DESC);

ALTER TABLE gravacoes_gps_web
ALTER COLUMN duracao_s TYPE NUMERIC(12,3)
USING duracao_s::numeric;

ALTER TABLE rotas_atividade
ADD COLUMN IF NOT EXISTS pontos_metadata JSONB;

ALTER TABLE rotas_atividade
DROP CONSTRAINT IF EXISTS ck_rotas_pontos_metadata;

ALTER TABLE rotas_atividade
ADD CONSTRAINT ck_rotas_pontos_metadata
CHECK (pontos_metadata IS NULL OR jsonb_typeof(pontos_metadata) = 'array');

CREATE TABLE IF NOT EXISTS activity_stream_bundles (
    idbundle VARCHAR(21) PRIMARY KEY,
    idregistro VARCHAR(21) NOT NULL UNIQUE REFERENCES registros_atividade(idregistro) ON DELETE CASCADE,
    schema_version SMALLINT NOT NULL DEFAULT 1 CHECK (schema_version = 1),
    source VARCHAR(40),
    source_metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
    idempotency_key_hash CHAR(64),
    payload_hash CHAR(64) NOT NULL,
    sample_count INTEGER NOT NULL DEFAULT 0 CHECK (sample_count BETWEEN 0 AND 50000),
    available_streams JSONB NOT NULL DEFAULT '[]'::jsonb,
    data_criacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    data_atualizacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT ck_activity_stream_bundle_source_metadata CHECK (jsonb_typeof(source_metadata) = 'object'),
    CONSTRAINT ck_activity_stream_bundle_available_streams CHECK (jsonb_typeof(available_streams) = 'array')
);

CREATE UNIQUE INDEX IF NOT EXISTS ux_activity_stream_bundle_idempotency
ON activity_stream_bundles (idregistro, idempotency_key_hash)
WHERE idempotency_key_hash IS NOT NULL;

CREATE TABLE IF NOT EXISTS activity_stream_samples (
    idbundle VARCHAR(21) NOT NULL REFERENCES activity_stream_bundles(idbundle) ON DELETE CASCADE,
    sample_index INTEGER NOT NULL CHECK (sample_index >= 0),
    elapsed_ms BIGINT NOT NULL CHECK (elapsed_ms >= 0),
    moving_ms BIGINT CHECK (moving_ms IS NULL OR moving_ms >= 0),
    distance_m NUMERIC(14,3) CHECK (distance_m IS NULL OR distance_m >= 0),
    speed_mps NUMERIC(10,5) CHECK (speed_mps IS NULL OR speed_mps >= 0),
    heart_rate_bpm SMALLINT CHECK (heart_rate_bpm IS NULL OR heart_rate_bpm BETWEEN 20 AND 260),
    altitude_m NUMERIC(10,3),
    grade_pct NUMERIC(8,3) CHECK (grade_pct IS NULL OR grade_pct BETWEEN -100 AND 100),
    cadence NUMERIC(8,2) CHECK (cadence IS NULL OR cadence >= 0),
    power_w NUMERIC(10,2) CHECK (power_w IS NULL OR power_w >= 0),
    temperature_c NUMERIC(6,2) CHECK (temperature_c IS NULL OR temperature_c BETWEEN -100 AND 100),
    horizontal_accuracy_m NUMERIC(9,3) CHECK (horizontal_accuracy_m IS NULL OR horizontal_accuracy_m >= 0),
    vertical_accuracy_m NUMERIC(9,3) CHECK (vertical_accuracy_m IS NULL OR vertical_accuracy_m >= 0),
    speed_accuracy_mps NUMERIC(9,4) CHECK (speed_accuracy_mps IS NULL OR speed_accuracy_mps >= 0),
    bearing_deg NUMERIC(7,3) CHECK (bearing_deg IS NULL OR (bearing_deg >= 0 AND bearing_deg < 360)),
    gap_before_ms BIGINT CHECK (gap_before_ms IS NULL OR gap_before_ms >= 0),
    route_point_index INTEGER CHECK (route_point_index IS NULL OR route_point_index >= 0),
    sample_source VARCHAR(40),
    PRIMARY KEY (idbundle, sample_index)
);

CREATE INDEX IF NOT EXISTS ix_activity_stream_samples_elapsed
ON activity_stream_samples (idbundle, elapsed_ms);

CREATE INDEX IF NOT EXISTS ix_activity_stream_samples_distance
ON activity_stream_samples (idbundle, distance_m)
WHERE distance_m IS NOT NULL;

CREATE TABLE IF NOT EXISTS activity_laps (
    idlap VARCHAR(21) PRIMARY KEY,
    idregistro VARCHAR(21) NOT NULL REFERENCES registros_atividade(idregistro) ON DELETE CASCADE,
    lap_order INTEGER NOT NULL CHECK (lap_order > 0),
    origin VARCHAR(20) NOT NULL DEFAULT 'manual' CHECK (origin IN ('manual', 'import')),
    start_elapsed_ms BIGINT NOT NULL CHECK (start_elapsed_ms >= 0),
    end_elapsed_ms BIGINT CHECK (end_elapsed_ms IS NULL OR end_elapsed_ms >= start_elapsed_ms),
    start_moving_ms BIGINT CHECK (start_moving_ms IS NULL OR start_moving_ms >= 0),
    end_moving_ms BIGINT CHECK (end_moving_ms IS NULL OR (start_moving_ms IS NULL OR end_moving_ms >= start_moving_ms)),
    start_distance_m NUMERIC(14,3) CHECK (start_distance_m IS NULL OR start_distance_m >= 0),
    end_distance_m NUMERIC(14,3) CHECK (end_distance_m IS NULL OR (start_distance_m IS NULL OR end_distance_m >= start_distance_m)),
    source VARCHAR(40),
    source_metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
    data_criacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT ck_activity_lap_source_metadata CHECK (jsonb_typeof(source_metadata) = 'object'),
    UNIQUE (idregistro, origin, lap_order)
);

CREATE INDEX IF NOT EXISTS ix_activity_laps_record_order
ON activity_laps (idregistro, lap_order);

CREATE TABLE IF NOT EXISTS activity_analysis_cache (
    idregistro VARCHAR(21) PRIMARY KEY REFERENCES registros_atividade(idregistro) ON DELETE CASCADE,
    analysis_version SMALLINT NOT NULL CHECK (analysis_version > 0),
    input_fingerprint CHAR(64) NOT NULL,
    payload JSONB NOT NULL,
    data_calculo TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT ck_activity_analysis_payload CHECK (jsonb_typeof(payload) = 'object')
);

COMMIT;
