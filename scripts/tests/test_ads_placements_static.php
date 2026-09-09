<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/src/includes/app.php';
require_once $root . '/src/layout/ads.php';

$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    $checks++;
    if (!$ok) {
        fwrite(STDERR, "Ads placements static failed: {$message}\n");
        exit(1);
    }
};
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);

$placements = [
    'home-after-week' => ['public/home.php', 'STRIDEBR_ADSENSE_SLOT_HOME_AFTER_WEEK'],
    'library-end' => ['public/user/biblioteca.php', 'STRIDEBR_ADSENSE_SLOT_LIBRARY_END'],
    'profile-end' => ['public/user/perfil.php', 'STRIDEBR_ADSENSE_SLOT_PROFILE_END'],
    'equipment-end' => ['public/user/equipamentos.php', 'STRIDEBR_ADSENSE_SLOT_EQUIPMENT_END'],
    'events-list-end' => ['public/calendario.php', 'STRIDEBR_ADSENSE_SLOT_EVENTS_LIST_END'],
    'event-detail-end' => ['public/evento.php', 'STRIDEBR_ADSENSE_SLOT_EVENT_DETAIL_END'],
    'public-content-end' => ['src/layout/static_page.php', 'STRIDEBR_ADSENSE_SLOT_PUBLIC_CONTENT_END'],
];
$money = $read('src/function/monetization.php');
$adsLayout = $read('src/layout/ads.php');
$footer = $read('src/layout/footer.php');
$env = $read('.env.example');
$compose = $read('compose.yaml');
$setup = $read('scripts/setup_env.sh');
$setupFixture = tempnam(sys_get_temp_dir(), 'stridebr-env-test-');
unlink($setupFixture);
exec('STRIDEBR_ENV_FILE=' . escapeshellarg($setupFixture) . ' sh ' . escapeshellarg($root . '/scripts/setup_env.sh'), $setupOutput, $setupExit);
$assert($setupExit === 0, 'setup de ambiente precisa executar com sucesso.');
$setupGenerated = (string) file_get_contents($setupFixture);
unlink($setupFixture);
$docs = $read('docs/MONETIZATION.md');
$adsJs = $read('public/assets/js/ads.js');
$csp = $read('src/includes/http_headers.php');

foreach ($placements as $placement => [$file, $slotEnv]) {
    $assert(str_contains($money, "'{$placement}'") && str_contains($money, "'slot_env' => '{$slotEnv}'"), "registry precisa conter {$placement}.");
    $assert(str_contains($read($file), "stridebr_render_ad_slot('{$placement}'"), "{$file} precisa declarar {$placement}.");
    $assert(str_contains($env, $slotEnv . '=') && str_contains($compose, $slotEnv . ':') && str_contains($setupGenerated, $slotEnv . '='), "configuração precisa propagar {$slotEnv}.");
}

$assert(str_contains($env, "STRIDEBR_ADS_ENABLED=0\nSTRIDEBR_ADS_AUTHENTICATED_ENABLED=0\nSTRIDEBR_ADS_PLACEHOLDERS=0\nSTRIDEBR_ADS_DEV_PREVIEW=0"), 'flags precisam nascer desligadas.');
$assert(!str_contains($env, 'STRIDEBR_ADSENSE_SLOT_FOOTER') && !str_contains($compose, 'STRIDEBR_ADSENSE_SLOT_RAIL_LEFT') && !str_contains($setup, 'STRIDEBR_ADSENSE_SLOT_RAIL_RIGHT'), 'slots legacy não podem continuar ativos.');
$assert(!str_contains($adsLayout, 'rail-left') && !str_contains($adsLayout, 'rail-right') && !str_contains($adsLayout, "'footer'"), 'renderer não pode manter rails/footer legacy.');
$assert(str_contains($footer, 'stridebr_render_ads_runtime()') && !str_contains($footer, 'stridebr_ads_enabled() || stridebr_ads_placeholders_enabled()'), 'footer deve apenas emitir runtime quando registrado.');
$assert(str_contains($money, "'/pages/help/faq.php'") && str_contains($money, "'/pages/about/about.php'") && !str_contains($money, "'/pages/about/team.php'"), 'public-content precisa usar allowlist explícita e excluir Team.');
$assert(str_contains($money, "'/user/atividades.php'") && str_contains($money, "'/admin/'") && str_contains($money, "'/feedback.php'"), 'denylist precisa cobrir páginas operacionais/sensíveis.');
$assert(str_contains($adsJs, "data-ad-status") && str_contains($adsJs, "unfilled") && str_contains($adsJs, 'stridebrAdInitialized'), 'runtime precisa tratar no-fill e impedir dupla inicialização.');
$assert(!str_contains($adsJs, 'stridebr.ads.consent') && !str_contains($adsLayout, 'ad-consent'), 'consentimento local antigo precisa estar desacoplado do runtime.');
$assert(str_contains($csp, 'pagead2.googlesyndication.com') && str_contains($csp, 'frame-src') && str_contains($csp, 'doubleclick.net'), 'CSP precisa estar preparada para o provider futuro.');
$assert(str_contains($read('public/index.php'), 'stridebr_ui_boot_script()') && !str_contains($read('public/index.php'), 'stridebr_adsense_verification_meta()') && str_contains($read('src/includes/i18n.php'), 'return stridebr_adsense_verification_meta()'), 'landing pública precisa suportar meta de verificação sem ligar ads.');
$assert($read('public/ads.txt') === "google.com, pub-3948145279411749, DIRECT, f08c47fec0942fa0\n", 'public/ads.txt precisa conter exatamente a autorização do publisher.');
$assert(str_contains($docs, 'crawler login') && str_contains($docs, 'CMP') && str_contains($docs, 'php scripts/ads_status.php'), 'documentação precisa cobrir ativação futura completa.');

$allAdVars = [
    'STRIDEBR_ADS_ENABLED', 'STRIDEBR_ADS_AUTHENTICATED_ENABLED', 'STRIDEBR_ADS_PLACEHOLDERS', 'STRIDEBR_ADS_DEV_PREVIEW', 'STRIDEBR_ADSENSE_CLIENT',
    ...array_map(static fn(array $entry): string => $entry[1], array_values($placements)),
];
$reset = static function () use ($allAdVars): void {
    foreach ($allAdVars as $name) putenv($name . '=');
    putenv('STRIDEBR_APP_ENV=development');
    stridebr_ads_reset_request_state();
};
$render = static function (string $placement, string $path, bool $auth): string {
    ob_start();
    stridebr_render_ad_slot($placement, $path, $auth);
    stridebr_render_ads_runtime();
    return (string) ob_get_clean();
};

$reset();
$out = $render('home-after-week', '/home.php', true);
$assert($out === '', 'ADS OFF precisa ter zero footprint.');

$reset(); putenv('STRIDEBR_ADS_DEV_PREVIEW=1');
$out = $render('home-after-week', '/home.php', true);
$assert(str_contains($out, 'data-ad-preview="1"') && str_contains($out, 'home-after-week') && !str_contains($out, 'adsbygoogle') && !str_contains($out, 'ads.js'), 'preview precisa renderizar local sem provider/config real.');

$reset(); putenv('STRIDEBR_ADS_ENABLED=1'); putenv('STRIDEBR_ADSENSE_SLOT_EVENT_DETAIL_END=1234567890');
$assert($render('event-detail-end', '/evento.php', false) === '', 'master ON com client vazio precisa falhar fechado.');

$reset(); putenv('STRIDEBR_ADS_ENABLED=1'); putenv('STRIDEBR_ADSENSE_CLIENT=ca-pub-1234567890123456');
$assert($render('event-detail-end', '/evento.php', false) === '', 'slot vazio precisa desativar apenas o placement.');

$reset(); putenv('STRIDEBR_ADS_ENABLED=1'); putenv('STRIDEBR_ADSENSE_CLIENT=ca-pub-1234567890123456'); putenv('STRIDEBR_ADSENSE_SLOT_HOME_AFTER_WEEK=1234567890');
$assert($render('home-after-week', '/home.php', true) === '', 'ads autenticados precisam do gate separado.');

$reset(); putenv('STRIDEBR_ADS_ENABLED=1'); putenv('STRIDEBR_ADS_AUTHENTICATED_ENABLED=1'); putenv('STRIDEBR_ADSENSE_CLIENT=ca-pub-1234567890123456'); putenv('STRIDEBR_ADSENSE_SLOT_HOME_AFTER_WEEK=1234567890');
$out = $render('home-after-week', '/home.php', true);
$assert(str_contains($out, 'class="adsbygoogle site-ad-provider"') && str_contains($out, 'stridebr-ads-config') && str_contains($out, '/assets/js/ads.js'), 'configuração autenticada válida precisa preparar um único slot real.');

$reset(); putenv('STRIDEBR_ADS_ENABLED=1'); putenv('STRIDEBR_ADSENSE_CLIENT=ca-pub-1234567890123456'); putenv('STRIDEBR_ADSENSE_SLOT_EVENTS_LIST_END=1234567890');
$assert($render('events-list-end', '/user/atividades.php', true) === '', 'página proibida precisa permanecer sem ads mesmo com configuração válida.');

$reset(); putenv('STRIDEBR_ADS_DEV_PREVIEW=1');
ob_start();
$first = stridebr_render_ad_slot('events-list-end', '/calendario.php', false);
$second = stridebr_render_ad_slot('event-detail-end', '/evento.php', false);
$html = (string) ob_get_clean();
$assert($first && !$second && substr_count($html, 'site-ad-placement') >= 1, 'request deve aceitar no máximo um placement.');

$reset();
$assert(stridebr_adsense_slot_id('footer') === '' && stridebr_adsense_slot_id('rail-left') === '' && stridebr_adsense_slot_id('rail-right') === '', 'placements legacy precisam ser inertes.');

$reset(); putenv('STRIDEBR_ADSENSE_CLIENT=ca-pub-1234567890123456');
$assert(str_contains(stridebr_adsense_verification_meta(), 'google-adsense-account') && !stridebr_ads_enabled(), 'meta de verificação deve funcionar com master OFF.');

$reset();
$assert(stridebr_adsense_verification_meta() === '<meta name="google-adsense-account" content="ca-pub-3948145279411749">', 'verificação deve identificar o proprietário mesmo sem configurar o provider.');
$assert(!stridebr_ads_enabled() && stridebr_adsense_client_id() === '' && $render('event-detail-end', '/evento.php', false) === '', 'verificação não pode configurar o provider nem ativar anúncios.');

printf("✓ ads placements static: %d assertions\n", $checks);
