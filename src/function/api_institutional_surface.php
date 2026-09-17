<?php

declare(strict_types=1);

require_once __DIR__ . '/teams_surface_provider.php';

function stridebr_api_institutional_named_ref(mixed $value): ?array
{
    if (!is_array($value)) return null;
    $ref = trim((string) ($value['ref'] ?? ''));
    $name = trim((string) ($value['name'] ?? ''));
    if ($ref === '' && $name === '') return null;
    return [
        'ref' => $ref !== '' ? $ref : null,
        'name' => $name !== '' ? $name : null,
    ];
}

function stridebr_api_institutional_team_context(mixed $value): ?array
{
    if (!is_array($value)) return null;
    $organization = stridebr_api_institutional_named_ref($value['organization'] ?? null);
    $team = stridebr_api_institutional_named_ref($value['team'] ?? null);
    $season = is_array($value['season'] ?? null) ? $value['season'] : [];
    $seasonRef = trim((string) ($season['ref'] ?? ''));
    $seasonLabel = trim((string) ($season['label'] ?? ''));
    if ($organization === null || $team === null || ($seasonRef === '' && $seasonLabel === '')) return null;
    $sport = trim((string) (($value['team']['sport'] ?? '') ?: ''));
    if ($sport !== '') $team['sport'] = $sport;
    $roles = [];
    foreach ((array) ($value['roles'] ?? []) as $role) {
        $role = trim((string) $role);
        if ($role !== '' && !in_array($role, $roles, true)) $roles[] = $role;
    }
    $group = trim((string) ($value['group'] ?? ''));
    return [
        'organization' => $organization,
        'team' => $team,
        'season' => ['ref' => $seasonRef !== '' ? $seasonRef : null, 'label' => $seasonLabel !== '' ? $seasonLabel : null],
        'roles' => $roles,
        'group' => $group !== '' ? $group : null,
    ];
}

function stridebr_api_institutional_context(PDO $pdo, string $userId): array
{
    $surface = stridebr_teams_surface_context($pdo, $userId);
    $teams = [];
    foreach ((array) ($surface['my_teams'] ?? []) as $team) {
        $serialized = stridebr_api_institutional_team_context($team);
        if ($serialized !== null) $teams[] = $serialized;
    }
    $seasonRef = trim((string) ($surface['season_ref'] ?? ''));
    return [
        'season_ref' => $seasonRef !== '' ? $seasonRef : null,
        'my_teams' => $teams,
    ];
}

function stridebr_api_institutional_roster_member(mixed $value, bool $includeGroup): ?array
{
    if (!is_array($value)) return null;
    $personRef = trim((string) ($value['person_ref'] ?? ''));
    $displayName = trim((string) ($value['display_name'] ?? ''));
    if ($personRef === '' || $displayName === '') return null;
    $roles = [];
    foreach ((array) ($value['roles'] ?? []) as $role) {
        $role = trim((string) $role);
        if ($role !== '' && !in_array($role, $roles, true)) $roles[] = $role;
    }
    $member = ['person_ref' => $personRef, 'display_name' => $displayName, 'roles' => $roles];
    if ($includeGroup) {
        $group = trim((string) ($value['group'] ?? ''));
        $member['group'] = $group !== '' ? $group : null;
    }
    return $member;
}

function stridebr_api_institutional_roster(PDO $pdo, string $userId, string $teamRef, string $seasonRef): ?array
{
    $surface = stridebr_teams_surface_roster($pdo, $userId, $teamRef, $seasonRef);
    if (!is_array($surface)) return null;
    $athletes = [];
    foreach ((array) ($surface['athletes'] ?? []) as $member) {
        $serialized = stridebr_api_institutional_roster_member($member, true);
        if ($serialized !== null) $athletes[] = $serialized;
    }
    $staff = [];
    foreach ((array) ($surface['staff'] ?? []) as $member) {
        $serialized = stridebr_api_institutional_roster_member($member, false);
        if ($serialized !== null) $staff[] = $serialized;
    }
    return ['athletes' => $athletes, 'staff' => $staff];
}

function stridebr_api_institutional_result(mixed $value): ?array
{
    if (!is_array($value)) return null;
    $result = [];
    foreach (['phase', 'placement_scope', 'result', 'classification', 'medal', 'provenance'] as $key) {
        if (!array_key_exists($key, $value) || $value[$key] === null) continue;
        if (is_array($value[$key])) {
            if ($key === 'provenance') {
                $safe = [];
                foreach (['source_role', 'status'] as $subKey) {
                    $text = trim((string) ($value[$key][$subKey] ?? ''));
                    if ($text !== '') $safe[$subKey] = $text;
                }
                if ($safe !== []) $result[$key] = $safe;
            }
            continue;
        }
        $text = trim((string) $value[$key]);
        if ($text !== '') $result[$key] = $text;
    }
    if (isset($value['position']) && is_numeric($value['position'])) $result['position'] = (int) $value['position'];
    return $result !== [] ? $result : null;
}

function stridebr_api_institutional_entry(mixed $value): ?array
{
    if (!is_array($value)) return null;
    $entryRef = trim((string) ($value['entry_ref'] ?? ''));
    $programItem = trim((string) ($value['program_item'] ?? ''));
    if ($entryRef === '' || $programItem === '') return null;
    $results = [];
    foreach ((array) ($value['results'] ?? []) as $result) {
        $serialized = stridebr_api_institutional_result($result);
        if ($serialized !== null) $results[] = $serialized;
    }
    $phase = trim((string) ($value['phase'] ?? ''));
    return [
        'entry_ref' => $entryRef,
        'program_item' => $programItem,
        'phase' => $phase !== '' ? $phase : null,
        'results' => $results,
    ];
}

function stridebr_api_institutional_competition_summary(mixed $value): ?array
{
    if (!is_array($value)) return null;
    $competitionRef = trim((string) ($value['competition_ref'] ?? ''));
    $displayName = trim((string) ($value['display_name'] ?? ''));
    if ($competitionRef === '' || $displayName === '') return null;
    return [
        'competition_ref' => $competitionRef,
        'display_name' => $displayName,
        'start_date' => trim((string) ($value['start_date'] ?? '')) ?: null,
        'end_date' => trim((string) ($value['end_date'] ?? '')) ?: null,
        'delegation_member' => !empty($value['delegation_member']),
        'organization' => stridebr_api_institutional_named_ref($value['organization'] ?? null),
    ];
}

function stridebr_api_institutional_competitions(PDO $pdo, string $userId): array
{
    $competitions = [];
    foreach (stridebr_teams_surface_competitions($pdo, $userId) as $competition) {
        $serialized = stridebr_api_institutional_competition_summary($competition);
        if ($serialized !== null) $competitions[] = $serialized;
    }
    return $competitions;
}

function stridebr_api_institutional_competition(PDO $pdo, string $userId, string $competitionRef): ?array
{
    $surface = stridebr_teams_surface_competition($pdo, $userId, $competitionRef);
    if (!is_array($surface)) return null;
    $summary = stridebr_api_institutional_competition_summary($surface);
    if ($summary === null) return null;
    $entries = [];
    foreach ((array) ($surface['entries'] ?? []) as $entry) {
        $serialized = stridebr_api_institutional_entry($entry);
        if ($serialized !== null) $entries[] = $serialized;
    }
    $summary['entries'] = $entries;
    return $summary;
}
