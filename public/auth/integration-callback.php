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
    stridebr_flash('info', stridebr_t('integrations.connection_cancelled'));
    header('Location: ' . $returnTo);
    exit;
}

if (!is_array($pending)
    || !hash_equals((string) ($pending['provider'] ?? ''), $provider)
    || (int) ($pending['created_at'] ?? 0) < time() - 900
    || $code === '') {
    stridebr_flash('danger', stridebr_t('integrations.connection_expired'));
    header('Location: ' . $returnTo);
    exit;
}

try {
    $token = stridebr_integrations_exchange($provider, $code, $pending);
    $connection = stridebr_integrations_save_token($pdo, $idUsuario, $provider, $token);
    $providerConfig = stridebr_integrations_provider($provider);
    // OAuth reauthorization releases auth/backoff state, preserving the user's preferences.
    stridebr_integrations_sync_state($pdo, $idUsuario, $provider, []);
    stridebr_flash('success', stridebr_t('integrations.connected', ['provider' => $providerConfig['label']]));
    if (in_array($provider, stridebr_integrations_periodic_providers(), true) && stridebr_db_bool($connection['sincronizar_atividades'] ?? true)) {
        stridebr_flash('info', stridebr_t('integrations.automatic_help'));
    }
    try {
        $sync = stridebr_integrations_initial_sync($pdo, $idUsuario, $provider);
        if (empty($sync['skipped'])) stridebr_flash(...stridebr_integrations_feedback($sync, $providerConfig['label']));
    } catch (Throwable $syncError) {
        stridebr_integrations_log_failure($provider, 'initial_sync', null, $syncError);
        stridebr_flash('info', stridebr_t('integrations.try_later'));
    }
} catch (Throwable $e) {
    stridebr_integrations_log_failure($provider, 'oauth', null, $e);
    stridebr_flash('danger', stridebr_t('integrations.connection_failed'));
}

header('Location: ' . $returnTo);
exit;
