<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/environment.php';

function stridebr_teams_env_bool(string $name, bool $default = false): bool
{
    $raw = getenv($name);
    if ($raw === false || trim((string) $raw) === '') return $default;
    return in_array(strtolower(trim((string) $raw)), ['1', 'true', 'yes', 'on'], true);
}

function stridebr_teams_enabled(): bool
{
    return stridebr_teams_env_bool('STRIDEBR_TEAMS_ENABLED', false);
}

function stridebr_teams_surface_mode(): string
{
    $mode = strtolower(trim((string) (getenv('STRIDEBR_TEAMS_SURFACE_MODE') ?: 'disabled')));
    return in_array($mode, ['disabled', 'fixture', 'remote'], true) ? $mode : 'disabled';
}

function stridebr_teams_fixture_allowed(): bool
{
    return stridebr_is_development();
}

function stridebr_teams_surface_availability(): array
{
    if (!stridebr_teams_enabled()) return ['enabled' => false, 'available' => false, 'state' => 'disabled', 'mode' => 'disabled'];
    $mode = stridebr_teams_surface_mode();
    if ($mode === 'disabled') return ['enabled' => true, 'available' => false, 'state' => 'unavailable', 'mode' => $mode];
    if ($mode === 'fixture' && !stridebr_teams_fixture_allowed()) return ['enabled' => true, 'available' => false, 'state' => 'unavailable', 'mode' => $mode];
    if ($mode === 'remote') return ['enabled' => true, 'available' => false, 'state' => 'not_implemented', 'mode' => $mode];
    return ['enabled' => true, 'available' => true, 'state' => 'available', 'mode' => $mode];
}

function stridebr_teams_surface_provider_call_count(): int
{
    return (int) ($GLOBALS['stridebr_teams_surface_provider_calls'] ?? 0);
}

function stridebr_teams_surface_provider_reset_call_count(): void
{
    $GLOBALS['stridebr_teams_surface_provider_calls'] = 0;
}

function stridebr_teams_surface_fixture_identity_map(): array
{
    return ['brunowithouth' => 'fixture:bruno-evaristo'];
}

function stridebr_teams_surface_identity_for_core_user(PDO $pdo, string $userId): ?string
{
    if (!stridebr_teams_enabled()) return null;
    $stmt = $pdo->prepare("SELECT username FROM usuarios WHERE idusuario = :id AND statususuario = 'Ativo' LIMIT 1");
    $stmt->execute([':id' => $userId]);
    $username = strtolower(trim((string) ($stmt->fetchColumn() ?: '')));
    if ($username === '') return null;
    return stridebr_teams_surface_fixture_identity_map()[$username] ?? null;
}

function stridebr_teams_surface_string_list(mixed $value): array
{
    if (!is_array($value)) return [];
    $items = [];
    foreach ($value as $item) {
        $item = trim((string) $item);
        if ($item !== '' && !in_array($item, $items, true)) $items[] = $item;
    }
    return $items;
}

function stridebr_teams_surface_named_ref(mixed $value, string $nameKey = 'name'): ?array
{
    if (!is_array($value)) return null;
    $ref = trim((string) ($value['ref'] ?? ''));
    $name = trim((string) ($value[$nameKey] ?? ''));
    if ($ref === '' && $name === '') return null;
    return ['ref' => $ref !== '' ? $ref : null, $nameKey => $name !== '' ? $name : null];
}

function stridebr_teams_surface_team_projection(mixed $value): ?array
{
    if (!is_array($value)) return null;
    $team = stridebr_teams_surface_named_ref($value);
    if ($team === null) return null;
    $sport = trim((string) ($value['sport'] ?? ''));
    if ($sport !== '') $team['sport'] = $sport;
    return $team;
}

function stridebr_teams_surface_season_projection(mixed $value): ?array
{
    if (!is_array($value)) return null;
    $ref = trim((string) ($value['ref'] ?? ''));
    $label = trim((string) ($value['label'] ?? ''));
    if ($ref === '' && $label === '') return null;
    return ['ref' => $ref !== '' ? $ref : null, 'label' => $label !== '' ? $label : null];
}

function stridebr_teams_surface_context_projection(mixed $value): ?array
{
    if (!is_array($value)) return null;
    $organization = stridebr_teams_surface_named_ref($value['organization'] ?? null);
    $team = stridebr_teams_surface_team_projection($value['team'] ?? null);
    $season = stridebr_teams_surface_season_projection($value['season'] ?? null);
    if ($organization === null || $team === null || $season === null) return null;
    $group = trim((string) ($value['group'] ?? ''));
    return [
        'organization' => $organization,
        'team' => $team,
        'season' => $season,
        'roles' => stridebr_teams_surface_string_list($value['roles'] ?? []),
        'group' => $group !== '' ? $group : null,
    ];
}

function stridebr_teams_surface_athlete_context_projection(mixed $value): ?array
{
    if (!is_array($value)) return null;
    $teams = [];
    foreach ((array) ($value['my_teams'] ?? []) as $team) {
        $projected = stridebr_teams_surface_context_projection($team);
        if ($projected !== null) $teams[] = $projected;
    }
    $personRef = trim((string) ($value['person_ref'] ?? ''));
    $seasonRef = trim((string) ($value['season_ref'] ?? ''));
    return ['person_ref' => $personRef !== '' ? $personRef : null, 'season_ref' => $seasonRef !== '' ? $seasonRef : null, 'my_teams' => $teams];
}

function stridebr_teams_surface_roster_member_projection(mixed $value, bool $includeGroup): ?array
{
    if (!is_array($value)) return null;
    $personRef = trim((string) ($value['person_ref'] ?? ''));
    $displayName = trim((string) ($value['display_name'] ?? ''));
    if ($personRef === '' || $displayName === '') return null;
    $result = ['person_ref' => $personRef, 'display_name' => $displayName, 'roles' => stridebr_teams_surface_string_list($value['roles'] ?? [])];
    if ($includeGroup) {
        $group = trim((string) ($value['group'] ?? ''));
        $result['group'] = $group !== '' ? $group : null;
    }
    return $result;
}

function stridebr_teams_surface_roster_projection(mixed $value): ?array
{
    if (!is_array($value)) return null;
    $athletes = [];
    foreach ((array) ($value['athletes'] ?? []) as $member) {
        $projected = stridebr_teams_surface_roster_member_projection($member, true);
        if ($projected !== null) $athletes[] = $projected;
    }
    $staff = [];
    foreach ((array) ($value['staff'] ?? []) as $member) {
        $projected = stridebr_teams_surface_roster_member_projection($member, false);
        if ($projected !== null) $staff[] = $projected;
    }
    return ['athletes' => $athletes, 'staff' => $staff];
}

function stridebr_teams_surface_target_projection(mixed $value): ?array
{
    if (!is_array($value)) return null;
    $result = [];
    foreach (['type', 'unit'] as $key) {
        $text = trim((string) ($value[$key] ?? ''));
        if ($text !== '') $result[$key] = $text;
    }
    foreach (['min', 'max', 'value'] as $key) if (isset($value[$key]) && is_numeric($value[$key])) $result[$key] = $value[$key] + 0;
    return $result !== [] ? $result : null;
}

function stridebr_teams_surface_structure_projection(mixed $value): array
{
    $steps = [];
    foreach ((array) $value as $index => $step) {
        if (!is_array($step)) continue;
        $name = trim((string) ($step['name'] ?? ''));
        if ($name === '') continue;
        $projected = ['step_type' => trim((string) ($step['step_type'] ?? 'work')) ?: 'work', 'name' => $name];
        foreach (['duration_s', 'distance_m', 'sets', 'rest_s', 'repeat_count', 'order'] as $key) if (isset($step[$key]) && is_numeric($step[$key])) $projected[$key] = $step[$key] + 0;
        if (isset($step['repetitions'])) $projected['repetitions'] = trim((string) $step['repetitions']);
        $target = stridebr_teams_surface_target_projection($step['target'] ?? null);
        if ($target !== null) $projected['target'] = $target;
        if (!isset($projected['order'])) $projected['order'] = $index + 1;
        $steps[] = $projected;
    }
    return $steps;
}

function stridebr_teams_surface_capabilities_projection(mixed $value): array
{
    $value = is_array($value) ? $value : [];
    return [
        'can_start_session' => !empty($value['can_start_session']),
        'can_start_gps' => !empty($value['can_start_gps']),
        'can_quick_register' => !empty($value['can_quick_register']),
        'can_complete_manually' => !empty($value['can_complete_manually']),
    ];
}

function stridebr_teams_surface_permissions_projection(mixed $value): array
{
    $value = is_array($value) ? $value : [];
    return [
        'can_edit' => !empty($value['can_edit']),
        'can_reschedule' => !empty($value['can_reschedule']),
        'can_cancel' => !empty($value['can_cancel']),
        'can_delete' => !empty($value['can_delete']),
    ];
}

function stridebr_teams_surface_training_projection(mixed $value): ?array
{
    if (!is_array($value)) return null;
    $trainingRef = trim((string) ($value['training_ref'] ?? ''));
    $recipientRef = trim((string) ($value['recipient_ref'] ?? ''));
    $title = trim((string) ($value['title'] ?? ''));
    $date = trim((string) ($value['date'] ?? ''));
    if ($trainingRef === '' || $recipientRef === '' || $title === '' || $date === '') return null;
    $planningBlock = stridebr_teams_surface_named_ref($value['planning_block'] ?? null);
    $competition = stridebr_teams_surface_named_ref($value['competition'] ?? null);
    return [
        'training_ref' => $trainingRef,
        'recipient_ref' => $recipientRef,
        'title' => $title,
        'date' => $date,
        'time' => trim((string) ($value['time'] ?? '')) ?: null,
        'status' => trim((string) ($value['status'] ?? '')),
        'sport' => trim((string) ($value['sport'] ?? '')),
        'planned_duration_s' => isset($value['planned_duration_s']) && is_numeric($value['planned_duration_s']) ? (int) $value['planned_duration_s'] : null,
        'planned_distance_m' => isset($value['planned_distance_m']) && is_numeric($value['planned_distance_m']) ? (float) $value['planned_distance_m'] : null,
        'organization' => stridebr_teams_surface_named_ref($value['organization'] ?? null),
        'team' => stridebr_teams_surface_team_projection($value['team'] ?? null),
        'season' => stridebr_teams_surface_season_projection($value['season'] ?? null),
        'planning_block' => $planningBlock,
        'competition' => $competition,
        'structure' => stridebr_teams_surface_structure_projection($value['structure'] ?? []),
        'permissions' => stridebr_teams_surface_permissions_projection($value['permissions'] ?? []),
        'capabilities' => stridebr_teams_surface_capabilities_projection($value['capabilities'] ?? []),
    ];
}

function stridebr_teams_surface_result_projection(mixed $value): ?array
{
    if (!is_array($value)) return null;
    $result = [];
    foreach (['phase', 'placement_scope', 'result', 'classification', 'medal'] as $key) {
        $text = trim((string) ($value[$key] ?? ''));
        if ($text !== '') $result[$key] = $text;
    }
    if (isset($value['position']) && is_numeric($value['position'])) $result['position'] = (int) $value['position'];
    $provenance = $value['provenance'] ?? null;
    if (is_array($provenance)) {
        $safe = [];
        foreach (['source_role', 'status'] as $key) {
            $text = trim((string) ($provenance[$key] ?? ''));
            if ($text !== '') $safe[$key] = $text;
        }
        if ($safe !== []) $result['provenance'] = $safe;
    } else {
        $text = trim((string) ($provenance ?? ''));
        if ($text !== '') $result['provenance'] = $text;
    }
    return $result !== [] ? $result : null;
}

function stridebr_teams_surface_competition_projection(mixed $value): ?array
{
    if (!is_array($value)) return null;
    $ref = trim((string) ($value['competition_ref'] ?? ''));
    $name = trim((string) ($value['display_name'] ?? ''));
    if ($ref === '' || $name === '') return null;
    $entries = [];
    foreach ((array) ($value['entries'] ?? []) as $entry) {
        if (!is_array($entry)) continue;
        $entryRef = trim((string) ($entry['entry_ref'] ?? ''));
        $programItem = trim((string) ($entry['program_item'] ?? ''));
        if ($entryRef === '' || $programItem === '') continue;
        $results = [];
        foreach ((array) ($entry['results'] ?? []) as $result) {
            $projected = stridebr_teams_surface_result_projection($result);
            if ($projected !== null) $results[] = $projected;
        }
        $phase = trim((string) ($entry['phase'] ?? ''));
        $entries[] = ['entry_ref' => $entryRef, 'program_item' => $programItem, 'phase' => $phase !== '' ? $phase : null, 'results' => $results];
    }
    return [
        'competition_ref' => $ref,
        'display_name' => $name,
        'start_date' => trim((string) ($value['start_date'] ?? '')),
        'end_date' => trim((string) ($value['end_date'] ?? '')),
        'delegation_member' => !empty($value['delegation_member']),
        'organization' => stridebr_teams_surface_named_ref($value['organization'] ?? null),
        'entries' => $entries,
    ];
}

function stridebr_teams_surface_fixture(): array
{
    return [
        'identities' => [
            'fixture:bruno-evaristo' => [
                'person_ref' => 'person:bruno-evaristo',
                'display_name' => 'Bruno Evaristo',
                'season_ref' => 'season:2027',
                'my_teams' => [[
                    'organization' => ['ref' => 'org:iffar-fw', 'name' => 'IF Farroupilha — Frederico Westphalen'],
                    'team' => ['ref' => 'team:atletismo-fw', 'name' => 'Atletismo', 'sport' => 'atletismo'],
                    'season' => ['ref' => 'season:2027', 'label' => '2027'],
                    'roles' => ['Atleta'],
                    'group' => 'Fundo',
                ]],
            ],
            'fixture:other-athlete' => [
                'person_ref' => 'person:other-athlete',
                'display_name' => 'Atleta Externo',
                'season_ref' => 'season:2027',
                'my_teams' => [[
                    'organization' => ['ref' => 'org:iffar-fw', 'name' => 'IF Farroupilha — Frederico Westphalen'],
                    'team' => ['ref' => 'team:voleibol-fw', 'name' => 'Voleibol', 'sport' => 'voleibol'],
                    'season' => ['ref' => 'season:2027', 'label' => '2027'],
                    'roles' => ['Atleta'],
                    'group' => null,
                ]],
            ],
        ],
        'rosters' => [
            'team:atletismo-fw|season:2027' => [
                'athletes' => [
                    ['person_ref' => 'person:bruno-evaristo', 'display_name' => 'Bruno Evaristo', 'roles' => ['Atleta'], 'group' => 'Fundo'],
                    ['person_ref' => 'person:ana-martins', 'display_name' => 'Ana Martins', 'roles' => ['Atleta'], 'group' => 'Velocidade'],
                    ['person_ref' => 'person:lucas-ferreira', 'display_name' => 'Lucas Ferreira', 'roles' => ['Atleta'], 'group' => 'Fundo'],
                ],
                'staff' => [
                    ['person_ref' => 'person:carlos-almeida', 'display_name' => 'Professor Carlos Almeida', 'roles' => ['Treinador']],
                    ['person_ref' => 'person:joao-ribeiro', 'display_name' => 'Professor João Ribeiro', 'roles' => ['Assistente']],
                ],
            ],
            'team:voleibol-fw|season:2027' => [
                'athletes' => [['person_ref' => 'person:other-athlete', 'display_name' => 'Atleta Externo', 'roles' => ['Atleta'], 'group' => null]],
                'staff' => [['person_ref' => 'person:volei-staff', 'display_name' => 'Professor Vôlei', 'roles' => ['Treinador']]],
            ],
        ],
        'trainings' => [
            [
                'training_ref' => 'training:atletismo:2027:rodagem-8k',
                'recipient_ref' => 'recipient:bruno:rodagem-8k',
                'recipient_identity_ref' => 'fixture:bruno-evaristo',
                'title' => 'Rodagem contínua 8 km', 'date' => '2027-03-15', 'time' => '17:30', 'status' => 'publicado', 'sport' => 'corrida',
                'planned_duration_s' => 3000, 'planned_distance_m' => 8000.0,
                'organization' => ['ref' => 'org:iffar-fw', 'name' => 'IF Farroupilha — Frederico Westphalen'],
                'team' => ['ref' => 'team:atletismo-fw', 'name' => 'Atletismo'],
                'season' => ['ref' => 'season:2027', 'label' => '2027'],
                'planning_block' => ['ref' => 'planning:base-1', 'name' => 'Base aeróbica'],
                'competition' => ['ref' => 'competition:jeif-2027', 'name' => 'JEIF 2027'],
                'structure' => [
                    ['step_type' => 'warmup', 'name' => 'Aquecimento', 'duration_s' => 600, 'order' => 1],
                    ['step_type' => 'work', 'name' => 'Rodagem', 'distance_m' => 8000, 'target' => ['type' => 'pace', 'min' => 330, 'max' => 360, 'unit' => 's_per_km'], 'order' => 2],
                    ['step_type' => 'cooldown', 'name' => 'Desaquecimento', 'duration_s' => 300, 'order' => 3],
                ],
                'permissions' => ['can_edit' => false, 'can_reschedule' => false, 'can_cancel' => false, 'can_delete' => false],
                'capabilities' => ['can_start_session' => true, 'can_start_gps' => false, 'can_quick_register' => false, 'can_complete_manually' => false],
            ],
            [
                'training_ref' => 'training:atletismo:2027:forca-fundo',
                'recipient_ref' => 'recipient:bruno:forca-fundo',
                'recipient_identity_ref' => 'fixture:bruno-evaristo',
                'title' => 'Força geral — Fundo', 'date' => '2027-03-17', 'time' => '17:00', 'status' => 'publicado', 'sport' => 'musculacao',
                'planned_duration_s' => 3600, 'planned_distance_m' => null,
                'organization' => ['ref' => 'org:iffar-fw', 'name' => 'IF Farroupilha — Frederico Westphalen'],
                'team' => ['ref' => 'team:atletismo-fw', 'name' => 'Atletismo'],
                'season' => ['ref' => 'season:2027', 'label' => '2027'],
                'planning_block' => ['ref' => 'planning:strength-1', 'name' => 'Força geral'],
                'competition' => null,
                'structure' => [
                    ['step_type' => 'exercise', 'name' => 'Agachamento', 'sets' => 3, 'repetitions' => '8', 'rest_s' => 120, 'order' => 1],
                    ['step_type' => 'exercise', 'name' => 'Afundo', 'sets' => 3, 'repetitions' => '10', 'rest_s' => 90, 'order' => 2],
                    ['step_type' => 'exercise', 'name' => 'Panturrilha em pé', 'sets' => 3, 'repetitions' => '15', 'rest_s' => 60, 'order' => 3],
                ],
                'permissions' => ['can_edit' => false, 'can_reschedule' => false, 'can_cancel' => false, 'can_delete' => false],
                'capabilities' => ['can_start_session' => true, 'can_start_gps' => false, 'can_quick_register' => false, 'can_complete_manually' => false],
            ],
            [
                'training_ref' => 'training:private:other-athlete', 'recipient_ref' => 'recipient:other:private', 'recipient_identity_ref' => 'fixture:other-athlete',
                'title' => 'Treino privado de outro atleta', 'date' => '2027-03-18', 'time' => '18:00', 'status' => 'publicado', 'sport' => 'voleibol',
                'planned_duration_s' => 3600, 'planned_distance_m' => null,
                'organization' => ['ref' => 'org:iffar-fw', 'name' => 'IF Farroupilha — Frederico Westphalen'],
                'team' => ['ref' => 'team:voleibol-fw', 'name' => 'Voleibol'], 'season' => ['ref' => 'season:2027', 'label' => '2027'],
                'planning_block' => null, 'competition' => null, 'structure' => [['step_type' => 'work', 'name' => 'Técnico', 'duration_s' => 3600, 'order' => 1]],
                'permissions' => ['can_edit' => false, 'can_reschedule' => false, 'can_cancel' => false, 'can_delete' => false],
                'capabilities' => ['can_start_session' => true, 'can_start_gps' => false, 'can_quick_register' => false, 'can_complete_manually' => false],
            ],
        ],
        'competitions' => [
            [
                'competition_ref' => 'competition:jeif-2027', 'display_name' => 'JEIF 2027', 'start_date' => '2027-04-10', 'end_date' => '2027-04-12', 'delegation_member' => true,
                'organization' => ['ref' => 'org:iffar-fw', 'name' => 'IF Farroupilha — Frederico Westphalen'],
                'entries' => [[
                    'entry_ref' => 'entry:bruno:jeif:5000m', 'program_item' => '5000 m', 'phase' => 'Final',
                    'results' => [['position' => 3, 'result' => '17:58.42', 'classification' => '3º lugar', 'medal' => 'bronze', 'provenance' => 'official']],
                ]],
            ],
            [
                'competition_ref' => 'competition:jifsul-2027', 'display_name' => 'JIFSul 2027', 'start_date' => '2027-09-20', 'end_date' => '2027-09-24', 'delegation_member' => true,
                'organization' => ['ref' => 'org:iffar-fw', 'name' => 'IF Farroupilha — Frederico Westphalen'], 'entries' => [['entry_ref' => 'entry:bruno:jifsul:5000m', 'program_item' => '5000 m', 'phase' => null, 'results' => []]],
            ],
            [
                'competition_ref' => 'competition:jifnacional-2027', 'display_name' => 'JIF Nacional 2027', 'start_date' => '2027-11-08', 'end_date' => '2027-11-13', 'delegation_member' => true,
                'organization' => ['ref' => 'org:iffar-fw', 'name' => 'IF Farroupilha — Frederico Westphalen'], 'entries' => [['entry_ref' => 'entry:bruno:jifnacional:5000m', 'program_item' => '5000 m', 'phase' => null, 'results' => []]],
            ],
        ],
    ];
}

function stridebr_teams_surface_provider_dispatch(string $operation, array $input): mixed
{
    $availability = stridebr_teams_surface_availability();
    if (!$availability['available']) return null;
    $GLOBALS['stridebr_teams_surface_provider_calls'] = stridebr_teams_surface_provider_call_count() + 1;
    if ($availability['mode'] !== 'fixture') return null;
    $fixture = stridebr_teams_surface_fixture();
    $identityRef = (string) ($input['identity_ref'] ?? '');
    $identity = $fixture['identities'][$identityRef] ?? null;
    if (!$identity) return null;

    if ($operation === 'athlete_context') return stridebr_teams_surface_athlete_context_projection(['person_ref' => $identity['person_ref'], 'season_ref' => $identity['season_ref'], 'my_teams' => $identity['my_teams']]);
    if ($operation === 'team_roster') {
        $teamRef = (string) ($input['team_ref'] ?? '');
        $seasonRef = (string) ($input['season_ref'] ?? '');
        $allowed = false;
        foreach ($identity['my_teams'] as $team) if (($team['team']['ref'] ?? '') === $teamRef && ($team['season']['ref'] ?? '') === $seasonRef) $allowed = true;
        if (!$allowed) return null;
        return stridebr_teams_surface_roster_projection($fixture['rosters'][$teamRef . '|' . $seasonRef] ?? null);
    }
    if ($operation === 'trainings') {
        $from = (string) ($input['from'] ?? '0000-00-00');
        $to = (string) ($input['to'] ?? '9999-12-31');
        $teamRef = (string) ($input['team_ref'] ?? '');
        $matches = array_values(array_filter($fixture['trainings'], static function (array $training) use ($identityRef, $from, $to, $teamRef): bool {
            if (($training['recipient_identity_ref'] ?? '') !== $identityRef) return false;
            if (($training['status'] ?? '') !== 'publicado') return false;
            if ((string) $training['date'] < $from || (string) $training['date'] > $to) return false;
            if ($teamRef !== '' && ($training['team']['ref'] ?? '') !== $teamRef) return false;
            return true;
        }));
        return array_values(array_filter(array_map('stridebr_teams_surface_training_projection', $matches)));
    }
    if ($operation === 'training_detail') {
        $ref = (string) ($input['training_ref'] ?? '');
        foreach ($fixture['trainings'] as $training) if (($training['training_ref'] ?? '') === $ref && ($training['recipient_identity_ref'] ?? '') === $identityRef && ($training['status'] ?? '') === 'publicado') return stridebr_teams_surface_training_projection($training);
        return null;
    }
    if ($operation === 'competitions') {
        if ($identityRef !== 'fixture:bruno-evaristo') return [];
        return array_values(array_filter(array_map('stridebr_teams_surface_competition_projection', $fixture['competitions'])));
    }
    if ($operation === 'competition_detail') {
        if ($identityRef !== 'fixture:bruno-evaristo') return null;
        $ref = (string) ($input['competition_ref'] ?? '');
        foreach ($fixture['competitions'] as $competition) if (($competition['competition_ref'] ?? '') === $ref) return stridebr_teams_surface_competition_projection($competition);
        return null;
    }
    return null;
}

function stridebr_teams_surface_safe_dispatch(string $operation, array $input): mixed
{
    try {
        return stridebr_teams_surface_provider_dispatch($operation, $input);
    } catch (Throwable $error) {
        error_log('[stridebr][teams_surface] ' . $operation . ' unavailable: ' . $error->getMessage());
        return null;
    }
}

function stridebr_teams_surface_identity(PDO $pdo, string $userId): ?string
{
    if (!stridebr_teams_enabled()) return null;
    return stridebr_teams_surface_identity_for_core_user($pdo, $userId);
}

function stridebr_teams_surface_context(PDO $pdo, string $userId): array
{
    if (!stridebr_teams_enabled()) return ['availability' => stridebr_teams_surface_availability(), 'person_ref' => null, 'season_ref' => null, 'my_teams' => []];
    $identityRef = stridebr_teams_surface_identity($pdo, $userId);
    if ($identityRef === null) return ['availability' => stridebr_teams_surface_availability(), 'person_ref' => null, 'season_ref' => null, 'my_teams' => []];
    $data = stridebr_teams_surface_safe_dispatch('athlete_context', ['identity_ref' => $identityRef]);
    return is_array($data) ? ['availability' => stridebr_teams_surface_availability()] + $data : ['availability' => stridebr_teams_surface_availability(), 'person_ref' => null, 'season_ref' => null, 'my_teams' => []];
}

function stridebr_teams_surface_roster(PDO $pdo, string $userId, string $teamRef, string $seasonRef): ?array
{
    if (!stridebr_teams_enabled()) return null;
    $identityRef = stridebr_teams_surface_identity($pdo, $userId);
    if ($identityRef === null) return null;
    $data = stridebr_teams_surface_safe_dispatch('team_roster', ['identity_ref' => $identityRef, 'team_ref' => $teamRef, 'season_ref' => $seasonRef]);
    return is_array($data) ? $data : null;
}

function stridebr_teams_surface_trainings(PDO $pdo, string $userId, string $from, string $to, ?string $teamRef = null): array
{
    if (!stridebr_teams_enabled()) return [];
    $identityRef = stridebr_teams_surface_identity($pdo, $userId);
    if ($identityRef === null) return [];
    $data = stridebr_teams_surface_safe_dispatch('trainings', ['identity_ref' => $identityRef, 'from' => $from, 'to' => $to, 'team_ref' => $teamRef ?? '']);
    return is_array($data) ? array_values($data) : [];
}

function stridebr_teams_surface_training(PDO $pdo, string $userId, string $trainingRef): ?array
{
    if (!stridebr_teams_enabled()) return null;
    $identityRef = stridebr_teams_surface_identity($pdo, $userId);
    if ($identityRef === null) return null;
    $data = stridebr_teams_surface_safe_dispatch('training_detail', ['identity_ref' => $identityRef, 'training_ref' => $trainingRef]);
    return is_array($data) ? $data : null;
}

function stridebr_teams_surface_competitions(PDO $pdo, string $userId): array
{
    if (!stridebr_teams_enabled()) return [];
    $identityRef = stridebr_teams_surface_identity($pdo, $userId);
    if ($identityRef === null) return [];
    $data = stridebr_teams_surface_safe_dispatch('competitions', ['identity_ref' => $identityRef]);
    return is_array($data) ? array_values($data) : [];
}

function stridebr_teams_surface_competition(PDO $pdo, string $userId, string $competitionRef): ?array
{
    if (!stridebr_teams_enabled()) return null;
    $identityRef = stridebr_teams_surface_identity($pdo, $userId);
    if ($identityRef === null) return null;
    $data = stridebr_teams_surface_safe_dispatch('competition_detail', ['identity_ref' => $identityRef, 'competition_ref' => $competitionRef]);
    return is_array($data) ? $data : null;
}

function stridebr_teams_execution_ack_payload(array $session, string $status, ?string $activityRef = null): array
{
    $payload = [
        'training_ref' => trim((string) ($session['referencia_externa'] ?? '')) ?: null,
        'recipient_ref' => trim((string) ($session['recipient_ref_externo'] ?? '')) ?: null,
        'status' => $status,
        'started_at' => !empty($session['data_inicio']) ? (string) $session['data_inicio'] : null,
        'completed_at' => !empty($session['data_fim']) ? (string) $session['data_fim'] : null,
        'activity_ref' => $activityRef,
    ];
    return array_filter($payload, static fn(mixed $value): bool => $value !== null && $value !== '');
}
