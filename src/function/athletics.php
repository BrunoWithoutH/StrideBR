<?php

declare(strict_types=1);

function athleticsCatalog(): array
{
    return [
        '60m' => ['slug'=>'atletismo-60m','category'=>'running','measurement'=>'time','direction'=>'lower','wind'=>false,'fat_required'=>true],
        '100m' => ['slug'=>'atletismo-100m','category'=>'running','measurement'=>'time','direction'=>'lower','wind'=>true,'fat_required'=>true],
        '200m' => ['slug'=>'atletismo-200m','category'=>'running','measurement'=>'time','direction'=>'lower','wind'=>true,'fat_required'=>true],
        '400m' => ['slug'=>'atletismo-400m','category'=>'running','measurement'=>'time','direction'=>'lower','wind'=>false,'fat_required'=>true],
        '800m' => ['slug'=>'atletismo-800m','category'=>'running','measurement'=>'time','direction'=>'lower','wind'=>false,'fat_required'=>false],
        '1500m' => ['slug'=>'atletismo-1500m','category'=>'running','measurement'=>'time','direction'=>'lower','wind'=>false,'fat_required'=>false],
        '3000m' => ['slug'=>'atletismo-3000m','category'=>'running','measurement'=>'time','direction'=>'lower','wind'=>false,'fat_required'=>false],
        '5000m' => ['slug'=>'atletismo-5000m','category'=>'running','measurement'=>'time','direction'=>'lower','wind'=>false,'fat_required'=>false],
        '10000m' => ['slug'=>'atletismo-10000m','category'=>'running','measurement'=>'time','direction'=>'lower','wind'=>false,'fat_required'=>false],
        '100m_hurdles' => ['slug'=>'100m-com-barreiras','category'=>'hurdles','measurement'=>'time','direction'=>'lower','wind'=>true,'fat_required'=>true],
        '110m_hurdles' => ['slug'=>'110m-com-barreiras','category'=>'hurdles','measurement'=>'time','direction'=>'lower','wind'=>true,'fat_required'=>true],
        '400m_hurdles' => ['slug'=>'400m-com-barreiras','category'=>'hurdles','measurement'=>'time','direction'=>'lower','wind'=>false,'fat_required'=>true],
        '3000m_steeplechase' => ['slug'=>'3000m-com-obstaculos','category'=>'hurdles','measurement'=>'time','direction'=>'lower','wind'=>false,'fat_required'=>false],
        'long_jump' => ['slug'=>'salto-em-distancia','category'=>'jumps','measurement'=>'distance','direction'=>'higher','wind'=>true,'fat_required'=>false],
        'triple_jump' => ['slug'=>'salto-triplo','category'=>'jumps','measurement'=>'distance','direction'=>'higher','wind'=>true,'fat_required'=>false],
        'high_jump' => ['slug'=>'salto-em-altura','category'=>'jumps','measurement'=>'height','direction'=>'higher','wind'=>false,'fat_required'=>false],
        'pole_vault' => ['slug'=>'salto-com-vara','category'=>'jumps','measurement'=>'height','direction'=>'higher','wind'=>false,'fat_required'=>false],
        'shot_put' => ['slug'=>'arremesso-de-peso','category'=>'throws','measurement'=>'distance','direction'=>'higher','wind'=>false,'fat_required'=>false],
        'discus_throw' => ['slug'=>'lancamento-de-disco','category'=>'throws','measurement'=>'distance','direction'=>'higher','wind'=>false,'fat_required'=>false],
        'javelin_throw' => ['slug'=>'lancamento-de-dardo','category'=>'throws','measurement'=>'distance','direction'=>'higher','wind'=>false,'fat_required'=>false],
        'hammer_throw' => ['slug'=>'lancamento-de-martelo','category'=>'throws','measurement'=>'distance','direction'=>'higher','wind'=>false,'fat_required'=>false],
    ];
}

function athleticsEventConfig(string $code): ?array
{
    $code = stridebr_lower(trim($code));
    $catalog = athleticsCatalog();
    return isset($catalog[$code]) ? ['code'=>$code] + $catalog[$code] : null;
}

function athleticsEventFromSlug(string $slug): ?array
{
    $slug = stridebr_lower(trim($slug));
    foreach (athleticsCatalog() as $code => $config) {
        if ((string) $config['slug'] === $slug) return ['code'=>$code] + $config;
    }
    return null;
}

function athleticsEventCodeFromSlug(string $slug): ?string
{
    return athleticsEventFromSlug($slug)['code'] ?? null;
}

function athleticsEventLabel(string $code): string
{
    $config = athleticsEventConfig($code);
    if ($config === null) return $code;
    return stridebr_t('athletics.event.' . $code);
}

function athleticsCategoryLabel(string $category): string
{
    return stridebr_t('athletics.category.' . stridebr_lower(trim($category)));
}

function athleticsWindLimitMps(): float
{
    return 2.0;
}

function athleticsNormalizeEnvironment(mixed $value): string
{
    $value = stridebr_lower(trim((string) $value));
    return in_array($value, ['indoor','outdoor','unknown'], true) ? $value : 'unknown';
}

function athleticsNormalizeTimingMethod(mixed $value): string
{
    $value = stridebr_lower(trim((string) $value));
    return in_array($value, ['fat','hand','unknown'], true) ? $value : 'unknown';
}

function athleticsFormatTime(float $seconds): string
{
    $seconds = max(0.0, $seconds);
    if ($seconds < 60) return number_format($seconds, 2, '.', '');
    $minutes = intdiv((int) floor($seconds), 60);
    $remaining = $seconds - ($minutes * 60);
    return $minutes . ':' . str_pad(number_format($remaining, 2, '.', ''), 5, '0', STR_PAD_LEFT);
}

function athleticsFormatValue(string $eventCode, float $value): string
{
    $config = athleticsEventConfig($eventCode);
    if ($config === null) return stridebr_format_number($value, 2);
    if ($config['measurement'] === 'time') return athleticsFormatTime($value) . ' s';
    return stridebr_format_number($value, 2) . ' m';
}

function athleticsEligibilityReasonLabel(string $reason): string
{
    return stridebr_t('athletics.eligibility.reason.' . $reason);
}

function athleticsRecordEligibility(array $evidence): array
{
    $eventCode = trim((string) ($evidence['athletics_event_code'] ?? $evidence['event_code'] ?? ''));
    $event = athleticsEventConfig($eventCode);
    if ($event === null) return ['status'=>'unknown','reasons'=>['conditions_unknown']];

    if (stridebr_db_bool($evidence['attempt_invalid'] ?? false)) return ['status'=>'ineligible','reasons'=>['invalid_attempt']];

    $reasons = [];
    $context = stridebr_lower(trim((string) ($evidence['contexto'] ?? $evidence['context'] ?? '')));
    if ($context !== '' && $context !== 'competicao') return ['status'=>'ineligible','reasons'=>['unofficial_context']];
    if ($context === '') $reasons[] = 'conditions_unknown';

    $officiality = stridebr_lower(trim((string) ($evidence['effective_officiality'] ?? $evidence['oficialidade'] ?? '')));
    if ($officiality === 'nao_oficial') return ['status'=>'ineligible','reasons'=>['unofficial_context']];
    if (!in_array($officiality, ['informado_oficial','verificado'], true)) $reasons[] = 'officiality_unknown';

    $environment = athleticsNormalizeEnvironment($evidence['athletics_environment'] ?? $evidence['environment'] ?? 'unknown');
    if (!empty($event['wind']) && $environment !== 'indoor') {
        if (!is_numeric($evidence['wind_mps'] ?? null)) {
            $reasons[] = 'wind_unknown';
        } elseif ((float) $evidence['wind_mps'] > athleticsWindLimitMps()) {
            return ['status'=>'ineligible','reasons'=>['wind_over_limit']];
        }
    }

    if (!empty($event['fat_required'])) {
        $timing = athleticsNormalizeTimingMethod($evidence['timing_method'] ?? 'unknown');
        if ($timing === 'hand') return ['status'=>'ineligible','reasons'=>['hand_timed_not_allowed']];
        if ($timing === 'unknown') $reasons[] = 'timing_unknown';
    }

    $reasons = array_values(array_unique($reasons));
    if ($reasons !== []) return ['status'=>'unknown','reasons'=>$reasons];
    return ['status'=>'eligible','reasons'=>[]];
}

function athleticsModalityRows(PDO $pdo, string $userId): array
{
    $slugs = array_values(array_unique(array_map(static fn(array $config): string => (string) $config['slug'], athleticsCatalog())));
    if ($slugs === []) return [];
    $holders = [];
    $params = [':usuario'=>$userId];
    foreach ($slugs as $i => $slug) {
        $key = ':slug' . $i;
        $holders[] = $key;
        $params[$key] = $slug;
    }
    $stmt = $pdo->prepare('SELECT idmodalidade,idusuario,nome,slug,familia_hub FROM modalidades WHERE slug IN (' . implode(',', $holders) . ') AND (idusuario IS NULL OR idusuario=:usuario) ORDER BY ordem_catalogo,nome');
    $stmt->execute($params);
    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $event = athleticsEventFromSlug((string) ($row['slug'] ?? ''));
        if ($event === null) continue;
        $rows[$event['code']] = $row + ['athletics_event_code'=>$event['code']];
    }
    return $rows;
}

function athleticsActivityEvidence(PDO $pdo, string $userId, ?string $eventCode = null): array
{
    $catalog = athleticsCatalog();
    if ($eventCode !== null && athleticsEventConfig($eventCode) === null) return [];
    $allowedSlugs = [];
    foreach ($catalog as $code => $config) {
        if ($eventCode !== null && $code !== $eventCode) continue;
        $allowedSlugs[] = (string) $config['slug'];
    }
    if ($allowedSlugs === []) return [];

    $holders = [];
    $params = [':usuario'=>$userId];
    foreach ($allowedSlugs as $i => $slug) {
        $key = ':slug' . $i;
        $holders[] = $key;
        $params[$key] = $slug;
    }
    $in = implode(',', $holders);

    $stmt = $pdo->prepare("SELECT ra.idregistro,ra.idmodalidade,ra.data_inicio,ra.origem,ra.origem_provedor,ra.idcompeticao,m.slug AS modalidade_slug,m.nome AS modalidade_nome,
        COALESCE(cu.oficialidade,'nao_informada') AS competition_officiality,cu.nome AS competition_name,
        COALESCE(NULLIF(GREATEST(0,EXTRACT(EPOCH FROM (COALESCE(ra.data_fim,ra.data_inicio)-ra.data_inicio))),0),metric.duration_s) AS duration_s,
        perf.wind_mps
        FROM registros_atividade ra
        JOIN modalidades m ON m.idmodalidade=ra.idmodalidade
        LEFT JOIN competicoes_usuario cu ON cu.idcompeticao=ra.idcompeticao AND cu.idusuario=ra.idusuario
        LEFT JOIN LATERAL (
            SELECT SUM(va.valor_normalizado) FILTER (WHERE lower(c.slug)='duracao') AS duration_s
            FROM valores_atividade va JOIN campos_modelo c ON c.idcampo=va.idcampo
            WHERE va.idregistro=ra.idregistro
        ) metric ON TRUE
        LEFT JOIN LATERAL (
            SELECT AVG(COALESCE(va.valor_decimal,va.valor_inteiro::numeric)) FILTER (WHERE lower(c.slug)='vento') AS wind_mps
            FROM valores_atividade va JOIN campos_modelo c ON c.idcampo=va.idcampo
            WHERE va.idregistro=ra.idregistro AND va.idunidade_atividade IS NULL
        ) perf ON TRUE
        WHERE ra.idusuario=:usuario AND ra.excluido_em IS NULL AND ra.status='concluido' AND m.slug IN ({$in})
        ORDER BY ra.data_inicio DESC");
    $stmt->execute($params);

    $result = [];
    $fieldActivityIds = [];
    foreach ($stmt->fetchAll() as $row) {
        $event = athleticsEventFromSlug((string) $row['modalidade_slug']);
        if ($event === null) continue;
        if ($event['measurement'] === 'time') {
            if (!is_numeric($row['duration_s'] ?? null) || (float) $row['duration_s'] <= 0) continue;
            $result[] = [
                'evidence_kind'=>'activity', 'idbenchmark'=>null, 'idregistro'=>(string)$row['idregistro'], 'idunidade_atividade'=>null,
                'idmodalidade'=>(string)$row['idmodalidade'], 'modalidade_slug'=>(string)$row['modalidade_slug'], 'modalidade_nome'=>(string)$row['modalidade_nome'],
                'tipo'=>'athletics', 'athletics_event_code'=>$event['code'], 'athletics_environment'=>'unknown', 'valor_canonico'=>(float)$row['duration_s'],
                'data_resultado'=>substr((string)$row['data_inicio'],0,10), 'origem'=>((string)$row['origem']==='importacao'?'importacao':((string)$row['origem']==='api'?'api':'atividade')),
                'metodo'=>'medido', 'contexto'=>trim((string)($row['idcompeticao']??''))!==''?'competicao':'treino', 'oficialidade'=>'nao_aplicavel',
                'effective_officiality'=>(string)($row['competition_officiality']??'nao_informada'), 'idcompeticao'=>$row['idcompeticao']??null, 'competicao_nome'=>$row['competition_name']??null,
                'wind_mps'=>is_numeric($row['wind_mps']??null)?(float)$row['wind_mps']:null, 'timing_method'=>'unknown', 'attempt_invalid'=>false,
                'data_criacao'=>(string)$row['data_inicio'], 'excluido_progresso'=>false,
            ];
        } else {
            $fieldActivityIds[(string)$row['idregistro']] = $row;
        }
    }

    if ($fieldActivityIds !== []) {
        $idHolders = [];
        $fieldParams = [];
        foreach (array_keys($fieldActivityIds) as $i => $id) {
            $key = ':id' . $i;
            $idHolders[] = $key;
            $fieldParams[$key] = $id;
        }
        $units = $pdo->prepare("SELECT ua.idunidade_atividade,ua.idregistro,ua.ordem,
            MAX(COALESCE(va.valor_normalizado,va.valor_decimal)) FILTER (WHERE lower(c.slug)='marca') AS mark_m,
            MAX(COALESCE(va.valor_decimal,va.valor_inteiro::numeric)) FILTER (WHERE lower(c.slug)='vento') AS wind_mps,
            COALESCE(BOOL_OR(COALESCE(va.valor_booleano,FALSE)) FILTER (WHERE lower(c.slug)='tentativa-nula'),FALSE) AS attempt_invalid
            FROM unidades_atividade ua
            LEFT JOIN valores_atividade va ON va.idunidade_atividade=ua.idunidade_atividade
            LEFT JOIN campos_modelo c ON c.idcampo=va.idcampo
            WHERE ua.idregistro IN (" . implode(',', $idHolders) . ") AND ua.tipo_unidade='tentativa'
            GROUP BY ua.idunidade_atividade,ua.idregistro,ua.ordem
            ORDER BY ua.idregistro,ua.ordem");
        $units->execute($fieldParams);
        foreach ($units->fetchAll() as $unit) {
            $parent = $fieldActivityIds[(string)$unit['idregistro']] ?? null;
            if (!is_array($parent)) continue;
            $event = athleticsEventFromSlug((string)$parent['modalidade_slug']);
            if ($event === null || !is_numeric($unit['mark_m']??null) || (float)$unit['mark_m']<=0 || stridebr_db_bool($unit['attempt_invalid']??false)) continue;
            $result[] = [
                'evidence_kind'=>'activity', 'idbenchmark'=>null, 'idregistro'=>(string)$parent['idregistro'], 'idunidade_atividade'=>(string)$unit['idunidade_atividade'],
                'idmodalidade'=>(string)$parent['idmodalidade'], 'modalidade_slug'=>(string)$parent['modalidade_slug'], 'modalidade_nome'=>(string)$parent['modalidade_nome'],
                'tipo'=>'athletics', 'athletics_event_code'=>$event['code'], 'athletics_environment'=>'unknown', 'valor_canonico'=>(float)$unit['mark_m'],
                'data_resultado'=>substr((string)$parent['data_inicio'],0,10), 'origem'=>((string)$parent['origem']==='importacao'?'importacao':((string)$parent['origem']==='api'?'api':'atividade')),
                'metodo'=>'medido', 'contexto'=>trim((string)($parent['idcompeticao']??''))!==''?'competicao':'treino', 'oficialidade'=>'nao_aplicavel',
                'effective_officiality'=>(string)($parent['competition_officiality']??'nao_informada'), 'idcompeticao'=>$parent['idcompeticao']??null, 'competicao_nome'=>$parent['competition_name']??null,
                'wind_mps'=>is_numeric($unit['wind_mps']??null)?(float)$unit['wind_mps']:null, 'timing_method'=>'unknown', 'attempt_invalid'=>false,
                'data_criacao'=>(string)$parent['data_inicio'], 'excluido_progresso'=>false,
            ];
        }
    }

    return $result;
}
