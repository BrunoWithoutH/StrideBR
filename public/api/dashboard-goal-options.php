<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: private, no-store');

$idUsuario = stridebr_require_login();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método não permitido.']);
    exit;
}

require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/src/function/dashboard.php';

try {
    $available = dashboardMetasDisponiveis($pdo);
    stridebr_session_release();
    if (!$available) {
        http_response_code(503);
        echo json_encode(['ok' => false, 'error' => 'Metas indisponíveis.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    $modalidades = array_map(static fn(array $item): array => [
        'id' => (string) $item['idmodalidade'],
        'name' => (string) $item['nome'],
        'favorite' => stridebr_db_bool($item['favorita'] ?? false),
    ], dashboardListarModalidades($pdo, $idUsuario));
    $exercicios = array_map(static fn(array $item): array => [
        'id' => (string) $item['idexercicio'],
        'name' => (string) $item['nome'],
        'modalities' => (string) ($item['modalidades'] ?? ''),
    ], dashboardListarExerciciosMeta($pdo, $idUsuario));
    echo json_encode(['ok' => true, 'modalities' => $modalidades, 'exercises' => $exercicios], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log('StrideBR dashboard goal options: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Não foi possível carregar as opções de meta.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
