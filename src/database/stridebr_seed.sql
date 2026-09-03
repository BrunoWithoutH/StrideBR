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

UPDATE modalidades
SET permite_rota = TRUE
WHERE idusuario IS NULL
  AND lower(slug) IN ('caminhada', 'corrida', 'trilha', 'ciclismo', 'mountain-bike', 'marcha-atletica', 'downhill', 'bmx', 'patins', 'skate', 'canoagem', 'caiaque', 'remo', 'vela', 'esqui', 'snowboard');

INSERT INTO modalidades (
    idmodalidade, nome, slug, descricao, visibilidade, status_publicacao,
    categoria, icone, ordem_catalogo, metrica_derivada, permite_rota
) VALUES (
    'm_triatlo', 'Triatlo', 'triatlo', 'Sessão multiesporte com etapas de natação, ciclismo, corrida e outras modalidades.',
    'publico', 'publicado', 'Multiesporte', '🏊', 180, 'nenhuma', FALSE
) ON CONFLICT DO NOTHING;

INSERT INTO modelos_modalidade (
    idmodelo, idmodalidade, nome, slug, descricao, tipo_unidade_padrao,
    rotulo_unidade, permite_multiplas_unidades, versao, padrao, visibilidade, status_publicacao
) VALUES (
    'md_triatlo', 'm_triatlo', 'Triatlo', 'basico', 'Registro de uma sessão multiesporte organizada em trechos.',
    'etapa', 'Trecho', TRUE, 1, TRUE, 'publico', 'publicado'
) ON CONFLICT DO NOTHING;

INSERT INTO campos_modelo (
    idcampo, idmodelo, nome, slug, rotulo, tipo_campo, escopo,
    idgrandeza, idunidade, obrigatorio, ordem, exibicao_padrao, grupo_ui
) VALUES
    ('f_triat_dist', 'md_triatlo', 'distancia', 'distancia', 'Distância', 'decimal', 'unidade', 'g_distancia', 'u_km', FALSE, 10, TRUE, 'principal'),
    ('f_triat_dur', 'md_triatlo', 'duracao', 'duracao', 'Duração', 'intervalo', 'unidade', 'g_tempo', NULL, FALSE, 20, TRUE, 'principal'),
    ('f_triat_elev', 'md_triatlo', 'elevacao', 'elevacao', 'Elevação', 'decimal', 'unidade', 'g_distancia', 'u_m', FALSE, 30, FALSE, 'extras')
ON CONFLICT DO NOTHING;

COMMIT;
