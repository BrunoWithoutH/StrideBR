<?php

declare(strict_types=1);

/** Only structured, allowlisted diagnostics cross the provider boundary. */
final class StridebrIntegrationError extends RuntimeException
{
    public function __construct(
        public readonly string $stage,
        public readonly string $internalCode,
        public readonly ?int $httpStatus = null,
        public readonly int $retryAfter = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct('External activity synchronization failed.', 0, $previous);
    }
}

function stridebr_integrations_http_error(int $status, array $headers): StridebrIntegrationError
{
    $raw = $headers['retry-after'][0] ?? '';
    $retry = ctype_digit((string) $raw) ? (int) $raw : max(0, (strtotime((string) $raw) ?: time()) - time());
    return new StridebrIntegrationError('http', match ($status) {
        401 => 'reauthorize', 429 => 'rate_limit', default => 'provider_http',
    }, $status, min(172800, $retry));
}

function stridebr_integrations_log_failure(string $provider, string $stage, ?string $externalId, Throwable $error): void
{
    $cause = $error instanceof StridebrIntegrationError ? ($error->getPrevious() ?? $error) : $error;
    $record = [
        'provider' => preg_replace('/[^a-z_]/', '', $provider),
        'stage' => $error instanceof StridebrIntegrationError && $error->stage !== 'http' ? $error->stage : $stage,
        'external_activity_id' => $externalId === null ? null : substr(preg_replace('/[^a-zA-Z0-9_-]/', '', $externalId), 0, 190),
        'http_status' => $error instanceof StridebrIntegrationError ? $error->httpStatus : null,
        'exception_type' => get_class($cause),
        'error_code' => $error instanceof StridebrIntegrationError ? $error->internalCode : 'sync_failed',
    ];
    if ($cause instanceof PDOException && preg_match('/^[0-9A-Z]{5}$/', (string) $cause->getCode())) $record['sqlstate'] = (string) $cause->getCode();
    // Never include exception messages, traces, request headers, URLs or response bodies.
    error_log('StrideBR integration ' . json_encode($record, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
}

function stridebr_integrations_periodic_providers(): array
{
    return ['strava', 'polar', 'google_health', 'suunto'];
}

function stridebr_integrations_periodic_interval(string $provider): int
{
    // Strava webhooks are primary; the periodic runner is only reconciliation.
    return $provider === 'strava' ? 21600 : 900;
}

function stridebr_integrations_eligible(array $connection, string $trigger = 'periodic', ?int $now = null): bool
{
    $now ??= time();
    if (!in_array($connection['status'] ?? '', ['conectado', 'erro'], true)) return false;
    $provider = (string) ($connection['provedor'] ?? '');
    if ($trigger === 'periodic' && !in_array($provider, stridebr_integrations_periodic_providers(), true)) return false;
    if (!stridebr_db_bool($connection['sincronizar_atividades'] ?? true)) return false;
    $metadata = stridebr_integrations_metadata($connection);
    $sync = is_array($metadata['sync'] ?? null) ? $metadata['sync'] : [];
    if (!empty($sync['reauthorize'])) return false;
    if ((int) ($sync['retry_at'] ?? 0) > $now) return false;
    if (($connection['status'] ?? '') === 'erro' && !isset($sync['retry_at']) && (strtotime((string) ($connection['atualizado_em'] ?? '')) ?: 0) + 900 > $now) return false;
    if ($trigger === 'periodic') {
        $recentDue = (strtotime((string) ($connection['ultima_sincronizacao_em'] ?? '')) ?: 0) + stridebr_integrations_periodic_interval($provider) <= $now;
        if ($provider === 'strava') {
            $backfill = is_array($metadata['backfill'] ?? null) ? $metadata['backfill'] : [];
            $backfillDue = ($backfill['status'] ?? 'pending') !== 'completed' && (int) ($backfill['retry_at'] ?? 0) <= $now;
            if (!$recentDue && !$backfillDue) return false;
        } elseif (!$recentDue) return false;
    }
    return true;
}

function stridebr_integrations_sync_state(PDO $pdo, string $userId, string $provider, array $sync): void
{
    $stmt = $pdo->prepare("UPDATE integracoes_usuario SET metadados = jsonb_set(CASE WHEN jsonb_typeof(metadados) = 'object' THEN metadados WHEN jsonb_typeof(metadados) = 'array' AND jsonb_array_length(metadados) > 0 THEN jsonb_build_object('_legacy_array', metadados) ELSE '{}'::jsonb END, '{sync}', CAST(:sync AS jsonb), true) WHERE idusuario = :usuario AND provedor = :provedor");
    $stmt->execute([':sync' => json_encode($sync, JSON_THROW_ON_ERROR), ':usuario' => $userId, ':provedor' => $provider]);
}

function stridebr_integrations_feedback(array $result, string $provider): array
{
    $messages = [];
    if (($result['created'] ?? 0) > 0) $messages[] = stridebr_t($result['created'] === 1 ? 'integrations.imported_one' : 'integrations.imported_many', ['count' => $result['created']]);
    if (!empty($result['backfill_only_failure'])) {
        $messages[] = stridebr_t('integrations.waiting');
    } elseif (($result['failed'] ?? 0) > 0) {
        $messages[] = stridebr_t($result['failed'] === 1 ? 'integrations.failed_one' : 'integrations.failed_many', ['count' => $result['failed'], 'provider' => $provider]);
        $messages[] = stridebr_t('integrations.try_later');
    } elseif (($result['skipped'] ?? '') === 'busy') {
        $messages[] = stridebr_t('integrations.already_running');
    } elseif (!empty($result['deferred']) || !empty($result['skipped']) || !empty($result['backfill_paused']) || !empty($result['backfill_retry'])) {
        $messages[] = stridebr_t('integrations.waiting');
    } elseif ($messages === []) {
        $messages[] = stridebr_t('integrations.up_to_date');
    }
    return [!empty($result['failed']) || !empty($result['deferred']) || !empty($result['skipped']) ? 'info' : 'success', implode(' ', $messages)];
}

function stridebr_integrations_initial_sync(PDO $pdo, string $userId, string $provider): array
{
    return stridebr_integrations_sync_detailed($pdo, $userId, $provider, 'initial');
}

function stridebr_integrations_due_connections(PDO $pdo, string $providerFilter = '', string $userFilter = '', int $limit = 500): array
{
    $readyProviders = stridebr_integrations_periodic_providers();
    if ($providerFilter !== '' && !in_array($providerFilter, $readyProviders, true)) return [];
    $limit = max(1, min(5000, $limit));
    $where = [
        "(status = 'conectado' OR (status = 'erro' AND atualizado_em < NOW() - INTERVAL '15 minutes'))",
        'sincronizar_atividades = TRUE',
        "COALESCE((metadados->'sync'->>'retry_at')::bigint, 0) <= EXTRACT(EPOCH FROM NOW())",
        "COALESCE((metadados->'sync'->>'reauthorize')::boolean, FALSE) = FALSE",
        "(provedor <> 'strava' OR ultima_sincronizacao_em IS NULL OR ultima_sincronizacao_em <= NOW() - INTERVAL '6 hours' OR (COALESCE(metadados->'backfill'->>'status','pending') <> 'completed' AND COALESCE((metadados->'backfill'->>'retry_at')::bigint,0) <= EXTRACT(EPOCH FROM NOW())))",
        "(provedor = 'strava' OR ultima_sincronizacao_em IS NULL OR ultima_sincronizacao_em <= NOW() - INTERVAL '15 minutes')",
    ];
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
    $order = "CASE WHEN provedor='strava' AND COALESCE(metadados->'backfill'->>'status','pending') <> 'completed' THEN COALESCE((metadados->'backfill'->>'last_attempt_at')::bigint,0) ELSE EXTRACT(EPOCH FROM COALESCE(ultima_sincronizacao_em,to_timestamp(0))) END ASC";
    $stmt = $pdo->prepare('SELECT idusuario, provedor FROM integracoes_usuario WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $order . ' LIMIT ' . $limit);
    $stmt->execute($params);
    return $stmt->fetchAll();
}
