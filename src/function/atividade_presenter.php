<?php

declare(strict_types=1);

function atividadeCardFormatarValor(array $campo, mixed $valor): string
{
    if ($valor === null || $valor === '') return '';
    if (($campo['tipo_campo'] ?? '') === 'booleano') return stridebr_db_bool($valor) ? 'Sim' : 'Não';
    if (($campo['tipo_campo'] ?? '') === 'selecao') {
        foreach ($campo['opcoes'] ?? [] as $opcao) {
            if ((string) $opcao['idopcao'] === (string) $valor) return (string) $opcao['rotulo'];
        }
    }
    if (($campo['tipo_campo'] ?? '') === 'intervalo') return atividadeFormatarIntervalo($valor);
    $texto = (string) $valor;
    if (($campo['tipo_campo'] ?? '') === 'decimal' && is_numeric($texto)) $texto = rtrim(rtrim(number_format((float) $texto, 3, ',', '.'), '0'), ',');
    $unidade = trim((string) ($campo['unidade_simbolo'] ?? ''));
    return $unidade !== '' ? $texto . ' ' . $unidade : $texto;
}

function atividadeTotaisCanonicosUnidades(array $detalhes): array
{
    $units = is_array($detalhes['unidades'] ?? null) ? array_values($detalhes['unidades']) : [];
    $fields = [];
    $canonicalFields = [];
    foreach ($detalhes['campos'] ?? [] as $campo) {
        $id = (string) ($campo['idcampo'] ?? '');
        if ($id === '') continue;
        $fields[$id] = $campo;
        if ((string) ($campo['escopo'] ?? '') !== 'unidade') continue;
        $slug = stridebr_lower((string) ($campo['slug'] ?? ''));
        if (in_array($slug, ['distancia', 'duracao', 'elevacao', 'desnivel'], true) && !isset($canonicalFields[$slug])) $canonicalFields[$slug] = $campo;
    }
    $distanceM = 0.0;
    $durationS = 0;
    $elevationM = 0.0;
    $distanceCount = 0;
    $durationCount = 0;
    $elevationCount = 0;
    $derivedTypes = [];
    foreach ($units as $unit) {
        $values = is_array($unit['values'] ?? null) ? $unit['values'] : [];
        $distanceFound = false;
        $elevationFound = false;
        if (is_numeric($unit['distancia_metros'] ?? null)) {
            $distanceM += max(0.0, (float) $unit['distancia_metros']);
            $distanceFound = true;
        }
        if (is_numeric($unit['duracao_segundos'] ?? null)) {
            $durationS += max(0, (int) $unit['duracao_segundos']);
            $durationCount++;
        }
        if (is_numeric($unit['elevacao_m'] ?? null)) {
            $elevationM += max(0.0, (float) $unit['elevacao_m']);
            $elevationFound = true;
        }
        foreach ($values as $idCampo => $valor) {
            $campo = $fields[(string) $idCampo] ?? null;
            if (!$campo || (string) ($campo['escopo'] ?? '') !== 'unidade') continue;
            $slug = stridebr_lower((string) ($campo['slug'] ?? ''));
            if ($slug === 'distancia' && !$distanceFound && is_numeric($valor)) {
                $numeric = max(0.0, (float) $valor);
                $unitSymbol = stridebr_lower(trim((string) ($campo['unidade_simbolo'] ?? 'km')));
                $distanceM += $unitSymbol === 'm' ? $numeric : $numeric * 1000;
                $distanceFound = true;
            } elseif ($slug === 'duracao' && !is_numeric($unit['duracao_segundos'] ?? null)) {
                $texto = atividadeFormatarIntervalo($valor);
                if (preg_match('/^(\d+):([0-5]\d):([0-5]\d)$/', $texto, $partes)) {
                    $durationS += ((int) $partes[1] * 3600) + ((int) $partes[2] * 60) + (int) $partes[3];
                    $durationCount++;
                }
            } elseif (in_array($slug, ['elevacao', 'desnivel'], true) && !$elevationFound && is_numeric($valor)) {
                $numeric = (float) $valor;
                $unitSymbol = stridebr_lower(trim((string) ($campo['unidade_simbolo'] ?? 'm')));
                $elevationM += $unitSymbol === 'km' ? $numeric * 1000 : $numeric;
                $elevationFound = true;
            }
        }
        $route = is_array($unit['rota'] ?? null) ? $unit['rota'] : [];
        if (!$distanceFound && is_numeric($route['distancia_metros'] ?? null) && (float) $route['distancia_metros'] > 0) {
            $distanceM += (float) $route['distancia_metros'];
            $distanceFound = true;
        }
        if (!$elevationFound && is_numeric($route['ganho_elevacao_m'] ?? null)) {
            $elevationM += max(0.0, (float) $route['ganho_elevacao_m']);
            $elevationFound = true;
        }
        if ($distanceFound) $distanceCount++;
        if ($elevationFound) $elevationCount++;
        $derivedType = trim((string) ($unit['modalidade_metrica_derivada'] ?? $detalhes['metrica_derivada'] ?? 'nenhuma'));
        if ($derivedType !== '' && $derivedType !== 'nenhuma') $derivedTypes[$derivedType] = true;
    }
    return [
        'distancia_m' => $distanceCount > 0 ? $distanceM : null,
        'duracao_s' => $durationCount > 0 ? $durationS : null,
        'elevacao_m' => $elevationCount > 0 ? $elevationM : null,
        'distancia_count' => $distanceCount,
        'duracao_count' => $durationCount,
        'elevacao_count' => $elevationCount,
        'unit_count' => count($units),
        'derived_types' => array_keys($derivedTypes),
        'fields' => $canonicalFields,
    ];
}

function atividadeCardTotaisUnidades(array $detalhes): array
{
    $units = is_array($detalhes['unidades'] ?? null) ? $detalhes['unidades'] : [];
    $segmented = !empty($detalhes['usa_trechos']);
    if (!$segmented && count($units) <= 1) return [];
    $canonical = atividadeTotaisCanonicosUnidades($detalhes);
    $totals = [];
    foreach ($canonical['fields'] as $slug => $campo) {
        $idCampo = (string) ($campo['idcampo'] ?? '');
        if ($idCampo === '') continue;
        if ($slug === 'distancia' && $canonical['distancia_m'] !== null) {
            $unitSymbol = stridebr_lower(trim((string) ($campo['unidade_simbolo'] ?? 'km')));
            $totals[$idCampo] = $unitSymbol === 'm' ? $canonical['distancia_m'] : $canonical['distancia_m'] / 1000;
        } elseif ($slug === 'duracao' && $canonical['duracao_s'] !== null) {
            $seconds = max(0, (int) $canonical['duracao_s']);
            $totals[$idCampo] = sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
        } elseif (in_array($slug, ['elevacao', 'desnivel'], true) && $canonical['elevacao_m'] !== null) {
            $unitSymbol = stridebr_lower(trim((string) ($campo['unidade_simbolo'] ?? 'm')));
            $totals[$idCampo] = $unitSymbol === 'km' ? $canonical['elevacao_m'] / 1000 : $canonical['elevacao_m'];
        }
    }
    return $totals;
}

function atividadeCardMetricaDerivada(array $detalhes): ?array
{
    $tipo = (string) ($detalhes['metrica_derivada'] ?? 'nenhuma');
    if ($tipo === 'nenhuma') return null;
    $segmented = !empty($detalhes['usa_trechos']);
    if ($segmented) {
        $canonical = atividadeTotaisCanonicosUnidades($detalhes);
        $types = $canonical['derived_types'];
        if (count($types) > 1) return null;
        if (count($types) === 1) $tipo = (string) $types[0];
        $metros = $canonical['distancia_m'];
        $duracaoSegundos = $canonical['duracao_s'];
        if (!$metros || !$duracaoSegundos) return null;
    } else {
        $camposPorId = [];
        foreach ($detalhes['campos'] ?? [] as $campo) $camposPorId[(string) $campo['idcampo']] = $campo;
        $valores = $detalhes['record_values'] ?? [];
        $totaisUnidades = atividadeCardTotaisUnidades($detalhes);
        if ($totaisUnidades !== []) $valores += $totaisUnidades;
        elseif (!empty($detalhes['unidades'][0]['values'])) $valores += $detalhes['unidades'][0]['values'];
        $distancia = null;
        $distanciaUnidade = 'km';
        $duracaoSegundos = null;
        foreach ($valores as $idCampo => $valor) {
            $campo = $camposPorId[(string) $idCampo] ?? null;
            if (!$campo) continue;
            $slug = stridebr_lower((string) ($campo['slug'] ?? ''));
            if ($slug === 'distancia' && is_numeric($valor)) {
                $distancia = (float) $valor;
                $distanciaUnidade = trim((string) ($campo['unidade_simbolo'] ?? 'km')) ?: 'km';
            }
            if ($slug === 'duracao') {
                $texto = atividadeFormatarIntervalo($valor);
                if (preg_match('/^(\d+):([0-5]\d):([0-5]\d)$/', $texto, $partes)) $duracaoSegundos = ((int) $partes[1] * 3600) + ((int) $partes[2] * 60) + (int) $partes[3];
            }
        }
        if (!$distancia || !$duracaoSegundos) return null;
        $metros = $distanciaUnidade === 'm' ? $distancia : $distancia * 1000;
    }
    $km = $metros / 1000;
    if ($metros <= 0 || $km <= 0) return null;
    $formatarPace = static function (float $segundos): string {
        $total = (int) round($segundos);
        return intdiv($total, 60) . ':' . str_pad((string) ($total % 60), 2, '0', STR_PAD_LEFT);
    };
    return match ($tipo) {
        'pace_km' => ['rotulo' => 'Ritmo', 'valor' => $formatarPace($duracaoSegundos / $km) . '/km', 'prioridade' => 2],
        'velocidade_kmh' => ['rotulo' => 'Velocidade', 'valor' => number_format($km / ($duracaoSegundos / 3600), 1, ',', '.') . ' km/h', 'prioridade' => 2],
        'pace_100m' => ['rotulo' => 'Ritmo', 'valor' => $formatarPace($duracaoSegundos / ($metros / 100)) . '/100 m', 'prioridade' => 2],
        'split_500m' => ['rotulo' => 'Split', 'valor' => $formatarPace($duracaoSegundos / ($metros / 500)) . '/500 m', 'prioridade' => 2],
        default => null,
    };
}

function atividadeCardMetricas(array $detalhes, int $limite = 4): array
{
    $camposPorId = [];
    foreach ($detalhes['campos'] ?? [] as $campo) $camposPorId[(string) $campo['idcampo']] = $campo;
    $candidatos = [];
    $strength = in_array(stridebr_lower((string) ($detalhes['modalidade_slug'] ?? '')), ['musculacao', 'calistenia', 'crossfit'], true);
    if ($strength) {
        $hasCodeValue = false;
        $hasFocusValue = false;
        foreach ($detalhes['record_values'] ?? [] as $idCampo => $valor) {
            if ($valor === null || trim((string) $valor) === '') continue;
            $slug = stridebr_lower((string) ($camposPorId[(string) $idCampo]['slug'] ?? ''));
            if ($slug === 'codigo_treino') $hasCodeValue = true;
            if ($slug === 'foco_muscular') $hasFocusValue = true;
        }
        $codigo = trim((string) ($detalhes['treino_codigo'] ?? ''));
        $foco = trim((string) ($detalhes['treino_foco'] ?? ''));
        if (($codigo === '' || $foco === '') && preg_match('/^(?:academia|treino)\s+([A-Z0-9]+)\s*[-–—]\s*(.+)$/iu', trim((string) ($detalhes['titulo'] ?? '')), $partes)) {
            if ($codigo === '') $codigo = trim((string) ($partes[1] ?? ''));
            if ($foco === '') $foco = trim((string) ($partes[2] ?? ''));
        }
        if (!$hasCodeValue && $codigo !== '') $candidatos[] = ['rotulo' => 'Código', 'valor' => $codigo, 'prioridade' => -20, 'ordem' => 0];
        if (!$hasFocusValue && $foco !== '') $candidatos[] = ['rotulo' => 'Foco', 'valor' => $foco, 'prioridade' => -19, 'ordem' => 0];
    }
    $adicionar = static function (string $idCampo, mixed $valor) use (&$candidatos, $camposPorId, $strength): void {
        $campo = $camposPorId[$idCampo] ?? null;
        if (!$campo) return;
        $texto = atividadeCardFormatarValor($campo, $valor);
        if ($texto === '') return;
        $chave = stridebr_lower(trim((string) (($campo['slug'] ?? '') . ' ' . ($campo['rotulo'] ?? ''))));
        foreach (['intensidade', 'feeling', 'sensacao', 'sensação', 'esforco', 'esforço', 'observa', 'nota'] as $ocultar) if (str_contains($chave, $ocultar)) return;
        $prioridade = 50;
        if ($strength && str_contains($chave, 'codigo_treino')) $prioridade = -18;
        elseif ($strength && (str_contains($chave, 'foco_muscular') || str_contains($chave, 'foco muscular'))) $prioridade = -17;
        $grupos = [0 => ['distancia', 'distância'], 1 => ['duracao', 'duração', 'tempo'], 2 => ['ritmo', 'pace', 'velocidade'], 3 => ['elevacao', 'elevação', 'desnivel', 'desnível'], 4 => ['series', 'séries', 'sets'], 5 => ['repeticoes', 'repetições', 'reps'], 6 => ['carga', 'peso']];
        if ($prioridade >= 0) foreach ($grupos as $rank => $termos) foreach ($termos as $termo) if (str_contains($chave, $termo)) { $prioridade = $rank; break 2; }
        $candidatos[] = ['rotulo' => (string) $campo['rotulo'], 'valor' => $texto, 'prioridade' => $prioridade, 'ordem' => (int) ($campo['ordem'] ?? 999)];
    };
    foreach ($detalhes['record_values'] ?? [] as $idCampo => $valor) $adicionar((string) $idCampo, $valor);
    $totaisUnidades = atividadeCardTotaisUnidades($detalhes);
    $idsTotais = array_fill_keys(array_keys($totaisUnidades), true);
    foreach ($totaisUnidades as $idCampo => $valor) $adicionar((string) $idCampo, $valor);
    if (empty($detalhes['usa_trechos'])) {
        foreach ($detalhes['unidades'] ?? [] as $unidade) {
            foreach ($unidade['values'] ?? [] as $idCampo => $valor) {
                if (isset($idsTotais[(string) $idCampo])) continue;
                $adicionar((string) $idCampo, $valor);
            }
        }
    }
    $hasDuration = false;
    foreach ($candidatos as $item) {
        if (str_contains(stridebr_lower((string) ($item['rotulo'] ?? '')), 'dura')) { $hasDuration = true; break; }
    }
    if (!$hasDuration && !empty($detalhes['data_inicio']) && !empty($detalhes['data_fim'])) {
        try {
            $startedAt = new DateTimeImmutable((string) $detalhes['data_inicio']);
            $endedAt = new DateTimeImmutable((string) $detalhes['data_fim']);
            $seconds = max(0, $endedAt->getTimestamp() - $startedAt->getTimestamp());
            if ($seconds > 0) {
                $candidatos[] = ['rotulo' => 'Duração', 'valor' => sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60), 'prioridade' => 1, 'ordem' => 0];
            }
        } catch (Throwable) {}
    }
    $derivada = atividadeCardMetricaDerivada($detalhes);
    if ($derivada !== null) $candidatos[] = ['rotulo' => $derivada['rotulo'], 'valor' => $derivada['valor'], 'prioridade' => $derivada['prioridade'], 'ordem' => 0];
    $externalCalories = is_numeric($detalhes['calorias_externas'] ?? null) ? (float) $detalhes['calorias_externas'] : null;
    $estimatedCalories = is_numeric($detalhes['calorias_ativas_estimadas'] ?? null) ? (float) $detalhes['calorias_ativas_estimadas'] : null;
    if ($externalCalories !== null && $externalCalories > 0) {
        $candidatos[] = ['rotulo' => 'Calorias', 'valor' => number_format($externalCalories, 0, ',', '.') . ' kcal', 'prioridade' => 20, 'ordem' => 0];
    } elseif ($estimatedCalories !== null && $estimatedCalories > 0) {
        $candidatos[] = ['rotulo' => 'Calorias', 'valor' => '~' . number_format($estimatedCalories, 0, ',', '.') . ' kcal', 'prioridade' => 20, 'ordem' => 0];
    }
    usort($candidatos, static fn(array $a, array $b): int => [$a['prioridade'], $a['ordem']] <=> [$b['prioridade'], $b['ordem']]);
    $unicos = [];
    foreach ($candidatos as $item) {
        $key = stridebr_lower($item['rotulo']);
        if (isset($unicos[$key])) continue;
        $unicos[$key] = $item;
        if (count($unicos) >= $limite) break;
    }
    return array_values($unicos);
}

function atividadeCampoOpcional(array $campo): bool
{
    return empty($campo['obrigatorio']) && !stridebr_db_bool($campo['exibicao_padrao'] ?? true);
}

function atividadeCampoOrdemVisual(array $campo): int
{
    return match (stridebr_lower((string) ($campo['slug'] ?? ''))) {
        'distancia' => 10, 'duracao' => 20, 'ritmo', 'pace', 'velocidade' => 30, 'elevacao', 'desnivel' => 40,
        default => 100 + (int) ($campo['ordem'] ?? 0),
    };
}

function atividadeMesAbreviado(DateTimeInterface $data): string
{
    $meses = [1 => 'JAN', 'FEV', 'MAR', 'ABR', 'MAI', 'JUN', 'JUL', 'AGO', 'SET', 'OUT', 'NOV', 'DEZ'];
    return $meses[(int) $data->format('n')] ?? '';
}

function atividadeHistoricoMetricaKind(string $label): string
{
    $value = stridebr_lower(trim($label));
    $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if (is_string($normalized)) $value = strtolower($normalized);
    if (str_contains($value, 'codigo')) return 'code';
    if (str_contains($value, 'foco')) return 'focus';
    if (str_contains($value, 'duracao') || str_contains($value, 'tempo')) return 'duration';
    if (str_contains($value, 'exercicio')) return 'exercises';
    if (str_contains($value, 'serie') || str_contains($value, 'set')) return 'sets';
    if (str_contains($value, 'esforco') || str_contains($value, 'sensacao')) return 'effort';
    return 'metric';
}

function atividadeHistoricoLinhaHtml(array $item): string
{
    $id = (string) ($item['id'] ?? '');
    $title = (string) ($item['titulo'] ?? $item['modalidade'] ?? 'Atividade');
    $sport = (string) ($item['modalidade'] ?? 'Atividade');
    $sportSlug = (string) ($item['modalidade_slug'] ?? '');
    $strengthClass = in_array($sportSlug, ['musculacao', 'calistenia', 'crossfit'], true) ? ' is-strength-row' : '';
    $metrics = '';
    foreach ((array) ($item['metricas'] ?? []) as $metric) {
        $label = (string) ($metric['rotulo'] ?? '');
        $value = (string) ($metric['valor'] ?? '');
        if ($label === '' || $value === '') continue;
        $metrics .= '<span data-metric-kind="' . stridebr_e(atividadeHistoricoMetricaKind($label)) . '"><small>' . stridebr_e($label) . '</small><strong>' . stridebr_e($value) . '</strong></span>';
    }
    if ($metrics !== '') $metrics = '<div class="activity-row-metrics">' . $metrics . '</div>';
    $icon = (string) ($item['icone_html'] ?? '');

    return '<article class="activity-list-row' . $strengthClass . '" data-history-row data-activity-id="' . stridebr_e($id) . '" data-activity-title="' . stridebr_e($title) . '" data-activity-sport="' . stridebr_e($sportSlug) . '">' .
        '<label class="activity-row-select" aria-label="Selecionar ' . stridebr_e($title) . '"><input type="checkbox" value="' . stridebr_e($id) . '" data-bulk-row-select><span aria-hidden="true"></span></label>' .
        '<button type="button" class="activity-row-main" data-open-activity-detail="' . stridebr_e($id) . '">' .
            '<div class="activity-row-date"><strong>' . stridebr_e((string) ($item['dia'] ?? '')) . '</strong><span>' . stridebr_e((string) ($item['mes'] ?? '')) . '</span></div>' .
            '<div class="activity-row-icon">' . $icon . '</div>' .
            '<div class="activity-row-title"><strong>' . stridebr_e($title) . '</strong><span>' . stridebr_e($sport) . ' · ' . stridebr_e((string) ($item['hora'] ?? '')) . '</span></div>' .
            $metrics .
            '<span class="activity-row-open" aria-hidden="true">›</span>' .
        '</button>' .
    '</article>';
}

function atividadeResumoHistorico(PDO $pdo, string $idUsuario, string $modalidadeSlug = ''): array
{
    $tz = new DateTimeZone('America/Sao_Paulo');
    $fim = new DateTimeImmutable('tomorrow', $tz);
    $inicio = $fim->modify('-7 days');
    $params = [
        ':usuario' => $idUsuario,
        ':inicio' => $inicio->format('Y-m-d H:i:sP'),
        ':fim' => $fim->format('Y-m-d H:i:sP'),
    ];
    $sportFilter = '';
    $modalidadeSlug = trim($modalidadeSlug);
    if ($modalidadeSlug !== '') {
        $sportFilter = ' AND m.slug = :modalidade_slug';
        $params[':modalidade_slug'] = $modalidadeSlug;
    }
    $sql = "WITH registros_periodo AS MATERIALIZED (
                SELECT ra.idregistro, ra.data_inicio, ra.data_fim, ra.usa_trechos
                FROM registros_atividade ra
                JOIN modalidades m ON m.idmodalidade = ra.idmodalidade
                WHERE ra.idusuario = :usuario
                  AND ra.excluido_em IS NULL
                  AND ra.status = 'concluido'
                  AND ra.data_inicio >= :inicio
                  AND ra.data_inicio < :fim" . $sportFilter . "
            ),
            metricas AS (
                SELECT va.idregistro,
                       SUM(va.valor_normalizado) FILTER (WHERE lower(cm.slug) = 'distancia') AS distancia_m,
                       SUM(va.valor_normalizado) FILTER (WHERE lower(cm.slug) = 'duracao') AS duracao_s,
                       SUM(va.valor_normalizado) FILTER (WHERE lower(cm.slug) IN ('elevacao', 'desnivel')) AS elevacao_m
                FROM registros_periodo rp
                JOIN valores_atividade va ON va.idregistro = rp.idregistro
                JOIN campos_modelo cm ON cm.idcampo = va.idcampo
                GROUP BY va.idregistro
            ),
            metricas_trechos AS (
                SELECT ua.idregistro,
                       SUM(COALESCE(ua.distancia_metros, ru.distancia_metros)) FILTER (WHERE COALESCE(ua.distancia_metros, ru.distancia_metros) IS NOT NULL) AS distancia_m,
                       SUM(ua.duracao_segundos) FILTER (WHERE ua.duracao_segundos IS NOT NULL) AS duracao_s,
                       SUM(COALESCE(ua.elevacao_m, ru.ganho_elevacao_m)) FILTER (WHERE COALESCE(ua.elevacao_m, ru.ganho_elevacao_m) IS NOT NULL) AS elevacao_m
                FROM registros_periodo rp
                JOIN unidades_atividade ua ON ua.idregistro = rp.idregistro
                LEFT JOIN rotas_unidades_atividade ru ON ru.idunidade_atividade = ua.idunidade_atividade
                WHERE rp.usa_trechos = TRUE
                GROUP BY ua.idregistro
            )
            SELECT COUNT(*) AS atividades,
                   COALESCE(SUM(CASE
                       WHEN rp.usa_trechos AND COALESCE(st.duracao_s, 0) > 0 THEN st.duracao_s
                       WHEN COALESCE(mt.duracao_s, 0) > 0 THEN mt.duracao_s
                       WHEN rp.data_fim IS NOT NULL THEN GREATEST(EXTRACT(EPOCH FROM (rp.data_fim - rp.data_inicio)), 0)
                       ELSE 0 END), 0) AS duracao_s,
                   COALESCE(SUM(CASE
                       WHEN rp.usa_trechos AND COALESCE(st.distancia_m, 0) > 0 THEN st.distancia_m
                       WHEN COALESCE(mt.distancia_m, 0) > 0 THEN mt.distancia_m
                       ELSE COALESCE(rt.distancia_metros, 0) END), 0) AS distancia_m,
                   COALESCE(SUM(CASE
                       WHEN rp.usa_trechos AND COALESCE(st.elevacao_m, 0) > 0 THEN st.elevacao_m
                       WHEN COALESCE(mt.elevacao_m, 0) > 0 THEN mt.elevacao_m
                       ELSE COALESCE(rt.ganho_elevacao_m, 0) END), 0) AS elevacao_m
            FROM registros_periodo rp
            LEFT JOIN metricas mt ON mt.idregistro = rp.idregistro
            LEFT JOIN metricas_trechos st ON st.idregistro = rp.idregistro
            LEFT JOIN rotas_atividade rt ON rt.idregistro = rp.idregistro";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch() ?: [];
    $seconds = max(0, (int) round((float) ($row['duracao_s'] ?? 0)));
    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    $tempo = $hours > 0 ? $hours . 'h ' . $minutes . 'min' : $minutes . 'min';
    $distance = max(0, (float) ($row['distancia_m'] ?? 0));
    $distanceText = $distance >= 1000
        ? number_format($distance / 1000, $distance >= 10000 ? 0 : 1, ',', '.') . ' km'
        : number_format($distance, 0, ',', '.') . ' m';
    return [
        'atividades' => (int) ($row['atividades'] ?? 0),
        'tempo' => $tempo,
        'distancia' => $distanceText,
        'elevacao' => number_format(max(0, (float) ($row['elevacao_m'] ?? 0)), 0, ',', '.') . ' m',
        'periodo' => 'Últimos 7 dias',
    ];
}

function atividadeListarRegistrosPagina(PDO $pdo, string $idUsuario, int $limite = 20, ?string $cursor = null, string $busca = '', string $modalidadeSlug = ''): array
{
    $limite = max(5, min(50, $limite));
    $params = [':usuario' => $idUsuario];
    $where = ['ra.idusuario = :usuario', 'ra.excluido_em IS NULL'];
    if ($cursor !== null && $cursor !== '') {
        $parts = explode('|', $cursor, 2);
        if (count($parts) === 2 && preg_match('/^\d{4}-\d{2}-\d{2}T/', $parts[0])) {
            $where[] = '(ra.data_inicio, ra.idregistro) < (CAST(:cursor_data AS timestamptz), :cursor_id)';
            $params[':cursor_data'] = $parts[0];
            $params[':cursor_id'] = $parts[1];
        }
    }
    $busca = trim($busca);
    if ($busca !== '') {
        $where[] = "(lower(COALESCE(ra.titulo,'')) LIKE :busca_titulo OR lower(m.nome) LIKE :busca_modalidade OR lower(COALESCE(tc.codigo,'')) LIKE :busca_codigo OR lower(COALESCE(tc.foco,'')) LIKE :busca_foco)";
        $searchTerm = '%' . stridebr_lower($busca) . '%';
        $params[':busca_titulo'] = $searchTerm;
        $params[':busca_modalidade'] = $searchTerm;
        $params[':busca_codigo'] = $searchTerm;
        $params[':busca_foco'] = $searchTerm;
    }
    $modalidadeSlug = trim($modalidadeSlug);
    if ($modalidadeSlug !== '') {
        $where[] = 'm.slug = :modalidade_slug';
        $params[':modalidade_slug'] = $modalidadeSlug;
    }
    $sql = "SELECT ra.idregistro, ra.idmodelo, ra.titulo, ra.data_inicio, ra.data_fim, ra.esforco_percebido,
                   m.nome AS modalidade_nome, m.slug AS modalidade_slug, m.metrica_derivada,
                   tc.codigo AS treino_codigo, tc.foco AS treino_foco
            FROM registros_atividade ra
            JOIN modalidades m ON m.idmodalidade = ra.idmodalidade
            LEFT JOIN treinos_cronograma tc ON tc.idtreino = ra.idtreino_cronograma
            WHERE " . implode(' AND ', $where) . "
            ORDER BY ra.data_inicio DESC, ra.idregistro DESC
            LIMIT " . ($limite + 1);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    $hasMore = count($rows) > $limite;
    if ($hasMore) $rows = array_slice($rows, 0, $limite);
    if ($rows === []) return ['items' => [], 'next_cursor' => null];
    $details = atividadeCarregarRegistrosResumo($pdo, $rows);
    $items = [];
    foreach ($rows as $row) {
        $detail = $details[(string) $row['idregistro']] ?? ['campos' => [], 'record_values' => [], 'unidades' => [], 'metrica_derivada' => $row['metrica_derivada'] ?? 'nenhuma'];
        $detail['data_inicio'] = $row['data_inicio'] ?? null;
        $detail['data_fim'] = $row['data_fim'] ?? null;
        $detail['titulo'] = $row['titulo'] ?? '';
        $detail['modalidade_slug'] = $row['modalidade_slug'] ?? '';
        $detail['treino_codigo'] = $row['treino_codigo'] ?? '';
        $detail['treino_foco'] = $row['treino_foco'] ?? '';
        $date = new DateTimeImmutable((string) $row['data_inicio']);
        $items[] = [
            'id' => (string) $row['idregistro'], 'titulo' => (string) ($row['titulo'] ?: $row['modalidade_nome']),
            'modalidade' => (string) $row['modalidade_nome'], 'modalidade_slug' => (string) $row['modalidade_slug'],
            'icone_html' => function_exists('stridebr_sport_icon_html') ? stridebr_sport_icon_html((string) $row['modalidade_slug']) : '',
            'data_iso' => $date->format(DATE_ATOM), 'data' => $date->format('d/m/Y'), 'dia' => $date->format('d'), 'mes' => atividadeMesAbreviado($date), 'hora' => $date->format('H:i'),
            'metricas' => atividadeCardMetricas($detail), 'esforco' => $row['esforco_percebido'] !== null ? (int) $row['esforco_percebido'] : null,
        ];
    }
    $last = end($rows);
    $next = $hasMore && $last ? (new DateTimeImmutable((string) $last['data_inicio']))->format(DATE_ATOM) . '|' . $last['idregistro'] : null;
    return ['items' => $items, 'next_cursor' => $next];
}

function atividadeCarregarRegistrosResumo(PDO $pdo, array $registros): array
{
    $ids = array_values(array_unique(array_filter(array_map(static fn(array $row): string => (string) ($row['idregistro'] ?? ''), $registros))));
    if ($ids === []) return [];

    $result = [];
    foreach ($registros as $row) {
        $rid = (string) ($row['idregistro'] ?? '');
        if ($rid === '') continue;
        $result[$rid] = [
            'campos' => [],
            'record_values' => [],
            'unidades' => [],
            'metrica_derivada' => (string) ($row['metrica_derivada'] ?? 'nenhuma'),
        ];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT va.idregistro, va.idunidade_atividade,
                ua.ordem AS unidade_ordem, ua.rotulo AS unidade_rotulo,
                cm.idcampo, cm.slug, cm.rotulo AS campo_rotulo, cm.tipo_campo, cm.escopo,
                cm.obrigatorio, cm.ordem AS campo_ordem, cm.exibicao_padrao, cm.grupo_ui,
                u.simbolo AS unidade_simbolo,
                va.valor_texto, va.valor_inteiro, va.valor_decimal, va.valor_booleano,
                va.valor_data, va.valor_hora, va.valor_intervalo, va.idopcao,
                cmo.rotulo AS opcao_rotulo, cmo.valor AS opcao_valor
         FROM valores_atividade va
         JOIN campos_modelo cm ON cm.idcampo = va.idcampo
         LEFT JOIN unidades u ON u.idunidade = cm.idunidade
         LEFT JOIN unidades_atividade ua ON ua.idunidade_atividade = va.idunidade_atividade
         LEFT JOIN campos_modelo_opcoes cmo ON cmo.idopcao = va.idopcao AND cmo.idcampo = va.idcampo
         WHERE va.idregistro IN ({$placeholders})
         ORDER BY va.idregistro, COALESCE(ua.ordem, 0), cm.ordem, cm.rotulo"
    );
    $stmt->execute($ids);

    $fieldsByRecord = [];
    $unitsByRecord = [];
    foreach ($stmt->fetchAll() as $value) {
        $rid = (string) $value['idregistro'];
        if (!isset($result[$rid])) continue;
        $fieldId = (string) $value['idcampo'];
        if (!isset($fieldsByRecord[$rid][$fieldId])) {
            $field = [
                'idcampo' => $fieldId,
                'slug' => $value['slug'],
                'rotulo' => $value['campo_rotulo'],
                'tipo_campo' => $value['tipo_campo'],
                'escopo' => $value['escopo'],
                'obrigatorio' => stridebr_db_bool($value['obrigatorio']),
                'ordem' => (int) $value['campo_ordem'],
                'exibicao_padrao' => stridebr_db_bool($value['exibicao_padrao']),
                'grupo_ui' => $value['grupo_ui'] ?: 'detalhes',
                'unidade_simbolo' => $value['unidade_simbolo'],
                'opcoes' => [],
            ];
            if ($value['idopcao'] !== null && $value['opcao_rotulo'] !== null) {
                $field['opcoes'][] = [
                    'idopcao' => $value['idopcao'],
                    'rotulo' => $value['opcao_rotulo'],
                    'valor' => $value['opcao_valor'],
                ];
            }
            $fieldsByRecord[$rid][$fieldId] = $field;
        }

        $resolvedValue = atividadeValorLinha($value);
        $unitId = $value['idunidade_atividade'] !== null ? (string) $value['idunidade_atividade'] : '';
        if ($unitId === '') {
            $result[$rid]['record_values'][$fieldId] = $resolvedValue;
            continue;
        }
        if (!isset($unitsByRecord[$rid][$unitId])) {
            $unitsByRecord[$rid][$unitId] = [
                'idunidade_atividade' => $unitId,
                'ordem' => (int) ($value['unidade_ordem'] ?? 0),
                'rotulo' => (string) ($value['unidade_rotulo'] ?? ''),
                'values' => [],
            ];
        }
        $unitsByRecord[$rid][$unitId]['values'][$fieldId] = $resolvedValue;
    }

    foreach ($result as $rid => &$detail) {
        $fields = array_values($fieldsByRecord[$rid] ?? []);
        usort($fields, static fn(array $a, array $b): int => [$a['ordem'], $a['rotulo']] <=> [$b['ordem'], $b['rotulo']]);
        $detail['campos'] = $fields;
        $units = array_values($unitsByRecord[$rid] ?? []);
        usort($units, static fn(array $a, array $b): int => [$a['ordem'], $a['idunidade_atividade']] <=> [$b['ordem'], $b['idunidade_atividade']]);
        $detail['unidades'] = $units;
    }
    unset($detail);

    return $result;
}

function atividadeDetalheApi(PDO $pdo, string $idRegistro, string $idUsuario): array
{
    $registro = atividadeCarregarRegistro($pdo, $idRegistro, $idUsuario);
    if ($registro === []) return [];
    $camposPorId = [];
    foreach ($registro['campos'] ?? [] as $campo) $camposPorId[(string) $campo['idcampo']] = $campo;
    $formatValues = static function (array $values) use ($camposPorId): array {
        $out = [];
        foreach ($values as $idCampo => $valor) {
            $campo = $camposPorId[(string) $idCampo] ?? null;
            if (!$campo) continue;
            $formatted = atividadeCardFormatarValor($campo, $valor);
            if ($formatted === '') continue;
            $out[] = ['rotulo' => (string) $campo['rotulo'], 'valor' => $formatted];
        }
        return $out;
    };
    $units = [];
    $sourceUnits = array_values($registro['unidades'] ?? []);
    $lastUnitIndex = count($sourceUnits) - 1;
    foreach ($sourceUnits as $index => $unit) {
        $unitRoute = null;
        if (is_array($unit['rota'] ?? null)) {
            $unitGeo = json_decode((string) ($unit['rota']['coordenadas'] ?? ''), true);
            $unitProfile = json_decode((string) ($unit['rota']['perfil_elevacao'] ?? ''), true);
            if (is_array($unitGeo) && ($unitGeo['type'] ?? '') === 'LineString') {
                $unitRoute = [
                    'geojson' => $unitGeo,
                    'distancia_m' => (float) ($unit['rota']['distancia_metros'] ?? 0),
                    'ganho_m' => $unit['rota']['ganho_elevacao_m'] !== null ? (float) $unit['rota']['ganho_elevacao_m'] : null,
                    'perda_m' => $unit['rota']['perda_elevacao_m'] !== null ? (float) $unit['rota']['perda_elevacao_m'] : null,
                    'min_m' => $unit['rota']['elevacao_min_m'] !== null ? (float) $unit['rota']['elevacao_min_m'] : null,
                    'max_m' => $unit['rota']['elevacao_max_m'] !== null ? (float) $unit['rota']['elevacao_max_m'] : null,
                    'perfil' => is_array($unitProfile) ? $unitProfile : [],
                    'ocultar_inicio_m' => $index === 0 ? max(0, (int) ($registro['ocultar_inicio_m'] ?? 0)) : 0,
                    'ocultar_fim_m' => $index === $lastUnitIndex ? max(0, (int) ($registro['ocultar_fim_m'] ?? 0)) : 0,
                ];
            }
        }
        $unitDetails = $registro;
        $unitDetails['record_values'] = [];
        $unitDetails['usa_trechos'] = false;
        $unitDetails['modalidade_slug'] = (string) ($unit['modalidade_slug'] ?? $registro['modalidade_slug'] ?? '');
        $unitDetails['metrica_derivada'] = (string) ($unit['modalidade_metrica_derivada'] ?? $registro['metrica_derivada'] ?? 'nenhuma');
        $unitValuesForShare = is_array($unit['values'] ?? null) ? $unit['values'] : [];
        foreach ($registro['campos'] ?? [] as $campo) {
            if (($campo['escopo'] ?? '') !== 'unidade') continue;
            $idCampo = (string) ($campo['idcampo'] ?? '');
            if ($idCampo === '' || isset($unitValuesForShare[$idCampo]) && trim((string) $unitValuesForShare[$idCampo]) !== '') continue;
            $slug = stridebr_lower((string) ($campo['slug'] ?? ''));
            if ($slug === 'distancia') {
                $meters = is_numeric($unit['distancia_metros'] ?? null) ? (float) $unit['distancia_metros'] : (($unitRoute['distancia_m'] ?? 0) > 0 ? (float) $unitRoute['distancia_m'] : null);
                if ($meters !== null) $unitValuesForShare[$idCampo] = (($campo['unidade_simbolo'] ?? 'm') === 'km') ? $meters / 1000 : $meters;
            } elseif ($slug === 'duracao' && is_numeric($unit['duracao_segundos'] ?? null)) {
                $seconds = max(0, (int) $unit['duracao_segundos']);
                $unitValuesForShare[$idCampo] = sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
            } elseif (in_array($slug, ['elevacao', 'desnivel'], true)) {
                $meters = is_numeric($unit['elevacao_m'] ?? null) ? (float) $unit['elevacao_m'] : (is_numeric($unitRoute['ganho_m'] ?? null) ? (float) $unitRoute['ganho_m'] : null);
                if ($meters !== null) $unitValuesForShare[$idCampo] = (($campo['unidade_simbolo'] ?? 'm') === 'km') ? $meters / 1000 : $meters;
            }
        }
        $unitDetails['unidades'] = [['values' => $unitValuesForShare]];
        $unitDetails['data_inicio'] = null;
        $unitDetails['data_fim'] = null;
        $unitMetrics = atividadeCardMetricas($unitDetails, 8);
        $unitSlug = (string) ($unit['modalidade_slug'] ?? $registro['modalidade_slug'] ?? '');
        $units[] = [
            'id' => (string) ($unit['idunidade_atividade'] ?? ''),
            'tipo' => (string) ($unit['tipo_unidade'] ?? $registro['tipo_unidade_padrao'] ?? 'unidade'),
            'rotulo' => (string) ($unit['rotulo'] ?: (($registro['rotulo_unidade'] ?? 'Unidade') . ' ' . ($index + 1))),
            'modalidade' => (string) ($unit['modalidade_nome'] ?? $registro['modalidade_nome'] ?? 'Atividade'),
            'modalidade_slug' => $unitSlug,
            'modalidade_icone' => function_exists('stridebr_sport_icon_id') ? stridebr_sport_icon_id($unitSlug) : 'track_and_field',
            'metrica_derivada' => (string) ($unit['modalidade_metrica_derivada'] ?? $registro['metrica_derivada'] ?? 'nenhuma'),
            'distancia_metros' => is_numeric($unit['distancia_metros'] ?? null) ? (float) $unit['distancia_metros'] : (is_numeric($unitRoute['distancia_m'] ?? null) ? (float) $unitRoute['distancia_m'] : null),
            'duracao_segundos' => is_numeric($unit['duracao_segundos'] ?? null) ? (int) $unit['duracao_segundos'] : null,
            'elevacao_m' => is_numeric($unit['elevacao_m'] ?? null) ? (float) $unit['elevacao_m'] : (is_numeric($unitRoute['ganho_m'] ?? null) ? (float) $unitRoute['ganho_m'] : null),
            'valores' => $formatValues($unitValuesForShare),
            'metricas_compartilhamento' => $unitMetrics,
            'rota' => $unitRoute,
        ];
    }
    $route = null;
    if (empty($registro['usa_trechos']) && is_array($registro['rota'] ?? null)) {
        $geo = json_decode((string) ($registro['rota']['coordenadas'] ?? ''), true);
        $profile = json_decode((string) ($registro['rota']['perfil_elevacao'] ?? ''), true);
        if (is_array($geo) && ($geo['type'] ?? '') === 'LineString') {
            $route = ['geojson' => $geo, 'distancia_m' => (float) ($registro['rota']['distancia_metros'] ?? 0),
                'ganho_m' => $registro['rota']['ganho_elevacao_m'] !== null ? (float) $registro['rota']['ganho_elevacao_m'] : null,
                'perda_m' => $registro['rota']['perda_elevacao_m'] !== null ? (float) $registro['rota']['perda_elevacao_m'] : null,
                'min_m' => $registro['rota']['elevacao_min_m'] !== null ? (float) $registro['rota']['elevacao_min_m'] : null,
                'max_m' => $registro['rota']['elevacao_max_m'] !== null ? (float) $registro['rota']['elevacao_max_m'] : null,
                'perfil' => is_array($profile) ? $profile : [],
                'ocultar_inicio_m' => max(0, (int) ($registro['ocultar_inicio_m'] ?? 0)),
                'ocultar_fim_m' => max(0, (int) ($registro['ocultar_fim_m'] ?? 0))];
        }
    }
    $gpsWeb = null;
    try {
        $gpsStmt = $pdo->prepare(
            'SELECT distancia_medida_m, distancia_final_m, duracao_s, pontos_recebidos, pontos_aceitos, pontos_rejeitados,
                    precisao_media_m, precisao_melhor_m, precisao_pior_m, lacunas_visibilidade,
                    tipo_meta, valor_meta, finalizado_por_meta, usuario_ajustou
             FROM gravacoes_gps_web WHERE idregistro = :registro LIMIT 1'
        );
        $gpsStmt->execute([':registro' => $idRegistro]);
        $gpsRow = $gpsStmt->fetch();
        if (is_array($gpsRow)) {
            $gpsWeb = [
                'distancia_medida_m' => $gpsRow['distancia_medida_m'] !== null ? (float) $gpsRow['distancia_medida_m'] : null,
                'distancia_final_m' => $gpsRow['distancia_final_m'] !== null ? (float) $gpsRow['distancia_final_m'] : null,
                'duracao_s' => (int) ($gpsRow['duracao_s'] ?? 0),
                'pontos_recebidos' => (int) ($gpsRow['pontos_recebidos'] ?? 0),
                'pontos_aceitos' => (int) ($gpsRow['pontos_aceitos'] ?? 0),
                'pontos_rejeitados' => (int) ($gpsRow['pontos_rejeitados'] ?? 0),
                'precisao_media_m' => $gpsRow['precisao_media_m'] !== null ? (float) $gpsRow['precisao_media_m'] : null,
                'precisao_melhor_m' => $gpsRow['precisao_melhor_m'] !== null ? (float) $gpsRow['precisao_melhor_m'] : null,
                'precisao_pior_m' => $gpsRow['precisao_pior_m'] !== null ? (float) $gpsRow['precisao_pior_m'] : null,
                'lacunas_visibilidade' => (int) ($gpsRow['lacunas_visibilidade'] ?? 0),
                'tipo_meta' => $gpsRow['tipo_meta'] !== null ? (string) $gpsRow['tipo_meta'] : null,
                'valor_meta' => $gpsRow['valor_meta'] !== null ? (float) $gpsRow['valor_meta'] : null,
                'finalizado_por_meta' => !empty($gpsRow['finalizado_por_meta']),
                'usuario_ajustou' => !empty($gpsRow['usuario_ajustou']),
            ];
        }
    } catch (PDOException $e) {
        if ($e->getCode() !== '42P01') throw $e;
    }

    $strength = null;
    if (stridebr_db_table_exists($pdo, 'series_exercicio_atividade')) {
        $strengthStmt = $pdo->prepare('SELECT idexercicio, nome_exercicio, ordem_exercicio, ordem_serie, tipo, carga_kg, repeticoes, rir, rpe, concluida FROM series_exercicio_atividade WHERE idregistro = :registro ORDER BY ordem_exercicio, ordem_serie, idserie');
        $strengthStmt->execute([':registro' => $idRegistro]);
        $strengthRows = $strengthStmt->fetchAll();
        if ($strengthRows !== []) {
            $strengthExercises = [];
            $strengthVolume = 0.0;
            $strengthSets = 0;
            foreach ($strengthRows as $row) {
                $order = (int) $row['ordem_exercicio'];
                if (!isset($strengthExercises[$order])) {
                    $strengthExercises[$order] = [
                        'id' => (string) ($row['idexercicio'] ?? ''),
                        'nome' => (string) $row['nome_exercicio'],
                        'series' => [],
                        'melhor_carga_kg' => null,
                        'volume_kg' => 0.0,
                    ];
                }
                $load = $row['carga_kg'] !== null ? (float) $row['carga_kg'] : null;
                $reps = $row['repeticoes'] !== null ? (int) $row['repeticoes'] : null;
                $completed = stridebr_db_bool($row['concluida']);
                $setVolume = $completed && $load !== null && $reps !== null ? $load * $reps : 0.0;
                if ($completed) {
                    $strengthSets++;
                    $strengthVolume += $setVolume;
                    $strengthExercises[$order]['volume_kg'] += $setVolume;
                    if ($load !== null && ($strengthExercises[$order]['melhor_carga_kg'] === null || $load > $strengthExercises[$order]['melhor_carga_kg'])) $strengthExercises[$order]['melhor_carga_kg'] = $load;
                }
                $strengthExercises[$order]['series'][] = [
                    'numero' => (int) $row['ordem_serie'],
                    'tipo' => (string) $row['tipo'],
                    'carga_kg' => $load,
                    'repeticoes' => $reps,
                    'rir' => $row['rir'] !== null ? (float) $row['rir'] : null,
                    'rpe' => $row['rpe'] !== null ? (float) $row['rpe'] : null,
                    'concluida' => $completed,
                ];
            }
            $strength = [
                'exercicios' => array_values($strengthExercises),
                'total_exercicios' => count($strengthExercises),
                'total_series' => $strengthSets,
                'volume_kg' => round($strengthVolume, 1),
            ];
        }
    }

    $date = new DateTimeImmutable((string) $registro['data_inicio']);
    $registro['modalidade_slug'] = $registro['modalidade_slug'] ?? '';
    $shareMetrics = atividadeCardMetricas($registro, 12);
    if (!empty($registro['esforco_percebido'])) $shareMetrics[] = ['rotulo' => 'Esforço', 'valor' => (int) $registro['esforco_percebido'] . '/10'];
    $externalCalories = is_numeric($registro['calorias_externas'] ?? null) ? (float) $registro['calorias_externas'] : null;
    $estimatedCalories = is_numeric($registro['calorias_ativas_estimadas'] ?? null) ? (float) $registro['calorias_ativas_estimadas'] : null;
    $energy = null;
    if ($externalCalories !== null && $externalCalories > 0) {
        $rawEnergySource = stridebr_lower(trim((string) ($registro['calorias_fonte'] ?? '')) ?: trim((string) ($registro['origem'] ?? '')));
        $energySource = match ($rawEnergySource) {
            'garmin' => 'Garmin Connect',
            'strava' => 'Strava',
            'polar' => 'Polar Flow',
            'fitbit' => 'Fitbit',
            'suunto' => 'Suunto',
            'health_connect' => 'Health Connect',
            'samsung_health' => 'Samsung Health',
            'apple_health' => 'Apple Health',
            'fit' => 'arquivo FIT',
            'gpx' => 'arquivo GPX',
            'tcx' => 'arquivo TCX',
            default => $rawEnergySource !== '' ? $rawEnergySource : 'origem da atividade',
        };
        $energy = [
            'kcal' => round($externalCalories, 1),
            'estimated' => false,
            'source' => $energySource,
            'confidence' => null,
            'total_kcal' => null,
            'weight_kg' => null,
        ];
    } elseif ($estimatedCalories !== null && $estimatedCalories > 0) {
        $energy = [
            'kcal' => round($estimatedCalories, 1),
            'estimated' => true,
            'source' => 'StrideBR',
            'confidence' => trim((string) ($registro['calorias_confianca'] ?? '')) ?: null,
            'total_kcal' => is_numeric($registro['calorias_totais_estimadas'] ?? null) ? round((float) $registro['calorias_totais_estimadas'], 1) : null,
            'weight_kg' => is_numeric($registro['peso_calculo_kg'] ?? null) ? round((float) $registro['peso_calculo_kg'], 2) : null,
        ];
    }
    return [
        'id' => $idRegistro, 'titulo' => (string) ($registro['titulo'] ?: $registro['modalidade_nome']), 'modalidade' => (string) $registro['modalidade_nome'], 'modalidade_slug' => (string) $registro['modalidade_slug'], 'modalidade_icone' => function_exists('stridebr_sport_icon_id') ? stridebr_sport_icon_id((string) $registro['modalidade_slug']) : 'track_and_field', 'origem' => (string) ($registro['origem'] ?? ''),
        'data' => $date->format('d/m/Y'), 'hora' => $date->format('H:i'), 'visibilidade' => (string) $registro['visibilidade'], 'esforco' => $registro['esforco_percebido'] !== null ? (int) $registro['esforco_percebido'] : null,
        'observacoes' => (string) ($registro['observacoes'] ?? ''), 'usa_trechos' => !empty($registro['usa_trechos']), 'metricas' => atividadeCardMetricas($registro), 'metricas_compartilhamento' => $shareMetrics, 'energia' => $energy,
        'dados' => $formatValues($registro['record_values'] ?? []), 'unidades' => $units, 'equipamentos' => array_map(static fn(array $e): array => ['id' => (string) $e['idequipamento'], 'nome' => (string) $e['nome']], $registro['equipamentos'] ?? []),
        'treino' => (!empty($registro['treino_codigo']) || !empty($registro['treino_foco']) || !empty($registro['treino_titulo'])) ? [
            'codigo' => (string) ($registro['treino_codigo'] ?? ''),
            'foco' => (string) ($registro['treino_foco'] ?? ''),
            'titulo' => (string) ($registro['treino_titulo'] ?? ''),
        ] : null,
        'rota' => $route,
        'gps_web' => $gpsWeb,
        'forca' => $strength,
    ];
}
