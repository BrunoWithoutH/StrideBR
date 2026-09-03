<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/src/includes/app.php';
require_once $root . '/src/function/activity_energy.php';
require_once $root . '/src/function/sport_hub.php';

$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    $checks++;
    if (!$ok) {
        fwrite(STDERR, "Sport taxonomy/energy failed: {$message}\n");
        exit(1);
    }
};

$taxonomy = (string) file_get_contents($root . '/src/database/migrations/20260903_v1_rc.sql');
$energyMigration = (string) file_get_contents($root . '/src/database/migrations/20260903_v1_rc.sql');
$performanceMigration = (string) file_get_contents($root . '/src/database/migrations/20260903_v1_rc.sql');
$sessionMigration = (string) file_get_contents($root . '/src/database/migrations/20260903_v1_rc.sql');
$energyCode = (string) file_get_contents($root . '/src/function/activity_energy.php');
$presenter = (string) file_get_contents($root . '/src/function/atividade_presenter.php');
$settings = (string) file_get_contents($root . '/public/user/settings.php');
$progress = (string) file_get_contents($root . '/public/user/progresso.php');
$activityPanels = (string) file_get_contents($root . '/src/layout/activity_model_panels.php');

$assert(str_contains($taxonomy, 'familia_hub') && str_contains($taxonomy, "'salto-triplo'") && str_contains($taxonomy, "'tiro-com-arco'") && str_contains($taxonomy, "'mma'") && str_contains($taxonomy, "'kart'"), 'catálogo amplo e famílias esportivas precisam existir.');
$assert(str_contains($taxonomy, "WHEN 'cardio' THEN 'Cardio'") && str_contains($taxonomy, "WHEN 'athletics' THEN 'Atletismo'") && str_contains($taxonomy, "WHEN 'team' THEN 'Esportes em equipe'"), 'nomes do hub precisam ser intuitivos.');
$assert(sportHubBucket('Atletismo', 'salto-em-distancia') === 'athletics' && sportHubBucket('Esportes em equipe', 'futebol') === 'team' && sportHubBucket('Cardio', 'corrida') === 'cardio', 'classificação do hub precisa reconhecer famílias principais.');
$assert(str_contains($energyMigration, 'historico_peso_usuario') && str_contains($energyMigration, 'modalidades_energia') && str_contains($energyMigration, 'calorias_ativas_estimadas'), 'schema de energia e histórico de peso precisa existir.');
$assert(str_contains($performanceMigration, "'fc-maxima'") && str_contains($performanceMigration, "'cadencia'") && str_contains($performanceMigration, "'potencia'") && str_contains($performanceMigration, "'tempo-reacao'"), 'métricas avançadas precisam existir para cardio e pista.');
$assert(str_contains($energyMigration, "modelo = 'racket'") && str_contains($energyMigration, "modelo = 'combat'") && str_contains($energyMigration, "modelo = 'field_event'") && str_contains($energyMigration, "modelo = 'motorsport'"), 'perfis de energia precisam cobrir famílias além de corrida e força.');

$runProfile = ['modelo' => 'run', 'met_leve' => 6.5, 'met_moderado' => 9.3, 'met_vigoroso' => 12.0, 'met_maximo' => 16.8];
$flatRun = atividadeEnergiaEstimar(['duration_s' => 1800, 'distance_m' => 5000, 'elevation_profile' => [['distancia_m' => 0, 'elevacao_m' => 100], ['distancia_m' => 5000, 'elevacao_m' => 100]]], $runProfile, 70.0);
$hillRun = atividadeEnergiaEstimar(['duration_s' => 1800, 'distance_m' => 5000, 'elevation_profile' => [['distancia_m' => 0, 'elevacao_m' => 100], ['distancia_m' => 2500, 'elevacao_m' => 250], ['distancia_m' => 5000, 'elevacao_m' => 100]]], $runProfile, 70.0);
$assert(is_array($flatRun) && $flatRun['active_kcal'] > 250 && $flatRun['active_kcal'] < 500 && $flatRun['method'] === 'run_pace_grade_profile', 'corrida precisa usar pace e perfil de elevação em faixa plausível.');
$assert(is_array($hillRun) && $hillRun['active_kcal'] > $flatRun['active_kcal'], 'subida precisa aumentar o custo estimado da corrida.');

$cycle = atividadeEnergiaEstimar(['duration_s' => 3600, 'distance_m' => 30000, 'avg_power_w' => 200], ['modelo' => 'cycle', 'met_leve' => 4.0, 'met_moderado' => 8.0, 'met_vigoroso' => 12.0, 'met_maximo' => 16.8], 70.0);
$assert(is_array($cycle) && $cycle['method'] === 'cycle_power' && $cycle['confidence'] === 'alta' && $cycle['active_kcal'] > 650 && $cycle['active_kcal'] < 820, 'ciclismo com potência precisa priorizar watts.');

$strength = atividadeEnergiaEstimar(['duration_s' => 3600, 'completed_sets' => 20, 'rpe' => 7], ['modelo' => 'strength', 'met_leve' => 3.0, 'met_moderado' => 4.5, 'met_vigoroso' => 6.0, 'met_maximo' => 7.5], 70.0);
$assert(is_array($strength) && $strength['method'] === 'strength_density' && $strength['active_kcal'] > 250 && $strength['active_kcal'] < 550, 'força precisa usar densidade de séries e esforço, não volume como calorias.');

$lightPerson = atividadeEnergiaEstimar(['duration_s' => 1800, 'distance_m' => 5000], $runProfile, 55.0);
$heavyPerson = atividadeEnergiaEstimar(['duration_s' => 1800, 'distance_m' => 5000], $runProfile, 85.0);
$assert(is_array($lightPerson) && is_array($heavyPerson) && $heavyPerson['active_kcal'] > $lightPerson['active_kcal'], 'peso de referência precisa influenciar o gasto energético.');
$assert(str_contains($energyCode, 'atividadeEnergiaPesoParaData') && str_contains($settings, 'Histórico de peso') && str_contains($settings, 'historico_peso_usuario'), 'peso histórico precisa ser usado e editável.');
$assert(str_contains($presenter, "'energia' => \$energy") && str_contains($presenter, "'rotulo' => 'Calorias'") && str_contains($progress, 'calories_kcal'), 'calorias precisam voltar para detalhe, compartilhamento e progresso.');
$assert(str_contains($taxonomy, "tipo_unidade_padrao = 'tentativa'") && str_contains($taxonomy, "'tentativa-nula'") && str_contains($taxonomy, "'vento'") && str_contains($taxonomy, "slug = 'marca'"), 'saltos e lançamentos precisam registrar tentativas, marcas e tentativa nula/falha.');
$assert(str_contains($activityPanels, "\$attemptMode ? 'Tentativas' : 'Trechos'") && str_contains($progress, 'Melhores marcas') && str_contains($progress, 'Melhores tempos') && str_contains((string) file_get_contents($root . '/src/function/sport_hub.php'), 'sportHubAthleticsDashboard'), 'atletismo precisa ter UX de tentativas e painel próprio de evolução.');
$assert(sportHubCardioDiscipline('corrida-em-trilha') === 'run' && sportHubCardioDiscipline('ciclismo-de-estrada') === 'cycle' && sportHubCardioDiscipline('natacao-em-piscina') === 'swim', 'Cardio precisa separar corrida, ciclismo, natação e demais disciplinas de forma intuitiva.');
$cardioDashboard = sportHubCardioDashboard([[
    'hub_bucket' => 'cardio', 'modalidade_slug' => 'corrida', 'data_inicio' => date('c'), 'duration_s' => 1800, 'distancia_metros' => 5000, 'ganho_elevacao_m' => 40, 'calorias_kcal' => 320,
]]);
$assert(($cardioDashboard['summary']['run']['current']['activities'] ?? 0) === 1 && ($cardioDashboard['summary']['run']['current']['avg_pace_km_s'] ?? 0) > 0 && isset($cardioDashboard['active']['run']) && str_contains($progress, 'Últimas 8 semanas'), 'Cardio precisa ter subáreas e métricas próprias por modalidade.');
$cardioRich = sportHubCardioDashboard([[
    'hub_bucket' => 'cardio', 'idregistro' => 'r1', 'titulo' => 'Corrida pela manhã', 'modalidade_nome' => 'Corrida', 'modalidade_slug' => 'corrida', 'data_inicio' => date('c'), 'duration_s' => 2400, 'distancia_metros' => 7000, 'ganho_elevacao_m' => 60, 'calorias_kcal' => 430, 'fc_media_bpm' => 148, 'fc_maxima_bpm' => 172, 'cadencia_media' => 174, 'potencia_media_w' => 265,
]]);
$assert(($cardioRich['summary']['run']['current']['avg_hr_bpm'] ?? 0) > 140 && ($cardioRich['summary']['run']['current']['avg_power_w'] ?? 0) > 250 && count($cardioRich['recent']['run'] ?? []) === 1 && array_key_exists('pace_pct', $cardioRich['trends']['run'] ?? []), 'Cardio precisa aproveitar FC, cadência, potência, histórico recente e tendência.');
$assert(str_contains($progress, 'Esforço & técnica') && str_contains($progress, 'Sessões recentes') && str_contains($progress, 'Nova marca') && str_contains($progress, 'melhor com vento válido'), 'hub precisa apresentar técnica, evolução de força e validade de vento no atletismo.');
$assert(str_contains($sessionMigration, "'racket','tipo-sessao'") && str_contains($sessionMigration, "'team','placar-favor'") && str_contains($sessionMigration, "'combat','rounds'") && str_contains($sessionMigration, "'precision','pontuacao'"), 'raquetes, equipe, lutas e precisão precisam aceitar detalhes próprios sem criar formulários separados.');
$sessionDash = sportHubSessionDashboard([[
    'hub_bucket' => 'racket', 'idregistro' => 'r2', 'titulo' => 'Tênis à tarde', 'modalidade_nome' => 'Tênis', 'data_inicio' => date('c'), 'duration_s' => 5400,
    'tipo_sessao' => 'partida', 'formato_jogo' => 'simples', 'resultado' => 'vitoria', 'adversario' => 'Dani', 'placar' => '6/4 6/3',
]], 'racket');
$assert(($sessionDash['summary']['current']['matches'] ?? 0) === 1 && ($sessionDash['summary']['current']['wins'] ?? 0) === 1 && ($sessionDash['summary']['current']['win_rate'] ?? 0) === 100.0 && count($sessionDash['recent'] ?? []) === 1, 'hub de raquetes precisa entender partida, resultado e contexto da sessão.');
$assert(str_contains($progress, 'Últimas sessões') && str_contains($progress, 'Aproveitamento') && str_contains($progress, 'Melhor pontuação'), 'hubs de raquetes, equipe, lutas e precisão precisam mostrar métricas próprias.');
$tennisSingles = atividadeEnergiaEstimar(['duration_s' => 3600, 'rpe' => 6, 'session_type' => 'partida', 'game_format' => 'simples'], ['modelo' => 'racket', 'met_leve' => 4.5, 'met_moderado' => 6.8, 'met_vigoroso' => 8.0, 'met_maximo' => 10.0], 70.0);
$tennisDoubles = atividadeEnergiaEstimar(['duration_s' => 3600, 'rpe' => 6, 'session_type' => 'partida', 'game_format' => 'duplas'], ['modelo' => 'racket', 'met_leve' => 4.5, 'met_moderado' => 6.8, 'met_vigoroso' => 8.0, 'met_maximo' => 10.0], 70.0);
$assert(is_array($tennisSingles) && is_array($tennisDoubles) && $tennisSingles['method'] === 'racket_session' && $tennisSingles['active_kcal'] > $tennisDoubles['active_kcal'], 'raquetes precisam usar tipo/formato da sessão na estimativa sem tratar simples e duplas como iguais.');
$combatTechnique = atividadeEnergiaEstimar(['duration_s' => 3600, 'rpe' => 7, 'session_type' => 'tecnica', 'rounds' => 4], ['modelo' => 'combat', 'met_leve' => 5.0, 'met_moderado' => 7.0, 'met_vigoroso' => 10.0, 'met_maximo' => 12.5], 70.0);
$combatSparring = atividadeEnergiaEstimar(['duration_s' => 3600, 'rpe' => 7, 'session_type' => 'sparring', 'rounds' => 10], ['modelo' => 'combat', 'met_leve' => 5.0, 'met_moderado' => 7.0, 'met_vigoroso' => 10.0, 'met_maximo' => 12.5], 70.0);
$assert(is_array($combatTechnique) && is_array($combatSparring) && $combatSparring['active_kcal'] > $combatTechnique['active_kcal'], 'lutas precisam distinguir técnica, sparring e densidade de rounds no gasto estimado.');

printf("✓ sport taxonomy/energy: %d assertions\n", $checks);
