<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/app.php';
require_once dirname(__DIR__, 2) . '/src/includes/auth.php';

if (stridebr_is_logged_in()) {
    header('Location: /home.php');
    exit;
}

$redirect = stridebr_safe_redirect((string) ($_GET['redirect'] ?? ''), '/home.php');
try {
    header('Location: ' . stridebr_auth_google_start($redirect));
    exit;
} catch (Throwable $e) {
    stridebr_flash('danger', 'Não foi possível iniciar o login com Google.');
    header('Location: /login.php?redirect=' . rawurlencode($redirect));
    exit;
}
