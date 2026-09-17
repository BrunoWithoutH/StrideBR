<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);
$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    $checks++;
    if (!$ok) {
        fwrite(STDERR, "Web Product Expansion v1 static failed: {$message}\n");
        exit(1);
    }
};

$migration = $read('src/database/migrations/20260915_web_product_expansion_v1.sql');
$presenter = $read('src/function/atividade_presenter.php');
$activityModel = $read('src/function/atividade_modelo.php');
$sportHub = $read('src/function/sport_hub.php');
$progress = $read('src/function/progress_service.php');
$dashboard = $read('src/function/dashboard.php');
$activitiesPage = $read('public/user/atividades.php');
$activitiesJs = $read('public/assets/js/atividades.js');
$comparison = $read('public/user/comparar-atividades.php');
$comparisonJs = $read('public/assets/js/activity-comparison-v2.js');
$insights = $read('src/function/activity_insights.php');
$cronograma = $read('src/function/cronograma.php');
$training = $read('src/function/training_platform_service.php');
$apiWorkouts = $read('src/function/api_workouts.php');
$workoutPage = $read('public/user/exercicioscronograma.php');
$activityDetailJs = $read('public/assets/js/activity-detail-v3.js');
$plannedApi = $read('public/api/atividade-planned-actual.php');
$plannedService = $read('src/function/endurance_workout_analysis.php');
$equipmentPage = $read('public/user/equipamentos.php');
$competitionsPage = $read('public/user/competicoes.php');
$competitions = $read('src/function/competitions.php');
$routes = $read('src/function/routes.php');
$routesPage = $read('public/user/rotas.php');
$routesJs = $read('public/assets/js/routes.js');
$schedulePage = $read('public/user/cronogramatreinos.php');
$scheduleJs = $read('public/assets/js/cronogramas.js');
$header = $read('src/layout/header.php');
$home = $read('public/home.php');
$homeJs = $read('public/assets/js/dashboard-v2.js');
$homeCss = $read('public/assets/css/dashboard-v2.css');
$openapi = $read('docs/api/openapi.yaml');

$assert(str_contains($migration, 'ADD COLUMN IF NOT EXISTS excluir_estatisticas BOOLEAN NOT NULL DEFAULT FALSE'), 'migration deve adicionar exclude-from-stats sem apagar Activity.');
$assert(str_contains($migration, 'limite_alerta_km') && str_contains($migration, 'data_fim_uso'), 'Equipment v2 precisa de limite opcional e data final de uso.');
$assert(str_contains($migration, "participacao_status IN ('interessado','inscrito','participou','cancelou')"), 'Competition v2 precisa validar participação.');
$assert(str_contains($migration, 'CREATE TABLE IF NOT EXISTS rotas_salvas') && str_contains($migration, 'CREATE TABLE IF NOT EXISTS rotas_salvas_atividades'), 'Routes precisa de domínio reutilizável próprio.');
$assert(str_contains($migration, 'REFERENCES pacer_plans(idplan) ON DELETE SET NULL'), 'Route precisa referenciar o PK real do Pacer Plan.');
$assert(str_contains($migration, 'tipo_passo') && str_contains($migration, 'interval_group') && str_contains($migration, 'warmup') && str_contains($migration, 'cooldown'), 'migration precisa suportar passos endurance estruturados.');

$assert(str_contains($activityModel, 'function atividadeDefinirExclusaoEstatisticas') && str_contains($activityModel, 'excluir_estatisticas=:excluir'), 'Activity deve poder sair dos agregados sem ser apagada.');
$assert(substr_count($sportHub, 'COALESCE(ra.excluir_estatisticas,FALSE)=FALSE') >= 3, 'Sport Hub precisa excluir Activities marcadas dos agregados.');
$assert(str_contains($progress, 'COALESCE(ra.excluir_estatisticas,FALSE)=FALSE'), 'Progress precisa respeitar exclude-from-stats.');
$assert(substr_count($dashboard, 'COALESCE(ra.excluir_estatisticas,FALSE)=FALSE') >= 2, 'totais/tendências da Home precisam respeitar exclude-from-stats.');
$assert(str_contains($activitiesPage, 'distance_min_km') && str_contains($activitiesPage, 'duration_min_min') && str_contains($activitiesPage, 'name="equipment"'), 'Histórico V3 precisa filtrar distância, duração e equipamento.');
$assert(str_contains($activitiesPage, 'name="with_route"') && str_contains($activitiesPage, 'name="with_hr"') && str_contains($activitiesPage, 'name="with_analysis"'), 'Histórico V3 precisa filtrar route/HR/analysis.');
$assert(str_contains($activitiesPage, 'name="workout_linked"') && str_contains($activitiesPage, 'name="competition_linked"') && str_contains($activitiesPage, 'name="stats"'), 'Histórico V3 precisa filtrar treino, competição e estatísticas.');
$assert(str_contains($activitiesJs, "params.set('distance_min_m'") && str_contains($activitiesJs, "params.set('duration_min_s'") && str_contains($activitiesJs, 'history.replaceState'), 'filtros importantes precisam sobreviver na URL.');
$assert(str_contains($presenter, "sessoes_treino st WHERE st.idregistro_atividade=ra.idregistro") && str_contains($presenter, 'AS has_workout'), 'filtro de Workout precisa reconhecer recorrência e Workout Session/agendamento.');
$assert(str_contains($activitiesJs, 'Fora das estatísticas') && str_contains($activityDetailJs, '/api/atividade-estatisticas.php'), 'Web precisa indicar e permitir alternar exclusão de estatísticas.');

$assert(str_contains($comparison, '<h2>Curvas</h2>') && str_contains($comparison, '<h2>Splits de 1 km</h2>') && str_contains($comparison, '<h2>Análise</h2>'), 'Comparison V2 precisa comparar curvas, splits e analysis.');
$assert(str_contains($comparison, 'Primeira metade') && str_contains($comparison, 'Segunda metade') && str_contains($comparison, 'Variabilidade') && str_contains($comparison, 'Cardiac drift'), 'Comparison V2 precisa expor métricas objetivas avançadas.');
$assert(str_contains($comparisonJs, 'gap_before_ms') && str_contains($comparisonJs, 'row.x') && str_contains($comparison, 'Sobreposição por distância'), 'curvas A×B precisam usar eixo distância e respeitar gaps.');
$assert(str_contains($insights, 'activityStreamRead(') && str_contains($insights, 'activityStreamSplits(') && str_contains($insights, 'activityAnalysisCompute('), 'Comparison V2 precisa reutilizar Streams/Splits/Analysis canônicos.');
$assert(str_contains($insights, 'FROM series_exercicio_atividade sea') && !str_contains(substr($insights, 0, strpos($insights, 'function activityInsightsProgress')), 'sessoes_treino_series'), 'comparação de força precisa usar séries canônicas.');
$assert(str_contains($insights, 'SELECT MAX(sea.carga_kg) FROM series_exercicio_atividade'), 'insights de força históricos precisam usar a mesma fonte canônica.');

$assert(str_contains($migration, 'repeticoes_bloco') && str_contains($migration, 'alvo_tipo') && str_contains($migration, 'recuperacao_duracao_s'), 'schema endurance precisa persistir repetição, alvo e recuperação.');
$assert(str_contains($workoutPage, 'Aquecimento') && str_contains($workoutPage, 'Trabalho') && str_contains($workoutPage, 'Recuperação') && str_contains($workoutPage, 'Desaquecimento'), 'editor Web precisa expor passos endurance.');
$assert(str_contains($workoutPage, "['pace'=>'Pace','speed'=>'Velocidade','heart_rate'=>'FC','rpe'=>'RPE','duration'=>'Duração','distance'=>'Distância']"), 'editor precisa expor targets de endurance suportados.');
$assert(str_contains($cronograma, 'cronogramaNormalizarPassoEstruturado') && str_contains($cronograma, 'cronogramaPassoNomePadrao'), 'cronograma precisa centralizar normalização dos passos.');
$assert(str_contains($training, "array_key_exists('step_type', \$row)") && str_contains($training, "isset(\$row['target'])") && str_contains($training, "isset(\$row['recovery'])"), 'Training Platform precisa aceitar o contrato canônico endurance do Mobile.');
$assert(str_contains($apiWorkouts, "'step_type'") && str_contains($apiWorkouts, "'repeat_count'") && str_contains($apiWorkouts, "'target'") && str_contains($apiWorkouts, "'recovery'"), 'Workout API precisa devolver os passos estruturados.');
$assert(str_contains($openapi, 'WorkoutStepTarget:') && str_contains($openapi, 'WorkoutStepRecovery:') && str_contains($openapi, 'step_type: { type: string, enum: [exercise, warmup, work, recovery, cooldown, interval_group] }'), 'OpenAPI precisa documentar endurance estruturado.');
$assert(str_contains($plannedService, 'function enduranceWorkoutCompareActivity') && str_contains($plannedService, 'activityStreamSegmentMetrics('), 'planned × actual deve reutilizar Streams canônicos.');
$assert(str_contains($plannedService, 'distance_m') && str_contains($plannedService, 'moving_ms'), 'blocos precisam poder ser delimitados sequencialmente por distância ou moving time.');
$assert(str_contains($activityDetailJs, '/api/atividade-planned-actual.php') && str_contains($activityDetailJs, 'Dentro da faixa') && str_contains($activityDetailJs, 'Fora da faixa'), 'Activity Analysis Web precisa mostrar planejado × realizado por bloco quando comparável.');
$assert(str_contains($plannedApi, 'stridebr_require_login()') && str_contains($plannedApi, 'Cache-Control: private, no-store'), 'planned × actual precisa ser privado.');

$assert(str_contains($equipmentPage, 'atividadeDetalheEquipamento(') && str_contains($equipmentPage, 'Alerta de uso') && str_contains($equipmentPage, 'Primeiro uso') && str_contains($equipmentPage, 'Último uso'), 'Equipment V2 precisa ter detail, limite e histórico temporal.');
$assert(str_contains($activityModel, 'total_atividades') && str_contains($activityModel, 'distancia_total_km') && str_contains($activityModel, 'duracao_total_s') && str_contains($activityModel, 'elevacao_total_m'), 'Equipment detail precisa calcular somente agregados objetivos.');
$assert(!str_contains($equipmentPage, 'mais rápido') && !str_contains($equipmentPage, 'desgastado'), 'Equipment V2 não deve inferir performance/desgaste.');
$assert(str_contains($equipmentPage, 'name="eq_q"') && str_contains($equipmentPage, 'name="eq_from"') && str_contains($equipmentPage, 'name="eq_to"'), 'Equipment V2 precisa permitir filtrar o histórico por busca e período.');

$assert(str_contains($competitionsPage, 'interessado') && str_contains($competitionsPage, 'inscrito') && str_contains($competitionsPage, 'participou') && str_contains($competitionsPage, 'cancelou'), 'Competition V2 precisa expor participação coerente.');
$assert(str_contains($competitionsPage, 'resultado_tempo_s') && str_contains($competitionsPage, 'resultado_posicao_geral') && str_contains($competitionsPage, 'resultado_medalha'), 'Competition V2 precisa editar resultados factuais.');
$assert(str_contains($competitions, 'function competitionLinkActivity') && str_contains($competitions, 'UPDATE registros_atividade SET idcompeticao=:competicao'), 'Competition precisa vincular Activity explicitamente.');
$assert(str_contains($competitions, "':participacao'=>\$data['participacao_status']") && str_contains($competitions, "':resultado_tempo'=>\$data['resultado_tempo_s']"), 'UPDATE de competição precisa bindar os novos campos V2.');
$assert(str_contains($competitionsPage, 'Preparação') && str_contains($competitionsPage, '/user/pacer.php') && str_contains($competitionsPage, '/user/cronogramatreinos.php'), 'competição futura precisa conectar preparação sem AI plan.');
$assert(str_contains($competitionsPage, "stridebr_t('competitions.empty')") && str_contains($competitionsPage, "stridebr_t('competitions.empty_help')") && str_contains($competitionsPage, "stridebr_t('competitions.history')") && str_contains($competitionsPage, "stridebr_t('competitions.upcoming')"), 'Competições precisa ter empty state útil e separar próximas de histórico.');

$assert(str_contains($routes, 'function routeSavedCreateFromActivity') && str_contains($routes, 'function routeSavedValidateForWorkout') && str_contains($routes, 'function routeSavedLinkActivityFromWorkout'), 'Routes precisa salvar de Activity, validar Workout e registrar reuso.');
$assert(str_contains($routes, 'p.idplan=r.idpacerplan') && str_contains($routes, 'WHERE idplan=:id'), 'Routes precisa usar idplan do Pacer real.');
$assert(str_contains($routesPage, 'Minhas Rotas') && str_contains($routesPage, 'Usar em treino') && str_contains($routesPage, 'data-route-detail-map'), 'Routes Web precisa ter lista/detail/map/reuse.');
$assert(str_contains($routesPage, 'if ($routes === [] && $detail === null)') && str_contains($routesPage, 'Nenhuma rota salva.') && str_contains($routesPage, 'Ver atividades com rota'), 'Zero rotas precisa usar empty state único antes do split pane.');
$assert(str_contains($routesJs, 'IntersectionObserver') && str_contains($routesJs, 'StrideBRWebMap'), 'mini mapas de Routes precisam ser lazy e reutilizar mapa centralizado.');
$assert(str_contains($activityDetailJs, '/api/rota-salvar.php') && str_contains($activityDetailJs, 'Salvar rota'), 'Activity Detail precisa permitir salvar rota sem duplicar Activity.');
$assert(str_contains($schedulePage, 'name="route_id"') && str_contains($schedulePage, 'data-editor-route') && str_contains($schedulePage, 'data-quick-route'), 'Workout Web precisa aceitar Route no editor e Quick Create.');
$assert(str_contains($scheduleJs, 'syncEditorRoute') && str_contains($scheduleJs, 'dataset.sport') && str_contains($scheduleJs, "pageParams.get('route')"), 'Route selector precisa filtrar modalidade e aceitar prefill de Minhas Rotas.');
$assert(str_contains($cronograma, 'routeSavedValidateForWorkout(') && str_contains($cronograma, 'idrota_salva = :rota') && str_contains($cronograma, "\$payload['route_id'] = \$requestedRouteId"), 'cronograma precisa persistir/propagar Route pelo service compartilhado.');
$assert(str_contains($header, '/user/rotas.php'), 'Minhas Rotas precisa estar acessível na navegação.');

$assert(str_contains($home, 'dashboard-tomorrow-context') && str_contains($home, 'Últimos 28 dias') && str_contains($home, 'data-dashboard-module="recent"') && !str_contains($home, '>Próximo treino<') && !str_contains($home, '>Última atividade<'), 'Home precisa concentrar Hoje/Amanhã, semana, faixa de 28 dias e atividades recentes sem cards redundantes.');
$assert(str_contains($home, 'dashboard-home-rail') && str_contains($home, 'Próximas competições') && str_contains($home, 'count($nextCompetitions) >= 3'), 'Competições precisam ocupar rail compacto e limitar a três itens.');
$assert(str_contains($home, 'dashboard-pacer-rail') && str_contains($home, 'Novo treino') && str_contains($home, 'Importar atividade') && !preg_match('/dashboard-secondary-actions[^\n]+Criar Pacer/', $home), 'Pacer deve viver no rail e ações secundárias precisam evitar CTA duplicada.');
$assert(str_contains($home, 'dashboard-button-primary') && str_contains($home, 'home.log_activity') && str_contains($home, 'home.record_gps'), 'ações primárias de gravação e registro precisam permanecer no topo.');
$assert(!str_contains($home, 'data-home-last-map') && !str_contains($home, 'dashboard-v2-last-map'), 'Home não deve carregar mapa da última atividade.');
$assert(str_contains($homeCss, '.dashboard-home-layout') && str_contains($homeCss, 'minmax(280px,320px)') && str_contains($homeCss, '.dashboard-period-strip') && str_contains($homeCss, '@media(max-width:900px)'), 'Home precisa manter main + right rail e reflow responsivo.');
$assert(!str_contains($home, 'data-dashboard-module="upcoming"') && str_contains($home, 'data-dashboard-module="recent"'), 'Home deve recuperar somente o módulo recente, sem reintroduzir o antigo próximo treino.');

printf("✓ Web Product Expansion v1 static: %d assertions\n", $checks);
