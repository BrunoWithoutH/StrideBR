<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';

stridebr_require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método não permitido.');
}
stridebr_verify_csrf();

$original = $_SESSION['OwnerImpersonation'] ?? null;
if (!is_array($original) || (string) ($original['PapelUsuario'] ?? '') !== 'owner' || empty($original['IdUsuario'])) {
    stridebr_error_document(403);
}

$csrf = $_SESSION['csrf_token'] ?? null;
unset($_SESSION['OwnerImpersonation']);
foreach (['IdUsuario','NomeUsuario','NomeExibicao','Username','PapelUsuario','EmailUsuario','FotoUsuario','SessaoVersao','OnboardingConcluido'] as $key) {
    if (array_key_exists($key, $original)) $_SESSION[$key] = $original[$key];
}
if (is_string($csrf) && $csrf !== '') $_SESSION['csrf_token'] = $csrf;
$_SESSION['SessionGuardCheckedAt'] = 0;
session_regenerate_id(true);
header('Location: /admin/index.php');
exit;
