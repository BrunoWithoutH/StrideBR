<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/src/function/api_v1.php';

$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    $checks++;
    if (!$ok) throw new RuntimeException($message);
};
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);

$router = $read('public/api/v1/index.php');
$helper = $read('src/function/api_institutional_surface.php');
$assert(str_contains($router, "(\$parts[0] ?? '') === 'institutional' && !stridebr_teams_enabled()"), 'Rotas institucionais precisam falhar antes do bootstrap quando Teams estiver OFF.');
$assert(strpos($router, "(\$parts[0] ?? '') === 'institutional' && !stridebr_teams_enabled()") < strpos($router, "pg_config.php"), 'Feature OFF precisa responder antes do acesso ao banco/provider.');
foreach (['institutional/context', 'institutional/competitions', "'teams' && \$parts[3] === 'roster'", "'competitions'"] as $needle) {
    $assert(str_contains($router, $needle), "Router institucional precisa conter {$needle}.");
}
$assert(!str_contains($router, 'institutional/workouts'), 'Não pode existir endpoint institutional/workouts duplicado.');
$assert(str_contains($helper, 'stridebr_teams_surface_context(') && str_contains($helper, 'stridebr_teams_surface_roster(') && str_contains($helper, 'stridebr_teams_surface_competitions(') && str_contains($helper, 'stridebr_teams_surface_competition('), 'API institucional precisa delegar ao provider Core existente.');
$assert(!str_contains($helper, 'fixture:') && !str_contains($helper, 'stridebr_teams_surface_fixture('), 'API institucional não pode duplicar fixture.');

$openapi = $read('docs/api/openapi.yaml');
foreach (['/institutional/context:', '/institutional/teams/{team_ref}/roster:', '/institutional/competitions:', '/institutional/competitions/{competition_ref}:'] as $path) $assert(str_contains($openapi, $path), "OpenAPI precisa documentar {$path}.");
foreach (['InstitutionalAthleteContext:', 'InstitutionalTeamContext:', 'InstitutionalRoster:', 'InstitutionalRosterMember:', 'InstitutionalCompetitionSummary:', 'InstitutionalCompetitionDetail:', 'InstitutionalEntry:', 'InstitutionalResult:'] as $schema) $assert(str_contains($openapi, $schema), "OpenAPI precisa conter schema {$schema}.");
$mobileDocs = $read('docs/MOBILE_API.md') . $read('docs/MOBILE_TRAINING_API.md');
$assert(str_contains($mobileDocs, 'GET /institutional/context') && str_contains($mobileDocs, 'GET /institutional/competitions'), 'Docs Mobile precisam registrar a read surface institucional.');
$assert(str_contains($mobileDocs, 'source=teams') && str_contains($mobileDocs, 'kind=institutional'), 'Docs Mobile precisam preservar o contrato único de Workouts institucionais.');
$surfaceDocs = $read('docs/INSTITUTIONAL_ATHLETE_SURFACE.md') . $read('docs/STRIDEBR_TEAMS.md');
$assert(str_contains($surfaceDocs, 'API serializer allowlist') && str_contains($surfaceDocs, '404'), 'Docs institucionais precisam registrar defesa em profundidade e feature OFF invisível.');
$assert(!str_contains($router, '\$_GET[\'person\']') && !str_contains($router, '\$_GET[\'identity_ref\']') && !str_contains($router, '\$_GET[\'username\']'), 'API institucional não pode aceitar viewer arbitrário pela query.');

$context = stridebr_api_institutional_team_context([
    'organization' => ['ref' => 'org:test', 'name' => 'Org', 'workspace_grant' => 'hidden'],
    'team' => ['ref' => 'team:test', 'name' => 'Equipe', 'sport' => 'Atletismo', 'permissions' => ['admin']],
    'season' => ['ref' => 'season:test', 'label' => '2027', 'internal' => 'hidden'],
    'roles' => ['Atleta'], 'group' => 'Fundo', 'membership_status' => 'Ativo', 'email' => 'private@example.invalid',
]);
$contextJson = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
foreach (['workspace_grant', 'permissions', 'internal', 'membership_status', 'email'] as $forbidden) $assert(!str_contains($contextJson, $forbidden), "Context API não pode vazar {$forbidden}.");

$athlete = stridebr_api_institutional_roster_member([
    'person_ref' => 'person:test', 'display_name' => 'Pessoa', 'roles' => ['Atleta'], 'group' => 'Fundo',
    'availability' => 'Indisponível', 'health' => ['injury' => true], 'phone' => 'hidden', 'private_notes' => 'hidden',
], true);
$athleteJson = json_encode($athlete, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
foreach (['availability', 'health', 'injury', 'phone', 'private_notes'] as $forbidden) $assert(!str_contains($athleteJson, $forbidden), "Roster API não pode vazar {$forbidden}.");

$competition = stridebr_api_institutional_competition_summary([
    'competition_ref' => 'competition:test', 'display_name' => 'Teste', 'start_date' => '2027-01-01', 'end_date' => '2027-01-02',
    'delegation_member' => true, 'organization' => ['ref' => 'org:test', 'name' => 'Org', 'billing' => 'hidden'],
    'entries' => [['entry_ref' => 'other-person']], 'organizer_internals' => ['hidden'],
]);
$competitionJson = json_encode($competition, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
foreach (['billing', 'other-person', 'organizer_internals'] as $forbidden) $assert(!str_contains($competitionJson, $forbidden), "Competition summary API não pode vazar {$forbidden}.");

$result = stridebr_api_institutional_result([
    'phase' => 'Final', 'placement_scope' => 'final', 'position' => 2, 'result' => '17:10', 'classification' => '2º', 'medal' => 'Prata',
    'provenance' => ['source_role' => 'participant_organization', 'status' => 'Informado', 'source_organization_id' => 'internal-org'],
    'private_notes' => 'hidden',
]);
$resultJson = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
foreach (['private_notes', 'source_organization_id', 'internal-org'] as $forbidden) $assert(!str_contains($resultJson, $forbidden), "Result API não pode vazar {$forbidden}.");
foreach (['phase', 'placement_scope', 'position', 'result', 'classification', 'medal', 'provenance'] as $allowed) $assert(array_key_exists($allowed, $result ?? []), "Result API precisa permitir {$allowed}.");

$prepend = tempnam(sys_get_temp_dir(), 'stridebr-inst-prepend-');
if ($prepend === false) throw new RuntimeException('Não foi possível criar prepend temporário.');
file_put_contents($prepend, <<<'PHP_PREPEND'
<?php
$GLOBALS['stridebr_teams_surface_provider_calls'] = 0;
register_shutdown_function(static function (): void {
    $file = getenv('STRIDEBR_PROVIDER_COUNT_FILE');
    if (is_string($file) && $file !== '') file_put_contents($file, (string) ($GLOBALS['stridebr_teams_surface_provider_calls'] ?? 0));
});
PHP_PREPEND);
try {
    foreach (['/api/v1/institutional/context', '/api/v1/institutional/competitions', '/api/v1/institutional/teams/team%3Aatletismo-fw/roster?season=season%3A2027', '/api/v1/institutional/competitions/competition%3Ajeif-2027'] as $uri) {
        $countFile = tempnam(sys_get_temp_dir(), 'stridebr-inst-count-');
        if ($countFile === false) throw new RuntimeException('Não foi possível criar count temporário.');
        $command = sprintf(
            'STRIDEBR_APP_ENV=development STRIDEBR_TEAMS_ENABLED=false STRIDEBR_TEAMS_SURFACE_MODE=disabled STRIDEBR_PROVIDER_COUNT_FILE=%s REQUEST_METHOD=GET REQUEST_URI=%s php -d auto_prepend_file=%s %s 2>/dev/null',
            escapeshellarg($countFile), escapeshellarg($uri), escapeshellarg($prepend), escapeshellarg($root . '/public/api/v1/index.php')
        );
        $output = [];
        $exit = 0;
        exec($command, $output, $exit);
        $body = implode("\n", $output);
        $assert($exit === 0 && str_contains($body, '"code":"not_found"'), "Teams OFF precisa esconder {$uri} com 404 lógico.");
        $assert(trim((string) file_get_contents($countFile)) === '0', "Teams OFF não pode consultar provider em {$uri}.");
        @unlink($countFile);
    }
} finally {
    @unlink($prepend);
}

printf("✓ Institutional Athlete API Surface v1 static: %d assertions\n", $checks);
