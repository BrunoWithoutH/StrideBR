CREATE DATABASE if not exists stridebr;

USE stridebr;

CREATE TABLE if not exists usuarios (
  IdUsuario INT(11) NOT NULL AUTO_INCREMENT UNIQUE,
  NomeUsuario varchar(255) NOT NULL,
  SobrenomeUsuario varchar(255) NOT NULL,
  EmailUsuario varchar(255) NOT NULL,
  SenhaUsuario varchar(16) NOT NULL,

  PRIMARY KEY (IdUsuario)
)

CREATE TABLE if not exists atividades_fisicas(
  IdAtividade INT(11) NOT NULL AUTO_INCREMENT UNIQUE,
  IdUsuario INT(11) NOT NULL,
  TipoAtividade varchar(255) NOT NULL,
  DataAtividade date NOT NULL COMMENT 'dd-mm-aaaa',
  HoraAtividade time DEFAULT NULL,
  DuracaoAtividade INT(11) DEFAULT NULL,
  DistanciaAtividade decimal(5,2) DEFAULT NULL,
  CaloriasAtividade INT(11) DEFAULT NULL,

  PRIMARY KEY (IdAtividade),

  FOREIGN KEY (IdUsuario) REFERENCES usuarios(IdUsuario)
)

CREATE TABLE if not exists cronograma_treinos (
  IdCronograma INT(11) NOT NULL AUTO_INCREMENT UNIQUE,
  IdUsuario INT(11) NOT NULL,
  DiaSemanaCronograma enum('Domingo','Segunda','Terça','Quarta','Q INTa','Sexta','Sábado') NOT NULL,
  TurnoCronograma enum('Manhã','Tarde','Noite') NOT NULL,
  TextoCronograma text NOT NULL,

  PRIMARY KEY (IdCronograma),

  FOREIGN KEY (IdUsuario) REFERENCES usuarios(IdUsuario)
)