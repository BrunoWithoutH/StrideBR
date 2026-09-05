<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/errors.php';
require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /login.php');
    exit;
}

stridebr_verify_csrf();

$email = stridebr_lower(trim((string) ($_POST['UEmail'] ?? '')));
$password = (string) ($_POST['USenha'] ?? '');
$redirect = stridebr_safe_redirect((string) ($_POST['redirect'] ?? ''), '/home.php');
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !stridebr_password_is_valid_length($password, 1, 128)) {
    stridebr_flash('danger', 'Informe um e-mail e uma senha válidos.');
    header('Location: /login.php?redirect=' . rawurlencode($redirect));
    exit;
}

require_once dirname(__DIR__) . '/config/pg_config.php';

$ip = stridebr_client_ip() ?? '';
$emailBlocked = stridebr_auth_limit_is_blocked($pdo, 'login-email', $email);
$ipBlocked = $ip !== '' && stridebr_auth_limit_is_blocked($pdo, 'login-ip', $ip);
if ($emailBlocked || $ipBlocked) {
    stridebr_flash('danger', 'Muitas tentativas de login. Aguarde alguns minutos e tente novamente.');
    header('Location: /login.php?redirect=' . rawurlencode($redirect));
    exit;
}

$stmt = $pdo->prepare('SELECT idusuario, nomeusuario, nome_exibicao, username, papelusuario, onboarding_concluido, emailusuario, senhausuario, fotousuario, statususuario, verificado, email_verificado_em, sessao_versao, preferenciasusuario, google_sub FROM usuarios WHERE lower(emailusuario) = lower(:email) LIMIT 1');
$stmt->execute([':email' => $email]);
$user = $stmt->fetch();

if (!$user || $user['statususuario'] !== 'Ativo' || !password_verify($password, $user['senhausuario'])) {
    stridebr_auth_limit_record_failure($pdo, 'login-email', $email, 8, 600, 600);
    if ($ip !== '') {
        stridebr_auth_limit_record_failure($pdo, 'login-ip', $ip, 30, 600, 600);
    }
    stridebr_flash('danger', 'Credenciais inválidas.');
    header('Location: /login.php?redirect=' . rawurlencode($redirect));
    exit;
}

if (stridebr_auth_email_verification_required($pdo) && !stridebr_db_bool($user['verificado']) && empty($user['email_verificado_em'])) {
    stridebr_auth_limit_clear($pdo, 'login-email', $email);
    stridebr_auth_set_pending_verification((string) $user['idusuario'], (string) $user['emailusuario'], $redirect);
    header('Location: /verify-email.php');
    exit;
}

if (stridebr_password_needs_rehash((string) $user['senhausuario'])) {
    $rehash = $pdo->prepare('UPDATE usuarios SET senhausuario = :senha WHERE idusuario = :id');
    $rehash->execute([':senha' => stridebr_password_hash($password), ':id' => $user['idusuario']]);
}

stridebr_auth_limit_clear($pdo, 'login-email', $email);
stridebr_auth_limit_cleanup($pdo);
stridebr_auth_start_session($pdo, $user, $ip);

if (stridebr_feature_enabled($pdo, 'access_logs.enabled', false)) {
    $access = $pdo->prepare('INSERT INTO acessos_usuario (idusuario, ip, user_agent) VALUES (:usuario, CAST(:ip AS inet), :agente)');
    $accessIp = $ip !== '' ? $ip : null;
    $agent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500) ?: null;
    $access->bindValue(':usuario', $user['idusuario']);
    $access->bindValue(':ip', $accessIp, $accessIp === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $access->bindValue(':agente', $agent, $agent === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $access->execute();
    $pdo->exec("DELETE FROM acessos_usuario WHERE data_acesso < NOW() - INTERVAL '90 days'");
}

$previous = $_SESSION['previous_page'] ?? null;
unset($_SESSION['previous_page']);
if (!$_SESSION['OnboardingConcluido']) {
    header('Location: /user/onboarding.php');
    exit;
}
header('Location: ' . stridebr_safe_redirect(is_string($previous) ? $previous : $redirect, $redirect));
exit;
