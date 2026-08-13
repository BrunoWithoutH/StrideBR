BEGIN;

CREATE SCHEMA IF NOT EXISTS stridebr;
SET search_path TO stridebr, public;

CREATE TABLE usuarios (
    idusuario VARCHAR(21) PRIMARY KEY,
    nomeusuario VARCHAR(255) NOT NULL CHECK (length(trim(nomeusuario)) > 0),
    emailusuario VARCHAR(255) NOT NULL,
    senhausuario VARCHAR(255) NOT NULL,
    foneusuario VARCHAR(20),
    datanascimentousuario DATE,
    dataregistrousuario TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    nivelusuario INTEGER NOT NULL DEFAULT 0,
    statususuario VARCHAR(10) NOT NULL DEFAULT 'Ativo' CHECK (statususuario IN ('Ativo', 'Desativado')),
    fotousuario VARCHAR(255),
    generousuario VARCHAR(20) CHECK (generousuario IN ('Masculino', 'Feminino', 'Não-binário', 'Agênero', 'Bigênero', 'Gênero fluido', 'Prefiro não informar', 'Outro')),
    pronomesusuario VARCHAR(30),
    biousuario TEXT,
    pesousuario NUMERIC(6,2),
    alturausuario INTEGER CHECK (alturausuario IS NULL OR alturausuario > 0),
    objetivousuario TEXT,
    visibilidadeperfil VARCHAR(10) NOT NULL DEFAULT 'privado' CHECK (visibilidadeperfil IN ('privado', 'amigos', 'publico')),
    verificado BOOLEAN NOT NULL DEFAULT FALSE,
    ultimologin TIMESTAMPTZ,
    ipregistro VARCHAR(45),
    ipultimologin VARCHAR(45),
    notificacoesconfig JSONB NOT NULL DEFAULT '{}'::jsonb
);

CREATE UNIQUE INDEX ux_usuarios_email ON usuarios (lower(emailusuario));

CREATE TABLE cronogramas (
    idcronograma VARCHAR(21) PRIMARY KEY,
    idusuario VARCHAR(21) NOT NULL REFERENCES usuarios(idusuario) ON DELETE CASCADE,
    nome VARCHAR(120) NOT NULL CHECK (length(trim(nome)) > 0),
    descricao TEXT,
    cor VARCHAR(20),
    ativo BOOLEAN NOT NULL DEFAULT TRUE,
    visibilidade VARCHAR(10) NOT NULL DEFAULT 'privado' CHECK (visibilidade IN ('privado', 'amigos', 'publico')),
    status_publicacao VARCHAR(20) NOT NULL DEFAULT 'privado' CHECK (status_publicacao IN ('privado', 'pendente', 'publicado')),
    data_criacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    data_atualizacao TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE UNIQUE INDEX ux_cronogramas_usuario_nome ON cronogramas (idusuario, lower(nome));
CREATE INDEX ix_cronogramas_usuario_ativo ON cronogramas (idusuario, ativo, data_atualizacao DESC);

CREATE TABLE treinos_cronograma (
    idtreino VARCHAR(21) PRIMARY KEY,
    idcronograma VARCHAR(21) NOT NULL REFERENCES cronogramas(idcronograma) ON DELETE CASCADE,
    titulo VARCHAR(120) NOT NULL CHECK (length(trim(titulo)) > 0),
    descricao TEXT,
    dia_semana SMALLINT NOT NULL CHECK (dia_semana BETWEEN 0 AND 6),
    hora_inicio TIME NOT NULL,
    hora_fim TIME NOT NULL,
    termina_dia_seguinte BOOLEAN NOT NULL DEFAULT FALSE,
    cor VARCHAR(20),
    ordem INTEGER NOT NULL DEFAULT 1 CHECK (ordem > 0),
    data_criacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    data_atualizacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT ck_treinos_horario CHECK (
        (NOT termina_dia_seguinte AND hora_fim > hora_inicio)
        OR
        (termina_dia_seguinte AND hora_fim <= hora_inicio)
    )
);

CREATE INDEX ix_treinos_cronograma_semana ON treinos_cronograma (idcronograma, dia_semana, hora_inicio, ordem);

CREATE TABLE categorias_exercicio (
    idcategoria VARCHAR(21) PRIMARY KEY,
    idusuario VARCHAR(21) REFERENCES usuarios(idusuario) ON DELETE CASCADE,
    nome VARCHAR(80) NOT NULL CHECK (length(trim(nome)) > 0),
    slug VARCHAR(100) NOT NULL CHECK (length(trim(slug)) > 0),
    ativo BOOLEAN NOT NULL DEFAULT TRUE,
    data_criacao TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE UNIQUE INDEX ux_categoria_sistema_slug ON categorias_exercicio (lower(slug)) WHERE idusuario IS NULL;
CREATE UNIQUE INDEX ux_categoria_usuario_slug ON categorias_exercicio (idusuario, lower(slug)) WHERE idusuario IS NOT NULL;

CREATE TABLE exercicios (
    idexercicio VARCHAR(21) PRIMARY KEY,
    idusuario VARCHAR(21) REFERENCES usuarios(idusuario) ON DELETE CASCADE,
    nome VARCHAR(120) NOT NULL CHECK (length(trim(nome)) > 0),
    slug VARCHAR(140) NOT NULL CHECK (length(trim(slug)) > 0),
    descricao TEXT,
    ativo BOOLEAN NOT NULL DEFAULT TRUE,
    visibilidade VARCHAR(10) NOT NULL DEFAULT 'privado' CHECK (visibilidade IN ('privado', 'amigos', 'publico')),
    status_publicacao VARCHAR(20) NOT NULL DEFAULT 'privado' CHECK (status_publicacao IN ('privado', 'pendente', 'publicado')),
    data_criacao TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    data_atualizacao TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE UNIQUE INDEX ux_exercicio_sistema_slug ON exercicios (lower(slug)) WHERE idusuario IS NULL;
CREATE UNIQUE INDEX ux_exercicio_usuario_slug ON exercicios (idusuario, lower(slug)) WHERE idusuario IS NOT NULL;
CREATE INDEX ix_exercicios_usuario_ativo ON exercicios (idusuario, ativo, nome);

CREATE TABLE exercicios_categorias (
    idexercicio VARCHAR(21) NOT NULL REFERENCES exercicios(idexercicio) ON DELETE CASCADE,
    idcategoria VARCHAR(21) NOT NULL REFERENCES categorias_exercicio(idcategoria) ON DELETE CASCADE,
    PRIMARY KEY (idexercicio, idcategoria)
);

CREATE TABLE treinos_exercicios (
    idtreino_exercicio VARCHAR(21) PRIMARY KEY,
    idtreino VARCHAR(21) NOT NULL REFERENCES treinos_cronograma(idtreino) ON DELETE CASCADE,
    idexercicio VARCHAR(21) REFERENCES exercicios(idexercicio) ON DELETE SET NULL,
    nome_snapshot VARCHAR(120) NOT NULL CHECK (length(trim(nome_snapshot)) > 0),
    series INTEGER CHECK (series IS NULL OR series > 0),
    repeticoes VARCHAR(40),
    carga VARCHAR(40),
    bloco VARCHAR(40),
    cluster VARCHAR(80),
    descanso VARCHAR(40),
    observacoes TEXT,
    ordem INTEGER NOT NULL CHECK (ordem > 0),
    data_criacao TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE UNIQUE INDEX ux_treinos_exercicios_ordem ON treinos_exercicios (idtreino, ordem);
CREATE INDEX ix_treinos_exercicios_exercicio ON treinos_exercicios (idexercicio);

CREATE TABLE campos_treino_exercicio (
    idcampo VARCHAR(21) PRIMARY KEY,
    idtreino VARCHAR(21) NOT NULL REFERENCES treinos_cronograma(idtreino) ON DELETE CASCADE,
    nome VARCHAR(80) NOT NULL CHECK (length(trim(nome)) > 0),
    slug VARCHAR(100) NOT NULL CHECK (length(trim(slug)) > 0),
    tipo VARCHAR(15) NOT NULL CHECK (tipo IN ('texto', 'inteiro', 'decimal', 'booleano')),
    ordem INTEGER NOT NULL CHECK (ordem > 0),
    ativo BOOLEAN NOT NULL DEFAULT TRUE,
    data_criacao TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE UNIQUE INDEX ux_campos_treino_slug ON campos_treino_exercicio (idtreino, lower(slug));
CREATE UNIQUE INDEX ux_campos_treino_ordem ON campos_treino_exercicio (idtreino, ordem);

CREATE TABLE valores_treino_exercicio (
    idvalor VARCHAR(21) PRIMARY KEY,
    idtreino_exercicio VARCHAR(21) NOT NULL REFERENCES treinos_exercicios(idtreino_exercicio) ON DELETE CASCADE,
    idcampo VARCHAR(21) NOT NULL REFERENCES campos_treino_exercicio(idcampo) ON DELETE RESTRICT,
    valor_texto TEXT,
    valor_inteiro BIGINT,
    valor_decimal NUMERIC(30,10),
    valor_booleano BOOLEAN,
    CONSTRAINT ck_valor_treino_tipo CHECK (num_nonnulls(valor_texto, valor_inteiro, valor_decimal, valor_booleano) = 1),
    UNIQUE (idtreino_exercicio, idcampo)
);

COMMIT;
