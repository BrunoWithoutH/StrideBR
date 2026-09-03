<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/includes/env.php';
require_once dirname(__DIR__) . '/src/includes/app.php';
require_once dirname(__DIR__) . '/src/function/integrations.php';

$loaded = defined('STRIDEBR_ENV_FILE_LOADED') ? (string) STRIDEBR_ENV_FILE_LOADED : '';
echo 'Ambiente: ' . (getenv('STRIDEBR_APP_ENV') ?: 'development') . PHP_EOL;
echo '.env: ' . ($loaded !== '' ? $loaded : 'não carregado (podem existir variáveis do sistema)') . PHP_EOL;
try {
    $appUrl = stridebr_app_url();
    echo 'URL do app: ' . $appUrl . PHP_EOL;
} catch (Throwable $e) {
    $appUrl = '';
    echo 'URL do app: ERRO - ' . $e->getMessage() . PHP_EOL;
}
echo 'Segredo das integrações: ' . (stridebr_integrations_secret() !== null ? 'OK' : 'FALTANDO (mínimo 32 caracteres)') . PHP_EOL;
echo PHP_EOL;

foreach (stridebr_integrations_registry() as $id => $provider) {
    $kind = (string) ($provider['kind'] ?? '');
    if ($kind !== 'cloud') {
        echo sprintf("%-15s %s\n", $provider['label'] . ':', $kind === 'mobile' ? 'requer app móvel' : 'ponte via app/Health Connect');
        continue;
    }
    if (array_key_exists('implementation_ready', $provider) && !$provider['implementation_ready']) {
        echo sprintf("%-15s %s\n", $provider['label'] . ':', 'PREPARADO — aguardando validação do adaptador com o Developer Portal');
        if ($appUrl !== '') echo '  callback previsto: ' . stridebr_integrations_callback_uri((string) $id) . PHP_EOL;
        continue;
    }
    $missing = [];
    if (trim((string) ($provider['client_id'] ?? '')) === '') $missing[] = 'client id';
    if (trim((string) ($provider['client_secret'] ?? '')) === '') $missing[] = 'client secret';
    if (!filter_var((string) ($provider['authorize_url'] ?? ''), FILTER_VALIDATE_URL)) $missing[] = 'authorize URL';
    if (!filter_var((string) ($provider['token_url'] ?? ''), FILTER_VALIDATE_URL)) $missing[] = 'token URL';
    if ($id === 'suunto' && trim((string) ($provider['subscription_key'] ?? '')) === '') $missing[] = 'subscription key';
    if (stridebr_integrations_secret() === null) $missing[] = 'STRIDEBR_INTEGRATIONS_SECRET';
    $status = $missing === [] ? 'PRONTO' : 'pendente: ' . implode(', ', array_unique($missing));
    echo sprintf("%-15s %s\n", $provider['label'] . ':', $status);
    if ($appUrl !== '') echo '  callback: ' . stridebr_integrations_callback_uri((string) $id) . PHP_EOL;
}
