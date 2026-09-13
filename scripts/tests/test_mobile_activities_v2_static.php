<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);
$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    $checks++;
    if (!$ok) {
        fwrite(STDERR, "Mobile Activities v2 failed: {$message}\n");
        exit(1);
    }
};

$api = $read('src/function/api_v1.php');
$gps = $read('src/function/gps_web.php');
$model = $read('src/function/atividade_modelo.php');
$migration = $read('src/database/migrations/20260913_mobile_activities_v2.sql');
$docs = $read('docs/MOBILE_API.md');
$openapi = $read('docs/api/openapi.yaml');

foreach (['distance_m','duration_s','elevation_gain_m','average_speed_mps','origin_provider','perceived_effort'] as $field) {
    $assert(str_contains($api, "'{$field}'"), "Summary deve expor {$field}.");
}
$assert(str_contains($api, 'WITH page_rows AS') && strpos($api, 'LIMIT :limit OFFSET :offset') < strpos($api, 'LEFT JOIN LATERAL'), 'Paginação deve ocorrer antes dos agregados de métricas.');
$listStart = strpos($api, 'function stridebr_api_list_activities');
$listEnd = strpos($api, 'function stridebr_api_activity_summary');
$listCode = substr($api, $listStart, $listEnd - $listStart);
$assert(!str_contains($listCode, 'r.coordenadas') && !str_contains($listCode, 'pontos_metadata'), 'GET /activities não pode carregar track GPS.');
$assert(str_contains($api, "SELECT modo,coordenadas,pontos_metadata") && str_contains($api, "'route'"), 'Track completo deve pertencer somente ao detalhe.');
foreach (['elevation_min_m','elevation_max_m','active_calories_kcal','total_calories_kcal','route_privacy','segments','equipment','gps'] as $field) {
    $assert(str_contains($api, "'{$field}'"), "Detalhe deve preservar {$field}.");
}
foreach (['altitude_m','accuracy_m','timestamp_ms'] as $field) {
    $assert(str_contains($api, "'{$field}'") && str_contains($gps, "'{$field}'"), "Ponto GPS deve preservar {$field} quando disponível.");
}
foreach (['measured_distance_m','points_received','accuracy_avg_m','accuracy_best_m','accuracy_worst_m'] as $field) {
    $assert(str_contains($api, "'{$field}'"), "Metadados GPS devem expor {$field}.");
}
$assert(str_contains($api, 'WHERE ra.idregistro=:id AND ra.idusuario=:user'), 'Detalhe autenticado precisa continuar ownership-scoped.');
$assert(str_contains($migration, 'ADD COLUMN IF NOT EXISTS pontos_metadata JSONB') && str_contains($migration, 'NUMERIC(12,3)'), 'Migration deve adicionar apenas metadata alinhada e preservar duração subsegundo.');
$assert(!str_contains($migration, 'latitude') && !str_contains($migration, 'longitude'), 'Migration não deve duplicar latitude/longitude já existentes no GeoJSON.');
$assert(str_contains($model, "['!Y-m-d H:i:s', '!Y-m-d H:i']"), 'Persistência deve aceitar segundos novos sem quebrar formulários legados por minuto.');
$assert(str_contains($gps, "format('Y-m-d H:i:s')"), 'Novas gravações GPS devem preservar segundos em início/fim.');
$assert(str_contains($docs, 'GET /activities/{id}') && str_contains($docs, 'distance_m') && str_contains($docs, 'timestamp_ms'), 'MOBILE_API deve documentar detalhe rico e GPS.');
$assert(str_contains($openapi, 'ActivitySummary:') && str_contains($openapi, 'ActivityDetail:') && str_contains($openapi, 'ActivityRoutePoint:'), 'OpenAPI deve tipar summary, detalhe e pontos de rota.');
$assert(str_contains($openapi, 'nullable: true'), 'OpenAPI deve explicitar campos opcionais/nulos.');
$assert(!str_contains($api, "'pace' =>") && !str_contains($api, "'pace_avg' =>"), 'API não deve persistir/emitir pace formatado quando distância e duração bastam.');

printf("✓ mobile activities v2 static: %d assertions\n", $checks);
