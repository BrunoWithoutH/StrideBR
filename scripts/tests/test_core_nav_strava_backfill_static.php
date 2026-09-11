<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);
$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    $checks++;
    if (!$ok) {
        fwrite(STDERR, "Core nav/Strava static failed: {$message}\n");
        exit(1);
    }
};

$header = $read('src/layout/header.php');
$footer = $read('src/layout/footer.php');
$profile = $read('public/user/perfil.php');
$settingsWorkspace = $read('src/layout/settings_workspace.php');
$cards = $read('src/layout/integration_cards.php');
$integrations = $read('src/function/integrations.php');
$support = $read('src/function/integration_sync_support.php');
$callback = $read('public/auth/integration-callback.php');
$action = $read('public/function/integration-action.php');
$webhook = $read('public/webhooks/strava.php');
$activities = $read('public/user/atividades.php');
$activitiesJs = $read('public/assets/js/atividades.js');
$activityCss = $read('public/assets/css/atividades.css');
$scripts = $read('public/assets/js/scripts.js');
$ui = $read('public/assets/css/ui-refresh.css');
$pt = $read('src/i18n/pt-BR.php');
$en = $read('src/i18n/en.php');

$assert(str_contains($header, 'desktop-account-menu') && str_contains($header, 'mobile-global-menu'), 'Desktop e mobile precisam ter menus de responsabilidades distintas.');
$assert(str_contains($header, "stridebr_t('settings.connections')") && str_contains($header, "stridebr_t('nav.connections_hint')"), 'Conexões precisa ser descobrível no menu de conta.');
$assert(!str_contains($footer, 'data-mobile-more-toggle') && !str_contains($footer, 'mobile-more-sheet'), 'Bottom nav não pode manter o antigo Mais.');
$assert(str_contains($footer, 'mobile-profile-tab') && str_contains($footer, 'mobile-nav-avatar') && str_contains($footer, "stridebr_t('nav.profile_tab')"), 'Bottom nav mobile precisa ter Perfil com avatar.');
$assert(str_contains($header, 'data-header-menu-close') && str_contains($scripts, "[data-header-menu-close]"), 'Menu global mobile precisa de backdrop/fechamento explícito.');
$assert(str_contains($ui, '.mobile-global-menu[open]>.mobile-global-menu-backdrop') && str_contains($ui, 'min-height:44px'), 'Menu mobile precisa sobrepor corretamente e preservar touch targets.');
$assert(str_contains($profile, '/user/edit-profile.php') && str_contains($profile, '/user/settings.php?view=connections'), 'Perfil próprio precisa oferecer atalhos de conta/conexões.');
$assert(str_contains($settingsWorkspace, "'connections' =>") && str_contains($settingsWorkspace, '/user/settings.php?view=connections'), 'Settings precisa manter Conexões como seção de primeira classe.');

$assert(str_contains($cards, "integrations.strava.connect") && !preg_match('/>\s*S\s*</', $cards), 'Strava não deve ser representado por uma letra isolada.');
foreach (['recent_title','history_title','paused_title','retry_title','reauth_title','complete_title','disabled_title','none'] as $key) {
    $assert(str_contains($cards, "integrations.strava.{$key}") && str_contains($pt, "'integrations.strava.{$key}'") && str_contains($en, "'integrations.strava.{$key}'"), "Estado Strava {$key} precisa de UI e i18n.");
}
$assert(!str_contains($cards, '%') && !str_contains($cards, 'progress-bar'), 'Backfill não pode fabricar percentual desconhecido.');
$assert(str_contains($integrations, "metadados = jsonb_set") && str_contains($integrations, "'{backfill}'") && str_contains($integrations, "jsonb_build_object('_legacy_array', metadados)"), 'Backfill deve persistir no metadata existente sem descartar raiz legacy-array.');
$assert(str_contains($integrations, "'before' =>") && str_contains($integrations, '$before') && str_contains($integrations, '$perPage') && str_contains($integrations, "'page' => 1"), 'Checkpoint precisa usar cursor temporal before.');
$assert(str_contains($integrations, '$perPage = 10') && str_contains($integrations, '$detailBudget = 3'), 'Backfill precisa usar lote pequeno e orçamento de detalhes.');
$assert(str_contains($integrations, 'if ($detailBudget <= 0 || microtime(true) >= $deadline)') && str_contains($integrations, "'backfill_deferred' => true"), 'Backfill parcial precisa ser retomável sem avançar cursor.');
$assert(str_contains($support, "provedor <> 'strava'") && str_contains($support, "backfill'->>'last_attempt_at'"), 'Runner precisa combinar recent/reconciliation e fairness do backfill Strava.');
$assert(str_contains($support, "if (!stridebr_db_bool(\$connection['sincronizar_atividades'] ?? true)) return false;"), 'Sync desativado precisa bloquear recent/backfill inclusive manual.');
$assert(str_contains($integrations, "CAST(:signed AS boolean)") && str_contains($integrations, "? 'true' : 'false'"), 'Webhook precisa bindar signature_verified como boolean PostgreSQL seguro.');
$assert(str_contains($webhook, 'STRAVA_WEBHOOK_SIGNING_SECRET') || str_contains($integrations, "'signing_secret'"), 'Webhook deve preservar signing secret opcional sem substituição.');
$assert(str_contains($callback, '/user/settings.php?view=connections#conexoes') && str_contains($action, '/user/settings.php?view=connections#conexoes'), 'OAuth/sync deve retornar para Settings Conexões.');

$assert(str_contains($activities, 'data-history-toolbar-normal') && str_contains($activities, 'data-bulk-toolbar') && str_contains($activities, 'data-bulk-dialog'), 'Seleção em Atividades deve reutilizar o header e dialog de edição.');
$assert(!str_contains($activities, 'class="activity-bulk-bar"'), 'Seleção não deve inserir uma segunda barra persistente abaixo do header.');
$assert(str_contains($activitiesJs, 'openBulkDialog') && str_contains($activitiesJs, 'closeBulkDialog') && str_contains($activitiesJs, 'bulkToolbar.hidden = !bulkMode'), 'JS batch precisa alternar toolbar e dialog sem layout shift estrutural.');
$assert(str_contains($activitiesJs, 'lastPassiveHistoryCheckAt') && str_contains($activitiesJs, "loadHistory({background: true, preserveDetail: true})"), 'Atividades precisa revalidar discretamente a lista própria em focus/visibility.');
$assert(!str_contains($activitiesJs, '/function/integration-action.php') && !str_contains($activitiesJs, '/auth/integration'), 'Focus de Atividades não pode disparar sync Strava.');
$assert(str_contains($activitiesJs, "window.scrollTo(0, scrollY)") && str_contains($activitiesJs, 'bulkMode'), 'Refresh passivo precisa preservar scroll e respeitar modo seleção.');
$assert(str_contains($activityCss, '.activity-bulk-dialog') && str_contains($activityCss, '.activity-bulk-toolbar-actions'), 'Batch desktop/mobile precisa ter dialog e toolbar contextual próprios.');

printf("✓ Core navigation + Strava backfill static: %d assertions\n", $checks);
