<?php
// Test-only router, outside public/. Never authenticates or persists real accounts.
if (PHP_SAPI !== 'cli-server') { http_response_code(404); exit; }
if (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) !== '/__integration_cards') return false;
putenv('STRIDEBR_INTEGRATIONS_SECRET=synthetic-test-secret-123456789012345');
foreach (['STRAVA_CLIENT_ID','STRAVA_CLIENT_SECRET','POLAR_CLIENT_ID','POLAR_CLIENT_SECRET','GOOGLE_HEALTH_CLIENT_ID','GOOGLE_HEALTH_CLIENT_SECRET','SUUNTO_CLIENT_ID','SUUNTO_CLIENT_SECRET','SUUNTO_SUBSCRIPTION_KEY'] as $key) putenv($key.'=synthetic');
require_once dirname(__DIR__,2).'/src/includes/app.php';
require_once dirname(__DIR__,2).'/src/function/integrations.php';
$integrationRegistry = stridebr_integrations_registry();
$base = ['status'=>'conectado','sincronizar_atividades'=>true,'ultima_sincronizacao_em'=>date('c'),'metadados'=>[]];
$integrationConnections = [
    'strava'=>$base+['usuario_externo_nome'=>'Atleta Teste'],
    'polar'=>array_replace($base,['status'=>'erro']),
    'google_health'=>array_replace($base,['status'=>'erro','metadados'=>['sync'=>['reauthorize'=>true]]]),
    'coros'=>$base,
];
$integrationSyncReady = array_fill_keys(['strava','polar','google_health','coros','suunto'],true);
?>
<!doctype html><html lang="<?php echo stridebr_e(stridebr_locale()); ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<?php echo stridebr_ui_boot_script(); ?>
<link rel="stylesheet" href="/assets/css/style.css"><link rel="stylesheet" href="/assets/css/ui-refresh.css"><title>Integration fixture</title></head><body>
<main class="main-content"><div class="page-shell settings-shell"><section class="content-card settings-connections"><h1><?php echo stridebr_e(stridebr_t('settings.connections')); ?></h1>
<?php require dirname(__DIR__,2).'/src/layout/integration_cards.php'; ?>
</section></div></main></body></html>
