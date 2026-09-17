<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);
$n = 0;
$assert = static function (bool $ok, string $message) use (&$n): void {
    $n++;
    if (!$ok) throw new RuntimeException($message);
};

$build = $read('src/function/build_identifier.php');
$app = $read('src/includes/app.php');
$api = $read('src/function/api_v1.php');
$footer = $read('src/layout/footer.php');
$diagnostics = $read('public/admin/diagnostics.php');
$home = $read('public/home.php');
$homeCss = $read('public/assets/css/dashboard-v2.css');
$competitions = $read('public/user/competicoes.php');
$routes = $read('public/user/rotas.php');
$pacer = $read('public/user/pacer.php');
$pacerCss = $read('public/assets/css/pacer-web.css');
$env = $read('.env.example');
$deploy = $read('docs/DEPLOY_DOKPLOY.md');

$assert(str_contains($build, "getenv('STRIDEBR_BUILD')") && str_contains($build, "STRIDEBR_BUILD_FILE_FALLBACK"), 'Build precisa ter env canônico e fallback de arquivo opt-in.');
$assert(str_contains($app, 'stridebr_build_identifier()') && str_contains($api, 'stridebr_build_identifier()'), 'Web e API precisam compartilhar a mesma fonte de build.');
$assert(!str_contains($app, '20260825-daily-use-polish'), 'Web não pode fingir build hardcoded quando identificador está ausente.');
$assert(str_contains($footer, '$footerBuild !==') && str_contains($diagnostics, 'build não informado'), 'Footer/admin precisam lidar com build ausente sem mostrar identificador stale.');
$assert(str_contains($env, 'STRIDEBR_BUILD_FILE_FALLBACK=false') && str_contains($deploy, 'Do not depend on `.stridebr-build` in Dokploy'), 'Deploy precisa documentar STRIDEBR_BUILD como fonte canônica.');

$assert(str_contains($home, 'data-dashboard-module="recent"') && str_contains($home, 'Importar atividade') && !str_contains($home, '>Última atividade<'), 'Home PT-BR precisa usar lista de atividades recentes, não card isolado de última atividade.');
$assert(str_contains($homeCss, '.dashboard-home-layout') && str_contains($homeCss, 'minmax(280px,320px)') && str_contains($homeCss, '.dashboard-period-strip'), 'Home precisa usar composição principal com rail e faixa compacta de 28 dias.');
$assert(str_contains($home, 'dashboard-button-primary') && str_contains($home, 'home.log_activity') && str_contains($home, 'home.record_gps'), 'Gravar com GPS e registrar atividade precisam liderar o topo.');

$assert(str_contains($competitions, '$all === [] && !$isFormOpen') && str_contains($competitions, "stridebr_t('competitions.empty')") && str_contains($competitions, "stridebr_t('competitions.empty_help')"), 'Competições vazias precisam de uma única superfície de empty state.');
$assert(str_contains($competitions, "stridebr_t('competitions.upcoming')") && str_contains($competitions, "stridebr_t('competitions.history')"), 'Competições com dados precisam separar próximas e histórico.');
$assert(!str_contains($competitions, "stridebr_t('competitions.recent')"), 'Título genérico Recentes não deve comandar a lista pessoal.');

$assert(str_contains($routes, 'if ($routes === [] && $detail === null)') && str_contains($routes, 'Nenhuma rota salva.'), 'Rotas vazias precisam evitar split pane sem conteúdo.');
$assert(str_contains($routes, 'Ver atividades com rota') && str_contains($routes, 'data-route-detail-map'), 'Rotas precisa manter CTA útil e detail com mapa quando houver dados.');

$assert(str_contains($pacer, '$hasAnyPlans') && str_contains($pacer, 'is-first-use'), 'Pacer zero-state precisa priorizar o editor real.');
$assert(str_contains($pacer, 'Estratégias de ritmo para treinos e provas.') && str_contains($pacerCss, '.pacer-layout.is-first-use'), 'Pacer precisa ter descoberta e layout de primeiro uso próprios.');


require_once $root . '/src/function/build_identifier.php';
$previousBuild = getenv('STRIDEBR_BUILD');
$previousFallback = getenv('STRIDEBR_BUILD_FILE_FALLBACK');
putenv('STRIDEBR_BUILD=7144a55-test');
putenv('STRIDEBR_BUILD_FILE_FALLBACK=false');
$assert(stridebr_build_identifier() === '7144a55-test', 'Build configurado no deploy precisa vencer qualquer fallback.');
putenv('STRIDEBR_BUILD');
putenv('STRIDEBR_BUILD_FILE_FALLBACK=false');
$assert(stridebr_build_identifier() === null, 'Sem env, fallback de arquivo desativado não pode vazar .stridebr-build stale.');
putenv('STRIDEBR_BUILD_FILE_FALLBACK=true');
$assert(stridebr_build_identifier() !== null, 'Fallback legado só pode funcionar quando explicitamente habilitado.');
$previousBuild === false ? putenv('STRIDEBR_BUILD') : putenv('STRIDEBR_BUILD=' . $previousBuild);
$previousFallback === false ? putenv('STRIDEBR_BUILD_FILE_FALLBACK') : putenv('STRIDEBR_BUILD_FILE_FALLBACK=' . $previousFallback);

printf("✓ production stabilization/web polish static: %d assertions\n", $n);
