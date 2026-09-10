<?php

declare(strict_types=1);

/*
 * Transport helpers for the public API.  This file intentionally does not load
 * app.php: app.php starts the browser session and is reserved for the web UI.
 */

function stridebr_api_lower(string $value): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
}

function stridebr_api_id(int $length = 21): string
{
    $bytes = random_bytes((int) ceil($length * 3 / 4) + 2);
    return substr(rtrim(strtr(base64_encode($bytes), '+/', '-_'), '='), 0, $length);
}

function stridebr_api_token(int $bytes = 32): string
{
    return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
}

function stridebr_api_token_hash(string $token): string
{
    return hash('sha256', $token);
}

function stridebr_api_bool(mixed $value): bool
{
    return $value === true || $value === 1 || $value === '1' || $value === 't' || $value === 'true';
}

function stridebr_api_iso(?string $value): ?string
{
    if ($value === null || trim($value) === '') return null;
    try {
        return (new DateTimeImmutable($value))->format(DateTimeInterface::ATOM);
    } catch (Throwable) {
        return null;
    }
}

function stridebr_api_response(int $status, array $payload = []): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    if ($status !== 204) echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    exit;
}

function stridebr_api_error(int $status, string $code, string $message, array $fields = []): never
{
    $error = ['code' => $code, 'message' => $message];
    if ($fields !== []) $error['fields'] = $fields;
    stridebr_api_response($status, ['error' => $error]);
}

function stridebr_api_require_method(string ...$methods): void
{
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (!in_array($method, $methods, true)) {
        header('Allow: ' . implode(', ', $methods));
        stridebr_api_error(405, 'method_not_allowed', 'Método HTTP não permitido.');
    }
}

function stridebr_api_json_input(int $maxBytes = 65536): array
{
    $length = $_SERVER['CONTENT_LENGTH'] ?? null;
    if ($length !== null && (!ctype_digit((string) $length) || (int) $length > $maxBytes)) {
        stridebr_api_error(413, 'payload_too_large', 'A requisição excede o limite permitido.');
    }
    $raw = file_get_contents('php://input', false, null, 0, $maxBytes + 1);
    if (!is_string($raw) || strlen($raw) > $maxBytes) stridebr_api_error(413, 'payload_too_large', 'A requisição excede o limite permitido.');
    if ($raw === '') return [];
    $contentType = strtolower(trim(explode(';', (string) ($_SERVER['CONTENT_TYPE'] ?? 'application/json'))[0]));
    if ($contentType !== 'application/json') stridebr_api_error(415, 'unsupported_media_type', 'Use application/json.');
    try {
        $data = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        stridebr_api_error(400, 'invalid_json', 'JSON inválido.');
    }
    if (!is_array($data) || array_is_list($data)) stridebr_api_error(400, 'invalid_json', 'O corpo JSON precisa ser um objeto.');
    return $data;
}

function stridebr_api_bearer_token(): ?string
{
    $header = trim((string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? ''));
    if (!preg_match('/^Bearer[ ]+([A-Za-z0-9_-]{32,256})$/D', $header, $match)) return null;
    return $match[1];
}

function stridebr_api_user(PDO $pdo): array
{
    $token = stridebr_api_bearer_token();
    if ($token === null) stridebr_api_error(401, 'authentication_required', 'Token de acesso ausente ou inválido.');
    $stmt = $pdo->prepare(
        "SELECT s.idsessao, s.idusuario, s.sessao_versao, u.nomeusuario, u.nome_exibicao, u.username, u.emailusuario, u.fotousuario, u.papelusuario, u.statususuario, u.onboarding_concluido, u.preferenciasusuario, u.sessao_versao AS usuario_sessao_versao
         FROM api_sessoes s JOIN usuarios u ON u.idusuario = s.idusuario
         WHERE s.access_token_hash = :hash AND s.revogado_em IS NULL AND s.access_expira_em > NOW() LIMIT 1"
    );
    $stmt->execute([':hash' => stridebr_api_token_hash($token)]);
    $user = $stmt->fetch();
    if (!$user || $user['statususuario'] !== 'Ativo' || (int) $user['sessao_versao'] !== (int) $user['usuario_sessao_versao']) {
        if ($user) {
            $revoke = $pdo->prepare('UPDATE api_sessoes SET revogado_em = COALESCE(revogado_em, NOW()) WHERE idsessao = :id');
            $revoke->execute([':id' => $user['idsessao']]);
        }
        stridebr_api_error(401, 'token_expired', 'Token de acesso expirado ou revogado.');
    }
    $pdo->prepare('UPDATE api_sessoes SET ultimo_uso_em = NOW() WHERE idsessao = :id')->execute([':id' => $user['idsessao']]);
    return $user;
}

function stridebr_api_user_payload(array $user): array
{
    $preferences = is_array($user['preferenciasusuario'] ?? null) ? $user['preferenciasusuario'] : json_decode((string) ($user['preferenciasusuario'] ?? '{}'), true);
    return [
        'id' => (string) $user['idusuario'],
        'name' => (string) ($user['nome_exibicao'] ?: $user['nomeusuario']),
        'username' => $user['username'] !== null ? (string) $user['username'] : null,
        'email' => (string) $user['emailusuario'],
        'avatar_url' => $user['fotousuario'] !== null && $user['fotousuario'] !== '' ? (string) $user['fotousuario'] : null,
        'role' => (string) $user['papelusuario'],
        'onboarding_complete' => stridebr_api_bool($user['onboarding_concluido'] ?? false),
        'preferences' => is_array($preferences) ? $preferences : [],
    ];
}

function stridebr_api_issue_session(PDO $pdo, array $user, array $device = [], ?string $family = null, ?string $rotatedFrom = null): array
{
    $access = stridebr_api_token(32);
    $refresh = stridebr_api_token(48);
    $sessionId = stridebr_api_id();
    $family = $family ?: $sessionId;
    $deviceId = substr(trim((string) ($device['device_id'] ?? '')), 0, 128) ?: null;
    $deviceName = substr(trim((string) ($device['device_name'] ?? '')), 0, 120) ?: null;
    $platform = stridebr_api_lower(trim((string) ($device['platform'] ?? '')));
    if (!in_array($platform, ['android', 'ios', 'web', 'other'], true)) $platform = null;
    $stmt = $pdo->prepare("INSERT INTO api_sessoes (idsessao, idusuario, access_token_hash, refresh_token_hash, familia_sessao, identificador_dispositivo, nome_dispositivo, plataforma, sessao_versao, access_expira_em, refresh_expira_em, rotacionado_de) VALUES (:id, :user, :access, :refresh, :family, :device_id, :device_name, :platform, :version, NOW() + INTERVAL '15 minutes', NOW() + INTERVAL '30 days', :rotated_from)");
    $stmt->execute([':id'=>$sessionId, ':user'=>$user['idusuario'], ':access'=>stridebr_api_token_hash($access), ':refresh'=>stridebr_api_token_hash($refresh), ':family'=>$family, ':device_id'=>$deviceId, ':device_name'=>$deviceName, ':platform'=>$platform, ':version'=>(int) ($user['sessao_versao'] ?? 1), ':rotated_from'=>$rotatedFrom]);
    return ['access_token'=>$access, 'refresh_token'=>$refresh, 'token_type'=>'Bearer', 'expires_in'=>900, 'refresh_expires_in'=>2592000];
}

function stridebr_api_login(PDO $pdo, array $payload): array
{
    $email = stridebr_api_lower(trim((string) ($payload['email'] ?? '')));
    $password = (string) ($payload['password'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') stridebr_api_error(422, 'validation_error', 'E-mail e senha são obrigatórios.', ['email'=>'Informe um e-mail válido.', 'password'=>'Informe a senha.']);
    $scope = 'api_login';
    $limitHash = hash('sha256', $scope . "\0" . $email);
    $blocked = $pdo->prepare('SELECT 1 FROM auth_rate_limits WHERE chave_hash = :hash AND bloqueado_ate > NOW()');
    $blocked->execute([':hash'=>$limitHash]);
    if ($blocked->fetchColumn()) stridebr_api_error(429, 'rate_limited', 'Tente novamente mais tarde.');
    $stmt = $pdo->prepare('SELECT idusuario, nomeusuario, nome_exibicao, username, emailusuario, fotousuario, papelusuario, statususuario, onboarding_concluido, preferenciasusuario, sessao_versao, senhausuario FROM usuarios WHERE lower(emailusuario) = :email LIMIT 1');
    $stmt->execute([':email'=>$email]);
    $user = $stmt->fetch();
    if (!$user || $user['statususuario'] !== 'Ativo' || !password_verify($password, (string) $user['senhausuario'])) {
        $failure = $pdo->prepare("INSERT INTO auth_rate_limits (chave_hash, escopo, tentativas, janela_inicio, bloqueado_ate, atualizado_em) VALUES (:hash, :scope, 1, NOW(), NULL, NOW()) ON CONFLICT (chave_hash) DO UPDATE SET tentativas = CASE WHEN auth_rate_limits.janela_inicio <= NOW() - INTERVAL '15 minutes' THEN 1 ELSE auth_rate_limits.tentativas + 1 END, janela_inicio = CASE WHEN auth_rate_limits.janela_inicio <= NOW() - INTERVAL '15 minutes' THEN NOW() ELSE auth_rate_limits.janela_inicio END, bloqueado_ate = CASE WHEN auth_rate_limits.janela_inicio > NOW() - INTERVAL '15 minutes' AND auth_rate_limits.tentativas + 1 >= 10 THEN NOW() + INTERVAL '15 minutes' ELSE NULL END, atualizado_em = NOW()");
        $failure->execute([':hash'=>$limitHash, ':scope'=>$scope]);
        stridebr_api_error(401, 'invalid_credentials', 'E-mail ou senha inválidos.');
    }
    $pdo->prepare('DELETE FROM auth_rate_limits WHERE chave_hash = :hash')->execute([':hash'=>$limitHash]);
    unset($user['senhausuario']);
    $pdo->prepare('UPDATE usuarios SET ultimologin = NOW() WHERE idusuario = :id')->execute([':id'=>$user['idusuario']]);
    return ['tokens'=>stridebr_api_issue_session($pdo, $user, $payload), 'user'=>stridebr_api_user_payload($user)];
}

function stridebr_api_refresh(PDO $pdo, array $payload): array
{
    $token = trim((string) ($payload['refresh_token'] ?? ''));
    if (!preg_match('/^[A-Za-z0-9_-]{48,256}$/D', $token)) stridebr_api_error(401, 'invalid_refresh_token', 'Refresh token inválido.');
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT s.*, u.nomeusuario, u.nome_exibicao, u.username, u.emailusuario, u.fotousuario, u.papelusuario, u.statususuario, u.onboarding_concluido, u.preferenciasusuario, u.sessao_versao AS usuario_sessao_versao FROM api_sessoes s JOIN usuarios u ON u.idusuario = s.idusuario WHERE s.refresh_token_hash = :hash FOR UPDATE");
        $stmt->execute([':hash'=>stridebr_api_token_hash($token)]);
        $session = $stmt->fetch();
        if (!$session || $session['revogado_em'] !== null || strtotime((string) $session['refresh_expira_em']) <= time() || $session['statususuario'] !== 'Ativo' || (int) $session['sessao_versao'] !== (int) $session['usuario_sessao_versao']) {
            if ($session) $pdo->prepare('UPDATE api_sessoes SET revogado_em = COALESCE(revogado_em, NOW()) WHERE familia_sessao = :family')->execute([':family'=>$session['familia_sessao']]);
            $pdo->commit();
            stridebr_api_error(401, 'invalid_refresh_token', 'Refresh token expirado ou revogado.');
        }
        $pdo->prepare('UPDATE api_sessoes SET revogado_em = NOW() WHERE idsessao = :id')->execute([':id'=>$session['idsessao']]);
        $tokens = stridebr_api_issue_session($pdo, $session, $payload + ['device_id'=>$session['identificador_dispositivo'], 'device_name'=>$session['nome_dispositivo'], 'platform'=>$session['plataforma']], (string) $session['familia_sessao'], (string) $session['idsessao']);
        $pdo->commit();
        return ['tokens'=>$tokens, 'user'=>stridebr_api_user_payload($session)];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function stridebr_api_activity_summary(array $row): array
{
    return ['id'=>(string)$row['idregistro'], 'title'=>(string)($row['titulo'] ?: $row['modalidade_nome']), 'sport'=>['id'=>(string)$row['idmodalidade'], 'slug'=>(string)$row['modalidade_slug'], 'name'=>(string)$row['modalidade_nome']], 'started_at'=>stridebr_api_iso((string)$row['data_inicio']), 'ended_at'=>stridebr_api_iso($row['data_fim'] !== null ? (string)$row['data_fim'] : null), 'status'=>(string)$row['status'], 'visibility'=>(string)$row['visibilidade'], 'origin'=>(string)$row['origem'], 'perceived_effort'=>$row['esforco_percebido'] !== null ? (int)$row['esforco_percebido'] : null];
}

function stridebr_api_activity_detail(PDO $pdo, string $activityId, string $userId): array
{
    $stmt = $pdo->prepare('SELECT ra.idregistro, ra.idmodalidade, ra.titulo, ra.observacoes, ra.data_inicio, ra.data_fim, ra.status, ra.visibilidade, ra.origem, ra.esforco_percebido, ra.usa_trechos, m.nome AS modalidade_nome, m.slug AS modalidade_slug FROM registros_atividade ra JOIN modalidades m ON m.idmodalidade = ra.idmodalidade WHERE ra.idregistro = :id AND ra.idusuario = :user AND ra.excluido_em IS NULL LIMIT 1');
    $stmt->execute([':id'=>$activityId, ':user'=>$userId]);
    $row = $stmt->fetch();
    if (!$row) return [];
    $detail = stridebr_api_activity_summary($row);
    $detail['notes'] = $row['observacoes'] !== null ? (string)$row['observacoes'] : null;
    $detail['uses_segments'] = stridebr_api_bool($row['usa_trechos'] ?? false);
    $units = $pdo->prepare('SELECT ua.idunidade_atividade, ua.ordem, ua.tipo_unidade, ua.rotulo, ua.observacoes, ua.distancia_metros, ua.duracao_segundos, ua.elevacao_m, COALESCE(um.idmodalidade, :sport) AS idmodalidade, COALESCE(um.nome, :sport_name) AS modalidade_nome, COALESCE(um.slug, :sport_slug) AS modalidade_slug FROM unidades_atividade ua LEFT JOIN modalidades um ON um.idmodalidade = ua.idmodalidade WHERE ua.idregistro = :id ORDER BY ua.ordem');
    $units->execute([':id'=>$activityId, ':sport'=>$row['idmodalidade'], ':sport_name'=>$row['modalidade_nome'], ':sport_slug'=>$row['modalidade_slug']]);
    $detail['segments'] = array_map(static fn(array $unit): array => ['id'=>(string)$unit['idunidade_atividade'], 'order'=>(int)$unit['ordem'], 'type'=>(string)$unit['tipo_unidade'], 'label'=>$unit['rotulo'] !== null ? (string)$unit['rotulo'] : null, 'notes'=>$unit['observacoes'] !== null ? (string)$unit['observacoes'] : null, 'distance_m'=>$unit['distancia_metros'] !== null ? (float)$unit['distancia_metros'] : null, 'duration_s'=>$unit['duracao_segundos'] !== null ? (float)$unit['duracao_segundos'] : null, 'elevation_m'=>$unit['elevacao_m'] !== null ? (float)$unit['elevacao_m'] : null, 'sport'=>['id'=>(string)$unit['idmodalidade'], 'slug'=>(string)$unit['modalidade_slug'], 'name'=>(string)$unit['modalidade_nome']]], $units->fetchAll());
    $equipment = $pdo->prepare('SELECT e.idequipamento, e.nome, e.categoria FROM registros_atividade_equipamentos link JOIN equipamentos_usuario e ON e.idequipamento = link.idequipamento WHERE link.idregistro = :id AND e.idusuario = :user ORDER BY e.nome');
    $equipment->execute([':id'=>$activityId, ':user'=>$userId]);
    $detail['equipment'] = array_map(static fn(array $item): array => ['id'=>(string)$item['idequipamento'], 'name'=>(string)$item['nome'], 'category'=>(string)$item['categoria']], $equipment->fetchAll());
    return $detail;
}
