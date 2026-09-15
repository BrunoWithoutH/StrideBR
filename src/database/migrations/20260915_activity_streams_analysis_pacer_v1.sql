BEGIN;
SET search_path TO stridebr, public;

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

CREATE TABLE IF NOT EXISTS zone_profiles (
    idprofile VARCHAR(21) PRIMARY KEY,
    idusuario VARCHAR(21) NOT NULL REFERENCES usuarios(idusuario) ON DELETE CASCADE,
    idmodalidade VARCHAR(21) REFERENCES modalidades(idmodalidade) ON DELETE CASCADE,
    profile_type VARCHAR(20) NOT NULL CHECK (profile_type IN ('heart_rate', 'pace')),
    name VARCHAR(80) NOT NULL CHECK (length(trim(name)) BETWEEN 1 AND 80),
    unit VARCHAR(20) NOT NULL CHECK (unit IN ('bpm', 's_per_km')),
    is_default BOOLEAN NOT NULL DEFAULT FALSE,
    data_criacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    data_atualizacao TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE UNIQUE INDEX IF NOT EXISTS ux_zone_profiles_default_global
ON zone_profiles (idusuario, profile_type)
WHERE is_default = TRUE AND idmodalidade IS NULL;

CREATE UNIQUE INDEX IF NOT EXISTS ux_zone_profiles_default_sport
ON zone_profiles (idusuario, idmodalidade, profile_type)
WHERE is_default = TRUE AND idmodalidade IS NOT NULL;

CREATE INDEX IF NOT EXISTS ix_zone_profiles_user_type
ON zone_profiles (idusuario, profile_type, idmodalidade);

CREATE TABLE IF NOT EXISTS zone_profile_ranges (
    idrange VARCHAR(21) PRIMARY KEY,
    idprofile VARCHAR(21) NOT NULL REFERENCES zone_profiles(idprofile) ON DELETE CASCADE,
    zone_order SMALLINT NOT NULL CHECK (zone_order BETWEEN 1 AND 20),
    code VARCHAR(20) NOT NULL CHECK (length(trim(code)) BETWEEN 1 AND 20),
    label VARCHAR(80),
    min_value NUMERIC(12,3),
    max_value NUMERIC(12,3),
    data_criacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT ck_zone_range_bounds CHECK (min_value IS NULL OR max_value IS NULL OR max_value > min_value),
    UNIQUE (idprofile, zone_order),
    UNIQUE (idprofile, code)
);

CREATE TABLE IF NOT EXISTS pacer_plans (
    idplan VARCHAR(21) PRIMARY KEY,
    idusuario VARCHAR(21) NOT NULL REFERENCES usuarios(idusuario) ON DELETE CASCADE,
    idmodalidade VARCHAR(21) NOT NULL REFERENCES modalidades(idmodalidade) ON DELETE RESTRICT,
    name VARCHAR(120) NOT NULL CHECK (length(trim(name)) BETWEEN 1 AND 120),
    strategy VARCHAR(24) NOT NULL CHECK (strategy IN ('even', 'negative_split', 'positive_split', 'custom')),
    target_distance_m NUMERIC(14,3) NOT NULL CHECK (target_distance_m > 0),
    target_time_s NUMERIC(12,3) NOT NULL CHECK (target_time_s > 0),
    target_average_pace_s_per_km NUMERIC(10,3) NOT NULL CHECK (target_average_pace_s_per_km > 0),
    default_tolerance_s_per_km NUMERIC(8,3) NOT NULL DEFAULT 10 CHECK (default_tolerance_s_per_km BETWEEN 0 AND 600),
    terrain_adjustment_mode VARCHAR(20) NOT NULL DEFAULT 'none' CHECK (terrain_adjustment_mode = 'none'),
    guidance_rules JSONB NOT NULL DEFAULT '{}'::jsonb,
    status VARCHAR(16) NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'archived')),
    version INTEGER NOT NULL DEFAULT 1 CHECK (version > 0),
    data_criacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    data_atualizacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT ck_pacer_guidance_rules CHECK (jsonb_typeof(guidance_rules) = 'object')
);

CREATE INDEX IF NOT EXISTS ix_pacer_plans_user_status
ON pacer_plans (idusuario, status, data_atualizacao DESC);

CREATE TABLE IF NOT EXISTS pacer_plan_segments (
    idsegment VARCHAR(21) PRIMARY KEY,
    idplan VARCHAR(21) NOT NULL REFERENCES pacer_plans(idplan) ON DELETE CASCADE,
    segment_order INTEGER NOT NULL CHECK (segment_order > 0),
    basis VARCHAR(16) NOT NULL DEFAULT 'distance' CHECK (basis IN ('distance', 'time')),
    start_distance_m NUMERIC(14,3),
    end_distance_m NUMERIC(14,3),
    start_time_s NUMERIC(12,3),
    end_time_s NUMERIC(12,3),
    target_pace_s_per_km NUMERIC(10,3) CHECK (target_pace_s_per_km IS NULL OR target_pace_s_per_km > 0),
    tolerance_s_per_km NUMERIC(8,3) CHECK (tolerance_s_per_km IS NULL OR tolerance_s_per_km BETWEEN 0 AND 600),
    heart_rate_floor_bpm SMALLINT CHECK (heart_rate_floor_bpm IS NULL OR heart_rate_floor_bpm BETWEEN 20 AND 260),
    heart_rate_ceiling_bpm SMALLINT CHECK (heart_rate_ceiling_bpm IS NULL OR heart_rate_ceiling_bpm BETWEEN 20 AND 260),
    instruction_metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
    data_criacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT ck_pacer_segment_basis_values CHECK (
        (basis = 'distance' AND start_distance_m IS NOT NULL AND end_distance_m IS NOT NULL AND end_distance_m > start_distance_m AND start_time_s IS NULL AND end_time_s IS NULL)
        OR
        (basis = 'time' AND start_time_s IS NOT NULL AND end_time_s IS NOT NULL AND end_time_s > start_time_s AND start_distance_m IS NULL AND end_distance_m IS NULL)
    ),
    CONSTRAINT ck_pacer_segment_hr_bounds CHECK (heart_rate_floor_bpm IS NULL OR heart_rate_ceiling_bpm IS NULL OR heart_rate_ceiling_bpm > heart_rate_floor_bpm),
    CONSTRAINT ck_pacer_segment_instruction_metadata CHECK (jsonb_typeof(instruction_metadata) = 'object'),
    UNIQUE (idplan, segment_order)
);

ALTER TABLE treinos_agendados ADD COLUMN IF NOT EXISTS idpacerplan VARCHAR(21);
ALTER TABLE treinos_cronograma ADD COLUMN IF NOT EXISTS idpacerplan VARCHAR(21);

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'fk_treinos_agendados_pacer_plan'
          AND conrelid = 'treinos_agendados'::regclass
    ) THEN
        ALTER TABLE treinos_agendados
        ADD CONSTRAINT fk_treinos_agendados_pacer_plan
        FOREIGN KEY (idpacerplan) REFERENCES pacer_plans(idplan) ON DELETE SET NULL;
    END IF;
END $$;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'fk_treinos_cronograma_pacer_plan'
          AND conrelid = 'treinos_cronograma'::regclass
    ) THEN
        ALTER TABLE treinos_cronograma
        ADD CONSTRAINT fk_treinos_cronograma_pacer_plan
        FOREIGN KEY (idpacerplan) REFERENCES pacer_plans(idplan) ON DELETE SET NULL;
    END IF;
END $$;

COMMIT;
