<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/includes/app.php';
require_once dirname(__DIR__) . '/src/config/pg_config.php';
require_once dirname(__DIR__) . '/src/function/activity_energy.php';

$options = getopt('', ['user::', 'limit::', 'all']);
$userId = trim((string) ($options['user'] ?? ''));
$limit = max(1, min(100000, (int) ($options['limit'] ?? 5000)));
$recalculateAll = array_key_exists('all', $options);

$where = ["ra.excluido_em IS NULL", "ra.status = 'concluido'"];
$params = [];
if ($userId !== '') {
    $where[] = 'ra.idusuario = :usuario';
    $params[':usuario'] = $userId;
}
if (!$recalculateAll) {
    $where[] = '(ra.calorias_ativas_estimadas IS NULL OR ra.data_calorias_atualizacao IS NULL)';
}

$sql = 'SELECT ra.idregistro, ra.idusuario FROM registros_atividade ra WHERE ' . implode(' AND ', $where) . ' ORDER BY ra.data_inicio ASC LIMIT ' . $limit;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$updated = 0;
$skipped = 0;
$failed = 0;
foreach ($rows as $row) {
    try {
        $result = atividadeEnergiaAtualizarRegistro($pdo, (string) $row['idregistro'], (string) $row['idusuario']);
        if ($result !== null && is_array($result['estimate'] ?? null)) $updated++;
        else $skipped++;
    } catch (Throwable $error) {
        $failed++;
        fwrite(STDERR, (string) $row['idregistro'] . ': ' . $error->getMessage() . PHP_EOL);
    }
}

printf("Energia recalculada: %d | sem estimativa: %d | falhas: %d | analisadas: %d\n", $updated, $skipped, $failed, count($rows));
exit($failed > 0 ? 1 : 0);
