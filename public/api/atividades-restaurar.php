<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: private, no-store');

$idUsuario = stridebr_require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método não permitido.']);
    exit;
}

try {
    stridebr_verify_csrf();
    require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
    require_once dirname(__DIR__, 2) . '/src/function/atividade_modelo.php';
    $ids = is_array($_POST['ids'] ?? null) ? $_POST['ids'] : [$_POST['id'] ?? ''];
    $ids = array_values(array_unique(array_filter(array_map(static fn($id): string => trim((string) $id), $ids))));
    if ($ids === [] || count($ids) > 100) throw new InvalidArgumentException('Atividades inválidas para restauração.');
    $restored = 0;
    foreach ($ids as $id) {
        if (atividadeRestaurarRegistro($pdo, $id, $idUsuario)) $restored++;
    }
    echo json_encode(['ok' => $restored > 0, 'restored' => $restored], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log('StrideBR restore activities: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Não foi possível restaurar a atividade.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
