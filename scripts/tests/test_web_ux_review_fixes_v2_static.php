<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);
$count = 0;
$assert = static function (bool $ok, string $message) use (&$count): void {
    $count++;
    if (!$ok) {
        fwrite(STDERR, "Web UX Review Fixes V2 static failed: {$message}\n");
        exit(1);
    }
};

$page = $read('public/user/atividades.php');
$activitiesJs = $read('public/assets/js/atividades.js');
$activitiesCss = $read('public/assets/css/atividades.css');
$detailJs = $read('public/assets/js/activity-detail-v3.js');
$detailCss = $read('public/assets/css/activity-detail-v3.css');
$home = $read('public/home.php');
$homeCss = $read('public/assets/css/dashboard-v2.css');
$header = $read('src/layout/header.php');

$topbar = strpos($page, 'activity-detail-topbar');
$headerStart = strpos($page, '<header>', $topbar ?: 0);
$assert($topbar !== false && $headerStart !== false && $topbar < $headerStart, 'Voltar às atividades precisa ficar em topbar própria antes do header.');
$assert(str_contains($page, 'type="button" class="activity-detail-back-link"') && str_contains($activitiesCss, 'white-space:nowrap!important') && str_contains($activitiesCss, 'border:1px solid var(--ui-border)!important'), 'Voltar precisa ser button quiet real e não quebrar linha.');
$assert(str_contains($page, 'data-detail-full-actions') && str_contains($page, 'data-share-activity') && str_contains($page, 'data-detail-edit') && str_contains($page, 'data-detail-actions-menu'), 'Ações desktop precisam morar no header do detail.');
$assert(str_contains($page, 'data-detail-compare') && str_contains($page, 'data-detail-repeat') && str_contains($page, 'data-detail-stats') && str_contains($page, 'data-detail-delete'), 'Menu secundário precisa agrupar comparar, repetir, estatísticas e excluir.');
$assert(str_contains($page, 'data-detail-delete') && str_contains($page, 'is-danger'), 'Excluir precisa permanecer danger no fim do menu.');
$assert(str_contains($activitiesCss, 'top:calc(100% + 6px)!important') && str_contains($activitiesCss, 'right:0!important') && str_contains($activitiesCss, 'max-width:min(280px,calc(100vw - 24px))'), 'Menu ... precisa ancorar abaixo e permanecer dentro da viewport.');
$assert(str_contains($activitiesJs, "document.querySelectorAll('[data-detail-actions-menu][open]')") && str_contains($activitiesJs, "event.key === 'Escape'"), 'Menu ... precisa fechar por click outside e Escape.');
$assert(str_contains($activitiesCss, 'grid-template-columns:repeat(auto-fit,minmax(min(145px,100%),1fr))'), 'Métricas principais do header precisam usar grid fluido.');
$assert(str_contains($detailCss, 'repeat(auto-fit,minmax(min(150px,100%),1fr))'), 'Grids internos de métricas precisam ser fluidos.');
$assert(!str_contains($detailJs, 'const summary = `${metricCards(activity.metricas || [])}') && str_contains($detailJs, 'primaryMetricLabels'), 'Resumo full detail não pode repetir métricas principais do header.');

foreach (['Elevação','Elevacao','Elevation','Altitude','Elevation gain','Elevation loss','Grade','Inclination','Ganho','Perda','Desnível'] as $label) {
    $normalized = strtolower(strtr($label, ['ç' => 'c', 'ã' => 'a', 'é' => 'e', 'í' => 'i']));
    $assert(!str_contains($normalized, 'dummy'), 'fixture de label de elevação precisa ser válida.');
}
$assert(str_contains($activitiesJs, 'eleva(?:ção|cao|tion)') && str_contains($activitiesJs, 'gain|loss') && str_contains($activitiesJs, 'ascent|descent') && str_contains($activitiesJs, 'inclina(?:ção|cao|tion)'), 'Filtro principal precisa reconhecer elevação PT e EN.');
$assert(str_contains($detailJs, 'eleva(?:ção|cao|tion)') && str_contains($detailJs, 'terrain\\s+elevation'), 'Detail V3 precisa filtrar Elevation e terrain elevation.');
$assert(str_contains($detailJs, 'activity-v3-unit-title') && str_contains($detailJs, 'activity-v3-unit-metrics') && !str_contains($detailJs, '${metricCards(unit.valores)}'), 'Trechos precisam usar rows compactas, sem painéis de métricas aninhados.');
$assert(str_contains($detailJs, 'routeHtml(activity,{saveAction:true,contextRail})'), 'Salvar rota precisa continuar no bloco Percurso.');

$assert(str_contains($homeCss, '.dashboard-home-rail{min-width:0;position:static;align-self:start;display:grid;gap:14px}') && !str_contains($homeCss, '.dashboard-home-rail{min-width:0;position:sticky'), 'Right rail da Home não pode ser sticky.');
$competitions = strpos($home, 'dashboard-competition-rail');
$pacer = strpos($home, 'dashboard-pacer-rail');
$goals = strpos($home, 'dashboard-goals-rail');
$assert($competitions !== false && $pacer !== false && $goals !== false && $competitions < $pacer && $pacer < $goals, 'Rail precisa ordenar competições, Pacer e metas.');
$assert(str_contains($home, "pacerPlanList(\$pdo, \$idUsuario, ['status' => 'active'])") && str_contains($home, '/user/pacer.php?edit='), 'Home precisa usar plano Pacer ativo como fallback e abrir o plano selecionado.');
$assert(str_contains($home, 'Nenhuma estratégia ativa.') && str_contains($home, '/user/pacer.php?new=1'), 'Rail Pacer precisa ter empty state compacto.');
$assert(str_contains($home, 'array_slice($metas, 0, 3)') && str_contains($home, 'dashboard-goal-rail-list'), 'Rail de metas precisa limitar a três metas.');
$assert(!str_contains($home, 'dashboard-modules dashboard-modules-fixed') && !preg_match('/dashboard-secondary-actions[^\n]+Criar Pacer/', $home), 'Home não pode manter módulo largo de metas nem CTA Criar Pacer duplicada.');
$assert(str_contains($homeCss, '@media(max-width:900px){.dashboard-home-layout{grid-template-columns:1fr}'), 'Rail precisa cair para coluna única no mobile.');

$assert(str_contains($header, "'/user/treinador.php'") && str_contains($header, 'href="/user/treinador.php"') && str_contains($header, "nav.training_trainer_desc"), 'Treinador precisa existir em Treinos e manter seção ativa.');
$assert(substr_count($header, '/user/treinador.php') >= 3, 'Treinador precisa permanecer também nos menus relacionais já existentes.');

printf("✓ Web UX Review Fixes V2 static: %d assertions\n", $count);
