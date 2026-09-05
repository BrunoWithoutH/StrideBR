<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);
$manifestPath = $root . '/public/manifest.webmanifest';
$manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
$pwa = $read('public/assets/js/pwa.js');
$sw = $read('public/sw.js');
$boot = $read('src/includes/i18n.php');
$css = $read('public/assets/css/ui-refresh.css');
$activities = $read('public/user/atividades.php');
$gps = $read('public/user/gravar-atividade.php');
$offline = $read('public/offline.html');

$iconChecks = true;
foreach ([
    '/assets/img/pwa/icon-192.png' => [192, 192],
    '/assets/img/pwa/icon-512.png' => [512, 512],
    '/assets/img/pwa/maskable-512.png' => [512, 512],
    '/assets/img/pwa/apple-touch-icon.png' => [180, 180],
] as $path => $expected) {
    $absolute = $root . '/public' . $path;
    $size = is_file($absolute) ? getimagesize($absolute) : false;
    $iconChecks = $iconChecks && $size !== false && [$size[0], $size[1]] === $expected;
}

$manifestIcons = array_column($manifest['icons'] ?? [], 'src');
$checks = [
    'manifest possui identidade e escopo root-relative' => ($manifest['name'] ?? null) === 'StrideBR' && ($manifest['short_name'] ?? null) === 'StrideBR' && ($manifest['id'] ?? null) === '/' && ($manifest['start_url'] ?? null) === '/home.php' && ($manifest['scope'] ?? null) === '/',
    'manifest abre standalone com cores coerentes' => ($manifest['display'] ?? null) === 'standalone' && ($manifest['background_color'] ?? null) === '#F0F1EF' && ($manifest['theme_color'] ?? null) === '#40507C',
    'manifest aponta para ícones instaláveis e maskable' => in_array('/assets/img/pwa/icon-192.png', $manifestIcons, true) && in_array('/assets/img/pwa/icon-512.png', $manifestIcons, true) && in_array('/assets/img/pwa/maskable-512.png', $manifestIcons, true),
    'ícones PWA existem nas dimensões declaradas' => $iconChecks,
    'boot compartilhado inclui manifest e metadados iOS' => str_contains($boot, 'rel="manifest" href="/manifest.webmanifest"') && str_contains($boot, 'apple-mobile-web-app-capable') && str_contains($boot, 'apple-mobile-web-app-title') && str_contains($boot, 'apple-touch-icon') && str_contains($boot, '/assets/js/pwa.js'),
    'páginas principais usam viewport-fit cover' => str_contains($activities, 'viewport-fit=cover') && str_contains($gps, 'viewport-fit=cover'),
    'detecção standalone é central para padrão e iOS legado' => str_contains($pwa, "matchMedia?.('(display-mode: standalone)')") && str_contains($pwa, 'window.navigator.standalone === true') && str_contains($pwa, "classList.toggle('is-standalone'") && str_contains($pwa, 'dataset.displayMode') && str_contains($pwa, 'window.StrideBRPWA'),
    'service worker é registrado apenas em contexto seguro e com scope raiz' => str_contains($pwa, "'serviceWorker' in navigator && window.isSecureContext") && str_contains($pwa, "navigator.serviceWorker.register('/sw.js', {scope: '/'})"),
    'service worker pré-cacheia apenas shell estático seguro' => str_contains($sw, "const STATIC_CACHE = 'stridebr-static-v1'") && str_contains($sw, "'/assets/css/style.css'") && str_contains($sw, "'/assets/js/pwa.js'") && str_contains($sw, "'/offline.html'") && !str_contains($sw, "'/api/") && !str_contains($sw, "'/user/atividades.php'"),
    'navegação autenticada permanece network-first sem cache persistente' => str_contains($sw, "request.mode === 'navigate'") && str_contains($sw, "fetch(request, {cache: 'no-store'})") && str_contains($sw, 'caches.match(OFFLINE_URL)'),
    'cache runtime fica restrito a assets e manifest' => str_contains($sw, "url.pathname.startsWith('/assets/') || url.pathname === '/manifest.webmanifest'") && str_contains($sw, 'cache.put(request, response.clone())'),
    'fallback offline é neutro e não promete GPS offline' => str_contains($offline, 'Sem conexão') && str_contains($offline, 'Reconecte-se para continuar usando o StrideBR') && !str_contains(strtolower($offline), 'gps offline') && !str_contains(strtolower($offline), 'continue correndo'),
    'safe areas só ganham variáveis PWA adicionais em standalone' => str_contains($css, ':root{') && str_contains($css, '--pwa-safe-top:0px') && str_contains($css, 'html.is-standalone{') && str_contains($css, '--pwa-safe-top:env(safe-area-inset-top)') && str_contains($css, 'html.is-standalone .site-header'),
    'bottom navigation standalone respeita laterais e base seguras' => str_contains($css, 'html.is-standalone .mobile-bottom-nav') && str_contains($css, 'var(--pwa-safe-left)') && str_contains($css, 'var(--pwa-safe-right)') && str_contains($css, 'var(--pwa-safe-bottom)'),
];

$failed = [];
foreach ($checks as $label => $ok) if (!$ok) $failed[] = $label;
if ($failed !== []) {
    fwrite(STDERR, "Falhas na fundação PWA:\n- " . implode("\n- ", $failed) . "\n");
    exit(1);
}
echo '✓ PWA foundation static: ' . count($checks) . " assertions\n";
