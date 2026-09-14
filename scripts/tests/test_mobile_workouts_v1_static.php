<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);
$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    $checks++;
    if (!$ok) {
        fwrite(STDERR, "Mobile Workouts v1 failed: {$message}\n");
        exit(1);
    }
};

$domain = $read('src/function/api_workouts.php');
$api = $read('src/function/api_v1.php');
$router = $read('public/api/v1/index.php');
$migration = $read('src/database/migrations/20260914_mobile_workouts_v1.sql');
$snapshotMigration = $read('src/database/migrations/20260914_mobile_workouts_v1_exercise_snapshot.sql');
$docs = $read('docs/MOBILE_API.md');
$openapi = $read('docs/api/openapi.yaml');

foreach (['treinos_agendados', 'treinos_cronograma', 'treinos_modelo', 'sessoes_treino'] as $table) {
    $assert(str_contains($domain, $table), "API deve reutilizar {$table}.");
}
foreach (['stridebr_api_workout_schedule', 'stridebr_api_workout_detail', 'stridebr_api_workout_create', 'stridebr_api_workout_update', 'stridebr_api_workout_cancel', 'stridebr_api_workout_complete'] as $function) {
    $assert(str_contains($domain, 'function ' . $function), "Domínio deve implementar {$function}.");
}
$assert(str_contains($router, "'workouts/schedule'") && str_contains($router, "'workouts/templates'"), 'Router deve expor calendário e biblioteca.');
$assert(str_contains($router, "['complete', 'cancel']"), 'Router deve expor comandos explícitos de conclusão e cancelamento.');
$assert(str_contains($router, 'if ($route === \'workouts\')') && str_contains($router, 'if ($method === \'PATCH\')'), 'Router deve expor criação e edição de treino pessoal.');
$assert(str_contains($domain, "'timezone' => stridebr_api_workout_timezone()") && str_contains($domain, "return 'America/Sao_Paulo';"), 'Calendário deve documentar timezone canônico atual do Core.');
$assert(str_contains($domain, "'time' => \$row['hora_inicio'] !== null"), 'Horário ausente deve permanecer null.');
$assert(str_contains($domain, 'ta.data_treino BETWEEN :from AND :to'), 'Calendário agendado deve usar range query owner-scoped.');
$assert(str_contains($domain, 'cronogramaListarOcorrencias') && str_contains($domain, 'cronogramaConciliarOcorrenciasComRegistros'), 'Recorrência deve reutilizar a engine real do cronograma.');
$assert(str_contains($domain, 'idatleta = :user') && str_contains($domain, 'idusuario = :user'), 'Leituras e vínculos precisam ser ownership-scoped.');
$assert(str_contains($domain, "origem='usuario'") || str_contains($domain, "origem = 'usuario'"), 'Edição deve restringir treino pessoal quando aplicável.');
$assert(str_contains($domain, 'creator_name') && str_contains($domain, "'source' =>"), 'Contrato precisa preservar autoria/origem de prescrição.');
$assert(str_contains($domain, 'treinos_modelo_exercicios') && str_contains($domain, "'template_id'"), 'Criação por template deve reutilizar biblioteca existente.');
$assert(str_contains($domain, 'idagendamento_origem') && str_contains($domain, 'idregistro_atividade'), 'Vínculo de treino agendado com Activity deve reutilizar sessão existente.');
$assert(str_contains($domain, 'idtreino_cronograma') && str_contains($domain, 'data_ocorrencia_origem'), 'Vínculo recorrente com Activity deve reutilizar campos de cronograma existentes.');
$assert(str_contains($api, 'workout_id') && str_contains($api, 'stridebr_api_workout_prepare_activity_link') && str_contains($api, 'stridebr_api_workout_link_activity'), 'POST /activities deve aceitar vínculo opcional a workout.');
$assert(str_contains($api, '$detail[\'workout\'] = null'), 'Detalhe da Activity deve informar vínculo quando existir.');
$assert(str_contains($migration, 'ALTER TABLE treinos_agendados') && str_contains($migration, 'ADD COLUMN IF NOT EXISTS idmodalidade'), 'Migration deve estender a ocorrência existente em vez de criar domínio paralelo.');
$assert(str_contains($migration, 'distancia_prevista_m') && str_contains($migration, 'idtreino_modelo_origem'), 'Migration deve cobrir métricas simples e proveniência de template.');
$assert(str_contains($migration, 'SET search_path TO stridebr, public;'), 'Migration deve manter search_path explícito.');
$assert(!str_contains($migration, 'CREATE TABLE mobile_') && !str_contains($migration, 'workouts_mobile'), 'Migration não pode criar tabelas exclusivas do Mobile.');
$assert(str_contains($snapshotMigration, 'ALTER TABLE treinos_agendados_exercicios') && str_contains($snapshotMigration, 'ADD COLUMN IF NOT EXISTS bloco VARCHAR(40)') && str_contains($snapshotMigration, 'ADD COLUMN IF NOT EXISTS cluster VARCHAR(80)'), 'Migration incremental deve completar bloco/cluster no snapshot agendado.');
$assert(str_contains($snapshotMigration, 'SET search_path TO stridebr, public;') && !str_contains($snapshotMigration, 'UPDATE treinos_agendados_exercicios'), 'Migration de snapshot deve ser aditiva, idempotente e sem backfill especulativo.');
$assert(str_contains($docs, 'MOBILE WORKOUTS V1 CONTRACT') && str_contains($docs, 'GET /workouts/schedule'), 'MOBILE_API deve conter contrato Mobile Workouts v1.');
$assert(str_contains($openapi, '/workouts/schedule:') && str_contains($openapi, '/workouts/{id}:') && str_contains($openapi, '/workouts/templates:'), 'OpenAPI deve documentar calendário, detalhe e biblioteca.');
$assert(str_contains($openapi, 'workout_id:') && str_contains($openapi, 'WorkoutDetail:'), 'OpenAPI deve tipar vínculo Activity e detalhe do treino.');
$assert(str_contains($domain, "'template_id' =>") && str_contains($domain, "'updated_at' => stridebr_api_iso"), 'Summary/detalhe devem expor proveniência de template e updated_at sem carregar estrutura pesada.');
$assert(str_contains($domain, "'session' =>") && str_contains($domain, 'completed.idsessao AS session_id'), 'Detalhe deve expor a sessão executada quando houver ponte real para Activity.');
$assert(str_contains($docs, 'POST /workouts` não possui Idempotency-Key própria nesta v1'), 'Docs devem explicitar a limitação de idempotência de criação sem inventar mecanismo paralelo.');
$assert(str_contains($openapi, 'template_id: { type: string, nullable: true }') && str_contains($openapi, 'updated_at: { type: string, format: date-time, nullable: true }'), 'OpenAPI deve documentar metadados leves de template/atualização.');
$assert(!str_contains($domain, 'RRULE') && !str_contains($domain, 'rrule'), 'API não deve introduzir engine RRULE paralela.');
$assert(!str_contains($domain, "status = 'concluido' WHERE data_treino"), 'Treino passado não pode ser concluído automaticamente.');

printf("✓ mobile workouts v1 static: %d assertions\n", $checks);
