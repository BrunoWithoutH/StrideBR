<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);

$app = $read('src/includes/app.php');
$i18n = $read('src/includes/i18n.php');
$en = $read('src/i18n/en.php');
$pt = $read('src/i18n/pt-BR.php');
$header = $read('src/layout/header.php');
$footer = $read('src/layout/footer.php');
$settings = $read('public/user/settings.php');
$login = $read('public/login.php');
$signup = $read('public/signup.php');
$activities = $read('public/user/atividades.php');
$auth = $read('src/includes/auth.php');
$googleStart = $read('public/auth/google.php');
$googleCallback = $read('public/auth/google-callback.php');
$themeJs = $read('public/assets/js/ui-preferences.js');
$themeBootJs = $read('public/assets/js/ui-boot.js');
$htaccess = $read('public/.htaccess');
$diagnostics = $read('public/admin/diagnostics.php');
$runtime = $read('public/assets/js/i18n-runtime.js');
$css = $read('public/assets/css/ui-refresh.css');
$activitiesCss = $read('public/assets/css/atividades.css');
$styleCss = $read('public/assets/css/style.css');
$migration = $read('src/database/migrations/20260903_v1_rc.sql');
$env = $read('.env.example');

$checks = [
    'i18n carregado no bootstrap' => str_contains($app, "require_once __DIR__ . '/i18n.php'"),
    'português e inglês disponíveis' => str_contains($i18n, "'pt-BR'") && str_contains($i18n, "'en'") && str_contains($en, "'nav.home' => 'Home'") && str_contains($pt, "'nav.home' => 'Início'"),
    'idioma automático usa pt ou inglês' => str_contains($i18n, 'stridebr_detect_locale') && str_contains($i18n, "return 'en';") && str_contains($i18n, "return 'pt-BR';") && str_contains($i18n, "['auto', 'pt-BR', 'en']"),
    'idioma e tema persistíveis nas preferências' => str_contains($settings, 'name="locale"') && str_contains($settings, 'value="auto"') && str_contains($settings, 'name="theme"') && str_contains($settings, "\$preferences['locale']") && str_contains($settings, "\$preferences['theme']"),
    'idioma e tema ficam em personalização' => str_contains($settings, "settings.personalization") && str_contains($settings, "settings.language_auto"),
    'cabeçalho não expõe tema e idioma' => !str_contains($header, 'data-locale-select') && !str_contains($header, 'data-theme-select') && !str_contains($header, 'data-theme-toggle'),
    'autenticação não expõe tema e idioma' => !str_contains($login, 'auth-page-tools') && !str_contains($signup, 'auth-page-tools') && !str_contains($login, 'data-locale-select') && !str_contains($signup, 'data-locale-select'),
    'notificações usam dropdown no header' => str_contains($header, 'header-notification-menu') && str_contains($header, 'header-notification-popover') && str_contains($header, 'notificacaoListar'),
    'runtime de preferência carregado globalmente' => str_contains($footer, '/assets/js/ui-preferences.js') && str_contains($footer, 'stridebr_i18n_runtime_script(false)'),
    'boot de aparência roda antes do CSS nas atividades' => strpos($activities, 'stridebr_ui_boot_script()') < strpos($activities, '/assets/css/style.css'),
    'boot de aparência é externo e compatível com CSP' => str_contains($i18n, "stridebr_asset('/assets/js/ui-boot.js')") && str_contains($i18n, 'data-theme-mode=') && !str_contains($i18n, '<script data-stridebr-ui-boot>(function()') && str_contains($themeBootJs, 'document.currentScript') && str_contains($themeBootJs, 'root.dataset.theme = theme') && str_contains($htaccess, "script-src 'self' https://unpkg.com"),
    'diagnóstico separa migrations atuais de consolidadas' => str_contains($diagnostics, '$consolidatedHistory') && str_contains($diagnostics, '$orphanHistory') && str_contains($diagnostics, '/<?php echo count($migrationFiles); ?> atuais') && str_contains($diagnostics, 'migrations históricas consolidadas'),
    'modo escuro nativo possui tokens e superfícies' => str_contains($styleCss, ':root[data-theme="dark"]') && str_contains($styleCss, '--color-background: #1A1F24') && str_contains($styleCss, '--color-panel: #14191D') && str_contains($activitiesCss, '.activity-history-summary') && str_contains($activitiesCss, 'background:var(--ui-panel)'),
    'aparência oferece apenas claro e escuro com claro padrão' => !str_contains($themeJs, "'system'") && str_contains($themeJs, "['light', 'dark']") && str_contains($i18n, "return 'light';"),
    'página de atividades usa tradução estrutural' => str_contains($activities, "stridebr_t('activity.page_title')") && str_contains($activities, "stridebr_t('activity.history')") && str_contains($activities, "stridebr_t('activity.detail_title')"),
    'conteúdo dinâmico usa API por chave' => str_contains($runtime, 'window.StrideBRI18n = api') && str_contains($runtime, 'dataset.i18n') && str_contains($runtime, 'MutationObserver') && !str_contains($runtime, "'Compartilhar atividade':'Share activity'"),
    'login reutiliza shell visual do cadastro' => str_contains($login, 'signup-onboarding-body') && str_contains($login, 'signup-onboarding-shell') && str_contains($login, 'auth-unified-card'),
    'google fica condicionado ao feature flag e configuração' => str_contains($login, '$googleAuthEnabled') && str_contains($login, 'stridebr_auth_google_enabled()') && str_contains($login, '/auth/google.php') && str_contains($auth, 'GOOGLE_OAUTH_ENABLED') && str_contains($auth, 'stridebr_auth_google_feature_enabled()'),
    'oauth usa state e authorization code no servidor' => str_contains($auth, "'state' => \$state") && str_contains($auth, 'https://accounts.google.com/o/oauth2/v2/auth') && str_contains($auth, 'https://oauth2.googleapis.com/token') && str_contains($googleCallback, 'hash_equals') && str_contains($googleCallback, "\$_GET['code']"),
    'openid solicita apenas perfil básico para login' => str_contains($auth, "'scope' => 'openid email profile'") && str_contains($auth, 'https://openidconnect.googleapis.com/v1/userinfo') && str_contains($auth, 'email_verified'),
    'google não persiste token OAuth' => !str_contains($auth, 'refresh_token') && !preg_match('/INSERT[^;]+access_token/is', $auth),
    'endpoint inicial preserva redirect seguro' => str_contains($googleStart, 'stridebr_safe_redirect') || str_contains($auth, 'stridebr_safe_redirect'),
    'migração google é idempotente e única' => str_contains($migration, 'ADD COLUMN IF NOT EXISTS google_sub') && str_contains($migration, 'CREATE UNIQUE INDEX IF NOT EXISTS ux_usuarios_google_sub'),
    'variáveis google documentadas' => str_contains($env, 'GOOGLE_OAUTH_ENABLED=0') && str_contains($env, 'GOOGLE_OAUTH_CLIENT_ID=') && str_contains($env, 'GOOGLE_OAUTH_CLIENT_SECRET=') && str_contains($env, 'GOOGLE_OAUTH_REDIRECT_URI='),
    'google desativado por padrão e protegido no servidor' => str_contains($auth, "getenv('GOOGLE_OAUTH_ENABLED') ?: '0'") && substr_count($auth, 'stridebr_auth_google_enabled()') >= 3 && str_contains($googleStart, 'stridebr_auth_google_start'),
];

$failed = array_keys(array_filter($checks, static fn(bool $ok): bool => !$ok));
if ($failed !== []) {
    fwrite(STDERR, "Falhas em idioma/tema/Google:\n- " . implode("\n- ", $failed) . "\n");
    exit(1);
}

printf("✓ i18n/theme/google static: %d assertions\n", count($checks));
