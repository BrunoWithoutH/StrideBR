<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/includes/app.php';
require_once dirname(__DIR__) . '/src/config/pg_config.php';
require_once dirname(__DIR__) . '/src/function/activity_stream_service.php';
require_once dirname(__DIR__) . '/src/function/zone_profile_service.php';
require_once dirname(__DIR__) . '/src/function/activity_analysis_service.php';

$options = getopt('', ['user::', 'after::', 'limit::', 'analysis']);
$userId = trim((string) ($options['user'] ?? ''));
$after = trim((string) ($options['after'] ?? ''));
$limit = max(1, min(5000, (int) ($options['limit'] ?? 250)));
$withAnalysis = array_key_exists('analysis', $options);

$where = ["ra.excluido_em IS NULL", "ra.status = 'concluido'", 'b.idregistro IS NULL'];
$params = [];
if ($userId !== '') {
    $where[] = 'ra.idusuario = :usuario';
    $params[':usuario'] = $userId;
}
if ($after !== '') {
    $where[] = 'ra.idregistro > :after';
    $params[':after'] = $after;
}
$where[] = "(EXISTS (SELECT 1 FROM atividade_importacoes ai WHERE ai.idusuario=ra.idusuario AND ai.idregistro=ra.idregistro AND ai.status='importado' AND ai.series_temporais IS NOT NULL) OR EXISTS (SELECT 1 FROM rotas_atividade rota WHERE rota.idregistro=ra.idregistro AND rota.pontos_metadata IS NOT NULL))";

$sql = 'SELECT ra.idregistro,ra.idusuario FROM registros_atividade ra LEFT JOIN activity_stream_bundles b ON b.idregistro=ra.idregistro WHERE ' . implode(' AND ', $where) . ' ORDER BY ra.idregistro LIMIT ' . $limit;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$materialized = 0;
$analyzed = 0;
$skipped = 0;
$failed = 0;
$lastId = null;
foreach ($rows as $row) {
    $lastId = (string) $row['idregistro'];
    try {
        $result = activityStreamEnsureMaterialized($pdo, (string) $row['idusuario'], $lastId);
        if (!empty($result['materialized'])) $materialized++;
        else $skipped++;
        if ($withAnalysis && !empty($result['bundle'])) {
            activityAnalysisCompute($pdo, (string) $row['idusuario'], $lastId, true);
            $analyzed++;
        }
    } catch (Throwable $error) {
        $failed++;
        fwrite(STDERR, $lastId . ': ' . $error->getMessage() . PHP_EOL);
    }
}

printf("Streams materializados: %d | analysis: %d | sem fonte suficiente: %d | falhas: %d | analisadas: %d\n", $materialized, $analyzed, $skipped, $failed, count($rows));
if ($lastId !== null) printf("Próximo --after: %s\n", $lastId);
exit($failed > 0 ? 1 : 0);
