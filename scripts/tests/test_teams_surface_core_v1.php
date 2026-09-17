<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/function/api_v1.php';
require_once dirname(__DIR__, 2) . '/src/function/teams_surface_provider.php';

return function (PDO $pdo): void {
    $oldEnv = getenv('STRIDEBR_APP_ENV');
    $oldEnabled = getenv('STRIDEBR_TEAMS_ENABLED');
    $oldMode = getenv('STRIDEBR_TEAMS_SURFACE_MODE');
    try {
        putenv('STRIDEBR_APP_ENV=development');
        putenv('STRIDEBR_TEAMS_ENABLED=true');
        putenv('STRIDEBR_TEAMS_SURFACE_MODE=fixture');

        $owner = alphaTestUser($pdo, 'teams-surface-bruno', ['nome' => 'Bruno Evaristo', 'username' => 'brunowithouth']);
        $other = alphaTestUser($pdo, 'teams-surface-other');
        $spoofedName = alphaTestUser($pdo, 'teams-surface-spoofed-name', ['nome' => 'Bruno Evaristo', 'username' => 'not-brunowithouth']);
        AlphaTest::assert((bool) $pdo->query("SELECT to_regclass('stridebr.sessoes_treino')")->fetchColumn(), 'Workout Session table deve existir');

        $context = stridebr_teams_surface_context($pdo, $owner);
        AlphaTest::same('Atletismo', (string) ($context['my_teams'][0]['team']['name'] ?? ''), 'Bruno deve receber projection da equipe Atletismo');
        AlphaTest::same(null, stridebr_teams_surface_roster($pdo, $owner, 'team:voleibol-fw', 'season:2027'), 'cross-team roster precisa falhar fechado');
        $roster = stridebr_teams_surface_roster($pdo, $owner, 'team:atletismo-fw', 'season:2027');
        AlphaTest::assert(is_array($roster) && count($roster['athletes'] ?? []) >= 1, 'roster athlete-safe da própria Team deve estar disponível');

        $apiContext = stridebr_api_institutional_context($pdo, $owner);
        AlphaTest::same(['season_ref', 'my_teams'], array_keys($apiContext), 'context API precisa usar allowlist própria sem person_ref/availability internos');
        AlphaTest::same('season:2027', (string) ($apiContext['season_ref'] ?? ''), 'context API deve expor Season opaca do atleta');
        AlphaTest::same('IF Farroupilha — Frederico Westphalen', (string) ($apiContext['my_teams'][0]['organization']['name'] ?? ''), 'context API deve expor organização athlete-safe');
        AlphaTest::same('Atletismo', (string) ($apiContext['my_teams'][0]['team']['name'] ?? ''), 'context API deve expor Team athlete-safe');
        AlphaTest::same('2027', (string) ($apiContext['my_teams'][0]['season']['label'] ?? ''), 'context API deve expor label da Season');
        AlphaTest::assert(in_array('Atleta', $apiContext['my_teams'][0]['roles'] ?? [], true), 'context API deve expor papel Atleta');
        AlphaTest::same('Fundo', (string) ($apiContext['my_teams'][0]['group'] ?? ''), 'context API deve expor grupo athlete-safe');

        $apiRoster = stridebr_api_institutional_roster($pdo, $owner, 'team:atletismo-fw', 'season:2027');
        AlphaTest::assert(is_array($apiRoster), 'roster API da própria Team deve estar disponível');
        $lucas = array_values(array_filter($apiRoster['athletes'] ?? [], static fn(array $member): bool => ($member['display_name'] ?? '') === 'Lucas Ferreira'));
        AlphaTest::assert(count($lucas) === 1, 'Lucas deve poder aparecer no roster athlete-safe');
        AlphaTest::assert(!array_key_exists('availability', $lucas[0]), 'roster API não pode expor availability de colega');
        AlphaTest::same(null, stridebr_api_institutional_roster($pdo, $owner, 'team:voleibol-fw', 'season:2027'), 'cross-Team na API precisa falhar fechado como 404 lógico');
        AlphaTest::same(null, stridebr_api_institutional_roster($pdo, $owner, 'team:atletismo-fw', 'season:2026'), 'roster API não pode reutilizar Season diferente');

        $apiCompetitions = stridebr_api_institutional_competitions($pdo, $owner);
        $apiCompetitionNames = array_map(static fn(array $competition): string => (string) ($competition['display_name'] ?? ''), $apiCompetitions);
        foreach (['JEIF 2027', 'JIFSul 2027', 'JIF Nacional 2027'] as $competitionName) AlphaTest::assert(in_array($competitionName, $apiCompetitionNames, true), "competitions API precisa incluir {$competitionName}");
        foreach ($apiCompetitions as $competitionSummary) AlphaTest::assert(!array_key_exists('entries', $competitionSummary), 'lista de competitions não deve carregar entries/detail por acidente');
        $apiCompetition = stridebr_api_institutional_competition($pdo, $owner, 'competition:jeif-2027');
        AlphaTest::assert(is_array($apiCompetition) && count($apiCompetition['entries'] ?? []) >= 1, 'competition detail API precisa expor minhas entries');
        $apiCompetitionJson = json_encode($apiCompetition, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        foreach (['Lívia Demo', 'Equipe externa', 'other_participants', 'organizer_internals'] as $forbidden) AlphaTest::assert(!str_contains($apiCompetitionJson, $forbidden), "competition detail API não pode expor {$forbidden}");
        AlphaTest::same(null, stridebr_api_institutional_competition($pdo, $owner, 'competition:nao-visivel'), 'Competition não visível precisa falhar como 404 lógico');

        $personal = stridebr_api_workout_create($pdo, $owner, [
            'title' => 'Treino pessoal no mesmo calendário',
            'sport' => 'corrida',
            'date' => '2027-03-15',
            'time' => '06:30',
            'planned_duration_s' => 1800,
        ]);
        $schedule = stridebr_api_workout_schedule($pdo, $owner, ['from' => '2027-03-15', 'to' => '2027-03-17']);
        $sources = array_values(array_unique(array_map(static fn(array $row): string => (string) ($row['source'] ?? ''), $schedule['data'])));
        AlphaTest::assert(in_array('usuario', $sources, true), 'schedule deve preservar workout pessoal');
        AlphaTest::assert(in_array('teams', $sources, true), 'schedule deve compor training Teams no calendário existente');
        $teamsItems = array_values(array_filter($schedule['data'], static fn(array $row): bool => ($row['source'] ?? '') === 'teams'));
        AlphaTest::assert(count($teamsItems) >= 2, 'schedule deve incluir prescriptions publicadas do atleta no intervalo');
        $teamsWorkout = $teamsItems[0];
        AlphaTest::same('institutional', (string) $teamsWorkout['kind'], 'Teams workout precisa kind institutional');
        AlphaTest::same(false, (bool) ($teamsWorkout['capabilities']['can_edit'] ?? true), 'Teams workout não pode ser editado no Core');
        AlphaTest::same(false, (bool) ($teamsWorkout['capabilities']['can_start_gps'] ?? true), 'Teams workout v1 não inicia GPS');
        AlphaTest::same(false, (bool) ($teamsWorkout['capabilities']['can_quick_register'] ?? true), 'Teams workout v1 não usa Quick Register');
        AlphaTest::same(true, (bool) ($teamsWorkout['capabilities']['can_start_session'] ?? false), 'Teams workout estruturado autorizado deve iniciar Session');

        $opaqueId = (string) $teamsWorkout['id'];
        $detail = stridebr_api_workout_detail($pdo, $owner, $opaqueId);
        AlphaTest::same('teams', (string) $detail['source'], 'detail deve preservar source teams');
        AlphaTest::same('institutional', (string) $detail['kind'], 'detail deve preservar kind institutional');
        AlphaTest::assert(!empty($detail['institutional_context']['team']['name']), 'detail deve expor institutional_context athlete-safe');
        AlphaTest::same([], stridebr_api_workout_detail($pdo, $owner, 'teams:training:private:other-athlete'), 'training destinado a outro atleta deve resolver como 404 lógico');
        AlphaTest::throws(fn() => stridebr_api_workout_update($pdo, $owner, $opaqueId, ['title' => 'Não']), 'prescription institucional precisa ser read-only');

        $session = stridebr_api_workout_session_start($pdo, $owner, $opaqueId);
        AlphaTest::same('teams', (string) $session['source'], 'Workout Session institucional deve source teams');
        AlphaTest::same($opaqueId, (string) ($session['workout']['id'] ?? ''), 'Session deve preservar workout.id institucional');
        AlphaTest::same('institutional', (string) ($session['workout']['kind'] ?? ''), 'Session deve preservar kind institutional');
        AlphaTest::assert(count($session['exercises'] ?? []) >= 1, 'structure Teams deve ser snapshotada na Session Core');

        $rawSession = sessaoCarregarPorId($pdo, $owner, (string) $session['id'], false);
        AlphaTest::same('teams', (string) ($rawSession['origem_externa'] ?? ''), 'Session deve preservar origem externa');
        AlphaTest::assert((string) ($rawSession['referencia_externa'] ?? '') !== '', 'Session deve preservar training ref externo');
        AlphaTest::assert((string) ($rawSession['recipient_ref_externo'] ?? '') !== '', 'Session deve preservar recipient ref externo');
        AlphaTest::assert(str_contains((string) ($rawSession['contexto_institucional_snapshot'] ?? ''), 'Atletismo'), 'Session deve snapshotar contexto mínimo necessário');

        putenv('STRIDEBR_TEAMS_ENABLED=false');
        putenv('STRIDEBR_TEAMS_SURFACE_MODE=disabled');
        $current = stridebr_api_workout_session_current($pdo, $owner);
        AlphaTest::same('teams', (string) ($current['source'] ?? ''), 'Session iniciada precisa sobreviver à indisponibilidade posterior do provider');
        AlphaTest::same($opaqueId, (string) ($current['workout']['id'] ?? ''), 'snapshot deve preservar referência institucional mesmo com feature desligada');

        $finishNow = new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo'));
        $finished = stridebr_api_workout_session_finish($pdo, $owner, (string) $session['id'], ['started_at_local' => $finishNow->modify('-30 minutes')->format('Y-m-d\TH:i'), 'ended_at_local' => $finishNow->modify('-5 minutes')->format('Y-m-d\TH:i'), 'intensity' => 'moderado', 'feeling' => 4]);
        $activityId = (string) ($finished['activity']['id'] ?? '');
        AlphaTest::assert($activityId !== '', 'finish institucional precisa criar Activity Core');
        $activityStmt = $pdo->prepare('SELECT idusuario,status FROM registros_atividade WHERE idregistro=:id');
        $activityStmt->execute([':id' => $activityId]);
        $activity = $activityStmt->fetch();
        AlphaTest::same($owner, (string) ($activity['idusuario'] ?? ''), 'Activity institucional executada continua Core-owned pelo atleta');
        AlphaTest::same('concluido', (string) ($activity['status'] ?? ''), 'Activity final deve usar fluxo normal concluído do Core');
        $ack = $finished['execution_ack'] ?? null;
        AlphaTest::assert(is_array($ack), 'finish institucional deve construir acknowledgement lógico');
        AlphaTest::same($activityId, (string) ($ack['activity_ref'] ?? ''), 'ack precisa referenciar Activity opacamente');
        AlphaTest::assert(!isset($ack['gps']) && !isset($ack['heart_rate']) && !isset($ack['route']) && !isset($ack['notes']), 'ack não pode transportar dados privados da execução');

        stridebr_teams_surface_provider_reset_call_count();
        $scheduleOff = stridebr_api_workout_schedule($pdo, $owner, ['from' => '2027-03-15', 'to' => '2027-03-17']);
        AlphaTest::same(0, stridebr_teams_surface_provider_call_count(), 'Teams OFF não pode consultar provider no schedule');
        AlphaTest::assert(count(array_filter($scheduleOff['data'], static fn(array $row): bool => ($row['source'] ?? '') === 'teams')) === 0, 'Teams OFF não pode compor institutional workouts');
        AlphaTest::assert(count(array_filter($scheduleOff['data'], static fn(array $row): bool => ($row['id'] ?? '') === $personal['id'])) === 1, 'Teams OFF precisa preservar workout pessoal exatamente no calendário');
        AlphaTest::same([], stridebr_api_workout_detail($pdo, $owner, $opaqueId), 'Teams detail deve ficar indisponível quando feature OFF');

        putenv('STRIDEBR_TEAMS_ENABLED=true');
        putenv('STRIDEBR_TEAMS_SURFACE_MODE=fixture');
        $otherContext = stridebr_teams_surface_context($pdo, $other);
        AlphaTest::same([], $otherContext['my_teams'] ?? [], 'usuário sem identity mapping não deve receber vínculo institucional por inferência');
        $otherApiContext = stridebr_api_institutional_context($pdo, $other);
        AlphaTest::same([], $otherApiContext['my_teams'] ?? [], 'context API de usuário sem mapping precisa responder lista vazia');
        AlphaTest::same([], stridebr_api_institutional_competitions($pdo, $other), 'competitions API de usuário sem mapping precisa responder lista vazia');
        $spoofedContext = stridebr_teams_surface_context($pdo, $spoofedName);
        AlphaTest::same([], $spoofedContext['my_teams'] ?? [], 'nome de exibição igual ao fixture não pode conceder identidade institucional sem mapping explícito.');

        putenv('STRIDEBR_TEAMS_SURFACE_MODE=remote');
        stridebr_teams_surface_provider_reset_call_count();
        $unavailableContext = stridebr_api_institutional_context($pdo, $owner);
        AlphaTest::same([], $unavailableContext['my_teams'] ?? [], 'provider indisponível precisa falhar fechado no context sem exception interna');
        AlphaTest::same([], stridebr_api_institutional_competitions($pdo, $owner), 'provider indisponível precisa falhar fechado na lista de competitions');
        AlphaTest::same(null, stridebr_api_institutional_roster($pdo, $owner, 'team:atletismo-fw', 'season:2027'), 'provider indisponível não pode retornar roster parcial');
        AlphaTest::same(null, stridebr_api_institutional_competition($pdo, $owner, 'competition:jeif-2027'), 'provider indisponível não pode retornar Competition parcial');
        AlphaTest::same(0, stridebr_teams_surface_provider_call_count(), 'remote reservado/não implementado não deve consultar adapter');

        putenv('STRIDEBR_APP_ENV=production');
        putenv('STRIDEBR_TEAMS_SURFACE_MODE=fixture');
        $productionContext = stridebr_api_institutional_context($pdo, $owner);
        AlphaTest::same([], $productionContext['my_teams'] ?? [], 'production + fixture não pode expor context demonstrativo pela API');
        AlphaTest::same([], stridebr_api_institutional_competitions($pdo, $owner), 'production + fixture não pode expor competitions demonstrativas pela API');

        putenv('STRIDEBR_APP_ENV=staging');
        $stagingContext = stridebr_api_institutional_context($pdo, $owner);
        AlphaTest::same([], $stagingContext['my_teams'] ?? [], 'staging + fixture não pode expor context demonstrativo pela API');
        AlphaTest::same([], stridebr_api_institutional_competitions($pdo, $owner), 'staging + fixture não pode expor competitions demonstrativas pela API');
    } finally {
        putenv($oldEnv === false ? 'STRIDEBR_APP_ENV' : 'STRIDEBR_APP_ENV=' . $oldEnv);
        putenv($oldEnabled === false ? 'STRIDEBR_TEAMS_ENABLED' : 'STRIDEBR_TEAMS_ENABLED=' . $oldEnabled);
        putenv($oldMode === false ? 'STRIDEBR_TEAMS_SURFACE_MODE' : 'STRIDEBR_TEAMS_SURFACE_MODE=' . $oldMode);
    }
};
