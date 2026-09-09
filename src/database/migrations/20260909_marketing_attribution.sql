BEGIN;
SET search_path TO stridebr, public;

CREATE TABLE IF NOT EXISTS marketing_campanhas (
    idcampanha VARCHAR(21) PRIMARY KEY,
    nome VARCHAR(140) NOT NULL,
    codigo VARCHAR(80) NOT NULL UNIQUE,
    tipo VARCHAR(24) NOT NULL,
    descricao TEXT,
    inicio DATE,
    fim DATE,
    status VARCHAR(16) NOT NULL DEFAULT 'planejada',
    criado_por VARCHAR(21) REFERENCES usuarios(idusuario) ON DELETE SET NULL,
    data_criacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    data_atualizacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT ck_marketing_campanha_tipo CHECK (tipo IN ('offline','paid_social','organic_social','event','other')),
    CONSTRAINT ck_marketing_campanha_status CHECK (status IN ('planejada','ativa','encerrada')),
    CONSTRAINT ck_marketing_campanha_periodo CHECK (fim IS NULL OR inicio IS NULL OR fim >= inicio)
);

CREATE TABLE IF NOT EXISTS marketing_placements (
    idplacement VARCHAR(21) PRIMARY KEY,
    idcampanha VARCHAR(21) NOT NULL REFERENCES marketing_campanhas(idcampanha) ON DELETE CASCADE,
    nome VARCHAR(160) NOT NULL,
    codigo VARCHAR(80) NOT NULL UNIQUE,
    subtipo VARCHAR(60),
    destino VARCHAR(500) NOT NULL DEFAULT '/',
    ativo BOOLEAN NOT NULL DEFAULT TRUE,
    observacao TEXT,
    data_criacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    data_atualizacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT ck_marketing_placement_destino CHECK (destino ~ '^/[^\\r\\n]*$' AND destino !~ '^//')
);

CREATE INDEX IF NOT EXISTS ix_marketing_placements_campanha
ON marketing_placements (idcampanha, ativo, codigo);

CREATE TABLE IF NOT EXISTS marketing_atribuicoes (
    chave_hash CHAR(64) PRIMARY KEY,
    idcampanha VARCHAR(21) REFERENCES marketing_campanhas(idcampanha) ON DELETE SET NULL,
    idplacement VARCHAR(21) REFERENCES marketing_placements(idplacement) ON DELETE SET NULL,
    idusuario VARCHAR(21) REFERENCES usuarios(idusuario) ON DELETE SET NULL,
    utm_source VARCHAR(80),
    utm_medium VARCHAR(80),
    utm_campaign VARCHAR(80),
    utm_content VARCHAR(120),
    utm_term VARCHAR(120),
    data_criacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    vinculada_em TIMESTAMPTZ
);

CREATE UNIQUE INDEX IF NOT EXISTS ux_marketing_atribuicoes_usuario
ON marketing_atribuicoes (idusuario)
WHERE idusuario IS NOT NULL;

CREATE INDEX IF NOT EXISTS ix_marketing_atribuicoes_campanha
ON marketing_atribuicoes (idcampanha, data_criacao DESC);

CREATE INDEX IF NOT EXISTS ix_marketing_atribuicoes_placement
ON marketing_atribuicoes (idplacement, data_criacao DESC);

CREATE TABLE IF NOT EXISTS marketing_eventos_aquisicao (
    idevento BIGSERIAL PRIMARY KEY,
    chave_evento CHAR(64) NOT NULL UNIQUE,
    chave_atribuicao CHAR(64) REFERENCES marketing_atribuicoes(chave_hash) ON DELETE SET NULL,
    idusuario VARCHAR(21) REFERENCES usuarios(idusuario) ON DELETE SET NULL,
    nome VARCHAR(40) NOT NULL,
    caminho VARCHAR(300),
    data_criacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT ck_marketing_evento_nome CHECK (nome IN ('landing_view','signup_start','signup_complete','activation'))
);

CREATE INDEX IF NOT EXISTS ix_marketing_eventos_nome_data
ON marketing_eventos_aquisicao (nome, data_criacao DESC);

CREATE INDEX IF NOT EXISTS ix_marketing_eventos_atribuicao_data
ON marketing_eventos_aquisicao (chave_atribuicao, data_criacao DESC)
WHERE chave_atribuicao IS NOT NULL;

CREATE INDEX IF NOT EXISTS ix_marketing_eventos_usuario_data
ON marketing_eventos_aquisicao (idusuario, data_criacao DESC)
WHERE idusuario IS NOT NULL;

INSERT INTO marketing_campanhas (idcampanha, nome, codigo, tipo, descricao, status)
VALUES (
    'GId4uMVzDDYfIWT7RjeEW',
    'Frederico Westphalen — Lançamento local 2026',
    'fw_local_2026',
    'offline',
    'Campanha local inicial para cartazes e QR Codes em Frederico Westphalen.',
    'planejada'
)
ON CONFLICT (codigo) DO NOTHING;

INSERT INTO marketing_placements (idplacement, idcampanha, nome, codigo, subtipo, destino, ativo) VALUES
('FmZD54DICw4GBxX5aqmi8','GId4uMVzDDYfIWT7RjeEW','IF — Ginásio — principal','fw_if_ginasio','offline_poster','/',TRUE),
('sXQYi56FM_TkJh94GdPLl','GId4uMVzDDYfIWT7RjeEW','IF — Prédio Central','fw_if_central','offline_poster','/',TRUE),
('As766S528odM9x2PAg5vC','GId4uMVzDDYfIWT7RjeEW','IF — TI','fw_if_ti','offline_poster','/',TRUE),
('ZkurmArB-ExZmDyRL60pR','GId4uMVzDDYfIWT7RjeEW','IF — ADM / RU','fw_if_adm_ru','offline_poster','/',TRUE),
('XJ5MwQ2vVkUAPrpz0O-9s','GId4uMVzDDYfIWT7RjeEW','SESC','fw_sesc','offline_poster','/',TRUE),
('unySAlWCGTGl9SWfoLZjl','GId4uMVzDDYfIWT7RjeEW','SESC — Entrada','fw_sesc_entrada','offline_poster','/',TRUE),
('JGzGr17GX-mnQHdYPi0AD','GId4uMVzDDYfIWT7RjeEW','SESC — Saída','fw_sesc_saida','offline_poster','/',TRUE),
('fSifdFKFVw0xdhUDHzNPp','GId4uMVzDDYfIWT7RjeEW','Vitória Bike','fw_vitoria_bike','offline_poster','/',TRUE),
('ckp0XUJKIdZ9ExgmWPDq7','GId4uMVzDDYfIWT7RjeEW','Matéria Prima','fw_materia_prima','offline_poster','/',TRUE),
('nY3iUpUmN6okFAjVJvUlF','GId4uMVzDDYfIWT7RjeEW','Rede Mestre','fw_rede_mestre','offline_poster','/',TRUE),
('zDIn6CzGKJpj0f3wxL2Xy','GId4uMVzDDYfIWT7RjeEW','Garra','fw_garra','offline_poster','/',TRUE),
('yPf9OcBhveKsCincZI2TG','GId4uMVzDDYfIWT7RjeEW','CT','fw_ct','offline_poster','/',TRUE),
('yhvAz5SuZckN2eGZPh8jk','GId4uMVzDDYfIWT7RjeEW','Vital','fw_vital','offline_poster','/',TRUE),
('ntsg4U_TfNgV8GDGUtpab','GId4uMVzDDYfIWT7RjeEW','Império Fight','fw_imperio_fight','offline_poster','/',TRUE),
('9wvB3bMDxMvR-iIJ12xK8','GId4uMVzDDYfIWT7RjeEW','Raja Sul','fw_raja_sul','offline_poster','/',TRUE)
ON CONFLICT (codigo) DO NOTHING;

COMMIT;
