BEGIN;
SET search_path TO stridebr, public;

CREATE TABLE IF NOT EXISTS aplicacoes_cronograma_treinador (
    idaplicacao VARCHAR(21) PRIMARY KEY,
    idvinculo VARCHAR(21) REFERENCES vinculos_treinador_atleta(idvinculo) ON DELETE SET NULL,
    idtreinador VARCHAR(21) NOT NULL REFERENCES usuarios(idusuario) ON DELETE CASCADE,
    idatleta VARCHAR(21) NOT NULL REFERENCES usuarios(idusuario) ON DELETE CASCADE,
    idcronograma_origem VARCHAR(21) REFERENCES cronogramas(idcronograma) ON DELETE SET NULL,
    nome_snapshot VARCHAR(120) NOT NULL CHECK (length(trim(nome_snapshot)) BETWEEN 1 AND 120),
    data_inicio DATE NOT NULL,
    data_fim DATE NOT NULL,
    status VARCHAR(12) NOT NULL DEFAULT 'ativo' CHECK (status IN ('ativo','removido')),
    idempotency_key VARCHAR(120),
    payload_hash CHAR(64),
    criado_em TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    atualizado_em TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    removido_em TIMESTAMPTZ,
    CONSTRAINT ck_aplicacoes_cronograma_periodo CHECK (data_fim >= data_inicio),
    CONSTRAINT ck_aplicacoes_cronograma_usuarios CHECK (idtreinador <> idatleta)
);

CREATE UNIQUE INDEX IF NOT EXISTS ux_aplicacoes_cronograma_idempotencia
ON aplicacoes_cronograma_treinador (idtreinador, idempotency_key)
WHERE idempotency_key IS NOT NULL;

CREATE INDEX IF NOT EXISTS ix_aplicacoes_cronograma_atleta_periodo
ON aplicacoes_cronograma_treinador (idatleta, data_inicio, data_fim)
WHERE status = 'ativo';

ALTER TABLE treinos_agendados
    ADD COLUMN IF NOT EXISTS idaplicacao_cronograma VARCHAR(21);

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'fk_treinos_agendados_aplicacao_cronograma'
          AND conrelid = 'treinos_agendados'::regclass
    ) THEN
        ALTER TABLE treinos_agendados
        ADD CONSTRAINT fk_treinos_agendados_aplicacao_cronograma
        FOREIGN KEY (idaplicacao_cronograma)
        REFERENCES aplicacoes_cronograma_treinador(idaplicacao)
        ON DELETE SET NULL;
    END IF;
END $$;

CREATE UNIQUE INDEX IF NOT EXISTS ux_treinos_agendados_aplicacao_ocorrencia
ON treinos_agendados (idaplicacao_cronograma, idtreino_origem, data_treino)
WHERE idaplicacao_cronograma IS NOT NULL AND idtreino_origem IS NOT NULL;

CREATE INDEX IF NOT EXISTS ix_treinos_agendados_aplicacao
ON treinos_agendados (idaplicacao_cronograma, status, data_treino)
WHERE idaplicacao_cronograma IS NOT NULL;

CREATE TABLE IF NOT EXISTS comentarios_treino (
    idcomentario VARCHAR(21) PRIMARY KEY,
    idagendamento VARCHAR(21) NOT NULL REFERENCES treinos_agendados(idagendamento) ON DELETE CASCADE,
    idautor VARCHAR(21) REFERENCES usuarios(idusuario) ON DELETE SET NULL,
    idvinculo VARCHAR(21) REFERENCES vinculos_treinador_atleta(idvinculo) ON DELETE SET NULL,
    texto TEXT NOT NULL CHECK (length(trim(texto)) BETWEEN 1 AND 2000),
    criado_em TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    editado_em TIMESTAMPTZ,
    excluido_em TIMESTAMPTZ
);

CREATE INDEX IF NOT EXISTS ix_comentarios_treino_agendamento
ON comentarios_treino (idagendamento, criado_em)
WHERE excluido_em IS NULL;

COMMIT;
