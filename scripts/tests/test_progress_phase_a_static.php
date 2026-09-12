<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/src/includes/app.php';
require_once $root . '/src/function/sport_hub.php';

$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    $checks++;
    if (!$ok) {
        fwrite(STDERR, "Progress Phase A failed: {$message}\n");
        exit(1);
    }
};
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);
$hub = $read('src/function/sport_hub.php');
$progress = $read('public/user/progresso.php');
$dashboard = $read('src/function/dashboard.php');
$api = $read('public/api/progress-preferences.php');
$js = $read('public/assets/js/progresso.js');
$css = $read('public/assets/css/sport-hub.css');
$pt = $read('src/i18n/pt-BR.php');
$en = $read('src/i18n/en.php');

$historyStart = new DateTimeImmutable('2021-02-03 08:15:00-03');
$all = sportHubResolvePeriod('all', '', $historyStart);
$assert($all['view'] === 'all' && $all['has_previous'] === false, 'Todo o histórico não pode criar comparação anterior.');
$assert($all['previous_start'] === null && $all['previous_end'] === null, 'Todo o histórico precisa manter previous window nula.');
$assert($all['current_start']->format('Y-m-d') === '2021-02-03', 'Todo o histórico precisa começar no primeiro dado relevante do contexto.');
foreach (['4w' => 'week', '12w' => 'week', '6m' => 'month', '1y' => 'month'] as $period => $bucket) {
    $window = sportHubResolvePeriod($period);
    $assert($window['has_previous'] === true, "{$period} precisa de janela anterior equivalente.");
    $assert(sportHubBucketMode($window) === $bucket, "{$period} precisa do bucketing esperado.");
}
$shortAll = sportHubResolvePeriod('all', '', new DateTimeImmutable('2025-03-01 00:00:00-03'));
$assert(sportHubBucketMode($shortAll) === 'month', 'Todo o histórico curto precisa usar buckets mensais.');
$longAll = sportHubResolvePeriod('all', '', new DateTimeImmutable('2018-01-01 00:00:00-03'));
$assert(sportHubBucketMode($longAll) === 'year', 'Todo o histórico longo precisa usar buckets anuais.');

$athleticsRows = [
    ['hub_bucket' => 'athletics', 'modalidade_slug' => 'atletismo-100m'],
    ['hub_bucket' => 'athletics', 'modalidade_slug' => 'lancamento-de-dardo'],
    ['hub_bucket' => 'cardio', 'modalidade_slug' => 'corrida'],
];
$assert(count(sportHubFilterSport($athleticsRows, 'atletismo')) === 2, 'Atletismo global precisa agregar todas as provas.');
$assert(count(sportHubFilterSport($athleticsRows, 'atletismo', 'atletismo-100m')) === 1, 'Navegação local de Atletismo precisa filtrar uma prova.');

$assert(str_contains($hub, 'COUNT(ra.idregistro) AS history_count') && str_contains($hub, "'slug' => 'atletismo'") && str_contains($hub, 'function sportHubAthleticsEvents'), 'Inventário global precisa vir do banco e agregar Atletismo.');
$assert(!str_contains($hub, "NOW() - INTERVAL '48 months'"), 'Força e Atletismo não podem manter limite arbitrário de 48 meses.');
$assert(str_contains($hub, "'best_e1rm_source'") && str_contains($hub, '$reps <= 12') && str_contains($hub, "'load_kg' => \$load"), 'e1RM precisa preservar série-fonte e excluir reps acima de 12.');
$assert(str_contains($hub, "'direct_muscles'") && str_contains($hub, "'secondary_muscles'") && !str_contains($hub, '+ .5'), 'Músculos primários e secundários precisam ficar separados.');
$assert(substr_count($hub, '$wind !== null && $wind <= 2.0') >= 2, 'Vento ausente não pode provar legalidade em provas sensíveis.');
$assert(str_contains($hub, "'direction' => 'lower'") && str_contains($hub, "'direction' => 'higher'"), 'Direção de performance do Atletismo precisa ser explícita por tipo de prova.');

$assert(str_contains($progress, "require_once dirname(__DIR__, 2) . '/src/function/dashboard.php'") && str_contains($progress, 'dashboardListarMetas($pdo, $idUsuario, true)') && !str_contains($progress, 'FROM metas_usuario'), 'Progresso precisa reutilizar helpers reais de metas.');
$assert(str_contains($progress, 'dashboardPreferenciasProgress') && str_contains($dashboard, "['progress']") && str_contains($api, 'stridebr_verify_csrf()'), 'Preferência de período precisa usar JSONB existente, POST autenticado e CSRF.');
$assert(str_contains($progress, "'all' => stridebr_t('progress.period.all')") && str_contains($progress, "stridebr_t('progress.since_date'") && !str_contains($progress, 'progress.period_comparison'), 'Todo o histórico precisa usar trajetória sem comparação anterior.');
$assert(str_contains($progress, "stridebr_t('progress.best_average_pace_activity')") && !str_contains($progress, "stridebr_t('progress.best_record')"), 'Pace médio de uma atividade não pode ser rotulado como recorde.');
$assert(str_contains($progress, 'sportHubSportsPeriodSummary($availableSports, $navigationPeriodActivities)') && str_contains($progress, "stridebr_t('progress.no_sport_data_yet'") && str_contains($progress, "stridebr_t('progress.no_sport_in_period'"), 'Seus esportes precisa usar ativo ∪ histórico e distinguir empty states.');
$assert(str_contains($progress, "stridebr_t('progress.practice_distribution')") && str_contains($progress, '$durationTotal') === false && str_contains($progress, '$distributionTotal'), 'Distribuição da prática precisa ser baseada em duração.');

$summaryPos = strpos($progress, "stridebr_t('progress.period_summary')");
$sportsPos = strpos($progress, "stridebr_t('progress.your_sports')");
$goalsPos = strpos($progress, "id=\"progress-goals-title\"");
$consistencyPos = strpos($progress, "id=\"progress-consistency-title\"");
$distributionPos = strpos($progress, "stridebr_t('progress.practice_distribution')");
$assert($summaryPos !== false && $sportsPos !== false && $goalsPos !== false && $consistencyPos !== false && $distributionPos !== false && $summaryPos < $sportsPos && $sportsPos < $goalsPos && $goalsPos < $consistencyPos && $consistencyPos < $distributionPos, 'Visão geral precisa seguir a ordem de produto da Fase A.');
$assert(str_contains($progress, 'progress-event-nav') && str_contains($progress, "['event' => \$eventSlug") && str_contains($hub, "'slug' => 'atletismo'"), 'Atletismo precisa ter uma tab global e navegação local por prova.');
$assert(str_contains($progress, "stridebr_t('progress.e1rm_source'") && str_contains($progress, "stridebr_t('progress.direct_sets')") && str_contains($progress, "stridebr_t('progress.secondary_involvement')"), 'UI de força precisa mostrar provenance e separar músculos.');
$assert(str_contains($progress, 'sportHubTrainingLoad($currentActivities)') && str_contains($progress, "stridebr_t('progress.rpe_coverage'"), 'sRPE precisa permanecer com cobertura explícita.');

$assert(str_contains($js, 'AbortController') && str_contains($js, 'history.pushState') && str_contains($js, "window.addEventListener('popstate'") && str_contains($js, 'event.metaKey || event.ctrlKey'), 'Progressive enhancement atual precisa ser preservado.');
$assert(str_contains($js, 'savePeriodPreference') && str_contains($js, "method: 'POST'") && str_contains($js, 'csrf_token'), 'Período precisa ser salvo como enhancement sem substituir navegação GET.');
$assert(str_contains($js, "[data-progress-period-select]") && str_contains($js, 'progressCsrfToken') && str_contains($js, 'progressPreferenceUrl'), 'Persistência do período precisa usar os data-* reais do formulário.');
$assert(str_contains($hub, 'if ($eventSlug !== \'\' && $slug !== $eventSlug) continue;') && str_contains($progress, 'sportHubAthleticsDashboard($pdo, $idUsuario, $filteredActivities, $periodWindow, $selectedEvent)'), 'Renderer de Atletismo precisa respeitar a prova selecionada mesmo sem atividades no período.');
$assert(str_contains($css, '.progress-highlight-grid') && str_contains($css, '.progress-distribution-list') && str_contains($css, '.progress-consistency-band'), 'Novas superfícies precisam reutilizar sport-hub.css.');
foreach (['progress.highlights', 'progress.since_date', 'progress.best_average_pace_activity', 'progress.e1rm_source', 'progress.direct_sets', 'progress.secondary_involvement', 'progress.practice_distribution', 'progress.no_sport_in_period'] as $key) {
    $assert(str_contains($pt, "'{$key}'") && str_contains($en, "'{$key}'"), "Tradução {$key} precisa existir nos dois locales.");
}

printf("✓ progress phase A static: %d assertions\n", $checks);
