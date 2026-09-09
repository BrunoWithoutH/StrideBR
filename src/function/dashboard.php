<?php

declare(strict_types=1);

require_once __DIR__ . '/activity_sport_context.php';

function dashboardGerarId(int $length = 21): string
{
    return stridebr_generate_id($length);
}

function dashboardMetasDisponiveis(PDO $pdo): bool
{
    static $available = null;
    if ($available !== null) return $available;
    $cached = $_SESSION['StrideBRCapabilityCache']['dashboard_goals'] ?? null;
    if (is_array($cached) && isset($cached['at']) && (time() - (int) $cached['at']) < 300) {
        return $available = (bool) ($cached['value'] ?? false);
    }
    try {
        $value = $pdo->query(
            "SELECT to_regclass('stridebr.metas_usuario') IS NOT NULL
                    AND to_regclass('stridebr.metas_conclusoes') IS NOT NULL
                    AND EXISTS (
                        SELECT 1 FROM information_schema.columns
                        WHERE table_schema='stridebr' AND table_name='metas_usuario' AND column_name='data_inicio'
                    )
                    AND EXISTS (
                        SELECT 1 FROM information_schema.columns
                        WHERE table_schema='stridebr' AND table_name='metas_usuario' AND column_name='arquivada_em'
                    )
                    AND EXISTS (
                        SELECT 1 FROM information_schema.columns
                        WHERE table_schema='stridebr' AND table_name='metas_usuario' AND column_name='idexercicio'
                    )"
        )->fetchColumn();
        $available = stridebr_db_bool($value);
    } catch (Throwable) {
        $available = false;
    }
    $_SESSION['StrideBRCapabilityCache']['dashboard_goals'] = ['value' => $available, 'at' => time()];
    return $available;
}

function dashboardListarModalidades(PDO $pdo, string $idUsuario): array
{
    $stmt = $pdo->prepare(
        "SELECT m.idmodalidade, m.nome, m.slug, m.categoria, m.familia_hub,
                COALESCE(mu.favorita, FALSE) AS favorita,
                mu.ultimo_uso
         FROM modalidades m
         LEFT JOIN modalidades_usuario mu
           ON mu.idmodalidade = m.idmodalidade
          AND mu.idusuario = :usuario
         WHERE m.ativo = TRUE
           AND (m.idusuario IS NULL OR m.idusuario = :usuario)
         ORDER BY COALESCE(mu.favorita, FALSE) DESC,
                  mu.ultimo_uso DESC NULLS LAST,
                  m.ordem_catalogo,
                  m.nome"
    );
    $stmt->execute([':usuario' => $idUsuario]);
    return $stmt->fetchAll();
}

function dashboardListarExerciciosMeta(PDO $pdo, string $idUsuario): array
{
    $stmt = $pdo->prepare(
        "SELECT e.idexercicio, e.nome,
                COALESCE(string_agg(DISTINCT em.idmodalidade, ',' ORDER BY em.idmodalidade), '') AS modalidades
         FROM exercicios e
         LEFT JOIN exercicios_modalidades em ON em.idexercicio = e.idexercicio
         WHERE e.ativo = TRUE
           AND (e.idusuario IS NULL OR e.idusuario = :usuario)
         GROUP BY e.idexercicio, e.nome, e.idusuario
         ORDER BY CASE WHEN e.idusuario = :usuario THEN 0 ELSE 1 END, lower(e.nome), e.idexercicio"
    );
    $stmt->execute([':usuario' => $idUsuario]);
    return $stmt->fetchAll();
}

function dashboardMetricasMeta(): array
{
    return ['distancia', 'duracao', 'atividades', 'elevacao', 'dias_ativos', 'carga_maxima'];
}

function dashboardPeriodosMeta(): array
{
    return ['continuo', 'semanal', 'mensal', 'anual', 'personalizado'];
}

function dashboardNormalizarDataMeta(?string $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('America/Sao_Paulo'));
    if (!$date || $date->format('Y-m-d') !== $value) {
        throw new InvalidArgumentException(stridebr_t('goals.error.invalid_dates'));
    }
    return $value;
}

function dashboardValidarMeta(PDO $pdo, string $idUsuario, array $payload, ?string $idMeta = null): array
{
    $metrica = trim((string) ($payload['metrica'] ?? ''));
    $periodo = trim((string) ($payload['periodo'] ?? ''));
    $idModalidade = trim((string) ($payload['idmodalidade'] ?? '')) ?: null;
    $idExercicio = trim((string) ($payload['idexercicio'] ?? '')) ?: null;
    $nome = trim((string) ($payload['nome'] ?? '')) ?: null;
    $valorRaw = str_replace(',', '.', trim((string) ($payload['valor_alvo'] ?? '')));
    $dataInicio = dashboardNormalizarDataMeta($payload['data_inicio'] ?? null);
    $dataFim = dashboardNormalizarDataMeta($payload['data_fim'] ?? null);

    if (!in_array($metrica, dashboardMetricasMeta(), true)) {
        throw new InvalidArgumentException(stridebr_t('goals.error.invalid_metric'));
    }
    if (!in_array($periodo, dashboardPeriodosMeta(), true)) {
        throw new InvalidArgumentException(stridebr_t('goals.error.invalid_period'));
    }
    if (!is_numeric($valorRaw) || (float) $valorRaw <= 0) {
        throw new InvalidArgumentException(stridebr_t('goals.error.target_positive'));
    }

    $valor = (float) $valorRaw;
    $limites = [
        'distancia' => 100000.0,
        'duracao' => 1000000.0,
        'atividades' => 10000.0,
        'elevacao' => 10000000.0,
        'dias_ativos' => 10000.0,
        'carga_maxima' => 100000.0,
    ];
    if ($valor > $limites[$metrica]) {
        throw new InvalidArgumentException(stridebr_t('goals.error.target_too_large'));
    }
    if (in_array($metrica, ['atividades', 'dias_ativos'], true) && floor($valor) !== $valor) {
        throw new InvalidArgumentException(stridebr_t('goals.error.integer_required'));
    }
    if ($nome !== null && stridebr_length($nome) > 80) {
        throw new InvalidArgumentException(stridebr_t('goals.error.name_too_long'));
    }

    if ($periodo === 'personalizado') {
        if ($dataInicio === null || $dataFim === null) {
            throw new InvalidArgumentException(stridebr_t('goals.error.custom_dates'));
        }
        if ($dataFim < $dataInicio) {
            throw new InvalidArgumentException(stridebr_t('goals.error.end_before_start'));
        }
        $inicio = new DateTimeImmutable($dataInicio);
        $fim = new DateTimeImmutable($dataFim);
        if ($inicio->diff($fim)->days > 3660) {
            throw new InvalidArgumentException(stridebr_t('goals.error.max_ten_years'));
        }
        if ($metrica === 'dias_ativos' && $valor > ((int) $inicio->diff($fim)->days + 1)) {
            throw new InvalidArgumentException(stridebr_t('goals.error.active_days_period'));
        }
    } elseif ($periodo === 'continuo') {
        $dataInicio ??= (new DateTimeImmutable('today', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
        $dataFim = null;
    } else {
        $dataInicio = null;
        $dataFim = null;
        if ($metrica === 'dias_ativos') {
            $maxDias = match ($periodo) {
                'semanal' => 7,
                'mensal' => 31,
                default => 366,
            };
            if ($valor > $maxDias) {
                throw new InvalidArgumentException(stridebr_t('goals.error.active_days_period'));
            }
        }
    }

    if ($idModalidade !== null) {
        $check = $pdo->prepare(
            'SELECT 1 FROM modalidades WHERE idmodalidade = :modalidade AND ativo = TRUE AND (idusuario IS NULL OR idusuario = :usuario)'
        );
        $check->execute([':modalidade' => $idModalidade, ':usuario' => $idUsuario]);
        if (!$check->fetchColumn()) {
            throw new InvalidArgumentException(stridebr_t('goals.error.invalid_sport'));
        }
    }

    if ($metrica === 'carga_maxima') {
        if ($idExercicio === null) {
            throw new InvalidArgumentException(stridebr_t('goals.error.choose_exercise'));
        }
        $checkExercise = $pdo->prepare('SELECT 1 FROM exercicios WHERE idexercicio = :exercicio AND ativo = TRUE AND (idusuario IS NULL OR idusuario = :usuario)');
        $checkExercise->execute([':exercicio' => $idExercicio, ':usuario' => $idUsuario]);
        if (!$checkExercise->fetchColumn()) throw new InvalidArgumentException(stridebr_t('goals.error.invalid_exercise'));
    } else {
        $idExercicio = null;
    }

    if ($idMeta !== null) {
        $check = $pdo->prepare('SELECT 1 FROM metas_usuario WHERE idmeta = :meta AND idusuario = :usuario LIMIT 1');
        $check->execute([':meta' => $idMeta, ':usuario' => $idUsuario]);
        if (!$check->fetchColumn()) {
            throw new InvalidArgumentException(stridebr_t('goals.error.not_found'));
        }
    }

    return [
        'metrica' => $metrica,
        'periodo' => $periodo,
        'idmodalidade' => $idModalidade,
        'idexercicio' => $idExercicio,
        'nome' => $nome,
        'valor_alvo' => $valor,
        'data_inicio' => $dataInicio,
        'data_fim' => $dataFim,
    ];
}

function dashboardCriarMeta(PDO $pdo, string $idUsuario, array $payload): void
{
    if (!dashboardMetasDisponiveis($pdo)) {
        throw new RuntimeException(stridebr_t('goals.error.migration_missing'));
    }

    $dados = dashboardValidarMeta($pdo, $idUsuario, $payload);
    $count = $pdo->prepare('SELECT COUNT(*) FROM metas_usuario WHERE idusuario = :usuario AND ativa = TRUE');
    $count->execute([':usuario' => $idUsuario]);
    if ((int) $count->fetchColumn() >= 12) {
        throw new InvalidArgumentException(stridebr_t('goals.error.max_active'));
    }

    $stmt = $pdo->prepare(
        'INSERT INTO metas_usuario (idmeta, idusuario, idmodalidade, idexercicio, nome, metrica, periodo, valor_alvo, data_inicio, data_fim)
         VALUES (:id, :usuario, :modalidade, :exercicio, :nome, :metrica, :periodo, :valor, :inicio, :fim)'
    );
    $stmt->execute([
        ':id' => dashboardGerarId(),
        ':usuario' => $idUsuario,
        ':modalidade' => $dados['idmodalidade'],
        ':exercicio' => $dados['idexercicio'],
        ':nome' => $dados['nome'],
        ':metrica' => $dados['metrica'],
        ':periodo' => $dados['periodo'],
        ':valor' => $dados['valor_alvo'],
        ':inicio' => $dados['data_inicio'],
        ':fim' => $dados['data_fim'],
    ]);
}

function dashboardEditarMeta(PDO $pdo, string $idUsuario, string $idMeta, array $payload): void
{
    if (!dashboardMetasDisponiveis($pdo)) {
        throw new RuntimeException(stridebr_t('goals.error.migration_missing'));
    }
    $dados = dashboardValidarMeta($pdo, $idUsuario, $payload, $idMeta);
    $stmt = $pdo->prepare(
        'UPDATE metas_usuario
         SET idmodalidade = :modalidade, idexercicio = :exercicio, nome = :nome, metrica = :metrica, periodo = :periodo,
             valor_alvo = :valor, data_inicio = :inicio, data_fim = :fim, data_atualizacao = NOW()
         WHERE idmeta = :meta AND idusuario = :usuario'
    );
    $stmt->execute([
        ':modalidade' => $dados['idmodalidade'],
        ':exercicio' => $dados['idexercicio'],
        ':nome' => $dados['nome'],
        ':metrica' => $dados['metrica'],
        ':periodo' => $dados['periodo'],
        ':valor' => $dados['valor_alvo'],
        ':inicio' => $dados['data_inicio'],
        ':fim' => $dados['data_fim'],
        ':meta' => $idMeta,
        ':usuario' => $idUsuario,
    ]);
}

function dashboardArquivarMeta(PDO $pdo, string $idUsuario, string $idMeta): bool
{
    if (!dashboardMetasDisponiveis($pdo)) {
        return false;
    }
    $stmt = $pdo->prepare(
        'UPDATE metas_usuario SET ativa = FALSE, arquivada_em = NOW(), data_atualizacao = NOW() WHERE idmeta = :meta AND idusuario = :usuario AND ativa = TRUE'
    );
    $stmt->execute([':meta' => $idMeta, ':usuario' => $idUsuario]);
    return $stmt->rowCount() === 1;
}

function dashboardReativarMeta(PDO $pdo, string $idUsuario, string $idMeta): bool
{
    if (!dashboardMetasDisponiveis($pdo)) {
        return false;
    }
    $stmt = $pdo->prepare(
        'UPDATE metas_usuario SET ativa = TRUE, arquivada_em = NULL, data_atualizacao = NOW() WHERE idmeta = :meta AND idusuario = :usuario AND ativa = FALSE'
    );
    $stmt->execute([':meta' => $idMeta, ':usuario' => $idUsuario]);
    return $stmt->rowCount() === 1;
}

function dashboardPeriodoMeta(array $meta, ?DateTimeImmutable $now = null): array
{
    $tz = new DateTimeZone('America/Sao_Paulo');
    $now ??= new DateTimeImmutable('now', $tz);
    $periodo = (string) ($meta['periodo'] ?? 'semanal');

    if ($periodo === 'personalizado') {
        $inicio = new DateTimeImmutable((string) $meta['data_inicio'] . ' 00:00:00', $tz);
        $fim = new DateTimeImmutable((string) $meta['data_fim'] . ' 23:59:59', $tz);
    } elseif ($periodo === 'continuo') {
        $inicioRaw = trim((string) ($meta['data_inicio'] ?? ''));
        if ($inicioRaw === '') {
            $inicioRaw = substr((string) ($meta['data_criacao'] ?? $now->format('Y-m-d')), 0, 10);
        }
        $inicio = new DateTimeImmutable($inicioRaw . ' 00:00:00', $tz);
        $concluidaEm = trim((string) ($meta['concluida_em'] ?? ''));
        $fim = $concluidaEm !== '' ? new DateTimeImmutable($concluidaEm, $tz) : $now;
    } elseif ($periodo === 'mensal') {
        $inicio = $now->modify('first day of this month')->setTime(0, 0);
        $fim = $now->modify('last day of this month')->setTime(23, 59, 59);
    } elseif ($periodo === 'anual') {
        $inicio = $now->setDate((int) $now->format('Y'), 1, 1)->setTime(0, 0);
        $fim = $now->setDate((int) $now->format('Y'), 12, 31)->setTime(23, 59, 59);
    } else {
        $inicio = $now->modify('monday this week')->setTime(0, 0);
        $fim = $inicio->modify('+6 days')->setTime(23, 59, 59);
    }

    return [$inicio, $fim];
}

function dashboardInicioPeriodo(string $periodo): DateTimeImmutable
{
    [$inicio] = dashboardPeriodoMeta(['periodo' => $periodo]);
    return $inicio;
}

function dashboardCargaNumero(?string $valor): ?float
{
    $valor = trim((string) $valor);
    if ($valor === '' || !preg_match('/-?\d+(?:[.,]\d+)?/', $valor, $match)) return null;
    return (float) str_replace(',', '.', $match[0]);
}

function dashboardCargaMaximaMeta(PDO $pdo, string $idUsuario, string $idExercicio, DateTimeImmutable $inicio, DateTimeImmutable $fim): float
{
    if ($idExercicio === '') return 0.0;
    static $cache = [];
    $cacheKey = $idUsuario . '|' . $idExercicio . '|' . $inicio->format('c') . '|' . $fim->format('c');
    if (array_key_exists($cacheKey, $cache)) return $cache[$cacheKey];
    $stmt = $pdo->prepare(
        "SELECT se.carga_snapshot, se.concluido AS exercicio_concluido, st.carga_realizada, st.concluida AS serie_concluida
         FROM sessoes_treino s
         JOIN sessoes_treino_exercicios se ON se.idsessao = s.idsessao
         LEFT JOIN sessoes_treino_series st ON st.idsessao_exercicio = se.idsessao_exercicio
         WHERE s.idusuario = :usuario
           AND s.status = 'concluido'
           AND COALESCE(s.data_fim, s.data_inicio) >= :inicio
           AND COALESCE(s.data_fim, s.data_inicio) <= :fim
           AND se.idexercicio = :exercicio"
    );
    $stmt->execute([
        ':usuario' => $idUsuario,
        ':inicio' => $inicio->format('Y-m-d H:i:sP'),
        ':fim' => $fim->format('Y-m-d H:i:sP'),
        ':exercicio' => $idExercicio,
    ]);
    $max = 0.0;
    foreach ($stmt->fetchAll() as $row) {
        $realizada = stridebr_db_bool($row['serie_concluida'] ?? false) ? dashboardCargaNumero((string) ($row['carga_realizada'] ?? '')) : null;
        $planejada = stridebr_db_bool($row['exercicio_concluido'] ?? false) ? dashboardCargaNumero((string) ($row['carga_snapshot'] ?? '')) : null;
        if ($realizada !== null) $max = max($max, $realizada);
        elseif ($planejada !== null) $max = max($max, $planejada);
    }
    return $cache[$cacheKey] = $max;
}

function dashboardCalcularProgressoMeta(PDO $pdo, string $idUsuario, array $meta): array
{
    [$inicio, $fim] = dashboardPeriodoMeta($meta);
    if ((string) ($meta['metrica'] ?? '') === 'carga_maxima') {
        $progresso = dashboardCargaMaximaMeta($pdo, $idUsuario, (string) ($meta['idexercicio'] ?? ''), $inicio, $fim);
        $alvo = (float) $meta['valor_alvo'];
        $percentualReal = $alvo > 0 ? ($progresso / $alvo) * 100 : 0.0;
        $restante = max(0.0, $alvo - $progresso);
        $atingida = $alvo > 0 && $progresso >= $alvo;
        if ($atingida && !empty($meta['idmeta'])) {
            try {
                if ((string) ($meta['periodo'] ?? '') === 'continuo' && empty($meta['concluida_em'])) {
                    $complete = $pdo->prepare('UPDATE metas_usuario SET concluida_em = NOW(), data_atualizacao = NOW() WHERE idmeta = :meta AND idusuario = :usuario AND concluida_em IS NULL');
                    $complete->execute([':meta' => $meta['idmeta'], ':usuario' => $idUsuario]);
                }
                $insert = $pdo->prepare(
                    'INSERT INTO metas_conclusoes (idconclusao, idmeta, periodo_inicio, periodo_fim, valor_atingido)
'
                    . 'VALUES (:id, :meta, :inicio, :fim, :valor)
'
                    . 'ON CONFLICT (idmeta, periodo_inicio, periodo_fim) DO UPDATE
'
                    . 'SET valor_atingido = EXCLUDED.valor_atingido
'
                    . 'WHERE EXCLUDED.valor_atingido > metas_conclusoes.valor_atingido'
                );
                $insert->execute([
                    ':id' => dashboardGerarId(),
                    ':meta' => $meta['idmeta'],
                    ':inicio' => $inicio->format('Y-m-d'),
                    ':fim' => $fim->format('Y-m-d'),
                    ':valor' => $progresso,
                ]);
            } catch (PDOException $e) {
                if ($e->getCode() !== '42P01') throw $e;
            }
        }
        return ['progresso' => $progresso, 'percentual' => min(100.0, max(0.0, $percentualReal)), 'percentual_real' => $percentualReal, 'restante' => $restante, 'atingida' => $atingida, 'periodo_inicio' => $inicio, 'periodo_fim' => $fim, 'expirada' => (string) ($meta['periodo'] ?? '') === 'personalizado' && $fim < new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo'))];
    }
    $sql =
        "SELECT COUNT(DISTINCT ra.idregistro) AS atividades,
                COUNT(DISTINCT DATE(ra.data_inicio AT TIME ZONE 'America/Sao_Paulo')) AS dias_ativos,
                COALESCE(SUM(va.valor_normalizado) FILTER (WHERE lower(cm.slug) = 'distancia'), 0) AS distancia_m,
                COALESCE(SUM(va.valor_normalizado) FILTER (WHERE lower(cm.slug) = 'duracao'), 0) AS duracao_s,
                COALESCE(SUM(va.valor_normalizado) FILTER (WHERE lower(cm.slug) IN ('elevacao', 'desnivel')), 0) AS elevacao_m
         FROM registros_atividade ra
         LEFT JOIN valores_atividade va ON va.idregistro = ra.idregistro
         LEFT JOIN campos_modelo cm ON cm.idcampo = va.idcampo
         WHERE ra.idusuario = :usuario
           AND ra.excluido_em IS NULL
           AND ra.status = 'concluido'
           AND ra.data_inicio >= :inicio
           AND ra.data_inicio <= :fim";
    $params = [
        ':usuario' => $idUsuario,
        ':inicio' => $inicio->format('Y-m-d H:i:sP'),
        ':fim' => $fim->format('Y-m-d H:i:sP'),
    ];
    if (!empty($meta['idmodalidade'])) {
        $sql .= ' AND ra.idmodalidade = :modalidade';
        $params[':modalidade'] = $meta['idmodalidade'];
    }
    static $aggregateCache = [];
    $aggregateKey = $idUsuario . '|' . $inicio->format('c') . '|' . $fim->format('c') . '|' . (string) ($meta['idmodalidade'] ?? '');
    if (array_key_exists($aggregateKey, $aggregateCache)) {
        $dados = $aggregateCache[$aggregateKey];
    } else {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $dados = $stmt->fetch() ?: [];
        $aggregateCache[$aggregateKey] = $dados;
    }

    $progresso = match ((string) $meta['metrica']) {
        'distancia' => ((float) ($dados['distancia_m'] ?? 0)) / 1000,
        'duracao' => ((float) ($dados['duracao_s'] ?? 0)) / 60,
        'elevacao' => (float) ($dados['elevacao_m'] ?? 0),
        'dias_ativos' => (float) ($dados['dias_ativos'] ?? 0),
        default => (float) ($dados['atividades'] ?? 0),
    };
    $alvo = (float) $meta['valor_alvo'];
    $percentualReal = $alvo > 0 ? ($progresso / $alvo) * 100 : 0.0;
    $restante = max(0.0, $alvo - $progresso);
    $atingida = $alvo > 0 && $progresso >= $alvo;

    if ($atingida && !empty($meta['idmeta'])) {
        try {
            if ((string) ($meta['periodo'] ?? '') === 'continuo' && empty($meta['concluida_em'])) {
                $complete = $pdo->prepare('UPDATE metas_usuario SET concluida_em = NOW(), data_atualizacao = NOW() WHERE idmeta = :meta AND idusuario = :usuario AND concluida_em IS NULL');
                $complete->execute([':meta' => $meta['idmeta'], ':usuario' => $idUsuario]);
            }
            $insert = $pdo->prepare(
                'INSERT INTO metas_conclusoes (idconclusao, idmeta, periodo_inicio, periodo_fim, valor_atingido)
                 VALUES (:id, :meta, :inicio, :fim, :valor)
                 ON CONFLICT (idmeta, periodo_inicio, periodo_fim) DO UPDATE
                 SET valor_atingido = EXCLUDED.valor_atingido
                 WHERE EXCLUDED.valor_atingido > metas_conclusoes.valor_atingido'
            );
            $insert->execute([
                ':id' => dashboardGerarId(),
                ':meta' => $meta['idmeta'],
                ':inicio' => $inicio->format('Y-m-d'),
                ':fim' => $fim->format('Y-m-d'),
                ':valor' => $progresso,
            ]);
        } catch (PDOException $e) {
            if ($e->getCode() !== '42P01') {
                throw $e;
            }
        }
    }

    return [
        'progresso' => $progresso,
        'percentual' => min(100.0, $percentualReal),
        'percentual_real' => $percentualReal,
        'restante' => $restante,
        'atingida' => $atingida,
        'periodo_inicio' => $inicio,
        'periodo_fim' => $fim,
        'expirada' => (string) ($meta['periodo'] ?? '') === 'personalizado' && $fim < new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')),
    ];
}

function dashboardListarMetas(PDO $pdo, string $idUsuario, bool $somenteAtivas = true): array
{
    if (!dashboardMetasDisponiveis($pdo)) {
        return [];
    }

    $sql = "SELECT g.idmeta, g.idmodalidade, g.idexercicio, g.nome, g.metrica, g.periodo, g.valor_alvo,
                   g.ativa, g.data_inicio, g.data_fim, g.data_criacao, g.data_atualizacao, g.concluida_em, g.arquivada_em,
                   m.nome AS modalidade_nome, m.slug AS modalidade_slug, e.nome AS exercicio_nome
            FROM metas_usuario g
            LEFT JOIN modalidades m ON m.idmodalidade = g.idmodalidade
            LEFT JOIN exercicios e ON e.idexercicio = g.idexercicio
            WHERE g.idusuario = :usuario";
    if ($somenteAtivas) {
        $sql .= ' AND g.ativa = TRUE';
    }
    $sql .= ' ORDER BY g.ativa DESC, g.data_atualizacao DESC, g.data_criacao DESC, g.idmeta';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':usuario' => $idUsuario]);
    $metas = $stmt->fetchAll();

    foreach ($metas as &$meta) {
        $meta = array_merge($meta, dashboardCalcularProgressoMeta($pdo, $idUsuario, $meta));
    }
    unset($meta);
    return $metas;
}

function dashboardListarConclusoesMetas(PDO $pdo, string $idUsuario, int $limite = 30): array
{
    try {
        $stmt = $pdo->prepare(
            "SELECT c.idconclusao, c.idmeta, c.periodo_inicio, c.periodo_fim, c.valor_atingido, c.atingida_em,
                    g.nome, g.metrica, g.periodo, g.valor_alvo, g.idexercicio, m.nome AS modalidade_nome, m.slug AS modalidade_slug, e.nome AS exercicio_nome
             FROM metas_conclusoes c
             JOIN metas_usuario g ON g.idmeta = c.idmeta
             LEFT JOIN modalidades m ON m.idmodalidade = g.idmodalidade
             LEFT JOIN exercicios e ON e.idexercicio = g.idexercicio
             WHERE g.idusuario = :usuario
             ORDER BY c.atingida_em DESC
             LIMIT :limite"
        );
        $stmt->bindValue(':usuario', $idUsuario);
        $stmt->bindValue(':limite', max(1, min(100, $limite)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        if ($e->getCode() === '42P01') {
            return [];
        }
        throw $e;
    }
}

function dashboardFormatarDuracao(float $segundos): string
{
    $segundos = max(0, (int) round($segundos));
    $horas = intdiv($segundos, 3600);
    $minutos = intdiv($segundos % 3600, 60);
    if ($horas > 0) {
        return $horas . 'h ' . str_pad((string) $minutos, 2, '0', STR_PAD_LEFT) . 'min';
    }
    return $minutos . ' min';
}

function dashboardFormatarNumero(float $valor, int $casas = 1): string
{
    return stridebr_format_number($valor, max(0, $casas), true);
}

function dashboardMetaUnidade(string $metrica): string
{
    return match ($metrica) {
        'distancia' => 'km',
        'duracao' => 'min',
        'elevacao' => 'm',
        'dias_ativos' => stridebr_t('home.unit_days'),
        'carga_maxima' => 'kg',
        default => stridebr_t('home.unit_activities'),
    };
}

function dashboardMetaMetrica(string $metrica): string
{
    return stridebr_t(match ($metrica) {
        'distancia' => 'home.metric_distance',
        'duracao' => 'home.metric_duration',
        'elevacao' => 'home.metric_elevation',
        'dias_ativos' => 'home.metric_active_days',
        'carga_maxima' => 'home.metric_max_load',
        default => 'home.metric_activities',
    });
}

function dashboardMetaPeriodo(string $periodo): string
{
    return stridebr_t(match ($periodo) {
        'mensal' => 'home.period_month',
        'anual' => 'home.period_year',
        'personalizado' => 'home.period_until_date',
        'continuo' => 'home.period_no_deadline',
        default => 'home.period_week',
    });
}

function dashboardMetaTitulo(array $meta): string
{
    $nome = trim((string) ($meta['nome'] ?? ''));
    if ($nome !== '') {
        return $nome;
    }
    if (($meta['metrica'] ?? '') === 'carga_maxima' && !empty($meta['exercicio_nome'])) return (string) $meta['exercicio_nome'] . ' · ' . stridebr_t('home.metric_max_load');
    $sport = !empty($meta['modalidade_slug']) ? stridebr_sport_name((string) $meta['modalidade_slug'], (string) ($meta['modalidade_nome'] ?? '')) : stridebr_t('home.all_sports');
    return $sport . ' · ' . dashboardMetaMetrica((string) ($meta['metrica'] ?? 'atividades'));
}

function dashboardMetaPrazoLabel(array $meta): string
{
    if (($meta['periodo'] ?? '') === 'personalizado' && !empty($meta['data_inicio']) && !empty($meta['data_fim'])) {
        $inicio = new DateTimeImmutable((string) $meta['data_inicio']);
        $fim = new DateTimeImmutable((string) $meta['data_fim']);
        return stridebr_format_date_short($inicio) . ' – ' . stridebr_format_date_short($fim);
    }
    if (($meta['periodo'] ?? '') === 'continuo') {
        if (!empty($meta['concluida_em'])) {
            return stridebr_t('home.goal_completed_on', ['date' => stridebr_format_date_short(new DateTimeImmutable((string) $meta['concluida_em']))]);
        }
        if (!empty($meta['data_inicio'])) {
            return stridebr_t('home.no_deadline_since', ['date' => stridebr_format_date_short(new DateTimeImmutable((string) $meta['data_inicio']))]);
        }
        return stridebr_t('home.no_deadline_short');
    }
    return dashboardMetaPeriodo((string) ($meta['periodo'] ?? 'semanal'));
}

function dashboardAtividadeSemanaApresentar(array $atividade, ?string $locale = null): array
{
    $slug = trim((string) ($atividade['modalidade_slug'] ?? ''));
    $sportName = stridebr_sport_name($slug, (string) ($atividade['modalidade_nome'] ?? ''), $locale);
    $storedTitle = trim((string) ($atividade['titulo'] ?? ''));
    $title = stridebr_present_activity_title($storedTitle !== '' ? $storedTitle : $sportName, $slug, $locale);
    $distanceM = is_numeric($atividade['distancia_m'] ?? null) ? max(0.0, (float) $atividade['distancia_m']) : 0.0;
    $durationS = is_numeric($atividade['duracao_s'] ?? null) ? max(0.0, (float) $atividade['duracao_s']) : 0.0;
    $context = atividadeContextoEsportivo($slug, (string) ($atividade['modalidade_familia_hub'] ?? ''), ['registered_m' => $distanceM]);
    $distanceLabel = $distanceM > 0 ? atividadeContextoFormatarDistancia($distanceM, $context, $locale) : '';
    $durationLabel = '';
    if ($durationS > 0) {
        $wholeMinute = abs(($durationS / 60.0) - round($durationS / 60.0)) < 0.000001;
        $durationLabel = $distanceM <= 0 && empty($context['is_track']) && $wholeMinute
            ? dashboardFormatarDuracao($durationS)
            : atividadeContextoFormatarTempo($durationS, $locale, $durationS >= 60);
    }
    $startedAt = null;
    try {
        if (!empty($atividade['data_inicio'])) $startedAt = new DateTimeImmutable((string) $atividade['data_inicio']);
    } catch (Throwable) {
        $startedAt = null;
    }
    $timeLabel = $startedAt ? $startedAt->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('H:i') : '';
    $metadata = array_values(array_filter([$timeLabel, $distanceLabel, $durationLabel], static fn(string $value): bool => $value !== ''));
    $id = trim((string) ($atividade['idregistro'] ?? ''));
    return [
        'idregistro' => $id,
        'titulo' => $title,
        'modalidade_slug' => $slug,
        'hora' => $timeLabel,
        'distancia' => $distanceLabel,
        'duracao' => $durationLabel,
        'metadata' => implode(' · ', $metadata),
        'aria_label' => implode(', ', array_values(array_filter([$title, ...$metadata], static fn(string $value): bool => $value !== ''))),
        'href' => $id !== '' ? '/user/atividades.php#atividade-' . rawurlencode($id) : '/user/atividades.php',
    ];
}

function dashboardMontarVisaoAtividades(array $dailyRows, ?DateTimeImmutable $today = null, int $weekOffset = 0): array
{
    $tz = new DateTimeZone('America/Sao_Paulo');
    $today = ($today ?? new DateTimeImmutable('today', $tz))->setTimezone($tz)->setTime(0, 0);
    $weekStart = $today->modify('-' . ((int) $today->format('N') - 1) . ' days')->modify(sprintf('%+d weeks', $weekOffset));
    $weekEnd = $weekStart->modify('+7 days');
    $rows = [];
    foreach ($dailyRows as $row) {
        $key = trim((string) ($row['dia'] ?? ''));
        if ($key !== '') $rows[$key] = $row;
    }

    $resumo = ['atividades' => 0, 'distancia_km' => 0.0, 'duracao_s' => 0.0, 'elevacao_m' => 0.0, 'dias_ativos' => 0];
    $dias = [];
    for ($i = 0; $i < 7; $i++) {
        $date = $weekStart->modify('+' . $i . ' days');
        $key = $date->format('Y-m-d');
        $row = $rows[$key] ?? [];
        $rawActivities = [];
        if (isset($row['atividades_itens']) && is_array($row['atividades_itens'])) {
            $rawActivities = array_values($row['atividades_itens']);
        } elseif (!empty($row['atividades_json'])) {
            $decoded = json_decode((string) $row['atividades_json'], true);
            if (is_array($decoded)) $rawActivities = array_values($decoded);
        }
        $activities = [];
        foreach ($rawActivities as $activity) {
            if (!is_array($activity)) continue;
            $activities[] = dashboardAtividadeSemanaApresentar($activity);
        }
        $modalidades = array_values(array_filter(array_map(static fn(array $item): string => (string) ($item['modalidade_slug'] ?? ''), $activities)));
        if ($modalidades === []) {
            if (isset($row['modalidades']) && is_array($row['modalidades'])) {
                $modalidades = array_values(array_filter(array_map('strval', $row['modalidades'])));
            } elseif (!empty($row['modalidades_json'])) {
                $decoded = json_decode((string) $row['modalidades_json'], true);
                if (is_array($decoded)) $modalidades = array_values(array_filter(array_map('strval', $decoded)));
            }
        }
        $activityCount = max(count($activities), max(0, (int) ($row['atividades'] ?? 0)));
        $dias[] = [
            'data' => $key,
            'rotulo' => stridebr_weekday_short($date),
            'data_label' => stridebr_format_date_weekday($date),
            'atividades' => $activityCount,
            'atividades_itens' => $activities,
            'modalidades' => $modalidades,
            'duracao_s' => max(0.0, (float) ($row['duracao_s'] ?? 0)),
            'hoje' => $key === $today->format('Y-m-d'),
            'futuro' => $date > $today,
        ];
        if ($date <= $today) {
            $resumo['atividades'] += $activityCount;
            $resumo['dias_ativos'] += $activityCount > 0 ? 1 : 0;
            $resumo['distancia_km'] += max(0.0, (float) ($row['distancia_m'] ?? 0)) / 1000;
            $resumo['duracao_s'] += max(0.0, (float) ($row['duracao_s'] ?? 0));
            $resumo['elevacao_m'] += max(0.0, (float) ($row['elevacao_m'] ?? 0));
        }
    }
    return ['resumo' => $resumo, 'dias' => $dias, 'inicio' => $weekStart, 'fim' => $weekEnd];
}

function dashboardVisaoAtividades(PDO $pdo, string $idUsuario, int $weekOffset = 0): array
{
    $tz = new DateTimeZone('America/Sao_Paulo');
    $today = new DateTimeImmutable('today', $tz);
    $weekStart = $today->modify('-' . ((int) $today->format('N') - 1) . ' days')->modify(sprintf('%+d weeks', $weekOffset));
    $weekEnd = $weekStart->modify('+7 days');
    $stmt = $pdo->prepare(
        "WITH atividade_dia AS (
            SELECT ra.idregistro,
                   ra.titulo,
                   ra.data_inicio,
                   (ra.data_inicio AT TIME ZONE 'America/Sao_Paulo')::date AS dia,
                   m.nome AS modalidade_nome,
                   m.slug AS modalidade_slug,
                   m.familia_hub AS modalidade_familia_hub,
                   COALESCE(SUM(va.valor_normalizado) FILTER (WHERE lower(cm.slug) = 'distancia'), 0) AS distancia_m,
                   COALESCE(NULLIF(GREATEST(0, EXTRACT(EPOCH FROM (COALESCE(MAX(ra.data_fim), MAX(ra.data_inicio)) - MAX(ra.data_inicio)))), 0), COALESCE(SUM(va.valor_normalizado) FILTER (WHERE lower(cm.slug) = 'duracao'), 0), 0) AS duracao_s,
                   COALESCE(SUM(va.valor_normalizado) FILTER (WHERE lower(cm.slug) IN ('elevacao', 'desnivel')), 0) AS elevacao_m
            FROM registros_atividade ra
            JOIN modalidades m ON m.idmodalidade = ra.idmodalidade
            LEFT JOIN valores_atividade va ON va.idregistro = ra.idregistro
            LEFT JOIN campos_modelo cm ON cm.idcampo = va.idcampo
            WHERE ra.idusuario = :usuario
              AND ra.excluido_em IS NULL
              AND ra.status = 'concluido'
              AND ra.data_inicio >= :inicio
              AND ra.data_inicio < :fim
            GROUP BY ra.idregistro, ra.titulo, ra.data_inicio, (ra.data_inicio AT TIME ZONE 'America/Sao_Paulo')::date, m.nome, m.slug, m.familia_hub
        )
        SELECT dia,
               COUNT(*) AS atividades,
               COALESCE(SUM(distancia_m), 0) AS distancia_m,
               COALESCE(SUM(duracao_s), 0) AS duracao_s,
               COALESCE(SUM(elevacao_m), 0) AS elevacao_m,
               json_agg(modalidade_slug ORDER BY data_inicio, idregistro) AS modalidades_json,
               json_agg(json_build_object(
                   'idregistro', idregistro,
                   'titulo', titulo,
                   'data_inicio', data_inicio,
                   'modalidade_nome', modalidade_nome,
                   'modalidade_slug', modalidade_slug,
                   'modalidade_familia_hub', modalidade_familia_hub,
                   'distancia_m', distancia_m,
                   'duracao_s', duracao_s
               ) ORDER BY data_inicio, idregistro) AS atividades_json
        FROM atividade_dia
        GROUP BY dia
        ORDER BY dia"
    );
    $stmt->execute([
        ':usuario' => $idUsuario,
        ':inicio' => $weekStart->format('Y-m-d 00:00:00P'),
        ':fim' => $weekEnd->format('Y-m-d 00:00:00P'),
    ]);
    return dashboardMontarVisaoAtividades($stmt->fetchAll(), $today, $weekOffset);
}

function dashboardResumoSemana(PDO $pdo, string $idUsuario): array
{
    $inicio = dashboardInicioPeriodo('semanal')->format('Y-m-d H:i:sP');
    $stmt = $pdo->prepare(
        "SELECT COUNT(DISTINCT ra.idregistro) AS atividades,
                COALESCE(SUM(va.valor_normalizado) FILTER (WHERE lower(cm.slug) = 'distancia'), 0) AS distancia_m,
                COALESCE(SUM(va.valor_normalizado) FILTER (WHERE lower(cm.slug) = 'duracao'), 0) AS duracao_s,
                COALESCE(SUM(va.valor_normalizado) FILTER (WHERE lower(cm.slug) IN ('elevacao', 'desnivel')), 0) AS elevacao_m
         FROM registros_atividade ra
         LEFT JOIN valores_atividade va ON va.idregistro = ra.idregistro
         LEFT JOIN campos_modelo cm ON cm.idcampo = va.idcampo
         WHERE ra.idusuario = :usuario
           AND ra.excluido_em IS NULL
           AND ra.status = 'concluido'
           AND ra.data_inicio >= :inicio"
    );
    $stmt->execute([':usuario' => $idUsuario, ':inicio' => $inicio]);
    $dados = $stmt->fetch() ?: [];
    return [
        'atividades' => (int) ($dados['atividades'] ?? 0),
        'distancia_km' => ((float) ($dados['distancia_m'] ?? 0)) / 1000,
        'duracao_s' => (float) ($dados['duracao_s'] ?? 0),
        'elevacao_m' => (float) ($dados['elevacao_m'] ?? 0),
    ];
}

function dashboardAtividadesSemana(PDO $pdo, string $idUsuario): array
{
    $tz = new DateTimeZone('America/Sao_Paulo');
    $today = new DateTimeImmutable('today', $tz);
    $start = $today->modify('-6 days');
    $stmt = $pdo->prepare(
        "SELECT ra.data_inicio::date AS dia,
                COUNT(DISTINCT ra.idregistro) AS atividades,
                COALESCE(SUM(va.valor_normalizado) FILTER (WHERE lower(cm.slug) = 'duracao'), 0) AS duracao_s
         FROM registros_atividade ra
         LEFT JOIN valores_atividade va ON va.idregistro = ra.idregistro
         LEFT JOIN campos_modelo cm ON cm.idcampo = va.idcampo
         WHERE ra.idusuario = :usuario
           AND ra.excluido_em IS NULL
           AND ra.status = 'concluido'
           AND ra.data_inicio >= :inicio
         GROUP BY ra.data_inicio::date
         ORDER BY ra.data_inicio::date"
    );
    $stmt->execute([':usuario' => $idUsuario, ':inicio' => $start->format('Y-m-d 00:00:00P')]);
    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $rows[(string) $row['dia']] = $row;
    }

    $dias = [];
    for ($i = 0; $i < 7; $i++) {
        $date = $start->modify('+' . $i . ' days');
        $key = $date->format('Y-m-d');
        $row = $rows[$key] ?? [];
        $dias[] = [
            'data' => $key,
            'rotulo' => stridebr_weekday_short($date),
            'atividades' => (int) ($row['atividades'] ?? 0),
            'duracao_s' => (float) ($row['duracao_s'] ?? 0),
            'hoje' => $key === $today->format('Y-m-d'),
        ];
    }
    return $dias;
}

function dashboardAtividadesRecentes(PDO $pdo, string $idUsuario, int $limite = 5): array
{
    $limite = max(1, min(8, $limite));
    $stmt = $pdo->prepare(
        "SELECT ra.idregistro, ra.titulo, ra.data_inicio,
                m.nome AS modalidade_nome, m.slug AS modalidade_slug,
                COALESCE(SUM(va.valor_normalizado) FILTER (WHERE lower(cm.slug) = 'distancia'), 0) AS distancia_m,
                COALESCE(SUM(va.valor_normalizado) FILTER (WHERE lower(cm.slug) = 'duracao'), 0) AS duracao_s,
                COALESCE(SUM(va.valor_normalizado) FILTER (WHERE lower(cm.slug) IN ('elevacao', 'desnivel')), 0) AS elevacao_m
         FROM registros_atividade ra
         JOIN modalidades m ON m.idmodalidade = ra.idmodalidade
         LEFT JOIN valores_atividade va ON va.idregistro = ra.idregistro
         LEFT JOIN campos_modelo cm ON cm.idcampo = va.idcampo
         WHERE ra.idusuario = :usuario AND ra.excluido_em IS NULL AND ra.status = 'concluido'
         GROUP BY ra.idregistro, m.nome, m.slug
         ORDER BY ra.data_inicio DESC
         LIMIT {$limite}"
    );
    $stmt->execute([':usuario' => $idUsuario]);
    return $stmt->fetchAll();
}

function dashboardTreinosProximos(PDO $pdo, string $idUsuario, int $limite = 5): array
{
    require_once __DIR__ . '/cronograma.php';
    $now = new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo'));
    $rows = cronogramaListarOcorrenciasConciliadas($pdo, $idUsuario, $now->format('Y-m-d'), $now->modify('+14 days')->format('Y-m-d'));
    $proximos = [];
    $seen = [];
    foreach ($rows as $row) {
        if (!empty($row['concluido']) || ($row['status'] ?? '') === 'cancelado') continue;
        $candidate = new DateTimeImmutable($row['data_treino'] . ' ' . $row['hora_inicio'], $now->getTimezone());
        if ($candidate < $now) continue;
        // Identity is the occurrence, never its title: two workouts can share a name.
        $key = $row['idtreino'] . ':' . $row['data_original'];
        if (isset($seen[$key])) continue;
        $seen[$key] = true;
        $row['proxima_data'] = $candidate;
        $proximos[] = $row;
    }
    usort($proximos, static fn(array $a, array $b): int => [$a['proxima_data'], $a['idtreino']] <=> [$b['proxima_data'], $b['idtreino']]);
    return array_slice($proximos, 0, max(1, min(8, $limite)));
}

function dashboardPreferenciasUsuario(PDO $pdo, string $idUsuario): array
{
    $stmt = $pdo->prepare('SELECT preferenciasusuario FROM usuarios WHERE idusuario = :usuario LIMIT 1');
    $stmt->execute([':usuario' => $idUsuario]);
    $raw = $stmt->fetchColumn();
    return is_array($raw) ? $raw : (json_decode((string) ($raw ?: '{}'), true) ?: []);
}

function dashboardPreferenciasHome(PDO $pdo, string $idUsuario): array
{
    $allowed = ['progress', 'goals', 'upcoming', 'recent'];
    $prefs = dashboardPreferenciasUsuario($pdo, $idUsuario);
    $home = is_array($prefs['dashboard_home'] ?? null) ? $prefs['dashboard_home'] : [];
    $order = is_array($home['order'] ?? null) ? array_values(array_intersect($home['order'], $allowed)) : [];
    if ($order === []) {
        $goals = is_array($prefs['goals'] ?? null) ? $prefs['goals'] : [];
        $tracking = is_array($prefs['tracking'] ?? null) ? $prefs['tracking'] : [];
        if (in_array('organizar', $goals, true) || in_array('rotina', $goals, true)) {
            $order = ['upcoming', 'progress', 'goals', 'recent'];
        } elseif (array_intersect($goals, ['evolucao', 'condicionamento', 'prova']) || array_intersect($tracking, ['frequencia', 'duracao', 'distancia', 'elevacao', 'carga'])) {
            $order = ['progress', 'recent', 'goals', 'upcoming'];
        } elseif (in_array('metas', $tracking, true)) {
            $order = ['goals', 'progress', 'upcoming', 'recent'];
        } elseif (in_array('lazer', $goals, true)) {
            $order = ['recent', 'progress', 'upcoming', 'goals'];
        }
    }
    foreach ($allowed as $item) {
        if (!in_array($item, $order, true)) $order[] = $item;
    }
    $hidden = is_array($home['hidden'] ?? null) ? array_values(array_intersect($home['hidden'], $allowed)) : [];
    return ['order' => $order, 'hidden' => $hidden];
}

function dashboardSalvarPreferenciasHome(PDO $pdo, string $idUsuario, array $order, array $hidden): array
{
    $allowed = ['progress', 'goals', 'upcoming', 'recent'];
    $order = array_values(array_unique(array_intersect($order, $allowed)));
    foreach ($allowed as $item) {
        if (!in_array($item, $order, true)) $order[] = $item;
    }
    $hidden = array_values(array_unique(array_intersect($hidden, $allowed)));

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT preferenciasusuario FROM usuarios WHERE idusuario = :usuario FOR UPDATE');
        $stmt->execute([':usuario' => $idUsuario]);
        $raw = $stmt->fetchColumn();
        $prefs = is_array($raw) ? $raw : (json_decode((string) ($raw ?: '{}'), true) ?: []);
        $prefs['dashboard_home'] = ['order' => $order, 'hidden' => $hidden];
        $update = $pdo->prepare('UPDATE usuarios SET preferenciasusuario = CAST(:prefs AS jsonb) WHERE idusuario = :usuario');
        $update->execute([
            ':prefs' => json_encode($prefs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            ':usuario' => $idUsuario,
        ]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    return ['order' => $order, 'hidden' => $hidden];
}

function dashboardSessaoAtiva(PDO $pdo, string $idUsuario): array
{
    try {
        $stmt = $pdo->prepare("SELECT idsessao, idtreino_origem, idagendamento_origem, titulo_snapshot, data_inicio,
                                      data_ocorrencia_origem, data_ocorrencia_planejada, hora_ocorrencia_planejada
                               FROM sessoes_treino
                               WHERE idusuario = :usuario AND status = 'ativo'
                               ORDER BY data_inicio DESC
                               LIMIT 1");
        $stmt->execute([':usuario' => $idUsuario]);
        return $stmt->fetch() ?: [];
    } catch (Throwable) {
        return [];
    }
}

function dashboardContextoHoje(PDO $pdo, string $idUsuario): array
{
    $tz = new DateTimeZone('America/Sao_Paulo');
    $now = new DateTimeImmutable('now', $tz);
    $date = $now->format('Y-m-d');
    $active = dashboardSessaoAtiva($pdo, $idUsuario);

    if ($active !== []) {
        return [
            'state' => 'active',
            'date' => $date,
            'active' => $active,
            'items' => [],
            'primary' => null,
            'completed' => [],
        ];
    }

    $hasSchedule = false;
    try {
        $stmt = $pdo->prepare('SELECT EXISTS (SELECT 1 FROM cronogramas WHERE idusuario = :usuario AND ativo = TRUE)');
        $stmt->execute([':usuario' => $idUsuario]);
        $hasSchedule = stridebr_db_bool($stmt->fetchColumn());
    } catch (Throwable) {
        $hasSchedule = false;
    }

    $items = [];
    if (function_exists('cronogramaListarOcorrencias')) {
        try {
            $items = cronogramaListarOcorrencias($pdo, $idUsuario, $date, $date);
            if ($items !== []) {
                $ids = array_values(array_unique(array_filter(array_map(static fn(array $item): string => (string) ($item['idtreino'] ?? ''), $items))));
                if ($ids !== []) {
                    $params = [':usuario' => $idUsuario, ':data_planned' => $date, ':data_origin' => $date, ':data_actual' => $date];
                    $placeholders = [];
                    foreach ($ids as $index => $id) {
                        $key = ':treino' . $index;
                        $placeholders[] = $key;
                        $params[$key] = $id;
                    }
                    $doneStmt = $pdo->prepare("SELECT idregistro, idtreino_cronograma, data_ocorrencia_origem, data_ocorrencia_planejada, data_inicio
                                               FROM registros_atividade
                                               WHERE idusuario = :usuario AND excluido_em IS NULL AND status = 'concluido'
                                                 AND idtreino_cronograma IN (" . implode(',', $placeholders) . ")
                                                 AND (data_ocorrencia_planejada = CAST(:data_planned AS date)
                                                      OR data_ocorrencia_origem = CAST(:data_origin AS date)
                                                      OR ((data_ocorrencia_planejada IS NULL AND data_ocorrencia_origem IS NULL)
                                                          AND (data_inicio AT TIME ZONE 'America/Sao_Paulo')::date = CAST(:data_actual AS date)))");
                    $doneStmt->execute($params);
                    $doneRows = $doneStmt->fetchAll();
                    foreach ($items as $index => $item) {
                        $items[$index]['concluido'] = false;
                        $items[$index]['idregistro'] = '';
                        foreach ($doneRows as $done) {
                            if ((string) ($done['idtreino_cronograma'] ?? '') !== (string) ($item['idtreino'] ?? '')) continue;
                            $planned = trim((string) ($done['data_ocorrencia_planejada'] ?? ''));
                            $original = trim((string) ($done['data_ocorrencia_origem'] ?? ''));
                            if ($planned !== '' && $planned !== (string) ($item['data_treino'] ?? '')) continue;
                            if ($planned === '' && $original !== '' && $original !== (string) ($item['data_original'] ?? '')) continue;
                            $items[$index]['concluido'] = true;
                            $items[$index]['idregistro'] = (string) $done['idregistro'];
                            break;
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            error_log('StrideBR dashboard today occurrences: ' . $e->getMessage());
        }
    }

    $scheduled = [];
    try {
        $stmt = $pdo->prepare("SELECT idagendamento, idtreino_origem, data_treino, hora_inicio, titulo, status
                               FROM treinos_agendados
                               WHERE idatleta = :usuario AND data_treino = :data AND status IN ('publicado','concluido')
                               ORDER BY hora_inicio NULLS LAST, data_criacao");
        $stmt->execute([':usuario' => $idUsuario, ':data' => $date]);
        foreach ($stmt->fetchAll() as $row) {
            $scheduled[] = [
                'kind' => 'scheduled',
                'idagendamento' => (string) $row['idagendamento'],
                'idtreino' => (string) ($row['idtreino_origem'] ?? ''),
                'titulo' => (string) $row['titulo'],
                'hora_inicio' => $row['hora_inicio'] ? substr((string) $row['hora_inicio'], 0, 5) : '',
                'data_treino' => (string) $row['data_treino'],
                'concluido' => (string) $row['status'] === 'concluido',
            ];
        }
    } catch (Throwable) {
        $scheduled = [];
    }

    $normalized = [];
    foreach ($items as $item) {
        $normalized[] = [
            'kind' => 'schedule',
            'idtreino' => (string) ($item['idtreino'] ?? ''),
            'idcronograma' => (string) ($item['idcronograma'] ?? ''),
            'cronograma_nome' => (string) ($item['cronograma_nome'] ?? ''),
            'titulo' => (string) ($item['titulo'] ?? 'Treino'),
            'hora_inicio' => substr((string) ($item['hora_planejada'] ?? $item['hora_inicio'] ?? ''), 0, 5),
            'data_original' => (string) ($item['data_original'] ?? $date),
            'data_treino' => (string) ($item['data_planejada'] ?? $item['data_treino'] ?? $date),
            'idregistro' => (string) ($item['idregistro'] ?? ''),
            'concluido' => !empty($item['concluido']),
        ];
    }
    $normalized = array_merge($normalized, $scheduled);

    usort($normalized, static function (array $a, array $b): int {
        $aTime = $a['hora_inicio'] !== '' ? $a['hora_inicio'] : '23:59';
        $bTime = $b['hora_inicio'] !== '' ? $b['hora_inicio'] : '23:59';
        return [$a['concluido'] ? 1 : 0, $aTime, $a['titulo']] <=> [$b['concluido'] ? 1 : 0, $bTime, $b['titulo']];
    });

    $pending = array_values(array_filter($normalized, static fn(array $item): bool => empty($item['concluido'])));
    $completed = array_values(array_filter($normalized, static fn(array $item): bool => !empty($item['concluido'])));
    $primary = $pending[0] ?? null;

    return [
        'state' => $primary !== null ? 'planned' : ($completed !== [] ? 'completed' : ($hasSchedule ? 'rest' : 'free')),
        'date' => $date,
        'has_schedule' => $hasSchedule,
        'active' => [],
        'items' => $normalized,
        'primary' => $primary,
        'completed' => $completed,
    ];
}
