CREATE DATABASE IF NOT EXISTS stridebr DEFAULT CHARACTER SET utf8;

USE stridebr;

CREATE TABLE IF NOT EXISTS usuarios (
  IdUsuario INT(11) NOT NULL AUTO_INCREMENT UNIQUE,
  NomeUsuario VARCHAR(255) NOT NULL,
  SobrenomeUsuario VARCHAR(255) NOT NULL,
  EmailUsuario VARCHAR(255) NOT NULL,
  SenhaUsuario VARCHAR(16) NOT NULL,
  DataNascimentoUsuario DATE NOT NULL,

  PRIMARY KEY (IdUsuario)
);

CREATE TABLE IF NOT EXISTS atividades_fisicas (
  IdAtividade INT(11) NOT NULL AUTO_INCREMENT UNIQUE,
  IdUsuario INT(11) NOT NULL,
  TituloAtividade VARCHAR(255),
  EsporteAtividade VARCHAR(255) NOT NULL,
  RitmoAtividade ENUM('Leve', 'Moderado', 'Intenso') NOT NULL,
  DataHoraAtividade DATETIME NOT NULL,
  DuracaoAtividade INT(11) DEFAULT NULL,
  DistanciaAtividade DECIMAL(5,2) DEFAULT NULL,
  PesoInseridoAtividade DECIMAL(5,2) DEFAULT NULL,
  CaloriasAtividade INT(11) DEFAULT NULL,

  PRIMARY KEY (IdAtividade),

  FOREIGN KEY (IdUsuario) REFERENCES usuarios(IdUsuario)
);

CREATE TABLE IF NOT EXISTS cronograma_treinos (
  IdCronograma INT(11) NOT NULL AUTO_INCREMENT UNIQUE,
  IdUsuario INT(11) NOT NULL,
  DiaSemanaCronograma ENUM('Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado') NOT NULL,
  TurnoCronograma ENUM('Manhã', 'Tarde', 'Noite') NOT NULL,
  TextoCronograma TEXT NOT NULL,

  PRIMARY KEY (IdCronograma),

  FOREIGN KEY (IdUsuario) REFERENCES usuarios(IdUsuario)
);