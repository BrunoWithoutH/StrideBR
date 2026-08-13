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

$stmt = $pdo->prepare('SELECT idusuario, nomeusuario, emailusuario, senhausuario, fotousuario, statususuario FROM usuarios WHERE lower(emailusuario) = lower(:email) LIMIT 1');
$stmt->execute([':email' => $email]);
$user = $stmt->fetch();

if (!$user || $user['statususuario'] !== 'Ativo' || !password_verify($password, $user['senhausuario'])) {
    $_SESSION['login_attempts'] = $attempts + 1;
    $_SESSION['login_attempt_window'] = $windowStarted;
    stridebr_flash('danger', 'Credenciais inválidas.');
    header('Location: /login.php?redirect=' . rawurlencode($redirect));
    exit;
}

session_regenerate_id(true);
unset($_SESSION['login_attempts'], $_SESSION['login_attempt_window']);
$_SESSION['IdUsuario'] = $user['idusuario'];
$_SESSION['NomeUsuario'] = $user['nomeusuario'];
$_SESSION['EmailUsuario'] = $user['emailusuario'];
$_SESSION['FotoUsuario'] = $user['fotousuario'] ?: null;

$stmt = $pdo->prepare('UPDATE usuarios SET ultimologin = NOW(), ipultimologin = :ip WHERE idusuario = :idusuario');
$stmt->execute([
    ':ip' => stridebr_client_ip(),
    ':idusuario' => $user['idusuario'],
]);

$previous = $_SESSION['previous_page'] ?? null;
unset($_SESSION['previous_page']);
header('Location: ' . stridebr_safe_redirect(is_string($previous) ? $previous : $redirect, $redirect));
exit;
