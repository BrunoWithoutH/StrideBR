BEGIN;
SET search_path TO stridebr, public;

INSERT INTO grandezas (idgrandeza, nome, slug) VALUES
('g_distancia', 'Distância', 'distancia'),
('g_tempo', 'Tempo', 'tempo'),
('g_massa', 'Massa', 'massa'),
('g_energia', 'Energia', 'energia')
ON CONFLICT DO NOTHING;

INSERT INTO unidades (idunidade, idgrandeza, nome, simbolo, fator_para_base, ajuste_para_base, eh_base) VALUES
('u_m', 'g_distancia', 'Metro', 'm', 1, 0, TRUE),
('u_km', 'g_distancia', 'Quilômetro', 'km', 1000, 0, FALSE),
('u_s', 'g_tempo', 'Segundo', 's', 1, 0, TRUE),
('u_min', 'g_tempo', 'Minuto', 'min', 60, 0, FALSE),
('u_h', 'g_tempo', 'Hora', 'h', 3600, 0, FALSE),
('u_kg', 'g_massa', 'Quilograma', 'kg', 1, 0, TRUE),
('u_kcal', 'g_energia', 'Quilocaloria', 'kcal', 1, 0, TRUE)
ON CONFLICT DO NOTHING;

INSERT INTO modalidades (idmodalidade, nome, slug, descricao, visibilidade, status_publicacao) VALUES
('m_corrida', 'Corrida', 'corrida', 'Corrida em qualquer distância ou terreno.', 'publico', 'publicado'),
('m_caminhada', 'Caminhada', 'caminhada', 'Caminhada recreativa, esportiva ou de treino.', 'publico', 'publicado'),
('m_marcha', 'Marcha Atlética', 'marcha-atletica', 'Treinos e provas de marcha atlética.', 'publico', 'publicado'),
('m_trilha', 'Trilha', 'trilha', 'Atividades realizadas em trilhas e terrenos naturais.', 'publico', 'publicado'),
('m_ciclismo', 'Ciclismo', 'ciclismo', 'Ciclismo em estrada, urbano ou indoor.', 'publico', 'publicado'),
('m_mtb', 'Mountain Bike', 'mountain-bike', 'Mountain bike e percursos off-road.', 'publico', 'publicado'),
('m_downhill', 'Downhill', 'downhill', 'Descidas e treinos de downhill.', 'publico', 'publicado'),
('m_bmx', 'BMX', 'bmx', 'Treinos e sessões de BMX.', 'publico', 'publicado'),
('m_natacao', 'Natação', 'natacao', 'Treinos de natação com diferentes estilos.', 'publico', 'publicado'),
('m_tenis', 'Tênis', 'tenis', 'Treinos e partidas de tênis.', 'publico', 'publicado'),
('m_tenismesa', 'Tênis de mesa', 'tenis-de-mesa', 'Treinos e partidas de tênis de mesa.', 'publico', 'publicado'),
('m_badminton', 'Badminton', 'badminton', 'Treinos e partidas de badminton.', 'publico', 'publicado'),
('m_padel', 'Padel', 'padel', 'Treinos e partidas de padel.', 'publico', 'publicado'),
('m_beachtennis', 'Beach Tennis', 'beach-tennis', 'Treinos e partidas de beach tennis.', 'publico', 'publicado'),
('m_peso', 'Arremesso de peso', 'arremesso-de-peso', 'Treinos e competições de arremesso de peso.', 'publico', 'publicado'),
('m_disco', 'Lançamento de disco', 'lancamento-de-disco', 'Treinos e competições de lançamento de disco.', 'publico', 'publicado'),
('m_dardo', 'Lançamento de dardo', 'lancamento-de-dardo', 'Treinos e competições de lançamento de dardo.', 'publico', 'publicado'),
('m_martelo', 'Lançamento de martelo', 'lancamento-de-martelo', 'Treinos e competições de lançamento de martelo.', 'publico', 'publicado'),
('m_musculacao', 'Musculação', 'musculacao', 'Sessões de musculação e treinamento resistido.', 'publico', 'publicado'),
('m_calistenia', 'Calistenia', 'calistenia', 'Treinos com peso corporal e habilidades.', 'publico', 'publicado'),
('m_karate', 'Karatê', 'karate', 'Treinos de karatê, kata e prática técnica.', 'publico', 'publicado'),
('m_geral', 'Outra atividade', 'outra-atividade', 'Modelo genérico para outras atividades físicas.', 'publico', 'publicado')
ON CONFLICT DO NOTHING;

INSERT INTO modelos_modalidade (idmodelo, idmodalidade, nome, slug, descricao, tipo_unidade_padrao, rotulo_unidade, permite_multiplas_unidades, versao, padrao, visibilidade, status_publicacao) VALUES
('md_corrida', 'm_corrida', 'Corrida básica', 'basico', 'Registro rápido de corrida.', 'trecho', 'Trecho', TRUE, 1, TRUE, 'publico', 'publicado'),
('md_caminhada', 'm_caminhada', 'Caminhada básica', 'basico', 'Registro rápido de caminhada.', 'trecho', 'Trecho', TRUE, 1, TRUE, 'publico', 'publicado'),
('md_marcha', 'm_marcha', 'Marcha básica', 'basico', 'Registro rápido de marcha atlética.', 'trecho', 'Trecho', TRUE, 1, TRUE, 'publico', 'publicado'),
('md_trilha', 'm_trilha', 'Trilha básica', 'basico', 'Registro rápido de trilha.', 'trecho', 'Trecho', TRUE, 1, TRUE, 'publico', 'publicado'),
('md_ciclismo', 'm_ciclismo', 'Ciclismo básico', 'basico', 'Registro rápido de ciclismo.', 'trecho', 'Trecho', TRUE, 1, TRUE, 'publico', 'publicado'),
('md_mtb', 'm_mtb', 'Mountain Bike básico', 'basico', 'Registro rápido de mountain bike.', 'trecho', 'Trecho', TRUE, 1, TRUE, 'publico', 'publicado'),
('md_downhill', 'm_downhill', 'Downhill básico', 'basico', 'Registro rápido de downhill.', 'descida', 'Descida', TRUE, 1, TRUE, 'publico', 'publicado'),
('md_bmx', 'm_bmx', 'BMX básico', 'basico', 'Registro rápido de BMX.', 'serie', 'Série', TRUE, 1, TRUE, 'publico', 'publicado'),
('md_natacao', 'm_natacao', 'Natação básica', 'basico', 'Registro rápido de natação.', 'serie', 'Série', TRUE, 1, TRUE, 'publico', 'publicado'),
('md_tenis', 'm_tenis', 'Tênis básico', 'basico', 'Registro rápido de tênis.', 'sessao', 'Sessão', FALSE, 1, TRUE, 'publico', 'publicado'),
('md_tenismesa', 'm_tenismesa', 'Tênis de mesa básico', 'basico', 'Registro rápido de tênis de mesa.', 'sessao', 'Sessão', FALSE, 1, TRUE, 'publico', 'publicado'),
('md_badminton', 'm_badminton', 'Badminton básico', 'basico', 'Registro rápido de badminton.', 'sessao', 'Sessão', FALSE, 1, TRUE, 'publico', 'publicado'),
('md_padel', 'm_padel', 'Padel básico', 'basico', 'Registro rápido de padel.', 'sessao', 'Sessão', FALSE, 1, TRUE, 'publico', 'publicado'),
('md_beachtennis', 'm_beachtennis', 'Beach Tennis básico', 'basico', 'Registro rápido de beach tennis.', 'sessao', 'Sessão', FALSE, 1, TRUE, 'publico', 'publicado'),
('md_peso', 'm_peso', 'Arremesso de peso', 'basico', 'Registro de múltiplas tentativas.', 'tentativa', 'Tentativa', TRUE, 1, TRUE, 'publico', 'publicado'),
('md_disco', 'm_disco', 'Lançamento de disco', 'basico', 'Registro de múltiplas tentativas.', 'tentativa', 'Tentativa', TRUE, 1, TRUE, 'publico', 'publicado'),
('md_dardo', 'm_dardo', 'Lançamento de dardo', 'basico', 'Registro de múltiplas tentativas.', 'tentativa', 'Tentativa', TRUE, 1, TRUE, 'publico', 'publicado'),
('md_martelo', 'm_martelo', 'Lançamento de martelo', 'basico', 'Registro de múltiplas tentativas.', 'tentativa', 'Tentativa', TRUE, 1, TRUE, 'publico', 'publicado'),
('md_musculacao', 'm_musculacao', 'Sessão de musculação', 'basico', 'Registro geral de uma sessão de musculação.', 'sessao', 'Sessão', FALSE, 1, TRUE, 'publico', 'publicado'),
('md_calistenia', 'm_calistenia', 'Sessão de calistenia', 'basico', 'Registro geral de uma sessão de calistenia.', 'sessao', 'Sessão', FALSE, 1, TRUE, 'publico', 'publicado'),
('md_karate', 'm_karate', 'Karatê básico', 'basico', 'Registro de prática de karatê.', 'serie', 'Série', TRUE, 1, TRUE, 'publico', 'publicado'),
('md_geral', 'm_geral', 'Registro livre', 'basico', 'Registro genérico para outras atividades.', 'unidade', 'Unidade', TRUE, 1, TRUE, 'publico', 'publicado')
ON CONFLICT DO NOTHING;

INSERT INTO campos_modelo (idcampo, idmodelo, nome, slug, rotulo, tipo_campo, escopo, idgrandeza, idunidade, obrigatorio, ordem) VALUES
('f_corr_dist', 'md_corrida', 'distancia', 'distancia', 'Distância', 'decimal', 'unidade', 'g_distancia', 'u_km', FALSE, 1),
('f_corr_dur', 'md_corrida', 'duracao', 'duracao', 'Duração', 'intervalo', 'unidade', 'g_tempo', NULL, FALSE, 2),
('f_corr_elev', 'md_corrida', 'elevacao', 'elevacao', 'Elevação', 'decimal', 'unidade', 'g_distancia', 'u_m', FALSE, 3),
('f_corr_int', 'md_corrida', 'intensidade', 'intensidade', 'Intensidade', 'selecao', 'registro', NULL, NULL, FALSE, 4),
('f_corr_obs', 'md_corrida', 'sensacao', 'sensacao', 'Sensação / observação', 'texto_longo', 'registro', NULL, NULL, FALSE, 5),

('f_cam_dist', 'md_caminhada', 'distancia', 'distancia', 'Distância', 'decimal', 'unidade', 'g_distancia', 'u_km', FALSE, 1),
('f_cam_dur', 'md_caminhada', 'duracao', 'duracao', 'Duração', 'intervalo', 'unidade', 'g_tempo', NULL, FALSE, 2),
('f_cam_elev', 'md_caminhada', 'elevacao', 'elevacao', 'Elevação', 'decimal', 'unidade', 'g_distancia', 'u_m', FALSE, 3),
('f_cam_int', 'md_caminhada', 'intensidade', 'intensidade', 'Intensidade', 'selecao', 'registro', NULL, NULL, FALSE, 4),

('f_mar_dist', 'md_marcha', 'distancia', 'distancia', 'Distância', 'decimal', 'unidade', 'g_distancia', 'u_km', FALSE, 1),
('f_mar_dur', 'md_marcha', 'duracao', 'duracao', 'Duração', 'intervalo', 'unidade', 'g_tempo', NULL, FALSE, 2),
('f_mar_int', 'md_marcha', 'intensidade', 'intensidade', 'Intensidade', 'selecao', 'registro', NULL, NULL, FALSE, 3),

('f_tri_dist', 'md_trilha', 'distancia', 'distancia', 'Distância', 'decimal', 'unidade', 'g_distancia', 'u_km', FALSE, 1),
('f_tri_dur', 'md_trilha', 'duracao', 'duracao', 'Duração', 'intervalo', 'unidade', 'g_tempo', NULL, FALSE, 2),
('f_tri_elev', 'md_trilha', 'elevacao', 'elevacao', 'Elevação', 'decimal', 'unidade', 'g_distancia', 'u_m', FALSE, 3),
('f_tri_obs', 'md_trilha', 'terreno', 'terreno', 'Terreno', 'texto', 'registro', NULL, NULL, FALSE, 4),

('f_cic_dist', 'md_ciclismo', 'distancia', 'distancia', 'Distância', 'decimal', 'unidade', 'g_distancia', 'u_km', FALSE, 1),
('f_cic_dur', 'md_ciclismo', 'duracao', 'duracao', 'Duração', 'intervalo', 'unidade', 'g_tempo', NULL, FALSE, 2),
('f_cic_elev', 'md_ciclismo', 'elevacao', 'elevacao', 'Elevação', 'decimal', 'unidade', 'g_distancia', 'u_m', FALSE, 3),
('f_cic_int', 'md_ciclismo', 'intensidade', 'intensidade', 'Intensidade', 'selecao', 'registro', NULL, NULL, FALSE, 4),

('f_mtb_dist', 'md_mtb', 'distancia', 'distancia', 'Distância', 'decimal', 'unidade', 'g_distancia', 'u_km', FALSE, 1),
('f_mtb_dur', 'md_mtb', 'duracao', 'duracao', 'Duração', 'intervalo', 'unidade', 'g_tempo', NULL, FALSE, 2),
('f_mtb_elev', 'md_mtb', 'elevacao', 'elevacao', 'Elevação', 'decimal', 'unidade', 'g_distancia', 'u_m', FALSE, 3),

('f_down_dur', 'md_downhill', 'duracao', 'duracao', 'Duração', 'intervalo', 'unidade', 'g_tempo', NULL, FALSE, 1),
('f_down_dist', 'md_downhill', 'distancia', 'distancia', 'Distância', 'decimal', 'unidade', 'g_distancia', 'u_km', FALSE, 2),
('f_bmx_dur', 'md_bmx', 'duracao', 'duracao', 'Duração', 'intervalo', 'unidade', 'g_tempo', NULL, FALSE, 1),

('f_nat_dist', 'md_natacao', 'distancia', 'distancia', 'Distância', 'decimal', 'unidade', 'g_distancia', 'u_m', FALSE, 1),
('f_nat_dur', 'md_natacao', 'duracao', 'duracao', 'Duração', 'intervalo', 'unidade', 'g_tempo', NULL, FALSE, 2),
('f_nat_estilo', 'md_natacao', 'estilo', 'estilo', 'Estilo', 'selecao', 'registro', NULL, NULL, FALSE, 3),

('f_ten_dur', 'md_tenis', 'duracao', 'duracao', 'Duração', 'intervalo', 'unidade', 'g_tempo', NULL, FALSE, 1),
('f_ten_int', 'md_tenis', 'intensidade', 'intensidade', 'Intensidade', 'selecao', 'registro', NULL, NULL, FALSE, 2),
('f_tm_dur', 'md_tenismesa', 'duracao', 'duracao', 'Duração', 'intervalo', 'unidade', 'g_tempo', NULL, FALSE, 1),
('f_tm_int', 'md_tenismesa', 'intensidade', 'intensidade', 'Intensidade', 'selecao', 'registro', NULL, NULL, FALSE, 2),
('f_bad_dur', 'md_badminton', 'duracao', 'duracao', 'Duração', 'intervalo', 'unidade', 'g_tempo', NULL, FALSE, 1),
('f_bad_int', 'md_badminton', 'intensidade', 'intensidade', 'Intensidade', 'selecao', 'registro', NULL, NULL, FALSE, 2),
('f_pad_dur', 'md_padel', 'duracao', 'duracao', 'Duração', 'intervalo', 'unidade', 'g_tempo', NULL, FALSE, 1),
('f_pad_int', 'md_padel', 'intensidade', 'intensidade', 'Intensidade', 'selecao', 'registro', NULL, NULL, FALSE, 2),
('f_bt_dur', 'md_beachtennis', 'duracao', 'duracao', 'Duração', 'intervalo', 'unidade', 'g_tempo', NULL, FALSE, 1),
('f_bt_int', 'md_beachtennis', 'intensidade', 'intensidade', 'Intensidade', 'selecao', 'registro', NULL, NULL, FALSE, 2),

('f_peso_dist', 'md_peso', 'distancia', 'distancia', 'Distância', 'decimal', 'unidade', 'g_distancia', 'u_m', TRUE, 1),
('f_peso_val', 'md_peso', 'valida', 'valida', 'Tentativa válida', 'booleano', 'unidade', NULL, NULL, FALSE, 2),
('f_disco_dist', 'md_disco', 'distancia', 'distancia', 'Distância', 'decimal', 'unidade', 'g_distancia', 'u_m', TRUE, 1),
('f_disco_val', 'md_disco', 'valida', 'valida', 'Tentativa válida', 'booleano', 'unidade', NULL, NULL, FALSE, 2),
('f_dardo_dist', 'md_dardo', 'distancia', 'distancia', 'Distância', 'decimal', 'unidade', 'g_distancia', 'u_m', TRUE, 1),
('f_dardo_val', 'md_dardo', 'valida', 'valida', 'Tentativa válida', 'booleano', 'unidade', NULL, NULL, FALSE, 2),
('f_mart_dist', 'md_martelo', 'distancia', 'distancia', 'Distância', 'decimal', 'unidade', 'g_distancia', 'u_m', TRUE, 1),
('f_mart_val', 'md_martelo', 'valida', 'valida', 'Tentativa válida', 'booleano', 'unidade', NULL, NULL, FALSE, 2),

('f_musc_dur', 'md_musculacao', 'duracao', 'duracao', 'Duração', 'intervalo', 'unidade', 'g_tempo', NULL, FALSE, 1),
('f_musc_int', 'md_musculacao', 'intensidade', 'intensidade', 'Intensidade', 'selecao', 'registro', NULL, NULL, FALSE, 2),
('f_musc_obs', 'md_musculacao', 'observacoes', 'observacoes', 'Observações', 'texto_longo', 'registro', NULL, NULL, FALSE, 3),
('f_cal_dur', 'md_calistenia', 'duracao', 'duracao', 'Duração', 'intervalo', 'unidade', 'g_tempo', NULL, FALSE, 1),
('f_cal_int', 'md_calistenia', 'intensidade', 'intensidade', 'Intensidade', 'selecao', 'registro', NULL, NULL, FALSE, 2),
('f_cal_obs', 'md_calistenia', 'observacoes', 'observacoes', 'Observações', 'texto_longo', 'registro', NULL, NULL, FALSE, 3),

('f_kar_kata', 'md_karate', 'kata', 'kata', 'Kata / técnica', 'texto', 'unidade', NULL, NULL, FALSE, 1),
('f_kar_rep', 'md_karate', 'repeticoes', 'repeticoes', 'Repetições', 'inteiro', 'unidade', NULL, NULL, FALSE, 2),
('f_kar_dur', 'md_karate', 'duracao', 'duracao', 'Duração', 'intervalo', 'unidade', 'g_tempo', NULL, FALSE, 3),
('f_kar_obs', 'md_karate', 'observacoes', 'observacoes', 'Observações', 'texto_longo', 'registro', NULL, NULL, FALSE, 4),

('f_ger_dur', 'md_geral', 'duracao', 'duracao', 'Duração', 'intervalo', 'unidade', 'g_tempo', NULL, FALSE, 1),
('f_ger_int', 'md_geral', 'intensidade', 'intensidade', 'Intensidade', 'selecao', 'registro', NULL, NULL, FALSE, 2),
('f_ger_obs', 'md_geral', 'observacoes', 'observacoes', 'Observações', 'texto_longo', 'registro', NULL, NULL, FALSE, 3)
ON CONFLICT DO NOTHING;

INSERT INTO campos_modelo_opcoes (idopcao, idcampo, rotulo, valor, ordem)
SELECT 'o_' || replace(idcampo, 'f_', '') || '_l', idcampo, 'Leve', 'leve', 1 FROM campos_modelo WHERE slug = 'intensidade'
ON CONFLICT DO NOTHING;
INSERT INTO campos_modelo_opcoes (idopcao, idcampo, rotulo, valor, ordem)
SELECT 'o_' || replace(idcampo, 'f_', '') || '_m', idcampo, 'Moderado', 'moderado', 2 FROM campos_modelo WHERE slug = 'intensidade'
ON CONFLICT DO NOTHING;
INSERT INTO campos_modelo_opcoes (idopcao, idcampo, rotulo, valor, ordem)
SELECT 'o_' || replace(idcampo, 'f_', '') || '_i', idcampo, 'Intenso', 'intenso', 3 FROM campos_modelo WHERE slug = 'intensidade'
ON CONFLICT DO NOTHING;

INSERT INTO campos_modelo_opcoes (idopcao, idcampo, rotulo, valor, ordem) VALUES
('o_nat_livre', 'f_nat_estilo', 'Livre', 'livre', 1),
('o_nat_peito', 'f_nat_estilo', 'Peito', 'peito', 2),
('o_nat_costas', 'f_nat_estilo', 'Costas', 'costas', 3),
('o_nat_borb', 'f_nat_estilo', 'Borboleta', 'borboleta', 4)
ON CONFLICT DO NOTHING;

INSERT INTO categorias_exercicio (idcategoria, nome, slug) VALUES
('c_peito', 'Peito', 'peito'),
('c_costas', 'Costas', 'costas'),
('c_pernas', 'Pernas', 'pernas'),
('c_ombros', 'Ombros', 'ombros'),
('c_bracos', 'Braços', 'bracos'),
('c_core', 'Core', 'core'),
('c_mobilidade', 'Mobilidade', 'mobilidade'),
('c_cardio', 'Cardio', 'cardio'),
('c_corpotodo', 'Corpo inteiro', 'corpo-inteiro')
ON CONFLICT DO NOTHING;

INSERT INTO exercicios (idexercicio, nome, slug, descricao, visibilidade, status_publicacao) VALUES
('e_supino', 'Supino reto', 'supino-reto', 'Exercício de empurrar para peitoral, tríceps e ombros.', 'publico', 'publicado'),
('e_agachamento', 'Agachamento livre', 'agachamento-livre', 'Agachamento com peso livre ou peso corporal.', 'publico', 'publicado'),
('e_remada', 'Remada curvada', 'remada-curvada', 'Exercício de puxar para costas e braços.', 'publico', 'publicado'),
('e_barra', 'Barra fixa', 'barra-fixa', 'Puxada vertical com peso corporal.', 'publico', 'publicado'),
('e_flexao', 'Flexão', 'flexao', 'Empurrar com peso corporal.', 'publico', 'publicado'),
('e_legpress', 'Leg press', 'leg-press', 'Exercício de pernas em máquina.', 'publico', 'publicado'),
('e_afundo', 'Afundo', 'afundo', 'Exercício unilateral para pernas.', 'publico', 'publicado'),
('e_desenvolv', 'Desenvolvimento', 'desenvolvimento', 'Exercício de empurrar acima da cabeça.', 'publico', 'publicado'),
('e_elevlat', 'Elevação lateral', 'elevacao-lateral', 'Elevação lateral para ombros.', 'publico', 'publicado'),
('e_rosca', 'Rosca direta', 'rosca-direta', 'Flexão de cotovelo para bíceps.', 'publico', 'publicado'),
('e_triceps', 'Tríceps testa', 'triceps-testa', 'Extensão de cotovelo para tríceps.', 'publico', 'publicado'),
('e_prancha', 'Prancha', 'prancha', 'Exercício isométrico de core.', 'publico', 'publicado'),
('e_burpee', 'Burpee', 'burpee', 'Exercício de corpo inteiro com componente cardiovascular.', 'publico', 'publicado')
ON CONFLICT DO NOTHING;

INSERT INTO exercicios_categorias (idexercicio, idcategoria) VALUES
('e_supino', 'c_peito'),
('e_remada', 'c_costas'),
('e_barra', 'c_costas'),
('e_flexao', 'c_peito'),
('e_agachamento', 'c_pernas'),
('e_legpress', 'c_pernas'),
('e_afundo', 'c_pernas'),
('e_desenvolv', 'c_ombros'),
('e_elevlat', 'c_ombros'),
('e_rosca', 'c_bracos'),
('e_triceps', 'c_bracos'),
('e_prancha', 'c_core'),
('e_burpee', 'c_corpotodo'),
('e_burpee', 'c_cardio')
ON CONFLICT DO NOTHING;

INSERT INTO exercicios_modalidades (idexercicio, idmodalidade)
SELECT idexercicio, 'm_musculacao' FROM exercicios
ON CONFLICT DO NOTHING;
INSERT INTO exercicios_modalidades (idexercicio, idmodalidade) VALUES
('e_barra', 'm_calistenia'),
('e_flexao', 'm_calistenia'),
('e_agachamento', 'm_calistenia'),
('e_prancha', 'm_calistenia'),
('e_burpee', 'm_calistenia')
ON CONFLICT DO NOTHING;

COMMIT;
