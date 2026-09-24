<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/src/function/workout_prescription_v2.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
};
$same = static function (mixed $expected, mixed $actual, string $message) use (&$assertions): void {
    $assertions++;
    if ($expected !== $actual) throw new RuntimeException($message . ' esperado=' . var_export($expected, true) . ' recebido=' . var_export($actual, true));
};
$throws = static function (callable $callback, string $message) use (&$assertions): void {
    $assertions++;
    try { $callback(); } catch (InvalidArgumentException) { return; }
    throw new RuntimeException($message);
};
$read = static fn(string $path): string => file_get_contents($root . '/' . $path) ?: '';

$standard = workoutPrescriptionNormalize(['prescription' => ['method'=>'standard','sets'=>4,'reps'=>['mode'=>'range','min'=>8,'max'=>12],'load'=>['value'=>30,'unit'=>'kg'],'rest_after_s'=>90]]);
$same('standard', $standard['method'], 'Standard precisa manter método.');
$same(['mode'=>'range','min'=>8,'max'=>12], $standard['config']['reps'], 'Faixa precisa manter min/max separados.');
$same('4 × 8–12 · 30 kg · 90 s', workoutPrescriptionSummary(['metodo_prescricao'=>'standard','config_prescricao'=>workoutPrescriptionJson($standard['config'])]), 'Summary standard precisa ser compacto.');

$amrap = workoutPrescriptionNormalize(['prescription'=>['method'=>'standard','sets'=>3,'reps'=>['mode'=>'amrap']]]);
$same('amrap', $amrap['config']['reps']['mode'], 'AMRAP precisa ser target e não método.');
$same('3 × AMRAP', workoutPrescriptionSummary(['metodo_prescricao'=>'standard','config_prescricao'=>workoutPrescriptionJson($amrap['config'])]), 'AMRAP precisa aparecer no summary.');
$failure = workoutPrescriptionNormalize(['prescription'=>['method'=>'standard','sets'=>1,'reps'=>['mode'=>'failure']]]);
$same('failure', $failure['config']['reps']['mode'], 'Failure precisa ser target.');

$cluster = workoutPrescriptionNormalize(['prescription'=>['method'=>'cluster','blocks'=>3,'clusters'=>[['reps'=>4],['reps'=>4],['reps'=>3]],'load'=>['value'=>80,'unit'=>'kg'],'intra_cluster_rest_s'=>20,'between_blocks_rest_s'=>120]]);
$same([4,4,3], array_column($cluster['config']['clusters'], 'reps'), 'Cluster irregular precisa sobreviver normalização.');
$clusterSets = workoutPrescriptionMaterializeSets(['metodo_prescricao'=>'cluster','config_prescricao'=>workoutPrescriptionJson($cluster['config'])]);
$same(9, count($clusterSets), 'Cluster 3×3 precisa materializar nove microsets.');
$same('cluster', $clusterSets[0]['segment_type'], 'Microset precisa carregar tipo cluster.');
$same(1, $clusterSets[0]['block_index'], 'Microset precisa carregar bloco.');
$same(3, $clusterSets[2]['stage_index'], 'Microset precisa carregar etapa.');
$same(120, $clusterSets[2]['rest_after_s'], 'Último microset do bloco precisa usar descanso entre blocos.');

$drop = workoutPrescriptionNormalize(['prescription'=>['method'=>'drop_set','rounds'=>1,'stages'=>[
    ['load'=>['value'=>40,'unit'=>'kg'],'reps'=>['mode'=>'fixed','value'=>10]],
    ['load'=>['value'=>30,'unit'=>'kg'],'reps'=>['mode'=>'fixed','value'=>8]],
    ['load'=>['value'=>20,'unit'=>'kg'],'reps'=>['mode'=>'amrap']],
],'round_rest_s'=>120]]);
$same(3, count($drop['config']['stages']), 'Drop set precisa manter três etapas.');
$same('amrap', $drop['config']['stages'][2]['reps']['mode'], 'Última queda precisa manter AMRAP.');
$same('40×10 → 30×8 → 20×AMRAP', workoutPrescriptionSummary(['metodo_prescricao'=>'drop_set','config_prescricao'=>workoutPrescriptionJson($drop['config'])]), 'Summary drop precisa manter ordem das etapas.');
$dropSets = workoutPrescriptionMaterializeSets(['metodo_prescricao'=>'drop_set','config_prescricao'=>workoutPrescriptionJson($drop['config'])]);
$same('drop_stage', $dropSets[2]['segment_type'], 'Drop precisa materializar stage explícito.');
$same('AMRAP', $dropSets[2]['planned_repetitions'], 'AMRAP planejado não pode virar zero.');

$same('m', workoutPrescriptionDefaultDistanceUnit('lancamento-de-dardo', 'distance', 'exercise'), 'Dardo precisa usar metros.');
$same('km', workoutPrescriptionDefaultDistanceUnit('corrida', 'duration_distance', 'exercise'), 'Corrida longa precisa usar km por padrão.');
$same('m', workoutPrescriptionDefaultDistanceUnit('corrida', 'duration_distance', 'interval_group'), 'Intervalo de pista precisa preferir metros.');
$same('54.73 m', workoutPrescriptionFormatDistance(54.73, 'm'), 'Marca de lançamento precisa manter centímetro.');
$same(400.0, workoutPrescriptionDistanceMeters('0.4', 'km'), 'Conversão km→m precisa converter magnitude.');

$legacy = workoutPrescriptionNormalize(['repeticoes'=>'pirâmide antiga','carga'=>'elástico forte','duracao'=>'20mn','distancia'=>'400 m','prescription'=>['method'=>'standard','sets'=>4,'reps'=>['mode'=>'legacy','text'=>'pirâmide antiga'],'load'=>['value'=>'','unit'=>'kg'],'duration'=>['value'=>'','unit'=>'min'],'distance'=>['value'=>'','unit'=>'m'],'effort'=>['type'=>'','value'=>'']]]);
$same('legacy', $legacy['config']['reps']['mode'], 'Rep legacy desconhecida precisa sobreviver.');
$same('pirâmide antiga', $legacy['config']['reps']['text'], 'Texto legacy precisa ser preservado.');
$same('elástico forte', $legacy['config']['load']['text'], 'Carga legacy não numérica precisa ser preservada.');
$same(1200, $legacy['config']['duration_s'], 'Legacy 20mn precisa continuar duração.');
$same(400.0, $legacy['config']['distance_m'], 'Legacy 400m precisa continuar distância.');
$throws(fn()=>workoutPrescriptionNormalize(['prescription'=>['method'=>'standard','sets'=>3,'hack'=>'x']]), 'Config nova precisa rejeitar propriedade arbitrária.');
$throws(fn()=>workoutPrescriptionNormalize(['prescription'=>['method'=>'drop_set','rounds'=>1,'stages'=>[['reps'=>['mode'=>'fixed','value'=>10],'html'=>'x'],['reps'=>['mode'=>'amrap']]]]]), 'Stage precisa rejeitar propriedade arbitrária.');

$builder = $read('src/layout/workout_builder.php');
$builderJs = $read('public/assets/js/workout-builder.js');
$sessionJs = $read('public/assets/js/workout-session.js');
$cronPage = $read('public/user/exercicioscronograma.php');
$modelPage = $read('public/user/exerciciostreinomodelo.php');
$migration = $read('src/database/migrations/20260923_workout_builder_v2.sql');
$apiSession = $read('src/function/api_workout_sessions.php');
$apiWorkouts = $read('src/function/api_workouts.php');
$openapi = $read('docs/api/openapi.yaml');
$trainer = $read('src/function/treinador.php');
$strengthActivity = $read('src/function/strength_activity.php');
$prescriptionDomain = $read('src/function/workout_prescription_v2.php');

$assert(str_contains($cronPage, "src/layout/workout_builder.php") && str_contains($modelPage, "src/layout/workout_builder.php"), 'Cronograma e modelo precisam usar a mesma primitive.');
$assert(!str_contains($cronPage, 'cronogramaListarExerciciosBiblioteca($pdo, $idUsuario)'), 'Page load do builder não deve carregar catálogo inteiro.');
$assert(str_contains($builderJs, '/api/exercicio-resolver.php?name='), 'Picker precisa reutilizar resolver da Exercise Library.');
$assert(str_contains($builder, 'data-wb-move="up"') && str_contains($builder, 'data-wb-move="down"') && str_contains($builderJs, "button.dataset.wbMove"), 'Reorder precisa ter alternativa sem drag.');
$assert(str_contains($builderJs, "event.altKey") && str_contains($builderJs, "ArrowUp") && str_contains($builderJs, "ArrowDown"), 'Reorder precisa ter caminho de teclado.');
$assert(str_contains($builderJs, 'data-wb-live') && str_contains($builderJs, 'workout_builder.reorder_announcement'), 'Reorder precisa anunciar posição.');
$assert(str_contains($builder, 'data-wb-method-panel="cluster"') && str_contains($builder, 'data-wb-drop-stage'), 'Builder precisa renderizar Cluster e Drop estruturados.');
$assert(str_contains($builder, 'data-wb-rep-mode') && str_contains($builder, "['fixed','range','amrap','failure']"), 'Rep target precisa ser camada própria.');
$assert(str_contains($builder, 'data-wb-group') && str_contains($cronPage, 'data-wb-create-group="superset"') && str_contains($cronPage, 'data-wb-create-group="circuit"') && str_contains($modelPage, 'data-wb-create-group="superset"') && str_contains($modelPage, 'data-wb-create-group="circuit"') && str_contains($builderJs, 'createGroup(button.dataset.wbCreateGroup)'), 'Superset/Circuit precisam ser grupos estruturados nas duas superfícies.');
$assert(str_contains($sessionJs, 'segmentInfo') && str_contains($sessionJs, 'session-prescription-group') && str_contains($sessionJs, 'workoutPrescription?.structured?.(exercise)'), 'Execution Web precisa entender métodos e grupos estruturados.');
$assert(str_contains($migration, 'CREATE TABLE IF NOT EXISTS grupos_prescricao') && str_contains($migration, 'config_prescricao JSONB') && str_contains($migration, 'segmento_tipo'), 'Migration precisa ter grupos/config/segmentos estruturados.');
$assert(str_contains($apiSession, "'structured_prescription'") && str_contains($apiSession, "'client_capability' => 'structured_prescription_v1'"), 'API de sessão precisa declarar capability estruturada.');
$assert(str_contains($apiSession, "'segment' =>") && str_contains($apiSession, "'rep_target' =>"), 'API de sessão precisa expor metadata de execução.');
$assert(str_contains($apiWorkouts, "'prescription_method' =>") && str_contains($apiWorkouts, "'prescription' =>") && str_contains($apiWorkouts, "'group' =>"), 'Workout DTO precisa expor structured prescription aditiva.');
$assert(str_contains($openapi, 'WorkoutPrescriptionCluster:') && str_contains($openapi, 'WorkoutPrescriptionDropSet:') && str_contains($openapi, 'WorkoutPrescriptionGroup:'), 'OpenAPI precisa congelar methods e groups estruturados.');
$assert(str_contains($openapi, 'WorkoutSessionSegment:') && str_contains($openapi, 'structured_prescription_v1'), 'OpenAPI precisa expor segmentos e capability do cliente.');
$assert(str_contains($openapi, 'ActivityStrengthPlannedTarget:') && str_contains($openapi, 'planned_target:') && str_contains($openapi, 'prescription_method: { type: string, enum: [standard, cluster, drop_set]'), 'OpenAPI do Activity Detail precisa documentar método, segmento e target planejado estruturados.');
$assert(str_contains($read('src/function/api_mobile_completion.php'), "'planned_target' =>") && str_contains($read('src/function/api_mobile_completion.php'), "'prescription_method' =>"), 'Activity Detail precisa emitir structured prescription opcional sem remover actuals.');
$assert(str_contains($trainer, "array_key_exists('prescription', \$row)") && str_contains($trainer, "\$normalizedRow['metodo_prescricao']") && str_contains($trainer, "'grupo_chave'"), 'Criação do treinador precisa preservar structured prescription e grupo.');
$assert(str_contains($trainer, 'workoutDefinitionWriteScheduledItemsBatch') && str_contains($read('src/function/workout_definition.php'), 'workoutPrescriptionReplaceGroupsBatch'), 'Materialização Coach precisa usar o materializer canônico com grupos em batch.');
$assert(str_contains($strengthActivity, "'segmento_tipo'") && str_contains($strengthActivity, "'meta_planejada'"), 'Histórico de Activity precisa manter semântica dos segmentos.');
$assert(str_contains($builderJs, 'dissolveGroup(group)') && str_contains($builderJs, 'members.length < 2'), 'Grupo com menos de dois exercícios precisa ser dissolvido.');

$copySources = [$read('src/function/cronograma.php'), $read('src/function/training_platform_service.php'), $read('src/function/treinador.php'), $read('src/function/workout_session_service.php')];
foreach ($copySources as $source) $assert(str_contains($source, 'metodo_prescricao') && str_contains($source, 'config_prescricao'), 'Copy path precisa preservar método/config.');

$assert(!preg_match('/<select[^>]*>[^<]*(?:652|exercicios)/i', $cronPage), 'Builder não deve embutir catálogo gigante em select.');
$assert(str_contains($cronPage, 'wb-custom-fields-admin'), 'Campos personalizados precisam ficar em disclosure secundário.');

echo "Workout Builder V2 static/domain: {$assertions} assertions\n";
