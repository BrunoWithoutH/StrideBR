BEGIN;

SET search_path TO stridebr, public;

ALTER TABLE sessoes_treino
    ADD COLUMN IF NOT EXISTS idmodalidade_origem VARCHAR(21);

ALTER TABLE sessoes_treino_exercicios
    ADD COLUMN IF NOT EXISTS bloco_snapshot VARCHAR(40),
    ADD COLUMN IF NOT EXISTS cluster_snapshot VARCHAR(80);

CREATE TABLE IF NOT EXISTS api_workout_idempotencias (
    idusuario VARCHAR(21) NOT NULL REFERENCES usuarios(idusuario) ON DELETE CASCADE,
    escopo VARCHAR(40) NOT NULL,
    chave_hash CHAR(64) NOT NULL,
    payload_hash CHAR(64) NOT NULL,
    recurso_id VARCHAR(120) NOT NULL,
    idregistro VARCHAR(21) REFERENCES registros_atividade(idregistro) ON DELETE SET NULL,
    data_criacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    PRIMARY KEY (idusuario, escopo, chave_hash)
);

CREATE INDEX IF NOT EXISTS ix_api_workout_idempotencias_registro
ON api_workout_idempotencias (idregistro)
WHERE idregistro IS NOT NULL;

COMMIT;
