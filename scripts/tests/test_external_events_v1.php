<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/function/external_events.php';

return function (PDO $pdo): void {
    AlphaTest::assert(externalEventsSchemaAvailable($pdo), 'Schema External Sports Events V1 não aplicado');
    $states = externalEventsProviderStates($pdo);
    foreach (['faergs', 'cbat', 'cbc', 'fgc', 'cbtri'] as $key) {
        AlphaTest::assert(isset($states[$key]), 'Provider ausente do estado de sync: ' . $key);
        AlphaTest::assert(!stridebr_db_bool($states[$key]['enabled'] ?? true), 'Provider deve nascer desabilitado: ' . $key);
        AlphaTest::same('pendente', (string) ($states[$key]['compliance_status'] ?? ''), 'Compliance deve nascer pendente: ' . $key);
    }

    $faergs = externalEventsProvider('faergs');
    $user = alphaTestUser($pdo, 'external_events_manual');
    $base = [
        'source_url' => 'https://www.faergs.com.br/',
        'title' => 'Alpha test event idempotente',
        'start_at' => '2027-03-28T00:00:00-03:00',
        'city' => 'Novo Hamburgo',
        'state' => 'RS',
        'country' => 'Brasil',
        'primary_sport' => 'atletismo',
        'sports' => ['atletismo'],
        'event_type' => 'atletismo',
        'source_metadata' => ['calendar_url' => 'https://www.faergs.com.br/'],
    ];
    $first = externalEventsSyncOne($pdo, $faergs, $base, false);
    $second = externalEventsSyncOne($pdo, $faergs, $base, false);
    AlphaTest::same((string) $first['event_id'], (string) $second['event_id'], 'Segunda sincronização do provider não pode duplicar evento');
    $count = $pdo->prepare("SELECT COUNT(*) FROM eventos_esportivos WHERE titulo='Alpha test event idempotente'");
    $count->execute();
    AlphaTest::same(1, (int) $count->fetchColumn(), 'Provider idempotente criou duplicata');
    eventosSalvar($pdo, $user, [
        'titulo' => 'Alpha test event idempotente editado',
        'idmodalidade' => 'm_atletismo',
        'tipo' => 'atletismo',
        'data_inicio' => '2027-03-28T08:00',
        'cidade' => 'Novo Hamburgo',
        'estado' => 'RS',
        'pais' => 'Brasil',
        'organizador' => 'Curadoria manual',
        'status' => 'publicado',
    ], (string) $first['event_id']);
    $curatedStmt = $pdo->prepare('SELECT titulo,origem,sincronizacao_bloqueada,seo_indexavel FROM eventos_esportivos WHERE idevento=:id');
    $curatedStmt->execute([':id' => $first['event_id']]);
    $curated = $curatedStmt->fetch();
    AlphaTest::assert(stridebr_db_bool($curated['sincronizacao_bloqueada'] ?? false), 'Editar evento externo no admin deve bloquear ressincronização automática');
    AlphaTest::assert(stridebr_db_bool($curated['seo_indexavel'] ?? false), 'Publicação manual de evento externo deve liberar indexação');
    externalEventsSyncOne($pdo, $faergs, $base, false);
    $curatedStmt->execute([':id' => $first['event_id']]);
    AlphaTest::same('Alpha test event idempotente editado', (string) $curatedStmt->fetch()['titulo'], 'Provider não pode desfazer curadoria manual bloqueada');

    $faergsCross = [
        'source_url' => 'https://www.faergs.com.br/',
        'title' => 'Alpha test event Campeonato Gaúcho Sub-20',
        'start_at' => '2027-05-09T00:00:00-03:00',
        'city' => 'Porto Alegre',
        'state' => 'RS',
        'country' => 'Brasil',
        'primary_sport' => 'atletismo',
        'sports' => ['atletismo'],
        'event_type' => 'atletismo',
        'source_metadata' => ['calendar_url' => 'https://www.faergs.com.br/'],
    ];
    $crossA = externalEventsSyncOne($pdo, $faergs, $faergsCross, false);
    $cbatCross = $faergsCross;
    $cbatCross['source_url'] = 'https://competicoes.cbat.org.br/novo/competicoes/programa.php?id=alpha-cross';
    $cbatCross['external_id'] = 'alpha-cross';
    $cbatCross['title'] = 'Alpha test event Campeonato Gaúcho de Atletismo Sub-20';
    $cbatCross['source_metadata'] = ['calendar_url' => 'https://competicoes.cbat.org.br/novo/index.php?pagina=calendario_estadual'];
    $crossB = externalEventsSyncOne($pdo, externalEventsProvider('cbat'), $cbatCross, false);
    AlphaTest::same((string) $crossA['event_id'], (string) $crossB['event_id'], 'Duas fontes seguras devem convergir no mesmo evento');
    $sourceCount = $pdo->prepare('SELECT COUNT(*) FROM eventos_fontes_externas WHERE idevento=:id');
    $sourceCount->execute([':id' => $crossA['event_id']]);
    AlphaTest::same(2, (int) $sourceCount->fetchColumn(), 'Evento deduplicado deve preservar as duas proveniências');

    $manualPayload = [
        'titulo' => 'Alpha test event manual preserve',
        'idmodalidade' => 'm_atletismo',
        'tipo' => 'atletismo',
        'data_inicio' => '2027-06-10T08:00',
        'cidade' => 'Santa Maria',
        'estado' => 'RS',
        'pais' => 'Brasil',
        'organizador' => 'Organizador manual',
        'status' => 'rascunho',
    ];
    $manualId = eventosSalvar($pdo, $user, $manualPayload);
    $manualExternal = [
        'source_url' => 'https://www.faergs.com.br/',
        'title' => 'Alpha test event manual preserve',
        'start_at' => '2027-06-10T00:00:00-03:00',
        'city' => 'Santa Maria',
        'state' => 'RS',
        'country' => 'Brasil',
        'organizer' => 'Organizador externo',
        'primary_sport' => 'atletismo',
        'sports' => ['atletismo'],
        'event_type' => 'atletismo',
        'source_metadata' => ['calendar_url' => 'https://www.faergs.com.br/'],
    ];
    $manualResult = externalEventsSyncOne($pdo, $faergs, $manualExternal, false);
    AlphaTest::same($manualId, (string) $manualResult['event_id'], 'Fonte externa deve poder apontar para evento manual equivalente');
    AlphaTest::assert(!empty($manualResult['manual_preserved']), 'Evento manual convergido deve ser preservado');
    $manualRow = $pdo->prepare('SELECT organizador,origem FROM eventos_esportivos WHERE idevento=:id');
    $manualRow->execute([':id' => $manualId]);
    $manualRow = $manualRow->fetch();
    AlphaTest::same('Organizador manual', (string) $manualRow['organizador'], 'Sync não pode sobrescrever edição manual');
    AlphaTest::same('manual', (string) $manualRow['origem'], 'Evento manual não pode mudar de ownership');

    $ambiguousIds = [];
    for ($i = 1; $i <= 2; $i++) {
        $ambiguousIds[] = eventosSalvar($pdo, $user, [
            'titulo' => 'Alpha test event ambíguo',
            'idmodalidade' => 'm_atletismo',
            'tipo' => 'atletismo',
            'data_inicio' => '2027-07-20T08:00',
            'cidade' => 'Pelotas',
            'estado' => 'RS',
            'pais' => 'Brasil',
            'organizador' => 'Manual ' . $i,
            'status' => 'rascunho',
        ]);
    }
    $ambiguousResult = externalEventsSyncOne($pdo, $faergs, [
        'source_url' => 'https://www.faergs.com.br/',
        'title' => 'Alpha test event ambíguo',
        'start_at' => '2027-07-20T00:00:00-03:00',
        'city' => 'Pelotas',
        'state' => 'RS',
        'primary_sport' => 'atletismo',
        'sports' => ['atletismo'],
        'source_metadata' => ['calendar_url' => 'https://www.faergs.com.br/'],
    ], false);
    AlphaTest::assert(!empty($ambiguousResult['ambiguous']) && !in_array((string) $ambiguousResult['event_id'], $ambiguousIds, true), 'Merge ambíguo não pode ocorrer silenciosamente');

    $multi = externalEventsSyncOne($pdo, externalEventsProvider('cbtri'), [
        'external_id' => 'alpha-multi-1',
        'source_url' => 'https://cbtri.org.br/calendario_2026/',
        'title' => 'Alpha test event multisport',
        'start_at' => '2027-08-02T00:00:00-03:00',
        'city' => 'Brasília',
        'state' => 'DF',
        'primary_sport' => 'triatlo',
        'sports' => ['triatlo', 'duatlo', 'aquatlo'],
        'event_type' => 'multiesporte',
        'source_metadata' => ['calendar_url' => 'https://cbtri.org.br/calendario_2026/'],
    ], false);
    $sportRows = $pdo->prepare('SELECT m.slug,em.principal FROM eventos_modalidades em JOIN modalidades m ON m.idmodalidade=em.idmodalidade WHERE em.idevento=:id ORDER BY em.ordem');
    $sportRows->execute([':id' => $multi['event_id']]);
    $sportRows = $sportRows->fetchAll();
    AlphaTest::same(['triatlo', 'duatlo', 'aquatlo'], array_column($sportRows, 'slug'), 'Evento multi-modalidade deve persistir N:N sem duplicar evento');
    AlphaTest::assert(stridebr_db_bool($sportRows[0]['principal'] ?? false), 'Modalidade principal deve ser explícita');

    $programEvent = externalEventsSyncOne($pdo, externalEventsProvider('cbat'), [
        'external_id' => 'alpha-program-1',
        'source_url' => 'https://competicoes.cbat.org.br/novo/competicoes/programa.php?id=alpha-program-1',
        'title' => 'Alpha test event programa atletismo',
        'start_at' => '2027-09-01T00:00:00-03:00',
        'city' => 'Bragança Paulista',
        'state' => 'SP',
        'primary_sport' => 'atletismo',
        'sports' => ['atletismo'],
        'event_type' => 'atletismo',
        'distances' => [],
        'program' => [
            ['name' => '100 metros', 'group' => 'corrida', 'distance_m' => 100.0, 'sport' => 'atletismo'],
            ['name' => 'Salto em Altura', 'group' => 'salto', 'distance_m' => null, 'sport' => 'atletismo'],
            ['name' => 'Arremesso do Peso', 'group' => 'arremesso_lancamento', 'distance_m' => null, 'sport' => 'atletismo'],
            ['name' => 'Lançamento do Disco', 'group' => 'arremesso_lancamento', 'distance_m' => null, 'sport' => 'atletismo'],
        ],
        'source_metadata' => ['calendar_url' => 'https://competicoes.cbat.org.br/novo/index.php?pagina=calendario_oficial'],
    ], false);
    $eventRow = $pdo->prepare('SELECT distancias FROM eventos_esportivos WHERE idevento=:id');
    $eventRow->execute([':id' => $programEvent['event_id']]);
    $distances = $eventRow->fetchColumn();
    $distances = is_array($distances) ? $distances : (json_decode((string) $distances, true) ?: []);
    AlphaTest::same([], $distances, 'Provas de pista e campo não podem ser colocadas em distancias');
    $programRows = $pdo->prepare('SELECT nome,grupo,distancia_m,idmodalidade FROM eventos_provas WHERE idevento=:id ORDER BY ordem');
    $programRows->execute([':id' => $programEvent['event_id']]);
    $programRows = $programRows->fetchAll();
    AlphaTest::same(4, count($programRows), 'Programa explícito deve ser persistido em eventos_provas');
    AlphaTest::same('salto', (string) $programRows[1]['grupo'], 'Salto precisa ficar no grupo próprio');
    AlphaTest::assert($programRows[1]['distancia_m'] === null && $programRows[2]['distancia_m'] === null && $programRows[3]['distancia_m'] === null, 'Campo não pode ganhar distancia_m inventada');
    AlphaTest::assert(!empty($programRows[0]['idmodalidade']), 'Programa pode referenciar modalidade quando explicitamente mapeada');

    $cancelled = [
        'source_url' => 'https://www.faergs.com.br/',
        'title' => 'Alpha test event cancel explicit',
        'start_at' => '2027-09-12T00:00:00-03:00',
        'city' => 'Canoas',
        'state' => 'RS',
        'primary_sport' => 'atletismo',
        'sports' => ['atletismo'],
        'source_metadata' => ['calendar_url' => 'https://www.faergs.com.br/'],
    ];
    externalEventsSyncOne($pdo, $faergs, $cancelled, false);
    $cancelled['status'] = 'cancelled';
    $cancelledResult = externalEventsSyncOne($pdo, $faergs, $cancelled, false);
    $statusStmt = $pdo->prepare('SELECT status FROM eventos_esportivos WHERE idevento=:id');
    $statusStmt->execute([':id' => $cancelledResult['event_id']]);
    AlphaTest::same('cancelado', (string) $statusStmt->fetchColumn(), 'Cancelamento explícito da fonte deve atualizar status');

    externalEventsMarkMissingForCalendar($pdo, 'faergs', 'https://www.faergs.com.br/', (new DateTimeImmutable('+2 seconds'))->format('Y-m-d H:i:sP'));
    $staleStmt = $pdo->prepare('SELECT sync_misses,stale_since FROM eventos_fontes_externas WHERE idevento=:id AND provider=:provider');
    $staleStmt->execute([':id' => $first['event_id'], ':provider' => 'faergs']);
    $stale = $staleStmt->fetch();
    AlphaTest::assert((int) ($stale['sync_misses'] ?? 0) >= 1 && !empty($stale['stale_since']), 'Evento desaparecido deve ficar stale sem exclusão imediata');
    $existsStmt = $pdo->prepare('SELECT COUNT(*) FROM eventos_esportivos WHERE idevento=:id');
    $existsStmt->execute([':id' => $first['event_id']]);
    AlphaTest::same(1, (int) $existsStmt->fetchColumn(), 'Stale não pode excluir evento');

    $preservedId = (string) $programEvent['event_id'];
    $brokenProvider = new class implements ExternalEventsProvider {
        public function key(): string { return 'cbat'; }
        public function label(): string { return 'CBAt'; }
        public function hosts(): array { return ['competicoes.cbat.org.br']; }
        public function initialUrls(): array { return ['https://competicoes.cbat.org.br/novo/index.php?pagina=calendario_oficial']; }
        public function parse(string $url, string $html): array { throw new RuntimeException('fixture_structure_changed'); }
        public function discover(string $url, string $html): array { return []; }
    };
    AlphaTest::throws(fn() => externalEventsSyncProvider($pdo, $brokenProvider, static fn(string $url, array $hosts, array $conditional, ?float $deadline): array => ['status'=>200,'body'=>'<html>mudou</html>','content_type'=>'text/html','final_url'=>$url], microtime(true) + 10, true), 'Parser quebrado deve falhar provider');
    $existsStmt->execute([':id' => $preservedId]);
    AlphaTest::same(1, (int) $existsStmt->fetchColumn(), 'Parser quebrado não pode apagar evento válido existente');

    $timeoutProvider = new class implements ExternalEventsProvider {
        public function key(): string { return 'faergs'; }
        public function label(): string { return 'FAERGS'; }
        public function hosts(): array { return ['www.faergs.com.br']; }
        public function initialUrls(): array { return ['https://www.faergs.com.br/']; }
        public function parse(string $url, string $html): array { return []; }
        public function discover(string $url, string $html): array { return []; }
    };
    $healthyProvider = new class implements ExternalEventsProvider {
        public function key(): string { return 'cbc'; }
        public function label(): string { return 'CBC'; }
        public function hosts(): array { return ['www.cbc.esp.br']; }
        public function initialUrls(): array { return ['https://www.cbc.esp.br/modalidades/calendario/busca/estrada']; }
        public function parse(string $url, string $html): array {
            return [externalEventsNormalized(['provider'=>'cbc','external_id'=>'alpha-batch-healthy','source_url'=>$url,'title'=>'Alpha test event batch healthy','start_at'=>'2027-10-10T00:00:00-03:00','city'=>'Porto Alegre','state'=>'RS','primary_sport'=>'ciclismo-de-estrada','sports'=>['ciclismo-de-estrada'],'event_type'=>'estrada','source_metadata'=>['calendar_url'=>$url]])];
        }
        public function discover(string $url, string $html): array { return []; }
    };
    $batch = externalEventsSyncProviders($pdo, ['faergs'=>$timeoutProvider, 'cbc'=>$healthyProvider], static function (string $key): callable {
        if ($key === 'faergs') return static function (): array { throw new RuntimeException('fixture_timeout'); };
        return static fn(string $url): array => ['status'=>200,'body'=>'<html>ok</html>','content_type'=>'text/html','final_url'=>$url];
    }, microtime(true) + 10, true);
    AlphaTest::assert(isset($batch['failures']['faergs']), 'Timeout de um provider deve ser registrado');
    AlphaTest::assert(isset($batch['results']['cbc']) && empty($batch['results']['cbc']['skipped']), 'Falha de um provider não pode impedir o próximo');

    $seoStmt = $pdo->prepare("UPDATE eventos_esportivos SET status='publicado',seo_indexavel=FALSE WHERE idevento=:id");
    $seoStmt->execute([':id' => $multi['event_id']]);
    $slugStmt = $pdo->prepare('SELECT slug FROM eventos_esportivos WHERE idevento=:id');
    $slugStmt->execute([':id' => $multi['event_id']]);
    $externalCanonical = stridebr_seo_canonical('/evento.php', (string) $slugStmt->fetchColumn());
    AlphaTest::assert(!in_array($externalCanonical, stridebr_seo_sitemap_urls($pdo), true), 'Evento externo noindex não pode entrar no sitemap');
};
