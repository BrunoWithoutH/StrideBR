<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';

$idUsuario = stridebr_require_login();
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/src/function/integrations.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') stridebr_error_document(405);
stridebr_verify_csrf();
$returnTo = stridebr_safe_redirect((string) ($_POST['return'] ?? ''), '/user/settings.php?view=connections#conexoes');
$provider = stridebr_lower(trim((string) ($_POST['provider'] ?? '')));
$action = trim((string) ($_POST['action'] ?? ''));

try {
    $providerConfig = stridebr_integrations_provider($provider);
    if ($action === 'disconnect') {
        stridebr_integrations_disconnect($pdo, $idUsuario, $provider);
        stridebr_flash('success', stridebr_t('integrations.disconnected', ['provider' => $providerConfig['label']]));
    } elseif ($action === 'sync') {
        $sync = stridebr_integrations_sync_detailed($pdo, $idUsuario, $provider);
        stridebr_flash(...stridebr_integrations_feedback($sync, $providerConfig['label']));
    } elseif ($action === 'preferences') {
        stridebr_integrations_update_preferences($pdo, $idUsuario, $provider, [
            'sync_activities' => isset($_POST['sync_activities']),
            'sync_workouts' => isset($_POST['sync_workouts']),
            'show_profile' => isset($_POST['show_profile']),
            'profile_url' => trim((string) ($_POST['profile_url'] ?? '')),
        ]);
        stridebr_flash('success', stridebr_t('integrations.preferences_saved', ['provider' => $providerConfig['short']]));
    } else {
        throw new InvalidArgumentException('Ação de integração inválida.');
    }
} catch (InvalidArgumentException $e) {
    stridebr_flash('danger', $e->getMessage());
} catch (Throwable $e) {
    stridebr_integrations_log_failure($provider, 'action', null, $e);
    stridebr_flash('danger', stridebr_t('integrations.update_failed'));
}

header('Location: ' . $returnTo, true, 303);
exit;
