<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);
$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (!$condition) throw new RuntimeException($message);
};

$zonesMigration = $read('src/database/migrations/20260915_activity_streams_analysis_pacer_v1.sql');
$accountData = $read('src/function/account_data.php');
$routes = $read('src/function/routes.php');
$dashboard = $read('src/function/dashboard.php');
$sessionService = $read('src/function/workout_session_service.php');
$progressIntegration = $read('scripts/tests/test_mobile_progress_platform_v1.php');
$streamsIntegration = $read('scripts/tests/test_activity_streams_analysis_pacer_v1.php');
$teamsIntegration = $read('scripts/tests/test_teams_surface_core_v1.php');
$apiRouter = $read('public/api/v1/index.php');
$apiHelpers = $read('src/function/api_v1.php');
$buildHelper = $read('src/function/build_identifier.php');
$workoutSessionService = $read('src/function/workout_session_service.php');
$trainingApi = $read('src/function/api_training_platform.php');
$trainingService = $read('src/function/training_platform_service.php');
$streamService = $read('src/function/activity_stream_service.php');
$configuration = $read('src/includes/configuration.php');
$activityPublishRepair = $read('src/database/migrations/20260915_z_mobile_activity_publish_p0.sql');

$assert(str_contains($zonesMigration, 'profile_type VARCHAR(20)') && str_contains($zonesMigration, 'name VARCHAR(80)'), 'zone_profiles precisa usar profile_type/name como schema canônico.');
$assert(str_contains($accountData, 'ORDER BY profile_type,name') && !str_contains($accountData, 'ORDER BY tipo,nome'), 'Export da conta precisa consultar a taxonomia real de zone_profiles.');
$assert(str_contains($accountData, 'pacer_plan_segments WHERE idplan=:id') && !str_contains($accountData, 'pacer_plan_segments WHERE idpacerplan=:id'), 'Export de Pacer precisa usar idplan real.');
$assert(str_contains($routes, 'p.name AS pacer_nome') && !str_contains($routes, 'p.nome AS pacer_nome'), 'Routes precisa usar pacer_plans.name.');
$assert(!str_contains($dashboard, '(SELECT r.coordenadas FROM rotas_atividade r WHERE r.idregistro=ra.idregistro LIMIT 1) AS route_geojson'), 'Aggregate de metas não pode referenciar ra.idregistro fora de GROUP BY.');
$assert(str_contains($sessionService, "(?:kg)?$/i") && str_contains($sessionService, "return \$formatted . ' kg';"), 'Workout Session precisa aceitar e normalizar carga em kg sem relaxar formato numérico.');
$assert(str_contains($progressIntegration, "':done' => 'false'") && !str_contains($progressIntegration, "':done' => false"), 'Fixture Progress precisa bindar false PostgreSQL de forma explícita.');
$assert(str_contains($streamsIntegration, 'stridebr_api_activity_detail($pdo, $activityId, $owner)'), 'Teste Streams precisa respeitar a assinatura canônica activityId,userId.');
$assert(str_contains($teamsIntegration, "'started_at_local'") && str_contains($teamsIntegration, "'ended_at_local'"), 'Fixture Teams precisa concluir sessão com duração válida explícita.');
$assert(str_contains($apiRouter, "'build'=>stridebr_api_build_identifier()") && str_contains($buildHelper, "getenv('STRIDEBR_BUILD')"), 'GET /meta precisa expor build do ambiente de deploy.');
$assert(!str_contains($apiHelpers, 'git rev-parse') && !str_contains($apiHelpers, 'shell_exec'), 'Build da API não pode executar Git em runtime.');
$assert(!str_contains($workoutSessionService, '(:expected IS NULL OR s.idsessao = :expected)') && str_contains($workoutSessionService, 'if ($expectedSessionId !== null)'), 'Workout Session não pode usar placeholder NULL ambíguo no PostgreSQL.');
$assert(str_contains($trainingApi, '?string $idempotencyKey = null'), 'Training Platform precisa manter idempotency key opcional no helper interno.');
$assert(str_contains($trainingService, "'idexercicio' => \$exerciseId !== '' ? \$exerciseId : null"), 'Passo endurance sem exercício precisa normalizar idexercicio vazio para NULL.');
$assert(str_contains($streamService, "\$item['pace_s_per_km'] = \$item['pace']"), 'Streams alinhados precisam expor alias pace_s_per_km junto de pace.');
$assert(str_contains($streamService, "\$next = \$samples[\$index + 1]") && str_contains($streamService, "gap_before_ms"), 'Primeiro sample sem speed precisa poder derivar do próximo intervalo sem atravessar gap.');
$assert(str_contains($streamService, "activityStreamEncodeJsonObject") && str_contains($streamService, "activityStreamMetadataObject") && !str_contains($streamService, ":metadata' => json_encode(\$lap['metadata']"), 'Metadata vazio de bundle/lap precisa persistir como JSON object, nunca [].');
$assert(str_contains($configuration, "to_regclass('stridebr.activity_stream_bundles')") && str_contains($configuration, "to_regclass('stridebr.activity_stream_samples')") && str_contains($configuration, "to_regclass('stridebr.activity_laps')") && str_contains($configuration, "to_regclass('stridebr.activity_analysis_cache')"), 'Readiness precisa verificar a infraestrutura estrutural de Activity Streams.');
$assert(str_contains($configuration, "column_name = 'pontos_metadata'") && str_contains($configuration, "column_name = 'duracao_s'") && str_contains($configuration, "data_type = 'numeric'"), 'Readiness precisa detectar drift do schema Mobile Activities v2.');
$assert(str_contains($activityPublishRepair, 'ALTER COLUMN duracao_s TYPE NUMERIC(12,3)') && str_contains($activityPublishRepair, 'ADD COLUMN IF NOT EXISTS pontos_metadata JSONB'), 'Repair migration precisa restaurar o schema crítico de GPS mobile.');
$assert(str_contains($activityPublishRepair, 'CREATE TABLE IF NOT EXISTS activity_stream_bundles') && str_contains($activityPublishRepair, 'CREATE TABLE IF NOT EXISTS activity_stream_samples') && str_contains($activityPublishRepair, 'CREATE TABLE IF NOT EXISTS activity_laps') && str_contains($activityPublishRepair, 'CREATE TABLE IF NOT EXISTS activity_analysis_cache'), 'Repair migration precisa restaurar toda a infraestrutura crítica de Streams/Laps.');

printf("✓ Core stabilization PostgreSQL gate static: %d assertions\n", $checks);
