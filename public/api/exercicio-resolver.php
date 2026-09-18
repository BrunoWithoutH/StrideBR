<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/app.php';
$userId = stridebr_require_login();
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/src/function/exercise_resolver.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    exit;
}
$name = trim((string) ($_GET['name'] ?? ''));
if (stridebr_length($name) > 120) {
    http_response_code(422);
    echo json_encode(['ok'=>false]);
    exit;
}
stridebr_session_release();
try {
    $catalog = stridebr_exercise_catalog_for_user($pdo, $userId);
    $resolution = stridebr_exercise_resolve_entry($catalog, ['nome'=>$name]);
    $options = stridebr_exercise_search_catalog($catalog, $name, [], 8);
    if (is_array($resolution['match'] ?? null)) array_unshift($options, $resolution['match']);
    foreach ($resolution['suggestions'] ?? [] as $suggestion) $options[] = $suggestion;
    $unique = [];
    foreach ($options as $item) {
        $id = (string) ($item['idexercicio'] ?? '');
        if ($id === '' || isset($unique[$id])) continue;
        $unique[$id] = array_intersect_key($item, array_flip(['idexercicio','nome','slug','equipamento','tipo_registro','grupos_musculares_primarios','idusuario']));
    }
    echo json_encode(['ok'=>true,'resolution'=>$resolution,'options'=>array_slice(array_values($unique),0,8)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $error) {
    error_log('StrideBR exercise resolution: ' . get_class($error) . ': ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['ok'=>false]);
}
