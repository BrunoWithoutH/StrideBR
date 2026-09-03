<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$metadata = (string) file_get_contents($root . '/src/database/migrations/20260903_v1_rc.sql');
$foundation = (string) file_get_contents($root . '/src/database/migrations/20260903_v1_rc.sql');
$taxonomy = (string) file_get_contents($root . '/src/database/migrations/20260903_v1_rc.sql');
$performance = (string) file_get_contents($root . '/src/database/migrations/20260903_v1_rc.sql');
$details = (string) file_get_contents($root . '/src/database/migrations/20260903_v1_rc.sql');
$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    $checks++;
    if (!$ok) {
        fwrite(STDERR, "Migration dependency static failed: {$message}\n");
        exit(1);
    }
};

$columnPos = strpos($metadata, 'ADD COLUMN IF NOT EXISTS grupos_musculares_primarios');
$updatePos = strpos($metadata, 'UPDATE exercicios SET grupos_musculares_primarios');
$assert($columnPos !== false && $updatePos !== false && $columnPos < $updatePos, 'metadata muscular precisa criar as colunas antes de utilizá-las.');
$assert(str_contains($metadata, 'ADD COLUMN IF NOT EXISTS grupos_musculares_secundarios'), 'metadata muscular precisa ser autossuficiente para as duas colunas.');
$assert(str_contains($foundation, 'ADD COLUMN IF NOT EXISTS grupos_musculares_primarios') && str_contains($foundation, 'CREATE TABLE IF NOT EXISTS series_exercicio_atividade'), 'foundation continua idempotente quando executada depois da metadata.');

$assert(str_contains($taxonomy, 'tmp_sport_taxonomy_model_upgrade') && str_contains($taxonomy, 'idmodelo_anterior') && str_contains($taxonomy, 'EXISTS (SELECT 1 FROM registros_atividade ra WHERE ra.idmodelo = mm.idmodelo)'), 'taxonomia precisa versionar modelos históricos antes de alterar seus campos.');
$assert(strpos($taxonomy, 'tmp_sport_taxonomy_model_upgrade') < strpos($taxonomy, "':fc-media'"), 'versionamento histórico precisa acontecer antes dos novos campos da taxonomia.');
$assert(str_contains($performance, 'tmp_sport_performance_model_upgrade') && strpos($performance, 'tmp_sport_performance_model_upgrade') < strpos($performance, "':fc-maxima'"), 'métricas esportivas precisam versionar modelos históricos antes de acrescentar campos.');
$assert(str_contains($details, 'tmp_sport_details_model_upgrade') && strpos($details, 'tmp_sport_details_model_upgrade') < strpos($details, 'WITH defs('), 'detalhes de sessão precisam versionar modelos históricos antes de acrescentar campos/opções.');

$rcMigration = $root . '/src/database/migrations/20260903_v1_rc.sql';
$assert(is_file($rcMigration), 'a release candidate precisa ter uma única migration consolidada pós-alpha.');

printf("✓ migration dependency static: %d assertions\n", $checks);
