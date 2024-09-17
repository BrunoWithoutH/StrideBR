CREATE TABLE `usuarios` (
  `ID` int(11) NOT NULL,
  `Nome` varchar(255) NOT NULL,
  `Sobrenome` varchar(255) NOT NULL,
  `Email` varchar(255) NOT NULL,
  `Senha` varchar(16) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE `atividades_fisicas`(
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `tipo_atividade` varchar(255) NOT NULL,
  `data_atividade` date NOT NULL COMMENT 'yyy-mm-',
  `hora_atividade` time DEFAULT NULL,
  `duracao` int(11) DEFAULT NULL,
  `distancia` decimal(5,2) DEFAULT NULL,
  `calorias` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE `cronograma_treinos` (
  `id_cronograma` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `dia_semana` enum('Domingo','Segunda','Terça','Quarta','Quinta','Sexta','Sábado') NOT NULL,
  `periodo` enum('Manhã','Tarde','Noite') NOT NULL,
  `treino` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

ALTER TABLE `atividades_fisicas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`);

ALTER TABLE `cronograma_treinos`
  ADD PRIMARY KEY (`id_cronograma`),
  ADD UNIQUE KEY `usuario_id` (`usuario_id`,`dia_semana`,`periodo`),
  ADD UNIQUE KEY `usuario_id_2` (`usuario_id`,`dia_semana`,`periodo`),
  ADD UNIQUE KEY `usuario_id_3` (`usuario_id`,`dia_semana`,`periodo`);

ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`ID`);

ALTER TABLE `atividades_fisicas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

ALTER TABLE `cronograma_treinos`
  MODIFY `id_cronograma` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

ALTER TABLE `usuarios`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

ALTER TABLE `atividades_fisicas`
  ADD CONSTRAINT `atividades_fisicas_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`ID`) ON DELETE CASCADE;

ALTER TABLE `cronograma_treinos`
  ADD CONSTRAINT `cronograma_treinos_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`ID`) ON DELETE CASCADE;
COMMIT;
