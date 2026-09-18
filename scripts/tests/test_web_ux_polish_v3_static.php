<?php
$root = dirname(__DIR__, 2);
$read = static fn(string $path): string => file_get_contents($root . '/' . $path);
$count = 0;
$assert = static function (bool $ok, string $message) use (&$count): void {
    $count++;
    if (!$ok) { fwrite(STDERR, "FAIL UX V3: $message\n"); exit(1); }
};
$friends = $read('public/user/amigos.php');
$css = $read('public/assets/css/ui-refresh.css');
$assert(str_contains($friends, 'AS nome_exibicao') && str_contains($friends, 'stridebr_person_name_for_display') && str_contains($friends, "stridebr_e(\$user['username'])"), 'friends preserve actual names and optional usernames');
$assert(str_contains($css, '.friends-shell .person-card{grid-template-columns:minmax(0,1fr) auto') && str_contains($css, '.person-identity-link strong'), 'identity wrapper has space for visible text');
$assert(str_contains($friends, 'u.descobrivel = TRUE') && str_contains($friends, 'ILIKE :term_name') && str_contains($friends, 'label for="friend-q"'), 'search preserves discovery and accessible label');
$assert(str_contains($friends, 'friends.load_error') && str_contains($friends, 'elseif ($friends === [])'), 'load error is distinct from empty list');
$routes = $read('public/user/rotas.php');
$assert(str_contains($routes, "stridebr_t('routes.new')") && str_contains($routes, 'with_route=1'), 'new route uses existing GPS activity workflow');
$assert(str_contains($routes, 'routes-toolbar') && str_contains($routes, 'routeSport') && str_contains($routes, 'routeDistance'), 'route filters use actual saved data');
$assert(str_contains($routes, 'ganho_elevacao_m') && str_contains($routes, 'distancia_m') && str_contains($routes, 'data-route-mini-map'), 'route list includes map, distance and elevation');
$assert(str_contains($routes, 'route-card-menu') && str_contains($routes, 'route-edit') && !str_contains($routes, 'Duplicar'), 'existing secondary actions are contextual');
$assert(str_contains($css, '@media(max-height:880px)') && str_contains($css, 'max-height:calc(100dvh - 80px)') && str_contains($css, 'overflow-y:auto') && str_contains($css, 'flex-direction:row'), 'menu adapts to height and keeps scroll fallback');
$assert(str_contains($read('public/admin/index.php'), 'admin-overview-grid') && str_contains($css, '.admin-shell .admin-grid{align-items:start}') && str_contains($css, '.admin-overview-grid>.admin-feature-controls .flag-list{grid-template-columns:repeat(3'), 'overview avoids stretched empty panels and groups flags horizontally');
$assert(str_contains($css, '.admin-shell .admin-card{padding:12px') && str_contains($css, '.admin-shell .admin-table-wrap :is(th,td){padding:7px'), 'admin panels and semantic tables remain dense');
$assert(str_contains($read('public/admin/events.php'), 'last_success_at') && str_contains($read('public/admin/events.php'), 'last_error_code'), 'provider panel preserves actual diagnostics');
$pt = require $root . '/src/i18n/pt-BR.php';
$en = require $root . '/src/i18n/en.php';
$assert($pt['activity.detail.expand'] === 'Abrir detalhes' && $en['activity.detail.expand'] === 'Open details', 'detail action exists in both locales');
$assert(str_contains($pt['news.september.events'], 'permanecem desabilitadas') && str_contains($en['news.september.events'], 'remain disabled'), 'news never claims external sync is enabled');
foreach ($pt as $key => $value) if (str_starts_with($key, 'routes.') || str_starts_with($key, 'news.september.') || str_starts_with($key, 'admin.external_sync.')) $assert(isset($en[$key]), 'translation parity: ' . $key);
echo "✓ Web UX Polish V3: $count contracts\n";
