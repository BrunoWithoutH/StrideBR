<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';

$idUsuario = stridebr_require_login();
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/src/function/integrations.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') stridebr_error_document(405);
stridebr_verify_csrf();
$returnTo = stridebr_safe_redirect((string) ($_POST['return'] ?? ''), '/user/edit-profile.php#conexoes');
$provider = stridebr_lower(trim((string) ($_POST['provider'] ?? '')));
$action = trim((string) ($_POST['action'] ?? ''));

try {
    $providerConfig = stridebr_integrations_provider($provider);
    if ($action === 'disconnect') {
        stridebr_integrations_disconnect($pdo, $idUsuario, $provider);
        stridebr_flash('success', $providerConfig['label'] . ' desconectado.');
    } elseif ($action === 'sync') {
        $sync = stridebr_integrations_sync_detailed($pdo, $idUsuario, $provider);
        $count = (int) ($sync['created'] ?? 0);
        $existing = (int) ($sync['existing'] ?? 0);
        $failed = (int) ($sync['failed'] ?? 0);
        stridebr_flash($failed > 0 ? 'info' : 'success', $count . ' novas · ' . $existing . ' já existentes · ' . $failed . ' falharam.');
    } elseif ($action === 'preferences') {
        stridebr_integrations_update_preferences($pdo, $idUsuario, $provider, [
            'sync_activities' => isset($_POST['sync_activities']),
            'sync_workouts' => isset($_POST['sync_workouts']),
            'show_profile' => isset($_POST['show_profile']),
            'profile_url' => trim((string) ($_POST['profile_url'] ?? '')),
        ]);
        stridebr_flash('success', 'Preferências de ' . $providerConfig['short'] . ' salvas.');
    } else {
        throw new InvalidArgumentException('Ação de integração inválida.');
    }
} catch (InvalidArgumentException $e) {
    stridebr_flash('danger', $e->getMessage());
} catch (Throwable $e) {
    error_log('StrideBR integration action failed for user ' . $idUsuario . ' / ' . $provider . ' [' . get_class($e) . ']');
    stridebr_flash('danger', 'Não foi possível atualizar essa conexão agora.');
}

header('Location: ' . $returnTo, true, 303);
exit;
