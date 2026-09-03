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
$integrations = $read('src/function/integrations.php');
$settings = $read('public/user/settings.php');
$profile = $read('public/user/perfil.php');
$onboarding = $read('public/user/onboarding.php');
$activityPage = $read('public/user/atividades.php');
$activityJs = $read('public/assets/js/atividades.js');
$env = $read('.env.example');
$syncScript = $read('scripts/sync_integrations.php');

$assert(str_contains($migration, 'CREATE TABLE IF NOT EXISTS integracoes_usuario') && str_contains($migration, 'origem_provedor') && str_contains($migration, 'id_externo'), 'a base de conexões e deduplicação externa precisa existir.');
$assert(str_contains($migration, 'uq_registros_atividade_origem_externa'), 'atividades externas precisam ser deduplicadas por provedor e id.');
$assert(str_contains($integrations, "'garmin' =>") && str_contains($integrations, "'strava' =>") && str_contains($integrations, "'polar' =>") && str_contains($integrations, "'health_connect' =>") && str_contains($integrations, "'apple_health' =>") && str_contains($integrations, "'fitbit' =>"), 'o catálogo de provedores precisa incluir relógios e hubs móveis principais.');
$assert(str_contains($integrations, 'aes-256-gcm') && str_contains($env, 'STRIDEBR_INTEGRATIONS_SECRET='), 'tokens externos precisam ser criptografados com segredo separado.');
$assert(str_contains($integrations, 'stridebr_integrations_duplicate') && str_contains($integrations, 'stridebr_integrations_store_activity'), 'sincronização precisa passar pelo normalizador e deduplicador comum.');
$assert(str_contains($integrations, 'stridebr_integrations_sync_strava') && str_contains($integrations, 'stridebr_integrations_sync_polar') && str_contains($integrations, 'stridebr_integrations_sync_fitbit') && str_contains($integrations, '/1/user/-/activities/list.json') && str_contains($integrations, '.tcx'), 'Strava, Polar e Fitbit precisam ter sincronização de entrada implementada.');
$assert(str_contains($integrations, 'stridebr_integrations_sync_suunto') && str_contains($integrations, 'Ocp-Apim-Subscription-Key') && str_contains($env, 'SUUNTO_SUBSCRIPTION_KEY='), 'Suunto precisa ter OAuth, chave de assinatura e sincronização de entrada pela Cloud API.');
$assert(str_contains($syncScript, "['strava', 'polar', 'fitbit', 'suunto']") && str_contains($syncScript, 'stridebr_integrations_sync('), 'sincronização automática precisa ter runner para cron sem depender da tela aberta.');
$assert(str_contains($settings, 'id="conexoes"') && str_contains($settings, '/auth/integration.php?provider=') && str_contains($settings, 'Mostrar esta conexão no meu perfil'), 'Editar perfil precisa centralizar conexão e exposição pública.');
$assert(strpos($settings, 'id="conexoes"') > strpos($settings, '<h2>Links e redes</h2>'), 'Conexões deve ficar na parte baixa de Editar perfil, junto da área de links e redes.');
$assert(str_contains($onboarding, 'Conecte onde você já treina') && str_contains($onboarding, 'data-initial-step'), 'onboarding precisa apresentar conexões sem torná-las obrigatórias.');
$assert(str_contains($profile, "'connected_garmin' => 'Garmin Connect'") && str_contains($profile, 'mostrar_perfil'), 'perfil público precisa respeitar opt-in das conexões.');
$assert(str_contains($activityPage, 'data-effort-range') && !str_contains($activityPage, 'Inclua só o que fizer sentido para esta atividade.'), 'registro precisa usar RPE em slider e copy enxuta.');
$assert(str_contains($activityJs, "period = 'pela manhã'") && str_contains($activityJs, "period = 'à tarde'") && !str_contains($activityJs, 'de tardinha'), 'títulos automáticos precisam usar períodos naturais.');
$assert(str_contains($strengthMigration, 'CREATE TABLE IF NOT EXISTS series_exercicio_atividade') && str_contains($strengthMigration, 'carga_kg') && str_contains($strengthMigration, 'repeticoes'), 'progresso de força precisa ter séries, carga e repetições na base.');

printf("✓ integrations/strength foundation static: %d assertions\n", $checks);
