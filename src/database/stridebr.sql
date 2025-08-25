-- CREATE DATABASE stridebr;

-- \c stridebr;

CREATE TABLE IF NOT EXISTS usuarios (
    idusuario SERIAL PRIMARY KEY,
    nomeusuario VARCHAR(255) NOT NULL,
    emailusuario VARCHAR(255) NOT NULL,
    senhausuario VARCHAR(255) NOT NULL,
    foneusuario VARCHAR(20),
    datanascimentousuario DATE NOT NULL,
    dataregistrousuario TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    nivelusuario INT DEFAULT 0,
    statususuario VARCHAR(10) CHECK (statususuario IN ('Ativo', 'Desativado')) DEFAULT 'Ativo'
);

CREATE TABLE IF NOT EXISTS atividades (
    idatividade SERIAL PRIMARY KEY,
    idusuario INT NOT NULL,
    tituloatividade VARCHAR(255),
    esporteatividade VARCHAR(255) NOT NULL,
    ritmoatividade VARCHAR(10) CHECK (ritmoatividade IN ('Leve', 'Moderado', 'Intenso')),
    dataatividade DATE NOT NULL,
    horaatividade TIME NOT NULL,
    datahoraregistroatividade TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    duracaoatividade INT DEFAULT NULL,
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
    idcronograma SERIAL PRIMARY KEY,
    idusuario INT NOT NULL,
    diasemanacronograma VARCHAR(10) CHECK (diasemanacronograma IN ('Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado')),
    turnocronograma VARCHAR(10) CHECK (turnocronograma IN ('Manhã', 'Tarde', 'Noite')),
    textocronograma TEXT NOT NULL,
    datahoraregistrocronograma TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (IdUsuario) REFERENCES usuarios(IdUsuario) ON DELETE CASCADE
);
