<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/includes/app.php';
require_once dirname(__DIR__) . '/src/config/pg_config.php';
require_once dirname(__DIR__) . '/src/function/integrations.php';

$options = getopt('', ['provider::', 'user::', 'limit::']);
$providerFilter = stridebr_lower(trim((string) ($options['provider'] ?? '')));
$userFilter = trim((string) ($options['user'] ?? ''));
$limit = max(1, min(5000, (int) ($options['limit'] ?? 500)));
$readyProviders = ['strava', 'polar', 'google_health', 'suunto'];

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

$where = ["(status = 'conectado' OR (status = 'erro' AND atualizado_em < NOW() - INTERVAL '15 minutes'))", 'sincronizar_atividades = TRUE'];
$params = [];
if ($providerFilter !== '') {
    $where[] = 'provedor = :provedor';
    $params[':provedor'] = $providerFilter;
} else {
    $placeholders = [];
    foreach ($readyProviders as $index => $provider) {
        $key = ':p' . $index;
        $placeholders[] = $key;
        $params[$key] = $provider;
    }
    $where[] = 'provedor IN (' . implode(',', $placeholders) . ')';
}
if ($userFilter !== '') {
    $where[] = 'idusuario = :usuario';
    $params[':usuario'] = $userFilter;
}

$stmt = $pdo->prepare('SELECT idusuario, provedor FROM integracoes_usuario WHERE ' . implode(' AND ', $where) . ' ORDER BY COALESCE(ultima_sincronizacao_em, to_timestamp(0)) ASC LIMIT ' . $limit);
$stmt->execute($params);
$connections = $stmt->fetchAll();

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
        $sync = stridebr_integrations_sync_detailed($pdo, $userId, $providerId);
        $created = (int) ($sync['created'] ?? 0);
        $processed++;
        $imported += $created;
        printf("%s %s: +%d novas | %d existentes | %d falhas\n", $providerId, $userId, $created, (int) ($sync['existing'] ?? 0), (int) ($sync['failed'] ?? 0));
    } catch (Throwable $error) {
        $processed++;
        $failed++;
        fwrite(STDERR, $providerId . ' ' . $userId . ': sincronização falhou [' . get_class($error) . ']' . PHP_EOL);
    }
}

printf("Conexões processadas: %d | atividades importadas: %d | ignoradas: %d | falhas: %d\n", $processed, $imported, $skipped, $failed);
exit($failed > 0 ? 1 : 0);
