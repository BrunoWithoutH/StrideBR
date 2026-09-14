BEGIN;
SET search_path TO stridebr, public;

ALTER TABLE treinos_agendados ADD COLUMN IF NOT EXISTS idmodalidade VARCHAR(21);
ALTER TABLE treinos_agendados ADD COLUMN IF NOT EXISTS idtreino_modelo_origem VARCHAR(21);
ALTER TABLE treinos_agendados ADD COLUMN IF NOT EXISTS distancia_prevista_m NUMERIC(14,3);
ALTER TABLE treinos_agendados ADD COLUMN IF NOT EXISTS intensidade VARCHAR(80);
ALTER TABLE treinos_agendados ADD COLUMN IF NOT EXISTS objetivo VARCHAR(160);

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'fk_treinos_agendados_modalidade'
          AND conrelid = 'treinos_agendados'::regclass
    ) THEN
        ALTER TABLE treinos_agendados
        ADD CONSTRAINT fk_treinos_agendados_modalidade
        FOREIGN KEY (idmodalidade)
        REFERENCES modalidades(idmodalidade)
        ON DELETE SET NULL;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'fk_treinos_agendados_modelo_origem'
          AND conrelid = 'treinos_agendados'::regclass
    ) THEN
        ALTER TABLE treinos_agendados
        ADD CONSTRAINT fk_treinos_agendados_modelo_origem
        FOREIGN KEY (idtreino_modelo_origem)
        REFERENCES treinos_modelo(idtreino_modelo)
        ON DELETE SET NULL;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'ck_treinos_agendados_distancia_prevista'
          AND conrelid = 'treinos_agendados'::regclass
    ) THEN
        ALTER TABLE treinos_agendados
        ADD CONSTRAINT ck_treinos_agendados_distancia_prevista
        CHECK (distancia_prevista_m IS NULL OR distancia_prevista_m >= 0);
    END IF;
END $$;

CREATE INDEX IF NOT EXISTS ix_treinos_agendados_modelo_origem
ON treinos_agendados (idtreino_modelo_origem)
WHERE idtreino_modelo_origem IS NOT NULL;

COMMIT;
