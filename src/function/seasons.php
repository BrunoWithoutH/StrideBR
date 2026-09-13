<?php

declare(strict_types=1);

function seasonTableExists(PDO $pdo): bool
{
    return stridebr_db_table_exists($pdo, 'temporadas_usuario');
}

function seasonCanonicalModalityRow(PDO $pdo, string $userId, string $modalityId): ?array
{
    $modalityId = trim($modalityId);
    if ($modalityId === '') return null;
    $stmt = $pdo->prepare('SELECT idmodalidade,idusuario,nome,slug,familia_hub FROM modalidades WHERE idmodalidade=:modalidade AND (idusuario IS NULL OR idusuario=:usuario) LIMIT 1');
    $stmt->execute([':modalidade'=>$modalityId, ':usuario'=>$userId]);
    $row = $stmt->fetch();
    if (!is_array($row)) return null;
    if (stridebr_lower((string) ($row['familia_hub'] ?? '')) !== 'athletics') return $row;
    $athletics = $pdo->prepare("SELECT idmodalidade,idusuario,nome,slug,familia_hub FROM modalidades WHERE idusuario IS NULL AND slug='atletismo' LIMIT 1");
    $athletics->execute();
    $canonical = $athletics->fetch();
    return is_array($canonical) ? $canonical : $row;
}

function seasonCanonicalModalityId(PDO $pdo, string $userId, string $modalityId): ?string
{
    $row = seasonCanonicalModalityRow($pdo, $userId, $modalityId);
    $id = trim((string) ($row['idmodalidade'] ?? ''));
    return $id !== '' ? $id : null;
}

function seasonNormalizeDate(mixed $value, bool $allowEmpty = false): ?string
{
    $raw = trim((string) $value);
    if ($raw === '' && $allowEmpty) return null;
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
    if (!$date || $date->format('Y-m-d') !== $raw) throw new InvalidArgumentException(stridebr_t('seasons.error.invalid_date'));
    return $raw;
}

function seasonNormalizeInput(PDO $pdo, string $userId, array $input, ?array $existing = null): array
{
    $name = trim((string) ($input['nome'] ?? $existing['nome'] ?? ''));
    if ($name === '' || stridebr_length($name) > 120) throw new InvalidArgumentException(stridebr_t('seasons.error.invalid_name'));

    $modalityInput = trim((string) ($input['idmodalidade'] ?? $existing['idmodalidade'] ?? ''));
    $canonical = seasonCanonicalModalityRow($pdo, $userId, $modalityInput);
    if ($canonical === null) throw new InvalidArgumentException(stridebr_t('seasons.error.invalid_sport'));
    $modalityId = (string) $canonical['idmodalidade'];

    $start = seasonNormalizeDate($input['data_inicio'] ?? $existing['data_inicio'] ?? null);
    $end = seasonNormalizeDate($input['data_fim'] ?? $existing['data_fim'] ?? null, true);
    $status = stridebr_lower(trim((string) ($input['status'] ?? $existing['status'] ?? ($end === null ? 'ativa' : 'encerrada'))));
    if (!in_array($status, ['ativa', 'encerrada'], true)) throw new InvalidArgumentException(stridebr_t('seasons.error.invalid_status'));
    if ($status === 'ativa') $end = null;
    if ($status === 'encerrada' && $end === null) throw new InvalidArgumentException(stridebr_t('seasons.error.end_required'));
    if ($end !== null && $end < $start) throw new InvalidArgumentException(stridebr_t('seasons.error.invalid_period'));

    $notes = trim((string) ($input['observacoes'] ?? $existing['observacoes'] ?? ''));
    if (stridebr_length($notes) > 2000) throw new InvalidArgumentException(stridebr_t('seasons.error.notes_long'));

    return [
        'idmodalidade'=>$modalityId,
        'nome'=>$name,
        'data_inicio'=>$start,
        'data_fim'=>$end,
        'status'=>$status,
        'observacoes'=>$notes !== '' ? $notes : null,
    ];
}

function seasonAssertNoOverlap(PDO $pdo, string $userId, string $modalityId, string $start, ?string $end, ?string $excludeId = null): void
{
    $sql = "SELECT idtemporada FROM temporadas_usuario WHERE idusuario=:usuario AND idmodalidade=:modalidade AND daterange(data_inicio,COALESCE(data_fim,'infinity'::date),'[]') && daterange(CAST(:inicio AS date),COALESCE(CAST(:fim AS date),'infinity'::date),'[]')";
    $params = [':usuario'=>$userId, ':modalidade'=>$modalityId, ':inicio'=>$start, ':fim'=>$end];
    if ($excludeId !== null && $excludeId !== '') {
        $sql .= ' AND idtemporada<>:exclude';
        $params[':exclude'] = $excludeId;
    }
    $sql .= ' LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    if ($stmt->fetchColumn()) throw new InvalidArgumentException(stridebr_t('seasons.error.overlap'));
}

function seasonGet(PDO $pdo, string $userId, string $seasonId): ?array
{
    if (!seasonTableExists($pdo) || trim($seasonId) === '') return null;
    $stmt = $pdo->prepare('SELECT t.*,m.nome AS modalidade_nome,m.slug AS modalidade_slug,m.familia_hub FROM temporadas_usuario t JOIN modalidades m ON m.idmodalidade=t.idmodalidade WHERE t.idtemporada=:temporada AND t.idusuario=:usuario LIMIT 1');
    $stmt->execute([':temporada'=>$seasonId, ':usuario'=>$userId]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function seasonList(PDO $pdo, string $userId, ?string $modalityId = null): array
{
    if (!seasonTableExists($pdo)) return [];
    $sql = 'SELECT t.*,m.nome AS modalidade_nome,m.slug AS modalidade_slug,m.familia_hub FROM temporadas_usuario t JOIN modalidades m ON m.idmodalidade=t.idmodalidade WHERE t.idusuario=:usuario';
    $params = [':usuario'=>$userId];
    if ($modalityId !== null && trim($modalityId) !== '') {
        $canonicalId = seasonCanonicalModalityId($pdo, $userId, $modalityId);
        if ($canonicalId === null) return [];
        $sql .= ' AND t.idmodalidade=:modalidade';
        $params[':modalidade'] = $canonicalId;
    }
    $sql .= " ORDER BY CASE WHEN t.status='ativa' THEN 0 ELSE 1 END,t.data_inicio DESC,t.data_criacao DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function seasonCurrent(PDO $pdo, string $userId, string $modalityId, ?string $onDate = null): ?array
{
    if (!seasonTableExists($pdo)) return null;
    $canonicalId = seasonCanonicalModalityId($pdo, $userId, $modalityId);
    if ($canonicalId === null) return null;
    $date = $onDate !== null ? seasonNormalizeDate($onDate) : (new DateTimeImmutable('today', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
    $stmt = $pdo->prepare("SELECT t.*,m.nome AS modalidade_nome,m.slug AS modalidade_slug,m.familia_hub FROM temporadas_usuario t JOIN modalidades m ON m.idmodalidade=t.idmodalidade WHERE t.idusuario=:usuario AND t.idmodalidade=:modalidade AND CAST(:data AS date) BETWEEN t.data_inicio AND COALESCE(t.data_fim,'infinity'::date) ORDER BY t.data_inicio DESC LIMIT 1");
    $stmt->execute([':usuario'=>$userId, ':modalidade'=>$canonicalId, ':data'=>$date]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function seasonResolveForDate(PDO $pdo, string $userId, string $modalityId, string $date): ?array
{
    return seasonCurrent($pdo, $userId, $modalityId, $date);
}

function seasonCreate(PDO $pdo, string $userId, array $input): array
{
    if (!seasonTableExists($pdo)) throw new RuntimeException(stridebr_t('seasons.error.unavailable'));
    $data = seasonNormalizeInput($pdo, $userId, $input);
    seasonAssertNoOverlap($pdo, $userId, $data['idmodalidade'], $data['data_inicio'], $data['data_fim']);
    $id = stridebr_generate_id();
    $stmt = $pdo->prepare('INSERT INTO temporadas_usuario (idtemporada,idusuario,idmodalidade,nome,data_inicio,data_fim,status,observacoes) VALUES (:id,:usuario,:modalidade,:nome,:inicio,:fim,:status,:observacoes)');
    $stmt->execute([':id'=>$id, ':usuario'=>$userId, ':modalidade'=>$data['idmodalidade'], ':nome'=>$data['nome'], ':inicio'=>$data['data_inicio'], ':fim'=>$data['data_fim'], ':status'=>$data['status'], ':observacoes'=>$data['observacoes']]);
    return seasonGet($pdo, $userId, $id) ?? throw new RuntimeException(stridebr_t('seasons.error.save_failed'));
}

function seasonUpdate(PDO $pdo, string $userId, string $seasonId, array $input): array
{
    $existing = seasonGet($pdo, $userId, $seasonId);
    if ($existing === null) throw new InvalidArgumentException(stridebr_t('seasons.error.not_found'));
    $data = seasonNormalizeInput($pdo, $userId, $input, $existing);
    seasonAssertNoOverlap($pdo, $userId, $data['idmodalidade'], $data['data_inicio'], $data['data_fim'], $seasonId);
    $stmt = $pdo->prepare('UPDATE temporadas_usuario SET idmodalidade=:modalidade,nome=:nome,data_inicio=:inicio,data_fim=:fim,status=:status,observacoes=:observacoes,data_atualizacao=NOW() WHERE idtemporada=:id AND idusuario=:usuario');
    $stmt->execute([':modalidade'=>$data['idmodalidade'], ':nome'=>$data['nome'], ':inicio'=>$data['data_inicio'], ':fim'=>$data['data_fim'], ':status'=>$data['status'], ':observacoes'=>$data['observacoes'], ':id'=>$seasonId, ':usuario'=>$userId]);
    return seasonGet($pdo, $userId, $seasonId) ?? throw new RuntimeException(stridebr_t('seasons.error.save_failed'));
}

function seasonClose(PDO $pdo, string $userId, string $seasonId, ?string $endDate = null): array
{
    $season = seasonGet($pdo, $userId, $seasonId);
    if ($season === null) throw new InvalidArgumentException(stridebr_t('seasons.error.not_found'));
    $end = $endDate !== null && trim($endDate) !== '' ? seasonNormalizeDate($endDate) : (new DateTimeImmutable('today', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
    return seasonUpdate($pdo, $userId, $seasonId, ['status'=>'encerrada','data_fim'=>$end]);
}

function seasonReopen(PDO $pdo, string $userId, string $seasonId): array
{
    $season = seasonGet($pdo, $userId, $seasonId);
    if ($season === null) throw new InvalidArgumentException(stridebr_t('seasons.error.not_found'));
    return seasonUpdate($pdo, $userId, $seasonId, ['status'=>'ativa','data_fim'=>'']);
}
