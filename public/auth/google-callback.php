<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/app.php';
require_once dirname(__DIR__, 2) . '/src/includes/auth.php';
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';

$pending = $_SESSION['GoogleOAuth'] ?? null;
unset($_SESSION['GoogleOAuth']);
$state = trim((string) ($_GET['state'] ?? ''));
$code = trim((string) ($_GET['code'] ?? ''));
if (!is_array($pending) || !hash_equals((string) ($pending['state'] ?? ''), $state) || (int) ($pending['created_at'] ?? 0) < time() - 600 || $code === '') {
    stridebr_flash('danger', 'A tentativa de login com Google expirou ou não pôde ser validada.');
    header('Location: /login.php');
    exit;
}

$redirect = stridebr_safe_redirect((string) ($pending['redirect'] ?? ''), '/home.php');
try {
    $profile = stridebr_auth_google_exchange($code);
    $result = stridebr_auth_google_login_or_create($pdo, $profile, stridebr_client_ip());
    $user = $result['user'];
    stridebr_auth_start_session($pdo, $user, stridebr_client_ip());
    if (!$_SESSION['OnboardingConcluido']) {
        header('Location: /user/onboarding.php');
        exit;
    }
    header('Location: ' . $redirect);
    exit;
} catch (Throwable $e) {
    error_log('Google OAuth login failed: ' . $e->getMessage());
    stridebr_flash('danger', 'Não foi possível entrar com Google agora. Tente novamente.');
    header('Location: /login.php?redirect=' . rawurlencode($redirect));
    exit;
}
