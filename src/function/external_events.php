<?php

declare(strict_types=1);

require_once __DIR__ . '/external_events/provider.php';
require_once __DIR__ . '/external_events/http.php';
require_once __DIR__ . '/external_events/providers/faergs.php';
require_once __DIR__ . '/external_events/providers/cbat.php';
require_once __DIR__ . '/external_events/providers/cbc.php';
require_once __DIR__ . '/external_events/providers/fgc.php';
require_once __DIR__ . '/external_events/providers/cbtri.php';
require_once __DIR__ . '/eventos.php';

function externalEventsProviders(): array
{
    static $providers = null;
    if (is_array($providers)) return $providers;
    $items = [
        new ExternalEventsFaergsProvider(),
        new ExternalEventsCbatProvider(),
        new ExternalEventsCbcProvider(),
        new ExternalEventsFgcProvider(),
        new ExternalEventsCbtriProvider(),
    ];
    $providers = [];
    foreach ($items as $provider) $providers[$provider->key()] = $provider;
    return $providers;
}

function externalEventsProvider(string $key): ExternalEventsProvider
{
    $key = strtolower(trim($key));
    $providers = externalEventsProviders();
    if (!isset($providers[$key])) throw new InvalidArgumentException('external_provider_unknown');
    return $providers[$key];
}

function externalEventsSchemaAvailable(PDO $pdo): bool
{
    return stridebr_db_table_exists($pdo, 'eventos_modalidades')
        && stridebr_db_table_exists($pdo, 'eventos_provas')
        && stridebr_db_table_exists($pdo, 'eventos_fontes_externas')
        && stridebr_db_table_exists($pdo, 'eventos_provedores_sync');
}

function externalEventsProviderStates(PDO $pdo): array
{
    if (!externalEventsSchemaAvailable($pdo)) return [];
    $rows = $pdo->query('SELECT * FROM eventos_provedores_sync ORDER BY provider')->fetchAll();
    $result = [];
    foreach ($rows as $row) $result[(string) $row['provider']] = $row;
    return $result;
}

function externalEventsProviderState(PDO $pdo, string $provider): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM eventos_provedores_sync WHERE provider=:provider LIMIT 1');
    $stmt->execute([':provider' => $provider]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function externalEventsProviderCache(array $state): array
{
    $stats = $state['stats'] ?? [];
    if (!is_array($stats)) $stats = json_decode((string) $stats, true) ?: [];
    return is_array($stats['http_cache'] ?? null) ? $stats['http_cache'] : [];
}

function externalEventsExecuteStatement(PDOStatement $stmt, array $params): void
{
    foreach ($params as $name => $value) {
        $type = PDO::PARAM_STR;
        if ($value === null) $type = PDO::PARAM_NULL;
        elseif (is_bool($value)) $type = PDO::PARAM_BOOL;
        elseif (is_int($value)) $type = PDO::PARAM_INT;
        $stmt->bindValue($name, $value, $type);
    }
    $stmt->execute();
}

function externalEventsUpdateProviderState(PDO $pdo, string $provider, array $changes): void
{
    $allowed = ['enabled','auto_publish','compliance_status','last_attempt_at','last_success_at','last_error_code','consecutive_failures','etag','last_modified','next_sync_at','stats'];
    $sets = [];
    $params = [':provider' => $provider];
    foreach ($changes as $key => $value) {
        if (!in_array($key, $allowed, true)) continue;
        $sets[] = $key . '=:' . $key;
        if ($key === 'stats' && is_array($value)) $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $params[':' . $key] = $value;
    }
    if ($sets === []) return;
    $sql = 'UPDATE eventos_provedores_sync SET ' . implode(',', $sets) . ', data_atualizacao=NOW() WHERE provider=:provider';
    $stmt = $pdo->prepare($sql);
    externalEventsExecuteStatement($stmt, $params);
}

function externalEventsSportIds(PDO $pdo, array $slugs): array
{
    $slugs = array_values(array_unique(array_filter(array_map('strval', $slugs))));
    if ($slugs === []) return [];
    $stmt = $pdo->prepare('SELECT idmodalidade,slug FROM modalidades WHERE idusuario IS NULL AND ativo=TRUE AND lower(slug)=lower(:slug) LIMIT 1');
    $result = [];
    foreach ($slugs as $slug) {
        $stmt->execute([':slug' => $slug]);
        $row = $stmt->fetch();
        if ($row) $result[$slug] = (string) $row['idmodalidade'];
    }
    return $result;
}

function externalEventsExistingByIdentity(PDO $pdo, string $provider, string $identityKey): ?array
{
    $stmt = $pdo->prepare('SELECT e.*,m.slug AS modalidade_slug,x.idvinculo,x.identity_key FROM eventos_fontes_externas x JOIN eventos_esportivos e ON e.idevento=x.idevento LEFT JOIN modalidades m ON m.idmodalidade=e.idmodalidade WHERE x.provider=:provider AND x.identity_key=:identity LIMIT 1');
    $stmt->execute([':provider' => $provider, ':identity' => $identityKey]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function externalEventsDedupeMatch(PDO $pdo, array $event): array
{
    $date = substr((string) $event['start_at'], 0, 10);
    $city = trim((string) ($event['city'] ?? ''));
    $state = trim((string) ($event['state'] ?? ''));
    $stmt = $pdo->prepare("SELECT e.idevento,e.titulo,e.origem,e.sincronizacao_bloqueada,m.slug AS modalidade_slug FROM eventos_esportivos e LEFT JOIN modalidades m ON m.idmodalidade=e.idmodalidade WHERE e.data_inicio::date=CAST(:date AS date) AND lower(COALESCE(e.cidade,''))=lower(:city) AND lower(COALESCE(e.estado,''))=lower(:state) LIMIT 20");
    $stmt->execute([':date' => $date, ':city' => $city, ':state' => $state]);
    $expectedTitle = externalEventsComparableTitle((string) $event['title'], (string) ($event['primary_sport'] ?? ''));
    $expectedSport = trim((string) ($event['primary_sport'] ?? ''));
    $matches = [];
    foreach ($stmt->fetchAll() as $row) {
        $rowSport = trim((string) ($row['modalidade_slug'] ?? ''));
        if ($expectedSport !== '' && $rowSport !== '' && $expectedSport !== $rowSport && !($expectedSport === 'atletismo' && str_starts_with($rowSport, 'atletismo'))) continue;
        $candidate = externalEventsComparableTitle((string) $row['titulo'], $expectedSport !== '' ? $expectedSport : null);
        if ($candidate === $expectedTitle) $matches[] = $row;
    }
    return ['event' => count($matches) === 1 ? $matches[0] : null, 'ambiguous' => count($matches) > 1];
}

function externalEventsEventStatus(array $event, bool $autoPublish, ?array $existing = null): string
{
    if (($event['status'] ?? '') === 'cancelled') return 'cancelado';
    if ($existing !== null) {
        $current = (string) ($existing['status'] ?? 'rascunho');
        if (in_array($current, ['publicado', 'cancelado'], true)) return $current;
    }
    return $autoPublish ? 'publicado' : 'rascunho';
}

function externalEventsInsertEvent(PDO $pdo, array $event, bool $autoPublish, array $sportIds): string
{
    $id = stridebr_generate_id();
    $title = trim((string) $event['title']);
    if ($title === '' || strlen($title) > 160) throw new InvalidArgumentException('external_event_title_invalid');
    $slug = eventosSlugUnico($pdo, $title);
    $primarySlug = trim((string) ($event['primary_sport'] ?? ''));
    $primaryId = $primarySlug !== '' ? ($sportIds[$primarySlug] ?? null) : null;
    $start = new DateTimeImmutable((string) $event['start_at']);
    $end = !empty($event['end_at']) ? new DateTimeImmutable((string) $event['end_at']) : null;
    if ($end && $end < $start) throw new InvalidArgumentException('external_event_period_invalid');
    $stmt = $pdo->prepare('INSERT INTO eventos_esportivos (idevento,titulo,slug,idmodalidade,tipo,data_inicio,data_fim,cidade,estado,pais,local_nome,endereco,organizador,distancias,url_oficial,url_inscricao,inscricoes_ate,status,destaque,origem,sincronizacao_bloqueada,seo_indexavel,horario_informado) VALUES (:id,:titulo,:slug,:modalidade,:tipo,:inicio,:fim,:cidade,:estado,:pais,:local,:endereco,:organizador,CAST(:distancias AS jsonb),:oficial,:inscricao,:limite,:status,FALSE,\'externo\',FALSE,FALSE,:horario)');
    $params = [
        ':id' => $id,
        ':titulo' => $title,
        ':slug' => $slug,
        ':modalidade' => $primaryId,
        ':tipo' => trim((string) ($event['event_type'] ?? '')) ?: null,
        ':inicio' => $start->format('Y-m-d H:i:sP'),
        ':fim' => $end?->format('Y-m-d H:i:sP'),
        ':cidade' => trim((string) ($event['city'] ?? '')) ?: null,
        ':estado' => trim((string) ($event['state'] ?? '')) ?: null,
        ':pais' => trim((string) ($event['country'] ?? '')) ?: 'Brasil',
        ':local' => trim((string) ($event['venue'] ?? '')) ?: null,
        ':endereco' => trim((string) ($event['address'] ?? '')) ?: null,
        ':organizador' => trim((string) ($event['organizer'] ?? '')) ?: null,
        ':distancias' => json_encode(array_values((array) ($event['distances'] ?? [])), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        ':oficial' => eventosUrl($event['official_url'] ?? $event['source_url'] ?? null),
        ':inscricao' => eventosUrl($event['registration_url'] ?? null),
        ':limite' => !empty($event['registration_deadline']) ? (new DateTimeImmutable((string) $event['registration_deadline']))->format('Y-m-d H:i:sP') : null,
        ':status' => externalEventsEventStatus($event, $autoPublish),
        ':horario' => !empty($event['time_known']),
    ];
    externalEventsExecuteStatement($stmt, $params);
    return $id;
}

function externalEventsUpdateEvent(PDO $pdo, string $eventId, array $event, bool $autoPublish, array $sportIds, array $existing): void
{
    if ((string) ($existing['origem'] ?? 'manual') !== 'externo' || stridebr_db_bool($existing['sincronizacao_bloqueada'] ?? false)) return;
    $primarySlug = trim((string) ($event['primary_sport'] ?? ''));
    $primaryId = $primarySlug !== '' ? ($sportIds[$primarySlug] ?? null) : null;
    $start = new DateTimeImmutable((string) $event['start_at']);
    $end = !empty($event['end_at']) ? new DateTimeImmutable((string) $event['end_at']) : null;
    if ($end && $end < $start) throw new InvalidArgumentException('external_event_period_invalid');
    $stmt = $pdo->prepare('UPDATE eventos_esportivos SET titulo=:titulo,idmodalidade=:modalidade,tipo=:tipo,data_inicio=:inicio,data_fim=:fim,cidade=:cidade,estado=:estado,pais=:pais,local_nome=:local,endereco=:endereco,organizador=:organizador,distancias=CAST(:distancias AS jsonb),url_oficial=:oficial,url_inscricao=:inscricao,inscricoes_ate=:limite,status=:status,horario_informado=:horario,data_atualizacao=NOW() WHERE idevento=:id');
    $params = [
        ':id' => $eventId,
        ':titulo' => trim((string) $event['title']),
        ':modalidade' => $primaryId,
        ':tipo' => trim((string) ($event['event_type'] ?? '')) ?: null,
        ':inicio' => $start->format('Y-m-d H:i:sP'),
        ':fim' => $end?->format('Y-m-d H:i:sP'),
        ':cidade' => trim((string) ($event['city'] ?? '')) ?: null,
        ':estado' => trim((string) ($event['state'] ?? '')) ?: null,
        ':pais' => trim((string) ($event['country'] ?? '')) ?: 'Brasil',
        ':local' => trim((string) ($event['venue'] ?? '')) ?: null,
        ':endereco' => trim((string) ($event['address'] ?? '')) ?: null,
        ':organizador' => trim((string) ($event['organizer'] ?? '')) ?: null,
        ':distancias' => json_encode(array_values((array) ($event['distances'] ?? [])), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        ':oficial' => eventosUrl($event['official_url'] ?? $event['source_url'] ?? null),
        ':inscricao' => eventosUrl($event['registration_url'] ?? null),
        ':limite' => !empty($event['registration_deadline']) ? (new DateTimeImmutable((string) $event['registration_deadline']))->format('Y-m-d H:i:sP') : null,
        ':status' => externalEventsEventStatus($event, $autoPublish, $existing),
        ':horario' => !empty($event['time_known']),
    ];
    externalEventsExecuteStatement($stmt, $params);
}

function externalEventsSyncModalities(PDO $pdo, string $eventId, array $event, array $sportIds, bool $allowWrite): void
{
    if (!$allowWrite) return;
    $slugs = array_values(array_unique(array_filter(array_merge((array) ($event['sports'] ?? []), [(string) ($event['primary_sport'] ?? '')]))));
    $ids = [];
    foreach ($slugs as $slug) if (isset($sportIds[$slug])) $ids[$slug] = $sportIds[$slug];
    if ($ids === []) return;
    $pdo->prepare('DELETE FROM eventos_modalidades WHERE idevento=:evento')->execute([':evento' => $eventId]);
    $insert = $pdo->prepare('INSERT INTO eventos_modalidades (idevento,idmodalidade,principal,ordem) VALUES (:evento,:modalidade,:principal,:ordem) ON CONFLICT (idevento,idmodalidade) DO UPDATE SET principal=EXCLUDED.principal,ordem=EXCLUDED.ordem');
    $primary = (string) ($event['primary_sport'] ?? '');
    $order = 1;
    foreach ($ids as $slug => $id) {
        externalEventsExecuteStatement($insert, [
            ':evento' => $eventId,
            ':modalidade' => $id,
            ':principal' => $slug === $primary,
            ':ordem' => $order++,
        ]);
    }
}

function externalEventsSyncProgram(PDO $pdo, string $eventId, array $event, array $sportIds, bool $allowWrite): void
{
    $program = array_values(array_filter((array) ($event['program'] ?? []), 'is_array'));
    if (!$allowWrite || $program === []) return;
    $pdo->prepare('DELETE FROM eventos_provas WHERE idevento=:evento')->execute([':evento' => $eventId]);
    $insert = $pdo->prepare('INSERT INTO eventos_provas (idprova,idevento,idmodalidade,nome,grupo,categoria,sexo,distancia_m,data_hora,fase,ordem,metadados) VALUES (:id,:evento,:modalidade,:nome,:grupo,:categoria,:sexo,:distancia,:data_hora,:fase,:ordem,CAST(:metadata AS jsonb))');
    foreach ($program as $index => $item) {
        $name = trim((string) ($item['name'] ?? ''));
        if ($name === '') continue;
        $metadata = is_array($item['metadata'] ?? null) ? $item['metadata'] : [];
        if (!empty($item['time'])) $metadata['time'] = (string) $item['time'];
        $insert->execute([
            ':id' => stridebr_generate_id(),
            ':evento' => $eventId,
            ':modalidade' => isset($item['sport']) && isset($sportIds[(string) $item['sport']]) ? $sportIds[(string) $item['sport']] : null,
            ':nome' => substr($name, 0, 180),
            ':grupo' => trim((string) ($item['group'] ?? '')) ?: null,
            ':categoria' => trim((string) ($item['category'] ?? '')) ?: null,
            ':sexo' => trim((string) ($item['sex'] ?? '')) ?: null,
            ':distancia' => isset($item['distance_m']) && is_numeric($item['distance_m']) ? (float) $item['distance_m'] : null,
            ':data_hora' => !empty($item['datetime']) ? (new DateTimeImmutable((string) $item['datetime']))->format('Y-m-d H:i:sP') : null,
            ':fase' => trim((string) ($item['phase'] ?? '')) ?: null,
            ':ordem' => $index + 1,
            ':metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        ]);
    }
}

function externalEventsEnsurePublicSource(PDO $pdo, string $eventId, string $label, string $url): void
{
    $check = $pdo->prepare('SELECT 1 FROM eventos_fontes WHERE idevento=:evento AND url=:url LIMIT 1');
    $check->execute([':evento' => $eventId, ':url' => $url]);
    if ($check->fetchColumn()) return;
    $order = $pdo->prepare('SELECT COALESCE(MAX(ordem),0)+1 FROM eventos_fontes WHERE idevento=:evento');
    $order->execute([':evento' => $eventId]);
    $position = max(1, min(100, (int) $order->fetchColumn()));
    $pdo->prepare('INSERT INTO eventos_fontes (idfonte,idevento,nome,url,ordem) VALUES (:id,:evento,:nome,:url,:ordem)')->execute([
        ':id' => stridebr_generate_id(), ':evento' => $eventId, ':nome' => substr($label, 0, 120), ':url' => $url, ':ordem' => $position,
    ]);
}

function externalEventsUpsertExternalLink(PDO $pdo, string $eventId, array $event): void
{
    $provider = (string) $event['provider'];
    $identity = externalEventsIdentityKey($event);
    $metadata = is_array($event['source_metadata'] ?? null) ? $event['source_metadata'] : [];
    $metadata['fingerprint'] = (string) $event['fingerprint'];
    $hashPayload = $event;
    unset($hashPayload['source_metadata']);
    $contentHash = hash('sha256', json_encode($hashPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    $stmt = $pdo->prepare('INSERT INTO eventos_fontes_externas (idvinculo,idevento,provider,identity_key,external_id,source_url,content_hash,metadados) VALUES (:id,:evento,:provider,:identity,:external_id,:url,:hash,CAST(:metadata AS jsonb)) ON CONFLICT (provider,identity_key) DO UPDATE SET idevento=EXCLUDED.idevento,external_id=EXCLUDED.external_id,source_url=EXCLUDED.source_url,last_seen_at=NOW(),last_fetched_at=NOW(),content_hash=EXCLUDED.content_hash,sync_misses=0,stale_since=NULL,metadados=EXCLUDED.metadados,data_atualizacao=NOW()');
    $stmt->execute([
        ':id' => stridebr_generate_id(), ':evento' => $eventId, ':provider' => $provider, ':identity' => $identity,
        ':external_id' => trim((string) ($event['external_id'] ?? '')) ?: null,
        ':url' => (string) $event['source_url'], ':hash' => $contentHash,
        ':metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
    ]);
}

function externalEventsSyncOne(PDO $pdo, ExternalEventsProvider $provider, array $event, bool $autoPublish): array
{
    $event['provider'] = $provider->key();
    $event = externalEventsNormalized($event);
    if (trim((string) $event['source_url']) === '' || trim((string) $event['title']) === '' || trim((string) $event['start_at']) === '') throw new InvalidArgumentException('external_event_incomplete');
    externalEventsHttpValidateUrl((string) $event['source_url'], $provider->hosts());
    $sportSlugs = array_values(array_unique(array_filter(array_merge((array) $event['sports'], [(string) ($event['primary_sport'] ?? '')]))));
    $sportIds = externalEventsSportIds($pdo, $sportSlugs);
    $identity = externalEventsIdentityKey($event);
    $existing = externalEventsExistingByIdentity($pdo, $provider->key(), $identity);
    $ambiguous = false;
    if ($existing === null) {
        $match = externalEventsDedupeMatch($pdo, $event);
        $existing = $match['event'];
        $ambiguous = (bool) $match['ambiguous'];
    }
    $created = false;
    if ($existing === null || $ambiguous) {
        $eventId = externalEventsInsertEvent($pdo, $event, $autoPublish, $sportIds);
        $existing = ['idevento' => $eventId, 'origem' => 'externo', 'sincronizacao_bloqueada' => false, 'status' => externalEventsEventStatus($event, $autoPublish)];
        $created = true;
        if ($ambiguous) $event['source_metadata']['dedupe_ambiguous'] = true;
    } else {
        $eventId = (string) $existing['idevento'];
        externalEventsUpdateEvent($pdo, $eventId, $event, $autoPublish, $sportIds, $existing);
    }
    $allowWrite = (string) ($existing['origem'] ?? 'manual') === 'externo' && !stridebr_db_bool($existing['sincronizacao_bloqueada'] ?? false);
    externalEventsSyncModalities($pdo, $eventId, $event, $sportIds, $allowWrite);
    externalEventsSyncProgram($pdo, $eventId, $event, $sportIds, $allowWrite);
    externalEventsUpsertExternalLink($pdo, $eventId, $event);
    externalEventsEnsurePublicSource($pdo, $eventId, $provider->label(), (string) $event['source_url']);
    return ['event_id' => $eventId, 'created' => $created, 'ambiguous' => $ambiguous, 'manual_preserved' => !$allowWrite];
}

function externalEventsMarkMissingForCalendar(PDO $pdo, string $provider, string $calendarUrl, string $runStartedAt): void
{
    $stmt = $pdo->prepare("UPDATE eventos_fontes_externas SET sync_misses=sync_misses+1,stale_since=COALESCE(stale_since,NOW()),data_atualizacao=NOW() WHERE provider=:provider AND COALESCE(metadados->>'calendar_url','')=:calendar AND last_seen_at < CAST(:started AS timestamptz)");
    $stmt->execute([':provider' => $provider, ':calendar' => $calendarUrl, ':started' => $runStartedAt]);
}

function externalEventsRetryAfterAt(mixed $value): ?string
{
    $raw = trim((string) $value);
    if ($raw === '') return null;
    if (ctype_digit($raw)) return (new DateTimeImmutable('now'))->modify('+' . max(60, min(86400, (int) $raw)) . ' seconds')->format('Y-m-d H:i:sP');
    try { return (new DateTimeImmutable($raw))->format('Y-m-d H:i:sP'); } catch (Throwable) { return null; }
}

function externalEventsSyncProvider(PDO $pdo, ExternalEventsProvider $provider, ?callable $fetcher = null, ?float $deadlineAt = null, bool $ignoreState = false): array
{
    if (!externalEventsSchemaAvailable($pdo)) throw new RuntimeException('external_events_schema_missing');
    $state = externalEventsProviderState($pdo, $provider->key());
    if (!$state) throw new RuntimeException('external_provider_state_missing');
    if (!$ignoreState && (!stridebr_db_bool($state['enabled'] ?? false) || (string) ($state['compliance_status'] ?? '') !== 'aprovado')) {
        return ['provider' => $provider->key(), 'skipped' => true, 'reason' => 'disabled_or_compliance_pending'];
    }
    if (!$ignoreState && !empty($state['next_sync_at'])) {
        try {
            if (new DateTimeImmutable((string) $state['next_sync_at']) > new DateTimeImmutable('now')) {
                return ['provider' => $provider->key(), 'skipped' => true, 'reason' => 'retry_after'];
            }
        } catch (Throwable) {
        }
    }
    $fetcher ??= 'externalEventsHttpFetch';
    $attemptAt = (new DateTimeImmutable('now'))->format('Y-m-d H:i:sP');
    externalEventsUpdateProviderState($pdo, $provider->key(), ['last_attempt_at' => $attemptAt, 'last_error_code' => null]);
    $cache = externalEventsProviderCache($state);
    $queue = $provider->initialUrls();
    $seenUrls = [];
    $fetchedCalendars = [];
    $events = [];
    $httpCache = $cache;
    $programFetches = 0;
    try {
        while ($queue !== []) {
            if ($deadlineAt !== null && microtime(true) >= $deadlineAt) throw new RuntimeException('external_sync_deadline');
            $url = array_shift($queue);
            if (isset($seenUrls[$url])) continue;
            $seenUrls[$url] = true;
            if (count($seenUrls) > 24) throw new RuntimeException('external_provider_url_limit');
            externalEventsHttpValidateUrl($url, $provider->hosts());
            $conditional = is_array($httpCache[$url] ?? null) ? $httpCache[$url] : [];
            $response = $fetcher($url, $provider->hosts(), $conditional, $deadlineAt);
            $status = (int) ($response['status'] ?? 0);
            if ($status === 304) continue;
            if ($status === 429) {
                externalEventsUpdateProviderState($pdo, $provider->key(), ['next_sync_at' => externalEventsRetryAfterAt($response['retry_after'] ?? null), 'last_error_code' => 'http_429']);
                throw new RuntimeException('external_http_429');
            }
            if ($status < 200 || $status >= 300) throw new RuntimeException('external_http_status_' . $status);
            $contentType = strtolower((string) ($response['content_type'] ?? ''));
            if ($contentType !== '' && !str_contains($contentType, 'text/html') && !str_contains($contentType, 'application/xhtml+xml')) throw new RuntimeException('external_http_content_type');
            $body = (string) ($response['body'] ?? '');
            if ($body === '') throw new RuntimeException('external_http_empty_body');
            $finalUrl = (string) ($response['final_url'] ?? $url);
            $httpCache[$url] = ['etag' => $response['etag'] ?? null, 'last_modified' => $response['last_modified'] ?? null];
            $parsed = $provider->parse($finalUrl, $body);
            foreach ($parsed as $item) {
                if (!is_array($item)) continue;
                if ($provider instanceof ExternalEventsCbatProvider && $programFetches < 8) {
                    $programUrl = trim((string) (($item['source_metadata']['program_url'] ?? '')));
                    if ($programUrl !== '') {
                        externalEventsHttpValidateUrl($programUrl, $provider->hosts());
                        $programResponse = $fetcher($programUrl, $provider->hosts(), [], $deadlineAt);
                        if ((int) ($programResponse['status'] ?? 0) >= 200 && (int) ($programResponse['status'] ?? 0) < 300) {
                            $item['program'] = $provider->parseProgram((string) ($programResponse['final_url'] ?? $programUrl), (string) ($programResponse['body'] ?? ''));
                            $programFetches++;
                        }
                    }
                }
                $events[] = $item;
            }
            if ($parsed !== []) $fetchedCalendars[$finalUrl] = true;
            foreach ($provider->discover($finalUrl, $body) as $discovered) {
                externalEventsHttpValidateUrl($discovered, $provider->hosts());
                if (!isset($seenUrls[$discovered])) $queue[] = $discovered;
            }
        }
        $pdo->beginTransaction();
        $created = 0;
        $updated = 0;
        $manualPreserved = 0;
        $ambiguous = 0;
        foreach ($events as $event) {
            $result = externalEventsSyncOne($pdo, $provider, $event, stridebr_db_bool($state['auto_publish'] ?? false));
            $created += !empty($result['created']) ? 1 : 0;
            $updated += empty($result['created']) ? 1 : 0;
            $manualPreserved += !empty($result['manual_preserved']) ? 1 : 0;
            $ambiguous += !empty($result['ambiguous']) ? 1 : 0;
        }
        foreach (array_keys($fetchedCalendars) as $calendarUrl) externalEventsMarkMissingForCalendar($pdo, $provider->key(), $calendarUrl, $attemptAt);
        $pdo->commit();
        $stats = ['http_cache' => $httpCache, 'last_run' => ['events' => count($events), 'created' => $created, 'updated' => $updated, 'manual_preserved' => $manualPreserved, 'ambiguous' => $ambiguous]];
        externalEventsUpdateProviderState($pdo, $provider->key(), [
            'last_success_at' => (new DateTimeImmutable('now'))->format('Y-m-d H:i:sP'),
            'last_error_code' => null,
            'consecutive_failures' => 0,
            'next_sync_at' => null,
            'stats' => $stats,
        ]);
        return ['provider' => $provider->key(), 'skipped' => false, 'events' => count($events), 'created' => $created, 'updated' => $updated, 'manual_preserved' => $manualPreserved, 'ambiguous' => $ambiguous];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $failures = (int) ($state['consecutive_failures'] ?? 0) + 1;
        externalEventsUpdateProviderState($pdo, $provider->key(), ['last_error_code' => substr($e->getMessage(), 0, 80), 'consecutive_failures' => $failures, 'stats' => ['http_cache' => $httpCache]]);
        throw $e;
    }
}

function externalEventsSyncProviders(PDO $pdo, array $providers, ?callable $fetcherFactory = null, ?float $deadlineAt = null, bool $ignoreState = false): array
{
    $results = [];
    $failures = [];
    foreach ($providers as $key => $provider) {
        if (!$provider instanceof ExternalEventsProvider) continue;
        if ($deadlineAt !== null && microtime(true) >= $deadlineAt) {
            $failures[(string) $key] = 'external_sync_deadline';
            continue;
        }
        try {
            $fetcher = $fetcherFactory !== null ? $fetcherFactory((string) $key, $provider) : null;
            if ($fetcher !== null && !is_callable($fetcher)) throw new RuntimeException('external_fetcher_invalid');
            $results[(string) $key] = externalEventsSyncProvider($pdo, $provider, $fetcher, $deadlineAt, $ignoreState);
        } catch (Throwable $error) {
            $failures[(string) $key] = $error->getMessage();
        }
    }
    return ['results' => $results, 'failures' => $failures];
}
