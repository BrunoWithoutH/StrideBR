-- CREATE DATABASE stridebr;

-- \c stridebr;

CREATE TABLE IF NOT EXISTS usuarios (
    idusuario VARCHAR(12) PRIMARY KEY UNIQUE,
    nomeusuario VARCHAR(255) NOT NULL,
    emailusuario VARCHAR(255) NOT NULL,
    senhausuario VARCHAR(255) NOT NULL,
    foneusuario VARCHAR(20),
    datanascimentousuario DATE DEFAULT NULL,
    dataregistrousuario TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    nivelusuario INT DEFAULT 0,
    statususuario VARCHAR(10) CHECK (statususuario IN ('Ativo', 'Desativado')) DEFAULT 'Ativo'
);

CREATE TABLE IF NOT EXISTS atividades (
    idatividade VARCHAR(16) PRIMARY KEY UNIQUE,
    idusuario VARCHAR(12) NOT NULL,
    tituloatividade VARCHAR(255),
    esporteatividade VARCHAR(255) NOT NULL,
    ritmoatividade VARCHAR(10) CHECK (ritmoatividade IN ('Leve', 'Moderado', 'Intenso')),
    dataatividade DATE NOT NULL,
    horaatividade TIME NOT NULL,
    datahoraregistroatividade TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    duracaoatividade NUMERIC DEFAULT NULL,
    distanciaatividade DECIMAL(5,2) DEFAULT NULL,
	unidadeaistanciaatividade VARCHAR(20),
    elevacaoatividade DECIMAL(5,2) DEFAULT NULL,
    velocidademediaatividade DECIMAL(5,2) DEFAULT NULL,
    pesoinseridoatividade DECIMAL(5,2) DEFAULT NULL,
    indicegastocaloricoatividade DECIMAL(5,2) DEFAULT NULL,
    observacaoatividade TEXT,
    caloriasatividade INT DEFAULT NULL,
    
    FOREIGN KEY (IdUsuario) REFERENCES usuarios(IdUsuario) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS cronogramas (
    idcronograma VARCHAR(12) PRIMARY KEY,
    idusuario VARCHAR(12) NOT NULL,
    diasemanacronograma VARCHAR(10) CHECK (diasemanacronograma IN ('Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado')),
    turnocronograma VARCHAR(10) CHECK (turnocronograma IN ('Manhã', 'Tarde', 'Noite')),
    titulotreinocronograma VARCHAR(100) NOT NULL,
    observacoescronograma TEXT,
    datahoraregistrocronograma TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (idusuario) REFERENCES usuarios(idusuario) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS exercicios_cronograma (
    idexercicio SERIAL PRIMARY KEY,
    idcronograma VARCHAR(12) NOT NULL,
    nomeexercicio VARCHAR(100) NOT NULL,
    seriesexercicio INT,
    repeticoesexercicio VARCHAR(20),
    descansoexercicio VARCHAR(20),
    blocoexercicio VARCHAR(20),
    clusterexercicio VARCHAR(20),
    cargaexercicio VARCHAR (20),
    observacoesexercicio TEXT,
    linhasexercicio INT DEFAULT 1,
	ordemexercicio INT NOT NULL,
    FOREIGN KEY (idcronograma) REFERENCES cronogramas(idcronograma) ON DELETE CASCADE
);