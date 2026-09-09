<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/app.php';

function stridebr_marketing_schema_available(PDO $pdo): bool
{
    static $cache = null;
    if (is_bool($cache)) return $cache;
    try {
        $cache = (bool) $pdo->query("SELECT to_regclass('stridebr.marketing_campanhas') IS NOT NULL AND to_regclass('stridebr.marketing_eventos_aquisicao') IS NOT NULL")->fetchColumn();
    } catch (Throwable) {
        $cache = false;
    }
    return $cache;
}

function stridebr_marketing_slug(string $value): string
{
    $value = stridebr_lower(trim($value));
    if (class_exists('Normalizer')) {
        $normalized = Normalizer::normalize($value, Normalizer::FORM_D);
        if (is_string($normalized)) $value = $normalized;
    }
    $value = preg_replace('/\p{Mn}+/u', '', $value) ?? $value;
    if (function_exists('iconv')) {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($ascii) && $ascii !== '') $value = $ascii;
    }
    $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? '';
    $value = trim($value, '_');
    return substr($value, 0, 80);
}

function stridebr_marketing_validate_slug(string $value): string
{
    $slug = stridebr_marketing_slug($value);
    if ($slug === '' || strlen($slug) < 2 || preg_match('/^[a-z0-9][a-z0-9_\-]{1,79}$/', $slug) !== 1) {
        throw new InvalidArgumentException('Código inválido. Use letras minúsculas, números, hífen ou underscore.');
    }
    return $slug;
}

function stridebr_marketing_clean_text(mixed $value, int $limit): ?string
{
    $text = trim((string) $value);
    if ($text === '') return null;
    $text = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $text) ?? '';
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
    if (function_exists('mb_substr')) return mb_substr($text, 0, $limit, 'UTF-8');
    return substr($text, 0, $limit);
}

function stridebr_marketing_internal_destination(string $value): string
{
    $value = trim($value);
    if ($value === '') $value = '/';
    if (strlen($value) > 500 || preg_match('/[\x00-\x1F\x7F\\\\]/', $value)) {
        throw new InvalidArgumentException('Destino inválido.');
    }
    $parts = parse_url($value);
    if ($parts === false || isset($parts['scheme']) || isset($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
        throw new InvalidArgumentException('O destino precisa ser uma rota interna do StrideBR.');
    }
    $path = (string) ($parts['path'] ?? '');
    $decodedPath = rawurldecode($path);
    if ($path === '' || !str_starts_with($path, '/') || str_starts_with($path, '//') || str_starts_with($decodedPath, '//') || preg_match('/[\x00-\x1F\x7F\\\\]/', $decodedPath)) {
        throw new InvalidArgumentException('O destino precisa começar com /.');
    }
    if ($decodedPath === '/r' || str_starts_with($decodedPath, '/r/')) {
        throw new InvalidArgumentException('O destino não pode apontar para outro redirect de campanha.');
    }
    return $value;
}

function stridebr_marketing_campaign_types(): array
{
    return ['offline', 'paid_social', 'organic_social', 'event', 'other'];
}

function stridebr_marketing_campaign_statuses(): array
{
    return ['planejada', 'ativa', 'encerrada'];
}

function stridebr_marketing_date_or_null(mixed $value, string $message): ?string
{
    $date = trim((string) $value);
    if ($date === '') return null;
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$parsed || ($errors !== false && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0)) || $parsed->format('Y-m-d') !== $date) {
        throw new InvalidArgumentException($message);
    }
    return $date;
}


function stridebr_marketing_metrics(PDO $pdo, ?string $campaignId = null): array
{
    $where = '';
    $params = [];
    if ($campaignId !== null && $campaignId !== '') {
        $where = 'WHERE a.idcampanha = :campanha';
        $params[':campanha'] = $campaignId;
    }
    $stmt = $pdo->prepare(
        "SELECT
            COUNT(e.idevento) FILTER (WHERE e.nome = 'landing_view') AS acessos,
            COUNT(e.idevento) FILTER (WHERE e.nome = 'landing_view' AND e.chave_atribuicao IS NOT NULL) AS atribuidos,
            COUNT(e.idevento) FILTER (WHERE e.nome = 'landing_view' AND e.chave_atribuicao IS NULL) AS diretos,
            COUNT(e.idevento) FILTER (WHERE e.nome = 'signup_start') AS signup_iniciados,
            COUNT(e.idevento) FILTER (WHERE e.nome = 'signup_complete') AS signup_concluidos,
            COUNT(e.idevento) FILTER (WHERE e.nome = 'activation') AS ativacoes
         FROM marketing_eventos_aquisicao e
         LEFT JOIN marketing_atribuicoes a ON a.chave_hash = e.chave_atribuicao
         {$where}"
    );
    $stmt->execute($params);
    $row = $stmt->fetch() ?: [];
    $metrics = [];
    foreach (['acessos', 'atribuidos', 'diretos', 'signup_iniciados', 'signup_concluidos', 'ativacoes'] as $key) {
        $metrics[$key] = (int) ($row[$key] ?? 0);
    }
    $metrics['conversao_cadastro'] = $metrics['acessos'] > 0 ? ($metrics['signup_concluidos'] / $metrics['acessos']) * 100 : 0.0;
    $metrics['conversao_ativacao'] = $metrics['signup_concluidos'] > 0 ? ($metrics['ativacoes'] / $metrics['signup_concluidos']) * 100 : 0.0;
    return $metrics;
}

function stridebr_marketing_campaigns_with_metrics(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT c.*,
            COUNT(e.idevento) FILTER (WHERE e.nome = 'landing_view') AS acessos,
            COUNT(e.idevento) FILTER (WHERE e.nome = 'signup_start') AS signup_iniciados,
            COUNT(e.idevento) FILTER (WHERE e.nome = 'signup_complete') AS signup_concluidos,
            COUNT(e.idevento) FILTER (WHERE e.nome = 'activation') AS ativacoes
         FROM marketing_campanhas c
         LEFT JOIN marketing_atribuicoes a ON a.idcampanha = c.idcampanha
         LEFT JOIN marketing_eventos_aquisicao e ON e.chave_atribuicao = a.chave_hash
         GROUP BY c.idcampanha
         ORDER BY c.data_criacao DESC, c.nome"
    );
    return $stmt->fetchAll() ?: [];
}

function stridebr_marketing_placements_with_metrics(PDO $pdo, string $campaignId): array
{
    $stmt = $pdo->prepare(
        "SELECT p.*,
            COUNT(e.idevento) FILTER (WHERE e.nome = 'landing_view') AS acessos,
            COUNT(e.idevento) FILTER (WHERE e.nome = 'signup_start') AS signup_iniciados,
            COUNT(e.idevento) FILTER (WHERE e.nome = 'signup_complete') AS signup_concluidos,
            COUNT(e.idevento) FILTER (WHERE e.nome = 'activation') AS ativacoes
         FROM marketing_placements p
         LEFT JOIN marketing_atribuicoes a ON a.idplacement = p.idplacement
         LEFT JOIN marketing_eventos_aquisicao e ON e.chave_atribuicao = a.chave_hash
         WHERE p.idcampanha = :campanha
         GROUP BY p.idplacement
         ORDER BY p.nome"
    );
    $stmt->execute([':campanha' => $campaignId]);
    return $stmt->fetchAll() ?: [];
}

function stridebr_marketing_cookie_name(): string
{
    return 'stridebr_acq';
}

function stridebr_marketing_session_id(): string
{
    stridebr_start_session();
    $value = (string) ($_SESSION['StrideBRMarketingSession'] ?? '');
    if (preg_match('/^[a-f0-9]{32}$/', $value) !== 1) {
        $value = bin2hex(random_bytes(16));
        $_SESSION['StrideBRMarketingSession'] = $value;
    }
    return $value;
}

function stridebr_marketing_cookie_token(): ?string
{
    $value = trim((string) ($_COOKIE[stridebr_marketing_cookie_name()] ?? ''));
    return preg_match('/^[a-f0-9]{36}$/', $value) === 1 ? $value : null;
}

function stridebr_marketing_set_cookie(string $token): void
{
    if (headers_sent()) return;
    setcookie(stridebr_marketing_cookie_name(), $token, [
        'expires' => time() + 30 * 86400,
        'path' => '/',
        'secure' => stridebr_secure_cookie(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE[stridebr_marketing_cookie_name()] = $token;
}

function stridebr_marketing_attribution_by_hash(PDO $pdo, string $hash): ?array
{
    if (!stridebr_marketing_schema_available($pdo) || preg_match('/^[a-f0-9]{64}$/', $hash) !== 1) return null;
    $stmt = $pdo->prepare('SELECT * FROM marketing_atribuicoes WHERE chave_hash = :hash LIMIT 1');
    $stmt->execute([':hash' => $hash]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function stridebr_marketing_current_attribution(PDO $pdo): ?array
{
    $sessionHash = (string) ($_SESSION['StrideBRMarketingAttributionHash'] ?? '');
    if (preg_match('/^[a-f0-9]{64}$/', $sessionHash) === 1) {
        $row = stridebr_marketing_attribution_by_hash($pdo, $sessionHash);
        if ($row) return $row;
    }
    $token = stridebr_marketing_cookie_token();
    if ($token === null) return null;
    $hash = hash('sha256', $token);
    $row = stridebr_marketing_attribution_by_hash($pdo, $hash);
    if ($row) {
        stridebr_start_session();
        $_SESSION['StrideBRMarketingAttributionHash'] = $hash;
    }
    return $row;
}

function stridebr_marketing_lookup_campaign(PDO $pdo, string $code): ?array
{
    $code = stridebr_marketing_slug($code);
    if ($code === '' || !stridebr_marketing_schema_available($pdo)) return null;
    $stmt = $pdo->prepare('SELECT * FROM marketing_campanhas WHERE codigo = :codigo LIMIT 1');
    $stmt->execute([':codigo' => $code]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function stridebr_marketing_lookup_placement(PDO $pdo, string $code, bool $redirectEligibleOnly = false): ?array
{
    $code = stridebr_marketing_slug($code);
    if ($code === '' || !stridebr_marketing_schema_available($pdo)) return null;
    $sql = 'SELECT p.*, c.codigo AS campanha_codigo, c.nome AS campanha_nome, c.tipo AS campanha_tipo, c.status AS campanha_status, c.inicio AS campanha_inicio, c.fim AS campanha_fim FROM marketing_placements p JOIN marketing_campanhas c ON c.idcampanha = p.idcampanha WHERE p.codigo = :codigo';
    if ($redirectEligibleOnly) {
        $sql .= " AND p.ativo = TRUE AND c.status = 'ativa' AND (c.inicio IS NULL OR c.inicio <= CURRENT_DATE) AND (c.fim IS NULL OR c.fim >= CURRENT_DATE)";
    }
    $sql .= ' LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':codigo' => $code]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function stridebr_marketing_capture_first_touch(PDO $pdo, array $params = [], ?array $placement = null): ?array
{
    if (!stridebr_marketing_schema_available($pdo)) return null;
    stridebr_start_session();
    $existing = stridebr_marketing_current_attribution($pdo);
    if ($existing) return $existing;

    $utmSource = stridebr_marketing_clean_text($params['utm_source'] ?? null, 80);
    $utmMedium = stridebr_marketing_clean_text($params['utm_medium'] ?? null, 80);
    $utmCampaign = stridebr_marketing_clean_text($params['utm_campaign'] ?? null, 80);
    $utmContent = stridebr_marketing_clean_text($params['utm_content'] ?? null, 120);
    $utmTerm = stridebr_marketing_clean_text($params['utm_term'] ?? null, 120);
    $campaign = null;

    if ($placement !== null) {
        $campaign = [
            'idcampanha' => $placement['idcampanha'],
            'codigo' => $placement['campanha_codigo'] ?? null,
            'tipo' => $placement['campanha_tipo'] ?? null,
        ];
        $utmSource ??= 'qr';
        $utmMedium ??= (string) ($placement['campanha_tipo'] ?? 'offline');
        $utmCampaign ??= (string) ($placement['campanha_codigo'] ?? '');
        $utmContent ??= (string) ($placement['codigo'] ?? '');
    } elseif ($utmCampaign !== null) {
        $campaign = stridebr_marketing_lookup_campaign($pdo, $utmCampaign);
        if ($campaign && $utmContent !== null) {
            $candidate = stridebr_marketing_lookup_placement($pdo, $utmContent, false);
            if ($candidate && (string) $candidate['idcampanha'] === (string) $campaign['idcampanha']) $placement = $candidate;
        }
    }

    $hasExplicitSource = $placement !== null || $utmSource !== null || $utmMedium !== null || $utmCampaign !== null || $utmContent !== null || $utmTerm !== null;
    if (!$hasExplicitSource) return null;

    $token = bin2hex(random_bytes(18));
    $hash = hash('sha256', $token);
    $stmt = $pdo->prepare('INSERT INTO marketing_atribuicoes (chave_hash, idcampanha, idplacement, utm_source, utm_medium, utm_campaign, utm_content, utm_term) VALUES (:hash, :campanha, :placement, :source, :medium, :campaign, :content, :term)');
    $stmt->execute([
        ':hash' => $hash,
        ':campanha' => $campaign['idcampanha'] ?? ($placement['idcampanha'] ?? null),
        ':placement' => $placement['idplacement'] ?? null,
        ':source' => $utmSource,
        ':medium' => $utmMedium,
        ':campaign' => $utmCampaign,
        ':content' => $utmContent,
        ':term' => $utmTerm,
    ]);
    $_SESSION['StrideBRMarketingAttributionHash'] = $hash;
    stridebr_marketing_set_cookie($token);
    return stridebr_marketing_attribution_by_hash($pdo, $hash);
}

function stridebr_marketing_attribution_for_user(PDO $pdo, string $userId): ?array
{
    if (!stridebr_marketing_schema_available($pdo) || trim($userId) === '') return null;
    $stmt = $pdo->prepare('SELECT * FROM marketing_atribuicoes WHERE idusuario = :usuario LIMIT 1');
    $stmt->execute([':usuario' => $userId]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function stridebr_marketing_link_user(PDO $pdo, string $userId): ?array
{
    if (!stridebr_marketing_schema_available($pdo) || trim($userId) === '') return null;
    $existing = stridebr_marketing_attribution_for_user($pdo, $userId);
    if ($existing) return $existing;
    $attribution = stridebr_marketing_current_attribution($pdo);
    if (!$attribution) return null;
    try {
        $stmt = $pdo->prepare('UPDATE marketing_atribuicoes SET idusuario = :usuario, vinculada_em = COALESCE(vinculada_em, NOW()) WHERE chave_hash = :hash AND idusuario IS NULL');
        $stmt->execute([':usuario' => $userId, ':hash' => $attribution['chave_hash']]);
    } catch (PDOException $e) {
        if ($e->getCode() !== '23505') throw $e;
    }
    return stridebr_marketing_attribution_for_user($pdo, $userId) ?? $attribution;
}

function stridebr_marketing_event_key(string $name, ?string $userId = null, ?string $attributionHash = null): string
{
    if ($userId !== null && in_array($name, ['signup_complete', 'activation'], true)) {
        return hash('sha256', 'user|' . $userId . '|' . $name);
    }
    if ($name === 'landing_view') {
        $sourceKey = is_string($attributionHash) && preg_match('/^[a-f0-9]{64}$/', $attributionHash) === 1
            ? $attributionHash
            : 'direct';
        return hash('sha256', 'session|' . stridebr_marketing_session_id() . '|landing_view|' . $sourceKey);
    }
    return hash('sha256', 'session|' . stridebr_marketing_session_id() . '|' . $name);
}

function stridebr_marketing_record_event(PDO $pdo, string $name, ?string $userId = null, ?string $path = null): bool
{
    if (!stridebr_marketing_schema_available($pdo)) return false;
    if (!in_array($name, ['landing_view', 'signup_start', 'signup_complete', 'activation'], true)) {
        throw new InvalidArgumentException('Evento de aquisição inválido.');
    }
    stridebr_start_session();
    $attribution = $userId !== null ? stridebr_marketing_attribution_for_user($pdo, $userId) : null;
    if (!$attribution) $attribution = stridebr_marketing_current_attribution($pdo);
    $cleanPath = stridebr_marketing_clean_text($path ?? (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/'), 300);
    $stmt = $pdo->prepare('INSERT INTO marketing_eventos_aquisicao (chave_evento, chave_atribuicao, idusuario, nome, caminho) VALUES (:event_key, :attribution, :usuario, :nome, :caminho) ON CONFLICT (chave_evento) DO NOTHING');
    $stmt->execute([
        ':event_key' => stridebr_marketing_event_key($name, $userId, isset($attribution['chave_hash']) ? (string) $attribution['chave_hash'] : null),
        ':attribution' => $attribution['chave_hash'] ?? null,
        ':usuario' => $userId,
        ':nome' => $name,
        ':caminho' => $cleanPath,
    ]);
    return $stmt->rowCount() === 1;
}

function stridebr_marketing_capture_request(PDO $pdo, array $params): ?array
{
    return stridebr_marketing_capture_first_touch($pdo, [
        'utm_source' => $params['utm_source'] ?? null,
        'utm_medium' => $params['utm_medium'] ?? null,
        'utm_campaign' => $params['utm_campaign'] ?? null,
        'utm_content' => $params['utm_content'] ?? null,
        'utm_term' => $params['utm_term'] ?? null,
    ]);
}

function stridebr_marketing_signup_start(PDO $pdo, array $params = []): void
{
    try {
        stridebr_marketing_capture_request($pdo, $params);
        stridebr_marketing_record_event($pdo, 'signup_start', null, '/signup.php');
    } catch (Throwable $e) {
        error_log('StrideBR acquisition signup_start failed: ' . get_class($e));
    }
}

function stridebr_marketing_signup_complete(PDO $pdo, string $userId): void
{
    try {
        stridebr_marketing_link_user($pdo, $userId);
        stridebr_marketing_record_event($pdo, 'signup_complete', $userId, '/signup.php');
    } catch (Throwable $e) {
        error_log('StrideBR acquisition signup_complete failed: ' . get_class($e));
    }
}

function stridebr_marketing_activation(PDO $pdo, string $userId, string $path = '/user/atividades.php'): void
{
    try {
        stridebr_marketing_record_event($pdo, 'activation', $userId, $path);
    } catch (Throwable $e) {
        error_log('StrideBR acquisition activation failed: ' . get_class($e));
    }
}

function stridebr_marketing_campaign_create(PDO $pdo, string $actorId, array $input): string
{
    $name = stridebr_marketing_clean_text($input['nome'] ?? null, 140) ?? '';
    if ($name === '') throw new InvalidArgumentException('Informe o nome da campanha.');
    $code = stridebr_marketing_validate_slug((string) ($input['codigo'] ?? ''));
    $type = (string) ($input['tipo'] ?? 'other');
    $status = (string) ($input['status'] ?? 'planejada');
    if (!in_array($type, stridebr_marketing_campaign_types(), true)) throw new InvalidArgumentException('Tipo de campanha inválido.');
    if (!in_array($status, stridebr_marketing_campaign_statuses(), true)) throw new InvalidArgumentException('Status de campanha inválido.');
    $start = stridebr_marketing_date_or_null($input['inicio'] ?? null, 'Data inicial inválida.');
    $end = stridebr_marketing_date_or_null($input['fim'] ?? null, 'Data final inválida.');
    if ($start !== null && $end !== null && $end < $start) throw new InvalidArgumentException('A data final não pode ser anterior à inicial.');
    $id = stridebr_generate_id();
    $stmt = $pdo->prepare('INSERT INTO marketing_campanhas (idcampanha, nome, codigo, tipo, descricao, inicio, fim, status, criado_por) VALUES (:id, :nome, :codigo, :tipo, :descricao, :inicio, :fim, :status, :ator)');
    $stmt->execute([
        ':id' => $id,
        ':nome' => $name,
        ':codigo' => $code,
        ':tipo' => $type,
        ':descricao' => stridebr_marketing_clean_text($input['descricao'] ?? null, 3000),
        ':inicio' => $start,
        ':fim' => $end,
        ':status' => $status,
        ':ator' => $actorId,
    ]);
    return $id;
}

function stridebr_marketing_campaign_update(PDO $pdo, string $campaignId, array $input): void
{
    $name = stridebr_marketing_clean_text($input['nome'] ?? null, 140) ?? '';
    if ($name === '') throw new InvalidArgumentException('Informe o nome da campanha.');
    $code = stridebr_marketing_validate_slug((string) ($input['codigo'] ?? ''));
    $type = (string) ($input['tipo'] ?? 'other');
    $status = (string) ($input['status'] ?? 'planejada');
    if (!in_array($type, stridebr_marketing_campaign_types(), true)) throw new InvalidArgumentException('Tipo de campanha inválido.');
    if (!in_array($status, stridebr_marketing_campaign_statuses(), true)) throw new InvalidArgumentException('Status de campanha inválido.');
    $start = stridebr_marketing_date_or_null($input['inicio'] ?? null, 'Data inicial inválida.');
    $end = stridebr_marketing_date_or_null($input['fim'] ?? null, 'Data final inválida.');
    if ($start !== null && $end !== null && $end < $start) throw new InvalidArgumentException('A data final não pode ser anterior à inicial.');
    $stmt = $pdo->prepare('UPDATE marketing_campanhas SET nome=:nome, codigo=:codigo, tipo=:tipo, descricao=:descricao, inicio=:inicio, fim=:fim, status=:status, data_atualizacao=NOW() WHERE idcampanha=:id');
    $stmt->execute([
        ':id' => $campaignId,
        ':nome' => $name,
        ':codigo' => $code,
        ':tipo' => $type,
        ':descricao' => stridebr_marketing_clean_text($input['descricao'] ?? null, 3000),
        ':inicio' => $start,
        ':fim' => $end,
        ':status' => $status,
    ]);
    if ($stmt->rowCount() !== 1) throw new RuntimeException('Campanha não encontrada.');
}

function stridebr_marketing_placement_create(PDO $pdo, string $campaignId, array $input): string
{
    $name = stridebr_marketing_clean_text($input['nome'] ?? null, 160) ?? '';
    if ($name === '') throw new InvalidArgumentException('Informe o nome do placement.');
    $code = stridebr_marketing_validate_slug((string) ($input['codigo'] ?? ''));
    $destination = stridebr_marketing_internal_destination((string) ($input['destino'] ?? '/'));
    $id = stridebr_generate_id();
    $stmt = $pdo->prepare('INSERT INTO marketing_placements (idplacement, idcampanha, nome, codigo, subtipo, destino, ativo, observacao) VALUES (:id, :campanha, :nome, :codigo, :subtipo, :destino, :ativo, :observacao)');
    $stmt->bindValue(':id', $id);
    $stmt->bindValue(':campanha', $campaignId);
    $stmt->bindValue(':nome', $name);
    $stmt->bindValue(':codigo', $code);
    $stmt->bindValue(':subtipo', stridebr_marketing_clean_text($input['subtipo'] ?? null, 60));
    $stmt->bindValue(':destino', $destination);
    $stmt->bindValue(':ativo', !isset($input['ativo']) || (string) $input['ativo'] === '1', PDO::PARAM_BOOL);
    $stmt->bindValue(':observacao', stridebr_marketing_clean_text($input['observacao'] ?? null, 3000));
    $stmt->execute();
    return $id;
}

function stridebr_marketing_placement_update(PDO $pdo, string $placementId, string $campaignId, array $input): void
{
    $name = stridebr_marketing_clean_text($input['nome'] ?? null, 160) ?? '';
    if ($name === '') throw new InvalidArgumentException('Informe o nome do placement.');
    $code = stridebr_marketing_validate_slug((string) ($input['codigo'] ?? ''));
    $destination = stridebr_marketing_internal_destination((string) ($input['destino'] ?? '/'));
    $stmt = $pdo->prepare('UPDATE marketing_placements SET nome=:nome, codigo=:codigo, subtipo=:subtipo, destino=:destino, ativo=:ativo, observacao=:observacao, data_atualizacao=NOW() WHERE idplacement=:id AND idcampanha=:campanha');
    $stmt->bindValue(':id', $placementId);
    $stmt->bindValue(':campanha', $campaignId);
    $stmt->bindValue(':nome', $name);
    $stmt->bindValue(':codigo', $code);
    $stmt->bindValue(':subtipo', stridebr_marketing_clean_text($input['subtipo'] ?? null, 60));
    $stmt->bindValue(':destino', $destination);
    $stmt->bindValue(':ativo', (string) ($input['ativo'] ?? '0') === '1', PDO::PARAM_BOOL);
    $stmt->bindValue(':observacao', stridebr_marketing_clean_text($input['observacao'] ?? null, 3000));
    $stmt->execute();
    if ($stmt->rowCount() !== 1) throw new RuntimeException('Placement não encontrado.');
}
