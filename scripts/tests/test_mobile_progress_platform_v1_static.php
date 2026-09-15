<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$service = file_get_contents($root . '/src/function/progress_service.php');
$api = file_get_contents($root . '/src/function/api_progress.php');
$router = file_get_contents($root . '/public/api/v1/index.php');
$sportHub = file_get_contents($root . '/src/function/sport_hub.php');
$trainingService = file_get_contents($root . '/src/function/training_platform_service.php');
$openapi = file_get_contents($root . '/docs/api/openapi.yaml');
$doc = file_get_contents($root . '/docs/MOBILE_PROGRESS_API.md');
$migration = file_get_contents($root . '/src/database/migrations/20260915_mobile_progress_platform_v1.sql');

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

foreach (['overview','timeseries','sports','calendar','cardio','strength','exercises','adherence','dashboard'] as $path) {
    $assert(str_contains($router, "progress/{$path}"), "Rota progress/{$path} ausente");
}
$assert(str_contains($router, "count(\$parts) === 3") && str_contains($router, "\$parts[1] === 'exercises'"), 'Detalhe de exercício ausente');
$assert(str_contains($router, "stridebr_api_error(404, 'not_found'"), '404 JSON de exercício ausente');
$assert(str_contains($router, "stridebr_api_error(422, 'validation_error'"), '422 JSON de validação ausente');
$assert(str_contains($api, 'progressOverview(') && str_contains($api, 'progressDashboard('), 'Adapter API Progress incompleto');
$assert(str_contains($service, "new DateTimeZone('America/Sao_Paulo')"), 'Timezone canônico ausente');
$assert(str_contains($service, '1827'), 'Limite de range ausente');
$assert(str_contains($service, "['activities', 'duration', 'distance', 'elevation_gain', 'training_load', 'perceived_effort', 'strength_volume']"), 'Enum de métricas incompleto');
$assert(str_contains($service, "['day', 'week', 'month']"), 'Enum de buckets incompleto');
$assert(str_contains($service, 'sportHubTrainingLoad($rows)'), 'Training load não reutiliza Sport Hub');
$assert(str_contains($service, 'planejamentoEstado($row, $today)'), 'Aderência não reutiliza planejamentoEstado');
$assert(str_contains($service, 'cronogramaListarOcorrencias(') && str_contains($service, 'cronogramaConciliarOcorrenciasComRegistros('), 'Aderência não reutiliza engine de cronograma');
$assert(str_contains($service, "sportHubStrengthSets(\$pdo, \$userId, \$range['start'], \$range['end'], true)"), 'Strength analytics não reutiliza Sport Hub em modo canônico');
$assert(!str_contains($service, 'sessoes_treino_series'), 'Progress não pode usar séries transitórias da sessão como histórico');
$assert(!str_contains($service, 'best_e1rm') && !str_contains($service, '1rm'), 'Progress v1 não deve criar e1RM');
$assert(str_contains($service, 'max(0, (int) $row[\'repeticoes\']) * max(0.0, (float) $row[\'carga_kg\'])'), 'Semântica reps × load_kg ausente');
$assert(str_contains($service, 'treinoExerciciosMelhoresCargasKg(') && str_contains($service, 'treinoExercicioMelhorCargaKg('), 'Progress não reutiliza melhor carga da Training Platform');
$assert(str_contains($trainingService, 'MAX(sea.carga_kg) AS best_load_kg'), 'Melhor carga canônica ausente na Training Platform');
$assert(str_contains($sportHub, 'function sportHubActivityRowsQuery'), 'Consulta compartilhada do Sport Hub ausente');
$assert(str_contains($sportHub, 'distancia_raw_m') && str_contains($sportHub, 'duration_raw_s'), 'Sport Hub não expõe nullability de métricas');
$assert(str_contains($sportHub, 'm.metrica_derivada') && str_contains($service, "'derived_metric'"), 'Behavior esportivo não usa metrica_derivada canônica');
$assert(!str_contains($service, "preg_match('/corrida"), 'Progress não deve inferir behavior por slug');
$assert(str_contains($service, "'change_percent' => \$percent"), 'Comparação percentual ausente');
$assert(str_contains($service, "\$previous != 0.0"), 'Divisão por zero não está protegida');
$assert(str_contains($service, "'completion_rate'"), 'Completion rate ausente');
$assert(str_contains($service, "'pending_count'") && str_contains($service, "'past_due_count'"), 'Estados de aderência incompletos');
$assert(str_contains($service, 'progress_route_capable'), 'Comparação de distância não verifica route-capable');
$assert(str_contains($service, "'Cache-Control: no-store'") === false, 'Cache HTTP pertence ao transport, não ao serviço');
$assert(str_contains(file_get_contents($root . '/src/function/api_v1.php'), "header('Cache-Control: no-store')"), 'API privada não usa no-store');
foreach (['/progress/overview','/progress/timeseries','/progress/sports','/progress/calendar','/progress/cardio','/progress/strength','/progress/exercises','/progress/exercises/{id}','/progress/adherence','/progress/dashboard'] as $path) {
    $assert(str_contains($openapi, "  {$path}:"), "OpenAPI sem {$path}");
}
foreach (['activities, duration, distance, elevation_gain, training_load, perceived_effort, strength_volume','day, week, month','America/Sao_Paulo','1827','best_load_kg','completion_rate'] as $term) {
    $assert(str_contains($openapi, $term), "OpenAPI sem {$term}");
}
foreach (['Exemplo A','Exemplo B','Exemplo C','Exemplo D','Exemplo E','Exemplo F','Exemplo G','Exemplo H','Exemplo I','Exemplo J'] as $example) {
    $assert(str_contains($doc, $example), "Documento sem {$example}");
}
$assert(str_contains($doc, 'duration_minutes × perceived_effort'), 'Documento não define training load');
$assert(str_contains($doc, 'reps × load_kg'), 'Documento não define volume de força');
$assert(str_contains($doc, '`null`') && str_contains($doc, '`0`'), 'Documento não define null/zero');
$assert(str_contains($migration, 'ix_registros_atividade_progress_user_sport_date'), 'Índice de Activity ausente');
$assert(str_contains($migration, 'ix_series_exercicio_atividade_progress_exercise_record'), 'Índice de exercício ausente');
$assert(str_contains($migration, 'ix_treinos_agendados_progress_athlete_date_status'), 'Índice de planejamento ausente');

echo "✓ Mobile Progress Platform API v1 static\n";
