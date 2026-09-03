<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';

$idUsuario = stridebr_require_login();
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/src/function/integrations.php';

$provider = stridebr_lower(trim((string) ($_GET['provider'] ?? '')));
$state = trim((string) ($_GET['state'] ?? ''));
$code = trim((string) ($_GET['code'] ?? ''));
$oauthError = trim((string) ($_GET['error'] ?? ''));
$pendingAll = is_array($_SESSION['StrideBRIntegrationOAuth'] ?? null) ? $_SESSION['StrideBRIntegrationOAuth'] : [];
$pending = $state !== '' && is_array($pendingAll[$state] ?? null) ? $pendingAll[$state] : null;
if ($state !== '') unset($_SESSION['StrideBRIntegrationOAuth'][$state]);
$returnTo = stridebr_safe_redirect(is_array($pending) ? (string) ($pending['return'] ?? '') : '', '/user/edit-profile.php#conexoes');

if ($oauthError !== '') {
    stridebr_flash('info', 'A conexão foi cancelada.');
    header('Location: ' . $returnTo);
    exit;
}

if (!is_array($pending)
    || !hash_equals((string) ($pending['provider'] ?? ''), $provider)
    || (int) ($pending['created_at'] ?? 0) < time() - 900
    || $code === '') {
    stridebr_flash('danger', 'A tentativa de conexão expirou ou não pôde ser validada.');
    header('Location: ' . $returnTo);
    exit;
}

try {
    $token = stridebr_integrations_exchange($provider, $code);
    $connection = stridebr_integrations_save_token($pdo, $idUsuario, $provider, $token);
    if ($provider === 'polar') {
        stridebr_integrations_register_polar($pdo, $idUsuario, $connection);
        $connection = stridebr_integrations_get($pdo, $idUsuario, $provider) ?? $connection;
    }
    $providerConfig = stridebr_integrations_provider($provider);
    stridebr_flash('success', $providerConfig['label'] . ' conectado ao StrideBR.');
    try {
        $imported = stridebr_integrations_sync($pdo, $idUsuario, $provider);
        if ($imported > 0) stridebr_flash('success', $imported . ' atividade' . ($imported === 1 ? '' : 's') . ' importada' . ($imported === 1 ? '' : 's') . '.');
    } catch (Throwable $syncError) {
        error_log('StrideBR initial integration sync failed for ' . $provider . ': ' . $syncError->getMessage());
    }
} catch (Throwable $e) {
    error_log('StrideBR integration callback failed for user ' . $idUsuario . ' / ' . $provider . ': ' . $e->getMessage());
    stridebr_flash('danger', 'Não foi possível concluir a conexão agora.');
}

header('Location: ' . $returnTo);
exit;
