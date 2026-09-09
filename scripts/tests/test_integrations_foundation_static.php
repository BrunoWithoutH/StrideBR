<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);
$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    $checks++;
    if (!$ok) {
        fwrite(STDERR, "Integrations foundation static failed: {$message}\n");
        exit(1);
    }
};

$migration = $read('src/database/migrations/20260903_v1_rc.sql');
$strengthMigration = $read('src/database/migrations/20260903_v1_rc.sql');
$providerMigration = $read('src/database/migrations/20260908_integrations_providers.sql');
$integrations = $read('src/function/integrations.php');
$settings = $read('public/user/settings.php') . $read('src/layout/integration_cards.php');
$profile = $read('public/user/perfil.php');
$onboarding = $read('public/user/onboarding.php');
$activityPage = $read('public/user/atividades.php');
$activityJs = $read('public/assets/js/atividades.js');
$activityDetails = $read('src/layout/activity_log_details.php');
$env = $read('.env.example');
$syncScript = $read('scripts/sync_integrations.php');

$assert(str_contains($migration, 'CREATE TABLE IF NOT EXISTS integracoes_usuario') && str_contains($migration, 'origem_provedor') && str_contains($migration, 'id_externo'), 'a base de conexões e deduplicação externa precisa existir.');
$assert(str_contains($migration, 'uq_registros_atividade_origem_externa'), 'atividades externas precisam ser deduplicadas por provedor e id.');
$assert(str_contains($integrations, "'garmin' =>") && str_contains($integrations, "'strava' =>") && str_contains($integrations, "'polar' =>") && str_contains($integrations, "'google_health' =>") && str_contains($integrations, "'coros' =>") && str_contains($integrations, "'health_connect' =>") && str_contains($integrations, "'apple_health' =>") && !str_contains($integrations, "'fitbit' =>"), 'o catálogo ativo precisa usar Google Health/COROS e aposentar Fitbit Web.');
$assert(str_contains($providerMigration, "'google_health', 'coros'") && str_contains($providerMigration, "'fitbit'"), 'migration mínima precisa liberar providers novos preservando histórico Fitbit.');
$assert(str_contains($integrations, 'aes-256-gcm') && str_contains($env, 'STRIDEBR_INTEGRATIONS_SECRET='), 'tokens externos precisam ser criptografados com segredo separado.');
$assert(str_contains($integrations, 'stridebr_integrations_duplicate') && str_contains($integrations, 'stridebr_integrations_store_activity'), 'sincronização precisa passar pelo normalizador e deduplicador comum.');
$assert(str_contains($integrations, 'stridebr_integrations_sync_strava') && str_contains($integrations, 'stridebr_integrations_sync_polar') && str_contains($integrations, 'stridebr_integrations_sync_google_health') && str_contains($integrations, 'stridebr_integrations_sync_coros'), 'Strava, Polar, Google Health e COROS precisam ter sincronização de entrada implementada.');
$assert(str_contains($integrations, 'https://www.polaraccesslink.com/v4/data') && !str_contains($integrations, 'polaraccesslink.com/v3') && !str_contains($integrations, 'api.fitbit.com'), 'endpoints ativos precisam estar sem Polar/Fitbit legado.');
$assert(str_contains($integrations, 'stridebr_integrations_sync_suunto') && str_contains($integrations, 'Ocp-Apim-Subscription-Key') && str_contains($env, 'SUUNTO_SUBSCRIPTION_KEY='), 'Suunto precisa ter OAuth, chave de assinatura e sincronização de entrada pela Cloud API.');
$assert(str_contains($syncScript, 'stridebr_integrations_periodic_providers()') && str_contains($syncScript, 'stridebr_integrations_sync_detailed(') && !str_contains($syncScript, "'coros', 'suunto'"), 'runner oportunista precisa excluir COROS e aposentar Fitbit.');
$assert(str_contains($settings, 'id="conexoes"') && str_contains($settings, '/auth/integration.php?provider=') && str_contains($settings, "stridebr_t('settings.show_connection')"), 'Editar perfil precisa centralizar conexão e exposição pública.');
$assert(strpos($settings, 'id="conexoes"') > strpos($settings, "stridebr_t('settings.links_social')"), 'Conexões deve ficar na parte baixa de Editar perfil, junto da área de links e redes.');
$assert(str_contains($onboarding, "stridebr_t('onboarding.connect_title')") && str_contains($onboarding, 'data-initial-step'), 'onboarding precisa apresentar conexões sem torná-las obrigatórias.');
$assert(str_contains($profile, "'connected_garmin' => 'Garmin Connect'") && str_contains($profile, 'mostrar_perfil'), 'perfil público precisa respeitar opt-in das conexões.');
$assert(str_contains($activityPage, 'activity_log_details.php') && str_contains($activityDetails, 'data-effort-range') && !str_contains($activityPage, 'Inclua só o que fizer sentido para esta atividade.'), 'registro precisa usar RPE em slider e copy enxuta.');
$assert(str_contains($activityJs, "const period = clock < 5 * 60 ? 'late_night'") && str_contains($activityJs, 'activity.auto_title.') && !str_contains($activityJs, 'de tardinha'), 'títulos automáticos precisam usar períodos naturais.');
$assert(str_contains($strengthMigration, 'CREATE TABLE IF NOT EXISTS series_exercicio_atividade') && str_contains($strengthMigration, 'carga_kg') && str_contains($strengthMigration, 'repeticoes'), 'progresso de força precisa ter séries, carga e repetições na base.');

printf("✓ integrations/strength foundation static: %d assertions\n", $checks);
