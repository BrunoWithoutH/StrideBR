-- StrideBR 1.0 RC - consolidated migration
-- Consolidates all database changes developed after commit b0bab77
-- (2026-08-16, closed alpha) into the single migration shipped with the RC.
-- Historical migrations already published before that commit remain separate.


-- ============================================================================
-- Consolidated from: 20260819_identity_security_hardening.sql
-- ============================================================================
BEGIN;
SET search_path TO stridebr, public;

CREATE TABLE IF NOT EXISTS auth_rate_limits (
    chave_hash CHAR(64) PRIMARY KEY,
    escopo VARCHAR(32) NOT NULL,
    tentativas INTEGER NOT NULL DEFAULT 0 CHECK (tentativas >= 0),
    janela_inicio TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    bloqueado_ate TIMESTAMPTZ,
    atualizado_em TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS ix_auth_rate_limits_bloqueado ON auth_rate_limits (bloqueado_ate) WHERE bloqueado_ate IS NOT NULL;
CREATE INDEX IF NOT EXISTS ix_auth_rate_limits_atualizado ON auth_rate_limits (atualizado_em);


COMMIT;


-- ============================================================================
-- Consolidated from: 20260819_trainer_monthly_hardening.sql
-- ============================================================================
BEGIN;
SET search_path TO stridebr, public;

ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS descobrivel BOOLEAN NOT NULL DEFAULT TRUE;
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS modo_treinador BOOLEAN NOT NULL DEFAULT FALSE;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'ck_usuarios_nome_tamanho'
          AND conrelid = 'usuarios'::regclass
    ) THEN
        ALTER TABLE usuarios
        ADD CONSTRAINT ck_usuarios_nome_tamanho
        CHECK (length(trim(nomeusuario)) BETWEEN 1 AND 80) NOT VALID;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'ck_usuarios_nome_exibicao_tamanho'
          AND conrelid = 'usuarios'::regclass
    ) THEN
        ALTER TABLE usuarios
        ADD CONSTRAINT ck_usuarios_nome_exibicao_tamanho
        CHECK (nome_exibicao IS NULL OR length(trim(nome_exibicao)) BETWEEN 1 AND 60) NOT VALID;
    END IF;
END $$;

CREATE TABLE IF NOT EXISTS vinculos_treinador_atleta (
    idvinculo VARCHAR(21) PRIMARY KEY,
    idtreinador VARCHAR(21) NOT NULL REFERENCES usuarios(idusuario) ON DELETE CASCADE,
    idatleta VARCHAR(21) NOT NULL REFERENCES usuarios(idusuario) ON DELETE CASCADE,
    solicitado_por VARCHAR(12) NOT NULL CHECK (solicitado_por IN ('treinador', 'atleta')),
    status VARCHAR(12) NOT NULL DEFAULT 'pendente' CHECK (status IN ('pendente', 'aceito', 'recusado', 'encerrado')),
    pode_prescrever BOOLEAN NOT NULL DEFAULT TRUE,
    pode_ver_cronograma BOOLEAN NOT NULL DEFAULT TRUE,
    pode_ver_atividades BOOLEAN NOT NULL DEFAULT TRUE,
    pode_ver_feedback BOOLEAN NOT NULL DEFAULT TRUE,
    data_criacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    data_atualizacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    aceito_em TIMESTAMPTZ,
    encerrado_em TIMESTAMPTZ,
    CONSTRAINT ck_vinculo_usuarios_diferentes CHECK (idtreinador <> idatleta)
);

CREATE UNIQUE INDEX IF NOT EXISTS ux_vinculo_treinador_atleta_ativo
ON vinculos_treinador_atleta (idtreinador, idatleta)
WHERE status IN ('pendente', 'aceito');

CREATE INDEX IF NOT EXISTS ix_vinculos_treinador_status
ON vinculos_treinador_atleta (idtreinador, status, data_atualizacao DESC);

CREATE INDEX IF NOT EXISTS ix_vinculos_atleta_status
ON vinculos_treinador_atleta (idatleta, status, data_atualizacao DESC);

CREATE TABLE IF NOT EXISTS treinos_agendados (
    idagendamento VARCHAR(21) PRIMARY KEY,
    idatleta VARCHAR(21) NOT NULL REFERENCES usuarios(idusuario) ON DELETE CASCADE,
    idcriador VARCHAR(21) REFERENCES usuarios(idusuario) ON DELETE SET NULL,
    idvinculo VARCHAR(21) REFERENCES vinculos_treinador_atleta(idvinculo) ON DELETE SET NULL,
    idcronograma_origem VARCHAR(21) REFERENCES cronogramas(idcronograma) ON DELETE SET NULL,
    idtreino_origem VARCHAR(21) REFERENCES treinos_cronograma(idtreino) ON DELETE SET NULL,
    data_treino DATE NOT NULL,
    hora_inicio TIME,
    duracao_prevista_min INTEGER CHECK (duracao_prevista_min IS NULL OR duracao_prevista_min BETWEEN 1 AND 1440),
    titulo VARCHAR(120) NOT NULL CHECK (length(trim(titulo)) BETWEEN 1 AND 120),
    descricao TEXT,
    origem VARCHAR(12) NOT NULL DEFAULT 'usuario' CHECK (origem IN ('usuario', 'treinador')),
    status VARCHAR(12) NOT NULL DEFAULT 'publicado' CHECK (status IN ('rascunho', 'publicado', 'concluido', 'cancelado')),
    feedback_atleta TEXT,
    nota_atleta SMALLINT CHECK (nota_atleta IS NULL OR nota_atleta BETWEEN 1 AND 5),
    feedback_em TIMESTAMPTZ,
    publicado_em TIMESTAMPTZ,
    data_criacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    data_atualizacao TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS ix_treinos_agendados_atleta_data
ON treinos_agendados (idatleta, data_treino, hora_inicio);

CREATE INDEX IF NOT EXISTS ix_treinos_agendados_criador_data
ON treinos_agendados (idcriador, data_treino DESC);

CREATE INDEX IF NOT EXISTS ix_treinos_agendados_vinculo_status
ON treinos_agendados (idvinculo, status, data_treino DESC);

CREATE TABLE IF NOT EXISTS treinos_agendados_exercicios (
    idagendamento_exercicio VARCHAR(21) PRIMARY KEY,
    idagendamento VARCHAR(21) NOT NULL REFERENCES treinos_agendados(idagendamento) ON DELETE CASCADE,
    idexercicio VARCHAR(21) REFERENCES exercicios(idexercicio) ON DELETE SET NULL,
    nome_snapshot VARCHAR(120) NOT NULL CHECK (length(trim(nome_snapshot)) BETWEEN 1 AND 120),
    series INTEGER CHECK (series IS NULL OR series BETWEEN 1 AND 99),
    repeticoes VARCHAR(40),
    carga VARCHAR(40),
    descanso VARCHAR(40),
    observacoes TEXT,
    ordem INTEGER NOT NULL CHECK (ordem > 0),
    data_criacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (idagendamento, ordem)
);

ALTER TABLE sessoes_treino ADD COLUMN IF NOT EXISTS idagendamento_origem VARCHAR(21);

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'fk_sessoes_treino_agendamento'
          AND conrelid = 'sessoes_treino'::regclass
    ) THEN
        ALTER TABLE sessoes_treino
        ADD CONSTRAINT fk_sessoes_treino_agendamento
        FOREIGN KEY (idagendamento_origem)
        REFERENCES treinos_agendados(idagendamento)
        ON DELETE SET NULL;
    END IF;
END $$;

CREATE INDEX IF NOT EXISTS ix_sessoes_treino_agendamento
ON sessoes_treino (idagendamento_origem)
WHERE idagendamento_origem IS NOT NULL;

INSERT INTO feature_flags (chave, ativo, descricao) VALUES
('trainer.enabled', TRUE, 'Ativa vínculos treinador-atleta, prescrição e acompanhamento esportivo.'),
('monthly_calendar.enabled', TRUE, 'Ativa a agenda mensal com treinos recorrentes e prescrições por data.')
ON CONFLICT (chave) DO NOTHING;

COMMIT;


-- ============================================================================
-- Consolidated from: 20260821_schedule_recurrence_exercise_fields.sql
-- ============================================================================
BEGIN;
SET search_path TO stridebr, public;

DO $$
BEGIN
    IF to_regclass('stridebr.treinos_cronograma') IS NULL THEN
        RAISE EXCEPTION 'Tabela stridebr.treinos_cronograma não existe. Aplique o schema base/migrations anteriores primeiro.';
    END IF;
    IF to_regclass('stridebr.treinos_exercicios') IS NULL THEN
        RAISE EXCEPTION 'Tabela stridebr.treinos_exercicios não existe. Aplique o schema base/migrations anteriores primeiro.';
    END IF;
END $$;

ALTER TABLE treinos_cronograma ADD COLUMN IF NOT EXISTS vigencia_inicio DATE;
ALTER TABLE treinos_cronograma ADD COLUMN IF NOT EXISTS vigencia_fim DATE;

UPDATE treinos_cronograma
SET vigencia_inicio = COALESCE(vigencia_inicio, data_criacao::date, CURRENT_DATE)
WHERE vigencia_inicio IS NULL;

ALTER TABLE treinos_cronograma ALTER COLUMN vigencia_inicio SET DEFAULT CURRENT_DATE;
ALTER TABLE treinos_cronograma ALTER COLUMN vigencia_inicio SET NOT NULL;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'ck_treinos_cronograma_vigencia'
          AND conrelid = 'treinos_cronograma'::regclass
    ) THEN
        ALTER TABLE treinos_cronograma
        ADD CONSTRAINT ck_treinos_cronograma_vigencia
        CHECK (vigencia_fim IS NULL OR vigencia_fim >= vigencia_inicio);
    END IF;
END $$;

CREATE INDEX IF NOT EXISTS ix_treinos_cronograma_vigencia
ON treinos_cronograma (idcronograma, vigencia_inicio, vigencia_fim, dia_semana, hora_inicio);

CREATE TABLE IF NOT EXISTS treinos_cronograma_excecoes (
    idexcecao VARCHAR(21) PRIMARY KEY,
    idtreino VARCHAR(21) NOT NULL REFERENCES treinos_cronograma(idtreino) ON DELETE CASCADE,
    data_original DATE NOT NULL,
    tipo VARCHAR(12) NOT NULL CHECK (tipo IN ('alterar', 'cancelar')),
    data_treino DATE,
    titulo VARCHAR(120),
    descricao TEXT,
    hora_inicio TIME,
    hora_fim TIME,
    termina_dia_seguinte BOOLEAN,
    data_criacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    data_atualizacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (idtreino, data_original),
    CONSTRAINT ck_treino_excecao_data CHECK (
        (tipo = 'cancelar' AND data_treino IS NULL)
        OR
        (tipo = 'alterar' AND data_treino IS NOT NULL)
    ),
    CONSTRAINT ck_treino_excecao_horario CHECK (
        hora_inicio IS NULL OR hora_fim IS NULL OR termina_dia_seguinte IS NULL
        OR (NOT termina_dia_seguinte AND hora_fim > hora_inicio)
        OR (termina_dia_seguinte AND hora_fim <= hora_inicio)
    )
);

CREATE INDEX IF NOT EXISTS ix_treino_excecoes_original
ON treinos_cronograma_excecoes (idtreino, data_original);

CREATE INDEX IF NOT EXISTS ix_treino_excecoes_data_treino
ON treinos_cronograma_excecoes (data_treino)
WHERE data_treino IS NOT NULL;

ALTER TABLE treinos_exercicios ADD COLUMN IF NOT EXISTS duracao VARCHAR(40);
ALTER TABLE treinos_exercicios ADD COLUMN IF NOT EXISTS distancia VARCHAR(40);
ALTER TABLE treinos_exercicios ADD COLUMN IF NOT EXISTS intensidade VARCHAR(80);
ALTER TABLE treinos_exercicios ADD COLUMN IF NOT EXISTS rpe NUMERIC(3,1);
ALTER TABLE treinos_exercicios ADD COLUMN IF NOT EXISTS rir NUMERIC(3,1);
ALTER TABLE treinos_exercicios ADD COLUMN IF NOT EXISTS tempo_execucao VARCHAR(40);
ALTER TABLE treinos_exercicios ADD COLUMN IF NOT EXISTS cadencia VARCHAR(40);

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'ck_treinos_exercicios_rpe'
          AND conrelid = 'treinos_exercicios'::regclass
    ) THEN
        ALTER TABLE treinos_exercicios
        ADD CONSTRAINT ck_treinos_exercicios_rpe CHECK (rpe IS NULL OR rpe BETWEEN 0 AND 10);
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'ck_treinos_exercicios_rir'
          AND conrelid = 'treinos_exercicios'::regclass
    ) THEN
        ALTER TABLE treinos_exercicios
        ADD CONSTRAINT ck_treinos_exercicios_rir CHECK (rir IS NULL OR rir BETWEEN 0 AND 10);
    END IF;
END $$;

DO $$
BEGIN
    IF to_regclass('stridebr.treinos_agendados_exercicios') IS NOT NULL THEN
        ALTER TABLE treinos_agendados_exercicios ADD COLUMN IF NOT EXISTS duracao VARCHAR(40);
        ALTER TABLE treinos_agendados_exercicios ADD COLUMN IF NOT EXISTS distancia VARCHAR(40);
        ALTER TABLE treinos_agendados_exercicios ADD COLUMN IF NOT EXISTS intensidade VARCHAR(80);
        ALTER TABLE treinos_agendados_exercicios ADD COLUMN IF NOT EXISTS rpe NUMERIC(3,1);
        ALTER TABLE treinos_agendados_exercicios ADD COLUMN IF NOT EXISTS rir NUMERIC(3,1);
        ALTER TABLE treinos_agendados_exercicios ADD COLUMN IF NOT EXISTS tempo_execucao VARCHAR(40);
        ALTER TABLE treinos_agendados_exercicios ADD COLUMN IF NOT EXISTS cadencia VARCHAR(40);
        IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'ck_treinos_agendados_exercicios_rpe' AND conrelid = 'treinos_agendados_exercicios'::regclass) THEN
            ALTER TABLE treinos_agendados_exercicios ADD CONSTRAINT ck_treinos_agendados_exercicios_rpe CHECK (rpe IS NULL OR rpe BETWEEN 0 AND 10);
        END IF;
        IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'ck_treinos_agendados_exercicios_rir' AND conrelid = 'treinos_agendados_exercicios'::regclass) THEN
            ALTER TABLE treinos_agendados_exercicios ADD CONSTRAINT ck_treinos_agendados_exercicios_rir CHECK (rir IS NULL OR rir BETWEEN 0 AND 10);
        END IF;
    END IF;

    IF to_regclass('stridebr.sessoes_treino_exercicios') IS NOT NULL THEN
        ALTER TABLE sessoes_treino_exercicios ADD COLUMN IF NOT EXISTS duracao_snapshot VARCHAR(40);
        ALTER TABLE sessoes_treino_exercicios ADD COLUMN IF NOT EXISTS distancia_snapshot VARCHAR(40);
        ALTER TABLE sessoes_treino_exercicios ADD COLUMN IF NOT EXISTS intensidade_snapshot VARCHAR(80);
        ALTER TABLE sessoes_treino_exercicios ADD COLUMN IF NOT EXISTS rpe_snapshot NUMERIC(3,1);
        ALTER TABLE sessoes_treino_exercicios ADD COLUMN IF NOT EXISTS rir_snapshot NUMERIC(3,1);
        ALTER TABLE sessoes_treino_exercicios ADD COLUMN IF NOT EXISTS tempo_execucao_snapshot VARCHAR(40);
        ALTER TABLE sessoes_treino_exercicios ADD COLUMN IF NOT EXISTS cadencia_snapshot VARCHAR(40);
    END IF;

    IF to_regclass('stridebr.sessoes_treino') IS NOT NULL THEN
        ALTER TABLE sessoes_treino ADD COLUMN IF NOT EXISTS data_ocorrencia_origem DATE;
    END IF;
END $$;

COMMIT;


-- ============================================================================
-- Consolidated from: 20260822_activities_v2.sql
-- ============================================================================
BEGIN;
SET search_path TO stridebr, public;

ALTER TABLE modalidades ADD COLUMN IF NOT EXISTS categoria VARCHAR(60) NOT NULL DEFAULT 'Outras atividades';
ALTER TABLE modalidades ADD COLUMN IF NOT EXISTS icone VARCHAR(16);
ALTER TABLE modalidades ADD COLUMN IF NOT EXISTS ordem_catalogo INTEGER NOT NULL DEFAULT 999;
ALTER TABLE modalidades ADD COLUMN IF NOT EXISTS metrica_derivada VARCHAR(20) NOT NULL DEFAULT 'nenhuma';

ALTER TABLE modalidades_usuario ADD COLUMN IF NOT EXISTS favorita BOOLEAN NOT NULL DEFAULT FALSE;
ALTER TABLE modalidades_usuario ADD COLUMN IF NOT EXISTS ordem_preferencia INTEGER;
ALTER TABLE modalidades_usuario ADD COLUMN IF NOT EXISTS ultimo_uso TIMESTAMPTZ;

ALTER TABLE campos_modelo ADD COLUMN IF NOT EXISTS exibicao_padrao BOOLEAN NOT NULL DEFAULT TRUE;
ALTER TABLE campos_modelo ADD COLUMN IF NOT EXISTS grupo_ui VARCHAR(30) NOT NULL DEFAULT 'detalhes';

ALTER TABLE registros_atividade ADD COLUMN IF NOT EXISTS esforco_percebido SMALLINT;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'ck_registros_atividade_esforco'
          AND conrelid = 'registros_atividade'::regclass
    ) THEN
        ALTER TABLE registros_atividade
        ADD CONSTRAINT ck_registros_atividade_esforco
        CHECK (esforco_percebido IS NULL OR esforco_percebido BETWEEN 1 AND 10);
    END IF;
END $$;

CREATE TABLE IF NOT EXISTS equipamentos_usuario (
    idequipamento VARCHAR(21) PRIMARY KEY,
    idusuario VARCHAR(21) NOT NULL REFERENCES usuarios(idusuario) ON DELETE CASCADE,
    nome VARCHAR(120) NOT NULL CHECK (length(trim(nome)) BETWEEN 1 AND 120),
    categoria VARCHAR(40) NOT NULL DEFAULT 'outro' CHECK (length(trim(categoria)) BETWEEN 1 AND 40),
    marca VARCHAR(80),
    modelo VARCHAR(100),
    data_inicio_uso DATE,
    distancia_inicial_km NUMERIC(12,3) NOT NULL DEFAULT 0 CHECK (distancia_inicial_km >= 0),
    observacoes TEXT,
    ativo BOOLEAN NOT NULL DEFAULT TRUE,
    data_criacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    data_atualizacao TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS ix_equipamentos_usuario_ativo
ON equipamentos_usuario (idusuario, ativo, nome);

CREATE TABLE IF NOT EXISTS registros_atividade_equipamentos (
    idregistro VARCHAR(21) NOT NULL REFERENCES registros_atividade(idregistro) ON DELETE CASCADE,
    idequipamento VARCHAR(21) NOT NULL REFERENCES equipamentos_usuario(idequipamento) ON DELETE CASCADE,
    PRIMARY KEY (idregistro, idequipamento)
);

CREATE INDEX IF NOT EXISTS ix_registro_equipamento_equipamento
ON registros_atividade_equipamentos (idequipamento, idregistro);

UPDATE modalidades SET categoria = 'Corrida e caminhada', icone = '🏃', ordem_catalogo = 10, metrica_derivada = 'pace_km' WHERE slug = 'corrida';
UPDATE modalidades SET categoria = 'Corrida e caminhada', icone = '🚶', ordem_catalogo = 20, metrica_derivada = 'pace_km' WHERE slug = 'caminhada';
UPDATE modalidades SET categoria = 'Corrida e caminhada', icone = '🏃', ordem_catalogo = 30, metrica_derivada = 'pace_km' WHERE slug = 'marcha-atletica';
UPDATE modalidades SET categoria = 'Corrida e caminhada', icone = '🥾', ordem_catalogo = 40, metrica_derivada = 'pace_km' WHERE slug = 'trilha';
UPDATE modalidades SET categoria = 'Ciclismo', icone = '🚴', ordem_catalogo = 100, metrica_derivada = 'velocidade_kmh' WHERE slug = 'ciclismo';
UPDATE modalidades SET categoria = 'Ciclismo', icone = '🚵', ordem_catalogo = 110, metrica_derivada = 'velocidade_kmh' WHERE slug = 'mountain-bike';
UPDATE modalidades SET categoria = 'Ciclismo', icone = '🚵', ordem_catalogo = 120, metrica_derivada = 'velocidade_kmh' WHERE slug = 'downhill';
UPDATE modalidades SET categoria = 'Ciclismo', icone = '🚲', ordem_catalogo = 130, metrica_derivada = 'velocidade_kmh' WHERE slug = 'bmx';
UPDATE modalidades SET categoria = 'Aquáticos', icone = '🏊', ordem_catalogo = 200, metrica_derivada = 'pace_100m' WHERE slug = 'natacao';
UPDATE modalidades SET categoria = 'Raquete', icone = '🎾', ordem_catalogo = 300 WHERE slug = 'tenis';
UPDATE modalidades SET categoria = 'Raquete', icone = '🏓', ordem_catalogo = 310 WHERE slug = 'tenis-de-mesa';
UPDATE modalidades SET categoria = 'Raquete', icone = '🏸', ordem_catalogo = 320 WHERE slug = 'badminton';
UPDATE modalidades SET categoria = 'Raquete', icone = '🎾', ordem_catalogo = 330 WHERE slug = 'padel';
UPDATE modalidades SET categoria = 'Raquete', icone = '🎾', ordem_catalogo = 340 WHERE slug = 'beach-tennis';
UPDATE modalidades SET categoria = 'Atletismo', icone = '🏟️', ordem_catalogo = 700 WHERE slug IN ('arremesso-de-peso', 'lancamento-de-disco', 'lancamento-de-dardo', 'lancamento-de-martelo');
UPDATE modalidades SET categoria = 'Força e condicionamento', icone = '🏋️', ordem_catalogo = 500 WHERE slug = 'musculacao';
UPDATE modalidades SET categoria = 'Força e condicionamento', icone = '🤸', ordem_catalogo = 510 WHERE slug = 'calistenia';
UPDATE modalidades SET categoria = 'Lutas', icone = '🥋', ordem_catalogo = 600 WHERE slug = 'karate';
UPDATE modalidades SET categoria = 'Outras atividades', icone = '＋', ordem_catalogo = 9999 WHERE slug = 'outra-atividade';

INSERT INTO modalidades (idmodalidade, nome, slug, descricao, visibilidade, status_publicacao, categoria, icone, ordem_catalogo, metrica_derivada) VALUES
('m_trailrun', 'Corrida em trilha', 'corrida-em-trilha', 'Corrida em trilhas e terreno natural.', 'publico', 'publicado', 'Corrida e caminhada', '🏃', 15, 'pace_km'),
('m_treadmill', 'Corrida em esteira', 'corrida-em-esteira', 'Corrida em esteira ou ambiente interno.', 'publico', 'publicado', 'Corrida e caminhada', '🏃', 16, 'pace_km'),
('m_wheelchair', 'Cadeira de rodas esportiva', 'cadeira-de-rodas', 'Atividade esportiva em cadeira de rodas.', 'publico', 'publicado', 'Corrida e caminhada', '♿', 50, 'velocidade_kmh'),
('m_gravel', 'Gravel', 'gravel', 'Ciclismo em estradas de cascalho e terreno misto.', 'publico', 'publicado', 'Ciclismo', '🚴', 105, 'velocidade_kmh'),
('m_ebike', 'Bicicleta elétrica', 'bicicleta-eletrica', 'Pedalada com bicicleta elétrica.', 'publico', 'publicado', 'Ciclismo', '🚲', 115, 'velocidade_kmh'),
('m_emtb', 'E-Mountain Bike', 'e-mountain-bike', 'Mountain bike com assistência elétrica.', 'publico', 'publicado', 'Ciclismo', '🚵', 116, 'velocidade_kmh'),
('m_cicindoor', 'Ciclismo indoor', 'ciclismo-indoor', 'Treino em bicicleta ergométrica, spinning ou rolo.', 'publico', 'publicado', 'Ciclismo', '🚴', 140, 'velocidade_kmh'),
('m_handcycle', 'Handcycle', 'handcycle', 'Ciclismo com bicicleta de mão.', 'publico', 'publicado', 'Ciclismo', '♿', 150, 'velocidade_kmh'),
('m_velomovel', 'Velomóvel', 'velomovel', 'Ciclismo em velomóvel.', 'publico', 'publicado', 'Ciclismo', '🚲', 160, 'velocidade_kmh'),
('m_remo', 'Remo', 'remo', 'Remo em água.', 'publico', 'publicado', 'Aquáticos', '🚣', 210, 'split_500m'),
('m_remoind', 'Remo indoor', 'remo-indoor', 'Treino em ergômetro de remo.', 'publico', 'publicado', 'Aquáticos', '🚣', 211, 'split_500m'),
('m_canoa', 'Canoagem', 'canoagem', 'Atividade de canoa.', 'publico', 'publicado', 'Aquáticos', '🛶', 220, 'velocidade_kmh'),
('m_caiaque', 'Caiaque', 'caiaque', 'Atividade de caiaque.', 'publico', 'publicado', 'Aquáticos', '🛶', 230, 'velocidade_kmh'),
('m_sup', 'Stand Up Paddle', 'stand-up-paddle', 'Stand up paddle.', 'publico', 'publicado', 'Aquáticos', '🏄', 240, 'velocidade_kmh'),
('m_surf', 'Surfe', 'surfe', 'Sessão de surfe.', 'publico', 'publicado', 'Aquáticos', '🏄', 250, 'nenhuma'),
('m_kitesurf', 'Kitesurf', 'kitesurf', 'Sessão de kitesurf.', 'publico', 'publicado', 'Aquáticos', '🏄', 260, 'velocidade_kmh'),
('m_windsurf', 'Windsurf', 'windsurf', 'Sessão de windsurf.', 'publico', 'publicado', 'Aquáticos', '🏄', 270, 'velocidade_kmh'),
('m_vela', 'Vela', 'vela', 'Atividade de vela.', 'publico', 'publicado', 'Aquáticos', '⛵', 280, 'nenhuma'),
('m_pickle', 'Pickleball', 'pickleball', 'Treino ou partida de pickleball.', 'publico', 'publicado', 'Raquete', '🏓', 350, 'nenhuma'),
('m_squash', 'Squash', 'squash', 'Treino ou partida de squash.', 'publico', 'publicado', 'Raquete', '🎾', 360, 'nenhuma'),
('m_raquetebol', 'Raquetebol', 'raquetebol', 'Treino ou partida de raquetebol.', 'publico', 'publicado', 'Raquete', '🎾', 370, 'nenhuma'),
('m_futebol', 'Futebol', 'futebol', 'Treino ou partida de futebol.', 'publico', 'publicado', 'Esportes coletivos', '⚽', 400, 'nenhuma'),
('m_futsal', 'Futsal', 'futsal', 'Treino ou partida de futsal.', 'publico', 'publicado', 'Esportes coletivos', '⚽', 410, 'nenhuma'),
('m_basquete', 'Basquete', 'basquete', 'Treino ou partida de basquete.', 'publico', 'publicado', 'Esportes coletivos', '🏀', 420, 'nenhuma'),
('m_volei', 'Vôlei', 'volei', 'Treino ou partida de vôlei.', 'publico', 'publicado', 'Esportes coletivos', '🏐', 430, 'nenhuma'),
('m_volei_praia', 'Vôlei de praia', 'volei-de-praia', 'Treino ou partida de vôlei de praia.', 'publico', 'publicado', 'Esportes coletivos', '🏐', 431, 'nenhuma'),
('m_handebol', 'Handebol', 'handebol', 'Treino ou partida de handebol.', 'publico', 'publicado', 'Esportes coletivos', '🤾', 440, 'nenhuma'),
('m_rugby', 'Rugby', 'rugby', 'Treino ou partida de rugby.', 'publico', 'publicado', 'Esportes coletivos', '🏉', 450, 'nenhuma'),
('m_futam', 'Futebol americano', 'futebol-americano', 'Treino ou partida de futebol americano.', 'publico', 'publicado', 'Esportes coletivos', '🏈', 460, 'nenhuma'),
('m_criquete', 'Críquete', 'criquete', 'Treino ou partida de críquete.', 'publico', 'publicado', 'Esportes coletivos', '🏏', 470, 'nenhuma'),
('m_crossfit', 'CrossFit', 'crossfit', 'Sessão de CrossFit.', 'publico', 'publicado', 'Força e condicionamento', '🏋️', 520, 'nenhuma'),
('m_hiit', 'HIIT', 'hiit', 'Treino intervalado de alta intensidade.', 'publico', 'publicado', 'Força e condicionamento', '⚡', 530, 'nenhuma'),
('m_funcional', 'Treino funcional', 'treino-funcional', 'Treino funcional ou circuito.', 'publico', 'publicado', 'Força e condicionamento', '🏋️', 540, 'nenhuma'),
('m_cardio', 'Cardio', 'cardio', 'Sessão geral de cardio.', 'publico', 'publicado', 'Força e condicionamento', '❤', 550, 'nenhuma'),
('m_eliptico', 'Elíptico', 'eliptico', 'Treino em aparelho elíptico.', 'publico', 'publicado', 'Força e condicionamento', '🏃', 560, 'nenhuma'),
('m_escadas', 'Simulador de escada', 'simulador-de-escada', 'Treino em simulador de escada.', 'publico', 'publicado', 'Força e condicionamento', '↗', 570, 'nenhuma'),
('m_corda', 'Pular corda', 'pular-corda', 'Treino com corda.', 'publico', 'publicado', 'Força e condicionamento', '➰', 580, 'nenhuma'),
('m_yoga', 'Yoga', 'yoga', 'Sessão de yoga.', 'publico', 'publicado', 'Força e condicionamento', '🧘', 590, 'nenhuma'),
('m_pilates', 'Pilates', 'pilates', 'Sessão de pilates.', 'publico', 'publicado', 'Força e condicionamento', '🧘', 591, 'nenhuma'),
('m_mobilidade', 'Mobilidade', 'mobilidade', 'Mobilidade, alongamento e trabalho de amplitude.', 'publico', 'publicado', 'Força e condicionamento', '🤸', 592, 'nenhuma'),
('m_danca', 'Dança', 'danca', 'Sessão de dança.', 'publico', 'publicado', 'Força e condicionamento', '💃', 593, 'nenhuma'),
('m_judo', 'Judô', 'judo', 'Treino de judô.', 'publico', 'publicado', 'Lutas', '🥋', 610, 'nenhuma'),
('m_jiujitsu', 'Jiu-jítsu', 'jiu-jitsu', 'Treino de jiu-jítsu.', 'publico', 'publicado', 'Lutas', '🥋', 620, 'nenhuma'),
('m_boxe', 'Boxe', 'boxe', 'Treino de boxe.', 'publico', 'publicado', 'Lutas', '🥊', 630, 'nenhuma'),
('m_muaythai', 'Muay Thai', 'muay-thai', 'Treino de Muay Thai.', 'publico', 'publicado', 'Lutas', '🥊', 640, 'nenhuma'),
('m_taekwondo', 'Taekwondo', 'taekwondo', 'Treino de taekwondo.', 'publico', 'publicado', 'Lutas', '🥋', 650, 'nenhuma'),
('m_capoeira', 'Capoeira', 'capoeira', 'Treino ou roda de capoeira.', 'publico', 'publicado', 'Lutas', '🤸', 660, 'nenhuma'),
('m_wrestling', 'Luta olímpica', 'luta-olimpica', 'Treino de luta olímpica.', 'publico', 'publicado', 'Lutas', '🤼', 670, 'nenhuma'),
('m_kickbox', 'Kickboxing', 'kickboxing', 'Treino de kickboxing.', 'publico', 'publicado', 'Lutas', '🥊', 680, 'nenhuma'),
('m_esgrima', 'Esgrima', 'esgrima', 'Treino de esgrima.', 'publico', 'publicado', 'Lutas', '🤺', 690, 'nenhuma'),
('m_atletismo', 'Atletismo', 'atletismo', 'Treino geral de atletismo.', 'publico', 'publicado', 'Atletismo', '🏟️', 701, 'nenhuma'),
('m_salto_dist', 'Salto em distância', 'salto-em-distancia', 'Treinos e provas de salto em distância.', 'publico', 'publicado', 'Atletismo', '🏟️', 710, 'nenhuma'),
('m_salto_alt', 'Salto em altura', 'salto-em-altura', 'Treinos e provas de salto em altura.', 'publico', 'publicado', 'Atletismo', '🏟️', 720, 'nenhuma'),
('m_salto_vara', 'Salto com vara', 'salto-com-vara', 'Treinos e provas de salto com vara.', 'publico', 'publicado', 'Atletismo', '🏟️', 730, 'nenhuma'),
('m_escalada', 'Escalada', 'escalada', 'Escalada esportiva em rocha ou parede.', 'publico', 'publicado', 'Escalada', '🧗', 800, 'nenhuma'),
('m_boulder', 'Boulder', 'boulder', 'Sessão de boulder.', 'publico', 'publicado', 'Escalada', '🧗', 810, 'nenhuma'),
('m_patins', 'Patinação inline', 'patinacao-inline', 'Patinação com patins inline.', 'publico', 'publicado', 'Rodas', '🛼', 850, 'velocidade_kmh'),
('m_skate', 'Skate', 'skate', 'Sessão de skate.', 'publico', 'publicado', 'Rodas', '🛹', 860, 'nenhuma'),
('m_rollerski', 'Roller ski', 'roller-ski', 'Treino de roller ski.', 'publico', 'publicado', 'Rodas', '🎿', 870, 'velocidade_kmh'),
('m_esqui_alp', 'Esqui alpino', 'esqui-alpino', 'Sessão de esqui alpino.', 'publico', 'publicado', 'Inverno', '🎿', 900, 'velocidade_kmh'),
('m_esqui_nord', 'Esqui nórdico', 'esqui-nordico', 'Sessão de esqui nórdico.', 'publico', 'publicado', 'Inverno', '🎿', 910, 'velocidade_kmh'),
('m_esqui_back', 'Esqui fora de pista', 'esqui-fora-de-pista', 'Sessão de esqui fora de pista.', 'publico', 'publicado', 'Inverno', '🎿', 920, 'velocidade_kmh'),
('m_snowboard', 'Snowboard', 'snowboard', 'Sessão de snowboard.', 'publico', 'publicado', 'Inverno', '🏂', 930, 'velocidade_kmh'),
('m_raquete_neve', 'Raquete de neve', 'raquete-de-neve', 'Caminhada com raquetes de neve.', 'publico', 'publicado', 'Inverno', '🥾', 940, 'pace_km'),
('m_patgelo', 'Patinação no gelo', 'patinacao-no-gelo', 'Sessão de patinação no gelo.', 'publico', 'publicado', 'Inverno', '⛸️', 950, 'velocidade_kmh'),
('m_golfe', 'Golfe', 'golfe', 'Partida ou treino de golfe.', 'publico', 'publicado', 'Outras atividades', '⛳', 1000, 'nenhuma'),
('m_equita', 'Equitação', 'equitacao', 'Sessão de equitação.', 'publico', 'publicado', 'Outras atividades', '🐎', 1010, 'nenhuma')
ON CONFLICT DO NOTHING;

INSERT INTO modelos_modalidade (
    idmodelo, idmodalidade, nome, slug, descricao, tipo_unidade_padrao, rotulo_unidade,
    permite_multiplas_unidades, versao, padrao, visibilidade, status_publicacao
)
SELECT
    'md' || substr(md5(m.idmodalidade), 1, 19),
    m.idmodalidade,
    'Registro padrão',
    'padrao',
    'Registro rápido da modalidade.',
    'sessao',
    'Sessão',
    FALSE,
    1,
    TRUE,
    'publico',
    'publicado'
FROM modalidades m
WHERE m.idusuario IS NULL
  AND NOT EXISTS (
      SELECT 1 FROM modelos_modalidade mm
      WHERE mm.idmodalidade = m.idmodalidade
        AND mm.ativo = TRUE
        AND mm.padrao = TRUE
  )
ON CONFLICT DO NOTHING;


UPDATE modelos_modalidade
SET padrao = FALSE, ativo = FALSE
WHERE idmodelo = 'md_geral';

INSERT INTO modelos_modalidade (
    idmodelo, idmodalidade, idusuario, idmodelo_anterior, nome, slug, descricao,
    tipo_unidade_padrao, rotulo_unidade, permite_multiplas_unidades, versao,
    padrao, ativo, visibilidade, status_publicacao
) VALUES (
    'md_geral_v2', 'm_geral', NULL, 'md_geral', 'Registro livre', 'livre',
    'Registro livre com campos opcionais escolhidos no momento do cadastro.',
    'sessao', 'Sessão', FALSE, 2, TRUE, TRUE, 'publico', 'publicado'
)
ON CONFLICT (idmodelo) DO UPDATE
SET padrao = TRUE, ativo = TRUE;


UPDATE modalidades_usuario
SET idmodelo_ativo = 'md_geral_v2'
WHERE idmodalidade = 'm_geral'
  AND idmodelo_ativo = 'md_geral';

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM campos_modelo WHERE idmodelo = 'md_geral_v2') THEN
        INSERT INTO campos_modelo (
            idcampo, idmodelo, nome, slug, rotulo, tipo_campo, escopo,
            idgrandeza, idunidade, obrigatorio, ordem, exibicao_padrao, grupo_ui
        ) VALUES
        ('f_ger2_dur', 'md_geral_v2', 'duracao', 'duracao', 'Duração', 'intervalo', 'unidade', 'g_tempo', NULL, FALSE, 1, TRUE, 'principal'),
        ('f_ger2_dist', 'md_geral_v2', 'distancia', 'distancia', 'Distância', 'decimal', 'unidade', 'g_distancia', 'u_km', FALSE, 2, FALSE, 'extras'),
        ('f_ger2_elev', 'md_geral_v2', 'elevacao', 'elevacao', 'Elevação', 'decimal', 'unidade', 'g_distancia', 'u_m', FALSE, 3, FALSE, 'extras'),
        ('f_ger2_fc', 'md_geral_v2', 'fc_media', 'fc-media', 'FC média (bpm)', 'inteiro', 'unidade', NULL, NULL, FALSE, 4, FALSE, 'extras'),
        ('f_ger2_steps', 'md_geral_v2', 'passos', 'passos', 'Passos', 'inteiro', 'unidade', NULL, NULL, FALSE, 5, FALSE, 'extras'),
        ('f_ger2_sets', 'md_geral_v2', 'series', 'series', 'Séries', 'inteiro', 'unidade', NULL, NULL, FALSE, 6, FALSE, 'extras'),
        ('f_ger2_reps', 'md_geral_v2', 'repeticoes', 'repeticoes', 'Repetições', 'inteiro', 'unidade', NULL, NULL, FALSE, 7, FALSE, 'extras'),
        ('f_ger2_load', 'md_geral_v2', 'carga', 'carga', 'Carga', 'decimal', 'unidade', 'g_massa', 'u_kg', FALSE, 8, FALSE, 'extras'),
        ('f_ger2_cad', 'md_geral_v2', 'cadencia', 'cadencia', 'Cadência', 'inteiro', 'unidade', NULL, NULL, FALSE, 9, FALSE, 'extras'),
        ('f_ger2_power', 'md_geral_v2', 'potencia', 'potencia', 'Potência (W)', 'inteiro', 'unidade', NULL, NULL, FALSE, 10, FALSE, 'extras'),
        ('f_ger2_obs', 'md_geral_v2', 'observacoes', 'observacoes', 'Observações específicas', 'texto_longo', 'registro', NULL, NULL, FALSE, 11, FALSE, 'extras')
        ON CONFLICT DO NOTHING;
    END IF;
END $$;

-- Modelos que já possuem registros históricos não podem receber/remover campos.
-- Para eles, criamos uma nova versão, copiamos a definição atual e só então
-- acrescentamos os campos da interface v2. Os registros antigos continuam
-- apontando para a versão antiga, preservando o histórico.
CREATE TEMP TABLE tmp_modelos_atividades_v2 (
    idmodelo_antigo VARCHAR(21) PRIMARY KEY,
    idmodelo_novo VARCHAR(21) NOT NULL UNIQUE,
    idmodalidade VARCHAR(21) NOT NULL
) ON COMMIT DROP;

INSERT INTO tmp_modelos_atividades_v2 (idmodelo_antigo, idmodelo_novo, idmodalidade)
SELECT
    mm.idmodelo,
    'v2' || substr(md5(mm.idmodelo || ':activities-v2'), 1, 19),
    mm.idmodalidade
FROM modelos_modalidade mm
JOIN modalidades m ON m.idmodalidade = mm.idmodalidade
WHERE mm.ativo = TRUE
  AND mm.padrao = TRUE
  AND mm.idusuario IS NULL
  AND EXISTS (
      SELECT 1 FROM registros_atividade r WHERE r.idmodelo = mm.idmodelo
  )
  AND (
      NOT EXISTS (
          SELECT 1 FROM campos_modelo c
          WHERE c.idmodelo = mm.idmodelo AND lower(c.slug) = 'duracao'
      )
      OR (
          m.metrica_derivada <> 'nenhuma'
          AND NOT EXISTS (
              SELECT 1 FROM campos_modelo c
              WHERE c.idmodelo = mm.idmodelo AND lower(c.slug) = 'distancia'
          )
      )
  )
ON CONFLICT DO NOTHING;

-- Libera a restrição de "um modelo padrão ativo" antes de criar a nova versão.
UPDATE modelos_modalidade mm
SET padrao = FALSE,
    ativo = FALSE
FROM tmp_modelos_atividades_v2 t
WHERE mm.idmodelo = t.idmodelo_antigo;

INSERT INTO modelos_modalidade (
    idmodelo, idmodalidade, idusuario, idmodelo_anterior, nome, slug, descricao,
    tipo_unidade_padrao, rotulo_unidade, permite_multiplas_unidades, versao,
    padrao, ativo, visibilidade, status_publicacao
)
SELECT
    t.idmodelo_novo,
    antigo.idmodalidade,
    antigo.idusuario,
    antigo.idmodelo,
    antigo.nome,
    antigo.slug,
    antigo.descricao,
    antigo.tipo_unidade_padrao,
    antigo.rotulo_unidade,
    antigo.permite_multiplas_unidades,
    COALESCE((
        SELECT MAX(v.versao)
        FROM modelos_modalidade v
        WHERE v.idmodalidade = antigo.idmodalidade
          AND lower(v.slug) = lower(antigo.slug)
          AND v.idusuario IS NOT DISTINCT FROM antigo.idusuario
    ), antigo.versao) + 1,
    TRUE,
    TRUE,
    antigo.visibilidade,
    antigo.status_publicacao
FROM tmp_modelos_atividades_v2 t
JOIN modelos_modalidade antigo ON antigo.idmodelo = t.idmodelo_antigo
ON CONFLICT (idmodelo) DO NOTHING;

-- Copia campos da versão histórica para a nova versão.
INSERT INTO campos_modelo (
    idcampo, idmodelo, nome, slug, rotulo, tipo_campo, escopo,
    idgrandeza, idunidade, obrigatorio, ordem, ativo, exibicao_padrao, grupo_ui
)
SELECT
    'cf' || substr(md5(t.idmodelo_novo || ':' || c.idcampo), 1, 19),
    t.idmodelo_novo,
    c.nome,
    c.slug,
    c.rotulo,
    c.tipo_campo,
    c.escopo,
    c.idgrandeza,
    c.idunidade,
    c.obrigatorio,
    c.ordem,
    c.ativo,
    c.exibicao_padrao,
    c.grupo_ui
FROM tmp_modelos_atividades_v2 t
JOIN campos_modelo c ON c.idmodelo = t.idmodelo_antigo
ON CONFLICT DO NOTHING;

-- Copia as opções de campos do tipo seleção para a nova versão.
INSERT INTO campos_modelo_opcoes (
    idopcao, idcampo, rotulo, valor, ordem, ativo
)
SELECT
    'op' || substr(md5(novo_campo.idcampo || ':' || o.idopcao), 1, 19),
    novo_campo.idcampo,
    o.rotulo,
    o.valor,
    o.ordem,
    o.ativo
FROM tmp_modelos_atividades_v2 t
JOIN campos_modelo campo_antigo ON campo_antigo.idmodelo = t.idmodelo_antigo
JOIN campos_modelo_opcoes o ON o.idcampo = campo_antigo.idcampo
JOIN campos_modelo novo_campo
  ON novo_campo.idmodelo = t.idmodelo_novo
 AND lower(novo_campo.slug) = lower(campo_antigo.slug)
ON CONFLICT DO NOTHING;

-- Quem usava explicitamente o modelo padrão antigo passa a usar a nova versão.
UPDATE modalidades_usuario mu
SET idmodelo_ativo = t.idmodelo_novo
FROM tmp_modelos_atividades_v2 t
WHERE mu.idmodelo_ativo = t.idmodelo_antigo;

INSERT INTO campos_modelo (
    idcampo, idmodelo, nome, slug, rotulo, tipo_campo, escopo,
    idgrandeza, idunidade, obrigatorio, ordem, exibicao_padrao, grupo_ui
)
SELECT
    'fd' || substr(md5(mm.idmodelo || ':duracao'), 1, 19),
    mm.idmodelo,
    'duracao',
    'duracao',
    'Duração',
    'intervalo',
    'unidade',
    'g_tempo',
    NULL,
    FALSE,
    COALESCE((SELECT MAX(c.ordem) FROM campos_modelo c WHERE c.idmodelo = mm.idmodelo), 0) + 1,
    TRUE,
    'principal'
FROM modelos_modalidade mm
JOIN modalidades m ON m.idmodalidade = mm.idmodalidade
WHERE mm.ativo = TRUE
  AND mm.padrao = TRUE
  AND m.idusuario IS NULL
  AND NOT EXISTS (
      SELECT 1 FROM campos_modelo c
      WHERE c.idmodelo = mm.idmodelo AND lower(c.slug) = 'duracao'
  )
ON CONFLICT DO NOTHING;

INSERT INTO campos_modelo (
    idcampo, idmodelo, nome, slug, rotulo, tipo_campo, escopo,
    idgrandeza, idunidade, obrigatorio, ordem, exibicao_padrao, grupo_ui
)
SELECT
    'ds' || substr(md5(mm.idmodelo || ':distancia'), 1, 19),
    mm.idmodelo,
    'distancia',
    'distancia',
    'Distância',
    'decimal',
    'unidade',
    'g_distancia',
    CASE WHEN m.metrica_derivada IN ('pace_100m', 'split_500m') THEN 'u_m' ELSE 'u_km' END,
    FALSE,
    COALESCE((SELECT MAX(c.ordem) FROM campos_modelo c WHERE c.idmodelo = mm.idmodelo), 0) + 1,
    TRUE,
    'principal'
FROM modelos_modalidade mm
JOIN modalidades m ON m.idmodalidade = mm.idmodalidade
WHERE mm.ativo = TRUE
  AND mm.padrao = TRUE
  AND m.idusuario IS NULL
  AND m.metrica_derivada <> 'nenhuma'
  AND NOT EXISTS (
      SELECT 1 FROM campos_modelo c
      WHERE c.idmodelo = mm.idmodelo AND lower(c.slug) = 'distancia'
  )
ON CONFLICT DO NOTHING;

UPDATE campos_modelo
SET grupo_ui = 'principal', exibicao_padrao = TRUE
WHERE lower(slug) IN ('distancia', 'duracao');

UPDATE campos_modelo
SET grupo_ui = 'extras', exibicao_padrao = FALSE
WHERE obrigatorio = FALSE
  AND lower(slug) IN ('elevacao', 'terreno', 'sensacao', 'observacoes', 'intensidade');

CREATE INDEX IF NOT EXISTS ix_modalidades_catalogo
ON modalidades (categoria, ordem_catalogo, nome)
WHERE ativo = TRUE;

CREATE INDEX IF NOT EXISTS ix_modalidades_usuario_preferencias
ON modalidades_usuario (idusuario, favorita DESC, ordem_preferencia, ultimo_uso DESC)
WHERE ativo = TRUE;

COMMIT;


-- ============================================================================
-- Consolidated from: 20260822_dashboard_goals.sql
-- ============================================================================
BEGIN;

SET search_path TO stridebr, public;

CREATE TABLE IF NOT EXISTS metas_usuario (
    idmeta VARCHAR(21) PRIMARY KEY,
    idusuario VARCHAR(21) NOT NULL REFERENCES usuarios(idusuario) ON DELETE CASCADE,
    idmodalidade VARCHAR(21) REFERENCES modalidades(idmodalidade) ON DELETE SET NULL,
    nome VARCHAR(80),
    metrica VARCHAR(20) NOT NULL CHECK (metrica IN ('distancia', 'duracao', 'atividades', 'elevacao')),
    periodo VARCHAR(12) NOT NULL CHECK (periodo IN ('semanal', 'mensal', 'anual')),
    valor_alvo NUMERIC(14,3) NOT NULL CHECK (valor_alvo > 0),
    ativa BOOLEAN NOT NULL DEFAULT TRUE,
    data_criacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    data_atualizacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT ck_meta_nome CHECK (nome IS NULL OR length(trim(nome)) BETWEEN 1 AND 80)
);

CREATE INDEX IF NOT EXISTS ix_metas_usuario_ativas
    ON metas_usuario (idusuario, ativa, data_criacao DESC);

CREATE INDEX IF NOT EXISTS ix_metas_modalidade
    ON metas_usuario (idmodalidade)
    WHERE idmodalidade IS NOT NULL;

COMMIT;


-- ============================================================================
-- Consolidated from: 20260824_workout_logging_v2.sql
-- ============================================================================
BEGIN;

SET search_path TO stridebr, public;

ALTER TABLE treinos_cronograma
    ADD COLUMN IF NOT EXISTS codigo VARCHAR(24),
    ADD COLUMN IF NOT EXISTS foco VARCHAR(80);

CREATE INDEX IF NOT EXISTS ix_treinos_cronograma_codigo
    ON treinos_cronograma (idcronograma, codigo)
    WHERE codigo IS NOT NULL;

COMMIT;


-- ============================================================================
-- Consolidated from: 20260825_alpha_stability.sql
-- ============================================================================
BEGIN;

SET search_path TO stridebr, public;

WITH ranked AS (
    SELECT
        idcompartilhamento,
        ROW_NUMBER() OVER (
            PARTITION BY idcronograma_origem, idusuario_origem, idusuario_destino, tipo
            ORDER BY data_criacao DESC, idcompartilhamento DESC
        ) AS ordem
    FROM cronograma_compartilhamentos
    WHERE status = 'pendente'
      AND idcronograma_origem IS NOT NULL
)
UPDATE cronograma_compartilhamentos AS compartilhamento
SET status = 'revogado', data_atualizacao = NOW()
FROM ranked
WHERE compartilhamento.idcompartilhamento = ranked.idcompartilhamento
  AND ranked.ordem > 1;

CREATE UNIQUE INDEX IF NOT EXISTS ux_cronograma_compartilhamento_pendente
ON cronograma_compartilhamentos (idcronograma_origem, idusuario_origem, idusuario_destino, tipo)
WHERE status = 'pendente' AND idcronograma_origem IS NOT NULL;

COMMIT;


-- ============================================================================
-- Consolidated from: 20260825_daily_use_polish.sql
-- ============================================================================
BEGIN;
SET search_path TO stridebr, public;

ALTER TABLE usuarios
    ADD COLUMN IF NOT EXISTS senha_alterada_em TIMESTAMPTZ;

CREATE TABLE IF NOT EXISTS sessoes_usuario (
    sessao_hash CHAR(64) PRIMARY KEY,
    idusuario VARCHAR(21) NOT NULL REFERENCES usuarios(idusuario) ON DELETE CASCADE,
    criado_em TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    ultimo_uso_em TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    ip INET,
    user_agent VARCHAR(500),
    revogado_em TIMESTAMPTZ
);

CREATE INDEX IF NOT EXISTS ix_sessoes_usuario_usuario_ultimo_uso
    ON sessoes_usuario (idusuario, ultimo_uso_em DESC);

CREATE INDEX IF NOT EXISTS ix_sessoes_usuario_ativas
    ON sessoes_usuario (idusuario, ultimo_uso_em DESC)
    WHERE revogado_em IS NULL;

COMMIT;


-- ============================================================================
-- Consolidated from: 20260825_performance_pass.sql
-- ============================================================================
BEGIN;

SET search_path TO stridebr, public;

CREATE INDEX IF NOT EXISTS ix_registros_usuario_status_data
    ON registros_atividade (idusuario, status, data_inicio DESC);

CREATE INDEX IF NOT EXISTS ix_valores_atividade_registro
    ON valores_atividade (idregistro);

CREATE INDEX IF NOT EXISTS ix_valores_atividade_unidade
    ON valores_atividade (idunidade_atividade)
    WHERE idunidade_atividade IS NOT NULL;

COMMIT;


-- ============================================================================
-- Consolidated from: 20260825_product_polish_v1.sql
-- ============================================================================
BEGIN;

SET search_path TO stridebr, public;

ALTER TABLE metas_usuario
    ADD COLUMN IF NOT EXISTS data_inicio DATE,
    ADD COLUMN IF NOT EXISTS data_fim DATE,
    ADD COLUMN IF NOT EXISTS concluida_em TIMESTAMPTZ,
    ADD COLUMN IF NOT EXISTS arquivada_em TIMESTAMPTZ;

ALTER TABLE metas_usuario DROP CONSTRAINT IF EXISTS metas_usuario_metrica_check;
ALTER TABLE metas_usuario DROP CONSTRAINT IF EXISTS metas_usuario_periodo_check;
ALTER TABLE metas_usuario DROP CONSTRAINT IF EXISTS ck_metas_metrica;
ALTER TABLE metas_usuario DROP CONSTRAINT IF EXISTS ck_metas_periodo;
ALTER TABLE metas_usuario DROP CONSTRAINT IF EXISTS ck_metas_datas;

ALTER TABLE metas_usuario
    ADD CONSTRAINT ck_metas_metrica CHECK (metrica IN ('distancia', 'duracao', 'atividades', 'elevacao', 'dias_ativos')),
    ADD CONSTRAINT ck_metas_periodo CHECK (periodo IN ('semanal', 'mensal', 'anual', 'personalizado')),
    ADD CONSTRAINT ck_metas_datas CHECK (
        (periodo <> 'personalizado')
        OR (data_inicio IS NOT NULL AND data_fim IS NOT NULL AND data_fim >= data_inicio)
    );

CREATE INDEX IF NOT EXISTS ix_metas_usuario_historico
    ON metas_usuario (idusuario, concluida_em DESC NULLS LAST, arquivada_em DESC NULLS LAST, data_criacao DESC);

CREATE TABLE IF NOT EXISTS metas_conclusoes (
    idconclusao VARCHAR(21) PRIMARY KEY,
    idmeta VARCHAR(21) NOT NULL REFERENCES metas_usuario(idmeta) ON DELETE CASCADE,
    periodo_inicio DATE NOT NULL,
    periodo_fim DATE NOT NULL,
    valor_atingido NUMERIC(14,3) NOT NULL CHECK (valor_atingido >= 0),
    atingida_em TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (idmeta, periodo_inicio, periodo_fim)
);

CREATE INDEX IF NOT EXISTS ix_metas_conclusoes_meta
    ON metas_conclusoes (idmeta, atingida_em DESC);

CREATE TABLE IF NOT EXISTS eventos_esportivos (
    idevento VARCHAR(21) PRIMARY KEY,
    titulo VARCHAR(160) NOT NULL CHECK (length(trim(titulo)) BETWEEN 3 AND 160),
    slug VARCHAR(190) NOT NULL,
    idmodalidade VARCHAR(21) REFERENCES modalidades(idmodalidade) ON DELETE SET NULL,
    tipo VARCHAR(40),
    descricao TEXT,
    data_inicio TIMESTAMPTZ NOT NULL,
    data_fim TIMESTAMPTZ,
    cidade VARCHAR(100),
    estado VARCHAR(80),
    pais VARCHAR(80) NOT NULL DEFAULT 'Brasil',
    local_nome VARCHAR(160),
    endereco TEXT,
    organizador VARCHAR(160),
    distancias JSONB NOT NULL DEFAULT '[]'::jsonb,
    url_oficial TEXT,
    url_inscricao TEXT,
    inscricoes_ate TIMESTAMPTZ,
    status VARCHAR(20) NOT NULL DEFAULT 'rascunho' CHECK (status IN ('rascunho', 'publicado', 'cancelado', 'encerrado')),
    destaque BOOLEAN NOT NULL DEFAULT FALSE,
    criado_por VARCHAR(21) REFERENCES usuarios(idusuario) ON DELETE SET NULL,
    data_criacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    data_atualizacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT ck_eventos_periodo CHECK (data_fim IS NULL OR data_fim >= data_inicio),
    CONSTRAINT ck_eventos_urls CHECK (
        (url_oficial IS NULL OR url_oficial ~* '^https?://')
        AND (url_inscricao IS NULL OR url_inscricao ~* '^https?://')
    )
);

CREATE UNIQUE INDEX IF NOT EXISTS ux_eventos_slug ON eventos_esportivos (lower(slug));
CREATE INDEX IF NOT EXISTS ix_eventos_publicados_data ON eventos_esportivos (status, data_inicio);
CREATE INDEX IF NOT EXISTS ix_eventos_modalidade_data ON eventos_esportivos (idmodalidade, data_inicio) WHERE status = 'publicado';
CREATE INDEX IF NOT EXISTS ix_eventos_local_data ON eventos_esportivos (estado, cidade, data_inicio) WHERE status = 'publicado';

CREATE TABLE IF NOT EXISTS eventos_fontes (
    idfonte VARCHAR(21) PRIMARY KEY,
    idevento VARCHAR(21) NOT NULL REFERENCES eventos_esportivos(idevento) ON DELETE CASCADE,
    nome VARCHAR(120) NOT NULL CHECK (length(trim(nome)) BETWEEN 1 AND 120),
    url TEXT NOT NULL CHECK (url ~* '^https?://'),
    ordem SMALLINT NOT NULL DEFAULT 1 CHECK (ordem BETWEEN 1 AND 100),
    data_criacao TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS ix_eventos_fontes_evento ON eventos_fontes (idevento, ordem, idfonte);

CREATE TABLE IF NOT EXISTS eventos_imagens (
    idimagem VARCHAR(21) PRIMARY KEY,
    idevento VARCHAR(21) NOT NULL REFERENCES eventos_esportivos(idevento) ON DELETE CASCADE,
    caminho VARCHAR(255) NOT NULL,
    texto_alternativo VARCHAR(180),
    principal BOOLEAN NOT NULL DEFAULT FALSE,
    ordem SMALLINT NOT NULL DEFAULT 1 CHECK (ordem BETWEEN 1 AND 100),
    data_criacao TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS ix_eventos_imagens_evento ON eventos_imagens (idevento, principal DESC, ordem, idimagem);
CREATE UNIQUE INDEX IF NOT EXISTS ux_eventos_imagem_principal ON eventos_imagens (idevento) WHERE principal = TRUE;

CREATE TABLE IF NOT EXISTS eventos_salvos (
    idusuario VARCHAR(21) NOT NULL REFERENCES usuarios(idusuario) ON DELETE CASCADE,
    idevento VARCHAR(21) NOT NULL REFERENCES eventos_esportivos(idevento) ON DELETE CASCADE,
    data_criacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    PRIMARY KEY (idusuario, idevento)
);

CREATE INDEX IF NOT EXISTS ix_eventos_salvos_usuario ON eventos_salvos (idusuario, data_criacao DESC);

INSERT INTO feature_flags (chave, ativo, descricao)
VALUES ('events.enabled', TRUE, 'Exibe o calendário de eventos esportivos cadastrados pela administração.')
ON CONFLICT (chave) DO UPDATE
SET descricao = EXCLUDED.descricao;

UPDATE feature_flags
SET descricao = 'Exibe o canal permanente de feedback do StrideBR.'
WHERE chave = 'feedback.enabled';

UPDATE feature_flags
SET ativo = TRUE,
    descricao = 'Permite criar novas contas no StrideBR.'
WHERE chave = 'registration.enabled';

UPDATE feature_flags
SET ativo = FALSE,
    descricao = 'Quando ativado, exige um convite válido para criar conta.'
WHERE chave = 'registration.invite_only.enabled';

COMMIT;


-- ============================================================================
-- Consolidated from: 20260825_routes_v1.sql
-- ============================================================================
BEGIN;

SET search_path TO stridebr, public;

ALTER TABLE modalidades
    ADD COLUMN IF NOT EXISTS permite_rota BOOLEAN NOT NULL DEFAULT FALSE;

UPDATE modalidades
SET permite_rota = TRUE
WHERE idusuario IS NULL
  AND lower(slug) IN (
      'caminhada', 'corrida', 'trilha', 'ciclismo', 'mountain-bike',
      'marcha-atletica', 'downhill', 'bmx', 'patins', 'skate',
      'canoagem', 'caiaque', 'remo', 'vela', 'esqui', 'snowboard'
  );

ALTER TABLE rotas_atividade
    ADD COLUMN IF NOT EXISTS ganho_elevacao_m NUMERIC(12,2),
    ADD COLUMN IF NOT EXISTS perda_elevacao_m NUMERIC(12,2),
    ADD COLUMN IF NOT EXISTS elevacao_min_m NUMERIC(10,2),
    ADD COLUMN IF NOT EXISTS elevacao_max_m NUMERIC(10,2),
    ADD COLUMN IF NOT EXISTS perfil_elevacao JSONB,
    ADD COLUMN IF NOT EXISTS fonte_elevacao VARCHAR(40),
    ADD COLUMN IF NOT EXISTS data_atualizacao TIMESTAMPTZ NOT NULL DEFAULT NOW();

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'ck_rotas_elevacao_ganho_perda' AND conrelid = 'stridebr.rotas_atividade'::regclass) THEN
        ALTER TABLE rotas_atividade ADD CONSTRAINT ck_rotas_elevacao_ganho_perda
            CHECK ((ganho_elevacao_m IS NULL OR ganho_elevacao_m >= 0)
               AND (perda_elevacao_m IS NULL OR perda_elevacao_m >= 0));
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'ck_rotas_elevacao_faixa' AND conrelid = 'stridebr.rotas_atividade'::regclass) THEN
        ALTER TABLE rotas_atividade ADD CONSTRAINT ck_rotas_elevacao_faixa
            CHECK (elevacao_min_m IS NULL OR elevacao_max_m IS NULL OR elevacao_max_m >= elevacao_min_m);
    END IF;
END $$;

COMMIT;


-- ============================================================================
-- Consolidated from: 20260825_v1_release.sql
-- ============================================================================
BEGIN;

SET search_path TO stridebr, public;

ALTER TABLE metas_usuario DROP CONSTRAINT IF EXISTS metas_usuario_periodo_check;
ALTER TABLE metas_usuario DROP CONSTRAINT IF EXISTS ck_metas_periodo;
ALTER TABLE metas_usuario DROP CONSTRAINT IF EXISTS ck_metas_datas;

ALTER TABLE metas_usuario
    ADD CONSTRAINT ck_metas_periodo CHECK (
        periodo IN ('continuo', 'semanal', 'mensal', 'anual', 'personalizado')
    ),
    ADD CONSTRAINT ck_metas_datas CHECK (
        (periodo = 'personalizado' AND data_inicio IS NOT NULL AND data_fim IS NOT NULL AND data_fim >= data_inicio)
        OR (periodo = 'continuo' AND data_inicio IS NOT NULL AND data_fim IS NULL)
        OR (periodo IN ('semanal', 'mensal', 'anual') AND data_inicio IS NULL AND data_fim IS NULL)
    );

UPDATE usuarios
SET verificado = TRUE,
    email_verificado_em = COALESCE(email_verificado_em, NOW())
WHERE statususuario = 'Ativo'
  AND (verificado = FALSE OR verificado IS NULL)
  AND email_verificado_em IS NULL;

INSERT INTO feature_flags (chave, ativo, descricao)
VALUES
    ('auth.email_verification.enabled', TRUE, 'Envia confirmação de e-mail para novas contas quando o envio de e-mail está configurado.'),
    ('auth.email_verification.required', TRUE, 'Exige confirmação de e-mail para novas contas quando o envio de e-mail está configurado.'),
    ('auth.password_reset.enabled', TRUE, 'Permite redefinir a senha por link enviado ao e-mail da conta quando o envio está configurado.')
ON CONFLICT (chave) DO UPDATE
SET ativo = EXCLUDED.ativo,
    descricao = EXCLUDED.descricao;

COMMIT;


-- ============================================================================
-- Consolidated from: 20260826_planning_performance_v2.sql
-- ============================================================================
BEGIN;
SET search_path TO stridebr, public;

CREATE TABLE IF NOT EXISTS treinos_modelo (
    idtreino_modelo VARCHAR(21) PRIMARY KEY,
    idusuario VARCHAR(21) NOT NULL REFERENCES usuarios(idusuario) ON DELETE CASCADE,
    titulo VARCHAR(120) NOT NULL CHECK (length(trim(titulo)) > 0),
    codigo VARCHAR(24),
    foco VARCHAR(80),
    descricao TEXT,
    ativo BOOLEAN NOT NULL DEFAULT TRUE,
    data_criacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    data_atualizacao TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS ix_treinos_modelo_usuario ON treinos_modelo (idusuario, ativo, data_atualizacao DESC);

CREATE TABLE IF NOT EXISTS treinos_modelo_exercicios (
    idtreino_modelo_exercicio VARCHAR(21) PRIMARY KEY,
    idtreino_modelo VARCHAR(21) NOT NULL REFERENCES treinos_modelo(idtreino_modelo) ON DELETE CASCADE,
    idexercicio VARCHAR(21) REFERENCES exercicios(idexercicio) ON DELETE SET NULL,
    nome_snapshot VARCHAR(120) NOT NULL,
    series INTEGER CHECK (series IS NULL OR series > 0),
    repeticoes VARCHAR(40),
    carga VARCHAR(40),
    bloco VARCHAR(40),
    cluster VARCHAR(80),
    descanso VARCHAR(40),
    observacoes TEXT,
    duracao VARCHAR(40),
    distancia VARCHAR(40),
    intensidade VARCHAR(80),
    rpe NUMERIC(3,1) CHECK (rpe IS NULL OR rpe BETWEEN 0 AND 10),
    rir NUMERIC(3,1) CHECK (rir IS NULL OR rir BETWEEN 0 AND 10),
    tempo_execucao VARCHAR(40),
    cadencia VARCHAR(40),
    ordem INTEGER NOT NULL CHECK (ordem > 0),
    data_criacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (idtreino_modelo, ordem)
);

ALTER TABLE treinos_cronograma ADD COLUMN IF NOT EXISTS idtreino_modelo VARCHAR(21);
DO $$ BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'fk_treinos_cronograma_modelo' AND conrelid = 'treinos_cronograma'::regclass
    ) THEN
        ALTER TABLE treinos_cronograma ADD CONSTRAINT fk_treinos_cronograma_modelo
            FOREIGN KEY (idtreino_modelo) REFERENCES treinos_modelo(idtreino_modelo) ON DELETE SET NULL;
    END IF;
END $$;
CREATE INDEX IF NOT EXISTS ix_treinos_cronograma_modelo ON treinos_cronograma (idtreino_modelo) WHERE idtreino_modelo IS NOT NULL;

INSERT INTO treinos_modelo (idtreino_modelo, idusuario, titulo, codigo, foco, descricao, data_criacao, data_atualizacao)
SELECT substr(md5(tc.idtreino || ':' || c.idusuario), 1, 21), c.idusuario, tc.titulo, tc.codigo, tc.foco, tc.descricao, tc.data_criacao, tc.data_atualizacao
FROM treinos_cronograma tc
JOIN cronogramas c ON c.idcronograma = tc.idcronograma
WHERE tc.idtreino_modelo IS NULL
ON CONFLICT DO NOTHING;

UPDATE treinos_cronograma tc
SET idtreino_modelo = substr(md5(tc.idtreino || ':' || c.idusuario), 1, 21)
FROM cronogramas c
WHERE c.idcronograma = tc.idcronograma AND tc.idtreino_modelo IS NULL;

INSERT INTO treinos_modelo_exercicios (
    idtreino_modelo_exercicio, idtreino_modelo, idexercicio, nome_snapshot, series, repeticoes, carga, bloco, cluster, descanso,
    observacoes, duracao, distancia, intensidade, rpe, rir, tempo_execucao, cadencia, ordem, data_criacao
)
SELECT substr(md5(te.idtreino_exercicio || ':model'), 1, 21), tc.idtreino_modelo, te.idexercicio, te.nome_snapshot, te.series,
       te.repeticoes, te.carga, te.bloco, te.cluster, te.descanso, te.observacoes, te.duracao, te.distancia,
       te.intensidade, te.rpe, te.rir, te.tempo_execucao, te.cadencia, te.ordem, te.data_criacao
FROM treinos_exercicios te
JOIN treinos_cronograma tc ON tc.idtreino = te.idtreino
WHERE tc.idtreino_modelo IS NOT NULL
ON CONFLICT (idtreino_modelo, ordem) DO NOTHING;

CREATE INDEX IF NOT EXISTS ix_registros_atividade_usuario_data_id
ON registros_atividade (idusuario, data_inicio DESC, idregistro DESC);

CREATE INDEX IF NOT EXISTS ix_treino_excecoes_treino_datas
ON treinos_cronograma_excecoes (idtreino, data_original, data_treino);

COMMIT;


-- ============================================================================
-- Consolidated from: 20260827_activity_workout_library_polish.sql
-- ============================================================================
BEGIN;
SET search_path TO stridebr, public;

ALTER TABLE treinos_modelo ADD COLUMN IF NOT EXISTS idmodalidade VARCHAR(21);

DO $$ BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'fk_treinos_modelo_modalidade'
          AND conrelid = 'treinos_modelo'::regclass
    ) THEN
        ALTER TABLE treinos_modelo
            ADD CONSTRAINT fk_treinos_modelo_modalidade
            FOREIGN KEY (idmodalidade) REFERENCES modalidades(idmodalidade) ON DELETE SET NULL;
    END IF;
END $$;

UPDATE treinos_modelo tm
SET idmodalidade = inferred.idmodalidade
FROM (
    SELECT tm2.idtreino_modelo,
           (
               SELECT em.idmodalidade
               FROM treinos_modelo_exercicios tme
               JOIN exercicios_modalidades em ON em.idexercicio = tme.idexercicio
               JOIN modalidades m ON m.idmodalidade = em.idmodalidade AND m.ativo = TRUE
               WHERE tme.idtreino_modelo = tm2.idtreino_modelo
               GROUP BY em.idmodalidade, m.ordem_catalogo
               ORDER BY COUNT(*) DESC,
                        CASE em.idmodalidade WHEN 'm_musculacao' THEN 0 WHEN 'm_calistenia' THEN 1 ELSE 2 END,
                        m.ordem_catalogo
               LIMIT 1
           ) AS idmodalidade
    FROM treinos_modelo tm2
) inferred
WHERE tm.idtreino_modelo = inferred.idtreino_modelo
  AND tm.idmodalidade IS NULL
  AND inferred.idmodalidade IS NOT NULL;

UPDATE treinos_modelo
SET idmodalidade = 'm_musculacao'
WHERE idmodalidade IS NULL
  AND (
      lower(titulo) LIKE '%academia%'
      OR lower(titulo) LIKE '%muscula%'
      OR lower(COALESCE(foco, '')) LIKE '%muscula%'
  )
  AND EXISTS (SELECT 1 FROM modalidades WHERE idmodalidade = 'm_musculacao' AND ativo = TRUE);

UPDATE treinos_modelo
SET idmodalidade = 'm_calistenia'
WHERE idmodalidade IS NULL
  AND (
      lower(titulo) LIKE '%calisten%'
      OR lower(COALESCE(foco, '')) LIKE '%calisten%'
  )
  AND EXISTS (SELECT 1 FROM modalidades WHERE idmodalidade = 'm_calistenia' AND ativo = TRUE);

CREATE INDEX IF NOT EXISTS ix_treinos_modelo_modalidade
ON treinos_modelo (idmodalidade)
WHERE idmodalidade IS NOT NULL;

CREATE OR REPLACE FUNCTION fn_valida_registro_atividade()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
DECLARE
    v_modelo RECORD;
    v_modalidade RECORD;
    v_cronograma_usuario VARCHAR(21);
    v_treino RECORD;
    v_cronograma_alterado BOOLEAN;
    v_treino_alterado BOOLEAN;
    v_modelo_alterado BOOLEAN;
BEGIN
    IF TG_OP = 'UPDATE' AND NEW.idusuario <> OLD.idusuario THEN
        RAISE EXCEPTION 'O usuário de um registro histórico não pode ser alterado.';
    END IF;

    IF TG_OP = 'INSERT' THEN
        v_modelo_alterado := TRUE;
    ELSE
        v_modelo_alterado := NEW.idmodalidade IS DISTINCT FROM OLD.idmodalidade
            OR NEW.idmodelo IS DISTINCT FROM OLD.idmodelo;
    END IF;

    SELECT idmodalidade, idusuario, ativo
      INTO v_modelo
      FROM stridebr.modelos_modalidade
     WHERE idmodelo = NEW.idmodelo;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'Modelo inválido.';
    END IF;

    IF v_modelo_alterado AND NOT v_modelo.ativo THEN
        RAISE EXCEPTION 'Modelo inativo.';
    END IF;

    IF v_modelo.idmodalidade <> NEW.idmodalidade THEN
        RAISE EXCEPTION 'O modelo não pertence à modalidade.';
    END IF;

    IF v_modelo.idusuario IS NOT NULL AND v_modelo.idusuario <> NEW.idusuario THEN
        RAISE EXCEPTION 'O modelo pertence a outro usuário.';
    END IF;

    SELECT idusuario, ativo
      INTO v_modalidade
      FROM stridebr.modalidades
     WHERE idmodalidade = NEW.idmodalidade;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'Modalidade inválida.';
    END IF;

    IF v_modelo_alterado AND NOT v_modalidade.ativo THEN
        RAISE EXCEPTION 'Modalidade inativa.';
    END IF;

    IF v_modalidade.idusuario IS NOT NULL AND v_modalidade.idusuario <> NEW.idusuario THEN
        RAISE EXCEPTION 'A modalidade pertence a outro usuário.';
    END IF;

    IF TG_OP = 'INSERT' THEN
        v_cronograma_alterado := TRUE;
        v_treino_alterado := TRUE;
    ELSE
        v_cronograma_alterado := NEW.idcronograma IS DISTINCT FROM OLD.idcronograma;
        v_treino_alterado := NEW.idtreino_cronograma IS DISTINCT FROM OLD.idtreino_cronograma;
    END IF;

    IF v_cronograma_alterado AND NEW.idcronograma IS NOT NULL THEN
        SELECT idusuario
          INTO v_cronograma_usuario
          FROM stridebr.cronogramas
         WHERE idcronograma = NEW.idcronograma;

        IF NOT FOUND OR v_cronograma_usuario <> NEW.idusuario THEN
            RAISE EXCEPTION 'Cronograma inválido para este usuário.';
        END IF;
    END IF;

    IF NEW.idtreino_cronograma IS NOT NULL
       AND (v_treino_alterado OR (v_cronograma_alterado AND NEW.idcronograma IS NOT NULL)) THEN
        SELECT t.idcronograma, c.idusuario
          INTO v_treino
          FROM stridebr.treinos_cronograma t
          JOIN stridebr.cronogramas c ON c.idcronograma = t.idcronograma
         WHERE t.idtreino = NEW.idtreino_cronograma;

        IF NOT FOUND OR v_treino.idusuario <> NEW.idusuario THEN
            RAISE EXCEPTION 'Treino de cronograma inválido para este usuário.';
        END IF;

        IF NEW.idcronograma IS NOT NULL AND v_treino.idcronograma <> NEW.idcronograma THEN
            RAISE EXCEPTION 'O treino não pertence ao cronograma informado.';
        END IF;
    END IF;

    RETURN NEW;
END;
$$;

COMMIT;


-- ============================================================================
-- Consolidated from: 20260827_schedule_reconciliation.sql
-- ============================================================================
BEGIN;
SET search_path TO stridebr, public;

ALTER TABLE registros_atividade ADD COLUMN IF NOT EXISTS data_ocorrencia_origem DATE;
ALTER TABLE registros_atividade ADD COLUMN IF NOT EXISTS data_ocorrencia_planejada DATE;

ALTER TABLE sessoes_treino ADD COLUMN IF NOT EXISTS data_ocorrencia_planejada DATE;

CREATE INDEX IF NOT EXISTS ix_registros_atividade_treino_ocorrencia
ON registros_atividade (idusuario, idtreino_cronograma, data_ocorrencia_origem)
WHERE idtreino_cronograma IS NOT NULL;

CREATE INDEX IF NOT EXISTS ix_registros_atividade_cronograma_data
ON registros_atividade (idusuario, idcronograma, data_inicio DESC)
WHERE idcronograma IS NOT NULL;

CREATE TABLE IF NOT EXISTS cronograma_semana_preferencias (
    idusuario VARCHAR(21) NOT NULL REFERENCES usuarios(idusuario) ON DELETE CASCADE,
    idcronograma VARCHAR(21) NOT NULL REFERENCES cronogramas(idcronograma) ON DELETE CASCADE,
    semana_inicio DATE NOT NULL,
    idtreino_proximo VARCHAR(21) REFERENCES treinos_cronograma(idtreino) ON DELETE SET NULL,
    data_atualizacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    PRIMARY KEY (idusuario, idcronograma, semana_inicio)
);

CREATE INDEX IF NOT EXISTS ix_cronograma_semana_preferencias_cronograma
ON cronograma_semana_preferencias (idcronograma, semana_inicio DESC);

COMMIT;


-- ============================================================================
-- Consolidated from: 20260828_goals_flexibility_v2.sql
-- ============================================================================
BEGIN;

SET search_path TO stridebr, public;

ALTER TABLE metas_usuario
    ALTER COLUMN periodo TYPE VARCHAR(20),
    ALTER COLUMN metrica TYPE VARCHAR(32),
    ADD COLUMN IF NOT EXISTS idexercicio VARCHAR(21) REFERENCES exercicios(idexercicio) ON DELETE SET NULL;

ALTER TABLE metas_usuario DROP CONSTRAINT IF EXISTS metas_usuario_metrica_check;
ALTER TABLE metas_usuario DROP CONSTRAINT IF EXISTS metas_usuario_periodo_check;
ALTER TABLE metas_usuario DROP CONSTRAINT IF EXISTS ck_metas_metrica;
ALTER TABLE metas_usuario DROP CONSTRAINT IF EXISTS ck_metas_periodo;
ALTER TABLE metas_usuario DROP CONSTRAINT IF EXISTS ck_metas_datas;
ALTER TABLE metas_usuario DROP CONSTRAINT IF EXISTS ck_metas_exercicio;

ALTER TABLE metas_usuario
    ADD CONSTRAINT ck_metas_metrica CHECK (metrica IN ('distancia', 'duracao', 'atividades', 'elevacao', 'dias_ativos', 'carga_maxima')),
    ADD CONSTRAINT ck_metas_periodo CHECK (periodo IN ('continuo', 'semanal', 'mensal', 'anual', 'personalizado')),
    ADD CONSTRAINT ck_metas_datas CHECK (
        (periodo = 'personalizado' AND data_inicio IS NOT NULL AND data_fim IS NOT NULL AND data_fim >= data_inicio)
        OR (periodo = 'continuo' AND data_inicio IS NOT NULL AND data_fim IS NULL)
        OR (periodo IN ('semanal', 'mensal', 'anual') AND data_inicio IS NULL AND data_fim IS NULL)
    ),
    ADD CONSTRAINT ck_metas_exercicio CHECK (metrica <> 'carga_maxima' OR idexercicio IS NOT NULL);

CREATE INDEX IF NOT EXISTS ix_metas_usuario_exercicio
    ON metas_usuario (idusuario, idexercicio)
    WHERE idexercicio IS NOT NULL;

COMMIT;


-- ============================================================================
-- Consolidated from: 20260828_schedule_editing_hardening.sql
-- ============================================================================
BEGIN;
SET search_path TO stridebr, public;

ALTER TABLE treinos_cronograma ADD COLUMN IF NOT EXISTS idmodalidade VARCHAR(21);

DO $$ BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'fk_treinos_cronograma_modalidade'
          AND conrelid = 'treinos_cronograma'::regclass
    ) THEN
        ALTER TABLE treinos_cronograma
            ADD CONSTRAINT fk_treinos_cronograma_modalidade
            FOREIGN KEY (idmodalidade) REFERENCES modalidades(idmodalidade) ON DELETE SET NULL;
    END IF;
END $$;

ALTER TABLE treinos_cronograma_excecoes ADD COLUMN IF NOT EXISTS codigo VARCHAR(24);
ALTER TABLE treinos_cronograma_excecoes ADD COLUMN IF NOT EXISTS foco VARCHAR(80);
ALTER TABLE treinos_cronograma_excecoes ADD COLUMN IF NOT EXISTS idmodalidade VARCHAR(21);

DO $$ BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'fk_treino_excecao_modalidade'
          AND conrelid = 'treinos_cronograma_excecoes'::regclass
    ) THEN
        ALTER TABLE treinos_cronograma_excecoes
            ADD CONSTRAINT fk_treino_excecao_modalidade
            FOREIGN KEY (idmodalidade) REFERENCES modalidades(idmodalidade) ON DELETE SET NULL;
    END IF;
END $$;

UPDATE treinos_cronograma tc
SET idmodalidade = tm.idmodalidade
FROM treinos_modelo tm
WHERE tc.idtreino_modelo = tm.idtreino_modelo
  AND tc.idmodalidade IS NULL
  AND tm.idmodalidade IS NOT NULL;

UPDATE treinos_cronograma tc
SET idmodalidade = inferred.idmodalidade
FROM (
    SELECT tc2.idtreino,
           (
               SELECT em.idmodalidade
               FROM treinos_exercicios te
               JOIN exercicios_modalidades em ON em.idexercicio = te.idexercicio
               JOIN modalidades m ON m.idmodalidade = em.idmodalidade AND m.ativo = TRUE
               WHERE te.idtreino = tc2.idtreino
               GROUP BY em.idmodalidade, m.ordem_catalogo
               ORDER BY COUNT(*) DESC,
                        CASE em.idmodalidade WHEN 'm_musculacao' THEN 0 WHEN 'm_calistenia' THEN 1 ELSE 2 END,
                        m.ordem_catalogo
               LIMIT 1
           ) AS idmodalidade
    FROM treinos_cronograma tc2
) inferred
WHERE tc.idtreino = inferred.idtreino
  AND tc.idmodalidade IS NULL
  AND inferred.idmodalidade IS NOT NULL;

UPDATE treinos_cronograma
SET idmodalidade = 'm_musculacao'
WHERE idmodalidade IS NULL
  AND (lower(titulo) LIKE '%academia%' OR lower(titulo) LIKE '%muscula%' OR lower(COALESCE(foco, '')) LIKE '%muscula%')
  AND EXISTS (SELECT 1 FROM modalidades WHERE idmodalidade = 'm_musculacao' AND ativo = TRUE);

UPDATE treinos_cronograma
SET idmodalidade = 'm_calistenia'
WHERE idmodalidade IS NULL
  AND (lower(titulo) LIKE '%calisten%' OR lower(COALESCE(foco, '')) LIKE '%calisten%')
  AND EXISTS (SELECT 1 FROM modalidades WHERE idmodalidade = 'm_calistenia' AND ativo = TRUE);

CREATE INDEX IF NOT EXISTS ix_treinos_cronograma_modalidade
ON treinos_cronograma (idmodalidade)
WHERE idmodalidade IS NOT NULL;

COMMIT;


-- ============================================================================
-- Consolidated from: 20260828_schedule_history_flexibility.sql
-- ============================================================================
BEGIN;
SET search_path TO stridebr, public;

ALTER TABLE registros_atividade
    ADD COLUMN IF NOT EXISTS hora_ocorrencia_planejada TIME;

ALTER TABLE sessoes_treino
    ADD COLUMN IF NOT EXISTS hora_ocorrencia_planejada TIME;

COMMIT;


-- ============================================================================
-- Consolidated from: 20260829_activity_file_exchange.sql
-- ============================================================================
BEGIN;
SET search_path TO stridebr, public;

CREATE TABLE IF NOT EXISTS atividade_importacoes (
    idimportacao VARCHAR(21) PRIMARY KEY,
    idusuario VARCHAR(21) NOT NULL REFERENCES usuarios(idusuario) ON DELETE CASCADE,
    idregistro VARCHAR(21) REFERENCES registros_atividade(idregistro) ON DELETE SET NULL,
    formato VARCHAR(8) NOT NULL CHECK (formato IN ('fit', 'tcx', 'gpx')),
    tipo_arquivo VARCHAR(20) NOT NULL DEFAULT 'desconhecido' CHECK (tipo_arquivo IN ('atividade', 'percurso', 'treino', 'desconhecido')),
    nome_arquivo VARCHAR(255) NOT NULL,
    mime_type VARCHAR(120),
    tamanho_bytes INTEGER NOT NULL CHECK (tamanho_bytes >= 0 AND tamanho_bytes <= 26214400),
    sha256 CHAR(64) NOT NULL,
    modalidade_detectada VARCHAR(120),
    resumo JSONB NOT NULL DEFAULT '{}'::jsonb,
    series_temporais JSONB NOT NULL DEFAULT '[]'::jsonb,
    rota_geojson JSONB,
    dispositivo JSONB NOT NULL DEFAULT '{}'::jsonb,
    arquivo_original BYTEA,
    status VARCHAR(20) NOT NULL DEFAULT 'pendente' CHECK (status IN ('pendente', 'importado', 'descartado', 'erro')),
    data_criacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    data_atualizacao TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS ix_atividade_importacoes_usuario_data
ON atividade_importacoes (idusuario, data_criacao DESC);

CREATE INDEX IF NOT EXISTS ix_atividade_importacoes_registro
ON atividade_importacoes (idregistro)
WHERE idregistro IS NOT NULL;

CREATE INDEX IF NOT EXISTS ix_atividade_importacoes_hash
ON atividade_importacoes (idusuario, sha256, status);

COMMIT;


-- ============================================================================
-- Consolidated from: 20260829_performance_round_v2.sql
-- ============================================================================
BEGIN;
SET search_path TO stridebr, public;

CREATE INDEX IF NOT EXISTS ix_unidades_atividade_registro_ordem
ON unidades_atividade (idregistro, ordem, idunidade_atividade);

CREATE INDEX IF NOT EXISTS ix_sessoes_concluidas_usuario_data
ON sessoes_treino (idusuario, COALESCE(data_fim, data_inicio) DESC)
WHERE status = 'concluido';

CREATE INDEX IF NOT EXISTS ix_sessao_exercicios_exercicio_sessao
ON sessoes_treino_exercicios (idexercicio, idsessao)
WHERE idexercicio IS NOT NULL;

CREATE INDEX IF NOT EXISTS ix_sessao_exercicios_nome_sessao
ON sessoes_treino_exercicios (lower(nome_snapshot), idsessao);

CREATE INDEX IF NOT EXISTS ix_atividade_importacoes_usuario_registro_data
ON atividade_importacoes (idusuario, idregistro, data_criacao DESC)
WHERE status = 'importado' AND idregistro IS NOT NULL;

COMMIT;


-- ============================================================================
-- Consolidated from: 20260829_strength_activity_fields_v3.sql
-- ============================================================================
BEGIN;
SET search_path TO stridebr, public;

CREATE TEMP TABLE tmp_strength_model_upgrade ON COMMIT DROP AS
SELECT
    mm.idmodelo AS idmodelo_antigo,
    'sv' || substr(md5(mm.idmodelo || ':strength-v3'), 1, 19) AS idmodelo_novo,
    mm.padrao AS era_padrao,
    EXISTS (
        SELECT 1
        FROM registros_atividade ra
        WHERE ra.idmodelo = mm.idmodelo
    ) AS usado_historicamente
FROM modelos_modalidade mm
JOIN modalidades m ON m.idmodalidade = mm.idmodalidade
WHERE mm.ativo = TRUE
  AND m.slug IN ('musculacao', 'calistenia', 'crossfit')
  AND (
      NOT EXISTS (
          SELECT 1 FROM campos_modelo c
          WHERE c.idmodelo = mm.idmodelo AND lower(c.slug) = 'codigo_treino'
      )
      OR NOT EXISTS (
          SELECT 1 FROM campos_modelo c
          WHERE c.idmodelo = mm.idmodelo AND lower(c.slug) = 'foco_muscular'
      )
  );

UPDATE modelos_modalidade mm
SET padrao = FALSE,
    ativo = FALSE
FROM tmp_strength_model_upgrade t
WHERE t.usado_historicamente = TRUE
  AND mm.idmodelo = t.idmodelo_antigo;

INSERT INTO modelos_modalidade (
    idmodelo, idmodalidade, idusuario, idmodelo_anterior, nome, slug, descricao,
    tipo_unidade_padrao, rotulo_unidade, permite_multiplas_unidades, versao,
    padrao, ativo, visibilidade, status_publicacao
)
SELECT
    t.idmodelo_novo,
    antigo.idmodalidade,
    antigo.idusuario,
    antigo.idmodelo,
    antigo.nome,
    antigo.slug,
    antigo.descricao,
    antigo.tipo_unidade_padrao,
    antigo.rotulo_unidade,
    antigo.permite_multiplas_unidades,
    COALESCE((
        SELECT MAX(v.versao)
        FROM modelos_modalidade v
        WHERE v.idmodalidade = antigo.idmodalidade
          AND lower(v.slug) = lower(antigo.slug)
          AND v.idusuario IS NOT DISTINCT FROM antigo.idusuario
    ), antigo.versao) + 1,
    t.era_padrao,
    TRUE,
    antigo.visibilidade,
    antigo.status_publicacao
FROM tmp_strength_model_upgrade t
JOIN modelos_modalidade antigo ON antigo.idmodelo = t.idmodelo_antigo
WHERE t.usado_historicamente = TRUE
ON CONFLICT (idmodelo) DO NOTHING;

INSERT INTO campos_modelo (
    idcampo, idmodelo, nome, slug, rotulo, tipo_campo, escopo,
    idgrandeza, idunidade, obrigatorio, ordem, ativo, exibicao_padrao, grupo_ui
)
SELECT
    'vc' || substr(md5(t.idmodelo_novo || ':' || c.idcampo), 1, 19),
    t.idmodelo_novo,
    c.nome,
    c.slug,
    c.rotulo,
    c.tipo_campo,
    c.escopo,
    c.idgrandeza,
    c.idunidade,
    c.obrigatorio,
    c.ordem,
    c.ativo,
    c.exibicao_padrao,
    c.grupo_ui
FROM tmp_strength_model_upgrade t
JOIN campos_modelo c ON c.idmodelo = t.idmodelo_antigo
WHERE t.usado_historicamente = TRUE
ON CONFLICT DO NOTHING;

INSERT INTO campos_modelo_opcoes (
    idopcao, idcampo, rotulo, valor, ordem, ativo
)
SELECT
    'vo' || substr(md5(novo_campo.idcampo || ':' || o.idopcao), 1, 19),
    novo_campo.idcampo,
    o.rotulo,
    o.valor,
    o.ordem,
    o.ativo
FROM tmp_strength_model_upgrade t
JOIN campos_modelo campo_antigo ON campo_antigo.idmodelo = t.idmodelo_antigo
JOIN campos_modelo_opcoes o ON o.idcampo = campo_antigo.idcampo
JOIN campos_modelo novo_campo
  ON novo_campo.idmodelo = t.idmodelo_novo
 AND lower(novo_campo.slug) = lower(campo_antigo.slug)
WHERE t.usado_historicamente = TRUE
ON CONFLICT DO NOTHING;

UPDATE modalidades_usuario mu
SET idmodelo_ativo = t.idmodelo_novo
FROM tmp_strength_model_upgrade t
WHERE t.usado_historicamente = TRUE
  AND mu.idmodelo_ativo = t.idmodelo_antigo;

CREATE TEMP TABLE tmp_strength_editable_models ON COMMIT DROP AS
SELECT
    CASE WHEN usado_historicamente THEN idmodelo_novo ELSE idmodelo_antigo END AS idmodelo
FROM tmp_strength_model_upgrade;

INSERT INTO campos_modelo (
    idcampo, idmodelo, nome, slug, rotulo, tipo_campo, escopo,
    idgrandeza, idunidade, obrigatorio, ordem, ativo, exibicao_padrao, grupo_ui
)
SELECT
    'sc' || substr(md5(t.idmodelo || ':codigo_treino'), 1, 19),
    t.idmodelo,
    'codigo_treino',
    'codigo_treino',
    'Código do treino',
    'texto',
    'registro',
    NULL,
    NULL,
    FALSE,
    COALESCE((SELECT MAX(c.ordem) FROM campos_modelo c WHERE c.idmodelo = t.idmodelo), 0) + 1,
    TRUE,
    TRUE,
    'principal'
FROM tmp_strength_editable_models t
WHERE NOT EXISTS (
    SELECT 1 FROM campos_modelo c
    WHERE c.idmodelo = t.idmodelo AND lower(c.slug) = 'codigo_treino'
)
ON CONFLICT DO NOTHING;

INSERT INTO campos_modelo (
    idcampo, idmodelo, nome, slug, rotulo, tipo_campo, escopo,
    idgrandeza, idunidade, obrigatorio, ordem, ativo, exibicao_padrao, grupo_ui
)
SELECT
    'sf' || substr(md5(t.idmodelo || ':foco_muscular'), 1, 19),
    t.idmodelo,
    'foco_muscular',
    'foco_muscular',
    'Foco muscular',
    'texto',
    'registro',
    NULL,
    NULL,
    FALSE,
    COALESCE((SELECT MAX(c.ordem) FROM campos_modelo c WHERE c.idmodelo = t.idmodelo), 0) + 1,
    TRUE,
    TRUE,
    'principal'
FROM tmp_strength_editable_models t
WHERE NOT EXISTS (
    SELECT 1 FROM campos_modelo c
    WHERE c.idmodelo = t.idmodelo AND lower(c.slug) = 'foco_muscular'
)
ON CONFLICT DO NOTHING;

COMMIT;


-- ============================================================================
-- Consolidated from: 20260830_activity_sharing_v2.sql
-- ============================================================================
BEGIN;
SET search_path TO stridebr, public;

CREATE TABLE IF NOT EXISTS rotas_unidades_atividade (
    idrota_unidade VARCHAR(21) PRIMARY KEY,
    idunidade_atividade VARCHAR(21) NOT NULL UNIQUE REFERENCES unidades_atividade(idunidade_atividade) ON DELETE CASCADE,
    idregistro VARCHAR(21) NOT NULL REFERENCES registros_atividade(idregistro) ON DELETE CASCADE,
    modo VARCHAR(30) NOT NULL DEFAULT 'manual',
    coordenadas JSONB NOT NULL,
    distancia_metros NUMERIC(14,3) NOT NULL CHECK (distancia_metros > 0),
    ganho_elevacao_m NUMERIC(12,3),
    perda_elevacao_m NUMERIC(12,3),
    elevacao_min_m NUMERIC(12,3),
    elevacao_max_m NUMERIC(12,3),
    perfil_elevacao JSONB,
    fonte_elevacao VARCHAR(80),
    data_criacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    data_atualizacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT ck_rotas_unidades_coordenadas CHECK (jsonb_typeof(coordenadas) = 'object')
);

CREATE INDEX IF NOT EXISTS ix_rotas_unidades_registro
ON rotas_unidades_atividade (idregistro, idunidade_atividade);

COMMIT;


-- ============================================================================
-- Consolidated from: 20260830_desktop_release_candidate_features.sql
-- ============================================================================
BEGIN;
SET search_path TO stridebr, public;

ALTER TABLE registros_atividade ADD COLUMN IF NOT EXISTS excluido_em TIMESTAMPTZ;
ALTER TABLE registros_atividade ADD COLUMN IF NOT EXISTS ocultar_inicio_m INTEGER NOT NULL DEFAULT 0;
ALTER TABLE registros_atividade ADD COLUMN IF NOT EXISTS ocultar_fim_m INTEGER NOT NULL DEFAULT 0;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'ck_registros_ocultar_inicio_m'
          AND conrelid = 'registros_atividade'::regclass
    ) THEN
        ALTER TABLE registros_atividade
        ADD CONSTRAINT ck_registros_ocultar_inicio_m CHECK (ocultar_inicio_m BETWEEN 0 AND 10000);
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'ck_registros_ocultar_fim_m'
          AND conrelid = 'registros_atividade'::regclass
    ) THEN
        ALTER TABLE registros_atividade
        ADD CONSTRAINT ck_registros_ocultar_fim_m CHECK (ocultar_fim_m BETWEEN 0 AND 10000);
    END IF;
END $$;

CREATE INDEX IF NOT EXISTS ix_registros_usuario_ativos_data
ON registros_atividade (idusuario, data_inicio DESC)
WHERE excluido_em IS NULL;

CREATE TABLE IF NOT EXISTS notificacoes (
    idnotificacao VARCHAR(21) PRIMARY KEY,
    idusuario VARCHAR(21) NOT NULL REFERENCES usuarios(idusuario) ON DELETE CASCADE,
    tipo VARCHAR(50) NOT NULL,
    titulo VARCHAR(160) NOT NULL,
    mensagem TEXT,
    url TEXT,
    dados JSONB NOT NULL DEFAULT '{}'::jsonb,
    lida_em TIMESTAMPTZ,
    data_criacao TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS ix_notificacoes_usuario_data
ON notificacoes (idusuario, data_criacao DESC);

CREATE INDEX IF NOT EXISTS ix_notificacoes_usuario_nao_lidas
ON notificacoes (idusuario, data_criacao DESC)
WHERE lida_em IS NULL;

CREATE TABLE IF NOT EXISTS eventos_produto (
    idevento BIGSERIAL PRIMARY KEY,
    idusuario VARCHAR(21) REFERENCES usuarios(idusuario) ON DELETE SET NULL,
    nome VARCHAR(80) NOT NULL,
    contexto VARCHAR(80),
    duracao_ms INTEGER,
    dados JSONB NOT NULL DEFAULT '{}'::jsonb,
    data_criacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT ck_eventos_produto_duracao CHECK (duracao_ms IS NULL OR duracao_ms BETWEEN 0 AND 86400000)
);

CREATE INDEX IF NOT EXISTS ix_eventos_produto_nome_data
ON eventos_produto (nome, data_criacao DESC);

CREATE INDEX IF NOT EXISTS ix_eventos_produto_usuario_data
ON eventos_produto (idusuario, data_criacao DESC)
WHERE idusuario IS NOT NULL;

CREATE INDEX IF NOT EXISTS ix_cronograma_compartilhamentos_sync_destino
ON cronograma_compartilhamentos (idusuario_destino, status, data_atualizacao DESC)
WHERE tipo = 'sincronizado';

INSERT INTO feature_flags (chave, ativo, descricao) VALUES
('synced_schedules.enabled', TRUE, 'Ativa cronogramas sincronizados entre amigos.'),
('notifications.enabled', TRUE, 'Ativa a central de notificações internas.'),
('product_analytics.enabled', TRUE, 'Ativa analytics de produto limitados e sem conteúdo pessoal.')
ON CONFLICT (chave) DO UPDATE SET ativo = EXCLUDED.ativo, descricao = EXCLUDED.descricao, data_atualizacao = NOW();

COMMIT;


-- ============================================================================
-- Consolidated from: 20260830_gps_web.sql
-- ============================================================================
BEGIN;
SET search_path TO stridebr, public;

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

CREATE INDEX IF NOT EXISTS ix_gravacoes_gps_web_iniciado ON gravacoes_gps_web (iniciado_em DESC);

COMMIT;


-- ============================================================================
-- Consolidated from: 20260831_activity_segments_v2.sql
-- ============================================================================
BEGIN;
SET search_path TO stridebr, public;

ALTER TABLE registros_atividade
    ADD COLUMN IF NOT EXISTS usa_trechos BOOLEAN NOT NULL DEFAULT FALSE;

ALTER TABLE unidades_atividade
    ADD COLUMN IF NOT EXISTS idmodalidade VARCHAR(21),
    ADD COLUMN IF NOT EXISTS distancia_metros NUMERIC(12,3),
    ADD COLUMN IF NOT EXISTS duracao_segundos INTEGER,
    ADD COLUMN IF NOT EXISTS elevacao_m NUMERIC(10,2);

ALTER TABLE unidades_atividade
    DROP CONSTRAINT IF EXISTS unidades_atividade_distancia_metros_check,
    DROP CONSTRAINT IF EXISTS unidades_atividade_duracao_segundos_check,
    DROP CONSTRAINT IF EXISTS unidades_atividade_elevacao_m_check;

ALTER TABLE unidades_atividade
    ADD CONSTRAINT unidades_atividade_distancia_metros_check CHECK (distancia_metros IS NULL OR distancia_metros >= 0),
    ADD CONSTRAINT unidades_atividade_duracao_segundos_check CHECK (duracao_segundos IS NULL OR duracao_segundos >= 0),
    ADD CONSTRAINT unidades_atividade_elevacao_m_check CHECK (elevacao_m IS NULL OR elevacao_m >= 0);

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'fk_unidades_atividade_modalidade'
          AND conrelid = 'stridebr.unidades_atividade'::regclass
    ) THEN
        ALTER TABLE unidades_atividade
            ADD CONSTRAINT fk_unidades_atividade_modalidade
            FOREIGN KEY (idmodalidade)
            REFERENCES modalidades(idmodalidade)
            ON DELETE SET NULL;
    END IF;
END $$;

CREATE INDEX IF NOT EXISTS ix_unidades_atividade_modalidade
ON unidades_atividade (idmodalidade);

UPDATE unidades_atividade ua
SET idmodalidade = ra.idmodalidade
FROM registros_atividade ra
WHERE ra.idregistro = ua.idregistro
  AND ua.idmodalidade IS NULL;

WITH metricas_legadas AS (
    SELECT va.idunidade_atividade,
           MAX(va.valor_normalizado) FILTER (WHERE lower(cm.slug) = 'distancia') AS distancia_metros,
           MAX(va.valor_normalizado) FILTER (WHERE lower(cm.slug) = 'duracao') AS duracao_segundos,
           MAX(va.valor_normalizado) FILTER (WHERE lower(cm.slug) IN ('elevacao', 'desnivel')) AS elevacao_m
    FROM valores_atividade va
    JOIN campos_modelo cm ON cm.idcampo = va.idcampo
    WHERE va.idunidade_atividade IS NOT NULL
      AND va.valor_normalizado IS NOT NULL
    GROUP BY va.idunidade_atividade
)
UPDATE unidades_atividade ua
SET distancia_metros = COALESCE(ua.distancia_metros, ml.distancia_metros),
    duracao_segundos = COALESCE(ua.duracao_segundos, ROUND(ml.duracao_segundos)::INTEGER),
    elevacao_m = COALESCE(ua.elevacao_m, ml.elevacao_m)
FROM metricas_legadas ml
WHERE ml.idunidade_atividade = ua.idunidade_atividade
  AND (
      ua.distancia_metros IS NULL
      OR ua.duracao_segundos IS NULL
      OR ua.elevacao_m IS NULL
  );

UPDATE unidades_atividade ua
SET distancia_metros = COALESCE(ua.distancia_metros, ru.distancia_metros),
    elevacao_m = COALESCE(ua.elevacao_m, ru.ganho_elevacao_m)
FROM rotas_unidades_atividade ru
WHERE ru.idunidade_atividade = ua.idunidade_atividade
  AND (ua.distancia_metros IS NULL OR ua.elevacao_m IS NULL);

UPDATE registros_atividade ra
SET usa_trechos = TRUE
WHERE usa_trechos = FALSE
  AND EXISTS (
      SELECT 1
      FROM unidades_atividade ua
      WHERE ua.idregistro = ra.idregistro
      GROUP BY ua.idregistro
      HAVING COUNT(*) > 1
  );

COMMIT;


-- ============================================================================
-- Consolidated from: 20260831_i18n_theme_google_auth.sql
-- ============================================================================
BEGIN;
SET search_path TO stridebr, public;

ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS google_sub VARCHAR(255);
CREATE UNIQUE INDEX IF NOT EXISTS ux_usuarios_google_sub ON usuarios (google_sub) WHERE google_sub IS NOT NULL;

COMMIT;


-- ============================================================================
-- Consolidated from: 20260831_triathlon_share_polish.sql
-- ============================================================================
BEGIN;
SET search_path TO stridebr, public;

INSERT INTO modalidades (
    idmodalidade, nome, slug, descricao, visibilidade, status_publicacao,
    categoria, icone, ordem_catalogo, metrica_derivada, permite_rota
) VALUES (
    'm_triatlo', 'Triatlo', 'triatlo', 'Sessão multiesporte com etapas de natação, ciclismo, corrida e outras modalidades.',
    'publico', 'publicado', 'Multiesporte', '🏊', 180, 'nenhuma', FALSE
)
ON CONFLICT DO NOTHING;

INSERT INTO modelos_modalidade (
    idmodelo, idmodalidade, nome, slug, descricao, tipo_unidade_padrao,
    rotulo_unidade, permite_multiplas_unidades, versao, padrao, visibilidade, status_publicacao
) VALUES (
    'md_triatlo', 'm_triatlo', 'Triatlo', 'basico', 'Registro de uma sessão multiesporte organizada em trechos.',
    'etapa', 'Trecho', TRUE, 1, TRUE, 'publico', 'publicado'
)
ON CONFLICT DO NOTHING;

INSERT INTO campos_modelo (
    idcampo, idmodelo, nome, slug, rotulo, tipo_campo, escopo,
    idgrandeza, idunidade, obrigatorio, ordem, exibicao_padrao, grupo_ui
) VALUES
    ('f_triat_dist', 'md_triatlo', 'distancia', 'distancia', 'Distância', 'decimal', 'unidade', 'g_distancia', 'u_km', FALSE, 10, TRUE, 'principal'),
    ('f_triat_dur', 'md_triatlo', 'duracao', 'duracao', 'Duração', 'intervalo', 'unidade', 'g_tempo', NULL, FALSE, 20, TRUE, 'principal'),
    ('f_triat_elev', 'md_triatlo', 'elevacao', 'elevacao', 'Elevação', 'decimal', 'unidade', 'g_distancia', 'u_m', FALSE, 30, FALSE, 'extras')
ON CONFLICT DO NOTHING;

COMMIT;


-- ============================================================================
-- Consolidated from: 20260901_elevation_visible_by_default.sql
-- ============================================================================
BEGIN;
SET search_path TO stridebr, public;

-- A maioria das pessoas espera ver o campo de elevação já disponível ao
-- registrar uma atividade, em vez de precisar clicar em "+ Elevação" pra
-- revelar. Continua sendo um campo comum (não obrigatório) — dá pra
-- esconder com o X a qualquer momento e trazer de volta depois pela mesma
-- tag de campos opcionais (correção feita junto no atividades.js).
UPDATE campos_modelo
SET exibicao_padrao = TRUE
WHERE lower(slug) IN ('elevacao', 'desnivel')
  AND exibicao_padrao = FALSE;

COMMIT;


-- ============================================================================
-- Consolidated from: 20260901_generic_activity_allows_route.sql
-- ============================================================================
BEGIN;
SET search_path TO stridebr, public;

-- "Outra atividade" é o fallback genérico usado quando a modalidade não é
-- reconhecida — inclusive na importação de FIT/TCX/GPX, quando o app do
-- relógio não informa (ou informa de um jeito que a gente ainda não
-- reconhece) o tipo de esporte. Como esses arquivos quase sempre têm rota
-- de GPS de verdade, não fazia sentido essa modalidade rejeitar rota: a
-- pessoa importava um arquivo com 2900+ pontos de GPS e via um aviso de
-- "esta modalidade não permite rota", mesmo a rota existindo no arquivo.
UPDATE modalidades
SET permite_rota = TRUE
WHERE idusuario IS NULL
  AND lower(slug) = 'outra-atividade';

COMMIT;


-- ============================================================================
-- Consolidated from: 20260901_integrations_foundation.sql
-- ============================================================================
BEGIN;
SET search_path TO stridebr, public;

CREATE TABLE IF NOT EXISTS integracoes_usuario (
    idintegracao VARCHAR(21) PRIMARY KEY,
    idusuario VARCHAR(21) NOT NULL REFERENCES usuarios(idusuario) ON DELETE CASCADE,
    provedor VARCHAR(32) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'conectado',
    usuario_externo_id VARCHAR(190),
    usuario_externo_nome VARCHAR(190),
    access_token_enc TEXT,
    refresh_token_enc TEXT,
    token_expira_em TIMESTAMPTZ,
    escopos TEXT,
    metadados JSONB NOT NULL DEFAULT '{}'::jsonb,
    sincronizar_atividades BOOLEAN NOT NULL DEFAULT TRUE,
    sincronizar_treinos BOOLEAN NOT NULL DEFAULT FALSE,
    mostrar_perfil BOOLEAN NOT NULL DEFAULT FALSE,
    perfil_publico_url TEXT,
    ultima_sincronizacao_em TIMESTAMPTZ,
    ultimo_erro TEXT,
    criado_em TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    atualizado_em TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT uq_integracoes_usuario_provedor UNIQUE (idusuario, provedor),
    CONSTRAINT ck_integracoes_usuario_provedor CHECK (provedor IN ('garmin', 'strava', 'polar', 'suunto', 'fitbit', 'health_connect', 'apple_health', 'samsung_health')),
    CONSTRAINT ck_integracoes_usuario_status CHECK (status IN ('conectado', 'erro', 'revogado', 'indisponivel')),
    CONSTRAINT ck_integracoes_usuario_perfil_url CHECK (perfil_publico_url IS NULL OR perfil_publico_url ~ '^https?://')
);

CREATE INDEX IF NOT EXISTS ix_integracoes_usuario_status
ON integracoes_usuario (idusuario, status, provedor);

ALTER TABLE registros_atividade ADD COLUMN IF NOT EXISTS origem_provedor VARCHAR(32);
ALTER TABLE registros_atividade ADD COLUMN IF NOT EXISTS id_externo VARCHAR(190);
ALTER TABLE registros_atividade ADD COLUMN IF NOT EXISTS dispositivo_origem JSONB NOT NULL DEFAULT '{}'::jsonb;

CREATE UNIQUE INDEX IF NOT EXISTS uq_registros_atividade_origem_externa
ON registros_atividade (idusuario, origem_provedor, id_externo)
WHERE origem_provedor IS NOT NULL AND id_externo IS NOT NULL AND excluido_em IS NULL;

CREATE INDEX IF NOT EXISTS ix_registros_atividade_origem_provedor
ON registros_atividade (idusuario, origem_provedor, data_inicio DESC)
WHERE origem_provedor IS NOT NULL;

COMMIT;


-- ============================================================================
-- Consolidated from: 20260901_strength_muscle_metadata.sql
-- ============================================================================
BEGIN;
SET search_path TO stridebr, public;

ALTER TABLE exercicios ADD COLUMN IF NOT EXISTS grupos_musculares_primarios JSONB NOT NULL DEFAULT '[]'::jsonb;
ALTER TABLE exercicios ADD COLUMN IF NOT EXISTS grupos_musculares_secundarios JSONB NOT NULL DEFAULT '[]'::jsonb;

UPDATE exercicios SET grupos_musculares_primarios = '["peito"]'::jsonb, grupos_musculares_secundarios = '["triceps","ombros"]'::jsonb WHERE idexercicio IN ('e_supino','e_flexao') AND grupos_musculares_primarios = '[]'::jsonb;
UPDATE exercicios SET grupos_musculares_primarios = '["quadriceps","gluteos"]'::jsonb, grupos_musculares_secundarios = '["posteriores"]'::jsonb WHERE idexercicio IN ('e_agachamento','e_legpress','e_afundo') AND grupos_musculares_primarios = '[]'::jsonb;
UPDATE exercicios SET grupos_musculares_primarios = '["costas"]'::jsonb, grupos_musculares_secundarios = '["biceps"]'::jsonb WHERE idexercicio IN ('e_remada','e_barra') AND grupos_musculares_primarios = '[]'::jsonb;
UPDATE exercicios SET grupos_musculares_primarios = '["ombros"]'::jsonb, grupos_musculares_secundarios = '["triceps"]'::jsonb WHERE idexercicio IN ('e_desenvolv','e_elevlat') AND grupos_musculares_primarios = '[]'::jsonb;
UPDATE exercicios SET grupos_musculares_primarios = '["biceps"]'::jsonb, grupos_musculares_secundarios = '[]'::jsonb WHERE idexercicio = 'e_rosca' AND grupos_musculares_primarios = '[]'::jsonb;
UPDATE exercicios SET grupos_musculares_primarios = '["triceps"]'::jsonb, grupos_musculares_secundarios = '[]'::jsonb WHERE idexercicio = 'e_triceps' AND grupos_musculares_primarios = '[]'::jsonb;
UPDATE exercicios SET grupos_musculares_primarios = '["core"]'::jsonb, grupos_musculares_secundarios = '[]'::jsonb WHERE idexercicio = 'e_prancha' AND grupos_musculares_primarios = '[]'::jsonb;
UPDATE exercicios SET grupos_musculares_primarios = '["corpo-inteiro"]'::jsonb, grupos_musculares_secundarios = '["quadriceps","peito","ombros","core"]'::jsonb WHERE idexercicio = 'e_burpee' AND grupos_musculares_primarios = '[]'::jsonb;

COMMIT;


-- ============================================================================
-- Consolidated from: 20260901_strength_progress_foundation.sql
-- ============================================================================
BEGIN;
SET search_path TO stridebr, public;

ALTER TABLE exercicios ADD COLUMN IF NOT EXISTS grupos_musculares_primarios JSONB NOT NULL DEFAULT '[]'::jsonb;
ALTER TABLE exercicios ADD COLUMN IF NOT EXISTS grupos_musculares_secundarios JSONB NOT NULL DEFAULT '[]'::jsonb;

CREATE TABLE IF NOT EXISTS series_exercicio_atividade (
    idserie VARCHAR(21) PRIMARY KEY,
    idregistro VARCHAR(21) NOT NULL REFERENCES registros_atividade(idregistro) ON DELETE CASCADE,
    idexercicio VARCHAR(21) REFERENCES exercicios(idexercicio) ON DELETE SET NULL,
    nome_exercicio VARCHAR(160) NOT NULL,
    ordem_exercicio INTEGER NOT NULL DEFAULT 1,
    ordem_serie INTEGER NOT NULL DEFAULT 1,
    tipo VARCHAR(20) NOT NULL DEFAULT 'trabalho',
    carga_kg NUMERIC(10,3),
    repeticoes INTEGER,
    duracao_segundos INTEGER,
    distancia_metros NUMERIC(12,3),
    rir NUMERIC(3,1),
    rpe NUMERIC(3,1),
    concluida BOOLEAN NOT NULL DEFAULT TRUE,
    observacoes TEXT,
    criado_em TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT ck_series_exercicio_atividade_tipo CHECK (tipo IN ('aquecimento', 'trabalho', 'drop', 'falha', 'outro')),
    CONSTRAINT ck_series_exercicio_atividade_carga CHECK (carga_kg IS NULL OR carga_kg >= 0),
    CONSTRAINT ck_series_exercicio_atividade_reps CHECK (repeticoes IS NULL OR repeticoes >= 0),
    CONSTRAINT ck_series_exercicio_atividade_rir CHECK (rir IS NULL OR rir BETWEEN 0 AND 10),
    CONSTRAINT ck_series_exercicio_atividade_rpe CHECK (rpe IS NULL OR rpe BETWEEN 1 AND 10)
);

CREATE INDEX IF NOT EXISTS ix_series_exercicio_atividade_registro
ON series_exercicio_atividade (idregistro, ordem_exercicio, ordem_serie);

CREATE INDEX IF NOT EXISTS ix_series_exercicio_atividade_exercicio
ON series_exercicio_atividade (idexercicio, criado_em DESC)
WHERE idexercicio IS NOT NULL;

COMMIT;


-- ============================================================================
-- Consolidated from: 20260902_01_sport_taxonomy_v1.sql
-- ============================================================================
BEGIN;
SET search_path TO stridebr, public;

ALTER TABLE modalidades ADD COLUMN IF NOT EXISTS familia_hub VARCHAR(30);
CREATE INDEX IF NOT EXISTS ix_modalidades_familia_hub ON modalidades (familia_hub, ordem_catalogo, nome);

UPDATE modalidades SET familia_hub = CASE
    WHEN slug IN ('musculacao','calistenia','crossfit','hiit','treino-funcional') THEN 'strength'
    WHEN categoria = 'Atletismo' OR slug IN ('marcha-atletica','atletismo','salto-em-distancia','salto-em-altura','salto-com-vara','arremesso-de-peso','lancamento-de-disco','lancamento-de-dardo','lancamento-de-martelo') THEN 'athletics'
    WHEN categoria = 'Raquete' THEN 'racket'
    WHEN categoria = 'Esportes coletivos' THEN 'team'
    WHEN categoria = 'Lutas' THEN 'combat'
    WHEN slug IN ('yoga','pilates','mobilidade') THEN 'movement'
    WHEN slug = 'danca' THEN 'dance'
    WHEN slug IN ('trilha','stand-up-paddle','surfe','kitesurf','windsurf','vela','skate') OR categoria = 'Escalada' THEN 'outdoor'
    WHEN slug IN ('corrida','corrida-em-trilha','corrida-em-esteira','caminhada','ciclismo','mountain-bike','downhill','bmx','gravel','bicicleta-eletrica','e-mountain-bike','ciclismo-indoor','handcycle','velomovel','natacao','remo','remo-indoor','canoagem','caiaque','eliptico','simulador-de-escada','pular-corda','cardio','patinacao-inline','roller-ski') OR categoria IN ('Corrida e caminhada','Ciclismo','Multiesporte') THEN 'cardio'
    WHEN categoria = 'Inverno' THEN 'winter'
    WHEN slug = 'equitacao' THEN 'equestrian'
    WHEN slug = 'golfe' THEN 'precision'
    WHEN categoria = 'Aquáticos' THEN 'outdoor'
    WHEN categoria = 'Rodas' THEN 'outdoor'
    ELSE COALESCE(familia_hub, 'other')
END
WHERE idusuario IS NULL;

UPDATE modalidades SET categoria = CASE familia_hub
    WHEN 'strength' THEN 'Força'
    WHEN 'cardio' THEN 'Cardio'
    WHEN 'athletics' THEN 'Atletismo'
    WHEN 'racket' THEN 'Raquetes'
    WHEN 'team' THEN 'Esportes em equipe'
    WHEN 'combat' THEN 'Lutas'
    WHEN 'movement' THEN 'Ginástica & movimento'
    WHEN 'outdoor' THEN 'Outdoor & aventura'
    WHEN 'precision' THEN 'Precisão'
    WHEN 'winter' THEN 'Inverno'
    WHEN 'dance' THEN 'Dança'
    WHEN 'equestrian' THEN 'Equestres'
    WHEN 'motorsport' THEN 'Motores'
    ELSE categoria
END
WHERE idusuario IS NULL;

WITH catalogo(nome, slug, descricao, categoria, familia_hub, icone, ordem_catalogo, metrica_derivada) AS (
    VALUES
        ('Powerlifting','powerlifting','Levantamentos de força com agachamento, supino e terra.','Força','strength','🏋️',2001,'nenhuma'),
        ('Levantamento olímpico','levantamento-olimpico','Arranco e arremesso olímpico.','Força','strength','🏋️',2002,'nenhuma'),
        ('Strongman','strongman','Treino e provas de strongman.','Força','strength','🏋️',2003,'nenhuma'),
        ('Fisiculturismo','fisiculturismo','Sessão de treino voltada ao fisiculturismo.','Força','strength','🏋️',2004,'nenhuma'),
        ('Kettlebell','kettlebell','Treino com kettlebell.','Força','strength','🏋️',2005,'nenhuma'),
        ('Street workout','street-workout','Calistenia e habilidades em barras.','Força','strength','🤸',2006,'nenhuma'),
        ('Grip sport','grip-sport','Treino e provas de força de pegada.','Força','strength','✊',2007,'nenhuma'),
        ('Queda de braço','queda-de-braco','Treino ou competição de braço de ferro.','Força','strength','💪',2008,'nenhuma'),
        ('Treino com trenó','treino-com-treno','Empurrar ou puxar trenó com carga.','Força','strength','🏋️',2009,'nenhuma'),
        ('Treino com sandbag','treino-com-sandbag','Treino de força com saco de areia.','Força','strength','🏋️',2010,'nenhuma'),
        ('HYROX','hyrox','Treino ou prova HYROX.','Força','strength','⚡',2011,'nenhuma'),
        ('Boot camp','boot-camp','Treino físico em circuito ou estações.','Força','strength','⚡',2012,'nenhuma'),
        ('Jogging','jogging','Corrida leve e contínua.','Cardio','cardio','🏃',2013,'pace_km'),
        ('Corrida de rua','corrida-de-rua','Corrida em vias pavimentadas.','Cardio','cardio','🏃',2014,'pace_km'),
        ('Nordic walking','nordic-walking','Caminhada com bastões.','Cardio','cardio','🚶',2015,'pace_km'),
        ('Rucking','rucking','Caminhada com carga ou mochila.','Cardio','cardio','🥾',2016,'pace_km'),
        ('Ciclismo de estrada','ciclismo-de-estrada','Ciclismo em estrada.','Cardio','cardio','🚴',2017,'velocidade_kmh'),
        ('Ciclismo urbano','ciclismo-urbano','Pedalada urbana e deslocamento.','Cardio','cardio','🚲',2018,'velocidade_kmh'),
        ('Spinning','spinning','Aula ou sessão de bike indoor.','Cardio','cardio','🚴',2019,'nenhuma'),
        ('Natação em piscina','natacao-em-piscina','Natação em piscina.','Cardio','cardio','🏊',2020,'pace_100m'),
        ('Natação em águas abertas','natacao-aguas-abertas','Natação em mar, lago ou rio.','Cardio','cardio','🏊',2021,'pace_100m'),
        ('Aquajogging','aquajogging','Corrida em água.','Cardio','cardio','🏊',2022,'nenhuma'),
        ('Hidroginástica','hidroginastica','Exercícios aeróbicos em água.','Cardio','cardio','🏊',2023,'nenhuma'),
        ('Patinação de velocidade','patinacao-de-velocidade','Patinação voltada à velocidade.','Cardio','cardio','⛸️',2024,'velocidade_kmh'),
        ('Triatlo','triatlo','Natação, ciclismo e corrida.','Cardio','cardio','🏊',2025,'nenhuma'),
        ('Duatlo','duatlo','Corrida e ciclismo.','Cardio','cardio','🏃',2026,'nenhuma'),
        ('Aquatlo','aquatlo','Natação e corrida.','Cardio','cardio','🏊',2027,'nenhuma'),
        ('Swimrun','swimrun','Alternância entre corrida e natação.','Cardio','cardio','🏊',2028,'nenhuma'),
        ('Multiesporte','multiesporte','Sessão com mais de uma modalidade.','Cardio','cardio','⚡',2029,'nenhuma'),
        ('60 m','atletismo-60m','Prova de velocidade de 60 metros.','Atletismo','athletics','🏟️',2030,'pace_km'),
        ('100 m','atletismo-100m','Prova de velocidade de 100 metros.','Atletismo','athletics','🏟️',2031,'pace_km'),
        ('200 m','atletismo-200m','Prova de velocidade de 200 metros.','Atletismo','athletics','🏟️',2032,'pace_km'),
        ('400 m','atletismo-400m','Prova de velocidade de 400 metros.','Atletismo','athletics','🏟️',2033,'pace_km'),
        ('800 m','atletismo-800m','Prova de meio-fundo de 800 metros.','Atletismo','athletics','🏟️',2034,'pace_km'),
        ('1.500 m','atletismo-1500m','Prova de meio-fundo de 1.500 metros.','Atletismo','athletics','🏟️',2035,'pace_km'),
        ('Milha','atletismo-milha','Prova de uma milha.','Atletismo','athletics','🏟️',2036,'pace_km'),
        ('3.000 m','atletismo-3000m','Prova de fundo de 3.000 metros.','Atletismo','athletics','🏟️',2037,'pace_km'),
        ('5.000 m','atletismo-5000m','Prova de fundo de 5.000 metros.','Atletismo','athletics','🏟️',2038,'pace_km'),
        ('10.000 m','atletismo-10000m','Prova de fundo de 10.000 metros.','Atletismo','athletics','🏟️',2039,'pace_km'),
        ('60 m com barreiras','60m-com-barreiras','Prova de 60 metros com barreiras.','Atletismo','athletics','🏟️',2040,'pace_km'),
        ('100 m com barreiras','100m-com-barreiras','Prova de 100 metros com barreiras.','Atletismo','athletics','🏟️',2041,'pace_km'),
        ('110 m com barreiras','110m-com-barreiras','Prova de 110 metros com barreiras.','Atletismo','athletics','🏟️',2042,'pace_km'),
        ('400 m com barreiras','400m-com-barreiras','Prova de 400 metros com barreiras.','Atletismo','athletics','🏟️',2043,'pace_km'),
        ('3.000 m com obstáculos','3000m-com-obstaculos','Prova de 3.000 metros com obstáculos.','Atletismo','athletics','🏟️',2044,'pace_km'),
        ('4 × 100 m','revezamento-4x100m','Revezamento 4 × 100 metros.','Atletismo','athletics','🏟️',2045,'pace_km'),
        ('4 × 400 m','revezamento-4x400m','Revezamento 4 × 400 metros.','Atletismo','athletics','🏟️',2046,'pace_km'),
        ('Salto triplo','salto-triplo','Treino e prova de salto triplo.','Atletismo','athletics','🏟️',2047,'nenhuma'),
        ('Decatlo','decatlo','Prova combinada de decatlo.','Atletismo','athletics','🏟️',2048,'nenhuma'),
        ('Heptatlo','heptatlo','Prova combinada de heptatlo.','Atletismo','athletics','🏟️',2049,'nenhuma'),
        ('Pentatlo','pentatlo-atletismo','Prova combinada de pentatlo.','Atletismo','athletics','🏟️',2050,'nenhuma'),
        ('Cross-country','cross-country','Corrida competitiva em terreno natural.','Atletismo','athletics','🏟️',2051,'pace_km'),
        ('Corrida de montanha','corrida-de-montanha','Corrida competitiva em montanha.','Atletismo','athletics','🏟️',2052,'pace_km'),
        ('Crossminton','crossminton','Esporte de raquete com speed badminton.','Raquetes','racket','🏸',2053,'nenhuma'),
        ('Soft tennis','soft-tennis','Modalidade de tênis com bola macia.','Raquetes','racket','🎾',2054,'nenhuma'),
        ('Frescobol','frescobol','Jogo de raquetes cooperativo.','Raquetes','racket','🏓',2055,'nenhuma'),
        ('Futebol society','futebol-society','Partida ou treino de futebol society.','Esportes em equipe','team','⚽',2056,'nenhuma'),
        ('Futebol de areia','futebol-de-areia','Partida ou treino de futebol de areia.','Esportes em equipe','team','⚽',2057,'nenhuma'),
        ('Flag football','flag-football','Partida ou treino de flag football.','Esportes em equipe','team','🏈',2058,'nenhuma'),
        ('Rugby sevens','rugby-sevens','Rugby com sete jogadores.','Esportes em equipe','team','🏉',2059,'nenhuma'),
        ('Touch rugby','touch-rugby','Rugby sem contato de tackle.','Esportes em equipe','team','🏉',2060,'nenhuma'),
        ('Hóquei de campo','hoquei-de-campo','Partida ou treino de hóquei de campo.','Esportes em equipe','team','🏑',2061,'nenhuma'),
        ('Hóquei indoor','hoquei-indoor','Partida ou treino de hóquei indoor.','Esportes em equipe','team','🏑',2062,'nenhuma'),
        ('Floorball','floorball','Partida ou treino de floorball.','Esportes em equipe','team','🏑',2063,'nenhuma'),
        ('Baseball','baseball','Partida ou treino de baseball.','Esportes em equipe','team','⚾',2064,'nenhuma'),
        ('Softball','softball','Partida ou treino de softball.','Esportes em equipe','team','🥎',2065,'nenhuma'),
        ('Lacrosse','lacrosse','Partida ou treino de lacrosse.','Esportes em equipe','team','🥍',2066,'nenhuma'),
        ('Polo aquático','polo-aquatico','Partida ou treino de polo aquático.','Esportes em equipe','team','🤽',2067,'nenhuma'),
        ('Ultimate frisbee','ultimate-frisbee','Partida ou treino de ultimate.','Esportes em equipe','team','🥏',2068,'nenhuma'),
        ('Dodgeball','dodgeball','Partida ou treino de dodgeball.','Esportes em equipe','team','🏐',2069,'nenhuma'),
        ('Netball','netball','Partida ou treino de netball.','Esportes em equipe','team','🏀',2070,'nenhuma'),
        ('Korfball','korfball','Partida ou treino de korfball.','Esportes em equipe','team','🏀',2071,'nenhuma'),
        ('Sepak takraw','sepak-takraw','Partida ou treino de sepak takraw.','Esportes em equipe','team','🏐',2072,'nenhuma'),
        ('Futebol australiano','futebol-australiano','Partida ou treino de futebol australiano.','Esportes em equipe','team','🏉',2073,'nenhuma'),
        ('Futebol gaélico','futebol-gaelico','Partida ou treino de futebol gaélico.','Esportes em equipe','team','🏐',2074,'nenhuma'),
        ('Hurling','hurling','Partida ou treino de hurling.','Esportes em equipe','team','🏑',2075,'nenhuma'),
        ('Canoe polo','canoe-polo','Polo praticado em caiaque.','Esportes em equipe','team','🛶',2076,'nenhuma'),
        ('MMA','mma','Treino ou luta de artes marciais mistas.','Lutas','combat','🥊',2077,'nenhuma'),
        ('Grappling','grappling','Treino ou competição de grappling.','Lutas','combat','🤼',2078,'nenhuma'),
        ('Sambo','sambo','Treino ou luta de sambo.','Lutas','combat','🥋',2079,'nenhuma'),
        ('Sanda','sanda','Treino ou luta de sanda.','Lutas','combat','🥊',2080,'nenhuma'),
        ('Kung fu','kung-fu','Treino de kung fu.','Lutas','combat','🥋',2081,'nenhuma'),
        ('Wushu','wushu','Treino ou competição de wushu.','Lutas','combat','🥋',2082,'nenhuma'),
        ('Savate','savate','Treino ou luta de savate.','Lutas','combat','🥊',2083,'nenhuma'),
        ('Sumô','sumo','Treino ou luta de sumô.','Lutas','combat','🤼',2084,'nenhuma'),
        ('Aikido','aikido','Treino de aikido.','Lutas','combat','🥋',2085,'nenhuma'),
        ('Kendo','kendo','Treino ou combate de kendo.','Lutas','combat','🥋',2086,'nenhuma'),
        ('Krav Maga','krav-maga','Treino de Krav Maga.','Lutas','combat','🥋',2087,'nenhuma'),
        ('Hapkido','hapkido','Treino de hapkido.','Lutas','combat','🥋',2088,'nenhuma'),
        ('Ginástica artística','ginastica-artistica','Treino ou competição de ginástica artística.','Ginástica & movimento','movement','🤸',2089,'nenhuma'),
        ('Ginástica rítmica','ginastica-ritmica','Treino ou competição de ginástica rítmica.','Ginástica & movimento','movement','🤸',2090,'nenhuma'),
        ('Ginástica acrobática','ginastica-acrobatica','Treino ou competição de ginástica acrobática.','Ginástica & movimento','movement','🤸',2091,'nenhuma'),
        ('Ginástica aeróbica','ginastica-aerobica','Treino ou competição de ginástica aeróbica.','Ginástica & movimento','movement','🤸',2092,'nenhuma'),
        ('Trampolim','trampolim','Treino ou competição de trampolim.','Ginástica & movimento','movement','🤸',2093,'nenhuma'),
        ('Cheerleading','cheerleading','Treino ou apresentação de cheerleading.','Ginástica & movimento','movement','🤸',2094,'nenhuma'),
        ('Parkour','parkour','Treino de parkour.','Ginástica & movimento','movement','🤸',2095,'nenhuma'),
        ('Freerunning','freerunning','Treino de freerunning.','Ginástica & movimento','movement','🤸',2096,'nenhuma'),
        ('Power yoga','power-yoga','Sessão de yoga vigorosa.','Ginástica & movimento','movement','🧘',2097,'nenhuma'),
        ('Hot yoga','hot-yoga','Sessão de yoga em ambiente aquecido.','Ginástica & movimento','movement','🧘',2098,'nenhuma'),
        ('Tai chi','tai-chi','Sessão de tai chi.','Ginástica & movimento','movement','🧘',2099,'nenhuma'),
        ('Qi gong','qi-gong','Sessão de qi gong.','Ginástica & movimento','movement','🧘',2100,'nenhuma'),
        ('Acroyoga','acroyoga','Sessão de acroyoga.','Ginástica & movimento','movement','🤸',2101,'nenhuma'),
        ('Pole sport','pole-sport','Treino esportivo em pole.','Ginástica & movimento','movement','🤸',2102,'nenhuma'),
        ('Tecido acrobático','tecido-acrobatico','Treino em tecido aéreo.','Ginástica & movimento','movement','🤸',2103,'nenhuma'),
        ('Lira aérea','lira-aerea','Treino em lira aérea.','Ginástica & movimento','movement','🤸',2104,'nenhuma'),
        ('Alongamento','alongamento','Sessão dedicada de alongamento.','Ginástica & movimento','movement','🤸',2105,'nenhuma'),
        ('Montanhismo','montanhismo','Ascensão e deslocamento em montanha.','Outdoor & aventura','outdoor','🏔️',2106,'nenhuma'),
        ('Escalada tradicional','escalada-tradicional','Escalada tradicional em rocha.','Outdoor & aventura','outdoor','🧗',2107,'nenhuma'),
        ('Escalada indoor','escalada-indoor','Escalada em parede indoor.','Outdoor & aventura','outdoor','🧗',2108,'nenhuma'),
        ('Via ferrata','via-ferrata','Percurso protegido em montanha.','Outdoor & aventura','outdoor','🧗',2109,'nenhuma'),
        ('Canyoning','canyoning','Descida de cânions.','Outdoor & aventura','outdoor','🏞️',2110,'nenhuma'),
        ('Orientação','orientacao','Corrida ou caminhada de orientação.','Outdoor & aventura','outdoor','🧭',2111,'pace_km'),
        ('Corrida de aventura','corrida-de-aventura','Prova multiesportiva de aventura.','Outdoor & aventura','outdoor','🧭',2112,'nenhuma'),
        ('Corrida com obstáculos','corrida-com-obstaculos','Corrida com obstáculos artificiais ou naturais.','Outdoor & aventura','outdoor','🏃',2113,'pace_km'),
        ('Slackline','slackline','Treino de equilíbrio em fita.','Outdoor & aventura','outdoor','🤸',2114,'nenhuma'),
        ('Highline','highline','Slackline em altura.','Outdoor & aventura','outdoor','🤸',2115,'nenhuma'),
        ('Bodyboard','bodyboard','Sessão de bodyboard.','Outdoor & aventura','outdoor','🏄',2116,'nenhuma'),
        ('Rafting','rafting','Descida de rio em bote.','Outdoor & aventura','outdoor','🚣',2117,'nenhuma'),
        ('Wakeboard','wakeboard','Sessão de wakeboard.','Outdoor & aventura','outdoor','🏄',2118,'nenhuma'),
        ('Esqui aquático','esqui-aquatico','Sessão de esqui aquático.','Outdoor & aventura','outdoor','🎿',2119,'nenhuma'),
        ('Mergulho','mergulho','Sessão de mergulho com cilindro.','Outdoor & aventura','outdoor','🤿',2120,'nenhuma'),
        ('Snorkeling','snorkeling','Sessão de snorkeling.','Outdoor & aventura','outdoor','🤿',2121,'nenhuma'),
        ('Apneia','apneia','Treino ou mergulho em apneia.','Outdoor & aventura','outdoor','🤿',2122,'nenhuma'),
        ('Longboard','longboard','Sessão de longboard.','Outdoor & aventura','outdoor','🛹',2123,'nenhuma'),
        ('Mountainboard','mountainboard','Sessão de mountainboard.','Outdoor & aventura','outdoor','🛹',2124,'nenhuma'),
        ('Tiro com arco','tiro-com-arco','Treino ou competição de arco e flecha.','Precisão','precision','🏹',2125,'nenhuma'),
        ('Tiro esportivo','tiro-esportivo','Treino ou competição de tiro esportivo.','Precisão','precision','🎯',2126,'nenhuma'),
        ('Dardos','dardos','Treino ou partida de dardos.','Precisão','precision','🎯',2127,'nenhuma'),
        ('Boliche','boliche','Partida ou treino de boliche.','Precisão','precision','🎳',2128,'nenhuma'),
        ('Bocha','bocha','Partida ou treino de bocha.','Precisão','precision','🎯',2129,'nenhuma'),
        ('Petanca','petanca','Partida ou treino de petanca.','Precisão','precision','🎯',2130,'nenhuma'),
        ('Sinuca','sinuca','Partida ou treino de sinuca.','Precisão','precision','🎱',2131,'nenhuma'),
        ('Bilhar','bilhar','Partida ou treino de bilhar.','Precisão','precision','🎱',2132,'nenhuma'),
        ('Snooker','snooker','Partida ou treino de snooker.','Precisão','precision','🎱',2133,'nenhuma'),
        ('Disc golf','disc-golf','Partida de disc golf.','Precisão','precision','🥏',2134,'nenhuma'),
        ('Minigolfe','minigolfe','Partida de minigolfe.','Precisão','precision','⛳',2135,'nenhuma'),
        ('Esqui freestyle','esqui-freestyle','Sessão de esqui freestyle.','Inverno','winter','🎿',2136,'nenhuma'),
        ('Esqui de velocidade','esqui-de-velocidade','Sessão ou prova de esqui de velocidade.','Inverno','winter','🎿',2137,'velocidade_kmh'),
        ('Salto de esqui','salto-de-esqui','Treino ou competição de salto de esqui.','Inverno','winter','🎿',2138,'nenhuma'),
        ('Combinado nórdico','combinado-nordico','Esqui cross-country e salto de esqui.','Inverno','winter','🎿',2139,'nenhuma'),
        ('Esqui-alpinismo','esqui-alpinismo','Subida e descida em esquis.','Inverno','winter','🎿',2140,'nenhuma'),
        ('Biatlo','biatlo','Esqui cross-country e tiro.','Inverno','winter','🎿',2141,'nenhuma'),
        ('Bobsled','bobsled','Treino ou prova de bobsled.','Inverno','winter','🛷',2142,'nenhuma'),
        ('Luge','luge','Treino ou prova de luge.','Inverno','winter','🛷',2143,'nenhuma'),
        ('Skeleton','skeleton','Treino ou prova de skeleton.','Inverno','winter','🛷',2144,'nenhuma'),
        ('Curling','curling','Partida ou treino de curling.','Inverno','winter','🥌',2145,'nenhuma'),
        ('Patinação artística','patinacao-artistica','Treino ou competição de patinação artística.','Inverno','winter','⛸️',2146,'nenhuma'),
        ('Patinação de velocidade no gelo','patinacao-velocidade-gelo','Treino ou prova de velocidade no gelo.','Inverno','winter','⛸️',2147,'velocidade_kmh'),
        ('Ballet','ballet','Aula, ensaio ou apresentação de ballet.','Dança','dance','💃',2148,'nenhuma'),
        ('Dança contemporânea','danca-contemporanea','Aula, ensaio ou apresentação de dança contemporânea.','Dança','dance','💃',2149,'nenhuma'),
        ('Jazz dance','jazz-dance','Aula ou sessão de jazz dance.','Dança','dance','💃',2150,'nenhuma'),
        ('Hip-hop','hip-hop','Aula ou sessão de dança hip-hop.','Dança','dance','💃',2151,'nenhuma'),
        ('Breaking','breaking','Treino ou sessão de breaking.','Dança','dance','💃',2152,'nenhuma'),
        ('Samba','samba','Aula ou sessão de samba.','Dança','dance','💃',2153,'nenhuma'),
        ('Forró','forro','Aula ou sessão de forró.','Dança','dance','💃',2154,'nenhuma'),
        ('Salsa','salsa','Aula ou sessão de salsa.','Dança','dance','💃',2155,'nenhuma'),
        ('Bachata','bachata','Aula ou sessão de bachata.','Dança','dance','💃',2156,'nenhuma'),
        ('Tango','tango','Aula ou sessão de tango.','Dança','dance','💃',2157,'nenhuma'),
        ('Zouk','zouk','Aula ou sessão de zouk.','Dança','dance','💃',2158,'nenhuma'),
        ('Dança de salão','danca-de-salao','Aula ou sessão de dança de salão.','Dança','dance','💃',2159,'nenhuma'),
        ('Dança gaúcha','danca-gaucha','Ensaio ou sessão de dança tradicional gaúcha.','Dança','dance','💃',2160,'nenhuma'),
        ('Sapateado','sapateado','Aula ou sessão de sapateado.','Dança','dance','💃',2161,'nenhuma'),
        ('Zumba','zumba','Aula de dança fitness.','Dança','dance','💃',2162,'nenhuma'),
        ('Hipismo — salto','hipismo-salto','Treino ou competição de salto equestre.','Equestres','equestrian','🐎',2163,'nenhuma'),
        ('Adestramento equestre','adestramento-equestre','Treino ou competição de adestramento.','Equestres','equestrian','🐎',2164,'nenhuma'),
        ('Concurso completo','concurso-completo-equestre','Concurso completo de equitação.','Equestres','equestrian','🐎',2165,'nenhuma'),
        ('Enduro equestre','enduro-equestre','Prova ou treino de enduro equestre.','Equestres','equestrian','🐎',2166,'nenhuma'),
        ('Polo','polo','Partida ou treino de polo.','Equestres','equestrian','🐎',2167,'nenhuma'),
        ('Rodeio','rodeio','Treino ou prova de rodeio.','Equestres','equestrian','🐎',2168,'nenhuma'),
        ('Kart','kart','Treino ou prova de kart.','Motores','motorsport','🏎️',2169,'nenhuma'),
        ('Automobilismo de pista','automobilismo-de-pista','Treino ou prova em circuito.','Motores','motorsport','🏎️',2170,'nenhuma'),
        ('Rally','rally','Treino ou prova de rally.','Motores','motorsport','🏎️',2171,'nenhuma'),
        ('Motocross','motocross','Treino ou prova de motocross.','Motores','motorsport','🏍️',2172,'nenhuma'),
        ('Enduro de moto','enduro-de-moto','Treino ou prova de enduro de moto.','Motores','motorsport','🏍️',2173,'nenhuma'),
        ('Trial de moto','trial-de-moto','Treino ou prova de trial.','Motores','motorsport','🏍️',2174,'nenhuma'),
        ('Motovelocidade','motovelocidade','Treino ou prova de motovelocidade.','Motores','motorsport','🏍️',2175,'nenhuma'),
        ('Supermoto','supermoto','Treino ou prova de supermoto.','Motores','motorsport','🏍️',2176,'nenhuma'),
        ('UTV / off-road','utv-off-road','Sessão ou prova de UTV e off-road.','Motores','motorsport','🏎️',2177,'nenhuma')
)
INSERT INTO modalidades (idmodalidade, nome, slug, descricao, visibilidade, status_publicacao, categoria, familia_hub, icone, ordem_catalogo, metrica_derivada)
SELECT 'm_' || substr(md5(slug), 1, 19), nome, slug, descricao, 'publico', 'publicado', categoria, familia_hub, icone, ordem_catalogo, metrica_derivada
FROM catalogo
ON CONFLICT DO NOTHING;

INSERT INTO modelos_modalidade (idmodelo, idmodalidade, nome, slug, descricao, tipo_unidade_padrao, rotulo_unidade, permite_multiplas_unidades, versao, padrao, ativo, visibilidade, status_publicacao)
SELECT 'sm_' || substr(md5(m.idmodalidade || ':catalog-v1'), 1, 18), m.idmodalidade, 'Registro padrão', 'padrao', 'Registro rápido da modalidade.', 'sessao', 'Sessão', FALSE, 1, TRUE, TRUE, 'publico', 'publicado'
FROM modalidades m
WHERE m.idusuario IS NULL AND m.familia_hub IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM modelos_modalidade mm WHERE mm.idmodalidade = m.idmodalidade AND mm.ativo = TRUE AND mm.padrao = TRUE)
ON CONFLICT DO NOTHING;

-- Modelos usados por atividades antigas são imutáveis por design.
-- Se esta taxonomia precisar acrescentar/alterar campos em um modelo histórico,
-- cria uma nova versão antes de tocar na definição. Os registros antigos
-- continuam apontando para a versão anterior.
CREATE TEMP TABLE tmp_sport_taxonomy_model_upgrade ON COMMIT DROP AS
SELECT
    mm.idmodelo AS idmodelo_antigo,
    'st' || substr(md5(mm.idmodelo || ':sport-taxonomy-v1'), 1, 19) AS idmodelo_novo,
    mm.padrao AS era_padrao
FROM modelos_modalidade mm
JOIN modalidades m ON m.idmodalidade = mm.idmodalidade
WHERE mm.ativo = TRUE
  AND mm.padrao = TRUE
  AND mm.idusuario IS NULL
  AND m.idusuario IS NULL
  AND EXISTS (SELECT 1 FROM registros_atividade ra WHERE ra.idmodelo = mm.idmodelo)
  AND (
      NOT EXISTS (SELECT 1 FROM campos_modelo c WHERE c.idmodelo = mm.idmodelo AND lower(c.slug) = 'duracao')
      OR (m.familia_hub IN ('cardio','athletics','outdoor','winter','motorsport')
          AND NOT EXISTS (SELECT 1 FROM campos_modelo c WHERE c.idmodelo = mm.idmodelo AND lower(c.slug) = 'distancia'))
      OR (m.familia_hub IN ('cardio','outdoor','winter')
          AND NOT EXISTS (SELECT 1 FROM campos_modelo c WHERE c.idmodelo = mm.idmodelo AND lower(c.slug) IN ('elevacao','desnivel')))
      OR (m.familia_hub <> 'precision'
          AND NOT EXISTS (SELECT 1 FROM campos_modelo c WHERE c.idmodelo = mm.idmodelo AND lower(c.slug) IN ('fc-media','fc_media')))
      OR m.slug IN ('salto-em-distancia','salto-triplo','salto-em-altura','salto-com-vara','arremesso-de-peso','lancamento-de-disco','lancamento-de-dardo','lancamento-de-martelo')
  );

UPDATE modelos_modalidade mm
SET padrao = FALSE, ativo = FALSE
FROM tmp_sport_taxonomy_model_upgrade t
WHERE mm.idmodelo = t.idmodelo_antigo;

INSERT INTO modelos_modalidade (
    idmodelo, idmodalidade, idusuario, idmodelo_anterior, nome, slug, descricao,
    tipo_unidade_padrao, rotulo_unidade, permite_multiplas_unidades, versao,
    padrao, ativo, visibilidade, status_publicacao
)
SELECT
    t.idmodelo_novo,
    antigo.idmodalidade,
    antigo.idusuario,
    antigo.idmodelo,
    antigo.nome,
    antigo.slug,
    antigo.descricao,
    antigo.tipo_unidade_padrao,
    antigo.rotulo_unidade,
    antigo.permite_multiplas_unidades,
    COALESCE((
        SELECT MAX(v.versao)
        FROM modelos_modalidade v
        WHERE v.idmodalidade = antigo.idmodalidade
          AND lower(v.slug) = lower(antigo.slug)
          AND v.idusuario IS NOT DISTINCT FROM antigo.idusuario
    ), antigo.versao) + 1,
    t.era_padrao,
    TRUE,
    antigo.visibilidade,
    antigo.status_publicacao
FROM tmp_sport_taxonomy_model_upgrade t
JOIN modelos_modalidade antigo ON antigo.idmodelo = t.idmodelo_antigo
ON CONFLICT (idmodelo) DO NOTHING;

INSERT INTO campos_modelo (
    idcampo, idmodelo, nome, slug, rotulo, tipo_campo, escopo,
    idgrandeza, idunidade, obrigatorio, ordem, ativo, exibicao_padrao, grupo_ui
)
SELECT
    'sc' || substr(md5(t.idmodelo_novo || ':' || c.idcampo), 1, 19),
    t.idmodelo_novo, c.nome, c.slug, c.rotulo, c.tipo_campo, c.escopo,
    c.idgrandeza, c.idunidade, c.obrigatorio, c.ordem, c.ativo,
    c.exibicao_padrao, c.grupo_ui
FROM tmp_sport_taxonomy_model_upgrade t
JOIN campos_modelo c ON c.idmodelo = t.idmodelo_antigo
ON CONFLICT DO NOTHING;

INSERT INTO campos_modelo_opcoes (idopcao, idcampo, rotulo, valor, ordem, ativo)
SELECT
    'so' || substr(md5(novo.idcampo || ':' || o.idopcao), 1, 19),
    novo.idcampo, o.rotulo, o.valor, o.ordem, o.ativo
FROM tmp_sport_taxonomy_model_upgrade t
JOIN campos_modelo antigo ON antigo.idmodelo = t.idmodelo_antigo
JOIN campos_modelo_opcoes o ON o.idcampo = antigo.idcampo
JOIN campos_modelo novo
  ON novo.idmodelo = t.idmodelo_novo
 AND lower(novo.slug) = lower(antigo.slug)
ON CONFLICT DO NOTHING;

UPDATE modalidades_usuario mu
SET idmodelo_ativo = t.idmodelo_novo
FROM tmp_sport_taxonomy_model_upgrade t
WHERE mu.idmodelo_ativo = t.idmodelo_antigo;

INSERT INTO campos_modelo (idcampo, idmodelo, nome, slug, rotulo, tipo_campo, escopo, idgrandeza, idunidade, obrigatorio, ordem, exibicao_padrao, grupo_ui)
SELECT 'cf_' || substr(md5(mm.idmodelo || ':duracao'), 1, 18), mm.idmodelo, 'duracao', 'duracao', 'Duração', 'intervalo', 'unidade', 'g_tempo', NULL, FALSE, COALESCE((SELECT MAX(c2.ordem) + 1 FROM campos_modelo c2 WHERE c2.idmodelo = mm.idmodelo), 1), TRUE, 'principal'
FROM modelos_modalidade mm JOIN modalidades m ON m.idmodalidade = mm.idmodalidade
WHERE mm.ativo = TRUE AND mm.padrao = TRUE AND m.idusuario IS NULL
  AND NOT EXISTS (SELECT 1 FROM campos_modelo c WHERE c.idmodelo = mm.idmodelo AND lower(c.slug) = 'duracao')
ON CONFLICT DO NOTHING;

INSERT INTO campos_modelo (idcampo, idmodelo, nome, slug, rotulo, tipo_campo, escopo, idgrandeza, idunidade, obrigatorio, ordem, exibicao_padrao, grupo_ui)
SELECT 'cf_' || substr(md5(mm.idmodelo || ':distancia'), 1, 18), mm.idmodelo, 'distancia', 'distancia', 'Distância', 'decimal', 'unidade', 'g_distancia', 'u_km', FALSE, COALESCE((SELECT MAX(c2.ordem) + 1 FROM campos_modelo c2 WHERE c2.idmodelo = mm.idmodelo), 2), TRUE, 'principal'
FROM modelos_modalidade mm JOIN modalidades m ON m.idmodalidade = mm.idmodalidade
WHERE mm.ativo = TRUE AND mm.padrao = TRUE AND m.idusuario IS NULL AND m.familia_hub IN ('cardio','athletics','outdoor','winter','motorsport')
  AND NOT EXISTS (SELECT 1 FROM campos_modelo c WHERE c.idmodelo = mm.idmodelo AND lower(c.slug) = 'distancia')
ON CONFLICT DO NOTHING;

INSERT INTO campos_modelo (idcampo, idmodelo, nome, slug, rotulo, tipo_campo, escopo, idgrandeza, idunidade, obrigatorio, ordem, exibicao_padrao, grupo_ui)
SELECT 'cf_' || substr(md5(mm.idmodelo || ':elevacao'), 1, 18), mm.idmodelo, 'elevacao', 'elevacao', 'Elevação', 'decimal', 'unidade', 'g_distancia', 'u_m', FALSE, COALESCE((SELECT MAX(c2.ordem) + 1 FROM campos_modelo c2 WHERE c2.idmodelo = mm.idmodelo), 3), TRUE, 'principal'
FROM modelos_modalidade mm JOIN modalidades m ON m.idmodalidade = mm.idmodalidade
WHERE mm.ativo = TRUE AND mm.padrao = TRUE AND m.idusuario IS NULL AND m.familia_hub IN ('cardio','outdoor','winter')
  AND NOT EXISTS (SELECT 1 FROM campos_modelo c WHERE c.idmodelo = mm.idmodelo AND lower(c.slug) IN ('elevacao','desnivel'))
ON CONFLICT DO NOTHING;

INSERT INTO campos_modelo (idcampo, idmodelo, nome, slug, rotulo, tipo_campo, escopo, idgrandeza, idunidade, obrigatorio, ordem, exibicao_padrao, grupo_ui)
SELECT 'cf_' || substr(md5(mm.idmodelo || ':fc-media'), 1, 18), mm.idmodelo, 'fc_media', 'fc-media', 'FC média (bpm)', 'inteiro', 'unidade', NULL, NULL, FALSE, COALESCE((SELECT MAX(c2.ordem) + 1 FROM campos_modelo c2 WHERE c2.idmodelo = mm.idmodelo), 10), FALSE, 'extras'
FROM modelos_modalidade mm JOIN modalidades m ON m.idmodalidade = mm.idmodalidade
WHERE mm.ativo = TRUE AND mm.padrao = TRUE AND m.idusuario IS NULL AND m.familia_hub <> 'precision'
  AND NOT EXISTS (SELECT 1 FROM campos_modelo c WHERE c.idmodelo = mm.idmodelo AND lower(c.slug) IN ('fc-media','fc_media'))
ON CONFLICT DO NOTHING;

UPDATE modelos_modalidade mm
SET tipo_unidade_padrao = 'tentativa', rotulo_unidade = 'Tentativa', permite_multiplas_unidades = TRUE
FROM modalidades m
WHERE m.idmodalidade = mm.idmodalidade
  AND mm.ativo = TRUE AND mm.padrao = TRUE AND m.idusuario IS NULL
  AND m.slug IN ('salto-em-distancia','salto-triplo','salto-em-altura','salto-com-vara','arremesso-de-peso','lancamento-de-disco','lancamento-de-dardo','lancamento-de-martelo');

UPDATE campos_modelo c
SET escopo = 'registro', exibicao_padrao = TRUE, grupo_ui = 'principal'
FROM modelos_modalidade mm JOIN modalidades m ON m.idmodalidade = mm.idmodalidade
WHERE c.idmodelo = mm.idmodelo
  AND mm.ativo = TRUE AND mm.padrao = TRUE AND m.idusuario IS NULL
  AND m.slug IN ('salto-em-distancia','salto-triplo','salto-em-altura','salto-com-vara','arremesso-de-peso','lancamento-de-disco','lancamento-de-dardo','lancamento-de-martelo')
  AND lower(c.slug) = 'duracao';

UPDATE campos_modelo c
SET nome = 'marca', slug = 'marca', rotulo = 'Marca', escopo = 'unidade', idgrandeza = 'g_distancia', idunidade = 'u_m', obrigatorio = FALSE, exibicao_padrao = TRUE, grupo_ui = 'principal'
FROM modelos_modalidade mm JOIN modalidades m ON m.idmodalidade = mm.idmodalidade
WHERE c.idmodelo = mm.idmodelo
  AND mm.ativo = TRUE AND mm.padrao = TRUE AND m.idusuario IS NULL
  AND m.slug IN ('salto-em-distancia','salto-triplo','salto-em-altura','salto-com-vara','arremesso-de-peso','lancamento-de-disco','lancamento-de-dardo','lancamento-de-martelo')
  AND lower(c.slug) = 'distancia';

INSERT INTO campos_modelo (idcampo, idmodelo, nome, slug, rotulo, tipo_campo, escopo, idgrandeza, idunidade, obrigatorio, ordem, exibicao_padrao, grupo_ui)
SELECT 'cf_' || substr(md5(mm.idmodelo || ':tentativa-nula'), 1, 18), mm.idmodelo, 'tentativa_nula', 'tentativa-nula', 'Tentativa nula / falha', 'booleano', 'unidade', NULL, NULL, FALSE, COALESCE((SELECT MAX(c2.ordem) + 1 FROM campos_modelo c2 WHERE c2.idmodelo = mm.idmodelo), 2), TRUE, 'principal'
FROM modelos_modalidade mm JOIN modalidades m ON m.idmodalidade = mm.idmodalidade
WHERE mm.ativo = TRUE AND mm.padrao = TRUE AND m.idusuario IS NULL
  AND m.slug IN ('salto-em-distancia','salto-triplo','salto-em-altura','salto-com-vara','arremesso-de-peso','lancamento-de-disco','lancamento-de-dardo','lancamento-de-martelo')
  AND NOT EXISTS (SELECT 1 FROM campos_modelo c WHERE c.idmodelo = mm.idmodelo AND lower(c.slug) = 'tentativa-nula')
ON CONFLICT DO NOTHING;

INSERT INTO campos_modelo (idcampo, idmodelo, nome, slug, rotulo, tipo_campo, escopo, idgrandeza, idunidade, obrigatorio, ordem, exibicao_padrao, grupo_ui)
SELECT 'cf_' || substr(md5(mm.idmodelo || ':vento'), 1, 18), mm.idmodelo, 'vento', 'vento', 'Vento', 'decimal', 'unidade', NULL, NULL, FALSE, COALESCE((SELECT MAX(c2.ordem) + 1 FROM campos_modelo c2 WHERE c2.idmodelo = mm.idmodelo), 3), FALSE, 'extras'
FROM modelos_modalidade mm JOIN modalidades m ON m.idmodalidade = mm.idmodalidade
WHERE mm.ativo = TRUE AND mm.padrao = TRUE AND m.idusuario IS NULL
  AND m.slug IN ('salto-em-distancia','salto-triplo')
  AND NOT EXISTS (SELECT 1 FROM campos_modelo c WHERE c.idmodelo = mm.idmodelo AND lower(c.slug) = 'vento')
ON CONFLICT DO NOTHING;

COMMIT;


-- ============================================================================
-- Consolidated from: 20260902_02_activity_energy_v1.sql
-- ============================================================================
BEGIN;
SET search_path TO stridebr, public;

CREATE TABLE IF NOT EXISTS historico_peso_usuario (
    idpesagem VARCHAR(21) PRIMARY KEY,
    idusuario VARCHAR(21) NOT NULL REFERENCES usuarios(idusuario) ON DELETE CASCADE,
    peso_kg NUMERIC(6,2) NOT NULL CHECK (peso_kg > 0 AND peso_kg <= 9999),
    data_medicao DATE NOT NULL,
    origem VARCHAR(30) NOT NULL DEFAULT 'perfil',
    data_criacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (idusuario, data_medicao)
);

CREATE INDEX IF NOT EXISTS ix_historico_peso_usuario_data
ON historico_peso_usuario (idusuario, data_medicao DESC);

INSERT INTO historico_peso_usuario (idpesagem, idusuario, peso_kg, data_medicao, origem)
SELECT 'wp_' || substr(md5(idusuario || ':' || CURRENT_DATE::text), 1, 18), idusuario, pesousuario, CURRENT_DATE, 'migracao'
FROM usuarios
WHERE pesousuario IS NOT NULL AND pesousuario > 0
ON CONFLICT (idusuario, data_medicao) DO NOTHING;

CREATE TABLE IF NOT EXISTS modalidades_energia (
    idmodalidade VARCHAR(21) PRIMARY KEY REFERENCES modalidades(idmodalidade) ON DELETE CASCADE,
    modelo VARCHAR(32) NOT NULL DEFAULT 'generic',
    met_leve NUMERIC(6,2) NOT NULL CHECK (met_leve > 0),
    met_moderado NUMERIC(6,2) NOT NULL CHECK (met_moderado > 0),
    met_vigoroso NUMERIC(6,2) NOT NULL CHECK (met_vigoroso > 0),
    met_maximo NUMERIC(6,2) NOT NULL CHECK (met_maximo > 0),
    configuracao JSONB NOT NULL DEFAULT '{}'::jsonb,
    atualizado_em TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CHECK (met_leve <= met_moderado AND met_moderado <= met_vigoroso AND met_vigoroso <= met_maximo)
);

INSERT INTO modalidades_energia (idmodalidade, modelo, met_leve, met_moderado, met_vigoroso, met_maximo)
SELECT m.idmodalidade,
       CASE m.familia_hub
           WHEN 'strength' THEN 'strength'
           WHEN 'cardio' THEN 'generic_cardio'
           WHEN 'athletics' THEN 'athletics'
           WHEN 'racket' THEN 'racket'
           WHEN 'team' THEN 'team'
           WHEN 'combat' THEN 'combat'
           WHEN 'movement' THEN 'movement'
           WHEN 'outdoor' THEN 'outdoor'
           WHEN 'precision' THEN 'precision'
           WHEN 'winter' THEN 'winter'
           WHEN 'dance' THEN 'dance'
           WHEN 'equestrian' THEN 'equestrian'
           WHEN 'motorsport' THEN 'motorsport'
           ELSE 'generic'
       END,
       CASE m.familia_hub WHEN 'strength' THEN 3.5 WHEN 'cardio' THEN 4.0 WHEN 'athletics' THEN 4.0 WHEN 'racket' THEN 4.0 WHEN 'team' THEN 5.0 WHEN 'combat' THEN 4.0 WHEN 'movement' THEN 2.5 WHEN 'outdoor' THEN 3.5 WHEN 'precision' THEN 2.0 WHEN 'winter' THEN 4.0 WHEN 'dance' THEN 3.0 WHEN 'equestrian' THEN 3.0 WHEN 'motorsport' THEN 2.5 ELSE 2.5 END,
       CASE m.familia_hub WHEN 'strength' THEN 5.0 WHEN 'cardio' THEN 6.0 WHEN 'athletics' THEN 6.0 WHEN 'racket' THEN 6.0 WHEN 'team' THEN 7.0 WHEN 'combat' THEN 7.0 WHEN 'movement' THEN 3.5 WHEN 'outdoor' THEN 6.0 WHEN 'precision' THEN 2.5 WHEN 'winter' THEN 6.5 WHEN 'dance' THEN 5.0 WHEN 'equestrian' THEN 4.5 WHEN 'motorsport' THEN 4.0 ELSE 4.5 END,
       CASE m.familia_hub WHEN 'strength' THEN 6.0 WHEN 'cardio' THEN 9.0 WHEN 'athletics' THEN 10.0 WHEN 'racket' THEN 8.0 WHEN 'team' THEN 9.0 WHEN 'combat' THEN 10.0 WHEN 'movement' THEN 5.0 WHEN 'outdoor' THEN 8.0 WHEN 'precision' THEN 3.5 WHEN 'winter' THEN 9.0 WHEN 'dance' THEN 7.5 WHEN 'equestrian' THEN 6.0 WHEN 'motorsport' THEN 6.0 ELSE 7.0 END,
       CASE m.familia_hub WHEN 'strength' THEN 7.5 WHEN 'cardio' THEN 12.0 WHEN 'athletics' THEN 14.0 WHEN 'racket' THEN 10.0 WHEN 'team' THEN 12.0 WHEN 'combat' THEN 12.5 WHEN 'movement' THEN 7.0 WHEN 'outdoor' THEN 11.0 WHEN 'precision' THEN 5.0 WHEN 'winter' THEN 13.0 WHEN 'dance' THEN 10.0 WHEN 'equestrian' THEN 8.0 WHEN 'motorsport' THEN 8.0 ELSE 10.0 END
FROM modalidades m
WHERE m.idusuario IS NULL
ON CONFLICT (idmodalidade) DO NOTHING;

UPDATE modalidades_energia me SET modelo = 'run', met_leve = 6.5, met_moderado = 9.3, met_vigoroso = 12.0, met_maximo = 16.8, atualizado_em = NOW()
FROM modalidades m WHERE m.idmodalidade = me.idmodalidade AND m.slug IN ('corrida','corrida-de-rua','corrida-em-trilha','corrida-em-esteira','jogging','cross-country','corrida-de-montanha','corrida-com-obstaculos','atletismo-800m','atletismo-1500m','atletismo-milha','atletismo-3000m','atletismo-5000m','atletismo-10000m');

UPDATE modalidades_energia me SET modelo = 'sprint', met_leve = 8.0, met_moderado = 10.0, met_vigoroso = 13.0, met_maximo = 18.0, atualizado_em = NOW()
FROM modalidades m WHERE m.idmodalidade = me.idmodalidade AND m.slug IN ('atletismo-60m','atletismo-100m','atletismo-200m','atletismo-400m','revezamento-4x100m','revezamento-4x400m','60m-com-barreiras','100m-com-barreiras','110m-com-barreiras','400m-com-barreiras','3000m-com-obstaculos');

UPDATE modalidades_energia me SET modelo = 'walk', met_leve = 2.8, met_moderado = 3.8, met_vigoroso = 6.0, met_maximo = 8.5, atualizado_em = NOW()
FROM modalidades m WHERE m.idmodalidade = me.idmodalidade AND m.slug IN ('caminhada','trilha','nordic-walking','rucking','marcha-atletica','raquete-de-neve');

UPDATE modalidades_energia me SET modelo = 'cycle', met_leve = 4.0, met_moderado = 8.0, met_vigoroso = 12.0, met_maximo = 16.8, atualizado_em = NOW()
FROM modalidades m WHERE m.idmodalidade = me.idmodalidade AND m.slug IN ('ciclismo','ciclismo-de-estrada','ciclismo-urbano','mountain-bike','gravel','downhill','bmx','ciclismo-indoor','spinning','handcycle','velomovel','bicicleta-eletrica','e-mountain-bike');

UPDATE modalidades_energia me SET modelo = 'swim', met_leve = 6.0, met_moderado = 8.3, met_vigoroso = 10.0, met_maximo = 14.5, atualizado_em = NOW()
FROM modalidades m WHERE m.idmodalidade = me.idmodalidade AND m.slug IN ('natacao','natacao-em-piscina','natacao-aguas-abertas','aquajogging');

UPDATE modalidades_energia me SET modelo = 'row', met_leve = 3.5, met_moderado = 7.0, met_vigoroso = 10.0, met_maximo = 14.0, atualizado_em = NOW()
FROM modalidades m WHERE m.idmodalidade = me.idmodalidade AND m.slug IN ('remo','remo-indoor','canoagem','caiaque');

UPDATE modalidades_energia me SET modelo = 'hiit', met_leve = 6.0, met_moderado = 8.0, met_vigoroso = 11.0, met_maximo = 13.0, atualizado_em = NOW()
FROM modalidades m WHERE m.idmodalidade = me.idmodalidade AND m.slug IN ('hiit','crossfit','hyrox','boot-camp','treino-funcional');

UPDATE modalidades_energia me SET modelo = 'strength', met_leve = 3.0, met_moderado = 4.5, met_vigoroso = 6.0, met_maximo = 7.5, atualizado_em = NOW()
FROM modalidades m WHERE m.idmodalidade = me.idmodalidade AND m.slug IN ('musculacao','powerlifting','levantamento-olimpico','strongman','fisiculturismo','kettlebell','calistenia','street-workout','grip-sport','queda-de-braco','treino-com-treno','treino-com-sandbag');

UPDATE modalidades_energia me SET modelo = 'field_event', met_leve = 3.5, met_moderado = 5.0, met_vigoroso = 6.5, met_maximo = 8.0, atualizado_em = NOW()
FROM modalidades m WHERE m.idmodalidade = me.idmodalidade AND m.slug IN ('salto-em-distancia','salto-triplo','salto-em-altura','salto-com-vara','arremesso-de-peso','lancamento-de-disco','lancamento-de-dardo','lancamento-de-martelo');

UPDATE modalidades_energia me SET modelo = 'racket', met_leve = 4.5, met_moderado = 6.8, met_vigoroso = 8.0, met_maximo = 10.0, atualizado_em = NOW()
FROM modalidades m WHERE m.idmodalidade = me.idmodalidade AND m.slug IN ('tenis','soft-tennis');

UPDATE modalidades_energia me SET modelo = 'racket', met_leve = 4.0, met_moderado = 6.0, met_vigoroso = 7.5, met_maximo = 9.0, atualizado_em = NOW()
FROM modalidades m WHERE m.idmodalidade = me.idmodalidade AND m.slug IN ('padel','beach-tennis','pickleball','frescobol','crossminton');

UPDATE modalidades_energia me SET modelo = 'racket', met_leve = 4.0, met_moderado = 5.5, met_vigoroso = 7.5, met_maximo = 9.0, atualizado_em = NOW()
FROM modalidades m WHERE m.idmodalidade = me.idmodalidade AND m.slug IN ('badminton');

UPDATE modalidades_energia me SET modelo = 'racket', met_leve = 5.0, met_moderado = 7.3, met_vigoroso = 9.0, met_maximo = 11.0, atualizado_em = NOW()
FROM modalidades m WHERE m.idmodalidade = me.idmodalidade AND m.slug IN ('squash','raquetebol');

UPDATE modalidades_energia me SET modelo = 'racket', met_leve = 2.5, met_moderado = 4.0, met_vigoroso = 5.0, met_maximo = 7.0, atualizado_em = NOW()
FROM modalidades m WHERE m.idmodalidade = me.idmodalidade AND m.slug IN ('tenis-de-mesa');

UPDATE modalidades_energia me SET modelo = 'team', met_leve = 5.0, met_moderado = 7.0, met_vigoroso = 10.0, met_maximo = 12.0, atualizado_em = NOW()
FROM modalidades m WHERE m.idmodalidade = me.idmodalidade AND m.slug IN ('futebol','futsal','futebol-society','futebol-de-areia','futebol-americano','flag-football','rugby','rugby-sevens','touch-rugby','futebol-australiano','futebol-gaelico','hurling');

UPDATE modalidades_energia me SET modelo = 'team', met_leve = 4.5, met_moderado = 6.5, met_vigoroso = 9.3, met_maximo = 11.0, atualizado_em = NOW()
FROM modalidades m WHERE m.idmodalidade = me.idmodalidade AND m.slug IN ('basquete','basquete-3x3','handebol','handebol-de-areia','lacrosse','floorball','hoquei-de-campo','hoquei-indoor','hoquei-no-gelo','polo-aquatico');

UPDATE modalidades_energia me SET modelo = 'team', met_leve = 3.0, met_moderado = 4.0, met_vigoroso = 6.0, met_maximo = 8.0, atualizado_em = NOW()
FROM modalidades m WHERE m.idmodalidade = me.idmodalidade AND m.slug IN ('volei','volei-de-praia','netball','korfball','dodgeball');

UPDATE modalidades_energia me SET modelo = 'team', met_leve = 2.5, met_moderado = 4.0, met_vigoroso = 6.0, met_maximo = 8.0, atualizado_em = NOW()
FROM modalidades m WHERE m.idmodalidade = me.idmodalidade AND m.slug IN ('baseball','softball','cricket','criquete');

UPDATE modalidades_energia me SET modelo = 'combat', met_leve = 5.8, met_moderado = 7.8, met_vigoroso = 10.8, met_maximo = 12.3, atualizado_em = NOW()
FROM modalidades m WHERE m.idmodalidade = me.idmodalidade AND m.slug IN ('boxe');

UPDATE modalidades_energia me SET modelo = 'combat', met_leve = 5.0, met_moderado = 7.0, met_vigoroso = 10.0, met_maximo = 12.5, atualizado_em = NOW()
FROM modalidades m WHERE m.idmodalidade = me.idmodalidade AND m.slug IN ('muay-thai','kickboxing','mma','karate','taekwondo','kung-fu','wushu','sanda','savate','lethwei','capoeira','esgrima');

UPDATE modalidades_energia me SET modelo = 'combat', met_leve = 4.0, met_moderado = 6.5, met_vigoroso = 9.0, met_maximo = 11.0, atualizado_em = NOW()
FROM modalidades m WHERE m.idmodalidade = me.idmodalidade AND m.slug IN ('jiu-jitsu','grappling','judo','wrestling','wrestling-livre','greco-romana','sambo','sumo','luta-olimpica');

UPDATE modalidades_energia me SET modelo = 'movement', met_leve = 2.0, met_moderado = 2.5, met_vigoroso = 3.5, met_maximo = 5.0, atualizado_em = NOW()
FROM modalidades m WHERE m.idmodalidade = me.idmodalidade AND m.slug IN ('mobilidade','alongamento','tai-chi','qi-gong');

UPDATE modalidades_energia me SET modelo = 'movement', met_leve = 2.3, met_moderado = 3.0, met_vigoroso = 4.5, met_maximo = 6.0, atualizado_em = NOW()
FROM modalidades m WHERE m.idmodalidade = me.idmodalidade AND m.slug IN ('yoga','power-yoga','hot-yoga','pilates','acroyoga');

UPDATE modalidades_energia me SET modelo = 'movement', met_leve = 3.0, met_moderado = 4.5, met_vigoroso = 6.0, met_maximo = 8.0, atualizado_em = NOW()
FROM modalidades m WHERE m.idmodalidade = me.idmodalidade AND m.slug IN ('ginastica-artistica','ginastica-ritmica','ginastica-acrobatica','ginastica-aerobica','trampolim','cheerleading','pole-sport','tecido-acrobatico','lira-aerea');

UPDATE modalidades_energia me SET modelo = 'movement', met_leve = 4.0, met_moderado = 7.0, met_vigoroso = 9.0, met_maximo = 12.0, atualizado_em = NOW()
FROM modalidades m WHERE m.idmodalidade = me.idmodalidade AND m.slug IN ('parkour','freerunning');

UPDATE modalidades_energia me SET modelo = 'outdoor', met_leve = 4.0, met_moderado = 6.0, met_vigoroso = 8.0, met_maximo = 10.0, atualizado_em = NOW()
FROM modalidades m WHERE m.idmodalidade = me.idmodalidade AND m.slug IN ('hiking','trekking','montanhismo','orientacao','corrida-de-aventura','via-ferrata','canyoning');

UPDATE modalidades_energia me SET modelo = 'outdoor', met_leve = 4.0, met_moderado = 6.5, met_vigoroso = 8.5, met_maximo = 10.0, atualizado_em = NOW()
FROM modalidades m WHERE m.idmodalidade = me.idmodalidade AND m.slug IN ('escalada','escalada-esportiva','escalada-tradicional','escalada-indoor','boulder');

UPDATE modalidades_energia me SET modelo = 'outdoor', met_leve = 3.0, met_moderado = 5.0, met_vigoroso = 6.5, met_maximo = 8.5, atualizado_em = NOW()
FROM modalidades m WHERE m.idmodalidade = me.idmodalidade AND m.slug IN ('surfe','bodyboard','stand-up-paddle','windsurf','kitesurf','wakeboard','esqui-aquatico','vela');

UPDATE modalidades_energia me SET modelo = 'precision', met_leve = 3.0, met_moderado = 4.8, met_vigoroso = 6.0, met_maximo = 7.0, atualizado_em = NOW()
FROM modalidades m WHERE m.idmodalidade = me.idmodalidade AND m.slug IN ('golfe','disc-golf');

UPDATE modalidades_energia me SET modelo = 'precision', met_leve = 2.0, met_moderado = 3.0, met_vigoroso = 4.0, met_maximo = 5.0, atualizado_em = NOW()
FROM modalidades m WHERE m.idmodalidade = me.idmodalidade AND m.slug IN ('tiro-com-arco','tiro-esportivo','dardos','boliche','bocha','petanca','sinuca','bilhar','snooker','minigolfe');

UPDATE modalidades_energia me SET modelo = 'winter', met_leve = 4.3, met_moderado = 6.3, met_vigoroso = 8.0, met_maximo = 10.0, atualizado_em = NOW()
FROM modalidades m WHERE m.idmodalidade = me.idmodalidade AND m.slug IN ('esqui-alpino','esqui-fora-de-pista','snowboard','esqui-freestyle');

UPDATE modalidades_energia me SET modelo = 'winter', met_leve = 6.8, met_moderado = 9.0, met_vigoroso = 12.0, met_maximo = 16.0, atualizado_em = NOW()
FROM modalidades m WHERE m.idmodalidade = me.idmodalidade AND m.slug IN ('esqui-cross-country','esqui-nordico','esqui-alpinismo','combinado-nordico','biatlo');

UPDATE modalidades_energia me SET modelo = 'winter', met_leve = 5.0, met_moderado = 7.0, met_vigoroso = 10.0, met_maximo = 13.8, atualizado_em = NOW()
FROM modalidades m WHERE m.idmodalidade = me.idmodalidade AND m.slug IN ('patinacao-no-gelo','patinacao-artistica','patinacao-velocidade-gelo');

UPDATE modalidades_energia me SET modelo = 'dance', met_leve = 3.0, met_moderado = 5.0, met_vigoroso = 7.5, met_maximo = 10.0, atualizado_em = NOW()
FROM modalidades m WHERE m.idmodalidade = me.idmodalidade AND m.familia_hub = 'dance';

UPDATE modalidades_energia me SET modelo = 'dance', met_leve = 4.0, met_moderado = 6.5, met_vigoroso = 8.5, met_maximo = 10.0, atualizado_em = NOW()
FROM modalidades m WHERE m.idmodalidade = me.idmodalidade AND m.slug IN ('zumba','breaking');

UPDATE modalidades_energia me SET modelo = 'equestrian', met_leve = 3.0, met_moderado = 4.5, met_vigoroso = 6.0, met_maximo = 8.0, atualizado_em = NOW()
FROM modalidades m WHERE m.idmodalidade = me.idmodalidade AND m.familia_hub = 'equestrian';

UPDATE modalidades_energia me SET modelo = 'motorsport', met_leve = 2.5, met_moderado = 4.0, met_vigoroso = 6.0, met_maximo = 8.0, atualizado_em = NOW()
FROM modalidades m WHERE m.idmodalidade = me.idmodalidade AND m.familia_hub = 'motorsport';

UPDATE modalidades_energia me SET modelo = 'motorsport', met_leve = 4.0, met_moderado = 6.0, met_vigoroso = 8.0, met_maximo = 10.0, atualizado_em = NOW()
FROM modalidades m WHERE m.idmodalidade = me.idmodalidade AND m.slug IN ('motocross','enduro-de-moto','trial-de-moto');

UPDATE modalidades_energia me SET modelo = 'field_event', met_leve = 4.0, met_moderado = 6.0, met_vigoroso = 7.0, met_maximo = 8.0, atualizado_em = NOW()
FROM modalidades m WHERE m.idmodalidade = me.idmodalidade AND m.slug IN ('salto-em-distancia','salto-triplo','salto-em-altura','salto-com-vara','lancamento-de-dardo');

UPDATE modalidades_energia me SET modelo = 'field_event', met_leve = 3.0, met_moderado = 4.0, met_vigoroso = 5.0, met_maximo = 6.0, atualizado_em = NOW()
FROM modalidades m WHERE m.idmodalidade = me.idmodalidade AND m.slug IN ('arremesso-de-peso','lancamento-de-disco','lancamento-de-martelo');

ALTER TABLE registros_atividade ADD COLUMN IF NOT EXISTS calorias_externas NUMERIC(10,2);
ALTER TABLE registros_atividade ADD COLUMN IF NOT EXISTS calorias_ativas_estimadas NUMERIC(10,2);
ALTER TABLE registros_atividade ADD COLUMN IF NOT EXISTS calorias_totais_estimadas NUMERIC(10,2);
ALTER TABLE registros_atividade ADD COLUMN IF NOT EXISTS calorias_fonte VARCHAR(40);
ALTER TABLE registros_atividade ADD COLUMN IF NOT EXISTS calorias_confianca VARCHAR(16);
ALTER TABLE registros_atividade ADD COLUMN IF NOT EXISTS calorias_metodo VARCHAR(40);
ALTER TABLE registros_atividade ADD COLUMN IF NOT EXISTS met_efetivo NUMERIC(7,3);
ALTER TABLE registros_atividade ADD COLUMN IF NOT EXISTS peso_calculo_kg NUMERIC(6,2);
ALTER TABLE registros_atividade ADD COLUMN IF NOT EXISTS data_calorias_atualizacao TIMESTAMPTZ;

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'ck_registro_calorias_valores' AND conrelid = 'registros_atividade'::regclass) THEN
        ALTER TABLE registros_atividade ADD CONSTRAINT ck_registro_calorias_valores CHECK (
            (calorias_externas IS NULL OR calorias_externas >= 0) AND
            (calorias_ativas_estimadas IS NULL OR calorias_ativas_estimadas >= 0) AND
            (calorias_totais_estimadas IS NULL OR calorias_totais_estimadas >= 0) AND
            (met_efetivo IS NULL OR met_efetivo > 0) AND
            (peso_calculo_kg IS NULL OR peso_calculo_kg > 0)
        );
    END IF;
END $$;

CREATE INDEX IF NOT EXISTS ix_registros_atividade_calorias
ON registros_atividade (idusuario, data_inicio DESC)
WHERE calorias_externas IS NOT NULL OR calorias_ativas_estimadas IS NOT NULL;

COMMIT;


-- ============================================================================
-- Consolidated from: 20260902_03_sport_performance_metrics.sql
-- ============================================================================
BEGIN;
SET search_path TO stridebr, public;

-- Preserve historical model definitions. If an active default model is already
-- referenced by activities and needs one of the performance fields below, create
-- a new version first and keep old activities pointing to the original model.
CREATE TEMP TABLE tmp_sport_performance_model_upgrade ON COMMIT DROP AS
SELECT
    mm.idmodelo AS idmodelo_antigo,
    'sp' || substr(md5(mm.idmodelo || ':sport-performance-v1'), 1, 19) AS idmodelo_novo,
    mm.padrao AS era_padrao
FROM modelos_modalidade mm
JOIN modalidades m ON m.idmodalidade = mm.idmodalidade
WHERE mm.ativo = TRUE
  AND mm.padrao = TRUE
  AND mm.idusuario IS NULL
  AND m.idusuario IS NULL
  AND EXISTS (SELECT 1 FROM registros_atividade ra WHERE ra.idmodelo = mm.idmodelo)
  AND (
      (m.familia_hub IN ('cardio','athletics','outdoor','winter','racket','team')
       AND NOT EXISTS (SELECT 1 FROM campos_modelo c WHERE c.idmodelo = mm.idmodelo AND lower(c.slug) IN ('fc-maxima','fc_maxima','frequencia-cardiaca-maxima')))
      OR ((m.familia_hub = 'cardio'
           OR m.slug IN ('atletismo-60m','atletismo-100m','atletismo-200m','atletismo-400m','atletismo-800m','atletismo-1500m','atletismo-milha','atletismo-3000m','atletismo-5000m','atletismo-10000m'))
          AND NOT EXISTS (SELECT 1 FROM campos_modelo c WHERE c.idmodelo = mm.idmodelo AND lower(c.slug) = 'cadencia'))
      OR ((m.familia_hub = 'cardio'
           OR m.slug IN ('esqui-cross-country','esqui-nordico','esqui-alpinismo','remo-indoor'))
          AND NOT EXISTS (SELECT 1 FROM campos_modelo c WHERE c.idmodelo = mm.idmodelo AND lower(c.slug) = 'potencia'))
      OR (m.slug IN ('atletismo-100m','atletismo-200m','100m-com-barreiras','110m-com-barreiras')
          AND NOT EXISTS (SELECT 1 FROM campos_modelo c WHERE c.idmodelo = mm.idmodelo AND lower(c.slug) = 'vento'))
      OR (m.slug IN ('atletismo-60m','atletismo-100m','atletismo-200m','atletismo-400m','60m-com-barreiras','100m-com-barreiras','110m-com-barreiras','400m-com-barreiras')
          AND NOT EXISTS (SELECT 1 FROM campos_modelo c WHERE c.idmodelo = mm.idmodelo AND lower(c.slug) = 'tempo-reacao'))
  );

UPDATE modelos_modalidade mm
SET padrao = FALSE, ativo = FALSE
FROM tmp_sport_performance_model_upgrade t
WHERE mm.idmodelo = t.idmodelo_antigo;

INSERT INTO modelos_modalidade (
    idmodelo, idmodalidade, idusuario, idmodelo_anterior, nome, slug, descricao,
    tipo_unidade_padrao, rotulo_unidade, permite_multiplas_unidades, versao,
    padrao, ativo, visibilidade, status_publicacao
)
SELECT
    t.idmodelo_novo,
    antigo.idmodalidade,
    antigo.idusuario,
    antigo.idmodelo,
    antigo.nome,
    antigo.slug,
    antigo.descricao,
    antigo.tipo_unidade_padrao,
    antigo.rotulo_unidade,
    antigo.permite_multiplas_unidades,
    COALESCE((
        SELECT MAX(v.versao)
        FROM modelos_modalidade v
        WHERE v.idmodalidade = antigo.idmodalidade
          AND lower(v.slug) = lower(antigo.slug)
          AND v.idusuario IS NOT DISTINCT FROM antigo.idusuario
    ), antigo.versao) + 1,
    t.era_padrao,
    TRUE,
    antigo.visibilidade,
    antigo.status_publicacao
FROM tmp_sport_performance_model_upgrade t
JOIN modelos_modalidade antigo ON antigo.idmodelo = t.idmodelo_antigo
ON CONFLICT (idmodelo) DO NOTHING;

INSERT INTO campos_modelo (
    idcampo, idmodelo, nome, slug, rotulo, tipo_campo, escopo,
    idgrandeza, idunidade, obrigatorio, ordem, ativo, exibicao_padrao, grupo_ui
)
SELECT
    'pc' || substr(md5(t.idmodelo_novo || ':' || c.idcampo), 1, 19),
    t.idmodelo_novo, c.nome, c.slug, c.rotulo, c.tipo_campo, c.escopo,
    c.idgrandeza, c.idunidade, c.obrigatorio, c.ordem, c.ativo,
    c.exibicao_padrao, c.grupo_ui
FROM tmp_sport_performance_model_upgrade t
JOIN campos_modelo c ON c.idmodelo = t.idmodelo_antigo
ON CONFLICT DO NOTHING;

INSERT INTO campos_modelo_opcoes (idopcao, idcampo, rotulo, valor, ordem, ativo)
SELECT
    'po' || substr(md5(novo.idcampo || ':' || o.idopcao), 1, 19),
    novo.idcampo, o.rotulo, o.valor, o.ordem, o.ativo
FROM tmp_sport_performance_model_upgrade t
JOIN campos_modelo antigo ON antigo.idmodelo = t.idmodelo_antigo
JOIN campos_modelo_opcoes o ON o.idcampo = antigo.idcampo
JOIN campos_modelo novo
  ON novo.idmodelo = t.idmodelo_novo
 AND lower(novo.slug) = lower(antigo.slug)
ON CONFLICT DO NOTHING;

UPDATE modalidades_usuario mu
SET idmodelo_ativo = t.idmodelo_novo
FROM tmp_sport_performance_model_upgrade t
WHERE mu.idmodelo_ativo = t.idmodelo_antigo;

INSERT INTO campos_modelo (idcampo, idmodelo, nome, slug, rotulo, tipo_campo, escopo, idgrandeza, idunidade, obrigatorio, ordem, exibicao_padrao, grupo_ui)
SELECT 'cf_' || substr(md5(mm.idmodelo || ':fc-maxima'), 1, 18), mm.idmodelo, 'fc_maxima', 'fc-maxima', 'FC máxima (bpm)', 'inteiro', 'unidade', NULL, NULL, FALSE, 11, FALSE, 'extras'
FROM modelos_modalidade mm JOIN modalidades m ON m.idmodalidade = mm.idmodalidade
WHERE mm.ativo = TRUE AND mm.padrao = TRUE AND m.idusuario IS NULL
  AND m.familia_hub IN ('cardio','athletics','outdoor','winter','racket','team')
  AND NOT EXISTS (SELECT 1 FROM campos_modelo c WHERE c.idmodelo = mm.idmodelo AND lower(c.slug) IN ('fc-maxima','fc_maxima','frequencia-cardiaca-maxima'))
ON CONFLICT DO NOTHING;

INSERT INTO campos_modelo (idcampo, idmodelo, nome, slug, rotulo, tipo_campo, escopo, idgrandeza, idunidade, obrigatorio, ordem, exibicao_padrao, grupo_ui)
SELECT 'cf_' || substr(md5(mm.idmodelo || ':cadencia'), 1, 18), mm.idmodelo, 'cadencia', 'cadencia', 'Cadência', 'inteiro', 'unidade', NULL, NULL, FALSE, 12, FALSE, 'extras'
FROM modelos_modalidade mm JOIN modalidades m ON m.idmodalidade = mm.idmodalidade
WHERE mm.ativo = TRUE AND mm.padrao = TRUE AND m.idusuario IS NULL
  AND (
    m.familia_hub = 'cardio'
    OR m.slug IN ('atletismo-60m','atletismo-100m','atletismo-200m','atletismo-400m','atletismo-800m','atletismo-1500m','atletismo-milha','atletismo-3000m','atletismo-5000m','atletismo-10000m')
  )
  AND NOT EXISTS (SELECT 1 FROM campos_modelo c WHERE c.idmodelo = mm.idmodelo AND lower(c.slug) = 'cadencia')
ON CONFLICT DO NOTHING;

INSERT INTO campos_modelo (idcampo, idmodelo, nome, slug, rotulo, tipo_campo, escopo, idgrandeza, idunidade, obrigatorio, ordem, exibicao_padrao, grupo_ui)
SELECT 'cf_' || substr(md5(mm.idmodelo || ':potencia'), 1, 18), mm.idmodelo, 'potencia', 'potencia', 'Potência média (W)', 'inteiro', 'unidade', NULL, NULL, FALSE, 13, FALSE, 'extras'
FROM modelos_modalidade mm JOIN modalidades m ON m.idmodalidade = mm.idmodalidade
WHERE mm.ativo = TRUE AND mm.padrao = TRUE AND m.idusuario IS NULL
  AND (
    m.familia_hub = 'cardio'
    OR m.slug IN ('esqui-cross-country','esqui-nordico','esqui-alpinismo','remo-indoor')
  )
  AND NOT EXISTS (SELECT 1 FROM campos_modelo c WHERE c.idmodelo = mm.idmodelo AND lower(c.slug) = 'potencia')
ON CONFLICT DO NOTHING;

INSERT INTO campos_modelo (idcampo, idmodelo, nome, slug, rotulo, tipo_campo, escopo, idgrandeza, idunidade, obrigatorio, ordem, exibicao_padrao, grupo_ui)
SELECT 'cf_' || substr(md5(mm.idmodelo || ':vento-pista'), 1, 18), mm.idmodelo, 'vento', 'vento', 'Vento (m/s)', 'decimal', 'registro', NULL, NULL, FALSE, 20, FALSE, 'extras'
FROM modelos_modalidade mm JOIN modalidades m ON m.idmodalidade = mm.idmodalidade
WHERE mm.ativo = TRUE AND mm.padrao = TRUE AND m.idusuario IS NULL
  AND m.slug IN ('atletismo-100m','atletismo-200m','100m-com-barreiras','110m-com-barreiras')
  AND NOT EXISTS (SELECT 1 FROM campos_modelo c WHERE c.idmodelo = mm.idmodelo AND lower(c.slug) = 'vento')
ON CONFLICT DO NOTHING;

INSERT INTO campos_modelo (idcampo, idmodelo, nome, slug, rotulo, tipo_campo, escopo, idgrandeza, idunidade, obrigatorio, ordem, exibicao_padrao, grupo_ui)
SELECT 'cf_' || substr(md5(mm.idmodelo || ':tempo-reacao'), 1, 18), mm.idmodelo, 'tempo_reacao', 'tempo-reacao', 'Tempo de reação (s)', 'decimal', 'registro', NULL, NULL, FALSE, 21, FALSE, 'extras'
FROM modelos_modalidade mm JOIN modalidades m ON m.idmodalidade = mm.idmodalidade
WHERE mm.ativo = TRUE AND mm.padrao = TRUE AND m.idusuario IS NULL
  AND m.slug IN ('atletismo-60m','atletismo-100m','atletismo-200m','atletismo-400m','60m-com-barreiras','100m-com-barreiras','110m-com-barreiras','400m-com-barreiras')
  AND NOT EXISTS (SELECT 1 FROM campos_modelo c WHERE c.idmodelo = mm.idmodelo AND lower(c.slug) = 'tempo-reacao')
ON CONFLICT DO NOTHING;

COMMIT;


-- ============================================================================
-- Consolidated from: 20260902_04_sport_session_details.sql
-- ============================================================================
BEGIN;
SET search_path TO stridebr, public;

-- Session-detail fields/options cannot be appended to a model that is already
-- part of activity history. Version the relevant active defaults first.
CREATE TEMP TABLE tmp_sport_details_model_upgrade ON COMMIT DROP AS
SELECT
    mm.idmodelo AS idmodelo_antigo,
    'sd' || substr(md5(mm.idmodelo || ':sport-session-details-v1'), 1, 19) AS idmodelo_novo,
    mm.padrao AS era_padrao
FROM modelos_modalidade mm
JOIN modalidades m ON m.idmodalidade = mm.idmodalidade
WHERE mm.ativo = TRUE
  AND mm.padrao = TRUE
  AND mm.idusuario IS NULL
  AND m.idusuario IS NULL
  AND m.familia_hub IN ('racket','team','combat','precision')
  AND EXISTS (SELECT 1 FROM registros_atividade ra WHERE ra.idmodelo = mm.idmodelo);

UPDATE modelos_modalidade mm
SET padrao = FALSE, ativo = FALSE
FROM tmp_sport_details_model_upgrade t
WHERE mm.idmodelo = t.idmodelo_antigo;

INSERT INTO modelos_modalidade (
    idmodelo, idmodalidade, idusuario, idmodelo_anterior, nome, slug, descricao,
    tipo_unidade_padrao, rotulo_unidade, permite_multiplas_unidades, versao,
    padrao, ativo, visibilidade, status_publicacao
)
SELECT
    t.idmodelo_novo,
    antigo.idmodalidade,
    antigo.idusuario,
    antigo.idmodelo,
    antigo.nome,
    antigo.slug,
    antigo.descricao,
    antigo.tipo_unidade_padrao,
    antigo.rotulo_unidade,
    antigo.permite_multiplas_unidades,
    COALESCE((
        SELECT MAX(v.versao)
        FROM modelos_modalidade v
        WHERE v.idmodalidade = antigo.idmodalidade
          AND lower(v.slug) = lower(antigo.slug)
          AND v.idusuario IS NOT DISTINCT FROM antigo.idusuario
    ), antigo.versao) + 1,
    t.era_padrao,
    TRUE,
    antigo.visibilidade,
    antigo.status_publicacao
FROM tmp_sport_details_model_upgrade t
JOIN modelos_modalidade antigo ON antigo.idmodelo = t.idmodelo_antigo
ON CONFLICT (idmodelo) DO NOTHING;

INSERT INTO campos_modelo (
    idcampo, idmodelo, nome, slug, rotulo, tipo_campo, escopo,
    idgrandeza, idunidade, obrigatorio, ordem, ativo, exibicao_padrao, grupo_ui
)
SELECT
    'dc' || substr(md5(t.idmodelo_novo || ':' || c.idcampo), 1, 19),
    t.idmodelo_novo, c.nome, c.slug, c.rotulo, c.tipo_campo, c.escopo,
    c.idgrandeza, c.idunidade, c.obrigatorio, c.ordem, c.ativo,
    c.exibicao_padrao, c.grupo_ui
FROM tmp_sport_details_model_upgrade t
JOIN campos_modelo c ON c.idmodelo = t.idmodelo_antigo
ON CONFLICT DO NOTHING;

INSERT INTO campos_modelo_opcoes (idopcao, idcampo, rotulo, valor, ordem, ativo)
SELECT
    'do' || substr(md5(novo.idcampo || ':' || o.idopcao), 1, 19),
    novo.idcampo, o.rotulo, o.valor, o.ordem, o.ativo
FROM tmp_sport_details_model_upgrade t
JOIN campos_modelo antigo ON antigo.idmodelo = t.idmodelo_antigo
JOIN campos_modelo_opcoes o ON o.idcampo = antigo.idcampo
JOIN campos_modelo novo
  ON novo.idmodelo = t.idmodelo_novo
 AND lower(novo.slug) = lower(antigo.slug)
ON CONFLICT DO NOTHING;

UPDATE modalidades_usuario mu
SET idmodelo_ativo = t.idmodelo_novo
FROM tmp_sport_details_model_upgrade t
WHERE mu.idmodelo_ativo = t.idmodelo_antigo;

WITH defs(familia, slug, nome, rotulo, tipo_campo, ordem) AS (
    VALUES
        ('racket','tipo-sessao','tipo_sessao','Tipo de sessão','selecao',30),
        ('racket','formato-jogo','formato_jogo','Formato','selecao',31),
        ('racket','resultado','resultado','Resultado','selecao',32),
        ('racket','adversario','adversario','Adversário','texto',33),
        ('racket','placar','placar','Placar','texto',34),
        ('team','tipo-sessao','tipo_sessao','Tipo de sessão','selecao',30),
        ('team','resultado','resultado','Resultado','selecao',31),
        ('team','adversario','adversario','Adversário','texto',32),
        ('team','placar-favor','placar_favor','Placar a favor','inteiro',33),
        ('team','placar-contra','placar_contra','Placar contra','inteiro',34),
        ('team','posicao','posicao','Posição / função','texto',35),
        ('combat','tipo-sessao','tipo_sessao','Tipo de sessão','selecao',30),
        ('combat','rounds','rounds','Rounds','inteiro',31),
        ('combat','resultado','resultado','Resultado','selecao',32),
        ('combat','adversario','adversario','Adversário','texto',33),
        ('precision','pontuacao','pontuacao','Pontuação','decimal',30),
        ('precision','rodadas','rodadas','Rodadas / séries','inteiro',31)
)
INSERT INTO campos_modelo (
    idcampo, idmodelo, nome, slug, rotulo, tipo_campo, escopo,
    idgrandeza, idunidade, obrigatorio, ordem, ativo, exibicao_padrao, grupo_ui
)
SELECT
    'sd' || substr(md5(mm.idmodelo || ':' || d.slug), 1, 19),
    mm.idmodelo,
    d.nome,
    d.slug,
    d.rotulo,
    d.tipo_campo,
    'registro',
    NULL,
    NULL,
    FALSE,
    d.ordem,
    TRUE,
    FALSE,
    'extras'
FROM modelos_modalidade mm
JOIN modalidades m ON m.idmodalidade = mm.idmodalidade
JOIN defs d ON d.familia = m.familia_hub
WHERE mm.ativo = TRUE
  AND mm.padrao = TRUE
  AND m.idusuario IS NULL
  AND NOT EXISTS (
      SELECT 1 FROM campos_modelo c
      WHERE c.idmodelo = mm.idmodelo AND lower(c.slug) = lower(d.slug)
  )
ON CONFLICT DO NOTHING;

WITH opts(familia, campo_slug, rotulo, valor, ordem) AS (
    VALUES
        ('racket','tipo-sessao','Treino','treino',1),
        ('racket','tipo-sessao','Partida','partida',2),
        ('racket','tipo-sessao','Aula','aula',3),
        ('racket','formato-jogo','Simples','simples',1),
        ('racket','formato-jogo','Duplas','duplas',2),
        ('racket','resultado','Vitória','vitoria',1),
        ('racket','resultado','Derrota','derrota',2),
        ('racket','resultado','Empate','empate',3),
        ('racket','resultado','Sem resultado','sem-resultado',4),
        ('team','tipo-sessao','Treino','treino',1),
        ('team','tipo-sessao','Jogo','jogo',2),
        ('team','tipo-sessao','Amistoso','amistoso',3),
        ('team','resultado','Vitória','vitoria',1),
        ('team','resultado','Empate','empate',2),
        ('team','resultado','Derrota','derrota',3),
        ('team','resultado','Sem resultado','sem-resultado',4),
        ('combat','tipo-sessao','Técnica','tecnica',1),
        ('combat','tipo-sessao','Saco / manopla','saco-manopla',2),
        ('combat','tipo-sessao','Sparring','sparring',3),
        ('combat','tipo-sessao','Luta','luta',4),
        ('combat','tipo-sessao','Competição','competicao',5),
        ('combat','resultado','Vitória','vitoria',1),
        ('combat','resultado','Empate','empate',2),
        ('combat','resultado','Derrota','derrota',3),
        ('combat','resultado','Sem resultado','sem-resultado',4)
)
INSERT INTO campos_modelo_opcoes (idopcao, idcampo, rotulo, valor, ordem, ativo)
SELECT
    'so' || substr(md5(c.idcampo || ':' || o.valor), 1, 19),
    c.idcampo,
    o.rotulo,
    o.valor,
    o.ordem,
    TRUE
FROM opts o
JOIN modalidades m ON m.familia_hub = o.familia AND m.idusuario IS NULL
JOIN modelos_modalidade mm ON mm.idmodalidade = m.idmodalidade AND mm.ativo = TRUE AND mm.padrao = TRUE
JOIN campos_modelo c ON c.idmodelo = mm.idmodelo AND lower(c.slug) = lower(o.campo_slug)
WHERE NOT EXISTS (
    SELECT 1 FROM campos_modelo_opcoes x
    WHERE x.idcampo = c.idcampo AND lower(x.valor) = lower(o.valor)
)
ON CONFLICT DO NOTHING;

COMMIT;
