<?php

declare(strict_types=1);

require_once __DIR__ . '/sport_catalog.php';

function combatProgressNormalizeWhitespace(string $value): string
{
    return trim((string) preg_replace('/\s+/u', ' ', trim($value)));
}

function combatProgressNormalizeKey(string $value): string
{
    return stridebr_lower(combatProgressNormalizeWhitespace($value));
}

function combatProgressModality(PDO $pdo, string $userId, string $modalityId): ?array
{
    $modalityId = trim($modalityId);
    if ($modalityId === '') return null;
    $stmt = $pdo->prepare('SELECT idmodalidade,idusuario,nome,slug,categoria,familia_hub,ativo FROM modalidades WHERE idmodalidade=:modalidade AND (idusuario IS NULL OR idusuario=:usuario) LIMIT 1');
    $stmt->execute([':modalidade' => $modalityId, ':usuario' => $userId]);
    $row = $stmt->fetch();
    if (!is_array($row) || !stridebr_db_bool($row['ativo'] ?? false)) return null;
    $family = sportCatalogFamilyKey((string) ($row['familia_hub'] ?? ''), (string) ($row['categoria'] ?? ''), (string) ($row['slug'] ?? ''));
    return $family === 'combat' ? $row : null;
}

function combatProgressRequireModality(PDO $pdo, string $userId, string $modalityId): array
{
    $row = combatProgressModality($pdo, $userId, $modalityId);
    if ($row === null) throw new InvalidArgumentException(stridebr_t('combat.error.invalid_modality'));
    return $row;
}

function combatProgressDate(mixed $value, string $errorKey): string
{
    $raw = trim((string) $value);
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $raw, new DateTimeZone('America/Sao_Paulo'));
    if (!$date || $date->format('Y-m-d') !== $raw || $date > new DateTimeImmutable('today', new DateTimeZone('America/Sao_Paulo'))) {
        throw new InvalidArgumentException(stridebr_t($errorKey));
    }
    return $raw;
}

function combatProgressNullableText(mixed $value, int $max, string $errorKey): ?string
{
    $text = combatProgressNormalizeWhitespace((string) $value);
    if ($text === '') return null;
    if (mb_strlen($text) > $max) throw new InvalidArgumentException(stridebr_t($errorKey));
    return $text;
}

function combatRankNormalizeInput(PDO $pdo, string $userId, array $input, ?array $existing = null): array
{
    $modalityId = trim((string) ($input['idmodalidade'] ?? $existing['idmodalidade'] ?? ''));
    combatProgressRequireModality($pdo, $userId, $modalityId);
    if ($existing !== null && $modalityId !== (string) $existing['idmodalidade']) throw new InvalidArgumentException(stridebr_t('combat.error.identity_locked'));
    $system = combatProgressNullableText($input['sistema'] ?? $existing['sistema'] ?? null, 120, 'combat.error.invalid_system');
    $rank = combatProgressNormalizeWhitespace((string) ($input['graduacao'] ?? $existing['graduacao'] ?? ''));
    if ($rank === '' || mb_strlen($rank) > 120) throw new InvalidArgumentException(stridebr_t('combat.error.invalid_rank'));
    $detail = combatProgressNullableText($input['detalhe'] ?? $existing['detalhe'] ?? null, 120, 'combat.error.invalid_detail');
    $issuer = combatProgressNullableText($input['emissor'] ?? $existing['emissor'] ?? null, 160, 'combat.error.invalid_issuer');
    $notes = trim((string) ($input['observacoes'] ?? $existing['observacoes'] ?? ''));
    if (mb_strlen($notes) > 4000) throw new InvalidArgumentException(stridebr_t('combat.error.invalid_notes'));
    $date = combatProgressDate($input['data_graduacao'] ?? $existing['data_graduacao'] ?? '', 'combat.error.invalid_rank_date');
    return [
        'idmodalidade' => $modalityId,
        'sistema' => $system,
        'sistema_normalizado' => $system === null ? '' : combatProgressNormalizeKey($system),
        'graduacao' => $rank,
        'detalhe' => $detail,
        'data_graduacao' => $date,
        'emissor' => $issuer,
        'observacoes' => $notes === '' ? null : $notes,
    ];
}

function combatRankCreate(PDO $pdo, string $userId, array $input): array
{
    $data = combatRankNormalizeInput($pdo, $userId, $input);
    $id = stridebr_generate_id();
    $stmt = $pdo->prepare('INSERT INTO graduacoes_usuario (idgraduacao,idusuario,idmodalidade,sistema,sistema_normalizado,graduacao,detalhe,data_graduacao,emissor,observacoes) VALUES (:id,:usuario,:modalidade,:sistema,:sistema_normalizado,:graduacao,:detalhe,:data_graduacao,:emissor,:observacoes)');
    $stmt->execute([':id'=>$id, ':usuario'=>$userId, ':modalidade'=>$data['idmodalidade'], ':sistema'=>$data['sistema'], ':sistema_normalizado'=>$data['sistema_normalizado'], ':graduacao'=>$data['graduacao'], ':detalhe'=>$data['detalhe'], ':data_graduacao'=>$data['data_graduacao'], ':emissor'=>$data['emissor'], ':observacoes'=>$data['observacoes']]);
    return combatRankGet($pdo, $userId, $id) ?? throw new RuntimeException('Rank persistence failed.');
}

function combatRankGet(PDO $pdo, string $userId, string $rankId): ?array
{
    $stmt = $pdo->prepare('SELECT g.*,m.nome AS modalidade_nome,m.slug AS modalidade_slug FROM graduacoes_usuario g JOIN modalidades m ON m.idmodalidade=g.idmodalidade WHERE g.idgraduacao=:id AND g.idusuario=:usuario LIMIT 1');
    $stmt->execute([':id'=>$rankId, ':usuario'=>$userId]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function combatRankUpdate(PDO $pdo, string $userId, string $rankId, array $input): array
{
    $existing = combatRankGet($pdo, $userId, $rankId);
    if ($existing === null) throw new InvalidArgumentException(stridebr_t('combat.error.rank_not_found'));
    $data = combatRankNormalizeInput($pdo, $userId, $input, $existing);
    $stmt = $pdo->prepare('UPDATE graduacoes_usuario SET sistema=:sistema,sistema_normalizado=:sistema_normalizado,graduacao=:graduacao,detalhe=:detalhe,data_graduacao=:data_graduacao,emissor=:emissor,observacoes=:observacoes,data_atualizacao=NOW() WHERE idgraduacao=:id AND idusuario=:usuario');
    $stmt->execute([':sistema'=>$data['sistema'], ':sistema_normalizado'=>$data['sistema_normalizado'], ':graduacao'=>$data['graduacao'], ':detalhe'=>$data['detalhe'], ':data_graduacao'=>$data['data_graduacao'], ':emissor'=>$data['emissor'], ':observacoes'=>$data['observacoes'], ':id'=>$rankId, ':usuario'=>$userId]);
    return combatRankGet($pdo, $userId, $rankId) ?? throw new RuntimeException('Rank persistence failed.');
}

function combatRankDelete(PDO $pdo, string $userId, string $rankId): bool
{
    $stmt = $pdo->prepare('DELETE FROM graduacoes_usuario WHERE idgraduacao=:id AND idusuario=:usuario');
    $stmt->execute([':id'=>$rankId, ':usuario'=>$userId]);
    return $stmt->rowCount() === 1;
}

function combatRankList(PDO $pdo, string $userId, string $modalityId): array
{
    combatProgressRequireModality($pdo, $userId, $modalityId);
    $stmt = $pdo->prepare('SELECT g.*,m.nome AS modalidade_nome,m.slug AS modalidade_slug FROM graduacoes_usuario g JOIN modalidades m ON m.idmodalidade=g.idmodalidade WHERE g.idusuario=:usuario AND g.idmodalidade=:modalidade ORDER BY g.data_graduacao DESC,g.data_criacao DESC,g.idgraduacao DESC');
    $stmt->execute([':usuario'=>$userId, ':modalidade'=>$modalityId]);
    return $stmt->fetchAll();
}

function combatRankSummary(PDO $pdo, string $userId, string $modalityId): array
{
    $history = combatRankList($pdo, $userId, $modalityId);
    $currentBySystem = [];
    foreach ($history as $row) {
        $key = (string) ($row['sistema_normalizado'] ?? '');
        if (!isset($currentBySystem[$key])) $currentBySystem[$key] = $row;
    }
    return ['current'=>$history[0] ?? null, 'current_by_system'=>$currentBySystem, 'history'=>$history];
}

function combatTechniqueCategories(): array
{
    return ['striking','clinch','takedown','throw','guard','pass','sweep','submission','defense','control','movement','kata_form','weapon','other'];
}

function combatTechniqueStates(): array
{
    return ['learning','practicing','consolidated','archived'];
}

function combatTechniqueNormalizeInput(PDO $pdo, string $userId, array $input, ?array $existing = null): array
{
    $modalityId = trim((string) ($input['idmodalidade'] ?? $existing['idmodalidade'] ?? ''));
    combatProgressRequireModality($pdo, $userId, $modalityId);
    if ($existing !== null && $modalityId !== (string) $existing['idmodalidade']) throw new InvalidArgumentException(stridebr_t('combat.error.identity_locked'));
    $name = combatProgressNormalizeWhitespace((string) ($input['nome'] ?? $existing['nome'] ?? ''));
    if ($name === '' || mb_strlen($name) > 160) throw new InvalidArgumentException(stridebr_t('combat.error.invalid_technique_name'));
    $category = stridebr_lower(trim((string) ($input['categoria_code'] ?? $existing['categoria_code'] ?? '')));
    if ($category === '') $category = null;
    if ($category !== null && !in_array($category, combatTechniqueCategories(), true)) throw new InvalidArgumentException(stridebr_t('combat.error.invalid_category'));
    $customCategory = combatProgressNullableText($input['categoria_custom'] ?? $existing['categoria_custom'] ?? null, 100, 'combat.error.invalid_category');
    if ($category !== 'other') $customCategory = null;
    $state = stridebr_lower(trim((string) ($input['estado'] ?? $existing['estado'] ?? 'learning')));
    if (!in_array($state, combatTechniqueStates(), true)) throw new InvalidArgumentException(stridebr_t('combat.error.invalid_state'));
    $notes = trim((string) ($input['observacoes'] ?? $existing['observacoes'] ?? ''));
    if (mb_strlen($notes) > 4000) throw new InvalidArgumentException(stridebr_t('combat.error.invalid_notes'));
    return [
        'idmodalidade'=>$modalityId,
        'nome'=>$name,
        'nome_normalizado'=>combatProgressNormalizeKey($name),
        'categoria_code'=>$category,
        'categoria_custom'=>$customCategory,
        'estado'=>$state,
        'observacoes'=>$notes === '' ? null : $notes,
    ];
}

function combatTechniqueGet(PDO $pdo, string $userId, string $techniqueId): ?array
{
    $stmt = $pdo->prepare('SELECT t.*,m.nome AS modalidade_nome,m.slug AS modalidade_slug,COUNT(p.idpratica)::int AS practice_count,MIN(p.data_pratica) AS first_practice,MAX(p.data_pratica) AS last_practice FROM tecnicas_usuario t JOIN modalidades m ON m.idmodalidade=t.idmodalidade LEFT JOIN praticas_tecnica p ON p.idtecnica=t.idtecnica AND p.idusuario=t.idusuario WHERE t.idtecnica=:id AND t.idusuario=:usuario GROUP BY t.idtecnica,m.nome,m.slug LIMIT 1');
    $stmt->execute([':id'=>$techniqueId, ':usuario'=>$userId]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function combatTechniqueCreate(PDO $pdo, string $userId, array $input): array
{
    $data = combatTechniqueNormalizeInput($pdo, $userId, $input);
    $id = stridebr_generate_id();
    try {
        $stmt = $pdo->prepare("INSERT INTO tecnicas_usuario (idtecnica,idusuario,idmodalidade,nome,nome_normalizado,categoria_code,categoria_custom,estado,observacoes,archived_at) VALUES (:id,:usuario,:modalidade,:nome,:nome_normalizado,:categoria_code,:categoria_custom,:estado,:observacoes,CASE WHEN :estado_archive='archived' THEN NOW() ELSE NULL END)");
        $stmt->execute([':id'=>$id, ':usuario'=>$userId, ':modalidade'=>$data['idmodalidade'], ':nome'=>$data['nome'], ':nome_normalizado'=>$data['nome_normalizado'], ':categoria_code'=>$data['categoria_code'], ':categoria_custom'=>$data['categoria_custom'], ':estado'=>$data['estado'], ':observacoes'=>$data['observacoes'], ':estado_archive'=>$data['estado']]);
    } catch (PDOException $error) {
        if ($error->getCode() === '23505') throw new InvalidArgumentException(stridebr_t('combat.error.duplicate_technique'));
        throw $error;
    }
    return combatTechniqueGet($pdo, $userId, $id) ?? throw new RuntimeException('Technique persistence failed.');
}

function combatTechniqueUpdate(PDO $pdo, string $userId, string $techniqueId, array $input): array
{
    $existing = combatTechniqueGet($pdo, $userId, $techniqueId);
    if ($existing === null) throw new InvalidArgumentException(stridebr_t('combat.error.technique_not_found'));
    $data = combatTechniqueNormalizeInput($pdo, $userId, $input, $existing);
    try {
        $stmt = $pdo->prepare("UPDATE tecnicas_usuario SET nome=:nome,nome_normalizado=:nome_normalizado,categoria_code=:categoria_code,categoria_custom=:categoria_custom,estado=:estado,observacoes=:observacoes,archived_at=CASE WHEN :estado_archive='archived' THEN COALESCE(archived_at,NOW()) ELSE NULL END,data_atualizacao=NOW() WHERE idtecnica=:id AND idusuario=:usuario");
        $stmt->execute([':nome'=>$data['nome'], ':nome_normalizado'=>$data['nome_normalizado'], ':categoria_code'=>$data['categoria_code'], ':categoria_custom'=>$data['categoria_custom'], ':estado'=>$data['estado'], ':observacoes'=>$data['observacoes'], ':estado_archive'=>$data['estado'], ':id'=>$techniqueId, ':usuario'=>$userId]);
    } catch (PDOException $error) {
        if ($error->getCode() === '23505') throw new InvalidArgumentException(stridebr_t('combat.error.duplicate_technique'));
        throw $error;
    }
    return combatTechniqueGet($pdo, $userId, $techniqueId) ?? throw new RuntimeException('Technique persistence failed.');
}

function combatTechniqueSetArchived(PDO $pdo, string $userId, string $techniqueId, bool $archived): array
{
    $existing = combatTechniqueGet($pdo, $userId, $techniqueId);
    if ($existing === null) throw new InvalidArgumentException(stridebr_t('combat.error.technique_not_found'));
    $state = $archived ? 'archived' : 'learning';
    $stmt = $pdo->prepare('UPDATE tecnicas_usuario SET estado=:estado,archived_at=' . ($archived ? 'COALESCE(archived_at,NOW())' : 'NULL') . ',data_atualizacao=NOW() WHERE idtecnica=:id AND idusuario=:usuario');
    $stmt->execute([':estado'=>$state, ':id'=>$techniqueId, ':usuario'=>$userId]);
    return combatTechniqueGet($pdo, $userId, $techniqueId) ?? throw new RuntimeException('Technique persistence failed.');
}

function combatTechniqueList(PDO $pdo, string $userId, string $modalityId, bool $includeArchived = true): array
{
    combatProgressRequireModality($pdo, $userId, $modalityId);
    $whereArchived = $includeArchived ? '' : " AND t.estado <> 'archived'";
    $stmt = $pdo->prepare("SELECT t.*,COUNT(p.idpratica)::int AS practice_count,MIN(p.data_pratica) AS first_practice,MAX(p.data_pratica) AS last_practice FROM tecnicas_usuario t LEFT JOIN praticas_tecnica p ON p.idtecnica=t.idtecnica AND p.idusuario=t.idusuario WHERE t.idusuario=:usuario AND t.idmodalidade=:modalidade{$whereArchived} GROUP BY t.idtecnica ORDER BY CASE t.estado WHEN 'learning' THEN 1 WHEN 'practicing' THEN 2 WHEN 'consolidated' THEN 3 ELSE 4 END,t.nome_normalizado");
    $stmt->execute([':usuario'=>$userId, ':modalidade'=>$modalityId]);
    return $stmt->fetchAll();
}

function combatPracticeValidateActivity(PDO $pdo, string $userId, string $activityId, string $modalityId): array
{
    $stmt = $pdo->prepare('SELECT idregistro,idmodalidade,titulo,data_inicio FROM registros_atividade WHERE idregistro=:atividade AND idusuario=:usuario AND excluido_em IS NULL LIMIT 1');
    $stmt->execute([':atividade'=>$activityId, ':usuario'=>$userId]);
    $row = $stmt->fetch();
    if (!is_array($row)) throw new InvalidArgumentException(stridebr_t('combat.error.invalid_activity'));
    if ((string) ($row['idmodalidade'] ?? '') !== $modalityId) throw new InvalidArgumentException(stridebr_t('combat.error.activity_modality_mismatch'));
    return $row;
}

function combatPracticeCreate(PDO $pdo, string $userId, array $input): array
{
    $techniqueId = trim((string) ($input['idtecnica'] ?? ''));
    $technique = combatTechniqueGet($pdo, $userId, $techniqueId);
    if ($technique === null) throw new InvalidArgumentException(stridebr_t('combat.error.technique_not_found'));
    $date = combatProgressDate($input['data_pratica'] ?? '', 'combat.error.invalid_practice_date');
    $activityId = trim((string) ($input['idregistro'] ?? ''));
    $origin = 'manual';
    if ($activityId !== '') {
        combatPracticeValidateActivity($pdo, $userId, $activityId, (string) $technique['idmodalidade']);
        $origin = 'activity';
    } else {
        $activityId = null;
    }
    $notes = trim((string) ($input['observacoes'] ?? ''));
    if (mb_strlen($notes) > 2000) throw new InvalidArgumentException(stridebr_t('combat.error.invalid_notes'));
    $id = stridebr_generate_id();
    $stmt = $pdo->prepare('INSERT INTO praticas_tecnica (idpratica,idusuario,idtecnica,data_pratica,idregistro,origem,observacoes) VALUES (:id,:usuario,:tecnica,:data_pratica,:atividade,:origem,:observacoes)');
    $stmt->execute([':id'=>$id, ':usuario'=>$userId, ':tecnica'=>$techniqueId, ':data_pratica'=>$date, ':atividade'=>$activityId, ':origem'=>$origin, ':observacoes'=>$notes === '' ? null : $notes]);
    return combatPracticeGet($pdo, $userId, $id) ?? throw new RuntimeException('Practice persistence failed.');
}

function combatPracticeGet(PDO $pdo, string $userId, string $practiceId): ?array
{
    $stmt = $pdo->prepare('SELECT p.*,t.nome AS tecnica_nome,t.idmodalidade,ra.titulo AS atividade_titulo FROM praticas_tecnica p JOIN tecnicas_usuario t ON t.idtecnica=p.idtecnica LEFT JOIN registros_atividade ra ON ra.idregistro=p.idregistro WHERE p.idpratica=:id AND p.idusuario=:usuario AND t.idusuario=:owner LIMIT 1');
    $stmt->execute([':id'=>$practiceId, ':usuario'=>$userId, ':owner'=>$userId]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function combatPracticeDelete(PDO $pdo, string $userId, string $practiceId): bool
{
    $stmt = $pdo->prepare('DELETE FROM praticas_tecnica WHERE idpratica=:id AND idusuario=:usuario');
    $stmt->execute([':id'=>$practiceId, ':usuario'=>$userId]);
    return $stmt->rowCount() === 1;
}

function combatPracticeList(PDO $pdo, string $userId, string $techniqueId, int $limit = 50): array
{
    $technique = combatTechniqueGet($pdo, $userId, $techniqueId);
    if ($technique === null) throw new InvalidArgumentException(stridebr_t('combat.error.technique_not_found'));
    $limit = max(1, min(200, $limit));
    $stmt = $pdo->prepare("SELECT p.*,ra.titulo AS atividade_titulo FROM praticas_tecnica p LEFT JOIN registros_atividade ra ON ra.idregistro=p.idregistro AND ra.idusuario=p.idusuario WHERE p.idusuario=:usuario AND p.idtecnica=:tecnica ORDER BY p.data_pratica DESC,p.data_criacao DESC,p.idpratica DESC LIMIT {$limit}");
    $stmt->execute([':usuario'=>$userId, ':tecnica'=>$techniqueId]);
    return $stmt->fetchAll();
}

function combatActivityOptions(PDO $pdo, string $userId, string $modalityId, int $limit = 50): array
{
    combatProgressRequireModality($pdo, $userId, $modalityId);
    $limit = max(1, min(100, $limit));
    $stmt = $pdo->prepare("SELECT idregistro,titulo,data_inicio FROM registros_atividade WHERE idusuario=:usuario AND idmodalidade=:modalidade AND excluido_em IS NULL AND status='concluido' ORDER BY data_inicio DESC,idregistro DESC LIMIT {$limit}");
    $stmt->execute([':usuario'=>$userId, ':modalidade'=>$modalityId]);
    return $stmt->fetchAll();
}

function combatProgressDashboard(PDO $pdo, string $userId, string $modalityId): array
{
    $modality = combatProgressRequireModality($pdo, $userId, $modalityId);
    $ranks = combatRankSummary($pdo, $userId, $modalityId);
    $techniques = combatTechniqueList($pdo, $userId, $modalityId, true);
    $states = array_fill_keys(combatTechniqueStates(), 0);
    foreach ($techniques as $row) $states[(string) $row['estado']] = ($states[(string) $row['estado']] ?? 0) + 1;
    return [
        'modality'=>$modality,
        'ranks'=>$ranks,
        'techniques'=>$techniques,
        'state_counts'=>$states,
        'active_count'=>count(array_filter($techniques, static fn(array $row): bool => (string) ($row['estado'] ?? '') !== 'archived')),
        'activity_options'=>combatActivityOptions($pdo, $userId, $modalityId),
    ];
}
