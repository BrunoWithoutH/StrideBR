-- CREATE DATABASE stridebr;

-- \c stridebr;

CREATE TABLE IF NOT EXISTS usuarios (
    IdUsuario SERIAL PRIMARY KEY,
    NomeUsuario VARCHAR(255) NOT NULL,
    EmailUsuario VARCHAR(255) NOT NULL UNIQUE,
    SenhaUsuario VARCHAR(255) NOT NULL,
    FoneUsuario VARCHAR(20),
    NivelUsuario INT
);

CREATE TABLE IF NOT EXISTS atividades (
    IdAtividade SERIAL PRIMARY KEY,
    IdUsuario INT NOT NULL,
    TituloAtividade VARCHAR(255),
    EsporteAtividade VARCHAR(255) NOT NULL,
    RitmoAtividade VARCHAR(10) CHECK (RitmoAtividade IN ('Leve', 'Moderado', 'Intenso')),
    DataHoraAtividade TIMESTAMP NOT NULL,
    DuracaoAtividade INT DEFAULT NULL,
    DistanciaAtividade DECIMAL(5,2) DEFAULT NULL,
    PesoInseridoAtividade DECIMAL(5,2) DEFAULT NULL,
    CaloriasAtividade INT DEFAULT NULL,
    
    FOREIGN KEY (IdUsuario) REFERENCES usuarios(IdUsuario) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS cronogramas (
    IdCronograma SERIAL PRIMARY KEY,
    IdUsuario INT NOT NULL,
    DiaSemanaCronograma VARCHAR(10) CHECK (DiaSemanaCronograma IN ('Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado')),
    TurnoCronograma VARCHAR(10) CHECK (TurnoCronograma IN ('Manhã', 'Tarde', 'Noite')),
    TextoCronograma TEXT NOT NULL,

    FOREIGN KEY (IdUsuario) REFERENCES usuarios(IdUsuario) ON DELETE CASCADE
);

