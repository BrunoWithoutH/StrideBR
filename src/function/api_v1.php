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

function stridebr_api_authorization_header(): string
{
    $header = trim((string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? ''));
    if ($header !== '') return $header;
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        if (is_array($headers)) {
            foreach ($headers as $name => $value) {
                if (strcasecmp((string) $name, 'Authorization') === 0) return trim((string) $value);
            }
        }
    }
    return '';
}

function stridebr_api_bearer_token(): ?string
{
    $header = stridebr_api_authorization_header();
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

function stridebr_api_logout(PDO $pdo, string $sessionId): void
{
    $stmt = $pdo->prepare('UPDATE api_sessoes SET revogado_em = COALESCE(revogado_em, NOW()) WHERE idsessao = :id');
    $stmt->execute([':id' => $sessionId]);
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


function stridebr_api_idempotency_key(): string
{
    $key = trim((string) ($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? ''));
    if ($key === '' && function_exists('getallheaders')) {
        $headers = getallheaders();
        if (is_array($headers)) {
            foreach ($headers as $name => $value) {
                if (strcasecmp((string) $name, 'Idempotency-Key') === 0) {
                    $key = trim((string) $value);
                    break;
                }
            }
        }
    }
    if (strlen($key) < 8 || strlen($key) > 128 || preg_match('/^[A-Za-z0-9._:-]+$/D', $key) !== 1) {
        throw new InvalidArgumentException('Idempotency-Key inválida. Use entre 8 e 128 caracteres ASCII seguros.');
    }
    return $key;
}

function stridebr_api_activity_mobile_recording(PDO $pdo, string $userId, array $payload, string $idempotencyKey): array
{
    require_once __DIR__ . '/gps_web.php';
    $sport = trim((string) ($payload['sport'] ?? ''));
    if ($sport === '') throw new InvalidArgumentException('Informe a modalidade em sport.');
    $selected = null;
    foreach (gpsWebRouteModalities($pdo, $userId) as $candidate) {
        if ((string) ($candidate['idmodalidade'] ?? '') === $sport || (string) ($candidate['slug'] ?? '') === $sport) {
            $selected = $candidate;
            break;
        }
    }
    if ($selected === null) throw new InvalidArgumentException('A modalidade informada não está disponível para gravação GPS.');

    $startedRaw = trim((string) ($payload['started_at'] ?? ''));
    $endedRaw = trim((string) ($payload['ended_at'] ?? ''));
    if ($startedRaw === '' || $endedRaw === '') throw new InvalidArgumentException('started_at e ended_at são obrigatórios.');
    foreach ([$startedRaw, $endedRaw] as $timestamp) {
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/D', $timestamp) !== 1) {
            throw new InvalidArgumentException('started_at e ended_at devem usar ISO 8601 com offset.');
        }
    }
    try {
        $started = new DateTimeImmutable($startedRaw);
        $ended = new DateTimeImmutable($endedRaw);
    } catch (Throwable) {
        throw new InvalidArgumentException('started_at e ended_at devem usar ISO 8601 com offset.');
    }
    if ($ended < $started) throw new InvalidArgumentException('ended_at não pode ser anterior a started_at.');

    $metrics = is_array($payload['metrics'] ?? null) ? $payload['metrics'] : [];
    $gps = is_array($payload['gps'] ?? null) ? $payload['gps'] : [];
    $privacy = is_array($payload['privacy'] ?? null) ? $payload['privacy'] : [];
    foreach (['distance_m', 'duration_s', 'elevation_gain_m', 'elevation_min_m', 'elevation_max_m'] as $field) {
        if (array_key_exists($field, $metrics) && $metrics[$field] !== null && !is_numeric($metrics[$field])) {
            throw new InvalidArgumentException('metrics.' . $field . ' precisa ser numérico.');
        }
    }
    foreach (['measured_distance_m', 'accuracy_avg_m', 'accuracy_best_m', 'accuracy_worst_m'] as $field) {
        if (array_key_exists($field, $gps) && $gps[$field] !== null && !is_numeric($gps[$field])) {
            throw new InvalidArgumentException('gps.' . $field . ' precisa ser numérico.');
        }
    }
    foreach (['hide_route_start_m', 'hide_route_end_m'] as $field) {
        if (array_key_exists($field, $privacy) && filter_var($privacy[$field], FILTER_VALIDATE_INT) === false) {
            throw new InvalidArgumentException('privacy.' . $field . ' precisa ser inteiro.');
        }
        if (array_key_exists($field, $privacy) && ((int) $privacy[$field] < 0 || (int) $privacy[$field] > 10000)) {
            throw new InvalidArgumentException('privacy.' . $field . ' deve ficar entre 0 e 10000 metros.');
        }
    }
    $points = is_array($gps['points'] ?? null) ? $gps['points'] : [];
    if ($points === []) throw new InvalidArgumentException('gps.points é obrigatório.');

    $duration = is_numeric($metrics['duration_s'] ?? null)
        ? (float) $metrics['duration_s']
        : max(0.001, (float) ($ended->format('U.u') - $started->format('U.u')));
    if ($duration <= 0) throw new InvalidArgumentException('metrics.duration_s precisa ser maior que zero.');

    $recordingKey = hash('sha256', "mobile-v1\0" . $userId . "\0" . $idempotencyKey);
    $recording = [
        'recording_id' => $recordingKey,
        'idmodalidade' => (string) $selected['idmodalidade'],
        'title' => trim((string) ($payload['title'] ?? '')),
        'notes' => trim((string) ($payload['notes'] ?? '')),
        'visibility' => trim((string) ($payload['visibility'] ?? '')),
        'effort' => $payload['perceived_effort'] ?? '',
        'started_at_ms' => (int) round((float) $started->format('U.u') * 1000),
        'ended_at_ms' => (int) round((float) $ended->format('U.u') * 1000),
        'duration_s' => $duration,
        'distance_m' => $metrics['distance_m'] ?? null,
        'elevation_gain_m' => $metrics['elevation_gain_m'] ?? null,
        'elevation_min_m' => $metrics['elevation_min_m'] ?? null,
        'elevation_max_m' => $metrics['elevation_max_m'] ?? null,
        'measured_distance_m' => $gps['measured_distance_m'] ?? null,
        'points' => $points,
        'segments' => is_array($payload['segments'] ?? null) ? $payload['segments'] : [],
        'hide_route_start_m' => $privacy['hide_route_start_m'] ?? 0,
        'hide_route_end_m' => $privacy['hide_route_end_m'] ?? 0,
        'points_received' => $gps['points_received'] ?? count($points),
        'points_rejected' => $gps['points_rejected'] ?? 0,
        'accuracy_avg_m' => $gps['accuracy_avg_m'] ?? null,
        'accuracy_best_m' => $gps['accuracy_best_m'] ?? null,
        'accuracy_worst_m' => $gps['accuracy_worst_m'] ?? null,
        'visibility_gaps' => $gps['visibility_gaps'] ?? 0,
        'user_adjusted' => !empty($payload['user_adjusted']),
        'client_source' => 'app',
    ];
    return $recording;
}

function stridebr_api_mark_mobile_activity_source(PDO $pdo, string $activityId, string $userId): void
{
    $stmt = $pdo->prepare("UPDATE registros_atividade SET origem_provedor = 'stridebr_android' WHERE idregistro = :id AND idusuario = :user AND origem = 'gps' AND origem_provedor IS NULL");
    $stmt->execute([':id' => $activityId, ':user' => $userId]);
}

function stridebr_api_create_activity(PDO $pdo, string $userId, array $payload, string $idempotencyKey): array
{
    require_once __DIR__ . '/gps_web.php';
    $recording = stridebr_api_activity_mobile_recording($pdo, $userId, $payload, $idempotencyKey);
    $workoutId = trim((string) ($payload['workout_id'] ?? ''));
    $workoutLink = $workoutId !== '' ? stridebr_api_workout_prepare_activity_link($pdo, $userId, $workoutId, (string) $recording['idmodalidade']) : null;
    $recordingKey = gpsWebRecordingKey($recording);
    $existing = gpsWebFindExistingRecording($pdo, $userId, $recordingKey);
    if ($existing !== null) {
        stridebr_api_mark_mobile_activity_source($pdo, $existing, $userId);
        if ($workoutLink !== null) stridebr_api_workout_link_activity($pdo, $userId, $existing, $workoutLink);
        return ['id' => $existing, 'activity' => stridebr_api_activity_detail($pdo, $existing, $userId), 'reused' => true];
    }

    $activityPayload = gpsWebBuildActivityPayload($pdo, $userId, $recording);
    if ($workoutLink !== null) $activityPayload = stridebr_api_workout_apply_activity_payload($activityPayload, $workoutLink);
    $meta = $activityPayload['_gps_meta'];
    unset($activityPayload['_gps_meta']);
    $pdo->beginTransaction();
    try {
        $id = atividadeSalvarRegistro($pdo, $userId, $activityPayload);
        stridebr_api_mark_mobile_activity_source($pdo, $id, $userId);
        gpsWebSaveMetadata($pdo, $id, $meta);
        if ($workoutLink !== null) stridebr_api_workout_link_activity($pdo, $userId, $id, $workoutLink);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($e instanceof PDOException && $e->getCode() === '23505') {
            $existing = gpsWebFindExistingRecording($pdo, $userId, $recordingKey);
            if ($existing !== null) {
                stridebr_api_mark_mobile_activity_source($pdo, $existing, $userId);
                if ($workoutLink !== null) stridebr_api_workout_link_activity($pdo, $userId, $existing, $workoutLink);
                return ['id' => $existing, 'activity' => stridebr_api_activity_detail($pdo, $existing, $userId), 'reused' => true];
            }
        }
        throw $e;
    }
    return ['id' => $id, 'activity' => stridebr_api_activity_detail($pdo, $id, $userId), 'reused' => false];
}

function stridebr_api_list_activities(PDO $pdo, string $userId, array $filters = []): array
{
    $page = max(1, min(100000, (int) ($filters['page'] ?? 1)));
    $limit = max(1, min(100, (int) ($filters['limit'] ?? 25)));
    $where = ['ra.idusuario = :user', 'ra.excluido_em IS NULL'];
    $params = [':user' => $userId];
    $sport = trim((string) ($filters['sport'] ?? ''));
    if ($sport !== '') {
        $where[] = 'm.slug = :sport';
        $params[':sport'] = $sport;
    }
    $from = trim((string) ($filters['from'] ?? ''));
    if ($from !== '') {
        try { $from = (new DateTimeImmutable($from))->format(DateTimeInterface::ATOM); }
        catch (Throwable) { throw new InvalidArgumentException('Parâmetro from inválido.'); }
        $where[] = 'ra.data_inicio >= :from';
        $params[':from'] = $from;
    }
    $to = trim((string) ($filters['to'] ?? ''));
    if ($to !== '') {
        try { $to = (new DateTimeImmutable($to))->format(DateTimeInterface::ATOM); }
        catch (Throwable) { throw new InvalidArgumentException('Parâmetro to inválido.'); }
        $where[] = 'ra.data_inicio < :to';
        $params[':to'] = $to;
    }
    $q = trim((string) ($filters['q'] ?? ''));
    if ($q !== '') {
        $where[] = 'ra.titulo ILIKE :q';
        $params[':q'] = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
    }
    $condition = implode(' AND ', $where);
    $count = $pdo->prepare("SELECT COUNT(*) FROM registros_atividade ra JOIN modalidades m ON m.idmodalidade = ra.idmodalidade WHERE $condition");
    $count->execute($params);
    $total = (int) $count->fetchColumn();
    $sql = "WITH page_rows AS (
                SELECT ra.idregistro,ra.idmodalidade,ra.titulo,ra.data_inicio,ra.data_fim,ra.status,ra.visibilidade,ra.origem,ra.origem_provedor,ra.esforco_percebido,ra.usa_trechos,
                       m.nome AS modalidade_nome,m.slug AS modalidade_slug
                FROM registros_atividade ra
                JOIN modalidades m ON m.idmodalidade=ra.idmodalidade
                WHERE $condition
                ORDER BY ra.data_inicio DESC,ra.idregistro DESC
                LIMIT :limit OFFSET :offset
            )
            SELECT ra.idregistro,ra.idmodalidade,ra.titulo,ra.data_inicio,ra.data_fim,ra.status,ra.visibilidade,ra.origem,ra.origem_provedor,ra.esforco_percebido,ra.usa_trechos,
                   ra.modalidade_nome,ra.modalidade_slug,
                   COALESCE(NULLIF(g.distancia_final_m,0),CASE WHEN ra.usa_trechos THEN NULLIF(seg.distancia_m,0) END,NULLIF(r.distancia_metros,0),NULLIF(metric.distancia_m,0)) AS api_distance_m,
                   COALESCE(NULLIF(g.duracao_s,0),CASE WHEN ra.usa_trechos THEN NULLIF(seg.duracao_s,0) END,NULLIF(GREATEST(EXTRACT(EPOCH FROM (COALESCE(ra.data_fim,ra.data_inicio)-ra.data_inicio)),0),0),NULLIF(metric.duracao_s,0)) AS api_duration_s,
                   COALESCE(CASE WHEN ra.usa_trechos THEN NULLIF(seg.elevacao_gain_m,0) END,r.ganho_elevacao_m,metric.elevacao_m) AS api_elevation_gain_m
            FROM page_rows ra
            LEFT JOIN rotas_atividade r ON r.idregistro=ra.idregistro
            LEFT JOIN gravacoes_gps_web g ON g.idregistro=ra.idregistro
            LEFT JOIN LATERAL (
                SELECT SUM(va.valor_normalizado) FILTER (WHERE lower(c.slug)='distancia') AS distancia_m,
                       SUM(va.valor_normalizado) FILTER (WHERE lower(c.slug)='duracao') AS duracao_s,
                       SUM(va.valor_normalizado) FILTER (WHERE lower(c.slug) IN ('elevacao','desnivel')) AS elevacao_m
                FROM valores_atividade va JOIN campos_modelo c ON c.idcampo=va.idcampo
                WHERE va.idregistro=ra.idregistro
            ) metric ON TRUE
            LEFT JOIN LATERAL (
                SELECT SUM(COALESCE(ua.distancia_metros,ru.distancia_metros)) AS distancia_m,
                       SUM(ua.duracao_segundos) AS duracao_s,
                       SUM(COALESCE(ua.elevacao_m,ru.ganho_elevacao_m)) AS elevacao_gain_m
                FROM unidades_atividade ua LEFT JOIN rotas_unidades_atividade ru ON ru.idunidade_atividade=ua.idunidade_atividade
                WHERE ua.idregistro=ra.idregistro
            ) seg ON TRUE
            ORDER BY ra.data_inicio DESC,ra.idregistro DESC";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) $stmt->bindValue($key, $value);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', ($page - 1) * $limit, PDO::PARAM_INT);
    $stmt->execute();
    return [
        'data' => array_map('stridebr_api_activity_summary', $stmt->fetchAll()),
        'pagination' => ['page'=>$page, 'limit'=>$limit, 'total'=>$total, 'total_pages'=>(int) ceil($total / $limit)],
    ];
}

function stridebr_api_activity_summary(array $row): array
{
    $distance = is_numeric($row['api_distance_m'] ?? null) ? max(0.0, (float)$row['api_distance_m']) : null;
    $duration = is_numeric($row['api_duration_s'] ?? null) ? max(0.0, (float)$row['api_duration_s']) : null;
    $elevation = is_numeric($row['api_elevation_gain_m'] ?? null) ? max(0.0, (float)$row['api_elevation_gain_m']) : null;
    $averageSpeed = $distance !== null && $duration !== null && $distance > 0 && $duration > 0 ? $distance / $duration : null;
    return [
        'id'=>(string)$row['idregistro'],
        'title'=>(string)($row['titulo'] ?: $row['modalidade_nome']),
        'sport'=>['id'=>(string)$row['idmodalidade'], 'slug'=>(string)$row['modalidade_slug'], 'name'=>(string)$row['modalidade_nome']],
        'started_at'=>stridebr_api_iso((string)$row['data_inicio']),
        'ended_at'=>stridebr_api_iso($row['data_fim'] !== null ? (string)$row['data_fim'] : null),
        'status'=>(string)$row['status'],
        'visibility'=>(string)$row['visibilidade'],
        'origin'=>(string)$row['origem'],
        'origin_provider'=>isset($row['origem_provedor']) && trim((string)$row['origem_provedor']) !== '' ? (string)$row['origem_provedor'] : null,
        'perceived_effort'=>$row['esforco_percebido'] !== null ? (int)$row['esforco_percebido'] : null,
        'distance_m'=>$distance,
        'duration_s'=>$duration,
        'elevation_gain_m'=>$elevation,
        'average_speed_mps'=>$averageSpeed !== null ? round($averageSpeed, 6) : null,
    ];
}

function stridebr_api_activity_detail(PDO $pdo, string $activityId, string $userId): array
{
    $stmt = $pdo->prepare("SELECT ra.idregistro,ra.idmodalidade,ra.titulo,ra.observacoes,ra.data_inicio,ra.data_fim,ra.status,ra.visibilidade,ra.origem,ra.origem_provedor,ra.esforco_percebido,ra.usa_trechos,
            ra.ocultar_inicio_m,ra.ocultar_fim_m,ra.calorias_ativas_estimadas,ra.calorias_totais_estimadas,
            m.nome AS modalidade_nome,m.slug AS modalidade_slug,
            COALESCE(NULLIF(g.distancia_final_m,0),CASE WHEN ra.usa_trechos THEN NULLIF(seg.distancia_m,0) END,NULLIF(r.distancia_metros,0),NULLIF(metric.distancia_m,0)) AS api_distance_m,
            COALESCE(NULLIF(g.duracao_s,0),CASE WHEN ra.usa_trechos THEN NULLIF(seg.duracao_s,0) END,NULLIF(GREATEST(EXTRACT(EPOCH FROM (COALESCE(ra.data_fim,ra.data_inicio)-ra.data_inicio)),0),0),NULLIF(metric.duracao_s,0)) AS api_duration_s,
            COALESCE(CASE WHEN ra.usa_trechos THEN NULLIF(seg.elevacao_gain_m,0) END,r.ganho_elevacao_m,metric.elevacao_m) AS api_elevation_gain_m,
            COALESCE(r.elevacao_min_m,seg.elevacao_min_m) AS api_elevation_min_m,
            COALESCE(r.elevacao_max_m,seg.elevacao_max_m) AS api_elevation_max_m
        FROM registros_atividade ra
        JOIN modalidades m ON m.idmodalidade=ra.idmodalidade
        LEFT JOIN rotas_atividade r ON r.idregistro=ra.idregistro
        LEFT JOIN gravacoes_gps_web g ON g.idregistro=ra.idregistro
        LEFT JOIN LATERAL (
            SELECT SUM(va.valor_normalizado) FILTER (WHERE lower(c.slug)='distancia') AS distancia_m,
                   SUM(va.valor_normalizado) FILTER (WHERE lower(c.slug)='duracao') AS duracao_s,
                   SUM(va.valor_normalizado) FILTER (WHERE lower(c.slug) IN ('elevacao','desnivel')) AS elevacao_m
            FROM valores_atividade va JOIN campos_modelo c ON c.idcampo=va.idcampo WHERE va.idregistro=ra.idregistro
        ) metric ON TRUE
        LEFT JOIN LATERAL (
            SELECT SUM(COALESCE(ua.distancia_metros,ru.distancia_metros)) AS distancia_m,SUM(ua.duracao_segundos) AS duracao_s,
                   SUM(COALESCE(ua.elevacao_m,ru.ganho_elevacao_m)) AS elevacao_gain_m,MIN(ru.elevacao_min_m) AS elevacao_min_m,MAX(ru.elevacao_max_m) AS elevacao_max_m
            FROM unidades_atividade ua LEFT JOIN rotas_unidades_atividade ru ON ru.idunidade_atividade=ua.idunidade_atividade WHERE ua.idregistro=ra.idregistro
        ) seg ON TRUE
        WHERE ra.idregistro=:id AND ra.idusuario=:user AND ra.excluido_em IS NULL LIMIT 1");
    $stmt->execute([':id'=>$activityId, ':user'=>$userId]);
    $row = $stmt->fetch();
    if (!$row) return [];

    $detail = stridebr_api_activity_summary($row);
    $detail['notes'] = $row['observacoes'] !== null ? (string)$row['observacoes'] : null;
    $detail['uses_segments'] = stridebr_api_bool($row['usa_trechos'] ?? false);
    $detail['elevation_min_m'] = is_numeric($row['api_elevation_min_m'] ?? null) ? (float)$row['api_elevation_min_m'] : null;
    $detail['elevation_max_m'] = is_numeric($row['api_elevation_max_m'] ?? null) ? (float)$row['api_elevation_max_m'] : null;
    $detail['active_calories_kcal'] = is_numeric($row['calorias_ativas_estimadas'] ?? null) ? (float)$row['calorias_ativas_estimadas'] : null;
    $detail['total_calories_kcal'] = is_numeric($row['calorias_totais_estimadas'] ?? null) ? (float)$row['calorias_totais_estimadas'] : null;
    $detail['route_privacy'] = ['hide_start_m'=>(int)($row['ocultar_inicio_m'] ?? 0), 'hide_end_m'=>(int)($row['ocultar_fim_m'] ?? 0)];

    $units = $pdo->prepare('SELECT ua.idunidade_atividade, ua.ordem, ua.tipo_unidade, ua.rotulo, ua.observacoes, ua.distancia_metros, ua.duracao_segundos, ua.elevacao_m, COALESCE(um.idmodalidade, :sport) AS idmodalidade, COALESCE(um.nome, :sport_name) AS modalidade_nome, COALESCE(um.slug, :sport_slug) AS modalidade_slug FROM unidades_atividade ua LEFT JOIN modalidades um ON um.idmodalidade = ua.idmodalidade WHERE ua.idregistro = :id ORDER BY ua.ordem');
    $units->execute([':id'=>$activityId, ':sport'=>$row['idmodalidade'], ':sport_name'=>$row['modalidade_nome'], ':sport_slug'=>$row['modalidade_slug']]);
    $detail['segments'] = array_map(static fn(array $unit): array => ['id'=>(string)$unit['idunidade_atividade'], 'order'=>(int)$unit['ordem'], 'type'=>(string)$unit['tipo_unidade'], 'label'=>$unit['rotulo'] !== null ? (string)$unit['rotulo'] : null, 'notes'=>$unit['observacoes'] !== null ? (string)$unit['observacoes'] : null, 'distance_m'=>$unit['distancia_metros'] !== null ? (float)$unit['distancia_metros'] : null, 'duration_s'=>$unit['duracao_segundos'] !== null ? (float)$unit['duracao_segundos'] : null, 'elevation_m'=>$unit['elevacao_m'] !== null ? (float)$unit['elevacao_m'] : null, 'sport'=>['id'=>(string)$unit['idmodalidade'], 'slug'=>(string)$unit['modalidade_slug'], 'name'=>(string)$unit['modalidade_nome']]], $units->fetchAll());

    $equipment = $pdo->prepare('SELECT e.idequipamento, e.nome, e.categoria FROM registros_atividade_equipamentos link JOIN equipamentos_usuario e ON e.idequipamento = link.idequipamento WHERE link.idregistro = :id AND e.idusuario = :user ORDER BY e.nome');
    $equipment->execute([':id'=>$activityId, ':user'=>$userId]);
    $detail['equipment'] = array_map(static fn(array $item): array => ['id'=>(string)$item['idequipamento'], 'name'=>(string)$item['nome'], 'category'=>(string)$item['categoria']], $equipment->fetchAll());

    $routeStmt = $pdo->prepare('SELECT modo,coordenadas,pontos_metadata,distancia_metros,ganho_elevacao_m,perda_elevacao_m,elevacao_min_m,elevacao_max_m,fonte_elevacao FROM rotas_atividade WHERE idregistro=:id LIMIT 1');
    $routeStmt->execute([':id'=>$activityId]);
    $routeRow = $routeStmt->fetch();
    $detail['route'] = null;
    if (is_array($routeRow)) {
        $geometry = json_decode((string)($routeRow['coordenadas'] ?? ''), true);
        $metadata = json_decode((string)($routeRow['pontos_metadata'] ?? ''), true);
        if (!is_array($metadata)) $metadata = [];
        $coordinates = is_array($geometry) && ($geometry['type'] ?? '') === 'LineString' && is_array($geometry['coordinates'] ?? null) ? $geometry['coordinates'] : [];
        $points = [];
        foreach ($coordinates as $index => $coordinate) {
            if (!is_array($coordinate) || !is_numeric($coordinate[0] ?? null) || !is_numeric($coordinate[1] ?? null)) continue;
            $meta = is_array($metadata[$index] ?? null) ? $metadata[$index] : [];
            $points[] = [
                'lat'=>(float)$coordinate[1],
                'lon'=>(float)$coordinate[0],
                'altitude_m'=>is_numeric($meta['altitude_m'] ?? null) ? (float)$meta['altitude_m'] : (is_numeric($coordinate[2] ?? null) ? (float)$coordinate[2] : null),
                'accuracy_m'=>is_numeric($meta['accuracy_m'] ?? null) ? (float)$meta['accuracy_m'] : null,
                'timestamp_ms'=>is_numeric($meta['timestamp_ms'] ?? null) ? (int)$meta['timestamp_ms'] : null,
            ];
        }
        if ($points !== []) {
            $detail['route'] = [
                'mode'=>(string)($routeRow['modo'] ?? ''),
                'points'=>$points,
                'distance_m'=>is_numeric($routeRow['distancia_metros'] ?? null) ? (float)$routeRow['distancia_metros'] : null,
                'elevation_gain_m'=>is_numeric($routeRow['ganho_elevacao_m'] ?? null) ? (float)$routeRow['ganho_elevacao_m'] : null,
                'elevation_loss_m'=>is_numeric($routeRow['perda_elevacao_m'] ?? null) ? (float)$routeRow['perda_elevacao_m'] : null,
                'elevation_min_m'=>is_numeric($routeRow['elevacao_min_m'] ?? null) ? (float)$routeRow['elevacao_min_m'] : null,
                'elevation_max_m'=>is_numeric($routeRow['elevacao_max_m'] ?? null) ? (float)$routeRow['elevacao_max_m'] : null,
                'elevation_source'=>trim((string)($routeRow['fonte_elevacao'] ?? '')) ?: null,
            ];
        }
    }

    $gpsStmt = $pdo->prepare('SELECT distancia_medida_m,pontos_recebidos,pontos_aceitos,pontos_rejeitados,precisao_media_m,precisao_melhor_m,precisao_pior_m,lacunas_visibilidade,usuario_ajustou FROM gravacoes_gps_web WHERE idregistro=:id LIMIT 1');
    $gpsStmt->execute([':id'=>$activityId]);
    $gps = $gpsStmt->fetch();
    $detail['gps'] = is_array($gps) ? [
        'measured_distance_m'=>is_numeric($gps['distancia_medida_m'] ?? null) ? (float)$gps['distancia_medida_m'] : null,
        'points_received'=>(int)($gps['pontos_recebidos'] ?? 0),
        'points_accepted'=>(int)($gps['pontos_aceitos'] ?? 0),
        'points_rejected'=>(int)($gps['pontos_rejeitados'] ?? 0),
        'accuracy_avg_m'=>is_numeric($gps['precisao_media_m'] ?? null) ? (float)$gps['precisao_media_m'] : null,
        'accuracy_best_m'=>is_numeric($gps['precisao_melhor_m'] ?? null) ? (float)$gps['precisao_melhor_m'] : null,
        'accuracy_worst_m'=>is_numeric($gps['precisao_pior_m'] ?? null) ? (float)$gps['precisao_pior_m'] : null,
        'visibility_gaps'=>(int)($gps['lacunas_visibilidade'] ?? 0),
        'user_adjusted'=>stridebr_api_bool($gps['usuario_ajustou'] ?? false),
    ] : null;

    $workoutStmt = $pdo->prepare("SELECT st.idagendamento_origem, ra.idtreino_cronograma, ra.data_ocorrencia_origem, ra.data_ocorrencia_planejada, ra.data_inicio
        FROM registros_atividade ra
        LEFT JOIN LATERAL (
            SELECT s.idagendamento_origem
              FROM sessoes_treino s
             WHERE s.idregistro_atividade = ra.idregistro
               AND s.idusuario = ra.idusuario
               AND s.idagendamento_origem IS NOT NULL
               AND s.status = 'concluido'
             ORDER BY s.data_criacao DESC
             LIMIT 1
        ) st ON TRUE
        WHERE ra.idregistro = :id AND ra.idusuario = :user AND ra.excluido_em IS NULL LIMIT 1");
    $workoutStmt->execute([':id' => $activityId, ':user' => $userId]);
    $workout = $workoutStmt->fetch();
    $detail['workout'] = null;
    if (is_array($workout)) {
        if (!empty($workout['idagendamento_origem'])) {
            $detail['workout'] = ['id' => stridebr_api_workout_id_scheduled((string) $workout['idagendamento_origem']), 'kind' => 'scheduled'];
        } elseif (!empty($workout['idtreino_cronograma'])) {
            $date = trim((string) ($workout['data_ocorrencia_origem'] ?? $workout['data_ocorrencia_planejada'] ?? ''));
            if ($date === '' && !empty($workout['data_inicio'])) {
                $date = (new DateTimeImmutable((string) $workout['data_inicio']))->setTimezone(new DateTimeZone(stridebr_api_workout_timezone()))->format('Y-m-d');
            }
            if ($date !== '') $detail['workout'] = ['id' => stridebr_api_workout_id_recurring((string) $workout['idtreino_cronograma'], substr($date, 0, 10)), 'kind' => 'recurring'];
        }
    }
    return $detail;
}

require_once __DIR__ . '/api_workouts.php';
