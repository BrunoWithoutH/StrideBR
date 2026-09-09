BEGIN;
SET search_path TO stridebr, public;

DO $$
BEGIN
    IF to_regclass('stridebr.integracoes_usuario') IS NOT NULL THEN
        ALTER TABLE integracoes_usuario DROP CONSTRAINT IF EXISTS ck_integracoes_usuario_provedor;
        ALTER TABLE integracoes_usuario
            ADD CONSTRAINT ck_integracoes_usuario_provedor
            CHECK (provedor IN (
                'garmin', 'strava', 'polar', 'suunto', 'fitbit',
                'google_health', 'coros',
                'health_connect', 'apple_health', 'samsung_health'
            ));
    END IF;
END $$;

COMMIT;
