<?php

declare(strict_types=1);

require_once __DIR__ . '/activity_sport_context.php';
require_once __DIR__ . '/marketing.php';
require_once __DIR__ . '/competitions.php';


function atividadeGerarId(int $length = 21): string
{
    return stridebr_generate_id($length);
}

const ATIVIDADE_ROTA_MAX_PONTOS = 2000;
const ATIVIDADE_ROTA_MAX_JSON_BYTES = 200000;
const ATIVIDADE_ELEVACAO_MAX_AMOSTRAS = 100;

function atividadeDistanciaCoordenadas(array $a, array $b): float
{
    $earth = 6371008.8;
    $lat1 = deg2rad((float) $a[1]);
    $lat2 = deg2rad((float) $b[1]);
    $deltaLat = $lat2 - $lat1;
    $deltaLon = deg2rad((float) $b[0] - (float) $a[0]);
    $h = sin($deltaLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($deltaLon / 2) ** 2;
    return $earth * 2 * atan2(sqrt($h), sqrt(max(0.0, 1 - $h)));
}

function atividadeDistanciaRota(array $coordinates): float
{
    $distance = 0.0;
    for ($index = 1, $count = count($coordinates); $index < $count; $index++) {
        $distance += atividadeDistanciaCoordenadas($coordinates[$index - 1], $coordinates[$index]);
    }
    return $distance;
}

function atividadeValidarRotaGeoJson(mixed $raw, bool $allowEmpty = true): ?array
{
    if ($raw === null || $raw === '') {
        if ($allowEmpty) return null;
        throw new InvalidArgumentException('Desenhe ao menos dois pontos para salvar a rota.');
    }
    if (is_string($raw)) {
        if (strlen($raw) > ATIVIDADE_ROTA_MAX_JSON_BYTES) throw new InvalidArgumentException('A rota enviada é muito grande.');
        try {
            $geojson = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidArgumentException('A rota enviada é inválida.');
        }
    } elseif (is_array($raw)) {
        $geojson = $raw;
        $encoded = json_encode($geojson);
        if ($encoded === false || strlen($encoded) > ATIVIDADE_ROTA_MAX_JSON_BYTES) throw new InvalidArgumentException('A rota enviada é muito grande.');
    } else {
        throw new InvalidArgumentException('A rota enviada é inválida.');
    }
    if (($geojson['type'] ?? null) !== 'LineString' || !is_array($geojson['coordinates'] ?? null)) {
        throw new InvalidArgumentException('A rota deve ser uma linha GeoJSON válida.');
    }
    $coordinates = $geojson['coordinates'];
    if (count($coordinates) < 2) throw new InvalidArgumentException('Desenhe ao menos dois pontos para salvar a rota.');
    if (count($coordinates) > ATIVIDADE_ROTA_MAX_PONTOS) throw new InvalidArgumentException('A rota excede o limite de ' . ATIVIDADE_ROTA_MAX_PONTOS . ' pontos.');
    $validated = [];
    foreach ($coordinates as $point) {
        if (!is_array($point) || count($point) !== 2 || !is_numeric($point[0]) || !is_numeric($point[1])) {
            throw new InvalidArgumentException('Uma das coordenadas da rota é inválida.');
        }
        $longitude = (float) $point[0];
        $latitude = (float) $point[1];
        if (!is_finite($longitude) || !is_finite($latitude) || $longitude < -180 || $longitude > 180 || $latitude < -90 || $latitude > 90) {
            throw new InvalidArgumentException('Uma das coordenadas da rota está fora dos limites geográficos.');
        }
        $validated[] = [round($longitude, 7), round($latitude, 7)];
    }
    return ['type' => 'LineString', 'coordinates' => $validated];
}

function atividadeReamostrarRota(array $coordinates, int $limit = ATIVIDADE_ELEVACAO_MAX_AMOSTRAS): array
{
    $limit = max(2, min(ATIVIDADE_ELEVACAO_MAX_AMOSTRAS, $limit));
    $cumulative = [0.0];
    for ($i = 1, $count = count($coordinates); $i < $count; $i++) {
        $cumulative[$i] = $cumulative[$i - 1] + atividadeDistanciaCoordenadas($coordinates[$i - 1], $coordinates[$i]);
    }
    $total = end($cumulative) ?: 0.0;
    if ($total <= 0) return array_slice($coordinates, 0, $limit);
    // Cerca de uma amostra a cada 100 m evita um perfil artificialmente plano
    // quando o usuário desenha poucos vértices, sem ultrapassar o limite da API.
    $sampleCount = min($limit, max(count($coordinates), (int) ceil($total / 100) + 1));
    if ($sampleCount === count($coordinates) && count($coordinates) <= $limit) return $coordinates;
    $sampled = [];
    $segment = 1;
    for ($sample = 0; $sample < $sampleCount; $sample++) {
        $target = $total * ($sample / ($sampleCount - 1));
        while ($segment < count($cumulative) - 1 && $cumulative[$segment] < $target) $segment++;
        $previous = max(0, $segment - 1);
        $span = $cumulative[$segment] - $cumulative[$previous];
        $ratio = $span > 0 ? ($target - $cumulative[$previous]) / $span : 0;
        $sampled[] = [
            $coordinates[$previous][0] + ($coordinates[$segment][0] - $coordinates[$previous][0]) * $ratio,
            $coordinates[$previous][1] + ($coordinates[$segment][1] - $coordinates[$previous][1]) * $ratio,
        ];
    }
    return $sampled;
}

function atividadeCalcularPerfilElevacao(array $coordinates, array $elevations): ?array
{
    if (count($coordinates) < 2 || count($coordinates) !== count($elevations)) return null;
    $smoothed = [];
    $count = count($elevations);
    for ($i = 0; $i < $count; $i++) {
        $values = [];
        for ($j = max(0, $i - 2); $j <= min($count - 1, $i + 2); $j++) {
            if (is_numeric($elevations[$j])) $values[] = (float) $elevations[$j];
        }
        if ($values === []) return null;
        $smoothed[] = array_sum($values) / count($values);
    }
    $profile = [['distancia_m' => 0.0, 'elevacao_m' => round($smoothed[0], 1)]];
    $gain = 0.0;
    $loss = 0.0;
    $distance = 0.0;
    for ($i = 1; $i < $count; $i++) {
        $distance += atividadeDistanciaCoordenadas($coordinates[$i - 1], $coordinates[$i]);
        $delta = $smoothed[$i] - $smoothed[$i - 1];
        if ($delta >= 0.8) $gain += $delta;
        elseif ($delta <= -0.8) $loss += abs($delta);
        $profile[] = ['distancia_m' => round($distance, 1), 'elevacao_m' => round($smoothed[$i], 1)];
    }
    return [
        'ganho_elevacao_m' => round($gain, 1),
        'perda_elevacao_m' => round($loss, 1),
        'elevacao_min_m' => round(min($smoothed), 1),
        'elevacao_max_m' => round(max($smoothed), 1),
        'perfil_elevacao' => $profile,
        'fonte_elevacao' => 'open-meteo-copernicus',
    ];
}

function atividadeConsultarElevacao(array $geojson, int $timeoutSeconds = 4): ?array
{
    if (getenv('STRIDEBR_ELEVATION_API_ENABLED') === '0') return null;
    $coordinates = atividadeReamostrarRota($geojson['coordinates']);
    $latitudes = implode(',', array_map(static fn(array $point): string => (string) round((float) $point[1], 6), $coordinates));
    $longitudes = implode(',', array_map(static fn(array $point): string => (string) round((float) $point[0], 6), $coordinates));
    $cacheDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'stridebr-elevation';
    $cacheFile = $cacheDir . DIRECTORY_SEPARATOR . hash('sha256', $latitudes . '|' . $longitudes) . '.json';
    if (is_file($cacheFile) && (time() - (int) filemtime($cacheFile)) < 604800) {
        $cached = @file_get_contents($cacheFile);
        if (is_string($cached) && $cached !== '') {
            try {
                $result = json_decode($cached, true, 32, JSON_THROW_ON_ERROR);
                if (is_array($result)) return $result;
            } catch (JsonException) {
            }
        }
    }
    $url = 'https://api.open-meteo.com/v1/elevation?' . http_build_query(['latitude' => $latitudes, 'longitude' => $longitudes]);
    $context = stream_context_create(['http' => ['timeout' => max(1, min(8, $timeoutSeconds)), 'ignore_errors' => true, 'header' => "Accept: application/json\r\nUser-Agent: StrideBR/1.0\r\n"]]);
    $response = @file_get_contents($url, false, $context);
    if (!is_string($response) || $response === '' || strlen($response) > 100000) return null;
    try {
        $data = json_decode($response, true, 16, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return null;
    }
    if (!is_array($data['elevation'] ?? null)) return null;
    $result = atividadeCalcularPerfilElevacao($coordinates, $data['elevation']);
    if (!is_dir($cacheDir)) @mkdir($cacheDir, 0700, true);
    if (is_dir($cacheDir)) @file_put_contents($cacheFile, json_encode($result, JSON_UNESCAPED_SLASHES), LOCK_EX);
    return $result;
}

function atividadeAplicarMetricaRotaNoPayload(array &$payload, array $campos, array $slugs, float $valueMeters, bool $somenteSeVazio = false): void
{
    foreach ($campos as $campo) {
        if (!in_array(stridebr_lower((string) ($campo['slug'] ?? '')), $slugs, true)) continue;
        $value = ($campo['unidade_simbolo'] ?? 'm') === 'km' ? $valueMeters / 1000 : $valueMeters;
        if (($campo['escopo'] ?? '') === 'registro') {
            $current = $payload['record_values'][(string) $campo['idcampo']] ?? null;
            if ($somenteSeVazio && is_scalar($current) && trim((string) $current) !== '') return;
            $payload['record_values'][(string) $campo['idcampo']] = round($value, 3);
        } else {
            if (!isset($payload['unidades'][0]) || !is_array($payload['unidades'][0])) $payload['unidades'][0] = ['values' => []];
            $current = $payload['unidades'][0]['values'][(string) $campo['idcampo']] ?? null;
            if ($somenteSeVazio && is_scalar($current) && trim((string) $current) !== '') return;
            $payload['unidades'][0]['values'][(string) $campo['idcampo']] = round($value, 3);
        }
        return;
    }
}

function atividadeListarCatalogo(PDO $pdo, string $idUsuario): array
{
    $stmt = $pdo->prepare(
        "SELECT
            m.idmodalidade,
            m.nome AS modalidade_nome,
            m.slug AS modalidade_slug,
            m.categoria AS modalidade_categoria,
            m.familia_hub AS modalidade_familia_hub,
            m.icone AS modalidade_icone,
            m.ordem_catalogo,
            m.metrica_derivada,
            m.permite_rota,
            COALESCE(mu.favorita, FALSE) AS favorita,
            COALESCE(mu.ativo, FALSE) AS pratica,
            mu.ordem_preferencia,
            mu.ultimo_uso,
            mm.idmodelo,
            mm.nome AS modelo_nome,
            mm.slug AS modelo_slug,
            mm.versao,
            mm.padrao,
            mm.tipo_unidade_padrao,
            mm.rotulo_unidade,
            mm.permite_multiplas_unidades
        FROM modalidades m
        JOIN modelos_modalidade mm ON mm.idmodalidade = m.idmodalidade
        LEFT JOIN modalidades_usuario mu ON mu.idusuario = :usuario_preferencia AND mu.idmodalidade = m.idmodalidade
        WHERE m.ativo = TRUE
          AND mm.ativo = TRUE
          AND (m.idusuario IS NULL OR m.idusuario = :usuario_modalidade)
          AND (mm.idusuario IS NULL OR mm.idusuario = :usuario_modelo)
        ORDER BY COALESCE(mu.favorita, FALSE) DESC, COALESCE(mu.ativo, FALSE) DESC, mu.ordem_preferencia NULLS LAST, mu.ultimo_uso DESC NULLS LAST, m.ordem_catalogo, m.nome, mm.padrao DESC, mm.nome, mm.versao DESC"
    );
    $stmt->execute([':usuario_preferencia' => $idUsuario, ':usuario_modalidade' => $idUsuario, ':usuario_modelo' => $idUsuario]);
    $rows = $stmt->fetchAll();

    $catalogo = [];
    foreach ($rows as $row) {
        $modalidadeId = $row['idmodalidade'];
        if (!isset($catalogo[$modalidadeId])) {
            $catalogo[$modalidadeId] = [
                'idmodalidade' => $modalidadeId,
                'nome' => $row['modalidade_nome'],
                'slug' => $row['modalidade_slug'],
                'categoria' => $row['modalidade_categoria'] ?: 'Outras atividades',
                'familia_hub' => $row['modalidade_familia_hub'] ?: '',
                'icone' => $row['modalidade_icone'] ?: '•',
                'ordem_catalogo' => (int) $row['ordem_catalogo'],
                'metrica_derivada' => $row['metrica_derivada'] ?: 'nenhuma',
                'permite_rota' => stridebr_db_bool($row['permite_rota']),
                'favorita' => stridebr_db_bool($row['favorita']),
                'pratica' => stridebr_db_bool($row['pratica']),
                'ordem_preferencia' => $row['ordem_preferencia'] === null ? null : (int) $row['ordem_preferencia'],
                'ultimo_uso' => $row['ultimo_uso'],
                'modelos' => [],
            ];
        }
        $catalogo[$modalidadeId]['modelos'][] = [
            'idmodelo' => $row['idmodelo'],
            'nome' => $row['modelo_nome'],
            'slug' => $row['modelo_slug'],
            'versao' => (int) $row['versao'],
            'padrao' => stridebr_db_bool($row['padrao']),
            'tipo_unidade_padrao' => $row['tipo_unidade_padrao'],
            'rotulo_unidade' => $row['rotulo_unidade'],
            'permite_multiplas_unidades' => stridebr_db_bool($row['permite_multiplas_unidades']),
        ];
    }

    return array_values($catalogo);
}


function atividadeListarModalidadesCatalogoLeve(PDO $pdo, string $idUsuario): array
{
    $stmt = $pdo->prepare(
        "SELECT
            m.idmodalidade,
            m.nome,
            m.slug,
            m.categoria,
            m.familia_hub,
            m.icone,
            m.ordem_catalogo,
            m.metrica_derivada,
            m.permite_rota,
            COALESCE(mu.favorita, FALSE) AS favorita,
            COALESCE(mu.ativo, FALSE) AS pratica,
            mu.ordem_preferencia,
            mu.ultimo_uso
        FROM modalidades m
        LEFT JOIN modalidades_usuario mu ON mu.idusuario = :usuario_preferencia AND mu.idmodalidade = m.idmodalidade
        WHERE m.ativo = TRUE
          AND (m.idusuario IS NULL OR m.idusuario = :usuario_modalidade)
          AND EXISTS (
              SELECT 1
              FROM modelos_modalidade mm
              WHERE mm.idmodalidade = m.idmodalidade
                AND mm.ativo = TRUE
                AND (mm.idusuario IS NULL OR mm.idusuario = :usuario_modelo)
          )
        ORDER BY COALESCE(mu.favorita, FALSE) DESC, COALESCE(mu.ativo, FALSE) DESC, mu.ordem_preferencia NULLS LAST, mu.ultimo_uso DESC NULLS LAST, m.ordem_catalogo, m.nome"
    );
    $stmt->execute([
        ':usuario_preferencia' => $idUsuario,
        ':usuario_modalidade' => $idUsuario,
        ':usuario_modelo' => $idUsuario,
    ]);

    return array_map(static fn(array $row): array => [
        'idmodalidade' => $row['idmodalidade'],
        'nome' => $row['nome'],
        'slug' => $row['slug'],
        'categoria' => $row['categoria'] ?: 'Outras atividades',
        'familia_hub' => $row['familia_hub'] ?: '',
        'icone' => $row['icone'] ?: '•',
        'ordem_catalogo' => (int) $row['ordem_catalogo'],
        'metrica_derivada' => $row['metrica_derivada'] ?: 'nenhuma',
        'permite_rota' => stridebr_db_bool($row['permite_rota']),
        'favorita' => stridebr_db_bool($row['favorita']),
        'pratica' => stridebr_db_bool($row['pratica']),
        'ordem_preferencia' => $row['ordem_preferencia'] === null ? null : (int) $row['ordem_preferencia'],
        'ultimo_uso' => $row['ultimo_uso'],
        'modelos' => [],
    ], $stmt->fetchAll());
}

function atividadeBuscarCatalogoModelo(PDO $pdo, string $idUsuario, string $idModelo): ?array
{
    $stmt = $pdo->prepare(
        "SELECT
            m.idmodalidade,
            m.nome AS modalidade_nome,
            m.slug AS modalidade_slug,
            m.categoria AS modalidade_categoria,
            m.familia_hub AS modalidade_familia_hub,
            m.icone AS modalidade_icone,
            m.ordem_catalogo,
            m.metrica_derivada,
            m.permite_rota,
            COALESCE(mu.favorita, FALSE) AS favorita,
            COALESCE(mu.ativo, FALSE) AS pratica,
            mu.ordem_preferencia,
            mu.ultimo_uso,
            mm.idmodelo,
            mm.nome AS modelo_nome,
            mm.slug AS modelo_slug,
            mm.versao,
            mm.padrao,
            mm.tipo_unidade_padrao,
            mm.rotulo_unidade,
            mm.permite_multiplas_unidades
        FROM modelos_modalidade mm
        JOIN modalidades m ON m.idmodalidade = mm.idmodalidade
        LEFT JOIN modalidades_usuario mu ON mu.idusuario = :usuario_preferencia AND mu.idmodalidade = m.idmodalidade
        WHERE mm.idmodelo = :modelo
          AND m.ativo = TRUE
          AND mm.ativo = TRUE
          AND (m.idusuario IS NULL OR m.idusuario = :usuario_modalidade)
          AND (mm.idusuario IS NULL OR mm.idusuario = :usuario_modelo)
        LIMIT 1"
    );
    $stmt->execute([
        ':modelo' => $idModelo,
        ':usuario_preferencia' => $idUsuario,
        ':usuario_modalidade' => $idUsuario,
        ':usuario_modelo' => $idUsuario,
    ]);
    $row = $stmt->fetch();
    if (!$row) return null;

    return [
        'idmodalidade' => $row['idmodalidade'],
        'nome' => $row['modalidade_nome'],
        'slug' => $row['modalidade_slug'],
        'categoria' => $row['modalidade_categoria'] ?: 'Outras atividades',
        'familia_hub' => $row['modalidade_familia_hub'] ?: '',
        'icone' => $row['modalidade_icone'] ?: '•',
        'ordem_catalogo' => (int) $row['ordem_catalogo'],
        'metrica_derivada' => $row['metrica_derivada'] ?: 'nenhuma',
        'permite_rota' => stridebr_db_bool($row['permite_rota']),
        'favorita' => stridebr_db_bool($row['favorita']),
        'pratica' => stridebr_db_bool($row['pratica']),
        'ordem_preferencia' => $row['ordem_preferencia'] === null ? null : (int) $row['ordem_preferencia'],
        'ultimo_uso' => $row['ultimo_uso'],
        'modelos' => [[
            'idmodelo' => $row['idmodelo'],
            'nome' => $row['modelo_nome'],
            'slug' => $row['modelo_slug'],
            'versao' => (int) $row['versao'],
            'padrao' => stridebr_db_bool($row['padrao']),
            'tipo_unidade_padrao' => $row['tipo_unidade_padrao'],
            'rotulo_unidade' => $row['rotulo_unidade'],
            'permite_multiplas_unidades' => stridebr_db_bool($row['permite_multiplas_unidades']),
        ]],
    ];
}

function atividadeDefinirModalidadeFavorita(PDO $pdo, string $idUsuario, string $idModalidade, bool $favorita): void
{
    $stmt = $pdo->prepare(
        "SELECT m.idmodalidade, mm.idmodelo
         FROM modalidades m
         JOIN modelos_modalidade mm ON mm.idmodalidade = m.idmodalidade AND mm.ativo = TRUE
         WHERE m.idmodalidade = :modalidade
           AND m.ativo = TRUE
           AND (m.idusuario IS NULL OR m.idusuario = :usuario_modalidade)
           AND (mm.idusuario IS NULL OR mm.idusuario = :usuario_modelo)
         ORDER BY mm.padrao DESC, mm.versao DESC
         LIMIT 1"
    );
    $stmt->execute([
        ':modalidade' => $idModalidade,
        ':usuario_modalidade' => $idUsuario,
        ':usuario_modelo' => $idUsuario,
    ]);
    $modalidade = $stmt->fetch();
    if (!$modalidade) {
        throw new InvalidArgumentException('Modalidade inválida.');
    }

    $existing = $pdo->prepare('SELECT ativo FROM modalidades_usuario WHERE idusuario = :usuario AND idmodalidade = :modalidade LIMIT 1');
    $existing->execute([':usuario' => $idUsuario, ':modalidade' => $idModalidade]);
    $existingRow = $existing->fetch(PDO::FETCH_ASSOC);

    if (!$existingRow && !$favorita) {
        return;
    }

    if (!$existingRow) {
        $insert = $pdo->prepare(
            'INSERT INTO modalidades_usuario (idusuario, idmodalidade, idmodelo_ativo, ativo, favorita, data_ativacao, data_desativacao)
             VALUES (:usuario, :modalidade, :modelo, FALSE, TRUE, NOW(), NOW())'
        );
        $insert->execute([':usuario' => $idUsuario, ':modalidade' => $idModalidade, ':modelo' => $modalidade['idmodelo']]);
        return;
    }

    $update = $pdo->prepare(
        'UPDATE modalidades_usuario
         SET idmodelo_ativo = COALESCE(idmodelo_ativo, :modelo), favorita = :favorita
         WHERE idusuario = :usuario AND idmodalidade = :modalidade'
    );
    $update->bindValue(':modelo', $modalidade['idmodelo']);
    $update->bindValue(':favorita', $favorita, PDO::PARAM_BOOL);
    $update->bindValue(':usuario', $idUsuario);
    $update->bindValue(':modalidade', $idModalidade);
    $update->execute();
}

function atividadeAtualizarEsportesUsuario(PDO $pdo, string $idUsuario, array $praticados, array $favoritos): void
{
    $praticados = array_values(array_unique(array_filter(array_map('strval', $praticados))));
    $favoritos = array_values(array_unique(array_filter(array_map('strval', $favoritos))));
    $requested = array_values(array_unique(array_merge($praticados, $favoritos)));

    $valid = [];
    $models = [];
    $stmt = $pdo->prepare(
        "SELECT m.idmodalidade, mm.idmodelo
         FROM modalidades m
         JOIN modelos_modalidade mm ON mm.idmodalidade = m.idmodalidade AND mm.ativo = TRUE
         WHERE m.ativo = TRUE
           AND (m.idusuario IS NULL OR m.idusuario = :usuario_modalidade)
           AND (mm.idusuario IS NULL OR mm.idusuario = :usuario_modelo)
         ORDER BY m.idmodalidade, mm.padrao DESC, mm.versao DESC"
    );
    $stmt->execute([':usuario_modalidade' => $idUsuario, ':usuario_modelo' => $idUsuario]);
    foreach ($stmt->fetchAll() as $row) {
        $id = (string) $row['idmodalidade'];
        if (!isset($models[$id])) {
            $models[$id] = (string) $row['idmodelo'];
            $valid[$id] = true;
        }
    }

    foreach ($requested as $id) {
        if (!isset($valid[$id])) {
            throw new InvalidArgumentException('Uma das modalidades selecionadas não está disponível.');
        }
    }

    $existingStmt = $pdo->prepare('SELECT idmodalidade FROM modalidades_usuario WHERE idusuario = :usuario');
    $existingStmt->execute([':usuario' => $idUsuario]);
    $existingIds = array_map('strval', array_column($existingStmt->fetchAll(), 'idmodalidade'));
    $allTouched = array_values(array_unique(array_merge($existingIds, $requested)));

    $upsert = $pdo->prepare(
        'INSERT INTO modalidades_usuario (idusuario, idmodalidade, idmodelo_ativo, ativo, favorita, data_ativacao, data_desativacao)
         VALUES (:usuario, :modalidade, :modelo, :ativo, :favorita, NOW(), :desativacao)
         ON CONFLICT (idusuario, idmodalidade) DO UPDATE SET
             idmodelo_ativo = COALESCE(modalidades_usuario.idmodelo_ativo, EXCLUDED.idmodelo_ativo),
             ativo = EXCLUDED.ativo,
             favorita = EXCLUDED.favorita,
             data_ativacao = CASE WHEN EXCLUDED.ativo AND NOT modalidades_usuario.ativo THEN NOW() ELSE modalidades_usuario.data_ativacao END,
             data_desativacao = CASE WHEN EXCLUDED.ativo THEN NULL ELSE COALESCE(modalidades_usuario.data_desativacao, NOW()) END'
    );

    foreach ($allTouched as $id) {
        if (!isset($valid[$id])) {
            continue;
        }
        $active = in_array($id, $praticados, true);
        $favorite = in_array($id, $favoritos, true);
        $upsert->bindValue(':usuario', $idUsuario);
        $upsert->bindValue(':modalidade', $id);
        $upsert->bindValue(':modelo', $models[$id]);
        $upsert->bindValue(':ativo', $active, PDO::PARAM_BOOL);
        $upsert->bindValue(':favorita', $favorite, PDO::PARAM_BOOL);
        if ($active) {
            $upsert->bindValue(':desativacao', null, PDO::PARAM_NULL);
        } else {
            $upsert->bindValue(':desativacao', date('Y-m-d H:i:sP'));
        }
        $upsert->execute();
    }
}


function atividadeListarEquipamentos(PDO $pdo, string $idUsuario, bool $somenteAtivos = true): array
{
    $activeClause = $somenteAtivos ? ' AND eu.ativo = TRUE' : '';
    $stmt = $pdo->prepare(
        "SELECT eu.*,
                COUNT(DISTINCT rae.idregistro) AS total_atividades,
                COALESCE(SUM(CASE
                    WHEN lower(cm.slug) = 'distancia' AND va.valor_decimal IS NOT NULL AND g.slug = 'distancia'
                         AND (va.idunidade_atividade IS NULL OR ua.ordem = 1)
                    THEN (va.valor_decimal * u.fator_para_base) / 1000
                    ELSE 0
                END), 0) + eu.distancia_inicial_km AS distancia_total_km
         FROM equipamentos_usuario eu
         LEFT JOIN registros_atividade_equipamentos rae ON rae.idequipamento = eu.idequipamento
         LEFT JOIN registros_atividade ra ON ra.idregistro = rae.idregistro AND ra.excluido_em IS NULL
         LEFT JOIN valores_atividade va ON va.idregistro = ra.idregistro
         LEFT JOIN unidades_atividade ua ON ua.idunidade_atividade = va.idunidade_atividade
         LEFT JOIN campos_modelo cm ON cm.idcampo = va.idcampo
         LEFT JOIN unidades u ON u.idunidade = cm.idunidade
         LEFT JOIN grandezas g ON g.idgrandeza = cm.idgrandeza
         WHERE eu.idusuario = :usuario" . $activeClause . "
         GROUP BY eu.idequipamento
         ORDER BY eu.ativo DESC, eu.nome"
    );
    $stmt->execute([':usuario' => $idUsuario]);
    return $stmt->fetchAll();
}

function atividadeSalvarEquipamento(PDO $pdo, string $idUsuario, array $payload, ?string $idEquipamento = null): string
{
    $nome = trim((string) ($payload['nome'] ?? ''));
    $categoria = trim((string) ($payload['categoria'] ?? 'outro'));
    $marca = trim((string) ($payload['marca'] ?? ''));
    $modelo = trim((string) ($payload['modelo'] ?? ''));
    $observacoes = trim((string) ($payload['observacoes'] ?? ''));
    $dataInicioRaw = trim((string) ($payload['data_inicio_uso'] ?? ''));
    $distanciaRaw = str_replace(',', '.', trim((string) ($payload['distancia_inicial_km'] ?? '0')));

    if ($nome === '' || stridebr_length($nome) > 120) {
        throw new InvalidArgumentException('Informe um nome de equipamento com até 120 caracteres.');
    }
    if ($categoria === '' || stridebr_length($categoria) > 40) {
        throw new InvalidArgumentException('Categoria de equipamento inválida.');
    }
    if ($marca !== '' && stridebr_length($marca) > 80) {
        throw new InvalidArgumentException('A marca é muito longa.');
    }
    if ($modelo !== '' && stridebr_length($modelo) > 100) {
        throw new InvalidArgumentException('O modelo é muito longo.');
    }
    if (!is_numeric($distanciaRaw) || (float) $distanciaRaw < 0) {
        throw new InvalidArgumentException('A quilometragem inicial é inválida.');
    }

    $dataInicio = null;
    if ($dataInicioRaw !== '') {
        $data = DateTimeImmutable::createFromFormat('!Y-m-d', $dataInicioRaw);
        if (!$data || $data->format('Y-m-d') !== $dataInicioRaw) {
            throw new InvalidArgumentException('Data de início de uso inválida.');
        }
        $dataInicio = $dataInicioRaw;
    }

    if ($idEquipamento !== null) {
        $stmt = $pdo->prepare(
            'UPDATE equipamentos_usuario
             SET nome = :nome, categoria = :categoria, marca = :marca, modelo = :modelo,
                 data_inicio_uso = :data_inicio, distancia_inicial_km = :distancia,
                 observacoes = :observacoes, data_atualizacao = NOW()
             WHERE idequipamento = :id AND idusuario = :usuario'
        );
        $stmt->execute([
            ':nome' => $nome,
            ':categoria' => $categoria,
            ':marca' => $marca !== '' ? $marca : null,
            ':modelo' => $modelo !== '' ? $modelo : null,
            ':data_inicio' => $dataInicio,
            ':distancia' => $distanciaRaw,
            ':observacoes' => $observacoes !== '' ? $observacoes : null,
            ':id' => $idEquipamento,
            ':usuario' => $idUsuario,
        ]);
        if ($stmt->rowCount() === 0) {
            $owner = $pdo->prepare('SELECT 1 FROM equipamentos_usuario WHERE idequipamento = :id AND idusuario = :usuario');
            $owner->execute([':id' => $idEquipamento, ':usuario' => $idUsuario]);
            if (!$owner->fetchColumn()) {
                throw new InvalidArgumentException('Equipamento não encontrado.');
            }
        }
        return $idEquipamento;
    }

    $idEquipamento = atividadeGerarId();
    $stmt = $pdo->prepare(
        'INSERT INTO equipamentos_usuario
         (idequipamento, idusuario, nome, categoria, marca, modelo, data_inicio_uso, distancia_inicial_km, observacoes)
         VALUES (:id, :usuario, :nome, :categoria, :marca, :modelo, :data_inicio, :distancia, :observacoes)'
    );
    $stmt->execute([
        ':id' => $idEquipamento,
        ':usuario' => $idUsuario,
        ':nome' => $nome,
        ':categoria' => $categoria,
        ':marca' => $marca !== '' ? $marca : null,
        ':modelo' => $modelo !== '' ? $modelo : null,
        ':data_inicio' => $dataInicio,
        ':distancia' => $distanciaRaw,
        ':observacoes' => $observacoes !== '' ? $observacoes : null,
    ]);
    return $idEquipamento;
}

function atividadeDefinirEquipamentoAtivo(PDO $pdo, string $idUsuario, string $idEquipamento, bool $ativo): bool
{
    $stmt = $pdo->prepare('UPDATE equipamentos_usuario SET ativo = :ativo, data_atualizacao = NOW() WHERE idequipamento = :id AND idusuario = :usuario');
    $stmt->bindValue(':ativo', $ativo, PDO::PARAM_BOOL);
    $stmt->bindValue(':id', $idEquipamento);
    $stmt->bindValue(':usuario', $idUsuario);
    $stmt->execute();
    return $stmt->rowCount() === 1;
}

function atividadeBuscarModelo(PDO $pdo, string $idModelo, string $idUsuario, bool $somenteAtivo = true): array
{
    $activeClause = $somenteAtivo ? ' AND mm.ativo = TRUE AND m.ativo = TRUE' : '';
    $stmt = $pdo->prepare(
        "SELECT
            mm.idmodelo,
            mm.idmodalidade,
            mm.nome,
            mm.slug,
            mm.versao,
            mm.tipo_unidade_padrao,
            mm.rotulo_unidade,
            mm.permite_multiplas_unidades,
            m.nome AS modalidade_nome,
            m.slug AS modalidade_slug,
            m.categoria AS modalidade_categoria,
            m.familia_hub AS modalidade_familia_hub,
            m.permite_rota
        FROM modelos_modalidade mm
        JOIN modalidades m ON m.idmodalidade = mm.idmodalidade
        WHERE mm.idmodelo = :modelo
          AND (mm.idusuario IS NULL OR mm.idusuario = :usuario_modelo)
          AND (m.idusuario IS NULL OR m.idusuario = :usuario_modalidade)" . $activeClause . "
        LIMIT 1"
    );
    $stmt->execute([':modelo' => $idModelo, ':usuario_modelo' => $idUsuario, ':usuario_modalidade' => $idUsuario]);
    $modelo = $stmt->fetch();
    if (!$modelo) {
        return [];
    }
    $modelo['permite_multiplas_unidades'] = stridebr_db_bool($modelo['permite_multiplas_unidades']);
    $modelo['permite_rota'] = stridebr_db_bool($modelo['permite_rota']);
    return $modelo;
}

function atividadeBuscarModeloPadraoModalidade(PDO $pdo, string $idModalidade, string $idUsuario): array
{
    $stmt = $pdo->prepare(
        "SELECT mm.idmodelo
         FROM modelos_modalidade mm
         JOIN modalidades m ON m.idmodalidade = mm.idmodalidade
         LEFT JOIN modalidades_usuario mu
           ON mu.idusuario = :usuario_pref
          AND mu.idmodalidade = mm.idmodalidade
         WHERE mm.idmodalidade = :modalidade
           AND mm.ativo = TRUE
           AND m.ativo = TRUE
           AND (mm.idusuario IS NULL OR mm.idusuario = :usuario_modelo)
           AND (m.idusuario IS NULL OR m.idusuario = :usuario_modalidade)
         ORDER BY CASE WHEN mu.idmodelo_ativo = mm.idmodelo THEN 0 WHEN mm.padrao = TRUE THEN 1 ELSE 2 END,
                  mm.versao DESC,
                  mm.idmodelo
         LIMIT 1"
    );
    $stmt->execute([
        ':usuario_pref' => $idUsuario,
        ':modalidade' => $idModalidade,
        ':usuario_modelo' => $idUsuario,
        ':usuario_modalidade' => $idUsuario,
    ]);
    $idModelo = $stmt->fetchColumn();
    return $idModelo === false ? [] : atividadeBuscarModelo($pdo, (string) $idModelo, $idUsuario, true);
}

function atividadeRemapearValoresModelo(array $camposOrigem, array $camposDestino, array $recordValues, array $unidades): array
{
    $origemPorId = [];
    foreach ($camposOrigem as $campo) {
        $origemPorId[(string) $campo['idcampo']] = $campo;
    }
    $destinoPorChave = [];
    foreach ($camposDestino as $campo) {
        $destinoPorChave[(string) $campo['escopo'] . ':' . stridebr_lower((string) $campo['slug'])] = $campo;
    }

    $mapValue = static function (array $origem, array $destino, mixed $raw): mixed {
        if ($raw === null || (is_string($raw) && trim($raw) === '')) return null;
        if (($origem['tipo_campo'] ?? '') !== 'selecao' || ($destino['tipo_campo'] ?? '') !== 'selecao') return $raw;
        $valor = null;
        foreach ($origem['opcoes'] ?? [] as $opcao) {
            if ((string) ($opcao['idopcao'] ?? '') === (string) $raw) {
                $valor = (string) ($opcao['valor'] ?? '');
                break;
            }
        }
        if ($valor === null) return null;
        foreach ($destino['opcoes'] ?? [] as $opcao) {
            if ((string) ($opcao['valor'] ?? '') === $valor) return (string) $opcao['idopcao'];
        }
        return null;
    };

    $mappedRecord = [];
    foreach ($recordValues as $idCampo => $raw) {
        $origem = $origemPorId[(string) $idCampo] ?? null;
        if (!$origem) continue;
        $destino = $destinoPorChave['registro:' . stridebr_lower((string) $origem['slug'])] ?? null;
        if (!$destino) continue;
        $value = $mapValue($origem, $destino, $raw);
        if ($value !== null && $value !== '') $mappedRecord[(string) $destino['idcampo']] = $value;
    }

    $mappedUnits = [];
    foreach ($unidades as $unit) {
        if (!is_array($unit)) continue;
        $mapped = ['rotulo' => trim((string) ($unit['rotulo'] ?? '')), 'observacoes' => trim((string) ($unit['observacoes'] ?? '')), 'values' => []];
        $values = is_array($unit['values'] ?? null) ? $unit['values'] : [];
        foreach ($values as $idCampo => $raw) {
            $origem = $origemPorId[(string) $idCampo] ?? null;
            if (!$origem) continue;
            $destino = $destinoPorChave['unidade:' . stridebr_lower((string) $origem['slug'])] ?? null;
            if (!$destino) continue;
            $value = $mapValue($origem, $destino, $raw);
            if ($value !== null && $value !== '') $mapped['values'][(string) $destino['idcampo']] = $value;
        }
        $mappedUnits[] = $mapped;
    }
    if ($mappedUnits === []) $mappedUnits[] = ['rotulo' => '', 'observacoes' => '', 'values' => []];
    return ['record_values' => $mappedRecord, 'unidades' => $mappedUnits];
}

function atividadeIntervaloParaSegundos(mixed $valor): ?float
{
    $valor = trim((string) $valor);
    if ($valor === '') return null;
    if (preg_match('/^(\d+):([0-5]\d):([0-5]\d)(?:\.(\d{1,3}))?$/', $valor, $m)) {
        $fraction = isset($m[4]) && $m[4] !== '' ? ((int) str_pad($m[4], 3, '0')) / 1000 : 0.0;
        return ((int) $m[1]) * 3600 + ((int) $m[2]) * 60 + (int) $m[3] + $fraction;
    }
    if (preg_match('/^(\d+):([0-5]\d)(?:\.(\d{1,3}))?$/', $valor, $m)) {
        $fraction = isset($m[3]) && $m[3] !== '' ? ((int) str_pad($m[3], 3, '0')) / 1000 : 0.0;
        return ((int) $m[1]) * 60 + (int) $m[2] + $fraction;
    }
    if (preg_match('/^(\d+(?:\.\d{1,3})?)\s+seconds?$/i', $valor, $m)) return round((float) $m[1], 3);
    return null;
}

function atividadeSegundosParaIntervalo(float|int $segundos): string
{
    $totalMs = max(0, (int) round((float) $segundos * 1000));
    $hours = intdiv($totalMs, 3600000);
    $remaining = $totalMs % 3600000;
    $minutes = intdiv($remaining, 60000);
    $remaining %= 60000;
    $seconds = intdiv($remaining, 1000);
    $milliseconds = $remaining % 1000;
    $base = sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    return $milliseconds === 0 ? $base : $base . '.' . str_pad((string) $milliseconds, 3, '0', STR_PAD_LEFT);
}

function atividadeDuracaoPayloadSegundos(array $recordValues, array $unidades, array $campos): ?float
{
    foreach ($campos as $campo) {
        if (stridebr_lower((string) ($campo['slug'] ?? '')) !== 'duracao') continue;
        $id = (string) ($campo['idcampo'] ?? '');
        if ($id === '') return null;
        $raw = (($campo['escopo'] ?? '') === 'registro') ? ($recordValues[$id] ?? null) : ($unidades[0]['values'][$id] ?? null);
        return atividadeIntervaloParaSegundos($raw);
    }
    return null;
}

function atividadeAplicarDuracaoCalculada(array &$recordValues, array &$unidades, array $campos, float|int $segundos): void
{
    $texto = atividadeSegundosParaIntervalo(max(0.0, (float) $segundos));
    foreach ($campos as $campo) {
        if (stridebr_lower((string) ($campo['slug'] ?? '')) !== 'duracao') continue;
        if (($campo['tipo_campo'] ?? '') !== 'intervalo') continue;
        if (($campo['escopo'] ?? '') === 'registro') {
            $recordValues[(string) $campo['idcampo']] = $texto;
        } else {
            if (!isset($unidades[0]) || !is_array($unidades[0])) $unidades[0] = ['rotulo' => '', 'values' => []];
            if (!isset($unidades[0]['values']) || !is_array($unidades[0]['values'])) $unidades[0]['values'] = [];
            $unidades[0]['values'][(string) $campo['idcampo']] = $texto;
        }
        return;
    }
}

function atividadeBuscarCamposModelos(PDO $pdo, array $idsModelo, bool $somenteAtivos = true): array
{
    $idsModelo = array_values(array_unique(array_filter(array_map('strval', $idsModelo), static fn(string $id): bool => $id !== '')));
    if ($idsModelo === []) {
        return [];
    }

    $modelPlaceholders = [];
    $params = [];
    foreach ($idsModelo as $index => $idModelo) {
        $key = ':modelo_' . $index;
        $modelPlaceholders[] = $key;
        $params[$key] = $idModelo;
    }

    $activeClause = $somenteAtivos ? ' AND cm.ativo = TRUE' : '';
    $stmt = $pdo->prepare(
        "SELECT
            cm.idmodelo,
            cm.idcampo,
            cm.slug,
            cm.rotulo,
            cm.tipo_campo,
            cm.escopo,
            cm.obrigatorio,
            cm.ordem,
            cm.ativo,
            cm.exibicao_padrao,
            cm.grupo_ui,
            u.simbolo AS unidade_simbolo
        FROM campos_modelo cm
        LEFT JOIN unidades u ON u.idunidade = cm.idunidade
        WHERE cm.idmodelo IN (" . implode(', ', $modelPlaceholders) . ")" . $activeClause . "
        ORDER BY cm.idmodelo, cm.ordem, cm.rotulo"
    );
    $stmt->execute($params);

    $byModel = array_fill_keys($idsModelo, []);
    $selectionFields = [];
    foreach ($stmt->fetchAll() as $campo) {
        $campo['obrigatorio'] = stridebr_db_bool($campo['obrigatorio']);
        $campo['ativo'] = stridebr_db_bool($campo['ativo']);
        $campo['exibicao_padrao'] = stridebr_db_bool($campo['exibicao_padrao']);
        $campo['grupo_ui'] = $campo['grupo_ui'] ?: 'detalhes';
        $campo['opcoes'] = [];
        if ($campo['tipo_campo'] === 'selecao') {
            $selectionFields[] = (string) $campo['idcampo'];
        }
        $modelId = (string) $campo['idmodelo'];
        unset($campo['idmodelo']);
        $byModel[$modelId][] = $campo;
    }

    if ($selectionFields !== []) {
        $selectionFields = array_values(array_unique($selectionFields));
        $fieldPlaceholders = [];
        $optionParams = [];
        foreach ($selectionFields as $index => $idCampo) {
            $key = ':campo_' . $index;
            $fieldPlaceholders[] = $key;
            $optionParams[$key] = $idCampo;
        }
        $optionActiveClause = $somenteAtivos ? ' AND ativo = TRUE' : '';
        $optionStmt = $pdo->prepare(
            'SELECT idcampo, idopcao, rotulo, valor, ativo FROM campos_modelo_opcoes WHERE idcampo IN (' . implode(', ', $fieldPlaceholders) . ')' . $optionActiveClause . ' ORDER BY idcampo, ordem, rotulo'
        );
        $optionStmt->execute($optionParams);
        $optionsByField = [];
        foreach ($optionStmt->fetchAll() as $option) {
            $optionsByField[(string) $option['idcampo']][] = [
                'idopcao' => $option['idopcao'],
                'rotulo' => $option['rotulo'],
                'valor' => $option['valor'],
                'ativo' => $option['ativo'],
            ];
        }
        foreach ($byModel as &$campos) {
            foreach ($campos as &$campo) {
                if ($campo['tipo_campo'] === 'selecao') {
                    $campo['opcoes'] = $optionsByField[(string) $campo['idcampo']] ?? [];
                }
            }
            unset($campo);
        }
        unset($campos);
    }

    return $byModel;
}

function atividadeBuscarCamposModelo(PDO $pdo, string $idModelo, bool $somenteAtivos = true): array
{
    $all = atividadeBuscarCamposModelos($pdo, [$idModelo], $somenteAtivos);
    return $all[$idModelo] ?? [];
}

function atividadeAgruparCampos(array $campos): array
{
    $resultado = ['registro' => [], 'unidade' => []];
    foreach ($campos as $campo) {
        $escopo = $campo['escopo'] === 'registro' ? 'registro' : 'unidade';
        $resultado[$escopo][] = $campo;
    }
    return $resultado;
}

function atividadeMontarModelosDetalhados(array $catalogo, array $camposPorModelo): array
{
    $detalhados = [];
    foreach ($catalogo as $modalidade) {
        foreach ((array) ($modalidade['modelos'] ?? []) as $modelo) {
            $idModelo = (string) ($modelo['idmodelo'] ?? '');
            if ($idModelo === '') {
                continue;
            }
            $campos = $camposPorModelo[$idModelo] ?? [];
            $familia = function_exists('sportCatalogFamilyKey')
                ? sportCatalogFamilyKey((string) ($modalidade['familia_hub'] ?? ''), (string) ($modalidade['categoria'] ?? ''), (string) ($modalidade['slug'] ?? ''))
                : (string) ($modalidade['familia_hub'] ?? '');
            $campos = atividadeFiltrarCamposPorModalidade($campos, $familia, (string) ($modalidade['slug'] ?? ''));
            $modelo['campos'] = $campos;
            $modelo['campos_agrupados'] = atividadeAgruparCampos($campos);
            $detalhados[$idModelo] = $modelo + [
                'idmodalidade' => $modalidade['idmodalidade'] ?? '',
                'modalidade_nome' => $modalidade['nome'] ?? '',
                'modalidade_slug' => $modalidade['slug'] ?? '',
                'modalidade_icone' => $modalidade['icone'] ?? '',
                'metrica_derivada' => $modalidade['metrica_derivada'] ?? 'nenhuma',
                'familia_hub' => $modalidade['familia_hub'] ?? '',
                'permite_rota' => !empty($modalidade['permite_rota']),
            ];
        }
    }
    return $detalhados;
}

function atividadeFormatarValorDecimalEdicao(mixed $valor, string $unidadeSimbolo = ''): string
{
    if ($valor === null || $valor === '') return '';
    if (!is_numeric($valor)) return (string) $valor;
    $casasMaximas = stridebr_lower(trim($unidadeSimbolo)) === 'km' ? 3 : 2;
    $formatado = number_format((float) $valor, $casasMaximas, '.', '');
    if (str_contains($formatado, '.')) {
        $formatado = rtrim(rtrim($formatado, '0'), '.');
    }
    return $formatado === '' || $formatado === '-0' ? '0' : $formatado;
}

function atividadeFiltrarCamposPorModalidade(array $campos, string $familia, string $modalidadeSlug = ''): array
{
    $familia = stridebr_lower(trim($familia));
    $modalidadeSlug = stridebr_lower(trim($modalidadeSlug));
    $cargaExtra = in_array($modalidadeSlug, ['rucking', 'treino-com-treno', 'treino-com-sandbag'], true);

    return array_values(array_filter($campos, static function (array $campo) use ($familia, $cargaExtra): bool {
        $slug = stridebr_lower(trim((string) ($campo['slug'] ?? '')));
        $idCampo = stridebr_lower(trim((string) ($campo['idcampo'] ?? '')));
        $modelo = stridebr_lower(trim((string) ($campo['idmodelo'] ?? '')));
        $campoGenerico = $modelo === 'md_geral_v2' || str_starts_with($idCampo, 'f_ger2_');
        if ($slug === 'rir') return $familia === 'strength';
        if ($slug === 'carga') return $familia === 'strength' || $cargaExtra;
        if ($campoGenerico && in_array($slug, ['series', 'repeticoes'], true)) return $familia === 'strength';
        return true;
    }));
}


function atividadeRotuloCampoEditor(array $campo): string
{
    $slug = stridebr_lower(trim((string) ($campo['slug'] ?? '')));
    if ($slug === 'cadencia') return stridebr_t('activity.average_cadence');
    if ($slug === 'potencia') return stridebr_t('activity.average_power');
    return stridebr_activity_field_label((string) ($campo['slug'] ?? ''), (string) ($campo['rotulo'] ?? stridebr_t('activity.field')));
}

function atividadeRenderizarCampo(array $campo, string $name, string $id, mixed $valor = null, bool $enforceRequired = true): string
{
    $fieldLabel = atividadeRotuloCampoEditor($campo);
    $label = stridebr_e($fieldLabel);
    $required = $enforceRequired && !empty($campo['obrigatorio']) ? ' required' : '';
    $unitSymbol = trim((string) ($campo['unidade_simbolo'] ?? ''));
    $slug = stridebr_lower((string) ($campo['slug'] ?? 'campo'));
    if ($slug === 'potencia') $unitSymbol = 'W';
    $contextualUnit = in_array($slug, ['cadencia', 'potencia'], true);
    $unit = ($unitSymbol !== '' || $contextualUnit) ? ' <span class="field-unit"' . ($contextualUnit ? ' data-contextual-field-unit' : '') . '>' . stridebr_e($unitSymbol) . '</span>' : '';
    if ($slug === 'distancia' && in_array(stridebr_lower($unitSymbol), ['m', 'km'], true)) {
        $unit = ' <select class="activity-distance-unit-select" data-distance-unit-select data-canonical-unit="' . stridebr_e(stridebr_lower($unitSymbol)) . '" aria-label="' . stridebr_e(stridebr_t('activity.distance_unit')) . '"><option value="m"' . (stridebr_lower($unitSymbol) === 'm' ? ' selected' : '') . '>m</option><option value="km"' . (stridebr_lower($unitSymbol) === 'km' ? ' selected' : '') . '>km</option></select><span class="field-unit" data-distance-unit-label hidden>' . stridebr_e(stridebr_lower($unitSymbol)) . '</span>';
    }
    $type = $campo['tipo_campo'] ?? 'texto';
    $slug = (string) ($campo['slug'] ?? 'campo');
    $group = (string) ($campo['grupo_ui'] ?? 'detalhes');
    $value = $valor === null ? '' : (string) $valor;
    $hasValue = $valor !== null && $valor !== '';
    $defaultVisible = !array_key_exists('exibicao_padrao', $campo) || stridebr_db_bool($campo['exibicao_padrao']);
    $visible = !empty($campo['obrigatorio']) || $defaultVisible || $hasValue;
    $classes = 'input-field dynamic-field' . ($type === 'texto_longo' ? ' is-long-field' : '') . ($visible ? '' : ' is-optional-hidden');
    $distanceAttrs = $slug === 'distancia' && in_array(stridebr_lower($unitSymbol), ['m', 'km'], true) ? ' data-distance-canonical-unit="' . stridebr_e(stridebr_lower($unitSymbol)) . '" data-distance-display-unit="' . stridebr_e(stridebr_lower($unitSymbol)) . '"' : '';
    $html = '<div class="' . $classes . '" data-dynamic-field data-field-slug="' . stridebr_e($slug) . '" data-field-label="' . stridebr_e($fieldLabel) . '" data-field-group="' . stridebr_e($group) . '" data-default-visible="' . ($defaultVisible ? '1' : '0') . '"' . $distanceAttrs . ($visible ? '' : ' hidden') . '>';
    $html .= '<label for="' . stridebr_e($id) . '"><span data-field-label-text>' . $label . '</span>' . $unit . '</label>';

    if ($type === 'texto_longo') {
        $html .= '<textarea id="' . stridebr_e($id) . '" name="' . stridebr_e($name) . '" rows="3"' . $required . '>' . stridebr_e($value) . '</textarea>';
    } elseif ($type === 'inteiro') {
        $html .= '<input type="number" step="1" inputmode="numeric" id="' . stridebr_e($id) . '" name="' . stridebr_e($name) . '" value="' . stridebr_e($value) . '"' . $required . '>';
    } elseif ($type === 'decimal') {
        $valorExibicao = atividadeFormatarValorDecimalEdicao($valor, $unitSymbol);
        $html .= '<input type="number" step="any" inputmode="decimal" id="' . stridebr_e($id) . '" name="' . stridebr_e($name) . '" value="' . stridebr_e($valorExibicao) . '"' . $required . '>';
    } elseif ($type === 'booleano') {
        $isTrue = $hasValue && stridebr_db_bool($valor);
        $html .= '<select id="' . stridebr_e($id) . '" name="' . stridebr_e($name) . '"' . $required . '>';
        $html .= '<option value="">' . stridebr_e(stridebr_t('common.not_informed')) . '</option>';
        $html .= '<option value="1"' . ($hasValue && $isTrue ? ' selected' : '') . '>' . stridebr_e(stridebr_t('common.yes')) . '</option>';
        $html .= '<option value="0"' . ($hasValue && !$isTrue ? ' selected' : '') . '>' . stridebr_e(stridebr_t('common.no')) . '</option>';
        $html .= '</select>';
    } elseif ($type === 'data') {
        $html .= '<input type="date" id="' . stridebr_e($id) . '" name="' . stridebr_e($name) . '" value="' . stridebr_e($value) . '"' . $required . '>';
    } elseif ($type === 'hora') {
        $html .= '<input type="time" id="' . stridebr_e($id) . '" name="' . stridebr_e($name) . '" value="' . stridebr_e($value) . '"' . $required . '>';
    } elseif ($type === 'intervalo') {
        $formatted = atividadeFormatarIntervalo($value);
        $hours = '';
        $minutes = '';
        $seconds = '';
        $milliseconds = '';
        if ($formatted !== '' && preg_match('/^(\d+):([0-5]\d):([0-5]\d)(?:\.(\d{3}))?$/', $formatted, $parts)) {
            $hours = (string) ((int) $parts[1]);
            $minutes = (string) ((int) $parts[2]);
            $seconds = (string) ((int) $parts[3]);
            $milliseconds = isset($parts[4]) ? $parts[4] : '';
        }
        $html .= '<div class="duration-segments" data-duration-field>';
        $html .= '<label><span>h</span><input type="text" inputmode="numeric" maxlength="2" value="' . stridebr_e($hours) . '" data-duration-hours aria-label="' . stridebr_e(stridebr_t('activity.hours')) . '" autocomplete="off"></label>';
        $html .= '<span aria-hidden="true">:</span>';
        $html .= '<label><span>min</span><input type="text" inputmode="numeric" maxlength="2" value="' . stridebr_e($minutes) . '" data-duration-minutes aria-label="' . stridebr_e(stridebr_t('activity.minutes')) . '" autocomplete="off"></label>';
        $html .= '<span aria-hidden="true">:</span>';
        $html .= '<label><span>s</span><input type="text" inputmode="numeric" maxlength="2" value="' . stridebr_e($seconds) . '" data-duration-seconds aria-label="' . stridebr_e(stridebr_t('activity.seconds')) . '" autocomplete="off"></label>';
        $html .= '<span class="duration-ms-separator" data-duration-ms-separator aria-hidden="true"' . ($milliseconds === '' ? ' hidden' : '') . '>.</span>';
        $html .= '<label class="duration-ms-field" data-duration-ms-wrap' . ($milliseconds === '' ? ' hidden' : '') . '><span>ms</span><input type="text" inputmode="numeric" maxlength="3" value="' . stridebr_e($milliseconds) . '" data-duration-milliseconds aria-label="' . stridebr_e(stridebr_t('activity.milliseconds')) . '" autocomplete="off"></label>';
        $html .= '<input type="hidden" id="' . stridebr_e($id) . '" name="' . stridebr_e($name) . '" value="' . stridebr_e($formatted) . '" data-duration-value>';
        $html .= '</div>';
    } elseif ($type === 'selecao') {
        $html .= '<select id="' . stridebr_e($id) . '" name="' . stridebr_e($name) . '"' . $required . '>';
        $html .= '<option value="">' . stridebr_e(stridebr_t('common.select')) . '</option>';
        foreach ($campo['opcoes'] ?? [] as $opcao) {
            $selected = (string) $opcao['idopcao'] === $value ? ' selected' : '';
            $html .= '<option value="' . stridebr_e($opcao['idopcao']) . '"' . $selected . '>' . stridebr_e($opcao['rotulo']) . '</option>';
        }
        $html .= '</select>';
    } else {
        $html .= '<input type="text" id="' . stridebr_e($id) . '" name="' . stridebr_e($name) . '" value="' . stridebr_e($value) . '"' . $required . '>';
    }

    if (empty($campo['obrigatorio']) && !$defaultVisible) {
        $html .= '<button type="button" class="field-remove-button" data-hide-optional-field aria-label="' . stridebr_e(stridebr_t('activity.remove_field')) . '">×</button>';
    }
    $html .= '</div>';
    return $html;
}

function atividadeNormalizarIntervalo(mixed $valor): ?string
{
    $valor = trim((string) $valor);
    if ($valor === '') return null;
    $seconds = atividadeIntervaloParaSegundos($valor);
    if ($seconds === null) throw new InvalidArgumentException(stridebr_t('activity.duration_format_error'));
    $normalized = number_format($seconds, 3, '.', '');
    $normalized = rtrim(rtrim($normalized, '0'), '.');
    if ($normalized === '') $normalized = '0';
    return $normalized . ' seconds';
}

function atividadeFormatarIntervalo(mixed $valor): string
{
    if ($valor === null || $valor === '') return '';
    $texto = trim((string) $valor);
    $seconds = atividadeIntervaloParaSegundos($texto);
    if ($seconds !== null) return atividadeSegundosParaIntervalo($seconds);
    if (preg_match('/^(\d+) days? (\d+):([0-5]\d):([0-5]\d)(?:\.(\d{1,6}))?/', $texto, $matches)) {
        $hours = (int) $matches[1] * 24 + (int) $matches[2];
        $fraction = isset($matches[5]) && $matches[5] !== '' ? ((int) str_pad(substr($matches[5], 0, 3), 3, '0')) / 1000 : 0.0;
        return atividadeSegundosParaIntervalo($hours * 3600 + (int) $matches[3] * 60 + (int) $matches[4] + $fraction);
    }
    if (preg_match('/^(\d+):(\d{2}):([0-5]\d)(?:\.(\d{1,6}))?$/', $texto, $matches)) {
        $fraction = isset($matches[4]) && $matches[4] !== '' ? ((int) str_pad(substr($matches[4], 0, 3), 3, '0')) / 1000 : 0.0;
        return atividadeSegundosParaIntervalo((int) $matches[1] * 3600 + (int) $matches[2] * 60 + (int) $matches[3] + $fraction);
    }
    return $texto;
}

function atividadePrepararValor(array $campo, mixed $raw, bool $permitirAusente = false): ?array
{
    $tipo = $campo['tipo_campo'];
    $fieldLabel = stridebr_activity_field_label((string) ($campo['slug'] ?? ''), (string) ($campo['rotulo'] ?? stridebr_t('activity.field')));
    $missing = $raw === null || (is_string($raw) && trim($raw) === '');
    if ($missing) {
        if ($permitirAusente) return null;
        if (!empty($campo['obrigatorio'])) {
            throw new InvalidArgumentException(stridebr_t('activity.field_required', ['field' => $fieldLabel]));
        }
        return null;
    }

    $bind = [
        'valor_texto' => null,
        'valor_inteiro' => null,
        'valor_decimal' => null,
        'valor_booleano' => null,
        'valor_data' => null,
        'valor_hora' => null,
        'valor_intervalo' => null,
        'idopcao' => null,
    ];

    if ($tipo === 'texto' || $tipo === 'texto_longo') {
        $bind['valor_texto'] = trim((string) $raw);
    } elseif ($tipo === 'inteiro') {
        if (filter_var($raw, FILTER_VALIDATE_INT) === false) {
            throw new InvalidArgumentException(stridebr_t('activity.field_integer', ['field' => $fieldLabel]));
        }
        $bind['valor_inteiro'] = (int) $raw;
    } elseif ($tipo === 'decimal') {
        $normalizado = str_replace(',', '.', trim((string) $raw));
        if (!is_numeric($normalizado)) {
            throw new InvalidArgumentException(stridebr_t('activity.field_number', ['field' => $fieldLabel]));
        }
        $bind['valor_decimal'] = $normalizado;
    } elseif ($tipo === 'booleano') {
        if (!in_array($raw, [0, 1, '0', '1', false, true], true)) {
            throw new InvalidArgumentException(stridebr_t('activity.field_invalid_value', ['field' => $fieldLabel]));
        }
        $bind['valor_booleano'] = in_array($raw, [1, '1', true], true);
    } elseif ($tipo === 'data') {
        $data = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $raw);
        if (!$data || $data->format('Y-m-d') !== $raw) {
            throw new InvalidArgumentException(stridebr_t('activity.field_invalid_date', ['field' => $fieldLabel]));
        }
        $bind['valor_data'] = $raw;
    } elseif ($tipo === 'hora') {
        $hora = DateTimeImmutable::createFromFormat('!H:i', (string) $raw);
        if (!$hora) {
            throw new InvalidArgumentException(stridebr_t('activity.field_invalid_time', ['field' => $fieldLabel]));
        }
        $bind['valor_hora'] = $hora->format('H:i:s');
    } elseif ($tipo === 'intervalo') {
        $bind['valor_intervalo'] = atividadeNormalizarIntervalo($raw);
    } elseif ($tipo === 'selecao') {
        $opcoes = array_column($campo['opcoes'] ?? [], 'idopcao');
        if (!in_array((string) $raw, $opcoes, true)) {
            throw new InvalidArgumentException(stridebr_t('activity.field_invalid_option', ['field' => $fieldLabel]));
        }
        $bind['idopcao'] = (string) $raw;
    }

    return $bind;
}

function atividadeInserirValor(PDO $pdo, string $idRegistro, ?string $idUnidade, array $campo, array $valor): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO valores_atividade (idvalor, idregistro, idunidade_atividade, idcampo, valor_texto, valor_inteiro, valor_decimal, valor_booleano, valor_data, valor_hora, valor_intervalo, idopcao)
         VALUES (:idvalor, :idregistro, :idunidade, :idcampo, :valor_texto, :valor_inteiro, :valor_decimal, :valor_booleano, :valor_data, :valor_hora, CAST(:valor_intervalo AS interval), :idopcao)'
    );
    $stmt->bindValue(':idvalor', atividadeGerarId());
    $stmt->bindValue(':idregistro', $idRegistro);
    $stmt->bindValue(':idunidade', $idUnidade, $idUnidade === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':idcampo', $campo['idcampo']);
    $stmt->bindValue(':valor_texto', $valor['valor_texto'], $valor['valor_texto'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':valor_inteiro', $valor['valor_inteiro'], $valor['valor_inteiro'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $stmt->bindValue(':valor_decimal', $valor['valor_decimal'], $valor['valor_decimal'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':valor_booleano', $valor['valor_booleano'], $valor['valor_booleano'] === null ? PDO::PARAM_NULL : PDO::PARAM_BOOL);
    $stmt->bindValue(':valor_data', $valor['valor_data'], $valor['valor_data'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':valor_hora', $valor['valor_hora'], $valor['valor_hora'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':valor_intervalo', $valor['valor_intervalo'], $valor['valor_intervalo'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':idopcao', $valor['idopcao'], $valor['idopcao'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->execute();
}

function atividadePreferenciasUsuario(PDO $pdo, string $idUsuario): array
{
    static $cache = [];
    if (isset($cache[$idUsuario])) return $cache[$idUsuario];
    $stmt = $pdo->prepare('SELECT preferenciasusuario FROM usuarios WHERE idusuario = :usuario LIMIT 1');
    $stmt->execute([':usuario' => $idUsuario]);
    $raw = $stmt->fetchColumn();
    return $cache[$idUsuario] = (is_array($raw) ? $raw : (json_decode((string) ($raw ?: '{}'), true) ?: []));
}

function atividadePadroesUsuario(PDO $pdo, string $idUsuario): array
{
    $prefs = atividadePreferenciasUsuario($pdo, $idUsuario);
    $defaults = is_array($prefs['activity_defaults'] ?? null) ? $prefs['activity_defaults'] : [];
    $visibility = (string) ($defaults['visibility'] ?? 'privado');
    if (!in_array($visibility, ['privado', 'amigos', 'publico'], true)) $visibility = 'privado';
    $hideStart = max(0, min(10000, (int) ($defaults['hide_route_start_m'] ?? 0)));
    $hideEnd = max(0, min(10000, (int) ($defaults['hide_route_end_m'] ?? $hideStart)));
    return ['visibility' => $visibility, 'hide_route_start_m' => $hideStart, 'hide_route_end_m' => $hideEnd];
}

function atividadeSalvarRegistro(PDO $pdo, string $idUsuario, array $payload, ?string $idRegistro = null): string
{
    if ($idRegistro !== null) {
        $ownership = $pdo->prepare('SELECT 1 FROM registros_atividade WHERE idregistro = :registro AND idusuario = :usuario AND excluido_em IS NULL');
        $ownership->execute([':registro' => $idRegistro, ':usuario' => $idUsuario]);
        if (!$ownership->fetchColumn()) throw new RuntimeException('Atividade não encontrada.');
    }
    $modelo = atividadeBuscarModelo($pdo, (string) ($payload['idmodelo'] ?? ''), $idUsuario, $idRegistro === null);
    if ($modelo === []) {
        throw new InvalidArgumentException('Modelo de atividade inválido.');
    }

    $campos = atividadeBuscarCamposModelo($pdo, $modelo['idmodelo'], $idRegistro === null);
    $familiaModelo = function_exists('sportCatalogFamilyKey')
        ? sportCatalogFamilyKey((string) ($modelo['modalidade_familia_hub'] ?? ''), (string) ($modelo['modalidade_categoria'] ?? ''), (string) ($modelo['modalidade_slug'] ?? ''))
        : (string) ($modelo['modalidade_familia_hub'] ?? '');
    $campos = atividadeFiltrarCamposPorModalidade($campos, $familiaModelo, (string) ($modelo['modalidade_slug'] ?? ''));
    $allowPartialFields = !empty($payload['permitir_campos_vazios']) || (($payload['origem'] ?? 'manual') === 'manual');

    $camposPorId = [];
    foreach ($campos as $campo) {
        $camposPorId[$campo['idcampo']] = $campo;
    }

    $unidades = $payload['unidades'] ?? [];
    if (!is_array($unidades) || $unidades === []) $unidades = [['values' => []]];
    $segmentsEnabled = !empty($payload['usa_trechos']);
    if (!$segmentsEnabled) $unidades = [reset($unidades) ?: ['values' => []]];

    // O registro manual precisa continuar salvando mesmo durante rollout de migrations.
    // As colunas novas enriquecem trechos, mas não podem virar dependência para criar
    // uma atividade básica/vazia.
    $hasRecordSegments = stridebr_db_column_exists($pdo, 'registros_atividade', 'usa_trechos');
    $hasUnitSport = stridebr_db_column_exists($pdo, 'unidades_atividade', 'idmodalidade');
    $hasUnitDistance = stridebr_db_column_exists($pdo, 'unidades_atividade', 'distancia_metros');
    $hasUnitDuration = stridebr_db_column_exists($pdo, 'unidades_atividade', 'duracao_segundos');
    $hasUnitElevation = stridebr_db_column_exists($pdo, 'unidades_atividade', 'elevacao_m');
    $hasUnitRoutes = stridebr_db_table_exists($pdo, 'rotas_unidades_atividade');
    $hasGeneralRoutes = stridebr_db_table_exists($pdo, 'rotas_atividade');
    $hasEquipmentLinks = stridebr_db_table_exists($pdo, 'registros_atividade_equipamentos');
    $hasCompetitionLink = stridebr_db_column_exists($pdo, 'registros_atividade', 'idcompeticao');
    foreach ($unidades as $index => $unitPayload) {
        if (!is_array($unitPayload)) $unitPayload = ['values' => []];
        $unitPayload['idmodalidade'] = trim((string) ($unitPayload['idmodalidade'] ?? '')) ?: (string) $modelo['idmodalidade'];
        $unidades[$index] = $unitPayload;
    }

    $unitSportIds = array_values(array_unique(array_map(static fn(array $unit): string => (string) ($unit['idmodalidade'] ?? ''), $unidades)));
    $unitSports = [];
    if ($unitSportIds !== []) {
        $placeholders = [];
        $params = [':usuario_modalidade_unidade' => $idUsuario];
        foreach ($unitSportIds as $index => $idModalidadeUnidade) {
            $key = ':modalidade_unidade_' . $index;
            $placeholders[] = $key;
            $params[$key] = $idModalidadeUnidade;
        }
        $unitSportStmt = $pdo->prepare(
            'SELECT idmodalidade, nome, slug, metrica_derivada, permite_rota
             FROM modalidades
             WHERE ativo = TRUE
               AND (idusuario IS NULL OR idusuario = :usuario_modalidade_unidade)
               AND idmodalidade IN (' . implode(', ', $placeholders) . ')'
        );
        $unitSportStmt->execute($params);
        foreach ($unitSportStmt->fetchAll() as $sportRow) {
            $sportRow['permite_rota'] = stridebr_db_bool($sportRow['permite_rota']);
            $unitSports[(string) $sportRow['idmodalidade']] = $sportRow;
        }
    }
    foreach ($unidades as $unitPayload) {
        if (!isset($unitSports[(string) ($unitPayload['idmodalidade'] ?? '')])) throw new InvalidArgumentException('Uma das modalidades dos trechos não está disponível.');
    }

    $routeProvided = array_key_exists('rota_coordenadas', $payload);
    if ($segmentsEnabled) {
        $generalRouteRaw = $payload['rota_coordenadas'] ?? null;
        if ($generalRouteRaw !== null && $generalRouteRaw !== '' && trim((string) ($unidades[0]['rota_coordenadas'] ?? '')) === '') {
            $unidades[0]['rota_coordenadas'] = $generalRouteRaw;
            $generalMode = trim((string) ($payload['rota_modo'] ?? 'manual'));
            $unidades[0]['rota_modo'] = in_array($generalMode, ['gps', 'importada'], true) ? $generalMode : 'manual';
            if (is_array($payload['rota_metricas'] ?? null)) $unidades[0]['rota_metricas'] = $payload['rota_metricas'];
        }
        $routeProvided = true;
        $payload['rota_coordenadas'] = '';
    }
    $payload['unidades'] = $unidades;

    $route = null;
    $routeDistance = null;
    $routeElevation = null;
    $routeMode = (string) ($payload['rota_modo'] ?? 'desenho_livre');
    if (!in_array($routeMode, ['desenho_livre', 'seguir_ruas', 'gps', 'importada'], true)) $routeMode = 'desenho_livre';
    if (!$segmentsEnabled && $routeProvided) {
        $route = atividadeValidarRotaGeoJson($payload['rota_coordenadas'] ?? null);
    }
    if ($route !== null) {
        if (!$modelo['permite_rota']) throw new InvalidArgumentException('Esta modalidade não permite rota.');
        $providedRouteMetrics = is_array($payload['rota_metricas'] ?? null) ? $payload['rota_metricas'] : [];
        $routeDistance = is_numeric($providedRouteMetrics['distancia_metros'] ?? null)
            ? max(0.0, (float) $providedRouteMetrics['distancia_metros'])
            : atividadeDistanciaRota($route['coordinates']);
        if ($routeDistance <= 0) throw new InvalidArgumentException('A rota precisa ter distância maior que zero.');
        if ($providedRouteMetrics !== []) {
            $routeElevation = [
                'ganho_elevacao_m' => is_numeric($providedRouteMetrics['ganho_elevacao_m'] ?? null) ? max(0.0, (float) $providedRouteMetrics['ganho_elevacao_m']) : null,
                'perda_elevacao_m' => is_numeric($providedRouteMetrics['perda_elevacao_m'] ?? null) ? max(0.0, (float) $providedRouteMetrics['perda_elevacao_m']) : null,
                'elevacao_min_m' => is_numeric($providedRouteMetrics['elevacao_min_m'] ?? null) ? (float) $providedRouteMetrics['elevacao_min_m'] : null,
                'elevacao_max_m' => is_numeric($providedRouteMetrics['elevacao_max_m'] ?? null) ? (float) $providedRouteMetrics['elevacao_max_m'] : null,
                'perfil_elevacao' => is_array($providedRouteMetrics['perfil_elevacao'] ?? null) ? $providedRouteMetrics['perfil_elevacao'] : null,
                'fonte_elevacao' => trim((string) ($providedRouteMetrics['fonte_elevacao'] ?? '')) ?: null,
            ];
        } else {
            $routeElevation = atividadeConsultarElevacao($route);
        }
        atividadeAplicarMetricaRotaNoPayload($payload, $campos, ['distancia'], $routeDistance, true);
        if (is_numeric($routeElevation['ganho_elevacao_m'] ?? null)) atividadeAplicarMetricaRotaNoPayload($payload, $campos, ['elevacao', 'desnivel'], (float) $routeElevation['ganho_elevacao_m'], true);
        $unidades = $payload['unidades'];
    }

    $inicioRaw = trim((string) ($payload['data_inicio'] ?? ''));
    $inicio = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $inicioRaw);
    if (!$inicio || $inicio->format('Y-m-d H:i') !== $inicioRaw) {
        throw new InvalidArgumentException('Data ou hora da atividade inválida.');
    }

    $status = (string) ($payload['status'] ?? 'concluido');
    if (!in_array($status, ['rascunho', 'ativo', 'concluido', 'cancelado'], true)) {
        throw new InvalidArgumentException('Status da atividade inválido.');
    }

    $fimSql = null;
    $fimRaw = trim((string) ($payload['data_fim'] ?? ''));
    if ($fimRaw !== '') {
        $fim = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $fimRaw);
        if (!$fim || $fim->format('Y-m-d H:i') !== $fimRaw || $fim < $inicio) {
            throw new InvalidArgumentException('Horário final da atividade inválido.');
        }
        $fimSql = $fim->format('Y-m-d H:i:s');
    }

    $origem = (string) ($payload['origem'] ?? 'manual');
    if (!in_array($origem, ['manual', 'gps', 'importacao', 'api'], true)) {
        throw new InvalidArgumentException('Origem da atividade inválida.');
    }
    $idCronograma = trim((string) ($payload['idcronograma'] ?? '')) ?: null;
    $idTreinoCronograma = trim((string) ($payload['idtreino_cronograma'] ?? '')) ?: null;
    $dataOcorrenciaOrigem = trim((string) ($payload['data_ocorrencia_origem'] ?? '')) ?: null;
    $dataOcorrenciaPlanejada = trim((string) ($payload['data_ocorrencia_planejada'] ?? '')) ?: null;
    foreach ([$dataOcorrenciaOrigem, $dataOcorrenciaPlanejada] as $occurrenceDate) {
        if ($occurrenceDate === null) continue;
        $parsedOccurrenceDate = DateTimeImmutable::createFromFormat('!Y-m-d', $occurrenceDate);
        if (!$parsedOccurrenceDate || $parsedOccurrenceDate->format('Y-m-d') !== $occurrenceDate) {
            throw new InvalidArgumentException('Data da ocorrência planejada inválida.');
        }
    }
    $horaOcorrenciaPlanejada = trim((string) ($payload['hora_ocorrencia_planejada'] ?? '')) ?: null;
    if ($horaOcorrenciaPlanejada !== null) {
        if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $horaOcorrenciaPlanejada)) {
            throw new InvalidArgumentException('Hora da ocorrência planejada inválida.');
        }
        $horaOcorrenciaPlanejada .= ':00';
    }

    $userDefaults = atividadePadroesUsuario($pdo, $idUsuario);
    $visibilidade = trim((string) ($payload['visibilidade'] ?? ''));
    if ($visibilidade === '') $visibilidade = $userDefaults['visibility'];
    if (!in_array($visibilidade, ['privado', 'amigos', 'publico'], true)) {
        throw new InvalidArgumentException('Visibilidade da atividade inválida.');
    }
    $ocultarInicioM = array_key_exists('ocultar_inicio_m', $payload) ? (int) $payload['ocultar_inicio_m'] : (int) $userDefaults['hide_route_start_m'];
    $ocultarFimM = array_key_exists('ocultar_fim_m', $payload) ? (int) $payload['ocultar_fim_m'] : (int) $userDefaults['hide_route_end_m'];
    if ($ocultarInicioM < 0 || $ocultarInicioM > 10000 || $ocultarFimM < 0 || $ocultarFimM > 10000) {
        throw new InvalidArgumentException('A distância de privacidade da rota deve ficar entre 0 e 10 km.');
    }

    $titulo = trim((string) ($payload['titulo'] ?? ''));
    if ($titulo === '') {
        $titulo = $modelo['modalidade_nome'];
    }
    if (stridebr_length($titulo) > 255) {
        throw new InvalidArgumentException('O título da atividade é muito longo.');
    }

    $esforcoRaw = trim((string) ($payload['esforco_percebido'] ?? ''));
    $esforco = null;
    if ($esforcoRaw !== '') {
        if (filter_var($esforcoRaw, FILTER_VALIDATE_INT) === false || (int) $esforcoRaw < 1 || (int) $esforcoRaw > 10) {
            throw new InvalidArgumentException('O esforço percebido deve ficar entre 1 e 10.');
        }
        $esforco = (int) $esforcoRaw;
    }

    $competitionId = trim((string) ($payload['idcompeticao'] ?? ''));
    if ($hasCompetitionLink && $competitionId !== '') competitionValidateOwned($pdo, $idUsuario, $competitionId);
    if (!$hasCompetitionLink) $competitionId = '';

    $equipamentos = array_values(array_unique(array_filter(array_map(
        static fn(mixed $id): string => trim((string) $id),
        is_array($payload['equipamentos'] ?? null) ? $payload['equipamentos'] : []
    ))));

    $isUpdate = $idRegistro !== null;
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) {
        $pdo->beginTransaction();
    }
    try {
        if ($idRegistro !== null) {
            $owner = $pdo->prepare('SELECT idregistro, idmodelo FROM registros_atividade WHERE idregistro = :id AND idusuario = :usuario AND excluido_em IS NULL FOR UPDATE');
            $owner->execute([':id' => $idRegistro, ':usuario' => $idUsuario]);
            $existing = $owner->fetch();
            if (!$existing) {
                throw new RuntimeException('Atividade não encontrada.');
            }
            $updateSql = 'UPDATE registros_atividade SET idmodalidade = :modalidade, idmodelo = :modelo, titulo = :titulo, observacoes = :observacoes, data_inicio = :inicio, data_fim = :fim, status = :status, visibilidade = :visibilidade, esforco_percebido = :esforco, ocultar_inicio_m = :ocultar_inicio, ocultar_fim_m = :ocultar_fim';
            if ($hasRecordSegments) $updateSql .= ', usa_trechos = :usa_trechos';
            if ($hasCompetitionLink) $updateSql .= ', idcompeticao = :competicao';
            $updateSql .= ', data_atualizacao = NOW() WHERE idregistro = :id AND idusuario = :usuario AND excluido_em IS NULL';
            $stmt = $pdo->prepare($updateSql);
            $updateParams = [
                ':modalidade' => $modelo['idmodalidade'],
                ':modelo' => $modelo['idmodelo'],
                ':titulo' => $titulo,
                ':observacoes' => trim((string) ($payload['observacoes'] ?? '')) ?: null,
                ':inicio' => $inicio->format('Y-m-d H:i:s'),
                ':fim' => $fimSql,
                ':status' => $status,
                ':visibilidade' => $visibilidade,
                ':esforco' => $esforco,
                ':ocultar_inicio' => $ocultarInicioM,
                ':ocultar_fim' => $ocultarFimM,
                ':id' => $idRegistro,
                ':usuario' => $idUsuario,
            ];
            if ($hasRecordSegments) $updateParams[':usa_trechos'] = $segmentsEnabled ? 1 : 0;
            if ($hasCompetitionLink) $updateParams[':competicao'] = $competitionId !== '' ? $competitionId : null;
            $stmt->execute($updateParams);
            $pdo->prepare('DELETE FROM valores_atividade WHERE idregistro = :id')->execute([':id' => $idRegistro]);
            $pdo->prepare('DELETE FROM unidades_atividade WHERE idregistro = :id')->execute([':id' => $idRegistro]);
        } else {
            $idRegistro = atividadeGerarId();
            $recordColumns = ['idregistro', 'idusuario', 'idmodalidade', 'idmodelo', 'idcronograma', 'idtreino_cronograma', 'data_ocorrencia_origem', 'data_ocorrencia_planejada', 'hora_ocorrencia_planejada', 'titulo', 'observacoes', 'data_inicio', 'data_fim', 'status', 'visibilidade', 'origem', 'esforco_percebido', 'ocultar_inicio_m', 'ocultar_fim_m'];
            $recordValuesSql = [':id', ':usuario', ':modalidade', ':modelo', ':cronograma', ':treino', ':ocorrencia_origem', ':ocorrencia_planejada', ':hora_ocorrencia_planejada', ':titulo', ':observacoes', ':inicio', ':fim', ':status', ':visibilidade', ':origem', ':esforco', ':ocultar_inicio', ':ocultar_fim'];
            if ($hasCompetitionLink) {
                $recordColumns[] = 'idcompeticao';
                $recordValuesSql[] = ':competicao';
            }
            if ($hasRecordSegments) {
                $recordColumns[] = 'usa_trechos';
                $recordValuesSql[] = ':usa_trechos';
            }
            $stmt = $pdo->prepare('INSERT INTO registros_atividade (' . implode(', ', $recordColumns) . ') VALUES (' . implode(', ', $recordValuesSql) . ')');
            $insertParams = [
                ':id' => $idRegistro,
                ':usuario' => $idUsuario,
                ':modalidade' => $modelo['idmodalidade'],
                ':modelo' => $modelo['idmodelo'],
                ':cronograma' => $idCronograma,
                ':treino' => $idTreinoCronograma,
                ':ocorrencia_origem' => $dataOcorrenciaOrigem,
                ':ocorrencia_planejada' => $dataOcorrenciaPlanejada,
                ':hora_ocorrencia_planejada' => $horaOcorrenciaPlanejada,
                ':titulo' => $titulo,
                ':observacoes' => trim((string) ($payload['observacoes'] ?? '')) ?: null,
                ':inicio' => $inicio->format('Y-m-d H:i:s'),
                ':fim' => $fimSql,
                ':status' => $status,
                ':visibilidade' => $visibilidade,
                ':origem' => $origem,
                ':esforco' => $esforco,
                ':ocultar_inicio' => $ocultarInicioM,
                ':ocultar_fim' => $ocultarFimM,
            ];
            if ($hasCompetitionLink) $insertParams[':competicao'] = $competitionId !== '' ? $competitionId : null;
            if ($hasRecordSegments) $insertParams[':usa_trechos'] = $segmentsEnabled ? 1 : 0;
            $stmt->execute($insertParams);
        }

        $recordValues = is_array($payload['record_values'] ?? null) ? $payload['record_values'] : [];
        foreach ($campos as $campo) {
            if ($campo['escopo'] !== 'registro') {
                continue;
            }
            $prepared = atividadePrepararValor($campo, $recordValues[$campo['idcampo']] ?? null, $allowPartialFields);
            if ($prepared !== null) {
                atividadeInserirValor($pdo, $idRegistro, null, $campo, $prepared);
            }
        }

        $ordem = 1;
        foreach ($unidades as $unidadePayload) {
            if (!is_array($unidadePayload)) continue;
            $rotuloUnidade = trim((string) ($unidadePayload['rotulo'] ?? ''));
            if (stridebr_length($rotuloUnidade) > 120) throw new InvalidArgumentException('O rótulo da unidade de atividade é muito longo.');

            $unitSportId = trim((string) ($unidadePayload['idmodalidade'] ?? '')) ?: (string) $modelo['idmodalidade'];
            $unitSport = $unitSports[$unitSportId] ?? null;
            if (!$unitSport) throw new InvalidArgumentException('Uma das modalidades dos trechos não está disponível.');

            $unitRoute = null;
            $unitRouteDistance = null;
            $unitRouteMetrics = [];
            $unitRouteRaw = $unidadePayload['rota_coordenadas'] ?? null;
            if ($unitRouteRaw !== null && $unitRouteRaw !== '') {
                if (empty($unitSport['permite_rota'])) throw new InvalidArgumentException('A modalidade de um dos trechos não permite rota.');
                $unitRoute = atividadeValidarRotaGeoJson($unitRouteRaw, false);
                $unitRouteMetrics = is_array($unidadePayload['rota_metricas'] ?? null) ? $unidadePayload['rota_metricas'] : [];
                $unitRouteDistance = is_numeric($unitRouteMetrics['distancia_metros'] ?? null)
                    ? max(0.0, (float) $unitRouteMetrics['distancia_metros'])
                    : atividadeDistanciaRota($unitRoute['coordinates']);
                if ($unitRouteDistance <= 0) throw new InvalidArgumentException('A rota do trecho precisa ter distância maior que zero.');
            }

            $values = is_array($unidadePayload['values'] ?? null) ? $unidadePayload['values'] : [];
            $segmentMetrics = is_array($unidadePayload['metricas'] ?? null) ? $unidadePayload['metricas'] : [];
            $segmentDistanceM = null;
            $segmentDurationS = null;
            $segmentElevationM = null;
            if ($segmentsEnabled) {
                $distanceRaw = $segmentMetrics['distancia'] ?? null;
                if (is_numeric($distanceRaw) && (float) $distanceRaw >= 0) {
                    $distanceUnit = (string) ($segmentMetrics['distancia_unidade'] ?? 'km');
                    $segmentDistanceM = $distanceUnit === 'm' ? (float) $distanceRaw : (float) $distanceRaw * 1000;
                }
                $durationRaw = trim((string) ($segmentMetrics['duracao'] ?? ''));
                if ($durationRaw !== '') $segmentDurationS = atividadeIntervaloParaSegundos($durationRaw);
                $elevationRaw = $segmentMetrics['elevacao'] ?? null;
                if (is_numeric($elevationRaw) && (float) $elevationRaw >= 0) $segmentElevationM = (float) $elevationRaw;
            }

            // Mesmo sem o modo de trechos, mantenha as métricas canônicas da unidade
            // sincronizadas com os campos normais. Isso permite registros parciais
            // (por exemplo, apenas distância + duração) sem depender de campos extras.
            foreach ($campos as $campo) {
                if (($campo['escopo'] ?? '') !== 'unidade') continue;
                $slug = stridebr_lower((string) ($campo['slug'] ?? ''));
                $fieldValue = $values[(string) ($campo['idcampo'] ?? '')] ?? null;
                if ($segmentDistanceM === null && $slug === 'distancia' && is_numeric($fieldValue)) {
                    $segmentDistanceM = (($campo['unidade_simbolo'] ?? 'km') === 'm') ? (float) $fieldValue : (float) $fieldValue * 1000;
                } elseif ($segmentDurationS === null && $slug === 'duracao' && trim((string) $fieldValue) !== '') {
                    $segmentDurationS = atividadeIntervaloParaSegundos(atividadeFormatarIntervalo($fieldValue));
                } elseif ($segmentElevationM === null && in_array($slug, ['elevacao', 'desnivel'], true) && is_numeric($fieldValue)) {
                    $segmentElevationM = (($campo['unidade_simbolo'] ?? 'm') === 'km') ? (float) $fieldValue * 1000 : (float) $fieldValue;
                }
            }
            if ($segmentDistanceM === null && $unitRouteDistance !== null) $segmentDistanceM = $unitRouteDistance;
            if ($segmentElevationM === null && is_numeric($unitRouteMetrics['ganho_elevacao_m'] ?? null)) {
                $segmentElevationM = max(0.0, (float) $unitRouteMetrics['ganho_elevacao_m']);
            }
            if ($unitRouteDistance !== null && !$segmentsEnabled) {
                foreach ($campos as $campo) {
                    if (($campo['escopo'] ?? '') !== 'unidade' || stridebr_lower((string) ($campo['slug'] ?? '')) !== 'distancia') continue;
                    $idCampo = (string) ($campo['idcampo'] ?? '');
                    $current = $values[$idCampo] ?? null;
                    if ($idCampo !== '' && (!is_scalar($current) || trim((string) $current) === '')) {
                        $value = (($campo['unidade_simbolo'] ?? 'm') === 'km') ? $unitRouteDistance / 1000 : $unitRouteDistance;
                        $values[$idCampo] = round($value, 3);
                    }
                    break;
                }
            }

            $idUnidade = atividadeGerarId();
            $unitColumns = ['idunidade_atividade', 'idregistro', 'ordem', 'tipo_unidade', 'rotulo', 'observacoes'];
            $unitValuesSql = [':id', ':registro', ':ordem', ':tipo', ':rotulo', ':observacoes'];
            $unitParams = [
                ':id' => $idUnidade,
                ':registro' => $idRegistro,
                ':ordem' => $ordem,
                ':tipo' => $modelo['tipo_unidade_padrao'],
                ':rotulo' => $rotuloUnidade !== '' ? $rotuloUnidade : null,
                ':observacoes' => trim((string) ($unidadePayload['observacoes'] ?? '')) ?: null,
            ];
            if ($hasUnitSport) {
                $unitColumns[] = 'idmodalidade';
                $unitValuesSql[] = ':modalidade';
                $unitParams[':modalidade'] = $unitSportId;
            }
            if ($hasUnitDistance) {
                $unitColumns[] = 'distancia_metros';
                $unitValuesSql[] = ':distancia';
                $unitParams[':distancia'] = $segmentDistanceM !== null ? round($segmentDistanceM, 3) : null;
            }
            if ($hasUnitDuration) {
                $unitColumns[] = 'duracao_segundos';
                $unitValuesSql[] = ':duracao';
                $unitParams[':duracao'] = $segmentDurationS !== null ? round($segmentDurationS, 3) : null;
            }
            if ($hasUnitElevation) {
                $unitColumns[] = 'elevacao_m';
                $unitValuesSql[] = ':elevacao';
                $unitParams[':elevacao'] = $segmentElevationM !== null ? round($segmentElevationM, 2) : null;
            }
            $stmtUnit = $pdo->prepare('INSERT INTO unidades_atividade (' . implode(', ', $unitColumns) . ') VALUES (' . implode(', ', $unitValuesSql) . ')');
            $stmtUnit->execute($unitParams);

            foreach ($campos as $campo) {
                if ($campo['escopo'] !== 'unidade') continue;
                $slug = stridebr_lower((string) ($campo['slug'] ?? ''));
                if ($segmentsEnabled && in_array($slug, ['distancia', 'duracao', 'elevacao', 'desnivel'], true)) continue;
                $prepared = atividadePrepararValor($campo, $values[$campo['idcampo']] ?? null, $allowPartialFields);
                if ($prepared !== null) atividadeInserirValor($pdo, $idRegistro, $idUnidade, $campo, $prepared);
            }

            if ($hasUnitRoutes && $unitRoute !== null && $unitRouteDistance !== null) {
                $unitRouteMode = trim((string) ($unidadePayload['rota_modo'] ?? 'manual'));
                if (!in_array($unitRouteMode, ['manual', 'gps', 'importada'], true)) $unitRouteMode = 'manual';
                $stmtUnitRoute = $pdo->prepare(
                    'INSERT INTO rotas_unidades_atividade
                     (idrota_unidade, idunidade_atividade, idregistro, modo, coordenadas, distancia_metros, ganho_elevacao_m, perda_elevacao_m, elevacao_min_m, elevacao_max_m, perfil_elevacao, fonte_elevacao, data_atualizacao)
                     VALUES (:id, :unidade, :registro, :modo, CAST(:coordenadas AS jsonb), :distancia, :ganho, :perda, :minima, :maxima, CAST(:perfil AS jsonb), :fonte, NOW())'
                );
                $stmtUnitRoute->execute([
                    ':id' => atividadeGerarId(),
                    ':unidade' => $idUnidade,
                    ':registro' => $idRegistro,
                    ':modo' => $unitRouteMode,
                    ':coordenadas' => json_encode($unitRoute, JSON_UNESCAPED_SLASHES),
                    ':distancia' => round($unitRouteDistance, 3),
                    ':ganho' => is_numeric($unitRouteMetrics['ganho_elevacao_m'] ?? null) ? max(0.0, (float) $unitRouteMetrics['ganho_elevacao_m']) : null,
                    ':perda' => is_numeric($unitRouteMetrics['perda_elevacao_m'] ?? null) ? max(0.0, (float) $unitRouteMetrics['perda_elevacao_m']) : null,
                    ':minima' => is_numeric($unitRouteMetrics['elevacao_min_m'] ?? null) ? (float) $unitRouteMetrics['elevacao_min_m'] : null,
                    ':maxima' => is_numeric($unitRouteMetrics['elevacao_max_m'] ?? null) ? (float) $unitRouteMetrics['elevacao_max_m'] : null,
                    ':perfil' => is_array($unitRouteMetrics['perfil_elevacao'] ?? null) ? json_encode($unitRouteMetrics['perfil_elevacao'], JSON_UNESCAPED_SLASHES) : null,
                    ':fonte' => trim((string) ($unitRouteMetrics['fonte_elevacao'] ?? '')) ?: null,
                ]);
            }
            $ordem++;
        }

        if ($hasEquipmentLinks) {
            if ($isUpdate || $equipamentos !== []) {
                $pdo->prepare('DELETE FROM registros_atividade_equipamentos WHERE idregistro = :registro')->execute([':registro' => $idRegistro]);
            }
            if ($equipamentos !== []) {
                $equipmentActiveClause = $isUpdate ? '' : ' AND ativo = TRUE';
                $checkEquipment = $pdo->prepare('SELECT 1 FROM equipamentos_usuario WHERE idequipamento = :equipamento AND idusuario = :usuario' . $equipmentActiveClause);
                $insertEquipment = $pdo->prepare('INSERT INTO registros_atividade_equipamentos (idregistro, idequipamento) VALUES (:registro, :equipamento) ON CONFLICT DO NOTHING');
                foreach ($equipamentos as $idEquipamento) {
                    $checkEquipment->execute([':equipamento' => $idEquipamento, ':usuario' => $idUsuario]);
                    if (!$checkEquipment->fetchColumn()) {
                        throw new InvalidArgumentException('Um dos equipamentos selecionados não está disponível.');
                    }
                    $insertEquipment->execute([':registro' => $idRegistro, ':equipamento' => $idEquipamento]);
                }
            }
        }

        if ($hasGeneralRoutes && $routeProvided && ($isUpdate || $route !== null)) {
            if ($route === null) {
                $pdo->prepare('DELETE FROM rotas_atividade WHERE idregistro = :registro')->execute([':registro' => $idRegistro]);
            } else {
                $routeStmt = $pdo->prepare(
                    'INSERT INTO rotas_atividade
                     (idrota, idregistro, modo, coordenadas, distancia_metros, ganho_elevacao_m, perda_elevacao_m, elevacao_min_m, elevacao_max_m, perfil_elevacao, fonte_elevacao, data_atualizacao)
                     VALUES (:id, :registro, :modo, CAST(:coordenadas AS jsonb), :distancia, :ganho, :perda, :minima, :maxima, CAST(:perfil AS jsonb), :fonte, NOW())
                     ON CONFLICT (idregistro) DO UPDATE SET modo = EXCLUDED.modo, coordenadas = EXCLUDED.coordenadas,
                       distancia_metros = EXCLUDED.distancia_metros, ganho_elevacao_m = EXCLUDED.ganho_elevacao_m,
                       perda_elevacao_m = EXCLUDED.perda_elevacao_m, elevacao_min_m = EXCLUDED.elevacao_min_m,
                       elevacao_max_m = EXCLUDED.elevacao_max_m, perfil_elevacao = EXCLUDED.perfil_elevacao,
                       fonte_elevacao = EXCLUDED.fonte_elevacao, data_atualizacao = NOW()'
                );
                $routeStmt->execute([
                    ':id' => atividadeGerarId(), ':registro' => $idRegistro, ':modo' => $routeMode,
                    ':coordenadas' => json_encode($route, JSON_UNESCAPED_SLASHES), ':distancia' => round((float) $routeDistance, 3),
                    ':ganho' => $routeElevation['ganho_elevacao_m'] ?? null, ':perda' => $routeElevation['perda_elevacao_m'] ?? null,
                    ':minima' => $routeElevation['elevacao_min_m'] ?? null, ':maxima' => $routeElevation['elevacao_max_m'] ?? null,
                    ':perfil' => isset($routeElevation['perfil_elevacao']) ? json_encode($routeElevation['perfil_elevacao'], JSON_UNESCAPED_SLASHES) : null,
                    ':fonte' => $routeElevation['fonte_elevacao'] ?? null,
                ]);
            }
        }

        $externalCalories = is_numeric($payload['calorias_externas'] ?? null) ? max(0.0, (float) $payload['calorias_externas']) : null;
        $externalCaloriesSource = trim((string) ($payload['fonte_calorias_externa'] ?? '')) ?: null;
        if (stridebr_db_column_exists($pdo, 'registros_atividade', 'calorias_ativas_estimadas')) {
            try {
                require_once __DIR__ . '/activity_energy.php';
                if ($pdo->inTransaction()) $pdo->exec('SAVEPOINT stridebr_energy_after_save');
                atividadeEnergiaAtualizarRegistro($pdo, (string) $idRegistro, $idUsuario, $externalCalories, $externalCaloriesSource);
                if ($pdo->inTransaction()) $pdo->exec('RELEASE SAVEPOINT stridebr_energy_after_save');
            } catch (Throwable $energyError) {
                if ($pdo->inTransaction()) {
                    try { $pdo->exec('ROLLBACK TO SAVEPOINT stridebr_energy_after_save'); } catch (Throwable) {}
                }
                error_log('StrideBR activity energy after save: ' . $energyError->getMessage());
            }
        }

        if ($ownsTransaction) {
            $pdo->commit();
        }

        // Preferência/último uso é metadado auxiliar. Se essa atualização falhar,
        // a atividade já salva não deve ser perdida nem reportada como falha.
        try {
            if (stridebr_db_table_exists($pdo, 'modalidades_usuario')) {
                $hasLastUse = stridebr_db_column_exists($pdo, 'modalidades_usuario', 'ultimo_uso');
                if ($hasLastUse) {
                    $preference = $pdo->prepare(
                        'INSERT INTO modalidades_usuario (idusuario, idmodalidade, idmodelo_ativo, ativo, favorita, data_ativacao, data_desativacao, ultimo_uso)
                         VALUES (:usuario, :modalidade, :modelo, FALSE, FALSE, NOW(), NOW(), NOW())
                         ON CONFLICT (idusuario, idmodalidade) DO UPDATE
                         SET idmodelo_ativo = EXCLUDED.idmodelo_ativo, ultimo_uso = NOW()'
                    );
                } else {
                    $preference = $pdo->prepare(
                        'INSERT INTO modalidades_usuario (idusuario, idmodalidade, idmodelo_ativo, ativo, favorita, data_ativacao, data_desativacao)
                         VALUES (:usuario, :modalidade, :modelo, FALSE, FALSE, NOW(), NOW())
                         ON CONFLICT (idusuario, idmodalidade) DO UPDATE
                         SET idmodelo_ativo = EXCLUDED.idmodelo_ativo'
                    );
                }
                $preference->execute([':usuario' => $idUsuario, ':modalidade' => $modelo['idmodalidade'], ':modelo' => $modelo['idmodelo']]);
            }
        } catch (Throwable $preferenceError) {
            error_log('StrideBR activity preference after save: ' . $preferenceError->getMessage());
        }

        if (!$isUpdate) {
            stridebr_marketing_activation($pdo, $idUsuario);
        }

        return $idRegistro;
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function atividadeValorLinha(array $row): mixed
{
    foreach (['valor_texto', 'valor_inteiro', 'valor_decimal', 'valor_booleano', 'valor_data', 'valor_hora', 'valor_intervalo', 'idopcao'] as $coluna) {
        if ($row[$coluna] !== null) {
            if ($coluna === 'valor_booleano') {
                return stridebr_db_bool($row[$coluna]);
            }
            if ($coluna === 'valor_intervalo') {
                return atividadeFormatarIntervalo($row[$coluna]);
            }
            return $row[$coluna];
        }
    }
    return null;
}

function atividadeCarregarRegistro(PDO $pdo, string $idRegistro, string $idUsuario): array
{
    $stmt = $pdo->prepare(
        "SELECT ra.*, m.nome AS modalidade_nome, m.slug AS modalidade_slug, m.icone AS modalidade_icone, m.metrica_derivada, m.familia_hub AS modalidade_familia_hub, m.permite_rota,
                mm.nome AS modelo_nome, mm.slug AS modelo_slug, mm.tipo_unidade_padrao, mm.rotulo_unidade, mm.permite_multiplas_unidades,
                tc.codigo AS treino_codigo, tc.foco AS treino_foco, tc.titulo AS treino_titulo, c.nome AS competicao_nome
         FROM registros_atividade ra
         JOIN modalidades m ON m.idmodalidade = ra.idmodalidade
         JOIN modelos_modalidade mm ON mm.idmodelo = ra.idmodelo
         LEFT JOIN treinos_cronograma tc ON tc.idtreino = ra.idtreino_cronograma
         LEFT JOIN competicoes_usuario c ON c.idcompeticao = ra.idcompeticao
         WHERE ra.idregistro = :id AND ra.idusuario = :usuario AND ra.excluido_em IS NULL LIMIT 1"
    );
    $stmt->execute([':id' => $idRegistro, ':usuario' => $idUsuario]);
    $registro = $stmt->fetch();
    if (!$registro) {
        return [];
    }

    $registro['permite_multiplas_unidades'] = stridebr_db_bool($registro['permite_multiplas_unidades']);
    $registro['permite_rota'] = stridebr_db_bool($registro['permite_rota']);
    $registro['usa_trechos'] = stridebr_db_bool($registro['usa_trechos'] ?? false);
    $registro['campos'] = atividadeBuscarCamposModelo($pdo, $registro['idmodelo'], false);
    $registro['record_values'] = [];
    $registro['unidades'] = [];

    $valueStmt = $pdo->prepare(
        'SELECT va.*, cmo.rotulo AS opcao_rotulo FROM valores_atividade va LEFT JOIN campos_modelo_opcoes cmo ON cmo.idopcao = va.idopcao WHERE va.idregistro = :registro AND va.idunidade_atividade IS NULL'
    );
    $valueStmt->execute([':registro' => $idRegistro]);
    foreach ($valueStmt->fetchAll() as $row) {
        $registro['record_values'][$row['idcampo']] = atividadeValorLinha($row);
    }

    $unitStmt = $pdo->prepare('SELECT ua.*, um.nome AS modalidade_nome, um.slug AS modalidade_slug, um.familia_hub AS modalidade_familia_hub, um.metrica_derivada AS modalidade_metrica_derivada, um.permite_rota AS modalidade_permite_rota FROM unidades_atividade ua LEFT JOIN modalidades um ON um.idmodalidade = ua.idmodalidade WHERE ua.idregistro = :registro ORDER BY ua.ordem');
    $unitStmt->execute([':registro' => $idRegistro]);
    $units = $unitStmt->fetchAll();
    $valuesByUnit = [];
    if ($units !== []) {
        $unitValueStmt = $pdo->prepare(
            'SELECT va.*, cmo.rotulo AS opcao_rotulo FROM valores_atividade va LEFT JOIN campos_modelo_opcoes cmo ON cmo.idopcao = va.idopcao WHERE va.idregistro = :registro AND va.idunidade_atividade IS NOT NULL ORDER BY va.idunidade_atividade'
        );
        $unitValueStmt->execute([':registro' => $idRegistro]);
        foreach ($unitValueStmt->fetchAll() as $row) {
            $valuesByUnit[(string) $row['idunidade_atividade']][(string) $row['idcampo']] = atividadeValorLinha($row);
        }
    }
    $routesByUnit = [];
    if ($units !== []) {
        $unitRouteStmt = $pdo->prepare('SELECT * FROM rotas_unidades_atividade WHERE idregistro = :registro');
        $unitRouteStmt->execute([':registro' => $idRegistro]);
        foreach ($unitRouteStmt->fetchAll() as $routeRow) {
            $routesByUnit[(string) $routeRow['idunidade_atividade']] = $routeRow;
        }
    }
    foreach ($units as $unit) {
        $unitId = (string) $unit['idunidade_atividade'];
        $unit['idmodalidade'] = trim((string) ($unit['idmodalidade'] ?? '')) ?: (string) $registro['idmodalidade'];
        $unit['modalidade_nome'] = trim((string) ($unit['modalidade_nome'] ?? '')) ?: (string) $registro['modalidade_nome'];
        $unit['modalidade_slug'] = trim((string) ($unit['modalidade_slug'] ?? '')) ?: (string) $registro['modalidade_slug'];
        $unit['modalidade_familia_hub'] = trim((string) ($unit['modalidade_familia_hub'] ?? '')) ?: (string) ($registro['modalidade_familia_hub'] ?? '');
        $unit['modalidade_metrica_derivada'] = trim((string) ($unit['modalidade_metrica_derivada'] ?? '')) ?: (string) $registro['metrica_derivada'];
        $unit['modalidade_permite_rota'] = $unit['modalidade_permite_rota'] === null ? $registro['permite_rota'] : stridebr_db_bool($unit['modalidade_permite_rota']);
        $unit['values'] = $valuesByUnit[$unitId] ?? [];
        $unit['rota'] = $routesByUnit[$unitId] ?? null;
        $registro['unidades'][] = $unit;
    }

    $equipmentStmt = $pdo->prepare(
        'SELECT eu.idequipamento, eu.nome, eu.categoria, eu.marca, eu.modelo
         FROM registros_atividade_equipamentos rae
         JOIN equipamentos_usuario eu ON eu.idequipamento = rae.idequipamento
         WHERE rae.idregistro = :registro AND eu.idusuario = :usuario
         ORDER BY eu.nome'
    );
    $equipmentStmt->execute([':registro' => $idRegistro, ':usuario' => $idUsuario]);
    $registro['equipamentos'] = $equipmentStmt->fetchAll();
    $routeStmt = $pdo->prepare('SELECT * FROM rotas_atividade WHERE idregistro = :registro LIMIT 1');
    $routeStmt->execute([':registro' => $idRegistro]);
    $registro['rota'] = $routeStmt->fetch() ?: null;

    return $registro;
}

function atividadeCarregarRegistrosDetalhados(PDO $pdo, array $registros, string $idUsuario): array
{
    $ids = array_values(array_unique(array_filter(array_map(static fn(array $row): string => (string) ($row['idregistro'] ?? ''), $registros))));
    if ($ids === []) {
        return [];
    }

    $recordPlaceholders = [];
    $params = [':usuario' => $idUsuario];
    foreach ($ids as $index => $idRegistro) {
        $key = ':registro_' . $index;
        $recordPlaceholders[] = $key;
        $params[$key] = $idRegistro;
    }
    $in = implode(', ', $recordPlaceholders);

    $stmt = $pdo->prepare(
        "SELECT ra.*, m.nome AS modalidade_nome, m.slug AS modalidade_slug, m.icone AS modalidade_icone, m.metrica_derivada, m.familia_hub AS modalidade_familia_hub, m.permite_rota,
                mm.nome AS modelo_nome, mm.slug AS modelo_slug, mm.tipo_unidade_padrao, mm.rotulo_unidade, mm.permite_multiplas_unidades
         FROM registros_atividade ra
         JOIN modalidades m ON m.idmodalidade = ra.idmodalidade
         JOIN modelos_modalidade mm ON mm.idmodelo = ra.idmodelo
         WHERE ra.idusuario = :usuario AND ra.excluido_em IS NULL AND ra.idregistro IN ({$in})"
    );
    $stmt->execute($params);
    $details = [];
    $modelIds = [];
    foreach ($stmt->fetchAll() as $registro) {
        $registro['permite_multiplas_unidades'] = stridebr_db_bool($registro['permite_multiplas_unidades']);
        $registro['permite_rota'] = stridebr_db_bool($registro['permite_rota']);
        $registro['usa_trechos'] = stridebr_db_bool($registro['usa_trechos'] ?? false);
        $registro['record_values'] = [];
        $registro['unidades'] = [];
        $registro['equipamentos'] = [];
        $registro['rota'] = null;
        $details[(string) $registro['idregistro']] = $registro;
        $modelIds[] = (string) $registro['idmodelo'];
    }

    $fieldsByModel = atividadeBuscarCamposModelos($pdo, $modelIds, false);
    foreach ($details as &$detail) {
        $detail['campos'] = $fieldsByModel[(string) $detail['idmodelo']] ?? [];
    }
    unset($detail);

    $unitStmt = $pdo->prepare("SELECT ua.*, um.nome AS modalidade_nome, um.slug AS modalidade_slug, um.familia_hub AS modalidade_familia_hub, um.metrica_derivada AS modalidade_metrica_derivada, um.permite_rota AS modalidade_permite_rota FROM unidades_atividade ua LEFT JOIN modalidades um ON um.idmodalidade = ua.idmodalidade WHERE ua.idregistro IN ({$in}) ORDER BY ua.idregistro, ua.ordem");
    $unitParams = $params;
    unset($unitParams[':usuario']);
    $unitStmt->execute($unitParams);
    $unitToRecord = [];
    $unitsByRecord = [];
    foreach ($unitStmt->fetchAll() as $unit) {
        $idRegistro = (string) $unit['idregistro'];
        $idUnidade = (string) $unit['idunidade_atividade'];
        $record = $details[$idRegistro] ?? [];
        $unit['idmodalidade'] = trim((string) ($unit['idmodalidade'] ?? '')) ?: (string) ($record['idmodalidade'] ?? '');
        $unit['modalidade_nome'] = trim((string) ($unit['modalidade_nome'] ?? '')) ?: (string) ($record['modalidade_nome'] ?? '');
        $unit['modalidade_slug'] = trim((string) ($unit['modalidade_slug'] ?? '')) ?: (string) ($record['modalidade_slug'] ?? '');
        $unit['modalidade_familia_hub'] = trim((string) ($unit['modalidade_familia_hub'] ?? '')) ?: (string) ($record['modalidade_familia_hub'] ?? '');
        $unit['modalidade_metrica_derivada'] = trim((string) ($unit['modalidade_metrica_derivada'] ?? '')) ?: (string) ($record['metrica_derivada'] ?? 'nenhuma');
        $unit['modalidade_permite_rota'] = $unit['modalidade_permite_rota'] === null ? !empty($record['permite_rota']) : stridebr_db_bool($unit['modalidade_permite_rota']);
        $unit['values'] = [];
        $unitsByRecord[$idRegistro][$idUnidade] = $unit;
        $unitToRecord[$idUnidade] = $idRegistro;
    }

    $valueStmt = $pdo->prepare(
        "SELECT va.* FROM valores_atividade va WHERE va.idregistro IN ({$in}) ORDER BY va.idregistro"
    );
    $valueStmt->execute($unitParams);
    foreach ($valueStmt->fetchAll() as $row) {
        $idRegistro = (string) $row['idregistro'];
        if (!isset($details[$idRegistro])) {
            continue;
        }
        $idUnidade = $row['idunidade_atividade'] !== null ? (string) $row['idunidade_atividade'] : '';
        if ($idUnidade === '') {
            $details[$idRegistro]['record_values'][(string) $row['idcampo']] = atividadeValorLinha($row);
        } elseif (isset($unitsByRecord[$idRegistro][$idUnidade])) {
            $unitsByRecord[$idRegistro][$idUnidade]['values'][(string) $row['idcampo']] = atividadeValorLinha($row);
        }
    }

    if ($unitToRecord !== []) {
        $unitRouteStmt = $pdo->prepare("SELECT * FROM rotas_unidades_atividade WHERE idregistro IN ({$in})");
        $unitRouteStmt->execute($unitParams);
        foreach ($unitRouteStmt->fetchAll() as $routeRow) {
            $idRegistro = (string) $routeRow['idregistro'];
            $idUnidade = (string) $routeRow['idunidade_atividade'];
            if (isset($unitsByRecord[$idRegistro][$idUnidade])) {
                $unitsByRecord[$idRegistro][$idUnidade]['rota'] = $routeRow;
            }
        }
    }

    foreach ($unitsByRecord as $idRegistro => $units) {
        if (isset($details[$idRegistro])) {
            $details[$idRegistro]['unidades'] = array_values($units);
        }
    }

    $equipmentParams = $params;
    $equipmentStmt = $pdo->prepare(
        "SELECT rae.idregistro, eu.idequipamento, eu.nome, eu.categoria, eu.marca, eu.modelo
         FROM registros_atividade_equipamentos rae
         JOIN equipamentos_usuario eu ON eu.idequipamento = rae.idequipamento
         WHERE eu.idusuario = :usuario AND rae.idregistro IN ({$in})
         ORDER BY rae.idregistro, eu.nome"
    );
    $equipmentStmt->execute($equipmentParams);
    foreach ($equipmentStmt->fetchAll() as $equipment) {
        $idRegistro = (string) $equipment['idregistro'];
        unset($equipment['idregistro']);
        if (isset($details[$idRegistro])) {
            $details[$idRegistro]['equipamentos'][] = $equipment;
        }
    }

    $routeStmt = $pdo->prepare("SELECT * FROM rotas_atividade WHERE idregistro IN ({$in})");
    $routeStmt->execute($unitParams);
    foreach ($routeStmt->fetchAll() as $routeRow) {
        $idRegistro = (string) $routeRow['idregistro'];
        if (isset($details[$idRegistro])) $details[$idRegistro]['rota'] = $routeRow;
    }

    return $details;
}

function atividadeListarRegistros(PDO $pdo, string $idUsuario, int $limite = 50): array
{
    $limite = max(1, min(501, $limite));
    $stmt = $pdo->prepare(
        "SELECT ra.idregistro, ra.titulo, ra.observacoes, ra.data_inicio, ra.status, ra.visibilidade, ra.esforco_percebido,
                m.nome AS modalidade_nome, m.slug AS modalidade_slug, m.icone AS modalidade_icone, mm.nome AS modelo_nome,
                COUNT(DISTINCT ua.idunidade_atividade) AS total_unidades
         FROM registros_atividade ra
         JOIN modalidades m ON m.idmodalidade = ra.idmodalidade
         JOIN modelos_modalidade mm ON mm.idmodelo = ra.idmodelo
         LEFT JOIN unidades_atividade ua ON ua.idregistro = ra.idregistro
         WHERE ra.idusuario = :usuario AND ra.excluido_em IS NULL
         GROUP BY ra.idregistro, m.nome, m.slug, m.icone, mm.nome
         ORDER BY ra.data_inicio DESC
         LIMIT {$limite}"
    );
    $stmt->execute([':usuario' => $idUsuario]);
    return $stmt->fetchAll();
}

function atividadeExcluirRegistro(PDO $pdo, string $idRegistro, string $idUsuario): bool
{
    $stmt = $pdo->prepare('UPDATE registros_atividade SET excluido_em = NOW(), data_atualizacao = NOW() WHERE idregistro = :id AND idusuario = :usuario AND excluido_em IS NULL');
    $stmt->execute([':id' => $idRegistro, ':usuario' => $idUsuario]);
    return $stmt->rowCount() === 1;
}

function atividadeRestaurarRegistro(PDO $pdo, string $idRegistro, string $idUsuario): bool
{
    $stmt = $pdo->prepare("UPDATE registros_atividade SET excluido_em = NULL, data_atualizacao = NOW() WHERE idregistro = :id AND idusuario = :usuario AND excluido_em IS NOT NULL AND excluido_em >= NOW() - INTERVAL '30 minutes'");
    $stmt->execute([':id' => $idRegistro, ':usuario' => $idUsuario]);
    return $stmt->rowCount() === 1;
}


function atividadeLimparDuracaoPayload(array &$recordValues, array &$unidades, array $campos): void
{
    foreach ($campos as $campo) {
        if (stridebr_lower((string) ($campo['slug'] ?? '')) !== 'duracao') continue;
        $idCampo = (string) ($campo['idcampo'] ?? '');
        if ($idCampo === '') continue;
        if (($campo['escopo'] ?? '') === 'registro') {
            unset($recordValues[$idCampo]);
        } else {
            foreach ($unidades as &$unidade) {
                if (isset($unidade['values']) && is_array($unidade['values'])) unset($unidade['values'][$idCampo]);
            }
            unset($unidade);
        }
    }
}

function atividadeSubstituirValoresModelo(PDO $pdo, string $idRegistro, array $modelo, array $campos, array $recordValues, array $unidades): void
{
    $pdo->prepare('DELETE FROM valores_atividade WHERE idregistro = :registro')->execute([':registro' => $idRegistro]);
    $pdo->prepare('DELETE FROM unidades_atividade WHERE idregistro = :registro')->execute([':registro' => $idRegistro]);

    foreach ($campos as $campo) {
        if (($campo['escopo'] ?? '') !== 'registro') continue;
        $raw = $recordValues[(string) $campo['idcampo']] ?? null;
        if ($raw === null || (is_string($raw) && trim($raw) === '')) continue;
        $prepared = atividadePrepararValor($campo, $raw);
        if ($prepared !== null) atividadeInserirValor($pdo, $idRegistro, null, $campo, $prepared);
    }

    if ($unidades === []) $unidades = [['rotulo' => '', 'observacoes' => '', 'values' => []]];
    if (empty($modelo['permite_multiplas_unidades'])) $unidades = [reset($unidades) ?: ['rotulo' => '', 'observacoes' => '', 'values' => []]];
    $ordem = 1;
    foreach ($unidades as $unidadePayload) {
        if (!is_array($unidadePayload)) continue;
        $idUnidade = atividadeGerarId();
        $stmtUnit = $pdo->prepare('INSERT INTO unidades_atividade (idunidade_atividade, idregistro, ordem, tipo_unidade, rotulo, observacoes) VALUES (:id, :registro, :ordem, :tipo, :rotulo, :observacoes)');
        $rotulo = trim((string) ($unidadePayload['rotulo'] ?? ''));
        $observacoes = trim((string) ($unidadePayload['observacoes'] ?? ''));
        $stmtUnit->execute([
            ':id' => $idUnidade,
            ':registro' => $idRegistro,
            ':ordem' => $ordem,
            ':tipo' => (string) ($modelo['tipo_unidade_padrao'] ?? 'unidade'),
            ':rotulo' => $rotulo !== '' ? $rotulo : null,
            ':observacoes' => $observacoes !== '' ? $observacoes : null,
        ]);
        $values = is_array($unidadePayload['values'] ?? null) ? $unidadePayload['values'] : [];
        foreach ($campos as $campo) {
            if (($campo['escopo'] ?? '') !== 'unidade') continue;
            $raw = $values[(string) $campo['idcampo']] ?? null;
            if ($raw === null || (is_string($raw) && trim($raw) === '')) continue;
            $prepared = atividadePrepararValor($campo, $raw);
            if ($prepared !== null) atividadeInserirValor($pdo, $idRegistro, $idUnidade, $campo, $prepared);
        }
        $ordem++;
    }
}

function atividadeAtualizarRegistrosEmLote(PDO $pdo, string $idUsuario, array $ids, array $changes): array
{
    $ids = array_values(array_unique(array_filter(array_map(static fn($id): string => trim((string) $id), $ids))));
    if ($ids === []) throw new InvalidArgumentException('Selecione pelo menos uma atividade.');
    if (count($ids) > 100) throw new InvalidArgumentException('Edite no máximo 100 atividades por vez.');

    $placeholders = [];
    $params = [':usuario' => $idUsuario];
    foreach ($ids as $index => $id) {
        $key = ':id' . $index;
        $placeholders[] = $key;
        $params[$key] = $id;
    }
    $in = implode(',', $placeholders);

    $owned = $pdo->prepare("SELECT idregistro, idmodelo, idmodalidade, data_inicio, data_fim FROM registros_atividade WHERE idusuario = :usuario AND excluido_em IS NULL AND idregistro IN ({$in}) ORDER BY idregistro");
    $owned->execute($params);
    $ownedRows = $owned->fetchAll(PDO::FETCH_ASSOC);
    if (count($ownedRows) !== count($ids)) throw new InvalidArgumentException('Uma ou mais atividades não estão disponíveis.');

    if (!empty($changes['apagar'])) {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE registros_atividade SET excluido_em = NOW(), data_atualizacao = NOW() WHERE idusuario = :usuario AND excluido_em IS NULL AND idregistro IN ({$in})");
            $stmt->execute($params);
            $affected = $stmt->rowCount();
            $pdo->commit();
            return ['total' => $affected, 'deleted' => true];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    $idModalidade = trim((string) ($changes['idmodalidade'] ?? ''));
    $targetModel = [];
    $targetFields = [];
    if ($idModalidade !== '') {
        $targetModel = atividadeBuscarModeloPadraoModalidade($pdo, $idModalidade, $idUsuario);
        if ($targetModel === []) throw new InvalidArgumentException('Modalidade inválida.');
        $targetFields = atividadeBuscarCamposModelo($pdo, (string) $targetModel['idmodelo'], true);
    }

    $durationMode = trim((string) ($changes['duracao_modo'] ?? 'keep'));
    if (!in_array($durationMode, ['keep', 'set', 'clear'], true)) throw new InvalidArgumentException('Ação de duração inválida.');
    $durationMinutes = null;
    if ($durationMode === 'set') {
        $durationRaw = trim((string) ($changes['duracao_minutos'] ?? ''));
        if (!ctype_digit($durationRaw)) throw new InvalidArgumentException('Informe a duração que deseja aplicar.');
        $durationMinutes = (int) $durationRaw;
        if ($durationMinutes < 1 || $durationMinutes > 1440) throw new InvalidArgumentException('A duração deve ficar entre 1 e 1440 minutos.');
    }

    $visibility = trim((string) ($changes['visibilidade'] ?? ''));
    if ($visibility !== '' && !in_array($visibility, ['privado', 'amigos', 'publico'], true)) throw new InvalidArgumentException('Visibilidade inválida.');
    if ($idModalidade === '' && $durationMode === 'keep' && $visibility === '') throw new InvalidArgumentException('Escolha pelo menos uma alteração para aplicar.');

    $needsValues = $idModalidade !== '' || $durationMode !== 'keep';
    $details = [];
    if ($needsValues) {
        $details = atividadeCarregarRegistrosDetalhados($pdo, array_map(static fn(array $row): array => ['idregistro' => (string) $row['idregistro']], $ownedRows), $idUsuario);
    }

    $modelCache = [];
    $fieldCache = [];
    $pdo->beginTransaction();
    try {
        foreach ($ownedRows as $row) {
            $idRegistro = (string) $row['idregistro'];
            $detail = $details[$idRegistro] ?? [];
            $currentModelId = (string) $row['idmodelo'];
            $model = $targetModel;
            $fields = $targetFields;
            $recordValues = is_array($detail['record_values'] ?? null) ? $detail['record_values'] : [];
            $units = is_array($detail['unidades'] ?? null) ? $detail['unidades'] : [];

            if ($idModalidade !== '') {
                $sourceFields = is_array($detail['campos'] ?? null) ? $detail['campos'] : [];
                $mapped = atividadeRemapearValoresModelo($sourceFields, $targetFields, $recordValues, $units);
                $recordValues = $mapped['record_values'];
                $units = $mapped['unidades'];
                if ($durationMode === 'keep' && !empty($row['data_inicio']) && !empty($row['data_fim'])) {
                    $startTs = strtotime((string) $row['data_inicio']);
                    $endTs = strtotime((string) $row['data_fim']);
                    if ($startTs !== false && $endTs !== false && $endTs > $startTs) {
                        atividadeAplicarDuracaoCalculada($recordValues, $units, $targetFields, $endTs - $startTs);
                    }
                }
            } elseif ($needsValues) {
                if (!isset($modelCache[$currentModelId])) $modelCache[$currentModelId] = atividadeBuscarModelo($pdo, $currentModelId, $idUsuario, false);
                if (!isset($fieldCache[$currentModelId])) $fieldCache[$currentModelId] = atividadeBuscarCamposModelo($pdo, $currentModelId, false);
                $model = $modelCache[$currentModelId];
                $fields = $fieldCache[$currentModelId];
            }

            if ($durationMode === 'set' && $durationMinutes !== null) {
                atividadeAplicarDuracaoCalculada($recordValues, $units, $fields, $durationMinutes * 60);
            } elseif ($durationMode === 'clear') {
                atividadeLimparDuracaoPayload($recordValues, $units, $fields);
            }

            $sets = [];
            $rowParams = [':registro' => $idRegistro, ':usuario' => $idUsuario];
            if ($idModalidade !== '') {
                $sets[] = 'idmodalidade = :modalidade_nova';
                $sets[] = 'idmodelo = :modelo_novo';
                $rowParams[':modalidade_nova'] = (string) $targetModel['idmodalidade'];
                $rowParams[':modelo_novo'] = (string) $targetModel['idmodelo'];
            }
            if ($durationMode === 'set' && $durationMinutes !== null) {
                $sets[] = "data_fim = data_inicio + (CAST(:duracao_minutos AS integer) * INTERVAL '1 minute')";
                $rowParams[':duracao_minutos'] = $durationMinutes;
            } elseif ($durationMode === 'clear') {
                $sets[] = 'data_fim = NULL';
            }
            if ($visibility !== '') {
                $sets[] = 'visibilidade = :visibilidade_nova';
                $rowParams[':visibilidade_nova'] = $visibility;
            }
            $sets[] = 'data_atualizacao = NOW()';
            $stmt = $pdo->prepare('UPDATE registros_atividade SET ' . implode(', ', $sets) . ' WHERE idregistro = :registro AND idusuario = :usuario AND excluido_em IS NULL');
            $stmt->execute($rowParams);

            if ($needsValues && $model !== []) atividadeSubstituirValoresModelo($pdo, $idRegistro, $model, $fields, $recordValues, $units);
        }
        $pdo->commit();
        return ['total' => count($ownedRows), 'deleted' => false];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function atividadeListarEquipamentosLeve(PDO $pdo, string $idUsuario, bool $somenteAtivos = true): array
{
    $sql = 'SELECT idequipamento, nome, categoria, marca, modelo, ativo FROM equipamentos_usuario WHERE idusuario = :usuario';
    if ($somenteAtivos) $sql .= ' AND ativo = TRUE';
    $sql .= ' ORDER BY ativo DESC, nome';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':usuario' => $idUsuario]);
    return $stmt->fetchAll();
}
