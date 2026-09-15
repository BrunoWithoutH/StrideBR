<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/src/function/teams_surface_provider.php';
require_once $root . '/src/function/api_workouts.php';

if (!function_exists('stridebr_api_bool')) {
    function stridebr_api_bool(mixed $value): bool { return $value === true || $value === 1 || $value === '1' || $value === 'true' || $value === 't'; }
}

$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    $checks++;
    if (!$ok) {
        fwrite(STDERR, "Institutional Athlete Surface v1 static failed: {$message}\n");
        exit(1);
    }
};
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);
$oldEnv = getenv('STRIDEBR_APP_ENV');
$oldEnabled = getenv('STRIDEBR_TEAMS_ENABLED');
$oldMode = getenv('STRIDEBR_TEAMS_SURFACE_MODE');
$restore = static function () use ($oldEnv, $oldEnabled, $oldMode): void {
    putenv($oldEnv === false ? 'STRIDEBR_APP_ENV' : 'STRIDEBR_APP_ENV=' . $oldEnv);
    putenv($oldEnabled === false ? 'STRIDEBR_TEAMS_ENABLED' : 'STRIDEBR_TEAMS_ENABLED=' . $oldEnabled);
    putenv($oldMode === false ? 'STRIDEBR_TEAMS_SURFACE_MODE' : 'STRIDEBR_TEAMS_SURFACE_MODE=' . $oldMode);
};

try {
    putenv('STRIDEBR_APP_ENV=development');
    putenv('STRIDEBR_TEAMS_ENABLED=false');
    putenv('STRIDEBR_TEAMS_SURFACE_MODE=fixture');
    stridebr_teams_surface_provider_reset_call_count();
    $availability = stridebr_teams_surface_availability();
    $assert($availability['enabled'] === false && $availability['available'] === false, 'feature flag OFF deve vencer qualquer surface mode.');
    $assert(stridebr_teams_surface_provider_dispatch('athlete_context', ['identity_ref' => 'fixture:bruno-evaristo']) === null, 'provider não pode servir fixture com feature OFF.');
    $assert(stridebr_teams_surface_provider_call_count() === 0, 'feature OFF não pode consultar provider institucional.');

    putenv('STRIDEBR_TEAMS_ENABLED=true');
    putenv('STRIDEBR_TEAMS_SURFACE_MODE=fixture');
    stridebr_teams_surface_provider_reset_call_count();
    $availability = stridebr_teams_surface_availability();
    $assert($availability['available'] === true && $availability['mode'] === 'fixture', 'fixture deve estar disponível em development quando flag ON.');
    $context = stridebr_teams_surface_provider_dispatch('athlete_context', ['identity_ref' => 'fixture:bruno-evaristo']);
    $team = $context['my_teams'][0] ?? [];
    $assert(($team['team']['name'] ?? '') === 'Atletismo', 'fixture Bruno deve projetar Atletismo.');
    $assert(str_contains((string) ($team['organization']['name'] ?? ''), 'IF Farroupilha'), 'fixture Bruno deve projetar IF Farroupilha.');
    $assert(in_array('Atleta', $team['roles'] ?? [], true), 'fixture Bruno deve projetar papel Atleta.');
    $assert(($team['group'] ?? null) === 'Fundo', 'fixture Bruno deve projetar grupo Fundo.');

    $roster = stridebr_teams_surface_provider_dispatch('team_roster', ['identity_ref' => 'fixture:bruno-evaristo', 'team_ref' => 'team:atletismo-fw', 'season_ref' => 'season:2027']);
    $assert(is_array($roster) && count($roster['athletes'] ?? []) >= 1 && count($roster['staff'] ?? []) >= 1, 'roster deve separar athletes e staff.');
    $keys = [];
    $collectKeys = static function (array $value) use (&$collectKeys, &$keys): void {
        foreach ($value as $key => $child) {
            if (is_string($key)) $keys[] = strtolower($key);
            if (is_array($child)) $collectKeys($child);
        }
    };
    $collectKeys($roster);
    foreach (['email', 'telefone', 'phone', 'address', 'birth_date', 'membership_status', 'workspace_grant', 'availability', 'lesao', 'dor', 'fadiga', 'sono', 'readiness', 'gps', 'heart_rate', 'route', 'private_notes'] as $forbidden) {
        $assert(!in_array($forbidden, $keys, true), "roster não pode transportar {$forbidden}.");
    }
    $assert(stridebr_teams_surface_provider_dispatch('team_roster', ['identity_ref' => 'fixture:bruno-evaristo', 'team_ref' => 'team:voleibol-fw', 'season_ref' => 'season:2027']) === null, 'Bruno não pode abrir roster de outra Team.');

    $trainings = stridebr_teams_surface_provider_dispatch('trainings', ['identity_ref' => 'fixture:bruno-evaristo', 'from' => '2027-03-01', 'to' => '2027-03-31']);
    $assert(count($trainings) === 2, 'projection deve retornar somente prescriptions publicadas destinadas ao Bruno no range.');
    foreach ($trainings as $training) {
        $assert(($training['status'] ?? '') === 'publicado', 'provider não pode projetar Draft.');
        $assert(($training['recipient_identity_ref'] ?? '') === 'fixture:bruno-evaristo', 'training deve ser recipient-scoped.');
    }
    $assert(stridebr_teams_surface_provider_dispatch('training_detail', ['identity_ref' => 'fixture:bruno-evaristo', 'training_ref' => 'training:private:other-athlete']) === null, 'detail de training de outro atleta não pode vazar existência.');
    $competitions = stridebr_teams_surface_provider_dispatch('competitions', ['identity_ref' => 'fixture:bruno-evaristo']);
    $competitionNames = array_map(static fn(array $row): string => (string) ($row['display_name'] ?? ''), is_array($competitions) ? $competitions : []);
    foreach (['JEIF 2027', 'JIFSul 2027', 'JIF Nacional 2027'] as $name) $assert(in_array($name, $competitionNames, true), "fixture precisa projetar {$name}.");

    $parsed = stridebr_api_workout_parse_id('teams:training:atletismo:2027:rodagem-8k');
    $assert(($parsed['kind'] ?? '') === 'institutional', 'parser deve reconhecer kind institutional.');
    $assert(($parsed['ref'] ?? '') === 'training:atletismo:2027:rodagem-8k', 'parser deve preservar tudo após teams: como opaque ref.');
    $capabilities = stridebr_api_workout_capabilities([
        'source' => 'teams', 'status' => 'publicado', 'session' => null, 'activity' => null,
        'capabilities' => ['can_start_session' => true, 'can_start_gps' => true, 'can_quick_register' => true, 'can_complete_manually' => true],
    ]);
    $assert($capabilities['can_start_session'] === true, 'Teams pode iniciar session somente quando explicitamente autorizado.');
    foreach (['can_edit', 'can_reschedule', 'can_cancel', 'can_delete', 'can_complete_manually', 'can_quick_register', 'can_start_gps'] as $blocked) {
        $assert(($capabilities[$blocked] ?? true) === false, "capability {$blocked} deve permanecer bloqueada para Teams v1.");
    }

    $ack = stridebr_teams_execution_ack_payload([
        'referencia_externa' => 'training:atletismo:2027:rodagem-8k',
        'recipient_ref_externo' => 'recipient:bruno:rodagem-8k',
        'data_inicio' => '2027-03-15T17:30:00-03:00',
        'data_fim' => '2027-03-15T18:20:00-03:00',
        'gps' => 'forbidden', 'heart_rate' => 'forbidden', 'route' => 'forbidden', 'private_notes' => 'forbidden',
    ], 'completed', 'activity:opaque');
    $assert(array_keys($ack) === ['training_ref', 'recipient_ref', 'status', 'started_at', 'completed_at', 'activity_ref'], 'ack deve usar allowlist mínima e estável.');
    $ackJson = json_encode($ack) ?: '';
    foreach (['gps', 'heart_rate', 'route', 'private_notes'] as $forbidden) $assert(!str_contains($ackJson, $forbidden), "ack não pode conter {$forbidden}.");

    putenv('STRIDEBR_APP_ENV=production');
    stridebr_teams_surface_provider_reset_call_count();
    $availability = stridebr_teams_surface_availability();
    $assert($availability['available'] === false && $availability['state'] === 'unavailable', 'fixture precisa ser recusada em production.');
    $assert(stridebr_teams_surface_provider_dispatch('athlete_context', ['identity_ref' => 'fixture:bruno-evaristo']) === null, 'production não pode expor fixture.');
    $assert(stridebr_teams_surface_provider_call_count() === 0, 'fixture recusada em production não pode consultar adapter demonstrativo.');

    $envExample = $read('.env.example');
    $assert(str_contains($envExample, 'STRIDEBR_TEAMS_ENABLED=false') && str_contains($envExample, 'STRIDEBR_TEAMS_SURFACE_MODE=disabled'), 'env example deve nascer com integração escondida e disabled.');
    $provider = $read('src/function/teams_surface_provider.php');
    $assert(str_contains($provider, "if (!stridebr_teams_enabled()) return"), 'provider facade deve falhar cedo com feature OFF.');
    $assert(str_contains($provider, 'stridebr_teams_surface_safe_dispatch'), 'provider boundary deve absorver falha futura do transporte.');
    $header = $read('src/layout/header.php');
    $home = $read('public/home.php');
    $agenda = $read('public/user/agenda-mensal.php');
    $competitionPage = $read('public/user/competicoes.php');
    foreach ([$header, $home, $agenda, $competitionPage] as $surface) $assert(str_contains($surface, 'stridebr_teams_enabled()'), 'cada surface compartilhada precisa estar atrás da feature flag.');
    foreach (['public/user/equipes.php', 'public/user/equipe.php', 'public/user/treino-institucional.php', 'public/user/competicao-institucional.php'] as $page) {
        $content = $read($page);
        $assert(str_contains($content, 'stridebr_teams_enabled()') && str_contains($content, '404'), "{$page} deve responder 404 com feature OFF.");
    }
    $workoutApi = $read('src/function/api_workouts.php');
    $assert(str_contains($workoutApi, "'source' => 'teams'") && str_contains($workoutApi, "'kind' => 'institutional'"), 'workout institucional precisa source=teams e kind=institutional.');
    $assert(str_contains($workoutApi, 'stridebr_teams_surface_trainings($pdo, $userId, $from, $to)'), 'schedule deve solicitar ao provider apenas o range atual.');
    $assert(str_contains($workoutApi, "if (stridebr_teams_enabled())"), 'schedule precisa evitar provider quando feature OFF.');
    $sessionService = $read('src/function/workout_session_service.php');
    $assert(str_contains($sessionService, 'function sessaoIniciarInstitucional') && str_contains($sessionService, "'teams'"), 'Workout Session precisa preservar origem externa Teams.');
    $migration = $read('src/database/migrations/20260915_institutional_athlete_surface_v1.sql');
    foreach (['origem_externa', 'referencia_externa', 'recipient_ref_externo', 'contexto_institucional_snapshot'] as $column) $assert(str_contains($migration, $column), "migration precisa adicionar {$column}.");
    $openapi = $read('docs/api/openapi.yaml');
    $assert(str_contains($openapi, 'enum: [usuario, treinador, cronograma, teams]'), 'OpenAPI precisa incluir source teams.');
    $assert(str_contains($openapi, 'enum: [scheduled, recurring, institutional]'), 'OpenAPI precisa incluir kind institutional.');
    $assert(str_contains($openapi, 'InstitutionalContext:'), 'OpenAPI precisa documentar institutional_context opcional.');
} finally {
    $restore();
}

printf("✓ Institutional Athlete Surface v1 static: %d assertions\n", $checks);
