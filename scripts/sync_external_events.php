<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/includes/app.php';
require_once dirname(__DIR__) . '/src/config/pg_config.php';
require_once dirname(__DIR__) . '/src/function/external_events.php';

$options = getopt('', ['provider::', 'deadline::']);
$providerFilter = strtolower(trim((string) ($options['provider'] ?? '')));
$deadlineSeconds = max(15, min(300, (int) ($options['deadline'] ?? 120)));
$providers = externalEventsProviders();
if ($providerFilter !== '' && !isset($providers[$providerFilter])) {
    fwrite(STDERR, "Provider desconhecido: {$providerFilter}\n");
    exit(2);
}

$lock = fopen(sys_get_temp_dir() . '/stridebr-external-events-sync.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "Outra sincronização de eventos externos já está em andamento.\n");
    exit(0);
}

if (!externalEventsSchemaAvailable($pdo)) {
    fwrite(STDERR, "Schema de eventos externos indisponível. Rode as migrations.\n");
    exit(2);
}

$selected = $providerFilter !== '' ? [$providerFilter => $providers[$providerFilter]] : $providers;
$batch = externalEventsSyncProviders($pdo, $selected, null, microtime(true) + $deadlineSeconds);
$processed = 0;
$skipped = 0;
$events = 0;
$created = 0;
$updated = 0;

foreach ($batch['results'] as $key => $result) {
    if (!empty($result['skipped'])) {
        $skipped++;
        printf("%s: ignorado (%s)\n", $key, (string) ($result['reason'] ?? 'indisponível'));
        continue;
    }
    $processed++;
    $events += (int) ($result['events'] ?? 0);
    $created += (int) ($result['created'] ?? 0);
    $updated += (int) ($result['updated'] ?? 0);
    printf("%s: %d evento(s) | +%d | %d atualizado(s) | %d manual(is) preservado(s)\n", $key, (int) ($result['events'] ?? 0), (int) ($result['created'] ?? 0), (int) ($result['updated'] ?? 0), (int) ($result['manual_preserved'] ?? 0));
}
foreach ($batch['failures'] as $key => $error) {
    $processed++;
    fwrite(STDERR, $key . ': ' . $error . PHP_EOL);
}
$failed = count($batch['failures']);
printf("Providers processados: %d | ignorados: %d | eventos: %d | criados: %d | atualizados: %d | falhas: %d\n", $processed, $skipped, $events, $created, $updated, $failed);
exit($failed > 0 ? 1 : 0);
