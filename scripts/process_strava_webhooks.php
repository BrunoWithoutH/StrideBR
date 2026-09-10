<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/includes/app.php';
require_once dirname(__DIR__) . '/src/config/pg_config.php';
require_once dirname(__DIR__) . '/src/function/integrations.php';

$options = getopt('', ['limit::']); $limit = max(1, min(200, (int) ($options['limit'] ?? 50)));
if (!stridebr_db_table_exists($pdo, 'integracao_webhook_eventos')) { fwrite(STDERR, "Fila de webhooks não existe. Rode as migrations.\n"); exit(2); }
$pdo->beginTransaction();
// Recover abandoned claims, then claim a small batch with PostgreSQL row locks.
$pdo->exec("UPDATE integracao_webhook_eventos SET status='pending', processing_started_at=NULL, retry_at=NOW() WHERE status='processing' AND processed_at IS NULL AND processing_started_at < NOW() - INTERVAL '15 minutes'");
$claim = $pdo->prepare("SELECT * FROM integracao_webhook_eventos WHERE provider='strava' AND status='pending' AND retry_at <= NOW() ORDER BY id FOR UPDATE SKIP LOCKED LIMIT {$limit}");
$claim->execute(); $events = $claim->fetchAll();
foreach ($events as $event) $pdo->prepare("UPDATE integracao_webhook_eventos SET status='processing', processing_started_at=NOW(), attempts=attempts+1 WHERE id=:id")->execute([':id'=>$event['id']]);
$pdo->commit();
// Coalesce same-batch activity create/update events. A later delete deliberately wins.
$grouped = [];
foreach ($events as $event) {
    $key = $event['owner_external_id'] . ':' . $event['object_type'] . ':' . $event['object_id'];
    if (!isset($grouped[$key])) { $grouped[$key] = $event + ['_ids'=>[$event['id']]]; continue; }
    $base =& $grouped[$key]; $base['_ids'][] = $event['id'];
    if ($event['object_type'] === 'activity' && in_array($base['aspect_type'], ['create','update'], true) && in_array($event['aspect_type'], ['create','update'], true)) {
        $base['aspect_type'] = $base['aspect_type'] === 'create' ? 'create' : 'update';
        $base['updates'] = json_encode(array_merge((array)json_decode((string)$base['updates'],true), (array)json_decode((string)$event['updates'],true)), JSON_UNESCAPED_SLASHES);
        $base['signature_verified'] = $base['signature_verified'] || $event['signature_verified'];
    } elseif ($event['aspect_type'] === 'delete') { $base = $event + ['_ids'=>$base['_ids']]; }
    unset($base);
}
$events = array_values($grouped);
$complete = $ignored = $failed = 0;
foreach ($events as $event) {
    try {
        $outcome = stridebr_strava_webhook_process($pdo, $event);
        $ids = array_map('intval', $event['_ids'] ?? [$event['id']]); $placeholders = implode(',', $ids);
        $pdo->prepare("UPDATE integracao_webhook_eventos SET status=:status, processed_at=NOW(), last_error_code=NULL WHERE id IN ({$placeholders}) AND status='processing'")->execute([':status'=>$outcome === 'ignored' ? 'ignored' : 'complete']);
        if ($outcome === 'ignored') $ignored++; else $complete++;
    } catch (Throwable $error) {
        $attempts = (int) $event['attempts'] + 1;
        $code = $error instanceof StridebrIntegrationError ? $error->internalCode : 'provider_failed';
        $retry = $error instanceof StridebrIntegrationError ? max(0, $error->retryAfter) : 0;
        $terminal = $attempts >= 8 || $code === 'reauthorize';
        $seconds = $retry ?: min(21600, 60 * (2 ** min(8, $attempts)));
        $stmt = $pdo->prepare("UPDATE integracao_webhook_eventos SET status=:status, retry_at=NOW() + (:seconds * INTERVAL '1 second'), processed_at=CASE WHEN :terminal THEN NOW() ELSE NULL END, last_error_code=:code WHERE id=:id AND status='processing'");
        foreach ($event['_ids'] ?? [$event['id']] as $id) $stmt->execute([':status'=>$terminal ? 'failed' : 'pending', ':seconds'=>$seconds, ':terminal'=>$terminal, ':code'=>substr($code, 0, 64), ':id'=>$id]);
        stridebr_integrations_log_failure('strava', 'webhook_worker', (string) ($event['object_id'] ?? ''), $error); $failed++;
    }
}
printf("Eventos: %d completos | %d ignorados | %d adiados/falhos\n", $complete, $ignored, $failed);
exit(0);
