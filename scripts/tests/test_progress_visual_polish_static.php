<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    $checks++;
    if (!$ok) {
        fwrite(STDERR, "Progress visual polish failed: {$message}\n");
        exit(1);
    }
};
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);
$progress = $read('public/user/progresso.php');
$css = $read('public/assets/css/sport-hub.css');
$js = $read('public/assets/js/progresso.js');
$pt = $read('src/i18n/pt-BR.php');
$en = $read('src/i18n/en.php');

$assert(str_contains($css, 'max-width:1236px') && str_contains($css, 'padding:18px 28px 46px'), 'Shell deve centralizar 1180 px úteis com padding previsível.');
$assert(str_contains($css, '.progress-page{box-sizing:border-box;width:100%;max-width:1236px;margin-inline:auto'), 'Shell deve usar width 100%, max-width e margin-inline auto.');
$assert(str_contains($progress, '$desktopSportTabs = array_slice($navigationSports, 0, 5)'), 'Navegação desktop deve limitar esportes prioritários.');
$assert(str_contains($progress, 'array_unshift($navigationSports, $currentNavigationSport)'), 'Esporte atual nunca pode sumir da navegação prioritária.');
$assert(str_contains($progress, 'count($mobileSportTabSlugs) >= 2'), 'Mobile deve limitar tabs esportivas visíveis.');
$assert(str_contains($progress, 'data-progress-nav-more') && str_contains($progress, "stridebr_t('progress.more')"), 'Navegação deve oferecer More acessível.');
$assert(str_contains($progress, 'progress-nav-current-item') && str_contains($progress, 'aria-current="page"'), 'More deve indicar claramente o esporte/prova atual.');
$assert(str_contains($progress, '$desktopSportOverflow') && str_contains($progress, '$mobileOnlySportOverflow'), 'Todos os esportes precisam continuar acessíveis em desktop e mobile.');
$assert(str_contains($progress, '$desktopEventTabs = array_slice($navigationEvents, 0, 5)') && str_contains($progress, '$desktopEventOverflow'), 'Atletismo deve limitar provas e manter More local.');
$assert(str_contains($progress, "'slug' => 'atletismo'") === false || str_contains($progress, "stridebr_t('progress.athletics')"), 'UX deve continuar tratando Atletismo como entrada global única.');
$assert(str_contains($progress, '$primarySportSummary = array_slice($sportPeriodSummary, 0, 7)') && str_contains($progress, '$extraSportSummary = array_slice($sportPeriodSummary, 7)'), 'Your sports deve limitar a lista inicial.');
$assert(str_contains($progress, 'progress-list-disclosure') && str_contains($progress, "stridebr_t('progress.show_more_sports'"), 'Lista completa de esportes deve usar disclosure sem depender de JS.');
$assert(str_contains($progress, 'is-period-empty'), 'Esportes sem atividade no período devem ter peso visual reduzido.');
$assert(str_contains($progress, '$distributionPrimary = array_slice($distribution, 0, 6, true)') && str_contains($progress, '$distributionExtra = array_slice($distribution, 6, null, true)'), 'Distribuição deve usar detalhe progressivo quando houver muitas modalidades.');
$assert(!str_contains($progress, '<select name="metric"') && str_contains($progress, 'progress-chart-switch'), 'Métrica não pode continuar como seletor global; alternância deve ficar no gráfico.');
$assert(str_contains($progress, '<input type="hidden" name="metric"'), 'Troca de período deve preservar a métrica atual sem expor controle global.');
$assert(!str_contains($progress, "stridebr_t('nav.goals')") && substr_count($progress, "stridebr_t('common.goals')") >= 2, 'Metas não pode vazar chave i18n nav.goals.');
$assert(str_contains($css, '.progress-summary-section .progress-kpi-strip{grid-template-columns:repeat(4') && str_contains($css, '@media(max-width:820px)') && str_contains($css, 'grid-template-columns:repeat(2'), 'Resumo deve ser 4 colunas no desktop e 2×2 em telas menores.');
$assert(str_contains($css, '.progress-sport-row{grid-template-columns:34px minmax(170px,1fr) minmax(90px,140px) 14px'), 'Linhas de esporte devem usar colunas previsíveis em vez de espaço arbitrário.');
$assert(str_contains($css, '.progress-nav-menu{position:absolute') && str_contains($css, 'max-height:min(420px,60vh)') && str_contains($css, 'overflow-y:auto'), 'Menu More desktop deve ter viewport e scroll internos seguros.');
$assert(str_contains($css, '@media(max-width:620px)') && str_contains($css, '.progress-nav-menu{position:fixed') && str_contains($css, 'env(safe-area-inset-bottom)'), 'Menu More mobile deve respeitar viewport e safe area.');
$assert(str_contains($css, '.progress-sport-nav>.progress-priority-tab:not(.is-mobile-primary)') && str_contains($css, '.progress-event-nav>.progress-priority-tab:not(.is-mobile-primary)'), 'Mobile não deve reproduzir todas as tabs desktop.');
$assert(str_contains($css, '.progress-highlight-grid{grid-template-columns:repeat(2') && str_contains($css, 'article:only-child'), 'Highlights deve usar área proporcional, inclusive com um único destaque.');
$highlightRules = [];
preg_match_all('/\.progress-highlight-grid article\s*\{([^}]*)\}/', $css, $highlightRules);
$assert(count($highlightRules[1] ?? []) >= 1, 'Highlights precisa manter regra explícita de superfície.');
foreach (($highlightRules[1] ?? []) as $ruleBody) {
    $assert(!preg_match('/padding(?:-inline)?\s*:\s*[^;]*\b0(?:px)?\b/i', $ruleBody), 'Highlights não pode perder padding horizontal por override posterior.');
}
$assert(str_contains($css, '.progress-consistency-band{max-width:960px') && str_contains($css, 'gap:6px'), 'Consistência deve manter largura e gaps controlados.');
$assert(str_contains($css, '.progress-distribution-list{max-width:960px') && str_contains($css, 'grid-template-columns:minmax(170px,230px)'), 'Distribuição deve alinhar nome, barra e percentual.');
$assert(str_contains($css, ':root[data-theme="dark"]') && str_contains($css, 'var(--ui-panel)') && str_contains($css, 'var(--ui-border)'), 'Novas superfícies devem continuar baseadas nos tokens de dark mode.');
$assert(str_contains($js, 'closeNavMenus') && str_contains($js, "event.key !== 'Escape'"), 'More deve fechar por clique externo/resize e Escape como enhancement.');
$assert(str_contains($js, 'AbortController') && str_contains($js, 'history.pushState') && str_contains($js, "window.addEventListener('popstate'") && str_contains($js, 'event.metaKey || event.ctrlKey'), 'Polimento não pode regredir progressive enhancement.');
$assert(str_contains($progress, '<noscript><button type="submit">'), 'Fallback GET sem JS deve continuar disponível.');

foreach (['progress.more', 'progress.more_sports', 'progress.more_events', 'progress.show_more_sports', 'progress.show_fewer_sports', 'progress.chart_metric_aria'] as $key) {
    $assert(str_contains($pt, "'{$key}'") && str_contains($en, "'{$key}'"), "Tradução {$key} precisa existir nos dois locales.");
}

printf("✓ progress visual polish static: %d assertions\n", $checks);
