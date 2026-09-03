<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';

$idUsuario = stridebr_require_login();
require_once dirname(__DIR__, 2) . '/src/function/integrations.php';

$provider = stridebr_lower(trim((string) ($_GET['provider'] ?? '')));
$returnTo = stridebr_safe_redirect((string) ($_GET['return'] ?? ''), '/user/edit-profile.php#conexoes');

try {
    header('Location: ' . stridebr_integrations_start($provider, $returnTo));
    exit;
} catch (InvalidArgumentException $e) {
    stridebr_flash('danger', $e->getMessage());
} catch (Throwable $e) {
    error_log('StrideBR integration start failed for user ' . $idUsuario . ': ' . $e->getMessage());
    stridebr_flash('info', 'Essa conexão ainda precisa ser configurada no servidor.');
}

header('Location: ' . $returnTo);
exit;
