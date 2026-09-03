<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';
$idUsuario = stridebr_require_login();
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
stridebr_session_release();

try {
    $stmt = $pdo->prepare("SELECT ra.idregistro, COALESCE(NULLIF(ra.titulo, ''), m.nome) AS titulo, m.nome AS modalidade_nome, ra.data_inicio
                           FROM stridebr.registros_atividade ra
                           JOIN stridebr.modalidades m ON m.idmodalidade = ra.idmodalidade
                           WHERE ra.idusuario = :usuario AND ra.excluido_em IS NULL
                             AND ra.status <> 'cancelado'
                             AND NOT EXISTS (SELECT 1 FROM stridebr.rotas_atividade rota WHERE rota.idregistro = ra.idregistro)
                           ORDER BY ra.data_inicio DESC, ra.idregistro DESC
                           LIMIT 120");
    $stmt->execute([':usuario' => $idUsuario]);
    $items = array_map(static fn(array $row): array => [
        'id' => (string) $row['idregistro'],
        'title' => (string) $row['titulo'],
        'modality' => (string) $row['modalidade_nome'],
        'start' => (string) $row['data_inicio'],
    ], $stmt->fetchAll());
    echo json_encode(['ok' => true, 'items' => $items], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    error_log('StrideBR route targets API: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Não foi possível carregar as atividades disponíveis.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
