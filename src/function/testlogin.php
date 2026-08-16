<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/errors.php';
require_once dirname(__DIR__) . '/includes/app.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /login.php');
    exit;
}

stridebr_verify_csrf();

$email = stridebr_lower(trim((string) ($_POST['UEmail'] ?? '')));
$password = (string) ($_POST['USenha'] ?? '');
$redirect = stridebr_safe_redirect((string) ($_POST['redirect'] ?? ''), '/home.php');
$now = time();
$attempts = (int) ($_SESSION['login_attempts'] ?? 0);
$windowStarted = (int) ($_SESSION['login_attempt_window'] ?? $now);

if ($now - $windowStarted > 600) {
    $attempts = 0;
    $windowStarted = $now;
}

if ($attempts >= 5) {
    stridebr_flash('danger', 'Muitas tentativas de login. Aguarde alguns minutos e tente novamente.');
    header('Location: /login.php?redirect=' . rawurlencode($redirect));
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
    stridebr_flash('danger', 'Informe um e-mail e uma senha válidos.');
    header('Location: /login.php?redirect=' . rawurlencode($redirect));
    exit;
}

require_once dirname(__DIR__) . '/config/pg_config.php';

$stmt = $pdo->prepare('SELECT idusuario, nomeusuario, nome_exibicao, username, papelusuario, onboarding_concluido, emailusuario, senhausuario, fotousuario, statususuario, verificado, email_verificado_em, sessao_versao FROM usuarios WHERE lower(emailusuario) = lower(:email) LIMIT 1');
$stmt->execute([':email' => $email]);
$user = $stmt->fetch();

if (!$user || $user['statususuario'] !== 'Ativo' || !password_verify($password, $user['senhausuario'])) {
    $_SESSION['login_attempts'] = $attempts + 1;
    $_SESSION['login_attempt_window'] = $windowStarted;
    stridebr_flash('danger', 'Credenciais inválidas.');
    header('Location: /login.php?redirect=' . rawurlencode($redirect));
    exit;
}

if (stridebr_feature_enabled($pdo, 'auth.email_verification.required', false) && !stridebr_db_bool($user['verificado']) && empty($user['email_verificado_em'])) {
    stridebr_flash('warning', 'Confirme seu e-mail antes de entrar. Se precisar, use a opção de reenviar a verificação.');
    header('Location: /login.php?redirect=' . rawurlencode($redirect));
    exit;
}

session_regenerate_id(true);
unset($_SESSION['login_attempts'], $_SESSION['login_attempt_window']);
$_SESSION['IdUsuario'] = $user['idusuario'];
$_SESSION['NomeUsuario'] = $user['nomeusuario'];
$_SESSION['NomeExibicao'] = trim((string) ($user['nome_exibicao'] ?? '')) ?: $user['nomeusuario'];
$_SESSION['Username'] = $user['username'] ?? null;
$_SESSION['PapelUsuario'] = $user['papelusuario'] ?? 'user';
$_SESSION['OnboardingConcluido'] = stridebr_db_bool($user['onboarding_concluido'] ?? false);
$_SESSION['EmailUsuario'] = $user['emailusuario'];
$_SESSION['FotoUsuario'] = $user['fotousuario'] ?: null;
$_SESSION['SessaoVersao'] = (int) ($user['sessao_versao'] ?? 1);

$stmt = $pdo->prepare('UPDATE usuarios SET ultimologin = NOW(), ipultimologin = :ip WHERE idusuario = :idusuario');
$stmt->execute([':ip' => stridebr_client_ip(), ':idusuario' => $user['idusuario']]);

if (stridebr_feature_enabled($pdo, 'access_logs.enabled', false)) {
    $access = $pdo->prepare('INSERT INTO acessos_usuario (idusuario, ip, user_agent) VALUES (:usuario, CAST(:ip AS inet), :agente)');
    $ip = stridebr_client_ip();
    $agent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500) ?: null;
    $access->bindValue(':usuario', $user['idusuario']);
    $access->bindValue(':ip', $ip, $ip === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
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
