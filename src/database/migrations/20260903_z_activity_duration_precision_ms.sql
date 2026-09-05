BEGIN;
SET search_path TO stridebr, public;

ALTER TABLE unidades_atividade
    DROP CONSTRAINT IF EXISTS unidades_atividade_duracao_segundos_check;

ALTER TABLE unidades_atividade
    ALTER COLUMN duracao_segundos TYPE NUMERIC(14,3)
    USING duracao_segundos::NUMERIC(14,3);

ALTER TABLE unidades_atividade
    ADD CONSTRAINT unidades_atividade_duracao_segundos_check
    CHECK (duracao_segundos IS NULL OR duracao_segundos >= 0);


COMMIT;
