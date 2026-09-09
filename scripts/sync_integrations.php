<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/includes/app.php';
require_once dirname(__DIR__) . '/src/config/pg_config.php';
require_once dirname(__DIR__) . '/src/function/integrations.php';

$options = getopt('', ['provider::', 'user::', 'limit::']);
$providerFilter = stridebr_lower(trim((string) ($options['provider'] ?? '')));
$userFilter = trim((string) ($options['user'] ?? ''));
$limit = max(1, min(5000, (int) ($options['limit'] ?? 500)));
$readyProviders = stridebr_integrations_periodic_providers();

if ($providerFilter !== '' && !in_array($providerFilter, $readyProviders, true)) {
    fwrite(STDERR, "Provedor sem sincronização automática disponível: {$providerFilter}\n");
    exit(2);
}

$lock = fopen(sys_get_temp_dir() . '/stridebr-integrations-sync.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "Outra sincronização de integrações já está em andamento.\n");
    exit(0);
}

if (!stridebr_db_table_exists($pdo, 'integracoes_usuario')) {
    fwrite(STDERR, "Tabela de integrações ainda não existe. Rode as migrations.\n");
    exit(2);
}

$connections = stridebr_integrations_due_connections($pdo, $providerFilter, $userFilter, $limit);

$processed = 0;
$imported = 0;
$skipped = 0;
$failed = 0;
foreach ($connections as $connection) {
    $userId = (string) $connection['idusuario'];
    $providerId = (string) $connection['provedor'];
    try {
        $provider = stridebr_integrations_provider($providerId);
        if (!stridebr_integrations_configured($provider)) {
            $skipped++;
            continue;
        }
        $sync = stridebr_integrations_sync_detailed($pdo, $userId, $providerId, 'periodic');
        if (!empty($sync['skipped'])) { $skipped++; continue; }
        $failed += (int) ($sync['failed'] ?? 0);
        $created = (int) ($sync['created'] ?? 0);
        $processed++;
        $imported += $created;
        printf("%s: +%d novas | %d existentes | %d falhas\n", $providerId, $created, (int) ($sync['existing'] ?? 0), (int) ($sync['failed'] ?? 0));
    } catch (Throwable $error) {
        $processed++;
        $failed++;
        stridebr_integrations_log_failure($providerId, 'periodic_runner', null, $error);
        fwrite(STDERR, $providerId . ': sincronização falhou.' . PHP_EOL);
    }
}

printf("Conexões processadas: %d | atividades importadas: %d | ignoradas: %d | falhas: %d\n", $processed, $imported, $skipped, $failed);
exit($failed > 0 ? 1 : 0);
