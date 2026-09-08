<?php

declare(strict_types=1);

require_once __DIR__ . '/app.php';
require_once __DIR__ . '/mail.php';

function stridebr_auth_email_verification_enabled(PDO $pdo): bool
{
    return stridebr_mail_is_configured()
        && stridebr_feature_enabled($pdo, 'auth.email_verification.enabled', false);
}

function stridebr_auth_email_verification_required(PDO $pdo): bool
{
    return stridebr_auth_email_verification_enabled($pdo)
        && stridebr_feature_enabled($pdo, 'auth.email_verification.required', false);
}

function stridebr_auth_password_reset_enabled(PDO $pdo): bool
{
    return stridebr_mail_is_configured()
        && stridebr_feature_enabled($pdo, 'auth.password_reset.enabled', false);
}

function stridebr_auth_create_token(PDO $pdo, string $userId, string $email, string $type, int $minutes, ?string $raw = null): string
{
    if (!in_array($type, ['verificar_email', 'redefinir_senha'], true)) {
        throw new InvalidArgumentException('Tipo de token inválido.');
    }

    $cooldown = $pdo->prepare("SELECT 1 FROM auth_tokens WHERE idusuario = :usuario AND tipo = :tipo AND usado_em IS NULL AND expira_em > NOW() AND criado_em > NOW() - INTERVAL '2 minutes' LIMIT 1");
    $cooldown->execute([':usuario' => $userId, ':tipo' => $type]);
    if ($cooldown->fetchColumn()) {
        throw new RuntimeException('Aguarde alguns minutos antes de solicitar novamente.');
    }

    $raw = $raw ?? bin2hex(random_bytes(32));
    if (!preg_match('/^(?:[a-f0-9]{64}|[0-9]{6})$/', $raw)) {
        throw new InvalidArgumentException('Formato de token inválido.');
    }

    $hash = hash('sha256', $raw);
    $pdo->beginTransaction();
    try {
        $invalidate = $pdo->prepare('UPDATE auth_tokens SET usado_em = NOW() WHERE idusuario = :usuario AND tipo = :tipo AND usado_em IS NULL');
        $invalidate->execute([':usuario' => $userId, ':tipo' => $type]);

        $stmt = $pdo->prepare("INSERT INTO auth_tokens (idtoken, idusuario, tipo, token_hash, email_destino, expira_em, solicitacao_ip) VALUES (:id, :usuario, :tipo, :hash, :email, NOW() + (:minutos || ' minutes')::interval, CAST(:ip AS inet))");
        $ip = stridebr_client_ip();
        $stmt->bindValue(':id', stridebr_generate_id());
        $stmt->bindValue(':usuario', $userId);
        $stmt->bindValue(':tipo', $type);
        $stmt->bindValue(':hash', $hash);
        $stmt->bindValue(':email', $email);
        $stmt->bindValue(':minutos', (string) max(1, $minutes));
        $stmt->bindValue(':ip', $ip, $ip === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->execute();
        $pdo->commit();
        $pdo->exec("DELETE FROM auth_tokens WHERE (usado_em IS NOT NULL AND usado_em < NOW() - INTERVAL '7 days') OR expira_em < NOW() - INTERVAL '7 days'");
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return $raw;
}

function stridebr_auth_create_verification_code(PDO $pdo, string $userId, string $email, int $minutes = 15): string
{
    for ($attempt = 0; $attempt < 20; $attempt++) {
        $code = (string) random_int(100000, 999999);
        $check = $pdo->prepare('SELECT 1 FROM auth_tokens WHERE token_hash = :hash LIMIT 1');
        $check->execute([':hash' => hash('sha256', $code)]);
        if (!$check->fetchColumn()) {
            return stridebr_auth_create_token($pdo, $userId, $email, 'verificar_email', $minutes, $code);
        }
    }
    throw new RuntimeException('Não foi possível gerar um código de confirmação agora.');
}

function stridebr_auth_find_token(PDO $pdo, string $raw, string $type): ?array
{
    $raw = trim($raw);
    if (!preg_match('/^(?:[a-f0-9]{64}|[0-9]{6})$/i', $raw)) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT t.idtoken, t.idusuario, t.email_destino, t.expira_em, t.usado_em, u.emailusuario, u.nomeusuario, u.nome_exibicao, u.username, u.papelusuario, u.onboarding_concluido, u.fotousuario, u.sessao_versao, u.statususuario FROM auth_tokens t JOIN usuarios u ON u.idusuario = t.idusuario WHERE t.token_hash = :hash AND t.tipo = :tipo LIMIT 1');
    $stmt->execute([':hash' => hash('sha256', $raw), ':tipo' => $type]);
    $token = $stmt->fetch();
    if (!$token || $token['usado_em'] !== null || strtotime((string) $token['expira_em']) <= time()) {
        return null;
    }
    return $token;
}

function stridebr_auth_set_pending_verification(string $userId, string $email, string $redirect = '/home.php'): void
{
    $_SESSION['PendingVerification'] = [
        'user_id' => $userId,
        'email' => stridebr_lower(trim($email)),
        'redirect' => stridebr_safe_redirect($redirect, '/home.php'),
        'created_at' => time(),
    ];
}

function stridebr_auth_pending_verification(): ?array
{
    $pending = $_SESSION['PendingVerification'] ?? null;
    if (!is_array($pending)) {
        return null;
    }
    if ((int) ($pending['created_at'] ?? 0) < time() - 86400) {
        unset($_SESSION['PendingVerification']);
        return null;
    }
    $userId = trim((string) ($pending['user_id'] ?? ''));
    $email = stridebr_lower(trim((string) ($pending['email'] ?? '')));
    if ($userId === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        unset($_SESSION['PendingVerification']);
        return null;
    }
    return [
        'user_id' => $userId,
        'email' => $email,
        'redirect' => stridebr_safe_redirect((string) ($pending['redirect'] ?? ''), '/home.php'),
    ];
}

function stridebr_auth_clear_pending_verification(): void
{
    unset($_SESSION['PendingVerification']);
}

function stridebr_auth_start_session(PDO $pdo, array $user, ?string $ip = null): void
{
    session_regenerate_id(true);
    $_SESSION['IdUsuario'] = (string) $user['idusuario'];
    $_SESSION['NomeUsuario'] = (string) $user['nomeusuario'];
    $_SESSION['NomeExibicao'] = trim((string) ($user['nome_exibicao'] ?? '')) ?: (string) $user['nomeusuario'];
    $_SESSION['Username'] = $user['username'] ?? null;
    $_SESSION['PapelUsuario'] = $user['papelusuario'] ?? 'user';
    $_SESSION['OnboardingConcluido'] = stridebr_db_bool($user['onboarding_concluido'] ?? false);
    $_SESSION['EmailUsuario'] = (string) $user['emailusuario'];
    $_SESSION['FotoUsuario'] = !empty($user['fotousuario']) ? $user['fotousuario'] : null;
    $_SESSION['SessaoVersao'] = (int) ($user['sessao_versao'] ?? 1);
    $prefs = is_array($user['preferenciasusuario'] ?? null) ? $user['preferenciasusuario'] : (json_decode((string) ($user['preferenciasusuario'] ?? '{}'), true) ?: []);
    stridebr_set_locale_preference((string) ($prefs['locale'] ?? 'auto'));
    stridebr_set_theme((string) ($prefs['theme'] ?? stridebr_theme()));
    $stmt = $pdo->prepare('UPDATE usuarios SET ultimologin = NOW(), ipultimologin = :ip WHERE idusuario = :idusuario');
    $stmt->execute([':ip' => $ip !== '' ? $ip : null, ':idusuario' => $user['idusuario']]);
    stridebr_session_register($pdo, (string) $user['idusuario']);
    stridebr_auth_clear_pending_verification();
}

function stridebr_auth_load_user(PDO $pdo, string $userId): ?array
{
    $stmt = $pdo->prepare('SELECT idusuario, nomeusuario, nome_exibicao, username, papelusuario, onboarding_concluido, emailusuario, fotousuario, statususuario, verificado, email_verificado_em, sessao_versao, preferenciasusuario, google_sub FROM usuarios WHERE idusuario = :id LIMIT 1');
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function stridebr_send_verification_email(PDO $pdo, string $userId, string $email, string $name): bool
{
    if (!stridebr_mail_is_configured()) {
        return false;
    }
    $code = stridebr_auth_create_verification_code($pdo, $userId, $email, 15);
    $body = "Olá, {$name}.\n\nSeu código de confirmação do StrideBR é:\n\n{$code}\n\nDigite esse código na etapa de verificação. Ele expira em 15 minutos. Se você não criou essa conta, ignore esta mensagem.";
    $sent = stridebr_send_mail($email, 'Seu código de confirmação do StrideBR', $body);
    if (!$sent) {
        $stmt = $pdo->prepare("UPDATE auth_tokens SET usado_em = NOW() WHERE idusuario = :usuario AND tipo = 'verificar_email' AND token_hash = :hash AND usado_em IS NULL");
        $stmt->execute([':usuario' => $userId, ':hash' => hash('sha256', $code)]);
    }
    return $sent;
}

function stridebr_send_email_change_code(PDO $pdo, string $userId, string $email, string $name): bool
{
    if (!stridebr_mail_is_configured()) {
        return false;
    }
    $code = stridebr_auth_create_verification_code($pdo, $userId, $email, 15);
    $body = "Olá, {$name}.\n\nRecebemos um pedido para alterar o e-mail da sua conta StrideBR para este endereço.\n\nSeu código de confirmação é:\n\n{$code}\n\nEle expira em 15 minutos. Se você não pediu essa alteração, não compartilhe o código e ignore esta mensagem.";
    $sent = stridebr_send_mail($email, 'Confirme o novo e-mail do StrideBR', $body);
    if (!$sent) {
        $stmt = $pdo->prepare("UPDATE auth_tokens SET usado_em = NOW() WHERE idusuario = :usuario AND tipo = 'verificar_email' AND token_hash = :hash AND usado_em IS NULL");
        $stmt->execute([':usuario' => $userId, ':hash' => hash('sha256', $code)]);
    }
    return $sent;
}

function stridebr_send_password_reset_email(PDO $pdo, string $userId, string $email, string $name): bool
{
    if (!stridebr_mail_is_configured()) {
        return false;
    }
    $token = stridebr_auth_create_token($pdo, $userId, $email, 'redefinir_senha', 30);
    $url = stridebr_app_url() . '/reset-password.php?token=' . rawurlencode($token);
    $body = "Olá, {$name}.\n\nRecebemos uma solicitação para redefinir sua senha do StrideBR. Use o link abaixo:\n{$url}\n\nO link expira em 30 minutos e só pode ser usado uma vez. Se você não pediu a redefinição, ignore esta mensagem.";
    $sent = stridebr_send_mail($email, 'Redefinição de senha do StrideBR', $body);
    if (!$sent) {
        $stmt = $pdo->prepare("UPDATE auth_tokens SET usado_em = NOW() WHERE idusuario = :usuario AND tipo = 'redefinir_senha' AND token_hash = :hash AND usado_em IS NULL");
        $stmt->execute([':usuario' => $userId, ':hash' => hash('sha256', $token)]);
    }
    return $sent;
}

function stridebr_auth_google_feature_enabled(): bool
{
    $value = strtolower(trim((string) (getenv('GOOGLE_OAUTH_ENABLED') ?: '0')));
    return in_array($value, ['1', 'true', 'yes', 'on'], true);
}

function stridebr_auth_google_configured(): bool
{
    $clientId = trim((string) (getenv('GOOGLE_OAUTH_CLIENT_ID') ?: ''));
    $clientSecret = trim((string) (getenv('GOOGLE_OAUTH_CLIENT_SECRET') ?: ''));
    return $clientId !== '' && $clientSecret !== '';
}

function stridebr_auth_google_enabled(): bool
{
    return stridebr_auth_google_feature_enabled() && stridebr_auth_google_configured();
}

function stridebr_auth_google_redirect_uri(): string
{
    return stridebr_app_url() . '/auth/google-callback.php';
}

function stridebr_auth_google_start(string $redirect = '/home.php'): string
{
    if (!stridebr_auth_google_enabled()) {
        throw new RuntimeException('Login com Google está desativado ou não configurado.');
    }
    $state = bin2hex(random_bytes(24));
    $nonce = bin2hex(random_bytes(24));
    $_SESSION['GoogleOAuth'] = [
        'state' => $state,
        'nonce' => $nonce,
        'redirect' => stridebr_safe_redirect($redirect, '/home.php'),
        'created_at' => time(),
    ];
    $query = http_build_query([
        'client_id' => trim((string) getenv('GOOGLE_OAUTH_CLIENT_ID')),
        'redirect_uri' => stridebr_auth_google_redirect_uri(),
        'response_type' => 'code',
        'scope' => 'openid email profile',
        'state' => $state,
        'nonce' => $nonce,
        'prompt' => 'select_account',
        'include_granted_scopes' => 'true',
    ], '', '&', PHP_QUERY_RFC3986);
    return 'https://accounts.google.com/o/oauth2/v2/auth?' . $query;
}

function stridebr_auth_http_post_form(string $url, array $fields): array
{
    $body = http_build_query($fields, '', '&', PHP_QUERY_RFC3986);
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'],
        ]);
        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if (!is_string($response) || $status < 200 || $status >= 300) {
            throw new RuntimeException('Falha ao conversar com o Google.' . ($error !== '' ? ' ' . $error : ''));
        }
    } else {
        $context = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\nAccept: application/json\r\n",
            'content' => $body,
            'timeout' => 12,
            'ignore_errors' => true,
        ]]);
        $response = @file_get_contents($url, false, $context);
        if (!is_string($response)) throw new RuntimeException('Falha ao conversar com o Google.');
    }
    $decoded = json_decode($response, true);
    if (!is_array($decoded)) throw new RuntimeException('Resposta inválida do Google.');
    return $decoded;
}

function stridebr_auth_http_get_json(string $url, string $bearer): array
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $bearer, 'Accept: application/json'],
        ]);
        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if (!is_string($response) || $status < 200 || $status >= 300) throw new RuntimeException('Não foi possível confirmar sua conta Google.');
    } else {
        $context = stream_context_create(['http' => [
            'method' => 'GET',
            'header' => "Authorization: Bearer {$bearer}\r\nAccept: application/json\r\n",
            'timeout' => 12,
            'ignore_errors' => true,
        ]]);
        $response = @file_get_contents($url, false, $context);
        if (!is_string($response)) throw new RuntimeException('Não foi possível confirmar sua conta Google.');
    }
    $decoded = json_decode($response, true);
    if (!is_array($decoded)) throw new RuntimeException('Resposta inválida do Google.');
    return $decoded;
}

function stridebr_auth_google_exchange(string $code): array
{
    if (!stridebr_auth_google_enabled()) throw new RuntimeException('Login com Google está desativado ou não configurado.');
    $token = stridebr_auth_http_post_form('https://oauth2.googleapis.com/token', [
        'code' => $code,
        'client_id' => trim((string) getenv('GOOGLE_OAUTH_CLIENT_ID')),
        'client_secret' => trim((string) getenv('GOOGLE_OAUTH_CLIENT_SECRET')),
        'redirect_uri' => stridebr_auth_google_redirect_uri(),
        'grant_type' => 'authorization_code',
    ]);
    $accessToken = trim((string) ($token['access_token'] ?? ''));
    if ($accessToken === '') throw new RuntimeException('O Google não retornou uma credencial válida.');
    $profile = stridebr_auth_http_get_json('https://openidconnect.googleapis.com/v1/userinfo', $accessToken);
    $sub = trim((string) ($profile['sub'] ?? ''));
    $email = stridebr_lower(trim((string) ($profile['email'] ?? '')));
    $verified = stridebr_db_bool($profile['email_verified'] ?? false);
    if ($sub === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$verified) {
        throw new RuntimeException('A conta Google precisa ter um e-mail verificado.');
    }
    return [
        'sub' => $sub,
        'email' => $email,
        'name' => stridebr_person_name_normalize((string) ($profile['name'] ?? $profile['given_name'] ?? 'Usuário')),
        'picture' => filter_var((string) ($profile['picture'] ?? ''), FILTER_VALIDATE_URL) ? (string) $profile['picture'] : '',
    ];
}

function stridebr_auth_google_login_or_create(PDO $pdo, array $profile, ?string $ip = null): array
{
    $sub = trim((string) ($profile['sub'] ?? ''));
    $email = stridebr_lower(trim((string) ($profile['email'] ?? '')));
    if ($sub === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Conta Google inválida.');

    $stmt = $pdo->prepare('SELECT idusuario, nomeusuario, nome_exibicao, username, papelusuario, onboarding_concluido, emailusuario, fotousuario, statususuario, verificado, email_verificado_em, sessao_versao, preferenciasusuario, google_sub FROM usuarios WHERE google_sub = :sub OR lower(emailusuario) = lower(:email) ORDER BY CASE WHEN google_sub = :sub2 THEN 0 ELSE 1 END LIMIT 1');
    $stmt->execute([':sub' => $sub, ':sub2' => $sub, ':email' => $email]);
    $user = $stmt->fetch();

    if ($user) {
        if ((string) ($user['statususuario'] ?? '') !== 'Ativo') throw new RuntimeException('Esta conta está desativada.');
        $linkedSub = trim((string) ($user['google_sub'] ?? ''));
        if ($linkedSub !== '' && !hash_equals($linkedSub, $sub)) {
            throw new RuntimeException('Este e-mail já está vinculado a outra identidade Google.');
        }
        if ($linkedSub === '') {
            $picture = trim((string) ($profile['picture'] ?? ''));
            $link = $pdo->prepare("UPDATE usuarios SET google_sub = :sub, verificado = TRUE, email_verificado_em = COALESCE(email_verificado_em, NOW()), fotousuario = COALESCE(NULLIF(fotousuario, ''), :foto) WHERE idusuario = :id");
            $link->execute([':sub' => $sub, ':foto' => $picture !== '' ? $picture : null, ':id' => $user['idusuario']]);
            $user['google_sub'] = $sub;
            if (trim((string) ($user['fotousuario'] ?? '')) === '' && $picture !== '') $user['fotousuario'] = $picture;
            $user['verificado'] = true;
        }
        return ['user' => $user, 'created' => false];
    }

    $name = stridebr_person_name_normalize((string) ($profile['name'] ?? ''));
    if (!stridebr_person_name_is_valid($name, 80)) $name = 'Usuário StrideBR';
    $id = stridebr_generate_id();
    $randomPassword = stridebr_password_hash(bin2hex(random_bytes(32)));
    $preferences = [
        'units' => 'metric',
        'week_start' => 'sunday',
        'locale' => stridebr_locale(),
        'theme' => stridebr_theme(),
    ];
    $picture = trim((string) ($profile['picture'] ?? ''));
    $insert = $pdo->prepare('INSERT INTO usuarios (idusuario, nomeusuario, nome_exibicao, emailusuario, senhausuario, google_sub, verificado, email_verificado_em, ipregistro, fotousuario, preferenciasusuario) VALUES (:id, :nome, :display, :email, :senha, :sub, TRUE, NOW(), :ip, :foto, CAST(:prefs AS jsonb))');
    $insert->execute([
        ':id' => $id,
        ':nome' => $name,
        ':display' => $name,
        ':email' => $email,
        ':senha' => $randomPassword,
        ':sub' => $sub,
        ':ip' => $ip !== '' ? $ip : null,
        ':foto' => $picture !== '' ? $picture : null,
        ':prefs' => json_encode($preferences, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
    $user = stridebr_auth_load_user($pdo, $id);
    if (!$user) throw new RuntimeException('Não foi possível iniciar a conta Google.');
    return ['user' => $user, 'created' => true];
}
