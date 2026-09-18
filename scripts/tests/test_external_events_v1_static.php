<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/src/function/external_events/provider.php';
require_once $root . '/src/function/external_events/http.php';
require_once $root . '/src/function/external_events/providers/faergs.php';
require_once $root . '/src/function/external_events/providers/cbat.php';
require_once $root . '/src/function/external_events/providers/cbc.php';
require_once $root . '/src/function/external_events/providers/fgc.php';
require_once $root . '/src/function/external_events/providers/cbtri.php';

$fixture = static fn(string $name): string => (string) file_get_contents(__DIR__ . '/fixtures/external_events/' . $name);
$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (!$condition) throw new RuntimeException($message);
};
$throws = static function (callable $callback, string $message) use (&$checks): void {
    $checks++;
    try { $callback(); } catch (Throwable) { return; }
    throw new RuntimeException($message);
};

$faergs = new ExternalEventsFaergsProvider();
$faergsEvents = $faergs->parse('https://www.faergs.com.br/', $fixture('faergs.html'));
$assert(count($faergsEvents) === 2, 'FAERGS deve extrair duas competições da fixture');
$assert(($faergsEvents[0]['title'] ?? '') === 'Troféu IENH de Atletismo', 'FAERGS perdeu título');
$assert(($faergsEvents[0]['city'] ?? '') === 'Novo Hamburgo' && ($faergsEvents[0]['state'] ?? '') === 'RS', 'FAERGS perdeu cidade/UF');
$assert(str_starts_with((string) ($faergsEvents[1]['start_at'] ?? ''), '2026-08-15'), 'FAERGS perdeu data');
$assert(str_contains((string) ($faergsEvents[1]['title'] ?? ''), 'Provas Combinadas'), 'FAERGS precisa preservar competição de combinadas sem inventar programa');
$assert(($faergsEvents[1]['program'] ?? null) === [], 'FAERGS não pode inferir provas pela categoria do campeonato');

$cbat = new ExternalEventsCbatProvider();
$cbatEvents = $cbat->parse('https://competicoes.cbat.org.br/novo/index.php?pagina=calendario_oficial', $fixture('cbat_calendar.html'));
$assert(count($cbatEvents) === 1 && ($cbatEvents[0]['primary_sport'] ?? '') === 'atletismo', 'CBAt deve normalizar calendário como atletismo');
$assert(($cbatEvents[0]['external_id'] ?? '') === '9001', 'CBAt deve preservar ID externo quando publicado na URL');
$program = $cbat->parseProgram('https://competicoes.cbat.org.br/novo/competicoes/programa.php?id=9001', $fixture('cbat_program.html'));
$groups = array_values(array_unique(array_column($program, 'group')));
foreach (['corrida', 'revezamento', 'salto', 'arremesso_lancamento', 'combinadas'] as $group) $assert(in_array($group, $groups, true), 'CBAt não reconheceu grupo ' . $group);
$programByName = [];
foreach ($program as $item) $programByName[(string) $item['name']] = $item;
$assert(($programByName['1.500 metros']['distance_m'] ?? null) === 1500.0, '1.500 metros precisa normalizar para 1500 m');
$assert(array_key_exists('distance_m', $programByName['Salto em Distância']) && $programByName['Salto em Distância']['distance_m'] === null, 'Salto em Distância não pode virar distância de percurso');
$assert(array_key_exists('distance_m', $programByName['Arremesso do Peso']) && $programByName['Arremesso do Peso']['distance_m'] === null, 'Arremesso não pode entrar como distância');
$assert(array_key_exists('distance_m', $programByName['Lançamento do Dardo']) && $programByName['Lançamento do Dardo']['distance_m'] === null, 'Lançamento não pode entrar como distância');

$cbc = new ExternalEventsCbcProvider();
$cbcFixtures = [
    'estrada' => ['cbc_estrada.html', 'ciclismo-de-estrada', 'estrada', 'Caxias do Sul'],
    'pista' => ['cbc_pista.html', 'ciclismo', 'pista', 'Curitiba'],
    'mtb' => ['cbc_mtb.html', 'mountain-bike', 'mtb', 'Bento Gonçalves'],
];
foreach ($cbcFixtures as $path => [$file, $sport, $type, $city]) {
    $events = $cbc->parse('https://www.cbc.esp.br/modalidades/calendario/busca/' . $path, $fixture($file));
    $assert(count($events) === 1, 'CBC ' . $path . ' deve extrair evento');
    $assert(($events[0]['primary_sport'] ?? '') === $sport && ($events[0]['event_type'] ?? '') === $type, 'CBC ' . $path . ' mapeou modalidade incorretamente');
    $assert(($events[0]['city'] ?? '') === $city, 'CBC ' . $path . ' perdeu cidade');
    $assert(!empty($events[0]['end_at']), 'CBC ' . $path . ' perdeu data final');
    $assert(!empty($events[0]['source_metadata']['category']) && !empty($events[0]['source_metadata']['class']), 'CBC ' . $path . ' perdeu categoria/classe');
}

$fgc = new ExternalEventsFgcProvider();
$fgcRoad = $fgc->parse('https://www.fgc.com.br/estrada/campeonato-2026/', $fixture('fgc_estrada.html'));
$fgcMtb = $fgc->parse('https://www.fgc.com.br/mountain-bike/campeonato-2026/', $fixture('fgc_mtb.html'));
$fgcDownhill = $fgc->parse('https://www.fgc.com.br/downhill/campeonato-2026/', $fixture('fgc_downhill.html'));
$assert(count($fgcRoad) === 2 && ($fgcRoad[0]['primary_sport'] ?? '') === 'ciclismo-de-estrada', 'FGC estrada não reconhecida');
$assert(($fgcRoad[0]['city'] ?? '') === 'Campo Bom', 'FGC estrada perdeu cidade da etapa');
$assert(str_starts_with((string) ($fgcRoad[1]['start_at'] ?? ''), '2026-03-21') && str_starts_with((string) ($fgcRoad[1]['end_at'] ?? ''), '2026-03-22'), 'FGC estrada deve preservar intervalo explícito de dois dias');
$assert(count($fgcMtb) === 1 && ($fgcMtb[0]['primary_sport'] ?? '') === 'mountain-bike', 'FGC MTB não reconhecido');
$assert(($fgcMtb[0]['state'] ?? '') === 'RS', 'FGC deve fixar UF RS da federação estadual');
$assert(count($fgcDownhill) === 1 && ($fgcDownhill[0]['primary_sport'] ?? '') === 'downhill', 'FGC downhill não reconhecido');
$assert(($fgcDownhill[0]['city'] ?? '') === 'São Vendelino', 'FGC downhill perdeu cidade da etapa');
$assert(str_starts_with((string) ($fgcDownhill[0]['start_at'] ?? ''), '2026-05-16') && str_starts_with((string) ($fgcDownhill[0]['end_at'] ?? ''), '2026-05-17'), 'FGC downhill deve preservar intervalo explícito');

$cbtri = new ExternalEventsCbtriProvider();
$discovered = $cbtri->discover('https://cbtri.org.br/calendario_2026/', $fixture('cbtri_root.html'));
$assert($discovered === ['https://cbtri.org.br/calendario_2026/triatlo/'], 'CBTri deve descobrir só calendário no host permitido');
$cbtriEvents = $cbtri->parse($discovered[0], $fixture('cbtri_calendar.html'));
$assert(count($cbtriEvents) === 3, 'CBTri deve extrair triathlon, duathlon e aquathlon');
$assert(array_column($cbtriEvents, 'primary_sport') === ['triatlo', 'duatlo', 'aquatlo'], 'CBTri mapeou modalidades incorretamente');
$assert(($cbtriEvents[0]['status'] ?? '') === 'confirmed' && ($cbtriEvents[1]['status'] ?? '') === 'planned', 'CBTri perdeu status publicado pela fonte');
$assert(($cbtriEvents[1]['source_metadata']['category'] ?? '') === 'Age Group', 'CBTri perdeu categoria');

$throws(static fn() => externalEventsHttpValidateUrl('http://www.faergs.com.br/', $faergs->hosts()), 'HTTP deve ser rejeitado');
$throws(static fn() => externalEventsHttpValidateUrl('https://example.org/', $faergs->hosts()), 'host fora da allowlist deve ser rejeitado');
$throws(static fn() => $faergs->parse('https://www.faergs.com.br/', $fixture('broken.html')), 'FAERGS quebrado deve falhar fechado');
$throws(static fn() => $cbat->parse('https://competicoes.cbat.org.br/novo/index.php?pagina=calendario_oficial', $fixture('broken.html')), 'CBAt quebrado deve falhar fechado');
$throws(static fn() => $cbc->parse('https://www.cbc.esp.br/modalidades/calendario/busca/estrada', $fixture('broken.html')), 'CBC quebrado deve falhar fechado');
$throws(static fn() => $fgc->parse('https://www.fgc.com.br/estrada/campeonato-2026/', $fixture('broken.html')), 'FGC quebrado deve falhar fechado');
$throws(static fn() => $cbtri->parse('https://cbtri.org.br/calendario_2026/triatlo/', $fixture('broken.html')), 'CBTri quebrado deve falhar fechado');

$sharedCalendarEvent = externalEventsNormalized(['title' => 'Evento A', 'start_at' => '2026-09-20T00:00:00-03:00', 'city' => 'Porto Alegre', 'state' => 'RS', 'primary_sport' => 'atletismo', 'source_url' => 'https://www.faergs.com.br/', 'source_metadata' => ['calendar_url' => 'https://www.faergs.com.br/']]);
$specificEvent = externalEventsNormalized(['title' => 'Evento B', 'start_at' => '2026-09-20T00:00:00-03:00', 'city' => 'Porto Alegre', 'state' => 'RS', 'primary_sport' => 'atletismo', 'source_url' => 'https://competicoes.cbat.org.br/novo/competicoes/programa.php?id=77', 'source_metadata' => ['calendar_url' => 'https://competicoes.cbat.org.br/novo/index.php?pagina=calendario_oficial']]);
$assert(str_starts_with(externalEventsIdentityKey($sharedCalendarEvent), 'fp:'), 'URL compartilhada de calendário deve usar fingerprint');
$assert(str_starts_with(externalEventsIdentityKey($specificEvent), 'url:'), 'URL específica do evento deve poder ser identidade estável');

printf("External Sports Events V1 static: %d assertions\n", $checks);
