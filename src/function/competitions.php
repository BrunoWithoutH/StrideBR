<?php

declare(strict_types=1);

function competitionTableExists(PDO $pdo): bool
{
    static $cache = [];
    $key = spl_object_id($pdo);
    if (array_key_exists($key, $cache)) return $cache[$key];
    try {
        $cache[$key] = stridebr_db_bool($pdo->query("SELECT to_regclass('stridebr.competicoes_usuario') IS NOT NULL")->fetchColumn());
    } catch (Throwable) {
        $cache[$key] = false;
    }
    return $cache[$key];
}

function competitionHasActivityLink(PDO $pdo): bool
{
    return competitionTableExists($pdo) && stridebr_db_column_exists($pdo, 'registros_atividade', 'idcompeticao');
}

function competitionHasBenchmarkLink(PDO $pdo): bool
{
    return competitionTableExists($pdo) && stridebr_db_column_exists($pdo, 'benchmarks_usuario', 'idcompeticao');
}

function competitionStatusLabel(string $status): string
{
    return stridebr_t('competitions.status.' . str_replace('-', '_', $status));
}

function competitionOfficialityLabel(string $officiality): string
{
    return match ($officiality) {
        'informado_oficial' => stridebr_t('competitions.reported_official'),
        'verificado' => stridebr_t('competitions.verified'),
        'nao_oficial' => stridebr_t('competitions.not_official'),
        default => '',
    };
}

function competitionModalityRow(PDO $pdo, string $userId, string $modalityId): ?array
{
    if ($modalityId === '') return null;
    $stmt = $pdo->prepare('SELECT idmodalidade,nome,slug,familia_hub FROM modalidades WHERE idmodalidade=:modalidade AND (idusuario IS NULL OR idusuario=:usuario) LIMIT 1');
    $stmt->execute([':modalidade' => $modalityId, ':usuario' => $userId]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function competitionPublicEventRow(PDO $pdo, string $eventId): ?array
{
    if ($eventId === '') return null;
    $stmt = $pdo->prepare('SELECT e.*,m.nome AS modalidade_nome,m.slug AS modalidade_slug FROM eventos_esportivos e LEFT JOIN modalidades m ON m.idmodalidade=e.idmodalidade WHERE e.idevento=:evento LIMIT 1');
    $stmt->execute([':evento' => $eventId]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function competitionNormalizeDate(?string $raw, string $fieldKey, bool $required = false): ?string
{
    $raw = trim((string) $raw);
    if ($raw === '') {
        if ($required) throw new InvalidArgumentException(stridebr_t($fieldKey));
        return null;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
    if (!$date || $date->format('Y-m-d') !== $raw) throw new InvalidArgumentException(stridebr_t($fieldKey));
    return $raw;
}

function competitionNormalizeInput(PDO $pdo, string $userId, array $input, ?array $existing = null): array
{
    $name = trim((string) ($input['nome'] ?? $existing['nome'] ?? ''));
    if (stridebr_length($name) < 3 || stridebr_length($name) > 160) throw new InvalidArgumentException(stridebr_t('competitions.error.invalid_name'));

    $start = competitionNormalizeDate($input['data_inicio'] ?? $existing['data_inicio'] ?? null, 'competitions.error.invalid_start', true);
    $end = competitionNormalizeDate($input['data_fim'] ?? $existing['data_fim'] ?? null, 'competitions.error.invalid_end');
    if ($end !== null && strcmp($end, (string) $start) < 0) throw new InvalidArgumentException(stridebr_t('competitions.error.invalid_period'));

    $status = stridebr_lower(trim((string) ($input['status'] ?? $existing['status'] ?? '')));
    if ($status === '') $status = strcmp((string) $start, (new DateTimeImmutable('today'))->format('Y-m-d')) > 0 ? 'planejada' : 'realizada';
    if (!in_array($status, ['planejada', 'realizada', 'cancelada'], true)) throw new InvalidArgumentException(stridebr_t('competitions.error.invalid_status'));

    $origin = stridebr_lower(trim((string) ($input['origem'] ?? $existing['origem'] ?? 'manual')));
    if (!in_array($origin, ['manual', 'catalogo', 'importacao', 'api'], true)) throw new InvalidArgumentException(stridebr_t('competitions.error.invalid_origin'));

    $officiality = stridebr_lower(trim((string) ($input['oficialidade'] ?? $existing['oficialidade'] ?? 'nao_informada')));
    if (!in_array($officiality, ['nao_informada', 'nao_oficial', 'informado_oficial', 'verificado'], true)) throw new InvalidArgumentException(stridebr_t('competitions.error.invalid_officiality'));
    if ($origin === 'manual' && $officiality === 'verificado') throw new InvalidArgumentException(stridebr_t('competitions.error.verified_not_available'));

    $modalityId = trim((string) ($input['idmodalidade_principal'] ?? $existing['idmodalidade_principal'] ?? ''));
    if ($modalityId !== '' && competitionModalityRow($pdo, $userId, $modalityId) === null) throw new InvalidArgumentException(stridebr_t('competitions.error.invalid_modality'));

    $eventId = trim((string) ($input['idevento'] ?? $existing['idevento'] ?? ''));
    if ($eventId !== '' && competitionPublicEventRow($pdo, $eventId) === null) throw new InvalidArgumentException(stridebr_t('competitions.error.invalid_event'));

    $fields = [];
    foreach ([
        'tipo' => 40,
        'organizador' => 160,
        'local_nome' => 160,
        'cidade' => 100,
        'estado' => 80,
        'pais' => 80,
        'nivel' => 80,
        'observacoes' => 4000,
    ] as $field => $max) {
        $value = trim((string) ($input[$field] ?? $existing[$field] ?? ''));
        if ($value !== '' && stridebr_length($value) > $max) throw new InvalidArgumentException(stridebr_t('competitions.error.field_too_long'));
        $fields[$field] = $value !== '' ? $value : null;
    }

    return [
        'nome' => $name,
        'data_inicio' => $start,
        'data_fim' => $end,
        'idmodalidade_principal' => $modalityId !== '' ? $modalityId : null,
        'idevento' => $eventId !== '' ? $eventId : null,
        'tipo' => $fields['tipo'],
        'organizador' => $fields['organizador'],
        'local_nome' => $fields['local_nome'],
        'cidade' => $fields['cidade'],
        'estado' => $fields['estado'],
        'pais' => $fields['pais'],
        'nivel' => $fields['nivel'],
        'oficialidade' => $officiality,
        'observacoes' => $fields['observacoes'],
        'status' => $status,
        'origem' => $origin,
    ];
}


function competitionAvailableModalities(PDO $pdo, string $userId): array
{
    $stmt = $pdo->prepare('SELECT idmodalidade,nome,slug,familia_hub FROM modalidades WHERE ativo=TRUE AND (idusuario IS NULL OR idusuario=:usuario) ORDER BY nome');
    $stmt->execute([':usuario'=>$userId]);
    return $stmt->fetchAll();
}

function competitionRecentForProgress(PDO $pdo, string $userId, string|array|null $modalityIds = null, int $limit = 3): array
{
    if (!competitionTableExists($pdo)) return [];
    $limit = max(1, min(10, $limit));
    $sql = "SELECT DISTINCT c.*,m.nome AS modalidade_nome,m.slug AS modalidade_slug,(SELECT COUNT(*) FROM registros_atividade ra2 WHERE ra2.idcompeticao=c.idcompeticao AND ra2.idusuario=c.idusuario AND ra2.excluido_em IS NULL) AS atividades_count,(SELECT COUNT(*) FROM benchmarks_usuario b2 LEFT JOIN registros_atividade bra2 ON bra2.idregistro=b2.idregistro WHERE b2.idusuario=c.idusuario AND (b2.idcompeticao=c.idcompeticao OR bra2.idcompeticao=c.idcompeticao)) AS benchmarks_count FROM competicoes_usuario c LEFT JOIN modalidades m ON m.idmodalidade=c.idmodalidade_principal WHERE c.idusuario=:usuario AND c.status<>'cancelada'";
    $params = [':usuario' => $userId];
    $ids = is_array($modalityIds) ? $modalityIds : ($modalityIds !== null && $modalityIds !== '' ? [$modalityIds] : []);
    $ids = array_values(array_unique(array_filter(array_map(static fn($id): string => trim((string) $id), $ids), static fn(string $id): bool => $id !== '')));
    if ($ids !== []) {
        $holderGroups = ['main' => [], 'activity' => [], 'benchmark' => []];
        foreach ($ids as $index => $id) {
            foreach (array_keys($holderGroups) as $group) {
                $holder = ':' . $group . '_modalidade' . $index;
                $holderGroups[$group][] = $holder;
                $params[$holder] = $id;
            }
        }
        $mainIn = implode(',', $holderGroups['main']);
        $activityIn = implode(',', $holderGroups['activity']);
        $benchmarkIn = implode(',', $holderGroups['benchmark']);
        $sql .= " AND (c.idmodalidade_principal IN ({$mainIn}) OR EXISTS (SELECT 1 FROM registros_atividade ra WHERE ra.idcompeticao=c.idcompeticao AND ra.idusuario=c.idusuario AND ra.idmodalidade IN ({$activityIn}) AND ra.excluido_em IS NULL) OR EXISTS (SELECT 1 FROM benchmarks_usuario b LEFT JOIN registros_atividade rax ON rax.idregistro=b.idregistro WHERE (b.idcompeticao=c.idcompeticao OR rax.idcompeticao=c.idcompeticao) AND b.idusuario=c.idusuario AND b.idmodalidade IN ({$benchmarkIn})))";
    }
    $sql .= " ORDER BY c.data_inicio DESC,c.data_criacao DESC LIMIT {$limit}";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function competitionFindByEvent(PDO $pdo, string $userId, string $eventId): ?array
{
    if (!competitionTableExists($pdo) || trim($eventId) === '') return null;
    $stmt=$pdo->prepare('SELECT idcompeticao FROM competicoes_usuario WHERE idusuario=:usuario AND idevento=:evento ORDER BY data_criacao LIMIT 1');
    $stmt->execute([':usuario'=>$userId,':evento'=>$eventId]);
    $id=trim((string)$stmt->fetchColumn());
    return $id !== '' ? competitionGet($pdo,$userId,$id) : null;
}

function competitionCreate(PDO $pdo, string $userId, array $input): array
{
    if (!competitionTableExists($pdo)) throw new RuntimeException(stridebr_t('competitions.error.unavailable'));
    $data = competitionNormalizeInput($pdo, $userId, $input);
    $id = stridebr_generate_id();
    $stmt = $pdo->prepare('INSERT INTO competicoes_usuario (idcompeticao,idusuario,nome,data_inicio,data_fim,idmodalidade_principal,idevento,tipo,organizador,local_nome,cidade,estado,pais,nivel,oficialidade,observacoes,status,origem) VALUES (:id,:usuario,:nome,:inicio,:fim,:modalidade,:evento,:tipo,:organizador,:local,:cidade,:estado,:pais,:nivel,:oficialidade,:observacoes,:status,:origem)');
    $stmt->execute([
        ':id'=>$id, ':usuario'=>$userId, ':nome'=>$data['nome'], ':inicio'=>$data['data_inicio'], ':fim'=>$data['data_fim'], ':modalidade'=>$data['idmodalidade_principal'], ':evento'=>$data['idevento'], ':tipo'=>$data['tipo'], ':organizador'=>$data['organizador'], ':local'=>$data['local_nome'], ':cidade'=>$data['cidade'], ':estado'=>$data['estado'], ':pais'=>$data['pais'], ':nivel'=>$data['nivel'], ':oficialidade'=>$data['oficialidade'], ':observacoes'=>$data['observacoes'], ':status'=>$data['status'], ':origem'=>$data['origem'],
    ]);
    return competitionGet($pdo, $userId, $id) ?? throw new RuntimeException(stridebr_t('competitions.error.save_failed'));
}

function competitionGet(PDO $pdo, string $userId, string $competitionId): ?array
{
    if (!competitionTableExists($pdo) || trim($competitionId) === '') return null;
    $stmt = $pdo->prepare('SELECT c.*,m.nome AS modalidade_nome,m.slug AS modalidade_slug,e.titulo AS evento_titulo,e.slug AS evento_slug, (SELECT COUNT(*) FROM registros_atividade ra WHERE ra.idcompeticao=c.idcompeticao AND ra.idusuario=c.idusuario AND ra.excluido_em IS NULL) AS atividades_count, (SELECT COUNT(*) FROM benchmarks_usuario b LEFT JOIN registros_atividade bra ON bra.idregistro=b.idregistro WHERE b.idusuario=c.idusuario AND (b.idcompeticao=c.idcompeticao OR bra.idcompeticao=c.idcompeticao)) AS benchmarks_count FROM competicoes_usuario c LEFT JOIN modalidades m ON m.idmodalidade=c.idmodalidade_principal LEFT JOIN eventos_esportivos e ON e.idevento=c.idevento WHERE c.idcompeticao=:competicao AND c.idusuario=:usuario LIMIT 1');
    $stmt->execute([':competicao'=>$competitionId, ':usuario'=>$userId]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function competitionList(PDO $pdo, string $userId, array $filters = []): array
{
    if (!competitionTableExists($pdo)) return [];
    $sql = 'SELECT c.*,m.nome AS modalidade_nome,m.slug AS modalidade_slug,e.titulo AS evento_titulo,e.slug AS evento_slug, (SELECT COUNT(*) FROM registros_atividade ra WHERE ra.idcompeticao=c.idcompeticao AND ra.idusuario=c.idusuario AND ra.excluido_em IS NULL) AS atividades_count, (SELECT COUNT(*) FROM benchmarks_usuario b LEFT JOIN registros_atividade bra ON bra.idregistro=b.idregistro WHERE b.idusuario=c.idusuario AND (b.idcompeticao=c.idcompeticao OR bra.idcompeticao=c.idcompeticao)) AS benchmarks_count FROM competicoes_usuario c LEFT JOIN modalidades m ON m.idmodalidade=c.idmodalidade_principal LEFT JOIN eventos_esportivos e ON e.idevento=c.idevento WHERE c.idusuario=:usuario';
    $params = [':usuario'=>$userId];
    if (!empty($filters['status'])) { $sql .= ' AND c.status=:status'; $params[':status']=(string)$filters['status']; }
    if (!empty($filters['modality_id'])) { $sql .= ' AND c.idmodalidade_principal=:modalidade'; $params[':modalidade']=(string)$filters['modality_id']; }
    $sql .= ' ORDER BY CASE WHEN c.status=\'planejada\' THEN 0 ELSE 1 END, CASE WHEN c.status=\'planejada\' THEN c.data_inicio END ASC NULLS LAST, c.data_inicio DESC, c.data_criacao DESC';
    $stmt = $pdo->prepare($sql); $stmt->execute($params); return $stmt->fetchAll();
}

function competitionUpdate(PDO $pdo, string $userId, string $competitionId, array $input): array
{
    $existing = competitionGet($pdo, $userId, $competitionId);
    if ($existing === null) throw new InvalidArgumentException(stridebr_t('competitions.error.not_found'));
    $data = competitionNormalizeInput($pdo, $userId, $input, $existing);
    $stmt = $pdo->prepare('UPDATE competicoes_usuario SET nome=:nome,data_inicio=:inicio,data_fim=:fim,idmodalidade_principal=:modalidade,idevento=:evento,tipo=:tipo,organizador=:organizador,local_nome=:local,cidade=:cidade,estado=:estado,pais=:pais,nivel=:nivel,oficialidade=:oficialidade,observacoes=:observacoes,status=:status,origem=:origem,data_atualizacao=NOW() WHERE idcompeticao=:id AND idusuario=:usuario');
    $stmt->execute([
        ':nome'=>$data['nome'], ':inicio'=>$data['data_inicio'], ':fim'=>$data['data_fim'], ':modalidade'=>$data['idmodalidade_principal'], ':evento'=>$data['idevento'], ':tipo'=>$data['tipo'], ':organizador'=>$data['organizador'], ':local'=>$data['local_nome'], ':cidade'=>$data['cidade'], ':estado'=>$data['estado'], ':pais'=>$data['pais'], ':nivel'=>$data['nivel'], ':oficialidade'=>$data['oficialidade'], ':observacoes'=>$data['observacoes'], ':status'=>$data['status'], ':origem'=>$data['origem'], ':id'=>$competitionId, ':usuario'=>$userId,
    ]);
    return competitionGet($pdo, $userId, $competitionId) ?? throw new RuntimeException(stridebr_t('competitions.error.save_failed'));
}

function competitionDelete(PDO $pdo, string $userId, string $competitionId): bool
{
    if (!competitionTableExists($pdo)) return false;
    $stmt = $pdo->prepare('DELETE FROM competicoes_usuario WHERE idcompeticao=:id AND idusuario=:usuario');
    $stmt->execute([':id'=>$competitionId, ':usuario'=>$userId]);
    return $stmt->rowCount() > 0;
}

function competitionNearby(PDO $pdo, string $userId, ?string $date = null, int $limit = 12): array
{
    if (!competitionTableExists($pdo)) return [];
    $date = trim((string) $date);
    if ($date === '') $date = (new DateTimeImmutable('today'))->format('Y-m-d');
    $limit = max(1, min(30, $limit));
    $stmt = $pdo->prepare("SELECT c.*,m.nome AS modalidade_nome,m.slug AS modalidade_slug, CASE WHEN CAST(:data AS date) BETWEEN c.data_inicio AND COALESCE(c.data_fim,c.data_inicio) THEN 0 WHEN c.data_inicio >= CAST(:data AS date) THEN 1 ELSE 2 END AS proximity_group, LEAST(ABS(c.data_inicio-CAST(:data AS date)),ABS(COALESCE(c.data_fim,c.data_inicio)-CAST(:data AS date))) AS proximity_days FROM competicoes_usuario c LEFT JOIN modalidades m ON m.idmodalidade=c.idmodalidade_principal WHERE c.idusuario=:usuario AND c.status <> 'cancelada' AND c.data_inicio BETWEEN CAST(:data AS date)-INTERVAL '365 days' AND CAST(:data AS date)+INTERVAL '365 days' ORDER BY proximity_group,proximity_days,c.data_inicio DESC LIMIT {$limit}");
    $stmt->execute([':usuario'=>$userId, ':data'=>$date]);
    return $stmt->fetchAll();
}

function competitionDateRelation(array $competition, string $date): string
{
    $date = trim($date);
    if ($date === '') return 'unknown';
    $start = (string) ($competition['data_inicio'] ?? '');
    $end = trim((string) ($competition['data_fim'] ?? '')) ?: $start;
    if ($start === '') return 'unknown';
    if ($date >= $start && $date <= $end) return 'inside';
    try {
        $target = new DateTimeImmutable($date); $boundary = new DateTimeImmutable($date < $start ? $start : $end);
        return abs((int)$target->diff($boundary)->format('%r%a')) <= 2 ? 'near' : 'outside';
    } catch (Throwable) { return 'unknown'; }
}

function competitionValidateOwned(PDO $pdo, string $userId, ?string $competitionId): ?array
{
    $competitionId = trim((string) $competitionId);
    if ($competitionId === '') return null;
    $row = competitionGet($pdo, $userId, $competitionId);
    if ($row === null) throw new InvalidArgumentException(stridebr_t('competitions.error.invalid_competition'));
    return $row;
}

function competitionLinkActivity(PDO $pdo, string $userId, string $activityId, ?string $competitionId): void
{
    if (!competitionHasActivityLink($pdo)) return;
    $competition = competitionValidateOwned($pdo, $userId, $competitionId);
    $stmt = $pdo->prepare('UPDATE registros_atividade SET idcompeticao=:competicao,data_atualizacao=NOW() WHERE idregistro=:registro AND idusuario=:usuario AND excluido_em IS NULL');
    $stmt->execute([':competicao'=>$competition['idcompeticao'] ?? null, ':registro'=>$activityId, ':usuario'=>$userId]);
    if ($stmt->rowCount() < 1) throw new InvalidArgumentException(stridebr_t('competitions.error.not_found'));
}

function competitionLinkBenchmark(PDO $pdo, string $userId, string $benchmarkId, ?string $competitionId): void
{
    if (!competitionHasBenchmarkLink($pdo)) return;
    $benchmarkStmt = $pdo->prepare('SELECT b.idbenchmark,b.idregistro,ra.idcompeticao AS activity_competition FROM benchmarks_usuario b LEFT JOIN registros_atividade ra ON ra.idregistro=b.idregistro WHERE b.idbenchmark=:benchmark AND b.idusuario=:usuario LIMIT 1');
    $benchmarkStmt->execute([':benchmark'=>$benchmarkId, ':usuario'=>$userId]);
    $benchmark = $benchmarkStmt->fetch();
    if (!is_array($benchmark)) throw new InvalidArgumentException(stridebr_t('benchmarks.error.not_found'));
    $competition = competitionValidateOwned($pdo, $userId, $competitionId);
    $activityId = trim((string) ($benchmark['idregistro'] ?? ''));
    $activityCompetition = trim((string) ($benchmark['activity_competition'] ?? ''));
    $requested = (string) ($competition['idcompeticao'] ?? '');
    if ($activityId !== '') {
        if ($requested !== '' && $activityCompetition !== '' && $activityCompetition !== $requested) throw new InvalidArgumentException(stridebr_t('competitions.error.benchmark_conflict'));
        if ($requested !== '' && $activityCompetition === '') throw new InvalidArgumentException(stridebr_t('competitions.error.benchmark_activity_competition'));
        $requested = '';
    }
    $pdo->prepare('UPDATE benchmarks_usuario SET idcompeticao=:competicao,data_atualizacao=NOW() WHERE idbenchmark=:benchmark AND idusuario=:usuario')->execute([':competicao'=>$requested !== '' ? $requested : null, ':benchmark'=>$benchmarkId, ':usuario'=>$userId]);
}

function competitionActivities(PDO $pdo, string $userId, string $competitionId): array
{
    if (!competitionHasActivityLink($pdo)) return [];
    $stmt = $pdo->prepare('SELECT ra.*,m.nome AS modalidade_nome,m.slug AS modalidade_slug FROM registros_atividade ra LEFT JOIN modalidades m ON m.idmodalidade=ra.idmodalidade WHERE ra.idusuario=:usuario AND ra.idcompeticao=:competicao AND ra.excluido_em IS NULL ORDER BY ra.data_inicio,ra.idregistro');
    $stmt->execute([':usuario'=>$userId, ':competicao'=>$competitionId]);
    return $stmt->fetchAll();
}

function competitionBenchmarks(PDO $pdo, string $userId, string $competitionId): array
{
    if (!competitionHasBenchmarkLink($pdo)) return [];
    $stmt = $pdo->prepare('SELECT b.*,m.nome AS modalidade_nome,m.slug AS modalidade_slug,e.nome AS exercicio_nome,ra.titulo AS atividade_titulo FROM benchmarks_usuario b JOIN modalidades m ON m.idmodalidade=b.idmodalidade LEFT JOIN exercicios e ON e.idexercicio=b.idexercicio LEFT JOIN registros_atividade ra ON ra.idregistro=b.idregistro WHERE b.idusuario=:usuario AND (b.idcompeticao=:competicao OR (b.idregistro IS NOT NULL AND ra.idcompeticao=:competicao)) ORDER BY b.data_resultado,b.data_criacao');
    $stmt->execute([':usuario'=>$userId, ':competicao'=>$competitionId]);
    return $stmt->fetchAll();
}

function competitionPrefillFromEvent(PDO $pdo, string $eventId): array
{
    $event = competitionPublicEventRow($pdo, $eventId);
    if ($event === null) return [];
    try { $start=(new DateTimeImmutable((string)$event['data_inicio']))->format('Y-m-d'); } catch(Throwable) { $start=''; }
    try { $end=trim((string)($event['data_fim']??''))!==''?(new DateTimeImmutable((string)$event['data_fim']))->format('Y-m-d'):null; } catch(Throwable) { $end=null; }
    return [
        'nome'=>(string)($event['titulo']??''), 'data_inicio'=>$start, 'data_fim'=>$end,
        'idmodalidade_principal'=>$event['idmodalidade']??null, 'idevento'=>$event['idevento']??null,
        'tipo'=>$event['tipo']??null, 'organizador'=>$event['organizador']??null,
        'local_nome'=>$event['local_nome']??null, 'cidade'=>$event['cidade']??null, 'estado'=>$event['estado']??null, 'pais'=>$event['pais']??null,
        'status'=>$start !== '' && $start > (new DateTimeImmutable('today'))->format('Y-m-d') ? 'planejada' : 'realizada',
        'origem'=>'catalogo', 'oficialidade'=>'nao_informada',
    ];
}
