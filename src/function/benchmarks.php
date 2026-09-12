<?php

declare(strict_types=1);

require_once __DIR__ . '/competitions.php';

function benchmarkRegistry(): array
{
    return [
        'one_rm' => [
            'label_key' => 'benchmarks.one_rm',
            'unit' => 'kg',
            'direction' => 'higher',
            'primary' => 'best',
            'requires_exercise' => true,
            'requires_distance' => false,
            'families' => ['strength'],
            'slugs' => [],
            'methods' => ['medido'],
            'default_method' => 'medido',
        ],
        'ftp' => [
            'label_key' => 'benchmarks.ftp',
            'unit' => 'w',
            'direction' => 'higher',
            'primary' => 'latest',
            'requires_exercise' => false,
            'requires_distance' => false,
            'families' => ['cardio'],
            'slugs' => ['ciclismo', 'mountain-bike', 'downhill', 'bmx', 'gravel', 'bicicleta-eletrica', 'e-mountain-bike', 'ciclismo-indoor', 'handcycle', 'velomovel'],
            'methods' => ['medido', 'calculado', 'informado'],
            'default_method' => 'informado',
        ],
        'css' => [
            'label_key' => 'benchmarks.css',
            'unit' => 's_per_100m',
            'direction' => 'lower',
            'primary' => 'latest',
            'requires_exercise' => false,
            'requires_distance' => false,
            'families' => ['cardio'],
            'slugs' => ['natacao'],
            'methods' => ['calculado', 'informado'],
            'default_method' => 'informado',
        ],
        'distance_time' => [
            'label_key' => 'benchmarks.distance_time',
            'unit' => 'seconds',
            'direction' => 'lower',
            'primary' => 'best',
            'requires_exercise' => false,
            'requires_distance' => true,
            'families' => ['cardio'],
            'slugs' => ['corrida', 'corrida-em-trilha', 'corrida-em-esteira'],
            'methods' => ['medido', 'informado'],
            'default_method' => 'medido',
        ],
    ];
}

function benchmarkTypeConfig(string $type): ?array
{
    $type = stridebr_lower(trim($type));
    $registry = benchmarkRegistry();
    return $registry[$type] ?? null;
}

function benchmarkTableExists(PDO $pdo): bool
{
    try {
        $stmt = $pdo->query("SELECT to_regclass('stridebr.benchmarks_usuario') IS NOT NULL");
        return stridebr_db_bool($stmt->fetchColumn());
    } catch (Throwable) {
        return false;
    }
}

function benchmarkParseDecimal(mixed $value): ?float
{
    if (is_int($value) || is_float($value)) return is_finite((float) $value) ? (float) $value : null;
    $raw = trim((string) $value);
    if ($raw === '') return null;
    $raw = str_replace([' ', ','], ['', '.'], $raw);
    if (preg_match('/^\d+(?:\.\d+)?$/D', $raw) !== 1) return null;
    $number = (float) $raw;
    return is_finite($number) ? $number : null;
}

function benchmarkParseClockSeconds(mixed $value): ?float
{
    if (is_int($value) || is_float($value)) {
        $number = (float) $value;
        return is_finite($number) && $number > 0 ? $number : null;
    }
    $raw = trim((string) $value);
    if ($raw === '') return null;
    if (preg_match('/^\d+(?:[\.,]\d+)?$/D', $raw) === 1) {
        $number = (float) str_replace(',', '.', $raw);
        return $number > 0 ? $number : null;
    }
    $parts = explode(':', $raw);
    if (count($parts) < 2 || count($parts) > 3) return null;
    foreach ($parts as $part) if (preg_match('/^\d+(?:[\.,]\d+)?$/D', trim($part)) !== 1) return null;
    if (count($parts) === 2) {
        [$minutes, $seconds] = $parts;
        $minutes = (int) $minutes;
        $seconds = (float) str_replace(',', '.', $seconds);
        if ($seconds >= 60) return null;
        $total = ($minutes * 60) + $seconds;
        return $total > 0 ? $total : null;
    }
    [$hours, $minutes, $seconds] = $parts;
    $hours = (int) $hours;
    $minutes = (int) $minutes;
    $seconds = (float) str_replace(',', '.', $seconds);
    if ($minutes >= 60 || $seconds >= 60) return null;
    $total = ($hours * 3600) + ($minutes * 60) + $seconds;
    return $total > 0 ? $total : null;
}

function benchmarkFormatClock(float $seconds): string
{
    $seconds = max(0, (int) round($seconds));
    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    $remaining = $seconds % 60;
    if ($hours > 0) return $hours . ':' . str_pad((string) $minutes, 2, '0', STR_PAD_LEFT) . ':' . str_pad((string) $remaining, 2, '0', STR_PAD_LEFT);
    return $minutes . ':' . str_pad((string) $remaining, 2, '0', STR_PAD_LEFT);
}

function benchmarkFormatDistance(float $meters): string
{
    if (abs($meters - 42195) < .5) return stridebr_format_number(42.195, 3) . ' km';
    if (abs($meters - 21097.5) < .5) return stridebr_format_number(21.1, 1) . ' km';
    if ($meters >= 1000) {
        $km = $meters / 1000;
        return stridebr_format_number($km, abs($km - round($km)) < .0001 ? 0 : 1) . ' km';
    }
    return stridebr_format_number($meters, abs($meters - round($meters)) < .0001 ? 0 : 1) . ' m';
}

function benchmarkFormatValue(string $type, float $value, ?float $distanceM = null): string
{
    return match ($type) {
        'one_rm' => stridebr_format_number($value, abs($value - round($value)) < .001 ? 0 : 1) . ' kg',
        'ftp' => stridebr_format_number($value, 0) . ' W',
        'css' => benchmarkFormatClock($value) . '/100 m',
        'distance_time' => benchmarkFormatClock($value),
        default => stridebr_format_number($value, 2),
    };
}

function benchmarkFormatDifference(string $type, float $difference): string
{
    $sign = $difference > 0 ? '+' : ($difference < 0 ? '−' : '');
    $absolute = abs($difference);
    return match ($type) {
        'one_rm' => $sign . stridebr_format_number($absolute, abs($absolute - round($absolute)) < .001 ? 0 : 1) . ' kg',
        'ftp' => $sign . stridebr_format_number($absolute, 0) . ' W',
        'css' => $sign . stridebr_format_number($absolute, abs($absolute - round($absolute)) < .001 ? 0 : 1) . ' s/100 m',
        'distance_time' => $sign . benchmarkFormatClock($absolute),
        default => $sign . stridebr_format_number($absolute, 2),
    };
}

function benchmarkFormatAbsoluteDifference(string $type, float $difference): string
{
    $absolute = abs($difference);
    return match ($type) {
        'one_rm' => stridebr_format_number($absolute, abs($absolute - round($absolute)) < .001 ? 0 : 1) . ' kg',
        'ftp' => stridebr_format_number($absolute, 0) . ' W',
        'css' => stridebr_format_number($absolute, abs($absolute - round($absolute)) < .001 ? 0 : 1) . ' s/100 m',
        'distance_time' => benchmarkFormatClock($absolute),
        default => stridebr_format_number($absolute, 2),
    };
}

function benchmarkModalityRow(PDO $pdo, string $userId, string $modalityId): ?array
{
    $stmt = $pdo->prepare('SELECT idmodalidade, idusuario, nome, slug, familia_hub FROM modalidades WHERE idmodalidade = :modalidade AND (idusuario IS NULL OR idusuario = :usuario) LIMIT 1');
    $stmt->execute([':modalidade' => $modalityId, ':usuario' => $userId]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function benchmarkTypeSupportsModality(array $config, array $modality): bool
{
    $slug = stridebr_lower(trim((string) ($modality['slug'] ?? '')));
    $family = stridebr_lower(trim((string) ($modality['familia_hub'] ?? '')));
    $slugs = array_map('strval', (array) ($config['slugs'] ?? []));
    if ($slugs !== []) return in_array($slug, $slugs, true);
    return in_array($family, array_map('strval', (array) ($config['families'] ?? [])), true);
}

function benchmarkExerciseRow(PDO $pdo, string $userId, string $exerciseId): ?array
{
    $stmt = $pdo->prepare('SELECT idexercicio, idusuario, nome, ativo FROM exercicios WHERE idexercicio = :exercicio AND (idusuario IS NULL OR idusuario = :usuario) LIMIT 1');
    $stmt->execute([':exercicio' => $exerciseId, ':usuario' => $userId]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function benchmarkActivityEvidence(PDO $pdo, string $userId, ?string $activityId, ?string $unitId): array
{
    $activityId = trim((string) $activityId);
    $unitId = trim((string) $unitId);
    if ($activityId === '' && $unitId === '') return ['activity_id' => null, 'unit_id' => null];
    if ($unitId !== '') {
        $stmt = $pdo->prepare('SELECT ua.idunidade_atividade, ra.idregistro, ra.idmodalidade, ra.idcompeticao FROM unidades_atividade ua JOIN registros_atividade ra ON ra.idregistro = ua.idregistro WHERE ua.idunidade_atividade = :unidade AND ra.idusuario = :usuario LIMIT 1');
        $stmt->execute([':unidade' => $unitId, ':usuario' => $userId]);
        $row = $stmt->fetch();
        if (!is_array($row)) throw new InvalidArgumentException(stridebr_t('benchmarks.error.invalid_activity_reference'));
        if ($activityId !== '' && $activityId !== (string) $row['idregistro']) throw new InvalidArgumentException(stridebr_t('benchmarks.error.invalid_activity_reference'));
        return ['activity_id' => (string) $row['idregistro'], 'unit_id' => (string) $row['idunidade_atividade'], 'modality_id' => (string) $row['idmodalidade'], 'competition_id' => trim((string) ($row['idcompeticao'] ?? '')) ?: null];
    }
    $stmt = $pdo->prepare('SELECT idregistro, idmodalidade, idcompeticao FROM registros_atividade WHERE idregistro = :registro AND idusuario = :usuario LIMIT 1');
    $stmt->execute([':registro' => $activityId, ':usuario' => $userId]);
    $row = $stmt->fetch();
    if (!is_array($row)) throw new InvalidArgumentException(stridebr_t('benchmarks.error.invalid_activity_reference'));
    return ['activity_id' => (string) $row['idregistro'], 'unit_id' => null, 'modality_id' => (string) $row['idmodalidade'], 'competition_id' => trim((string) ($row['idcompeticao'] ?? '')) ?: null];
}

function benchmarkValidateOfficialContext(string $officiality, ?string $context): void
{
    if ($officiality === 'informado_oficial' && $context !== 'competicao') {
        throw new InvalidArgumentException(stridebr_t('benchmarks.error.official_requires_competition'));
    }
}

function benchmarkApplyReportedOfficialInput(array $input, bool $reportedOfficial): array
{
    $input['oficialidade'] = $reportedOfficial ? 'informado_oficial' : 'nao_aplicavel';
    if ($reportedOfficial) $input['contexto'] = 'competicao';
    return $input;
}

function benchmarkNormalizeMetadata(mixed $metadata): array
{
    if ($metadata === null || $metadata === '') return [];
    if (is_string($metadata)) {
        $raw = trim($metadata);
        if ($raw === '') return [];
        try {
            $root = json_decode($raw, false, 512, JSON_THROW_ON_ERROR);
            if (!$root instanceof stdClass) throw new InvalidArgumentException(stridebr_t('benchmarks.error.invalid_metadata'));
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : [];
        } catch (JsonException) {
            throw new InvalidArgumentException(stridebr_t('benchmarks.error.invalid_metadata'));
        }
    }
    if ($metadata instanceof stdClass) $metadata = (array) $metadata;
    if (!is_array($metadata)) throw new InvalidArgumentException(stridebr_t('benchmarks.error.invalid_metadata'));
    if ($metadata !== [] && array_is_list($metadata)) throw new InvalidArgumentException(stridebr_t('benchmarks.error.invalid_metadata'));
    return $metadata;
}

function benchmarkMetadataJson(array $metadata): string
{
    return json_encode((object) $metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}

function benchmarkNormalizeInput(PDO $pdo, string $userId, array $input, ?array $existing = null): array
{
    $type = stridebr_lower(trim((string) ($input['tipo'] ?? $existing['tipo'] ?? '')));
    $config = benchmarkTypeConfig($type);
    if ($config === null) throw new InvalidArgumentException(stridebr_t('benchmarks.error.invalid_type'));

    $modalityId = trim((string) ($input['idmodalidade'] ?? $existing['idmodalidade'] ?? ''));
    $modality = $modalityId !== '' ? benchmarkModalityRow($pdo, $userId, $modalityId) : null;
    if ($modality === null || !benchmarkTypeSupportsModality($config, $modality)) throw new InvalidArgumentException(stridebr_t('benchmarks.error.invalid_modality'));

    $value = benchmarkParseDecimal($input['valor_canonico'] ?? $existing['valor_canonico'] ?? null);
    if ($value === null || $value <= 0 || $value > 100000000) throw new InvalidArgumentException(stridebr_t('benchmarks.error.invalid_value'));

    $dateRaw = trim((string) ($input['data_resultado'] ?? $existing['data_resultado'] ?? ''));
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $dateRaw);
    if (!$date || $date->format('Y-m-d') !== $dateRaw || $date > new DateTimeImmutable('today')) throw new InvalidArgumentException(stridebr_t('benchmarks.error.invalid_date'));

    $exerciseId = trim((string) ($input['idexercicio'] ?? $existing['idexercicio'] ?? ''));
    $snapshot = trim((string) ($input['referencia_nome_snapshot'] ?? $existing['referencia_nome_snapshot'] ?? ''));
    if (!empty($config['requires_exercise'])) {
        if ($exerciseId === '') {
            if (!is_array($existing) || trim((string) ($existing['referencia_nome_snapshot'] ?? '')) === '') throw new InvalidArgumentException(stridebr_t('benchmarks.error.exercise_required'));
            $snapshot = trim((string) ($existing['referencia_nome_snapshot'] ?? ''));
        } else {
            $exercise = benchmarkExerciseRow($pdo, $userId, $exerciseId);
            if ($exercise === null) throw new InvalidArgumentException(stridebr_t('benchmarks.error.invalid_exercise'));
            $snapshot = trim((string) $exercise['nome']);
        }
    } elseif ($exerciseId !== '') {
        $exercise = benchmarkExerciseRow($pdo, $userId, $exerciseId);
        if ($exercise === null) throw new InvalidArgumentException(stridebr_t('benchmarks.error.invalid_exercise'));
        if ($snapshot === '') $snapshot = trim((string) $exercise['nome']);
    } else {
        $exerciseId = '';
    }

    $distance = benchmarkParseDecimal($input['distancia_m'] ?? $existing['distancia_m'] ?? null);
    if (!empty($config['requires_distance']) && ($distance === null || $distance <= 0 || $distance > 1000000)) throw new InvalidArgumentException(stridebr_t('benchmarks.error.distance_required'));
    if (empty($config['requires_distance'])) $distance = null;

    $origin = stridebr_lower(trim((string) ($input['origem'] ?? $existing['origem'] ?? 'manual')));
    if (!in_array($origin, ['manual', 'atividade', 'importacao', 'api'], true)) throw new InvalidArgumentException(stridebr_t('benchmarks.error.invalid_provenance'));

    $method = stridebr_lower(trim((string) ($input['metodo'] ?? $existing['metodo'] ?? $config['default_method'])));
    if (!in_array($method, (array) $config['methods'], true)) throw new InvalidArgumentException(stridebr_t('benchmarks.error.invalid_method'));

    $context = stridebr_lower(trim((string) ($input['contexto'] ?? $existing['contexto'] ?? '')));
    if ($context === '') $context = null;
    if ($context !== null && !in_array($context, ['treino', 'teste', 'competicao'], true)) throw new InvalidArgumentException(stridebr_t('benchmarks.error.invalid_context'));

    $officiality = stridebr_lower(trim((string) ($input['oficialidade'] ?? $existing['oficialidade'] ?? 'nao_aplicavel')));
    if (!in_array($officiality, ['nao_aplicavel', 'nao_oficial', 'informado_oficial', 'verificado'], true)) throw new InvalidArgumentException(stridebr_t('benchmarks.error.invalid_officiality'));
    benchmarkValidateOfficialContext($officiality, $context);
    if ($officiality === 'verificado' && $origin === 'manual') throw new InvalidArgumentException(stridebr_t('benchmarks.error.verified_not_available'));

    $protocol = trim((string) ($input['protocolo'] ?? $existing['protocolo'] ?? ''));
    if (stridebr_length($protocol) > 80) throw new InvalidArgumentException(stridebr_t('benchmarks.error.protocol_long'));
    if ($protocol === '') $protocol = null;

    $notes = trim((string) ($input['observacoes'] ?? $existing['observacoes'] ?? ''));
    if (stridebr_length($notes) > 2000) throw new InvalidArgumentException(stridebr_t('benchmarks.error.notes_long'));
    if ($notes === '') $notes = null;

    $provider = trim((string) ($input['provider'] ?? $existing['provider'] ?? ''));
    $externalId = trim((string) ($input['external_source_id'] ?? $existing['external_source_id'] ?? ''));
    if (($provider === '') !== ($externalId === '')) throw new InvalidArgumentException(stridebr_t('benchmarks.error.invalid_external_source'));
    if (stridebr_length($provider) > 50 || stridebr_length($externalId) > 180) throw new InvalidArgumentException(stridebr_t('benchmarks.error.invalid_external_source'));
    $provider = $provider === '' ? null : $provider;
    $externalId = $externalId === '' ? null : $externalId;

    $metadataSource = array_key_exists('metadados', $input) ? $input['metadados'] : ($existing['metadados'] ?? null);
    $metadata = benchmarkNormalizeMetadata($metadataSource);

    $evidence = benchmarkActivityEvidence($pdo, $userId, $input['idregistro'] ?? $existing['idregistro'] ?? null, $input['idunidade_atividade'] ?? $existing['idunidade_atividade'] ?? null);
    if (isset($evidence['modality_id']) && $evidence['modality_id'] !== $modalityId) throw new InvalidArgumentException(stridebr_t('benchmarks.error.activity_modality_mismatch'));

    $hasActivityEvidence = trim((string) ($evidence['activity_id'] ?? '')) !== '';
    $competitionInputProvided = array_key_exists('idcompeticao', $input);
    $competitionId = trim((string) ($competitionInputProvided ? ($input['idcompeticao'] ?? '') : ($hasActivityEvidence ? '' : ($existing['idcompeticao'] ?? ''))));
    $activityCompetitionId = trim((string) ($evidence['competition_id'] ?? ''));
    if ($hasActivityEvidence) {
        if ($competitionId !== '' && $activityCompetitionId !== '' && $competitionId !== $activityCompetitionId) throw new InvalidArgumentException(stridebr_t('competitions.error.benchmark_conflict'));
        if ($competitionId !== '' && $activityCompetitionId === '') throw new InvalidArgumentException(stridebr_t('competitions.error.benchmark_activity_competition'));
        $competitionId = '';
    } elseif ($competitionId !== '') {
        competitionValidateOwned($pdo, $userId, $competitionId);
    }

    return [
        'tipo' => $type,
        'idmodalidade' => $modalityId,
        'valor_canonico' => $value,
        'data_resultado' => $dateRaw,
        'idexercicio' => $exerciseId !== '' ? $exerciseId : null,
        'idregistro' => $evidence['activity_id'] ?? null,
        'idunidade_atividade' => $evidence['unit_id'] ?? null,
        'idcompeticao' => $competitionId !== '' ? $competitionId : null,
        'referencia_nome_snapshot' => $snapshot !== '' ? $snapshot : null,
        'origem' => $origin,
        'metodo' => $method,
        'contexto' => $context,
        'oficialidade' => $officiality,
        'protocolo' => $protocol,
        'distancia_m' => $distance,
        'provider' => $provider,
        'external_source_id' => $externalId,
        'metadados' => $metadata,
        'observacoes' => $notes,
        'excluido_progresso' => stridebr_db_bool($input['excluido_progresso'] ?? $existing['excluido_progresso'] ?? false),
    ];
}

function benchmarkCreate(PDO $pdo, string $userId, array $input): array
{
    if (!benchmarkTableExists($pdo)) throw new RuntimeException(stridebr_t('benchmarks.error.unavailable'));
    $data = benchmarkNormalizeInput($pdo, $userId, $input);
    $id = stridebr_generate_id();
    $stmt = $pdo->prepare("INSERT INTO benchmarks_usuario (idbenchmark,idusuario,idmodalidade,tipo,valor_canonico,data_resultado,idexercicio,idregistro,idunidade_atividade,idcompeticao,referencia_nome_snapshot,origem,metodo,contexto,oficialidade,protocolo,distancia_m,provider,external_source_id,metadados,observacoes,excluido_progresso) VALUES (:id,:usuario,:modalidade,:tipo,:valor,:data,:exercicio,:registro,:unidade,:competicao,:snapshot,:origem,:metodo,:contexto,:oficialidade,:protocolo,:distancia,:provider,:external_id,CAST(:metadados AS jsonb),:observacoes,CAST(:excluido AS boolean))");
    $stmt->execute([
        ':id' => $id, ':usuario' => $userId, ':modalidade' => $data['idmodalidade'], ':tipo' => $data['tipo'], ':valor' => $data['valor_canonico'], ':data' => $data['data_resultado'], ':exercicio' => $data['idexercicio'], ':registro' => $data['idregistro'], ':unidade' => $data['idunidade_atividade'], ':competicao' => $data['idcompeticao'], ':snapshot' => $data['referencia_nome_snapshot'], ':origem' => $data['origem'], ':metodo' => $data['metodo'], ':contexto' => $data['contexto'], ':oficialidade' => $data['oficialidade'], ':protocolo' => $data['protocolo'], ':distancia' => $data['distancia_m'], ':provider' => $data['provider'], ':external_id' => $data['external_source_id'], ':metadados' => benchmarkMetadataJson($data['metadados']), ':observacoes' => $data['observacoes'], ':excluido' => $data['excluido_progresso'] ? 'true' : 'false',
    ]);
    return benchmarkGet($pdo, $userId, $id) ?? throw new RuntimeException(stridebr_t('benchmarks.error.save_failed'));
}

function benchmarkGet(PDO $pdo, string $userId, string $benchmarkId): ?array
{
    if (!benchmarkTableExists($pdo)) return null;
    $stmt = $pdo->prepare("SELECT b.*,m.nome AS modalidade_nome,m.slug AS modalidade_slug,m.familia_hub,e.nome AS exercicio_nome,ra.titulo AS atividade_titulo,COALESCE(ac.nome,dc.nome) AS competicao_nome,COALESCE(ac.idcompeticao,dc.idcompeticao) AS competicao_efetiva FROM benchmarks_usuario b JOIN modalidades m ON m.idmodalidade=b.idmodalidade LEFT JOIN exercicios e ON e.idexercicio=b.idexercicio LEFT JOIN registros_atividade ra ON ra.idregistro=b.idregistro LEFT JOIN competicoes_usuario dc ON dc.idcompeticao=b.idcompeticao LEFT JOIN competicoes_usuario ac ON ac.idcompeticao=ra.idcompeticao WHERE b.idbenchmark=:benchmark AND b.idusuario=:usuario LIMIT 1");
    $stmt->execute([':benchmark' => $benchmarkId, ':usuario' => $userId]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function benchmarkUpdate(PDO $pdo, string $userId, string $benchmarkId, array $input): array
{
    $existing = benchmarkGet($pdo, $userId, $benchmarkId);
    if ($existing === null) throw new RuntimeException(stridebr_t('benchmarks.error.not_found'));
    $data = benchmarkNormalizeInput($pdo, $userId, $input, $existing);
    $stmt = $pdo->prepare("UPDATE benchmarks_usuario SET idmodalidade=:modalidade,tipo=:tipo,valor_canonico=:valor,data_resultado=:data,idexercicio=:exercicio,idregistro=:registro,idunidade_atividade=:unidade,idcompeticao=:competicao,referencia_nome_snapshot=:snapshot,origem=:origem,metodo=:metodo,contexto=:contexto,oficialidade=:oficialidade,protocolo=:protocolo,distancia_m=:distancia,provider=:provider,external_source_id=:external_id,metadados=CAST(:metadados AS jsonb),observacoes=:observacoes,excluido_progresso=CAST(:excluido AS boolean),data_atualizacao=NOW() WHERE idbenchmark=:benchmark AND idusuario=:usuario");
    $stmt->execute([
        ':modalidade' => $data['idmodalidade'], ':tipo' => $data['tipo'], ':valor' => $data['valor_canonico'], ':data' => $data['data_resultado'], ':exercicio' => $data['idexercicio'], ':registro' => $data['idregistro'], ':unidade' => $data['idunidade_atividade'], ':competicao' => $data['idcompeticao'], ':snapshot' => $data['referencia_nome_snapshot'], ':origem' => $data['origem'], ':metodo' => $data['metodo'], ':contexto' => $data['contexto'], ':oficialidade' => $data['oficialidade'], ':protocolo' => $data['protocolo'], ':distancia' => $data['distancia_m'], ':provider' => $data['provider'], ':external_id' => $data['external_source_id'], ':metadados' => benchmarkMetadataJson($data['metadados']), ':observacoes' => $data['observacoes'], ':excluido' => $data['excluido_progresso'] ? 'true' : 'false', ':benchmark' => $benchmarkId, ':usuario' => $userId,
    ]);
    return benchmarkGet($pdo, $userId, $benchmarkId) ?? throw new RuntimeException(stridebr_t('benchmarks.error.save_failed'));
}

function benchmarkDelete(PDO $pdo, string $userId, string $benchmarkId): bool
{
    if (!benchmarkTableExists($pdo)) return false;
    $stmt = $pdo->prepare('DELETE FROM benchmarks_usuario WHERE idbenchmark=:benchmark AND idusuario=:usuario');
    $stmt->execute([':benchmark' => $benchmarkId, ':usuario' => $userId]);
    return $stmt->rowCount() > 0;
}

function benchmarkList(PDO $pdo, string $userId, array $filters = []): array
{
    if (!benchmarkTableExists($pdo)) return [];
    $where = ['b.idusuario=:usuario'];
    $params = [':usuario' => $userId];
    foreach (['tipo' => 'b.tipo', 'idmodalidade' => 'b.idmodalidade', 'idexercicio' => 'b.idexercicio'] as $key => $column) {
        $value = trim((string) ($filters[$key] ?? ''));
        if ($value !== '') { $where[] = "{$column}=:{$key}"; $params[":{$key}"] = $value; }
    }
    if (isset($filters['distancia_m']) && is_numeric($filters['distancia_m'])) { $where[] = 'ABS(b.distancia_m-:distancia)<0.001'; $params[':distancia'] = (float) $filters['distancia_m']; }
    if (empty($filters['include_excluded'])) $where[] = 'b.excluido_progresso=FALSE';
    $limit = max(1, min(1000, (int) ($filters['limit'] ?? 500)));
    $sql = "SELECT b.*,m.nome AS modalidade_nome,m.slug AS modalidade_slug,m.familia_hub,e.nome AS exercicio_nome,ra.titulo AS atividade_titulo,COALESCE(ac.nome,dc.nome) AS competicao_nome,COALESCE(ac.idcompeticao,dc.idcompeticao) AS competicao_efetiva FROM benchmarks_usuario b JOIN modalidades m ON m.idmodalidade=b.idmodalidade LEFT JOIN exercicios e ON e.idexercicio=b.idexercicio LEFT JOIN registros_atividade ra ON ra.idregistro=b.idregistro LEFT JOIN competicoes_usuario dc ON dc.idcompeticao=b.idcompeticao AND dc.idusuario=b.idusuario LEFT JOIN competicoes_usuario ac ON ac.idcompeticao=ra.idcompeticao AND ac.idusuario=b.idusuario WHERE " . implode(' AND ', $where) . " ORDER BY b.data_resultado DESC,b.data_criacao DESC LIMIT {$limit}";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function benchmarkSummarize(array $rows, string $type): array
{
    $config = benchmarkTypeConfig($type);
    if ($config === null) return ['latest' => null, 'best' => null, 'previous' => null, 'history' => []];
    $rows = array_values(array_filter($rows, static fn(array $row): bool => (string) ($row['tipo'] ?? '') === $type && !stridebr_db_bool($row['excluido_progresso'] ?? false)));
    usort($rows, static function (array $a, array $b): int {
        $date = strcmp((string) ($b['data_resultado'] ?? ''), (string) ($a['data_resultado'] ?? ''));
        if ($date !== 0) return $date;
        return strcmp((string) ($b['data_criacao'] ?? ''), (string) ($a['data_criacao'] ?? ''));
    });
    $latest = $rows[0] ?? null;
    $previous = $rows[1] ?? null;
    $best = null;
    foreach ($rows as $row) {
        if (!is_numeric($row['valor_canonico'] ?? null)) continue;
        if ($best === null) { $best = $row; continue; }
        $value = (float) $row['valor_canonico'];
        $bestValue = (float) $best['valor_canonico'];
        if (($config['direction'] === 'lower' && $value < $bestValue) || ($config['direction'] === 'higher' && $value > $bestValue)) $best = $row;
    }
    return ['latest' => $latest, 'best' => $best, 'previous' => $previous, 'primary' => $config['primary'] === 'latest' ? $latest : $best, 'history' => $rows];
}

function benchmarkReferenceGroupKey(array $row): string
{
    $exerciseId = trim((string) ($row['idexercicio'] ?? ''));
    if ($exerciseId !== '') return $exerciseId;
    $snapshot = stridebr_lower(trim((string) ($row['referencia_nome_snapshot'] ?? $row['exercicio_nome'] ?? '')));
    return $snapshot !== '' ? 'snapshot:' . hash('sha256', $snapshot) : 'snapshot:unknown';
}

function benchmarkGroupDistanceTests(array $rows): array
{
    $groups = [];
    foreach ($rows as $row) {
        if (($row['tipo'] ?? '') !== 'distance_time' || !is_numeric($row['distancia_m'] ?? null)) continue;
        $distance = round((float) $row['distancia_m'], 3);
        $key = number_format($distance, 3, '.', '');
        $groups[$key]['distance_m'] = $distance;
        $groups[$key]['rows'][] = $row;
    }
    foreach ($groups as &$group) $group['summary'] = benchmarkSummarize((array) $group['rows'], 'distance_time');
    unset($group);
    uasort($groups, static fn(array $a, array $b): int => ($a['distance_m'] <=> $b['distance_m']));
    return $groups;
}

function benchmarkObservationPersisted(array $row): array
{
    $type = (string) ($row['tipo'] ?? '');
    $config = benchmarkTypeConfig($type) ?? ['unit' => '', 'direction' => 'higher'];
    return [
        'kind' => 'persisted',
        'type' => $type,
        'modality_id' => (string) ($row['idmodalidade'] ?? ''),
        'modality_slug' => (string) ($row['modalidade_slug'] ?? ''),
        'reference_id' => $row['idexercicio'] ?? null,
        'reference_name' => (string) ($row['referencia_nome_snapshot'] ?: ($row['exercicio_nome'] ?? '')),
        'value' => is_numeric($row['valor_canonico'] ?? null) ? (float) $row['valor_canonico'] : null,
        'unit' => (string) ($config['unit'] ?? ''),
        'date' => (string) ($row['data_resultado'] ?? ''),
        'direction' => (string) ($config['direction'] ?? 'higher'),
        'origin' => (string) ($row['origem'] ?? ''),
        'method' => (string) ($row['metodo'] ?? ''),
        'context' => $row['contexto'] ?? null,
        'officiality' => (string) ($row['oficialidade'] ?? 'nao_aplicavel'),
        'evidence' => ['activity_id' => $row['idregistro'] ?? null, 'unit_id' => $row['idunidade_atividade'] ?? null],
        'protocol' => ['name' => $row['protocolo'] ?? null, 'distance_m' => is_numeric($row['distancia_m'] ?? null) ? (float) $row['distancia_m'] : null],
        'eligible_progress' => !stridebr_db_bool($row['excluido_progresso'] ?? false),
    ];
}

function benchmarkObservationEstimatedOneRm(array $exercise): ?array
{
    if (!is_numeric($exercise['current_best_e1rm'] ?? null)) return null;
    $source = (array) ($exercise['current_best_e1rm_source'] ?? []);
    return [
        'kind' => 'derived',
        'type' => 'estimated_one_rm',
        'reference_id' => $exercise['key'] ?? null,
        'reference_name' => (string) ($exercise['nome'] ?? ''),
        'value' => (float) $exercise['current_best_e1rm'],
        'unit' => 'kg',
        'date' => (string) ($source['date'] ?? $exercise['latest_date'] ?? ''),
        'direction' => 'higher',
        'origin' => 'atividade',
        'method' => 'estimado',
        'context' => 'treino',
        'officiality' => 'nao_aplicavel',
        'evidence' => ['activity_id' => $source['idregistro'] ?? null, 'load_kg' => $source['load_kg'] ?? null, 'reps' => $source['reps'] ?? null],
        'protocol' => ['name' => 'Epley'],
        'eligible_progress' => true,
    ];
}

function benchmarkObservationAthletics(array $record, string $type): ?array
{
    $valueKey = $type === 'athletics_time' ? 'best_time_s' : 'best_mark';
    if (!is_numeric($record[$valueKey] ?? null)) return null;
    return [
        'kind' => 'derived',
        'type' => $type,
        'reference_id' => $record['slug'] ?? null,
        'reference_name' => (string) ($record['nome'] ?? ''),
        'value' => (float) $record[$valueKey],
        'unit' => $type === 'athletics_time' ? 'seconds' : 'm',
        'date' => (string) ($record['date'] ?? ''),
        'direction' => $type === 'athletics_time' ? 'lower' : 'higher',
        'origin' => 'atividade',
        'method' => 'medido',
        'context' => null,
        'officiality' => 'nao_aplicavel',
        'evidence' => ['activity_id' => $record['idregistro'] ?? null, 'unit_id' => $record['idunidade_atividade'] ?? null],
        'protocol' => [],
        'eligible_progress' => true,
    ];
}

function benchmarkHistoryStart(PDO $pdo, string $userId, ?string $modalityId = null): ?DateTimeImmutable
{
    if (!benchmarkTableExists($pdo)) return null;
    $sql = 'SELECT MIN(data_resultado) FROM benchmarks_usuario WHERE idusuario=:usuario AND excluido_progresso=FALSE';
    $params = [':usuario'=>$userId];
    if ($modalityId !== null && $modalityId !== '') { $sql .= ' AND idmodalidade=:modalidade'; $params[':modalidade']=$modalityId; }
    $stmt=$pdo->prepare($sql);
    $stmt->execute($params);
    $value=trim((string)$stmt->fetchColumn());
    if ($value==='') return null;
    try { return new DateTimeImmutable($value, new DateTimeZone('America/Sao_Paulo')); } catch (Throwable) { return null; }
}

function benchmarkAvailableExercises(PDO $pdo, string $userId): array
{
    $stmt = $pdo->prepare('SELECT idexercicio,nome FROM exercicios WHERE ativo=TRUE AND (idusuario IS NULL OR idusuario=:usuario) ORDER BY nome');
    $stmt->execute([':usuario' => $userId]);
    return $stmt->fetchAll();
}

function benchmarkHighlightFacts(array $rows, DateTimeImmutable $start, DateTimeImmutable $end): array
{
    $groups = [];
    foreach ($rows as $row) {
        if (stridebr_db_bool($row['excluido_progresso'] ?? false)) continue;
        $type = (string) ($row['tipo'] ?? '');
        $config = benchmarkTypeConfig($type);
        if ($config === null) continue;
        $key = $type;
        if ($type === 'one_rm') $key .= ':' . benchmarkReferenceGroupKey($row);
        if ($type === 'distance_time') $key .= ':' . number_format((float) ($row['distancia_m'] ?? 0), 3, '.', '');
        $groups[$key][] = $row;
    }
    $facts = [];
    foreach ($groups as $items) {
        usort($items, static fn(array $a, array $b): int => [strval($a['data_resultado'] ?? ''), strval($a['data_criacao'] ?? '')] <=> [strval($b['data_resultado'] ?? ''), strval($b['data_criacao'] ?? '')]);
        $current = [];
        $before = [];
        foreach ($items as $item) {
            try { $date = new DateTimeImmutable((string) ($item['data_resultado'] ?? '')); } catch (Throwable) { continue; }
            if ($date >= $start && $date < $end) $current[] = $item;
            elseif ($date < $start) $before[] = $item;
        }
        if ($current === []) continue;
        $type = (string) ($current[0]['tipo'] ?? '');
        $config = benchmarkTypeConfig($type);
        if ($config === null) continue;
        if ($type === 'ftp' || $type === 'css') {
            $candidate = end($current);
            $previous = $before !== [] ? end($before) : (count($current) > 1 ? $current[count($current)-2] : null);
            if (!is_array($previous)) continue;
            $difference = (float) $candidate['valor_canonico'] - (float) $previous['valor_canonico'];
            $improved = $config['direction'] === 'lower' ? $difference < 0 : $difference > 0;
            if ($improved) $facts[] = ['type'=>$type,'kind'=>'updated','row'=>$candidate,'previous'=>$previous,'difference'=>$difference];
            continue;
        }
        $better = static function (array $a, array $b) use ($config): bool {
            $av=(float)$a['valor_canonico']; $bv=(float)$b['valor_canonico'];
            return $config['direction'] === 'lower' ? $av < $bv : $av > $bv;
        };
        $candidate = null;
        $candidateIndex = -1;
        foreach ($items as $index => $item) {
            try { $itemDate = new DateTimeImmutable((string) ($item['data_resultado'] ?? '')); } catch (Throwable) { continue; }
            if ($itemDate < $start || $itemDate >= $end) continue;
            if ($candidate === null || $better($item, $candidate)) { $candidate = $item; $candidateIndex = $index; }
        }
        if (!is_array($candidate) || $candidateIndex < 1) continue;
        $previousBest = null;
        for ($index = 0; $index < $candidateIndex; $index++) {
            $item = $items[$index];
            if ($previousBest === null || $better($item, $previousBest)) $previousBest = $item;
        }
        if ($previousBest === null || !$better($candidate, $previousBest)) continue;
        $facts[] = ['type'=>$type,'kind'=>'new_best','row'=>$candidate,'previous'=>$previousBest,'difference'=>(float)$candidate['valor_canonico']-(float)$previousBest['valor_canonico']];
    }
    usort($facts, static fn(array $a,array $b): int => strcmp((string)($b['row']['data_resultado']??''),(string)($a['row']['data_resultado']??'')));
    return array_slice($facts,0,4);
}

function benchmarkMethodLabel(string $method): string
{
    return stridebr_t('benchmarks.method.' . str_replace('-', '_', $method));
}

function benchmarkContextLabel(?string $context): string
{
    $context = trim((string) $context);
    return $context === '' ? '' : stridebr_t('benchmarks.context.' . str_replace('-', '_', $context));
}

function benchmarkOfficialityLabel(string $officiality): string
{
    return match ($officiality) {
        'informado_oficial' => stridebr_t('benchmarks.reported_official'),
        'verificado' => stridebr_t('benchmarks.verified'),
        'nao_oficial' => stridebr_t('benchmarks.not_official'),
        default => '',
    };
}

function benchmarkProtocolLabel(?string $protocol): string
{
    $protocol = trim((string) $protocol);
    if ($protocol === '') return '';
    if (in_array($protocol, ['ramp', '20min', 'informado', 'outro'], true)) return stridebr_t('benchmarks.protocol.' . $protocol);
    return $protocol;
}
