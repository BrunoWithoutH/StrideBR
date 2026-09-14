BEGIN;

SET search_path TO stridebr, public;

ALTER TABLE treinos_agendados_exercicios
    ADD COLUMN IF NOT EXISTS bloco VARCHAR(40),
    ADD COLUMN IF NOT EXISTS cluster VARCHAR(80);

COMMIT;
