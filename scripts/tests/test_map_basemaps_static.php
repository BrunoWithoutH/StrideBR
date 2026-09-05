<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$files = [
    'helper' => file_get_contents($root . '/public/assets/js/map-basemaps.js'),
    'activities' => file_get_contents($root . '/public/assets/js/atividades.js'),
    'gps' => file_get_contents($root . '/public/assets/js/gps-recorder.js'),
    'app' => file_get_contents($root . '/src/includes/app.php'),
    'activities_page' => file_get_contents($root . '/public/user/atividades.php'),
    'edit_page' => file_get_contents($root . '/public/user/editatividade.php'),
    'record_page' => file_get_contents($root . '/public/user/gravar-atividade.php'),
    'env' => file_get_contents($root . '/.env.example'),
    'docs' => file_get_contents($root . '/docs/MAPS.md'),
    'share_css' => file_get_contents($root . '/public/assets/css/activity-sharing.css'),
];
$count = 0;
$assert = static function (bool $condition, string $message) use (&$count): void {
    $count++;
    if (!$condition) {
        fwrite(STDERR, "Falha: {$message}\n");
        exit(1);
    }
};

$assert(str_contains($files['helper'], 'World_Imagery/MapServer/tile/{z}/{y}/{x}?token='), 'World Imagery precisa ficar centralizado no helper');
$assert(str_contains($files['helper'], 'Elevation/World_Hillshade/MapServer/tile/{z}/{y}/{x}?token='), 'World Hillshade precisa ficar centralizado no helper');
$assert(str_contains($files['helper'], 'stridebr.map.basemap'), 'preferência local precisa ter chave estável');
$assert(str_contains($files['helper'], 'OpenStreetMap'), 'helper precisa manter atribuição OSM');
$assert(str_contains($files['helper'], 'maxNativeZoom: 19') && substr_count($files['helper'], 'maxZoom: 23') >= 3, 'basemaps precisam compartilhar limite lógico sem requisitar OSM além do zoom nativo');
$assert(str_contains($files['helper'], 'Powered by <a href="https://www.esri.com/'), 'helper precisa manter atribuição Esri');
$assert(substr_count($files['activities'], 'StrideBRBasemaps?.attach(map, {controls: true, remember: true})') === 1 && str_contains($files['activities'], 'activity:route-open-target'), 'rota principal e rotas de trecho precisam compartilhar o mesmo mapa com controle de basemap');
$assert(str_contains($files['activities'], "StrideBRBasemaps?.attach(section._routeMap, {initial: 'street', remember: false})"), 'mapa de detalhe deve reutilizar helper sem herdar preferência');
$assert(substr_count($files['gps'], "StrideBRBasemaps?.attach(") === 2, 'mapas GPS devem reutilizar o helper sem receber seletor');
$assert(str_contains($files['gps'], "{initial: 'street', remember: false}"), 'GPS deve permanecer em Mapa');
$assert(str_contains($files['app'], "getenv('STRIDEBR_MAPS_ARCGIS_KEY')"), 'backend precisa ler apenas a chave pública de mapas');
$assert(str_contains($files['env'], 'STRIDEBR_MAPS_ARCGIS_KEY='), '.env.example precisa ter placeholder');
$assert(str_contains($files['activities_page'], 'stridebr_maps_runtime_script()') && str_contains($files['edit_page'], 'stridebr_maps_runtime_script()') && str_contains($files['record_page'], 'stridebr_maps_runtime_script()'), 'helper precisa ser carregado nas páginas que criam mapas');
$assert(str_contains($files['docs'], 'STRIDEBR_MAPS_ARCGIS_KEY'), 'configuração da chave precisa estar documentada');
$assert(str_contains($files['docs'], 'stridebr.map.basemap'), 'persistência local precisa estar documentada');
$assert(str_contains($files['share_css'], 'left: 0;') && str_contains($files['share_css'], 'right: auto;') && str_contains($files['share_css'], 'bottom: calc(100% + var(--space-2));'), 'menu de compartilhamento precisa abrir para dentro e acima');
$assert(str_contains($files['share_css'], 'max-height: min(320px, calc(100dvh - 120px));') && str_contains($files['share_css'], 'overflow-y: auto;'), 'popover precisa proteger o limite vertical');
$assert(!str_contains($files['share_css'], 'z-index: 99999'), 'popover não pode usar z-index arbitrário');
$assert(str_contains($files['share_css'], 'z-index: var(--z-popover);'), 'popover precisa usar token de stacking');

echo "✓ map basemaps static: {$count} assertions\n";
