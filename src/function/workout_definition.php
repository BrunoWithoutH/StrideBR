<?php

declare(strict_types=1);

require_once __DIR__ . '/cronograma.php';

function workoutDefinitionRow(array $row, int $fallbackOrder = 1): array
{
    $prescription = workoutPrescriptionDecode($row['config_prescricao'] ?? null);
    if ($prescription === [] && ($row['tipo_passo'] ?? 'exercise') === 'exercise') {
        try {
            $normalized = workoutPrescriptionNormalize($row);
            $prescription = $normalized['config'];
        } catch (Throwable) {
            $prescription = [];
        }
    }
    return [
        'order' => isset($row['ordem']) && is_numeric($row['ordem']) ? (int) $row['ordem'] : $fallbackOrder,
        'exercise_id' => trim((string) ($row['idexercicio'] ?? '')) ?: null,
        'name' => (string) ($row['nome_snapshot'] ?? $row['nome'] ?? ''),
        'tracking_mode' => (string) ($row['tracking_mode'] ?? $row['tipo_registro'] ?? 'load_reps'),
        'step_type' => (string) ($row['tipo_passo'] ?? 'exercise'),
        'prescription_method' => (string) ($row['metodo_prescricao'] ?? $prescription['method'] ?? 'standard'),
        'prescription' => $prescription,
        'legacy' => [
            'series' => $row['series'] ?? null,
            'repeticoes' => $row['repeticoes'] ?? null,
            'carga' => $row['carga'] ?? null,
            'bloco' => $row['bloco'] ?? null,
            'cluster' => $row['cluster'] ?? null,
            'descanso' => $row['descanso'] ?? null,
            'duracao' => $row['duracao'] ?? $row['duracao_snapshot'] ?? null,
            'distancia' => $row['distancia'] ?? $row['distancia_snapshot'] ?? null,
            'intensidade' => $row['intensidade'] ?? $row['intensidade_snapshot'] ?? null,
            'rpe' => $row['rpe'] ?? $row['rpe_snapshot'] ?? null,
            'rir' => $row['rir'] ?? $row['rir_snapshot'] ?? null,
            'tempo_execucao' => $row['tempo_execucao'] ?? $row['tempo_execucao_snapshot'] ?? null,
            'cadencia' => $row['cadencia'] ?? $row['cadencia_snapshot'] ?? null,
        ],
        'endurance' => [
            'repeticoes_bloco' => $row['repeticoes_bloco'] ?? null,
            'alvo_tipo' => $row['alvo_tipo'] ?? null,
            'alvo_min' => $row['alvo_min'] ?? null,
            'alvo_max' => $row['alvo_max'] ?? null,
            'alvo_unidade' => $row['alvo_unidade'] ?? null,
            'recuperacao_duracao_s' => $row['recuperacao_duracao_s'] ?? null,
            'recuperacao_distancia_m' => $row['recuperacao_distancia_m'] ?? null,
        ],
        'notes' => $row['observacoes'] ?? $row['observacoes_snapshot'] ?? null,
        'group' => trim((string) ($row['grupo_chave'] ?? $row['idgrupo_prescricao'] ?? '')) !== '' ? [
            'key' => (string) ($row['grupo_chave'] ?? $row['idgrupo_prescricao']),
            'type' => (string) ($row['grupo_tipo'] ?? ''),
            'rounds' => isset($row['grupo_voltas']) ? (int) $row['grupo_voltas'] : null,
            'rest_between_exercises_s' => isset($row['grupo_descanso_entre_exercicios_s']) && $row['grupo_descanso_entre_exercicios_s'] !== null ? (int) $row['grupo_descanso_entre_exercicios_s'] : null,
            'rest_after_round_s' => isset($row['grupo_descanso_pos_volta_s']) && $row['grupo_descanso_pos_volta_s'] !== null ? (int) $row['grupo_descanso_pos_volta_s'] : null,
        ] : null,
    ];
}

function workoutDefinitionBuild(array $workout, array $rows, string $source): array
{
    $items = [];
    foreach (array_values($rows) as $index => $row) if (is_array($row)) $items[] = workoutDefinitionRow($row, $index + 1);
    usort($items, static fn(array $a, array $b): int => $a['order'] <=> $b['order']);
    $groups = [];
    foreach ($items as $item) {
        if (!is_array($item['group'])) continue;
        $key = (string) $item['group']['key'];
        if (!isset($groups[$key])) $groups[$key] = $item['group'] + ['members' => []];
        $groups[$key]['members'][] = $item['order'];
    }
    return [
        'schema' => 'stridebr-workout-definition',
        'version' => 1,
        'source' => $source,
        'workout' => [
            'title' => (string) ($workout['titulo'] ?? $workout['titulo_snapshot'] ?? ''),
            'description' => $workout['descricao'] ?? null,
            'sport_id' => $workout['idmodalidade'] ?? null,
            'code' => $workout['codigo'] ?? null,
            'focus' => $workout['foco'] ?? null,
        ],
        'items' => $items,
        'groups' => array_values($groups),
    ];
}

function workoutDefinitionFromSchedule(PDO $pdo, string $userId, string $workoutId): array
{
    $workout = cronogramaBuscarTreino($pdo, $workoutId, $userId);
    if ($workout === []) throw new RuntimeException(stridebr_t('schedule.workout_not_found'));
    return workoutDefinitionBuild($workout, cronogramaListarTreinoExercicios($pdo, $workoutId, $userId), 'schedule');
}

function workoutDefinitionFromTemplate(PDO $pdo, string $userId, string $templateId): array
{
    $workout = cronogramaBuscarTreinoModelo($pdo, $userId, $templateId);
    if ($workout === []) throw new RuntimeException(stridebr_t('library.workout_not_found'));
    return workoutDefinitionBuild($workout, (array) ($workout['exercicios'] ?? []), 'template');
}

function workoutDefinitionFromScheduled(PDO $pdo, string $actorId, string $appointmentId): array
{
    $stmt = $pdo->prepare("SELECT ta.* FROM treinos_agendados ta WHERE ta.idagendamento=:id AND (ta.idatleta=:actor OR ta.idcriador=:actor) LIMIT 1");
    $stmt->execute([':id' => $appointmentId, ':actor' => $actorId]);
    $workout = $stmt->fetch() ?: [];
    if ($workout === []) throw new RuntimeException('Treino agendado não encontrado.');
    $rows = $pdo->prepare('SELECT * FROM treinos_agendados_exercicios WHERE idagendamento=:id ORDER BY ordem');
    $rows->execute([':id' => $appointmentId]);
    return workoutDefinitionBuild($workout, cronogramaHidratarExerciciosPlanejados($pdo, $actorId, $rows->fetchAll()), 'scheduled');
}

function workoutDefinitionFromSessionSnapshot(PDO $pdo, string $userId, string $sessionId): array
{
    $stmt = $pdo->prepare('SELECT * FROM sessoes_treino WHERE idsessao=:id AND idusuario=:user LIMIT 1');
    $stmt->execute([':id' => $sessionId, ':user' => $userId]);
    $workout = $stmt->fetch() ?: [];
    if ($workout === []) throw new RuntimeException('Sessão não encontrada.');
    $rows = $pdo->prepare('SELECT se.*, se.nome_snapshot, se.series_planejadas AS series, se.repeticoes_snapshot AS repeticoes, se.carga_snapshot AS carga, se.bloco_snapshot AS bloco, se.cluster_snapshot AS cluster, se.descanso_snapshot AS descanso, se.observacoes_snapshot AS observacoes, se.duracao_snapshot AS duracao, se.distancia_snapshot AS distancia, se.intensidade_snapshot AS intensidade, se.rpe_snapshot AS rpe, se.rir_snapshot AS rir, se.tempo_execucao_snapshot AS tempo_execucao, se.cadencia_snapshot AS cadencia FROM sessoes_treino_exercicios se WHERE se.idsessao=:id ORDER BY se.ordem');
    $rows->execute([':id' => $sessionId]);
    return workoutDefinitionBuild($workout, workoutPrescriptionHydrateGroups($pdo, $rows->fetchAll()), 'session');
}

function workoutDefinitionSemantic(array $definition): array
{
    $items = [];
    $groupKeyMap = [];
    foreach ((array) ($definition['groups'] ?? []) as $index => $group) {
        if (!is_array($group)) continue;
        $groupKeyMap[(string) ($group['key'] ?? '')] = 'g' . ($index + 1);
    }
    foreach ((array) ($definition['items'] ?? []) as $item) {
        if (!is_array($item)) continue;
        $group = is_array($item['group'] ?? null) ? $item['group'] : null;
        if ($group) $group['key'] = $groupKeyMap[(string) ($group['key'] ?? '')] ?? 'group';
        $items[] = [
            'order' => (int) ($item['order'] ?? 0), 'exercise_id' => $item['exercise_id'] ?? null, 'name' => (string) ($item['name'] ?? ''),
            'tracking_mode' => (string) ($item['tracking_mode'] ?? 'load_reps'), 'step_type' => (string) ($item['step_type'] ?? 'exercise'),
            'prescription_method' => (string) ($item['prescription_method'] ?? 'standard'), 'prescription' => $item['prescription'] ?? [],
            'legacy' => $item['legacy'] ?? [], 'endurance' => $item['endurance'] ?? [], 'notes' => $item['notes'] ?? null, 'group' => $group,
        ];
    }
    return ['workout' => $definition['workout'] ?? [], 'items' => $items];
}

function workoutDefinitionRows(array $definition): array
{
    $rows = [];
    foreach ((array) ($definition['items'] ?? []) as $item) {
        if (!is_array($item)) continue;
        $legacy = is_array($item['legacy'] ?? null) ? $item['legacy'] : [];
        $endurance = is_array($item['endurance'] ?? null) ? $item['endurance'] : [];
        $group = is_array($item['group'] ?? null) ? $item['group'] : [];
        $rows[] = array_merge($legacy, $endurance, [
            'idexercicio' => $item['exercise_id'] ?? '', 'nome' => $item['name'] ?? '', 'nome_snapshot' => $item['name'] ?? '',
            'tracking_mode' => $item['tracking_mode'] ?? 'load_reps', 'tipo_passo' => $item['step_type'] ?? 'exercise',
            'metodo_prescricao' => $item['prescription_method'] ?? 'standard', 'prescription' => $item['prescription'] ?? [],
            'config_prescricao' => workoutPrescriptionJson($item['prescription'] ?? []), 'observacoes' => $item['notes'] ?? null,
            'grupo_chave' => $group['key'] ?? '', 'grupo_tipo' => $group['type'] ?? '', 'grupo_voltas' => $group['rounds'] ?? null,
            'grupo_descanso_entre_exercicios_s' => $group['rest_between_exercises_s'] ?? null, 'grupo_descanso_pos_volta_s' => $group['rest_after_round_s'] ?? null,
            'ordem' => $item['order'] ?? count($rows) + 1,
        ]);
    }
    return $rows;
}

function workoutDefinitionWriteScheduledItemsBatch(PDO $pdo, array $appointments): void
{
    $rowsByAppointment = [];
    foreach ($appointments as $appointment) {
        $id = trim((string) ($appointment['idagendamento'] ?? ''));
        if ($id === '') continue;
        $rowsByAppointment[$id] = array_values((array) ($appointment['rows'] ?? []));
    }
    if ($rowsByAppointment === []) return;
    workoutPrescriptionReplaceGroupsBatch($pdo, 'scheduled', $rowsByAppointment);
    $ids = array_keys($rowsByAppointment);
    $marks = implode(',', array_fill(0, count($ids), '?'));
    $pdo->prepare("DELETE FROM treinos_agendados_exercicios WHERE idagendamento IN ({$marks})")->execute($ids);
    $all = [];
    foreach ($rowsByAppointment as $appointmentId => $rows) {
        foreach ($rows as $index => $row) {
            if (!is_array($row)) continue;
            if (($row['tipo_passo'] ?? 'exercise') === 'exercise') $row = workoutPrescriptionLegacyFields($row, workoutPrescriptionNormalize($row));
            $all[] = [
                stridebr_generate_id(), $appointmentId, trim((string)($row['idexercicio']??''))?:null, trim((string)($row['nome_snapshot']??$row['nome']??'')),
                isset($row['series'])&&is_numeric($row['series'])?(int)$row['series']:null, trim((string)($row['repeticoes']??''))?:null, trim((string)($row['carga']??''))?:null,
                trim((string)($row['bloco']??''))?:null, trim((string)($row['cluster']??''))?:null, trim((string)($row['descanso']??''))?:null, trim((string)($row['observacoes']??''))?:null,
                trim((string)($row['duracao']??''))?:null, trim((string)($row['distancia']??''))?:null, trim((string)($row['intensidade']??''))?:null,
                isset($row['rpe'])&&is_numeric($row['rpe'])?(float)$row['rpe']:null, isset($row['rir'])&&is_numeric($row['rir'])?(float)$row['rir']:null,
                trim((string)($row['tempo_execucao']??''))?:null, trim((string)($row['cadencia']??''))?:null, trim((string)($row['tipo_passo']??''))?:'exercise',
                isset($row['repeticoes_bloco'])&&is_numeric($row['repeticoes_bloco'])?(int)$row['repeticoes_bloco']:null, trim((string)($row['alvo_tipo']??''))?:null,
                isset($row['alvo_min'])&&is_numeric($row['alvo_min'])?(float)$row['alvo_min']:null, isset($row['alvo_max'])&&is_numeric($row['alvo_max'])?(float)$row['alvo_max']:null,
                trim((string)($row['alvo_unidade']??''))?:null, isset($row['recuperacao_duracao_s'])&&is_numeric($row['recuperacao_duracao_s'])?(int)$row['recuperacao_duracao_s']:null,
                isset($row['recuperacao_distancia_m'])&&is_numeric($row['recuperacao_distancia_m'])?(float)$row['recuperacao_distancia_m']:null,
                trim((string)($row['metodo_prescricao']??''))?:'standard', $row['config_prescricao']??workoutPrescriptionJson($row['prescription']??[]), $row['idgrupo_prescricao']??null,
                isset($row['ordem'])&&is_numeric($row['ordem'])?(int)$row['ordem']:$index+1,
            ];
        }
    }
    if ($all === []) return;
    $columns='idagendamento_exercicio,idagendamento,idexercicio,nome_snapshot,series,repeticoes,carga,bloco,cluster,descanso,observacoes,duracao,distancia,intensidade,rpe,rir,tempo_execucao,cadencia,tipo_passo,repeticoes_bloco,alvo_tipo,alvo_min,alvo_max,alvo_unidade,recuperacao_duracao_s,recuperacao_distancia_m,metodo_prescricao,config_prescricao,idgrupo_prescricao,ordem';
    foreach (array_chunk($all, 200) as $chunk) {
        $values=[]; $params=[];
        foreach ($chunk as $row) {
            $rowMarks=array_fill(0,30,'?'); $rowMarks[27]='CAST(? AS jsonb)';
            $values[]='('.implode(',',$rowMarks).')'; array_push($params,...$row);
        }
        $pdo->prepare("INSERT INTO treinos_agendados_exercicios ({$columns}) VALUES ".implode(',',$values))->execute($params);
    }
}

function workoutDefinitionWriteScheduledItems(PDO $pdo, string $appointmentId, array $rows): void
{
    workoutDefinitionWriteScheduledItemsBatch($pdo, [['idagendamento'=>$appointmentId,'rows'=>$rows]]);
}

function workoutDefinitionMaterializeScheduled(PDO $pdo, string $appointmentId, array $definition): void
{
    workoutDefinitionWriteScheduledItems($pdo, $appointmentId, workoutDefinitionRows($definition));
}

function workoutDefinitionQuickCompleteMode(array $definition): string
{
    $hasItems = false;
    $hasKnownTargets = false;
    $hasAmbiguousTargets = false;
    foreach ((array) ($definition['items'] ?? []) as $item) {
        if (!is_array($item)) continue;
        $hasItems = true;
        if ((string) ($item['step_type'] ?? 'exercise') !== 'exercise') {
            $endurance = (array) ($item['endurance'] ?? []);
            $legacy = (array) ($item['legacy'] ?? []);
            if (($endurance['alvo_min'] ?? null) !== null || ($endurance['alvo_max'] ?? null) !== null || trim((string) ($legacy['duracao'] ?? '')) !== '' || trim((string) ($legacy['distancia'] ?? '')) !== '') $hasKnownTargets = true;
            continue;
        }
        $method = (string) ($item['prescription_method'] ?? 'standard');
        $config = is_array($item['prescription'] ?? null) ? $item['prescription'] : [];
        if ($method === 'standard') {
            $reps = is_array($config['reps'] ?? null) ? $config['reps'] : [];
            $mode = (string) ($reps['mode'] ?? '');
            if (in_array($mode, ['range','amrap','failure'], true)) $hasAmbiguousTargets = true;
            if ($mode === 'fixed' || isset($config['duration_s']) || isset($config['distance_m']) || is_array($config['load'] ?? null)) $hasKnownTargets = true;
            continue;
        }
        if ($method === 'cluster') {
            $clusters = (array) ($config['clusters'] ?? []);
            if ($clusters !== []) $hasKnownTargets = true;
            continue;
        }
        if ($method === 'drop_set') {
            foreach ((array) ($config['stages'] ?? []) as $stage) {
                if (!is_array($stage)) continue;
                $reps = is_array($stage['reps'] ?? null) ? $stage['reps'] : [];
                $mode = (string) ($reps['mode'] ?? '');
                if (in_array($mode, ['range','amrap','failure'], true)) $hasAmbiguousTargets = true;
                if ($mode === 'fixed' || is_array($stage['load'] ?? null)) $hasKnownTargets = true;
            }
        }
    }
    if (!$hasItems) return 'unsupported';
    if ($hasAmbiguousTargets) return 'ambiguous';
    if ($hasKnownTargets) return 'exact';
    return 'partial';
}

function workoutDefinitionCapabilities(array $definition): array
{
    $items = array_values(array_filter((array) ($definition['items'] ?? []), 'is_array'));
    $mode = workoutDefinitionQuickCompleteMode($definition);
    return [
        'can_start_session' => $items !== [],
        'can_quick_complete' => $items !== [],
        'quick_complete_mode' => $mode,
    ];
}

function workoutDefinitionPresentation(array $definition): array
{
    $items = [];
    foreach ((array) ($definition['items'] ?? []) as $item) {
        if (!is_array($item)) continue;
        $row = workoutDefinitionRows(['items' => [$item]])[0] ?? [];
        $summary = '';
        try {
            $summary = workoutPrescriptionSummary($row);
        } catch (Throwable) {
            $summary = '';
        }
        $items[] = [
            'order' => (int) ($item['order'] ?? count($items) + 1),
            'exercise_id' => $item['exercise_id'] ?? null,
            'name' => (string) ($item['name'] ?? ''),
            'step_type' => (string) ($item['step_type'] ?? 'exercise'),
            'tracking_mode' => (string) ($item['tracking_mode'] ?? 'load_reps'),
            'prescription_method' => (string) ($item['prescription_method'] ?? 'standard'),
            'summary' => $summary,
            'notes' => $item['notes'] ?? null,
            'group_key' => is_array($item['group'] ?? null) ? ($item['group']['key'] ?? null) : null,
        ];
    }
    $groups = [];
    foreach ((array) ($definition['groups'] ?? []) as $group) {
        if (!is_array($group)) continue;
        $groups[] = [
            'key' => (string) ($group['key'] ?? ''),
            'type' => (string) ($group['type'] ?? ''),
            'rounds' => (int) ($group['rounds'] ?? 1),
            'rest_between_exercises_s' => $group['rest_between_exercises_s'] ?? null,
            'rest_after_round_s' => $group['rest_after_round_s'] ?? null,
            'members' => array_values((array) ($group['members'] ?? [])),
        ];
    }
    return [
        'workout' => $definition['workout'] ?? [],
        'items' => $items,
        'groups' => $groups,
        'capabilities' => workoutDefinitionCapabilities($definition),
    ];
}
