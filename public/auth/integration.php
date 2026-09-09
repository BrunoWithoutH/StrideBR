<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';

$idUsuario = stridebr_require_login();
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/src/function/integrations.php';

$provider = stridebr_lower(trim((string) ($_GET['provider'] ?? '')));
$returnTo = stridebr_safe_redirect((string) ($_GET['return'] ?? ''), '/user/edit-profile.php#conexoes');

try {
    $forceConsent = !empty($_GET['reauthorize']);
    if ($provider === 'google_health') {
        $connection = stridebr_integrations_get($pdo, $idUsuario, $provider);
        if (is_array($connection)) {
            $refresh = stridebr_integrations_decrypt((string) ($connection['refresh_token_enc'] ?? ''));
            $requested = stridebr_integrations_provider($provider)['scope'] ?? '';
            if (!$refresh || !stridebr_integrations_scopes_cover((string) ($connection['escopos'] ?? ''), $requested)) $forceConsent = true;
        }
    }
    header('Location: ' . stridebr_integrations_start($provider, $returnTo, $forceConsent));
    exit;
} catch (InvalidArgumentException $e) {
    stridebr_flash('danger', $e->getMessage());
} catch (Throwable $e) {
    error_log('StrideBR integration start failed for user ' . $idUsuario . ' / ' . $provider . ' [' . get_class($e) . ']');
    stridebr_flash('info', 'Essa conexão ainda precisa ser configurada no servidor.');
}

header('Location: ' . $returnTo);
exit;
